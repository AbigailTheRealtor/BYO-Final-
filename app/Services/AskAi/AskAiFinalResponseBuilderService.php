<?php

namespace App\Services\AskAi;

/**
 * AskAiFinalResponseBuilderService — Phase 5 Final Response Builder
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Pure transformation layer for Ask AI (Phase 5).
 * Converts a prompt package (assembled by Phase 3) plus a raw adapter result
 * (the LLM output obtained by the caller) into a safe, normalised final response
 * object. This service holds no business logic beyond status routing and text
 * normalisation — it only transforms and aggregates.
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
class AskAiFinalResponseBuilderService
{
    /**
     * Answer text returned when the prompt package status is 'insufficient_context'.
     */
    private const INSUFFICIENT_CONTEXT_ANSWER =
        'The requested information is not available because one or more required data sources ' .
        'are missing for this listing. Please ensure all required listing data is complete before ' .
        'requesting an AI response.';

    /**
     * Answer text returned when the prompt package status is 'unsupported'.
     */
    private const UNSUPPORTED_ANSWER =
        'This question type is not supported. Please select an approved question category ' .
        'to generate an AI response.';

    /**
     * Convert a prompt package and a raw adapter result into a normalised final response.
     *
     * Output contract — always returns exactly these 7 keys:
     *   success           bool        — true only when status is 'ready'
     *   status            string      — 'ready' | 'blocked' | 'insufficient_context' | 'unsupported' | 'failed'
     *   answer            string|null — trimmed/whitespace-normalised model text; null on non-ready paths
     *   disclosures       array       — always populated from prompt_package['required_disclosures']
     *   source_attribution array      — always passed through from prompt_package['source_attribution']
     *   refusal_message   string|null — from prompt_package['refusal_template'] on blocked; null otherwise
     *   error             string|null — null on non-failed paths; error message on failed/exception
     *
     * Adapter result answer text resolution (priority order):
     *   1. adapterResult['raw_response'] — official output key of AskAiOpenAiAdapterService
     *   2. adapterResult['text']         — backward-compatibility fallback
     *   3. adapterResult['answer']       — backward-compatibility fallback
     *
     * Status routing:
     *   prompt_package['status'] === 'blocked'               → status='blocked', refusal_message set, answer=null
     *   prompt_package['status'] === 'insufficient_context'  → status='insufficient_context', answer=unavailable message
     *   prompt_package['status'] === 'unsupported'           → status='unsupported', answer=unsupported message
     *   adapterResult['success'] === false OR adapterResult['error'] present → status='failed', error populated
     *   prompt_package['status'] === 'prompt_ready' AND adapter succeeded   → status='ready', answer=normalised text
     *
     * @param  array $promptPackage   Output of AskAiPromptBuilderService::buildPromptPackage().
     * @param  array $adapterResult   Raw result from AskAiOpenAiAdapterService or compatible adapter.
     * @return array
     */
    public function build(array $promptPackage, array $adapterResult): array
    {
        try {
            $disclosures       = $promptPackage['required_disclosures'] ?? [];
            $sourceAttribution = $promptPackage['source_attribution'] ?? [];
            $packageStatus     = $promptPackage['status'] ?? '';

            if ($packageStatus === 'blocked') {
                return [
                    'success'           => false,
                    'status'            => 'blocked',
                    'answer'            => null,
                    'disclosures'       => $disclosures,
                    'source_attribution'=> $sourceAttribution,
                    'refusal_message'   => $promptPackage['refusal_template'] ?? null,
                    'error'             => null,
                ];
            }

            if ($packageStatus === 'insufficient_context') {
                return [
                    'success'           => false,
                    'status'            => 'insufficient_context',
                    'answer'            => self::INSUFFICIENT_CONTEXT_ANSWER,
                    'disclosures'       => $disclosures,
                    'source_attribution'=> $sourceAttribution,
                    'refusal_message'   => null,
                    'error'             => null,
                ];
            }

            if ($packageStatus === 'unsupported') {
                return [
                    'success'           => false,
                    'status'            => 'unsupported',
                    'answer'            => self::UNSUPPORTED_ANSWER,
                    'disclosures'       => $disclosures,
                    'source_attribution'=> $sourceAttribution,
                    'refusal_message'   => null,
                    'error'             => null,
                ];
            }

            if (($adapterResult['success'] ?? true) === false || isset($adapterResult['error'])) {
                return [
                    'success'           => false,
                    'status'            => 'failed',
                    'answer'            => null,
                    'disclosures'       => $disclosures,
                    'source_attribution'=> $sourceAttribution,
                    'refusal_message'   => null,
                    'error'             => $adapterResult['error'] ?? 'Adapter failed without an error message.',
                ];
            }

            $rawText = $adapterResult['raw_response'] ?? $adapterResult['text'] ?? $adapterResult['answer'] ?? '';
            $answer  = $this->normaliseText($rawText);

            return [
                'success'           => true,
                'status'            => 'ready',
                'answer'            => $answer,
                'disclosures'       => $disclosures,
                'source_attribution'=> $sourceAttribution,
                'refusal_message'   => null,
                'error'             => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success'           => false,
                'status'            => 'failed',
                'answer'            => null,
                'disclosures'       => $promptPackage['required_disclosures'] ?? [],
                'source_attribution'=> $promptPackage['source_attribution'] ?? [],
                'refusal_message'   => null,
                'error'             => $e->getMessage(),
            ];
        }
    }

    /**
     * Trim leading/trailing whitespace and collapse internal runs of whitespace
     * (spaces, tabs, non-breaking spaces) to a single space.
     * Newlines are preserved — only horizontal whitespace is collapsed within lines.
     *
     * @param  string $text  Raw model output text.
     * @return string
     */
    private function normaliseText(string $text): string
    {
        $lines = explode("\n", $text);

        $lines = array_map(static function (string $line): string {
            return trim(preg_replace('/[ \t\xA0]+/', ' ', $line));
        }, $lines);

        return trim(implode("\n", $lines));
    }
}
