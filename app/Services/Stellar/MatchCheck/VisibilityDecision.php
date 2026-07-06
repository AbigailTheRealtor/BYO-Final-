<?php

namespace App\Services\Stellar\MatchCheck;

/**
 * Result of a ListingVisibilityGate check (Phase 4 · F9).
 *
 * Carries a machine-readable reason so the consumer-facing "blocked" message and
 * a future permission-gated internal path can branch on *why* a listing is (in)visible
 * without re-deriving the check.
 */
final class VisibilityDecision
{
    public function __construct(
        public readonly bool $visible,
        public readonly string $reason,
    ) {
    }

    public static function visible(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function blocked(string $reason): self
    {
        return new self(false, $reason);
    }
}
