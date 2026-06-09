<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\OfferAvailableActionsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferActionVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeActions(array $overrides = []): array
    {
        $defaults = [
            'can_submit'        => false,
            'can_counter'       => false,
            'can_accept'        => false,
            'can_reject'        => false,
            'can_withdraw'      => false,
            'can_expire'        => false,
            'can_view_timeline' => false,
            'reasons'           => [
                'submit'        => 'Not allowed to submit',
                'counter'       => 'Not allowed to counter',
                'accept'        => 'Not allowed to accept',
                'reject'        => 'Not allowed to reject',
                'withdraw'      => 'Not allowed to withdraw',
                'expire'        => 'Not allowed to expire',
                'view_timeline' => '',
            ],
        ];

        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $defaults) && $key !== 'reasons') {
                $defaults[$key] = $value;
            }
        }

        if (isset($overrides['reasons'])) {
            $defaults['reasons'] = array_merge($defaults['reasons'], $overrides['reasons']);
        }

        return $defaults;
    }

    private function mockActionsService(Offer $offer, array $actions): void
    {
        $this->mock(OfferAvailableActionsService::class, function ($mock) use ($offer, $actions) {
            $mock->shouldReceive('forOffer')
                 ->andReturn($actions);
        });
    }

    // ── Test 1: Buyer + draft offer → Submit Offer button is present and not disabled ──

    public function test_buyer_draft_offer_shows_submit_offer_button_enabled(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft', 'role' => 'buyer']);
        $actions = $this->makeActions(['can_submit' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('Submit Offer', $content);
        $this->assertStringContainsString('btn-primary btn-sm', $content);
        $this->assertStringNotContainsString('disabled title=', substr($content, strpos($content, 'Submit Offer') ?: 0, 200));
    }

    // ── Test 2: Seller + submitted offer → Accept and Reject buttons are present and not disabled ──

    public function test_seller_submitted_offer_shows_accept_and_reject_buttons_enabled(): void
    {
        $offer   = Offer::factory()->submitted()->create(['role' => 'seller']);
        $actions = $this->makeActions([
            'can_accept' => true,
            'can_reject' => true,
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('btn-success btn-sm">Accept', $content);
        $this->assertStringContainsString('btn-danger btn-sm">Reject', $content);
    }

    // ── Test 3: Buyer + submitted offer → Withdraw button is present and not disabled ──

    public function test_buyer_submitted_offer_shows_withdraw_button_enabled(): void
    {
        $offer   = Offer::factory()->submitted()->create(['role' => 'buyer']);
        $actions = $this->makeActions(['can_withdraw' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('btn-outline-secondary btn-sm">Withdraw', $content);
    }

    // ── Test 4: Blocked action → disabled button + reason text rendered (no form action) ──

    public function test_blocked_action_produces_no_html(): void
    {
        $offer        = Offer::factory()->create(['status' => 'draft']);
        $blockedReason = 'Offer must be in submitted status';
        $actions = $this->makeActions([
            'reasons' => ['submit' => $blockedReason],
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $submitUrl  = route('offers.submit', $offer);

        $this->assertStringNotContainsString('disabled title="' . $blockedReason . '"', $content, 'Disabled button must not use title= attribute for reason.');
        $this->assertStringNotContainsString('action="' . $submitUrl . '"', $content, 'Blocked action must not have a form action.');
        $this->assertStringContainsString($blockedReason, $content, 'Blocked reason text must appear in the page.');
        $this->assertStringContainsString('Submit Offer', $content, 'Disabled Submit Offer button must still appear.');
    }

    // ── Test 5: Expire action never rendered regardless of can_expire value ──

    public function test_expire_action_never_rendered(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft']);
        $actions = $this->makeActions(['can_expire' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('>Expire<', $content);
        $this->assertStringNotContainsString('>Expire ', $content);
    }

    // ── Test 6: When all actions are disabled, no form tags but disabled buttons with reasons appear ──

    public function test_all_disabled_actions_produce_no_form_tags(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft']);
        $actions = $this->makeActions();

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('<form', $content, 'No form tags must appear when all actions are disabled.');
        $this->assertStringNotContainsString('action=', $content, 'No action= attributes must appear when all actions are disabled.');
        $this->assertStringContainsString('Submit Offer', $content, 'Disabled Submit Offer button must appear with a reason.');
        $this->assertStringContainsString('>Withdraw<', $content, 'Disabled Withdraw button must appear with a reason.');
    }

    // ── Test 7: Enabled actions produce forms; disabled actions of the same type do not ──

    public function test_enabled_action_has_form_and_disabled_action_has_none(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        // Submit enabled — all others disabled
        $actions = $this->makeActions(['can_submit' => true]);
        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $submitUrl  = route('offers.submit', $offer);
        $acceptUrl  = route('offers.accept', $offer);
        $rejectUrl  = route('offers.reject', $offer);
        $withdrawUrl = route('offers.withdraw', $offer);

        $this->assertStringContainsString('<form', $content, 'An enabled action must produce a form element.');
        $this->assertStringContainsString('action="' . $submitUrl . '"', $content, 'Enabled submit must have its route as form action.');
        $this->assertStringNotContainsString('action="' . $acceptUrl . '"', $content, 'Disabled accept must not have a form action.');
        $this->assertStringNotContainsString('action="' . $rejectUrl . '"', $content, 'Disabled reject must not have a form action.');
        $this->assertStringNotContainsString('action="' . $withdrawUrl . '"', $content, 'Disabled withdraw must not have a form action.');
    }

    // ── Test 8: Counter — disabled button + reason when can_counter=false with reason ──

    public function test_counter_renders_nothing_when_can_counter_is_false(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $actions = $this->makeActions(['can_counter' => false]);
        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $counterUrl = route('offers.counter', $offer);

        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content, 'No counter form action must appear when can_counter=false.');
        $this->assertStringNotContainsString('Submit Counter Offer',         $content, 'No "Submit Counter Offer" header must appear when can_counter=false.');
        $this->assertStringNotContainsString('counter-offer-submit-btn',     $content, 'Counter submit button ID must not appear when can_counter=false.');
        $this->assertStringContainsString('Not allowed to counter',          $content, 'Reason text must be visible when can_counter=false and reason is set.');
    }

    // ── Test 9: Tenant offer creator sees enabled Withdraw on a submitted offer ──

    public function test_tenant_offer_creator_sees_enabled_withdraw_button(): void
    {
        $offer   = Offer::factory()->submitted()->create([
            'role'    => 'tenant',
            'user_id' => $this->user->id,
        ]);
        $actions = $this->makeActions(['can_withdraw' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('btn-outline-secondary btn-sm">Withdraw', $content, 'Enabled Withdraw button must appear for offer creator.');
        $this->assertStringNotContainsString(' disabled', substr($content, (int) strpos($content, '>Withdraw<') - 300, 350));
    }

    // ── Test 10: Disabled Withdraw button with reason appears when can_withdraw=false ──

    public function test_no_disabled_withdraw_html_for_any_blocked_role(): void
    {
        $offer   = Offer::factory()->submitted()->create(['role' => 'seller']);
        $actions = $this->makeActions(['can_withdraw' => false]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $withdrawUrl = route('offers.withdraw', $offer);

        $this->assertStringNotContainsString('action="' . $withdrawUrl . '"', $content, 'No Withdraw form action must appear when can_withdraw=false.');
        $this->assertStringContainsString('>Withdraw<', $content, 'Disabled Withdraw button must appear when can_withdraw=false with a reason.');
        $this->assertStringContainsString('Not allowed to withdraw', $content, 'Reason text must appear when can_withdraw=false.');
        $this->assertStringContainsString('btn-outline-secondary btn-sm', $content, 'Disabled Withdraw button must carry its CSS class.');
    }

    // ── Test 11: View Timeline absent when can_view_timeline=false ──

    public function test_view_timeline_absent_when_not_allowed(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_view_timeline' => false]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('View Timeline', $content, 'View Timeline must not appear when can_view_timeline=false.');
    }

    // ── Test 12: View Timeline present when can_view_timeline=true ──

    public function test_view_timeline_present_when_allowed(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_view_timeline' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('View Timeline', $content, 'View Timeline button must appear when can_view_timeline=true.');
    }
}
