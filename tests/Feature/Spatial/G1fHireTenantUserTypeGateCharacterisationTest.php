<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\TenantAgentAuction as HireTenantCreate;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;
use Throwable;

/**
 * G1f GAP 3 — the Hire Tenant `user_type` gate.
 *
 * WHY THIS SUITE EXISTS
 * ---------------------
 * The G1f pre-implementation report records F-G1F-4: in both Hire Tenant components the
 * canonical blob write is CONDITIONAL.
 *
 *   // TenantAgentAuction.php:4290        TenantAgentAuctionEdit.php:3456
 *   if (in_array($this->user_type, ['buyer', 'tenant'])) {
 *       $this->saveSearchAreas($auction);
 *       $this->saveImportantPlaces($auction);
 *   }
 *
 * `saveSearchAreas()` is the ONLY call site in either file, so for a record whose
 * `user_type` is `seller` or `landlord`:
 *
 *   - the canonical blob is never written, on create or on edit;
 *   - the discrete mirrors ARE written, from component properties, a few lines earlier;
 *   - any pre-existing blob becomes permanently stale while the mirrors keep moving.
 *
 * That is a live blob/mirror divergence generator. No G1 document named it before the
 * G1f audit and no test covered it: G1a's eight-workflow matrix ran these components as
 * `tenant` only, so the `seller` / `landlord` branch is unexecuted in the entire suite.
 *
 * WHY SOURCE INSPECTION IS NOT SUFFICIENT
 * ---------------------------------------
 * The gate is plainly visible. Its CONSEQUENCE is not: a permanently stale blob beside a
 * moving mirror is a multi-save emergent property, and only a save → load → save
 * sequence shows the divergence widening rather than self-correcting.
 *
 * WHAT THIS SUITE DOES NOT DO
 * ---------------------------
 * It does not judge the gate. Whether a seller or landlord Hire Tenant record is CORRECT
 * to have no search envelope is owner decision D-G1F-3, which is open. This suite records
 * today's behaviour exactly, including the parts that look undesirable, so that decision
 * can be made against evidence rather than against a reading of the source.
 */
class G1fHireTenantUserTypeGateCharacterisationTest extends TestCase
{
    use DatabaseTransactions;

    private const BLOB = '{"cities":["Orlando"],"state":"GA"}';

    /**
     * Every `user_type` the gate can see, what it currently does, and whether the save
     * path completes at all.
     *
     * Both columns record OBSERVED behaviour, not desired behaviour.
     *
     * `raises` is a SECOND, independent failure mode discovered while closing this gap
     * and unrelated to the gate itself. Before G1f-2, `saveAllMetadata()` built a role key
     * as `$this->user_type . '_specific'` and indexed `$compatibility_preferences` with it.
     * That array declares exactly four role keys — `tenant_specific`, `buyer_specific`,
     * `seller_specific`, `landlord_specific` — so an empty or unrecognised `user_type`
     * raised `Undefined array key`, but only AFTER the mirror writes had already run, with
     * no transaction anywhere: a PARTIAL save.
     *
     * UPDATED BY G1f-2. The raise still happens for those two input classes — the gate's
     * shape is unchanged and no value is coerced into a role — but it now happens BEFORE
     * any Location DNA write, so nothing is left half-written. The column therefore still
     * reads `true`; what changed is where the failure occurs and what it leaves behind.
     * See test_an_unrecognised_user_type_writes_no_location_dna_at_all.
     *
     * @return array<string, array{user_type: mixed, expected_blob_write: bool, raises: bool}>
     */
    private function userTypes(): array
    {
        return [
            'tenant'   => ['user_type' => 'tenant',   'expected_blob_write' => true,  'raises' => false],
            'buyer'    => ['user_type' => 'buyer',    'expected_blob_write' => true,  'raises' => false],
            'seller'   => ['user_type' => 'seller',   'expected_blob_write' => false, 'raises' => false],
            'landlord' => ['user_type' => 'landlord', 'expected_blob_write' => false, 'raises' => false],
            'missing'  => ['user_type' => '',         'expected_blob_write' => false, 'raises' => true],
            'invalid'  => ['user_type' => 'nonsense', 'expected_blob_write' => false, 'raises' => true],
        ];
    }

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'tenant']);
    }

    private function record(User $owner, array $meta = []): TenantAgentAuction
    {
        $auction = TenantAgentAuction::factory()->create(['user_id' => $owner->id]);

        foreach (array_merge(['user_type' => 'tenant', 'property_items' => '[]'], $meta) as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    private function reread(TenantAgentAuction $auction): TenantAgentAuction
    {
        return TenantAgentAuction::with('meta')->findOrFail($auction->id);
    }

    /**
     * True when the stored canonical document carries `self::BLOB`'s MEANING.
     *
     * SEMANTIC, NOT BYTE-IDENTICAL — and that is a G1f-2 change, recorded rather than hidden.
     * Before the migration this component wrote the submitted string through verbatim, so a
     * byte comparison was a fair test of "the blob was written". The canonical writer
     * serialises deterministically and stamps `schema_version: 2` (F-G1F-10, §27.7), so the
     * stored bytes legitimately differ from the submitted ones while the meaning does not.
     *
     * §5.3 withdrew the byte-compatibility guarantee in favour of semantic equality, so this
     * helper asserts what the contract actually promises. A byte comparison here would now
     * fail for a correct write, which is precisely the false alarm §27.7 warns about.
     */
    private function storesTheBlobMeaning(mixed $stored): bool
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($decoded)
            && ($decoded['cities'] ?? null) === ['Orlando']
            && ($decoded['state'] ?? null) === 'GA';
    }

    /**
     * Invoke the real save path for a given `user_type`.
     *
     * Returns the Throwable when the save path raises for that `user_type` rather than
     * completing — itself a characterisation result, since a `user_type` the component
     * cannot process is a real input class the gate must be understood against.
     */
    private function attemptSave(string $userType, TenantAgentAuction $auction, ?string $blob): ?Throwable
    {
        $component            = new HireTenantCreate();
        $component->user_type = $userType;
        $component->cities    = ['Tampa'];
        $component->counties  = ['Hillsborough'];
        $component->state     = 'FL';

        if ($blob !== null) {
            $component->location_dna_preferences_json = $blob;
        }

        $method = new ReflectionMethod(HireTenantCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);

        try {
            $method->invoke($component, $auction);
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

    /**
     * The `user_type` gate that most closely guards a given statement.
     *
     * Both components use `in_array($this->user_type, [...])` for several unrelated guards, so
     * asserting the literal against the whole file proves nothing about the gate the canonical
     * writer actually sits behind. This returns the LAST such test before the entry point,
     * which is that gate.
     */
    private function gateGuarding(string $source, string $entryPoint): string
    {
        $at = strpos($source, $entryPoint);

        $this->assertNotFalse($at, "entry point `{$entryPoint}` not found");

        preg_match_all(
            '/in_array\(\$this->user_type, \[[^\]]*\]\)/',
            substr($source, 0, $at),
            $matches
        );

        $this->assertNotEmpty($matches[0], "no user_type gate precedes `{$entryPoint}`");

        return (string) end($matches[0]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE GATE · which user_types reach the canonical write
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · the canonical blob write occurs for `buyer` and `tenant` only.
     *
     * The core GAP 3 assertion. Four of the six input classes never reach the canonical
     * writer at all.
     */
    public function test_canonical_blob_write_occurs_only_for_buyer_and_tenant_user_types(): void
    {
        $observed = [];

        foreach ($this->userTypes() as $label => $case) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $error = $this->attemptSave($case['user_type'], $auction, self::BLOB);

            $stored  = $this->reread($auction);
            $written = $stored->info('location_dna_preferences');

            $observed[$label] = [
                'blob_written' => $this->storesTheBlobMeaning($written),
                'raised'       => $error !== null,
            ];

            $this->assertSame(
                $case['expected_blob_write'],
                $observed[$label]['blob_written'],
                "user_type '{$label}': expected the RECORDED CURRENT behaviour "
                .($case['expected_blob_write'] ? 'blob written' : 'NO blob write')
                .'. If this fails, the user_type gate has changed — D-G1F-3 option 3-C keeps it.'
            );
        }

        $this->assertSame(
            2,
            count(array_filter($observed, fn ($o) => $o['blob_written'])),
            'Exactly two of the six user_type input classes currently reach the canonical writer.'
        );
    }

    /**
     * CHARACTERISED · the discrete mirrors are written for every SUPPORTED `user_type`,
     * including the two that never reach the canonical writer — and for neither of the
     * two unsupported ones.
     *
     * This is what makes the gate a divergence generator rather than a simple skip: the
     * mirrors still move for all four supported roles while the blob moves for only two.
     *
     * UPDATED BY G1f-2, in one direction only. Before the migration the mirrors moved for
     * all SIX input classes, including the two the component cannot process — that was the
     * partial write, not a feature. The four supported rows are unchanged; the two
     * unsupported rows now assert the absence the repair introduced.
     */
    public function test_discrete_mirrors_are_written_for_every_supported_user_type(): void
    {
        foreach ($this->userTypes() as $label => $case) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $error = $this->attemptSave($case['user_type'], $auction, self::BLOB);

            // Asserted rather than skipped: a `continue` here would let this test pass
            // vacuously. The raise itself is characterised, not tolerated.
            $this->assertSame(
                $case['raises'],
                $error !== null,
                "user_type '{$label}': expected the RECORDED CURRENT outcome "
                .($case['raises'] ? 'the save path RAISES' : 'the save path completes')
                .'. '.($error !== null ? 'Raised: '.$error->getMessage() : '')
            );

            $stored = $this->reread($auction);

            if ($case['raises']) {
                $this->assertFalse(
                    $stored->info('cities'),
                    "user_type '{$label}': an unsupported user_type must write NO mirror. `info()` "
                    .'returns false for an unwritten meta key.'
                );
                $this->assertFalse(
                    $stored->info('state'),
                    "user_type '{$label}': an unsupported user_type must write no `state` mirror."
                );

                continue;
            }

            $this->assertNotFalse(
                $stored->info('cities'),
                "user_type '{$label}': the discrete `cities` mirror is written regardless of the gate."
            );
            $this->assertNotFalse(
                $stored->info('state'),
                "user_type '{$label}': the discrete `state` mirror is written regardless of the gate."
            );
        }
    }

    /**
     * CHARACTERISED · for a gated `user_type` the mirrors carry the COMPONENT PROPERTY
     * values, uncorrected by the blob.
     *
     * With `seller`, the blob says Orlando/GA and the properties say Tampa/FL. Because
     * `saveSearchAreas()` never runs, nothing overwrites the property-sourced mirror —
     * the inverse of what the three ungated Hire components do (GAP 2).
     */
    public function test_gated_user_types_persist_component_property_mirrors_uncorrected_by_the_blob(): void
    {
        foreach (['seller', 'landlord'] as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $error = $this->attemptSave($userType, $auction, self::BLOB);

            $this->assertNull(
                $error,
                "user_type '{$userType}': the save path completes today; a skip here would make the "
                .'assertions below vacuous.'
            );

            $stored = $this->reread($auction);

            $this->assertSame(
                json_encode(['Tampa']),
                (string) $stored->info('cities'),
                "user_type '{$userType}': the mirror must hold the component-property value, because "
                .'the trait never ran to overwrite it with the blob value.'
            );
            $this->assertSame(
                'FL',
                (string) $stored->info('state'),
                "user_type '{$userType}': the mirror must hold the component-property state."
            );
        }
    }

    /**
     * REPAIRED BY G1f-2 · an empty or unrecognised `user_type` still raises, and now
     * writes NO Location DNA at all.
     *
     * THE DEFECT THIS REPLACES, RECORDED SO THE CHANGE IS LEGIBLE.
     * ------------------------------------------------------------
     * This test previously asserted the opposite outcome, under the name
     * `test_an_unrecognised_user_type_leaves_a_partial_write`. The role key
     * `$this->user_type . '_specific'` is undefined for any value outside the four declared
     * roles, so the save raised — but only after the discrete mirrors had already been
     * written, with no transaction anywhere in between. Those mirror writes were committed
     * and the remainder of the save never happened. That was defect boundary 1: a concrete
     * instance of the report's §7 finding that atomicity is the exception, and the failure
     * mode `LocationDnaPersistenceService` must not inherit.
     *
     * WHAT G1f-2 CHANGED, AND WHAT IT DID NOT.
     * ----------------------------------------
     * Changed: `user_type` is validated BEFORE the Location Information block, so the
     * rejection lands while every Location DNA key still holds its stored value.
     *
     * NOT changed: the value is still rejected rather than repaired. No unrecognised
     * `user_type` is coerced into `buyer`, `tenant`, `seller` or `landlord`, so the gate
     * never acts on a role the user did not supply.
     */
    public function test_an_unrecognised_user_type_writes_no_location_dna_at_all(): void
    {
        foreach (['' => 'missing', 'nonsense' => 'invalid'] as $userType => $label) {
            $owner = $this->owner();

            // Pre-existing Location DNA state, so this proves PRESERVATION and not merely
            // that an empty record stayed empty — which would pass vacuously.
            $auction = $this->record($owner, [
                'location_dna_preferences' => '{"cities":["Sarasota"],"state":"FL"}',
                'cities'                   => json_encode(['Sarasota']),
                'counties'                 => json_encode(['Sarasota County']),
                'state'                    => 'FL',
                'zipCodes'                 => json_encode(['34236']),
            ]);

            $error = $this->attemptSave((string) $userType, $auction, self::BLOB);

            $this->assertNotNull(
                $error,
                "user_type '{$label}': expected the save path to raise — an unsupported role is "
                .'rejected, never guessed at.'
            );
            $this->assertStringContainsString(
                'Location DNA cannot be persisted for user_type',
                $error->getMessage(),
                "user_type '{$label}': expected the Location DNA precondition failure, not the "
                .'former undefined role-key error that fired far too late.'
            );

            $stored = $this->reread($auction);

            $this->assertSame(
                '{"cities":["Sarasota"],"state":"FL"}',
                (string) $stored->info('location_dna_preferences'),
                "user_type '{$label}': the canonical document must be untouched, byte for byte."
            );
            $this->assertSame(
                json_encode(['Sarasota']),
                (string) $stored->info('cities'),
                "user_type '{$label}': the `cities` mirror must NOT have advanced. This is the "
                .'assertion that inverted: it used to hold the new value, committed by a partial save.'
            );
            $this->assertSame(
                json_encode(['Sarasota County']),
                (string) $stored->info('counties'),
                "user_type '{$label}': the `counties` mirror must not have advanced."
            );
            $this->assertSame(
                'FL',
                (string) $stored->info('state'),
                "user_type '{$label}': the `state` mirror must not have advanced."
            );
            $this->assertSame(
                json_encode(['34236']),
                (string) $stored->info('zipCodes'),
                "user_type '{$label}': the unmanaged `zipCodes` mirror must not have advanced "
                .'either — the rejection precedes it too.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE CONSEQUENCE · divergence widens across saves
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED · a pre-existing blob on a `seller` record goes stale while the
     * mirrors keep moving, and the gap widens with each save.
     *
     * The multi-save emergent property that source inspection cannot show. A record that
     * once had a canonical blob — for instance because its `user_type` changed, or
     * because it was created through a different path — retains that blob unchanged
     * forever, while every subsequent save advances the mirrors.
     */
    public function test_a_pre_existing_blob_goes_stale_while_mirrors_advance_across_two_saves(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => '{"cities":["Sarasota"],"state":"FL"}',
        ]);

        $original = (string) $this->reread($auction)->info('location_dna_preferences');
        $this->assertSame('{"cities":["Sarasota"],"state":"FL"}', $original, 'fixture precondition');

        // First save as seller, mirrors set to one value.
        $first            = new HireTenantCreate();
        $first->user_type = 'seller';
        $first->cities    = ['Tampa'];
        $first->counties  = ['Hillsborough'];
        $first->state     = 'FL';
        $first->location_dna_preferences_json = '{"cities":["Orlando"],"state":"GA"}';

        $method = new ReflectionMethod(HireTenantCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);

        try {
            $method->invoke($first, $auction);
        } catch (Throwable $e) {
            $this->markTestSkipped('seller save path raised: '.$e->getMessage());
        }

        $afterFirst = $this->reread($auction);

        // Second save as seller, mirrors moved again.
        $second            = new HireTenantCreate();
        $second->user_type = 'seller';
        $second->cities    = ['Naples'];
        $second->counties  = ['Collier'];
        $second->state     = 'FL';
        $second->location_dna_preferences_json = '{"cities":["Miami"],"state":"GA"}';
        $method->invoke($second, $afterFirst);

        $afterSecond = $this->reread($auction);

        $this->assertSame(
            $original,
            (string) $afterSecond->info('location_dna_preferences'),
            'The canonical blob must be UNCHANGED after two seller saves — the gate never lets the '
            .'writer run, so the blob is frozen at whatever it held.'
        );
        $this->assertSame(
            json_encode(['Naples']),
            (string) $afterSecond->info('cities'),
            'The mirror must have advanced twice while the blob stood still. This is the divergence '
            .'F-G1F-4 describes, measured.'
        );
        $this->assertNotSame(
            (string) $afterSecond->info('cities'),
            json_encode(json_decode($original, true)['cities']),
            'Blob and mirror must now disagree — the record is internally inconsistent and nothing '
            .'surfaced an error.'
        );
    }

    /**
     * CHARACTERISED · an ungated `user_type` on the same fixture DOES advance the blob.
     *
     * The control. It proves the frozen blob above is caused by the gate and not by some
     * unrelated property of the fixture or the save path.
     */
    public function test_control_tenant_user_type_advances_the_blob_on_the_same_fixture(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => '{"cities":["Sarasota"],"state":"FL"}',
        ]);

        $error = $this->attemptSave('tenant', $auction, self::BLOB);
        $this->assertNull($error, 'the tenant save path must complete');

        $this->assertTrue(
            $this->storesTheBlobMeaning($this->reread($auction)->info('location_dna_preferences')),
            'With an ungated user_type the canonical blob is written, on the identical fixture.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STRUCTURAL · the edit component
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * CHARACTERISED STRUCTURALLY · `TenantAgentAuctionEdit` carries the identical gate,
     * and `saveSearchAreas()` has no other call site in either component.
     *
     * KNOWN WEAKER ASSERTION for the edit component, whose write lives inside `update()`
     * — the boundary documented in the class docblock of
     * `G1fHireDoubleWriteCharacterisationTest` and established by the G1a suites.
     *
     * The "only call site" half is the load-bearing part and is exact: if a second,
     * ungated call to the canonical writer were ever added, the gate would stop being
     * total and this assertion would fail.
     *
     * UPDATED BY G1f-2 · the two components now reach the canonical writer by different
     * routes, so the entry point is asserted per component rather than as one string.
     * `TenantAgentAuction` calls `persistLocationDna()`; `TenantAgentAuctionEdit` still
     * calls the trait's `saveSearchAreas()`, unmigrated. The property under test — exactly
     * one call site, inside the gate — is identical for both, and is what a future
     * increment must not weaken.
     */
    public function test_the_gate_is_the_only_path_to_the_canonical_writer_in_both_components(): void
    {
        foreach ([
            // component => the single statement that reaches the canonical writer
            'app/Http/Livewire/TenantAgentAuction.php'     => '$this->persistLocationDna($auction);',
            'app/Http/Livewire/TenantAgentAuctionEdit.php' => '$this->saveSearchAreas($auction);',
        ] as $relative => $entryPoint) {
            $source = file_get_contents(base_path($relative));

            // The literal appears several times in each file for unrelated guards, so a
            // whole-file search would pass even if THIS gate were widened. The decisive
            // question is which gate the writer sits behind, so the nearest preceding one is
            // the one asserted. (Found by non-vacuity probe 6.)
            $this->assertSame(
                "in_array(\$this->user_type, ['buyer', 'tenant'])",
                $this->gateGuarding($source, $entryPoint),
                "{$relative}: the gate immediately guarding the canonical writer must still admit "
                .'buyer and tenant only — D-G1F-3, option 3-C.'
            );

            $callSites = substr_count($source, $entryPoint);

            $this->assertSame(
                1,
                $callSites,
                "{$relative}: `{$entryPoint}` must have exactly ONE call site, inside the gate. "
                .'A second call site would make the gate non-total and change the characterisation.'
            );
        }

        // The migrated component must not retain BOTH routes — that would be a new double-write.
        $this->assertStringNotContainsString(
            '$this->saveSearchAreas($auction);',
            file_get_contents(base_path('app/Http/Livewire/TenantAgentAuction.php')),
            'TenantAgentAuction is migrated; the trait save must be gone, not merely accompanied.'
        );
    }
}
