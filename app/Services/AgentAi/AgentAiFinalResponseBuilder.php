<?php

namespace App\Services\AgentAi;

/**
 * AgentAiFinalResponseBuilder
 *
 * Normalizes raw OpenAI adapter output into the canonical V2 public response
 * contract. Handles status routing, text normalization, disclosure injection,
 * and low-confidence detection.
 *
 * GOVERNANCE: Pure transformation. No external calls. No DB writes.
 * Must never include prompt contents, raw context blocks, API keys, or
 * internal model-selection details in the output.
 */
class AgentAiFinalResponseBuilder
{
    /**
     * Phrases that constitute confirmed-commitment language and must be removed
     * or softened. These represent the agent committing to an action on behalf
     * of the client — prohibited per governance rules.
     */
    private const PROHIBITED_COMMITMENT_PHRASES = [
        'I guarantee'         => 'I can try to help with',
        'I promise'           => 'I will aim to',
        'I will definitely'   => 'I will aim to',
        'I can definitely'    => 'I may be able to',
        'you will receive'    => 'you may receive',
        'this will happen'    => 'this may happen',
        'I assure you'        => 'I can tell you',
        'rest assured'        => 'please note',
        'without a doubt'     => 'based on available information',
        'I can confirm'       => 'I can share that',
        'I am certain'        => 'I believe',
        'I am sure'           => 'I believe',
    ];

    /**
     * Low-confidence signal phrases that trigger escalate: true in the response.
     * These indicate the model is guessing or lacks sufficient context.
     */
    private const LOW_CONFIDENCE_PHRASES = [
        'I believe',
        'I think',
        'I\'m not sure',
        'I am not sure',
        'probably',
        'might be',
        'possibly',
        'I\'m unsure',
        'I am unsure',
        'not certain',
        'not entirely sure',
        'you may want to verify',
        'you should verify',
        'I cannot confirm',
        'I don\'t have that information',
        'I do not have that information',
        'I\'m unable to confirm',
        'I am unable to confirm',
        'reach out to the agent',
        'contact the agent',
        'ask the agent',
    ];

    /**
     * Legal/compliance trigger phrases. When the answer contains these, a
     * compliance disclaimer is appended.
     */
    private const COMPLIANCE_TRIGGERS = [
        'HOA',
        'homeowners association',
        'flood zone',
        'flood insurance',
        'zoning',
        'permit',
        'easement',
        'encumbrance',
        'lien',
        'title',
        'tax',
        'assessed value',
        'rental income',
        'lease term',
        'eviction',
        'fair housing',
        'discrimination',
        'legal',
        'attorney',
        'lawyer',
    ];

    private const COMPLIANCE_DISCLAIMER = 'Note: For legal, financial, or contractual matters, please consult the agent or a qualified professional directly.';

    /**
     * Build the final public response from a prompt package and orchestrator result.
     *
     * @param  array $promptPackage   Output of AgentAiPromptBuilder::build()
     * @param  array $adapterResult   Output of AgentAiOpenAiOrchestrator::call()
     * @param  array $options
     * @return array{
     *   success: bool,
     *   status: string,
     *   answer: string|null,
     *   escalate: bool,
     *   disclosures: string|null,
     *   tokens_used: int,
     * }
     */
    public function build(array $promptPackage, array $adapterResult, array $options = []): array
    {
        // ── Fallback: OpenAI call failed ─────────────────────────────────────
        if (!($adapterResult['success'] ?? false)) {
            $fallback = (string) config(
                'ask_ai.agent_ai_fallback_message',
                'I can help connect you with the agent to confirm — please reach out directly.'
            );

            return [
                'success'     => false,
                'status'      => 'fallback',
                'answer'      => $fallback,
                'escalate'    => true,
                'disclosures' => null,
                'tokens_used' => 0,
            ];
        }

        $rawContent = (string) ($adapterResult['raw_content'] ?? '');
        $tokensUsed = (int) (($adapterResult['usage']['total_tokens'] ?? null) ?? 0);

        // ── Normalize text ───────────────────────────────────────────────────
        $answer = $this->stripProhibitedLanguage($rawContent);
        $answer = trim($answer);

        if ($answer === '') {
            $fallback = (string) config(
                'ask_ai.agent_ai_fallback_message',
                'I can help connect you with the agent to confirm — please reach out directly.'
            );

            return [
                'success'     => false,
                'status'      => 'empty_response',
                'answer'      => $fallback,
                'escalate'    => true,
                'disclosures' => null,
                'tokens_used' => $tokensUsed,
            ];
        }

        // ── Low-confidence detection ─────────────────────────────────────────
        $escalate = $this->detectLowConfidence($answer);

        // Also escalate if governance flags from prompt builder indicate uncertainty
        $governanceFlags = $promptPackage['governance_flags'] ?? [];
        if (in_array('empty_context', $governanceFlags, true)) {
            $escalate = true;
        }

        // ── Compliance disclosure injection ──────────────────────────────────
        // When a regulated topic is detected, the disclaimer is appended
        // directly to the answer text so it is always surfaced to the visitor.
        $disclosures = $this->buildDisclosures($answer);
        if ($disclosures !== null) {
            $answer = $answer . "\n\n" . $disclosures;
        }

        return [
            'success'     => true,
            'status'      => 'answered',
            'answer'      => $answer,
            'escalate'    => $escalate,
            'disclosures' => $disclosures,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Replace prohibited commitment phrases with governance-compliant alternatives.
     * Case-sensitive replacement to preserve sentence structure.
     */
    private function stripProhibitedLanguage(string $text): string
    {
        foreach (self::PROHIBITED_COMMITMENT_PHRASES as $prohibited => $replacement) {
            $text = str_ireplace($prohibited, $replacement, $text);
        }
        return $text;
    }

    /**
     * Check whether the answer contains low-confidence signal phrases.
     */
    private function detectLowConfidence(string $answer): bool
    {
        $lower = mb_strtolower($answer);
        foreach (self::LOW_CONFIDENCE_PHRASES as $phrase) {
            if (str_contains($lower, mb_strtolower($phrase))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a compliance disclosure string when the answer touches regulated topics.
     * Returns null when no disclosure is triggered.
     */
    private function buildDisclosures(string $answer): ?string
    {
        $lower    = mb_strtolower($answer);
        $triggered = false;

        foreach (self::COMPLIANCE_TRIGGERS as $trigger) {
            if (str_contains($lower, mb_strtolower($trigger))) {
                $triggered = true;
                break;
            }
        }

        return $triggered ? self::COMPLIANCE_DISCLAIMER : null;
    }
}
