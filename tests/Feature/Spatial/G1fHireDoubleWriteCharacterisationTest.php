<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Http\Livewire\TenantAgentAuction as HireTenantCreate;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1f GAP 2 — the Hire double-write. BLOCKS G1f-1.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * The G1f pre-implementation report records F-G1F-3: every Hire component writes the
 * discrete `cities` / `counties` / `state` mirrors TWICE per save — once from its own
 * component properties, and again from the blob when `saveSearchAreas()` runs a few
 * statements later.
 *
 *   BuyerAgentAuction::saveAllMetadata()
 *     :1901  saveMeta('cities',   json_encode($this->cities))     ← component property
 *     :1902  saveMeta('counties', json_encode($this->counties))   ← component property
 *     :1903  saveMeta('state',    $this->state)                   ← component property
 *     :1908  $this->saveSearchAreas($auction)
 *              → HasSearchAreas:123  saveMeta('counties', …)      ← OVERWRITES :1902
 *              → HasSearchAreas:126  saveMeta('state', …)         ← OVERWRITES :1903
 *              → HasSearchAreas:130  saveMeta('cities', …)        ← OVERWRITES :1901
 *
 * The correctness of all four Hire workflows therefore rests entirely on STATEMENT
 * ORDERING inside a 2,300–5,300-line method, and no test asserted it. A G1f change that
 * moves the trait call, or that makes the persistence service run at a different point,
 * silently reverts four workflows to component-property semantics — and before this
 * suite, nothing would have failed.
 *
 * `BuyerAgentAuction` is the workflow the report recommends migrating FIRST, and this
 * double-write is the exact structure that migration removes. Without this baseline
 * there is nothing to prove parity against, which is why the report records GAP 2 as
 * blocking G1f-1.
 *
 * WHY SOURCE ORDER IS NOT SUFFICIENT EVIDENCE
 * -------------------------------------------
 * Reading the source shows which statement comes first. It does NOT show:
 *   - that the second `updateOrCreate` actually wins (it could no-op, or match a
 *     different row, or be swallowed by a unique constraint);
 *   - that nothing between the two writes mutates `$this->cities`;
 *   - that the winning value is the BLOB-derived one rather than a coincidentally
 *     equal property value.
 * All three are settled here by executing the real save path against DIVERGENT
 * fixtures, where the component property and the blob carry deliberately different
 * values so the winner is observable.
 *
 * The write ORDER itself is proven from the query log rather than inferred: both
 * candidate values are searched for in the executed statement bindings, and their
 * relative positions asserted. That is a direct observation of execution order.
 *
 * STATUS AFTER G1f-5 — GAP 2 CLOSED, AND THE DEFECT REMOVED FROM EVERY INVOCABLE PATH
 * -----------------------------------------------------------------------------------
 * All three Hire components with an invocable `saveAllMetadata()` have now been migrated to
 * `LocationDnaPersistenceService`: `BuyerAgentAuction` by G1f-1, `TenantAgentAuction` by G1f-2
 * and `BuyerAgentAuctionEdit` by G1f-5. G1f-6 then migrated `TenantAgentAuctionEdit`, the last
 * component of any kind that carried it.
 *
 * **The double-write this suite documents no longer exists anywhere in the application.** That is
 * the terminal state, and it is asserted rather than assumed: each migrated component has a
 * structural test below proving it does not carry the construct, and the Hire Tenant edit test
 * additionally pins that its surviving property write moved INTO the seller/landlord else branch
 * rather than simply remaining where it was.
 *
 * This suite therefore no longer characterises a LIVE double-write behaviourally, and does not
 * claim to. What it does instead — deliberately, rather than by emptying its fixture set and
 * iterating nothing — is hold the migrated edit copy to the post-migration outcome:
 *
 *   - the four tests expressed as properties of the PERSISTED RESULT (which mirror value wins,
 *     that no third value appears, that agreeing values converge, that no property value
 *     survives) hold UNCHANGED across the migration. That they still pass, against the same
 *     divergent fixture, is the parity evidence G1f-5 owed this suite;
 *   - the two expressed as properties of the double-write MECHANISM (statement ordering,
 *     in-flight property mutation) are INVERTED, because the mechanism is what was removed.
 *
 * Each migrated component also has a structural assertion here that it no longer carries the
 * duplicate write, so reintroducing one fails the suite that documents what a double-write IS.
 *
 * COVERAGE, AND ONE DELIBERATE BOUNDARY
 * -------------------------------------
 * `TenantAgentAuctionEdit` carries its double-write inside `update()`, which runs full
 * validation, file handling and a redirect; invoking it would characterise those, not the mirror
 * contract. It is asserted STRUCTURALLY and recorded as a KNOWN WEAKER ASSERTION — the same
 * boundary, for the same reason, that `G1aWorkflowPersistenceMatrixCharacterisationTest` and
 * `TenantOfferCitiesMirrorTest` already establish and document.
 *
 * CHARACTERISATION, NOT REPAIR
 * ----------------------------
 * Every assertion records today's behaviour. No owner decision is assumed and no
 * production file is touched. PHP and database only.
 */
class G1fHireDoubleWriteCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    /** The component-property values — deliberately different from the blob's. */
    private const PROP_CITIES   = ['Tampa'];
    private const PROP_COUNTIES = ['Hillsborough'];
    private const PROP_STATE    = 'FL';

    /** The blob values — what the trait-derived write should persist. */
    private const BLOB_CITIES   = ['Orlando'];
    private const BLOB_COUNTIES = ['Orange'];
    private const BLOB_STATE    = 'GA';

    /**
     * The Hire component exercised behaviourally through its invocable save path.
     *
     * NARROWED BY G1f-1, THEN G1f-2 — then KEPT, NOT EMPTIED, BY G1f-5.
     * ----------------------------------------------------------------
     * `BuyerAgentAuction` (Hire Buyer · create) was the first workflow migrated to
     * `LocationDnaPersistenceService` and `TenantAgentAuction` (Hire Tenant · create) the second,
     * so neither had a double-write left to characterise; both were removed from this set and
     * replaced by the structural assertions below. Their post-migration behaviour is covered by
     * {@see G1f1BuyerAgentAuctionMigrationTest} and {@see G1f2TenantAgentAuctionMigrationTest}.
     *
     * G1f-5 migrated the entry that remained, `BuyerAgentAuctionEdit`. Removing it too would have
     * emptied this set and left all six behavioural tests below iterating nothing — six green
     * tests asserting nothing at all. It is therefore RETAINED, and the six tests assert the
     * POST-migration outcome on it instead of the pre-migration one.
     *
     * That is a real narrowing of what this suite claims, stated plainly: it no longer
     * characterises a live double-write on any invocable path, because none exists. Four of the
     * six tests below were already expressed as properties of the PERSISTED RESULT — which mirror
     * value wins, that no third value appears, that agreeing values converge — and those hold
     * unchanged across the migration, which is precisely the parity G1f-5 had to preserve. The
     * two that were expressed as properties of the double-write MECHANISM (statement ordering,
     * in-flight property mutation) are inverted, since the mechanism is what was removed.
     *
     * `TenantAgentAuctionEdit` remains absent for the boundary reason in the class docblock, and
     * it — alone now — still carries the live double-write, asserted structurally below.
     *
     * @return array<string, array<string, mixed>>
     */
    private function behaviouralComponents(): array
    {
        return [
            'Hire Buyer · edit' => [
                'class' => HireBuyerEdit::class, 'model' => 'buyer', 'user_type' => null,
            ],
        ];
    }

    private function owner(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    /** Build a real record of the kind the given workflow writes to. */
    private function record(string $kind, User $owner, array $meta = [])
    {
        if ($kind === 'tenant') {
            $auction = TenantAgentAuction::factory()->create(['user_id' => $owner->id]);

            foreach (array_merge(['user_type' => 'tenant', 'property_items' => '[]'], $meta) as $k => $v) {
                $auction->saveMeta($k, $v);
            }

            return TenantAgentAuction::with('meta')->findOrFail($auction->id);
        }

        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'address'     => '',
            'title'       => 'G1f double-write',
            'is_draft'    => true,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $rows = [];
        foreach (array_merge([
            'user_type'                    => 'buyer',
            'property_items'               => '[]',
            'condition_prop_buyer'         => '[]',
            'garage_parking_spaces_option' => '[]',
            'assets'                       => '[]',
        ], $meta) as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread(string $kind, $auction)
    {
        $class = $kind === 'tenant' ? TenantAgentAuction::class : BuyerAgentAuction::class;

        return $class::with('meta')->findOrFail($auction->id);
    }

    /**
     * A component primed for its real save path, carrying DIVERGENT property and blob
     * values so the winning write is observable.
     */
    private function componentWithDivergentValues(array $cfg, array $blob): object
    {
        $component = new $cfg['class']();

        if ($cfg['user_type'] !== null) {
            $component->user_type = $cfg['user_type'];
        }

        $component->cities   = self::PROP_CITIES;
        $component->counties = self::PROP_COUNTIES;
        $component->state    = self::PROP_STATE;

        $component->location_dna_preferences_json = json_encode($blob);

        return $component;
    }

    private function invokeSave(array $cfg, object $component, $auction): void
    {
        $method = new ReflectionMethod($cfg['class'], 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    private function divergentBlob(): array
    {
        return [
            'cities'   => self::BLOB_CITIES,
            'counties' => self::BLOB_COUNTIES,
            'state'    => self::BLOB_STATE,
        ];
    }

    /**
     * Positions, in execution order, of every logged statement whose bindings contain
     * the given needle. Empty when the value was never written.
     *
     * @return list<int>
     */
    private function bindingPositions(array $queries, string $needle): array
    {
        $hits = [];

        foreach ($queries as $i => $q) {
            foreach ((array) ($q['bindings'] ?? []) as $binding) {
                if (is_string($binding) && str_contains($binding, $needle)) {
                    $hits[] = $i;
                    break;
                }
            }
        }

        return $hits;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ORDER · the component-property write happens first
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * INVERTED BY G1f-5 · the component-property write is GONE, and only the
     * blob-derived value ever reaches storage.
     *
     * This test previously proved the ordering the double-write rested on: the
     * component-property value was written first and the blob-derived value overwrote
     * it, both located in the executed query bindings and their positions compared.
     *
     * G1f-5 removed the first of those two writes, so there is no longer an ordering to
     * observe — and an ordering assertion over a single write would be meaningless.
     * What replaces it is the stronger property the removal establishes: the
     * component-property value is never written AT ALL, for any dimension.
     *
     * Still proven from the query log rather than from source, and still using the
     * divergent fixture, so this is a direct observation that no statement anywhere in
     * the real save path carries the property value to storage. That is what makes it
     * strictly stronger than the ordering claim it replaces: ordering only established
     * which write won, this establishes that the losing write no longer happens.
     */
    public function test_no_component_property_write_occurs_and_only_the_blob_derived_value_is_written(): void
    {
        $covered = 0;

        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);
            $component = $this->componentWithDivergentValues($cfg, $this->divergentBlob());

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->invokeSave($cfg, $component, $auction);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            foreach ([
                'cities'   => [self::PROP_CITIES[0],   self::BLOB_CITIES[0]],
                'counties' => [self::PROP_COUNTIES[0], self::BLOB_COUNTIES[0]],
            ] as $dimension => [$propValue, $blobValue]) {
                $propPositions = $this->bindingPositions($queries, $propValue);
                $blobPositions = $this->bindingPositions($queries, $blobValue);

                $this->assertSame(
                    [],
                    $propPositions,
                    "{$label} · {$dimension}: the component-property value '{$propValue}' reached "
                    .'storage. The double-write F-G1F-3 records has been reintroduced.'
                );
                $this->assertNotEmpty(
                    $blobPositions,
                    "{$label} · {$dimension}: the blob-derived value '{$blobValue}' was never written. "
                    .'The canonical writer did not run, or no longer mirrors this dimension.'
                );
            }

            $covered++;
        }

        $this->assertSame(
            1,
            $covered,
            'The one Hire component with an invocable save path was exercised. It is MIGRATED as of '
            .'G1f-5; it is retained here rather than removed so this suite keeps asserting against a '
            .'real save path instead of iterating an empty set.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OUTCOME · the trait-derived write wins
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · the trait-derived mirror write wins for all three dimensions in
     * every invocable Hire component.
     *
     * This is the assertion G1f's migration must preserve. The consolidated writer has
     * to produce these same persisted values without relying on one statement
     * overwriting another.
     */
    public function test_the_trait_derived_mirror_write_wins(): void
    {
        $covered = 0;

        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);
            $component = $this->componentWithDivergentValues($cfg, $this->divergentBlob());

            $this->invokeSave($cfg, $component, $auction);

            $stored = $this->reread($cfg['model'], $auction);

            $this->assertSame(
                json_encode(self::BLOB_CITIES),
                (string) $stored->info('cities'),
                "{$label}: the persisted `cities` mirror must be the BLOB value, not the property value."
            );
            $this->assertSame(
                json_encode(self::BLOB_COUNTIES),
                (string) $stored->info('counties'),
                "{$label}: the persisted `counties` mirror must be the BLOB value."
            );
            $this->assertSame(
                self::BLOB_STATE,
                (string) $stored->info('state'),
                "{$label}: the persisted `state` mirror must be the BLOB value."
            );

            $covered++;
        }

        $this->assertSame(1, $covered);
    }

    /**
     * CHARACTERISED · the persisted value equals the blob-derived value exactly, and
     * carries no trace of the component property.
     *
     * Separated from the assertion above so that a future change producing a MERGED
     * value — for example `["Tampa","Orlando"]` — fails with a distinct signal rather
     * than looking like a simple ordering flip.
     */
    public function test_the_persisted_value_matches_the_blob_and_contains_no_property_value(): void
    {
        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);
            $component = $this->componentWithDivergentValues($cfg, $this->divergentBlob());

            $this->invokeSave($cfg, $component, $auction);

            $stored = $this->reread($cfg['model'], $auction);

            foreach (['cities' => self::PROP_CITIES[0], 'counties' => self::PROP_COUNTIES[0]] as $key => $propValue) {
                $this->assertStringNotContainsString(
                    $propValue,
                    (string) $stored->info($key),
                    "{$label} · {$key}: the persisted mirror must not retain the component-property "
                    .'value. A merge, rather than an overwrite, would be a different defect.'
                );
            }

            $this->assertNotSame(
                self::PROP_STATE,
                (string) $stored->info('state'),
                "{$label} · state: the property value must not survive."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MECHANISM · which dimensions are mutated in flight, and which are not
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * INVERTED BY G1f-5 · NO component property is mutated in flight — the save path no
     * longer reads or writes component state for any of the three dimensions.
     *
     * This test previously proved F-G1-4's "the three dimensions do not share a code
     * path" for the Hire family: `saveSearchAreas()` called
     * `hydrateDiscreteLocationFromBlob()`, which overwrote `$this->state` and
     * `$this->counties` from the blob but never touched `$this->cities` — so after a
     * save two properties had silently changed under the caller and one had not.
     *
     * G1f-5 removed the trait call, and with it the only route by which a save mutated
     * component state. The asymmetry is gone because the mechanism producing it is gone,
     * so the assertion becomes its uniform counterpart: all three properties hold the
     * values the caller set, unchanged.
     *
     * This is worth asserting rather than dropping, and it is not a restatement of the
     * test above. That one proves no property value reached STORAGE; this one proves the
     * save did not reach back and mutate the CALLER. A writer that re-hydrated component
     * props as a side effect would pass that test and fail this one — and it would be a
     * real regression, because the three dimensions would once again disagree about
     * whether component state is authoritative after a save.
     */
    public function test_no_component_property_is_mutated_in_flight(): void
    {
        $covered = 0;

        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);
            $component = $this->componentWithDivergentValues($cfg, $this->divergentBlob());

            $this->invokeSave($cfg, $component, $auction);

            $this->assertSame(
                self::PROP_STATE,
                $component->state,
                "{$label}: \$this->state must be UNCHANGED — the migrated save path no longer "
                .'hydrates discrete props from the blob.'
            );
            $this->assertSame(
                self::PROP_COUNTIES,
                $component->counties,
                "{$label}: \$this->counties must be UNCHANGED for the same reason."
            );
            $this->assertSame(
                self::PROP_CITIES,
                $component->cities,
                "{$label}: \$this->cities must be UNCHANGED — it never was mutated, and the uniform "
                .'treatment of all three dimensions is what G1f-5 established.'
            );

            $covered++;
        }

        $this->assertSame(1, $covered, 'The one invocable Hire save path was exercised.');
    }

    /**
     * CHARACTERISED · no third value appears. The only two candidates written for a
     * dimension are the property value and the blob value.
     *
     * Closes "no intermediate mutation changes the expected outcome": if some statement
     * between the two writes introduced a different value, it would appear in the log
     * for that meta key and this assertion would fail.
     */
    public function test_no_intermediate_write_introduces_a_third_value(): void
    {
        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);
            $component = $this->componentWithDivergentValues($cfg, $this->divergentBlob());

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->invokeSave($cfg, $component, $auction);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            $citiesWrites = [];
            foreach ($queries as $q) {
                foreach ((array) ($q['bindings'] ?? []) as $binding) {
                    if (is_string($binding)
                        && (str_contains($binding, self::PROP_CITIES[0]) || str_contains($binding, self::BLOB_CITIES[0]))
                        && str_starts_with(trim($binding), '[')
                    ) {
                        $citiesWrites[] = $binding;
                    }
                }
            }

            $this->assertNotEmpty($citiesWrites, "{$label}: expected at least one cities mirror write.");

            foreach ($citiesWrites as $written) {
                $this->assertContains(
                    $written,
                    [json_encode(self::PROP_CITIES), json_encode(self::BLOB_CITIES)],
                    "{$label}: an unexpected third value '{$written}' was written to the cities "
                    .'mirror. Only the component-property value and the blob value are expected.'
                );
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CONTROL · agreeing values
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CONTROL · when the component property and the blob agree, the outcome is the same
     * value and the ordering is unobservable.
     *
     * The negative case the report asks for. It proves the divergent-fixture result
     * above is produced by the overwrite and not by some unrelated property of the save
     * path — with agreeing values, no assertion in this suite could distinguish which
     * write won, which is exactly why the divergent fixture is necessary.
     */
    public function test_control_case_agreeing_property_and_blob_values_converge(): void
    {
        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);

            $agreed = [
                'cities'   => self::PROP_CITIES,
                'counties' => self::PROP_COUNTIES,
                'state'    => self::PROP_STATE,
            ];

            $component = $this->componentWithDivergentValues($cfg, $agreed);
            $this->invokeSave($cfg, $component, $auction);

            $stored = $this->reread($cfg['model'], $auction);

            $this->assertSame(
                json_encode(self::PROP_CITIES),
                (string) $stored->info('cities'),
                "{$label}: agreeing values must persist that value."
            );
            $this->assertSame(
                json_encode(self::PROP_COUNTIES),
                (string) $stored->info('counties'),
                "{$label}: agreeing values must persist that value."
            );
            $this->assertSame(
                self::PROP_STATE,
                (string) $stored->info('state'),
                "{$label}: agreeing values must persist that value."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STRUCTURAL · the fourth Hire component, and the migrated one
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * G1f-1 · `BuyerAgentAuction` no longer carries the double-write.
     *
     * The assertion that replaces its former behavioural row. It is the inverse of what this
     * suite proved before the migration: the component-property mirror writes are gone, the
     * trait's save is no longer called from it, and one canonical writer call stands in their
     * place.
     *
     * Deliberately asserted here rather than only in the migration suite, so that restoring the
     * duplicate write fails the suite that documents what a double-write IS.
     */
    public function test_buyer_agent_auction_no_longer_carries_the_double_write(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php'));

        $this->assertStringNotContainsString(
            "\$auction->saveMeta('cities', json_encode(\$this->cities));",
            $source,
            'The component-property cities mirror write must be gone — it was the first half of '
            .'the double-write.'
        );
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $source,
            'BuyerAgentAuction must no longer call the trait save — it writes through '
            .'LocationDnaPersistenceService now.'
        );
        $this->assertStringContainsString(
            '$this->persistLocationDna($auction);',
            $source,
            'It must call the canonical writer seam exactly once.'
        );

        // The trait itself is untouched, and the other three Hire hosts still use it.
        $this->assertStringContainsString(
            "empty(\$ldna['cities'] ?? [])",
            file_get_contents(base_path('app/Http/Livewire/Concerns/HasSearchAreas.php')),
            'HasSearchAreas must be unchanged by G1f-1 — it still serves the three unmigrated hosts.'
        );

        // And the class this suite no longer exercises behaviourally is still the migrated one.
        $this->assertSame(
            'App\\Http\\Livewire\\HireBuyerAgent\\BuyerAgentAuction',
            HireBuyerCreate::class,
            'The narrowing in behaviouralComponents() refers to this component.'
        );
    }

    /**
     * G1f-2 · `TenantAgentAuction` no longer carries the double-write.
     *
     * The assertion that replaces its behavioural row, in the same form G1f-1 established for
     * `BuyerAgentAuction`: the component-property mirror writes are gone from the ungated path,
     * the trait's save is no longer called, and one canonical writer call stands in their place.
     *
     * TWO THINGS THAT DELIBERATELY DID NOT CHANGE are asserted alongside, because this component
     * — unlike the first migration — carries them:
     *
     *   - the `user_type` gate, still textually identical (D-G1F-3 3-C);
     *   - the property-sourced `zipCodes` mirror write (D-G1F-4 (a)).
     *
     * Asserting them HERE, in the suite that documents what a double-write is, means an attempt to
     * fold either of them into a future consolidation fails the characterisation rather than
     * passing quietly.
     */
    public function test_tenant_agent_auction_no_longer_carries_the_double_write(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/TenantAgentAuction.php'));

        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $source,
            'TenantAgentAuction must no longer call the trait save — its buyer/tenant path writes '
            .'through LocationDnaPersistenceService now.'
        );
        $this->assertStringContainsString(
            '$this->persistLocationDna($auction);',
            $source,
            'It must call the canonical writer seam exactly once.'
        );
        $this->assertSame(
            1,
            substr_count($source, '$this->persistLocationDna($auction);'),
            'Exactly one canonical writer call site — a second would reintroduce a double-write of '
            .'a new kind.'
        );

        // PRESERVED · the gate, unchanged and still the only route to the canonical writer.
        $this->assertStringContainsString(
            "in_array(\$this->user_type, ['buyer', 'tenant'])",
            $source,
            'The user_type gate must survive G1f-2 verbatim — D-G1F-3, option 3-C.'
        );

        // PRESERVED · zipCodes, still property-sourced and still unmanaged.
        $this->assertStringContainsString(
            "\$auction->saveMeta('zipCodes', json_encode(\$this->zipCodes));",
            $source,
            'The zipCodes mirror write must be unchanged — D-G1F-4 (a) keeps it out of scope.'
        );

        // The trait itself is untouched, and the two remaining hosts still use it.
        $this->assertStringContainsString(
            "empty(\$ldna['cities'] ?? [])",
            file_get_contents(base_path('app/Http/Livewire/Concerns/HasSearchAreas.php')),
            'HasSearchAreas must be unchanged by G1f-2 — it still serves the unmigrated hosts.'
        );

        $this->assertSame(
            'App\\Http\\Livewire\\TenantAgentAuction',
            HireTenantCreate::class,
            'The narrowing in behaviouralComponents() refers to this component.'
        );
    }

    /**
     * G1f-5 · `BuyerAgentAuctionEdit` no longer carries the double-write.
     *
     * The structural counterpart to the two inversions above, in the same form G1f-1 established
     * for `BuyerAgentAuction` and G1f-2 for `TenantAgentAuction`: the component-property mirror
     * writes are gone, the trait's save is no longer called, and exactly one canonical writer call
     * stands in their place.
     *
     * Asserted here, in the suite that documents what a double-write IS, so that reintroducing the
     * duplicate write fails this suite and not only the migration suite.
     *
     * TWO ABSENCES ARE ASSERTED DELIBERATELY, because this component is the Buyer family's edit
     * copy and both were explicit scope boundaries for G1f-5:
     *
     *   - no `zipCodes` mirror write — the Buyer family has never written that key and D-G1F-4
     *     keeps it that way; the Tenant family's opt-in must not leak across;
     *   - no transaction opened in the component — the writer owns the transaction, and a second
     *     one here would nest it silently.
     */
    public function test_buyer_agent_auction_edit_no_longer_carries_the_double_write(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php'));

        $this->assertStringNotContainsString(
            "\$auction->saveMeta('cities', json_encode(\$this->cities));",
            $source,
            'The component-property cities mirror write must be gone — it was the first half of '
            .'the double-write.'
        );
        $this->assertStringNotContainsString(
            "\$auction->saveMeta('counties', json_encode(\$this->counties));",
            $source,
            'The component-property counties mirror write must be gone.'
        );
        $this->assertStringNotContainsString(
            "\$auction->saveMeta('state', \$this->state);",
            $source,
            'The component-property state mirror write must be gone.'
        );
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $source,
            'BuyerAgentAuctionEdit must no longer call the trait save — it writes through '
            .'LocationDnaPersistenceService now.'
        );
        $this->assertStringContainsString(
            '$this->persistLocationDna($auction);',
            $source,
            'It must call the canonical writer seam.'
        );
        $this->assertSame(
            1,
            substr_count($source, '$this->persistLocationDna($auction);'),
            'Exactly one canonical writer call site — a second would reintroduce a double-write of '
            .'a new kind.'
        );

        // SCOPE BOUNDARY · zipCodes is not introduced for the Buyer family (D-G1F-4).
        $this->assertStringNotContainsString(
            "saveMeta('zipCodes'",
            $source,
            'G1f-5 must not introduce a zipCodes mirror write — the Buyer family never wrote it.'
        );
        $this->assertStringNotContainsString(
            'managingMirrors(',
            $source,
            'G1f-5 must use the default managed mirror set, not a zipCodes opt-in.'
        );

        // SCOPE BOUNDARY · no transaction is opened here; the writer owns it.
        $this->assertStringNotContainsString(
            'DB::transaction(',
            $source,
            'G1f-5 must not add an outer transaction — LocationDnaPersistenceService owns it.'
        );

        // The trait itself is untouched, and the one remaining host still uses it.
        $this->assertStringContainsString(
            "empty(\$ldna['cities'] ?? [])",
            file_get_contents(base_path('app/Http/Livewire/Concerns/HasSearchAreas.php')),
            'HasSearchAreas must be unchanged by G1f-5 — it still serves TenantAgentAuctionEdit.'
        );

        $this->assertSame(
            'App\\Http\\Livewire\\HireBuyerAgent\\BuyerAgentAuctionEdit',
            HireBuyerEdit::class,
            'The retained entry in behaviouralComponents() refers to this component.'
        );
    }

    /**
     * CHARACTERISED STRUCTURALLY · `TenantAgentAuctionEdit` carries the same
     * double-write, in the same order, inside `update()`.
     *
     * KNOWN WEAKER ASSERTION, recorded rather than presented as equivalent. The write
     * lives inside `update()`, which runs full validation, file handling and a
     * redirect; invoking it would characterise those rather than the mirror contract.
     * This is the same boundary, taken for the same reason, that
     * `G1aWorkflowPersistenceMatrixCharacterisationTest` and `TenantOfferCitiesMirrorTest`
     * already establish for this component.
     *
     * The behaviour itself is proven on the three invocable Hire components above, and
     * the trait executing the winning write is shared code — so what rests on this
     * structural assertion is only that this component still calls it, in that order.
     */
    public function test_hire_tenant_edit_no_longer_carries_the_double_write(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/TenantAgentAuctionEdit.php'));

        $writerCall    = strpos($source, '$this->persistLocationDna($auction);');
        $propertyWrite = strpos($source, "\$auction->saveMeta('cities', json_encode(\$this->cities));");

        $this->assertNotFalse(
            $writerCall,
            'TenantAgentAuctionEdit must reach the canonical writer — G1f-6 migrated it.'
        );
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            $source,
            'and must no longer call the trait save. With this gone, NO component in the '
            .'application performs the double-write this suite documents.'
        );

        // THE ORDERING ASSERTION, INVERTED — and it is the precise evidence of the restructure.
        //
        // Before: the property write stood ABOVE the gate and therefore BEFORE the trait call,
        // which is what made it the losing half of a double-write for buyer/tenant.
        // After: the property write survives only in the seller/landlord ELSE branch, so it must
        // now come AFTER the writer call in the if-branch. A migration that left it above the gate
        // would keep writing property-sourced mirrors for buyer/tenant and this would fail.
        $this->assertNotFalse(
            $propertyWrite,
            'The component-property cities write must SURVIVE — seller and landlord have no '
            .'canonical document, so it is their only mirror write.'
        );
        $this->assertGreaterThan(
            $writerCall,
            $propertyWrite,
            'The surviving property write must sit in the seller/landlord else branch, AFTER the '
            .'gated writer call — not above the gate where it used to run for all four roles.'
        );
    }
}
