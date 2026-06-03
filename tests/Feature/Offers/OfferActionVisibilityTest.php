<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferAvailableActionsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferActionVisibilityTest extends TestCase
{
    use DatabaseTransactions;

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
        $this->assertStringContainsString('btn-primary btn-sm">Submit Offer', $content);
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

    // ── Test 4: Blocked action with non-empty reason → disabled button with reason tooltip ──

    public function test_blocked_action_with_reason_shows_disabled_button_with_tooltip(): void
    {
        $offer        = Offer::factory()->create(['status' => 'draft']);
        $blockedReason = 'Offer must be in submitted status';
        $actions = $this->makeActions([
            'reasons' => ['submit' => $blockedReason],
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString($blockedReason, $content);
        $this->assertStringContainsString('disabled title="' . $blockedReason . '"', $content);
        $this->assertStringContainsString('title="' . $blockedReason . '"', $content);
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

    // ── Test 6: When all actions are disabled, no form tags appear on page ──

    public function test_all_disabled_actions_produce_no_form_tags(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft']);
        $actions = $this->makeActions();

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('<form', $content);
        $this->assertStringNotContainsString('action=', $content);
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

    // ── Test 8: Counter — disabled button with reason when can_counter=false and reason is set ──

    public function test_counter_renders_disabled_button_with_reason_when_blocked(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        // Counter disabled with non-empty reason (default in makeActions)
        $actions = $this->makeActions(['can_counter' => false]);
        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $counterUrl = route('offers.counter', $offer);

        $this->assertStringContainsString('Counter', $content, 'Counter must be visible when can_counter=false with a reason.');
        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content, 'Counter must not have a form action when can_counter=false.');
        $this->assertStringContainsString('Not allowed to counter', $content, 'Reason text must be visible when can_counter=false.');

        // disabled attribute must appear adjacent to the Counter label
        $counterPos = strpos($content, '>Counter<');
        $this->assertNotFalse($counterPos);
        $snippet = substr($content, max(0, $counterPos - 200), 250);
        $this->assertStringContainsString(' disabled', $snippet, 'Counter must render as a disabled button when can_counter=false.');
    }
}
