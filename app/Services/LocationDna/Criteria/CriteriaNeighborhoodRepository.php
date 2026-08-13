<?php

namespace App\Services\LocationDna\Criteria;

/**
 * Phase 1d-5 — the read-only enumeration seam for the tier BELOW cities.
 *
 * WHY THIS IS NOT A FIFTH METHOD ON {@see CriteriaGeographyRepository}
 * --------------------------------------------------------------------
 * That interface has three implementations, and only one of them could answer this question.
 * `EloquentCriteriaGeographyRepository` reads the `us_*` reference tables, which contain no
 * neighbourhood data of any kind and never will — the whole reason the supplemental
 * `location_places` layer exists is that neither the legacy tables nor the Census corpus publishes
 * Clearwater Beach. Adding a method there would force a permanently-empty implementation onto
 * Eloquent and Fake, and a method that returns `[]` because it CANNOT answer is indistinguishable,
 * at the call site, from one that returns `[]` because there is genuinely nothing there.
 *
 * Keeping it separate says the same thing structurally and says it once: a caller that wants
 * neighbourhoods asks for this interface, and whether anything answers is a BINDING decision. The
 * Phase 1a interface stays frozen, its three implementations stay untouched, and "no neighbourhoods
 * under the eloquent source" is expressed by binding {@see NullCriteriaNeighborhoodRepository}
 * rather than by a stub that lies about having looked.
 *
 * READ-ONLY, like everything in this namespace. One method, returning
 * {@see GeographyOption} lists, no model, no write path.
 *
 * THE PARENT IS A CITY, AND THAT IS THE WHOLE CONTRACT
 * ----------------------------------------------------
 * A neighbourhood is justified by the municipality that contains it, not by its county. Clearwater
 * Beach is meaningful as part of Clearwater; parenting it by Pinellas County would make this tier a
 * second, flat city list and would leave a selected neighbourhood alive after its city was
 * deselected. So the input is CITY option ids and the emitted options carry the city as `parentId`.
 */
interface CriteriaNeighborhoodRepository
{
    /**
     * The neighbourhoods and communities inside the given cities, ordered by name.
     *
     * CONTAINMENT, NOT ASSOCIATION — the opposite of ZIPs. A neighbourhood belongs to exactly one
     * city (`location_places.parent_place_id` is a single foreign key), so each is emitted once and
     * a caller never has to reason about multiple parents. That asymmetry with
     * {@see CriteriaGeographyRepository::zipsInCounties()} is deliberate and is what lets the
     * cascade clear this tier unconditionally when its city goes.
     *
     * @param  list<string>  $cityIds  city option ids, as issued by the bound geography repository
     * @return list<GeographyOption> kind = neighborhood; empty when the list is empty or unknown
     */
    public function neighborhoodsInCities(array $cityIds): array;
}
