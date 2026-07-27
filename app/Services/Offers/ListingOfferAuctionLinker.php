<?php

namespace App\Services\Offers;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves — and when absent creates — the OfferAuction linked 1:1 to a Seller
 * or Landlord listing via the listing meta `linked_offer_auction_id`.
 *
 * This exists because bidding activation needs the link to exist at publish
 * time, and the Landlord half of it did not: the link was created only as a
 * side effect of rendering the public page. Publishing and the backfill command
 * now both create it through here, so they cannot produce differently-shaped
 * rows.
 *
 * SCOPE — this is NOT the only creation path in the codebase. The two Seller
 * Livewire components retain their own private ensureLinkedOfferAuction(),
 * which writes the same Seller payload (`user_id` only) and runs on every save.
 * Both implementations resolve an existing link before creating, so they cannot
 * fight; consolidating them is deliberately left out of scope here.
 *
 * The per-role bodies reproduce the payload each role already wrote, so routing
 * an existing caller through this service is behaviour preserving. Landlord rows
 * in particular must keep `offer_type` and `linked_landlord_auction_id` — the
 * offer detail page reads them to pre-fill the tenant application with the
 * landlord's asking terms.
 */
class ListingOfferAuctionLinker
{
    /**
     * Read the linked OfferAuction without ever writing.
     */
    public function resolve(Model $listing): ?OfferAuction
    {
        $linkedId = $listing->info('linked_offer_auction_id');

        if (! $linkedId) {
            return null;
        }

        return OfferAuction::find((int) $linkedId) ?: null;
    }

    /**
     * Return the linked OfferAuction, creating one on first call.
     *
     * @param  string  $role  'seller' or 'landlord'.
     */
    public function ensureFor(Model $listing, string $role): OfferAuction
    {
        $existing = $this->resolve($listing);

        if ($existing) {
            return $existing;
        }

        $offerAuction = $role === 'landlord'
            ? $this->createForLandlord($listing)
            : $this->createForSeller($listing);

        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return $offerAuction;
    }

    /**
     * Reverse the link: find the listing an OfferAuction belongs to.
     *
     * The two roles record the association differently — Landlord OfferAuctions
     * carry a `linked_landlord_auction_id` meta pointing forward, while Seller
     * listings only point at the OfferAuction, so the seller lookup has to walk
     * the meta table backwards. Both directions are checked here so callers do
     * not have to know which role they are dealing with.
     *
     * @return array{0: ?Model, 1: ?string}  [listing, role] — [null, null] when unlinked.
     */
    public function listingFor(?OfferAuction $offerAuction): array
    {
        if ($offerAuction === null) {
            return [null, null];
        }

        $landlordId = $offerAuction->info('linked_landlord_auction_id');

        if ($landlordId) {
            $landlord = LandlordAgentAuction::with('meta')->find((int) $landlordId);

            if ($landlord) {
                return [$landlord, 'landlord'];
            }
        }

        $metaRow = SellerAgentAuctionMeta::where('meta_key', 'linked_offer_auction_id')
            ->where('meta_value', (string) $offerAuction->id)
            ->first();

        if ($metaRow) {
            $seller = SellerAgentAuction::with('meta')->find($metaRow->seller_agent_auction_id);

            if ($seller) {
                return [$seller, 'seller'];
            }
        }

        return [null, null];
    }

    private function createForSeller(SellerAgentAuction|Model $listing): OfferAuction
    {
        return OfferAuction::create(['user_id' => $listing->user_id]);
    }

    private function createForLandlord(LandlordAgentAuction|Model $listing): OfferAuction
    {
        $offerAuction = OfferAuction::create([
            'user_id'     => $listing->user_id,
            'title'       => $listing->title ?: ($listing->info('listing_title') ?: 'Rental Property'),
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $offerAuction->saveMeta('offer_type', 'rental');
        $offerAuction->saveMeta('linked_landlord_auction_id', $listing->id);

        return $offerAuction;
    }
}
