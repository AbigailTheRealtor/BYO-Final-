<?php

namespace App\Console\Commands;

use App\Models\DnaScore;
use App\Services\Dna\Scores\LockAndLeaveScoreService;
use Illuminate\Console\Command;

/**
 * dna:scrub-lock-and-leave-age — 55+ leak remediation straggler cleanup.
 *
 * Removes any pre-V2 Lock-and-Leave DEMAND dna_scores rows. Such rows persisted the
 * 55+ leak (inputs_json.age_targeted, the "55+ targeted" explanation clause, and a
 * +15 value bump). Regeneration (`dna:generate-scores`) overwrites rows for listings
 * that still regenerate; this command cleans up STRAGGLERS — rows for listings that
 * no longer regenerate (deleted/inactive) — so no leaked age data lingers.
 *
 * Deletion (not in-place scrub) is deliberate: because V2 also changes the value,
 * a partial scrub would leave a wrong value; deleting lets the row regenerate as a
 * correct V2 row on the next generation, and a missing dimension is harmless to the
 * read-only Matching V2 consumer.
 *
 * Safe by construction: touches ONLY (score_key='lock_and_leave', side='demand')
 * rows whose version is not the current V2. Idempotent (a second run finds none) and
 * a no-op where generation has never run (e.g. production with the flag off).
 *
 * @see docs/matching-v2-55plus-leak-remediation-scope.md
 */
class DnaScrubLockAndLeaveAge extends Command
{
    protected $signature = 'dna:scrub-lock-and-leave-age {--dry-run : Report affected rows without deleting}';

    protected $description = 'Delete stale pre-V2 Lock-and-Leave demand dna_scores rows (55+ age-leak straggler cleanup).';

    public function handle(): int
    {
        $version = LockAndLeaveScoreService::VERSION;

        $query = DnaScore::query()
            ->where('score_key', LockAndLeaveScoreService::SCORE_KEY)
            ->where('side', 'demand')
            ->where(function ($q) use ($version) {
                $q->whereNull('version')->orWhere('version', '!=', $version);
            });

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("[dry-run] {$count} stale (pre-{$version}) Lock-and-Leave demand row(s) would be deleted.");
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} stale Lock-and-Leave demand row(s); they regenerate as {$version} on the next generation run.");

        return self::SUCCESS;
    }
}
