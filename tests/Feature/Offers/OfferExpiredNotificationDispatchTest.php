<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Notifications\Offers\OfferExpiredNotification;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class OfferExpiredNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    // ── Test 1: Successful expiration dispatches notification ────────────────

    public function test_successful_expiration_dispatches_notification_to_offer_owner(): void
    {
        Notification::fake();

        $offer = Offer::factory()->submitted()->create([
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('offers:expire-pending')->assertExitCode(0);

        Notification::assertSentTo($offer->user, OfferExpiredNotification::class);
    }

    // ── Test 2: Facade denial sends no notification ──────────────────────────

    public function test_facade_denial_sends_no_notification(): void
    {
        Notification::fake();

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

        $this->artisan('offers:expire-pending')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    // ── Test 3: Mixed batch — only allowed expirations dispatch ──────────────

    public function test_mixed_batch_only_allowed_expirations_dispatch(): void
    {
        Notification::fake();

        $offerA = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);
        $offerB = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $callCount = 0;

        $mock = Mockery::mock(OfferWorkflowFacade::class);
        $mock->shouldReceive('expire')
            ->twice()
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                return [
                    'allowed'     => $callCount === 1,
                    'from_status' => 'submitted',
                    'to_status'   => 'expired',
                    'reason'      => $callCount === 1 ? '' : 'blocked',
                    'event_log'   => null,
                ];
            });
        $this->app->instance(OfferWorkflowFacade::class, $mock);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 1 offer(s).')
            ->assertExitCode(0);

        Notification::assertSentTimes(OfferExpiredNotification::class, 1);
    }

    // ── Test 4: Command output reads "Expired X offer(s)." correctly ─────────

    public function test_command_output_reflects_allowed_count(): void
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
                return [
                    'allowed'     => true,
                    'from_status' => 'submitted',
                    'to_status'   => 'expired',
                    'reason'      => '',
                    'event_log'   => null,
                ];
            });
        $this->app->instance(OfferWorkflowFacade::class, $mock);

        $this->artisan('offers:expire-pending')
            ->expectsOutput('Expired 2 offer(s).')
            ->assertExitCode(0);
    }
}
