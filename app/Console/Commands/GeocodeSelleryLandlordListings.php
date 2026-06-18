<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GeocodeSelleryLandlordListings extends Command
{
    protected $signature = 'app:geocode-seller-landlord-listings
                            {--dry-run : Show what would be updated without saving}
                            {--limit=50 : Max records to process per run}';

    protected $description = 'Backfill property_lat/property_lng/google_place_id/formatted_address for Seller and Landlord listings that have an address but no coordinates.';

    public function handle(): int
    {
        $apiKey  = config('services.google.places_key', '');
        $dryRun  = $this->option('dry-run');
        $limit   = (int) $this->option('limit');

        if (!$apiKey) {
            $this->error('GOOGLE_PLACES_API_KEY is not configured. Cannot geocode.');
            return Command::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($this->candidateRows($limit) as $row) {
            $address = trim($row->address ?? '');
            if (!$address) {
                $skipped++;
                continue;
            }

            // Skip if already has coordinates
            if (!empty($row->property_lat) && !empty($row->property_lng)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY-RUN] Would geocode [{$row->role}] #{$row->listing_id}: {$address}");
                $updated++;
                continue;
            }

            $result = $this->geocode($address, $apiKey);
            if (!$result) {
                $this->warn("  FAILED [{$row->role}] #{$row->listing_id}: {$address}");
                $failed++;
                continue;
            }

            $this->saveMeta($row->meta_table, $row->listing_id, $result);
            $this->line("  OK [{$row->role}] #{$row->listing_id}: {$result['formatted_address']}");
            $updated++;
        }

        $this->info("Done — updated: {$updated}, skipped: {$skipped}, failed: {$failed}");
        return Command::SUCCESS;
    }

    private function candidateRows(int $limit): array
    {
        $rows = [];

        // Seller listings
        $sellers = DB::select("
            SELECT pa.id AS listing_id,
                   addr_meta.meta_value AS address,
                   'seller' AS role,
                   'property_auction_metas' AS meta_table
            FROM property_auctions pa
            JOIN property_auction_metas addr_meta
                ON addr_meta.property_auction_id = pa.id
                AND addr_meta.meta_key = 'address'
                AND addr_meta.meta_value IS NOT NULL
                AND addr_meta.meta_value != ''
            LEFT JOIN property_auction_metas lat_meta
                ON lat_meta.property_auction_id = pa.id
                AND lat_meta.meta_key = 'property_lat'
                AND lat_meta.meta_value IS NOT NULL
                AND lat_meta.meta_value != ''
            WHERE lat_meta.id IS NULL
            LIMIT :lim
        ", ['lim' => $limit]);

        foreach ($sellers as $r) $rows[] = $r;

        // Landlord listings (table may not exist in all environments)
        try {
            $landlords = DB::select("
                SELECT la.id AS listing_id,
                       addr_meta.meta_value AS address,
                       'landlord' AS role,
                       'landlord_auction_metas' AS meta_table
                FROM landlord_auctions la
                JOIN landlord_auction_metas addr_meta
                    ON addr_meta.landlord_auction_id = la.id
                    AND addr_meta.meta_key = 'address'
                    AND addr_meta.meta_value IS NOT NULL
                    AND addr_meta.meta_value != ''
                LEFT JOIN landlord_auction_metas lat_meta
                    ON lat_meta.landlord_auction_id = la.id
                    AND lat_meta.meta_key = 'property_lat'
                    AND lat_meta.meta_value IS NOT NULL
                    AND lat_meta.meta_value != ''
                WHERE lat_meta.id IS NULL
                LIMIT :lim
            ", ['lim' => $limit]);
        } catch (\Exception $e) {
            $landlords = [];
        }

        foreach ($landlords as $r) $rows[] = $r;

        return $rows;
    }

    private function geocode(string $address, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address'    => $address,
                'components' => 'country:US',
                'key'        => $apiKey,
            ]);

            if (!$response->ok()) return null;

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) return null;

            $result = $data['results'][0];
            return [
                'property_lat'      => (string) ($result['geometry']['location']['lat'] ?? ''),
                'property_lng'      => (string) ($result['geometry']['location']['lng'] ?? ''),
                'google_place_id'   => $result['place_id'] ?? '',
                'formatted_address' => $result['formatted_address'] ?? '',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function saveMeta(string $table, int $listingId, array $coords): void
    {
        $fkCol = $table === 'property_auction_metas' ? 'property_auction_id' : 'landlord_auction_id';

        foreach ($coords as $key => $value) {
            $exists = DB::table($table)
                ->where($fkCol, $listingId)
                ->where('meta_key', $key)
                ->exists();

            if ($exists) {
                DB::table($table)
                    ->where($fkCol, $listingId)
                    ->where('meta_key', $key)
                    ->update(['meta_value' => $value, 'updated_at' => now()]);
            } else {
                DB::table($table)->insert([
                    $fkCol       => $listingId,
                    'meta_key'   => $key,
                    'meta_value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
