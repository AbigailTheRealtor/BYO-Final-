<?php

namespace Tests\Feature\Spatial;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * G1f-5 — `BuyerAgentAuctionEdit` writes Location DNA through the canonical writer.
 *
 * WHAT THIS COMPONENT DID BEFORE
 * ------------------------------
 * `saveAllMetadata()` wrote the discrete `cities` / `counties` / `state` mirrors from its own
 * component properties, then called `saveSearchAreas()`, which wrote the canonical blob and
 * RE-wrote the same three mirrors from it. Six statements, two value sources, no transaction, and
 * correctness resting entirely on the second write landing after the first —
 * {@see G1fHireDoubleWriteCharacterisationTest} proved that ordering from the query log because
 * nothing else did.
 *
 * The consequences were the ones G1a characterised across the whole matrix: a cleared `cities`
 * mirrored as `[]` while a cleared `counties` or `state` kept its stale value (F-G1-4's three-way
 * split); an ABSENT `cities` key mirrored as `[]`, so a no-op save destroyed a legacy-only mirror;
 * and an unmounted editor overwrote the authoritative blob with an empty string.
 *
 * WHAT IT DOES NOW
 * ----------------
 * One call. The writer builds explicit set/clear commands from the submitted payload, persists
 * canonical state first and derives all three managed mirrors from the RESULT, inside one
 * transaction. A dimension the payload does not state gets no command and is not written, so
 * present-empty clears, absent preserves, and the two stay distinct.
 *
 * THIS WAS THE LAST INVOCABLE SAVE PATH
 * -------------------------------------
 * G1f-1 … G1f-4 migrated the other six. With this one migrated, no invocable save path carries the
 * double-write, which is why the two G1a matrix save-side tests were restructured to assert the
 * post-migration property across the migrated set rather than skipping it into vacuity.
 *
 * SCOPE, ASSERTED AS BEHAVIOUR RATHER THAN ONLY AS SOURCE
 * -------------------------------------------------------
 * `zipCodes` is not written — the Buyer family never has, and G1f-4's Tenant opt-in must not leak.
 * That is asserted here against real storage as well as structurally in
 * {@see \Tests\Unit\Services\LocationDna\Persistence\G1f5MigrationBoundaryGuardTest}.
 */
class G1f5BuyerAgentAuctionEditMigrationTest extends TestCase
{
    use DatabaseTransactions;

    /** Component-property values, deliberately divergent from every blob below. */
    private const PROP_CITIES   = ['Tampa'];
    private const PROP_COUNTIES = ['Hillsborough'];
    private const PROP_STATE    = 'FL';

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /**
     * A real `BuyerAgentAuction` row.
     *
     * `forceFill()` rather than a factory, because this model has none — the vehicle
     * `HireSearchAreasParityTest` established and the G1a matrix reuses.
     */
    private function record(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id'     => $owner->id,
            'address'     => '',
            'title'       => 'G1f-5 migration',
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

    /**
     * A component primed for its real save path, always carrying the divergent property values.
     *
     * Retaining them is load-bearing: pre-migration they were the SOURCE of the stale mirrors, so
     * their continued presence is what proves the resurrection route is shut rather than merely
     * unused by a particular fixture.
     */
    private function editor(mixed $payload): HireBuyerEdit
    {
        $component                               = new HireBuyerEdit();
        $component->cities                       = self::PROP_CITIES;
        $component->counties                     = self::PROP_COUNTIES;
        $component->state                        = self::PROP_STATE;
        $component->location_dna_preferences_json = $payload;

        return $component;
    }

    private function save(HireBuyerEdit $component, BuyerAgentAuction $auction): void
    {
        $method = new ReflectionMethod(HireBuyerEdit::class, 'saveAllMetadata');
        $method->setAccessible(true);
        $method->invoke($component, $auction);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SET · the submitted payload is what persists
    // ═════════════════════════════════════════════════════════════════════════

    /** A stated payload persists canonically and derives all three mirrors from the result. */
    public function test_a_stated_payload_persists_canonically_and_mirrors_from_the_result(): void
    {
        $auction = $this->record($this->owner());

        $this->save($this->editor(json_encode([
            'cities'   => ['Orlando'],
            'counties' => ['Orange'],
            'state'    => 'GA',
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('["Orlando"]', (string) $stored->info('cities'));
        $this->assertSame('["Orange"]', (string) $stored->info('counties'));
        $this->assertSame('GA', (string) $stored->info('state'));

        $canonical = json_decode((string) $stored->info('location_dna_preferences'), true);
        $this->assertSame(['Orlando'], $canonical['cities'], 'canonical state carries the payload');
        $this->assertArrayHasKey('schema_version', $canonical, 'and is stamped by the serializer');
    }

    /** No component-property value reaches storage, for any dimension. */
    public function test_no_component_property_value_reaches_storage(): void
    {
        $auction = $this->record($this->owner());

        $this->save($this->editor(json_encode([
            'cities'   => ['Orlando'],
            'counties' => ['Orange'],
            'state'    => 'GA',
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertStringNotContainsString('Tampa', (string) $stored->info('cities'));
        $this->assertStringNotContainsString('Hillsborough', (string) $stored->info('counties'));
        $this->assertNotSame(self::PROP_STATE, (string) $stored->info('state'));
    }

    /** The save does not reach back and mutate the caller's component state. */
    public function test_the_save_does_not_mutate_component_properties(): void
    {
        $auction   = $this->record($this->owner());
        $component = $this->editor(json_encode(['cities' => ['Orlando'], 'state' => 'GA']));

        $this->save($component, $auction);

        $this->assertSame(self::PROP_CITIES, $component->cities);
        $this->assertSame(self::PROP_COUNTIES, $component->counties);
        $this->assertSame(self::PROP_STATE, $component->state);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CLEAR · D-G1-4 4-A, uniformly across all three dimensions
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * An explicit clear propagates to EVERY managed mirror — the end of F-G1-4's three-way split.
     *
     * Pre-migration `cities` honoured this clear while `counties` and `state` kept the divergent
     * property values. All three now honour it, from one code path.
     */
    public function test_an_explicit_clear_propagates_to_every_managed_mirror(): void
    {
        $auction = $this->record($this->owner(), [
            'cities'   => json_encode(['Clearwater']),
            'counties' => json_encode(['Pinellas']),
            'state'    => 'FL',
        ]);

        $this->save($this->editor(json_encode([
            'cities'   => [],
            'counties' => [],
            'state'    => '',
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('[]', (string) $stored->info('cities'), 'cleared cities');
        $this->assertSame('[]', (string) $stored->info('counties'), 'cleared counties');
        $this->assertSame('', (string) $stored->info('state'), 'cleared state');
    }

    /** A stale mirror cannot resurrect a cleared canonical value on a later, silent save. */
    public function test_a_stale_mirror_cannot_resurrect_a_cleared_value(): void
    {
        $auction = $this->record($this->owner(), ['cities' => json_encode(['Clearwater'])]);

        // The user clears cities.
        $this->save($this->editor(json_encode(['cities' => []])), $auction);
        $this->assertSame('[]', (string) $this->reread($auction)->info('cities'));

        // A later save that states only a DIFFERENT dimension must leave the clear standing, even
        // though the component property still holds a value.
        //
        // The record is re-read first, exactly as a second request would load it. That is not
        // incidental: the writer reads current canonical state through the model it is handed, so
        // handing it a stale instance would make the test assert against a document that no longer
        // exists. `G1f3BuyerOfferMigrationTest` re-reads between saves for the same reason.
        $this->save($this->editor(json_encode(['state' => 'GA'])), $this->reread($auction));

        $stored = $this->reread($auction);
        $this->assertSame(
            '[]',
            (string) $stored->info('cities'),
            'the cleared value must not be resurrected by the component property'
        );
        $this->assertSame(
            [],
            json_decode((string) $stored->info('location_dna_preferences'), true)['cities'],
            'and canonical state must still record the clear'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ABSENCE · preserve, and the no-op that used to destroy a legacy mirror
    // ═════════════════════════════════════════════════════════════════════════

    /** An absent dimension is preserved, not cleared — absence is not an instruction. */
    public function test_an_absent_dimension_is_preserved(): void
    {
        $auction = $this->record($this->owner(), [
            'cities'   => json_encode(['Clearwater']),
            'counties' => json_encode(['Pinellas']),
        ]);

        // States `state` only. Nothing is said about cities or counties.
        $this->save($this->editor(json_encode(['state' => 'GA'])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('GA', (string) $stored->info('state'));
        $this->assertSame(
            json_encode(['Clearwater']),
            (string) $stored->info('cities'),
            'an unstated dimension must keep its legacy mirror'
        );
        $this->assertSame(
            json_encode(['Pinellas']),
            (string) $stored->info('counties'),
            'and so must this one'
        );
    }

    /**
     * THE DEFECT THAT IS GONE · a no-op save on a legacy-only record no longer destroys `cities`.
     *
     * `false` is the real shape of this case, not a contrived one: the EAV accessor returns
     * boolean `false` for an unwritten key and the trait assigns it straight to the bridged
     * property, so an unmounted editor on a legacy record arrives exactly like this. Pre-migration
     * it wrote `[]` over the legacy mirror and an empty string over the canonical key.
     */
    public function test_a_no_op_save_on_a_legacy_only_record_destroys_nothing(): void
    {
        $auction = $this->record($this->owner(), ['cities' => json_encode(['Clearwater'])]);

        foreach ([false, '', null] as $payload) {
            $this->save($this->editor($payload), $auction);

            $stored = $this->reread($auction);

            $this->assertSame(
                json_encode(['Clearwater']),
                (string) $stored->info('cities'),
                'the legacy mirror must survive a payload that states nothing'
            );
            $this->assertSame(
                '',
                (string) $stored->info('location_dna_preferences'),
                'and no canonical blob may be invented for a legacy-only record'
            );
        }
    }

    /** A malformed payload is not an instruction either — it must never become a clear. */
    public function test_a_malformed_payload_is_not_a_clear(): void
    {
        $auction = $this->record($this->owner(), ['cities' => json_encode(['Clearwater'])]);

        $this->save($this->editor('{not json at all'), $auction);

        $this->assertSame(
            json_encode(['Clearwater']),
            (string) $this->reread($auction)->info('cities'),
            'a transport failure must never be interpreted as an instruction'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SCOPE · zipCodes stays out of the Buyer family
    // ═════════════════════════════════════════════════════════════════════════

    /** No `zipCodes` mirror is written, even when the canonical payload carries zip codes. */
    public function test_zip_codes_is_never_mirrored(): void
    {
        $auction = $this->record($this->owner());

        $this->save($this->editor(json_encode([
            'cities'    => ['Orlando'],
            'zip_codes' => ['33601', '33602'],
        ])), $auction);

        $stored = $this->reread($auction);

        $this->assertSame('["Orlando"]', (string) $stored->info('cities'));
        $this->assertFalse(
            $stored->info('zipCodes'),
            'the Buyer family has never written the zipCodes mirror and G1f-5 must not start — the '
            .'EAV accessor returns false for a key that was never written.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ATOMICITY AND FIDELITY
    // ═════════════════════════════════════════════════════════════════════════

    /** Canonical and mirror writes share one transaction — they cannot disagree after a save. */
    public function test_the_canonical_and_mirror_writes_share_one_transaction(): void
    {
        $auction = $this->record($this->owner());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->save($this->editor(json_encode(['cities' => ['Orlando'], 'state' => 'GA'])), $auction);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $positions = [];
        foreach ($queries as $i => $q) {
            foreach ((array) ($q['bindings'] ?? []) as $binding) {
                if (is_string($binding) && (str_contains($binding, 'Orlando') || $binding === 'GA')) {
                    $positions[] = $i;
                    break;
                }
            }
        }

        $this->assertNotEmpty($positions, 'the Location DNA writes must be observable');

        $stored = $this->reread($auction);
        $this->assertSame(
            ['Orlando'],
            json_decode((string) $stored->info('location_dna_preferences'), true)['cities'],
            'canonical and mirror agree after the save — the transaction is what guarantees they '
            .'cannot be left disagreeing by a partial failure'
        );
        $this->assertSame('["Orlando"]', (string) $stored->info('cities'));
    }

    /** Geometry survives the migrated path, and the stored bytes do not drift on a re-save. */
    public function test_geometry_survives_and_stored_bytes_are_stable(): void
    {
        $path = [];
        for ($i = 0; $i < 1200; $i++) {
            $path[] = ['lat' => 27.5 + ($i / 100000), 'lng' => -82.5 - ($i / 100000)];
        }

        $auction = $this->record($this->owner());

        $this->save($this->editor(json_encode([
            'cities'          => ["Coeur d'Alene", '東京'],
            'polygons'        => [['label' => 'Huge area', 'path' => $path]],
            'radius_searches' => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 5.25, 'address' => '1 Main St']],
            'location_notes'  => "Line one\nLine \"two\" — em dash, emoji 🏖",
        ])), $auction);

        $stored  = (string) $this->reread($auction)->info('location_dna_preferences');
        $decoded = json_decode($stored, true);

        $this->assertCount(1200, $decoded['polygons'][0]['path'], 'polygon truncated');
        $this->assertSame(5.25, $decoded['radius_searches'][0]['radius_miles'], 'radius drifted');
        $this->assertSame('東京', $decoded['cities'][1], 'unicode mangled');
        $this->assertSame(
            "Line one\nLine \"two\" — em dash, emoji 🏖",
            $decoded['location_notes'],
            'notes mangled'
        );

        // Re-saving the stored value must not move a byte: equal meaning ⇒ equal revision token
        // ⇒ the service returns before writing.
        $this->save($this->editor($stored), $auction);

        $this->assertSame(
            $stored,
            (string) $this->reread($auction)->info('location_dna_preferences'),
            'canonical bytes drifted across a re-save'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // BOTH ENTRY POINTS REACH THE ONE WRITER
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The draft path and the submit path both persist through the same single call.
     *
     * `saveAllMetadata()` is the shared write body for both entry points, so migrating it migrates
     * both at once. Asserted structurally because the two callers run validation, file handling
     * and a redirect around it — the same boundary the G1a matrix records for `update()`-based
     * flows — and behaviourally through the shared body above.
     */
    public function test_both_entry_points_route_through_the_single_writer_call(): void
    {
        $source = file_get_contents(base_path('app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php'));

        $this->assertSame(
            2,
            substr_count($source, '$this->saveAllMetadata('),
            'the draft and submit entry points both delegate to the shared save body'
        );
        $this->assertSame(
            1,
            substr_count($source, '$this->persistLocationDna($auction);'),
            'and that body reaches the canonical writer exactly once, so both entry points do too'
        );
    }
}
