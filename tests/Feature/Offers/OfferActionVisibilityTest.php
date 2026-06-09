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

    // ── Test 4: can_submit=false → entire block silently hidden (no disabled button, no reason) ──

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
        $this->assertStringNotContainsString($blockedReason, $content, 'Submit reason text must not appear in the page when can_submit is false.');
        $this->assertStringNotContainsString('Submit Offer', $content, 'Submit Offer must not appear when can_submit is false.');
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
        $this->assertStringNotContainsString('Submit Offer', $content, 'Submit Offer must not appear when can_submit is false.');
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
        $this->assertStringContainsString('href="#offer-timeline"', $content, 'View Timeline must link to #offer-timeline anchor.');
        $this->assertStringContainsString('id="offer-timeline"', $content, 'Negotiation Timeline card must have id="offer-timeline".');
    }

    // ── Test 13: Submitted offer page does not contain "Cannot submit" reason text ──

    public function test_submitted_offer_does_not_contain_cannot_submit_text(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions([
            'reasons' => ['submit' => 'Cannot submit — offer is already submitted'],
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('Cannot submit', $content, 'Submit reason text must never appear for a submitted offer.');
    }

    // ── Test 14: Submitted offer with can_submit=false renders no disabled Submit Offer button ──

    public function test_submitted_offer_with_can_submit_false_renders_no_submit_button(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_submit' => false]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('Submit Offer', $content, 'Submit Offer must not appear at all when can_submit is false.');
    }

    // ── Test 15: Draft offer page contains both save button labels ──

    public function test_draft_offer_shows_both_save_buttons(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft', 'user_id' => $this->user->id]);
        $actions = $this->makeActions();

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('Save Offer Terms', $content, 'Save Offer Terms button must appear on a draft offer.');
        $this->assertStringContainsString('Save &amp; Submit Offer', $content, 'Save &amp; Submit Offer button must appear on a draft offer.');
    }

    // ── Test 16: Draft offer Save & Submit button carries btn-success class ──

    public function test_draft_offer_save_and_submit_button_has_btn_success_class(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft', 'user_id' => $this->user->id]);
        $actions = $this->makeActions();

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $btnPos = strpos($content, 'id="save-and-submit-offer-btn"');
        $this->assertNotFalse($btnPos, 'save-and-submit-offer-btn must be present on a draft offer page.');
        $surrounding = substr($content, max(0, $btnPos - 150), 350);
        $this->assertStringContainsString('btn-success', $surrounding, 'Save &amp; Submit Offer button must carry btn-success class.');
        $this->assertStringNotContainsString('background:white', $surrounding, 'Save &amp; Submit Offer button must not have a white background override.');
        $this->assertStringNotContainsString('background:transparent', $surrounding, 'Save &amp; Submit Offer button must not have a transparent background override.');
    }

    // ── Test 17: Submitter of active offer sees Withdraw and View Timeline but not Accept or Reject ──

    public function test_submitter_of_active_offer_sees_withdraw_not_accept_or_reject(): void
    {
        $offer = Offer::factory()->submitted()->create(['user_id' => $this->user->id]);
        $actions = $this->makeActions([
            'can_withdraw'      => true,
            'can_view_timeline' => true,
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('Withdraw', $content, 'Submitter must see the Withdraw button.');
        $this->assertStringContainsString('View Timeline', $content, 'Submitter must see the View Timeline button.');
        $this->assertStringNotContainsString('>Accept<', $content, 'Submitter must not see the Accept button.');
        $this->assertStringNotContainsString('>Reject<', $content, 'Submitter must not see the Reject button.');
    }
}
