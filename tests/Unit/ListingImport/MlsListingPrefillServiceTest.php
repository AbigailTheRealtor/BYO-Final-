<?php

namespace Tests\Unit\ListingImport;

use App\Services\ListingImport\MlsListingPrefillService;
use App\Services\Property\PropertyCandidate;
use Tests\TestCase;

/**
 * MlsListingPrefillService — the facts-only boundary for Seller/Landlord prefill.
 *
 * The compliance tests here are the reason this class exists. PropertyCandidate
 * deliberately enforces no allow-list and carries the untouched Bridge record in
 * $raw, so if this service ever started reading $raw — for a fallback, for one
 * missing field — remarks, agent details and media URLs would begin flowing into
 * a publishable listing form. These tests are what make that a failing build
 * rather than a licensing incident.
 */
class MlsListingPrefillServiceTest extends TestCase
{
    private function service(): MlsListingPrefillService
    {
        return new MlsListingPrefillService();
    }

    /**
     * A candidate carrying every restricted field a real Stellar record would.
     * The restricted values are distinctive strings so a leak is unmistakable.
     */
    private function candidate(array $overrides = [], array $raw = []): PropertyCandidate
    {
        $defaults = [
            'source'                => 'bridge',
            'sourceRecordId'        => '42',
            'mlsNumber'             => 'A4567890',
            'listingKey'            => 'STELLAR-MFR-4567890',
            'standardStatus'        => 'Active',
            'mlsStatus'             => 'Active',
            'propertyType'          => 'Residential',
            'propertySubType'       => 'Single Family Residence',
            'listPrice'             => 459000.0,
            'unparsedAddress'       => '123 Main St, Tampa, FL 33601',
            'city'                  => 'Tampa',
            'stateOrProvince'       => 'FL',
            'postalCode'            => '33601',
            'countyOrParish'        => 'Hillsborough',
            'bedrooms'              => 4,
            'bathrooms'             => 3,
            'livingAreaSqft'        => 2450,
            'lotSizeSqft'           => 8712,
            'yearBuilt'             => 1998,
            'latitude'              => 27.9506,
            'longitude'             => -82.4572,
            'associationFee'        => 250.0,
            'taxAnnualAmount'       => 5400.0,
            'petsAllowed'           => 'Yes',
            'pool'                  => true,
            'garage'                => true,
            'waterfront'            => false,
            'view'                  => null,
            'waterView'             => null,
            'seniorCommunity'       => false,
            'association'           => true,
            'newConstruction'       => false,
            'cdd'                   => false,
            'modificationTimestamp' => '2026-08-01 12:00:00',
            'raw'                   => array_merge([
                'PublicRemarks'       => 'RESTRICTED_PUBLIC_REMARKS stunning move-in ready home!',
                'PrivateRemarks'      => 'RESTRICTED_PRIVATE_REMARKS lockbox code 1234',
                'ShowingInstructions' => 'RESTRICTED_SHOWING call first',
                'ListAgentFullName'   => 'RESTRICTED_AGENT Jane Agent',
                'ListAgentEmail'      => 'RESTRICTED_AGENT_EMAIL jane@example.com',
                'ListAgentDirectPhone' => 'RESTRICTED_AGENT_PHONE 813-555-0100',
                'ListOfficeName'      => 'RESTRICTED_BROKER Acme Realty',
                'ListOfficePhone'     => 'RESTRICTED_BROKER_PHONE 813-555-0199',
                'Media'               => ['RESTRICTED_PHOTO https://cdn.example.com/1.jpg'],
                'VirtualTourURLUnbranded' => 'RESTRICTED_TOUR https://tour.example.com',
            ], $raw),
        ];

        return new PropertyCandidate(...array_merge($defaults, $overrides));
    }

    // ── Result shape ─────────────────────────────────────────────────────────

    public function test_returns_import_service_result_shape(): void
    {
        $result = $this->service()->fromCandidate($this->candidate());

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error']);
        $this->assertIsArray($result['data']);
    }

    public function test_null_candidate_is_a_failure_not_a_crash(): void
    {
        $result = $this->service()->fromCandidate(null);

        $this->assertFalse($result['success']);
        $this->assertSame([], $result['data']);
        $this->assertNotSame('', $result['error']);
    }

    // ── Field mapping ────────────────────────────────────────────────────────

    public function test_maps_every_expected_factual_field(): void
    {
        $data = $this->service()->fromCandidate($this->candidate())['data'];

        $this->assertSame('123 Main St, Tampa, FL 33601', $data['address']);
        $this->assertSame('Tampa',        $data['city']);
        $this->assertSame('FL',           $data['state']);
        $this->assertSame('33601',        $data['zip']);
        $this->assertSame('Hillsborough', $data['county']);
        $this->assertSame('4',            $data['bedrooms']);
        $this->assertSame('3',            $data['bathrooms']);
        $this->assertSame('2450',         $data['heated_sqft']);
        $this->assertSame('8712',         $data['lot_size_sqft']);
        $this->assertSame('1998',         $data['year_built']);
        $this->assertSame('459000',       $data['price']);
        $this->assertSame('Residential',  $data['property_type']);
        $this->assertSame('Single Family Residence', $data['property_sub_type']);
        $this->assertSame('Active',       $data['mls_status']);
        $this->assertSame('A4567890',     $data['mls_number']);
        $this->assertSame('STELLAR-MFR-4567890', $data['mls_listing_key']);
    }

    public function test_coordinates_are_mapped_and_trimmed(): void
    {
        $data = $this->service()->fromCandidate($this->candidate())['data'];

        $this->assertSame('27.9506',  $data['latitude']);
        $this->assertSame('-82.4572', $data['longitude']);
    }

    public function test_whole_number_price_has_no_trailing_decimal(): void
    {
        $data = $this->service()->fromCandidate($this->candidate(['listPrice' => 350000.0]))['data'];

        $this->assertSame('350000', $data['price']);
    }

    public function test_absent_fields_are_omitted_rather_than_blank(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'countyOrParish' => null,
            'yearBuilt'      => null,
            'lotSizeSqft'    => null,
        ]))['data'];

        $this->assertArrayNotHasKey('county',        $data);
        $this->assertArrayNotHasKey('year_built',    $data);
        $this->assertArrayNotHasKey('lot_size_sqft', $data);
    }

    // ── Coordinate pair atomicity ────────────────────────────────────────────

    /**
     * Half a coordinate pair is not a partial location — and it is worse than
     * none, because a populated property_lat suppresses the geocoding fallback.
     *
     * @dataProvider brokenCoordinateProvider
     */
    public function test_incomplete_or_invalid_coordinates_are_dropped_as_a_pair(
        ?float $lat,
        ?float $lng,
        string $why
    ): void {
        $data = $this->service()->fromCandidate($this->candidate([
            'latitude'  => $lat,
            'longitude' => $lng,
        ]))['data'];

        $this->assertArrayNotHasKey('latitude',  $data, $why);
        $this->assertArrayNotHasKey('longitude', $data, $why);
    }

    public static function brokenCoordinateProvider(): array
    {
        return [
            'latitude missing'   => [null,     -82.4572, 'a lone longitude is not a location'],
            'longitude missing'  => [27.9506,  null,     'a lone latitude is not a location'],
            'both missing'       => [null,     null,     'nothing to import'],
            'zero latitude'      => [0.0,      -82.4572, 'a feed zero is an unset column, not Null Island'],
            'zero longitude'     => [27.9506,  0.0,      'a feed zero is an unset column, not Null Island'],
            'both zero'          => [0.0,      0.0,      'Null Island'],
            'latitude past pole' => [91.0,     -82.4572, 'out of range'],
            'longitude too big'  => [27.9506,  181.0,    'out of range'],
        ];
    }

    public function test_valid_negative_coordinates_survive(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'latitude'  => -33.8688,
            'longitude' => -70.6693,
        ]))['data'];

        $this->assertSame('-33.8688', $data['latitude']);
        $this->assertSame('-70.6693', $data['longitude']);
    }

    // ── FACTS ONLY — the compliance boundary ─────────────────────────────────

    /**
     * Not one restricted value may appear anywhere in the returned data, under
     * any key. Scans the serialized result rather than named keys, so a leak
     * under an unexpected key is caught too.
     *
     * @dataProvider restrictedMarkerProvider
     */
    public function test_restricted_source_fields_never_reach_import_data(string $marker, string $what): void
    {
        $result = $this->service()->fromCandidate($this->candidate());

        $this->assertStringNotContainsString(
            $marker,
            json_encode($result),
            "{$what} leaked into prefill data — this is a Stellar MLS licensing boundary"
        );
    }

    public static function restrictedMarkerProvider(): array
    {
        return [
            'public remarks'   => ['RESTRICTED_PUBLIC_REMARKS',  'PublicRemarks'],
            'private remarks'  => ['RESTRICTED_PRIVATE_REMARKS', 'PrivateRemarks'],
            'showing instr.'   => ['RESTRICTED_SHOWING',         'ShowingInstructions'],
            'agent name'       => ['RESTRICTED_AGENT',           'listing agent name'],
            'agent email'      => ['RESTRICTED_AGENT_EMAIL',     'listing agent email'],
            'agent phone'      => ['RESTRICTED_AGENT_PHONE',     'listing agent phone'],
            'broker name'      => ['RESTRICTED_BROKER',          'broker/office name'],
            'broker phone'     => ['RESTRICTED_BROKER_PHONE',    'broker phone'],
            'photos'           => ['RESTRICTED_PHOTO',           'media/photo URL'],
            'virtual tour'     => ['RESTRICTED_TOUR',            'virtual tour URL'],
        ];
    }

    /**
     * The allow-list is the mechanism, so its contents are pinned. Adding a
     * field here is a licensing decision — this assertion makes that decision
     * visible in review instead of arriving as a silent mapping tweak.
     */
    public function test_allow_list_contents_are_pinned(): void
    {
        $this->assertSame([
            'mlsNumber'       => 'mls_number',
            'listingKey'      => 'mls_listing_key',
            'unparsedAddress' => 'address',
            'city'            => 'city',
            'stateOrProvince' => 'state',
            'postalCode'      => 'zip',
            'countyOrParish'  => 'county',
            'latitude'        => 'latitude',
            'longitude'       => 'longitude',
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'livingAreaSqft'  => 'heated_sqft',
            'lotSizeSqft'     => 'lot_size_sqft',
            'yearBuilt'       => 'year_built',
            'propertyType'    => 'property_type',
            'propertySubType' => 'property_sub_type',
            'mlsStatus'       => 'mls_status',
            'listPrice'       => 'price',
            'taxAnnualAmount' => 'annual_taxes',
            'association'     => 'has_hoa',
            'associationFee'  => 'association_fee_amount',
            'cdd'             => 'has_cdd',
            'waterfront'      => 'waterfront',
            'pool'            => 'pool',
            'garage'          => 'garage',

            // ── Bridge reconciliation ───────────────────────────────────────
            // Construction, systems, land, tax/legal and hazard facts. Each has
            // a canonical BidYourOffer field AND an MlsFieldMap target for both
            // Seller and Landlord, each target was confirmed rendered on the
            // live form with no property-type restriction, and each was being
            // fetched from the feed and discarded before this.
            //
            // Objective property characteristics only — nothing authored, no
            // imagery, no contact data, no transaction terms.
            'appliances'            => 'appliances',
            'constructionMaterials' => 'exterior_construction',
            'cooling'               => 'air_conditioning',
            'heating'               => 'heating_fuel',
            'foundationDetails'     => 'foundation',
            'interiorFeatures'      => 'interior_features',
            'roof'                  => 'roof_type',
            'sewer'                 => 'sewer',
            'utilities'             => 'utilities',
            'waterSource'           => 'water',
            'waterfrontFeatures'    => 'water_access',
            'parcelNumber'          => 'tax_id',
            'taxLegalDescription'   => 'legal_description',
            'taxYear'               => 'tax_year',
            'buildingAreaTotal'     => 'building_size_sqft',
            'floodZoneCode'         => 'flood_zone_code',
        ], MlsListingPrefillService::ALLOWED_FIELDS);
    }

    /**
     * Master Phase 1 deliberately stops short of three fields the candidate can
     * already supply. Each exclusion is a decision, not an oversight, so each one
     * is asserted — a future edit that "completes" the set has to delete a test
     * with a reason written next to it.
     *
     * @dataProvider deliberatelyExcludedCandidateProperties
     */
    public function test_deliberately_excluded_candidate_properties_are_not_allow_listed(string $property, string $why): void
    {
        $this->assertArrayNotHasKey(
            $property,
            MlsListingPrefillService::ALLOWED_FIELDS,
            $why
        );
    }

    public static function deliberatelyExcludedCandidateProperties(): array
    {
        return [
            'occupancy: OccupantType belongs to a user-controlled terms surface' => [
                'occupantType',
                'occupant_status lives on the Sale Terms / Leasing Terms tab, which is the '
                . "user's statement of how they intend to transact. The feed's view of who "
                . 'occupies the property today is not that claim, and there is no MlsFieldMap '
                . 'target for it on either role.',
            ],
            'flooring: no MlsFieldMap target on either role' => [
                'flooring',
                'A permitted fact with a real form field but no canonical route. Minting one '
                . 'inside a licensing allow-list is a separate, reviewable change.',
            ],
            'subdivision: no MlsFieldMap target on either role' => [
                'subdivisionName',
                'Same as flooring — permitted, but there is no canonical route to add it to.',
            ],
            'furnished: role-divergent target with merge semantics the write path lacks' => [
                'furnished',
                'Seller merges Furnished into building_features and excludes "Unfurnished"; '
                . 'Landlord routes it to tenant_require. MlsQuickImportDraftWriter has no '
                . 'merge step, so importing it would replace a user array instead of adding.',
            ],
            'pets: no wire:model binding for pet_policy on any Create Offer tab' => [
                'petsAllowed',
                'pet_policy has no blade binding — importing it writes state the user cannot see or correct',
            ],
            'standardStatus: no form target on either role' => [
                'standardStatus',
                'no Livewire property and no form field accepts a listing status',
            ],
            'seniorCommunity: no form target on either role' => [
                'seniorCommunity',
                'leasing_55_plus is a different concept with its own vocabulary',
            ],
        ];
    }

    /**
     * A new restricted field appearing in the feed must not appear in the
     * output. This is the fail-closed property an allow-list has and a denylist
     * does not.
     */
    public function test_an_unknown_future_raw_field_cannot_leak(): void
    {
        $result = $this->service()->fromCandidate($this->candidate([], [
            'SomeBrandNewAgentNotesField' => 'RESTRICTED_FUTURE_FIELD seller is motivated, will take less',
        ]));

        $this->assertStringNotContainsString('RESTRICTED_FUTURE_FIELD', json_encode($result));
    }

    /**
     * Every key the service can emit is one the allow-list names. Guards against
     * a future edit adding an ad-hoc key outside the constant.
     */
    public function test_output_keys_are_a_subset_of_the_allow_list(): void
    {
        $data = $this->service()->fromCandidate($this->candidate())['data'];

        $this->assertEmpty(
            array_diff(array_keys($data), array_values(MlsListingPrefillService::ALLOWED_FIELDS)),
            'prefill emitted a canonical key that is not in ALLOWED_FIELDS'
        );
    }

    // ── Master Phase 1: null vs false vs zero ────────────────────────────────

    /**
     * Absent is not "No". A feed that never populated GarageYN is telling us
     * nothing about the garage, and writing "No" into the form would turn our
     * ignorance into the seller's assertion — on a disclosure form, where that
     * distinction is the whole point. Null must produce no row at all, leaving
     * the field for a human.
     *
     * @dataProvider nullBearingPhaseOneProperties
     */
    public function test_a_null_phase_one_value_produces_no_key(string $property, string $canonicalKey): void
    {
        $data = $this->service()->fromCandidate($this->candidate([$property => null]))['data'];

        $this->assertArrayNotHasKey(
            $canonicalKey,
            $data,
            "null {$property} must not be translated into a value"
        );
    }

    public static function nullBearingPhaseOneProperties(): array
    {
        return [
            'association fee' => ['associationFee', 'association_fee_amount'],
            'annual taxes'    => ['taxAnnualAmount', 'annual_taxes'],
            'hoa'             => ['association', 'has_hoa'],
            'cdd'             => ['cdd', 'has_cdd'],
            'pool'            => ['pool', 'pool'],
            'garage'          => ['garage', 'garage'],
            'waterfront'      => ['waterfront', 'waterfront'],
        ];
    }

    /**
     * False, by contrast, IS an assertion — the feed says there is no pool — and
     * must survive as the form's "No". The bug this guards against is treating
     * false as empty, which every naive `if ($value)` does.
     *
     * @dataProvider falseBearingPhaseOneProperties
     */
    public function test_false_is_imported_as_no(string $property, string $canonicalKey): void
    {
        $data = $this->service()->fromCandidate($this->candidate([$property => false]))['data'];

        $this->assertSame('No', $data[$canonicalKey] ?? null, "false {$property} must import as No");
    }

    public static function falseBearingPhaseOneProperties(): array
    {
        return [
            'hoa'        => ['association', 'has_hoa'],
            'cdd'        => ['cdd', 'has_cdd'],
            'pool'       => ['pool', 'pool'],
            'garage'     => ['garage', 'garage'],
            'waterfront' => ['waterfront', 'waterfront'],
        ];
    }

    public function test_true_is_imported_as_yes(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'association' => true,
            'pool'        => true,
            'waterfront'  => true,
        ]))['data'];

        $this->assertSame('Yes', $data['has_hoa']);
        $this->assertSame('Yes', $data['pool']);
        $this->assertSame('Yes', $data['waterfront']);
    }

    /**
     * A zero-dollar HOA fee and a zero tax bill are facts, and both are falsy.
     * Integer and float zero are separated because they fail differently: the
     * float path also runs the whole-number branch that would otherwise render
     * "0.0".
     */
    public function test_integer_zero_survives(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'associationFee'  => 0,
            'taxAnnualAmount' => 0,
        ]))['data'];

        $this->assertSame('0', $data['association_fee_amount'] ?? null);
        $this->assertSame('0', $data['annual_taxes'] ?? null);
    }

    public function test_decimal_zero_survives_and_is_not_rendered_as_a_float(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'associationFee'  => 0.0,
            'taxAnnualAmount' => 0.00,
        ]))['data'];

        $this->assertSame('0', $data['association_fee_amount'] ?? null);
        $this->assertSame('0', $data['annual_taxes'] ?? null);
    }

    public function test_a_fractional_amount_keeps_its_cents(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'taxAnnualAmount' => 1786.38,
        ]))['data'];

        $this->assertSame('1786.38', $data['annual_taxes']);
    }

    /**
     * The listing the master audit was built on, as a fixed candidate. Guards the
     * exact combination that made it a good specimen: a true, several falses, a
     * fractional tax amount and a genuinely absent association fee.
     */
    public function test_audited_condo_tb8528949_produces_the_expected_facts(): void
    {
        $data = $this->service()->fromCandidate($this->candidate([
            'mlsNumber'       => 'TB8528949',
            'listingKey'      => 'e8ea2e5193b25dbab98d77f1e11e070d',
            'unparsedAddress' => '2142 BRADFORD STREET UNIT 308',
            'city'            => 'CLEARWATER',
            'postalCode'      => '33760',
            'countyOrParish'  => 'Pinellas',
            'propertySubType' => 'Condominium',
            'listPrice'       => 100000.0,
            'bedrooms'        => 1,
            'bathrooms'       => 1,
            'livingAreaSqft'  => 480,
            'lotSizeSqft'     => null,
            'yearBuilt'       => 1986,
            'taxAnnualAmount' => 1786.38,
            'association'     => true,
            'associationFee'  => null,
            'cdd'             => false,
            'pool'            => false,
            'garage'          => false,
            'waterfront'      => true,
        ]))['data'];

        $this->assertSame('1786.38', $data['annual_taxes']);
        $this->assertSame('Yes', $data['has_hoa']);
        $this->assertSame('No',  $data['has_cdd']);
        $this->assertSame('No',  $data['pool']);
        $this->assertSame('No',  $data['garage']);
        $this->assertSame('Yes', $data['waterfront']);

        $this->assertArrayNotHasKey(
            'association_fee_amount',
            $data,
            'the feed carried no AssociationFee for this listing, so no row may claim one'
        );
    }

    // ── Degenerate candidate ─────────────────────────────────────────────────

    public function test_candidate_with_only_record_handles_is_a_failure(): void
    {
        $result = $this->service()->fromCandidate(new PropertyCandidate(
            source: 'bridge', sourceRecordId: '1',
            mlsNumber: 'A1', listingKey: 'K1',
            standardStatus: null, mlsStatus: null, propertyType: null, propertySubType: null,
            listPrice: null,
            unparsedAddress: null, city: null, stateOrProvince: null, postalCode: null, countyOrParish: null,
            bedrooms: null, bathrooms: null, livingAreaSqft: null, lotSizeSqft: null, yearBuilt: null,
            latitude: null, longitude: null,
            associationFee: null, taxAnnualAmount: null,
            petsAllowed: null, pool: null, garage: null, waterfront: null, view: null, waterView: null,
            seniorCommunity: null, association: null, newConstruction: null, cdd: null,
        ));

        $this->assertFalse($result['success'], 'a record with no property facts is not a usable import');
        $this->assertNotSame('', $result['error']);
    }
}
