<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Http\Livewire\TenantAgentAuction as HireLiveCreate;
use App\Http\Livewire\TenantAgentAuctionEdit as HireLiveEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 9D — Search Areas + Important Places parity for the Hire Buyer/Tenant Agent wizards.
 *
 * Verifies that the Hire flows reuse the same Search Areas map (location_dna_preferences
 * blob) and Important Places (important_places_json) plumbing as the Create Buyer/Tenant
 * Offer flow, across BOTH component trees that render the shared buyer partial:
 *   - the live catch-all TenantAgentAuction / TenantAgentAuctionEdit (/hire/agent/auction/*)
 *   - the dedicated HireBuyerAgent\BuyerAgentAuction / ...Edit (/add-auction)
 *
 * Coverage: renders without Livewire property/method errors, edit-load prefill (legacy
 * discrete state/counties seed the blob; important_places_json hydrates), blob → discrete
 * meta write-back, and the Important Places submit guard.
 */
class HireSearchAreasParityTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $type = 'buyer'): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function makeBuyerAuction(User $user, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $user->id,
            'address'     => '',
            'title'       => 'Test Hire Buyer',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        // Base meta mirrors a real saved Hire Buyer auction: the component reads user_type
        // from meta (not the route), and the property tab expects property_items /
        // condition_prop_buyer as arrays (a real saved auction always has them).
        // property_items / condition_prop_buyer / garage_parking_spaces_option / assets are
        // rendered through in_array(); a real saved auction stores them as arrays. (The
        // dedicated BuyerAgentAuctionEdit defaults a couple of them to '' — set them here so
        // the full-blade render exercises the Search Areas wiring rather than tripping on
        // unrelated legacy string-vs-array fragility.)
        $meta = array_merge([
            'user_type'                    => 'buyer',
            'property_items'               => '[]',
            'condition_prop_buyer'         => '[]',
            'garage_parking_spaces_option' => '[]',
            'assets'                       => '[]',
        ], $meta);
        $rows = [['buyer_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type', 'meta_value' => 'hire_agent']];
        foreach ($meta as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return $auction;
    }

    private function makeTenantAuction(User $user, array $meta = []): TenantAgentAuction
    {
        // tenant_agent_auctions stores address/extended fields as EAV meta — no `address` column.
        $auction = (new TenantAgentAuction())->forceFill([
            'user_id'     => $user->id,
            'title'       => 'Test Hire Tenant',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $meta = array_merge(['user_type' => 'tenant', 'property_items' => '[]'], $meta);
        $rows = [['tenant_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type', 'meta_value' => 'hire_agent']];
        foreach ($meta as $k => $v) {
            $rows[] = ['tenant_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        TenantAgentAuctionMeta::insert($rows);

        return $auction;
    }

    // ── Render: no Livewire property/method errors; the map + Important Places appear ──

    public function test_live_buyer_create_renders_search_areas_map(): void
    {
        Livewire::actingAs($this->makeUser('buyer'))
            ->test(HireLiveCreate::class, ['user_type' => 'buyer'])
            ->assertSee('ldna-map-hire-buyer')
            ->assertSee('Important Places');
    }

    public function test_live_tenant_create_renders_search_areas_map(): void
    {
        Livewire::actingAs($this->makeUser('tenant'))
            ->test(HireLiveCreate::class, ['user_type' => 'tenant'])
            ->assertSee('ldna-map-hire-tenant')
            ->assertSee('Important Places');
    }

    public function test_dedicated_buyer_create_renders_search_areas_map(): void
    {
        Livewire::actingAs($this->makeUser('buyer'))
            ->test(HireBuyerCreate::class)
            ->assertSee('ldna-map-hire-buyer')
            ->assertSee('Important Places');
    }

    // ── Edit-load prefill: legacy discrete meta seeds the blob; Important Places hydrate ──

    public function test_live_buyer_edit_prefills_blob_from_legacy_meta(): void
    {
        $owner   = $this->makeUser('buyer');
        $auction = $this->makeBuyerAuction($owner, [
            'state'                => 'Georgia',
            'counties'             => json_encode(['Cobb County, GA']),
            'important_places_json' => json_encode([[
                'type' => 'Work', 'type_other' => '', 'address' => '1 Peachtree St, Atlanta, GA',
                'distance_pref' => 'miles', 'distance_value' => 10, 'travel_mode' => 'driving',
            ]]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireLiveEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer'])
            ->instance();

        $this->assertEquals('Georgia', $instance->existingLocationDna['state'] ?? null);
        $this->assertContains('Cobb County, GA', $instance->existingLocationDna['counties'] ?? []);
        $this->assertCount(1, $instance->existingImportantPlaces);
        $this->assertEquals('Work', $instance->existingImportantPlaces[0]['type']);
    }

    public function test_live_tenant_edit_prefills_blob_from_legacy_meta(): void
    {
        $owner   = $this->makeUser('tenant');
        $auction = $this->makeTenantAuction($owner, [
            'state'    => 'Florida',
            'counties' => json_encode(['Pinellas County, FL']),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireLiveEdit::class, ['auctionId' => $auction->id, 'user_type' => 'tenant'])
            ->instance();

        $this->assertEquals('Florida', $instance->existingLocationDna['state'] ?? null);
        $this->assertContains('Pinellas County, FL', $instance->existingLocationDna['counties'] ?? []);
    }

    public function test_dedicated_buyer_edit_prefills_blob_from_legacy_meta(): void
    {
        $owner   = $this->makeUser('buyer');
        $auction = $this->makeBuyerAuction($owner, [
            'state'    => 'Texas',
            'counties' => json_encode(['Travis County, TX']),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireBuyerEdit::class, ['auctionId' => $auction->id])
            ->instance();

        $this->assertEquals('Texas', $instance->existingLocationDna['state'] ?? null);
        $this->assertContains('Travis County, TX', $instance->existingLocationDna['counties'] ?? []);
    }

    // ── Write-back: blob → location_dna_preferences + mirrored discrete state/counties ──

    public function test_live_buyer_draft_save_writes_blob_and_mirrors_discrete_meta(): void
    {
        $owner   = $this->makeUser('buyer');
        $auction = $this->makeBuyerAuction($owner);

        $blob = json_encode([
            'cities'   => ['Tampa, FL'],
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Hillsborough County, FL'],
        ]);
        $ip = json_encode([[
            'type' => 'School', 'type_other' => '', 'address' => '100 School Rd, Tampa, FL',
            'distance_pref' => 'minutes', 'distance_value' => 15, 'travel_mode' => 'transit',
        ]]);

        Livewire::actingAs($owner)
            ->test(HireLiveEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer'])
            ->set('location_dna_preferences_json', $blob)
            ->set('important_places_json', $ip)
            ->call('saveDraftOnly');

        $fresh = $auction->fresh();
        $this->assertEquals($blob, $fresh->info('location_dna_preferences'));
        $this->assertEquals('Florida', $fresh->info('state'));

        $counties = json_decode($fresh->info('counties'), true) ?? [];
        $this->assertContains('Pinellas County, FL', $counties);
        $this->assertContains('Hillsborough County, FL', $counties);

        $cities = json_decode($fresh->info('cities'), true) ?? [];
        $this->assertContains('Tampa, FL', $cities);

        $savedIp = json_decode($fresh->info('important_places_json'), true) ?? [];
        $this->assertCount(1, $savedIp);
        $this->assertEquals('School', $savedIp[0]['type']);
        $this->assertEquals('minutes', $savedIp[0]['distance_pref']);
    }

    /** Empty blob state must never wipe an existing discrete state (backward-compat guard). */
    public function test_empty_blob_state_does_not_wipe_discrete_state(): void
    {
        $owner   = $this->makeUser('buyer');
        $auction = $this->makeBuyerAuction($owner, ['state' => 'Georgia']);

        Livewire::actingAs($owner)
            ->test(HireLiveEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer'])
            ->set('location_dna_preferences_json', json_encode(['cities' => [], 'state' => '']))
            ->call('saveDraftOnly');

        $this->assertEquals('Georgia', $auction->fresh()->info('state'));
    }

    // ── Submit guard: an incomplete Important Place row blocks the full submit ──

    public function test_incomplete_important_place_blocks_submit(): void
    {
        $owner   = $this->makeUser('buyer');
        $auction = $this->makeBuyerAuction($owner);

        // Started row missing the address → must fail validation on full submit.
        $badIp = json_encode([[
            'type' => 'Work', 'type_other' => '', 'address' => '',
            'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving',
        ]]);

        Livewire::actingAs($owner)
            ->test(HireLiveEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer'])
            ->set('important_places_json', $badIp)
            ->call('update')
            ->assertHasErrors('important_places_json');
    }
}
