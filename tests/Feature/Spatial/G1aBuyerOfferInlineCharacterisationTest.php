<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1a residue — direct characterisation of the TWO BUYER OFFER INLINE IMPLEMENTATIONS.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * The first G1a increment characterised the `HasSearchAreas` trait through a thin
 * host. `BuyerOfferListing` and `BuyerOfferListingEdit` do NOT use that trait —
 * each carries its own inline copy of the load/save/mirror logic. No test in the
 * repository executed either copy's presence semantics, so §16.3's rule
 * ("a workflow with no characterisation may not be migrated") blocked G1f for both.
 *
 * The pre-implementation report listed their correctness as *"to be characterised"*.
 * This suite answers that question.
 *
 * THE ANSWER — F-G1-1 RESOLVED
 * ----------------------------
 * Both inline copies are **behaviourally equivalent to the trait**, including all
 * five presence-guard defects. They are NOT divergent-and-correct like the two
 * Tenant Offer copies. Confirmed by reading the source and, below, by executing it:
 *
 *   BuyerOfferListing.php:1940      `if (empty($ldna['cities'] ?? [])) {`
 *   BuyerOfferListing.php:1961      `if (empty($this->existingLocationDna['state'] ?? '')`
 *   BuyerOfferListing.php:1964      `if (empty($this->existingLocationDna['counties'] ?? [])`
 *   BuyerOfferListing.php:2434      `if (trim((string) ($ldna['state'] ?? '')) !== '')`
 *   BuyerOfferListing.php:2437      `if (!empty($ldna['counties'] ?? []))`
 *
 * and the same five in `BuyerOfferListingEdit.php`. So the corrected mirror
 * inventory is **three defective implementations** (trait + both Buyer Offer
 * copies) and **two correct** (both Tenant Offer copies) — not "one defective and
 * four identical copies".
 *
 * WHY THE COMPONENT METHODS ARE CALLED DIRECTLY
 * ---------------------------------------------
 * `loadDraft()` / `loadAuctionData()` are invoked on real component instances, and
 * `saveAllMetadata()` through reflection — the pattern `TenantOfferCitiesMirrorTest`
 * established. Booting the full Livewire lifecycle would drag in hundreds of
 * unrelated required props and characterise validation rather than the mirror
 * contract. The methods under test, the model, its meta table and its
 * saveMeta/info implementations are all real.
 *
 * Both load entry points scope by `Auth::id()`, so the owning user must be
 * authenticated or the record is simply not found and the hydration block never
 * runs — which would make every assertion here pass vacuously. `actingAs()` is
 * therefore load-bearing, not incidental.
 *
 * CHARACTERISATION, NOT REPAIR
 * ----------------------------
 * Every assertion records what the code does TODAY. Where that contradicts v1.2
 * §5.2 the contradiction is asserted and named. Nothing here asserts the desired
 * end state, and no owner decision D-G1-1 … D-G1-6 is assumed.
 *
 * SCOPE BOUNDARY
 * --------------
 * PHP and database only. The blob is produced in the browser by
 * `window.ldnaSerialize`; nothing here executes it.
 */
class G1aBuyerOfferInlineCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /**
     * A real saved Buyer Offer listing.
     *
     * `buyer_agent_auctions` has no factory, so the row is built with `forceFill()`
     * — the vehicle `HireSearchAreasParityTest` established. The base meta mirrors
     * a real saved record: the components read `user_type` from meta, and several
     * array-valued keys are rendered through `in_array()`, so a real record always
     * has them present as arrays.
     */
    private function auction(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'address'     => '',
            'title'       => 'G1a Buyer Offer characterisation',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type'                    => 'buyer',
            'workflow_type'                => 'offer_listing',
            'property_items'               => '[]',
            'condition_prop_buyer'         => '[]',
            'garage_parking_spaces_option' => '[]',
            'assets'                       => '[]',
        ], $meta);

        $rows = [];
        foreach ($meta as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread(BuyerAgentAuction $auction): BuyerAgentAuction
    {
        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    /** Hydrate through the real create/draft entry point. */
    private function hydrateCreate(BuyerAgentAuction $auction, User $owner): BuyerOfferListing
    {
        $this->actingAs($owner);

        $component = new BuyerOfferListing();
        $component->loadDraft($auction->id);

        return $component;
    }

    /** Hydrate through the real edit entry point. */
    private function hydrateEdit(BuyerAgentAuction $auction, User $owner): BuyerOfferListingEdit
    {
        $this->actingAs($owner);

        $component = new BuyerOfferListingEdit();
        $component->loadAuctionData($auction->id);

        return $component;
    }

    /** Invoke a component's protected save path. */
    private function save(object $component, BuyerAgentAuction $auction): void
    {
        $method = new ReflectionMethod($component::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // LOAD · the three-state boundary, on BOTH inline implementations
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED DEFECT · create flow — a cleared `cities` is resurrected.
     *
     * `BuyerOfferListing`'s own inline `empty()` branch behaves exactly as the
     * trait's site 48 does: the blob says "explicitly cleared" and the legacy
     * mirror overrides it.
     */
    public function test_create_flow_cleared_cities_are_resurrected_from_the_mirror(): void
    {
        $owner   = $this->owner();
        $auction = $this->auction($owner, [
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => json_encode(['Tampa', 'Miami']),
        ]);

        $component = $this->hydrateCreate($auction, $owner);

        $this->assertSame(
            ['Tampa', 'Miami'],
            $component->existingLocationDna['cities'],
            'CHARACTERISATION: the Buyer Offer create-flow inline copy resurrects a cleared '
            .'cities list, exactly as the trait does. Contradicts §5.2.'
        );
    }

    /** CHARACTERISED DEFECT · edit flow — same resurrection. */
    public function test_edit_flow_cleared_cities_are_resurrected_from_the_mirror(): void
    {
        $owner   = $this->owner();
        $auction = $this->auction($owner, [
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => json_encode(['Tampa', 'Miami']),
        ]);

        $component = $this->hydrateEdit($auction, $owner);

        $this->assertSame(
            ['Tampa', 'Miami'],
            $component->existingLocationDna['cities'],
            'CHARACTERISATION: the Buyer Offer edit-flow inline copy resurrects a cleared cities list.'
        );
    }

    /**
     * The three-state boundary on the create flow, asserted in one test so it reads
     * as a boundary rather than three unrelated facts.
     */
    public function test_create_flow_three_state_boundary(): void
    {
        $owner  = $this->owner();
        $legacy = json_encode(['Tampa']);

        // (a) key absent → mirror legitimately consulted
        $absent = $this->hydrateCreate($this->auction($owner, [
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => $legacy,
        ]), $owner);
        $this->assertSame(['Tampa'], $absent->existingLocationDna['cities']);

        // (b) key present but EMPTY → mirror consulted anyway (the defect)
        $emptyKey = $this->hydrateCreate($this->auction($owner, [
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => $legacy,
        ]), $owner);
        $this->assertSame(['Tampa'], $emptyKey->existingLocationDna['cities']);

        // (c) key present and populated → blob wins
        $populated = $this->hydrateCreate($this->auction($owner, [
            'location_dna_preferences' => json_encode(['cities' => ['Orlando']]),
            'cities'                   => $legacy,
        ]), $owner);
        $this->assertSame(['Orlando'], $populated->existingLocationDna['cities']);
    }

    /** The identical boundary on the edit flow. */
    public function test_edit_flow_three_state_boundary(): void
    {
        $owner  = $this->owner();
        $legacy = json_encode(['Tampa']);

        $absent = $this->hydrateEdit($this->auction($owner, [
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => $legacy,
        ]), $owner);
        $this->assertSame(['Tampa'], $absent->existingLocationDna['cities']);

        $emptyKey = $this->hydrateEdit($this->auction($owner, [
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => $legacy,
        ]), $owner);
        $this->assertSame(['Tampa'], $emptyKey->existingLocationDna['cities']);

        $populated = $this->hydrateEdit($this->auction($owner, [
            'location_dna_preferences' => json_encode(['cities' => ['Orlando']]),
            'cities'                   => $legacy,
        ]), $owner);
        $this->assertSame(['Orlando'], $populated->existingLocationDna['cities']);
    }

    /**
     * CHARACTERISED DEFECT · a cleared `state` is overwritten by the discrete meta
     * on the edit flow.
     *
     * The Buyer Offer edit copy's site-71 analogue. `$this->state` is loaded from
     * the discrete `state` meta, and an `empty()` guard lets it overwrite a blob
     * that explicitly cleared the dimension.
     */
    public function test_edit_flow_cleared_state_is_overwritten_by_discrete_meta(): void
    {
        $owner   = $this->owner();
        $auction = $this->auction($owner, [
            'location_dna_preferences' => json_encode(['state' => '']),
            'state'                    => 'Georgia',
        ]);

        $component = $this->hydrateEdit($auction, $owner);

        $this->assertSame(
            'Georgia',
            $component->existingLocationDna['state'],
            'CHARACTERISATION: a cleared state is replaced by the discrete meta value.'
        );
    }

    /**
     * CHARACTERISED DEFECT · a cleared `counties` is overwritten by the discrete
     * meta on the edit flow — the site-77 analogue.
     */
    public function test_edit_flow_cleared_counties_are_overwritten_by_discrete_meta(): void
    {
        $owner   = $this->owner();
        $auction = $this->auction($owner, [
            'location_dna_preferences' => json_encode(['counties' => []]),
            'counties'                 => json_encode(['Cobb County, GA']),
        ]);

        $component = $this->hydrateEdit($auction, $owner);

        $this->assertSame(
            ['Cobb County, GA'],
            $component->existingLocationDna['counties'],
            'CHARACTERISATION: cleared counties are replaced by the discrete meta value.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SAVE · the three-different-ways defect, on BOTH inline implementations
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * REPAIRED BY G1f-3 · create flow — one save now records a cleared dimension
     * ONE way.
     *
     * THE DEFECT THIS REPLACES, RECORDED SO THE CHANGE IS LEGIBLE.
     * -----------------------------------------------------------
     * This test previously asserted F-G1-4's three-way split on the Buyer Offer
     * inline copy: `cities` honoured the clear while `counties` and `state` kept
     * their previous values, because the three dimensions did not share a code
     * path. It confirmed the defect was a property of all three implementations,
     * not of the shared trait alone — which is precisely why G1f's single writer
     * had to serve all three.
     *
     * G1f-3 made this workflow one of the writer's callers, so all three
     * dimensions are now projected from the same canonical document and a clear
     * takes effect uniformly. This is D-G1-4 option 4-A, arriving here.
     *
     * The component props still carry the stale values, deliberately: they must
     * NOT influence the persisted mirrors any more, and asserting against them is
     * what proves it.
     */
    public function test_create_flow_records_a_cleared_dimension_one_way(): void
    {
        $owner   = $this->owner();
        $auction = $this->auction($owner);

        $component        = new BuyerOfferListing();
        $component->state = 'Georgia';
        $component->counties = ['Pinellas'];
        $component->location_dna_preferences_json = json_encode([
            'cities'   => [],
            'counties' => [],
            'state'    => '',
        ]);

        $this->save($component, $auction);

        $fresh = $this->reread($auction);

        $this->assertSame('[]', $fresh->info('cities'), 'cities honours the clear');
        $this->assertSame(
            '[]',
            $fresh->info('counties'),
            'counties now honours the clear too — it used to keep the stale ["Pinellas"]'
        );
        $this->assertSame(
            '',
            $fresh->info('state'),
            'state now honours the clear too — it used to keep the stale "Georgia"'
        );
    }

    /** REPAIRED BY G1f-3 · edit flow — the identical uniform clear. */
    public function test_edit_flow_records_a_cleared_dimension_one_way(): void
    {
        $owner   = $this->owner();
        $auction = $this->auction($owner);

        $component        = new BuyerOfferListingEdit();
        $component->state = 'Georgia';
        $component->counties = ['Pinellas'];
        $component->location_dna_preferences_json = json_encode([
            'cities'   => [],
            'counties' => [],
            'state'    => '',
        ]);

        $this->save($component, $auction);

        $fresh = $this->reread($auction);

        $this->assertSame('[]', $fresh->info('cities'));
        $this->assertSame('[]', $fresh->info('counties'));
        $this->assertSame('', $fresh->info('state'));
    }

    /** A populated blob mirrors correctly on both flows — the control. */
    public function test_populated_blob_mirrors_correctly_on_both_flows(): void
    {
        $owner = $this->owner();

        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $class) {
            $auction = $this->auction($owner);

            $component = new $class();
            $component->location_dna_preferences_json = json_encode([
                'cities'   => ['Tampa', 'Orlando'],
                'counties' => ['Hillsborough'],
                'state'    => 'FL',
            ]);

            $this->save($component, $auction);

            $fresh = $this->reread($auction);

            $this->assertSame('["Tampa","Orlando"]', $fresh->info('cities'), $class);
            $this->assertSame('["Hillsborough"]', $fresh->info('counties'), $class);
            $this->assertSame('FL', $fresh->info('state'), $class);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PERSISTENCE · geometry fidelity through the real components
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Geometry survives a save → load → save cycle through both flows without loss.
     *
     * The trait's equivalent is `SearchAreasPersistenceCharacterisationTest`; this
     * establishes the same property for the two implementations that suite never
     * touches.
     *
     * SEMANTIC AFTER G1f-3, BYTE-IDENTICAL BEFORE IT. The canonical writer
     * serialises deterministically and stamps `schema_version: 2` (F-G1F-10), so
     * the stored bytes legitimately differ from the submitted ones while the
     * meaning does not. §5.3 withdrew the byte guarantee in favour of semantic
     * equality, so that is what is asserted — dimension by dimension, plus the
     * float precision, vertex count and unicode a byte comparison used to cover
     * incidentally. Those are the properties that actually protect geometry; byte
     * identity only ever protected them by accident.
     */
    public function test_geometry_round_trips_without_loss_on_both_flows(): void
    {
        $owner   = $this->owner();
        $encoded = json_encode([
            'cities'          => ['Tampa'],
            'state'           => 'FL',
            'counties'        => ['Hillsborough'],
            'polygons'        => [[
                'label' => 'Drawn area 1',
                'path'  => [
                    ['lat' => 27.9506, 'lng' => -82.4572],
                    ['lat' => 27.9606, 'lng' => -82.4472],
                    ['lat' => 27.9406, 'lng' => -82.4372],
                ],
            ]],
            'radius_searches' => [
                ['lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 3.5, 'address' => '400 N Ashley Dr'],
            ],
            'location_notes'  => 'Near the river — walkable. 東京 🏖',
        ]);

        $submitted = json_decode($encoded, true);

        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $class) {
            $auction = $this->auction($owner);

            $first = new $class();
            $first->location_dna_preferences_json = $encoded;
            $this->save($first, $auction);

            $afterFirst = (string) $this->reread($auction)->info('location_dna_preferences');
            $decoded    = json_decode($afterFirst, true);

            foreach (['cities', 'state', 'counties', 'polygons', 'radius_searches', 'location_notes'] as $dimension) {
                $this->assertSame(
                    $submitted[$dimension],
                    $decoded[$dimension] ?? null,
                    "{$class}: `{$dimension}` must survive the first write unchanged in meaning"
                );
            }

            // Re-save the stored value unchanged; its MEANING must not drift.
            $second = new $class();
            $second->location_dna_preferences_json = $afterFirst;
            $this->save($second, $auction);

            $stored   = (string) $this->reread($auction)->info('location_dna_preferences');
            $reparsed = json_decode($stored, true);

            $this->assertSame(
                $afterFirst,
                $stored,
                "{$class}: a re-save of identical meaning must not rewrite the bytes at all — the "
                .'revision token suppresses the write'
            );

            foreach (['cities', 'state', 'counties', 'polygons', 'radius_searches', 'location_notes'] as $dimension) {
                $this->assertSame($submitted[$dimension], $reparsed[$dimension] ?? null, "{$class}: {$dimension} drifted");
            }

            $this->assertCount(3, $reparsed['polygons'][0]['path'], $class);
            $this->assertSame(3.5, $reparsed['radius_searches'][0]['radius_miles'], "{$class}: float precision lost");
            $this->assertStringContainsString('東京', $stored, "{$class}: unicode mangled");
            $this->assertStringContainsString('🏖', $stored, "{$class}: astral-plane character mangled");
        }
    }

    /**
     * REPAIRED BY G1f-3 · an unmounted editor now PRESERVES saved geometry through
     * both flows.
     *
     * THE DEFECT THIS REPLACES. F-G1-7, reproduced on the real components: an empty
     * payload was written straight over the authoritative blob and the `cities`
     * mirror was emptied in the same save. The G0 guard — client-side JavaScript —
     * was the only defence, so anything that reached the save path with an
     * unmounted editor destroyed the record's geometry server-side.
     *
     * G1f-3 removed the defence's need. An empty payload states nothing, produces
     * no command, and the writer returns before reading or writing anything. This
     * is D-G1-2 option 2-A at the workflow level, and it is the single most
     * user-visible repair in the increment.
     */
    public function test_unmounted_editor_preserves_geometry_on_both_flows(): void
    {
        $owner = $this->owner();

        foreach ([BuyerOfferListing::class, BuyerOfferListingEdit::class] as $class) {
            $auction = $this->auction($owner);

            $seed = new $class();
            $seed->location_dna_preferences_json = json_encode([
                'cities'   => ['Tampa'],
                'polygons' => [['label' => 'Drawn area 1', 'path' => [['lat' => 27.9, 'lng' => -82.4]]]],
            ]);
            $this->save($seed, $auction);

            $this->assertStringContainsString(
                'Drawn area 1',
                (string) $this->reread($auction)->info('location_dna_preferences'),
                "{$class}: precondition — geometry stored"
            );

            $seeded = (string) $this->reread($auction)->info('location_dna_preferences');

            $unmounted = new $class();
            $unmounted->location_dna_preferences_json = '';
            $this->save($unmounted, $auction);

            $fresh = $this->reread($auction);

            $this->assertSame(
                $seeded,
                (string) $fresh->info('location_dna_preferences'),
                "{$class}: the blob must be preserved BYTE FOR BYTE. It used to be overwritten "
                .'with an empty string.'
            );
            $this->assertStringContainsString(
                'Drawn area 1',
                (string) $fresh->info('location_dna_preferences'),
                "{$class}: the geometry specifically must survive"
            );
            $this->assertSame(
                '["Tampa"]',
                $fresh->info('cities'),
                "{$class}: and the mirror with it — it used to be emptied in the same save"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // EQUIVALENCE · the finding that resolves F-G1-1
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The two Buyer Offer inline copies and the trait produce IDENTICAL observable
     * behaviour on the case that distinguishes them from the Tenant Offer copies.
     *
     * Asserted structurally as well as behaviourally, because the structural half
     * is what a future reader will check first: both Buyer Offer files carry the
     * `empty()` branch and neither carries the Tenant divergence construct
     * `array_key_exists('cities', …)`. The Tenant Offer pair is the opposite, and
     * `TenantOfferCitiesMirrorTest` pins that side.
     *
     * The negative assertion is deliberately narrowed to `array_key_exists('cities'`
     * rather than the bare function name. A file-wide check on `array_key_exists`
     * was the first draft and it FAILED: both Buyer Offer files contain one
     * occurrence, in a commented-out line about `$this->enable`
     * (`BuyerOfferListing.php:2349`, `BuyerOfferListingEdit.php:2366`) that has
     * nothing to do with the cities merge. Recorded because a future reader
     * grepping for the function name will hit the same false positive.
     *
     * Consequence recorded for G1f: there are THREE defective implementations to
     * consolidate, not one — the trait and both Buyer Offer copies.
     */
    public function test_buyer_offer_copies_are_defective_like_the_trait_not_divergent_like_tenant(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString(
                "if (empty(\$ldna['cities'] ?? [])) {",
                $source,
                "{$file} must still carry the empty() branch"
            );
            $this->assertStringNotContainsString(
                "array_key_exists('cities'",
                $source,
                "{$file} does NOT carry the Tenant Offer divergence construct"
            );
        }

        // The Tenant Offer pair is the opposite — the divergence is real and local.
        foreach ([
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        ] as $file) {
            $this->assertStringContainsString(
                "array_key_exists('cities'",
                file_get_contents(base_path($file)),
                "{$file} must still carry the divergence"
            );
        }
    }
}
