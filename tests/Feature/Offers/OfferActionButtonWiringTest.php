<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferAvailableActionsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferActionButtonWiringTest extends TestCase
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
            $mock->shouldReceive('forOffer')->andReturn($actions);
        });
    }

    // ── Test 1: Enabled submit → form POSTs to offers.submit ─────────────────

    public function test_enabled_submit_has_form_posting_to_submit_route(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft']);
        $actions = $this->makeActions(['can_submit' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();
        $url     = route('offers.submit', $offer);

        $this->assertStringContainsString('<form', $content);
        $this->assertStringContainsString('action="' . $url . '"', $content);
        $this->assertStringContainsString('Submit Offer', $content);
    }

    // ── Test 2: Enabled accept → form POSTs to offers.accept ─────────────────

    public function test_enabled_accept_has_form_posting_to_accept_route(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_accept' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();
        $url     = route('offers.accept', $offer);

        $this->assertStringContainsString('action="' . $url . '"', $content);
        $this->assertStringContainsString('Accept', $content);
    }

    // ── Test 3: Enabled reject → form POSTs to offers.reject ─────────────────

    public function test_enabled_reject_has_form_posting_to_reject_route(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_reject' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();
        $url     = route('offers.reject', $offer);

        $this->assertStringContainsString('action="' . $url . '"', $content);
        $this->assertStringContainsString('Reject', $content);
    }

    // ── Test 4: Enabled withdraw → form POSTs to offers.withdraw ─────────────

    public function test_enabled_withdraw_has_form_posting_to_withdraw_route(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_withdraw' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();
        $url     = route('offers.withdraw', $offer);

        $this->assertStringContainsString('action="' . $url . '"', $content);
        $this->assertStringContainsString('Withdraw', $content);
    }

    // ── Test 5: Every enabled form includes @csrf token ───────────────────────

    public function test_enabled_action_form_contains_csrf_token(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft']);
        $actions = $this->makeActions(['can_submit' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('_token', $content);
    }

    // ── Test 6: Disabled action renders button but no wrapping form ───────────

    public function test_disabled_action_renders_button_without_form(): void
    {
        $offer  = Offer::factory()->create(['status' => 'draft']);
        $reason = 'Offer must be in submitted status';
        $actions = $this->makeActions([
            'reasons' => ['submit' => $reason],
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $submitUrl  = route('offers.submit', $offer);

        $this->assertStringContainsString('disabled', $content);
        $this->assertStringContainsString('Submit Offer', $content);
        $this->assertStringNotContainsString('action="' . $submitUrl . '"', $content);
        $this->assertStringContainsString($reason, $content);
    }

    // ── Test 7: Counter — disabled button with reason when can_counter=false and reason set ─

    public function test_counter_renders_disabled_button_with_reason_when_blocked(): void
    {
        $offer = Offer::factory()->submitted()->create();

        // can_counter=false with non-empty reason → disabled button, reason visible, no form action
        $actions = $this->makeActions(['can_counter' => false]);
        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $counterUrl = route('offers.counter', $offer);

        $this->assertStringContainsString('Counter', $content, 'Counter must be visible when can_counter=false and reason is set.');
        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content, 'Counter must not have a form action when can_counter=false.');
        $this->assertStringContainsString('Not allowed to counter', $content, 'Reason must be visible when can_counter=false and reason is set.');

        $counterPos = strpos($content, '>Counter<');
        $this->assertNotFalse($counterPos);
        $snippet = substr($content, max(0, $counterPos - 200), 250);
        $this->assertStringContainsString(' disabled', $snippet, 'Counter must render as disabled when can_counter=false.');
    }

    // ── Test 8: Expire never appears in rendered HTML ─────────────────────────

    public function test_expire_never_appears_in_rendered_html(): void
    {
        $offer   = Offer::factory()->create(['status' => 'draft']);
        $actions = $this->makeActions(['can_expire' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('>Expire<', $content);
        $this->assertStringNotContainsString('>Expire ', $content);
        $this->assertStringNotContainsString('/expire"', $content);
    }

    // ── Test 9: Hidden action absent — no form for disabled submit ────────────

    public function test_fully_disabled_page_has_no_form_elements(): void
    {
        $offer   = Offer::factory()->create(['status' => 'accepted']);
        $actions = $this->makeActions();

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString('<form', $content);
        $this->assertStringNotContainsString('action=', $content);
    }

    // ── Test 10: No direct status mutation strings in Blade source ────────────

    public function test_blade_source_contains_no_direct_status_mutation_strings(): void
    {
        $viewPath    = base_path('resources/views/offers/show.blade.php');
        $viewContent = file_get_contents($viewPath);

        $this->assertStringNotContainsString("->status =",       $viewContent, "Blade must not directly assign ->status");
        $this->assertStringNotContainsString("->update(['status'", $viewContent, "Blade must not directly call ->update(['status']");
        $this->assertStringNotContainsString("Offer::where",     $viewContent, "Blade must not run Offer::where() queries");
        $this->assertStringNotContainsString("OfferEventLog",    $viewContent, "Blade must not reference OfferEventLog");
    }
}
