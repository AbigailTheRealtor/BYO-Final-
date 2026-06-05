<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\User;
use App\Notifications\Offers\OfferRejectedNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfferRejectedNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Offer $submittedOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'seller']);

        $this->submittedOffer = Offer::factory()->submitted()->create([
            'user_id' => $this->user->id,
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

    public function test_successful_reject_dispatches_notification(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('reject')->willReturn([
            'allowed' => true,
            'reason'  => '',
        ]);
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.reject', $this->submittedOffer))
            ->assertOk();

        Notification::assertSentTo($this->submittedOffer->user, OfferRejectedNotification::class);
    }

    public function test_permission_denial_does_not_dispatch(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions([
            'can_reject' => false,
            'reasons'    => array_merge($this->allowedActions()['reasons'], [
                'reject' => 'Cannot reject: not allowed.',
            ]),
        ]));

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->expects($this->never())->method('reject');
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.reject', $this->submittedOffer));

        Notification::assertNothingSent();
    }

    public function test_facade_denial_does_not_dispatch(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions());

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('reject')->willReturn([
            'allowed' => false,
            'reason'  => 'State machine disallowed the transition.',
        ]);
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.reject', $this->submittedOffer));

        Notification::assertNothingSent();
    }
}
