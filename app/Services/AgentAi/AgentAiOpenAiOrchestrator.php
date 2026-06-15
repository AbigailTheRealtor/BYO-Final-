<?php

namespace App\Services\AgentAi;

/**
 * AgentAiOpenAiOrchestrator
 *
 * Sends a governed prompt package to OpenAI and returns the raw response.
 * Handles retry logic, timeouts, and token usage logging.
 *
 * GOVERNANCE:
 *   - MUST NEVER invent or hallucinate data.
 *   - MUST NEVER embed API keys in source code.
 *   - MUST NEVER be called outside the V2 pipeline.
 *   - All calls are logged via AgentAiUsageLogger (Build 3).
 */
class AgentAiOpenAiOrchestrator
{
    /**
     * Send a prompt package to OpenAI and return the raw adapter response.
     *
     * @param  array $promptPackage  Output of AgentAiPromptBuilder::build()
     * @param  array $options        Optional overrides (model, max_tokens, temperature, etc.)
     * @return array{
     *   success: bool,
     *   raw_content: string|null,
     *   usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}|null,
     *   model: string|null,
     *   error: string|null,
     * }
     */
    public function call(array $promptPackage, array $options = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
