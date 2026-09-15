<?php

namespace Tests\Feature\Safeguards;

use App\Support\Safeguards\ProductionDatabaseGuard;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The identity rules behind every manual-script, QA-command and fixture-seeder refusal.
 *
 * Most of this is pure: ProductionDatabaseGuard::assess() takes arrays and returns a verdict, so
 * each rule is exercised directly, with no container state to leak and no connection to open.
 * The one place that is not good enough is DATABASE_URL. Its effect comes from how
 * config/database.php is WRITTEN (the `default` ternary and the `url` on every connection), so a
 * hand-built config array would be testing this file's idea of that config rather than the config.
 * That case evaluates the real file in a child process with a controlled environment.
 *
 * Like TestDatabaseIdentityTest, it uses neither DatabaseTransactions nor RefreshDatabase and issues
 * no query.
 */
class ProductionDatabaseGuardTest extends TestCase
{
    private const PRODUCTION_URL = 'postgresql://qa:secret@helium:5432/heliumdb?sslmode=disable';

    // ── permits a known test / local database ───────────────────────────────

    /** @test */
    public function the_real_test_database_of_this_process_is_permitted(): void
    {
        $assessment = ProductionDatabaseGuard::assessApplication($this->app);

        $this->assertSame([], $assessment->signals(), 'The PHPUnit SQLite :memory: database was treated as production.');
        $this->assertFalse($assessment->isProduction());
    }

    /** @test */
    public function the_guard_resolves_the_default_connection_exactly_as_the_phpunit_guard_does(): void
    {
        // One parser, two policies. If these ever disagree, one of the two resolutions has drifted.
        $theirs = static::resolvedConnection();
        $ours = ProductionDatabaseGuard::assessApplication($this->app)->defaultConnection();

        $this->assertNotNull($ours);
        $this->assertSame($theirs['name'], $ours['name']);
        $this->assertSame($theirs['driver'], $ours['driver']);
        $this->assertSame($theirs['database'], $ours['database']);
        $this->assertSame($theirs['host'] === null ? [] : [$theirs['host']], $ours['hosts']);
    }

    /** @test */
    public function a_local_sqlite_file_is_permitted(): void
    {
        $this->assertPermitted($this->assessLocal(['default' => 'sqlite', 'connections' => [
            'sqlite' => ['driver' => 'sqlite', 'url' => null, 'database' => '/tmp/qa.sqlite'],
        ]]));
    }

    /** @test */
    public function a_local_postgresql_is_permitted(): void
    {
        $this->assertPermitted($this->assessLocal(['default' => 'pgsql', 'connections' => [
            'pgsql' => ['driver' => 'pgsql', 'url' => null, 'host' => '127.0.0.1', 'database' => 'byo_local'],
        ]]));
    }

    /** @test */
    public function the_ci_incremental_migration_environment_is_permitted(): void
    {
        // .github/workflows/incremental-migration-tests.yml: APP_ENV=testing, pgsql on 127.0.0.1/byo_test.
        $this->assertPermitted(ProductionDatabaseGuard::assess(['testing'], [
            'default' => 'pgsql',
            'connections' => [
                'pgsql' => ['driver' => 'pgsql', 'url' => '', 'host' => '127.0.0.1', 'database' => 'byo_test'],
                'mysql' => ['driver' => 'mysql', 'url' => '', 'host' => '127.0.0.1', 'database' => 'byo_test'],
            ],
        ], ['APP_ENV' => ['testing'], 'DATABASE_URL' => [], 'PGHOST' => []]));
    }

    /** @test */
    public function a_non_default_mysql_connection_naming_the_production_host_does_not_refuse(): void
    {
        // .replit injects DB_HOST=helium, so the unused `mysql` connection names it everywhere,
        // this test process included. A mysql client cannot open a session on PostgreSQL.
        $this->assertPermitted($this->assessLocal(['default' => 'sqlite', 'connections' => [
            'sqlite' => ['driver' => 'sqlite', 'url' => null, 'database' => ':memory:'],
            'mysql' => ['driver' => 'mysql', 'url' => null, 'host' => 'helium', 'database' => 'forge'],
        ]]));
    }

    // ── rejects production ───────────────────────────────────────────────────

    /** @test */
    public function the_production_identity_is_refused_on_every_signal_at_once(): void
    {
        $assessment = ProductionDatabaseGuard::assess(['production'], [
            'default' => 'pgsql',
            'connections' => ['pgsql' => ['driver' => 'pgsql', 'url' => self::PRODUCTION_URL]],
        ], [
            'APP_ENV' => ['production'],
            'DATABASE_URL' => [self::PRODUCTION_URL],
            'PGHOST' => ['helium'],
            'PGDATABASE' => ['heliumdb'],
        ]);

        $this->assertTrue($assessment->isProduction());

        $joined = implode(' | ', $assessment->signals());
        foreach ([
            "application environment resolves to 'production'",
            "APP_ENV is 'production'",
            "DATABASE_URL points at host 'helium'",
            "DATABASE_URL points at database 'heliumdb'",
            "connection 'pgsql' (default) resolves to host 'helium'",
            "connection 'pgsql' (default) resolves to database 'heliumdb'",
            "PGHOST is 'helium'",
            "PGDATABASE is 'heliumdb'",
        ] as $expected) {
            $this->assertStringContainsString($expected, $joined);
        }

        $this->assertSame("connection 'pgsql': driver=pgsql host=helium database=heliumdb", $assessment->resolvedTarget());
        $this->assertStringNotContainsString('secret', $joined . $assessment->resolvedTarget(), 'A credential reached a message.');
    }

    /**
     * @test
     * @dataProvider singleProductionSignals
     */
    public function each_production_signal_is_sufficient_on_its_own(array $appEnvironments, array $database, array $environment, string $expected): void
    {
        $assessment = ProductionDatabaseGuard::assess($appEnvironments, $database, $environment);

        $this->assertTrue($assessment->isProduction(), "Not refused: {$expected}");
        $this->assertStringContainsString($expected, implode(' | ', $assessment->signals()));
    }

    public function singleProductionSignals(): array
    {
        $local = ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'url' => null, 'database' => ':memory:']]];

        return [
            'APP_ENV=production, local database' => [['production'], $local, [], "resolves to 'production'"],
            'APP_ENV in the raw environment only' => [['local'], $local, ['APP_ENV' => ['production']], "APP_ENV is 'production'"],
            'REPLIT_DEPLOYMENT' => [['local'], $local, ['REPLIT_DEPLOYMENT' => ['1']], 'REPLIT_DEPLOYMENT is set'],
            'host only' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => 'helium', 'database' => 'other']]], [], "resolves to host 'helium'"],
            'host with a port and a subdomain' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => 'helium.internal:5432', 'database' => 'other']]], [], "resolves to host 'helium.internal'"],
            'host in a libpq host list' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => '127.0.0.1,helium', 'database' => 'other']]], [], "resolves to host 'helium'"],
            'read replica host' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'read' => ['host' => ['helium']], 'database' => 'other']]], [], "resolves to host 'helium'"],
            'database only' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'heliumdb']]], [], "resolves to database 'heliumdb'"],
            'a renamed copy of the database' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'heliumdb_qa']]], [], "resolves to database 'heliumdb_qa'"],
            'blank host falls back to libpq PGHOST' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => '', 'database' => 'other']]], ['PGHOST' => ['helium']], '(via the libpq environment fallback)'],
            'blank database falls back to libpq PGDATABASE' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => '']]], ['PGDATABASE' => ['heliumdb']], "resolves to database 'heliumdb' (via the libpq environment fallback)"],
            'libpq service file cannot be verified' => [['local'], ['default' => 'pgsql', 'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => null, 'database' => 'other']]], ['PGSERVICE' => ['prod']], 'PGSERVICE is set'],
            'a non-default pgsql connection' => [['local'], ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'], 'pgsql' => ['driver' => 'pgsql', 'host' => 'helium', 'database' => 'x']]], [], "connection 'pgsql' resolves to host 'helium'"],
            'undefined default connection' => [['local'], ['default' => 'missing', 'connections' => []], [], "'missing' is not defined"],
            'unparseable DATABASE_URL' => [['local'], $local, ['DATABASE_URL' => ['not a url']], 'cannot be parsed'],
        ];
    }

    // ── rejects production when resolution comes through DATABASE_URL ──────

    /** @test */
    public function a_url_on_the_connection_named_sqlite_is_resolved_not_trusted(): void
    {
        // The trap from TestDatabaseIdentityTest: `url` overrides the connection's own driver and
        // database, so a connection NAMED sqlite with DB_DATABASE=:memory: is pgsql/heliumdb.
        $assessment = ProductionDatabaseGuard::assess(['local'], [
            'default' => 'sqlite',
            'connections' => ['sqlite' => ['driver' => 'sqlite', 'url' => self::PRODUCTION_URL, 'database' => ':memory:']],
        ], []);

        $this->assertTrue($assessment->isProduction());
        $this->assertSame('pgsql', $assessment->defaultConnection()['driver']);
        $this->assertStringContainsString("connection 'sqlite' (default) resolves to database 'heliumdb'", implode(' | ', $assessment->signals()));
    }

    /** @test */
    public function the_real_database_config_is_refused_when_only_database_url_points_at_production(): void
    {
        // Every other database variable says "local SQLite". Only DATABASE_URL disagrees, and the
        // real config/database.php is what decides what that means.
        $result = $this->assessRealConfigInChildProcess([
            'APP_ENV' => 'local',
            'DATABASE_URL' => self::PRODUCTION_URL,
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_HOST' => '127.0.0.1',
        ]);

        $this->assertTrue($result['production'], 'config/database.php resolved DATABASE_URL to production and the guard did not notice.');
        $this->assertSame('pgsql', $result['default']['name'], 'config/database.php no longer selects pgsql when DATABASE_URL is set.');
        $this->assertSame(['helium'], $result['default']['hosts']);
        $this->assertSame('heliumdb', $result['default']['database']);

        $joined = implode(' | ', $result['signals']);
        $this->assertStringContainsString("DATABASE_URL points at host 'helium'", $joined);
        $this->assertStringContainsString("connection 'sqlite' resolves to host 'helium'", $joined);
    }

    /** @test */
    public function the_real_database_config_is_permitted_for_a_fully_local_environment(): void
    {
        // The control for the test above: same child, same config, nothing pointing at production.
        $result = $this->assessRealConfigInChildProcess([
            'APP_ENV' => 'local',
            'DATABASE_URL' => '',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_HOST' => '127.0.0.1',
        ]);

        $this->assertSame([], $result['signals']);
        $this->assertFalse($result['production']);
    }

    // ── the explicit override ────────────────────────────────────────────────

    /** @test */
    public function the_override_is_honoured_only_when_supplied_and_permitted(): void
    {
        $production = ProductionDatabaseGuard::assess(['production'], ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]], []);
        $local = $this->assessLocal(['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]]);

        $this->assertSame(ProductionDatabaseGuard::OUTCOME_REFUSED, ProductionDatabaseGuard::decide($production, false, false));
        $this->assertSame(ProductionDatabaseGuard::OUTCOME_REFUSED, ProductionDatabaseGuard::decide($production, false, true), 'Permitting the override must not apply it.');
        $this->assertSame(ProductionDatabaseGuard::OUTCOME_REFUSED, ProductionDatabaseGuard::decide($production, true, false), 'An entry point that does not accept the override honoured it.');
        $this->assertSame(ProductionDatabaseGuard::OUTCOME_OVERRIDDEN, ProductionDatabaseGuard::decide($production, true, true));

        // A safe target is simply permitted. The override is not required, and supplying it changes nothing.
        $this->assertSame(ProductionDatabaseGuard::OUTCOME_PERMITTED, ProductionDatabaseGuard::decide($local, false, false));
        $this->assertSame(ProductionDatabaseGuard::OUTCOME_PERMITTED, ProductionDatabaseGuard::decide($local, true, true));
    }

    /** @test */
    public function only_the_exact_override_token_counts(): void
    {
        $this->assertTrue(ProductionDatabaseGuard::overrideSuppliedIn(['script.php', '--i-know-this-is-production']));
        $this->assertTrue(ProductionDatabaseGuard::overrideSuppliedIn(['script.php', 'arg', '--i-know-this-is-production', 'x']));

        foreach ([
            'nothing' => ['script.php'],
            'force' => ['script.php', '--force'],
            'yes' => ['script.php', '-y'],
            'with a value' => ['script.php', '--i-know-this-is-production=1'],
            'case-folded' => ['script.php', '--I-KNOW-THIS-IS-PRODUCTION'],
            'abbreviated' => ['script.php', '--i-know'],
            'single dash' => ['script.php', '-i-know-this-is-production'],
            'underscores' => ['script.php', '--i_know_this_is_production'],
            'as argv[0]' => ['--i-know-this-is-production'],
        ] as $label => $argv) {
            $this->assertFalse(ProductionDatabaseGuard::overrideSuppliedIn($argv), "Override accepted from: {$label}");
        }
    }

    /** @test */
    public function the_override_cannot_come_from_the_environment(): void
    {
        $original = [getenv('I_KNOW_THIS_IS_PRODUCTION'), $_SERVER['I_KNOW_THIS_IS_PRODUCTION'] ?? null];

        try {
            putenv('I_KNOW_THIS_IS_PRODUCTION=1');
            $_SERVER['I_KNOW_THIS_IS_PRODUCTION'] = '1';

            $this->assertFalse(ProductionDatabaseGuard::overrideSuppliedIn(['script.php']));
            $this->assertArrayNotHasKey('I_KNOW_THIS_IS_PRODUCTION', ProductionDatabaseGuard::captureEnvironment());
        } finally {
            $original[0] === false ? putenv('I_KNOW_THIS_IS_PRODUCTION') : putenv('I_KNOW_THIS_IS_PRODUCTION=' . $original[0]);
            if ($original[1] === null) {
                unset($_SERVER['I_KNOW_THIS_IS_PRODUCTION']);
            } else {
                $_SERVER['I_KNOW_THIS_IS_PRODUCTION'] = $original[1];
            }
        }
    }

    /** @test */
    public function the_refusal_message_names_the_target_and_whether_an_override_exists(): void
    {
        $assessment = ProductionDatabaseGuard::assess(['production'], [
            'default' => 'pgsql',
            'connections' => ['pgsql' => ['driver' => 'pgsql', 'host' => 'helium', 'database' => 'heliumdb', 'password' => 'secret']],
        ], []);

        $closed = $assessment->refusalMessage('scripts/x.php', false, 'php scripts/x.php');
        $this->assertStringContainsString('PRODUCTION DATABASE DETECTED', $closed);
        $this->assertStringContainsString('REFUSED TO RUN: scripts/x.php', $closed);
        $this->assertStringContainsString('host=helium database=heliumdb', $closed);
        $this->assertStringContainsString('does not accept a production override', $closed);
        $this->assertStringNotContainsString('--i-know-this-is-production', $closed);
        $this->assertStringNotContainsString('secret', $closed);

        $open = $assessment->refusalMessage('scripts/x.php', true, 'php scripts/x.php');
        $this->assertStringContainsString('Re-run it with --i-know-this-is-production', $open);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function assessLocal(array $database): \App\Support\Safeguards\ProductionDatabaseAssessment
    {
        return ProductionDatabaseGuard::assess(['local'], $database, ['APP_ENV' => ['local']]);
    }

    private function assertPermitted(\App\Support\Safeguards\ProductionDatabaseAssessment $assessment): void
    {
        $this->assertSame([], $assessment->signals());
        $this->assertFalse($assessment->isProduction());
    }

    /**
     * Evaluate the real config/database.php in a child PHP process whose environment is exactly
     * what the caller says for every variable the guard or that file reads. Nothing is inherited
     * by accident. No framework boots and no connection is made: the child requires the config
     * file and hands it to the pure assessment.
     */
    private function assessRealConfigInChildProcess(array $environment): array
    {
        $environment += [
            'REPLIT_DEPLOYMENT' => '',
            'PGHOST' => '', 'PGHOSTADDR' => '', 'PGPORT' => '', 'PGDATABASE' => '', 'PGUSER' => '', 'PGPASSWORD' => '', 'PGSERVICE' => '',
            'SPATIAL_DATABASE_URL' => '', 'SPATIAL_PGHOST' => '', 'SPATIAL_PGDATABASE' => '',
        ];

        $script = <<<'CHILD'
            $base = $argv[1];
            require $base . '/vendor/autoload.php';
            $app = new \Illuminate\Foundation\Application($base);
            $database = require $base . '/config/database.php';
            $assessment = \App\Support\Safeguards\ProductionDatabaseGuard::assess(
                [(string) \Illuminate\Support\Env::get('APP_ENV', '')],
                $database,
                \App\Support\Safeguards\ProductionDatabaseGuard::captureEnvironment()
            );
            echo json_encode([
                'production' => $assessment->isProduction(),
                'signals' => $assessment->signals(),
                'default' => $assessment->defaultConnection(),
            ]);
            CHILD;

        $process = new Process(
            [(new PhpExecutableFinder())->find() ?: 'php', '-r', $script, base_path()],
            base_path(),
            $environment,
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'The config probe failed: ' . $process->getErrorOutput());

        $decoded = json_decode($process->getOutput(), true);
        $this->assertIsArray($decoded, 'The config probe returned no usable output: ' . $process->getOutput());

        return $decoded;
    }
}
