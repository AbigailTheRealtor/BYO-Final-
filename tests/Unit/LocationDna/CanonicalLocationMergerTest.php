<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\Providers\CanonicalField;
use App\Services\LocationDna\Providers\CanonicalLocationMerger;
use PHPUnit\Framework\TestCase;

/**
 * Validates the canonical precedence / merge / contradiction contract (Stage B).
 */
class CanonicalLocationMergerTest extends TestCase
{
    private CanonicalLocationMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merger = new CanonicalLocationMerger();
    }

    public function test_base_wins_over_overlay_and_fallback(): void
    {
        $field = $this->merger->merge([
            ['value' => 'B', 'source' => 'osm_overpass',  'role' => 'base',     'confidence' => 0.5],
            ['value' => 'O', 'source' => 'google_places', 'role' => 'overlay',  'confidence' => 0.9],
            ['value' => 'F', 'source' => 'geoapify',      'role' => 'fallback', 'confidence' => 0.9],
        ]);

        // 'value' is scalar so no attribute merge; base wins the value.
        $this->assertSame('B', $field->value);
        // >1 contributor → source is reported as merged with full provenance trail.
        $this->assertSame(CanonicalField::METHOD_MERGED, $field->source);
        $this->assertSame('osm_overpass', $field->provenance['provider']);
        $this->assertEqualsCanonicalizing(
            ['osm_overpass', 'google_places', 'geoapify'],
            $field->provenance['contributors']
        );
    }

    public function test_authoritative_source_outranks_base(): void
    {
        $field = $this->merger->merge(
            [
                ['value' => 'AE', 'source' => 'fema',         'role' => 'fallback', 'confidence' => 0.95],
                ['value' => 'X',  'source' => 'osm_overpass', 'role' => 'base',     'confidence' => 0.5],
            ],
            ['authoritative' => ['fema']]
        );

        $this->assertSame('AE', $field->value);
        $this->assertSame('fema', $field->provenance['provider']);
    }

    public function test_human_corroborated_beats_authoritative(): void
    {
        $field = $this->merger->merge(
            [
                ['value' => 'FEMA-AE',  'source' => 'fema',  'role' => 'base', 'confidence' => 0.95],
                ['value' => 'HUMAN-VE', 'source' => 'agent', 'role' => 'base', 'confidence' => 0.1, 'human_corroborated' => true],
            ],
            ['authoritative' => ['fema']]
        );

        $this->assertSame('HUMAN-VE', $field->value);
        $this->assertTrue($field->humanCorroborated);
    }

    public function test_overlay_enriches_missing_attributes_without_clobbering(): void
    {
        // The signature OSM(base geometry) + Google(overlay rating) case.
        $field = $this->merger->merge([
            [
                'value'  => ['name' => 'Riverside Park', 'lat' => 27.9, 'lng' => -82.4, 'rating' => null],
                'source' => 'osm_overpass', 'role' => 'base', 'confidence' => 0.5, 'license' => 'odbl',
            ],
            [
                'value'  => ['name' => 'Riverside Park (Google)', 'rating' => 4.6, 'review_count' => 812],
                'source' => 'google_places', 'role' => 'overlay', 'confidence' => 0.8, 'license' => 'google-tos',
            ],
        ]);

        // Base keeps its own present values (name, geometry); overlay fills the null rating + new key.
        $this->assertSame('Riverside Park', $field->value['name']);
        $this->assertSame(27.9, $field->value['lat']);
        $this->assertSame(4.6, $field->value['rating']);        // filled from overlay (base had null)
        $this->assertSame(812, $field->value['review_count']);  // added from overlay
        $this->assertSame(CanonicalField::METHOD_MERGED, $field->source);
        // Winner confidence (base) is retained.
        $this->assertSame(0.5, $field->confidence);
    }

    public function test_all_null_contributions_yield_explicit_unknown(): void
    {
        $field = $this->merger->merge([
            ['value' => null, 'source' => 'osm_overpass', 'role' => 'base'],
            ['value' => null, 'source' => 'geoapify',     'role' => 'fallback'],
        ]);

        $this->assertNull($field->value);        // unknown, never fabricated
        $this->assertSame('none', $field->source);
        $this->assertNull($field->confidence);
        // Contributors still recorded for audit.
        $this->assertEqualsCanonicalizing(['osm_overpass', 'geoapify'], $field->provenance['contributors']);
    }

    public function test_detects_scalar_contradiction_beyond_tolerance(): void
    {
        $field = $this->merger->merge(
            [
                ['value' => 'Hillsborough County School District', 'source' => 'census_tiger', 'role' => 'base'],
                ['value' => 'Pinellas County School District',     'source' => 'osm_overpass', 'role' => 'fallback'],
            ]
        );

        $this->assertCount(1, $field->contradictions);
        $this->assertSame('census_tiger', $field->contradictions[0]['winner_source']);
        $this->assertSame('osm_overpass', $field->contradictions[0]['other_source']);
    }

    public function test_numeric_tolerance_suppresses_trivial_disagreement(): void
    {
        $field = $this->merger->merge(
            [
                ['value' => 1.20, 'source' => 'osm_overpass',  'role' => 'base'],
                ['value' => 1.23, 'source' => 'google_places', 'role' => 'overlay'],
            ],
            ['numeric_tolerance' => 0.05]
        );

        $this->assertSame([], $field->contradictions); // 0.03 <= 0.05 tolerance
    }

    public function test_single_contributor_is_not_labeled_merged(): void
    {
        $field = $this->merger->merge([
            ['value' => 'solo', 'source' => 'google_places', 'role' => 'base', 'confidence' => 0.7, 'method' => CanonicalField::METHOD_API],
        ]);

        $this->assertSame('google_places', $field->source);
        $this->assertSame(CanonicalField::METHOD_API, $field->provenance['method']);
    }
}
