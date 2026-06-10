<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferTermsDisplayTest extends TestCase
{
    use DatabaseTransactions;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeOfferWithAuction(string $offerType = 'sale', string $status = 'draft'): array
    {
        $owner   = User::factory()->create();
        $auction = OfferAuction::factory()->create();
        $auction->saveMeta('offer_type', $offerType);
        $auction->load('metas');

        $offer = Offer::factory()->create([
            'user_id'          => $owner->id,
            'offer_auction_id' => $auction->id,
            'status'           => $status,
        ]);

        return ['offer' => $offer, 'owner' => $owner, 'auction' => $auction];
    }

    private function allowPlayoffAccess(User $user): void
    {
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [$user->id]);
    }

    private function mockCanSubmit(bool $allowed = true): void
    {
        $reasons = ['submit' => $allowed ? '' : 'Not permitted.', 'counter' => '', 'accept' => '', 'reject' => '', 'withdraw' => '', 'expire' => '', 'view_timeline' => ''];
        $actions = [
            'can_submit' => $allowed, 'can_counter' => false, 'can_accept' => false,
            'can_reject' => false, 'can_withdraw' => false, 'can_expire' => false,
            'can_view_timeline' => true, 'reasons' => $reasons,
        ];
        $mock = $this->createMock(OfferAvailableActionsService::class);
        $mock->method('forOffer')->willReturn($actions);
        $this->app->instance(OfferAvailableActionsService::class, $mock);
    }

    private function mockFacadeSubmitAllowed(): void
    {
        $mock = $this->createMock(OfferWorkflowFacade::class);
        $mock->method('submit')->willReturn(['allowed' => true, 'reason' => null]);
        $this->app->instance(OfferWorkflowFacade::class, $mock);
    }

    private function baseSalePayload(): array
    {
        return [
            '_offer_terms_present'       => '1',
            'offer_price'                => '450000',
            'earnest_deposit'            => '5000',
            'earnest_deposit_unit'       => '$',
            'financing_type'             => 'Conventional',
            'down_payment_value'         => '20',
            'down_payment_unit'          => '%',
            'financing_contingency'      => '1',
            'financing_contingency_days' => '21',
            'inspection_contingency'     => '1',
            'inspection_contingency_days'=> '10',
            'appraisal_contingency'      => '0',
            'closing_date'               => now()->addDays(30)->toDateString(),
            'possession_date'            => now()->addDays(32)->toDateString(),
            'custom_terms'               => 'Seller to leave all appliances.',
            'expires_at'                 => now()->addDays(3)->toDateString(),
            'included_personal_property' => 'Refrigerator, washer, dryer',
            'excluded_items'             => 'Dining room chandelier',
            'seller_contribution_requested' => 'Yes',
            'seller_contribution_details'   => '3% toward closing costs',
            'home_warranty_requested'    => 'Yes',
            'home_warranty_details'      => 'One-year plan',
        ];
    }

    // ── Test 1: Submit with terms in request → show page renders all values ──

    public function test_submit_with_terms_saves_metas_and_show_page_renders_values(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);
        $this->mockCanSubmit(true);
        $this->mockFacadeSubmitAllowed();

        $payload = $this->baseSalePayload();

        $this->actingAs($owner)
            ->post(route('offers.submit', $offer), $payload)
            ->assertRedirect(route('offers.show', $offer));

        $offer->load('metas');
        $this->assertSame('450000', $offer->getMeta('offer_price'));
        $this->assertSame('Conventional', $offer->getMeta('financing_type'));
        $this->assertSame('Seller to leave all appliances.', $offer->getMeta('custom_terms'));
        $this->assertSame('Refrigerator, washer, dryer', $offer->getMeta('included_personal_property'));
        $this->assertSame('Dining room chandelier', $offer->getMeta('excluded_items'));
        $this->assertSame('Yes', $offer->getMeta('seller_contribution_requested'));
        $this->assertSame('3% toward closing costs', $offer->getMeta('seller_contribution_details'));
        $this->assertSame('Yes', $offer->getMeta('home_warranty_requested'));
    }

    // ── Test 2: Submit without saved terms or request terms → blocked ─────────

    public function test_submit_without_saved_terms_is_blocked_with_clear_error(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);
        $this->mockCanSubmit(true);

        $response = $this->actingAs($owner)
            ->post(route('offers.submit', $offer), []);

        // Offer must stay draft — primary contract: no submission without saved terms
        $response->assertRedirect(route('offers.show', $offer));
        $offer->refresh();
        $this->assertSame('draft', $offer->status);

        // Verify the error flash WAS set (even though it's already aged in the session store)
        $flashOld = $response->baseResponse->getSession()->get('_flash.old', []);
        $this->assertContains('error', $flashOld, 'Expected an error flash to be set on the redirect');
    }

    // ── Test 3: Offer metas not deleted or overwritten by submit action ────────

    public function test_offer_metas_not_deleted_by_submit_when_no_terms_in_request(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        // Pre-save terms first
        $offer->saveMeta('offer_price', '500000');
        $offer->saveMeta('financing_type', 'Cash');
        $offer->saveMeta('offer_type', 'sale');

        $this->mockCanSubmit(true);
        $this->mockFacadeSubmitAllowed();

        $this->actingAs($owner)
            ->post(route('offers.submit', $offer), [])
            ->assertRedirect(route('offers.show', $offer));

        $offer->load('metas');
        $this->assertSame('500000', $offer->getMeta('offer_price'));
        $this->assertSame('Cash', $offer->getMeta('financing_type'));
    }

    // ── Test 4: Seller Financing sub-fields render after submit ──────────────

    public function test_seller_financing_sub_fields_render_after_submit(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Seller Financing');
        $offer->saveMeta('sf_purchase_price', '400000');
        $offer->saveMeta('seller_financing_rate', '6.5');
        $offer->saveMeta('seller_financing_term', '30 years');
        $offer->saveMeta('seller_financing_balloon', 'Yes');
        $offer->saveMeta('seller_financing_balloon_amount', '50000');

        $response = $this->actingAs($owner)
            ->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Seller Financing');
        $response->assertSee('400,000');
        $response->assertSee('6.5%');
        $response->assertSee('30 years');
        $response->assertSee('50,000');
    }

    // ── Test 5: Contingency day fields render ─────────────────────────────────

    public function test_contingency_day_fields_render_in_read_only_view(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '350000');
        $offer->saveMeta('financing_contingency', '1');
        $offer->saveMeta('financing_contingency_days', '21');
        $offer->saveMeta('inspection_contingency', '1');
        $offer->saveMeta('inspection_contingency_days', '10');
        $offer->saveMeta('appraisal_contingency', '1');
        $offer->saveMeta('appraisal_contingency_days', '14');

        $response = $this->actingAs($owner)
            ->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('21 days');
        $response->assertSee('10 days');
        $response->assertSee('14 days');
    }

    // ── Test 6: Seller contribution, home warranty, included/excluded render ──

    public function test_purchase_terms_fields_render_in_read_only_view(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '475000');
        $offer->saveMeta('seller_contribution_requested', 'Yes');
        $offer->saveMeta('seller_contribution_details', '2% closing costs');
        $offer->saveMeta('home_warranty_requested', 'Yes');
        $offer->saveMeta('home_warranty_details', 'American Home Shield');
        $offer->saveMeta('included_personal_property', 'Washer and dryer');
        $offer->saveMeta('excluded_items', 'Living room TV');

        $response = $this->actingAs($owner)
            ->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('2% closing costs');
        $response->assertSee('American Home Shield');
        $response->assertSee('Washer and dryer');
        $response->assertSee('Living room TV');
    }

    // ── Test 7: Private notes key never appears in the response ───────────────

    public function test_notes_key_never_appears_in_read_only_view(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->saveMeta('notes', 'This is a private internal note that must stay hidden');

        $response = $this->actingAs($owner)
            ->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee('This is a private internal note that must stay hidden');
    }

    // ── Test 8: Save & Submit via form sends _offer_terms_present signal ──────

    public function test_save_and_submit_button_persists_terms_and_submits(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);
        $this->mockCanSubmit(true);
        $this->mockFacadeSubmitAllowed();

        $payload = array_merge($this->baseSalePayload(), [
            'financing_type' => 'FHA',
            'offer_price'    => '320000',
        ]);

        $response = $this->actingAs($owner)
            ->post(route('offers.submit', $offer), $payload);

        $response->assertRedirect(route('offers.show', $offer));

        // Primary contract: terms were persisted to offer_metas
        $offer->load('metas');
        $this->assertSame('320000', $offer->getMeta('offer_price'));
        $this->assertSame('FHA', $offer->getMeta('financing_type'));

        // Verify success flash WAS set (aged by the time assertSessionHas runs)
        $flashOld = $response->baseResponse->getSession()->get('_flash.old', []);
        $this->assertContains('success', $flashOld, 'Expected a success flash to be set on the redirect');
    }

    // ── Test 9: Metas are not overwritten by a plain submit (no terms) ────────

    public function test_plain_submit_does_not_overwrite_existing_metas(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);
        $this->mockCanSubmit(true);
        $this->mockFacadeSubmitAllowed();

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '999000');
        $offer->saveMeta('financing_type', 'Jumbo');

        $this->actingAs($owner)
            ->post(route('offers.submit', $offer), []);

        $offer->load('metas');
        $this->assertSame('999000', $offer->getMeta('offer_price'));
        $this->assertSame('Jumbo', $offer->getMeta('financing_type'));
    }

    // ── Test 10: Read-only display shows expires_at in human-readable format ──

    public function test_expires_at_renders_in_human_readable_format(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->saveMeta('expires_at', '2026-06-18');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('June 18, 2026');
        $response->assertDontSee('2026-06-18');
    }

    // ── Test 11: closing_date and possession_date render in F j, Y format ─────

    public function test_closing_and_possession_dates_render_in_human_readable_format(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->saveMeta('closing_date', '2026-07-15');
        $offer->saveMeta('possession_date', '2026-07-17');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('July 15, 2026');
        $response->assertSee('July 17, 2026');
        $response->assertDontSee('2026-07-15');
        $response->assertDontSee('2026-07-17');
    }

    // ── Test 12: Timeline columns show timestamps in human-readable format ─────

    public function test_timeline_timestamps_render_in_human_readable_format(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->created_at = \Carbon\Carbon::parse('2026-06-09 21:25:00');
        $offer->save();

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('June 9, 2026 at 9:25 PM', $content, 'Timeline Created At must render in human-readable format.');
        $this->assertStringNotContainsString('21:25:00', $content, 'Raw 24-hour time must not appear on the page.');
    }

    // ── Test 13: Offer Information card shows created_at in readable format ────

    public function test_offer_info_card_created_at_renders_in_human_readable_format(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->created_at = \Carbon\Carbon::parse('2026-06-09 21:25:00');
        $offer->save();

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('June 9, 2026 at 9:25 PM', $content, 'Offer Information card Created At must render in human-readable format.');
        $this->assertStringNotContainsString('2026-06-09 21:25:00', $content, 'Raw Y-m-d H:i:s format must not appear in the Offer Information card.');
    }

    // ── Test 14: Assumable Financing — all sub-fields render ─────────────────

    public function test_assumable_financing_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '350000');
        $offer->saveMeta('financing_type', 'Assumable');
        $offer->saveMeta('assumable_interest', 'Yes');
        $offer->saveMeta('assumable_max_interest_rate', '4.75');
        $offer->saveMeta('assumable_max_monthly_payment', '2200');
        $offer->saveMeta('assumable_bridge_gap_cash', '45000');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Assumable');
        $response->assertSee('Yes');
        $response->assertSee('4.75%');
        $response->assertSee('2,200');
        $response->assertSee('45,000');
    }

    // ── Test 15: Assumable Financing — "No" interest answer renders ───────────

    public function test_assumable_financing_no_interest_renders(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->saveMeta('financing_type', 'Assumable');
        $offer->saveMeta('assumable_interest', 'No');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Assumable');
        $response->assertSee('Interested in Assumable Financing?');
    }

    // ── Test 16: Cryptocurrency — all sub-fields render ──────────────────────

    public function test_cryptocurrency_financing_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '500000');
        $offer->saveMeta('financing_type', 'Cryptocurrency');
        $offer->saveMeta('cryptocurrency_type', 'Bitcoin');
        $offer->saveMeta('crypto_percentage', '50');
        $offer->saveMeta('crypto_exchange_method', 'Spot price at closing via Coinbase');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Cryptocurrency');
        $response->assertSee('Bitcoin');
        $response->assertSee('50%');
        $response->assertSee('Spot price at closing via Coinbase');
    }

    // ── Test 17: Exchange/Trade — all sub-fields render (incl. Other item & liens details) ──

    public function test_exchange_trade_financing_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '400000');
        $offer->saveMeta('financing_type', 'Exchange/Trade');
        $offer->saveMeta('exchange_item', 'Other');
        $offer->saveMeta('other_exchange_item', 'Private Jet');
        $offer->saveMeta('exchange_item_value', '75000');
        $offer->saveMeta('exchange_item_condition', 'Excellent');
        $offer->saveMeta('additional_cash', '25000');
        $offer->saveMeta('value_determination', 'Licensed appraisal');
        $offer->saveMeta('exchange_transfer_method', 'Bill of sale at closing');
        $offer->saveMeta('exchange_liens', 'Yes');
        $offer->saveMeta('exchange_liens_details', 'Auto loan balance of $10,000');
        $offer->saveMeta('exchange_inspection_rights', 'Yes');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Exchange/Trade');
        $response->assertSee('Private Jet');
        $response->assertSee('75,000');
        $response->assertSee('Excellent');
        $response->assertSee('25,000');
        $response->assertSee('Licensed appraisal');
        $response->assertSee('Bill of sale at closing');
        $response->assertSee('Auto loan balance of $10,000');
        $response->assertSee('Yes');
    }

    // ── Test 18: Exchange/Trade — standard item (non-Other) renders ───────────

    public function test_exchange_trade_standard_item_renders(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->saveMeta('financing_type', 'Exchange/Trade');
        $offer->saveMeta('exchange_item', 'Vehicle');
        $offer->saveMeta('exchange_item_value', '30000');
        $offer->saveMeta('exchange_item_condition', 'Good');
        $offer->saveMeta('exchange_liens', 'No');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Vehicle');
        $response->assertSee('30,000');
        $response->assertSee('Good');
    }

    // ── Test 19: Seller Financing — all sub-fields render ────────────────────

    public function test_seller_financing_complete_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Seller Financing');
        $offer->saveMeta('sf_purchase_price', '450000');
        $offer->saveMeta('sf_down_payment_amount', '90000');
        $offer->saveMeta('sf_down_payment_type', '$');
        $offer->saveMeta('seller_financing_amount', '360000');
        $offer->saveMeta('seller_financing_amount_type', '$');
        $offer->saveMeta('seller_financing_rate', '6.5');
        $offer->saveMeta('seller_financing_term', '30 years');
        $offer->saveMeta('seller_financing_amortization', 'Fully Amortizing');
        $offer->saveMeta('seller_financing_payment_frequency', 'Monthly');
        $offer->saveMeta('seller_financing_balloon', 'Yes');
        $offer->saveMeta('seller_financing_balloon_amount', '50000');
        $offer->saveMeta('seller_financing_balloon_date', '5 Years');
        $offer->saveMeta('prepayment_penalty', 'Yes');
        $offer->saveMeta('prepayment_penalty_amount', '5000');
        $offer->saveMeta('seller_late_fee_amount', '$100 after 10 days');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Seller Financing');
        $response->assertSee('450,000');
        $response->assertSee('90,000');
        $response->assertSee('360,000');
        $response->assertSee('6.5%');
        $response->assertSee('30 years');
        $response->assertSee('Fully Amortizing');
        $response->assertSee('Monthly');
        $response->assertSee('50,000');
        $response->assertSee('5 Years');
        $response->assertSee('5,000');
        $response->assertSee('$100 after 10 days');
    }

    // ── Test 20: Seller Financing — amortization Other and payment frequency Other render ──

    public function test_seller_financing_amortization_other_and_payment_frequency_other_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Seller Financing');
        $offer->saveMeta('sf_purchase_price', '300000');
        $offer->saveMeta('seller_financing_amortization', 'Other');
        $offer->saveMeta('seller_financing_amortization_other', 'Graduated Payments');
        $offer->saveMeta('seller_financing_payment_frequency', 'Other');
        $offer->saveMeta('seller_financing_payment_frequency_other', 'Semi-Annual');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Graduated Payments');
        $response->assertSee('Semi-Annual');
    }

    // ── Test 21: Seller Financing — percentage-based amount and down payment render ──

    public function test_seller_financing_percentage_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Seller Financing');
        $offer->saveMeta('sf_purchase_price', '500000');
        $offer->saveMeta('sf_down_payment_amount', '20');
        $offer->saveMeta('sf_down_payment_type', '%');
        $offer->saveMeta('seller_financing_amount', '80');
        $offer->saveMeta('seller_financing_amount_type', '%');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Seller Financing');
        $response->assertSee('20%');
        $response->assertSee('80%');
    }

    // ── Test 22: Lease Option — all sub-fields render ─────────────────────────

    public function test_lease_option_financing_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Lease Option');
        $offer->saveMeta('lease_option_price', '500000');
        $offer->saveMeta('lease_option_payment', '2500');
        $offer->saveMeta('lease_option_duration', '24');
        $offer->saveMeta('has_option_fee', 'Yes');
        $offer->saveMeta('option_fee_amount', '15000');
        $offer->saveMeta('lease_option_fee_credit', 'Partial');
        $offer->saveMeta('lease_option_fee_credit_pct', '50');
        $offer->saveMeta('lease_option_maintenance', 'Tenant-Buyer');
        $offer->saveMeta('lease_option_conditions', 'Option exercisable after 12 months');
        $offer->saveMeta('lease_option_terms', 'Buyer may inspect during lease term');
        $offer->saveMeta('lease_option_extension_terms', 'May extend 6 months with $5,000 fee');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Lease Option');
        $response->assertSee('500,000');
        $response->assertSee('2,500');
        $response->assertSee('24 months');
        $response->assertSee('15,000');
        $response->assertSee('Partial');
        $response->assertSee('50%');
        $response->assertSee('Tenant-Buyer');
        $response->assertSee('Option exercisable after 12 months');
        $response->assertSee('Buyer may inspect during lease term');
        $response->assertSee('May extend 6 months with $5,000 fee');
    }

    // ── Test 23: Lease Option — option fee "No" and fee credit "Yes" render ───

    public function test_lease_option_no_fee_and_yes_credit_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Lease Option');
        $offer->saveMeta('lease_option_price', '300000');
        $offer->saveMeta('has_option_fee', 'No');
        $offer->saveMeta('lease_option_fee_credit', 'Yes');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Lease Option');
        $response->assertSee('Option Fee');
        $response->assertSee('Fee Credit Toward Price');
    }

    // ── Test 24: Lease Purchase — all sub-fields render ───────────────────────

    public function test_lease_purchase_financing_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Lease Purchase');
        $offer->saveMeta('lease_purchase_price', '800000');
        $offer->saveMeta('lease_purchase_payment', '5000');
        $offer->saveMeta('lease_purchase_duration', '12');
        $offer->saveMeta('lease_purchase_rent_credit', 'Yes');
        $offer->saveMeta('lease_purchase_rent_credit_amount', '500');
        $offer->saveMeta('lease_purchase_deposit', '10000');
        $offer->saveMeta('lease_purchase_maintenance', 'Shared');
        $offer->saveMeta('lease_purchase_conditions', 'Buyer must secure financing by lease end');
        $offer->saveMeta('lease_purchase_terms', 'Right of first refusal if seller decides to sell');
        $offer->saveMeta('lease_purchase_extension_terms', 'Lease may be extended 6 months');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Lease Purchase');
        $response->assertSee('800,000');
        $response->assertSee('5,000');
        $response->assertSee('12 months');
        $response->assertSee('500');
        $response->assertSee('10,000');
        $response->assertSee('Shared');
        $response->assertSee('Buyer must secure financing by lease end');
        $response->assertSee('Right of first refusal if seller decides to sell');
        $response->assertSee('Lease may be extended 6 months');
    }

    // ── Test 25: Lease Purchase — Partial rent credit renders ─────────────────

    public function test_lease_purchase_partial_rent_credit_renders(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Lease Purchase');
        $offer->saveMeta('lease_purchase_price', '400000');
        $offer->saveMeta('lease_purchase_rent_credit', 'Partial');
        $offer->saveMeta('lease_purchase_rent_credit_amount', '250');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Lease Purchase');
        $response->assertSee('Partial');
        $response->assertSee('250');
    }

    // ── Test 26: Non-Fungible Token (NFT) — all sub-fields render ─────────────

    public function test_nft_financing_all_fields_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '600000');
        $offer->saveMeta('financing_type', 'Non-Fungible Token (NFT)');
        $offer->saveMeta('nft_description', 'Tokenized real estate on Ethereum');
        $offer->saveMeta('nft_percentage', '40');
        $offer->saveMeta('cash_percentage_nft', '60');
        $offer->saveMeta('nft_valuation_method', 'Floor price on OpenSea');
        $offer->saveMeta('nft_transfer_method', 'MetaMask to escrow smart contract');
        $offer->saveMeta('nft_gas_fees', 'Buyer');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Non-Fungible Token (NFT)');
        $response->assertSee('Tokenized real estate on Ethereum');
        $response->assertSee('40%');
        $response->assertSee('60%');
        $response->assertSee('Floor price on OpenSea');
        $response->assertSee('MetaMask to escrow smart contract');
        $response->assertSee('Buyer');
    }

    // ── Test 27: NFT — zero-value percentages (0%) render ────────────────────

    public function test_nft_zero_percentage_renders(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '500000');
        $offer->saveMeta('financing_type', 'Non-Fungible Token (NFT)');
        $offer->saveMeta('nft_description', 'Digital Art NFT');
        $offer->saveMeta('nft_percentage', '0');
        $offer->saveMeta('cash_percentage_nft', '100');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Non-Fungible Token (NFT)');
        $response->assertSee('0%');
        $response->assertSee('100%');
    }

    // ── Test 28: Other Financing — other_financing_details renders ─────────────

    public function test_other_financing_details_render(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '250000');
        $offer->saveMeta('financing_type', 'Other');
        $offer->saveMeta('other_financing_details', 'Gold bullion and private investment agreement');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Other');
        $response->assertSee('Gold bullion and private investment agreement');
    }

    // ── Test 29: Assumable — zero interest rate (0%) renders ─────────────────

    public function test_assumable_zero_interest_rate_renders(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '350000');
        $offer->saveMeta('financing_type', 'Assumable');
        $offer->saveMeta('assumable_interest', 'Yes');
        $offer->saveMeta('assumable_max_interest_rate', '0');
        $offer->saveMeta('assumable_max_monthly_payment', '1500');
        $offer->saveMeta('assumable_bridge_gap_cash', '20000');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Assumable');
        $response->assertSee('0%');
        $response->assertSee('1,500');
        $response->assertSee('20,000');
    }

    // ── Test 30: Seller Financing — balloon payment "No" renders (populated fields must not be hidden) ──

    public function test_seller_financing_balloon_no_renders_balloon_row(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('financing_type', 'Seller Financing');
        $offer->saveMeta('sf_purchase_price', '300000');
        $offer->saveMeta('seller_financing_balloon', 'No');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Seller Financing');
        // "No" is a populated value and must render — no saved field should be silently hidden
        $response->assertSee('Balloon Payment');
        $response->assertSee('No');
    }

    // ── Test 31: Crypto — zero percent renders correctly ─────────────────────

    public function test_crypto_zero_percentage_renders(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '400000');
        $offer->saveMeta('financing_type', 'Cryptocurrency');
        $offer->saveMeta('cryptocurrency_type', 'Ethereum');
        $offer->saveMeta('crypto_percentage', '0');
        $offer->saveMeta('crypto_exchange_method', 'TBD');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Ethereum');
        $response->assertSee('0%');
        $response->assertSee('TBD');
    }

    // ── Test 32: expires_at label is "Response Requested By", not "Offer Expires At" ──

    public function test_expires_at_label_is_response_requested_by(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');
        $this->allowPlayoffAccess($owner);

        $offer->saveMeta('offer_type', 'sale');
        $offer->saveMeta('offer_price', '300000');
        $offer->saveMeta('expires_at', '2026-09-01');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Response Requested By');
        $response->assertDontSee('Offer Expires At');
        $response->assertSee('September 1, 2026');
    }
}
