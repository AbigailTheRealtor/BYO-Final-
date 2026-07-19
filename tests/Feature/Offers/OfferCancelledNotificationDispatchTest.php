<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Notifications\Offers\OfferCancelledNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * B2.1B — OfferController::cancel notification dispatch.
 *
 * On a successful cancellation, BOTH parties (listing owner + offer submitter)
 * receive OfferCancelledNotification. Permission/facade denials dispatch nothing.
 */
class OfferCancelledNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $submitter;
    private Offer $acceptedOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner     = User::factory()->create(['user_type' => 'seller']);
        $this->submitter = User::factory()->create(['user_type' => 'buyer']);

        $auction = OfferAuction::factory()->create(['user_id' => $this->owner->id]);

        $this->acceptedOffer = Offer::factory()->accepted()->create([
            'user_id'          => $this->submitter->id,
            'offer_auction_id' => $auction->id,
        ]);
    }

    private function actingAsAllowedUser(User $user): static
    {
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [$user->id]);
        return $this->actingAs($user);
    }

    private function actions(array $overrides = []): array
    {
        return array_merge([
            'can_submit' => false, 'can_counter' => false, 'can_accept' => false,
            'can_reject' => false, 'can_withdraw' => false, 'can_cancel' => true,
            'can_expire' => false, 'can_view_timeline' => true,
            'reasons' => [
                'submit' => '', 'counter' => '', 'accept' => '', 'reject' => '',
                'withdraw' => '', 'cancel' => '', 'expire' => '', 'view_timeline' => '',
            ],
        ], $overrides);
    }

    private function mockActions(array $actions): void
    {
        $mock = $this->createMock(OfferAvailableActionsService::class);
        $mock->method('forOffer')->willReturn($actions);
        $this->app->instance(OfferAvailableActionsService::class, $mock);
    }

    public function test_successful_cancel_notifies_both_parties(): void
    {
        Notification::fake();
        $this->mockActions($this->actions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('cancel')->willReturn(['allowed' => true, 'reason' => '']);
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser($this->owner)
            ->postJson(route('offers.cancel', $this->acceptedOffer), ['reason' => 'Financing fell through'])
            ->assertOk();

        Notification::assertSentTo($this->owner, OfferCancelledNotification::class);
        Notification::assertSentTo($this->submitter, OfferCancelledNotification::class);
    }

    public function test_permission_denial_does_not_dispatch(): void
    {
        Notification::fake();
        $this->mockActions($this->actions([
            'can_cancel' => false,
            'reasons'    => array_merge($this->actions()['reasons'], ['cancel' => 'Not allowed.']),
        ]));

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->expects($this->never())->method('cancel');
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser($this->owner)
            ->postJson(route('offers.cancel', $this->acceptedOffer), ['reason' => 'x'])
            ->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_facade_denial_does_not_dispatch(): void
    {
        Notification::fake();
        $this->mockActions($this->actions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('cancel')->willReturn(['allowed' => false, 'reason' => 'State machine disallowed.']);
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser($this->owner)
            ->postJson(route('offers.cancel', $this->acceptedOffer), ['reason' => 'x'])
            ->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_missing_reason_is_rejected_and_dispatches_nothing(): void
    {
        Notification::fake();
        $this->mockActions($this->actions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->expects($this->never())->method('cancel');
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser($this->owner)
            ->postJson(route('offers.cancel', $this->acceptedOffer), [])
            ->assertStatus(422);

        Notification::assertNothingSent();
    }
}
