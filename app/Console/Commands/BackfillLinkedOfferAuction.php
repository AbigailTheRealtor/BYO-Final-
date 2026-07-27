<?php

namespace App\Console\Commands;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Services\Offers\ListingOfferAuctionLinker;
use Illuminate\Console\Command;

/**
 * Creates the missing OfferAuction + linked_offer_auction_id meta for Offer
 * Listings that lack one.
 *
 * Landlord coverage exists because the Landlord public view used to create the
 * link as a side effect of an unauthenticated GET. That write was removed —
 * public pages are read-only — so listings published before publish-time linking
 * existed need this command to get their link. Without it their offer /
 * application form posts an empty offer_auction_id.
 */
class BackfillLinkedOfferAuction extends Command
{
    protected $signature = 'offer:backfill-linked-auction
                            {--role=all : Which listings to process — seller, landlord, or all}
                            {--dry-run : Report what would be linked without writing}';

    protected $description = 'Create missing OfferAuction records and linked_offer_auction_id meta for Seller and Landlord Offer Listings that lack one.';

    public function handle(): int
    {
        $role   = strtolower((string) $this->option('role'));
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($role, ['all', 'seller', 'landlord'], true)) {
            $this->error("Unknown --role '{$role}'. Use seller, landlord, or all.");
            return 1;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no records will be written.');
        }

        $total = 0;

        if ($role === 'all' || $role === 'seller') {
            $total += $this->backfillSeller($dryRun);
        }

        if ($role === 'all' || $role === 'landlord') {
            $total += $this->backfillLandlord($dryRun);
        }

        $this->info($dryRun
            ? "Done. {$total} listing(s) would be linked."
            : "Done. Created and linked {$total} OfferAuction record(s).");

        return 0;
    }

    private function backfillSeller(bool $dryRun): int
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
            $this->info('Seller: all Offer Listings already linked. Nothing to do.');
            return 0;
        }

        return $this->link($toProcess, 'seller', $dryRun, fn ($id) => SellerAgentAuction::with('meta')->find($id));
    }

    private function backfillLandlord(bool $dryRun): int
    {
        $auctionIds = LandlordAgentAuctionMeta::where('meta_key', 'workflow_type')
            ->where('meta_value', 'offer_listing')
            ->pluck('landlord_agent_auction_id');

        $alreadyLinked = LandlordAgentAuctionMeta::where('meta_key', 'linked_offer_auction_id')
            ->whereIn('landlord_agent_auction_id', $auctionIds)
            ->pluck('landlord_agent_auction_id')
            ->flip();

        $toProcess = $auctionIds->reject(fn ($id) => isset($alreadyLinked[$id]));

        if ($toProcess->isEmpty()) {
            $this->info('Landlord: all Offer Listings already linked. Nothing to do.');
            return 0;
        }

        return $this->link($toProcess, 'landlord', $dryRun, fn ($id) => LandlordAgentAuction::with('meta')->find($id));
    }

    /**
     * @param  \Illuminate\Support\Collection  $ids
     * @param  callable(int): ?\Illuminate\Database\Eloquent\Model  $find
     */
    private function link($ids, string $role, bool $dryRun, callable $find): int
    {
        $linker = app(ListingOfferAuctionLinker::class);
        $count  = 0;

        foreach ($ids as $id) {
            $listing = $find($id);

            if (! $listing) {
                $this->warn("  {$role} listing #{$id} not found — skipping.");
                continue;
            }

            if ($dryRun) {
                $this->line("  would link {$role} listing #{$id}");
                $count++;
                continue;
            }

            $offerAuction = $linker->ensureFor($listing, $role);

            $this->line("  Linked OfferAuction #{$offerAuction->id} → {$role} listing #{$id}");
            $count++;
        }

        return $count;
    }
}
