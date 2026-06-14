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
];
