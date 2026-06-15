<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;

class ImportBridgeProperties extends Command
{
    protected $signature = 'bridge:import-properties {--limit=10 : Number of properties to fetch}';

    protected $description = 'Fetch properties from the Bridge OData API and upsert into bridge_properties';

    public function handle(BridgeApiService $service): int
    {
        $limit   = (int) $this->option('limit');
        $records = $service->fetchProperties($limit);

        if (empty($records)) {
            $this->warn('No records returned from Bridge API. Check logs for details.');
            return self::FAILURE;
        }

        $imported = 0;

        foreach ($records as $record) {
            $listingKey = $record['ListingKey'] ?? null;

            if (empty($listingKey)) {
                Log::warning('ImportBridgeProperties: Skipping record missing ListingKey.', [
                    'record_snippet' => array_slice($record, 0, 3),
                ]);
                $this->line('  Skipped record: missing ListingKey.');
                continue;
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

            $imported++;
        }

        $this->info("Bridge import complete. Imported/updated: {$imported} record(s).");
        return self::SUCCESS;
    }
}
