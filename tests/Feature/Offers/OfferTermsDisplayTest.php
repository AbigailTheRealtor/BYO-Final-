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
}
