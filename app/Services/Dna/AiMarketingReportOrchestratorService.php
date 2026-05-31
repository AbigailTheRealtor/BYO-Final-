<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * AiMarketingReportOrchestratorService — Phase XG AI Marketing Report Orchestrator
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service wires together AiMarketingReportGeneratorService (Phase XD) and
 * AiMarketingReportReviewService (Phase XF) into a single backend-only, in-memory
 * orchestration pipeline. It runs generation followed by review and returns one
 * unified result array.
 *
 * This service MUST NEVER:
 *   - Write to or read from any database table, queue, cache layer, session, or
 *     persistent store of any kind.
 *   - Introduce any route, controller, Blade view, Livewire component, JavaScript,
 *     migration, seeder, or database schema change.
 *   - Catch, swallow, or wrap any exception — all exceptions (including
 *     MarketingReadinessException) must propagate to the caller unchanged.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *   - Modify any upstream service (AiMarketingReportGeneratorService,
 *     AiMarketingReportReviewService, OpenAiClientService,
 *     PropertyMarketingContextService, PropertyMarketingBriefService,
 *     PropertyMarketingReadinessService).
 * ==================================================================================
 */
class AiMarketingReportOrchestratorService
{
    public function __construct(
        private readonly AiMarketingReportGeneratorService $generatorService,
        private readonly AiMarketingReportReviewService $reviewService,
    ) {}

    /**
     * Run the full in-memory generation + review pipeline for the given profile.
     *
     * Steps:
     *   1. Calls AiMarketingReportGeneratorService::generate($profile).
     *   2. Extracts $generation['report'] and passes it to
     *      AiMarketingReportReviewService::review($report).
     *   3. Derives orchestration_status:
     *      - 'ready_for_agent_review' when $review['passed'] === true
     *        AND $review['review_status'] === 'approved_for_agent_review'.
     *      - 'blocked' otherwise.
     *   4. Returns the unified four-key result array in memory only.
     *
     * All exceptions (including MarketingReadinessException) propagate unchanged.
     * Nothing is written to any persistent store at any point.
     *
     * Return structure:
     * [
     *     'generation'           => array,  // full result from generate()
     *     'review'               => array,  // full result from review()
     *     'orchestration_status' => string, // 'ready_for_agent_review' | 'blocked'
     *     'generated_at'         => string, // UTC ISO 8601 timestamp
     * ]
     *
     * @param  PropertyDnaProfile $profile  A persisted, cast profile model instance.
     * @return array
     */
    public function run(PropertyDnaProfile $profile): array
    {
        $generation = $this->generatorService->generate($profile);

        $report = $generation['report'];

        $review = $this->reviewService->review($report);

        $status = ($review['passed'] === true && $review['review_status'] === 'approved_for_agent_review')
            ? 'ready_for_agent_review'
            : 'blocked';

        return [
            'generation'           => $generation,
            'review'               => $review,
            'orchestration_status' => $status,
            'generated_at'         => now()->utc()->toIso8601String(),
        ];
    }
}
