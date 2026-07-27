<?php

namespace App\Services\Offers;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
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
     * Criteria roles address their OfferAuction by a deterministic listing_id
     * key rather than by meta alone.
     *
     * offer_auctions.listing_id carries a UNIQUE index, so "buyer_criteria:{id}"
     * can only ever name one row. That is what makes a second auction for the
     * same listing impossible no matter which path runs first — publication or a
     * legacy first-offer submission.
     */
    private const CRITERIA_KEY_PREFIX = [
        'buyer'  => 'buyer_criteria:',
        'tenant' => 'tenant_criteria:',
    ];

    /**
     * The canonical listing_id key for a criteria listing, or null for the roles
     * that key their auctions by meta instead.
     */
    public static function criteriaKey(string $role, int $listingId): ?string
    {
        $prefix = self::CRITERIA_KEY_PREFIX[$role] ?? null;

        return $prefix ? $prefix . $listingId : null;
    }

    /**
     * Return the linked OfferAuction, creating one on first call.
     *
     * @param  string  $role  'seller', 'landlord', 'buyer' or 'tenant'.
     */
    public function ensureFor(Model $listing, string $role): OfferAuction
    {
        $existing = $this->resolve($listing);

        if ($existing) {
            return $existing;
        }

        if (isset(self::CRITERIA_KEY_PREFIX[$role])) {
            return $this->ensureForCriteria($listing, $role);
        }

        $offerAuction = $role === 'landlord'
            ? $this->createForLandlord($listing)
            : $this->createForSeller($listing);

        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return $offerAuction;
    }

    /**
     * Buyer / Tenant criteria listings.
     *
     * firstOrCreate on the unique listing_id key reproduces exactly the row
     * OfferController::resolveOfferAuctionId() has always produced, so an auction
     * created earlier by a first-offer submission is ADOPTED rather than
     * duplicated, and every offer already attached to it keeps resolving.
     *
     * The linked_offer_auction_id meta is written too, so these listings answer
     * resolve() the same way Seller and Landlord do and the shared search-card
     * window map needs no role-specific branch.
     */
    private function ensureForCriteria(Model $listing, string $role): OfferAuction
    {
        $offerAuction = OfferAuction::firstOrCreate(
            ['listing_id' => self::criteriaKey($role, (int) $listing->id)],
            [
                'user_id'     => $listing->user_id,
                'title'       => $listing->title,
                'is_draft'    => false,
                'is_approved' => true,
            ]
        );

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

        // Criteria roles are addressed by the listing_id key, not by meta. The
        // server-side bidding guards reach the listing through here, so without
        // this branch a Buyer/Tenant window could never be enforced.
        $listingId = (string) ($offerAuction->listing_id ?? '');

        foreach (self::CRITERIA_KEY_PREFIX as $role => $prefix) {
            if (! str_starts_with($listingId, $prefix)) {
                continue;
            }

            $sourceId = (int) substr($listingId, strlen($prefix));
            $model    = $role === 'buyer' ? BuyerAgentAuction::class : TenantAgentAuction::class;
            $listing  = $model::with('meta')->find($sourceId);

            if ($listing) {
                return [$listing, $role];
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
