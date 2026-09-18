<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceChipCatalog;
use App\Support\ListingPreferences\ListingPreferencePrefetch;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The compact card variant, and the page-level properties that make a results
 * page affordable.
 *
 * ONE COMPONENT, TWO LAYOUTS. Every assertion here is against the SAME
 * component the detail page renders. A card-specific copy would pass these
 * tests too, which is why the architecture guard separately asserts there is
 * only one — this file proves the one behaves correctly on a card.
 */
class ListingPreferenceCardSurfaceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
    }

    // -------------------------------------------------------- compact variant

    /** @test */
    public function the_compact_variant_renders_the_same_three_states(): void
    {
        $this->actingAs($this->buyer());

        $html = $this->renderCompact($this->sellerListing());

        $this->assertStringContainsString('data-lp-control', $html);
        $this->assertStringContainsString('data-lp-compact="1"', $html);
        $this->assertStringContainsString('lp-compact', $html);

        foreach (ListingPreferenceState::cases() as $state) {
            $this->assertStringContainsString('data-lp-state="' . $state->value . '"', $html);
        }
    }

    /**
     * Compact and full differ in LAYOUT only. The endpoints, the listing
     * reference and the reason tray are identical, because a card and a detail
     * page must write through exactly the same path.
     *
     * @test
     */
    public function compact_and_full_reach_the_same_endpoints_and_offer_the_same_tray(): void
    {
        $this->actingAs($this->buyer());
        $listing = $this->sellerListing();

        $full    = $this->renderFull($listing);
        $compact = $this->renderCompact($listing);

        foreach ([
            route('listing-preferences.store'),
            route('listing-preferences.reasons'),
            route('listing-preferences.destroy'),
        ] as $endpoint) {
            $this->assertStringContainsString($endpoint, $full);
            $this->assertStringContainsString($endpoint, $compact);
        }

        // The tray, its Done and its Remove are present on both — reasons stay
        // optional on a card, and clear/undo stays reachable from one.
        foreach (['data-lp-tray', 'data-lp-done', 'data-lp-clear'] as $hook) {
            $this->assertStringContainsString($hook, $full);
            $this->assertStringContainsString($hook, $compact);
        }

        // Same listing identity in both.
        $this->assertStringContainsString('data-lp-listing-type="seller_agent"', $compact);
        $this->assertStringContainsString('data-lp-listing-id="' . $listing->id . '"', $compact);
    }

    /** The customer's existing choice is visible on a card, not just a detail page. @test */
    public function the_current_state_is_visible_on_a_compact_card(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing();

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id),
            ListingPreferenceState::Pass,
            ['too_expensive'],
        );

        $this->actingAs($user);
        $html = $this->renderCompact($listing);

        $this->assertMatchesRegularExpression(
            '/data-lp-state="pass"[^>]*aria-pressed="true"|aria-pressed="true"[^>]*data-lp-state="pass"/',
            $html
        );
        $this->assertStringContainsString('"state":"pass"', $html);
        $this->assertStringContainsString('too_expensive', $html);
    }

    /**
     * THE COMPACT TRAY MUST STAY IN FLOW.
     *
     * It was absolutely positioned, to float over the neighbouring cards rather
     * than grow its own. Both card grids set `overflow: hidden` on `.card`, so
     * the browser clipped it: laid out, painted nowhere, and — measured on the
     * real page — a chip's own centre belonged to a DIFFERENT card.
     *
     * This asserts the fix at its source, because the symptom is invisible to
     * every ordinary check: the element still has a box, and Playwright's
     * `toBeVisible()` still passes on it.
     *
     * @test
     */
    public function the_compact_tray_is_not_absolutely_positioned(): void
    {
        $this->actingAs($this->buyer());

        $css = $this->renderCompact($this->sellerListing());

        $this->assertStringContainsString('.lp-compact .lp-tray', $css);
        $this->assertStringContainsString('.lp-compact { margin-bottom: 0; }', $css);

        // The rule block for the compact tray must not take it out of flow.
        $start = strpos($css, '.lp-compact .lp-tray {');
        $this->assertNotFalse($start);
        $rule = substr($css, $start, (int) strpos($css, '}', $start) - $start);

        $this->assertStringNotContainsString('position: absolute', $rule);
        $this->assertStringNotContainsString('position: fixed', $rule);

        /*
         | A long chip list must still not make one card enormous — but the
         | SCROLLING REGION IS THE CHIPS, not the tray. Capping the tray put Done
         | and Remove below the fold, so the panel's primary action was invisible
         | until you scrolled a box that did not look scrollable.
         */
        $this->assertStringNotContainsString('max-height', $rule);
        $this->assertStringContainsString('.lp-compact .lp-chips { max-height: 9rem; overflow-y: auto; }', $css);
    }

    /**
     * The compact button cannot collapse to an unusable height.
     *
     * Its icon is Font Awesome from a CDN; with the glyph absent the button was
     * measured at 12px on a phone. The labels are what give it size now, and the
     * floor is what holds it up if they ever go away again.
     *
     * @test
     */
    public function the_compact_button_has_a_height_floor_and_keeps_its_label(): void
    {
        $this->actingAs($this->buyer());

        $css = $this->renderCompact($this->sellerListing());

        $this->assertStringContainsString('.lp-compact .lp-btn { min-height: 28px; }', $css);

        // The label-hiding media query is gone: measured at a 320px viewport the
        // three labels fit on one row with room to spare.
        $this->assertStringNotContainsString('.lp-compact .lp-btn span { display: none; }', $css);

        foreach (['Save', 'Maybe', 'Pass'] as $label) {
            $this->assertStringContainsString('<span>' . $label . '</span>', $css);
        }
    }

    // ------------------------------------------------------- one payload, once

    /**
     * THE PAYLOAD IS EMITTED ONCE PER CONTEXT, NOT ONCE PER CARD.
     *
     * @test
     */
    public function a_page_of_cards_emits_one_chip_catalog_not_one_per_card(): void
    {
        $this->actingAs($this->buyer());

        $listings = collect(range(1, 12))->map(fn () => $this->sellerListing());

        $html = $this->renderPage($listings->pluck('id')->all());

        // Counted on a marker that appears in MARKUP only. `data-lp-control`
        // itself also appears inside the delegated handler's selectors, and a
        // count that included those would drift with the script.
        $this->assertSame(
            12,
            substr_count($html, 'data-lp-listing-id="'),
            'every card must still render its own control'
        );

        $this->assertSame(
            1,
            substr_count($html, '<script type="application/json" data-lp-chip-catalog='),
            'the chip catalog must be written into the page exactly once'
        );

        // Each control points at the catalog rather than carrying a copy.
        $this->assertSame(12, substr_count($html, 'data-lp-chip-context="residential.sale"'));

        // THE PAYLOAD DOES NOT GROW WITH THE PAGE. A chip key legitimately
        // appears once per STATE that offers it, so the meaningful assertion is
        // not an absolute count but that twelve cards cost exactly what one
        // does — which is false the moment the payload is copied per card.
        $this->resetPerRequestStores();
        $one = $this->renderPage([$this->sellerListing()->id]);

        $this->assertSame(
            substr_count($one, 'updated_kitchen'),
            substr_count($html, 'updated_kitchen'),
            'the chip payload must not be duplicated once per card'
        );

        $this->assertGreaterThan(0, substr_count($one, 'updated_kitchen'), 'the chip must actually be offered');
    }

    /**
     * CSS and JS are emitted once for the whole page, and the handler is
     * delegated rather than per card.
     *
     * @test
     */
    public function styles_and_behaviour_are_emitted_once_per_page(): void
    {
        $this->actingAs($this->buyer());

        $listings = collect(range(1, 12))->map(fn () => $this->sellerListing());
        $html     = $this->renderPage($listings->pluck('id')->all());

        $this->assertSame(1, substr_count($html, '<style>'), 'one stylesheet per page');
        $this->assertSame(
            1,
            substr_count($html, '.lp-btn.is-active { color: #fff; }'),
            'the stylesheet must not repeat'
        );
        $this->assertSame(1, substr_count($html, '<script>'), 'one behaviour block per page');

        // One delegated listener, not one binding per control.
        $this->assertStringContainsString("document.addEventListener('click'", $html);
        $this->assertSame(
            0,
            substr_count($html, 'onclick='),
            'no inline handlers: behaviour is delegated'
        );
    }

    /**
     * A page that mixes contexts gets a payload per context, and each card
     * points at its own. One page-wide blob would offer some listings the wrong
     * chips.
     *
     * @test
     */
    public function a_mixed_context_page_emits_one_catalog_per_context(): void
    {
        $this->actingAs($this->buyer());

        $house = $this->sellerListing('Residential');
        $land  = $this->sellerListing('Vacant Land');

        $html = $this->renderPage([$house->id, $land->id]);

        $this->assertSame(2, substr_count($html, '<script type="application/json" data-lp-chip-catalog='));
        $this->assertStringContainsString('data-lp-chip-catalog="residential.sale"', $html);
        $this->assertStringContainsString('data-lp-chip-catalog="land.sale"', $html);
        $this->assertStringContainsString('data-lp-chip-context="residential.sale"', $html);
        $this->assertStringContainsString('data-lp-chip-context="land.sale"', $html);

        // A kitchen belongs to the house's catalog and to no other: adding the
        // land card added no kitchen chip anywhere.
        $this->resetPerRequestStores();
        $houseOnly = $this->renderPage([$this->sellerListing('Residential')->id]);

        $this->assertSame(
            substr_count($houseOnly, 'updated_kitchen'),
            substr_count($html, 'updated_kitchen'),
            'the land catalog must not carry a residential chip'
        );

        // …and the land catalog is genuinely a different, narrower vocabulary.
        $this->assertStringNotContainsString(
            'updated_kitchen',
            $this->catalogBlock($html, 'land.sale'),
            'a kitchen chip must never be offered on a vacant lot'
        );
    }

    // ------------------------------------------------------ N+1 regression bound

    /**
     * THE REGRESSION GUARD. A primed page of 24 cards must cost far less than a
     * page that reads per card.
     *
     * The bound is deliberately generous — the surrounding view does its own
     * work — but it is an order of magnitude below 2×24, so reverting to
     * per-card reads fails it unambiguously.
     *
     * @test
     */
    public function a_primed_page_of_cards_does_not_issue_a_query_per_card(): void
    {
        $user = $this->buyer();
        $this->actingAs($user);

        $listings = collect(range(1, 24))->map(fn () => $this->sellerListing());
        $ids      = $listings->pluck('id')->all();

        $primed = $this->countQueries(fn () => $this->renderPage($ids));

        $this->assertLessThanOrEqual(
            8,
            $primed,
            "a primed page of 24 cards issued {$primed} queries — N+1 behaviour has returned"
        );

        // And the count does not move when the page doubles.
        $this->resetPerRequestStores();
        $more = collect(range(1, 48))->map(fn () => $this->sellerListing())->pluck('id')->all();

        $doubled = $this->countQueries(fn () => $this->renderPage($more));

        $this->assertLessThanOrEqual(
            $primed + 1,
            $doubled,
            'doubling the cards must not meaningfully change the query count'
        );
    }

    /**
     * A page that forgets to prime is SLOWER, never wrong. The fallback is what
     * keeps the component droppable onto a surface nobody has optimised yet.
     *
     * @test
     */
    public function an_unprimed_page_still_renders_correctly(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing();

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id),
            ListingPreferenceState::Save,
            [],
        );

        $this->actingAs($user);

        // Rendered with no prefetch at all.
        $html = $this->renderCompact($listing);

        $this->assertStringContainsString('data-lp-control', $html);
        $this->assertStringContainsString('"state":"save"', $html);
    }

    /** Priming for one viewer must never serve another viewer's state. @test */
    public function the_prefetch_store_refuses_to_serve_another_viewers_state(): void
    {
        $mine    = $this->buyer();
        $theirs  = $this->buyer();
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id);

        app(ListingPreferenceWriter::class)->setState(
            (int) $mine->id,
            SeekerRole::Buyer,
            $ref,
            ListingPreferenceState::Save,
            [],
        );

        $store = app(ListingPreferencePrefetch::class);
        $store->prime(SmartTagListingType::SellerAgent, [$listing->id], (int) $mine->id, SeekerRole::Buyer);

        $this->assertTrue($store->hasCurrent((int) $mine->id, SeekerRole::Buyer, $ref));
        $this->assertFalse(
            $store->hasCurrent((int) $theirs->id, SeekerRole::Buyer, $ref),
            'a primed store must not answer for a viewer it was not primed for'
        );
        $this->assertFalse(
            $store->hasCurrent((int) $mine->id, SeekerRole::Tenant, $ref),
            'a primed store must not answer across seeker roles'
        );
    }

    // ------------------------------------------------- flag / guest / role gates

    /** @test */
    public function a_card_page_renders_no_control_while_the_feature_is_off(): void
    {
        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());

        $html = $this->renderPage([$this->sellerListing()->id]);

        $this->assertStringNotContainsString('data-lp-control', $html);
        $this->assertStringNotContainsString('data-lp-chip-catalog', $html);
    }

    /** The prefetch reads nothing while the feature is off — there is nothing to render. @test */
    public function the_prefetch_issues_no_queries_while_the_feature_is_off(): void
    {
        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());

        $ids = collect(range(1, 5))->map(fn () => $this->sellerListing())->pluck('id')->all();

        $this->assertSame(
            0,
            $this->countQueries(fn () => Blade::render(
                '<x-listing-preference.prefetch listing-type="seller_agent" :listing-ids="$ids" />',
                ['ids' => $ids]
            ))
        );
    }

    /** @test */
    public function a_guest_gets_a_compact_control_that_routes_to_login_and_stores_nothing(): void
    {
        $html = $this->renderCompact($this->sellerListing());

        $this->assertStringContainsString('data-lp-guest="1"', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString('"state":null', $html);
        $this->assertDatabaseCount('listing_preferences', 0);
    }

    /** @test */
    public function a_non_seeker_account_gets_no_compact_control(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'agent']));

        $this->assertStringNotContainsString('data-lp-control', $this->renderCompact($this->sellerListing()));
    }

    // -------------------------------------------------------- Buyer vs Tenant

    /** A buyer is offered a sale listing. @test */
    public function a_buyer_gets_a_control_on_a_sale_listing(): void
    {
        $this->actingAs($this->buyer());

        $this->assertStringContainsString('data-lp-control', $this->renderCompact($this->sellerListing('Residential')));
    }

    /** A tenant is offered a lease listing. @test */
    public function a_tenant_gets_a_control_on_a_rental_listing(): void
    {
        $this->actingAs($this->tenant());

        $html = Blade::render(
            '<x-listing-preference.control listing-type="landlord_agent" :listing-id="$id" :compact="true" />',
            ['id' => $this->landlordListing()->id]
        );

        $this->assertStringContainsString('data-lp-control', $html);
        $this->assertStringContainsString('data-lp-listing-type="landlord_agent"', $html);
    }

    /** …and neither is offered the other's market. @test */
    public function a_market_mismatch_renders_no_control_on_a_card(): void
    {
        // Tenant on a sale listing.
        $this->actingAs($this->tenant());
        $this->assertSame('', trim($this->renderCompact($this->sellerListing('Residential'))));

        // Buyer on a lease listing.
        $this->actingAs($this->buyer());
        $this->assertSame('', trim(Blade::render(
            '<x-listing-preference.control listing-type="landlord_agent" :listing-id="$id" :compact="true" />',
            ['id' => $this->landlordListing()->id]
        )));
    }

    /** Fair Housing exclusions travel with the catalog onto the card surface. @test */
    public function excluded_smart_tags_never_reach_a_card_payload(): void
    {
        $this->actingAs($this->buyer());

        $html = $this->renderPage([$this->sellerListing()->id]);

        $this->assertStringNotContainsString('accessible_features', $html);
        $this->assertStringNotContainsString('playground', $html);
    }

    // ---------------------------------------------------------- Bridge identity

    /** A Bridge card addresses the row id, and the control renders for it. @test */
    public function a_bridge_card_renders_from_the_bridge_row_id(): void
    {
        $this->actingAs($this->buyer());

        $row = BridgeProperty::create([
            'provider'      => 'stellar_bridge',
            'listing_key'   => 'STELLAR-CARD-0001',
            'property_type' => 'Residential',
        ]);

        $html = Blade::render(
            '<x-listing-preference.control listing-type="bridge" :listing-id="$id" :compact="true" />',
            ['id' => $row->id]
        );

        $this->assertStringContainsString('data-lp-listing-type="bridge"', $html);
        $this->assertStringContainsString('data-lp-listing-id="' . $row->id . '"', $html);

        // The MLS listing key is never the submitted identity.
        $this->assertStringNotContainsString('STELLAR-CARD-0001', $html);
    }

    // ---------------------------------------------------------------- helpers

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $work();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    /** The JSON payload written for one context token, isolated from the page. */
    private function catalogBlock(string $html, string $token): string
    {
        $needle = '<script type="application/json" data-lp-chip-catalog="' . $token . '">';
        $start  = strpos($html, $needle);

        $this->assertNotFalse($start, "no catalog block was emitted for {$token}");

        $start += strlen($needle);
        $end    = strpos($html, '</script>', $start);

        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /** One render pass is one document: reset what is request-scoped. */
    private function resetPerRequestStores(): void
    {
        app(ListingPreferencePrefetch::class)->forget();
        app(ListingPreferenceChipCatalog::class)->forget();
    }

    /** @param list<int> $ids */
    private function renderPage(array $ids): string
    {
        return Blade::render(
            <<<'BLADE'
            <x-listing-preference.prefetch listing-type="seller_agent" :listing-ids="$ids" />
            @foreach($ids as $id)
                <x-listing-preference.control listing-type="seller_agent" :listing-id="$id" :compact="true" />
            @endforeach
            BLADE,
            ['ids' => $ids]
        );
    }

    private function renderCompact(SellerAgentAuction $listing): string
    {
        return Blade::render(
            '<x-listing-preference.control listing-type="seller_agent" :listing-id="$id" :compact="true" />',
            ['id' => $listing->id]
        );
    }

    private function renderFull(SellerAgentAuction $listing): string
    {
        return Blade::render(
            '<x-listing-preference.control listing-type="seller_agent" :listing-id="$id" />',
            ['id' => $listing->id]
        );
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function tenant(): User
    {
        return User::factory()->create(['user_type' => 'tenant']);
    }

    private function sellerListing(string $propertyType = 'Residential'): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => 900500,
            'address' => '5 Card Lane, St. Petersburg, FL 33701',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_type',
            'meta_value'              => $propertyType,
        ]);

        return $auction;
    }

    private function landlordListing(): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create(['user_id' => 900501]);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'property_type',
            'meta_value'                => 'Residential Property',
        ]);

        return $auction;
    }
}
