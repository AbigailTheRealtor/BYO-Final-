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
        $response->assertSee('450000');
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
        // Conditional financing sub-fields
        $response->assertSee('name="assumable_terms"', false);
        $response->assertSee('name="cryptocurrency_type"', false);
        $response->assertSee('name="exchange_item"', false);
        $response->assertSee('name="seller_financing_amount"', false);
        // Purchase terms
        $response->assertSee('name="initial_deposit_amount"', false);
        $response->assertSee('name="additional_deposit_amount"', false);
        $response->assertSee('name="escrow_agent_preference"', false);
        $response->assertSee('name="preferred_inspection_period"', false);
        $response->assertSee('name="appraisal_contingency_preference"', false);
        $response->assertSee('name="financing_contingency_preference"', false);
        $response->assertSee('name="sale_of_buyer_property_contingency"', false);
        $response->assertSee('name="possession_preference"', false);
        $response->assertSee('name="possession_details"', false);
        $response->assertSee('name="seller_contribution_requested"', false);
        $response->assertSee('name="included_personal_property"', false);
        $response->assertSee('name="excluded_items"', false);
        $response->assertSee('name="home_warranty_requested"', false);
        $response->assertSee('name="hoa_condo_association_terms"', false);
        $response->assertSee('name="additional_seller_sale_terms"', false);
    }

    // ── Test 11: Saving all new fields persists them to offer_metas ───────────

    public function test_saving_all_new_sale_fields_persists_to_offer_metas(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $payload = [
            'financing_type'                       => 'Assumable',
            'assumable_terms'                      => '$200,000 at 3.5% fixed for 25 years',
            'assumable_loan_type'                  => 'FHA',
            'assumable_interest_rate'              => '3.5',
            'outstanding_balance'                  => '200000',
            'assumable_loan_term_remaining'        => '25 years',
            'initial_deposit_amount'               => '5000',
            'initial_deposit_timeframe'            => 'Within 3 Days',
            'additional_deposit_amount'            => '10000',
            'additional_deposit_timeframe'         => 'Within 10 Days',
            'escrow_agent_preference'              => 'First American Title',
            'preferred_inspection_period'          => '10',
            'appraisal_contingency_preference'     => 'Preferred Waived',
            'financing_contingency_preference'     => 'Negotiable',
            'sale_of_buyer_property_contingency'   => 'Accepted',
            'possession_preference'                => 'Seller Rent Back',
            'possession_details'                   => 'Seller requests 30-day rent back at market rate',
            'seller_contribution_requested'        => 'Yes',
            'seller_contribution_details'          => '$5,000 toward closing costs',
            'included_personal_property'           => 'Refrigerator, Washer/Dryer',
            'excluded_items'                       => 'Antique chandelier',
            'home_warranty_requested'              => 'Yes',
            'home_warranty_details'                => '$500 one-year warranty',
            'hoa_condo_association_terms'          => '$200 transfer fee',
            'additional_seller_sale_terms'         => 'Seller must vacate within 30 days.',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_type',                     'meta_value' => 'Assumable']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'assumable_terms',                    'meta_value' => '$200,000 at 3.5% fixed for 25 years']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'assumable_loan_type',                'meta_value' => 'FHA']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'outstanding_balance',                'meta_value' => '200000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'initial_deposit_amount',             'meta_value' => '5000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'initial_deposit_timeframe',          'meta_value' => 'Within 3 Days']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_deposit_amount',         'meta_value' => '10000']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'escrow_agent_preference',            'meta_value' => 'First American Title']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'preferred_inspection_period',         'meta_value' => '10']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'appraisal_contingency_preference',   'meta_value' => 'Preferred Waived']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'financing_contingency_preference',   'meta_value' => 'Negotiable']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'sale_of_buyer_property_contingency', 'meta_value' => 'Accepted']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'possession_preference',              'meta_value' => 'Seller Rent Back']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'possession_details',                 'meta_value' => 'Seller requests 30-day rent back at market rate']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_contribution_requested',     'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'seller_contribution_details',      'meta_value' => '$5,000 toward closing costs']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'included_personal_property',        'meta_value' => 'Refrigerator, Washer/Dryer']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'excluded_items',                    'meta_value' => 'Antique chandelier']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'home_warranty_requested',          'meta_value' => 'Yes']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'home_warranty_details',             'meta_value' => '$500 one-year warranty']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'hoa_condo_association_terms',       'meta_value' => '$200 transfer fee']);
        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'additional_seller_sale_terms',      'meta_value' => 'Seller must vacate within 30 days.']);
    }

    // ── Test 12: Saved new values repopulate form on reload ───────────────────

    public function test_new_sale_fields_repopulate_on_reload(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'draft');
        $this->allowPlayoffAccess($owner);

        $this->actingAs($owner)->post(route('offers.terms', $offer), [
            'financing_type'             => 'cryptocurrency',
            'cryptocurrency_type'        => 'Bitcoin',
            'crypto_percentage'          => '50',
            'escrow_agent_preference'    => 'Propy Title',
            'included_personal_property' => 'Pool table, Wine fridge',
            'additional_seller_sale_terms' => 'Close by year end.',
        ]);

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee('value="Cryptocurrency"', false);
        $response->assertSee('Bitcoin');
        $response->assertSee('50');
        $response->assertSee('Propy Title');
        $response->assertSee('Pool table, Wine fridge');
        $response->assertSee('Close by year end.');
    }

    // ── Test 13: Read-only view displays all new sale fields ──────────────────

    public function test_read_only_view_displays_new_sale_fields(): void
    {
        ['offer' => $offer, 'owner' => $owner] = $this->makeOfferWithAuction('sale', 'submitted');

        $offer->saveMeta('financing_type',                     'Seller Financing');
        $offer->saveMeta('seller_financing_amount',            '350000');
        $offer->saveMeta('seller_financing_rate',              '6.5');
        $offer->saveMeta('seller_financing_term',              '30 years');
        $offer->saveMeta('initial_deposit_amount',             '7500');
        $offer->saveMeta('initial_deposit_timeframe',          'Within 5 Days');
        $offer->saveMeta('escrow_agent_preference',              'Old Republic Title');
        $offer->saveMeta('preferred_inspection_period',          '15');
        $offer->saveMeta('appraisal_contingency_preference',     'Required');
        $offer->saveMeta('financing_contingency_preference',     'Preferred Waived');
        $offer->saveMeta('sale_of_buyer_property_contingency',   'Negotiable');
        $offer->saveMeta('possession_preference',                'Seller Rent Back');
        $offer->saveMeta('possession_details',                   'Seller requests 30-day rent back');
        $offer->saveMeta('seller_contribution_requested',      'Yes');
        $offer->saveMeta('seller_contribution_details',        '$3,000 toward closing costs');
        $offer->saveMeta('included_personal_property',         'Stainless fridge');
        $offer->saveMeta('excluded_items',                     'Garden shed');
        $offer->saveMeta('home_warranty_requested',            'Yes');
        $offer->saveMeta('home_warranty_details',              '$600 AHS warranty');
        $offer->saveMeta('hoa_condo_association_terms',        '$150 transfer fee');
        $offer->saveMeta('additional_seller_sale_terms',       'Must close by Dec 31.');

        $response = $this->actingAs($owner)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee('Save Offer Terms');
        $response->assertSee('Seller Financing');
        $response->assertSee('350,000');
        $response->assertSee('6.5%');
        $response->assertSee('30 years');
        $response->assertSee('7500');
        $response->assertSee('Within 5 Days');
        $response->assertSee('Old Republic Title');
        $response->assertSee('15 days');
        $response->assertSee('Required');
        $response->assertSee('Preferred Waived');
        $response->assertSee('Negotiable');
        $response->assertSee('Seller Rent Back');
        $response->assertSee('Seller requests 30-day rent back');
        $response->assertSee('$3,000 toward closing costs');
        $response->assertSee('Stainless fridge');
        $response->assertSee('Garden shed');
        $response->assertSee('$600 AHS warranty');
        $response->assertSee('$150 transfer fee');
        $response->assertSee('Must close by Dec 31.');
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
            'exchange_item'       => 'Another Home in Tampa',
            'exchange_item_value' => '350000',
        ];

        $response = $this->actingAs($owner)->post(route('offers.terms', $offer), $payload);
        $response->assertRedirect(route('offers.show', $offer));

        $this->assertDatabaseHas('offer_metas', ['offer_id' => $offer->id, 'meta_key' => 'exchange_item',       'meta_value' => 'Another Home in Tampa']);
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
}
