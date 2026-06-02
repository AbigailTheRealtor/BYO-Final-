<?php

namespace App\Services\AskAi;

/**
 * AskAiInternalRunnerService — Phase 1–3 Orchestration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Orchestration runner for Ask AI Phases 1–3.
 * Chains AskAiContextBuilderService, AskAiResponseContractService, and
 * AskAiPromptBuilderService in sequence and returns a single structured result.
 * This service holds no business logic of its own — it only delegates and aggregates.
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
class AskAiInternalRunnerService
{
    private AskAiContextBuilderService $contextBuilder;
    private AskAiResponseContractService $contractService;
    private AskAiPromptBuilderService $promptBuilder;

    public function __construct(
        AskAiContextBuilderService $contextBuilder,
        AskAiResponseContractService $contractService,
        AskAiPromptBuilderService $promptBuilder
    ) {
        $this->contextBuilder  = $contextBuilder;
        $this->contractService = $contractService;
        $this->promptBuilder   = $promptBuilder;
    }

    /**
     * Run the Phase 1–3 Ask AI pipeline in sequence.
     *
     * Output contract — always returns exactly these six keys:
     *   success        bool        — true only when prompt_package['status'] === 'prompt_ready'
     *   status         string      — mirrors prompt_package['status'], or 'failed' on exception
     *   context        array|null  — output of AskAiContextBuilderService::buildForListing()
     *   contract       array|null  — output of AskAiResponseContractService::buildContract()
     *   prompt_package array|null  — output of AskAiPromptBuilderService::buildPromptPackage()
     *   error          string|null — null on all non-exception paths; error message on \Throwable
     *
     * @param  string  $listingType   Canonical or aliased listing type string.
     * @param  int     $listingId     Primary key of the listing record.
     * @param  string  $questionType  One of the defined Ask AI question types.
     * @param  string  $userQuestion  The user's question text.
     * @param  array   $options       Optional pair options forwarded to buildForListing().
     * @return array
     */
    public function run(
        string $listingType,
        int $listingId,
        string $questionType,
        string $userQuestion,
        array $options = []
    ): array {
        try {
            $context = $this->contextBuilder->buildForListing($listingType, $listingId, $options);

            $contract = $this->contractService->buildContract($questionType, $context);

            $promptPackage = $this->promptBuilder->buildPromptPackage($userQuestion, $context, $contract);

            $status  = $promptPackage['status'] ?? 'failed';
            $success = ($status === 'prompt_ready');

            return [
                'success'        => $success,
                'status'         => $status,
                'context'        => $context,
                'contract'       => $contract,
                'prompt_package' => $promptPackage,
                'error'          => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success'        => false,
                'status'         => 'failed',
                'context'        => null,
                'contract'       => null,
                'prompt_package' => null,
                'error'          => $e->getMessage(),
            ];
        }
    }
}
