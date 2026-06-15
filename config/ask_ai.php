<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ask AI API Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of Ask AI API requests allowed per minute per authenticated
    | user (or per IP address for anonymous/web requests). Applied to both the
    | web route (POST /ask-ai/ask) and the Sanctum API route (POST /api/ask-ai/ask)
    | via the named 'ask-ai-api' rate limiter.
    |
    */
    'rate_limit_per_minute' => 20,

    /*
    |--------------------------------------------------------------------------
    | Ask AI — Feature Flags
    |--------------------------------------------------------------------------
    */

    /*
     * Enable OpenAI-powered intent normalization as a pre-classification step.
     *
     * When true, questions that the deterministic classifier cannot match
     * (question_type = 'unsupported') are passed to AskAiIntentNormalizerService,
     * which calls OpenAI with a strictly governed prompt to map the question to
     * a known canonical field key. If a valid field key is returned, the pipeline
     * re-enters the listing_facts path using that key; the final answer is still
     * generated exclusively from allowed context paths through the existing
     * contract and prompt-builder governance — no facts are invented.
     *
     * GOVERNANCE REVIEW REQUIRED before enabling in production.
     * This flag must remain false in production until the governance review
     * described in the Ask AI Phase 2 Task C design document has been completed
     * and approved. Dev/staging environments may set
     * ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION=true in their .env file.
     */
    'enable_openai_intent_normalization' => env('ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION', false),

    /*
     * Enable description-derived fallback answers for listing.* fields that are null or absent.
     *
     * When true, if Guard B detects a null/missing listing.* field value AND the listing has a
     * non-empty description, the runner will make a targeted OpenAI call using only the listing
     * description as context. If OpenAI finds a relevant answer in the description, that answer
     * is returned with description_fallback source attribution; otherwise the normal
     * 'insufficient_context' response fires.
     *
     * This flag defaults to false (off) in all environments. Enable via the env file only after
     * governance review — the description call goes to OpenAI and costs tokens.
     *
     * Dev/staging: set ASK_AI_ENABLE_DESCRIPTION_FALLBACK=true in .env.
     */
    'enable_description_fallback' => env('ASK_AI_ENABLE_DESCRIPTION_FALLBACK', false),

    /*
     * Enable Agent AI Assistant V2.
     *
     * When true, the V2 routes (POST /agent-ai/ask and POST /agent-ai/session/start)
     * are active and return responses from the V2 pipeline. When false (the default),
     * those routes return 404 and the V1 pipeline is completely unchanged.
     *
     * V1 Ask AI routes, controllers, and service classes are never affected by this flag.
     *
     * Enable via: AGENT_AI_ASSISTANT_V2=true in .env (dev/staging only).
     * GOVERNANCE REVIEW REQUIRED before enabling in production.
     */
    'agent_ai_v2_enabled' => env('AGENT_AI_ASSISTANT_V2', false),

    /*
    |--------------------------------------------------------------------------
    | Agent AI V2 — Model Configuration
    |--------------------------------------------------------------------------
    |
    | Two model tiers are supported: a "fast" model for straightforward questions
    | and a "reasoning" model for complex multi-step analysis. Model names are
    | configuration-driven — never hardcoded in application logic.
    |
    | fast_model    — Used for simple factual questions (property details, etc.)
    |                 Default: gpt-4o-mini
    | reasoning_model — Used for complex analysis, comparison, or multi-part
    |                   questions detected by AgentAiOpenAiOrchestrator.
    |                   Default: gpt-4o
    |
    | Set AGENT_AI_FAST_MODEL and AGENT_AI_REASONING_MODEL in .env to override.
    | GOVERNANCE REVIEW REQUIRED before changing models in production.
    |
    */
    'agent_ai_fast_model'      => env('AGENT_AI_FAST_MODEL', 'gpt-4o-mini'),
    'agent_ai_reasoning_model' => env('AGENT_AI_REASONING_MODEL', 'gpt-4o'),

    /*
    | Agent AI V2 — Generation Parameters
    |
    | max_tokens    — Maximum completion tokens per response (caps verbosity).
    | temperature   — Sampling temperature. Low value = more deterministic answers.
    | timeout_secs  — Per-request timeout in seconds before treating as failed.
    */
    'agent_ai_max_tokens'      => (int)   env('AGENT_AI_MAX_TOKENS', 1024),
    'agent_ai_temperature'     => (float) env('AGENT_AI_TEMPERATURE', 0.3),
    'agent_ai_timeout_seconds' => (int)   env('AGENT_AI_TIMEOUT_SECONDS', 60),

    /*
    | Agent AI V2 — Conversation History
    |
    | verbatim_turns — Number of recent turns passed verbatim to the prompt.
    |                  Older turns beyond this limit are condensed into a
    |                  single "Prior conversation summary:" prefix line.
    */
    'agent_ai_verbatim_turns' => (int) env('AGENT_AI_VERBATIM_TURNS', 6),

    /*
    | Agent AI V2 — Maximum Context Token Budget
    |
    | Total estimated token budget for the assembled prompt (system + context +
    | history + question). When the estimate exceeds this value, the oldest
    | history messages are trimmed by AgentAiPromptBuilder::enforceTokenBudget()
    | until the prompt fits. System message, context block, and current question
    | are always preserved. Set conservatively below actual model limits to leave
    | headroom for the completion (max_tokens above).
    */
    'agent_ai_max_context_tokens' => (int) env('AGENT_AI_MAX_CONTEXT_TOKENS', 6000),

    /*
    | Agent AI V2 — Fallback Response
    |
    | Returned to the caller when OpenAI is unavailable, times out, or returns
    | an API error. Must not expose internal details or model names.
    */
    'agent_ai_fallback_message' => env(
        'AGENT_AI_FALLBACK_MESSAGE',
        'I can help connect you with the agent to confirm — please reach out directly for this information.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Agent AI V2 — Lead Scoring Point Table (Build 5)
    |--------------------------------------------------------------------------
    |
    | Canonical point values for each scoring signal. AgentAiLeadScoringService
    | reads from this configuration so thresholds can be tuned without code changes.
    |
    | Signal keys correspond to AgentAiLeadIntentDetector::SIGNAL_* constants.
    | Contact-field signals (phone_provided, email_provided) are also applied here.
    |
    | Notification thresholds:
    |   score >= 50  → dashboard notification card
    |   score >= 75  → dashboard card + email to agent
    |   score >= 90  → dashboard card + email + in-app nav badge
    |
    | Scores are capped at 100 in AgentAiLeadScoringService::accumulateForSession().
    */
    'agent_ai_lead_scoring' => [
        'points' => [
            'property_question'        => 5,
            'financial_question'       => 10,
            'showing_request'          => 25,
            'offer_question'           => 35,
            'submit_offer_intent'      => 50,
            'consultation_request'     => 40,
            'human_escalation_requested' => 15,
            'phone_provided'           => 20,
            'email_provided'           => 10,
        ],
        'thresholds' => [
            'dashboard_card'  => 50,
            'email'           => 75,
            'nav_badge'       => 90,
        ],
        'max_score' => 100,
    ],
];
