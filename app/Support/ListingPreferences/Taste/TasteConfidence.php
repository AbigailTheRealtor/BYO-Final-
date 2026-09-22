<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * A coarse, deterministic confidence TIER — never a percentage.
 *
 * The internal strength behind it is a number, and it stays internal: a
 * customer shown "73% likes pools" would read precision into three clicks.
 * The tier is what reaches words ("tend to" / "often"), and `insufficient`
 * never reaches the page at all.
 */
enum TasteConfidence: string
{
    case Insufficient = 'insufficient';
    case Emerging     = 'emerging';
    case Established  = 'established';

    public function isDisplayable(): bool
    {
        return $this !== self::Insufficient;
    }

    public function rank(): int
    {
        return match ($this) {
            self::Insufficient => 0,
            self::Emerging     => 1,
            self::Established  => 2,
        };
    }
}
