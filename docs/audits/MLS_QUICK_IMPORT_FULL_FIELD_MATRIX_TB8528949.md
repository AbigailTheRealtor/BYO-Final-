# MLS Quick Import — complete Bridge field matrix (TB8528949)

> Status: **audit only — no behaviour changed by this document.** Generated against the
> LIVE Bridge/Stellar record for `TB8528949`, 2142 Bradford Street Unit 308, Clearwater FL 33760.

Every **populated** field on the record is assigned exactly one disposition, and the
dispositions reconcile back to the populated count — no field disappears unexplained.

## Dispositions

| Disposition | Meaning |
|---|---|
| `CANONICAL BYO (PERSISTED)` | Reaches a BidYourOffer form field today. |
| `CANONICAL BYO (TARGET EXISTS, NOT FED)` | A BYO field **and** an `MlsFieldMap` target exist, but `MlsListingPrefillService::ALLOWED_FIELDS` does not permit the source key, so it is never populated. |
| `MLS DISPLAY (SHOWN)` | Rendered today through `MlsPropertyDetailsPresenter` (Layer C). |
| `MLS DISPLAY (PROPOSED)` | Permitted property fact with no BYO destination and no presenter entry — the growth area for the comprehensive display layer. |
| `MLS AGENT/BROKERAGE` / `PERMISSION NEEDS CONFIRMATION` | Agent, brokerage and office attribution. Locked owner decision of 2026-07-05 excludes these; the owner has since asked for them to be displayed. **Not implemented by assumption.** |
| `MEDIA` | Handled by `MlsMediaExtractor` + `MlsMediaPolicy`, reference-only. |
| `RESTRICTED` | Excluded with a documented reason in the guard-tested `MlsPropertyDetailsPresenter::EXCLUDED`. |
| `SYSTEM/METADATA` | Record plumbing — OData ids, timestamps, feed keys. Not consumer content. |

## The licensing position, as the repository records it

`docs/mls-direct-import-design-and-plan.md`, **Locked decisions (owner: Abigail, 2026-07-05)**, item 1:

> Seller/Landlord prefill imports objective property fields only … No photos, no PublicRemarks,
> no agent/private remarks, no showing instructions, no agent/office contacts.
> Reason: avoids Stellar MLS licensing risk (photo/remarks reuse, retention, rehosting).
> Fuller media only after written Stellar MLS confirmation.

The **photo** clause has since been superseded in this environment: `MLS_MEDIA_LICENSE_ACKNOWLEDGED`
is set, which `config/mls_media.php` defines as an explicit statement that the written
confirmation has been obtained.

The **agent/office** and **PublicRemarks** clauses have **not** been superseded in writing.
They are therefore classified `PERMISSION NEEDS CONFIRMATION` rather than implemented,
per the instruction not to expand licensing-sensitive behaviour by assumption.

---

```
RECORD TB8528949 — 2142 BRADFORD STREET UNIT 308
total keys=552  populated=169

DISPOSITION COUNTS
  CANONICAL BYO (PERSISTED)                   19
  CANONICAL BYO (TARGET EXISTS, NOT FED)      20
  MEDIA                                        5
  MLS DISPLAY (PROPOSED)                      68
  MLS DISPLAY (SHOWN)                         10
  PERMISSION NEEDS CONFIRMATION                6
  RESTRICTED                                  16
  SYSTEM/METADATA                             25
  TOTAL                                      169  (populated = 169) RECONCILES


### CANONICAL BYO (PERSISTED)
  AssociationYN                          1                                -> has_hoa
  BathroomsTotalInteger                  1                                -> bathrooms
  BedroomsTotal                          1                                -> bedrooms
  City                                   CLEARWATER                       -> city
  CountyOrParish                         Pinellas                         -> county
  Latitude                               27.918374                        -> latitude
  ListPrice                              100000                           -> price
  ListingId                              TB8528949                        -> mls_number
  LivingArea                             480                              -> heated_sqft
  Longitude                              -82.717138                       -> longitude
  MlsStatus                              Active                           -> mls_status
  PostalCode                             33760                            -> zip
  PropertySubType                        Condominium                      -> property_sub_type
  PropertyType                           Residential                      -> property_type
  StateOrProvince                        FL                               -> state
  TaxAnnualAmount                        1786.38                          -> annual_taxes
  UnparsedAddress                        2142 BRADFORD STREET UNIT 308    -> address
  WaterfrontYN                           1                                -> waterfront
  YearBuilt                              1986                             -> year_built

### CANONICAL BYO (TARGET EXISTS, NOT FED)
  Appliances                             ["Dryer","Microwave","Range","Refrigerator","Washer"] -> appliances (blocked by ALLOWED_FIELDS)
  BuildingAreaTotal                      480                              -> total_square_feet (blocked by ALLOWED_FIELDS)
  ConstructionMaterials                  ["Block","Stucco"]               -> exterior_construction (blocked by ALLOWED_FIELDS)
  Cooling                                ["Mini-Split Unit(s)"]           -> air_conditioning (blocked by ALLOWED_FIELDS)
  Flooring                               ["Laminate"]                     -> flooring (blocked by ALLOWED_FIELDS)
  FoundationDetails                      ["Slab"]                         -> foundation (blocked by ALLOWED_FIELDS)
  Furnished                              Unfurnished                      -> building_features (blocked by ALLOWED_FIELDS)
  Heating                                ["Central","Other"]              -> heating_and_fuel (blocked by ALLOWED_FIELDS)
  InteriorFeatures                       ["Cathedral Ceiling(s)","Open Floorplan","PrimaryBedroom Ups -> interior_features (blocked by ALLOWED_FIELDS)
  OccupantType                           Tenant                           -> occupant_status (blocked by ALLOWED_FIELDS)
  ParcelNumber                           322916106750020308               -> parcel_id (blocked by ALLOWED_FIELDS)
  Roof                                   ["Shingle"]                      -> roof_type (blocked by ALLOWED_FIELDS)
  STELLAR_FloodZoneCode                  X                                -> flood_zone_code (blocked by ALLOWED_FIELDS)
  Sewer                                  ["Public Sewer"]                 -> sewer (blocked by ALLOWED_FIELDS)
  SubdivisionName                        BRADFORD ACRES A CONDO           -> subdivision (blocked by ALLOWED_FIELDS)
  TaxLegalDescription                    BRADFORD ACRES CONDO PHASE II BLDG 3, UNIT 308 TOGETHER WITH -> legal_description (blocked by ALLOWED_FIELDS)
  TaxYear                                2025                             -> tax_year (blocked by ALLOWED_FIELDS)
  Utilities                              ["Cable Available","Cable Connected","Public","Sewer Availab -> utilities (blocked by ALLOWED_FIELDS)
  WaterSource                            ["Public"]                       -> water (blocked by ALLOWED_FIELDS)
  WaterfrontFeatures                     ["Pond"]                         -> water_access (blocked by ALLOWED_FIELDS)

### MEDIA
  PhotosChangeTimestamp                  2026-07-14T02:11:03.496Z         handled by MlsMediaExtractor/Policy
  PhotosCount                            13                               handled by MlsMediaExtractor/Policy
  STELLAR_CreateAutomaticVirtualTourYN   1                                handled by MlsMediaExtractor/Policy
  STELLAR_TotalPhotosCount               13                               handled by MlsMediaExtractor/Policy
  VirtualTourURLUnbranded                https://www.propertypanorama.com/instaview/stellar/TB8528949 handled by MlsMediaExtractor/Policy

### MLS DISPLAY (PROPOSED)
  BathroomsFull                          1                                permitted property fact, no BYO destination yet
  BathroomsHalf                          0                                permitted property fact, no BYO destination yet
  BathroomsTotalDecimal                  1                                permitted property fact, no BYO destination yet
  BuildingAreaSource                     Public Records                   permitted property fact, no BYO destination yet
  BuildingAreaUnits                      Square Feet                      permitted property fact, no BYO destination yet
  ContractStatusChangeDate               2026-07-13                       permitted property fact, no BYO destination yet
  CoolingYN                              1                                permitted property fact, no BYO destination yet
  CumulativeDaysOnMarket                 16                               permitted property fact, no BYO destination yet
  DaysOnMarket                           16                               permitted property fact, no BYO destination yet
  DirectionFaces                         North                            permitted property fact, no BYO destination yet
  Directions                             Travel east on East Bay Drive. Just past US Hwy 19 and turn  permitted property fact, no BYO destination yet
  HeatingYN                              1                                permitted property fact, no BYO destination yet
  InternetEntireListingDisplayYN         1                                permitted property fact, no BYO destination yet
  LeaseConsideredYN                      1                                permitted property fact, no BYO destination yet
  ListingContractDate                    2026-07-13                       permitted property fact, no BYO destination yet
  LivingAreaSource                       Public Records                   permitted property fact, no BYO destination yet
  LivingAreaUnits                        Square Feet                      permitted property fact, no BYO destination yet
  OnMarketDate                           2026-07-13                       permitted property fact, no BYO destination yet
  OriginalListPrice                      100000                           permitted property fact, no BYO destination yet
  Ownership                              Fee Simple                       permitted property fact, no BYO destination yet
  PostalCodePlus4                        1909                             permitted property fact, no BYO destination yet
  RoadSurfaceType                        ["Paved"]                        permitted property fact, no BYO destination yet
  RoomsTotal                             3                                permitted property fact, no BYO destination yet
  STELLAR_AdditionalLeaseRestrictions    Confirm all lease restrictions with HOA. permitted property fact, no BYO destination yet
  STELLAR_ApprovalProcess                Confirm all lease restrictions with HOA. permitted property fact, no BYO destination yet
  STELLAR_AssociationApprovalRequiredYN  1                                permitted property fact, no BYO destination yet
  STELLAR_AssociationFeeRequirement      None                             permitted property fact, no BYO destination yet
  STELLAR_BuildingNameNumber             2142                             permitted property fact, no BYO destination yet
  STELLAR_CalculatedListPriceByCalculatedSqFt 208.33                           permitted property fact, no BYO destination yet
  STELLAR_CondoFees                      250                              permitted property fact, no BYO destination yet
  STELLAR_CondoFeesTerm                  Monthly                          permitted property fact, no BYO destination yet
  STELLAR_CurrencyMonthlyRentAmt         1200                             permitted property fact, no BYO destination yet
  STELLAR_CurrentPrice                   100000                           permitted property fact, no BYO destination yet
  STELLAR_ExistLseTenantYN               1                                permitted property fact, no BYO destination yet
  STELLAR_FloorNumber                    1                                permitted property fact, no BYO destination yet
  STELLAR_LeaseRestrictionsYN            1                                permitted property fact, no BYO destination yet
  STELLAR_MaxPetWeight                   10                               permitted property fact, no BYO destination yet
  STELLAR_MinimumLease                   No Minimum                       permitted property fact, no BYO destination yet
  STELLAR_MlsMajorChangeType             New Listing                      permitted property fact, no BYO destination yet
  STELLAR_MonthlyCondoFeeAmount          250                              permitted property fact, no BYO destination yet
  STELLAR_MontlyMaintAmtAdditionToHOA    0                                permitted property fact, no BYO destination yet
  STELLAR_OfficeIDXOfficeParticipationYN 1                                permitted property fact, no BYO destination yet
  STELLAR_OfficeSyndicateTo              ["Apartments.com","International MLS","Realtor.com","Zillow  permitted property fact, no BYO destination yet
  STELLAR_PetSize                        Very Small (Under 15 Lbs.)       permitted property fact, no BYO destination yet
  STELLAR_RATIO_CurrentPrice_By_BuildingAreaTotal 208.33                           permitted property fact, no BYO destination yet
  STELLAR_RATIO_CurrentPrice_By_CalculatedSqFt 208.33                           permitted property fact, no BYO destination yet
  STELLAR_SellerRepresentation           Transaction Broker               permitted property fact, no BYO destination yet
  STELLAR_ShowingRequirements            ["Call Listing Agent"]           permitted property fact, no BYO destination yet
  STELLAR_ThirdPartyYN                   1                                permitted property fact, no BYO destination yet
  STELLAR_TotalAnnualFees                3000                             permitted property fact, no BYO destination yet
  STELLAR_TotalMonthlyFees               250                              permitted property fact, no BYO destination yet
  STELLAR_UniversalPropertyId            US-12103-N-322916106750020308-S-308 permitted property fact, no BYO destination yet
  STELLAR_UnparsedAddress                2142 BRADFORD ST #308            permitted property fact, no BYO destination yet
  STELLAR_WaterAccess                    ["Pond"]                         permitted property fact, no BYO destination yet
  STELLAR_WaterAccessYN                  1                                permitted property fact, no BYO destination yet
  STELLAR_WaterView                      ["Pond"]                         permitted property fact, no BYO destination yet
  STELLAR_WaterViewYN                    1                                permitted property fact, no BYO destination yet
  STELLAR_WaterfrontFeetTotal            20                               permitted property fact, no BYO destination yet
  SourceSystemKey                        799934446                        permitted property fact, no BYO destination yet
  SpecialListingConditions               ["None"]                         permitted property fact, no BYO destination yet
  StandardStatus                         Active                           permitted property fact, no BYO destination yet
  StatusChangeTimestamp                  2026-07-14T02:07:24.770Z         permitted property fact, no BYO destination yet
  StreetName                             BRADFORD                         permitted property fact, no BYO destination yet
  StreetNumber                           2142                             permitted property fact, no BYO destination yet
  StreetNumberNumeric                    2142                             permitted property fact, no BYO destination yet
  StreetSuffix                           STREET                           permitted property fact, no BYO destination yet
  TaxBookNumber                          81-126                           permitted property fact, no BYO destination yet
  UnitNumber                             308                              permitted property fact, no BYO destination yet

### MLS DISPLAY (SHOWN)
  AssociationFeeIncludes                 ["Insurance","Maintenance Structure","Maintenance Grounds"," section: Community
  CommunityFeatures                      ["None"]                         section: Community
  ExteriorFeatures                       ["Sidewalk"]                     section: Exterior
  LaundryFeatures                        ["Laundry Closet"]               section: Interior
  Levels                                 ["Two"]                          section: Interior
  MLSAreaMajor                           33760 - Clearwater               section: Property Details
  PetsAllowed                            ["Cats OK","Dogs OK","Size Limit","Yes"] section: Community
  StoriesTotal                           2                                section: Interior
  TaxBlock                               0                                section: Property Details
  TaxLot                                 0                                section: Property Details

### PERMISSION NEEDS CONFIRMATION
  ListAOR                                Suncoast Tampa                   agent/brokerage — locked decision 2026-07-05 excludes; owner now requests display
  ListAgentAOR                           Suncoast Tampa                   agent/brokerage — locked decision 2026-07-05 excludes; owner now requests display
  ListAgentPreferredPhone                727-776-2013                     agent/brokerage — locked decision 2026-07-05 excludes; owner now requests display
  ListOfficeMlsId                        260033704                        agent/brokerage — locked decision 2026-07-05 excludes; owner now requests display
  STELLAR_ListOfficeContactPreferred     727-776-2013                     agent/brokerage — locked decision 2026-07-05 excludes; owner now requests display
  STELLAR_ListOfficeHeadOfficeKeyNumeric 591592334                        agent/brokerage — locked decision 2026-07-05 excludes; owner now requests display

### RESTRICTED
  AssociationName                        Jerilyn Smith                    frequently a named individual
  AssociationPhone                       813-522-6346                     contact information
  ListAgentEmail                         Abigail@RealEstateMatchmakerClub.com contact information
  ListAgentFullName                      Abigail Sweeney                  agent identity
  ListAgentKey                           15c8b5f049e923c990a16e7d51222739 agent identity
  ListAgentMlsId                         260036749                        agent identity
  ListOfficeKey                          d853820ecd03b7d8317758bcc438f0c6 brokerage identity
  ListOfficeName                         REAL ESTATE MATCHMAKER CLUB      brokerage identity
  ListOfficePhone                        727-776-2013                     contact information
  ListingKey                             e8ea2e5193b25dbab98d77f1e11e070d internal identifier
  Media                                  [{"MediaKey":"e8ea2e5193b25dbab98d77f1e11e070d-m1","MediaCat handled by MlsMediaPolicy
  OriginatingSystemKey                   stellar                          internal identifier
  OriginatingSystemName                  Stellar MLS                      internal identifier
  PublicRemarks                          Welcome to this charming corner-unit 1-bedroom, 1-bath loft- authored marketing prose
  STELLAR_PublicRemarksAgent             Welcome to this charming corner-unit 1-bedroom, 1-bath loft- authored marketing prose
  STELLAR_ShowingConsiderations          ["Pet(s) on Premises"]           access instructions

### SYSTEM/METADATA
  @odata.id                              https://api.bridgedataoutput.com/api/v2/OData/stellar/Proper record plumbing, not consumer content
  BridgeModificationTimestamp            2026-07-29T18:18:04.958Z         record plumbing, not consumer content
  Coordinates                            [-82.717138,27.918374]           record plumbing, not consumer content
  Country                                US                               record plumbing, not consumer content
  DocumentsCount                         0                                record plumbing, not consumer content
  FeedTypes                              ["IDX"]                          record plumbing, not consumer content
  IDXParticipationYN                     1                                record plumbing, not consumer content
  InternetAddressDisplayYN               1                                record plumbing, not consumer content
  InternetAutomatedValuationDisplayYN    1                                record plumbing, not consumer content
  ListingKeyNumeric                      799934446                        record plumbing, not consumer content
  ModificationTimestamp                  2026-07-29T18:13:48.823Z         record plumbing, not consumer content
  OriginalEntryTimestamp                 2026-07-14T02:07:24.770Z         record plumbing, not consumer content
  PublicSurveyRange                      16                               record plumbing, not consumer content
  PublicSurveySection                    32                               record plumbing, not consumer content
  PublicSurveyTownship                   29                               record plumbing, not consumer content
  STELLAR_AlternateKeyFolioNum           322916106750020308               record plumbing, not consumer content
  STELLAR_CensusBlock                    2                                record plumbing, not consumer content
  STELLAR_CensusTract                    024508                           record plumbing, not consumer content
  STELLAR_DPRURL                         https://www.workforce-resource.com/dpr/listing/MFRMLS/TB8528 record plumbing, not consumer content
  STELLAR_DPRURL2                        https://www.workforce-resource.com/dpr/listing/MFRMLS/TB8528 record plumbing, not consumer content
  STELLAR_DPRYN                          1                                record plumbing, not consumer content
  STELLAR_EscrowState                    FL                               record plumbing, not consumer content
  STELLAR_GreenVerificationCount         0                                record plumbing, not consumer content
  STELLAR_ListSource                     MATRIX                           record plumbing, not consumer content
  STELLAR_ListSourceOriginal             MATRIX                           record plumbing, not consumer content

```
