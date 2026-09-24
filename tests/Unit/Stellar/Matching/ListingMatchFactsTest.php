<?php

namespace Tests\Unit\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeListingMatchFactsBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\Matching\ListingMatchFacts;
use Tests\TestCase;

/**
 * P1-A — ListingMatchFacts is the match engine's input, and the Bridge facts
 * builder hands over exactly the values the engine used to read off the row.
 *
 * Parity across the whole engine is the P1-A0 baseline's job; this pins the seam.
 */
class ListingMatchFactsTest extends TestCase
{
    private function listing(array $attrs = [], array $raw = []): BridgeProperty
    {
        $p = new BridgeProperty();
        foreach ($attrs as $k => $v) {
            $p->{$k} = $v;
        }
        $p->raw_json = json_encode($raw);

        return $p;
    }

    private function criteria(array $extra = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Commercial Sale'],
            'is_55_plus_eligible' => false,
        ], $extra));
    }

    private function richListing(): BridgeProperty
    {
        return $this->listing([
            'id'                  => 7,
            'listing_key'         => 'KEY-FACTS',
            'list_price'          => 525000,
            'latitude'            => 27.7676,
            'longitude'           => -82.6403,
            'city'                => 'St Petersburg',
            'state_or_province'   => 'FL',
            'postal_code'         => '33701',
            'county_or_parish'    => 'Pinellas',
            'living_area'         => 2400,
            'lot_size_sqft'       => 9000,
            'year_built'          => 1999,
            'property_type'       => 'Commercial Sale',
            'property_sub_type'   => 'Office',
            'pool_private_yn'     => false,
            'garage_yn'           => true,
            'waterfront_yn'       => null,
            'view_yn'             => true,
            'water_view_yn'       => false,
            'association_fee'     => 2400,
            'association_yn'      => true,
            'tax_annual_amount'   => 6000,
            'cdd_yn'              => null,
            'new_construction_yn' => false,
            'pets_allowed'        => 'Yes',
        ], [
            'LeaseAmountFrequency'          => 'Monthly',
            'AssociationFeeFrequency'       => 'Annually',
            'BuildingAreaTotal'             => '3100',
            'CommunityFeatures'             => ['Pool', 'Fitness Center'],
            'AssociationAmenities'          => 'Clubhouse',
            'GreenEnergyEfficient'          => ['Windows'],
            'GreenBuildingVerificationType' => [],
            'LeaseTerm'                     => '24 Months',
            'DaysOnMarket'                  => 75,
            'STELLAR_FloodZoneCode'         => 'AE',
            'HighSchool'                    => 'Central',
        ]);
    }

    public function test_the_bridge_builder_hands_over_the_values_the_engine_read(): void
    {
        $f = BridgeListingMatchFactsBuilder::build($this->richListing());

        $this->assertSame('KEY-FACTS', $f->listingKey);
        // Decimal columns stay the model's decimal strings; nothing is re-typed.
        $this->assertSame('525000.00', $f->listPrice);
        $this->assertSame('27.7676000', $f->latitude);
        $this->assertSame('2400.00', $f->associationFee);
        $this->assertSame(9000, $f->lotSizeSqft);
        $this->assertSame(1999, $f->yearBuilt);
        $this->assertSame(2400, $f->livingArea);
        $this->assertSame(3100.0, $f->buildingAreaTotal);
        $this->assertSame('Monthly', $f->leaseFrequency);
        $this->assertSame('Annually', $f->associationFeeFrequency);
        $this->assertTrue($f->garage);
        $this->assertFalse($f->poolPrivate);
        $this->assertNull($f->waterfront);
        $this->assertNull($f->cdd);
        // Lists and the lease term are carried as stated; the rules interpret them.
        $this->assertSame(['Pool', 'Fitness Center'], $f->communityFeatures);
        $this->assertSame('Clubhouse', $f->associationAmenities);
        $this->assertSame([], $f->greenBuildingVerificationType);
        $this->assertSame('24 Months', $f->leaseTerm);
        $this->assertSame(75, $f->daysOnMarket);
        $this->assertTrue($f->floodZoneStated);
        $this->assertTrue($f->schoolsListed);
    }

    public function test_an_absent_or_unreadable_record_yields_empty_feed_facts(): void
    {
        $listing = $this->listing(['id' => 9, 'listing_key' => null]);
        $listing->raw_json = '{not json';

        $f = BridgeListingMatchFactsBuilder::build($listing);

        $this->assertSame('9', $f->listingKey, 'falls back to the row id, as the result always did');
        $this->assertNull($f->leaseFrequency);
        $this->assertNull($f->buildingAreaTotal);
        $this->assertNull($f->leaseTerm);
        $this->assertNull($f->daysOnMarket);
        $this->assertFalse($f->floodZoneStated);
        $this->assertFalse($f->schoolsListed);
    }

    public function test_scoring_facts_needs_no_bridge_row_and_agrees_with_scoring_the_row(): void
    {
        $scorer   = new BuyerMatchScorer();
        $criteria = $this->criteria([
            'ideal_price'                   => 500000,
            'min_sqft'                      => 2000,
            'max_sqft'                      => 3000,
            'wants_garage'                  => true,
            'max_monthly_total_burden'      => 1000,
            'community_feature_keywords'    => ['pool', 'clubhouse'],
            'wants_energy_efficient'        => true,
            'preferred_lease_terms'         => ['2 Years'],
            'preferred_cities'              => ['St Petersburg'],
        ]);

        $listing = $this->richListing();
        $fromRow = $scorer->score($listing, $criteria);

        // The same facts, written by hand: no model, no raw record.
        $handFacts = new ListingMatchFacts(...(array) BridgeListingMatchFactsBuilder::build($listing));
        $fromFacts = $scorer->scoreFacts($handFacts, $criteria);

        $this->assertSame($fromRow->categoryScores, $fromFacts->categoryScores);
        $this->assertSame($fromRow->totalScore, $fromFacts->totalScore);
        $this->assertSame($fromRow->importantPlaceMatches, $fromFacts->importantPlaceMatches);
        $this->assertEquals(BridgeListingMatchFactsBuilder::build($listing), $fromRow->facts);
    }

    public function test_explanations_are_the_same_whether_the_result_carries_facts_or_not(): void
    {
        $criteria = $this->criteria(['max_price' => 400000, 'wants_garage' => true, 'max_monthly_hoa' => 100]);
        $listing  = $this->richListing();
        $builder  = new BuyerMatchResultBuilder();

        $scored = (new BuyerMatchScorer())->score($listing, $criteria);
        $byHand = new BuyerMatchResult($scored->listingKey, $scored->totalScore, $scored->categoryScores, $listing);
        $this->assertNull($byHand->facts);

        $a = $builder->buildDetailed($scored, $criteria);
        $b = $builder->buildDetailed($byHand, $criteria);

        foreach (['whyThisMatches', 'tradeoffs', 'cautionFlags', 'missingData', 'whyNot', 'confidence', 'recommendations'] as $block) {
            $this->assertSame($a->{$block}, $b->{$block}, $block);
        }
        $this->assertEquals($a->facts, $b->facts);
    }
}
