<?php

namespace App\Jobs;

use App\Services\Dna\Relevance\MatchingV2Service;
use App\Services\Dna\Relevance\Persistence\MatchResultPersister;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * MaterializeMatchesForSubject — Matching V2 C7: per-subject materialization job
 * (OD-2, the queued path alongside the batch command).
 *
 * Computes one subject's OrchestratedMatchResult via the C6 facade and persists
 * it. Inert under the exact same three write gates as MatchResultPersister — if
 * any is closed (V2 off, persistence off, or production) the persister no-ops and
 * the job writes nothing. It never enables anything itself.
 *
 * @see docs/matching-v2-c7-persistence-scope.md §7
 */
class MaterializeMatchesForSubject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly ?int $cap = null,
    ) {
        // Queueable owns $queue; redeclaring it as a property with a default fatals on PHP 8.2.
        $this->queue = 'matching';
    }

    public function handle(MatchingV2Service $engine, MatchResultPersister $persister): void
    {
        // Cheap early-out: if persistence can't write, don't even compute.
        if (! $persister->canPersist()) {
            return;
        }

        $result = $engine->matchForSubject($this->subjectType, $this->subjectId, $this->cap);

        $persister->persist($result);
    }
}
