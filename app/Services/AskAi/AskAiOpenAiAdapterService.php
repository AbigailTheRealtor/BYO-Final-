<?php

namespace App\Services\AskAi;

use App\Services\Ai\OpenAiClientService;

/**
 * AskAiOpenAiAdapterService — Phase 4 OpenAI Gateway
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Gateway between a built prompt_package and the OpenAI API.
 * Accepts a prompt_package produced by AskAiInternalRunnerService and delegates
 * to OpenAiClientService only when the package status is 'prompt_ready'.
 * Returns a structured five-key result for every code path.
 *
 * This service MUST NEVER:
 *   - Hardcode or embed any OpenAI API key — the key is read exclusively from
 *     config('ai.api_key') via the injected OpenAiClientService.
 *   - Create, read, update, or delete any database record, migration, or schema.
 *   - Introduce any route, controller, Blade view, Livewire component, or
 *     JavaScript of any kind.
 *   - Log any OpenAI API key, Authorization header value, or PII.
 *   - Maintain conversation history or stateful session data.
 *   - Implement retry logic — that is owned exclusively by OpenAiClientService.
 *   - Know about PropertyDnaProfile, PropertyMarketingBriefService, or any other
 *     domain model or service beyond what is needed to call OpenAiClientService.
 *   - Generate prompt content, system instructions, or user messages.
 * ==================================================================================
 */
class AskAiOpenAiAdapterService
{
    private OpenAiClientService $client;

    public function __construct(OpenAiClientService $client)
    {
        $this->client = $client;
    }

    /**
     * Attempt to generate an AI response for a prompt package.
     *
     * Gate rule: only calls OpenAI when $promptPackage['status'] === 'prompt_ready'.
     * Any other status returns immediately with status='blocked' and no network call.
     *
     * Output contract — always returns exactly these five keys:
     *   success       bool         — true only on successful generation
     *   status        string       — 'generated' | 'blocked' | 'failed'
     *   raw_response  string|null  — JSON-encoded OpenAI response data, or null
     *   model         string|null  — model version string used, or null
     *   error         string|null  — null on success/blocked; error message on failure
     *
     * @param  array $promptPackage  The prompt package built by AskAiInternalRunnerService.
     * @return array
     */
    public function generate(array $promptPackage): array
    {
        $status = $promptPackage['status'] ?? '';

        if ($status !== 'prompt_ready') {
            return [
                'success'      => false,
                'status'       => 'blocked',
                'raw_response' => null,
                'model'        => null,
                'error'        => null,
            ];
        }

        try {
            $result = $this->client->send($promptPackage);

            $encoded = json_encode(
                $result['data'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if ($encoded === false) {
                return [
                    'success'      => false,
                    'status'       => 'failed',
                    'raw_response' => null,
                    'model'        => null,
                    'error'        => 'json_encode failed on OpenAI response data',
                ];
            }

            return [
                'success'      => true,
                'status'       => 'generated',
                'raw_response' => $encoded,
                'model'        => $result['model'] ?? null,
                'error'        => null,
            ];

        } catch (\Throwable $e) {
            return [
                'success'      => false,
                'status'       => 'failed',
                'raw_response' => null,
                'model'        => null,
                'error'        => $e->getMessage(),
            ];
        }
    }
}
