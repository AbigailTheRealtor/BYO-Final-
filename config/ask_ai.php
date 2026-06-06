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

];
