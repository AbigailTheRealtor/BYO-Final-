<?php

namespace App\Console\Commands;

use App\Jobs\ComputeDnaScores;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\Dna\Scores\DnaScoreGenerationService;
use Illuminate\Console\Command;

/**
 * dna:generate-scores — bulk production of dna_scores across the four *_agent
 * listing families (Beyond-MLS Phase 13).
 *
 * Two uses:
 *   - Backfill: seed scores for existing listings after enabling generation.
 *   - Version-aware rescore: after a generator/algorithm bump, --only-stale
 *     regenerates just the listings whose persisted generator_version (and, for
 *     bridged scores, source_version) is behind current — the analogue of
 *     ldna:rerank-all --only-stale.
 *
 * Honors the single master gate (config dna_scores.generation_enabled): if
 * generation is disabled the command performs no work and says so, so there is
 * exactly one switch that turns production DNA generation on.
 *
 * Usage:
 *   php artisan dna:generate-scores --only-stale            # queue stale only (recommended)
 *   php artisan dna:generate-scores --type=seller_agent     # one family
 *   php artisan dna:generate-scores --sync                  # run inline (no queue)
 */
class DnaGenerateScores extends Command
{
    protected $signature = 'dna:generate-scores
        {--type= : Limit to one listing type: seller_agent|landlord_agent|buyer_agent|tenant_agent}
        {--only-stale : Only (re)generate scores whose persisted version is behind current}
        {--sync : Generate inline instead of dispatching ComputeDnaScores to the queue}';

    protected $description = 'Bulk-generate dna_scores for *_agent listings, version-aware (Phase 13).';

    private const MODELS = [
        'seller_agent'   => SellerAgentAuction::class,
        'landlord_agent' => LandlordAgentAuction::class,
        'buyer_agent'    => BuyerAgentAuction::class,
        'tenant_agent'   => TenantAgentAuction::class,
    ];

    public function handle(DnaScoreGenerationService $service): int
    {
        if (! $service->isEnabled()) {
            $this->warn('DNA score generation is disabled (config dna_scores.generation_enabled). Nothing to do.');
            $this->line('Enable DNA_SCORES_GENERATION_ENABLED to run generation.');
            return self::SUCCESS;
        }

        $onlyStale = (bool) $this->option('only-stale');
        $sync      = (bool) $this->option('sync');
        $type      = $this->option('type');

        $types = self::MODELS;
        if ($type !== null) {
            if (! isset(self::MODELS[$type])) {
                $this->error("Unknown --type '{$type}'. Expected one of: " . implode(', ', array_keys(self::MODELS)) . '.');
                return self::FAILURE;
            }
            $types = [$type => self::MODELS[$type]];
        }

        $options   = ['generated_by' => 'system', 'only_stale' => $onlyStale];
        $listings  = 0;
        $rowsTotal = 0;

        foreach ($types as $listingType => $modelClass) {
            foreach ($modelClass::query()->pluck('id') as $id) {
                $listings++;

                if ($sync) {
                    $rowsTotal += count($service->generateForListing($listingType, (int) $id, $options));
                } else {
                    ComputeDnaScores::dispatch($listingType, (int) $id, 'system');
                }
            }
        }

        $mode = $sync ? 'generated' : 'queued';
        $stale = $onlyStale ? ' (only-stale)' : '';
        $this->info("dna:generate-scores {$mode} for {$listings} listing(s){$stale}."
            . ($sync ? " Wrote {$rowsTotal} score row(s)." : ''));

        return self::SUCCESS;
    }
}
