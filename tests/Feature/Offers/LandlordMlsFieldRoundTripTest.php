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
 * Round-trip tests for the five new MLS fields on the Landlord components.
 *
 * Covers: waterfront, water_access, water_view, interior_features, flood_zone_date
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
}
