<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as CreateTenant;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit as EditTenant;
use App\Models\TenantAgentAuction as TenantRecord;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1c slice 3 — Create Tenant Listing.
 *
 * WHAT MAKES THIS WORKFLOW DIFFERENT FROM THE TWO ALREADY SHIPPED
 * ---------------------------------------------------------------
 * Two things, and both are asserted here rather than assumed.
 *
 * 1. THE ZIP MIRROR REACHES META BY A DIFFERENT ROUTE. The Hire family writes its legacy
 *    `zipCodes` mirror from a component property, which is why `hire_tenant` is a member of
 *    HasGeographyCascade::ZIP_MIRROR_WORKFLOWS. The Offer family does not: it opts that key into
 *    `LegacyMirrorProjection` via `managingMirrors()` and DERIVES it from canonical state. So a
 *    cascade ZIP selection reaches the mirror automatically through the canonical `zip_codes`
 *    dimension, and `create_tenant` is deliberately NOT in ZIP_MIRROR_WORKFLOWS — adding it would
 *    create a second, property-sourced path to the same key. Same outcome, different mechanism,
 *    and the outcome is what is pinned below.
 *
 * 2. VALIDATION READS THE DISCRETE PROPS BEFORE THE WRITE HAPPENS. This family calls
 *    `hydrateDiscreteLocationFromBlob()` before validating, because the discrete Acceptable
 *    State/Counties inputs were removed in 9B-3. The cascade therefore projects into the bridged
 *    payload BEFORE that hydrate, so the `required` rules see the selection the user actually
 *    made rather than the stored values the widget's server-seeded blob still carries.
 *
 * Everything else — the label format, preserved history, the role gate — is shared with the two
 * shipped workflows and is asserted here to prove it did not drift.
 */
class Phase1cCreateTenantGeographyCascadeTest extends TestCase
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

    /** The shipped scope: all three workflows. */
    private function enableCascade(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer', 'hire_tenant', 'create_tenant'],
        ]);
    }

    private function tenant(): User
    {
        return User::factory()->create(['user_type' => 'tenant']);
    }

    private function draft(User $owner, array $meta = []): TenantRecord
    {
        $auction = (new TenantRecord())->forceFill([
            'user_id' => $owner->id, 'title' => 'Create Tenant fixture',
            'is_draft' => true, 'is_approved' => false, 'is_sold' => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type' => 'tenant', 'workflow_type' => 'offer_listing', 'property_items' => '[]',
        ], $meta);

        $rows = [];
        foreach ($meta as $k => $v) {
            $rows[] = ['tenant_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        TenantAgentAuctionMeta::insert($rows);

        return $auction;
    }

    private function savedDraft(object $component): TenantRecord
    {
        $id = $component->instance()->listingId;

        $this->assertNotEmpty($id, 'saveDraft() must have produced a listing.');

        return TenantRecord::findOrFail($id);
    }

    private function canonicalOf(TenantRecord $record): array
    {
        return json_decode((string) $record->fresh()->info('location_dna_preferences'), true) ?: [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE FLAG IS A REAL SWITCH ON THIS SURFACE TOO
    // ═════════════════════════════════════════════════════════════════════════

    public function test_create_tenant_renders_the_cascade_when_enabled(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->assertSee('id="geo-cascade-state"', false)
            ->assertSee('id="geo-cascade-counties"', false)
            ->assertSee('id="geo-cascade-cities"', false)
            ->assertSee('id="geo-cascade-zips"', false)
            ->assertDontSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="ldna-cities-input"', false)
            ->assertDontSee('id="ldna-state-input"', false)
            ->assertDontSee('id="ldna-zips-input"', false);
    }

    public function test_create_tenant_keeps_the_legacy_editor_when_disabled(): void
    {
        Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    public function test_the_map_and_its_other_tools_survive_the_swap(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->assertSee('ldna-map-tenant')
            ->assertSee('Important Places')
            ->assertSee('ldna-draw-btn-polygon');
    }

    /** Both switches must agree: the tab opt-in alone is not an enable. */
    public function test_omitting_create_tenant_from_the_scope_leaves_the_legacy_editor(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer', 'hire_tenant'],
        ]);

        Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE STORED FORMAT IS THE ONE EVERY OTHER WORKFLOW WRITES
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_selection_is_stored_in_the_legacy_label_format(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->call('saveDraft');

        $canonical = $this->canonicalOf($this->savedDraft($component));

        $this->assertSame('Florida', $canonical['state']);
        $this->assertSame(['Pinellas County, FL', 'Hillsborough County, FL'], $canonical['counties']);
        $this->assertSame(['St. Petersburg, FL'], $canonical['cities']);
    }

    public function test_the_legacy_mirrors_are_written_in_the_same_format(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->call('saveDraft');

        $record = $this->savedDraft($component)->fresh();

        $this->assertSame('Florida', $record->info('state'));
        $this->assertSame(['Pinellas County, FL'], json_decode((string) $record->info('counties'), true));
        $this->assertSame(['St. Petersburg, FL'], json_decode((string) $record->info('cities'), true));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE ZIP MIRROR, VIA CANONICAL DERIVATION
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The ZIP reaches the legacy mirror — derived from canonical state, not from a property.
     *
     * Same observable outcome as `hire_tenant`, reached by the mechanism this family already
     * uses. If someone later adds `create_tenant` to ZIP_MIRROR_WORKFLOWS, this test keeps
     * passing while a second write path quietly appears; the scope guard's assertion on the
     * const membership is what catches that.
     */
    public function test_the_zip_selection_reaches_the_legacy_mirror(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoZipCodes', ['33708'])
            ->call('saveDraft');

        $record = $this->savedDraft($component)->fresh();

        $this->assertSame(['33708'], $this->canonicalOf($record)['zip_codes']);
        $this->assertSame(
            json_encode(['33708']),
            (string) $record->info('zipCodes'),
            'The Offer family derives this mirror from canonical state — the ZIP must appear there.'
        );
    }

    /** No ZIP chosen ⇒ the mirror is the canonical empty, not an invented value. */
    public function test_the_zip_mirror_is_empty_when_nothing_is_chosen(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->call('saveDraft');

        $this->assertSame(
            json_encode([]),
            (string) $this->savedDraft($component)->fresh()->info('zipCodes'),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · VALIDATION SEES THE CASCADE, NOT THE STALE BLOB
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The discrete props the `required` rules read carry the cascade's selection.
     *
     * This family hydrates those props from the bridged blob immediately before validating, so
     * the cascade must project into that blob first. Without that ordering the hydrate would
     * re-read the widget's server-seeded values and validation would judge the wrong geography.
     */
    public function test_the_cascade_feeds_the_props_the_required_rules_read(): void
    {
        $this->enableCascade();

        $instance = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->instance();

        $this->assertSame('Florida', $instance->state);
        $this->assertSame(['Pinellas County, FL'], $instance->counties);
    }

    /**
     * A cascade change SURVIVES the pre-validation hydrate.
     *
     * The hydrate runs against the bridged blob. Seeding that blob with a DIFFERENT, stale
     * geography and then asserting the cascade's choice wins is what proves the ordering: if the
     * projection ran after the hydrate — or not at all — the stale value would be stored.
     */
    public function test_a_cascade_choice_beats_a_stale_widget_blob(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('location_dna_preferences_json', json_encode([
                'state'    => 'Georgia',
                'counties' => ['Cobb County, GA'],
            ]))
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->call('saveDraft');

        $canonical = $this->canonicalOf($this->savedDraft($component));

        $this->assertSame('Florida', $canonical['state'], 'the cascade must win over the stale blob');
        $this->assertSame(['Pinellas County, FL'], $canonical['counties']);
    }

    /**
     * The PRE-VALIDATION hydrate sees the cascade's geography, not the stale widget blob.
     *
     * This is the assertion that pins the ORDERING, and it needs to exist separately from the
     * stored-data test above: the persist-time projection also runs, so a test that only checks
     * what was written passes even when the pre-validation call is deleted — a mutation probe
     * that removed it survived exactly that way.
     *
     * `store()` mutates the discrete props through `hydrateDiscreteLocationFromBlob()` BEFORE it
     * validates, so reading them afterwards observes the ordering directly, whatever validation
     * then decides. With the projection missing, the hydrate re-reads the blob and these props
     * come back carrying Georgia — the value the user replaced.
     */
    public function test_the_pre_validation_hydrate_sees_the_cascade_not_the_stale_blob(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant'])
            ->set('location_dna_preferences_json', json_encode([
                'state'    => 'Georgia',
                'counties' => ['Cobb County, GA'],
            ]))
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->call('store');

        $instance = $component->instance();

        $this->assertSame(
            'Florida',
            $instance->state,
            'the pre-validation hydrate must see the cascade selection, not the stale blob'
        );
        $this->assertSame(['Pinellas County, FL'], $instance->counties);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · EDIT ROUND-TRIP AND PRESERVED HISTORY
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_edit_surface_hydrates_the_cascade(): void
    {
        $this->enableCascade();

        $owner   = $this->tenant();
        $auction = $this->draft($owner, [
            'location_dna_preferences' => json_encode([
                'state'     => 'Florida',
                'counties'  => ['Pinellas County, FL'],
                'cities'    => ['St. Petersburg, FL'],
                'zip_codes' => ['33708'],
            ]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(EditTenant::class, ['auctionId' => $auction->id, 'user_type' => 'tenant'])
            ->instance();

        $this->assertTrue($instance->geoCascadeEnabled);
        $this->assertSame((string) $this->florida, $instance->geoStateId);
        $this->assertSame([(string) $this->pinellas], $instance->geoCountyIds);
        $this->assertSame([(string) $this->stPetersburg], $instance->geoCityIds);
        $this->assertSame(['33708'], $instance->geoZipCodes);
    }

    /**
     * Unmatched history survives an edit.
     *
     * Shaped so a save that never happened cannot satisfy it: a NEW county is selected and the
     * exact resulting order is asserted.
     */
    public function test_unmatched_history_survives_an_edit(): void
    {
        $this->enableCascade();

        $owner   = $this->tenant();
        $auction = $this->draft($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
            ]),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(EditTenant::class, ['auctionId' => $auction->id, 'user_type' => 'tenant']);

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
    // 6 · THE OTHER ROLES THIS COMPONENT SERVES STAY BLOCKED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `TenantOfferListing` is a four-role switch, like the catch-all Hire component: `store()`
     * maps `user_type` to four auction models and the root blade picks a different property tab
     * per role. Only tenant produces a workflow key, so no scope-list value can reach the others
     * — asserted here with EVERY plausible key in scope, including invented ones.
     *
     * WHY THIS ASSERTS THE MAP RATHER THAN RENDERING THE OTHER ROLES
     * --------------------------------------------------------------
     * Rendering this component as `seller` or `landlord` fails on `Undefined variable
     * $water_access`, and has always failed: its root blade switches to the seller/landlord
     * property tab, which reads properties only the Seller/Landlord Offer components declare.
     * That is a pre-existing defect on a path production never takes — those roles have their
     * own components — and it is outside this slice. Driving the gate directly keeps this test
     * measuring the role gate instead of that unrelated breakage.
     */
    public function test_the_other_roles_this_component_serves_stay_blocked(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => [
                'hire_buyer', 'hire_tenant', 'create_tenant',
                'create_seller', 'create_landlord', 'create_buyer',
            ],
        ]);

        $component = Livewire::actingAs($this->tenant())
            ->test(CreateTenant::class, ['user_type' => 'tenant']);

        $instance = $component->instance();

        // Sanity: the gate is genuinely open for the role this component exists to serve, so a
        // false result below means the ROLE was rejected rather than the flag being off.
        $this->assertTrue($instance->geoCascadeEnabled);

        $workflow = new \ReflectionMethod($instance, 'geographyCascadeWorkflow');
        $workflow->setAccessible(true);

        $role = new \ReflectionProperty($instance, 'user_type');
        $role->setAccessible(true);

        foreach (['seller', 'landlord', 'buyer', '', null] as $other) {
            $role->setValue($instance, $other);

            $this->assertNull(
                $workflow->invoke($instance),
                'Create Tenant must produce no workflow key for role '.var_export($other, true)
            );
            $this->assertFalse(
                $instance->geographyCascadeIsEnabledFor($workflow->invoke($instance)),
                'A null workflow must never resolve to enabled.'
            );
        }
    }

    /** The tab is included by three other Offer blades whose components carry no trait. */
    public function test_the_tab_tolerates_a_host_without_the_cascade_property(): void
    {
        $tab = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php'
        ));

        $this->assertStringContainsString('@if ($geoCascadeEnabled ?? false)', $tab);
        $this->assertStringContainsString(
            '\'ldnaGeographyCascade\'   => $geoCascadeEnabled ?? false,',
            $tab,
        );
    }
}
