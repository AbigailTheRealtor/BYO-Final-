<?php

namespace Tests\Unit\Providers;

use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use Tests\TestCase;

/**
 * Stage E — proves the AppServiceProvider PoiLookupAdapterInterface binding, now
 * driven by LocationProviderRegistry (config/location_providers.php), resolves to
 * the same concrete adapters as the legacy config('location_dna.poi.provider')
 * logic: Google when google_places is the effective base and a key is present,
 * Stub otherwise.
 */
class PoiLookupAdapterBindingTest extends TestCase
{
    private function resolveAdapter(): PoiLookupAdapterInterface
    {
        // Bound (not singleton) — forget any prior instance so config changes apply.
        $this->app->forgetInstance(PoiLookupAdapterInterface::class);

        return $this->app->make(PoiLookupAdapterInterface::class);
    }

    public function test_resolves_google_adapter_when_google_is_base_and_key_present(): void
    {
        // Current production config: google_places enabled, others disabled.
        config(['services.google.places_key' => 'test-key']);

        $this->assertInstanceOf(GooglePlacesPoiAdapter::class, $this->resolveAdapter());
    }

    public function test_resolves_stub_adapter_when_google_key_is_absent(): void
    {
        config(['services.google.places_key' => null]);

        $this->assertInstanceOf(StubPoiLookupAdapter::class, $this->resolveAdapter());
    }

    public function test_resolves_stub_adapter_when_google_provider_is_disabled(): void
    {
        // Even with a key, if google_places is not an enabled provider the binding
        // must degrade safely to the stub (no crash, no other provider enabled yet).
        config([
            'services.google.places_key'                     => 'test-key',
            'location_providers.providers.google_places.enabled' => false,
        ]);

        $this->assertInstanceOf(StubPoiLookupAdapter::class, $this->resolveAdapter());
    }
}
