<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\LocationDnaVersionService;
use App\Services\LocationDna\Providers\LocationProviderRegistry;
use Tests\TestCase;

/**
 * Validates the two independent version stamps (Stage E0):
 *   - determinism / order-independence,
 *   - fetch reacts to provider surface + radius; scoring does not,
 *   - fetch and scoring are distinct and mutually independent.
 */
class LocationDnaVersionServiceTest extends TestCase
{
    private function registry(bool $extraProviderEnabled = false): LocationProviderRegistry
    {
        return new LocationProviderRegistry([
            'providers' => [
                'google_places' => ['tier' => 'premium', 'license' => 'google-tos', 'serves' => ['rating'], 'adapter' => 'G', 'enabled' => true],
                'osm_overpass'  => ['tier' => 'free',    'license' => 'odbl',       'serves' => ['existence'], 'adapter' => 'O', 'enabled' => $extraProviderEnabled],
            ],
            'capabilities'       => ['poi.default' => [['provider' => 'google_places', 'role' => 'base']]],
            'regional_overrides' => [],
        ]);
    }

    public function test_versions_are_deterministic(): void
    {
        $a = new LocationDnaVersionService($this->registry());
        $b = new LocationDnaVersionService($this->registry());

        $this->assertSame($a->fetchVersion(), $b->fetchVersion());
        $this->assertSame($a->scoringVersion(), $b->scoringVersion());
    }

    public function test_fetch_and_scoring_versions_are_distinct(): void
    {
        $svc = new LocationDnaVersionService($this->registry());

        $this->assertNotSame($svc->fetchVersion(), $svc->scoringVersion());
    }

    public function test_capability_change_moves_fetch_version_but_not_scoring(): void
    {
        $before = new LocationDnaVersionService($this->registry(false));
        $after  = new LocationDnaVersionService($this->registry(true)); // enables a second provider

        $this->assertNotSame(
            $before->fetchVersion(),
            $after->fetchVersion(),
            'Enabling a provider must change the fetch version (capabilityHash participates).'
        );
        $this->assertSame(
            $before->scoringVersion(),
            $after->scoringVersion(),
            'Provider surface must not affect the scoring version.'
        );
    }

    public function test_radius_change_moves_fetch_version_but_not_scoring(): void
    {
        config(['location_dna.poi.max_radius_miles' => 10]);
        $small = new LocationDnaVersionService($this->registry());
        $smallFetch   = $small->fetchVersion();
        $smallScoring = $small->scoringVersion();

        config(['location_dna.poi.max_radius_miles' => 25]);
        $large = new LocationDnaVersionService($this->registry());

        $this->assertNotSame($smallFetch, $large->fetchVersion(), 'Radius change must move the fetch version.');
        $this->assertSame($smallScoring, $large->scoringVersion(), 'Radius change must not move the scoring version.');
    }

    public function test_versions_are_sha256_hex(): void
    {
        $svc = new LocationDnaVersionService($this->registry());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $svc->fetchVersion());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $svc->scoringVersion());
    }
}
