<?php

namespace App\Http\Controllers\AgentAi;

use App\Http\Controllers\Controller;
use App\Models\AgentAiChatLead;
use App\Models\AgentAiChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * AgentAiInboxController
 *
 * Manages the agent-only AI conversation inbox at /agent/ai-inbox.
 *
 * GOVERNANCE:
 *  - Agents see ONLY their own sessions (scoped by agent_id = auth()->id()).
 *  - Visitor contact information and conversation transcripts are never
 *    exposed to any agent other than the owning agent.
 *  - No public routes. All methods require auth + agent user type.
 *  - No cross-agent analytics or shared data.
 */
class AgentAiInboxController extends Controller
{
    /**
     * GET /agent/ai-inbox
     *
     * Paginated, filterable inbox of AI chat sessions with lead data.
     * Filters: min_score, max_score, lead_type, date_from, date_to.
     */
    public function index(Request $request): View
    {
        $agentId = Auth::id();

        $query = AgentAiChatSession::query()
            ->where('agent_id', $agentId)
            ->with(['lead', 'messages'])
            ->orderByDesc('last_active_at');

        // ── Filters ───────────────────────────────────────────────────────────

        $minScore = $request->input('min_score');
        $maxScore = $request->input('max_score');
        $leadType = $request->input('lead_type');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if ($minScore !== null) {
            $query->whereHas('lead', function ($q) use ($minScore) {
                $q->where('lead_score', '>=', (int) $minScore);
            });
        }

        if ($maxScore !== null) {
            $query->whereHas('lead', function ($q) use ($maxScore) {
                $q->where('lead_score', '<=', (int) $maxScore);
            });
        }

        if ($leadType !== null && $leadType !== '') {
            $query->whereHas('lead', function ($q) use ($leadType) {
                $q->where('lead_type', $leadType);
            });
        }

        if ($dateFrom !== null) {
            $query->where('started_at', '>=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== null) {
            $query->where('started_at', '<=', $dateTo . ' 23:59:59');
        }

        $sessions = $query->paginate(20)->withQueryString();

        // Unread hot-lead count (score >= 75, not reviewed).
        $unreadHotLeadCount = AgentAiChatSession::where('agent_id', $agentId)
            ->whereNull('reviewed_at')
            ->whereHas('lead', function ($q) {
                $q->where('lead_score', '>=', 75);
            })
            ->count();

        return view('agent.ai-inbox.index', compact(
            'sessions',
            'unreadHotLeadCount',
            'minScore',
            'maxScore',
            'leadType',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * GET /agent/ai-inbox/{sessionId}
     *
     * Show the full message thread for a session.
     * Scoped to the authenticated agent.
     */
    public function show(int $sessionId): View
    {
        $agentId = Auth::id();

        $session = AgentAiChatSession::where('id', $sessionId)
            ->where('agent_id', $agentId)
            ->with(['lead', 'messages'])
            ->firstOrFail();

        return view('agent.ai-inbox.show', compact('session'));
    }

    /**
     * POST /agent/ai-inbox/{sessionId}/mark-reviewed
     *
     * Mark a session as reviewed. Writes reviewed_at and reviewed_by_user_id.
     */
    public function markReviewed(int $sessionId): JsonResponse
    {
        $agentId = Auth::id();

        $session = AgentAiChatSession::where('id', $sessionId)
            ->where('agent_id', $agentId)
            ->firstOrFail();

        $session->update([
            'reviewed_at'          => now(),
            'reviewed_by_user_id'  => $agentId,
        ]);

        return response()->json(['status' => 'reviewed']);
    }
}
