<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;

/**
 * AgentAiPromptBuilder
 *
 * Converts an assembled context payload and a user question into a governed
 * prompt package ready for AgentAiOpenAiOrchestrator.
 *
 * GOVERNANCE: No external calls. No data invention. No protected class references.
 * All system instructions must include attribution and disclosure requirements.
 */
class AgentAiPromptBuilder
{
    /**
     * Build a governed prompt package from context + question.
     *
     * @param  array               $context   Output of AgentAiContextBuilder::build()
     * @param  string              $question  User's raw question
     * @param  AgentAiContextScope $scope
     * @param  array               $options
     * @return array{
     *   status: string,
     *   messages: array,
     *   token_estimate: int,
     *   governance_flags: array,
     * }
     */
    public function build(array $context, string $question, AgentAiContextScope $scope, array $options = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
