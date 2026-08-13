<?php

namespace App\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\CriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\NullCriteriaNeighborhoodRepository;

/**
 * Phase 1b — validates a selection and reports every rule it breaks.
 *
 * WHY THIS EXISTS ALONGSIDE THE RESOLVER
 * --------------------------------------
 * {@see GeographySelectionResolver} produces selections that are valid by construction, so it is
 * fair to ask what is left to validate. The answer is everything the resolver never saw: a form
 * post, a restored draft, a value carried over from the legacy free-text fields. The resolver
 * governs CASCADE TRANSITIONS; the validator governs UNTRUSTED INPUT. Only the second can be handed
 * a selection of unknown provenance.
 *
 * NEVER THROWS (D4)
 * -----------------
 * Every problem is accumulated into {@see GeographyValidationResult}. Two reasons: a surface giving
 * live feedback needs all four tiers' problems at once, and an exception can carry only the first.
 *
 * REFERENTIAL CHECKS SHORT-CIRCUIT AT THE STATE
 * ---------------------------------------------
 * With no usable state there is nothing to enumerate counties from, so every selected county would
 * be reported as "not in state" — a user who has picked twelve counties and not yet a state would
 * be shown thirteen errors describing one problem. When the state is missing or unknown, this
 * reports that alone and stops. It does NOT short-circuit at the county tier: a city selected with
 * no counties really is an orphan, and saying so is correct.
 *
 * READ-ONLY, like everything in this namespace.
 */
final class GeographySelectionValidator
{
    /** Canonical ZIP form: exactly five digits. Phase 1a pads on the way out of the repository. */
    private const ZIP_PATTERN = '/^\d{5}$/';

    /**
     * Phase 1d-5 — `$neighborhoods` is OPTIONAL, defaulting to the null object, for the same reason
     * it is on {@see GeographySelectionResolver}: every existing call site keeps working and keeps
     * reporting exactly what it reported before, and the tier stays inert until a surface asks for
     * the repository.
     *
     * One consequence worth stating, because it looks like a bug and is not: with the null object
     * bound, a selection that somehow carries neighbourhood ids reports EVERY one of them as an
     * orphan. That is correct — nothing justifies them, which is precisely what the rule says — and
     * it is unreachable in practice, because no surface can produce such a selection while the tier
     * is off.
     */
    public function __construct(
        private readonly CriteriaGeographyRepository $geography,
        private readonly CriteriaNeighborhoodRepository $neighborhoods = new NullCriteriaNeighborhoodRepository(),
    ) {
    }

    public function validate(GeographySelection $selection): GeographyValidationResult
    {
        $violations = [];

        $this->checkStructure($selection, $violations);
        $this->checkReferences($selection, $violations);

        return GeographyValidationResult::of($violations);
    }

    /**
     * S1–S5. No corpus needed, so these run first and always run.
     *
     * @param  list<GeographyViolation>  $violations  accumulator, by reference
     */
    private function checkStructure(GeographySelection $selection, array &$violations): void
    {
        // S1 — a state is required for COMPLETENESS (not for validity).
        if (! $selection->hasState()) {
            $violations[] = GeographyViolation::of(GeographyRule::StateRequired);
        }

        // S2 — at least one county, likewise completeness-only.
        if ($selection->countyIds === []) {
            $violations[] = GeographyViolation::of(GeographyRule::CountyRequired);
        }

        foreach ([GeographyTier::Counties, GeographyTier::Cities, GeographyTier::Neighborhoods, GeographyTier::ZipCodes] as $tier) {
            $seen = [];

            foreach ($selection->idsFor($tier) as $id) {
                // S4 — an empty id is unusable.
                if ($id === '') {
                    $violations[] = GeographyViolation::of(GeographyRule::MalformedId, $id, $tier);
                    continue;
                }

                // S3 — the same id twice in one tier.
                if (isset($seen[$id])) {
                    $violations[] = GeographyViolation::of(GeographyRule::DuplicateSelection, $id, $tier);
                    continue;
                }

                $seen[$id] = true;

                // S5 — canonical five-digit ZIP form.
                //
                // Reported, never silently repaired. 3,000 of the 34,741 reference rows are stored
                // unpadded ("501" for 00501), so an unpadded value in a SELECTION means it came
                // from somewhere that bypassed the repository — which is a caller bug worth
                // surfacing, not a formatting nit worth hiding.
                if ($tier === GeographyTier::ZipCodes && preg_match(self::ZIP_PATTERN, $id) !== 1) {
                    $violations[] = GeographyViolation::of(GeographyRule::MalformedZip, $id, $tier);
                }
            }
        }
    }

    /**
     * R1–R4. Corpus-backed, and skipped below an unusable state.
     *
     * @param  list<GeographyViolation>  $violations  accumulator, by reference
     */
    private function checkReferences(GeographySelection $selection, array &$violations): void
    {
        if (! $selection->hasState()) {
            return;
        }

        $stateId = (string) $selection->stateId;

        // R1 — the state exists.
        $knownStates = $this->idsOf($this->geography->states(), GeographyOption::KIND_STATE);

        if (! isset($knownStates[$stateId])) {
            $violations[] = GeographyViolation::of(GeographyRule::StateUnknown, $stateId);

            return;
        }

        // R2 — every county belongs to that state.
        $allowedCounties = $this->idsOf(
            $this->geography->countiesInState($stateId),
            GeographyOption::KIND_COUNTY
        );

        $justifiedCounties = [];

        foreach ($this->uniqueNonEmpty($selection->countyIds) as $countyId) {
            if (isset($allowedCounties[$countyId])) {
                $justifiedCounties[] = $countyId;
                continue;
            }

            $violations[] = GeographyViolation::of(GeographyRule::CountyNotInState, $countyId);
        }

        // R3 — every city is CONTAINED by a justified county (single FK parent).
        $allowedCities = $justifiedCounties === []
            ? []
            : $this->idsOf(
                $this->geography->citiesInCounties($justifiedCounties),
                GeographyOption::KIND_CITY
            );

        $justifiedCities = [];

        foreach ($this->uniqueNonEmpty($selection->cityIds) as $cityId) {
            if (isset($allowedCities[$cityId])) {
                $justifiedCities[] = $cityId;
                continue;
            }

            $violations[] = GeographyViolation::of(GeographyRule::CityNotInSelectedCounty, $cityId);
        }

        // R5 — every neighbourhood is CONTAINED by a JUSTIFIED city.
        //
        // Justified, not merely selected: a neighbourhood under a city that is itself an orphan has
        // no standing either, and hanging it off the raw selection would let one bad county keep a
        // whole branch alive. The chain is the same one the resolver walks — state, county, city,
        // neighbourhood — so the two agree by construction rather than by coincidence.
        //
        // Reported ONCE. A user who picked a city in the wrong county sees that city named; they do
        // not additionally see every neighbourhood beneath it listed as broken, which would be
        // several messages describing one mistake. The same restraint the ZIP check shows below.
        $allowedNeighborhoods = $justifiedCities === []
            ? []
            : $this->idsOf(
                $this->neighborhoods->neighborhoodsInCities($justifiedCities),
                GeographyOption::KIND_NEIGHBORHOOD
            );

        foreach ($this->uniqueNonEmpty($selection->neighborhoodIds) as $neighborhoodId) {
            if (! isset($allowedNeighborhoods[$neighborhoodId])) {
                $violations[] = GeographyViolation::of(
                    GeographyRule::NeighborhoodNotInSelectedCity,
                    $neighborhoodId
                );
            }
        }

        // R4 — every ZIP is ASSOCIATED with at least one justified county.
        //
        // Matched on the ZIP code alone, ignoring which county produced each row. Requiring a
        // particular parent would be containment, which the reference data cannot support: it
        // associates ZIPs to counties by name, and a ZCTA crosses county lines. See
        // GeographySelectionResolver for the full argument.
        $allowedZips = $justifiedCounties === []
            ? []
            : $this->idsOf(
                $this->geography->zipsInCounties($justifiedCounties),
                GeographyOption::KIND_ZIP
            );

        foreach ($this->uniqueNonEmpty($selection->zipCodes) as $zip) {
            // A malformed ZIP is necessarily also unjustified — nothing in the corpus can match
            // "501" or "ABCDE". Reporting both would show the user two messages for one mistake,
            // the same reason duplicates and blanks are excluded above. The structural pass has
            // already named it, and its message is the actionable one.
            if (preg_match(self::ZIP_PATTERN, $zip) !== 1) {
                continue;
            }

            if (! isset($allowedZips[$zip])) {
                $violations[] = GeographyViolation::of(GeographyRule::ZipNotInSelectedCounties, $zip);
            }
        }
    }

    /**
     * @param  list<GeographyOption>  $options
     * @return array<string, true>
     */
    private function idsOf(array $options, string $kind): array
    {
        $ids = [];

        foreach ($options as $option) {
            if ($option->is($kind)) {
                $ids[$option->id] = true;
            }
        }

        return $ids;
    }

    /**
     * Duplicates and blanks are already reported by the structural pass; re-reporting them here as
     * referential failures would double up on one mistake.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function uniqueNonEmpty(array $ids): array
    {
        $out  = [];
        $seen = [];

        foreach ($ids as $id) {
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $out[]     = $id;
        }

        return $out;
    }
}
