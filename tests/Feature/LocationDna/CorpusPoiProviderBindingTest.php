<?php

namespace Tests\Feature\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use App\Services\LocationDna\Providers\LocationProviderRegistry;
use App\Services\LocationDna\Providers\NearbyPoiFetcherFactory;
use App\Services\LocationDna\StubNearbyPoiFetcher;
use App\Services\LocationDna\StubPoiLookupAdapter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The rollout posture: what ships, what resolves when the corpus is switched on, and the
 * one thing that must never happen — a corpus miss falling through to a billable provider.
 *
 * Two independent gates guard this feature, and the tests treat them as independent
 * because they are: `location_providers.providers.overture_corpus.enabled` decides WHICH
 * provider is selected, and `overture_corpus_poi.enabled` decides whether the adapter may
 * read at all. Either one off means no corpus read.
 */
class CorpusPoiProviderBindingTest extends TestCase
{
    // ── shipped defaults ────────────────────────────────────────────────────

    /**
     * The whole point of this phase: the corpus is BUILT and OFF. A merge that silently
     * changed which provider production uses would be a rollout, not a merge.
     */
    public function test_the_corpus_provider_ships_disabled(): void
    {
        $this->assertFalse(
            (bool) config('location_providers.providers.overture_corpus.enabled'),
            'overture_corpus must ship disabled in the capability map.'
        );

        $this->assertFalse(
            (bool) config('overture_corpus_poi.enabled'),
            'The adapter master gate must ship disabled.'
        );

        $this->assertNull(
            config('overture_corpus_poi.corpus_version'),
            'No corpus version may be pinned by default — an unpinned adapter is inert.'
        );
    }

    /** Registered, but filtered out of resolution. Both facts matter. */
    public function test_the_disabled_provider_is_registered_but_never_resolved(): void
    {
        $registry = new LocationProviderRegistry((array) config('location_providers', []));

        $this->assertArrayHasKey('overture_corpus', (array) config('location_providers.providers'));
        $this->assertFalse($registry->isEnabled('overture_corpus'));

        foreach ($registry->resolve('poi.default') as $binding) {
            $this->assertNotSame('overture_corpus', $binding['provider']);
        }
    }

    /**
     * Byte-identical resolution to before the provider was listed. `overture_corpus` is
     * declared FIRST and as `base` in the capability map, so if the enabled-filter ever
     * stopped working this would flip immediately.
     */
    public function test_shipped_config_still_resolves_poi_default_to_google_places(): void
    {
        $registry = new LocationProviderRegistry((array) config('location_providers', []));

        $this->assertSame('google_places', $registry->effectiveBase('poi.default')['provider']);
    }

    /**
     * And the end state on this machine is still the stub, because the Google credential
     * is blank in the suite (and empty in the real .env). Nothing about adding the corpus
     * provider changes today's behaviour.
     */
    public function test_shipped_config_still_binds_the_stubs(): void
    {
        $this->releaseTestHarnessStubs();

        $this->assertInstanceOf(StubPoiLookupAdapter::class, app(PoiLookupAdapterInterface::class));
        $this->assertInstanceOf(StubNearbyPoiFetcher::class, app(NearbyPoiFetcherInterface::class));
    }

    // ── resolution when explicitly enabled ──────────────────────────────────

    /** With both gates open and a readable corpus, both seams resolve to the adapter. */
    public function test_both_seams_resolve_to_the_corpus_adapter_when_enabled(): void
    {
        $this->releaseTestHarnessStubs();
        $this->enableCorpus();
        $this->pretendCorpusIsReadable();

        $this->assertInstanceOf(OvertureCorpusPoiAdapter::class, app(PoiLookupAdapterInterface::class));
        $this->assertInstanceOf(
            OvertureCorpusPoiAdapter::class,
            (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make()
        );
    }

    /** Enabling the provider makes it the effective base — not merely a participant. */
    public function test_the_enabled_corpus_becomes_the_effective_base(): void
    {
        $this->enableCorpus();

        $registry = new LocationProviderRegistry((array) config('location_providers', []));
        $base     = $registry->effectiveBase('poi.default');

        $this->assertSame('overture_corpus', $base['provider']);
        $this->assertSame(LocationProviderRegistry::ROLE_BASE, $base['role']);
    }

    /** The registry gate alone is not enough — the adapter gate must agree. */
    public function test_the_registry_gate_alone_does_not_produce_a_corpus_read(): void
    {
        $this->releaseTestHarnessStubs();
        $this->enableCorpus();
        // overture_corpus_poi.enabled left false — the adapter refuses.

        $this->assertInstanceOf(
            StubNearbyPoiFetcher::class,
            (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make()
        );
        $this->assertInstanceOf(StubPoiLookupAdapter::class, app(PoiLookupAdapterInterface::class));
    }

    /** An enabled adapter with no version pinned is unavailable, so the stub stands in. */
    public function test_an_unpinned_corpus_version_degrades_to_the_stub(): void
    {
        $this->enableCorpus();
        config([
            'overture_corpus_poi.enabled'        => true,
            'overture_corpus_poi.corpus_version' => null,
        ]);

        $this->assertInstanceOf(
            StubNearbyPoiFetcher::class,
            (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make()
        );
    }

    /**
     * The realistic outage: both gates open, but the spatial cluster is unreachable (which
     * is CI's permanent state). The factory must produce an inert fetcher rather than an
     * adapter that raises on every category.
     */
    public function test_an_unreachable_corpus_degrades_to_the_stub_not_to_an_exception(): void
    {
        $this->enableCorpus();
        config([
            'overture_corpus_poi.enabled'        => true,
            'overture_corpus_poi.corpus_version' => 'overture-2026-06-17.0-fl',
        ]);

        // SPATIAL_* is blanked by tests/bootstrap.php, so the connection cannot resolve.
        $this->assertInstanceOf(
            StubNearbyPoiFetcher::class,
            (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make()
        );
    }

    // ── the rule that matters most ──────────────────────────────────────────

    /**
     * NO GOOGLE FALLBACK. With the corpus selected and a Google key present and the Google
     * kill switch on, the resolved provider must still be the corpus. `effectiveBase()`
     * returns one provider and the factory constructs only that one, so a corpus miss is a
     * miss — it can never become a billable Google call.
     */
    public function test_an_enabled_corpus_never_falls_through_to_google(): void
    {
        $this->releaseTestHarnessStubs();
        $this->enableCorpus();
        $this->pretendCorpusIsReadable();

        // Google as available as it could possibly be.
        config([
            'google_places.enabled'      => true,
            'services.google.places_key' => 'a-key-that-must-never-be-used',
        ]);

        $fetcher = (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make();

        $this->assertInstanceOf(OvertureCorpusPoiAdapter::class, $fetcher);
        $this->assertNotInstanceOf(GooglePlacesPoiAdapter::class, $fetcher);
        $this->assertInstanceOf(OvertureCorpusPoiAdapter::class, app(PoiLookupAdapterInterface::class));
    }

    /**
     * Even when the corpus is selected but UNREADABLE — the case where falling back would
     * be most tempting — the answer is the stub, never Google. A provider outage is not a
     * licence to start spending.
     */
    public function test_an_unreadable_corpus_degrades_to_the_stub_and_not_to_google(): void
    {
        $this->enableCorpus();
        config([
            'overture_corpus_poi.enabled'        => true,
            'overture_corpus_poi.corpus_version' => 'overture-2026-06-17.0-fl',
            'google_places.enabled'              => true,
            'services.google.places_key'         => 'a-key-that-must-never-be-used',
        ]);

        $fetcher = (new NearbyPoiFetcherFactory((array) config('location_providers', [])))->make();

        $this->assertInstanceOf(StubNearbyPoiFetcher::class, $fetcher);
        $this->assertNotInstanceOf(GooglePlacesPoiAdapter::class, $fetcher);
    }

    /** Google remains declared as an overlay, never promoted to an existence fallback. */
    public function test_google_stays_an_overlay_in_the_capability_map(): void
    {
        $bindings = (array) config('location_providers.capabilities')['poi.default'];

        $google = array_values(array_filter(
            $bindings,
            static fn (array $b): bool => ($b['provider'] ?? null) === 'google_places'
        ));

        $this->assertCount(1, $google);
        $this->assertSame('overlay', $google[0]['role']);
    }

    /** The corpus is declared first and as base, so enabling it is decisive. */
    public function test_the_corpus_is_declared_first_and_as_base(): void
    {
        $bindings = array_values((array) config('location_providers.capabilities')['poi.default']);

        $this->assertSame('overture_corpus', $bindings[0]['provider']);
        $this->assertSame('base', $bindings[0]['role']);
    }

    /** Its descriptor states $0, US-FL only, and the existence/geometry it can serve. */
    public function test_the_descriptor_states_zero_cost_and_florida_only_coverage(): void
    {
        $descriptor = (array) config('location_providers.providers.overture_corpus');

        $this->assertSame(0.0, $descriptor['cost_per_1k']);
        $this->assertSame(['US-FL'], $descriptor['regions']);
        $this->assertSame(['existence', 'geometry'], $descriptor['serves']);
        $this->assertSame('free', $descriptor['tier']);
        $this->assertSame(OvertureCorpusPoiAdapter::class, $descriptor['adapter']);
    }

    /**
     * `poi.hospitals` keeps its own binding. The corpus holds no hospitals, so it must not
     * have quietly become the base for the one category where a wrong answer costs most.
     */
    public function test_the_hospitals_capability_is_untouched(): void
    {
        $bindings = array_values((array) config('location_providers.capabilities')['poi.hospitals']);

        $this->assertSame('google_places', $bindings[0]['provider']);
        $this->assertSame('base', $bindings[0]['role']);

        foreach ($bindings as $binding) {
            $this->assertNotSame('overture_corpus', $binding['provider']);
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** Turn on the REGISTRY gate only. */
    private function enableCorpus(): void
    {
        config(['location_providers.providers.overture_corpus.enabled' => true]);
    }

    /**
     * Drop the stub INSTANCES that `Tests\TestCase` binds for every test.
     *
     * That harness binding is a deliberate blast-radius guard from the 2026-07-05
     * NearbySearch incident — "no reachable configuration resolves a live provider
     * adapter" — and it SHADOWS the AppServiceProvider closure this file exists to test.
     * `forgetInstance()` is the documented way through it; `PoiLookupAdapterBindingTest`
     * takes the same route.
     *
     * It matters that these tests take it. Without this call every assertion here would
     * read the harness's stub instead of the production binding's answer, and would pass
     * whether or not the binding worked.
     */
    private function releaseTestHarnessStubs(): void
    {
        $this->app->forgetInstance(PoiLookupAdapterInterface::class);
        $this->app->forgetInstance(NearbyPoiFetcherInterface::class);
    }

    /**
     * Turn on the ADAPTER gate and give it a corpus it can genuinely see.
     *
     * NOT a mock and not a container swap: the adapter's availability probe is
     * `Schema::hasTable()` / `hasColumn()`, which SQLite answers perfectly well. Pointing
     * the adapter's connection at the suite's in-memory database and creating a `places`
     * table with the columns it checks makes `isAvailable()` return true by running for
     * real — so the routing assertions exercise the same code path production would.
     *
     * The table is deliberately empty and no PostGIS function exists here; these tests
     * assert WHICH ADAPTER IS SELECTED, never what a query returns. Reading is covered by
     * OvertureCorpusPoiAdapterTest and by the live read-only smoke verification.
     */
    private function pretendCorpusIsReadable(): void
    {
        config([
            'overture_corpus_poi.enabled'        => true,
            'overture_corpus_poi.corpus_version' => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.connection'     => config('database.default'),
        ]);

        Schema::dropIfExists('places');
        Schema::create('places', function (Blueprint $table): void {
            $table->increments('place_id');
            $table->string('corpus_version');
            $table->string('category_key');
            $table->string('name')->nullable();
        });
    }
}
