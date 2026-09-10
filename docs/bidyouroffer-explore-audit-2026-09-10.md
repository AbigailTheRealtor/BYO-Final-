# BidYourOffer Explore — audit and Phase 1 record

**Date:** 2026-09-10
**Branch:** `feat/bidyouroffer-explore-google-3d`
**Base:** `origin/main` @ `0b344160d`

Explore is a discovery surface over systems BidYourOffer already has. It
synchronises nothing, imports nothing, scores nothing and stores nothing. This
document records what the audit found, what Phase 1 built on it, and what would
have to be true before the VOW tier could exist.

---

## 1. VOW — MISSING

A repository-wide audit found **no VOW capability of any kind**:

| Item | Status |
|---|---|
| Stellar MLS VOW approval / agreement recorded in this repository | MISSING |
| VOW-capable Bridge/Stellar dataset | MISSING |
| VOW credentials or configuration | MISSING |
| Consumer VOW registration flow | MISSING |
| VOW terms acknowledgement | MISSING |
| VOW policy / field-filtering / logging classes | MISSING |
| Any VOW field in the feed payload | NOT PRESENT (551 distinct Property fields; none VOW-related) |

The only occurrences of "VOW" in the codebase are in `docs/`, every one of them
listing VOW distribution settings among the fields **not** to import.

**Delayed Distribution is also not represented in this feed.** No
Delayed-Distribution field exists in the payload — the nearest are `Exclusions`
and `STELLAR_OfficeSyndicateTo`, neither of which carries the concept. It cannot
be honoured, leaked or tested against data that is not there. The eligibility
model keeps `public_idx_eligible` and `vow_eligible` as separate concepts
(`ExploreAccessTier`) rather than one `is_mls_visible` boolean, so the
distinction remains expressible when the data arrives.

### What activation would require

Held in code, in `VowAvailability::activationRequirements()`, so the next person
to ask reads the answer beside the thing that refuses:

1. A signed Stellar MLS VOW agreement covering this application, recorded here.
2. A Bridge/Stellar dataset provisioned for VOW, with its own credentials. The
   existing `BRIDGE_DATASET` is an IDX dataset and must not be reused to imply
   VOW rights.
3. A written field-by-field determination of which VOW fields a registered
   consumer may see. **Bridge returning a field is not permission to display it.**
4. A consumer VOW registration flow — identity, a bona fide relationship, a
   recorded and revocable terms acknowledgement. Not `Auth::check()`.
5. A written determination on **historical media**. Prior-listing photographs,
   tours and video are covered by no permission in this repository and are
   assumed prohibited.
6. A retention and display-duration rule for historical records.
7. Access logging and abuse controls sufficient for the VOW rules.

Until all seven hold, `VowAvailability::isAvailable()` returns false and
`decideTier()` returns `PUBLIC_IDX` for every caller — **regardless of
`EXPLORE_VOW_ENABLED`**. The flag is not the gate.

---

## 2. What the Stellar/Bridge feed actually provides

Measured against the live cache (read-only `SELECT`, 2026-09-10) and the seven
per-type fixtures in `tests/fixtures/mls/bridge/`.

**Inventory — 1,225 cached records, both markets present:**

| PropertyType | StandardStatus | Rows | With coordinates |
|---|---|---:|---:|
| Residential Lease | Active | 502 | 502 |
| Commercial Sale | Active | 501 | 498 |
| Business Opportunity | Active | 199 | 192 |
| Residential | Active | 16 | 16 |
| Commercial Lease | Active | 3 | 3 |
| Vacant Land | Active | 1 | 1 |
| Income | Active | 1 | 1 |
| Residential | Closed / Pending | 2 | 2 |

**Display flags:** `IDXParticipationYN` true on 1,203 (null on 22);
`InternetEntireListingDisplayYN` identical; `InternetAddressDisplayYN` **false on
71** — listings published without their address.

**Media:** 1,203 rows carry a `Media` array; 980 an unbranded virtual tour; 32 a
branded one (branded is RESTRICTED and never offered). No video field exists —
video would arrive as a `Media` entry whose category is not in the licence
allow-list, so `has_video` is correctly false everywhere today.

**Lease frequency:** Monthly 386, Seasonal 78, Annually 19, Weekly 16, Daily 2.
`"/mo"` is wrong for roughly a quarter of the rental inventory.

**Property identity:** `ParcelNumber` on 1,090 rows, `UnitNumber` on 400. A
parcel is frequently the whole building — in the live cache a condominium and
the building's income listing share parcel `183116855380170208` exactly.

**Statuses (live probe, `MlsSourceStatus`):** Active, Pending, Closed, Active
Under Contract and Coming Soon are confirmed; Expired, Withdrawn, Canceled and
Temporarily Off Market returned nothing. Unconfirmed ≠ non-existent — an IDX feed
commonly withholds off-market records under licence.

---

## 3. Existing authorities Explore reuses (and does not reimplement)

| Concern | Reused authority |
|---|---|
| Public IDX display permission | `MlsDisplayPermissions::listingDisplayable()` |
| Address display permission | `MlsDisplayPermissions::addressDisplayable()` |
| Market status vocabulary | `MlsSourceStatus` / `MlsLinkedListingStatus` |
| Property-type vocabulary | `PropertyTypeVocabulary::forRole()` |
| Media licence, https rule, category allow-list | `MlsMediaPolicy` |
| Media extraction + per-object public-display flag | `MlsMediaExtractor` |
| Withheld-field classification (tested against) | `MlsFieldCatalog::RESTRICTED` / `INTERNAL` / `CONTACTS` |
| MLS→listing linkage | `MlsQuickImportDraftWriter::META_LISTING_KEY` |
| Offer-Listing product identity | `ScopesListingWorkflow` + `ListingWorkflowResolver` |
| Unit-preserving property identity | `PropertyAddress::propertyIdentityLine()` (pattern) |
| MLS-only detail page | `stellar.property.show` (auth, Active-only, creates nothing) |

`ListingVisibilityGate` (Match Check) reads only `IDXParticipationYN` and is
therefore **weaker** than `MlsDisplayPermissions`. Explore uses the stricter one.

---

## 4. Deliberate Phase 1 decisions

- **`Active` only.** Both OData filter builders already emit
  `StandardStatus eq 'Active'` unconditionally and the Stellar detail page
  refuses anything else. A wider set would make Explore the first surface
  publishing a market status nobody cleared it to publish. `Coming Soon` is
  excluded specifically: a pre-marketing status is exactly the category governed
  by distribution rules this repository has no record of.
- **Sale/rent is an exact-match allow-list**, not `PropertyTypeVocabulary`. That
  class matches substrings (correct for picking a form vocabulary) and would
  classify `Residential Lease` as a sale.
- **An unclassified PropertyType is excluded from both filters.** Its `ListPrice`
  cannot be labelled.
- **An over-large bbox is refused, never clamped.** A clamped box answers a
  question the consumer did not ask.
- **Ask AI is not wired in.** `ask-ai.listing-question` is authenticated and
  answers only about a listing the requester **owns**; it serves private consumer
  offer-listings, not public MLS data. Explore adds no AI path and offers no
  Ask AI control. Reuse would require an AI surface scoped to the public MLS
  projection — a separate piece of work.
- **Save / favourites does not exist anywhere in this application.** No control
  is rendered. See follow-ups.
- **Schedule Showing only where a real listing exists.** `ShowingController`
  requires auth and an `offer_auctions` row; an MLS-only property has no showing
  workflow and never offers one.
- **`match_score` is null.** `PropertyLocationDna` / `PropertyLocationPoi` are
  listing-scoped (`listing_type = 'seller_agent'`), not MLS-scoped. No reliable
  score exists for an MLS-only property and Phase 1 invents none.
- **Static asset, no npm dependency.** The renderer is a plain IIFE; the Google
  Maps JS API is a runtime script tag. Nothing to bundle, and `/explore` is not
  coupled to a build step.

---

## 5. Known limitations (reported, not worked around)

1. **Google Photorealistic 3D is unverified.** The only Google credential in this
   application is `GOOGLE_PLACES_API_KEY` — a **server** key for address
   validation and POI lookup, which must never be emitted into a page. Explore
   needs its own browser key (`EXPLORE_GOOGLE_MAPS_BROWSER_KEY`). Without it the
   page renders a stated unavailable panel and issues **zero** Google requests.
   The live visual pass is blocked on that credential.
2. **Explore's inventory is current only while `EXPLORE_DISCOVERY_ENABLED` is on.**
   With discovery off it falls back to whatever the criteria-driven imports have
   cached, and labels the response `discovery.status = "disabled"` so a thin
   answer is not read as a thin market. See §6.
3. **Eligibility requires decoding `raw_json` per row** — display permissions and
   lease frequency exist only there. Bounded by overfetch-then-filter with a hard
   read ceiling rather than by inventing a projection column.

---

## 6. Viewport discovery — reusing the one MLS ingestion architecture

The first cut of Explore read `bridge_properties` and nothing else, so a neighbourhood nobody
had previously searched looked empty. That is a statement about our cache presented as a
statement about the market, and it is corrected here.

### The live-sync audit (2026-09-10)

| Question | Answer |
|---|---|
| Live-sync implementation | `SyncMlsListings` · `app/Services/ListingImport/Sync/` · `config/mls_sync.php` · scheduled in `app/Console/Kernel.php` |
| Refreshes known/imported listings | **YES** |
| Discovers new Stellar inventory | **NO** — its candidate set is a query over `seller_agent_auctions` / `landlord_agent_auctions` with MLS meta, so a listing nobody imported can never be a candidate. It also does not refresh `bridge_properties` generally. |
| Geographic Stellar query available | **YES** — `BuyerCriteriaODataFilterBuilder::buildGeoClause()` + `PolygonBoundingBox::fromPayload()` already emit the lat/lng box |
| Sale / rent discovery | **YES / YES** — the builder is generic over `PropertyType` |
| Existing upsert pipeline reusable | **YES** — `LazyBridgeImportService::importForCriteria()` -> `BridgePropertyNormalizer::upsert()` |
| Stale-on-demand refresh available | **YES**, at two grains: the criteria fetch cache per viewport, and `BridgeListingLookupService::refreshByListingKey()` per record |
| Scheduled refresh available | **YES**, but scoped to MLS-linked BidYourOffer listings — freshness, never coverage |
| Sync flags | `MLS_SYNC_ENABLED`, `MLS_SYNC_SCHEDULE_ENABLED`, `MLS_SYNC_LAZY_REFRESH_ENABLED` — all absent from `.env`, all defaulting false: **implemented and wired, not activated** |

**Freshness and coverage are different problems.** A synchroniser keeping 500 previously
imported listings current does not make Explore complete when Stellar has inventory this
application has never encountered; and a large local cache is worth nothing if it is stale.
Explore needed both, and they are solved by different mechanisms — discovery for coverage, the
confirmation window for currency.

### What was built

```
Explore viewport
  -> ExploreInventoryService       translator: bbox -> BuyerCriteriaPayload
  -> LazyBridgeImportService       THE EXISTING IMPORTER — lock, fetch cache,
                                   pagination, normalizer, DNA dispatch
  -> bridge_properties
  -> ExploreEligibilityPolicy      existing MlsDisplayPermissions
  -> ExploreListingProjection      the allow-list DTO
  -> browser
```

The only change to shared code is additive: two role strings in
`LazyBridgeImportService::SUPPORTED_ROLES`, and optional pagination caps the importer clamps
**downwards** so a call site can lower a spend limit and never raise one.
`ExploreInventoryService` writes no OData — it builds the payload the existing buyer builder
already turns into `StandardStatus eq 'Active' and (PropertyType eq ...) and (bbox)`. A test
scans `app/Services/Explore/` and fails if any class there holds a provider client, reaches the
network, or writes an MLS row.

Both Explore roles use the **buyer** builder. Its name is historical — it knows nothing about
purchasing — so the rental PropertyTypes produce the rental filter. The tenant builder is not
used: its documented rental vocabulary includes `Residential`, which in this dataset is a sale
type. That defect belongs to the tenant-search work and is neither inherited nor fixed here.

### Three decisions worth knowing

**The tile grid is load-bearing.** The fetch cache is keyed on a payload hash. Unsnapped, a
viewport mints a new key on every pixel of pan and "reuse the existing cache" becomes a request
per camera nudge. The discovery box is snapped **outwards** to a 0.05 degree grid: neighbouring
viewports share one entry, and the box always contains the viewport it came from — a property at
the screen edge must never be rendered from an area discovery did not ask about.

**Presence is not currency.** After a pass that completely covered the viewport, every eligible
listing in it was just upserted, so a row the pass did not touch is one the provider no longer
returns. Those are withheld — via `ExploreFreshness`, which borrows `bridge.lazy_ttl_minutes`
(the value `mls_sync.freshness_minutes` already borrows) against the `imported_at` stamp every
upsert writes. **Nothing is deleted.** The rule is suppressed after a *partial* pass, because
absence then means "we stopped asking"; it is also suppressed when discovery is off or the
provider was unreachable, and the response says which via `discovery.complete` /
`discovery.degraded`.

**An outage is not an empty market.** A failed pass serves last-known rows and marks the
response degraded. Emptying the map would assert a neighbourhood has nothing for sale — a claim
about the world rather than about our connectivity; serving stale rows silently would be the
same lie inverted.

### To activate freshness in a deployed environment

| Variable | Effect | Ships |
|---|---|---|
| `EXPLORE_DISCOVERY_ENABLED` | viewport discovery + panel record refresh | `false` |
| `MLS_SYNC_ENABLED` | keeps MLS-**linked BidYourOffer listings** current (a separate concern; Explore does not depend on it) | `false` |
| `MLS_SYNC_SCHEDULE_ENABLED` | the unattended sweep + daily reconcile | `false` |
| `MLS_SYNC_LAZY_REFRESH_ENABLED` | owner stale-on-access refresh | `false` |

Bridge credentials (`BRIDGE_DATASET`, `BRIDGE_SERVER_TOKEN`) are already present and are
required. **Nothing was activated by this work.**

---

## 7. Follow-ups

- **Google browser credential + live visual verification** (blocks §51 entirely).
- **Saved properties.** No favourites system exists. Saving a property that has
  MLS history but no BidYourOffer listing needs a property-scoped identity —
  `ExplorePropertyIdentity` already produces one.
- **"Interested in this home"** for off-market properties. No generic
  property-interest model exists. Not built casually; reported per §46.
- **Ask AI over the public MLS projection**, if wanted — a new scope, not a reuse.
- **Location DNA overlays / match scoring** for Buyer→sale and Tenant→rent.
- **Public-record / parcel data** is a SEPARATE source from MLS/VOW (§17). No
  provider added, none proposed here.
- **Full MLS detail for anonymous visitors.** `stellar.property.show` is
  authenticated; Explore therefore offers no detail link to a guest.
