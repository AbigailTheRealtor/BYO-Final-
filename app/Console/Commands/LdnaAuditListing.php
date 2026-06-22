<?php

namespace App\Console\Commands;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationDnaAudit;
use App\Models\PropertyLocationPoi;
use Illuminate\Console\Command;

/**
 * ldna:audit-listing — dev-only diagnostic dump
 *
 * Reads the stored PropertyLocationDna record, all PropertyLocationPoi rows
 * (all candidates, all ranks, grouped by category), and the latest
 * PropertyLocationDnaAudit entry for a given listing and prints them as
 * structured JSON to stdout.
 *
 * This command is read-only and never triggers the pipeline or any API call.
 * It is intended for internal auditing and is not accessible via any route.
 *
 * v2 update (task #3110): POI rows are now grouped by poi_category and ordered
 * by rank within each group, so all stored candidates per category are visible.
 *
 * Usage:
 *   php artisan ldna:audit-listing {listingId}
 *   php artisan ldna:audit-listing {listingId} --listing-type=seller_agent
 *
 * If --listing-type is omitted, all property_location_dna rows matching the
 * given listing_id are included (useful when the same numeric ID appears in
 * multiple listing types).
 *
 * Example:
 *   php artisan ldna:audit-listing 183
 *   php artisan ldna:audit-listing 183 --listing-type=seller_agent
 */
class LdnaAuditListing extends Command
{
    protected $signature = 'ldna:audit-listing
        {listingId              : The listing primary key to audit}
        {--listing-type=        : Optional listing_type filter (e.g. seller_agent, landlord_agent, seller, landlord). Omit to return all types for this ID.}';

    protected $description = '[DEV-ONLY] Dump raw Location DNA payload (DNA record, all POI candidates by rank, latest audit entry) for one listing as JSON';

    public function handle(): int
    {
        $listingId   = (int) $this->argument('listingId');
        $listingType = $this->option('listing-type') ?: null;

        // --- DNA record(s) ---
        $dnaQuery = PropertyLocationDna::where('listing_id', $listingId);
        if ($listingType !== null) {
            $dnaQuery->where('listing_type', $listingType);
        }
        $dnaRecords = $dnaQuery->get();

        if ($dnaRecords->isEmpty()) {
            $filter = $listingType ? "listing_type={$listingType} listing_id={$listingId}" : "listing_id={$listingId}";
            $this->error("No PropertyLocationDna record found for {$filter}");
            return Command::FAILURE;
        }

        $payload = [];

        foreach ($dnaRecords as $dnaRecord) {
            $type = $dnaRecord->listing_type;

            // --- POI rows: all candidates ordered by category then rank ---
            $pois = PropertyLocationPoi::where('listing_type', $type)
                ->where('listing_id', $listingId)
                ->orderBy('poi_category')
                ->orderBy('rank')
                ->get();

            // Group by category so all ranked candidates are visible per category
            $poisByCategoryRaw = [];
            foreach ($pois as $poi) {
                $cat = $poi->poi_category;
                if (! isset($poisByCategoryRaw[$cat])) {
                    $poisByCategoryRaw[$cat] = [];
                }
                $poisByCategoryRaw[$cat][] = $poi->toArray();
            }

            // Build summary per category (count, statuses)
            $categoryStats = [];
            foreach ($poisByCategoryRaw as $cat => $catPois) {
                $categoryStats[$cat] = [
                    'candidate_count' => count($catPois),
                    'statuses'        => array_count_values(array_column($catPois, 'status')),
                    'has_rank_1'      => count(array_filter($catPois, fn($p) => ($p['rank'] ?? null) == 1)) > 0,
                ];
            }

            // --- Latest audit entry (one row) ---
            $latestAudit = PropertyLocationDnaAudit::where('listing_type', $type)
                ->where('listing_id', $listingId)
                ->orderByDesc('created_at')
                ->first();

            $payload[] = [
                'meta' => [
                    'command'               => 'ldna:audit-listing',
                    'listing_type'          => $type,
                    'listing_id'            => $listingId,
                    'generated_at'          => now()->toIso8601String(),
                    'total_poi_rows'        => $pois->count(),
                    'total_poi_categories'  => count($poisByCategoryRaw),
                ],
                'property_location_dna'             => $dnaRecord->toArray(),
                'property_location_pois_by_category' => [
                    'category_count'  => count($poisByCategoryRaw),
                    'category_stats'  => $categoryStats,
                    'categories'      => $poisByCategoryRaw,
                ],
                'latest_property_location_dna_audit' => $latestAudit?->toArray(),
            ];
        }

        // Unwrap single-record output to a plain object (not an array wrapper)
        $output = count($payload) === 1 ? $payload[0] : $payload;

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
