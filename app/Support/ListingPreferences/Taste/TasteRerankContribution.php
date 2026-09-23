<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * One learned signal that touched one candidate, and which way.
 *
 * INTERNAL. `weight` is a number and never reaches a customer; the explanation
 * presenter reads only the signal's label, direction, tier and sources.
 */
final class TasteRerankContribution
{
    public function __construct(
        public readonly TasteSignal $signal,
        public readonly float $weight,
    ) {
    }

    public function isPositive(): bool
    {
        return $this->weight > 0;
    }
}
