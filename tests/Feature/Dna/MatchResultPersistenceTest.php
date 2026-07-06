<?php

namespace Tests\Feature\Dna;

use App\Models\DnaScore;
use App\Models\Matching\MatchRun;
use App\Models\Matching\PersistedMatch;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\MatchTier;
use App\Services\Dna\Relevance\MatchTierResult;
use App\Services\Dna\Relevance\OrchestratedMatchResult;
use App\Services\Dna\Relevance\Persistence\MatchResultPersister;
use App\Services\Dna\Relevance\Persistence\PersistedMatchReader;
use App\Services\Dna\Relevance\RankedMatch;
use App\Services\Dna\Relevance\RankedMatchSet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 C7 — persistence slice safety + correctness.
 *
 * Proves the load-bearing invariants: the writer is inert unless BOTH flags are
 * on AND the app is not production; zero-determined subjects still get an empty
 * summary; re-materialization is idempotent; the reader re-gates on flag +
 * version; and persistence never touches dna_scores.
 */
class MatchResultPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Default: both gates ON, non-production. Individual tests flip as needed.
        config(['matching.v2_enabled' => true]);
        config(['matching.persistence.enabled' => true]);
        config(['matching.persistence.version' => 'c7-test']);
    }

    private function tier(MatchTier $t, int $value): MatchTierResult
    {
        return new MatchTierResult($t, $value, 90, 100, ['pet_friendliness'], [], [], 'seed');
    }

    /**
     * @param array<int,array{0:int,1:MatchTier,2:int,3:?string}> $matches [id, tier, value, type]
     */
    private function result(string $type, int $id, array $matches, int $undetermined = 0): OrchestratedMatchResult
    {
        $ranked = array_map(
            fn (array $m): RankedMatch => new RankedMatch($m[0], $this->tier($m[1], $m[2]), $m[3]),
            $matches
        );

        return new OrchestratedMatchResult(
            $type,
            $id,
            MatchDirection::DemandToListings,
            new RankedMatchSet($ranked, $undetermined),
            count($ranked) + $undetermined,
            false,
        );
    }

    private function persister(): MatchResultPersister
    {
        return app(MatchResultPersister::class);
    }

    // ── Gating / safety ───────────────────────────────────────────────────────

    public function test_inert_when_v2_master_gate_off(): void
    {
        config(['matching.v2_enabled' => false]);

        $run = $this->persister()->persist($this->result('buyer_agent', 1, [[10, MatchTier::Strong, 80, 'seller_agent']]));

        $this->assertNull($run);
        $this->assertSame(0, MatchRun::count());
        $this->assertSame(0, PersistedMatch::count());
    }

    public function test_inert_when_persistence_gate_off(): void
    {
        config(['matching.persistence.enabled' => false]);

        $run = $this->persister()->persist($this->result('buyer_agent', 1, [[10, MatchTier::Strong, 80, 'seller_agent']]));

        $this->assertNull($run);
        $this->assertSame(0, MatchRun::count());
    }

    public function test_refuses_in_production_even_with_both_flags_on(): void
    {
        $this->app['env'] = 'production';

        $this->assertFalse($this->persister()->canPersist());

        $run = $this->persister()->persist($this->result('buyer_agent', 1, [[10, MatchTier::Strong, 80, 'seller_agent']]));

        $this->assertNull($run);
        $this->assertSame(0, MatchRun::count());
        $this->assertSame(0, PersistedMatch::count());
    }

    // ── Correctness ───────────────────────────────────────────────────────────

    public function test_persists_summary_and_ordered_children(): void
    {
        $run = $this->persister()->persist($this->result('buyer_agent', 7, [
            [10, MatchTier::Exact, 95, 'seller_agent'],
            [10, MatchTier::Strong, 80, 'landlord_agent'], // colliding id, distinct type
            [12, MatchTier::Opportunity, 40, 'seller_agent'],
        ], undetermined: 2));

        $this->assertNotNull($run);
        $this->assertSame('buyer_agent', $run->subject_type);
        $this->assertSame(7, $run->subject_id);
        $this->assertSame('DemandToListings', $run->direction);
        $this->assertSame('c7-test', $run->version);
        $this->assertSame(3, $run->determined_count);
        $this->assertSame(2, $run->undetermined_count);
        $this->assertSame(5, $run->candidates_considered);
        $this->assertSame(
            ['exact' => 1, 'strong' => 1, 'similar' => 0, 'opportunity' => 1],
            $run->tier_counts
        );

        $children = PersistedMatch::where('match_run_id', $run->id)->orderBy('position')->get();
        $this->assertCount(3, $children);
        $this->assertSame([0, 1, 2], $children->pluck('position')->all());
        $this->assertSame(['seller_agent', 'landlord_agent', 'seller_agent'], $children->pluck('counterpart_type')->all());
        $this->assertSame('exact', $children[0]->tier);
        $this->assertSame(95, $children[0]->value);
        $this->assertSame(90, $children[0]->confidence);
        $this->assertSame(100, $children[0]->coverage);
    }

    public function test_zero_determined_writes_empty_summary_only(): void
    {
        $run = $this->persister()->persist(
            OrchestratedMatchResult::empty('buyer_agent', 99, MatchDirection::DemandToListings)
        );

        $this->assertNotNull($run);
        $this->assertSame(0, $run->determined_count);
        $this->assertSame(['exact' => 0, 'strong' => 0, 'similar' => 0, 'opportunity' => 0], $run->tier_counts);
        $this->assertSame(1, MatchRun::where('subject_type', 'buyer_agent')->where('subject_id', 99)->count());
        $this->assertSame(0, PersistedMatch::where('match_run_id', $run->id)->count());
    }

    public function test_reupsert_is_idempotent(): void
    {
        $p = $this->persister();

        $first = $p->persist($this->result('buyer_agent', 5, [
            [10, MatchTier::Exact, 95, 'seller_agent'],
            [11, MatchTier::Strong, 80, 'seller_agent'],
        ]));

        $second = $p->persist($this->result('buyer_agent', 5, [
            [10, MatchTier::Exact, 90, 'seller_agent'],
        ]));

        $this->assertSame($first->id, $second->id, 'same subject+version upserts one summary row');
        $this->assertSame(1, MatchRun::where('subject_type', 'buyer_agent')->where('subject_id', 5)->count());
        $this->assertSame(1, PersistedMatch::where('match_run_id', $second->id)->count(), 'children replaced, not duplicated');
        $this->assertSame(90, PersistedMatch::where('match_run_id', $second->id)->first()->value);
    }

    // ── Reader re-gate (OD-3) ─────────────────────────────────────────────────

    public function test_reader_regate_returns_null_when_v2_off(): void
    {
        $this->persister()->persist($this->result('buyer_agent', 3, [[10, MatchTier::Strong, 80, 'seller_agent']]));
        $this->assertSame(1, MatchRun::count(), 'row exists on disk');

        config(['matching.v2_enabled' => false]);

        $this->assertNull(app(PersistedMatchReader::class)->read('buyer_agent', 3), 'disabled engine hides persisted rows');
    }

    public function test_reader_regate_ignores_stale_version(): void
    {
        $this->persister()->persist($this->result('buyer_agent', 3, [[10, MatchTier::Strong, 80, 'seller_agent']]));

        config(['matching.persistence.version' => 'c7-v2-newer']);

        $this->assertNull(app(PersistedMatchReader::class)->read('buyer_agent', 3), 'version bump invalidates old rows at read time');
    }

    public function test_reader_roundtrips_persisted_run(): void
    {
        $this->persister()->persist($this->result('buyer_agent', 8, [
            [10, MatchTier::Exact, 95, 'seller_agent'],
            [11, MatchTier::Similar, 60, 'landlord_agent'],
        ], undetermined: 1));

        $read = app(PersistedMatchReader::class)->read('buyer_agent', 8);

        $this->assertNotNull($read);
        $this->assertSame('buyer_agent', $read->subjectType());
        $this->assertSame(8, $read->subjectId());
        $this->assertSame('DemandToListings', $read->direction());
        $this->assertSame('c7-test', $read->version());
        $this->assertSame(2, $read->determinedCount());
        $this->assertSame(1, $read->undeterminedCount());
        $this->assertFalse($read->isEmpty());

        $matches = $read->matches();
        $this->assertCount(2, $matches);
        $this->assertSame(0, $matches[0]['position']);
        $this->assertSame('seller_agent', $matches[0]['counterpart_type']);
        $this->assertSame(10, $matches[0]['counterpart_id']);
        $this->assertSame('exact', $matches[0]['tier']);
        $this->assertSame(95, $matches[0]['value']);
    }

    public function test_reader_returns_null_when_never_materialized(): void
    {
        $this->assertNull(app(PersistedMatchReader::class)->read('buyer_agent', 12345));
    }

    // ── Isolation guard ───────────────────────────────────────────────────────

    public function test_persist_never_touches_dna_scores(): void
    {
        DnaScore::create([
            'listing_type' => 'seller_agent', 'listing_id' => 10, 'score_key' => 'pet_friendliness',
            'side' => 'property', 'value' => 80, 'data_completeness' => 100, 'confidence' => 90,
            'explanation' => 'seed', 'version' => 'TEST_V1', 'generator_version' => 'TEST_V1', 'generated_by' => 'system',
        ]);
        $before = DnaScore::count();

        $this->persister()->persist($this->result('buyer_agent', 4, [[10, MatchTier::Strong, 80, 'seller_agent']]));

        $this->assertSame($before, DnaScore::count(), 'persistence is read-only over dna_scores');
    }
}
