<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Offer $submittedOffer;
    private Offer $draftOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'seller']);

        $this->draftOffer = Offer::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'draft',
        ]);

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

    private function mockFacadeMethod(string $method, array $result): void
    {
        $mock = $this->createMock(OfferWorkflowFacade::class);
        $mock->method($method)->willReturn($result);
        $this->app->instance(OfferWorkflowFacade::class, $mock);
    }

    private function successResult(array $extra = []): array
    {
        return array_merge(['allowed' => true, 'reason' => ''], $extra);
    }

    // ── Test 1: submit happy path ─────────────────────────────────────────────

    public function test_submit_happy_path(): void
    {
        $this->mockActionsService($this->allowedActions());
        $this->mockFacadeMethod('submit', $this->successResult());

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer));

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Offer submitted.']);
    }

    // ── Test 2: accept happy path ─────────────────────────────────────────────

    public function test_accept_happy_path(): void
    {
        $this->mockActionsService($this->allowedActions());
        $this->mockFacadeMethod('accept', $this->successResult());

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.accept', $this->submittedOffer));

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Offer accepted.']);
    }

    // ── Test 3: reject happy path ─────────────────────────────────────────────

    public function test_reject_happy_path(): void
    {
        $this->mockActionsService($this->allowedActions());
        $this->mockFacadeMethod('reject', $this->successResult());

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.reject', $this->submittedOffer));

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Offer rejected.']);
    }

    // ── Test 4: withdraw happy path ───────────────────────────────────────────

    public function test_withdraw_happy_path(): void
    {
        $this->mockActionsService($this->allowedActions());
        $this->mockFacadeMethod('withdraw', $this->successResult());

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.withdraw', $this->submittedOffer));

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Offer withdrawn.']);
    }

    // ── Test 5: counter happy path — verify overrides are allowlisted ────────

    public function test_counter_happy_path(): void
    {
        $this->mockActionsService($this->allowedActions());

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $capturedOverrides = null;

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')
            ->willReturnCallback(function (
                $offer, $actorId, $actorRole, $overrides, $metadata, $ipAddress
            ) use (&$capturedOverrides, $counterOffer) {
                $capturedOverrides = $overrides;
                return $this->successResult(['counter_offer' => $counterOffer]);
            });
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'expires_at'       => now()->addDays(7)->toDateString(),
                'user_id'          => 9999,
                'role'             => 'hacker',
                'offer_auction_id' => 9999,
                'status'           => 'accepted',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Counter offer created.']);

        $this->assertIsArray($capturedOverrides);
        $this->assertArrayNotHasKey('user_id',          $capturedOverrides, 'user_id must not be forwarded as an override.');
        $this->assertArrayNotHasKey('role',              $capturedOverrides, 'role must not be forwarded as an override.');
        $this->assertArrayNotHasKey('offer_auction_id',  $capturedOverrides, 'offer_auction_id must not be forwarded as an override.');
        $this->assertArrayNotHasKey('status',            $capturedOverrides, 'status must not be forwarded as an override.');
        $this->assertArrayHasKey('expires_at', $capturedOverrides);
    }

    // ── Test 6: denied by OfferAvailableActionsService → 422, facade not called ──

    public function test_denied_by_actions_service_returns_422_and_facade_not_called(): void
    {
        $deniedReason = 'Cannot submit: offer status is \'accepted\', expected \'draft\'.';

        $this->mockActionsService($this->allowedActions([
            'can_submit' => false,
            'reasons'    => array_merge($this->allowedActions()['reasons'], [
                'submit' => $deniedReason,
            ]),
        ]));

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->expects($this->never())->method('submit');
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => $deniedReason]);
    }

    // ── Test 7: denied by facade → 422 with result reason ────────────────────

    public function test_denied_by_facade_returns_422(): void
    {
        $this->mockActionsService($this->allowedActions());

        $facadeReason = 'State machine disallowed the transition.';
        $this->mockFacadeMethod('submit', [
            'allowed' => false,
            'reason'  => $facadeReason,
        ]);

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => $facadeReason]);
    }

    // ── Test 8: unauthenticated request → redirect or 401 ────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson(route('offers.submit', $this->draftOffer));

        $response->assertStatus(401);
    }

    // ── Test 9: missing offer → 404 via route-model binding ──────────────────

    public function test_missing_offer_returns_404(): void
    {
        $response = $this->actingAsAllowedUser()
            ->postJson('/offers/999999/submit');

        $response->assertStatus(404);
    }

    // ── Test 10: static scan — no direct Offer.status mutation or OfferEventLog write ──

    public function test_controller_source_does_not_directly_mutate_offer_status_or_write_event_log(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/OfferController.php'));

        $this->assertStringNotContainsString(
            "->status =",
            $source,
            'OfferController must not directly assign ->status on an Offer instance.',
        );

        $this->assertStringNotContainsString(
            "Offer::where",
            $source,
            'OfferController must not run direct Offer::where() queries (use facade).',
        );

        $this->assertStringNotContainsString(
            'OfferEventLog::create',
            $source,
            'OfferController must not write OfferEventLog directly.',
        );

        $this->assertStringNotContainsString(
            'OfferEventLog::insert',
            $source,
            'OfferController must not write OfferEventLog directly.',
        );

        $this->assertStringNotContainsString(
            "->update(['status'",
            $source,
            'OfferController must not directly call ->update([\'status\' on an Offer instance.',
        );
    }
}
