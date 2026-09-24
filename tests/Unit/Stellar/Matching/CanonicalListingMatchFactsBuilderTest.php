<?php

namespace Tests\Unit\Stellar\Matching;

use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Services\Stellar\Matching\CanonicalListingMatchFactsBuilder;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\ListingMatchResidualFacts;
use App\Support\Listing\MlsProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * P1-B — the canonical builder's conversions, its MLS-only boundary and its ORIGIN map.
 *
 * Extends PHPUnit's TestCase directly: the builder is pure, and running with no
 * application booted proves it needs no container, config or database.
 */
class CanonicalListingMatchFactsBuilderTest extends TestCase
{
    public function test_it_converts_each_canonical_value_to_the_representation_the_rules_read(): void
    {
        $facts = $this->build([
            V::LOCATION_LATITUDE        => 27.788945,
            V::LOCATION_LONGITUDE       => -82.735144,
            V::LISTING_LIST_PRICE       => 184900.0,
            V::PROPERTY_LIVING_AREA_SQFT => 775.0,
            V::PROPERTY_YEAR_BUILT      => 1991,
            V::LOCATION_CITY            => 'ST PETERSBURG',
            V::LOCATION_STATE           => 'FL',
            V::LOCATION_POSTAL_CODE     => '33710',
            V::LOCATION_COUNTY          => 'Pinellas',
        ]);

        // decimal:7 and decimal:2 casts hand the rules fixed-point strings.
        $this->assertSame('27.7889450', $facts->latitude);
        $this->assertSame('-82.7351440', $facts->longitude);
        $this->assertSame('184900.00', $facts->listPrice);
        $this->assertSame(775, $facts->livingArea);
        $this->assertSame(1991, $facts->yearBuilt);
        // Text passes through exactly, case preserved.
        $this->assertSame('ST PETERSBURG', $facts->city);
        $this->assertSame('FL', $facts->stateOrProvince);
        $this->assertSame('33710', $facts->postalCode);
        $this->assertSame('Pinellas', $facts->countyOrParish);
        $this->assertSame('KEY-1', $facts->listingKey);
    }

    public function test_the_rent_period_token_passes_through(): void
    {
        $facts = $this->build([
            V::LISTING_TRANSACTION_TYPE       => 'lease',
            V::LISTING_LEASE_AMOUNT_FREQUENCY => 'month_to_month',
        ]);

        $this->assertSame('month_to_month', $facts->leaseFrequency);
    }

    /** @dataProvider typeSpellings */
    public function test_the_property_type_is_the_recognised_type_for_category_and_transaction(string $category, string $transaction, string $expected): void
    {
        $facts = $this->build([V::PROPERTY_TYPE => $category, V::LISTING_TRANSACTION_TYPE => $transaction]);

        $this->assertSame($expected, $facts->propertyType);
    }

    public static function typeSpellings(): array
    {
        return [
            'residential sale'     => ['Residential', 'sale', 'Residential'],
            'residential lease'    => ['Residential', 'lease', 'Residential Lease'],
            'income'               => ['Income', 'sale', 'Income'],
            'commercial sale'      => ['Commercial', 'sale', 'Commercial Sale'],
            'commercial lease'     => ['Commercial', 'lease', 'Commercial Lease'],
            'business opportunity' => ['Business', 'sale', 'Business Opportunity'],
            'vacant land'          => ['Vacant Land', 'sale', 'Vacant Land'],
        ];
    }

    public function test_an_unanswerable_type_or_missing_transaction_is_unknown(): void
    {
        $this->assertNull($this->build([V::PROPERTY_TYPE => 'Residential'])->propertyType);
        $this->assertNull($this->build([V::PROPERTY_TYPE => 'Business', V::LISTING_TRANSACTION_TYPE => 'lease'])->propertyType);
        $this->assertNull($this->build([])->propertyType);
    }

    public function test_booleans_are_the_canonical_answer_and_a_non_boolean_waterfront_is_unknown(): void
    {
        $yes = $this->build([V::PROPERTY_POOL => true, V::PROPERTY_GARAGE => true, 'property.waterfront' => true]);
        $this->assertTrue($yes->poolPrivate);
        $this->assertTrue($yes->garage);
        $this->assertTrue($yes->waterfront);

        $unknown = $this->build(['property.waterfront' => 'Y']);
        $this->assertNull($unknown->poolPrivate);
        $this->assertNull($unknown->garage);
        $this->assertNull($unknown->waterfront);
    }

    public function test_absent_canonical_values_are_unknown(): void
    {
        $facts = $this->build([]);

        foreach (['latitude', 'longitude', 'city', 'stateOrProvince', 'postalCode', 'countyOrParish',
                  'listPrice', 'leaseFrequency', 'livingArea', 'yearBuilt', 'propertyType'] as $field) {
            $this->assertNull($facts->{$field}, $field);
        }
    }

    public function test_every_residual_value_reaches_the_facts_unchanged(): void
    {
        $residual = new ListingMatchResidualFacts(
            lotSizeSqft: 12680, buildingAreaTotal: 1500.5, propertySubType: 'Townhouse',
            view: true, waterView: false,
            associationFee: '350.00', associationFeeFrequency: 'Monthly', association: true, taxAnnualAmount: '2692.80', cdd: false,
            newConstruction: true, petsAllowed: 'Cats OK', communityFeatures: ['Pool'], associationAmenities: 'Gym',
            greenEnergyEfficient: ['Windows'], greenBuildingVerificationType: [], leaseTerm: '12 Months',
            daysOnMarket: 4, floodZoneStated: true, schoolsListed: true,
        );

        $facts = CanonicalListingMatchFactsBuilder::build($this->canonical([]), $residual);

        foreach (self::residualFields() as $field) {
            $this->assertSame($residual->{$field}, $facts->{$field}, $field);
        }
    }

    public function test_a_listing_without_an_mls_native_identity_gets_no_facts(): void
    {
        $byo = new CanonicalListing('seller_agent', 42, [V::LISTING_LIST_PRICE => 100000.0]);

        $this->assertNull(CanonicalListingMatchFactsBuilder::build($byo, ListingMatchResidualFacts::unknown()));
    }

    public function test_origin_covers_exactly_the_facts_fields_and_partitions_canonical_from_residual(): void
    {
        $factFields = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new ReflectionClass(ListingMatchFacts::class))->getConstructor()->getParameters()
        );
        $origin = CanonicalListingMatchFactsBuilder::ORIGIN;

        $this->assertCount(35, $factFields);
        $this->assertSame($factFields, array_keys($origin), 'ORIGIN names every ListingMatchFacts field once, in order');

        $residual = array_keys(array_filter($origin, static fn ($o) => $o === CanonicalListingMatchFactsBuilder::ORIGIN_RESIDUAL));
        $identity = array_keys(array_filter($origin, static fn ($o) => $o === CanonicalListingMatchFactsBuilder::ORIGIN_IDENTITY));
        $canonical = array_diff_key($origin, array_flip(array_merge($residual, $identity)));

        $this->assertSame(self::residualFields(), $residual, 'the residual entries are exactly the residual object\'s fields');
        $this->assertSame(['listingKey'], $identity);
        $this->assertCount(14, $canonical);

        foreach ($canonical as $field => $keys) {
            foreach ((array) $keys as $key) {
                $this->assertTrue(V::isDeclared($key), "{$field}: {$key} must be a declared canonical key");
            }
        }
    }

    /** @return list<string> */
    private static function residualFields(): array
    {
        return array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new ReflectionClass(ListingMatchResidualFacts::class))->getConstructor()->getParameters()
        );
    }

    private function canonical(array $fields): CanonicalListing
    {
        return new CanonicalListing(V::MLS_LISTING_TYPE, 1, $fields, [], MlsProvider::current(), 'KEY-1');
    }

    private function build(array $fields): ListingMatchFacts
    {
        $facts = CanonicalListingMatchFactsBuilder::build($this->canonical($fields), ListingMatchResidualFacts::unknown());
        $this->assertNotNull($facts);

        return $facts;
    }
}
