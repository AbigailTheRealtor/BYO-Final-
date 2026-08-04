<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\TenantAgentAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * FINDING 2B-3 — Tenant Offer `cities` mirror: correction and preservation.
 *
 * THE INVARIANT THIS SUITE EXISTS TO PROTECT
 * ------------------------------------------
 *   `location_dna_preferences.cities` is the SINGLE SOURCE OF TRUTH whenever
 *   that key EXISTS. The legacy discrete `cities` mirror may be consulted ONLY
 *   when the blob carries no `cities` key at all. It may NEVER overwrite an
 *   existing blob value — including an intentionally empty array.
 *
 * The distinction between "key missing" and "key present but empty" is the core
 * behavioural contract. `empty()` cannot express it; `array_key_exists()` can.
 * That single choice is what allows a user to delete every city and have the
 * deletion stick, while a genuinely legacy record still recovers its cities.
 *
 * WHY THE COMPONENT METHODS ARE CALLED DIRECTLY
 * ---------------------------------------------
 * `loadAuctionData()` and `loadDraft()` are invoked on real component instances
 * against real `TenantAgentAuction` records, and `saveAllMetadata()` through
 * reflection. Booting the full Livewire lifecycle would drag in hundreds of
 * unrelated required props and characterise the components' validation rather
 * than the mirror contract. The methods under test, the model, its meta table
 * and its saveMeta/info implementations are all real.
 *
 * COVERAGE LIMIT — read before trusting a green run
 * -------------------------------------------------
 * The blob is produced in the browser by `window.ldnaSerialize` and carried to
 * the server by a JavaScript bridge. This project has no JavaScript test runner,
 * so no test here proves the bridge actually syncs. These tests prove the PHP
 * contract on both sides of that bridge. The manual browser checklist in
 * docs/spatial/phase-2b-geometry-contract.md is not optional.
 */
class TenantOfferCitiesMirrorTest extends TestCase
{
    use DatabaseTransactions;

    private function auction(array $meta = []): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->create();

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread(TenantAgentAuction $auction): TenantAgentAuction
    {
        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    /** Hydrate through the real edit-flow entry point. */
    private function hydrateEdit(TenantAgentAuction $auction): TenantOfferListingEdit
    {
        $component = new TenantOfferListingEdit();
        $component->loadAuctionData($auction->id, 'tenant');

        return $component;
    }

    /**
     * Hydrate through the real draft/create-flow entry point.
     *
     * `loadDraft()` scopes its lookup by `Auth::id()`, so the owning user must be
     * authenticated or the record is simply not found and the hydration block
     * never runs — which would make these tests pass vacuously.
     */
    private function hydrateDraft(TenantAgentAuction $auction): TenantOfferListing
    {
        $this->actingAs($auction->user);

        $component = new TenantOfferListing();
        $component->user_type = 'tenant';
        $component->loadDraft($auction->id);

        return $component;
    }

    /** Invoke the protected create-flow save path. */
    private function saveViaCreateFlow(TenantOfferListing $component, TenantAgentAuction $auction): void
    {
        $method = new ReflectionMethod(TenantOfferListing::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE INVARIANT — the three cases the contract turns on
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * INVARIANT 1 · blob has cities, mirror differs → THE BLOB WINS.
     *
     * The stale mirror must not leak back into the blob during hydration.
     */
    public function test_invariant_blob_cities_win_over_a_differing_mirror(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['cities' => ['Orlando']]),
            'cities'                   => json_encode(['Tampa', 'Miami']),
        ]);

        $component = $this->hydrateEdit($auction);

        $this->assertSame(
            ['Orlando'],
            $component->existingLocationDna['cities'],
            'The blob is authoritative; the legacy mirror must not override it.'
        );
    }

    /**
     * INVARIANT 2 · blob has an EMPTY cities array, mirror has legacy data
     * → THE EMPTY ARRAY WINS.
     *
     * This is the intentional-clear case and the reason `array_key_exists()`
     * replaces `empty()`. Under `empty()` this test fails: the legacy mirror
     * would resurrect cities the user had just deleted.
     */
    public function test_invariant_empty_blob_array_is_not_overwritten_by_legacy_mirror(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => json_encode(['Tampa', 'Miami']),
        ]);

        $component = $this->hydrateEdit($auction);

        $this->assertSame(
            [],
            $component->existingLocationDna['cities'],
            'An intentionally cleared blob must stay cleared. Legacy cities must NOT return.'
        );
    }

    /**
     * INVARIANT 3 · the mirror is consulted ONLY when the blob has no `cities`
     * key at all.
     *
     * Asserted across all three states in one test so the boundary is visible
     * as a boundary rather than as three unrelated facts.
     */
    public function test_invariant_mirror_is_consulted_only_when_the_key_is_absent(): void
    {
        $legacy = json_encode(['Tampa']);

        // (a) key absent → mirror consulted
        $absent = $this->hydrateEdit($this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => $legacy,
        ]));
        $this->assertSame(['Tampa'], $absent->existingLocationDna['cities']);

        // (b) key present but empty → mirror NOT consulted
        $emptyKey = $this->hydrateEdit($this->auction([
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => $legacy,
        ]));
        $this->assertSame([], $emptyKey->existingLocationDna['cities']);

        // (c) key present and populated → mirror NOT consulted
        $populated = $this->hydrateEdit($this->auction([
            'location_dna_preferences' => json_encode(['cities' => ['Orlando']]),
            'cities'                   => $legacy,
        ]));
        $this->assertSame(['Orlando'], $populated->existingLocationDna['cities']);
    }

    /** The same three-state boundary holds on the draft/create hydration path. */
    public function test_invariant_holds_identically_on_the_draft_path(): void
    {
        $legacy = json_encode(['Tampa']);

        $absent = $this->hydrateDraft($this->auction([
            'location_dna_preferences' => json_encode(['state' => 'FL']),
            'cities'                   => $legacy,
        ]));
        $this->assertSame(['Tampa'], $absent->existingLocationDna['cities']);

        $emptyKey = $this->hydrateDraft($this->auction([
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => $legacy,
        ]));
        $this->assertSame([], $emptyKey->existingLocationDna['cities']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REQUIREMENT 1 — legacy record: non-empty mirror, no blob
    // ═════════════════════════════════════════════════════════════════════════

    /** Edit load seeds the blob from the legacy mirror. Fails before the fix. */
    public function test_legacy_record_seeds_blob_cities_from_the_mirror(): void
    {
        $auction = $this->auction([
            'cities' => json_encode(['Seminole, FL', 'St. Petersburg, FL']),
        ]);

        $component = $this->hydrateEdit($auction);

        $this->assertSame(
            ['Seminole, FL', 'St. Petersburg, FL'],
            $component->existingLocationDna['cities']
        );
    }

    /** Blank and whitespace-only legacy entries are filtered out during the merge. */
    public function test_legacy_merge_filters_blank_entries(): void
    {
        $auction = $this->auction([
            'cities' => json_encode(['Tampa', '', '   ', 'Orlando']),
        ]);

        $component = $this->hydrateEdit($auction);

        $this->assertSame(['Tampa', 'Orlando'], $component->existingLocationDna['cities']);
    }

    /** A record with neither blob nor mirror gains no cities key at all. */
    public function test_record_with_no_blob_and_no_mirror_gains_no_cities_key(): void
    {
        $component = $this->hydrateEdit($this->auction());

        $this->assertArrayNotHasKey('cities', $component->existingLocationDna);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REQUIREMENT 2 — merge must not disturb state / counties
    // ═════════════════════════════════════════════════════════════════════════

    /** Merging cities leaves an existing blob state and counties untouched. */
    public function test_merge_does_not_overwrite_existing_state_or_counties(): void
    {
        $auction = $this->auction([
            'location_dna_preferences' => json_encode([
                'state'    => 'FL',
                'counties' => ['Pinellas'],
            ]),
            'cities' => json_encode(['Tampa']),
        ]);

        $component = $this->hydrateEdit($auction);

        $this->assertSame(['Tampa'], $component->existingLocationDna['cities']);
        $this->assertSame('FL', $component->existingLocationDna['state']);
        $this->assertSame(['Pinellas'], $component->existingLocationDna['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REQUIREMENTS 5 & 6 — the mirror write on the save paths
    // ═════════════════════════════════════════════════════════════════════════

    /** Create/draft save writes the mirror from the blob. Fails before the fix. */
    public function test_create_flow_writes_the_cities_mirror_from_the_blob(): void
    {
        $auction   = $this->auction();
        $component = new TenantOfferListing();
        $component->location_dna_preferences_json = json_encode(['cities' => ['Tampa', 'Orlando']]);

        $this->saveViaCreateFlow($component, $auction);

        $this->assertSame('["Tampa","Orlando"]', $this->reread($auction)->info('cities'));
    }

    /** An intentionally emptied blob mirrors as `[]`, not as stale data. */
    public function test_create_flow_mirrors_an_intentional_clear_as_empty_array(): void
    {
        $auction   = $this->auction(['cities' => json_encode(['Tampa'])]);
        $component = new TenantOfferListing();
        $component->location_dna_preferences_json = json_encode(['cities' => []]);

        $this->saveViaCreateFlow($component, $auction);

        $this->assertSame('[]', $this->reread($auction)->info('cities'));
    }

    /**
     * A blob with no cities key writes NO cities mirror at all.
     *
     * CHANGED BY G1f-4, deliberately. The inline path decoded the blob and wrote
     * `$ldnaDecoded['cities'] ?? []`, so an ABSENT cities key mirrored as `[]` — indistinguishable
     * from an explicit clear, and the mechanism by which a no-op save destroyed a legacy-only
     * mirror. The canonical writer issues no command for an absent dimension, so nothing is
     * written and any existing legacy value survives. Present-empty still clears; see the test
     * below. This is the defect being fixed, not a regression.
     */
    public function test_create_flow_writes_no_cities_mirror_when_the_key_is_absent(): void
    {
        $auction   = $this->auction(['cities' => json_encode(['Tampa'])]);
        $component = new TenantOfferListing();
        $component->location_dna_preferences_json = json_encode(['state' => 'FL']);

        $this->saveViaCreateFlow($component, $auction);

        $this->assertSame(
            json_encode(['Tampa']),
            $this->reread($auction)->info('cities'),
            'an absent cities key must leave the legacy mirror untouched, not overwrite it with []'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REQUIREMENT 4 — the full intentional-clear cycle
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The end-to-end clear cycle: a legacy record recovers its cities, the user
     * clears them, and they stay cleared across a subsequent reload.
     *
     * This is the scenario the invariant exists for. Step 3 is where an `empty()`
     * based merge would silently resurrect the deleted cities.
     */
    public function test_full_cycle_legacy_recovery_then_intentional_clear_persists(): void
    {
        // 1 · legacy record: mirror only, no blob
        $auction = $this->auction(['cities' => json_encode(['Tampa'])]);

        // 2 · edit load recovers the legacy cities into the blob
        $first = $this->hydrateEdit($auction);
        $this->assertSame(['Tampa'], $first->existingLocationDna['cities']);

        // 3 · the user clears every city in the widget; the bridge carries `[]` back
        $saver = new TenantOfferListing();
        $saver->location_dna_preferences_json = json_encode(['cities' => []]);
        $this->saveViaCreateFlow($saver, $auction);

        $this->assertSame('[]', $this->reread($auction)->info('cities'));

        // 4 · reload — the clear must survive, NOT be undone by the legacy merge
        $second = $this->hydrateEdit($this->reread($auction));
        $this->assertSame(
            [],
            $second->existingLocationDna['cities'],
            'The legacy merge resurrected an intentional clear. The invariant is broken.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REQUIREMENT 8 — the edit-flow mirror write
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `update()` carries the mirror write.
     *
     * Asserted structurally rather than behaviourally: `update()` runs full
     * validation, file handling and a redirect, so invoking it would test those
     * rather than the mirror. The write itself is byte-identical to the
     * create-flow write proven behaviourally above.
     *
     * Recorded as a known weaker assertion rather than presented as equivalent.
     */
    public function test_edit_flow_update_reaches_the_cities_mirror_through_the_writer(): void
    {
        $source = file_get_contents(
            base_path('app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php')
        );

        // CONVERTED BY G1f-4. This was an inline-write tripwire: it asserted that `update()`
        // still wrote the cities mirror from the decoded blob. G1f-4 removes that line by
        // definition, so the assertion is inverted into the boundary that replaced it — the
        // mirror is still reached on this path, now through the canonical writer.
        $this->assertStringNotContainsString(
            "\$auction->saveMeta('cities', json_encode(\$ldnaDecoded['cities'] ?? []));",
            $source,
            'the inline cities mirror write must be gone — G1f-4 migrated it'
        );
        $this->assertSame(
            1,
            substr_count($source, '$this->persistLocationDna($auction);'),
            'exactly one canonical writer call site in the edit flow'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REQUIREMENT 7 — Buyer and Hire must be untouched
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The trait keeps its original `empty()` semantics.
     *
     * The Tenant Offer components deliberately diverge by using
     * array_key_exists(). This test pins the divergence so it stays deliberate:
     * if someone later "aligns" the trait, this fails and forces the Hire flows
     * to be re-verified rather than changed silently.
     */
    public function test_hire_trait_semantics_are_unchanged(): void
    {
        $trait = file_get_contents(base_path('app/Http/Livewire/Concerns/HasSearchAreas.php'));

        $this->assertStringContainsString("if (empty(\$ldna['cities'] ?? [])) {", $trait);
        $this->assertStringNotContainsString('array_key_exists', $trait);
    }

    /** Both Buyer Offer components keep their original mirror write, underived from this change. */
    /**
     * B4 · RESOLVED BY G1f-3 — converted from "unchanged" to a positive migrated boundary.
     *
     * WHAT THIS GUARD USED TO SAY, AND WHY IT CHANGED.
     * ------------------------------------------------
     * It asserted that both Buyer Offer components still contained the exact inline mirror-write
     * line `saveMeta('cities', json_encode($ldnaDecoded['cities'] ?? []))`. That was a deliberate
     * tripwire: it existed so that any edit to the Buyer Offer copies — including the Tenant
     * Offer divergence being copied across — would fail loudly here.
     *
     * G1f-3 removes that line from both files by definition, so the tripwire fires. It was
     * recorded as blocker B4 and named in the G1f-3 authorization in advance, which is why this
     * change is an expected update and not a regression.
     *
     * The guard is NOT deleted. It is inverted into the assertion that carries the same
     * protective value after the migration: both files reach the canonical key through the writer
     * seam and through nothing else, and the migration stopped exactly there. A future increment
     * that quietly re-adds an inline canonical or mirror write, or that drags a further workflow
     * across without its own authorization, still fails here.
     */
    public function test_offer_components_are_migrated_and_the_boundary_held(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            // MIGRATED — the inline writes are gone and one writer seam stands in their place.
            $this->assertStringNotContainsString(
                "\$auction->saveMeta('cities', json_encode(\$ldnaDecoded['cities'] ?? []));",
                $source,
                "{$file}: the inline cities mirror write must be gone — G1f-3 migrated it."
            );
            $this->assertStringNotContainsString(
                "\$auction->saveMeta('location_dna_preferences', \$this->location_dna_preferences_json);",
                $source,
                "{$file}: the inline canonical write must be gone."
            );
            $this->assertSame(
                1,
                substr_count($source, '$this->persistLocationDna($auction);'),
                "{$file}: exactly one canonical writer call site."
            );

            // UNCHANGED — the Tenant divergence construct was never copied here, and the
            // never-introduce marker still must not appear.
            $this->assertStringNotContainsString('FINDING 2B-3', $source);
            $this->assertStringNotContainsString(
                "array_key_exists('cities'",
                $source,
                "{$file}: the Tenant Offer divergence construct must still not appear here."
            );
        }

        // CONVERTED BY G1f-4. This block previously asserted that the Tenant Offer pair was NOT
        // migrated — correct for G1f-3, and false by definition once G1f-4 migrated it. It is now
        // the positive boundary: the pair is migrated, together, with no inline writes left.
        foreach ([
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString(
                "saveMeta('location_dna_preferences'",
                $source,
                "{$file}: the inline canonical write must be gone — G1f-4 migrated it."
            );
            $this->assertSame(
                1,
                substr_count($source, '$this->persistLocationDna($auction);'),
                "{$file}: exactly one canonical writer call site."
            );
            foreach (['cities', 'counties', 'state', 'zipCodes'] as $mirror) {
                $this->assertStringNotContainsString(
                    "\$auction->saveMeta('{$mirror}',",
                    $source,
                    "{$file}: the inline {$mirror} mirror write must be gone."
                );
            }
        }

        // NARROWED BY G1f-5. `BuyerAgentAuctionEdit` was one of the two Hire edit workflows this
        // assertion held back, and it has since been migrated under its OWN authorization — which
        // is the point the assertion was making: G1f-4's scope could not reach it. Its migrated
        // boundary is pinned by G1f5MigrationBoundaryGuardTest and its behaviour by
        // G1f5BuyerAgentAuctionEditMigrationTest.
        //
        // INVERTED BY G1f-6. `TenantAgentAuctionEdit` was the last workflow this assertion held
        // back, and it too has now been migrated under its own authorization. With the list empty
        // the loop would assert nothing, so the property is restated positively: both Hire edit
        // workflows are migrated, and each reaches the writer exactly once.
        //
        // The point the original assertion made still stands and is still recorded — G1f-4's scope
        // could not reach either of them; each needed its own increment.
        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertSame(
                1,
                substr_count($source, '$this->persistLocationDna($auction);'),
                "{$file}: migrated under its own authorization, with exactly one writer call."
            );
            $this->assertStringNotContainsString(
                '$this->saveSearchAreas($auction);',
                $source,
                "{$file}: the trait save must be gone."
            );
        }
    }
}
