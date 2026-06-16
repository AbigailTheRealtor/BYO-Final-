<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;

class ImportBridgeProperties extends Command
{
    protected $signature = 'bridge:import-properties
                            {--limit=10 : Number of properties to fetch (single-page mode)}
                            {--target=0 : Import until this many total rows are in bridge_properties (paginated mode; 0 = disabled)}
                            {--page-size=200 : Records per API page in paginated mode}';

    protected $description = 'Fetch properties from the Bridge OData API and upsert into bridge_properties';

    public function handle(BridgeApiService $service): int
    {
        $target   = (int) $this->option('target');
        $pageSize = (int) $this->option('page-size');

        if ($target > 0) {
            return $this->runPaginated($service, $target, $pageSize);
        }

        return $this->runSingle($service, (int) $this->option('limit'));
    }

    private function runSingle(BridgeApiService $service, int $limit): int
    {
        $records = $service->fetchProperties($limit);

        if (empty($records)) {
            $this->warn('No records returned from Bridge API. Check logs for details.');
            return self::FAILURE;
        }

        $imported = 0;

        foreach ($records as $record) {
            if ($this->upsertRecord($record)) {
                $imported++;
            }
        }

        $this->info("Bridge import complete. Imported/updated: {$imported} record(s).");
        return self::SUCCESS;
    }

    private function runPaginated(BridgeApiService $service, int $target, int $pageSize): int
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

        $skip        = 0;
        $page        = 0;
        $totalUpserted = 0;

        while (true) {
            $page++;
            $this->info("Fetching page {$page} (skip={$skip}, top={$pageSize})...");

            $records = $service->fetchPropertiesPaginated($pageSize, $skip);

            if (empty($records)) {
                $this->warn("Page {$page}: API returned 0 records — end of feed or API error. Stopping.");
                break;
            }

            $pageImported = 0;
            foreach ($records as $record) {
                if ($this->upsertRecord($record)) {
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

    private function upsertRecord(array $record): bool
    {
        $listingKey = $record['ListingKey'] ?? null;

        if (empty($listingKey)) {
            Log::warning('ImportBridgeProperties: Skipping record missing ListingKey.', [
                'record_snippet' => array_slice($record, 0, 3),
            ]);
            $this->line('  Skipped record: missing ListingKey.');
            return false;
        }

        $modTs = isset($record['ModificationTimestamp'])
            ? \Carbon\Carbon::parse($record['ModificationTimestamp'])->toDateTimeString()
            : null;

        BridgeProperty::updateOrCreate(
            ['listing_key' => $listingKey],
            [
                'listing_id'              => $record['ListingId'] ?? null,
                'standard_status'         => $record['StandardStatus'] ?? null,
                'property_type'           => $record['PropertyType'] ?? null,
                'list_price'              => isset($record['ListPrice']) ? (float) $record['ListPrice'] : null,
                'unparsed_address'        => $record['UnparsedAddress'] ?? null,
                'city'                    => $record['City'] ?? null,
                'state_or_province'       => $record['StateOrProvince'] ?? null,
                'postal_code'             => $record['PostalCode'] ?? null,
                'bedrooms_total'          => isset($record['BedroomsTotal']) ? (int) $record['BedroomsTotal'] : null,
                'bathrooms_total_integer' => isset($record['BathroomsTotalInteger']) ? (int) $record['BathroomsTotalInteger'] : null,
                'living_area'             => isset($record['LivingArea']) ? (int) $record['LivingArea'] : null,
                'modification_timestamp'  => $modTs,
                'raw_json'                => json_encode($record),
                'imported_at'             => now(),
            ]
        );

        return true;
    }
}
