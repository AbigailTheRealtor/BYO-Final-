<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MatchingCanonicalParity as Cmd;
use App\Services\Stellar\Matching\Parity\CanonicalMatchingParityRunner;
use App\Support\Safeguards\ProductionDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * P1-B2 — `matching:canonical-parity` refuses production, first, with no override.
 *
 * Production is simulated exactly as ProductionDatabaseGuardConsumersTest does it: the
 * non-default `pgsql` connection points at the production host. That connection is never
 * opened — the refusal is decided from configuration alone. No database trait here: the
 * refusal must not depend on (or be observed through) an open transaction.
 */
class MatchingCanonicalParityProductionRefusalTest extends TestCase
{
    public function test_production_is_refused_before_any_query_option_or_runner(): void
    {
        $this->app->bind(CanonicalMatchingParityRunner::class, function () {
            throw new RuntimeException('the runner was constructed against production');
        });

        $this->whileProductionIsResolvable(function (): void {
            $queries = 0;
            DB::listen(function () use (&$queries) { $queries++; });

            $out  = new BufferedOutput();
            // Invalid options too: nothing is read — not even the options — before the refusal.
            $code = $this->app[Kernel::class]->call('matching:canonical-parity', ['--max-listings' => '999999', '--output' => '/nonexistent/x.json'], $out);
            $text = $out->fetch();

            $this->assertSame(Cmd::EXIT_REFUSED, $code);
            $this->assertSame(ProductionDatabaseGuard::EXIT_REFUSED, $code);
            $this->assertSame(0, $queries, 'no query may run before the production refusal');
            $this->assertStringContainsString('PRODUCTION DATABASE DETECTED', $text);
            $this->assertStringContainsString('does not accept a production override', $text);
            $this->assertStringNotContainsString('max-listings', $text, 'the options were never validated');
        });
    }

    public function test_the_override_flag_is_rejected_because_the_command_never_declares_it(): void
    {
        $command = $this->app[Kernel::class]->all()['matching:canonical-parity'];
        $this->assertFalse($command->getDefinition()->hasOption(ProductionDatabaseGuard::OVERRIDE_OPTION));

        $this->whileProductionIsResolvable(function (): void {
            try {
                $this->app[Kernel::class]->call('matching:canonical-parity', ['--' . ProductionDatabaseGuard::OVERRIDE_OPTION => true]);
                $this->fail('the override flag was accepted');
            } catch (InvalidOptionException $e) {
                $this->assertStringContainsString(ProductionDatabaseGuard::OVERRIDE_OPTION, $e->getMessage());
            }
        });
    }

    public function test_no_other_route_around_the_refusal_exists_in_source(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/MatchingCanonicalParity.php'));

        $this->assertStringContainsString('refusesProductionDatabase(false)', $source);
        $this->assertStringNotContainsString('refusesProductionDatabase(true)', $source);
        $this->assertStringNotContainsString('{--i-know-this-is-production', $source);
        $this->assertStringNotContainsString('env(', $source, 'no environment variable may relax the refusal');
        $this->assertStringNotContainsString('getenv(', $source);
        $this->assertStringNotContainsString('$_SERVER', $source);
        $this->assertStringNotContainsString('$_ENV', $source);
    }

    private function whileProductionIsResolvable(callable $callback): void
    {
        $original = config('database.connections.pgsql');

        try {
            config(['database.connections.pgsql' => ['driver' => 'pgsql', 'url' => null, 'host' => 'helium', 'database' => 'heliumdb']]);
            $this->assertTrue(ProductionDatabaseGuard::assessApplication($this->app)->isProduction(), 'The simulation did not register as production.');

            $callback();
        } finally {
            config(['database.connections.pgsql' => $original]);
        }

        $this->assertFalse(ProductionDatabaseGuard::assessApplication($this->app)->isProduction(), 'The production simulation leaked.');
    }
}
