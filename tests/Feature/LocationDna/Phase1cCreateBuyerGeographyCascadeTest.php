<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing as CreateBuyer;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit as EditBuyer;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1c slice 4 — Create Buyer Listing, the last workflow.
 *
 * WHERE THIS WORKFLOW SITS BETWEEN THE THREE ALREADY SHIPPED
 * ----------------------------------------------------------
 * It combines the two halves that were previously in different places, which is why it gets its
 * own suite rather than being folded into an existing one:
 *
 *   FROM CREATE TENANT — the ordering. This family calls `hydrateDiscreteLocationFromBlob()`
 *   immediately before validating, so the cascade must project into the bridged payload FIRST or
 *   the `required` rules judge the stored geography instead of the edited one.
 *
 *   FROM HIRE BUYER — the mirror rules. It persists through the DEFAULT writer, whose managed set
 *   excludes `zipCodes`. The Buyer family has never written that key, so `create_buyer` is
 *   deliberately absent from ZIP_MIRROR_WORKFLOWS — and this component declares no `$zipCodes`
 *   property for the trait to touch even if it were. Both are asserted below, because a ZIP tier
 *   that quietly started writing a Buyer-family mirror is the one regression no existing guard
 *   would catch.
 *
 * The label format, preserved history and the role gate are shared with all three shipped
 * workflows and are re-asserted here to prove they did not drift on the last surface.
 */
class Phase1cCreateBuyerGeographyCascadeTest extends TestCase
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

    /** The shipped scope: all four workflows. */
    private function enableCascade(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => [
                'hire_buyer', 'hire_tenant', 'create_tenant', 'create_buyer',
            ],
        ]);
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function draft(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'address' => '', 'title' => 'Create Buyer fixture',
            'is_draft' => true, 'is_approved' => false, 'is_sold' => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type' => 'buyer', 'workflow_type' => 'offer_listing',
            'property_items' => '[]', 'condition_prop_buyer' => '[]',
            'garage_parking_spaces_option' => '[]', 'assets' => '[]',
        ], $meta);

        $rows = [];
        foreach ($meta as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return $auction;
    }

    private function savedDraft(object $component): BuyerAgentAuction
    {
        $id = $component->instance()->listingId;

        $this->assertNotEmpty($id, 'saveDraft() must have produced a listing.');

        return BuyerAgentAuction::findOrFail($id);
    }

    private function canonicalOf(BuyerAgentAuction $record): array
    {
        return json_decode((string) $record->fresh()->info('location_dna_preferences'), true) ?: [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE FLAG IS A REAL SWITCH ON THE LAST SURFACE TOO
    // ═════════════════════════════════════════════════════════════════════════

    public function test_create_buyer_renders_the_cascade_when_enabled(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->assertSee('id="geo-cascade-state"', false)
            ->assertSee('id="geo-cascade-counties"', false)
            ->assertSee('id="geo-cascade-cities"', false)
            ->assertSee('id="geo-cascade-zips"', false)
            ->assertDontSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="ldna-cities-input"', false)
            ->assertDontSee('id="ldna-state-input"', false)
            ->assertDontSee('id="ldna-zips-input"', false);
    }

    public function test_create_buyer_keeps_the_legacy_editor_when_disabled(): void
    {
        Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    public function test_the_map_and_its_other_tools_survive_the_swap(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->assertSee('ldna-map-buyer')
            ->assertSee('Important Places')
            ->assertSee('ldna-draw-btn-polygon');
    }

    /** Both switches must agree: the tab opt-in alone is not an enable. */
    public function test_omitting_create_buyer_from_the_scope_leaves_the_legacy_editor(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer', 'hire_tenant', 'create_tenant'],
        ]);

        Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE STORED FORMAT IS THE ONE EVERY OTHER WORKFLOW WRITES
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_selection_is_stored_in_the_legacy_label_format(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->call('saveDraft');

        $canonical = $this->canonicalOf($this->savedDraft($component));

        $this->assertSame('Florida', $canonical['state']);
        $this->assertSame(['Pinellas County, FL', 'Hillsborough County, FL'], $canonical['counties']);
        $this->assertSame(['St. Petersburg, FL'], $canonical['cities']);
    }

    /**
     * The managed mirrors are derived from that canonical state.
     *
     * `cities` is asserted deliberately: this component declares no `$cities` property, so the
     * mirror cannot be property-sourced here — it can only have come from the canonical document
     * via `LegacyMirrorProjection`, which is exactly the path this slice must not disturb.
     */
    public function test_the_managed_mirrors_are_written_in_the_same_format(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
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
    // 3 · THE BUYER FAMILY STILL WRITES NO ZIP MIRROR
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The ZIP tier is fully usable and reaches CANONICAL state — but the legacy `zipCodes` mirror
     * stays unwritten, because the Buyer family has never written it.
     *
     * This is the regression a ZIP tier invites and no pre-existing guard covers for this
     * component: G1f pins `Hire Buyer · create` and `Buyer Offer · create`, but only against a
     * component constructed without the cascade. Asserting `info('zipCodes')` is literally FALSE
     * — the value `info()` returns for a key that was never written — is what distinguishes
     * "not written" from "written as an empty array".
     */
    public function test_the_cascade_does_not_make_create_buyer_emit_a_zip_mirror(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoZipCodes', ['33708'])
            ->call('saveDraft');

        $record = $this->savedDraft($component)->fresh();

        $this->assertFalse(
            $record->info('zipCodes'),
            'The Buyer family must write no zipCodes mirror, even with a ZIP selected.'
        );

        // The selection is not lost — it lives in canonical state, which is where this family
        // has always kept it.
        $this->assertSame(['33708'], $this->canonicalOf($record)['zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · VALIDATION SEES THE CASCADE, NOT THE STALE BLOB
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_cascade_feeds_the_props_the_required_rules_read(): void
    {
        $this->enableCascade();

        $instance = Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->instance();

        $this->assertSame('Florida', $instance->state);
        $this->assertSame(['Pinellas County, FL'], $instance->counties);
    }

    /**
     * The PRE-VALIDATION hydrate sees the cascade, not the stale widget blob.
     *
     * Separate from the stored-data assertions on purpose: the persist-time projection also runs,
     * so a test that only checks what was written stays green even when the pre-validation call
     * is deleted. `store()` mutates the discrete props through the hydrate BEFORE it validates,
     * so reading them afterwards observes the ordering directly.
     */
    public function test_the_pre_validation_hydrate_sees_the_cascade_not_the_stale_blob(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
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

        $owner   = $this->buyer();
        $auction = $this->draft($owner, [
            'location_dna_preferences' => json_encode([
                'state'     => 'Florida',
                'counties'  => ['Pinellas County, FL'],
                'cities'    => ['St. Petersburg, FL'],
                'zip_codes' => ['33708'],
            ]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(EditBuyer::class, ['auctionId' => $auction->id])
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

        $owner   = $this->buyer();
        $auction = $this->draft($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
            ]),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(EditBuyer::class, ['auctionId' => $auction->id]);

        $this->assertSame(
            ['Ye Olde County, FL'],
            $component->instance()->geoPreserved['counties'],
        );

        $component->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->call('saveDraftOnly');

        $newest = BuyerAgentAuction::where('user_id', $owner->id)->latest('id')->firstOrFail();

        $this->assertSame(
            ['Pinellas County, FL', 'Hillsborough County, FL', 'Ye Olde County, FL'],
            $this->canonicalOf($newest)['counties'],
            'Selected counties lead, preserved history trails, and nothing is lost.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6 · THE OTHER ROLES THIS COMPONENT SERVES STAY BLOCKED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `BuyerOfferListing` is a four-role switch: its root blade picks a different property tab per
     * `user_type`, and a loaded record overwrites the default. Only buyer produces a workflow key,
     * so no scope-list value can reach the others — asserted with every plausible key in scope,
     * including invented ones.
     *
     * The map is driven directly rather than by rendering the other roles, for the same reason as
     * the Create Tenant suite: those role branches route to tabs that read properties this
     * component never declared, and have always failed to render. That is a pre-existing defect on
     * a path production never takes, and it is not what this test is measuring.
     */
    public function test_the_other_roles_this_component_serves_stay_blocked(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => [
                'hire_buyer', 'hire_tenant', 'create_tenant', 'create_buyer',
                'create_seller', 'create_landlord',
            ],
        ]);

        $instance = Livewire::actingAs($this->buyer())
            ->test(CreateBuyer::class)
            ->instance();

        $this->assertTrue($instance->geoCascadeEnabled, 'sanity: the buyer role is genuinely open');

        $workflow = new \ReflectionMethod($instance, 'geographyCascadeWorkflow');
        $workflow->setAccessible(true);

        $role = new \ReflectionProperty($instance, 'user_type');
        $role->setAccessible(true);

        foreach (['seller', 'landlord', 'tenant', '', null] as $other) {
            $role->setValue($instance, $other);

            $this->assertNull(
                $workflow->invoke($instance),
                'Create Buyer must produce no workflow key for role '.var_export($other, true)
            );
            $this->assertFalse(
                $instance->geographyCascadeIsEnabledFor($workflow->invoke($instance)),
                'A null workflow must never resolve to enabled.'
            );
        }
    }

    /** The tab is included by four other Offer blades whose components carry no trait. */
    public function test_the_tab_tolerates_a_host_without_the_cascade_property(): void
    {
        $tab = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php'
        ));

        $this->assertStringContainsString('@if ($geoCascadeEnabled ?? false)', $tab);
        $this->assertStringContainsString(
            '\'ldnaGeographyCascade\'   => $geoCascadeEnabled ?? false,',
            $tab,
        );
    }
}
