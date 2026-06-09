<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferAvailableActionsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferCounterFormTest extends TestCase
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
            $mock->shouldReceive('forOffer')->andReturn($actions);
        });
    }

    private function makeOffer(array $attrs = []): Offer
    {
        return Offer::factory()->create(array_merge(['user_id' => $this->user->id], $attrs));
    }

    private function makeSubmittedOffer(array $attrs = []): Offer
    {
        return Offer::factory()->submitted()->create(array_merge(['user_id' => $this->user->id], $attrs));
    }

    private function makeSubmittedOfferByOtherUser(array $attrs = []): Offer
    {
        $other        = User::factory()->create();
        $offerAuction = OfferAuction::factory()->create(['user_id' => $this->user->id]);
        return Offer::factory()->submitted()->create(array_merge([
            'user_id'          => $other->id,
            'offer_auction_id' => $offerAuction->id,
        ], $attrs));
    }

    // ── Test 1: can_counter=true → form with action pointing to offers.counter ──

    public function test_counter_form_appears_with_correct_action_when_can_counter_is_true(): void
    {
        $offer   = $this->makeSubmittedOfferByOtherUser();
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
        $offer   = $this->makeSubmittedOfferByOtherUser();
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
        $offer   = $this->makeSubmittedOfferByOtherUser();
        $actions = $this->makeActions(['can_counter' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('type="date"', $content);
        $this->assertStringContainsString('name="expires_at"', $content);
    }

    // ── Test 4: can_counter=false with reason → disabled Counter button + reason, no form action ──

    public function test_no_counter_element_when_can_counter_is_false_with_reason(): void
    {
        $offer  = $this->makeSubmittedOfferByOtherUser();
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

        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content, 'No counter form action must be present when can_counter=false.');
        $this->assertStringContainsString('>Counter<', $content, 'Disabled Counter button must be rendered when can_counter=false with a reason.');
        $this->assertStringContainsString($reason, $content, 'Counter reason text must appear in the page when can_counter=false with a reason.');

        $counterPos = strpos($content, '>Counter<');
        $this->assertNotFalse($counterPos, 'Counter button must be findable in rendered HTML.');
        $snippet = substr($content, max(0, $counterPos - 200), 250);
        $this->assertStringContainsString(' disabled', $snippet, 'Counter button must carry the disabled attribute when can_counter=false.');
    }

    // ── Test 5: can_counter=false with empty reason → no Counter element at all ──

    public function test_no_counter_element_when_can_counter_is_false_and_reason_is_empty(): void
    {
        $offer   = $this->makeSubmittedOffer();
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
        $offer   = $this->makeOffer(['status' => 'draft']);
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

    // ── Test 8: can_counter=true → full form contains all required offer term fields ──

    public function test_counter_form_contains_all_required_offer_term_fields(): void
    {
        $offer   = $this->makeSubmittedOfferByOtherUser();
        $actions = $this->makeActions(['can_counter' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $requiredFields = [
            'offer_price',
            'earnest_deposit',
            'down_payment_value',
            'initial_deposit_amount',
            'additional_deposit_amount',
            'financing_type',
            'financing_contingency',
            'financing_contingency_days',
            'inspection_contingency',
            'inspection_contingency_days',
            'appraisal_contingency',
            'appraisal_contingency_days',
            'closing_date',
            'possession_date',
            'seller_contribution_requested',
            'home_warranty_requested',
            'included_personal_property',
            'excluded_items',
            'custom_terms',
            'expires_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $content,
                "Counter form must contain a field named '{$field}'.");
        }
    }

    // ── Test 9: counter form pre-populates all fields from counterDefaults ────

    public function test_counter_form_pre_populates_fields_from_counter_defaults(): void
    {
        $offer = $this->makeSubmittedOfferByOtherUser();

        $offer->saveMeta('offer_price',  '450000');
        $offer->saveMeta('closing_date', '2026-09-01');
        $offer->saveMeta('custom_terms', 'Test special conditions');
        $offer->saveMeta('expires_at',   '2026-08-01');

        $actions = $this->makeActions(['can_counter' => true]);
        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('450,000',                    $content, 'Offer price should be pre-populated in counter form.');
        $this->assertStringContainsString('2026-09-01',                 $content, 'Closing date should be pre-populated in counter form.');
        $this->assertStringContainsString('Test special conditions',    $content, 'Custom terms should be pre-populated in counter form.');
        $this->assertStringContainsString('2026-08-01',                 $content, 'Expires at should be pre-populated in counter form.');
    }

    // ── Test 10: Submit Counter Offer button appears in counter form ──────────

    public function test_counter_form_has_submit_counter_offer_button(): void
    {
        $offer   = $this->makeSubmittedOfferByOtherUser();
        $actions = $this->makeActions(['can_counter' => true]);

        $this->mockActionsService($offer, $actions);

        $response = $this->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('Submit Counter Offer', $content, 'Counter form must have a "Submit Counter Offer" submit button.');
    }
}
