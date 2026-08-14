# Full MLS Listing Import — Phase A Audit

> Branch: `feat/full-mls-listing-import` · Base: `origin/main` @ `65ac70376`
> Date: 2026-08-13 · Status: **audit only, no production code changed**

This is the end-to-end trace required before any architectural change. It answers the
twelve questions in the brief's §3, the media questions in §5, and records the
constraints any implementation must not break.

---

## 1. What exists today

### 1.1 The two import mechanisms are independent

| | URL / raw-text importer | Bridge MLS # lookup |
|---|---|---|
| Entry point | `HasMlsImport::importListingFromUrl()` | `HasMlsImport::importListingByMlsNumber()` |
| Service | `MlsListingImportService` (1185 LOC scraper/parser) | `BridgeListingLookupService` → `MlsListingPrefillService` |
| Data origin | text the **user** supplied | the **Stellar feed** via Bridge OData |
| Feature flag | none — always on | `mls_direct_import.prefill_enabled`, default **false** |
| Roles | all four | `seller`, `landlord` only |

They converge on one preview/apply pipeline: `buildImportPreview()` → checkbox table →
`applyImportedFields()`. That convergence is deliberate and is why the *field allow-list*,
not the pipeline, is where compliance is enforced.

### 1.2 Components in scope

- `App\Http\Livewire\OfferListing\Seller\SellerOfferListing` (create) / `…\SellerOfferListingEdit`
- `App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing` / `…\LandlordOfferListingEdit`
- Shared: `OfferListing\Concerns\HasMlsImport`, `Concerns\ResolvesOwnedAuction`,
  `Concerns\DeletesOwnedListingMedia`, `Concerns\ValidatesMediaUploads`
- View: `resources/views/livewire/offer-listing/shared/mls-import-modal.blade.php`

### 1.3 Bridge service layer

```
BridgeApiService            raw OData HTTP; no $select, so the full permitted projection returns
BridgeListingLookupService  local-first → API-on-miss → upsert → PropertyCandidate
BridgePropertyNormalizer    raw record → 30 native columns + raw_json
BridgePropertyCandidateAdapter  BridgeProperty row → PropertyCandidate DTO
MlsListingPrefillService    PropertyCandidate → 27 canonical keys  ← THE COMPLIANCE BOUNDARY
```

### 1.4 Storage today

| Layer | Where | Contents |
|---|---|---|
| Raw source | `bridge_properties.raw_json` | `json_encode($record)` — **the entire Bridge record, including `Media`** |
| Source metadata | `bridge_properties` | `listing_key`, `listing_id`, `modification_timestamp`, `imported_at`, `is_permanent` |
| Normalized | `bridge_properties` (30 cols) | identity, address, geo, price, beds/baths/sqft/lot/year, type/subtype/status, HOA fee, taxes, 9 boolean feature flags |
| Listing-side | `*_agent_auction_metas` EAV | `mls_import_snapshot` (canonical-key subset), `mls_address_raw`, `mls_listing_key` |

`SellerAgentAuction` and `LandlordAgentAuction` both expose `saveMeta()` / `info()` / `->get`
and store Offer-Listing data as EAV meta rows.

---

## 2. Answers to §3

**1. Does Bridge already return the full property record?**
Yes. `BridgeApiService::fetchProperties()` sends `$top`, `$filter` and `access_token` only —
no `$select` — so OData returns the complete projection our token is entitled to.
`docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` (25-record live sample, 2026-06-15) counts
**553 unique field keys**, 217 populated in ≥50 % of records.

**2. Which returned fields are currently discarded?**
None are discarded at the *cache* layer — `raw_json` keeps the whole record. Fields are
dropped at two later points:
- 523 of 553 keys have no native column on `bridge_properties` (queryable only via `raw_json`);
- **at the form boundary**, `MlsListingPrefillService::ALLOWED_FIELDS` admits exactly 27
  canonical keys and reads nothing else. `$candidate->raw` is never consulted.

**3. Is the raw Bridge/RESO response stored anywhere?**
Yes — `bridge_properties.raw_json`, indefinitely. `mls_import_snapshot` on the listing stores
only the canonical-key subset, not raw RESO.

**4. Which fields are normalized into our listing tables?**
The 27 `ALLOWED_FIELDS` keys, routed through `MlsFieldMap::forRole()` to Livewire properties
and persisted as listing meta. Two (`mls_number`, `mls_listing_key`) are record handles
suppressed from the preview table (`NON_PREVIEW_KEYS`) but still persisted.

**5. Which fields are necessary for BYO search / matching / business logic?**
The normalized set: address + city/state/zip/county, latitude/longitude, list price,
beds, baths, living area, lot size, year built, property type/subtype, MLS status, HOA
present + fee, annual taxes, and the boolean flags (pool, garage, waterfront, view,
water view, senior community, association, new construction, CDD). These feed the
`*BidMatchScoreHelper` classes, the search filters and the coordinate ladder.

**6. Which fields are only needed for display?**
Everything in `raw_json` that has no BYO structural consumer — Appliances, Flooring,
FireplaceFeatures, InteriorFeatures, Heating, Cooling, Roof, ConstructionMaterials,
ExteriorFeatures, LotFeatures, WaterfrontFeatures, PatioAndPorchFeatures,
AssociationAmenities, CommunityFeatures, Zoning/ZoningDescription, TaxBlock/TaxLot/
TaxMapNumber, ArchitecturalStyle, Utilities, Sewer, WaterSource, FoundationDetails,
Levels, StoriesTotal, PropertyCondition, ParkingFeatures, GarageSpaces. These are
already stored and currently never rendered anywhere.

**7. Does Bridge provide listing photos through the property response, Media resources,
or a related endpoint?**
**Inline, on the Property resource itself.** The field audit records `Media` as an
`array`, populated **25/25 records**, example value
`[{"MediaKey":"25b3e48ee7095eed12688b25c10ea606-m1","MediaCat…` (truncated in the report).
Companion fields also present 25/25: `PhotosCount`, `PhotosChangeTimestamp`,
`STELLAR_TotalPhotosCount`, `DocumentsCount`, `STELLAR_TotalDocumentsCount`, plus
`VirtualTourURLUnbranded` (18/25), `VirtualTourURLBranded` (4/25).
**No separate `/Media` endpoint call exists in this codebase.**

**8. Are MLS photo URLs currently retrieved but discarded?**
Retrieved and **persisted** (they are inside `raw_json`). Discarded only at the form
boundary — nothing reads them, nothing renders them.

**9. Are MLS photos currently intentionally excluded?**
**Yes — by a locked owner decision.** `docs/mls-direct-import-design-and-plan.md`,
"Locked decisions (owner: Abigail, 2026-07-05)", item 1:

> *Phase 3 = facts only. … **No photos**, no PublicRemarks, no agent/private remarks, no
> showing instructions, no agent/office contacts. Reason: avoids **Stellar MLS licensing
> risk (photo/remarks reuse, retention, rehosting)**. Users add photos/description
> manually. **Fuller media only after written Stellar MLS confirmation.***

**10. Are PublicRemarks or agent/broker information intentionally excluded?**
Yes, same decision, same mechanism. One nuance worth stating precisely: `MlsFieldMap`
*does* contain `'description' => 'additional_details'` for seller — but that key is only
ever emitted by the **URL/text parser** (text the user pasted themselves).
`MlsListingPrefillService` has no `description` entry, so **Bridge PublicRemarks has never
reached a listing form.**

**11. Licensing / IDX restrictions encoded in the implementation that must remain intact.**
1. `MlsListingPrefillService::ALLOWED_FIELDS` — an **allow-list, not a denylist**, chosen so
   a newly-added feed column fails *closed*. `build()` reads only named properties; there is
   no dynamic lookup and `$candidate->raw` is never touched.
2. `tests/Unit/ListingImport/MlsListingPrefillServiceTest.php` asserts the constant's
   **exact contents** (`assertSame` on the whole map) plus "an unknown future raw field
   cannot leak" and "output keys are a subset of the allow list". Adding a field is
   therefore a reviewed licensing decision, not a silent mapping tweak.
3. `PropertyCandidate::toArray()` excludes `$raw` unless explicitly asked.
4. `config/mls_direct_import.php` — master flag default **false**, role allow-list
   `['seller','landlord']`; `mlsNumberImportAvailable()` is re-checked server-side in the
   action, not only in the blade.
5. The **A/B boundary**: Match Check (Buyer/Tenant) may read restricted fields *internally
   to score* and must never republish them; prefill (Seller/Landlord) may not read them at
   all. Storage ≠ use ≠ public display.

**12. Are media URLs persistent, signed, temporary, or subject to refresh?**
**Unknown, and not determinable in this environment.** `BRIDGE_DATASET` and
`BRIDGE_SERVER_TOKEN` are absent from `.env` here, no `Media` fixture exists under
`tests/fixtures/mls/`, and the only in-repo sample is the truncated audit line above —
it shows `MediaKey` and the beginning of `MediaCategory` but no URL field, no expiry and
no `ModificationTimestamp`. This must be resolved against a live credential before any
media-hosting decision is finalised.

---

## 3. The existing BYO photo system

### 3.1 Shape

- `property_photos` meta — JSON array of **bare filenames**; **array order is gallery order**.
- `photo` meta — a separate single image, used as a cover on some surfaces.
- Files: public disk, `auction/images/`, UUID names, written by
  `ListingStorageWriter::storePublic()`. Cap 50 photos, mimes jpg/jpeg/png/webp, 50 MB each.
- URLs: `ListingMediaUrl::get('auction/images/'.$filename)` — **always resolves to our own
  disk**. There is no code path today that renders a listing photo from an external host.

### 3.2 A latent structured-entry seam already exists

All four public view blades (`offer-listing/{seller,landlord,buyer,tenant}/view.blade.php`)
already tolerate an entry that is an **array** rather than a string:

```php
$fn = is_array($ph) ? ($ph['filename'] ?? '') : $ph;
if (is_array($ph) && !empty($ph['is_cover'])) { … }
```

**Nothing writes that shape today.** It is a forward-compatible seam, and it is the natural
place to hang `source`, `provider`, `media_key` and `sequence`.

### 3.3 …but the security layer assumes strings — this is the key integration constraint

| Site | Assumption |
|---|---|
| `DeletesOwnedListingMedia::isDeletableStoredMediaFilename()` | returns **false** for any non-string |
| `DeletesOwnedListingMedia::deleteOwnedListingMediaFromCollection()` | `array_search($selected, $entries, true)` — strict |
| `SellerOfferListing::applyPhotoOrder()` | `in_array($fname, $authoritative, true)` — strict |
| `persistedPropertyPhotos()` | returns the decoded array verbatim |

Introducing structured entries naively would make delete refuse every MLS photo and make
reorder silently drop them. Any implementation must widen these deliberately, with tests,
or keep the persisted entries string-shaped and hold the metadata in a sibling meta key.

### 3.4 Ownership model (must be preserved exactly)

Two layers, both required, for a documented reason:

1. `hydrate()` on all eight Offer-Listing components calls
   `assertCanManageAuction($modelClass, $this->listingId, null)` → 403.
2. **Re-resolution at the action boundary** — `resolveOwnedMediaListing()` on
   Seller/Landlord re-queries `where('id', $listingId)->where('user_id', Auth::id())`
   and `abort_if(null, 403)`. The in-code rationale: *Livewire applies client `syncInput`
   updates **after** the hydration hooks run, so a `listingId` tampered between requests
   reaches an action having already passed `hydrate()`.*

Any new MLS-media action (import, refresh, set-cover, reorder) **must** re-resolve at the
action boundary, not rely on `hydrate()`.

**Pre-existing observation, outside this feature's scope:** `SellerOfferListing::store()`
resolves with an unscoped `SellerAgentAuctionModel::find($this->listingId)` and then assigns
`$auction->user_id = Auth::id()`. It is covered by `hydrate()` only, i.e. by the layer the
S5/S6 comments say is insufficient on its own. Recorded here; not changed by this branch.

---

## 4. Flow, publish and terminology

- **Creation**: `/offer-listing/{seller,landlord}` → tabbed Livewire wizard →
  `store()` → validates → `saveAllMetadata()` → `stampBiddingActivation()` →
  `resolvePropertyCoordinates()` + `ComputeLocationDna` → **redirect to the public view page**.
- **There is no pre-publish review screen today.** The public view page is reached *after*
  publication. §17 of the brief requires a review *before* publish; that surface does not exist.
- **Listing method**: `auction_type` meta, canonical values `'Traditional'` and
  `'Bidding Period'`. The Bidding Period option is hidden unless
  `BIDDING_PERIOD_ENABLED=true` (`config/bya_beta.php`, default **false**); when disabled
  `auction_type` is forced to `'Traditional'` server-side.
- **Financing**: `offered_financing`, a JSON array (multi-select). Canonical option list
  (source: `offer-seller-tabs/commission-based/seller-terms.blade.php`) —
  Assumable, Cash, Conventional, FHA, Jumbo, VA, No-Doc, Non-QM, USDA, Cryptocurrency,
  Exchange/Trade, Lease Option, Lease Purchase, Non-Fungible Token (NFT), Seller Financing,
  Other. **`Seller Financing` and `Exchange/Trade` are the canonical spellings** — not
  "Seller Finance"/"Trade".
- **Landlord** has full parity for the hardened photo methods
  (`resolveOwnedMediaListing`, `persistedPropertyPhotos`, `applyPhotoOrder`,
  `deletePropertyPhoto`, `reorderPhotos`, `movePhotoUp/Down`) and its own rental terms;
  it does **not** carry the seller financing questions.

---

## 5. Location DNA / geocoding interaction

- A Bridge import that carries a coordinate pair sets
  `$mlsBridgeCoordinatesAvailable = true`, which **suppresses** both geocoding entry points
  (`applyImportedFields()` and `mlsGeocodeSaveTimeFallback()`).
- `saveMlsListingKeyMeta()` writes `mls_listing_key`, which activates rung 2 of the
  coordinate ladder (`BridgeMlsCoordinatesAdapter`) — it matches on `listing_key` **only**,
  never by address similarity.
- Coordinates are atomic: `buildCoordinatePair()` emits both or neither, treats `0.0` as
  absent, and range-checks. A half-pair would both fail to locate the property *and*
  suppress the fallback.
- Existing `LocationDnaGeocodeService` use inside `HasMlsImport` is untouched by this branch.

---

## 6. Test baseline (this worktree, before any change)

```
php artisan test tests/Unit/ListingImport/ tests/Unit/Bridge/
Tests: 49 passed        Time: 0.99s
```

Existing coverage: `MlsListingPrefillServiceTest` (allow-list guard),
`BridgePropertyCandidateAdapterTest`, `BridgePropertyNormalizerUpsertTest`,
`BridgeListingLookupDispatchOptOutTest`, `MlsNumberPrefillTest`,
`tests/Feature/ListingImport/*` (11 files), `SellerMlsFieldRoundTripTest`,
`LandlordMlsFieldRoundTripTest`, `tests/Feature/Storage/*`.

**There is no existing test for MLS media** — because there is no MLS media code.

---

## 7. The blocker

The brief asks for MLS photo import. The repository contains a locked owner decision
(2026-07-05) stating that photos are excluded **pending written Stellar MLS confirmation**,
enforced structurally by an allow-list and a test that asserts its exact contents.

The brief itself also says (§5) *"Use only the method allowed by our applicable MLS/Bridge
permissions"* and (§20) *"Do NOT weaken any existing MLS/IDX/VOW/data-license restrictions."*

Three facts are therefore required before Layer C / media work can be finalised:

1. Whether written Stellar confirmation now exists, and what it permits.
2. Whether permitted use is **reference-only** (render from provider URLs) or includes
   **caching / rehosting** to BYO-controlled storage.
3. The actual `Media` object shape and URL lifetime — unverifiable here (no credentials,
   no fixture, sample truncated).

Until (1)–(3) are settled, the safe construction is: build the full pipeline, default the
hosting mode to **reference-only, never rehost**, and gate the whole thing behind a master
flag that is **off** by default and a separate explicit media-license acknowledgement — so
nothing in the existing compliance posture changes until the owner flips it.
