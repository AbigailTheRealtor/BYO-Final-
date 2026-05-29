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

];
