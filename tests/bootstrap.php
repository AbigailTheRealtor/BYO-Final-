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

    // ── TEST DATABASE IDENTITY ───────────────────────────────────────────────
    //
    // `DATABASE_URL` is the whole ballgame, and it is why every previous attempt
    // to force SQLite failed. Two lines of `config/database.php` conspire:
    //
    //     'default' => env('DATABASE_URL') ? 'pgsql' : env('DB_CONNECTION', 'mysql'),
    //     'sqlite'  => [ 'driver' => 'sqlite', 'url' => env('DATABASE_URL'), ... ],
    //
    // The first makes a present `DATABASE_URL` override `DB_CONNECTION=sqlite`
    // outright. The second is the trap: Laravel's `ConfigurationUrlParser` lets a
    // connection's `url` override its own `driver` and `database`, so the connection
    // *named* `sqlite` resolves to `pgsql` against `heliumdb`. Forcing
    // `database.default = sqlite` at runtime therefore protects nothing, and a guard
    // that reads `config('database.connections.sqlite.database')` reads back the
    // `:memory:` it just wrote while the real PDO handle points at the shared
    // PostgreSQL dev database.
    //
    // Replit injects `DATABASE_URL`, `DB_CONNECTION=pgsql`, and `DB_DATABASE=heliumdb`
    // as SYSTEM environment variables. phpdotenv's ImmutableStringRepository refuses to
    // overwrite anything already set, so neither `.env.testing` nor phpunit.xml's
    // `<env>` (without `force`) can win. This file runs before the autoloader, before
    // Dotenv, and before Laravel — it is the only place that can.
    //
    // Blanked to '' rather than unset: an unset variable would simply be repopulated
    // from `.env`. An empty string is falsy to `env()`, so `default` falls through to
    // `DB_CONNECTION`, and `ConfigurationUrlParser` skips an empty `url` entirely.
    //
    // Asserted by tests/Feature/Safeguards/TestDatabaseIdentityTest.php, which inspects
    // the RESOLVED connection rather than the values written here.
    'DATABASE_URL'  => '',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE'   => ':memory:',

    // ── SPATIAL CLUSTER IDENTITY ─────────────────────────────────────────────
    //
    // `pgsql_spatial` (config/database.php) is a SEPARATE PostGIS connection that
    // reads only its own SPATIAL_* variables. The block above therefore does not
    // touch it, and until now nothing did: an ambient `SPATIAL_DATABASE_URL` was
    // inherited straight into the test process.
    //
    // That is not theoretical. `SpatialFirstSliceCategorySeeder::run()` fails
    // closed only while the connection is inert — its guard throws when both `url`
    // and `host` are empty. With a live DSN inherited, the guard passes, and
    // `SpatialFirstSliceSeederIsolationTest` stops testing isolation and instead
    // upserts `place_categories` into whatever that DSN points at. The suite has no
    // business reaching any real database.
    //
    // Blanked to '' rather than unset, for the same reason as `DATABASE_URL`: an
    // unset variable is simply repopulated from `.env`. Empty is falsy to `env()`,
    // so every SPATIAL_* key resolves null/empty and the connection is inert.
    'SPATIAL_DATABASE_URL' => '',
    'SPATIAL_PGHOST'       => '',
    'SPATIAL_PGPORT'       => '',
    'SPATIAL_PGDATABASE'   => '',
    'SPATIAL_PGUSER'       => '',
    'SPATIAL_PGPASSWORD'   => '',
    'SPATIAL_PGSSLMODE'    => '',

    // ── libpq CLIENT FALLBACK ────────────────────────────────────────────────
    //
    // Blanking SPATIAL_* alone would not be enough, and would arguably be worse.
    // Laravel's PostgresConnector builds its DSN as
    //
    //     $host = isset($host) ? "host={$host};" : '';
    //     $dsn  = "pgsql:{$host}dbname='{$database}'";
    //
    // so a null/blank host is OMITTED from the DSN rather than forced empty. libpq
    // then supplies its own defaults from the environment — PGHOST, PGDATABASE,
    // PGUSER. This host injects PGHOST=helium and PGDATABASE=heliumdb, so a
    // spatial connection attempt with no explicit host would land on the shared
    // APPLICATION database: the exact outcome the SQLite pinning above exists to
    // prevent, reached through a different door.
    //
    // These are blanked for the same reason `migration-tests.yml` asserts that no
    // inherited PG* variable can redirect `psql`. Nothing in the suite talks to
    // PostgreSQL — it runs on SQLite :memory: — so removing libpq's ambient
    // defaults costs the tests nothing and closes the fallback.
    'PGHOST'     => '',
    'PGHOSTADDR' => '',
    'PGPORT'     => '',
    'PGDATABASE' => '',
    'PGUSER'     => '',
    'PGPASSWORD' => '',
    'PGSERVICE'  => '',

    // ── CRITERIA LOCATION DNA RUNTIME FLAGS ──────────────────────────────────
    //
    // The suite must start from the SHIPPED CONFIG DEFAULTS, so that a test which
    // does not mention the cascade is testing the cascade as it ships. Roughly
    // twenty tests assert exactly that — `the_master_gate_ships_disabled`,
    // `the_shipped_default_is_still_eloquent`, `the_scope_list_names_only_the_
    // wired_workflow` — and they are the guard against a rollout widening by
    // accident.
    //
    // These four are Replit SECRETS, not `.env` keys, so they arrive as real
    // process environment variables and phpdotenv's immutable repository will not
    // overwrite them. The moment the cascade was enabled in the deployed runtime
    // (census source, all four workflows), 19 of those tests began failing — on a
    // branch whose source diff could not have caused it. The application was
    // correct; the SUITE was reading the deployment's configuration.
    //
    // NOTE THE TWO SHAPES BELOW, because using one shape for all four is wrong:
    //
    //   Blanked to '' — the two booleans. `(bool) env(KEY, false)` on an empty
    //   string is false, which IS the shipped default, so blanking is exact.
    //
    //   Pinned to the literal default — the two non-booleans, for the same reason
    //   `DB_CONNECTION` above is pinned to 'sqlite' rather than blanked. `env()`
    //   returns its default ONLY when the key is absent; a key present-but-empty
    //   returns ''. So blanking these would NOT restore the default, it would
    //   invent a third state: `geography_source` would become '' and
    //   AppServiceProvider's binding — which deliberately refuses to fall back —
    //   would throw `Unknown criteria_location_dna.geography_source ''` on every
    //   test that resolves the repository, and `geography_cascade_workflows` would
    //   become [] rather than the shipped ['hire_buyer', 'create_buyer'].
    //
    // Unsetting instead of pinning was rejected for the reason given throughout
    // this file: an unset variable is repopulated from `.env`.
    //
    // THIS DOES NOT DISABLE THE CASCADE FOR TESTS THAT WANT IT. Opt-in happens
    // through `config()->set('criteria_location_dna.…')` after the application has
    // booted, which overrides the resolved config directly and never consults the
    // environment. Every test that exercises the census source, the enabled gate,
    // the workflow scope or the search flag already opts in that way and is
    // unaffected by these values.
    //
    // Production is untouched: this file is the PHPUnit bootstrap and is loaded by
    // nothing else. `config/criteria_location_dna.php` keeps its own defaults, and
    // the deployed runtime keeps reading the Replit Secrets.
    'CRITERIA_LDNA_CASCADE_ENABLED'   => '',
    'CRITERIA_LDNA_SEARCH_ENABLED'    => '',
    'CRITERIA_LDNA_GEOGRAPHY_SOURCE'  => 'eloquent',
    'CRITERIA_LDNA_CASCADE_WORKFLOWS' => 'hire_buyer,create_buyer',
];

foreach ($byoBlankedCredentials as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name]    = $value;
    $_SERVER[$name] = $value;
}

unset($byoBlankedCredentials, $name, $value);

require __DIR__ . '/../vendor/autoload.php';
