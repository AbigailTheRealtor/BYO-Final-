<?php

namespace App\Support\ListingPreferences;

use App\Support\Listing\MlsProvider;
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
 *   bridge                                    → mls:<provider>:<listing_key>
 *   seller_agent / landlord_agent, no MLS     → byo:<listing_type>:<id>
 *   seller_agent / landlord_agent, MLS-linked → mls:<provider>:<listing_key>
 *
 * THE PROVIDER SEGMENT IS NOT DECORATION. A `ListingKey` is minted by the MLS
 * that issued it and is unique only within that system — the same fact that made
 * `UNIQUE(provider, listing_key)` necessary on `bridge_properties`. Keyed on the
 * bare key, one customer's Pass on Stellar's 12345 and their Save on another
 * provider's 12345 would be ONE row under
 * `unique(user_id, seeker_role, subject_key)`: one would overwrite the other, and
 * the append-only history would record a transition that never happened.
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

    /** `listing_preferences.subject_key` and its event twin are both string(191). */
    public const MAX_KEY_LENGTH = 191;

    public function __construct(
        public readonly SmartTagListingRef $ref,
        public readonly string $subjectKey,
    ) {
        if (! self::isWellFormedKey($subjectKey)) {
            throw new InvalidArgumentException(
                'A listing preference subject key must be mls:<provider>:<listing_key> '
                . 'with a recognised provider, or byo:<listing_type>:<id>, and at most '
                . self::MAX_KEY_LENGTH . ' characters.'
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
    public static function mls(SmartTagListingRef $ref, MlsProvider $provider, string $listingKey): self
    {
        $listingKey = trim($listingKey);

        if ($listingKey === '') {
            throw new InvalidArgumentException('An mls: subject key requires a non-empty listing key.');
        }

        // MlsProvider::nativeIdentity() is the one producer of `mls:<provider>:<key>`;
        // its prefix is PREFIX_MLS, which a test pins.
        return new self($ref, $provider->nativeIdentity($listingKey));
    }

    /**
     * The provider that issued this subject's listing, or null for a native-only
     * subject.
     *
     * Parsed from this object's OWN key, which was built from a typed
     * {@see MlsProvider} moments earlier — never from a string that arrived from
     * somewhere else. A key whose provider segment is not recognised cannot exist,
     * because the constructor refuses it.
     */
    public function mlsProvider(): ?MlsProvider
    {
        if (! $this->isMlsSubject()) {
            return null;
        }

        return MlsProvider::fromStored(explode(':', $this->subjectKey, 3)[1] ?? null);
    }

    public function isMlsSubject(): bool
    {
        return str_starts_with($this->subjectKey, self::PREFIX_MLS . ':');
    }

    /**
     * The MLS ListingKey this subject is keyed on, or null for a native-only
     * subject.
     *
     * Split with a limit of 3 so a key that itself contains a colon survives
     * intact — only the prefix and the provider are consumed.
     */
    public function mlsListingKey(): ?string
    {
        if (! $this->isMlsSubject()) {
            return null;
        }

        return explode(':', $this->subjectKey, 3)[2] ?? null;
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
        // The column is string(191) on both tables. A key that would be
        // truncated is refused here rather than silently stored as a DIFFERENT
        // subject than the one the caller asked for — `bridge_properties.listing_key`
        // is string(255), so an over-long key is expressible even though no real
        // RESO ListingKey approaches it.
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            return false;
        }

        if (str_starts_with($key, self::PREFIX_MLS . ':')) {
            $parts = explode(':', $key, 3);

            // mls : <provider> : <listing key>. An unrecognised provider is
            // REFUSED, never read as the one provider we happen to have — that
            // substitution is how another MLS's property would quietly inherit
            // Stellar's preference row.
            return count($parts) === 3
                && MlsProvider::recognises($parts[1])
                && trim($parts[2]) !== '';
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
