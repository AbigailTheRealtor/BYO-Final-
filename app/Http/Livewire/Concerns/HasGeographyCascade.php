<?php

namespace App\Http\Livewire\Concerns;

use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Projection\GeographyLabelProjector;
use App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator;
use App\Services\LocationDna\Criteria\Projection\PreservedGeographyLabels;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionResolver;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionValidator;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;

/**
 * Phase 1c — the Livewire surface for the Criteria geography cascade.
 *
 * WHAT IT REPLACES, AND WHAT IT DELIBERATELY DOES NOT
 * ---------------------------------------------------
 * It replaces four free-text inputs — preferred cities, ZIP codes, counties and state — with a
 * corpus-backed cascade over the `us_*` reference tables. It replaces NOTHING else in the shared
 * map widget: polygons, radius searches, Important Places, boundary overlays and the map itself
 * are untouched and still render exactly as before. Conflating those with tier selection would
 * turn a contained increment into a rewrite of the whole editor.
 *
 * IT HOLDS NO RULES OF ITS OWN
 * ----------------------------
 * Every clearing decision comes from {@see GeographySelectionResolver} and every message from
 * {@see GeographySelectionValidator}. That is not ceremony: the resolver's cascade rules include
 * one — a ZIP survives while ANY of its associated counties survives — that is easy to
 * re-implement wrongly and impossible to notice when you have. Re-deriving any of it here would
 * put the rules in two places and let the copies drift.
 *
 * THE WRITE PATH IS UNCHANGED
 * ---------------------------
 * This trait writes nothing. It projects its selection into the same four canonical keys the
 * previous editor produced, in the same label format, and MERGES them into the bridged payload the
 * host already hands to the canonical writer. The writer, its command builder, its capability
 * resolution and its legacy mirror set are all untouched — so a workflow running the cascade
 * stores byte-comparable data to one that is not.
 *
 * HISTORY IS NEVER SILENTLY DISCARDED
 * -----------------------------------
 * A stored label the corpus cannot resolve is carried in {@see $geoPreserved} and projected back
 * out on save. See {@see \App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator}
 * for why that is the load-bearing behaviour of the whole phase.
 *
 * HOST CONTRACT
 * -------------
 *   - declares `$location_dna_preferences_json` (via {@see HasSearchAreas});
 *   - calls {@see self::bootGeographyCascade()} in `mount()`, naming its workflow key;
 *   - calls {@see self::loadGeographyCascade()} with the stored blob when editing;
 *   - calls {@see self::applyGeographyCascadeToPayload()} immediately before persisting.
 *
 * Discrete `$state` / `$counties` / `$cities` props are mirrored when the host declares them, so
 * existing validation rules and the map partial keep seeing the values they always have.
 */
trait HasGeographyCascade
{
    /**
     * How many options one tier will render before the list is truncated.
     *
     * A multi-county selection can enumerate thousands of cities, and a `<select>` with thousands
     * of children is slow to render and useless to read. Truncation is REPORTED rather than
     * silent — {@see self::geoOptionsTruncated()} drives a visible notice — because a list that
     * quietly stops at a limit reads as "this is everything" when it is not.
     */
    private const OPTION_LIMIT = 1000;

    /**
     * Workflows whose host ALREADY writes a legacy `zipCodes` mirror, and therefore wants the
     * cascade's ZIP selection mirrored into the discrete `$zipCodes` property.
     *
     * WHY THIS IS AN OPT-IN RATHER THAN "SYNC IT IF THE PROPERTY EXISTS"
     * ------------------------------------------------------------------
     * The obvious rule — mirror every discrete property the host declares — is wrong here, and
     * wrong in a way that would only show up in stored data.
     *
     * The Buyer family has never written a `zipCodes` mirror; `LegacyMirrorProjection`'s default
     * managed set deliberately excludes it. But `TenantAgentAuction` serves Hire Buyer as well as
     * Hire Tenant from ONE class, it declares `$zipCodes`, and it writes that property to meta
     * unconditionally for every role. So a property-exists rule would make a Hire Buyer listing
     * created through the catch-all start emitting real ZIPs, while the same listing created
     * through the dedicated component emitted none — reintroducing, in stored data, exactly the
     * per-entry-point divergence this work exists to remove. Nothing would fail: the G1f
     * characterisation pins the dedicated component only.
     *
     * So the sync is named per workflow. `hire_tenant` is listed because that workflow already
     * writes the mirror and currently writes `[]` into it on every save (no ZIP control has
     * rendered there since the tag inputs were retired) — mirroring the cascade's selection is the
     * approved repair of that. `hire_buyer` is deliberately absent, so both Buyer entry points
     * keep today's behaviour byte for byte.
     *
     * @var list<string>
     */
    private const ZIP_MIRROR_WORKFLOWS = ['hire_tenant'];

    /** Is the cascade active for this host? Public so it survives a round trip and reaches Blade. */
    public $geoCascadeEnabled = false;

    /**
     * Selected state id, or '' when nothing is chosen.
     *
     * WHOSE id IT IS DEPENDS ON THE BOUND SOURCE, and nothing here may assume: `eloquent` yields a
     * `us_states.id`, `census` a two-digit GEOID. Resolve it only through the repository that
     * issued it — {@see self::selectedStateOption()}.
     */
    public $geoStateId = '';

    /** @var array<int, string> selected county ids */
    public $geoCountyIds = [];

    /** @var array<int, string> selected city ids */
    public $geoCityIds = [];

    /** @var array<int, string> selected ZIP codes — codes, not option ids. See GeographySelection. */
    public $geoZipCodes = [];

    /**
     * Display name and abbreviation of the selected state.
     *
     * Cached as properties rather than looked up during projection: the projector needs the
     * abbreviation to build `Pinellas County, FL`, and re-reading it on every save would be a
     * query per save for a value that only changes when the state does.
     */
    public $geoStateName = '';

    public $geoStateAbbrev = '';

    /**
     * Stored labels the corpus could not resolve, in {@see PreservedGeographyLabels::toArray()}
     * shape. Shown to the user as history, projected back out on save, never dropped.
     *
     * @var array<string, mixed>
     */
    public $geoPreserved = ['state' => null, 'counties' => [], 'cities' => [], 'zip_codes' => []];

    /**
     * What the last resolution cleared, as `['tier' => …, 'id' => …, 'reason' => …]` rows.
     *
     * Clearing a dependent selection is data loss from the user's point of view. A cascade that
     * removes eleven ZIP codes and says nothing looks like a bug to the person it happens to.
     *
     * @var array<int, array<string, string>>
     */
    public $geoCleared = [];

    /** Per-request memo of enumerated options, keyed by tier. Never serialised — see below. */
    protected array $geoOptionCache = [];

    /**
     * Per-request memo of the raw state roster, held apart from {@see $geoOptionCache}.
     *
     * That cache narrows every option to `{id, name}` for the template and drops the rest, so it
     * cannot answer the abbreviation question {@see self::selectedStateOption()} asks. Protected
     * for the same reason it is: Livewire serialises public properties into the request payload
     * and back, and this list has no business making that round trip.
     *
     * @var array<int, GeographyOption>|null
     */
    protected ?array $geoStateRoster = null;

    /**
     * The workflow key this host registers under, e.g. `hire_buyer`.
     *
     * Kept as a property rather than an abstract method so the trait mixes into components that
     * predate it without forcing an edit to their class body.
     */
    public $geoWorkflow = '';

    // ═════════════════════════════════════════════════════════════════════════
    // LIFECYCLE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Decide whether the cascade runs for this host, and under which workflow key.
     *
     * BOTH the master switch and the scope list must agree. A single environment variable cannot
     * widen the rollout by accident, which is what makes enabling one workflow at a time a real
     * guarantee rather than an intention.
     *
     * A NULL WORKFLOW IS A FIRST-CLASS ANSWER, NOT A MISSING ARGUMENT
     * ---------------------------------------------------------------
     * `TenantAgentAuction` serves all four roles from one class, and only two of them have a
     * geography surface at all. Passing null for seller and landlord — rather than inventing keys
     * like `hire_seller` that no scope list would ever contain — means those roles are disabled by
     * the TYPE of the argument rather than by the contents of a config file someone could widen.
     * There is no string an operator can put in `CRITERIA_LDNA_CASCADE_WORKFLOWS` that turns the
     * cascade on for a seller.
     */
    protected function bootGeographyCascade(?string $workflow): void
    {
        $this->geoWorkflow       = (string) $workflow;
        $this->geoCascadeEnabled = $workflow !== null && $this->geographyCascadeIsEnabledFor($workflow);
    }

    public function geographyCascadeIsEnabledFor(?string $workflow): bool
    {
        if ($workflow === null || $workflow === '') {
            return false;
        }

        if (! (bool) config('criteria_location_dna.geography_cascade_enabled', false)) {
            return false;
        }

        $scope = (array) config('criteria_location_dna.geography_cascade_workflows', []);

        return in_array($workflow, array_map('strval', $scope), true);
    }

    /** Does this workflow want its ZIP selection mirrored to the discrete property? See the const. */
    public function geographyMirrorsZipCodes(): bool
    {
        return in_array($this->geoWorkflow, self::ZIP_MIRROR_WORKFLOWS, true);
    }

    /**
     * Hydrate the cascade from a stored blob.
     *
     * The resolver is run over the hydrated selection before it is accepted, so a record whose
     * stored data violates a cascade rule — a city under a county that has since moved state, say
     * — opens in a consistent state rather than in one the validator would immediately reject.
     * Anything the resolver drops is reported through {@see $geoCleared}, not discarded quietly.
     *
     * @param  array<string, mixed>  $blob  decoded `location_dna_preferences`
     */
    protected function loadGeographyCascade(array $blob): void
    {
        if (! $this->geoCascadeEnabled) {
            return;
        }

        $hydrated = (new GeographySelectionHydrator($this->geographyRepository()))->fromLabels($blob);

        $this->geoPreserved = $hydrated->preserved->toArray();

        $this->assignGeographySelection($hydrated->selection);
        $this->refreshGeographyCascade();
    }

    // ── Livewire change hooks ────────────────────────────────────────────────
    //
    // One hook per tier, all funnelling into the same resolution pass. The resolver is a function
    // of (selection, corpus) rather than of (previous, next), so no hook has to know or say what
    // changed — which is why there are four one-line methods here and no transition logic at all.

    public function updatedGeoStateId(): void
    {
        $this->rememberSelectedState();
        $this->refreshGeographyCascade();
    }

    public function updatedGeoCountyIds(): void
    {
        $this->refreshGeographyCascade();
    }

    public function updatedGeoCityIds(): void
    {
        $this->refreshGeographyCascade();
    }

    public function updatedGeoZipCodes(): void
    {
        $this->refreshGeographyCascade();
    }

    /**
     * Re-resolve the selection against the corpus and mirror the result outwards.
     *
     * Idempotent, because the resolver is: running it twice on the same state changes nothing and
     * clears nothing, so calling it defensively is free.
     */
    protected function refreshGeographyCascade(): void
    {
        if (! $this->geoCascadeEnabled) {
            return;
        }

        $this->geoOptionCache = [];
        $this->geoStateRoster = null;

        $resolution = (new GeographySelectionResolver($this->geographyRepository()))
            ->resolve($this->geographySelection());

        $this->geoCleared = array_map(
            static fn ($cleared): array => $cleared->toArray(),
            $resolution->cleared,
        );

        $this->assignGeographySelection($resolution->selection);
        $this->syncDiscreteLocationProps();
    }

    /** Drop the cleared-selection notice once the user has read it. */
    public function dismissGeographyCleared(): void
    {
        $this->geoCleared = [];
    }

    /** Remove one preserved legacy label. Deliberate, per-item, and never automatic. */
    public function removePreservedGeography(string $tier, int $index): void
    {
        if (! isset($this->geoPreserved[$tier]) || ! is_array($this->geoPreserved[$tier])) {
            return;
        }

        unset($this->geoPreserved[$tier][$index]);

        $this->geoPreserved[$tier] = array_values($this->geoPreserved[$tier]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OPTIONS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Options are METHODS, not public properties, and that is a deliberate performance choice.
     *
     * Livewire serialises every public property into the request payload and back. A multi-county
     * selection enumerates thousands of cities, so holding the option lists as properties would
     * ship them to the browser and back on every keystroke. Recomputing per request is cheaper
     * than transporting them, and the per-request memo makes the repeat calls a Blade template
     * naturally makes free.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function geoStateOptions(): array
    {
        return $this->geoOptions('states', fn (): array => $this->geographyRepository()->states());
    }

    /** @return array<int, array{id: string, name: string}> */
    public function geoCountyOptions(): array
    {
        return $this->geoOptions('counties', function (): array {
            return $this->geoStateId === ''
                ? []
                : $this->geographyRepository()->countiesInState((string) $this->geoStateId);
        });
    }

    /** @return array<int, array{id: string, name: string}> */
    public function geoCityOptions(): array
    {
        return $this->geoOptions('cities', function (): array {
            return $this->geoCountyIds === []
                ? []
                : $this->geographyRepository()->citiesInCounties($this->stringList($this->geoCountyIds));
        });
    }

    /**
     * ZIP options, collapsed to one row per code.
     *
     * The repository emits a ZIP once per associated county, because a ZCTA legitimately crosses
     * county lines. That many-to-many is exactly right for the resolver and exactly wrong for a
     * picker, which would otherwise show the same ZIP several times.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function geoZipOptions(): array
    {
        return $this->geoOptions('zips', function (): array {
            if ($this->geoCountyIds === []) {
                return [];
            }

            $seen = [];
            $out  = [];

            foreach ($this->geographyRepository()->zipsInCounties($this->stringList($this->geoCountyIds)) as $option) {
                if (! isset($seen[$option->id])) {
                    $seen[$option->id] = true;
                    $out[]             = $option;
                }
            }

            return $out;
        });
    }

    /** Did this tier's list hit {@see self::OPTION_LIMIT}? Drives the visible truncation notice. */
    public function geoOptionsTruncated(string $tier): bool
    {
        return ($this->geoOptionCache[$tier]['truncated'] ?? false) === true;
    }

    /**
     * @param  callable(): array<int, GeographyOption>  $enumerate
     * @return array<int, array{id: string, name: string}>
     */
    private function geoOptions(string $tier, callable $enumerate): array
    {
        if (isset($this->geoOptionCache[$tier])) {
            return $this->geoOptionCache[$tier]['options'];
        }

        $options = $enumerate();
        $total   = count($options);

        if ($total > self::OPTION_LIMIT) {
            $options = array_slice($options, 0, self::OPTION_LIMIT);
        }

        $this->geoOptionCache[$tier] = [
            'truncated' => $total > self::OPTION_LIMIT,
            'options'   => array_map(
                static fn (GeographyOption $o): array => ['id' => $o->id, 'name' => $o->name],
                array_values($options),
            ),
        ];

        return $this->geoOptionCache[$tier]['options'];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // VALIDATION
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Messages for one tier, so a surface can place a problem beside the control that caused it.
     *
     * @return array<int, string>
     */
    public function geographyViolationsFor(string $tier): array
    {
        $enum = GeographyTier::tryFrom($tier);

        if ($enum === null || ! $this->geoCascadeEnabled) {
            return [];
        }

        return array_values(array_map(
            static fn ($violation): string => $violation->message,
            array_filter(
                $this->geographyValidation()->violationsFor($enum),
                // Completeness is a hint, not an error. Telling a user they have not chosen a
                // county while the county list is still empty is shouting at them for not having
                // done something they have not had the chance to do.
                static fn ($violation): bool => ! $violation->governsCompletenessOnly(),
            ),
        ));
    }

    /**
     * The ONLY predicate a submit gate may use.
     *
     * `isValid()` is deliberately not exposed: a state-only selection is valid, and gating submit
     * on validity would let a half-finished cascade through. The distinction is documented at
     * length on {@see \App\Services\LocationDna\Criteria\Rules\GeographyValidationResult}.
     */
    public function geographyIsComplete(): bool
    {
        if (! $this->geoCascadeEnabled) {
            return true;
        }

        return $this->geographyValidation()->isComplete();
    }

    private function geographyValidation()
    {
        return (new GeographySelectionValidator($this->geographyRepository()))
            ->validate($this->geographySelection());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PROJECTION OUT
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Merge the cascade's geography into the bridged payload, immediately before it is persisted.
     *
     * MERGE, NOT REPLACE. The payload also carries polygons, radius searches, the flexible flag
     * and location notes, all of which belong to the map widget and none of which this trait
     * knows anything about. Rebuilding the payload here would destroy them.
     *
     * The cascade's four keys WIN over whatever the widget serialised for them, because when the
     * cascade is mounted the widget's own geography inputs are not rendered and its values are
     * merely the untouched server seed.
     */
    protected function applyGeographyCascadeToPayload(): void
    {
        if (! $this->geoCascadeEnabled) {
            return;
        }

        $decoded = json_decode((string) ($this->location_dna_preferences_json ?? ''), true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            $decoded = [];
        }

        $this->location_dna_preferences_json = (string) json_encode(
            array_merge($decoded, $this->geographyProjection())
        );
    }

    /**
     * The four canonical geography keys, in the label format stored data has always carried.
     *
     * @return array{state: string, counties: array<int, string>, cities: array<int, string>, zip_codes: array<int, string>}
     */
    public function geographyProjection(): array
    {
        $counties = $this->geoCountyIds === [] || $this->geoStateId === ''
            ? []
            : $this->geographyRepository()->countiesInState((string) $this->geoStateId);

        $cities = $this->geoCityIds === []
            ? []
            : $this->geographyRepository()->citiesInCounties($this->stringList($this->geoCountyIds));

        return (new GeographyLabelProjector())->project(
            $this->geographySelection(),
            $this->geoStateName,
            $this->geoStateAbbrev,
            $counties,
            $cities,
            PreservedGeographyLabels::fromArray($this->geoPreserved),
        );
    }

    /**
     * Mirror the projection into the host's discrete props.
     *
     * These feed existing validation rules and the map partial's prefill, both of which predate
     * the cascade and read names rather than ids. Guarded with `property_exists()` so the trait is
     * safe in a host that omits one.
     *
     * `$zipCodes` needs a SECOND gate on top of that, and the reason is not symmetry with the
     * other three — see {@see self::ZIP_MIRROR_WORKFLOWS}. Declaring the property is not evidence
     * that the workflow wants it written; `TenantAgentAuction` declares it for all four roles and
     * only one of them should mirror it.
     */
    protected function syncDiscreteLocationProps(): void
    {
        $projection = $this->geographyProjection();

        if (property_exists($this, 'state')) {
            $this->state = $projection['state'];
        }
        if (property_exists($this, 'counties')) {
            $this->counties = $projection['counties'];
        }
        if (property_exists($this, 'cities')) {
            $this->cities = $projection['cities'];
        }
        if (property_exists($this, 'zipCodes') && $this->geographyMirrorsZipCodes()) {
            $this->zipCodes = $projection['zip_codes'];
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // INTERNALS
    // ═════════════════════════════════════════════════════════════════════════

    private function geographySelection(): GeographySelection
    {
        return GeographySelection::of(
            $this->geoStateId === '' ? null : (string) $this->geoStateId,
            $this->stringList($this->geoCountyIds),
            $this->stringList($this->geoCityIds),
            $this->stringList($this->geoZipCodes),
        );
    }

    private function assignGeographySelection(GeographySelection $selection): void
    {
        $this->geoStateId   = (string) ($selection->stateId ?? '');
        $this->geoCountyIds = $selection->countyIds;
        $this->geoCityIds   = $selection->cityIds;
        $this->geoZipCodes  = $selection->zipCodes;

        $this->rememberSelectedState();
    }

    /**
     * Cache the selected state's display name and abbreviation; both feed the label format.
     *
     * RESOLVED THROUGH THE REPOSITORY, NOT THROUGH `UsState` (Phase 1d-3)
     * -------------------------------------------------------------------
     * This read used to be `UsState::query()->whereKey($this->geoStateId)`. That was correct only
     * because, under the `eloquent` source, a state option's id IS the `us_states` primary key —
     * an equivalence that held by accident of which repository happened to be bound, and that was
     * never true of any other source.
     *
     * Under `census` the id is a two-digit GEOID. `whereKey('12')` addressed `us_states` row 12,
     * which is a different state entirely, or none at all. The abbreviation is the suffix in the
     * stored `Pinellas County, FL` label, so the failure surfaced as labels silently carrying the
     * wrong state or no state — persisted, and indistinguishable from correct data after the fact.
     *
     * Resolving through the bound repository makes the answer come from whichever source produced
     * the id in the first place, so the two can no longer disagree. `GeographyOption::abbreviation`
     * exists for exactly this: `us_states.abbreviation` and `census_states.usps` both land there.
     */
    private function rememberSelectedState(): void
    {
        if ($this->geoStateId === '') {
            $this->geoStateName   = '';
            $this->geoStateAbbrev = '';

            return;
        }

        $state = $this->selectedStateOption();

        $this->geoStateName   = (string) ($state->name ?? '');
        $this->geoStateAbbrev = (string) ($state->abbreviation ?? '');
    }

    /**
     * The enumerated state option matching {@see $geoStateId}, or null when the corpus has none.
     *
     * A linear scan over the state roster rather than a keyed lookup, because the repository
     * contract enumerates a tier and does not fetch one option by id. The list is the 50 states
     * plus territories — bounded, small, and memoised for the request — so the scan costs less
     * than widening the contract for a single call site would.
     *
     * A miss returns null rather than throwing: a stored state the corpus cannot resolve is a real
     * condition on legacy records, and the phase's rule is that unresolvable history is preserved
     * and reported, never fatal. The caller degrades to empty strings, exactly as the previous
     * implementation did when `first()` found no row.
     */
    private function selectedStateOption(): ?GeographyOption
    {
        if ($this->geoStateRoster === null) {
            $this->geoStateRoster = $this->geographyRepository()->states();
        }

        foreach ($this->geoStateRoster as $option) {
            if ($option->id === (string) $this->geoStateId) {
                return $option;
            }
        }

        return null;
    }

    private function geographyRepository(): CriteriaGeographyRepository
    {
        return app(CriteriaGeographyRepository::class);
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function stringList($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $out = [];

        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $out[] = trim((string) $value);
            }
        }

        return array_values($out);
    }
}
