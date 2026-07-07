<?php

namespace App\Services\Stellar\MatchCheck;

use App\Services\Stellar\Matching\DTO\BuyerMatchResult;

/**
 * Pure projection: a decorated BuyerMatchResult → MatchReport (Phase 4 · git-C13b / Plan-C9 · F3).
 *
 * The final step of Match Check report production. It takes a BuyerMatchResult that has ALREADY been
 * through BuyerMatchResultBuilder::buildDetailed() (so its F3 blocks — whyThisMatches / whyNot /
 * tradeoffs / missingData / confidence / recommendations — are populated) and reshapes it into the
 * serializable MatchReport, adding only the criteria identity, the data source, and the INJECTED
 * generatedAt timestamp. narrative (F8) stays null; cautionFlags is intentionally dropped (it is not
 * a MatchReport field).
 *
 * PURE / SIDE-EFFECT-FREE BY DESIGN. It reads no feature flag, does no I/O, runs no scoring, and — per
 * the MatchReport contract — never calls now(): generatedAt is supplied by the caller (the
 * orchestrator's report step) so reports are deterministic and testable. It is a total function of its
 * arguments: the same inputs always yield the same report.
 */
final class MatchReportFactory
{
    /**
     * @param  BuyerMatchResult  $detailed      A result AFTER buildDetailed() — F3 blocks populated.
     * @param  int               $criteriaId    The scored criteria record id (preferredCriteria['id']).
     * @param  string            $criteriaType  'buyer'|'tenant'|'buyer_offer'|'tenant_offer'.
     * @param  string            $source        Data source, e.g. 'bridge'.
     * @param  string            $generatedAt   Injected ISO-8601 timestamp (never now() inside).
     */
    public function fromDetailed(
        BuyerMatchResult $detailed,
        int $criteriaId,
        string $criteriaType,
        string $source,
        string $generatedAt,
    ): MatchReport {
        return new MatchReport(
            criteriaId: $criteriaId,
            criteriaType: $criteriaType,
            listingKey: $detailed->listingKey,
            source: $source,
            totalScore: $detailed->totalScore,
            categoryScores: $detailed->categoryScores,
            whyThisMatches: $detailed->whyThisMatches,
            // whyNot / recommendations are nullable until buildDetailed() runs; coalesce so the report
            // always carries the non-null arrays its contract promises. confidence stays a nullable
            // structured block and passes through as-is.
            whyNot: $detailed->whyNot ?? [],
            tradeoffs: $detailed->tradeoffs,
            missingData: $detailed->missingData,
            confidence: $detailed->confidence,
            recommendations: $detailed->recommendations ?? [],
            generatedAt: $generatedAt,
            narrative: null,
        );
    }
}
