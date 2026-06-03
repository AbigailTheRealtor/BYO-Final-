<?php

namespace App\Console\Commands;

use App\Models\Offer;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Console\Command;

class ExpireOffersCommand extends Command
{
    protected $signature   = 'offers:expire-pending';
    protected $description = 'Expire submitted or countered offers whose expires_at is in the past';

    public function handle(OfferWorkflowFacade $facade): int
    {
        $offers = Offer::query()
            ->whereIn('status', ['submitted', 'countered'])
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($offers as $offer) {
            $result = $facade->expire($offer, null, 'system', ['source' => 'scheduled_command'], null);

            if ($result['allowed'] === true) {
                $count++;
            }
        }

        $this->line("Expired {$count} offer(s).");

        return 0;
    }
}
