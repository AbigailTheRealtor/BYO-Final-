<?php

namespace Tests\Feature\Agent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as MlsMeta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Offer Listings hub's Status column, and the two different things it says.
 *
 * THE DEFECT THIS FILE PINS
 * -------------------------
 * `AgentController::normalizeRoleOfferListing()` built each card's ONE status
 * badge from the ladder the shared page's hero used before PR #150:
 *
 *     Draft → Accepted → Pending Review → Expired → $meta['listing_status'] ?? 'Active'
 *
 * so an MLS-linked Seller or Landlord listing Stellar reports as Pending read
 * 'Active' in the hub — the stale value the owner typed — while the page the
 * card links to said 'Pending'. And a lapsed BidYourOffer offer period replaced
 * the market status with 'Expired'.
 *
 * WHAT THE TESTS ASSERT
 * ---------------------
 * The card now carries the hero's contract: a workflow badge only when a
 * workflow state applies, and a listing-status badge that is the same
 * `listing_status_display` value the linked page prints. The filter tabs are
 * workflow filters and are unchanged. Every case is rendered through the real
 * hub, and the typed View links from PR #148 are checked unchanged.
 */
class AgentHubCardStatusTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['user_type' => 'seller']);

        config(['offer.playoff_access.allowed_user_ids' => '*']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function sellerListing(array $meta = [], array $flags = []): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '1 Hub Way',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($flags) {
            $listing->forceFill($flags)->save();
        }

        // Marks the row as an Offer Listing, which is what puts it in the hub.
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('listing_title', 'SELLER HUB LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function landlordListing(array $meta = [], array $flags = []): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LANDLORD HUB LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($flags) {
            $listing->forceFill($flags)->save();
        }

        $listing->saveMeta('listing_title', 'LANDLORD HUB LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function buyerListing(array $meta = [], array $flags = []): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '3 Hub Way',
            'title'       => 'BUYER HUB LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($flags) {
            $listing->forceFill($flags)->save();
        }

        $listing->saveMeta('listing_title', 'BUYER HUB LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function tenantListing(array $meta = [], array $flags = []): TenantAgentAuction
    {
        // TenantAgentAuction declares neither $fillable nor $guarded, and has no
        // `title` column — the hub falls back to `listing_title`.
        $listing = new TenantAgentAuction();
        $listing->user_id     = $this->owner->id;
        $listing->is_draft    = false;
        $listing->is_approved = true;

        foreach ($flags as $key => $value) {
            $listing->{$key} = $value;
        }

        $listing->save();

        $listing->saveMeta('listing_title', 'TENANT HUB LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /** An MLS-linked meta set: a stored owner answer plus Stellar's status. */
    private function mls(string $stored, string $standardStatus, string $key = 'STELLAR-HUB'): array
    {
        return [
            'listing_status'              => $stored,
            MlsMeta::META_LISTING_KEY     => $key,
            MlsMeta::META_STANDARD_STATUS => $standardStatus,
        ];
    }

    // ── A / B. The card's listing status is the effective one ─────────────

    /** @test */
    public function seller_mls_linked_card_shows_the_effective_pending_not_the_stored_active(): void
    {
        $listing = $this->sellerListing($this->mls('Active', 'Pending'));

        [$row, $tr] = $this->hubRow('seller', $listing->id);

        $this->assertSame('Pending', $row['listing_status_display']);
        $this->assertSame('Pending', $this->listingBadge($tr));
        $this->assertSame('primary', $this->cardBadges($tr)['listing']['class']);
        $this->assertArrayNotHasKey('workflow', $this->cardBadges($tr), 'an approved, open listing has no workflow state to report');
        $this->assertNotContains('Active', $this->allBadgeTexts($tr), 'the stale stored status must not reach the card');
    }

    /** @test */
    public function landlord_mls_linked_card_shows_the_effective_pending_not_the_stored_active(): void
    {
        $listing = $this->landlordListing($this->mls('Active', 'Pending'));

        [, $tr] = $this->hubRow('landlord', $listing->id);

        $this->assertSame('Pending', $this->listingBadge($tr));
        $this->assertNotContains('Active', $this->allBadgeTexts($tr));
    }

    // ── C. A refreshed feed status reaches the card, nothing is rewritten ──

    /** @test */
    public function a_refreshed_mls_status_reaches_the_card_without_rewriting_the_stored_status(): void
    {
        $listing = $this->sellerListing($this->mls('Active', 'Pending'));

        $this->assertSame('Pending', $this->listingBadge($this->hubRow('seller', $listing->id)[1]));

        $listing->saveMeta(MlsMeta::META_STANDARD_STATUS, 'Closed');

        $this->assertSame('Closed', $this->listingBadge($this->hubRow('seller', $listing->id)[1]));
        $this->assertSame(
            'Active',
            $listing->fresh()->info('listing_status'),
            'rendering the hub must never rewrite the stored listing_status',
        );
    }

    // ── D. Workflow state and market status coexist, distinguishably ───────

    /** @test */
    public function pending_review_workflow_and_pending_market_status_are_two_badges(): void
    {
        $listing = $this->sellerListing($this->mls('Active', 'Pending'), ['is_approved' => false]);

        [, $tr]  = $this->hubRow('seller', $listing->id);
        $badges  = $this->cardBadges($tr);

        $this->assertSame(['class' => 'warning', 'text' => 'Pending Review'], $badges['workflow']);
        $this->assertSame('Pending', $this->listingBadge($tr));
        $this->assertNotSame($badges['workflow']['text'], $badges['listing']['text']);

        // The filter tabs are workflow filters, and still count this row as
        // awaiting review — not as a listing whose market status is Pending.
        $this->assertNotNull($this->hubRow('seller', $listing->id, 'pending')[0]);
        $this->assertNull($this->hubRow('seller', $listing->id, 'active')[0]);
    }

    // ── E. An expired offer period is not an expired listing ───────────────

    /** @test */
    public function an_expired_offer_period_does_not_become_the_card_market_status(): void
    {
        $listing = $this->landlordListing(
            $this->mls('Active', 'Active') + ['listing_expiration' => '2020-01-01'],
        );

        [, $tr] = $this->hubRow('landlord', $listing->id);
        $badges = $this->cardBadges($tr);

        $this->assertSame(['class' => 'danger', 'text' => 'Expired'], $badges['workflow']);
        $this->assertSame('Active', $this->listingBadge($tr));
        $this->assertSame('primary', $badges['listing']['class'], 'an Active MLS listing must not be coloured as expired');

        // The Expired tab is the offer period's, and still holds the row.
        $this->assertNotNull($this->hubRow('landlord', $listing->id, 'expired')[0]);
    }

    /** @test */
    public function a_lapsed_expiration_date_on_an_mls_listing_changes_neither_badge(): void
    {
        $listing = $this->sellerListing(
            $this->mls('Active', 'Active') + ['expiration_date' => '2020-01-01'],
        );

        [, $tr] = $this->hubRow('seller', $listing->id);

        $this->assertArrayNotHasKey('workflow', $this->cardBadges($tr));
        $this->assertSame('Active', $this->listingBadge($tr));
    }

    // ── F. An accepted transaction stays a workflow state ──────────────────

    /** @test */
    public function an_accepted_transaction_is_a_workflow_badge_not_a_market_status(): void
    {
        // What the accept path writes: is_sold, and listing_status 'Hired Agent'.
        $listing = $this->sellerListing($this->mls('Hired Agent', 'Closed'), ['is_sold' => true]);

        [$row, $tr] = $this->hubRow('seller', $listing->id);

        $this->assertSame(['class' => 'success', 'text' => 'Accepted'], $this->cardBadges($tr)['workflow']);
        $this->assertNotSame('Accepted', $this->listingBadge($tr), 'acceptance must not be printed as the listing status');

        // ListingStatusDisplay's own precedence — a completed BidYourOffer
        // transaction outranks the feed — exactly as on the linked page.
        $this->assertSame('Hired Agent', $this->listingBadge($tr));
        $this->assertNotNull($this->hubRow('seller', $listing->id, 'accepted')[0]);
        $this->assertSame($this->listingBadge($tr), $this->linkedPageListingStatus($row['view_route']));
    }

    // ── G / H. Manual Seller and Landlord keep their stored status ─────────

    /** @test */
    public function a_manual_seller_card_keeps_its_stored_status(): void
    {
        foreach (['Active', 'Pending'] as $stored) {
            $listing = $this->sellerListing(['listing_status' => $stored]);

            [, $tr] = $this->hubRow('seller', $listing->id);

            $this->assertSame($stored, $this->listingBadge($tr));
            $this->assertArrayNotHasKey('workflow', $this->cardBadges($tr));
        }
    }

    /** @test */
    public function a_manual_landlord_card_keeps_its_stored_status(): void
    {
        foreach (['Active', 'Pending'] as $stored) {
            $listing = $this->landlordListing(['listing_status' => $stored]);

            [, $tr] = $this->hubRow('landlord', $listing->id);

            $this->assertSame($stored, $this->listingBadge($tr));
            $this->assertArrayNotHasKey('workflow', $this->cardBadges($tr));
        }
    }

    // ── I / J. Buyer and Tenant: no MLS behaviour, workflow unchanged ──────

    /** @test */
    public function buyer_card_keeps_the_stored_status_and_ignores_mls_meta(): void
    {
        $listing = $this->buyerListing($this->mls('Active', 'Pending', 'STELLAR-BUYER'));

        $this->assertSame('Active', $this->listingBadge($this->hubRow('buyer', $listing->id)[1]), 'Buyer has no MLS status contract');

        $pending = $this->buyerListing(['listing_status' => 'Active'], ['is_approved' => false]);

        $this->assertSame(
            ['class' => 'warning', 'text' => 'Pending Review'],
            $this->cardBadges($this->hubRow('buyer', $pending->id)[1])['workflow'],
        );
    }

    /** @test */
    public function tenant_card_keeps_the_stored_status_and_ignores_mls_meta(): void
    {
        $listing = $this->tenantListing($this->mls('Active', 'Pending', 'STELLAR-TENANT'));

        $this->assertSame('Active', $this->listingBadge($this->hubRow('tenant', $listing->id)[1]), 'Tenant has no MLS status contract');

        $draft = $this->tenantListing(['listing_status' => 'Active'], ['is_draft' => true]);

        [, $tr] = $this->hubRow('tenant', $draft->id);

        $this->assertSame(['class' => 'secondary', 'text' => 'Draft'], $this->cardBadges($tr)['workflow']);
        $this->assertStringContainsString('Continue Draft', $tr);
    }

    // ── K. Nothing is invented to fill the cell ────────────────────────────

    /** @test */
    public function a_listing_with_no_status_renders_no_listing_status_badge(): void
    {
        $seller = $this->sellerListing();
        $buyer  = $this->buyerListing();

        foreach ([['seller', $seller->id], ['buyer', $buyer->id]] as [$role, $id]) {
            [$row, $tr] = $this->hubRow($role, $id);

            $this->assertNull($row['listing_status_display'], "{$role}: no status was stored, so none may be asserted");
            $this->assertSame([], $this->cardBadges($tr));
            $this->assertNotContains('Active', $this->allBadgeTexts($tr), "{$role}: 'Active' must not be invented");
            $this->assertMatchesRegularExpression('/<span class="text-muted">—<\/span>\s*<\/td>\s*<td class="text-end pe-3">/', $tr);
        }
    }

    // ── Parity with the page the card links to ─────────────────────────────

    /** @test */
    public function the_card_and_the_page_it_links_to_print_the_same_listing_status(): void
    {
        $cases = [
            ['seller',   $this->sellerListing($this->mls('Active', 'Pending'))],
            ['landlord', $this->landlordListing($this->mls('Active', 'Closed'))],
            ['seller',   $this->sellerListing(['listing_status' => 'Pending'])],
            ['buyer',    $this->buyerListing($this->mls('Active', 'Pending', 'STELLAR-BUYER'))],
            ['tenant',   $this->tenantListing(['listing_status' => 'Active'])],
        ];

        foreach ($cases as [$role, $listing]) {
            [$row, $tr] = $this->hubRow($role, $listing->id);

            $this->assertSame(
                $this->linkedPageListingStatus($row['view_route']),
                $this->listingBadge($tr),
                "{$role}-{$listing->id}: the card and the page it links to disagree",
            );
        }
    }

    // ── L. PR #148 typed links are unchanged ───────────────────────────────

    /** @test */
    public function hub_view_links_still_carry_typed_role_identities(): void
    {
        $listings = [
            'seller'   => $this->sellerListing(['listing_status' => 'Active']),
            'landlord' => $this->landlordListing(['listing_status' => 'Active']),
            'buyer'    => $this->buyerListing(['listing_status' => 'Active']),
            'tenant'   => $this->tenantListing(['listing_status' => 'Active']),
        ];

        foreach ($listings as $role => $listing) {
            [$row, $tr] = $this->hubRow($role, $listing->id);

            $this->assertSame("/offer/listing/view/{$role}-{$listing->id}", parse_url($row['view_route'], PHP_URL_PATH));
            $this->assertStringContainsString('href="' . e($row['view_route']) . '"', $tr);
        }
    }

    // ── M. Rendering writes nothing ────────────────────────────────────────

    /** @test */
    public function rendering_the_hub_mutates_nothing(): void
    {
        $offerAuction = OfferAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LINKED OFFER AUCTION',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $seller = $this->sellerListing(
            $this->mls('Active', 'Pending') + [
                'listing_expiration'      => '2020-01-01',
                'linked_offer_auction_id' => (string) $offerAuction->id,
            ],
            ['is_approved' => false],
        );
        $landlord = $this->landlordListing($this->mls('Active', 'Closed'), ['is_sold' => true]);

        $snapshot = fn () => [
            'seller_row'    => (array) DB::table('seller_agent_auctions')->where('id', $seller->id)->first(['is_draft', 'is_approved', 'is_sold']),
            'seller_meta'   => $seller->fresh()->meta->pluck('meta_value', 'meta_key')->sort()->toArray(),
            'landlord_row'  => (array) DB::table('landlord_agent_auctions')->where('id', $landlord->id)->first(['is_draft', 'is_approved', 'is_sold']),
            'landlord_meta' => $landlord->fresh()->meta->pluck('meta_value', 'meta_key')->sort()->toArray(),
            'oa_count'      => OfferAuction::count(),
        ];

        $before = $snapshot();

        foreach (['all', 'active', 'pending', 'draft', 'accepted', 'expired'] as $filter) {
            $this->actingAs($this->owner)->get(route('agent.offer-listings', ['filter' => $filter]))->assertStatus(200);
        }

        $this->assertSame($before, $snapshot(), 'the hub must read listings, never write to them');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * One listing's hub row: its data array and its rendered <tr>, or
     * [null, null] when the given filter does not list it.
     *
     * @return array{0: ?array, 1: ?string}
     */
    private function hubRow(string $role, int $id, string $filter = 'all'): array
    {
        $response = $this->actingAs($this->owner)->get(route('agent.offer-listings', ['filter' => $filter]));
        $response->assertStatus(200);

        $row = collect($response->original->getData()['listings'])->first(
            fn ($l) => $l['role'] === $role && (int) $l['id'] === $id
        );

        if ($row === null) {
            return [null, null];
        }

        preg_match_all('/<tr>.*?<\/tr>/s', $response->getContent(), $matches);

        $tr = collect($matches[0])->first(
            fn ($t) => str_contains($t, '>' . e($row['listing_id']) . '</code>')
        );

        $this->assertNotNull($tr, "the hub listed {$role} #{$id} but rendered no row for it");

        return [$row, $tr];
    }

    /**
     * The card's status badges, keyed by surface.
     *
     * @return array<string,array{class:string,text:string}>
     */
    private function cardBadges(string $tr): array
    {
        preg_match_all(
            '/<span class="badge bg-([a-z-]+)" data-hub-status="(workflow|listing)">(.*?)<\/span>/s',
            $tr,
            $matches,
            PREG_SET_ORDER,
        );

        $badges = [];

        foreach ($matches as [, $class, $surface, $text]) {
            $this->assertArrayNotHasKey($surface, $badges, "the card rendered two {$surface} badges");

            $badges[$surface] = ['class' => $class, 'text' => trim(html_entity_decode(strip_tags($text)))];
        }

        return $badges;
    }

    /** The listing-status badge's value without its label, or null when absent. */
    private function listingBadge(string $tr): ?string
    {
        $text = $this->cardBadges($tr)['listing']['text'] ?? null;

        if ($text === null) {
            return null;
        }

        $this->assertStringStartsWith('Listing Status: ', $text);

        return substr($text, strlen('Listing Status: '));
    }

    /**
     * Every badge text in the row — status and offer-type alike — so a value
     * cannot pass an absence check merely by moving to an untagged badge.
     *
     * @return list<string>
     */
    private function allBadgeTexts(string $tr): array
    {
        preg_match_all('/<span class="badge[^"]*"[^>]*>(.*?)<\/span>/s', $tr, $matches);

        return array_map(fn ($t) => trim(html_entity_decode(strip_tags($t))), $matches[1]);
    }

    /** The "Listing Status" row of the shared page the card links to, or null. */
    private function linkedPageListingStatus(string $viewRoute): ?string
    {
        $response = $this->actingAs($this->owner)->get($viewRoute);
        $response->assertStatus(200);

        if (! preg_match('/Listing Status<\/div>\s*<div[^>]*>(.*?)<\/div>/s', $response->getContent(), $m)) {
            return null;
        }

        return trim(strip_tags($m[1]));
    }
}
