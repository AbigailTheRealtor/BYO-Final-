<?php

namespace App\Services\AgentAi;

/**
 * AgentAiLeadScoringService
 *
 * Scores a captured lead based on engagement signals from the chat session
 * (question depth, CTA interactions, session length, etc.).
 *
 * GOVERNANCE: Read-only scoring. No external HTTP calls. No DB writes
 * beyond updating the score column on an existing lead record.
 */
class AgentAiLeadScoringService
{
    /**
     * Score a lead record.
     *
     * @param  int   $leadId       ID of the lead record to score
     * @param  array $signals      Engagement signals collected during the session
     * @return array{success: bool, score: int|null, tier: string|null, error: string|null}
     */
    public function score(int $leadId, array $signals): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
