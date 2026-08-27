<?php

namespace Tests\Feature\Safeguards;

use Database\Seeders\SpatialFirstSliceCategorySeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The test suite must never be able to reach a real PostGIS cluster.
 *
 * Sibling of TestDatabaseIdentityTest, which pins the APPLICATION connection to
 * SQLite :memory:. That guard deliberately covers only `DATABASE_URL` / `DB_*`,
 * because `pgsql_spatial` is a separate connection reading its own SPATIAL_*
 * variables — so nothing covered this one, and an ambient `SPATIAL_DATABASE_URL`
 * was inherited straight into the test process.
 *
 * The consequence was concrete, not theoretical. `SpatialFirstSliceCategorySeeder`
 * fails closed only while the connection is inert: its guard throws when both
 * `url` and `host` are empty. With a live DSN inherited the guard passed, and
 * SpatialFirstSliceSeederIsolationTest — the test whose whole purpose is to prove
 * the seeder cannot write off-cluster — upserted `place_categories` into a real
 * database instead.
 *
 * Like TestDatabaseIdentityTest, this inspects the RESOLVED configuration rather
 * than the values `tests/bootstrap.php` writes: a guard that reads back what it
 * just wrote proves nothing.
 */
class SpatialDatabaseIdentityTest extends TestCase
{
    /** Every key the pgsql_spatial connection reads. */
    private const SPATIAL_KEYS = [
        'SPATIAL_DATABASE_URL',
        'SPATIAL_PGHOST',
        'SPATIAL_PGPORT',
        'SPATIAL_PGDATABASE',
        'SPATIAL_PGUSER',
        'SPATIAL_PGPASSWORD',
        'SPATIAL_PGSSLMODE',
    ];

    /** libpq client variables that can supply DSN defaults behind Laravel's back. */
    private const LIBPQ_REDIRECT_KEYS = [
        'PGHOST',
        'PGHOSTADDR',
        'PGPORT',
        'PGDATABASE',
        'PGUSER',
        'PGSERVICE',
    ];

    /** @test */
    public function the_resolved_spatial_connection_is_inert(): void
    {
        $c = config('database.connections.pgsql_spatial');

        $this->assertNotNull($c, 'pgsql_spatial must still be defined — this guard makes it inert, not absent.');

        foreach (['url', 'host', 'database', 'username'] as $key) {
            $this->assertEmpty(
                $c[$key] ?? null,
                "pgsql_spatial.{$key} resolved to a value; the suite could reach a real spatial database."
            );
        }
    }

    /** @test */
    public function no_spatial_env_var_survives_into_the_test_process(): void
    {
        foreach (self::SPATIAL_KEYS as $key) {
            $this->assertSame(
                '',
                (string) getenv($key),
                "{$key} was inherited into the test process; tests/bootstrap.php must blank it."
            );
        }
    }

    /**
     * @test
     *
     * Blanking SPATIAL_* alone is not sufficient. PostgresConnector::getDsn() does
     *
     *     $host = isset($host) ? "host={$host};" : '';
     *
     * so a null host is omitted from the DSN entirely and libpq fills in its own
     * defaults from PGHOST / PGDATABASE / PGUSER. This host injects
     * PGHOST=helium and PGDATABASE=heliumdb, so an unguarded spatial connect would
     * land on the shared APPLICATION database.
     */
    public function no_libpq_client_variable_can_supply_a_default_host(): void
    {
        foreach (self::LIBPQ_REDIRECT_KEYS as $key) {
            $this->assertSame(
                '',
                (string) getenv($key),
                "{$key} survived into the test process and could redirect a PostgreSQL connection."
            );
        }
    }

    /**
     * @test
     *
     * The behavioural consequence, stated as behaviour: with the connection inert
     * the seeder's own guard refuses. This is the property
     * SpatialFirstSliceSeederIsolationTest depends on.
     */
    public function the_spatial_seeder_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SpatialFirstSliceCategorySeeder())->run();
    }

    /** @test */
    public function no_spatial_connection_has_been_opened(): void
    {
        $opened = array_keys(DB::getConnections());

        $this->assertNotContains(
            'pgsql_spatial',
            $opened,
            'A PDO handle was opened against pgsql_spatial during the test run.'
        );
    }

    /**
     * @test
     *
     * The load-bearing one. Everything above observes the CURRENT process, which
     * inherits whatever this machine happens to export. This spawns a child with a
     * deliberately hostile SPATIAL_DATABASE_URL and PGHOST set, boots the framework
     * through tests/bootstrap.php exactly as PHPUnit does, and asserts the hostile
     * values cannot reach the resolved configuration.
     *
     * That is what makes this a regression test rather than a restatement: it fails
     * if the neutralisation is removed, regardless of how the host is configured.
     */
    public function a_hostile_ambient_spatial_dsn_cannot_reach_the_resolved_config(): void
    {
        $root = base_path();

        $script = <<<'PHP'
            $root = getenv('BYO_ROOT');
            require $root . '/tests/bootstrap.php';
            $app = require $root . '/bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $c = config('database.connections.pgsql_spatial');
            echo json_encode([
                'url'      => $c['url'] ?? null,
                'host'     => $c['host'] ?? null,
                'database' => $c['database'] ?? null,
                'pghost'   => getenv('PGHOST'),
            ]);
            PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script],
            $root,
            [
                'BYO_ROOT'             => $root,
                'SPATIAL_DATABASE_URL' => 'postgres://attacker:secret@hostile.example.invalid:5432/hostile_spatial',
                'SPATIAL_PGHOST'       => 'hostile.example.invalid',
                'SPATIAL_PGDATABASE'   => 'hostile_spatial',
                'PGHOST'               => 'hostile.example.invalid',
                'PGDATABASE'           => 'hostile_app',
            ]
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'Probe process failed: ' . $process->getErrorOutput()
        );

        $resolved = json_decode(trim($process->getOutput()), true);
        $this->assertIsArray($resolved, 'Probe did not return JSON: ' . $process->getOutput());

        $this->assertEmpty($resolved['url'], 'A hostile ambient SPATIAL_DATABASE_URL reached the resolved config.');
        $this->assertEmpty($resolved['host'], 'A hostile ambient SPATIAL_PGHOST reached the resolved config.');
        $this->assertEmpty($resolved['database'], 'A hostile ambient SPATIAL_PGDATABASE reached the resolved config.');
        $this->assertSame('', (string) $resolved['pghost'], 'A hostile ambient PGHOST survived into the process.');

        $this->assertStringNotContainsString('hostile', json_encode($resolved), 'Hostile values leaked into the resolved config.');
    }
}
