<?php

namespace App\Console\Commands;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillLocationSnapshots extends Command
{
    protected $signature   = 'offers:backfill-location-snapshots
                                {--dry-run : Print what would change without writing}';
    protected $description = 'Backfill location columns on accepted_bid_summaries from the associated listing meta.';

    private int $filled  = 0;
    private int $skipped = 0;
    private int $failed  = 0;

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[dry-run] No writes will be performed.');
        }

        $this->info('Scanning accepted_bid_summaries with null property_lat…');

        AcceptedBidSummary::whereNull('property_lat')
            ->chunkById(100, function ($summaries) use ($isDryRun) {
                foreach ($summaries as $summary) {
                    $this->processSummary($summary, $isDryRun);
                }
            });

        $this->info("Done. Filled: {$this->filled}  Skipped: {$this->skipped}  Failed: {$this->failed}");

        return self::SUCCESS;
    }

    private function processSummary(AcceptedBidSummary $summary, bool $isDryRun): void
    {
        try {
            $locationData = $this->resolveLocationData($summary);

            if (empty($locationData)) {
                $this->skipped++;
                return;
            }

            if ($isDryRun) {
                $this->line("  [dry-run] id={$summary->id} type={$summary->listing_type} listing_id={$summary->listing_id}");
                $this->filled++;
                return;
            }

            $summary->fill($locationData)->save();
            $this->filled++;

            Log::info('BackfillLocationSnapshots: filled', [
                'summary_id'  => $summary->id,
                'listing_type' => $summary->listing_type,
                'listing_id'  => $summary->listing_id,
            ]);
        } catch (\Throwable $e) {
            $this->failed++;
            Log::warning('BackfillLocationSnapshots: failed', [
                'summary_id' => $summary->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function resolveLocationData(AcceptedBidSummary $summary): array
    {
        $type = $summary->listing_type;
        $id   = $summary->listing_id;

        if (!$type || !$id) {
            return [];
        }

        try {
            switch ($type) {
                case 'seller':
                    $listing = SellerAgentAuction::find($id);
                    return $listing ? $this->extractPropertyLocationFromListing($listing->get) : [];

                case 'landlord':
                    $listing = LandlordAgentAuction::find($id);
                    return $listing ? $this->extractPropertyLocationFromListing($listing->get) : [];

                case 'buyer':
                    $listing = BuyerAgentAuction::find($id);
                    return $listing ? $this->extractLocationIntelligenceFromListing($listing->get) : [];

                case 'tenant':
                    $listing = TenantAgentAuction::find($id);
                    return $listing ? $this->extractLocationIntelligenceFromListing($listing->get) : [];

                default:
                    return [];
            }
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function extractPropertyLocationFromListing($listingData): array
    {
        $lat = data_get($listingData, 'property_lat');
        $lng = data_get($listingData, 'property_lng');

        return array_filter([
            'property_address'  => data_get($listingData, 'address') ?: data_get($listingData, 'street_address') ?: null,
            'property_city'     => data_get($listingData, 'property_city') ?: null,
            'property_county'   => data_get($listingData, 'property_county') ?: null,
            'property_state'    => data_get($listingData, 'property_state') ?: null,
            'property_zip'      => data_get($listingData, 'property_zip') ?: data_get($listingData, 'zip_code') ?: null,
            'property_lat'      => ($lat !== null && $lat !== '') ? (float) $lat : null,
            'property_lng'      => ($lng !== null && $lng !== '') ? (float) $lng : null,
            'google_place_id'   => data_get($listingData, 'google_place_id') ?: null,
            'legal_description' => data_get($listingData, 'legal_description') ?: null,
            'parcel_id'         => data_get($listingData, 'parcel_id') ?: null,
        ], fn($v) => $v !== null);
    }

    private function extractLocationIntelligenceFromListing($listingData): array
    {
        $raw = data_get($listingData, 'location_dna_preferences');

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        return ['location_intelligence_snapshot' => $decoded];
    }
}
