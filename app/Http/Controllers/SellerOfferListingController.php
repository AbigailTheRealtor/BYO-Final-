<?php

namespace App\Http\Controllers;

use App\Models\SellerAgentAuction;
use Illuminate\Http\Request;

class SellerOfferListingController extends Controller
{
    /**
     * Meta keys that are exclusive to Seller Offer Listings (Full Service).
     * Used as a fallback identifier for records that pre-date the workflow_type stamp.
     */
    public const OFFER_LISTING_META_KEYS = [
        'parcel_id',
        'flood_zone_code',
        'annual_property_taxes',
        'seller_disclosure_available',
        'property_photos',
        'listing_documents',
        'brokerage_relationship',
        'association_type',
        'auction_type',
    ];

    public function view($id)
    {
        $auction = SellerAgentAuction::with(['meta', 'bids.user'])->find($id);

        if (!$auction) {
            abort(404, 'Listing not found');
        }

        $workflowType = $auction->info('workflow_type');

        if ($workflowType === 'offer_listing') {
            // Primary check: workflow_type stamp (all records created after Task #833)
        } elseif (
            // Fallback for older Offer Listing records that pre-date the workflow_type stamp.
            // These meta keys only appear in Full Service Seller Offer Listings, not in
            // Hire Seller's Agent records, so their presence is a safe identifier.
            // Additive OR — any single match is sufficient to recognise an Offer Listing.
            $auction->info('parcel_id')                !== false ||
            $auction->info('flood_zone_code')          !== false ||
            $auction->info('annual_property_taxes')    !== false ||
            $auction->info('seller_disclosure_available') !== false ||
            $auction->info('property_photos')          !== false ||
            $auction->info('listing_documents')        !== false ||
            $auction->info('brokerage_relationship')   !== false ||
            $auction->info('association_type')         !== false ||
            $auction->info('auction_type')             !== false
        ) {
            // Fallback: presence of Offer Listing-specific meta keys
        } else {
            abort(404, 'Listing not found');
        }

        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        $page_data = [
            'title'   => $auction->address ?? ($meta['listing_title'] ?? 'Seller Offer Listing'),
            'id'      => $id,
            'auth_id' => auth()->id(),
        ];

        return view('offer-listing.seller.view', compact('auction', 'meta') + $page_data);
    }

    public function searchOfferListings(Request $request)
    {
        $page_data['title'] = 'Seller Listings';

        $auctions = SellerAgentAuction::query()
            ->selectRaw("*, (SELECT meta_value FROM seller_agent_auction_metas WHERE seller_agent_auction_metas.seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where('is_approved', true)
            ->where('is_draft', false)
            ->where(function ($q) {
                // Primary: workflow_type = offer_listing
                $q->whereHas('meta', function ($m) {
                    $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
                })
                // Fallback: presence of any offer-listing-specific meta key
                ->orWhereHas('meta', function ($m) {
                    $m->whereIn('meta_key', self::OFFER_LISTING_META_KEYS);
                });
            });

        if ($request->title != '') {
            $auctions->where('address', 'like', '%' . $request->title . '%');
        }

        if ($request->bedrooms != '') {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'bedrooms')->where('meta_value', $request->bedrooms);
            });
        }

        if ($request->bathrooms != '') {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'bathrooms')->where('meta_value', $request->bathrooms);
            });
        }

        if ($request->property_type != '') {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'property_type')->where('meta_value', $request->property_type);
            });
        }

        $sort = $request->sort ?? 'newest';
        if ($sort === 'most_viewed') {
            $auctions->orderByRaw('(SELECT COUNT(*) FROM seller_agent_auction_bids WHERE seller_agent_auction_bids.seller_agent_auction_id = seller_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (seller_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (seller_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM seller_agent_auction_metas
                        WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM seller_agent_auction_metas
                            WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM seller_agent_auction_metas
                        WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, seller_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = (clone $auctions)->count();
        $page_data['pAuctions'] = $auctions->paginate(12);

        return view('offer-listing.seller.search', $page_data);
    }
}
