<?php

/**
 * PHPUnit bootstrap — neutralise third-party credentials before anything loads.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * `phpunit.xml` sets `<server name="GOOGLE_PLACES_API_KEY" value="" force="true"/>`,
 * which writes `$_SERVER`. It does **not** touch `getenv()`. On a host that injects
 * the credential as a real process environment variable — Replit does exactly this —
 * `getenv('GOOGLE_PLACES_API_KEY')` still returns the live key, so:
 *
 *   • `tests/TestCase::detectLiveGooglePlacesKey()` finds it and refuses to run,
 *     taking the entire suite down; and
 *   • any code reaching for `getenv()` directly would authenticate a billable call.
 *
 * PHPUnit applies its `<php>` block *after* this bootstrap, and Laravel's phpdotenv
 * uses an immutable repository that will not overwrite a value already present. So we
 * set the variable to an **empty string** here (rather than unsetting it, which would
 * let `.env` repopulate it) across all three lookup surfaces. Every later reader —
 * `getenv()`, `$_SERVER`, `$_ENV`, `env()`, `config()` — then sees a blank credential.
 *
 * This is the in-process equivalent of the documented
 * `GOOGLE_PLACES_API_KEY= php artisan test`, applied automatically so that no
 * developer and no CI job has to remember it. The `TestCase` guard remains in place
 * as a backstop and still fails closed.
 *
 * @see docs/architecture/SPATIAL-INTELLIGENCE-PLATFORM.md — INV-11 (erratum E-37 proposed,
 *      not yet accepted into Appendix B; this file is the reason it exists)
 * @see docs/investigations/Google-Places-Root-Cause-Analysis.md
 */
$byoBlankedCredentials = [
    'GOOGLE_PLACES_API_KEY' => '',
    'GOOGLE_PLACES_ENABLED' => 'false',
];

foreach ($byoBlankedCredentials as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name]    = $value;
    $_SERVER[$name] = $value;
}

unset($byoBlankedCredentials, $name, $value);

require __DIR__ . '/../vendor/autoload.php';
