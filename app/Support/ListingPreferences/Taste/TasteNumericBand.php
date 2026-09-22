<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * The typical range of one structured fact across a set of homes.
 *
 * The middle half (a deterministic lower and upper quartile by index), not the
 * full span, so one unusual house does not stretch "usually" into a
 * meaningless range. `count` is how many homes contributed a value.
 */
final class TasteNumericBand
{
    public function __construct(
        public readonly float $low,
        public readonly float $high,
        public readonly int $count,
    ) {
    }

    /**
     * @param list<float> $values already rounded to the dimension's precision
     */
    public static function from(array $values): ?self
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);

        $n    = count($values);
        $low  = $values[(int) floor(($n - 1) * 0.25)];
        $high = $values[(int) ceil(($n - 1) * 0.75)];

        return new self((float) $low, (float) $high, $n);
    }

    public function overlaps(self $other): bool
    {
        return $this->low <= $other->high && $other->low <= $this->high;
    }

    /** @return array{low: float, high: float, count: int} */
    public function toArray(): array
    {
        return ['low' => $this->low, 'high' => $this->high, 'count' => $this->count];
    }
}
