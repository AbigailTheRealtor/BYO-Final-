<?php

namespace App\Services\Location\AddressCorpus\Ng911;

use App\Services\Location\Coordinates\CoordinatePrecision;

/**
 * Hillsborough County, FL — `SiteAddressPoints`.
 *
 * Publisher: Hillsborough County (Streets & Addresses / 911 Agency).
 * NENA NG9-1-1 GIS Data Model. Licence: CC BY 4.0 — attribution required.
 * Service: services.arcgis.com/apTfC6SUmnNfnxuF/…/Site_Address_Point/0
 *
 * Configuration only — every value below was read off the published layer during
 * the source audit.
 *
 * THE INJECTED CONSTANTS
 * ----------------------
 * Hillsborough publishes neither a state nor a county column. Inside the
 * county's own 911 system that is obvious; in a nationwide corpus it is missing
 * data, and `PropertyAddress::hasMinimumForLookup()` would reject most of the
 * file without it. So both are supplied here as configuration, and every record
 * built from this map records them as **injected** rather than read. That
 * distinction is the whole point: a reviewer can tell what the county asserted
 * from what we configured.
 *
 * NATIVE CRS IS NOT WGS84
 * -----------------------
 * The service stores geometry in EPSG:2237 (NAD83 / Florida West, US feet).
 * Nothing in this application reprojects. The acquisition procedure must export
 * GeoJSON with `outSR=4326`, and {@see \App\Services\Location\AddressCorpus\GeoJsonSourceReader}
 * fails closed rather than reading State Plane feet as degrees. This note lives
 * here because this is the map a future operator will read first.
 *
 * WHAT THE AUDIT FOUND
 * --------------------
 * 752,161 records; `STATUS` = Current on 748,310, Inactive on 642. 208,435 rows
 * (27.7%) carry a unit. `POINTTYPE` is populated — but with `Location` on 96.1%
 * of rows, which is a label rather than a placement claim, so precision stays at
 * Parcel exactly as it does for Pinellas.
 */
final class HillsboroughColumnMap
{
    public const SOURCE = 'hillsborough';

    public static function map(): Ng911ColumnMap
    {
        return new Ng911ColumnMap(
            source:       self::SOURCE,
            jurisdiction: 'Hillsborough County, FL',
            stateFips:    '12',

            numberColumn: 'ADDRNUM',
            streetColumn: 'FULLNAME',

            sourceRefColumn:         'SITEADDID',
            fallbackSourceRefColumn: 'GlobalID',

            unitTypeColumn: 'UNITTYPE',
            unitIdColumn:   'UNITID',
            additionalUnitColumns: [],

            // POSTALCOMM is the postal community — the name a person writes on an
            // envelope, and therefore the one they type into our form.
            // PLACENAME is the fallback.
            cityColumn:         'POSTALCOMM',
            fallbackCityColumn: 'PLACENAME',
            zipColumn:          'ZIP',

            // Absent from the source. Supplied below, and flagged as injected.
            stateColumn:  null,
            countyColumn: null,

            municipalityColumn: 'MUNICIPALITY',

            placementColumn: 'POINTTYPE',
            statusColumn:    'STATUS',
            updatedColumn:   'LASTUPDATE',

            stateConstant:  'FL',
            countyConstant: 'Hillsborough',

            // Observed: Current / Pending / Temporary / Inactive.
            activeStatusValues: ['Current', 'Pending', 'Temporary'],

            // Observed POINTTYPE values that are not property addresses.
            // `Other` and `Unknown` are deliberately absent — they are kept and
            // stay visible in the report rather than being guessed at.
            nonAddressPlacements: ['Utility', 'Code Enforcement', 'Project'],

            defaultPrecision: CoordinatePrecision::Parcel,
        );
    }
}
