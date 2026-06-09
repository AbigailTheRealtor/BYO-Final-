<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Notifications\Offers\OfferSubmittedNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfferSubmittedNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $listingOwner;
    private Offer $draftOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user         = User::factory()->create(['user_type' => 'seller']);
        $this->listingOwner = User::factory()->create(['user_type' => 'seller']);

        $auction = OfferAuction::factory()->create(['user_id' => $this->listingOwner->id]);

        $this->draftOffer = Offer::factory()->create([
            'user_id'          => $this->user->id,
            'offer_auction_id' => $auction->id,
            'status'           => 'draft',
        ]);
        $this->draftOffer->saveMeta('offer_price', '480000');
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

    private function mockFacadeMethod(string $method, array $result): void
    {
        $mock = $this->createMock(OfferWorkflowFacade::class);
        $mock->method($method)->willReturn($result);
        $this->app->instance(OfferWorkflowFacade::class, $mock);
    }

    // ── Test 1: successful submit dispatches OfferSubmittedNotification ───────

    public function test_successful_submit_dispatches_notification(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions());
        $this->mockFacadeMethod('submit', ['allowed' => true, 'reason' => '']);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer))
            ->assertOk();

        Notification::assertSentTo($this->listingOwner, OfferSubmittedNotification::class);
    }

    // ── Test 2: permission denial does not dispatch ───────────────────────────

    public function test_permission_denial_does_not_dispatch_notification(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions([
            'can_submit' => false,
            'reasons'    => array_merge($this->allowedActions()['reasons'], [
                'submit' => 'Cannot submit: offer is not in draft status.',
            ]),
        ]));

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->expects($this->never())->method('submit');
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer))
            ->assertStatus(422);

        Notification::assertNothingSent();
    }

    // ── Test 3: facade denial does not dispatch ───────────────────────────────

    public function test_facade_denial_does_not_dispatch_notification(): void
    {
        Notification::fake();

        $this->mockActionsService($this->allowedActions());
        $this->mockFacadeMethod('submit', [
            'allowed' => false,
            'reason'  => 'State machine disallowed the transition.',
        ]);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer))
            ->assertStatus(422);

        Notification::assertNothingSent();
    }
}
