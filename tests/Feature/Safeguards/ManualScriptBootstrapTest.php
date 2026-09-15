<?php

namespace Tests\Feature\Safeguards;

use App\Support\Safeguards\ProductionDatabaseGuard;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * ManualScriptBootstrap end to end: a real script, a real PHP process, a real Laravel bootstrap.
 *
 * WHY A CHILD PROCESS
 * -------------------
 * The behaviour under test is that the process stops before the providers boot and before the
 * script body runs, with a message and an exit status. In-process that could only be mocked. So
 * each case writes a throwaway script (to the system temp directory, never under scripts/), runs
 * it with an environment set explicitly for every variable that can select a database, and reads
 * back STDOUT, STDERR and the exit code.
 *
 * A shutdown function registered BEFORE boot() reports whether the service providers had booted
 * when the process ended. `exit` runs shutdown functions, so a refusal still reports, and it must
 * report 0.
 *
 * SAFETY OF THE TEST ITSELF
 * -------------------------
 * The refused cases name the production host, and nothing is ever sent to it: the guard reads
 * config and the process exits before any provider, facade or query exists. The cases that get
 * past the guard (permitted, overridden) all use SQLite :memory: with every libpq variable blanked.
 * The override is proven with APP_ENV=production, never with a production DSN, so a test that
 * went wrong could not reach the production server.
 */
class ManualScriptBootstrapTest extends TestCase
{
    private const PRODUCTION_URL = 'postgresql://qa:secret@helium:5432/heliumdb?sslmode=disable';

    /** @var list<string> */
    private array $fixtures = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $fixture) {
            @unlink($fixture);
        }

        parent::tearDown();
    }

    /** @test */
    public function a_local_database_is_permitted_and_the_script_body_runs(): void
    {
        $run = $this->runScript($this->fixture(false), [], $this->localEnvironment());

        $this->assertSame(0, $run['exit'], 'A local run failed: ' . $run['stderr']);
        $this->assertStringContainsString('SCRIPT_BODY_RAN', $run['stdout']);
        $this->assertStringContainsString('PROVIDERS_BOOTED=1', $run['stdout']);
        $this->assertStringNotContainsString('PRODUCTION', $run['stderr']);
    }

    /** @test */
    public function the_production_database_is_refused_before_anything_boots(): void
    {
        $run = $this->runScript($this->fixture(false), [], [
            'APP_ENV' => 'production',
            'DATABASE_URL' => self::PRODUCTION_URL,
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => 'helium',
            'DB_DATABASE' => 'heliumdb',
            'PGHOST' => 'helium',
            'PGDATABASE' => 'heliumdb',
        ] + $this->localEnvironment());

        $this->assertRefusedBeforeBoot($run);
        $this->assertStringContainsString("connection 'pgsql': driver=pgsql host=helium database=heliumdb", $run['stderr']);
        $this->assertStringContainsString('does not accept a production override', $run['stderr']);
        $this->assertStringNotContainsString('secret', $run['stderr'], 'A credential reached the refusal message.');
    }

    /** @test */
    public function production_reached_only_through_database_url_is_refused(): void
    {
        // Everything else in the environment says "local SQLite".
        $run = $this->runScript($this->fixture(false), [], ['DATABASE_URL' => self::PRODUCTION_URL] + $this->localEnvironment());

        $this->assertRefusedBeforeBoot($run);
        $this->assertStringContainsString("DATABASE_URL points at host 'helium'", $run['stderr']);
        $this->assertStringContainsString('host=helium database=heliumdb', $run['stderr']);
    }

    /** @test */
    public function production_reached_only_through_the_libpq_environment_is_refused(): void
    {
        $run = $this->runScript($this->fixture(false), [], ['PGHOST' => 'helium'] + $this->localEnvironment());

        $this->assertRefusedBeforeBoot($run);
        $this->assertStringContainsString("PGHOST is 'helium'", $run['stderr']);
    }

    /** @test */
    public function the_override_runs_a_permitting_script_and_says_so_loudly(): void
    {
        $run = $this->runScript($this->fixture(true), [ProductionDatabaseGuard::OVERRIDE_FLAG], ['APP_ENV' => 'production'] + $this->localEnvironment());

        $this->assertSame(0, $run['exit'], 'The override did not let the script run: ' . $run['stderr']);
        $this->assertStringContainsString('SCRIPT_BODY_RAN', $run['stdout']);
        $this->assertStringContainsString('PRODUCTION OVERRIDE', $run['stderr']);
        $this->assertSame(1, substr_count($run['stderr'], 'PRODUCTION OVERRIDE'), 'The banner should print once, not once per check.');
    }

    /** @test */
    public function a_permitting_script_without_the_flag_is_still_refused(): void
    {
        $run = $this->runScript($this->fixture(true), [], ['APP_ENV' => 'production'] + $this->localEnvironment());

        $this->assertRefusedBeforeBoot($run);
        $this->assertStringContainsString('Re-run it with --i-know-this-is-production', $run['stderr']);
    }

    /** @test */
    public function the_flag_is_ignored_by_a_script_that_does_not_accept_it(): void
    {
        $run = $this->runScript($this->fixture(false), [ProductionDatabaseGuard::OVERRIDE_FLAG], ['APP_ENV' => 'production'] + $this->localEnvironment());

        $this->assertRefusedBeforeBoot($run);
    }

    /** @test */
    public function vague_flags_and_environment_variables_do_not_override(): void
    {
        $production = ['APP_ENV' => 'production', 'I_KNOW_THIS_IS_PRODUCTION' => '1', 'FORCE' => '1'] + $this->localEnvironment();

        foreach ([['--force'], ['--i-know-this-is-production=1'], ['-y']] as $arguments) {
            $run = $this->runScript($this->fixture(true), $arguments, $production);
            $this->assertRefusedBeforeBoot($run, 'arguments ' . implode(' ', $arguments));
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function assertRefusedBeforeBoot(array $run, string $context = ''): void
    {
        $context = $context === '' ? '' : " ({$context})";

        $this->assertSame(ProductionDatabaseGuard::EXIT_REFUSED, $run['exit'], "Not refused{$context}. STDERR: {$run['stderr']} STDOUT: {$run['stdout']}");
        $this->assertStringContainsString('PRODUCTION DATABASE DETECTED', $run['stderr'], "No refusal message{$context}.");
        $this->assertStringNotContainsString('SCRIPT_BODY_RAN', $run['stdout'], "The script body ran{$context}.");
        $this->assertStringContainsString('PROVIDERS_BOOTED=0', $run['stdout'], "Providers booted before the refusal{$context}.");
    }

    /** Every variable that can select a database, set to a local SQLite target. */
    private function localEnvironment(): array
    {
        return [
            'APP_ENV' => 'local',
            // A config cache would bypass config/*.php entirely. Point it at nothing.
            'APP_CONFIG_CACHE' => sys_get_temp_dir() . '/manual-script-guard-no-config-cache.php',
            'DATABASE_URL' => '',
            'DB_CONNECTION' => 'sqlite',
            'DB_HOST' => '127.0.0.1',
            'DB_DATABASE' => ':memory:',
            'PGHOST' => '', 'PGHOSTADDR' => '', 'PGPORT' => '', 'PGDATABASE' => '', 'PGUSER' => '', 'PGPASSWORD' => '', 'PGSERVICE' => '',
            'SPATIAL_DATABASE_URL' => '', 'SPATIAL_PGHOST' => '', 'SPATIAL_PGDATABASE' => '',
            'REPLIT_DEPLOYMENT' => '',
            'LOG_CHANNEL' => 'null',
            'CACHE_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    private function fixture(bool $permitOverride): string
    {
        $reserved = tempnam(sys_get_temp_dir(), 'manual-script-guard-');
        $path = $reserved . '.php';
        array_push($this->fixtures, $reserved, $path);

        $autoload = var_export(base_path('vendor/autoload.php'), true);
        $permit = $permitOverride ? 'true' : 'false';

        file_put_contents($path, <<<PHP
            <?php
            require {$autoload};

            register_shutdown_function(static function (): void {
                \$app = \\Illuminate\\Container\\Container::getInstance();
                echo 'PROVIDERS_BOOTED=' . ((\$app instanceof \\Illuminate\\Foundation\\Application && \$app->isBooted()) ? '1' : '0') . PHP_EOL;
            });

            \$app = \\App\\Support\\Safeguards\\ManualScriptBootstrap::boot(__FILE__, null, {$permit});

            echo 'SCRIPT_BODY_RAN' . PHP_EOL;
            PHP);

        return $path;
    }

    /** @return array{exit: ?int, stdout: string, stderr: string} */
    private function runScript(string $script, array $arguments, array $environment): array
    {
        $process = new Process(
            array_merge([(new PhpExecutableFinder())->find() ?: 'php', $script], $arguments),
            base_path(),
            $environment,
            null,
            60,
        );
        $process->run();

        return ['exit' => $process->getExitCode(), 'stdout' => $process->getOutput(), 'stderr' => $process->getErrorOutput()];
    }
}
