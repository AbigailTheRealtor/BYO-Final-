<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate-limiter coverage for the Ask AI listing-question endpoint.
 *
 * NOTE: the endpoint is now authenticated and owner-scoped (the AskAi engine
 * serves private consumer offer-listings, not public MLS data). Consequently:
 *   - the guest rate-limit tier is unreachable via this route — guests are
 *     rejected by the auth middleware (401) before any rate limiting; and
 *   - the per-listing counter can only be incremented by the listing's owner,
 *     so the per-listing cap is exercised by a single owning account.
 * The user-hourly, shared-IP, per-listing, and 429-shape behaviours remain and
 * are exercised below with authenticated owners of the targeted listings.
 */
class AskAiRateLimiterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Exercise the controller's AskAiRateLimitService, not the edge throttle;
        // CSRF uses the blade token in production. Auth middleware stays active.
        $this->withoutMiddleware([ThrottleRequests::class, VerifyCsrfToken::class]);
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

    /** Create $count seller listings owned by $user; returns their ids. */
    private function ownedListings(User $user, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = SellerAgentAuction::forceCreate([
                'user_id'  => $user->id,
                'title'    => 'Owned listing',
                'is_draft' => true,
            ])->id;
        }
        return $ids;
    }

    private function payload(int $listingId): array
    {
        return [
            'listing_type' => 'seller',
            'listing_id'   => $listingId,
            'question'     => 'What are the HOA fees?',
        ];
    }

    /**
     * (a) Unauthenticated requests are rejected by auth before any rate limiting
     *     (the guest rate-limit tier is unreachable via this route).
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postJson('/ask-ai/listing-question', $this->payload(1))
             ->assertUnauthorized();
    }

    /**
     * (b) Logged-in user hourly limit blocks on the 21st request.
     *     Each allowed request targets a distinct OWNED listing so the per-listing
     *     cap (10/hr) is never hit before the user cap (20/hr); the shared-IP cap
     *     (30/hr) also stays under its limit (20 < 30).
     */
    public function test_user_hourly_limit_blocks_on_21st_request(): void
    {
        $this->mockRunner();
        $user = User::factory()->create(['user_type' => 'buyer']);
        $ids  = $this->ownedListings($user, 21);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)
                 ->postJson('/ask-ai/listing-question', $this->payload($ids[$i]))
                 ->assertOk();
        }

        $this->actingAs($user)
             ->postJson('/ask-ai/listing-question', $this->payload($ids[20]))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'user_hourly']]);
    }

    /**
     * (c) Shared IP hourly limit blocks on the 31st across two users on one IP.
     *     Each user targets distinct OWNED listings so neither the per-listing nor
     *     the per-user (20/hr) cap fires first.
     */
    public function test_shared_ip_hourly_limit_blocks_on_31st_mixed_request(): void
    {
        $this->mockRunner();

        $userA = User::factory()->create(['user_type' => 'buyer']);
        $userB = User::factory()->create(['user_type' => 'buyer']);
        $idsA  = $this->ownedListings($userA, 15);
        $idsB  = $this->ownedListings($userB, 16);

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($userA)
                 ->postJson('/ask-ai/listing-question', $this->payload($idsA[$i]))
                 ->assertOk();
        }
        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($userB)
                 ->postJson('/ask-ai/listing-question', $this->payload($idsB[$i]))
                 ->assertOk();
        }

        // 31st request from the same IP — shared IP cap fires.
        $this->actingAs($userB)
             ->postJson('/ask-ai/listing-question', $this->payload($idsB[15]))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'ip_shared_hourly']]);
    }

    /**
     * (d) Per-listing hourly limit blocks on the 11th request to one listing.
     *     Under owner-scoping only the owner can hit their own listing, so the
     *     cap is exercised by the single owning account.
     */
    public function test_listing_hourly_limit_blocks_on_11th_request(): void
    {
        $this->mockRunner();

        $owner     = User::factory()->create(['user_type' => 'buyer']);
        $listingId = $this->ownedListings($owner, 1)[0];

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($owner)
                 ->postJson('/ask-ai/listing-question', $this->payload($listingId))
                 ->assertOk();
        }

        $this->actingAs($owner)
             ->postJson('/ask-ai/listing-question', $this->payload($listingId))
             ->assertStatus(429)
             ->assertJson(['error' => ['limit_type' => 'listing_hourly']]);
    }

    /**
     * (e) A request under all limits returns 200 and the runner is called once.
     */
    public function test_request_under_all_limits_returns_200(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $owner     = User::factory()->create(['user_type' => 'buyer']);
        $listingId = $this->ownedListings($owner, 1)[0];

        $this->actingAs($owner)
             ->postJson('/ask-ai/listing-question', $this->payload($listingId))
             ->assertOk();
    }

    /**
     * (f) The runner is never called when a limit is exceeded.
     *     Pre-seed the per-listing counter for an owned listing to its max.
     */
    public function test_runner_never_called_when_limit_exceeded(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $owner     = User::factory()->create(['user_type' => 'buyer']);
        $listingId = $this->ownedListings($owner, 1)[0];

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit("ask_ai:listing:seller:{$listingId}:hourly", 3600);
        }

        $this->actingAs($owner)
             ->postJson('/ask-ai/listing-question', $this->payload($listingId))
             ->assertStatus(429);
    }

    /**
     * (g) The 429 response body contains exactly error.message, error.retry_after,
     *     and error.limit_type — no internal details are exposed.
     */
    public function test_429_response_body_shape_is_exact(): void
    {
        $this->mockRunner();

        $owner     = User::factory()->create(['user_type' => 'buyer']);
        $listingId = $this->ownedListings($owner, 1)[0];

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit("ask_ai:listing:seller:{$listingId}:hourly", 3600);
        }

        $response = $this->actingAs($owner)
             ->postJson('/ask-ai/listing-question', $this->payload($listingId));
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
