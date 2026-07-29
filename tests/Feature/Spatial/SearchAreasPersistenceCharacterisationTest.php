<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\Concerns\HasSearchAreas;
use App\Models\TenantAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 2B — characterisation of the Search Areas contract through REAL EAV storage.
 *
 * WHY THIS EXISTS ALONGSIDE THE UNIT SUITE
 * ----------------------------------------
 * SearchAreasGeometryContractTest proves the trait's logic against an in-memory
 * fake. That fake cannot tell you whether a 1,200-point polygon survives the
 * `meta_value` column, whether Eloquent's casting mangles a boolean, or whether
 * unicode makes the round trip. Those are properties of the STORAGE, and the
 * only way to characterise them is to write and read real rows.
 *
 * This matters directly to Phase 2C criterion #7. A renderer that emits larger
 * or differently-shaped geometry than today's would meet the criterion in PHP
 * and still lose data in the column — a failure the unit suite is structurally
 * incapable of seeing.
 *
 * VEHICLE
 * -------
 * `TenantAgentAuction` is used because it is one of the four real hosts of
 * `HasSearchAreas` and the only one with a factory. The trait is exercised
 * through a thin host object rather than through the full Livewire component:
 * the component carries hundreds of unrelated required props, and booting it
 * would characterise the component's validation, not the geometry contract.
 * The model, its meta table, and its saveMeta/info implementations are real.
 *
 * SCOPE BOUNDARY
 * --------------
 * PHP and database only. The blob is produced in the browser by
 * `window.ldnaSerialize`; nothing here executes it. See
 * docs/spatial/phase-2b-geometry-contract.md §"What this does not cover".
 */
class SearchAreasPersistenceCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    private function auction(): TenantAgentAuction
    {
        return TenantAgentAuction::factory()->create();
    }

    private function host(): SearchAreasPersistenceHost
    {
        return new SearchAreasPersistenceHost();
    }

    /**
     * Re-read the model from the database so `info()` resolves against freshly
     * loaded rows rather than an in-memory relation cached before the write.
     */
    private function reread(TenantAgentAuction $auction): TenantAgentAuction
    {
        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullBlob(): array
    {
        return [
            'cities'            => ['St. Petersburg', 'Tampa'],
            'zip_codes'         => ['33708'],
            'neighborhoods'     => ['Old Northeast'],
            'counties'          => ['Pinellas'],
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
            ],
            'flexible_location' => true,
            'location_notes'    => 'Near the water, walkable.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Byte-identity through real storage
    // ─────────────────────────────────────────────────────────────────────────

    /** The blob survives a real write → read cycle byte-for-byte. */
    public function test_blob_survives_real_storage_byte_identically(): void
    {
        $auction = $this->auction();
        $encoded = json_encode($this->fullBlob());

        $host = $this->host();
        $host->location_dna_preferences_json = $encoded;
        $host->callSave($auction);

        $stored = $this->reread($auction)->info('location_dna_preferences');

        $this->assertSame($encoded, $stored);
    }

    /**
     * A full save → load → save cycle is stable: the second write equals the first.
     *
     * This is the property criterion #7 actually depends on. A single write
     * proving byte-identity is not enough — the risk is drift accumulating over
     * repeated edit-and-save cycles, which only a re-save can expose.
     */
    public function test_repeated_save_load_cycles_do_not_drift(): void
    {
        $auction = $this->auction();
        $encoded = json_encode($this->fullBlob());

        $host = $this->host();
        $host->location_dna_preferences_json = $encoded;
        $host->callSave($auction);

        $second = $this->host();
        $second->callLoad($this->reread($auction));
        $second->callSave($auction);

        $this->assertSame(
            $encoded,
            $this->reread($auction)->info('location_dna_preferences'),
            'The blob drifted across a save → load → save cycle.'
        );
    }

    /**
     * A large polygon survives the `meta_value` column without truncation.
     *
     * 1,200 vertices is well beyond anything a user draws by hand, and the point
     * is exactly that: it establishes headroom rather than confirming today's
     * typical payload fits. A VARCHAR(255) column would fail this loudly instead
     * of silently clipping someone's search area in production.
     */
    public function test_large_geometry_survives_without_truncation(): void
    {
        $auction = $this->auction();

        $path = [];
        for ($i = 0; $i < 1200; $i++) {
            $path[] = ['lat' => 27.5 + ($i / 100000), 'lng' => -82.5 - ($i / 100000)];
        }

        $encoded = json_encode([
            'polygons' => [['label' => 'Huge area', 'path' => $path]],
            'cities'   => [],
        ]);

        $host = $this->host();
        $host->location_dna_preferences_json = $encoded;
        $host->callSave($auction);

        $stored = $this->reread($auction)->info('location_dna_preferences');

        $this->assertSame(strlen($encoded), strlen($stored), 'The stored blob was truncated.');
        $this->assertSame($encoded, $stored);
        $this->assertCount(1200, json_decode($stored, true)['polygons'][0]['path']);
    }

    /** Unicode and quoting in user-entered fields survive storage unchanged. */
    public function test_unicode_and_quoting_survive_storage(): void
    {
        $auction = $this->auction();

        $encoded = json_encode([
            'cities'         => ['Coeur d\'Alene', 'Ñuñoa', '東京'],
            'location_notes' => "Line one\nLine \"two\" — em dash, emoji 🏖",
        ]);

        $host = $this->host();
        $host->location_dna_preferences_json = $encoded;
        $host->callSave($auction);

        $stored = $this->reread($auction)->info('location_dna_preferences');

        $this->assertSame($encoded, $stored);

        $decoded = json_decode($stored, true);
        $this->assertSame('東京', $decoded['cities'][2]);
        $this->assertStringContainsString('🏖', $decoded['location_notes']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Discrete mirrors, through real meta keys
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The three mirrors land in their own meta keys, derived from the blob.
     *
     * These are the keys Ask AI, the match engine, filtering and public display
     * read. Naming each one explicitly means a renamed key fails here rather
     * than silently degrading a consumer that this suite never loads.
     */
    public function test_discrete_mirrors_are_written_to_their_own_meta_keys(): void
    {
        $auction = $this->auction();

        $host = $this->host();
        $host->location_dna_preferences_json = json_encode([
            'state'    => 'FL',
            'counties' => ['Pinellas', 'Hillsborough'],
            'cities'   => ['Tampa'],
        ]);
        $host->callSave($auction);

        $fresh = $this->reread($auction);

        $this->assertSame('FL', $fresh->info('state'));
        $this->assertSame('["Pinellas","Hillsborough"]', $fresh->info('counties'));
        $this->assertSame('["Tampa"]', $fresh->info('cities'));
    }

    /** Saving twice updates the mirror rows in place rather than duplicating them. */
    public function test_repeated_saves_update_meta_rows_in_place(): void
    {
        $auction = $this->auction();

        $host = $this->host();
        $host->location_dna_preferences_json = json_encode(['cities' => ['Tampa']]);
        $host->callSave($auction);

        $host->location_dna_preferences_json = json_encode(['cities' => ['Orlando']]);
        $host->callSave($auction);

        $fresh = $this->reread($auction);

        $this->assertSame(
            1,
            $fresh->meta->where('meta_key', 'cities')->count(),
            'saveMeta must updateOrCreate, not append a second row.'
        );
        $this->assertSame('["Orlando"]', $fresh->info('cities'));
    }

    /**
     * The legacy `cities` meta fallback works against real rows: a record whose
     * blob predates city tracking still pre-populates from its discrete meta.
     */
    public function test_legacy_cities_meta_prefills_from_real_rows(): void
    {
        $auction = $this->auction();
        $auction->saveMeta('location_dna_preferences', json_encode(['state' => 'FL']));
        $auction->saveMeta('cities', json_encode(['Clearwater', 'Largo']));

        $host = $this->host();
        $host->callLoad($this->reread($auction));

        $this->assertSame(['Clearwater', 'Largo'], $host->existingLocationDna['cities']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FINDING 2B-1 through the real persistence path
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * FINDING 2B-1 · a record with no blob writes boolean `false` into meta.
     *
     * The unit suite establishes that `info()`'s FALSE return survives `?? ''`
     * and leaves the property as boolean false. This records what the DATABASE
     * then stores when that value is persisted — the part a fake cannot answer,
     * because it depends on Eloquent and the column type rather than on the trait.
     *
     * Asserted on the SHAPE, not the literal: the exact stored representation of
     * a PHP boolean is a driver detail (SQLite here, MySQL in production), and
     * pinning it would make this test assert the driver rather than the contract.
     *
     * The first draft of this test asserted the stored value was undecodable
     * JSON. That was wrong, and the failure is worth recording: boolean false
     * persists as "0", and json_decode("0") is integer 0 — perfectly valid JSON.
     * So a consumer doing json_decode($blob, true)['cities'] does not get a
     * clean null from a decode failure; it gets an array-offset read on an
     * integer. That is a strictly worse failure mode than the one predicted, and
     * it is the reason this test asserts "not an array" rather than "not JSON".
     *
     * Characterised, not fixed. 2B is characterisation-only.
     */
    public function test_finding_2b1_absent_blob_persists_a_non_array_value(): void
    {
        $auction = $this->auction();

        $host = $this->host();
        $host->callLoad($this->reread($auction)); // no blob meta exists
        $host->callSave($auction);

        $stored = $this->reread($auction)->info('location_dna_preferences');

        $this->assertIsNotArray(
            json_decode((string) $stored, true),
            'Characterisation: the persisted value does not decode to the blob array consumers expect.'
        );
        // The cities mirror still lands correctly, because json_decode(false)
        // is null and the `?? []` fallback catches it.
        $this->assertSame('[]', $this->reread($auction)->info('cities'));
    }

    /**
     * `info()` returns boolean FALSE — not null — for a key that was never
     * written. Asserted directly, because the trait's behaviour under a missing
     * key only makes sense once this is on record.
     */
    public function test_info_returns_false_for_an_absent_meta_key(): void
    {
        $fresh = $this->reread($this->auction());

        $this->assertFalse($fresh->info('location_dna_preferences'));
    }
}

/**
 * Thin host exercising the real trait against a real model.
 *
 * Declares exactly the three props the trait's documented host contract
 * requires, and nothing else — so the characterisation covers the geometry
 * contract rather than any one component's surrounding behaviour.
 */
class SearchAreasPersistenceHost
{
    use HasSearchAreas;

    public $state    = '';
    public $counties = [];
    public $cities   = [];

    public function callLoad($auction): void
    {
        $this->loadSearchAreas($auction);
    }

    public function callSave($auction): void
    {
        $this->saveSearchAreas($auction);
    }
}
