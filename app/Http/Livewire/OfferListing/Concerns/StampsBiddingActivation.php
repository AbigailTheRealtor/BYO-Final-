<?php

namespace App\Http\Livewire\OfferListing\Concerns;

use App\Models\Offer;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\ListingOfferAuctionLinker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Publish-time activation: links the listing's OfferAuction and, for Bidding
 * Period listings, stamps the canonical bidding start.
 *
 * Call stampBiddingActivation() from the PUBLISH path only — never from
 * saveDraft(). Bidding begins when the listing first becomes Active, so a draft
 * save must not start the clock.
 *
 * The call is safe to repeat: the linker returns an existing link untouched and
 * BiddingWindowService::markActivated() refuses to overwrite an existing stamp,
 * so re-publishing or re-saving an active listing cannot restart a live window.
 */
trait StampsBiddingActivation
{
    /**
     * @param  string  $role  'seller' or 'landlord'.
     */
    protected function stampBiddingActivation(Model $listing, string $role): void
    {
        $windows = app(BiddingWindowService::class);

        // A publish failure must never be caused by bookkeeping. If this cannot
        // be written the listing still publishes; the window then falls back to
        // legacy behaviour, which is degraded but not broken.
        try {
            // Link FIRST, for every listing type. The public view is read-only,
            // so publish is the only place the link can be established — and a
            // Traditional listing still needs one for its offer/application form.
            $offerAuction = app(ListingOfferAuctionLinker::class)->ensureFor($listing, $role);

            // Only a Bidding Period listing has a clock to start.
            if ($windows->isBiddingPeriod($this->auction_type ?? null)) {
                $windows->markActivated($offerAuction);
            }
        } catch (\Throwable $e) {
            Log::warning('[BIDDING ACTIVATION] Could not link or stamp listing', [
                'listing_id' => $listing->id ?? null,
                'role'       => $role,
                'reason'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Statuses proving a root offer was genuinely submitted at some point.
     *
     * Everything except 'draft'. The freeze must survive the bid that triggered
     * it: a withdrawn, rejected or expired offer still means a real bidder
     * committed to the terms on display, so the deadline stays put. Were these
     * terminal states omitted, an owner could withdraw-or-reject their way back
     * into an editable window and move the finish line afterwards.
     */
    private const SUBMITTED_ROOT_STATUSES = [
        'submitted',
        'countered',
        'accepted',
        'rejected',
        'withdrawn',
        'expired',
    ];

    /**
     * Are the auction terms frozen for this listing?
     *
     * Frozen once the listing is live AND at least one ROOT offer has ever been
     * submitted against it. Changing the format or length at that point would
     * move the finish line under bidders who committed to the terms they saw —
     * shortening a window can retroactively invalidate a bid, lengthening one
     * reopens a contest the bidders believed was closed.
     *
     * Scoped to roots (parent_offer_id IS NULL) because a root is the only thing
     * that represents a distinct bidder entering the contest; counters are
     * continuations of a root that already froze the terms.
     *
     * Draft listings, and live listings that never received a submission,
     * remain editable.
     */
    protected function biddingTermsAreFrozen(Model $listing, string $role): bool
    {
        if (filter_var($listing->is_draft ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $offerAuction = app(ListingOfferAuctionLinker::class)->resolve($listing);

        if ($offerAuction === null) {
            return false;
        }

        return Offer::where('offer_auction_id', $offerAuction->id)
            ->whereNull('parent_offer_id')
            ->whereIn('status', self::SUBMITTED_ROOT_STATUSES)
            ->exists();
    }

    /**
     * Resolve the auction_type / auction_time to persist, honouring the freeze.
     *
     * Returns the stored values unchanged when frozen, so a stale or tampered
     * form post cannot move a live deadline. Callers save whatever comes back.
     *
     * @return array{0: string, 1: string}  [auction_type, auction_time]
     */
    protected function resolveAuctionTermsForSave(Model $listing, string $role): array
    {
        $type = (string) ($this->auction_type ?? '');
        $time = (string) ($this->auction_time ?? '');

        if ($this->biddingTermsAreFrozen($listing, $role)) {
            $storedType = $listing->info('auction_type');
            $storedTime = $listing->info('auction_time');

            $type = $storedType === false ? $type : (string) $storedType;
            $time = $storedTime === false ? $time : (string) $storedTime;
        }

        return [$type, $time];
    }
}
