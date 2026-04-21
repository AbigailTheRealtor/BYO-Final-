<?php

return [
    'default_auto_approve' => true,

    /*
    |--------------------------------------------------------------------------
    | Offer Playoff Access Control
    |--------------------------------------------------------------------------
    | Single source of truth for which accounts may access Offer Playoff.
    | The 'offer-playoff' Gate reads this list.
    |
    | To enable for all agents at launch, set:
    |   'allowed_user_ids' => '*'
    |
    | To enable by a DB flag in future, replace the Gate logic in
    | AuthServiceProvider — no route or Blade changes required.
    |
    */
    'playoff_access' => [
        'allowed_user_ids' => [11],   // internal test account(s) only
    ],
];
