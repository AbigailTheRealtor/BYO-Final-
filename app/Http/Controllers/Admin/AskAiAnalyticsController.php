<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AskAiAnalyticsController extends Controller
{
    /**
     * Estimated average prompt + completion tokens saved per DB hit
     * (replaces an OpenAI call). Used for cost savings estimates.
     * Configurable via config/ai.php ask_ai_savings.avg_tokens_per_db_hit.
     */
    private const DEFAULT_AVG_TOKENS_PER_DB_HIT = 800;

    /**
     * Blended cost rate (USD per 1 000 tokens) used when a model-specific
     * rate cannot be matched. Mirrors the gpt-4o prompt rate in config/ai.php.
     */
    private const DEFAULT_COST_PER_1K_TOKENS = 0.005;

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
                    [$dateFrom, $dateTo] = [$dateTo->startOfDay(), $dateFrom->endOfDay()];
                }
            } catch (\Exception $e) {
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

        // ── Fixed-window reference windows ────────────────────────────────────
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

        // ── Question type table ───────────────────────────────────────────────
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

        // ── Top 25 listing analytics ──────────────────────────────────────────
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

        // ── Rate limiter analytics ────────────────────────────────────────────
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

        // ── Daily cost table ──────────────────────────────────────────────────
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

        // ── Outcome-category breakdown ────────────────────────────────────────
        $allOutcomeCategories = [
            'database_hit'                 => 'DB Hit',
            'openai_fallback'              => 'OpenAI Fallback',
            'blank_information_not_provided' => 'Blank / Not Provided',
            'restricted'                   => 'Restricted',
            'blocked_restricted'           => 'Blocked (Restricted)',
            'unsupported'                  => 'Unsupported',
            'error'                        => 'Error',
        ];

        $outcomeRows = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('outcome_category')
            ->select('outcome_category', DB::raw('COUNT(*) as cnt'))
            ->groupBy('outcome_category')
            ->get()
            ->keyBy('outcome_category');

        $totalWithOutcome = $outcomeRows->sum('cnt');

        $outcomeData = [];
        foreach ($allOutcomeCategories as $key => $label) {
            $count = isset($outcomeRows[$key]) ? (int) $outcomeRows[$key]->cnt : 0;
            $outcomeData[] = [
                'key'        => $key,
                'label'      => $label,
                'count'      => $count,
                'percentage' => $totalWithOutcome > 0 ? round($count / $totalWithOutcome * 100, 1) : 0,
            ];
        }

        // Daily outcome trend (newest-first, up to 120 rows ≈ 30 days × 7 categories)
        $dailyOutcomeTrend = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('outcome_category')
            ->select(
                DB::raw('DATE(created_at) as date'),
                'outcome_category',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(DB::raw('DATE(created_at)'), 'outcome_category')
            ->orderByDesc(DB::raw('DATE(created_at)'))
            ->limit(120)
            ->get();

        // Weekly outcome breakdown: ISO week start (Monday) within the selected range.
        // Uses a driver-aware helper so the query runs on PostgreSQL, MySQL, and SQLite.
        $weekSql = $this->weekStartSql();
        $weeklyOutcomeTrend = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('outcome_category')
            ->select(
                DB::raw("{$weekSql} as week_start"),
                'outcome_category',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(DB::raw($weekSql), 'outcome_category')
            ->orderByDesc(DB::raw($weekSql))
            ->limit(56)   // up to 8 weeks × 7 categories
            ->get();

        // Monthly outcome breakdown: calendar month within the selected range.
        // Uses a driver-aware helper so the query runs on PostgreSQL, MySQL, and SQLite.
        $monthSql = $this->monthSql();
        $monthlyOutcomeTrend = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('outcome_category')
            ->select(
                DB::raw("{$monthSql} as month"),
                'outcome_category',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(DB::raw($monthSql), 'outcome_category')
            ->orderByDesc(DB::raw($monthSql))
            ->limit(42)   // up to 6 months × 7 categories
            ->get();

        // ── Top Unanswered Questions (openai_fallback) ────────────────────────
        // Role filter is applied in SQL before groupBy/orderBy/limit so that
        // per-role top-50 is accurate (not a post-hoc slice of a global top-50).
        $fallbackRoleFilter = $request->query('fallback_role', '');

        $topFallbackQuery = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->where('outcome_category', 'openai_fallback')
            ->whereNotNull('question_hash');

        if ($fallbackRoleFilter !== '') {
            $topFallbackQuery->where('listing_type', $fallbackRoleFilter);
        }

        $topFallbackQuestions = $topFallbackQuery
            ->select(
                'question_hash',
                'listing_type',
                DB::raw('COUNT(*) as occurrences'),
                DB::raw('MIN(created_at) as first_seen'),
                DB::raw('MAX(created_at) as last_seen')
            )
            ->groupBy('question_hash', 'listing_type')
            ->orderByDesc('occurrences')
            ->limit(50)
            ->get();

        // ── Role-scoped coverage metrics ──────────────────────────────────────
        $roleCoverageData = $this->buildRoleCoverageData($dateFromStr, $dateToStr);

        // ── Cost savings metrics ──────────────────────────────────────────────
        $savingsData = $this->buildSavingsData($dateFromStr, $dateToStr, $totalQuestions);

        return view('admin.ask-ai-analytics', compact(
            'preset',
            'rawFrom',
            'rawTo',
            'dateFrom',
            'dateTo',
            // Fixed-window reference cards
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
            // Table sections
            'modelUsage',
            'questionTypeData',
            'topListings',
            'rateLimiterData',
            'dailyCost',
            // Phase 5 additions
            'outcomeData',
            'dailyOutcomeTrend',
            'weeklyOutcomeTrend',
            'monthlyOutcomeTrend',
            'topFallbackQuestions',
            'fallbackRoleFilter',
            'roleCoverageData',
            'savingsData'
        ));
    }

    // =========================================================================
    // Role-scoped coverage helpers
    // =========================================================================

    private function buildRoleCoverageData(string $dateFromStr, string $dateToStr): array
    {
        $roles = ['seller', 'buyer', 'landlord', 'tenant'];

        // Registry-mapped field counts per role (source: PHP registry)
        $registryCountsByRole = $this->computeRegistryCountsByRole();

        // Latest ready snapshot per (listing_type, listing_id).
        // Counting across ALL ready snapshots would overstate coverage because
        // multiple historical versions of a listing each contribute canonical keys.
        // This subquery selects only the highest-version ready snapshot per listing
        // using a portable MAX(version) GROUP BY approach (works on PG, MySQL, SQLite).
        $maxVersionSub = DB::table('ask_ai_knowledge_snapshots')
            ->where('status', 'ready')
            ->select('listing_type', 'listing_id', DB::raw('MAX(version) as max_version'))
            ->groupBy('listing_type', 'listing_id');

        $latestSnapSubquery = DB::table('ask_ai_knowledge_snapshots as snaps')
            ->joinSub($maxVersionSub, 'mv', function ($join) {
                $join->on('snaps.listing_type', '=', 'mv.listing_type')
                     ->on('snaps.listing_id', '=', 'mv.listing_id')
                     ->on('snaps.version', '=', 'mv.max_version');
            })
            ->where('snaps.status', 'ready')
            ->select('snaps.id', 'snaps.listing_type', 'snaps.listing_id');

        // Snapshot-covered questions per role: from latest-snapshot subquery only.
        $snapshotCoveredByRole = DB::table('ask_ai_questions')
            ->joinSub($latestSnapSubquery, 'latest_snaps', function ($join) {
                $join->on('ask_ai_questions.snapshot_id', '=', 'latest_snaps.id');
            })
            ->select('latest_snaps.listing_type', DB::raw('COUNT(DISTINCT ask_ai_questions.canonical_key) as covered'))
            ->groupBy('latest_snaps.listing_type')
            ->get()
            ->keyBy('listing_type');

        // Answerable fields per role: distinct canonical_keys in ask_ai_answers
        // with a non-empty answer_text, from latest-snapshot subquery only.
        $answerableByRole = DB::table('ask_ai_answers')
            ->joinSub($latestSnapSubquery, 'latest_snaps', function ($join) {
                $join->on('ask_ai_answers.snapshot_id', '=', 'latest_snaps.id');
            })
            ->whereNotNull('ask_ai_answers.answer_text')
            ->where('ask_ai_answers.answer_text', '<>', '')
            ->select('latest_snaps.listing_type', DB::raw('COUNT(DISTINCT ask_ai_answers.canonical_key) as answerable'))
            ->groupBy('latest_snaps.listing_type')
            ->get()
            ->keyBy('listing_type');

        // Restricted facts per role: from latest-snapshot subquery only.
        $restrictedByRole = DB::table('ask_ai_facts')
            ->joinSub($latestSnapSubquery, 'latest_snaps', function ($join) {
                $join->on('ask_ai_facts.snapshot_id', '=', 'latest_snaps.id');
            })
            ->where('ask_ai_facts.restricted', true)
            ->select('latest_snaps.listing_type', DB::raw('COUNT(DISTINCT ask_ai_facts.canonical_key) as restricted'))
            ->groupBy('latest_snaps.listing_type')
            ->get()
            ->keyBy('listing_type');

        // DB-hit rate per role from usage logs (active date range).
        $dbHitsByRole = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->where('outcome_category', 'database_hit')
            ->whereNotNull('listing_type')
            ->select('listing_type', DB::raw('COUNT(*) as hits'))
            ->groupBy('listing_type')
            ->get()
            ->keyBy('listing_type');

        $totalByRole = DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->whereNotNull('listing_type')
            ->select('listing_type', DB::raw('COUNT(*) as total'))
            ->groupBy('listing_type')
            ->get()
            ->keyBy('listing_type');

        $roleCoverageData = [];
        foreach ($roles as $role) {
            $registryMapped  = $registryCountsByRole[$role] ?? 0;
            $snapshotCovered = (int) ($snapshotCoveredByRole[$role]->covered ?? 0);
            $answerable      = (int) ($answerableByRole[$role]->answerable ?? 0);
            $restricted      = (int) ($restrictedByRole[$role]->restricted ?? 0);
            $dbHits          = (int) ($dbHitsByRole[$role]->hits ?? 0);
            $roleTotal       = (int) ($totalByRole[$role]->total ?? 0);

            $coveragePct = $registryMapped > 0
                ? round($snapshotCovered / $registryMapped * 100, 1)
                : 0;

            $dbHitRate = $roleTotal > 0
                ? round($dbHits / $roleTotal * 100, 1)
                : 0;

            $roleCoverageData[] = [
                'role'             => $role,
                'registry_mapped'  => $registryMapped,
                'snapshot_covered' => $snapshotCovered,
                'answerable'       => $answerable,
                'restricted'       => $restricted,
                'coverage_pct'     => $coveragePct,
                'db_hit_rate'      => $dbHitRate,
                'db_hits'          => $dbHits,
                'total_questions'  => $roleTotal,
            ];
        }

        return $roleCoverageData;
    }

    /**
     * Compute total registry-mapped field counts per role from the PHP registry.
     * Combines both registry() (FAQ entries) and listingFieldRegistry() entries.
     */
    private function computeRegistryCountsByRole(): array
    {
        $counts = ['seller' => 0, 'buyer' => 0, 'landlord' => 0, 'tenant' => 0];

        $allEntries = array_merge(
            AskAiFieldQuestionRegistryService::registry(),
            AskAiFieldQuestionRegistryService::listingFieldRegistry()
        );

        foreach ($allEntries as $entry) {
            $roles = $entry['roles'] ?? [];
            foreach ($roles as $role) {
                if (isset($counts[$role])) {
                    $counts[$role]++;
                }
            }
        }

        return $counts;
    }

    // =========================================================================
    // Cost savings helpers
    // =========================================================================

    private function buildSavingsData(string $dateFromStr, string $dateToStr, int $totalQuestions): array
    {
        $avgTokensPerDbHit = (int) config('ai.ask_ai_savings.avg_tokens_per_db_hit', self::DEFAULT_AVG_TOKENS_PER_DB_HIT);
        $costPer1kTokens   = (float) config('ai.ask_ai_savings.cost_per_1k_tokens', self::DEFAULT_COST_PER_1K_TOKENS);

        $dbHits = (int) DB::table('ask_ai_usage_logs')
            ->whereBetween('created_at', [$dateFromStr, $dateToStr])
            ->where('outcome_category', 'database_hit')
            ->count();

        $tokensAvoided     = $dbHits * $avgTokensPerDbHit;
        $estimatedSavedUsd = ($tokensAvoided / 1000) * $costPer1kTokens;

        // Monthly run-rate extrapolation based on the active date range length.
        $daysDiff = max(1, (int) round(
            (strtotime($dateToStr) - strtotime($dateFromStr)) / 86400
        ));
        $monthlyEstimateUsd = $daysDiff > 0
            ? round($estimatedSavedUsd / $daysDiff * 30, 4)
            : 0.0;

        $dbHitPct = $totalQuestions > 0
            ? round($dbHits / $totalQuestions * 100, 1)
            : 0;

        return [
            'db_hits'               => $dbHits,
            'db_hit_pct'            => $dbHitPct,
            'avg_tokens_per_db_hit' => $avgTokensPerDbHit,
            'tokens_avoided'        => $tokensAvoided,
            'estimated_saved_usd'   => round($estimatedSavedUsd, 4),
            'monthly_estimate_usd'  => $monthlyEstimateUsd,
            'cost_per_1k_tokens'    => $costPer1kTokens,
            'methodology_note'      => "Tokens avoided = DB hits × {$avgTokensPerDbHit} avg tokens/call. "
                . "Cost = tokens avoided ÷ 1 000 × \${$costPer1kTokens} (blended gpt-4o prompt rate). "
                . "Monthly estimate extrapolated from the active {$daysDiff}-day range.",
        ];
    }

    // =========================================================================
    // Database-driver-aware date expression helpers
    // =========================================================================

    /**
     * Returns a raw SQL expression for the ISO week start (Monday) of created_at
     * that is compatible with PostgreSQL, MySQL 5.7+, and SQLite 3.x.
     */
    private function weekStartSql(): string
    {
        return match(DB::getDriverName()) {
            'pgsql'  => "DATE_TRUNC('week', created_at)::date",
            'mysql'  => "DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(DATE(created_at)) DAY)",
            default  => "date(created_at, 'weekday 1', '-6 days')",  // SQLite
        };
    }

    /**
     * Returns a raw SQL expression for the YYYY-MM month string of created_at
     * that is compatible with PostgreSQL, MySQL 5.7+, and SQLite 3.x.
     */
    private function monthSql(): string
    {
        return match(DB::getDriverName()) {
            'pgsql'  => "TO_CHAR(created_at, 'YYYY-MM')",
            'mysql'  => "DATE_FORMAT(created_at, '%Y-%m')",
            default  => "strftime('%Y-%m', created_at)",  // SQLite
        };
    }

    // =========================================================================
    // Date preset helper
    // =========================================================================

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
