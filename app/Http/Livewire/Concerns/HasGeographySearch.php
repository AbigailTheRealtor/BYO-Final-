<?php

namespace App\Http\Livewire\Concerns;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use App\Services\LocationDna\Criteria\Search\GeographyMatch;
use App\Services\LocationDna\Criteria\Search\GeographyQuery;
use App\Services\LocationDna\Criteria\Search\GeographySearchRepository;

/**
 * M2 — a search box that SEEDS the geography cascade.
 *
 * WHAT IT IS, AND WHAT IT DELIBERATELY IS NOT
 * -------------------------------------------
 * It is a shortcut into {@see HasGeographyCascade}, not an alternative to it. A user types
 * "Clearwater", picks the result, and the cascade ends up in exactly the state it would have
 * reached had they chosen Florida, then Pinellas County, then Clearwater from the three selects.
 * Nothing here writes storage, computes a label, or decides what a selection means — the cascade's
 * resolver, projector and hydrator remain the only authority, which is what makes the stored
 * payload identical either way. `GeographySearchSelectionParityTest` pins that equivalence.
 *
 * IT DOES NOT MODIFY THE CASCADE TRAIT, AND THAT CONSTRAINT SHAPED THE DESIGN
 * ---------------------------------------------------------------------------
 * `HasGeographyCascade` is byte-unchanged. Two of the things back-fill needs are private to it —
 * `rememberSelectedState()`, which caches the state name and abbreviation that become the `, FL`
 * suffix of every stored label, and `geographyRepository()`. So back-fill does not reach past the
 * trait's surface: it assigns the public selection properties and then calls the trait's own PUBLIC
 * update hooks, `updatedGeoStateId()` and friends, exactly as a user's dropdown change would.
 *
 * That is not a workaround. Driving the same entry points the UI drives is precisely why a searched
 * selection cannot diverge from a clicked one — there is no second path through the cascade to keep
 * in step.
 *
 * ORDER IS LOAD-BEARING
 * ---------------------
 * State, then counties, then cities. Each tier is enumerated from the one above, so a city assigned
 * before its county is not a city the resolver can see — it clears it on the very next pass, and
 * the user watches their selection disappear a moment after making it.
 *
 * SEARCH IS ADDITIVE WITHIN ITS TIER. Picking Clearwater adds Pinellas to whatever counties are
 * already chosen rather than replacing them, because a Criteria selection is a set of places a
 * buyer will consider and the user is building it up.
 */
trait HasGeographySearch
{
    /** How many matches one search returns. Small: this is a dropdown, not a report. */
    private const SEARCH_LIMIT = 10;

    /**
     * Digits in a county GEOID, whose first two ARE the state's.
     *
     * That is the corpus's own rule rather than an inference — `census_counties.state_geoid` is
     * populated as `substr(geoid, 0, 2)` by the table's migration, and the same FIPS structure is
     * what makes a county id five digits in the first place. It is used to answer "which state does
     * this city belong to?", which a city match cannot otherwise say: its parents are counties.
     */
    private const COUNTY_GEOID_WIDTH = 5;

    /** The raw term, bound to the input. */
    public $geoSearchTerm = '';

    /**
     * The current matches, as arrays.
     *
     * ARRAYS RATHER THAN DTOs BECAUSE LIVEWIRE SERIALISES THIS. A public property carrying value
     * objects would have to survive a JSON round trip on every keystroke; the array form is what
     * the Blade partial reads anyway.
     *
     * @var array<int, array<string, mixed>>
     */
    public $geoSearchResults = [];

    /** Did the last search have more matches than it returned? Drives the visible notice. */
    public $geoSearchTruncated = false;

    /** Has a search been run whose term was long enough to query? Distinguishes "no hits" from "not yet". */
    public $geoSearchPerformed = false;

    /**
     * Is the search surface active for this host? Public so it survives a round trip and reaches
     * Blade — the same reason {@see HasGeographyCascade::$geoCascadeEnabled} is public.
     *
     * IT MATTERS THAT THIS IS A PROPERTY RATHER THAN A METHOD CALL IN THE VIEW. The Buyer
     * property-preferences tab is rendered by TWO components — the dedicated `BuyerAgentAuction`
     * and the catch-all `TenantAgentAuction` serving `user_type = buyer` — and only the former
     * carries this trait. A view calling `$this->geographySearchIsEnabled()` would fatal on the
     * latter; a null-coalesced property renders false and changes nothing.
     */
    public $geoSearchEnabled = false;

    /**
     * The state a search selection just moved the user to, or '' when it did not move them.
     *
     * WHY THE CROSS-STATE CASE IS NOT COVERED BY THE SUPPRESSION BELOW
     * ----------------------------------------------------------------
     * Back-fill legitimately reshapes the tiers above a chosen place, and reporting that churn as
     * "selections were cleared" describes the user's own action back to them as data loss. That
     * reasoning holds while they stay inside one state.
     *
     * It stops holding the moment the state changes. Someone with Florida and three counties
     * selected who picks "Springfield, IL" has just lost the whole Florida context — deliberately,
     * but it is the single largest silent change search can make, and a suppressed warning there
     * would mean the user is told nothing at all about it.
     *
     * So the generic cleared-selection notice is still suppressed — it would enumerate every
     * dropped county as though something had gone wrong — and this replaces it with one specific,
     * accurate statement: the location moved, and here is where to.
     *
     * IT CARRIES THE STATE NAME, NOT A SENTENCE. Presentation belongs in the view; a property
     * holding prose would have to be re-read by every surface that wanted to phrase it differently.
     */
    public $geoStateChangedTo = '';

    // ═════════════════════════════════════════════════════════════════════════
    // GATE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Decide whether search runs for this host. Call from `mount()`, AFTER `bootGeographyCascade()`.
     *
     * The order is required, not stylistic: the gate reads `$geoCascadeEnabled`, which the cascade's
     * own boot sets. Called before it, search would resolve false for every host on first render and
     * true on every request after — a bug that only appears on the second keystroke.
     */
    protected function bootGeographySearch(): void
    {
        $this->geoSearchEnabled = $this->geographySearchIsEnabled();
    }

    /**
     * Search renders only where the cascade already does, and only behind its own flag.
     *
     * TWO CONDITIONS, AND THE FIRST IS NOT REDUNDANT. Search seeds the cascade's tiers; without
     * them there is nothing to seed, and a search box above the legacy free-text inputs would
     * populate selections no surface renders. So a workflow reaches search only after it reaches
     * the cascade — which keeps Seller and Landlord structurally excluded here for exactly the
     * reason they are excluded there, with no second exclusion to maintain.
     */
    public function geographySearchIsEnabled(): bool
    {
        return $this->geoCascadeEnabled
            && (bool) config('criteria_location_dna.geography_search_enabled', false);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SEARCH
    // ═════════════════════════════════════════════════════════════════════════

    /** Livewire hook: the user typed. */
    public function updatedGeoSearchTerm(): void
    {
        $this->runGeographySearch();
    }

    public function clearGeographySearch(): void
    {
        $this->geoSearchTerm      = '';
        $this->geoSearchResults   = [];
        $this->geoSearchTruncated = false;
        $this->geoSearchPerformed = false;
    }

    /**
     * Run the current term, or clear the list when it is too short to be worth a query.
     *
     * The floor is the repository's, not a second one invented here — {@see GeographyQuery} decides
     * what is usable, and asking it keeps a single answer to "is this worth searching?".
     */
    protected function runGeographySearch(): void
    {
        if (! $this->geographySearchIsEnabled()) {
            $this->clearGeographySearch();

            return;
        }

        $query = $this->geographySearchQuery();

        if (! $query->isUsable()) {
            $this->geoSearchResults   = [];
            $this->geoSearchTruncated = false;
            $this->geoSearchPerformed = false;

            return;
        }

        $result = $this->geographySearchRepository()->search($query);

        $this->geoSearchResults = array_map(
            fn (GeographyMatch $match): array => [
                'kind'       => $match->option->kind,
                'id'         => $match->option->id,
                'label'      => $match->label(),
                'breadcrumb' => $match->breadcrumb,
                'parent_ids' => $match->parentIds,
            ],
            $result->matches,
        );

        $this->geoSearchTruncated = $result->truncated;
        $this->geoSearchPerformed = true;
    }

    /**
     * The query for the current term.
     *
     * SCOPED TO THE SELECTED STATE WHEN THERE IS ONE. A user who has already chosen Florida and
     * types "Springfield" means the Florida one; offering the other thirty-three would bury it.
     *
     * UNLESS THE TERM NAMES A STATE ITSELF, AND THIS EXCEPTION IS NOT OPTIONAL. The repository
     * resolves an explicit scope ahead of a typed `, IL` suffix — correctly, because a caller's
     * established scope outranks an inference from text. But that makes it the CALLER's job not to
     * assert a scope the user has just contradicted. Without this, someone with Florida selected who
     * types "Springfield, IL" gets an empty list and no explanation: the suffix they typed is
     * overridden by a selection they are plainly trying to move away from.
     *
     * Deciding it here keeps the repository's rule intact and puts the judgement where the user's
     * intent is actually known.
     *
     * NEIGHBOURHOODS ARE NOT REQUESTED. The tier ships off, and the repository would return nothing
     * anyway, but naming the four tiers explicitly says so at the call site rather than relying on a
     * flag two layers down.
     */
    protected function geographySearchQuery(): GeographyQuery
    {
        $term = (string) $this->geoSearchTerm;

        $termNamesItsOwnState = GeographyQuery::for($term)->stateAbbreviationHint() !== null;

        $scope = ($termNamesItsOwnState || $this->geoStateId === '')
            ? null
            : (string) $this->geoStateId;

        return GeographyQuery::for(
            $term,
            [GeographyTier::State, GeographyTier::Counties, GeographyTier::Cities, GeographyTier::ZipCodes],
            $scope,
            [],
            self::SEARCH_LIMIT,
        );
    }

    private function geographySearchRepository(): GeographySearchRepository
    {
        return app(GeographySearchRepository::class);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SELECTION → BACK-FILL
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Apply a match to the cascade.
     *
     * THE CLEARED-SELECTION NOTICE IS SUPPRESSED FOR THIS PATH ONLY, AND ONLY FOR THIS PATH.
     * `refreshGeographyCascade()` records what the resolver dropped so the user is told when their
     * stored geography narrows underneath them. Back-fill trips it for a different reason: choosing
     * a city legitimately reshapes the tiers above it, and reporting that as "selections were
     * cleared" describes the user's own deliberate action back to them as data loss. So the notice
     * is snapshotted before the back-fill and restored after — any warning that was already on
     * screen survives, and only the churn this method caused is discarded.
     *
     * A manual dropdown change is untouched by this and still reports normally. The suppression
     * lives here, in the caller, precisely so the trait's behaviour does not change.
     */
    public function selectGeographyMatch(string $kind, string $id): void
    {
        if (! $this->geographySearchIsEnabled()) {
            return;
        }

        $match = $this->findGeographySearchResult($kind, $id);

        if ($match === null) {
            return;
        }

        $noticeBeforeBackfill = $this->geoCleared;
        $stateBeforeBackfill  = (string) $this->geoStateId;

        // Reset first, so a selection that does NOT move the user cannot leave a stale notice from
        // one that did.
        $this->geoStateChangedTo = '';

        $this->applyGeographyMatch($match);

        $this->geoCleared = $noticeBeforeBackfill;

        // Only a MOVE counts. Choosing the first state of an empty selection is not a change of
        // context — there was no context to lose — and announcing it would make the notice
        // meaningless by firing on the common case.
        if ($stateBeforeBackfill !== '' && (string) $this->geoStateId !== $stateBeforeBackfill) {
            $this->geoStateChangedTo = (string) $this->geoStateName;
        }

        $this->clearGeographySearch();
    }

    /** Drop the location-change notice once the user has read it. */
    public function dismissGeographyStateChange(): void
    {
        $this->geoStateChangedTo = '';
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function applyGeographyMatch(array $match): void
    {
        $kind      = (string) $match['kind'];
        $id        = (string) $match['id'];
        $parentIds = array_map('strval', (array) ($match['parent_ids'] ?? []));

        if ($kind === GeographyOption::KIND_STATE) {
            $this->assignSearchedState($id);

            return;
        }

        if ($kind === GeographyOption::KIND_COUNTY) {
            // A county names its state directly, so nothing has to be derived.
            $this->assignSearchedState($parentIds[0] ?? $this->stateOfCounty($id));
            $this->addSearchedCounty($id);

            return;
        }

        // Cities and ZIPs both hang off counties, so both take the canonical parent and climb from
        // there. `parentIds[0]` is canonical by construction — `is_primary` for a place, lowest
        // GEOID for a ZCTA — so this is a lookup, not a choice.
        $county = $parentIds[0] ?? '';

        if ($county === '') {
            return;
        }

        $this->assignSearchedState($this->stateOfCounty($county));
        $this->addSearchedCounty($county);

        if ($kind === GeographyOption::KIND_CITY) {
            $this->addSearchedCity($id);

            return;
        }

        if ($kind === GeographyOption::KIND_ZIP) {
            $this->addSearchedZip($id);
        }
    }

    /**
     * Set the state, through the trait's own hook.
     *
     * `updatedGeoStateId()` is what caches the state's name and abbreviation — the `, FL` suffix on
     * every stored label. Assigning the property alone would leave that cache stale and the payload
     * would silently differ from a manually chosen one, which is the exact divergence the parity
     * test exists to catch.
     *
     * A no-op when the state is already selected, so re-selecting inside one state does not churn
     * the tiers below.
     */
    private function assignSearchedState(string $stateId): void
    {
        if ($stateId === '' || (string) $this->geoStateId === $stateId) {
            return;
        }

        $this->geoStateId = $stateId;
        $this->updatedGeoStateId();
    }

    private function addSearchedCounty(string $countyId): void
    {
        $counties = array_map('strval', (array) $this->geoCountyIds);

        if (in_array($countyId, $counties, true)) {
            return;
        }

        $counties[]           = $countyId;
        $this->geoCountyIds   = array_values($counties);
        $this->updatedGeoCountyIds();
    }

    private function addSearchedCity(string $cityId): void
    {
        $cities = array_map('strval', (array) $this->geoCityIds);

        if (in_array($cityId, $cities, true)) {
            return;
        }

        $cities[]         = $cityId;
        $this->geoCityIds = array_values($cities);
        $this->updatedGeoCityIds();
    }

    private function addSearchedZip(string $zip): void
    {
        $zips = array_map('strval', (array) $this->geoZipCodes);

        if (in_array($zip, $zips, true)) {
            return;
        }

        $zips[]             = $zip;
        $this->geoZipCodes  = array_values($zips);
        $this->updatedGeoZipCodes();
    }

    /**
     * The state a county belongs to — its GEOID's first two digits.
     *
     * See {@see self::COUNTY_GEOID_WIDTH}. Returns empty for anything that is not a well-formed
     * county GEOID rather than slicing a shorter string, so a malformed id back-fills nothing
     * instead of selecting an arbitrary state.
     */
    private function stateOfCounty(string $countyId): string
    {
        $countyId = trim($countyId);

        return preg_match('/^\d{'.self::COUNTY_GEOID_WIDTH.'}$/', $countyId) === 1
            ? substr($countyId, 0, 2)
            : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findGeographySearchResult(string $kind, string $id): ?array
    {
        foreach ($this->geoSearchResults as $result) {
            if ((string) $result['kind'] === $kind && (string) $result['id'] === $id) {
                return $result;
            }
        }

        // Not found is normal rather than exceptional: a stale click after the list has moved on.
        // Returning null makes it a no-op instead of a back-fill of something the user cannot see.
        return null;
    }
}
