<?php

namespace App\Console\Commands;

use App\Jobs\MaterializeMatchesForSubject;
use App\Services\Dna\Relevance\MatchingV2Service;
use App\Services\Dna\Relevance\Persistence\MatchResultPersister;
use App\Services\Dna\Relevance\Validation\ValidationRosterBuilder;
use Illuminate\Console\Command;

/**
 * matching:materialize — Matching V2 C7: batch materialization (OD-2).
 *
 * Computes the read-only Matching V2 result for a set of subjects (one explicit
 * subject, a pinned roster, or an auto-discovered roster) and PERSISTS each via
 * MatchResultPersister into the matching_v2_* tables.
 *
 * GOVERNANCE — this is the only C7 entry point that intends to WRITE, so it is
 * hard-guarded:
 *   - refuses in the production environment (never overridable);
 *   - refuses unless BOTH MATCHING_V2_ENABLED and MATCHING_V2_PERSISTENCE_ENABLED
 *     are on (it does NOT force-enable anything — unlike the read-only preview);
 *   - otherwise the underlying persister is itself gated, so nothing can slip.
 *
 * @see docs/matching-v2-c7-persistence-scope.md §3, §7, §9
 */
class MatchingMaterialize extends Command
{
    private const SUPPORTED = ['seller_agent', 'landlord_agent', 'buyer_agent', 'tenant_agent'];
    private const EXIT_REFUSED = 2;

    protected $signature = 'matching:materialize
        {listingType? : optional single subject type (seller_agent|landlord_agent|buyer_agent|tenant_agent)}
        {listingId? : optional single subject id (requires listingType)}
        {--roster= : path to a pinned roster JSON (subjects to materialize)}
        {--limit=10 : subjects per auto-discovered category (when no explicit subject/roster)}
        {--cap= : discovery candidate cap passthrough}
        {--queue : dispatch a per-subject job for each subject instead of materializing inline}';

    protected $description = 'Materialize Matching V2 results into the matching_v2_* tables (staging/dev only; refuses in production).';

    public function handle(
        MatchingV2Service $engine,
        MatchResultPersister $persister,
        ValidationRosterBuilder $rosters,
    ): int {
        // --- guard 1: staging/dev only, never overridable ---
        if ($this->getLaravel()->environment('production')) {
            $this->error('matching:materialize writes and refuses to run in production.');
            return self::EXIT_REFUSED;
        }

        // --- guard 2: both gates must be on (this command does NOT force-enable) ---
        if (! config('matching.v2_enabled', false) || ! config('matching.persistence.enabled', false)) {
            $this->error('Refusing: set MATCHING_V2_ENABLED=true AND MATCHING_V2_PERSISTENCE_ENABLED=true (staging) first.');
            return self::EXIT_REFUSED;
        }

        try {
            $subjects = $this->resolveSubjects($rosters);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::EXIT_REFUSED;
        }

        if ($subjects === []) {
            $this->warn('No subjects to materialize.');
            return self::SUCCESS;
        }

        $cap    = $this->option('cap') !== null ? (int) $this->option('cap') : null;
        $queued = (bool) $this->option('queue');

        $this->info(sprintf(
            'Materializing %d subject(s) at version "%s"%s.',
            count($subjects),
            $persister->version(),
            $queued ? ' (queued)' : '',
        ));

        $written = 0;
        $empty   = 0;
        foreach ($subjects as $s) {
            [$type, $id] = $s;

            if ($queued) {
                MaterializeMatchesForSubject::dispatch($type, $id, $cap);
                $this->line("  queued {$type}#{$id}");
                continue;
            }

            $result = $engine->matchForSubject($type, $id, $cap);
            $run    = $persister->persist($result);

            if ($run === null) {
                // A gate closed mid-run — stop rather than silently no-op the rest.
                $this->error('Persister became inert (a gate closed); aborting.');
                return self::EXIT_REFUSED;
            }

            $determined = (int) $run->determined_count;
            $written++;
            if ($determined === 0) {
                $empty++;
            }
            $this->line("  {$type}#{$id} → determined={$determined}");
        }

        if (! $queued) {
            $this->info("Done. runs written={$written} (empty summaries={$empty}).");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int,array{0:string,1:int}> [type, id] pairs
     */
    private function resolveSubjects(ValidationRosterBuilder $rosters): array
    {
        // Explicit single subject.
        $type = $this->argument('listingType');
        $id   = $this->argument('listingId');
        if ($type !== null || $id !== null) {
            if ($type === null || $id === null) {
                throw new \RuntimeException('Provide BOTH listingType and listingId, or neither.');
            }
            if (! in_array($type, self::SUPPORTED, true)) {
                throw new \RuntimeException("Unsupported subject listing_type: {$type}.");
            }
            return [[(string) $type, (int) $id]];
        }

        // Pinned or auto-discovered roster.
        $entries = $this->option('roster')
            ? $rosters->fromFile((string) $this->option('roster'))
            : $rosters->build((int) $this->option('limit'));

        $subjects = [];
        $seen     = [];
        foreach ($entries as $e) {
            $key = $e['listing_type'] . '#' . $e['listing_id'];
            if (isset($seen[$key]) || ! in_array($e['listing_type'], self::SUPPORTED, true)) {
                continue;
            }
            $seen[$key]  = true;
            $subjects[]  = [(string) $e['listing_type'], (int) $e['listing_id']];
        }

        return $subjects;
    }
}
