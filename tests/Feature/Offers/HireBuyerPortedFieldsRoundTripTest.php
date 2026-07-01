<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase 12 — B5.8: Create-Buyer fields ported to Hire Buyer.
 *
 * Verifies the persistence round-trip for the four ported fields
 * (Purchase Purpose + its "Other", HOA Acceptance, Maximum HOA Monthly Fee,
 * Flood Zone Preference + its "Other") through the dedicated Hire Buyer edit
 * component:
 *   - LOAD: saved EAV meta hydrates the Livewire properties (flood zone JSON → array).
 *   - SAVE: setting the properties and running saveAllMetadata persists them back
 *     (flood zone array → JSON), so create/edit round-trip is intact.
 *
 * The select2 rendering of the flood-zone multiselect is browser-QA only and not
 * asserted here; this test covers the deterministic persistence wiring.
 */
class HireBuyerPortedFieldsRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function makeBuyerAuction(User $user, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $user->id,
            'address'     => '',
            'title'       => 'Test Hire Buyer B5.8',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        // Mirror a real saved Hire Buyer auction (arrays stored as JSON strings) so the
        // full-blade render / load exercises the ported fields, not legacy string-vs-array.
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

    public function test_edit_load_hydrates_ported_fields_from_meta(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeBuyerAuction($owner, [
            'purchase_purpose'           => 'Investment',
            'purchase_purpose_other'     => 'House flipping',
            'hoa_acceptance'             => 'Flexible',
            'hoa_max_monthly_fee'        => '350',
            'flood_zone_tolerance'       => json_encode(['Preferred Outside Flood Zones', 'Other']),
            'flood_zone_tolerance_other' => 'Open to coastal if insurance is reasonable',
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireBuyerEdit::class, ['auctionId' => $auction->id])
            ->instance();

        $this->assertEquals('Investment', $instance->purchase_purpose);
        $this->assertEquals('House flipping', $instance->purchase_purpose_other);
        $this->assertEquals('Flexible', $instance->hoa_acceptance);
        $this->assertEquals('350', (string) $instance->hoa_max_monthly_fee);
        $this->assertIsArray($instance->flood_zone_tolerance);
        $this->assertContains('Preferred Outside Flood Zones', $instance->flood_zone_tolerance);
        $this->assertContains('Other', $instance->flood_zone_tolerance);
        $this->assertEquals('Open to coastal if insurance is reasonable', $instance->flood_zone_tolerance_other);
    }

    public function test_save_persists_ported_fields_back_to_meta(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeBuyerAuction($owner);

        $component = Livewire::actingAs($owner)
            ->test(HireBuyerEdit::class, ['auctionId' => $auction->id])
            ->set('purchase_purpose', 'Development')
            ->set('purchase_purpose_other', '')
            ->set('hoa_acceptance', 'Yes')
            ->set('hoa_max_monthly_fee', '425')
            ->set('flood_zone_tolerance', ['Open to Any Flood Zone'])
            ->set('flood_zone_tolerance_other', '');

        // saveAllMetadata is the shared persistence path for the edit component.
        $save = new ReflectionMethod(HireBuyerEdit::class, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component->instance(), $auction->fresh());

        $fresh = $auction->fresh();
        $this->assertEquals('Development', $fresh->info('purchase_purpose'));
        $this->assertEquals('Yes', $fresh->info('hoa_acceptance'));
        $this->assertEquals('425', (string) $fresh->info('hoa_max_monthly_fee'));

        $flood = json_decode($fresh->info('flood_zone_tolerance'), true) ?? [];
        $this->assertEquals(['Open to Any Flood Zone'], $flood);
    }
}
