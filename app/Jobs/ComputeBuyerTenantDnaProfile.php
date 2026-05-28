<?php

namespace App\Jobs;

use App\Services\Dna\BuyerTenantDnaGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ComputeBuyerTenantDnaProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public string $listingType;
    public int $listingId;

    public function __construct(string $listingType, int $listingId)
    {
        $this->listingType = $listingType;
        $this->listingId   = $listingId;
    }

    public function handle(BuyerTenantDnaGenerator $generator): void
    {
        try {
            $generator->generate($this->listingType, $this->listingId);
        } catch (\Throwable $e) {
            Log::error('ComputeBuyerTenantDnaProfile job failed', [
                'listing_type' => $this->listingType,
                'listing_id'   => $this->listingId,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
