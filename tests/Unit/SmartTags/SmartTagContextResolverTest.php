<?php

namespace Tests\Unit\SmartTags;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagListingType;
use PHPUnit\Framework\TestCase;

class SmartTagContextResolverTest extends TestCase
{
    /** @test */
    public function bridge_property_types_resolve_exactly(): void
    {
        $this->assertSame(SmartTagContext::ResidentialSale, SmartTagContextResolver::forBridge('Residential'));
        $this->assertSame(SmartTagContext::IncomeSale, SmartTagContextResolver::forBridge('Income'));
        $this->assertSame(SmartTagContext::CommercialSale, SmartTagContextResolver::forBridge('Commercial Sale'));
        $this->assertSame(SmartTagContext::BusinessSale, SmartTagContextResolver::forBridge('Business Opportunity'));
        $this->assertSame(SmartTagContext::LandSale, SmartTagContextResolver::forBridge('Vacant Land'));
        $this->assertSame(SmartTagContext::ResidentialLease, SmartTagContextResolver::forBridge('Residential Lease'));
        $this->assertSame(SmartTagContext::CommercialLease, SmartTagContextResolver::forBridge(' Commercial Lease '));
    }

    /** @test */
    public function native_transaction_comes_from_the_role_not_the_string(): void
    {
        $this->assertSame(SmartTagContext::ResidentialSale, SmartTagContextResolver::forListingType(SmartTagListingType::SellerAgent, 'Residential'));
        $this->assertSame(SmartTagContext::BusinessSale, SmartTagContextResolver::forListingType(SmartTagListingType::SellerAgent, 'Business'));
        $this->assertSame(SmartTagContext::ResidentialLease, SmartTagContextResolver::forListingType(SmartTagListingType::LandlordAgent, 'Residential Property'));
        $this->assertSame(SmartTagContext::CommercialLease, SmartTagContextResolver::forListingType(SmartTagListingType::LandlordAgent, 'Commercial Property'));

        // PropertyTypeVocabulary::classifySource() reads "Residential Property" as a sale. This must not.
        $this->assertNull(SmartTagContextResolver::forListingType(SmartTagListingType::SellerAgent, 'Residential Property'));
        $this->assertNull(SmartTagContextResolver::forListingType(SmartTagListingType::LandlordAgent, 'Residential'));
    }

    /** @test */
    public function unknown_or_unsupported_property_types_fail_closed(): void
    {
        foreach ([null, '', 'residential', 'Residential Income', 'Farm', 'Commercial', 'Lease', 'Residential Leases'] as $value) {
            $this->assertNull(SmartTagContextResolver::forBridge($value), var_export($value, true));
        }

        $this->assertNull(SmartTagContextResolver::forListingType(SmartTagListingType::LandlordAgent, 'Income'));
        $this->assertNull(SmartTagContextResolver::forListingType(SmartTagListingType::LandlordAgent, 'Vacant Land'));
        $this->assertNull(SmartTagContextResolver::forSeeker('agent', 'Residential'));
    }

    /** @test */
    public function seekers_use_the_same_contexts_as_the_listings_they_search(): void
    {
        $this->assertSame(SmartTagContext::IncomeSale, SmartTagContextResolver::forSeeker('buyer', 'Income'));
        $this->assertSame(SmartTagContext::CommercialLease, SmartTagContextResolver::forSeeker('tenant', 'Commercial Property'));
    }
}
