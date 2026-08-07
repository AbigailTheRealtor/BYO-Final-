<?php

namespace Tests\Unit\Services\Location\Coordinates;

use App\Services\Location\Coordinates\PropertyAddress;
use PHPUnit\Framework\TestCase;

/**
 * PropertyAddress — the two normalizations and the fingerprint input.
 *
 * The condo cases are the point of this suite. Everything else is ordinary
 * string hygiene; the unit rule is the one that decides whether two listings
 * are the same property.
 */
class PropertyAddressTest extends TestCase
{
    private function pinellas(string $unit = ''): PropertyAddress
    {
        return new PropertyAddress(
            address: '123 Main St',
            unitAddress: $unit,
            city: 'St. Petersburg',
            county: 'Pinellas',
            state: 'FL',
            zip: '33701',
        );
    }

    // ── unit handling: the condo rule ────────────────────────────────────────

    public function test_two_units_in_one_building_are_different_property_identities(): void
    {
        $a = $this->pinellas('Unit 4A');
        $b = $this->pinellas('Unit 4B');

        $this->assertNotSame(
            $a->propertyIdentityLine(),
            $b->propertyIdentityLine(),
            '4A and 4B are different properties and must not share an identity'
        );
    }

    public function test_two_units_in_one_building_share_a_coordinate_lookup(): void
    {
        $a = $this->pinellas('Unit 4A');
        $b = $this->pinellas('Unit 4B');

        $this->assertSame(
            $a->coordinateLookupLine(),
            $b->coordinateLookupLine(),
            'Both units sit in one building, so one lookup and one cached coordinate serve both'
        );
    }

    public function test_coordinate_lookup_line_excludes_the_unit(): void
    {
        $this->assertStringNotContainsString('4a', $this->pinellas('Unit 4A')->coordinateLookupLine());
    }

    public function test_property_identity_line_retains_the_unit(): void
    {
        $this->assertStringContainsString('4a', $this->pinellas('Unit 4A')->propertyIdentityLine());
    }

    public function test_unit_designators_are_folded_to_the_bare_identifier(): void
    {
        $forms = ['Unit 4A', 'unit 4a', 'Apt. 4-A', '#4A', 'Suite 4 A', 'STE 4A'];
        $lines = array_map(fn (string $u): string => $this->pinellas($u)->propertyIdentityLine(), $forms);

        $this->assertCount(
            1,
            array_unique($lines),
            'A unit is identified by 4A, not by whether the building says unit/apt/suite: ' . implode(' | ', array_unique($lines))
        );
    }

    public function test_a_property_without_a_unit_reports_no_unit(): void
    {
        $this->assertFalse($this->pinellas()->hasUnit());
        $this->assertTrue($this->pinellas('Unit 4A')->hasUnit());
    }

    public function test_identity_and_lookup_agree_when_there_is_no_unit(): void
    {
        $address = $this->pinellas();

        $this->assertSame($address->coordinateLookupLine(), $address->propertyIdentityLine());
    }

    // ── normalization ───────────────────────────────────────────────────────

    public function test_case_punctuation_and_spacing_do_not_change_identity(): void
    {
        $a = new PropertyAddress('123 Main St.', '', 'St. Petersburg', 'Pinellas', 'FL', '33701');
        $b = new PropertyAddress('  123   MAIN ST  ', '', 'st petersburg', 'pinellas', 'fl', '33701');

        $this->assertSame($a->propertyIdentityLine(), $b->propertyIdentityLine());
    }

    public function test_street_suffixes_and_directionals_are_folded(): void
    {
        $long  = new PropertyAddress('100 North Second Avenue', '', 'Tampa', '', 'FL', '33602');
        $short = new PropertyAddress('100 N Second Ave', '', 'Tampa', '', 'FL', '33602');

        $this->assertSame($long->coordinateLookupLine(), $short->coordinateLookupLine());
    }

    public function test_state_name_and_abbreviation_agree(): void
    {
        $spelled = new PropertyAddress('123 Main St', '', 'Tampa', '', 'Florida', '33602');
        $abbrev  = new PropertyAddress('123 Main St', '', 'Tampa', '', 'FL', '33602');

        $this->assertSame($spelled->coordinateLookupLine(), $abbrev->coordinateLookupLine());
    }

    public function test_zip_plus_four_is_truncated_to_zip5(): void
    {
        $five = new PropertyAddress('123 Main St', '', 'Tampa', '', 'FL', '33602');
        $nine = new PropertyAddress('123 Main St', '', 'Tampa', '', 'FL', '33602-1234');

        $this->assertSame(
            $five->propertyIdentityLine(),
            $nine->propertyIdentityLine(),
            'ZIP+4 identifies a delivery segment, not a property — it must not split one address into two identities'
        );
    }

    public function test_county_is_not_part_of_the_lookup_line(): void
    {
        $withCounty = new PropertyAddress('123 Main St', '', 'Tampa', 'Hillsborough', 'FL', '33602');
        $without    = new PropertyAddress('123 Main St', '', 'Tampa', '', 'FL', '33602');

        $this->assertSame($without->coordinateLookupLine(), $withCounty->coordinateLookupLine());
    }

    // ── determinism ─────────────────────────────────────────────────────────

    public function test_cache_key_input_is_deterministic_across_instances(): void
    {
        $this->assertSame(
            $this->pinellas('Unit 4A')->coordinateCacheKeyInput(),
            $this->pinellas('Unit 4A')->coordinateCacheKeyInput()
        );
    }

    public function test_cache_key_input_is_unit_free_so_a_building_geocodes_once(): void
    {
        $this->assertSame(
            $this->pinellas('Unit 4A')->coordinateCacheKeyInput(),
            $this->pinellas('Unit 12B')->coordinateCacheKeyInput()
        );
    }

    public function test_fingerprint_input_is_the_identity_line(): void
    {
        $address = $this->pinellas('Unit 4A');

        $this->assertSame($address->propertyIdentityLine(), $address->identityFingerprintInput());
    }

    // ── minimum viable input ────────────────────────────────────────────────

    public function test_street_with_zip_is_enough_to_attempt_a_lookup(): void
    {
        $this->assertTrue((new PropertyAddress('123 Main St', '', '', '', '', '33701'))->hasMinimumForLookup());
    }

    public function test_street_with_city_and_state_is_enough(): void
    {
        $this->assertTrue((new PropertyAddress('123 Main St', '', 'Tampa', '', 'FL', ''))->hasMinimumForLookup());
    }

    public function test_street_alone_is_not_enough(): void
    {
        $this->assertFalse((new PropertyAddress('123 Main St'))->hasMinimumForLookup());
    }

    public function test_no_street_is_not_enough(): void
    {
        $this->assertFalse((new PropertyAddress('', '', 'Tampa', '', 'FL', '33602'))->hasMinimumForLookup());
    }

    // ── construction from the component payload shape ───────────────────────

    public function test_from_array_reads_the_listing_component_key_names(): void
    {
        $address = PropertyAddress::fromArray([
            'address'         => '123 Main St',
            'unit_address'    => 'Unit 4A',
            'property_city'   => 'St. Petersburg',
            'property_county' => 'Pinellas',
            'property_state'  => 'FL',
            'property_zip'    => '33701',
        ]);

        $this->assertSame($this->pinellas('Unit 4A')->propertyIdentityLine(), $address->propertyIdentityLine());
    }

    public function test_from_array_tolerates_missing_keys(): void
    {
        $this->assertFalse(PropertyAddress::fromArray([])->hasMinimumForLookup());
    }
}
