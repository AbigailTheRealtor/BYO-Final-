<?php

namespace Tests\Feature\VirtualDrive;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Explore\ExploreInventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * The listing data behind both provider pages: stored MLS rows, through
 * Explore's policy and projection, and nothing else.
 */
class VirtualDriveListingApiTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private const CENTER_LAT = 27.7889450;
    private const CENTER_LNG = -82.7351440;

    protected function setUp(): void
    {
        parent::setUp();

        config(['virtual_drive.proof_enabled' => true]);
    }

    /** @test */
    public function each_listing_id_maps_to_its_own_mls_coordinates_in_walk_order(): void
    {
        $this->makeRental(['listing_key' => 'VDRENT0001', 'latitude' => 27.7901550, 'longitude' => -82.7356820]);
        $this->makeListing(['listing_key' => 'VDSALE0001', 'latitude' => self::CENTER_LAT, 'longitude' => self::CENTER_LNG]);

        config(['virtual_drive.test_listing_keys' => ['VDSALE0001', 'VDRENT0001']]);

        $listings = $this->getJson('/dev/virtual-drive/api/listings?set=test')->assertOk()->json('listings');

        $this->assertSame(['VDSALE0001', 'VDRENT0001'], array_column($listings, 'id'));

        $this->assertEqualsWithDelta(self::CENTER_LAT, $listings[0]['latitude'], 1e-7);
        $this->assertEqualsWithDelta(self::CENTER_LNG, $listings[0]['longitude'], 1e-7);
        $this->assertEqualsWithDelta(27.7901550, $listings[1]['latitude'], 1e-7);
        $this->assertEqualsWithDelta(-82.7356820, $listings[1]['longitude'], 1e-7);
    }

    /** @test */
    public function a_sale_listing_is_signed_for_sale_and_a_rental_for_rent(): void
    {
        $this->makeListing(['listing_key' => 'VDSALE0002', 'list_price' => 184900]);
        $this->makeRental(['listing_key' => 'VDRENT0002', 'list_price' => 1950]);

        config(['virtual_drive.test_listing_keys' => ['VDSALE0002', 'VDRENT0002']]);

        $listings = collect($this->getJson('/dev/virtual-drive/api/listings?set=test')->json('listings'))->keyBy('id');

        $this->assertSame('FOR SALE', $listings['VDSALE0002']['sign_label']);
        $this->assertSame('sale', $listings['VDSALE0002']['transaction_type']);
        $this->assertSame('$184,900', $listings['VDSALE0002']['display_price']);

        $this->assertSame('FOR RENT', $listings['VDRENT0002']['sign_label']);
        $this->assertSame('rent', $listings['VDRENT0002']['transaction_type']);
        $this->assertSame('$1,950/mo', $listings['VDRENT0002']['display_price']);
    }

    /** @test */
    public function missing_and_ineligible_keys_are_reported_and_never_filled_in(): void
    {
        $this->makeListing(['listing_key' => 'VDOK000001']);
        $this->makeListing(['listing_key' => 'VDPENDING1', 'standard_status' => 'Pending']);
        $this->makeListing(['listing_key' => 'VDNOIDX001'], ['IDXParticipationYN' => false]);

        config(['virtual_drive.test_listing_keys' => ['VDOK000001', 'VDPENDING1', 'VDNOIDX001', 'VDMISSING1']]);

        $response = $this->getJson('/dev/virtual-drive/api/listings?set=test')->assertOk();

        $this->assertSame(['VDOK000001'], array_column($response->json('listings'), 'id'));
        $this->assertSame(['VDPENDING1', 'VDNOIDX001', 'VDMISSING1'], $response->json('unavailable_keys'));
    }

    /** @test */
    public function what_the_feed_withholds_stays_withheld_and_nothing_is_invented(): void
    {
        $this->makeListing(['listing_key' => 'VDNOADDR01', 'list_price' => 0], ['InternetAddressDisplayYN' => false, 'ListPrice' => 0]);

        config(['virtual_drive.test_listing_keys' => ['VDNOADDR01']]);

        $listing = $this->getJson('/dev/virtual-drive/api/listings?set=test')->json('listings.0');

        $this->assertNull($listing['address']);
        $this->assertNull($listing['postal_code']);
        $this->assertNull($listing['display_price']);
        $this->assertNull($listing['match_score']);
        $this->assertFalse($listing['has_video']);
        // The marker still exists: an address refusal is not a listing refusal.
        $this->assertSame('FOR SALE', $listing['sign_label']);
    }

    /** @test */
    public function nearby_returns_only_listings_inside_the_radius_nearest_first(): void
    {
        $this->makeListing(['listing_key' => 'VDNEAR0145', 'latitude' => self::CENTER_LAT + 0.0013, 'longitude' => self::CENTER_LNG]);
        $this->makeRental(['listing_key' => 'VDNEAR0000', 'latitude' => self::CENTER_LAT, 'longitude' => self::CENTER_LNG]);
        // Inside the query's bounding box, outside its 400 m circle (~446 m away).
        $this->makeListing(['listing_key' => 'VDCORNER01', 'latitude' => self::CENTER_LAT + 0.0030, 'longitude' => self::CENTER_LNG + 0.0030]);
        $this->makeListing(['listing_key' => 'VDORLANDO1', 'latitude' => 28.45, 'longitude' => -81.40]);

        $response = $this->getJson('/dev/virtual-drive/api/listings?' . http_build_query([
            'lat' => self::CENTER_LAT, 'lng' => self::CENTER_LNG, 'radius' => 400,
        ]))->assertOk();

        $this->assertSame(['VDNEAR0000', 'VDNEAR0145'], array_column($response->json('listings'), 'id'));
        $this->assertSame([0, 145], array_column($response->json('listings'), 'distance_m'));
        $this->assertSame(400, $response->json('radius_m'));
    }

    /** @test */
    public function nearby_refuses_a_request_without_a_usable_coordinate(): void
    {
        $this->getJson('/dev/virtual-drive/api/listings')->assertStatus(422);
        $this->getJson('/dev/virtual-drive/api/listings?lat=abc&lng=-82.7')->assertStatus(422);
        $this->getJson('/dev/virtual-drive/api/listings?lat=95&lng=-82.7')->assertStatus(422);
    }

    /**
     * A camera move lands here. It must be a read of stored rows and nothing
     * else: no Bridge request, no discovery, no write to the MLS cache.
     *
     * @test
     */
    public function listing_reads_send_no_provider_request_and_write_nothing(): void
    {
        $this->makeListing(['listing_key' => 'VDREAD0001', 'latitude' => self::CENTER_LAT, 'longitude' => self::CENTER_LNG]);
        $this->makeRental(['listing_key' => 'VDREAD0002', 'latitude' => self::CENTER_LAT + 0.001, 'longitude' => self::CENTER_LNG]);

        config(['virtual_drive.test_listing_keys' => ['VDREAD0001', 'VDREAD0002']]);

        $this->mock(ExploreInventoryService::class, function ($mock) {
            $mock->shouldNotReceive('ensureCurrentFor');
            $mock->shouldNotReceive('refreshRecord');
        });

        Http::fake();

        $before = BridgeProperty::query()->orderBy('id')->get()->toArray();

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower(trim($query->sql));
        });

        $this->getJson('/dev/virtual-drive/api/listings?set=test')
            ->assertOk()
            ->assertJsonPath('provider_requests', 0)
            ->assertJsonPath('source', 'bridge_properties');

        $this->getJson('/dev/virtual-drive/api/listings?lat=' . self::CENTER_LAT . '&lng=' . self::CENTER_LNG)
            ->assertOk()
            ->assertJsonPath('count', 2);

        Http::assertNothingSent();

        $this->assertNotEmpty($statements);

        foreach ($statements as $sql) {
            $this->assertMatchesRegularExpression('/^(select|pragma)\b/', $sql, 'Non-read statement: ' . $sql);
        }

        $this->assertSame($before, BridgeProperty::query()->orderBy('id')->get()->toArray());
    }

    /** @test */
    public function each_listings_actions_target_that_listing_and_no_other(): void
    {
        $this->makeListing(['listing_key' => 'VDACTSALE1']);
        $this->makeRental(['listing_key' => 'VDACTRENT1']);

        config(['virtual_drive.test_listing_keys' => ['VDACTSALE1', 'VDACTRENT1']]);

        $this->actingAs(User::factory()->create());

        $listings = collect($this->getJson('/dev/virtual-drive/api/listings?set=test')->json('listings'))->keyBy('id');

        foreach (['VDACTSALE1' => 'VDACTRENT1', 'VDACTRENT1' => 'VDACTSALE1'] as $own => $other) {
            $actions = collect($listings[$own]['actions'])->keyBy('key');

            $this->assertSame(route('stellar.property.show', ['listingKey' => $own]), $actions['details']['url']);
            $this->assertSame('https://tours.example.com/' . $own, $actions['tour']['url']);
            $this->assertTrue($actions['photos']['available']);

            foreach ($actions as $action) {
                $this->assertStringNotContainsString($other, (string) $action['url'], "{$own}'s {$action['key']} action points at {$other}");
            }

            // MLS-only property: no BidYourOffer page, so no real Ask / Showing form to send anyone to.
            $this->assertFalse($actions['ask']['available']);
            $this->assertFalse($actions['showing']['available']);
            $this->assertFalse($actions['save']['available']);
            $this->assertNotEmpty($actions['save']['reason']);
        }
    }

    /** @test */
    public function a_guest_is_not_handed_a_details_link_that_bounces_to_login(): void
    {
        $this->makeListing(['listing_key' => 'VDGUEST001']);

        config(['virtual_drive.test_listing_keys' => ['VDGUEST001']]);

        $details = collect($this->getJson('/dev/virtual-drive/api/listings?set=test')->json('listings.0.actions'))
            ->firstWhere('key', 'details');

        $this->assertFalse($details['available']);
        $this->assertNull($details['url']);
    }
}
