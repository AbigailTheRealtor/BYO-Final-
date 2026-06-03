<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AskAiAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // ── Resolve and validate date range ──────────────────────────────────
        $rawFrom = $request->query('from');
        $rawTo   = $request->query('to');
        $preset  = $request->query('preset', 'last_30');

        if ($preset === 'custom' && $rawFrom && $rawTo) {
            try {
                $dateFrom = Carbon::createFromFormat('Y-m-d', $rawFrom)->startOfDay();
                $dateTo   = Carbon::createFromFormat('Y-m-d', $rawTo)->endOfDay();
                if ($dateTo->lt($dateFrom)) {
                    // Swap silently so the page never 500s on reversed ranges
                    [$dateFrom, $dateTo] = [$dateTo->startOfDay(), $dateFrom->endOfDay()];
                }
            } catch (\Exception $e) {
                // Fall back to last 30 days on any malformed date input
                $preset   = 'last_30';
                $rawFrom  = null;
                $rawTo    = null;
                [$dateFrom, $dateTo] = $this->presetRange('last_30');
            }
        } else {
            [$dateFrom, $dateTo] = $this->presetRange($preset);
        }

        $dateFromStr = $dateFrom->toDateTimeString();
        $dateToStr   = $dateTo->toDateTimeString();

        // ── Fixed-window reference windows (always computed, ignore active filter) ──
        $todayStart  = Carbon::today()->startOfDay()->toDateTimeString();
        $todayEnd    = Carbon::today()->endOfDay()->toDateTimeString();
        $last7Start  = Carbon::today()->subDays(6)->startOfDay()->toDateTimeString();
        $last30Start = Carbon::today()->subDays(29)->startOfDay()->toDateTimeString();

        $questionsToday  = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $questionsLast7  = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$last7Start, $todayEnd])
            ->count();

        $questionsLast30 = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$last30Start, $todayEnd])
            ->count();

        $costToday  = (float) DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('estimated_cost_usd');

        $costLast7  = (float) DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$last7Start, $todayEnd])
            ->sum('estimated_cost_usd');

        $costLast30 = (float) DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$last30Start, $todayEnd])
            ->sum('estimated_cost_usd');

        // ── Active-filter summary cards ───────────────────────────────────────
        $totalQuestions = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->count();

        $totalCost = (float) DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->sum('estimated_cost_usd');

        $avgCostPerQuestion = $totalQuestions > 0
            ? (float) DB::table('ask_ai_usage_logs')
                ->whereBetween('created_at', [$dateFromStr, $dateToStr])
                ->whereNotNull('estimated_cost_usd')
                ->avg('estimated_cost_usd')
            : 0.0;

        $uniqueListings = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('listing_id')
            ->distinct()
            ->count(DB::raw("listing_type || ':' || listing_id::text"));

        $rateLimiterKeys = [
            'guest_ip_hourly',
            'user_hourly',
            'admin_daily',
            'ip_shared_hourly',
            'listing_hourly',
        ];

        $rateLimitedCount = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereIn('error_code', $rateLimiterKeys)
            ->count();

        // ── Model usage table ────────────────────────────────────────────────
        $modelUsage = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('model')
            ->select(
                'model',
                DB::raw('COUNT(*) as questions'),
                DB::raw('SUM(prompt_tokens) as prompt_tokens'),
                DB::raw('SUM(completion_tokens) as completion_tokens'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as estimated_cost')
            )
            ->groupBy('model')
            ->orderByDesc('questions')
            ->get();

        // ── Question type table (all nine canonical types) ───────────────────
        $allQuestionTypes = [
            'property_standout',
            'suited_audience',
            'buyer_tenant_match',
            'compatibility_signals',
            'missing_data',
            'marketing_angles',
            'educational',
            'unsupported',
            'blocked',
        ];

        $questionTypeRows = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->select('question_type', DB::raw('COUNT(*) as questions'))
            ->groupBy('question_type')
            ->get()
            ->keyBy('question_type');

        $questionTypeData = [];
        foreach ($allQuestionTypes as $type) {
            $count = isset($questionTypeRows[$type]) ? (int) $questionTypeRows[$type]->questions : 0;
            $questionTypeData[] = [
                'type'       => $type,
                'questions'  => $count,
                'percentage' => $totalQuestions > 0 ? round($count / $totalQuestions * 100, 1) : 0,
            ];
        }

        // ── Top 25 listing analytics ─────────────────────────────────────────
        $topListings = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('listing_id')
            ->select(
                'listing_id',
                'listing_type',
                DB::raw('COUNT(*) as questions'),
                DB::raw('SUM(estimated_cost_usd) as estimated_cost')
            )
            ->groupBy('listing_id', 'listing_type')
            ->orderByDesc('questions')
            ->limit(25)
            ->get();

        // ── Rate limiter analytics ───────────────────────────────────────────
        $rateLimiterRows = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereIn('error_code', $rateLimiterKeys)
            ->select('error_code', DB::raw('COUNT(*) as hits'))
            ->groupBy('error_code')
            ->get()
            ->keyBy('error_code');

        $rateLimiterData = [];
        foreach ($rateLimiterKeys as $key) {
            $rateLimiterData[] = [
                'error_code' => $key,
                'hits'       => isset($rateLimiterRows[$key]) ? (int) $rateLimiterRows[$key]->hits : 0,
            ];
        }

        // ── Daily cost table (up to 30 rows within the active date filter) ───
        $dailyCost = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as questions'),
                DB::raw('SUM(prompt_tokens) as prompt_tokens'),
                DB::raw('SUM(completion_tokens) as completion_tokens'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as estimated_cost')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc(DB::raw('DATE(created_at)'))
            ->limit(30)
            ->get();

        return view('admin.ask-ai-analytics', compact(
            'preset',
            'rawFrom',
            'rawTo',
            'dateFrom',
            'dateTo',
            // Fixed-window reference cards (always Today / Last 7 / Last 30)
            'questionsToday',
            'questionsLast7',
            'questionsLast30',
            'costToday',
            'costLast7',
            'costLast30',
            // Active-filter summary cards
            'totalQuestions',
            'totalCost',
            'avgCostPerQuestion',
            'uniqueListings',
            'rateLimitedCount',
            // Table sections (all filter-scoped)
            'modelUsage',
            'questionTypeData',
            'topListings',
            'rateLimiterData',
            'dailyCost'
        ));
    }

    private function presetRange(string $preset): array
    {
        switch ($preset) {
            case 'today':
                return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
            case 'last_7':
                return [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()];
            case 'last_30':
            default:
                return [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()];
        }
    }
}
