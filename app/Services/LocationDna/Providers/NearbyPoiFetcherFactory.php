<?php

namespace App\Services\LocationDna\Providers;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
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
 * WHAT IT RETURNS NOW. `overture_corpus` is the declared `base` for `poi.default`; when
 * its routing gate is on and the corpus is readable, `make()` returns the corpus adapter.
 * When the gate is off no base resolves at all — `google_places` is declared `overlay` and
 * `effectiveBase()` will not promote it — and `make()` returns the inert stub. Google is
 * constructed only when it is the declared `base`, which the shipped `poi.default` map
 * does not do.
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
        $provider = $base['provider'] ?? null;
        $role     = $base['role'] ?? null;

        // Local corpus. Checked FIRST because it is the base when enabled, and because a
        // corpus that is selected but unreadable must land on the stub rather than on
        // Google: a provider outage is not a licence to start spending.
        //
        // `isAvailable()` is consulted here rather than left to the adapter, so that a
        // misconfigured corpus (flag on, version unpinned, cluster unreachable) yields an
        // inert fetcher instead of an adapter that raises on every category. The adapter
        // still guards itself — it is safe to construct directly — but the factory is
        // where "this provider cannot serve right now" belongs.
        if ($provider === OvertureCorpusPoiAdapter::PROVIDER_ID) {
            $adapter = new OvertureCorpusPoiAdapter();

            return $adapter->isAvailable() ? $adapter : new StubNearbyPoiFetcher();
        }

        // GOOGLE IS CONSTRUCTED ONLY WHEN IT IS THE DECLARED `base`, NEVER MERELY WHEN IT
        // IS WHAT IS LEFT. The role check is the second of the two independent barriers
        // (the first is `effectiveBase()` refusing to promote an overlay); together they
        // mean the billable existence provider can only be reached by a capability map
        // that names it `base` for poi.default on purpose, in a diff somebody reads.
        //
        // Belt and braces on purpose: these two barriers live in different files and fail
        // for different reasons, so restoring the old behaviour by accident takes two
        // mistakes rather than one.
        if (
            $provider === 'google_places'
            && $role === LocationProviderRegistry::ROLE_BASE
            && ! blank(config('services.google.places_key'))
        ) {
            return new GooglePlacesPoiAdapter($client);
        }

        // No provider resolved, a provider with no fetcher, or Google in a non-base role.
        // The inert fetcher is the safe landing: it returns no candidates and sends
        // nothing. `LocationDnaPoiDistanceService` refuses the whole run before reaching
        // here when no provider is selected, so this is a backstop, not the reporting path.
        return new StubNearbyPoiFetcher();
    }
}
