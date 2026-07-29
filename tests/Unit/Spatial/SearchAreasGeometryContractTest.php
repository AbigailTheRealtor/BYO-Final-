<?php

namespace Tests\Unit\Spatial;

use App\Http\Livewire\Concerns\HasSearchAreas;
use Tests\TestCase;

/**
 * Phase 2B — characterisation of the Search Areas geometry contract.
 *
 * WHAT THIS IS
 * ------------
 * A CHARACTERISATION suite, not a specification suite. Every assertion below
 * records what `HasSearchAreas` does at this commit. Where the behaviour is
 * surprising, the test still asserts the surprising thing and says so in a
 * comment. Nothing here is a statement about what the behaviour *ought* to be.
 *
 * WHY IT EXISTS
 * -------------
 * Phase 2C criterion #7 — "geometry round-trips byte-identically through the new
 * renderer" — is currently unfalsifiable, because no recorded baseline exists to
 * compare a new renderer against. This file is that baseline. It must be written
 * BEFORE the renderer changes, or it characterises the replacement rather than
 * the thing being replaced.
 *
 * SCOPE BOUNDARY — read before extending
 * --------------------------------------
 * This file exercises PHP only, with no database and no HTTP. The blob it moves
 * around is produced in the browser by `window.ldnaSerialize` in
 * partials/location-dna/map-input.blade.php. Nothing here proves that function
 * emits what these fixtures contain. See SearchAreasWidgetContractTest for the
 * (structural, non-executing) JavaScript side and docs/spatial/
 * phase-2b-geometry-contract.md §"What this does not cover".
 */
class SearchAreasGeometryContractTest extends TestCase
{
    /**
     * A blob carrying all nine keys, with geometry shaped exactly as
     * `ldnaSerialize` emits it: polygons as {label, path:[{lat,lng}]}, radius
     * searches as {lat, lng, radius_miles, address|label}.
     *
     * Float precision is deliberately awkward. Round-tripping 27.7676 is a weak
     * test; round-tripping 27.76761234567 catches a re-encode that silently
     * truncates, which is exactly the silent data loss criterion #7 guards.
     *
     * @return array<string, mixed>
     */
    private function fullBlob(): array
    {
        return [
            'cities'            => ['St. Petersburg', 'Tampa'],
            'zip_codes'         => ['33708', '33701'],
            'neighborhoods'     => ['Old Northeast'],
            'counties'          => ['Pinellas', 'Hillsborough'],
            'state'             => 'FL',
            'polygons'          => [[
                'label' => 'Drawn area 1',
                'path'  => [
                    ['lat' => 27.76761234567, 'lng' => -82.63980987654],
                    ['lat' => 27.77001234567, 'lng' => -82.61980987654],
                    ['lat' => 27.75001234567, 'lng' => -82.62980987654],
                ],
            ]],
            'radius_searches'   => [
                ['lat' => 27.7676, 'lng' => -82.6398, 'radius_miles' => 5.25, 'address' => '100 2nd Ave N'],
                ['lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 10.0, 'label' => 'Circle 2'],
            ],
            'flexible_location' => true,
            'location_notes'    => 'Near the water, walkable.',
        ];
    }

    private function host(): SearchAreasContractHost
    {
        return new SearchAreasContractHost();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // The central finding: the blob is OPAQUE to the PHP layer on the save path
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * saveSearchAreas() writes `$location_dna_preferences_json` through to meta
     * VERBATIM — it never decodes and re-encodes the blob it persists.
     *
     * This is the single most important fact for Phase 2C. Because PHP never
     * re-serialises the geometry, byte-identity across the PHP layer is
     * structural rather than lucky: no float can be truncated, no key can be
     * reordered, no unicode escape can change form, because nothing parses it.
     *
     * The corollary matters just as much: criterion #7's real risk lives
     * ENTIRELY on the JavaScript side, which this suite cannot execute.
     */
    public function test_save_path_writes_the_blob_verbatim_without_reencoding(): void
    {
        $auction = new SearchAreasContractFakeAuction();
        $host    = $this->host();

        // Key order here is deliberately NOT the order fullBlob() declares, and
        // the spacing is non-canonical. A decode/re-encode would normalise both.
        $raw = '{"state":"FL","polygons":[{"label":"a","path":[{"lat":27.76761234567,"lng":-82.63980987654}]}],  "cities":["Tampa"]}';

        $host->location_dna_preferences_json = $raw;
        $host->callSave($auction);

        $this->assertSame(
            $raw,
            $auction->meta['location_dna_preferences'],
            'The blob was altered on the save path. It must be persisted byte-for-byte.'
        );
    }

    /**
     * All nine keys survive a full load → save cycle byte-identically.
     *
     * Asserted on the encoded string, not on a decoded array, because a decoded
     * comparison would pass even if precision were lost in a way PHP's == hides.
     */
    public function test_all_nine_keys_round_trip_byte_identically(): void
    {
        $encoded = json_encode($this->fullBlob());

        $auction = new SearchAreasContractFakeAuction([
            'location_dna_preferences' => $encoded,
        ]);

        $host = $this->host();
        $host->callLoad($auction);
        $host->callSave($auction);

        $this->assertSame($encoded, $auction->meta['location_dna_preferences']);

        // And every key is still present after the cycle, named explicitly so a
        // silently dropped key fails by name rather than by array diff.
        $decoded = json_decode($auction->meta['location_dna_preferences'], true);
        foreach ([
            'cities', 'zip_codes', 'neighborhoods', 'counties', 'state',
            'polygons', 'radius_searches', 'flexible_location', 'location_notes',
        ] as $key) {
            $this->assertArrayHasKey($key, $decoded, "Key '{$key}' did not survive the round trip.");
        }
    }

    /**
     * Geometry survives at full float precision.
     *
     * Separated from the byte-identity test above so that a failure tells you
     * WHICH property broke: string identity, or numeric precision.
     */
    public function test_polygon_and_radius_geometry_survive_at_full_precision(): void
    {
        $encoded = json_encode($this->fullBlob());
        $auction = new SearchAreasContractFakeAuction(['location_dna_preferences' => $encoded]);

        $host = $this->host();
        $host->callLoad($auction);
        $host->callSave($auction);

        $out = json_decode($auction->meta['location_dna_preferences'], true);

        $this->assertSame(27.76761234567, $out['polygons'][0]['path'][0]['lat']);
        $this->assertSame(-82.63980987654, $out['polygons'][0]['path'][0]['lng']);
        $this->assertCount(3, $out['polygons'][0]['path']);

        $this->assertSame(5.25, $out['radius_searches'][0]['radius_miles']);
        $this->assertSame('100 2nd Ave N', $out['radius_searches'][0]['address']);
        // The second entry carries `label` instead of `address` — ldnaSerialize
        // emits one or the other, never both. Characterised, not corrected.
        $this->assertSame('Circle 2', $out['radius_searches'][1]['label']);
        $this->assertArrayNotHasKey('address', $out['radius_searches'][1]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Discrete mirrors
    // ─────────────────────────────────────────────────────────────────────────

    /** The `cities` mirror is derived from the blob, not from `$this->cities`. */
    public function test_cities_mirror_is_derived_from_the_blob(): void
    {
        $auction = new SearchAreasContractFakeAuction();
        $host    = $this->host();

        $host->cities = ['STALE — should not be written'];
        $host->location_dna_preferences_json = json_encode(['cities' => ['Tampa', 'Orlando']]);
        $host->callSave($auction);

        $this->assertSame('["Tampa","Orlando"]', $auction->meta['cities']);
    }

    /** A blob with no `cities` key mirrors an empty array, not null. */
    public function test_missing_cities_key_mirrors_an_empty_json_array(): void
    {
        $auction = new SearchAreasContractFakeAuction();
        $host    = $this->host();

        $host->location_dna_preferences_json = json_encode(['state' => 'FL']);
        $host->callSave($auction);

        $this->assertSame('[]', $auction->meta['cities']);
    }

    /** state and counties are hydrated out of the blob onto the host props. */
    public function test_state_and_counties_hydrate_from_the_blob(): void
    {
        $host = $this->host();
        $host->location_dna_preferences_json = json_encode([
            'state'    => '  FL  ',
            'counties' => ['Pinellas', '', '  ', 'Hillsborough'],
        ]);

        $host->callHydrate();

        // state is trimmed.
        $this->assertSame('FL', $host->state);
        // counties are filtered of blank/whitespace entries and re-indexed.
        $this->assertSame(['Pinellas', 'Hillsborough'], $host->counties);
    }

    /**
     * Non-empty guards: an empty blob value never wipes an existing discrete
     * value. This is the backward-compatibility guarantee the trait's docblock
     * claims, asserted rather than trusted.
     */
    public function test_empty_blob_values_never_wipe_existing_discrete_values(): void
    {
        $host = $this->host();
        $host->state    = 'FL';
        $host->counties = ['Pinellas'];

        $host->location_dna_preferences_json = json_encode([
            'state'    => '',
            'counties' => [],
        ]);

        $host->callHydrate();

        $this->assertSame('FL', $host->state);
        $this->assertSame(['Pinellas'], $host->counties);
    }

    /** A whitespace-only state is treated as empty and does not overwrite. */
    public function test_whitespace_only_state_does_not_overwrite(): void
    {
        $host = $this->host();
        $host->state = 'FL';
        $host->location_dna_preferences_json = json_encode(['state' => '   ']);

        $host->callHydrate();

        $this->assertSame('FL', $host->state);
    }

    /** Invalid JSON causes hydrate to return early, leaving props untouched. */
    public function test_invalid_json_leaves_discrete_props_untouched(): void
    {
        $host = $this->host();
        $host->state    = 'FL';
        $host->counties = ['Pinellas'];
        $host->location_dna_preferences_json = '{not valid json';

        $host->callHydrate();

        $this->assertSame('FL', $host->state);
        $this->assertSame(['Pinellas'], $host->counties);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Load path
    // ─────────────────────────────────────────────────────────────────────────

    /** Legacy discrete `cities` meta is merged in when the blob carries none. */
    public function test_legacy_cities_meta_merges_when_the_blob_has_none(): void
    {
        $auction = new SearchAreasContractFakeAuction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => json_encode(['Clearwater', '', 'Largo']),
        ]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertSame(['Clearwater', 'Largo'], $host->existingLocationDna['cities']);
    }

    /** A blob that already carries cities is NOT overwritten by legacy meta. */
    public function test_blob_cities_take_precedence_over_legacy_meta(): void
    {
        $auction = new SearchAreasContractFakeAuction([
            'location_dna_preferences' => json_encode(['cities' => ['Tampa']]),
            'cities'                   => json_encode(['Clearwater']),
        ]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertSame(['Tampa'], $host->existingLocationDna['cities']);
    }

    /** The load path seeds the prefill blob from discrete props when it lacks them. */
    public function test_load_seeds_prefill_blob_from_discrete_props(): void
    {
        $auction = new SearchAreasContractFakeAuction([
            'location_dna_preferences' => json_encode(['cities' => ['Tampa']]),
        ]);

        $host = $this->host();
        $host->state    = 'FL';
        $host->counties = ['Pinellas', ''];
        $host->callLoad($auction);

        $this->assertSame('FL', $host->existingLocationDna['state']);
        $this->assertSame(['Pinellas'], $host->existingLocationDna['counties']);
    }

    /** loadSearchAreas does not write to the database. */
    public function test_load_performs_no_writes(): void
    {
        $auction = new SearchAreasContractFakeAuction([
            'location_dna_preferences' => json_encode(['cities' => ['Tampa']]),
        ]);

        $this->host()->callLoad($auction);

        $this->assertSame([], $auction->writes, 'loadSearchAreas() must be read-only.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FINDING — recorded, deliberately NOT corrected
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * FINDING 2B-1 · `?? ''` does not normalise `info()`'s missing-key return.
     *
     * The models' `info()` returns boolean FALSE when a meta key is absent (see
     * e.g. App\Models\BuyerAgentAuction::info). The trait then does:
     *
     *     $this->location_dna_preferences_json = $ldnaRaw ?? '';
     *
     * `??` only substitutes for NULL, so a missing blob leaves the property as
     * boolean `false`, NOT the empty string its `= ''` default and its docblock
     * ("Raw blob JSON") both imply.
     *
     * Characterised, not fixed. Changing it is a behaviour change and 2B is
     * characterisation-only. Recorded in docs/spatial/phase-2b-geometry-contract.md.
     */
    public function test_finding_2b1_absent_meta_yields_boolean_false_not_empty_string(): void
    {
        $auction = new SearchAreasContractFakeAuction(); // no meta at all
        $host    = $this->host();

        $host->callLoad($auction);

        $this->assertFalse(
            $host->location_dna_preferences_json,
            'Characterisation: absent meta leaves the property as boolean false.'
        );
        $this->assertNotSame('', $host->location_dna_preferences_json);
    }

    /**
     * FINDING 2B-1, continued — what that `false` does on the very next save.
     *
     * saveSearchAreas() persists the property unchanged, so a load-then-save of
     * a record that never had a blob writes boolean `false` into the meta value
     * rather than an empty string or a JSON literal. Through the real Eloquent
     * path this is cast by the database layer; through this fake it is observed
     * directly. The `cities` mirror still writes `[]`, because json_decode(false)
     * is null and the `?? []` fallback catches it.
     */
    public function test_finding_2b1_false_blob_is_persisted_unchanged(): void
    {
        $auction = new SearchAreasContractFakeAuction();
        $host    = $this->host();

        $host->callLoad($auction);
        $host->callSave($auction);

        $this->assertFalse($auction->meta['location_dna_preferences']);
        $this->assertSame('[]', $auction->meta['cities']);
    }

    /**
     * The `property_exists` guards make the trait safe on a host that omits a
     * prop — the documented "host contract" escape hatch. Asserted because an
     * ungated read would be a fatal error, and "it would have crashed" is not
     * evidence that it does not.
     */
    public function test_trait_is_safe_on_a_host_missing_the_discrete_props(): void
    {
        $auction = new SearchAreasContractFakeAuction([
            'location_dna_preferences' => json_encode(['cities' => ['Tampa'], 'state' => 'FL']),
        ]);

        $host = new SearchAreasContractMinimalHost();
        $host->callLoad($auction);
        $host->callSave($auction);

        // Only the blob and the cities mirror are written; state/counties are skipped.
        $this->assertArrayHasKey('location_dna_preferences', $auction->meta);
        $this->assertArrayHasKey('cities', $auction->meta);
        $this->assertArrayNotHasKey('state', $auction->meta);
        $this->assertArrayNotHasKey('counties', $auction->meta);
    }
}

/**
 * Host declaring the full documented contract: `$state`, `$counties`, `$cities`.
 * Mirrors what all four Hire components declare.
 */
class SearchAreasContractHost
{
    use HasSearchAreas;

    public $state    = '';
    public $counties = [];
    public $cities   = [];

    public function callLoad($auction): void
    {
        $this->loadSearchAreas($auction);
    }

    public function callHydrate(): void
    {
        $this->hydrateDiscreteLocationFromBlob();
    }

    public function callSave($auction): void
    {
        $this->saveSearchAreas($auction);
    }
}

/** Host declaring NONE of the discrete props — exercises the property_exists guards. */
class SearchAreasContractMinimalHost
{
    use HasSearchAreas;

    public function callLoad($auction): void
    {
        $this->loadSearchAreas($auction);
    }

    public function callSave($auction): void
    {
        $this->saveSearchAreas($auction);
    }
}

/**
 * In-memory stand-in for an auction model's EAV meta surface.
 *
 * info() returns boolean FALSE for a missing key, matching the real models
 * (BuyerAgentAuction::info, TenantAgentAuction::info) rather than returning
 * null. That fidelity is the whole point — FINDING 2B-1 is invisible against a
 * stub that returns null.
 *
 * saveMeta() reproduces the real json_encode-on-array behaviour.
 */
class SearchAreasContractFakeAuction
{
    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var list<string> */
    public array $writes = [];

    /** @param array<string, mixed> $meta */
    public function __construct(array $meta = [])
    {
        $this->meta = $meta;
    }

    public function info($key)
    {
        return $this->meta[$key] ?? false;
    }

    public function saveMeta($key, $val)
    {
        if (is_array($val) || is_object($val)) {
            $val = json_encode($val);
        }

        $this->meta[$key] = $val;
        $this->writes[]   = $key;

        return true;
    }
}
