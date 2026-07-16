<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1.2 — every spatial migration fails CLOSED unless its resolved connection is a
 * configured PostgreSQL target. Covers both required conditions (plan §0):
 *   • connection absent  → RuntimeException
 *   • connection present but NOT PostgreSQL → RuntimeException
 *
 * No real PostgreSQL is contacted: the guard rejects before any DDL, and
 * getDriverName() reads config without opening a PDO.
 */
class SpatialMigrationGuardTest extends TestCase
{
    /** @return array<string,string> file path => migration class name */
    private function spatialMigrations(): array
    {
        $out = [];
        foreach (glob(base_path('database/migrations/spatial') . '/*_*.php') as $file) {
            $src = file_get_contents($file);
            $this->assertMatchesRegularExpression('/class\s+(\w+)\s+extends\s+Migration/', $src,
                "No migration class found in {$file}");
            preg_match('/class\s+(\w+)\s+extends\s+Migration/', $src, $m);
            require_once $file;
            $out[$file] = $m[1];
        }
        return $out;
    }

    /** @test */
    public function every_spatial_migration_targets_the_pgsql_spatial_connection(): void
    {
        foreach ($this->spatialMigrations() as $file => $class) {
            $migration = new $class();
            $this->assertSame('pgsql_spatial', $migration->getConnection(),
                basename($file) . " must declare protected \$connection = 'pgsql_spatial'");
        }
    }

    /** @test */
    public function up_fails_closed_when_the_spatial_connection_is_absent(): void
    {
        DB::purge('pgsql_spatial');
        config(['database.connections.pgsql_spatial' => [
            'driver' => 'pgsql', 'url' => null, 'host' => null,
        ]]);

        foreach ($this->spatialMigrations() as $file => $class) {
            $migration = new $class();
            try {
                $migration->up();
                $this->fail(basename($file) . ' should have thrown when pgsql_spatial is unconfigured.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not configured', $e->getMessage());
            }
        }
    }

    /** @test */
    public function up_fails_closed_when_the_connection_is_not_postgresql(): void
    {
        DB::purge('pgsql_spatial');
        // host present (passes the "configured" check) but driver is sqlite.
        config(['database.connections.pgsql_spatial' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'host' => 'placeholder',
        ]]);

        foreach ($this->spatialMigrations() as $file => $class) {
            $migration = new $class();
            try {
                $migration->up();
                $this->fail(basename($file) . ' should have refused a non-pgsql driver.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString("not 'pgsql'", $e->getMessage());
            }
        }

        DB::purge('pgsql_spatial');
    }

    /** @test */
    public function down_also_fails_closed_on_a_non_postgresql_connection(): void
    {
        DB::purge('pgsql_spatial');
        config(['database.connections.pgsql_spatial' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'host' => 'placeholder',
        ]]);

        foreach ($this->spatialMigrations() as $file => $class) {
            $migration = new $class();
            $this->expectExceptionMessageMatchesForDown($migration, basename($file));
        }

        DB::purge('pgsql_spatial');
    }

    private function expectExceptionMessageMatchesForDown(object $migration, string $label): void
    {
        try {
            $migration->down();
            $this->fail($label . ' down() should have refused a non-pgsql driver.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("not 'pgsql'", $e->getMessage());
        }
    }
}
