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

    /*
    |--------------------------------------------------------------------------
    | Bidding Period Feature Flag
    |--------------------------------------------------------------------------
    | When false (default), the "Bidding Period" auction type option is hidden
    | from all offer listing creation / edit forms.  All new listings default
    | to "Traditional".  Existing listings already saved with auction_type =
    | "Bidding Period" continue to function normally — this flag only controls
    | the creation UI.
    |
    | Set BIDDING_PERIOD_ENABLED=true in .env to show the option.
    */
    'bidding_period_enabled' => (bool) env('BIDDING_PERIOD_ENABLED', false),

];
