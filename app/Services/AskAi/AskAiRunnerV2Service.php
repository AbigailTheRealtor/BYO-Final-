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
 *   5. AskAiFollowUpQuestionService    — append follow-up chips to the final response
 *
 * Optional normalizer step (feature-flagged):
 *   When the classifier returns 'unsupported' and AskAiIntentNormalizerService
 *   is injected and its flag is enabled, the normalizer maps the question to a
 *   canonical listing_facts field key. The pipeline then re-enters listing_facts
 *   through the normal contract and prompt-builder governance — no facts are invented.
 *   Layer 1 'prohibited' questions always block before the normalizer is reached.
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
    private AskAiFollowUpQuestionService $followUpService;
    private ?AskAiIntentNormalizerService $normalizer;

    public function __construct(
        AskAiQuestionClassifierService $classifier,
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalResponseBuilder,
        AskAiFollowUpQuestionService $followUpService,
        ?AskAiIntentNormalizerService $normalizer = null
    ) {
        $this->classifier           = $classifier;
        $this->internalRunner       = $internalRunner;
        $this->adapter              = $adapter;
        $this->finalResponseBuilder = $finalResponseBuilder;
        $this->followUpService      = $followUpService;
        $this->normalizer           = $normalizer;
    }

    /**
     * Execute the full Ask AI pipeline and return a structured result.
     *
     * Pipeline stages:
     *   1. Classify the question (always runs)
     *   1a. [Optional] Intent normalization: if classifier returns 'unsupported'
     *       AND the normalizer is injected AND its feature flag is enabled,
     *       attempt to map the question to a known listing_facts field key.
     *       Layer 1 'prohibited' questions always block before this step.
     *   2. Run internal pipeline: context → contract → prompt package
     *   3. Guard: if no prompt_package returned, skip OpenAI and return safe failed result
     *   4. Generate via OpenAI adapter
     *   5. Build the final normalised response
     *   6. Append follow-up question chips to final_response['follow_up_questions']
     *
     * Output contract — always returns exactly these nine keys:
     *   success        bool        — mirrors final_response['success']; false on guard/exception paths
     *   status         string      — mirrors final_response['status']; 'failed' on guard/exception paths
     *   classification array|null  — output of AskAiQuestionClassifierService::classify(); null on early exception
     *                                When normalization fires, carries 'normalized_field_key' for QA.
     *   context        array|null  — output of AskAiInternalRunnerService context stage
     *   contract       array|null  — output of AskAiInternalRunnerService contract stage
     *   prompt_package array|null  — output of AskAiInternalRunnerService prompt stage
     *   adapter_result array|null  — output of AskAiOpenAiAdapterService::generate(); null if skipped
     *   final_response array|null  — output of AskAiFinalResponseBuilderService::build() plus
     *                                follow_up_questions key from AskAiFollowUpQuestionService; null if skipped
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

            // ----------------------------------------------------------------
            // Step 1a — Optional intent normalization (feature-flagged).
            // Fires only when:
            //   (a) classifier returned 'unsupported' (not 'prohibited' or any
            //       other type — Layer 1 refusals always win)
            //   (b) the normalizer service is injected
            //   (c) the normalizer's feature flag is enabled
            // When a canonical path is returned, the pipeline re-enters
            // listing_facts through the normal contract + prompt-builder
            // governance. The normalized_field_key is attached to classification
            // for QA/debug visibility but does not bypass any governance layer.
            // ----------------------------------------------------------------
            if ($questionType === 'unsupported'
                && $this->normalizer !== null
                && $this->normalizer->isEnabled()
            ) {
                $knownFieldKeys = $this->normalizer->buildKnownFieldKeys();
                $normalizedKey  = $this->normalizer->normalize($question, $knownFieldKeys);

                if ($normalizedKey !== null) {
                    $classification = [
                        'question_type'        => 'listing_facts',
                        'confidence'           => 0.70,
                        'reason'               => 'OpenAI intent normalization resolved this question to a known field key.',
                        'normalized_field_key' => $normalizedKey,
                    ];
                    $questionType = 'listing_facts';

                    // Propagate the canonical path into $options so the internal runner
                    // can narrow the contract's allowed_context to only that field,
                    // ensuring the prompt package is grounded in exactly the right data.
                    $options = array_merge($options, ['normalized_field_key' => $normalizedKey]);
                }
            }

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

            $finalResponse['follow_up_questions'] = $this->followUpService->forResult(
                $finalResponse,
                $classification
            );

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
