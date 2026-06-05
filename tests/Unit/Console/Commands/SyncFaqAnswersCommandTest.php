<?php

namespace Tests\Unit\Console\Commands;

use App\Services\AskAi\AskAiFaqEnrichmentService;
use Mockery;
use Tests\TestCase;

/**
 * SyncFaqAnswersCommandTest
 *
 * Tests the ask-ai:sync-faq-answers Artisan command.
 * The AskAiFaqEnrichmentService is mocked so no database is touched.
 *
 * Test coverage:
 *   A. Success path — synced count line matches exact command output; exit code 0
 *   B. Success path — skipped count line matches exact command output; exit code 0
 *   C. Success path — keys listed in output; exit code 0
 *   D. Error path — error message shown in output; exit code 1
 *   E. Argument forwarding — listing_type and listing_id passed to sync()
 *   F. Command has no own persistence tables
 *   G. Service file contains no OpenAI or HTTP calls
 */
class SyncFaqAnswersCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockService(array $syncReturn): void
    {
        $mock = Mockery::mock(AskAiFaqEnrichmentService::class);
        $mock->shouldReceive('sync')->andReturn($syncReturn);
        $this->app->instance(AskAiFaqEnrichmentService::class, $mock);
    }

    private function commandFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Console/Commands/SyncFaqAnswers.php';
    }

    // =========================================================================
    // Case A — success path — synced count line
    // =========================================================================

    public function test_case_A_success_path_reports_synced_count_line(): void
    {
        $this->mockService([
            'synced'  => ['roof_age', 'hvac'],
            'skipped' => [],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->expectsOutput('Synced:  2 answer(s).')
            ->assertExitCode(0);
    }

    public function test_case_A_success_path_zero_synced_reports_zero(): void
    {
        $this->mockService([
            'synced'  => [],
            'skipped' => [],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->expectsOutput('Synced:  0 answer(s).')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Case B — success path — skipped count line
    // =========================================================================

    public function test_case_B_success_path_reports_skipped_count_line(): void
    {
        $this->mockService([
            'synced'  => ['roof_age'],
            'skipped' => ['empty_field', 'null_field'],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->expectsOutput('Skipped: 2 blank/empty answer(s).')
            ->assertExitCode(0);
    }

    public function test_case_B_success_path_zero_skipped_reports_zero(): void
    {
        $this->mockService([
            'synced'  => ['roof_age'],
            'skipped' => [],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->expectsOutput('Skipped: 0 blank/empty answer(s).')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Case C — success path — synced/skipped key lists in output
    // =========================================================================

    public function test_case_C_synced_keys_listed_in_output(): void
    {
        $this->mockService([
            'synced'  => ['roof_age', 'hvac'],
            'skipped' => [],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->expectsOutput('Keys synced: roof_age, hvac')
            ->assertExitCode(0);
    }

    public function test_case_C_skipped_keys_listed_in_output(): void
    {
        $this->mockService([
            'synced'  => [],
            'skipped' => ['empty_field', 'null_field'],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->expectsOutput('Keys skipped: empty_field, null_field')
            ->assertExitCode(0);
    }

    public function test_case_C_no_keys_lines_when_both_empty(): void
    {
        $this->mockService([
            'synced'  => [],
            'skipped' => [],
            'error'   => null,
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
        ])
            ->doesntExpectOutput('Keys synced:')
            ->doesntExpectOutput('Keys skipped:')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Case D — error path
    // =========================================================================

    public function test_case_D_error_path_shows_error_message(): void
    {
        $this->mockService([
            'synced'  => [],
            'skipped' => [],
            'error'   => 'Listing not found for type seller and id 999',
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'seller',
            'listing_id'   => 999,
        ])
            ->expectsOutput('Listing not found for type seller and id 999')
            ->assertExitCode(1);
    }

    public function test_case_D_error_path_returns_failure_exit_code(): void
    {
        $this->mockService([
            'synced'  => [],
            'skipped' => [],
            'error'   => 'Something went wrong',
        ]);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'landlord',
            'listing_id'   => 5,
        ])->assertExitCode(1);
    }

    // =========================================================================
    // Case E — argument forwarding
    // =========================================================================

    public function test_case_E_listing_type_and_id_forwarded_to_service(): void
    {
        $capturedType = null;
        $capturedId   = null;

        $mock = Mockery::mock(AskAiFaqEnrichmentService::class);
        $mock->shouldReceive('sync')
            ->once()
            ->andReturnUsing(function (string $type, int $id) use (&$capturedType, &$capturedId) {
                $capturedType = $type;
                $capturedId   = $id;
                return ['synced' => [], 'skipped' => [], 'error' => null];
            });
        $this->app->instance(AskAiFaqEnrichmentService::class, $mock);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'tenant',
            'listing_id'   => 42,
        ])->assertExitCode(0);

        $this->assertSame('tenant', $capturedType,
            'listing_type argument must be forwarded to sync() as-is');
        $this->assertSame(42, $capturedId,
            'listing_id argument must be forwarded to sync() as an integer');
    }

    public function test_case_E_buyer_listing_type_forwarded_correctly(): void
    {
        $capturedType = null;

        $mock = Mockery::mock(AskAiFaqEnrichmentService::class);
        $mock->shouldReceive('sync')
            ->once()
            ->andReturnUsing(function (string $type, int $id) use (&$capturedType) {
                $capturedType = $type;
                return ['synced' => ['buyer_motivation'], 'skipped' => [], 'error' => null];
            });
        $this->app->instance(AskAiFaqEnrichmentService::class, $mock);

        $this->artisan('ask-ai:sync-faq-answers', [
            'listing_type' => 'buyer',
            'listing_id'   => 7,
        ])->assertExitCode(0);

        $this->assertSame('buyer', $capturedType,
            "'buyer' listing_type must be forwarded to sync() without modification");
    }

    // =========================================================================
    // Case F — command has no own persistence tables
    // =========================================================================

    public function test_case_F_command_has_no_own_persistence_tables(): void
    {
        $source   = file_get_contents($this->commandFilePath());
        $lines    = explode("\n", $source);
        $offences = [];

        $createPatterns = ['Schema::create', 'Schema::table', 'DB::statement', 'createTable'];

        foreach ($lines as $i => $line) {
            $stripped = preg_replace('/\/\/.*$/', '', $line);
            $stripped = preg_replace('/\*.*$/', '', $stripped);

            foreach ($createPatterns as $pattern) {
                if (str_contains($stripped, $pattern)) {
                    $offences[] = sprintf('Line %d: %s', $i + 1, trim($line));
                }
            }
        }

        $this->assertEmpty($offences,
            "Command must not contain schema-creation calls:\n" . implode("\n", $offences));
    }

    // =========================================================================
    // Case G — command file contains no OpenAI or HTTP calls
    // =========================================================================

    public function test_case_G_command_file_has_no_openai_or_http_calls(): void
    {
        $source   = file_get_contents($this->commandFilePath());
        $lines    = explode("\n", $source);
        $offences = [];

        $forbidden = ['OpenAI', 'openai', 'Http::get', 'Http::post', 'guzzle', 'curl_'];

        foreach ($lines as $i => $line) {
            $stripped = preg_replace('/\/\/.*$/', '', $line);
            $stripped = preg_replace('/\*.*$/', '', $stripped);

            foreach ($forbidden as $pattern) {
                if (str_contains($stripped, $pattern)) {
                    $offences[] = sprintf('Line %d: %s', $i + 1, trim($line));
                }
            }
        }

        $this->assertEmpty($offences,
            "Command file must not contain OpenAI or HTTP calls:\n" . implode("\n", $offences));
    }
}
