<?php

namespace App\Console\Commands;

use App\Services\AskAi\AskAiFaqEnrichmentService;
use Illuminate\Console\Command;

class SyncFaqAnswers extends Command
{
    protected $signature = 'ask-ai:sync-faq-answers
                            {listing_type : Canonical listing type (seller, buyer, landlord, tenant)}
                            {listing_id : Primary key of the listing}';

    protected $description = 'Sync FAQ answers from the listing_ai_faq JSON blob into the ai_faq_answers table';

    public function __construct(private AskAiFaqEnrichmentService $enrichmentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $listingType = (string) $this->argument('listing_type');
        $listingId   = (int)    $this->argument('listing_id');

        $this->info("Syncing FAQ answers for {$listingType} #{$listingId} …");

        $result = $this->enrichmentService->sync($listingType, $listingId);

        if ($result['error'] !== null) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $syncedCount  = count($result['synced']);
        $skippedCount = count($result['skipped']);

        $this->info("Synced:  {$syncedCount} answer(s).");
        $this->info("Skipped: {$skippedCount} blank/empty answer(s).");

        if (!empty($result['synced'])) {
            $this->line('Keys synced: ' . implode(', ', $result['synced']));
        }

        if (!empty($result['skipped'])) {
            $this->line('Keys skipped: ' . implode(', ', $result['skipped']));
        }

        return self::SUCCESS;
    }
}
