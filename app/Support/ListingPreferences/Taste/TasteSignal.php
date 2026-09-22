<?php

namespace App\Support\ListingPreferences\Taste;

use DateTimeImmutable;

/**
 * One learned pattern, with everything needed to explain it.
 *
 * EXPLAINABLE BY CONSTRUCTION. A signal answers, from its own fields:
 *   what pattern?           dimension + key (+ label)
 *   which way?              direction
 *   how sure?               confidence (tier) — strength and agreement are the
 *                           internal numbers behind it and never reach a customer
 *   how many choices?       supportCount, and save / maybe / pass counts
 *   from what?              sources — a reason the customer chose, a governed
 *                           characteristic of the homes, or both
 *   over what period?       firstAt / lastAt of the supporting choices
 *
 * Counts are DISTINCT HOMES. One home changed back and forth ten times is one
 * home, so repetition strengthens a pattern only across independent choices.
 */
final class TasteSignal
{
    /**
     * @param list<TasteSource> $sources sorted by value
     */
    public function __construct(
        public readonly TasteDimension $dimension,
        public readonly string $key,
        public readonly string $label,
        public readonly TasteDirection $direction,
        public readonly TasteConfidence $confidence,
        public readonly float $strength,
        public readonly float $agreement,
        public readonly float $positiveWeight,
        public readonly float $negativeWeight,
        public readonly float $uncertainWeight,
        public readonly int $supportCount,
        public readonly int $saveCount,
        public readonly int $maybeCount,
        public readonly int $passCount,
        public readonly ?DateTimeImmutable $firstAt,
        public readonly ?DateTimeImmutable $lastAt,
        public readonly array $sources,
        public readonly ?TasteNumericBand $saveBand = null,
        public readonly ?TasteNumericBand $passBand = null,
    ) {
    }

    public function isDisplayable(): bool
    {
        return $this->confidence->isDisplayable();
    }

    public function hasSource(TasteSource $source): bool
    {
        return in_array($source, $this->sources, true);
    }

    /** "<dimension>:<key>" — stable across rebuilds. */
    public function id(): string
    {
        return $this->dimension->value . ':' . $this->key;
    }

    /**
     * The full audit shape. Internal: this is what a rebuild is compared on and
     * what a reviewer reads; the customer page goes through the presenter.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dimension'        => $this->dimension->value,
            'key'              => $this->key,
            'label'            => $this->label,
            'direction'        => $this->direction->value,
            'confidence'       => $this->confidence->value,
            'strength'         => $this->strength,
            'agreement'        => $this->agreement,
            'positive_weight'  => $this->positiveWeight,
            'negative_weight'  => $this->negativeWeight,
            'uncertain_weight' => $this->uncertainWeight,
            'support_count'    => $this->supportCount,
            'save_count'       => $this->saveCount,
            'maybe_count'      => $this->maybeCount,
            'pass_count'       => $this->passCount,
            'first_at'         => $this->firstAt?->format(DATE_ATOM),
            'last_at'          => $this->lastAt?->format(DATE_ATOM),
            'sources'          => array_map(static fn (TasteSource $s): string => $s->value, $this->sources),
            'save_band'        => $this->saveBand?->toArray(),
            'pass_band'        => $this->passBand?->toArray(),
        ];
    }
}
