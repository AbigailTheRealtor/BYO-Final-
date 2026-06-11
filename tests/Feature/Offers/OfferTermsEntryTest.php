<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferTermsEntryTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeOfferWithAuction(string $offerType, string $offerStatus, ?int $ownerId = null): array
    {
        $owner   = $ownerId ? User::find($ownerId) : User::factory()->create();
        $auction = OfferAuction::factory()->create();
        $auction->saveMeta('offer_type', $offerType);
        $auction->load('metas');

        $offer = Offer::factory()->create([
            'user_id'          => $owner->id,
            'offer_auction_id' => $auction->id,
            'status'           => $offerStatus,
        ]);

        return ['offer' => $offer, 'owner' => $owner, 'auction' => $auction];
    }

    private function allowPlayoffAccess(User $user): void
    {
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [$user->id]);
    }

    // ── Test 1: Draft sale offer shows editable Offer Terms form ──────────────

    public function test_draft_sale_offer_shows_editable_terms_form(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('action="' . route('offers.terms', $offer) . '"', false);
        $response->assertSee('name="offer_price"', false);
        $response->assertSee('Save Offer Terms');
    }

    // ── Test 2: Saving sale terms persists to offer_metas ────────────────────

    public function test_saving_sale_terms_persists_to_offer_metas(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'offer_price'                => '450000',
            'earnest_deposit'            => '5000',
            'financing_type'             => 'Conventional',
            'down_payment_percent'       => '20',
            'financing_contingency'      => '1',
            'financing_contingency_days' => '21',
            'inspection_contingency'     => '1',
            'inspection_contingency_days'=> '10',
            'appraisal_contingency'      => '0',
            'closing_date'               => now()->addDays(30)->toDateString(),
            'possession_date'            => now()->addDays(32)->toDateString(),
            'custom_terms'               => 'Seller to leave appliances.',
            'notes'                      => 'Private note.',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);

        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'offer_price',
            'meta_value' => '450000',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'earnest_deposit',
            'meta_value' => '5000',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'financing_type',
            'meta_value' => 'Conventional',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'closing_date',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'custom_terms',
            'meta_value' => 'Seller to leave appliances.',
        ]);
    }

    // ── Test 3: Reloading page repopulates saved sale fields ──────────────────

    public function test_reloading_show_page_repopulates_saved_sale_terms(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'offer_price'    => '450000',
            'financing_type' => 'conventional',
            'custom_terms'   => 'Seller to leave appliances.',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('450,000');
        $response->assertSee('conventional');
        $response->assertSee('Seller to leave appliances.');
    }

    // ── Test 4: Draft rental offer shows rental fields (not sale fields) ──────

    public function test_draft_rental_offer_shows_rental_fields(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('rental', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('name="monthly_rent"', false);
        $response->assertSee('name="security_deposit"', false);
        $response->assertDontSee('name="offer_price"', false);
        $response->assertSee('Rental Offer Terms', false);
        $response->assertSee('Additional Terms &amp; Requests', false);
        $response->assertDontSee('name="parking_terms"', false);
        $response->assertDontSee('name="utilities_terms"', false);
        $response->assertDontSee('name="maintenance_responsibility"', false);
        $response->assertDontSee('name="move_in_funds"', false);
        $response->assertDontSee('name="message_to_landlord"', false);
        $response->assertDontSee('name="custom_terms"', false);
    }

    // ── Test 4b: Draft lease offer shows lease-specific fields ───────────────

    public function test_draft_lease_offer_shows_lease_fields(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('lease', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('name="monthly_rent"', false);
        $response->assertSee('name="security_deposit"', false);
        $response->assertSee('name="lease_term_months"', false);
        $response->assertDontSee('name="offer_price"', false);
        $response->assertSee('Rental Offer Terms', false);
        $response->assertDontSee('name="parking_terms"', false);
        $response->assertDontSee('name="utilities_terms"', false);
        $response->assertDontSee('name="maintenance_responsibility"', false);
        $response->assertDontSee('name="move_in_funds"', false);
        $response->assertDontSee('name="message_to_landlord"', false);
        $response->assertDontSee('name="custom_terms"', false);
    }

    // ── Test 5: Non-draft offer renders terms read-only, no form, no save button

    public function test_submitted_offer_renders_terms_read_only(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');

        $offer->saveMeta('offer_price', '375000');
        $offer->saveMeta('custom_terms', 'As-is sale.');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee('action="' . route('offers.terms', $offer) . '"', false);
        $response->assertDontSee('Save Offer Terms');
        $response->assertSee('375,000');
    }

    // ── Test 6: Non-owner cannot save terms and gets 403 ─────────────────────

    public function test_non_owner_cannot_save_terms(): void
    {
        ['offer' => $offer] = $this->makeOfferWithAuction('sale', 'draft');

        $stranger = User::factory()->create();
        $this->allowPlayoffAccess($stranger);

        $response = $this->actingAs($stranger)->post(route('offers.terms', $offer), [
            'offer_price' => '300000',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'offer_price']);
    }

    // ── Test 7: Non-draft offer cannot save terms and gets 422 ────────────────

    public function test_non_draft_offer_cannot_save_terms(): void
    {
        $this->allowPlayoffAccess($this->user);

        ['offer' => $offer] = $this->makeOfferWithAuction('sale', 'submitted', $this->user->id);

        $response = $this->actingAs($this->user)->post(route('offers.terms', $offer), [
            'offer_price'    => '300000',
            'financing_type' => 'conventional',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'offer_price']);
    }

    // ── Test 8: Validation errors preserve old input ──────────────────────────

    public function test_validation_errors_preserve_old_input(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $invalidPayload = [
            'financing_type' => 'invalid_value',
            'offer_price'    => 'not-a-number',
        ];

        $response = $this->actingAs($owner)
            ->from(route('offers.show', $offer))
            ->post(route('offers.terms', $offer), $invalidPayload);

        $response->assertRedirect();

        $followedResponse = $this->actingAs($owner)->get(route('offers.show', $offer));

        $followedResponse->assertStatus(200);
        $followedResponse->assertSee('not-a-number');
    }

    // ── Test 9: All expanded financing type options render in form ────────────

    public function test_expanded_financing_options_render_in_sale_form(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('value="Assumable"', false);
        $response->assertSee('value="Jumbo"', false);
        $response->assertSee('value="No-Doc"', false);
        $response->assertSee('value="Non-QM"', false);
        $response->assertSee('value="USDA"', false);
        $response->assertSee('value="Cryptocurrency"', false);
        $response->assertSee('value="Exchange/Trade"', false);
        $response->assertSee('value="Lease Option"', false);
        $response->assertSee('value="Lease Purchase"', false);
        $response->assertSee('value="Non-Fungible Token (NFT)"', false);
        $response->assertSee('value="Seller Financing"', false);
    }

    // ── Test 10: New sale term fields render in the form ──────────────────────

    public function test_new_sale_term_fields_render_in_form(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        // Conditional financing sub-fields (Assumable uses structured sub-fields, not legacy assumable_terms textarea)
        $response->assertSee('name="assumable_interest"', false);
        $response->assertSee('name="assumable_max_interest_rate"', false);
        $response->assertSee('name="cryptocurrency_type"', false);
        $response->assertSee('name="exchange_item"', false);
        $response->assertSee('name="seller_financing_amount"', false);
        // Contingencies section — sale_of_buyer_property_contingency now a toggle + days
        $response->assertSee('name="sale_of_buyer_property_contingency"', false);
        $response->assertSee('name="sale_of_buyer_property_contingency_days"', false);
        // Purchase terms
        $response->assertSee('name="initial_deposit_amount"', false);
        $response->assertSee('name="additional_deposit_amount"', false);
        $response->assertSee('name="possession_notes"', false);
        $response->assertSee('name="seller_contribution_requested"', false);
        $response->assertSee('name="included_personal_property"', false);
        $response->assertSee('name="excluded_items"', false);
        $response->assertSee('name="home_warranty_requested"', false);
        // Removed fields must not appear
        $response->assertDontSee('name="preferred_inspection_period"', false);
        $response->assertDontSee('name="possession_preference"', false);
        $response->assertDontSee('name="possession_details"', false);
        $response->assertDontSee('name="escrow_agent_preference"', false);
        $response->assertDontSee('name="appraisal_contingency_preference"', false);
        $response->assertDontSee('name="financing_contingency_preference"', false);
        $response->assertDontSee('name="hoa_condo_association_terms"', false);
        $response->assertDontSee('name="additional_seller_sale_terms"', false);
    }

    // ── Test 11: Saving all new fields persists them to offer_metas ───────────

    public function test_saving_all_new_sale_fields_persists_to_offer_metas(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'                          => 'Assumable',
            // Assumable structured sub-fields (replaces legacy assumable_terms textarea)
            'assumable_interest'                      => 'Yes',
            'assumable_max_interest_rate'             => '3.5',
            'assumable_max_monthly_payment'           => '1500',
            'assumable_bridge_gap_cash'               => '5000',
            'initial_deposit_amount'                  => '5000',
            'initial_deposit_timeframe'               => 'Within 3 Days',
            'additional_deposit_amount'               => '10000',
            'additional_deposit_timeframe'            => 'Within 10 Days',
            'sale_of_buyer_property_contingency'      => '1',
            'sale_of_buyer_property_contingency_days' => '14',
            'possession_notes'                        => 'Possession at closing preferred.',
            'seller_contribution_requested'           => 'Yes',
            'seller_contribution_details'             => '$5,000 toward closing costs',
            'included_personal_property'              => 'Refrigerator, Washer/Dryer',
            'excluded_items'                          => 'Antique chandelier',
            'home_warranty_requested'                 => 'Yes',
            'home_warranty_details'                   => '$500 one-year warranty',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_type',                          'meta_value' => 'Assumable']);
        // Assumable structured sub-fields (legacy assumable_terms/assumable_loan_type/outstanding_balance replaced)
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'assumable_interest',                      'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'assumable_max_interest_rate',             'meta_value' => '3.5']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'assumable_max_monthly_payment',           'meta_value' => '1500']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'assumable_bridge_gap_cash',               'meta_value' => '5000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'initial_deposit_amount',                  'meta_value' => '5000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'initial_deposit_timeframe',               'meta_value' => 'Within 3 Days']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_deposit_amount',               'meta_value' => '10000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sale_of_buyer_property_contingency',      'meta_value' => '1']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sale_of_buyer_property_contingency_days', 'meta_value' => '14']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'possession_notes',                        'meta_value' => 'Possession at closing preferred.']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_contribution_requested',           'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_contribution_details',             'meta_value' => '$5,000 toward closing costs']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'included_personal_property',              'meta_value' => 'Refrigerator, Washer/Dryer']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'excluded_items',                          'meta_value' => 'Antique chandelier']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'home_warranty_requested',                 'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'home_warranty_details',                   'meta_value' => '$500 one-year warranty']);
        // Removed fields must never be written to offer_metas
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'preferred_inspection_period']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'possession_preference']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'possession_details']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'escrow_agent_preference']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'appraisal_contingency_preference']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_contingency_preference']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'hoa_condo_association_terms']);
        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_seller_sale_terms']);
    }

    // ── Test 12: Saved new values repopulate form on reload ───────────────────

    public function test_new_sale_fields_repopulate_on_reload(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'financing_type'                          => 'Cryptocurrency',
            'cryptocurrency_type'                     => 'Bitcoin',
            'crypto_percentage'                       => '50',
            'included_personal_property'              => 'Pool table, Wine fridge',
            'sale_of_buyer_property_contingency'      => '1',
            'sale_of_buyer_property_contingency_days' => '21',
        ]);

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('value="Cryptocurrency"', false);
        $response->assertSee('Bitcoin');
        $response->assertSee('50');
        $response->assertSee('Pool table, Wine fridge');
        $response->assertSee('21');
    }

    // ── Test 13: Read-only view displays all new sale fields ──────────────────

    public function test_read_only_view_displays_new_sale_fields(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');

        $offer->saveMeta('financing_type',                          'Seller Financing');
        $offer->saveMeta('seller_financing_amount',                 '350000');
        $offer->saveMeta('seller_financing_rate',                   '6.5');
        $offer->saveMeta('seller_financing_term',                   '30 years');
        $offer->saveMeta('initial_deposit_amount',                  '7500');
        $offer->saveMeta('initial_deposit_timeframe',               'Within 5 Days');
        $offer->saveMeta('sale_of_buyer_property_contingency',      '1');
        $offer->saveMeta('sale_of_buyer_property_contingency_days', '10');
        $offer->saveMeta('possession_notes',                        'Seller requests 30-day rent back');
        $offer->saveMeta('seller_contribution_requested',           'Yes');
        $offer->saveMeta('seller_contribution_details',             '$3,000 toward closing costs');
        $offer->saveMeta('included_personal_property',              'Stainless fridge');
        $offer->saveMeta('excluded_items',                          'Garden shed');
        $offer->saveMeta('home_warranty_requested',                 'Yes');
        $offer->saveMeta('home_warranty_details',                   '$600 AHS warranty');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee('Save Offer Terms');
        $response->assertSee('Seller Financing');
        $response->assertSee('350,000');
        $response->assertSee('6.5%');
        $response->assertSee('30 years');
        $response->assertSee('7,500');
        $response->assertSee('Within 5 Days');
        $response->assertSee('Yes');
        $response->assertSee('10 days');
        $response->assertSee('Seller requests 30-day rent back');
        $response->assertSee('$3,000 toward closing costs');
        $response->assertSee('Stainless fridge');
        $response->assertSee('Garden shed');
        $response->assertSee('$600 AHS warranty');
    }

    // ── Test 14: Cryptocurrency fields save and repopulate ────────────────────

    public function test_cryptocurrency_sub_fields_save_and_repopulate(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'        => 'Cryptocurrency',
            'cryptocurrency_type'   => 'Ethereum',
            'crypto_percentage'     => '25',
            'crypto_exchange_method'=> 'Spot price via Coinbase at closing',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'cryptocurrency_type',   'meta_value' => 'Ethereum']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'crypto_percentage',     'meta_value' => '25']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'crypto_exchange_method','meta_value' => 'Spot price via Coinbase at closing']);
    }

    // ── Test 15: Exchange/Trade fields save correctly ─────────────────────────

    public function test_exchange_trade_sub_fields_save_correctly(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'      => 'Exchange/Trade',
            'exchange_item'       => 'Another Home',
            'exchange_item_value' => '350000',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_item',       'meta_value' => 'Another Home']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_item_value', 'meta_value' => '350000']);
    }

    // ── Test 16: Deposit timeframe "Other" fallback saves and displays ────────

    public function test_deposit_timeframe_other_fallback_saves_and_displays(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'initial_deposit_amount'            => '8000',
            'initial_deposit_timeframe'         => 'Other',
            'initial_deposit_timeframe_other'   => 'Within 21 Days',
            'additional_deposit_amount'         => '12000',
            'additional_deposit_timeframe'      => 'Other',
            'additional_deposit_timeframe_other'=> 'Within 28 Days',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'initial_deposit_timeframe',         'meta_value' => 'Other']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'initial_deposit_timeframe_other',   'meta_value' => 'Within 21 Days']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_deposit_timeframe',      'meta_value' => 'Other']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_deposit_timeframe_other','meta_value' => 'Within 28 Days']);

        $reloadResponse = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reloadResponse->assertStatus(200);
        $reloadResponse->assertSee('Within 21 Days');
        $reloadResponse->assertSee('Within 28 Days');
    }

    // ── Test 17: Deposit timeframe "Other" fallback appears in read-only view ─

    public function test_deposit_timeframe_other_fallback_renders_in_read_only(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');

        $offer->saveMeta('initial_deposit_amount',             '6000');
        $offer->saveMeta('initial_deposit_timeframe',          'Other');
        $offer->saveMeta('initial_deposit_timeframe_other',    'Within 21 Days');
        $offer->saveMeta('additional_deposit_amount',          '11000');
        $offer->saveMeta('additional_deposit_timeframe',       'Other');
        $offer->saveMeta('additional_deposit_timeframe_other', 'Within 28 Days');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee('Save Offer Terms');
        $response->assertSee('Within 21 Days');
        $response->assertSee('Within 28 Days');
        $response->assertDontSee('Other — Other');
    }

    // ── Test 17: Sale-of-buyer-property toggle ON saves 1 ────────────────────

    public function test_sale_of_buyer_property_contingency_toggle_on_saves_one(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'sale_of_buyer_property_contingency'      => '1',
            'sale_of_buyer_property_contingency_days' => '30',
        ]);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sale_of_buyer_property_contingency',      'meta_value' => '1']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sale_of_buyer_property_contingency_days', 'meta_value' => '30']);
    }

    // ── Test 18: Sale-of-buyer-property toggle OFF saves 0 ───────────────────

    public function test_sale_of_buyer_property_contingency_toggle_off_saves_zero(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), []);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sale_of_buyer_property_contingency', 'meta_value' => '0']);
    }

    // ── Test 19: possession_notes saves, repopulates, and displays read-only ──

    public function test_possession_notes_saves_repopulates_and_displays_read_only(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'possession_notes' => 'Buyer prefers possession at closing.',
        ]);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'possession_notes', 'meta_value' => 'Buyer prefers possession at closing.']);

        $formResponse = $this->actingAs($owner)->get(route('offers.show', $offer));
        $formResponse->assertStatus(200);
        $formResponse->assertSee('Buyer prefers possession at closing.');

        $offer->update(['status' => 'submitted']);
        $readOnlyResponse = $this->actingAs($owner)->get(route('offers.show', $offer));
        $readOnlyResponse->assertStatus(200);
        $readOnlyResponse->assertSee('Possession Notes');
        $readOnlyResponse->assertSee('Buyer prefers possession at closing.');
    }

    // ── Test 20: possession_notes row hidden when empty in read-only view ─────

    public function test_possession_notes_row_hidden_when_empty_in_read_only(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee('Possession Notes');
    }

    // ── Test 21: preferred_inspection_period absent from form and never written

    public function test_preferred_inspection_period_absent_from_form_and_never_written(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $formResponse = $this->actingAs($owner)->get(route('offers.show', $offer));
        $formResponse->assertDontSee('name="preferred_inspection_period"', false);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'preferred_inspection_period' => '10',
        ]);

        $this->assertDatabaseMissing('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'preferred_inspection_period']);
    }

    // ── Test 22: Submit button has visible primary styling ────────────────────

    public function test_submit_button_has_visible_primary_styling(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('save-offer-terms-btn', false);
        $response->assertSee('#2563eb', false);
    }

    // ── Test 23: Exactly one Save Offer Terms button in draft mode ─────────────

    public function test_exactly_one_save_offer_terms_button_in_draft(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'id="save-offer-terms-btn"'),
            'Expected exactly one element with id="save-offer-terms-btn"');
        $this->assertSame(1, substr_count($html, 'Save Offer Terms'),
            'Expected exactly one Save Offer Terms button text');
    }

    // ── Test 24: Unit selectors render for all four deposit fields ─────────────

    public function test_unit_selectors_render_for_all_four_deposit_fields(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('name="earnest_deposit_unit"', false);
        $response->assertSee('name="initial_deposit_amount_unit"', false);
        $response->assertSee('name="additional_deposit_amount_unit"', false);
        $response->assertSee('name="down_payment_unit"', false);
        $response->assertSee('name="earnest_deposit"', false);
        $response->assertSee('name="initial_deposit_amount"', false);
        $response->assertSee('name="additional_deposit_amount"', false);
        $response->assertSee('name="down_payment_value"', false);
    }

    // ── Test 25: Earnest deposit % unit persists and shows as percentage ───────

    public function test_earnest_deposit_percent_unit_persists_and_displays(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'earnest_deposit'      => '1.5',
            'earnest_deposit_unit' => '%',
        ]);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'earnest_deposit',      'meta_value' => '1.5']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'earnest_deposit_unit', 'meta_value' => '%']);

        $offer->update(['status' => 'submitted']);
        $readOnly = $this->actingAs($owner)->get(route('offers.show', $offer));
        $readOnly->assertSee('1.5%');
        $readOnly->assertDontSee('$1');
    }

    // ── Test 26: Down payment $ unit persists and shows as dollar amount ───────

    public function test_down_payment_dollar_unit_persists_and_displays(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'down_payment_value' => '90000',
            'down_payment_unit'  => '$',
        ]);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'down_payment_value', 'meta_value' => '90000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'down_payment_unit',  'meta_value' => '$']);

        $offer->update(['status' => 'submitted']);
        $readOnly = $this->actingAs($owner)->get(route('offers.show', $offer));
        $readOnly->assertSee('$90,000');
    }

    // ── Test 27: Lease Option fields save and persist ─────────────────────────

    public function test_lease_option_fields_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'               => 'Lease Option',
            'lease_option_price'           => '500000',
            'lease_option_payment'         => '2500',
            'lease_option_duration'        => '12',
            'has_option_fee'               => 'Yes',
            'option_fee_amount'            => '15000',
            'lease_option_fee_credit'      => 'Partial',
            'lease_option_fee_credit_pct'  => '50',
            'lease_option_maintenance'     => 'Tenant-Buyer',
            'lease_option_conditions'      => 'Option exercisable after 12 months',
            'lease_option_terms'           => 'Buyer may inspect during lease term',
            'lease_option_extension_terms' => 'May extend 6 months with $5,000 fee',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_type',          'meta_value' => 'Lease Option']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_option_price',      'meta_value' => '500000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_option_payment',    'meta_value' => '2500']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_option_duration',   'meta_value' => '12']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'has_option_fee',          'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'option_fee_amount',       'meta_value' => '15000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_option_fee_credit', 'meta_value' => 'Partial']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_option_conditions', 'meta_value' => 'Option exercisable after 12 months']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_option_maintenance','meta_value' => 'Tenant-Buyer']);

        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('500,000');
        $reload->assertSee('2,500');
        $reload->assertSee('12');
        $reload->assertSee('Option exercisable after 12 months');
    }

    // ── Test 28: Lease Purchase fields save and persist ───────────────────────

    public function test_lease_purchase_fields_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'                    => 'Lease Purchase',
            'lease_purchase_price'              => '800000',
            'lease_purchase_payment'            => '5000',
            'lease_purchase_duration'           => '24',
            'lease_purchase_rent_credit'        => 'Yes',
            'lease_purchase_rent_credit_amount' => '500',
            'lease_purchase_deposit'            => '10000',
            'lease_purchase_maintenance'        => 'Shared',
            'lease_purchase_conditions'         => 'Buyer must secure financing by lease end',
            'lease_purchase_terms'              => 'Rent credits apply toward purchase',
            'lease_purchase_extension_terms'    => 'Lease may extend 6 months',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_type',                 'meta_value' => 'Lease Purchase']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_price',           'meta_value' => '800000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_payment',         'meta_value' => '5000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_duration',        'meta_value' => '24']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_rent_credit',     'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_rent_credit_amount', 'meta_value' => '500']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_deposit',         'meta_value' => '10000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'lease_purchase_conditions',      'meta_value' => 'Buyer must secure financing by lease end']);

        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('800,000');
        $reload->assertSee('5,000');
        $reload->assertSee('Buyer must secure financing by lease end');
    }

    // ── Test 29: NFT fields save and persist ──────────────────────────────────

    public function test_nft_fields_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'        => 'Non-Fungible Token (NFT)',
            'nft_description'       => 'Tokenized Real Estate',
            'nft_percentage'        => '40',
            'cash_percentage_nft'   => '60',
            'nft_valuation_method'  => 'Floor price on OpenSea',
            'nft_transfer_method'   => 'MetaMask',
            'nft_gas_fees'          => 'Buyer',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_type',       'meta_value' => 'Non-Fungible Token (NFT)']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'nft_description',      'meta_value' => 'Tokenized Real Estate']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'nft_percentage',       'meta_value' => '40']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'cash_percentage_nft',  'meta_value' => '60']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'nft_valuation_method', 'meta_value' => 'Floor price on OpenSea']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'nft_transfer_method',  'meta_value' => 'MetaMask']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'nft_gas_fees',         'meta_value' => 'Buyer']);

        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('Tokenized Real Estate');
        $reload->assertSee('40');
        $reload->assertSee('60');
    }

    // ── Test 30: Other financing details save and persist ─────────────────────

    public function test_other_financing_details_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'          => 'Other',
            'other_financing_details' => 'Gold Bullion exchange at current spot price',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_type',          'meta_value' => 'Other']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'other_financing_details', 'meta_value' => 'Gold Bullion exchange at current spot price']);

        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('Gold Bullion exchange at current spot price');
    }

    // ── Test 31: Seller financing balloon + amortization save and persist ─────

    public function test_seller_financing_expanded_fields_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'                         => 'Seller Financing',
            'seller_financing_amount'                => '400000',
            'seller_financing_rate'                  => '6.5',
            'seller_financing_term'                  => '30 years',
            'seller_financing_amortization'          => 'Fully Amortizing',
            'seller_financing_payment_frequency'     => 'Monthly',
            'seller_financing_balloon'               => 'Yes',
            'seller_financing_balloon_amount'        => '100000',
            'seller_financing_balloon_date'          => '5 Years',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_amortization',      'meta_value' => 'Fully Amortizing']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_payment_frequency', 'meta_value' => 'Monthly']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_balloon',           'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_balloon_amount',    'meta_value' => '100000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_balloon_date',      'meta_value' => '5 Years']);

        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('Fully Amortizing');
        $reload->assertSee('Monthly');
    }

    // ── Test 32: Exchange/Trade full field set saves and persists ─────────────

    public function test_exchange_trade_full_fields_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'            => 'Exchange/Trade',
            'exchange_item'             => 'Another Home',
            'exchange_item_value'       => '350000',
            'exchange_item_condition'   => 'Good',
            'additional_cash'           => '25000',
            'value_determination'       => 'Licensed Appraisal',
            'exchange_transfer_method'  => 'Title transfer at closing',
            'exchange_liens'            => 'Yes',
            'exchange_liens_details'    => 'Auto loan balance $8,500',
            'exchange_inspection_rights'=> 'Yes',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_item',              'meta_value' => 'Another Home']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_item_condition',    'meta_value' => 'Good']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_cash',           'meta_value' => '25000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'value_determination',       'meta_value' => 'Licensed Appraisal']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_transfer_method',  'meta_value' => 'Title transfer at closing']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_liens',            'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_liens_details',    'meta_value' => 'Auto loan balance $8,500']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_inspection_rights','meta_value' => 'Yes']);

        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('Another Home');
        $reload->assertSee('Licensed Appraisal');
    }

    // ── Test 33: Seller Financing expanded fields (SF-specific) save/persist ──

    public function test_seller_financing_sf_specific_fields_save_and_persist(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'               => 'Seller Financing',
            'sf_purchase_price'            => '500000',
            'sf_down_payment_type'         => '%',
            'sf_down_payment_amount'       => '20',
            'seller_financing_amount_type' => '%',
            'seller_financing_amount'      => '80',
            'seller_financing_rate'        => '6.5',
            'seller_financing_term'        => '30 Years',
            'prepayment_penalty'           => 'Yes',
            'prepayment_penalty_amount'    => '5000',
            'seller_late_fee_amount'       => '$100 after 10 days',
        ];

        $this->actingAs($owner)->post(route('offers.terms', $offer), $payload)->assertRedirect();

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sf_purchase_price',          'meta_value' => '500000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sf_down_payment_type',        'meta_value' => '%']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sf_down_payment_amount',      'meta_value' => '20']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_amount_type','meta_value' => '%']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_financing_amount',     'meta_value' => '80']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'prepayment_penalty',          'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'prepayment_penalty_amount',   'meta_value' => '5000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_late_fee_amount',      'meta_value' => '$100 after 10 days']);

        $offer->update(['status' => 'submitted']);
        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertSee('Desired Purchase Price');
        $reload->assertSee('80%');
        $reload->assertSee('Late Payment Fee');
    }

    // ── Test 34: Lease Option fee credit Partial shows pct in read-only ───────

    public function test_lease_option_fee_credit_partial_shows_pct_in_read_only(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $offer->saveMeta('financing_type',          'Lease Option');
        $offer->saveMeta('lease_option_fee_credit', 'Partial');
        $offer->saveMeta('lease_option_fee_credit_pct', '50');
        $offer->update(['status' => 'submitted']);

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));
        $response->assertStatus(200);
        $response->assertSee('Partial');
        $response->assertSee('50%');
    }

    // ── Test 35: Financing section placeholders follow Enter … (e.g., …) format ─

    public function test_financing_section_placeholders_follow_enter_eg_format(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        // Placeholders must start with "Enter" — not bare "e.g.,"
        $response->assertSee('Enter additional cash offered (e.g., 25,000)', false);
        $response->assertSee('Enter valuation method (e.g., Licensed appraisal, Online valuation)', false);
        $response->assertSee('Enter transfer method (e.g., Title transfer, Bill of sale, Delivery at closing)', false);
        $response->assertSee('Enter lien details (e.g., Auto loan balance, UCC filing)', false);
        $response->assertSee('Enter purchase price (e.g., 500,000)', false);
        $response->assertSee('Enter balloon amount (e.g., 100,000)', false);
        $response->assertSee('Enter due date (e.g., 5 Years)', false);
        $response->assertSee('Enter penalty amount (e.g., 5,000)', false);
        $response->assertSee('Enter offering price (e.g., 500,000)', false);
        $response->assertSee('Enter monthly payment (e.g., 2,500)', false);
        $response->assertSee('Enter duration in months (e.g., 12)', false);
        $response->assertSee('Enter option fee amount (e.g., 15,000)', false);
        $response->assertSee('Enter valuation method (e.g., Floor price on OpenSea, Independent appraisal)', false);
        $response->assertSee('Enter transfer method (e.g., MetaMask, OpenSea, Propy Title, Escrow smart contract)', false);

        // No bare "e.g.," placeholders should remain in money/text financing fields
        $html = $response->getContent();
        $this->assertStringNotContainsString('placeholder="e.g.,', $html,
            'No input should have a bare e.g., placeholder without an "Enter" prefix');
    }

    // ── Test 36: Balloon Amount and Prepayment Penalty inputs have $ prefix ────

    public function test_balloon_amount_and_prepayment_penalty_have_dollar_prefix(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Balloon amount: $ prefix span must appear before the balloon amount input
        $balloonInputPos   = strpos($html, 'name="seller_financing_balloon_amount"');
        $dollarBeforeBalloon = strrpos(substr($html, 0, $balloonInputPos), 'fa-dollar-sign');
        $this->assertNotFalse($dollarBeforeBalloon,
            'Balloon Payment Amount input must be preceded by a $ input-group prefix');

        // Prepayment penalty amount: $ prefix span must appear before the input
        $penaltyInputPos     = strpos($html, 'name="prepayment_penalty_amount"');
        $dollarBeforePenalty = strrpos(substr($html, 0, $penaltyInputPos), 'fa-dollar-sign');
        $this->assertNotFalse($dollarBeforePenalty,
            'Prepayment Penalty Amount input must be preceded by a $ input-group prefix');

        // Option fee amount: $ prefix span must appear before the input
        $optionFeeInputPos  = strpos($html, 'name="option_fee_amount"');
        $dollarBeforeOption = strrpos(substr($html, 0, $optionFeeInputPos), 'fa-dollar-sign');
        $this->assertNotFalse($dollarBeforeOption,
            'Option Fee Amount input must be preceded by a $ input-group prefix');
    }

    // ── Test 37: Comma-formatted money input is saved as clean numeric string ──

    public function test_comma_formatted_money_input_is_saved_as_clean_number(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'offer_price'                    => '450,000',
            'earnest_deposit'                => '5,000',
            'earnest_deposit_unit'           => '$',
            'seller_financing_balloon_amount'=> '100,000',
            'prepayment_penalty_amount'      => '5,000',
            'financing_type'                 => 'Seller Financing',
            'seller_financing_balloon'       => 'Yes',
            'prepayment_penalty'             => 'Yes',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'offer_price',
            'meta_value' => '450000',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'earnest_deposit',
            'meta_value' => '5000',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'seller_financing_balloon_amount',
            'meta_value' => '100000',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'prepayment_penalty_amount',
            'meta_value' => '5000',
        ]);
    }

    // ── Test 38: Percent-unit mixed fields persist decimal values unchanged ────

    public function test_percent_unit_mixed_fields_persist_decimal_values_unchanged(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'earnest_deposit'                => '1.5',
            'earnest_deposit_unit'           => '%',
            'down_payment_value'             => '20',
            'down_payment_unit'              => '%',
            'initial_deposit_amount'         => '2',
            'initial_deposit_amount_unit'    => '%',
            'additional_deposit_amount'      => '1',
            'additional_deposit_amount_unit' => '%',
            'sf_down_payment_amount'         => '20',
            'sf_down_payment_type'           => '%',
            'seller_financing_amount'        => '80',
            'seller_financing_amount_type'   => '%',
            'financing_type'                 => 'Seller Financing',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        // Decimal percent value must be stored exactly — not comma-stripped to integer
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'earnest_deposit',
            'meta_value' => '1.5',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'sf_down_payment_amount',
            'meta_value' => '20',
        ]);
        $this->assertDatabaseHas('offer_metas', [
            'offer_id'   => $offer->id,
            'meta_key'   => 'seller_financing_amount',
            'meta_value' => '80',
        ]);

        // Reload page — percent-mode fields must not display comma-formatted values
        $reload = $this->actingAs($owner)->get(route('offers.show', $offer));
        $reload->assertStatus(200);
        // The value "1.5" must appear as-is in the form (not formatted to "2" or "1")
        $reload->assertSee('value="1.5"', false);
        // The earnest deposit unit selector must render with % selected
        $reload->assertSee('data-unit-select="earnest_deposit_unit"', false);
    }

    // ── Test 39: Appraisal contingency days saves to offer_metas ─────────────

    public function test_appraisal_contingency_days_saves_to_offer_metas(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'appraisal_contingency'      => '1',
            'appraisal_contingency_days' => '14',
        ]);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'appraisal_contingency',      'meta_value' => '1']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'appraisal_contingency_days', 'meta_value' => '14']);
    }

    // ── Test 40: Appraisal contingency days field renders in form and repopulates

    public function test_appraisal_contingency_days_renders_and_repopulates(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'appraisal_contingency'      => '1',
            'appraisal_contingency_days' => '21',
        ]);

        $formResponse = $this->actingAs($owner)->get(route('offers.show', $offer));
        $formResponse->assertStatus(200);
        $formResponse->assertSee('name="appraisal_contingency_days"', false);
        $formResponse->assertSee('value="21"', false);
    }

    // ── Test 41: Read-only view displays appraisal contingency days ───────────

    public function test_read_only_view_displays_appraisal_contingency_days(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');

        $offer->saveMeta('appraisal_contingency',      '1');
        $offer->saveMeta('appraisal_contingency_days', '10');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Appraisal Contingency');
        $response->assertSee('10 days');
    }

    // ── Test 42: New offer down payment unit defaults to percent ──────────────

    public function test_new_offer_down_payment_unit_defaults_to_percent(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('downPayUnit: \'%\'', false);
    }

    // ── Test 43: Saving down payment without explicit unit persists % default ──

    public function test_saving_down_payment_without_unit_persists_percent_default(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'down_payment_value' => '20',
        ]);

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'down_payment_unit', 'meta_value' => '%']);
    }

    // ── Test 44: Included personal property placeholder uses sentence case ────

    public function test_included_personal_property_placeholder_uses_sentence_case(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('Washer/dryer', false);
        $response->assertSee('Dining room chandelier', false);
        $response->assertDontSee('Washer/Dryer', false);
        $response->assertDontSee('Dining Room Chandelier', false);
    }

    // ── Test 45: Submit Offer action button has visible primary CTA styling ───

    public function test_submit_offer_action_button_has_visible_primary_cta_styling(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('submit-offer-action-btn', false);
        $response->assertSee('Submit Offer');
        $html = $response->getContent();
        $this->assertStringContainsString('#2563eb', $html,
            'Available Actions section must include blue CTA styling for the Submit Offer button');
    }
}
