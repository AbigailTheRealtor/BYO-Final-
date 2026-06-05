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
            'financing_type'             => 'conventional',
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
            'meta_value' => 'conventional',
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
}
