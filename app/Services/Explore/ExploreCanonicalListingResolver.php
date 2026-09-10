<?php

namespace App\Services\Explore;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Services\Listing\ListingWorkflowResolver;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Support\Listing\ListingWorkflow;

/**
 * Does this MLS record already have a BidYourOffer listing, and if so where?
 *
 * EXPLORE LINKS TO THE CANONICAL PAGE. IT DOES NOT REBUILD IT.
 * -----------------------------------------------------------
 * FOR SALE resolves to the existing Seller Offer Listing page and FOR RENT to
 * the existing Landlord one. Neither is duplicated inside Explore, and Explore
 * creates nothing: clicking a house must never materialise a listing, so this
 * class only ever reads.
 *
 * THE LINK IS THE IMPORT PROVENANCE ALREADY BEING WRITTEN
 * ------------------------------------------------------
 * {@see MlsQuickImportDraftWriter::META_LISTING_KEY} — the `mls_listing_key`
 * EAV row — is what ties a listing to its MLS record, and it is written by the
 * one class that creates MLS-linked listings. No new linkage table, no new
 * column, no second source of truth.
 *
 * ROLE IS CHOSEN BY TRANSACTION TYPE, NOT BY WHICHEVER TABLE ANSWERS
 * -----------------------------------------------------------------
 * A lease record resolves against Landlord listings and a sale record against
 * Seller ones. Searching both and taking whatever matched would let a stray
 * Seller listing carrying a lease record's key send a consumer looking at a
 * rental to a purchase page.
 *
 * PUBLIC MEANS PUBLIC
 * -------------------
 * Only a listing that an anonymous visitor can actually open is offered:
 * approved, not a draft, not archived — the same three conditions
 * SellerOfferListingController::view() and its landlord twin enforce, and they
 * are enforced here so Explore never advertises a link that 404s. A draft
 * belonging to someone is invisible to Explore entirely.
 *
 * PRODUCT, NOT JUST TABLE
 * -----------------------
 * The four `*_agent_auctions` tables hold both Offer Listings and Hire-an-Agent
 * records. `forWorkflow()` narrows in SQL and {@see ListingWorkflowResolver}
 * decides in PHP — both halves, in that order, exactly as the trait's own
 * documentation requires. A Hire listing must never be offered as a property
 * page.
 *
 * BATCHED
 * -------
 * One query per role per page of markers, not one per marker. A viewport of 150
 * properties costs four queries, not three hundred.
 */
class ExploreCanonicalListingResolver
{
    public function __construct(
        private readonly ListingWorkflowResolver $workflowResolver,
    ) {}

    /**
     * @param  array<string,ExploreTransactionType>  $typeByListingKey
     * @return array<string,array{role:string,listing_id:int,url:string}>  keyed by MLS ListingKey
     */
    public function resolveMany(array $typeByListingKey): array
    {
        $byRole = ['seller' => [], 'landlord' => []];

        foreach ($typeByListingKey as $listingKey => $type) {
            $listingKey = (string) $listingKey;

            if ($listingKey === '') {
                continue;
            }

            $byRole[$type->internalRole()][] = $listingKey;
        }

        $resolved = [];

        foreach ($byRole as $role => $keys) {
            if ($keys === []) {
                continue;
            }

            foreach ($this->resolveRole($role, array_values(array_unique($keys))) as $key => $link) {
                $resolved[$key] = $link;
            }
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $listingKeys
     * @return array<string,array{role:string,listing_id:int,url:string}>
     */
    private function resolveRole(string $role, array $listingKeys): array
    {
        [$metaClass, $modelClass, $foreignKey, $routeName] = match ($role) {
            'seller' => [
                SellerAgentAuctionMeta::class,
                SellerAgentAuction::class,
                'seller_agent_auction_id',
                'offer.listing.seller.view',
            ],
            'landlord' => [
                LandlordAgentAuctionMeta::class,
                LandlordAgentAuction::class,
                'landlord_agent_auction_id',
                'offer.listing.landlord.view',
            ],
            default => [null, null, null, null],
        };

        if ($metaClass === null) {
            return [];
        }

        /** @var array<int,string> $listingKeyByAuctionId */
        $listingKeyByAuctionId = [];

        $metaClass::query()
            ->where('meta_key', Meta::META_LISTING_KEY)
            ->whereIn('meta_value', $listingKeys)
            ->get([$foreignKey, 'meta_value'])
            ->each(function ($row) use (&$listingKeyByAuctionId, $foreignKey): void {
                $listingKeyByAuctionId[(int) $row->{$foreignKey}] = (string) $row->meta_value;
            });

        if ($listingKeyByAuctionId === []) {
            return [];
        }

        $candidates = $modelClass::query()
            ->whereIn('id', array_keys($listingKeyByAuctionId))
            ->where('is_approved', true)
            ->where('is_draft', false)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->forWorkflow(ListingWorkflow::OFFER_LISTING)
            ->orderBy('id')
            ->get();

        $resolved = [];

        foreach ($candidates as $auction) {
            if (! $this->workflowResolver->matches($auction, ListingWorkflow::OFFER_LISTING)) {
                continue;
            }

            $listingKey = $listingKeyByAuctionId[(int) $auction->id] ?? null;

            if ($listingKey === null || isset($resolved[$listingKey])) {
                continue;
            }

            $resolved[$listingKey] = [
                'role'       => $role,
                'listing_id' => (int) $auction->id,
                'url'        => route($routeName, ['id' => $auction->id]),
            ];
        }

        return $resolved;
    }
}
