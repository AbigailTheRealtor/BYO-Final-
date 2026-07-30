<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\LocationDnaDocument;
use App\Services\LocationDna\Contract\LocationDnaSerializer;
use PHPUnit\Framework\TestCase;

/**
 * G1c — LocationDnaSerializer: the PRIVATE canonical document (D-G1-1).
 *
 * Serialisation is not an exposure boundary. Geometry and location_notes are retained in
 * full; PublicGeometryProjection remains the separate redaction mechanism and is unchanged.
 */
class LocationDnaSerializerTest extends TestCase
{
    private LocationDnaSerializer $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = new LocationDnaSerializer();
    }

    private function doc(): LocationDnaDocument
    {
        return LocationDnaDocument::fromCanonical([
            'cities'         => ['Tampa'],
            'counties'       => [],
            'polygons'       => [['label' => 'A', 'path' => [['lat' => 27.9, 'lng' => -82.4]]]],
            'location_notes' => 'Private note.',
        ]);
    }

    // ── shape ────────────────────────────────────────────────────────────────

    public function test_omission_and_canonical_empty_are_distinguishable_in_the_output(): void
    {
        $out = $this->s->toArray($this->doc());

        // `counties` was cleared: present as the canonical empty.
        $this->assertArrayHasKey('counties', $out);
        $this->assertSame([], $out['counties']);

        // `state` was never authored: OMITTED entirely. This is the §5.2 capability.
        $this->assertArrayNotHasKey('state', $out);
        $this->assertArrayNotHasKey('zip_codes', $out);
    }

    public function test_schema_version_is_stamped(): void
    {
        $this->assertSame(2, $this->s->toArray($this->doc())['schema_version']);
    }

    public function test_output_key_order_is_deterministic(): void
    {
        $a = LocationDnaDocument::fromCanonical(['state' => 'FL', 'cities' => ['Tampa']]);
        $b = LocationDnaDocument::fromCanonical(['cities' => ['Tampa'], 'state' => 'FL']);

        $this->assertSame($this->s->toArray($a), $this->s->toArray($b));
        $this->assertSame($this->s->toJson($a), $this->s->toJson($b), 'JSON is structure-stable');
    }

    public function test_repeated_serialisation_is_stable(): void
    {
        $doc = $this->doc();

        $this->assertSame($this->s->toJson($doc), $this->s->toJson($doc));
    }

    // ── private data is retained ─────────────────────────────────────────────

    public function test_geometry_is_retained_in_the_private_document(): void
    {
        $json = $this->s->toJson($this->doc());

        $this->assertStringContainsString('27.9', $json);
        $this->assertStringContainsString('-82.4', $json);
        $this->assertStringContainsString('"polygons"', $json);
    }

    public function test_location_notes_is_retained_in_the_private_document(): void
    {
        $this->assertStringContainsString('Private note.', $this->s->toJson($this->doc()));
    }

    public function test_serialisation_applies_no_public_projection(): void
    {
        $out = $this->s->toArray($this->doc());

        // No projection marker, and geometry intact — this is not the exposure boundary.
        $this->assertArrayNotHasKey('__public_view_projection', $out);
        $this->assertArrayNotHasKey('__withheld_search_geometry', $out);
        $this->assertNotSame([], $out['polygons']);
    }

    public function test_unicode_survives_serialisation_legibly(): void
    {
        $doc  = LocationDnaDocument::fromCanonical(['cities' => ['東京'], 'location_notes' => 'emoji 🏖']);
        $json = $this->s->toJson($doc);

        $this->assertStringContainsString('東京', $json);
        $this->assertStringContainsString('🏖', $json);
    }

    // ── extensions round trip ────────────────────────────────────────────────

    public function test_extensions_round_trip_without_interpretation(): void
    {
        $doc = LocationDnaDocument::fromCanonical(
            ['cities' => ['Tampa']],
            ['neighborhoods' => ['Old Northeast'], 'future_thing' => ['a' => 1]],
        );

        $out = $this->s->toArray($doc);

        $this->assertSame(['Old Northeast'], $out['neighborhoods']);
        $this->assertSame(['a' => 1], $out['future_thing']);
    }

    public function test_lazy_upgrade_output_stamps_the_version_without_changing_values(): void
    {
        $legacy = LocationDnaDocument::fromCanonical(
            ['cities' => ['Tampa']],
            [],
            \App\Services\LocationDna\Contract\InterpretationMode::Legacy,
            null,
        );

        $out = $this->s->toArrayForLazyUpgrade($legacy);

        $this->assertSame(2, $out['schema_version']);
        $this->assertSame(['Tampa'], $out['cities']);
        $this->assertNull($legacy->schemaVersion(), 'the source document is not mutated');
    }

    public function test_an_all_cleared_document_serialises_as_present_empties(): void
    {
        $doc = LocationDnaDocument::emptyDocument()
            ->withClearedDimension(Dimension::Cities)
            ->withClearedDimension(Dimension::Polygons)
            ->withClearedDimension(Dimension::State);

        $out = $this->s->toArray($doc);

        $this->assertSame([], $out['cities']);
        $this->assertSame([], $out['polygons']);
        $this->assertSame('', $out['state']);
    }
}
