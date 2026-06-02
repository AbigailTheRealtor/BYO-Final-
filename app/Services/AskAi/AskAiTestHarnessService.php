<?php

namespace App\Services\AskAi;

/**
 * AskAiTestHarnessService — Developer Test Utility
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Backend-only developer utility that wires AskAiQuestionClassifierService
 * and AskAiInternalRunnerService together into a single runTest() call.
 * Exists purely as a test/inspection aid — it must never be exposed via a route,
 * controller, or any user-facing surface.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiTestHarnessService
{
    private AskAiQuestionClassifierService $classifier;
    private AskAiInternalRunnerService $runner;

    public function __construct(
        AskAiQuestionClassifierService $classifier,
        AskAiInternalRunnerService $runner
    ) {
        $this->classifier = $classifier;
        $this->runner     = $runner;
    }

    /**
     * Classify the question, then run the full Phase 1–3 pipeline.
     *
     * Output contract — always returns exactly these two keys:
     *   classification  array  — output of AskAiQuestionClassifierService::classify()
     *   runner_result   array  — output of AskAiInternalRunnerService::run()
     *
     * @param  string  $listingType  Canonical or aliased listing type string.
     * @param  int     $listingId    Primary key of the listing record.
     * @param  string  $question     The raw question text from the user.
     * @param  array   $options      Optional pair options forwarded to the runner.
     * @return array
     */
    public function runTest(
        string $listingType,
        int $listingId,
        string $question,
        array $options = []
    ): array {
        $classification = $this->classifier->classify($question);

        $runnerResult = $this->runner->run(
            $listingType,
            $listingId,
            $classification['question_type'],
            $question,
            $options
        );

        return [
            'classification' => $classification,
            'runner_result'  => $runnerResult,
        ];
    }
}
