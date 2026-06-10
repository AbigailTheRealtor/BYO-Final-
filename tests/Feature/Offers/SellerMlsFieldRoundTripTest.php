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
