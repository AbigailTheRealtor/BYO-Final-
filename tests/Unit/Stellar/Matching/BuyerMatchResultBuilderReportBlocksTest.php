<?php

namespace Tests\Unit\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Tests\TestCase;

/**
 * Phase 4 · git-C10 (Plan-C6, F3) — BuyerMatchResultBuilder detailed report blocks.
 *
 * Verifies the three new blocks (whyNot / confidence / recommendations) and the buildDetailed()
 * composition, and — critically — that the live batch path (plain build()) is untouched: it does
 * NOT populate the new slots and its four existing blocks are byte-identical.
 */
class BuyerMatchResultBuilderReportBlocksTest extends TestCase
{
    private function builder(): BuyerMatchResultBuilder
    {
        return new BuyerMatchResultBuilder();
    }

    /** Build a BridgeProperty with arbitrary in-memory attributes (no DB). */
    private function listing(array $attrs = []): BridgeProperty
    {
        $p = new BridgeProperty();
        foreach ($attrs as $k => $v) {
            $p->{$k} = $v;
        }
        return $p;
    }

    private function fullListing(bool $geo = true): BridgeProperty
    {
        return $this->listing([
            'list_price'    => 400000,
            'living_area'   => 1800,
            'year_built'    => 2005,
            'lot_size_sqft' => 6000,
            'property_type' => 'Residential',
            'latitude'      => $geo ? 27.9 : null,
            'longitude'     => $geo ? -82.5 : null,
        ]);
    }

    private function criteria(array $extra = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $extra));
    }

    private function result(array $scores, BridgeProperty $listing): BuyerMatchResult
    {
        return new BuyerMatchResult('KEY-1', (int) array_sum($scores), $scores, $listing);
    }

    /** @test */
    public function build_detailed_populates_all_three_new_blocks_and_the_existing_ones(): void
    {
        $result = $this->result(['location' => 20, 'price' => 15], $this->fullListing());

        $returned = $this->builder()->buildDetailed($result, $this->criteria());

        // Same mutated instance is returned.
        $this->assertSame($result, $returned);

        // New blocks populated (no longer null).
        $this->assertIsArray($result->whyNot);
        $this->assertIsArray($result->confidence);
        $this->assertArrayHasKey('level', $result->confidence);
        $this->assertIsArray($result->recommendations);

        // Existing blocks still populated.
        $this->assertIsArray($result->whyThisMatches);
        $this->assertIsArray($result->tradeoffs);
        $this->assertIsArray($result->cautionFlags);
        $this->assertIsArray($result->missingData);
    }

    /** @test */
    public function why_not_includes_only_zero_scoring_dimensions(): void
    {
        $result = $this->result(
            ['location' => 0, 'price' => 15, 'size' => 0, 'amenities' => 3],
            $this->fullListing()
        );

        $this->builder()->buildDetailed($result, $this->criteria());

        $dims = array_column($result->whyNot, 'dimension');
        sort($dims);
        $this->assertSame(['location', 'size'], $dims);

        foreach ($result->whyNot as $entry) {
            $this->assertSame(0, $entry['score_contribution']);
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('fields_used', $entry);
        }
    }

    /** @test */
    public function why_not_is_empty_when_every_scored_dimension_is_positive(): void
    {
        $result = $this->result(['location' => 20, 'price' => 15, 'size' => 8], $this->fullListing());

        $this->builder()->buildDetailed($result, $this->criteria());

        $this->assertSame([], $result->whyNot);
    }

    /** @test */
    public function confidence_is_high_when_geo_precise_and_complete(): void
    {
        $result = $this->result(['price' => 10], $this->fullListing(geo: true));

        $this->builder()->buildDetailed($result, $this->criteria());

        $this->assertSame('high', $result->confidence['level']);
        $this->assertSame(1.0, $result->confidence['score']);
        $this->assertTrue($result->confidence['factors']['geo_precise']);
        $this->assertSame(1.0, $result->confidence['factors']['completeness']);
    }

    /** @test */
    public function confidence_applies_a_penalty_when_geo_is_missing(): void
    {
        $result = $this->result(['price' => 10], $this->fullListing(geo: false));

        $this->builder()->buildDetailed($result, $this->criteria());

        // Complete data (1.0) minus the fixed 0.2 geo penalty = 0.8.
        $this->assertSame(0.8, $result->confidence['score']);
        $this->assertFalse($result->confidence['factors']['geo_precise']);
        $this->assertSame(1.0, $result->confidence['factors']['completeness']);
    }

    /** @test */
    public function confidence_is_low_when_sparse_and_geo_missing(): void
    {
        // Only property_type present (1/5 = 0.2), no geo → 0.2 - 0.2 = 0.0 → low.
        $result = $this->result(['price' => 0], $this->listing(['property_type' => 'Residential']));

        $this->builder()->buildDetailed($result, $this->criteria());

        $this->assertSame('low', $result->confidence['level']);
        $this->assertSame(0.0, $result->confidence['score']);
        $this->assertSame(0.2, $result->confidence['factors']['completeness']);
    }

    /** @test */
    public function recommendations_widen_price_when_price_under_scored_and_above_ideal(): void
    {
        $listing = $this->fullListing();
        $listing->list_price = 600000;
        $result  = $this->result(['price' => 5, 'location' => 20], $listing);

        $this->builder()->buildDetailed($result, $this->criteria(['ideal_price' => 500000]));

        $types = array_column($result->recommendations, 'type');
        $this->assertContains('widen_price', $types);

        $widen = collect($result->recommendations)->firstWhere('type', 'widen_price');
        $this->assertSame('price', $widen['dimension']);
        $this->assertStringContainsString('100,000', $widen['label']);
    }

    /** @test */
    public function recommendations_consider_adjacent_area_when_location_zero(): void
    {
        $result = $this->result(['location' => 0, 'price' => 20], $this->fullListing());

        $this->builder()->buildDetailed($result, $this->criteria(['preferred_cities' => ['Tampa']]));

        $rec = collect($result->recommendations)->firstWhere('type', 'consider_adjacent_area');
        $this->assertNotNull($rec);
        $this->assertSame('location', $rec['dimension']);
        $this->assertStringContainsString('Tampa', $rec['label']);
    }

    /** @test */
    public function recommendations_empty_when_scores_are_strong(): void
    {
        // price at the proximity max (not under-scored) and location positive → no rules fire.
        $result = $this->result(['price' => 20, 'location' => 24], $this->fullListing());

        $this->builder()->buildDetailed($result, $this->criteria(['ideal_price' => 500000]));

        $this->assertSame([], $result->recommendations);
    }

    /** @test */
    public function plain_build_leaves_new_slots_null_and_existing_blocks_unchanged(): void
    {
        // Batch-path regression: build() must NOT populate the new slots, and its four existing
        // blocks must be identical to what buildDetailed() produces for the same input.
        $scores  = ['location' => 0, 'price' => 5, 'size' => 8];
        $criteria = $this->criteria(['ideal_price' => 500000, 'preferred_cities' => ['Tampa']]);

        $batch = $this->result($scores, $this->fullListing());
        $this->builder()->build($batch, $criteria);

        $detailed = $this->result($scores, $this->fullListing());
        $this->builder()->buildDetailed($detailed, $criteria);

        // build() leaves the git-C9 slots null (live path unaffected).
        $this->assertNull($batch->whyNot);
        $this->assertNull($batch->confidence);
        $this->assertNull($batch->recommendations);

        // The four existing blocks are byte-identical between build() and buildDetailed().
        $this->assertEquals($detailed->whyThisMatches, $batch->whyThisMatches);
        $this->assertEquals($detailed->tradeoffs, $batch->tradeoffs);
        $this->assertEquals($detailed->cautionFlags, $batch->cautionFlags);
        $this->assertEquals($detailed->missingData, $batch->missingData);
    }
}
