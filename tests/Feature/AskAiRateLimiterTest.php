<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AskAiRateLimiterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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
            'adapter_result' => ['raw' => 'ok'],
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

    private function mockRunner(): AskAiRunnerV2Service
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);
        return $mock;
    }

    private function payload(int $listingId = 1): array
    {
        return [
            'listing_type' => 'seller',
            'listing_id'   => $listingId,
            'question'     => 'What are the HOA fees?',
        ];
    }

    /**
     * (a) Guest IP hourly limit blocks on the 6th request.
     *
     * Listing IDs 5001-5005 are used (one per request) so the per-listing
     * limit (10/hr) is never reached within this test.
     */
    public function test_guest_ip_hourly_limit_blocks_on_sixth_request(): void
    {
        $this->mockRunner();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/ask-ai/listing-question', $this->payload(5001 + $i))
                 ->assertOk();
        }

        $this->postJson('/ask-ai/listing-question', $this->payload(5099))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'guest_ip_hourly']]);
    }

    /**
     * (b) Logged-in user hourly limit blocks on the 21st request.
     *
     * Each of the 20 allowed requests targets a distinct listing ID so the
     * per-listing cap (10/hr) is never hit before the user cap (20/hr).
     * The shared-IP cap (30/hr) also stays under its limit (20 < 30).
     */
    public function test_user_hourly_limit_blocks_on_21st_request(): void
    {
        $this->mockRunner();
        $user = User::factory()->create(['user_type' => 'buyer']);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)
                 ->postJson('/ask-ai/listing-question', $this->payload(6001 + $i))
                 ->assertOk();
        }

        $this->actingAs($user)
             ->postJson('/ask-ai/listing-question', $this->payload(6099))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'user_hourly']]);
    }

    /**
     * (c) Shared IP hourly limit blocks on the 31st across mixed guest/auth requests.
     *
     * Two users each make 15 requests, all to distinct listing IDs so neither
     * the per-listing cap nor the per-user cap (20/hr) fires first.
     * After 30 total requests from the same IP the shared-IP cap (30/hr) fires.
     */
    public function test_shared_ip_hourly_limit_blocks_on_31st_mixed_request(): void
    {
        $this->mockRunner();

        $userA = User::factory()->create(['user_type' => 'buyer']);
        $userB = User::factory()->create(['user_type' => 'buyer']);

        // User A: 15 requests, each to a unique listing (shared IP = 15)
        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($userA)
                 ->postJson('/ask-ai/listing-question', $this->payload(7001 + $i))
                 ->assertOk();
        }

        // User B: 15 requests, each to a unique listing (shared IP = 30)
        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($userB)
                 ->postJson('/ask-ai/listing-question', $this->payload(7101 + $i))
                 ->assertOk();
        }

        // 31st request from the same IP — shared IP cap fires
        $this->actingAs($userB)
             ->postJson('/ask-ai/listing-question', $this->payload(7999))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'ip_shared_hourly']]);
    }

    /**
     * (d) Per-listing hourly limit blocks on the 11th request regardless of requester identity.
     *
     * Two different users contribute to the same listing counter, proving
     * the cap is listing-scoped, not identity-scoped.
     */
    public function test_listing_hourly_limit_blocks_on_11th_request(): void
    {
        $this->mockRunner();

        $userA = User::factory()->create(['user_type' => 'buyer']);
        $userB = User::factory()->create(['user_type' => 'buyer']);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($userA)
                 ->postJson('/ask-ai/listing-question', $this->payload(8001))
                 ->assertOk();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($userB)
                 ->postJson('/ask-ai/listing-question', $this->payload(8001))
                 ->assertOk();
        }

        // 11th request to listing 8001 — per-listing cap fires regardless of who asks
        $this->actingAs($userA)
             ->postJson('/ask-ai/listing-question', $this->payload(8001))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'listing_hourly']]);
    }

    /**
     * (e) A request under all limits returns 200 and the runner is called exactly once.
     */
    public function test_request_under_all_limits_returns_200(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postJson('/ask-ai/listing-question', $this->payload(9001))
             ->assertOk();
    }

    /**
     * (f) The runner is never called when a limit is exceeded.
     */
    public function test_runner_never_called_when_limit_exceeded(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        // Pre-seed the guest IP counter to its maximum without going through HTTP
        $hashedIp = hash('sha256', '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit("ask_ai:guest:{$hashedIp}:hourly", 3600);
        }

        $this->postJson('/ask-ai/listing-question', $this->payload(9002))
             ->assertStatus(429);
    }

    /**
     * (g) The 429 response body contains exactly error.message, error.retry_after,
     *     and error.limit_type — no internal details are exposed.
     */
    public function test_429_response_body_shape_is_exact(): void
    {
        $this->mockRunner();

        $hashedIp = hash('sha256', '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit("ask_ai:guest:{$hashedIp}:hourly", 3600);
        }

        $response = $this->postJson('/ask-ai/listing-question', $this->payload(9003));
        $response->assertStatus(429);

        $data = $response->json();

        // Top level must have exactly one key: 'error'
        $this->assertArrayHasKey('error', $data);
        $this->assertCount(1, $data);

        $error = $data['error'];

        // error must contain exactly these three keys
        $this->assertArrayHasKey('message',     $error);
        $this->assertArrayHasKey('retry_after', $error);
        $this->assertArrayHasKey('limit_type',  $error);
        $this->assertCount(3, $error);

        // Types
        $this->assertIsString($error['message']);
        $this->assertIsInt($error['retry_after']);
        $this->assertIsString($error['limit_type']);

        // No internal detail leaks in the message
        $this->assertStringNotContainsString('Exception', $error['message']);
        $this->assertStringNotContainsString('stack',     $error['message']);
    }
}
