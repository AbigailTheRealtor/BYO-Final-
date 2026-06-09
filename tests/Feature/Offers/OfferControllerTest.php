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
        $this->draftOffer->saveMeta('offer_price', '480000');

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

    // ── Test 10: store with non-existent offer_auction_id → 422 ─────────────────

    public function test_store_with_nonexistent_offer_auction_id_returns_422(): void
    {
        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.store'), [
                'offer_auction_id' => 999999,
                'role'             => 'seller',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_auction_id']);
    }

    // ── Test 11: counter POST — parent metas are carried forward to child offer ─

    public function test_counter_carries_forward_parent_metas_to_child_offer(): void
    {
        $this->mockActionsService($this->allowedActions());

        $this->submittedOffer->saveMeta('offer_price',       '480000');
        $this->submittedOffer->saveMeta('financing_type',    'Conventional');
        $this->submittedOffer->saveMeta('closing_date',      '2026-10-01');
        $this->submittedOffer->saveMeta('possession_date',   '2026-10-03');
        $this->submittedOffer->saveMeta('custom_terms',      'Original terms');
        $this->submittedOffer->saveMeta('earnest_deposit',   '10000');
        $this->submittedOffer->saveMeta('earnest_deposit_unit', '$');
        $this->submittedOffer->saveMeta('inspection_contingency_days', '10');
        $this->submittedOffer->saveMeta('seller_contribution_requested', 'Yes');
        $this->submittedOffer->saveMeta('home_warranty_requested', 'Yes');

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), []);

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        // Non-boolean fields: carried forward unchanged from parent.
        $this->assertEquals('480000',        $childMetas->get('offer_price'),                  'offer_price must be carried forward from parent.');
        $this->assertEquals('Conventional',  $childMetas->get('financing_type'),               'financing_type must be carried forward from parent.');
        $this->assertEquals('2026-10-01',    $childMetas->get('closing_date'),                 'closing_date must be carried forward from parent.');
        $this->assertEquals('2026-10-03',    $childMetas->get('possession_date'),              'possession_date must be carried forward from parent.');
        $this->assertEquals('Original terms',$childMetas->get('custom_terms'),                 'custom_terms must be carried forward from parent.');
        $this->assertEquals('10000',         $childMetas->get('earnest_deposit'),              'earnest_deposit must be carried forward from parent.');
        $this->assertEquals('$',             $childMetas->get('earnest_deposit_unit'),         'earnest_deposit_unit must be carried forward from parent.');
        $this->assertEquals('10',            $childMetas->get('inspection_contingency_days'),  'inspection_contingency_days must be carried forward from parent.');
        $this->assertEquals('Yes',           $childMetas->get('seller_contribution_requested'),'seller_contribution_requested must be carried forward from parent.');
        $this->assertEquals('Yes',           $childMetas->get('home_warranty_requested'),      'home_warranty_requested must be carried forward from parent.');
        // Boolean contingency fields use $request->boolean() — always driven by form state, not carry-forward.
        // See test_counter_boolean_contingency_toggle_true_to_false / _false_to_true for those assertions.
    }

    // ── Test 12: counter POST — submitted form fields override parent metas ────

    public function test_counter_submitted_fields_override_parent_metas(): void
    {
        $this->mockActionsService($this->allowedActions());

        $this->submittedOffer->saveMeta('offer_price',  '480000');
        $this->submittedOffer->saveMeta('closing_date', '2026-10-01');
        $this->submittedOffer->saveMeta('custom_terms', 'Original terms');

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'offer_price'  => '510000',
                'closing_date' => '2026-11-15',
                'custom_terms' => 'Updated counter terms',
            ]);

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        $this->assertEquals('510000',               $childMetas->get('offer_price'),  'Submitted offer_price must override parent value.');
        $this->assertEquals('2026-11-15',            $childMetas->get('closing_date'), 'Submitted closing_date must override parent value.');
        $this->assertEquals('Updated counter terms', $childMetas->get('custom_terms'), 'Submitted custom_terms must override parent value.');
    }

    // ── Test 13: counter POST — comma-formatted money fields have commas stripped ──

    public function test_counter_strips_commas_from_money_fields(): void
    {
        $this->mockActionsService($this->allowedActions());

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'offer_price'      => '525,000',
                'earnest_deposit'  => '10,500',
                'down_payment_value' => '105,000',
            ]);

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        $this->assertEquals('525000',  $childMetas->get('offer_price'),      'Commas must be stripped from offer_price before saving.');
        $this->assertEquals('10500',   $childMetas->get('earnest_deposit'),   'Commas must be stripped from earnest_deposit before saving.');
        $this->assertEquals('105000',  $childMetas->get('down_payment_value'),'Commas must be stripped from down_payment_value before saving.');
    }

    // ── Test 14: counter POST — all full-form SF sub-fields are validated & saved ─

    public function test_counter_validates_and_saves_seller_financing_sub_fields(): void
    {
        $this->mockActionsService($this->allowedActions());

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $response = $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'financing_type'              => 'Seller Financing',
                'seller_financing_rate'       => '6.5',
                'seller_financing_term'       => '30 years',
                'seller_financing_amortization' => 'Fully Amortizing',
                'seller_financing_balloon'    => 'No',
                'prepayment_penalty'          => 'No',
            ]);

        $response->assertOk();

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        $this->assertEquals('Seller Financing',   $childMetas->get('financing_type'),              'financing_type must be saved for SF offer.');
        $this->assertEquals('6.5',                $childMetas->get('seller_financing_rate'),        'seller_financing_rate must be saved.');
        $this->assertEquals('30 years',           $childMetas->get('seller_financing_term'),        'seller_financing_term must be saved.');
        $this->assertEquals('Fully Amortizing',   $childMetas->get('seller_financing_amortization'),'seller_financing_amortization must be saved.');
        $this->assertEquals('No',                 $childMetas->get('seller_financing_balloon'),     'seller_financing_balloon must be saved.');
        $this->assertEquals('No',                 $childMetas->get('prepayment_penalty'),           'prepayment_penalty must be saved.');
    }

    // ── Test 15: counter POST — boolean contingency true→false toggle is respected ─

    public function test_counter_boolean_contingency_toggle_true_to_false(): void
    {
        $this->mockActionsService($this->allowedActions());

        // Parent has all four contingencies set to true.
        $this->submittedOffer->saveMeta('financing_contingency',               '1');
        $this->submittedOffer->saveMeta('inspection_contingency',              '1');
        $this->submittedOffer->saveMeta('appraisal_contingency',               '1');
        $this->submittedOffer->saveMeta('sale_of_buyer_property_contingency',  '1');

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        // Counter submitted with no contingency keys present at all (simulates unchecked checkboxes).
        $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'offer_price' => '460000',
                // All four contingency checkboxes absent from request → should become 0.
            ]);

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        $this->assertEquals(0, (int) $childMetas->get('financing_contingency'),              'financing_contingency must be 0 when absent from counter request (unchecked).');
        $this->assertEquals(0, (int) $childMetas->get('inspection_contingency'),             'inspection_contingency must be 0 when absent from counter request (unchecked).');
        $this->assertEquals(0, (int) $childMetas->get('appraisal_contingency'),              'appraisal_contingency must be 0 when absent from counter request (unchecked).');
        $this->assertEquals(0, (int) $childMetas->get('sale_of_buyer_property_contingency'), 'sale_of_buyer_property_contingency must be 0 when absent from counter request (unchecked).');
    }

    // ── Test 16: counter POST — boolean contingency false→true toggle is respected ─

    public function test_counter_boolean_contingency_toggle_false_to_true(): void
    {
        $this->mockActionsService($this->allowedActions());

        $this->submittedOffer->saveMeta('financing_contingency',  '0');
        $this->submittedOffer->saveMeta('inspection_contingency', '0');

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'financing_contingency'  => 1,
                'inspection_contingency' => 1,
            ]);

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        $this->assertEquals(1, (int) $childMetas->get('financing_contingency'),  'financing_contingency must be 1 when explicitly sent as 1.');
        $this->assertEquals(1, (int) $childMetas->get('inspection_contingency'), 'inspection_contingency must be 1 when explicitly sent as 1.');
    }

    // ── Test 17: counter POST — intentional text field clear overwrites parent value ──

    public function test_counter_intentional_field_clear_overwrites_parent(): void
    {
        $this->mockActionsService($this->allowedActions());

        $this->submittedOffer->saveMeta('custom_terms',       'Original terms text');
        $this->submittedOffer->saveMeta('possession_notes',   'Possession at closing');

        $counterOffer = Offer::factory()->create([
            'parent_offer_id' => $this->submittedOffer->id,
            'status'          => 'submitted',
        ]);

        $facade = $this->createMock(OfferWorkflowFacade::class);
        $facade->method('counter')->willReturn($this->successResult(['counter_offer' => $counterOffer]));
        $this->app->instance(OfferWorkflowFacade::class, $facade);

        // Explicitly send the fields as null to simulate intentional clearing.
        $this->actingAsAllowedUser()
            ->postJson(route('offers.counter', $this->submittedOffer), [
                'offer_price'     => '500000',
                'custom_terms'    => null,
                'possession_notes' => null,
            ]);

        $counterOffer->load('metas');
        $childMetas = $counterOffer->metas->pluck('meta_value', 'meta_key');

        $this->assertNull($childMetas->get('custom_terms'),     'custom_terms must be null when explicitly cleared in counter request.');
        $this->assertNull($childMetas->get('possession_notes'), 'possession_notes must be null when explicitly cleared in counter request.');
    }

    // ── Test 18: static scan — no direct Offer.status mutation or OfferEventLog write ──

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
