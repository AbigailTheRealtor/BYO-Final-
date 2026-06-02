<?php

namespace App\Services\AskAi;

/**
 * AskAiRunnerV2Service — End-to-End Ask AI Pipeline Runner
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: End-to-end orchestrator for the Ask AI pipeline.
 * Chains four already-built pipeline services in sequence:
 *   1. AskAiQuestionClassifierService  — classify the user question
 *   2. AskAiInternalRunnerService      — build context, contract, and prompt package
 *   3. AskAiOpenAiAdapterService       — call OpenAI with the prompt package
 *   4. AskAiFinalResponseBuilderService — normalise the raw adapter output
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service directly.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Hardcode or embed any OpenAI API key.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 *   - Maintain conversation history or stateful session data.
 * ==================================================================================
 */
class AskAiRunnerV2Service
{
    private AskAiQuestionClassifierService $classifier;
    private AskAiInternalRunnerService $internalRunner;
    private AskAiOpenAiAdapterService $adapter;
    private AskAiFinalResponseBuilderService $finalResponseBuilder;

    public function __construct(
        AskAiQuestionClassifierService $classifier,
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalResponseBuilder
    ) {
        $this->classifier           = $classifier;
        $this->internalRunner       = $internalRunner;
        $this->adapter              = $adapter;
        $this->finalResponseBuilder = $finalResponseBuilder;
    }

    /**
     * Execute the full Ask AI pipeline and return a structured result.
     *
     * Pipeline stages:
     *   1. Classify the question (always runs)
     *   2. Run internal pipeline: context → contract → prompt package
     *   3. Guard: if no prompt_package returned, skip OpenAI and return safe failed result
     *   4. Generate via OpenAI adapter
     *   5. Build the final normalised response
     *
     * Output contract — always returns exactly these nine keys:
     *   success        bool        — mirrors final_response['success']; false on guard/exception paths
     *   status         string      — mirrors final_response['status']; 'failed' on guard/exception paths
     *   classification array|null  — output of AskAiQuestionClassifierService::classify(); null on early exception
     *   context        array|null  — output of AskAiInternalRunnerService context stage
     *   contract       array|null  — output of AskAiInternalRunnerService contract stage
     *   prompt_package array|null  — output of AskAiInternalRunnerService prompt stage
     *   adapter_result array|null  — output of AskAiOpenAiAdapterService::generate(); null if skipped
     *   final_response array|null  — output of AskAiFinalResponseBuilderService::build(); null if skipped
     *   error          string|null — null unless final_response['error'] is set or a Throwable is caught
     *
     * @param  string $listingType  Canonical or aliased listing type string.
     * @param  int    $listingId    Primary key of the listing record.
     * @param  string $question     The raw user question string.
     * @param  array  $options      Optional pair options forwarded to the internal runner.
     * @return array
     */
    public function run(string $listingType, int $listingId, string $question, array $options = []): array
    {
        try {
            $classification = $this->classifier->classify($question);
            $questionType   = $classification['question_type'];

            $internalResult = $this->internalRunner->run(
                $listingType,
                $listingId,
                $questionType,
                $question,
                $options
            );

            $context       = $internalResult['context']        ?? null;
            $contract      = $internalResult['contract']       ?? null;
            $promptPackage = $internalResult['prompt_package'] ?? null;

            if ($promptPackage === null) {
                return [
                    'success'        => false,
                    'status'         => 'failed',
                    'classification' => $classification,
                    'context'        => $context,
                    'contract'       => $contract,
                    'prompt_package' => null,
                    'adapter_result' => null,
                    'final_response' => null,
                    'error'          => 'Internal runner returned no prompt_package; OpenAI call skipped.',
                ];
            }

            $adapterResult = $this->adapter->generate($promptPackage);

            $finalResponse = $this->finalResponseBuilder->build($promptPackage, $adapterResult);

            $error = ($finalResponse['error'] ?? null) ?: null;

            return [
                'success'        => $finalResponse['success'],
                'status'         => $finalResponse['status'],
                'classification' => $classification,
                'context'        => $context,
                'contract'       => $contract,
                'prompt_package' => $promptPackage,
                'adapter_result' => $adapterResult,
                'final_response' => $finalResponse,
                'error'          => $error,
            ];

        } catch (\Throwable $e) {
            return [
                'success'        => false,
                'status'         => 'failed',
                'classification' => null,
                'context'        => null,
                'contract'       => null,
                'prompt_package' => null,
                'adapter_result' => null,
                'final_response' => null,
                'error'          => $e->getMessage(),
            ];
        }
    }
}
