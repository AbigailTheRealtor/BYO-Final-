<?php

namespace App\Services\Dna\Relevance;

/**
 * MatchTier — the four §F6 match bands (roadmap line 141):
 * Exact Match · Strong Match · Similar Match · Opportunity Match.
 *
 * A tier is assigned to a single listing ↔ demand pair by overall Relevance and
 * which demanded categories cleared. An undetermined pair has NO tier (the
 * classifier returns a null tier), so this enum intentionally does not carry an
 * "undetermined" case — that is a distinct state, not a match band.
 */
enum MatchTier: string
{
    case Exact       = 'exact';
    case Strong      = 'strong';
    case Similar     = 'similar';
    case Opportunity = 'opportunity';

    /** Human label per the roadmap's §F6 wording. */
    public function label(): string
    {
        return match ($this) {
            self::Exact       => 'Exact Match',
            self::Strong      => 'Strong Match',
            self::Similar     => 'Similar Match',
            self::Opportunity => 'Opportunity Match',
        };
    }

    /** Ordinal where a higher rank is a better match (Exact = 4 … Opportunity = 1). */
    public function rank(): int
    {
        return match ($this) {
            self::Exact       => 4,
            self::Strong      => 3,
            self::Similar     => 2,
            self::Opportunity => 1,
        };
    }

    /** Map a computed rank back to a tier; ranks at or below 1 clamp to Opportunity. */
    public static function fromRank(int $rank): self
    {
        return match (true) {
            $rank >= 4 => self::Exact,
            $rank === 3 => self::Strong,
            $rank === 2 => self::Similar,
            default     => self::Opportunity,
        };
    }
}
