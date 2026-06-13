<?php

namespace App\Services;

use App\Models\BidFunnelTimestamp;
use App\Models\BidScoreSnapshot;
use App\Models\RecommendationInteraction;
use Illuminate\Support\Facades\Log;

/**
 * BidAnalyticsService
 *
 * Captures append-only analytics for the matching pipeline:
 *
 *   captureSnapshot()          — Score snapshot at a bid lifecycle event
 *   advanceFunnel()            — First-entry timestamp per funnel stage (never overwrites)
 *   recordRecommendation()     — Recommendation interaction with attribution flag
 *
 * All writes are wrapped in try/catch so analytics failures never disrupt
 * the core bid flow. Silent failures are logged for monitoring.
 *
 * Duplicate snapshot prevention:
 *   A guard_key combining bid_type, bid_id, event_type, and the current
 *   minute is stored as a unique column. If the observer fires twice in the
 *   same minute for the same event (e.g., multiple meta saves), the second
 *   INSERT is quietly skipped.
 *
 * No personally identifiable consumer data is stored in any analytics row.
 */
class BidAnalyticsService
{
    /**
     * Event types recognised by the snapshot system.
     */
    public const EVENT_BID_CREATED   = 'bid_created';
    public const EVENT_BID_UPDATED   = 'bid_updated';
    public const EVENT_BID_SUBMITTED = 'bid_submitted';
    public const EVENT_BID_ACCEPTED  = 'bid_accepted';
    public const EVENT_AGENT_HIRED   = 'agent_hired';

    /**
     * Funnel stage column map.
     * Each key is a readiness state or event trigger; the value is the
     * nullable timestamp column in bid_funnel_timestamps.
     */
    private const FUNNEL_STAGE_COLUMNS = [
        'not_ready'          => 'not_ready_at',
        'quick_match_ready'  => 'quick_match_ready_at',
        'full_match_ready'   => 'full_match_ready_at',
        self::EVENT_BID_SUBMITTED => 'bid_submitted_at',
        self::EVENT_BID_ACCEPTED  => 'bid_accepted_at',
        self::EVENT_AGENT_HIRED   => 'agent_hired_at',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Score snapshots
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Capture a score snapshot for a bid lifecycle event.
     *
     * Calls CompatibilityScoreService internally using the supplied listing
     * and bid data arrays. If scoring fails the snapshot is still recorded
     * with readiness_state='unknown' and score_type='none'.
     *
     * @param  string      $bidType      'seller_agent'|'buyer_agent'|'landlord_agent'|'tenant_agent'
     * @param  int         $bidId
     * @param  string      $role         'seller'|'buyer'|'landlord'|'tenant'
     * @param  string|null $propertyType
     * @param  string      $eventType    One of EVENT_* constants
     * @param  array       $listingData  Decoded listing/criteria data for scoring
     * @param  array       $bidData      Decoded bid data for scoring
     */
    public static function captureSnapshot(
        string $bidType,
        int $bidId,
        string $role,
        ?string $propertyType,
        string $eventType,
        array $listingData,
        array $bidData
    ): void {
        try {
            $guardKey = self::buildGuardKey($bidType, $bidId, $eventType);

            // Skip if a snapshot for this event already exists within this minute
            if (BidScoreSnapshot::where('guard_key', $guardKey)->exists()) {
                return;
            }

            $scoreResult = self::safeScore($listingData, $bidData, $role, $propertyType);

            BidScoreSnapshot::create([
                'bid_type'        => $bidType,
                'bid_id'          => $bidId,
                'role'            => $role,
                'property_type'   => $propertyType,
                'event_type'      => $eventType,
                'readiness_state' => $scoreResult['readiness_state'],
                'score_type'      => $scoreResult['score_type'],
                'score_value'     => $scoreResult['score'],
                'scoring_version' => CompatibilityScoreService::SCORING_VERSION,
                'guard_key'       => $guardKey,
                'captured_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[BidAnalytics] captureSnapshot failed', [
                'bid_type'   => $bidType,
                'bid_id'     => $bidId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Capture a snapshot for an agent_hired event triggered by HireAgentLead.
     * No listing/bid scoring is possible; stored with score_type='none'.
     *
     * @param  string      $role
     * @param  string|null $propertyType
     */
    public static function captureAgentHiredSnapshot(
        string $role,
        ?string $propertyType
    ): void {
        try {
            // For HireAgentLead hires there is no bid, so guard_key uses a random suffix
            $guardKey = self::buildGuardKey('hire_lead', 0, self::EVENT_AGENT_HIRED)
                . ':' . uniqid('', true);

            BidScoreSnapshot::create([
                'bid_type'        => null,
                'bid_id'          => null,
                'role'            => $role,
                'property_type'   => $propertyType,
                'event_type'      => self::EVENT_AGENT_HIRED,
                'readiness_state' => 'unknown',
                'score_type'      => 'none',
                'score_value'     => null,
                'scoring_version' => CompatibilityScoreService::SCORING_VERSION,
                'guard_key'       => $guardKey,
                'captured_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[BidAnalytics] captureAgentHiredSnapshot failed', [
                'role'  => $role,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Funnel timestamps
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record the first time a bid enters a funnel stage.
     * If the stage timestamp is already set, the call is a no-op.
     *
     * @param  string $bidType
     * @param  int    $bidId
     * @param  string $role
     * @param  string $stage  Key from FUNNEL_STAGE_COLUMNS
     */
    public static function advanceFunnel(
        string $bidType,
        int $bidId,
        string $role,
        string $stage
    ): void {
        $column = self::FUNNEL_STAGE_COLUMNS[$stage] ?? null;
        if ($column === null) {
            return;
        }

        try {
            $row = BidFunnelTimestamp::firstOrNew(
                ['bid_type' => $bidType, 'bid_id' => $bidId],
                ['role' => $role]
            );

            // First-entry-only: never overwrite an existing timestamp
            if ($row->{$column} === null) {
                $row->role     = $row->role ?: $role;
                $row->{$column} = now();
                $row->save();
            }
        } catch (\Throwable $e) {
            Log::warning('[BidAnalytics] advanceFunnel failed', [
                'bid_type' => $bidType,
                'bid_id'   => $bidId,
                'stage'    => $stage,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recommendation interactions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a recommendation interaction event.
     *
     * @param  string      $eventType           'bid_viewed'|'bid_accepted'|'agent_hired'
     * @param  string      $role
     * @param  bool        $fromRecommendation  TRUE only for recommendation-surface actions
     * @param  string|null $surface             Recommendation surface identifier
     * @param  string|null $bidType
     * @param  int|null    $bidId
     * @param  string|null $propertyType
     * @param  int|null    $userId
     * @param  array       $metadata
     */
    public static function recordRecommendationInteraction(
        string $eventType,
        string $role,
        bool $fromRecommendation,
        ?string $surface = null,
        ?string $bidType = null,
        ?int $bidId = null,
        ?string $propertyType = null,
        ?int $userId = null,
        array $metadata = []
    ): void {
        try {
            RecommendationInteraction::create([
                'bid_type'                => $bidType,
                'bid_id'                  => $bidId,
                'role'                    => $role,
                'property_type'           => $propertyType,
                'event_type'              => $eventType,
                'from_recommendation'     => $fromRecommendation,
                'recommendation_surface'  => $fromRecommendation ? $surface : null,
                'user_id'                 => $userId,
                'metadata'                => !empty($metadata) ? $metadata : null,
            ]);

            // Always persist recommendation context on bid_viewed — both true and false.
            //
            // When from_recommendation=true: store the surface so downstream bid_accepted /
            // agent_hired events on the same bid can be attributed without re-transmitting
            // the query string.
            //
            // When from_recommendation=false: explicitly overwrite any previously stored
            // rec context for this bid with false. Without this clear, a user who views a
            // bid from a recommendation surface (stores true) and then revisits the same
            // bid page via a normal link would still have the stale true context carried
            // forward to a later bid_accepted / agent_hired event — inflating attribution.
            if ($eventType === 'bid_viewed' && $bidId > 0 && $bidType !== null) {
                self::storeRecContext($bidType, $bidId, $fromRecommendation, $fromRecommendation ? $surface : null);
            }
        } catch (\Throwable $e) {
            Log::warning('[BidAnalytics] recordRecommendationInteraction failed', [
                'event_type' => $eventType,
                'role'       => $role,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recommendation attribution context — session propagation helpers
    //
    // When a bid detail page is viewed via a recommendation link (?from_rec=1),
    // the attribution context (which surface drove the view) is stored in the
    // session so that subsequent bid_accepted and agent_hired events on the
    // same bid can be attributed to the recommendation without needing the
    // query string to be re-submitted.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Store recommendation attribution context for a specific bid.
     *
     * Two-layer storage:
     *  1. Static in-memory store — the primary layer. Works in all execution
     *     contexts (web requests, CLI, tests). Provides fast within-request
     *     lookups for bid_accepted when viewed and accepted in the same request.
     *  2. Session — the cross-request carry-over layer. In production, a user
     *     typically views a bid (Request A) then accepts it later (Request B).
     *     The session carries the attribution across those requests.
     */
    public static function storeRecContext(string $bidType, int $bidId, bool $fromRec, ?string $surface): void
    {
        $storeKey = "{$bidType}.{$bidId}";
        $value = [
            'from_recommendation' => $fromRec,
            'surface'             => $fromRec ? $surface : null,
        ];

        // Primary: static in-memory (always works)
        self::$recContextStore[$storeKey] = $value;

        // Secondary: session for cross-request carry-over (best effort)
        try {
            if (!app()->runningInConsole() && session()->isStarted()) {
                session()->put("analytics_rec_ctx.{$storeKey}", $value);
            }
        } catch (\Throwable $e) {
            // Session unavailable — in-memory layer is sufficient for current request
        }
    }

    /**
     * Retrieve stored recommendation attribution context for a bid.
     *
     * Lookup order:
     *  1. Static in-memory store (same request — fastest path)
     *  2. Session (cross-request carry-over from a prior page view)
     *  3. Default non-attributed context
     *
     * @return array{from_recommendation: bool, surface: string|null}
     */
    public static function getRecContext(string $bidType, int $bidId): array
    {
        $default  = ['from_recommendation' => false, 'surface' => null];
        $storeKey = "{$bidType}.{$bidId}";

        // 1. In-memory store (same request / same test method)
        if (isset(self::$recContextStore[$storeKey])) {
            $ctx = self::$recContextStore[$storeKey];
            return [
                'from_recommendation' => (bool) ($ctx['from_recommendation'] ?? false),
                'surface'             => ($ctx['from_recommendation'] ?? false) ? ($ctx['surface'] ?? null) : null,
            ];
        }

        // 2. Session (cross-request carry-over)
        try {
            if (!app()->runningInConsole() && session()->isStarted()) {
                $ctx = session("analytics_rec_ctx.{$storeKey}");
                if (is_array($ctx)) {
                    return [
                        'from_recommendation' => (bool) ($ctx['from_recommendation'] ?? false),
                        'surface'             => ($ctx['from_recommendation'] ?? false) ? ($ctx['surface'] ?? null) : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }

        return $default;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a deduplication guard key.
     *
     * Strategy:
     *  - Once-only events (bid_created, bid_submitted, bid_accepted, agent_hired):
     *    key is deterministic from bid identity + event type, with NO time component.
     *    DB unique constraint enforces exactly-once semantics over the bid lifetime.
     *
     *  - Repeatable events (bid_updated):
     *    key uses a static per-request token that is:
     *      • The SAME throughout one PHP request — so if Eloquent fires the updated
     *        observer twice from a single save() call, the second INSERT hits the
     *        unique constraint and is silently skipped (one snapshot per trigger).
     *      • DIFFERENT across separate requests — so each real user edit produces
     *        its own distinct snapshot row.
     */
    private static function buildGuardKey(string $bidType, int $bidId, string $eventType): string
    {
        $onceOnlyEvents = [
            self::EVENT_BID_CREATED,
            self::EVENT_BID_SUBMITTED,
            self::EVENT_BID_ACCEPTED,
            self::EVENT_AGENT_HIRED,
        ];

        if (in_array($eventType, $onceOnlyEvents, true) && $bidId > 0) {
            return hash('sha256', "$bidType:$bidId:$eventType");
        }

        // bid_updated: one snapshot per logical update trigger (one PHP request).
        return hash('sha256', "$bidType:$bidId:$eventType:" . self::requestToken());
    }

    /**
     * Static per-request token. Generated once on first call; reset between
     * requests automatically because PHP-FPM starts a fresh process state.
     * In tests, reset via resetRequestToken() at the start of each test method.
     */
    private static ?string $requestToken = null;

    /**
     * In-memory store for recommendation attribution context, keyed by
     * "{bidType}.{bidId}". Acts as the primary fast-path — works in all
     * execution contexts (web, CLI, tests). Populated by storeRecContext()
     * and consumed by getRecContext().
     *
     * In production (PHP-FPM), this only persists within the current request.
     * The session layer provides cross-request carry-over so that a user who
     * views a bid in Request A can have their acceptance in Request B attributed.
     */
    private static array $recContextStore = [];

    private static function requestToken(): string
    {
        if (self::$requestToken === null) {
            self::$requestToken = uniqid('req_', true);
        }
        return self::$requestToken;
    }

    /**
     * Reset the per-request token AND the in-memory rec-context store.
     * Call this in test setUp() so each test method gets a fresh token
     * and rec context from prior tests does not bleed through.
     */
    public static function resetRequestToken(): void
    {
        self::$requestToken = null;
        self::$recContextStore = [];
    }

    /**
     * Call CompatibilityScoreService::score() safely.
     * Returns a minimal 'not_ready'/'none'/null result on any failure.
     */
    private static function safeScore(
        array $listingData,
        array $bidData,
        string $role,
        ?string $propertyType
    ): array {
        try {
            return CompatibilityScoreService::score($listingData, $bidData, $role, $propertyType);
        } catch (\Throwable $e) {
            Log::debug('[BidAnalytics] score computation failed during snapshot', [
                'role'  => $role,
                'error' => $e->getMessage(),
            ]);
            return [
                'readiness_state' => 'unknown',
                'score_type'      => 'none',
                'score'           => null,
            ];
        }
    }
}
