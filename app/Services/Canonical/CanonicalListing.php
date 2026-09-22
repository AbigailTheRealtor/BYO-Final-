<?php

namespace App\Services\Canonical;

use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Support\Listing\MlsProvider;
use InvalidArgumentException;

/**
 * CanonicalListing — a source-neutral, in-memory projection of a listing.
 *
 * This is the Wave 1 realization of §F1 of the frozen roadmap: instead of a
 * physical canonical mega-table, a resolver/adapter layer projects any source
 * (BYO role listings today; Bridge/RESO, RentCast, ATTOM, CSV later) onto one
 * canonical field vocabulary. Downstream DNA / scoring / matching read ONLY
 * from here, never from a source-specific column.
 *
 * Each canonical field carries its provenance metadata (source, source_field,
 * source_reliability, freshness) so confidence (§F4) and explainability (§F5)
 * can trace every value back to where it came from.
 *
 * Fields are only present when the source actually populated them, so has()/
 * present() are meaningful signals for data-completeness scoring. Unknown is
 * the ABSENCE of a key — never zero, false, '' or 'Other'.
 *
 * THE VOCABULARY
 * --------------
 * Every key is declared in {@see CanonicalListingVocabulary}, with one type and
 * one side (supply or demand). The typed accessors below read the core supply
 * facts; `get()` remains the general reader and is what the DNA score services
 * use for the extension keys.
 *
 * THIS CLASS AND PropertyCandidate ARE DIFFERENT THINGS
 * -----------------------------------------------------
 * {@see \App\Services\Property\PropertyCandidate} is an INGESTION DTO: the
 * normalized shape of one record as a source hands it over (today, a Bridge
 * record on its way into import, prefill or Match Check). It is provider-shaped
 * by design — it carries a ListingKey, an MlsStatus, a raw payload.
 *
 * CanonicalListing is the CONSUMER-FACING listing: what downstream Matchmaker
 * code reads regardless of which source produced it, with provenance per field.
 * A source adapter may use a PropertyCandidate as its input — the MLS adapter
 * ({@see Adapters\MlsListingAdapter}, P0-5) does, and is the only canonical
 * class allowed to — but the two are not merged merely because their fields
 * overlap, and nothing downstream should accept a PropertyCandidate where it
 * means "a listing". MLS records are resolved through
 * {@see \App\Services\Bridge\MlsCanonicalListingResolver}, never through
 * CanonicalListingResolver, whose supports() gates the DNA-score chain.
 */
class CanonicalListing
{
    /** @var array<string,mixed> canonical_key => normalized value */
    private array $fields;

    /** @var array<string,array<string,mixed>> canonical_key => provenance meta */
    private array $meta;

    private string $listingType;

    private int $listingId;

    /**
     * The MLS that issued this listing, when it is an MLS record (P0-5).
     *
     * With {@see $mlsListingKey}, the provider-scoped NATIVE identity — never the
     * ListingKey alone, which is unique only within its provider. It is not a
     * canonical field (it is identity, not a fact about the property, and it has
     * no provenance to carry) and it is not a persisted canonical id. Null on
     * every BYO row, including an MLS-linked one: that row stores a ListingKey
     * with no governed provider beside it.
     */
    private ?MlsProvider $mlsProvider;

    private ?string $mlsListingKey;

    /**
     * @param array<string,mixed> $fields
     * @param array<string,array<string,mixed>> $meta
     */
    public function __construct(
        string $listingType,
        int $listingId,
        array $fields = [],
        array $meta = [],
        ?MlsProvider $mlsProvider = null,
        ?string $mlsListingKey = null,
    ) {
        $mlsListingKey = $mlsListingKey === null ? null : trim($mlsListingKey);

        // Both halves or neither: a provider with no key names no record, and a
        // key with no provider is exactly the global-ListingKey identity this
        // reference exists to replace.
        if (($mlsProvider === null) !== ($mlsListingKey === null || $mlsListingKey === '')) {
            throw new InvalidArgumentException('A native MLS reference needs both a provider and a non-empty listing key.');
        }

        $this->listingType   = $listingType;
        $this->listingId     = $listingId;
        $this->fields        = $fields;
        $this->meta          = $meta;
        $this->mlsProvider   = $mlsProvider;
        $this->mlsListingKey = $mlsProvider === null ? null : $mlsListingKey;
    }

    public function listingType(): string
    {
        return $this->listingType;
    }

    public function listingId(): int
    {
        return $this->listingId;
    }

    /**
     * Does this row describe a property on offer (Seller / Landlord)?
     *
     * A Buyer or Tenant row is a seeker's criteria. It carries `demand.*` and
     * `pet.profile.*` keys and never a supply fact — it is not a listing of a
     * property, and nothing here pretends it is.
     */
    public function isSupply(): bool
    {
        return V::isSupplyListingType($this->listingType);
    }

    /** The MLS that issued this listing, or null when it is not an MLS record. */
    public function mlsProvider(): ?MlsProvider
    {
        return $this->mlsProvider;
    }

    /** The ListingKey within {@see mlsProvider()}, or null. Never read it alone. */
    public function mlsListingKey(): ?string
    {
        return $this->mlsListingKey;
    }

    /**
     * `mls:<provider>:<listing_key>` — the same string the Listing Preference
     * subject key uses for this record — or null when not an MLS record.
     */
    public function mlsNativeIdentity(): ?string
    {
        return $this->mlsProvider?->nativeIdentity((string) $this->mlsListingKey);
    }

    /** Does this row describe what a seeker is looking for (Buyer / Tenant)? */
    public function isDemand(): bool
    {
        return V::isDemandListingType($this->listingType);
    }

    /** A canonical key exists (even if its value is null). */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    /** A canonical key exists AND carries a non-null value. */
    public function present(string $key): bool
    {
        return $this->has($key) && $this->fields[$key] !== null;
    }

    /** @return mixed the normalized value, or $default when absent/null. */
    public function get(string $key, $default = null)
    {
        return $this->present($key) ? $this->fields[$key] : $default;
    }

    /** @return array<string,mixed> provenance metadata for a canonical field. */
    public function fieldMeta(string $key): array
    {
        return $this->meta[$key] ?? [];
    }

    /** @return array<string,mixed> all present canonical fields. */
    public function all(): array
    {
        return $this->fields;
    }

    // ── Typed core accessors (P0-4). Each returns null when unknown. ─────────

    /** 'sale' | 'lease' | null. */
    public function transactionType(): ?string
    {
        return $this->string(V::LISTING_TRANSACTION_TYPE);
    }

    /** RESO StandardStatus ('Active', 'Pending', …) or null. */
    public function standardStatus(): ?string
    {
        return $this->string(V::LISTING_STANDARD_STATUS);
    }

    /** 'offer_listing' | 'hire_agent' | null. */
    public function workflow(): ?string
    {
        return $this->string(V::LISTING_WORKFLOW);
    }

    /** The price of record (never a Your Terms figure), or null. */
    public function listPrice(): ?float
    {
        return $this->float(V::LISTING_LIST_PRICE);
    }

    /** The period a lease list price is quoted per, or null when unknown. */
    public function leaseAmountFrequency(): ?string
    {
        return $this->string(V::LISTING_LEASE_AMOUNT_FREQUENCY);
    }

    /** Residential | Income | Commercial | Business | Vacant Land, or null. */
    public function propertyType(): ?string
    {
        return $this->string(V::PROPERTY_TYPE);
    }

    public function bedrooms(): ?int
    {
        $v = $this->get(V::PROPERTY_BEDROOMS);

        return is_int($v) ? $v : null;
    }

    public function bathrooms(): ?float
    {
        return $this->float(V::PROPERTY_BATHROOMS);
    }

    public function livingAreaSqft(): ?float
    {
        return $this->float(V::PROPERTY_LIVING_AREA_SQFT);
    }

    public function yearBuilt(): ?int
    {
        $v = $this->get(V::PROPERTY_YEAR_BUILT);

        return is_int($v) ? $v : null;
    }

    public function lotAcreage(): ?float
    {
        return $this->float('property.lot_acreage');
    }

    public function hasPool(): ?bool
    {
        $v = $this->get(V::PROPERTY_POOL);

        return is_bool($v) ? $v : null;
    }

    public function hasGarage(): ?bool
    {
        $v = $this->get(V::PROPERTY_GARAGE);

        return is_bool($v) ? $v : null;
    }

    public function addressLine(): ?string
    {
        return $this->string(V::LOCATION_ADDRESS_LINE);
    }

    public function city(): ?string
    {
        return $this->string(V::LOCATION_CITY);
    }

    public function state(): ?string
    {
        return $this->string(V::LOCATION_STATE);
    }

    public function postalCode(): ?string
    {
        return $this->string(V::LOCATION_POSTAL_CODE);
    }

    public function county(): ?string
    {
        return $this->string(V::LOCATION_COUNTY);
    }

    /**
     * A measurable coordinate, or null. Both halves or neither: a lone latitude
     * is not a location.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function coordinates(): ?array
    {
        $lat = $this->float(V::LOCATION_LATITUDE);
        $lng = $this->float(V::LOCATION_LONGITUDE);

        return ($lat === null || $lng === null) ? null : ['lat' => $lat, 'lng' => $lng];
    }

    private function string(string $key): ?string
    {
        $v = $this->get($key);

        return is_string($v) && trim($v) !== '' ? $v : null;
    }

    private function float(string $key): ?float
    {
        $v = $this->get($key);

        return is_float($v) || is_int($v) ? (float) $v : null;
    }
}
