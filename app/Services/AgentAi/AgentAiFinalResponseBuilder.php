<?php

namespace App\Services\AgentAi;

/**
 * AgentAiFinalResponseBuilder
 *
 * Normalizes raw OpenAI adapter output into the canonical V2 public response
 * contract. Handles status routing, text normalization, disclosure injection,
 * and source attribution formatting.
 *
 * GOVERNANCE: Pure transformation. No external calls. No DB writes.
 */
class AgentAiFinalResponseBuilder
{
    /**
     * Build the final public response from a prompt package and adapter result.
     *
     * @param  array $promptPackage  Output of AgentAiPromptBuilder::build()
     * @param  array $adapterResult  Output of AgentAiOpenAiOrchestrator::call()
     * @param  array $options
     * @return array{
     *   success: bool,
     *   status: string,
     *   answer: string|null,
     *   disclosures: string|null,
     *   source_attribution: string|null,
     *   follow_up_questions: array,
     *   error: string|null,
     * }
     */
    public function build(array $promptPackage, array $adapterResult, array $options = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
