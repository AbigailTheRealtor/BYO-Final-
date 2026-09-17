<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\SmartTagVersion;
use PHPUnit\Framework\TestCase;

/**
 * BridgeRecordAccessor::inputsFor() must hash what the RULES MEAN, not what the
 * database happened to hand back.
 *
 * THE DEFECT THIS PINS. `inputsFor()` used to record raw attribute values, so the
 * two boolean columns the Bridge rules read came out differently depending on
 * provenance:
 *
 *     column:waterfront_yn    just written: true (PHP bool)    re-read: 1 (int)
 *     column:pool_private_yn  just written: true               re-read: 1
 *
 * `SmartTagVersion::structuredInputsHash()` canonical-JSONs those, and `true` and
 * `1` are different JSON, so ONE UNCHANGED LISTING HAD TWO HASHES. The
 * single-record lookup seam derives from the model `updateOrCreate()` returned
 * (write-shaped) while a backfill reads rows fresh (read-shaped), so each
 * re-derived rows the other had already done. Tags stayed correct — re-derivation
 * is idempotent — but "unchanged input skips re-derivation" was not true across
 * those two paths.
 *
 * These tests are all pure: no container, no database, no fixtures.
 */
class BridgeStructuredInputHashTest extends TestCase
{
    /** A single boolean rule over the real column the live rules read. */
    private const BOOLEAN_RULE = [
        ['id' => 'test.waterfront', 'kind' => 'boolean', 'column' => 'waterfront_yn', 'tag' => 'waterfront'],
    ];

    /**
     * @param array<string, mixed> $columns
     * @param array<string, mixed> $raw
     * @param array<int, array<string, mixed>> $rules
     */
    private function hash(array $columns, array $raw = [], array $rules = self::BOOLEAN_RULE): string
    {
        $accessor = new BridgeRecordAccessor($columns, $raw);

        return SmartTagVersion::structuredInputsHash($accessor->inputsFor($rules));
    }

    /* =====================================================================
     * The reported defect
     * ===================================================================== */

    /** @test */
    public function a_written_boolean_and_a_reread_boolean_hash_identically(): void
    {
        $justWritten = $this->hash(['waterfront_yn' => true, 'property_type' => 'Residential']);
        $reRead      = $this->hash(['waterfront_yn' => 1, 'property_type' => 'Residential']);

        $this->assertSame($justWritten, $reRead,
            'A boolean column hashed differently as PHP true and as int 1 — the reported defect.');
    }

    /** @test */
    public function the_same_holds_for_the_other_boolean_column_the_rules_read(): void
    {
        $rules = [['id' => 'test.pool', 'kind' => 'boolean', 'column' => 'pool_private_yn', 'tag' => 'private_pool']];

        $this->assertSame(
            $this->hash(['pool_private_yn' => true], [], $rules),
            $this->hash(['pool_private_yn' => 1], [], $rules),
        );
    }

    /* =====================================================================
     * Boolean canonicalisation — YES / NO / UNKNOWN
     *
     * The groups follow BridgeRecordAccessor::toBool() EXACTLY. Nothing here
     * widens what the accessor accepts; these assert that the hash agrees with
     * the interpretation the rule engine already uses.
     * ===================================================================== */

    /**
     * @test
     * @dataProvider yesRepresentations
     */
    public function every_supported_yes_representation_hashes_identically(mixed $value): void
    {
        $this->assertSame(
            $this->hash(['waterfront_yn' => true]),
            $this->hash(['waterfront_yn' => $value]),
            'A supported YES representation did not canonicalise to the same hash as true.'
        );
    }

    public static function yesRepresentations(): array
    {
        return [
            'bool true'    => [true],
            'int 1'        => [1],
            'string "1"'   => ['1'],
            'string "true"' => ['true'],
            'string "Y"'   => ['Y'],
            'string "yes"' => ['yes'],
            'string "t"'   => ['t'],
            'mixed case "True"' => ['True'],
            'padded " yes "'    => [' yes '],
        ];
    }

    /**
     * @test
     * @dataProvider noRepresentations
     */
    public function every_supported_no_representation_hashes_identically(mixed $value): void
    {
        $this->assertSame(
            $this->hash(['waterfront_yn' => false]),
            $this->hash(['waterfront_yn' => $value]),
            'A supported NO representation did not canonicalise to the same hash as false.'
        );
    }

    public static function noRepresentations(): array
    {
        return [
            'bool false'    => [false],
            'int 0'         => [0],
            'string "0"'    => ['0'],
            'string "false"' => ['false'],
            'string "N"'    => ['N'],
            'string "no"'   => ['no'],
            'string "f"'    => ['f'],
            'mixed case "FALSE"' => ['FALSE'],
            'padded " no "' => [' no '],
        ];
    }

    /**
     * @test
     * @dataProvider unknownRepresentations
     */
    public function every_unknown_representation_hashes_identically(array $columns): void
    {
        $this->assertSame(
            $this->hash(['waterfront_yn' => null]),
            $this->hash($columns),
            'An unknown representation did not canonicalise to the same hash as null.'
        );
    }

    public static function unknownRepresentations(): array
    {
        return [
            'explicit null'   => [['waterfront_yn' => null]],
            'missing key'     => [[]],
            'empty string'    => [['waterfront_yn' => '']],
            'whitespace'      => [['waterfront_yn' => '   ']],
            'unrecognised'    => [['waterfront_yn' => 'maybe']],
            'unrecognised 2'  => [['waterfront_yn' => 'unknown']],
            'int 2'           => [['waterfront_yn' => 2]],
            'array'           => [['waterfront_yn' => ['yes']]],
        ];
    }

    /** @test */
    public function yes_no_and_unknown_remain_three_different_hashes(): void
    {
        $yes = $this->hash(['waterfront_yn' => true]);
        $no = $this->hash(['waterfront_yn' => false]);
        $unknown = $this->hash(['waterfront_yn' => null]);

        $this->assertNotSame($yes, $no, 'YES and NO collapsed to one hash.');
        $this->assertNotSame($yes, $unknown, 'YES and UNKNOWN collapsed to one hash.');
        $this->assertNotSame($no, $unknown, 'NO and UNKNOWN collapsed to one hash.');
    }

    /**
     * The direction that would actually be dangerous.
     *
     * A value nobody recognised must read as UNKNOWN, never as YES: a tag claimed
     * on a property that does not have it is a false statement about a listing,
     * while an absent tag is merely a missing one.
     *
     * @test
     */
    public function an_unrecognised_value_never_canonicalises_to_yes(): void
    {
        foreach (['maybe', 'unknown', 'pending', 'TBD', '2', 'Y?'] as $value) {
            $this->assertNotSame(
                $this->hash(['waterfront_yn' => true]),
                $this->hash(['waterfront_yn' => $value]),
                "'{$value}' canonicalised to YES."
            );
            $this->assertSame(
                $this->hash(['waterfront_yn' => null]),
                $this->hash(['waterfront_yn' => $value]),
                "'{$value}' did not canonicalise to UNKNOWN."
            );
        }
    }

    /* =====================================================================
     * The other rule kinds Bridge uses
     * ===================================================================== */

    /**
     * `equals` reads scalar(): trimmed, blank-to-null.
     *
     * @test
     */
    public function scalar_readings_canonicalise_whitespace_and_blankness(): void
    {
        $rules = [['id' => 't', 'kind' => 'equals', 'field' => 'Cooling', 'tag' => 'central_air', 'values' => ['Central Air']]];

        $this->assertSame(
            $this->hash([], ['Cooling' => 'Central Air'], $rules),
            $this->hash([], ['Cooling' => '  Central Air  '], $rules),
            'Surrounding whitespace changed a scalar hash.'
        );

        $this->assertSame(
            $this->hash([], ['Cooling' => null], $rules),
            $this->hash([], ['Cooling' => '   '], $rules),
            'A blank scalar did not canonicalise to the same hash as null.'
        );

        $this->assertNotSame(
            $this->hash([], ['Cooling' => 'Central Air'], $rules),
            $this->hash([], ['Cooling' => 'Wall Unit'], $rules),
            'Two different scalars collapsed to one hash.'
        );
    }

    /**
     * `any` / `prefix` / `nonempty` / `vocab` all read values(): trimmed, blanks
     * dropped, duplicates collapsed, a comma string split.
     *
     * @test
     */
    public function list_readings_canonicalise_blanks_duplicates_and_string_form(): void
    {
        $rules = [['id' => 't', 'kind' => 'any', 'field' => 'InteriorFeatures', 'tag' => 'open_floorplan', 'values' => ['Open Floorplan']]];

        $canonical = $this->hash([], ['InteriorFeatures' => ['Open Floorplan', 'Vaulted Ceiling(s)']], $rules);

        $this->assertSame($canonical,
            $this->hash([], ['InteriorFeatures' => ['  Open Floorplan  ', 'Vaulted Ceiling(s)', '']], $rules),
            'Blank and padded list entries changed the hash.');

        $this->assertSame($canonical,
            $this->hash([], ['InteriorFeatures' => ['Open Floorplan', 'Open Floorplan', 'Vaulted Ceiling(s)']], $rules),
            'A duplicated list entry changed the hash.');

        $this->assertSame($canonical,
            $this->hash([], ['InteriorFeatures' => 'Open Floorplan,Vaulted Ceiling(s)'], $rules),
            'The comma-string form of a list hashed differently from the array form.');

        $this->assertNotSame($canonical,
            $this->hash([], ['InteriorFeatures' => ['Open Floorplan']], $rules),
            'Dropping a list entry did not change the hash.');
    }

    /**
     * `number_gt` reads number(): numeric strings and numbers agree.
     *
     * @test
     */
    public function numeric_readings_canonicalise_string_and_native_numbers(): void
    {
        $rules = [['id' => 't', 'kind' => 'number_gt', 'column' => 'lot_size_sqft', 'tag' => 'large_lot', 'threshold' => 20000]];

        $this->assertSame(
            $this->hash(['lot_size_sqft' => 43560], [], $rules),
            $this->hash(['lot_size_sqft' => '43560'], [], $rules),
            'A numeric string hashed differently from the same number.'
        );

        $this->assertSame(
            $this->hash(['lot_size_sqft' => 43560], [], $rules),
            $this->hash(['lot_size_sqft' => 43560.0], [], $rules),
            'An int and the equivalent float hashed differently.'
        );

        $this->assertSame(
            $this->hash(['lot_size_sqft' => null], [], $rules),
            $this->hash(['lot_size_sqft' => 'not a number'], [], $rules),
            'A non-numeric value did not canonicalise to the same hash as null.'
        );

        $this->assertNotSame(
            $this->hash(['lot_size_sqft' => 43560], [], $rules),
            $this->hash(['lot_size_sqft' => 10000], [], $rules),
            'Two different numbers collapsed to one hash.'
        );
    }

    /* =====================================================================
     * Structure
     * ===================================================================== */

    /**
     * One field read by two DIFFERENT readings keeps both.
     *
     * Keying by field alone would let the later rule's interpretation overwrite
     * the earlier one, so a change only the overwritten reading could see would
     * stop making the listing stale.
     *
     * @test
     */
    public function one_field_read_two_ways_records_both_readings(): void
    {
        $rules = [
            ['id' => 'a', 'kind' => 'boolean', 'column' => 'pets_allowed', 'tag' => 'pets_allowed'],
            ['id' => 'b', 'kind' => 'equals', 'column' => 'pets_allowed', 'tag' => 'pets_allowed', 'values' => ['Yes']],
        ];

        $inputs = (new BridgeRecordAccessor(['pets_allowed' => 'Yes'], []))->inputsFor($rules);

        $this->assertArrayHasKey('column:pets_allowed#boolean', $inputs);
        $this->assertArrayHasKey('column:pets_allowed#scalar', $inputs);
        $this->assertTrue($inputs['column:pets_allowed#boolean']);
        $this->assertSame('Yes', $inputs['column:pets_allowed#scalar']);
    }

    /**
     * The four kinds that share values() collapse to ONE entry, because they are
     * genuinely the same reading of the same field.
     *
     * @test
     */
    public function kinds_that_share_a_reading_collapse_to_one_entry(): void
    {
        $rules = [
            ['id' => 'a', 'kind' => 'any', 'field' => 'Vegetation', 'tag' => 'mature_trees', 'values' => ['Mature Landscaping']],
            ['id' => 'b', 'kind' => 'vocab', 'field' => 'Vegetation', 'vocab' => 'bridge_vegetation'],
            ['id' => 'c', 'kind' => 'prefix', 'field' => 'Vegetation', 'tag' => 'wooded', 'prefix' => 'wood'],
            ['id' => 'd', 'kind' => 'nonempty', 'field' => 'Vegetation', 'tag' => 'landscaped'],
        ];

        $inputs = (new BridgeRecordAccessor([], ['Vegetation' => ['Mature Landscaping']]))->inputsFor($rules);

        $valuesKeys = array_filter(array_keys($inputs), static fn (string $k) => str_contains($k, 'Vegetation'));

        $this->assertSame(['field:Vegetation#values'], array_values($valuesKeys),
            'Four kinds sharing values() produced more than one hash entry.');
    }

    /**
     * A kind this class has never heard of still contributes to the hash.
     *
     * Dropping it would mean a change to that field never made the listing stale
     * again — change detection that silently stops watching is the worse failure.
     *
     * @test
     */
    public function an_unrecognised_rule_kind_falls_back_to_the_raw_value(): void
    {
        $rules = [['id' => 't', 'kind' => 'kind_invented_next_year', 'column' => 'some_column', 'tag' => 'x']];

        $before = (new BridgeRecordAccessor(['some_column' => 'alpha'], []))->inputsFor($rules);
        $after = (new BridgeRecordAccessor(['some_column' => 'beta'], []))->inputsFor($rules);

        $this->assertArrayHasKey('column:some_column#raw', $before);
        $this->assertNotSame(
            SmartTagVersion::structuredInputsHash($before),
            SmartTagVersion::structuredInputsHash($after),
            'A change to a field read by an unknown kind did not change the hash.'
        );
    }

    /** @test */
    public function the_property_type_still_participates_in_the_hash(): void
    {
        $this->assertNotSame(
            $this->hash(['waterfront_yn' => true, 'property_type' => 'Residential']),
            $this->hash(['waterfront_yn' => true, 'property_type' => 'Income']),
            'The property type stopped affecting the structured hash.'
        );
    }

    /**
     * Every kind the LIVE Bridge rules use has a reading, so none of them falls
     * back to raw values in production.
     *
     * This is the test that fails if a new kind is added to the rule engine and
     * the readings map is not updated.
     *
     * @test
     */
    public function every_live_bridge_rule_kind_has_an_interpreted_reading(): void
    {
        $accessor = new BridgeRecordAccessor([], []);
        $rules = SmartTagSourceRules::bridgeRules();

        $this->assertNotEmpty($rules, 'No Bridge rules were loaded — the test would prove nothing.');

        $inputs = $accessor->inputsFor($rules);
        $raw = array_filter(array_keys($inputs), static fn (string $k) => str_ends_with($k, '#raw'));

        $this->assertSame([], array_values($raw),
            'A live Bridge rule kind has no entry in BridgeRecordAccessor::READINGS and fell back to the raw value.');
    }

    /**
     * The whole live rule set, hashed from a write-shaped record and a read-shaped
     * one. This is the end-to-end form of the reported defect.
     *
     * @test
     */
    public function the_whole_live_rule_set_hashes_identically_from_either_provenance(): void
    {
        $rules = SmartTagSourceRules::bridgeRules();

        // Every boolean column as a just-written model holds it...
        $written = [
            'property_type' => 'Residential',
            'waterfront_yn' => true, 'pool_private_yn' => true, 'garage_yn' => false,
            'association_yn' => true, 'new_construction_yn' => false, 'view_yn' => true,
            'water_view_yn' => false, 'cdd_yn' => true, 'senior_community_yn' => false,
        ];

        // ...and as a re-read row holds it.
        $reRead = [
            'property_type' => 'Residential',
            'waterfront_yn' => 1, 'pool_private_yn' => 1, 'garage_yn' => 0,
            'association_yn' => 1, 'new_construction_yn' => 0, 'view_yn' => 1,
            'water_view_yn' => 0, 'cdd_yn' => 1, 'senior_community_yn' => 0,
        ];

        $raw = ['PropertyType' => 'Residential'];

        $this->assertSame(
            SmartTagVersion::structuredInputsHash((new BridgeRecordAccessor($written, $raw))->inputsFor($rules)),
            SmartTagVersion::structuredInputsHash((new BridgeRecordAccessor($reRead, $raw))->inputsFor($rules)),
            'The live Bridge rule set still hashes differently by provenance.'
        );
    }

    /**
     * Sanity: the hash is still SENSITIVE. A fix that made everything equal would
     * pass every test above and destroy change detection entirely.
     *
     * @test
     */
    public function the_hash_still_changes_when_a_read_field_actually_changes(): void
    {
        $rules = SmartTagSourceRules::bridgeRules();

        $base = ['property_type' => 'Residential', 'waterfront_yn' => true];
        $moved = ['property_type' => 'Residential', 'waterfront_yn' => false];

        $this->assertNotSame(
            SmartTagVersion::structuredInputsHash((new BridgeRecordAccessor($base, []))->inputsFor($rules)),
            SmartTagVersion::structuredInputsHash((new BridgeRecordAccessor($moved, []))->inputsFor($rules)),
            'A real change to a rule-read field no longer changes the hash.'
        );
    }
}
