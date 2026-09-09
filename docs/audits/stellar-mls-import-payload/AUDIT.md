# Stellar MLS / Bridge Import — Complete Payload Audit

> **Status: AUDIT ONLY. No application source was changed.**
> Branch `audit/stellar-mls-import-complete-payload`, worktree `/home/runner/listing-marketing-ai-mls-import-audit`, base `d342f5447`.
> Date: 2026-09-04.

---

## A. Worktree

| | |
|---|---|
| Repo | `/home/runner/workspace` (git worktree host) |
| Source main | `origin/main` = `d342f5447cf23583041aa0d365abf6b26110a3a2` — *"Merge pull request #121 from AbigailTheRealtor/fix/serve-worker-no-reload"*, 2026-09-03 |
| New branch | `audit/stellar-mls-import-complete-payload` (created from `origin/main`) |
| Worktree path | `/home/runner/listing-marketing-ai-mls-import-audit` |
| Status | Clean at creation. Only additions are this audit directory (`docs/audits/stellar-mls-import-payload/`). No `app/`, `config/`, `resources/`, `routes/`, `database/` file was touched. |
| Other worktrees | Untouched. Nothing deleted, reset or reused. |

### Evidence base

Bridge credentials (`BRIDGE_DATASET`, `BRIDGE_SERVER_TOKEN`) are **present as keys but empty** in the workspace `.env`, so **no live Bridge request was possible from this environment**. The audit was therefore run against the **local cache of real Bridge payloads**:

* `bridge_properties` — **1,224 rows**, every one carrying a complete untouched `raw_json`, imported 2026-08-13 → 2026-09-03.
* Property-type coverage in that cache: **all seven Stellar categories** — Residential Lease 501, Commercial Sale 501, Business Opportunity 199, Residential 18, Commercial Lease 3, Income 1, Vacant Land 1.
* Cross-checked against the prior live field audit `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` (25-record live sample, 553 keys) — the two agree on key count (553 vs 553) and on schema shape.

**Limit of this evidence:** it establishes what Bridge returns **from the `/Property` resource under the current query**. It cannot establish what a `$expand` or a `/Member`, `/Office`, `/OpenHouse` request would return, because those requests have never been made. Those are called out explicitly rather than guessed at.

---

## B. Current MLS Import architecture (exact chain)

There are **two** MLS-number import surfaces, both Seller/Landlord only.

### B1 — "Import by MLS #" prefill (inside the Create Offer wizard)

```
Create Offer Listing (Seller / Landlord Livewire component)
  └ app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php
      · mlsNumberImportAvailable()        ← config mls_direct_import.prefill_enabled + prefill_roles
      · importListingByMlsNumber()
        └ App\Services\Bridge\BridgeListingLookupService::lookupByMlsNumber()
            · local: BridgeProperty::where('listing_id', …)          [cache first]
            · miss:  BridgeApiService::fetchProperties(1, "ListingId eq '…'")
            · BridgePropertyNormalizer::upsert()  → bridge_properties (+ raw_json)
            · ComputeLocationDna::dispatch('bridge', id)  on new/address-changed
            · BridgePropertyCandidateAdapter::fromModel() → PropertyCandidate
        └ App\Services\ListingImport\MlsListingPrefillService::fromCandidate()
            ★ ALLOWED_FIELDS — the facts-only allow-list (43 entries)
        └ HasMlsImport::buildImportPreview()  → checkbox review table
        └ HasMlsImport::applyImportedFields()
            · MlsFieldMap::forRole()  → Livewire public props
            · saveMeta('mls_import_snapshot' | 'mls_address_raw' | 'mls_listing_key')
  └ wizard save → *_agent_auction_metas
  └ render: resources/views/offer-listing/{seller,landlord}/view.blade.php
```

### B2 — MLS Quick Import (the standalone shortened path)

```
GET /offer-listing/{seller|landlord}/import-mls
  └ App\Http\Livewire\OfferListing\QuickImport\{Seller|Landlord}MlsQuickImport
      extends MlsQuickImportComponent
      · findListing()
        └ MlsQuickImportService::lookup()
            · BridgeListingLookupService::lookupByMlsNumber()   (same seam as B1)
            · MlsListingPrefillService::fromCandidate()  →  facts   (43-key allow-list)
            · MlsMediaExtractor::fromRecord($candidate->raw) + MlsMediaPolicy  →  media
            · MlsPropertyDetailsPresenter::present($candidate->raw)  →  details  (69-entry allow-list)
            → MlsQuickImportResult{facts, media, headline, details, listingKey, mlsNumber, mlsStatus}
      · acceptProperty()
        └ MlsQuickImportDraftWriter::materialise()
            · owner-scoped draft resolve/create; ListingWorkflow::stamp(OFFER_LISTING)
            · writeFacts()      ← $result->facts  via MlsFieldMap        ✅ persisted
            · writeGallery()    ← $result->media  via MlsListingGallerySync ✅ persisted
            · writeProvenance() ← mls_provider / mls_number / mls_listing_key / mls_imported_at /
                                  mls_source_status / mls_source_property_type / mls_quick_import
            · ✗ $result->details is NEVER PASSED IN AND NEVER PERSISTED
      · terms → review (review screen re-derives details via mlsDetailsFor())
      · publish() → redirect offer.listing.{seller|landlord}.view
  └ render: SellerOfferListingController::view() / LandlordOfferListingController::view()
      → resources/views/offer-listing/{seller,landlord}/view.blade.php
        (renders BYO meta only — no MLS Details section exists on this page)
```

**Feature gates:** `MLS_DIRECT_IMPORT_PREFILL_ENABLED` (currently `true` in `.env`), `MLS_DIRECT_IMPORT_QUICK_IMPORT_ENABLED` (`true`), roles fixed to `['seller','landlord']`. Media is separately gated by `MLS_MEDIA_IMPORT_ENABLED` **and** `MLS_MEDIA_LICENSE_ACKNOWLEDGED` (both unset ⇒ default `false` ⇒ **no photo currently reaches an imported listing**).

### C. Current Stellar search / detail architecture

```
GET /stellar/buyer/results     → StellarBuyerResultsController
  └ BuyerCriteriaLoader / TenantCriteriaLoader → criteria payload
  └ Stellar\Matching\BuyerMatchService
      · LazyBridgeImportService (cache freshness by criteria hash)
        └ BuyerCriteriaODataFilterBuilder / TenantCriteriaODataFilterBuilder → $filter
        └ BridgeApiService::fetchPropertiesPaginated($top,$skip,$filter)
        └ BridgePropertyNormalizer::upsert() → bridge_properties
      · BuyerMatchQueryBuilder (local SQL over native columns) → BuyerMatchScorer
  └ Stellar\BuyerResultViewMapper → resources/views/stellar/buyer/results.blade.php

GET /stellar/property/{listingKey} → StellarPropertyDetailController
  └ BridgeProperty::where(listing_key)->where(standard_status,'Active')->firstOrFail()
  └ IDX gate: raw['IDXParticipationYN'] must not be false → else 403
  └ Stellar\PropertyDetailViewMapper::map()   ★ 78 RESO keys
  └ view stellar/property/detail.blade.php + x-stellar.property-* components
      photo-carousel · header · description(PublicRemarks) · interior · exterior ·
      community · utilities · key-facts · hoa-taxes · schools · office(ListOfficeName) · map
  └ (+ Matchmaker Analysis: Location DNA, DNA personality, match context)
```

### D. Listing Link importer — proven separate

`HasMlsImport::importListingFromUrl()` → `App\Services\ListingImport\MlsListingImportService` (URL scrape / pasted-text parser). It holds **no Bridge credential**, is **not gated** by `mls_direct_import.prefill_enabled`, and produces the same `['success','data','error']` shape so the shared preview/apply machinery consumes either. Nothing in this audit's findings or recommendations touches it.

### E. Manual Create — proven separate

`HasMlsImport::chooseManualListing()` simply closes the import modal and leaves the user on the ordinary Create Offer Livewire form (`/offer-listing/{role}` routes). It runs no importer and no Bridge code.

---

## D. The actual Bridge request

**One endpoint, ever.** `App\Services\Bridge\BridgeApiService`:

```
GET https://api.bridgedataoutput.com/api/v2/OData/{dataset}/Property
      ?$top={limit}[&$skip={offset}][&$filter={odata}]&access_token={token}
```

| OData option | Used? | Evidence |
|---|---|---|
| `$select` | **NO** | `grep -rn '\$select'` over `app/` returns zero OData occurrences. Both methods build `$params` with only `$top`, `$skip`, `$filter`, `access_token`. |
| `$expand` | **NO** | Same grep. No navigation property is ever expanded. |
| `$filter` | Yes | `ListingId eq '…'` (MLS # import) · `ListingKey eq '…'` · address-part equality/`contains` (address search) · `BuyerCriteriaODataFilterBuilder` / `TenantCriteriaODataFilterBuilder` (search flow) |
| `$top` | Yes | 1 (single lookup), 25 (address search), 200 (`bridge.lazy_page_size`), 10 (admin preview) |
| `$skip` | Yes | pagination in `fetchPropertiesPaginated()` only |
| `$orderby` / `$count` / `$metadata` | **NO** | not referenced anywhere |
| Secondary resources (`/Media`, `/Member`, `/Office`, `/OpenHouse`) | **NEVER REQUESTED** | `$baseUrl` is fixed and the only path segment appended is `/Property` |

**Conclusion on API-query truncation: there is none for the Property resource.** Because no `$select` is sent, Bridge returns its full default Property projection — 553 distinct keys in practice — and **`BridgePropertyNormalizer` stores 100% of it verbatim in `bridge_properties.raw_json`**. Every loss in this system happens *after* the payload is already in our database.

**There is, however, relationship truncation.** `Media` is embedded on the Property resource and does arrive. `OpenHouse`, `Rooms` and `Units` keys are **entirely absent from the Property payload** in all 1,224 records — they are separate Bridge resources that would need their own request, and none is made. `STELLAR_OpenHouseCount` is populated on 7 records with values of 1 and 3, proving open houses exist for listings we hold and that we have only the count.

---

## E. Complete Bridge field inventory

Measured over 1,224 cached raw payloads spanning all seven property types.

| Measure | Count |
|---|---|
| Distinct top-level Property keys observed | **553** |
| …populated at least once | **402** |
| …key present but null/empty in every record | **151** |
| Nested object containers on Property | **1** (`Media`) |
| `Media` sub-fields | **11** — `MediaKey`, `MediaCategory`, `MediaURL`, `MediaObjectID`, `ResourceRecordKey`, `ResourceName`, `ClassName`, `MimeType`, `Order`, `Permission`, `LongDescription` (+ `ShortDescription`, present but null in 34,248/34,248) |
| Media objects held | **34,248** across 1,202 records (~28/listing, max 100) |
| Separate Bridge resources reachable but never requested | `Member`, `Office`, `OpenHouse`, `Room`, `Unit` (existence inferred from Bridge/RESO; **not verified live — no credentials**) |

Full per-type inventory: `bridge-field-inventory-by-property-type.tsv` (554 rows × 12 columns).

---

## F. Field trace matrix

**`field-trace-matrix.tsv` — 553 rows, one per Bridge field.** Columns:

`BridgeField · Type · Populated/1224 · Example · NativeColumn · OnPropertyCandidate · ImportMapsToForm · ReviewPresenterSection · StellarDetailDisplays · ImportedListingDisplays · Status`

Status distribution:

| Status | Fields |
|---|---|
| RAW-JSON ONLY (populated, never read by any code) | **288** |
| NOT POPULATED IN SAMPLE (key present, always empty) | **151** |
| FULL PARITY (search shows it *and* import maps it) | **34** |
| SEARCH + REVIEW ONLY — import loses it | **24** |
| PERMISSION / DISPLAY-RIGHTS RESTRICTION | **19** |
| REVIEW ONLY (presenter shows it, never persisted) | **15** |
| SEARCH ONLY — import loses it | **13** |
| IMPORT ONLY (import maps it, search does not show it) | **7** |
| NORMALIZER CARRIES / IMPORT MAPPER DROPS IT | **2** |

Note on the two exclusive columns: `ReviewPresenterSection` marks a field the **quick-import review screen** renders (and then discards); `ImportedListingDisplays` is `Y` only where the value reaches a Create Offer field that the published listing view renders. The presenter's output is never written to the listing, so a "REVIEW ONLY" row means *shown once, during import, then gone*.

---

## G. Missing-field totals (measured, not estimated)

| Question | Answer |
|---|---|
| Bridge Property fields available to us | **553** (402 populated) |
| Currently requested from Bridge | **553** — all of them (no `$select`) |
| Stored in `raw_json` | **553 — 100%** |
| Promoted to a native `bridge_properties` column | **32** |
| Carried on the typed `PropertyCandidate` DTO | **50** |
| Mapped into a Create Offer form field by MLS Import | **43** (of which 2 — `ListingId`, `ListingKey` — are provenance handles, so **41 facts**) |
| Rendered on the imported listing after publish | **41 facts**, via existing BYO fields only. **Zero** additional MLS attributes. |
| Shown on the quick-import **review** screen only, then discarded | **59 populated** presenter fields (69 allow-list entries; 5 name keys the feed never sends, 5 more are always empty) |
| Shown on Stellar search/detail | **78** RESO keys |
| Search shows it / import loses it | **37** (24 also appear on the review screen; 13 appear nowhere in the import path) — plus **5** more lost under display-rights exclusions |
| Populated Bridge fields no code path reads at all | **288** |
| Media fields/resources missing | Media itself arrives in full; **`PreferredPhotoYN` never sent** (so cover selection always falls back), **`Permission` never read**, **50-photo cap truncates 186 of 1,202 listings**, and OpenHouse/Rooms/Units resources are never fetched |
| Agent/member/office fields available but never surfaced | **17 populated** ListAgent/ListOffice/CoList fields (see §I) |
| Permission/display-rights restricted | **19** in the presenter's `EXCLUDED` list, of which **12 are actually populated** in the feed |

---

## H. Photos / media findings

1. **What Bridge provides.** A `Media[]` array embedded on the Property resource. 34,248 objects across 1,202 records. 11 populated sub-fields (see §E).
2. **Are all photos requested?** Yes, implicitly — no `$select`, so the whole array arrives. **`PhotosCount` equals `count(Media)` on 1,202 of 1,202 records**, which proves Bridge is not truncating the array for us.
3. **Ordering.** `Order` is dense, 1-based, unique per listing (range 1..100, zero listings with duplicate `Order` values). `MlsMediaExtractor::assignSequence()` re-derives a dense zero-based sequence with a stable sort — correct, and defensive beyond what this feed needs.
4. **URL.** `MediaURL` only; the extractor accepts 6 aliases, only `MediaURL` is ever present. All URLs are `https://dvvjkgh94f2v6.cloudfront.net/...`, `image/jpeg`.
5. **Captions.** `ShortDescription` is present-but-**null on all 34,248 objects**; `LongDescription` is populated on **4,445**. The extractor's caption chain (`ShortDescription → Caption → LongDescription → Description`) therefore works, and captions do survive.
6. **Category / type.** `MediaCategory` is `"Photo"` on **100%** of objects. The policy's allow-list (`Photo, Image, Property Photo, Floor Plan`) admits everything present.
7. **Primary / preferred photo.** ⚠️ **`PreferredPhotoYN` — and every alias the extractor accepts (`PreferredPhoto`, `IsPrimary`, `PrimaryYN`) — is absent from every media object.** `MlsMediaItem::isPreferred` is therefore always `false`, and `MlsListingGallerySync::applyCover()` falls through to `$entries[0]` (i.e. `Order = 1`). That is a sane result for this feed, but the "feed's preferred image" branch is dead code and nothing tells an operator so.
8. **Image count.** Mean ≈ 28.5, max 100.
9. **Store or copy?** **Reference only.** `config/mls_media.hosting_mode = 'reference'` is the only implemented mode, `MlsMediaPolicy::hostingModeSupported()` rejects anything else outright, no bytes are ever downloaded, and `auction/images/` is untouched.
10. **Do imported listings display every permitted photo?** ⚠️ **Currently they display none**, because `MLS_MEDIA_IMPORT_ENABLED` and `MLS_MEDIA_LICENSE_ACKNOWLEDGED` are both unset (default `false`). With the flags on: **`mls_media.max_images = 50` truncates the tail on 186 of 1,202 listings (15.5%)** that carry more than 50 photos.
11. **Does search show photos the importer does not?** Yes, today, absolutely — `PropertyDetailViewMapper::parsePhotos()` is gated by nothing but the IDX flag and renders up to 50 photos on `/stellar/property/{key}`, while the import path renders zero. That asymmetry is a **licensing inconsistency, not just a feature gap**: the same imagery is already published on one surface under no licence flag and blocked on the other under two.
12. **Do photos disappear after import?** No. `MlsListingGallerySync` preserves user uploads, never empties a gallery on an empty incoming set, keeps owner cover choice and owner ordering, and re-derives cover when the chosen entry vanishes.
13. **Idempotency / dedup.** Yes — `MlsMediaExtractor::deduplicate()` by media key, and the sync matches on stored MLS key so a re-import updates in place rather than duplicating.

**Additional media gap:** the feed sends `Permission: ["Public"]` on every object. `MlsMediaPolicy::allowsItem()` reads `PermittedForPublicDisplay` / `PublicDisplayYN` / `InternalOnlyYN` — **none of which this feed sends — and never reads `Permission`.** A future object carrying `Permission: ["Private"]` would pass the policy. The comment in `config/mls_media.php` also claims a "permitted MIME hint" check that `allowsItem()` does not perform.

---

## I. Agent / member / office findings

Measured over 1,224 records / 709 distinct list agents / 546 distinct offices — this is real MLS-wide data, not one office's own listings.

| Field | Populated | Distinct | Present in payload? | Reaches import? | Reaches search/detail? |
|---|---|---|---|---|---|
| `ListAgentFullName` | 1202 | 709 | ✅ | ✗ excluded | ✗ |
| `ListAgentMlsId` | 1202 | 709 | ✅ | ✗ excluded | ✗ |
| `ListAgentKey` | 1202 | 709 | ✅ | ✗ excluded | ✗ |
| `ListAgentEmail` | 1202 | 709 | ✅ | ✗ excluded | ✗ |
| `ListAgentPreferredPhone` | 1198 | 705 | ✅ | ✗ excluded | ✗ |
| `ListAgentAOR` / `ListAOR` | 1202 | 20 | ✅ | ✗ | ✗ |
| `ListAgentDirectPhone` / `OfficePhone` / `MobilePhone` / `URL` / `KeyNumeric` | — | — | ❌ **key not in payload at all** | n/a | n/a |
| `ListAgentFirstName` / `LastName` / `StateLicense` | 0 | — | key present, always null | n/a | n/a |
| `ListOfficeName` | 1202 | 546 | ✅ | ✗ excluded | ✅ **rendered** (`x-stellar.property-office`) |
| `ListOfficePhone` | 1202 | 560 | ✅ | ✗ excluded | ✗ |
| `ListOfficeMlsId` / `ListOfficeKey` | 1202 | 569 | ✅ | ✗ excluded | ✗ |
| `STELLAR_ListOfficeContactPreferred` | 1202 | 561 | ✅ | ✗ | ✗ |
| `STELLAR_ListOfficeHeadOfficeKeyNumeric` | 1202 | 522 | ✅ | ✗ | ✗ |
| `ListOfficeURL` / `Email` / `Fax` / `Address1` / `City` / `State` / `PostalCode` | — | — | ❌ **key not in payload** | n/a | n/a |
| `CoListAgentFullName` / `CoListAgentMlsId` | 143 | 114 | ✅ | ✗ | ✗ |
| `CoListOfficeName` / `CoListOfficeMlsId` | 144 / 143 | 95 / 99 | ✅ | ✗ | ✗ |
| `CoListAgentEmail` / `Phone` / `Key`, `CoListOfficePhone` / `Key` | — | — | ❌ **key not in payload** | n/a | n/a |
| `BuyerAgent*` / `BuyerOffice*` | 0 | — | key present, always null (cache holds only Active listings) | n/a | n/a |
| `ListTeamName` | 15 | 13 | ✅ | ✗ | ✗ |
| `AssociationName` | 472 | 297 | ✅ | ✗ excluded | ✅ **rendered as "HOA name"** |
| `AssociationPhone` | 144 | 102 | ✅ | ✗ excluded | ✗ |
| `STELLAR_AssociationEmail` | 20 | 20 | ✅ (e.g. `Karla Baumann <kscpoa@aol.com>`) | ✗ excluded | ✗ |
| `STELLAR_PropertyManager` / `PropertyManagerPhone` | 31 / 24 | 31 / 23 | ✅ | ✗ | ✗ |
| `STELLAR_CallCenterPhoneNumber` | 99 | 86 | ✅ | ✗ | ✗ |
| `BuyerAgencyCompensation` / `SubAgencyCompensation` / `TransactionBrokerCompensation` | — | — | ❌ **key not in payload** (post-settlement removal) | n/a | n/a |
| `STELLAR_SellerRepresentation` / `STELLAR_Representation` | 616 / 29 | 4 / 1 | ✅ | ✗ | ✗ |

**Reporting the way you asked:**

* **FIELD AVAILABLE — DISPLAY RIGHTS NEED CLARIFICATION:** `ListAgentFullName`, `ListAgentMlsId`, `ListAgentKey`, `ListAgentEmail`, `ListAgentPreferredPhone`, `ListOfficeName`, `ListOfficePhone`, `ListOfficeMlsId`, `ListOfficeKey`, `STELLAR_ListOfficeContactPreferred`, `CoListAgentFullName`, `CoListAgentMlsId`, `CoListOfficeName`, `CoListOfficeMlsId`, `ListTeamName`, `AssociationName`, `AssociationPhone`, `STELLAR_AssociationEmail`, `STELLAR_PropertyManager(Phone)`, `STELLAR_CallCenterPhoneNumber`. All technically present, all currently withheld by `MlsPropertyDetailsPresenter::EXCLUDED` or simply unread.
* **FIELD NOT AVAILABLE AT ALL — no Bridge/expansion needed, it is not in the feed:** every `ListAgentDirectPhone` / `MobilePhone` / `OfficePhone` / `URL` / `StateLicense`, every `ListOffice` address or URL or email or fax, every CoList contact detail, and all three compensation fields. **These would require the `/Member` and `/Office` Bridge resources, which we have never queried.** Whether Stellar's Bridge dataset exposes them is *unverified* — no credentials in this environment.
* **The one live inconsistency:** `ListOfficeName` is in the presenter's `EXCLUDED` list ("brokerage identity") **and simultaneously rendered on the public `/stellar/property/{key}` page** by `x-stellar.property-office`. Same for `AssociationName` ("frequently a named individual") which is rendered as the HOA name. Two surfaces, two opposite decisions, on the same data.

---

## J. Search-vs-import parity — SEARCH HAS / IMPORT LOSES

Of the **78** RESO keys `PropertyDetailViewMapper` renders on `/stellar/property/{listingKey}`:

* 34 — **FULL PARITY** (also reach a Create Offer field)
* 5 — withheld from import by display-rights exclusions (`PublicRemarks`, `AssociationName`, `ListOfficeName`, `Media`, `ListingKey`)
* 2 — never populated in the sample (`AssociationFee`, `Stories`)
* **37 — SEARCH HAS / IMPORT LOSES**

The 37, with population out of 1,224:

**Shown on the quick-import review screen and then discarded (24):**
`AccessibilityFeatures` 59 · `AssociationAmenities` 113 · `AssociationFeeFrequency` 1 · `CarportSpaces` 191 · `CommunityFeatures` 391 · `ExteriorFeatures` 135 · `FireplaceFeatures` 9 · `FireplaceYN` 122 · `GarageSpaces` 257 · `LaundryFeatures` 364 · `Levels` 673 · `LotSizeAcres` 1061 · `NewConstructionYN` 1090 · `OtherStructures` 100 · `ParkingFeatures` 288 · `PatioAndPorchFeatures` 140 · `PetsAllowed` 528 · `PoolFeatures` 205 · `SeniorCommunityYN` 493 · `SpaFeatures` 42 · `SpaYN` 64 · `SubdivisionName` 877 · `View` 151 · `WindowFeatures` 174

**Absent from the import path entirely (13):**
`BathroomsFull` 505 · `BathroomsHalf` 487 · `DaysOnMarket` 1202 · `ElementarySchool` 69 · `HighSchool` 69 · `MiddleOrJuniorSchool` 68 · `OnMarketDate` 1134 · `OriginalListPrice` 1202 · `SecurityFeatures` 161 · `STELLAR_WaterViewYN` 1199 · `UnitNumber` 399 · `ViewYN` 1202 · `VirtualTourURLUnbranded` 979

The last group is the sharpest: **schools, days-on-market, original list price, unit number, virtual tour and the water-view flag are on the Stellar page and simply do not exist on an imported listing** — and `STELLAR_WaterViewYN` even has a native `bridge_properties.water_view_yn` column *and* a `water_view` entry in `MlsFieldMap` for both roles. It is fetched, promoted to a column, mapped to a form field, and still never crosses the prefill allow-list.

---

## K. Property-type gaps

Fields concentrated in one property type (≥15% populated there, ≤5% elsewhere) and **not** reached by the import:

**Residential Lease (n=501)** — 19 fields, all dropped:
`AvailabilityDate` 100% · `LeaseAmountFrequency` 100% · `OwnerPays` 100% · `STELLAR_LongTermYN` 100% · `STELLAR_ApplicationFee` 99% · `STELLAR_MonthsAvailable` 72% · `STELLAR_RentSpreeYN` 59% · `STELLAR_SecurityDeposit` 43% · `TenantPays` 43% · `PoolFeatures` 41% · `CarportSpaces` 38% · `STELLAR_SeasonalRent` 31% · `View` 30% · `PatioAndPorchFeatures` 28% · `STELLAR_OffSeasonRent` 28% · `STELLAR_AssociationApprovalFee` 26% · `STELLAR_NumberOfPets` 21% · `STELLAR_RentSpreeURL` 20% · `STELLAR_MasterBedSize` 16%.
Also feed-wide but lease-relevant: `LeaseTerm` 216 · `STELLAR_MinimumLease` 213 · `STELLAR_PetDepositFee` 64 · `STELLAR_PetFeeNonRefundable` 44 · `STELLAR_PetRestrictions` 66 · `STELLAR_PetSize` 72 · `STELLAR_MaxPetWeight` 62 · `STELLAR_AdditionalApplicantFee` 51 · `OccupantType` 631.
**Six of these have an existing `MlsFieldMap` landlord target already** (`available_date`, `lease_available_date`, `lease_amount_frequency`, `minimum_security_deposit`, `terms_of_lease`, `tenant_pays`, `rent_includes`, `minimum_lease_months`, `pets_allowed`) — the form field exists, the feed value exists, and only the prefill allow-list entry is missing.

**Business Opportunity (n=199)** — 4 fields, all dropped:
`BusinessName` 99% · `BusinessType` 99% · `YearEstablished` 99% · `STELLAR_BusinessOpportunityWithRealEstateYN` 99%.
`business_type` already has a Seller `MlsFieldMap` target.

**Income (n=1)** — 3 fields, all dropped: `CapRate` · `GrossScheduledIncome` · `NetOperatingIncome`. All three have Seller `MlsFieldMap` targets (`cap_rate`, `gross_annual_income`, `net_operating_income`). Feed-wide: `GrossIncome` 22 · `STELLAR_AnnualExpenses` 28 · `STELLAR_AnnualIncomeType` 48 · `STELLAR_NetOperatingIncomeType` 43 · `NumberOfUnitsTotal` 80 · `NumberOfBuildings` 548 · `NumberOfSeparateElectricMeters` 57 / `Gas` 11 / `Water` 44.

**Commercial Sale (n=501) & Commercial Lease (n=3)** — the commercial vocabulary is broad and entirely dropped:
`BuildingFeatures` 196 · `STELLAR_CeilingHeight` 86 · `STELLAR_CeilingType` 89 · `STELLAR_NumofOffices` 49 · `STELLAR_NumofBaysGradeLevel` 15 · `STELLAR_NumofBaysDockHigh` 6 · `STELLAR_NumofConferenceMeetingRooms` 15 · `STELLAR_DoorHeight` 9 / `DoorWidth` 11 / `EavesHeight` 6 / `GarageDoorHeight` 10 · `STELLAR_OfficeRetailSpaceSqFt` 62 · `STELLAR_LeasableArea` 15 · `STELLAR_FreestandingYN` 84 · `STELLAR_FreezerSpaceYN` 67 · `STELLAR_SpaceType` 17 · `STELLAR_ComTransactionType` 15 / `ComTransactionTerms` 26 · `STELLAR_Management` 137 · `STELLAR_FutureLandUse` 28 · `STELLAR_UseCode` 48 · `Zoning` 437 · `ZoningDescription` (always empty) · `STELLAR_AdjoiningProperty` 156 · `STELLAR_ConvertedResidenceYN` 82 · `STELLAR_MillageRate` 15 · `NumberOfLots` 126 · `RoadFrontageType` 624 / `RoadSurfaceType` 1026 / `RoadResponsibility` 189 · `Ownership` 699 · `STELLAR_ExistLseTenantYN` 9 · `TotalActualRent` (always empty).

**Vacant Land (n=1)** — no type-exclusive fields at this sample size. Land-relevant feed-wide fields that are dropped: `STELLAR_TotalAcreage` 545 · `LotSizeDimensions` 217 · `LotSizeArea` 982 · `LotSizeUnits` 982 · `Vegetation` 69 · `Topography` (always empty) · `PublicSurveySection/Township/Range` ~517 each · `STELLAR_PricePerAcre` (always empty) · `HorseAmenities` 1.

**Residential (n=18)** — dropped: `STELLAR_HomesteadYN`, `STELLAR_DPRYN`/`DPRURL`, `LeaseConsideredYN`, `LandLeaseYN`, `STELLAR_LeaseRestrictionsYN`, `STELLAR_AdditionalLeaseRestrictions`, `STELLAR_ExistLseTenantYN`, `STELLAR_InLawSuiteYN` 350, `STELLAR_AdditionalRooms` 73, `STELLAR_FloorNumber` 303, `STELLAR_BuildingNameNumber` 328, `STELLAR_BuildingElevatorYN` 120.

**Cross-type, high population, dropped, and with an existing MlsFieldMap target already:**
`LotSizeAcres` 1061 → `lot_size_acres` · `Zoning` 437 → `zoning` · `CarportYN` 491 → `carport` · `GarageSpaces` 257 → `garage_spaces` · `STELLAR_FloodZonePanel` 262 → `flood_zone_panel` · `STELLAR_FloodZoneDate` 268 → `flood_zone_date` · `AdditionalParcelsYN` 557 → `additional_parcels` · `STELLAR_WaterViewYN` 1199 → `water_view` · `STELLAR_WaterfrontFeetTotal` 1189 → `waterfront_feet` · `LotSizeDimensions` 217 → `lot_dimensions` · `AssociationFeeFrequency` → `association_fee_frequency` · `LivingAreaSource` 509 → `sqft_heated_source` · `PublicRemarks` 1202 → `description` (compliance decision).

**That is the single most actionable finding in the audit: `MlsFieldMap` already has 74 seller and 68 landlord canonical targets, the Bridge prefill supplies only 43 of them, and at least 13 of the missing ones have both an existing form field and a populated Bridge source.**

---

## L. Exact root causes, separated by layer

| # | Layer | What is lost | Cause | Scale |
|---|---|---|---|---|
| 1 | **API query** | Nothing on the Property resource | No `$select` is sent — the full projection arrives | **0 fields** |
| 2 | **API relationships** | Open houses, room-by-room data, unit rosters, agent/office contact details beyond the six inline fields | `BridgeApiService` only ever appends `/Property`; no `$expand`, no `/Member`, `/Office`, `/OpenHouse` request exists | 5 resources, unmeasured field count |
| 3 | **Cache normalization** | Nothing | `BridgePropertyNormalizer` writes `raw_json = json_encode($record)` — full fidelity — alongside 32 promoted columns | **0 fields** |
| 4 | **DTO adapter** | 503 fields never acquire a typed property | `BridgePropertyCandidateAdapter` reads 32 columns + 18 named `raw` keys. Deliberate ("named keys only, no loop over `$raw`") but it is the first real narrowing | 553 → **50** |
| 5 | **Prefill allow-list** | 359 populated fields never reach a form | `MlsListingPrefillService::ALLOWED_FIELDS` — 43 entries, structurally enforced, `$raw` never read | 402 populated → **43** |
| 6 | **Field map coverage** | ~31–37 existing form fields per role stay empty | `MlsFieldMap` has the target; `ALLOWED_FIELDS` has no entry to feed it | **13+** confirmed fixable pairs |
| 7 | **Persistence of Layer C** | Every display-only MLS attribute | `MlsQuickImportResult::$details` is built, shown on the review screen, and **never passed to `MlsQuickImportDraftWriter`**. `writeFacts()` iterates `$result->facts` only. | **59 populated** attributes |
| 8 | **Listing UI** | Same as #7 | `resources/views/offer-listing/{seller,landlord}/view.blade.php` has no MLS Details section; `mlsDetailsFor()` exists only on the quick-import component | — |
| 9 | **Dead storage** | `mls_import_snapshot` | Written by `HasMlsImport::applyImportedFields()`, **read by nothing** — grep confirms zero consumers outside the writer. And it stores the already-narrowed 43-key canonical set, not the raw record. | 1 meta key |
| 10 | **Media flags** | All photos, today | `MLS_MEDIA_IMPORT_ENABLED` + `MLS_MEDIA_LICENSE_ACKNOWLEDGED` both default `false` | 34,248 objects |
| 11 | **Media cap** | Photos 51..100 | `mls_media.max_images = 50` | **186 of 1,202 listings** |
| 12 | **Display-rights exclusions** | Remarks, agent, office, HOA contact | `MlsPropertyDetailsPresenter::EXCLUDED` — 19 entries, 12 populated | 12 populated fields |

Answering your A–E quantification directly:

* **A. Bridge never requested the field** — **0** for Property attributes; **5 resources** (Member, Office, OpenHouse, Room, Unit) never requested.
* **B. Bridge returned it but normalization dropped it** — **0**. `raw_json` is complete.
* **C. raw_json retained it but the importer ignored it** — **359 populated fields** (402 populated − 43 mapped). This is the dominant category by an order of magnitude.
* **D. Importer retained it but the listing UI ignored it** — **59** (the whole presenter output; retained through the review screen, never persisted or rendered on the listing).
* **E. Secondary Bridge entities never fetched** — Media is inline and *is* fetched; **OpenHouse, Rooms, Units, Member, Office are not.**

---

## M. Recommended implementation architecture (smallest safe shape)

The goal — *every legitimate Stellar fact is preserved, and every displayable fact can appear on the imported listing even where no BYO form field exists* — needs **four** changes, in this order. Three of them are additive and touch no existing behaviour.

### M1. Persist Layer C (highest value, lowest risk)

`MlsPropertyDetailsPresenter` already solves the "hundreds of facts, no form fields" problem and already has a guard test. It is simply not wired to storage.

* Pass `MlsQuickImportResult::$details` into `MlsQuickImportDraftWriter::materialise()`.
* Write it as **one** EAV meta blob, `mls_property_details`, alongside the existing `mls_provider` / `mls_imported_at` provenance keys. One key, not 60, so no schema work and no meta-row explosion.
* Store the presenter's *output* (section → `[{label, value}]`), not raw RESO, so the display allow-list is applied at write time and a later widening of `FIELDS` cannot retroactively publish something on an old listing without a re-import.
* Render it in a new **"MLS Property Facts"** partial included by both listing views, clearly attributed to the MLS and visually distinct from user-entered data.
* **Re-check the flags at render time**, exactly as `MlsMediaPolicy` requires for media.

This alone takes the imported listing from 41 facts to ~100.

### M2. Widen `ALLOWED_FIELDS` for the pairs that already have a form field

For each of the ~13 confirmed cases in §K, add one `ALLOWED_FIELDS` entry (and, where needed, one `PropertyCandidate` property + one `BridgePropertyCandidateAdapter` line). No new form controls, no schema change, no new compliance surface — these are objective property facts with a destination that already exists and already renders.

Priority order by population × form-target existence: `LotSizeAcres`, `STELLAR_WaterViewYN`, `Zoning`, `CarportYN`, `AdditionalParcelsYN`, `GarageSpaces`, `STELLAR_FloodZonePanel`, `STELLAR_FloodZoneDate`, `LotSizeDimensions`, `AvailabilityDate`, `LeaseAmountFrequency`, `LeaseTerm`, `STELLAR_SecurityDeposit`, `TenantPays`, `OwnerPays`, `STELLAR_MinimumLease`, `BusinessType`, `CapRate`, `GrossIncome`/`GrossScheduledIncome`, `NetOperatingIncome`, `STELLAR_OfficeRetailSpaceSqFt`, `STELLAR_CeilingHeight`, `NumberOfUnitsTotal`, `STELLAR_WaterfrontFeetTotal`.

Note the two known blockers, which are product decisions rather than mapping work: `pets_allowed` and `rent_includes`/`tenant_pays`/`building_features_list`/`current_use_list` currently have **no `wire:model` binding**, so importing them writes a value the user cannot see or correct. Bind the field or leave the mapping out; do not import into an unbound field.

### M3. Close the search/import parity gap deliberately

For the 13 fields in §J that appear nowhere in the import path — schools, DOM, original list price, unit number, virtual tour, `BathroomsFull/Half`, `SecurityFeatures` — the right destination is **M1's MLS Property Facts section**, not new form fields. Add them to `MlsPropertyDetailsPresenter::FIELDS` (they are already published on `/stellar/property/{key}`, so the display-rights precedent is set by our own live behaviour), except `VirtualTourURLUnbranded`, which deserves its own link treatment rather than a text row.

While doing this, fix the five presenter entries that name keys the feed never sends (`BasementYN`, `FurnishedYN`, `RoofType`, `LotDimensions`, `ParkingTotal`) — the correct keys are `Basement`, `Furnished`, `Roof`, `LotSizeDimensions` and there is no parking total in this feed.

### M4. Resolve the two live inconsistencies before anything ships

1. **`ListOfficeName` and `AssociationName` are excluded on one surface and published on another.** Pick one answer and apply it to both. IDX rules generally *require* brokerage attribution on displayed listings, which argues for including `ListOfficeName` (and probably `ListOfficePhone`) rather than excluding it — but that is a licence question, not an engineering one.
2. **Media is published on `/stellar/property/{key}` under no licence flag while the import path is blocked by two.** Either the Stellar page needs the same gate, or the import gate is stricter than the licence requires. Today the codebase asserts both positions simultaneously.

### What is explicitly NOT recommended

* **Do not add a Bridge `$select`.** The absence of one is why `raw_json` is complete. Adding one would create the API-truncation failure mode this system currently does not have.
* **Do not store raw RESO on the listing.** Store presenter output. The allow-list must be applied at write time.
* **Do not add hundreds of Create Offer inputs.** M1 is the answer to that, exactly as your §11 anticipated.
* **Do not change the coordinate ladder, Location DNA, Census, or introduce Google Places.** See §N.
* **Do not fetch Member/Office/OpenHouse resources yet** — that is new outbound provider surface, new rate-limit exposure and new licensing questions, and it should be a separate authorised phase with its own budget/breaker treatment mirroring `CENSUS_GEOCODER_*`.

---

## N. Exact files that would require modification

**M1 — persist and render Layer C**
* `app/Services/ListingImport/QuickImport/MlsQuickImportDraftWriter.php` — accept and persist `$result->details`; add `META_PROPERTY_DETAILS`
* `app/Http/Livewire/OfferListing/QuickImport/MlsQuickImportComponent.php` — pass details through `acceptProperty()`
* `resources/views/offer-listing/seller/view.blade.php` — include the new partial
* `resources/views/offer-listing/landlord/view.blade.php` — include the new partial
* **NEW** `resources/views/offer-listing/partials/_mls_property_facts.blade.php`
* `app/Http/Controllers/SellerOfferListingController.php` / `LandlordOfferListingController.php` — flag re-check at render time
* *(B1 parity)* `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php` — write the same meta key on the wizard prefill path, so both import surfaces produce the same listing

**M2 — widen the fact allow-list**
* `app/Services/ListingImport/MlsListingPrefillService.php` (`ALLOWED_FIELDS`)
* `app/Services/Property/PropertyCandidate.php` (new typed properties)
* `app/Services/Bridge/BridgePropertyCandidateAdapter.php` (new named-key reads)
* `app/Services/ListingImport/MlsFieldMap.php` (only where a target genuinely does not exist yet)
* `app/Services/ListingImport/MlsNormalizer.php` (value normalisation for new enumerated fields)

**M3 — presenter coverage + key corrections**
* `app/Services/ListingImport/MlsPropertyDetailsPresenter.php` (`FIELDS`)

**M4 — consistency**
* `app/Services/Stellar/PropertyDetailViewMapper.php` and/or `MlsPropertyDetailsPresenter::EXCLUDED`
* `config/mls_media.php` (the `max_images` cap; the inaccurate MIME comment)
* `app/Services/ListingImport/Media/MlsMediaPolicy.php` (read `Permission`; drop or document the dead `PreferredPhotoYN` path)

**Dead-storage cleanup (optional, separate)**
* `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php` — `mls_import_snapshot` has no reader

---

## O. Migration / schema requirements

**None required.** Do not create any.

* Both `seller_agent_auctions` and `landlord_agent_auctions` already carry EAV meta tables, and the existing MLS provenance keys (`mls_provider`, `mls_listing_key`, `mls_imported_at`, …) are written the same way. `mls_property_details` is one more meta row.
* `bridge_properties.raw_json` already holds 100% of the payload — no new column is needed for anything in §M.
* Reminder from `CLAUDE.md`: `deploy/start-production.sh` is the **only** thing that runs migrations, and Laravel 8 has no `migrate --isolated`. If a future phase does need a column (e.g. promoting `LotSizeAcres` to native for search), it goes through that single owner and both CI migration gates.

---

## P. Test plan (to prove Bridge fields are never silently lost again)

1. **Payload-fidelity test** — assert `BridgePropertyNormalizer::upsert()` round-trips a fixture record so `json_decode(raw_json) == $record` exactly. Locks in "no `$select`, no loss".
2. **No-`$select` guard** — assert `BridgeApiService` request params contain only `$top`, `$skip`, `$filter`, `access_token`. A future `$select` becomes a deliberate, reviewed act.
3. **Allow-list snapshot tests** (extend the existing ones) — `MlsListingPrefillService::ALLOWED_FIELDS`, `MlsPropertyDetailsPresenter::FIELDS` and `::EXCLUDED` asserted by exact contents, so any addition is visible in the diff. These already exist for two of the three; keep them.
4. **★ Coverage-regression test (the one that is missing).** Load a multi-type Bridge fixture set, compute *every populated key*, subtract everything reached by native columns + `PropertyCandidate` + `ALLOWED_FIELDS` + presenter `FIELDS` + `EXCLUDED`, and assert the remainder equals a **checked-in expected list**. A new field appearing in the feed, or an existing one quietly falling out of a map, then fails the build with the exact field name. This is the test whose absence made the current gap invisible.
5. **Search/import parity test** — assert every RESO key `PropertyDetailViewMapper` renders is either in `ALLOWED_FIELDS`, in presenter `FIELDS`, or in a checked-in `PARITY_EXEMPT` list with a reason. Prevents the two surfaces drifting apart again.
6. **Layer-C persistence test** — import a fixture, publish, re-fetch the listing, assert the MLS Property Facts section renders the same sections the review screen showed, and that a flag flipped off after import suppresses it at render time.
7. **Media completeness test** — fixture with 100 media objects: assert count, ordering (dense, `Order`-derived), cover selection, cap behaviour is logged not silent, and that a `Permission: ["Private"]` object is rejected.
8. **Property-type matrix test** — one fixture per Stellar type (fixtures already exist under `tests/fixtures/mls/`), asserting the type-specific fields each one is expected to carry through.
9. **Excluded-never-published test** (extend existing) — assert no `EXCLUDED` key can appear in presenter output *or* in the new persisted meta blob *or* in the listing view's rendered HTML.

---

## Q. MLS / IDX display-rights concerns (listed separately, never used to skip discovery)

1. **The feed already carries per-listing display permissions that no code reads.** `InternetAddressDisplayYN` is **`false` on 71 of 1,202 records** — those listings' addresses must not be published. `InternetEntireListingDisplayYN` (true on all 1,202), `InternetAutomatedValuationDisplayYN` (false on 338), `InternetConsumerCommentYN` (false on 531) and `FeedTypes` (`["IDX"]` on all) are likewise unread. Only `IDXParticipationYN` is checked, and only by `StellarPropertyDetailController`. **The import path checks none of them** — an imported listing today can publish an address the feed says must not be displayed. This is the most urgent compliance finding in the audit and it is independent of everything else recommended here.
2. **Media licence is unresolved and is recorded as such.** `docs/mls-direct-import-design-and-plan.md` records a locked owner decision (2026-07-05) that MLS photos are excluded pending **written Stellar MLS confirmation**. `config/mls_media.php` implements that as two flags. That decision stands; nothing in §M changes it.
3. **…but `/stellar/property/{key}` publishes the same photos today with no licence flag.** Whatever the answer is, it is currently being given both ways by the same codebase.
4. **Brokerage attribution.** IDX rules commonly *require* listing-brokerage attribution on any displayed listing. `ListOfficeName` is excluded from the import display allow-list and rendered on the Stellar page. If imported listings are to display MLS facts at all, the attribution requirement needs a deliberate answer.
5. **Agent PII.** `ListAgentEmail` and `ListAgentPreferredPhone` are populated on 100% of records for 709 distinct agents. These are real personal contact details for people who are not our users. Holding them (as `raw_json` does) is a different act from displaying them; nothing here recommends displaying them.
6. **HOA contact data.** `AssociationName` (472, frequently a person's name — e.g. "Jerilyn Smith"), `AssociationPhone` (144) and `STELLAR_AssociationEmail` (20, e.g. `Karla Baumann <kscpoa@aol.com>`). The presenter's stated reason for excluding `AssociationName` is exactly right, and the Stellar detail page contradicts it.
7. **Authored prose.** `PublicRemarks` (1,202) and `STELLAR_PublicRemarksAgent` (1,202, near-identical content). Named in the locked decision alongside photo reuse. Currently published on the Stellar page, excluded from import. Same inconsistency as #3.
8. **Access and safety data.** `STELLAR_ShowingRequirements` (1,199), `STELLAR_ShowingConsiderations` (258, e.g. "Pet(s) on Premises"), `LockBoxType` (2), `STELLAR_TenantName`/`TenantPhone` (2 each), `STELLAR_EscrowAgent*` (~215 each with names, emails, phones), `STELLAR_PropertyManager(Phone)`. Correctly excluded. **`ShowingInstructions`, `PrivateRemarks` and `SyndicationRemarks` are not in the feed at all** — Bridge already strips them.
9. **Compensation.** `BuyerAgencyCompensation`, `SubAgencyCompensation`, `TransactionBrokerCompensation` are **absent from the payload entirely**, consistent with post-settlement removal. Nothing to decide.
10. **Third-party syndication signals.** `STELLAR_OfficeSyndicateTo` (1,202) and `STELLAR_ThirdPartyYN` (`true` on 1,100) describe where the listing broker has authorised syndication. Worth legal review as an input to what BidYourOffer may republish.

---

## Regression boundary — confirmed unchanged

Nothing in this audit modified: Location DNA · MapLibre · Census geography hierarchy · Buyer/Tenant geographic matching · MLS matching · Seller/Buyer/Landlord/Tenant Hire Agent · Create Offer workflows · authentication · billing · Fair Housing work · the coordinate ladder or its precedence · the Listing Link importer · Manual Create.

The only writes performed were **read-only SQL queries** against `bridge_properties` (no `UPDATE`, `INSERT` or `DELETE`), and the creation of this audit directory. No Bridge API request was issued (no credentials). No Google Places call was made. No secret was read, printed or transmitted.

### Data authoritativeness (§13)

| Value on an imported listing | Authority today |
|---|---|
| Address, city, state, zip, county | Bridge `UnparsedAddress` etc., user-editable afterwards |
| Beds / baths / sqft / year / price / lot | Bridge, user-editable |
| Construction, systems, appliances, interior | Bridge, user-editable |
| `property_lat` / `property_lng` | **Coordinate ladder**, via `MlsQuickImportComponent::enrichLocation()` → `PropertyCoordinatePersistenceService`. `BridgeMlsCoordinatesAdapter` matches on the persisted `mls_listing_key`. Provenance (`geocode_provider`, `geocode_precision`, `normalized_address`) is recorded. **Unchanged by this audit and out of scope for M1–M4.** |
| Flood zone, POIs, commute, schools (Matchmaker) | Location DNA pipeline (FEMA / Census TIGER / Google Places POI), not Bridge |
| Everything on the terms/leasing tabs | **User input only** — deliberately, per the `OccupantType` exclusion note in `MlsListingPrefillService` |
| Photos | Bridge `Media[]` by reference, when both media flags are on |
