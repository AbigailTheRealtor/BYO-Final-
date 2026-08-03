<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\TenantAgentAuction as HireTenantCreate;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\LocationDna\Contract\LocationDnaHydrator;
use App\Services\LocationDna\Persistence\LegacyMirrorProjection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * G1f-2 — `TenantAgentAuction` (Hire Tenant · create) migrated to the canonical writer.
 *
 * WHAT THIS INCREMENT DID, IN ONE SENTENCE EACH
 * ---------------------------------------------
 * 1. The `buyer` / `tenant` path writes through `LocationDnaPersistenceService` instead of
 *    writing property-sourced mirrors and then calling `saveSearchAreas()` over the top.
 * 2. `user_type` is validated BEFORE any Location DNA write, closing defect boundary 1.
 *
 * TWO CONCERNS, DELIBERATELY NOT MERGED
 * -------------------------------------
 * The `user_type` gate is PRESERVED (D-G1F-3, option 3-C): `seller` and `landlord` records
 * still get no canonical document, and this migration does not decide whether they should.
 * The partial-write defect is a separate, ordering-only repair. Bundling them would have
 * made "the gate changed" and "the ordering changed" indistinguishable in one commit, so
 * both are asserted here independently.
 *
 * WHY THE REAL SAVE PATH
 * ----------------------
 * Every behavioural test drives `saveAllMetadata()` by reflection — the same entry point
 * `G1aWorkflowPersistenceMatrixCharacterisationTest`, `G1fHireDoubleWriteCharacterisationTest`
 * and the G1f-1 migration suite use. Testing the writer in isolation would prove the writer
 * works, not that this 5,300-line component reaches it correctly, which is the whole risk.
 *
 * WHAT IS NOT ASSERTED HERE
 * -------------------------
 * The writer's own contract — capability denial, malformed documents, unsupported schema
 * versions, nested transactions — belongs to `G1f1LocationDnaPersistenceServiceTest` and is
 * unchanged by this increment. Repeating it would create a second source of truth.
 */
class G1f2TenantAgentAuctionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /** The two roles the preserved gate admits to the canonical writer. */
    private const UNGATED = ['buyer', 'tenant'];

    /** The two roles the preserved gate excludes. */
    private const GATED = ['seller', 'landlord'];

    /** Values the component cannot process and must reject rather than guess at. */
    private const UNSUPPORTED = ['' => 'missing', 'nonsense' => 'invalid'];

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
     * Drive the real save path.
     *
     * `$payload === null` means the bridged property is never assigned at all — the
     * unmounted-editor case, which is not the same as assigning an empty string.
     */
    private function save(
        string $userType,
        $auction,
        mixed $payload,
        array $props = []
    ): HireTenantCreate {
        $component            = new HireTenantCreate();
        $component->user_type = $userType;

        foreach (array_merge(
            ['cities' => [], 'counties' => [], 'state' => '', 'zipCodes' => []],
            $props
        ) as $k => $v) {
            $component->{$k} = $v;
        }

        if ($payload !== null) {
            $component->location_dna_preferences_json = $payload;
        }

        $method = new ReflectionMethod(HireTenantCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        return $component;
    }

    /** Run the real save path and return whatever it raised, or null. */
    private function attempt(string $userType, $auction, mixed $payload, array $props = []): ?Throwable
    {
        try {
            $this->save($userType, $auction, $payload, $props);
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

    /** Every executed statement, so writes can be counted rather than inferred. */
    private function capture(callable $fn): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $fn();
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
        }

        return $queries;
    }

    /**
     * How many INSERT/UPDATE statements carried this exact string in their bindings.
     *
     * WHY MATCHING IS BY BINDING AND NOT BY META KEY ALONE.
     * ----------------------------------------------------
     * `saveMeta()` goes through `updateOrCreate`, which binds the meta KEY only when it
     * INSERTs a new row. Updating an existing row binds the meta VALUE and the row id, and
     * the key never appears. A key-only counter therefore reports zero writes for every
     * update — it would pass whether or not the write happened, which is exactly the kind of
     * vacuous assertion this suite exists to avoid. (Found by non-vacuity probe 1: moving the
     * `user_type` validation back below the mirror writes did not fail a key-only count.)
     *
     * Callers pass whichever token is decisive for the case: the meta key when the row is new,
     * the written value when it is not.
     */
    private function bindingWrites(array $queries, string $needle): int
    {
        $writes = 0;

        foreach ($queries as $q) {
            $sql = strtolower((string) ($q['query'] ?? ''));

            if (! str_contains($sql, 'insert into') && ! str_contains($sql, 'update ')) {
                continue;
            }

            foreach ((array) ($q['bindings'] ?? []) as $binding) {
                if ($binding === $needle) {
                    $writes++;
                    break;
                }
            }
        }

        return $writes;
    }

    /** How many INSERT/UPDATE statements had a binding CONTAINING this substring. */
    private function bindingWritesContaining(array $queries, string $needle): int
    {
        $writes = 0;

        foreach ($queries as $q) {
            $sql = strtolower((string) ($q['query'] ?? ''));

            if (! str_contains($sql, 'insert into') && ! str_contains($sql, 'update ')) {
                continue;
            }

            foreach ((array) ($q['bindings'] ?? []) as $binding) {
                if (is_string($binding) && str_contains($binding, $needle)) {
                    $writes++;
                    break;
                }
            }
        }

        return $writes;
    }

    /** Every INSERT/UPDATE statement issued against this record's meta table. */
    private function metaWrites(array $queries): array
    {
        return array_values(array_filter($queries, function ($q) {
            $sql = strtolower((string) ($q['query'] ?? ''));

            return str_contains($sql, 'tenant_agent_auction_metas')
                && (str_contains($sql, 'insert into') || str_contains($sql, 'update '));
        }));
    }

    private function decodeCanonical(TenantAgentAuction $auction): ?array
    {
        $raw = $auction->info('location_dna_preferences');

        return is_string($raw) ? json_decode($raw, true) : null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE GATE · preserved exactly — D-G1F-3, option 3-C
    // ═════════════════════════════════════════════════════════════════════════

    /** `buyer` and `tenant` write the canonical document AND the managed mirrors. */
    public function test_ungated_user_types_write_canonical_state_and_managed_mirrors(): void
    {
        foreach (self::UNGATED as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $this->save($userType, $auction, json_encode([
                'cities'   => ['Tampa'],
                'counties' => ['Hillsborough'],
                'state'    => 'FL',
            ]));

            $stored  = $this->reread($auction);
            $decoded = $this->decodeCanonical($stored);

            $this->assertSame(['Tampa'], $decoded['cities'] ?? null, "{$userType}: canonical cities");
            $this->assertSame('FL', $decoded['state'] ?? null, "{$userType}: canonical state");

            $this->assertSame('["Tampa"]', (string) $stored->info('cities'), "{$userType}: cities mirror");
            $this->assertSame('["Hillsborough"]', (string) $stored->info('counties'), "{$userType}: counties mirror");
            $this->assertSame('FL', (string) $stored->info('state'), "{$userType}: state mirror");
        }
    }

    /**
     * `seller` and `landlord` write NO canonical document — the gate holds.
     *
     * Asserted against a record that has never had one, so the absence is the gate's doing
     * and not a stale value being left alone.
     */
    public function test_gated_user_types_write_no_canonical_document(): void
    {
        foreach (self::GATED as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $error = $this->attempt($userType, $auction, json_encode(['cities' => ['Orlando']]), [
                'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
            ]);

            $this->assertNull($error, "{$userType}: the gated save path must complete");

            $stored = $this->reread($auction);

            $this->assertFalse(
                $stored->info('location_dna_preferences'),
                "{$userType}: no canonical document may be created. `info()` returns false for an "
                .'unwritten meta key. D-G1F-3 3-C keeps the gate; G1f-2 does not open it.'
            );

            // And the legacy, property-sourced mirrors still land — this branch's only writes.
            $this->assertSame('["Tampa"]', (string) $stored->info('cities'), "{$userType}: cities mirror");
            $this->assertSame('FL', (string) $stored->info('state'), "{$userType}: state mirror");
        }
    }

    /** A gated role does not create a canonical document out of existing mirror values. */
    public function test_a_gated_role_never_promotes_mirrors_into_a_canonical_document(): void
    {
        foreach (self::GATED as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner, [
                'cities'   => json_encode(['Sarasota']),
                'counties' => json_encode(['Sarasota County']),
                'state'    => 'FL',
            ]);

            $this->save($userType, $auction, null, ['cities' => ['Naples'], 'state' => 'FL']);

            $this->assertFalse(
                $this->reread($auction)->info('location_dna_preferences'),
                "{$userType}: legacy mirror values must not be read back and written out as an "
                .'authored canonical document. That is the promotion §10.3 forbids.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE PARTIAL-WRITE DEFECT · closed by ordering
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * DATABASE PROOF · an unsupported `user_type` issues ZERO Location DNA writes.
     *
     * Counted from the query log rather than inferred from the stored values, because a
     * value can be unchanged either because nothing was written or because the same value
     * was rewritten. Only the statement log distinguishes them, and "nothing was written"
     * is the property the repair actually claims.
     *
     * Each case is measured twice, because neither measurement alone is decisive:
     *
     *  - on a record with NO Location DNA meta, a write would be an INSERT, so the meta KEY
     *    appears in the bindings;
     *  - on a record that already has it, a write would be an UPDATE, where only the new
     *    VALUE appears.
     *
     * Probe 1 — moving the validation back below the mirror writes — must fail this test. It
     * did not when only the first measurement existed, which is why both are here.
     */
    public function test_an_unsupported_user_type_issues_zero_location_dna_writes(): void
    {
        foreach (self::UNSUPPORTED as $userType => $label) {
            // ── Case A · fresh record. A write would INSERT, binding the meta key. ──────
            $auction = $this->record($this->owner());

            $errorA  = null;
            $queries = $this->capture(function () use ($userType, $auction, &$errorA) {
                $errorA = $this->attempt((string) $userType, $auction, json_encode([
                    'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
                ]), ['cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL', 'zipCodes' => ['33701']]);
            });

            $this->assertNotNull($errorA, "{$label}: the save path must reject the user_type");

            foreach (['location_dna_preferences', 'cities', 'counties', 'state', 'zipCodes'] as $key) {
                $this->assertSame(
                    0,
                    $this->bindingWrites($queries, $key),
                    "{$label}: `{$key}` must not be inserted. Before G1f-2 the mirrors were written "
                    .'here and the save then failed further on, with no transaction — the partial '
                    .'write this ordering removes.'
                );
            }

            // ── Case B · populated record. A write would UPDATE, binding the new value. ──
            $existing = $this->record($this->owner(), [
                'location_dna_preferences' => '{"cities":["Sarasota"]}',
                'cities'                   => json_encode(['Sarasota']),
                'counties'                 => json_encode(['Sarasota County']),
                'state'                    => 'FL',
                'zipCodes'                 => json_encode(['34236']),
            ]);

            $errorB   = null;
            $queriesB = $this->capture(function () use ($userType, $existing, &$errorB) {
                $errorB = $this->attempt((string) $userType, $existing, json_encode([
                    'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'GA',
                ]), ['cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'GA', 'zipCodes' => ['33701']]);
            });

            $this->assertNotNull($errorB, "{$label}: the save path must reject the user_type");

            foreach ([
                json_encode(['Tampa']),
                json_encode(['Hillsborough']),
                'GA',
                json_encode(['33701']),
            ] as $newValue) {
                $this->assertSame(
                    0,
                    $this->bindingWrites($queriesB, $newValue),
                    "{$label}: the new value `{$newValue}` must never be bound to a write. An UPDATE "
                    .'does not carry the meta key, so this is the measurement that catches an '
                    .'overwrite of an existing mirror.'
                );
            }

            $stored = $this->reread($existing);

            $this->assertSame('{"cities":["Sarasota"]}', (string) $stored->info('location_dna_preferences'));
            $this->assertSame(json_encode(['Sarasota']), (string) $stored->info('cities'));
            $this->assertSame(json_encode(['Sarasota County']), (string) $stored->info('counties'));
            $this->assertSame('FL', (string) $stored->info('state'));
            $this->assertSame(json_encode(['34236']), (string) $stored->info('zipCodes'));
        }
    }

    /** The rejection names the offending value and does not coerce it into a role. */
    public function test_an_unsupported_user_type_is_rejected_and_never_coerced(): void
    {
        foreach (self::UNSUPPORTED as $userType => $label) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $error = $this->attempt((string) $userType, $auction, json_encode(['cities' => ['Tampa']]));

            $this->assertInstanceOf(
                \InvalidArgumentException::class,
                $error,
                "{$label}: an unsupported role is a precondition failure, not a silent skip."
            );
            $this->assertStringContainsString(
                'buyer, tenant, seller, landlord',
                $error->getMessage(),
                "{$label}: the message must name the supported set."
            );

            $this->assertFalse(
                $this->reread($auction)->info('location_dna_preferences'),
                "{$label}: no canonical document may be created for it under any assumed role."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE MIGRATION · one writer, one write per key, canonical first
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The persisted mirrors equal `LegacyMirrorProjection`'s output exactly.
     *
     * Ties the workflow to the pure component: if the two could differ, the mirrors would
     * have a second, undocumented source — which is exactly what the removed double-write
     * was.
     */
    public function test_persisted_mirrors_equal_the_projection_output(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $this->save('tenant', $auction, json_encode([
            'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
        ]));

        $stored   = $this->reread($auction);
        $document = (new LocationDnaHydrator())->hydrate($stored->info('location_dna_preferences'))->documentOrFail();
        $expected = (new LegacyMirrorProjection())->project($document);

        foreach ($expected as $key => $value) {
            $this->assertSame($value, (string) $stored->info($key), "mirror `{$key}` must equal the projection");
        }

        $this->assertSame(['cities', 'counties', 'state'], array_keys($expected));
    }

    /**
     * Exactly ONE write per managed mirror key, and one canonical write — the double-write
     * is gone.
     *
     * `G1fHireDoubleWriteCharacterisationTest` proved this component issued two writes per
     * key, the second winning by statement order alone. This is the inverse measurement.
     *
     * COUNTED BY VALUE, ON A RECORD THAT ALREADY HAS THE KEYS. Both candidate writes would
     * be UPDATEs, which bind the value and not the key, so each source is counted separately:
     * the property value must be written zero times and the derived value exactly once. A
     * key-based count on a fresh record cannot see the second write at all — non-vacuity
     * probe 3 (restoring the duplicate writes) passed such a count.
     */
    public function test_one_canonical_write_and_one_write_per_managed_mirror(): void
    {
        foreach (self::UNGATED as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner, [
                'location_dna_preferences' => json_encode(['cities' => ['Sarasota']]),
                'cities'                   => json_encode(['Sarasota']),
                'counties'                 => json_encode(['Sarasota County']),
                'state'                    => 'FL',
            ]);

            $queries = $this->capture(function () use ($userType, $auction) {
                $this->save($userType, $auction, json_encode([
                    'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'GA',
                ]), ['cities' => ['Naples'], 'counties' => ['Collier'], 'state' => 'TX']);
            });

            foreach ([
                'cities'   => [json_encode(['Naples']),  json_encode(['Tampa'])],
                'counties' => [json_encode(['Collier']), json_encode(['Hillsborough'])],
                'state'    => ['TX', 'GA'],
            ] as $key => [$propertyValue, $derivedValue]) {
                $this->assertSame(
                    0,
                    $this->bindingWrites($queries, $propertyValue),
                    "{$userType} · `{$key}`: the component-property value must not be written at "
                    .'all. It was the first half of the double-write.'
                );
                $this->assertSame(
                    1,
                    $this->bindingWrites($queries, $derivedValue),
                    "{$userType} · `{$key}`: the derived value must be written exactly once. Two "
                    .'would mean a duplicate write has returned.'
                );
            }

            $this->assertSame(
                1,
                $this->bindingWritesContaining($queries, '"schema_version":2'),
                "{$userType}: exactly one canonical write per save. Only the writer stamps the "
                .'schema version, so this counts canonical writes without depending on key binding.'
            );
        }
    }

    /**
     * The component-property values do not reach storage on the migrated path.
     *
     * The props and the payload deliberately disagree. Before the migration both values were
     * written and the blob-derived one won by ordering; now only the derived one is issued at
     * all, so a future reordering cannot resurrect the property value.
     */
    public function test_component_property_values_never_reach_storage_on_the_migrated_path(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $queries = $this->capture(function () use ($auction) {
            $this->save('tenant', $auction, json_encode(['cities' => ['Orlando'], 'state' => 'GA']), [
                'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
            ]);
        });

        foreach ($queries as $q) {
            foreach ((array) ($q['bindings'] ?? []) as $binding) {
                if (is_string($binding)) {
                    $this->assertStringNotContainsString(
                        'Tampa',
                        $binding,
                        'the component-property city must never be written on the migrated path'
                    );
                }
            }
        }

        $stored = $this->reread($auction);

        $this->assertSame('["Orlando"]', (string) $stored->info('cities'));
        $this->assertSame('GA', (string) $stored->info('state'));
    }

    /** Canonical state is persisted before the mirrors that derive from it. */
    public function test_canonical_state_is_written_before_the_mirrors(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $queries = $this->capture(function () use ($auction) {
            $this->save('tenant', $auction, json_encode(['cities' => ['Tampa']]));
        });

        $canonicalAt = null;
        $mirrorAt    = null;

        foreach ($queries as $i => $q) {
            foreach ((array) ($q['bindings'] ?? []) as $binding) {
                if ($binding === 'location_dna_preferences' && $canonicalAt === null) {
                    $canonicalAt = $i;
                }
                if ($binding === 'cities' && $mirrorAt === null) {
                    $mirrorAt = $i;
                }
            }
        }

        $this->assertNotNull($canonicalAt, 'the canonical key must be written');
        $this->assertNotNull($mirrorAt, 'the cities mirror must be written');
        $this->assertLessThan($mirrorAt, $canonicalAt, 'canonical first, mirrors second');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CLEAR · ABSENCE · NO-OP
    // ═════════════════════════════════════════════════════════════════════════

    /** D-G1-4 4-A · an explicit clear now propagates to every managed mirror. */
    public function test_an_explicit_clear_propagates_to_the_managed_mirrors(): void
    {
        foreach (self::UNGATED as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner, [
                'location_dna_preferences' => json_encode([
                    'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
                ]),
                'cities'   => '["Tampa"]',
                'counties' => '["Hillsborough"]',
                'state'    => 'FL',
            ]);

            $this->save($userType, $auction, json_encode([
                'cities' => [], 'counties' => [], 'state' => '',
            ]));

            $stored = $this->reread($auction);

            $this->assertSame('[]', (string) $stored->info('cities'), "{$userType}: cleared cities");
            $this->assertSame(
                '[]',
                (string) $stored->info('counties'),
                "{$userType}: a cleared counties no longer keeps a stale value"
            );
            $this->assertSame(
                '',
                (string) $stored->info('state'),
                "{$userType}: a cleared state no longer keeps a stale value"
            );
        }
    }

    /**
     * BINDING · a save with no Location DNA payload preserves everything.
     *
     * The unmounted-editor case. Before the migration this overwrote the canonical blob with
     * an empty string and destroyed the `cities` mirror.
     */
    public function test_an_absent_payload_preserves_canonical_state_and_mirrors(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => json_encode(['cities' => ['Tampa'], 'schema_version' => 2]),
            'cities'                   => '["Tampa"]',
            'counties'                 => '["Hillsborough"]',
            'state'                    => 'FL',
        ]);

        // The bridged property is never assigned — the editor never mounted.
        $this->save('tenant', $auction, null);

        $stored = $this->reread($auction);

        $this->assertSame(
            json_encode(['cities' => ['Tampa'], 'schema_version' => 2]),
            (string) $stored->info('location_dna_preferences'),
            'the canonical document must be untouched, byte for byte'
        );
        $this->assertSame('["Tampa"]', (string) $stored->info('cities'), 'and the mirrors with it');
        $this->assertSame('["Hillsborough"]', (string) $stored->info('counties'));
        $this->assertSame('FL', (string) $stored->info('state'));
    }

    /**
     * BINDING STOP CONDITION · a no-op save on a legacy-only record creates no canonical
     * document and does not destroy the legacy mirror.
     *
     * The §10.2 constraint at the workflow level. A record whose location lives only in the
     * discrete mirrors must survive a save that states nothing about location.
     */
    public function test_a_no_op_save_on_a_legacy_only_record_creates_no_document(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, ['cities' => json_encode(['Clearwater'])]);

        $queries = $this->capture(function () use ($auction) {
            $this->save('tenant', $auction, null);
        });

        $stored = $this->reread($auction);

        $this->assertFalse(
            $stored->info('location_dna_preferences'),
            'no canonical document may be conjured for a legacy-only record'
        );
        $this->assertSame(
            json_encode(['Clearwater']),
            (string) $stored->info('cities'),
            'and the legacy mirror must survive intact. This is the non-vacuous half: the '
            .'pre-migration path wrote the empty component property over it.'
        );
        $this->assertSame(
            0,
            $this->bindingWrites($queries, 'location_dna_preferences'),
            'no canonical INSERT may be issued for a record that has no canonical document'
        );
    }

    /** A semantic no-op writes nothing, even though the payload is present. */
    public function test_a_semantic_no_op_rewrites_nothing(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);
        $payload = json_encode(['cities' => ['Tampa'], 'state' => 'FL']);

        // First save canonicalises the record.
        $this->save('tenant', $auction, $payload);
        $canonical = (string) $this->reread($auction)->info('location_dna_preferences');

        // Second save states the same meaning.
        $queries = $this->capture(function () use ($auction, $payload) {
            $this->save('tenant', $this->reread($auction), $payload);
        });

        // Matched on the stored VALUE, not the meta key: the second save would be an UPDATE,
        // and an UPDATE binds only the value. Matching the key here would pass either way.
        $this->assertSame(
            0,
            $this->bindingWrites($queries, $canonical),
            'the revision token must suppress a write when canonical meaning is unchanged'
        );
        $this->assertSame(
            0,
            $this->bindingWrites($queries, '["Tampa"]'),
            'and no managed mirror write either'
        );
        $this->assertSame(
            $canonical,
            (string) $this->reread($auction)->info('location_dna_preferences'),
            'the stored bytes must be identical after a semantic no-op'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ZIPCODES · unchanged and unmanaged — D-G1F-4 (a)
    // ═════════════════════════════════════════════════════════════════════════

    /** `zipCodes` is still written from the component property, for every supported role. */
    public function test_zipcodes_is_still_written_from_the_component_property_for_every_role(): void
    {
        foreach ([...self::UNGATED, ...self::GATED] as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $this->save($userType, $auction, json_encode(['zip_codes' => ['90210']]), [
                'zipCodes' => ['33701', '33702'],
            ]);

            $this->assertSame(
                json_encode(['33701', '33702']),
                (string) $this->reread($auction)->info('zipCodes'),
                "{$userType}: the zipCodes mirror must still hold the COMPONENT PROPERTY value, "
                .'never the blob dimension. D-G1F-4 (a) leaves it unmanaged.'
            );
        }
    }

    /** `zipCodes` is not in the managed mirror set and the projection never emits it. */
    public function test_zipcodes_remains_outside_the_legacy_mirror_projection(): void
    {
        $this->assertSame(
            ['cities', 'counties', 'state'],
            LegacyMirrorProjection::MANAGED_KEYS,
            'the managed set must not have grown; adding zipCodes is the §17.4 checkpoint, not a refactor'
        );

        $document = (new LocationDnaHydrator())
            ->hydrate(json_encode(['zip_codes' => ['33701'], 'cities' => ['Tampa']]))
            ->documentOrFail();

        $this->assertArrayNotHasKey(
            'zipCodes',
            (new LegacyMirrorProjection())->project($document),
            'the projection must never emit zipCodes, even when the document carries zip_codes'
        );
    }

    /**
     * Clearing canonical `zip_codes` does not clear the mirror — the pre-existing divergence
     * is preserved, not repaired.
     *
     * `G1fZipCodesMirrorCharacterisationTest` pinned this before the migration. Asserting it
     * again here states the intent: G1f-2 declined to fix it, rather than overlooking it.
     */
    public function test_a_canonical_zip_clear_still_leaves_the_mirror_alone(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, ['zipCodes' => json_encode(['33701'])]);

        $this->save('tenant', $auction, json_encode(['zip_codes' => []]), ['zipCodes' => ['33701']]);

        $stored = $this->reread($auction);

        $this->assertSame(
            [],
            $this->decodeCanonical($stored)['zip_codes'] ?? null,
            'the canonical clear must be recorded'
        );
        $this->assertSame(
            json_encode(['33701']),
            (string) $stored->info('zipCodes'),
            'and the mirror must be untouched by it — unchanged behaviour, recorded not hidden'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SCHEMA VERSION · F-G1F-10, and where it does NOT apply
    // ═════════════════════════════════════════════════════════════════════════

    /** The first semantic write through the canonical writer stamps `schema_version: 2`. */
    public function test_the_first_semantic_write_canonicalises_to_schema_version_2(): void
    {
        foreach (self::UNGATED as $userType) {
            $owner   = $this->owner();
            $auction = $this->record($owner);

            $this->save($userType, $auction, '{"cities":["Orlando"],"state":"GA"}');

            $decoded = $this->decodeCanonical($this->reread($auction));

            $this->assertSame(2, $decoded['schema_version'] ?? null, "{$userType}: §5.5 lazy upgrade");
            $this->assertSame(['Orlando'], $decoded['cities'] ?? null, "{$userType}: meaning preserved");
            $this->assertSame('GA', $decoded['state'] ?? null, "{$userType}: meaning preserved");
        }
    }

    /** A no-command save does NOT canonicalise a legacy blob. */
    public function test_a_no_command_save_does_not_canonicalise_a_legacy_document(): void
    {
        $legacy  = '{"cities":["Tampa"],"state":"FL"}';
        $owner   = $this->owner();
        $auction = $this->record($owner, ['location_dna_preferences' => $legacy]);

        $this->save('tenant', $auction, null);

        $this->assertSame(
            $legacy,
            (string) $this->reread($auction)->info('location_dna_preferences'),
            'a save that states nothing must not rewrite the byte representation — the one-way '
            .'S1→S2 door opens on a SEMANTIC change only (§27.7)'
        );
    }

    /** The gated path does not canonicalise either — no write means no rewrite. */
    public function test_the_gated_path_does_not_canonicalise_an_existing_document(): void
    {
        foreach (self::GATED as $userType) {
            $legacy  = '{"cities":["Tampa"],"state":"FL"}';
            $owner   = $this->owner();
            $auction = $this->record($owner, ['location_dna_preferences' => $legacy]);

            $this->save($userType, $auction, json_encode(['cities' => ['Orlando']]));

            $this->assertSame(
                $legacy,
                (string) $this->reread($auction)->info('location_dna_preferences'),
                "{$userType}: the gate excludes this record from the writer, so its document keeps "
                .'both its meaning and its bytes.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ATOMICITY · canonical and managed mirrors succeed or fail together
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * An induced managed-mirror failure rolls back the canonical write with it.
     *
     * The record is passed as a proxy that forwards everything to the real model but raises
     * on the `state` mirror — the LAST managed key the projection emits, so `cities` and
     * `counties` have already been written inside the transaction when it fires. All three,
     * plus the canonical document, must be gone afterwards.
     *
     * This is the failure mode the pre-migration path had no answer to: its blob and mirror
     * writes were separated by hundreds of statements with no transaction at all.
     */
    public function test_a_failing_managed_mirror_write_rolls_back_the_canonical_write(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $proxy = new class($auction) {
            public function __construct(private $inner)
            {
            }

            public function saveMeta($key, $value)
            {
                if ($key === 'state') {
                    throw new RuntimeException('induced managed-mirror failure');
                }

                return $this->inner->saveMeta($key, $value);
            }

            public function __call($name, $arguments)
            {
                return $this->inner->{$name}(...$arguments);
            }

            public function __get($name)
            {
                return $this->inner->{$name};
            }
        };

        $error = $this->attempt('tenant', $proxy, json_encode([
            'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
        ]));

        $this->assertInstanceOf(RuntimeException::class, $error, 'the induced failure must propagate');

        $stored = $this->reread($auction);

        $this->assertFalse(
            $stored->info('location_dna_preferences'),
            'the canonical write must have rolled back with the failing mirror'
        );
        $this->assertFalse(
            $stored->info('cities'),
            'the cities mirror, written earlier INSIDE the same transaction, must roll back too'
        );
        $this->assertFalse(
            $stored->info('counties'),
            'and the counties mirror with it — the batch applies wholly or not at all'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UNRELATED BEHAVIOUR · untouched
    // ═════════════════════════════════════════════════════════════════════════

    /** Unrelated metadata still persists on the migrated path. */
    public function test_unrelated_metadata_still_persists(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $component                = new HireTenantCreate();
        $component->user_type     = 'tenant';
        $component->cities        = [];
        $component->counties      = [];
        $component->state         = '';
        $component->zipCodes      = [];
        $component->listing_date  = '2026-08-03';
        $component->property_type = 'Residential Property';
        $component->restrictions  = 'None';
        $component->location_dna_preferences_json = json_encode(['cities' => ['Tampa']]);

        $method = new ReflectionMethod(HireTenantCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        $stored = $this->reread($auction);

        $this->assertSame('tenant', (string) $stored->info('user_type'));
        $this->assertSame('2026-08-03', (string) $stored->info('listing_date'));
        $this->assertSame('Residential Property', (string) $stored->info('property_type'));
        $this->assertSame('None', (string) $stored->info('restrictions'));
        $this->assertSame('hire_agent', (string) $stored->info('workflow_type'));
    }

    /**
     * The load side is unchanged — the trait still hydrates this component.
     *
     * G1f-2 migrated the WRITE path only. `loadSearchAreas()` and the discrete host props are
     * deliberately untouched, so the map partial and the prefill behave exactly as before.
     */
    public function test_the_load_side_is_unchanged(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => json_encode(['cities' => ['Tampa'], 'state' => 'FL']),
        ]);

        $component = new HireTenantCreate();

        $load = new ReflectionMethod(HireTenantCreate::class, 'loadSearchAreas');
        $load->setAccessible(true);
        $load->invoke($component, $this->reread($auction));

        $this->assertSame(['Tampa'], $component->existingLocationDna['cities'] ?? null);
        $this->assertSame('FL', $component->existingLocationDna['state'] ?? null);
        $this->assertSame(
            json_encode(['cities' => ['Tampa'], 'state' => 'FL']),
            $component->location_dna_preferences_json,
            'the bridged property still carries the stored bytes verbatim'
        );
    }
}
