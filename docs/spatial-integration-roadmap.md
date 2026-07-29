# Florida Spatial Integration — Master Implementation Roadmap

**Project:** make the existing Florida Spatial Intelligence stack usable throughout the application.
**Explicit non-goal:** loading more datasets. The Florida corpus is already loaded and has passed Gate 2.
**Source of truth for findings:** [`spatial-ui-integration-audit-2026-07-25.md`](./spatial-ui-integration-audit-2026-07-25.md)
**Owner:** Abigail · **Started:** 2026-07-25

---

## Status at a glance

| Phase | Title | Status | Blocked by | Milestone on completion |
|---|---|---|---|---|
| **0** | Address Validation & User Experience | ✅ **Complete** | — | `Florida Beta – Spatial Phase 0 Complete` *(tag not yet cut)* |
| **1** | Shared Components | ✅ **Complete** | — | `Florida Beta – Spatial Phase 1 Complete` *(tag not yet cut)* |
| **2** | Spatial UI Repair | ⏳ Not started | CORS origins · no map library installed | `Florida Beta – Spatial Phase 2 Complete` |
| **3** | Spatial Database Connection | ⏳ Not started | Phase 2 | `Florida Beta – Spatial Phase 3 Complete` |
| **4A** | Text-Based Spatial Matching | ⏳ Not started | Phase 3 | `Florida Beta – Spatial Phase 4A Complete` |
| **4B** | Geometry-Based Spatial Matching | ⏳ Not started | Phase 4A · **Decision D1** (geocoder) | `Florida Beta – Spatial Phase 4B Complete` |
| **5** | Admin Spatial Debug Console | ⏳ Not started | Phase 4B | `Florida Beta – Spatial Phase 5 Complete` |

### Milestone convention

Every phase ends with a **named, annotated Git tag** and explicit acceptance criteria that were demonstrated before the tag was created. A phase is not "done" because its code merged — it is done when its acceptance criteria have been shown to hold and the milestone tag exists. Tags are the checkpoints this project is tracked by.

> ⚠️ **Tag debt, recorded honestly (2026-07-26).** Neither the Phase 0 nor the Phase 1 tag exists yet — `git tag -l` carries no `Florida Beta – …` tag at all. Phases 0 and 1 are marked ✅ here on the strength of their demonstrated acceptance criteria, so by the letter of the convention above they are not yet closed. The Phase 1 tag is to be cut against this documentation commit once it has been reviewed; the Phase 0 tag waits until its intended target commit is explicitly confirmed by the owner (owner decision, 2026-07-26). Recorded rather than quietly satisfied, because a convention that gets waived silently the first time it is inconvenient is not a convention.

---

## Open decisions — these gate the phases above

Both were raised in the audit (§7). **D2 was resolved on 2026-07-28** and its infrastructure is built and verified; **D1 remains unanswered**. Neither blocked Phase 0 or Phase 1 — both phases shipped ahead of them by design.

### D1 · Approved no-Google geocoding source

The `addresses` table in the spatial database holds **0 rows** and no address importer exists. Options, cost, and coverage are laid out in audit §7.1. Recommendation stands: **US Census Geocoder now** (free, public domain, no key, days of work), **OpenAddresses/NAD for Florida next** (rooftop accuracy, same seam, no UI change).

> ⚠️ **This decision reaches further than it looks.** `GeoEnvelopeNarrower` evaluates polygons and circles by testing a **candidate listing's coordinates** against the drawn geometry. No geocoder means no listing coordinates, which means polygon, circle and radius matching cannot work at all — only city, ZIP, county and state matching can, because those compare declared text fields.
>
> **This is why Phase 4 is split into 4A and 4B** (owner decision, 2026-07-25). Phase 4A ships text-based matching on Phase 3 alone. Phase 4B does not begin until D1 is implemented. The split exists so that "matching is connected" is never claimed on the strength of the half that does not need coordinates.

### D2 · Basemap tile source — ✅ **RESOLVED 2026-07-28**

**Decision: self-hosted Protomaps `.pmtiles` for Florida, served from Cloudflare R2.** No vendor, no API key, no usage policy to breach; aligns with SIA-D25. The hosted-vendor fallback named in audit §7.2 was not taken.

The infrastructure for this decision is **built and verified** — see [Basemap infrastructure](#basemap-infrastructure--delivered-2026-07-28) under Phase 2 for the full integrity record.

| Field | Value |
|---|---|
| Architecture | Self-hosted Protomaps PMTiles on Cloudflare R2 |
| Object key | `basemaps/florida/20260726/florida-z15.pmtiles` |
| Bucket | `byo-basemap` (dedicated; separate from listing-media) |
| Public delivery | `r2.dev` managed URL — **development and verification only** |
| Custom domain | Not configured; deferred (owner decision, 2026-07-28) |

> 🚫 **The "no temporary renderer" hold still stands.** D2 being resolved unblocks the *tile source* question only. Phase 2 does not begin until the renderer work is authorised separately, and the licence-ordering constraint is unchanged: Google Maps Content may not be displayed over a non-Google basemap, so Google **data** must leave the address path (D1) before or alongside the renderer swap.

**Phase 2 is no longer blocked by D2.** It is now blocked by two other things, neither of them a decision about tiles:

1. **CORS is not configured** on the basemap bucket — no browser can fetch the archive cross-origin today. Pending final production origin approval; see Phase 2 dependencies.
2. **No map library is installed** — `package.json` still contains no `maplibre-gl` and no PMTiles client.

---

## Phase 0 — Address Validation & User Experience ✅

**Status:** Complete · 2026-07-25

### Objective

Make it impossible to store an invalid property location, and restore the City/County/State autofill that died with the Google credential — **without depending on either open decision**. This phase deliberately ships value before the architecture questions are answered.

### Files affected

*New:*

| File | Role |
|---|---|
| `app/Services/Location/AddressShapeValidator.php` | Pure, DB-free classifier: is this string shaped like a street address? |
| `app/Services/Location/ZipCodeLookupService.php` | ZIP → city / county / state / centroid from the owned `us_zip_codes` gazetteer |
| `app/Rules/ValidStreetAddress.php` | Validation rule; `::rules()` is the single canonical rule definition |
| `app/Http/Livewire/Concerns/ValidatesPropertyAddress.php` | Shared trait: rules + ZIP autofill + ZIP-in-street-field recovery |
| `resources/views/components/address-assist-notice.blade.php` | Inline "we moved your ZIP" explanation |

*Modified — the eight Seller/Landlord components:*

`OfferListing/Seller/SellerOfferListing.php` · `SellerOfferListingEdit.php` · `OfferListing/Landlord/LandlordOfferListing.php` · `LandlordOfferListingEdit.php` · `HireSellerAgent/SellerAgentAuction.php` · `SellerAgentAuctionEdit.php` · `HireLandLordAgent/LandLordAgentAuction.php` · `LandLordAgentAuctionEdit.php`

*Modified — publish rules and views:*

`OfferListing/Concerns/SellerPublishValidation.php` · `LandlordPublishValidation.php` · `components/byo-address-autocomplete.blade.php` · `components/google-maps-auth-telemetry.blade.php` · `offer-seller-tabs/…/property-preferences.blade.php` · `offer-landlord-tabs/…/property-preferences.blade.php` · `partials/location-dna/map-input.blade.php`

*Tests:* 48 new — 23 unit (`tests/Unit/Services/Location/`) + 25 feature (`tests/Feature/Location/`) · 4 existing fixture files updated

> Verified count as of the Phase 0 commit sequence. An earlier draft of this document estimated 56; 48 is the number the suite actually reports.

### Dependencies

None. Shipped ahead of both open decisions by design.

### Success criteria — all met

- [x] `43434` rejected on all four Seller/Landlord flows, create **and** edit
- [x] `33708` (a real ZIP) recognised as a ZIP, moved to the ZIP field, location autofilled, explained inline
- [x] `43434` (not a US ZIP) and `33708` produce **different** messages — the gazetteer earns its keep
- [x] Incomplete addresses (`Main`, `Main Street`, `...`) rejected with actionable messages
- [x] ZIP → City / County / State autofill working with **zero external calls**
- [x] Autofill never overwrites a value the user already corrected
- [x] **No coordinates written** — a ZIP centroid is not a property location, and writing one would poison the Phase 4 geo narrower
- [x] Address privacy rules untouched — street line still agent-only-after-hire
- [x] Search-area map degrades honestly instead of sitting on "Loading map…" forever
- [x] Zero regressions — verified by diffing failures against a clean worktree at the same HEAD

### Milestone

`Florida Beta – Spatial Phase 0 Complete`

### Commit references

| # | Hash | Message |
|---|---|---|
| 1 | `5c24dde72` | `feat(location): add street-address shape validation and ZIP gazetteer lookup` |
| 2 | `053b3876e` | `feat(location): enforce street-address validation across all Seller/Landlord flows` |
| 3 | `caa51fb2a` | `feat(location): surface address errors and degrade the search-area map honestly` |
| 4 | `f460dddd5` | `docs(spatial): close out Phase 1 and register the spatial documentation set` |

> **Two notes on row 4.** First, it cannot carry its own hash: a commit's hash is computed from its content, so writing the hash inside the content it names is impossible. It is filled in by a follow-up amend or a subsequent commit, and until then this row is deliberately not a hash.
>
> Second, its message has changed from what an earlier draft of this table predicted (`add integration audit, roadmap, and test-suite debt register`). That draft assumed the three spatial documents had been committed during Phase 0. **They had not** — they existed only as untracked files in the main workspace and appear in no commit on any branch. This one commit therefore does two jobs at once: it places all three documents under version control for the first time *and* records the Phase 1 closeout. That is why Phase 0's fourth commit reference and Phase 1's fourth commit reference are the same commit.

### Known limitation carried forward

Phase 0 validates address **shape**, not **existence**. It cannot tell you whether `100 2nd Ave N` is a real address — that is geocoding, and it arrives with **D1**.

---

## Phase 1 — Shared Components ✅

**Status:** ✅ Complete · 2026-07-26 — *with two documented, approved deviations; see Success Criteria #1 and #6.*

### Objective

Consolidate the duplicated Seller/Landlord address logic onto the shared component that already exists. Eliminate the fork, do not maintain two implementations.

### Files affected

*Shared seam — the consolidation target:*

| File | Change |
|---|---|
| `app/Http/Livewire/Concerns/HandlesGooglePlacesAddress.php` | Now the **only** fill implementation; method renamed `fillFromGooglePlaces()` → `fillFromResolvedAddress()` |
| `resources/views/components/byo-address-autocomplete.blade.php` | Gained the `render-markup` prop (default `true`) and two payload-guard corrections |

*Duplicate fill method removed from the four listing components:*

`OfferListing/Seller/SellerOfferListing.php` · `SellerOfferListingEdit.php` · `OfferListing/Landlord/LandlordOfferListing.php` · `LandlordOfferListingEdit.php`

*Duplicated listener JS removed from the four listing blades:*

`seller/offer-seller-listing.blade.php` (−65) · `seller/offer-seller-listing-edit.blade.php` (−55) · `landlord/offer-landlord-listing.blade.php` (−65) · `landlord/offer-landlord-listing-edit.blade.php` (−55)

*Gated component invocation added to the two shared property-preference partials:*

`offer-seller-tabs/commission-based/property-preferences.blade.php` · `offer-landlord-tabs/commission-based/property-preferences.blade.php`

*Rename touch-through (naming only):* `Concerns/ValidatesPropertyAddress.php` · `Rules/ValidStreetAddress.php`

*Tests:* 631 new lines across 2 files — `tests/Feature/Location/PropertyAddressFillParityTest.php` (186) and `SharedAddressAutocompleteAdoptionTest.php` (445) · 3 existing files updated

### Dependencies

Phase 0 (the shared trait and canonical rule are the seam this consolidates onto). Neither open decision was required, and neither was touched.

### Correction — the predicted shape was wrong

This section previously predicted that four Seller/Landlord **listing blades** each held an inline street-address implementation, and that Phase 1 would move that markup into the shared component. **The codebase was not shaped that way**, and the difference changed the design:

| Predicted | Actual |
|---|---|
| Four blades hold inline street-address markup + JS | The blades held **only the JS**. The street and unit `<input>`s live in **two property-preference partials** |
| Those blades are the only consumers | Each partial is included by **six listing blades spanning all four roles** — Seller, Buyer, Landlord and Tenant |
| Adoption = move the markup into the component | Moving the markup would have **changed the Buyer and Tenant pages**, which are outside Phase 1 scope |

Four consequences follow, and all four are load-bearing:

**1 · `renderMarkup` — adoption is script-only.** The shared component gained a `render-markup` prop defaulting to `true`, so every pre-existing consumer is untouched. The two partials invoke it with `render-markup="false"`, which emits the Places listener and the Maps loader while leaving their own inputs exactly where they were. DOM ids, `wire:model` bindings, required/optional state, validation display, the address-assist notice, layout and styling are byte-identical to before.

**2 · Seller/Landlord adoption is real but bounded.** All four listing flows now route through the one shared listener, and the offer-listing tree is separately asserted to contain **zero** listeners of its own. "One listener" is true of the offer-listing flows, **not of the codebase** — roughly 40 Blade files elsewhere (legacy criteria pages, standalone hire/bid forms, the Location DNA map partial) still build their own Autocomplete. Phase 1 did not touch them.

**3 · Buyer/Tenant gating was mandatory, not defensive.** `BuyerOfferListing`, `BuyerOfferListingEdit`, `TenantOfferListing` and `TenantOfferListingEdit` render these same partials but implement **no** fill method — not their own, not via the trait. An ungated adoption would have emitted a listener calling a nonexistent Livewire method on their pages. The invocation is therefore gated on an explicit **two-class allowlist** *and* on `method_exists()`, the latter making an unimplemented call structurally impossible rather than merely unlikely. A test parses the allowlist out of each partial, rebuilds the predicate, and evaluates it against real instances of all eight candidate hosts.

**4 · The provider-neutral rename stops one level short, deliberately.** `fillFromResolvedAddress()` names what the method receives rather than who resolved it, so the D1 geocoder swap changes only the caller. The **trait is still called `HandlesGooglePlacesAddress`** — renaming a trait used by ten components was outside that commit's approved scope. The Google-specific name survives one level up as a known, deliberate leftover rather than an oversight.

### Two approved payload corrections

The shared listener guards differently from the four copies it replaced. Both differences are deliberate fixes, approved by the owner 2026-07-26 — so Phase 1 is **not** strictly zero-behaviour-change, and the criteria below say so:

1. **`address_components` present, `geometry` absent.** The copies opened with `if (!place || !place.geometry …) return;` and dropped the selection entirely — the user's click did nothing. The component fills the textual fields and leaves lat/lng blank.
2. **`geometry` present, `address_components` absent.** The copies passed their guard, found no components, left every part as `''` and called the fill method anyway — **silently wiping** the street, city, county, state and ZIP already entered. The component returns before calling anything.

The project has no JS test runner, so both are asserted structurally over the shared script rather than as executed-handler tests. That is meaningful only because the offer-listing tree is separately asserted to carry no listener of its own.

### Success criteria

The criteria below are reproduced **exactly as approved before implementation**. Two were not met as literally written; those carry `[~]` rather than `[x]`, with the deviation stated beneath. The wording has deliberately not been adjusted to fit what was delivered.

- [~] All four flows render `<x-byo-address-autocomplete>`; no inline street-address markup remains
  - **Delivered with a deviation.** The inline *listeners* were eliminated and the shared-component delegation is complete — all four flows delegate, and the offer-listing tree is asserted to contain zero listeners of its own. But inline street-address **markup does remain**, in the two shared property-preference partials. It was left there by design: those partials are included by six listing blades across all four roles, so moving the inputs into the component would have changed Buyer and Tenant behaviour, which is outside Phase 1 scope. The second clause of this criterion is therefore not satisfied as written.
- [x] Exactly **one** `fillFromGooglePlaces()` implementation (currently five)
  - Met. The single implementation is `fillFromResolvedAddress()` in `HandlesGooglePlacesAddress`, renamed under criterion #4 below.
- [x] Roughly 240 lines of duplicated autocomplete JS removed
  - Met. 240 lines exactly (65 + 55 + 65 + 55).
- [x] Fill method renamed provider-neutrally so the D1 swap is a one-file change
- [x] Summary delivered: files removed · files consolidated · duplicate lines eliminated
  - See the consolidation summary below. Note that **files removed = 0**: this was a de-duplication, not a deletion.
- [~] Zero behaviour change — asserted by an existing-vs-new parity test
  - **Delivered with a deviation.** The parity test (`PropertyAddressFillParityTest`) holds, but behaviour did change in two cases: the two payload corrections described above, each approved by the owner on 2026-07-26 before implementation. Both are asserted individually. Because those changes are real and intentional, the literal "Zero behaviour change" bar was **not fully met** — it was knowingly traded for two bug fixes.

### Consolidation summary

| Measure | Result |
|---|---|
| Files changed | 18 (+790 / −344) |
| Duplicated autocomplete JS removed | **240 lines**, across 4 listing blades |
| Duplicate `fillFromGooglePlaces()` bodies removed | **4** (88 lines), leaving 22 lines of delegation |
| Fill implementations | **5 → 1** |
| Files consolidated onto the shared component | 4 listing flows, via 2 shared partials |
| Files deleted | **0** — this was a de-duplication, not a removal |
| New test lines | **631** across 2 new files |
| Production files whose behaviour changed | 1 (`byo-address-autocomplete.blade.php`, the two approved payload guards) |

**Verification at `495f36da8`:** `tests/Unit/Services/Location` 23 passed · `tests/Feature/Location` 64 passed · `tests/Feature/Offers` 660 passed / 55 failed, with the failure set diffed **byte-identical** to the pre-change baseline at the same HEAD. The 55 failures are pre-existing and catalogued in [`technical-debt-test-suite.md`](./technical-debt-test-suite.md).

### Milestone

`Florida Beta – Spatial Phase 1 Complete`

**Not yet cut.** To be created as an annotated tag against the final Phase 1 documentation commit; see the tag itself for its exact target (owner decision, 2026-07-26). See the tag-debt note under *Milestone convention*.

### Commit references

| # | Hash | Message |
|---|---|---|
| 1 | `1ef63072a` | `refactor(location): consolidate Seller/Landlord Google Places fill onto the shared trait` |
| 2 | `1de72215c` | `refactor(location): adopt shared address autocomplete component across Seller/Landlord listing flows` |
| 3 | `495f36da8` | `refactor(location): rename shared address fill interface to provider-neutral terminology` |
| 4 | `f460dddd5` | `docs(spatial): close out Phase 1 and register the spatial documentation set` |

### Known limitations carried forward

- The trait name `HandlesGooglePlacesAddress` still names a provider. The method no longer does. Renaming the trait is a future cleanup, not a D1 blocker.
- Roughly 40 Blade files outside the offer-listing tree still construct their own Google Autocomplete. Phase 1 consolidated the offer-listing flows only.
- Server-side Google Autocomplete proxies remain in 12 Livewire components. Untouched by Phase 1; they leave with **D1**.

---

## Phase 2 — Spatial UI Repair ⏳

### Objective

Replace the dead Google renderer in the Buyer/Tenant Search Areas component with the approved MapLibre stack, restoring map display, polygons, circles, radius search, and shape save/edit/delete/restore.

### Files affected

- `resources/views/partials/location-dna/map-input.blade.php` — **1,586 lines; the single highest-leverage file in the project.** It is included by all four Buyer/Tenant flows plus four legacy criteria pages, so one renderer rewrite fixes **twelve pages**.
- `resources/views/components/location-dna-map.blade.php` — 810 lines; the read-only map on six public view pages
- `package.json` / `webpack.mix.js` — add `maplibre-gl` (note: this project builds with **Laravel Mix**, not Vite, despite `vite.config.js` existing)
- `app/Http/Livewire/Concerns/HasSearchAreas.php` — unchanged if the geometry contract holds; that is the test

### Dependencies

- ~~**D2 — basemap tile source.**~~ ✅ **Resolved 2026-07-28.** Self-hosted Protomaps PMTiles on Cloudflare R2; archive uploaded and integrity-verified. See below.
- 🟡 **CORS configuration on the basemap bucket — previously a hard blocker; now verified for the development proof origin.** For that origin the preflight and ranged `206` reads both return the expected `Access-Control-Allow-*` headers, with no wildcard in use. **Production and any additional origins remain unconfigured** and each needs its own explicit entry; the policy template is recorded in [`basemap-r2-deployment-2026-07-28.md`](./spatial/basemap-r2-deployment-2026-07-28.md#5-cors-configuration-to-apply). Adding origins needs an admin-scoped R2 credential — the current object-scoped token cannot read or write bucket CORS.
- 🟢 **Map library installed and exercised.** `maplibre-gl` and a PMTiles client are pinned in `package-lock.json` and built through Laravel Mix. MapLibre's worker assets are published beside the proof bundle and committed, because the bundler cannot resolve the worker URL itself. An internal, flag-gated proof route renders the Florida basemap; it is a diagnostic only — not wired into listing, search, matching, database or any Seller/Buyer/Landlord/Tenant flow.
- Licence ordering constraint: Google Maps Content may not be displayed over a non-Google basemap. Google **data** must leave the address path (D1) before or alongside the renderer swap.

### Basemap infrastructure — delivered 2026-07-28

The tile source D2 selected is **built, uploaded and verified**. This is infrastructure only: no renderer code, no map library, no Blade changes, no branch.

| Field | Value |
|---|---|
| Object key | `basemaps/florida/20260726/florida-z15.pmtiles` |
| Size | `1,119,503,390` bytes (1.07 GiB) |
| BLAKE3 | `96864f80abbe43f97cbc833a6a022855b4933d2dcbeefc07397449e14dff299d` |
| SHA-256 | `856d18124d12d8f6753e8e607226e59aac7e1c502e967a99ec3371b9459138af` |
| ETag | `"2ca93f5944a7f0354e4722e222232b7e-17"` (multipart, 17 × 64 MiB) |
| Coverage | Florida bbox, zoom 0–15, PMTiles spec 3, vector (`mvt`) |
| Upstream | Protomaps build `20260726` · OSM data `2026-07-26T04:00:00Z` |

Integrity was confirmed end to end: the local archive was re-hashed against its provenance record, and byte-for-byte parity was then re-established **twice independently** after upload — once via authenticated `GetObject`, once via a full credential-free download over the public URL. Ranged reads (HTTP 206), `Accept-Ranges: bytes`, ETag consistency and HEAD all verified. The R2 token was confirmed scoped to the basemap bucket alone.

Full verification record, R2 configuration, and the CORS policy — verified for the development proof origin, awaiting production origins: [`docs/spatial/basemap-r2-deployment-2026-07-28.md`](./spatial/basemap-r2-deployment-2026-07-28.md).

**Known operational caveat:** the `r2.dev` public URL returns `403 / error code: 1010` to clients without a browser-like user-agent. Browser rendering is unaffected; **server-side consumers, CI smoke tests and uptime probes are**. This is independent of CORS and will not be fixed by applying the CORS policy.

### Success criteria

- [ ] Map renders
- [ ] City / ZIP / county selection with boundary display
- [ ] State selection
- [ ] Custom polygon drawing · circle drawing · radius search from a resolved address
- [ ] Edit and delete saved shapes
- [ ] Saved shapes restore correctly when editing an existing listing
- [ ] **Geometry round-trips byte-identically** through the new renderer — the guard that proves no silent data loss
- [ ] Feature-flagged and reversible
- [ ] Every feature demonstrated before Phase 3 begins

### Commit references

_To be filled in._

---

## Phase 3 — Spatial Database Connection ⏳

### Objective

Give the application its **first read path** into the live Florida PostGIS database. Today the only code that opens that connection is a read-only measurement command — the corpus cannot influence anything a user sees.

### Files affected

- New read service over the `pgsql_spatial` connection (boundary lookup + nearest-place queries)
- `config/database.php` — connection already configured, `SPATIAL_DATABASE_URL` already set
- `resources/views/partials/location-dna/map-input.blade.php` — boundary fetch moves from browser-direct Nominatim/TIGERweb to our own endpoint
- Retires the browser-direct `nominatim.openstreetmap.org` calls (already flagged for removal; they violate the OSM usage policy at application volume)

### Dependencies

Phase 2 (a boundary the map cannot draw is not observable). No new datasets — this reads what is already loaded.

### What is actually available to read

| Table | Rows | Note |
|---|---:|---|
| `places` | 29,434 | Florida Overture, confidence ≥ 0.90 |
| `place_categories` | 7 | grocery · gym · pharmacy · restaurant · coffee · fuel · shopping centre |
| `boundaries` | 67 | **County only.** City (`place`) and ZIP (`zcta`) layers are *not* loaded — their importers exist and are tested, the data simply has not been imported. |

### Success criteria

- [ ] Application reads county boundaries from PostGIS
- [ ] Application reads Overture places and place categories
- [ ] City and ZIP boundary layers imported for Florida using the **existing** importers
- [ ] Browser-direct Nominatim and TIGERweb calls removed
- [ ] Pinellas County, St. Petersburg and 33708 return correct geometry from our own database

### Commit references

_To be filled in._

---

## Phase 4A — Text-Based Spatial Matching ⏳ (first feature-complete milestone)

### Objective

Make saved location preferences actually influence ranking, for every preference that can be evaluated **without listing coordinates**. Today they influence nothing: a user can save a search area, reopen the listing and see it restored perfectly — and it changes no match result.

### Files affected

- `app/Services/Dna/Relevance/Narrowers/GeoEnvelopeNarrower.php` — its city / ZIP / county set-matching path
- `app/Services/Dna/Relevance/CandidateNarrowingPipeline.php` — gates the narrower behind `hard_filters`
- `config/matching.php` — `MATCHING_V2_ENABLED`, `MATCHING_V2_HARD_FILTERS_ENABLED` (both `false` today)
- `config/match_scoring.php` — `service_area` dimension, weight 15, `enabled => false`
- `app/Http/Livewire/Concerns/HasSearchAreas.php` — the discrete city/county mirrors the scorer reads

### Dependencies

Phase 3. **No dependency on D1** — every criterion below compares declared text fields.

### Success criteria

- [ ] County matching measurably affects rankings
- [ ] ZIP matching measurably affects rankings
- [ ] State filter measurably affects rankings
- [ ] City matching measurably affects rankings (where applicable)
- [ ] **Before/after ranking change demonstrated** — the same candidate set, ranked twice, with only the saved search area changed between runs
- [ ] Automated tests covering each of the four criteria
- [ ] Reverse direction (`ListingToDemands`) decided and implemented, or explicitly deferred with a stated reason
- [ ] If `service_area` is enabled, **all enabled weights still sum to 100**

### Explicitly out of scope

Polygons, circles and radius searches. They are Phase 4B and cannot be evaluated without coordinates. **Phase 4A completion must not be described as "matching is connected"** — only as text-based matching being connected.

### Milestone

`Florida Beta – Spatial Phase 4A Complete`

### Commit references

_To be filled in._

---

## Phase 4B — Geometry-Based Spatial Matching ⏳

> **Begins only after Decision D1 (geocoder) is implemented.** Not merely decided — implemented, with listings carrying real coordinates.

### Objective

Make drawn geometry influence ranking: a polygon, a circle or a radius the user saved must change which listings surface and in what order.

### Files affected

- `app/Services/Dna/Relevance/Narrowers/GeoEnvelopeNarrower.php` — its Haversine radius and point-in-polygon paths
- The candidate profile resolver that supplies `lat` / `lng` per candidate listing
- `listing_locations` in the spatial database — written from Phase 2 of the audit's sequence (the D1 work); read here
- Important Places consumption — **net-new**; `important_places_json` has no matching consumer at all today

### Dependencies

- Phase 4A
- **D1 implemented.** Without listing coordinates `GeoEnvelopeNarrower` fails open on every candidate, so geometry silently changes nothing — which would look like success and be the exact failure the audit warned about.

### Success criteria

- [ ] A saved **polygon** changes ranking
- [ ] A saved **circle** changes ranking
- [ ] A saved **radius** changes ranking
- [ ] Listing coordinates are consumed by the narrower (not merely stored)
- [ ] `GeoEnvelopeNarrower` reachable in the live path and demonstrably exercised
- [ ] **Demonstrated with real Florida data** — Pinellas County addresses, real saved geometry, real ranking output
- [ ] Automated tests for each geometry type
- [ ] Important Places consumption designed and implemented, or explicitly deferred with a stated reason

### Milestone

`Florida Beta – Spatial Phase 4B Complete`

### Commit references

_To be filled in._

---

## Phase 5 — Admin Spatial Debug Console ⏳

### Objective

An internal, admin-only page that makes the whole spatial pipeline inspectable — and makes "why did this rank there?" answerable without a database session.

### Files affected

- New admin controller + view under the existing admin area
- `routes/web.php` — admin-gated route
- Reuses `ScoreBreakdownService` and `AgentMatchExplanationBuilder` rather than recomputing

### Dependencies

Phase 4B (there is nothing meaningful to explain until matching consumes geometry).

### Success criteria

**Listing panel:** coordinates · county · city · state · ZIP · search geometry · nearby places · Location DNA inputs
**Buyer/Tenant panel:** saved search areas · saved Important Places · radius searches · polygon data
**Matching panel:** score breakdown · spatial score · matching explanation · nearby-place calculations — *showing exactly why a listing ranked where it did*

- [ ] All three panels render real data
- [ ] **Admin-only and not publicly accessible** — asserted by an authorization test, not by assumption
- [ ] No write operations anywhere on the page

### Commit references

_To be filled in._

---

## Florida Beta — definition of done

The Florida Beta is complete when all of the following hold:

- [ ] Seller location works
- [ ] Landlord location works
- [ ] Buyer Search Areas work
- [ ] Tenant Search Areas work
- [ ] Maps render correctly
- [ ] Search areas save and restore
- [ ] Spatial database is queried successfully
- [ ] Matching uses spatial preferences
- [ ] Florida users can complete all workflows without broken location features

---

## Working agreement

1. Verify worktree, branch and Git status before making changes.
2. Small, reviewable commits.
3. Preserve existing tests; add new tests where appropriate.
4. Reuse shared components; never create a second implementation.
5. **Stop after each phase, demonstrate, and wait for approval before continuing.**
6. **Every phase ends with a named milestone tag** and acceptance criteria demonstrated before the tag is cut.
7. **Never build a stop-gap to route around a blocked decision.** A blocked phase waits. Inventing a temporary renderer, a temporary geocoder or a temporary provider means building it twice and risks a licence problem rather than merely a technical one.

Note: `git checkout`, `git switch`, `git restore`, `git reset` and `git clean` are **denied** by project policy in `.claude/settings.local.json`. Branch creation is a manual step by the owner. This is a deliberate guardrail and is not to be worked around.

---

## Related documents

- [`spatial-ui-integration-audit-2026-07-25.md`](./spatial-ui-integration-audit-2026-07-25.md) — the audit this roadmap executes
- [`spatial/basemap-r2-deployment-2026-07-28.md`](./spatial/basemap-r2-deployment-2026-07-28.md) — D2 decision record, basemap upload + integrity verification, R2 configuration, the CORS policy (verified for the development proof origin, awaiting production origins), and proof-route environment recovery
- [`technical-debt-test-suite.md`](./technical-debt-test-suite.md) — pre-existing test-suite debt found while verifying Phase 0
- [`architecture/MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md`](./architecture/MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md) — governing architecture
- [`architecture/GOOGLE-MAPS-PLATFORM-MIGRATION-INVENTORY.md`](./architecture/GOOGLE-MAPS-PLATFORM-MIGRATION-INVENTORY.md) — the Google-to-zero checklist
