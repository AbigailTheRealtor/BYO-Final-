<?php

namespace App\Services\LocationDna\Providers;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\StubNearbyPoiFetcher;
use GuzzleHttp\ClientInterface;

/**
 * NearbyPoiFetcherFactory — the single, registry-driven construction site for the
 * production POI fetcher (Phase 1 Batch 2, Deliverable 4).
 *
 * Before this class, `LocationDnaPoiDistanceService` hard-coded
 * `new GooglePlacesPoiAdapter(...)`, which made `LocationProviderRegistry`
 * non-authoritative for the production fetch path. This factory resolves the
 * effective base provider for `poi.default` through the registry and constructs the
 * matching fetcher. When Phase 2 enables a second provider, the selection changes
 * here — with no edit to the service.
 *
 * BATCH 1 SUB-OPTION 1a IS PRESERVED: `make()` accepts an optional client and forwards
 * it to the adapter, so a test that injects a mock/blocking Guzzle client via the
 * service constructor still reaches the outbound call. A null client falls through to
 * the container binding inside the adapter (telemetry + BlocksGooglePlacesHttpClient).
 *
 * BYTE-IDENTICAL TODAY: only `google_places` is an enabled `poi.default` provider, so
 * `effectiveBase('poi.default')` resolves to it and — combined with the whole-run
 * kill-switch / key guards in the service that fire before any fetch — `make()` returns
 * exactly the `GooglePlacesPoiAdapter` the hard-code produced.
 */
class NearbyPoiFetcherFactory
{
    private const POI_DEFAULT_KEY = 'poi.default';

    /**
     * @param  array  $config  The `config/location_providers.php` array.
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Build the production fetcher for `poi.default`, forwarding an optional client.
     *
     * @param  ClientInterface|null  $client  Injected client forwarded to the adapter
     *                                        (null → the adapter resolves the container
     *                                        binding). Preserves Batch 1 sub-option 1a.
     */
    public function make(?ClientInterface $client = null): NearbyPoiFetcherInterface
    {
        $registry = new LocationProviderRegistry($this->config);
        $base     = $registry->effectiveBase(self::POI_DEFAULT_KEY);

        if (
            ($base['provider'] ?? null) === 'google_places'
            && ! blank(config('services.google.places_key'))
        ) {
            return new GooglePlacesPoiAdapter($client);
        }

        return new StubNearbyPoiFetcher();
    }
}
