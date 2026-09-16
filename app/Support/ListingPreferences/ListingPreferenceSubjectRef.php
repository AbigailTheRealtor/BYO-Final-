<?php

namespace App\Support\ListingPreferences;

use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use InvalidArgumentException;

/**
 * What a preference is ABOUT: the listing the customer acted on, plus the
 * durable subject key that decides uniqueness.
 *
 * TWO IDENTITIES, ON PURPOSE
 * --------------------------
 * `ref` is the listing the customer actually touched — audit truth. It is the
 * house (listing_type, listing_id) convention, produced only by
 * SmartTagListingType so a caller cannot invent a type string.
 *
 * `subjectKey` is what the unique index uses, and it answers a question the ref
 * cannot: the SAME property reaches a customer as two different rows. Explore
 * resolves a Bridge MLS row to its canonical BidYourOffer page through the
 * `mls_listing_key` provenance meta, so one house is both `bridge:12345` and
 * `seller_agent:678`. Storing only the ref would let someone Pass a property on
 * the map and meet it again, unpassed, in results — the duplicated feedback this
 * key exists to prevent.
 *
 * The key is also more durable than the ref. Smart Tags address
 * `bridge_properties.id`, stable only because the importer upserts on
 * listing_key; a preference outlives a listing in a way tag evidence does not,
 * and an orphaned Save is a customer-visible bug. So a Bridge subject is keyed
 * on the listing_key STRING, which is the identity the feed itself guarantees.
 *
 *   bridge                                    → mls:<listing_key>
 *   seller_agent / landlord_agent, no MLS     → byo:<listing_type>:<id>
 *   seller_agent / landlord_agent, MLS-linked → mls:<listing_key>
 *
 * NOT A PROPERTY IDENTITY. Parcel and address grouping is deliberately absent:
 * {@see \App\Services\Explore\ExplorePropertyIdentity} answers a different
 * question (grouping successive LISTINGS of one property), and adopting it here
 * would merge two listings of the same house that a customer may legitimately
 * feel differently about. Coordinates are never an identity here either, for the
 * reason that class already documents.
 *
 * Build these through {@see \App\Services\ListingPreferences\ListingPreferenceSubjectResolver},
 * which is the only thing that may read provenance to decide a key.
 */
final class ListingPreferenceSubjectRef
{
    public const PREFIX_MLS = 'mls';

    public const PREFIX_BYO = 'byo';

    public function __construct(
        public readonly SmartTagListingRef $ref,
        public readonly string $subjectKey,
    ) {
        if (! self::isWellFormedKey($subjectKey)) {
            throw new InvalidArgumentException(
                'A listing preference subject key must be mls:<listing_key> or byo:<listing_type>:<id>.'
            );
        }
    }

    /** A native BidYourOffer listing carrying no MLS provenance. */
    public static function native(SmartTagListingRef $ref): self
    {
        if (! $ref->type->isNative()) {
            throw new InvalidArgumentException('A byo: subject key requires a native listing type.');
        }

        return new self($ref, self::PREFIX_BYO . ':' . $ref->type->value . ':' . $ref->id);
    }

    /**
     * A subject identified by its MLS ListingKey — either the Bridge row itself
     * or a native listing imported from it. Both produce the SAME key, which is
     * the whole point.
     */
    public static function mls(SmartTagListingRef $ref, string $listingKey): self
    {
        $listingKey = trim($listingKey);

        if ($listingKey === '') {
            throw new InvalidArgumentException('An mls: subject key requires a non-empty listing key.');
        }

        return new self($ref, self::PREFIX_MLS . ':' . $listingKey);
    }

    public function isMlsSubject(): bool
    {
        return str_starts_with($this->subjectKey, self::PREFIX_MLS . ':');
    }

    /** The MLS ListingKey this subject is keyed on, or null for a native-only subject. */
    public function mlsListingKey(): ?string
    {
        return $this->isMlsSubject()
            ? substr($this->subjectKey, strlen(self::PREFIX_MLS) + 1)
            : null;
    }

    public function listingType(): SmartTagListingType
    {
        return $this->ref->type;
    }

    public function listingId(): int
    {
        return $this->ref->id;
    }

    /**
     * Two refs that resolve to one subject are the same subject — a Bridge row
     * and the BidYourOffer listing imported from it compare equal here while
     * their refs do not.
     */
    public function sameSubjectAs(self $other): bool
    {
        return $this->subjectKey === $other->subjectKey;
    }

    private static function isWellFormedKey(string $key): bool
    {
        if (str_starts_with($key, self::PREFIX_MLS . ':')) {
            return trim(substr($key, strlen(self::PREFIX_MLS) + 1)) !== '';
        }

        foreach (SmartTagListingType::cases() as $type) {
            if (! $type->isNative()) {
                continue;
            }

            if (preg_match('/^' . self::PREFIX_BYO . ':' . preg_quote($type->value, '/') . ':[1-9][0-9]*$/', $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
