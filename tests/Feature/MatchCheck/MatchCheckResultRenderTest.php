<?php

namespace Tests\Feature\MatchCheck;

use App\Services\Stellar\MatchCheck\EnrichmentGuardDecision;
use App\Services\Stellar\MatchCheck\MatchCheckAnalysis;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\MatchCheckResult;
use App\Services\Stellar\MatchCheck\MatchReport;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use Tests\TestCase;

/**
 * git-C14 — status rendering for the layout-free result partial.
 *
 * Renders _result_body directly for each MatchCheckAnalysis status and asserts the
 * boundary compliance rules:
 *   F8 — the MatchReport->narrative slot is NEVER rendered (no AI narrative surface).
 *   F7 — no raw source data (raw_json / PublicRemarks) appears in the HTML.
 *   F9 — a BLOCKED listing shows neutral copy only; the block reason is not exposed.
 */
class MatchCheckResultRenderTest extends TestCase
{
    private function render(MatchCheckAnalysis $analysis): string
    {
        return view('match-check.partials._result_body', ['analysis' => $analysis])->render();
    }

    /** @test */
    public function scored_renders_the_report_but_never_the_narrative(): void
    {
        $prep = MatchCheckPreparation::ready(
            VisibilityDecision::visible('idx'),
            'buyer',
            ['id' => 1, 'type' => 'buyer_criteria'],
        );
        $result = MatchCheckResult::scored($prep, 'X1', 87, ['location' => 30, 'price' => 25]);

        $report = new MatchReport(
            criteriaId: 1,
            criteriaType: 'buyer_criteria',
            listingKey: 'X1',
            source: 'bridge',
            totalScore: 87,
            categoryScores: ['location' => 30, 'price' => 25],
            whyThisMatches: ['Great location match'],
            whyNot: ['Slightly over budget'],
            tradeoffs: ['Smaller lot than preferred'],
            missingData: ['garage'],
            confidence: ['level' => 'high'],
            recommendations: ['Ask about HOA fees'],
            generatedAt: '2026-07-07T00:00:00+00:00',
            narrative: ['text' => 'SECRET_NARRATIVE_MUST_NOT_RENDER'],
        );

        $html = $this->render(
            MatchCheckAnalysis::fromResult($result, EnrichmentGuardDecision::REASON_ALLOWED, $report)
        );

        $this->assertStringContainsString('data-status="scored"', $html);
        $this->assertStringContainsString('87/100', $html);
        $this->assertStringContainsString('Great location match', $html);
        $this->assertStringContainsString('Slightly over budget', $html);
        $this->assertStringContainsString('Ask about HOA fees', $html);

        // F8 — narrative must never render.
        $this->assertStringNotContainsString('SECRET_NARRATIVE_MUST_NOT_RENDER', $html);

        // F7 — no restricted source data.
        $this->assertStringNotContainsString('raw_json', $html);
        $this->assertStringNotContainsString('PublicRemarks', $html);
    }

    /** @test */
    public function not_found_renders_the_neutral_empty_state(): void
    {
        $html = $this->render(MatchCheckAnalysis::notFound());

        $this->assertStringContainsString('data-status="not_found"', $html);
    }

    /** @test */
    public function disabled_degrades_to_the_neutral_state_without_error(): void
    {
        // Not reachable over HTTP (middleware 404s), but the template must not blow up.
        $html = $this->render(MatchCheckAnalysis::disabled());

        $this->assertStringContainsString('data-status="not_found"', $html);
    }

    /** @test */
    public function blocked_shows_neutral_copy_and_hides_the_reason(): void
    {
        $prep   = MatchCheckPreparation::blocked(VisibilityDecision::blocked('non_idx_reason'));
        $result = MatchCheckResult::blocked($prep);

        $html = $this->render(
            MatchCheckAnalysis::fromResult($result, EnrichmentGuardDecision::REASON_FEATURE_DISABLED)
        );

        $this->assertStringContainsString('data-status="blocked"', $html);
        // F9 — never expose why a listing is hidden.
        $this->assertStringNotContainsString('non_idx_reason', $html);
    }

    /** @test */
    public function no_criteria_renders_the_criteria_empty_state(): void
    {
        $prep   = MatchCheckPreparation::ready(VisibilityDecision::visible('idx'), 'buyer', null);
        $result = MatchCheckResult::noCriteria($prep);

        $html = $this->render(
            MatchCheckAnalysis::fromResult($result, EnrichmentGuardDecision::REASON_FEATURE_DISABLED)
        );

        $this->assertStringContainsString('data-status="no_criteria"', $html);
    }

    /** @test */
    public function criteria_not_loaded_renders_the_retry_state(): void
    {
        $prep   = MatchCheckPreparation::ready(VisibilityDecision::visible('idx'), 'buyer', ['id' => 1, 'type' => 'buyer_criteria']);
        $result = MatchCheckResult::criteriaNotLoaded($prep);

        $html = $this->render(
            MatchCheckAnalysis::fromResult($result, EnrichmentGuardDecision::REASON_FEATURE_DISABLED)
        );

        $this->assertStringContainsString('data-status="criteria_not_loaded"', $html);
    }
}
