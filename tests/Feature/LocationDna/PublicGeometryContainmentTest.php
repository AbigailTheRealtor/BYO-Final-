<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * G0.1 — Public Geometry Containment: route-level contract.
 *
 * FINDING F-P1 — the exposure this suite exists to prevent
 * --------------------------------------------------------
 *   Four public viewer routes serialised exact user-authored polygon vertices
 *   and exact radius-search centres into page HTML/JavaScript via
 *   `resources/views/components/location-dna-map.blade.php` (`@json($polygons)`,
 *   `@json($radii)`). All four carry middleware ["web"] only, and two of them
 *   perform no authorisation or approval check whatsoever. The geometry was
 *   recoverable from page source alone — no map interaction, no JavaScript
 *   execution, no Google credential, no authentication.
 *
 * THE INVARIANT
 * -------------
 *   A response from a public viewer route contains NO exact user-authored
 *   geometry, NO radius-centre coordinates or street addresses, and NO
 *   free-text location notes — regardless of who is logged in.
 *
 * Decision D4 is the load-bearing part, and it is why the owner cases below
 * assert withholding rather than passthrough: authentication alone is not
 * authorization. The public route does not vary by login state. Owners and
 * authorised participants obtain exact geometry only through a dedicated
 * private surface (the `map-input` editor), which this gate does not touch and
 * which test_private_editing_surface_is_untouched pins.
 *
 * Decision D5: `location_notes` is withheld, not truncated or sanitised.
 *
 * CATEGORY — integration (route). Assertions run against the RAW response body,
 * including <script> contents, never against rendered or visible text. A payload
 * that is present but merely not drawn would fail these assertions, which is the
 * point: G0.1 removes the data server-side rather than hiding it.
 *
 * COVERAGE LIMIT — read before trusting a green run
 * -------------------------------------------------
 * This project has no browser automation (no tests/Browser, no Dusk/Playwright/
 * Puppeteer — G0.1 plan §6.2). These tests prove what the server sends. They do
 * not execute JavaScript and therefore cannot prove what a browser does with it.
 * Because G0.1's containment is server-side, "what the server sends" is the
 * decisive question here — but that equivalence does NOT hold for the renderer
 * gates that follow, and this suite must not be cited as browser verification.
 */
class PublicGeometryContainmentTest extends TestCase
{
    use DatabaseTransactions;

    /** Distinctive literals that must never reach a public response body. */
    private const VERTEX_LAT_A = '27.7634891';
    private const VERTEX_LNG_A = '-82.6401234';
    private const VERTEX_LAT_B = '27.7644892';
    private const VERTEX_LNG_B = '-82.6411235';
    private const CENTRE_LAT   = '27.7701234';
    private const CENTRE_LNG   = '-82.6501234';
    private const CENTRE_ADDR  = '1234 Sensitive Lane';
    private const NOTES_TEXT   = 'Close to my mother on Elm Street';
    private const POLYGON_LABEL = 'Near the school';

    /** Retained, permitted at T4 per v1.2 §13.3. */
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

    /** Every literal that constitutes the F-P1 exposure. */
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
                "F-P1 REGRESSION on {$route}: sensitive literal '{$literal}' was serialised "
                . 'into the public response body.'
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

        return [$listing, $owner];
    }

    /** @return array{0:TenantAgentAuction,1:User} */
    private function tenantOfferListing(): array
    {
        $owner   = $this->owner();
        $listing = TenantAgentAuction::factory()->create(['user_id' => $owner->id]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$listing, $owner];
    }

    /** @return array{0:BuyerCriteriaAuction,1:User} */
    private function buyerCriteria(): array
    {
        $owner = $this->owner();

        $record = new BuyerCriteriaAuction();
        $record->user_id     = $owner->id;
        $record->buyer_id    = $owner->id;
        $record->max_price   = 500000;
        $record->title       = 'Buyer Criteria';
        $record->is_approved = true;
        $record->save();

        $record->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$record, $owner];
    }

    /** @return array{0:TenantCriteriaAuction,1:User} */
    private function tenantCriteria(): array
    {
        $owner = $this->owner();

        // PRE-EXISTING DEFECT, not introduced by G0.1 and deliberately not fixed
        // here: TenantCriteriaAuction uses App\Traits\HasListingId, whose
        // `creating` hook assigns `listing_id`, but `tenant_criteria_auctions`
        // has no such column. The migration that adds it
        // (2025_12_05_063000_add_listing_id_to_auctions_tables) is guarded by
        // Schema::hasTable() and runs BEFORE the table is created
        // (2026_06_14_000002), so the column is never added on a fresh migrate.
        // Suppressing model events is the narrowest way to build a fixture
        // without changing a migration, which is out of G0.1 scope.
        $record = TenantCriteriaAuction::withoutEvents(function () use ($owner) {
            $record = new TenantCriteriaAuction();
            $record->user_id     = $owner->id;
            $record->is_approved = true;
            $record->save();

            return $record;
        });

        $record->saveMeta('location_dna_preferences', $this->sensitiveBlob());

        return [$record, $owner];
    }

    // ── T-1 / T-2 / T-3 / T-7: no exact geometry on any public route ─────────

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

    // ── D4: the public route does not vary by login state ────────────────────

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

    // ── T-9: presence indicator, and permitted names retained ────────────────

    public function test_public_response_shows_presence_indicator_and_retains_public_names(): void
    {
        [$listing] = $this->buyerOfferListing();

        $body = $this->get("/offer-listing/buyer/view/{$listing->id}")->getContent();

        $this->assertStringContainsString('Search area preferences provided', $body);
        $this->assertStringContainsString('Additional location preferences provided', $body);

        // v1.2 §13.3 — published administrative names remain publishable at T4.
        // Asserted on the ZIP rather than the city because the component renders
        // exactly one tier (Polygon > Radius > Neighborhood > ZIP > City > ...),
        // so with geometry withheld the ZIP tier wins and city chips are not
        // emitted. That precedence is pre-existing component behaviour, not
        // something G0.1 changed.
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

        $listing->saveMeta('location_dna_preferences', json_encode([
            'cities' => [self::PUBLIC_CITY],
        ]));

        $body = $this->get("/offer-listing/buyer/view/{$listing->id}")->getContent();

        // Must not claim geometry exists when none was authored.
        $this->assertStringNotContainsString('Search area preferences provided', $body);
        $this->assertStringNotContainsString('Additional location preferences provided', $body);
    }

    // ── T-5: G0.1 is read-path only ─────────────────────────────────────────

    public function test_canonical_stored_geometry_is_unchanged_by_a_public_request(): void
    {
        [$listing] = $this->buyerOfferListing();
        $before = $listing->info('location_dna_preferences');

        $this->get("/offer-listing/buyer/view/{$listing->id}")->assertOk();

        $after = BuyerAgentAuction::with('meta')->findOrFail($listing->id)
            ->info('location_dna_preferences');

        $this->assertSame($before, $after, 'A public GET altered canonical stored geometry.');

        // The stored value must still contain the exact geometry: the projection
        // withholds on the way out, it does not clear the record (v1.2 L5).
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

    // ── T-6: failure must never be recorded as intent (v1.2 L5) ─────────────

    public function test_geometry_survives_when_the_maps_credential_is_unavailable(): void
    {
        // The test harness already forces the Google key blank, which is the
        // condition that produced the original data-loss hazard. Containment and
        // canonical state must both hold with no renderer able to initialise.
        config(['services.google.places_key' => null]);

        [$listing] = $this->buyerOfferListing();

        $body = $this->get("/offer-listing/buyer/view/{$listing->id}")->getContent();
        $this->assertBodyWithholdsGeometry($body, 'buyer view (no maps credential)');

        $after = BuyerAgentAuction::with('meta')->findOrFail($listing->id)
            ->info('location_dna_preferences');
        $this->assertStringContainsString(self::VERTEX_LAT_A, (string) $after);
    }

    // ── D4: the dedicated private surface is not touched by this gate ────────

    public function test_private_editing_surface_is_untouched(): void
    {
        // CATEGORY NOTE — this single assertion is STRUCTURAL, not behavioural.
        // It pins the fact that G0.1 did not strip geometry from the owner's
        // editor, which is where authorised users legitimately obtain exact
        // geometry (D4). It is not evidence that the editor works; only browser
        // verification could establish that.
        $partial = file_get_contents(resource_path('views/partials/location-dna/map-input.blade.php'));

        $this->assertStringContainsString('polygons:          @json($ldnaPolygons)', $partial);
        $this->assertStringContainsString('radius_searches:   @json($ldnaRadii)', $partial);
    }
}
