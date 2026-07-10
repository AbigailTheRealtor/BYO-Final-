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

/**
 * DATABASE ISOLATION — the suite must never reach the dev database.
 * ------------------------------------------------------------------
 * Same host problem as the Google credential above, different blast radius.
 *
 * `config/database.php` declares the `sqlite` connection with `'url' => env('DATABASE_URL')`,
 * and this host injects `DATABASE_URL=postgresql://.../heliumdb` as a process env var.
 * Laravel's `ConfigurationUrlParser` gives `url` **precedence over both `driver` and
 * `database`**, so the connection *named* `sqlite` resolved to PostgreSQL against
 * `heliumdb` — the live dev database (3,079 users; 529 seller_agent_auctions).
 *
 * `TestCase::assertSafeTestDatabase()` did not catch it: that guard reads
 * `config('database.default')` and `config(...sqlite.database)`, which are precisely the
 * two keys `url` overrides. Configuration said SQLite; the connection was Postgres.
 *
 * Blanking `DATABASE_URL` is sufficient and is the *safe* form of the fix.
 * `ConfigurationUrlParser::parseConfiguration()` does `$url = Arr::pull($config, 'url');
 * if (! $url) { return $config; }` — an empty string is falsy, so URL parsing is bypassed
 * entirely and the declared `driver`/`database` win. Unsetting the variable instead would
 * let `.env` repopulate it, exactly as for the Google key above.
 *
 * `DB_CONNECTION` and `DB_DATABASE` are forced alongside it. `config/database.php:18` reads
 * `env('DATABASE_URL') ? 'pgsql' : env('DB_CONNECTION', 'mysql')`, so with the URL blanked
 * the default falls through to `DB_CONNECTION` — which this host sets to `pgsql`.
 * Both must be forced, or the fix is only half applied.
 *
 * ⚠️  DO NOT "fix" this by repairing the `database` config key while leaving `url` in place.
 *     `RefreshDatabase::usingInMemoryDatabase()` reads that same overridden key; seeing
 *     `':memory:'` is the only reason it takes the non-destructive `migrate` branch rather
 *     than `migrate:fresh`. Neutralise `url`, or five test files begin dropping every table
 *     in `heliumdb` on the next run.
 *
 * Asserted by tests/Feature/Safeguards/TestDatabaseIdentityTest.php, which inspects the
 * **resolved connection object** rather than the config values this defect falsifies.
 *
 * @see docs/certification/TRACK-F-CHECKPOINT.md
 */
$byoIsolatedTestDatabase = [
    'DATABASE_URL'  => '',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE'   => ':memory:',
];

foreach ($byoBlankedCredentials + $byoIsolatedTestDatabase as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name]    = $value;
    $_SERVER[$name] = $value;
}

unset($byoBlankedCredentials, $byoIsolatedTestDatabase, $name, $value);

require __DIR__ . '/../vendor/autoload.php';
