<?php

namespace Tests\Unit\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\Matching\ListingMatchFacts;
use Tests\TestCase;

/**
 * A zero criteria bound is never used as a divisor.
 *
 * BuyerCriteriaPayload accepts `max_sqft`, `max_lot_sqft` and `ideal_price` of 0 when it is
 * built straight from an array (the DB loaders drop non-positive values first). Three
 * type-specific scorers divided by the max bound and BuyerMatchResultBuilder::buildTradeoffs()
 * divided by the ideal price, so those payloads raised DivisionByZeroError — P1-B2's five
 * ERROR_PARITY cases. The guarded siblings (the same methods' min side, rangeScore(), the
 * scorer's price proximity) already skipped an unusable denominator; these now do the same.
 *
 * Every positive-bound expectation below is what the code produced before the fix.
 */
class ZeroCriteriaBoundsTest extends TestCase
{
    // ── Income: building area against max_sqft ──────────────────────────────

    public function test_income_with_a_zero_max_sqft_does_not_throw_and_scores_as_close_to_range(): void
    {
        $this->assertSame(5, $this->nonResidential('Income', ['max_sqft' => 0], buildingArea: 1000.0));
    }

    public function test_income_positive_null_and_in_range_bounds_are_unchanged(): void
    {
        $this->assertSame(10, $this->nonResidential('Income', [], buildingArea: 1000.0), 'no bound');
        $this->assertSame(10, $this->nonResidential('Income', ['max_sqft' => 2000], buildingArea: 1000.0), 'in range');
        $this->assertSame(5, $this->nonResidential('Income', ['max_sqft' => 900], buildingArea: 1000.0), 'within 20%');
        $this->assertSame(0, $this->nonResidential('Income', ['max_sqft' => 500], buildingArea: 1000.0), 'far over');
    }

    public function test_income_with_a_negative_max_sqft_scores_as_it_did_before(): void
    {
        // Characterization, not a rule: a negative bound was divided by and gave a negative
        // ratio (≤ 0.20 → partial). Skipping the division gives the same partial score.
        $this->assertSame(5, $this->nonResidential('Income', ['max_sqft' => -100], buildingArea: 1000.0));
    }

    // ── Commercial Sale: building area against max_sqft (lot unconstrained → +5) ──

    public function test_commercial_sale_with_a_zero_max_sqft_does_not_throw_and_scores_as_close_to_range(): void
    {
        $this->assertSame(8, $this->nonResidential('Commercial Sale', ['max_sqft' => 0], buildingArea: 1000.0));
    }

    public function test_commercial_sale_positive_null_and_in_range_bounds_are_unchanged(): void
    {
        $this->assertSame(10, $this->nonResidential('Commercial Sale', [], buildingArea: 1000.0), 'no bound');
        $this->assertSame(10, $this->nonResidential('Commercial Sale', ['max_sqft' => 2000], buildingArea: 1000.0), 'in range');
        $this->assertSame(8, $this->nonResidential('Commercial Sale', ['max_sqft' => 900], buildingArea: 1000.0), 'within 20%');
        $this->assertSame(5, $this->nonResidential('Commercial Sale', ['max_sqft' => 500], buildingArea: 1000.0), 'far over');
    }

    // ── Vacant Land: lot size against max_lot_sqft ──────────────────────────

    public function test_vacant_land_with_a_zero_max_lot_sqft_does_not_throw_and_scores_as_close_to_range(): void
    {
        $this->assertSame(5, $this->nonResidential('Vacant Land', ['max_lot_sqft' => 0], lotSqft: 5000));
    }

    public function test_vacant_land_positive_null_and_in_range_bounds_are_unchanged(): void
    {
        $this->assertSame(10, $this->nonResidential('Vacant Land', [], lotSqft: 5000), 'no bound');
        $this->assertSame(10, $this->nonResidential('Vacant Land', ['max_lot_sqft' => 8000], lotSqft: 5000), 'in range');
        $this->assertSame(5, $this->nonResidential('Vacant Land', ['max_lot_sqft' => 4500], lotSqft: 5000), 'within 20%');
        $this->assertSame(0, $this->nonResidential('Vacant Land', ['max_lot_sqft' => 2000], lotSqft: 5000), 'far over');
    }

    public function test_commercial_sale_and_vacant_land_with_a_negative_max_score_as_they_did_before(): void
    {
        // Characterization, as for Income: the negative ratio was always ≤ 0.20 → partial.
        $this->assertSame(8, $this->nonResidential('Commercial Sale', ['max_sqft' => -100], buildingArea: 1000.0));
        $this->assertSame(5, $this->nonResidential('Vacant Land', ['max_lot_sqft' => -100], lotSqft: 5000));
    }

    // ── Result builder: ideal_price ─────────────────────────────────────────

    public function test_result_builder_with_a_zero_ideal_price_does_not_throw_and_states_no_price_tradeoff(): void
    {
        [$batch, $detailed] = $this->built(['ideal_price' => 0, 'max_price' => 500000], listPrice: '300000.00');

        $this->assertSame([], $this->priceTradeoffs($batch));
        $this->assertNotNull($detailed->whyNot);
    }

    public function test_result_builder_positive_ideal_price_tradeoff_is_unchanged(): void
    {
        [$batch] = $this->built(['ideal_price' => 280000, 'max_price' => 500000], listPrice: '300000.00');

        $this->assertSame([[
            'dimension'   => 'price',
            'label'       => 'Price is 7% above your ideal — at the upper end of your range',
            'fields_used' => ['list_price'],
            'deviation'   => '7%_above_ideal',
        ]], $this->priceTradeoffs($batch));
    }

    public function test_result_builder_null_ideal_price_still_falls_back_to_max_price(): void
    {
        [$batch] = $this->built(['max_price' => 500000], listPrice: '480000.00');

        $this->assertSame([[
            'dimension'   => 'price',
            'label'       => 'Price is near the top of your budget',
            'fields_used' => ['list_price'],
            'deviation'   => 'near_max_price',
        ]], $this->priceTradeoffs($batch));
    }

    public function test_a_negative_ideal_price_is_still_rejected_by_the_payload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->payload('Residential', ['ideal_price' => -1]);
    }

    // ── The original reproduction, through a direct-array payload ───────────

    public function test_a_direct_array_payload_with_zero_bounds_reaches_every_site_without_throwing(): void
    {
        $payload = new BuyerCriteriaPayload([
            'property_types'      => ['Income', 'Commercial Sale', 'Vacant Land'],
            'is_55_plus_eligible' => false,
            'max_sqft'            => 0,
            'max_lot_sqft'        => 0,
            'ideal_price'         => 0,
            'max_price'           => 500000,
        ]);

        $this->assertSame(0, $payload->maxSqft);
        $this->assertSame(0, $payload->maxLotSqft);
        $this->assertSame(0, $payload->idealPrice);

        foreach (['Income', 'Commercial Sale', 'Vacant Land'] as $type) {
            $facts = $this->facts($type, listPrice: '300000.00', buildingArea: 1000.0, lotSqft: 5000);
            $score = (new BuyerMatchScorer())->scoreFacts($facts, $payload);
            $this->assertIsInt($score->totalScore, $type);

            $builder = new BuyerMatchResultBuilder();
            $builder->build($this->result($facts, $score), $payload);
            $builder->buildDetailed($this->result($facts, $score), $payload);
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function nonResidential(string $type, array $criteria, ?float $buildingArea = null, ?int $lotSqft = null): int
    {
        $payload = $this->payload($type, $criteria);
        $score   = (new BuyerMatchScorer())->scoreFacts($this->facts($type, buildingArea: $buildingArea, lotSqft: $lotSqft), $payload);

        return $score->categoryScores['non_residential'];
    }

    /** @return array{0: BuyerMatchResult, 1: BuyerMatchResult} */
    private function built(array $criteria, string $listPrice): array
    {
        $payload = $this->payload('Residential', $criteria);
        $facts   = $this->facts('Residential', listPrice: $listPrice);
        $score   = (new BuyerMatchScorer())->scoreFacts($facts, $payload);
        $builder = new BuyerMatchResultBuilder();

        return [
            $builder->build($this->result($facts, $score), $payload),
            $builder->buildDetailed($this->result($facts, $score), $payload),
        ];
    }

    private function priceTradeoffs(BuyerMatchResult $result): array
    {
        return array_values(array_filter($result->tradeoffs, fn (array $t) => $t['dimension'] === 'price'));
    }

    private function result(ListingMatchFacts $facts, $score): BuyerMatchResult
    {
        $r = new BuyerMatchResult($facts->listingKey, $score->totalScore, $score->categoryScores, new BridgeProperty());
        $r->importantPlaceMatches = $score->importantPlaceMatches;
        $r->seekerFeatureMatch    = $score->seekerFeatureMatch;
        $r->facts                 = $facts;

        return $r;
    }

    private function payload(string $type, array $criteria): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(['property_types' => [$type], 'is_55_plus_eligible' => false] + $criteria);
    }

    private function facts(string $type, ?string $listPrice = '300000.00', ?float $buildingArea = null, ?int $lotSqft = null): ListingMatchFacts
    {
        return new ListingMatchFacts(
            listingKey: 'ZERO-BOUNDS-1',
            latitude: '27.7700000',
            longitude: '-82.6400000',
            city: 'St Petersburg',
            stateOrProvince: 'FL',
            postalCode: '33701',
            countyOrParish: 'Pinellas',
            listPrice: $listPrice,
            leaseFrequency: null,
            livingArea: null,
            lotSizeSqft: $lotSqft,
            yearBuilt: 2000,
            buildingAreaTotal: $buildingArea,
            propertyType: $type,
            propertySubType: null,
            poolPrivate: null,
            garage: null,
            waterfront: null,
            view: null,
            waterView: null,
            associationFee: null,
            associationFeeFrequency: null,
            association: null,
            taxAnnualAmount: null,
            cdd: null,
            newConstruction: null,
            petsAllowed: null,
            communityFeatures: null,
            associationAmenities: null,
            greenEnergyEfficient: null,
            greenBuildingVerificationType: null,
            leaseTerm: null,
            daysOnMarket: null,
            floodZoneStated: false,
            schoolsListed: false,
        );
    }
}
