<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\SellerListingInquiry;
use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LandlordOfferListingController extends Controller
{
    /**
     * Meta keys that are exclusive to Landlord Offer Listings (Full Service).
     * Used as a fallback identifier for records that pre-date the workflow_type stamp.
     * These keys are saved only from the Create Landlord Listing form — never from
     * the Hire Landlord's Agent flow — so their presence positively identifies an
     * Offer Listing when the workflow_type stamp is absent.
     */
    // `auction_type` removed: both the Hire Landlord's Agent and the Create Offer
    // Landlord flows write it, so it is not a valid Offer-Listing discriminator.
    // Hire rows are positively tagged workflow_type='hire_agent' (rejected before
    // this fallback runs); the primary `offer_listing` stamp + these keys identify
    // legacy Offer rows that pre-date the stamp.
    public const OFFER_LISTING_META_KEYS = [
        'desired_rental_amount',
        'lease_amount_frequency',
        'tenant_require',
        'listing_date',
        'property_photos',
    ];

    /**
     * Resolve a LandlordAgentAuction by ID and confirm it is a Landlord Offer Listing.
     *
     * Primary path  — workflow_type === 'offer_listing' (all records after stamp was introduced).
     * Fallback path — presence of Offer-Listing-exclusive meta keys for older records.
     * Any other record (hire_agent, null, unknown, etc.) results in abort(404).
     */
    /**
     * Read the OfferAuction record linked to this LandlordAgentAuction.
     * Returns null when none is present.  Never writes to the database.
     */
    private function resolveOfferAuction(LandlordAgentAuction $auction): ?OfferAuction
    {
        $linkedId = $auction->info('linked_offer_auction_id');
        if ($linkedId) {
            return OfferAuction::find((int) $linkedId) ?: null;
        }
        return null;
    }

    /**
     * Return the OfferAuction linked to this landlord listing, creating one on
     * first access when none exists.  The OfferAuction is the record that
     * offer_auction_id in offer submission forms must reference.
     *
     * A back-reference meta (linked_landlord_auction_id) is stored on the
     * OfferAuction so that the offer show page can pre-fill the tenant
     * application form with the landlord's asking terms.
     */
    public function ensureLinkedOfferAuction(LandlordAgentAuction $auction): OfferAuction
    {
        $existing = $this->resolveOfferAuction($auction);
        if ($existing) {
            return $existing;
        }

        $offerAuction = OfferAuction::create([
            'user_id'     => $auction->user_id,
            'title'       => $auction->title ?: ($auction->info('listing_title') ?: 'Rental Property'),
            'is_draft'    => false,
            'is_approved' => true,
        ]);
        $offerAuction->saveMeta('offer_type', 'rental');
        $offerAuction->saveMeta('linked_landlord_auction_id', $auction->id);

        $auction->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return $offerAuction;
    }

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

        // WF-2: an archived listing is hidden from everyone except its owner.
        if ($auction->is_archived && (int) $auction->user_id !== (int) auth()->id()) {
            abort(404);
        }

        // WF-4: a draft / not-yet-approved listing is private to its owner (no public leak).
        if ((! filter_var($auction->is_approved, FILTER_VALIDATE_BOOLEAN) || filter_var($auction->is_draft, FILTER_VALIDATE_BOOLEAN))
            && (int) $auction->user_id !== (int) auth()->id()) {
            abort(404);
        }

        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        $askAiChipContext = app(AskAiContextBuilderService::class)->buildChipContext($auction, 'landlord');

        $agentAiV2      = config('ask_ai.agent_ai_v2_enabled', false);
        $agentAiAgentId = (int) ($meta['hired_agent_id'] ?? 0);
        $agentAiScope   = 'public_listing_landlord';

        $page_data = [
            'title' => $auction->title ?? ($meta['listing_title'] ?? 'Rental Property Listing'),
        ];

        $locationDna  = PropertyLocationDna::where('listing_type', 'landlord_agent')
            ->where('listing_id', $auction->id)
            ->first();
        $locationPois = $locationDna
            ? PropertyLocationPoi::where('listing_type', 'landlord_agent')
                ->where('listing_id', $auction->id)
                ->orderBy('poi_category')
                ->orderBy('rank')
                ->get()
            : collect();

        $offerAuction = $this->ensureLinkedOfferAuction($auction);

        return view('offer-listing.landlord.view', compact('auction', 'meta', 'askAiChipContext', 'offerAuction', 'agentAiV2', 'agentAiAgentId', 'agentAiScope', 'locationDna', 'locationPois') + $page_data);
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
                ->withErrors($validator, 'lolQuestionInquiry')
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
            'source'     => 'landlord_listing',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Your question has been sent.');
    }

    public function submitShowing(Request $request, $auction)
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return redirect()->back()->with('success', 'Your showing request has been sent.');
        }

        $listing = $this->resolveOfferListing($auction);

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:191',
            'email'          => 'required|email|max:191',
            'phone'          => 'nullable|string|max:64',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string|max:32',
            'message'        => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator, 'lolShowingInquiry')
                ->withInput()
                ->with('open_modal', 'showing');
        }

        SellerListingInquiry::create([
            'auction_id'     => $listing->id,
            'type'           => 'showing',
            'name'           => $request->input('name'),
            'email'          => $request->input('email'),
            'phone'          => $request->input('phone'),
            'preferred_date' => $request->input('preferred_date'),
            'preferred_time' => $request->input('preferred_time'),
            'message'        => $request->input('message'),
            'status'         => 'new',
            'source'         => 'landlord_listing',
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Your showing request has been sent.');
    }

    public function searchOfferListings(Request $request)
    {
        $page_data['title'] = 'Rental Properties';

        $auctions = LandlordAgentAuction::query()
            ->selectRaw("*, (SELECT meta_value FROM landlord_agent_auction_metas WHERE landlord_agent_auction_metas.landlord_agent_auction_id = landlord_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where('is_approved', true)
            ->where('is_draft', false)
            ->where('is_archived', 0) // WF-2: hide owner-archived listings from discovery
            // Safety guard: never surface a record explicitly stamped hire_agent
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'hire_agent');
            })
            // Include stamped offer_listing records (primary path) OR unstamped legacy records
            // that pre-date the workflow_type stamp but carry Offer-Listing-exclusive meta keys.
            // The whereDoesntHave(hire_agent) guard above blocks all explicitly stamped
            // hire_agent records, so the fallback path is safe for truly unstamped listings.
            ->where(function ($q) {
                $q->whereHas('meta', function ($m) {
                    $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
                })->orWhere(function ($q2) {
                    // Legacy path: no workflow_type row at all + at least one Offer-Listing-exclusive key
                    $q2->whereDoesntHave('meta', function ($m) {
                        $m->where('meta_key', 'workflow_type');
                    })->whereHas('meta', function ($m) {
                        $m->whereIn('meta_key', self::OFFER_LISTING_META_KEYS);
                    });
                });
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
