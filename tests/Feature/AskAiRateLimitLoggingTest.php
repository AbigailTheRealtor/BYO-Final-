<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AskAiUsageLog;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AskAiRateLimitLoggingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function payload(int $listingId = 1, string $listingType = 'seller'): array
    {
        return [
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'question'     => 'What are the HOA fees?',
        ];
    }

    private function mockRunnerNeverCalled(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);
    }

    private function makeReadyResult(): array
    {
        return [
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'factual'],
            'context'        => ['listing' => []],
            'contract'       => ['rules' => []],
            'prompt_package' => ['prompt' => 'test'],
            'adapter_result' => ['model' => 'gpt-4o', 'raw_response' => 'ok'],
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'Test answer.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated.',
                'source_attribution' => 'Listing data only.',
                'error'              => null,
            ],
            'error' => null,
        ];
    }

    private function assertRateLimitedLogRow(string $limitType, int $listingId = 1, string $listingType = 'seller'): void
    {
        $log = AskAiUsageLog::where('status', 'rate_limited')
            ->where('error_code', $limitType)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, "Expected a rate_limited log row with error_code={$limitType}");
        $this->assertSame('rate_limited', $log->status);
        $this->assertSame($limitType, $log->error_code);
        $this->assertFalse((bool) $log->success);
        $this->assertSame(0, (int) $log->prompt_tokens);
        $this->assertSame(0, (int) $log->completion_tokens);
        $this->assertSame(0, (int) $log->total_tokens);
        $this->assertNull($log->estimated_cost_usd);
        $this->assertSame($listingType, $log->listing_type);
        $this->assertSame($listingId, (int) $log->listing_id);
        $this->assertNotNull($log->question_hash);
        $this->assertSame(64, strlen($log->question_hash));
        $this->assertNotNull($log->ip_address);
    }

    /**
     * (1) guest_ip_hourly: 5 pre-seeded hits trigger the limit; logs a rate_limited row.
     */
    public function test_guest_ip_hourly_logs_rate_limited_row(): void
    {
        $this->mockRunnerNeverCalled();

        $hashedIp = hash('sha256', '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit("ask_ai:guest:{$hashedIp}:hourly", 3600);
        }

        $countBefore = AskAiUsageLog::count();

        $response = $this->postJson('/ask-ai/listing-question', $this->payload(1001));

        $response->assertStatus(429)
            ->assertJson(['error' => ['limit_type' => 'guest_ip_hourly']]);

        $this->assertSame($countBefore + 1, AskAiUsageLog::count());
        $this->assertRateLimitedLogRow('guest_ip_hourly', 1001);
    }

    /**
     * (2) user_hourly: 20 pre-seeded hits trigger the limit; logs a rate_limited row.
     */
    public function test_user_hourly_logs_rate_limited_row(): void
    {
        $this->mockRunnerNeverCalled();

        $user = User::factory()->create(['user_type' => 'buyer']);

        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit("ask_ai:user:{$user->id}:hourly", 3600);
        }

        $countBefore = AskAiUsageLog::count();

        $response = $this->actingAs($user)
            ->postJson('/ask-ai/listing-question', $this->payload(2001));

        $response->assertStatus(429)
            ->assertJson(['error' => ['limit_type' => 'user_hourly']]);

        $this->assertSame($countBefore + 1, AskAiUsageLog::count());

        $log = AskAiUsageLog::where('status', 'rate_limited')
            ->where('error_code', 'user_hourly')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('rate_limited', $log->status);
        $this->assertSame('user_hourly', $log->error_code);
        $this->assertFalse((bool) $log->success);
        $this->assertSame(0, (int) $log->prompt_tokens);
        $this->assertSame(0, (int) $log->completion_tokens);
        $this->assertSame(0, (int) $log->total_tokens);
        $this->assertNull($log->estimated_cost_usd);
        $this->assertSame($user->id, (int) $log->user_id);
    }

    /**
     * (3) admin_daily: 100 pre-seeded hits trigger the limit; logs a rate_limited row.
     */
    public function test_admin_daily_logs_rate_limited_row(): void
    {
        $this->mockRunnerNeverCalled();

        $admin = User::factory()->create(['user_type' => 'admin']);

        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit("ask_ai:admin:{$admin->id}:daily", 86400);
        }

        $countBefore = AskAiUsageLog::count();

        $response = $this->actingAs($admin)
            ->postJson('/ask-ai/listing-question', $this->payload(3001));

        $response->assertStatus(429)
            ->assertJson(['error' => ['limit_type' => 'admin_daily']]);

        $this->assertSame($countBefore + 1, AskAiUsageLog::count());

        $log = AskAiUsageLog::where('status', 'rate_limited')
            ->where('error_code', 'admin_daily')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('rate_limited', $log->status);
        $this->assertSame('admin_daily', $log->error_code);
        $this->assertFalse((bool) $log->success);
        $this->assertSame(0, (int) $log->prompt_tokens);
        $this->assertSame(0, (int) $log->completion_tokens);
        $this->assertSame(0, (int) $log->total_tokens);
        $this->assertNull($log->estimated_cost_usd);
    }

    /**
     * (4) ip_shared_hourly: 30 pre-seeded hits trigger the limit; logs a rate_limited row.
     */
    public function test_ip_shared_hourly_logs_rate_limited_row(): void
    {
        $this->mockRunnerNeverCalled();

        $hashedIp = hash('sha256', '127.0.0.1');
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit("ask_ai:ip:{$hashedIp}:hourly", 3600);
        }

        $countBefore = AskAiUsageLog::count();

        $response = $this->postJson('/ask-ai/listing-question', $this->payload(4001));

        $response->assertStatus(429)
            ->assertJson(['error' => ['limit_type' => 'ip_shared_hourly']]);

        $this->assertSame($countBefore + 1, AskAiUsageLog::count());
        $this->assertRateLimitedLogRow('ip_shared_hourly', 4001);
    }

    /**
     * (5) listing_hourly: 10 pre-seeded hits on the listing key trigger the limit;
     *     logs a rate_limited row. Shared IP and identity counters stay at zero
     *     so only the listing limit fires.
     */
    public function test_listing_hourly_logs_rate_limited_row(): void
    {
        $this->mockRunnerNeverCalled();

        $listingType = 'seller';
        $listingId   = 5001;

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit("ask_ai:listing:{$listingType}:{$listingId}:hourly", 3600);
        }

        $countBefore = AskAiUsageLog::count();

        $response = $this->postJson('/ask-ai/listing-question', $this->payload($listingId, $listingType));

        $response->assertStatus(429)
            ->assertJson(['error' => ['limit_type' => 'listing_hourly']]);

        $this->assertSame($countBefore + 1, AskAiUsageLog::count());
        $this->assertRateLimitedLogRow('listing_hourly', $listingId, $listingType);
    }

    /**
     * (6) 429 response shape is unchanged after adding logging — same three keys, same types.
     */
    public function test_429_response_shape_is_unchanged(): void
    {
        $this->mockRunnerNeverCalled();

        $hashedIp = hash('sha256', '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit("ask_ai:guest:{$hashedIp}:hourly", 3600);
        }

        $response = $this->postJson('/ask-ai/listing-question', $this->payload(6001));
        $response->assertStatus(429);

        $data = $response->json();

        $this->assertArrayHasKey('error', $data);
        $this->assertCount(1, $data);

        $error = $data['error'];
        $this->assertArrayHasKey('message',     $error);
        $this->assertArrayHasKey('retry_after', $error);
        $this->assertArrayHasKey('limit_type',  $error);
        $this->assertCount(3, $error);
        $this->assertIsString($error['message']);
        $this->assertIsInt($error['retry_after']);
        $this->assertIsString($error['limit_type']);

        $response->assertHeader('Retry-After');
    }

    /**
     * (7) A request that passes all limits does NOT produce a rate_limited log row.
     */
    public function test_passing_request_does_not_produce_rate_limited_log_row(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $countBefore = AskAiUsageLog::where('status', 'rate_limited')->count();

        $this->postJson('/ask-ai/listing-question', $this->payload(7001))
            ->assertOk();

        $this->assertSame(
            $countBefore,
            AskAiUsageLog::where('status', 'rate_limited')->count()
        );
    }

    /**
     * (8) A logger failure during rate-limit logging does not change the 429 response.
     */
    public function test_logger_failure_during_rate_limit_does_not_change_429_response(): void
    {
        $this->mockRunnerNeverCalled();

        $loggerMock = $this->createMock(\App\Services\AskAi\AskAiUsageLoggerService::class);
        $loggerMock->method('logListingQuestion')->willThrowException(new \RuntimeException('DB is down'));
        $this->app->instance(\App\Services\AskAi\AskAiUsageLoggerService::class, $loggerMock);

        $hashedIp = hash('sha256', '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit("ask_ai:guest:{$hashedIp}:hourly", 3600);
        }

        $response = $this->postJson('/ask-ai/listing-question', $this->payload(8001));

        $response->assertStatus(429)
            ->assertJson(['error' => ['limit_type' => 'guest_ip_hourly']]);
    }
}
