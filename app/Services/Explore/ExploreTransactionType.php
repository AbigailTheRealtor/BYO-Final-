<?php

namespace App\Services\Explore;

/**
 * FOR SALE or FOR RENT — the one place a RESO PropertyType becomes a
 * transaction type, and the reason Explore is not a Seller-only surface.
 *
 * WHY THIS IS NOT PropertyTypeVocabulary
 * --------------------------------------
 * {@see \App\Support\Listing\PropertyTypeVocabulary} already translates a feed
 * property type into BidYourOffer's own words, and Explore uses it for the
 * label it shows. But it answers a different question — "which form vocabulary
 * does this belong to" — and it answers it with SUBSTRING matching, which is
 * correct for that job and wrong for this one:
 *
 *     PropertyTypeVocabulary::forRole('Residential Lease', 'seller') === 'Residential'
 *
 * A rental read through that lens becomes a sale. So the sale/rent decision is
 * an exact-match allowlist, held in config/explore.php, and a value in neither
 * list resolves to null rather than to a guess.
 *
 * WHY null MATTERS
 * ----------------
 * A property whose transaction type is unknown is a property whose ListPrice
 * cannot be labelled — is 475 a monthly rent or a suspiciously cheap building?
 * Both filters exclude it, so an unclassified type disappears from Explore
 * rather than appearing under the wrong heading. That is the failure this
 * enum exists to make impossible.
 */
enum ExploreTransactionType: string
{
    case SALE = 'sale';
    case RENT = 'rent';

    /**
     * The transaction type for a RESO PropertyType, or null when the value is
     * not one this application has classified.
     *
     * Exact match after trimming. Case-sensitive, matching the posture of
     * {@see \App\Support\Listing\MlsSourceStatus::isRecognised()}: the feed's
     * own casing is what is stored, and a case-insensitive match here would
     * accept 'residential lease' as classified while every other reader of the
     * same string treated it as unknown.
     */
    public static function fromPropertyType(?string $propertyType): ?self
    {
        $value = is_string($propertyType) ? trim($propertyType) : '';

        if ($value === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (in_array($value, $case->propertyTypes(), true)) {
                return $case;
            }
        }

        return null;
    }

    /** The requested filter, or null for "all listings" / an unrecognised request. */
    public static function tryFromFilter(?string $filter): ?self
    {
        return is_string($filter) ? self::tryFrom(trim(strtolower($filter))) : null;
    }

    /**
     * The RESO PropertyType values belonging to this transaction type.
     *
     * @return list<string>
     */
    public function propertyTypes(): array
    {
        $configured = config("explore.transaction_types.{$this->value}", []);

        return array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', (array) $configured),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /** Every classified PropertyType, both directions. */
    public static function allPropertyTypes(): array
    {
        return array_merge(self::SALE->propertyTypes(), self::RENT->propertyTypes());
    }

    /**
     * The consumer's word for it.
     *
     * Consumer terminology is FOR SALE / FOR RENT, never Seller / Landlord.
     * Those remain internal domain words and do not belong on a control a
     * member of the public reads.
     */
    public function consumerLabel(): string
    {
        return match ($this) {
            self::SALE => 'For Sale',
            self::RENT => 'For Rent',
        };
    }

    /**
     * Which BidYourOffer role owns a listing of this transaction type.
     *
     * Used to pick the canonical destination and the form vocabulary — never
     * shown to a consumer.
     */
    public function internalRole(): string
    {
        return match ($this) {
            self::SALE => 'seller',
            self::RENT => 'landlord',
        };
    }
}
