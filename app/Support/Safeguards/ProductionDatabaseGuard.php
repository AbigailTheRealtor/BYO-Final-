<?php

namespace App\Support\Safeguards;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConfigurationUrlParser;
use Throwable;

/**
 * Decides whether a manually invoked developer / QA entry point is pointed at production.
 *
 * WHY THIS EXISTS
 * ---------------
 * PHPUnit cannot reach the shared database: tests/bootstrap.php neutralises every environment
 * path before Laravel loads, and tests/TestCase.php checks the RESOLVED connection. A standalone
 * script, a debug Artisan command or a fixture seeder has neither. It inherits whatever the shell
 * carries, and on this host the shell carries production: `.replit` injects `DB_HOST=helium` /
 * `DB_DATABASE=heliumdb`, the platform injects `DATABASE_URL` and `PGHOST`/`PGDATABASE`, and the
 * workspace `.env` says `APP_ENV=production`. So "run the QA script" meant "run it on production"
 * unless somebody remembered otherwise.
 *
 * WHAT IT CHECKS, AND WHY MORE THAN ONE THING
 * -------------------------------------------
 * `config/database.php` can reach the same server through several doors, and a guard that watches
 * one of them is the guard tests/TestCase.php had to replace:
 *
 *   • `DATABASE_URL` makes `pgsql` the default, and a non-empty `url` overrides the driver, host and
 *     database of every connection that carries it, including the one NAMED `sqlite`.
 *   • `pgsql` reads `PGHOST`/`PGDATABASE` before `DB_HOST`/`DB_DATABASE`.
 *   • A pgsql connection with no host is not forced empty. Laravel leaves it out of the DSN and
 *     libpq fills it in from `PGHOST`/`PGHOSTADDR`/`PGSERVICE`.
 *   • `APP_ENV` is a production claim of its own, and `config/app.php` defaults it to production.
 *
 * So the assessment resolves each connection through the same `ConfigurationUrlParser` that
 * `ConnectionFactory` uses (the parser tests/TestCase.php::resolvedConnection() also uses, so there
 * is no second URL implementation to drift). It applies libpq's fallback and also reads the raw
 * process environment, independently of config. **Any one signal counts as production.** A target
 * that cannot be resolved counts as production too, because "could not tell" is not safe.
 *
 * It constructs no connection, opens no PDO handle and issues no query. It is safe to call before
 * anyone knows whether the target is safe, which is the only time the check is useful.
 *
 * POLICY IS NOT ISOLATION
 * -----------------------
 * This is a deny policy for production identity. It is deliberately not the PHPUnit allow policy
 * ("SQLite :memory: or nothing"): a developer may legitimately point a QA script at a local
 * PostgreSQL. The two share the parser, not the rule.
 *
 * @see \App\Support\Safeguards\ManualScriptBootstrap   standalone scripts/*.php
 * @see \App\Console\Concerns\RefusesProductionDatabase Artisan QA/debug commands
 * @see tests/Feature/Safeguards/ProductionDatabaseGuardTest.php
 */
final class ProductionDatabaseGuard
{
    /**
     * The explicit override, as an Artisan option name. Deliberately long and unambiguous, so it
     * cannot be typed by accident or read as a generic `--force`, and it shows up in shell history.
     * There is no environment-variable equivalent.
     */
    public const OVERRIDE_OPTION = 'i-know-this-is-production';

    public const OVERRIDE_FLAG = '--' . self::OVERRIDE_OPTION;

    /** Exit status for a refused run. Distinct from 1 (generic failure) and 255 (PHP fatal). */
    public const EXIT_REFUSED = 3;

    /** The production application database. Matched as a substring: `heliumdb_copy` is not safer. */
    public const PRODUCTION_DATABASE_MARKERS = ['heliumdb'];

    /** The production database host. `helium` itself, or any name under it (`helium.internal`). */
    public const PRODUCTION_HOSTS = ['helium'];

    public const PRODUCTION_APP_ENVS = ['production'];

    public const OUTCOME_PERMITTED = 'permitted';
    public const OUTCOME_REFUSED = 'refused';
    public const OUTCOME_OVERRIDDEN = 'overridden';

    /**
     * Environment keys read straight from the process, independently of config. `DB_HOST` and
     * `DB_DATABASE` are deliberately absent: they only matter through a connection that reads them,
     * and that connection's resolved config is assessed already. Checking them raw would refuse a
     * developer who pointed `DATABASE_URL` elsewhere while `.replit` still injects `DB_HOST=helium`.
     */
    private const RAW_ENVIRONMENT_KEYS = [
        'APP_ENV', 'REPLIT_DEPLOYMENT', 'DATABASE_URL', 'PGHOST', 'PGHOSTADDR', 'PGDATABASE', 'PGSERVICE',
    ];

    /**
     * Assess a running application: its detected environment, its loaded config and the process
     * environment. Requires configuration to be loaded, and nothing after that.
     */
    public static function assessApplication(?Container $app = null): ProductionDatabaseAssessment
    {
        $app = $app ?? \Illuminate\Container\Container::getInstance();
        $config = $app->make('config');

        $appEnvironments = [(string) $config->get('app.env', '')];

        // The application's detected environment can differ from config('app.env') (a test sets
        // $app['env'], `--env=` on the command line). Either one saying production is enough.
        if ($app->bound('env')) {
            $appEnvironments[] = (string) $app['env'];
        }

        return self::assess(
            $appEnvironments,
            (array) $config->get('database', []),
            self::captureEnvironment(),
        );
    }

    /**
     * The pure assessment. No container and no I/O, so every rule can be tested with plain arrays.
     *
     * @param  list<string>                 $appEnvironments  resolved application environment name(s)
     * @param  array<string, mixed>          $database         the `database` config array
     * @param  array<string, list<string>>   $environment      raw process values per key, every surface
     */
    public static function assess(array $appEnvironments, array $database, array $environment): ProductionDatabaseAssessment
    {
        $signals = [];

        foreach (array_unique(array_map([self::class, 'normalise'], $appEnvironments)) as $appEnv) {
            if (in_array($appEnv, self::PRODUCTION_APP_ENVS, true)) {
                $signals[] = "the application environment resolves to '{$appEnv}'";
            }
        }

        foreach (self::values($environment, 'APP_ENV') as $value) {
            if (in_array(self::normalise($value), self::PRODUCTION_APP_ENVS, true)) {
                $signals[] = "APP_ENV is '{$value}' in the process environment";
            }
        }

        foreach (self::values($environment, 'REPLIT_DEPLOYMENT') as $value) {
            if (! in_array(self::normalise($value), ['0', 'false', 'no', 'off'], true)) {
                $signals[] = "REPLIT_DEPLOYMENT is set ('{$value}'): this process is a Replit deployment";
            }
        }

        foreach (self::values($environment, 'DATABASE_URL') as $url) {
            $signals = array_merge($signals, self::databaseUrlSignals($url));
        }

        foreach (['PGHOST', 'PGHOSTADDR'] as $key) {
            foreach (self::values($environment, $key) as $value) {
                if (self::hostListIsProduction($value)) {
                    $signals[] = "{$key} is '{$value}' in the process environment; libpq uses it for any pgsql connection without a host";
                }
            }
        }

        foreach (self::values($environment, 'PGDATABASE') as $value) {
            if (self::databaseIsProduction($value)) {
                $signals[] = "PGDATABASE is '{$value}' in the process environment; libpq uses it for any pgsql connection without a database";
            }
        }

        $defaultName = (string) ($database['default'] ?? '');
        $connections = (array) ($database['connections'] ?? []);
        $default = null;

        if ($defaultName === '' || ! is_array($connections[$defaultName] ?? null)) {
            $signals[] = "the default connection '{$defaultName}' is not defined, so its target cannot be verified";
        }

        foreach ($connections as $name => $config) {
            $isDefault = (string) $name === $defaultName;
            $label = $isDefault ? "connection '{$name}' (default)" : "connection '{$name}'";

            try {
                $identity = self::resolveConnection((array) $config, $environment);
            } catch (Throwable $e) {
                // A URL the factory cannot parse is a URL nobody has verified.
                $signals[] = "{$label} has a database URL that cannot be parsed, so its target cannot be verified";
                continue;
            }

            if ($isDefault) {
                $default = ['name' => (string) $name] + $identity;
            }

            // heliumdb is PostgreSQL. A mysql or sqlsrv client cannot open a session on it, so a
            // NON-default connection only matters once it resolves to pgsql (which `DATABASE_URL`
            // does to every connection that carries it). The default connection is assessed
            // whatever its driver, because it is the one a script uses without asking.
            if (! $isDefault && $identity['driver'] !== 'pgsql') {
                continue;
            }

            foreach ($identity['hosts'] as $host) {
                if (self::hostIsProduction($host)) {
                    $signals[] = "{$label} resolves to host '{$host}'" . ($identity['host_from_libpq'] ? ' (via the libpq environment fallback)' : '');
                }
            }

            if ($identity['database'] !== null && self::databaseIsProduction($identity['database'])) {
                $signals[] = "{$label} resolves to database '{$identity['database']}'" . ($identity['database_from_libpq'] ? ' (via the libpq environment fallback)' : '');
            }

            if ($identity['unverifiable'] !== null) {
                $signals[] = "{$label} {$identity['unverifiable']}";
            }
        }

        return new ProductionDatabaseAssessment(array_values(array_unique($signals)), $default);
    }

    /**
     * What happens next, given an assessment and whether the override was deliberately supplied.
     * The override changes nothing unless the entry point has chosen to accept it.
     */
    public static function decide(ProductionDatabaseAssessment $assessment, bool $overrideSupplied, bool $overridePermitted): string
    {
        if (! $assessment->isProduction()) {
            return self::OUTCOME_PERMITTED;
        }

        return $overrideSupplied && $overridePermitted ? self::OUTCOME_OVERRIDDEN : self::OUTCOME_REFUSED;
    }

    /**
     * Whether a standalone script's argv carries the override. Exact token only: no `=value`, no
     * case folding, no prefix match, no `--force`, and never the environment. argv[0] is the script.
     *
     * @param  list<string>  $argv
     */
    public static function overrideSuppliedIn(array $argv): bool
    {
        return in_array(self::OVERRIDE_FLAG, array_slice($argv, 1), true);
    }

    /**
     * Every non-blank value of the keys this guard reads, from all three surfaces Laravel's Env
     * consults. Surfaces can disagree; every value is assessed, so the strictest one wins.
     *
     * @return array<string, list<string>>
     */
    public static function captureEnvironment(): array
    {
        $captured = [];

        foreach (self::RAW_ENVIRONMENT_KEYS as $key) {
            $values = [(string) getenv($key), (string) ($_ENV[$key] ?? ''), (string) ($_SERVER[$key] ?? '')];
            $captured[$key] = array_values(array_unique(array_filter($values, static fn (string $v): bool => trim($v) !== '')));
        }

        return $captured;
    }

    /**
     * Resolve one connection the way ConnectionFactory, then libpq, would.
     *
     * @return array{driver: ?string, hosts: list<string>, database: ?string, host_from_libpq: bool, database_from_libpq: bool, unverifiable: ?string}
     */
    private static function resolveConnection(array $config, array $environment): array
    {
        $parsed = (new ConfigurationUrlParser())->parseConfiguration($config);
        $driver = isset($parsed['driver']) ? (string) $parsed['driver'] : null;

        $hosts = [];
        foreach ([$parsed['host'] ?? null, $parsed['read']['host'] ?? null, $parsed['write']['host'] ?? null] as $candidate) {
            foreach ((array) $candidate as $host) {
                $hosts = array_merge($hosts, self::splitHosts((string) $host));
            }
        }

        $database = trim((string) ($parsed['database'] ?? ''));
        $hostFromLibpq = false;
        $databaseFromLibpq = false;
        $unverifiable = null;

        if ($driver === 'pgsql') {
            // PostgresConnector: `isset($host) ? "host={$host};" : ''`. A blank host is not
            // "no host". It is libpq's host, taken from the environment.
            if ($hosts === []) {
                foreach (['PGHOSTADDR', 'PGHOST'] as $key) {
                    foreach (self::values($environment, $key) as $value) {
                        $hosts = array_merge($hosts, self::splitHosts($value));
                        $hostFromLibpq = true;
                    }
                }

                if ($hosts === [] && self::values($environment, 'PGSERVICE') !== []) {
                    $unverifiable = 'has no host and PGSERVICE is set, so libpq will take the target from a service file this guard cannot read';
                }
            }

            if ($database === '') {
                $fallback = self::values($environment, 'PGDATABASE');
                $database = $fallback[0] ?? '';
                $databaseFromLibpq = $fallback !== [];
            }
        }

        return [
            'driver' => $driver,
            'hosts' => array_values(array_unique($hosts)),
            'database' => $database === '' ? null : $database,
            'host_from_libpq' => $hostFromLibpq,
            'database_from_libpq' => $databaseFromLibpq,
            'unverifiable' => $unverifiable,
        ];
    }

    /** @return list<string> */
    private static function databaseUrlSignals(string $url): array
    {
        try {
            $parsed = (new ConfigurationUrlParser())->parseConfiguration(['url' => $url]);
        } catch (Throwable $e) {
            $parsed = [];
        }

        // parse_url() accepts almost any string, so "did not throw" is not "understood". A value
        // with no scheme names no driver, and nobody can say what it points at.
        if (empty($parsed['driver'])) {
            return ['DATABASE_URL is set but cannot be parsed as a database URL, so its target cannot be verified'];
        }

        $signals = [];

        foreach (self::splitHosts((string) ($parsed['host'] ?? '')) as $host) {
            if (self::hostIsProduction($host)) {
                $signals[] = "DATABASE_URL points at host '{$host}'";
            }
        }

        $database = (string) ($parsed['database'] ?? '');
        if (self::databaseIsProduction($database)) {
            $signals[] = "DATABASE_URL points at database '{$database}'";
        }

        return $signals;
    }

    private static function hostListIsProduction(string $hosts): bool
    {
        foreach (self::splitHosts($hosts) as $host) {
            if (self::hostIsProduction($host)) {
                return true;
            }
        }

        return false;
    }

    private static function hostIsProduction(string $host): bool
    {
        $host = trim(self::normalise($host), '[]');

        foreach (self::PRODUCTION_HOSTS as $production) {
            if ($host === $production || str_starts_with($host, $production . '.')) {
                return true;
            }
        }

        return false;
    }

    private static function databaseIsProduction(string $database): bool
    {
        foreach (self::PRODUCTION_DATABASE_MARKERS as $marker) {
            if (str_contains(self::normalise($database), $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * libpq accepts a comma-separated host list, and a host may carry a `:port`.
     *
     * @return list<string>
     */
    private static function splitHosts(string $hosts): array
    {
        $split = [];

        foreach (explode(',', $hosts) as $host) {
            $host = trim($host);
            if ($host === '') {
                continue;
            }
            // Strip a trailing :port, but not from a bracketed IPv6 literal's own colons.
            $split[] = preg_replace('/^([^\[\]:]+):\d+$/', '$1', $host);
        }

        return $split;
    }

    /** @return list<string> */
    private static function values(array $environment, string $key): array
    {
        return array_values(array_filter(
            array_map('strval', (array) ($environment[$key] ?? [])),
            static fn (string $v): bool => trim($v) !== '',
        ));
    }

    private static function normalise(string $value): string
    {
        return strtolower(trim($value));
    }
}
