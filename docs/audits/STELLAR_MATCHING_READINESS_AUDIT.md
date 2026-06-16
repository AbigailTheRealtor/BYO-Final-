# Stellar MLS Matching Readiness Audit

> Source audit: `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md`
> Audit date: 2026-06-16
> Total fields classified: **553**
> Analyst note: Sample of 25 records used; population counts reflect that sample only.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Complete Field Classification Table](#2-complete-field-classification-table)
3. [Buyer Matching Blueprint](#3-buyer-matching-blueprint)
4. [Tenant Matching Blueprint](#4-tenant-matching-blueprint)
5. [Ask AI Blueprint](#5-ask-ai-blueprint)
6. [Property Alert Blueprint](#6-property-alert-blueprint)
7. [Search Filter Blueprint](#7-search-filter-blueprint)
8. [Future Database Mapping Strategy](#8-future-database-mapping-strategy)
9. [Top 50 Most Valuable Fields](#9-top-50-most-valuable-fields)
10. [Proof Section](#10-proof-section)

---

## 1. Executive Summary

### Field Universe

| Metric | Count |
|---|---|
| Total unique Stellar/Bridge fields | 553 |
| Reliably populated (≥50% of 25-record sample) | 217 |
| Sparsely populated (<50%) | 44 |
| Always empty in sample | 292 |
| Compliance-restricted (must never be used publicly) | 23 |

### Tier Distribution

| Tier | Label | Count |
|---|---|---|
| 1 | Core Matching | 66 |
| 2 | Match Enhancement | 85 |
| 3 | Ask AI Context | 116 |
| 4 | Alert & Recommendation | 51 |
| 5 | Search Filters (primary) | 12 |
| 6 | Compliance / Excluded | 223 |
| **Total** | | **553** |

### Native `bridge_properties` Column Coverage

The current `bridge_properties` table stores **13 native columns** mapped from Stellar fields:

| Native Column | Stellar Field | Tier |
|---|---|---|
| `listing_key` | `ListingKey` | 4 |
| `listing_id` | `ListingId` | 4 |
| `standard_status` | `StandardStatus` | 1 |
| `property_type` | `PropertyType` | 1 |
| `list_price` | `ListPrice` | 1 |
| `unparsed_address` | `UnparsedAddress` | 3 |
| `city` | `City` | 1 |
| `state_or_province` | `StateOrProvince` | 1 |
| `postal_code` | `PostalCode` | 1 |
| `bedrooms_total` | `BedroomsTotal` | 1 |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | 1 |
| `living_area` | `LivingArea` | 1 |
| `modification_timestamp` | `ModificationTimestamp` | 4 |

Of the 66 Tier 1 Core Matching fields, **9 are already native columns**. The remaining 57 Tier 1 fields live only in `raw_json` and must be queried with JSON extraction functions — a significant gap for any matching engine.

### Top-Level Recommendations

1. **Promote 12 high-frequency Tier 1 fields to native columns before building the matching engine** — `latitude`, `longitude`, `county_or_parish`, `lot_size_square_feet`, `garage_yn`, `pool_private_yn`, `waterfront_yn`, `year_built`, `pets_allowed`, `association_fee`, `new_construction_yn`, and `mls_status`. These are reliably populated (≥88% of sample) and are the most queried match dimensions.

2. **Rental matching cannot run at all without JSON extraction** — `LeaseConsideredYN`, `LeaseTerm`, `Furnished`, `PetsAllowed`, `LeaseAmountFrequency`, and all `STELLAR_*` rental-specific fields are raw JSON only. Tenant matching requires either column promotion or a dedicated JSON-query layer.

3. **40 Tier 1 fields are always empty in the current sample** — These are important fields (investment income, condo fees, security deposits, pet fees) that exist in the MLS schema but are not populated for the residential-for-sale sample fetched. They will appear in rental/investment/condo feeds and must be included in the matching model despite zero current data.

4. **School district data is a structural gap** — `SchoolDistrict`, `ElementarySchool`, `MiddleOrJuniorSchool`, and `HighSchool` are present in the Stellar schema but inconsistently populated and not normalized. `HighSchoolDistrict` is always empty. Buyer/tenant matching on schools requires a supplemental geocode-to-district lookup service.

5. **Flood zone is sparsely populated and inconsistent** — `STELLAR_FloodZoneCode` is 17/25 populated but the value format ("X", "AE", etc.) is FEMA zone code, not a human-readable risk label. A zone-code-to-risk-tier translation table is needed before this can drive matching or Ask AI answers.

6. **Walk score, transit score, and neighborhood ratings are completely absent** — There is no Stellar field for walkability or transit access. If these are important for buyer/tenant matching, a third-party API (Walk Score API) must be integrated separately.

7. **Compliance boundary is clear** — The 23 explicitly flagged fields plus an additional ~200 agent/brokerage/admin/commercial fields are classified Tier 6. No Tier 6 field should appear in any match score, Ask AI response, alert copy, or public recommendation.

### Data Quality Concerns

- **`BathroomsTotalInteger` vs `BathroomsTotalDecimal`**: Both are populated for every record. Use `BathroomsTotalInteger` as the canonical match field; `Decimal` is a display convenience only.
- **`LotSizeAcres` vs `LotSizeSquareFeet` vs `LotSizeArea`**: All three are populated and numerically consistent. Use `LotSizeSquareFeet` as the canonical match dimension (most precise); the others are redundant.
- **`MlsStatus` vs `StandardStatus`**: Both present and populated. `StandardStatus` uses RESO standard vocabulary (`Active`, `Closed`, `Pending`). `MlsStatus` uses board-specific values (`Sold`, etc.). For matching and alerts, always prefer `StandardStatus`.
- **`PublicRemarks` vs `STELLAR_PublicRemarksAgent`**: In the sample, these contain identical text. `STELLAR_PublicRemarksAgent` appears to be the MLS-internal copy. Use `PublicRemarks` as the canonical Ask AI text source.
- **`AssociationFee` vs `STELLAR_MonthlyHOAAmount`**: Both present and matching values (60 in sample). `AssociationFee` is the RESO standard; use it as canonical. `STELLAR_MonthlyHOAAmount` is a redundant cross-check.
- **`STELLAR_CDDYN` is a boolean flag with no associated CDD amount in populated records** — the CDD fee amount would need to come from `TaxOtherAnnualAssessmentAmount` or a separate field.

---

## 2. Complete Field Classification Table

> **Tier legend:**
> 1 = Core Matching · 2 = Match Enhancement · 3 = Ask AI Context · 4 = Alert & Recommendation · 5 = Search Filter (primary) · 6 = Compliance/Excluded

| Field Key | Category | Population | Primary Tier | Notes |
|---|---|---|---|---|
| `@odata.id` | Unknown | 25/25 | 6 | Internal API URL; no data value |
| `AccessibilityFeatures` | Interior Features | 0/25 | 2 | When populated, enhances accessibility matching |
| `AdditionalParcelsDescription` | Unknown | 0/25 | 6 | Legal/admin; not user-facing |
| `AdditionalParcelsYN` | Unknown | 25/25 | 6 | Admin flag; not matching-relevant |
| `Appliances` | Interior Features | 25/25 | 2 | Amenity enhancement for buyers |
| `ApprovalStatus` | Unknown | 0/25 | 6 | Internal MLS admin |
| `ArchitecturalStyle` | Exterior/Lot | 15/25 | 2 | Preference matching enhancement |
| `AssociationAmenities` | HOA/Fees | 18/25 | 2 | Community feature enhancement |
| `AssociationFee` | Price | 22/25 | 1 | Monthly HOA cost — financial match dimension |
| `AssociationFee2` | Price | 0/25 | 2 | Secondary HOA; when populated adds to total cost |
| `AssociationFee2Frequency` | Price | 0/25 | 3 | Ask AI context for HOA cost questions |
| `AssociationFeeFrequency` | Price | 23/25 | 3 | Ask AI context (monthly/annual qualifier) |
| `AssociationFeeIncludes` | HOA/Fees | 6/25 | 2 | Enhances HOA value assessment |
| `AssociationName` | HOA/Fees | 0/25 | 3 | Ask AI — "what HOA manages this?" |
| `AssociationName2` | HOA/Fees | 0/25 | 3 | Ask AI |
| `AssociationPhone` | HOA/Fees | 0/25 | 3 | HOA contact (not agent PII); Ask AI |
| `AssociationPhone2` | HOA/Fees | 0/25 | 3 | HOA contact; Ask AI |
| `AssociationYN` | HOA/Fees | 25/25 | 1 | Core financial match — does HOA exist |
| `AttachedGarageYN` | Exterior/Lot | 25/25 | 2 | Garage detail enhancement |
| `AvailabilityDate` | Lease/Rental | 0/25 | 1 | Core for tenant matching — when can I move in |
| `Basement` | Interior Features | 0/25 | 2 | Feature when present |
| `BathroomsFull` | Interior Features | 25/25 | 1 | Core size dimension |
| `BathroomsHalf` | Interior Features | 25/25 | 1 | Core size dimension |
| `BathroomsOneQuarter` | Interior Features | 0/25 | 2 | Rare; enhancement when present |
| `BathroomsPartial` | Unknown | 0/25 | 2 | Redundant bath count; enhancement |
| `BathroomsThreeQuarter` | Interior Features | 0/25 | 2 | Enhancement when present |
| `BathroomsTotalDecimal` | Interior Features | 25/25 | 6 | Derived from BathroomsTotalInteger; redundant |
| `BathroomsTotalInteger` | Interior Features | 25/25 | 1 | Core size dimension (canonical bath count) |
| `BedroomsTotal` | Interior Features | 25/25 | 1 | Core size dimension |
| `BodyType` | Unknown | 0/25 | 6 | Mobile home type; not relevant to residential |
| `BridgeModificationTimestamp` | Property Basics | 25/25 | 4 | Alert — record sync recency |
| `BuilderModel` | Unknown | 0/25 | 3 | Ask AI — "what model is this home?" |
| `BuilderName` | Unknown | 0/25 | 3 | Ask AI — builder reputation context |
| `BuildingAreaSource` | Unknown | 25/25 | 6 | Metadata label; no match value |
| `BuildingAreaTotal` | Interior Features | 9/25 | 2 | Total building area (sparse); size enhancement |
| `BuildingAreaUnits` | Interior Features | 9/25 | 6 | Metadata label |
| `BuildingFeatures` | Unknown | 0/25 | 2 | Commercial/condo feature enhancement |
| `BusinessName` | Unknown | 0/25 | 6 | Commercial only |
| `BusinessType` | Unknown | 0/25 | 6 | Commercial only |
| `BuyerAgentAOR` | Unknown | 25/25 | 6 | Agent admin data |
| `BuyerAgentFirstName` | Unknown | 0/25 | 6 | Agent PII |
| `BuyerAgentFullName` | Agent/Brokerage | 25/25 | 6 | Agent PII; not relevant to buyers searching |
| `BuyerAgentKeyNumeric` | Agent/Brokerage | 25/25 | 6 | Agent admin ID |
| `BuyerAgentLastName` | Unknown | 0/25 | 6 | Agent PII |
| `BuyerAgentMlsId` | Unknown | 25/25 | 6 | Agent admin ID |
| `BuyerAgentStateLicense` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `BuyerOfficeKeyNumeric` | Unknown | 25/25 | 6 | Brokerage admin ID |
| `BuyerOfficeMlsId` | Unknown | 25/25 | 6 | Brokerage admin ID |
| `BuyerOfficeName` | Agent/Brokerage | 25/25 | 6 | Brokerage info; not property attribute |
| `BuyerTeamName` | Unknown | 0/25 | 6 | Agent admin data |
| `CapRate` | Financial/Investment | 0/25 | 1 | Core for investment property matching |
| `CarportSpaces` | Exterior/Lot | 0/25 | 5 | Search filter only; not a score driver |
| `CarportYN` | Exterior/Lot | 0/25 | 5 | Search filter only |
| `City` | Location | 25/25 | 1 | **Core location dimension** — native column |
| `CloseDate` | Property Basics | 25/25 | 4 | Alert — historical transaction date |
| `ClosePrice` | Price | 25/25 | 3 | Ask AI — "what did comparable homes sell for?" |
| `CoBuyerAgentFirstName` | Unknown | 0/25 | 6 | Agent PII |
| `CoBuyerAgentFullName` | Agent/Brokerage | 0/25 | 6 | Agent PII |
| `CoBuyerAgentKeyNumeric` | Agent/Brokerage | 0/25 | 6 | Agent admin ID |
| `CoBuyerAgentLastName` | Unknown | 0/25 | 6 | Agent PII |
| `CoBuyerAgentMlsId` | Unknown | 0/25 | 6 | Agent admin ID |
| `CoBuyerAgentStateLicense` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `CoBuyerOfficeKeyNumeric` | Unknown | 0/25 | 6 | Brokerage admin ID |
| `CoBuyerOfficeMlsId` | Unknown | 0/25 | 6 | Brokerage admin ID |
| `CoBuyerOfficeName` | Agent/Brokerage | 0/25 | 6 | Brokerage info |
| `CoListAgentFirstName` | Unknown | 0/25 | 6 | Agent PII |
| `CoListAgentFullName` | Agent/Brokerage | 12/25 | 6 | Agent PII |
| `CoListAgentKeyNumeric` | Agent/Brokerage | 1/25 | 6 | Agent admin ID |
| `CoListAgentLastName` | Unknown | 0/25 | 6 | Agent PII |
| `CoListAgentMlsId` | Agent/Brokerage | 1/25 | 6 | Agent admin ID |
| `CoListAgentStateLicense` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `CoListOfficeKeyNumeric` | Agent/Brokerage | 1/25 | 6 | Brokerage admin ID |
| `CoListOfficeMlsId` | Agent/Brokerage | 1/25 | 6 | Brokerage admin ID |
| `CoListOfficeName` | Agent/Brokerage | 2/25 | 6 | Brokerage info |
| `CommonWalls` | Unknown | 0/25 | 2 | Condo/townhouse feature enhancement |
| `CommunityFeatures` | HOA/Fees | 23/25 | 2 | Community amenity enhancement |
| `Concessions` | Unknown | 25/25 | 3 | Ask AI — seller concession context |
| `ConcessionsAmount` | Unknown | 25/25 | 3 | Ask AI — dollar value of concessions |
| `ConstructionMaterials` | Exterior/Lot | 25/25 | 2 | Property quality enhancement |
| `ContractStatusChangeDate` | Unknown | 25/25 | 4 | Alert — contract status event |
| `Cooling` | Interior Features | 25/25 | 2 | HVAC feature enhancement |
| `CoolingYN` | Interior Features | 25/25 | 6 | Redundant boolean derived from Cooling array |
| `Coordinates` | Unknown | 25/25 | 6 | Array duplicate of Latitude/Longitude |
| `CopyrightNotice` | Unknown | 0/25 | 6 | Legal notice; no data value |
| `Country` | Location | 0/25 | 6 | Always US for Stellar; zero match value |
| `CountyOrParish` | Location | 25/25 | 1 | Core location dimension |
| `CoveredSpaces` | Unknown | 25/25 | 5 | Total covered parking; search filter |
| `CrossStreet` | Location | 0/25 | 3 | Ask AI — directions context |
| `CumulativeDaysOnMarket` | Property Basics | 25/25 | 4 | Alert — cumulative market time |
| `CurrentUse` | Unknown | 0/25 | 2 | Land use type; enhancement for land/mixed use |
| `DOH1` | Unknown | 0/25 | 6 | Mobile home Dept of Housing ID |
| `DOH2` | Unknown | 0/25 | 6 | Mobile home Dept of Housing ID |
| `DOH3` | Unknown | 0/25 | 6 | Mobile home Dept of Housing ID |
| `DaysOnMarket` | Property Basics | 25/25 | 4 | Alert — listing freshness |
| `DirectionFaces` | Unknown | 18/25 | 2 | Facing direction; preference enhancement |
| `Directions` | Location | 25/25 | 3 | Ask AI — how to find property |
| `Disclosures` | Unknown | 0/25 | 3 | Ask AI — disclosure context |
| `DocumentsCount` | Media | 25/25 | 4 | Listing completeness signal |
| `DoorFeatures` | Unknown | 0/25 | 2 | Interior feature enhancement |
| `Electric` | Exterior/Lot | 0/25 | 2 | Utility availability; enhancement |
| `ElementarySchool` | Unknown | 20/25 | 3 | Ask AI — school info (not normalized enough for match scoring) |
| `Exclusions` | Unknown | 0/25 | 3 | Ask AI — what is not included in sale |
| `ExteriorFeatures` | Exterior/Lot | 22/25 | 2 | Outdoor feature enhancement |
| `FeedTypes` | Unknown | 25/25 | 6 | MLS syndication admin |
| `Fencing` | Exterior/Lot | 0/25 | 5 | Search filter — pet owners, privacy seekers |
| `FireplaceFeatures` | Interior Features | 0/25 | 2 | Feature enhancement when present |
| `FireplaceYN` | Interior Features | 25/25 | 2 | Interior feature enhancement |
| `FireplacesTotal` | Interior Features | 0/25 | 2 | Fireplace count detail |
| `Flooring` | Interior Features | 25/25 | 5 | Search filter — material preference |
| `FoundationDetails` | Exterior/Lot | 25/25 | 2 | Construction quality enhancement |
| `FrontageLength` | Unknown | 0/25 | 2 | Lot shape detail; enhancement |
| `Furnished` | Lease/Rental | 9/25 | 1 | Core tenant match dimension |
| `GarageSpaces` | Exterior/Lot | 25/25 | 1 | Core size/feature dimension for buyers |
| `GarageYN` | Exterior/Lot | 25/25 | 1 | Core feature dimension |
| `Gas` | Unknown | 0/25 | 2 | Utility feature; enhancement |
| `GreenBuildingVerificationType` | Unknown | 24/25 | 2 | Green/efficiency enhancement |
| `GreenEnergyEfficient` | Unknown | 23/25 | 2 | Energy efficiency enhancement |
| `GreenEnergyGeneration` | Unknown | 0/25 | 2 | Solar/generation enhancement |
| `GreenIndoorAirQuality` | Unknown | 15/25 | 2 | Health/air quality enhancement |
| `GreenLocation` | Unknown | 0/25 | 2 | Green location features |
| `GreenSustainability` | Unknown | 0/25 | 2 | Sustainability features |
| `GreenWaterConservation` | Unknown | 15/25 | 2 | Water conservation features |
| `GrossIncome` | Unknown | 0/25 | 1 | Core for investment matching (when present) |
| `GrossScheduledIncome` | Financial/Investment | 0/25 | 1 | Core for investment matching (when present) |
| `Heating` | Interior Features | 25/25 | 2 | HVAC feature enhancement |
| `HeatingYN` | Interior Features | 25/25 | 6 | Redundant boolean; derived from Heating |
| `HighSchool` | Unknown | 21/25 | 3 | Ask AI — school info |
| `HighSchoolDistrict` | Unknown | 0/25 | 3 | Ask AI — school district (gap field) |
| `HomeWarrantyYN` | Unknown | 25/25 | 3 | Ask AI — seller offering warranty |
| `HorseAmenities` | Unknown | 0/25 | 2 | Niche equestrian feature |
| `IDXParticipationYN` | Unknown | 25/25 | 6 | MLS display rights flag |
| `InteriorFeatures` | Interior Features | 24/25 | 2 | Rich interior amenity enhancement |
| `InternetAddressDisplayYN` | Unknown | 25/25 | 6 | MLS display setting |
| `InternetAutomatedValuationDisplayYN` | Unknown | 25/25 | 6 | MLS display setting |
| `InternetConsumerCommentYN` | Unknown | 24/25 | 6 | MLS display setting |
| `InternetEntireListingDisplayYN` | Unknown | 25/25 | 6 | MLS display setting |
| `LandLeaseAmount` | Price | 0/25 | 3 | Ask AI — land lease cost context |
| `LandLeaseYN` | Unknown | 0/25 | 3 | Ask AI — is land leased |
| `Latitude` | Location | 25/25 | 1 | Core geo-matching dimension |
| `LaundryFeatures` | Interior Features | 13/25 | 5 | Search filter — in-unit vs shared laundry |
| `LeaseAmountFrequency` | Price | 0/25 | 1 | Core rental pricing dimension (freq qualifier) |
| `LeaseConsideredYN` | Lease/Rental | 0/25 | 1 | Core rental-vs-sale separation |
| `LeaseTerm` | Lease/Rental | 0/25 | 1 | Core tenant match — minimum lease length |
| `Levels` | Interior Features | 25/25 | 2 | Stories/floors enhancement |
| `License1` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `License2` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `License3` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `ListAOR` | Unknown | 25/25 | 6 | MLS admin |
| `ListAgentAOR` | Unknown | 25/25 | 6 | MLS admin |
| `ListAgentEmail` | Compliance/Restricted | 25/25 | 6 | **COMPLIANCE** — email address |
| `ListAgentFirstName` | Unknown | 0/25 | 6 | Agent PII |
| `ListAgentFullName` | Agent/Brokerage | 25/25 | 6 | Agent info; not property attribute |
| `ListAgentKey` | Agent/Brokerage | 25/25 | 6 | Agent admin key |
| `ListAgentLastName` | Unknown | 0/25 | 6 | Agent PII |
| `ListAgentMlsId` | Agent/Brokerage | 25/25 | 6 | Agent admin ID |
| `ListAgentPreferredPhone` | Compliance/Restricted | 25/25 | 6 | **COMPLIANCE** — phone number |
| `ListAgentStateLicense` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `ListOfficeKey` | Agent/Brokerage | 25/25 | 6 | Brokerage admin key |
| `ListOfficeMlsId` | Agent/Brokerage | 25/25 | 6 | Brokerage admin ID |
| `ListOfficeName` | Agent/Brokerage | 25/25 | 6 | Brokerage name; not property attribute |
| `ListOfficePhone` | Compliance/Restricted | 25/25 | 6 | **COMPLIANCE** — phone number |
| `ListPrice` | Price | 25/25 | 1 | **Core price dimension** — native column |
| `ListTeamName` | Unknown | 2/25 | 6 | Agent team admin |
| `ListingContractDate` | Property Basics | 25/25 | 4 | Alert — listing start date |
| `ListingId` | Property Basics | 25/25 | 4 | Alert identifier — native column |
| `ListingKey` | Property Basics | 25/25 | 4 | Primary record identifier — native column |
| `ListingKeyNumeric` | Property Basics | 25/25 | 4 | Numeric listing identifier |
| `ListingTerms` | Unknown | 24/25 | 3 | Ask AI — accepted financing types |
| `LivingArea` | Interior Features | 25/25 | 1 | **Core size dimension** — native column |
| `LivingAreaSource` | Interior Features | 25/25 | 6 | Metadata label |
| `LivingAreaUnits` | Interior Features | 25/25 | 6 | Metadata label |
| `LockBoxLocation` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — lockbox access |
| `LockBoxSerialNumber` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — lockbox access |
| `LockBoxType` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — lockbox access |
| `Longitude` | Location | 25/25 | 1 | Core geo-matching dimension |
| `LotFeatures` | Exterior/Lot | 25/25 | 2 | Lot characteristic enhancement |
| `LotSizeAcres` | Exterior/Lot | 25/25 | 1 | Core lot size dimension (acreage) |
| `LotSizeArea` | Exterior/Lot | 25/25 | 6 | Redundant with LotSizeSquareFeet; avoid duplication |
| `LotSizeDimensions` | Unknown | 19/25 | 3 | Ask AI — "what are the lot dimensions?" |
| `LotSizeSquareFeet` | Exterior/Lot | 25/25 | 1 | Core lot size dimension (canonical sqft) |
| `LotSizeUnits` | Exterior/Lot | 25/25 | 6 | Metadata label |
| `MLSAreaMajor` | Location | 25/25 | 1 | MLS sub-market area — location dimension |
| `Make` | Unknown | 0/25 | 6 | Mobile home make; not relevant |
| `MaloneId` | Unknown | 0/25 | 6 | Obscure admin ID |
| `Media` | Media | 25/25 | 4 | Photo array — listing quality/recommendation |
| `MiddleOrJuniorSchool` | Unknown | 21/25 | 3 | Ask AI — school info |
| `MlsStatus` | Property Basics | 25/25 | 1 | Status dimension — board-specific vocabulary |
| `MobileLength` | Unknown | 0/25 | 6 | Mobile home dimension |
| `MobileWidth` | Unknown | 0/25 | 6 | Mobile home dimension |
| `Model` | Unknown | 3/25 | 3 | Ask AI — builder model name |
| `ModificationTimestamp` | Property Basics | 25/25 | 4 | Alert recency — native column |
| `NewConstructionYN` | Exterior/Lot | 25/25 | 1 | Core feature for buyer matching |
| `NumberOfBuildings` | Unknown | 0/25 | 6 | Commercial only |
| `NumberOfLots` | Unknown | 0/25 | 3 | Ask AI — multi-lot context |
| `NumberOfPads` | Unknown | 0/25 | 6 | Mobile home/commercial |
| `NumberOfSeparateElectricMeters` | Exterior/Lot | 0/25 | 6 | Commercial/investment admin |
| `NumberOfSeparateGasMeters` | Unknown | 0/25 | 6 | Commercial/investment admin |
| `NumberOfSeparateWaterMeters` | Unknown | 0/25 | 6 | Commercial/investment admin |
| `NumberOfUnitsLeased` | Lease/Rental | 0/25 | 3 | Ask AI — investment occupancy context |
| `NumberOfUnitsTotal` | Interior Features | 0/25 | 3 | Ask AI — multi-unit context |
| `OccupantType` | Unknown | 12/25 | 3 | Ask AI — vacancy/tenant status |
| `OffMarketDate` | Property Basics | 25/25 | 4 | Alert — listing end date |
| `OffMarketTimestamp` | Unknown | 25/25 | 4 | Alert — precise off-market time |
| `OnMarketDate` | Property Basics | 0/25 | 4 | Alert — listing live date |
| `OpenParkingSpaces` | Unknown | 0/25 | 5 | Search filter — open parking count |
| `OpenParkingYN` | Exterior/Lot | 1/25 | 5 | Search filter — open parking available |
| `OriginalEntryTimestamp` | Property Basics | 25/25 | 4 | Alert — when listing was first entered |
| `OriginalListPrice` | Price | 25/25 | 1 | Price change tracking — delta from original |
| `OriginatingSystemKey` | Agent/Brokerage | 25/25 | 6 | Data source admin |
| `OriginatingSystemName` | Agent/Brokerage | 25/25 | 6 | Data source admin |
| `OtherParking` | Unknown | 0/25 | 2 | Additional parking detail enhancement |
| `OtherStructures` | Exterior/Lot | 0/25 | 2 | Guest house/barn/etc. enhancement |
| `OwnerPays` | Unknown | 0/25 | 3 | Ask AI — which utilities owner pays |
| `Ownership` | Unknown | 25/25 | 3 | Ask AI — fee simple, condo, co-op, etc. |
| `ParcelNumber` | Location | 25/25 | 6 | Tax/legal admin ID |
| `ParkName` | Unknown | 0/25 | 3 | Ask AI — mobile home/RV park name |
| `ParkingFeatures` | Exterior/Lot | 10/25 | 2 | Parking type/detail enhancement |
| `PatioAndPorchFeatures` | Exterior/Lot | 15/25 | 2 | Outdoor living enhancement |
| `PetsAllowed` | Lease/Rental | 24/25 | 1 | Core tenant/rental match dimension |
| `PhotosChangeTimestamp` | Media | 25/25 | 4 | Alert — new photos signal |
| `PhotosCount` | Media | 25/25 | 4 | Listing quality/recommendation signal |
| `PoolFeatures` | Exterior/Lot | 2/25 | 2 | Pool type/detail enhancement |
| `PoolPrivateYN` | Exterior/Lot | 25/25 | 1 | Core feature dimension |
| `Possession` | Unknown | 0/25 | 3 | Ask AI — when can buyer/tenant move in |
| `PossibleUse` | Unknown | 0/25 | 2 | Zoning/use case enhancement |
| `PostalCode` | Location | 25/25 | 1 | **Core location dimension** — native column |
| `PostalCodePlus4` | Location | 17/25 | 6 | Too granular; admin use only |
| `PreviousListPrice` | Price | 22/25 | 4 | Alert — price reduction tracking |
| `PriceChangeTimestamp` | Price | 22/25 | 4 | Alert — when price was reduced |
| `PropertyAttachedYN` | Unknown | 0/25 | 2 | Attached/detached type enhancement |
| `PropertyCondition` | Exterior/Lot | 23/25 | 2 | Condition enhancement (new/under construction/etc.) |
| `PropertySubType` | Property Basics | 25/25 | 1 | Core type dimension |
| `PropertyType` | Property Basics | 25/25 | 1 | **Core type dimension** — native column |
| `PublicRemarks` | Unknown | 25/25 | 3 | Ask AI — primary free-text source |
| `PublicSurveyRange` | Location | 25/25 | 6 | Legal survey data; no match value |
| `PublicSurveySection` | Location | 25/25 | 6 | Legal survey data |
| `PublicSurveyTownship` | Location | 25/25 | 6 | Legal survey data |
| `PurchaseContractDate` | Property Basics | 25/25 | 4 | Alert — when property went under contract |
| `RentIncludes` | Price | 0/25 | 1 | Core tenant match — what is included in rent |
| `RoadFrontageType` | Unknown | 25/25 | 2 | Lot access type enhancement |
| `RoadResponsibility` | Unknown | 0/25 | 2 | Maintenance responsibility enhancement |
| `RoadSurfaceType` | Unknown | 24/25 | 2 | Road type enhancement (rural buyers) |
| `Roof` | Exterior/Lot | 25/25 | 2 | Construction quality enhancement |
| `RoomType` | Unknown | 0/25 | 2 | Room breakdown detail |
| `RoomsTotal` | Interior Features | 25/25 | 2 | Total room count enhancement |
| `STELLAR_ActiveOpenHouseCount` | Unknown | 5/25 | 4 | Alert — active open houses |
| `STELLAR_AdditionalApplicantFee` | Unknown | 0/25 | 3 | Ask AI — rental application fee |
| `STELLAR_AdditionalLeaseRestrictions` | Unknown | 0/25 | 3 | Ask AI — HOA/landlord restrictions |
| `STELLAR_AdditionalMembershipAvailableYN` | Unknown | 0/25 | 3 | Ask AI — club membership context |
| `STELLAR_AdditionalPetFees` | Unknown | 0/25 | 1 | Core rental cost — pet fees |
| `STELLAR_AdditionalRooms` | Unknown | 25/25 | 2 | Flex room / den / office enhancement |
| `STELLAR_AdditionalWaterInformation` | Unknown | 0/25 | 3 | Ask AI — waterfront details |
| `STELLAR_AdjoiningProperty` | Unknown | 0/25 | 3 | Ask AI — neighboring parcel context |
| `STELLAR_AffidavitYN` | Unknown | 0/25 | 6 | Legal/compliance admin |
| `STELLAR_AlternateKeyFolioNum` | Unknown | 0/25 | 6 | Tax/admin alternate key |
| `STELLAR_AmenitiesAdditionalFees` | Unknown | 0/25 | 3 | Ask AI — HOA amenity fees |
| `STELLAR_AnnualExpenses` | Unknown | 0/25 | 1 | Core investment matching dimension |
| `STELLAR_AnnualIncomeType` | Unknown | 0/25 | 3 | Ask AI — investment income context |
| `STELLAR_AnnualNetIncome` | Unknown | 0/25 | 1 | Core investment matching dimension |
| `STELLAR_AnnualRent` | Unknown | 0/25 | 1 | Core rental pricing dimension |
| `STELLAR_ApplicationFee` | Unknown | 0/25 | 3 | Ask AI — tenant application cost |
| `STELLAR_ApprovalProcess` | Unknown | 0/25 | 3 | Ask AI — HOA/condo approval process |
| `STELLAR_AssociationApplicationFee` | Unknown | 0/25 | 3 | Ask AI — HOA application cost |
| `STELLAR_AssociationApprovalFee` | Unknown | 0/25 | 3 | Ask AI — HOA approval cost |
| `STELLAR_AssociationApprovalRequiredYN` | Unknown | 0/25 | 3 | Ask AI — HOA pre-approval required |
| `STELLAR_AssociationEmail` | HOA/Fees | 0/25 | 3 | Ask AI — HOA contact (HOA email, not agent) |
| `STELLAR_AssociationFeeRequirement` | Price | 25/25 | 2 | HOA requirement status enhancement |
| `STELLAR_AssociationURL` | Unknown | 0/25 | 3 | Ask AI — HOA website link |
| `STELLAR_AuctionFirmURL` | Unknown | 0/25 | 6 | Auction admin (not standard listing flow) |
| `STELLAR_AuctionPropAccessYN` | Unknown | 0/25 | 6 | Auction admin |
| `STELLAR_AuctionTime` | Unknown | 0/25 | 6 | Auction admin |
| `STELLAR_AuctionType` | Unknown | 0/25 | 6 | Auction admin |
| `STELLAR_BOMDate` | Unknown | 0/25 | 4 | Alert — Back on Market date |
| `STELLAR_BackupsRequestedYN` | Unknown | 1/25 | 6 | Agent-to-agent workflow; no public value |
| `STELLAR_BarnFeatures` | Unknown | 0/25 | 2 | Equestrian/agricultural feature |
| `STELLAR_BuilderLicenseNumber` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — license number |
| `STELLAR_BuildingElevatorYN` | Unknown | 0/25 | 2 | Accessibility/condo elevator feature |
| `STELLAR_BuildingNameNumber` | Unknown | 1/25 | 3 | Ask AI — building name for condos |
| `STELLAR_BusinessOpportunityWithRealEstateYN` | Unknown | 0/25 | 3 | Ask AI — mixed-use context |
| `STELLAR_BuyersCountryofResidence` | Location | 25/25 | 6 | Buyer demographic; not property attribute |
| `STELLAR_BuyersIntendedUse` | Unknown | 24/25 | 6 | Buyer intent; not property attribute |
| `STELLAR_BuyersPremium` | Unknown | 0/25 | 6 | Auction term; not standard listing |
| `STELLAR_BuyersZipCode` | Unknown | 25/25 | 6 | Buyer demographic; not property attribute |
| `STELLAR_CDDYN` | Unknown | 25/25 | 1 | Core Florida financial dimension — CDD district |
| `STELLAR_CalculatedListPriceByCalculatedSqFt` | Price | 25/25 | 3 | Ask AI — price per sqft context |
| `STELLAR_CallCenterPhoneNumber` | Compliance/Restricted | 17/25 | 6 | **COMPLIANCE** — phone number |
| `STELLAR_CeilingHeight` | Unknown | 0/25 | 2 | Interior feature enhancement |
| `STELLAR_CeilingType` | Unknown | 0/25 | 2 | Interior feature enhancement |
| `STELLAR_CensusBlock` | Unknown | 0/25 | 6 | Census admin |
| `STELLAR_CensusTract` | Unknown | 0/25 | 6 | Census admin |
| `STELLAR_ClosePriceByCalculatedListPriceRatio` | Price | 25/25 | 3 | Ask AI — sale-to-list ratio context |
| `STELLAR_ClosePriceByCalculatedSqFt` | Price | 25/25 | 3 | Ask AI — close price per sqft |
| `STELLAR_ComTransactionTerms` | Unknown | 0/25 | 6 | Commercial transaction admin |
| `STELLAR_ComTransactionType` | Unknown | 0/25 | 6 | Commercial transaction admin |
| `STELLAR_ComingSoonDate` | Unknown | 0/25 | 4 | Alert — coming soon launch date |
| `STELLAR_ComplexCommunityNameNCCB` | Unknown | 0/25 | 3 | Ask AI — condo complex name |
| `STELLAR_ComplexDevelopmentName` | Unknown | 0/25 | 3 | Ask AI — development name |
| `STELLAR_ConditionExpDate` | Unknown | 0/25 | 6 | Contract condition expiry — agent admin |
| `STELLAR_CondoEnvironmentYN` | Unknown | 0/25 | 2 | Condo type enhancement |
| `STELLAR_CondoFees` | Unknown | 0/25 | 1 | Core condo financial matching dimension |
| `STELLAR_CondoFeesTerm` | Unknown | 0/25 | 3 | Ask AI — condo fee frequency |
| `STELLAR_CondoLandIncludedYN` | Unknown | 0/25 | 3 | Ask AI — land included in condo purchase |
| `STELLAR_ContractStatus` | Unknown | 25/25 | 4 | Alert — contingency status |
| `STELLAR_ConvertedResidenceYN` | Unknown | 0/25 | 3 | Ask AI — converted from commercial context |
| `STELLAR_CountyLandUseCode` | Unknown | 0/25 | 6 | Admin code |
| `STELLAR_CountyPropertyUseCode` | Unknown | 0/25 | 6 | Admin code |
| `STELLAR_CreateAutomaticVirtualTourYN` | Unknown | 25/25 | 6 | MLS admin setting |
| `STELLAR_CurrencyMonthlyRentAmt` | Unknown | 0/25 | 1 | Core rental pricing dimension (monthly amount) |
| `STELLAR_CurrentAdjacentUse` | Unknown | 0/25 | 3 | Ask AI — neighboring land use |
| `STELLAR_CurrentPrice` | Unknown | 25/25 | 4 | Alert — current effective price |
| `STELLAR_DPRURL` | Unknown | 22/25 | 3 | Ask AI — down payment assistance resource URL |
| `STELLAR_DPRURL2` | Unknown | 22/25 | 3 | Ask AI — secondary DPR URL |
| `STELLAR_DPRYN` | Unknown | 22/25 | 3 | Ask AI — eligible for DPR program |
| `STELLAR_DaysNoticeToTenantIfNotRenew` | Unknown | 0/25 | 3 | Ask AI — lease non-renewal notice period |
| `STELLAR_DaysToClosed` | Unknown | 25/25 | 4 | Alert — market speed indicator |
| `STELLAR_DaystoContract` | Unknown | 25/25 | 4 | Alert — time to contract metric |
| `STELLAR_DepositsYN` | Unknown | 0/25 | 3 | Ask AI — are deposits required |
| `STELLAR_Development` | Unknown | 0/25 | 3 | Ask AI — development/community name |
| `STELLAR_DisasterMitigation` | Unknown | 13/25 | 2 | Fire/flood mitigation enhancement |
| `STELLAR_DockDescrip` | Unknown | 0/25 | 3 | Ask AI — dock details for waterfront |
| `STELLAR_DockDimensions` | Unknown | 0/25 | 3 | Ask AI — dock size |
| `STELLAR_DockLiftCap` | Unknown | 0/25 | 3 | Ask AI — boat lift capacity |
| `STELLAR_DockMntncFee` | Unknown | 0/25 | 3 | Ask AI — dock maintenance fee |
| `STELLAR_DockMntncFeeFrqncy` | Unknown | 0/25 | 3 | Ask AI — dock fee frequency |
| `STELLAR_DockYN` | Unknown | 0/25 | 2 | Dock feature enhancement for waterfront |
| `STELLAR_DockYrBlt` | Unknown | 0/25 | 3 | Ask AI — dock age |
| `STELLAR_DoorHeight` | Unknown | 0/25 | 2 | Commercial/accessibility feature |
| `STELLAR_DoorWidth` | Unknown | 0/25 | 2 | Commercial/accessibility feature |
| `STELLAR_Easements` | Unknown | 0/25 | 3 | Ask AI — easement disclosures |
| `STELLAR_EavesHeight` | Unknown | 0/25 | 6 | Commercial warehouse spec |
| `STELLAR_EscrowAgentEmail` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — email address |
| `STELLAR_EscrowAgentFax` | Unknown | 0/25 | 6 | Escrow admin contact |
| `STELLAR_EscrowAgentName` | Unknown | 0/25 | 6 | Escrow admin |
| `STELLAR_EscrowAgentPhone` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — phone number |
| `STELLAR_EscrowCity` | Location | 0/25 | 6 | Escrow admin address |
| `STELLAR_EscrowCompany` | Unknown | 0/25 | 6 | Escrow admin |
| `STELLAR_EscrowPostalCode` | Location | 0/25 | 6 | Escrow admin address |
| `STELLAR_EscrowState` | Unknown | 0/25 | 6 | Escrow admin address |
| `STELLAR_EscrowStreetName` | Location | 0/25 | 6 | Escrow admin address |
| `STELLAR_EscrowStreetNumber` | Location | 0/25 | 6 | Escrow admin address |
| `STELLAR_EstAnnualMarketIncome` | Unknown | 0/25 | 1 | Core investment matching dimension |
| `STELLAR_ExistLseTenantYN` | Unknown | 12/25 | 3 | Ask AI — tenant already in place |
| `STELLAR_ExpectedClosingDate` | Unknown | 25/25 | 4 | Alert — projected close |
| `STELLAR_ExpectedLeaseDate` | Unknown | 2/25 | 4 | Alert — rental available date |
| `STELLAR_ExpectedOnMarketDate` | Property Basics | 0/25 | 4 | Alert — coming soon date |
| `STELLAR_ExpireRenewalDate` | Unknown | 0/25 | 4 | Alert — listing expiration |
| `STELLAR_FCHRURLYN` | Unknown | 0/25 | 6 | Obscure MLS flag; no known public meaning |
| `STELLAR_FloodZoneCode` | Unknown | 17/25 | 2 | Risk enhancement (needs FEMA zone translation) |
| `STELLAR_FloodZoneDate` | Unknown | 0/25 | 3 | Ask AI — when flood zone was last certified |
| `STELLAR_FloodZonePanel` | Unknown | 0/25 | 6 | Technical FEMA panel reference; admin |
| `STELLAR_FloorNumber` | Unknown | 1/25 | 2 | Condo floor level enhancement |
| `STELLAR_ForLeaseYN` | Unknown | 0/25 | 1 | Core rental vs. sale dimension |
| `STELLAR_FreestandingYN` | Unknown | 0/25 | 2 | Detached/freestanding feature |
| `STELLAR_FreezerSpaceYN` | Unknown | 0/25 | 6 | Commercial food storage; not residential |
| `STELLAR_FutureLandUse` | Unknown | 22/25 | 3 | Ask AI — zoning/future use context |
| `STELLAR_GarageDimensions` | Unknown | 14/25 | 2 | Garage size detail enhancement |
| `STELLAR_GarageDoorHeight` | Unknown | 0/25 | 2 | Garage clearance enhancement |
| `STELLAR_Geolocation` | Unknown | 0/25 | 6 | Null; duplicate of Latitude/Longitude |
| `STELLAR_GiftedDonated` | Unknown | 0/25 | 6 | Transaction type admin |
| `STELLAR_GreenEnergyGenerationYN` | Unknown | 0/25 | 2 | Solar/generation feature flag |
| `STELLAR_GreenLandscaping` | Unknown | 4/25 | 2 | Sustainable landscaping enhancement |
| `STELLAR_GreenVerificationCount` | Unknown | 25/25 | 2 | Green certification count signal |
| `STELLAR_HERSIndex` | Unknown | 11/25 | 2 | Energy efficiency index enhancement |
| `STELLAR_HomesteadYN` | Unknown | 25/25 | 3 | Ask AI — homestead tax exemption |
| `STELLAR_ILSTotalSQFT` | Unknown | 0/25 | 6 | ILS commercial listing data |
| `STELLAR_ILSUnderAirSQFT` | Unknown | 0/25 | 6 | ILS commercial data |
| `STELLAR_InLawSuiteDescrip` | Unknown | 0/25 | 3 | Ask AI — in-law suite details |
| `STELLAR_InLawSuiteYN` | Unknown | 0/25 | 2 | In-law suite feature enhancement |
| `STELLAR_LastDateAvailable` | Unknown | 0/25 | 4 | Alert — rental availability end date |
| `STELLAR_LastMonthsRent` | Unknown | 0/25 | 3 | Ask AI — last month's rent deposit |
| `STELLAR_LeasableArea` | Unknown | 0/25 | 3 | Ask AI — commercial/mixed-use leasable area |
| `STELLAR_LeasableAreaUnits` | Unknown | 0/25 | 6 | Metadata label |
| `STELLAR_LeasePricePerAcre` | Unknown | 0/25 | 3 | Ask AI — land lease pricing |
| `STELLAR_LeaseRestrictionsYN` | Unknown | 0/25 | 1 | Core rental match — HOA limits on leasing |
| `STELLAR_ListOfficeContactPreferred` | Compliance/Restricted | 21/25 | 6 | **COMPLIANCE** — phone/email value |
| `STELLAR_ListOfficeHeadOfficeKeyNumeric` | Unknown | 25/25 | 6 | Brokerage chain admin ID |
| `STELLAR_ListSource` | Unknown | 0/25 | 6 | MLS data source admin |
| `STELLAR_ListSourceOriginal` | Unknown | 0/25 | 6 | MLS data source admin |
| `STELLAR_LongTermYN` | Unknown | 0/25 | 1 | Core rental type — long-term vs. short-term |
| `STELLAR_Management` | Unknown | 0/25 | 3 | Ask AI — property management company |
| `STELLAR_ManufacturingSpaceHeated` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_ManufacturingSpaceTotal` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_MasterBedSize` | Unknown | 0/25 | 2 | Primary bedroom size enhancement |
| `STELLAR_MatrixTesting` | Unknown | 25/25 | 6 | MLS testing/QA flag |
| `STELLAR_MaxPetWeight` | Unknown | 0/25 | 1 | Core rental match — maximum pet weight |
| `STELLAR_MillageRate` | Unknown | 19/25 | 3 | Ask AI — tax mill rate context |
| `STELLAR_MinimumLease` | Unknown | 0/25 | 1 | Core rental match — minimum lease term |
| `STELLAR_MlsMajorChangeType` | Unknown | 25/25 | 4 | Alert — type of most recent MLS change |
| `STELLAR_MonthToMonthOrWeeklyYN` | Unknown | 0/25 | 1 | Core rental type flexibility dimension |
| `STELLAR_MonthlyCondoFeeAmount` | Unknown | 0/25 | 1 | Core condo financial match dimension |
| `STELLAR_MonthlyHOAAmount` | HOA/Fees | 23/25 | 2 | Redundant HOA amount (use AssociationFee) |
| `STELLAR_MonthsAvailable` | Unknown | 0/25 | 3 | Ask AI — seasonal rental availability |
| `STELLAR_MontlyMaintAmtAdditionToHOA` | Unknown | 1/25 | 3 | Ask AI — extra maintenance on top of HOA |
| `STELLAR_NetOperatingIncomeType` | Financial/Investment | 0/25 | 3 | Ask AI — investment NOI context |
| `STELLAR_NoDriveBeach` | Unknown | 0/25 | 2 | Beach access type enhancement |
| `STELLAR_NumOfOwnYearsPriorToLse` | Unknown | 0/25 | 6 | HOA restriction admin |
| `STELLAR_NumTimesperYear` | Unknown | 0/25 | 3 | Ask AI — vacation rental frequency |
| `STELLAR_NumberOfPaddocksPastures` | Unknown | 0/25 | 2 | Equestrian feature enhancement |
| `STELLAR_NumberOfPets` | Unknown | 3/25 | 1 | Core rental match — max pet count allowed |
| `STELLAR_NumberOfSeptics` | Unknown | 0/25 | 2 | Utility infrastructure detail |
| `STELLAR_NumberOfStalls` | Unknown | 0/25 | 2 | Equestrian feature enhancement |
| `STELLAR_NumberOfWells` | Unknown | 0/25 | 2 | Utility infrastructure detail |
| `STELLAR_NumofBays` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_NumofBaysDockHigh` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_NumofBaysGradeLevel` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_NumofConferenceMeetingRooms` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_NumofOffices` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_OffSeasonRent` | Unknown | 0/25 | 3 | Ask AI — seasonal pricing context |
| `STELLAR_OfficeIDXOfficeParticipationYN` | Unknown | 25/25 | 6 | MLS IDX admin flag |
| `STELLAR_OfficeRetailSpaceSqFt` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_OfficeSyndicateTo` | Unknown | 25/25 | 6 | MLS syndication admin |
| `STELLAR_OpenHouseCount` | Unknown | 5/25 | 4 | Alert — open house schedule count |
| `STELLAR_OriginatingSystemTimestamp` | Unknown | 25/25 | 4 | Alert — original data entry timestamp |
| `STELLAR_OtherExemptionsYN` | Unknown | 10/25 | 3 | Ask AI — other tax exemptions |
| `STELLAR_OtherFeesAmount` | Unknown | 0/25 | 3 | Ask AI — miscellaneous fees |
| `STELLAR_OtherFeesDescription` | Unknown | 0/25 | 3 | Ask AI — what are the other fees |
| `STELLAR_OtherFeesTerm` | Unknown | 0/25 | 3 | Ask AI — other fee frequency |
| `STELLAR_ParkingFeeTenants` | Unknown | 0/25 | 3 | Ask AI — tenant parking cost |
| `STELLAR_ParkingFeeTenantsFrequency` | Unknown | 0/25 | 3 | Ask AI — parking fee billing frequency |
| `STELLAR_PetDepositFee` | Unknown | 0/25 | 1 | Core rental match — upfront pet deposit |
| `STELLAR_PetFeeNonRefundable` | Unknown | 0/25 | 3 | Ask AI — non-refundable pet fee amount |
| `STELLAR_PetMonthlyFee` | Unknown | 0/25 | 1 | Core rental match — recurring monthly pet fee |
| `STELLAR_PetRestrictions` | Unknown | 7/25 | 3 | Ask AI — pet type/breed restrictions text |
| `STELLAR_PetSize` | Unknown | 0/25 | 1 | Core rental match — pet size restriction |
| `STELLAR_PlannedUnitDevelopmentYN` | Unknown | 19/25 | 2 | PUD community type enhancement |
| `STELLAR_PoolDimensions` | Unknown | 0/25 | 3 | Ask AI — pool size |
| `STELLAR_PreviousStatus` | Unknown | 25/25 | 4 | Alert — status before current status |
| `STELLAR_PricePerAcre` | Unknown | 0/25 | 3 | Ask AI — land pricing context |
| `STELLAR_ProjectedCompletionDate` | Unknown | 23/25 | 4 | Alert — new construction timeline |
| `STELLAR_PropertyManager` | Unknown | 0/25 | 3 | Ask AI — who manages the property |
| `STELLAR_PropertyManagerPhone` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — phone number |
| `STELLAR_PublicRemarksAgent` | Unknown | 25/25 | 3 | Ask AI — MLS-internal copy of remarks (use PublicRemarks) |
| `STELLAR_PublicRemarksRequired` | Unknown | 0/25 | 6 | MLS editorial admin |
| `STELLAR_PublicRemarksSpanishReq` | Unknown | 0/25 | 6 | MLS editorial admin |
| `STELLAR_RATIO_ClosePrice_By_ListPrice` | Price | 25/25 | 3 | Ask AI — negotiation ratio analysis |
| `STELLAR_RATIO_ClosePrice_By_OriginalListPrice` | Price | 25/25 | 3 | Ask AI — original price negotiation context |
| `STELLAR_RATIO_CurrentPrice_By_BuildingAreaTotal` | Interior Features | 9/25 | 3 | Ask AI — price per total sqft |
| `STELLAR_RATIO_CurrentPrice_By_CalculatedSqFt` | Unknown | 25/25 | 3 | Ask AI — price per living area sqft |
| `STELLAR_RETSUpdateTransactionYN` | Unknown | 0/25 | 6 | Legacy RETS data pipeline admin |
| `STELLAR_RealtorInfo` | Unknown | 21/25 | 3 | Ask AI — brochures, floor plans available |
| `STELLAR_RealtorInfoConfidential` | Unknown | 15/25 | 6 | Agent-only notes; must not be public |
| `STELLAR_RegionalAOR` | Unknown | 25/25 | 6 | MLS regional admin |
| `STELLAR_RentSpreeURL` | Unknown | 0/25 | 3 | Ask AI — online rental application link |
| `STELLAR_RentSpreeYN` | Unknown | 0/25 | 3 | Ask AI — online application available |
| `STELLAR_Representation` | Unknown | 11/25 | 6 | Agent representation type; not property attribute |
| `STELLAR_SDEOYN` | Unknown | 25/25 | 6 | Obscure Stellar admin flag |
| `STELLAR_SWSubdivCommunityName` | Unknown | 25/25 | 3 | Ask AI — SW Florida subdivision name |
| `STELLAR_SWSubdivCondoNum` | Unknown | 0/25 | 6 | Condo admin number |
| `STELLAR_SeasonalRent` | Unknown | 0/25 | 3 | Ask AI — seasonal rent amount |
| `STELLAR_SecurityDeposit` | Unknown | 0/25 | 1 | Core rental match — required security deposit |
| `STELLAR_SellerRepresentation` | Unknown | 0/25 | 6 | Agent admin data |
| `STELLAR_ShowingConsiderations` | Compliance/Restricted | 6/25 | 6 | **COMPLIANCE** — private showing notes |
| `STELLAR_ShowingRequirements` | Compliance/Restricted | 25/25 | 6 | **COMPLIANCE** — private access details |
| `STELLAR_ShowingTime` | Unknown | 17/25 | 6 | Showing logistics; agent workflow only |
| `STELLAR_SolarLeaseFinanceTerms` | Unknown | 0/25 | 3 | Ask AI — solar panel financing terms |
| `STELLAR_SolarPanelOwnership` | Unknown | 0/25 | 3 | Ask AI — owned vs. leased solar panels |
| `STELLAR_SoldRemarks` | Unknown | 0/25 | 6 | Post-close agent notes; not for public |
| `STELLAR_SpaceType` | Unknown | 0/25 | 6 | Commercial space type; not residential |
| `STELLAR_StateLandUseCode` | Unknown | 0/25 | 6 | Admin state code |
| `STELLAR_StatePropertyUseCode` | Unknown | 0/25 | 6 | Admin state code |
| `STELLAR_SubdivisionNum` | Unknown | 25/25 | 6 | Admin subdivision number |
| `STELLAR_SubdivisionSectionNumber` | Unknown | 0/25 | 6 | Admin section number |
| `STELLAR_TempOffMarketDate` | Property Basics | 0/25 | 4 | Alert — temporary withdrawal date |
| `STELLAR_TenantName` | Unknown | 2/25 | 6 | Tenant PII — never expose |
| `STELLAR_TenantPhone` | Compliance/Restricted | 2/25 | 6 | **COMPLIANCE** — phone number / tenant PII |
| `STELLAR_ThirdPartyYN` | Unknown | 25/25 | 6 | Lender/servicer transaction flag; agent admin |
| `STELLAR_TotalAcreage` | Unknown | 25/25 | 2 | Lot size range description (e.g. "0 to <1/4") |
| `STELLAR_TotalAnnualFees` | Unknown | 22/25 | 2 | Total annual HOA/CDD cost enhancement |
| `STELLAR_TotalDocumentsCount` | Media | 25/25 | 4 | Listing completeness signal |
| `STELLAR_TotalMonthlyExpenses` | Unknown | 0/25 | 3 | Ask AI — investment monthly cost context |
| `STELLAR_TotalMonthlyFees` | Unknown | 22/25 | 2 | Total monthly HOA/CDD summary |
| `STELLAR_TotalPhotosCount` | Media | 25/25 | 4 | Listing quality signal |
| `STELLAR_UnitCount` | Unknown | 0/25 | 3 | Ask AI — number of units in building |
| `STELLAR_UnitNumberYN` | Location | 0/25 | 6 | Admin flag |
| `STELLAR_UniversalPropertyId` | Unknown | 25/25 | 4 | Alert — cross-system property identifier |
| `STELLAR_UnparsedAddress` | Location | 25/25 | 1 | Full address string — primary address source |
| `STELLAR_UseCode` | Unknown | 0/25 | 6 | Admin use code |
| `STELLAR_VirtualTourURLBranded2` | Media | 0/25 | 3 | Ask AI — additional branded tour link |
| `STELLAR_VirtualTourURLUnbranded2` | Media | 1/25 | 3 | Ask AI — additional unbranded tour link |
| `STELLAR_VirtuallyStagedYN` | Unknown | 0/25 | 3 | Ask AI — disclosure: photos are virtually staged |
| `STELLAR_WarehouseSpaceHeated` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_WarehouseSpaceTotal` | Unknown | 0/25 | 6 | Commercial only |
| `STELLAR_WaterAccess` | Unknown | 0/25 | 2 | Water access feature enhancement |
| `STELLAR_WaterAccessYN` | Unknown | 25/25 | 2 | Water access flag enhancement |
| `STELLAR_WaterExtras` | Unknown | 0/25 | 2 | Waterfront extras enhancement |
| `STELLAR_WaterExtrasYN` | Unknown | 25/25 | 2 | Waterfront extras flag |
| `STELLAR_WaterView` | Exterior/Lot | 5/25 | 2 | Water view type detail enhancement |
| `STELLAR_WaterViewYN` | Exterior/Lot | 25/25 | 1 | Core feature — has water view |
| `STELLAR_WaterfrontFeetTotal` | Unknown | 0/25 | 2 | Waterfront dimension detail |
| `STELLAR_WeeklyRent` | Unknown | 0/25 | 3 | Ask AI — short-term rental weekly rate |
| `STELLAR_WeeksAvailable` | Unknown | 0/25 | 3 | Ask AI — vacation rental availability |
| `STELLAR_YrsOfOwnerPriorToLeasingReqYN` | Lease/Rental | 0/25 | 6 | HOA restriction admin |
| `STELLAR_ZoningCompatibleYN` | Financial/Investment | 16/25 | 3 | Ask AI — is use zoning-compatible |
| `SecurityFeatures` | Unknown | 23/25 | 2 | Security system enhancement |
| `SeniorCommunityYN` | HOA/Fees | 25/25 | 1 | Core match — age-restricted community |
| `SerialU` | Unknown | 0/25 | 6 | Mobile home serial |
| `SerialX` | Unknown | 0/25 | 6 | Mobile home serial |
| `SerialXX` | Unknown | 0/25 | 6 | Mobile home serial |
| `Sewer` | Exterior/Lot | 12/25 | 2 | Utility infrastructure enhancement |
| `Skirt` | Unknown | 0/25 | 6 | Mobile home skirting |
| `SourceSystemKey` | Unknown | 25/25 | 6 | Data source admin key |
| `SourceSystemName` | Agent/Brokerage | 0/25 | 6 | Data source admin |
| `SpaFeatures` | Exterior/Lot | 0/25 | 2 | Spa/hot tub detail enhancement |
| `SpaYN` | Exterior/Lot | 9/25 | 2 | Spa/hot tub feature |
| `SpecialListingConditions` | Unknown | 25/25 | 4 | Alert — foreclosure/short sale signal |
| `StandardStatus` | Property Basics | 25/25 | 1 | **Core status dimension** — native column |
| `StateOrProvince` | Location | 25/25 | 1 | **Core location dimension** — native column |
| `StatusChangeTimestamp` | Property Basics | 25/25 | 4 | Alert — status change time |
| `Stories` | Interior Features | 0/25 | 2 | Floor count enhancement |
| `StoriesTotal` | Interior Features | 0/25 | 2 | Total stories enhancement |
| `StreetAdditionalInfo` | Location | 0/25 | 3 | Ask AI — address context |
| `StreetDirPrefix` | Location | 0/25 | 6 | Address component; not user-facing alone |
| `StreetDirSuffix` | Unknown | 1/25 | 6 | Address component; not user-facing alone |
| `StreetName` | Location | 25/25 | 3 | Ask AI — street name for display |
| `StreetNumber` | Location | 25/25 | 3 | Ask AI — street number for display |
| `StreetNumberNumeric` | Location | 25/25 | 6 | Redundant numeric form of StreetNumber |
| `StreetSuffix` | Location | 25/25 | 6 | Address suffix (Drive/Lane); part of UnparsedAddress |
| `StreetSuffixModifier` | Location | 0/25 | 6 | Address modifier; not user-facing alone |
| `StructureType` | Unknown | 0/25 | 2 | Property structure type enhancement |
| `SubdivisionName` | Location | 25/25 | 1 | Core location dimension |
| `TaxAnnualAmount` | Price | 25/25 | 1 | Core financial dimension — annual tax burden |
| `TaxBlock` | Financial/Investment | 25/25 | 6 | Tax/legal admin |
| `TaxBookNumber` | Unknown | 25/25 | 6 | Tax/legal admin |
| `TaxExemptions` | Unknown | 0/25 | 3 | Ask AI — what exemptions apply |
| `TaxLegalDescription` | Location | 25/25 | 6 | Legal parcel description; not user-facing |
| `TaxLot` | Financial/Investment | 21/25 | 6 | Tax/legal admin |
| `TaxOtherAnnualAssessmentAmount` | Unknown | 9/25 | 3 | Ask AI — CDD/special assessment amount |
| `TaxYear` | Price | 25/25 | 6 | Metadata qualifier for TaxAnnualAmount |
| `Telephone` | Compliance/Restricted | 0/25 | 6 | **COMPLIANCE** — phone number |
| `TenantPays` | Lease/Rental | 0/25 | 1 | Core rental match — which utilities tenant pays |
| `Topography` | Exterior/Lot | 0/25 | 2 | Lot topography enhancement |
| `TotalActualRent` | Financial/Investment | 0/25 | 1 | Core investment matching — actual rent collected |
| `UnitNumber` | Location | 2/25 | 3 | Ask AI — condo/apartment unit number |
| `UnitTypeType` | Unknown | 0/25 | 2 | Unit type enhancement for condos |
| `UnparsedAddress` | Location | 25/25 | 3 | Ask AI — full formatted address display |
| `Utilities` | Exterior/Lot | 25/25 | 2 | Available utilities enhancement |
| `Vegetation` | Unknown | 15/25 | 2 | Lot landscaping enhancement |
| `View` | Exterior/Lot | 5/25 | 2 | View type detail enhancement |
| `ViewYN` | Exterior/Lot | 25/25 | 1 | Core feature — has any view |
| `VirtualTourURLBranded` | Media | 4/25 | 4 | Recommendation — branded tour available |
| `VirtualTourURLUnbranded` | Media | 18/25 | 4 | Recommendation — unbranded tour available |
| `VirtualTourURLZillow` | Media | 0/25 | 4 | Recommendation — Zillow 3D tour |
| `WaterBodyName` | Exterior/Lot | 0/25 | 3 | Ask AI — name of waterfront body |
| `WaterSource` | Exterior/Lot | 19/25 | 2 | Utility infrastructure enhancement |
| `WaterfrontFeatures` | Exterior/Lot | 2/25 | 2 | Waterfront type detail enhancement |
| `WaterfrontYN` | Exterior/Lot | 25/25 | 1 | Core feature dimension |
| `WindowFeatures` | Interior Features | 25/25 | 2 | Interior feature enhancement |
| `YearBuilt` | Exterior/Lot | 25/25 | 1 | Core age/condition dimension |
| `YearBuiltEffective` | Exterior/Lot | 0/25 | 2 | Effective year after renovation enhancement |
| `YearBuiltSource` | Exterior/Lot | 0/25 | 6 | Metadata label |
| `YearEstablished` | Unknown | 0/25 | 6 | Commercial/business use only |
| `Zoning` | Financial/Investment | 24/25 | 1 | Core dimension for land/investment buyers |
| `ZoningDescription` | Financial/Investment | 0/25 | 1 | Core zoning detail (when present) |

---

## 3. Buyer Matching Blueprint

### Purpose
Match buyer criteria (price range, location, property type, size, features) against active Stellar listings to score fit. Higher score = better match.

### Required Tier 1 Fields for Buyer Matching

> Columns: **Field** | **Source** | **Matching Dimension** | **Recommended Future Storage** | **Relative Weight** | **Population**

| Field | Source | Matching Dimension | Recommended Future Storage | Relative Weight | Population |
|---|---|---|---|---|---|
| `StandardStatus` | Existing native column | Property Type | Existing `bridge_properties` column | Critical — exclude non-active | 25/25 |
| `PropertyType` | Existing native column | Property Type | Existing `bridge_properties` column | Critical — primary type filter | 25/25 |
| `PropertySubType` | Raw JSON | Property Type | New future native column | High — SFR vs condo vs townhouse | 25/25 |
| `City` | Existing native column | Location | Existing `bridge_properties` column | Critical — geography filter | 25/25 |
| `CountyOrParish` | Raw JSON | Location | New future native column | Critical — county-level filter | 25/25 |
| `PostalCode` | Existing native column | Location | Existing `bridge_properties` column | Critical — zip-code filter | 25/25 |
| `StateOrProvince` | Existing native column | Location | Existing `bridge_properties` column | High — state filter | 25/25 |
| `Latitude` | Raw JSON | Location | New future native column | Critical — radius search | 25/25 |
| `Longitude` | Raw JSON | Location | New future native column | Critical — radius search | 25/25 |
| `MLSAreaMajor` | Raw JSON | Location | New future native column | Medium — sub-market filter | 25/25 |
| `SubdivisionName` | Raw JSON | Location | New future native column | Medium — community preference | 25/25 |
| `ListPrice` | Existing native column | Price | Existing `bridge_properties` column | Critical — price range filter | 25/25 |
| `OriginalListPrice` | Raw JSON | Price | New future native column | Medium — price reduction signal | 25/25 |
| `TaxAnnualAmount` | Raw JSON | Financial | New future native column | High — true cost of ownership | 25/25 |
| `AssociationFee` | Raw JSON | Financial | New future native column | High — monthly HOA cost | 22/25 |
| `AssociationYN` | Raw JSON | Financial | New future native column | Medium — HOA exists filter | 25/25 |
| `STELLAR_CDDYN` | Raw JSON | Financial | New future native column | Medium — CDD cost signal (FL) | 25/25 |
| `BedroomsTotal` | Existing native column | Size | Existing `bridge_properties` column | Critical — bedroom filter | 25/25 |
| `BathroomsTotalInteger` | Existing native column | Size | Existing `bridge_properties` column | Critical — bathroom filter | 25/25 |
| `BathroomsFull` | Raw JSON | Size | New future native column | High — full bath precision | 25/25 |
| `BathroomsHalf` | Raw JSON | Size | New future native column | Low — half bath detail | 25/25 |
| `LivingArea` | Existing native column | Size | Existing `bridge_properties` column | Critical — square footage filter | 25/25 |
| `LotSizeSquareFeet` | Raw JSON | Size | New future native column | High — lot size filter | 25/25 |
| `LotSizeAcres` | Raw JSON | Size | New future native column | Medium — acreage display | 25/25 |
| `GarageYN` | Raw JSON | Features | New future native column | High — garage filter | 25/25 |
| `GarageSpaces` | Raw JSON | Features | New future native column | Medium — garage count detail | 25/25 |
| `PoolPrivateYN` | Raw JSON | Features | New future native column | High — pool filter | 25/25 |
| `WaterfrontYN` | Raw JSON | Features | New future native column | High — waterfront filter | 25/25 |
| `ViewYN` | Raw JSON | Features | New future native column | Medium — view filter | 25/25 |
| `STELLAR_WaterViewYN` | Raw JSON | Features | New future native column | Medium — water view filter | 25/25 |
| `SeniorCommunityYN` | Raw JSON | Features | New future native column | High — 55+ filter (legal) | 25/25 |
| `NewConstructionYN` | Raw JSON | Features | New future native column | Medium — new construction filter | 25/25 |
| `YearBuilt` | Raw JSON | Features | New future native column | High — age range filter | 25/25 |
| `Zoning` | Raw JSON | Financial | Raw JSON only | Medium — zoning filter for investors | 24/25 |
| `ZoningDescription` | Raw JSON | Financial | Raw JSON only | Low — zoning detail when present | 0/25 |
| `CapRate` | Raw JSON | Financial | Raw JSON only | High — investment matching (when present) | 0/25 |
| `GrossIncome` | Raw JSON | Financial | Raw JSON only | High — investment matching (when present) | 0/25 |
| `GrossScheduledIncome` | Raw JSON | Financial | Raw JSON only | High — investment matching (when present) | 0/25 |
| `STELLAR_EstAnnualMarketIncome` | Raw JSON | Financial | Raw JSON only | High — investment matching (when present) | 0/25 |
| `STELLAR_AnnualNetIncome` | Raw JSON | Financial | Raw JSON only | High — investment matching (when present) | 0/25 |
| `STELLAR_AnnualExpenses` | Raw JSON | Financial | Raw JSON only | Medium — investment cost (when present) | 0/25 |
| `STELLAR_CondoFees` | Raw JSON | Financial | Raw JSON only | High — condo total cost (when present) | 0/25 |
| `STELLAR_MonthlyCondoFeeAmount` | Raw JSON | Financial | Raw JSON only | High — condo monthly cost (when present) | 0/25 |

### Identified Gaps for Buyer Matching

| Gap | Impact | Recommended Resolution |
|---|---|---|
| **School district/quality** | High — top buyer concern | Integrate a geocode-to-school-district API; `ElementarySchool` / `HighSchool` fields exist but are not normalized |
| **Walk Score / Transit Score** | Medium — urban buyers | Integrate Walk Score API using `Latitude`/`Longitude` |
| **Flood zone risk** | High — FL-specific | Translate `STELLAR_FloodZoneCode` (FEMA codes X/AE/VE) to risk tier; add to match score |
| **HOA restriction details** | Medium | `AssociationFeeIncludes` and `CommunityFeatures` only partially describe restrictions; manual input needed for full restrictions |
| **Investment financials for most listings** | High for investors | `CapRate`, `GrossIncome`, `GrossScheduledIncome` are 0/25 in residential-for-sale sample; they will populate in investment/multifamily feeds |
| **Price per sqft normalization** | Low | `STELLAR_CalculatedListPriceByCalculatedSqFt` is pre-computed but raw JSON; expose as a sort dimension |

---

## 4. Tenant Matching Blueprint

### Purpose
Match tenant search criteria (monthly rent, beds/baths, pets, furnished, lease term) against active rental listings.

### Required Tier 1 Fields for Tenant Matching

> Columns: **Field** | **Source** | **Matching Dimension** | **Recommended Future Storage** | **Relative Weight** | **Population**

| Field | Source | Matching Dimension | Recommended Future Storage | Relative Weight | Population |
|---|---|---|---|---|---|
| `StandardStatus` | Existing native column | Property Type | Existing `bridge_properties` column | Critical — active rentals only | 25/25 |
| `MlsStatus` | Raw JSON | Property Type | New future native column | Critical — rental-specific status | 25/25 |
| `LeaseConsideredYN` | Raw JSON | Rental | New future native column | Critical — is it actually rentable | 0/25 |
| `STELLAR_ForLeaseYN` | Raw JSON | Rental | New future native column | Critical — for lease flag | 0/25 |
| `STELLAR_LongTermYN` | Raw JSON | Rental | New future native column | High — long-term vs. vacation rental | 0/25 |
| `City` | Existing native column | Location | Existing `bridge_properties` column | Critical | 25/25 |
| `CountyOrParish` | Raw JSON | Location | New future native column | Critical | 25/25 |
| `PostalCode` | Existing native column | Location | Existing `bridge_properties` column | Critical | 25/25 |
| `Latitude` | Raw JSON | Location | New future native column | Critical — radius search | 25/25 |
| `Longitude` | Raw JSON | Location | New future native column | Critical — radius search | 25/25 |
| `SubdivisionName` | Raw JSON | Location | New future native column | Medium | 25/25 |
| `ListPrice` | Existing native column | Price | Existing `bridge_properties` column | Critical — monthly rent amount | 25/25 |
| `LeaseAmountFrequency` | Raw JSON | Price | New future native column | Critical — monthly/weekly qualifier | 0/25 |
| `STELLAR_AnnualRent` | Raw JSON | Price | Raw JSON only | High — annual rent context | 0/25 |
| `STELLAR_CurrencyMonthlyRentAmt` | Raw JSON | Price | New future native column | Critical — explicit monthly rent | 0/25 |
| `BedroomsTotal` | Existing native column | Size | Existing `bridge_properties` column | Critical | 25/25 |
| `BathroomsTotalInteger` | Existing native column | Size | Existing `bridge_properties` column | Critical | 25/25 |
| `LivingArea` | Existing native column | Size | Existing `bridge_properties` column | High | 25/25 |
| `PropertyType` | Existing native column | Property Type | Existing `bridge_properties` column | High | 25/25 |
| `PropertySubType` | Raw JSON | Property Type | New future native column | High | 25/25 |
| `Furnished` | Raw JSON | Rental | New future native column | Critical — core furnished filter | 9/25 |
| `PetsAllowed` | Raw JSON | Rental | New future native column | Critical — pet policy filter | 24/25 |
| `STELLAR_NumberOfPets` | Raw JSON | Rental | Raw JSON only | High — max pet count | 3/25 |
| `STELLAR_MaxPetWeight` | Raw JSON | Rental | Raw JSON only | High — pet weight limit | 0/25 |
| `STELLAR_PetSize` | Raw JSON | Rental | Raw JSON only | High — pet size restriction | 0/25 |
| `STELLAR_PetDepositFee` | Raw JSON | Rental | Raw JSON only | High — total cost of renting with pet | 0/25 |
| `STELLAR_PetMonthlyFee` | Raw JSON | Rental | Raw JSON only | High — monthly pet surcharge | 0/25 |
| `STELLAR_AdditionalPetFees` | Raw JSON | Rental | Raw JSON only | Medium — additional pet cost | 0/25 |
| `LeaseTerm` | Raw JSON | Rental | New future native column | Critical — min lease length | 0/25 |
| `STELLAR_MinimumLease` | Raw JSON | Rental | New future native column | Critical — explicit min lease months | 0/25 |
| `STELLAR_MonthToMonthOrWeeklyYN` | Raw JSON | Rental | Raw JSON only | High — flexible lease available | 0/25 |
| `TenantPays` | Raw JSON | Rental | Raw JSON only | High — utility cost responsibility | 0/25 |
| `RentIncludes` | Raw JSON | Rental | Raw JSON only | High — what is in rent price | 0/25 |
| `AvailabilityDate` | Raw JSON | Rental | New future native column | Critical — when can I move in | 0/25 |
| `STELLAR_LeaseRestrictionsYN` | Raw JSON | Rental | Raw JSON only | High — HOA lease restrictions | 0/25 |
| `STELLAR_SecurityDeposit` | Raw JSON | Rental | Raw JSON only | Medium — upfront cost signal | 0/25 |
| `GarageYN` | Raw JSON | Features | New future native column | Medium | 25/25 |
| `GarageSpaces` | Raw JSON | Features | New future native column | Medium | 25/25 |
| `PoolPrivateYN` | Raw JSON | Features | New future native column | Medium | 25/25 |
| `WaterfrontYN` | Raw JSON | Features | New future native column | Medium | 25/25 |
| `SeniorCommunityYN` | Raw JSON | Features | New future native column | High — age-restricted legal requirement | 25/25 |
| `AssociationYN` | Raw JSON | Financial | New future native column | Medium — HOA approval required? | 25/25 |
| `AssociationFee` | Raw JSON | Financial | New future native column | Low — may be in rent | 22/25 |
| `YearBuilt` | Raw JSON | Features | New future native column | Low — condition preference | 25/25 |

### Identified Gaps for Tenant Matching

| Gap | Impact | Recommended Resolution |
|---|---|---|
| **Most rental-specific fields are 0/25 in current sample** | Critical | Current sample is residential-for-sale only; tenant matching requires fetching Stellar's **For Lease** feed (`LeaseConsideredYN=true` or `STELLAR_ForLeaseYN=true`) |
| **No explicit monthly rent column** | Critical | `ListPrice` is populated but ambiguous (could be sale price); `STELLAR_CurrencyMonthlyRentAmt` (null in sample) should become the rental price native column |
| **Pet policy granularity** | High | `PetsAllowed` is an array (e.g., `["Yes"]`) that needs normalization; weight/count/breed restrictions are separate null fields |
| **Utility inclusion clarity** | High | `TenantPays` and `RentIncludes` are both null in sample; tenants cannot assess true monthly cost without these |
| **HOA approval process for tenants** | Medium | `STELLAR_AssociationApprovalRequiredYN` is null; renters need to know if HOA must approve them |
| **Short-term vs. long-term separation** | High | `STELLAR_LongTermYN` and `STELLAR_MonthToMonthOrWeeklyYN` are both null; platform cannot reliably separate vacation rentals from long-term leases |

---

## 5. Ask AI Blueprint

### Structured Fields (High Signal)

These fields should always be loaded into the Ask AI context when answering questions about a listing. They contain factual, structured data that maps cleanly to known question types.

| Field | Category | Example Answer Value | Priority |
|---|---|---|---|
| `PublicRemarks` | Free text | Long-form property description | Primary text source |
| `BedroomsTotal` | Size | `6` | High |
| `BathroomsTotalInteger` | Size | `5` | High |
| `LivingArea` | Size | `3,600 sq ft` | High |
| `LotSizeSquareFeet` / `LotSizeAcres` | Size | `8,450 sq ft / 0.19 acres` | High |
| `YearBuilt` | Age | `2013` | High |
| `ListPrice` | Price | `$360,618` | High |
| `TaxAnnualAmount` | Financial | `$504/year` | High |
| `AssociationFee` + `AssociationFeeFrequency` | Financial | `$60/month` | High |
| `STELLAR_CDDYN` + `TaxOtherAnnualAssessmentAmount` | Financial | `CDD: $2,567/year` | High |
| `PropertyCondition` | Condition | `Under Construction` | High |
| `NewConstructionYN` | Condition | `Yes / No` | High |
| `PetsAllowed` | Rental | `["Yes"]` | High |
| `Furnished` | Rental | `Unfurnished` | High |
| `LeaseTerm` / `STELLAR_MinimumLease` | Rental | `12 months minimum` | High |
| `Appliances` | Interior | `["Dishwasher", "Disposal", ...]` | Medium |
| `InteriorFeatures` | Interior | `["High Ceilings", ...]` | Medium |
| `ExteriorFeatures` | Exterior | `["Irrigation System", ...]` | Medium |
| `CommunityFeatures` | HOA | `["Deed Restrictions", ...]` | Medium |
| `AssociationAmenities` | HOA | `["Park", "Pool", ...]` | Medium |
| `Heating` / `Cooling` | HVAC | `["Central Air"]` | Medium |
| `GarageSpaces` + `AttachedGarageYN` | Parking | `3 car attached` | Medium |
| `PoolPrivateYN` + `PoolFeatures` | Feature | `Private pool (Child Safety Fence)` | Medium |
| `WaterfrontYN` + `WaterfrontFeatures` | Feature | `Waterfront (Pond)` | Medium |
| `STELLAR_WaterViewYN` + `STELLAR_WaterView` | Feature | `Water view (Pond)` | Medium |
| `STELLAR_FloodZoneCode` | Risk | `Zone X (low risk)` | Medium |
| `SeniorCommunityYN` | Feature | `55+ community` | Medium |
| `ElementarySchool` / `HighSchool` | Schools | `Lake Nona High` | Medium |
| `ConstructionMaterials` + `Roof` | Construction | `Block/Stone, Tile roof` | Medium |
| `Utilities` + `Sewer` + `WaterSource` | Utilities | `Public sewer, public water` | Medium |
| `Zoning` + `ZoningDescription` | Zoning | `R-1` | Low |
| `Ownership` | Title | `Fee Simple` | Low |
| `ListingTerms` | Financing | `["Cash", "Conventional"]` | Low |
| `ClosePrice` | History | `$330,000` | Low |
| `STELLAR_HomesteadYN` | Tax | `Homestead exempt` | Low |
| `HomeWarrantyYN` | Sale terms | `Seller offering warranty` | Low |
| `OccupantType` | Vacancy | `Vacant` | Low |

### Free-Text Fields

These fields contain natural language text and should be summarized or searched for relevant content.

| Field | Use |
|---|---|
| `PublicRemarks` | Primary listing narrative |
| `STELLAR_PublicRemarksAgent` | Identical to `PublicRemarks` in sample; use as fallback |
| `Directions` | "How do I get there?" questions |
| `LotSizeDimensions` | "What are the lot dimensions?" |
| `STELLAR_AdditionalRooms` | "Does this home have an office or den?" |
| `STELLAR_PetRestrictions` | "Are there pet restrictions?" |
| `STELLAR_AdditionalLeaseRestrictions` | "What are the lease restrictions?" |
| `STELLAR_SWSubdivCommunityName` | "What community is this in?" |
| `Model` / `BuilderName` | "Who built this home / what model is it?" |

### Hard Exclusion List (Must Never Appear in Any Ask AI Response)

These fields must be blocked at the context-builder layer. No Ask AI prompt, context payload, or response may include, reference, or derive information from them.

| Field | Reason |
|---|---|
| `ListAgentEmail` | Agent PII — email |
| `ListAgentPreferredPhone` | Agent PII — phone |
| `ListOfficePhone` | Agent PII — phone |
| `ListAgentStateLicense` | License number |
| `BuyerAgentStateLicense` | License number |
| `CoListAgentStateLicense` | License number |
| `CoBuyerAgentStateLicense` | License number |
| `License1` / `License2` / `License3` | License numbers |
| `STELLAR_BuilderLicenseNumber` | License number |
| `LockBoxLocation` / `LockBoxSerialNumber` / `LockBoxType` | Private property access |
| `STELLAR_ShowingRequirements` | Private showing instructions |
| `STELLAR_ShowingConsiderations` | Private showing notes |
| `STELLAR_CallCenterPhoneNumber` | Phone number |
| `STELLAR_EscrowAgentEmail` | Email address |
| `STELLAR_EscrowAgentPhone` | Phone number |
| `STELLAR_ListOfficeContactPreferred` | Phone/email value |
| `STELLAR_PropertyManagerPhone` | Phone number |
| `STELLAR_TenantPhone` | Tenant PII — phone |
| `Telephone` | Phone number |
| `STELLAR_TenantName` | Tenant PII — name |
| `STELLAR_RealtorInfoConfidential` | Agent-only notes |
| `STELLAR_SoldRemarks` | Post-close agent notes |
| All escrow address fields | Not relevant to public queries |

---

## 6. Property Alert Blueprint

### Alert Event Types

| Alert Type | Trigger Field(s) | Trigger Condition | Relevant Stellar Fields |
|---|---|---|---|
| **New Listing** | `StandardStatus`, `OriginalEntryTimestamp` | `StandardStatus = Active` AND record is new to platform | `StandardStatus`, `OriginalEntryTimestamp`, `ListingContractDate`, `OnMarketDate` |
| **Price Reduction** | `ListPrice`, `PreviousListPrice`, `PriceChangeTimestamp` | `ListPrice < PreviousListPrice` | `ListPrice`, `PreviousListPrice`, `PriceChangeTimestamp`, `STELLAR_CurrentPrice`, `STELLAR_CalculatedListPriceByCalculatedSqFt` |
| **Price Increase** | `ListPrice`, `PreviousListPrice` | `ListPrice > PreviousListPrice` | Same as price reduction |
| **Status Change** | `StandardStatus`, `MlsStatus`, `StatusChangeTimestamp` | Status transitions (Active → Pending → Closed) | `StandardStatus`, `MlsStatus`, `StatusChangeTimestamp`, `STELLAR_MlsMajorChangeType`, `STELLAR_PreviousStatus`, `STELLAR_ContractStatus` |
| **Back on Market** | `STELLAR_BOMDate`, `StandardStatus` | `STELLAR_BOMDate` set AND `StandardStatus = Active` | `STELLAR_BOMDate`, `StandardStatus`, `STELLAR_PreviousStatus`, `DaysOnMarket` |
| **New Match** | All Tier 1 match fields | Existing saved search produces new result | All buyer/tenant Tier 1 fields |
| **Coming Soon** | `STELLAR_ComingSoonDate`, `StandardStatus` | Status = Coming Soon | `STELLAR_ComingSoonDate`, `StandardStatus`, `ListPrice`, `STELLAR_ProjectedCompletionDate` |
| **Photos Updated** | `PhotosChangeTimestamp`, `PhotosCount` | `PhotosChangeTimestamp` changed | `PhotosChangeTimestamp`, `PhotosCount`, `STELLAR_TotalPhotosCount`, `Media` |
| **Open House Scheduled** | `STELLAR_ActiveOpenHouseCount`, `STELLAR_OpenHouseCount` | Count > 0 | `STELLAR_ActiveOpenHouseCount`, `STELLAR_OpenHouseCount` |
| **New Construction Milestone** | `STELLAR_ProjectedCompletionDate`, `PropertyCondition` | Completion date changed or PropertyCondition changes | `STELLAR_ProjectedCompletionDate`, `PropertyCondition`, `NewConstructionYN` |
| **Temporarily Off Market** | `STELLAR_TempOffMarketDate` | `STELLAR_TempOffMarketDate` set | `STELLAR_TempOffMarketDate`, `StandardStatus` |
| **Listing Expired/Cancelled** | `StandardStatus`, `OffMarketDate` | Status = Expired/Cancelled | `StandardStatus`, `OffMarketDate`, `OffMarketTimestamp`, `STELLAR_ExpireRenewalDate` |
| **Rental Available** | `STELLAR_ExpectedLeaseDate`, `AvailabilityDate` | Date is approaching | `AvailabilityDate`, `STELLAR_ExpectedLeaseDate`, `STELLAR_LastDateAvailable` |
| **Special Condition** | `SpecialListingConditions` | Value changes to/from foreclosure/short sale | `SpecialListingConditions` |

### Alert Copy Field Sources

For human-readable alert notification text, pull from these fields:

| Alert Line | Source Field |
|---|---|
| Property address | `STELLAR_UnparsedAddress` or `UnparsedAddress` |
| Property type/subtype | `PropertyType` + `PropertySubType` |
| Beds/baths | `BedroomsTotal` + `BathroomsTotalInteger` |
| Square footage | `LivingArea` |
| New price | `ListPrice` |
| Previous price | `PreviousListPrice` |
| Price delta % | Derived: `(PreviousListPrice - ListPrice) / PreviousListPrice` |
| Status label | `StandardStatus` |
| Days on market | `DaysOnMarket` |
| Photo count | `STELLAR_TotalPhotosCount` |
| Virtual tour | `VirtualTourURLUnbranded` |

---

## 7. Search Filter Blueprint

### Recommended Search Filter Dimensions

Each filter maps to its Stellar source. Note: most filter-useful fields are Tier 1 or Tier 2 primaries; they appear here as secondary Tier 5 usages.

| Filter Label | Primary Stellar Field(s) | Currently Native? | Filter Type | Notes |
|---|---|---|---|---|
| **Location — City** | `City` | ✅ Native column | Multi-select | |
| **Location — County** | `CountyOrParish` | ❌ Raw JSON | Multi-select | Promote to native |
| **Location — ZIP Code** | `PostalCode` | ✅ Native column | Multi-select | |
| **Location — Subdivision** | `SubdivisionName` | ❌ Raw JSON | Text search | Promote to native |
| **Location — MLS Area** | `MLSAreaMajor` | ❌ Raw JSON | Multi-select | Promote to native |
| **Location — Radius** | `Latitude`, `Longitude` | ❌ Raw JSON | Geo-radius slider | Promote both to native |
| **Price Range** | `ListPrice` | ✅ Native column | Range slider | |
| **Property Type** | `PropertyType`, `PropertySubType` | `PropertyType` native; `PropertySubType` raw | Multi-select | Promote `PropertySubType` |
| **Bedrooms** | `BedroomsTotal` | ✅ Native column | Min count | |
| **Bathrooms** | `BathroomsTotalInteger` | ✅ Native column | Min count | |
| **Living Area (sqft)** | `LivingArea` | ✅ Native column | Range slider | |
| **Lot Size** | `LotSizeSquareFeet`, `LotSizeAcres` | ❌ Raw JSON | Range slider | |
| **Year Built** | `YearBuilt` | ❌ Raw JSON | Range slider | |
| **Garage** | `GarageYN`, `GarageSpaces` | ❌ Raw JSON | Toggle + count | |
| **Pool** | `PoolPrivateYN` | ❌ Raw JSON | Toggle | |
| **Waterfront** | `WaterfrontYN`, `WaterfrontFeatures` | ❌ Raw JSON | Toggle + type | |
| **Water View** | `ViewYN`, `STELLAR_WaterViewYN` | ❌ Raw JSON | Toggle | |
| **Pets Allowed** | `PetsAllowed` | ❌ Raw JSON | Toggle | Rental-specific |
| **Furnished** | `Furnished` | ❌ Raw JSON | Toggle | Rental-specific |
| **Lease Term** | `LeaseTerm`, `STELLAR_MinimumLease` | ❌ Raw JSON | Multi-select | Rental-specific |
| **Senior Community (55+)** | `SeniorCommunityYN` | ❌ Raw JSON | Toggle | |
| **New Construction** | `NewConstructionYN` | ❌ Raw JSON | Toggle | |
| **HOA** | `AssociationYN`, `AssociationFee` | ❌ Raw JSON | Toggle + max fee | |
| **Status** | `StandardStatus` | ✅ Native column | Multi-select | Active, Pending, etc. |
| **Days on Market** | `DaysOnMarket` | ❌ Raw JSON | Range slider | |
| **Flood Zone** | `STELLAR_FloodZoneCode` | ❌ Raw JSON | Multi-select | Requires FEMA code translation |
| **Community Features** | `CommunityFeatures` | ❌ Raw JSON | Multi-select checkbox | Clubhouse, Tennis, Golf, etc. |
| **Parking Spaces (Covered)** | `CoveredSpaces`, `CarportSpaces` | ❌ Raw JSON | Min count | |
| **Laundry** | `LaundryFeatures` | ❌ Raw JSON | Multi-select | In-unit / community / hookups |
| **Flooring** | `Flooring` | ❌ Raw JSON | Multi-select | Tile, Hardwood, Carpet |
| **Architectural Style** | `ArchitecturalStyle` | ❌ Raw JSON | Multi-select | Mediterranean, Ranch, etc. |
| **Spa / Hot Tub** | `SpaYN` | ❌ Raw JSON | Toggle | |
| **Fireplace** | `FireplaceYN` | ❌ Raw JSON | Toggle | |
| **In-Law Suite** | `STELLAR_InLawSuiteYN` | ❌ Raw JSON | Toggle | |
| **Zoning** | `Zoning` | ❌ Raw JSON | Multi-select | For land/investment buyers |
| **Virtual Tour** | `VirtualTourURLUnbranded` | ❌ Raw JSON | Toggle (has tour) | |
| **Open Parking** | `OpenParkingYN`, `OpenParkingSpaces` | ❌ Raw JSON | Toggle | |
| **Green/Energy Cert** | `GreenBuildingVerificationType` | ❌ Raw JSON | Toggle | ENERGY STAR, etc. |
| **Dock** | `STELLAR_DockYN` | ❌ Raw JSON | Toggle | Waterfront properties |
| **CDD** | `STELLAR_CDDYN` | ❌ Raw JSON | Toggle (exclude CDD) | FL-specific |
| **Price / Sq Ft** | `STELLAR_CalculatedListPriceByCalculatedSqFt` | ❌ Raw JSON | Range slider | Sort/filter dimension |

---

## 8. Future Database Mapping Strategy

### Guiding Principles

1. **Only promote fields that will be queried in WHERE, ORDER BY, or JOIN clauses.** Fields used only in display can stay in `raw_json`.
2. **Match-critical fields must be native columns** — any field appearing in a buyer/tenant match score query must be a real column to avoid full-table JSON extraction.
3. **Rarely populated fields stay in `raw_json` unless needed for specific feature gating** — with 292/553 fields always empty, aggressive column promotion would waste schema space.
4. **Do not duplicate data** — when a Stellar field has an existing native column (`City`, `ListPrice`, etc.), the raw JSON value and the native column must be kept in sync by the import job.

### Tier 1: Promote to Native Columns (High Priority — Matching Engine)

These 12 Tier 1 fields are reliably populated (≥88% of sample) and must be queryable at index speed for any matching engine to perform acceptably.

| Field to Promote | Suggested Column Name | Type | Current State |
|---|---|---|---|
| `Latitude` | `latitude` | `decimal(10,7)` | Raw JSON |
| `Longitude` | `longitude` | `decimal(10,7)` | Raw JSON |
| `CountyOrParish` | `county_or_parish` | `string` | Raw JSON |
| `SubdivisionName` | `subdivision_name` | `string` | Raw JSON |
| `MLSAreaMajor` | `mls_area_major` | `string` | Raw JSON |
| `PropertySubType` | `property_sub_type` | `string` | Raw JSON |
| `MlsStatus` | `mls_status` | `string` | Raw JSON |
| `GarageYN` | `garage_yn` | `boolean` | Raw JSON |
| `PoolPrivateYN` | `pool_private_yn` | `boolean` | Raw JSON |
| `WaterfrontYN` | `waterfront_yn` | `boolean` | Raw JSON |
| `YearBuilt` | `year_built` | `smallint` | Raw JSON |
| `AssociationFee` | `association_fee` | `decimal(10,2)` | Raw JSON |

### Tier 2: Promote to Native Columns (Medium Priority — Filtering & Alerting)

These fields enable important search filters and alert comparisons but are lower priority than the matching engine set.

| Field to Promote | Suggested Column Name | Type | Rationale |
|---|---|---|---|
| `OriginalListPrice` | `original_list_price` | `decimal(15,2)` | Price reduction alerts |
| `LotSizeSquareFeet` | `lot_size_sqft` | `integer` | Lot size filter |
| `TaxAnnualAmount` | `tax_annual_amount` | `decimal(10,2)` | True cost of ownership |
| `AssociationYN` | `association_yn` | `boolean` | HOA filter |
| `NewConstructionYN` | `new_construction_yn` | `boolean` | New construction filter |
| `ViewYN` | `view_yn` | `boolean` | View filter |
| `STELLAR_WaterViewYN` | `water_view_yn` | `boolean` | Water view filter |
| `SeniorCommunityYN` | `senior_community_yn` | `boolean` | 55+ legal filter |
| `BathroomsFull` | `bathrooms_full` | `tinyint` | Precise bath filter |
| `GarageSpaces` | `garage_spaces` | `tinyint` | Garage count filter |
| `PetsAllowed` | `pets_allowed` | `string` | Rental pet filter (array→string) |
| `Furnished` | `furnished` | `string` | Rental furnished filter |
| `STELLAR_CDDYN` | `cdd_yn` | `boolean` | FL CDD filter |
| `DaysOnMarket` | `days_on_market` | `smallint` | Alert freshness |
| `StatusChangeTimestamp` | `status_change_timestamp` | `timestamp` | Alert trigger |
| `PreviousListPrice` | `previous_list_price` | `decimal(15,2)` | Price reduction alert |
| `PriceChangeTimestamp` | `price_change_timestamp` | `timestamp` | Alert trigger |

### Tier 3: Promote Only for Rental Feed (Rental Matching Specific)

These fields are all 0/25 in the current residential-for-sale sample but are essential for any rental matching system.

| Field to Promote | Suggested Column Name | Type | Notes |
|---|---|---|---|
| `AvailabilityDate` | `availability_date` | `date` | Critical for rental search |
| `LeaseConsideredYN` | `lease_considered_yn` | `boolean` | Rental vs. sale gate |
| `LeaseTerm` | `lease_term` | `string` | Min lease text |
| `STELLAR_MinimumLease` | `minimum_lease` | `string` | Min lease months |
| `STELLAR_LongTermYN` | `long_term_yn` | `boolean` | Long-term rental flag |
| `STELLAR_ForLeaseYN` | `for_lease_yn` | `boolean` | For lease flag |
| `STELLAR_CurrencyMonthlyRentAmt` | `monthly_rent` | `decimal(10,2)` | Canonical rent amount |
| `STELLAR_SecurityDeposit` | `security_deposit` | `decimal(10,2)` | Tenant cost planning |
| `STELLAR_MaxPetWeight` | `max_pet_weight` | `smallint` | Pet policy detail |
| `STELLAR_NumberOfPets` | `max_pets_allowed` | `tinyint` | Pet count limit |

### Stay in Raw JSON (No Promotion Needed)

The following categories should remain in `raw_json` only:

| Category | Fields | Rationale |
|---|---|---|
| **All Tier 6 fields** | All 223 compliance/admin/excluded fields | Never queried |
| **Array feature fields** | `Appliances`, `InteriorFeatures`, `ExteriorFeatures`, `CommunityFeatures`, `AssociationAmenities`, `Cooling`, `Heating`, `Flooring`, `LotFeatures`, etc. | Display only; full-text search possible via JSON operators if needed |
| **Media/photo fields** | `Media`, `VirtualTourURLUnbranded`, `PhotosCount` | Display only; alert triggers can use `PhotosChangeTimestamp` if promoted |
| **Ask AI context fields** | `PublicRemarks`, `Directions`, `ClosePrice`, school fields, `ListingTerms` | Loaded into context at query time, not indexed |
| **Investment ratios** | `STELLAR_RATIO_*`, `STELLAR_CalculatedListPriceByCalculatedSqFt` | Derived/display; not match criteria |
| **Green features** | `GreenBuildingVerificationType`, `GreenEnergyEfficient`, etc. | Niche; expose as raw JSON filters if demand grows |
| **All zero-populated fields** | 292 always-empty fields | Will populate in specialized feeds; add columns when feed is enabled |

### Derived / Computed Features (Future)

These are not raw Stellar fields but should be computed and stored:

| Derived Feature | Source Fields | Purpose |
|---|---|---|
| `price_per_sqft` | `ListPrice` / `LivingArea` | Sort and filter dimension |
| `match_score_buyer` | Multiple Tier 1 fields | Buyer match score cache |
| `match_score_tenant` | Multiple Tier 1 rental fields | Tenant match score cache |
| `flood_risk_tier` | `STELLAR_FloodZoneCode` → FEMA zone translation | Risk match dimension |
| `total_monthly_cost_estimate` | `AssociationFee` + `TaxAnnualAmount`/12 + `STELLAR_CDDYN` | True cost of ownership display |
| `geo_point` | `Latitude` + `Longitude` | PostGIS geography type for fast radius queries |

---

## 9. Top 50 Most Valuable Fields

Ranked by long-term platform value across matching, Ask AI, alerts, and search — accounting for population reliability, match impact, and uniqueness of information signal.

| Rank | Field | Tier | Reason |
|---|---|---|---|
| 1 | `ListPrice` | 1 | Most fundamental matching dimension; native column; 25/25 |
| 2 | `StandardStatus` | 1 | Gates all matching — only active listings score; native column; 25/25 |
| 3 | `BedroomsTotal` | 1 | Top buyer and tenant filter; native column; 25/25 |
| 4 | `BathroomsTotalInteger` | 1 | Second top size filter; native column; 25/25 |
| 5 | `LivingArea` | 1 | Size in sqft — price/sqft derived here; native column; 25/25 |
| 6 | `City` | 1 | Primary location dimension; native column; 25/25 |
| 7 | `PostalCode` | 1 | ZIP-level search; native column; 25/25 |
| 8 | `Latitude` + `Longitude` | 1 | Radius search — enables map-based search; 25/25 |
| 9 | `PropertyType` + `PropertySubType` | 1 | Type gate — SFR vs. condo vs. land; 25/25 |
| 10 | `PropertyCondition` | 2 | Buyer preference and valuation signal (Under Construction, New, etc.); 23/25 |
| 11 | `YearBuilt` | 1 | Age filter — decade/era preference is strong; 25/25 |
| 12 | `AssociationFee` + `AssociationYN` | 1 | Monthly cost of ownership; 22/25 |
| 13 | `TaxAnnualAmount` | 1 | Annual tax — true ownership cost; 25/25 |
| 14 | `GarageYN` + `GarageSpaces` | 1 | Top 5 buyer filter nationally; 25/25 |
| 15 | `PoolPrivateYN` | 1 | High-priority FL buyer feature; 25/25 |
| 16 | `WaterfrontYN` | 1 | Premium FL differentiator; 25/25 |
| 17 | `LotSizeSquareFeet` | 1 | Lot size filter; 25/25 |
| 18 | `NewConstructionYN` | 1 | Large buyer segment preference; 25/25 |
| 19 | `CountyOrParish` | 1 | County-level market search; 25/25 |
| 20 | `PublicRemarks` | 3 | Primary Ask AI text source; 25/25 |
| 21 | `PetsAllowed` | 1 | Critical rental dimension; 24/25 |
| 22 | `Furnished` | 1 | Critical rental filter; 9/25 (will improve in rental feed) |
| 23 | `SeniorCommunityYN` | 1 | Legal age-restriction filter; 25/25 |
| 24 | `SubdivisionName` | 1 | Community preference filter; 25/25 |
| 25 | `MLSAreaMajor` | 1 | Sub-market precision location; 25/25 |
| 26 | `InteriorFeatures` | 2 | Rich amenity signal for Ask AI and search; 24/25 |
| 27 | `CommunityFeatures` | 2 | HOA amenity signal; 23/25 |
| 28 | `ExteriorFeatures` | 2 | Outdoor feature signal; 22/25 |
| 29 | `STELLAR_CDDYN` | 1 | FL-specific cost signal; 25/25 |
| 30 | `STELLAR_WaterViewYN` | 1 | Water view premium filter; 25/25 |
| 31 | `ViewYN` + `View` | 1/2 | View filter and type; 25/25 / 5/25 |
| 32 | `OriginalListPrice` | 1 | Price reduction tracking; 25/25 |
| 33 | `PreviousListPrice` + `PriceChangeTimestamp` | 4 | Price alert pair; 22/25 |
| 34 | `StatusChangeTimestamp` | 4 | Alert trigger; 25/25 |
| 35 | `DaysOnMarket` | 4 | Market speed signal; 25/25 |
| 36 | `Appliances` | 2 | Kitchen feature signal; 25/25 |
| 37 | `AssociationAmenities` | 2 | HOA value assessment; 18/25 |
| 38 | `ConstructionMaterials` | 2 | Build quality signal; 25/25 |
| 39 | `STELLAR_FloodZoneCode` | 2 | Risk matching (FL); 17/25 |
| 40 | `ArchitecturalStyle` | 2 | Buyer style preference; 15/25 |
| 41 | `ModificationTimestamp` | 4 | Sync recency; native column; 25/25 |
| 42 | `PhotosCount` / `Media` | 4 | Listing quality signal; 25/25 |
| 43 | `VirtualTourURLUnbranded` | 4 | Recommendation CTR boost; 18/25 |
| 44 | `STELLAR_TotalAnnualFees` | 2 | True annual carrying cost; 22/25 |
| 45 | `LeaseTerm` / `STELLAR_MinimumLease` | 1 | Critical tenant filter (0/25 in sale sample) |
| 46 | `AvailabilityDate` | 1 | Critical tenant filter (0/25 in sale sample) |
| 47 | `STELLAR_SecurityDeposit` | 1 | Tenant move-in cost planning |
| 48 | `SpecialListingConditions` | 4 | Foreclosure/short sale alert; 25/25 |
| 49 | `Zoning` | 1 | Land/investment buyer filter; 24/25 |
| 50 | `STELLAR_CalculatedListPriceByCalculatedSqFt` | 3 | Price/sqft — top Ask AI question; 25/25 |

---

## 10. Proof Section

| Item | Value |
|---|---|
| **Source report** | `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` |
| **Total fields analyzed** | **553** |
| **Tier 1 – Core Matching** | **66** |
| **Tier 2 – Match Enhancement** | **85** |
| **Tier 3 – Ask AI Context** | **116** |
| **Tier 4 – Alert & Recommendation** | **51** |
| **Tier 5 – Search Filters (primary)** | **12** |
| **Tier 6 – Compliance / Excluded** | **223** |
| **Unclassified remainder** | **0** |
| **Total** | **553** |
| **Code files modified** | **None** |
| **Migrations created** | **None** |
| **API or job files changed** | **None** |

### Native Column Coverage Summary

| Metric | Count |
|---|---|
| Total Tier 1 fields | 66 |
| Tier 1 fields with existing native column | 9 |
| Tier 1 fields in raw JSON only | 57 |
| Fields recommended for first-priority promotion | 12 |
| Fields recommended for second-priority promotion | 17 |
| Fields recommended for rental-feed promotion | 10 |
| Fields recommended to remain raw JSON | 505 |

### Compliance Field Summary

| Metric | Count |
|---|---|
| Explicitly compliance-flagged in source audit | 23 |
| Additional agent/brokerage/admin fields (Tier 6) | 200 |
| Total fields in Tier 6 (full exclusion) | 223 |
| Fields requiring legal review before any public use | 23 |
