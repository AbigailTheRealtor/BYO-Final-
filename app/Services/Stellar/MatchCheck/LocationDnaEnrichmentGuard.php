<?php

namespace App\Services\Stellar\MatchCheck;

use Carbon\CarbonInterface;

/**
 * Location DNA enrichment guard / throttle (Phase 4 · git-C12 / Plan-C8 · F6).
 *
 * Answers a single read-only question: "is Match Check allowed to trigger Location DNA
 * enrichment for this (listing, user) right now?" It encodes the F6 throttle contract that
 * already lives, forward-declared and inert, in config/mls_match_check.php:
 *   - per-listing cooldown (dedupe)   — mls_match_check.dna_cooldown_hours
 *   - per-user hourly rate limit      — mls_match_check.dna_rate_limit_per_user_hourly
 *
 * PURE / SIDE-EFFECT-FREE BY DESIGN. decide() performs no cache read, no DB read, no
 * RateLimiter::hit(), no dispatch, and no logging. It is a total function of its arguments and
 * config, so the same inputs always yield the same decision. The throttle STATE is supplied by
 * the caller as an immutable EnrichmentThrottleSnapshot; building that snapshot (cache reads) and
 * recording an attempt after an allow (RateLimiter::hit()) are git-C13's job, not this slice's.
 *
 * INERT / UNWIRED. Nothing constructs or calls this guard yet — no route, controller, Livewire
 * component, middleware, job, or the MatchCheckOrchestrator. Existing enrichment paths
 * (ComputeLocationDna, LocationDnaPipelineRunner, LocationDnaEnrichmentRunner) are untouched.
 * git-C13 routes enrichment through it.
 *
 * See docs/match-check-phase4-git-c12-scope.md.
 */
class LocationDnaEnrichmentGuard
{
    /**
     * Decide whether enrichment may be triggered. Rules are evaluated in a fixed order and the
     * first match short-circuits (see scope §2.3):
     *   1. FEATURE_DISABLED — master flag OFF (default). Mirrors MatchCheckOrchestrator::isEnabled().
     *   2. COOLDOWN_ACTIVE  — listing enriched < dna_cooldown_hours ago (per-listing dedupe).
     *   3. RATE_LIMITED     — user's attempts in the trailing hour >= dna_rate_limit_per_user_hourly.
     *   4. ALLOWED          — none of the above.
     *
     * Cooldown is checked BEFORE the rate limit so a repeat view of an already-fresh listing is
     * served from cache without spending one of the user's scarce hourly enrichment tokens.
     *
     * @param  string  $listingType  Canonical listing type (e.g. matches ComputeLocationDna's arg).
     * @param  int  $listingId       Listing identifier.
     * @param  int  $userId          Acting user's identifier.
     * @param  EnrichmentThrottleSnapshot  $state  Caller-supplied read-only throttle state.
     * @param  CarbonInterface  $now  Injected clock — deterministic and testable.
     */
    public function decide(
        string $listingType,
        int $listingId,
        int $userId,
        EnrichmentThrottleSnapshot $state,
        CarbonInterface $now,
    ): EnrichmentGuardDecision {
        // 1. Feature gate — single source of truth, identical to MatchCheckOrchestrator. Default OFF,
        //    so while the master flag is unset the guard always denies (no meaningful retry time).
        if (! (bool) config('mls_match_check.enabled', false)) {
            return EnrichmentGuardDecision::deny(EnrichmentGuardDecision::REASON_FEATURE_DISABLED);
        }

        $nowTs = $now->getTimestamp();

        // 2. Per-listing cooldown (dedupe). Active only when the listing was enriched within the
        //    cooldown window. A zero/absent cooldown or a never-enriched listing passes through.
        $cooldownHours = (int) config('mls_match_check.dna_cooldown_hours', 24);
        if ($cooldownHours > 0 && $state->listingLastEnrichedAt !== null) {
            $cooldownEndTs = $state->listingLastEnrichedAt->getTimestamp() + ($cooldownHours * 3600);
            if ($nowTs < $cooldownEndTs) {
                return EnrichmentGuardDecision::deny(
                    EnrichmentGuardDecision::REASON_COOLDOWN_ACTIVE,
                    $cooldownEndTs - $nowTs,
                );
            }
        }

        // 3. Per-user hourly rate limit. Deny at or above the limit (matches RateLimiter
        //    ::tooManyAttempts / AskAiRateLimitService semantics).
        $rateLimit = (int) config('mls_match_check.dna_rate_limit_per_user_hourly', 20);
        if ($state->userAttemptsInWindow >= $rateLimit) {
            $retryAfter = null;
            if ($state->userWindowResetsAt !== null) {
                $retryAfter = max(0, $state->userWindowResetsAt->getTimestamp() - $nowTs);
            }

            return EnrichmentGuardDecision::deny(
                EnrichmentGuardDecision::REASON_RATE_LIMITED,
                $retryAfter,
            );
        }

        // 4. Nothing blocks it.
        return EnrichmentGuardDecision::allow();
    }
}
