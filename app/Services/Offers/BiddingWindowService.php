<?php

namespace App\Services\Offers;

use App\Models\OfferAuction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The single source of truth for when a Bidding Period listing opens and closes.
 *
 * ---------------------------------------------------------------------------
 * CANONICAL RULE
 * ---------------------------------------------------------------------------
 * Bidding starts when the listing first becomes Active/published, and that
 * moment is stamped once onto offer_auctions.bidding_started_at:
 *
 *     deadline = bidding_started_at + auction_time
 *
 * bidding_started_at is written exactly once, by markActivated(), at the
 * publish transition. It is never recalculated, never overwritten, and never
 * restarted — editing a listing, re-saving it, or re-publishing it does not
 * move the deadline.
 *
 * ---------------------------------------------------------------------------
 * LEGACY FALLBACK — TEMPORARY
 * ---------------------------------------------------------------------------
 * Listings that went Active before this column existed have bidding_started_at
 * = NULL (the migration deliberately did not backfill; see that file for why).
 * For those rows only, we fall back to the behaviour that shipped previously:
 *
 *     1. expiration_date, when set; otherwise
 *     2. listing created_at + auction_time
 *
 * Both are known to be wrong in ways this work exists to fix — expiration_date
 * is a listing expiry, not a bidding deadline, and created_at is when the
 * DRAFT was first saved, which can predate activation by days. The fallback is
 * here only so pre-existing active listings keep rendering a timer instead of
 * abruptly losing one. It is not a supported path for new listings and should
 * be deleted once no active Bidding Period listing has a NULL stamp.
 *
 * BiddingWindow::$isLegacyFallback flags every window that took this path.
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
     * Stamp the canonical bidding start onto the listing's linked OfferAuction.
     *
     * Idempotent by construction: a non-null bidding_started_at is left exactly
     * as it is. Callers may invoke this on every publish without risk of
     * restarting a live window.
     *
     * @return bool True only when this call performed the one-time stamp.
     */
    public function markActivated(?OfferAuction $offerAuction, ?CarbonImmutable $now = null): bool
    {
        if ($offerAuction === null) {
            return false;
        }

        // Never overwrite. Never restart.
        if ($offerAuction->bidding_started_at !== null) {
            return false;
        }

        $offerAuction->bidding_started_at = $now ?? CarbonImmutable::now();
        $offerAuction->save();

        return true;
    }

    /**
     * Resolve the bidding window for a listing.
     *
     * @param  Model              $listing        SellerAgentAuction or LandlordAgentAuction.
     * @param  OfferAuction|null  $offerAuction   The listing's linked OfferAuction, when one exists.
     */
    public function for(Model $listing, ?OfferAuction $offerAuction = null): BiddingWindow
    {
        $auctionType = $this->readListingValue($listing, 'auction_type');

        if (! $this->isBiddingPeriod($auctionType)) {
            return BiddingWindow::notBidding();
        }

        $auctionTime = $this->readListingValue($listing, 'auction_time');
        $startedAt   = $offerAuction?->bidding_started_at
            ? CarbonImmutable::parse($offerAuction->bidding_started_at)
            : null;

        // --- Canonical path -------------------------------------------------
        if ($startedAt !== null) {
            $endsAt = $this->addDuration($startedAt, $auctionTime);

            return new BiddingWindow(
                isBiddingPeriod: true,
                startedAt: $startedAt,
                endsAt: $endsAt,
                isLegacyFallback: false,
            );
        }

        // --- Legacy fallback (temporary; see class docblock) -----------------
        [$endsAt, $reason] = $this->legacyDeadline($listing, $auctionTime);

        return new BiddingWindow(
            isBiddingPeriod: true,
            startedAt: null,
            endsAt: $endsAt,
            isLegacyFallback: true,
            legacyFallbackReason: $reason,
        );
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
     * Bidding Period listings whose deadline cannot be resolved — bidders are
     * never locked out because of missing data on our side.
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
     * Legacy deadline for rows with no canonical stamp.
     *
     * @return array{0: ?CarbonImmutable, 1: string}
     */
    private function legacyDeadline(Model $listing, ?string $auctionTime): array
    {
        $expiration = trim((string) $this->readListingValue($listing, 'expiration_date'));

        if ($expiration !== '') {
            try {
                return [
                    CarbonImmutable::parse($expiration),
                    'legacy: expiration_date (no bidding_started_at stamp)',
                ];
            } catch (\Throwable) {
                // Unparseable expiration_date — fall through to created_at.
            }
        }

        if ($listing->created_at === null) {
            return [null, 'legacy: no expiration_date and no created_at'];
        }

        $endsAt = $this->addDuration(CarbonImmutable::parse($listing->created_at), $auctionTime);

        return [
            $endsAt,
            'legacy: created_at + auction_time (no bidding_started_at stamp)',
        ];
    }

    /**
     * Add a listing's auction_time to an instant.
     *
     * auction_time is a free-form label chosen from a select — "14 Days",
     * "2 Weeks", "48 Hours", and bare numbers like "14" all occur in the data.
     * A bare number means days, matching how the legacy views read it.
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
     * seller_agent_auctions has native columns, landlord_agent_auctions is
     * EAV — so both are checked. info() returns false when a key is absent.
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
