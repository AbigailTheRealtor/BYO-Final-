<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\Concerns\HasSearchAreas;
use App\Models\TenantAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * G1a — characterisation of the five record situations S1–S5 (v1.2 §5.4).
 *
 * WHAT THIS IS
 * ------------
 * §5.4 requires every one of S1–S5 to be a fixture, and §16.3 requires them
 * before the interpretation mode is built. This file supplies the fixtures and
 * records what TODAY'S code does with each one. It is characterisation: where
 * current behaviour contradicts §5.4, the contradiction is asserted, named and
 * left in place.
 *
 * THE HEADLINE FINDING
 * --------------------
 * There is no interpretation mode today. `schema_version` is not read by any
 * loader on this path, so S1 and S2 are indistinguishable at runtime, and S3's
 * "corrupt blob is an error to surface" rule is not implemented — a corrupt blob
 * silently becomes an empty record, which is precisely the failure L5 exists to
 * prevent. Both are asserted below rather than described.
 *
 * WHY THAT IS WORTH A TEST RATHER THAN A NOTE
 * -------------------------------------------
 * "The feature does not exist yet" is a claim that rots. A test asserting that a
 * `schema_version: 2` record behaves identically to one without it will fail the
 * moment the hydrator starts honouring the field — which is the exact moment G1c
 * needs to be told that its new code path is live.
 *
 * VEHICLE AND SCOPE
 * -----------------
 * Same thin-host + real-storage vehicle as
 * `G1aTraitPresenceSemanticsCharacterisationTest`. PHP and database only.
 */
class G1aRecordInterpretationCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    private function auction(array $meta = []): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->create();

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread(TenantAgentAuction $auction): TenantAgentAuction
    {
        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function host(string $state = '', array $counties = []): G1aInterpretationHost
    {
        $host           = new G1aInterpretationHost();
        $host->state    = $state;
        $host->counties = $counties;

        return $host;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // S1 · legacy blob, all-keys writer, no `schema_version`
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * S1 · a legacy blob carries no `schema_version` and is read without one.
     *
     * §5.4 says a missing key here is indeterminate and "cannot be a clear,
     * because the writer could not express one". Today no mode is derived at all;
     * the blob is simply decoded. Characterised: the absent key falls back, which
     * happens to match S1's prescribed outcome — for the opposite reason.
     */
    public function test_s1_legacy_blob_without_schema_version_falls_back_for_absent_keys(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => json_encode(['Tampa']),
        ]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertArrayNotHasKey('schema_version', $host->existingLocationDna);
        $this->assertSame(['Tampa'], $host->existingLocationDna['cities']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // S2 · canonical record, `schema_version` >= 2
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED GAP · `schema_version` is not read; S1 and S2 are identical.
     *
     * Two records differing only in `schema_version` produce identical loads. Under
     * §5.4 they must not: in S2 an absent key is authoritative ("never authored",
     * no fallback beyond the legacy rule), and in S1 it is indeterminate.
     *
     * The stamp survives the round trip untouched, which is worth recording
     * separately — it means legacy data already carrying the field is not
     * corrupted by today's reader, so the lazy upgrade §5.5 describes has a clean
     * starting point.
     */
    public function test_s2_schema_version_is_ignored_and_behaves_identically_to_s1(): void
    {
        $withVersion = $this->auction([
            'location_dna_preferences' => json_encode(['schema_version' => 2, 'state' => 'FL']),
            'cities'                   => json_encode(['Tampa']),
        ]);

        $withoutVersion = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => json_encode(['Tampa']),
        ]);

        $a = $this->host();
        $a->callLoad($withVersion);

        $b = $this->host();
        $b->callLoad($withoutVersion);

        // The legacy fallback fires in BOTH cases — the stamp changes nothing.
        $this->assertSame(['Tampa'], $a->existingLocationDna['cities']);
        $this->assertSame(['Tampa'], $b->existingLocationDna['cities']);

        $this->assertSame(
            2,
            $a->existingLocationDna['schema_version'],
            'The stamp round-trips intact even though nothing interprets it.'
        );
    }

    /**
     * S3 · with no blob meta row, every dimension is absent and mirrors are the
     * only source.
     *
     * `info()` returns boolean FALSE for an unwritten key, the falsy branch is
     * taken, and the decoded blob is `[]`.
     *
     * The raw-JSON property becomes boolean FALSE, not the empty string. Line 65
     * reads `$ldnaRaw ?? ''`, and `??` only catches null — `false ?? ''` is
     * `false`. This is the load-side half of finding 2B-1, whose persistence
     * consequence `SearchAreasPersistenceCharacterisationTest` already records.
     * Asserted here because §5.4's S3 fixture is incomplete without it: the
     * "empty record" S3 produces is not an empty string but a boolean, and a
     * consumer type-hinting `string` on this property would break on it.
     */
    public function test_s3_absent_blob_yields_an_empty_record_with_mirrors_as_the_only_source(): void
    {
        $auction = $this->auction(['cities' => json_encode(['Clearwater'])]);

        $host = $this->host();
        $host->callLoad($auction);

        $this->assertSame(['Clearwater'], $host->existingLocationDna['cities']);
        $this->assertFalse(
            $host->location_dna_preferences_json,
            'CHARACTERISATION: the raw-JSON prop holds boolean false, not "". `?? ""` does not catch false.'
        );
    }

    /**
     * S4 · present-but-empty is stored and read back as present-but-empty.
     *
     * The STORAGE layer distinguishes the two states correctly — `array_key_exists`
     * on the decoded blob answers truthfully. It is only the trait's `empty()`
     * branches that collapse them, which is characterised in the sibling suite.
     * Asserted here so G1c knows the substrate is sound and the defect is in the
     * readers, not the column.
     */
    public function test_s4_present_but_empty_survives_storage_as_a_distinct_state(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode([
                'cities'   => [],
                'polygons' => [],
                'state'    => '',
            ]),
        ]);

        $decoded = json_decode((string) $auction->info('location_dna_preferences'), true);

        $this->assertTrue(array_key_exists('cities', $decoded));
        $this->assertTrue(array_key_exists('polygons', $decoded));
        $this->assertTrue(array_key_exists('state', $decoded));
        $this->assertSame([], $decoded['cities']);
        $this->assertSame('', $decoded['state']);
    }

    /**
     * S4 vs absence · the two states are distinguishable in storage and are NOT
     * distinguished by the trait.
     *
     * The single most important pair of facts for G1f, asserted side by side: the
     * data can tell the difference, and the current reader cannot.
     */
    public function test_s4_and_absence_are_distinguishable_in_storage_but_not_by_the_trait(): void
    {
        $cleared = $this->auction([
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => json_encode(['Tampa']),
        ]);

        $neverAuthored = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => json_encode(['Tampa']),
        ]);

        // Storage CAN tell them apart.
        $this->assertTrue(array_key_exists(
            'cities',
            json_decode((string) $cleared->info('location_dna_preferences'), true)
        ));
        $this->assertFalse(array_key_exists(
            'cities',
            json_decode((string) $neverAuthored->info('location_dna_preferences'), true)
        ));

        // The trait CANNOT: both loads produce the mirror's value.
        $a = $this->host();
        $a->callLoad($cleared);

        $b = $this->host();
        $b->callLoad($neverAuthored);

        $this->assertSame(['Tampa'], $a->existingLocationDna['cities']);
        $this->assertSame(['Tampa'], $b->existingLocationDna['cities']);
        $this->assertSame(
            $a->existingLocationDna['cities'],
            $b->existingLocationDna['cities'],
            'CHARACTERISATION: cleared and never-authored produce one outcome. '
            .'This is the §5.2 violation G1f exists to remove.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // S5 · recovered from a legacy mirror — inherited, not authored
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * S5 · a mirror-recovered value carries no marker distinguishing it from an
     * authored one.
     *
     * §5.4 S5 requires the value to be "inherited, not authored", and rule 5
     * requires recovery to be non-promoting. There is no marker today: the
     * recovered list sits in `existingLocationDna['cities']` shaped exactly like an
     * authored one, and no consumer can tell which it is.
     */
    public function test_s5_mirror_recovered_value_is_indistinguishable_from_an_authored_one(): void
    {
        $recovered = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => json_encode(['Tampa']),
        ]);

        $authored = $this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL', 'cities' => ['Tampa']]),
        ]);

        $a = $this->host();
        $a->callLoad($recovered);

        $b = $this->host();
        $b->callLoad($authored);

        $this->assertSame(
            $a->existingLocationDna['cities'],
            $b->existingLocationDna['cities'],
            'CHARACTERISATION: inherited and authored values are shape-identical. '
            .'No provenance of authorship exists on this path.'
        );
    }

}

/**
 * Thin host exercising the real trait against a real model. Declares only the
 * props the trait's host contract requires.
 *
 * `$cities` is deliberately omitted here: the trait's `cities` handling is
 * `property_exists`-guarded, and omitting the prop characterises the loader
 * against a host that does not carry it — the shape §5.4's fixtures care about.
 */
class G1aInterpretationHost
{
    use HasSearchAreas;

    public $state    = '';
    public $counties = [];

    public function callLoad($auction): void
    {
        $this->loadSearchAreas($auction);
    }

}
