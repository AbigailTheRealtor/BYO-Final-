<?php

namespace App\Http\Controllers;

use App\Models\BuyerAgentAuction;
use App\Models\SellerListingInquiry; // TODO: replace with a neutral OfferListingInquiry model/table when buyer-specific inquiry tracking is needed
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BuyerOfferListingController extends Controller
{
    /**
     * Meta keys that appear exclusively in Buyer Offer Listings and never in
     * Hire Buyer's Agent records. Used as a legacy fallback to identify
     * older offer-listing records that pre-date the workflow_type stamp.
     *
     * These keys are written by BuyerOfferListing/BuyerOfferListingEdit but are
     * absent from HireBuyerAgent/BuyerAgentAuction and BuyerAgentAuctionController.
     *
     * NOTE: brokerage_relationship is intentionally omitted — it is written by
     * both flows (HireLandLordAgent and TenantAgentAuction hire Livewires also
     * write it), so it is not a safe discriminator.
     */
    public const OFFER_LISTING_META_KEYS = [
        'pre_approval_amount',
        'down_payment_type',
    ];

    /**
     * Resolve a BuyerAgentAuction by ID and confirm it is a Buyer Offer Listing.
     * Returns the auction on success or calls abort(404) when the record is absent
     * or belongs to a different workflow (e.g. Hire Agent).
     *
     * @param  int|string  $id
     * @param  bool        $withRelations  Load meta + bids.user when true (view page only).
     */
    private function resolveOfferListing($id, bool $withRelations = false): BuyerAgentAuction
    {
        $query = $withRelations
            ? BuyerAgentAuction::with(['meta', 'bids.user'])
            : BuyerAgentAuction::query();

        $auction = $query->find($id);

        if (!$auction) {
            abort(404, 'Listing not found');
        }

        $workflowType = $auction->info('workflow_type');

        if ($workflowType === 'offer_listing') {
            // Primary check: workflow_type stamp
        } elseif (
            // Fallback for older Offer Listing records that pre-date the workflow_type stamp.
            // These meta keys only appear in Buyer Offer Listings, not in Hire Buyer's Agent records.
            $auction->info('pre_approval_amount') !== false ||
            $auction->info('down_payment_type')   !== false
        ) {
            // Fallback: presence of Offer Listing-specific meta keys
        } else {
            abort(404, 'Listing not found');
        }

        // Reject records explicitly stamped as hire_agent
        if ($workflowType === 'hire_agent') {
            abort(404, 'Listing not found');
        }

        return $auction;
    }

    public function view($id)
    {
        $auction = $this->resolveOfferListing($id, withRelations: true);

        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        $page_data = [
            'title'   => $meta['listing_title'] ?? ($auction->title ?? 'Buyer Criteria Listing'),
            'id'      => $id,
            'auth_id' => auth()->id(),
        ];

        return view('offer-listing.buyer.view', compact('auction', 'meta') + $page_data);
    }

    public function submitQuestion(Request $request, $auction)
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return redirect()->back()->with('success', 'Your question has been sent.');
        }

        $listing = $this->resolveOfferListing($auction);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:191',
            'email'    => 'required|email|max:191',
            'phone'    => 'nullable|string|max:64',
            'question' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator, 'bolQuestionInquiry')
                ->withInput()
                ->with('open_modal', 'question');
        }

        SellerListingInquiry::create([
            'auction_id' => $listing->id,
            'type'       => 'question',
            'name'       => $request->input('name'),
            'email'      => $request->input('email'),
            'phone'      => $request->input('phone'),
            'question'   => $request->input('question'),
            'status'     => 'new',
            'source'     => 'buyer_listing',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Your question has been sent. We\'ll be in touch shortly.');
    }

    public function searchOfferListings(Request $request)
    {
        $page_data['title'] = 'Buyer Criteria Listings';

        $auctions = BuyerAgentAuction::query()
            ->selectRaw("*, (SELECT meta_value FROM buyer_agent_auction_metas WHERE buyer_agent_auction_metas.buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where(function ($q) {
                $q->where('is_approved', 'true')
                  ->orWhere('is_approved', '1')
                  ->orWhere('is_approved', 1);
            })
            ->where('is_draft', false)
            // Safety guard: never surface a record explicitly stamped hire_agent
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'hire_agent');
            })
            ->where(function ($q) {
                // Primary: workflow_type = offer_listing (all records created after workflow_type was introduced)
                $q->whereHas('meta', function ($m) {
                    $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
                })
                // Fallback: presence of any Buyer Offer Listing-specific meta key.
                // Catches legacy records created before the workflow_type stamp existed.
                ->orWhereHas('meta', function ($m) {
                    $m->whereIn('meta_key', self::OFFER_LISTING_META_KEYS);
                });
            });

        if ($request->title != '') {
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
                $meta->where('meta_key', 'property_type')->where('meta_value', $request->property_type);
            });
        }

        $sort = $request->sort ?? 'newest';
        if ($sort === 'most_viewed') {
            $auctions->orderByRaw('(SELECT COUNT(*) FROM buyer_agent_auction_bids WHERE buyer_agent_auction_bids.buyer_agent_auction_id = buyer_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM buyer_agent_auction_metas
                             WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM buyer_agent_auction_metas
                             WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (buyer_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM buyer_agent_auction_metas
                             WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (buyer_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM buyer_agent_auction_metas
                             WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM buyer_agent_auction_metas
                        WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM buyer_agent_auction_metas
                            WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM buyer_agent_auction_metas
                        WHERE buyer_agent_auction_id = buyer_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, buyer_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = (clone $auctions)->count();
        $page_data['pAuctions'] = $auctions->paginate(12);

        return view('offer-listing.buyer.search', $page_data);
    }
}
