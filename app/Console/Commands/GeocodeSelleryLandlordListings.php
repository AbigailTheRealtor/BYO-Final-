<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

            $outcome = $this->geocode($address, $apiKey);
            if ($outcome['status'] !== 'ok') {
                // Phase 0 item 1: an honest reason, never a silent null. "Address not on
                // the map" and "Google rejected our credential" are different facts and
                // must never be reported as the same FAILED line.
                $this->warn("  {$outcome['status']} [{$row->role}] #{$row->listing_id}: {$address}");
                $failed++;

                if ($outcome['status'] === 'credential_rejected') {
                    $this->error('  Google rejected the API key. Aborting: every remaining row would fail identically.');

                    return Command::FAILURE;
                }

                continue;
            }

            $result = $outcome['data'];
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

    /**
     * Geocode one address.
     *
     * Phase 0 item 1 — "an honest NOT_FOUND, never a silent null". This previously
     * answered `null` for four unrelated outcomes: address genuinely unknown, HTTP
     * error, malformed body, and a rejected credential. The operator saw one FAILED
     * line for all of them, which is precisely how a dead key looks like a bad address.
     *
     * @return array{status: string, data?: array}
     *         status is one of: ok · not_found · credential_rejected · http_error · transport_error
     */
    private function geocode(string $address, string $apiKey): array
    {
        try {
            // Phase 0 / erratum E-40: this was the one Google caller reaching the network
            // through the Http facade, which builds its own Guzzle client and never
            // consults the container — invisible to both GoogleOutboundTelemetryMiddleware
            // and the test-suite guards. Routed through the container binding instead.
            //
            // http_errors => false preserves the facade's semantics: Http::get() returns a
            // Response on 4xx/5xx rather than throwing.
            $client = app(\GuzzleHttp\ClientInterface::class);

            $response = $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address'    => $address,
                    'components' => 'country:US',
                    'key'        => $apiKey,
                ],
                'timeout'     => 8,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) return ['status' => 'http_error'];

            $data = json_decode((string) $response->getBody(), true) ?: [];

            // Google answers a blank, invalid, or revoked key with HTTP 200 and
            // REQUEST_DENIED. Status alone cannot distinguish it from a healthy miss.
            $googleStatus = $data['status'] ?? '';
            if (in_array($googleStatus, ['REQUEST_DENIED', 'OVER_QUERY_LIMIT'], true)) {
                return ['status' => 'credential_rejected'];
            }

            if ($googleStatus === 'ZERO_RESULTS' || empty($data['results'])) {
                return ['status' => 'not_found'];
            }

            if ($googleStatus !== 'OK') return ['status' => 'http_error'];

            $result = $data['results'][0];

            return [
                'status' => 'ok',
                'data'   => [
                    'property_lat'      => (string) ($result['geometry']['location']['lat'] ?? ''),
                    'property_lng'      => (string) ($result['geometry']['location']['lng'] ?? ''),
                    'google_place_id'   => $result['place_id'] ?? '',
                    'formatted_address' => $result['formatted_address'] ?? '',
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'transport_error'];
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
