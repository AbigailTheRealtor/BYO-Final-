<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BYA Compatibility General Availability — Feature Flag
    |--------------------------------------------------------------------------
    | When false (the default), the GA path is inactive. Beta path is controlled
    | independently by config/bya_consumer_beta.php.
    |
    | Set BYA_COMPATIBILITY_GA_ENABLED=true in .env when opening GA rollout.
    */
    'ga_enabled' => (bool) env('BYA_COMPATIBILITY_GA_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | BYA Compatibility Kill Switch
    |--------------------------------------------------------------------------
    | When true (the safe default), ALL consumer-facing compatibility access
    | (beta and GA paths) is immediately denied. Admin preview routes are
    | unaffected. Flip to false only when the platform is ready for consumers.
    |
    | Set BYA_COMPATIBILITY_KILL_SWITCH=false in .env to deactivate the switch.
    */
    'kill_switch' => (bool) env('BYA_COMPATIBILITY_KILL_SWITCH', true),

    /*
    |--------------------------------------------------------------------------
    | GA Rollout Percentage
    |--------------------------------------------------------------------------
    | Percentage (0–100) of eligible users who receive GA access via the
    | deterministic bucket check: abs(crc32(user_id)) % 100 < percentage.
    | 0 = no bucket-based access; 100 = all eligible users.
    | Users in allowed_user_ids bypass this check entirely.
    */
    'rollout_percentage' => (int) env('BYA_COMPATIBILITY_ROLLOUT_PERCENTAGE', 0),

    /*
    |--------------------------------------------------------------------------
    | GA Allowlist
    |--------------------------------------------------------------------------
    | Explicit user IDs that receive GA access regardless of rollout_percentage.
    | Store as a JSON array string in .env:
    |   BYA_COMPATIBILITY_ALLOWED_USER_IDS=[1,2,3]
    | An empty array means no allowlist entries (bucket-only rollout).
    */
    'allowed_user_ids' => json_decode(env('BYA_COMPATIBILITY_ALLOWED_USER_IDS', '[]'), true) ?? [],

];
