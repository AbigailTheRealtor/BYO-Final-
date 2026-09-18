<?php

namespace App\Services\Canonical\Adapters;

use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as MlsMeta;
use App\Services\Listing\ListingWorkflowResolver;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Support\Listing\ListingFlag;
use App\Support\Listing\ListingPriceDisplay;
use App\Support\Listing\MlsLinkedListingStatus;
use App\Support\Listing\MlsSourceStatus;
use App\Support\Listing\PropertyTypeVocabulary;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * ByoSupplyFacts — the core canonical listing facts of a Seller or Landlord
 * BidYourOffer row (P0-4).
 *
 * GOVERNANCE: read-only, deterministic, no AI, no network. Every value is
 * normalized HERE, at the source boundary, so the canonical layer never carries
 * a BYO storage oddity ('3', 'Other', '1,850', 'Yes').
 *
 * EVERY DECISION IS DELEGATED, NOT RESTATED
 * -----------------------------------------
 * This class decides nothing the platform has already decided somewhere else:
 *
 *   price        ListingPriceDisplay::mlsListPrice() — the price of record, with
 *                the landlord sale-record refusal already applied. The Seller's
 *                Desired Sale Price (`maximum_budget`) and the Landlord's desired
 *                rent (`desired_rental_amount`) are Your Terms and are NEVER
 *                read, so a manual listing has no canonical list price.
 *   status       MlsLinkedListingStatus / MlsSourceStatus — the feed's
 *                StandardStatus for an MLS-linked row; for a manual row only an
 *                explicitly stored RESO word. No 'Active' default, no 'Expired'
 *                derived from a typed date, and `is_sold` makes status unknown
 *                rather than letting a stale stored word stand.
 *   type         PropertyTypeVocabulary — no second translation table.
 *   pool/garage  MlsFieldMap::propertyTypeApplicability() — a Yes left behind by
 *                a property type the form no longer shows the control for is
 *                not a fact about the property.
 *   coordinates  ExistingCoordinatesAdapter + PropertyCoordinateResult's
 *                exactCoordinates() gate — never the unprovenanced
 *                `property_lat` / `property_lng` meta.
 *   workflow     ListingWorkflowResolver — fails closed on doubt.
 *
 * Buyer and Tenant rows never reach this class: they describe criteria, not a
 * property, and a Buyer's "3 bedrooms" must never become `property.bedrooms`.
 *
 * No BridgeProperty and no provider-specific field is read. The MLS link of an
 * MLS-linked row is NOT emitted here: a native row stores its ListingKey without
 * a governed provider beside it, and resolving that provider-scoped identity is
 * P0-5's job.
 */
final class ByoSupplyFacts
{
    private const ROLE_FOR_TYPE = [
        'seller_agent'   => 'seller',
        'landlord_agent' => 'landlord',
    ];

    private const TRANSACTION_FOR_ROLE = [
        'seller'   => PropertyTypeVocabulary::TRANSACTION_SALE,
        'landlord' => PropertyTypeVocabulary::TRANSACTION_LEASE,
    ];

    /** Meta keys read to decide price and status through the governed classes. */
    private const MLS_META_KEYS = [
        MlsMeta::META_LISTING_KEY,
        MlsMeta::META_MLS_NUMBER,
        MlsMeta::META_STANDARD_STATUS,
        MlsMeta::META_SOURCE_STATUS,
        MlsMeta::META_LIST_PRICE,
        MlsMeta::META_SOURCE_PTYPE,
    ];

    /** RESO spelling for a recognised variant that differs only in spelling. */
    private const STATUS_SPELLING = ['Cancelled' => 'Canceled'];

    /** A manual row's stored `listing_status` is a market status only when it is one of these. */
    private const MANUAL_MARKET_STATUSES = [V::STATUS_ACTIVE, V::STATUS_PENDING];

    private const EARLIEST_YEAR_BUILT = 1700;

    /**
     * @param object $model a Seller/Landlord *AgentAuction exposing info($key)
     * @return array<string, array{value: mixed, source_field: string}>
     *         canonical key => value plus the field it was read from; absent
     *         when unknown
     */
    public function extract(object $model, string $listingType, int $listingId): array
    {
        $role = self::ROLE_FOR_TYPE[$listingType] ?? null;
        if ($role === null) {
            return [];
        }

        $out = [];
        $put = static function (string $key, mixed $value, string $sourceField) use (&$out): void {
            if ($value !== null) {
                $out[$key] = ['value' => $value, 'source_field' => $sourceField];
            }
        };

        $rawType = $this->text($model, 'property_type');
        $mls     = $this->mlsMeta($model);

        $put(V::LISTING_TRANSACTION_TYPE, self::TRANSACTION_FOR_ROLE[$role], 'listing_type');
        $put(V::LISTING_STANDARD_STATUS, $this->standardStatus($model, $mls), 'listing_status');
        $put(V::LISTING_WORKFLOW, $this->workflow($model), 'workflow_type');
        $put(V::LISTING_LIST_PRICE, $this->listPrice($role, $mls), MlsMeta::META_LIST_PRICE);
        $put(V::PROPERTY_TYPE, $this->propertyType($rawType), 'property_type');

        $put(V::PROPERTY_BEDROOMS, $this->bedrooms($model), 'bedrooms');
        $put(V::PROPERTY_BATHROOMS, $this->bathrooms($model), 'bathrooms');
        $put(V::PROPERTY_LIVING_AREA_SQFT, $this->positiveNumber($this->text($model, 'minimum_heated_square')), 'minimum_heated_square');
        $put(V::PROPERTY_YEAR_BUILT, $this->yearBuilt($this->text($model, 'year_built')), 'year_built');
        $put(V::PROPERTY_POOL, $this->applicableYesNo($model, $role, $rawType, 'pool', 'pool_needed'), 'pool_needed');
        $put(V::PROPERTY_GARAGE, $this->applicableYesNo($model, $role, $rawType, 'garage', 'garage_needed'), 'garage_needed');

        $address = $this->text($model, 'address');
        $city    = $this->text($model, 'property_city');
        $state   = $this->text($model, 'property_state');
        $zip     = $this->text($model, 'property_zip');
        $county  = $this->text($model, 'property_county');

        $put(V::LOCATION_ADDRESS_LINE, $address, 'address');
        $put(V::LOCATION_CITY, $city, 'property_city');
        $put(V::LOCATION_STATE, $state, 'property_state');
        $put(V::LOCATION_POSTAL_CODE, $zip, 'property_zip');
        $put(V::LOCATION_COUNTY, $county, 'property_county');

        $point = $this->exactCoordinates($model, $listingType, $listingId, $address, $city, $state, $zip, $county);
        if ($point !== null) {
            $put(V::LOCATION_LATITUDE, $point['lat'], 'property_location_dna.geocoded_lat');
            $put(V::LOCATION_LONGITUDE, $point['lng'], 'property_location_dna.geocoded_lng');
        }

        return $out;
    }

    // ── Listing facts ────────────────────────────────────────────────────────

    /** @param array<string,mixed> $mls */
    private function standardStatus(object $model, array $mls): ?string
    {
        // A closed BidYourOffer transaction wins over any status string, as it
        // does on the listing page. What market status it implies is not
        // something this layer may guess, so the answer is "unknown".
        if ($this->isSold($model)) {
            return null;
        }

        $market = MlsLinkedListingStatus::marketStatus($mls);
        if ($market !== null) {
            return MlsSourceStatus::isRecognised($market)
                ? (self::STATUS_SPELLING[$market] ?? $market)
                : null;
        }

        $stored = $this->text($model, 'listing_status');

        return in_array($stored, self::MANUAL_MARKET_STATUSES, true) ? $stored : null;
    }

    /** @param array<string,mixed> $mls */
    private function listPrice(string $role, array $mls): ?float
    {
        $display = $role === 'landlord'
            ? ListingPriceDisplay::forLandlord($mls)
            : ListingPriceDisplay::forSeller($mls);

        return $display->mlsListPrice();
    }

    private function workflow(object $model): ?string
    {
        if (! $model instanceof Model) {
            return null;
        }

        try {
            return (new ListingWorkflowResolver())->resolve($model);
        } catch (Throwable) {
            return null;
        }
    }

    // ── Property facts ───────────────────────────────────────────────────────

    /**
     * The platform category, read through PropertyTypeVocabulary in the shared
     * (Seller/Buyer/Tenant) wording, so a Landlord "Residential Property" and a
     * Seller "Residential" are one canonical value. An unrecognised type is
     * unknown, never assumed Residential.
     */
    private function propertyType(?string $raw): ?string
    {
        return PropertyTypeVocabulary::roleCategoryFor($raw, 'seller');
    }

    private function bedrooms(object $model): ?int
    {
        return $this->count($this->withOther($model, 'bedrooms', 'other_bedrooms'));
    }

    private function bathrooms(object $model): ?float
    {
        $value = $this->positiveNumber($this->withOther($model, 'bathrooms', 'other_bathrooms'));

        // Bathrooms are counted in halves (RESO BathroomsTotalDecimal); anything
        // finer is not a bathroom count.
        return ($value !== null && fmod($value * 2, 1.0) === 0.0) ? $value : null;
    }

    /** The select's value, or its "Other" box when the select says Other. */
    private function withOther(object $model, string $key, string $otherKey): ?string
    {
        $value = $this->text($model, $key);

        if ($value !== null && strcasecmp($value, 'Other') === 0) {
            return $this->text($model, $otherKey);
        }

        return $value;
    }

    /** "3", "3 Beds", "3 bedrooms" → 3; "Other", "3.5", "" → null. */
    private function count(?string $raw): ?int
    {
        if ($raw === null || ! preg_match('/^\s*(\d{1,3})\s*(?:beds?|bedrooms?|br)?\s*$/i', $raw, $m)) {
            return null;
        }

        $n = (int) $m[1];

        return $n > 0 ? $n : null;
    }

    private function yearBuilt(?string $raw): ?int
    {
        if ($raw === null || ! preg_match('/^\s*(\d{4})\s*$/', $raw, $m)) {
            return null;
        }

        $year = (int) $m[1];

        return ($year >= self::EARLIEST_YEAR_BUILT && $year <= (int) date('Y') + 2) ? $year : null;
    }

    /**
     * Yes / No for a feature control, only when the stored property type is one
     * the form actually renders that control for.
     */
    private function applicableYesNo(object $model, string $role, ?string $rawType, string $mapKey, string $metaKey): ?bool
    {
        $applicableTo = MlsFieldMap::propertyTypeApplicability($role)[$mapKey] ?? null;

        if ($applicableTo !== null && ! in_array($rawType, $applicableTo, true)) {
            return null;
        }

        $value = strtolower((string) $this->text($model, $metaKey));

        return match ($value) {
            'yes' => true,
            'no'  => false,
            default => null, // 'Optional', blank, anything else: unknown
        };
    }

    // ── Location facts ───────────────────────────────────────────────────────

    /**
     * A coordinate this listing can be MEASURED from, or null.
     *
     * Only through the coordinate ladder's existing-coordinate rung, which
     * requires a stored precision, a provenanced provider and an address that
     * still matches — and then only through exactCoordinates(), so a coarse
     * point is absent rather than approximated. Stubs and unsaved models have no
     * stored row to read.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function exactCoordinates(
        object $model,
        string $listingType,
        int $listingId,
        ?string $address,
        ?string $city,
        ?string $state,
        ?string $zip,
        ?string $county,
    ): ?array {
        if (! $model instanceof Model || $listingId <= 0) {
            return null;
        }

        try {
            $rung = new ExistingCoordinatesAdapter();
            if (! $rung->isAvailable()) {
                return null;
            }

            return $rung->resolve(new PropertyAddress(
                address:     (string) $address,
                city:        (string) $city,
                county:      (string) $county,
                state:       (string) $state,
                zip:         (string) $zip,
                listingType: $listingType,
                listingId:   $listingId,
            ))->exactCoordinates();
        } catch (Throwable) {
            return null;
        }
    }

    // ── Reading helpers ──────────────────────────────────────────────────────

    /** @return array<string,mixed> the MLS provenance meta the governed readers consult */
    private function mlsMeta(object $model): array
    {
        $out = [];
        foreach (self::MLS_META_KEYS as $key) {
            $value = $this->text($model, $key);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function isSold(object $model): bool
    {
        $raw = $model instanceof Model ? $model->getAttribute('is_sold') : ($model->is_sold ?? null);

        return ListingFlag::isTrue($raw);
    }

    /** A meta value as trimmed text, or null when absent, blank or not scalar. */
    private function text(object $model, string $key): ?string
    {
        $raw = $model->info($key);

        // info() returns boolean false when the meta key is absent.
        if ($raw === false || $raw === null || is_array($raw) || is_object($raw)) {
            return null;
        }

        $text = trim((string) $raw);

        return $text === '' ? null : $text;
    }

    /** "1,850", "1850.5" → float; blank, zero, negative, prose → null. */
    private function positiveNumber(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $clean = str_replace([',', '$'], '', $raw);
        if (! is_numeric($clean)) {
            return null;
        }

        $value = (float) $clean;

        return $value > 0 ? $value : null;
    }
}
