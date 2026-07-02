<?php

namespace App\Services\Dna\Relevance;

/**
 * MatchTierResult — the tier assigned to one listing ↔ demand pair, with the
 * §F5 "why it was included and where it falls short" breakdown.
 *
 * `tier` is null when the pair is undetermined (no demanded overlap, or no
 * demanded dimension had property data) — an undetermined pair is not a match
 * band. `clearedKeys` / `shortfallKeys` / `gapKeys` partition the demanded
 * dimensions: cleared (relevance met the bar), fell short (determined but
 * below the bar), and gap (no property data). This object persists nothing.
 */
final class MatchTierResult
{
    /**
     * @param string[] $clearedKeys
     * @param string[] $shortfallKeys
     * @param string[] $gapKeys
     */
    public function __construct(
        public readonly ?MatchTier $tier,
        public readonly ?int $value,
        public readonly int $confidence,
        public readonly int $coverage,
        public readonly array $clearedKeys,
        public readonly array $shortfallKeys,
        public readonly array $gapKeys,
        public readonly string $explanation,
    ) {
    }

    /**
     * @param string[] $gapKeys
     */
    public static function undetermined(int $coverage, string $explanation, array $gapKeys = []): self
    {
        return new self(null, null, 0, $coverage, [], [], $gapKeys, $explanation);
    }

    public function isUndetermined(): bool
    {
        return $this->tier === null;
    }

    /**
     * @return array{tier:?string,tier_label:?string,value:?int,confidence:int,coverage:int,cleared:string[],shortfall:string[],gaps:string[],explanation:string}
     */
    public function toArray(): array
    {
        return [
            'tier'        => $this->tier?->value,
            'tier_label'  => $this->tier?->label(),
            'value'       => $this->value,
            'confidence'  => $this->confidence,
            'coverage'    => $this->coverage,
            'cleared'     => $this->clearedKeys,
            'shortfall'   => $this->shortfallKeys,
            'gaps'        => $this->gapKeys,
            'explanation' => $this->explanation,
        ];
    }
}
