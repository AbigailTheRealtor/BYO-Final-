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
 * The shared Agent page's hero, and the two different things it says.
 *
 * THE DEFECT THIS FILE PINS
 * -------------------------
 * `AgentController::offerListingView()` built ONE hero badge from a ladder that
 * mixed three concepts:
 *
 *     Draft → Accepted → Pending Review → Expired → $meta['listing_status'] ?? 'Active'
 *
 * The first three are BidYourOffer workflow and transaction states. 'Expired'
 * came from `listing_expiration`, a BidYourOffer offer-period date. The last rung
 * was the listing's market status — but read raw, so an MLS-linked listing
 * Stellar reports as Pending read 'Active' at the top of the page while the
 * "Listing Status" row beneath it (PR #148) correctly read 'Pending'. And a lapsed
 * offer period replaced the market status outright, printing 'Expired' for a
 * property still Active on the MLS.
 *
 * WHAT THE TESTS ASSERT
 * ---------------------
 * The hero now carries a workflow badge only when a workflow state applies, and a
 * listing-status badge that is the same `listing_status_display` value the row
 * prints. Every case is rendered through the real hub link or route, and the
 * listing-status badge is compared against the row wherever both appear.
 */
class AgentSharedHeroStatusTest extends TestCase
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
            'address'     => '1 Hero Way',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($flags) {
            $listing->forceFill($flags)->save();
        }

        // Marks the row as an Offer Listing, which is what puts it in the hub.
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('listing_title', 'SELLER HERO LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function landlordListing(array $meta = [], array $flags = []): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LANDLORD HERO LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($flags) {
            $listing->forceFill($flags)->save();
        }

        $listing->saveMeta('listing_title', 'LANDLORD HERO LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function buyerListing(array $meta = [], array $flags = []): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '3 Hero Way',
            'title'       => 'BUYER HERO LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($flags) {
            $listing->forceFill($flags)->save();
        }

        $listing->saveMeta('listing_title', 'BUYER HERO LISTING');

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

        $listing->saveMeta('listing_title', 'TENANT HERO LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /** An MLS-linked meta set: a stored owner answer plus Stellar's status. */
    private function mls(string $stored, string $standardStatus, string $key = 'STELLAR-HERO'): array
    {
        return [
            'listing_status'              => $stored,
            MlsMeta::META_LISTING_KEY     => $key,
            MlsMeta::META_STANDARD_STATUS => $standardStatus,
        ];
    }

    // ── A / B. The market status is the effective one, not the stored one ──

    /** @test */
    public function seller_mls_linked_hero_shows_the_effective_pending_not_the_stored_active(): void
    {
        $listing = $this->sellerListing($this->mls('Active', 'Pending'));

        $html = $this->render($this->hubViewLink('seller', $listing->id));

        $this->assertSame('Pending', $this->listingBadge($html));
        $this->assertSame('primary', $this->heroBadges($html)['listing']['class']);
        $this->assertNotContains('Active', $this->heroBadgeTexts($html), 'the stale stored status must not reach the hero');
        $this->assertArrayNotHasKey('workflow', $this->heroBadges($html), 'an approved, open listing has no workflow state to report');
        $this->assertHeroAgreesWithRow($html);
    }

    /** @test */
    public function landlord_mls_linked_hero_shows_the_effective_pending_not_the_stored_active(): void
    {
        $listing = $this->landlordListing($this->mls('Active', 'Pending'));

        $html = $this->render($this->hubViewLink('landlord', $listing->id));

        $this->assertSame('Pending', $this->listingBadge($html));
        $this->assertNotContains('Active', $this->heroBadgeTexts($html));
        $this->assertHeroAgreesWithRow($html);
    }

    // ── C. A refreshed feed status reaches the hero, and nothing is rewritten ──

    /** @test */
    public function a_refreshed_mls_status_reaches_the_hero_without_rewriting_the_stored_status(): void
    {
        $listing = $this->landlordListing($this->mls('Active', 'Pending'));
        $link    = $this->hubViewLink('landlord', $listing->id);

        $this->assertSame('Pending', $this->listingBadge($this->render($link)));

        $listing->saveMeta(MlsMeta::META_STANDARD_STATUS, 'Closed');

        $html = $this->render($link);

        $this->assertSame('Closed', $this->listingBadge($html));
        $this->assertHeroAgreesWithRow($html);
        $this->assertSame(
            'Active',
            $listing->fresh()->info('listing_status'),
            'rendering must never rewrite the stored listing_status',
        );
    }

    // ── D. Workflow state and market status coexist, distinguishably ───────

    /** @test */
    public function pending_review_workflow_and_pending_market_status_are_two_badges(): void
    {
        $listing = $this->sellerListing($this->mls('Active', 'Pending'), ['is_approved' => false]);

        $html   = $this->render($this->hubViewLink('seller', $listing->id));
        $badges = $this->heroBadges($html);

        $this->assertSame(['class' => 'warning', 'text' => 'Pending Review'], $badges['workflow']);
        $this->assertSame('Pending', $this->listingBadge($html));
        $this->assertNotSame(
            $badges['workflow']['text'],
            $badges['listing']['text'],
            'a workflow state and a market status must never be printed as the same thing',
        );
        $this->assertHeroAgreesWithRow($html);
    }

    // ── E. An expired offer period is not an expired listing ───────────────

    /** @test */
    public function an_expired_offer_period_does_not_become_the_mls_market_status(): void
    {
        $listing = $this->sellerListing(
            $this->mls('Active', 'Active') + ['listing_expiration' => '2020-01-01'],
        );

        $html   = $this->render($this->hubViewLink('seller', $listing->id));
        $badges = $this->heroBadges($html);

        $this->assertSame(['class' => 'danger', 'text' => 'Expired'], $badges['workflow']);
        $this->assertSame('Active', $this->listingBadge($html));
        $this->assertSame('primary', $badges['listing']['class'], 'an Active MLS listing must not be coloured as expired');
        $this->assertHeroAgreesWithRow($html);
    }

    /**
     * The realistic shape: `listing_expiration` is an OfferAuction key, so the
     * offer period that lapses is the OfferAuction's, reached by its bare integer
     * id, while the listing it is linked to is still Active on the MLS.
     *
     * @test
     */
    public function an_expired_offer_auction_linked_to_an_active_mls_listing_keeps_the_market_status(): void
    {
        $offerAuction = OfferAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LINKED OFFER AUCTION',
            'is_draft'    => false,
            'is_approved' => true,
        ]);
        $offerAuction->saveMeta('listing_role', 'seller');
        $offerAuction->saveMeta('listing_expiration', '2020-01-01');

        $this->sellerListing(
            $this->mls('Active', 'Active') + ['linked_offer_auction_id' => (string) $offerAuction->id],
        );

        $html   = $this->render(route('offer.listing.view', $offerAuction->id));
        $badges = $this->heroBadges($html);

        $this->assertSame(['class' => 'danger', 'text' => 'Expired'], $badges['workflow']);
        $this->assertSame('Active', $this->listingBadge($html));
        $this->assertHeroAgreesWithRow($html);
    }

    /** @test */
    public function a_lapsed_expiration_date_on_an_mls_listing_changes_neither_badge(): void
    {
        // `expiration_date` is the role listing's own date. For an MLS-linked
        // listing MlsLinkedListingStatus already forbids it from overriding
        // Stellar; the hero must not reintroduce that through a second door.
        $listing = $this->sellerListing(
            $this->mls('Active', 'Active') + ['expiration_date' => '2020-01-01'],
        );

        $html = $this->render($this->hubViewLink('seller', $listing->id));

        $this->assertArrayNotHasKey('workflow', $this->heroBadges($html));
        $this->assertSame('Active', $this->listingBadge($html));
    }

    // ── F. An accepted transaction stays a workflow state ──────────────────

    /** @test */
    public function an_accepted_transaction_is_a_workflow_badge_not_a_market_status(): void
    {
        // What the accept path writes: is_sold, and listing_status 'Hired Agent'.
        $listing = $this->sellerListing(
            $this->mls('Hired Agent', 'Closed'),
            ['is_sold' => true],
        );

        $html   = $this->render($this->hubViewLink('seller', $listing->id));
        $badges = $this->heroBadges($html);

        $this->assertSame(['class' => 'success', 'text' => 'Accepted'], $badges['workflow']);
        $this->assertNotSame('Accepted', $this->listingBadge($html), 'acceptance must not be printed as the listing status');

        // The listing-status surface prints exactly what the row prints — the
        // model's own answer, in which a completed BidYourOffer transaction
        // outranks the feed. That precedence is ListingStatusDisplay's, not the
        // hero's, and the hero must not second-guess it.
        $this->assertSame('Hired Agent', $this->listingBadge($html));
        $this->assertHeroAgreesWithRow($html);
    }

    // ── G / H. Manual Seller and Landlord keep their stored status ─────────

    /** @test */
    public function a_manual_seller_listing_keeps_its_stored_status_in_the_hero(): void
    {
        foreach (['Active', 'Pending'] as $stored) {
            $listing = $this->sellerListing(['listing_status' => $stored]);

            $html = $this->render($this->hubViewLink('seller', $listing->id));

            $this->assertSame($stored, $this->listingBadge($html));
            $this->assertArrayNotHasKey('workflow', $this->heroBadges($html));
            $this->assertHeroAgreesWithRow($html);
        }
    }

    /** @test */
    public function a_manual_landlord_listing_keeps_its_stored_status_in_the_hero(): void
    {
        foreach (['Active', 'Pending'] as $stored) {
            $listing = $this->landlordListing(['listing_status' => $stored]);

            $html = $this->render($this->hubViewLink('landlord', $listing->id));

            $this->assertSame($stored, $this->listingBadge($html));
            $this->assertArrayNotHasKey('workflow', $this->heroBadges($html));
            $this->assertHeroAgreesWithRow($html);
        }
    }

    // ── I / J. Buyer and Tenant: no MLS behaviour, workflow unchanged ──────

    /** @test */
    public function buyer_hero_keeps_the_stored_status_and_ignores_mls_meta(): void
    {
        $listing = $this->buyerListing($this->mls('Active', 'Pending', 'STELLAR-BUYER'));

        $html = $this->render($this->hubViewLink('buyer', $listing->id));

        $this->assertSame('Active', $this->listingBadge($html), 'Buyer has no MLS status contract');
        $this->assertHeroAgreesWithRow($html);

        $pending = $this->buyerListing(['listing_status' => 'Active'], ['is_approved' => false]);

        $this->assertSame(
            ['class' => 'warning', 'text' => 'Pending Review'],
            $this->heroBadges($this->render($this->hubViewLink('buyer', $pending->id)))['workflow'],
        );
    }

    /** @test */
    public function tenant_hero_keeps_the_stored_status_and_ignores_mls_meta(): void
    {
        $listing = $this->tenantListing($this->mls('Active', 'Pending', 'STELLAR-TENANT'));

        $html = $this->render($this->hubViewLink('tenant', $listing->id));

        $this->assertSame('Active', $this->listingBadge($html), 'Tenant has no MLS status contract');
        $this->assertHeroAgreesWithRow($html);

        $draft = $this->tenantListing(['listing_status' => 'Active'], ['is_draft' => true]);

        $this->assertSame(
            ['class' => 'secondary', 'text' => 'Draft'],
            $this->heroBadges($this->render($this->hubViewLink('tenant', $draft->id)))['workflow'],
        );
    }

    // ── K. Nothing is invented to fill a badge ─────────────────────────────

    /** @test */
    public function a_listing_with_no_status_renders_no_listing_status_badge(): void
    {
        $seller = $this->sellerListing();
        $buyer  = $this->buyerListing();

        foreach ([['seller', $seller->id], ['buyer', $buyer->id]] as [$role, $id]) {
            $html = $this->render($this->hubViewLink($role, $id));

            $this->assertNull($this->listingBadge($html), "{$role}: no status was stored, so none may be asserted");
            $this->assertNull($this->listingStatusRow($html));
            $this->assertArrayNotHasKey('workflow', $this->heroBadges($html));
            $this->assertNotContains('Active', $this->heroBadgeTexts($html), "{$role}: 'Active' must not be invented");
        }
    }

    // ── L. Rendering writes nothing ────────────────────────────────────────

    /** @test */
    public function rendering_the_hero_mutates_nothing(): void
    {
        $offerAuction = OfferAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LINKED OFFER AUCTION',
            'is_draft'    => false,
            'is_approved' => true,
        ]);
        $offerAuction->saveMeta('listing_role', 'seller');
        $offerAuction->saveMeta('listing_expiration', '2020-01-01');

        $listing = $this->sellerListing(
            $this->mls('Active', 'Pending') + [
                'listing_expiration'      => '2020-01-01',
                'linked_offer_auction_id' => (string) $offerAuction->id,
            ],
            ['is_approved' => false],
        );

        $snapshot = fn () => [
            'seller_row'  => (array) DB::table('seller_agent_auctions')->where('id', $listing->id)->first(['is_draft', 'is_approved', 'is_sold']),
            'seller_meta' => $listing->fresh()->meta->pluck('meta_value', 'meta_key')->sort()->toArray(),
            'oa_row'      => (array) DB::table('offer_auctions')->where('id', $offerAuction->id)->first(['is_draft', 'is_approved', 'is_sold']),
            'oa_meta'     => $offerAuction->fresh()->metas->pluck('meta_value', 'meta_key')->sort()->toArray(),
            'oa_count'    => OfferAuction::count(),
        ];

        $before = $snapshot();

        $this->render($this->hubViewLink('seller', $listing->id));
        $this->render(route('offer.listing.view', $offerAuction->id));

        $this->assertSame($before, $snapshot(), 'the hero must read the listing, never write to it');
    }

    // ── M. PR #148 identity is untouched ───────────────────────────────────

    /** @test */
    public function role_identity_tokens_still_resolve_their_own_record_beside_a_colliding_offer_auction(): void
    {
        $builders = [
            'seller'   => fn (array $meta) => $this->sellerListing($meta),
            'landlord' => fn (array $meta) => $this->landlordListing($meta),
            'buyer'    => fn (array $meta) => $this->buyerListing($meta),
            'tenant'   => fn (array $meta) => $this->tenantListing($meta),
        ];

        foreach ($builders as $role => $build) {
            $listing = $build(['listing_status' => 'Active']);

            // The four role tables are independent sequences, so they hand out the
            // same ids; one decoy per id collides with every role listing holding it.
            OfferAuction::find($listing->id) ?? $this->decoyOfferAuction($listing->id);

            $html = $this->render($this->hubViewLink($role, $listing->id));

            $this->assertStringContainsString(strtoupper($role) . ' HERO LISTING', $html);
            $this->assertStringNotContainsString('DECOY OFFER AUCTION', $html, "{$role}-{$listing->id} rendered the colliding OfferAuction");
            $this->assertSame('Active', $this->listingBadge($html), "{$role}: the hero read the decoy's status");
        }
    }

    /** @test */
    public function a_bare_integer_still_addresses_an_offer_auction(): void
    {
        $decoy = $this->decoyOfferAuction(null, 'LEGACY OFFER PLAYOFF LISTING');

        $html = $this->render(route('offer.listing.view', $decoy->id));

        $this->assertStringContainsString('LEGACY OFFER PLAYOFF LISTING', $html);
        $this->assertSame('DECOY STATUS', $this->listingBadge($html));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * An OfferAuction, optionally forced onto a chosen id so it collides with a
     * role listing, carrying a recognisable title and status.
     */
    private function decoyOfferAuction(?int $id, string $title = 'DECOY OFFER AUCTION'): OfferAuction
    {
        $auction = OfferAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => $title,
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        if ($id !== null && $id !== $auction->id) {
            OfferAuction::where('id', $auction->id)->update(['id' => $id]);
        }

        $fresh = OfferAuction::find($id ?? $auction->id);
        $fresh->saveMeta('listing_title', $title);
        $fresh->saveMeta('listing_status', 'DECOY STATUS');
        $fresh->saveMeta('listing_role', 'seller');

        return $fresh->fresh();
    }

    /** The hub's own View link for a role listing, read out of the real hub page. */
    private function hubViewLink(string $role, int $listingId): string
    {
        $response = $this->actingAs($this->owner)->get(route('agent.offer-listings'));
        $response->assertStatus(200);

        $match = collect($response->original->getData()['listings'])->first(
            fn ($l) => $l['role'] === $role && (int) $l['id'] === $listingId
        );

        $this->assertNotNull($match, "the hub did not list the {$role} listing #{$listingId}");

        return $match['view_route'];
    }

    private function render(string $url): string
    {
        $response = $this->actingAs($this->owner)->get($url);
        $response->assertStatus(200);

        return $response->getContent();
    }

    /**
     * The hero's status badges, keyed by surface.
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
            $this->assertArrayNotHasKey($surface, $badges, "the hero rendered two {$surface} badges");

            $badges[$surface] = ['class' => $class, 'text' => trim(html_entity_decode(strip_tags($text)))];
        }

        return $badges;
    }

    /** The listing-status badge's value without its label, or null when absent. */
    private function listingBadge(string $html): ?string
    {
        $text = $this->heroBadges($html)['listing']['text'] ?? null;

        if ($text === null) {
            return null;
        }

        $this->assertStringStartsWith('Listing Status: ', $text);

        return substr($text, strlen('Listing Status: '));
    }

    /**
     * Every badge text in the hero strip — the status badges and the offer-type
     * and role badges beside them — so an assertion that a value is absent from
     * the hero cannot be satisfied merely by it having moved to an untagged badge.
     *
     * @return list<string>
     */
    private function heroBadgeTexts(string $html): array
    {
        $this->assertSame(
            1,
            preg_match('/<code class="small" style="color:#049399;">.*?<\/code>(.*?)<\/div>/s', $html, $strip),
            'the hero badge strip was not found',
        );

        preg_match_all('/<span class="badge[^"]*"[^>]*>(.*?)<\/span>/s', $strip[1], $matches);

        return array_map(
            fn ($t) => trim(html_entity_decode(strip_tags($t))),
            $matches[1],
        );
    }

    /**
     * The rendered value of the "Listing Status" row, or null when the row was
     * not rendered at all.
     */
    private function listingStatusRow(string $html): ?string
    {
        if (! preg_match('/Listing Status<\/div>\s*<div[^>]*>(.*?)<\/div>/s', $html, $m)) {
            return null;
        }

        return trim(strip_tags($m[1]));
    }

    /** The hero's listing-status surface and the row are one value, or both absent. */
    private function assertHeroAgreesWithRow(string $html): void
    {
        $this->assertSame(
            $this->listingStatusRow($html),
            $this->listingBadge($html),
            'the hero listing status and the "Listing Status" row must be the same value',
        );
    }
}
