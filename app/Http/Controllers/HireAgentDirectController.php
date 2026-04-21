<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentBidMapperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HireAgentDirectController
 *
 * Phase-2 "Hire Me / Hire This Agent" entry point.
 *
 * GET  /hire/agent/direct/{agentId}/{role}/{propertyType?}
 *      → Preview page: loads agent + preset, zero DB writes.
 *
 * POST /hire/agent/direct/{agentId}/{role}/{propertyType}
 *      → Confirmation: creates listing + bid atomically inside a
 *        DB::transaction(), then redirects to the existing listing
 *        detail page so the client can accept / counter / reject.
 *
 * Design constraints (enforced throughout)
 * ─────────────────────────────────────────
 * • No listing or bid is created until the client presses Confirm.
 * • Only services the agent included in their preset are shown.
 * • AgentBidMapperService is the single source of bid-field mapping.
 * • accept / counter / reject flows are entirely reused — untouched.
 */
class HireAgentDirectController extends Controller
{
    private const VALID_ROLES = ['buyer', 'seller', 'landlord', 'tenant'];

    private const VALID_PROPERTY_TYPES = [
        'residential',
        'income',
        'commercial',
        'business',
        'vacant_land',
    ];

    /** Role → listing Eloquent model class */
    private const LISTING_MODELS = [
        'tenant'   => \App\Models\TenantAgentAuction::class,
        'landlord' => \App\Models\LandlordAgentAuction::class,
        'buyer'    => \App\Models\BuyerAgentAuction::class,
        'seller'   => \App\Models\SellerAgentAuction::class,
    ];

    /** Role → bid Eloquent model class */
    private const BID_MODELS = [
        'tenant'   => \App\Models\TenantAgentAuctionBid::class,
        'landlord' => \App\Models\LandlordAgentAuctionBid::class,
        'buyer'    => \App\Models\BuyerAgentAuctionBid::class,
        'seller'   => \App\Models\SellerAgentAuctionBid::class,
    ];

    /** Role → existing listing-detail route (client lands here after confirmation) */
    private const LISTING_VIEW_ROUTES = [
        'tenant'   => 'tenant.agent.auction.view',
        'landlord' => 'landlord.agent.auction.view',
        'buyer'    => 'buyer.view-auction',
        'seller'   => 'seller.agent.auction.detail',
    ];

    // ─────────────────────────────────────────────────────────────────
    // GET — Preview / confirmation page (no DB writes)
    // ─────────────────────────────────────────────────────────────────

    public function show(int $agentId, string $role, string $propertyType = 'residential')
    {
        // Validate URL segments
        if (!in_array($role, self::VALID_ROLES, true)) {
            abort(404);
        }
        if (!in_array($propertyType, self::VALID_PROPERTY_TYPES, true)) {
            $propertyType = 'residential';
        }

        // Load agent securely — must have user_type = 'agent'
        $agent = User::where('id', $agentId)
            ->where('user_type', 'agent')
            ->firstOrFail();

        // Prevent self-hire
        if (Auth::id() === $agent->id) {
            abort(403, 'You cannot hire yourself.');
        }

        // Load the agent's preset for this role + property type
        $profile = AgentDefaultProfile::findForAgentWithFallback(
            $agentId,
            $role,
            $propertyType
        );

        // Map preset fields — null when no profile exists
        $mapped = $profile
            ? AgentBidMapperService::mapFromProfile($profile->profile_data ?? [])
            : null;

        return view('hire-agent-direct.preview', compact(
            'agent',
            'role',
            'propertyType',
            'profile',
            'mapped'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // POST — Confirmation: create listing + bid atomically
    // ─────────────────────────────────────────────────────────────────

    public function confirm(Request $request, int $agentId, string $role, string $propertyType = 'residential')
    {
        // Re-validate URL segments
        if (!in_array($role, self::VALID_ROLES, true)) {
            abort(404);
        }
        if (!in_array($propertyType, self::VALID_PROPERTY_TYPES, true)) {
            abort(422);
        }

        // Re-verify agent identity
        $agent = User::where('id', $agentId)
            ->where('user_type', 'agent')
            ->firstOrFail();

        if (Auth::id() === $agent->id) {
            abort(403, 'You cannot hire yourself.');
        }

        // Validate minimal client inputs
        $validated = $request->validate([
            'address'              => 'required|string|max:500',
            'services'             => 'nullable|array',
            'services.*'           => 'string|max:500',
            'additional_requested' => 'nullable|string|max:3000',
        ]);

        // Load the preset — block confirmation if no profile exists
        $profile = AgentDefaultProfile::findForAgentWithFallback(
            $agentId,
            $role,
            $propertyType
        );

        if (!$profile) {
            return redirect()->back()
                ->with('error', 'This agent has not set up a profile preset for the selected role and property type. Unable to proceed.');
        }

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        // Services the client confirmed (must be a subset of agent's preset services)
        $agentServices  = $profile->profile_data['services'] ?? [];
        $clientServices = array_values(array_intersect(
            $validated['services'] ?? [],
            is_array($agentServices) ? $agentServices : []
        ));

        DB::beginTransaction();
        try {
            // ── 1. Create the listing ────────────────────────────────────
            $listingClass = self::LISTING_MODELS[$role];
            $listing = new $listingClass();
            $listing->user_id    = Auth::id();
            $listing->address    = $validated['address'];
            $listing->title      = 'Direct Hire – ' . ucfirst($role) . ' Agent';
            $listing->is_approved = true;
            $listing->is_draft    = false;
            $listing->is_sold     = false;
            $listing->save();

            // ── 2. Save listing meta ─────────────────────────────────────
            $listing->saveMeta('workflow_type',    'hire_agent');
            $listing->saveMeta('listing_status',   'Active');
            $listing->saveMeta('service_type',     'full_service');
            $listing->saveMeta('auction_type',     'Traditional');
            $listing->saveMeta('property_type',    $propertyType);
            $listing->saveMeta('expiration_date',  now()->addDays(30)->toDateString());
            $listing->saveMeta('services',         json_encode($clientServices));
            $listing->saveMeta('other_services',   json_encode([]));
            $listing->saveMeta('other_services_enabled', '0');
            $listing->saveMeta('hire_me_flow',     '1');

            if (!empty($validated['additional_requested'])) {
                $listing->saveMeta('client_additional_requested', $validated['additional_requested']);
            }

            // ── 3. Create the bid (on behalf of the agent's standing offer) ──
            $bidClass = self::BID_MODELS[$role];
            $bid = new $bidClass();
            $bid->user_id = $agent->id;
            $bid->{$role . '_agent_auction_id'} = $listing->id;
            $bid->save();

            // ── 4. Save bid meta from AgentBidMapperService ──────────────
            foreach ($mapped as $key => $value) {
                $bid->saveMeta(
                    $key,
                    is_array($value) ? json_encode($value) : (string) $value
                );
            }

            // Credential fields: prefer profile data, fall back to agent's user record
            $bid->saveMeta('first_name',  $mapped['first_name']  ?: ($agent->first_name ?? ''));
            $bid->saveMeta('last_name',   $mapped['last_name']   ?: ($agent->last_name  ?? ''));
            $bid->saveMeta('phone',       $mapped['phone']       ?: ($agent->phone      ?? ''));
            $bid->saveMeta('email',       $mapped['email']       ?: ($agent->email      ?? ''));
            $bid->saveMeta('brokerage',   $mapped['brokerage']   ?: ($agent->brokerage  ?? ''));
            $bid->saveMeta('license_no',  $mapped['license_no']  ?: ($agent->license_no ?? ''));
            $bid->saveMeta('nar_id',      $mapped['nar_id']      ?: ($agent->nar_id     ?? ''));

            // Services on the bid mirror what the client confirmed
            $bid->saveMeta('services',     json_encode($clientServices));
            $bid->saveMeta('hire_me_auto_bid', '1');

            DB::commit();

            Log::info('HireAgentDirect: listing + bid created', [
                'listing_id'  => $listing->id,
                'bid_id'      => $bid->id,
                'role'        => $role,
                'client_id'   => Auth::id(),
                'agent_id'    => $agent->id,
                'property_type' => $propertyType,
            ]);

            $viewRoute = self::LISTING_VIEW_ROUTES[$role];

            return redirect()
                ->route($viewRoute, $listing->id)
                ->with('success', "Your hire request has been sent. Review the agent's offer below and choose to accept, counter, or decline.");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('HireAgentDirect confirm failed', [
                'error'    => $e->getMessage(),
                'agentId'  => $agentId,
                'role'     => $role,
                'clientId' => Auth::id(),
            ]);
            return redirect()
                ->back()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }
}
