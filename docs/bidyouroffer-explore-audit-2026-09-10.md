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
2. **Explore's inventory is whatever the existing lazy import has cached.**
   `bridge_properties` is filled by criteria-driven imports; Explore reads it and
   triggers no fetch of its own, because a second sync system is explicitly out
   of scope. A viewport over an area nobody has searched will be sparse.
3. **Eligibility requires decoding `raw_json` per row** — display permissions and
   lease frequency exist only there. Bounded by overfetch-then-filter with a hard
   read ceiling rather than by inventing a projection column.

---

## 6. Follow-ups

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
