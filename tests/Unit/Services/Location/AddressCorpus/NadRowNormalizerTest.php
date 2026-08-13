<?php

namespace Tests\Unit\Services\Location\AddressCorpus;

use App\Services\Location\AddressCorpus\NadPlacementMap;
use App\Services\Location\AddressCorpus\NadRowNormalizer;
use App\Services\Location\AddressCorpus\StateFips;
use App\Services\Location\Coordinates\CoordinatePrecision;
use Tests\TestCase;

/**
 * The pure NAD row → record transform, one literal row at a time.
 *
 * Every reject reason and every field-selection rule gets a case, because a
 * corpus importer's failure mode is not an exception — it is quietly loading
 * 12 million rows with the wrong city field, or dropping 8% of a state without
 * anyone noticing which 8%.
 */
class NadRowNormalizerTest extends TestCase
{
    private NadRowNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new NadRowNormalizer();
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'UUID'       => '{aaaaaaaa-0000-0000-0000-000000000001}',
            'AddNo_Full' => '315',
            'Add_Number' => '315',
            'StNam_Full' => 'E Madison St',
            'SubAddress' => '',
            'Unit'       => '',
            'County'     => 'Hillsborough',
            'Inc_Muni'   => 'Tampa',
            'Post_City'  => 'Tampa',
            'State'      => 'FL',
            'Zip_Code'   => '33602',
            'Placement'  => 'Structure - Rooftop',
            'Latitude'   => '27.9475',
            'Longitude'  => '-82.4570',
        ], $overrides);
    }

    private function accept(array $overrides = [])
    {
        $out = $this->normalizer->normalize($this->row($overrides), '12');

        $this->assertNull($out['reject'], 'Expected acceptance, got reject: ' . (string) $out['reject']);

        return $out['record'];
    }

    private function rejectReason(array $overrides): ?string
    {
        return $this->normalizer->normalize($this->row($overrides), '12')['reject'];
    }

    // ── the ordinary case ───────────────────────────────────────────────────

    public function test_an_ordinary_single_family_row_becomes_a_record(): void
    {
        $r = $this->accept();

        $this->assertSame('{aaaaaaaa-0000-0000-0000-000000000001}', $r->sourceRef);
        $this->assertSame('315 e madison st tampa fl 33602', $r->normalized());
        $this->assertSame(27.9475, $r->latitude);
        $this->assertSame(-82.4570, $r->longitude);
        $this->assertSame('12', $r->stateFips);
        $this->assertFalse($r->hasUnit());
    }

    public function test_the_normalized_line_is_the_canonical_lookup_line(): void
    {
        // Not a second normalizer: the record delegates to PropertyAddress, so a
        // typed address and a corpus row converge by construction rather than by
        // two implementations agreeing.
        $r = $this->accept(['StNam_Full' => 'East Madison Street']);

        $this->assertSame('315 e madison st tampa fl 33602', $r->normalized());
    }

    // ── units ───────────────────────────────────────────────────────────────

    public function test_a_condo_uses_subaddress_and_keeps_the_building_lookup_line(): void
    {
        $r = $this->accept(['SubAddress' => 'Unit 501', 'Unit' => '501']);

        $this->assertSame('subaddress', $r->unitSource);
        $this->assertTrue($r->hasUnit());
        $this->assertSame('315 e madison st tampa fl 33602', $r->normalized());
        $this->assertSame('315 e madison st 501 tampa fl 33602', $r->address->propertyIdentityLine());
    }

    public function test_unit_falls_back_when_subaddress_is_absent(): void
    {
        $r = $this->accept(['SubAddress' => '', 'Unit' => 'Apt 12B']);

        $this->assertSame('unit', $r->unitSource);
        $this->assertSame('315 e madison st 12b tampa fl 33602', $r->address->propertyIdentityLine());
    }

    public function test_a_condo_without_a_unit_is_still_accepted(): void
    {
        $r = $this->accept(['SubAddress' => '', 'Unit' => '']);

        $this->assertSame('none', $r->unitSource);
        $this->assertFalse($r->hasUnit());
    }

    // ── locality ────────────────────────────────────────────────────────────

    public function test_post_city_is_preferred_over_the_legal_municipality(): void
    {
        // The decision that matters most for matching: people type the postal
        // city that appears on their mail, not the incorporating municipality.
        $r = $this->accept(['Post_City' => 'Palm Harbor', 'Inc_Muni' => 'Unincorporated Pinellas']);

        $this->assertSame('post_city', $r->localitySource);
        $this->assertSame('Palm Harbor', $r->city);
        $this->assertSame('315 e madison st palm harbor fl 33602', $r->normalized());
    }

    public function test_the_suffix_vocabulary_does_not_reach_the_city_name(): void
    {
        // Only the street line is suffix-folded. A locality called "Palm Harbor"
        // must stay "palm harbor" and not become "palm hbr" — the folding
        // vocabulary exists for thoroughfare types, and Florida is full of
        // places named after them ("Palm Harbor", "Key West", "Green Cove
        // Springs"). Mangling a city name would put the corpus and the typist on
        // opposite sides of a string neither of them wrote.
        foreach ([
            'Palm Harbor'  => 'palm harbor',
            'Key West'     => 'key west',
            'Center Hill'  => 'center hill',
            'Lake Placid'  => 'lake placid',
        ] as $city => $expected) {
            $this->assertStringContainsString(
                $expected,
                $this->accept(['Post_City' => $city])->normalized(),
                "City '{$city}' must not be suffix-folded"
            );
        }
    }

    public function test_inc_muni_is_the_fallback_when_post_city_is_absent(): void
    {
        $r = $this->accept(['Post_City' => '', 'Inc_Muni' => 'Bartow']);

        $this->assertSame('inc_muni', $r->localitySource);
        $this->assertSame('Bartow', $r->city);
    }

    public function test_a_row_with_neither_locality_still_resolves_via_zip(): void
    {
        $r = $this->accept(['Post_City' => '', 'Inc_Muni' => '']);

        $this->assertSame('none', $r->localitySource);
        $this->assertFalse($r->hasLocality());
        $this->assertSame('315 e madison st fl 33602', $r->normalized());
    }

    public function test_a_row_with_neither_locality_nor_zip_is_rejected(): void
    {
        // Street alone is ambiguous nationwide — PropertyAddress says so, and
        // the corpus must not hold a row the rung could never return.
        $this->assertSame(
            NadRowNormalizer::REJECT_INSUFFICIENT,
            $this->rejectReason(['Post_City' => '', 'Inc_Muni' => '', 'Zip_Code' => ''])
        );
    }

    // ── address number and street ───────────────────────────────────────────

    public function test_the_complete_address_number_is_preferred(): void
    {
        $r = $this->accept(['AddNo_Full' => '123 1/2', 'Add_Number' => '123']);

        $this->assertSame('123 1/2', $r->number);
        $this->assertSame('123 1 2 e madison st tampa fl 33602', $r->normalized());
    }

    public function test_the_bare_number_is_used_when_the_complete_form_is_absent(): void
    {
        $r = $this->accept(['AddNo_Full' => '', 'Add_Number' => '315']);

        $this->assertSame('315', $r->number);
    }

    public function test_zip_plus_four_is_truncated_to_five(): void
    {
        $r = $this->accept(['Zip_Code' => '33770-1234']);

        $this->assertSame('33770', $r->address->normalizedZip5());
        $this->assertStringEndsWith('fl 33770', $r->normalized());
    }

    public function test_a_directional_street_normalizes_symmetrically(): void
    {
        $long  = $this->accept(['StNam_Full' => 'Northwest Bayshore Crossing'])->normalized();
        $short = $this->accept(['StNam_Full' => 'NW Bayshore Xing'])->normalized();

        $this->assertSame($long, $short);
    }

    // ── rejects ─────────────────────────────────────────────────────────────

    /** @dataProvider rejectCases */
    public function test_rows_are_rejected_with_a_named_reason(array $overrides, string $expected): void
    {
        $this->assertSame($expected, $this->rejectReason($overrides));
    }

    public static function rejectCases(): array
    {
        return [
            'no uuid'          => [['UUID' => ''], NadRowNormalizer::REJECT_MISSING_UUID],
            'no number'        => [['AddNo_Full' => '', 'Add_Number' => ''], NadRowNormalizer::REJECT_MISSING_NUMBER],
            'no street'        => [['StNam_Full' => ''], NadRowNormalizer::REJECT_MISSING_STREET],
            'no latitude'      => [['Latitude' => ''], NadRowNormalizer::REJECT_MISSING_LATITUDE],
            'no longitude'     => [['Longitude' => ''], NadRowNormalizer::REJECT_MISSING_LONGITUDE],
            'bad latitude'     => [['Latitude' => 'not-a-number'], NadRowNormalizer::REJECT_MALFORMED_LATITUDE],
            'bad longitude'    => [['Longitude' => 'nonsense'], NadRowNormalizer::REJECT_MALFORMED_LONGITUDE],
            'null island'      => [['Latitude' => '0', 'Longitude' => '0'], NadRowNormalizer::REJECT_COORDINATE_INVALID],
            'latitude > 90'    => [['Latitude' => '91.5'], NadRowNormalizer::REJECT_COORDINATE_INVALID],
            'denver in florida' => [['Latitude' => '39.7392', 'Longitude' => '-104.9903'], NadRowNormalizer::REJECT_OUTSIDE_BOUNDS],
        ];
    }

    public function test_a_numerically_valid_coordinate_in_the_wrong_state_is_not_accepted(): void
    {
        // The point that matters: it parses, it is in range, it is on land, and
        // it is 1,700 miles from the address it claims to describe.
        $this->assertSame(
            NadRowNormalizer::REJECT_OUTSIDE_BOUNDS,
            $this->rejectReason(['Latitude' => '39.7392', 'Longitude' => '-104.9903'])
        );
    }

    public function test_a_jurisdiction_without_a_bounding_box_is_not_bounds_checked(): void
    {
        // A missing box must never reject a valid address — the check is a
        // sanity net, and an absent net is not a closed gate.
        $out = $this->normalizer->normalize(
            $this->row(['State' => 'GA', 'Latitude' => '33.7490', 'Longitude' => '-84.3880']),
            '13'
        );

        $this->assertNull($out['reject']);
    }

    // ── state filtering ─────────────────────────────────────────────────────

    public function test_state_matching_uses_the_fips_to_usps_map(): void
    {
        $this->assertTrue($this->normalizer->matchesState($this->row(), '12'));
        $this->assertFalse($this->normalizer->matchesState($this->row(['State' => 'GA']), '12'));
        $this->assertTrue($this->normalizer->matchesState($this->row(['State' => 'ga']), '13'));
    }

    public function test_an_unknown_fips_matches_nothing(): void
    {
        $this->assertFalse($this->normalizer->matchesState($this->row(), '99'));
    }

    public function test_fips_codes_are_zero_padded(): void
    {
        $this->assertSame('06', StateFips::normalizeFips('6'));
        $this->assertSame('12', StateFips::normalizeFips('12'));
        $this->assertSame('FL', StateFips::toUsps('12'));
        $this->assertSame('12', StateFips::toFips('fl'));
        $this->assertNull(StateFips::toUsps('99'));
    }

    // ── placement is preserved, never inferred ──────────────────────────────

    public function test_the_raw_placement_value_survives_onto_the_record(): void
    {
        $this->assertSame('Structure - Rooftop', $this->accept()->rawPlacement);
        $this->assertSame('Site - Approximate', $this->accept(['Placement' => 'Site - Approximate'])->rawPlacement);
    }

    public function test_a_missing_placement_does_not_prevent_acceptance(): void
    {
        $r = $this->accept(['Placement' => '']);

        $this->assertSame('', $r->rawPlacement);
    }

    public function test_a_valid_coordinate_never_implies_a_precision(): void
    {
        // The open decision. A row can carry a perfect rooftop coordinate and
        // say nothing about how it was placed; nothing here may fill that in.
        $this->assertNull(NadPlacementMap::proposedPrecision(null));
        $this->assertNull(NadPlacementMap::proposedPrecision(''));
        $this->assertNull(NadPlacementMap::proposedPrecision('Site - Approximate'));
        $this->assertFalse(NadPlacementMap::isRecognised(''));
    }

    /** @dataProvider recognisedPlacements */
    public function test_recognised_placement_values_propose_a_tier(string $raw, CoordinatePrecision $expected): void
    {
        $this->assertSame($expected, NadPlacementMap::proposedPrecision($raw));
    }

    public static function recognisedPlacements(): array
    {
        return [
            ['Structure - Rooftop',  CoordinatePrecision::Rooftop],
            ['structure-rooftop',    CoordinatePrecision::Rooftop],
            ['  Structure  -  Rooftop ', CoordinatePrecision::Rooftop],
            ['Structure - Entrance', CoordinatePrecision::Entrance],
            ['Parcel - Centroid',    CoordinatePrecision::Parcel],
        ];
    }

    public function test_placement_matching_is_exact_never_substring(): void
    {
        // "Parcel" appearing inside a longer phrase is not evidence the phrase
        // means a parcel centroid.
        $this->assertNull(NadPlacementMap::proposedPrecision('Not A Parcel At All'));
        $this->assertNull(NadPlacementMap::proposedPrecision('Rooftop Estimated From Parcel'));
    }
}
