<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use Illuminate\Http\Request;

class LandlordOfferListingController extends Controller
{
    public function searchOfferListings(Request $request)
    {
        $page_data['title'] = 'Rental Properties';

        $auctions = LandlordAgentAuction::query()
            ->selectRaw("*, (SELECT meta_value FROM landlord_agent_auction_metas WHERE landlord_agent_auction_metas.landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where('is_approved', true)
            ->where('is_draft', false)
            // Safety guard: never surface a record explicitly stamped hire_agent
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'hire_agent');
            })
            // Primary: workflow_type = offer_listing (strict — no meta-key fallback available
            // because HireLandLordAgent/LandLordAgentAuction Livewire writes identical meta keys
            // to LandlordOfferListing, making any key-presence check ambiguous for legacy records)
            ->whereHas('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
            });

        if (!empty($request->title)) {
            $auctions->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->title . '%')
                  ->orWhereHas('meta', function ($m) use ($request) {
                      $m->where('meta_key', 'address')
                        ->where('meta_value', 'like', '%' . $request->title . '%');
                  });
            });
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
                $meta->where('meta_key', 'property_type')
                     ->where('meta_value', 'LIKE', '%' . $request->property_type . '%');
            });
        }

        $sort = $request->sort ?? 'newest';
        if ($sort === 'most_viewed') {
            $auctions->orderByRaw('(SELECT COUNT(*) FROM landlord_agent_auction_bids WHERE landlord_agent_auction_bids.landlord_agent_auction_id = landlord_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (landlord_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (landlord_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM landlord_agent_auction_metas
                             WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM landlord_agent_auction_metas
                        WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM landlord_agent_auction_metas
                            WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM landlord_agent_auction_metas
                        WHERE landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, landlord_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = (clone $auctions)->count();
        $page_data['pAuctions'] = $auctions->paginate(12);

        return view('offer-listing.landlord.search', $page_data);
    }
}
