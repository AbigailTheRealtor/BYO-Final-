<?php

namespace Tests\Feature\Agent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as MlsMeta;
use App\Support\Listing\ListingFlag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One stored `is_sold` value, one meaning, on every Agent surface.
 *
 * THE DEFECT THIS FILE PINS
 * -------------------------
 * The shared Agent listing page read the flag as `(bool) $auction->is_sold`.
 * The hub, the Hire hub and all four role models read it through the strict
 * list `[true, 1, '1', 'true']`. They disagree on exactly one value that real
 * rows hold: the string 'false', which BuyerOfferListing and the Hire Buyer
 * wizard write into `buyer_agent_auctions.is_sold`, a varchar column.
 * `(bool) 'false'` is true — so the shared page announced an accepted
 * transaction on a listing the hub beside it correctly called open.
 *
 * WHAT THE TESTS ASSERT
 * ---------------------
 * Every representation the four tables can hold is stored raw, then read back
 * through the real hub, the real shared page (via the hub's own typed link) and
 * the model's status accessor, and all three must agree. `null` is covered at
 * the model and contract level only: every one of the four columns is NOT NULL,
 * so no stored row can hold it.
 */
class AgentIsSoldSemanticsTest extends TestCase
{
    use DatabaseTransactions;

    /** Stored representations that mean SOLD. */
    private const SOLD = [
        'true (bool)' => true,
        '1 (int)'     => 1,
        "'1'"         => '1',
        "'true'"      => 'true',
    ];

    /** Stored representations that mean NOT SOLD. */
    private const NOT_SOLD = [
        'false (bool)' => false,
        '0 (int)'      => 0,
        "'0'"          => '0',
        "'' (empty)"   => '',
        "'false'"      => 'false',
    ];

    private const TABLES = [
        'seller'   => 'seller_agent_auctions',
        'landlord' => 'landlord_agent_auctions',
        'buyer'    => 'buyer_agent_auctions',
        'tenant'   => 'tenant_agent_auctions',
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['user_type' => 'seller']);

        config(['offer.playoff_access.allowed_user_ids' => '*']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function sellerListing(array $meta = []): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '1 Sold Way',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        // Marks the row as an Offer Listing, which is what puts it in the hub.
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('listing_title', 'SELLER IS-SOLD LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function landlordListing(array $meta = []): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LANDLORD IS-SOLD LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $listing->saveMeta('listing_title', 'LANDLORD IS-SOLD LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function buyerListing(array $meta = []): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '3 Sold Way',
            'title'       => 'BUYER IS-SOLD LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $listing->saveMeta('listing_title', 'BUYER IS-SOLD LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function tenantListing(array $meta = []): TenantAgentAuction
    {
        // TenantAgentAuction declares neither $fillable nor $guarded, and has no
        // `title` column — the hub falls back to `listing_title`.
        $listing = new TenantAgentAuction();
        $listing->user_id     = $this->owner->id;
        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        $listing->saveMeta('listing_title', 'TENANT IS-SOLD LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function listingFor(string $role, array $meta = []): object
    {
        return match ($role) {
            'seller'   => $this->sellerListing($meta),
            'landlord' => $this->landlordListing($meta),
            'buyer'    => $this->buyerListing($meta),
            'tenant'   => $this->tenantListing($meta),
        };
    }

    private function modelFor(string $role, int $id): object
    {
        return match ($role) {
            'seller'   => SellerAgentAuction::find($id),
            'landlord' => LandlordAgentAuction::find($id),
            'buyer'    => BuyerAgentAuction::find($id),
            'tenant'   => TenantAgentAuction::find($id),
        };
    }

    /**
     * Store the flag exactly as given, bypassing the model — this is the value a
     * writer left behind, not one Eloquent chose. (Laravel binds PHP true/false
     * as 1/0, which is also what every model save of a boolean stores.)
     */
    private function storeRawIsSold(string $role, int $id, mixed $value): void
    {
        DB::table(self::TABLES[$role])->where('id', $id)->update(['is_sold' => $value]);
    }

    private function rawIsSold(string $role, int $id): mixed
    {
        return DB::table(self::TABLES[$role])->where('id', $id)->value('is_sold');
    }

    // ── The contract ───────────────────────────────────────────────────────

    /** @test */
    public function the_contract_names_exactly_four_values_as_set(): void
    {
        foreach (self::SOLD as $label => $value) {
            $this->assertTrue(ListingFlag::isTrue($value), "{$label} must read as sold");
        }

        foreach (self::NOT_SOLD + ['null' => null] as $label => $value) {
            $this->assertFalse(ListingFlag::isTrue($value), "{$label} must read as not sold");
        }

        // The reading this replaces, and the value it gets wrong.
        $this->assertTrue((bool) 'false', 'precondition: a plain cast reads the string false as true');
    }

    // ── E. Every role model reads the matrix the same way ──────────────────

    /** @test */
    public function every_role_model_status_reads_the_matrix_the_same_way(): void
    {
        $classes = [
            SellerAgentAuction::class,
            LandlordAgentAuction::class,
            BuyerAgentAuction::class,
            TenantAgentAuction::class,
        ];

        foreach ($classes as $class) {
            foreach (self::SOLD + self::NOT_SOLD + ['null' => null] as $label => $value) {
                $model = new $class();
                $model->setRawAttributes(['is_sold' => $value]);

                $this->assertSame(
                    array_key_exists($label, self::SOLD),
                    $model->status === 'Hired Agent',
                    class_basename($class) . " {$label}: the status accessor disagrees with the contract",
                );
            }
        }
    }

    // ── A–D, F–I, K. The page, the hub and the model agree, for every role ──

    /** @test */
    public function the_shared_page_the_hub_and_the_model_agree_for_every_stored_representation(): void
    {
        foreach (array_keys(self::TABLES) as $role) {
            $cases = [];

            foreach (self::SOLD + self::NOT_SOLD as $label => $value) {
                $listing = $this->listingFor($role, ['listing_status' => 'Active']);
                $this->storeRawIsSold($role, $listing->id, $value);
                $cases[] = [$label, (int) $listing->id, array_key_exists($label, self::SOLD)];
            }

            $hub = $this->hubRows();

            foreach ($cases as [$label, $id, $sold]) {
                $context = "{$role} is_sold={$label}";
                $row     = $hub["{$role}:{$id}"] ?? null;

                $this->assertNotNull($row, "{$context}: the hub did not list the listing");

                // K — the shared page is reached through the hub's typed link.
                $this->assertSame("/offer/listing/view/{$role}-{$id}", parse_url($row['view_route'], PHP_URL_PATH));

                $pageWorkflow = $this->heroBadges($this->render($row['view_route']))['workflow']['text'] ?? null;
                $modelSold    = $this->modelFor($role, $id)->status === 'Hired Agent';

                $this->assertSame($sold, $row['_sold'], "{$context}: hub");
                $this->assertSame($sold ? 'Accepted' : null, $row['workflow_status_label'], "{$context}: hub badge");
                $this->assertSame($sold ? 'Accepted' : null, $pageWorkflow, "{$context}: shared page");
                $this->assertSame($sold, $modelSold, "{$context}: model status");
            }
        }
    }

    // ── A / B. The production value: a Buyer listing saved with 'false' ────

    /** @test */
    public function a_buyer_listing_saved_with_the_string_false_is_accepted_nowhere(): void
    {
        // BuyerOfferListing and the Hire Buyer wizard both save
        //     $auction->is_sold = $auction->is_sold ?: 'false';
        // into buyer_agent_auctions.is_sold, a varchar column.
        $listing = $this->buyerListing(['listing_status' => 'Active']);
        $this->storeRawIsSold('buyer', $listing->id, 'false');

        $key = "buyer:{$listing->id}";
        $row = $this->hubRows()[$key];

        $hero = $this->heroBadges($this->render($row['view_route']));

        $this->assertArrayNotHasKey('workflow', $hero, 'the shared page must not announce an accepted transaction');
        $this->assertSame('Active', $this->listingText($hero));
        $this->assertNull($row['workflow_status_label']);
        $this->assertFalse($row['_sold']);
        $this->assertArrayNotHasKey($key, $this->hubRows('accepted'), 'the Accepted tab must not hold it');
        $this->assertArrayHasKey($key, $this->hubRows('active'));
        $this->assertSame('Active', $listing->fresh()->status);
    }

    // ── J. The listing-status badge stays independent ──────────────────────

    /** @test */
    public function the_listing_status_badge_is_independent_of_the_workflow_reading(): void
    {
        $mls = [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-IS-SOLD',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ];

        $open = $this->sellerListing($mls);
        $this->storeRawIsSold('seller', $open->id, 'false');

        $closed = $this->sellerListing($mls);
        $this->storeRawIsSold('seller', $closed->id, '1');

        $hub = $this->hubRows();

        // 'false': no transaction, so the feed's status stands on both surfaces.
        $openHero = $this->heroBadges($this->render($hub["seller:{$open->id}"]['view_route']));
        $this->assertArrayNotHasKey('workflow', $openHero);
        $this->assertSame('Pending', $this->listingText($openHero));
        $this->assertSame('Pending', $hub["seller:{$open->id}"]['listing_status_display']);

        // '1': the transaction is the workflow badge; the listing status is
        // ListingStatusDisplay's own answer, in which is_sold outranks the feed.
        $closedHero = $this->heroBadges($this->render($hub["seller:{$closed->id}"]['view_route']));
        $this->assertSame('Accepted', $closedHero['workflow']['text']);
        $this->assertSame('Hired Agent', $this->listingText($closedHero));
        $this->assertSame('Hired Agent', $hub["seller:{$closed->id}"]['listing_status_display']);
    }

    // ── OfferAuction keeps its own boolean cast ───────────────────────────

    /** @test */
    public function a_bare_offer_auction_still_reads_its_boolean_cast(): void
    {
        foreach ([true => 'Accepted', false => null] as $flag => $expected) {
            $offerAuction = OfferAuction::create([
                'user_id'     => $this->owner->id,
                'title'       => 'OFFER AUCTION IS-SOLD',
                'is_draft'    => false,
                'is_approved' => true,
                'is_sold'     => (bool) $flag,
            ]);

            $hero = $this->heroBadges($this->render(route('offer.listing.view', $offerAuction->id)));

            $this->assertSame($expected, $hero['workflow']['text'] ?? null);
            $this->assertSame((bool) $flag, $offerAuction->fresh()->status === 'Accepted', 'the page and the model must agree');
        }
    }

    // ── L. Reading writes nothing ──────────────────────────────────────────

    /** @test */
    public function reading_the_flag_rewrites_no_stored_value(): void
    {
        $listings = [
            ['buyer',    $this->buyerListing(['listing_status' => 'Active']),    'false'],
            ['seller',   $this->sellerListing(['listing_status' => 'Active']),   ''],
            ['landlord', $this->landlordListing(['listing_status' => 'Active']), 'true'],
            ['tenant',   $this->tenantListing(['listing_status' => 'Active']),   'false'],
        ];

        foreach ($listings as [$role, $listing, $value]) {
            $this->storeRawIsSold($role, $listing->id, $value);
        }

        $snapshot = function () use ($listings): array {
            $state = ['oa_count' => OfferAuction::count()];

            foreach ($listings as [$role, $listing]) {
                $state["{$role}:{$listing->id}"] = [
                    'is_sold' => $this->rawIsSold($role, $listing->id),
                    'meta'    => $this->modelFor($role, $listing->id)->meta->pluck('meta_value', 'meta_key')->sort()->toArray(),
                ];
            }

            return $state;
        };

        $before = $snapshot();

        $hub = $this->hubRows();

        foreach (['active', 'pending', 'draft', 'accepted', 'expired'] as $filter) {
            $this->hubRows($filter);
        }

        foreach ($listings as [$role, $listing]) {
            $this->render($hub["{$role}:{$listing->id}"]['view_route']);
        }

        $this->assertSame($before, $snapshot(), 'reading is_sold must never normalise the stored value');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * The hub's rows, keyed "role:id".
     *
     * @return array<string,array>
     */
    private function hubRows(string $filter = 'all'): array
    {
        $response = $this->actingAs($this->owner)->get(route('agent.offer-listings', ['filter' => $filter]));
        $response->assertStatus(200);

        $rows = [];

        foreach ($response->original->getData()['listings'] as $row) {
            $rows[$row['role'] . ':' . $row['id']] = $row;
        }

        return $rows;
    }

    private function render(string $url): string
    {
        $response = $this->actingAs($this->owner)->get($url);
        $response->assertStatus(200);

        return $response->getContent();
    }

    /**
     * The shared page's hero status badges, keyed by surface.
     *
     * @return array<string,array{class:string,text:string}>
     */
    private function heroBadges(string $html): array
    {
        preg_match_all(
            '/<span class="badge bg-([a-z-]+)" data-hero-status="(workflow|listing)">(.*?)<\/span>/s',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $badges = [];

        foreach ($matches as [, $class, $surface, $text]) {
            $badges[$surface] = ['class' => $class, 'text' => trim(html_entity_decode(strip_tags($text)))];
        }

        return $badges;
    }

    /** The hero's listing-status value without its label, or null. */
    private function listingText(array $badges): ?string
    {
        $text = $badges['listing']['text'] ?? null;

        return $text === null ? null : substr($text, strlen('Listing Status: '));
    }
}
