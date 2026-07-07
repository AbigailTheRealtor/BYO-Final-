<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\MatchCheck\MatchReport;
use App\Services\Stellar\MatchCheck\MatchReportFactory;
use Tests\TestCase;

/**
 * Phase 4 · git-C13b — MatchReportFactory (F3 projection).
 *
 * Pure projection of a buildDetailed() BuyerMatchResult into a MatchReport: field-for-field mapping,
 * null-array coalescing, nullable confidence pass-through, injected generatedAt (never now() inside),
 * null narrative, and the deliberate drop of cautionFlags. No flag, no I/O.
 */
class MatchReportFactoryTest extends TestCase
{
    private const GENERATED_AT = '2026-07-07T12:00:00+00:00';

    private function factory(): MatchReportFactory
    {
        return new MatchReportFactory();
    }

    private function listing(array $attrs = []): BridgeProperty
    {
        return new BridgeProperty(array_merge([
            'listing_key' => 'KEY-1',
            // Restricted source data the report must NEVER carry (the factory never reads $listing raw).
            'raw_json'    => json_encode(['PublicRemarks' => 'RESTRICTED remarks', 'ListAgentKey' => 'RESTRICTED']),
        ], $attrs));
    }

    /** A BuyerMatchResult in the post-buildDetailed() shape (all F3 blocks populated). */
    private function detailed(array $overrides = []): BuyerMatchResult
    {
        return new BuyerMatchResult(
            listingKey: $overrides['listingKey'] ?? 'KEY-1',
            totalScore: $overrides['totalScore'] ?? 82,
            categoryScores: $overrides['categoryScores'] ?? ['location' => 24, 'price' => 18],
            listing: $this->listing(),
            whyThisMatches: $overrides['whyThisMatches'] ?? [['dimension' => 'location', 'label' => 'In Tampa']],
            tradeoffs: $overrides['tradeoffs'] ?? [['note' => 'slightly over budget']],
            cautionFlags: $overrides['cautionFlags'] ?? [['flag' => 'stale_listing']],
            missingData: $overrides['missingData'] ?? [['field' => 'year_built']],
            whyNot: $overrides['whyNot'] ?? [['dimension' => 'size', 'label' => 'smaller than ideal']],
            confidence: $overrides['confidence'] ?? ['level' => 'high', 'coverage' => 0.9],
            recommendations: $overrides['recommendations'] ?? [['type' => 'adjust_price']],
        );
    }

    /** @test */
    public function it_projects_every_block_field_for_field(): void
    {
        $report = $this->factory()->fromDetailed($this->detailed(), 7, 'buyer', 'bridge', self::GENERATED_AT);

        $this->assertInstanceOf(MatchReport::class, $report);
        $this->assertSame(7, $report->criteriaId);
        $this->assertSame('buyer', $report->criteriaType);
        $this->assertSame('KEY-1', $report->listingKey);
        $this->assertSame('bridge', $report->source);
        $this->assertSame(82, $report->totalScore);
        $this->assertSame(['location' => 24, 'price' => 18], $report->categoryScores);
        $this->assertSame([['dimension' => 'location', 'label' => 'In Tampa']], $report->whyThisMatches);
        $this->assertSame([['dimension' => 'size', 'label' => 'smaller than ideal']], $report->whyNot);
        $this->assertSame([['note' => 'slightly over budget']], $report->tradeoffs);
        $this->assertSame([['field' => 'year_built']], $report->missingData);
        $this->assertSame(['level' => 'high', 'coverage' => 0.9], $report->confidence);
        $this->assertSame([['type' => 'adjust_price']], $report->recommendations);
        $this->assertSame(self::GENERATED_AT, $report->generatedAt);
        $this->assertNull($report->narrative);
    }

    /** @test */
    public function caution_flags_are_dropped_and_never_surface(): void
    {
        $report = $this->factory()->fromDetailed($this->detailed(), 7, 'buyer', 'bridge', self::GENERATED_AT);

        // MatchReport has no cautionFlags field / key.
        $this->assertArrayNotHasKey('caution_flags', $report->toArray());
        $this->assertStringNotContainsString('stale_listing', json_encode($report->toArray()));
    }

    /** @test */
    public function null_blocks_coalesce_to_empty_arrays_but_confidence_stays_null(): void
    {
        // The pre-buildDetailed() nullable slots default to null; the factory guarantees non-null
        // arrays for whyNot / recommendations while confidence remains a nullable structured block.
        // Built directly (not via the overrides helper) so the explicit nulls are not coalesced away.
        $detailed = new BuyerMatchResult(
            listingKey: 'KEY-1',
            totalScore: 40,
            categoryScores: [],
            listing: $this->listing(),
            whyNot: null,
            confidence: null,
            recommendations: null,
        );

        $report = $this->factory()->fromDetailed($detailed, 3, 'tenant', 'bridge', self::GENERATED_AT);

        $this->assertSame([], $report->whyNot);
        $this->assertSame([], $report->recommendations);
        $this->assertNull($report->confidence);
    }

    /** @test */
    public function generated_at_is_exactly_the_injected_value_no_internal_now(): void
    {
        $injected = '1999-01-01T00:00:00+00:00';

        $report = $this->factory()->fromDetailed($this->detailed(), 7, 'buyer', 'bridge', $injected);

        $this->assertSame($injected, $report->generatedAt);
    }

    /** @test */
    public function non_residential_tenant_type_passes_through(): void
    {
        $report = $this->factory()->fromDetailed($this->detailed(), 11, 'tenant_offer', 'bridge', self::GENERATED_AT);

        $this->assertSame(11, $report->criteriaId);
        $this->assertSame('tenant_offer', $report->criteriaType);
    }

    /** @test */
    public function report_carries_no_restricted_source_keys(): void
    {
        // The factory builds only from explanation blocks + scalars — never the listing's raw_json.
        $report  = $this->factory()->fromDetailed($this->detailed(), 7, 'buyer', 'bridge', self::GENERATED_AT);
        $encoded = json_encode($report->toArray());

        $this->assertStringNotContainsString('raw_json', $encoded);
        $this->assertStringNotContainsString('PublicRemarks', $encoded);
        $this->assertStringNotContainsString('RESTRICTED', $encoded);
    }
}
