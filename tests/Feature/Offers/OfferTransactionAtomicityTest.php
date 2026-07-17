<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferEventLog;
use App\Models\OfferMeta;
use App\Models\User;
use App\Notifications\Offers\OfferAcceptedNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferDecisionService;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferStateMachineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/**
 * BLK-06 — transaction atomicity, post-lock re-check, and notification safety.
 */
class OfferTransactionAtomicityTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function auction(): OfferAuction
    {
        return OfferAuction::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    private function rootOffer(OfferAuction $auction, string $status = 'submitted'): Offer
    {
        return Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => User::factory()->create()->id,
            'status'           => $status,
            'parent_offer_id'  => null,
        ]);
    }

    /** An event logger that explodes when the competing-close tries to log. */
    private function loggerThatThrowsOnRejected(): OfferEventLogService
    {
        $logger = Mockery::mock(OfferEventLogService::class);
        $logger->shouldReceive('log')->andReturnUsing(
            function ($offer, $actorId, $actorRole, $eventType) {
                if ($eventType === 'offer_rejected') {
                    throw new \RuntimeException('simulated failure closing a competing offer');
                }
                return new OfferEventLog();
            }
        );

        return $logger;
    }

    // ── A failure mid-transaction rolls back ALL status changes ──────────────

    public function test_failure_during_competing_close_rolls_back_the_whole_accept(): void
    {
        $auction    = $this->auction();
        $accepted   = $this->rootOffer($auction, 'submitted');
        $competitor = $this->rootOffer($auction, 'submitted');

        $service = new OfferDecisionService(
            new OfferStateMachineService(),
            $this->loggerThatThrowsOnRejected(),
        );

        try {
            $service->accept($accepted, null, 'system');
            $this->fail('Expected the simulated failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('competing offer', $e->getMessage());
        }

        // Nothing committed: the accepted offer is still submitted, the competitor
        // is still submitted, and no acceptance snapshot survived.
        $this->assertSame('submitted', Offer::find($accepted->id)->status);
        $this->assertSame('submitted', Offer::find($competitor->id)->status);
        $this->assertFalse(
            OfferMeta::where('offer_id', $accepted->id)
                ->where('meta_key', 'accepted_terms_snapshot')
                ->exists()
        );
    }

    // ── Stale in-memory state is re-checked against the locked row ────────────

    public function test_stale_status_is_rechecked_after_locking(): void
    {
        $offer = Offer::factory()->submitted()->create();

        // Simulate another request committing a terminal transition out-of-band,
        // AFTER we already hold a stale in-memory 'submitted' copy.
        Offer::query()->whereKey($offer->id)->update(['status' => 'withdrawn']);

        // $offer (in memory) still says 'submitted'; the service must re-read the
        // locked row and refuse the reject because the committed status is terminal.
        $result = app(OfferDecisionService::class)->reject($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertSame('withdrawn', Offer::find($offer->id)->status);
    }

    public function test_stale_status_blocks_a_second_accept_after_lock(): void
    {
        $offer = Offer::factory()->submitted()->create();

        Offer::query()->whereKey($offer->id)->update(['status' => 'accepted']);

        $result = app(OfferDecisionService::class)->accept($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
    }

    // ── No acceptance notification is emitted for a rolled-back accept ───────

    public function test_no_acceptance_notification_when_accept_rolls_back(): void
    {
        Notification::fake();

        $owner   = User::factory()->create(['user_type' => 'seller']);
        $auction = OfferAuction::factory()->create(['user_id' => $owner->id]);

        $accepted   = $this->rootOffer($auction, 'submitted');
        $competitor = $this->rootOffer($auction, 'submitted');

        // Allow the action at the controller layer.
        $actions = Mockery::mock(OfferAvailableActionsService::class);
        $actions->shouldReceive('forOffer')->andReturn([
            'can_submit' => true, 'can_counter' => true, 'can_accept' => true,
            'can_reject' => true, 'can_withdraw' => true, 'can_expire' => false,
            'can_view_timeline' => true,
            'reasons' => [
                'submit' => '', 'counter' => '', 'accept' => '', 'reject' => '',
                'withdraw' => '', 'expire' => '', 'view_timeline' => '',
            ],
        ]);
        $this->app->instance(OfferAvailableActionsService::class, $actions);

        // Force the competing-close to fail so the accept transaction rolls back.
        $this->app->instance(OfferEventLogService::class, $this->loggerThatThrowsOnRejected());

        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [$owner->id]);

        $response = $this->actingAs($owner)
            ->postJson(route('offers.accept', $accepted));

        // The request fails (rolled back) and NO acceptance notification is sent.
        $this->assertSame(500, $response->getStatusCode());
        Notification::assertNotSentTo($accepted->user, OfferAcceptedNotification::class);
        Notification::assertNothingSent();

        // The offer is unchanged — the rollback held.
        $this->assertSame('submitted', Offer::find($accepted->id)->status);
        $this->assertSame('submitted', Offer::find($competitor->id)->status);
    }
}
