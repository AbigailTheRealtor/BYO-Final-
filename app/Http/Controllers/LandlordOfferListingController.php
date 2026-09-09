<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\SellerListingInquiry;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\ListingOfferAuctionLinker;
use App\Services\Offers\PublicOfferFeedService;
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
     * Return the OfferAuction linked to this landlord listing, creating one when
     * none exists.  The OfferAuction is the record that offer_auction_id in offer
     * submission forms must reference.
     *
     * A back-reference meta (linked_landlord_auction_id) is stored on the
     * OfferAuction so that the offer show page can pre-fill the tenant
     * application form with the landlord's asking terms.
     *
     * WRITES — do not call from view() or any other GET handler. Publishing
     * (StampsBiddingActivation) and the backfill command are the supported
     * callers; the public page resolves read-only.
     */
    public function ensureLinkedOfferAuction(LandlordAgentAuction $auction): OfferAuction
    {
        // Delegated to ListingOfferAuctionLinker so this page and the publish-time
        // bidding activation stamp cannot create differently-shaped rows. The
        // payload written there is identical to what this method wrote before.
        return app(ListingOfferAuctionLinker::class)->ensureFor($auction, 'landlord');
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

        // READ-ONLY. This is an unauthenticated public GET and must never write.
        // The link is established at publish time (StampsBiddingActivation) and,
        // for listings that went live before that existed, by
        // `php artisan offer:backfill-linked-auction`. A null here degrades the
        // application form rather than mutating state on a page view.
        $offerAuction = $this->resolveOfferAuction($auction);

        $biddingWindow = app(BiddingWindowService::class)->for($auction, $offerAuction);

        // Guests never reach build(): no bid data is queried, serialized, or sent
        // to the browser for them. They get the login callout and nothing else.
        $feed           = app(PublicOfferFeedService::class);
        $canViewBidFeed = $feed->canView(auth()->user(), $auction, 'landlord');
        $bidFeed        = $canViewBidFeed ? $feed->build($offerAuction, 'landlord') : [];


        // ── MLS import payload and the feed's own display permissions ────────
        //
        // Resolved here rather than in the template so the permission check
        // cannot be skipped by a view that forgets it. `$mlsAddressVisible` is
        // false only for a NON-owner viewing a listing whose feed set
        // InternetAddressDisplayYN = false — the audit found that flag false on
        // 71 of 1,202 cached records, and before this the import path honoured
        // it nowhere. The owner always sees their own address and is told, via
        // $mlsAddressNotice, why a visitor does not.
        $mlsReader         = app(\App\Services\ListingImport\Mls\MlsListingDetailsReader::class);
        $mlsDetails        = $mlsReader->detailsFrom($meta);
        $viewerOwnsListing = (int) $auction->user_id === (int) auth()->id();
        $mlsAddressVisible = $mlsReader->addressVisibleTo($meta, $viewerOwnsListing);
        $mlsAddressNotice  = $viewerOwnsListing ? $mlsReader->addressRestrictionNotice($meta) : null;

        // Drives the Stellar/Bridge attribution block. Resolved from PROVENANCE
        // meta, never from "does this listing have MLS-looking data" — a
        // manually created listing must never carry an attribution it did not
        // earn, and a false provenance claim is worse than a missing one.
        $mlsImported       = $mlsReader->isMlsImported($meta);

        return view('offer-listing.landlord.view', compact('auction', 'meta', 'askAiChipContext', 'offerAuction', 'agentAiV2', 'agentAiAgentId', 'agentAiScope', 'locationDna', 'locationPois', 'biddingWindow', 'canViewBidFeed', 'bidFeed') + ['mlsDetails' => $mlsDetails, 'mlsAddressVisible' => $mlsAddressVisible, 'mlsAddressNotice' => $mlsAddressNotice, 'mlsImported' => $mlsImported] + $page_data);
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
            // Ordering reads the SAME canonical timestamp as every countdown and
            // every enforcement check: the stored offer_auctions.bidding_ends_at,
            // reached through the listing's linked_offer_auction_id meta.
            //
            // No arithmetic, no auction_time, no created_at, no expiration_date.
            // Listings with no canonical window sort last rather than being given
            // a synthetic deadline (Invariants 3, 4, 5, 6, 9, 10).
            //
            // "(expr) IS NULL" ahead of the value keeps NULLs last portably across
            // Postgres and the SQLite used by the test suite.
            //
            // The subquery lives in BiddingWindowService so all four roles share
            // one definition — including the bigint/text cast PostgreSQL requires
            // to compare offer_auctions.id against the EAV meta_value.
            app(BiddingWindowService::class)->applyEndingSoonOrder($auctions, 'landlord');
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = (clone $auctions)->count();
        $page_data['pAuctions'] = $auctions->paginate(12);

        $page_data['biddingWindows'] = $this->resolveBiddingWindows($page_data['pAuctions']);

        return view('offer-listing.landlord.search', $page_data);
    }

    /**
     * Canonical bidding windows for a page of listings, keyed by listing id.
     *
     * The card view renders countdowns from this map and performs no deadline
     * arithmetic of its own. Every window comes from the stored
     * offer_auctions.bidding_ends_at via BiddingWindowService, so a card, the
     * detail page, the server-side guard and the ending_soon sort all read the
     * one same timestamp (Invariants 3, 5, 6).
     *
     * @param  iterable  $listings
     * @return array<int, \App\Services\Offers\BiddingWindow>
     */
    private function resolveBiddingWindows($listings): array
    {
        $service = app(BiddingWindowService::class);
        $windows = [];

        foreach ($listings as $listing) {
            $linkedId = $listing->info('linked_offer_auction_id');
            $offerAuction = $linkedId ? \App\Models\OfferAuction::find((int) $linkedId) : null;

            $windows[$listing->id] = $service->for($listing, $offerAuction);
        }

        return $windows;
    }

}
