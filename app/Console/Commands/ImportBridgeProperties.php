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
                            {--page-size=200 : Records per API page in paginated mode}
                            {--status= : OData StandardStatus filter (e.g. Active)}
                            {--property-type= : OData PropertyType filter (e.g. Residential)}';

    protected $description = 'Fetch properties from the Bridge OData API and upsert into bridge_properties';

    public function handle(BridgeApiService $service): int
    {
        $target   = (int) $this->option('target');
        $pageSize = (int) $this->option('page-size');

        $filter = $this->buildODataFilter();

        if ($target > 0) {
            return $this->runPaginated($service, $target, $pageSize, $filter);
        }

        return $this->runSingle($service, (int) $this->option('limit'), $filter);
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

    private function runSingle(BridgeApiService $service, int $limit, ?string $filter = null): int
    {
        $records = $service->fetchProperties($limit, $filter);

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

    private function runPaginated(BridgeApiService $service, int $target, int $pageSize, ?string $filter = null): int
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

            $records = $service->fetchPropertiesPaginated($pageSize, $skip, $filter);

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

        // PetsAllowed normalisation — source field is an array in the Stellar API (e.g. ["Yes"], ["Dogs","Cats"]).
        // Store only the first element as the canonical VARCHAR(50) value.
        $petsRaw     = $record['PetsAllowed'] ?? null;
        $petsAllowed = is_array($petsRaw) ? ($petsRaw[0] ?? null) : (is_string($petsRaw) ? $petsRaw : null);

        BridgeProperty::updateOrCreate(
            ['listing_key' => $listingKey],
            [
                // Existing columns
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

                // --- Phase 1 native column promotions ---

                // Geospatial
                'latitude'             => isset($record['Latitude']) ? (float) $record['Latitude'] : null,
                'longitude'            => isset($record['Longitude']) ? (float) $record['Longitude'] : null,

                // Location
                'county_or_parish'     => $record['CountyOrParish'] ?? null,

                // Property classification
                'property_sub_type'    => $record['PropertySubType'] ?? null,
                'mls_status'           => $record['MlsStatus'] ?? null,

                // Age
                'year_built'           => isset($record['YearBuilt']) ? (int) $record['YearBuilt'] : null,

                // Financial
                'association_fee'      => isset($record['AssociationFee']) ? (float) $record['AssociationFee'] : null,
                'tax_annual_amount'    => isset($record['TaxAnnualAmount']) ? (float) $record['TaxAnnualAmount'] : null,

                // Size — use !== null guard to avoid PHP zero-falsy gotcha on string "0" values
                'lot_size_sqft'        => ($v = $record['LotSizeSquareFeet'] ?? null) !== null ? (int) $v : null,

                // Rental qualifiers
                'pets_allowed'         => $petsAllowed,
                // NOTE: 'furnished' is EXCLUDED from Phase 1 (blocked at Phase 0 — 35% population rate).
                //       Do not add it here. It is promoted in Phase 2R once the rental feed is active.

                // Boolean feature flags — array_key_exists ensures a missing key stores NULL (not false).
                // filter_var handles both native PHP bool and edge-case string representations ("true"/"false").
                'senior_community_yn'  => $this->filterBool($record, 'SeniorCommunityYN'),
                'garage_yn'            => $this->filterBool($record, 'GarageYN'),
                'pool_private_yn'      => $this->filterBool($record, 'PoolPrivateYN'),
                'waterfront_yn'        => $this->filterBool($record, 'WaterfrontYN'),
                'association_yn'       => $this->filterBool($record, 'AssociationYN'),
                'new_construction_yn'  => $this->filterBool($record, 'NewConstructionYN'),
                'view_yn'              => $this->filterBool($record, 'ViewYN'),
                'water_view_yn'        => $this->filterBool($record, 'STELLAR_WaterViewYN'),
                'cdd_yn'               => $this->filterBool($record, 'STELLAR_CDDYN'),
            ]
        );

        return true;
    }

    /**
     * Return true/false for a known boolean API field, or null if the key is absent.
     * Uses array_key_exists so a missing key → null (not false), matching nullable column semantics.
     */
    private function filterBool(array $record, string $key): ?bool
    {
        if (!array_key_exists($key, $record)) {
            return null;
        }
        return filter_var($record[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
