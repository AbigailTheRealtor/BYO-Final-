<?php

namespace Tests\Feature\Location;

use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenance;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * G4 makes coordinate provenance storable.
 *
 * The claim worth proving is the round trip. A precision written as a string
 * and read back as an unrecognised one degrades silently to Unknown — coarse —
 * so a stored rooftop fix would stop being usable for Location DNA without
 * anything appearing to fail. "Both sides are strings" is not evidence that
 * they agree.
 */
class CoordinateProvenanceTest extends TestCase
{
    use DatabaseTransactions;

    private function resolved(
        CoordinatePrecision $precision = CoordinatePrecision::Interpolated
    ): PropertyCoordinateResult {
        return PropertyCoordinateResult::resolved(
            latitude:          27.948434712759,
            longitude:         -82.458094358643,
            precision:         $precision,
            source:            CoordinateSource::Geocoder,
            provider:          'us_census',
            normalizedAddress: '315 madison st tampa fl 33602',
        );
    }

    // ── schema ──────────────────────────────────────────────────────────────

    public function test_the_provenance_columns_exist(): void
    {
        foreach (['geocode_precision', 'geocode_provider', 'normalized_address'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('property_location_dna', $column),
                "property_location_dna.{$column} must exist"
            );
        }
    }

    public function test_the_existing_geocode_columns_are_untouched(): void
    {
        // The migration is additive. Nothing production already depends on may
        // have been renamed or dropped.
        foreach ([
            'geocoded_lat', 'geocoded_lng', 'geocode_source',
            'geocode_status', 'geocode_error', 'geocoded_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('property_location_dna', $column),
                "property_location_dna.{$column} must survive the migration"
            );
        }
    }

    // ── mapping ─────────────────────────────────────────────────────────────

    public function test_a_resolved_result_maps_to_its_columns(): void
    {
        $columns = CoordinateProvenance::columnsFor($this->resolved());

        $this->assertSame('interpolated', $columns['geocode_precision']);
        $this->assertSame('us_census', $columns['geocode_provider']);
        $this->assertSame('315 madison st tampa fl 33602', $columns['normalized_address']);
    }

    public function test_an_unresolved_result_records_no_provenance(): void
    {
        $columns = CoordinateProvenance::columnsFor(
            PropertyCoordinateResult::unresolved('census_no_match', '999 nowhere rd tampa fl 33602')
        );

        $this->assertNull($columns['geocode_precision']);
        $this->assertNull($columns['geocode_provider']);
        $this->assertSame(
            '999 nowhere rd tampa fl 33602',
            $columns['normalized_address'],
            'Which line failed to resolve is what makes a miss diagnosable'
        );
    }

    // ── the round trip ──────────────────────────────────────────────────────

    /**
     * @dataProvider everyPrecision
     */
    public function test_every_precision_survives_a_database_round_trip(string $case): void
    {
        $precision = CoordinatePrecision::from($case);

        $row = PropertyLocationDna::create(array_merge([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 991001,
            'geocoded_lat'   => 27.948434712759,
            'geocoded_lng'   => -82.458094358643,
            'geocode_status' => 'geocoded',
        ], CoordinateProvenance::columnsFor($this->resolved($precision))));

        $stored = PropertyLocationDna::find($row->id);

        $this->assertSame($precision->value, $stored->geocode_precision);
        $this->assertSame(
            $precision,
            CoordinateProvenance::precisionFrom($stored->geocode_precision),
            'A tier that does not survive storage silently becomes Unknown'
        );
        $this->assertSame(
            $precision->isExact(),
            CoordinateProvenance::storedIsUsableForLocationDna($stored->geocode_precision),
            'The stored row must answer the DNA gate the same way the result did'
        );
    }

    public static function everyPrecision(): array
    {
        return array_map(
            static fn (CoordinatePrecision $p): array => [$p->value],
            array_combine(
                array_map(static fn (CoordinatePrecision $p): string => $p->value, CoordinatePrecision::cases()),
                CoordinatePrecision::cases()
            )
        );
    }

    // ── failing safe ────────────────────────────────────────────────────────

    /**
     * @dataProvider unreadableValues
     */
    public function test_an_unreadable_precision_is_coarse(?string $stored): void
    {
        // A row whose quality cannot be established must not be measured from.
        $this->assertSame(CoordinatePrecision::Unknown, CoordinateProvenance::precisionFrom($stored));
        $this->assertFalse(CoordinateProvenance::storedIsUsableForLocationDna($stored));
    }

    public static function unreadableValues(): array
    {
        return [
            'null'          => [null],
            'empty'         => [''],
            'legacy value'  => ['saved_meta'],
            'typo'          => ['rooftopp'],
            'future tier'   => ['survey_grade'],
        ];
    }

    public function test_a_row_written_before_g4_reads_as_unknown_rather_than_exact(): void
    {
        // Existing rows are not backfilled. NULL means "provenance unknown",
        // which must not be mistaken for a coordinate known to be good.
        $row = PropertyLocationDna::create([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 991002,
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
        ]);

        $this->assertNull($row->fresh()->geocode_precision);
        $this->assertFalse(
            CoordinateProvenance::storedIsUsableForLocationDna($row->fresh()->geocode_precision),
            'A pre-G4 coordinate of unknown quality must not pass the DNA gate'
        );
    }
}
