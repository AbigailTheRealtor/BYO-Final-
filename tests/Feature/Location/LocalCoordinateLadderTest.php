<?php

namespace Tests\Feature\Location;

use App\Models\BridgeProperty;
use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\LocalCoordinateLadder;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResolverInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * G2 assembles two local rungs and stops there.
 *
 * The two claims worth proving at this level are precedence — that a coordinate
 * we have already vouched for outranks a feed's second opinion — and closure:
 * that when neither rung answers, the ladder ends. There is no third rung to
 * fall through to, and a miss is a miss rather than the beginning of a provider
 * call.
 */
class LocalCoordinateLadderTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 7712;
    private const LISTING_KEY  = 'STELLAR-MFR-9900001';

    private const EXISTING_LAT = 27.9506;
    private const EXISTING_LNG = -82.4572;

    private const MLS_LAT = 28.1111;
    private const MLS_LNG = -82.2222;

    // ── fixtures ────────────────────────────────────────────────────────────

    private function address(string $street = '123 Main St'): PropertyAddress
    {
        return new PropertyAddress(
            address:       $street,
            unitAddress:   '',
            city:          'Tampa',
            county:        'Hillsborough',
            state:         'FL',
            zip:           '33602',
            listingType:   self::LISTING_TYPE,
            listingId:     self::LISTING_ID,
            mlsListingKey: self::LISTING_KEY,
        );
    }

    private function storedCoordinate(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_county'  => 'Hillsborough',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => self::EXISTING_LAT,
            'geocoded_lng'   => self::EXISTING_LNG,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    private function mlsCoordinate(): void
    {
        BridgeProperty::create([
            'listing_key'       => self::LISTING_KEY,
            'unparsed_address'  => '123 Main St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'latitude'          => self::MLS_LAT,
            'longitude'         => self::MLS_LNG,
        ]);
    }

    // ── precedence ──────────────────────────────────────────────────────────

    public function test_an_existing_coordinate_beats_the_mls_coordinate(): void
    {
        $this->storedCoordinate();
        $this->mlsCoordinate();

        $result = LocalCoordinateLadder::resolver()->resolve($this->address());

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::Existing, $result->source);
        $this->assertSame(self::EXISTING_LAT, $result->latitude);
    }

    public function test_the_mls_coordinate_is_used_when_nothing_is_stored(): void
    {
        $this->mlsCoordinate();

        $result = LocalCoordinateLadder::resolver()->resolve($this->address());

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::Mls, $result->source);
        $this->assertSame(self::MLS_LAT, $result->latitude);
    }

    public function test_the_mls_coordinate_is_used_when_the_stored_one_went_stale(): void
    {
        // The listing moved to a new address; the stored point describes the old
        // one and must not be reused, but the MLS record can still answer.
        $this->storedCoordinate();
        $this->mlsCoordinate();

        $result = LocalCoordinateLadder::resolver()->resolve($this->address('987 Elm St'));

        $this->assertSame(CoordinateSource::Mls, $result->source);
    }

    public function test_the_mls_coordinate_is_used_when_the_stored_one_is_corrupt(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => 0,
            'geocoded_lng'   => 0,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
        ]);
        $this->mlsCoordinate();

        $this->assertSame(
            CoordinateSource::Mls,
            LocalCoordinateLadder::resolver()->resolve($this->address())->source
        );
    }

    public function test_a_complete_miss_returns_unresolved(): void
    {
        $result = LocalCoordinateLadder::resolver()->resolve($this->address());

        $this->assertFalse($result->isResolved());
        $this->assertSame('no_adapter_resolved', $result->reason);
        $this->assertNull($result->exactCoordinates());
        $this->assertNull($result->displayCoordinates());
    }

    public function test_a_complete_miss_does_not_fabricate_a_centroid(): void
    {
        $result = LocalCoordinateLadder::resolver()->resolve($this->address());

        $this->assertNotSame(CoordinateSource::Centroid, $result->source);
        $this->assertNull($result->source);
    }

    // ── the ladder has exactly two rungs ────────────────────────────────────

    public function test_the_ladder_is_exactly_the_two_local_rungs_in_order(): void
    {
        $adapters = LocalCoordinateLadder::adapters();

        $this->assertCount(2, $adapters, 'G2 adds no third adapter');
        $this->assertInstanceOf(ExistingCoordinatesAdapter::class, $adapters[0]);
        $this->assertInstanceOf(BridgeMlsCoordinatesAdapter::class, $adapters[1]);
    }

    public function test_the_ladder_reports_its_providers_in_precedence_order(): void
    {
        $this->assertSame(
            ['existing_coordinates', 'bridge_mls'],
            LocalCoordinateLadder::resolver()->providerIds()
        );
    }

    public function test_no_network_adapter_exists_in_g2(): void
    {
        foreach ([
            'App\\Services\\Location\\Coordinates\\Adapters\\CensusGeocoderAdapter',
            'App\\Services\\Location\\Coordinates\\Adapters\\CommercialGeocoderAdapter',
            'App\\Services\\Location\\Coordinates\\Adapters\\GeoapifyAdapter',
            'App\\Services\\Location\\Coordinates\\Adapters\\CentroidAdapter',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} must not exist in G2");
        }
    }

    public function test_every_rung_declares_itself_local(): void
    {
        foreach (LocalCoordinateLadder::adapters() as $adapter) {
            $this->assertFalse(
                $adapter->requiresNetwork(),
                $adapter->providerId() . ' must not require the network'
            );
        }
    }

    public function test_the_assembled_resolver_is_local_only(): void
    {
        $this->assertTrue(LocalCoordinateLadder::resolver()->isLocalOnly());
    }

    // ── zero outbound ───────────────────────────────────────────────────────

    public function test_an_existing_coordinate_hit_sends_nothing(): void
    {
        Http::fake();
        $this->storedCoordinate();

        $this->assertTrue(LocalCoordinateLadder::resolver()->resolve($this->address())->isResolved());

        Http::assertNothingSent();
    }

    public function test_an_mls_coordinate_hit_sends_nothing(): void
    {
        Http::fake();
        $this->mlsCoordinate();

        $this->assertTrue(LocalCoordinateLadder::resolver()->resolve($this->address())->isResolved());

        Http::assertNothingSent();
    }

    public function test_a_complete_miss_sends_nothing(): void
    {
        Http::fake();

        LocalCoordinateLadder::resolver()->resolve($this->address());

        Http::assertNothingSent();
    }

    public function test_a_stale_coordinate_rejection_sends_nothing(): void
    {
        Http::fake();
        $this->storedCoordinate();

        LocalCoordinateLadder::resolver()->resolve($this->address('987 Elm St'));

        Http::assertNothingSent();
    }

    public function test_an_invalid_coordinate_rejection_sends_nothing(): void
    {
        Http::fake();
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => 91.0,
            'geocoded_lng'   => 200.0,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
        ]);

        LocalCoordinateLadder::resolver()->resolve($this->address());

        Http::assertNothingSent();
    }

    // ── source-level proof, not just behavioural ────────────────────────────

    public function test_no_adapter_constructs_an_outbound_client(): void
    {
        foreach ($this->namespaceFiles() as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);

            foreach (['Http::', 'new Client', 'GuzzleHttp', 'file_get_contents(', 'curl_', 'fsockopen'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($file) . " must not use {$needle}"
                );
            }
        }
    }

    public function test_no_google_symbol_appears_anywhere_in_the_namespace(): void
    {
        foreach ($this->namespaceFiles() as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('GOOGLE_PLACES', $source, basename($file));
            $this->assertStringNotContainsString('googleapis', $source, basename($file));
        }
    }

    public function test_g2_adds_no_location_dna_dispatch(): void
    {
        foreach ($this->namespaceFiles() as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('ComputeLocationDna', $source, basename($file));
            $this->assertStringNotContainsString('dispatch(', $source, basename($file));
        }
    }

    public function test_no_listing_component_references_the_resolver_or_the_ladder(): void
    {
        $components = [
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        ];

        foreach ($components as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertIsString($source, "{$path} should be readable");

            foreach (['PropertyCoordinateResolver', 'LocalCoordinateLadder', 'ExistingCoordinatesAdapter'] as $symbol) {
                $this->assertStringNotContainsString(
                    $symbol,
                    $source,
                    "{$path} must not reference {$symbol} in G2 — integration is G5/G6"
                );
            }
        }
    }

    public function test_the_resolver_interface_is_still_bound_to_nothing(): void
    {
        $this->assertFalse(
            $this->app->bound(PropertyCoordinateResolverInterface::class),
            'G2 wires the ladder but binds nothing, so no flow can inject it by accident'
        );
    }

    /**
     * Every file in the coordinates namespace, including the Adapters
     * subdirectory. Recursive on purpose: a non-recursive glob would have gone
     * on passing while the adapters it was meant to cover sat one level down.
     *
     * @return list<string>
     */
    private function namespaceFiles(): array
    {
        return array_merge(
            glob(base_path('app/Services/Location/Coordinates/*.php')) ?: [],
            glob(base_path('app/Services/Location/Coordinates/Adapters/*.php')) ?: [],
        );
    }
}
