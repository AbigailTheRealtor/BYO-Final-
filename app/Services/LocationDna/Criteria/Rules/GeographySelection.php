<?php

namespace App\Services\LocationDna\Criteria\Rules;

/**
 * Phase 1b — an immutable Criteria geography selection: one state, its counties, and optional
 * cities and ZIP codes.
 *
 * DELIBERATELY DUMB
 * -----------------
 * This DTO applies NO cascade rules. Handing it a new state does not clear the counties underneath;
 * that is {@see GeographySelectionResolver}'s single responsibility. If the DTO cleared as well,
 * rule C1 would live in two places and the two copies would drift — and a caller could no longer
 * hold a not-yet-resolved selection, which is precisely what a form post is.
 *
 * IT ALSO DOES NOT DEDUPLICATE OR NORMALISE
 * -----------------------------------------
 * A duplicate id survives construction on purpose. {@see GeographySelectionValidator} has to be
 * able to SEE a duplicate to report one, and a DTO that quietly repaired its input would make rule
 * S3 untestable and would hide the caller bug that produced it. The same argument applies to an
 * unpadded ZIP: it is reported, never silently padded. Phase 1a pads on the way OUT of the
 * repository, which is the one place padding belongs.
 *
 * WHY ZIPS ARE SCALARS AND EVERYTHING ELSE IS AN ID
 * ------------------------------------------------
 * States, counties and cities are carried by `GeographyOption::$id` — the application primary key.
 * ZIPs cannot be: a ZIP option's identity is the pair (id, parentId), because the same ZIP is
 * emitted once per associated county. A flat set of "ZIP option ids" would therefore be ambiguous.
 * So the selection carries ZIP CODES — five-digit strings — and the association back to counties is
 * re-derived from the corpus whenever it is needed. That also keeps the whole DTO serialisable to a
 * form post with no hydration step, which Phase 1c needs.
 */
final class GeographySelection
{
    /**
     * `$neighborhoodIds` (Phase 1d-5) carries the tier below cities. It is LAST in the signature so
     * that every existing positional call — of which there are many in the suites — keeps meaning
     * what it meant, and an old four-argument call yields an empty neighbourhood tier rather than
     * silently shifting ZIPs into it.
     *
     * @param  list<string>  $countyIds
     * @param  list<string>  $cityIds
     * @param  list<string>  $zipCodes
     * @param  list<string>  $neighborhoodIds
     */
    private function __construct(
        public readonly ?string $stateId,
        public readonly array $countyIds,
        public readonly array $cityIds,
        public readonly array $zipCodes,
        public readonly array $neighborhoodIds = [],
    ) {
    }

    public static function empty(): self
    {
        return new self(null, [], [], [], []);
    }

    /**
     * @param  list<string|int>  $countyIds
     * @param  list<string|int>  $cityIds
     * @param  list<string|int>  $zipCodes
     * @param  list<string|int>  $neighborhoodIds
     */
    public static function of(
        string|int|null $stateId = null,
        array $countyIds = [],
        array $cityIds = [],
        array $zipCodes = [],
        array $neighborhoodIds = [],
    ): self {
        return new self(
            self::scalarOrNull($stateId),
            self::stringList($countyIds),
            self::stringList($cityIds),
            self::stringList($zipCodes),
            self::stringList($neighborhoodIds),
        );
    }

    public function withState(string|int|null $stateId): self
    {
        return new self(self::scalarOrNull($stateId), $this->countyIds, $this->cityIds, $this->zipCodes, $this->neighborhoodIds);
    }

    /** @param list<string|int> $countyIds */
    public function withCounties(array $countyIds): self
    {
        return new self($this->stateId, self::stringList($countyIds), $this->cityIds, $this->zipCodes, $this->neighborhoodIds);
    }

    /** @param list<string|int> $cityIds */
    public function withCities(array $cityIds): self
    {
        return new self($this->stateId, $this->countyIds, self::stringList($cityIds), $this->zipCodes, $this->neighborhoodIds);
    }

    /** @param list<string|int> $zipCodes */
    public function withZipCodes(array $zipCodes): self
    {
        return new self($this->stateId, $this->countyIds, $this->cityIds, self::stringList($zipCodes), $this->neighborhoodIds);
    }

    /** @param list<string|int> $neighborhoodIds */
    public function withNeighborhoods(array $neighborhoodIds): self
    {
        return new self($this->stateId, $this->countyIds, $this->cityIds, $this->zipCodes, self::stringList($neighborhoodIds));
    }

    public function hasState(): bool
    {
        return $this->stateId !== null && $this->stateId !== '';
    }

    /** @return list<string> the ids selected in one tier */
    public function idsFor(GeographyTier $tier): array
    {
        return match ($tier) {
            GeographyTier::State         => $this->hasState() ? [(string) $this->stateId] : [],
            GeographyTier::Counties      => $this->countyIds,
            GeographyTier::Cities        => $this->cityIds,
            GeographyTier::Neighborhoods => $this->neighborhoodIds,
            GeographyTier::ZipCodes      => $this->zipCodes,
        };
    }

    /** Is anything at all selected? An empty selection is incomplete but not invalid. */
    public function isEmpty(): bool
    {
        return ! $this->hasState()
            && $this->countyIds === []
            && $this->cityIds === []
            && $this->neighborhoodIds === []
            && $this->zipCodes === [];
    }

    /** Order-insensitive value equality, so a resolver no-op is detectable. */
    public function equals(self $other): bool
    {
        if ($this->stateId !== $other->stateId) {
            return false;
        }

        foreach ([GeographyTier::Counties, GeographyTier::Cities, GeographyTier::Neighborhoods, GeographyTier::ZipCodes] as $tier) {
            $mine   = $this->idsFor($tier);
            $theirs = $other->idsFor($tier);

            sort($mine);
            sort($theirs);

            if ($mine !== $theirs) {
                return false;
            }
        }

        return true;
    }

    /**
     * The selection as an array, keyed by tier.
     *
     * ⚠ NOT THE STORAGE FORMAT, and the `neighborhoods` key is where that distinction starts to
     * matter. This is a DTO dump for debugging and for {@see SelectionResolution::toArray()}; what
     * gets PERSISTED is {@see \App\Services\LocationDna\Criteria\Projection\GeographyLabelProjector}'s
     * output, which has four keys and folds neighbourhoods into `cities`. Anything writing this
     * array to a blob would invent a fifth stored key that no consumer reads — see
     * {@see GeographyTier::Neighborhoods}.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            GeographyTier::State->value         => $this->stateId,
            GeographyTier::Counties->value      => $this->countyIds,
            GeographyTier::Cities->value        => $this->cityIds,
            GeographyTier::Neighborhoods->value => $this->neighborhoodIds,
            GeographyTier::ZipCodes->value      => $this->zipCodes,
        ];
    }

    private static function scalarOrNull(string|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Cast to strings and drop nothing else — duplicates and blanks are the validator's to report.
     *
     * @param  list<string|int>  $values
     * @return list<string>
     */
    private static function stringList(array $values): array
    {
        $out = [];

        foreach ($values as $value) {
            $out[] = trim((string) $value);
        }

        return array_values($out);
    }
}
