<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\Providers\LocationProviderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Validates the provider-agnostic resolution contract (Stage B).
 *
 * Uses a hand-built config array (not the shipped config) so the tests pin
 * behavior independently of how config/location_providers.php is currently tuned.
 */
class LocationProviderRegistryTest extends TestCase
{
    private function config(): array
    {
        return [
            'providers' => [
                'osm_overpass'  => ['tier' => 'free',    'license' => 'odbl',          'serves' => ['existence', 'geometry'], 'adapter' => 'Osm',    'enabled' => false],
                'google_places' => ['tier' => 'premium', 'license' => 'google-tos',    'serves' => ['rating'],                'adapter' => 'Google', 'enabled' => true],
                'geoapify'      => ['tier' => 'free',    'license' => 'geoapify-tos',  'serves' => ['existence'],             'adapter' => 'Geo',    'enabled' => false],
                'fema'          => ['tier' => 'free',    'license' => 'public-domain', 'serves' => ['hazard'],                'adapter' => 'Fema',   'enabled' => true],
            ],
            'capabilities' => [
                'poi.default' => [
                    ['provider' => 'osm_overpass',  'role' => 'base'],
                    ['provider' => 'geoapify',      'role' => 'fallback'],
                    ['provider' => 'google_places', 'role' => 'overlay'],
                ],
                'poi.hospitals' => [
                    ['provider' => 'google_places', 'role' => 'base'],
                    ['provider' => 'osm_overpass',  'role' => 'fallback'],
                ],
                'hazard.flood_zone' => [
                    ['provider' => 'fema', 'role' => 'base'],
                ],
            ],
            'regional_overrides' => [
                'poi.default' => [
                    'US-MT' => [['provider' => 'google_places', 'role' => 'base']],
                ],
            ],
        ];
    }

    public function test_resolve_skips_disabled_providers(): void
    {
        $registry = new LocationProviderRegistry($this->config());

        // osm + geoapify are disabled → only the enabled google overlay survives.
        $resolved = $registry->resolve('poi.schools');

        $this->assertCount(1, $resolved);
        $this->assertSame('google_places', $resolved[0]['provider']);
        $this->assertSame('overlay', $resolved[0]['role']);
    }

    public function test_poi_category_inherits_poi_default(): void
    {
        $registry = new LocationProviderRegistry($this->config());

        // 'poi.parks' has no explicit entry → inherits poi.default.
        $this->assertSame(
            $registry->resolve('poi.default'),
            $registry->resolve('poi.parks')
        );
    }

    public function test_effective_base_promotes_survivor_when_configured_base_disabled(): void
    {
        $registry = new LocationProviderRegistry($this->config());

        // Configured base (osm) is disabled; google (overlay) is the only survivor
        // and is promoted to effective base.
        $base = $registry->effectiveBase('poi.schools');

        $this->assertNotNull($base);
        $this->assertSame('google_places', $base['provider']);
    }

    public function test_regional_override_replaces_binding_list(): void
    {
        $registry = new LocationProviderRegistry($this->config());

        $default = $registry->resolve('poi.default', '*');
        $montana = $registry->resolve('poi.default', 'US-MT');

        $this->assertSame('google_places', $default[0]['provider']); // survivor after filter
        $this->assertSame('google_places', $montana[0]['provider']);
        $this->assertSame('base', $montana[0]['role']); // override declares google as base
    }

    public function test_unmapped_non_poi_category_resolves_empty(): void
    {
        $registry = new LocationProviderRegistry($this->config());

        $this->assertSame([], $registry->resolve('commute'));
    }

    public function test_capability_hash_is_deterministic_and_order_independent(): void
    {
        $a = new LocationProviderRegistry($this->config());

        // Re-key providers in a different order — hash must not change.
        $config = $this->config();
        $config['providers'] = array_reverse($config['providers'], true);
        $b = new LocationProviderRegistry($config);

        $this->assertSame($a->capabilityHash(), $b->capabilityHash());
    }

    public function test_capability_hash_changes_when_a_provider_is_enabled(): void
    {
        $before = (new LocationProviderRegistry($this->config()))->capabilityHash();

        $config = $this->config();
        $config['providers']['osm_overpass']['enabled'] = true; // provider surface changed
        $after = (new LocationProviderRegistry($config))->capabilityHash();

        $this->assertNotSame($before, $after);
    }

    public function test_adapter_classes_only_returns_enabled(): void
    {
        $registry = new LocationProviderRegistry($this->config());

        // Disabled providers (incl. any not-yet-implemented class) are never surfaced.
        $this->assertSame(['Google'], $registry->adapterClassesFor('poi.schools'));
    }
}
