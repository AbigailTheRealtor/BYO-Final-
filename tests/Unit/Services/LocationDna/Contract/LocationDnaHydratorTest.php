<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\HydrationOutcome;
use App\Services\LocationDna\Contract\InterpretationMode;
use App\Services\LocationDna\Contract\LocationDnaHydrator;
use App\Services\LocationDna\Contract\MalformedDocumentException;
use App\Services\LocationDna\Contract\UnsupportedSchemaVersionException;
use PHPUnit\Framework\TestCase;

/**
 * G1c — LocationDnaHydrator: the single version-aware boundary (D-G1-1).
 *
 * The behaviour this suite exists to lock down is that FAILURE IS NOT AN EMPTY DOCUMENT.
 * G1a proved the live code silently converts a corrupt blob into an empty record
 * (test_s3_corrupt_blob_is_silently_treated_as_an_empty_record); every such input here
 * produces a distinct, named outcome instead.
 */
class LocationDnaHydratorTest extends TestCase
{
    private LocationDnaHydrator $hydrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hydrator = new LocationDnaHydrator();
    }

    // ── absent ───────────────────────────────────────────────────────────────

    public function test_null_input_is_absent_not_empty(): void
    {
        $r = $this->hydrator->hydrate(null);

        $this->assertSame(HydrationOutcome::Absent, $r->outcome);
        $this->assertNull($r->document());
        $this->assertFalse($r->isHydrated());
    }

    public function test_boolean_false_is_absent(): void
    {
        // This is the exact value G1a pinned: `info()` returns boolean false for an unwritten
        // meta key, and `?? ''` does not catch it.
        $this->assertTrue($this->hydrator->hydrate(false)->isAbsent());
    }

    public function test_empty_string_is_absent(): void
    {
        $this->assertTrue($this->hydrator->hydrate('')->isAbsent());
    }

    // ── malformed ────────────────────────────────────────────────────────────

    public function test_truncated_json_is_malformed_and_quarantines_the_raw_input(): void
    {
        $raw = '{"cities": ["Tampa"';
        $r   = $this->hydrator->hydrate($raw);

        $this->assertSame(HydrationOutcome::Malformed, $r->outcome);
        $this->assertNull($r->document());
        $this->assertSame($raw, $r->rawInput(), 'the original bytes are retained, not discarded');
        $this->assertTrue($r->outcome->forbidsWrite());
    }

    public function test_decoded_scalar_is_malformed_not_an_empty_document(): void
    {
        // json_decode("0") is integer 0 — valid JSON, and the specific hazard G1a recorded.
        foreach (['0', '"a string"', 'true', '3.5'] as $json) {
            $r = $this->hydrator->hydrate($json);
            $this->assertTrue($r->isMalformed(), "input {$json} must be malformed");
        }
    }

    public function test_json_list_is_malformed(): void
    {
        $this->assertTrue($this->hydrator->hydrate('["Tampa"]')->isMalformed());
    }

    public function test_non_array_non_string_input_is_malformed(): void
    {
        $this->assertTrue($this->hydrator->hydrate(42)->isMalformed());
        $this->assertTrue($this->hydrator->hydrate(3.5)->isMalformed());
        $this->assertTrue($this->hydrator->hydrate(true)->isMalformed());
    }

    public function test_a_malformed_known_dimension_quarantines_the_whole_document(): void
    {
        // A path-less polygon must not be silently dropped, leaving a partial truth.
        $r = $this->hydrator->hydrate(['polygons' => [['label' => 'no path']], 'cities' => ['Tampa']]);

        $this->assertTrue($r->isMalformed());
        $this->assertStringContainsString('polygons', (string) $r->reason);
        $this->assertNull($r->document(), 'no partial document is produced');
    }

    public function test_malformed_schema_version_is_malformed(): void
    {
        $this->assertTrue($this->hydrator->hydrate(['schema_version' => 'two'])->isMalformed());
        $this->assertTrue($this->hydrator->hydrate(['schema_version' => 0])->isMalformed());
    }

    // ── unsupported higher version ───────────────────────────────────────────

    public function test_higher_version_is_refused_and_not_rewritten(): void
    {
        $r = $this->hydrator->hydrate(['schema_version' => 99, 'cities' => ['Tampa']]);

        $this->assertSame(HydrationOutcome::UnsupportedVersion, $r->outcome);
        $this->assertNull($r->document(), 'a newer record yields no interpretable document');
        $this->assertSame(99, $r->foundSchemaVersion);
        $this->assertTrue($r->outcome->forbidsWrite(), 'read-only: it must not be rewritten');
    }

    public function test_documentOrFail_throws_the_precise_domain_exception(): void
    {
        $this->expectException(UnsupportedSchemaVersionException::class);
        $this->hydrator->hydrate(['schema_version' => 99])->documentOrFail();
    }

    public function test_documentOrFail_throws_malformed_for_corrupt_input(): void
    {
        $this->expectException(MalformedDocumentException::class);
        $this->hydrator->hydrate('{"cities": ["Tampa"')->documentOrFail();
    }

    // ── valid documents and interpretation mode ──────────────────────────────

    public function test_valid_current_version_document_hydrates_in_canonical_mode(): void
    {
        $r = $this->hydrator->hydrate(['schema_version' => 2, 'cities' => ['Tampa'], 'state' => 'FL']);

        $this->assertTrue($r->isHydrated());
        $doc = $r->documentOrFail();

        $this->assertSame(InterpretationMode::Canonical, $doc->interpretationMode());
        $this->assertSame(2, $doc->schemaVersion());
        $this->assertSame(['Tampa'], $doc->value(Dimension::Cities));
    }

    public function test_missing_schema_version_hydrates_in_legacy_mode(): void
    {
        $doc = $this->hydrator->hydrate(['cities' => ['Tampa']])->documentOrFail();

        $this->assertSame(InterpretationMode::Legacy, $doc->interpretationMode());
        $this->assertNull($doc->schemaVersion());
        $this->assertTrue(
            $doc->interpretationMode()->allowsLegacyFallback(),
            '§5.4 S1: a missing key is indeterminate, so fallback may apply',
        );
    }

    public function test_version_one_is_read_with_legacy_interpretation(): void
    {
        $doc = $this->hydrator->hydrate(['schema_version' => 1, 'cities' => ['Tampa']])->documentOrFail();

        $this->assertSame(InterpretationMode::Legacy, $doc->interpretationMode());
        $this->assertSame(1, $doc->schemaVersion());
    }

    public function test_lazy_upgrade_changes_no_interpreted_values(): void
    {
        $legacy   = $this->hydrator->hydrate(['cities' => ['Tampa'], 'counties' => []])->documentOrFail();
        $upgraded = $legacy->withLazyUpgrade();

        $this->assertSame($legacy->toDimensionArray(), $upgraded->toDimensionArray(), 'no value changes');
        $this->assertSame(InterpretationMode::Canonical, $upgraded->interpretationMode());
        $this->assertSame(2, $upgraded->schemaVersion());
    }

    // ── present-but-cleared survives ─────────────────────────────────────────

    public function test_explicit_canonical_empty_collections_remain_authoritative(): void
    {
        $doc = $this->hydrator->hydrate([
            'schema_version' => 2,
            'cities'         => [],
            'polygons'       => [],
            'state'          => '',
        ])->documentOrFail();

        $this->assertTrue($doc->isCleared(Dimension::Cities));
        $this->assertTrue($doc->isCleared(Dimension::Polygons));
        $this->assertTrue($doc->isCleared(Dimension::State));
        $this->assertFalse($doc->isAbsent(Dimension::Cities), 'cleared is present, not absent');
    }

    public function test_the_hydrator_never_merges_a_legacy_mirror(): void
    {
        // A blob with cleared cities. The live trait would consult the discrete mirror here and
        // resurrect. The hydrator has no mirror concept at all — its only input is the blob.
        $doc = $this->hydrator->hydrate(['schema_version' => 2, 'cities' => []])->documentOrFail();

        $this->assertSame([], $doc->value(Dimension::Cities), 'no mirror value can appear');
        $this->assertTrue($doc->isCleared(Dimension::Cities));
    }

    // ── null members and extensions ──────────────────────────────────────────

    public function test_a_null_dimension_value_does_not_become_present(): void
    {
        $doc = $this->hydrator->hydrate(['schema_version' => 2, 'cities' => null])->documentOrFail();

        $this->assertTrue($doc->isAbsent(Dimension::Cities), 'null means not supplied, not cleared');
        $this->assertFalse($doc->isCleared(Dimension::Cities));
    }

    public function test_unknown_and_withdrawn_keys_are_retained_uninterpreted(): void
    {
        $doc = $this->hydrator->hydrate([
            'schema_version' => 2,
            'cities'         => ['Tampa'],
            'neighborhoods'  => ['Old Northeast'],
            'future_thing'   => ['a' => 1],
        ])->documentOrFail();

        $this->assertSame(['Old Northeast'], $doc->extensions()['neighborhoods']);
        $this->assertSame(['a' => 1], $doc->extensions()['future_thing']);
        $this->assertSame(['cities'], $doc->presenceSet(), 'neither became a canonical dimension');
    }

    // ── no input mutation ────────────────────────────────────────────────────

    public function test_the_input_array_is_not_mutated(): void
    {
        $input = [
            'schema_version' => 2,
            'cities'         => ['  Tampa  ', 'Tampa'],
            'polygons'       => [['label' => ' A ', 'path' => [['lat' => '27.9', 'lng' => '-82.4']]]],
        ];
        $before = $input;

        $this->hydrator->hydrate($input);

        $this->assertSame($before, $input, 'hydration must not mutate the supplied input');
    }

    public function test_hydration_is_deterministic(): void
    {
        $a = $this->hydrator->hydrate(['state' => 'FL', 'cities' => ['Tampa']])->documentOrFail();
        $b = $this->hydrator->hydrate(['cities' => ['Tampa'], 'state' => 'FL'])->documentOrFail();

        $this->assertSame($a->toDimensionArray(), $b->toDimensionArray());
    }
}
