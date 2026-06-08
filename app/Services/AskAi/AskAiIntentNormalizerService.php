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
     *   matched        — OpenAI returned a canonical key in the approved list.
     *   unknown        — OpenAI returned the literal string "unknown".
     *   failed         — An operational failure occurred; inspect $lastError for detail.
     *   null           — normalize() has not been called yet (initial state).
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
     * Values: 'matched' | 'unknown' | 'failed' | null (not yet called).
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
     * Per-call OpenAI transport constraints for intent normalization.
     *
     * Intent normalization requires only a single short JSON key back from OpenAI
     * (e.g. {"normalized_key":"listing.bedrooms"}). A 10-second timeout and a 60-token
     * ceiling are deliberately narrow: they protect overall API budget and prevent a slow
     * OpenAI response from blocking the main Ask AI pipeline on every unsupported question.
     * The 60-token budget (up from 20) ensures keys with longer path names are not
     * truncated mid-value.
     *
     * These values override the global ai.timeout_seconds and do NOT affect any other
     * OpenAiClientService caller.
     */
    private const CALL_OPTIONS = [
        'timeout_seconds' => 10,
        'max_tokens'      => 60,
    ];

    /**
     * Attempt to normalize a user question to a canonical context-path field key.
     *
     * Calls OpenAI with a strictly governed prompt. OpenAI may only return one
     * entry from $knownFieldKeys or the literal string 'unknown'. Any other
     * response, exception, or timeout results in null — never a fabricated key,
     * never a crash.
     *
     * After every call, getLastStatus() and getLastError() reflect the outcome:
     *   - matched        → the canonical key is returned; getLastError() is null.
     *   - unknown        → OpenAI returned "unknown"; getLastError() is null.
     *   - failed         → operational failure; getLastError() has the reason.
     * When the question or knownFieldKeys pre-conditions fail, both are reset to null.
     *
     * The call uses CALL_OPTIONS to enforce a short timeout and low token cap,
     * ensuring the normalization step is cheap and fast relative to the main pipeline.
     *
     * Returns null when:
     *   - The question or knownFieldKeys are empty.
     *   - OpenAI returns 'unknown' or omits 'normalized_key'.
     *   - OpenAI returns a key not present in knownFieldKeys (hallucination guard).
     *   - Any exception or timeout occurs during the OpenAI call.
     *
     * @param  string   $question       The raw user question string.
     * @param  string[] $knownFieldKeys Canonical context paths (e.g. 'listing.bedrooms',
     *                                  'faq_answers.hvac_system_age') OpenAI may choose from.
     * @return string|null              A matched canonical path, or null when no match.
     */
    public function normalize(string $question, array $knownFieldKeys): ?string
    {
        $this->lastStatus = null;
        $this->lastError  = null;

        if ($question === '' || empty($knownFieldKeys)) {
            $this->lastStatus = 'unknown';
            return null;
        }

        try {
            $payload = $this->buildPayload($question, $knownFieldKeys);
            $result  = $this->client->send($payload, self::CALL_OPTIONS);
            $data    = $result['data'] ?? [];

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
     * Build the prompt payload for the OpenAI intent normalization call.
     *
     * The governed prompt instructs OpenAI to return only a JSON object
     * with a single key 'normalized_key' whose value is exactly one entry from
     * the provided field_keys list or the literal string 'unknown'. OpenAI is
     * explicitly prohibited from generating a final answer or referencing any
     * protected class characteristics.
     *
     * @param  string   $question       The user question.
     * @param  string[] $knownFieldKeys The canonical field keys OpenAI may choose from.
     * @return array
     */
    private function buildPayload(string $question, array $knownFieldKeys): array
    {
        return [
            'task'        => 'intent_normalization_v1',
            'instruction' => implode(' ', [
                'You are a real estate field-key resolver.',
                'Users often ask about property features using informal, colloquial, or figurative language',
                'rather than technical terms.',
                'Given a user question about a property listing and a list of canonical field keys,',
                'identify the underlying real estate concept the question is about,',
                'then return the single field key from the provided list that best matches that concept.',
                'Common informal phrasings and the real estate concepts they represent:',
                '"solid covering overhead", "top covering of the house", "covering above the home",',
                '"overhead structure" → the roof (e.g. a roof-related field key if present in the list).',
                'Return ONLY a valid JSON object with exactly one key: "normalized_key".',
                'Its value must be exactly one entry from the provided field_keys list,',
                'OR the literal string "unknown" if no single field key matches.',
                'You MUST NOT generate a final answer to the question.',
                'You MUST NOT reference, infer, or imply any protected class characteristics',
                '(race, religion, national origin, sex, disability, familial status, or any similar category).',
                'Your entire response must be a valid JSON object with no additional text.',
            ]),
            'question'    => $question,
            'field_keys'  => array_values($knownFieldKeys),
            'governance'  => 'Return only {"normalized_key": "<one entry from field_keys or unknown>"}. No other output is permitted.',
        ];
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
