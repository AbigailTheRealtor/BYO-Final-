<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use App\Services\LocationDna\Places\PlaceNameKey;

/**
 * M1 — one geography search request.
 *
 * WHAT THIS IS FOR
 * ----------------
 * {@see GeographySearchRepository} answers "what might the user mean by this text?", where
 * {@see \App\Services\LocationDna\Criteria\CriteriaGeographyRepository} answers "what may I choose
 * inside this parent?". Enumeration walks DOWN a known hierarchy; search jumps INTO it from a
 * string. Those are different questions with different failure modes, which is why search is a
 * separate seam rather than a fifth method on the frozen Phase 1a interface.
 *
 * INTENT DETECTION LIVES HERE, NOT IN THE REPOSITORY, AND THAT IS THE POINT
 * -------------------------------------------------------------------------
 * Deciding that `33756` is a ZIP, that `Pinellas County` names a county, and that a trailing
 * `, FL` is a scope rather than part of the name is pure string reasoning. Putting it in the
 * repository would make it testable only against a database and would let the census and fake
 * implementations drift into disagreeing about what a term means — the two would then return
 * different tiers for the same input and only integration tests would notice.
 *
 * So this object is the single answer to "what did they type?", it needs no database, and every
 * rule in it is unit-testable.
 *
 * NORMALISATION IS BORROWED, NOT REINVENTED. {@see PlaceNameKey::of()} already lowercases,
 * collapses whitespace and folds the saint/st. variants, and `location_places.name_key` is stored
 * in exactly that form. Any second normaliser here would silently stop matching that column.
 */
final class GeographyQuery
{
    /** How many matches a search returns unless the caller says otherwise. */
    public const DEFAULT_LIMIT = 15;

    /**
     * Below this, a term is not worth a query.
     *
     * One character against 32,188 places is not a search, it is a table dump that happens to be
     * sorted. The floor is enforced by {@see self::isUsable()} rather than by clamping the limit,
     * so a caller gets an empty result rather than a truncated one it might render as "no matches".
     */
    public const MIN_TERM_LENGTH = 2;

    /** Digits in a complete ZIP / ZCTA. */
    private const ZIP_WIDTH = 5;

    /** Every tier a search may cover, in cascade order. */
    private const ALL_TIERS = [
        GeographyTier::State,
        GeographyTier::Counties,
        GeographyTier::Cities,
        GeographyTier::Neighborhoods,
        GeographyTier::ZipCodes,
    ];

    /**
     * @param  string                $term      the raw term, trimmed, exactly as typed
     * @param  list<GeographyTier>   $tiers     which tiers to search
     * @param  string|null           $stateId   optional scope: a state option's id
     * @param  list<string>          $countyIds optional scope: county option ids
     * @param  int                   $limit     maximum matches to return
     */
    private function __construct(
        public readonly string $term,
        public readonly array $tiers,
        public readonly ?string $stateId,
        public readonly array $countyIds,
        public readonly int $limit,
    ) {
    }

    /**
     * @param  list<GeographyTier>|null  $tiers      null means every tier
     * @param  list<string>              $countyIds
     */
    public static function for(
        string $term,
        ?array $tiers = null,
        ?string $stateId = null,
        array $countyIds = [],
        int $limit = self::DEFAULT_LIMIT,
    ): self {
        $tiers = $tiers === null || $tiers === [] ? self::ALL_TIERS : array_values($tiers);

        $counties = [];
        foreach ($countyIds as $countyId) {
            $countyId = trim((string) $countyId);
            if ($countyId !== '') {
                $counties[$countyId] = true;
            }
        }

        $stateId = $stateId === null ? null : trim($stateId);

        return new self(
            trim($term),
            $tiers,
            $stateId === '' ? null : $stateId,
            // strval, because a GEOID is a numeric STRING and PHP silently converts a numeric
            // array key to an integer. An int 12103 in this list would never satisfy the ranker's
            // strict comparison against a string parent id, so the county scope would be accepted,
            // stored, and then quietly match nothing.
            array_map('strval', array_keys($counties)),
            max(0, $limit),
        );
    }

    /**
     * Is this worth running?
     *
     * A zero limit counts as unusable rather than as "return nothing successfully": a caller that
     * computed its limit from a config value it misread should get the same empty answer either
     * way, and no query should be issued for a result that cannot hold a row.
     */
    public function isUsable(): bool
    {
        return $this->limit > 0
            && mb_strlen($this->searchableTerm()) >= self::MIN_TERM_LENGTH;
    }

    /**
     * The term with any trailing state suffix removed — what the NAME columns should be matched on.
     *
     * `Clearwater, FL` searches for `Clearwater` scoped to Florida, not for a place literally
     * called "Clearwater, FL". Without this a user who types the label format the cascade itself
     * stores gets no results, which is the worst possible first impression.
     */
    public function searchableTerm(): string
    {
        return trim(PlaceNameKey::stripStateSuffix($this->term));
    }

    /** The searchable term in the same normalised form `location_places.name_key` is stored in. */
    public function normalizedTerm(): string
    {
        return PlaceNameKey::of($this->searchableTerm());
    }

    /**
     * The USPS abbreviation the term ended with, uppercased, or null.
     *
     * A SCOPE HINT, NOT A FILTER, and the distinction matters. The repository resolves it to a
     * state and narrows the search; it does not reject rows when the abbreviation names nothing,
     * because `Springfield, XX` should behave like `Springfield` rather than returning silence.
     */
    public function stateAbbreviationHint(): ?string
    {
        if (preg_match('/,\s*([A-Za-z]{2})\s*$/', $this->term, $m) !== 1) {
            return null;
        }

        return strtoupper($m[1]);
    }

    /**
     * Could this be a ZIP, whole or partially typed?
     *
     * Deliberately true for a PARTIAL ZIP. A typeahead is read after every keystroke, so `337`
     * must already be offering ZIPs — waiting for the fifth digit means the ZIP tier never appears
     * until the user has finished typing and has no reason to look.
     */
    public function looksLikeZip(): bool
    {
        return preg_match('/^\d{1,'.self::ZIP_WIDTH.'}$/', $this->searchableTerm()) === 1;
    }

    /**
     * Does the term name a county outright — "Pinellas County", "Orleans Parish"?
     *
     * Used only to BOOST the county tier during ranking, never to suppress the others. A user who
     * types "Orange County" may still want the city of Orange, and a search that decided otherwise
     * on their behalf would be unexplainable from the UI.
     */
    public function looksLikeCounty(): bool
    {
        return preg_match(
            '/\b(county|parish|borough|census area|municipality|city and borough)\s*$/i',
            $this->searchableTerm()
        ) === 1;
    }

    public function wantsTier(GeographyTier $tier): bool
    {
        return in_array($tier, $this->tiers, true);
    }

    /** Is the search narrowed to specific counties? */
    public function hasCountyScope(): bool
    {
        return $this->countyIds !== [];
    }

    /** @return list<GeographyTier> */
    public static function allTiers(): array
    {
        return self::ALL_TIERS;
    }
}
