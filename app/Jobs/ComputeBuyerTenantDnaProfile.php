<?php

namespace App\Jobs;

use App\Services\Dna\BuyerTenantDnaGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        // Queueable owns $queue; redeclaring it as a property with a default fatals on PHP 8.2.
        $this->queue = 'dna';
    }

    public function handle(BuyerTenantDnaGenerator $generator): void
    {
        $generator->generate($this->listingType, $this->listingId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ComputeBuyerTenantDnaProfile job permanently failed', [
            'job'          => self::class,
            'listing_type' => $this->listingType,
            'listing_id'   => $this->listingId,
            'error'        => $exception->getMessage(),
            'exception'    => get_class($exception),
        ]);
    }
}
