<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Round-trip tests for MLS fields on the Landlord components.
 *
 * Covers:
 *   - Original five: waterfront, water_access, water_view, interior_features, flood_zone_date
 *   - Four new structural: lot_dimensions, roof_type, exterior_construction, foundation
 */
class LandlordMlsFieldRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    private function makeAuction(array $meta = []): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $this->user->id,
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'workflow_type',
            'meta_value'                => 'offer_listing',
        ]);

        foreach ($meta as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $key,
                'meta_value'                => (string) $value,
            ]);
        }

        return $auction;
    }

    // ── LandlordOfferListing (Create) — load path ─────────────────────────────

    public function test_landlord_create_loads_flood_zone_date(): void
    {
        $auction = $this->makeAuction(['flood_zone_date' => '2020-08-15']);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('flood_zone_date', '2020-08-15');
    }

    public function test_landlord_create_loads_waterfront(): void
    {
        $auction = $this->makeAuction(['waterfront' => 'Yes']);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('waterfront', 'Yes');
    }

    public function test_landlord_create_loads_water_access(): void
    {
        $auction = $this->makeAuction(['water_access' => json_encode(['Intracoastal Waterway', 'Creek'])]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('water_access', ['Intracoastal Waterway', 'Creek']);
    }

    public function test_landlord_create_loads_water_view(): void
    {
        $auction = $this->makeAuction(['water_view' => json_encode(['Bay/Harbor - Partial'])]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('water_view', ['Bay/Harbor - Partial']);
    }

    public function test_landlord_create_loads_interior_features(): void
    {
        $auction = $this->makeAuction(['interior_features' => json_encode(['Granite Counters', 'Vaulted Ceiling(s)'])]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('interior_features', ['Granite Counters', 'Vaulted Ceiling(s)']);
    }

    public function test_landlord_create_loads_lot_dimensions(): void
    {
        $auction = $this->makeAuction(['lot_dimensions' => '80x120']);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('lot_dimensions', '80x120');
    }

    public function test_landlord_create_loads_roof_type(): void
    {
        $auction = $this->makeAuction(['roof_type' => json_encode(['Shingle', 'Metal'])]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('roof_type', ['Shingle', 'Metal']);
    }

    public function test_landlord_create_loads_exterior_construction(): void
    {
        $auction = $this->makeAuction(['exterior_construction' => json_encode(['Block', 'Stucco'])]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('exterior_construction', ['Block', 'Stucco']);
    }

    public function test_landlord_create_loads_foundation(): void
    {
        $auction = $this->makeAuction(['foundation' => json_encode(['Slab'])]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $component->assertSet('foundation', ['Slab']);
    }

    // ── LandlordOfferListing (Create) — save path ─────────────────────────────

    public function test_landlord_create_saves_all_five_new_fields(): void
    {
        $auction = LandlordAgentAuction::create([
            'user_id'  => $this->user->id,
            'is_draft' => true,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class);

        $liveComponent = $component->instance();
        $liveComponent->flood_zone_date     = '2018-04-22';
        $liveComponent->waterfront          = 'No';
        $liveComponent->water_access        = ['Lake', 'Pond'];
        $liveComponent->water_view          = ['Lake'];
        $liveComponent->interior_features   = ['Ceiling Fans(s)', 'Crown Molding'];

        $method = new ReflectionMethod(LandlordOfferListing::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        $this->assertEquals('2018-04-22',                      $auction->info('flood_zone_date'));
        $this->assertEquals('No',                               $auction->info('waterfront'));
        $this->assertEquals(['Lake', 'Pond'],                   json_decode($auction->info('water_access'), true));
        $this->assertEquals(['Lake'],                           json_decode($auction->info('water_view'), true));
        $this->assertEquals(['Ceiling Fans(s)', 'Crown Molding'], json_decode($auction->info('interior_features'), true));
    }

    public function test_landlord_create_saves_structural_fields(): void
    {
        $auction = LandlordAgentAuction::create([
            'user_id'  => $this->user->id,
            'is_draft' => true,
        ]);

        $component = Livewire::actingAs($this->user)->test(LandlordOfferListing::class);
        $liveComponent = $component->instance();
        $liveComponent->lot_dimensions        = '100x150';
        $liveComponent->roof_type             = ['Shingle', 'Metal'];
        $liveComponent->other_roof_type       = '';
        $liveComponent->exterior_construction = ['Block', 'Stucco'];
        $liveComponent->other_exterior_construction = '';
        $liveComponent->foundation            = ['Slab'];
        $liveComponent->other_foundation      = '';

        $method = new ReflectionMethod(LandlordOfferListing::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        $this->assertEquals('100x150',              $auction->info('lot_dimensions'));
        $this->assertEquals(['Shingle', 'Metal'],    json_decode($auction->info('roof_type'), true));
        $this->assertEquals(['Block', 'Stucco'],     json_decode($auction->info('exterior_construction'), true));
        $this->assertEquals(['Slab'],                json_decode($auction->info('foundation'), true));
    }

    // ── LandlordOfferListingEdit — load path ──────────────────────────────────

    public function test_landlord_edit_loads_flood_zone_date(): void
    {
        $auction = $this->makeAuction(['flood_zone_date' => '2017-09-10']);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('flood_zone_date', '2017-09-10');
    }

    public function test_landlord_edit_loads_waterfront(): void
    {
        $auction = $this->makeAuction(['waterfront' => 'Yes']);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('waterfront', 'Yes');
    }

    public function test_landlord_edit_loads_water_access(): void
    {
        $auction = $this->makeAuction(['water_access' => json_encode(['Bay/Harbor', 'Gulf/Ocean'])]);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('water_access', ['Bay/Harbor', 'Gulf/Ocean']);
    }

    public function test_landlord_edit_loads_water_view(): void
    {
        $auction = $this->makeAuction(['water_view' => json_encode(['Gulf/Ocean - Full'])]);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('water_view', ['Gulf/Ocean - Full']);
    }

    public function test_landlord_edit_loads_interior_features(): void
    {
        $auction = $this->makeAuction(['interior_features' => json_encode(['Walk-In Closet(s)', 'Wet Bar'])]);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('interior_features', ['Walk-In Closet(s)', 'Wet Bar']);
    }

    public function test_landlord_edit_loads_lot_dimensions(): void
    {
        $auction = $this->makeAuction(['lot_dimensions' => '75x100']);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('lot_dimensions', '75x100');
    }

    public function test_landlord_edit_loads_roof_type(): void
    {
        $auction = $this->makeAuction(['roof_type' => json_encode(['Tile', 'Concrete'])]);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('roof_type', ['Tile', 'Concrete']);
    }

    public function test_landlord_edit_loads_exterior_construction(): void
    {
        $auction = $this->makeAuction(['exterior_construction' => json_encode(['Brick', 'Stone'])]);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('exterior_construction', ['Brick', 'Stone']);
    }

    public function test_landlord_edit_loads_foundation(): void
    {
        $auction = $this->makeAuction(['foundation' => json_encode(['Crawlspace', 'Slab'])]);
        $auction->is_draft = false; $auction->is_approved = true; $auction->save();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class)
            ->call('loadAuctionData', $auction->id);

        $component->assertSet('foundation', ['Crawlspace', 'Slab']);
    }

    // ── LandlordOfferListingEdit — save path ──────────────────────────────────

    public function test_landlord_edit_saves_all_five_new_fields(): void
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $this->user->id,
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListingEdit::class);

        $liveComponent = $component->instance();
        $liveComponent->flood_zone_date     = '2024-01-01';
        $liveComponent->waterfront          = 'Yes';
        $liveComponent->water_access        = ['River'];
        $liveComponent->water_view          = ['River', 'Canal'];
        $liveComponent->interior_features   = ['Fireplace'];

        $method = new ReflectionMethod(LandlordOfferListingEdit::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        $this->assertEquals('2024-01-01',    $auction->info('flood_zone_date'));
        $this->assertEquals('Yes',            $auction->info('waterfront'));
        $this->assertEquals(['River'],        json_decode($auction->info('water_access'), true));
        $this->assertEquals(['River', 'Canal'], json_decode($auction->info('water_view'), true));
        $this->assertEquals(['Fireplace'],    json_decode($auction->info('interior_features'), true));
    }

    public function test_landlord_edit_saves_structural_fields(): void
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $this->user->id,
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $component = Livewire::actingAs($this->user)->test(LandlordOfferListingEdit::class);
        $liveComponent = $component->instance();
        $liveComponent->lot_dimensions        = '60x90';
        $liveComponent->roof_type             = ['Tile'];
        $liveComponent->other_roof_type       = '';
        $liveComponent->exterior_construction = ['Concrete', 'Stucco'];
        $liveComponent->other_exterior_construction = '';
        $liveComponent->foundation            = ['Slab', 'Stem Wall'];
        $liveComponent->other_foundation      = '';

        $method = new ReflectionMethod(LandlordOfferListingEdit::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($liveComponent, $auction);

        $auction->refresh();
        $this->assertEquals('60x90',                    $auction->info('lot_dimensions'));
        $this->assertEquals(['Tile'],                    json_decode($auction->info('roof_type'), true));
        $this->assertEquals(['Concrete', 'Stucco'],      json_decode($auction->info('exterior_construction'), true));
        $this->assertEquals(['Slab', 'Stem Wall'],       json_decode($auction->info('foundation'), true));
    }

    // ── applyImportedFields — structural field application ────────────────────

    public function test_apply_sets_lot_dimensions_on_landlord(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new LandlordOfferListing();
        $component->lot_dimensions = '';

        $component->importPreviewData = [
            [
                'canonical_key'      => 'lot_dimensions',
                'prop_name'          => 'lot_dimensions',
                'label'              => 'Lot Dimensions',
                'value'              => '75x120',
                'is_array_prop'      => false,
                'has_existing_value' => false,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['lot_dimensions'], []);

        $this->assertEquals('75x120', $component->lot_dimensions);
    }

    public function test_apply_sets_roof_type_on_landlord(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new LandlordOfferListing();
        $component->roof_type = [];

        $component->importPreviewData = [
            [
                'canonical_key'      => 'roof_type',
                'prop_name'          => 'roof_type',
                'label'              => 'Roof Type',
                'value'              => 'Tile,Shingle',
                'is_array_prop'      => true,
                'has_existing_value' => false,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['roof_type'], []);

        $this->assertEquals(['Tile', 'Shingle'], $component->roof_type);
    }

    public function test_apply_sets_exterior_construction_on_landlord(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new LandlordOfferListing();
        $component->exterior_construction = [];

        $component->importPreviewData = [
            [
                'canonical_key'      => 'exterior_construction',
                'prop_name'          => 'exterior_construction',
                'label'              => 'Exterior Construction',
                'value'              => 'Block,Stucco',
                'is_array_prop'      => true,
                'has_existing_value' => false,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['exterior_construction'], []);

        $this->assertEquals(['Block', 'Stucco'], $component->exterior_construction);
    }

    public function test_apply_sets_foundation_on_landlord(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new LandlordOfferListing();
        $component->foundation = [];

        $component->importPreviewData = [
            [
                'canonical_key'      => 'foundation',
                'prop_name'          => 'foundation',
                'label'              => 'Foundation',
                'value'              => 'Slab,Crawlspace',
                'is_array_prop'      => true,
                'has_existing_value' => false,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['foundation'], []);

        $this->assertEquals(['Slab', 'Crawlspace'], $component->foundation);
    }

    public function test_apply_does_not_overwrite_existing_roof_type_without_override(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new LandlordOfferListing();
        $component->roof_type = ['Metal'];

        $component->importPreviewData = [
            [
                'canonical_key'      => 'roof_type',
                'prop_name'          => 'roof_type',
                'label'              => 'Roof Type',
                'value'              => 'Tile',
                'is_array_prop'      => true,
                'has_existing_value' => true,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['roof_type'], []);

        $this->assertEquals(['Metal'], $component->roof_type,
            'roof_type should not be overwritten without override confirmation');
    }

    public function test_apply_overwrites_existing_roof_type_with_override(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component = new LandlordOfferListing();
        $component->roof_type = ['Metal'];

        $component->importPreviewData = [
            [
                'canonical_key'      => 'roof_type',
                'prop_name'          => 'roof_type',
                'label'              => 'Roof Type',
                'value'              => 'Tile',
                'is_array_prop'      => true,
                'has_existing_value' => true,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['roof_type'], ['roof_type']);

        $this->assertEquals(['Tile'], $component->roof_type,
            'roof_type should be overwritten when in override list');
    }
}
