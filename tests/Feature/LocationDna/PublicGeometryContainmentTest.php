<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\TenantCriteriaAuctionBid;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Public Geometry Containment — route-level contract.
 *
 * THE EXPOSURE THIS SUITE EXISTS TO PREVENT
 * -----------------------------------------
 *   Four Buyer/Tenant viewer routes serialised exact user-authored polygon
 *   vertices and exact radius-search centres into page HTML/JavaScript via
 *   `resources/views/components/location-dna-map.blade.php` (`@json($polygons)`,
 *   `@json($radii)`), and printed `location_notes` as visible text. All four
 *   routes carry middleware ["web"] only. Two of them
 *   (/criteria/view/{id}, /tenant/criteria/auction/view/{id}) perform no
 *   approval or authorisation check whatsoever. The geometry was recoverable
 *   from page source alone — no map interaction, no JavaScript execution, no
 *   Google credential, no authentication.
 *
 *   `tenant_criteria/view.blade.php` carries a SECOND, independent decode of the
 *   same blob inside the public bid list, printing radius-centre street
 *   addresses and free-text notes directly. It is reached by any visitor once
 *   the owner has pressed "Show Bids" (`display_bids = 1`), which is a
 *   visibility choice about BIDS, not about the tenant's own address notes.
 *
 * THE INVARIANT
 * -------------
 *   A response from a public viewer route contains NO exact user-authored
 *   geometry, NO radius-centre coordinates or street addresses, and NO
 *   free-text location notes — regardless of who is logged in.
 *
 * Authentication alone is not authorization: these routes are the same page for
 * everyone, so the owner cases below assert withholding rather than passthrough.
 * The owner obtains exact geometry through the dedicated private editor
 * (`partials/location-dna/map-input.blade.php`), which this gate does not touch
 * and which test_private_editing_surface_is_untouched pins.
 *
 * `location_notes` is withheld, not truncated or sanitised.
 *
 * CATEGORY — integration (route). Assertions run against the RAW response body,
 * including <script> contents, never against rendered or visible text. A payload
 * that is present but merely not drawn would fail these assertions, which is the
 * point: containment removes the data server-side rather than hiding it.
 *
 * COVERAGE LIMIT — read before trusting a green run. This project has no browser
 * automation. These tests prove what the server sends; they do not execute
 * JavaScript. Because the containment is server-side, "what the server sends" is
 * the decisive question here — but this suite must not be cited as browser
 * verification of the map renderer.
 */
class PublicGeometryContainmentTest extends TestCase
{
    use DatabaseTransactions;

    /** Distinctive literals that must never reach a public response body. */
    private const VERTEX_LAT_A  = '27.7634891';
    private const VERTEX_LNG_A  = '-82.6401234';
    private const VERTEX_LAT_B  = '27.7644892';
    private const VERTEX_LNG_B  = '-82.6411235';
    private const CENTRE_LAT    = '27.7701234';
    private const CENTRE_LNG    = '-82.6501234';
    private const CENTRE_ADDR   = '1234 Sensitive Lane';
    private const NOTES_TEXT    = 'Close to my mother on Elm Street';
    private const POLYGON_LABEL = 'Near the school';

    /** Retained: a published administrative name is publishable. */
    private const PUBLIC_CITY = 'St. Petersburg, FL';

    private function sensitiveBlob(): string
    {
        return json_encode([
            'schema_version' => 2,
            'cities'         => [self::PUBLIC_CITY],
            'zip_codes'      => ['33708'],
            'counties'       => ['Pinellas County, FL'],
            'state'          => 'Florida',
            'polygons'       => [[
                'label' => self::POLYGON_LABEL,
                'path'  => [
                    ['lat' => (float) self::VERTEX_LAT_A, 'lng' => (float) self::VERTEX_LNG_A],
                    ['lat' => (float) self::VERTEX_LAT_B, 'lng' => (float) self::VERTEX_LNG_B],
                ],
            ]],
            'radius_searches' => [[
                'lat'          => (float) self::CENTRE_LAT,
                'lng'          => (float) self::CENTRE_LNG,
                'radius_miles' => 5,
                'address'      => self::CENTRE_ADDR . ', St. Petersburg, FL',
            ]],
            'flexible_location' => false,
            'location_notes'    => self::NOTES_TEXT,
        ]);
    }

    /** Every literal that constitutes the exposure. */
    private function sensitiveLiterals(): array
    {
        return [
            self::VERTEX_LAT_A, self::VERTEX_LNG_A,
            self::VERTEX_LAT_B, self::VERTEX_LNG_B,
            self::CENTRE_LAT, self::CENTRE_LNG,
            self::CENTRE_ADDR,
            self::NOTES_TEXT,
            self::POLYGON_LABEL,
        ];
    }

    private function assertBodyWithholdsGeometry(string $body, string $route): void
    {
        foreach ($this->sensitiveLiterals() as $literal) {
            $this->assertStringNotContainsString(
                $literal,
                $body,
                "PUBLIC GEOMETRY REGRESSION on {$route}: sensitive literal '{$literal}' was "
                . 'serialised into the public response body.'
            );
        }
    }

    private function owner(): User
    {
        return User::factory()->create();
    }

    /** @return array{0:BuyerAgentAuction,1:User} */
    private function buyerOfferListing(): array
    {
        $owner = $this->owner();

        $listing = new BuyerAgentAuction();
        $listing->user_id     = $owner->id;
        $listing->address     = '500 Example Ave';
        $listing->title       = 'Buyer Offer Listing';
        $listing->is_approved = true;
        $listing->is_draft    = false;
        $listing->save();

        // Required by BuyerOfferListingController::resolveOfferListing(), which
        // 404s anything not stamped as an Offer Listing.
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$listing->fresh('meta'), $owner];
    }

    /** @return array{0:TenantAgentAuction,1:User} */
    private function tenantOfferListing(): array
    {
        $owner   = $this->owner();
        $listing = TenantAgentAuction::factory()->create([
            'user_id'     => $owner->id,
            'is_approved' => true,
            'is_draft'    => false,
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$listing->fresh('meta'), $owner];
    }

    /** @return array{0:BuyerCriteriaAuction,1:User} */
    private function buyerCriteria(): array
    {
        $owner = $this->owner();

        $record = BuyerCriteriaAuction::forceCreate([
            'user_id'     => $owner->id,
            'buyer_id'    => $owner->id,
            'max_price'   => 500000,
            'title'       => 'Buyer Criteria',
            'is_approved' => true,
        ]);

        $record->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$record->fresh('meta'), $owner];
    }

    /**
     * `display_bids` defaults to 1 here, and that is not an arbitrary fixture choice.
     *
     * `tenant_criteria/view.blade.php` guards its bid list with
     * `@if ($auction->display_bids == 1 || $auction->user_id == Auth::user()->id)`.
     * For an anonymous visitor with `display_bids = 0`, the left side is false and
     * `Auth::user()->id` dereferences null, so the page 500s. That is a PRE-EXISTING
     * defect on current main, unrelated to geometry containment and deliberately not
     * fixed here (this branch is a security/privacy fix only). Building the fixture in
     * the state that actually renders keeps these assertions about containment rather
     * than about that bug — and `display_bids = 1` is the realistic public state
     * anyway, since it is what the owner's "Show Bids" button sets.
     *
     * @return array{0:TenantCriteriaAuction,1:User}
     */
    private function tenantCriteria(bool $displayBids = true): array
    {
        $owner = $this->owner();

        $record = TenantCriteriaAuction::forceCreate([
            'user_id'      => $owner->id,
            'is_approved'  => true,
            'display_bids' => $displayBids ? 1 : 0,
        ]);

        $record->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$record->fresh('meta'), $owner];
    }

    // ── No exact geometry on any of the four public routes ───────────────────

    public function test_buyer_offer_listing_view_withholds_geometry_from_public(): void
    {
        [$listing] = $this->buyerOfferListing();

        $response = $this->get("/offer-listing/buyer/view/{$listing->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry($response->getContent(), 'offer-listing/buyer/view');
    }

    public function test_tenant_offer_listing_view_withholds_geometry_from_public(): void
    {
        [$listing] = $this->tenantOfferListing();

        $response = $this->get("/offer-listing/tenant/view/{$listing->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry($response->getContent(), 'offer-listing/tenant/view');
    }

    public function test_buyer_criteria_view_withholds_geometry_from_public(): void
    {
        [$record] = $this->buyerCriteria();

        $response = $this->get("/criteria/view/{$record->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry($response->getContent(), 'criteria/view');
    }

    public function test_tenant_criteria_view_withholds_geometry_from_public(): void
    {
        [$record] = $this->tenantCriteria();

        $response = $this->get("/tenant/criteria/auction/view/{$record->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry($response->getContent(), 'tenant/criteria/auction/view');
    }

    // ── The second, independent decode inside the public bid list ────────────

    public function test_tenant_criteria_public_bid_panel_withholds_geometry(): void
    {
        // display_bids = 1 is what the owner's "Show Bids" button sets. It makes
        // the bid list — and the "Tenant's Location Preferences" panel nested
        // inside it — visible to every visitor, including anonymous ones. That
        // panel used to decode the blob itself rather than reading the
        // controller's variable, printing the radius-centre street address and the
        // free-text notes as visible text. It is a containment site in its own
        // right, and one the hero-map assertions above would never reach.
        [$record] = $this->tenantCriteria(displayBids: true);

        $bidder = User::factory()->create();

        // `tenant_criteria_auction_bid_metas` has no migration on this branch
        // (pre-existing schema drift, out of scope for a security fix), and
        // TenantCriteriaAuctionBid eager-loads that relation via `$with`, so the
        // bid list cannot render without the table. Create it for this test only
        // and drop it again, so nothing else in the process sees a table the real
        // schema does not have.
        $this->withTenantCriteriaBidMetaTable(function () use ($record, $bidder) {
            $bid = TenantCriteriaAuctionBid::forceCreate([
                'tenant_criteria_auction_id' => $record->id,
                'user_id'                    => $bidder->id,
            ]);
            // The bid row itself carries nothing sensitive here; `price` exists
            // only because the accordion header formats it unconditionally.
            // These meta keys carry nothing sensitive. They exist only because the
            // bid row and the criteria-match block read them without a null guard,
            // so the list cannot render without them.
            $bid->saveMeta('price', '2400');
            $bid->saveMeta('first_name', 'Bidding Agent');
            $bid->saveMeta('rental_highlights', json_encode([]));
            $bid->saveMeta('leasing_incentives', json_encode([]));
            $record->saveMeta('max_rent_budget', '2500');

            $body = $this->get("/tenant/criteria/auction/view/{$record->id}")->getContent();

            // Guard against a vacuous pass: if the panel did not render at all,
            // this test would prove nothing about containment.
            $this->assertStringContainsString(
                "Tenant's Location Preferences",
                $body,
                'Fixture failure: the public bid list did not render, so nothing was contained.'
            );

            $this->assertBodyWithholdsGeometry(
                $body,
                'tenant/criteria/auction/view (public bid list panel)'
            );
        });
    }

    /** Run $body with the missing bid-meta table present, then remove it again. */
    private function withTenantCriteriaBidMetaTable(callable $body): void
    {
        $created = false;

        if (! Schema::hasTable('tenant_criteria_auction_bid_metas')) {
            Schema::create('tenant_criteria_auction_bid_metas', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_criteria_auction_bid_id');
                $table->string('meta_key')->nullable();
                $table->text('meta_value')->nullable();
            });
            $created = true;
        }

        try {
            $body();
        } finally {
            if ($created) {
                Schema::dropIfExists('tenant_criteria_auction_bid_metas');
            }
        }
    }

    // ── The public route does not vary by login state ────────────────────────

    public function test_public_route_withholds_geometry_even_from_the_owner(): void
    {
        [$listing, $owner] = $this->buyerOfferListing();

        $response = $this->actingAs($owner)->get("/offer-listing/buyer/view/{$listing->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry(
            $response->getContent(),
            'offer-listing/buyer/view (authenticated as owner)'
        );
    }

    public function test_public_route_withholds_geometry_from_an_unrelated_authenticated_user(): void
    {
        [$listing] = $this->buyerOfferListing();
        $stranger  = $this->owner();

        $response = $this->actingAs($stranger)->get("/offer-listing/buyer/view/{$listing->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry(
            $response->getContent(),
            'offer-listing/buyer/view (authenticated stranger)'
        );
    }

    public function test_criteria_route_withholds_geometry_even_from_the_owner(): void
    {
        [$record, $owner] = $this->tenantCriteria();

        $response = $this->actingAs($owner)->get("/tenant/criteria/auction/view/{$record->id}");

        $response->assertOk();
        $this->assertBodyWithholdsGeometry(
            $response->getContent(),
            'tenant/criteria/auction/view (authenticated as owner)'
        );
    }

    // ── Presence indicator, and permitted names retained ─────────────────────

    public function test_public_response_shows_presence_indicator_and_retains_public_names(): void
    {
        [$listing] = $this->buyerOfferListing();

        $body = $this->get("/offer-listing/buyer/view/{$listing->id}")->getContent();

        $this->assertStringContainsString('Search area preferences provided', $body);
        $this->assertStringContainsString('Additional location preferences provided', $body);

        // A published administrative name remains publishable. Asserted on the
        // ZIP rather than the city because the component renders exactly one tier
        // (Polygon > Radius > Neighborhood > ZIP > City > ...), so with geometry
        // withheld the ZIP tier wins and city chips are not emitted. That
        // precedence is pre-existing component behaviour.
        $this->assertStringContainsString('33708', $body);
    }

    public function test_presence_indicator_is_absent_when_no_geometry_was_authored(): void
    {
        $owner = $this->owner();

        $listing = new BuyerAgentAuction();
        $listing->user_id     = $owner->id;
        $listing->address     = '500 Example Ave';
        $listing->title       = 'No Geometry Listing';
        $listing->is_approved = true;
        $listing->is_draft    = false;
        $listing->save();

        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('location_dna_preferences', json_encode([
            'cities' => [self::PUBLIC_CITY],
        ]));

        $body = $this->get("/offer-listing/buyer/view/{$listing->id}")->getContent();

        // Must not claim geometry exists when none was authored.
        $this->assertStringNotContainsString('Search area preferences provided', $body);
        $this->assertStringNotContainsString('Additional location preferences provided', $body);
    }

    // ── Read-path only: canonical state is never touched ─────────────────────

    public function test_canonical_stored_geometry_is_unchanged_by_a_public_request(): void
    {
        [$listing] = $this->buyerOfferListing();
        $before = $listing->info('location_dna_preferences');

        $this->get("/offer-listing/buyer/view/{$listing->id}")->assertOk();

        $after = BuyerAgentAuction::with('meta')->findOrFail($listing->id)
            ->info('location_dna_preferences');

        $this->assertSame($before, $after, 'A public GET altered canonical stored geometry.');

        // The stored value must still contain the exact geometry: the projection
        // withholds on the way out, it does not clear the record.
        $this->assertStringContainsString(self::VERTEX_LAT_A, (string) $after);
        $this->assertStringContainsString(self::CENTRE_ADDR, (string) $after);
        $this->assertStringContainsString(self::NOTES_TEXT, (string) $after);
    }

    public function test_repeated_public_requests_do_not_erode_stored_geometry(): void
    {
        [$record] = $this->tenantCriteria();

        for ($i = 0; $i < 3; $i++) {
            $this->get("/tenant/criteria/auction/view/{$record->id}")->assertOk();
        }

        $after = TenantCriteriaAuction::with('meta')->findOrFail($record->id)
            ->info('location_dna_preferences');

        $this->assertStringContainsString(self::VERTEX_LAT_A, (string) $after);
        $this->assertStringContainsString(self::NOTES_TEXT, (string) $after);
    }

    // ── Containment must hold with no map credential available ───────────────

    public function test_geometry_survives_when_the_maps_credential_is_unavailable(): void
    {
        // The test harness already forces the Google key blank. Containment and
        // canonical state must both hold with no renderer able to initialise.
        config(['services.google.places_key' => null]);

        [$listing] = $this->buyerOfferListing();

        $body = $this->get("/offer-listing/buyer/view/{$listing->id}")->getContent();
        $this->assertBodyWithholdsGeometry($body, 'buyer view (no maps credential)');

        $after = BuyerAgentAuction::with('meta')->findOrFail($listing->id)
            ->info('location_dna_preferences');
        $this->assertStringContainsString(self::VERTEX_LAT_A, (string) $after);
    }

    // ── The dedicated private surface is not touched by this gate ────────────

    public function test_private_editing_surface_still_renders_exact_geometry(): void
    {
        // BEHAVIOURAL counterpart to the structural assertion below: the owner's
        // editor is the surface where exact geometry is legitimately handed to a
        // browser, and containment must not have narrowed it. Rendered directly
        // rather than through its route because that route is behind auth +
        // email-verified + agent middleware, none of which is what is under test
        // here — what is under test is that the partial still emits the geometry
        // it is given.
        $rendered = view('partials.location-dna.map-input', [
            'existingLocationDna' => json_decode($this->sensitiveBlob(), true),
        ])->render();

        foreach ($this->sensitiveLiterals() as $literal) {
            $this->assertStringContainsString(
                $literal,
                $rendered,
                "OVER-REACH: the private Location DNA editor no longer emits '{$literal}'. "
                . 'Containment is a public-surface gate and must not touch the owner path.'
            );
        }
    }

    public function test_private_editing_surface_is_untouched(): void
    {
        // CATEGORY NOTE — this single assertion is STRUCTURAL, not behavioural.
        // It pins the fact that containment did not strip geometry from the
        // owner's editor, which is where authorised users legitimately obtain
        // exact geometry. It is not evidence that the editor works.
        $partial = file_get_contents(resource_path('views/partials/location-dna/map-input.blade.php'));

        $this->assertStringContainsString('polygons:          @json($ldnaPolygons)', $partial);
        $this->assertStringContainsString('radius_searches:   @json($ldnaRadii)', $partial);
    }
}
