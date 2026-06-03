<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate requests to the OpenAI API. Must be
    | stored exclusively in the environment secret management system — never
    | hardcoded in source code, committed in .env files, or stored in any
    | database, log, or cache layer. No default value is provided; a missing
    | key causes OpenAiClientService::validateRequest() to throw immediately.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Model Version
    |--------------------------------------------------------------------------
    |
    | The exact model version string sent in every API request. Wildcard or
    | alias identifiers that resolve to an unspecified version are prohibited
    | per Phase XA Section 2.3. The value must be a fully pinned version string
    | (e.g. "gpt-5-2025-11-01"). No default is provided; a missing model causes
    | OpenAiClientService::validateRequest() to throw immediately.
    |
    */

    'model' => env('OPENAI_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Prompt Template Version
    |--------------------------------------------------------------------------
    |
    | The version identifier of the prompt template to be included in every
    | generation audit record and in the report object's generation_metadata.
    | Must conform to the "property-dna-report-v{MAJOR}.{MINOR}" convention
    | defined in Phase XA Section 4.2. No default is provided; a missing
    | prompt_version causes OpenAiClientService::validateRequest() to throw.
    |
    */

    'prompt_version' => env('OPENAI_PROMPT_VERSION'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds the HTTP client will wait for a complete
    | response from the OpenAI API before treating the request as timed out.
    | A timeout is a retry-eligible failure per Phase XA Section 7.1. The
    | default of 90 seconds is conservative enough to allow five-section
    | report generation while preventing indefinite hangs.
    |
    */

    'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 90),

    /*
    |--------------------------------------------------------------------------
    | Maximum Retry Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of automatic retry attempts for a single generation call
    | (not counting the initial attempt). Per Phase XA Section 7.5 the total
    | number of attempts must not exceed 3 (1 initial + up to 2 retries when
    | max_retries = 2, or expressed as 3 total attempts when max_retries = 3).
    | The task spec sets max = 3 total; this env key controls that ceiling.
    |
    */

    'max_retries' => (int) env('OPENAI_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Ask AI — Per-Model Cost Rates
    |--------------------------------------------------------------------------
    |
    | Rate table used to estimate the USD cost of each Ask AI request.
    | Rates are sourced from ASK_AI_COST_TRACKING_SPEC_V1 Section 4.
    | Keys are OpenAI model identifiers; values are prompt and completion
    | costs per 1,000 tokens. Update whenever OpenAI publishes price changes.
    |
    | If a model returned by the API is not listed here, estimated_cost_usd
    | is stored as null and a warning is logged — the request is never blocked.
    |
    */

    'ask_ai_costs' => [
        'model_rates' => [
            'gpt-4o' => [
                'prompt_cost_per_1k_tokens'     => 0.005,
                'completion_cost_per_1k_tokens' => 0.015,
            ],
            'gpt-4-turbo' => [
                'prompt_cost_per_1k_tokens'     => 0.010,
                'completion_cost_per_1k_tokens' => 0.030,
            ],
            'gpt-3.5-turbo' => [
                'prompt_cost_per_1k_tokens'     => 0.0005,
                'completion_cost_per_1k_tokens' => 0.0015,
            ],
        ],
    ],

];
