<?php

namespace App\Services\Location\AddressCorpus\Ng911;

use App\Services\Location\Coordinates\CoordinatePrecision;

/**
 * Pinellas County, FL — `Pinellas_SiteAddressPoint`.
 *
 * Publisher: Pinellas County eGIS. NENA NG9-1-1 GIS Data Model.
 * Service: services.arcgis.com/f5HgUpxURgEzTccH/…/Pinellas_SiteAddressPoint_view/0
 *
 * Configuration only — every value below was read off the published layer during
 * the source audit, not guessed.
 *
 * WHAT THE AUDIT FOUND, AND WHAT IT IMPLIES HERE
 * ----------------------------------------------
 * 580,696 records; `STATUS` = Current on 568,358 and Retired on 11,432, which is
 * why the status filter exists at all. 220,722 rows (38%) carry a unit — this is
 * a beach-condo county, and the county models up to five unit designators per
 * record, which is why `additionalUnitColumns` is not empty.
 *
 * `POINTTYPE` is populated on 144 of 580,696 rows. That is the reason precision
 * stays at Parcel: there is no evidence here that a point is a rooftop, and the
 * handful of populated values are mostly the non-address ones below.
 *
 * LICENSING IS UNRESOLVED
 * -----------------------
 * Pinellas publishes an "as is" disclaimer and no licence grant, unlike
 * Hillsborough's CC BY 4.0. That is a question for the county, not for this
 * file, but nothing here should be read as an assertion that the data may be
 * used commercially — the map describes the schema, not the rights.
 */
final class PinellasColumnMap
{
    public const SOURCE = 'pinellas';

    public static function map(): Ng911ColumnMap
    {
        return new Ng911ColumnMap(
            source:       self::SOURCE,
            jurisdiction: 'Pinellas County, FL',
            stateFips:    '12',

            numberColumn: 'ADDRNUM',
            streetColumn: 'FULLNAME',

            // SITEADDID is the county's own address id; GlobalID is the replica
            // GUID. Prefer the address id — it is the thing the county's own
            // records key on, and it survives a republish.
            sourceRefColumn:         'SITEADDID',
            fallbackSourceRefColumn: 'GlobalID',

            unitTypeColumn: 'UNITTYPE',
            unitIdColumn:   'UNITID',
            // Pinellas models four further unit designators. Dropping them would
            // collapse distinct condos onto one identity line.
            additionalUnitColumns: ['ALTUNITID', 'SECONDALTUNITID', 'THIRDALTUNITID', 'FOURTHALTUNITID'],

            cityColumn:         'PSTLCITY',
            fallbackCityColumn: 'MSAG',
            zipColumn:          'PSTLZIP5',
            stateColumn:        'STATEABBREVIATION',
            countyColumn:       'COUNTY',
            municipalityColumn: 'MUNICIPALITY',

            placementColumn: 'POINTTYPE',
            statusColumn:    'STATUS',
            updatedColumn:   'last_edited_date',

            // Both published by the county — nothing injected.
            stateConstant:  null,
            countyConstant: null,

            // Domain: Current / Pending / Retired / Temporary / Other.
            // Pending is a real future address and Temporary a real current one;
            // Retired is the one that must never resolve.
            activeStatusValues: ['Current', 'Pending', 'Temporary'],

            // Observed POINTTYPE values that address infrastructure rather than
            // property. Exact match only.
            nonAddressPlacements: [
                'Utility Asset', 'Cell Tower', 'Lift Station',
                'Power Meter', 'Dumpster', 'Construction Trailer', 'Mile Post',
            ],

            defaultPrecision: CoordinatePrecision::Parcel,
        );
    }
}
