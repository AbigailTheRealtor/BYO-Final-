<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\BridgeProperty;

class BackfillNativeColumns extends Command
{
    protected $signature = 'bridge:backfill-native-columns
                            {--force : Reprocess all rows, even those already filled (default: skip rows where latitude IS NOT NULL)}';

    protected $description = 'Backfill Phase 1 native columns on bridge_properties from raw_json';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force) {
            $this->info('Running in --force mode: all rows will be reprocessed.');
        } else {
            $this->info('Running in default mode: skipping rows where latitude IS NOT NULL (already backfilled).');
        }

        $this->newLine();

        $updated  = 0;
        $errors   = 0;
        $processed = 0;

        $query = $force
            ? BridgeProperty::query()
            : BridgeProperty::whereNull('latitude');

        $total   = $query->count();
        $skipped = $force ? 0 : (BridgeProperty::whereNotNull('latitude')->count());

        $this->info("Total rows to process: {$total}");
        if (!$force) {
            $this->info("Rows skipped (latitude already filled): {$skipped}");
        }
        $this->newLine();

        $query->chunkById(1000, function ($rows) use (&$updated, &$skipped, &$errors, &$processed, $total) {
            foreach ($rows as $row) {
                $processed++;

                if (empty($row->raw_json)) {
                    Log::warning('bridge:backfill-native-columns: empty raw_json', [
                        'listing_key' => $row->listing_key,
                    ]);
                    $errors++;
                    continue;
                }

                $data = json_decode($row->raw_json, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('bridge:backfill-native-columns: malformed JSON', [
                        'listing_key'   => $row->listing_key,
                        'json_error'    => json_last_error_msg(),
                    ]);
                    $errors++;
                    continue;
                }

                $native = $this->extractNativeFields($data, $row->listing_key);

                try {
                    $row->update($native);
                    $updated++;
                } catch (\Throwable $e) {
                    Log::warning('bridge:backfill-native-columns: update failed', [
                        'listing_key' => $row->listing_key,
                        'error'       => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }

            if ($processed % 1000 === 0 || $processed === $total) {
                $this->info("Processed {$processed} / {$total} rows...");
            }
        });

        $this->newLine();
        $this->info("Backfill complete. Updated: {$updated}. Skipped (already filled): {$skipped}. Errors: {$errors}.");

        return self::SUCCESS;
    }

    /**
     * Extract the 19 Phase 1 native fields from a decoded raw_json array.
     * Applies the same coercion rules as ImportBridgeProperties::upsertRecord().
     * Logs unexpected types but never throws — always returns what it can.
     *
     * NOTE: 'furnished' is EXCLUDED from Phase 1 (blocked at Phase 0 — 35% population rate).
     *       Do not add it here. It is promoted in Phase 2R once the rental feed is active.
     */
    private function extractNativeFields(array $data, ?string $listingKey): array
    {
        $fields = [];

        // Geospatial
        $fields['latitude']  = $this->extractFloat($data, 'Latitude', $listingKey);
        $fields['longitude'] = $this->extractFloat($data, 'Longitude', $listingKey);

        // Location
        $fields['county_or_parish'] = $data['CountyOrParish'] ?? null;

        // Property classification
        $fields['property_sub_type'] = $data['PropertySubType'] ?? null;
        $fields['mls_status']        = $data['MlsStatus'] ?? null;

        // Age
        $fields['year_built'] = $this->extractInt($data, 'YearBuilt', $listingKey);

        // Financial
        $fields['association_fee']   = $this->extractFloat($data, 'AssociationFee', $listingKey);
        $fields['tax_annual_amount'] = $this->extractFloat($data, 'TaxAnnualAmount', $listingKey);

        // Size — use !== null guard to avoid PHP zero-falsy gotcha on string "0" values
        $lotRaw = $data['LotSizeSquareFeet'] ?? null;
        if ($lotRaw !== null && !is_numeric($lotRaw)) {
            Log::warning('bridge:backfill-native-columns: non-numeric value for int field', [
                'listing_key' => $listingKey,
                'field'       => 'LotSizeSquareFeet',
                'type'        => gettype($lotRaw),
            ]);
            $lotRaw = null;
        }
        $fields['lot_size_sqft'] = $lotRaw !== null ? (int) $lotRaw : null;

        // Rental qualifiers
        // PetsAllowed is an array in the Stellar API — store only the first element
        $petsRaw = $data['PetsAllowed'] ?? null;
        if (is_array($petsRaw)) {
            $fields['pets_allowed'] = $petsRaw[0] ?? null;
        } elseif (is_string($petsRaw)) {
            $fields['pets_allowed'] = $petsRaw;
        } else {
            $fields['pets_allowed'] = null;
        }

        // Boolean feature flags
        $fields['senior_community_yn'] = $this->extractBool($data, 'SeniorCommunityYN', $listingKey);
        $fields['garage_yn']           = $this->extractBool($data, 'GarageYN', $listingKey);
        $fields['pool_private_yn']     = $this->extractBool($data, 'PoolPrivateYN', $listingKey);
        $fields['waterfront_yn']       = $this->extractBool($data, 'WaterfrontYN', $listingKey);
        $fields['association_yn']      = $this->extractBool($data, 'AssociationYN', $listingKey);
        $fields['new_construction_yn'] = $this->extractBool($data, 'NewConstructionYN', $listingKey);
        $fields['view_yn']             = $this->extractBool($data, 'ViewYN', $listingKey);
        $fields['water_view_yn']       = $this->extractBool($data, 'STELLAR_WaterViewYN', $listingKey);
        $fields['cdd_yn']              = $this->extractBool($data, 'STELLAR_CDDYN', $listingKey);

        return $fields;
    }

    private function extractFloat(array $data, string $key, ?string $listingKey): ?float
    {
        if (!isset($data[$key])) {
            return null;
        }
        $v = $data[$key];
        if (!is_numeric($v)) {
            Log::warning('bridge:backfill-native-columns: non-numeric value for float field', [
                'listing_key' => $listingKey,
                'field'       => $key,
                'type'        => gettype($v),
            ]);
            return null;
        }
        return (float) $v;
    }

    private function extractInt(array $data, string $key, ?string $listingKey): ?int
    {
        if (!isset($data[$key])) {
            return null;
        }
        $v = $data[$key];
        if (!is_numeric($v)) {
            Log::warning('bridge:backfill-native-columns: non-numeric value for int field', [
                'listing_key' => $listingKey,
                'field'       => $key,
                'type'        => gettype($v),
            ]);
            return null;
        }
        return (int) $v;
    }

    private function extractBool(array $data, string $key, ?string $listingKey): ?bool
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }
        $v = $data[$key];
        if (is_array($v)) {
            Log::warning('bridge:backfill-native-columns: unexpected type (array) for bool field', [
                'listing_key' => $listingKey,
                'field'       => $key,
                'type'        => gettype($v),
            ]);
            return null;
        }
        $result = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($result === null && $v !== null) {
            Log::warning('bridge:backfill-native-columns: unexpected type for bool field', [
                'listing_key' => $listingKey,
                'field'       => $key,
                'type'        => gettype($v),
            ]);
        }
        return $result;
    }
}
