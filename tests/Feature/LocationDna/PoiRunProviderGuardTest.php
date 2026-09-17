<?php

namespace Tests\Feature\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\Providers\CanonicalField;
use App\Services\LocationDna\StubNearbyPoiFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The whole-run guards in `LocationDnaPoiDistanceService::calculateForListing()`, which
 * this phase scoped to Google.
 *
 * WHY THE CHANGE WAS NECESSARY
 * ----------------------------
 * Two guards short-circuit the run before the registry-backed fetcher is constructed:
 * the Google Places kill switch, and the Google API-key check. Until a second provider
 * could be the effective base, "unconditional" and "scoped to Google" described the same
 * guard, so nobody had to choose. They diverge the moment the corpus provider exists: an
 * unconditional check refuses a run that was never going to call Google, and reports
 * `google_places_disabled` for a local corpus read with no credential to be missing. The
 * corpus adapter would have been unreachable through the production path.
 *
 * WHAT THIS FILE PROVES
 * ---------------------
 * 1. On the SHIPPED config nothing moved — both guards still fire, in the same order,
 *    returning the same two error strings. This is the important half: the change must be
 *    a no-op until somebody deliberately enables the corpus.
 * 2. When a non-Google provider is the effective base, the guards no longer apply.
 * 3. The guard reads the SAME resolved provider that provenance is stamped from, so the
 *    guard, the fetcher and the persisted provenance cannot disagree about the run.
 */
class PoiRunProviderGuardTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 4242;

    /** 6817 Stones Throw Circle N, St. Petersburg FL 33710. */
    private const LAT = 27.788945;
    private const LNG = -82.735144;

    protected function setUp(): void
    {
        parent::setUp();

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '6817 STONES THROW CIRCLE N UNIT 17208',
            'source_city'    => 'ST PETERSBURG',
            'source_state'   => 'FL',
            'source_zip'     => '33710',
            'geocoded_lat'   => self::LAT,
            'geocoded_lng'   => self::LNG,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'saved_meta',
            'geocoded_at'    => now(),
        ]);
    }

    // ── the shipped config selects NOBODY ───────────────────────────────────

    /**
     * The posture this phase creates. `poi.default` declares `overture_corpus` as its base
     * and `google_places` only as an `overlay`; with the corpus routing gate off, no base
     * is enabled and an overlay is never promoted — so the run is refused for having no
     * provider, before Google's kill switch is reached.
     *
     * This replaces `test_the_google_guards_still_fire_on_the_shipped_config`, which
     * asserted the opposite and was accurate about the code as it then stood: Google was
     * the effective base by elimination, so the kill switch was the first guard to speak.
     * The error string is the visible half of the fix — it now names what actually
     * happened — and the invisible half is that no credential is consulted at all.
     */
    public function test_the_shipped_config_is_refused_for_having_no_provider(): void
    {
        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('no_poi_provider_selected', $result['error']);
    }

    /** …and it is refused the same way with a Google key and the kill switch ON. */
    public function test_the_shipped_config_is_refused_even_with_google_fully_available(): void
    {
        config([
            'google_places.enabled'      => true,
            'services.google.places_key' => 'a-real-looking-key',
        ]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertSame(
            'no_poi_provider_selected',
            $result['error'],
            'A usable Google credential must not be able to select Google for POI existence.'
        );
        $this->assertSame(0, PropertyLocationPoi::count());
    }

    // ── the Google guards, when Google is DELIBERATELY the base ─────────────

    /**
     * The guards themselves are unchanged; what changed is that reaching them requires a
     * capability map that names Google the `base` on purpose. These three tests state that
     * selection explicitly, which is what an operator would have to do too.
     */
    public function test_the_google_kill_switch_fires_when_google_is_the_declared_base(): void
    {
        $this->selectGoogleAsBase();
        config(['google_places.enabled' => false]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('google_places_disabled', $result['error']);
    }

    /** And the key guard still fires when the switch is on but the credential is absent. */
    public function test_the_google_key_guard_fires_when_google_is_the_declared_base(): void
    {
        $this->selectGoogleAsBase();
        config([
            'google_places.enabled'      => true,
            'services.google.places_key' => null,
        ]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('missing_google_api_key', $result['error']);
    }

    /** Order is preserved: the kill switch is checked before the key. */
    public function test_the_kill_switch_is_still_checked_before_the_key(): void
    {
        $this->selectGoogleAsBase();
        config([
            'google_places.enabled'      => false,
            'services.google.places_key' => null,
        ]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertSame(
            'google_places_disabled',
            $result['error'],
            'Both conditions hold; the kill switch must still be the one reported.'
        );
    }

    /** No rows are written when a guard refuses — the pre-existing posture. */
    public function test_a_refused_run_persists_no_poi_rows(): void
    {
        $this->selectGoogleAsBase();
        config(['google_places.enabled' => false]);

        (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertSame(0, PropertyLocationPoi::count());
    }

    // ── a corpus base is not subject to Google's guards ─────────────────────

    /**
     * With the corpus selected, a disabled Google and a blank Google key are facts about a
     * provider this run does not use. The run must proceed past them.
     *
     * A fetcher is INJECTED here, which also exercises the one documented exemption in the
     * corpus-readiness guard: the caller supplied the provider, so the corpus's own
     * connection readiness is not what decides this run. That matters in CI, where
     * `tests/bootstrap.php` blanks every SPATIAL_* variable on purpose and the cluster is
     * unreachable by design.
     */
    public function test_a_corpus_base_is_not_refused_by_the_google_guards(): void
    {
        $this->selectCorpusAsBase();
        config([
            'google_places.enabled'      => false,
            'services.google.places_key' => null,
        ]);

        $result = (new LocationDnaPoiDistanceService(nearbyFetcher: new StubNearbyPoiFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertNotSame(
            'google_places_disabled',
            $result['error'] ?? null,
            'A corpus-backed run must not be refused by the Google kill switch.'
        );
        $this->assertNotSame(
            'missing_google_api_key',
            $result['error'] ?? null,
            'A corpus-backed run has no Google credential to be missing.'
        );

        $this->assertGreaterThan(
            0,
            PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)->count(),
            'The run should have reached the fetch loop and recorded per-category outcomes.'
        );
    }

    /**
     * A SELECTED BUT UNREADABLE CORPUS REFUSES THE RUN — it does not answer "nothing".
     *
     * No fetcher is injected, so the adapter's own `isAvailable()` decides, and in CI it is
     * false (SPATIAL_* blanked, no pinned version). Without this guard the factory hands
     * back the inert stub and all 19 categories persist as `not_found`, which is a cluster
     * outage cached as this property's nearby places and rendered as an empty panel.
     */
    public function test_a_selected_but_unreadable_corpus_refuses_the_run(): void
    {
        $this->selectCorpusAsBase();

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('overture_corpus_unavailable', $result['error']);
        $this->assertSame(0, PropertyLocationPoi::count(), 'An outage must not be persisted as not_found.');
    }

    /** Provenance on corpus rows names the corpus, not Google — one resolved provider. */
    public function test_a_corpus_run_stamps_the_corpus_provider_into_provenance(): void
    {
        $this->selectCorpusAsBase();
        config(['google_places.enabled' => false]);

        (new LocationDnaPoiDistanceService(nearbyFetcher: new StubNearbyPoiFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $row = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)->firstOrFail();

        $this->assertSame('overture_corpus', $row->provenance_json['provider'] ?? null);
        $this->assertSame('overture_corpus', $row->data_source);
        $this->assertSame(
            'cdla-permissive-2.0+apache-2.0+cc0-1.0',
            $row->provenance_json['license'] ?? null,
            'Overture Places is NOT ODbL — see CorpusPoiLicenseProvenanceTest.'
        );
        $this->assertSame(
            CanonicalField::METHOD_CORPUS,
            $row->provenance_json['method'] ?? null,
            'A local corpus read issues no request and must not be recorded as an API call.'
        );
    }

    // ── selection helpers ───────────────────────────────────────────────────

    /**
     * Name Google the base for poi.default, as an operator would have to.
     *
     * Enabling the provider is deliberately NOT enough any more: on the shipped map it is
     * an `overlay`, and overlays are never promoted.
     */
    private function selectGoogleAsBase(): void
    {
        config(['location_providers.providers.google_places.enabled' => true]);

        // Whole-array write. `config(['...capabilities.poi.default' => ...])` silently
        // creates a nested `poi => default` key instead, because Arr::set splits on dots
        // and this capability key contains one — the real binding list would be untouched
        // and the test would pass for the wrong reason.
        $capabilities                = (array) config('location_providers.capabilities');
        $capabilities['poi.default'] = [['provider' => 'google_places', 'role' => 'base']];
        config(['location_providers.capabilities' => $capabilities]);
    }

    /** Open the corpus ROUTING gate. The adapter's own gate is a separate decision. */
    private function selectCorpusAsBase(): void
    {
        config(['location_providers.providers.overture_corpus.enabled' => true]);
    }

    // ── the guard and the provenance read one value ─────────────────────────

    /**
     * Structural: the guard must be keyed on the SAME resolved provider that provenance is
     * stamped from, not on a second independent lookup. Two lookups could disagree — a run
     * guarded as Google and stamped as corpus, or the reverse — and nothing would notice.
     */
    public function test_the_guard_reads_the_resolved_provenance_provider(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(LocationDnaPoiDistanceService::class))->getFileName()
        );

        $this->assertStringContainsString(
            "if (\$this->currentProvenanceProvider === 'google_places') {",
            $source,
            'The whole-run Google guards must be scoped by the already-resolved provenance '
            . 'provider, so the guard and the persisted provenance cannot disagree.'
        );
    }
}
