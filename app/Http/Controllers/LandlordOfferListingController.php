<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use Illuminate\Http\Request;

class LandlordOfferListingController extends Controller
{
    /**
     * Meta keys that are exclusive to Landlord Offer Listings (Full Service).
     * Used as a fallback identifier for records that pre-date the workflow_type stamp.
     * These keys are saved only from the Create Landlord Listing form — never from
     * the Hire Landlord's Agent flow — so their presence positively identifies an
     * Offer Listing when the workflow_type stamp is absent.
     */
    public const OFFER_LISTING_META_KEYS = [
        'desired_rental_amount',
        'lease_amount_frequency',
        'tenant_require',
        'listing_date',
        'auction_type',
        'property_photos',
    ];

    /**
     * Resolve a LandlordAgentAuction by ID and confirm it is a Landlord Offer Listing.
     *
     * Primary path  — workflow_type === 'offer_listing' (all records after stamp was introduced).
     * Fallback path — presence of Offer-Listing-exclusive meta keys for older records.
     * Any other record (hire_agent, null, unknown, etc.) results in abort(404).
     */
    private function resolveOfferListing(int|string $id): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::with('meta')->find($id);

        if (!$auction) {
            abort(404, 'Listing not found.');
        }

        $workflowType = $auction->info('workflow_type');

        // ── Step 1: Hard-block any Hire Agent record immediately. ─────────────────
        // No fallback is allowed for hire_agent — abort before any meta-key checks.
        if ($workflowType === 'hire_agent') {
            abort(404, 'Listing not found.');
        }

        // ── Step 2: Positive confirmation via workflow_type stamp. ────────────────
        if ($workflowType === 'offer_listing') {
            return $auction;
        }

        // ── Step 3: Fallback — ONLY for unstamped legacy records. ─────────────────
        // Applies exclusively when workflow_type is null/false/empty (stamp was never
        // written). Any other explicit non-offer value (unknown future types) is also
        // blocked here to prevent unintended exposure.
        $isUnstamped = ($workflowType === null || $workflowType === false || $workflowType === '');

        if (!$isUnstamped) {
            // Explicit non-offer type (not hire_agent, not offer_listing) — hard block.
            abort(404, 'Listing not found.');
        }

        // ── Step 4: Verify the unstamped record is a genuine Offer Listing. ───────
        // Presence of any Offer-Listing-exclusive meta key is sufficient positive
        // confirmation. These keys are written only by the Create Landlord Listing form.
        $isLegacyOfferListing =
            $auction->meta->contains('meta_key', 'desired_rental_amount')
            || $auction->meta->contains('meta_key', 'lease_amount_frequency')
            || $auction->meta->contains('meta_key', 'tenant_require')
            || $auction->meta->contains('meta_key', 'listing_date')
            || $auction->meta->contains('meta_key', 'auction_type')
            || $auction->meta->contains('meta_key', 'property_photos');

        if (!$isLegacyOfferListing) {
            abort(404, 'Listing not found.');
        }

        return $auction;
    }

    public function view(int|string $id)
    {
        $auction = $this->resolveOfferListing($id);

        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        $page_data = [
            'title' => $auction->title ?? ($meta['listing_title'] ?? 'Rental Property Listing'),
        ];

        return view('offer-listing.landlord.view', compact('auction', 'meta') + $page_data);
    }

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
