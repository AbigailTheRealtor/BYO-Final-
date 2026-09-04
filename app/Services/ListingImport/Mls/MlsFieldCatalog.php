<?php

namespace App\Services\ListingImport\Mls;

/**
 * Every Bridge/Stellar Property field this application has ever seen, and what
 * we do with it.
 *
 * WHY ONE CLASS
 * -------------
 * Before this, the answer to "what happens to field X?" was spread across five
 * places that did not know about each other: the normalizer's column list, the
 * candidate adapter's named-key reads, the prefill allow-list, the display
 * presenter's allow-list, and the Stellar view mapper. A field could be fetched,
 * cached, and then silently fall out of every one of them — which is exactly
 * what the 2026-09-04 payload audit found for 288 populated fields.
 *
 * This catalog is the single classification authority. Every known field
 * resolves to exactly one disposition, and
 * {@see \Tests\Feature\ListingImport\MlsNoFieldDropContractTest} fails the build
 * when a field in a fixture resolves to none of them. "We forgot" is no longer a
 * reachable state; the only reachable states are the eleven below, and each one
 * is a decision somebody wrote down.
 *
 * THE ELEVEN DISPOSITIONS
 * -----------------------
 *  TIER1_BYO          — has a true semantic equivalent in a Create Offer field
 *                       and is imported into it.
 *  PROPERTY_FACTS     — a legitimate public property fact with no editable BYO
 *                       equivalent; preserved as supplemental MLS metadata and
 *                       rendered in the MLS Details sections.
 *  LISTING_CONTEXT    — MLS bookkeeping a consumer legitimately wants (MLS #,
 *                       status, days on market, list date, virtual tour).
 *  CONTACTS           — listing agent / brokerage / association contact data.
 *                       Rendered only where display permissions allow.
 *  RELATED_RESOURCE   — belongs to a Bridge resource of its own (Media, Rooms,
 *                       Units, OpenHouse), not to the Property row.
 *  DISPLAY_CONTROL    — the feed's own display permissions. Read by
 *                       {@see MlsDisplayPermissions}, never rendered.
 *  ADDRESS_COMPONENT  — a piece of the address. Imported as a fact, and
 *                       suppressed at render when the feed forbids the address.
 *  INTERNAL           — provenance, keys, ratios, sync bookkeeping. Preserved in
 *                       raw_json, deliberately never shown to a consumer.
 *  RESTRICTED         — populated, and withheld for a stated licensing or
 *                       privacy reason. Never deleted, never displayed.
 *  DERIVED            — the same fact as another field we already show, in a
 *                       different unit or spelling. Rendering both would read as
 *                       a duplicate row.
 *  UNSUPPORTED        — recognised, and knowingly not handled, with a reason.
 *
 * ADDING A FIELD IS A DECISION, NOT A MAPPING TWEAK.
 * Everything here fails closed: a field absent from this catalog is rendered
 * nowhere, and the contract test names it in the failure. That is the intended
 * order of events — a new Stellar column breaks a test before it reaches a page.
 */
final class MlsFieldCatalog
{
    // =========================================================================
    // TIER 1 — reaches an existing, editable Create Offer field
    // =========================================================================

    /**
     * Bridge field => the canonical import key it travels under.
     *
     * The canonical key's role-specific destination lives in
     * {@see \App\Services\ListingImport\MlsFieldMap}; this constant only records
     * that the field HAS a Tier-1 destination, which is what the no-drop
     * contract needs to know.
     *
     * @var array<string,string>
     */
    public const TIER1_BYO = [
        // Identity carried as provenance meta rather than as an editable input.
        'ListingId'                     => 'mls_number',
        'ListingKey'                    => 'mls_listing_key',

        // Address
        'UnparsedAddress'               => 'address',
        'City'                          => 'city',
        'StateOrProvince'               => 'state',
        'PostalCode'                    => 'zip',
        'CountyOrParish'                => 'county',

        // Coordinates
        'Latitude'                      => 'latitude',
        'Longitude'                     => 'longitude',

        // Structure / size
        'BedroomsTotal'                 => 'bedrooms',
        'BathroomsTotalInteger'         => 'bathrooms',
        'LivingArea'                    => 'heated_sqft',
        'LivingAreaSource'              => 'sqft_heated_source',
        'LotSizeSquareFeet'             => 'lot_size_sqft',
        'LotSizeAcres'                  => 'lot_size_acres',
        'LotSizeDimensions'             => 'lot_dimensions',
        'BuildingAreaTotal'             => 'building_size_sqft',
        'YearBuilt'                     => 'year_built',

        // Classification
        'PropertyType'                  => 'property_type',
        'PropertySubType'               => 'property_sub_type',
        'MlsStatus'                     => 'mls_status',

        // Price
        'ListPrice'                     => 'price',

        // Tax / legal / parcel
        'TaxAnnualAmount'               => 'annual_taxes',
        'TaxYear'                       => 'tax_year',
        'ParcelNumber'                  => 'tax_id',
        'TaxLegalDescription'           => 'legal_description',
        'NumberOfLots'                  => 'total_parcel_count',
        'AdditionalParcelsYN'           => 'additional_parcels',
        'Zoning'                        => 'zoning',

        // HOA / CDD
        'AssociationYN'                 => 'has_hoa',
        'AssociationFee'                => 'association_fee_amount',
        'AssociationFeeFrequency'       => 'association_fee_frequency',
        'STELLAR_CDDYN'                 => 'has_cdd',

        // Hazard
        'STELLAR_FloodZoneCode'         => 'flood_zone_code',
        'STELLAR_FloodZonePanel'        => 'flood_zone_panel',
        'STELLAR_FloodZoneDate'         => 'flood_zone_date',

        // Physical characteristics
        'WaterfrontYN'                  => 'waterfront',
        'STELLAR_WaterfrontFeetTotal'   => 'waterfront_feet',
        'PoolPrivateYN'                 => 'pool',
        'GarageYN'                      => 'garage',
        'CarportYN'                     => 'carport',

        // Construction / systems
        'Appliances'                    => 'appliances',
        'ConstructionMaterials'         => 'exterior_construction',
        'Cooling'                       => 'air_conditioning',
        'Heating'                       => 'heating_fuel',
        'FoundationDetails'             => 'foundation',
        'InteriorFeatures'              => 'interior_features',
        'Roof'                          => 'roof_type',
        'Sewer'                         => 'sewer',
        'Utilities'                     => 'utilities',
        'WaterSource'                   => 'water',
        'WaterfrontFeatures'            => 'water_access',
        'Flooring'                      => 'flooring',
        'Furnished'                     => 'furnished',

        // Lease / rental (landlord destinations)
        // `LeaseTerm` is DELIBERATELY ABSENT. The landlord form's own lease
        // vocabulary is a fixed option list ('3 Months', '1 Year', '2 Years',
        // 'Month-to-Month', and a separate commercial set), and the feed's is
        // not: 'Short Term Lease', '>6 Months <12', '48 Months', 'Weekly'. Half
        // the feed's values have no destination option at all, and choosing the
        // right option list requires knowing the property type, which is written
        // in the same pass. A duration written into the commercial lease-TYPE
        // control would read as a lease structure the landlord never chose. The
        // fact is preserved verbatim under Lease / Rental instead.
        'AvailabilityDate'              => 'available_date',
        'LeaseAmountFrequency'          => 'lease_amount_frequency',
        'STELLAR_SecurityDeposit'       => 'minimum_security_deposit',
        'STELLAR_OfficeRetailSpaceSqFt' => 'office_area_sqft',

        // Income / business (seller destinations)
        'GrossIncome'                   => 'gross_annual_income',
        'STELLAR_AnnualExpenses'        => 'annual_operating_expenses',
        'BusinessType'                  => 'business_type',
    ];

    /**
     * Tier-1 fields whose destination exists on a role's form but which that
     * role's LISTING PAGE never renders.
     *
     * WHY THIS IS NEEDED AT ALL
     * -------------------------
     * The supplemental presenter omits a fact that already reached an editable
     * Create Offer field, so the listing does not print the same thing twice.
     * That reasoning holds only while the listing page actually prints the
     * editable field — and for these it does not. `air_conditioning`,
     * `heating_fuel`, `sewer`, `water` and `floor_covering` are all written to a
     * landlord listing and none of them appear anywhere on the landlord listing
     * page; suppressing them from MLS Details as well would mean the import
     * captured the fact and the reader never saw it. Exactly the loss this work
     * exists to remove, reintroduced by the fix for it.
     *
     * So these are exceptions: mapped, written, and STILL shown under MLS
     * Details, because that is the only place they surface.
     *
     * Pinned by {@see \Tests\Feature\ListingImport\MlsSearchImportParityTest},
     * which re-derives the list from the Blade templates. A view that stops
     * rendering a field, or starts, fails that test with the field's name — so
     * this constant cannot quietly go stale, and neither can the assumption
     * behind the suppression.
     *
     * @var array<string, list<string>>
     */
    public const TIER1_MAPPED_BUT_UNRENDERED = [
        'seller' => [
            'STELLAR_FloodZoneDate',
            'WaterfrontYN',
            'STELLAR_WaterfrontFeetTotal',
            'InteriorFeatures',
            'WaterfrontFeatures',
        ],
        'landlord' => [
            'STELLAR_WaterfrontFeetTotal',
            'Cooling',
            'Heating',
            'Sewer',
            'Utilities',
            'WaterSource',
            'Flooring',
            'STELLAR_OfficeRetailSpaceSqFt',
        ],
    ];

    // =========================================================================
    // TIER 2a — supplemental MLS property facts, grouped for display
    // =========================================================================

    /**
     * section => [ Bridge field => human-readable label ]
     *
     * Every entry is an objective, publicly-advertised characteristic of the
     * building, the land, or the terms on which it is offered. Nothing here is
     * authored prose, a person, an identifier, or a broker's compensation.
     *
     * Labels are written for a consumer, never derived from the column name: a
     * listing page must not print `STELLAR_NumofBaysDockHigh`.
     *
     * @var array<string, array<string,string>>
     */
    public const PROPERTY_FACTS = [
        'Property Details' => [
            'PropertySubType'                => 'Property Sub-Type',
            'ArchitecturalStyle'             => 'Architectural Style',
            'PropertyCondition'              => 'Condition',
            'StructureType'                  => 'Structure Type',
            'PropertyAttachedYN'             => 'Attached Property',
            'CommonWalls'                    => 'Common Walls',
            'BuildingAreaSource'             => 'Building Area Source',
            'BuildingAreaUnits'              => 'Building Area Units',
            'LivingAreaUnits'                => 'Living Area Units',
            'YearBuiltEffective'             => 'Effective Year Built',
            'YearBuiltSource'                => 'Year Built Source',
            'NewConstructionYN'              => 'New Construction',
            'STELLAR_ProjectedCompletionDate' => 'Projected Completion',
            'BuilderName'                    => 'Builder',
            'BuilderModel'                   => 'Builder Model',
            'STELLAR_BuilderLicenseNumber'   => 'Builder Licence',
            'MLSAreaMajor'                   => 'MLS Area',
            'SubdivisionName'                => 'Subdivision',
            'STELLAR_SWSubdivCommunityName'  => 'Community',
            'STELLAR_ComplexDevelopmentName' => 'Development',
            'STELLAR_ComplexCommunityNameNCCB' => 'Complex',
            'STELLAR_Development'            => 'Development Name',
            'STELLAR_BuildingNameNumber'     => 'Building',
            'STELLAR_FloorNumber'            => 'Floor',
            'STELLAR_UnitNumberYN'           => 'Has Unit Number',
            'STELLAR_CondoEnvironmentYN'     => 'Condominium Environment',
            'STELLAR_CondoLandIncludedYN'    => 'Condominium Land Included',
            'STELLAR_ConvertedResidenceYN'   => 'Converted Residence',
            'Ownership'                      => 'Ownership',
            'STELLAR_TotalAcreage'           => 'Total Acreage (MLS Range)',
            'LotSizeArea'                    => 'Lot Size Area',
            'LotSizeUnits'                   => 'Lot Size Units',
            'LotFeatures'                    => 'Lot Features',
            'NumberOfLots'                   => 'Number of Lots',
            'NumberOfBuildings'              => 'Number of Buildings',
            'NumberOfUnitsTotal'             => 'Total Units',
            'STELLAR_UnitCount'              => 'Unit Count',
            'AdditionalParcelsDescription'   => 'Additional Parcels',
            'Topography'                     => 'Topography',
            'Vegetation'                     => 'Vegetation',
            'DirectionFaces'                 => 'Faces',
            'RoadFrontageType'               => 'Road Frontage',
            'RoadSurfaceType'                => 'Road Surface',
            'RoadResponsibility'             => 'Road Maintenance',
            'FrontageLength'                 => 'Frontage',
            'STELLAR_Easements'              => 'Easements',
            'STELLAR_AdjoiningProperty'      => 'Adjoining Property',
            'STELLAR_FutureLandUse'          => 'Future Land Use',
            'PossibleUse'                    => 'Possible Use',
            'CurrentUse'                     => 'Current Use',
            'STELLAR_UseCode'                => 'Use Code',
            'HorseAmenities'                 => 'Horse Amenities',
            'STELLAR_NumberOfPaddocksPastures' => 'Paddocks / Pastures',
            'STELLAR_NumberOfStalls'         => 'Stalls',
            'STELLAR_BarnFeatures'           => 'Barn Features',
            'STELLAR_DisasterMitigation'     => 'Disaster Mitigation',
            'STELLAR_GreenLandscaping'       => 'Green Landscaping',
            'GreenEnergyEfficient'           => 'Energy Efficient Features',
            'GreenEnergyGeneration'          => 'Energy Generation',
            'GreenIndoorAirQuality'          => 'Indoor Air Quality',
            'GreenBuildingVerificationType'  => 'Green Building Verification',
            'GreenLocation'                  => 'Green Location',
            'GreenSustainability'            => 'Sustainability',
            'GreenWaterConservation'         => 'Water Conservation',
            'STELLAR_SolarPanelOwnership'    => 'Solar Panel Ownership',
            'STELLAR_SolarLeaseFinanceTerms' => 'Solar Lease Terms',
            'STELLAR_HERSIndex'              => 'HERS Index',
            'BodyType'                       => 'Body Type',
            'Skirt'                          => 'Skirt',
            'MobileLength'                   => 'Mobile Home Length',
            'MobileWidth'                    => 'Mobile Home Width',
            'Make'                           => 'Make',
            'Model'                          => 'Model',
            'NumberOfPads'                   => 'Number of Pads',
            'STELLAR_AffidavitYN'            => 'Affidavit on File',
            'STELLAR_HomesteadYN'            => 'Homestead',
            'STELLAR_PlannedUnitDevelopmentYN' => 'Planned Unit Development',
            'STELLAR_ZoningCompatibleYN'     => 'Zoning Compatible',
            'ZoningDescription'              => 'Zoning Description',
            'Disclosures'                    => 'Disclosures',
            'Exclusions'                     => 'Exclusions',
            'SpecialListingConditions'       => 'Special Listing Conditions',
            'STELLAR_RealtorInfo'            => 'Listing Notes',
            'STELLAR_Geolocation'            => 'Geographic Area',
        ],

        'Interior' => [
            'Appliances'                     => 'Appliances',
            'Flooring'                       => 'Flooring',
            'InteriorFeatures'               => 'Interior Features',
            'RoomsTotal'                     => 'Total Rooms',
            'STELLAR_AdditionalRooms'        => 'Additional Rooms',
            'RoomType'                       => 'Room Types',
            'BathroomsFull'                  => 'Full Bathrooms',
            'BathroomsHalf'                  => 'Half Bathrooms',
            'BathroomsThreeQuarter'          => 'Three-Quarter Bathrooms',
            'BathroomsOneQuarter'            => 'Quarter Bathrooms',
            'BathroomsPartial'               => 'Partial Bathrooms',
            'STELLAR_MasterBedSize'          => 'Primary Bedroom Size',
            'STELLAR_InLawSuiteYN'           => 'In-Law Suite',
            'STELLAR_InLawSuiteDescrip'      => 'In-Law Suite Details',
            'FireplaceYN'                    => 'Fireplace',
            'FireplacesTotal'                => 'Fireplaces',
            'FireplaceFeatures'              => 'Fireplace Features',
            'Heating'                        => 'Heating',
            'HeatingYN'                      => 'Has Heating',
            'Cooling'                        => 'Cooling',
            'CoolingYN'                      => 'Has Cooling',
            'LaundryFeatures'                => 'Laundry',
            'WindowFeatures'                 => 'Windows',
            'DoorFeatures'                   => 'Doors',
            'Levels'                         => 'Levels',
            'StoriesTotal'                   => 'Stories',
            'Stories'                        => 'Story Count',
            'Basement'                       => 'Basement',
            'AccessibilityFeatures'          => 'Accessibility Features',
            'SecurityFeatures'               => 'Security Features',
            'Furnished'                      => 'Furnished',
            'STELLAR_CeilingHeight'          => 'Ceiling Height',
            'STELLAR_CeilingType'            => 'Ceiling Type',
            'STELLAR_VirtuallyStagedYN'      => 'Virtually Staged',
            'STELLAR_ILSTotalSQFT'           => 'Total Square Feet (ILS)',
            'STELLAR_ILSUnderAirSQFT'        => 'Under-Air Square Feet (ILS)',
        ],

        'Exterior' => [
            'ExteriorFeatures'               => 'Exterior Features',
            'ConstructionMaterials'          => 'Construction',
            'Roof'                           => 'Roof',
            'FoundationDetails'              => 'Foundation',
            'PatioAndPorchFeatures'          => 'Patio & Porch',
            'Fencing'                        => 'Fencing',
            'OtherStructures'                => 'Other Structures',
            'STELLAR_GarageDimensions'       => 'Garage Dimensions',
            'STELLAR_FreestandingYN'         => 'Freestanding',
            'STELLAR_BuildingElevatorYN'     => 'Building Elevator',
            'STELLAR_DoorHeight'             => 'Door Height',
            'STELLAR_DoorWidth'              => 'Door Width',
            'STELLAR_GarageDoorHeight'       => 'Garage Door Height',
            'STELLAR_EavesHeight'            => 'Eaves Height',
        ],

        'Parking / Garage' => [
            'GarageYN'                       => 'Garage',
            'GarageSpaces'                   => 'Garage Spaces',
            'AttachedGarageYN'               => 'Attached Garage',
            'CarportYN'                      => 'Carport',
            'CarportSpaces'                  => 'Carport Spaces',
            'CoveredSpaces'                  => 'Covered Spaces',
            'ParkingFeatures'                => 'Parking Features',
            'ParkingTotal'                   => 'Total Parking',
            'OpenParkingYN'                  => 'Open Parking',
            'OpenParkingSpaces'              => 'Open Parking Spaces',
            'OtherParking'                   => 'Other Parking',
            'STELLAR_ParkingFeeTenants'      => 'Tenant Parking Fee',
            'STELLAR_ParkingFeeTenantsFrequency' => 'Tenant Parking Fee Frequency',
        ],

        'Pool / Spa' => [
            'PoolPrivateYN'                  => 'Private Pool',
            'PoolFeatures'                   => 'Pool Features',
            'STELLAR_PoolDimensions'         => 'Pool Dimensions',
            'SpaYN'                          => 'Spa',
            'SpaFeatures'                    => 'Spa Features',
        ],

        'Waterfront / Views' => [
            'WaterfrontYN'                   => 'Waterfront',
            'WaterfrontFeatures'             => 'Waterfront Features',
            'STELLAR_WaterfrontFeetTotal'    => 'Waterfront Feet',
            'WaterBodyName'                  => 'Water Body',
            'STELLAR_WaterAccessYN'          => 'Water Access',
            'STELLAR_WaterAccess'            => 'Water Access Type',
            'STELLAR_WaterExtrasYN'          => 'Water Extras',
            'STELLAR_WaterExtras'            => 'Water Extras Detail',
            'STELLAR_WaterViewYN'            => 'Water View',
            'STELLAR_WaterView'              => 'Water View Type',
            'STELLAR_AdditionalWaterInformation' => 'Additional Water Information',
            'STELLAR_DockYN'                 => 'Dock',
            'STELLAR_DockDescrip'            => 'Dock Description',
            'STELLAR_DockDimensions'         => 'Dock Dimensions',
            'STELLAR_DockLiftCap'            => 'Dock Lift Capacity',
            'STELLAR_DockYrBlt'              => 'Dock Year Built',
            'STELLAR_DockMntncFee'           => 'Dock Maintenance Fee',
            'STELLAR_DockMntncFeeFrqncy'     => 'Dock Maintenance Fee Frequency',
            'STELLAR_NoDriveBeach'           => 'No-Drive Beach',
            'ViewYN'                         => 'Has View',
            'View'                           => 'View',
        ],

        'Utilities' => [
            'Utilities'                      => 'Utilities',
            'Sewer'                          => 'Sewer',
            'WaterSource'                    => 'Water Source',
            'Electric'                       => 'Electric',
            'Gas'                            => 'Gas',
            'NumberOfSeparateElectricMeters' => 'Separate Electric Meters',
            'NumberOfSeparateGasMeters'      => 'Separate Gas Meters',
            'NumberOfSeparateWaterMeters'    => 'Separate Water Meters',
            'STELLAR_NumberOfWells'          => 'Wells',
            'STELLAR_NumberOfSeptics'        => 'Septic Systems',
        ],

        'HOA / Association' => [
            'AssociationYN'                  => 'Association',
            'AssociationFee'                 => 'Association Fee',
            'AssociationFeeFrequency'        => 'Fee Frequency',
            'AssociationFee2'                => 'Second Association Fee',
            'AssociationFee2Frequency'       => 'Second Fee Frequency',
            'AssociationFeeIncludes'         => 'Fee Includes',
            'AssociationAmenities'           => 'Amenities',
            'CommunityFeatures'              => 'Community Features',
            'SeniorCommunityYN'              => 'Senior Community',
            'STELLAR_AssociationFeeRequirement' => 'Fee Requirement',
            'STELLAR_AssociationApprovalRequiredYN' => 'Association Approval Required',
            'STELLAR_AssociationApprovalFee' => 'Association Approval Fee',
            'STELLAR_AssociationApplicationFee' => 'Association Application Fee',
            'STELLAR_MonthlyCondoFeeAmount'  => 'Monthly Condominium Fee',
            'STELLAR_CondoFees'              => 'Condominium Fee',
            'STELLAR_CondoFeesTerm'          => 'Condominium Fee Term',
            'STELLAR_MonthlyHOAAmount'       => 'Monthly HOA Amount',
            'STELLAR_MontlyMaintAmtAdditionToHOA' => 'Additional Monthly Maintenance',
            'STELLAR_TotalMonthlyFees'       => 'Total Monthly Fees',
            'STELLAR_TotalAnnualFees'        => 'Total Annual Fees',
            'STELLAR_AmenitiesAdditionalFees' => 'Amenities With Additional Fees',
            'STELLAR_OtherFeesAmount'        => 'Other Fees',
            'STELLAR_OtherFeesTerm'          => 'Other Fees Term',
            'STELLAR_OtherFeesDescription'   => 'Other Fees Description',
            'STELLAR_AdditionalMembershipAvailableYN' => 'Additional Membership Available',
            'STELLAR_ApprovalProcess'        => 'Approval Process',
        ],

        'Taxes / Financial' => [
            'TaxAnnualAmount'                => 'Annual Taxes',
            'TaxYear'                        => 'Tax Year',
            'TaxBlock'                       => 'Tax Block',
            'TaxLot'                         => 'Tax Lot',
            'TaxBookNumber'                  => 'Tax Book',
            'TaxExemptions'                  => 'Tax Exemptions',
            'TaxOtherAnnualAssessmentAmount' => 'Other Annual Assessment',
            'STELLAR_MillageRate'            => 'Millage Rate',
            'STELLAR_OtherExemptionsYN'      => 'Other Exemptions',
            'ListingTerms'                   => 'Acceptable Financing',
            'Possession'                     => 'Possession',
            'Concessions'                    => 'Concessions',
            'ConcessionsAmount'              => 'Concessions Amount',
            'HomeWarrantyYN'                 => 'Home Warranty',
            'LandLeaseYN'                    => 'Land Lease',
            'LandLeaseAmount'                => 'Land Lease Amount',
            'CapRate'                        => 'Cap Rate',
            'NetOperatingIncome'             => 'Net Operating Income',
            'STELLAR_NetOperatingIncomeType' => 'Net Operating Income Type',
            'STELLAR_AnnualNetIncome'        => 'Annual Net Income',
            'GrossIncome'                    => 'Gross Income',
            'GrossScheduledIncome'           => 'Gross Scheduled Income',
            'STELLAR_EstAnnualMarketIncome'  => 'Estimated Annual Market Income',
            'STELLAR_AnnualExpenses'         => 'Annual Expenses',
            'STELLAR_TotalMonthlyExpenses'   => 'Total Monthly Expenses',
            'STELLAR_AnnualIncomeType'       => 'Income Type',
            'TotalActualRent'                => 'Total Actual Rent',
            'STELLAR_AnnualRent'             => 'Annual Rent',
            'NumberOfUnitsLeased'            => 'Units Leased',
        ],

        'Schools' => [
            'ElementarySchool'               => 'Elementary School',
            'MiddleOrJuniorSchool'           => 'Middle School',
            'HighSchool'                     => 'High School',
            'HighSchoolDistrict'             => 'School District',
        ],

        'Lease / Rental' => [
            'AvailabilityDate'               => 'Available From',
            'STELLAR_LastDateAvailable'      => 'Available Until',
            'STELLAR_MonthsAvailable'        => 'Months Available',
            'STELLAR_WeeksAvailable'         => 'Weeks Available',
            'LeaseTerm'                      => 'Lease Term',
            'LeaseAmountFrequency'           => 'Rent Frequency',
            'LeaseConsideredYN'              => 'Lease Considered',
            'STELLAR_ForLeaseYN'             => 'For Lease',
            'STELLAR_LongTermYN'             => 'Long-Term Rental',
            'STELLAR_MonthToMonthOrWeeklyYN' => 'Month-to-Month or Weekly',
            'STELLAR_MinimumLease'           => 'Minimum Lease',
            'STELLAR_NumTimesperYear'        => 'Times Leased per Year',
            'STELLAR_SecurityDeposit'        => 'Security Deposit',
            'STELLAR_LastMonthsRent'         => 'Last Month’s Rent',
            'STELLAR_ApplicationFee'         => 'Application Fee',
            'STELLAR_AdditionalApplicantFee' => 'Additional Applicant Fee',
            'STELLAR_SeasonalRent'           => 'Seasonal Rent',
            'STELLAR_OffSeasonRent'          => 'Off-Season Rent',
            'STELLAR_WeeklyRent'             => 'Weekly Rent',
            'STELLAR_CurrencyMonthlyRentAmt' => 'Monthly Rent',
            'STELLAR_LeasePricePerAcre'      => 'Lease Price per Acre',
            'OwnerPays'                      => 'Owner Pays',
            'TenantPays'                     => 'Tenant Pays',
            'RentIncludes'                   => 'Rent Includes',
            'PetsAllowed'                    => 'Pets Allowed',
            'STELLAR_NumberOfPets'           => 'Number of Pets Allowed',
            'STELLAR_MaxPetWeight'           => 'Maximum Pet Weight',
            'STELLAR_PetSize'                => 'Pet Size',
            'STELLAR_PetRestrictions'        => 'Pet Restrictions',
            'STELLAR_PetDepositFee'          => 'Pet Deposit',
            'STELLAR_PetFeeNonRefundable'    => 'Non-Refundable Pet Fee',
            'STELLAR_PetMonthlyFee'          => 'Monthly Pet Fee',
            'STELLAR_AdditionalPetFees'      => 'Additional Pet Fees',
            'STELLAR_LeaseRestrictionsYN'    => 'Lease Restrictions',
            'STELLAR_AdditionalLeaseRestrictions' => 'Lease Restriction Details',
            'STELLAR_YrsOfOwnerPriorToLeasingReqYN' => 'Ownership Period Required Before Leasing',
            'STELLAR_NumOfOwnYearsPriorToLse' => 'Years of Ownership Required Before Leasing',
            'STELLAR_DaysNoticeToTenantIfNotRenew' => 'Notice if Lease Not Renewed',
            'OccupantType'                   => 'Currently Occupied By',
            'STELLAR_ExistLseTenantYN'       => 'Existing Tenant in Place',
            'STELLAR_ExpectedLeaseDate'      => 'Expected Lease Date',
            'STELLAR_ExpireRenewalDate'      => 'Lease Renewal Date',
            'STELLAR_DepositsYN'             => 'Deposits Required',
        ],

        'Commercial / Business' => [
            'BusinessName'                   => 'Business Name',
            'BusinessType'                   => 'Business Type',
            'YearEstablished'                => 'Year Established',
            'STELLAR_BusinessOpportunityWithRealEstateYN' => 'Sold With Real Estate',
            'STELLAR_SDEOYN'                 => 'Seller Discretionary Earnings Provided',
            'BuildingFeatures'               => 'Building Features',
            'STELLAR_SpaceType'              => 'Space Type',
            'STELLAR_LeasableArea'           => 'Leasable Area',
            'STELLAR_LeasableAreaUnits'      => 'Leasable Area Units',
            'STELLAR_OfficeRetailSpaceSqFt'  => 'Office / Retail Space',
            'STELLAR_ManufacturingSpaceTotal' => 'Manufacturing Space',
            'STELLAR_ManufacturingSpaceHeated' => 'Heated Manufacturing Space',
            'STELLAR_WarehouseSpaceTotal'    => 'Warehouse Space',
            'STELLAR_WarehouseSpaceHeated'   => 'Heated Warehouse Space',
            'STELLAR_NumofOffices'           => 'Offices',
            'STELLAR_NumofConferenceMeetingRooms' => 'Conference Rooms',
            'STELLAR_NumofBays'              => 'Bays',
            'STELLAR_NumofBaysGradeLevel'    => 'Grade-Level Bays',
            'STELLAR_NumofBaysDockHigh'      => 'Dock-High Bays',
            'STELLAR_FreezerSpaceYN'         => 'Freezer Space',
            'STELLAR_Management'             => 'Management',
            'STELLAR_ComTransactionType'     => 'Commercial Transaction Type',
            'STELLAR_ComTransactionTerms'    => 'Commercial Transaction Terms',
            'STELLAR_CurrentAdjacentUse'     => 'Current Adjacent Use',
            'UnitTypeType'                   => 'Unit Types',
        ],
    ];

    // =========================================================================
    // TIER 2b — MLS listing context
    // =========================================================================

    /**
     * MLS bookkeeping a consumer legitimately wants to see. Separated from
     * PROPERTY_FACTS because it describes the LISTING rather than the property,
     * and because its display gate is the whole-listing one rather than the
     * per-fact one.
     *
     * @var array<string, array<string,string>>
     */
    public const LISTING_CONTEXT = [
        'MLS Information' => [
            'ListingId'                    => 'MLS #',
            'ListingKey'                   => 'Listing Key',
            'MlsStatus'                    => 'MLS Status',
            'StandardStatus'               => 'Status',
            'STELLAR_PreviousStatus'       => 'Previous Status',
            'OriginatingSystemName'        => 'Source MLS',
            'ListingContractDate'          => 'Listed On',
            'OnMarketDate'                 => 'On Market',
            'OffMarketDate'                => 'Off Market',
            'STELLAR_BOMDate'              => 'Back on Market',
            'STELLAR_ComingSoonDate'       => 'Coming Soon',
            'STELLAR_ExpectedOnMarketDate' => 'Expected on Market',
            'ExpirationDate'               => 'Listing Expires',
            'DaysOnMarket'                 => 'Days on Market',
            'CumulativeDaysOnMarket'       => 'Cumulative Days on Market',
            'OriginalListPrice'            => 'Original List Price',
            'PreviousListPrice'            => 'Previous List Price',
            'PriceChangeTimestamp'         => 'Last Price Change',
            'STELLAR_MlsMajorChangeType'   => 'Latest Change',
            'StatusChangeTimestamp'        => 'Status Changed',
            'ModificationTimestamp'        => 'Last Updated',
            'PhotosCount'                  => 'Photos',
            'PhotosChangeTimestamp'        => 'Photos Updated',
            'VirtualTourURLUnbranded'      => 'Virtual Tour',
            'STELLAR_VirtualTourURLUnbranded2' => 'Additional Virtual Tour',
            'CloseDate'                    => 'Closed On',
            'PurchaseContractDate'         => 'Under Contract',
        ],
    ];

    /** Fields in LISTING_CONTEXT that must be rendered as a link, not as text. */
    public const URL_FIELDS = [
        'VirtualTourURLUnbranded',
        'STELLAR_VirtualTourURLUnbranded2',
    ];

    // =========================================================================
    // TIER 2c — contacts
    // =========================================================================

    /**
     * Listing agent, brokerage and association contact data.
     *
     * Held in its own constant, and rendered by its own presenter, for one
     * reason: it is the only group here whose display is gated on something
     * other than "is the value populated". {@see MlsContactsPresenter} applies
     * {@see MlsDisplayPermissions} before any of it reaches a page, and a
     * listing the feed has withdrawn from IDX renders none of it.
     *
     * @var array<string, array<string,string>>
     */
    public const CONTACTS = [
        'Listing Agent / Brokerage' => [
            'ListAgentFullName'                  => 'Listing Agent',
            'ListAgentFirstName'                 => 'Listing Agent First Name',
            'ListAgentLastName'                  => 'Listing Agent Last Name',
            'ListAgentMlsId'                     => 'Agent MLS ID',
            'ListAgentEmail'                     => 'Agent Email',
            'ListAgentPreferredPhone'            => 'Agent Phone',
            'ListAgentDirectPhone'               => 'Agent Direct Phone',
            'ListAgentOfficePhone'               => 'Agent Office Phone',
            'ListAgentMobilePhone'               => 'Agent Mobile',
            'ListAgentURL'                       => 'Agent Website',
            'ListAgentStateLicense'              => 'Agent Licence',
            'ListAgentAOR'                       => 'Agent Board of REALTORS®',
            'ListTeamName'                       => 'Team',
            'ListOfficeName'                     => 'Brokerage',
            'ListOfficePhone'                    => 'Brokerage Phone',
            'ListOfficeMlsId'                    => 'Brokerage MLS ID',
            'ListOfficeURL'                      => 'Brokerage Website',
            'ListOfficeEmail'                    => 'Brokerage Email',
            'ListOfficeFax'                      => 'Brokerage Fax',
            'ListOfficeAddress1'                 => 'Brokerage Address',
            'ListOfficeCity'                     => 'Brokerage City',
            'ListOfficeStateOrProvince'          => 'Brokerage State',
            'ListOfficePostalCode'               => 'Brokerage Postal Code',
            'STELLAR_ListOfficeContactPreferred' => 'Preferred Brokerage Contact',
            'ListAOR'                            => 'Listing Board of REALTORS®',
            'CoListAgentFullName'                => 'Co-Listing Agent',
            'CoListAgentFirstName'               => 'Co-Listing Agent First Name',
            'CoListAgentLastName'                => 'Co-Listing Agent Last Name',
            'CoListAgentMlsId'                   => 'Co-Listing Agent MLS ID',
            'CoListAgentEmail'                   => 'Co-Listing Agent Email',
            'CoListAgentPreferredPhone'          => 'Co-Listing Agent Phone',
            'CoListAgentDirectPhone'             => 'Co-Listing Agent Direct Phone',
            'CoListAgentStateLicense'            => 'Co-Listing Agent Licence',
            'CoListOfficeName'                   => 'Co-Listing Brokerage',
            'CoListOfficeMlsId'                  => 'Co-Listing Brokerage MLS ID',
            'CoListOfficePhone'                  => 'Co-Listing Brokerage Phone',
        ],

        'HOA / Management Contact' => [
            'AssociationName'                => 'Association',
            'AssociationPhone'               => 'Association Phone',
            'AssociationName2'               => 'Second Association',
            'AssociationPhone2'              => 'Second Association Phone',
            'STELLAR_AssociationEmail'       => 'Association Email',
            'STELLAR_AssociationURL'         => 'Association Website',
            'STELLAR_PropertyManager'        => 'Property Manager',
            'STELLAR_PropertyManagerPhone'   => 'Property Manager Phone',
        ],
    ];

    /** Contact fields rendered as a mailto/tel/href rather than as plain text. */
    public const CONTACT_LINK_FIELDS = [
        'ListAgentEmail'            => 'mailto',
        'ListOfficeEmail'           => 'mailto',
        'CoListAgentEmail'          => 'mailto',
        'STELLAR_AssociationEmail'  => 'mailto',
        'ListAgentURL'              => 'url',
        'ListOfficeURL'             => 'url',
        'STELLAR_AssociationURL'    => 'url',
    ];

    // =========================================================================
    // TIER 3 — fields on the RELATED resources
    // =========================================================================

    /**
     * `Member` fields that may be shown, as label => field.
     *
     * A live probe on 2026-09-04 confirmed this dataset exposes Member with 79
     * fields. These are the ones the Property resource does NOT carry and a
     * consumer legitimately wants: the ways to reach the listing agent, and the
     * licence that says they may act as one.
     *
     * Everything else on Member is deliberately absent — login ids, MLS access
     * flags, RETS security classes, roster bookkeeping, assistant links. Those
     * describe the agent's relationship with the MLS, not their availability to
     * a buyer, and several would leak the MLS's own internals.
     *
     * @var array<string,string>
     */
    public const MEMBER_FIELDS = [
        'MemberFullName'           => 'Listing Agent',
        'JobTitle'                 => 'Title',
        'MemberDesignation'        => 'Designations',
        'MemberDirectPhone'        => 'Direct Phone',
        'MemberOfficePhone'        => 'Office Phone',
        'MemberTollFreePhone'      => 'Toll-Free Phone',
        'MemberEmail'              => 'Email',
        'SocialMediaWebsiteUrlOrId' => 'Website',
        'MemberStateLicense'       => 'State Licence',
        'MemberStateLicenseState'  => 'Licence State',
        'MemberLanguages'          => 'Languages',
        'MemberAOR'                => 'Board of REALTORS®',
        'OfficeName'               => 'Brokerage',
    ];

    /**
     * `Office` fields that may be shown, as field => label.
     *
     * Brokerage attribution and how to reach the brokerage. IDX display rules
     * generally require the listing brokerage to be named on a displayed
     * listing; an address and a phone number are the useful form of that.
     *
     * @var array<string,string>
     */
    public const OFFICE_FIELDS = [
        'OfficeName'                => 'Brokerage',
        'STELLAR_OfficeLongName'    => 'Brokerage (Full Name)',
        'FranchiseAffiliation'      => 'Franchise',
        'OfficePhone'               => 'Brokerage Phone',
        'OfficeFax'                 => 'Brokerage Fax',
        'OfficeEmail'               => 'Brokerage Email',
        'SocialMediaWebsiteUrlOrId' => 'Brokerage Website',
        'OfficeAddress1'            => 'Brokerage Address',
        'OfficeAddress2'            => 'Brokerage Address (cont.)',
        'OfficeCity'                => 'Brokerage City',
        'OfficeStateOrProvince'     => 'Brokerage State',
        'OfficePostalCode'          => 'Brokerage Postal Code',
        'OfficeAOR'                 => 'Brokerage Board of REALTORS®',
    ];

    /**
     * `OpenHouse` fields that may be shown, as field => label.
     *
     * `STELLAR_OpenHouseCount` is populated on Property and the rows are not, so
     * before this the listing could say an open house existed and nothing more.
     *
     * The showing agent's name and MLS id are on this resource and are
     * deliberately NOT here: who is standing in the house on Saturday is
     * staffing information, and the listing already attributes the agent and
     * brokerage responsible for it.
     *
     * @var array<string,string>
     */
    public const OPEN_HOUSE_FIELDS = [
        'OpenHouseDate'          => 'Date',
        'OpenHouseStartTime'     => 'Starts',
        'OpenHouseEndTime'       => 'Ends',
        'OpenHouseType'          => 'Type',
        'OpenHouseMethod'        => 'Method',
        'OpenHouseStatus'        => 'Status',
        'AppointmentRequiredYN'  => 'Appointment Required',
        'OpenHouseRemarks'       => 'Notes',
        'VirtualOpenHouseURL'    => 'Virtual Open House',
        'LivestreamOpenHouseURL' => 'Livestream',
    ];

    /** Open-house fields rendered as a link rather than as text. */
    public const OPEN_HOUSE_URL_FIELDS = ['VirtualOpenHouseURL', 'LivestreamOpenHouseURL'];

    /**
     * Related resources this dataset does NOT expose, and what was tried.
     *
     * Recorded so nobody re-derives the finding, and so nobody fakes it: a
     * `Room` section built from `RoomsTotal`, or a `Unit` roster built from
     * `NumberOfUnitsTotal`, would be inventing rows the MLS never sent.
     *
     * @var array<string,string>
     */
    public const UNAVAILABLE_RESOURCES = [
        'Room' => 'HTTP 404 on this dataset (probed 2026-09-04). RoomsTotal is a count, not a roster — do not synthesise rooms from it.',
        'Unit' => 'HTTP 404 on this dataset (probed 2026-09-04). NumberOfUnitsTotal is a count, not a roster — do not synthesise units from it.',
    ];

    // =========================================================================
    // TIER 3 — related Bridge resources
    // =========================================================================

    /** Bridge field on Property => the resource it belongs to. */
    public const RELATED_RESOURCE = [
        'Media'                        => 'media',
        'OpenHouse'                    => 'open_house',
        'Rooms'                        => 'rooms',
        'Units'                        => 'units',
        'STELLAR_OpenHouseCount'       => 'open_house',
        'STELLAR_ActiveOpenHouseCount' => 'open_house',
        'DocumentsCount'               => 'documents',
        'STELLAR_TotalDocumentsCount'  => 'documents',
    ];

    // =========================================================================
    // Display controls, address components
    // =========================================================================

    /** The feed's own permission flags. Read by MlsDisplayPermissions. */
    public const DISPLAY_CONTROL = [
        'IDXParticipationYN'                  => 'IDX participation',
        'InternetEntireListingDisplayYN'      => 'whole-listing internet display',
        'InternetAddressDisplayYN'            => 'address internet display',
        'InternetAutomatedValuationDisplayYN' => 'automated valuation display',
        'InternetConsumerCommentYN'           => 'consumer comment display',
        'FeedTypes'                           => 'which feeds carry this listing',
        'STELLAR_OfficeIDXOfficeParticipationYN' => 'office-level IDX participation',
        'STELLAR_OfficeSyndicateTo'           => 'syndication destinations the broker authorised',
        'STELLAR_ThirdPartyYN'                => 'third-party display authorisation',
    ];

    /**
     * Address components.
     *
     * Imported as facts, and suppressed at RENDER time when
     * `InternetAddressDisplayYN` is false. Preserved either way — the coordinate
     * ladder and matching both need them regardless of what may be printed.
     */
    public const ADDRESS_COMPONENT = [
        'UnparsedAddress', 'STELLAR_UnparsedAddress', 'StreetNumber', 'StreetNumberNumeric',
        'StreetName', 'StreetSuffix', 'StreetSuffixModifier', 'StreetDirPrefix', 'StreetDirSuffix',
        'StreetAdditionalInfo', 'UnitNumber', 'City', 'StateOrProvince', 'PostalCode',
        'PostalCodePlus4', 'CountyOrParish', 'Country', 'CrossStreet', 'Directions',
        'Latitude', 'Longitude', 'Coordinates', 'ParcelNumber', 'TaxLegalDescription',
        'STELLAR_AlternateKeyFolioNum', 'STELLAR_UniversalPropertyId',
        'PublicSurveySection', 'PublicSurveyTownship', 'PublicSurveyRange',
        'STELLAR_CensusTract', 'STELLAR_CensusBlock', 'STELLAR_SubdivisionNum',
        'STELLAR_SWSubdivCondoNum', 'STELLAR_SubdivisionSectionNumber', 'ParkName',
        'MaloneId',
    ];

    // =========================================================================
    // Withheld
    // =========================================================================

    /**
     * Populated, preserved in raw_json, and deliberately never rendered.
     *
     * @var array<string,string>
     */
    public const RESTRICTED = [
        // Authored prose. The locked 2026-07-05 owner decision names remarks
        // reuse alongside photo reuse as the licensing risk.
        'PublicRemarks'                  => 'authored marketing prose — licensing',
        'STELLAR_PublicRemarksAgent'     => 'authored marketing prose — licensing',
        'STELLAR_PublicRemarksRequired'  => 'authored marketing prose — licensing',
        'STELLAR_PublicRemarksSpanishReq' => 'authored marketing prose — licensing',
        'SyndicationRemarks'             => 'authored marketing prose — licensing',
        'PrivateRemarks'                 => 'not public',
        'STELLAR_SoldRemarks'            => 'not public',

        // Access and personal safety.
        'ShowingInstructions'            => 'access instructions',
        'STELLAR_ShowingRequirements'    => 'access instructions',
        'STELLAR_ShowingConsiderations'  => 'access instructions',
        'STELLAR_ShowingTime'            => 'access instructions',
        'LockBoxType'                    => 'access instructions',
        'LockBoxLocation'                => 'access instructions',
        'LockBoxSerialNumber'            => 'access instructions',
        'STELLAR_CallCenterPhoneNumber'  => 'showing call centre — access routing',
        'STELLAR_AuctionPropAccessYN'    => 'access instructions',
        'STELLAR_TenantName'             => 'occupant identity',
        'STELLAR_TenantPhone'            => 'occupant contact',
        'STELLAR_RealtorInfoConfidential' => 'explicitly confidential to REALTORS®',

        // The other side of the transaction. Not our listing's representation,
        // and on a sold record it names the buyer's agent.
        'BuyerAgentFullName'             => 'counterparty agent identity',
        'BuyerAgentFirstName'            => 'counterparty agent identity',
        'BuyerAgentLastName'             => 'counterparty agent identity',
        'BuyerAgentMlsId'                => 'counterparty agent identity',
        'BuyerAgentKeyNumeric'           => 'counterparty agent identity',
        'BuyerAgentStateLicense'         => 'counterparty agent identity',
        'BuyerAgentAOR'                  => 'counterparty agent identity',
        'BuyerAgentEmail'                => 'counterparty contact information',
        'BuyerAgentPreferredPhone'       => 'counterparty contact information',
        'BuyerOfficeName'                => 'counterparty brokerage identity',
        'BuyerOfficeMlsId'               => 'counterparty brokerage identity',
        'BuyerOfficeKeyNumeric'          => 'counterparty brokerage identity',
        'BuyerOfficePhone'               => 'counterparty contact information',
        'BuyerTeamName'                  => 'counterparty team identity',
        'CoBuyerAgentFullName'           => 'counterparty agent identity',
        'CoBuyerAgentFirstName'          => 'counterparty agent identity',
        'CoBuyerAgentLastName'           => 'counterparty agent identity',
        'CoBuyerAgentMlsId'              => 'counterparty agent identity',
        'CoBuyerAgentKeyNumeric'         => 'counterparty agent identity',
        'CoBuyerAgentStateLicense'       => 'counterparty agent identity',
        'CoBuyerOfficeName'              => 'counterparty brokerage identity',
        'CoBuyerOfficeMlsId'             => 'counterparty brokerage identity',
        'CoBuyerOfficeKeyNumeric'        => 'counterparty brokerage identity',
        'STELLAR_BuyersCountryofResidence' => 'buyer personal information',
        'STELLAR_BuyersZipCode'          => 'buyer personal information',
        'STELLAR_BuyersIntendedUse'      => 'buyer personal information',
        'STELLAR_BuyersPremium'          => 'auction buyer premium — transaction term',

        // Escrow / title. Named individuals with direct contact details.
        'STELLAR_EscrowAgentName'        => 'escrow agent identity',
        'STELLAR_EscrowAgentEmail'       => 'contact information',
        'STELLAR_EscrowAgentPhone'       => 'contact information',
        'STELLAR_EscrowAgentFax'         => 'contact information',
        'STELLAR_EscrowCompany'          => 'escrow provider — transaction party',
        'STELLAR_EscrowStreetNumber'     => 'escrow provider address',
        'STELLAR_EscrowStreetName'       => 'escrow provider address',
        'STELLAR_EscrowCity'             => 'escrow provider address',
        'STELLAR_EscrowState'            => 'escrow provider address',
        'STELLAR_EscrowPostalCode'       => 'escrow provider address',

        // Compensation between brokerages is separately regulated and, as of
        // this feed, is not even transmitted.
        'BuyerAgencyCompensation'        => 'broker compensation',
        'SubAgencyCompensation'          => 'broker compensation',
        'TransactionBrokerCompensation'  => 'broker compensation',

        // Agency posture is a statement about the transaction's representation,
        // not a property fact, and is easily misread by a consumer.
        'STELLAR_SellerRepresentation'   => 'agency representation posture',
        'STELLAR_Representation'         => 'agency representation posture',

        // Closed-transaction economics.
        'ClosePrice'                     => 'closed transaction economics',
        'STELLAR_ClosePriceByCalculatedSqFt' => 'closed transaction economics',
        'STELLAR_ClosePriceByCalculatedListPriceRatio' => 'closed transaction economics',
        'STELLAR_RATIO_ClosePrice_By_ListPrice' => 'closed transaction economics',
        'STELLAR_RATIO_ClosePrice_By_OriginalListPrice' => 'closed transaction economics',
        'STELLAR_DaysToClosed'           => 'closed transaction economics',
        'STELLAR_DaystoContract'         => 'closed transaction economics',
        'STELLAR_ExpectedClosingDate'    => 'pending transaction detail',
        'STELLAR_ContractStatus'         => 'pending transaction detail',
        'STELLAR_ConditionExpDate'       => 'pending transaction detail',
        'STELLAR_TempOffMarketDate'      => 'listing-side operational detail',

        // Third-party programmes carrying someone else's branding or terms.
        'VirtualTourURLBranded'          => 'carries listing brokerage branding',
        'STELLAR_VirtualTourURLBranded2' => 'carries listing brokerage branding',
        'VirtualTourURLZillow'           => 'third-party portal branding',
        'STELLAR_RentSpreeURL'           => 'third-party application funnel',
        'STELLAR_RentSpreeYN'            => 'third-party application funnel',
        'STELLAR_DPRURL'                 => 'third-party down-payment programme funnel',
        'STELLAR_DPRURL2'                => 'third-party down-payment programme funnel',
        'STELLAR_DPRYN'                  => 'third-party down-payment programme funnel',
        'STELLAR_FCHRURLYN'              => 'third-party programme funnel',
        'STELLAR_AuctionFirmURL'         => 'third-party auction funnel',
        'STELLAR_AuctionTime'            => 'third-party auction logistics',
        'STELLAR_AuctionType'            => 'third-party auction logistics',
        'STELLAR_GiftedDonated'          => 'seller circumstance, not a property fact',
    ];

    /**
     * Provenance, keys, sync bookkeeping and internal ratios.
     *
     * Preserved in raw_json and used internally. Never rendered: meaningless to
     * a consumer, and several of them leak the shape of our integration.
     *
     * @var array<string,string>
     */
    public const INTERNAL = [
        '@odata.id'                                => 'OData entity URL',
        'ListingKeyNumeric'                        => 'provider key',
        'OriginatingSystemKey'                     => 'provider key',
        'SourceSystemKey'                          => 'provider key',
        'SourceSystemName'                         => 'provider key',
        'STELLAR_ListOfficeHeadOfficeKeyNumeric'   => 'provider key',
        'CoListAgentKeyNumeric'                    => 'provider key',
        'CoListOfficeKeyNumeric'                   => 'provider key',
        'ListAgentKey'                             => 'provider key — opaque 32-hex handle, meaningless to a consumer',
        'ListOfficeKey'                            => 'provider key — opaque 32-hex handle, meaningless to a consumer',
        'CoListAgentKey'                           => 'provider key',
        'CoListOfficeKey'                          => 'provider key',
        'ListAgentKeyNumeric'                      => 'provider key',
        'ListOfficeKeyNumeric'                     => 'provider key',
        'ListTeamKey'                              => 'provider key',
        'BridgeModificationTimestamp'              => 'Bridge sync bookkeeping',
        'OriginalEntryTimestamp'                   => 'MLS entry bookkeeping',
        'ContractStatusChangeDate'                 => 'MLS status bookkeeping',
        'ContractStatusChangeTimestamp'            => 'MLS status bookkeeping',
        'MajorChangeTimestamp'                     => 'MLS change bookkeeping',
        'MajorChangeType'                          => 'MLS change bookkeeping',
        'OffMarketTimestamp'                       => 'MLS status bookkeeping',
        'STELLAR_OriginatingSystemTimestamp'       => 'MLS sync bookkeeping',
        'STELLAR_RETSUpdateTransactionYN'          => 'MLS sync bookkeeping',
        'STELLAR_MatrixTesting'                    => 'MLS test-record marker',
        'STELLAR_ListSource'                       => 'MLS data-entry provenance',
        'STELLAR_ListSourceOriginal'               => 'MLS data-entry provenance',
        'STELLAR_RegionalAOR'                      => 'MLS board routing',
        'ApprovalStatus'                           => 'MLS internal workflow state',
        'STELLAR_CreateAutomaticVirtualTourYN'     => 'MLS tooling flag',
        'STELLAR_GreenVerificationCount'           => 'count with no detail rows in this feed',
        'STELLAR_GreenEnergyGenerationYN'          => 'superseded by GreenEnergyGeneration',
        'STELLAR_TotalPhotosCount'                 => 'duplicate of PhotosCount',
        'STELLAR_CurrentPrice'                     => 'duplicate of ListPrice',
        'STELLAR_CalculatedListPriceByCalculatedSqFt' => 'derived price ratio',
        'STELLAR_RATIO_CurrentPrice_By_CalculatedSqFt' => 'derived price ratio',
        'STELLAR_RATIO_CurrentPrice_By_BuildingAreaTotal' => 'derived price ratio',
        'STELLAR_PricePerAcre'                     => 'derived price ratio',
        'STELLAR_CountyLandUseCode'                => 'assessor code without a label in this feed',
        'STELLAR_CountyPropertyUseCode'            => 'assessor code without a label in this feed',
        'STELLAR_StateLandUseCode'                 => 'assessor code without a label in this feed',
        'STELLAR_StatePropertyUseCode'             => 'assessor code without a label in this feed',
        'CopyrightNotice'                          => 'feed licensing notice, rendered by the attribution block',
        'License1'                                 => 'MLS licensing bookkeeping',
        'License2'                                 => 'MLS licensing bookkeeping',
        'License3'                                 => 'MLS licensing bookkeeping',
        'DOH1'                                     => 'Department of Health permit bookkeeping',
        'DOH2'                                     => 'Department of Health permit bookkeeping',
        'DOH3'                                     => 'Department of Health permit bookkeeping',
        'SerialU'                                  => 'manufactured-home serial bookkeeping',
        'SerialX'                                  => 'manufactured-home serial bookkeeping',
        'SerialXX'                                 => 'manufactured-home serial bookkeeping',
        'Telephone'                                => 'unlabelled legacy contact column',
    ];

    /**
     * The same fact as something already displayed, in another unit or spelling.
     *
     * Kept out of the display layer so a listing does not print "Lot Size 34,791
     * sq ft" immediately above "Lot Size Area 34,791".
     *
     * @var array<string,string>
     */
    public const DERIVED = [
        'BathroomsTotalDecimal'  => 'BathroomsTotalInteger plus BathroomsHalf already shown',
        'BathroomsTotalInteger'  => 'imported to the bathrooms field; shown there',
        'LotSizeSquareFeet'      => 'imported to the lot size field; shown there',
        'LivingArea'             => 'imported to the heated square feet field; shown there',
        'BuildingAreaTotal'      => 'imported to the building size field; shown there',
        'BedroomsTotal'          => 'imported to the bedrooms field; shown there',
        'YearBuilt'              => 'imported to the year built field; shown there',
        'ListPrice'              => 'imported to the price field; shown there',
        'PropertyType'           => 'imported to the property type field; shown there',
        'LotSizeAcres'           => 'imported to the acreage field; shown there',
        'LotSizeDimensions'      => 'imported to the lot dimensions field; shown there',
        'Zoning'                 => 'imported to the zoning field; shown there',
        'TaxYear'                => 'imported to the tax year field; shown there',
        'STELLAR_FloodZoneCode'  => 'imported to the flood zone field; shown there',
        'STELLAR_FloodZonePanel' => 'imported to the flood zone panel field; shown there',
        'STELLAR_FloodZoneDate'  => 'imported to the flood zone date field; shown there',
        'AdditionalParcelsYN'    => 'imported to the additional parcels field; shown there',
        'LivingAreaSource'       => 'imported to the square-footage source field; shown there',
        'STELLAR_CDDYN'          => 'imported to the CDD field; shown there',
    ];

    /**
     * Recognised, knowingly unhandled, with a reason.
     *
     * EMPTY, AND THAT IS THE RESULT RATHER THAN AN OVERSIGHT.
     * Across seven property-type fixtures carrying ~200 populated fields each,
     * every populated Bridge Property field now resolves to one of the ten
     * dispositions above. Nothing is parked in a "we do not handle this" bucket.
     *
     * The constant stays because the no-drop contract needs somewhere honest to
     * put a future field that genuinely cannot be handled — a column whose
     * meaning nobody can establish, say — and because a generic bucket with no
     * reason attached is exactly what this catalog exists to prevent. An entry
     * added here must carry a sentence saying why.
     *
     * @var array<string,string>
     */
    public const UNSUPPORTED = [];

    // =========================================================================
    // Classification
    // =========================================================================

    public const D_TIER1        = 'tier1_byo';
    public const D_FACTS        = 'property_facts';
    public const D_CONTEXT      = 'listing_context';
    public const D_CONTACTS     = 'contacts';
    public const D_RELATED      = 'related_resource';
    public const D_DISPLAY_CTL  = 'display_control';
    public const D_ADDRESS      = 'address_component';
    public const D_INTERNAL     = 'internal';
    public const D_RESTRICTED   = 'restricted';
    public const D_DERIVED      = 'derived';
    public const D_UNSUPPORTED  = 'unsupported';
    public const D_UNKNOWN      = 'UNCLASSIFIED';

    /**
     * The single disposition for one Bridge field.
     *
     * Order matters and encodes precedence. A restricted field is restricted
     * even if it also looks like an address component; a Tier-1 destination
     * beats a display grouping, because "it reached an editable field" is the
     * stronger statement.
     */
    public static function classify(string $field): string
    {
        if (isset(self::RESTRICTED[$field]))       { return self::D_RESTRICTED; }
        if (isset(self::DISPLAY_CONTROL[$field]))  { return self::D_DISPLAY_CTL; }
        if (isset(self::RELATED_RESOURCE[$field])) { return self::D_RELATED; }
        if (isset(self::TIER1_BYO[$field]))        { return self::D_TIER1; }
        if (self::inGroups(self::PROPERTY_FACTS, $field))   { return self::D_FACTS; }
        if (self::inGroups(self::LISTING_CONTEXT, $field))  { return self::D_CONTEXT; }
        if (self::inGroups(self::CONTACTS, $field))         { return self::D_CONTACTS; }
        if (in_array($field, self::ADDRESS_COMPONENT, true)){ return self::D_ADDRESS; }
        if (isset(self::INTERNAL[$field]))         { return self::D_INTERNAL; }
        if (isset(self::DERIVED[$field]))          { return self::D_DERIVED; }
        if (isset(self::UNSUPPORTED[$field]))      { return self::D_UNSUPPORTED; }

        return self::D_UNKNOWN;
    }

    /**
     * Why a field is not on a listing page, in one sentence, or null when it is.
     */
    public static function withheldReason(string $field): ?string
    {
        return match (self::classify($field)) {
            self::D_RESTRICTED  => self::RESTRICTED[$field],
            self::D_INTERNAL    => self::INTERNAL[$field],
            self::D_DERIVED     => self::DERIVED[$field],
            self::D_UNSUPPORTED => self::UNSUPPORTED[$field],
            self::D_DISPLAY_CTL => 'feed display control: ' . self::DISPLAY_CONTROL[$field],
            self::D_RELATED     => 'belongs to the ' . self::RELATED_RESOURCE[$field] . ' resource',
            default             => null,
        };
    }

    /** Every field this catalog knows about, in any disposition. */
    public static function allKnownFields(): array
    {
        $fields = array_merge(
            array_keys(self::TIER1_BYO),
            array_keys(self::RESTRICTED),
            array_keys(self::INTERNAL),
            array_keys(self::DERIVED),
            array_keys(self::UNSUPPORTED),
            array_keys(self::DISPLAY_CONTROL),
            array_keys(self::RELATED_RESOURCE),
            self::ADDRESS_COMPONENT,
        );

        foreach ([self::PROPERTY_FACTS, self::LISTING_CONTEXT, self::CONTACTS] as $groups) {
            foreach ($groups as $fieldsInSection) {
                $fields = array_merge($fields, array_keys($fieldsInSection));
            }
        }

        return array_values(array_unique($fields));
    }

    /** @param array<string,array<string,string>> $groups */
    private static function inGroups(array $groups, string $field): bool
    {
        foreach ($groups as $fields) {
            if (array_key_exists($field, $fields)) {
                return true;
            }
        }

        return false;
    }
}
