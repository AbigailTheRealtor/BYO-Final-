<?php

namespace App\Support\SmartTags;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;

/**
 * THE registry of `listing_type` strings Smart Tags may store.
 *
 * Every Smart Tag table addresses a listing by (listing_type, listing_id), the
 * convention every multi-source table in this codebase already uses. This enum
 * is the only producer of those strings, so a caller cannot invent one.
 *
 * NEVER `seller` OR `landlord`. Those strings already name two different tables
 * in two different subsystems — `seller` is PropertyAuction in Location DNA and
 * SellerAgentAuction in Ask AI — and AcceptedBidSummary is written with one and
 * read with the other. Smart Tags uses the table-specific names only.
 *
 * `bridge` addresses bridge_properties.id, exactly as Location DNA does. The
 * surrogate id is stable because the importer upserts on listing_key.
 *
 * Native rows are Offer Listings only (`workflow_type = offer_listing`). The
 * same tables hold Hire Agent rows, which never receive property tags.
 */
enum SmartTagListingType: string
{
    case Bridge        = 'bridge';
    case SellerAgent   = 'seller_agent';
    case LandlordAgent = 'landlord_agent';

    /** @return class-string */
    public function modelClass(): string
    {
        return match ($this) {
            self::Bridge        => BridgeProperty::class,
            self::SellerAgent   => SellerAgentAuction::class,
            self::LandlordAgent => LandlordAgentAuction::class,
        };
    }

    /** A first-party BidYourOffer listing (as opposed to an MLS row). */
    public function isNative(): bool
    {
        return $this !== self::Bridge;
    }

    /** The form role whose property_type vocabulary this listing uses. */
    public function role(): ?string
    {
        return match ($this) {
            self::Bridge        => null,
            self::SellerAgent   => 'seller',
            self::LandlordAgent => 'landlord',
        };
    }

    /**
     * The contexts a listing of this type can be in at all.
     *
     * @return SmartTagContext[]
     */
    public function possibleContexts(): array
    {
        return match ($this) {
            self::Bridge => SmartTagContext::cases(),
            self::SellerAgent => [
                SmartTagContext::ResidentialSale,
                SmartTagContext::IncomeSale,
                SmartTagContext::CommercialSale,
                SmartTagContext::BusinessSale,
                SmartTagContext::LandSale,
            ],
            self::LandlordAgent => [
                SmartTagContext::ResidentialLease,
                SmartTagContext::CommercialLease,
            ],
        };
    }

    /**
     * Which evidence sources may exist for a listing of this type.
     *
     * @return SmartTagSource[]
     */
    public function allowedSources(): array
    {
        return match ($this) {
            self::Bridge => [SmartTagSource::StructuredMls, SmartTagSource::MlsRemarks],
            self::SellerAgent, self::LandlordAgent => [
                SmartTagSource::StructuredNativeListing,
                SmartTagSource::NativeListingDescription,
                SmartTagSource::ManualListingOwner,
            ],
        };
    }

    public function allowsSource(SmartTagSource $source): bool
    {
        return in_array($source, $this->allowedSources(), true);
    }
}
