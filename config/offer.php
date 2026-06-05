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
    | Default (env var absent): '*' — open to all authenticated users at launch.
    |
    | To restrict to specific accounts, set the environment variable:
    |   OFFER_PLAYOFF_ALLOWED_IDS=20,25,31
    |
    | To enable for all agents at launch, leave the env var unset or set:
    |   OFFER_PLAYOFF_ALLOWED_IDS=*
    |
    | To enable by a DB flag in future, replace the Gate logic in
    | AuthServiceProvider — no route or Blade changes required.
    |
    */
    'playoff_access' => [
        'allowed_user_ids' => (function () {
            $raw = env('OFFER_PLAYOFF_ALLOWED_IDS');

            if ($raw === null || trim($raw) === '' || trim($raw) === '*') {
                return '*';
            }

            return array_map('intval', array_filter(
                array_map('trim', explode(',', $raw))
            ));
        })(),
    ],
];
