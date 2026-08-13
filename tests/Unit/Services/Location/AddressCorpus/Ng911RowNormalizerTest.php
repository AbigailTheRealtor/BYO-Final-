<?php

namespace Tests\Unit\Services\Location\AddressCorpus;

use App\Services\Location\AddressCorpus\CorpusRejectReason;
use App\Services\Location\AddressCorpus\GeoJsonSourceReader;
use App\Services\Location\AddressCorpus\Ng911\HillsboroughColumnMap;
use App\Services\Location\AddressCorpus\Ng911\Ng911RowNormalizer;
use App\Services\Location\AddressCorpus\Ng911\PinellasColumnMap;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\PropertyAddress;
use Tests\TestCase;

/**
 * The generic NENA normalizer, exercised through two jurisdictions that spell
 * everything differently.
 *
 * The point of every test here is that only the *column map* differs. If a test
 * below needs a county-specific branch in the normalizer to pass, the design has
 * failed and adding the third county will mean writing a third parser.
 */
class Ng911RowNormalizerTest extends TestCase
{
    private function pinellas(): Ng911RowNormalizer
    {
        return new Ng911RowNormalizer(PinellasColumnMap::map());
    }

    private function hillsborough(): Ng911RowNormalizer
    {
        return new Ng911RowNormalizer(HillsboroughColumnMap::map());
    }

    /** A Pinellas-shaped row with the fields that county publishes. */
    private function pinellasRow(array $overrides = []): array
    {
        return array_merge([
            'SITEADDID'         => 'PIN-1',
            'GlobalID'          => '{aaaa-1}',
            'ADDRNUM'           => '315',
            'FULLNAME'          => 'E Madison St',
            'UNITTYPE'          => null,
            'UNITID'            => null,
            'PSTLCITY'          => 'Tampa',
            'PSTLZIP5'          => '33602',
            'STATEABBREVIATION' => 'FL',
            'COUNTY'            => 'Pinellas',
            'POINTTYPE'         => null,
            'STATUS'            => 'Current',
            GeoJsonSourceReader::LATITUDE  => 27.9475,
            GeoJsonSourceReader::LONGITUDE => -82.4570,
        ], $overrides);
    }

    /** A Hillsborough-shaped row — different spellings, no state, no county. */
    private function hillsboroughRow(array $overrides = []): array
    {
        return array_merge([
            'SITEADDID'  => 'HIL-1',
            'GlobalID'   => '{bbbb-1}',
            'ADDRNUM'    => '315',
            'FULLNAME'   => 'E Madison Street',
            'UNITTYPE'   => null,
            'UNITID'     => null,
            'POSTALCOMM' => 'Tampa',
            'ZIP'        => '33602',
            'POINTTYPE'  => 'Location',
            'STATUS'     => 'Current',
            GeoJsonSourceReader::LATITUDE  => 27.9475,
            GeoJsonSourceReader::LONGITUDE => -82.4570,
        ], $overrides);
    }

    private function accept(Ng911RowNormalizer $n, array $row)
    {
        $result = $n->normalize($row, '12');

        $this->assertNull($result['reject'], "Expected acceptance, got [{$result['reject']}]");

        return $result['record'];
    }

    private function rejectReason(Ng911RowNormalizer $n, array $row): ?string
    {
        return $n->normalize($row, '12')['reject'];
    }

    // ── the shared normalization contract ───────────────────────────────────

    public function test_two_jurisdictions_converge_on_one_lookup_line(): void
    {
        // The whole architecture in one assertion. Pinellas writes
        // "E Madison St" with PSTLCITY/PSTLZIP5; Hillsborough writes
        // "E Madison Street" with POSTALCOMM/ZIP and no state column at all.
        // The corpus can only be matched if both become the same string.
        $pinellas     = $this->accept($this->pinellas(), $this->pinellasRow());
        $hillsborough = $this->accept($this->hillsborough(), $this->hillsboroughRow());

        $this->assertSame('315 e madison st tampa fl 33602', $pinellas->normalized());
        $this->assertSame($pinellas->normalized(), $hillsborough->normalized());
    }

    public function test_a_corpus_row_and_a_typed_address_converge(): void
    {
        // The other half of the same property: what a person types must reach
        // the same string, or the corpus answers nothing.
        $typed = new PropertyAddress(
            address: '315 East Madison Street',
            city:    'Tampa',
            state:   'Florida',
            zip:     '33602-1234',
        );

        $record = $this->accept($this->hillsborough(), $this->hillsboroughRow());

        $this->assertSame($typed->coordinateLookupLine(), $record->normalized());
    }

    public function test_there_is_no_second_street_normalizer(): void
    {
        // A suffix word inside the street *name* must survive, exactly as it
        // does for a typed address — this is the position-aware rule, and it
        // lives in PropertyAddress and nowhere else.
        $record = $this->accept($this->hillsborough(), $this->hillsboroughRow([
            'ADDRNUM'  => '4600',
            'FULLNAME' => 'Silver Hill Rd',
        ]));

        $this->assertStringContainsString('4600 silver hill rd', $record->normalized());
        $this->assertStringNotContainsString('silver hl rd', $record->normalized());
    }

    // ── units ───────────────────────────────────────────────────────────────

    public function test_units_share_a_lookup_line_but_not_an_identity(): void
    {
        // Two condos in one building. They must resolve to one coordinate — the
        // lookup line is unit-free by design — while remaining distinct
        // properties, which is what the identity line is for.
        $a = $this->accept($this->hillsborough(), $this->hillsboroughRow([
            'SITEADDID' => 'HIL-A', 'ADDRNUM' => '600', 'FULLNAME' => 'N Ashley Dr',
            'UNITTYPE' => 'Suite', 'UNITID' => '1200',
        ]));
        $b = $this->accept($this->hillsborough(), $this->hillsboroughRow([
            'SITEADDID' => 'HIL-B', 'ADDRNUM' => '600', 'FULLNAME' => 'N Ashley Dr',
            'UNITTYPE' => 'Suite', 'UNITID' => '1400',
        ]));

        $this->assertSame($a->normalized(), $b->normalized());
        $this->assertNotSame($a->identity(), $b->identity());
        $this->assertTrue($a->hasUnit());
    }

    public function test_pinellas_additional_unit_designators_are_preserved(): void
    {
        // Pinellas models up to five. Dropping the extras would collapse
        // distinct condos onto one identity.
        $plain = $this->accept($this->pinellas(), $this->pinellasRow([
            'UNITTYPE' => 'Unit', 'UNITID' => '501',
        ]));
        $extra = $this->accept($this->pinellas(), $this->pinellasRow([
            'UNITTYPE' => 'Unit', 'UNITID' => '501', 'SECONDALTUNITID' => 'B',
        ]));

        $this->assertSame($plain->normalized(), $extra->normalized());
        $this->assertNotSame($plain->identity(), $extra->identity());
    }

    public function test_a_row_without_a_unit_reports_none(): void
    {
        $record = $this->accept($this->pinellas(), $this->pinellasRow());

        $this->assertFalse($record->hasUnit());
        $this->assertSame('none', $record->unitSource);
    }

    // ── status filtering ────────────────────────────────────────────────────

    public function test_a_retired_address_is_rejected(): void
    {
        $this->assertSame(
            CorpusRejectReason::INACTIVE_STATUS,
            $this->rejectReason($this->pinellas(), $this->pinellasRow(['STATUS' => 'Retired']))
        );
    }

    public function test_an_inactive_address_is_rejected(): void
    {
        // Hillsborough spells the same idea differently — configuration, not code.
        $this->assertSame(
            CorpusRejectReason::INACTIVE_STATUS,
            $this->rejectReason($this->hillsborough(), $this->hillsboroughRow(['STATUS' => 'Inactive']))
        );
    }

    /** @dataProvider liveStatuses */
    public function test_live_statuses_are_kept(string $status): void
    {
        $this->assertNull($this->rejectReason($this->pinellas(), $this->pinellasRow(['STATUS' => $status])));
    }

    public static function liveStatuses(): array
    {
        return [['Current'], ['Pending'], ['Temporary'], ['current'], ['CURRENT']];
    }

    public function test_a_blank_status_is_kept_rather_than_assumed_retired(): void
    {
        // The conservative direction: a live address wrongly dropped is
        // invisible; a retired one wrongly kept shows up in the report.
        $this->assertNull($this->rejectReason($this->pinellas(), $this->pinellasRow(['STATUS' => ''])));
    }

    // ── non-address features ────────────────────────────────────────────────

    public function test_infrastructure_is_not_a_property_address(): void
    {
        $this->assertSame(
            CorpusRejectReason::NON_ADDRESS_FEATURE,
            $this->rejectReason($this->pinellas(), $this->pinellasRow(['POINTTYPE' => 'Lift Station']))
        );

        $this->assertSame(
            CorpusRejectReason::NON_ADDRESS_FEATURE,
            $this->rejectReason($this->hillsborough(), $this->hillsboroughRow(['POINTTYPE' => 'Utility']))
        );
    }

    public function test_an_undecidable_placement_is_kept_rather_than_guessed_at(): void
    {
        // `Other` and `Unknown` are not on any exclusion list. Inventing a rule
        // for them would be inventing property semantics the source never stated.
        $this->assertNull($this->rejectReason($this->hillsborough(), $this->hillsboroughRow(['POINTTYPE' => 'Other'])));
        $this->assertNull($this->rejectReason($this->hillsborough(), $this->hillsboroughRow(['POINTTYPE' => 'Unknown'])));
    }

    public function test_placement_exclusion_is_exact_never_substring(): void
    {
        // "Utility Access Point" is not "Utility". Substring matching would drop
        // real addresses on a street that happens to be named for one.
        $this->assertNull($this->rejectReason($this->hillsborough(), $this->hillsboroughRow([
            'POINTTYPE' => 'Utility Access Point',
        ])));
    }

    // ── precision ───────────────────────────────────────────────────────────

    public function test_an_ng911_point_is_never_promoted_to_rooftop(): void
    {
        // Neither county documents placement, so a point attached to an address
        // is not evidence of a roof. Parcel locates the property without
        // claiming one — and still satisfies isExact(), so Location DNA can
        // measure from it honestly.
        $records = [
            'pinellas'     => $this->accept($this->pinellas(), $this->pinellasRow()),
            'hillsborough' => $this->accept($this->hillsborough(), $this->hillsboroughRow()),
        ];

        foreach ($records as $source => $record) {
            $this->assertSame(CoordinatePrecision::Parcel, $record->precision, $source);
            $this->assertNotSame(CoordinatePrecision::Rooftop, $record->precision, $source);
            $this->assertNotSame(CoordinatePrecision::Entrance, $record->precision, $source);

            // Parcel is still exact, so Location DNA may measure from it — the
            // conservative choice does not cost us the pipeline.
            $this->assertTrue($record->precision->isExact(), $source);
        }
    }

    public function test_a_nena_placement_value_is_not_claimed_as_recognised(): void
    {
        // Hillsborough's `Location` is a label the county chose, not a
        // vocabulary this codebase maps to a tier. Reporting it as recognised
        // would imply we had read meaning into it.
        $record = $this->accept($this->hillsborough(), $this->hillsboroughRow());

        $this->assertSame('location', $record->placementLabel);
        $this->assertFalse($record->placementRecognised);
    }

    // ── provenance ──────────────────────────────────────────────────────────

    public function test_the_authoritative_source_id_survives(): void
    {
        $record = $this->accept($this->pinellas(), $this->pinellasRow(['SITEADDID' => 'PIN-42']));

        $this->assertSame('PIN-42', $record->sourceRef);
        $this->assertSame('pinellas', $record->source);
        $this->assertSame('Pinellas County, FL', $record->jurisdiction);
        $this->assertSame('Current', $record->status);
    }

    public function test_the_global_id_is_the_fallback_source_ref(): void
    {
        $record = $this->accept($this->pinellas(), $this->pinellasRow([
            'SITEADDID' => '', 'GlobalID' => '{fallback-guid}',
        ]));

        $this->assertSame('{fallback-guid}', $record->sourceRef);
    }

    public function test_a_row_with_no_stable_id_is_rejected(): void
    {
        // The dedupe key's third member. Without it a re-import cannot be
        // idempotent, so the row must not enter the corpus.
        $this->assertSame(
            CorpusRejectReason::MISSING_SOURCE_REF,
            $this->rejectReason($this->pinellas(), $this->pinellasRow(['SITEADDID' => '', 'GlobalID' => '']))
        );
    }

    public function test_injected_jurisdiction_is_recorded_as_injected(): void
    {
        // Hillsborough publishes neither state nor county. Supplying them is
        // legitimate; not saying so would not be.
        $record = $this->accept($this->hillsborough(), $this->hillsboroughRow());

        $this->assertSame('FL', $record->state);
        $this->assertSame('Hillsborough', $record->county);
        $this->assertSame('injected', $record->stateProvenance);
        $this->assertSame('injected', $record->countyProvenance);
        $this->assertTrue($record->hasInjectedJurisdiction());
    }

    public function test_a_stated_jurisdiction_is_recorded_as_stated(): void
    {
        $record = $this->accept($this->pinellas(), $this->pinellasRow());

        $this->assertSame('column', $record->stateProvenance);
        $this->assertSame('column', $record->countyProvenance);
        $this->assertFalse($record->hasInjectedJurisdiction());
    }

    // ── jurisdiction scope ──────────────────────────────────────────────────

    public function test_a_florida_source_cannot_answer_for_another_state(): void
    {
        // Georgia is FIPS 13. A Florida county file must contribute nothing to
        // it, whatever its rows say.
        $this->assertFalse($this->pinellas()->matchesState($this->pinellasRow(), '13'));
        $this->assertFalse($this->hillsborough()->matchesState($this->hillsboroughRow(), '13'));

        $this->assertTrue($this->pinellas()->matchesState($this->pinellasRow(), '12'));
        $this->assertTrue($this->hillsborough()->matchesState($this->hillsboroughRow(), '12'));
    }

    public function test_a_row_claiming_another_state_is_excluded(): void
    {
        $this->assertFalse($this->pinellas()->matchesState($this->pinellasRow(['STATEABBREVIATION' => 'GA']), '12'));
    }

    public function test_an_unknown_fips_matches_nothing(): void
    {
        $this->assertFalse($this->pinellas()->matchesState($this->pinellasRow(), '99'));
    }

    public function test_a_coordinate_outside_the_state_box_is_rejected(): void
    {
        $this->assertSame(
            CorpusRejectReason::OUTSIDE_BOUNDS,
            $this->rejectReason($this->pinellas(), $this->pinellasRow([
                GeoJsonSourceReader::LATITUDE  => 39.7392,
                GeoJsonSourceReader::LONGITUDE => -104.9903,
            ]))
        );
    }

    // ── ordinary rejects ────────────────────────────────────────────────────

    /** @dataProvider rejectCases */
    public function test_rows_are_rejected_with_a_named_reason(array $overrides, string $expected): void
    {
        $this->assertSame($expected, $this->rejectReason($this->pinellas(), $this->pinellasRow($overrides)));
    }

    public static function rejectCases(): array
    {
        return [
            'no number'   => [['ADDRNUM' => ''], CorpusRejectReason::MISSING_NUMBER],
            'no street'   => [['FULLNAME' => ''], CorpusRejectReason::MISSING_STREET],
            'null island' => [[GeoJsonSourceReader::LATITUDE => 0, GeoJsonSourceReader::LONGITUDE => 0], CorpusRejectReason::COORDINATE_INVALID],
            'no locality' => [['PSTLCITY' => '', 'MSAG' => '', 'MUNICIPALITY' => '', 'PSTLZIP5' => ''], CorpusRejectReason::INSUFFICIENT],
        ];
    }

    public function test_a_row_with_no_city_still_resolves_via_zip(): void
    {
        $record = $this->accept($this->pinellas(), $this->pinellasRow([
            'PSTLCITY' => '', 'MSAG' => '', 'MUNICIPALITY' => '',
        ]));

        $this->assertFalse($record->hasLocality());
        $this->assertStringEndsWith('fl 33602', $record->normalized());
    }

    public function test_the_source_identity_is_exposed(): void
    {
        $this->assertSame('pinellas', $this->pinellas()->source());
        $this->assertSame('hillsborough', $this->hillsborough()->source());
    }
}
