<?php

namespace Tests\Feature\Safeguards;

use App\Console\Concerns\RefusesProductionDatabase;
use App\Support\Safeguards\ProductionDatabaseGuard;
use App\Support\Safeguards\ProductionDatabaseRefused;
use Database\Seeders\LocationDnaSellerSeeder;
use Database\Seeders\LocationDnaTestSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * The in-process consumers of ProductionDatabaseGuard: the Artisan trait, the fixture seeders and
 * the tinker notice.
 *
 * HOW "PRODUCTION" IS SIMULATED HERE
 * ----------------------------------
 * By pointing the NON-default `pgsql` connection's config at the production host. The guard counts
 * that as production (a script could use that connection by name), and the connection is never
 * used: every consumer under test refuses before any query, and the default connection stays
 * SQLite :memory:. The config is restored in `finally`. TestCase::databaseSafetyViolations() reads
 * only the default and sqlite connections, so the change cannot disturb the PHPUnit isolation guard.
 * No DatabaseTransactions and no RefreshDatabase.
 */
class ProductionDatabaseGuardConsumersTest extends TestCase
{
    // ── Artisan trait ────────────────────────────────────────────────────────

    /** @test */
    public function a_guarded_command_runs_normally_on_the_test_database(): void
    {
        $this->registerProbeCommands();

        $this->artisan('qa:guard-probe-closed')->expectsOutput('BODY RAN')->assertExitCode(0);
    }

    /** @test */
    public function a_guarded_command_refuses_production_and_does_not_run_its_body(): void
    {
        $this->registerProbeCommands();

        $this->whileProductionIsResolvable(function (): void {
            $output = new BufferedOutput();
            $code = $this->app[Kernel::class]->call('qa:guard-probe-closed', [], $output);

            $this->assertSame(ProductionDatabaseGuard::EXIT_REFUSED, $code);
            $this->assertStringContainsString('PRODUCTION DATABASE DETECTED', $output->fetch());
            $this->assertFalse(QaGuardProbeState::$bodyRan, 'The command body ran against production.');
        });
    }

    /** @test */
    public function a_command_that_does_not_declare_the_override_cannot_be_given_it(): void
    {
        $this->registerProbeCommands();

        $this->expectException(\Symfony\Component\Console\Exception\InvalidOptionException::class);
        $this->expectExceptionMessage('i-know-this-is-production');

        $this->app[Kernel::class]->call('qa:guard-probe-closed', ['--' . ProductionDatabaseGuard::OVERRIDE_OPTION => true]);
    }

    /** @test */
    public function a_command_that_accepts_the_override_refuses_without_it_and_runs_with_it(): void
    {
        $this->registerProbeCommands();

        $this->whileProductionIsResolvable(function (): void {
            $refused = new BufferedOutput();
            $this->assertSame(ProductionDatabaseGuard::EXIT_REFUSED, $this->app[Kernel::class]->call('qa:guard-probe-open', [], $refused));
            $this->assertStringContainsString('Re-run it with --i-know-this-is-production', $refused->fetch());
            $this->assertFalse(QaGuardProbeState::$bodyRan);

            $overridden = new BufferedOutput();
            $this->assertSame(0, $this->app[Kernel::class]->call('qa:guard-probe-open', ['--i-know-this-is-production' => true], $overridden));
            $text = $overridden->fetch();
            $this->assertStringContainsString('PRODUCTION OVERRIDE', $text);
            $this->assertStringContainsString('BODY RAN', $text);
            $this->assertTrue(QaGuardProbeState::$bodyRan);
        });
    }

    /** @test */
    public function the_incremental_migration_fixture_refuses_production_before_writing(): void
    {
        $this->whileProductionIsResolvable(function (): void {
            $output = new BufferedOutput();
            $code = $this->app[Kernel::class]->call('migrate:incremental-fixture', ['action' => 'seed'], $output);

            $this->assertSame(ProductionDatabaseGuard::EXIT_REFUSED, $code);
            $this->assertStringContainsString('does not accept a production override', $output->fetch());
        });
    }

    // ── fixture seeders ──────────────────────────────────────────────────────

    /** @test */
    public function the_location_dna_fixture_seeders_throw_on_production(): void
    {
        foreach ([LocationDnaSellerSeeder::class, LocationDnaTestSeeder::class] as $seeder) {
            $this->whileProductionIsResolvable(function () use ($seeder): void {
                try {
                    (new $seeder())->run();
                    $this->fail("{$seeder} ran against production.");
                } catch (ProductionDatabaseRefused $e) {
                    $this->assertStringContainsString($seeder, $e->getMessage());
                    $this->assertStringContainsString("connection 'pgsql' resolves to host 'helium'", $e->getMessage());
                }
            });
        }
    }

    // ── tinker notice ────────────────────────────────────────────────────────

    /** @test */
    public function tinker_warns_on_production_and_says_nothing_otherwise(): void
    {
        $quiet = $this->dispatchCommandStarting('tinker');
        $this->assertSame('', $quiet, 'The tinker notice fired on the test database.');

        $this->whileProductionIsResolvable(function (): void {
            $this->assertStringContainsString('TINKER IS CONNECTED TO PRODUCTION', $this->dispatchCommandStarting('tinker'));
            $this->assertSame('', $this->dispatchCommandStarting('migrate:status'), 'The notice fired for a command other than tinker.');
        });
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function whileProductionIsResolvable(callable $callback): void
    {
        $original = config('database.connections.pgsql');
        QaGuardProbeState::$bodyRan = false;

        try {
            config(['database.connections.pgsql' => ['driver' => 'pgsql', 'url' => null, 'host' => 'helium', 'database' => 'heliumdb']]);
            $this->assertTrue(ProductionDatabaseGuard::assessApplication($this->app)->isProduction(), 'The simulation did not register as production.');

            $callback();
        } finally {
            config(['database.connections.pgsql' => $original]);
        }

        $this->assertFalse(ProductionDatabaseGuard::assessApplication($this->app)->isProduction(), 'The production simulation leaked.');
    }

    private function dispatchCommandStarting(string $command): string
    {
        $output = new BufferedOutput();
        $this->app['events']->dispatch(new CommandStarting($command, new ArrayInput([]), $output));

        return $output->fetch();
    }

    private function registerProbeCommands(): void
    {
        $kernel = $this->app[Kernel::class];
        $kernel->registerCommand(new QaGuardProbeClosedCommand());
        $kernel->registerCommand(new QaGuardProbeOpenCommand());
        QaGuardProbeState::$bodyRan = false;
    }
}

final class QaGuardProbeState
{
    public static bool $bodyRan = false;
}

class QaGuardProbeClosedCommand extends Command
{
    use RefusesProductionDatabase;

    protected $signature = 'qa:guard-probe-closed';

    public function handle(): int
    {
        if ($this->refusesProductionDatabase()) {
            return ProductionDatabaseGuard::EXIT_REFUSED;
        }

        QaGuardProbeState::$bodyRan = true;
        $this->line('BODY RAN');

        return 0;
    }
}

class QaGuardProbeOpenCommand extends Command
{
    use RefusesProductionDatabase;

    protected $signature = 'qa:guard-probe-open {--i-know-this-is-production}';

    public function handle(): int
    {
        if ($this->refusesProductionDatabase(true)) {
            return ProductionDatabaseGuard::EXIT_REFUSED;
        }

        QaGuardProbeState::$bodyRan = true;
        $this->line('BODY RAN');

        return 0;
    }
}
