<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing as BuyerOfferCreate;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit as BuyerOfferEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as TenantOfferCreate;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit as TenantOfferEdit;
use App\Http\Livewire\TenantAgentAuction as HireTenantCreate;
use App\Http\Livewire\TenantAgentAuctionEdit as HireTenantEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1a residue — persistence and presence characterisation across ALL EIGHT workflows.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * §17 G1's prerequisite is per workflow: characterisation must exist for each
 * workflow the consolidation touches, before it is touched, and §16.3 forbids
 * migrating a workflow that has none. Before this suite, per-component persistence
 * characterisation stood at 1-of-8 — `SearchAreasPersistenceCharacterisationTest`
 * covers `TenantAgentAuction` and nothing else. The earlier G1a increment broadened
 * *dimension* coverage through a thin host; it did not broaden *component* coverage.
 *
 * This suite exercises the eight real components through their real entry points.
 *
 * THE MATRIX IT ESTABLISHES
 * -------------------------
 * The load-side outcome for an intentionally cleared `cities` list, per workflow:
 *
 *   Hire Buyer create / edit      trait     → RESURRECTED  (defect)
 *   Hire Tenant create / edit     trait     → RESURRECTED  (defect)
 *   Buyer Offer create / edit     inline    → RESURRECTED  (defect)
 *   Tenant Offer create / edit    inline    → PRESERVED    (correct)
 *
 * Six of eight workflows lose the user's clear; two honour it. That six-to-two split
 * is the parity baseline G1f must move to eight-to-zero without changing anything
 * else — and it is the reason consolidation cannot simply adopt whichever
 * implementation is most common.
 *
 * VEHICLES, AND WHY THEY DIFFER
 * -----------------------------
 * `TenantAgentAuction` has a factory; `BuyerAgentAuction` does not, so its rows are
 * built with `forceFill()` — the vehicle `HireSearchAreasParityTest` established.
 * Components are instantiated directly and their real load methods called, rather
 * than booted through the Livewire lifecycle, for the reason
 * `TenantOfferCitiesMirrorTest` documents: a full boot would characterise hundreds
 * of unrelated required props instead of the mirror contract.
 *
 * Every load entry point scopes by `Auth::id()`, so `actingAs()` is load-bearing —
 * without it the record is not found, the hydration block never runs, and every
 * assertion would pass vacuously.
 *
 * TWO WORKFLOWS CANNOT BE SAVED BEHAVIOURALLY
 * -------------------------------------------
 * `HireTenantEdit` and `TenantOfferEdit` carry their blob write inside `update()`,
 * which runs full validation, file handling and a redirect. Invoking it would test
 * those, not the mirror. Those two are asserted structurally instead, and recorded
 * as a **known weaker assertion** rather than presented as equivalent — the same
 * treatment and the same wording `TenantOfferCitiesMirrorTest` uses for its own
 * edit-flow write. The other six are exercised behaviourally.
 *
 * CHARACTERISATION, NOT REPAIR
 * ----------------------------
 * Every assertion records today's behaviour. No owner decision D-G1-1 … D-G1-6 is
 * assumed. PHP and database only — nothing here executes `window.ldnaSerialize`.
 */
class G1aWorkflowPersistenceMatrixCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The eight Search Envelope workflows.
     *
     * `cleared_cities` records the OBSERVED load-side outcome, not the desired one.
     * `save` is the protected save method where one is invocable, or null where the
     * write lives inside `update()`.
     *
     * @return array<string, array<string, mixed>>
     */
    private function workflows(): array
    {
        return [
            'Hire Buyer · create' => [
                'class' => HireBuyerCreate::class, 'model' => 'buyer', 'impl' => 'trait',
                'load' => 'loadDraft', 'user_type' => null,
                'save' => 'saveAllMetadata', 'cleared_cities' => 'resurrected',
                // G1f-1: MIGRATED to LocationDnaPersistenceService. Its LOAD side is unchanged
                // and is still asserted by every load-side test below. Its SAVE side moved to
                // the canonical writer by approved decision, so the two save-side tests that
                // pin pre-consolidation write behaviour skip it — see `migrated_save` below.
                'migrated_save' => true,
            ],
            'Hire Buyer · edit' => [
                'class' => HireBuyerEdit::class, 'model' => 'buyer', 'impl' => 'trait',
                'load' => 'loadAuctionData', 'user_type' => null,
                'save' => 'saveAllMetadata', 'cleared_cities' => 'resurrected',
                // G1f-5: MIGRATED to LocationDnaPersistenceService — the LAST invocable save path
                // to move. Its LOAD side is unchanged and is still asserted by every load-side test
                // below. With this row migrated no unmigrated invocable save path remains, which is
                // why the two save-side tests below now assert the POST-migration property across
                // the migrated set rather than skipping it — see their docblocks.
                'migrated_save' => true,
            ],
            'Hire Tenant · create' => [
                'class' => HireTenantCreate::class, 'model' => 'tenant', 'impl' => 'trait',
                'load' => 'loadDraft', 'user_type' => 'tenant',
                'save' => 'saveAllMetadata', 'cleared_cities' => 'resurrected',
                // G1f-2: MIGRATED to LocationDnaPersistenceService, on the same terms and for the
                // same reason as Hire Buyer create above. Its LOAD side is unchanged and is still
                // asserted by every load-side test below; only its two save-side rows move.
                'migrated_save' => true,
            ],
            'Hire Tenant · edit' => [
                'class' => HireTenantEdit::class, 'model' => 'tenant', 'impl' => 'trait',
                'load' => 'loadAuctionData', 'user_type' => 'tenant',
                'save' => null, 'cleared_cities' => 'resurrected',
            ],
            'Buyer Offer · create' => [
                'class' => BuyerOfferCreate::class, 'model' => 'buyer', 'impl' => 'inline',
                'load' => 'loadDraft', 'user_type' => null,
                'save' => 'saveAllMetadata', 'cleared_cities' => 'resurrected',
                // G1f-3: MIGRATED to LocationDnaPersistenceService, together with the edit copy
                // below. Load side unchanged and still asserted by every load-side test.
                'migrated_save' => true,
            ],
            'Buyer Offer · edit' => [
                'class' => BuyerOfferEdit::class, 'model' => 'buyer', 'impl' => 'inline',
                'load' => 'loadAuctionData', 'user_type' => null,
                'save' => 'saveAllMetadata', 'cleared_cities' => 'resurrected',
                // G1f-3: MIGRATED. Its former 400-line, 315-call untransacted window between the
                // mirror writes and the canonical write is closed.
                'migrated_save' => true,
            ],
            'Tenant Offer · create' => [
                'class' => TenantOfferCreate::class, 'model' => 'tenant', 'impl' => 'inline-divergent',
                'load' => 'loadDraft', 'user_type' => 'tenant',
                'save' => 'saveAllMetadata', 'cleared_cities' => 'preserved',
                // G1f-4: MIGRATED to LocationDnaPersistenceService, together with the edit copy
                // below, and opted into the `zipCodes` mirror. Load side unchanged and still
                // asserted by every load-side test.
                'migrated_save' => true,
            ],
            'Tenant Offer · edit' => [
                'class' => TenantOfferEdit::class, 'model' => 'tenant', 'impl' => 'inline-divergent',
                'load' => 'loadAuctionData', 'user_type' => 'tenant',
                'save' => null, 'cleared_cities' => 'preserved',
                // G1f-4: MIGRATED. Its three separate Location DNA write sites inside update()
                // are consolidated into one call, inside the transaction update() already opened.
                'migrated_save' => true,
            ],
        ];
    }

    private function owner(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    /** Build a real record of the kind the given workflow reads. */
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
            'title'       => 'G1a matrix',
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

    /** Instantiate and hydrate a workflow's component through its real entry point. */
    private function hydrate(array $wf, $auction, User $owner): object
    {
        $this->actingAs($owner);

        $component = new $wf['class']();

        if ($wf['user_type'] !== null) {
            $component->user_type = $wf['user_type'];
        }

        if ($wf['load'] === 'loadAuctionData') {
            $method = new ReflectionMethod($wf['class'], 'loadAuctionData');
            $args   = $method->getNumberOfParameters() >= 2
                ? [$auction->id, $wf['user_type'] ?? 'buyer']
                : [$auction->id];
            $component->loadAuctionData(...$args);
        } else {
            $component->loadDraft($auction->id);
        }

        return $component;
    }

    /**
     * Build a component ready for its real save path.
     *
     * `user_type` is set where the workflow expects one. This is not cosmetic:
     * `TenantAgentAuction::saveAllMetadata()` derives a compatibility-preferences key
     * as `$this->user_type . '_specific'` (line 4689) and raises
     * "Undefined array key" when the prop is empty. That is unrelated to the mirror
     * contract — it is a consequence of exercising the REAL save path, which persists
     * far more than Search Areas. Setting the prop keeps the real path in play rather
     * than substituting a narrower stub.
     */
    private function componentForSave(array $wf): object
    {
        $component = new $wf['class']();

        if ($wf['user_type'] !== null) {
            $component->user_type = $wf['user_type'];
        }

        return $component;
    }

    private function invokeSave(array $wf, object $component, $auction): void
    {
        $method = new ReflectionMethod($wf['class'], $wf['save']);
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE MATRIX · cleared-cities outcome, all eight workflows
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · six of eight workflows resurrect an intentionally cleared
     * `cities` list; two honour it.
     *
     * The single most important assertion for G1f. It fixes the parity baseline for
     * every workflow at once, so a consolidation can be proved to have changed the
     * six and not disturbed the two.
     */
    public function test_cleared_cities_outcome_across_all_eight_workflows(): void
    {
        $observed = [];

        foreach ($this->workflows() as $label => $wf) {
            $owner   = $this->owner($wf['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction = $this->record($wf['model'], $owner, [
                'location_dna_preferences' => json_encode(['cities' => []]),
                'cities'                   => json_encode(['Tampa', 'Miami']),
            ]);

            $component = $this->hydrate($wf, $auction, $owner);
            $cities    = $component->existingLocationDna['cities'] ?? null;

            $observed[$label] = $cities === [] ? 'preserved' : 'resurrected';

            $this->assertSame(
                $wf['cleared_cities'],
                $observed[$label],
                "{$label} ({$wf['impl']}): expected the RECORDED CURRENT behaviour "
                ."'{$wf['cleared_cities']}', observed '{$observed[$label]}'. "
                .'If this fails, the implementation changed — which is the signal G1f needs.'
            );
        }

        // The split itself is the baseline, asserted so a future change to the ratio
        // is visible even if every individual row still matches its own label.
        $this->assertSame(
            6,
            count(array_filter($observed, fn ($o) => $o === 'resurrected')),
            'Six workflows currently lose an intentional clear.'
        );
        $this->assertSame(
            2,
            count(array_filter($observed, fn ($o) => $o === 'preserved')),
            'Two workflows currently honour it — both Tenant Offer.'
        );
    }

    /**
     * CHARACTERISED · the legitimate fallback works in all eight workflows.
     *
     * With the `cities` key ABSENT, every workflow consults the legacy mirror. This
     * is the behaviour the fallback exists for and the one G1f must PRESERVE while
     * changing the present-but-empty case. Asserted across all eight so a
     * consolidation cannot quietly disable recovery for legacy records.
     */
    public function test_absent_cities_key_falls_back_in_all_eight_workflows(): void
    {
        foreach ($this->workflows() as $label => $wf) {
            $owner   = $this->owner($wf['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction = $this->record($wf['model'], $owner, [
                'location_dna_preferences' => json_encode(['state' => 'FL']),
                'cities'                   => json_encode(['Clearwater']),
            ]);

            $component = $this->hydrate($wf, $auction, $owner);

            $this->assertSame(
                ['Clearwater'],
                $component->existingLocationDna['cities'] ?? null,
                "{$label}: an absent cities key must fall back to the legacy mirror."
            );
        }
    }

    /**
     * CHARACTERISED · a populated blob is authoritative in all eight workflows.
     *
     * The third state of the boundary. No workflow lets a stale mirror override a
     * populated blob value.
     */
    public function test_populated_blob_wins_in_all_eight_workflows(): void
    {
        foreach ($this->workflows() as $label => $wf) {
            $owner   = $this->owner($wf['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction = $this->record($wf['model'], $owner, [
                'location_dna_preferences' => json_encode(['cities' => ['Orlando']]),
                'cities'                   => json_encode(['Tampa']),
            ]);

            $component = $this->hydrate($wf, $auction, $owner);

            $this->assertSame(
                ['Orlando'],
                $component->existingLocationDna['cities'] ?? null,
                "{$label}: a populated blob must win over the mirror."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PERSISTENCE · geometry fidelity through each invocable save path
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Geometry survives a write → read → write cycle through every workflow whose save
     * path is invocable (six of eight) — and the stored canonical bytes are STABLE
     * across a re-save.
     *
     * This is `SearchAreasPersistenceCharacterisationTest`'s byte-identity property,
     * extended from one component to six. Includes a 1,200-vertex polygon, because
     * the risk §16.6 names is column truncation rather than PHP-level loss, and
     * unicode, because that is a storage property no unit fake can answer.
     *
     * RESTRUCTURED BY G1f-5, AND WHY IT HAD TO BE
     * -------------------------------------------
     * G1f-1 … G1f-4 each narrowed this test by SKIPPING the workflow they migrated, and
     * asserted a shrinking `$covered` count: 6 → 4 → 2 → 1. G1f-5 migrates the last
     * invocable save path, so continuing that pattern would leave `$covered === 0` — a
     * loop that never executes and a suite that reports green because it asserted
     * nothing. That is the zero-iteration failure mode this restructure exists to avoid.
     *
     * So the test is INVERTED rather than narrowed: it now runs against the MIGRATED
     * invocable save paths — all six of them — and asserts the property in the form that
     * survives migration.
     *
     * WHAT CHANGED ABOUT THE PROPERTY, PRECISELY
     * ------------------------------------------
     * The pre-migration paths wrote `$this->location_dna_preferences_json` through to
     * storage verbatim, so the submitted bytes and the stored bytes were the same bytes.
     * The canonical writer does not do that and must not: it hydrates, applies commands
     * and RE-SERIALISES through {@see \App\Services\LocationDna\Contract\LocationDnaSerializer},
     * which sorts keys at every level and stamps `schema_version`. That class's own
     * contract states it outright — "BYTE-COMPATIBILITY IS WITHDRAWN … what IS guaranteed
     * is determinism". Asserting submitted-equals-stored would therefore assert a
     * guarantee the design deliberately withdrew, and would fail for the right reason.
     *
     * What is asserted instead is the pair of properties that actually protect the data,
     * and both are real:
     *
     *   1. FIDELITY through each migrated save path — the 1,200-vertex polygon is not
     *      truncated, the float radius does not drift, unicode is not mangled and the
     *      notes survive newlines, quotes, an em dash and an emoji. This is the risk
     *      §16.6 names (column truncation), and it is answered per workflow.
     *   2. BYTE IDENTITY at the shared boundary — re-saving the STORED value must leave
     *      the stored bytes untouched, byte for byte. This is the drift half of the
     *      original assertion, and it holds post-migration by construction: equal
     *      canonical meaning yields an equal revision token, the service returns a no-op
     *      before writing, and determinism guarantees the bytes could not have differed
     *      anyway. Asserted with `assertSame` on the raw strings.
     *
     * Between them the two cover what the single byte-identity assertion covered before:
     * nothing is lost on the way in, and nothing drifts on the way back out.
     */
    public function test_geometry_persists_faithfully_and_bytes_are_stable_through_every_migrated_save_path(): void
    {
        $path = [];
        for ($i = 0; $i < 1200; $i++) {
            $path[] = ['lat' => 27.5 + ($i / 100000), 'lng' => -82.5 - ($i / 100000)];
        }

        $encoded = json_encode([
            'cities'          => ["Coeur d'Alene", '東京'],
            'state'           => 'FL',
            'polygons'        => [['label' => 'Huge area', 'path' => $path]],
            'radius_searches' => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 5.25, 'address' => '1 Main St']],
            'location_notes'  => "Line one\nLine \"two\" — em dash, emoji 🏖",
        ]);

        $covered = 0;

        foreach ($this->workflows() as $label => $wf) {
            if ($wf['save'] === null || ! ($wf['migrated_save'] ?? false)) {
                continue;
            }

            $owner   = $this->owner($wf['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction = $this->record($wf['model'], $owner);

            $first = $this->componentForSave($wf);
            $first->location_dna_preferences_json = $encoded;
            $this->invokeSave($wf, $first, $auction);

            $stored = (string) $this->reread($wf['model'], $auction)->info('location_dna_preferences');

            // 1 · FIDELITY — nothing was truncated, drifted or mangled on the way in.
            $decoded = json_decode($stored, true);
            $this->assertIsArray($decoded, "{$label}: stored canonical document must be valid JSON");
            $this->assertCount(1200, $decoded['polygons'][0]['path'], "{$label}: polygon truncated");
            $this->assertSame(
                ['lat' => 27.5, 'lng' => -82.5],
                $decoded['polygons'][0]['path'][0],
                "{$label}: first vertex drifted"
            );
            $this->assertSame(
                ['lat' => 27.5 + (1199 / 100000), 'lng' => -82.5 - (1199 / 100000)],
                $decoded['polygons'][0]['path'][1199],
                "{$label}: last vertex drifted — a truncation that kept the count would show here"
            );
            $this->assertSame(5.25, $decoded['radius_searches'][0]['radius_miles'], "{$label}: radius drifted");
            $this->assertSame('東京', $decoded['cities'][1], "{$label}: unicode mangled");
            $this->assertSame("Coeur d'Alene", $decoded['cities'][0], "{$label}: apostrophe mangled");
            $this->assertSame(
                "Line one\nLine \"two\" — em dash, emoji 🏖",
                $decoded['location_notes'],
                "{$label}: notes lost a newline, quote, em dash or emoji"
            );

            // 2 · BYTE IDENTITY at the shared boundary — re-saving the stored value must not
            // move a single byte. Equal meaning ⇒ equal revision token ⇒ the service returns
            // before writing; and determinism means the bytes could not have differed anyway.
            $second = $this->componentForSave($wf);
            $second->location_dna_preferences_json = $stored;
            $this->invokeSave($wf, $second, $auction);

            $reStored = (string) $this->reread($wf['model'], $auction)->info('location_dna_preferences');
            $this->assertSame($stored, $reStored, "{$label}: canonical bytes drifted across a re-save");

            $covered++;
        }

        $this->assertSame(
            6,
            $covered,
            'All six invocable save paths are MIGRATED and every one was exercised. G1f-1 migrated '
            .'Hire Buyer create, G1f-2 Hire Tenant create, G1f-3 both Buyer Offer copies, G1f-4 both '
            .'Tenant Offer copies and G1f-5 Hire Buyer edit. If this count falls, a workflow stopped '
            .'being exercised and the fidelity property above went unasserted for it — which is the '
            .'zero-iteration failure this restructure exists to prevent.'
        );
    }

    /**
     * CLEAR SEMANTICS ARE UNIFORM · every invocable save path now honours a clear on
     * ALL THREE dimensions, and no component property can resurrect a cleared value.
     *
     * WHAT THIS TEST USED TO SAY, AND WHY IT NOW SAYS THE OPPOSITE
     * -----------------------------------------------------------
     * It characterised F-G1-4: a cleared `cities` mirrored as `[]` while `counties` and
     * `state` kept stale values sourced from the component properties. That THREE-WAY
     * SPLIT was uniform across every unmigrated invocable save path, and its uniformity
     * was the useful part — G1f had one shape of defect to replace, not three.
     *
     * G1f-1 … G1f-4 narrowed the test by skipping each workflow they migrated: 6 → 4 →
     * 2 → 1. G1f-5 migrates the last one, so narrowing again would make `$covered === 0`
     * and the loop would assert nothing at all. The property is therefore INVERTED
     * rather than retired, and it remains a statement about uniformity — the same
     * statement, now with the defect resolved:
     *
     *   before · cities honours the clear, counties and state ignore it   (split)
     *   after  · all three honour the clear                               (uniform)
     *
     * This is D-G1-4 4-A, asserted at the storage layer across all six migrated
     * workflows at once, which is what makes it a PARITY assertion rather than six
     * unrelated ones: had any single migration diverged, its row alone would fail.
     *
     * The divergent component properties are deliberately retained from the original
     * fixture. Pre-migration they were the SOURCE of the stale values; post-migration
     * the writer must derive every mirror from the resulting canonical document instead,
     * so their continued presence is exactly what proves the resurrection route is shut.
     */
    public function test_clear_semantics_are_uniform_across_every_migrated_save_path(): void
    {
        $covered = 0;

        foreach ($this->workflows() as $label => $wf) {
            if ($wf['save'] === null || ! ($wf['migrated_save'] ?? false)) {
                continue;
            }

            $owner   = $this->owner($wf['model'] === 'tenant' ? 'tenant' : 'buyer');
            $auction = $this->record($wf['model'], $owner);

            $component           = $this->componentForSave($wf);
            $component->state    = 'Georgia';
            $component->counties = ['Pinellas'];
            $component->cities   = ['Tampa'];
            $component->location_dna_preferences_json = json_encode([
                'cities'   => [],
                'counties' => [],
                'state'    => '',
            ]);

            $this->invokeSave($wf, $component, $auction);

            $fresh = $this->reread($wf['model'], $auction);

            $this->assertSame('[]', (string) $fresh->info('cities'), "{$label}: cities must honour the clear");
            $this->assertSame('[]', (string) $fresh->info('counties'), "{$label}: counties must honour the clear");
            $this->assertSame('', (string) $fresh->info('state'), "{$label}: state must honour the clear");

            // The resurrection route, shut. None of the divergent property values reached storage.
            $this->assertStringNotContainsString(
                'Pinellas',
                (string) $fresh->info('counties'),
                "{$label}: the component property must not resurrect a cleared counties value"
            );
            $this->assertStringNotContainsString(
                'Tampa',
                (string) $fresh->info('cities'),
                "{$label}: the component property must not resurrect a cleared cities value"
            );

            $covered++;
        }

        $this->assertSame(
            6,
            $covered,
            'All six invocable save paths are MIGRATED and every one was exercised. A falling count '
            .'means a workflow stopped being asserted rather than that the defect was fixed.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE TWO update()-BASED EDIT FLOWS · known weaker assertion
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The two `update()`-based edit flows carry the blob and mirror writes.
     *
     * Asserted structurally rather than behaviourally: `update()` runs full
     * validation, file handling and a redirect, so invoking it would test those
     * rather than the mirror contract. Their load sides ARE characterised
     * behaviourally above, so only the write half rests on this weaker assertion.
     *
     * Recorded as a known weaker assertion rather than presented as equivalent —
     * the same treatment `TenantOfferCitiesMirrorTest` gives its own edit-flow write.
     */
    public function test_update_based_edit_flows_carry_the_blob_and_mirror_writes(): void
    {
        // NARROWED BY G1f-4: `Tenant Offer · edit` migrated, so its inline blob and mirror writes
        // are gone by design. Its replacement — one consolidated writer call inside the existing
        // transaction — is pinned by G1f4MigrationBoundaryGuardTest. The Hire edit sibling remains
        // unmigrated and still carries the original construct.
        $files = [
            'Hire Tenant · edit'  => 'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ];

        foreach ($files as $label => $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString(
                'public function update()',
                $source,
                "{$label}: the write lives inside update()"
            );

            $this->assertTrue(
                str_contains($source, "saveMeta('location_dna_preferences'")
                    || str_contains($source, 'saveSearchAreas($auction)'),
                "{$label}: must persist the blob, either directly or via the trait"
            );
        }
    }

    /**
     * The eight workflows are still exactly the audited set.
     *
     * A structural guard: if a ninth Search Envelope component appears, or one of
     * these is renamed or removed, this fails and the matrix above must be
     * re-derived rather than silently covering seven.
     */
    public function test_the_eight_workflows_are_still_the_audited_set(): void
    {
        $this->assertCount(8, $this->workflows());

        foreach ($this->workflows() as $label => $wf) {
            $this->assertTrue(class_exists($wf['class']), "{$label}: {$wf['class']} must exist");
        }

        $traitHosts = array_filter($this->workflows(), fn ($w) => $w['impl'] === 'trait');
        $inline     = array_filter($this->workflows(), fn ($w) => $w['impl'] === 'inline');
        $divergent  = array_filter($this->workflows(), fn ($w) => $w['impl'] === 'inline-divergent');

        $this->assertCount(4, $traitHosts, 'four workflows use HasSearchAreas');
        $this->assertCount(2, $inline, 'two carry a defective inline copy (Buyer Offer)');
        $this->assertCount(2, $divergent, 'two carry the correct divergent copy (Tenant Offer)');
    }
}
