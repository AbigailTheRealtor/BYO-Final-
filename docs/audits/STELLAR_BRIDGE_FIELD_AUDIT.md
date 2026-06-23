# Stellar Bridge API Field Audit

> Generated: 2026-06-15 23:57:54 UTC  
> Sample size: **25** record(s) fetched (requested 25)  
> Dataset: `stellar`  
> Command: `php artisan bridge:audit-fields --limit=25`

---

## Executive Summary

- **Total unique field keys found:** 553
- **Reliably populated (≥50 % of records):** 217
- **Sparsely populated (<50 %):** 44
- **Always empty / null in this sample:** 292
- **Compliance-sensitive fields (excluded from matching):** 23

> **Note on compliance fields:** Fields flagged as `Compliance/Restricted` contain personal contact
> information (phone numbers, email addresses), agent license numbers, lockbox/access details, or
> private showing instructions. These must not be used in matching, Ask AI context, or any
> public-facing feature without legal review. Example values for these fields are redacted below.

---

## Commercial Lease PropertyType — Confirmed Addendum

> Added: 2026-06-23 (post-initial-audit supplement)

The original audit sampled 25 records with no PropertyType filter, all of which were
`Residential` sales. The following confirmation was obtained by a targeted live import:

**Command run:** `php artisan bridge:import-properties --property-type="Commercial Lease" --limit=1`  
**Result:** 1 record imported; confirmed fields on the live record:

| Field | Value |
|---|---|
| `PropertyType` | `Commercial Lease` |
| `StandardStatus` | `Closed` |
| `ListPrice` | `475` (monthly rent — this IS the rent amount for commercial lease listings) |
| `LeaseTerm` | `24 Months` |
| `City` | `OCALA` |

**Key findings for commercial lease matching:**
- `PropertyType = 'Commercial Lease'` is the exact OData filter string for commercial rentals.
- `ListPrice` is populated and represents monthly rent — the existing `list_price ≤ max_price` query filter is therefore correct for commercial lease tenant matching with no bypass needed.
- `LeaseTerm` is populated in raw_json (e.g. `"24 Months"`) and is used by `BuyerMatchScorer::scoreLeaseTermPreference()` for lease-duration alignment scoring.
- Commercial lease listings do NOT carry `SeniorCommunityYN = true` — the `is_55_plus_eligible = false` gate is safe for all commercial lease tenants.

---

## Matching Question Answers

| Question | Available? | Fields |
|---|---|---|
| City / County / ZIP | ✅ Yes | `City`, `CountyOrParish`, `PostalCode`, `StateOrProvince` |
| List price / rent amount | ✅ Yes | `ListPrice`, `LeaseAmountFrequency`, `OriginalListPrice` |
| Beds / baths / sqft | ✅ Yes | `BedroomsTotal`, `BathroomsTotalInteger`, `BathroomsFull`, `BathroomsHalf`, `LivingArea` |
| Property type / subtype | ✅ Yes | `PropertyType`, `PropertySubType` |
| Amenities (pool, garage, waterfront, view) | ✅ Yes | `PoolPrivateYN`, `GarageYN`, `WaterfrontYN`, `ViewYN`, `View`, `CommunityFeatures`, `AssociationAmenities` |
| HOA / CDD / flood zone | ✅ Yes | `AssociationYN`, `AssociationFee` |
| Rental vs sale separation | ✅ Yes | `LeaseConsideredYN`, `StandardStatus`, `MlsStatus`, `PropertyType` |
| Pet policy | ✅ Yes | `PetsAllowed` |
| Year built / condition | ✅ Yes | `YearBuilt`, `PropertyCondition`, `NewConstructionYN` |
| Lot size | ✅ Yes | `LotSizeAcres`, `LotSizeSquareFeet`, `LotSizeArea` |
| Zoning | ✅ Yes | `Zoning`, `ZoningDescription` |
| Commercial lease duration | ✅ Yes (raw_json) | `LeaseTerm` (e.g. `"24 Months"`, `"Month-to-Month"`) |

**Gaps requiring manual input or supplemental data source:** `SchoolDistrict`, `SchoolElementary`, `SchoolMiddleOrJunior`, `SchoolHigh`, `FloodZone`, `FloodZoneCode`, `WalkScore`, `TransitScore`

---

## Full Field Inventory

| Field Key | Category | Data Type | Population | Matching Useful? | Example Value |
|---|---|---|---|---|---|
| `@odata.id` | Unknown | string | 25/25 | No | `https://api.bridgedataoutput.com/api/v2/OData/stellar/Property('25b3e4` |
| `AccessibilityFeatures` | Interior Features | null | 0/25 | No | — |
| `AdditionalParcelsDescription` | Unknown | null | 0/25 | No | — |
| `AdditionalParcelsYN` | Unknown | boolean | 25/25 | No | false |
| `Appliances` | Interior Features | array | 25/25 | Yes | `[\"Dishwasher\",\"Disposal\"]` |
| `ApprovalStatus` | Unknown | null | 0/25 | No | — |
| `ArchitecturalStyle` | Exterior/Lot | array | 15/25 | Yes | `[\"Mediterranean\"]` |
| `AssociationAmenities` | HOA/Fees | array | 18/25 | Yes | `[\"Maintenance\",\"Park\"]` |
| `AssociationFee` | Price | integer | 22/25 | Yes | `60` |
| `AssociationFee2` | Price | null | 0/25 | No | — |
| `AssociationFee2Frequency` | Price | null | 0/25 | No | — |
| `AssociationFeeFrequency` | Price | string | 23/25 | No | `Monthly` |
| `AssociationFeeIncludes` | HOA/Fees | array | 6/25 | No | `[\"Cable TV\",\"Escrow Reserves Fund\"]` |
| `AssociationName` | HOA/Fees | null | 0/25 | No | — |
| `AssociationName2` | HOA/Fees | null | 0/25 | No | — |
| `AssociationPhone` | HOA/Fees | null | 0/25 | No | — |
| `AssociationPhone2` | HOA/Fees | null | 0/25 | No | — |
| `AssociationYN` | HOA/Fees | boolean | 25/25 | Yes | true |
| `AttachedGarageYN` | Exterior/Lot | boolean | 25/25 | No | true |
| `AvailabilityDate` | Lease/Rental | null | 0/25 | No | — |
| `Basement` | Interior Features | null | 0/25 | No | — |
| `BathroomsFull` | Interior Features | integer | 25/25 | Yes | `4` |
| `BathroomsHalf` | Interior Features | integer | 25/25 | Yes | `1` |
| `BathroomsOneQuarter` | Interior Features | null | 0/25 | No | — |
| `BathroomsPartial` | Unknown | null | 0/25 | No | — |
| `BathroomsThreeQuarter` | Interior Features | null | 0/25 | No | — |
| `BathroomsTotalDecimal` | Interior Features | float | 25/25 | No | `4.5` |
| `BathroomsTotalInteger` | Interior Features | integer | 25/25 | Yes | `5` |
| `BedroomsTotal` | Interior Features | integer | 25/25 | Yes | `6` |
| `BodyType` | Unknown | null | 0/25 | No | — |
| `BridgeModificationTimestamp` | Property Basics | datetime | 25/25 | No | `2021-12-08T10:24:53.428Z` |
| `BuilderModel` | Unknown | null | 0/25 | No | — |
| `BuilderName` | Unknown | null | 0/25 | No | — |
| `BuildingAreaSource` | Unknown | string | 25/25 | No | `Builder` |
| `BuildingAreaTotal` | Interior Features | integer | 9/25 | No | `4192` |
| `BuildingAreaUnits` | Interior Features | string | 9/25 | No | `Square Feet` |
| `BuildingFeatures` | Unknown | null | 0/25 | No | — |
| `BusinessName` | Unknown | null | 0/25 | No | — |
| `BusinessType` | Unknown | null | 0/25 | No | — |
| `BuyerAgentAOR` | Unknown | string | 25/25 | No | `Sarasota - Manatee` |
| `BuyerAgentFirstName` | Unknown | null | 0/25 | No | — |
| `BuyerAgentFullName` | Agent/Brokerage | string | 25/25 | No | `ABEL JIMENEZ` |
| `BuyerAgentKeyNumeric` | Agent/Brokerage | integer | 25/25 | No | `1127616` |
| `BuyerAgentLastName` | Unknown | null | 0/25 | No | — |
| `BuyerAgentMlsId` | Unknown | numeric string | 25/25 | No | `281523369` |
| `BuyerAgentStateLicense` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `BuyerOfficeKeyNumeric` | Unknown | integer | 25/25 | No | `1047515` |
| `BuyerOfficeMlsId` | Unknown | numeric string | 25/25 | No | `281519470` |
| `BuyerOfficeName` | Agent/Brokerage | string | 25/25 | No | `IMPULSE REALTY INTERNATIONAL` |
| `BuyerTeamName` | Unknown | null | 0/25 | No | — |
| `CapRate` | Financial/Investment | null | 0/25 | Yes | — |
| `CarportSpaces` | Exterior/Lot | null | 0/25 | No | — |
| `CarportYN` | Exterior/Lot | null | 0/25 | No | — |
| `City` | Location | string | 25/25 | Yes | `ORLANDO` |
| `CloseDate` | Property Basics | datetime | 25/25 | No | `2014-06-13` |
| `ClosePrice` | Price | integer | 25/25 | No | `330000` |
| `CoBuyerAgentFirstName` | Unknown | null | 0/25 | No | — |
| `CoBuyerAgentFullName` | Agent/Brokerage | null | 0/25 | No | — |
| `CoBuyerAgentKeyNumeric` | Agent/Brokerage | null | 0/25 | No | — |
| `CoBuyerAgentLastName` | Unknown | null | 0/25 | No | — |
| `CoBuyerAgentMlsId` | Unknown | null | 0/25 | No | — |
| `CoBuyerAgentStateLicense` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `CoBuyerOfficeKeyNumeric` | Unknown | null | 0/25 | No | — |
| `CoBuyerOfficeMlsId` | Unknown | null | 0/25 | No | — |
| `CoBuyerOfficeName` | Agent/Brokerage | null | 0/25 | No | — |
| `CoListAgentFirstName` | Unknown | null | 0/25 | No | — |
| `CoListAgentFullName` | Agent/Brokerage | string | 12/25 | No | `DR HORTON INC` |
| `CoListAgentKeyNumeric` | Agent/Brokerage | integer | 1/25 | No | `1110092` |
| `CoListAgentLastName` | Unknown | null | 0/25 | No | — |
| `CoListAgentMlsId` | Agent/Brokerage | numeric string | 1/25 | No | `261548019` |
| `CoListAgentStateLicense` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `CoListOfficeKeyNumeric` | Agent/Brokerage | integer | 1/25 | No | `1057138` |
| `CoListOfficeMlsId` | Agent/Brokerage | numeric string | 1/25 | No | `779856` |
| `CoListOfficeName` | Agent/Brokerage | string | 2/25 | No | `DR HORTON INC` |
| `CommonWalls` | Unknown | null | 0/25 | No | — |
| `CommunityFeatures` | HOA/Fees | array | 23/25 | Yes | `[\"Association Recreation - Owned\",\"Deed Restrictions\"]` |
| `Concessions` | Unknown | string | 25/25 | No | `Yes` |
| `ConcessionsAmount` | Unknown | integer | 25/25 | No | `10000` |
| `ConstructionMaterials` | Exterior/Lot | array | 25/25 | Yes | `[\"Block\",\"Stone\"]` |
| `ContractStatusChangeDate` | Unknown | datetime | 25/25 | No | `2014-06-13` |
| `Cooling` | Interior Features | array | 25/25 | Yes | `[\"Central Air\",\"Humidity Control\"]` |
| `CoolingYN` | Interior Features | boolean | 25/25 | No | true |
| `Coordinates` | Unknown | array | 25/25 | No | `[-81.238126,28.348956]` |
| `CopyrightNotice` | Unknown | null | 0/25 | No | — |
| `Country` | Location | null | 0/25 | No | — |
| `CountyOrParish` | Location | string | 25/25 | Yes | `Orange` |
| `CoveredSpaces` | Unknown | integer | 25/25 | No | `3` |
| `CrossStreet` | Location | null | 0/25 | No | — |
| `CumulativeDaysOnMarket` | Property Basics | integer | 25/25 | No | `19` |
| `CurrentUse` | Unknown | null | 0/25 | No | — |
| `DOH1` | Unknown | null | 0/25 | No | — |
| `DOH2` | Unknown | null | 0/25 | No | — |
| `DOH3` | Unknown | null | 0/25 | No | — |
| `DaysOnMarket` | Property Basics | integer | 25/25 | No | `19` |
| `DirectionFaces` | Unknown | string | 18/25 | No | `North` |
| `Directions` | Location | string | 25/25 | No | `From Hwy 408 E exit 18B for FL 417-S toward Orlando International Airp` |
| `Disclosures` | Unknown | null | 0/25 | No | — |
| `DocumentsCount` | Media | integer | 25/25 | No | `0` |
| `DoorFeatures` | Unknown | null | 0/25 | No | — |
| `Electric` | Exterior/Lot | null | 0/25 | No | — |
| `ElementarySchool` | Unknown | string | 20/25 | No | `Northlake Park Community` |
| `Exclusions` | Unknown | null | 0/25 | No | — |
| `ExteriorFeatures` | Exterior/Lot | array | 22/25 | Yes | `[\"Sliding Doors\",\"Irrigation System\"]` |
| `FeedTypes` | Unknown | array | 25/25 | No | `[\"IDX\"]` |
| `Fencing` | Exterior/Lot | null | 0/25 | No | — |
| `FireplaceFeatures` | Interior Features | null | 0/25 | No | — |
| `FireplaceYN` | Interior Features | boolean | 25/25 | No | false |
| `FireplacesTotal` | Interior Features | null | 0/25 | No | — |
| `Flooring` | Interior Features | array | 25/25 | No | `[\"Carpet\",\"Ceramic Tile\"]` |
| `FoundationDetails` | Exterior/Lot | array | 25/25 | No | `[\"Slab\"]` |
| `FrontageLength` | Unknown | null | 0/25 | No | — |
| `Furnished` | Lease/Rental | string | 9/25 | Yes | `Unfurnished` |
| `GarageSpaces` | Exterior/Lot | integer | 25/25 | Yes | `3` |
| `GarageYN` | Exterior/Lot | boolean | 25/25 | Yes | true |
| `Gas` | Unknown | null | 0/25 | No | — |
| `GreenBuildingVerificationType` | Unknown | array | 24/25 | No | `[\"ENERGY STAR Certified Homes\"]` |
| `GreenEnergyEfficient` | Unknown | array | 23/25 | No | `[\"Appliances\",\"HVAC\"]` |
| `GreenEnergyGeneration` | Unknown | null | 0/25 | No | — |
| `GreenIndoorAirQuality` | Unknown | array | 15/25 | No | `[\"HVAC Filter MERV 8+\",\"No\\/Low VOC Flooring\"]` |
| `GreenLocation` | Unknown | null | 0/25 | No | — |
| `GreenSustainability` | Unknown | null | 0/25 | No | — |
| `GreenWaterConservation` | Unknown | array | 15/25 | No | `[\"Low-Flow Fixtures\"]` |
| `GrossIncome` | Unknown | null | 0/25 | No | — |
| `GrossScheduledIncome` | Financial/Investment | null | 0/25 | Yes | — |
| `Heating` | Interior Features | array | 25/25 | Yes | `[\"Central\",\"Heat Recovery Unit\"]` |
| `HeatingYN` | Interior Features | boolean | 25/25 | No | true |
| `HighSchool` | Unknown | string | 21/25 | No | `Lake Nona High` |
| `HighSchoolDistrict` | Unknown | null | 0/25 | No | — |
| `HomeWarrantyYN` | Unknown | boolean | 25/25 | No | true |
| `HorseAmenities` | Unknown | null | 0/25 | No | — |
| `IDXParticipationYN` | Unknown | boolean | 25/25 | No | true |
| `InteriorFeatures` | Interior Features | array | 24/25 | Yes | `[\"Eating Space In Kitchen\",\"High Ceilings\"]` |
| `InternetAddressDisplayYN` | Unknown | boolean | 25/25 | No | true |
| `InternetAutomatedValuationDisplayYN` | Unknown | boolean | 25/25 | No | true |
| `InternetConsumerCommentYN` | Unknown | boolean | 24/25 | No | true |
| `InternetEntireListingDisplayYN` | Unknown | boolean | 25/25 | No | true |
| `LandLeaseAmount` | Price | null | 0/25 | No | — |
| `LandLeaseYN` | Unknown | null | 0/25 | No | — |
| `Latitude` | Location | float | 25/25 | Yes | `28.348956` |
| `LaundryFeatures` | Interior Features | array | 13/25 | No | `[\"Inside\"]` |
| `LeaseAmountFrequency` | Price | null | 0/25 | No | — |
| `LeaseConsideredYN` | Lease/Rental | null | 0/25 | Yes | — |
| `LeaseTerm` | Lease/Rental | null | 0/25 | Yes | — |
| `Levels` | Interior Features | array | 25/25 | Yes | `[\"Two\"]` |
| `License1` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `License2` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `License3` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `ListAOR` | Unknown | string | 25/25 | No | `Orlando Regional` |
| `ListAgentAOR` | Unknown | string | 25/25 | No | `Orlando Regional` |
| `ListAgentEmail` | Compliance/Restricted | string | 25/25 | No (restricted) | `[REDACTED]` |
| `ListAgentFirstName` | Unknown | null | 0/25 | No | — |
| `ListAgentFullName` | Agent/Brokerage | string | 25/25 | No | `ROSANA ALMEIDA` |
| `ListAgentKey` | Agent/Brokerage | string | 25/25 | No | `cb8393e4f5fdc335d349c89df3d55895` |
| `ListAgentLastName` | Unknown | null | 0/25 | No | — |
| `ListAgentMlsId` | Agent/Brokerage | numeric string | 25/25 | No | `261207052` |
| `ListAgentPreferredPhone` | Compliance/Restricted | string | 25/25 | No (restricted) | `[REDACTED]` |
| `ListAgentStateLicense` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `ListOfficeKey` | Agent/Brokerage | string | 25/25 | No | `0ae704fada083ff21fd695616c321617` |
| `ListOfficeMlsId` | Agent/Brokerage | numeric string | 25/25 | No | `261011545` |
| `ListOfficeName` | Agent/Brokerage | string | 25/25 | No | `MERITAGE HOMES OF FL REALTY` |
| `ListOfficePhone` | Compliance/Restricted | string | 25/25 | No (restricted) | `[REDACTED]` |
| `ListPrice` | Price | integer | 25/25 | Yes | `360618` |
| `ListTeamName` | Unknown | string | 2/25 | No | `Sales Team` |
| `ListingContractDate` | Property Basics | datetime | 25/25 | No | `2014-03-13` |
| `ListingId` | Property Basics | string | 25/25 | No | `O5215314` |
| `ListingKey` | Property Basics | string | 25/25 | No | `25b3e48ee7095eed12688b25c10ea606` |
| `ListingKeyNumeric` | Property Basics | integer | 25/25 | No | `32455874` |
| `ListingTerms` | Unknown | array | 24/25 | No | `[\"Cash\",\"Conventional\"]` |
| `LivingArea` | Interior Features | integer | 25/25 | Yes | `3600` |
| `LivingAreaSource` | Interior Features | string | 25/25 | No | `Builder` |
| `LivingAreaUnits` | Interior Features | string | 25/25 | No | `Square Feet` |
| `LockBoxLocation` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `LockBoxSerialNumber` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `LockBoxType` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `Longitude` | Location | float | 25/25 | Yes | `-81.238126` |
| `LotFeatures` | Exterior/Lot | array | 25/25 | Yes | `[\"City Lot\"]` |
| `LotSizeAcres` | Exterior/Lot | float | 25/25 | Yes | `0.19` |
| `LotSizeArea` | Exterior/Lot | integer | 25/25 | No | `8450` |
| `LotSizeDimensions` | Unknown | string | 19/25 | No | `55 x 115` |
| `LotSizeSquareFeet` | Exterior/Lot | integer | 25/25 | Yes | `8450` |
| `LotSizeUnits` | Exterior/Lot | string | 25/25 | No | `Square Feet` |
| `MLSAreaMajor` | Location | string | 25/25 | Yes | `32827 - Orlando/Airport/Alafaya/Lake Nona` |
| `Make` | Unknown | null | 0/25 | No | — |
| `MaloneId` | Unknown | null | 0/25 | No | — |
| `Media` | Media | array | 25/25 | No | `[{\"MediaKey\":\"25b3e48ee7095eed12688b25c10ea606-m1\",\"MediaCat…` |
| `MiddleOrJuniorSchool` | Unknown | string | 21/25 | No | `Lake Nona Middle School` |
| `MlsStatus` | Property Basics | string | 25/25 | Yes | `Sold` |
| `MobileLength` | Unknown | null | 0/25 | No | — |
| `MobileWidth` | Unknown | null | 0/25 | No | — |
| `Model` | Unknown | string | 3/25 | No | `Oxford` |
| `ModificationTimestamp` | Property Basics | datetime | 25/25 | No | `2021-12-07T06:56:39.953Z` |
| `NewConstructionYN` | Exterior/Lot | boolean | 25/25 | Yes | true |
| `NumberOfBuildings` | Unknown | null | 0/25 | No | — |
| `NumberOfLots` | Unknown | null | 0/25 | No | — |
| `NumberOfPads` | Unknown | null | 0/25 | No | — |
| `NumberOfSeparateElectricMeters` | Exterior/Lot | null | 0/25 | No | — |
| `NumberOfSeparateGasMeters` | Unknown | null | 0/25 | No | — |
| `NumberOfSeparateWaterMeters` | Unknown | null | 0/25 | No | — |
| `NumberOfUnitsLeased` | Lease/Rental | null | 0/25 | No | — |
| `NumberOfUnitsTotal` | Interior Features | null | 0/25 | No | — |
| `OccupantType` | Unknown | string | 12/25 | No | `Vacant` |
| `OffMarketDate` | Property Basics | datetime | 25/25 | No | `2014-06-13` |
| `OffMarketTimestamp` | Unknown | datetime | 25/25 | No | `2014-06-13T04:00:00.000Z` |
| `OnMarketDate` | Property Basics | null | 0/25 | No | — |
| `OpenParkingSpaces` | Unknown | null | 0/25 | No | — |
| `OpenParkingYN` | Exterior/Lot | boolean | 1/25 | No | true |
| `OriginalEntryTimestamp` | Property Basics | datetime | 25/25 | No | `2014-03-13T16:56:49.917Z` |
| `OriginalListPrice` | Price | integer | 25/25 | Yes | `365618` |
| `OriginatingSystemKey` | Agent/Brokerage | string | 25/25 | No | `stellar` |
| `OriginatingSystemName` | Agent/Brokerage | string | 25/25 | No | `Stellar MLS` |
| `OtherParking` | Unknown | null | 0/25 | No | — |
| `OtherStructures` | Exterior/Lot | null | 0/25 | No | — |
| `OwnerPays` | Unknown | null | 0/25 | No | — |
| `Ownership` | Unknown | string | 25/25 | No | `Fee Simple` |
| `ParcelNumber` | Location | numeric string | 25/25 | No | `322431280000730` |
| `ParkName` | Unknown | null | 0/25 | No | — |
| `ParkingFeatures` | Exterior/Lot | array | 10/25 | Yes | `[\"Oversized\"]` |
| `PatioAndPorchFeatures` | Exterior/Lot | array | 15/25 | No | `[\"Covered\",\"Deck\"]` |
| `PetsAllowed` | Lease/Rental | array | 24/25 | Yes | `[\"Yes\"]` |
| `PhotosChangeTimestamp` | Media | datetime | 25/25 | No | `2024-09-06T10:07:06.728Z` |
| `PhotosCount` | Media | integer | 25/25 | Yes | `1` |
| `PoolFeatures` | Exterior/Lot | array | 2/25 | No | `[\"Child Safety Fence\"]` |
| `PoolPrivateYN` | Exterior/Lot | boolean | 25/25 | Yes | false |
| `Possession` | Unknown | null | 0/25 | No | — |
| `PossibleUse` | Unknown | null | 0/25 | No | — |
| `PostalCode` | Location | numeric string | 25/25 | Yes | `32827` |
| `PostalCodePlus4` | Location | numeric string | 17/25 | No | `6847` |
| `PreviousListPrice` | Price | integer | 22/25 | No | `365618` |
| `PriceChangeTimestamp` | Price | datetime | 22/25 | No | `2014-03-28T04:00:00.000Z` |
| `PropertyAttachedYN` | Unknown | null | 0/25 | No | — |
| `PropertyCondition` | Exterior/Lot | array | 23/25 | Yes | `[\"Under Construction\"]` |
| `PropertySubType` | Property Basics | string | 25/25 | Yes | `Single Family Residence` |
| `PropertyType` | Property Basics | string | 25/25 | Yes | `Residential` |
| `PublicRemarks` | Unknown | string | 25/25 | No | `BRAND NEW, ENERGY EFFICIENT HOME, READY JUNE 2014! ---Welcome to Fells` |
| `PublicSurveyRange` | Location | numeric string | 25/25 | No | `31` |
| `PublicSurveySection` | Location | numeric string | 25/25 | No | `32` |
| `PublicSurveyTownship` | Location | numeric string | 25/25 | No | `24` |
| `PurchaseContractDate` | Property Basics | datetime | 25/25 | No | `2014-04-01` |
| `RentIncludes` | Price | null | 0/25 | No | — |
| `RoadFrontageType` | Unknown | array | 25/25 | No | `[\"Street Paved\"]` |
| `RoadResponsibility` | Unknown | null | 0/25 | No | — |
| `RoadSurfaceType` | Unknown | array | 24/25 | No | `[\"Paved\"]` |
| `Roof` | Exterior/Lot | array | 25/25 | No | `[\"Tile\"]` |
| `RoomType` | Unknown | null | 0/25 | No | — |
| `RoomsTotal` | Interior Features | integer | 25/25 | No | `4` |
| `STELLAR_ActiveOpenHouseCount` | Unknown | integer | 5/25 | No | `0` |
| `STELLAR_AdditionalApplicantFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_AdditionalLeaseRestrictions` | Unknown | null | 0/25 | No | — |
| `STELLAR_AdditionalMembershipAvailableYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_AdditionalPetFees` | Unknown | null | 0/25 | No | — |
| `STELLAR_AdditionalRooms` | Unknown | array | 25/25 | No | `[\"Den\\/Library\\/Office\",\"Formal Dining Room Separate\"]` |
| `STELLAR_AdditionalWaterInformation` | Unknown | null | 0/25 | No | — |
| `STELLAR_AdjoiningProperty` | Unknown | null | 0/25 | No | — |
| `STELLAR_AffidavitYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_AlternateKeyFolioNum` | Unknown | null | 0/25 | No | — |
| `STELLAR_AmenitiesAdditionalFees` | Unknown | null | 0/25 | No | — |
| `STELLAR_AnnualExpenses` | Unknown | null | 0/25 | No | — |
| `STELLAR_AnnualIncomeType` | Unknown | null | 0/25 | No | — |
| `STELLAR_AnnualNetIncome` | Unknown | null | 0/25 | No | — |
| `STELLAR_AnnualRent` | Unknown | null | 0/25 | No | — |
| `STELLAR_ApplicationFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_ApprovalProcess` | Unknown | null | 0/25 | No | — |
| `STELLAR_AssociationApplicationFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_AssociationApprovalFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_AssociationApprovalRequiredYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_AssociationEmail` | HOA/Fees | null | 0/25 | No | — |
| `STELLAR_AssociationFeeRequirement` | Price | string | 25/25 | No | `Required` |
| `STELLAR_AssociationURL` | Unknown | null | 0/25 | No | — |
| `STELLAR_AuctionFirmURL` | Unknown | null | 0/25 | No | — |
| `STELLAR_AuctionPropAccessYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_AuctionTime` | Unknown | null | 0/25 | No | — |
| `STELLAR_AuctionType` | Unknown | null | 0/25 | No | — |
| `STELLAR_BOMDate` | Unknown | null | 0/25 | No | — |
| `STELLAR_BackupsRequestedYN` | Unknown | boolean | 1/25 | No | false |
| `STELLAR_BarnFeatures` | Unknown | null | 0/25 | No | — |
| `STELLAR_BuilderLicenseNumber` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_BuildingElevatorYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_BuildingNameNumber` | Unknown | numeric string | 1/25 | No | `2360` |
| `STELLAR_BusinessOpportunityWithRealEstateYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_BuyersCountryofResidence` | Location | string | 25/25 | No | `US` |
| `STELLAR_BuyersIntendedUse` | Unknown | string | 24/25 | No | `Primary Residence` |
| `STELLAR_BuyersPremium` | Unknown | null | 0/25 | No | — |
| `STELLAR_BuyersZipCode` | Unknown | numeric string | 25/25 | No | `34743` |
| `STELLAR_CDDYN` | Unknown | boolean | 25/25 | No | false |
| `STELLAR_CalculatedListPriceByCalculatedSqFt` | Price | float | 25/25 | No | `100.17` |
| `STELLAR_CallCenterPhoneNumber` | Compliance/Restricted | string | 17/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_CeilingHeight` | Unknown | null | 0/25 | No | — |
| `STELLAR_CeilingType` | Unknown | null | 0/25 | No | — |
| `STELLAR_CensusBlock` | Unknown | null | 0/25 | No | — |
| `STELLAR_CensusTract` | Unknown | null | 0/25 | No | — |
| `STELLAR_ClosePriceByCalculatedListPriceRatio` | Price | float | 25/25 | No | `0.92` |
| `STELLAR_ClosePriceByCalculatedSqFt` | Price | float | 25/25 | No | `91.67` |
| `STELLAR_ComTransactionTerms` | Unknown | null | 0/25 | No | — |
| `STELLAR_ComTransactionType` | Unknown | null | 0/25 | No | — |
| `STELLAR_ComingSoonDate` | Unknown | null | 0/25 | No | — |
| `STELLAR_ComplexCommunityNameNCCB` | Unknown | null | 0/25 | No | — |
| `STELLAR_ComplexDevelopmentName` | Unknown | null | 0/25 | No | — |
| `STELLAR_ConditionExpDate` | Unknown | null | 0/25 | No | — |
| `STELLAR_CondoEnvironmentYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_CondoFees` | Unknown | null | 0/25 | No | — |
| `STELLAR_CondoFeesTerm` | Unknown | null | 0/25 | No | — |
| `STELLAR_CondoLandIncludedYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_ContractStatus` | Unknown | array | 25/25 | No | `[\"No Contingency\"]` |
| `STELLAR_ConvertedResidenceYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_CountyLandUseCode` | Unknown | null | 0/25 | No | — |
| `STELLAR_CountyPropertyUseCode` | Unknown | null | 0/25 | No | — |
| `STELLAR_CreateAutomaticVirtualTourYN` | Unknown | boolean | 25/25 | No | true |
| `STELLAR_CurrencyMonthlyRentAmt` | Unknown | null | 0/25 | No | — |
| `STELLAR_CurrentAdjacentUse` | Unknown | null | 0/25 | No | — |
| `STELLAR_CurrentPrice` | Unknown | integer | 25/25 | No | `330000` |
| `STELLAR_DPRURL` | Unknown | string | 22/25 | No | `http://www.workforce-resource.com/eligibility/listing?metro_area=MFRML` |
| `STELLAR_DPRURL2` | Unknown | string | 22/25 | No | `http://www.workforce-resource.com/eligibility/listing?metro_area=MFRML` |
| `STELLAR_DPRYN` | Unknown | boolean | 22/25 | No | true |
| `STELLAR_DaysNoticeToTenantIfNotRenew` | Unknown | null | 0/25 | No | — |
| `STELLAR_DaysToClosed` | Unknown | integer | 25/25 | No | `92` |
| `STELLAR_DaystoContract` | Unknown | integer | 25/25 | No | `19` |
| `STELLAR_DepositsYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_Development` | Unknown | null | 0/25 | No | — |
| `STELLAR_DisasterMitigation` | Unknown | array | 13/25 | No | `[\"Above Flood Plain\",\"Fire\\/Smoke Detection Integration\"]` |
| `STELLAR_DockDescrip` | Unknown | null | 0/25 | No | — |
| `STELLAR_DockDimensions` | Unknown | null | 0/25 | No | — |
| `STELLAR_DockLiftCap` | Unknown | null | 0/25 | No | — |
| `STELLAR_DockMntncFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_DockMntncFeeFrqncy` | Unknown | null | 0/25 | No | — |
| `STELLAR_DockYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_DockYrBlt` | Unknown | null | 0/25 | No | — |
| `STELLAR_DoorHeight` | Unknown | null | 0/25 | No | — |
| `STELLAR_DoorWidth` | Unknown | null | 0/25 | No | — |
| `STELLAR_Easements` | Unknown | null | 0/25 | No | — |
| `STELLAR_EavesHeight` | Unknown | null | 0/25 | No | — |
| `STELLAR_EscrowAgentEmail` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_EscrowAgentFax` | Unknown | null | 0/25 | No | — |
| `STELLAR_EscrowAgentName` | Unknown | null | 0/25 | No | — |
| `STELLAR_EscrowAgentPhone` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_EscrowCity` | Location | null | 0/25 | No | — |
| `STELLAR_EscrowCompany` | Unknown | null | 0/25 | No | — |
| `STELLAR_EscrowPostalCode` | Location | null | 0/25 | No | — |
| `STELLAR_EscrowState` | Unknown | null | 0/25 | No | — |
| `STELLAR_EscrowStreetName` | Location | null | 0/25 | No | — |
| `STELLAR_EscrowStreetNumber` | Location | null | 0/25 | No | — |
| `STELLAR_EstAnnualMarketIncome` | Unknown | null | 0/25 | No | — |
| `STELLAR_ExistLseTenantYN` | Unknown | boolean | 12/25 | No | false |
| `STELLAR_ExpectedClosingDate` | Unknown | datetime | 25/25 | No | `2014-07-18T04:00:00.000Z` |
| `STELLAR_ExpectedLeaseDate` | Unknown | datetime | 2/25 | No | `2014-05-28T04:00:00.000Z` |
| `STELLAR_ExpectedOnMarketDate` | Property Basics | null | 0/25 | No | — |
| `STELLAR_ExpireRenewalDate` | Unknown | null | 0/25 | No | — |
| `STELLAR_FCHRURLYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_FloodZoneCode` | Unknown | string | 17/25 | No | `X` |
| `STELLAR_FloodZoneDate` | Unknown | null | 0/25 | No | — |
| `STELLAR_FloodZonePanel` | Unknown | null | 0/25 | No | — |
| `STELLAR_FloorNumber` | Unknown | numeric string | 1/25 | No | `2` |
| `STELLAR_ForLeaseYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_FreestandingYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_FreezerSpaceYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_FutureLandUse` | Unknown | numeric string | 22/25 | No | `0001` |
| `STELLAR_GarageDimensions` | Unknown | string | 14/25 | No | `21x25` |
| `STELLAR_GarageDoorHeight` | Unknown | null | 0/25 | No | — |
| `STELLAR_Geolocation` | Unknown | null | 0/25 | No | — |
| `STELLAR_GiftedDonated` | Unknown | null | 0/25 | No | — |
| `STELLAR_GreenEnergyGenerationYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_GreenLandscaping` | Unknown | array | 4/25 | No | `[\"Fl. Friendly\\/Native Landscape\"]` |
| `STELLAR_GreenVerificationCount` | Unknown | integer | 25/25 | No | `2` |
| `STELLAR_HERSIndex` | Unknown | integer | 11/25 | No | `62` |
| `STELLAR_HomesteadYN` | Unknown | boolean | 25/25 | No | true |
| `STELLAR_ILSTotalSQFT` | Unknown | null | 0/25 | No | — |
| `STELLAR_ILSUnderAirSQFT` | Unknown | null | 0/25 | No | — |
| `STELLAR_InLawSuiteDescrip` | Unknown | null | 0/25 | No | — |
| `STELLAR_InLawSuiteYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_LastDateAvailable` | Unknown | null | 0/25 | No | — |
| `STELLAR_LastMonthsRent` | Unknown | null | 0/25 | No | — |
| `STELLAR_LeasableArea` | Unknown | null | 0/25 | No | — |
| `STELLAR_LeasableAreaUnits` | Unknown | null | 0/25 | No | — |
| `STELLAR_LeasePricePerAcre` | Unknown | null | 0/25 | No | — |
| `STELLAR_LeaseRestrictionsYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_ListOfficeContactPreferred` | Compliance/Restricted | string | 21/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_ListOfficeHeadOfficeKeyNumeric` | Unknown | integer | 25/25 | No | `1042271` |
| `STELLAR_ListSource` | Unknown | null | 0/25 | No | — |
| `STELLAR_ListSourceOriginal` | Unknown | null | 0/25 | No | — |
| `STELLAR_LongTermYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_Management` | Unknown | null | 0/25 | No | — |
| `STELLAR_ManufacturingSpaceHeated` | Unknown | null | 0/25 | No | — |
| `STELLAR_ManufacturingSpaceTotal` | Unknown | null | 0/25 | No | — |
| `STELLAR_MasterBedSize` | Unknown | null | 0/25 | No | — |
| `STELLAR_MatrixTesting` | Unknown | boolean | 25/25 | No | false |
| `STELLAR_MaxPetWeight` | Unknown | null | 0/25 | No | — |
| `STELLAR_MillageRate` | Unknown | float | 19/25 | No | `19.23` |
| `STELLAR_MinimumLease` | Unknown | null | 0/25 | No | — |
| `STELLAR_MlsMajorChangeType` | Unknown | string | 25/25 | No | `Sold` |
| `STELLAR_MonthToMonthOrWeeklyYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_MonthlyCondoFeeAmount` | Unknown | null | 0/25 | No | — |
| `STELLAR_MonthlyHOAAmount` | HOA/Fees | integer | 23/25 | No | `60` |
| `STELLAR_MonthsAvailable` | Unknown | null | 0/25 | No | — |
| `STELLAR_MontlyMaintAmtAdditionToHOA` | Unknown | integer | 1/25 | No | `0` |
| `STELLAR_NetOperatingIncomeType` | Financial/Investment | null | 0/25 | No | — |
| `STELLAR_NoDriveBeach` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumOfOwnYearsPriorToLse` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumTimesperYear` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumberOfPaddocksPastures` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumberOfPets` | Unknown | numeric string | 3/25 | No | `3` |
| `STELLAR_NumberOfSeptics` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumberOfStalls` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumberOfWells` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumofBays` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumofBaysDockHigh` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumofBaysGradeLevel` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumofConferenceMeetingRooms` | Unknown | null | 0/25 | No | — |
| `STELLAR_NumofOffices` | Unknown | null | 0/25 | No | — |
| `STELLAR_OffSeasonRent` | Unknown | null | 0/25 | No | — |
| `STELLAR_OfficeIDXOfficeParticipationYN` | Unknown | boolean | 25/25 | No | true |
| `STELLAR_OfficeRetailSpaceSqFt` | Unknown | null | 0/25 | No | — |
| `STELLAR_OfficeSyndicateTo` | Unknown | array | 25/25 | No | `[\"Facebook Marketplace\",\"International MLS\"]` |
| `STELLAR_OpenHouseCount` | Unknown | integer | 5/25 | No | `0` |
| `STELLAR_OriginatingSystemTimestamp` | Unknown | datetime | 25/25 | No | `2014-04-04T15:46:56.433Z` |
| `STELLAR_OtherExemptionsYN` | Unknown | boolean | 10/25 | No | false |
| `STELLAR_OtherFeesAmount` | Unknown | null | 0/25 | No | — |
| `STELLAR_OtherFeesDescription` | Unknown | null | 0/25 | No | — |
| `STELLAR_OtherFeesTerm` | Unknown | null | 0/25 | No | — |
| `STELLAR_ParkingFeeTenants` | Unknown | null | 0/25 | No | — |
| `STELLAR_ParkingFeeTenantsFrequency` | Unknown | null | 0/25 | No | — |
| `STELLAR_PetDepositFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_PetFeeNonRefundable` | Unknown | null | 0/25 | No | — |
| `STELLAR_PetMonthlyFee` | Unknown | null | 0/25 | No | — |
| `STELLAR_PetRestrictions` | Unknown | string | 7/25 | No | `NA` |
| `STELLAR_PetSize` | Unknown | null | 0/25 | No | — |
| `STELLAR_PlannedUnitDevelopmentYN` | Unknown | boolean | 19/25 | No | true |
| `STELLAR_PoolDimensions` | Unknown | null | 0/25 | No | — |
| `STELLAR_PreviousStatus` | Unknown | string | 25/25 | No | `Pending` |
| `STELLAR_PricePerAcre` | Unknown | null | 0/25 | No | — |
| `STELLAR_ProjectedCompletionDate` | Unknown | datetime | 23/25 | No | `2014-06-06T04:00:00.000Z` |
| `STELLAR_PropertyManager` | Unknown | null | 0/25 | No | — |
| `STELLAR_PropertyManagerPhone` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_PublicRemarksAgent` | Unknown | string | 25/25 | No | `BRAND NEW, ENERGY EFFICIENT HOME, READY JUNE 2014! ---Welcome to Fells` |
| `STELLAR_PublicRemarksRequired` | Unknown | null | 0/25 | No | — |
| `STELLAR_PublicRemarksSpanishReq` | Unknown | null | 0/25 | No | — |
| `STELLAR_RATIO_ClosePrice_By_ListPrice` | Price | float | 25/25 | No | `0.9151` |
| `STELLAR_RATIO_ClosePrice_By_OriginalListPrice` | Price | float | 25/25 | No | `0.90258` |
| `STELLAR_RATIO_CurrentPrice_By_BuildingAreaTotal` | Interior Features | float | 9/25 | No | `78.72` |
| `STELLAR_RATIO_CurrentPrice_By_CalculatedSqFt` | Unknown | float | 25/25 | No | `91.67` |
| `STELLAR_RETSUpdateTransactionYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_RealtorInfo` | Unknown | array | 21/25 | No | `[\"Brochure Available\",\"Floor Plan Available\"]` |
| `STELLAR_RealtorInfoConfidential` | Unknown | array | 15/25 | No | `[\"Go To Site\"]` |
| `STELLAR_RegionalAOR` | Unknown | string | 25/25 | No | `Orlando Regional` |
| `STELLAR_RentSpreeURL` | Unknown | null | 0/25 | No | — |
| `STELLAR_RentSpreeYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_Representation` | Unknown | string | 11/25 | No | `Seller Represented` |
| `STELLAR_SDEOYN` | Unknown | boolean | 25/25 | No | false |
| `STELLAR_SWSubdivCommunityName` | Unknown | string | 25/25 | No | `Not Applicable` |
| `STELLAR_SWSubdivCondoNum` | Unknown | null | 0/25 | No | — |
| `STELLAR_SeasonalRent` | Unknown | null | 0/25 | No | — |
| `STELLAR_SecurityDeposit` | Unknown | null | 0/25 | No | — |
| `STELLAR_SellerRepresentation` | Unknown | null | 0/25 | No | — |
| `STELLAR_ShowingConsiderations` | Compliance/Restricted | array | 6/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_ShowingRequirements` | Compliance/Restricted | array | 25/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_ShowingTime` | Unknown | string | 17/25 | No | `View Instructions` |
| `STELLAR_SolarLeaseFinanceTerms` | Unknown | null | 0/25 | No | — |
| `STELLAR_SolarPanelOwnership` | Unknown | null | 0/25 | No | — |
| `STELLAR_SoldRemarks` | Unknown | null | 0/25 | No | — |
| `STELLAR_SpaceType` | Unknown | null | 0/25 | No | — |
| `STELLAR_StateLandUseCode` | Unknown | null | 0/25 | No | — |
| `STELLAR_StatePropertyUseCode` | Unknown | null | 0/25 | No | — |
| `STELLAR_SubdivisionNum` | Unknown | numeric string | 25/25 | No | `2800` |
| `STELLAR_SubdivisionSectionNumber` | Unknown | null | 0/25 | No | — |
| `STELLAR_TempOffMarketDate` | Property Basics | null | 0/25 | No | — |
| `STELLAR_TenantName` | Unknown | string | 2/25 | No | `DR HORTON INC` |
| `STELLAR_TenantPhone` | Compliance/Restricted | string | 2/25 | No (restricted) | `[REDACTED]` |
| `STELLAR_ThirdPartyYN` | Unknown | boolean | 25/25 | No | false |
| `STELLAR_TotalAcreage` | Unknown | string | 25/25 | No | `0 to less than 1/4` |
| `STELLAR_TotalAnnualFees` | Unknown | integer | 22/25 | No | `720` |
| `STELLAR_TotalDocumentsCount` | Media | integer | 25/25 | No | `0` |
| `STELLAR_TotalMonthlyExpenses` | Unknown | null | 0/25 | No | — |
| `STELLAR_TotalMonthlyFees` | Unknown | integer | 22/25 | No | `60` |
| `STELLAR_TotalPhotosCount` | Media | integer | 25/25 | No | `7` |
| `STELLAR_UnitCount` | Unknown | null | 0/25 | No | — |
| `STELLAR_UnitNumberYN` | Location | null | 0/25 | No | — |
| `STELLAR_UniversalPropertyId` | Unknown | string | 25/25 | No | `US-12095-N-322431280000730-R-N` |
| `STELLAR_UnparsedAddress` | Location | string | 25/25 | No | `19323  FALLGLO DR` |
| `STELLAR_UseCode` | Unknown | null | 0/25 | No | — |
| `STELLAR_VirtualTourURLBranded2` | Media | null | 0/25 | No | — |
| `STELLAR_VirtualTourURLUnbranded2` | Media | string | 1/25 | No | `http://www.tourfactory.com/idxr1297663` |
| `STELLAR_VirtuallyStagedYN` | Unknown | null | 0/25 | No | — |
| `STELLAR_WarehouseSpaceHeated` | Unknown | null | 0/25 | No | — |
| `STELLAR_WarehouseSpaceTotal` | Unknown | null | 0/25 | No | — |
| `STELLAR_WaterAccess` | Unknown | null | 0/25 | No | — |
| `STELLAR_WaterAccessYN` | Unknown | boolean | 25/25 | No | false |
| `STELLAR_WaterExtras` | Unknown | null | 0/25 | No | — |
| `STELLAR_WaterExtrasYN` | Unknown | boolean | 25/25 | No | false |
| `STELLAR_WaterView` | Exterior/Lot | array | 5/25 | No | `[\"Pond\"]` |
| `STELLAR_WaterViewYN` | Exterior/Lot | boolean | 25/25 | No | true |
| `STELLAR_WaterfrontFeetTotal` | Unknown | null | 0/25 | No | — |
| `STELLAR_WeeklyRent` | Unknown | null | 0/25 | No | — |
| `STELLAR_WeeksAvailable` | Unknown | null | 0/25 | No | — |
| `STELLAR_YrsOfOwnerPriorToLeasingReqYN` | Lease/Rental | null | 0/25 | No | — |
| `STELLAR_ZoningCompatibleYN` | Financial/Investment | boolean | 16/25 | No | false |
| `SecurityFeatures` | Unknown | array | 23/25 | No | `[\"Security System Owned\"]` |
| `SeniorCommunityYN` | HOA/Fees | boolean | 25/25 | Yes | false |
| `SerialU` | Unknown | null | 0/25 | No | — |
| `SerialX` | Unknown | null | 0/25 | No | — |
| `SerialXX` | Unknown | null | 0/25 | No | — |
| `Sewer` | Exterior/Lot | array | 12/25 | Yes | `[\"Public Sewer\"]` |
| `Skirt` | Unknown | null | 0/25 | No | — |
| `SourceSystemKey` | Unknown | numeric string | 25/25 | No | `32455874` |
| `SourceSystemName` | Agent/Brokerage | null | 0/25 | No | — |
| `SpaFeatures` | Exterior/Lot | null | 0/25 | No | — |
| `SpaYN` | Exterior/Lot | boolean | 9/25 | No | true |
| `SpecialListingConditions` | Unknown | array | 25/25 | No | `[\"None\"]` |
| `StandardStatus` | Property Basics | string | 25/25 | Yes | `Closed` |
| `StateOrProvince` | Location | string | 25/25 | Yes | `FL` |
| `StatusChangeTimestamp` | Property Basics | datetime | 25/25 | No | `2014-06-19T21:52:55.680Z` |
| `Stories` | Interior Features | null | 0/25 | No | — |
| `StoriesTotal` | Interior Features | null | 0/25 | Yes | — |
| `StreetAdditionalInfo` | Location | null | 0/25 | No | — |
| `StreetDirPrefix` | Location | null | 0/25 | No | — |
| `StreetDirSuffix` | Unknown | string | 1/25 | No | `NE` |
| `StreetName` | Location | string | 25/25 | No | `FALLGLO` |
| `StreetNumber` | Location | numeric string | 25/25 | No | `19323` |
| `StreetNumberNumeric` | Location | integer | 25/25 | No | `19323` |
| `StreetSuffix` | Location | string | 25/25 | No | `DRIVE` |
| `StreetSuffixModifier` | Location | null | 0/25 | No | — |
| `StructureType` | Unknown | null | 0/25 | No | — |
| `SubdivisionName` | Location | string | 25/25 | Yes | `FELL'S LNDG` |
| `TaxAnnualAmount` | Price | integer | 25/25 | Yes | `504` |
| `TaxBlock` | Financial/Investment | numeric string | 25/25 | No | `00` |
| `TaxBookNumber` | Unknown | string | 25/25 | No | `77/42` |
| `TaxExemptions` | Unknown | null | 0/25 | No | — |
| `TaxLegalDescription` | Location | string | 25/25 | No | `FELLS LANDING 77/42 LOT 73 Plan Jackson 3179/C` |
| `TaxLot` | Financial/Investment | numeric string | 21/25 | No | `730` |
| `TaxOtherAnnualAssessmentAmount` | Unknown | integer | 9/25 | No | `2567` |
| `TaxYear` | Price | integer | 25/25 | No | `2013` |
| `Telephone` | Compliance/Restricted | null | 0/25 | No (restricted) | `[REDACTED]` |
| `TenantPays` | Lease/Rental | null | 0/25 | No | — |
| `Topography` | Exterior/Lot | null | 0/25 | No | — |
| `TotalActualRent` | Financial/Investment | null | 0/25 | No | — |
| `UnitNumber` | Location | numeric string | 2/25 | No | `2010` |
| `UnitTypeType` | Unknown | null | 0/25 | No | — |
| `UnparsedAddress` | Location | string | 25/25 | No | `19323 FALLGLO DRIVE` |
| `Utilities` | Exterior/Lot | array | 25/25 | Yes | `[\"Electricity Connected\",\"Public\"]` |
| `Vegetation` | Unknown | array | 15/25 | No | `[\"Trees\\/Landscaped\"]` |
| `View` | Exterior/Lot | array | 5/25 | Yes | `[\"Water\"]` |
| `ViewYN` | Exterior/Lot | boolean | 25/25 | Yes | true |
| `VirtualTourURLBranded` | Media | string | 4/25 | No | `http://www.drhorton.com/Where-We-Build/Florida/Central/Orlando/Chandle` |
| `VirtualTourURLUnbranded` | Media | string | 18/25 | Yes | `http://instatour.propertypanorama.com/instaview/mfr/O5215314` |
| `VirtualTourURLZillow` | Media | null | 0/25 | No | — |
| `WaterBodyName` | Exterior/Lot | null | 0/25 | No | — |
| `WaterSource` | Exterior/Lot | array | 19/25 | Yes | `[\"Public\"]` |
| `WaterfrontFeatures` | Exterior/Lot | array | 2/25 | No | `[\"Pond\"]` |
| `WaterfrontYN` | Exterior/Lot | boolean | 25/25 | Yes | true |
| `WindowFeatures` | Interior Features | array | 25/25 | No | `[\"ENERGY STAR Qualified Windows\"]` |
| `YearBuilt` | Exterior/Lot | integer | 25/25 | Yes | `2013` |
| `YearBuiltEffective` | Exterior/Lot | null | 0/25 | No | — |
| `YearBuiltSource` | Exterior/Lot | null | 0/25 | No | — |
| `YearEstablished` | Unknown | null | 0/25 | No | — |
| `Zoning` | Financial/Investment | string | 24/25 | Yes | `R-1` |
| `ZoningDescription` | Financial/Investment | null | 0/25 | Yes | — |

---

## Empty / Unreliable Fields (0 populated in sample)

| Field Key | Category |
|---|---|
| `AccessibilityFeatures` | Interior Features |
| `AdditionalParcelsDescription` | Unknown |
| `ApprovalStatus` | Unknown |
| `AssociationFee2` | Price |
| `AssociationFee2Frequency` | Price |
| `AssociationName` | HOA/Fees |
| `AssociationName2` | HOA/Fees |
| `AssociationPhone` | HOA/Fees |
| `AssociationPhone2` | HOA/Fees |
| `AvailabilityDate` | Lease/Rental |
| `Basement` | Interior Features |
| `BathroomsOneQuarter` | Interior Features |
| `BathroomsPartial` | Unknown |
| `BathroomsThreeQuarter` | Interior Features |
| `BodyType` | Unknown |
| `BuilderModel` | Unknown |
| `BuilderName` | Unknown |
| `BuildingFeatures` | Unknown |
| `BusinessName` | Unknown |
| `BusinessType` | Unknown |
| `BuyerAgentFirstName` | Unknown |
| `BuyerAgentLastName` | Unknown |
| `BuyerAgentStateLicense` | Compliance/Restricted |
| `BuyerTeamName` | Unknown |
| `CapRate` | Financial/Investment |
| `CarportSpaces` | Exterior/Lot |
| `CarportYN` | Exterior/Lot |
| `CoBuyerAgentFirstName` | Unknown |
| `CoBuyerAgentFullName` | Agent/Brokerage |
| `CoBuyerAgentKeyNumeric` | Agent/Brokerage |
| `CoBuyerAgentLastName` | Unknown |
| `CoBuyerAgentMlsId` | Unknown |
| `CoBuyerAgentStateLicense` | Compliance/Restricted |
| `CoBuyerOfficeKeyNumeric` | Unknown |
| `CoBuyerOfficeMlsId` | Unknown |
| `CoBuyerOfficeName` | Agent/Brokerage |
| `CoListAgentFirstName` | Unknown |
| `CoListAgentLastName` | Unknown |
| `CoListAgentStateLicense` | Compliance/Restricted |
| `CommonWalls` | Unknown |
| `CopyrightNotice` | Unknown |
| `Country` | Location |
| `CrossStreet` | Location |
| `CurrentUse` | Unknown |
| `DOH1` | Unknown |
| `DOH2` | Unknown |
| `DOH3` | Unknown |
| `Disclosures` | Unknown |
| `DoorFeatures` | Unknown |
| `Electric` | Exterior/Lot |
| `Exclusions` | Unknown |
| `Fencing` | Exterior/Lot |
| `FireplaceFeatures` | Interior Features |
| `FireplacesTotal` | Interior Features |
| `FrontageLength` | Unknown |
| `Gas` | Unknown |
| `GreenEnergyGeneration` | Unknown |
| `GreenLocation` | Unknown |
| `GreenSustainability` | Unknown |
| `GrossIncome` | Unknown |
| `GrossScheduledIncome` | Financial/Investment |
| `HighSchoolDistrict` | Unknown |
| `HorseAmenities` | Unknown |
| `LandLeaseAmount` | Price |
| `LandLeaseYN` | Unknown |
| `LeaseAmountFrequency` | Price |
| `LeaseConsideredYN` | Lease/Rental |
| `LeaseTerm` | Lease/Rental |
| `License1` | Compliance/Restricted |
| `License2` | Compliance/Restricted |
| `License3` | Compliance/Restricted |
| `ListAgentFirstName` | Unknown |
| `ListAgentLastName` | Unknown |
| `ListAgentStateLicense` | Compliance/Restricted |
| `LockBoxLocation` | Compliance/Restricted |
| `LockBoxSerialNumber` | Compliance/Restricted |
| `LockBoxType` | Compliance/Restricted |
| `Make` | Unknown |
| `MaloneId` | Unknown |
| `MobileLength` | Unknown |
| `MobileWidth` | Unknown |
| `NumberOfBuildings` | Unknown |
| `NumberOfLots` | Unknown |
| `NumberOfPads` | Unknown |
| `NumberOfSeparateElectricMeters` | Exterior/Lot |
| `NumberOfSeparateGasMeters` | Unknown |
| `NumberOfSeparateWaterMeters` | Unknown |
| `NumberOfUnitsLeased` | Lease/Rental |
| `NumberOfUnitsTotal` | Interior Features |
| `OnMarketDate` | Property Basics |
| `OpenParkingSpaces` | Unknown |
| `OtherParking` | Unknown |
| `OtherStructures` | Exterior/Lot |
| `OwnerPays` | Unknown |
| `ParkName` | Unknown |
| `Possession` | Unknown |
| `PossibleUse` | Unknown |
| `PropertyAttachedYN` | Unknown |
| `RentIncludes` | Price |
| `RoadResponsibility` | Unknown |
| `RoomType` | Unknown |
| `STELLAR_AdditionalApplicantFee` | Unknown |
| `STELLAR_AdditionalLeaseRestrictions` | Unknown |
| `STELLAR_AdditionalMembershipAvailableYN` | Unknown |
| `STELLAR_AdditionalPetFees` | Unknown |
| `STELLAR_AdditionalWaterInformation` | Unknown |
| `STELLAR_AdjoiningProperty` | Unknown |
| `STELLAR_AffidavitYN` | Unknown |
| `STELLAR_AlternateKeyFolioNum` | Unknown |
| `STELLAR_AmenitiesAdditionalFees` | Unknown |
| `STELLAR_AnnualExpenses` | Unknown |
| `STELLAR_AnnualIncomeType` | Unknown |
| `STELLAR_AnnualNetIncome` | Unknown |
| `STELLAR_AnnualRent` | Unknown |
| `STELLAR_ApplicationFee` | Unknown |
| `STELLAR_ApprovalProcess` | Unknown |
| `STELLAR_AssociationApplicationFee` | Unknown |
| `STELLAR_AssociationApprovalFee` | Unknown |
| `STELLAR_AssociationApprovalRequiredYN` | Unknown |
| `STELLAR_AssociationEmail` | HOA/Fees |
| `STELLAR_AssociationURL` | Unknown |
| `STELLAR_AuctionFirmURL` | Unknown |
| `STELLAR_AuctionPropAccessYN` | Unknown |
| `STELLAR_AuctionTime` | Unknown |
| `STELLAR_AuctionType` | Unknown |
| `STELLAR_BOMDate` | Unknown |
| `STELLAR_BarnFeatures` | Unknown |
| `STELLAR_BuilderLicenseNumber` | Compliance/Restricted |
| `STELLAR_BuildingElevatorYN` | Unknown |
| `STELLAR_BusinessOpportunityWithRealEstateYN` | Unknown |
| `STELLAR_BuyersPremium` | Unknown |
| `STELLAR_CeilingHeight` | Unknown |
| `STELLAR_CeilingType` | Unknown |
| `STELLAR_CensusBlock` | Unknown |
| `STELLAR_CensusTract` | Unknown |
| `STELLAR_ComTransactionTerms` | Unknown |
| `STELLAR_ComTransactionType` | Unknown |
| `STELLAR_ComingSoonDate` | Unknown |
| `STELLAR_ComplexCommunityNameNCCB` | Unknown |
| `STELLAR_ComplexDevelopmentName` | Unknown |
| `STELLAR_ConditionExpDate` | Unknown |
| `STELLAR_CondoEnvironmentYN` | Unknown |
| `STELLAR_CondoFees` | Unknown |
| `STELLAR_CondoFeesTerm` | Unknown |
| `STELLAR_CondoLandIncludedYN` | Unknown |
| `STELLAR_ConvertedResidenceYN` | Unknown |
| `STELLAR_CountyLandUseCode` | Unknown |
| `STELLAR_CountyPropertyUseCode` | Unknown |
| `STELLAR_CurrencyMonthlyRentAmt` | Unknown |
| `STELLAR_CurrentAdjacentUse` | Unknown |
| `STELLAR_DaysNoticeToTenantIfNotRenew` | Unknown |
| `STELLAR_DepositsYN` | Unknown |
| `STELLAR_Development` | Unknown |
| `STELLAR_DockDescrip` | Unknown |
| `STELLAR_DockDimensions` | Unknown |
| `STELLAR_DockLiftCap` | Unknown |
| `STELLAR_DockMntncFee` | Unknown |
| `STELLAR_DockMntncFeeFrqncy` | Unknown |
| `STELLAR_DockYN` | Unknown |
| `STELLAR_DockYrBlt` | Unknown |
| `STELLAR_DoorHeight` | Unknown |
| `STELLAR_DoorWidth` | Unknown |
| `STELLAR_Easements` | Unknown |
| `STELLAR_EavesHeight` | Unknown |
| `STELLAR_EscrowAgentEmail` | Compliance/Restricted |
| `STELLAR_EscrowAgentFax` | Unknown |
| `STELLAR_EscrowAgentName` | Unknown |
| `STELLAR_EscrowAgentPhone` | Compliance/Restricted |
| `STELLAR_EscrowCity` | Location |
| `STELLAR_EscrowCompany` | Unknown |
| `STELLAR_EscrowPostalCode` | Location |
| `STELLAR_EscrowState` | Unknown |
| `STELLAR_EscrowStreetName` | Location |
| `STELLAR_EscrowStreetNumber` | Location |
| `STELLAR_EstAnnualMarketIncome` | Unknown |
| `STELLAR_ExpectedOnMarketDate` | Property Basics |
| `STELLAR_ExpireRenewalDate` | Unknown |
| `STELLAR_FCHRURLYN` | Unknown |
| `STELLAR_FloodZoneDate` | Unknown |
| `STELLAR_FloodZonePanel` | Unknown |
| `STELLAR_ForLeaseYN` | Unknown |
| `STELLAR_FreestandingYN` | Unknown |
| `STELLAR_FreezerSpaceYN` | Unknown |
| `STELLAR_GarageDoorHeight` | Unknown |
| `STELLAR_Geolocation` | Unknown |
| `STELLAR_GiftedDonated` | Unknown |
| `STELLAR_GreenEnergyGenerationYN` | Unknown |
| `STELLAR_ILSTotalSQFT` | Unknown |
| `STELLAR_ILSUnderAirSQFT` | Unknown |
| `STELLAR_InLawSuiteDescrip` | Unknown |
| `STELLAR_InLawSuiteYN` | Unknown |
| `STELLAR_LastDateAvailable` | Unknown |
| `STELLAR_LastMonthsRent` | Unknown |
| `STELLAR_LeasableArea` | Unknown |
| `STELLAR_LeasableAreaUnits` | Unknown |
| `STELLAR_LeasePricePerAcre` | Unknown |
| `STELLAR_LeaseRestrictionsYN` | Unknown |
| `STELLAR_ListSource` | Unknown |
| `STELLAR_ListSourceOriginal` | Unknown |
| `STELLAR_LongTermYN` | Unknown |
| `STELLAR_Management` | Unknown |
| `STELLAR_ManufacturingSpaceHeated` | Unknown |
| `STELLAR_ManufacturingSpaceTotal` | Unknown |
| `STELLAR_MasterBedSize` | Unknown |
| `STELLAR_MaxPetWeight` | Unknown |
| `STELLAR_MinimumLease` | Unknown |
| `STELLAR_MonthToMonthOrWeeklyYN` | Unknown |
| `STELLAR_MonthlyCondoFeeAmount` | Unknown |
| `STELLAR_MonthsAvailable` | Unknown |
| `STELLAR_NetOperatingIncomeType` | Financial/Investment |
| `STELLAR_NoDriveBeach` | Unknown |
| `STELLAR_NumOfOwnYearsPriorToLse` | Unknown |
| `STELLAR_NumTimesperYear` | Unknown |
| `STELLAR_NumberOfPaddocksPastures` | Unknown |
| `STELLAR_NumberOfSeptics` | Unknown |
| `STELLAR_NumberOfStalls` | Unknown |
| `STELLAR_NumberOfWells` | Unknown |
| `STELLAR_NumofBays` | Unknown |
| `STELLAR_NumofBaysDockHigh` | Unknown |
| `STELLAR_NumofBaysGradeLevel` | Unknown |
| `STELLAR_NumofConferenceMeetingRooms` | Unknown |
| `STELLAR_NumofOffices` | Unknown |
| `STELLAR_OffSeasonRent` | Unknown |
| `STELLAR_OfficeRetailSpaceSqFt` | Unknown |
| `STELLAR_OtherFeesAmount` | Unknown |
| `STELLAR_OtherFeesDescription` | Unknown |
| `STELLAR_OtherFeesTerm` | Unknown |
| `STELLAR_ParkingFeeTenants` | Unknown |
| `STELLAR_ParkingFeeTenantsFrequency` | Unknown |
| `STELLAR_PetDepositFee` | Unknown |
| `STELLAR_PetFeeNonRefundable` | Unknown |
| `STELLAR_PetMonthlyFee` | Unknown |
| `STELLAR_PetSize` | Unknown |
| `STELLAR_PoolDimensions` | Unknown |
| `STELLAR_PricePerAcre` | Unknown |
| `STELLAR_PropertyManager` | Unknown |
| `STELLAR_PropertyManagerPhone` | Compliance/Restricted |
| `STELLAR_PublicRemarksRequired` | Unknown |
| `STELLAR_PublicRemarksSpanishReq` | Unknown |
| `STELLAR_RETSUpdateTransactionYN` | Unknown |
| `STELLAR_RentSpreeURL` | Unknown |
| `STELLAR_RentSpreeYN` | Unknown |
| `STELLAR_SWSubdivCondoNum` | Unknown |
| `STELLAR_SeasonalRent` | Unknown |
| `STELLAR_SecurityDeposit` | Unknown |
| `STELLAR_SellerRepresentation` | Unknown |
| `STELLAR_SolarLeaseFinanceTerms` | Unknown |
| `STELLAR_SolarPanelOwnership` | Unknown |
| `STELLAR_SoldRemarks` | Unknown |
| `STELLAR_SpaceType` | Unknown |
| `STELLAR_StateLandUseCode` | Unknown |
| `STELLAR_StatePropertyUseCode` | Unknown |
| `STELLAR_SubdivisionSectionNumber` | Unknown |
| `STELLAR_TempOffMarketDate` | Property Basics |
| `STELLAR_TotalMonthlyExpenses` | Unknown |
| `STELLAR_UnitCount` | Unknown |
| `STELLAR_UnitNumberYN` | Location |
| `STELLAR_UseCode` | Unknown |
| `STELLAR_VirtualTourURLBranded2` | Media |
| `STELLAR_VirtuallyStagedYN` | Unknown |
| `STELLAR_WarehouseSpaceHeated` | Unknown |
| `STELLAR_WarehouseSpaceTotal` | Unknown |
| `STELLAR_WaterAccess` | Unknown |
| `STELLAR_WaterExtras` | Unknown |
| `STELLAR_WaterfrontFeetTotal` | Unknown |
| `STELLAR_WeeklyRent` | Unknown |
| `STELLAR_WeeksAvailable` | Unknown |
| `STELLAR_YrsOfOwnerPriorToLeasingReqYN` | Lease/Rental |
| `SerialU` | Unknown |
| `SerialX` | Unknown |
| `SerialXX` | Unknown |
| `Skirt` | Unknown |
| `SourceSystemName` | Agent/Brokerage |
| `SpaFeatures` | Exterior/Lot |
| `Stories` | Interior Features |
| `StoriesTotal` | Interior Features |
| `StreetAdditionalInfo` | Location |
| `StreetDirPrefix` | Location |
| `StreetSuffixModifier` | Location |
| `StructureType` | Unknown |
| `TaxExemptions` | Unknown |
| `Telephone` | Compliance/Restricted |
| `TenantPays` | Lease/Rental |
| `Topography` | Exterior/Lot |
| `TotalActualRent` | Financial/Investment |
| `UnitTypeType` | Unknown |
| `VirtualTourURLZillow` | Media |
| `WaterBodyName` | Exterior/Lot |
| `YearBuiltEffective` | Exterior/Lot |
| `YearBuiltSource` | Exterior/Lot |
| `YearEstablished` | Unknown |
| `ZoningDescription` | Financial/Investment |

---

## Compliance-Sensitive Exclusions

The following fields were found in the dataset but **must not be used in matching, Ask AI, or
public-facing features** without legal review. Example values are redacted throughout this report.

Detection rules (key-pattern layer):
- Key contains `Phone` → agent/tenant/call-center phone numbers
- Key contains `Email` → email addresses (unless HOA/Association on allow-list)
- Key contains `License` → agent or builder license numbers
- Key starts with or contains `LockBox` → lockbox type/location/serial
- Key contains `PrivateRemarks` → agent-only private notes
- Key contains `ContactPreferred` or `Contact{Name|Phone|Type|Info|Number|Method}` → contact data fields
- Key contains `ShowingInstruction|ShowingContact|ShowingRequirement|ShowingConsideration` → private access details

Value-based backstop (catches ambiguous key names):
- Field's example value matches US phone number pattern → flagged as restricted
- Field's example value matches email address pattern → flagged as restricted

| Field Key | Reason |
|---|---|
| `BuyerAgentStateLicense` | License number — regulatory identifier |
| `CoBuyerAgentStateLicense` | License number — regulatory identifier |
| `CoListAgentStateLicense` | License number — regulatory identifier |
| `License1` | License number — regulatory identifier |
| `License2` | License number — regulatory identifier |
| `License3` | License number — regulatory identifier |
| `ListAgentEmail` | Email address — personal contact data |
| `ListAgentPreferredPhone` | Phone number — personal contact data |
| `ListAgentStateLicense` | License number — regulatory identifier |
| `ListOfficePhone` | Phone number — personal contact data |
| `LockBoxLocation` | Lockbox details — private property access |
| `LockBoxSerialNumber` | Lockbox details — private property access |
| `LockBoxType` | Lockbox details — private property access |
| `STELLAR_BuilderLicenseNumber` | License number — regulatory identifier |
| `STELLAR_CallCenterPhoneNumber` | Phone number — personal contact data |
| `STELLAR_EscrowAgentEmail` | Email address — personal contact data |
| `STELLAR_EscrowAgentPhone` | Phone number — personal contact data |
| `STELLAR_ListOfficeContactPreferred` | Preferred contact field — stores phone/email value |
| `STELLAR_PropertyManagerPhone` | Phone number — personal contact data |
| `STELLAR_ShowingConsiderations` | Showing considerations — private notes |
| `STELLAR_ShowingRequirements` | Showing requirements — private access details |
| `STELLAR_TenantPhone` | Phone number — personal contact data |
| `Telephone` | Phone number — personal contact data |

---

## Proof Block

| Item | Value |
|---|---|
| Command run | `php artisan bridge:audit-fields --limit=25` |
| Records received | 25 |
| Total unique field keys | 553 |
| Reliably populated fields (≥50%) | 217 |
| Sparse fields (<50%) | 44 |
| Always-empty fields | 292 |
| Compliance-flagged fields found | 23 |
| Existing tables modified | **None** |
| Migrations created | **None** |

**Top matching-ready fields found in sample:**

`Appliances`, `ArchitecturalStyle`, `AssociationAmenities`, `AssociationFee`, `AssociationYN`, `BathroomsFull`, `BathroomsHalf`, `BathroomsTotalInteger`, `BedroomsTotal`, `CapRate`, `City`, `CommunityFeatures`, `ConstructionMaterials`, `Cooling`, `CountyOrParish`, `ExteriorFeatures`, `Furnished`, `GarageSpaces`, `GarageYN`, `GrossScheduledIncome`, `Heating`, `InteriorFeatures`, `Latitude`, `LeaseConsideredYN`, `LeaseTerm`, `Levels`, `ListPrice`, `LivingArea`, `Longitude`, `LotFeatures`, `LotSizeAcres`, `LotSizeSquareFeet`, `MLSAreaMajor`, `MlsStatus`, `NewConstructionYN`, `OriginalListPrice`, `ParkingFeatures`, `PetsAllowed`, `PhotosCount`, `PoolPrivateYN`, `PostalCode`, `PropertyCondition`, `PropertySubType`, `PropertyType`, `SeniorCommunityYN`, `Sewer`, `StandardStatus`, `StateOrProvince`, `StoriesTotal`, `SubdivisionName`, `TaxAnnualAmount`, `Utilities`, `View`, `ViewYN`, `VirtualTourURLUnbranded`, `WaterSource`, `WaterfrontYN`, `YearBuilt`, `Zoning`, `ZoningDescription`

