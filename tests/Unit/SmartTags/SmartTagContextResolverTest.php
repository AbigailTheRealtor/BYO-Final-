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

    /**
     * The live Stellar feed sends the RESO values `Residential Income` and `Land`; the older
     * `Income` / `Vacant Land` spellings stay for rows already stored under them. Before the
     * aliases the two real listings of those types (1252, 1254) had no context and were
     * re-planned by every stale-only run.
     *
     * @test
     */
    public function both_spellings_of_income_and_land_resolve_to_one_context_each(): void
    {
        $this->assertSame(SmartTagContext::IncomeSale, SmartTagContextResolver::forBridge('Income'));
        $this->assertSame(SmartTagContext::IncomeSale, SmartTagContextResolver::forBridge('Residential Income'));
        $this->assertSame(SmartTagContext::IncomeSale, SmartTagContextResolver::forBridge(' Residential Income '));
        $this->assertSame(SmartTagContext::LandSale, SmartTagContextResolver::forBridge('Vacant Land'));
        $this->assertSame(SmartTagContext::LandSale, SmartTagContextResolver::forBridge('Land'));

        // Bridge-only: no native or seeker vocabulary gains a spelling.
        $this->assertNull(SmartTagContextResolver::forListingType(SmartTagListingType::SellerAgent, 'Land'));
        $this->assertNull(SmartTagContextResolver::forListingType(SmartTagListingType::SellerAgent, 'Residential Income'));
        $this->assertNull(SmartTagContextResolver::forSeeker('buyer', 'Land'));
        $this->assertNull(SmartTagContextResolver::forSeeker('buyer', 'Residential Income'));
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
        // Exact, never fuzzy: the RESO aliases below admit their exact spellings only.
        foreach ([null, '', 'residential', 'residential income', 'land', 'Lands', 'Farm', 'Commercial', 'Lease', 'Residential Leases'] as $value) {
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
