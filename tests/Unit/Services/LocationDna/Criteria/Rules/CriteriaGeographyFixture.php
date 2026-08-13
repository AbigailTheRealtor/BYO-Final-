<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\FakeCriteriaNeighborhoodRepository;

/**
 * Phase 1b — the shared fixture for the rules tests.
 *
 * Deliberately small, and deliberately shaped around the one case that separates a correct cascade
 * from a plausible one: ZIP 11001 is associated with BOTH Suffolk and Nassau. Every other row exists
 * to give that case something to contrast against.
 *
 * It also carries a second state (Louisiana, whose counties are Parishes) so cross-state leakage is
 * detectable, and a city per county so containment and association can be observed diverging in the
 * same assertion.
 *
 * No database. That is the point of the fake — the rules must be provable with no connection at all.
 */
trait CriteriaGeographyFixture
{
    // States
    protected const NEW_YORK  = '1';
    protected const LOUISIANA = '2';

    // Counties — New York
    protected const SUFFOLK = '10';
    protected const NASSAU  = '11';
    protected const KINGS   = '12';

    // Counties — Louisiana
    protected const ORLEANS = '20';

    // Cities (single-FK parents)
    protected const BABYLON     = '100'; // Suffolk
    protected const HUNTINGTON  = '101'; // Suffolk
    protected const HEMPSTEAD   = '110'; // Nassau
    protected const NEW_ORLEANS = '200'; // Orleans

    // ZIPs (association; may have several parents)
    protected const ZIP_SHARED  = '11001'; // Suffolk AND Nassau — the case that matters
    protected const ZIP_SUFFOLK = '11701'; // Suffolk only
    protected const ZIP_NASSAU  = '11550'; // Nassau only
    protected const ZIP_ORLEANS = '70112'; // Orleans only

    // Neighbourhoods (Phase 1d-5; containment, exactly one city parent)
    //
    // Two under Babylon so ordering and partial clearing are both observable, one under a city in a
    // DIFFERENT county so the two-tier transitive clear — drop Nassau, lose Hempstead, lose West
    // End — can be asserted rather than assumed.
    protected const OAK_BEACH  = '900'; // Babylon (Suffolk)
    protected const GILGO      = '901'; // Babylon (Suffolk)
    protected const WEST_END   = '910'; // Hempstead (Nassau)
    protected const FRENCH_QTR = '920'; // New Orleans (Orleans, Louisiana)

    protected function geography(): FakeCriteriaGeographyRepository
    {
        return (new FakeCriteriaGeographyRepository())
            ->withState(self::NEW_YORK, 'New York', '36')
            ->withState(self::LOUISIANA, 'Louisiana', '22')

            ->withCounty(self::SUFFOLK, 'Suffolk County', self::NEW_YORK, '36103')
            ->withCounty(self::NASSAU, 'Nassau County', self::NEW_YORK, '36059')
            ->withCounty(self::KINGS, 'Kings County', self::NEW_YORK, '36047')
            ->withCounty(self::ORLEANS, 'Orleans Parish', self::LOUISIANA, '22071')

            ->withCity(self::BABYLON, 'Babylon', self::SUFFOLK)
            ->withCity(self::HUNTINGTON, 'Huntington', self::SUFFOLK)
            ->withCity(self::HEMPSTEAD, 'Hempstead', self::NASSAU)
            ->withCity(self::NEW_ORLEANS, 'New Orleans', self::ORLEANS)

            // The shared ZIP is registered twice, once per associated county — exactly what
            // EloquentCriteriaGeographyRepository emits for a ZCTA that crosses a county line.
            ->withZip(self::ZIP_SHARED, self::SUFFOLK)
            ->withZip(self::ZIP_SHARED, self::NASSAU)
            ->withZip(self::ZIP_SUFFOLK, self::SUFFOLK)
            ->withZip(self::ZIP_NASSAU, self::NASSAU)
            ->withZip(self::ZIP_ORLEANS, self::ORLEANS);
    }

    /**
     * Phase 1d-5 — the neighbourhood corpus, kept SEPARATE from the geography one.
     *
     * Two repositories rather than one, because that is the shape production has: a test that could
     * reach neighbourhoods through `geography()` would be exercising a coupling
     * {@see \App\Services\LocationDna\Criteria\CriteriaNeighborhoodRepository} exists to prevent.
     *
     * Registered against CITY ids, never county ids — containment through the tier immediately
     * above, which is what makes the transitive clear observable.
     */
    protected function neighborhoods(): FakeCriteriaNeighborhoodRepository
    {
        return (new FakeCriteriaNeighborhoodRepository())
            ->withNeighborhood(self::OAK_BEACH, 'Oak Beach', self::BABYLON)
            ->withNeighborhood(self::GILGO, 'Gilgo', self::BABYLON)
            ->withNeighborhood(self::WEST_END, 'West End', self::HEMPSTEAD)
            ->withNeighborhood(self::FRENCH_QTR, 'French Quarter', self::NEW_ORLEANS);
    }
}
