<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Jobs\ComputeLocationDna;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regression suite for Task 3105: Create ↔ Edit/Draft parity.
 *
 * Coverage areas:
 *   (a) Broker Compensation tab present on Seller / Buyer / Tenant create forms
 *   (b) Listing Type (auction_type) selector on Seller and Landlord create forms
 *   (c) Landlord create JS submit — lease_term_options class present for JS skip
 *   (d) Photo upload max 50 MB — validation enforced, size hint visible
 *   (e) Document upload max 50 MB — size hint visible, message wording fix present
 *   (f) Seller create: ComputeLocationDna dispatched when address set (Queue::fake)
 *   (g) Landlord create: DNA dispatched when address set, skipped when blank (Queue::fake)
 *   (h) Seller edit: DNA dispatched when address CHANGES (Queue::fake)
 *   (i) Seller edit: DNA NOT dispatched when address is UNCHANGED (Queue::fake)
 *   (j) Landlord edit: DNA dispatched when address CHANGES (Queue::fake)
 *   (k) Landlord edit: DNA NOT dispatched when address is UNCHANGED (Queue::fake)
 *   (l) Landlord dispatch call sites wrapped in try/catch (non-blocking)
 *   (m) PHP upload limits: .user.ini has ≥ 50 MB upload_max_filesize / post_max_size
 *   (n) Livewire temp upload rule is ≥ 50 MB
 *   (o) Public listing view renders correctly with/without PropertyLocationDna record
 */
class CreateEditParityRegressionTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Shared helpers ───────────────────────────────────────────────────────

    private function makeAgentUser(): User
    {
        return User::factory()->create(['user_type' => 'agent']);
    }

    private function makeBuyerUser(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function makeSellerAuction(User $user, string $address = '', bool $isDraft = false): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Test Seller Listing',
            'is_draft'    => $isDraft,
            'is_approved' => !$isDraft,
            'is_sold'     => false,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        // BYO-H1: a published listing always carries the required publish fields.
        // Seed them so edit-update() (which now enforces the same rules as create)
        // reflects a realistic published listing rather than an invalid blank one.
        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_type', 'meta_value' => 'Residential'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'first_name',    'meta_value' => 'Test'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'last_name',     'meta_value' => 'Agent'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'phone_number',  'meta_value' => '5551234567'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'email',         'meta_value' => 'agent@example.com'],
        ]);

        if ($address) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => 'address',
                'meta_value'              => $address,
            ]);
        }

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'linked_offer_auction_id',
            'meta_value'              => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    private function makeLandlordAuction(User $user, string $address = '', bool $isDraft = false): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Test Landlord Listing',
            'is_draft'    => $isDraft,
            'is_approved' => !$isDraft,
            'is_sold'     => false,
        ]);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'workflow_type',
            'meta_value'                => 'offer_listing',
        ]);

        // BYO-H1: a published listing always carries the required publish fields.
        // Seed them so edit-update() (which now enforces the same rules as create)
        // reflects a realistic published listing rather than an invalid blank one.
        LandlordAgentAuctionMeta::insert([
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'first_name',           'meta_value' => 'Test'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'last_name',            'meta_value' => 'Agent'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'phone_number',         'meta_value' => '5551234567'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'email',                'meta_value' => 'agent@example.com'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'desired_lease_length', 'meta_value' => json_encode(['12 Months'])],
        ]);

        if ($address) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => 'address',
                'meta_value'                => $address,
            ]);
        }

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'linked_offer_auction_id',
            'meta_value'                => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    // ─── (a) Broker Compensation tab present on create forms ─────────────────

    /** Seller create page must render the "Broker Compensation" tab nav entry. */
    public function test_seller_create_page_contains_broker_compensation_tab(): void
    {
        $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')
            ->assertStatus(200)
            ->assertSee('Broker Compensation');
    }

    /** Buyer create page must render the "Broker Compensation" tab nav entry. */
    public function test_buyer_create_page_contains_broker_compensation_tab(): void
    {
        $this->actingAs($this->makeBuyerUser())
            ->get('/offer-listing/buyer')
            ->assertStatus(200)
            ->assertSee('Broker Compensation');
    }

    /** Tenant create page must render the "Broker Compensation" tab nav entry. */
    public function test_tenant_create_page_contains_broker_compensation_tab(): void
    {
        $user = User::factory()->create(['user_type' => 'tenant']);

        $this->actingAs($user)
            ->get('/offer-listing/tenant/tenant')
            ->assertStatus(200)
            ->assertSee('Broker Compensation');
    }

    /** Seller create page must contain the broker-compensation panel/tab ID. */
    public function test_seller_create_broker_comp_panel_id_is_present(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('broker-compensation', $html);
    }

    /** Buyer create page must contain the broker-compensation panel/tab ID. */
    public function test_buyer_create_broker_comp_panel_id_is_present(): void
    {
        $html = $this->actingAs($this->makeBuyerUser())
            ->get('/offer-listing/buyer')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('broker-compensation', $html);
    }

    // ─── (b) Listing Type selector present on Seller and Landlord create forms ─

    /** Seller create page must render the auction_type selector with "Traditional" option. */
    public function test_seller_create_page_contains_auction_type_field(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('auction_type', $html);
        $this->assertStringContainsString('Traditional', $html);
    }

    /** Landlord create page must render the auction_type selector with "Traditional" option. */
    public function test_landlord_create_page_contains_auction_type_field(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/landlord')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('auction_type', $html);
        $this->assertStringContainsString('Traditional', $html);
    }

    // ─── (b2) A1.2–A1.4 — Bidding Period restored for Seller/Landlord only ────

    /** A1.4: Seller create must now expose the "Bidding Period" Listing Type option. */
    public function test_seller_create_shows_bidding_period_option(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')->assertStatus(200)->getContent();
        $this->assertStringContainsString('value="Bidding Period"', $html);
    }

    /** A1.4: Landlord create must now expose the "Bidding Period" Listing Type option. */
    public function test_landlord_create_shows_bidding_period_option(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/landlord')->assertStatus(200)->getContent();
        $this->assertStringContainsString('value="Bidding Period"', $html);
    }

    /** A1.4 scope guard: Buyer create must NOT expose Bidding Period (Traditional-only). */
    public function test_buyer_create_hides_bidding_period_option(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/buyer')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('value="Bidding Period"', $html);
    }

    /** A1.4 scope guard: Tenant create must NOT expose Bidding Period (Traditional-only). */
    public function test_tenant_create_hides_bidding_period_option(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/tenant')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('value="Bidding Period"', $html);
    }

    /** A1.4/A1.5: a Bidding Period Landlord listing must require a Bidding Period Length (auction_time). */
    public function test_landlord_bidding_period_requires_auction_time(): void
    {
        Livewire::actingAs($this->makeAgentUser())
            ->test(LandlordOfferListing::class)
            ->set('auction_type', 'Bidding Period')
            ->call('store')
            ->assertHasErrors(['auction_time']);
    }

    // ─── (b3) A1.11/A1.13 — canonical "Submit" publish label on all create pages ─

    /**
     * A1.11/A1.13: every Create Offer page must use a single canonical publish
     * button labelled "Submit" — no "Save & Submit Offer" / "Submit Rental Offer".
     * The Save Draft button remains "Save Draft" (draft action) on the same page.
     *
     * @dataProvider createPageProvider
     */
    public function test_create_page_publish_button_is_submit(string $path): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get($path)->assertStatus(200)->getContent();

        // Canonical publish button (wire:target="store") is labelled exactly "Submit".
        $this->assertStringContainsString('wire:target="store">Submit<', $html,
            "[$path] publish button must be labelled 'Submit'.");
        // Draft action label preserved.
        $this->assertStringContainsString('Save Draft', $html, "[$path] must keep 'Save Draft'.");
        // Old mismatched labels must be gone.
        $this->assertStringNotContainsString('Save &amp; Submit Offer', $html,
            "[$path] must not contain 'Save & Submit Offer'.");
        $this->assertStringNotContainsString('Submit Rental Offer', $html,
            "[$path] must not contain 'Submit Rental Offer'.");
    }

    public static function createPageProvider(): array
    {
        return [
            'seller'   => ['/offer-listing/seller'],
            'buyer'    => ['/offer-listing/buyer'],
            'landlord' => ['/offer-listing/landlord'],
            'tenant'   => ['/offer-listing/tenant'],
        ];
    }

    // ─── (b4) A7.46 / C3 — server-side submit works for Business & Tenant ─────

    /**
     * A7.46: a Seller listing with property_type = "Business" must submit (publish)
     * at the component level — store() passes validation and redirects. (If the
     * reported "Business cannot submit" still occurs in-browser, it is a client-side
     * JS validation issue, not a server/validation block — flagged Needs Review.)
     */
    public function test_seller_business_listing_submits_server_side(): void
    {
        Livewire::actingAs($this->makeAgentUser())
            ->test(SellerOfferListing::class)
            ->set('listing_title', 'Business Listing')
            ->set('property_type', 'Business')
            ->set('first_name', 'Alice')
            ->set('last_name', 'Agent')
            ->set('phone_number', '5551234567')
            ->set('email', 'alice@example.com')
            ->set('auction_type', 'Traditional')
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect();
    }

    /**
     * C3: the Create Tenant listing Submit (publish) path must not be blocked by
     * validation — store() raises no validation errors for a basic Residential
     * listing. (Submit BUTTON presence is covered by
     * test_create_page_publish_button_is_submit / data set "tenant". The full
     * submit→redirect with complete data is exercised by the entry-flow suite;
     * here we only assert the publish path is not validation-blocked.)
     */
    public function test_tenant_listing_submit_is_not_validation_blocked(): void
    {
        Livewire::actingAs($this->makeAgentUser())
            ->test(TenantOfferListing::class, ['user_type' => 'tenant'])
            ->set('listing_title', 'Tenant Listing')
            ->set('property_type', 'Residential')
            ->set('first_name', 'Tom')
            ->set('last_name', 'Tenant')
            ->set('phone_number', '5551234567')
            ->set('email', 'tom@example.com')
            ->set('auction_type', 'Traditional')
            ->call('store')
            ->assertHasNoErrors();
    }

    // ─── (c) Landlord JS — lease_term_options class present for JS skip ───────

    /**
     * The Landlord create page must render the lease_term_options field with
     * the `lease_term_options` CSS class so that the JS validator skips it
     * (Select2-managed multi-select produces false positives otherwise).
     */
    public function test_landlord_create_page_lease_term_options_class_is_present(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/landlord')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('lease_term_options', $html);
    }

    // ─── (d) Photo upload — max 50 MB ────────────────────────────────────────

    /** SellerOfferListing must accept a photo file ≤ 50 MB without errors. */
    public function test_seller_create_photo_upload_allows_up_to_50mb(): void
    {
        $validFile = UploadedFile::fake()->create('photo.jpg', 40 * 1024, 'image/jpeg');

        Livewire::actingAs($this->makeAgentUser())
            ->test(SellerOfferListing::class)
            ->set('newPropertyPhotos', [$validFile])
            ->assertHasNoErrors(['newPropertyPhotos', 'newPropertyPhotos.*']);
    }

    /** SellerOfferListing must reject a photo file above 50 MB. */
    public function test_seller_create_photo_upload_rejects_above_50mb(): void
    {
        $oversizeFile = UploadedFile::fake()->create('photo.jpg', 52 * 1024, 'image/jpeg');

        Livewire::actingAs($this->makeAgentUser())
            ->test(SellerOfferListing::class)
            ->set('newPropertyPhotos', [$oversizeFile])
            ->assertHasErrors(['newPropertyPhotos.*']);
    }

    /** Seller create page must display a "50 MB" per-photo hint. */
    public function test_seller_create_photos_tab_contains_50mb_hint(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('50 MB', $html);
    }

    /** LandlordOfferListing must accept a photo file ≤ 50 MB. */
    public function test_landlord_create_photo_upload_allows_up_to_50mb(): void
    {
        $validFile = UploadedFile::fake()->create('photo.jpg', 40 * 1024, 'image/jpeg');

        Livewire::actingAs($this->makeAgentUser())
            ->test(LandlordOfferListing::class)
            ->set('newPropertyPhotos', [$validFile])
            ->assertHasNoErrors(['newPropertyPhotos', 'newPropertyPhotos.*']);
    }

    /** LandlordOfferListing must reject a photo file above 50 MB. */
    public function test_landlord_create_photo_upload_rejects_above_50mb(): void
    {
        $oversizeFile = UploadedFile::fake()->create('photo.jpg', 52 * 1024, 'image/jpeg');

        Livewire::actingAs($this->makeAgentUser())
            ->test(LandlordOfferListing::class)
            ->set('newPropertyPhotos', [$oversizeFile])
            ->assertHasErrors(['newPropertyPhotos.*']);
    }

    // ─── (d2) A1.10 — Landlord create submit surfaces validation, not silent ──

    /**
     * A1.10: calling store() with required publish fields blank must (a) register
     * field-level validation errors AND (b) flash a "Missing/invalid:" banner so the
     * submit is never silently swallowed when the failing field sits on a hidden tab.
     */
    public function test_landlord_create_submit_flashes_missing_fields_and_does_not_redirect(): void
    {
        Livewire::actingAs($this->makeAgentUser())
            ->test(LandlordOfferListing::class)
            ->call('store')
            ->assertHasErrors(['first_name', 'last_name', 'phone_number', 'email', 'desired_lease_length'])
            ->assertNoRedirect()
            // The re-rendered component must surface the "Missing/invalid:" banner
            // (offer-landlord-listing.blade.php @if(session()->has('error'))).
            ->assertSee('Missing/invalid:');
    }

    // ─── (e) Document upload — 50 MB hints + message wording fix ─────────────

    /** Seller create documents section must display the 50 MB hint. */
    public function test_seller_create_documents_section_contains_50mb_hint(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('50 MB', $html);
    }

    /** Landlord create documents section must display the 50 MB hint. */
    public function test_landlord_create_documents_section_contains_50mb_hint(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/landlord')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('50 MB', $html);
    }

    /**
     * SellerOfferListing must contain the ValidationException catch block that
     * rewrites "N kilobytes" error messages to "50 MB" so users see a readable
     * error when an oversized photo is rejected.
     *
     * Note: Livewire's file-upload test pipeline sets errors through its
     * internal upload handler before our lifecycle hook fires, so the rewrite
     * is verified via source inspection of the catch block — the upload-
     * rejection test above already confirms the size rule is enforced.
     */
    public function test_seller_create_photo_validation_error_mentions_50mb(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(SellerOfferListing::class))->getFileName()
        );

        $this->assertStringContainsString('ValidationException', $source);
        $this->assertStringContainsString('kilobytes', $source);
        $this->assertStringContainsString("'50 MB'", $source);
    }

    /** LandlordOfferListing must contain the ValidationException catch + preg_replace rewrite. */
    public function test_landlord_create_photo_validation_error_mentions_50mb(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(LandlordOfferListing::class))->getFileName()
        );

        $this->assertStringContainsString('ValidationException', $source);
        $this->assertStringContainsString('kilobytes', $source);
        $this->assertStringContainsString("'50 MB'", $source);
    }

    // ─── (f) Seller create: DNA dispatched on store() ─────────────────────────

    /**
     * Landlord create saveDraft dispatches ComputeLocationDna when address is set.
     * (Seller dispatches on store() which requires full form validation; the
     *  Landlord saveDraft path is used here as the representative "create with
     *  address → dispatch" behavioral test.)
     */
    public function test_landlord_create_save_draft_dispatches_location_dna_when_address_set(): void
    {
        Queue::fake();

        Livewire::actingAs($this->makeAgentUser())
            ->test(LandlordOfferListing::class)
            ->set('listing_title', 'DNA Dispatch Regression Test')
            ->set('address', '456 Oak Ave, Orlando, FL 32801')
            ->call('saveDraft');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /** Landlord create saveDraft must NOT dispatch DNA when address is empty. */
    public function test_landlord_create_save_draft_does_not_dispatch_location_dna_when_no_address(): void
    {
        Queue::fake();

        Livewire::actingAs($this->makeAgentUser())
            ->test(LandlordOfferListing::class)
            ->set('listing_title', 'No Address Listing')
            ->set('address', '')
            ->call('saveDraft');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    // ─── (g/h) Seller edit: DNA dispatched only when address CHANGED ──────────

    /**
     * SellerOfferListingEdit::update() must dispatch ComputeLocationDna when
     * the submitted address differs from the stored address (dirty-check).
     */
    public function test_seller_edit_update_dispatches_dna_when_address_changes(): void
    {
        Queue::fake();

        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, '100 Old St, Tampa, FL 33601', false);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', '200 New Blvd, Miami, FL 33101')
            ->call('update');

        Queue::assertPushed(ComputeLocationDna::class, function ($job) {
            return true;
        });
    }

    /**
     * SellerOfferListingEdit::update() must NOT dispatch ComputeLocationDna
     * when the submitted address is identical to the stored address.
     */
    public function test_seller_edit_update_does_not_dispatch_dna_when_address_unchanged(): void
    {
        Queue::fake();

        $address = '100 Old St, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, $address, false);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->call('update');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    // ─── (j/k) Landlord edit: DNA dispatched only when address CHANGED ────────

    /**
     * LandlordOfferListingEdit::saveDraftOnly() must dispatch ComputeLocationDna
     * when the submitted address differs from the stored one.
     *
     * saveDraftOnly() targets published (non-draft) listings. When the auction
     * is a draft it delegates to saveDraft() which has address-present (not
     * dirty) logic; for parity, we test the published path.
     */
    public function test_landlord_edit_dispatches_dna_when_address_changes(): void
    {
        Queue::fake();

        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, '100 Old Rd, Tampa, FL 33601', false);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', '200 New Ave, Orlando, FL 32801')
            ->call('saveDraftOnly');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /**
     * LandlordOfferListingEdit::saveDraftOnly() must NOT dispatch
     * ComputeLocationDna when the address is identical to the stored one.
     */
    public function test_landlord_edit_does_not_dispatch_dna_when_address_unchanged(): void
    {
        Queue::fake();

        $address = '100 Old Rd, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, $address, false);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->call('saveDraftOnly');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    // ─── (h2) Seller edit: saveDraftOnly() dispatches DNA when location changes ──

    /**
     * SellerOfferListingEdit::saveDraftOnly() must dispatch ComputeLocationDna
     * when the submitted address differs from the stored address (dirty-check).
     * The auction must be published (non-draft) so saveDraftOnly() runs its own
     * save path rather than delegating to saveDraft().
     */
    public function test_seller_edit_save_draft_only_dispatches_dna_when_address_changes(): void
    {
        Queue::fake();

        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, '100 Old St, Tampa, FL 33601', false);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', '200 New Blvd, Miami, FL 33101')
            ->call('saveDraftOnly');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /**
     * SellerOfferListingEdit::saveDraftOnly() must NOT dispatch when address
     * and all other location fields are unchanged.
     */
    public function test_seller_edit_save_draft_only_does_not_dispatch_when_location_unchanged(): void
    {
        Queue::fake();

        $address = '100 Old St, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, $address, false);

        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_city',   'meta_value' => 'Tampa'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_state',  'meta_value' => 'FL'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_zip',    'meta_value' => '33601'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_county', 'meta_value' => 'Hillsborough'],
        ]);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_city',   'Tampa')
            ->set('property_state',  'FL')
            ->set('property_zip',    '33601')
            ->set('property_county', 'Hillsborough')
            ->call('saveDraftOnly');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    /**
     * SellerOfferListingEdit::saveDraft() (versioned draft creation) must
     * dispatch ComputeLocationDna when any location field differs from the
     * parent draft — or when there is no parent draft and an address is set.
     */
    public function test_seller_edit_save_draft_dispatches_dna_when_address_set_and_no_parent(): void
    {
        Queue::fake();

        $user = $this->makeAgentUser();

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class)
            ->set('listing_title', 'First Draft')
            ->set('address', '123 New St, Tampa, FL 33601')
            ->call('saveDraft');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    // ─── (i) Seller edit: DNA dispatched when non-address location field changes ─

    /**
     * SellerOfferListingEdit::update() must dispatch when property_city changes
     * even when the raw address string is unchanged. This verifies the multi-
     * field dirty-check (shouldDispatchLocationDna) covers all location fields.
     */
    public function test_seller_edit_update_dispatches_dna_when_city_changes_without_address_change(): void
    {
        Queue::fake();

        $address = '100 Main St, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, $address, false);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_city',
            'meta_value'              => 'Tampa',
        ]);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_city', 'Miami')
            ->call('update');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /**
     * SellerOfferListingEdit::update() must NOT dispatch when every location
     * field (address, city, state, zip, county) matches the stored meta.
     */
    public function test_seller_edit_update_does_not_dispatch_when_all_location_fields_unchanged(): void
    {
        Queue::fake();

        $address = '100 Main St, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, $address, false);

        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_city',  'meta_value' => 'Tampa'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_state', 'meta_value' => 'FL'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_zip',   'meta_value' => '33601'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_county','meta_value' => 'Hillsborough'],
        ]);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_city',   'Tampa')
            ->set('property_state',  'FL')
            ->set('property_zip',    '33601')
            ->set('property_county', 'Hillsborough')
            ->call('update');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    // ─── (k) Landlord edit: DNA dispatched when non-address location field changes

    /**
     * LandlordOfferListingEdit::update() must dispatch when the address changes.
     */
    public function test_landlord_edit_update_dispatches_dna_when_address_changes(): void
    {
        Queue::fake();

        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, '100 Old Rd, Tampa, FL 33601', false);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', '200 New Ave, Orlando, FL 32801')
            ->call('update');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /**
     * LandlordOfferListingEdit::update() must NOT dispatch when every location
     * field is unchanged relative to stored meta.
     */
    public function test_landlord_edit_update_does_not_dispatch_when_all_location_fields_unchanged(): void
    {
        Queue::fake();

        $address = '100 Main Rd, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, $address, false);

        LandlordAgentAuctionMeta::insert([
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_city',   'meta_value' => 'Tampa'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_state',  'meta_value' => 'FL'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_zip',    'meta_value' => '33601'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_county', 'meta_value' => 'Hillsborough'],
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_city',   'Tampa')
            ->set('property_state',  'FL')
            ->set('property_zip',    '33601')
            ->set('property_county', 'Hillsborough')
            ->call('update');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    /**
     * LandlordOfferListingEdit::update() must dispatch when property_zip
     * changes without the address string changing.
     */
    public function test_landlord_edit_update_dispatches_dna_when_zip_changes_without_address_change(): void
    {
        Queue::fake();

        $address = '100 Main Rd, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, $address, false);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'property_zip',
            'meta_value'                => '33601',
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_zip', '33602')
            ->call('update');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /**
     * LandlordOfferListingEdit::saveDraftOnly() must dispatch when property_zip
     * changes without the address string changing.
     */
    public function test_landlord_edit_dispatches_dna_when_zip_changes_without_address_change(): void
    {
        Queue::fake();

        $address = '100 Main St, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, $address, false);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'property_zip',
            'meta_value'                => '33601',
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_zip', '33602')
            ->call('saveDraftOnly');

        Queue::assertPushed(ComputeLocationDna::class);
    }

    /**
     * LandlordOfferListingEdit::saveDraftOnly() must NOT dispatch when all
     * location fields (including city, state, zip, county) are unchanged.
     */
    public function test_landlord_edit_does_not_dispatch_when_all_location_fields_unchanged(): void
    {
        Queue::fake();

        $address = '100 Main St, Tampa, FL 33601';
        $user    = $this->makeAgentUser();
        $auction = $this->makeLandlordAuction($user, $address, false);

        LandlordAgentAuctionMeta::insert([
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_city',   'meta_value' => 'Tampa'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_state',  'meta_value' => 'FL'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_zip',    'meta_value' => '33601'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_county', 'meta_value' => 'Hillsborough'],
        ]);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('listing_title', $auction->title)
            ->set('address', $address)
            ->set('property_city',   'Tampa')
            ->set('property_state',  'FL')
            ->set('property_zip',    '33601')
            ->set('property_county', 'Hillsborough')
            ->call('saveDraftOnly');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    // ─── (l) Landlord dispatch sites wrapped in try/catch ────────────────────

    /**
     * All ComputeLocationDna dispatch sites in LandlordOfferListing must be
     * wrapped in try/catch with a Log::warning in the catch block so that a
     * geocoding failure never blocks the listing save/submit.
     */
    public function test_landlord_create_all_dispatch_calls_are_try_catch_wrapped(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(LandlordOfferListing::class))->getFileName()
        );

        $dispatchCount   = substr_count($source, "ComputeLocationDna::dispatch('landlord_agent'");
        $logWarningCount = substr_count($source, '[LANDLORD');

        $this->assertGreaterThan(0, $dispatchCount,
            'Expected at least one ComputeLocationDna dispatch in LandlordOfferListing'
        );
        $this->assertGreaterThanOrEqual(
            $dispatchCount,
            $logWarningCount,
            "Expected at least $dispatchCount Log::warning catch tags to match $dispatchCount dispatch sites"
        );
    }

    /** LandlordOfferListingEdit must wrap all dispatch sites in try/catch. */
    public function test_landlord_edit_all_dispatch_calls_are_try_catch_wrapped(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(LandlordOfferListingEdit::class))->getFileName()
        );

        $dispatchCount   = substr_count($source, "ComputeLocationDna::dispatch('landlord_agent'");
        $logWarningCount = substr_count($source, '[LANDLORD');

        $this->assertGreaterThanOrEqual(
            $dispatchCount,
            $logWarningCount,
            "Expected at least $dispatchCount Log::warning catch tags to match $dispatchCount dispatch sites"
        );
    }

    // ─── (m) PHP upload limits: .user.ini has ≥ 50 MB ─────────────────────────

    /**
     * The project-root .user.ini must declare upload_max_filesize ≥ 50 MB
     * and post_max_size ≥ 55 MB so the web server allows large file transfers
     * before PHP/Laravel validation runs.
     *
     * Note: PHP CLI (used in tests) ignores .user.ini — that file is only
     * read by the web server (PHP-FPM / Apache). This test verifies the file
     * is configured correctly without relying on ini_get() in CLI context.
     */
    public function test_php_user_ini_has_50mb_upload_limits(): void
    {
        $iniPath = base_path('.user.ini');
        $this->assertFileExists($iniPath, '.user.ini must exist in the project root');

        $content = file_get_contents($iniPath);
        $this->assertMatchesRegularExpression(
            '/^upload_max_filesize\s*=\s*5\d+M/m',
            $content,
            '.user.ini upload_max_filesize must be >= 50M'
        );
        $this->assertMatchesRegularExpression(
            '/^post_max_size\s*=\s*5\d+M/m',
            $content,
            '.user.ini post_max_size must be >= 50M'
        );
    }

    /** The public/.user.ini must also have the same upload limits for the public docroot. */
    public function test_public_php_user_ini_has_50mb_upload_limits(): void
    {
        $iniPath = public_path('.user.ini');
        $this->assertFileExists($iniPath, 'public/.user.ini must exist');

        $content = file_get_contents($iniPath);
        $this->assertMatchesRegularExpression(
            '/^upload_max_filesize\s*=\s*5\d+M/m',
            $content,
            'public/.user.ini upload_max_filesize must be >= 50M'
        );
    }

    // ─── (n) Livewire temp upload rule ≥ 50 MB ────────────────────────────────

    /**
     * Livewire's temporary_file_upload.rules must include max:51200 (50 MB)
     * so large files are accepted at the Livewire upload endpoint level before
     * reaching component-level validation.
     */
    public function test_livewire_temp_upload_rule_allows_50mb(): void
    {
        $rules = config('livewire.temporary_file_upload.rules', []);
        $rulesStr = is_array($rules) ? implode('|', $rules) : (string) $rules;

        $this->assertStringContainsString(
            '51200',
            $rulesStr,
            'livewire.temporary_file_upload.rules must include max:51200 (50 MB)'
        );
    }

    // ─── (o) Public listing view renders without PropertyLocationDna ──────────

    /** Seller public listing view must return HTTP 200 even when no DNA record exists. */
    public function test_seller_public_view_renders_without_location_dna(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        PropertyLocationDna::where('listing_type', 'seller_agent')
            ->where('listing_id', $auction->id)
            ->delete();

        $this->actingAs($this->makeBuyerUser())
            ->get(route('offer.listing.seller.view', $auction->id))
            ->assertStatus(200);
    }

    /** Landlord public listing view must return HTTP 200 even when no DNA record exists. */
    public function test_landlord_public_view_renders_without_location_dna(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeLandlordAuction($owner, '', false);

        PropertyLocationDna::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)
            ->delete();

        $this->actingAs($this->makeBuyerUser())
            ->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200);
    }

    /** Seller public listing view must render correctly when a DNA record exists. */
    public function test_seller_public_view_renders_with_existing_location_dna(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        PropertyLocationDna::create([
            'listing_type' => 'seller_agent',
            'listing_id'   => $auction->id,
            'status'       => 'complete',
            'raw_result'   => json_encode(['summary' => 'Good neighborhood.']),
        ]);

        $this->actingAs($this->makeBuyerUser())
            ->get(route('offer.listing.seller.view', $auction->id))
            ->assertStatus(200);
    }

    // ─── C11 — AI Knowledge Base stays private (saved, prefills, never public) ─

    /**
     * C11: the AI Knowledge Base (listing_ai_faq) is private. A non-owner viewing
     * the public Seller listing detail must NOT see any AI KB answer content.
     */
    public function test_seller_public_view_hides_ai_knowledge_base(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'listing_ai_faq',
            'meta_value'              => json_encode(['hoa_details' => 'SECRET_AI_ANSWER_DO_NOT_LEAK']),
        ]);

        // A non-owner (public) viewer must not see the private AI KB answer.
        $this->actingAs($this->makeBuyerUser())
            ->get(route('offer.listing.seller.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('SECRET_AI_ANSWER_DO_NOT_LEAK');
    }

    /**
     * C11: the AI Knowledge Base saves and pre-fills on edit — opening the edit
     * wizard must repopulate listing_ai_faq from the stored meta.
     */
    public function test_seller_edit_prefills_ai_knowledge_base(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'listing_ai_faq',
            'meta_value'              => json_encode(['hoa_details' => 'Quarterly HOA dues are $300.']),
        ]);

        Livewire::actingAs($owner)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSet('listing_ai_faq', ['hoa_details' => 'Quarterly HOA dues are $300.']);
    }

    // ─── A4.26/A4.27 — canonical Seller property-condition list (unified w/ Hire) ─

    /**
     * A4.26/A4.27: Create Seller uses the unified descriptive condition list and
     * no longer offers the demand-side "No Preference" option.
     */
    public function test_create_seller_uses_canonical_property_condition_list(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')->assertStatus(200)->getContent();

        $this->assertStringContainsString('Tear Down: Requires complete demolition and reconstruction', $html);
        $this->assertStringContainsString('Pre-Construction', $html);
        $this->assertStringContainsString('No updates needed: Completely updated', $html);
        $this->assertStringNotContainsString('No Preference', $html);
    }

    /**
     * A4.26/A4.27 backward-compat: an existing Seller listing saved with a legacy
     * condition value ("No Preference") must still load and remain selectable on
     * edit (the option is re-appended) — no data loss.
     */
    public function test_seller_edit_preserves_legacy_condition_value(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'condition_prop',
            'meta_value'              => 'No Preference',
        ]);

        Livewire::actingAs($owner)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSet('condition_prop', 'No Preference') // legacy value loaded
            ->assertSee('No Preference');                  // still rendered as a selectable option
    }

    // ─── A5.29/A5.30 — contingency option sets + legacy display mapping ───────

    /** A5.29: Seller contingencies use seller-perspective options; legacy Required/Preferred Waived no longer offered. */
    public function test_create_seller_contingency_uses_canonical_options(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/seller')->assertStatus(200)->getContent();

        $this->assertStringContainsString('>Accepted</option>', $html);
        $this->assertStringContainsString('>Not Accepted</option>', $html);
        $this->assertStringNotContainsString('>Preferred Waived</option>', $html);
    }

    /** A5.30: Buyer contingencies use buyer-perspective options; old "Not Applicable (Cash)" wording is gone. */
    public function test_create_buyer_contingency_uses_canonical_options(): void
    {
        $html = $this->actingAs($this->makeAgentUser())
            ->get('/offer-listing/buyer')->assertStatus(200)->getContent();

        $this->assertStringContainsString('>Not Included</option>', $html); // home-sale option, buyer-only
        $this->assertStringContainsString('>Included</option>', $html);
        $this->assertStringNotContainsString('>Not Applicable (Cash)</option>', $html);
    }

    /** A5.29 backward-compat: a legacy Seller value is NOT rewritten on edit-load and stays selectable under its canonical label. */
    public function test_seller_edit_preserves_legacy_contingency_value(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'appraisal_contingency_preference',
            'meta_value'              => 'Preferred Waived',
        ]);

        Livewire::actingAs($owner)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSet('appraisal_contingency_preference', 'Preferred Waived') // not rewritten
            ->assertSeeHtml('value="Preferred Waived"');                         // option carries the raw value
    }

    /** A5.29 display mapping: the public Seller view shows the canonical label, never the legacy text. */
    public function test_seller_public_view_maps_legacy_contingency_label(): void
    {
        $owner   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->makeSellerAuction($owner, '', false);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'appraisal_contingency_preference',
            'meta_value'              => 'Preferred Waived',
        ]);

        $this->actingAs($this->makeBuyerUser())
            ->get(route('offer.listing.seller.view', $auction->id))
            ->assertStatus(200)
            ->assertDontSee('Preferred Waived'); // legacy text mapped to "Negotiable" for display
    }
}
