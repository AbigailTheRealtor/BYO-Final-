<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;

/**
 * M1 — an in-memory {@see GeographySearchRepository} over a hand-built fixture.
 *
 * WHY A FAKE AND NOT A MOCK
 * -------------------------
 * The same reasoning {@see \App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository}
 * records: the suite runs SQLite in-memory, and the behaviour worth testing — tier filtering,
 * scoping, ranking, truncation, collapse — is behaviour of the SEAM, not of the corpus. A fixture
 * of six places makes an ambiguity test readable; 32,188 real ones make it a lottery.
 *
 * IT SHARES THE REAL CLASSIFIER AND THE REAL RANKER, WHICH IS THE WHOLE POINT
 * ---------------------------------------------------------------------------
 * {@see TermMatcher} and {@see GeographyMatchRanker} are used here exactly as the census
 * implementation uses them. A fake that scored its own way would let ranking tests pass against
 * logic production never runs — the classic way a test suite ends up testing its own doubles. What
 * differs between the two implementations is only how candidates are FETCHED.
 *
 * Test-only in practice, but it ships in `app/` rather than `tests/` because the container binds it
 * when `geography_source = fake`, and a binding cannot reach a class the autoloader does not see
 * outside the test environment.
 */
final class FakeGeographySearchRepository implements GeographySearchRepository
{
    /** @var list<array{option: GeographyOption, breadcrumb: string, parentIds: list<string>}> */
    private array $entries = [];

    private GeographyMatchRanker $ranker;

    public function __construct()
    {
        $this->ranker = new GeographyMatchRanker();
    }

    /**
     * @param  list<string>  $extraParentIds additional valid parents, for the many-to-many tiers
     */
    public function with(GeographyOption $option, string $breadcrumb = '', array $extraParentIds = []): self
    {
        $parentIds = $option->parentId === null
            ? []
            : array_values(array_unique([$option->parentId, ...$extraParentIds]));

        $this->entries[] = [
            'option'     => $option,
            'breadcrumb' => $breadcrumb,
            'parentIds'  => $parentIds,
        ];

        return $this;
    }

    /** {@inheritDoc} */
    public function search(GeographyQuery $query): GeographySearchResult
    {
        if (! $query->isUsable()) {
            return GeographySearchResult::empty();
        }

        $term    = $query->normalizedTerm();
        $matches = [];

        foreach ($this->entries as $entry) {
            /** @var GeographyOption $option */
            $option = $entry['option'];

            if (! $this->wanted($option, $query)) {
                continue;
            }

            // ZIPs are matched on their digits rather than through the name classifier: a ZIP has
            // no name to normalise, and "337" is a prefix of 33701 in a sense the word-boundary
            // rule would never see.
            $type = $option->is(GeographyOption::KIND_ZIP)
                ? $this->classifyZip($option->id, $query)
                : TermMatcher::classify($option->name, $term);

            if ($type === null) {
                continue;
            }

            $matches[] = new GeographyMatch($option, $type, $entry['breadcrumb'], $entry['parentIds']);
        }

        return $this->ranker->rank($matches, $query);
    }

    private function classifyZip(string $zip, GeographyQuery $query): ?MatchType
    {
        if (! $query->looksLikeZip()) {
            return null;
        }

        $digits = $query->searchableTerm();

        if ($zip === $digits) {
            return MatchType::Exact;
        }

        return str_starts_with($zip, $digits) ? MatchType::Prefix : null;
    }

    /** Is this option's tier requested, and does it survive the caller's county scope? */
    private function wanted(GeographyOption $option, GeographyQuery $query): bool
    {
        $tier = match ($option->kind) {
            GeographyOption::KIND_STATE        => GeographyTier::State,
            GeographyOption::KIND_COUNTY       => GeographyTier::Counties,
            GeographyOption::KIND_CITY         => GeographyTier::Cities,
            GeographyOption::KIND_NEIGHBORHOOD => GeographyTier::Neighborhoods,
            GeographyOption::KIND_ZIP          => GeographyTier::ZipCodes,
            default                            => null,
        };

        return $tier !== null && $query->wantsTier($tier);
    }
}
