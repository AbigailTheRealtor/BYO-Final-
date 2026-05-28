<?php

namespace App\Jobs;

use App\Services\Dna\PropertyDnaGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ComputePropertyDnaProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $listingType;
    public int $listingId;

    public function __construct(string $listingType, int $listingId)
    {
        $this->listingType = $listingType;
        $this->listingId   = $listingId;
    }

    public function handle(PropertyDnaGenerator $generator): void
    {
        try {
            $generator->generate($this->listingType, $this->listingId);
        } catch (\Throwable $e) {
            Log::error('ComputePropertyDnaProfile job failed', [
                'listing_type' => $this->listingType,
                'listing_id'   => $this->listingId,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
