<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferAvailableActionsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferCounterFormTest extends TestCase
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

    // ── Test 1: can_counter=true → form with action pointing to offers.counter ──

    public function test_counter_form_appears_with_correct_action_when_can_counter_is_true(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_counter' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $counterUrl = route('offers.counter', $offer);

        $this->assertStringContainsString('<form', $content);
        $this->assertStringContainsString('action="' . $counterUrl . '"', $content);
        $this->assertStringContainsString('Counter', $content);
    }

    // ── Test 2: Counter form includes a CSRF _token hidden input ─────────────

    public function test_counter_form_includes_csrf_token(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_counter' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('_token', $content);
    }

    // ── Test 3: Counter form includes <input type="date" name="expires_at"> ──

    public function test_counter_form_includes_expires_at_date_input(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions(['can_counter' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('type="date"', $content);
        $this->assertStringContainsString('name="expires_at"', $content);
    }

    // ── Test 4: can_counter=false with non-empty reason → disabled button with reason ──

    public function test_disabled_counter_button_with_reason_when_can_counter_is_false_and_reason_set(): void
    {
        $offer  = Offer::factory()->submitted()->create();
        $reason = 'Not allowed to counter';
        $actions = $this->makeActions([
            'can_counter' => false,
            'reasons'     => ['counter' => $reason],
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $counterUrl = route('offers.counter', $offer);

        $this->assertStringContainsString('Counter', $content, 'Counter button must be visible.');
        $this->assertStringContainsString('disabled', $content, 'Counter button must be disabled.');
        $this->assertStringContainsString('title="' . $reason . '"', $content, 'Reason must appear in title attribute.');
        $this->assertStringContainsString($reason, $content, 'Reason must appear as visible text.');
        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content, 'No form action must be present.');
    }

    // ── Test 5: can_counter=false with empty reason → no Counter element at all ──

    public function test_no_counter_element_when_can_counter_is_false_and_reason_is_empty(): void
    {
        $offer   = Offer::factory()->submitted()->create();
        $actions = $this->makeActions([
            'can_counter' => false,
            'reasons'     => ['counter' => ''],
        ]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content    = $response->getContent();
        $counterUrl = route('offers.counter', $offer);

        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content, 'No form action must be present.');
        $this->assertStringNotContainsString('>Counter<', $content, 'No Counter element must be rendered at all.');
    }

    // ── Test 6: Expire is absent from rendered HTML regardless of can_expire ──

    public function test_expire_is_absent_from_rendered_html_regardless_of_can_expire(): void
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

    // ── Test 7: No direct status mutation strings in the Blade source file ────

    public function test_blade_source_contains_no_direct_status_mutation_strings(): void
    {
        $viewPath    = base_path('resources/views/offers/show.blade.php');
        $viewContent = file_get_contents($viewPath);

        $this->assertStringNotContainsString('->status =',          $viewContent, 'Blade must not directly assign ->status.');
        $this->assertStringNotContainsString("->update(['status'",  $viewContent, 'Blade must not directly call ->update([\'status\']).');
        $this->assertStringNotContainsString('Offer::where',        $viewContent, 'Blade must not run Offer::where() queries.');
        $this->assertStringNotContainsString('OfferEventLog',       $viewContent, 'Blade must not reference OfferEventLog.');
    }
}
