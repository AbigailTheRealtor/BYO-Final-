<?php

namespace App\Services\Explore;

use App\Models\BridgeProperty;
use App\Services\ListingImport\Mls\MlsDisplayPermissions;
use App\Support\Listing\PropertyTypeVocabulary;

/**
 * Turns an eligible Bridge record into the one shape a consumer may receive.
 *
 * Everything the browser sees is decided here, on the server. The projector
 * never emits a value it did not explicitly choose to emit, and it emits null
 * rather than a guess wherever the permitted answer is "we do not know".
 *
 * PRICE IS THE PART MOST WORTH READING
 * ------------------------------------
 * `ListPrice` is not one number with one meaning. On a sale record it is a
 * purchase price. On a LEASE record it IS the periodic rent — the landlord
 * quick-import path documents this and refuses to seed a rent box from anything
 * it cannot positively identify as a lease, for exactly the reason that matters
 * here: a sale price printed under "FOR RENT" is a catastrophic display error
 * and a plausible-looking one.
 *
 * And the period is not always a month. `LeaseAmountFrequency` in the live cache
 * is Monthly 386, Seasonal 78, Annually 19, Weekly 16, Daily 2 — so "/mo" is
 * wrong for roughly a quarter of the rental inventory. The frequency is read,
 * normalised through the existing {@see \App\Services\ListingImport\MlsNormalizer}
 * vocabulary, and rendered as the suffix the feed actually reports. A rental
 * with NO stated frequency gets no suffix at all rather than an assumed one.
 *
 * ADDRESS SUPPRESSION IS NOT MARKER SUPPRESSION
 * ---------------------------------------------
 * `InternetAddressDisplayYN = false` (71 of 1,203 live records) removes the
 * street line, the unit and the postal code — everything that reconstructs the
 * address — and leaves the marker, the price and the property facts. The MLS
 * instruction is "publish this listing without its address", not "do not
 * publish this listing". City and state are kept because they do not
 * reconstruct a street address and the listing is already pinned to a
 * coordinate the feed itself supplied for public display.
 *
 * PROPERTY TYPE IS TRANSLATED, NOT ECHOED
 * ---------------------------------------
 * Through {@see PropertyTypeVocabulary}, so Explore says the same words as the
 * rest of the platform rather than RESO's. The transaction type is decided
 * separately and exactly — see {@see ExploreTransactionType} for why the two
 * cannot be the same lookup.
 */
class ExploreListingProjector
{
    public const PROVIDER_STELLAR_BRIDGE = 'stellar_bridge';

    public const ATTRIBUTION = 'Information provided by Stellar MLS via Bridge Data Output. '
        . 'All information is deemed reliable but not guaranteed and should be independently verified.';

    public function __construct(
        private readonly ExploreMediaCapability $media,
        private readonly ExplorePropertyIdentity $identity,
    ) {}

    /**
     * @param  array<string,mixed>  $raw
     * @param  array{role:string,listing_id:int,url:string}|null  $canonical
     */
    public function project(
        BridgeProperty $listing,
        array $raw,
        ExploreTransactionType $type,
        ExploreAccessTier $tier,
        ?array $canonical = null,
        ?string $detailUrl = null,
    ): ExploreListingProjection {
        $permissions = MlsDisplayPermissions::fromRecord($raw);
        $addressOk   = $permissions->addressDisplayable();

        $coordinates = [(float) $listing->latitude, (float) $listing->longitude];
        $media       = $this->media->for($raw, $type);
        $price       = $this->price($listing, $raw, $type);
        $identity    = $this->identity->for($listing, $raw);

        return new ExploreListingProjection(
            id:               (string) ($listing->listing_key ?? ''),
            provider:         self::PROVIDER_STELLAR_BRIDGE,
            propertyId:       $identity['opaque_id'],
            transactionType:  $type,
            latitude:         $coordinates[0],
            longitude:        $coordinates[1],
            effectiveStatus:  (string) ($listing->standard_status ?? ''),
            displayPrice:     $price['display'],
            priceValue:       $price['value'],
            priceQualifier:   $price['qualifier'],
            address:          $addressOk ? $this->addressLine($listing, $raw) : null,
            city:             $this->nullableString($listing->city),
            state:            $this->nullableString($listing->state_or_province),
            postalCode:       $addressOk ? $this->nullableString($listing->postal_code) : null,
            beds:             $this->nullableInt($listing->bedrooms_total),
            baths:            $this->nullableInt($listing->bathrooms_total_integer),
            livingArea:       $this->nullableInt($listing->living_area),
            propertyType:     $this->displayPropertyType($listing, $type),
            propertySubType:  $this->nullableString($listing->property_sub_type),
            primaryThumbnail: $media['primary_thumbnail'],
            hasPhotos:        $media['has_photos'],
            photoCount:       $media['photo_count'],
            hasVirtualTour:   $media['has_virtual_tour'],
            virtualTourUrl:   $media['virtual_tour_url'],
            hasVideo:         $media['has_video'],
            videoUrl:         $media['video_url'],
            canonicalUrl:     $canonical['url'] ?? null,
            detailUrl:        $detailUrl,
            // A showing can only be requested against a real BidYourOffer
            // listing, through the existing authenticated flow. An MLS-only
            // property has no showing workflow and must not offer one.
            showingAvailable: $canonical !== null,
            attribution:      self::ATTRIBUTION,
            // No reliable Location DNA / match score exists for a property with
            // no BidYourOffer listing, and Phase 1 does not invent one. The
            // field travels as null so a later phase fills it rather than
            // adding it.
            matchScore:       null,
            photoUrls:        $tier->isPublic() ? array_slice($this->media->photos($raw, $type), 0, 12) : [],
        );
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array{display:?string, value:?float, qualifier:?string}
     */
    private function price(BridgeProperty $listing, array $raw, ExploreTransactionType $type): array
    {
        $value = $listing->list_price;

        if ($value === null || ! is_numeric($value)) {
            return ['display' => null, 'value' => null, 'qualifier' => null];
        }

        $value = (float) $value;

        if ($value <= 0.0) {
            return ['display' => null, 'value' => null, 'qualifier' => null];
        }

        $amount = '$' . number_format($value, 0, '.', ',');

        if ($type === ExploreTransactionType::SALE) {
            return ['display' => $amount, 'value' => $value, 'qualifier' => null];
        }

        $qualifier = $this->leasePeriodSuffix($raw);

        return [
            'display'   => $qualifier === null ? $amount : $amount . $qualifier,
            'value'     => $value,
            'qualifier' => $qualifier,
        ];
    }

    /**
     * The "/mo" (or "/wk", "/yr", …) suffix a rental price carries.
     *
     * Null when the feed states no frequency — 724 of 1,225 cached rows carry
     * none, and every one of those is a sale record, so in practice a rental
     * with no frequency is rare. When it happens the price is shown WITHOUT a
     * period rather than with an assumed monthly one: a seasonal rental
     * advertised as a monthly figure misprices the property by a factor.
     *
     * @param  array<string,mixed>  $raw
     */
    private function leasePeriodSuffix(array $raw): ?string
    {
        $frequency = $raw['LeaseAmountFrequency'] ?? null;

        if (! is_string($frequency) || trim($frequency) === '') {
            return null;
        }

        return match (\App\Services\ListingImport\MlsNormalizer::normalizeLeaseFrequency($frequency)) {
            'monthly', 'month_to_month', '12_months', '24_months', '6_to_12_months' => '/mo',
            'weekly'    => '/wk',
            'daily'     => '/day',
            'annually'  => '/yr',
            'seasonal'  => ' seasonal',
            'short_term' => ' short term',
            default     => null,
        };
    }

    /** @param array<string,mixed> $raw */
    private function addressLine(BridgeProperty $listing, array $raw): ?string
    {
        $street = $this->nullableString($listing->unparsed_address) ?? $this->nullableString($raw['UnparsedAddress'] ?? null);

        if ($street === null) {
            return null;
        }

        $unit = $this->nullableString($raw['UnitNumber'] ?? null);

        return $unit === null ? $street : $street . ' #' . $unit;
    }

    private function displayPropertyType(BridgeProperty $listing, ExploreTransactionType $type): ?string
    {
        $source = $this->nullableString($listing->property_type);

        return $source === null
            ? null
            : PropertyTypeVocabulary::forRole($source, $type->internalRole());
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
