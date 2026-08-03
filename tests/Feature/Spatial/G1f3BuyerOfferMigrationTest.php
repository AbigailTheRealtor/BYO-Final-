<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
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
 * G1f-3 — both Buyer Offer writers migrated to the canonical writer.
 *
 * WHY BOTH IN ONE SUITE, AND BOTH IN ONE COMMIT
 * ---------------------------------------------
 * The two components are one implementation copied twice: `G1a`'s equivalence finding, the
 * shared characterisation suite and the B4 tripwire all treat them as a pair. Migrating one
 * would have left an ambiguous half-state in every one of those places. Every behavioural
 * test here therefore runs BOTH classes and reports which one failed, so pairing costs no
 * attribution.
 *
 * WHAT THIS INCREMENT REPAIRS
 * ---------------------------
 * Three characterised defects, at once, on two components:
 *
 *  1. **The three-way clear split** (F-G1-4) — `cities` honoured a clear while `counties`
 *     and `state` kept stale values.
 *  2. **Unmounted-editor destruction** (F-G1-7) — an empty payload overwrote the
 *     authoritative blob with an empty string and emptied the `cities` mirror.
 *  3. **The edit flow's non-atomic window** (F-G1F-7) — 400 lines and 315 `saveMeta` calls
 *     between the mirror writes and the canonical write, with no transaction anywhere.
 *
 * WHAT IT DELIBERATELY DOES NOT TOUCH
 * -----------------------------------
 * The load paths, the `hydrateDiscreteLocationFromBlob()` method definitions, and the
 * PRE-VALIDATION call to that method in `store()` / `update()` — which is a validation
 * concern that merely shares a name with the removed write concern (F-G1F-14). Several
 * tests below exist only to pin that distinction.
 */
class G1f3BuyerOfferMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /** Both migrated components, exercised identically. */
    private function flows(): array
    {
        return [
            'Buyer Offer · create' => BuyerOfferListing::class,
            'Buyer Offer · edit'   => BuyerOfferListingEdit::class,
        ];
    }

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function record(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'address'     => '',
            'title'       => 'G1f-3 migration',
            'is_draft'    => true,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $rows = [];
        foreach (array_merge([
            'user_type'                    => 'buyer',
            'workflow_type'                => 'offer_listing',
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

    private function reread($auction): BuyerAgentAuction
    {
        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    /** Drive the real save path. `$payload === null` leaves the bridged property unassigned. */
    private function save(string $class, $auction, mixed $payload, array $props = []): object
    {
        $component = new $class();

        foreach (array_merge(['cities' => [], 'counties' => [], 'state' => ''], $props) as $k => $v) {
            $component->{$k} = $v;
        }

        if ($payload !== null) {
            $component->location_dna_preferences_json = $payload;
        }

        $method = new ReflectionMethod($class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        return $component;
    }

    private function attempt(string $class, $auction, mixed $payload, array $props = []): ?Throwable
    {
        try {
            $this->save($class, $auction, $payload, $props);
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

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
     * How many INSERT/UPDATE statements bound this exact string.
     *
     * Matching is by binding rather than by meta key because `updateOrCreate` binds the KEY
     * only when it INSERTs; an UPDATE binds the VALUE and the row id. A key-only counter
     * reports zero writes for every update and would pass whether or not the write happened —
     * the vacuity §28.9's probes 1 and 3 caught. Callers pass the key for a new row, the
     * written value for an existing one.
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

    /** As above, but matching a substring — used to count canonical writes by their version stamp. */
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

    private function decodeCanonical(BuyerAgentAuction $auction): ?array
    {
        $raw = $auction->info('location_dna_preferences');

        return is_string($raw) ? json_decode($raw, true) : null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE NEW WRITE PATH
    // ═════════════════════════════════════════════════════════════════════════

    /** Both flows write canonical state through the writer, with the managed mirrors. */
    public function test_both_flows_write_canonical_state_and_managed_mirrors(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $this->save($class, $auction, json_encode([
                'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
            ]));

            $stored  = $this->reread($auction);
            $decoded = $this->decodeCanonical($stored);

            $this->assertSame(['Tampa'], $decoded['cities'] ?? null, "{$label}: canonical cities");
            $this->assertSame('FL', $decoded['state'] ?? null, "{$label}: canonical state");

            $this->assertSame('["Tampa"]', (string) $stored->info('cities'), "{$label}: cities mirror");
            $this->assertSame('["Hillsborough"]', (string) $stored->info('counties'), "{$label}: counties mirror");
            $this->assertSame('FL', (string) $stored->info('state'), "{$label}: state mirror");
        }
    }

    /** Persisted mirrors equal `LegacyMirrorProjection`'s output exactly, on both flows. */
    public function test_persisted_mirrors_equal_the_projection_output(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $this->save($class, $auction, json_encode([
                'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
            ]));

            $stored   = $this->reread($auction);
            $document = (new LocationDnaHydrator())->hydrate($stored->info('location_dna_preferences'))->documentOrFail();
            $expected = (new LegacyMirrorProjection())->project($document);

            foreach ($expected as $key => $value) {
                $this->assertSame($value, (string) $stored->info($key), "{$label}: mirror `{$key}`");
            }

            $this->assertSame(['cities', 'counties', 'state'], array_keys($expected), $label);
        }
    }

    /**
     * One canonical write and one write per managed mirror — no duplicate inline write remains.
     *
     * Counted by VALUE on a pre-seeded record so both candidate sources are visible: the
     * component property must be written zero times and the derived value exactly once.
     */
    public function test_one_canonical_write_and_one_write_per_managed_mirror(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner(), [
                'location_dna_preferences' => json_encode(['cities' => ['Sarasota']]),
                'cities'                   => json_encode(['Sarasota']),
                'counties'                 => json_encode(['Sarasota County']),
                'state'                    => 'FL',
            ]);

            $queries = $this->capture(function () use ($class, $auction) {
                $this->save($class, $auction, json_encode([
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
                    "{$label} · `{$key}`: the component-property value must never be written"
                );
                $this->assertSame(
                    1,
                    $this->bindingWrites($queries, $derivedValue),
                    "{$label} · `{$key}`: the derived value must be written exactly once"
                );
            }

            $this->assertSame(
                1,
                $this->bindingWritesContaining($queries, '"schema_version":2'),
                "{$label}: exactly one canonical write per save"
            );

            // SECOND MEASUREMENT, and the one that actually catches a restored duplicate.
            //
            // Above, the payload states all three dimensions, so a reinstated property-sourced
            // write would first be overwritten by `hydrateDiscreteLocationFromBlob()` and then
            // write the SAME value the writer does — and Eloquent issues no UPDATE for an
            // unchanged attribute, so the duplicate collapses and the count stays 1. (Found by
            // non-vacuity probe 3.)
            //
            // With a payload that states only `cities`, the props survive unhydrated and a
            // duplicate write would bind values the writer never produces, because an absent
            // dimension gets no command and therefore no mirror write at all.
            $second  = $this->record($this->owner());
            $queries = $this->capture(function () use ($class, $second) {
                $this->save($class, $second, json_encode(['cities' => ['Tampa']]), [
                    'counties' => ['Collier'], 'state' => 'TX',
                ]);
            });

            $this->assertSame(
                0,
                $this->bindingWrites($queries, json_encode(['Collier'])),
                "{$label}: an unstated `counties` must not be written from the component property"
            );
            $this->assertSame(
                0,
                $this->bindingWrites($queries, 'TX'),
                "{$label}: an unstated `state` must not be written from the component property"
            );
            $this->assertSame(
                0,
                $this->bindingWrites($queries, 'counties'),
                "{$label}: and no `counties` row may be created for an unstated dimension"
            );
        }
    }

    /** Canonical state is persisted before the mirrors that derive from it. */
    public function test_canonical_state_is_written_before_the_mirrors(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $queries = $this->capture(function () use ($class, $auction) {
                $this->save($class, $auction, json_encode(['cities' => ['Tampa']]));
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

            $this->assertNotNull($canonicalAt, "{$label}: the canonical key must be written");
            $this->assertNotNull($mirrorAt, "{$label}: the cities mirror must be written");
            $this->assertLessThan($mirrorAt, $canonicalAt, "{$label}: canonical first, mirrors second");
        }
    }

    /** `state` stays a raw string — 4S-i is a no-op for this family and must remain one. */
    public function test_state_remains_a_raw_string(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $this->save($class, $auction, json_encode(['state' => 'FL']));

            $this->assertSame(
                'FL',
                (string) $this->reread($auction)->info('state'),
                "{$label}: the state mirror must be the raw string, never JSON-encoded"
            );
        }
    }

    /** No `zipCodes` and no plural `states` are introduced. */
    public function test_no_zipcodes_and_no_plural_states_are_introduced(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $this->save($class, $auction, json_encode([
                'cities' => ['Tampa'], 'zip_codes' => ['33701'], 'state' => 'FL',
            ]));

            $stored = $this->reread($auction);

            $this->assertFalse(
                $stored->info('zipCodes'),
                "{$label}: the Buyer family has never written zipCodes and must not start"
            );
            $this->assertFalse(
                $stored->info('states'),
                "{$label}: the plural states key is a legacy dead write and must not appear"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // COMMAND SEMANTICS · set · clear · absence · no-op
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * D-G1-4 4-A · an explicit clear now propagates to every managed mirror, on both flows.
     *
     * This is the repair of the three-way split, asserted against stale mirrors AND stale
     * component props so neither can resurrect the cleared value.
     */
    public function test_an_explicit_clear_propagates_to_every_managed_mirror(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner(), [
                'location_dna_preferences' => json_encode([
                    'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
                ]),
                'cities'   => '["Tampa"]',
                'counties' => '["Hillsborough"]',
                'state'    => 'Georgia',
            ]);

            $this->save($class, $auction, json_encode([
                'cities' => [], 'counties' => [], 'state' => '',
            ]), ['cities' => ['Naples'], 'counties' => ['Collier'], 'state' => 'Georgia']);

            $stored = $this->reread($auction);

            $this->assertSame('[]', (string) $stored->info('cities'), "{$label}: cleared cities");
            $this->assertSame('[]', (string) $stored->info('counties'), "{$label}: cleared counties");
            $this->assertSame('', (string) $stored->info('state'), "{$label}: cleared state");
        }
    }

    /**
     * A stale discrete mirror cannot resurrect a cleared canonical value.
     *
     * The inverse of the resurrection defect, stated at the storage layer: after a clear, a
     * subsequent no-command save must not bring the old value back from the mirror.
     */
    public function test_a_stale_mirror_cannot_resurrect_a_cleared_canonical_value(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner(), [
                'location_dna_preferences' => json_encode(['cities' => ['Tampa']]),
                'cities'                   => '["Tampa"]',
            ]);

            // The user clears cities.
            $this->save($class, $auction, json_encode(['cities' => []]));
            $this->assertSame('[]', (string) $this->reread($auction)->info('cities'), "{$label}: cleared");

            // A later save that states nothing must leave the clear standing.
            $this->save($class, $this->reread($auction), null, ['cities' => ['Tampa']]);

            $stored = $this->reread($auction);

            $this->assertSame(
                '[]',
                (string) $stored->info('cities'),
                "{$label}: the cleared value must not be resurrected by the component property"
            );
            $this->assertSame(
                [],
                $this->decodeCanonical($stored)['cities'] ?? null,
                "{$label}: and canonical state must still record the clear"
            );
        }
    }

    /** BINDING · an absent payload preserves canonical bytes and every mirror. */
    public function test_an_absent_payload_preserves_canonical_state_and_mirrors(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner(), [
                'location_dna_preferences' => json_encode(['cities' => ['Tampa'], 'schema_version' => 2]),
                'cities'                   => '["Tampa"]',
                'counties'                 => '["Hillsborough"]',
                'state'                    => 'FL',
            ]);

            $this->save($class, $auction, null);

            $stored = $this->reread($auction);

            $this->assertSame(
                json_encode(['cities' => ['Tampa'], 'schema_version' => 2]),
                (string) $stored->info('location_dna_preferences'),
                "{$label}: canonical document untouched, byte for byte"
            );
            $this->assertSame('["Tampa"]', (string) $stored->info('cities'), $label);
            $this->assertSame('["Hillsborough"]', (string) $stored->info('counties'), $label);
            $this->assertSame('FL', (string) $stored->info('state'), $label);
        }
    }

    /** BINDING · a no-op save on a legacy-only record creates no canonical document. */
    public function test_a_no_op_save_on_a_legacy_only_record_creates_no_document(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner(), ['cities' => json_encode(['Clearwater'])]);

            $queries = $this->capture(function () use ($class, $auction) {
                $this->save($class, $auction, null);
            });

            $stored = $this->reread($auction);

            $this->assertFalse(
                $stored->info('location_dna_preferences'),
                "{$label}: no canonical document may be conjured for a legacy-only record"
            );
            $this->assertSame(
                json_encode(['Clearwater']),
                (string) $stored->info('cities'),
                "{$label}: and the legacy mirror must survive intact"
            );
            $this->assertSame(0, $this->bindingWrites($queries, 'location_dna_preferences'), $label);
        }
    }

    /** No legacy mirror value is promoted into canonical state. */
    public function test_a_legacy_mirror_value_is_never_promoted_to_authored(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner(), [
                'cities'   => json_encode(['Clearwater']),
                'counties' => json_encode(['Pinellas']),
                'state'    => 'FL',
            ]);

            // A save that states ONE unrelated dimension must not drag the mirrors in with it.
            $this->save($class, $auction, json_encode(['state' => 'GA']));

            $decoded = $this->decodeCanonical($this->reread($auction));

            $this->assertSame('GA', $decoded['state'] ?? null, "{$label}: the stated dimension is written");
            $this->assertArrayNotHasKey(
                'cities',
                $decoded,
                "{$label}: the legacy cities mirror must NOT have been promoted into the document"
            );
            $this->assertArrayNotHasKey('counties', $decoded, "{$label}: nor counties");
        }
    }

    /** A semantic no-op issues no write at all. */
    public function test_a_semantic_no_op_rewrites_nothing(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());
            $payload = json_encode(['cities' => ['Tampa'], 'state' => 'FL']);

            $this->save($class, $auction, $payload);
            $canonical = (string) $this->reread($auction)->info('location_dna_preferences');

            $queries = $this->capture(function () use ($class, $auction, $payload) {
                $this->save($class, $this->reread($auction), $payload);
            });

            $this->assertSame(
                0,
                $this->bindingWrites($queries, $canonical),
                "{$label}: the revision token must suppress a write of unchanged meaning"
            );
            $this->assertSame(0, $this->bindingWrites($queries, '["Tampa"]'), "{$label}: no mirror write");
            $this->assertSame(
                $canonical,
                (string) $this->reread($auction)->info('location_dna_preferences'),
                "{$label}: bytes identical after a semantic no-op"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SCHEMA VERSION
    // ═════════════════════════════════════════════════════════════════════════

    /** The first semantic write stamps `schema_version: 2` on both flows. */
    public function test_the_first_semantic_write_canonicalises_to_schema_version_2(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $this->save($class, $auction, '{"cities":["Orlando"],"state":"GA"}');

            $decoded = $this->decodeCanonical($this->reread($auction));

            $this->assertSame(2, $decoded['schema_version'] ?? null, "{$label}: §5.5 lazy upgrade");
            $this->assertSame(['Orlando'], $decoded['cities'] ?? null, "{$label}: meaning preserved");
            $this->assertSame('GA', $decoded['state'] ?? null, "{$label}: meaning preserved");
        }
    }

    /** A no-command save does not canonicalise a legacy document. */
    public function test_a_no_command_save_does_not_canonicalise(): void
    {
        foreach ($this->flows() as $label => $class) {
            $legacy  = '{"cities":["Tampa"],"state":"FL"}';
            $auction = $this->record($this->owner(), ['location_dna_preferences' => $legacy]);

            $this->save($class, $auction, null);

            $this->assertSame(
                $legacy,
                (string) $this->reread($auction)->info('location_dna_preferences'),
                "{$label}: the one-way S1→S2 door opens on a SEMANTIC change only"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ATOMICITY · the edit flow's 400-line window, closed
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * An induced final-mirror failure rolls back the canonical write and the earlier mirrors.
     *
     * The record is passed as a proxy that forwards to the real model but raises on the
     * `state` mirror — the LAST managed key the projection emits, so `cities` and `counties`
     * have already been written inside the transaction when it fires.
     *
     * For the EDIT flow this is the direct proof that F-G1F-7 is closed: those writes used to
     * be 400 lines and 315 `saveMeta` calls apart with no transaction, so a failure in that
     * window committed mirrors that disagreed with the blob permanently.
     */
    public function test_a_failing_managed_mirror_write_rolls_back_the_canonical_write(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

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

            $error = $this->attempt($class, $proxy, json_encode([
                'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL',
            ]));

            $this->assertInstanceOf(RuntimeException::class, $error, "{$label}: failure must propagate");

            $stored = $this->reread($auction);

            $this->assertFalse(
                $stored->info('location_dna_preferences'),
                "{$label}: the canonical write must have rolled back with the failing mirror"
            );
            $this->assertFalse(
                $stored->info('cities'),
                "{$label}: the cities mirror, written earlier in the same transaction, rolls back too"
            );
            $this->assertFalse(
                $stored->info('counties'),
                "{$label}: and counties with it — no Location DNA partial state remains"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // F-G1F-14 · the two hydrate call sites, separated
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * STRUCTURAL · the write-side hydrate call is gone; the pre-validation one remains.
     *
     * Asserted by position rather than by count, because the count alone cannot say WHICH
     * call survived — and surviving the wrong one would silently break validation while
     * every behavioural mirror test still passed.
     */
    public function test_only_the_pre_validation_hydrate_call_remains(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php'     => 'store',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php' => 'update',
        ] as $file => $entryMethod) {
            $source = file_get_contents(base_path($file));

            $this->assertSame(
                1,
                substr_count($source, '$this->hydrateDiscreteLocationFromBlob();'),
                "{$file}: exactly ONE hydrate call must remain — the write-side one is removed"
            );

            // The survivor must be the one inside store()/update(), i.e. AFTER saveAllMetadata()
            // is defined and before the validate() call it feeds.
            $hydrateAt  = strpos($source, '$this->hydrateDiscreteLocationFromBlob();');
            $persistAt  = strpos($source, '$this->persistLocationDna($auction);');
            $validateAt = strpos($source, '$this->validate(', $hydrateAt);

            $this->assertNotFalse($hydrateAt, "{$file}: hydrate call missing");
            $this->assertNotFalse($persistAt, "{$file}: writer call missing");
            $this->assertNotFalse($validateAt, "{$file}: no validate() follows the hydrate call");
            $this->assertGreaterThan(
                $persistAt,
                $hydrateAt,
                "{$file}: the surviving hydrate must be the PRE-VALIDATION one in {$entryMethod}(), "
                .'which sits after saveAllMetadata() in the file — not the removed write-side call'
            );

            // And the method definition itself is untouched.
            $this->assertStringContainsString(
                'protected function hydrateDiscreteLocationFromBlob(): void',
                $source,
                "{$file}: the method definition must remain — the survivor still needs it"
            );
        }
    }

    /**
     * BEHAVIOURAL · the pre-validation hydrate still populates the props validation reads.
     *
     * Invoked directly, because the point is the method's effect on component state rather
     * than any storage outcome. If G1f-3 had removed the wrong call site, `$this->state` and
     * `$this->counties` would stay empty here and every `required` rule fed by the map would
     * fail at submit.
     */
    public function test_the_pre_validation_hydrate_still_populates_the_validated_props(): void
    {
        foreach ($this->flows() as $label => $class) {
            $component = new $class();
            $component->state    = '';
            $component->counties = [];
            $component->location_dna_preferences_json = json_encode([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL'],
            ]);

            $hydrate = new ReflectionMethod($class, 'hydrateDiscreteLocationFromBlob');
            $hydrate->setAccessible(true);
            $hydrate->invoke($component);

            $this->assertSame('Florida', $component->state, "{$label}: state must be hydrated for validation");
            $this->assertSame(
                ['Pinellas County, FL'],
                $component->counties,
                "{$label}: counties must be hydrated for validation"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UNRELATED BEHAVIOUR · untouched
    // ═════════════════════════════════════════════════════════════════════════

    /** Unrelated metadata still persists on both migrated flows. */
    public function test_unrelated_metadata_still_persists(): void
    {
        foreach ($this->flows() as $label => $class) {
            $auction = $this->record($this->owner());

            $component = new $class();
            $component->cities        = [];
            $component->counties      = [];
            $component->state         = '';
            $component->property_type = 'Residential Property';
            $component->auction_type  = 'Open Offer';
            $component->location_dna_preferences_json = json_encode(['cities' => ['Tampa']]);

            $method = new ReflectionMethod($class, 'saveAllMetadata');
            $method->setAccessible(true);
            $method->invoke($component, $auction);

            $stored = $this->reread($auction);

            $this->assertSame('offer_listing', (string) $stored->info('workflow_type'), $label);
            $this->assertSame('Residential Property', (string) $stored->info('property_type'), $label);
            $this->assertSame('Open Offer', (string) $stored->info('auction_type'), $label);
        }
    }

    /** The load side is unchanged — the legacy-`cities` merge still runs. */
    public function test_the_load_side_is_unchanged(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString(
                "if (empty(\$ldna['cities'] ?? [])) {",
                $source,
                "{$file}: the load-side legacy merge and its empty() guard are untouched by G1f-3"
            );
            $this->assertStringContainsString(
                "\$auction->info('location_dna_preferences')",
                $source,
                "{$file}: the load-side canonical read remains"
            );
        }
    }
}
