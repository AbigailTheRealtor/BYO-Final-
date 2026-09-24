<?php

namespace App\Services\Stellar\Matching;

use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Support\Listing\PropertyTypeVocabulary;

/**
 * The match engine's input built from a CanonicalListing: canonical facts first,
 * the explicit {@see ListingMatchResidualFacts} for the rest.
 *
 * A SECOND WAY TO BUILD THE SAME FACTS — NOT WIRED TO ANYTHING
 * -----------------------------------------------------------
 * Live matching builds {@see ListingMatchFacts} from a Bridge row
 * ({@see \App\Services\Bridge\BridgeListingMatchFactsBuilder}). This builder
 * produces the same object from the source-neutral CanonicalListing, so the two can
 * be compared fact by fact and score by score (P1-B). No application code calls it:
 * nothing is scored from it until a later, separately gated stage.
 *
 * WHAT IT DOES AND DOES NOT DO
 * ----------------------------
 * It reads only a CanonicalListing and the residual it is handed — never a provider
 * row, a feed record, storage or a provider field name — and it changes no scoring
 * rule. It converts each canonical value into the REPRESENTATION the rules and
 * explanations already read, and nothing more:
 *
 *   coordinates     float → the 7-place decimal string a `decimal:7` column yields
 *   list price      float → the 2-place decimal string a `decimal:2` column yields
 *   living area     float → int (the canonical value was a whole number of sqft)
 *   property type   (category, transaction) → the platform's primary recognised type
 *                   via PropertyTypeVocabulary — the literal the rules dispatch on
 *   rent period     the canonical period token, which MonthlyEquivalent reads exactly
 *                   as it reads the feed's own wording
 *
 * SMART TAGS ARE ATTACHED LATER, NOT BUILT HERE
 * ---------------------------------------------
 * `smartTags` is neither canonical nor residual: a listing's resolved Smart Tags are
 * not in the listing, so the caller reads them beside it and attaches them with
 * {@see ListingMatchFacts::withSmartTags()} before scoring — exactly as the live
 * Bridge path does. This builder leaves them unattached (null), the same state the
 * Bridge builder produces, and reads no tag storage.
 *
 * Everything else passes through. Where the canonical listing is stricter than a
 * stored column (an invalid coordinate, a zero price, a "No" that may have been
 * fabricated), the canonical answer — unknown — is kept; that is the point of the
 * canonical listing, and the parity suite names each such difference.
 *
 * MLS ONLY FOR NOW
 * ----------------
 * A listing with no MLS native identity gets no facts (null): this stage compares
 * MLS listings, and a BidYourOffer listing needs its own identity and price
 * decisions before it can be scored this way.
 *
 * Pure: no container, no query, no config, no clock.
 */
final class CanonicalListingMatchFactsBuilder
{
    public const ORIGIN_IDENTITY = 'identity';
    public const ORIGIN_RESIDUAL = 'residual';
    public const ORIGIN_ATTACHED_SMART_TAGS = 'attached:smart_tags';

    /**
     * Where each ListingMatchFacts field comes from: the listing's native identity, the
     * canonical key(s) it is read from, the residual, or — for Smart Tags — an augmentation
     * attached beside the row after these facts are built. Diagnostic only — parity
     * reports use it with CanonicalListing::fieldMeta() to say where a differing value
     * came from. Nothing scores from it. A test pins that it covers every field once.
     *
     * @var array<string, string|list<string>>
     */
    public const ORIGIN = [
        'listingKey' => self::ORIGIN_IDENTITY,

        'latitude'        => V::LOCATION_LATITUDE,
        'longitude'       => V::LOCATION_LONGITUDE,
        'city'            => V::LOCATION_CITY,
        'stateOrProvince' => V::LOCATION_STATE,
        'postalCode'      => V::LOCATION_POSTAL_CODE,
        'countyOrParish'  => V::LOCATION_COUNTY,

        'listPrice'      => V::LISTING_LIST_PRICE,
        'leaseFrequency' => V::LISTING_LEASE_AMOUNT_FREQUENCY,

        'livingArea'        => V::PROPERTY_LIVING_AREA_SQFT,
        'lotSizeSqft'       => self::ORIGIN_RESIDUAL,
        'yearBuilt'         => V::PROPERTY_YEAR_BUILT,
        'buildingAreaTotal' => self::ORIGIN_RESIDUAL,

        'propertyType'    => [V::PROPERTY_TYPE, V::LISTING_TRANSACTION_TYPE],
        'propertySubType' => self::ORIGIN_RESIDUAL,

        'poolPrivate' => V::PROPERTY_POOL,
        'garage'      => V::PROPERTY_GARAGE,
        'waterfront'  => self::WATERFRONT,
        'view'        => self::ORIGIN_RESIDUAL,
        'waterView'   => self::ORIGIN_RESIDUAL,

        'associationFee'          => self::ORIGIN_RESIDUAL,
        'associationFeeFrequency' => self::ORIGIN_RESIDUAL,
        'association'             => self::ORIGIN_RESIDUAL,
        'taxAnnualAmount'         => self::ORIGIN_RESIDUAL,
        'cdd'                     => self::ORIGIN_RESIDUAL,

        'newConstruction'               => self::ORIGIN_RESIDUAL,
        'petsAllowed'                   => self::ORIGIN_RESIDUAL,
        'communityFeatures'             => self::ORIGIN_RESIDUAL,
        'associationAmenities'          => self::ORIGIN_RESIDUAL,
        'greenEnergyEfficient'          => self::ORIGIN_RESIDUAL,
        'greenBuildingVerificationType' => self::ORIGIN_RESIDUAL,
        'leaseTerm'                     => self::ORIGIN_RESIDUAL,

        'daysOnMarket'    => self::ORIGIN_RESIDUAL,
        'floodZoneStated' => self::ORIGIN_RESIDUAL,
        'schoolsListed'   => self::ORIGIN_RESIDUAL,

        'smartTags' => self::ORIGIN_ATTACHED_SMART_TAGS,
    ];

    /** The canonical EXTENSION key for waterfront (declared in CanonicalListingVocabulary). */
    private const WATERFRONT = 'property.waterfront';

    public static function build(CanonicalListing $listing, ListingMatchResidualFacts $residual): ?ListingMatchFacts
    {
        $listingKey = $listing->mlsListingKey();

        if ($listing->mlsNativeIdentity() === null || $listingKey === null) {
            return null;
        }

        $coordinates = $listing->coordinates();
        $listPrice   = $listing->listPrice();
        $livingArea  = $listing->livingAreaSqft();
        $waterfront  = $listing->get(self::WATERFRONT);

        return new ListingMatchFacts(
            listingKey: $listingKey,

            latitude:        $coordinates === null ? null : self::decimal($coordinates['lat'], 7),
            longitude:       $coordinates === null ? null : self::decimal($coordinates['lng'], 7),
            city:            $listing->city(),
            stateOrProvince: $listing->state(),
            postalCode:      $listing->postalCode(),
            countyOrParish:  $listing->county(),

            listPrice:      $listPrice === null ? null : self::decimal($listPrice, 2),
            leaseFrequency: $listing->leaseAmountFrequency(),

            livingArea:        $livingArea === null ? null : (int) round($livingArea),
            lotSizeSqft:       $residual->lotSizeSqft,
            yearBuilt:         $listing->yearBuilt(),
            buildingAreaTotal: $residual->buildingAreaTotal,

            propertyType:    PropertyTypeVocabulary::recognisedTypeFor($listing->propertyType(), $listing->transactionType()),
            propertySubType: $residual->propertySubType,

            poolPrivate: $listing->hasPool(),
            garage:      $listing->hasGarage(),
            waterfront:  is_bool($waterfront) ? $waterfront : null,
            view:        $residual->view,
            waterView:   $residual->waterView,

            associationFee:          $residual->associationFee,
            associationFeeFrequency: $residual->associationFeeFrequency,
            association:             $residual->association,
            taxAnnualAmount:         $residual->taxAnnualAmount,
            cdd:                     $residual->cdd,

            newConstruction:               $residual->newConstruction,
            petsAllowed:                   $residual->petsAllowed,
            communityFeatures:             $residual->communityFeatures,
            associationAmenities:          $residual->associationAmenities,
            greenEnergyEfficient:          $residual->greenEnergyEfficient,
            greenBuildingVerificationType: $residual->greenBuildingVerificationType,
            leaseTerm:                     $residual->leaseTerm,

            daysOnMarket:    $residual->daysOnMarket,
            floodZoneStated: $residual->floodZoneStated,
            schoolsListed:   $residual->schoolsListed,

            // Unattached: the caller attaches resolved tags later, beside the row.
            smartTags: null,
        );
    }

    /** A number in the fixed-point string form a `decimal:N` column hands the rules. */
    private static function decimal(float $value, int $places): string
    {
        return number_format($value, $places, '.', '');
    }
}
