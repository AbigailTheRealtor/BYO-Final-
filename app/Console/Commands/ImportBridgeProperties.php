<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgePropertyNormalizer;

class ImportBridgeProperties extends Command
{
    protected $signature = 'bridge:import-properties
                            {--limit=10 : Number of properties to fetch (single-page mode)}
                            {--target=0 : Import until this many total rows are in bridge_properties (paginated mode; 0 = disabled)}
                            {--page-size=200 : Records per API page in paginated mode}
                            {--status= : OData StandardStatus filter (e.g. Active)}
                            {--property-type= : OData PropertyType filter (e.g. Residential)}';

    protected $description = 'Fetch properties from the Bridge OData API and upsert into bridge_properties';

    public function handle(BridgeApiService $service, BridgePropertyNormalizer $normalizer): int
    {
        $target   = (int) $this->option('target');
        $pageSize = (int) $this->option('page-size');

        $filter = $this->buildODataFilter();

        if ($target > 0) {
            return $this->runPaginated($service, $normalizer, $target, $pageSize, $filter);
        }

        return $this->runSingle($service, $normalizer, (int) $this->option('limit'), $filter);
    }

    private function buildODataFilter(): ?string
    {
        $clauses = [];

        $status = $this->option('status');
        if (!empty($status)) {
            $clauses[] = "StandardStatus eq '" . addslashes($status) . "'";
        }

        $propertyType = $this->option('property-type');
        if (!empty($propertyType)) {
            $clauses[] = "PropertyType eq '" . addslashes($propertyType) . "'";
        }

        if (empty($clauses)) {
            return null;
        }

        return implode(' and ', $clauses);
    }

    private function runSingle(BridgeApiService $service, BridgePropertyNormalizer $normalizer, int $limit, ?string $filter = null): int
    {
        $records = $service->fetchProperties($limit, $filter);

        if (empty($records)) {
            $this->warn('No records returned from Bridge API. Check logs for details.');
            return self::FAILURE;
        }

        $imported = 0;

        foreach ($records as $record) {
            if ($this->upsertRecord($normalizer, $record)) {
                $imported++;
            }
        }

        $this->info("Bridge import complete. Imported/updated: {$imported} record(s).");
        return self::SUCCESS;
    }

    private function runPaginated(BridgeApiService $service, BridgePropertyNormalizer $normalizer, int $target, int $pageSize, ?string $filter = null): int
    {
        $existingCount = DB::table('bridge_properties')->count();

        if ($existingCount >= $target) {
            $this->info("bridge_properties already contains {$existingCount} rows — target of {$target} is already met.");
            $this->info("Final bridge_properties row count: {$existingCount}");
            return self::SUCCESS;
        }

        $this->info("Starting paginated import — target: {$target} rows, page size: {$pageSize}");
        $this->info("Current bridge_properties row count: {$existingCount}");
        $this->newLine();

        $skip          = 0;
        $page          = 0;
        $totalUpserted = 0;

        while (true) {
            $page++;
            $this->info("Fetching page {$page} (skip={$skip}, top={$pageSize})...");

            try {
                $records = $service->fetchPropertiesPaginated($pageSize, $skip, $filter);
            } catch (\Throwable $e) {
                $this->error("Page {$page}: API error — " . $e->getMessage() . '. Stopping pagination.');
                break;
            }

            if (empty($records)) {
                $this->warn("Page {$page}: API returned 0 records — end of feed. Stopping.");
                break;
            }

            $pageImported = 0;
            foreach ($records as $record) {
                if ($this->upsertRecord($normalizer, $record)) {
                    $pageImported++;
                }
            }

            $totalUpserted += $pageImported;
            $currentCount   = DB::table('bridge_properties')->count();

            $this->info("  Page {$page} done — {$pageImported} upserted this page | {$currentCount} total rows in bridge_properties");

            if ($currentCount >= $target) {
                $this->info("  Target of {$target} rows reached.");
                break;
            }

            if (count($records) < $pageSize) {
                $this->warn("  Page {$page} returned fewer records than page size ({$pageSize}) — likely end of feed.");
                break;
            }

            $skip += $pageSize;
        }

        $finalCount = DB::table('bridge_properties')->count();
        $this->newLine();
        $this->info("Paginated import complete. Total upserted this run: {$totalUpserted}");
        $this->info("Final bridge_properties row count: {$finalCount}");

        if ($finalCount < $target) {
            $this->warn("Warning: Final row count ({$finalCount}) is below the target ({$target}). The feed may have fewer active records than expected.");
        }

        return self::SUCCESS;
    }

    private function upsertRecord(BridgePropertyNormalizer $normalizer, array $record): bool
    {
        $result = $normalizer->upsert($record);

        if ($result === null) {
            $this->line('  Skipped record: missing ListingKey.');
            return false;
        }

        if ($result->shouldDispatchDna()) {
            ComputeLocationDna::dispatch('bridge', $result->model->id);
            Log::info('ImportBridgeProperties: dispatched ComputeLocationDna', [
                'bridge_property_id' => $result->model->id,
                'listing_key'        => $result->model->listing_key,
                'reason'             => $result->isNew ? 'new_record' : 'address_changed',
            ]);
        }

        return true;
    }
}
