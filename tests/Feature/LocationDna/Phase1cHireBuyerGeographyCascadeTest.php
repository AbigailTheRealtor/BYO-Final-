<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Http\Livewire\TenantAgentAuction as HireCatchAllCreate;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1c slice 1 — the Hire Buyer geography cascade, end to end.
 *
 * WHAT THIS SUITE IS FOR
 * ----------------------
 * The unit suites prove the projector and the hydrator in isolation. This proves the three things
 * only the real component, the real Blade tree and the real reference tables can answer:
 *
 *   1. THE FLAG IS A REAL SWITCH. Off, the workflow is byte-identical to before. On, the cascade
 *      replaces the legacy geography inputs — and does so for THIS workflow only, while the other
 *      surfaces that share the same widget partial keep theirs.
 *   2. THE STORED FORMAT DID NOT CHANGE. A cascade selection lands in the canonical blob and the
 *      legacy mirrors in exactly the label format the widget has always written. If it did not,
 *      every historical record would silently stop matching and nothing would report it.
 *   3. HISTORY SURVIVES. A stored label the corpus cannot match is still there after an edit that
 *      never touched it.
 *
 * Reference rows are inserted directly rather than through factories — these are reference tables,
 * not domain models, and several have no factory. That is the same choice
 * `EloquentCriteriaGeographyRepositoryTest` made.
 */
class Phase1cHireBuyerGeographyCascadeTest extends TestCase
{
    use DatabaseTransactions;

    private int $florida;
    private int $pinellas;
    private int $hillsborough;
    private int $stPetersburg;
    private int $tampa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceCorpus();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FIXTURES
    // ═════════════════════════════════════════════════════════════════════════

    private function seedReferenceCorpus(): void
    {
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
        $this->tampa = (int) DB::table('us_cities')->insertGetId([
            'name' => 'Tampa', 'state_id' => $this->florida, 'county_id' => $this->hillsborough,
        ]);

        // ONE ROW PER ZIP, BECAUSE THE SCHEMA ALLOWS NOTHING ELSE.
        //
        // `us_zip_codes.zip_code` carries a UNIQUE constraint, so a ZIP cannot be stored against
        // two counties and the cross-county ZCTA case the resolver's association rule exists for
        // is not representable here at all. That rule is real and still load-bearing — it is
        // exercised against the fake corpus by `GeographySelectionResolverTest` and
        // `GeographySelectionValidatorTest`, which can express the multi-parent shape this table
        // cannot. What this fixture covers is the single-parent behaviour the live data can
        // actually produce.
        foreach ([
            ['33708', 'Pinellas'],
            ['33777', 'Pinellas'],
            ['33602', 'Hillsborough'],
        ] as [$zip, $county]) {
            DB::table('us_zip_codes')->insert([
                'zip_code'     => $zip,
                'city'         => $county,
                'state_abbrev' => 'FL',
                'state_name'   => 'Florida',
                'county'       => $county,
            ]);
        }
    }

    private function enableCascade(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer'],
        ]);
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function draftAuction(User $user, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $user->id,
            'address'     => '',
            'title'       => 'Cascade Fixture',
            'is_draft'    => true,
            'is_approved' => false,
            'is_sold'     => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type'                    => 'buyer',
            'workflow_type'                => 'hire_agent',
            'property_items'               => '[]',
            'condition_prop_buyer'         => '[]',
            'garage_parking_spaces_option' => '[]',
            'assets'                       => '[]',
        ], $meta);

        $rows = [];
        foreach ($meta as $key => $value) {
            $rows[] = [
                'buyer_agent_auction_id' => $auction->id,
                'meta_key'               => $key,
                'meta_value'             => $value,
            ];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return $auction;
    }

    /** The record a Create-flow draft save produced, read back from the component's own id. */
    private function savedDraft(object $component): BuyerAgentAuction
    {
        $id = $component->instance()->listingId;

        $this->assertNotEmpty($id, 'saveDraft() must have produced a listing.');

        return BuyerAgentAuction::findOrFail($id);
    }

    private function canonical(BuyerAgentAuction $auction): array
    {
        return json_decode((string) $auction->fresh()->info('location_dna_preferences'), true) ?: [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE FLAG IS A REAL SWITCH, SCOPED TO ONE WORKFLOW
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Off is the shipped default, and off means nothing at all changed.
     *
     * Asserted on the MARKUP (`id="…"`) rather than on the bare id string. The widget's own
     * script block names these ids too, so a bare-string assertion would pass whether or not the
     * inputs were rendered — it would assert nothing at all in the direction that matters.
     */
    public function test_the_legacy_geography_inputs_render_when_the_flag_is_off(): void
    {
        Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->assertSee('id="ldna-counties-input"', false)
            ->assertSee('id="ldna-cities-input"', false)
            ->assertSee('id="ldna-state-input"', false)
            ->assertSee('id="ldna-zips-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    /**
     * On, the cascade stands in the legacy inputs' place.
     *
     * Asserted as a REPLACEMENT rather than an addition: two live geography editors writing the
     * same four blob keys would race, and whichever serialised last would win silently.
     */
    public function test_the_cascade_replaces_the_legacy_geography_inputs_when_enabled(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->assertSee('id="geo-cascade-state"', false)
            ->assertSee('id="geo-cascade-counties"', false)
            ->assertSee('id="geo-cascade-cities"', false)
            ->assertSee('id="geo-cascade-zips"', false)
            ->assertDontSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="ldna-cities-input"', false)
            ->assertDontSee('id="ldna-state-input"', false)
            ->assertDontSee('id="ldna-zips-input"', false);
    }

    /** The map itself is untouched — only the four geography tiers move. */
    public function test_the_map_and_its_other_tools_survive_the_swap(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->assertSee('ldna-map-hire-buyer')
            ->assertSee('Important Places')
            ->assertSee('ldna-draw-btn-polygon');
    }

    /**
     * Enabling Hire Buyer changes nothing for a workflow that is not in the scope list.
     *
     * This is the assertion behind "the third-party geography autocomplete is removed from the
     * enabled workflow only". The Hire Tenant surface shares the same widget partial and must
     * still render its own geography inputs while the master switch is on.
     */
    public function test_a_workflow_outside_the_scope_list_keeps_the_legacy_widget(): void
    {
        $this->enableCascade();

        Livewire::actingAs(User::factory()->create(['user_type' => 'tenant']))
            ->test(HireCatchAllCreate::class, ['user_type' => 'tenant'])
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    /**
     * The Buyer tab partial must tolerate a host that declares no `$geoCascadeEnabled`.
     *
     * A REGRESSION TEST WITH A SCAR, RETARGETED
     * -----------------------------------------
     * The first cut of this partial read that variable unguarded, and fataled every host that did
     * not declare it with an undefined-variable error — whether the flag was on or off.
     *
     * When this test was written, the host it protected was the catch-all Hire component. Slice 1b
     * then wired the catch-all deliberately, so that is no longer the uncovered host and the
     * behavioural coverage moved to
     * {@see \Tests\Feature\LocationDna\Phase1cCatchAllHireBuyerCascadeTest}. The guard itself is
     * still load-bearing: `SellerAgentAuction` and `LandLordAgentAuction` both include this same
     * Buyer tab from a role branch of their own root blade, and neither carries the cascade trait.
     * Those branches are not taken today, which is precisely why a render test cannot cover them —
     * so the guard is asserted where it lives.
     */
    public function test_the_buyer_tab_tolerates_a_host_without_the_cascade_property(): void
    {
        $tab = file_get_contents(base_path(
            'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php'
        ));

        $this->assertStringContainsString(
            '@if ($geoCascadeEnabled ?? false)',
            $tab,
            'The cascade block must default to off for a host that declares no such property.'
        );
        $this->assertStringContainsString(
            '\'ldnaGeographyCascade\'    => $geoCascadeEnabled ?? false,',
            $tab,
            'The widget opt-in must default to off for the same reason.'
        );
    }

    /** Master switch on but the workflow unlisted ⇒ still legacy. Both must agree. */
    public function test_the_master_switch_alone_does_not_enable_a_workflow(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_tenant'],
        ]);

        Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE CASCADE BEHAVES AS THE RULES LAYER SPECIFIES
    // ═════════════════════════════════════════════════════════════════════════

    public function test_choosing_a_state_offers_its_counties(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida);

        $names = array_column($component->instance()->geoCountyOptions(), 'name');

        $this->assertSame(['Hillsborough County', 'Pinellas County'], $names);
    }

    public function test_choosing_counties_offers_their_cities_and_zips(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas]);

        $this->assertSame(['St. Petersburg'], array_column($component->instance()->geoCityOptions(), 'name'));
        $this->assertSame(['33708', '33777'], array_column($component->instance()->geoZipOptions(), 'id'));
    }

    /** C1 — a new state cannot justify the old state's counties, so they go. */
    public function test_changing_the_state_clears_everything_below_it(): void
    {
        $this->enableCascade();

        $other = (int) DB::table('us_states')->insertGetId([
            'name' => 'Georgia', 'abbreviation' => 'GA', 'fips_code' => '13',
        ]);

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->set('geoStateId', (string) $other);

        $instance = $component->instance();

        $this->assertSame([], $instance->geoCountyIds);
        $this->assertSame([], $instance->geoCityIds);
        $this->assertNotEmpty($instance->geoCleared, 'The user must be told what was cleared.');
    }

    /** A ZIP still justified by a surviving county is kept when an unrelated county is dropped. */
    public function test_a_still_justified_zip_survives_an_unrelated_county_removal(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->set('geoZipCodes', ['33708'])
            ->set('geoCountyIds', [(string) $this->pinellas]);

        $this->assertSame(['33708'], $component->instance()->geoZipCodes);
    }

    /** A ZIP left with no justifying county IS cleared — the other half of the same rule. */
    public function test_an_orphaned_zip_is_cleared(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->set('geoZipCodes', ['33708'])
            ->set('geoCountyIds', [(string) $this->hillsborough]);

        $this->assertSame([], $component->instance()->geoZipCodes);
    }

    /** Completeness gates submission; validity alone does not. A state-only pick is valid. */
    public function test_a_state_without_a_county_is_valid_but_incomplete(): void
    {
        $this->enableCascade();

        $instance = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->instance();

        $this->assertFalse($instance->geographyIsComplete());
    }

    public function test_a_state_and_a_county_is_complete(): void
    {
        $this->enableCascade();

        $instance = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->instance();

        $this->assertTrue($instance->geographyIsComplete());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE STORED FORMAT DID NOT CHANGE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The whole compatibility argument, in one assertion.
     *
     * `Pinellas County, FL` — not `Pinellas County`, which is what the reference corpus holds.
     * Emitting the corpus form would break every historical comparison with no error anywhere.
     */
    public function test_a_cascade_selection_is_stored_in_the_legacy_label_format(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->set('geoCityIds', [(string) $this->tampa])
            ->set('geoZipCodes', ['33708'])
            ->call('saveDraft');

        $canonical = $this->canonical($this->savedDraft($component));

        $this->assertSame('Florida', $canonical['state']);
        $this->assertSame(['Pinellas County, FL', 'Hillsborough County, FL'], $canonical['counties']);
        $this->assertSame(['Tampa, FL'], $canonical['cities']);
        $this->assertSame(['33708'], $canonical['zip_codes']);
    }

    /** The legacy mirrors are derived from that same canonical state, unchanged in shape. */
    public function test_the_legacy_mirrors_are_written_in_the_same_format(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoCityIds', [(string) $this->stPetersburg])
            ->call('saveDraft');

        $auction = $this->savedDraft($component)->fresh();

        $this->assertSame('Florida', $auction->info('state'));
        $this->assertSame(['Pinellas County, FL'], json_decode((string) $auction->info('counties'), true));
        $this->assertSame(['St. Petersburg, FL'], json_decode((string) $auction->info('cities'), true));
    }

    /**
     * Hire Buyer must still write NO `zipCodes` mirror.
     *
     * The Buyer family has never emitted that key, and `LegacyMirrorProjection`'s default managed
     * set deliberately excludes it. A cascade with a ZIP tier is exactly the change that would
     * tempt someone to add it — which would make a workflow start writing a mirror it has never
     * written. G1f pins this independently; it is re-asserted here at the slice boundary.
     */
    public function test_the_cascade_does_not_make_hire_buyer_emit_a_zipcodes_mirror(): void
    {
        $this->enableCascade();

        $component = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->set('geoZipCodes', ['33708'])
            ->call('saveDraft');

        $auction = $this->savedDraft($component)->fresh();

        $this->assertFalse(
            (bool) $auction->info('zipCodes'),
            'Hire Buyer has never written a zipCodes mirror and must not start.'
        );
        $this->assertSame(['33708'], $this->canonical($auction)['zip_codes']);
    }

    /** The discrete props the existing validation rules read are fed by the cascade. */
    public function test_the_cascade_feeds_the_discrete_props_validation_reads(): void
    {
        $this->enableCascade();

        $instance = Livewire::actingAs($this->buyer())
            ->test(HireBuyerCreate::class)
            ->set('geoStateId', (string) $this->florida)
            ->set('geoCountyIds', [(string) $this->pinellas])
            ->instance();

        $this->assertSame('Florida', $instance->state);
        $this->assertSame(['Pinellas County, FL'], $instance->counties);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · HISTORY SURVIVES
    // ═════════════════════════════════════════════════════════════════════════

    public function test_edit_load_hydrates_the_cascade_from_stored_labels(): void
    {
        $this->enableCascade();

        $owner   = $this->buyer();
        $auction = $this->draftAuction($owner, [
            'location_dna_preferences' => json_encode([
                'state'     => 'Florida',
                'counties'  => ['Pinellas County, FL'],
                'cities'    => ['St. Petersburg, FL'],
                'zip_codes' => ['33708'],
            ]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireBuyerEdit::class, ['auctionId' => $auction->id])
            ->instance();

        $this->assertSame((string) $this->florida, $instance->geoStateId);
        $this->assertSame([(string) $this->pinellas], $instance->geoCountyIds);
        $this->assertSame([(string) $this->stPetersburg], $instance->geoCityIds);
        $this->assertSame(['33708'], $instance->geoZipCodes);
    }

    /**
     * The data-safety guarantee, exercised through the real edit flow.
     *
     * A county the corpus cannot match must still be in the record after an edit that never went
     * near it. Losing it here would be silent, permanent, and indistinguishable from the user
     * having removed it themselves.
     */
    public function test_an_unmatched_stored_county_survives_an_unrelated_edit(): void
    {
        $this->enableCascade();

        $owner   = $this->buyer();
        $auction = $this->draftAuction($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
            ]),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(HireBuyerEdit::class, ['auctionId' => $auction->id]);

        $this->assertSame(
            ['Ye Olde County, FL'],
            $component->instance()->geoPreserved['counties'],
            'An unmatched label must be surfaced as preserved history, not dropped.'
        );

        // Shaped so a save that never happened cannot satisfy it: a NEW county is selected, and
        // the exact resulting order is asserted. `assertContains` against the untouched original
        // document would pass even if the write silently bailed — and did, until a mutation probe
        // that deleted preservation outright survived this test.
        $component->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->call('saveDraft');

        $newest = BuyerAgentAuction::where('user_id', $owner->id)->latest('id')->firstOrFail();

        $this->assertSame(
            ['Pinellas County, FL', 'Hillsborough County, FL', 'Ye Olde County, FL'],
            $this->canonical($newest)['counties'],
            'Selected counties lead, preserved history trails, and nothing is lost.'
        );
    }

    /** An unknown state preserves the whole selection rather than emptying the record. */
    public function test_an_unknown_stored_state_preserves_everything_below_it(): void
    {
        $this->enableCascade();

        $owner   = $this->buyer();
        $auction = $this->draftAuction($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Atlantis',
                'counties' => ['Poseidon County, AT'],
            ]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(HireBuyerEdit::class, ['auctionId' => $auction->id])
            ->instance();

        $this->assertSame('', (string) $instance->geoStateId);
        $this->assertSame('Atlantis', $instance->geoPreserved['state']);
        $this->assertSame(['Poseidon County, AT'], $instance->geoPreserved['counties']);
    }
}
