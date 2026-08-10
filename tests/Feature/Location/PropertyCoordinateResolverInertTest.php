<?php

namespace Tests\Feature\Location;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * G1 is architecture only. This suite proves it is inert.
 *
 * The claim under test is not "we did not mean to call anything" but "there is
 * no path by which this code calls anything, dispatches anything, or is reached
 * from a listing flow". Each is asserted rather than described.
 */
class PropertyCoordinateResolverInertTest extends TestCase
{
    private function tampa(): PropertyAddress
    {
        return new PropertyAddress('123 Main St', '', 'Tampa', 'Hillsborough', 'FL', '33602');
    }

    // ── zero outbound ───────────────────────────────────────────────────────

    public function test_resolving_with_no_adapters_makes_no_http_request(): void
    {
        Http::fake();

        (new PropertyCoordinateResolver())->resolve($this->tampa());

        Http::assertNothingSent();
    }

    public function test_the_g1_resolver_is_local_only(): void
    {
        $this->assertTrue(
            (new PropertyCoordinateResolver())->isLocalOnly(),
            'G1 ships with no adapters, so it cannot reach the network'
        );
    }

    public function test_the_g1_resolver_has_no_providers(): void
    {
        $this->assertSame([], (new PropertyCoordinateResolver())->providerIds());
    }

    /**
     * G3 added the US Census rung, so its absence is no longer the guarantee.
     * What still holds — and is what this test was always really protecting —
     * is that the G1 resolver reaches nothing regardless of which adapters
     * exist elsewhere in the codebase, which the three assertions above state
     * directly. The commercial rungs remain unwritten.
     */
    public function test_no_commercial_geocoder_class_exists_yet(): void
    {
        foreach ([
            'App\\Services\\Location\\Coordinates\\Adapters\\CommercialGeocoderAdapter',
            'App\\Services\\Location\\Coordinates\\Adapters\\GeoapifyAdapter',
            'App\\Services\\Location\\Coordinates\\Adapters\\OpenCageAdapter',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} must not exist yet");
        }
    }

    /**
     * The Census adapter exists but cannot be reached by a default resolver:
     * an empty ladder has no rungs, and the adapter is on no ladder.
     */
    public function test_the_census_adapter_is_not_reachable_from_a_default_resolver(): void
    {
        $this->assertNotContains(
            'us_census',
            (new PropertyCoordinateResolver())->providerIds()
        );
    }

    // ── fail-closed behaviour ───────────────────────────────────────────────

    public function test_with_no_adapters_the_result_is_unresolved_with_a_reason(): void
    {
        $result = (new PropertyCoordinateResolver())->resolve($this->tampa());

        $this->assertFalse($result->isResolved());
        $this->assertSame('no_adapter_resolved', $result->reason);
        $this->assertFalse($result->isUsableForLocationDna());
    }

    public function test_an_insufficient_address_short_circuits_before_any_adapter(): void
    {
        $adapter = $this->neverCalledAdapter();

        $result = (new PropertyCoordinateResolver([$adapter]))->resolve(new PropertyAddress('123 Main St'));

        $this->assertSame('insufficient_address', $result->reason);
    }

    public function test_resolution_never_throws_for_an_unresolvable_address(): void
    {
        $result = (new PropertyCoordinateResolver())->resolve(new PropertyAddress());

        $this->assertFalse($result->isResolved());
    }

    // ── ladder behaviour ────────────────────────────────────────────────────

    public function test_the_first_resolving_adapter_wins(): void
    {
        $first  = $this->stubAdapter('first',  CoordinatePrecision::Rooftop);
        $second = $this->stubAdapter('second', CoordinatePrecision::Interpolated);

        $result = (new PropertyCoordinateResolver([$first, $second]))->resolve($this->tampa());

        $this->assertSame('first', $result->provider);
    }

    public function test_an_unavailable_adapter_is_skipped_without_being_resolved(): void
    {
        $unavailable = $this->neverCalledAdapter(available: false);
        $available   = $this->stubAdapter('second', CoordinatePrecision::Rooftop);

        $result = (new PropertyCoordinateResolver([$unavailable, $available]))->resolve($this->tampa());

        $this->assertSame('second', $result->provider);
    }

    public function test_the_intended_precedence_puts_local_sources_before_the_network(): void
    {
        $precedence = PropertyCoordinateResolver::INTENDED_PRECEDENCE;

        $this->assertSame(CoordinateSource::Existing, $precedence[0]);
        $this->assertSame(CoordinateSource::Mls,      $precedence[1]);
        $this->assertSame(CoordinateSource::Geocoder, $precedence[2]);
        $this->assertSame(CoordinateSource::Centroid, $precedence[3]);

        $networkIndex = array_search(CoordinateSource::Geocoder, $precedence, true);

        foreach ([CoordinateSource::Existing, CoordinateSource::Mls] as $local) {
            $this->assertLessThan(
                $networkIndex,
                array_search($local, $precedence, true),
                'Every already-known coordinate must be consulted before a provider is paid'
            );
        }
    }

    // ── no integration, no dispatch ─────────────────────────────────────────

    public function test_no_seller_or_landlord_component_references_the_resolver(): void
    {
        $components = [
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        ];

        foreach ($components as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertIsString($source, "{$path} should be readable");
            $this->assertStringNotContainsString(
                'PropertyCoordinateResolver',
                $source,
                "{$path} must not reference the resolver in G1 — integration is G5/G6"
            );
        }
    }

    public function test_g1_adds_no_location_dna_dispatch(): void
    {
        foreach (glob(base_path('app/Services/Location/Coordinates/*.php')) ?: [] as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('ComputeLocationDna', $source, basename($file));
            $this->assertStringNotContainsString('dispatch(', $source, basename($file));
        }
    }

    public function test_the_new_namespace_contains_no_outbound_client_construction(): void
    {
        foreach (glob(base_path('app/Services/Location/Coordinates/*.php')) ?: [] as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);

            foreach (['Http::', 'new Client', 'GuzzleHttp', 'file_get_contents(', 'curl_'] as $needle) {
                $this->assertStringNotContainsString($needle, $source, basename($file) . " must not use {$needle}");
            }
        }
    }

    public function test_no_google_symbol_appears_in_the_new_namespace(): void
    {
        foreach (glob(base_path('app/Services/Location/Coordinates/*.php')) ?: [] as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('GOOGLE_PLACES', $source, basename($file));
            $this->assertStringNotContainsString('googleapis', $source, basename($file));
        }
    }

    // ── stubs ───────────────────────────────────────────────────────────────

    private function stubAdapter(string $id, CoordinatePrecision $precision): CoordinateProviderAdapterInterface
    {
        return new class ($id, $precision) implements CoordinateProviderAdapterInterface {
            public function __construct(
                private readonly string $id,
                private readonly CoordinatePrecision $precision,
            ) {
            }

            public function providerId(): string { return $this->id; }
            public function source(): CoordinateSource { return CoordinateSource::Existing; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return true; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                return PropertyCoordinateResult::resolved(
                    27.9506, -82.4572, $this->precision, CoordinateSource::Existing, $this->id
                );
            }
        };
    }

    private function neverCalledAdapter(bool $available = true): CoordinateProviderAdapterInterface
    {
        return new class ($available, $this) implements CoordinateProviderAdapterInterface {
            public function __construct(
                private readonly bool $available,
                private readonly \PHPUnit\Framework\TestCase $test,
            ) {
            }

            public function providerId(): string { return 'never'; }
            public function source(): CoordinateSource { return CoordinateSource::Existing; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return $this->available; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                $this->test->fail('This adapter must never be resolved');
            }
        };
    }
}
