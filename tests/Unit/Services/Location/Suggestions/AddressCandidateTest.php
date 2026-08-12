<?php

namespace Tests\Unit\Services\Location\Suggestions;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use App\Services\Location\Suggestions\AddressCandidate;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

class AddressCandidateTest extends TestCase
{
    private function tampaCandidate(array $overrides = []): AddressCandidate
    {
        return new AddressCandidate(...array_merge([
            'providerId'  => 'address_point',
            'displayLine' => '123 N Main St Unit 4A, Tampa, FL 33602',
            'number'      => '123',
            'street'      => 'North Main Street',
            'unit'        => 'Unit 4A',
            'city'        => 'Tampa',
            'state'       => 'FL',
            'zip'         => '33602',
            'sourceRef'   => 'nad-uuid-0001',
        ], $overrides));
    }

    // ── it becomes an address, not a coordinate ─────────────────────────────

    public function test_picking_a_candidate_yields_an_address_for_the_ladder(): void
    {
        $address = $this->tampaCandidate()->toPropertyAddress();

        $this->assertSame('123 North Main Street', $address->address);
        $this->assertSame('Unit 4A', $address->unitAddress);
        $this->assertSame('Tampa', $address->city);
        $this->assertSame('fl', $address->normalizedState());
        $this->assertSame('33602', $address->normalizedZip5());
    }

    public function test_the_resulting_address_normalizes_through_the_canonical_lookup_line(): void
    {
        // The candidate hands over raw parts and normalizes nothing itself, so
        // the one normalization that matters is PropertyAddress's — the same one
        // every other rung of the ladder is keyed on. "North ... Street" folds
        // to "n ... st" and the unit is dropped, which is what makes a candidate
        // pick share a cache entry with a hand-typed address for the same place.
        $lookup = $this->tampaCandidate()->toPropertyAddress()->coordinateLookupLine();

        $this->assertSame('123 n main st tampa fl 33602', $lookup);
    }

    public function test_two_units_in_one_building_share_a_lookup_line_but_not_an_identity(): void
    {
        $a = $this->tampaCandidate(['unit' => 'Unit 4A'])->toPropertyAddress();
        $b = $this->tampaCandidate(['unit' => '#4b'])->toPropertyAddress();

        $this->assertSame(
            $a->coordinateLookupLine(),
            $b->coordinateLookupLine(),
            'Both units are in one building, so one lookup and one cache entry serve both.'
        );

        $this->assertNotSame(
            $a->propertyIdentityLine(),
            $b->propertyIdentityLine(),
            'They are different properties with different owners.'
        );
    }

    public function test_a_candidate_carries_no_listing_handle_into_the_address(): void
    {
        // A suggestion knows nothing about which of our tables will hold the
        // listing. Letting a handle appear here would let the Existing/MLS rungs
        // match a row on the strength of a dropdown pick.
        $address = $this->tampaCandidate()->toPropertyAddress();

        $this->assertFalse($address->hasListingHandle());
        $this->assertFalse($address->hasMlsListingKey());
    }

    // ── the coordinate hint is a hint ───────────────────────────────────────

    public function test_an_unstated_precision_reads_as_coarse(): void
    {
        $candidate = $this->tampaCandidate(['latitude' => 27.9506, 'longitude' => -82.4572]);

        $this->assertSame(CoordinatePrecision::Unknown, $candidate->precision);
        $this->assertFalse(
            $candidate->precision->isExact(),
            'A provider that does not state its precision does not get to be trusted with one.'
        );
    }

    public function test_the_hint_is_exposed_only_for_display(): void
    {
        $candidate = $this->tampaCandidate([
            'latitude'  => 27.9506,
            'longitude' => -82.4572,
            'precision' => CoordinatePrecision::Rooftop,
        ]);

        $this->assertTrue($candidate->hasCoordinateHint());
        $this->assertSame(['lat' => 27.9506, 'lng' => -82.4572], $candidate->displayCoordinateHint());

        // Even at rooftop precision there is no accessor that offers this point
        // for measurement. That gate lives on PropertyCoordinateResult, and a
        // candidate is not entitled to answer it.
        $this->assertFalse(
            method_exists($candidate, 'exactCoordinates'),
            'A candidate must not expose a measurement accessor; the ladder is what earns one.'
        );
    }

    public function test_a_candidate_without_a_point_reports_no_hint(): void
    {
        $candidate = $this->tampaCandidate();

        $this->assertFalse($candidate->hasCoordinateHint());
        $this->assertNull($candidate->displayCoordinateHint());
    }

    public function test_half_a_coordinate_is_no_coordinate(): void
    {
        $candidate = $this->tampaCandidate(['latitude' => 27.9506]);

        $this->assertFalse($candidate->hasCoordinateHint());
        $this->assertNull($candidate->displayCoordinateHint());
    }

    // ── it cannot become a resolution ───────────────────────────────────────

    public function test_no_method_converts_a_candidate_into_a_coordinate_result(): void
    {
        // The failure this guards is the one the old path shipped: an
        // autocomplete pick's coordinate becoming the property's coordinate with
        // no provider, no precision and no provenance recorded.
        foreach ((new ReflectionClass(AddressCandidate::class))->getMethods() as $method) {
            $type = $method->getReturnType();

            if ($type instanceof ReflectionNamedType) {
                $this->assertNotSame(
                    PropertyCoordinateResult::class,
                    $type->getName(),
                    "AddressCandidate::{$method->getName()}() must not manufacture a coordinate result."
                );
            }
        }
    }

    // ── provenance survives ─────────────────────────────────────────────────

    public function test_the_upstream_record_id_survives_onto_the_candidate(): void
    {
        // Same value the `addresses` corpus stores in source_ref, so a bad
        // suggestion is traceable to the row that produced it.
        $this->assertSame('nad-uuid-0001', $this->tampaCandidate()->sourceRef);
        $this->assertSame('address_point', $this->tampaCandidate()->providerId);
    }

    public function test_to_array_round_trips_every_field(): void
    {
        $array = $this->tampaCandidate([
            'latitude'   => 27.9506,
            'longitude'  => -82.4572,
            'precision'  => CoordinatePrecision::Parcel,
            'confidence' => 0.92,
        ])->toArray();

        $this->assertSame('address_point', $array['provider_id']);
        $this->assertSame('nad-uuid-0001', $array['source_ref']);
        $this->assertSame('parcel', $array['precision']);
        $this->assertSame(0.92, $array['confidence']);
        $this->assertSame(27.9506, $array['latitude']);
    }
}
