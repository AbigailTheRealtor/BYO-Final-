<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\TenantAgentAuction as LiveHireCreate;
use App\Http\Livewire\TenantAgentAuctionEdit as LiveHireEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #17 / #18 / #19 — B5.8 buyer Property Preferences fields on the LIVE Hire flow.
 *
 * The live Hire Buyer form is served by TenantAgentAuction / TenantAgentAuctionEdit
 * (via @switch($user_type) @case('buyer')), NOT the dormant HireBuyerAgent component.
 * Those live components previously did NOT declare purchase_purpose / hoa_acceptance /
 * flood_zone_tolerance, so a live wire:model on Purchase Purpose or HOA Acceptance threw
 * PublicPropertyNotFoundException (HTTP 500) the moment the user touched the field, and
 * the flood-zone selection never reached the server so its "Other" reveal was dead.
 *
 * These tests assert the deterministic wiring on the LIVE components:
 *   - the six public properties are declared (setting them via Livewire no longer 500s);
 *   - the edit component hydrates them from EAV meta on load;
 *   - the create component's saveAllMetadata persists them back (flood zone array → JSON).
 *
 * Select2 rendering of the flood-zone multiselect is browser-QA only.
 */
class LiveHireBuyerB58FieldsTest extends TestCase
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
            'title'       => 'Live Hire Buyer B5.8',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

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

    /** #17/#18/#19: the six B5.8 props exist on both live components (no 500 on interaction). */
    public function test_live_components_declare_the_b58_properties(): void
    {
        foreach ([LiveHireCreate::class, LiveHireEdit::class] as $class) {
            foreach ([
                'purchase_purpose', 'purchase_purpose_other',
                'hoa_acceptance', 'hoa_max_monthly_fee',
                'flood_zone_tolerance', 'flood_zone_tolerance_other',
            ] as $prop) {
                $this->assertTrue(
                    property_exists($class, $prop),
                    "$class must declare public \$$prop so its live wire:model does not 500"
                );
            }
        }
    }

    /** #18: flood_zone_tolerance defaults to an array on both live components. */
    public function test_flood_zone_default_is_array(): void
    {
        foreach ([new LiveHireCreate(), new LiveHireEdit()] as $component) {
            $this->assertIsArray($component->flood_zone_tolerance);
        }
    }

    /** #17/#18/#19: live edit hydrates the ported fields from EAV meta, and setting them does not 500. */
    public function test_live_edit_hydrates_and_accepts_the_b58_fields(): void
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

        $component = Livewire::actingAs($owner)
            ->test(LiveHireEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer']);

        // Hydrated from meta on load.
        $component->assertSet('purchase_purpose', 'Investment')
            ->assertSet('purchase_purpose_other', 'House flipping')
            ->assertSet('hoa_acceptance', 'Flexible');

        $instance = $component->instance();
        $this->assertEquals('350', (string) $instance->hoa_max_monthly_fee);
        $this->assertIsArray($instance->flood_zone_tolerance);
        $this->assertContains('Other', $instance->flood_zone_tolerance);
        $this->assertEquals('Open to coastal if insurance is reasonable', $instance->flood_zone_tolerance_other);

        // Interaction that previously 500'd: live wire:model set on undeclared props.
        $component->set('purchase_purpose', 'Development')
            ->set('hoa_acceptance', 'Yes')
            ->set('flood_zone_tolerance', ['Open to Any Flood Zone'])
            ->assertSet('purchase_purpose', 'Development')
            ->assertSet('hoa_acceptance', 'Yes')
            ->assertSet('flood_zone_tolerance', ['Open to Any Flood Zone'])
            ->assertHasNoErrors();
    }

    /** #17/#18/#19: the live buyer create form actually renders the B5.8 markup (DOM-level render proxy). */
    public function test_live_buyer_create_renders_the_b58_fields_in_dom(): void
    {
        $owner = $this->makeUser();

        $component = Livewire::actingAs($owner)
            ->test(LiveHireCreate::class, ['user_type' => 'buyer']);

        // Visible labels render on the live buyer path.
        $component->assertSee('Purchase Purpose')
            ->assertSee('HOA Acceptance')
            ->assertSee('Flood Zone Preference');

        // The live wire:model bindings (which previously 500'd) and the flood-zone
        // select + its "Other" wrapper are present in the markup.
        $component->assertSeeHtml('wire:model="purchase_purpose"')
            ->assertSeeHtml('wire:model="hoa_acceptance"')
            ->assertSeeHtml('id="flood_zone_tolerance"')
            ->assertSeeHtml('flood_zone_tolerance_other_wrapper');
    }

    /** #17/#18/#19: live create component's saveAllMetadata persists the ported fields (array → JSON). */
    public function test_live_create_save_persists_the_b58_fields(): void
    {
        $owner   = $this->makeUser();
        $auction = $this->makeBuyerAuction($owner);

        $component = Livewire::actingAs($owner)
            ->test(LiveHireCreate::class, ['user_type' => 'buyer'])
            ->set('purchase_purpose', 'Development')
            ->set('purchase_purpose_other', '')
            ->set('hoa_acceptance', 'Yes')
            ->set('hoa_max_monthly_fee', '425')
            ->set('flood_zone_tolerance', ['Open to Any Flood Zone'])
            ->set('flood_zone_tolerance_other', '');

        $save = new ReflectionMethod(LiveHireCreate::class, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component->instance(), $auction->fresh());

        $fresh = $auction->fresh();
        $this->assertEquals('Development', $fresh->info('purchase_purpose'));
        $this->assertEquals('Yes', $fresh->info('hoa_acceptance'));
        $this->assertEquals('425', (string) $fresh->info('hoa_max_monthly_fee'));
        $this->assertEquals(['Open to Any Flood Zone'], json_decode($fresh->info('flood_zone_tolerance'), true) ?? []);
    }
}
