<?php

namespace App\Services\Stellar\MatchCheck;

/**
 * Immutable result of MatchCheckOrchestrator::prepare() (Phase 4 · Wave 2 / C5).
 *
 * Describes what the consumer-facing Match Check flow WOULD do for a given
 * (listing, user) pair, without performing any scoring, rendering, or writes.
 * It is a pure composition of the Wave 0/1 decisions:
 *   - the master feature flag (config/mls_match_check.php),
 *   - ListingVisibilityGate      (F9 — IDX consumer visibility),
 *   - CriteriaIntentDetector     (F5 — sale→buyer / rent→tenant / ambiguous→null),
 *   - CriteriaListingResolver::resolvePreferred() (F5 — auto-selected criteria).
 *
 * There are exactly three terminal states:
 *   DISABLED — master flag is OFF; nothing was computed (the default, inert state).
 *   BLOCKED  — flag ON but the listing is not IDX-visible; criteria is NOT resolved.
 *   READY    — flag ON and listing visible; $intent and $preferredCriteria are populated
 *              (either may still be null: null intent = tenure-ambiguous, null criteria =
 *              user has no matching record → caller shows an empty state, never a
 *              wrong-engine score).
 */
final class MatchCheckPreparation
{
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_BLOCKED  = 'blocked';
    public const STATUS_READY    = 'ready';

    private function __construct(
        public readonly string $status,
        public readonly bool $enabled,
        public readonly bool $visible,
        public readonly string $visibilityReason,
        public readonly ?string $intent,
        public readonly ?array $preferredCriteria,
    ) {
    }

    /**
     * Master flag OFF — the orchestrator did no work. This is the default state
     * and is what keeps C5 fully inert until the owner enables the feature.
     */
    public static function disabled(): self
    {
        return new self(
            status: self::STATUS_DISABLED,
            enabled: false,
            visible: false,
            visibilityReason: 'feature_disabled',
            intent: null,
            preferredCriteria: null,
        );
    }

    /**
     * Flag ON but the listing is not consumer-visible (IDX). No criteria is resolved
     * for a listing that would never be shown.
     */
    public static function blocked(VisibilityDecision $decision): self
    {
        return new self(
            status: self::STATUS_BLOCKED,
            enabled: true,
            visible: false,
            visibilityReason: $decision->reason,
            intent: null,
            preferredCriteria: null,
        );
    }

    /**
     * Flag ON and listing visible. $intent may be null (tenure-ambiguous) and
     * $preferredCriteria may be null (no accessible record / agent must choose).
     *
     * @param  array{id: int, type: string, label: string, created_at: \Carbon\Carbon}|null  $preferredCriteria
     */
    public static function ready(VisibilityDecision $decision, ?string $intent, ?array $preferredCriteria): self
    {
        return new self(
            status: self::STATUS_READY,
            enabled: true,
            visible: true,
            visibilityReason: $decision->reason,
            intent: $intent,
            preferredCriteria: $preferredCriteria,
        );
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function hasPreferredCriteria(): bool
    {
        return $this->preferredCriteria !== null;
    }
}
