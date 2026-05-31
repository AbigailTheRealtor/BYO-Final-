<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BYA Compatibility Hidden Beta — Feature Flag
    |--------------------------------------------------------------------------
    | When false (the default), the beta route returns 403 unconditionally.
    | Set to true only for internal allow-listed reviewers.
    |
    | Mirrors the structure of config/offer.php → playoff_access.
    */
    'hidden_beta_enabled' => (bool) env('BYA_COMPATIBILITY_HIDDEN_BETA_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | BYA Beta Allow-list
    |--------------------------------------------------------------------------
    | Explicit list of user IDs permitted to access the beta view.
    | Agents on this list bypass the agent-block rule.
    | An empty array means no one is allow-listed (beta effectively closed).
    */
    'allowed_user_ids' => [],

];
