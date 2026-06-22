<?php

namespace App\Console\Commands;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\LocationDna\LocationDnaLifestyleScoreService;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaSummaryService;
use Illuminate\Console\Command;
use Throwable;

/**
 * ldna:refresh-all — Re-run the full Location DNA pipeline for all listings
 *
 * Always clears existing PropertyLocationPoi rows before re-running the
 * pipeline. This is intentional: the POI service skips refetching when stored
 * source coordinates match the current geocoded coordinates (cache path). By
 * deleting first, we bypass the cache and guarantee fresh multi-candidate data
 * is fetched from Google Places on every run.
 *
 * Primary use: backfill multi-candidate POI storage after the v2 migration.
 *
 * Usage:
 *   php artisan ldna:refresh-all
 *   php artisan ldna:refresh-all --listing-type=seller_agent
 *   php artisan ldna:refresh-all --dry-run    (print what would run, no changes)
 */
class LdnaRefreshAll extends Command
{
    protected $signature = 'ldna:refresh-all
        {--listing-type=  : Limit to a specific listing_type (e.g. seller_agent, landlord_agent)}
        {--dry-run        : Print which listings would be refreshed without running the pipeline}';

    protected $description = 'Re-run the Location DNA pipeline for all listings with an existing PropertyLocationDna record';

    public function handle(): int
    {
        $listingType = $this->option('listing-type') ?: null;
        $dryRun      = (bool) $this->option('dry-run');

        $query = PropertyLocationDna::select(['id', 'listing_type', 'listing_id', 'geocode_status']);

        if ($listingType !== null) {
            $query->where('listing_type', $listingType);
        }

        $records = $query->orderBy('listing_type')->orderBy('listing_id')->get();

        if ($records->isEmpty()) {
            $this->info('No PropertyLocationDna records found' . ($listingType ? " for listing_type={$listingType}" : '') . '.');
            return Command::SUCCESS;
        }

        $this->info("Found {$records->count()} PropertyLocationDna record(s) to refresh.");

        if ($dryRun) {
            foreach ($records as $rec) {
                $this->line("  [DRY-RUN] {$rec->listing_type} / {$rec->listing_id} (geocode_status={$rec->geocode_status})");
            }
            return Command::SUCCESS;
        }

        // Always clear existing POI rows before re-running.
        //
        // The POI distance service has coordinate-based caching: it skips the
        // Google Places API call if stored source_lat/source_lng match the
        // current geocoded coordinates. Since existing listings' coordinates
        // have not changed, running without this deletion would hit the cache
        // path and never rebuild multi-candidate (v2) data.
        //
        // Deleting first forces a fresh fetch on every ldna:refresh-all run.
        $poisDeleted = PropertyLocationPoi::when(
            $listingType,
            fn($q) => $q->where('listing_type', $listingType)
        )->delete();

        $this->line("Cleared {$poisDeleted} existing POI row(s) — fresh candidates will be fetched.");

        $runner = new LocationDnaPipelineRunner(
            new LocationDnaGeocodeService(),
            new LocationDnaPoiDistanceService(),
            new LocationDnaSummaryService(),
            new LocationDnaLifestyleScoreService(),
        );

        $successCount = 0;
        $failCount    = 0;

        foreach ($records as $index => $rec) {
            $label = "{$rec->listing_type} / {$rec->listing_id}";
            $this->line("[" . ($index + 1) . "/{$records->count()}] Refreshing {$label} ...");

            try {
                $result = $runner->run($rec->listing_type, (int) $rec->listing_id);
                $status = $result['status'] ?? 'unknown';

                if ($status === 'success') {
                    $this->info("  ✓ {$label} — {$status}");
                    $successCount++;
                } elseif ($status === 'partial') {
                    $this->warn("  ~ {$label} — {$status}");
                    $successCount++;
                } else {
                    $error = $result['error'] ?? ($result['steps']['geocode']['error'] ?? 'unknown error');
                    $this->error("  ✗ {$label} — {$status}: {$error}");
                    $failCount++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ {$label} — exception: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Done. Success: {$successCount} / Fail: {$failCount} / Total: {$records->count()}");

        return $failCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
