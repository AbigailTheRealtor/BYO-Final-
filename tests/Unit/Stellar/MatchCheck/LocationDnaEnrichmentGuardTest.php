<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Services\Stellar\MatchCheck\EnrichmentGuardDecision;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleSnapshot;
use App\Services\Stellar\MatchCheck\LocationDnaEnrichmentGuard;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Tests\TestCase;

/**
 * Phase 4 · git-C12 / Plan-C8 — LocationDnaEnrichmentGuard (F6).
 *
 * Pure decision contract: no cache / DB / RateLimiter is touched because the throttle state is
 * injected as an EnrichmentThrottleSnapshot and the clock is passed in. Precedence under test:
 * FEATURE_DISABLED → COOLDOWN_ACTIVE → RATE_LIMITED → ALLOWED.
 */
class LocationDnaEnrichmentGuardTest extends TestCase
{
    private const LISTING_TYPE = 'buyer_agent_auction';
    private const LISTING_ID   = 4321;
    private const USER_ID      = 99;

    private function guard(): LocationDnaEnrichmentGuard
    {
        return new LocationDnaEnrichmentGuard();
    }

    /** A fixed, deterministic "now" — no reliance on wall-clock. */
    private function now(): CarbonInterface
    {
        return Carbon::create(2026, 7, 6, 12, 0, 0);
    }

    private function decide(EnrichmentThrottleSnapshot $state): EnrichmentGuardDecision
    {
        return $this->guard()->decide(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::USER_ID,
            $state,
            $this->now(),
        );
    }

    /** A snapshot that would otherwise pass every rule (fresh, well under the rate limit). */
    private function passingSnapshot(): EnrichmentThrottleSnapshot
    {
        return new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: null,
            userAttemptsInWindow: 0,
            userWindowResetsAt: null,
        );
    }

    /** @test */
    public function flag_off_denies_even_when_state_would_otherwise_pass(): void
    {
        // Rely on the config default (false) — do NOT set it here.
        $decision = $this->decide($this->passingSnapshot());

        $this->assertFalse($decision->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_FEATURE_DISABLED, $decision->reason);
        $this->assertNull($decision->retryAfterSeconds);
    }

    /** @test */
    public function cooldown_active_denies_and_reports_retry_after(): void
    {
        config()->set('mls_match_check.enabled', true);
        config()->set('mls_match_check.dna_cooldown_hours', 24);

        // Enriched 1h ago → 23h of cooldown remain.
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: $this->now()->copy()->subHour(),
            userAttemptsInWindow: 0,
            userWindowResetsAt: null,
        );

        $decision = $this->decide($state);

        $this->assertFalse($decision->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_COOLDOWN_ACTIVE, $decision->reason);
        $this->assertSame(23 * 3600, $decision->retryAfterSeconds);
    }

    /** @test */
    public function cooldown_wins_over_rate_limit_when_both_hold(): void
    {
        config()->set('mls_match_check.enabled', true);
        config()->set('mls_match_check.dna_cooldown_hours', 24);
        config()->set('mls_match_check.dna_rate_limit_per_user_hourly', 20);

        // Fresh cooldown AND an exhausted rate limit — cooldown is checked first.
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: $this->now()->copy()->subHour(),
            userAttemptsInWindow: 20,
            userWindowResetsAt: $this->now()->copy()->addMinutes(30),
        );

        $decision = $this->decide($state);

        $this->assertFalse($decision->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_COOLDOWN_ACTIVE, $decision->reason);
    }

    /** @test */
    public function expired_cooldown_does_not_block(): void
    {
        config()->set('mls_match_check.enabled', true);
        config()->set('mls_match_check.dna_cooldown_hours', 24);

        // Enriched 25h ago → cooldown lapsed; well under rate limit → ALLOWED.
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: $this->now()->copy()->subHours(25),
            userAttemptsInWindow: 0,
            userWindowResetsAt: null,
        );

        $this->assertTrue($this->decide($state)->allowed);
    }

    /** @test */
    public function never_enriched_listing_does_not_block_on_cooldown(): void
    {
        config()->set('mls_match_check.enabled', true);

        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: null,
            userAttemptsInWindow: 0,
            userWindowResetsAt: null,
        );

        $this->assertTrue($this->decide($state)->allowed);
    }

    /** @test */
    public function rate_limit_reached_denies_at_or_above_limit(): void
    {
        config()->set('mls_match_check.enabled', true);
        config()->set('mls_match_check.dna_rate_limit_per_user_hourly', 20);

        // At the limit (>= boundary), cooldown clear.
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: null,
            userAttemptsInWindow: 20,
            userWindowResetsAt: $this->now()->copy()->addMinutes(15),
        );

        $decision = $this->decide($state);

        $this->assertFalse($decision->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_RATE_LIMITED, $decision->reason);
        $this->assertSame(15 * 60, $decision->retryAfterSeconds);
    }

    /** @test */
    public function rate_limit_null_reset_yields_null_retry_after(): void
    {
        config()->set('mls_match_check.enabled', true);
        config()->set('mls_match_check.dna_rate_limit_per_user_hourly', 20);

        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: null,
            userAttemptsInWindow: 25,
            userWindowResetsAt: null,
        );

        $decision = $this->decide($state);

        $this->assertSame(EnrichmentGuardDecision::REASON_RATE_LIMITED, $decision->reason);
        $this->assertNull($decision->retryAfterSeconds);
    }

    /** @test */
    public function under_rate_limit_is_allowed(): void
    {
        config()->set('mls_match_check.enabled', true);
        config()->set('mls_match_check.dna_rate_limit_per_user_hourly', 20);

        // One below the limit, cooldown clear.
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: null,
            userAttemptsInWindow: 19,
            userWindowResetsAt: $this->now()->copy()->addMinutes(30),
        );

        $decision = $this->decide($state);

        $this->assertTrue($decision->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_ALLOWED, $decision->reason);
        $this->assertNull($decision->retryAfterSeconds);
    }

    /** @test */
    public function thresholds_are_config_driven(): void
    {
        config()->set('mls_match_check.enabled', true);

        // Tighten the cooldown to 2h: a 3h-old enrichment must now pass where 24h would block it.
        config()->set('mls_match_check.dna_cooldown_hours', 2);
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: $this->now()->copy()->subHours(3),
            userAttemptsInWindow: 0,
            userWindowResetsAt: null,
        );
        $this->assertTrue($this->decide($state)->allowed);

        // Lower the rate limit to 5: 5 attempts now trips it.
        config()->set('mls_match_check.dna_rate_limit_per_user_hourly', 5);
        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: null,
            userAttemptsInWindow: 5,
            userWindowResetsAt: null,
        );
        $this->assertSame(
            EnrichmentGuardDecision::REASON_RATE_LIMITED,
            $this->decide($state)->reason,
        );
    }

    /** @test */
    public function decision_is_deterministic_for_identical_inputs(): void
    {
        config()->set('mls_match_check.enabled', true);

        $state = new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: $this->now()->copy()->subHours(3),
            userAttemptsInWindow: 7,
            userWindowResetsAt: $this->now()->copy()->addMinutes(10),
        );

        $a = $this->decide($state);
        $b = $this->decide($state);

        $this->assertSame($a->allowed, $b->allowed);
        $this->assertSame($a->reason, $b->reason);
        $this->assertSame($a->retryAfterSeconds, $b->retryAfterSeconds);
    }

    /** @test */
    public function decision_dto_factories_set_documented_shape(): void
    {
        $allow = EnrichmentGuardDecision::allow();
        $this->assertTrue($allow->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_ALLOWED, $allow->reason);
        $this->assertNull($allow->retryAfterSeconds);

        $deny = EnrichmentGuardDecision::deny(EnrichmentGuardDecision::REASON_RATE_LIMITED, 120);
        $this->assertFalse($deny->allowed);
        $this->assertSame(EnrichmentGuardDecision::REASON_RATE_LIMITED, $deny->reason);
        $this->assertSame(120, $deny->retryAfterSeconds);
    }
}
