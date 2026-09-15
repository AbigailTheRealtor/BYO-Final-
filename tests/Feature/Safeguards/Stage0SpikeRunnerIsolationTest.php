<?php

namespace Tests\Feature\Safeguards;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The two Stage 0 PostGIS spike runners refuse any target that is not explicitly named and
 * isolated, before the first psql command.
 *
 * NO DATABASE IS EVER CONTACTED
 * -----------------------------
 * `psql` is a stub on PATH (and in PSQL_BIN) that writes its arguments and the libpq environment
 * it inherited to a log file, then exits 0. "Refused before psql" is proven by that log NOT
 * existing. "Permitted and isolated" is proven by what the log says the connection would have
 * been. The runners are copied to a temp directory first: both write into a `results/` tree
 * next to themselves, and the real one holds committed evidence.
 *
 * The production-shaped environment mirrors what the Replit workspace injects into every shell
 * (PGHOST=helium, PGDATABASE=heliumdb, a PGPASSWORD). It only ever reaches the stub.
 *
 * @see spikes/phase-2-batch-0a-postgis-knn/lib/require-isolated-target.sh
 */
class Stage0SpikeRunnerIsolationTest extends TestCase
{
    private const SPIKE = 'spikes/phase-2-batch-0a-postgis-knn';

    private const RUNNERS = [
        'run_spike' => 'run_spike.sh',
        'run_provider_spike' => 'provider-validation/run_provider_spike.sh',
    ];

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/stage0-isolation-' . getmypid() . '-' . bin2hex(random_bytes(4));
        File::copyDirectory(base_path(self::SPIKE), "{$this->sandbox}/spike");

        File::ensureDirectoryExists("{$this->sandbox}/bin");
        file_put_contents("{$this->sandbox}/bin/psql", <<<'SH'
            #!/usr/bin/env bash
            {
                printf 'ARGS=%s\n' "$*"
                for v in PGHOST PGHOSTADDR PGPORT PGDATABASE PGUSER PGPASSWORD PGSERVICE PGSERVICEFILE; do
                    if [ "${!v+set}" = set ]; then printf '%s=%s\n' "$v" "${!v}"; else printf '%s=<unset>\n' "$v"; fi
                done
                printf -- '--\n'
            } >> "$STAGE0_PSQL_LOG"
            exit 0
            SH);
        chmod("{$this->sandbox}/bin/psql", 0755);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    // ── refusal ──────────────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider unsafeTargets
     */
    public function each_runner_refuses_an_unsafe_target_before_any_psql_command(array $target, string $expected): void
    {
        foreach (array_keys(self::RUNNERS) as $runner) {
            foreach ($runner === 'run_provider_spike' ? [[], ['--dry-run']] : [[]] as $arguments) {
                $run = $this->runRunner($runner, $arguments, $target);
                $context = "{$runner} " . implode(' ', $arguments);

                $this->assertSame(3, $run['exit'], "{$context} was not refused. STDERR: {$run['stderr']} STDOUT: {$run['stdout']}");
                $this->assertStringContainsString('STAGE 0 SPIKE REFUSED', $run['stderr'], $context);
                $this->assertStringContainsString($expected, $run['stderr'], $context);
                $this->assertFileDoesNotExist($this->psqlLog(), "{$context} invoked psql before refusing.");
                $this->assertStringNotContainsString('provider   :', $run['stdout'], "{$context} got past the guard.");
            }
        }
    }

    public function unsafeTargets(): array
    {
        $isolated = ['SPIKE_PGHOST' => '127.0.0.1', 'SPIKE_PGDATABASE' => 'stage0_spike_qa'];

        return [
            'ambient production only, nothing named' => [[], 'SPIKE_PGHOST is not set'],
            'blank host' => [['SPIKE_PGHOST' => '   ', 'SPIKE_PGDATABASE' => 'spike'], 'SPIKE_PGHOST is not set'],
            'host but no database' => [['SPIKE_PGHOST' => '127.0.0.1'], 'SPIKE_PGDATABASE is not set'],
            'production host named explicitly' => [['SPIKE_PGHOST' => 'helium', 'SPIKE_PGDATABASE' => 'spike'], "names 'helium'"],
            'production host, cased, subdomain, port' => [['SPIKE_PGHOST' => 'HELIUM.internal:5432', 'SPIKE_PGDATABASE' => 'spike'], 'production database host'],
            'production host inside a host list' => [['SPIKE_PGHOST' => '127.0.0.1,helium', 'SPIKE_PGDATABASE' => 'spike'], "names 'helium'"],
            'production database named explicitly' => [['SPIKE_PGHOST' => '127.0.0.1', 'SPIKE_PGDATABASE' => 'heliumdb'], "'heliumdb'"],
            'renamed production database' => [['SPIKE_PGHOST' => '127.0.0.1', 'SPIKE_PGDATABASE' => 'HeliumDB_copy'], 'production application database'],
            'replit deployment' => [$isolated + ['REPLIT_DEPLOYMENT' => '1'], 'REPLIT_DEPLOYMENT is set'],
            'app env production' => [$isolated + ['APP_ENV' => 'production'], 'APP_ENV=production'],
        ];
    }

    // ── permitted, and isolated from the ambient environment ─────────────────

    /** @test */
    public function run_spike_uses_only_the_named_target_and_scrubs_inherited_libpq_redirects(): void
    {
        $run = $this->runRunner('run_spike', [], $this->isolatedTarget());

        $this->assertSame(0, $run['exit'], "An isolated target was refused. STDERR: {$run['stderr']}");
        $calls = $this->psqlCalls();

        $this->assertCount(7, $calls, 'run_spike.sh should run its seven SQL steps.');
        foreach ($calls as $call) {
            $this->assertIsolatedConnection($call);
            $this->assertSame('postgres', $call['PGUSER']);
            $this->assertSame('spike', $call['PGPASSWORD'], 'run_spike.sh should use its own disposable-container password, not the inherited one.');
        }
        $this->assertStringContainsString('00_setup.sql', $calls[0]['ARGS']);
    }

    /** @test */
    public function run_provider_spike_uses_only_the_named_target_and_scrubs_inherited_libpq_redirects(): void
    {
        $target = $this->isolatedTarget() + ['PROVIDER' => 'crunchy', 'TIER' => '1', 'SPIKE_PGPORT' => '6543', 'SPIKE_PGUSER' => 'spike_tester'];

        $dry = $this->runRunner('run_provider_spike', ['--dry-run'], $target);
        $this->assertSame(0, $dry['exit'], "Dry run refused an isolated target. STDERR: {$dry['stderr']}");
        $this->assertStringContainsString('target     : spike_tester@127.0.0.1:6543/stage0_spike_qa', $dry['stdout']);
        $this->assertStringNotContainsString('helium', $dry['stdout']);
        $this->assertFileDoesNotExist($this->psqlLog(), '--dry-run must not invoke psql.');

        $run = $this->runRunner('run_provider_spike', [], $target);
        $this->assertSame(0, $run['exit'], "An isolated target was refused. STDERR: {$run['stderr']}");
        $calls = $this->psqlCalls();

        $this->assertCount(8, $calls, 'run_provider_spike.sh should run its eight SQL steps.');
        foreach ($calls as $call) {
            $this->assertIsolatedConnection($call);
            $this->assertStringContainsString('-h 127.0.0.1 -p 6543 -U spike_tester -d stage0_spike_qa', $call['ARGS']);
            $this->assertSame('<unset>', $call['PGPASSWORD'], 'The inherited PGPASSWORD reached psql; the wrapper promises ~/.pgpass only.');
        }
    }

    /** @test */
    public function both_runners_install_the_guard_and_never_read_the_ambient_target_themselves(): void
    {
        foreach (self::RUNNERS as $name => $path) {
            $source = (string) file_get_contents(base_path(self::SPIKE . '/' . $path));
            $code = implode("\n", array_filter(explode("\n", $source), static fn (string $line): bool => ! preg_match('/^\s*#/', $line)));

            $guardAt = strpos($code, 'stage0_require_isolated_target');
            $this->assertNotFalse($guardAt, "{$name} does not call stage0_require_isolated_target.");
            $this->assertStringContainsString('require-isolated-target.sh', $code, "{$name} does not source the guard.");

            $firstPsql = strpos($code, '"${PSQL[@]}"');
            $this->assertNotFalse($firstPsql, "{$name}: could not find its psql invocation; update this test.");
            $this->assertLessThan($firstPsql, $guardAt, "{$name} can reach psql before the guard.");

            foreach (['${PGHOST:-', '${PGDATABASE:-', 'PGHOSTADDR'] as $ambient) {
                $this->assertStringNotContainsString($ambient, $code, "{$name} reads the ambient {$ambient} again.");
            }
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function isolatedTarget(): array
    {
        // Named isolated target, while every inherited libpq redirect still points at production.
        return [
            'SPIKE_PGHOST' => '127.0.0.1',
            'SPIKE_PGDATABASE' => 'stage0_spike_qa',
            'PGHOSTADDR' => '10.99.99.99',
            'PGSERVICE' => 'production',
            'PGSERVICEFILE' => '/nonexistent/pg_service.conf',
        ];
    }

    private function assertIsolatedConnection(array $call): void
    {
        $this->assertSame('127.0.0.1', $call['PGHOST']);
        $this->assertSame('stage0_spike_qa', $call['PGDATABASE']);
        $this->assertSame('<unset>', $call['PGHOSTADDR'], 'An inherited PGHOSTADDR would override -h.');
        $this->assertSame('<unset>', $call['PGSERVICE']);
        $this->assertSame('<unset>', $call['PGSERVICEFILE']);
        $this->assertStringNotContainsString('helium', implode(' ', $call));
    }

    /** @return array{exit: ?int, stdout: string, stderr: string} */
    private function runRunner(string $runner, array $arguments, array $overrides): array
    {
        @unlink($this->psqlLog());

        $environment = array_merge([
            // What every Replit workspace shell carries.
            'PGHOST' => 'helium',
            'PGDATABASE' => 'heliumdb',
            'PGPORT' => '5432',
            'PGUSER' => 'postgres',
            'PGPASSWORD' => 'ambient-production-password',
            'DATABASE_URL' => 'postgresql://postgres:ambient@helium/heliumdb?sslmode=disable',
            'PGHOSTADDR' => false,
            'PGSERVICE' => false,
            'PGSERVICEFILE' => false,
            'REPLIT_DEPLOYMENT' => false,
            'APP_ENV' => false,
            'SPIKE_PGHOST' => false,
            'SPIKE_PGDATABASE' => false,
            'SPIKE_PGPORT' => false,
            'SPIKE_PGUSER' => false,
            'SPIKE_PGPASSWORD' => false,
            'PROVIDER' => false,
            'TIER' => false,
            'PSQL_BIN' => "{$this->sandbox}/bin/psql",
            'PATH' => "{$this->sandbox}/bin:" . (getenv('PATH') ?: '/usr/bin:/bin'),
            'STAGE0_PSQL_LOG' => $this->psqlLog(),
        ], $overrides);

        $process = new Process(
            array_merge(['bash', "{$this->sandbox}/spike/" . self::RUNNERS[$runner]], $arguments),
            $this->sandbox,
            $environment,
            null,
            60,
        );
        $process->run();

        return ['exit' => $process->getExitCode(), 'stdout' => $process->getOutput(), 'stderr' => $process->getErrorOutput()];
    }

    private function psqlLog(): string
    {
        return "{$this->sandbox}/psql.log";
    }

    /** @return list<array<string, string>> */
    private function psqlCalls(): array
    {
        $this->assertFileExists($this->psqlLog(), 'psql was never invoked.');

        $calls = [];
        foreach (array_filter(explode("--\n", (string) file_get_contents($this->psqlLog()))) as $block) {
            $call = [];
            foreach (explode("\n", trim($block)) as $line) {
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $call[$key] = $value;
            }
            $calls[] = $call;
        }

        return $calls;
    }
}
