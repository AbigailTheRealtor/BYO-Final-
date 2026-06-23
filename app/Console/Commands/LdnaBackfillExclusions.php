<?php

namespace App\Console\Commands;

use App\Jobs\ComputeLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use Illuminate\Console\Command;
use Throwable;

/**
 * ldna:backfill-exclusions — Delete POI rows whose rank-1 result fails current exclusion rules
 *
 * Intended for listings whose POI rows were written before a hardening task
 * improved the exclusion rules (e.g. before types_json was populated, or before
 * a new exclude_if_name_matches pattern was added). Those rows can never be
 * caught by exclusion logic that depends on data absent at write time.
 *
 * The command inspects rank-1 rows per (listing_type, listing_id, poi_category),
 * applies the current CATEGORY_EXCLUSION_RULES against the stored poi_name and
 * types_json, and deletes all rows for the category when rank-1 fails.
 *
 * Deleted categories will be re-fetched automatically on the next
 * ComputeLocationDna pipeline run, because the cache-bypass fix in
 * LocationDnaPoiDistanceService now detects missing categories.
 *
 * Usage:
 *   php artisan ldna:backfill-exclusions
 *   php artisan ldna:backfill-exclusions --listing-id=183
 *   php artisan ldna:backfill-exclusions --dry-run
 *   php artisan ldna:backfill-exclusions --listing-id=183 --force-rerun
 */
class LdnaBackfillExclusions extends Command
{
    protected $signature = 'ldna:backfill-exclusions
        {--listing-id=  : Limit to a single listing_id (checks all listing_types for that ID)}
        {--dry-run      : Report affected rows without deleting or dispatching anything}
        {--force-rerun  : After deletions, dispatch ComputeLocationDna jobs for affected listings}';

    protected $description = 'Delete cached POI rows that fail current exclusion rules so they are re-fetched on the next pipeline run';

    public function handle(): int
    {
        $listingId  = $this->option('listing-id') ? (int) $this->option('listing-id') : null;
        $dryRun     = (bool) $this->option('dry-run');
        $forceRerun = (bool) $this->option('force-rerun');

        if ($dryRun) {
            $this->warn('[DRY-RUN] No rows will be deleted or jobs dispatched.');
        }

        $service = new LocationDnaPoiDistanceService();

        // Fetch all rank-1 rows, grouped by listing and category.
        // We join on property_location_dna to get the listing_type alongside each row.
        $query = PropertyLocationPoi::where('rank', 1)
            ->whereIn('poi_category', array_keys(LocationDnaPoiDistanceService::CATEGORIES));

        if ($listingId !== null) {
            $query->where('listing_id', $listingId);
        }

        $rank1Rows = $query->orderBy('listing_type')
            ->orderBy('listing_id')
            ->orderBy('poi_category')
            ->get();

        if ($rank1Rows->isEmpty()) {
            $this->info('No rank-1 POI rows found' . ($listingId ? " for listing_id={$listingId}" : '') . '.');
            return Command::SUCCESS;
        }

        $this->info("Inspecting {$rank1Rows->count()} rank-1 row(s)...");

        $failedRows    = [];
        $affectedPairs = [];

        foreach ($rank1Rows as $row) {
            $syntheticPlace = [
                'name'  => $row->poi_name ?? '',
                'types' => $row->types_json ?? [],
            ];

            if (! $service->passesExclusionFilter($row->poi_category, $syntheticPlace)) {
                $failedRows[] = $row;
                $pair = $row->listing_type . '|' . $row->listing_id;
                if (! in_array($pair, $affectedPairs, true)) {
                    $affectedPairs[] = $pair;
                }
            }
        }

        if (empty($failedRows)) {
            $this->info('All rank-1 rows pass current exclusion rules. Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info(count($failedRows) . ' rank-1 row(s) fail current exclusion rules:');
        $this->newLine();

        foreach ($failedRows as $row) {
            $typesDisplay = $row->types_json ? implode(', ', $row->types_json) : 'NULL';
            $this->line(sprintf(
                '  [%s / listing_id=%d] category=%-20s  rank1="%s"  types=[%s]',
                $row->listing_type,
                $row->listing_id,
                $row->poi_category,
                $row->poi_name,
                $typesDisplay,
            ));
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn(sprintf(
                '[DRY-RUN] Would delete all category rows for %d rank-1 failure(s) across %d listing(s).',
                count($failedRows),
                count($affectedPairs),
            ));
            return Command::SUCCESS;
        }

        // Delete all rows for each affected (listing_type, listing_id, poi_category) triple.
        $deletedCount = 0;
        foreach ($failedRows as $row) {
            try {
                $deleted = PropertyLocationPoi::where('listing_type', $row->listing_type)
                    ->where('listing_id', $row->listing_id)
                    ->where('poi_category', $row->poi_category)
                    ->delete();
                $deletedCount += $deleted;
                $this->line("  ✓ Deleted {$deleted} row(s): {$row->listing_type} / {$row->listing_id} / {$row->poi_category}");
            } catch (Throwable $e) {
                $this->error("  ✗ Failed to delete {$row->listing_type} / {$row->listing_id} / {$row->poi_category}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Deleted {$deletedCount} total row(s) across " . count($affectedPairs) . ' listing(s).');

        if ($forceRerun) {
            $this->newLine();
            $this->info('Dispatching ComputeLocationDna jobs for affected listings...');
            $dispatched = 0;
            foreach ($affectedPairs as $pair) {
                [$type, $id] = explode('|', $pair, 2);
                try {
                    ComputeLocationDna::dispatch($type, (int) $id);
                    $this->line("  ✓ Dispatched: {$type} / {$id}");
                    $dispatched++;
                } catch (Throwable $e) {
                    $this->error("  ✗ Failed to dispatch {$type} / {$id}: {$e->getMessage()}");
                }
            }
            $this->info("Dispatched {$dispatched} job(s).");
        } else {
            $this->line('Tip: run with --force-rerun to immediately dispatch ComputeLocationDna jobs.');
            $this->line('     Or wait for the next scheduled pipeline run to re-fetch affected categories.');
        }

        return Command::SUCCESS;
    }
}
