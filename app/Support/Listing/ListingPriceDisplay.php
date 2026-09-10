<?php

namespace App\Support\Listing;

use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\Sync\MlsSyncFieldPolicy;

/**
 * Which price a listing page shows, and whose price it is.
 *
 * THE PROBLEM THIS EXISTS TO FIX
 * ------------------------------
 * An MLS-linked listing carries TWO prices that are not the same claim:
 *
 *   · `mls_list_price` — Stellar's own ListPrice, written and refreshed by
 *     MlsListingSyncService, never edited by anybody here. For an MLS-linked
 *     listing this is the asking price OF RECORD.
 *   · the seller's / landlord's BidYourOffer term — `maximum_budget` on seller
 *     ("Desired Sale Price"), `desired_rental_amount` on landlord — a number the
 *     user typed and may change at will.
 *
 * The page printed ONE unlabelled number in the hero, resolved by a fallback
 * chain over the BidYourOffer keys. A reader had no way to tell whether the
 * figure in front of them was what the MLS says the property is listed at or
 * what the seller would privately like to receive, and those two routinely
 * differ. Worse, the chain omitted `maximum_budget` entirely (see below), so the
 * number shown was frequently neither.
 *
 * So: resolve both, label both, and never let one silently stand in for the
 * other.
 *
 * `maximum_budget` AND THE CHAIN THAT MISSED IT
 * ---------------------------------------------
 * The Seller Offer Listing wizard stores its "Desired Sale Price" input under
 * `maximum_budget` — an odd key, deliberately not renamed, and the same one
 * SellerMlsQuickImport::priceField() writes. The hero's chain read
 * `desired_sale_price` first, which on a seller_agent_auction is written NOWHERE
 * (it belongs to Hire Agent Direct and to counter terms), then four bidding
 * fields. A Traditional seller who filled in the one required price field
 * therefore published a page with no price on it at all.
 *
 * This is the same defect, in the same shape, that
 * SellerOfferListingController::buildCalcData() already documents for the
 * Estimated Monthly Payment calculator. It is fixed here the same way: by
 * putting the key the wizard actually writes at the head of the chain. The
 * legacy keys stay behind it, so no listing that renders a price today stops.
 *
 * THE LANDLORD RULE IS NOT THE SELLER RULE
 * ----------------------------------------
 * A sale record's ListPrice is a purchase price. Printed under a "/ mo" suffix
 * it advertises a $184,900 monthly rent. MlsSyncFieldPolicy::allowsPriceSync()
 * already refuses to sync a price onto a landlord listing whose source record is
 * not a lease; this class asks that SAME method rather than restating the rule,
 * so the display cannot come to disagree with the writer. The re-check matters
 * because the stored row outlives the condition that wrote it: a listing synced
 * while its source read "Residential Lease" keeps its `mls_list_price` row after
 * a re-import changes the source type.
 *
 * NOTHING HERE WRITES
 * -------------------
 * Every method reads the meta array it was handed. Presentation must consume the
 * stored authoritative value dynamically — a re-sync that moves `mls_list_price`
 * from 184900 to 179900 changes the page on the next render because nothing
 * cached, copied or persisted the old figure.
 */
final class ListingPriceDisplay
{
    /**
     * Seller Your Terms keys, canonical first.
     *
     * @var list<string>
     */
    private const SELLER_TERM_KEYS = [
        'maximum_budget',
        'desired_sale_price',
        'purchase_price',
        'buy_now_price',
        'starting_price',
        'reserve_price',
    ];

    /**
     * Landlord Your Terms keys, canonical first. Already correct — the landlord
     * wizard and LandlordMlsQuickImport both write `desired_rental_amount`.
     *
     * @var list<string>
     */
    private const LANDLORD_TERM_KEYS = [
        'desired_rental_amount',
        'starting_rent',
        'reserve_rent',
        'lease_now_price',
    ];

    /**
     * @param  array<string,mixed>  $meta
     * @param  list<string>  $termKeys
     */
    private function __construct(
        private readonly array $meta,
        private readonly string $role,
        private readonly array $termKeys,
    ) {}

    /** @param array<string,mixed> $meta */
    public static function forSeller(array $meta): self
    {
        return new self($meta, 'seller', self::SELLER_TERM_KEYS);
    }

    /** @param array<string,mixed> $meta */
    public static function forLandlord(array $meta): self
    {
        return new self($meta, 'landlord', self::LANDLORD_TERM_KEYS);
    }

    /**
     * Stellar's own list price, or null.
     *
     * Null — never zero, never a blank label — whenever this listing has no
     * authoritative MLS figure to show. Four separate situations produce it and
     * all four must render as the manual page renders:
     *
     *   · the listing is not MLS-linked at all;
     *   · it is linked but no sync has run, so no price was ever stored (the
     *     quick import writes the mapped price field, not this key);
     *   · the stored value is empty, non-numeric or <= 0;
     *   · it is a landlord listing whose source record is not a lease.
     */
    public function mlsListPrice(): ?float
    {
        if (! $this->isMlsLinked()) {
            return null;
        }

        if (! MlsSyncFieldPolicy::allowsPriceSync($this->role, $this->sourcePropertyType())) {
            return null;
        }

        return self::positiveAmount($this->meta[Meta::META_LIST_PRICE] ?? null);
    }

    /**
     * The user's own BidYourOffer price term, or null.
     *
     * Read-only, and read from the same keys the wizards write. This value is
     * never replaced by the MLS figure and never overwritten by this class.
     */
    public function yourTermsPrice(): ?float
    {
        foreach ($this->termKeys as $key) {
            $amount = self::positiveAmount($this->meta[$key] ?? null);

            if ($amount !== null) {
                return $amount;
            }
        }

        return null;
    }

    /**
     * Is the MLS the authority for this listing's asking price?
     *
     * True only when there is an actual MLS figure to be authoritative WITH. A
     * listing that is MLS-linked but has never synced a price is, for display
     * purposes, an ordinary listing showing its own terms.
     */
    public function mlsIsAuthoritative(): bool
    {
        return $this->mlsListPrice() !== null;
    }

    /**
     * The asking price of record.
     *
     * For an MLS-linked listing that is Stellar's figure — the whole point of
     * the change. For everything else it is the existing BidYourOffer fallback,
     * unchanged.
     */
    public function askingPrice(): ?float
    {
        return $this->mlsListPrice() ?? $this->yourTermsPrice();
    }

    /**
     * Should the "Your Terms" figure be shown as a SEPARATE line?
     *
     * Only when it exists, the MLS figure is being shown as well, and the two
     * actually differ. A quick-imported listing seeds the MLS price into the
     * seller's own price input, so on the common case both numbers are the same
     * one and printing it twice under two headings would invent a distinction
     * the listing does not have.
     */
    public function showsSeparateTerms(): bool
    {
        $terms = $this->yourTermsPrice();

        return $this->mlsIsAuthoritative()
            && $terms !== null
            && abs($terms - (float) $this->mlsListPrice()) >= 0.005;
    }

    /** Does this listing carry an MLS source identifier at all? */
    public function isMlsLinked(): bool
    {
        return MlsLinkedListingStatus::isLinked($this->meta);
    }

    /**
     * "$184,900", or null. The same rendering the listing views' own $fmtMoney
     * produces, so a labelled MLS price and an unlabelled term price cannot end
     * up formatted differently on one page.
     */
    public static function money(?float $amount): ?string
    {
        return $amount === null ? null : '$' . number_format($amount, 0);
    }

    private function sourcePropertyType(): ?string
    {
        $type = $this->meta[Meta::META_SOURCE_PTYPE] ?? null;

        return is_string($type) ? $type : null;
    }

    /**
     * A stored money value as a positive float, or null.
     *
     * Zero, blank, whitespace, non-numeric text and negatives all resolve to
     * null rather than to `$0` — an empty source key must never publish a price.
     */
    private static function positiveAmount(mixed $value): ?float
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        $text = trim((string) $value);

        // The sign has to be read BEFORE the currency characters are stripped:
        // `preg_replace('/[^0-9.]/', …)` turns "-5000" into "5000", which is how
        // a negative stored value would otherwise publish as a positive price.
        if (str_starts_with($text, '-')) {
            return null;
        }

        $raw = preg_replace('/[^0-9.]/', '', $text);

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $amount = (float) $raw;

        return $amount > 0 ? $amount : null;
    }
}
