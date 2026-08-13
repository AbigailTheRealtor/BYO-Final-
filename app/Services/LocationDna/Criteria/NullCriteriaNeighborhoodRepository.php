<?php

namespace App\Services\LocationDna\Criteria;

/**
 * Phase 1d-5 — the neighbourhood tier, absent.
 *
 * WHAT IT IS FOR
 * --------------
 * Two situations produce it, and both are normal rather than exceptional:
 *
 *   1. THE TIER IS SWITCHED OFF. `criteria_location_dna.neighborhood_tier_enabled` ships false, so
 *      this is what every environment resolves until someone turns it on.
 *   2. THE SOURCE CANNOT SUPPORT IT. Under `geography_source = eloquent` a city option's id is a
 *      `us_cities` surrogate key, and the supplemental layer is keyed by seven-digit place GEOIDs.
 *      There is no join between them, so there is no honest answer to give.
 *
 * WHY A CLASS RATHER THAN A NULL CHECK AT THE CALL SITE
 * -----------------------------------------------------
 * The consumer never asks whether the tier is available; it asks what is in it and gets an empty
 * list. That keeps the gate in ONE place — the binding — instead of at every call site, where a
 * missed check would either fatal on a null or, worse, silently skip the tier for a reason nobody
 * wrote down. It also means "off" and "on but nothing there" travel the same code path, so turning
 * the flag on cannot expose a branch that has never run.
 *
 * EMPTY IS THE PRE-TIER BEHAVIOUR, EXACTLY. Before this phase the cascade had four tiers and a
 * stored `Clearwater Beach, FL` was preserved-but-unmatched. With this bound, that is still true —
 * which is what makes the whole slice safe to merge while the flag is off.
 */
final class NullCriteriaNeighborhoodRepository implements CriteriaNeighborhoodRepository
{
    /**
     * {@inheritDoc}
     *
     * Always empty. The argument is deliberately not inspected: there is no input that could make
     * this class find something, and pretending to look would invite a reader to believe otherwise.
     */
    public function neighborhoodsInCities(array $cityIds): array
    {
        return [];
    }
}
