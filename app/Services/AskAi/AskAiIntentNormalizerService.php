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
     * Values: 'not_called', 'matched', 'unknown', 'failed'
     * Detailed failure reason is stored in $lastError.
     */
    private string $lastStatus = 'not_called';

    /**
     * Detailed failure reason for the most recent normalize() call, or null.
     *
     * Values when $lastStatus === 'failed':
     *   'rate_limited', 'timeout', 'api_error', 'invalid_json', 'invalid_key', 'empty_response'
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
     * Return the internal status of the most recent normalize() call.
     *
     * Values:
     *   'not_called'  — normalize() was not called or input guards blocked before OpenAI.
     *   'matched'     — OpenAI returned a canonical key that passed the hallucination guard.
     *   'unknown'     — OpenAI returned 'unknown' (no suitable key found).
     *   'failed'      — An operational failure occurred; see getLastError() for the reason.
     *
     * @return string
     */
    public function getLastStatus(): string
    {
        return $this->lastStatus;
    }

    /**
     * Return the detailed failure reason for the most recent normalize() call.
     *
     * Non-null only when getLastStatus() === 'failed'. Values:
     *   'rate_limited'   — HTTP 429 or rate-limit error from OpenAI.
     *   'timeout'        — Request timed out before OpenAI responded.
     *   'api_error'      — Other transport or API-level error.
     *   'invalid_json'   — Response could not be decoded as valid JSON.
     *   'invalid_key'    — OpenAI returned a key not in the approved list (hallucination).
     *   'empty_response' — Response was missing or contained an empty/null normalized_key.
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
     * Internal status is recorded after every branch and readable via
     * getLastStatus() / getLastError() for trace logging by the caller.
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
        $this->lastStatus = 'not_called';
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

            if (!is_string($normalizedKey) || $normalizedKey === '') {
                $this->lastStatus = 'failed';
                $this->lastError  = 'empty_response';
                return null;
            }

            if ($normalizedKey === 'unknown') {
                $this->lastStatus = 'unknown';
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
            $classified       = $this->classifyThrowable($e);
            $this->lastStatus = $classified['status'];
            $this->lastError  = $classified['error'];
            return null;
        }
    }

    /**
     * Classify a caught Throwable into a structured failure reason.
     *
     * Uses exception message pattern detection to distinguish timeout, rate-limit,
     * JSON parse, and generic API errors. This approach works with any exception
     * type (RuntimeException, ErrorException, etc.) without requiring a dependency
     * on the OpenAI PHP SDK's specific exception hierarchy.
     *
     * @param  \Throwable $e
     * @return array{status: string, error: string}
     */
    private function classifyThrowable(\Throwable $e): array
    {
        $message = strtolower($e->getMessage());

        // HTTP 429 / rate-limit signals
        if (str_contains($message, '429')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'rate_limit')
            || str_contains($message, 'too many requests')
        ) {
            return ['status' => 'failed', 'error' => 'rate_limited'];
        }

        // Timeout / connection-timeout signals
        if (str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'time out')
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'operation_timedout')
        ) {
            return ['status' => 'failed', 'error' => 'timeout'];
        }

        // JSON decode / parse failure signals
        if (str_contains($message, 'json')
            || str_contains($message, 'parse error')
            || str_contains($message, 'decode')
            || str_contains($message, 'syntax error')
        ) {
            return ['status' => 'failed', 'error' => 'invalid_json'];
        }

        return ['status' => 'failed', 'error' => 'api_error'];
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
