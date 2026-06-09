<?php

namespace App\Services\AskAi;

use App\Services\Ai\OpenAiClientService;

/**
 * AskAiIntentNormalizerService — Phase 2 Task C: OpenAI Intent Normalization
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Feature-flagged pre-classification normalizer for ambiguous user questions.
 * When the deterministic classifier returns 'unsupported', this service calls
 * OpenAI with a strictly constrained prompt to map the question to one canonical
 * field key from an approved registry. The final answer is NEVER generated here —
 * all answers remain grounded in the existing listing_facts contract and
 * prompt-builder governance pipeline.
 *
 * This service MUST NEVER:
 *   - Generate, infer, or return any final answer text about a listing.
 *   - Accept or return any field key not present in the provided knownFieldKeys list.
 *   - Invent values for missing data.
 *   - Reference or infer protected class characteristics.
 *   - Execute any database write (save, update, create, delete).
 *   - Maintain conversation history or stateful session data.
 *   - Hardcode or embed any OpenAI API key.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 * ==================================================================================
 */
class AskAiIntentNormalizerService
{
    private OpenAiClientService $client;
    private AskAiResponseContractService $contractService;

    /**
     * Internal status of the most recent normalize() call.
     *
     * Set at every branch of normalize() so the runner can read a structured
     * outcome rather than inferring meaning from a null return value.
     *
     * Possible values:
     *   matched    — OpenAI returned a canonical key in the approved list.
     *   unknown    — OpenAI returned "unknown" or {status:"unsupported"}.
     *   prohibited — OpenAI flagged the question as a prohibited topic.
     *   failed     — An operational failure occurred; inspect $lastError for detail.
     *   null       — normalize() has not been called yet (initial state).
     */
    private ?string $lastStatus = null;

    /**
     * Structured error code for the most recent failed normalize() call.
     *
     * Non-null only when $lastStatus === 'failed'. Possible values:
     *   rate_limited   — OpenAI returned HTTP 429 (rate limit exceeded).
     *   timeout        — Network-level timeout or connection failure.
     *   api_error      — Other non-retryable OpenAI API error.
     *   invalid_json   — Response content is not valid JSON.
     *   invalid_key    — OpenAI returned a key not in the approved knownFieldKeys list.
     *   empty_response — Response data is present but normalized_key is missing or empty.
     */
    private ?string $lastError = null;

    /**
     * The raw context path returned by OpenAI on the most recent normalize() call,
     * captured BEFORE the hallucination guard validates it against knownFieldKeys.
     *
     * Non-null only when OpenAI returned a 'matched' status with a context_path, or
     * the legacy normalized_key format contained a non-empty, non-unknown value.
     * Null on 'unsupported', 'prohibited', empty, or error responses.
     *
     * Used by AskAiRunnerV2Service to populate router_context_path in the debug trace.
     */
    private ?string $lastContextPath = null;

    public function __construct(
        OpenAiClientService $client,
        AskAiResponseContractService $contractService
    ) {
        $this->client          = $client;
        $this->contractService = $contractService;
    }

    /**
     * Whether the normalization feature is enabled per the ask_ai config flag.
     *
     * The flag defaults to false and must remain false until governance review
     * has been completed. See config/ask_ai.php for details.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) config('ask_ai.enable_openai_intent_normalization', false);
    }

    /**
     * Return the status of the most recent normalize() call.
     *
     * Values: 'matched' | 'unknown' | 'prohibited' | 'failed' | null (not yet called).
     * Read this after every normalize() call to distinguish operational failures
     * from legitimate "no match" outcomes.
     *
     * @return string|null
     */
    public function getLastStatus(): ?string
    {
        return $this->lastStatus;
    }

    /**
     * Return the structured error code for the most recent failed normalize() call.
     *
     * Non-null only when getLastStatus() === 'failed'. Values:
     *   rate_limited   — HTTP 429 or rate-limit error from OpenAI.
     *   timeout        — Request timed out before OpenAI responded.
     *   api_error      — Other transport or API-level error.
     *   invalid_json   — Response could not be decoded as valid JSON.
     *   invalid_key    — OpenAI returned a key not in the approved list (hallucination).
     *   empty_response — Response was missing or contained an empty/null normalized_key.
     *
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Return the raw context path returned by OpenAI on the most recent normalize() call,
     * captured before the hallucination guard validates it against knownFieldKeys.
     *
     * Non-null when OpenAI returned a matched status with a context_path (or the legacy
     * normalized_key format with a non-empty, non-unknown value), even if the hallucination
     * guard subsequently rejected it.
     *
     * Null on 'unsupported', 'prohibited', empty, or error responses.
     *
     * Used by AskAiRunnerV2Service to populate router_context_path in the debug trace,
     * enabling QA to distinguish between OpenAI returning a plausible-but-invalid path
     * versus returning "unsupported" outright.
     *
     * @return string|null
     */
    public function getLastContextPath(): ?string
    {
        return $this->lastContextPath;
    }

    /**
     * Per-call OpenAI transport constraints for intent normalization.
     *
     * The router returns a compact JSON object with status and optional context_path
     * (e.g. {"status":"matched","context_path":"listing.bedrooms"}). A 10-second timeout
     * and an 80-token ceiling are deliberately narrow: they protect overall API budget
     * and prevent a slow OpenAI response from blocking the main Ask AI pipeline on every
     * unsupported question. The 80-token budget accommodates the longer status+context_path
     * JSON shape without truncating values at long field key names.
     *
     * These values override the global ai.timeout_seconds and do NOT affect any other
     * OpenAiClientService caller.
     */
    private const CALL_OPTIONS = [
        'timeout_seconds' => 10,
        'max_tokens'      => 80,
    ];

    /**
     * Attempt to normalize a user question to a canonical context-path field key.
     *
     * Calls OpenAI with a strictly governed prompt enriched with role-filtered registry
     * metadata (labels and sample questions). OpenAI returns one of three JSON shapes:
     *   {"status":"matched","context_path":"<key>"}  — a specific approved field was matched
     *   {"status":"unsupported"}                     — no approved field matches
     *   {"status":"prohibited"}                      — question touches a prohibited topic
     *
     * The legacy response format {"normalized_key":"<key>"} is also accepted for backward
     * compatibility. Any other response, exception, or timeout results in null.
     *
     * After every call, getLastStatus(), getLastError(), and getLastContextPath() reflect
     * the outcome:
     *   - matched    → the canonical key is returned; getLastError() is null.
     *   - unknown    → OpenAI returned "unsupported" or the legacy "unknown".
     *   - prohibited → OpenAI flagged the question as prohibited; null returned.
     *   - failed     → operational failure; getLastError() has the reason.
     *
     * The raw context_path from OpenAI (before the hallucination guard) is stored in
     * getLastContextPath() for observability, even when the guard rejects the path.
     *
     * Returns null when:
     *   - The question or knownFieldKeys are empty.
     *   - OpenAI returns 'unsupported' / 'unknown'.
     *   - OpenAI returns 'prohibited'.
     *   - OpenAI returns a key not present in knownFieldKeys (hallucination guard).
     *   - Any exception or timeout occurs during the OpenAI call.
     *
     * @param  string   $question       The raw user question string.
     * @param  string[] $knownFieldKeys Canonical context paths OpenAI may choose from.
     * @param  string   $role           Listing type ('seller','buyer','landlord','tenant').
     *                                  When provided, the prompt is enriched with role-filtered
     *                                  registry labels and sample questions.
     * @return string|null              A matched canonical path, or null when no match.
     */
    public function normalize(string $question, array $knownFieldKeys, string $role = ''): ?string
    {
        $this->lastStatus      = null;
        $this->lastError       = null;
        $this->lastContextPath = null;

        if ($question === '' || empty($knownFieldKeys)) {
            $this->lastStatus = 'unknown';
            return null;
        }

        try {
            $payload = $this->buildPayload($question, $knownFieldKeys, $role);
            $result  = $this->client->send($payload, self::CALL_OPTIONS);
            $data    = $result['data'] ?? [];

            // ----------------------------------------------------------------
            // Parse the router response.
            //
            // Primary (new) format: {status, context_path}
            //   status = 'matched'    → validated context_path is returned
            //   status = 'unsupported'→ null (no approved field matches)
            //   status = 'prohibited' → null (fair-housing or prohibited topic)
            //
            // Legacy fallback format: {normalized_key: "<key>"|"unknown"}
            //   Used by test mocks and earlier versions of the prompt.
            // ----------------------------------------------------------------
            if (isset($data['status'])) {
                $status = $data['status'];

                if ($status === 'prohibited') {
                    $this->lastStatus = 'prohibited';
                    return null;
                }

                if ($status === 'unsupported') {
                    $this->lastStatus = 'unknown';
                    return null;
                }

                if ($status === 'matched') {
                    $contextPath = $data['context_path'] ?? null;

                    if (!is_string($contextPath) || $contextPath === '') {
                        $this->lastStatus = 'failed';
                        $this->lastError  = 'empty_response';
                        return null;
                    }

                    // Capture raw path BEFORE the hallucination guard rejects it.
                    $this->lastContextPath = $contextPath;

                    // Hallucination guard: reject any path not in the approved registry.
                    if (!in_array($contextPath, $knownFieldKeys, true)) {
                        $this->lastStatus = 'failed';
                        $this->lastError  = 'invalid_key';
                        return null;
                    }

                    $this->lastStatus = 'matched';
                    return $contextPath;
                }

                // Unknown status value — treat as an empty/malformed response.
                $this->lastStatus = 'failed';
                $this->lastError  = 'empty_response';
                return null;
            }

            // ----------------------------------------------------------------
            // Legacy format: normalized_key field.
            // ----------------------------------------------------------------
            $normalizedKey = $data['normalized_key'] ?? null;

            if ($normalizedKey === 'unknown') {
                $this->lastStatus = 'unknown';
                return null;
            }

            if (!is_string($normalizedKey) || $normalizedKey === '') {
                $this->lastStatus = 'failed';
                $this->lastError  = 'empty_response';
                return null;
            }

            // Capture raw path BEFORE the hallucination guard rejects it.
            $this->lastContextPath = $normalizedKey;

            // Hallucination guard: reject any key not in the approved list.
            if (!in_array($normalizedKey, $knownFieldKeys, true)) {
                $this->lastStatus = 'failed';
                $this->lastError  = 'invalid_key';
                return null;
            }

            $this->lastStatus = 'matched';
            return $normalizedKey;

        } catch (\Throwable $e) {
            $this->lastStatus = 'failed';
            $this->lastError  = $this->classifyThrowable($e);
            return null;
        }
    }

    /**
     * Classify a caught Throwable into a structured error code.
     *
     * Inspects the exception code, the getPrevious() chain, and the message to
     * distinguish operational failure categories without importing any OpenAI SDK
     * class directly. Code-based and chain-based checks take priority; message-based
     * pattern matching serves as a fallback for exceptions from validateResponse()
     * and other places that embed HTTP status or error type in the message.
     *
     * No API key, payload content, or PII is included in the returned code.
     *
     * @param  \Throwable $e
     * @return string       One of: rate_limited | timeout | invalid_json | api_error
     */
    private function classifyThrowable(\Throwable $e): string
    {
        $message = $e->getMessage();
        $code    = $e->getCode();

        // HTTP 429 surfaced as the exception code on non-retryable fast-fail.
        if ($code === 429) {
            return 'rate_limited';
        }

        // Inspect the wrapped original exception via getPrevious().
        $previous = method_exists($e, 'getPrevious') ? $e->getPrevious() : null;

        if ($previous !== null) {
            $prevClass = get_class($previous);
            $prevCode  = method_exists($previous, 'getCode') ? $previous->getCode() : 0;

            if (str_contains($prevClass, 'TransporterException')) {
                return 'timeout';
            }

            if ($prevCode === 429) {
                return 'rate_limited';
            }

            if (str_contains($prevClass, 'ErrorException')) {
                return 'api_error';
            }

            if (str_contains($prevClass, 'UnserializableResponse')) {
                return 'invalid_json';
            }
        }

        // Message-based fallback for HTTP status codes and error types embedded in messages.
        $lower = strtolower($message);

        if (str_contains($lower, '429')
            || str_contains($lower, 'rate limit')
            || str_contains($lower, 'rate_limit')
            || str_contains($lower, 'too many requests')
        ) {
            return 'rate_limited';
        }

        if (str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'time out')
            || str_contains($lower, 'curl error 28')
            || str_contains($lower, 'operation_timedout')
        ) {
            return 'timeout';
        }

        if (str_contains($message, 'not valid JSON')
            || str_contains($message, 'could not be parsed')
            || str_contains($lower, 'json')
            || str_contains($lower, 'parse error')
            || str_contains($lower, 'syntax error')
        ) {
            return 'invalid_json';
        }

        return 'api_error';
    }

    /**
     * Build the canonical field-key registry from two authoritative sources:
     *
     *   (a) listing_facts allowed_context paths from AskAiResponseContractService
     *       (e.g. 'listing.bedrooms', 'listing.hoa_fee', 'listing.rent_amount').
     *       The bare 'faq_answers' path is excluded — only concrete leaf paths are included.
     *
     *   (b) faq_answers.* keys assembled from the four FAQ config files
     *       (e.g. 'faq_answers.hvac_system_age', 'faq_answers.roof_age_and_condition').
     *
     * Only paths the prompt builder can already pass through are included.
     *
     * @return string[]
     */
    public function buildKnownFieldKeys(): array
    {
        $listingPaths = $this->contractService->getListingFactsAllowedPaths();

        // Exclude the bare 'faq_answers' umbrella path — we expand it to leaf keys below.
        $filteredListingPaths = array_values(array_filter(
            $listingPaths,
            static function (string $path): bool {
                return $path !== 'faq_answers';
            }
        ));

        $faqKeys = $this->buildFaqAnswerKeys();

        return array_values(array_unique(array_merge($filteredListingPaths, $faqKeys)));
    }

    /**
     * Build the prompt payload for the OpenAI intent normalization / field router call.
     *
     * When $role is provided, the payload includes an enriched field registry pulled
     * from AskAiFieldQuestionRegistryService::routerEntries($role) — each entry carries
     * the canonical context path, a human-readable label, and up to two sample questions
     * that show how a user might phrase a question about that field naturally. This
     * allows OpenAI to match any informal, colloquial, or figurative phrasing to the
     * correct path without keyword training.
     *
     * OpenAI must return one of exactly three JSON shapes:
     *   {"status":"matched","context_path":"<exact entry from field_registry paths>"}
     *   {"status":"unsupported"}
     *   {"status":"prohibited"}
     *
     * The legacy response format {"normalized_key":"..."} is still accepted by the
     * normalize() parser for backward compatibility with test mocks, but new prompts
     * instruct OpenAI to use the status+context_path shape exclusively.
     *
     * @param  string   $question       The user question.
     * @param  string[] $knownFieldKeys The canonical field keys OpenAI may choose from.
     * @param  string   $role           Listing type for registry filtering (optional).
     * @return array
     */
    private function buildPayload(string $question, array $knownFieldKeys, string $role = ''): array
    {
        $registryContext = [];
        if ($role !== '') {
            try {
                $entries = AskAiFieldQuestionRegistryService::routerEntries($role);
                foreach ($entries as $path => $entry) {
                    $registryContext[] = [
                        'path'             => $path,
                        'label'            => $entry['label'],
                        'sample_questions' => $entry['sample_questions'],
                    ];
                }
            } catch (\Throwable $ignored) {
                // Registry unavailable (e.g. pure unit test without booted app). Fall
                // through — the prompt still contains field_keys as the fallback list.
                $registryContext = [];
            }
        }

        $instruction = implode(' ', [
            'You are a real estate field-key router.',
            'Users often ask about property features using informal, colloquial, or figurative language',
            'rather than technical terms.',
            'Given a user question and a registry of approved context paths with labels and sample questions,',
            'identify which single approved field the question is asking about.',
            'Common informal phrasings and the real estate concepts they represent:',
            '"solid covering overhead", "top covering of the house", "covering above the home",',
            '"overhead structure" → the roof (e.g. a roof-related field key if present in the registry).',
            'You MUST return ONLY one of these three JSON shapes — no other output is permitted:',
            '{"status":"matched","context_path":"<exact path from the approved registry>"}',
            '{"status":"unsupported"}',
            '{"status":"prohibited"}',
            'Use "matched" when one approved field clearly corresponds to the question.',
            'Use "unsupported" when no approved field matches the question.',
            'Use "prohibited" when the question asks about race, religion, national origin,',
            'sex, disability, familial status, or any other fair-housing protected characteristic.',
            'You MUST NOT generate a final answer to the user\'s question.',
            'You MUST NOT invent or infer any field not present in the approved registry.',
            'Your entire response must be a valid JSON object with no additional text.',
        ]);

        $payload = [
            'task'        => 'intent_normalization_v2',
            'instruction' => $instruction,
            'question'    => $question,
            'field_keys'  => array_values($knownFieldKeys),
            'governance'  => 'Return only {"status":"matched","context_path":"<path>"} or {"status":"unsupported"} or {"status":"prohibited"}. The context_path MUST be an exact entry from field_registry paths or field_keys. No other output is permitted.',
        ];

        if (!empty($registryContext)) {
            $payload['field_registry'] = $registryContext;
        }

        return $payload;
    }

    /**
     * Extract faq_answers.* canonical keys from the four FAQ config files.
     *
     * Seller, Landlord, and Buyer FAQs use a nested questions/addons structure:
     *   'questions' => [ 'Category' => [ 'key' => [...] ] ]
     *   'addons'    => [ 'addon_name' => [ 'questions' => [ 'key' => [...] ] ] ]
     *
     * Tenant FAQ uses a flat array of objects, each with a 'key' field:
     *   'questions' => [ ['key' => 'faq_q1', ...], ... ]
     *
     * @return string[]
     */
    private function buildFaqAnswerKeys(): array
    {
        $keys = [];

        foreach (['ai_faq_seller', 'ai_faq_landlord', 'ai_faq_buyer'] as $configName) {
            try {
                $config = config($configName, []) ?? [];
            } catch (\Throwable $e) {
                // config() is unavailable outside a bootstrapped Laravel application
                // (e.g. in pure unit tests). Degrade gracefully to an empty array.
                $config = [];
            }

            foreach ($config['questions'] ?? [] as $category => $questions) {
                foreach ($questions as $key => $definition) {
                    $keys[] = 'faq_answers.' . $key;
                }
            }

            foreach ($config['addons'] ?? [] as $addonName => $addon) {
                foreach ($addon['questions'] ?? [] as $key => $definition) {
                    $keys[] = 'faq_answers.' . $key;
                }
            }
        }

        try {
            $tenantConfig = config('tenant_ai_faq', []) ?? [];
        } catch (\Throwable $e) {
            $tenantConfig = [];
        }

        foreach ($tenantConfig['questions'] ?? [] as $item) {
            $key = $item['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = 'faq_answers.' . $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
