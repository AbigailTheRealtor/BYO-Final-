<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\GeographyOption;

/**
 * M1 — the answer to one {@see GeographyQuery}.
 *
 * TRUNCATION IS REPORTED, NEVER SILENT — the same rule the cascade's own OPTION_LIMIT follows. A
 * list that quietly stops at fifteen reads as "this is everything", and a user who cannot find
 * their town in it concludes the corpus is missing rather than that they should type more.
 */
final class GeographySearchResult
{
    /**
     * @param  list<GeographyMatch>  $matches   ranked, best first
     * @param  bool                  $truncated were there more matches than the limit allowed?
     */
    private function __construct(
        public readonly array $matches,
        public readonly bool $truncated,
    ) {
    }

    /** @param list<GeographyMatch> $matches */
    public static function of(array $matches, bool $truncated = false): self
    {
        return new self(array_values($matches), $truncated);
    }

    /**
     * No matches, and nothing was cut off.
     *
     * The one answer every implementation can always give — an unusable term, a null repository, a
     * tier that is switched off. Named so those call sites read as a decision rather than as an
     * empty array literal that might be an oversight.
     */
    public static function empty(): self
    {
        return new self([], false);
    }

    public function isEmpty(): bool
    {
        return $this->matches === [];
    }

    public function count(): int
    {
        return count($this->matches);
    }

    /** @return list<GeographyOption> */
    public function options(): array
    {
        return array_map(static fn (GeographyMatch $m): GeographyOption => $m->option, $this->matches);
    }

    /**
     * Only the matches in one tier's kind.
     *
     * @return list<GeographyMatch>
     */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter(
            $this->matches,
            static fn (GeographyMatch $m): bool => $m->option->is($kind)
        ));
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (GeographyMatch $m): array => $m->toArray(), $this->matches);
    }
}
