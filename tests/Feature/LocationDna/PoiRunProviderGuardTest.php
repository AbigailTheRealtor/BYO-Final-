<?php

namespace Tests\Feature\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
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

    // ── the shipped config is unchanged ─────────────────────────────────────

    /**
     * The regression this phase must not cause. Shipped config resolves poi.default to
     * google_places, so the kill switch still refuses the run with the exact error string
     * the 1,127 existing audit rows carry.
     */
    public function test_the_google_guards_still_fire_on_the_shipped_config(): void
    {
        config(['google_places.enabled' => false]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('google_places_disabled', $result['error']);
    }

    /** And the key guard still fires when the switch is on but the credential is absent. */
    public function test_the_google_key_guard_still_fires_on_the_shipped_config(): void
    {
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
        config(['google_places.enabled' => false]);

        (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertSame(0, PropertyLocationPoi::count());
    }

    // ── a non-Google base is not subject to Google's guards ─────────────────

    /**
     * With a non-Google provider selected, a disabled Google and a blank Google key are
     * facts about a provider this run does not use. The run must proceed past them.
     *
     * It then reaches the fetcher, which is the stub here (the corpus adapter cannot see a
     * cluster in CI), so every category records `not_found` — the honest outcome for a
     * provider that returned nothing. What matters is that the run got there at all.
     */
    public function test_a_non_google_base_is_not_refused_by_the_google_guards(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
            'services.google.places_key'                           => null,
        ]);

        $result = (new LocationDnaPoiDistanceService())->calculateForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
        );

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

    /** Provenance on those rows names the corpus, not Google — one resolved provider. */
    public function test_a_non_google_run_stamps_the_corpus_provider_into_provenance(): void
    {
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
        ]);

        (new LocationDnaPoiDistanceService())->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $row = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)->firstOrFail();

        $this->assertSame('overture_corpus', $row->provenance_json['provider'] ?? null);
        $this->assertSame(
            'cdla-permissive-2.0+apache-2.0+cc0-1.0',
            $row->provenance_json['license'] ?? null,
            'Overture Places is NOT ODbL — see CorpusPoiLicenseProvenanceTest.'
        );
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
