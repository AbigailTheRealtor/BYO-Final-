<?php

namespace App\Http\Controllers\AgentAi;

use App\Http\Controllers\Controller;
use App\Models\AgentAiChatLead;
use App\Models\AgentAiChatMessage;
use App\Models\AgentAiChatSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * AgentAiAnalyticsController
 *
 * Provides the analytics dashboard at GET /agent/ai-analytics.
 *
 * GOVERNANCE:
 *  - All queries are strictly scoped to auth()->id() as agent_id.
 *  - No query may aggregate, expose, or infer data from another agent's
 *    conversations, leads, sessions, or listings.
 *  - Each aggregate is cached for 5 minutes via Laravel's cache layer to
 *    avoid unbounded scans of agent_ai_chat_messages on every page load.
 */
class AgentAiAnalyticsController extends Controller
{
    private const CACHE_TTL = 300;

    public function index(): View
    {
        $agentId = (int) Auth::id();

        // ── Total questions asked ─────────────────────────────────────────────

        $totalQuestionsLast30 = Cache::remember(
            "ai_analytics_{$agentId}_q30",
            self::CACHE_TTL,
            fn () => AgentAiChatMessage::whereHas(
                'session',
                fn ($q) => $q->where('agent_id', $agentId)
            )
                ->where('role', AgentAiChatMessage::ROLE_USER)
                ->where('created_at', '>=', now()->subDays(30))
                ->count()
        );

        $totalQuestionsAllTime = Cache::remember(
            "ai_analytics_{$agentId}_qall",
            self::CACHE_TTL,
            fn () => AgentAiChatMessage::whereHas(
                'session',
                fn ($q) => $q->where('agent_id', $agentId)
            )
                ->where('role', AgentAiChatMessage::ROLE_USER)
                ->count()
        );

        // ── Most-asked topics (grouped by detected_intent) ────────────────────

        $topTopicsLast30 = Cache::remember(
            "ai_analytics_{$agentId}_topics30",
            self::CACHE_TTL,
            fn () => AgentAiChatMessage::whereHas(
                'session',
                fn ($q) => $q->where('agent_id', $agentId)
            )
                ->where('role', AgentAiChatMessage::ROLE_USER)
                ->whereNotNull('detected_intent')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('detected_intent, count(*) as cnt')
                ->groupBy('detected_intent')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get()
        );

        // ── Lead conversion rate ──────────────────────────────────────────────
        // Conversion = sessions with a captured email ÷ total sessions

        $totalSessions = Cache::remember(
            "ai_analytics_{$agentId}_sess_total",
            self::CACHE_TTL,
            fn () => AgentAiChatSession::where('agent_id', $agentId)->count()
        );

        $sessionsWithEmail = Cache::remember(
            "ai_analytics_{$agentId}_sess_email",
            self::CACHE_TTL,
            fn () => AgentAiChatSession::where('agent_id', $agentId)
                ->whereHas('lead', fn ($q) => $q->whereNotNull('visitor_email'))
                ->count()
        );

        $conversionRate = $totalSessions > 0
            ? round(($sessionsWithEmail / $totalSessions) * 100, 1)
            : 0.0;

        // ── Hot leads generated — sessions with lead_score_snapshot >= 75 ─────
        // A session is "hot" when any of its messages has lead_score_snapshot >= 75.
        // This matches the spec ("sessions with lead_score_snapshot >= 75") rather
        // than relying on the agent_ai_chat_leads row whose score may lag.

        $hotLeadsLast30 = Cache::remember(
            "ai_analytics_{$agentId}_hot30",
            self::CACHE_TTL,
            fn () => AgentAiChatSession::where('agent_id', $agentId)
                ->where('started_at', '>=', now()->subDays(30))
                ->whereHas(
                    'messages',
                    fn ($q) => $q->where('lead_score_snapshot', '>=', 75)
                )
                ->count()
        );

        $hotLeadsAllTime = Cache::remember(
            "ai_analytics_{$agentId}_hotall",
            self::CACHE_TTL,
            fn () => AgentAiChatSession::where('agent_id', $agentId)
                ->whereHas(
                    'messages',
                    fn ($q) => $q->where('lead_score_snapshot', '>=', 75)
                )
                ->count()
        );

        // ── Top CTAs clicked (grouped by action_key on user messages) ────────
        // action_key is set on user messages when a CTA action was explicitly
        // triggered by the visitor (e.g., 'view_agent_services').
        // Only messages with a non-null action_key count as CTA records.

        $topCtasLast30 = Cache::remember(
            "ai_analytics_{$agentId}_ctas30",
            self::CACHE_TTL,
            fn () => AgentAiChatMessage::whereHas(
                'session',
                fn ($q) => $q->where('agent_id', $agentId)
            )
                ->where('role', AgentAiChatMessage::ROLE_USER)
                ->whereNotNull('action_key')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('action_key, count(*) as cnt')
                ->groupBy('action_key')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get()
        );

        // ── Most viewed listings (by session count) ───────────────────────────

        $topListings = Cache::remember(
            "ai_analytics_{$agentId}_listings",
            self::CACHE_TTL,
            fn () => AgentAiChatSession::where('agent_id', $agentId)
                ->whereNotNull('listing_id')
                ->selectRaw('listing_type, listing_id, count(*) as session_count')
                ->groupBy('listing_type', 'listing_id')
                ->orderByDesc('session_count')
                ->limit(10)
                ->get()
        );

        // ── Most requested services (by intent_phrase frequency) ──────────────

        $topServices = Cache::remember(
            "ai_analytics_{$agentId}_services",
            self::CACHE_TTL,
            fn () => AgentAiChatLead::where('agent_id', $agentId)
                ->whereNotNull('intent_phrase')
                ->selectRaw('intent_phrase, count(*) as cnt')
                ->groupBy('intent_phrase')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get()
        );

        return view('agent.ai-analytics', compact(
            'totalQuestionsLast30',
            'totalQuestionsAllTime',
            'topTopicsLast30',
            'totalSessions',
            'sessionsWithEmail',
            'conversionRate',
            'hotLeadsLast30',
            'hotLeadsAllTime',
            'topCtasLast30',
            'topListings',
            'topServices'
        ));
    }
}
