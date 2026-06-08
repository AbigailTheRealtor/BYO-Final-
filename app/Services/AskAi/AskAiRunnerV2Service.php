<?php

namespace App\Services\AskAi;

use Illuminate\Support\Facades\Log;

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
 * Optional normalizer step (feature-flagged, step 1a):
 *   When the classifier returns 'unsupported' and AskAiIntentNormalizerService
 *   is injected and its flag is enabled, the normalizer maps the question to a
 *   canonical listing_facts field key. The pipeline then re-enters listing_facts
 *   through the normal contract and prompt-builder governance — no facts are invented.
 *   Layer 1 'prohibited' questions always block before the normalizer is reached.
 *
 * Deterministic FAQ key detection (always runs, step 1b):
 *   When the classifier routes to listing_facts, a keyword-based detector maps
 *   the question to a specific faq_answers.* path (e.g. roof-related phrases →
 *   faq_answers.roof_age_and_condition). This pins the allowed context to that
 *   field so the missing-data guard fires correctly if the listing has no answer.
 *   No external I/O; runs regardless of the normalizer feature flag.
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
    /**
     * Keyword → canonical faq_answers.* path map used by detectFaqFieldKey().
     *
     * Each entry maps a set of lower-case sub-string keywords to the canonical
     * FAQ field path they address. The detector iterates entries in order and
     * returns the FIRST matching path.
     *
     * Must be kept in sync with the FAQ config files (config/ai_faq_*.php).
     * Only include keys whose intent is unambiguously one specific FAQ field.
     */
    private const FAQ_KEY_KEYWORD_MAP = [
        'faq_answers.roof_age_and_condition' => [
            'roof age',
            'age of roof',
            'when was the roof',
            'how old is the roof',
            "what's the roof situation",
            'what is the roof situation',
            'tell me about the roof',
            'roof condition',
        ],
        'faq_answers.heating_cooling_system' => [
            'heating and cooling system',
            'heat and air',
            'heating/cooling',
            'what type of heating',
            'what kind of heating',
            'heating or cooling system',
            'cooling system type',
            'hvac type',
            'hvac system type',
        ],
        'faq_answers.laundry_situation' => [
            'in-unit laundry',
            'in unit laundry',
            'laundry situation',
            'laundry in unit',
            'washer and dryer in',
            'washer/dryer in',
            'laundry facilities',
        ],
    ];

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

            // Determine normalizer_status before building the trace so every
            // exit path (including early returns) carries the correct value.
            // not_applicable — question type is deterministic; normalizer not relevant.
            // not_called     — question is 'unsupported' but flag is off or service missing.
            // (updated below when normalizer is actually called)
            if ($questionType !== 'unsupported') {
                $normalizerStatus = 'not_applicable';
                $normalizerError  = null;
            } elseif ($this->normalizer === null || !$this->normalizer->isEnabled()) {
                $normalizerStatus = 'not_called';
                $normalizerError  = null;
            } else {
                // Will be overwritten after the normalizer call in step 1a.
                $normalizerStatus = 'not_called';
                $normalizerError  = null;
            }

            $trace = [
                'question'             => $question,
                'classifier_result'    => $questionType,
                'normalizer_called'    => 'N',
                'normalizer_status'    => $normalizerStatus,
                'normalizer_error'     => $normalizerError,
                'normalized_field_key' => null,
                'faq_key_detected'     => null,
                'final_question_type'  => $questionType,
                'final_status'         => null,
                'source_attribution'   => null,
            ];

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
                $trace['normalizer_called'] = 'Y';
                $knownFieldKeys = $this->normalizer->buildKnownFieldKeys();
                $normalizedKey  = $this->normalizer->normalize($question, $knownFieldKeys);

                // Map the normalizer's internal status to the three trace categories:
                //   matched → normalizer_status=matched
                //   unknown → normalizer_status=unknown
                //   any failure → normalizer_status=failed, normalizer_error=<reason>
                $lastNormStatus = $this->normalizer->getLastStatus();
                $lastNormError  = $this->normalizer->getLastError();
                if ($lastNormStatus === 'matched') {
                    $trace['normalizer_status'] = 'matched';
                    $trace['normalizer_error']  = null;
                } elseif ($lastNormStatus === 'unknown') {
                    $trace['normalizer_status'] = 'unknown';
                    $trace['normalizer_error']  = null;
                } else {
                    $trace['normalizer_status'] = 'failed';
                    $trace['normalizer_error']  = $lastNormError;
                }

                if ($normalizedKey !== null) {
                    $trace['normalized_field_key'] = $normalizedKey;
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

            // ----------------------------------------------------------------
            // Step 1b — Deterministic FAQ key detection for listing_facts.
            // Runs when the classifier (or step 1a normalization) routed the
            // question to listing_facts AND no normalized_field_key has been
            // set yet. A keyword lookup maps well-known factual intents to
            // their canonical faq_answers.* path — e.g. all six roof-condition
            // phrasings resolve to faq_answers.roof_age_and_condition.
            //
            // Runs regardless of the OpenAI normalizer flag because it is purely
            // deterministic (no external I/O). When a key is detected the prompt
            // package is narrowed to that field alone, and the missing-data guard
            // below fires correctly when the listing has no answer for that key.
            // ----------------------------------------------------------------
            if ($questionType === 'listing_facts' && !isset($options['normalized_field_key'])) {
                $detectedKey = $this->detectFaqFieldKey($question);
                if ($detectedKey !== null) {
                    $trace['faq_key_detected'] = $detectedKey;
                    $classification['normalized_field_key'] = $detectedKey;
                    $options = array_merge($options, ['normalized_field_key' => $detectedKey]);
                }
            }

            $trace['final_question_type'] = $questionType;

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
                $trace['final_status'] = 'failed';
                $this->emitTrace($trace);
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
                    'trace'          => $trace,
                ];
            }

            // ----------------------------------------------------------------
            // Field-specific missing-data guard.
            // Fires when the normalizer resolved a faq_answers.* key but the
            // listing has no answer for that key — i.e. filterAllowedContext()
            // returned an empty array because the FAQ entry is absent.
            // Without this guard, OpenAI would be called with zero context and
            // could hallucinate an answer. Instead we return a clear, grounded
            // message that the specific information was not provided.
            // Only applies to faq_answers.* keys; listing.* null values are
            // passed through normally (they appear as null in allowed_context
            // and the response rules direct the LLM to state they are absent).
            // ----------------------------------------------------------------
            $normalizedFieldKey = $options['normalized_field_key'] ?? null;

            if (
                $normalizedFieldKey !== null
                && str_starts_with($normalizedFieldKey, 'faq_answers.')
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
                && array_key_exists('allowed_context', $promptPackage)
                && empty($promptPackage['allowed_context'])
            ) {
                $fieldLabel        = $this->deriveFieldLabel($normalizedFieldKey);
                $missingDataAnswer = $fieldLabel . ' has not been provided for this listing.';

                $missingFinalResponse = [
                    'success'            => false,
                    'status'             => 'insufficient_context',
                    'answer'             => $missingDataAnswer,
                    'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                    'source_attribution' => $promptPackage['source_attribution'] ?? [],
                    'refusal_message'    => null,
                    'error'              => null,
                ];
                $missingFinalResponse['follow_up_questions'] = $this->followUpService->forResult(
                    $missingFinalResponse,
                    $classification
                );

                $trace['final_status']       = 'insufficient_context';
                $trace['source_attribution'] = $missingFinalResponse['source_attribution'] ?? null;
                $this->emitTrace($trace);

                return [
                    'success'        => false,
                    'status'         => 'insufficient_context',
                    'classification' => $classification,
                    'context'        => $context,
                    'contract'       => $contract,
                    'prompt_package' => $promptPackage,
                    'adapter_result' => null,
                    'final_response' => $missingFinalResponse,
                    'error'          => null,
                    'trace'          => $trace,
                ];
            }

            $adapterResult = $this->adapter->generate($promptPackage);

            $finalResponse = $this->finalResponseBuilder->build($promptPackage, $adapterResult);

            $finalResponse['follow_up_questions'] = $this->followUpService->forResult(
                $finalResponse,
                $classification
            );

            $error = ($finalResponse['error'] ?? null) ?: null;

            $trace['final_status']       = $finalResponse['status'];
            $trace['source_attribution'] = $finalResponse['source_attribution'] ?? null;
            $this->emitTrace($trace);

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
                'trace'          => $trace,
            ];

        } catch (\Throwable $e) {
            $exceptionTrace = [
                'question'             => $question ?? null,
                'classifier_result'    => null,
                'normalizer_called'    => null,
                'normalizer_status'    => null,
                'normalizer_error'     => null,
                'normalized_field_key' => null,
                'faq_key_detected'     => null,
                'final_question_type'  => null,
                'final_status'         => 'failed',
                'source_attribution'   => null,
            ];
            $this->emitTrace($exceptionTrace);
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
                'trace'          => $exceptionTrace,
            ];
        }
    }

    /**
     * Emit a debug trace log entry. Silently skips when the Log facade is
     * unavailable (e.g. pure PHPUnit tests without a booted Laravel app).
     *
     * @param  array<string, mixed> $trace
     */
    private function emitTrace(array $trace): void
    {
        try {
            Log::debug('AskAiRunnerV2 trace', $trace);
        } catch (\Throwable $ignored) {
            // Log facade unavailable in unit test contexts without a booted app.
        }
    }

    /**
     * Deterministically detect a specific faq_answers.* field key from the
     * question text.
     *
     * Iterates FAQ_KEY_KEYWORD_MAP and returns the canonical path for the first
     * matching keyword. The check is case-insensitive sub-string matching,
     * consistent with AskAiQuestionClassifierService::findFirstMatch().
     *
     * Returns null when no keyword matches — the caller leaves allowed_context
     * unnarrowed and the full listing_facts data set is available to the LLM.
     *
     * @param  string $question  Raw user question string.
     * @return string|null       Canonical faq_answers.* path, or null.
     */
    private function detectFaqFieldKey(string $question): ?string
    {
        $lower = mb_strtolower(trim($question));

        foreach (self::FAQ_KEY_KEYWORD_MAP as $faqKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, mb_strtolower($keyword))) {
                    return $faqKey;
                }
            }
        }

        return null;
    }

    /**
     * Derive a human-readable field label from a normalized faq_answers.* key.
     *
     * Used to compose field-specific missing-data messages such as
     * "Roof information has not been provided for this listing."
     *
     * Known FAQ keys are mapped to their subject-area label. Any key that
     * falls outside the known set gets the safe generic label "The requested
     * information" so the message remains grammatically correct and grounded.
     *
     * @param  string $normalizedFieldKey  Canonical path e.g. 'faq_answers.roof_age_and_condition'.
     * @return string
     */
    private function deriveFieldLabel(string $normalizedFieldKey): string
    {
        $labelMap = [
            'faq_answers.roof_age_and_condition'      => 'Roof information',
            'faq_answers.hvac_system_age'             => 'HVAC system information',
            'faq_answers.heating_cooling_system'      => 'Heating and cooling system information',
            'faq_answers.laundry_situation'           => 'Laundry information',
            'faq_answers.water_heater_age_type'       => 'Water heater information',
            'faq_answers.average_utility_costs'       => 'Utility cost information',
            'faq_answers.known_defects_issues'        => 'Known defects/issues information',
            'faq_answers.recent_renovations'          => 'Recent renovation information',
            'faq_answers.appliances_included'         => 'Appliance information',
            'faq_answers.pest_treatment_history'      => 'Pest treatment information',
            'faq_answers.hoa_rules_restrictions'      => 'HOA rules information',
            'faq_answers.neighborhood_highlights'     => 'Neighborhood information',
            'faq_answers.showing_tips_seller'         => 'Showing information',
            'faq_answers.property_unique_features'    => 'Unique feature information',
            'faq_answers.landlord_responsibilities'   => 'Landlord responsibility information',
            'faq_answers.tenant_rules_regulations'    => 'Tenant rules information',
            'faq_answers.lease_renewal_terms'         => 'Lease renewal information',
            'faq_answers.utility_setup_instructions'  => 'Utility setup information',
            'faq_answers.pet_policy_details'          => 'Pet policy information',
            'faq_answers.parking_instructions'        => 'Parking information',
        ];

        return $labelMap[$normalizedFieldKey] ?? 'The requested information';
    }
}
