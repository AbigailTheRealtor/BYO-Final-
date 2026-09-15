<?php

namespace Tests\Feature\Agent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Documents\ListingDocumentAccessService;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as MlsMeta;
use App\Support\Listing\ListingFlag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One stored `is_approved` value, one meaning, everywhere the four role models
 * are read.
 *
 * THE DEFECT THIS FILE PINS
 * -------------------------
 * All four role models cast `is_approved` to `boolean`, and Eloquent's boolean
 * cast is `(bool) $value`. `seller_agent_auctions.is_approved` and
 * `buyer_agent_auctions.is_approved` are varchar columns on PostgreSQL, so they
 * keep whatever string a writer sent — and the Hire/Tenant draft path wrote the
 * literal 'false' between 2026-01-20 and 2026-03-31. `(bool) 'false'` is true,
 * so such a row was APPROVED to every reader: the Agent page, both hubs, the
 * public-visibility gates, document access and Ask AI all asked the model, and
 * the model answered with the cast.
 *
 * The models now read the RAW stored value through {@see ListingFlag}. Handing
 * ListingFlag the already-cast value would change nothing — the cast has already
 * turned 'false' into true by then — which is why the tests below store raw
 * values and read them back through the model.
 *
 * WHICH STATES ARE PRODUCTION-REAL
 * --------------------------------
 * Seller and Buyer are varchar on PostgreSQL and on the SQLite test schema
 * alike: both store every representation verbatim, so their database tests use
 * the full matrix. Landlord and Tenant are real `boolean` columns on PostgreSQL,
 * which hands back only true or false; SQLite would keep the string 'false' in
 * the same column, a state production cannot reach. Their database tests
 * therefore store only true and false. The full matrix still applies to all four
 * models at the model level, because the contract is the model's, not the
 * column's. `null` is covered at the model level only: all four columns are
 * NOT NULL.
 *
 * A read-only production count taken 2026-09-11 found no 'false' (or 'true')
 * rows in either varchar table — every row is '0' or '1' — so this changes the
 * answer for no row that exists today. It closes the door rather than moving
 * anybody through it.
 */
class AgentIsApprovedSemanticsTest extends TestCase
{
    use DatabaseTransactions;

    /** Stored representations that mean APPROVED. */
    private const APPROVED = [
        'true (bool)' => true,
        '1 (int)'     => 1,
        "'1'"         => '1',
        "'true'"      => 'true',
    ];

    /** Stored representations that mean NOT APPROVED. */
    private const NOT_APPROVED = [
        'false (bool)' => false,
        '0 (int)'      => 0,
        "'0'"          => '0',
        "'' (empty)"   => '',
        "'false'"      => 'false',
    ];

    /** What a varchar column holds after each write (PHP booleans bind as 1 / 0). */
    private const VARCHAR_STORED = [
        'true (bool)'  => '1',
        '1 (int)'      => '1',
        "'1'"          => '1',
        "'true'"       => 'true',
        'false (bool)' => '0',
        '0 (int)'      => '0',
        "'0'"          => '0',
        "'' (empty)"   => '',
        "'false'"      => 'false',
    ];

    private const MODELS = [
        'seller'   => SellerAgentAuction::class,
        'landlord' => LandlordAgentAuction::class,
        'buyer'    => BuyerAgentAuction::class,
        'tenant'   => TenantAgentAuction::class,
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

    /** An Offer Listing: listed in the Offer hub and served by its public view. */
    private function offerListing(string $role, array $meta = []): object
    {
        $listing = match ($role) {
            'seller'   => SellerAgentAuction::create([
                'user_id' => $this->owner->id, 'address' => '1 Approval Way', 'is_draft' => false, 'is_approved' => true,
            ]),
            'landlord' => LandlordAgentAuction::create([
                'user_id' => $this->owner->id, 'title' => 'LANDLORD IS-APPROVED LISTING', 'is_draft' => false, 'is_approved' => true,
            ]),
            'buyer'    => BuyerAgentAuction::create([
                'user_id' => $this->owner->id, 'title' => 'BUYER IS-APPROVED LISTING', 'is_draft' => false, 'is_approved' => true,
            ]),
            'tenant'   => $this->tenantRow(),
        };

        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('listing_title', strtoupper($role) . ' IS-APPROVED LISTING');

        // An established link, as a published listing has, so a public page
        // render never needs to create one (a write on a GET).
        if (in_array($role, ['seller', 'buyer', 'landlord'], true)) {
            $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $this->owner->id])->id);
        }

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /** A Hire Seller's Agent listing: no Offer Listing stamp, so it is in the Hire hub only. */
    private function sellerHireListing(): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            'user_id' => $this->owner->id, 'address' => '2 Approval Way', 'is_draft' => false, 'is_approved' => true,
        ]);

        $listing->saveMeta('listing_title', 'SELLER HIRE IS-APPROVED LISTING');

        return $listing->fresh();
    }

    private function tenantRow(): TenantAgentAuction
    {
        // TenantAgentAuction declares neither $fillable nor $guarded and has no `title` column.
        $listing = new TenantAgentAuction();
        $listing->user_id     = $this->owner->id;
        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        return $listing;
    }

    /** A new, unsaved row for $role — assigned by property, as the wizards do. */
    private function unsavedRow(string $role): object
    {
        $model = new (self::MODELS[$role])();
        $model->user_id  = $this->owner->id;
        $model->is_draft = false;

        if (in_array($role, ['landlord', 'buyer'], true)) {
            $model->title = strtoupper($role) . ' IS-APPROVED INSERT';
        } elseif ($role === 'seller') {
            $model->address = '3 Approval Way';
        }

        return $model;
    }

    private function modelFor(string $role, int $id): object
    {
        return (self::MODELS[$role])::find($id);
    }

    /**
     * Store the flag exactly as given, bypassing the model — the value a writer
     * left behind, not one Eloquent chose.
     */
    private function storeRawApproval(string $role, int $id, mixed $value): void
    {
        DB::table(self::TABLES[$role])->where('id', $id)->update(['is_approved' => $value]);
    }

    private function rawApproval(string $role, int $id): mixed
    {
        return DB::table(self::TABLES[$role])->where('id', $id)->value('is_approved');
    }

    // ── The contract ───────────────────────────────────────────────────────

    /** @test */
    public function the_contract_names_exactly_four_values_as_approved(): void
    {
        foreach (self::APPROVED as $label => $value) {
            $this->assertTrue(ListingFlag::isTrue($value), "{$label} must read as approved");
        }

        foreach (self::NOT_APPROVED + ['null' => null] as $label => $value) {
            $this->assertFalse(ListingFlag::isTrue($value), "{$label} must read as not approved");
        }

        // The reading this replaces, and the value it gets wrong.
        $this->assertTrue((bool) 'false', 'precondition: a plain cast reads the string false as true');
    }

    // ── Every role model reads the RAW value through the contract ──────────

    /** @test */
    public function every_role_model_reads_the_raw_stored_value_through_the_contract(): void
    {
        foreach (self::MODELS as $role => $class) {
            foreach (self::APPROVED + self::NOT_APPROVED + ['null' => null] as $label => $raw) {
                $model = new $class();
                $model->setRawAttributes(['is_approved' => $raw], true);

                $expected = array_key_exists($label, self::APPROVED);

                $this->assertSame($expected, $model->is_approved, "{$role} raw {$label}: attribute");
                $this->assertSame($expected, $model->getOriginal('is_approved'), "{$role} raw {$label}: original");
            }
        }
    }

    // ── Seller and Buyer: the varchar columns, every representation ────────

    /** @test */
    public function seller_and_buyer_read_every_varchar_representation_by_its_meaning(): void
    {
        foreach (['seller', 'buyer'] as $role) {
            foreach (self::APPROVED + self::NOT_APPROVED as $label => $value) {
                $listing = $this->offerListing($role);
                $this->storeRawApproval($role, $listing->id, $value);

                $context = "{$role} is_approved={$label}";
                $this->assertSame(self::VARCHAR_STORED[$label], $this->rawApproval($role, $listing->id), "{$context}: stored verbatim");

                $fresh    = $this->modelFor($role, $listing->id);
                $expected = array_key_exists($label, self::APPROVED);

                $this->assertSame($expected, $fresh->is_approved, "{$context}: model");
                $this->assertSame($expected, $fresh->toArray()['is_approved'], "{$context}: serialised");
            }
        }
    }

    /** @test */
    public function the_legacy_strings_resolve_by_meaning_on_seller_and_buyer(): void
    {
        foreach (['seller', 'buyer'] as $role) {
            $false = $this->offerListing($role);
            $this->storeRawApproval($role, $false->id, 'false');

            $true = $this->offerListing($role);
            $this->storeRawApproval($role, $true->id, 'true');

            $this->assertSame('false', $this->rawApproval($role, $false->id), "{$role}: the column keeps the string");
            $this->assertFalse($this->modelFor($role, $false->id)->is_approved, "{$role}: raw 'false' must not be approved");
            $this->assertTrue($this->modelFor($role, $true->id)->is_approved, "{$role}: raw 'true' must be approved");
        }
    }

    // ── Landlord and Tenant: what PostgreSQL can actually hand back ────────

    /** @test */
    public function landlord_and_tenant_read_the_only_values_their_boolean_columns_hold(): void
    {
        foreach (['landlord', 'tenant'] as $role) {
            foreach ([true, false] as $value) {
                $listing = $this->offerListing($role);
                $this->storeRawApproval($role, $listing->id, $value);

                $label = $value ? 'true' : 'false';
                $row   = $this->hubRows()["{$role}:{$listing->id}"];

                $this->assertSame($value, $this->modelFor($role, $listing->id)->is_approved, "{$role} {$label}: model");
                $this->assertSame($value, $row['_approved'], "{$role} {$label}: hub");
                $this->assertSame($value ? null : 'Pending Review', $row['workflow_status_label'], "{$role} {$label}: hub badge");
            }
        }
    }

    // ── Downstream: a Seller or Buyer row holding 'false' ──────────────────

    /** @test */
    public function a_seller_or_buyer_row_holding_the_string_false_is_pending_on_every_agent_surface(): void
    {
        foreach (['seller', 'buyer'] as $role) {
            $pending = $this->offerListing($role, ['listing_status' => 'Active']);
            $this->storeRawApproval($role, $pending->id, 'false');

            $control = $this->offerListing($role, ['listing_status' => 'Active']);
            $this->storeRawApproval($role, $control->id, 'true');

            $hub = $this->hubRows();

            foreach ([[$pending, false], [$control, true]] as [$listing, $approved]) {
                $key     = "{$role}:{$listing->id}";
                $context = "{$role} " . ($approved ? "'true'" : "'false'");
                $row     = $hub[$key];

                // Typed identity (PR #148) is untouched: the link names its own table.
                $this->assertSame("/offer/listing/view/{$role}-{$listing->id}", parse_url($row['view_route'], PHP_URL_PATH));

                // Offer hub.
                $this->assertSame($approved, $row['_approved'], "{$context}: Offer hub");
                $this->assertSame($approved ? null : 'Pending Review', $row['workflow_status_label'], "{$context}: Offer hub badge");
                $this->assertSame(! $approved, array_key_exists($key, $this->hubRows('pending')), "{$context}: Offer hub Pending tab");
                $this->assertSame($approved, array_key_exists($key, $this->hubRows('active')), "{$context}: Offer hub Active tab");

                // Shared Agent page, reached through the hub's own link.
                $hero = $this->heroBadges($this->render($row['view_route']));
                $this->assertSame($approved ? null : 'Pending Review', $hero['workflow']['text'] ?? null, "{$context}: shared page");
                $this->assertSame('Active', $this->listingText($hero), "{$context}: listing status is its own badge");
            }

            // The Offer hub's counts follow the same reading.
            $counts = $this->hubResponse('all')->original->getData()['counts'];
            $this->assertGreaterThanOrEqual(1, $counts['pending'], "{$role}: pending count");
        }

        // Hire hub: a Hire Seller's Agent listing, and the Buyer rows (which the
        // Hire hub lists alongside the Offer hub).
        $sellerPending = $this->sellerHireListing();
        $this->storeRawApproval('seller', $sellerPending->id, 'false');
        $sellerControl = $this->sellerHireListing();
        $this->storeRawApproval('seller', $sellerControl->id, 'true');

        $buyerPending = $this->offerListing('buyer');
        $this->storeRawApproval('buyer', $buyerPending->id, 'false');
        $buyerControl = $this->offerListing('buyer');
        $this->storeRawApproval('buyer', $buyerControl->id, 'true');

        $hire        = $this->hireRows();
        $hirePending = $this->hireRows('pending');
        $hireActive  = $this->hireRows('active');

        foreach ([['seller', $sellerPending, false], ['seller', $sellerControl, true], ['buyer', $buyerPending, false], ['buyer', $buyerControl, true]] as [$role, $listing, $approved]) {
            $key     = "{$role}:{$listing->id}";
            $context = "Hire hub {$role} " . ($approved ? "'true'" : "'false'");

            $this->assertArrayHasKey($key, $hire, "{$context}: listed");
            $this->assertSame($approved, $hire[$key]['_approved'], $context);
            $this->assertSame($approved ? 'Active' : 'Pending Approval', $hire[$key]['status_label'], "{$context}: badge");
            $this->assertSame(! $approved, array_key_exists($key, $hirePending), "{$context}: Pending tab");
            $this->assertSame($approved, array_key_exists($key, $hireActive), "{$context}: Active tab");
        }
    }

    /** @test */
    public function a_seller_or_buyer_row_holding_the_string_false_fails_the_public_visibility_gates(): void
    {
        $access = app(ListingDocumentAccessService::class);

        foreach (['seller', 'buyer'] as $role) {
            $pending = $this->offerListing($role);
            $this->storeRawApproval($role, $pending->id, 'false');

            $control = $this->offerListing($role);
            $this->storeRawApproval($role, $control->id, 'true');

            $route = "offer.listing.{$role}.view";

            // WF-4: a not-yet-approved listing is private to its owner.
            $this->get(route($route, ['id' => $pending->id]))->assertStatus(404);
            $this->get(route($route, ['id' => $control->id]))->assertStatus(200);

            // The shared gate document access is built on.
            $this->assertFalse($access->isPubliclyVisible($this->modelFor($role, $pending->id)), "{$role} 'false': publicly visible");
            $this->assertTrue($access->isPubliclyVisible($this->modelFor($role, $control->id)), "{$role} 'true': publicly visible");
        }
    }

    /** @test */
    public function document_access_does_not_treat_a_seller_row_holding_the_string_false_as_approved(): void
    {
        // Seller and Landlord are the only listing types with catalogued
        // documents; Buyer's share of this is isPubliclyVisible(), asserted above.
        $access  = app(ListingDocumentAccessService::class);
        $visitor = User::factory()->create(['user_type' => 'buyer']);

        $pending = $this->offerListing('seller');
        $this->storeRawApproval('seller', $pending->id, 'false');

        $control = $this->offerListing('seller');
        $this->storeRawApproval('seller', $control->id, 'true');

        $this->assertFalse($access->canViewDownload($visitor, 'seller', $pending->id, 'seller_disclosure_file'));
        $this->assertFalse($access->canAiQuery($visitor, 'seller', $pending->id, 'seller_disclosure_file'));

        $this->assertTrue($access->canViewDownload($visitor, 'seller', $control->id, 'seller_disclosure_file'));
        $this->assertTrue($access->canAiQuery($visitor, 'seller', $control->id, 'seller_disclosure_file'));

        // The owner's own access never depended on approval.
        $this->assertTrue($access->canViewDownload($this->owner, 'seller', $pending->id, 'seller_disclosure_file'));
    }

    /** @test */
    public function ask_ai_context_reports_a_seller_or_buyer_row_holding_the_string_false_as_pending(): void
    {
        $builder = app(AskAiContextBuilderService::class);

        foreach (['seller', 'buyer'] as $role) {
            foreach (['false' => 'pending', 'true' => 'approved'] as $raw => $expected) {
                $listing = $this->offerListing($role);
                $this->storeRawApproval($role, $listing->id, $raw);

                $context = $builder->buildChipContext($this->modelFor($role, $listing->id), $role);

                $this->assertArrayHasKey('listing', $context, "{$role} '{$raw}': chip context was built");
                $this->assertSame($expected, $context['listing']['listing_status'], "{$role} '{$raw}'");
            }
        }
    }

    // ── Workflow and listing status stay independent ───────────────────────

    /** @test */
    public function pending_review_and_the_mls_listing_status_are_separate_answers(): void
    {
        $listing = $this->offerListing('seller', [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-IS-APPROVED',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);
        $this->storeRawApproval('seller', $listing->id, 'false');

        $row  = $this->hubRows()["seller:{$listing->id}"];
        $hero = $this->heroBadges($this->render($row['view_route']));

        $this->assertSame('Pending Review', $row['workflow_status_label']);
        $this->assertSame('Pending', $row['listing_status_display']);
        $this->assertSame('Pending Review', $hero['workflow']['text']);
        $this->assertSame('Pending', $this->listingText($hero));
    }

    // ── is_sold keeps its own reading and still outranks approval ──────────

    /** @test */
    public function is_sold_semantics_are_unchanged_and_still_outrank_approval(): void
    {
        $sold = $this->offerListing('seller', ['listing_status' => 'Active']);
        $this->storeRawApproval('seller', $sold->id, 'false');
        DB::table('seller_agent_auctions')->where('id', $sold->id)->update(['is_sold' => '1']);

        $open = $this->offerListing('buyer', ['listing_status' => 'Active']);
        $this->storeRawApproval('buyer', $open->id, 'false');
        DB::table('buyer_agent_auctions')->where('id', $open->id)->update(['is_sold' => 'false']);

        $hub = $this->hubRows();

        // Accepted is decided before approval is asked.
        $this->assertTrue($hub["seller:{$sold->id}"]['_sold']);
        $this->assertSame('Accepted', $hub["seller:{$sold->id}"]['workflow_status_label']);
        $this->assertSame('Hired Agent', $this->modelFor('seller', $sold->id)->status);

        // The string 'false' is still not sold (PR #154), and now not approved either.
        $this->assertFalse($hub["buyer:{$open->id}"]['_sold']);
        $this->assertSame('Pending Review', $hub["buyer:{$open->id}"]['workflow_status_label']);
    }

    // ── Writes are exactly what they were ──────────────────────────────────

    /** @test */
    public function a_new_listing_stores_exactly_what_its_writer_assigns(): void
    {
        foreach (['seller', 'buyer'] as $role) {
            foreach (['true (bool)' => true, 'false (bool)' => false, "'true'" => 'true', "'false'" => 'false'] as $label => $value) {
                $model = $this->unsavedRow($role);
                $model->is_approved = $value;
                $model->save();

                $this->assertSame(self::VARCHAR_STORED[$label], $this->rawApproval($role, $model->id), "{$role} insert {$label}: nothing is normalised");
            }
        }

        foreach (['landlord', 'tenant'] as $role) {
            foreach ([true, false] as $value) {
                $model = $this->unsavedRow($role);
                $model->is_approved = $value;
                $model->save();

                $this->assertSame($value, ListingFlag::isTrue($this->rawApproval($role, $model->id)), "{$role} insert " . var_export($value, true));
            }
        }
    }

    /** @test */
    public function an_update_writes_approval_only_when_its_meaning_changes(): void
    {
        foreach (['seller', 'buyer'] as $role) {
            // Re-asserting an approval the row already holds is not a change — in
            // particular the Buyer wizards' 'true' must not rewrite a stored '1',
            // which is what every `where('is_approved', true)` filter matches.
            foreach (['true (bool)' => true, "'true'" => 'true', "'1'" => '1'] as $label => $value) {
                $model = $this->modelFor($role, $this->offerListing($role)->id);
                $this->assertSame('1', $this->rawApproval($role, $model->id));

                $model->is_approved = $value;
                $this->assertFalse($model->isDirty('is_approved'), "{$role} '1' ← {$label}: not a change");
                $model->save();
                $this->assertSame('1', $this->rawApproval($role, $model->id), "{$role} '1' ← {$label}: stored value kept");
            }

            // A real change is written, exactly as the writer sent it.
            foreach (['true (bool)' => [true, '1'], "'true'" => ['true', 'true']] as $label => [$value, $stored]) {
                $listing = $this->offerListing($role);
                $this->storeRawApproval($role, $listing->id, '0');

                $model = $this->modelFor($role, $listing->id);
                $model->is_approved = $value;
                $model->save();
                $this->assertSame($stored, $this->rawApproval($role, $listing->id), "{$role} '0' ← {$label}");
            }

            $model = $this->modelFor($role, $this->offerListing($role)->id);
            $model->is_approved = false;
            $model->save();
            $this->assertSame('0', $this->rawApproval($role, $model->id), "{$role} '1' ← false");
        }
    }

    /** @test */
    public function a_legacy_false_row_can_still_be_approved(): void
    {
        // Under the old `(bool)` comparison 'false' and true were "equal", so an
        // approval assigned to such a row was silently never written. Now that
        // 'false' reads as not approved, that no-op would leave the row pending
        // for ever — so equivalence is judged by the same contract as the read.
        foreach (['seller', 'buyer'] as $role) {
            $listing = $this->offerListing($role, ['listing_status' => 'Active']);
            $this->storeRawApproval($role, $listing->id, 'false');

            $model = $this->modelFor($role, $listing->id);
            $this->assertFalse($model->is_approved);

            $model->is_approved = true;
            $this->assertTrue($model->isDirty('is_approved'), "{$role}: approving a 'false' row is a change");
            $model->save();

            $this->assertSame('1', $this->rawApproval($role, $listing->id));
            $this->assertTrue($this->modelFor($role, $listing->id)->is_approved);
            $this->assertNull($this->hubRows()["{$role}:{$listing->id}"]['workflow_status_label']);
        }
    }

    // ── Reading writes nothing ─────────────────────────────────────────────

    /** @test */
    public function reading_approval_rewrites_no_stored_value(): void
    {
        $listings = [
            ['seller',   $this->offerListing('seller', ['listing_status' => 'Active']),   'false'],
            ['buyer',    $this->offerListing('buyer', ['listing_status' => 'Active']),    'false'],
            ['landlord', $this->offerListing('landlord', ['listing_status' => 'Active']), false],
            ['tenant',   $this->offerListing('tenant', ['listing_status' => 'Active']),   true],
        ];

        foreach ($listings as [$role, $listing, $value]) {
            $this->storeRawApproval($role, $listing->id, $value);
        }

        $snapshot = function () use ($listings): array {
            $state = [];

            foreach ($listings as [$role, $listing]) {
                $state["{$role}:{$listing->id}"] = (array) DB::table(self::TABLES[$role])
                    ->where('id', $listing->id)
                    ->first(['is_approved', 'is_draft', 'is_sold', 'updated_at']);
            }

            return $state;
        };

        $before = $snapshot();

        $hub = $this->hubRows();
        foreach (['active', 'pending', 'draft', 'accepted', 'expired'] as $filter) {
            $this->hubRows($filter);
        }
        foreach (['active', 'pending', 'hired'] as $filter) {
            $this->hireRows($filter);
        }

        $access  = app(ListingDocumentAccessService::class);
        $builder = app(AskAiContextBuilderService::class);

        foreach ($listings as [$role, $listing]) {
            $this->render($hub["{$role}:{$listing->id}"]['view_route']);

            $model = $this->modelFor($role, $listing->id);
            $access->isPubliclyVisible($model);
            $builder->buildChipContext($model, $role);
        }

        $this->get(route('offer.listing.seller.view', ['id' => $listings[0][1]->id]));
        $this->get(route('offer.listing.buyer.view', ['id' => $listings[1][1]->id]));

        $this->assertSame($before, $snapshot(), 'reading is_approved must never normalise the stored value');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function hubResponse(string $filter)
    {
        $response = $this->actingAs($this->owner)->get(route('agent.offer-listings', ['filter' => $filter]));
        $response->assertStatus(200);

        return $response;
    }

    /**
     * The Offer hub's rows, keyed "role:id".
     *
     * @return array<string,array>
     */
    private function hubRows(string $filter = 'all'): array
    {
        return $this->keyed($this->hubResponse($filter)->original->getData()['listings']);
    }

    /**
     * The Hire hub's rows, keyed "role:id".
     *
     * @return array<string,array>
     */
    private function hireRows(string $filter = 'all'): array
    {
        $response = $this->actingAs($this->owner)->get(route('agent.hire-listings', ['filter' => $filter]));
        $response->assertStatus(200);

        return $this->keyed($response->original->getData()['listings']);
    }

    private function keyed(iterable $listings): array
    {
        $rows = [];

        foreach ($listings as $row) {
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
