<?php

namespace App\Console\Commands;

use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use Illuminate\Console\Command;

class BackfillLinkedOfferAuction extends Command
{
    protected $signature = 'offer:backfill-linked-auction';

    protected $description = 'Create missing OfferAuction records and linked_offer_auction_id meta for all Seller Offer Listings that lack one.';

    public function handle(): int
    {
        $auctionIds = SellerAgentAuctionMeta::where('meta_key', 'workflow_type')
            ->where('meta_value', 'offer_listing')
            ->pluck('seller_agent_auction_id');

        $alreadyLinked = SellerAgentAuctionMeta::where('meta_key', 'linked_offer_auction_id')
            ->whereIn('seller_agent_auction_id', $auctionIds)
            ->pluck('seller_agent_auction_id')
            ->flip();

        $toProcess = $auctionIds->reject(fn ($id) => isset($alreadyLinked[$id]));

        if ($toProcess->isEmpty()) {
            $this->info('All Seller Offer Listings already have a linked_offer_auction_id. Nothing to do.');
            return 0;
        }

        $created = 0;

        foreach ($toProcess as $auctionId) {
            $auction = SellerAgentAuction::find($auctionId);
            if (!$auction) {
                $this->warn("SellerAgentAuction #{$auctionId} not found — skipping.");
                continue;
            }

            $offerAuction = OfferAuction::create(['user_id' => $auction->user_id]);
            $auction->saveMeta('linked_offer_auction_id', $offerAuction->id);

            $this->line("  Linked OfferAuction #{$offerAuction->id} → SellerAgentAuction #{$auctionId}");
            $created++;
        }

        $this->info("Done. Created and linked {$created} OfferAuction record(s).");

        return 0;
    }
}
