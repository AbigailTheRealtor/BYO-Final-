<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentBidMapperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Resolve the agent's services list from a profile.
     * Handles: array, JSON-encoded string, "none", null.
     * Returns a plain indexed array of non-empty strings.
     *
     * @param  AgentDefaultProfile|null  $profile
     * @return array<int, string>
     */
    public static function resolveServices(?AgentDefaultProfile $profile): array
    {
        if (!$profile) {
            return [];
        }
        $raw = $profile->profile_data['services'] ?? null;

        if (is_array($raw)) {
            $services = $raw;
        } elseif (is_string($raw) && $raw !== 'none' && $raw !== '') {
            $decoded = json_decode($raw, true);
            $services = is_array($decoded) ? $decoded : [];
        } else {
            return [];
        }

        // Filter out blank entries and decode any literal JSON unicode escapes
        // (e.g. \u2019 stored as raw text rather than the decoded UTF-8 char).
        return array_values(array_filter(
            array_map(
                fn($s) => is_string($s) ? \App\Support\ServicesFormatter::decodeServiceLabel(trim($s)) : '',
                $services
            ),
            fn($s) => $s !== ''
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // GET — Phase-4 clean public Hire Me URL resolver
    //       Resolves agent by short_id, then redirects to the internal
    //       direct-hire preview route. No logic is duplicated.
    // ─────────────────────────────────────────────────────────────────

    public function showPublic(string $agentShortId, string $role, string $propertyType = 'residential')
    {
        $agent = \App\Models\User::where('short_id', $agentShortId)
            ->where('user_type', 'agent')
            ->firstOrFail();

        return redirect()->route('hire.agent.direct.preview', [
            'agentId'      => $agent->id,
            'role'         => $role,
            'propertyType' => $propertyType,
        ]);
    }

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

        // Owner preview: agent may view their own page but cannot submit (confirm() still aborts).
        $isOwnerPreview = Auth::id() === $agent->id;

        // Load the agent's preset for this exact role + property type.
        // A strict lookup is used deliberately — findForAgentWithFallback could
        // silently return a role-default (__default__) preset that belongs to a
        // different property type, causing the wrong services to be displayed.
        $profile = AgentDefaultProfile::findForAgent(
            $agentId,
            $role,
            $propertyType
        );

        // Map preset fields — null when no profile exists
        $mapped = $profile
            ? AgentBidMapperService::mapFromProfile($profile->profile_data ?? [])
            : null;

        // Resolve services from the preset (handles array or JSON-string storage)
        $agentServices = self::resolveServices($profile);

        // Resolve custom / additional services from the preset
        $otherServices = [];
        if ($profile) {
            $rawOther = $profile->profile_data['other_services'] ?? [];
            if (is_array($rawOther)) {
                $otherServices = array_values(array_filter(
                    $rawOther,
                    fn($s) => is_string($s) && trim($s) !== ''
                ));
            }
        }

        // Preset is only usable when it exists AND has at least one service
        $presetValid = $profile !== null && count($agentServices) > 0;

        // Generate a one-time submit token for backend duplicate protection.
        // Generated even in owner-preview mode so the session key is always set;
        // the confirm() method will reject any submission from the agent themselves.
        $submitToken = bin2hex(random_bytes(16));
        session(['hire_direct_token' => $submitToken]);

        return view('hire-agent-direct.preview', compact(
            'agent',
            'role',
            'propertyType',
            'profile',
            'mapped',
            'agentServices',
            'otherServices',
            'presetValid',
            'submitToken',
            'isOwnerPreview'
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

        // Fix 2 — backend one-time token: consume it or reject as duplicate
        $expectedToken = session('hire_direct_token');
        $submittedToken = $request->input('_hire_token');
        if (!$expectedToken || !$submittedToken || !hash_equals($expectedToken, $submittedToken)) {
            return redirect()->back()
                ->with('error', 'This request has already been submitted or the page has expired. Please refresh and try again.');
        }
        // Consume the token immediately — subsequent POSTs with the same token are rejected
        session()->forget('hire_direct_token');

        // Validate minimal client inputs
        $validated = $request->validate([
            'address'               => 'required|string|max:500',
            'services'              => 'nullable|array',
            'services.*'            => 'string|max:500',
            'other_services'        => 'nullable|array',
            'other_services.*'      => 'string|max:500',
            'additional_requested'  => 'nullable|string|max:3000',
            'client_custom_services' => 'nullable|string|max:3000',
        ]);

        // Load the preset using a strict lookup — no fallback to a role-default
        // preset that could belong to a different property type.
        $profile = AgentDefaultProfile::findForAgent(
            $agentId,
            $role,
            $propertyType
        );

        if (!$profile) {
            return redirect()->back()
                ->with('error', 'This agent has not set up a hiring profile for the selected role and property type. Unable to proceed.');
        }

        // Fix 1 — block if the agent's preset has no services configured
        $agentServices = self::resolveServices($profile);
        if (empty($agentServices)) {
            return redirect()->back()
                ->with('error', 'This agent has not finished setting up their services yet. Unable to proceed.');
        }

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        // Services the client confirmed (must be a subset of agent's preset services)
        $clientServices = array_values(array_intersect(
            $validated['services'] ?? [],
            $agentServices
        ));

        // Additional services the agent defined — intersect against the preset's
        // other_services list so clients cannot inject arbitrary values
        $presetOtherServices = array_values(array_filter(
            is_array($profile->profile_data['other_services'] ?? null)
                ? $profile->profile_data['other_services']
                : [],
            fn($s) => is_string($s) && trim($s) !== ''
        ));
        $clientOtherServices = array_values(array_intersect(
            $validated['other_services'] ?? [],
            $presetOtherServices
        ));

        // Parse client_custom_services: split by newline, trim, deduplicate, cap entries
        $clientCustomServices = [];
        $rawCustom = $validated['client_custom_services'] ?? null;
        if (!empty($rawCustom)) {
            $lines = preg_split('/\r\n|\r|\n/', $rawCustom);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines, fn($l) => $l !== '');
            $lines = array_values(array_unique($lines));
            // Cap at 50 entries to prevent abuse
            $clientCustomServices = array_slice($lines, 0, 50);
        }

        DB::beginTransaction();
        try {
            // ── 1. Create the listing ────────────────────────────────────
            $listingClass = self::LISTING_MODELS[$role];
            $listing = new $listingClass();
            $listing->user_id    = Auth::id();
            $listing->is_approved = true;
            $listing->is_draft    = false;
            $listing->is_sold     = false;

            // Only assign columns that exist on this model's table.
            // landlord_agent_auctions and tenant_agent_auctions do not
            // have address/title columns — those are stored as meta instead.
            $listingTable = $listing->getTable();
            if (Schema::hasColumn($listingTable, 'address')) {
                $listing->address = $validated['address'];
            }
            if (Schema::hasColumn($listingTable, 'title')) {
                $listing->title = 'Direct Hire – ' . ucfirst($role) . ' Agent';
            }
            $listing->save();

            // ── 2. Save listing meta ─────────────────────────────────────
            $listing->saveMeta('workflow_type',    'hire_agent');
            $listing->saveMeta('listing_status',   'Active');
            $listing->saveMeta('service_type',     'full_service');
            $listing->saveMeta('auction_type',     'Traditional');
            $listing->saveMeta('property_type',    $propertyType);
            $listing->saveMeta('expiration_date',  now()->addDays(30)->toDateString());
            $listing->saveMeta('services',         json_encode($clientServices));
            $listing->saveMeta('other_services',   json_encode($clientOtherServices));
            $listing->saveMeta('other_services_enabled', empty($clientOtherServices) ? '0' : '1');
            $listing->saveMeta('hire_me_flow',     '1');

            // For models without native address/title columns (landlord, tenant),
            // persist these values via meta so detail views can read them.
            if (!Schema::hasColumn($listingTable, 'address')) {
                $listing->saveMeta('address', $validated['address']);
            }
            if (!Schema::hasColumn($listingTable, 'title')) {
                $listing->saveMeta('title', 'Direct Hire – ' . ucfirst($role) . ' Agent');
            }

            if (!empty($validated['additional_requested'])) {
                $listing->saveMeta('client_additional_requested', $validated['additional_requested']);
            }

            if (!empty($clientCustomServices)) {
                $listing->saveMeta('client_custom_services', json_encode($clientCustomServices));
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
            $bid->saveMeta('services',       json_encode($clientServices));
            // Other services on the bid mirror only what the client kept checked
            $bid->saveMeta('other_services', json_encode($clientOtherServices));
            $bid->saveMeta('hire_me_auto_bid', '1');

            // Client-requested custom services — stored separately, never merged into services/other_services
            if (!empty($clientCustomServices)) {
                $bid->saveMeta('client_custom_services', json_encode($clientCustomServices));
            }

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
