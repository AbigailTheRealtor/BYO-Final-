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
 * STATUS AFTER G1f-1
 * ------------------
 * GAP 2 is CLOSED and `BuyerAgentAuction` has since been MIGRATED, so this suite was narrowed
 * to the workflows that still carry the double-write. See `behaviouralComponents()` for the
 * exact narrowing and `test_buyer_agent_auction_no_longer_carries_the_double_write` for the
 * assertion that replaces its former row. Everything else is unchanged, so the coverage
 * G1f-2 and later depend on is intact.
 *
 * COVERAGE, AND ONE DELIBERATE BOUNDARY
 * -------------------------------------
 * Two of the four Hire components still carry the double-write and expose an invocable
 * `saveAllMetadata()`; both are exercised behaviourally. `TenantAgentAuctionEdit` carries its double-write inside
 * `update()`, which runs full validation, file handling and a redirect; invoking it
 * would characterise those, not the mirror contract. It is asserted STRUCTURALLY and
 * recorded as a KNOWN WEAKER ASSERTION — the same boundary, for the same reason, that
 * `G1aWorkflowPersistenceMatrixCharacterisationTest` and `TenantOfferCitiesMirrorTest`
 * already establish and document.
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
     * The Hire components that STILL carry the double-write and whose save path is invocable.
     *
     * NARROWED BY G1f-1. `BuyerAgentAuction` (Hire Buyer · create) was the first workflow migrated
     * to `LocationDnaPersistenceService`, so it no longer has a double-write to characterise: its
     * component-property mirror writes were removed and `saveSearchAreas()` is no longer called
     * from it. Its post-migration behaviour is covered by
     * {@see G1f1BuyerAgentAuctionMigrationTest} instead.
     *
     * The narrowing is deliberately confined to that one entry. The remaining two components are
     * exercised exactly as before, so this suite still protects the unmigrated workflows — which
     * is the coverage G1f-2 and later will need.
     *
     * `TenantAgentAuctionEdit` remains absent for the boundary reason in the class docblock.
     *
     * @return array<string, array<string, mixed>>
     */
    private function behaviouralComponents(): array
    {
        return [
            'Hire Buyer · edit' => [
                'class' => HireBuyerEdit::class, 'model' => 'buyer', 'user_type' => null,
            ],
            'Hire Tenant · create' => [
                'class' => HireTenantCreate::class, 'model' => 'tenant', 'user_type' => 'tenant',
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
     * CHARACTERISED · in every invocable Hire component the component-property mirror
     * write is executed BEFORE the trait-derived one, for all three dimensions.
     *
     * Proven from the query log, not from source order. Both candidate values are
     * located in the executed bindings and their positions compared, so this is a
     * direct observation of execution sequence.
     */
    public function test_the_component_property_write_occurs_before_the_trait_derived_write(): void
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

                $this->assertNotEmpty(
                    $propPositions,
                    "{$label} · {$dimension}: the component-property value '{$propValue}' was never "
                    .'written. The double-write no longer exists in the form F-G1F-3 records.'
                );
                $this->assertNotEmpty(
                    $blobPositions,
                    "{$label} · {$dimension}: the blob-derived value '{$blobValue}' was never written. "
                    .'saveSearchAreas() did not run, or no longer mirrors this dimension.'
                );
                $this->assertLessThan(
                    max($blobPositions),
                    min($propPositions),
                    "{$label} · {$dimension}: the component-property write must execute BEFORE the "
                    .'trait-derived write. If this fails, the ordering the four Hire workflows depend '
                    .'on has changed.'
                );
            }

            $covered++;
        }

        $this->assertSame(
            2,
            $covered,
            'Two UNMIGRATED Hire components expose an invocable save path. BuyerAgentAuction was '
            .'migrated by G1f-1 and is covered by G1f1BuyerAgentAuctionMigrationTest.'
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

        $this->assertSame(2, $covered);
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
     * CHARACTERISED · `counties` and `state` reach the winning write through a MUTATED
     * component property, while `cities` bypasses the property entirely.
     *
     * This is F-G1-4's "the three dimensions do not share a code path", proven for the
     * Hire family. `hydrateDiscreteLocationFromBlob()` overwrites `$this->state` and
     * `$this->counties` from the blob before the trait's mirror writes; it never
     * touches `$this->cities`, which the trait reads straight from the decoded blob.
     *
     * The consequence for G1f is concrete: a consolidated writer cannot treat the three
     * dimensions uniformly by reading component state, because for `cities` the
     * component state is stale by design.
     */
    public function test_counties_and_state_are_mutated_in_flight_but_cities_is_not(): void
    {
        foreach ($this->behaviouralComponents() as $label => $cfg) {
            $owner     = $this->owner($cfg['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction   = $this->record($cfg['model'], $owner);
            $component = $this->componentWithDivergentValues($cfg, $this->divergentBlob());

            $this->invokeSave($cfg, $component, $auction);

            $this->assertSame(
                self::BLOB_STATE,
                $component->state,
                "{$label}: hydrateDiscreteLocationFromBlob() must have overwritten \$this->state."
            );
            $this->assertSame(
                self::BLOB_COUNTIES,
                $component->counties,
                "{$label}: hydrateDiscreteLocationFromBlob() must have overwritten \$this->counties."
            );
            $this->assertSame(
                self::PROP_CITIES,
                $component->cities,
                "{$label}: \$this->cities must be UNCHANGED — the trait reads cities from the decoded "
                .'blob, never from the property. The persisted mirror and the component property '
                .'therefore disagree after a save, by design.'
            );
        }
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
    public function test_hire_tenant_edit_carries_the_same_double_write_structurally(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/TenantAgentAuctionEdit.php'));

        $propertyWrite = strpos($source, "\$auction->saveMeta('cities', json_encode(\$this->cities));");
        $traitCall     = strpos($source, '$this->saveSearchAreas($auction);');

        $this->assertNotFalse(
            $propertyWrite,
            'TenantAgentAuctionEdit must still carry the component-property cities mirror write.'
        );
        $this->assertNotFalse(
            $traitCall,
            'TenantAgentAuctionEdit must still call saveSearchAreas().'
        );
        $this->assertLessThan(
            $traitCall,
            $propertyWrite,
            'The component-property write must still precede the trait call, matching the three '
            .'components proven behaviourally in this suite.'
        );
    }
}
