<?php

namespace App\Console\Commands;

use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

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

        if ($listingType === 'landlord') {
            if (! Schema::hasTable('landlord_auctions')) {
                $this->error('Landlord listing table not available in this environment.');
                return Command::FAILURE;
            }

            try {
                $listing = LandlordAuction::find($listingId);
            } catch (QueryException $e) {
                $this->error('Could not query landlord listing: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $listing = PropertyAuction::find($listingId);
        }

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
