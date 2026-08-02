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
     * and unrelated to the gate itself. `saveAllMetadata()` builds a role key as
     * `$this->user_type . '_specific'` and indexes `$compatibility_preferences` with it
     * (`TenantAgentAuction.php:4689`). That array declares exactly four role keys —
     * `tenant_specific`, `buyer_specific`, `seller_specific`, `landlord_specific` — so an
     * empty or unrecognised `user_type` raises `Undefined array key`. It is recorded here
     * because line 4689 executes AFTER the mirror writes at :4282-:4285 and after the
     * gate at :4290, and `TenantAgentAuction` opens no transaction — so the raise leaves a
     * PARTIAL save behind. See test_an_unrecognised_user_type_leaves_a_partial_write.
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
                'blob_written' => $written === self::BLOB,
                'raised'       => $error !== null,
            ];

            $this->assertSame(
                $case['expected_blob_write'],
                $observed[$label]['blob_written'],
                "user_type '{$label}': expected the RECORDED CURRENT behaviour "
                .($case['expected_blob_write'] ? 'blob written' : 'NO blob write')
                .'. If this fails, the gate at TenantAgentAuction.php:4290 has changed.'
            );
        }

        $this->assertSame(
            2,
            count(array_filter($observed, fn ($o) => $o['blob_written'])),
            'Exactly two of the six user_type input classes currently reach the canonical writer.'
        );
    }

    /**
     * CHARACTERISED · the discrete mirrors ARE written for every `user_type`, including
     * the four that never reach the canonical writer.
     *
     * This is what makes the gate a divergence generator rather than a simple skip: the
     * mirrors move for all six input classes while the blob moves for only two.
     */
    public function test_discrete_mirrors_are_written_for_every_user_type(): void
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
     * CHARACTERISED · an empty or unrecognised `user_type` raises mid-save and leaves a
     * PARTIAL write behind, because there is no transaction.
     *
     * Discovered while closing GAP 3, and independent of the gate itself. The role key
     * `$this->user_type . '_specific'` (`TenantAgentAuction.php:4689`) is undefined for
     * any value outside the four declared roles, so the save raises — but only after the
     * discrete mirrors have already been written at :4282-:4285. `TenantAgentAuction`
     * opens no transaction, so those writes are committed and the remainder of the save
     * never happens.
     *
     * This matters to G1f beyond the gate: it is a concrete instance of the report's §7
     * finding that atomicity is the exception, and it is the failure mode
     * `LocationDnaPersistenceService` must not inherit.
     */
    public function test_an_unrecognised_user_type_leaves_a_partial_write(): void
    {
        foreach (['' => 'missing', 'nonsense' => 'invalid'] as $userType => $label) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $error = $this->attemptSave((string) $userType, $auction, self::BLOB);

            $this->assertNotNull(
                $error,
                "user_type '{$label}': expected the save path to raise."
            );
            $this->assertStringContainsString(
                '_specific',
                $error->getMessage(),
                "user_type '{$label}': expected the undefined role-key error at :4689, not some "
                .'other failure.'
            );

            $stored = $this->reread($auction);

            $this->assertSame(
                json_encode(['Tampa']),
                (string) $stored->info('cities'),
                "user_type '{$label}': the discrete mirror written BEFORE the raise is committed — "
                .'a partial save, because no transaction wraps this path.'
            );
            $this->assertFalse(
                $stored->info('location_dna_preferences'),
                "user_type '{$label}': the canonical blob is never written — the gate excluded it "
                .'before the raise occurred.'
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

        $this->assertSame(
            self::BLOB,
            (string) $this->reread($auction)->info('location_dna_preferences'),
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
     * ungated call to `saveSearchAreas()` were ever added, the gate would stop being
     * total and this assertion would fail.
     */
    public function test_the_gate_is_the_only_path_to_the_canonical_writer_in_both_components(): void
    {
        foreach ([
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = file_get_contents(base_path($relative));

            $this->assertStringContainsString(
                "in_array(\$this->user_type, ['buyer', 'tenant'])",
                $source,
                "{$relative}: the user_type gate must still be present."
            );

            $callSites = substr_count($source, '$this->saveSearchAreas($auction);');

            $this->assertSame(
                1,
                $callSites,
                "{$relative}: saveSearchAreas() must have exactly ONE call site, inside the gate. "
                .'A second call site would make the gate non-total and change the characterisation.'
            );
        }
    }
}
