<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * What Taste DNA did to ONE candidate — the audit record behind an explanation.
 *
 * `adjustment` is bounded by TasteDnaReranker::MAX_INFLUENCE and is an ORDERING
 * key only: it is never added to, stored beside or displayed as the listing's
 * score. Internal, like every number here.
 */
final class TasteRerankInfluence
{
    /**
     * @param list<TasteRerankContribution> $contributions strongest first
     */
    public function __construct(
        public readonly string $key,
        public readonly int $basePosition,
        public readonly int $finalPosition,
        public readonly float $adjustment,
        public readonly array $contributions,
    ) {
    }

    /** The candidate changed place, and Taste evidence about IT is part of why. */
    public function moved(): bool
    {
        return $this->finalPosition !== $this->basePosition && $this->contributions !== [];
    }

    public function raised(): bool
    {
        return $this->moved() && $this->finalPosition < $this->basePosition;
    }
}
