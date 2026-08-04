<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as DedicatedBuyerCreate;
use App\Http\Livewire\TenantAgentAuction as CatchAllCreate;
use App\Http\Livewire\TenantAgentAuctionEdit as CatchAllEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1c slice 1b — the catch-all Hire Agent component, wired for Hire Buyer.
 *
 * WHY THIS SUITE EXISTS SEPARATELY FROM THE DEDICATED-COMPONENT ONE
 * -----------------------------------------------------------------
 * A route audit found that Hire Buyer is not one surface but three, served by two component trees:
 *
 *   /buyer/add-auction              → HireBuyerAgent\BuyerAgentAuction   (a buyer creating)
 *   /hire/agent/auction/buyer       → TenantAgentAuction                 (an agent creating)
 *   /buyer/agent/auction/edit/{id}  → TenantAgentAuctionEdit             (every edit, both audiences)
 *
 * `HireBuyerAgent\BuyerAgentAuctionEdit` is imported by routes/web.php but never routed, so the
 * catch-all edit component is the ONLY reachable Hire Buyer edit surface. Wiring the dedicated
 * create component alone would have produced a listing created with the cascade and edited with the
 * legacy editor — a broken round trip inside one user's own flow, invisible from the create routes.
 *
 * So this suite asserts the three things the other one cannot:
 *
 *   1. The catch-all's buyer role gets the cascade, and its stored output is byte-identical to the
 *      dedicated component's. That is what "one experience" means when stated as data.
 *   2. Edit round-trips: a listing created with the cascade opens in the cascade and re-saves
 *      without drift.
 *   3. Seller and landlord — served by the SAME class — are completely unaffected, including
 *      their save path.
 */
class Phase1cCatchAllHireBuyerCascadeTest extends TestCase
{
    use DatabaseTransactions;

    private int $florida;
    private int $pinellas;
    private int $hillsborough;
    private int $tampa;

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
        $this->tampa = (int) DB::table('us_cities')->insertGetId([
            'name' => 'Tampa', 'state_id' => $this->florida, 'county_id' => $this->hillsborough,
        ]);

        DB::table('us_zip_codes')->insert([
            'zip_code' => '33602', 'city' => 'Hillsborough', 'state_abbrev' => 'FL',
            'state_name' => 'Florida', 'county' => 'Hillsborough',
        ]);
    }

    private function enableCascade(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer'],
        ]);
    }

    private function user(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    /** @return array{0: string, 1: array<int, string>, 2: array<int, string>} */
    private function selection(): array
    {
        return [
            (string) $this->florida,
            [(string) $this->pinellas, (string) $this->hillsborough],
            [(string) $this->tampa],
        ];
    }

    private function buyerDraft(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'address' => '', 'title' => 'Catch-all fixture',
            'is_draft' => true, 'is_approved' => false, 'is_sold' => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type' => 'buyer', 'workflow_type' => 'hire_agent',
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

    private function canonicalOf(object $record): array
    {
        return json_decode((string) $record->fresh()->info('location_dna_preferences'), true) ?: [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE CANONICAL ENTRY POINT GETS THE CASCADE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_catch_all_buyer_role_renders_the_cascade_when_enabled(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->user('buyer'))
            ->test(CatchAllCreate::class, ['user_type' => 'buyer'])
            ->assertSee('id="geo-cascade-state"', false)
            ->assertSee('id="geo-cascade-counties"', false)
            ->assertDontSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="ldna-state-input"', false);
    }

    /** Off is still off on this surface too — the flag governs both trees identically. */
    public function test_the_catch_all_buyer_role_keeps_the_legacy_editor_when_disabled(): void
    {
        Livewire::actingAs($this->user('buyer'))
            ->test(CatchAllCreate::class, ['user_type' => 'buyer'])
            ->assertSee('id="ldna-counties-input"', false)
            ->assertDontSee('id="geo-cascade-state"', false);
    }

    /**
     * THE POINT OF THE WHOLE SLICE, STATED AS DATA.
     *
     * The same geography chosen through the two create entry points must produce the same stored
     * document. Not "both work" — byte-identical for every geography key. Anything less means a
     * buyer's listing differs depending on which link they clicked, which is the defect this
     * work exists to remove.
     */
    public function test_both_buyer_entry_points_store_identical_geography(): void
    {
        $this->enableCascade();
        [$state, $counties, $cities] = $this->selection();

        // Entry point A — the buyer's own route, dedicated component.
        $ownerA     = $this->user('buyer');
        $componentA = Livewire::actingAs($ownerA)
            ->test(DedicatedBuyerCreate::class)
            ->set('geoStateId', $state)
            ->set('geoCountyIds', $counties)
            ->set('geoCityIds', $cities)
            ->call('saveDraft');
        $recordA = BuyerAgentAuction::findOrFail($componentA->instance()->listingId);

        // Entry point B — the agent-facing route, catch-all component.
        $ownerB     = $this->user('buyer');
        $componentB = Livewire::actingAs($ownerB)
            ->test(CatchAllCreate::class, ['user_type' => 'buyer'])
            ->set('geoStateId', $state)
            ->set('geoCountyIds', $counties)
            ->set('geoCityIds', $cities)
            ->call('saveDraft');
        $recordB = BuyerAgentAuction::where('user_id', $ownerB->id)->latest('id')->firstOrFail();

        $a = $this->canonicalOf($recordA);
        $b = $this->canonicalOf($recordB);

        foreach (['state', 'counties', 'cities', 'zip_codes'] as $key) {
            $this->assertSame(
                $a[$key] ?? null,
                $b[$key] ?? null,
                "`{$key}` differs between the two Hire Buyer entry points."
            );
        }

        $this->assertSame('Florida', $a['state']);
        $this->assertSame(['Pinellas County, FL', 'Hillsborough County, FL'], $a['counties']);
        $this->assertSame(['Tampa, FL'], $a['cities']);
    }

    /**
     * The catch-all must NOT start emitting a real `zipCodes` mirror for a buyer.
     *
     * This class writes `$this->zipCodes` to meta unconditionally for every role, and today that
     * value is always `[]`. Mirroring the cascade's ZIP selection into it would make a listing
     * created here diverge in stored data from the same listing created through the dedicated
     * component, which writes no `zipCodes` key at all — the very divergence this slice removes,
     * reintroduced where no existing guard would catch it. `HasGeographyCascade` opts only
     * `hire_tenant` into that mirror.
     */
    public function test_the_catch_all_does_not_start_mirroring_zips_for_a_buyer(): void
    {
        $this->enableCascade();
        [$state, $counties] = $this->selection();

        $owner = $this->user('buyer');

        Livewire::actingAs($owner)
            ->test(CatchAllCreate::class, ['user_type' => 'buyer'])
            ->set('geoStateId', $state)
            ->set('geoCountyIds', $counties)
            ->set('geoZipCodes', ['33602'])
            ->call('saveDraft');

        $record = BuyerAgentAuction::where('user_id', $owner->id)->latest('id')->firstOrFail();

        $this->assertSame(
            json_encode([]),
            (string) $record->fresh()->info('zipCodes'),
            'The legacy zipCodes mirror must keep its pre-cascade value for Hire Buyer.'
        );

        // The ZIP is still canonical — only the LEGACY mirror is left alone.
        $this->assertSame(['33602'], $this->canonicalOf($record)['zip_codes']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · EDIT ROUND-TRIP — THE SURFACE THE CREATE ROUTES CANNOT SHOW
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The only reachable Hire Buyer edit surface opens the cascade, hydrated from stored labels.
     *
     * Without this, a listing created with the cascade would open in the legacy editor.
     */
    public function test_the_only_edit_surface_hydrates_the_cascade(): void
    {
        $this->enableCascade();

        $owner   = $this->user('buyer');
        $auction = $this->buyerDraft($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL'],
            ]),
        ]);

        $instance = Livewire::actingAs($owner)
            ->test(CatchAllEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer'])
            ->instance();

        $this->assertTrue($instance->geoCascadeEnabled);
        $this->assertSame((string) $this->florida, $instance->geoStateId);
        $this->assertSame([(string) $this->pinellas], $instance->geoCountyIds);
    }

    /**
     * The stored user_type wins over the route's default.
     *
     * `buyer.edit-auction` defaults `user_type` to `buyer`, but the record's own meta is what
     * decides which workflow a listing belongs to. If the route default won, a tenant listing
     * opened through that URL would be governed by the wrong workflow key.
     */
    public function test_the_stored_user_type_decides_the_workflow_not_the_route_default(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer'],
        ]);

        $owner   = $this->user('buyer');
        $auction = $this->buyerDraft($owner, ['user_type' => 'seller']);

        $instance = Livewire::actingAs($owner)
            ->test(CatchAllEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer'])
            ->instance();

        $this->assertFalse(
            $instance->geoCascadeEnabled,
            'A record stored as seller must not be governed by the buyer workflow key.'
        );
    }

    /** An unmatched legacy label survives an edit through the catch-all, same as anywhere else. */
    public function test_unmatched_history_survives_an_edit_through_the_catch_all(): void
    {
        $this->enableCascade();

        $owner   = $this->user('buyer');
        $auction = $this->buyerDraft($owner, [
            'location_dna_preferences' => json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
            ]),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(CatchAllEdit::class, ['auctionId' => $auction->id, 'user_type' => 'buyer']);

        $this->assertSame(
            ['Ye Olde County, FL'],
            $component->instance()->geoPreserved['counties'],
        );

        // A REAL EDIT, NOT A NO-OP — and the assertion is shaped so a no-op cannot satisfy it.
        //
        // `update()` runs a required-field gate and returns without writing when the fixture is
        // incomplete, so a test that only asserted "the preserved label is still there" would pass
        // by reading back the ORIGINAL stored document. A mutation probe that deleted preservation
        // outright survived that version of this test. Selecting a NEW county and asserting the
        // exact resulting order means the assertion fails if the save did not happen, fails if
        // preservation was dropped, and fails if the two were ordered the other way round.
        $component->set('geoCountyIds', [(string) $this->pinellas, (string) $this->hillsborough])
            ->call('saveDraftOnly');

        $this->assertSame(
            ['Pinellas County, FL', 'Hillsborough County, FL', 'Ye Olde County, FL'],
            $this->canonicalOf($auction)['counties'],
            'Selected counties lead, preserved history trails, and nothing is lost.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · SELLER AND LANDLORD — SERVED BY THE SAME CLASS, AND UNTOUCHED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The role gate is stronger than the config: no scope-list value can enable these roles,
     * because the workflow map produces no key for them at all.
     */
    public function test_seller_and_landlord_are_disabled_even_with_every_workflow_in_scope(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => [
                'hire_buyer', 'hire_tenant', 'hire_seller', 'hire_landlord',
            ],
        ]);

        foreach (['seller', 'landlord'] as $role) {
            $instance = Livewire::actingAs($this->user($role))
                ->test(CatchAllCreate::class, ['user_type' => $role])
                ->instance();

            $this->assertFalse(
                $instance->geoCascadeEnabled,
                "The cascade must be unreachable for the {$role} role."
            );
        }
    }

    public function test_seller_renders_no_cascade_markup_with_the_flag_on(): void
    {
        $this->enableCascade();

        Livewire::actingAs($this->user('seller'))
            ->test(CatchAllCreate::class, ['user_type' => 'seller'])
            ->assertDontSee('id="geo-cascade-state"', false)
            ->assertDontSee('id="geo-cascade-counties"', false);
    }

    /**
     * The seller SAVE path is unchanged: the existing user_type gate still routes seller records
     * to the property-sourced discrete mirror writes, and no canonical document appears.
     *
     * That gate predates this work and is explicitly out of scope; asserting it here is what stops
     * the cascade wiring from being blamed for, or accidentally causing, a change to it.
     */
    public function test_the_seller_save_path_is_unchanged(): void
    {
        $this->enableCascade();

        $owner   = $this->user('seller');
        $auction = (new SellerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'title' => 'Seller fixture',
            'is_draft' => true, 'is_approved' => false, 'is_sold' => false,
        ]);
        $auction->save();
        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'user_type', 'meta_value' => 'seller'],
        ]);

        // `counties` only, deliberately. Setting `state` would fire this component's legacy
        // state-autocomplete handler, which calls UsState::search() and therefore ILIKE — a
        // Postgres operator SQLite rejects, and a pre-existing suite failure unrelated to this
        // work. The claim under test does not need it: `counties` is property-sourced through the
        // same else-branch.
        Livewire::actingAs($owner)
            ->test(CatchAllCreate::class, ['user_type' => 'seller'])
            ->set('listing_title', 'Seller fixture')
            ->set('counties', ['Pinellas County, FL'])
            ->call('saveDraft');

        $stored = SellerAgentAuction::where('user_id', $owner->id)->latest('id')->firstOrFail();

        // Property-sourced, exactly as the gate's else-branch has always written it — and NOT
        // projected from a cascade, which would have produced an empty list here.
        $this->assertSame(
            ['Pinellas County, FL'],
            json_decode((string) $stored->info('counties'), true),
            'The seller mirror must still come from the component property.'
        );

        // The else-branch ran: it writes the `state` key unconditionally, so the key exists even
        // though the value is blank. `info()` returns false for a key that was never written.
        $this->assertNotFalse(
            $stored->info('state'),
            'The seller branch must still write its discrete state mirror.'
        );

        // And no canonical document — seller records have never carried one, and the cascade
        // wiring must not give them one.
        $this->assertFalse(
            $stored->info('location_dna_preferences'),
            'A seller record must not acquire a canonical Location DNA document.'
        );
    }
}
