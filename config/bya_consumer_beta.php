<?php

return [
    /*
     * Milestone 14 — BYA Limited Consumer Beta
     *
     * Controls consumer-facing access to compatibility reports.
     * Keep this false (the safe default) until the beta is ready to open.
     * Set BYA_COMPATIBILITY_CONSUMER_BETA_ENABLED=true in .env to enable.
     */
    'consumer_beta_enabled' => (bool) env('BYA_COMPATIBILITY_CONSUMER_BETA_ENABLED', false),
];
