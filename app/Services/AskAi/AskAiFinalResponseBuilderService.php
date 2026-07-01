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
     * Phase A compliance guardrail — output sanitization (C-A) + superlative restriction
     * (C-I) + educational disclaimer (C-J). Optional constructor arg keeps the existing
     * no-argument construction used across the test suite working while allowing the
     * Laravel container to inject the shared instance in production.
     */
    private AskAiComplianceGuardrailService $guardrail;

    public function __construct(?AskAiComplianceGuardrailService $guardrail = null)
    {
        $this->guardrail = $guardrail ?? new AskAiComplianceGuardrailService();
    }

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
            $answer  = $this->extractAnswerFromResponse($rawText);

            // Phase A — C-A/C-I output sanitization: scrub steering, demographic,
            // protected-class, advice, and superlative/comparative content from the
            // generated text before it leaves the pipeline. Centralised here so every
            // path that resolves to 'ready' is covered.
            $sanitized = $this->guardrail->sanitizeAnswer($answer);

            return [
                'success'           => true,
                'status'            => 'ready',
                'answer'            => $sanitized['text'],
                'disclosures'       => $disclosures,
                'source_attribution'=> $sourceAttribution,
                'refusal_message'   => null,
                'error'             => null,
                'compliance'        => [
                    'sanitized'         => $sanitized['modified'],
                    'withheld'          => $sanitized['withheld'],
                    'removed_sentences' => $sanitized['removed_sentences'],
                    'categories'        => $sanitized['categories'],
                ],
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
     * Extract a usable answer string from the raw adapter response text.
     *
     * OpenAI is instructed to respond with a JSON object having a single "answer" key.
     * This method decodes that JSON and extracts the answer text so users see natural
     * language instead of a raw JSON blob.
     *
     * Resolution order:
     *   1. Decode as JSON. If successful:
     *      a. Return the value of the "answer" key (primary key per SYSTEM_INSTRUCTIONS).
     *      b. Return the value of the "answer_text" key (description-fallback key).
     *      c. Recursively search all values (including nested objects) for the first
     *         non-empty string.  This guards against unexpected OpenAI response shapes
     *         that wrap the answer in a sub-object (e.g. {"data":{"answer":"..."}}).
     *   2. If not valid JSON (e.g. plain-text test stubs or legacy paths), treat the raw
     *      string as the answer text directly.
     *
     * In all cases the extracted text is passed through normaliseText() for whitespace cleanup.
     *
     * @param  string $rawText  The raw_response/text/answer string from the adapter result.
     * @return string
     */
    private function extractAnswerFromResponse(string $rawText): string
    {
        $decoded = json_decode($rawText, true);

        if (is_array($decoded)) {
            if (isset($decoded['answer']) && is_string($decoded['answer']) && $decoded['answer'] !== '') {
                // If the answer value is itself a JSON-encoded string (double-encoding),
                // recursively extract from the inner JSON before normalising.
                $inner = json_decode($decoded['answer'], true);
                if (is_array($inner)) {
                    $unwrapped = $this->extractAnswerFromResponse($decoded['answer']);
                    if ($unwrapped !== '') {
                        return $unwrapped;
                    }
                }
                return $this->normaliseText($decoded['answer']);
            }

            if (isset($decoded['answer_text']) && is_string($decoded['answer_text']) && $decoded['answer_text'] !== '') {
                // Same double-encoding guard for answer_text.
                $inner = json_decode($decoded['answer_text'], true);
                if (is_array($inner)) {
                    $unwrapped = $this->extractAnswerFromResponse($decoded['answer_text']);
                    if ($unwrapped !== '') {
                        return $unwrapped;
                    }
                }
                return $this->normaliseText($decoded['answer_text']);
            }

            // Recursive search handles nested JSON objects (e.g. {"data":{"answer":"..."}})
            // so structured blobs never leak through to the user as raw text.
            $found = $this->findFirstStringValue($decoded);
            if ($found !== null) {
                return $this->normaliseText($found);
            }
        }

        return $this->normaliseText($rawText);
    }

    /**
     * Recursively search an array for the first non-empty string scalar value.
     *
     * Handles nested JSON objects of arbitrary depth so that unexpected OpenAI
     * response shapes (e.g. {"data":{"answer":"..."}}) do not leak raw JSON text
     * back to the user.  Integer and float scalars are skipped; only strings are
     * returned so numeric-only fields (e.g. {"count":3}) cannot become the answer.
     *
     * @param  array $data  Decoded JSON array to search.
     * @return string|null  First non-empty string found, or null if none exists.
     */
    private function findFirstStringValue(array $data): ?string
    {
        foreach ($data as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_array($value)) {
                $nested = $this->findFirstStringValue($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return null;
    }

    /**
     * Determine whether an extracted answer is low-quality and requires a rewrite.
     *
     * Detection heuristics (any one match returns true):
     *   1. Raw JSON blob — answer starts with '{' or '[' after trimming, meaning the
     *      structured response was not parsed correctly and leaked through.
     *   2. JSON key-value pattern — answer contains a "key": pattern (quoted word
     *      followed by a colon), indicating a partially-decoded JSON structure.
     *   3. Very short — answer is fewer than 15 characters or fewer than 3 words,
     *      which typically indicates a bare boolean, single word, or field name was
     *      returned as-is rather than composed into a sentence.
     *
     * This method is public so AskAiRunnerV2Service can check the response after
     * calling build() and decide whether to invoke a one-shot quality rewrite.
     *
     * @param  string $answer  The extracted answer text from build().
     * @return bool            True when the answer is considered low-quality.
     */
    public function isResponseDegraded(string $answer): bool
    {
        $trimmed = trim($answer);

        if ($trimmed === '') {
            return true;
        }

        // Heuristic 1: raw JSON blob leaked through (extraction failed silently).
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return true;
        }

        // Heuristic 2a: JSON key-value pattern inside the answer text (quoted key).
        if (preg_match('/"[^"]+"\s*:/', $trimmed)) {
            return true;
        }

        // Heuristic 2b: bare snake_case or camelCase key-value dump (unquoted key).
        // Matches lines that start with a lowercase identifier (optionally snake_case)
        // immediately followed by a colon, e.g. "hoa_fee: 250". Natural sentences
        // that start with a word before a colon are excluded because they are
        // capitalised (sentence case) — this regex is anchored to line-start and
        // requires a lowercase first character, which prose sentences never have.
        if (preg_match('/^[a-z][a-z0-9_]+\s*:/m', $trimmed)) {
            return true;
        }

        // Heuristic 3: overly short responses (bare boolean, single-word, field label).
        if (mb_strlen($trimmed) < 15 || str_word_count($trimmed) < 3) {
            return true;
        }

        // Heuristic 4: No sentence-closing punctuation.
        // A well-formed synthesized answer always ends with '.', '!', or '?'.
        // Raw field echoes ("Central Air, Mini-Split Unit(s)"), comma-separated
        // lists ("Electric, Gas"), year stamps ("Installed 2019"), and other
        // verbatim value regurgitations never do.  Any response that passed
        // heuristics 1–3 but still lacks terminal punctuation is treated as a
        // raw echo and sent through the one-shot quality rewrite.
        // Trailing close-quote or parenthesis after the punctuation is allowed
        // (e.g. 'The system is "central air."').
        if (!preg_match('/[.!?]["\')»]?\s*$/', $trimmed)) {
            return true;
        }

        return false;
    }

    /**
     * Classify a final response into one of four Truth Source Contract answer forms.
     *
     * Contract forms:
     *   'direct_fact'          — question answered from structured listing data; answer
     *                            is a complete sentence grounded in a single field value
     *                            (e.g. "The asking price is $450,000.").
     *   'synthesis'            — answer requires combining multiple fields or reasoning
     *                            over a list (e.g. lease acceptance, credit amount + flag).
     *                            Identified when the answer is 'ready' and $synthesisHint is true.
     *   'insufficient_context' — required data is absent; answer is "not provided" message.
     *   'refusal'              — question touches a prohibited topic; answer is null.
     *
     * @param  array $finalResponse   The response array returned by build() or the runner.
     * @param  bool  $synthesisHint   Pass true when the normalized_field_key is in
     *                                SYNTHESIS_REQUIRED_KEYS (available from runner trace).
     * @return string  One of: 'direct_fact' | 'synthesis' | 'insufficient_context' | 'refusal'
     */
    public function contractFormOf(array $finalResponse, bool $synthesisHint = false): string
    {
        $status = $finalResponse['status'] ?? '';

        if ($status === 'blocked') {
            return 'refusal';
        }

        if ($status === 'insufficient_context') {
            return 'insufficient_context';
        }

        if ($status === 'ready') {
            return $synthesisHint ? 'synthesis' : 'direct_fact';
        }

        return 'insufficient_context';
    }

    /**
     * Assert that a final response matches the expected contract form.
     *
     * Throws a \LogicException when the actual form does not match $expectedForm.
     * Designed for use in golden QA tests — the exception message includes both
     * the expected and actual forms plus the response status and answer preview.
     *
     * @param  array  $finalResponse  Response from build() or the runner.
     * @param  string $expectedForm   One of: 'direct_fact' | 'synthesis' | 'insufficient_context' | 'refusal'
     * @param  bool   $synthesisHint  Pass true when the key is in SYNTHESIS_REQUIRED_KEYS.
     * @return void
     * @throws \LogicException
     */
    public static function assertContractForm(
        array $finalResponse,
        string $expectedForm,
        bool $synthesisHint = false
    ): void {
        $instance   = new self();
        $actualForm = $instance->contractFormOf($finalResponse, $synthesisHint);

        if ($actualForm !== $expectedForm) {
            $answerPreview = isset($finalResponse['answer'])
                ? substr((string) $finalResponse['answer'], 0, 80)
                : '(null)';
            throw new \LogicException(sprintf(
                'Contract form mismatch for key. Expected "%s", got "%s". Status: %s. Answer: %s',
                $expectedForm,
                $actualForm,
                $finalResponse['status'] ?? '(none)',
                $answerPreview
            ));
        }
    }

    /**
     * Enforce the Truth Source Contract at the outgoing boundary.
     *
     * Only three status values correspond to the four contract answer-forms that
     * a caller may receive:
     *
     *   'ready'               → direct_fact | synthesis
     *   'insufficient_context'→ insufficient_context
     *   'blocked'             → refusal
     *
     * Any status outside that set ('failed', 'unsupported', unknown strings) is
     * coerced to 'insufficient_context' so that non-contract statuses never leak
     * to end users. The original error/status is preserved in the returned array
     * under '_pre_coercion_status' for diagnostic purposes.
     *
     * @param  array $finalResponse  Response array from build() or a runner branch.
     * @return array                 Response with status coerced to a contract value.
     */
    public function coerceToContractStatus(array $finalResponse): array
    {
        $contractStatuses = ['ready', 'insufficient_context', 'blocked'];
        $status           = $finalResponse['status'] ?? '';

        // ── Content-level enforcement ──────────────────────────────────────────
        // A 'ready' response whose answer text fails the degradation check violates
        // the Truth Source Contract: both 'direct_fact' and 'synthesis' contract
        // forms require a complete, well-formed natural-language sentence. A raw
        // field echo that slipped through (bare list, short string, no terminal
        // punctuation) must be demoted to insufficient_context rather than surfaced
        // as if it were a proper answer.
        //
        // This layer handles cases where the quality-rewrite was skipped (value
        // already had terminal punctuation) but the value was still raw data —
        // e.g. a policy string like "No pets allowed." that reads as answered but
        // was never synthesised into a contextual answer for the question asked.
        // Note: the synthesis gate in the runner's direct-return fallback already
        // blocks the most common raw-data cases before this point; this layer is
        // a belt-and-suspenders guard on ALL outgoing 'ready' responses.
        if ($status === 'ready'
            && isset($finalResponse['answer'])
            && $this->isResponseDegraded((string) $finalResponse['answer'])
        ) {
            return array_merge($finalResponse, [
                '_pre_coercion_status' => $status,
                '_pre_coercion_reason' => 'degraded_answer_text',
                'success'              => false,
                'status'               => 'insufficient_context',
                'answer'               => self::INSUFFICIENT_CONTEXT_ANSWER,
            ]);
        }

        // ── Status-level enforcement ───────────────────────────────────────────
        // Non-contract statuses ('failed', 'unsupported', etc.) must not reach
        // the caller. Coerce to 'insufficient_context' and record the original.
        if (!in_array($status, $contractStatuses, true)) {
            return array_merge($finalResponse, [
                '_pre_coercion_status' => $status,
                'success'              => false,
                'status'               => 'insufficient_context',
                'answer'               => (isset($finalResponse['answer'])
                                            && (string) $finalResponse['answer'] !== '')
                                            ? $finalResponse['answer']
                                            : self::INSUFFICIENT_CONTEXT_ANSWER,
            ]);
        }

        return $finalResponse;
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
