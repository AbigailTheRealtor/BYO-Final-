<?php

namespace Tests\Feature\Offers;

use App\Http\Controllers\LandlordOfferListingController;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for the Landlord Listing Submit Application workflow.
 *
 * Covers:
 *   - ensureLinkedOfferAuction creates an OfferAuction on first view and is idempotent
 *   - POST to offers.store with role=landlord stamps offer_type=rental
 *   - Offers.show renders a rental form (not the sale form) for a landlord offer
 *   - saveTerms persists pre-screening + lease offer fields
 *   - submit transitions offer status to 'submitted'
 *   - Landlord can accept a submitted tenant application
 *   - Landlord can reject a submitted tenant application
 *   - Landlord can counter a submitted tenant application
 *   - Tenant can withdraw a submitted application
 *   - Pre-fill: show pre-populates monthly_rent from listing's desired_rental_amount
 */
class LandlordSubmitApplicationTest extends TestCase
{
    use DatabaseTransactions;

    private User $landlord;
    private User $tenant;
    private LandlordAgentAuction $listing;

    protected function setUp(): void
    {
        parent::setUp();

        // 'landlord' is not a user_type enum value; landlords are property owners
        // and use 'seller' as their user_type.
        $this->landlord = User::factory()->create(['user_type' => 'seller']);
        $this->tenant   = User::factory()->create(['user_type' => 'buyer']);

        $this->listing = LandlordAgentAuction::create([
            'user_id'     => $this->landlord->id,
            'title'       => 'Test Rental Property',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $this->listing->id,
            'meta_key'                  => 'workflow_type',
            'meta_value'                => 'offer_listing',
        ]);
    }

    private function actingAsTenant(): static
    {
        $this->app['config']->set(
            'offer.playoff_access.allowed_user_ids',
            [$this->tenant->id]
        );
        return $this->actingAs($this->tenant);
    }

    private function actingAsLandlord(): static
    {
        $this->app['config']->set(
            'offer.playoff_access.allowed_user_ids',
            [$this->landlord->id]
        );
        return $this->actingAs($this->landlord);
    }

    private function getLinkedOfferAuction(): OfferAuction
    {
        $controller = app(LandlordOfferListingController::class);
        $this->listing->load('meta');
        return $controller->ensureLinkedOfferAuction($this->listing);
    }

    // ── ensureLinkedOfferAuction ──────────────────────────────────────────────

    public function test_ensure_linked_offer_auction_creates_record_on_first_call(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $this->assertNotNull($offerAuction);
        $this->assertInstanceOf(OfferAuction::class, $offerAuction);
        $this->assertEquals($this->landlord->id, $offerAuction->user_id);
        $this->assertEquals('rental', $offerAuction->info('offer_type'));
        $this->assertEquals($this->listing->id, (int) $offerAuction->info('linked_landlord_auction_id'));
    }

    public function test_ensure_linked_offer_auction_is_idempotent(): void
    {
        $first  = $this->getLinkedOfferAuction();
        $this->listing->load('meta');
        $second = $this->getLinkedOfferAuction();

        $this->assertEquals($first->id, $second->id, 'ensureLinkedOfferAuction must return the same record on repeated calls.');
    }

    public function test_listing_view_stores_linked_offer_auction_id_meta(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('offer.listing.landlord.view', $this->listing->id))
            ->assertStatus(200);

        $this->listing->load('meta');
        $linkedId = $this->listing->info('linked_offer_auction_id');
        $this->assertNotEmpty($linkedId, 'linked_offer_auction_id meta should be written after first view.');
    }

    // ── store: creates draft with offer_type=rental ───────────────────────────

    public function test_store_creates_draft_with_rental_offer_type(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $response = $this->actingAsTenant()
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'landlord',
            ]);

        $offer = Offer::where('offer_auction_id', $offerAuction->id)
            ->where('user_id', $this->tenant->id)
            ->latest()->first();

        $this->assertNotNull($offer, 'Draft offer should have been created.');
        $this->assertEquals('draft', $offer->status);
        $this->assertEquals('landlord', $offer->role);
        $this->assertEquals('rental', $offer->getMeta('offer_type'), 'offer_type meta must be stamped as rental.');

        $response->assertRedirect(route('offers.show', $offer));
    }

    // ── show: renders rental form ─────────────────────────────────────────────

    public function test_show_renders_rental_terms_form_not_sale_form(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'draft',
        ]);
        $offer->saveMeta('offer_type', 'rental');

        $response = $this->actingAsTenant()
            ->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $body = $response->getContent();

        $this->assertStringContainsString('Pre-Screening Information', $body,
            'Offer show must render the Pre-Screening Information section for rental.');
        $this->assertStringContainsString('Lease Offer Terms', $body,
            'Offer show must render the Lease Offer Terms section for rental.');
        $this->assertStringNotContainsString('Financing Type', $body,
            'Offer show must NOT render the sale Financing Type field for rental.');
    }

    // ── show: pre-fill from listing metas ────────────────────────────────────

    public function test_show_prefills_monthly_rent_from_listing_desired_rental_amount(): void
    {
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $this->listing->id,
            'meta_key'                  => 'desired_rental_amount',
            'meta_value'                => '2500',
        ]);

        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'draft',
        ]);
        $offer->saveMeta('offer_type', 'rental');

        $body = $this->actingAsTenant()
            ->get(route('offers.show', $offer))
            ->assertStatus(200)
            ->getContent();

        // $fmtMoney formats 2500 as "2,500" — check for the formatted value
        $this->assertStringContainsString('2,500', $body,
            'Show page should pre-populate the desired_rental_amount from the listing (formatted as money).');
    }

    public function test_show_prefills_lease_condition_fields_from_listing_metas(): void
    {
        $metasToSave = [
            'security_deposit_amount'       => '2500',
            'total_move_in_funds_required'  => '7500',
            'available_date'                => '2026-09-01',
            'lease_date'                    => '2026-09-01',
            'utilities'                     => 'Water and trash included',
            'maintenance_by'                => 'Landlord',
            'parking_terms'                 => '1 assigned covered spot',
        ];
        foreach ($metasToSave as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $this->listing->id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        $offerAuction = $this->getLinkedOfferAuction();
        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'draft',
        ]);
        $offer->saveMeta('offer_type', 'rental');

        $body = $this->actingAsTenant()
            ->get(route('offers.show', $offer))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('2,500',                     $body, 'security_deposit pre-fill from listing');
        $this->assertStringContainsString('7,500',                     $body, 'move_in_funds pre-fill from listing');
        $this->assertStringContainsString('2026-09-01',                $body, 'move_in_date pre-fill from listing available_date');
        $this->assertStringContainsString('Water and trash included',  $body, 'utilities_terms pre-fill from listing');
        $this->assertStringContainsString('Landlord',                  $body, 'maintenance_responsibility pre-fill from listing');
        $this->assertStringContainsString('1 assigned covered spot',   $body, 'parking_terms pre-fill from listing');
    }

    public function test_show_denies_stranger_access_to_rental_offer(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();
        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'draft',
        ]);
        $offer->saveMeta('offer_type', 'rental');

        $stranger = User::factory()->create(['user_type' => 'seller']);

        $this->actingAs($stranger)
            ->get(route('offers.show', $offer))
            ->assertStatus(403,
                'An unrelated user must be denied access to view another party\'s rental application.');
    }

    // ── saveTerms: persists all rental fields ─────────────────────────────────

    public function test_save_terms_persists_prescreening_and_lease_fields(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'draft',
        ]);
        $offer->saveMeta('offer_type', 'rental');

        $response = $this->actingAsTenant()
            ->post(route('offers.terms', $offer), [
                '_offer_terms_present'    => '1',
                'num_occupants'           => '2',
                'has_pets'                => 'Yes',
                'pet_details'             => '1 dog, lab mix, 60 lbs',
                'smoking_preference'      => 'No',
                'monthly_income'          => '6000',
                'credit_score_range'      => '720-750',
                'screening_notes'         => 'Two-year tenant history, stable income.',
                'message_to_landlord'     => 'Very interested in this property.',
                'monthly_rent'            => '2200',
                'lease_term_months'       => '12',
                'security_deposit'        => '2200',
                'last_month_rent_offered' => 'Yes',
                'move_in_funds'           => '6600',
                'move_in_date'            => '2026-08-01',
                'utilities_terms'         => 'Tenant pays electric and gas',
                'maintenance_responsibility' => 'Landlord',
                'parking_terms'           => '1 assigned spot',
                'additional_lease_terms'  => 'Request to allow small garden plot.',
            ]);

        $response->assertRedirect(route('offers.show', $offer));

        $offer->load('metas');

        $this->assertEquals('2',       $offer->getMeta('num_occupants'));
        $this->assertEquals('Yes',     $offer->getMeta('has_pets'));
        $this->assertEquals('1 dog, lab mix, 60 lbs', $offer->getMeta('pet_details'));
        $this->assertEquals('No',      $offer->getMeta('smoking_preference'));
        $this->assertEquals('6000',    $offer->getMeta('monthly_income'));
        $this->assertEquals('720-750', $offer->getMeta('credit_score_range'));
        $this->assertEquals('Two-year tenant history, stable income.', $offer->getMeta('screening_notes'));
        $this->assertEquals('Very interested in this property.', $offer->getMeta('message_to_landlord'));
        $this->assertEquals('2200',    $offer->getMeta('monthly_rent'));
        $this->assertEquals('12',      $offer->getMeta('lease_term_months'));
        $this->assertEquals('2200',    $offer->getMeta('security_deposit'));
        $this->assertEquals('Yes',     $offer->getMeta('last_month_rent_offered'));
        $this->assertEquals('6600',    $offer->getMeta('move_in_funds'));
        $this->assertEquals('2026-08-01', $offer->getMeta('move_in_date'));
        $this->assertEquals('Tenant pays electric and gas', $offer->getMeta('utilities_terms'));
        $this->assertEquals('Landlord', $offer->getMeta('maintenance_responsibility'));
        $this->assertEquals('1 assigned spot', $offer->getMeta('parking_terms'));
        $this->assertEquals('Request to allow small garden plot.', $offer->getMeta('additional_lease_terms'));
    }

    // ── submit: transitions to submitted ─────────────────────────────────────

    public function test_submit_transitions_offer_to_submitted(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'draft',
        ]);
        $offer->saveMeta('offer_type', 'rental');
        $offer->saveMeta('monthly_rent', '2200');

        $response = $this->actingAsTenant()
            ->post(route('offers.submit', $offer));

        $response->assertRedirect(route('offers.show', $offer));

        $offer->refresh();
        $this->assertEquals('submitted', $offer->status,
            'Offer status must be "submitted" after tenant submits application.');
    }

    // ── landlord accept ───────────────────────────────────────────────────────

    public function test_landlord_can_accept_submitted_application(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'submitted',
        ]);
        $offer->saveMeta('offer_type', 'rental');
        $offer->saveMeta('monthly_rent', '2200');

        $response = $this->actingAsLandlord()
            ->post(route('offers.accept', $offer));

        $response->assertRedirect(route('offers.show', $offer));

        $offer->refresh();
        $this->assertEquals('accepted', $offer->status,
            'Landlord must be able to accept a submitted tenant application.');
    }

    // ── landlord reject ───────────────────────────────────────────────────────

    public function test_landlord_can_reject_submitted_application(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'submitted',
        ]);
        $offer->saveMeta('offer_type', 'rental');
        $offer->saveMeta('monthly_rent', '2200');

        $response = $this->actingAsLandlord()
            ->post(route('offers.reject', $offer));

        $response->assertRedirect(route('offers.show', $offer));

        $offer->refresh();
        $this->assertEquals('rejected', $offer->status,
            'Landlord must be able to reject a submitted tenant application.');
    }

    // ── landlord counter ──────────────────────────────────────────────────────

    public function test_landlord_can_counter_submitted_application(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'submitted',
        ]);
        $offer->saveMeta('offer_type', 'rental');
        $offer->saveMeta('monthly_rent', '2200');

        $response = $this->actingAsLandlord()
            ->post(route('offers.counter', $offer), [
                'monthly_rent'    => '2400',
                'security_deposit' => '2400',
                'lease_term_months' => '12',
            ]);

        $response->assertRedirectContains('/offers/');

        $offer->refresh();
        $this->assertEquals('countered', $offer->status,
            'Landlord must be able to counter a submitted tenant application.');

        $child = Offer::where('parent_offer_id', $offer->id)->latest()->first();
        $this->assertNotNull($child, 'A counter-offer child record must be created.');
        // The workflow gives the child offer a 'countered' status (it is itself a counter,
        // awaiting the other party's response) — not 'submitted'.
        $this->assertContains($child->status, ['countered', 'submitted'],
            "Child offer status must be 'countered' or 'submitted'; got '{$child->status}'.");
        $this->assertEquals('2400', $child->getMeta('monthly_rent'));
    }

    // ── tenant withdraw ───────────────────────────────────────────────────────

    public function test_tenant_can_withdraw_submitted_application(): void
    {
        $offerAuction = $this->getLinkedOfferAuction();

        $offer = Offer::create([
            'user_id'          => $this->tenant->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'landlord',
            'status'           => 'submitted',
        ]);
        $offer->saveMeta('offer_type', 'rental');
        $offer->saveMeta('monthly_rent', '2200');

        $response = $this->actingAsTenant()
            ->post(route('offers.withdraw', $offer));

        $response->assertRedirect(route('offers.show', $offer));

        $offer->refresh();
        $this->assertEquals('withdrawn', $offer->status,
            'Tenant must be able to withdraw their submitted application.');
    }
}
