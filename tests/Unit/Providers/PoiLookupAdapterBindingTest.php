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
    /**
     * Replace the `poi.default` binding list.
     *
     * A whole-array write, deliberately: `config(['...capabilities.poi.default' => ...])`
     * looks right and does nothing, because Arr::set splits the key on dots and this one
     * literally contains a dot — it would create a nested `poi => default` that the
     * registry never reads, leaving the real binding list in place.
     */
    private function declarePoiDefault(array $bindings): void
    {
        $capabilities                = (array) config('location_providers.capabilities');
        $capabilities['poi.default'] = $bindings;
        config(['location_providers.capabilities' => $capabilities]);
    }

    private function resolveAdapter(): PoiLookupAdapterInterface
    {
        // Bound (not singleton) — forget any prior instance so config changes apply.
        $this->app->forgetInstance(PoiLookupAdapterInterface::class);

        return $this->app->make(PoiLookupAdapterInterface::class);
    }

    public function test_resolves_google_adapter_when_google_is_base_and_key_present(): void
    {
        // The name of this test has always said `when google is base`; the shipped map no
        // longer does that (google_places is an `overlay` for poi.default and overlays are
        // not promoted), so the condition is now stated explicitly instead of inherited
        // from whichever provider happened to survive the enabled-filter.
        config(['services.google.places_key' => 'test-key']);
        $this->declarePoiDefault([['provider' => 'google_places', 'role' => 'base']]);

        $this->assertInstanceOf(GooglePlacesPoiAdapter::class, $this->resolveAdapter());
    }

    /**
     * The posture this phase exists to create: on the SHIPPED map, with a valid key and
     * google_places enabled, the binding must still be the stub — because Google is an
     * overlay there and an overlay is never the existence provider.
     */
    public function test_shipped_config_resolves_to_the_stub_even_with_a_google_key(): void
    {
        config(['services.google.places_key' => 'test-key']);

        $this->assertInstanceOf(StubPoiLookupAdapter::class, $this->resolveAdapter());
    }

    /** And Google declared as a FALLBACK is still not constructed — role must be `base`. */
    public function test_google_declared_as_a_fallback_is_not_constructed(): void
    {
        config(['services.google.places_key' => 'test-key']);
        $this->declarePoiDefault([['provider' => 'google_places', 'role' => 'fallback']]);

        $this->assertInstanceOf(StubPoiLookupAdapter::class, $this->resolveAdapter());
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
