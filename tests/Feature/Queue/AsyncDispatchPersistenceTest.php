<?php

namespace Tests\Feature\Queue;

use App\Jobs\MaterializeMatchesForSubject;
use App\Services\Dna\Relevance\MatchingV2Service;
use App\Services\Dna\Relevance\Persistence\MatchResultPersister;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Batch 6A Step 5 — isolated async-dispatch persistence.
 *
 * Proves that with the DATABASE queue driver and NO worker, dispatching
 * MaterializeMatchesForSubject persists exactly one row to `jobs` (the portable table added by
 * the Option-B migration) WITHOUT executing handle(). Runs entirely inside the protected
 * sqlite :memory: harness; heliumdb is unreachable and no worker is ever started.
 *
 * The committed QUEUE_CONNECTION=sync is never mutated: only config('queue.default') is
 * overridden locally and restored in a finally; env('QUEUE_CONNECTION') — the committed
 * surface — is asserted to remain sync throughout.
 *
 * WHY NON-EXECUTION IS ASSERTED VIA A SPY, NOT THE DB SIDE EFFECT
 * --------------------------------------------------------------
 * MaterializeMatchesForSubject::handle() only writes (to matching_v2_*) after
 * MatchResultPersister::canPersist() returns true, and that is independently gated off in tests
 * (MATCHING_V2 flags default-off + non-production). So "no matching_v2 rows" cannot distinguish
 * "handle did not run" from "handle ran but was gated". canPersist() is handle()'s FIRST line,
 * so a container spy on it is a faithful, gate-independent proxy for handle execution. The
 * second test proves that spy is not vacuous: under sync the same dispatch DOES call it.
 */
class AsyncDispatchPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function database_driver_persists_one_job_row_without_executing_handle(): void
    {
        // If handle() runs, its first line calls canPersist(). With no worker it never runs.
        $persister = Mockery::spy(MatchResultPersister::class);
        $this->app->instance(MatchResultPersister::class, $persister);

        $committedConnection = env('QUEUE_CONNECTION');   // committed source of truth
        $originalDefault     = config('queue.default');

        try {
            // (1) local override to the database driver.
            config(['queue.default' => 'database']);

            // (2) committed configuration is untouched: config() override does not mutate env().
            $this->assertSame('sync', $committedConnection, 'precondition: committed queue is sync');
            $this->assertSame('sync', env('QUEUE_CONNECTION'), 'committed QUEUE_CONNECTION must stay sync');

            MaterializeMatchesForSubject::dispatch('buyer', 4242, 5);

            // (3) exactly one row persisted to jobs.
            $this->assertSame(1, (int) DB::table('jobs')->count(), 'dispatch must persist exactly one jobs row');

            $row     = DB::table('jobs')->sole();
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);

            // (4) persisted queue name is 'matching' — end-to-end check of Step 3's assignment.
            $this->assertSame('matching', $row->queue);

            // (5) payload identifies MaterializeMatchesForSubject.
            $this->assertSame(MaterializeMatchesForSubject::class, $payload['displayName'] ?? null);
            $this->assertSame(MaterializeMatchesForSubject::class, $payload['data']['commandName'] ?? null);

            // (6)/(8) handle() did not execute: its first line (canPersist) was never called...
            $persister->shouldNotHaveReceived('canPersist');

            // ...and, belt-and-braces, the job's DB side effect is absent.
            $this->assertSame(0, (int) DB::table('matching_v2_match_runs')->count());
            $this->assertSame(0, (int) DB::table('matching_v2_matches')->count());
        } finally {
            // (9) restore temporary config.
            config(['queue.default' => $originalDefault]);
        }

        // (2, post) committed default resolves back to sync.
        $this->assertSame('sync', config('queue.default'));
    }

    /** @test */
    public function sync_driver_executes_handle_proving_the_spy_is_not_vacuous(): void
    {
        // Anti-vacuous contrast: under sync the SAME dispatch runs handle() inline, so the spy
        // MUST observe canPersist(). Both handle() deps are bound so method injection resolves
        // without fixtures; canPersist() returns false so the engine is never actually used.
        $persister = Mockery::mock(MatchResultPersister::class);
        $persister->shouldReceive('canPersist')->andReturnFalse();
        $this->app->instance(MatchResultPersister::class, $persister);
        $this->app->instance(MatchingV2Service::class, Mockery::mock(MatchingV2Service::class));

        $originalDefault = config('queue.default');

        try {
            config(['queue.default' => 'sync']);

            MaterializeMatchesForSubject::dispatch('buyer', 4242, 5);

            // handle() ran inline -> canPersist() was called.
            $persister->shouldHaveReceived('canPersist');

            // sync executes inline and does not use the jobs table.
            $this->assertSame(0, (int) DB::table('jobs')->count());
        } finally {
            config(['queue.default' => $originalDefault]);
        }
    }
}
