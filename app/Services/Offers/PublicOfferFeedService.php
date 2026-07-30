<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the anonymous competing-bid feed shown on public listing pages.
 *
 * Two responsibilities, kept together because they must never drift apart:
 *
 *   1. WHO may see a feed at all              — canView()
 *   2. WHAT a permitted viewer is allowed to see — build(), via a strict allow-list
 *
 * The allow-lists below are allow-lists, not deny-lists: a meta key that nobody
 * has explicitly approved is not emitted, so adding a new offer field cannot
 * leak it onto a public page by default.
 *
 * Callers MUST check canView() before calling build(). build() is never invoked
 * for guests — no bid data is loaded, serialized, or sent to the browser and
 * hidden with CSS. A guest's response contains the login callout and nothing else.
 */
class PublicOfferFeedService
{
    /**
     * Seller listings — the audited scalar bidding terms.
     *
     * Scalar, comparable, and directly relevant to competing on price and terms.
     *
     * Note the deliberate asymmetry on the last two pairs: the REQUESTED flags
     * (home_warranty_requested, seller_contribution_requested) are disclosed
     * because "is this bidder asking the seller to pay for X?" is a competitive
     * term, while their paired *_details fields are free text and are excluded.
     * The same rule keeps possession_date but drops possession_notes.
     *
     * Deliberately excluded: custom_terms, notes, possession_notes,
     * seller_contribution_details, home_warranty_details,
     * included_personal_property, excluded_items and every other free-text
     * field; the bidder's own property information (prop_*); match_explanation;
     * expires_at; documents; identity; raw IDs; and the cryptocurrency,
     * exchange/trade, seller-financing and lease-option/lease-purchase
     * sub-fields.
     */
    public const SELLER_ALLOWED_TERMS = [
        'offer_price',
        'earnest_deposit',
        'earnest_deposit_unit',
        'financing_type',
        'financing_contingency',
        'financing_contingency_days',
        'down_payment_value',
        'down_payment_unit',
        'inspection_contingency',
        'inspection_contingency_days',
        'appraisal_contingency',
        'appraisal_contingency_days',
        'sale_of_buyer_property_contingency',
        'sale_of_buyer_property_contingency_days',
        'closing_date',
        'possession_date',
        'home_warranty_requested',
        'seller_contribution_requested',
    ];

    /**
     * Landlord listings — narrow by design.
     *
     * Rental applications carry screening data that must never reach a public
     * page: occupants, pets, smoking, income, employment, credit, criminal
     * history, eviction history, bankruptcy, screening results, references,
     * notes, free text, messages, identity, documents, contact details and raw
     * user/offer IDs are all absent from this list and must stay absent.
     */
    public const LANDLORD_ALLOWED_TERMS = [
        'monthly_rent',
        'lease_term_months',
        'security_deposit',
        'last_month_rent_offered',
        'move_in_date',
        'move_in_funds',
        'maintenance_responsibility',
    ];

    /**
     * Statuses that appear in the public feed at all. Drafts are invisible —
     * an unsubmitted offer is not a competing bid and its existence is private
     * to its author.
     *
     * PERMANENT SUBMITTED-BID HISTORY (ratified 2026-07-29).
     *
     * Once a bid has been validly submitted, a later status change must not make
     * it disappear from bidding history. Every terminal status therefore remains
     * visible and is simply presented as finalized:
     *
     *   submitted / countered   still live, still actionable
     *   accepted                finalized, won
     *   expired                 the bidder's own respond-by deadline lapsed
     *   rejected                the owner refused it
     *   withdrawn               the bidder retracted it
     *
     * Visibility is NOT actionability. Every status above except submitted and
     * countered sits in OfferStateMachineService::FINAL_STATUSES, so
     * OfferPermissionService already denies accept/counter/reject/withdraw/
     * cancel/expire on all of them. Showing a bid does not resurrect it.
     *
     * Only 'draft' is excluded: an unsubmitted offer was never a bid, and its
     * existence is private to its author.
     *
     * On 'expired' specifically — an offer's expires_at is the bidder's
     * "respond by" deadline addressed to the listing owner; it is NOT the
     * listing's bidding window. Letting it delete the bid merges the two clocks
     * that Requirement 7 keeps separate, and because expires_at is mandatory on
     * every submit while `offers:expire-pending` sweeps every minute, every bid
     * on the platform would otherwise erase itself from this feed while its
     * listing's bidding window was still open.
     *
     * Supersedes the narrower 2026-07-29 rule that admitted only 'expired'. See
     * the Regression B Repair and Follow-Up sections of
     * TIMED_OFFER_RUNTIME_INVESTIGATION.md.
     */
    private const PUBLIC_STATUSES = [
        'submitted',
        'countered',
        'accepted',
        'expired',
        'rejected',
        'withdrawn',
    ];

    /**
     * Internal status -> sanitized public label. Anything unmapped reports as
     * 'Closed' rather than leaking an internal state name.
     *
     * 'submitted' reads as "Submitted" rather than "Active": with finalized bids
     * now sharing the table, the column states what happened to each bid, and
     * "Active" invited the reader to infer a ranking the feed does not compute.
     */
    private const PUBLIC_STATUS_LABELS = [
        'submitted' => 'Submitted',
        'countered' => 'Countered',
        'accepted'  => 'Accepted',
        'expired'   => 'Expired',
        'rejected'  => 'Rejected',
        'withdrawn' => 'Withdrawn',
    ];

    /**
     * Statuses that still represent a live negotiation.
     *
     * Exposed so a caller can distinguish live from finalized without
     * re-deriving the rule from labels. The feed itself performs no ranking —
     * see the deferred Bidding Rules scope.
     */
    public const LIVE_STATUS_LABELS = ['Submitted', 'Countered'];

    /**
     * User types eligible to participate as a bidder, per listing role.
     * A seller's listing receives buyer offers; a landlord's receives tenant
     * applications.
     */
    private const ELIGIBLE_BIDDER_TYPES = [
        'seller'   => ['buyer'],
        'landlord' => ['tenant'],
    ];

    /**
     * User types acting as an authorized representative of a marketplace party.
     */
    private const REPRESENTATIVE_TYPES = ['agent', 'buyer_agent', 'seller_agent'];

    /**
     * May this viewer load the anonymous bid feed for this listing?
     *
     * Permitted:
     *   - an authenticated, role-eligible bidder (buyer on seller listings,
     *     tenant on landlord listings)
     *   - the listing owner
     *   - an authorized representative
     *   - an admin
     *
     * Guests are never permitted — they receive the login callout only.
     *
     * @param  string  $role  'seller' or 'landlord'.
     */
    public function canView(?User $viewer, Model $listing, string $role): bool
    {
        if ($viewer === null) {
            return false;
        }

        $userType = strtolower(trim((string) ($viewer->user_type ?? '')));

        if ($userType === 'admin') {
            return true;
        }

        if ((int) $listing->user_id === (int) $viewer->id) {
            return true;
        }

        if (in_array($userType, self::REPRESENTATIVE_TYPES, true)) {
            return true;
        }

        return in_array($userType, self::ELIGIBLE_BIDDER_TYPES[$role] ?? [], true);
    }

    /**
     * Build the sanitized feed.
     *
     * Only call this once canView() has returned true.
     *
     * Bidder numbering is stable per ROOT offer: every offer in a counter chain
     * inherits the number assigned to the chain's original submission, ordered
     * by when that submission was made. A bidder's number therefore never
     * changes as the negotiation progresses or as other bidders drop out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(?OfferAuction $offerAuction, string $role): array
    {
        if ($offerAuction === null) {
            return [];
        }

        $offers = Offer::with('metas')
            ->where('offer_auction_id', $offerAuction->id)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->orderBy('id')
            ->get();

        if ($offers->isEmpty()) {
            return [];
        }

        $bidderNumbers = $this->assignBidderNumbers($offerAuction);
        $allowed       = $this->allowedTermsFor($role);

        $rows = [];

        foreach ($offers as $offer) {
            $rootId = $this->rootOfferId($offer);
            $number = $bidderNumbers[$rootId] ?? null;

            if ($number === null) {
                continue;
            }

            $terms = [];
            foreach ($allowed as $key) {
                $value = $offer->getMeta($key);
                if ($value !== null && $value !== '') {
                    $terms[$key] = $value;
                }
            }

            $rows[] = [
                'bidder_number' => $number,
                'submitted_at'  => $offer->submitted_at,
                'status'        => self::PUBLIC_STATUS_LABELS[$offer->status] ?? 'Closed',
                'terms'         => $terms,
            ];
        }

        // Present in bidder order so the feed reads consistently across reloads.
        usort($rows, fn ($a, $b) => $a['bidder_number'] <=> $b['bidder_number']);

        return $rows;
    }

    /**
     * @return array<string>
     */
    public function allowedTermsFor(string $role): array
    {
        return $role === 'landlord'
            ? self::LANDLORD_ALLOWED_TERMS
            : self::SELLER_ALLOWED_TERMS;
    }

    /**
     * Map every root offer on this listing to a stable Bidder #.
     *
     * Ordered by submitted_at, with id as the tiebreaker so the ordering is
     * total and deterministic even when two offers share a timestamp or a root
     * has no submitted_at yet.
     *
     * @return array<int, int>  root offer id => bidder number
     */
    private function assignBidderNumbers(OfferAuction $offerAuction): array
    {
        // Every root that was ever validly submitted gets a permanent slot.
        // Now that PUBLIC_STATUSES covers every non-draft status, numbering and
        // visibility are the same set by construction, so a bid can never be
        // shown without a number or renumber the bidders around it.
        $roots = Offer::where('offer_auction_id', $offerAuction->id)
            ->whereNull('parent_offer_id')
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->get(['id', 'submitted_at'])
            ->sort(function ($a, $b) {
                $aTime = $a->submitted_at?->getTimestamp();
                $bTime = $b->submitted_at?->getTimestamp();

                if ($aTime === $bTime) {
                    return $a->id <=> $b->id;
                }

                // Roots without a submitted_at sort last but keep a stable slot.
                if ($aTime === null) return 1;
                if ($bTime === null) return -1;

                return $aTime <=> $bTime;
            })
            ->values();

        $numbers = [];
        foreach ($roots as $index => $root) {
            $numbers[$root->id] = $index + 1;
        }

        return $numbers;
    }

    /**
     * Walk a counter chain to its original submission.
     *
     * Depth-guarded: a corrupted parent chain must not spin forever while
     * rendering a public page.
     */
    private function rootOfferId(Offer $offer): int
    {
        $current = $offer;
        $guard   = 0;

        while ($current->parent_offer_id !== null && $guard++ < 50) {
            $parent = $current->parentOffer;

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return $current->id;
    }
}
