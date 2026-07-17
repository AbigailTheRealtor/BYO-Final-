<?php

namespace App\Console\Commands;

use App\Models\Offer;
use App\Notifications\Offers\OfferExpiredNotification;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            // Each expiry is its own atomic, row-locked unit (BLK-06): the offer is
            // re-read under lockForUpdate and re-validated against committed state
            // before the transition, so a concurrent accept/withdraw already in
            // flight is respected. Notifications are dispatched only after commit,
            // never while the row lock is held.
            $expired = DB::transaction(function () use ($facade, $offer) {
                $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->first();

                if ($locked === null) {
                    return null;
                }

                // Re-check eligibility after acquiring the lock — the offer may have
                // been accepted, rejected, withdrawn, or had its deadline extended
                // between the initial query and this lock.
                if (! in_array($locked->status, ['submitted', 'countered'], true)) {
                    return null;
                }

                if ($locked->expires_at === null || now()->lessThan($locked->expires_at)) {
                    return null;
                }

                $result = $facade->expire($locked, null, 'system', ['source' => 'scheduled_command'], null);

                return ($result['allowed'] ?? false) === true ? $locked : null;
            });

            if ($expired !== null) {
                $count++;
                $expired->user->notify(new OfferExpiredNotification($expired));
            }
        }

        $this->line("Expired {$count} offer(s).");

        return 0;
    }
}
