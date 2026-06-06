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
     * This flag must remain false until the governance review described in
     * the Ask AI Phase 2 Task C design document has been completed and approved.
     */
    'enable_openai_intent_normalization' => false,
];
