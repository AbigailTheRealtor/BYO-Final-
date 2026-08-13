<?php

namespace App\Services\LocationDna\Criteria\Rules;

/**
 * Phase 1b — the rule vocabulary shared by validation and resolution.
 *
 * ONE VOCABULARY, TWO CONSUMERS
 * -----------------------------
 * {@see GeographySelectionValidator} reports a rule when a selection BREAKS it.
 * {@see GeographySelectionResolver} reports the same rule when it CLEARS a selection to avoid
 * breaking it. That is deliberate: the resolver clears exactly what the validator would flag, which
 * gives the layer a single load-bearing invariant —
 *
 *     a resolved selection carries no referential violation.
 *
 * If the two ever used different vocabularies that invariant could not be stated, let alone tested.
 *
 * COMPLETENESS VS VALIDITY
 * ------------------------
 * {@see self::governsCompletenessOnly()} splits the rules in two, and that split is what lets the
 * cascade give live feedback without shouting at a user who is halfway through it. "You have not
 * chosen a county yet" must not render as an error while the county list is still loading; "that
 * county is in another state" must. See {@see GeographyValidationResult}.
 */
enum GeographyRule: string
{
    /** No state chosen. Completeness only — an empty selection is not an illegal one. */
    case StateRequired = 'state_required';

    /** A state was chosen that the corpus does not contain. */
    case StateUnknown = 'state_unknown';

    /** No county chosen. Completeness only. */
    case CountyRequired = 'county_required';

    /** A chosen county does not belong to the chosen state. */
    case CountyNotInState = 'county_not_in_state';

    /** A chosen city does not belong to any chosen county. Cities have exactly one county. */
    case CityNotInSelectedCounty = 'city_not_in_selected_county';

    /**
     * Phase 1d-5 — a chosen neighbourhood does not belong to any chosen CITY.
     *
     * CONTAINMENT, like cities and unlike ZIPs. A neighbourhood sits inside exactly one
     * municipality — `location_places.parent_place_id` is a single foreign key — so deselecting
     * that city orphans it unconditionally. There is no "survives while any parent survives"
     * subtlety here; that rule belongs to ZIPs alone, and applying it to this tier would keep a
     * neighbourhood alive under a city the user has removed.
     *
     * Note the parent is the CITY, not the county. A neighbourhood whose county is still selected
     * but whose city is not is orphaned, because the tier above it is gone.
     */
    case NeighborhoodNotInSelectedCity = 'neighborhood_not_in_selected_city';

    /**
     * A chosen ZIP is associated with none of the chosen counties.
     *
     * ASSOCIATION, NOT CONTAINMENT. A ZIP is satisfied by ANY chosen county it is associated with,
     * because the reference data associates ZIPs to counties by name rather than by geometry and a
     * ZCTA legitimately crosses county lines. See {@see GeographySelectionResolver}.
     */
    case ZipNotInSelectedCounties = 'zip_not_in_selected_counties';

    /** The same id appears twice in one tier. */
    case DuplicateSelection = 'duplicate_selection';

    /** A ZIP is not in canonical five-digit form. */
    case MalformedZip = 'malformed_zip';

    /** An id is empty or otherwise unusable. */
    case MalformedId = 'malformed_id';

    /**
     * The tier a violation of this rule belongs to, so a surface can place it next to the right
     * control.
     *
     * NULL for the two rules that are not tier-specific: a duplicate or a malformed id can occur in
     * any tier, so the reporter must say which one it found the problem in.
     * {@see GeographyViolation} enforces that.
     */
    public function defaultTier(): ?GeographyTier
    {
        return match ($this) {
            self::StateRequired,
            self::StateUnknown => GeographyTier::State,

            self::CountyRequired,
            self::CountyNotInState => GeographyTier::Counties,

            self::CityNotInSelectedCounty => GeographyTier::Cities,

            self::NeighborhoodNotInSelectedCity => GeographyTier::Neighborhoods,

            self::ZipNotInSelectedCounties,
            self::MalformedZip => GeographyTier::ZipCodes,

            self::DuplicateSelection,
            self::MalformedId => null,
        };
    }

    /**
     * Does breaking this rule leave the selection INCOMPLETE rather than INVALID?
     *
     * Only the two "required" rules. Everything else describes a selection that is actively wrong —
     * an unknown id, an orphan, a duplicate, a malformed value — and no amount of further picking
     * will repair it.
     */
    public function governsCompletenessOnly(): bool
    {
        return $this === self::StateRequired || $this === self::CountyRequired;
    }

    /** A short, surface-agnostic explanation. Presentation is Phase 1c's problem, not this layer's. */
    public function describe(): string
    {
        return match ($this) {
            self::StateRequired             => 'A state must be selected.',
            self::StateUnknown              => 'The selected state is not a known state.',
            self::CountyRequired            => 'At least one county must be selected.',
            self::CountyNotInState          => 'The selected county does not belong to the selected state.',
            self::CityNotInSelectedCounty   => 'The selected city does not belong to any selected county.',
            self::NeighborhoodNotInSelectedCity => 'The selected neighborhood does not belong to any selected city.',
            self::ZipNotInSelectedCounties  => 'The selected ZIP code is not associated with any selected county.',
            self::DuplicateSelection        => 'The same selection appears more than once.',
            self::MalformedZip              => 'A ZIP code must be five digits.',
            self::MalformedId               => 'A selection must carry a non-empty identifier.',
        };
    }
}
