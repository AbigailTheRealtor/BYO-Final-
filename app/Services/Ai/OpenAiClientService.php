<?php

namespace App\Services\Ai;

use Exception;
use OpenAI;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Exceptions\UnserializableResponse;

/**
 * OpenAiClientService — Phase XB OpenAI Client Wrapper & Configuration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a LOW-LEVEL INFRASTRUCTURE WRAPPER ONLY. It reads configuration,
 * validates requests, sends structured payloads to the OpenAI chat completions API,
 * validates responses, implements retry logic, and returns parsed JSON plus audit
 * metadata. It is a neutral, auditable, policy-enforcing conduit.
 *
 * This service MUST NEVER:
 *   - Know about PropertyDnaProfile, PropertyMarketingBriefService,
 *     PropertyMarketingReadinessService, PropertyMarketingContextService,
 *     or any other domain model or service.
 *   - Generate prompt content, system instructions, or user messages.
 *   - Apply Fair Housing logic, attribution verification, or report contract
 *     validation beyond what is defined in this file.
 *   - Create, read, update, or delete any database record, migration, or schema.
 *   - Introduce any route, controller, Blade view, Livewire component, or
 *     JavaScript of any kind.
 *   - Log the OpenAI API key, any Authorization header value, or any PII.
 *   - Cache, session-persist, or statically store the API key — it must be
 *     read from config at call time so key rotation takes effect immediately.
 *   - Retry on non-retry-eligible failures: HTTP 401, 403, 400, or any HTTP
 *     code not in RETRY_ELIGIBLE_HTTP_CODES, invalid/non-JSON responses,
 *     or UnserializableResponse exceptions.
 *   - Attempt to repair, regex-parse, or partially accept a non-JSON response.
 * ==================================================================================
 */
class OpenAiClientService
{
    /**
     * Payload keys that are unconditionally prohibited from inclusion in any
     * AI generation request, per Phase XA Section 5.2 and Phase V Section 2.3.
     *
     * If any key in this list is present at ANY nesting depth in the payload
     * passed to send(), validateRequest() throws before the API is called.
     * Detection is recursive — nested sub-arrays are fully scanned.
     */
    private const PROHIBITED_PAYLOAD_KEYS = [
        'demographic',
        'race',
        'religion',
        'ethnicity',
        'disability',
        'family_status',
        'income_tier',
        'school_rating',
        'credit_score',
        'buyer_identity',
        'tenant_identity',
    ];

    /**
     * HTTP status codes from the OpenAI API that are eligible for automatic retry.
     *
     * Per Phase XA Section 7.1:
     *   408 — Request Timeout
     *   429 — Rate Limit
     *   500 / 502 / 503 / 504 — Transient server errors
     *
     * Any HTTP code NOT in this list results in an immediate, non-retried exception.
     * This list is the authoritative retry gate — only these exact codes are retried.
     */
    private const RETRY_ELIGIBLE_HTTP_CODES = [408, 429, 500, 502, 503, 504];

    /**
     * Build and return an OpenAI client instance using config values read at call time.
     *
     * The client is not cached in a property so that API key rotation takes effect
     * without requiring an application restart (Phase XA Section 3.2).
     */
    private function makeClient(): Client
    {
        $apiKey  = (string) config('ai.api_key', '');
        $timeout = (int)    config('ai.timeout_seconds', 90);

        return OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => $timeout]))
            ->make();
    }

    /**
     * Validate a payload array before it is dispatched to the OpenAI API.
     *
     * Asserts:
     *   1. API key is configured and non-empty.
     *   2. Model version string is configured and non-empty.
     *   3. Prompt version is configured and non-empty.
     *   4. Payload contains no prohibited key (Phase XA Section 5.2).
     *
     * Throws an Exception immediately on any failure — the API is never called.
     *
     * @param  array $payload  The associative array to be sent as AI request context.
     * @throws Exception
     */
    public function validateRequest(array $payload): void
    {
        $apiKey        = (string) config('ai.api_key', '');
        $model         = (string) config('ai.model', '');
        $promptVersion = (string) config('ai.prompt_version', '');

        if ($apiKey === '') {
            throw new Exception(
                'OpenAI API key is not configured. Set the OPENAI_API_KEY environment variable.'
            );
        }

        if ($model === '') {
            throw new Exception(
                'OpenAI model version is not configured. Set the OPENAI_MODEL environment variable.'
            );
        }

        if ($promptVersion === '') {
            throw new Exception(
                'OpenAI prompt version is not configured. Set the OPENAI_PROMPT_VERSION environment variable.'
            );
        }

        $foundKey = $this->findProhibitedKey($payload);

        if ($foundKey !== null) {
            throw new Exception(
                sprintf(
                    'Prohibited input key "%s" detected in AI generation payload. '
                    . 'This key is categorically prohibited per Phase XA Section 5.2 '
                    . 'and Phase V Fair Housing safeguards. The request has been aborted.',
                    $foundKey
                )
            );
        }
    }

    /**
     * Recursively scan an array at every nesting depth for prohibited keys.
     *
     * Returns the first prohibited key found (string), or null when the array
     * is clean. Traverses all nested array values so that prohibited keys cannot
     * be smuggled in through a sub-array structure.
     *
     * @param  array $data  The array to scan (top-level payload or any sub-array).
     * @return string|null  The first prohibited key found, or null if none.
     */
    private function findProhibitedKey(array $data): ?string
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::PROHIBITED_PAYLOAD_KEYS, true)) {
                return (string) $key;
            }

            if (is_array($value)) {
                $nested = $this->findProhibitedKey($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Validate a raw response value from the OpenAI API.
     *
     * Confirms that the response content is a non-empty string that decodes to
     * a valid PHP array. Throws immediately on any failure — no repair attempt,
     * no regex fallback, no partial result (Phase XA Section 6.1).
     *
     * IMPORTANT: This method throws a plain \Exception (not an SDK exception
     * subclass). The send() method does NOT catch plain \Exception, so any
     * exception thrown here propagates immediately out of the retry loop without
     * triggering a retry. Invalid/non-JSON responses are never retried.
     *
     * @param  mixed $raw  The raw content string from the OpenAI chat completion response.
     * @return array       The decoded PHP array.
     * @throws Exception
     */
    public function validateResponse(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            throw new Exception(
                'OpenAI response content is empty or not a string. '
                . 'The generation is treated as failed — no partial result is accepted.'
            );
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new Exception(
                sprintf(
                    'OpenAI response is not valid JSON: %s. '
                    . 'No regex fallback or repair will be attempted. '
                    . 'The generation is treated as failed.',
                    json_last_error_msg()
                )
            );
        }

        return $decoded;
    }

    /**
     * Send a payload to the OpenAI chat completions API and return parsed JSON
     * plus audit metadata.
     *
     * Calls validateRequest() before dispatching. Implements retry logic with
     * exponential back-off for a specific, narrow set of retry-eligible failures:
     *
     *   RETRIED:     ErrorException with HTTP code in RETRY_ELIGIBLE_HTTP_CODES
     *                (408, 429, 500, 502, 503, 504)
     *                TransporterException (network-level timeout / connection error)
     *
     *   NOT RETRIED: ErrorException with any other HTTP code (401, 403, 400, 404…)
     *                UnserializableResponse (OpenAI SDK parse failure — like invalid JSON)
     *                Exception from validateResponse() — bubbles up immediately uncaught
     *
     * Returns an array containing:
     *   - 'data'           => array    — The decoded JSON response from OpenAI.
     *   - 'model'          => string   — The exact model version string used.
     *   - 'prompt_version' => string   — The prompt template version from config.
     *   - 'attempt_count'  => int      — Total number of attempts made (1 = no retry needed).
     *   - 'requested_at'   => string   — UTC ISO 8601 timestamp when the call was initiated.
     *   - 'completed_at'   => string   — UTC ISO 8601 timestamp when the call completed.
     *
     * @param  array $payload  Associative array of approved Phase R/U/P context values.
     * @return array
     * @throws Exception  On non-retryable failure, exhausted retries, or invalid JSON response.
     */
    public function send(array $payload): array
    {
        $this->validateRequest($payload);

        $model         = (string) config('ai.model', '');
        $promptVersion = (string) config('ai.prompt_version', '');
        $maxRetries    = (int)    config('ai.max_retries', 3);

        $requestedAt   = now()->utc()->toIso8601String();
        $attemptCount  = 0;
        $lastException = null;

        while ($attemptCount < $maxRetries) {
            $attemptCount++;

            try {
                $client = $this->makeClient();

                $response = $client->chat()->create([
                    'model'           => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        [
                            'role'    => 'user',
                            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    ],
                ]);

                $rawContent = $response->choices[0]->message->content ?? '';

                // validateResponse() throws a plain \Exception on invalid JSON.
                // Plain \Exception is NOT caught by the SDK-specific catches below,
                // so it propagates immediately out of the retry loop. This enforces
                // the spec requirement that invalid/non-JSON responses throw immediately
                // with no retry, no repair, and no regex fallback.
                $decoded = $this->validateResponse($rawContent);

                $completedAt = now()->utc()->toIso8601String();

                return [
                    'data'           => $decoded,
                    'model'          => $model,
                    'prompt_version' => $promptVersion,
                    'attempt_count'  => $attemptCount,
                    'requested_at'   => $requestedAt,
                    'completed_at'   => $completedAt,
                ];

            } catch (ErrorException $e) {
                $httpCode = $e->getCode();

                if (!in_array($httpCode, self::RETRY_ELIGIBLE_HTTP_CODES, true)) {
                    throw new Exception(
                        sprintf(
                            'Non-retryable OpenAI API error (HTTP %d) on attempt %d: %s',
                            $httpCode,
                            $attemptCount,
                            $e->getMessage()
                        ),
                        $httpCode,
                        $e
                    );
                }

                $lastException = $e;

                if ($attemptCount < $maxRetries) {
                    $retryAfterSeconds = $this->resolveBackoffSeconds($e, $attemptCount);
                    sleep($retryAfterSeconds);
                }

            } catch (TransporterException $e) {
                // Network-level timeout / connection failure — retry-eligible per Phase XA §7.1.
                $lastException = $e;

                if ($attemptCount < $maxRetries) {
                    $retryAfterSeconds = $this->resolveBackoffSeconds($e, $attemptCount);
                    sleep($retryAfterSeconds);
                }

            } catch (UnserializableResponse $e) {
                // SDK-level parse failure. Treated as an invalid response — throw immediately,
                // no retry, consistent with the spec requirement for invalid JSON handling.
                throw new Exception(
                    sprintf(
                        'OpenAI response could not be parsed by the SDK on attempt %d: %s. '
                        . 'The generation is treated as failed with no retry.',
                        $attemptCount,
                        $e->getMessage()
                    ),
                    0,
                    $e
                );
            }
        }

        throw new Exception(
            sprintf(
                'OpenAI generation failed after %d attempt(s). Last error: %s',
                $attemptCount,
                $lastException ? $lastException->getMessage() : 'Unknown error'
            ),
            0,
            $lastException
        );
    }

    /**
     * Resolve how many seconds to wait before the next retry attempt.
     *
     * Reads the Retry-After header when the exception is an HTTP 429 and the
     * header is present. Falls back to exponential back-off with jitter for
     * all other retry-eligible failures, per Phase XA Section 7.4.
     *
     * Back-off formula: base * 2^(attempt-1) + jitter (0–1 s)
     * Attempt 1 → ~2 s, Attempt 2 → ~4 s, Attempt 3 → ~8 s (capped at 60 s).
     *
     * @param  Exception $e
     * @param  int       $attempt  1-based attempt number that just failed.
     * @return int                 Seconds to sleep before next attempt.
     */
    private function resolveBackoffSeconds(Exception $e, int $attempt): int
    {
        if ($e instanceof ErrorException && $e->getCode() === 429) {
            $message = $e->getMessage();

            if (preg_match('/retry.after[:\s]+(\d+)/i', $message, $matches)) {
                return (int) $matches[1];
            }
        }

        $base    = 2;
        $cap     = 60;
        $jitter  = mt_rand(0, 1000) / 1000;
        $backoff = min($cap, (int) ($base * (2 ** ($attempt - 1))) + $jitter);

        return (int) ceil($backoff);
    }
}
