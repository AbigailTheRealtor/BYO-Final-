<?php

namespace App\Console\Commands;

use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaLifestyleScoreService;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaSummaryService;
use App\Services\LocationDna\LocationDnaVersionService;
use Illuminate\Console\Command;

/**
 * ldna:rerank-all — recompute POI rankings from cache, no Google API calls (Stage E0).
 *
 * The cost-saving counterpart to a scoring-rules change: when ranking weights,
 * exclusion rules, or scoring constants change (pois_scoring_version moves), this
 * re-ranks stored candidates and rebuilds summary_json / lifestyle_json entirely
 * from the database — no refetch, no rate limits, no API spend
 * (docs/launch-audits/location-dna-architecture-review.md §2).
 *
 * Fetch-version changes (provider/category/radius) are NOT handled here — those
 * require a real refetch via the normal enrichment path.
 *
 * Usage:
 *   php artisan ldna:rerank-all --from-cache --only-stale   # recommended
 *   php artisan ldna:rerank-all --from-cache                # every listing
 */
class LdnaRerankAll extends Command
{
    protected $signature = 'ldna:rerank-all
        {--from-cache : Recompute from stored candidates without any API call (required)}
        {--only-stale : Only listings whose stored scoring version differs from current}';

    protected $description = 'Recompute POI rankings from cache (no API) for listings with a stale scoring version (Stage E0).';

    public function handle(
        LocationDnaVersionService $versions,
        LocationDnaPoiDistanceService $poi,
        LocationDnaSummaryService $summary,
        LocationDnaLifestyleScoreService $lifestyle,
    ): int {
        if (! $this->option('from-cache')) {
            $this->error('Refusing to run: pass --from-cache. Only cache recompute (no API) is supported.');
            return self::FAILURE;
        }

        $currentScoring = $versions->scoringVersion();

        $query = PropertyLocationPoi::query()
            ->select('listing_type', 'listing_id')
            ->distinct();

        if ($this->option('only-stale')) {
            $query->where(function ($q) use ($currentScoring) {
                $q->whereNull('pois_scoring_version')
                    ->orWhere('pois_scoring_version', '<>', $currentScoring);
            });
        }

        $listings   = $query->get();
        $recomputed = 0;

        foreach ($listings as $listing) {
            $updated = $poi->recomputeRankingsFromCache($listing->listing_type, $listing->listing_id);

            if ($updated > 0) {
                // Rebuild the read-side artifacts from the freshly re-ranked rows.
                // Both read from the database — no API call.
                $summary->summarizeForListing($listing->listing_type, $listing->listing_id);
                $lifestyle->generateForListing($listing->listing_type, $listing->listing_id);
                $recomputed++;
            }
        }

        $this->info("Recomputed {$recomputed} listing(s) from cache (scoring={$currentScoring}).");

        return self::SUCCESS;
    }
}
