<?php

namespace App\Services\AgentAi;

use Illuminate\Support\Facades\Log;
use OpenAI;

/**
 * AgentAiOpenAiOrchestrator
 *
 * Sends a governed prompt package to OpenAI and returns the raw response.
 * Routes between the configured "fast" model and "reasoning" model based on
 * question complexity detected from the prompt package.
 *
 * Model selection is ALWAYS configuration-driven (config/ask_ai.php).
 * Model names are NEVER hardcoded in application logic.
 *
 * GOVERNANCE:
 *   - MUST NEVER invent or hallucinate data.
 *   - MUST NEVER embed API keys in source code.
 *   - MUST NEVER be called outside the V2 pipeline.
 *   - MUST NEVER expose API keys, model names, or internal details in responses.
 *   - API errors return a graceful fallback — no unhandled exceptions escape.
 *   - Token usage is returned in the result for observability logging.
 *   - Context exceeding the configured budget is truncated by AgentAiPromptBuilder
 *     before this orchestrator is called.
 *
 * REASONING MODEL TRIGGERS (any one of these causes the reasoning model to be used):
 *   - Question contains comparison keywords: compare, vs, versus, difference, better, worse
 *   - Question contains analysis keywords: analyze, analyse, assess, evaluate, explain why
 *   - Question exceeds 300 characters
 *   - governance_flags includes 'history_summarized' (complex ongoing conversation)
 */
class AgentAiOpenAiOrchestrator
{
    /**
     * Keywords that indicate a complex question warranting the reasoning model.
     */
    private const REASONING_KEYWORDS = [
        'compare', 'vs', 'versus', 'difference between', 'better than', 'worse than',
        'analyze', 'analyse', 'assess', 'evaluate', 'explain why', 'why does', 'why is',
        'pros and cons', 'trade-off', 'tradeoff', 'recommendation', 'recommend',
    ];

    /**
     * Send a prompt package to OpenAI and return the raw adapter response.
     *
     * @param  array $promptPackage   Output of AgentAiPromptBuilder::build()
     * @param  array $options         Optional overrides: model, max_tokens, temperature, timeout_seconds
     * @return array{
     *   success: bool,
     *   raw_content: string|null,
     *   usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}|null,
     *   model: string|null,
     *   model_tier: string|null,
     *   error: string|null,
     * }
     */
    public function call(array $promptPackage, array $options = []): array
    {
        $status = $promptPackage['status'] ?? '';

        if ($status !== 'prompt_ready') {
            return $this->blocked("Prompt package status is '{$status}' — expected 'prompt_ready'.");
        }

        $messages = $promptPackage['messages'] ?? [];
        if (empty($messages)) {
            return $this->blocked('Prompt package contains no messages.');
        }

        $apiKey = (string) config('ai.api_key', '');
        if ($apiKey === '') {
            return $this->failed('OpenAI API key is not configured.');
        }

        // ── Model selection ──────────────────────────────────────────────────
        if (isset($options['model'])) {
            $model     = (string) $options['model'];
            $modelTier = 'override';
        } elseif ($this->requiresReasoning($promptPackage)) {
            $model     = (string) config('ask_ai.agent_ai_reasoning_model', 'gpt-4o');
            $modelTier = 'reasoning';
        } else {
            $model     = (string) config('ask_ai.agent_ai_fast_model', 'gpt-4o-mini');
            $modelTier = 'fast';
        }

        // ── Generation parameters ────────────────────────────────────────────
        $maxTokens   = $options['max_tokens']      ?? (int)   config('ask_ai.agent_ai_max_tokens',      1024);
        $temperature = $options['temperature']      ?? (float) config('ask_ai.agent_ai_temperature',     0.3);
        $timeout     = $options['timeout_seconds']  ?? (int)   config('ask_ai.agent_ai_timeout_seconds', 60);

        // ── Call OpenAI ──────────────────────────────────────────────────────
        try {
            $client = OpenAI::factory()
                ->withApiKey($apiKey)
                ->withHttpClient(new \GuzzleHttp\Client(['timeout' => $timeout]))
                ->make();

            $response = $client->chat()->create([
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
            ]);

            $rawContent = $response->choices[0]->message->content ?? '';

            $usage = [
                'prompt_tokens'     => (int) ($response->usage->promptTokens     ?? 0),
                'completion_tokens' => (int) ($response->usage->completionTokens ?? 0),
                'total_tokens'      => (int) ($response->usage->totalTokens      ?? 0),
            ];

            Log::info('AgentAiOpenAiOrchestrator: call succeeded', [
                'model'      => $model,
                'model_tier' => $modelTier,
                'usage'      => $usage,
                'scope'      => $promptPackage['scope'] ?? null,
            ]);

            return [
                'success'     => true,
                'raw_content' => $rawContent,
                'usage'       => $usage,
                'model'       => $model,
                'model_tier'  => $modelTier,
                'error'       => null,
            ];

        } catch (\Throwable $e) {
            Log::error('AgentAiOpenAiOrchestrator: OpenAI call failed', [
                'error'      => $e->getMessage(),
                'model'      => $model,
                'model_tier' => $modelTier,
                'scope'      => $promptPackage['scope'] ?? null,
            ]);

            return $this->failed($e->getMessage());
        }
    }

    /**
     * Determine whether the prompt requires the reasoning model tier.
     *
     * Checks the last user message for complexity keywords and length.
     */
    private function requiresReasoning(array $promptPackage): bool
    {
        // Check governance flags from prompt builder
        $flags = $promptPackage['governance_flags'] ?? [];
        if (in_array('history_summarized', $flags, true)) {
            return true;
        }

        // Inspect the last user message (the current question)
        $messages    = $promptPackage['messages'] ?? [];
        $lastUser    = null;

        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastUser = $msg['content'] ?? '';
                break;
            }
        }

        if ($lastUser === null) {
            return false;
        }

        // Length threshold: > 300 characters suggests a complex question
        if (mb_strlen($lastUser) > 300) {
            return true;
        }

        // Keyword check (case-insensitive)
        $lower = mb_strtolower($lastUser);
        foreach (self::REASONING_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a blocked (pre-call gated) result with no network call made.
     */
    private function blocked(string $reason): array
    {
        return [
            'success'     => false,
            'raw_content' => null,
            'usage'       => null,
            'model'       => null,
            'model_tier'  => null,
            'error'       => $reason,
        ];
    }

    /**
     * Return a failed (post-call or configuration error) result.
     */
    private function failed(string $error): array
    {
        return [
            'success'     => false,
            'raw_content' => null,
            'usage'       => null,
            'model'       => null,
            'model_tier'  => null,
            'error'       => $error,
        ];
    }
}
