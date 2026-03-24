<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * If a Bidding Period listing's timer has expired and it has not been hired,
     * automatically set listing_status meta to 'Pending' so the view reflects it.
     */
    protected function autoTransitionBpToPending($auction): void
    {
        if (!$auction) {
            return;
        }
        $isSold = in_array($auction->is_sold, [true, 'true', 1, '1'], true);
        if ($isSold) {
            return;
        }
        $auctionType = strtolower(trim($auction->info('auction_type') ?? ''));
        $isBiddingPeriod = in_array($auctionType, ['bidding period', 'auction (timer)']);
        if (!$isBiddingPeriod) {
            return;
        }
        $auctionTime = trim($auction->info('auction_time') ?? '');
        if (empty($auctionTime) || strtolower($auctionTime) === 'null') {
            return;
        }
        $parts = preg_split('/\s+/', $auctionTime, 2);
        $durVal = (int)($parts[0] ?? 0);
        $durUnit = strtolower($parts[1] ?? 'days');
        if ($durVal <= 0) {
            return;
        }
        $startTime = $auction->created_at ?? now();
        $expiration = match($durUnit) {
            'hour', 'hours' => \Carbon\Carbon::parse($startTime)->addHours($durVal),
            'week', 'weeks' => \Carbon\Carbon::parse($startTime)->addWeeks($durVal),
            'minute', 'minutes' => \Carbon\Carbon::parse($startTime)->addMinutes($durVal),
            default => \Carbon\Carbon::parse($startTime)->addDays($durVal),
        };
        if (!\Carbon\Carbon::now()->gte($expiration)) {
            return;
        }
        $currentStatus = $auction->info('listing_status');
        if ($currentStatus !== 'Pending' && $currentStatus !== 'Hired Agent') {
            $auction->saveMeta('listing_status', 'Pending');
            $auction->unsetRelation('meta');
        }
    }
}
