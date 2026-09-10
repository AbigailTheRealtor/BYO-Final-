<?php

namespace App\Services\Explore;

/**
 * One property as a consumer is allowed to receive it.
 *
 * THIS CLASS IS THE SECURITY BOUNDARY.
 * ------------------------------------
 * Explore never serialises a Bridge record. `return $bridgeProperty;` and
 * anything equivalent — spreading raw_json, forwarding an unfiltered array,
 * adding a "debug" passthrough — is the failure this type exists to make
 * structurally impossible. Every field a consumer can receive is a named,
 * typed, readonly property here, so a field reaches the browser only by
 * somebody adding it to this list on purpose.
 *
 * That is an ALLOW-LIST, not a scrub. A deny-list of prohibited names would
 * have to be complete forever against a 553-field feed that gains fields
 * without asking us; this has to be complete against nothing.
 *
 * WHAT IS DELIBERATELY ABSENT, AND WHY IT WOULD MATTER
 * ---------------------------------------------------
 * The feed genuinely carries all of these, in raw_json, on records Explore
 * publishes: STELLAR_TenantName and STELLAR_TenantPhone (occupant identity and
 * contact), LockBoxLocation / LockBoxSerialNumber / LockBoxType and
 * STELLAR_ShowingRequirements / STELLAR_ShowingConsiderations / ShowingInstructions
 * (physical access to somebody's home), PrivateRemarks,
 * STELLAR_RealtorInfoConfidential and STELLAR_SoldRemarks (broker-only prose),
 * ListingTerms (the listing agreement), PublicRemarks and SyndicationRemarks
 * (authored prose withheld on licensing grounds), and every listing-agent and
 * listing-office contact field. None of them has a property here, so none of
 * them has a route to a response.
 *
 * PROVIDER IDENTITY IS RETAINED, SERVER-SIDE MEANING INTACT
 * --------------------------------------------------------
 * `provider` travels so a second MLS can be added later without an Explore
 * rewrite, and `attribution` travels because the licence requires it. Neither
 * is a switch the front end branches on: presentation is identical whoever
 * supplied the row.
 *
 * NULLS ARE THE CONTRACT, NOT AN OVERSIGHT
 * ----------------------------------------
 * `address` is null on a record whose feed forbids displaying it (71 of 1,203
 * live records), and the marker still appears — a withheld address is not a
 * withheld listing. `matchScore` is null because no reliable score exists for
 * an MLS-only property, and inventing one is worse than omitting it.
 */
final class ExploreListingProjection
{
    /**
     * @param list<string> $photoUrls
     */
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $propertyId,
        public readonly ExploreTransactionType $transactionType,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $effectiveStatus,
        public readonly ?string $displayPrice,
        public readonly ?float $priceValue,
        public readonly ?string $priceQualifier,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postalCode,
        public readonly ?int $beds,
        public readonly ?int $baths,
        public readonly ?int $livingArea,
        public readonly ?string $propertyType,
        public readonly ?string $propertySubType,
        public readonly ?string $primaryThumbnail,
        public readonly bool $hasPhotos,
        public readonly int $photoCount,
        public readonly bool $hasVirtualTour,
        public readonly ?string $virtualTourUrl,
        public readonly bool $hasVideo,
        public readonly ?string $videoUrl,
        public readonly ?string $canonicalUrl,
        public readonly ?string $detailUrl,
        public readonly bool $showingAvailable,
        public readonly string $attribution,
        public readonly ?float $matchScore,
        public readonly array $photoUrls,
    ) {}

    /**
     * The wire shape. snake_case because it crosses into JavaScript.
     *
     * Every key is written out by hand. There is no dynamic expansion, no
     * get_object_vars(), and no "extra" bag — a projection that could grow keys
     * from its input would not be a boundary.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'provider'          => $this->provider,
            'property_id'       => $this->propertyId,
            'transaction_type'  => $this->transactionType->value,
            'latitude'          => $this->latitude,
            'longitude'         => $this->longitude,
            'effective_status'  => $this->effectiveStatus,
            'display_price'     => $this->displayPrice,
            'price_value'       => $this->priceValue,
            'price_qualifier'   => $this->priceQualifier,
            'address'           => $this->address,
            'city'              => $this->city,
            'state'             => $this->state,
            'postal_code'       => $this->postalCode,
            'beds'              => $this->beds,
            'baths'             => $this->baths,
            'living_area'       => $this->livingArea,
            'property_type'     => $this->propertyType,
            'property_subtype'  => $this->propertySubType,
            'primary_thumbnail' => $this->primaryThumbnail,
            'has_photos'        => $this->hasPhotos,
            'photo_count'       => $this->photoCount,
            'photo_urls'        => $this->photoUrls,
            'has_virtual_tour'  => $this->hasVirtualTour,
            'virtual_tour_url'  => $this->virtualTourUrl,
            'has_video'         => $this->hasVideo,
            'video_url'         => $this->videoUrl,
            'canonical_url'     => $this->canonicalUrl,
            'detail_url'        => $this->detailUrl,
            'showing_available' => $this->showingAvailable,
            'attribution'       => $this->attribution,
            'match_score'       => $this->matchScore,
        ];
    }
}
