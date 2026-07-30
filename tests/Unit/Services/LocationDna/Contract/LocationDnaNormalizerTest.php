<?php

namespace Tests\Unit\Services\LocationDna\Contract;

use App\Services\LocationDna\Contract\ContractViolation;
use App\Services\LocationDna\Contract\Dimension;
use App\Services\LocationDna\Contract\LocationDnaContractException;
use App\Services\LocationDna\Contract\LocationDnaNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * G1c — LocationDnaNormalizer (D-G1-1, D-G1-3).
 *
 * The load-bearing asymmetry under test: polygon VERTEX order is preserved because it is
 * semantically meaningful, while polygon and radius COLLECTION order is normalised because
 * D-G1-3 approved it as not meaningful. G1b proved the Bridge hash erases vertex order
 * (test_reordered_polygon_path_coordinates_do_not_change_the_hash); this layer must not.
 */
class LocationDnaNormalizerTest extends TestCase
{
    private LocationDnaNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new LocationDnaNormalizer();
    }

    // ── administrative labels ────────────────────────────────────────────────

    public function test_labels_are_trimmed_blank_filtered_deduplicated_and_ordered(): void
    {
        $out = $this->n->normalize(Dimension::Cities, ['  Tampa  ', 'Orlando', 'Tampa', '', '   ']);

        $this->assertSame(['Orlando', 'Tampa'], $out, 'trimmed, de-duplicated, deterministically ordered');
    }

    public function test_label_ordering_is_input_order_independent(): void
    {
        $a = $this->n->normalize(Dimension::Counties, ['Pinellas', 'Hillsborough']);
        $b = $this->n->normalize(Dimension::Counties, ['Hillsborough', 'Pinellas']);

        $this->assertSame($a, $b);
    }

    public function test_deduplication_is_case_sensitive_on_purpose(): void
    {
        // Collapsing "Tampa" and "tampa" would be a data decision this layer is not entitled
        // to make — they are not provably the same published place name.
        $this->assertSame(['Tampa', 'tampa'], $this->n->normalize(Dimension::Cities, ['Tampa', 'tampa']));
    }

    public function test_non_string_label_entry_is_rejected(): void
    {
        try {
            $this->n->normalize(Dimension::Cities, ['Tampa', 42]);
            $this->fail('a non-string entry must be rejected');
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::InvalidDimensionValue, $e->violation());
        }
    }

    public function test_non_array_label_value_is_rejected(): void
    {
        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::Cities, 'Tampa');
    }

    // ── text ─────────────────────────────────────────────────────────────────

    public function test_location_notes_is_trimmed_but_never_truncated_or_sanitised(): void
    {
        $notes = "  Near the water, walkable.\nSecond floor preferred — 東京 🏖  ";

        $this->assertSame(
            "Near the water, walkable.\nSecond floor preferred — 東京 🏖",
            $this->n->normalize(Dimension::LocationNotes, $notes),
        );
    }

    public function test_whitespace_only_text_normalises_to_the_canonical_empty(): void
    {
        // Allowed at this layer: a stored document may legitimately already be cleared. The
        // prohibition on '' silently meaning clear is enforced at the command boundary.
        $this->assertSame('', $this->n->normalize(Dimension::State, '   '));
    }

    public function test_non_string_text_is_rejected(): void
    {
        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::State, ['FL']);
    }

    public function test_flag_must_be_boolean(): void
    {
        $this->assertTrue($this->n->normalize(Dimension::FlexibleLocation, true));

        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::FlexibleLocation, 'yes');
    }

    // ── geometry: vertex order preserved ─────────────────────────────────────

    public function test_polygon_vertex_order_is_preserved_never_sorted(): void
    {
        $path = [
            ['lat' => 27.99, 'lng' => -82.49],
            ['lat' => 27.90, 'lng' => -82.40],
            ['lat' => 27.95, 'lng' => -82.45],
        ];

        $out = $this->n->normalize(Dimension::Polygons, [['label' => 'A', 'path' => $path]]);

        $this->assertSame(
            [27.99, 27.90, 27.95],
            array_column($out[0]['path'], 'lat'),
            'vertex order is semantically meaningful and must survive verbatim',
        );
    }

    public function test_polygon_collection_order_is_normalised_deterministically(): void
    {
        $a = ['label' => 'North', 'path' => [['lat' => 28.0, 'lng' => -82.0]]];
        $b = ['label' => 'South', 'path' => [['lat' => 27.0, 'lng' => -82.0]]];

        $this->assertSame(
            $this->n->normalize(Dimension::Polygons, [$a, $b]),
            $this->n->normalize(Dimension::Polygons, [$b, $a]),
            'collection order carries no meaning and is normalised',
        );
    }

    public function test_radius_collection_order_is_normalised_deterministically(): void
    {
        $a = ['lat' => 27.1, 'lng' => -82.1, 'radius_miles' => 1.0];
        $b = ['lat' => 28.2, 'lng' => -83.2, 'radius_miles' => 2.0];

        $this->assertSame(
            $this->n->normalize(Dimension::RadiusSearches, [$a, $b]),
            $this->n->normalize(Dimension::RadiusSearches, [$b, $a]),
        );
    }

    public function test_coordinates_and_radius_are_normalised_to_floats(): void
    {
        $out = $this->n->normalize(Dimension::RadiusSearches, [
            ['lat' => '27.9', 'lng' => '-82.4', 'radius_miles' => '3.5', 'address' => '  1 Main St  '],
        ]);

        $this->assertSame(27.9, $out[0]['lat']);
        $this->assertSame(-82.4, $out[0]['lng']);
        $this->assertSame(3.5, $out[0]['radius_miles']);
        $this->assertSame('1 Main St', $out[0]['address']);
    }

    // ── geometry: malformed is rejected, not accepted because it is JSON-able ─

    public function test_path_less_polygon_is_rejected(): void
    {
        foreach ([[['label' => 'no path']], [['label' => 'x', 'path' => []]]] as $bad) {
            try {
                $this->n->normalize(Dimension::Polygons, $bad);
                $this->fail('a path-less polygon must be rejected');
            } catch (LocationDnaContractException $e) {
                $this->assertSame(ContractViolation::InvalidGeometry, $e->violation());
            }
        }
    }

    public function test_centre_less_radius_entry_is_rejected(): void
    {
        try {
            $this->n->normalize(Dimension::RadiusSearches, [['radius_miles' => 5]]);
            $this->fail('a centre-less radius entry must be rejected');
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::InvalidGeometry, $e->violation());
        }
    }

    public function test_radius_entry_without_radius_miles_is_rejected(): void
    {
        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::RadiusSearches, [['lat' => 27.9, 'lng' => -82.4]]);
    }

    public function test_non_positive_radius_is_rejected(): void
    {
        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::RadiusSearches, [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 0]]);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::Polygons, [['path' => [['lat' => 200.0, 'lng' => 0.0]]]]);
    }

    public function test_non_numeric_coordinate_is_rejected(): void
    {
        $this->expectException(LocationDnaContractException::class);
        $this->n->normalize(Dimension::Polygons, [['path' => [['lat' => 'north', 'lng' => 0.0]]]]);
    }

    // ── nulls and purity ─────────────────────────────────────────────────────

    public function test_null_is_never_normalised_into_a_value(): void
    {
        try {
            $this->n->normalize(Dimension::Cities, null);
            $this->fail('null must be rejected');
        } catch (LocationDnaContractException $e) {
            $this->assertSame(ContractViolation::AuthoredNull, $e->violation());
        }
    }

    public function test_normalisation_does_not_mutate_its_input(): void
    {
        $input = [
            ['label' => ' A ', 'path' => [['lat' => '27.9', 'lng' => '-82.4']]],
        ];
        $before = $input;

        $this->n->normalize(Dimension::Polygons, $input);

        $this->assertSame($before, $input);
    }

    public function test_normalize_all_ignores_unknown_keys_and_is_deterministic(): void
    {
        $out = $this->n->normalizeAll([
            'state'         => ' FL ',
            'cities'        => [' Tampa '],
            'neighborhoods' => ['Old Northeast'],
        ]);

        $this->assertSame(['cities', 'state'], array_keys($out));
        $this->assertSame('FL', $out['state']);
        $this->assertArrayNotHasKey('neighborhoods', $out, 'withdrawn keys are not normalised as dimensions');
    }

    public function test_metres_per_mile_constant_matches_the_measured_contract_value(): void
    {
        $this->assertSame(1609.34, LocationDnaNormalizer::METRES_PER_MILE);
    }
}
