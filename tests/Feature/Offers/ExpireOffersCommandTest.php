<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Notifications\Offers\OfferExpiredNotification;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class ExpireOffersCommandTest extends TestCase
{
    use DatabaseTransactions;

    // ── Test 1: No eligible offers ───────────────────────────────────────────

    public function test_no_eligible_offers_outputs_zero(): void
    {
        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 0 offer(s).')
            ->assertExitCode(0);
    }

    // ── Test 2: submitted with past expires_at → expired ────────────────────

    public function test_submitted_offer_with_past_expiry_is_expired(): void
    {
        $offer = Offer::factory()->submitted()->create([
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 1 offer(s).')
            ->assertExitCode(0);

        $this->assertSame('expired', $offer->fresh()->status);
    }

    // ── Test 3: countered with past expires_at → expired ────────────────────

    public function test_countered_offer_with_past_expiry_is_expired(): void
    {
        $offer = Offer::factory()->countered()->create([
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 1 offer(s).')
            ->assertExitCode(0);

        $this->assertSame('expired', $offer->fresh()->status);
    }

    // ── Test 4: future expires_at → unchanged ───────────────────────────────

    public function test_offer_with_future_expiry_is_not_expired(): void
    {
        $offer = Offer::factory()->submitted()->create([
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 0 offer(s).')
            ->assertExitCode(0);

        $this->assertSame('submitted', $offer->fresh()->status);
    }

    // ── Test 5: accepted status → unchanged ─────────────────────────────────

    public function test_accepted_offer_is_not_touched(): void
    {
        $offer = Offer::factory()->accepted()->create([
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 0 offer(s).')
            ->assertExitCode(0);

        $this->assertSame('accepted', $offer->fresh()->status);
    }

    // ── Test 6: facade returns allowed=>false → no exception, completes ──────

    public function test_facade_returning_not_allowed_does_not_throw(): void
    {
        Offer::factory()->submitted()->create([
            'expires_at' => now()->subHour(),
        ]);

        $mock = Mockery::mock(OfferWorkflowFacade::class);
        $mock->shouldReceive('expire')->andReturn([
            'allowed'     => false,
            'from_status' => 'submitted',
            'to_status'   => 'expired',
            'reason'      => 'transition not allowed',
            'event_log'   => null,
        ]);
        $this->app->instance(OfferWorkflowFacade::class, $mock);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 0 offer(s).')
            ->assertExitCode(0);
    }

    // ── Test 7: one rejection does not stop remaining offers ─────────────────

    public function test_one_rejection_does_not_stop_remaining_eligible_offers(): void
    {
        $offerA = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);
        $offerB = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $callCount = 0;

        $mock = Mockery::mock(OfferWorkflowFacade::class);
        $mock->shouldReceive('expire')
            ->twice()
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                return [
                    'allowed'     => $callCount > 1,
                    'from_status' => 'submitted',
                    'to_status'   => 'expired',
                    'reason'      => $callCount === 1 ? 'blocked' : '',
                    'event_log'   => null,
                ];
            });
        $this->app->instance(OfferWorkflowFacade::class, $mock);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 1 offer(s).')
            ->assertExitCode(0);

        $this->assertSame(2, $callCount);
    }

    // ── Test 8: facade called with exact system arguments ────────────────────

    public function test_facade_expire_called_with_system_arguments(): void
    {
        $offer = Offer::factory()->submitted()->create([
            'expires_at' => now()->subHour(),
        ]);

        $capturedArgs = null;

        $mock = Mockery::mock(OfferWorkflowFacade::class);
        $mock->shouldReceive('expire')
            ->once()
            ->andReturnUsing(function (
                $capturedOffer,
                $actorId,
                $actorRole,
                $metadata,
                $ipAddress,
            ) use (&$capturedArgs) {
                $capturedArgs = compact('capturedOffer', 'actorId', 'actorRole', 'metadata', 'ipAddress');
                return [
                    'allowed'     => true,
                    'from_status' => 'submitted',
                    'to_status'   => 'expired',
                    'reason'      => '',
                    'event_log'   => null,
                ];
            });
        $this->app->instance(OfferWorkflowFacade::class, $mock);

        $this->artisan('offers:expire-pending')->assertExitCode(0);

        $this->assertNotNull($capturedArgs);
        $this->assertSame($offer->id, $capturedArgs['capturedOffer']->id);
        $this->assertNull($capturedArgs['actorId']);
        $this->assertSame('system', $capturedArgs['actorRole']);
        $this->assertSame(['source' => 'scheduled_command'], $capturedArgs['metadata']);
        $this->assertNull($capturedArgs['ipAddress']);
    }

    // ── Test 9 (L3): a throwing offer does not stop remaining eligible offers ─

    public function test_one_throwing_offer_does_not_stop_remaining_eligible_offers(): void
    {
        Notification::fake();

        Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);
        Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $callCount = 0;

        $mock = Mockery::mock(OfferWorkflowFacade::class);
        $mock->shouldReceive('expire')
            ->twice()
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    // The first offer blows up mid-expiry (e.g. a downstream error).
                    throw new \RuntimeException('simulated expiry failure');
                }
                return [
                    'allowed'     => true,
                    'from_status' => 'submitted',
                    'to_status'   => 'expired',
                    'reason'      => '',
                    'event_log'   => null,
                ];
            });
        $this->app->instance(OfferWorkflowFacade::class, $mock);

        // The command survives the first failure, processes the second, and exits 0.
        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 1 offer(s).')
            ->assertExitCode(0);

        $this->assertSame(2, $callCount, 'Both offers must be attempted despite the first throwing.');
        // Only the committed (second) offer notifies; the failed one does not.
        Notification::assertSentTimes(OfferExpiredNotification::class, 1);
    }
}
