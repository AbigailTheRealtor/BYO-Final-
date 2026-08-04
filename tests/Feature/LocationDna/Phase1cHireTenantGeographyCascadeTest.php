<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\TenantAgentAuction as HireCreate;
use App\Http\Livewire\TenantAgentAuctionEdit as HireEdit;
use App\Models\TenantAgentAuction as TenantRecord;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1c slice 2 — Hire Tenant.
 *
 * WHAT SLICE 2 ACTUALLY CHANGED, AND WHY THE SUITE IS SHAPED THIS WAY
 * -------------------------------------------------------------------
 * Slice 1b already wired `TenantAgentAuction` and its edit sibling, mapping the tenant role to
 * `hire_tenant`. Slice 2 therefore adds no component code at all: it turns the tenant tab's
 * cascade block on and adds the workflow to the scope list. Those two must land TOGETHER — the
 * cascade states all four geography keys whenever it is enabled, so a workflow switched on while
 * its tab still showed the legacy inputs would submit four empty values and silently clear stored
 * geography. `Phase1cHireBuyerCascadeScopeGuardTest` fails on that combination; this suite proves
 * the combination that did ship actually works.
 *
 * THE ONE BEHAVIOUR CHANGE THAT REACHES STORED DATA
 * -------------------------------------------------
 * Hire Tenant is the single workflow in `HasGeographyCascade::ZIP_MIRROR_WORKFLOWS`. It has always
 * written a legacy `zipCodes` mirror from its discrete property, and since the ZIP tag input was
 * retired that property has been empty on every save — so the mirror has been overwritten with
 * `[]` every time. Enabling the cascade here is the approved repair: the mirror finally carries
 * what the user chose. Hire Buyer is deliberately NOT in that list, so its mirror behaviour is
 * unchanged, and both facts are asserted below.
 */
class Phase1cHireTenantGeographyCascadeTest extends TestCase
{
    use DatabaseTransactions;

    private int $florida;
    private int $pinellas;
    private int $hillsborough;
    private int $stPetersburg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->florida = (int) DB::table('us_states')->insertGetId([
            'name' => 'Florida', 'abbreviation' => 'FL', 'fips_code' => '12',
        ]);
        $this->pinellas = (int) DB::table('us_counties')->insertGetId([
            'name' => 'Pinellas County', 'state_id' => $this->florida, 'fips_code' => '12103',
        ]);
        $this->hillsborough = (int) DB::table('us_counties')->insertGetId([
            'name' => 'Hillsborough County', 'state_id' => $this->florida, 'fips_code' => '12057',
        ]);
        $this->stPetersburg = (int) DB::table('us_cities')->insertGetId([
            'name' => 'St. Petersburg', 'state_id' => $this->florida, 'county_id' => $this->pinellas,
        ]);

        DB::table('us_zip_codes')->insert([
            'zip_code' => '33708', 'city' => 'Pinellas', 'state_abbrev' => 'FL',
            'state_name' => 'Florida', 'county' => 'Pinellas',
        ]);
    }

    /** The shipped scope: both Hire workflows. */
    private function enableCascade(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer', 'hire_tenant'],
        ]);
    }

    private function tenant(): User
    {
        return User::factory()->create(['user_type' => 'tenant']);
    }

    private function tenantDraft(User $owner, array $meta = []): TenantRecord
    {
        $auction = (new TenantRecord())->forceFill([
            'user_id' => $owner->id, 'title' => 'Tenant cascade fixture',
            'is_draft' => true, 'is_approved' => false, 'is_sold' => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type' => 'tenant', 'workflow_type' => 'hire_agent', 'property_items' => '[]',
        ], $meta);

        $rows = [];
        foreach ($meta as $k => $v) {
            $rows[] = ['tenant_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        TenantAgentAuctionMeta::insert($rows);

        return $auction;
    }

    private function newestTenantRecord(User $owner): TenantRecord
    {
        return TenantRecord::where('user_id', $owner->id)->latest('id')->firstOrFail();
    }

    private function canonicalOf(TenantRecord $record): array
    {
        return json_decode((string) $record->fresh()->info('location_dna_preferences'), true) ?: [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE TENANT WORKFLOW RENDERS THE CASCADE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_tenant_workflow_renders_the_cascade_when_enabled(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->tenant())
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->assertSee('id="geo-cascade-state"', false)
            ->assertSee('id="geo-cascade-counties"', false)
            ->assertSee('id="geo-cascade-cities"', false)
            ->assertSee('id="geo-cascade-zips"', false)
            ->assertDontSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="ldna-cities-input"', false)
            ->assertDontSee('id="ldna-state-input"', false)
            ->assertDontSee('id="ldna-zips-input"', false);
    }

    /** Off is still off: the tab opt-in alone changes nothing without the flag. */
    public function test_the_tenant_workflow_keeps_the_legacy_editor_when_disabled(): void
    {
        Livewire::actingAs($this->tenant())
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    /** The map, its draw tools and Important Places are untouched by the swap. */
    public function test_the_tenant_map_and_its_other_tools_survive_the_swap(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->tenant())
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->assertSee('ldna-map-hire-tenant')
            ->assertSee('Important Places')
            ->assertSee('ldna-draw-btn-polygon');
    }

    /** Both switches must still agree — the tab opt-in is not itself an enable. */
    public function test_listing_only_the_buyer_workflow_leaves_tenant_on_the_legacy_editor(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer'],
        ]);

        Livewire::actingAs($this->tenant())
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE STORED FORMAT IS THE SAME ONE HIRE BUYER WRITES
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_tenant_selection_is_stored_in_the_legacy_label_format(): void
    {
        $this->enableCascade();

        $owner = $this->tenant();

        Livewire::actingAs($owner)
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->call('saveDraft');

        $canonical = $this->canonicalOf($this->newestTenantRecord($owner));

        $this->assertSame('Florida', $canonical['state']);
        $this->assertSame(['Pinellas County, FL', 'Hillsborough County, FL'], $canonical['counties']);
        $this->assertSame(['St. Petersburg, FL'], $canonical['cities']);
    }

    public function test_the_tenant_legacy_mirrors_are_written_in_the_same_format(): void
    {
        $this->enableCascade();

        $owner = $this->tenant();

        Livewire::actingAs($owner)
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->call('saveDraft');

        $record = $this->newestTenantRecord($owner)->fresh();

        $this->assertSame('Florida', $record->info('state'));
        $this->assertSame(['Pinellas County, FL'], json_decode((string) $record->info('counties'), true));
        $this->assertSame(['St. Petersburg, FL'], json_decode((string) $record->info('cities'), true));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE ZIP MIRROR — THE ONE APPROVED BEHAVIOUR CHANGE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Hire Tenant's legacy `zipCodes` mirror finally carries the user's selection.
     *
     * This workflow has always written that key from its discrete property, and since the ZIP tag
     * input was retired the property has been empty on every save — so the mirror was overwritten
     * with `[]` each time, destroying whatever it held. `hire_tenant` is the only member of
     * ZIP_MIRROR_WORKFLOWS, and this is what that membership buys.
     */
    public function test_the_tenant_zip_mirror_now_carries_the_selection(): void
    {
        $this->enableCascade();

        $owner = $this->tenant();

        Livewire::actingAs($owner)
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoZipCodes', ['33708'])
            ->call('saveDraft');

        $record = $this->newestTenantRecord($owner)->fresh();

        $this->assertSame(
            json_encode(['33708']),
            (string) $record->info('zipCodes'),
            'The tenant zipCodes mirror must carry the cascade selection, not an empty array.'
        );
        $this->assertSame(['33708'], $this->canonicalOf($record)['zip_codes']);
    }

    /**
     * With the cascade on but no ZIP chosen, the mirror is an empty array — the same value the
     * workflow has always written. Enabling the cascade does not invent ZIPs.
     */
    public function test_the_tenant_zip_mirror_is_empty_when_nothing_is_chosen(): void
    {
        $this->enableCascade();

        $owner = $this->tenant();

        Livewire::actingAs($owner)
            ->test(HireCreate::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->call('saveDraft');

        $this->assertSame(
            json_encode([]),
            (string) $this->newestTenantRecord($owner)->fresh()->info('zipCodes'),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · EDIT ROUND-TRIP AND PRESERVED HISTORY
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_tenant_edit_surface_hydrates_the_cascade(): void
    {
        $this->enableCascade();

        $owner   = $this->tenant();
        $auction = $this->tenantDraft($owner, [
            'location_dna_preferences' => json_encode([
                'state'     => 'Florida',
                'counties'  => ['Pinellas County, FL'],
                'cities'    => ['St. Petersburg, FL'],
                'zip_codes' => ['33708'],
            ]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireEdit::class, ['auctionId' => $auction->id, 'user_type' => 'tenant'])
            ->instance();

        $this->assertTrue($instance->geoCascadeEnabled);
        $this->assertSame((string) $this->florida, $instance->geoStateId);
        $this->assertSame([(string) $this->pinellas], $instance->geoCountyIds);
        $this->assertSame([(string) $this->stPetersburg], $instance->geoCityIds);
        $this->assertSame(['33708'], $instance->geoZipCodes);
    }

    /**
     * An unmatched stored label survives a tenant edit.
     *
     * Shaped so a save that never happened cannot satisfy it: a NEW county is selected and the
     * exact resulting order is asserted, so the assertion fails if the write bailed, if
     * preservation was dropped, or if the ordering flipped.
     */
    public function test_unmatched_history_survives_a_tenant_edit(): void
    {
        $this->enableCascade();

        $owner   = $this->tenant();
        $auction = $this->tenantDraft($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
            ]),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(HireEdit::class, ['auctionId' => $auction->id, 'user_type' => 'tenant']);

        $this->assertSame(
            ['Ye Olde County, FL'],
            $component->instance()->geoPreserved['counties'],
        );

        $component->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->call('saveDraftOnly');

        $this->assertSame(
            ['Pinellas County, FL', 'Hillsborough County, FL', 'Ye Olde County, FL'],
            $this->canonicalOf($auction)['counties'],
            'Selected counties lead, preserved history trails, and nothing is lost.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · SELLER AND LANDLORD STAY BLOCKED WITH BOTH WORKFLOWS ENABLED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Enabling the tenant workflow must not leak into the other two roles the SAME component
     * serves. The workflow map produces no key for them, so no scope-list value can reach them.
     */
    public function test_seller_and_landlord_stay_blocked_with_both_workflows_enabled(): void
    {
        $this->enableCascade();

        foreach (['seller', 'landlord'] as $role) {
            $component = Livewire::actingAs(User::factory()->create(['user_type' => $role]))
                ->test(HireCreate::class, ['user_type' => $role]);

            $this->assertFalse(
                $component->instance()->geoCascadeEnabled,
                "The cascade must stay unreachable for the {$role} role."
            );

            $component->assertDontSee('id="geo-cascade-state"', false);
        }
    }

    /**
     * The tenant tab is included by the seller and landlord root blades too, from an unreachable
     * role branch. Those components carry no cascade trait, so the partial must still default the
     * flag to false rather than fataling on an undefined variable — the defect that broke four
     * tests when the Hire Buyer tab first gained this block.
     */
    public function test_the_tenant_tab_tolerates_a_host_without_the_cascade_property(): void
    {
        $tab = file_get_contents(base_path(
            'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php'
        ));

        $this->assertStringContainsString('@if ($geoCascadeEnabled ?? false)', $tab);
        $this->assertStringContainsString(
            '\'ldnaGeographyCascade\'    => $geoCascadeEnabled ?? false,',
            $tab,
        );
    }
}
