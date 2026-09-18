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
 * Returns a structured result for every code path.
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
     * Output contract — always returns exactly these keys:
     *   success            bool         — true only on successful generation
     *   status             string       — 'generated' | 'blocked' | 'failed'
     *   raw_response       string|null  — JSON-encoded OpenAI response data, or null
     *   model              string|null  — model version string used, or null
     *   error              string|null  — null on success/blocked; error message on failure
     *   prompt_tokens      int          — prompt token count (0 when blocked or failed)
     *   completion_tokens  int          — completion token count (0 when blocked or failed)
     *   total_tokens       int          — total token count (0 when blocked or failed)
     *   api_request_id     string|null  — OpenAI x-request-id header value, or null
     *
     * @param  array $promptPackage  The prompt package built by AskAiInternalRunnerService.
     * @return array
     */
    /**
     * Is the Ask AI property-question experience permitted to reach a language model?
     *
     * `false`, and it is a CODE CONSTANT rather than a config flag or an environment
     * variable on purpose — the same shape as the MLS-remarks processing approval
     * constant, which is likewise flipped only by a reviewed code change. The product
     * requirement is that Ask AI is deterministic: a supported fact is answered from the
     * registry, and an unsupported question is refused. A switch that an operator could
     * flip, or that a stale deployment secret could flip back, would make "no LLM" a
     * property of an environment rather than a property of the product. Changing this is
     * a reviewed code change and a product decision, not an ops action.
     *
     * WHY THE GATE IS HERE, AT THE ADAPTER, AND NOT IN THE RUNNER
     * -----------------------------------------------------------
     * `AskAiRunnerV2Service` is ~6,000 lines and references the OpenAI path in a dozen
     * places — prompt assembly, three fallback branches, outcome categorisation and
     * telemetry. Excising it there would be a large, risky edit across code whose other
     * behaviours (guardrails, compliance, usage logging) the product still depends on.
     * This class is the ONLY place an Ask AI prompt becomes an outbound model request —
     * `$this->client->send()` below is the single call — so refusing here makes every
     * caller unreachable to the provider at once, with one branch instead of a dozen.
     *
     * The refusal deliberately reuses the EXISTING `status => 'blocked'` shape rather
     * than inventing one. The runner already handles a blocked adapter result (it is what
     * a non-`prompt_ready` package produced), so the deterministic unsupported-question
     * response the caller ends up emitting is a path that already existed and is already
     * covered — not a new one written under a deadline.
     */
    public const LLM_ANSWERING_APPROVED = false;

    public function generate(array $promptPackage): array
    {
        // HARD GATE — before the prompt is inspected and before any client is touched.
        // Nothing below this line can be reached while the constant is false, so no Ask AI
        // surface (shopper card, owner free-text modal, admin test page, internal runner or
        // the sanctum API) has a runtime path to OpenAI.
        if (self::LLM_ANSWERING_APPROVED !== true) {
            return [
                'success'           => false,
                'status'            => 'blocked',
                'raw_response'      => null,
                'model'             => null,
                'error'             => 'llm_answering_not_approved',
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'total_tokens'      => 0,
                'api_request_id'    => null,
            ];
        }

        $status = $promptPackage['status'] ?? '';

        if ($status !== 'prompt_ready') {
            return [
                'success'           => false,
                'status'            => 'blocked',
                'raw_response'      => null,
                'model'             => null,
                'error'             => null,
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'total_tokens'      => 0,
                'api_request_id'    => null,
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
                    'success'           => false,
                    'status'            => 'failed',
                    'raw_response'      => null,
                    'model'             => null,
                    'error'             => 'json_encode failed on OpenAI response data',
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'api_request_id'    => null,
                ];
            }

            return [
                'success'           => true,
                'status'            => 'generated',
                'raw_response'      => $encoded,
                'model'             => $result['model']             ?? null,
                'error'             => null,
                'prompt_tokens'     => $result['prompt_tokens']     ?? 0,
                'completion_tokens' => $result['completion_tokens'] ?? 0,
                'total_tokens'      => $result['total_tokens']      ?? 0,
                'api_request_id'    => $result['api_request_id']    ?? null,
            ];

        } catch (\Throwable $e) {
            return [
                'success'           => false,
                'status'            => 'failed',
                'raw_response'      => null,
                'model'             => null,
                'error'             => $e->getMessage(),
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'total_tokens'      => 0,
                'api_request_id'    => null,
            ];
        }
    }
}
