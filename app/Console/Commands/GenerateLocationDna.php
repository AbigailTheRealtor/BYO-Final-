<?php

namespace App\Console\Commands;

use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Console\Command;

class GenerateLocationDna extends Command
{
    protected $signature = 'location-dna:generate {listing_type} {listing_id}';

    protected $description = 'Run the full Location DNA pipeline for a single seller or landlord listing';

    public function handle(LocationDnaPipelineRunner $runner): int
    {
        $listingType = $this->argument('listing_type');
        $listingId   = (int) $this->argument('listing_id');

        if (! in_array($listingType, ['seller', 'landlord'], true)) {
            $this->error("Invalid listing_type '{$listingType}'. Must be 'seller' or 'landlord'.");
            return Command::FAILURE;
        }

        $listing = match ($listingType) {
            'seller'   => PropertyAuction::find($listingId),
            'landlord' => LandlordAuction::find($listingId),
        };

        if ($listing === null) {
            $this->error("No {$listingType} listing found with ID {$listingId}.");
            return Command::FAILURE;
        }

        $this->info("Running Location DNA pipeline for {$listingType} listing #{$listingId}...");

        $result = $runner->run($listingType, $listingId);

        $this->line('Pipeline status: ' . $result['status']);

        foreach ($result['steps'] ?? [] as $step => $stepResult) {
            $status = $stepResult['status'] ?? 'unknown';
            $error  = $stepResult['error'] ?? null;
            $line   = "  [{$step}] {$status}";
            if ($error) {
                $line .= " — {$error}";
            }
            $this->line($line);
        }

        if (isset($result['error'])) {
            $this->error('Pipeline exception: ' . $result['error']);
        }

        return $result['status'] === 'success' ? Command::SUCCESS : Command::FAILURE;
    }
}
