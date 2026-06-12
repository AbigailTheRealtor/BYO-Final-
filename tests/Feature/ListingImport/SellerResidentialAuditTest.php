<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingImport\MlsListingImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Seller Residential Full-Stack Field Audit — Regression Tests (Task #2524)
 *
 * Covers:
 *   A. MLS parser emits property_type and garage_spaces from residential fixture
 *   B. target_closing_date save / load round trip
 *   C. occupant_status save / load round trip
 *   D. video_link renders on the public seller listing view
 *   E. furnished Apply Selected merges into building_features (not tenant_require)
 */
class SellerResidentialAuditTest extends TestCase
{
    use DatabaseTransactions;

    private MlsListingImportService $mlsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mlsService = app(MlsListingImportService::class);
    }

    // =========================================================================
    // A — MLS parser: property_type and garage_spaces
    // =========================================================================

    public function test_parser_emits_property_type_from_residential_fixture(): void
    {
        $rawText = file_get_contents(base_path('tests/fixtures/mls/residential.txt'));

        $result = $this->mlsService->import('', $rawText);

        $this->assertTrue($result['success'], 'Import failed: ' . ($result['error'] ?? ''));
        $this->assertArrayHasKey('property_type', $result['data'],
            'Parser must emit property_type canonical key from the residential fixture');
        $this->assertStringContainsStringIgnoringCase(
            'Single Family',
            $result['data']['property_type'],
            'property_type must contain "Single Family" from the fixture'
        );
    }

    public function test_parser_emits_garage_spaces_as_integer_from_residential_fixture(): void
    {
        $rawText = file_get_contents(base_path('tests/fixtures/mls/residential.txt'));

        $result = $this->mlsService->import('', $rawText);

        $this->assertTrue($result['success'], 'Import failed: ' . ($result['error'] ?? ''));
        $this->assertArrayHasKey('garage_spaces', $result['data'],
            'Parser must emit garage_spaces canonical key from the residential fixture');
        $this->assertSame(
            2,
            $result['data']['garage_spaces'],
            'garage_spaces must be emitted as integer 2 from the fixture'
        );
    }

    public function test_parser_emits_property_type_from_raw_text(): void
    {
        $rawText = 'Property Type: Single Family Residence  Bedrooms: 3  Bathrooms: 2';

        $result = $this->mlsService->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('property_type', $result['data']);
        $this->assertStringContainsStringIgnoringCase('Single Family', $result['data']['property_type']);
    }

    public function test_parser_emits_garage_spaces_as_integer_from_raw_text(): void
    {
        $rawText = 'Garage Spaces: 3  Garage: Yes  Bedrooms: 2';

        $result = $this->mlsService->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('garage_spaces', $result['data'],
            'Dedicated garage_spaces key must be emitted separately from the boolean garage key');
        $this->assertSame(3, $result['data']['garage_spaces']);
    }

    public function test_garage_spaces_does_not_bleed_into_garage_key(): void
    {
        $rawText = 'Garage Spaces: 2  Garage: Yes  Bedrooms: 3';

        $result = $this->mlsService->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('garage_spaces', $data);
        $this->assertSame(2, $data['garage_spaces'],
            'garage_spaces must be an integer, not the boolean-normalised garage value');

        if (isset($data['garage'])) {
            $this->assertNotSame(2, $data['garage'],
                'garage_spaces value must not contaminate the boolean garage key');
        }
    }

    public function test_property_type_maps_to_seller_livewire_property(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = 'Bedrooms: 3  Property Type: Condominium  Garage Spaces: 1';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError, 'Import must succeed: ' . $component->importError);

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');
        $this->assertArrayHasKey('property_type', $keyedPreview,
            'property_type must appear in importPreviewData for the seller role');

        $component->applyImportedFields(['property_type']);
        $this->assertEquals('Condominium', $component->property_type);
    }

    public function test_garage_spaces_maps_to_seller_livewire_property(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = 'Bedrooms: 3  Garage Spaces: 2  Garage: Yes';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError, 'Import must succeed: ' . $component->importError);

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');
        $this->assertArrayHasKey('garage_spaces', $keyedPreview,
            'garage_spaces must appear in importPreviewData for the seller role');

        $component->applyImportedFields(['garage_spaces']);
        $this->assertEquals('2', (string) $component->garage_parking_spaces,
            'garage_parking_spaces must be set to 2 after applying garage_spaces');
    }

    // =========================================================================
    // B — target_closing_date save / load round trip
    // =========================================================================

    public function test_target_closing_date_saved_and_loaded(): void
    {
        $user = User::factory()->create();

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Closing Date Test Listing',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        $auction->saveMeta('target_closing_date', '2025-12-31');
        $auction->saveMeta('workflow_type', 'offer_listing');

        $this->actingAs($user);
        Auth::login($user);

        $component = new SellerOfferListing();
        $component->loadDraft($auction->id);

        $this->assertEquals(
            '2025-12-31',
            $component->target_closing_date,
            'loadDraft() must populate target_closing_date from the saved meta value'
        );
    }

    public function test_target_closing_date_save_persists_via_savemeta(): void
    {
        $user = User::factory()->create();

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Target Closing Save Test',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        $auction->saveMeta('target_closing_date', '2026-03-15');

        $meta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'target_closing_date')
            ->first();

        $this->assertNotNull($meta,
            'A meta row with key target_closing_date must exist after saveMeta()');
        $this->assertEquals('2026-03-15', $meta->meta_value);
    }

    // =========================================================================
    // C — occupant_status save / load round trip
    // =========================================================================

    public function test_occupant_status_saved_and_loaded(): void
    {
        $user = User::factory()->create();

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Occupant Status Test Listing',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        $auction->saveMeta('occupant_status', 'Owner');
        $auction->saveMeta('workflow_type', 'offer_listing');

        $this->actingAs($user);
        Auth::login($user);

        $component = new SellerOfferListing();
        $component->loadDraft($auction->id);

        $this->assertEquals(
            'Owner',
            $component->occupant_status,
            'loadDraft() must populate occupant_status from the saved meta value'
        );
    }

    public function test_occupant_status_save_persists_via_savemeta(): void
    {
        $user = User::factory()->create();

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Occupant Save Test',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        $auction->saveMeta('occupant_status', 'Tenant');

        $meta = SellerAgentAuctionMeta::where('seller_agent_auction_id', $auction->id)
            ->where('meta_key', 'occupant_status')
            ->first();

        $this->assertNotNull($meta,
            'A meta row with key occupant_status must exist after saveMeta()');
        $this->assertEquals('Tenant', $meta->meta_value);
    }

    // =========================================================================
    // D — video_link renders on the public seller listing view
    // =========================================================================

    public function test_video_link_renders_on_seller_view_when_video_tour_url_absent(): void
    {
        $user = User::factory()->create();

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Video Link Render Test',
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '100 Test Blvd',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'linked_offer_auction_id',
            'meta_value'              => (string) $offerAuction->id,
        ]);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'video_link',
            'meta_value'              => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response = $this->actingAs($user)
            ->get(route('offer.listing.seller.view', $auction->id));

        $response->assertStatus(200);
        $response->assertSee('Photos &amp; Tours', false);
        $response->assertSee('dQw4w9WgXcQ', false);
    }

    public function test_video_link_embed_absent_when_no_video_meta(): void
    {
        $user = User::factory()->create();

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);

        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'No Video Test',
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '200 Test Ave',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'linked_offer_auction_id',
            'meta_value'              => (string) $offerAuction->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('offer.listing.seller.view', $auction->id));

        $response->assertStatus(200);
        // Neither video_link nor video_tour_url is set — no video embed should appear.
        $response->assertDontSee('iframe', false);
        $response->assertDontSee('youtube.com', false);
        $response->assertDontSee('vimeo.com', false);
    }

    // =========================================================================
    // E — furnished Apply Selected merges into building_features (not tenant_require)
    // =========================================================================

    public function test_furnished_applies_into_building_features_not_tenant_require(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = 'Bedrooms: 3  Furnished: Furnished';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError, 'Import must succeed: ' . $component->importError);

        $beforeBuildingFeatures = $component->building_features;
        $beforeTenantRequire    = $component->tenant_require;

        $component->applyImportedFields(['furnished']);

        $this->assertContains(
            'Furnished',
            $component->building_features,
            'Applying furnished=Furnished must push "Furnished" into building_features'
        );
        $this->assertEquals(
            $beforeTenantRequire,
            $component->tenant_require,
            'tenant_require must not be modified when applying furnished for the seller role'
        );
    }

    public function test_furnished_unfurnished_does_not_push_to_building_features(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = 'Bedrooms: 3  Furnished: Unfurnished';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError);

        $component->applyImportedFields(['furnished']);

        $this->assertNotContains(
            'Unfurnished',
            $component->building_features,
            '"Unfurnished" must NOT be pushed into building_features'
        );
        $this->assertNotContains(
            'unfurnished',
            $component->building_features,
            '"unfurnished" must NOT be pushed into building_features'
        );
    }

    public function test_furnished_turnkey_applies_into_building_features(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = 'Bedrooms: 3  Furnished: Turnkey';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError);

        $component->applyImportedFields(['furnished']);

        $this->assertContains(
            'Turnkey',
            $component->building_features,
            'Applying furnished=Turnkey must push "Turnkey" into building_features'
        );
    }

    public function test_furnished_apply_merges_not_replaces_existing_building_features(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component                   = new SellerOfferListing();
        $component->user_type        = 'seller';
        $component->building_features = ['Pool', 'Central Air'];

        $component->importRawText = 'Bedrooms: 3  Furnished: Furnished';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError);

        $component->applyImportedFields(['furnished']);

        $this->assertContains('Pool', $component->building_features,
            'Existing building_features must be preserved after applying furnished');
        $this->assertContains('Central Air', $component->building_features,
            'Existing building_features must be preserved after applying furnished');
        $this->assertContains('Furnished', $component->building_features,
            'Furnished must be merged in alongside existing building_features');
    }

    public function test_furnished_not_duplicated_when_already_in_building_features(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component                    = new SellerOfferListing();
        $component->user_type         = 'seller';
        $component->building_features = ['Furnished'];

        $component->importRawText = 'Bedrooms: 3  Furnished: Furnished';
        $component->importListingFromUrl();

        $this->assertEmpty($component->importError);

        $component->applyImportedFields(['furnished'], ['furnished']);

        $this->assertCount(
            1,
            array_filter($component->building_features, fn ($v) => $v === 'Furnished'),
            '"Furnished" must not be duplicated in building_features'
        );
    }
}
