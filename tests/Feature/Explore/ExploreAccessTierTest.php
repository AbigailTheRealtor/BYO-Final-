<?php

namespace Tests\Feature\Explore;

use App\Models\BridgeProperty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * §50 A/B and §27 — the VOW seam, tested as the refusal it currently is.
 *
 * There is no VOW capability in this installation, so these do not test a
 * Property Intelligence projection. They test that no path produces one, and
 * that Explore did not quietly implement "logged in = show more".
 */
class ExploreAccessTierTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    protected function setUp(): void
    {
        parent::setUp();
        config(['explore.enabled' => true]);
    }

    /** @test */
    public function an_anonymous_visitor_is_served_the_public_idx_tier(): void
    {
        $this->makeListing();

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->assertJsonPath('access_tier', 'public_idx');
    }

    /**
     * §13. Being signed in is not being VOW-registered. An ordinary
     * authenticated user gets exactly the public projection.
     *
     * @test
     */
    public function an_ordinary_authenticated_user_is_served_the_public_idx_tier(): void
    {
        $this->makeListing();
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->assertJsonPath('access_tier', 'public_idx');
    }

    /**
     * The client cannot ask for a tier. `?vow=true` is not read by anything —
     * the tier is resolved server-side from VowAvailability.
     *
     * @test
     */
    public function a_client_supplied_tier_claim_is_ignored(): void
    {
        $this->makeListing();
        $this->actingAs(User::factory()->create());

        $query = http_build_query([
            'bbox'        => $this->bboxAroundDefault(),
            'vow'         => 'true',
            'access_tier' => 'vow_registered',
            'tier'        => 'vow_registered',
        ]);

        $this->getJson('/api/explore/listings?' . $query)
            ->assertOk()
            ->assertJsonPath('access_tier', 'public_idx');
    }

    /**
     * Even with the flag set, no request produces the VOW tier. The flag is not
     * the gate — see VowAvailability.
     *
     * @test
     */
    public function enabling_the_vow_flag_changes_no_response(): void
    {
        $this->makeListing();

        $anonymous = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->json();

        config(['explore.vow_enabled' => true]);

        $withFlag = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->json();

        $this->assertSame($anonymous, $withFlag);
    }

    /**
     * §14 / §31. The control is ABSENT, not disabled. A greyed-out "Property
     * Intelligence" button tells every visitor that BidYourOffer holds
     * off-market MLS intelligence and is withholding it. It holds none.
     *
     * @test
     */
    public function the_property_intelligence_control_is_not_rendered(): void
    {
        $this->get('/explore')->assertOk()->assertDontSee('Property Intelligence');

        $this->actingAs(User::factory()->create());

        $this->get('/explore')->assertOk()->assertDontSee('Property Intelligence');
    }

    /**
     * §38. No Property Intelligence endpoint exists to be probed.
     *
     * @test
     */
    public function no_property_intelligence_endpoint_exists(): void
    {
        $listing = $this->makeListing();

        foreach ([
            '/api/explore/property/' . $listing->listing_key . '/intelligence',
            '/api/explore/intelligence/' . $listing->listing_key,
            '/explore/intelligence',
        ] as $path) {
            $this->getJson($path)->assertNotFound();
        }
    }

    /**
     * §40. Explore publishes no off-market property. There is no eligible
     * historical inventory and none is manufactured, so no marker can carry an
     * OFF MARKET label.
     *
     * @test
     */
    public function no_off_market_property_is_published(): void
    {
        $this->makeListing(['standard_status' => 'Closed']);
        $this->makeListing(['standard_status' => 'Expired']);
        $this->makeListing(['standard_status' => 'Withdrawn']);

        $body = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('off market', $body);
    }

    /**
     * §16 / §41. No historical fact is presented at all, so none can masquerade
     * as a current one. The projection has no history key to fill.
     *
     * @test
     */
    public function no_history_is_presented(): void
    {
        $this->makeListing([], [
            'ClosePrice'        => 410000,
            'CloseDate'         => '2021-06-01',
            'PreviousListPrice' => 439000,
            'OriginalListPrice' => 549000,
        ]);

        $body = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->getContent();

        foreach (['410000', '439000', '549000', '2021-06-01', 'ClosePrice', 'history'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "{$needle} must not appear");
        }
    }

    /**
     * §7 / §37. Opening a property creates NOTHING. No Seller or Landlord
     * listing is materialised because somebody clicked a house.
     *
     * @test
     */
    public function selecting_a_property_creates_no_listing(): void
    {
        $listing = $this->makeListing();

        $before = [
            \App\Models\SellerAgentAuction::count(),
            \App\Models\LandlordAgentAuction::count(),
            BridgeProperty::count(),
        ];

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->assertOk();
        $this->getJson('/api/explore/listings/' . $listing->listing_key)->assertOk();

        $this->assertSame($before, [
            \App\Models\SellerAgentAuction::count(),
            \App\Models\LandlordAgentAuction::count(),
            BridgeProperty::count(),
        ]);
    }

    /**
     * §3 / §48 / "DO NOT CREATE A SECOND MLS IMPORT/SYNC SYSTEM". Explore reads
     * the cache the existing lazy import fills. It issues no outbound request
     * of its own — not to Bridge, not to Google, not to anything.
     *
     * @test
     */
    public function explore_makes_no_outbound_request(): void
    {
        // The harness already refuses any unstubbed outbound request
        // (Tests\Support\Http\StrayRequestGuardFactory) and BlocksGooglePlacesHttpClient
        // guards the container-bound Guzzle client. Http::fake() with no stubs
        // records anything that does get sent so it can be asserted on.
        Http::fake();

        $listing = $this->makeListing();

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->assertOk();
        $this->getJson('/api/explore/listings/' . $listing->listing_key)->assertOk();

        Http::assertNothingSent();
    }

    /**
     * §48. Provider identity travels so a second MLS can be added later, and it
     * is not a switch presentation branches on.
     *
     * @test
     */
    public function provider_identity_is_retained_in_the_projection(): void
    {
        $this->makeListing();

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->assertJsonPath('listings.0.provider', 'stellar_bridge');
    }

    /**
     * §36 / §30. Sale and rent must never be indistinguishable marker types.
     *
     * @test
     */
    public function the_renderer_distinguishes_sale_from_rent_markers(): void
    {
        $renderer = (string) file_get_contents(public_path('js/explore/explore-3d.js'));

        $this->assertStringContainsString("'For Rent' : 'For Sale'", $renderer);
        $this->assertStringContainsString('typeLabel(listing)', $renderer);
    }
}
