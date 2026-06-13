<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Round-trip tests for the five new MLS fields on the Seller components.
 *
 * Covers: waterfront, water_access, water_view, interior_features, flood_zone_date
 *
 * Each test seeds meta values directly in the DB, mounts the component,
 * calls loadDraft / loadAuctionData, and asserts the properties are hydrated.
 * The save-path test uses ReflectionMethod to call saveAllMetadata and then
 * verifies the meta rows were written.
 */
class SellerMlsFieldRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    private function makeAuction(array $meta = []): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Test Seller Listing',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        foreach ($meta as $key => $value) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => $key,
                'meta_value'              => (string) $value,
            ]);
        }

        return $auction;
    }

    // ── SellerOfferListing (Create) — load path ───────────────────────────────

    public function test_seller_create_loads_flood_zone_date(): void
    {
        $auction = $this->makeAuction(['flood_zone_date' => '2022-05-10']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('flood_zone_date', '2022-05-10');
    }

    public function test_seller_create_loads_waterfront(): void
    {
        $auction = $this->makeAuction(['waterfront' => 'Yes']);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('waterfront', 'Yes');
    }

    public function test_seller_create_loads_water_access(): void
    {
        $auction = $this->makeAuction(['water_access' => json_encode(['Lake', 'Canal - Freshwater'])]);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('water_access', ['Lake', 'Canal - Freshwater']);
    }

    public function test_seller_create_loads_water_view(): void
    {
        $auction = $this->makeAuction(['water_view' => json_encode(['Gulf/Ocean - Full', 'Lake'])]);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('water_view', ['Gulf/Ocean - Full', 'Lake']);
    }

    public function test_seller_create_loads_interior_features(): void
    {
        $auction = $this->makeAuction(['interior_features' => json_encode(['High Ceilings', 'Crown Molding'])]);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('interior_features', ['High Ceilings', 'Crown Molding']);
    }

    // ── SellerOfferListing (Create) — save path ───────────────────────────────

    public function test_seller_create_saves_all_five_new_fields(): void
    {
        $auction = SellerAgentAuction::create([
            'user_id'  => $this->user->id,
            'title'    => 'Save Test',
            'is_draft' => true,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class);

        $liveComponent = $component->instance();
        $liveComponent->flood_zone_date     = '2021-03-01';
        $liveComponent->waterfront          = 'No';
        $liveComponent->water_access        = ['River', 'Bayou'];
        $liveComponent->water_view          = ['River'];
        $liveComponent->interior_features   = ['Fireplace', 'Walk-In Closet(s)'];

        $method = new ReflectionMethod(SellerOfferListing::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        $this->assertEquals('2021-03-01', $auction->info('flood_zone_date'));
        $this->assertEquals('No',         $auction->info('waterfront'));
        $this->assertEquals(['River', 'Bayou'],                json_decode($auction->info('water_access'), true));
        $this->assertEquals(['River'],                         json_decode($auction->info('water_view'), true));
        $this->assertEquals(['Fireplace', 'Walk-In Closet(s)'], json_decode($auction->info('interior_features'), true));
    }

    // ── SellerOfferListingEdit — load path ────────────────────────────────────

    public function test_seller_edit_loads_flood_zone_date(): void
    {
        $auction = $this->makeAuction([
            'flood_zone_date' => '2019-11-20',
            'is_draft'        => '0',
        ]);
        $auction->is_draft    = false;
        $auction->is_approved = true;
        $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('flood_zone_date', '2019-11-20');
    }

    public function test_seller_edit_loads_waterfront(): void
    {
        $auction = $this->makeAuction(['waterfront' => 'Yes']);
        $auction->is_draft = false; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('waterfront', 'Yes');
    }

    public function test_seller_edit_loads_water_access(): void
    {
        $auction = $this->makeAuction(['water_access' => json_encode(['Gulf/Ocean', 'Beach'])]);
        $auction->is_draft = false; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('water_access', ['Gulf/Ocean', 'Beach']);
    }

    public function test_seller_edit_loads_water_view(): void
    {
        $auction = $this->makeAuction(['water_view' => json_encode(['Canal'])]);
        $auction->is_draft = false; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('water_view', ['Canal']);
    }

    public function test_seller_edit_loads_interior_features(): void
    {
        $auction = $this->makeAuction(['interior_features' => json_encode(['Open Floorplan'])]);
        $auction->is_draft = false; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('interior_features', ['Open Floorplan']);
    }

    // ── applyImportedFields — field application on Seller ────────────────────

    /** Apply waterfront (scalar) to an empty Seller component. */
    public function test_apply_sets_waterfront_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->waterfront = '';
        $component->importPreviewData = [[
            'canonical_key' => 'waterfront', 'prop_name' => 'waterfront',
            'label' => 'Waterfront', 'value' => 'no',
            'is_array_prop' => false, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['waterfront'], []);
        $this->assertEquals('no', $component->waterfront);
    }

    /** Apply water_view (array) to an empty Seller component. */
    public function test_apply_sets_water_view_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->water_view = [];
        $component->importPreviewData = [[
            'canonical_key' => 'water_view', 'prop_name' => 'water_view',
            'label' => 'Water View', 'value' => 'Lake,Gulf/Ocean - Full',
            'is_array_prop' => true, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['water_view'], []);
        $this->assertEquals(['Lake', 'Gulf/Ocean - Full'], $component->water_view);
    }

    /** Apply interior_features (array) to an empty Seller component. */
    public function test_apply_sets_interior_features_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->interior_features = [];
        $component->importPreviewData = [[
            'canonical_key' => 'interior_features', 'prop_name' => 'interior_features',
            'label' => 'Interior Features', 'value' => 'Ceiling Fans(s),Crown Molding,Walk-In Closet(s)',
            'is_array_prop' => true, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['interior_features'], []);
        $this->assertEquals(['Ceiling Fans(s)', 'Crown Molding', 'Walk-In Closet(s)'], $component->interior_features);
    }

    /** Apply appliances (array) to an empty Seller component. */
    public function test_apply_sets_appliances_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->appliances = [];
        $component->importPreviewData = [[
            'canonical_key' => 'appliances', 'prop_name' => 'appliances',
            'label' => 'Appliances', 'value' => 'Dishwasher,Microwave,Refrigerator',
            'is_array_prop' => true, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['appliances'], []);
        $this->assertEquals(['Dishwasher', 'Microwave', 'Refrigerator'], $component->appliances);
    }

    /** Apply flood_zone_code (scalar) to an empty Seller component. */
    public function test_apply_sets_flood_zone_code_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->flood_zone_code = '';
        $component->importPreviewData = [[
            'canonical_key' => 'flood_zone_code', 'prop_name' => 'flood_zone_code',
            'label' => 'Flood Zone Code', 'value' => 'X',
            'is_array_prop' => false, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['flood_zone_code'], []);
        $this->assertEquals('X', $component->flood_zone_code);
    }

    /** Apply lot_dimensions (scalar) to an empty Seller component. */
    public function test_apply_sets_lot_dimensions_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->lot_dimensions = '';
        $component->importPreviewData = [[
            'canonical_key' => 'lot_dimensions', 'prop_name' => 'lot_dimensions',
            'label' => 'Lot Dimensions', 'value' => '80x120',
            'is_array_prop' => false, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['lot_dimensions'], []);
        $this->assertEquals('80x120', $component->lot_dimensions);
    }

    /** Apply roof_type (array) to an empty Seller component. */
    public function test_apply_sets_roof_type_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->roof_type = [];
        $component->importPreviewData = [[
            'canonical_key' => 'roof_type', 'prop_name' => 'roof_type',
            'label' => 'Roof Type', 'value' => 'Shingle,Tile',
            'is_array_prop' => true, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['roof_type'], []);
        $this->assertEquals(['Shingle', 'Tile'], $component->roof_type);
    }

    /** Apply exterior_construction (array) to an empty Seller component. */
    public function test_apply_sets_exterior_construction_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->exterior_construction = [];
        $component->importPreviewData = [[
            'canonical_key' => 'exterior_construction', 'prop_name' => 'exterior_construction',
            'label' => 'Exterior Construction', 'value' => 'Block,Stucco',
            'is_array_prop' => true, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['exterior_construction'], []);
        $this->assertEquals(['Block', 'Stucco'], $component->exterior_construction);
    }

    /** Apply foundation (array) to an empty Seller component. */
    public function test_apply_sets_foundation_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->foundation = [];
        $component->importPreviewData = [[
            'canonical_key' => 'foundation', 'prop_name' => 'foundation',
            'label' => 'Foundation', 'value' => 'Slab',
            'is_array_prop' => true, 'has_existing_value' => false, 'checked' => true,
        ]];

        $component->applyImportedFields(['foundation'], []);
        $this->assertEquals(['Slab'], $component->foundation);
    }

    /** Existing value must not be overwritten without override confirmation. */
    public function test_apply_does_not_overwrite_waterfront_without_override(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->waterfront = 'Yes';
        $component->importPreviewData = [[
            'canonical_key' => 'waterfront', 'prop_name' => 'waterfront',
            'label' => 'Waterfront', 'value' => 'no',
            'is_array_prop' => false, 'has_existing_value' => true, 'checked' => true,
        ]];

        $component->applyImportedFields(['waterfront'], []);
        $this->assertEquals('Yes', $component->waterfront,
            'waterfront must not be overwritten without override confirmation');
    }

    /** Existing value must be overwritten when in overrideKeys. */
    public function test_apply_overwrites_waterfront_with_override(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->waterfront = 'Yes';
        $component->importPreviewData = [[
            'canonical_key' => 'waterfront', 'prop_name' => 'waterfront',
            'label' => 'Waterfront', 'value' => 'no',
            'is_array_prop' => false, 'has_existing_value' => true, 'checked' => true,
        ]];

        $component->applyImportedFields(['waterfront'], ['waterfront']);
        $this->assertEquals('no', $component->waterfront,
            'waterfront should be overwritten when in override list');
    }

    // ── SellerOfferListingEdit — save path ────────────────────────────────────

    public function test_seller_edit_saves_all_five_new_fields(): void
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Edit Save Test',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListingEdit::class);

        $liveComponent = $component->instance();
        $liveComponent->flood_zone_date     = '2023-07-04';
        $liveComponent->waterfront          = 'Yes';
        $liveComponent->water_access        = ['Lake'];
        $liveComponent->water_view          = ['Lake', 'Pond'];
        $liveComponent->interior_features   = ['Split Bedroom'];

        $method = new ReflectionMethod(SellerOfferListingEdit::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        $this->assertEquals('2023-07-04',   $auction->info('flood_zone_date'));
        $this->assertEquals('Yes',           $auction->info('waterfront'));
        $this->assertEquals(['Lake'],        json_decode($auction->info('water_access'), true));
        $this->assertEquals(['Lake', 'Pond'], json_decode($auction->info('water_view'), true));
        $this->assertEquals(['Split Bedroom'], json_decode($auction->info('interior_features'), true));
    }
}
