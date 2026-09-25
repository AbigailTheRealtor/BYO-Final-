<?php

namespace App\Services\Canonical\Adapters;

use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Services\ListingImport\MlsNormalizer;
use App\Services\Location\Coordinates\Adapters\CoordinateValidator;
use App\Services\Property\PropertyCandidate;
use App\Support\Listing\MlsSourceStatus;
use App\Support\Listing\PropertyTypeVocabulary;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * MlsListingAdapter — projects one MLS record onto the canonical vocabulary (P0-5).
 *
 *   BridgeProperty → BridgePropertyCandidateAdapter → PropertyCandidate
 *                  → MlsListingAdapter → CanonicalListing
 *
 * GOVERNANCE: read-only, deterministic, no AI, no network, no database. The ONE
 * class in the canonical namespace allowed to read a PropertyCandidate, and it
 * reads only the candidate's typed, provider-neutral fields — never `$raw`, never
 * a source table, never a provider-specific (`STELLAR_*`) field. The
 * architecture guard pins all three. A second MLS reaches the same canonical keys
 * by handing over a PropertyCandidate; nothing here names Stellar or Bridge.
 *
 * INERT IN P0-5: nothing at runtime calls this. It is reached only through
 * {@see \App\Services\Bridge\MlsCanonicalListingResolver}, which no live surface
 * uses, and deliberately NOT through CanonicalListingResolver — whose supports()
 * gates the DNA-score chain in ComputeLocationDna.
 *
 * EVERY DECISION IS DELEGATED, NOT RESTATED
 * -----------------------------------------
 *   type / transaction  PropertyTypeVocabulary — exact table, the same reading
 *                       BYO uses; no translator. Unrecognised → both absent.
 *   status              the feed's StandardStatus, kept only when
 *                       MlsSourceStatus recognises it. MlsStatus is NEVER read
 *                       (not even as a fallback) and Expired is never derived.
 *   lease period        leasePeriodToken() over MlsNormalizer — the platform's own
 *                       normalized token; a lease length or unknown value is
 *                       absent, and nothing defaults to monthly.
 *   coordinates         CoordinateValidator::isValidPair() — both halves or
 *                       neither; Null Island and out-of-range are absent. The
 *                       coordinate ladder grades an MLS point Parcel, i.e. exact.
 *
 * WHAT IS DELIBERATELY NOT EMITTED
 * --------------------------------
 *   · listing.workflow — a BidYourOffer workflow term; an MLS record has none.
 *   · The vocabulary-bearing extension keys (structure_type, condition,
 *     view_preference, water_access, water_view, water_frontage_feet,
 *     hoa_fee_includes, community_amenities, pet.policy.*). Their consumers
 *     substring-match BYO option spellings, so feed vocabulary there would
 *     silently define MLS DNA behaviour. Deferred to provider-field containment.
 *   · Remarks, media, display permissions, contacts — licence-governed.
 *
 * The address is carried even when the feed withholds it from public display:
 * preservation and display are separate permissions, and a CanonicalListing is
 * an internal read model. Any public consumer must apply MlsDisplayPermissions.
 */
final class MlsListingAdapter
{
    /**
     * Reliability stamped on every MLS-derived field: the licensed feed of
     * record, which outranks BYO's self-reported
     * {@see ByoListingAdapter::SOURCE_RELIABILITY}. Defined once, here.
     */
    public const SOURCE_RELIABILITY = 95;

    /** Provenance `source` is `mls:<provider>` — the provider token comes only from MlsProvider. */
    public const SOURCE_PREFIX = 'mls';

    /**
     * The tokens MlsNormalizer::normalizeLeaseFrequency() produces that say what
     * a lease PRICE is quoted per. The other recognised Stellar values
     * (`12_months`, `24_months`, `6_to_12_months`, `short_term`) state a lease
     * LENGTH, not the period of the rent — the same split `MonthlyEquivalent`
     * makes when it declines to convert them. A subset of the normalizer's
     * output, never a second vocabulary; a test pins that.
     *
     * @var list<string>
     */
    public const LEASE_PERIOD_TOKENS = ['monthly', 'month_to_month', 'weekly', 'daily', 'annually', 'seasonal'];

    /** RESO spelling for a recognised variant that differs only in spelling (as BYO). */
    private const STATUS_SPELLING = ['Cancelled' => 'Canceled'];

    private const EARLIEST_YEAR_BUILT = 1700;

    /**
     * The canonical listing of one MLS record, or null when the record cannot be
     * named: no recognised provider, or no ListingKey. An unnamed record gets no
     * canonical listing rather than one identified by a bare key.
     */
    public function fromCandidate(PropertyCandidate $candidate): ?CanonicalListing
    {
        $provider   = $candidate->mlsProvider;
        $listingKey = $candidate->listingKey === null ? '' : trim($candidate->listingKey);

        if ($provider === null || $listingKey === '') {
            return null;
        }

        $fields = [];
        $meta   = [];
        $source    = self::SOURCE_PREFIX . ':' . $provider->value;
        $freshness = $this->freshness($candidate->modificationTimestamp);

        $put = function (string $key, mixed $value, string $sourceField) use (&$fields, &$meta, $source, $freshness): void {
            if ($value === null) {
                return;
            }

            $fields[$key] = $value;
            $meta[$key]   = [
                'source'             => $source,
                'source_field'       => $sourceField,
                'source_reliability' => self::SOURCE_RELIABILITY,
                'freshness'          => $freshness,
            ];
        };

        $transaction = PropertyTypeVocabulary::transactionFor($candidate->propertyType);

        // ── Listing ──────────────────────────────────────────────────────────
        $put(V::LISTING_TRANSACTION_TYPE, $transaction, 'PropertyType');
        $put(V::LISTING_STANDARD_STATUS, $this->standardStatus($candidate->standardStatus), 'StandardStatus');
        $put(V::LISTING_LIST_PRICE, $this->positive($candidate->listPrice), 'ListPrice');

        // A period belongs to a lease price only; a sale never carries one.
        if ($transaction === PropertyTypeVocabulary::TRANSACTION_LEASE) {
            $put(V::LISTING_LEASE_AMOUNT_FREQUENCY, self::leasePeriodToken($candidate->leaseAmountFrequency), 'LeaseAmountFrequency');
        }

        // ── Property ─────────────────────────────────────────────────────────
        $put(V::PROPERTY_TYPE, PropertyTypeVocabulary::roleCategoryFor($candidate->propertyType, 'seller'), 'PropertyType');
        $put(V::PROPERTY_BEDROOMS, $candidate->bedrooms !== null && $candidate->bedrooms > 0 ? $candidate->bedrooms : null, 'BedroomsTotal');
        // The decimal when the feed sends one; otherwise the same components-based total import
        // stores (BathroomTotal), so the canonical listing and the listing's own bathrooms field
        // are one reading of the record.
        $bathrooms = $this->bathrooms($candidate->bathroomsTotalDecimal);
        $put(
            V::PROPERTY_BATHROOMS,
            $bathrooms ?? $this->bathrooms($candidate->bathroomsTotal),
            $bathrooms !== null ? 'BathroomsTotalDecimal' : 'BathroomTotal'
        );
        $put(V::PROPERTY_LIVING_AREA_SQFT, $this->positive($candidate->livingAreaSqft), 'LivingArea');
        $put(V::PROPERTY_YEAR_BUILT, $this->yearBuilt($candidate->yearBuilt), 'YearBuilt');
        $put(V::PROPERTY_POOL, $this->affirmed($candidate->pool), 'PoolPrivateYN');
        $put(V::PROPERTY_GARAGE, $this->affirmed($candidate->garage), 'GarageYN');
        $put('property.lot_acreage', $this->positive($candidate->lotSizeAcres), 'LotSizeAcres');
        $put('property.waterfront', $this->affirmed($candidate->waterfront), 'WaterfrontYN');

        // ── Location ─────────────────────────────────────────────────────────
        $put(V::LOCATION_ADDRESS_LINE, $this->text($candidate->unparsedAddress), 'UnparsedAddress');
        $put(V::LOCATION_CITY, $this->text($candidate->city), 'City');
        $put(V::LOCATION_STATE, $this->text($candidate->stateOrProvince), 'StateOrProvince');
        $put(V::LOCATION_POSTAL_CODE, $this->text($candidate->postalCode), 'PostalCode');
        $put(V::LOCATION_COUNTY, $this->text($candidate->countyOrParish), 'CountyOrParish');

        if (CoordinateValidator::isValidPair($candidate->latitude, $candidate->longitude)) {
            $put(V::LOCATION_LATITUDE, (float) $candidate->latitude, 'Latitude');
            $put(V::LOCATION_LONGITUDE, (float) $candidate->longitude, 'Longitude');
        }

        return new CanonicalListing(
            V::MLS_LISTING_TYPE,
            $this->localId($candidate->sourceRecordId),
            $fields,
            $meta,
            $provider,
            $listingKey,
        );
    }

    /**
     * A feed Yes/No as a canonical fact only when it is YES.
     *
     * The stored `*_yn` columns cannot tell "No" from "not stated":
     * BridgePropertyNormalizer::filterBool() maps a present-but-null feed value
     * to false (`filter_var(null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`
     * is false), so every commercial, business and land record that leaves
     * PoolPrivateYN null is stored as "no pool". A false here may therefore be a
     * fabricated fact, and the canonical layer does not publish fabricated facts:
     * false is treated as unknown. True cannot be fabricated that way and is
     * kept. Correcting the normalizer changes stored data that live matching,
     * Match Check, Smart Tags and Explore read, so it is a separate change; once
     * made, this may accept false again.
     */
    private function affirmed(?bool $value): ?bool
    {
        return $value === true ? true : null;
    }

    /**
     * The normalized rent period of a feed LeaseAmountFrequency, or null when the
     * value is blank, unrecognised or a lease length rather than a period. Never
     * a default: an unknown period stays unknown.
     */
    public static function leasePeriodToken(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $token = MlsNormalizer::normalizeLeaseFrequency($value);

        return in_array($token, self::LEASE_PERIOD_TOKENS, true) ? $token : null;
    }

    /** A recognised RESO StandardStatus, verbatim; anything else is unknown. */
    private function standardStatus(?string $raw): ?string
    {
        $status = MlsSourceStatus::normalize($raw);

        if ($status === null || ! MlsSourceStatus::isRecognised($status)) {
            return null;
        }

        return self::STATUS_SPELLING[$status] ?? $status;
    }

    /** Counted in halves (RESO BathroomsTotalDecimal); zero or anything finer is unknown. */
    private function bathrooms(?float $value): ?float
    {
        return ($value !== null && $value > 0 && fmod($value * 2, 1.0) === 0.0) ? $value : null;
    }

    private function yearBuilt(?int $year): ?int
    {
        return ($year !== null && $year >= self::EARLIEST_YEAR_BUILT && $year <= (int) date('Y') + 2) ? $year : null;
    }

    /** A strictly positive number as a float; zero, negative and null are unknown. */
    private function positive(int|float|null $value): ?float
    {
        return ($value !== null && $value > 0) ? (float) $value : null;
    }

    private function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The feed's ModificationTimestamp as ISO-8601, or null. Never "now" and never
     * the local import time: freshness is when the MLS changed the record.
     */
    private function freshness(?string $timestamp): ?string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp, 'UTC')->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The local row id, or 0 for a record not held locally (a candidate built
     * from a raw record). Identity then rests on the native reference alone.
     */
    private function localId(?string $sourceRecordId): int
    {
        return ($sourceRecordId !== null && ctype_digit($sourceRecordId)) ? (int) $sourceRecordId : 0;
    }
}
