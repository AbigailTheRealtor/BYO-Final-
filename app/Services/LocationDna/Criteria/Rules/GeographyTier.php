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
 * than a translation. `Neighborhoods` is the one exception, and it is documented on the case.
 */
enum GeographyTier: string
{
    case State    = 'state';
    case Counties = 'counties';
    case Cities   = 'cities';
    case ZipCodes = 'zip_codes';

    /**
     * Phase 1d-5 — neighbourhoods and communities: the tier below a city.
     *
     * ⚠ THIS BACKING VALUE IS NOT A STORAGE KEY, AND THAT IS THE ONE THING TO KNOW ABOUT IT.
     *
     * The other four cases are named after the canonical geography keys a stored blob carries, so
     * reading `GeographyTier::Cities->value` as "the key cities live under" is correct for them and
     * is exactly the inference a reader will make here. It is wrong. There is no `neighborhoods`
     * key in the stored document and there must not be one: a selected neighbourhood is projected
     * into the EXISTING `cities` array, in the same `{name}, {ST}` label format as everything else.
     *
     * That was a deliberate decision rather than an omission. Six consumers already read the
     * `cities` key — `LocationMatchAuctionExtractor`, three Stellar criteria loaders,
     * `BoundaryLookupService` and `CriteriaListingResolver` — and none of them would read a fifth
     * key. Introducing one would make every selected neighbourhood invisible to matching, with no
     * error anywhere and no symptom until match quality was noticed to have dropped. Storing the
     * label alongside cities also means historic records need no migration at all: a stored
     * `Clearwater Beach, FL` is ALREADY in the `cities` array, preserved-but-unmatched, and this
     * tier simply lets the corpus recognise it.
     *
     * So this value is a TIER IDENTIFIER — it keys UI state, violations and preserved-label
     * buckets. It is not, and must not become, a place to write data.
     */
    case Neighborhoods = 'neighborhoods';

    /**
     * Is a selection in this tier required for a COMPLETE selection?
     *
     * State and counties are required; cities, neighbourhoods and ZIPs are optional refinements.
     * Note that "required" governs completeness only — an incomplete selection is not an invalid
     * one. See {@see GeographyValidationResult}.
     */
    public function isRequired(): bool
    {
        return $this === self::State || $this === self::Counties;
    }

    /** The {@see GeographyOption} kind that populates this tier. */
    public function optionKind(): string
    {
        return match ($this) {
            self::State         => GeographyOption::KIND_STATE,
            self::Counties      => GeographyOption::KIND_COUNTY,
            self::Cities        => GeographyOption::KIND_CITY,
            self::Neighborhoods => GeographyOption::KIND_NEIGHBORHOOD,
            self::ZipCodes      => GeographyOption::KIND_ZIP,
        };
    }
}
