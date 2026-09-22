<?php

namespace App\Support\Listing;

/**
 * WHICH MLS ISSUED A RECORD — the provider dimension, and nothing else.
 *
 * WHAT THIS ANSWERS
 * -----------------
 * "Where did this row come from?" It names the SYSTEM that issued the record,
 * so that a native identifier can be read in the context of the system that
 * minted it. Today there is exactly one such system; the point of this enum is
 * that adding a second is a new case here rather than a new string somewhere.
 *
 * WHAT THIS IS NOT — and each of these is a real confusion to avoid
 * ----------------------------------------------------------------
 *   RESO `ListingKey`   the provider's own identifier FOR a listing. Unique
 *                       within the issuing system and NOT across systems, which
 *                       is precisely why this enum exists.
 *   RESO `ListingId`    the human-facing "MLS #". Unique only within its
 *                       originating system, and weaker than ListingKey.
 *   canonical Property  the identity of a physical asset. A property is not a
 *                       record and has no provider — the same house can be
 *                       issued by two MLSs at once.
 *   canonical Listing   the identity of an offering in OUR vocabulary. Later
 *                       work; deliberately absent here.
 *   `listing_type`      the discriminator naming a TABLE (`bridge`,
 *                       `seller_agent`, `landlord_agent`). It answers "which
 *                       store holds this row", not "which system issued it".
 *   `PropertyType`      what the property IS (Residential, Commercial Lease …).
 *   an MLS display name "Stellar MLS via Bridge Data Output" is attribution
 *                       copy shown to a reader, governed by a licence and
 *                       liable to change wording. A stored identity must never
 *                       be a display string; see MlsSupplementalDetails and
 *                       ExploreListingProjector::ATTRIBUTION for that concern.
 *
 * WHY THE VALUE IS `stellar_bridge` AND NOT SOMETHING NEW
 * ------------------------------------------------------
 * {@see \App\Services\Explore\ExploreListingProjector::PROVIDER_STELLAR_BRIDGE}
 * already emits exactly this string, on a public API response, and its own
 * docblock says it travels "so a second MLS can be added later without an
 * Explore rewrite". That is this concept, discovered earlier and left as a bare
 * constant inside a consumer. Reusing the value rather than minting a parallel
 * one means the stored identity and the published one already agree, and
 * pointing Explore at this enum later is a refactor with no data change.
 *
 * WHY AN ENUM RATHER THAN A CONFIG FILE
 * -------------------------------------
 * A provider's *identity* is a closed vocabulary that code branches on; its
 * *capabilities, credentials, rights and attribution* are open configuration
 * that will differ per deployment. Those are a later, separate concern
 * (a registry, modelled on `config/location_providers.php`). Keeping identity
 * in code means an unknown value cannot be introduced by editing an env var.
 *
 * READING A STORED VALUE IS FAIL-CLOSED
 * -------------------------------------
 * {@see fromStored()} returns null for anything unrecognised — never a default,
 * never the current provider. An unrecognised provider is a record whose origin
 * we do not know, and silently reading it as "the one we have" is exactly how a
 * cross-provider mix-up would arrive disguised as success.
 */
enum MlsProvider: string
{
    /**
     * Stellar MLS, delivered through the Bridge Data Output API.
     *
     * The only provider this platform has ever ingested, and therefore the only
     * provider any existing `bridge_properties` row can have come from.
     */
    case StellarBridge = 'stellar_bridge';

    /**
     * The provider every ingestion path writes today.
     *
     * Exists so that no call site has to name a case directly to answer "the
     * current one", which is the line that would need editing in a dozen places
     * the day a second provider is added.
     */
    public static function current(): self
    {
        return self::StellarBridge;
    }

    /**
     * Interpret a value read back from storage.
     *
     * Fail-closed by contract: null in, null out; unrecognised in, null out.
     * A caller that needs a provider must handle null, and must not substitute
     * {@see current()} for it.
     */
    public static function fromStored(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * Every recognised provider value, for validation and for tests.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** Is this value one this application recognises? */
    public static function recognises(?string $value): bool
    {
        return self::fromStored($value) !== null;
    }

    /**
     * The provider-scoped native identity of one of this provider's listings:
     * `mls:<provider>:<listing_key>`.
     *
     * The ONE producer of that string. The Listing Preference subject key and a
     * canonical listing's native reference both come from here, so the two can
     * never spell the same record differently. A ListingKey is only unique
     * within its provider (`UNIQUE(provider, listing_key)`), which is why the
     * provider is part of the identity rather than a label beside it.
     *
     * @throws \InvalidArgumentException for a blank listing key — a key-less
     *         identity would name no record at all.
     */
    public function nativeIdentity(string $listingKey): string
    {
        $listingKey = trim($listingKey);

        if ($listingKey === '') {
            throw new \InvalidArgumentException('A native MLS identity requires a non-empty listing key.');
        }

        return 'mls:' . $this->value . ':' . $listingKey;
    }
}
