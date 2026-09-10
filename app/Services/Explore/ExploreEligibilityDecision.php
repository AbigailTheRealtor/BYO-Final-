<?php

namespace App\Services\Explore;

/**
 * Why a record is or is not on the Explore map.
 *
 * The reason string is for logs and tests, never for a consumer: telling the
 * public which listings were withheld and why is itself a disclosure. Nothing
 * in this class reaches a response body.
 */
final class ExploreEligibilityDecision
{
    private function __construct(
        public readonly bool $eligible,
        public readonly string $reason,
        public readonly ?ExploreTransactionType $transactionType = null,
    ) {}

    public static function eligible(ExploreTransactionType $type): self
    {
        return new self(true, 'eligible', $type);
    }

    public static function excluded(string $reason): self
    {
        return new self(false, $reason);
    }
}
