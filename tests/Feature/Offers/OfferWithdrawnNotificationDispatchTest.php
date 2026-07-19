<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Notifications\Offers\OfferWithdrawnNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfferWithdrawnNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $listingOwner;
    private Offer $submittedOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user         = User::factory()->create(['user_type' => 'seller']);
        $this->listingOwner = User::factory()->create(['user_type' => 'seller']);

        $auction = OfferAuction::factory()->create(['user_id' => $this->listingOwner->id]);

        $this->submittedOffer = Offer::factory()->submitted()->create([
            'user_id'          => $this->user->id,
            'offer_auction_id' => $auction->id,
        ]);
    }

    private function actingAsAllowedUser(?User $user = null): static
    {
        $u = $user ?? $this->user;

        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [$u->id]);

        return $this->actingAs($u);
    }

    private function allowedActions(array $overrides = []): array
    {
        return array_merge([
            'can_submit'        => true,
            'can_counter'       => true,
            'can_accept'        => true,
            'can_reject'        => true,
            'can_withdraw'      => true,
            'can_expire'        => false,
            'can_view_timeline' => true,
            'reasons'           => [
                'submit'        => '',
                'counter'       => '',
                'accept'        => '',
                'reject'        => '',
                'withdraw'      => '',
                'expire'        => 'Only system may expire.',
                'view_timeline' => '',
            ],
        ], $overrides);
    }

    private function mockActionsService(array $actions): void
    {
        $mock = $this->createMock(OfferAvailableActionsService::class);
        $mock->method('forOffer')->willReturn($actions);
        $this->app->instance(OfferAvailableActionsService::class, $mock);
    }

    public function test_successful_withdraw_dispatches_notification(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('withdraw')->willReturn([
            'allowed' => true,
            'reason'  => '',
        ]);
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.withdraw', $this->submittedOffer))
            ->assertOk();

        // HIGH-16: the listing owner (counterparty) is notified of the withdrawal,
        // not the submitter who performed it.
        Notification::assertSentTo($this->listingOwner, OfferWithdrawnNotification::class);
        Notification::assertNotSentTo($this->submittedOffer->user, OfferWithdrawnNotification::class);
    }

    public function test_permission_denial_does_not_dispatch(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions([
            'can_withdraw' => false,
            'reasons'      => array_merge($this->allowedActions()['reasons'], [
                'withdraw' => 'Cannot withdraw: not allowed.',
            ]),
        ]));

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->expects($this->never())->method('withdraw');
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.withdraw', $this->submittedOffer));

        Notification::assertNothingSent();
    }

    public function test_facade_denial_does_not_dispatch(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('withdraw')->willReturn([
            'allowed' => false,
            'reason'  => 'State machine disallowed the transition.',
        ]);
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.withdraw', $this->submittedOffer));

        Notification::assertNothingSent();
    }
}
