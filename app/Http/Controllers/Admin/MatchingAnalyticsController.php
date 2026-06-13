<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MatchingAnalyticsController
 *
 * Admin-only dashboard for P7 Matching Analytics.
 * Displays:
 *   - Readiness funnel breakdown by role and property type
 *   - Score distribution (Quick Match vs Full Match)
 *   - True stage-to-stage conversion funnel (Not Ready → Quick → Full → Hired)
 *     computed from bid_funnel_timestamps (first-entry-only, immutable)
 *   - Recommendation click-through and acceptance rates (with attribution)
 *   - Time range filters: 7 / 30 / 90 / all
 *
 * No personally identifiable consumer data is exposed.
 * All queries target append-only analytics tables; no listing or user PII.
 */
class MatchingAnalyticsController extends Controller
{
    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    public function index(Request $request)
    {
        $range = $request->query('range', '30');
        [$dateFrom, $dateTo] = $this->resolveRange($range);

        $dateFromStr = $dateFrom?->toDateTimeString();
        $dateToStr   = $dateTo->toDateTimeString();

        // ── Readiness funnel breakdown (by role + property type) ──────────────
        $funnelData = $this->buildFunnelData($dateFromStr, $dateToStr);

        // ── Score distribution ────────────────────────────────────────────────
        $scoreDistribution = $this->buildScoreDistribution($dateFromStr, $dateToStr);

        // ── True stage-to-stage conversion funnel ─────────────────────────────
        $conversionRates = $this->buildConversionRates($dateFromStr, $dateToStr);

        // ── Recommendation effectiveness ──────────────────────────────────────
        $recommendationData = $this->buildRecommendationData($dateFromStr, $dateToStr);

        // ── Top-level summary cards ───────────────────────────────────────────
        $summaryCards = $this->buildSummaryCards($dateFromStr, $dateToStr);

        return view('admin.matching-analytics', compact(
            'range',
            'dateFrom',
            'dateTo',
            'funnelData',
            'scoreDistribution',
            'conversionRates',
            'recommendationData',
            'summaryCards'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Funnel breakdown — by role + property type
    // ─────────────────────────────────────────────────────────────────────────

    private function buildFunnelData(?string $from, string $to): array
    {
        $query = DB::table('bid_score_snapshots')
            ->select('role', 'property_type', 'readiness_state', DB::raw('COUNT(*) as cnt'))
            ->groupBy('role', 'property_type', 'readiness_state')
            ->orderBy('role')
            ->orderBy('property_type');

        if ($from !== null) {
            $query->whereBetween('captured_at', [$from, $to]);
        } else {
            $query->where('captured_at', '<=', $to);
        }

        $rows = $query->get()->groupBy('role');

        $data = [];
        foreach (self::ROLES as $role) {
            $roleRows = $rows->get($role, collect());

            // Aggregate role-level totals (across all property types)
            $roleTotal    = $roleRows->sum('cnt');
            $byState      = $roleRows->groupBy('readiness_state')
                ->map(fn ($g) => $g->sum('cnt'));

            $notReady   = (int) ($byState->get('not_ready') ?? 0);
            $quickReady = (int) ($byState->get('quick_match_ready') ?? 0);
            $fullReady  = (int) ($byState->get('full_match_ready') ?? 0);

            // Per-property-type breakdown
            $byPropertyType = $roleRows->groupBy('property_type')
                ->map(function ($ptRows, $pt) {
                    $ptTotal    = $ptRows->sum('cnt');
                    $ptByState  = $ptRows->keyBy('readiness_state');
                    $ptNot      = (int) ($ptByState->get('not_ready')?->cnt ?? 0);
                    $ptQuick    = (int) ($ptByState->get('quick_match_ready')?->cnt ?? 0);
                    $ptFull     = (int) ($ptByState->get('full_match_ready')?->cnt ?? 0);
                    return [
                        'property_type'    => $pt,
                        'total'            => $ptTotal,
                        'not_ready'        => $ptNot,
                        'quick_match_ready' => $ptQuick,
                        'full_match_ready'  => $ptFull,
                        'not_ready_pct'    => $ptTotal > 0 ? round($ptNot   / $ptTotal * 100, 1) : 0,
                        'quick_ready_pct'  => $ptTotal > 0 ? round($ptQuick / $ptTotal * 100, 1) : 0,
                        'full_ready_pct'   => $ptTotal > 0 ? round($ptFull  / $ptTotal * 100, 1) : 0,
                    ];
                })->values();

            $data[] = [
                'role'              => $role,
                'total'             => $roleTotal,
                'not_ready'         => $notReady,
                'quick_match_ready' => $quickReady,
                'full_match_ready'  => $fullReady,
                'not_ready_pct'     => $roleTotal > 0 ? round($notReady   / $roleTotal * 100, 1) : 0,
                'quick_ready_pct'   => $roleTotal > 0 ? round($quickReady / $roleTotal * 100, 1) : 0,
                'full_ready_pct'    => $roleTotal > 0 ? round($fullReady  / $roleTotal * 100, 1) : 0,
                'by_property_type'  => $byPropertyType,
            ];
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Score distribution
    // ─────────────────────────────────────────────────────────────────────────

    private function buildScoreDistribution(?string $from, string $to): array
    {
        $query = DB::table('bid_score_snapshots')
            ->whereNotNull('score_value')
            ->whereIn('score_type', ['quick_match', 'full_match'])
            ->select(
                'role',
                'score_type',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('ROUND(AVG(score_value)) as avg_score'),
                DB::raw('MIN(score_value) as min_score'),
                DB::raw('MAX(score_value) as max_score')
            )
            ->groupBy('role', 'score_type')
            ->orderBy('role');

        if ($from !== null) {
            $query->whereBetween('captured_at', [$from, $to]);
        } else {
            $query->where('captured_at', '<=', $to);
        }

        $rows = $query->get();

        $bucketsQuery = DB::table('bid_score_snapshots')
            ->whereNotNull('score_value')
            ->whereIn('score_type', ['quick_match', 'full_match'])
            ->select(
                'score_type',
                DB::raw('FLOOR(score_value / 10) * 10 as bucket'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('score_type', DB::raw('FLOOR(score_value / 10) * 10'))
            ->orderBy('score_type')
            ->orderBy('bucket');

        if ($from !== null) {
            $bucketsQuery->whereBetween('captured_at', [$from, $to]);
        } else {
            $bucketsQuery->where('captured_at', '<=', $to);
        }

        $buckets = $bucketsQuery->get()->groupBy('score_type');

        return [
            'by_role' => $rows,
            'buckets' => $buckets,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // True stage-to-stage conversion funnel — uses bid_funnel_timestamps
    //
    // Each timestamp is first-entry-only and immutable, making them the
    // authoritative source for funnel progression rates.
    //
    // Funnel stages:
    //   Not Ready ──► Quick Match Ready ──► Full Match Ready ──► Agent Hired
    //                                (and separately)
    //   Submitted ──► Accepted ──► Agent Hired
    // ─────────────────────────────────────────────────────────────────────────

    private function buildConversionRates(?string $from, string $to): array
    {
        $data = [];

        foreach (self::ROLES as $role) {
            $base = DB::table('bid_funnel_timestamps')->where('role', $role);

            // Count bids that reached a given funnel stage within the time window,
            // using THAT STAGE'S OWN timestamp as the range anchor.
            //
            // This is intentionally different from a cohort filter on created_at.
            // A bid submitted 60 days ago that was accepted 2 days ago SHOULD appear
            // in the "accepted" count for a 7-day window — it represents real pipeline
            // activity during the requested period, regardless of when the funnel row
            // was first created.
            $stageCount = function (string $col) use ($base, $from, $to): int {
                $q = (clone $base)->whereNotNull($col);
                if ($from !== null) {
                    $q->whereBetween($col, [$from, $to]);
                } else {
                    $q->where($col, '<=', $to);
                }
                return $q->count();
            };

            $notReady   = $stageCount('not_ready_at');
            $quickReady = $stageCount('quick_match_ready_at');
            $fullReady  = $stageCount('full_match_ready_at');
            $submitted  = $stageCount('bid_submitted_at');
            $accepted   = $stageCount('bid_accepted_at');
            $hired      = $stageCount('agent_hired_at');

            // "total" anchors on submission — the earliest bidder-facing action.
            $total = $submitted;

            $notToQuick     = $notReady   > 0 ? round($quickReady / $notReady   * 100, 1) : 0.0;
            $quickToFull    = $quickReady > 0 ? round($fullReady  / $quickReady * 100, 1) : 0.0;
            $fullToHired    = $fullReady  > 0 ? round($hired      / $fullReady  * 100, 1) : 0.0;
            $submitToAccept = $submitted  > 0 ? round($accepted   / $submitted  * 100, 1) : 0.0;
            $acceptToHired  = $accepted   > 0 ? round($hired      / $accepted   * 100, 1) : 0.0;
            $submitToHired  = $submitted  > 0 ? round($hired      / $submitted  * 100, 1) : 0.0;

            $data[] = [
                'role'               => $role,
                'total'              => $total,
                'not_ready'          => $notReady,
                'quick_match_ready'  => $quickReady,
                'full_match_ready'   => $fullReady,
                'submitted'          => $submitted,
                'accepted'           => $accepted,
                'hired'              => $hired,
                // Stage-to-stage funnel rates
                'not_to_quick_rate'  => $notToQuick,
                'quick_to_full_rate' => $quickToFull,
                'full_to_hired_rate' => $fullToHired,
                // Linear funnel rates
                'submit_to_accept'   => $submitToAccept,
                'accept_to_hired'    => $acceptToHired,
                'submit_to_hired'    => $submitToHired,
            ];
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recommendation effectiveness
    // ─────────────────────────────────────────────────────────────────────────

    private function buildRecommendationData(?string $from, string $to): array
    {
        $q = DB::table('recommendation_interactions')
            ->select(
                'event_type',
                'from_recommendation',
                'recommendation_surface',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('event_type', 'from_recommendation', 'recommendation_surface')
            ->orderBy('event_type');

        if ($from !== null) {
            $q->whereBetween('created_at', [$from, $to]);
        } else {
            $q->where('created_at', '<=', $to);
        }

        $rows = $q->get();

        $viewedRec    = $rows->where('event_type', 'bid_viewed')->where('from_recommendation', true)->sum('cnt');
        $viewedTotal  = $rows->where('event_type', 'bid_viewed')->sum('cnt');
        $acceptedRec  = $rows->where('event_type', 'bid_accepted')->where('from_recommendation', true)->sum('cnt');
        $acceptedTotal = $rows->where('event_type', 'bid_accepted')->sum('cnt');
        $hiredRec     = $rows->where('event_type', 'agent_hired')->where('from_recommendation', true)->sum('cnt');
        $hiredTotal   = $rows->where('event_type', 'agent_hired')->sum('cnt');

        $ctr        = $viewedTotal  > 0 ? round($viewedRec   / $viewedTotal  * 100, 1) : 0.0;
        $acceptRate = $viewedRec    > 0 ? round($acceptedRec / max($viewedRec, 1) * 100, 1) : 0.0;
        $hireRate   = $acceptedRec  > 0 ? round($hiredRec    / max($acceptedRec, 1) * 100, 1) : 0.0;

        $bySurface = $rows->where('from_recommendation', true)
            ->groupBy('recommendation_surface')
            ->map(fn ($g) => [
                'surface'  => $g->first()->recommendation_surface,
                'viewed'   => (int) $g->where('event_type', 'bid_viewed')->sum('cnt'),
                'accepted' => (int) $g->where('event_type', 'bid_accepted')->sum('cnt'),
                'hired'    => (int) $g->where('event_type', 'agent_hired')->sum('cnt'),
            ])->values();

        return [
            'viewed_total'    => (int) $viewedTotal,
            'viewed_rec'      => (int) $viewedRec,
            'accepted_total'  => (int) $acceptedTotal,
            'accepted_rec'    => (int) $acceptedRec,
            'hired_total'     => (int) $hiredTotal,
            'hired_rec'       => (int) $hiredRec,
            'ctr'             => $ctr,
            'rec_accept_rate' => $acceptRate,
            'rec_hire_rate'   => $hireRate,
            'by_surface'      => $bySurface,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Summary cards
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSummaryCards(?string $from, string $to): array
    {
        $q = DB::table('bid_score_snapshots');
        if ($from !== null) {
            $q->whereBetween('captured_at', [$from, $to]);
        } else {
            $q->where('captured_at', '<=', $to);
        }

        $totalSnapshots = (clone $q)->count();
        $totalBids      = (clone $q)->whereNotNull('bid_id')->distinct('bid_id')->count('bid_id');
        $avgScore       = (clone $q)->whereNotNull('score_value')->avg('score_value');
        $fullReadyCount = (clone $q)->where('readiness_state', 'full_match_ready')->count();
        $hiredCount     = (clone $q)->where('event_type', 'agent_hired')->count();

        $riQ = DB::table('recommendation_interactions');
        if ($from !== null) {
            $riQ->whereBetween('created_at', [$from, $to]);
        } else {
            $riQ->where('created_at', '<=', $to);
        }
        $recInteractions = (clone $riQ)->where('from_recommendation', true)->count();

        return [
            'total_snapshots'   => $totalSnapshots,
            'total_bids'        => $totalBids,
            'avg_score'         => $avgScore !== null ? round((float) $avgScore, 1) : null,
            'full_ready_count'  => $fullReadyCount,
            'hired_count'       => $hiredCount,
            'rec_interactions'  => $recInteractions,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Date range helper
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveRange(string $range): array
    {
        $to = Carbon::today()->endOfDay();
        return match ($range) {
            '7'   => [Carbon::today()->subDays(6)->startOfDay(), $to],
            '30'  => [Carbon::today()->subDays(29)->startOfDay(), $to],
            '90'  => [Carbon::today()->subDays(89)->startOfDay(), $to],
            'all' => [null, $to],
            default => [Carbon::today()->subDays(29)->startOfDay(), $to],
        };
    }
}
