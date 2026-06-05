<?php

namespace App\Http\Controllers;

use App\Models\HireAgentLead;
use App\Models\User;
use App\Notifications\HireAgentLeadNotification;
use App\Services\AgentPresetCatalog;
use App\Services\HireAgentLeadMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HireAgentLeadController extends Controller
{
    public function __construct(private HireAgentLeadMatcherService $matcher)
    {
    }

    // ── Public AJAX: return preset-match options for the modal ────────────

    public function matchPresets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_listing_type'    => 'nullable|string|in:seller_offer,buyer_offer,landlord_offer,tenant_offer',
            'source_listing_id'      => 'nullable|integer|min:1',
            'representation_type'    => 'required|string|in:buyer,seller,landlord,tenant',
            'selected_property_type' => 'required|string',
        ]);

        $repType  = $validated['representation_type'];
        $propType = $validated['selected_property_type'];

        if (! AgentPresetCatalog::isValidCombination($repType, $propType)) {
            return response()->json(['match_status' => 'no_match', 'count' => 0, 'presets' => []]);
        }

        $listingType = $validated['source_listing_type'] ?? '';
        $listingId   = (int) ($validated['source_listing_id'] ?? 0);

        if ($listingType && $listingId) {
            $result = $this->matcher->matchPresetsForAjax($listingType, $listingId, $repType, $propType);
        } else {
            $cnt    = $this->matcher->countMatches($repType, $propType);
            $status = match ($cnt) { 0 => 'no_match', 1 => 'matched', default => 'multiple_matches' };
            $result = ['match_status' => $status, 'count' => $cnt, 'presets' => []];
        }

        return response()->json($result);
    }

    // ── Public: submit a new hire-agent lead ───────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_listing_type'    => 'required|string|in:seller_offer,buyer_offer,landlord_offer,tenant_offer',
            'source_listing_id'      => 'required|integer|min:1',
            'representation_type'    => 'required|string|in:buyer,seller,landlord,tenant',
            'selected_property_type' => 'required|string',
            'requester_name'         => 'required|string|max:191',
            'requester_email'        => 'required|email|max:191',
            'requester_phone'        => 'nullable|string|max:64',
            'message'                => 'nullable|string|max:2000',
        ]);

        $listingType = $validated['source_listing_type'];
        $listingId   = (int) $validated['source_listing_id'];
        $repType     = $validated['representation_type'];
        $propType    = $validated['selected_property_type'];

        if (! AgentPresetCatalog::isValidCombination($repType, $propType)) {
            return response()->json(['success' => false, 'message' => 'Invalid representation or property type.'], 422);
        }

        // ── Idempotency: deduplicate within 10-minute window ──────────────
        $existing = HireAgentLead::where('source_listing_type', $listingType)
            ->where('source_listing_id', $listingId)
            ->where('requester_email', $validated['requester_email'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Your request has already been sent.',
                'lead_id' => $existing->id,
            ]);
        }

        // ── Server-side agent + preset resolution (listing-agent-first) ───
        // target_agent_id is always server-derived; never trusted from client input.
        $matchResult       = $this->matcher->match($listingType, $listingId, $repType, $propType);
        $targetAgentId     = $matchResult['target_agent_id'];
        $firstPreset       = $matchResult['presets'][0] ?? null;
        $matchedPresetId   = $firstPreset['preset_id'] ?? null;
        $presetMatchStatus = $matchResult['match_status'];   // 'matched' or 'no_match'

        $lead = HireAgentLead::create([
            'source_listing_type'    => $listingType,
            'source_listing_id'      => $listingId,
            'source_listing_role'    => str_replace('_offer', '', $listingType),
            'source_property_type'   => $matchResult['source_property_type'] ?? null,
            'lead_source'            => 'offer_listing',
            'representation_type'    => $repType,
            'selected_property_type' => $propType,
            'requester_name'         => $validated['requester_name'],
            'requester_email'        => $validated['requester_email'],
            'requester_phone'        => $validated['requester_phone'] ?? null,
            'message'                => $validated['message'] ?? null,
            'requester_user_id'      => auth()->id(),
            'target_agent_id'        => $targetAgentId,
            'matched_preset_id'      => $matchedPresetId,
            'preset_match_status'    => $presetMatchStatus,
            'source_listing_title'   => $matchResult['source_listing_title'] ?? null,
            'source_listing_url'     => $matchResult['source_listing_url'] ?? null,
            // All new leads start as 'new'; transition to 'pending' on first agent view (markViewed)
            'status'                 => 'new',
        ]);

        // ── Notify matched agent ───────────────────────────────────────────
        if ($targetAgentId) {
            try {
                $agent = User::find($targetAgentId);
                if ($agent) {
                    $agent->notify(new HireAgentLeadNotification($lead, $firstPreset));
                }
            } catch (\Throwable $e) {
                Log::error('HireAgentLead: notification failed', [
                    'lead_id' => $lead->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Your request has been sent successfully!',
            'lead_id' => $lead->id,
        ]);
    }

    // ── Agent-only: lead index ─────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorizeAgent();

        $agentId = auth()->id();
        $status  = $request->query('status', '');

        $query = HireAgentLead::forAgent($agentId)->orderByDesc('created_at');

        if ($status && in_array($status, ['new', 'pending', 'accepted', 'declined', 'closed'])) {
            $query->where('status', $status);
        }

        $leads = $query->paginate(20)->withQueryString();

        $counts = [
            'all'      => HireAgentLead::forAgent($agentId)->count(),
            'new'      => HireAgentLead::forAgent($agentId)->where('status', 'new')->count(),
            'pending'  => HireAgentLead::forAgent($agentId)->where('status', 'pending')->count(),
            'accepted' => HireAgentLead::forAgent($agentId)->where('status', 'accepted')->count(),
            'declined' => HireAgentLead::forAgent($agentId)->where('status', 'declined')->count(),
            'closed'   => HireAgentLead::forAgent($agentId)->where('status', 'closed')->count(),
        ];

        return view('agent.hire-agent-leads.index', compact('leads', 'counts', 'status'));
    }

    // ── Agent-only: lead detail ────────────────────────────────────────────

    public function show(int $id): View
    {
        $this->authorizeAgent();
        $lead = $this->findLeadForAgent($id);

        // Auto-advance: marks viewed_at + transitions new → pending
        $lead->markViewed();
        $lead->refresh();

        return view('agent.hire-agent-leads.show', compact('lead'));
    }

    // ── Agent-only: accept ─────────────────────────────────────────────────

    public function accept(int $id): RedirectResponse
    {
        $this->authorizeAgent();
        $lead = $this->findLeadForAgent($id);
        $lead->markAccepted();

        return redirect()->route('agent.hire-leads.show', $id)
            ->with('success', 'Lead marked as accepted.');
    }

    // ── Agent-only: decline ────────────────────────────────────────────────

    public function decline(int $id): RedirectResponse
    {
        $this->authorizeAgent();
        $lead = $this->findLeadForAgent($id);
        $lead->markDeclined();

        return redirect()->route('agent.hire-leads.show', $id)
            ->with('success', 'Lead marked as declined.');
    }

    // ── Agent-only: mark responded ─────────────────────────────────────────

    public function respond(int $id): RedirectResponse
    {
        $this->authorizeAgent();
        $lead = $this->findLeadForAgent($id);
        $lead->markResponded();

        return redirect()->route('agent.hire-leads.show', $id)
            ->with('success', 'Lead marked as responded. Use the email link below to contact the requester.');
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function authorizeAgent(): void
    {
        if (! auth()->check() || auth()->user()->user_type !== 'agent') {
            abort(403, 'Agent access only.');
        }
    }

    private function findLeadForAgent(int $id): HireAgentLead
    {
        return HireAgentLead::where('id', $id)
            ->where('target_agent_id', auth()->id())
            ->firstOrFail();
    }
}
