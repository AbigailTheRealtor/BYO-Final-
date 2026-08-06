<?php

namespace App\Services\LocationDna\Criteria\Rules;

/**
 * Phase 1b — what {@see GeographySelectionResolver} produced: the surviving selection, plus an
 * itemised account of everything it cleared and why.
 */
final class SelectionResolution
{
    /** @param list<ClearedSelection> $cleared */
    public function __construct(
        public readonly GeographySelection $selection,
        public readonly array $cleared,
    ) {
    }

    /** Did resolution actually remove anything? */
    public function changed(): bool
    {
        return $this->cleared !== [];
    }

    /** @return list<ClearedSelection> */
    public function clearedFor(GeographyTier $tier): array
    {
        return array_values(array_filter(
            $this->cleared,
            static fn (ClearedSelection $c): bool => $c->tier === $tier
        ));
    }

    /** @return list<string> */
    public function clearedIdsFor(GeographyTier $tier): array
    {
        return array_values(array_map(
            static fn (ClearedSelection $c): string => $c->id,
            $this->clearedFor($tier)
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'selection' => $this->selection->toArray(),
            'cleared'   => array_map(
                static fn (ClearedSelection $c): array => $c->toArray(),
                $this->cleared
            ),
        ];
    }
}
