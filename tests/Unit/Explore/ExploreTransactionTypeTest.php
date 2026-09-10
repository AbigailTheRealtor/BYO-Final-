<?php

namespace Tests\Unit\Explore;

use App\Services\Explore\ExploreTransactionType;
use Tests\TestCase;

/**
 * The sale/rent rule — the contract that makes Explore support both markets
 * from its first version rather than being a Seller-only surface with a rental
 * bolt-on.
 */
class ExploreTransactionTypeTest extends TestCase
{
    /** @test */
    public function every_sale_property_type_in_this_dataset_classifies_as_sale(): void
    {
        foreach (['Residential', 'Income', 'Commercial Sale', 'Vacant Land', 'Business Opportunity'] as $type) {
            $this->assertSame(
                ExploreTransactionType::SALE,
                ExploreTransactionType::fromPropertyType($type),
                "{$type} must be FOR SALE"
            );
        }
    }

    /** @test */
    public function every_lease_property_type_in_this_dataset_classifies_as_rent(): void
    {
        foreach (['Residential Lease', 'Commercial Lease'] as $type) {
            $this->assertSame(
                ExploreTransactionType::RENT,
                ExploreTransactionType::fromPropertyType($type),
                "{$type} must be FOR RENT"
            );
        }
    }

    /**
     * Business Opportunity is a supported Seller property type on this platform
     * (PropertyTypeVocabulary maps it to 'Business', and it has its own Seller
     * services catalogue). It carries 199 live records — the third largest
     * category in the cache — and must not be dropped from Explore.
     *
     * @test
     */
    public function business_opportunity_is_not_omitted(): void
    {
        $this->assertContains('Business Opportunity', ExploreTransactionType::SALE->propertyTypes());
        $this->assertContains('Business Opportunity', ExploreTransactionType::allPropertyTypes());
    }

    /**
     * The reason this is not PropertyTypeVocabulary.
     *
     * A substring rule keyed on 'commercial' or 'residential' puts a lease
     * under FOR SALE, which prints a monthly rent as a purchase price — or a
     * purchase price as a rent. One word apart, catastrophically different.
     *
     * @test
     */
    public function classification_is_exact_and_never_substring(): void
    {
        $this->assertSame(ExploreTransactionType::SALE, ExploreTransactionType::fromPropertyType('Commercial Sale'));
        $this->assertSame(ExploreTransactionType::RENT, ExploreTransactionType::fromPropertyType('Commercial Lease'));
        $this->assertSame(ExploreTransactionType::SALE, ExploreTransactionType::fromPropertyType('Residential'));
        $this->assertSame(ExploreTransactionType::RENT, ExploreTransactionType::fromPropertyType('Residential Lease'));
    }

    /** @test */
    public function an_unclassified_property_type_belongs_to_neither_list(): void
    {
        foreach (['', '   ', 'Farm', 'Residential Income Lease', 'residential lease', null] as $type) {
            $this->assertNull(
                ExploreTransactionType::fromPropertyType($type),
                var_export($type, true) . ' must not be classified'
            );
        }
    }

    /** @test */
    public function consumer_labels_never_say_seller_or_landlord(): void
    {
        $this->assertSame('For Sale', ExploreTransactionType::SALE->consumerLabel());
        $this->assertSame('For Rent', ExploreTransactionType::RENT->consumerLabel());

        foreach (ExploreTransactionType::cases() as $case) {
            $this->assertStringNotContainsStringIgnoringCase('seller', $case->consumerLabel());
            $this->assertStringNotContainsStringIgnoringCase('landlord', $case->consumerLabel());
        }
    }

    /** @test */
    public function internal_roles_stay_internal_but_are_correct(): void
    {
        $this->assertSame('seller', ExploreTransactionType::SALE->internalRole());
        $this->assertSame('landlord', ExploreTransactionType::RENT->internalRole());
    }

    /** @test */
    public function the_filter_parser_accepts_only_the_two_transaction_types(): void
    {
        $this->assertSame(ExploreTransactionType::SALE, ExploreTransactionType::tryFromFilter('sale'));
        $this->assertSame(ExploreTransactionType::RENT, ExploreTransactionType::tryFromFilter(' RENT '));
        $this->assertNull(ExploreTransactionType::tryFromFilter('all'));
        $this->assertNull(ExploreTransactionType::tryFromFilter('seller'));
        $this->assertNull(ExploreTransactionType::tryFromFilter(null));
    }
}
