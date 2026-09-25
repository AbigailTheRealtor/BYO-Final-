<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MatchingCanonicalParity as Cmd;
use App\Services\Stellar\Matching\Parity\CanonicalMatchingParityRunner;
use App\Services\Stellar\Matching\Parity\CanonicalParityReport;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-B2 — `php artisan matching:canonical-parity`: option validation, every exit code and the
 * report outputs. The production refusal and the absent override are in
 * MatchingCanonicalParityProductionRefusalTest, which runs outside a transaction.
 */
class MatchingCanonicalParityCommandTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    private const ISOLATE = ['PoolPrivateYN' => true, 'GarageYN' => true, 'WaterfrontYN' => true];

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase migrates the first database test of a process through $this->artisan(),
        // which binds OutputStyle to a mock for that application; Kernel::call() output would
        // then go to the mock instead of the buffer these assertions read.
        unset($this->app[OutputStyle::class]);
    }

    private function parity(array $args = [], ?BufferedOutput $out = null): int
    {
        return $this->app[Kernel::class]->call('matching:canonical-parity', $args, $out ?? new BufferedOutput());
    }

    // ── production (the refusal itself: MatchingCanonicalParityProductionRefusalTest) ──

    public function test_the_refusal_is_the_first_statement_of_handle(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/MatchingCanonicalParity.php'));
        preg_match('/public function handle\(\): int\s*\{(.*?)\n    \}/s', $source, $m);
        $body = preg_replace('#^\s*//.*$#m', '', $m[1] ?? '');

        $this->assertMatchesRegularExpression('/^\s*if \(\$this->refusesProductionDatabase\(false\)\)/', $body);
    }

    // ── exit codes ───────────────────────────────────────────────────────────

    public function test_exit_0_when_only_allowed_differences_are_found(): void
    {
        $this->storeAllBaselineFixtures();

        $out = new BufferedOutput();
        $this->assertSame(Cmd::EXIT_OK, $this->parity([], $out));

        $text = $out->fetch();
        $this->assertStringContainsString('Verdict: PASS', $text);
        $this->assertStringContainsString('Post-attachment tag comparison: NOT_EXERCISED', $text);
        $this->assertMatchesRegularExpression('/Result digest: [0-9a-f]{64}/', $text);
    }

    public function test_exit_1_on_an_unexpected_failure(): void
    {
        $this->app->bind(CanonicalMatchingParityRunner::class, function () {
            throw new RuntimeException('boom');
        });

        $this->assertSame(Cmd::EXIT_FAILURE, $this->parity());
    }

    /** @dataProvider invalidOptions */
    public function test_exit_2_on_invalid_options_before_any_query(array $args): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        $this->assertSame(Cmd::EXIT_INVALID, $this->parity($args));
        $this->assertSame(0, $queries);
    }

    public static function invalidOptions(): array
    {
        return [
            'above the hard ceiling (refused, never clamped)' => [['--max-listings' => '5001']],
            'not a number'           => [['--max-listings' => 'lots']],
            'fractional'             => [['--stride' => '1.5']],
            'bad listing key'        => [['--listing-key' => ['a b']]],
            'key combined with from' => [['--listing-key' => ['K1'], '--from-id' => '5']],
            'output in missing dir'  => [['--output' => '/definitely/not/here/report.json']],
        ];
    }

    public function test_exit_2_for_output_inside_a_web_served_directory(): void
    {
        $this->assertSame(Cmd::EXIT_INVALID, $this->parity(['--output' => public_path('parity.json')]));
        $this->assertFileDoesNotExist(public_path('parity.json'));
    }

    public function test_exit_4_on_an_undeclared_difference(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'undeclared', ['living_area' => 1500.5]);

        $this->assertSame(Cmd::EXIT_UNDECLARED, $this->parity());
    }

    public function test_exit_5_on_an_error_mismatch_and_it_outranks_4(): void
    {
        $this->app->bind(CanonicalMatchingParityRunner::class, fn () => $this->stubRunner([
            'UNDECLARED_DIFFERENCE' => 1, 'ERROR_MISMATCH' => 1,
        ]));

        $this->assertSame(Cmd::EXIT_ERROR_MISMATCH, $this->parity());
    }

    public function test_exit_6_only_when_unresolvable_rows_are_asked_to_fail(): void
    {
        $this->storeBaselineFixture('residential', self::ISOLATE, 'other_provider', ['provider' => 'some_other_mls']);

        $this->assertSame(Cmd::EXIT_OK, $this->parity());
        $this->assertSame(Cmd::EXIT_UNRESOLVABLE, $this->parity(['--fail-on-unresolvable' => true]));
    }

    // ── output ───────────────────────────────────────────────────────────────

    public function test_json_mode_prints_the_report_and_output_writes_it_without_clobbering(): void
    {
        $this->storeAllBaselineFixtures();

        $out = new BufferedOutput();
        $this->assertSame(Cmd::EXIT_OK, $this->parity(['--json' => true], $out));
        $report = json_decode($out->fetch(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(CanonicalParityReport::SCHEMA, $report['schema']);
        $this->assertSame(hash('sha256', CanonicalParityReport::encode($report['result'])), $report['result_digest']);
        $this->assertSame('PASS', $report['verdict']);

        $dir  = sys_get_temp_dir() . '/p1b2-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . '/parity.json';

        $this->assertSame(Cmd::EXIT_OK, $this->parity(['--output' => $file]));
        $this->assertSame($report['result_digest'], json_decode((string) file_get_contents($file), true)['result_digest']);

        $this->assertSame(Cmd::EXIT_INVALID, $this->parity(['--output' => $file]), 'an existing report is not silently overwritten');
        $this->assertSame(Cmd::EXIT_OK, $this->parity(['--output' => $file, '--force-output' => true]));

        unlink($file);
        rmdir($dir);
    }

    public function test_the_command_is_manual_only(): void
    {
        $kernel   = (string) file_get_contents(app_path('Console/Kernel.php'));
        $routes   = implode("\n", array_map('file_get_contents', glob(base_path('routes/*.php'))));

        $this->assertStringNotContainsString('matching:canonical-parity', $kernel, 'never scheduled');
        $this->assertStringNotContainsString('MatchingCanonicalParity', $kernel);
        $this->assertStringNotContainsString('canonical-parity', $routes, 'never routed');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function stubRunner(array $listingStatus): object
    {
        // The runner is final; its report is the seam. A real run over nothing, re-wrapped.
        $empty = (new CanonicalMatchingParityRunner())->run();
        $result = $empty->result;
        $result['listings']['status'] = $listingStatus;

        return new class ($result, $empty) {
            public function __construct(private array $result, private CanonicalParityReport $empty) {}

            public function run(...$args): CanonicalParityReport
            {
                return new CanonicalParityReport($this->result, $this->empty->cost, $this->empty->run);
            }
        };
    }
}
