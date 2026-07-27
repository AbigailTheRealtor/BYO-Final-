<?php

namespace App\Services\Offers;

use App\Models\OfferAuction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for when a Bidding Period listing opens and closes.
 *
 * ---------------------------------------------------------------------------
 * CANONICAL RULE (Owner-Approved Decision A, 2026-07-27)
 * ---------------------------------------------------------------------------
 * When a listing first becomes Active, BOTH ends of its bidding window are
 * computed once and STORED, in a single transaction:
 *
 *     offer_auctions.bidding_starts_at = server time at activation
 *     offer_auctions.bidding_ends_at   = bidding_starts_at + auction_time
 *
 * After that moment auction_time is never consulted again. Every countdown,
 * every enforcement check, every API and every presenter reads
 * bidding_ends_at directly. The deadline is data, not a calculation.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS SERVICE WILL NEVER DO
 * ---------------------------------------------------------------------------
 *   - Recompute a deadline from auction_time after activation.
 *   - Read, reference, substitute or fall back to expiration_date. The listing
 *     expiration date is a SEPARATE business concept and is permanently
 *     independent of the bidding period (Invariants 1, 2, 10).
 *   - Fall back to created_at, which is when the DRAFT was first saved.
 *   - Invent a window for a listing that was never stamped (Decision B).
 *
 * A Bidding Period listing with no stored window is UNINITIALIZED: it renders
 * no countdown and blocks no bidder. That is the honest representation of
 * "we do not know", and it is deliberately visible rather than papered over.
 *
 * auction_time remains what it always was — creation-time wizard input. It is
 * read at exactly one moment in the lifecycle, by markActivated(), and never
 * again.
 */
class BiddingWindowService
{
    /**
     * Deadlines are displayed to users in Eastern time. Storage is untouched —
     * conversion happens at render only.
     */
    public const DISPLAY_TIMEZONE = 'America/New_York';

    /**
     * Listing auction_type values that open a bidding window. Matched
     * case-insensitively; 'auction (timer)' is a legacy synonym still present
     * on older rows.
     */
    private const BIDDING_TYPES = ['bidding period', 'auction (timer)'];

    /**
     * Stamp the canonical bidding window onto the listing's linked OfferAuction.
     *
     * Both timestamps are written together or not at all. A start without an end
     * is not a valid window — it would force a reader to derive the missing half,
     * which is the defect this architecture exists to remove.
     *
     * Idempotent by construction: an OfferAuction that already carries a
     * bidding_starts_at is left exactly as it is. Callers may invoke this on
     * every publish without risk of restarting a live window.
     *
     * @param  string|null  $auctionTime  The approved duration chosen in the
     *                                    creation wizard, e.g. "5 Days". Read
     *                                    here and never again.
     * @return bool True only when this call performed the one-time stamp.
     */
    public function markActivated(
        ?OfferAuction $offerAuction,
        ?string $auctionTime,
        ?CarbonImmutable $now = null,
    ): bool {
        if ($offerAuction === null) {
            return false;
        }

        // Never overwrite. Never restart.
        if ($offerAuction->bidding_starts_at !== null) {
            return false;
        }

        $startsAt = $now ?? CarbonImmutable::now();
        $endsAt   = $this->addDuration($startsAt, $auctionTime);

        // No usable duration means no computable end. Refuse the whole stamp
        // rather than write a half window that a reader would have to complete.
        if ($endsAt === null) {
            return false;
        }

        DB::transaction(function () use ($offerAuction, $startsAt, $endsAt) {
            $offerAuction->bidding_starts_at = $startsAt;
            $offerAuction->bidding_ends_at   = $endsAt;
            $offerAuction->save();
        });

        return true;
    }

    /**
     * Resolve the bidding window for a listing.
     *
     * Reads stored timestamps only. No arithmetic, no fallbacks.
     *
     * @param  Model              $listing        SellerAgentAuction or LandlordAgentAuction.
     * @param  OfferAuction|null  $offerAuction   The listing's linked OfferAuction, when one exists.
     */
    public function for(Model $listing, ?OfferAuction $offerAuction = null): BiddingWindow
    {
        if (! $this->isBiddingPeriod($this->readListingValue($listing, 'auction_type'))) {
            return BiddingWindow::notBidding();
        }

        return $this->fromStoredWindow($offerAuction);
    }

    /**
     * Resolve the window starting from an OfferAuction rather than a listing.
     *
     * Used by the server-side bidding guards, which see an Offer (and therefore
     * an OfferAuction) but not the listing it belongs to.
     */
    public function forOfferAuction(?OfferAuction $offerAuction): BiddingWindow
    {
        if ($offerAuction === null) {
            return BiddingWindow::notBidding();
        }

        [$listing] = app(ListingOfferAuctionLinker::class)->listingFor($offerAuction);

        if ($listing === null) {
            return BiddingWindow::notBidding();
        }

        return $this->for($listing, $offerAuction);
    }

    /**
     * Has bidding closed on the listing behind this OfferAuction?
     *
     * Returns false for anything that is not a Bidding Period listing, and for
     * Bidding Period listings with no canonical window — bidders are never
     * locked out because of missing data on our side.
     */
    public function isClosedForOfferAuction(?OfferAuction $offerAuction): bool
    {
        return $this->forOfferAuction($offerAuction)->isClosed();
    }

    public function isBiddingPeriod(?string $auctionType): bool
    {
        return in_array(strtolower(trim((string) $auctionType)), self::BIDDING_TYPES, true);
    }

    /**
     * Build the window from stored columns alone.
     *
     * Both timestamps must be present. A row carrying only a start is reported
     * as uninitialized rather than completed by arithmetic.
     */
    private function fromStoredWindow(?OfferAuction $offerAuction): BiddingWindow
    {
        $startsAt = $offerAuction?->bidding_starts_at;
        $endsAt   = $offerAuction?->bidding_ends_at;

        if ($startsAt === null || $endsAt === null) {
            return BiddingWindow::uninitialized();
        }

        return new BiddingWindow(
            isBiddingPeriod: true,
            startsAt: CarbonImmutable::parse($startsAt),
            endsAt: CarbonImmutable::parse($endsAt),
        );
    }

    /**
     * Add a listing's auction_time to an instant.
     *
     * PUBLISH-TIME ONLY. This is called from markActivated() and from nowhere
     * else in the runtime. auction_time is a free-form label chosen from a
     * select — "14 Days", "2 Weeks", "48 Hours", and bare numbers like "14" all
     * occur in the data. A bare number means days.
     */
    public function addDuration(CarbonImmutable $start, ?string $auctionTime): ?CarbonImmutable
    {
        $parsed = $this->parseDuration($auctionTime);

        if ($parsed === null) {
            return null;
        }

        [$value, $unit] = $parsed;

        return match ($unit) {
            'minutes' => $start->addMinutes($value),
            'hours'   => $start->addHours($value),
            'weeks'   => $start->addWeeks($value),
            'months'  => $start->addMonths($value),
            default   => $start->addDays($value),
        };
    }

    /**
     * @return array{0: int, 1: string}|null  [value, normalized unit] or null when unusable.
     */
    public function parseDuration(?string $auctionTime): ?array
    {
        $raw = strtolower(trim((string) $auctionTime));

        if ($raw === '' || $raw === 'null') {
            return null;
        }

        if (! preg_match('/(\d+)\s*([a-z]*)/', $raw, $m)) {
            return null;
        }

        $value = (int) $m[1];

        if ($value <= 0) {
            return null;
        }

        $unit = match (true) {
            str_starts_with($m[2], 'minute') => 'minutes',
            str_starts_with($m[2], 'hour')   => 'hours',
            str_starts_with($m[2], 'week')   => 'weeks',
            str_starts_with($m[2], 'month')  => 'months',
            default                          => 'days',
        };

        return [$value, $unit];
    }

    /**
     * Read a listing value from EAV meta, falling back to a native column.
     *
     * Seller and Landlord listings disagree about where these live —
     * seller_agent_auctions has native columns while landlord_agent_auctions is
     * EAV-by-design — so both are checked. info() returns false when a key is
     * absent.
     *
     * Only ever called for auction_type and auction_time. expiration_date is
     * never read by this service (Invariant 10).
     */
    private function readListingValue(Model $listing, string $key): ?string
    {
        if (method_exists($listing, 'info')) {
            $value = $listing->info($key);
            if ($value !== false && $value !== null && $value !== '') {
                return (string) $value;
            }
        }

        $native = $listing->getAttribute($key);

        return ($native === null || $native === '') ? null : (string) $native;
    }
}
