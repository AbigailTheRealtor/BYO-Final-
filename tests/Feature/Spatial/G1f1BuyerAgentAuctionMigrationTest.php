<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use App\Services\LocationDna\Contract\LocationDnaHydrator;
use App\Services\LocationDna\Persistence\LegacyMirrorProjection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1f-1 — `BuyerAgentAuction` migrated to the canonical writer.
 *
 * THE FIRST MIGRATION
 * -------------------
 * §19 recommended this workflow first and the characterisation confirmed it: it is the only one
 * that exercises the Hire double-write without also carrying the `user_type` gate, a `zipCodes`
 * mirror, a nested transaction, a non-invocable save path, or semantics that must not change.
 *
 * This suite drives the REAL `saveAllMetadata()` through its real entry point, against a real
 * record, and asserts what the migration changed and — more importantly — what it did not.
 *
 * WHAT CHANGED, BY APPROVED DECISION
 * ----------------------------------
 * - The component-property mirror writes are gone; one canonical write plus one projection write
 *   per managed key replaces them (D-G1F-2 2-A, and the removal of the F-G1F-3 double-write).
 * - A cleared `counties` / `state` now actually clears its mirror (D-G1-4 4-A).
 * - A save that states nothing writes nothing at all (D-G1-2 2-A, §10.2's binding constraint).
 *
 * WHAT DID NOT CHANGE
 * -------------------
 * The load side, the trait, `zipCodes` (this workflow never wrote it), the raw-string `state`
 * encoding, every unrelated metadata write, and all seven other workflows.
 */
class G1f1BuyerAgentAuctionMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function record(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'address'     => '',
            'title'       => 'G1f-1 migration',
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

    private function reread(BuyerAgentAuction $auction): BuyerAgentAuction
    {
        return BuyerAgentAuction::with('meta')->findOrFail($auction->id);
    }

    /** Drive the real save path with an optional bridged payload and component props. */
    private function save(BuyerAgentAuction $auction, mixed $payload, array $props = []): HireBuyerCreate
    {
        $component = new HireBuyerCreate();

        foreach (array_merge(['cities' => [], 'counties' => [], 'state' => ''], $props) as $k => $v) {
            $component->{$k} = $v;
        }

        if ($payload !== null) {
            $component->location_dna_preferences_json = $payload;
        }

        $method = new ReflectionMethod(HireBuyerCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        return $component;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE NEW WRITE PATH
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_real_save_path_writes_canonical_state_through_the_new_boundary(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $this->save($auction, json_encode(['cities' => ['Tampa'], 'state' => 'FL']));

        $stored  = $this->reread($auction);
        $decoded = json_decode((string) $stored->info('location_dna_preferences'), true);

        $this->assertSame(['Tampa'], $decoded['cities']);
        $this->assertSame('FL', $decoded['state']);
        $this->assertSame(
            2,
            $decoded['schema_version'],
            'the canonical writer stamps schema_version 2 — the lazy upgrade §5.5 defines'
        );
    }

    /**
     * The persisted mirrors equal the projection's output exactly.
     *
     * Ties the workflow to the pure component: if the projection and the persisted values could
     * differ, the mirrors would have a second, undocumented source.
     */
    public function test_persisted_mirrors_equal_the_projection_output(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);
        $payload = json_encode(['cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL']);

        $this->save($auction, $payload);

        $stored   = $this->reread($auction);
        $document = (new LocationDnaHydrator())->hydrate($stored->info('location_dna_preferences'))->documentOrFail();
        $expected = (new LegacyMirrorProjection())->project($document);

        foreach ($expected as $key => $value) {
            $this->assertSame($value, (string) $stored->info($key), "mirror `{$key}` must equal the projection");
        }

        $this->assertSame(['cities', 'counties', 'state'], array_keys($expected));
    }

    /**
     * Exactly ONE write per managed mirror key — the double-write is gone.
     *
     * Counted from the query log rather than inferred. Before the migration the same key was
     * written twice per save, which is what `G1fHireDoubleWriteCharacterisationTest` proved and
     * what this migration removes.
     */
    public function test_each_managed_mirror_key_is_written_exactly_once(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->save($auction, json_encode(['cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL']));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        foreach (['cities', 'counties', 'state'] as $key) {
            $writes = 0;

            foreach ($queries as $q) {
                $sql = strtolower((string) ($q['query'] ?? ''));

                if (! str_contains($sql, 'insert into') && ! str_contains($sql, 'update ')) {
                    continue;
                }

                foreach ((array) ($q['bindings'] ?? []) as $binding) {
                    if ($binding === $key) {
                        $writes++;
                        break;
                    }
                }
            }

            $this->assertSame(
                1,
                $writes,
                "mirror `{$key}` must be written exactly once per save; the pre-migration double-write "
                .'issued two.'
            );
        }
    }

    /** Canonical state is persisted before the mirrors that derive from it. */
    public function test_canonical_state_is_written_before_the_mirrors(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->save($auction, json_encode(['cities' => ['Tampa']]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

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
    public function test_an_explicit_clear_propagates_to_the_mirrors(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => json_encode(['cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => 'FL']),
            'cities'                   => '["Tampa"]',
            'counties'                 => '["Hillsborough"]',
            'state'                    => 'FL',
        ]);

        $this->save($auction, json_encode(['cities' => [], 'counties' => [], 'state' => '']));

        $stored = $this->reread($auction);

        $this->assertSame('[]', (string) $stored->info('cities'));
        $this->assertSame('[]', (string) $stored->info('counties'), 'a cleared counties no longer keeps a stale value');
        $this->assertSame('', (string) $stored->info('state'), 'a cleared state no longer keeps a stale value');
    }

    /**
     * BINDING · a save with no Location DNA payload preserves everything.
     *
     * The unmounted-editor case. Before the migration this destroyed the `cities` mirror and
     * overwrote the canonical blob with an empty string.
     */
    public function test_an_absent_payload_preserves_canonical_state_and_mirrors(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => json_encode(['cities' => ['Tampa'], 'schema_version' => 2]),
            'cities'                   => '["Tampa"]',
        ]);

        // No payload assigned at all — the editor never mounted.
        $this->save($auction, null);

        $stored = $this->reread($auction);

        $this->assertSame(
            json_encode(['cities' => ['Tampa'], 'schema_version' => 2]),
            (string) $stored->info('location_dna_preferences'),
            'the canonical document must be untouched, byte for byte'
        );
        $this->assertSame('["Tampa"]', (string) $stored->info('cities'), 'and the mirror with it');
    }

    /**
     * BINDING STOP CONDITION · a no-op save on a legacy-only record creates no blob.
     *
     * The §10.2 constraint at the workflow level, and the defect
     * `test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror` pins for the unmigrated
     * workflows. A record whose cities live only in the legacy mirror must come out untouched.
     */
    public function test_a_no_op_save_on_a_legacy_only_record_creates_no_blob_and_preserves_the_mirror(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, ['cities' => '["Clearwater"]']);

        $this->save($auction, null);

        $stored = $this->reread($auction);

        $this->assertFalse(
            $stored->info('location_dna_preferences'),
            'no canonical document may be created for a legacy-only record'
        );
        $this->assertSame(
            '["Clearwater"]',
            (string) $stored->info('cities'),
            'and the legacy mirror must survive intact'
        );
    }

    /**
     * BINDING · a legacy value recovered at load is NOT promoted to authored.
     *
     * `loadSearchAreas()` merges the legacy `cities` mirror into the prefill array. On the PHP
     * path the bridged payload stays unmerged, so the recovered value is never commanded and
     * never becomes owner-authored — §5.4 rule 5 and D-G1-6, satisfied structurally.
     */
    public function test_a_legacy_recovered_value_is_not_promoted_to_authored(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, ['cities' => '["Clearwater"]']);

        $this->actingAs($owner);
        $component = new HireBuyerCreate();
        $component->loadDraft($auction->id);

        $this->assertSame(
            ['Clearwater'],
            $component->existingLocationDna['cities'] ?? null,
            'precondition: the prefill recovered the legacy value'
        );

        $method = new ReflectionMethod(HireBuyerCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        $this->assertFalse(
            $this->reread($auction)->info('location_dna_preferences'),
            'the recovered value must not have been written into a canonical document'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UNCHANGED BEHAVIOUR
    // ═════════════════════════════════════════════════════════════════════════

    /** D-G1F-4 4S-i · `state` remains a raw string, so this workflow is encoding-neutral. */
    public function test_state_remains_a_raw_string(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $this->save($auction, json_encode(['state' => 'FL']));

        $this->assertSame('FL', (string) $this->reread($auction)->info('state'));
    }

    /** D-G1F-4 (a) · `zipCodes` stays unmanaged and unwritten for this workflow. */
    public function test_zipcodes_behaviour_is_unchanged_and_still_unwritten(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $this->save($auction, json_encode(['zip_codes' => ['33701'], 'cities' => ['Tampa']]));

        $stored = $this->reread($auction);

        $this->assertFalse($stored->info('zipCodes'), 'this workflow never wrote a zipCodes mirror and still does not');
        $this->assertSame(
            ['33701'],
            json_decode((string) $stored->info('location_dna_preferences'), true)['zip_codes'],
            'the canonical dimension is still persisted — only the MIRROR is unmanaged'
        );
    }

    /** §17.5 · the plural `states` key is never emitted by this workflow. */
    public function test_the_plural_states_key_is_never_written(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $this->save($auction, json_encode(['state' => 'FL']));

        $this->assertFalse($this->reread($auction)->info('states'));
    }

    /** Every unrelated metadata write still happens exactly as before. */
    public function test_all_unrelated_metadata_still_persists(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner);

        $component = new HireBuyerCreate();
        $component->cities        = [];
        $component->counties      = [];
        $component->state         = '';
        $component->listing_status = 'draft';
        $component->auction_type   = 'Bidding Period';
        $component->property_type  = 'Condo';
        $component->location_dna_preferences_json = json_encode(['cities' => ['Tampa']]);

        $method = new ReflectionMethod(HireBuyerCreate::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);

        $stored = $this->reread($auction);

        $this->assertSame('hire_agent', (string) $stored->info('workflow_type'), 'the workflow tag still writes');
        $this->assertSame('draft', (string) $stored->info('listing_status'));
        $this->assertSame('Bidding Period', (string) $stored->info('auction_type'));
        $this->assertSame('Condo', (string) $stored->info('property_type'));
    }

    /** The load side is untouched: legacy recovery still works exactly as characterised. */
    public function test_the_load_side_is_unchanged(): void
    {
        $owner   = $this->owner();
        $auction = $this->record($owner, [
            'location_dna_preferences' => json_encode(['cities' => []]),
            'cities'                   => '["Tampa","Miami"]',
        ]);

        $this->actingAs($owner);
        $component = new HireBuyerCreate();
        $component->loadDraft($auction->id);

        $this->assertSame(
            ['Tampa', 'Miami'],
            $component->existingLocationDna['cities'] ?? null,
            'the trait still resurrects on LOAD — G1f-1 changed only the write path'
        );
    }
}
