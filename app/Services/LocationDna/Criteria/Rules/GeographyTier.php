<?php

namespace App\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\GeographyOption;

/**
 * Phase 1b — the four tiers of the Criteria geography cascade.
 *
 * WHY THIS DUPLICATES A VOCABULARY THAT ALREADY EXISTS (D2)
 * ---------------------------------------------------------
 * The canonical contract layer already enumerates these four names. Reusing that enum here would
 * mean this namespace referenced the contract layer — and the Phase 1a inertness guard bans exactly
 * that, because reaching the contract layer is the first step by which a read layer becomes a write
 * layer. The guard is worth more than four deduplicated string literals, so this enum is
 * deliberately separate and the duplication is accepted on the record.
 *
 * Sharing the vocabulary later is a deliberate loosening of a Phase 1a boundary and must be its own
 * decision, not a side effect of a refactor. `Phase1bCriteriaRulesInertnessGuardTest` pins that.
 *
 * The backing values match the canonical dimension keys so that a future mapping is a lookup rather
 * than a translation.
 */
enum GeographyTier: string
{
    case State    = 'state';
    case Counties = 'counties';
    case Cities   = 'cities';
    case ZipCodes = 'zip_codes';

    /**
     * Is a selection in this tier required for a COMPLETE selection?
     *
     * State and counties are required; cities and ZIPs are optional refinements. Note that
     * "required" governs completeness only — an incomplete selection is not an invalid one.
     * See {@see GeographyValidationResult}.
     */
    public function isRequired(): bool
    {
        return $this === self::State || $this === self::Counties;
    }

    /** The {@see GeographyOption} kind that populates this tier. */
    public function optionKind(): string
    {
        return match ($this) {
            self::State    => GeographyOption::KIND_STATE,
            self::Counties => GeographyOption::KIND_COUNTY,
            self::Cities   => GeographyOption::KIND_CITY,
            self::ZipCodes => GeographyOption::KIND_ZIP,
        };
    }
}
