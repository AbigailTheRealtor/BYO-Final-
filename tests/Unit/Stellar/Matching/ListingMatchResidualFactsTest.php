<?php

namespace Tests\Unit\Stellar\Matching;

use App\Services\Stellar\Matching\CanonicalListingMatchFactsBuilder;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\ListingMatchResidualFacts;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * P1-B — the residual is exactly the twenty facts a CanonicalListing does not carry,
 * with the same names and types as the ListingMatchFacts properties they fill.
 */
class ListingMatchResidualFactsTest extends TestCase
{
    private const EXPECTED = [
        // matching-owned
        'daysOnMarket', 'floodZoneStated', 'schoolsListed',
        // provider residuals
        'lotSizeSqft', 'buildingAreaTotal', 'propertySubType', 'view', 'waterView',
        'associationFee', 'associationFeeFrequency', 'association', 'taxAnnualAmount', 'cdd',
        'newConstruction', 'petsAllowed', 'communityFeatures', 'associationAmenities',
        'greenEnergyEfficient', 'greenBuildingVerificationType', 'leaseTerm',
    ];

    public function test_it_carries_exactly_the_twenty_approved_fields(): void
    {
        $fields = array_keys(self::parameters(ListingMatchResidualFacts::class));

        $this->assertCount(20, $fields);
        $this->assertEqualsCanonicalizing(self::EXPECTED, $fields);
    }

    public function test_each_field_has_the_name_and_type_of_the_facts_property_it_fills(): void
    {
        $facts = self::parameters(ListingMatchFacts::class);

        foreach (self::parameters(ListingMatchResidualFacts::class) as $name => $type) {
            $this->assertArrayHasKey($name, $facts, "{$name} is not a ListingMatchFacts field");
            $this->assertSame($facts[$name], $type, "{$name} must have the ListingMatchFacts type");
        }
    }

    public function test_no_residual_field_is_also_a_canonical_one(): void
    {
        foreach (array_keys(self::parameters(ListingMatchResidualFacts::class)) as $name) {
            $this->assertSame(CanonicalListingMatchFactsBuilder::ORIGIN_RESIDUAL, CanonicalListingMatchFactsBuilder::ORIGIN[$name], $name);
        }
    }

    public function test_unknown_is_all_unknown(): void
    {
        $unknown = ListingMatchResidualFacts::unknown();

        foreach (array_keys(self::parameters(ListingMatchResidualFacts::class)) as $name) {
            in_array($name, ['floodZoneStated', 'schoolsListed'], true)
                ? $this->assertFalse($unknown->{$name}, $name)
                : $this->assertNull($unknown->{$name}, $name);
        }
    }

    public function test_it_is_a_flat_readonly_object_with_no_extras(): void
    {
        $class = new ReflectionClass(ListingMatchResidualFacts::class);

        $this->assertTrue($class->isFinal());
        foreach ($class->getProperties() as $property) {
            $this->assertTrue($property->isPromoted() && $property->isReadOnly() && $property->isPublic(), $property->getName());
        }
        $this->assertCount(20, $class->getProperties());
    }

    /** @return array<string,string> parameter name => declared type */
    private static function parameters(string $class): array
    {
        $out = [];
        foreach ((new ReflectionClass($class))->getConstructor()->getParameters() as $p) {
            $type = $p->getType();
            $out[$p->getName()] = $type instanceof ReflectionNamedType
                ? ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName()
                : (string) $type;
        }

        return $out;
    }
}
