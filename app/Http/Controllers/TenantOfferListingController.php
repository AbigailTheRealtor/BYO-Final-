<?php

namespace App\Http\Controllers;

use App\Models\TenantAgentAuction;
use App\Models\SellerListingInquiry;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\LocationDna\BoundaryLookupService;
use App\Services\LocationDna\FloodZoneLookupService;
use App\Services\LocationDna\LocationIntelligenceComposer;
use App\Services\LocationDna\SchoolDistrictLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantOfferListingController extends Controller
{
    /**
     * Meta keys written exclusively by Tenant Criteria Offer Listings and never
     * by Hire Tenant's Agent records. Used as a controlled legacy fallback to
     * identify older records that pre-date the workflow_type stamp.
     *
     * These keys are set by TenantOfferListing/TenantOfferListingEdit but are
     * absent from TenantAgentAuction (hire flow) and TenantAgentAuctionEdit.
     */
    public const OFFER_LISTING_META_KEYS = [
        'security_deposit_budget',
        'tenant_desired_lease_length',
    ];

    /**
     * Resolve a TenantAgentAuction by ID and confirm it is a Tenant Criteria Offer Listing.
     *
     * Guard logic (mirrors BuyerOfferListingController for consistency):
     *   1. Explicit offer_listing stamp → allowed.
     *   2. Missing/null workflow_type with an offer-listing-specific meta key present
     *      → allowed as a legacy record that pre-dates the workflow_type stamp.
     *   3. Anything else (wrong stamp, no recognised keys) → 404.
     *   4. hire_agent stamp → always 404, even if it somehow passed step 1/2.
     *
     * Dynamically loads ALL meta keys into a flat array — no hardcoded whitelist.
     */
    protected function resolveOfferListing(int $id): array
    {
        $auction = TenantAgentAuction::with('meta')->findOrFail($id);

        $workflowType = $auction->info('workflow_type');

        if ($workflowType === 'offer_listing') {
            // Primary: record is explicitly stamped as an Offer Listing.
        } elseif (
            // Controlled legacy fallback: presence of meta keys that only the
            // TenantOfferListing Livewire writes (not the Hire Tenant Agent flow).
            $auction->info('security_deposit_budget')    !== false ||
            $auction->info('tenant_desired_lease_length') !== false
        ) {
            // Legacy Tenant Criteria Listing without a workflow_type stamp — allow it.
        } else {
            // No recognised stamp and no offer-listing-specific keys found.
            abort(404, 'Listing not found');
        }

        // Explicitly reject any record stamped as hire_agent, regardless of the
        // outcome above (belt-and-suspenders guard against data anomalies).
        if ($workflowType === 'hire_agent') {
            abort(404, 'Listing not found');
        }

        // Dynamically build a flat meta array from every stored key.
        $meta = [];
        foreach ($auction->meta as $row) {
            $val = $row->meta_value;
            if ($val === null) {
                $meta[$row->meta_key] = null;
                continue;
            }
            $decoded = json_decode($val, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $val;
        }

        return [$auction, $meta];
    }

    /**
     * Public detail view for a Tenant Criteria Listing.
     */
    public function view(
        int $id,
        BoundaryLookupService $boundaryLookupService,
        FloodZoneLookupService $floodZoneLookupService,
        SchoolDistrictLookupService $schoolDistrictLookupService,
        LocationIntelligenceComposer $locationIntelligenceComposer
    ) {
        [$auction, $meta] = $this->resolveOfferListing($id);

        $askAiChipContext = app(AskAiContextBuilderService::class)->buildChipContext($auction, 'tenant');

        $agentAiV2      = config('ask_ai.agent_ai_v2_enabled', false);
        $agentAiAgentId = (int) ($meta['hired_agent_id'] ?? 0);
        $agentAiScope   = 'tenant_criteria';

        $ldnaRaw = $auction->info('location_dna_preferences');
        $locationDnaPreferences = $ldnaRaw ? (json_decode($ldnaRaw, true) ?? null) : null;
        $legacyLocation = [
            'cities'    => is_array($meta['cities'] ?? null) ? ($meta['cities'] ?? []) : (json_decode($meta['cities'] ?? '[]', true) ?? []),
            'counties'  => is_array($meta['counties'] ?? null) ? ($meta['counties'] ?? []) : (json_decode($meta['counties'] ?? '[]', true) ?? []),
            'states'    => ($meta['state'] ?? '') ? [$meta['state']] : [],
            'zip_codes' => [],
        ];
        $boundaryData = $boundaryLookupService->resolve($locationDnaPreferences, $legacyLocation);
        $floodZoneData = $floodZoneLookupService->resolve($boundaryData, $locationDnaPreferences ?? []);
        $schoolDistrictData = $schoolDistrictLookupService->resolve($boundaryData, $locationDnaPreferences ?? []);
        try {
            $locationIntelligence = $locationIntelligenceComposer->compose($boundaryData, $locationDnaPreferences ?? []);
            $locationIntelligenceSummary = $locationIntelligence['summary'] ?? ['summary_lines' => []];
        } catch (\Throwable $e) {
            $locationIntelligenceSummary = ['summary_lines' => []];
        }

        return view('offer-listing.tenant.view', [
            'auction'                    => $auction,
            'meta'                       => $meta,
            'ownerId'                    => $auction->user_id,
            'askAiChipContext'           => $askAiChipContext,
            'locationDnaPreferences'     => $locationDnaPreferences,
            'legacyLocation'             => $legacyLocation,
            'boundaryData'               => $boundaryData,
            'floodZoneData'              => $floodZoneData,
            'schoolDistrictData'         => $schoolDistrictData,
            'locationIntelligenceSummary' => $locationIntelligenceSummary,
            'agentAiV2'                  => $agentAiV2,
            'agentAiAgentId'             => $agentAiAgentId,
            'agentAiScope'               => $agentAiScope,
        ]);
    }

    public function submitQuestion(Request $request, $auction)
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return redirect()->back()->with('success', 'Your question has been sent.');
        }

        [$listing] = $this->resolveOfferListing((int) $auction);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:191',
            'email'    => 'required|email|max:191',
            'phone'    => 'nullable|string|max:64',
            'question' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator, 'tclQuestionInquiry')
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
            'source'     => 'tenant_listing',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Your question has been sent.');
    }

    public function searchOfferListings(Request $request)
    {
        $page_data['title'] = 'Tenant Criteria Listings';

        $auctions = TenantAgentAuction::query()
            ->selectRaw("*, (SELECT meta_value FROM tenant_agent_auction_metas WHERE tenant_agent_auction_metas.tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where('is_approved', true)
            ->where('is_draft', false)
            ->where('is_sold', false)
            // Safety guard: never surface a record explicitly stamped hire_agent
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'hire_agent');
            })
            // Primary: workflow_type = offer_listing (all records created after workflow_type was introduced)
            ->where(function ($q) {
                $q->whereHas('meta', function ($m) {
                    $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
                })
                // Legacy fallback: records created before the workflow_type stamp existed.
                // Identified by presence of meta keys exclusive to the Offer Listing flow.
                ->orWhere(function ($q2) {
                    $q2->whereDoesntHave('meta', function ($m) {
                        $m->where('meta_key', 'workflow_type');
                    })
                    ->where(function ($q3) {
                        $q3->whereHas('meta', function ($m) {
                            $m->where('meta_key', 'security_deposit_budget');
                        })->orWhereHas('meta', function ($m) {
                            $m->where('meta_key', 'tenant_desired_lease_length');
                        });
                    });
                });
            });

        if (!empty($request->title)) {
            $auctions->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->title . '%')
                  ->orWhereHas('meta', function ($m) use ($request) {
                      $m->where('meta_key', 'listing_title')
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
            $auctions->orderByRaw('(SELECT COUNT(*) FROM tenant_agent_auction_bids WHERE tenant_agent_auction_bids.tenant_agent_auction_id = tenant_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM tenant_agent_auction_metas
                             WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM tenant_agent_auction_metas
                             WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (tenant_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM tenant_agent_auction_metas
                             WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (tenant_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM tenant_agent_auction_metas
                             WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM tenant_agent_auction_metas
                        WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM tenant_agent_auction_metas
                            WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM tenant_agent_auction_metas
                        WHERE tenant_agent_auction_id = tenant_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, tenant_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = (clone $auctions)->count();
        $page_data['pAuctions'] = $auctions->paginate(12);

        return view('offer-listing.tenant.search', $page_data);
    }
}
