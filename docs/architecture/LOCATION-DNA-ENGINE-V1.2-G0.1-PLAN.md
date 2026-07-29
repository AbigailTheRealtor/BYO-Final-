# G0.1 — Public Geometry Containment · Implementation Plan

**Gate:** G0.1 (v1.2 §17) · **Type:** safety correction only
**Status:** **PLAN ONLY — NOT IMPLEMENTED. NOT AUTHORISED FOR IMPLEMENTATION.**
**Governed by:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md) · adopted per
[Adoption Record](./LOCATION-DNA-ENGINE-V1.2-ADOPTION-RECORD.md)
**Audit baseline:** `387a971d8` — every finding below re-verified at this commit
**Principle enforced:** **L14** — user-drawn geometry is sensitive; exact vertices are never exposed publicly

> No code has been written. No file outside `docs/architecture/` has been touched. This document is the
> deliverable of the planning step only.

---

## 1. Present-state exposure — re-verified

### 1.1 The emission points

`resources/views/components/location-dna-map.blade.php` — 810 lines, the single shared read-only viewer
component — serialises the following directly into page HTML/JavaScript:

| Line | Statement | Content | Sensitivity |
|---|---|---|---|
| **335** | `var polygons = @json($polygons);` | **exact user-drawn polygon vertices** (`path[{lat,lng}]`, full precision) | **sensitive — L14** |
| **336** | `var radii = @json($radii);` | **exact radius centres** (`lat`, `lng`, `radius_miles`, `address`) | **sensitive — L14** |
| **226** | `var pinData = @json($propertyPin);` | subject-property `lat`/`lng` + formatted street address | see §1.5 — not L14 |
| 544 | `var geoRings = @json($geoPolygons);` | administrative boundary rings (Census TIGER) | public domain |
| 339–340, 545–546 | `floodZones`, `schoolDistricts` | FEMA NFHL / Census TIGER rings | public domain |

Source at line 63–64:

```
$polygons = $prefs['polygons']        ?? [];
$radii    = $prefs['radius_searches'] ?? [];
```

`$prefs` is the decoded `location_dna_preferences` blob, passed in as the `:preferences` attribute.

### 1.2 Recoverable from page source without interacting with the map

**Yes — confirmed.** `@json(...)` writes the values into a `<script>` block during server-side Blade
rendering. Retrieval requires only `view-source:` or `curl`. It requires:

- **no** interaction with the map,
- **no** JavaScript execution,
- **no** working Google Maps credential,
- **no** authentication on the four routes in §1.3.

The component's display *tier* logic (`$tier = 'polygons' | 'radii' | …`, lines 84–119) selects what is
*drawn*. It does **not** gate what is *emitted*: lines 335–336 execute whenever the component renders
with preferences. **Withholding the drawing does not withhold the data.**

### 1.3 Affected routes — authorisation re-verified via `php artisan route:list --json`

**In F-P1 scope — exact user-drawn search geometry on unauthenticated routes:**

| # | Route | Name | Controller | Middleware | Controller-level gating |
|---|---|---|---|---|---|
| 1 | `GET offer-listing/buyer/view/{id}` | `offer.listing.buyer.view` | `BuyerOfferListingController::view` | `["web"]` | archived → 404 unless owner; unapproved/draft → 404 unless owner. **Approved listings fully public.** |
| 2 | `GET offer-listing/tenant/view/{id}` | `offer.listing.tenant.view` | `TenantOfferListingController::view` | `["web"]` | same as above |
| 3 | `GET criteria/view/{id}` | `buyer.criteria.view` | `BuyerCriteriaAuctionController::view` | `["web"]` | **none — no auth check, no approval check, no `abort()`** |
| 4 | `GET tenant/criteria/auction/view/{id}` | `tenant.criteria.auction.view` | `TenantCriteriaAuctionController::view` | `["web"]` | **none — no auth check, no approval check, no `abort()`** |

Routes 3 and 4 are the more severe: they are the Search-Envelope *criteria* records — the purest form of
"where a person wants to live" — with **no gate of any kind**.

**Adjacent, audited, NOT in F-P1 scope:**

| Route | Name | Middleware | Emits | Why out of scope |
|---|---|---|---|---|
| `offer-listing/seller/view/{id}` | `offer.listing.seller.view` | `["web"]` | `:preferences="null"`; `:propertyPin` = subject-property lat/lng + address | Passes **no** search geometry. See §1.5. |
| `offer-listing/landlord/view/{id}` | `offer.listing.landlord.view` | `["web"]` | same | same |

### 1.4 Audit beyond the six routes — every surface checked

Searched all consumers of `location_dna_preferences`, `polygons`, `radius_searches`, latitude/longitude,
`PolygonBoundingBox`, `location-dna-map`, and serialized map payloads.

| Surface | Finding | Verdict |
|---|---|---|
| **Shared viewer component consumers** | Exactly 6 views use `<x-location-dna-map>`: buyer/tenant/seller/landlord offer-listing views, `buyer_criteria/view`, `tenant_criteria/view` | 4 carry geometry (§1.3) |
| **All views referencing `polygons`/`radius_searches`** | Only 5 files: the component, `map-input.blade.php` (editor), `tenant_criteria/view`, `buyer_criteria/add-bid`, `tenant_criteria/add-bid` | see below |
| **`map-input.blade.php` (editor)** | Emits full geometry at lines 402–403 — but only on **authenticated editing** routes for the record owner | **out of scope** — v1.2 forbids modifying owner editing behaviour |
| **`tenant_criteria/view.blade.php` lines 973–984** | **Second, independent emission path** outside the component: prints `{{ $rs['address'] ?? $rs }}` for each radius search, plus `location_notes` free text | **IN SCOPE** — see §1.6 |
| **`buyer_criteria/add-bid`, `tenant_criteria/add-bid`** | Render **labels only** — `$p['label']`, `$r['label']`, `radius_miles`. **No coordinates, no vertices.** Behind `BuyerBidderAuth` / `SellerBidderAuth` middleware | **no exposure** — already presence-plus-label |
| **Search / browse cards** (12 unauthenticated `search/*` routes) | Use `LocationDnaChipPresenter`, whose governance block confines it to chip labels (`"Custom Search Area"`, `"Radius Search"`) with no coordinates | **no exposure** — and the working precedent for §2 |
| **`routes/api.php`** | Two routes only: `auth:sanctum` user endpoint, `auth:sanctum`+throttle ask-ai. No location endpoints | **no exposure** |
| **Accepted-bid summaries** | `location_intelligence_snapshot` (JSON column) stores the **entire decoded blob**; populated by `AcceptedBidSummaryService`, `BuyerAcceptedBidSummaryService`, `BackfillLocationSnapshots`. Grep confirms **no view, template or PDF consumer** — stored, never rendered. `AcceptedBidSummaryController` enforces `Auth::user()` + `abort(403)` in **every** method despite bare `web` middleware | **no exposure now**; durable at-rest copy → retention risk **R12** |
| **PDF generation** | Placeholder replacement in `summary_html`; no geometry placeholder exists | **no exposure** |
| **Structured data / metadata / OG tags** | No schema.org, `og:`, or JSON-LD emission of geometry found | **no exposure** |
| **Livewire hydration payloads** | The 4 public viewer routes are plain controller-rendered Blade, not Livewire components. No Livewire payload carries viewer geometry | **no exposure** |
| **Outbound to Bridge MLS API** | `PolygonBoundingBox` converts polygons to **coarse bounding boxes** for OData `$filter`. Third-party disclosure (v1.2 §14, **R13**), never a page payload | **out of scope** — do not alter matching |
| **AI endpoints** (`ask-ai/ask`, `agent-ai/*`, `ai-knowledge/{token}`) | Unauthenticated or token-gated. **Not audited to conclusion in this pass** | **UNKNOWN — see §7 risk U1** |

### 1.5 Subject-property coordinates — deliberately excluded, with reasons

Routes `offer-listing/seller/view` and `offer-listing/landlord/view` publish the subject property's
exact `property_lat` / `property_lng` and formatted street address via `:propertyPin`.

**This is not an F-P1 privacy defect.** For a property offered for sale or rent, publishing the address
is the purpose of the listing. L14 protects *user-drawn search geometry* — where a person wants to live —
not the location of a property being marketed.

**It is, however, a licensing matter.** Those coordinates are Google-derived: `property_lat` /
`property_lng` are written alongside `google_place_id` by `GeocodeSelleryLandlordListings`. Rendering
them over a non-Google basemap would violate L9. This is recorded in v1.2 §10.2 and is a **G8
prerequisite**, not a G0.1 task.

**G0.1 must not change subject-property behaviour.** Doing so would be scope creep into G8 and would
alter a working public feature.

### 1.6 Complete sensitive-field inventory on the four in-scope routes

| Field | Currently exposed publicly | Precision | G0.1 disposition |
|---|---|---|---|
| `polygons[].path[]` | **yes** — line 335 | exact vertices, full float precision | **remove server-side** |
| `polygons[].label` | yes | user-authored text | retain (presence label) |
| `radius_searches[].lat` / `.lng` | **yes** — line 336 | exact centre | **remove server-side** |
| `radius_searches[].radius_miles` | yes | exact | retain — not locating on its own |
| `radius_searches[].address` | **yes** — line 336, and `tenant_criteria/view:976` | **exact street address of a search centre** | **remove server-side** — more precise than the coordinates |
| `cities`, `zip_codes`, `counties`, `state` | yes | published administrative names | **retain** — v1.2 §13.3 permits at T4 |
| `neighborhoods` | yes | published names | retain |
| `flexible_location` | yes | boolean | retain |
| `location_notes` | **yes** — component line 328; `tenant_criteria/view:981` | **free text, unbounded** | **owner decision D5** — see §7 |
| Flood zone / school district rings | yes | public-domain geometry of the *area*, not the user | retain |
| Administrative boundary rings (`geoRings`) | yes | public domain | retain |
| POI data | not emitted on these routes | — | n/a |
| Subject-property coords | routes 5–6 only | exact | **unchanged** (§1.5) |

---

## 2. G0.1 safety contract

For **unauthenticated public viewers** of the four in-scope routes:

1. Exact polygon vertices **must not be sent to the browser**.
2. Exact radius centres **must not be sent to the browser**.
3. Exact radius-centre street addresses **must not be sent to the browser**.
4. Exact user-drawn geometry must not appear in **HTML, JavaScript, page source, API responses,
   structured metadata, or client-side hydration payloads**.
5. **The sensitive payload is removed server-side before rendering.** Hidden-but-present coordinates that
   are merely not drawn are explicitly **not** acceptable and would fail the test matrix.
6. The absence of exact geometry **must not alter or delete canonical stored state**. G0.1 is read-path
   only. It performs no write of any kind.
7. Public pages show a **presence-only indicator**. Preferred wording:
   **"Search area preferences provided"** — plus the already-permitted administrative names
   (cities, ZIPs, counties, state) and `radius_miles` counts.
8. **No generalisation algorithm is invented in G0.1.** No envelope, no grid snapping, no bounding box.
   v1.2 §13.3's T3 algorithm requires owner decision **D3** and is **out of scope**.

**Explicitly prohibited in G0.1** — each would be scope creep into a later gate:

- modifying owner or authorised private **editing** behaviour (`map-input.blade.php`)
- modifying canonical persistence, serialisation or the hydrator
- altering matching behaviour, including `PolygonBoundingBox` and the Bridge OData path
- changing the renderer provider or introducing MapLibre
- removing Google
- touching `HasSearchAreas`, the mirror implementations, or anything in G1 scope
- introducing the domain core, envelope, capability config or revision token

---

## 3. Authorisation tiers

Per v1.2 §13.2, applied to the four in-scope routes.

| Tier | Definition | Determinable today? | G0.1 geometry |
|---|---|---|---|
| **T1 Owner** | `auth()->id() === $record->user_id` | **yes** — this exact comparison already exists in the two offer-listing controllers | **full precision** |
| **T2 Authorised participant** | hired agent, bid participant, transaction party | **NO — ambiguous.** No single rule exists. The two criteria controllers have no participant concept at all; bid-participant relationships are expressed through separate bid tables and custom middleware (`BuyerBidderAuth`, `SellerBidderAuth`) that these routes do not use | **withheld** — per instruction, ambiguity defaults to withholding. Recorded as owner decision **D4** |
| **T3 Authenticated private viewer** | logged in, not owner, not participant | yes (by elimination) | **withheld** |
| **T4 Unauthenticated public viewer** | no session | yes | **withheld — presence-only** |

**Existing authorisation rule per route:**

| Route | Existing rule |
|---|---|
| `offer-listing/buyer/view/{id}` | archived → 404 unless owner; unapproved or draft → 404 unless owner; otherwise **fully public** |
| `offer-listing/tenant/view/{id}` | identical |
| `criteria/view/{id}` | **none** |
| `tenant/criteria/auction/view/{id}` | **none** |

**Consequence to state plainly:** the conservative default narrows exact geometry to **owner only**. On
these four routes, any agent or participant who can currently see exact geometry would stop seeing it.
That is a **behaviour change for a currently-working surface**, and it is why **D4** is an owner
decision rather than an implementation detail. Until D4 is decided, withholding is the correct default —
but the owner should decide knowingly, not by omission.

**Authentication alone does not authorise exact geometry.** T3 is withheld.

---

## 4. Containment boundary

### 4.1 Shared-component risk

`<x-location-dna-map>` is used by **6 views**. A change inside the component would apply to all six,
including the two subject-property views that must remain unchanged (§1.5) and — critically — it would
be a **client-side or presentation-layer** decision, which §2 rule 5 prohibits as the primary mechanism.

There is a second, sharper risk. In all four in-scope controllers, `$locationDnaPreferences` is used for
**two different purposes**:

```
$locationDnaPreferences = $ldnaRaw ? (json_decode($ldnaRaw, true) ?? null) : null;

$boundaryData       = $boundaryLookupService->resolve($locationDnaPreferences, $legacyLocation);
$floodZoneData      = $floodZoneLookupService->resolve($boundaryData, $locationDnaPreferences ?? []);
$schoolDistrictData = $schoolDistrictLookupService->resolve($boundaryData, $locationDnaPreferences ?? []);
$locationIntelligence = $locationIntelligenceComposer->compose($boundaryData, $locationDnaPreferences ?? []);

return view(..., compact('locationDnaPreferences', ...));
```

**Server-side enrichment legitimately requires full geometry.** Redacting the variable too early would
silently degrade boundary, flood-zone, school-district and intelligence resolution — changing behaviour
well beyond G0.1 and potentially producing wrong public output.

**Therefore redaction must occur strictly at the controller → view handoff, after every enrichment
call.**

### 4.2 Chosen boundary

> **A server-side public-view projection, applied at the controller → view handoff, as an
> authorisation-aware explicit input.**

A new pure, stateless service resolves the viewer tier and returns either the full preferences array
(T1) or a redacted array with `polygons[].path`, `radius_searches[].lat`, `.lng` and `.address` removed
(T2–T4). Controllers pass the **projection result** to the view; the enrichment services continue to
receive the unredacted array.

**Why this boundary and not the alternatives:**

| Candidate | Verdict |
|---|---|
| **Server-side public-view projection at the handoff** | **chosen** — single choke point, server-side, authorisation-aware, testable in isolation, cannot affect enrichment or persistence, cannot affect the editor |
| View model / presenter | equivalent in effect; the projection *is* a presenter. Named as such to keep it pure and unit-testable |
| Route/controller-level redaction inline | rejected — duplicates the rule four times, drifts (exactly the five-mirror failure L1 exists to prevent) |
| Inside the shared component | rejected — presentation layer, affects all six views, and risks the two subject-property views |
| **Client-side conditional rendering** | **rejected outright** — violates §2 rule 5. The payload would still be in page source |

**Defence in depth (recommended, second layer):** the component may additionally assert that any
`preferences` array it receives is already tier-tagged, failing loudly in non-production if untagged.
This guards a future view that forgets the projection. It is a guard, not the mechanism, and it must be
written so it cannot strip geometry from T1 — see §7 risk U2.

---

## 5. File plan

### 5.1 Production code

| # | File | Prod/Test/Doc | Why it must change | How it stays inside G0.1 | Rollback |
|---|---|---|---|---|---|
| 1 | `app/Services/LocationDna/PublicGeometryProjection.php` **(new)** | **prod** | Single pure choke point: resolve tier, return full or redacted preferences | Read-only pure function. No DB, no HTTP, no writes, no provider calls. Mirrors `LocationDnaChipPresenter`'s governance-block idiom | delete file |
| 2 | `app/Http/Controllers/BuyerOfferListingController.php` | **prod** | Apply projection at view handoff (~line 137), after enrichment (lines 119–123) | One call + pass projected array. Enrichment inputs untouched. No write path touched | revert hunk |
| 3 | `app/Http/Controllers/TenantOfferListingController.php` | **prod** | same (~line 115 / handoff) | same | revert hunk |
| 4 | `app/Http/Controllers/BuyerCriteriaAuctionController.php` | **prod** | same (~line 350 / handoff) | same | revert hunk |
| 5 | `app/Http/Controllers/TenantCriteriaAuctionController.php` | **prod** | same (~line 620 / handoff) | same | revert hunk |
| 6 | `resources/views/tenant_criteria/view.blade.php` | **prod** | Second independent emission path at lines 973–984 prints radius-centre **street addresses** outside the component | Consume the projected array only. No layout redesign, no other section touched | revert hunk |
| 7 | `resources/views/components/location-dna-map.blade.php` | **prod** | *(optional, defence in depth)* assert input is tier-tagged; fail loudly if not | Guard only — must not itself redact, so it cannot affect T1 or the two subject-property views | revert hunk / omit |

**Files deliberately NOT changed:** `map-input.blade.php` (owner editing) · `HasSearchAreas.php` and the
four inline mirrors (G1) · `PolygonBoundingBox.php` and all matching code · `AcceptedBidSummaryService`
and `BuyerAcceptedBidSummaryService` (no rendering path) · seller/landlord view Blades (§1.5) · any
serializer, hydrator, model, migration or config.

**No migration. No config change. No model change. No write path. No canonical semantics change.**

### 5.2 Test code

| # | File | Purpose |
|---|---|---|
| 8 | `tests/Unit/LocationDna/PublicGeometryProjectionTest.php` **(new)** | Pure unit: tier resolution, redaction shape, T1 passthrough, idempotence, empty/absent/malformed input |
| 9 | `tests/Feature/LocationDna/PublicGeometryContainmentTest.php` **(new)** | Route-level, **raw response body** assertions across all four routes × tiers |
| 10 | `tests/Feature/LocationDna/PublicGeometryRegressionGuardTest.php` **(new)** | Search-based structural guard over public-view Blade for new geometry serialisation |

### 5.3 Documentation

| # | File | Status |
|---|---|---|
| 11 | `docs/architecture/LOCATION-DNA-ENGINE-V1.2-ADOPTION-RECORD.md` | **already created** |
| 12 | `docs/architecture/LOCATION-DNA-ENGINE-V1.2-G0.1-PLAN.md` | **this document** |

---

## 6. Test matrix

| # | Requirement | Test | Category | Asserts |
|---|---|---|---|---|
| **T-1** | Public responses contain no exact vertices | `PublicGeometryContainmentTest` | integration (route) | For each of the 4 routes, unauthenticated `GET`: **raw body** contains none of the seeded vertex coordinate literals, in any format (raw float, `@json`-escaped, string) |
| **T-2** | Radius centres and exact coordinates absent | same | integration | Raw body contains no seeded centre `lat`/`lng` and no seeded radius-centre `address` |
| **T-3** | Raw HTML / hydration payload examined, not rendered visibility | same | integration | Assertions run against `$response->getContent()` — never against rendered/visible text. Explicitly includes `<script>` contents |
| **T-4** | Authorised private surfaces still receive expected geometry | same | integration | Authenticated **as owner**: full vertices present. Confirms containment is tier-aware, not blanket |
| **T-5** | Canonical stored geometry unchanged | same | integration | Re-read `location_dna_preferences` after each public request: byte-identical to seeded value. Asserts G0.1 is read-only |
| **T-6** | Failed/unavailable maps cannot erase geometry | same + unit | integration | Render with no Google credential and with the component failing: canonical state unchanged; projection is pure and never writes (L5) |
| **T-7** | Every affected public workflow | same | integration | Parameterised across all 4 routes: buyer offer, tenant offer, buyer criteria, tenant criteria |
| **T-8** | Projection unit behaviour | `PublicGeometryProjectionTest` | behavioural | T1 → passthrough; T2/T3/T4 → redacted; labels/cities/ZIPs/counties/state/`radius_miles` retained; `path`/`lat`/`lng`/`address` removed; **idempotent**; absent/empty/malformed input safe; **never mutates input** |
| **T-9** | Presence indicator present | `PublicGeometryContainmentTest` | integration | Public body contains the presence string when geometry exists, and does not falsely claim it when none exists |
| **T-10** | Second emission path contained | same | integration | `tenant_criteria/view` specifically: no radius-centre address in raw body |
| **T-11** | Structural regression guard | `PublicGeometryRegressionGuardTest` | **structural (supplementary)** | No public-view Blade introduces `@json`/`json_encode` of `polygons`/`radius_searches` outside the projection. **Documented in-test as supplementary — not browser proof** |
| **T-12** | Behavioural browser verification | §6.2 | **browser — deferred** | See below |

### 6.1 Explicit non-equivalence statement

Per v1.2 §16.1, the test file headers must record: **T-11 is a structural guard and is not evidence of
runtime behaviour.** T-1…T-10 are server-side response-body assertions — stronger than structural,
still not browser verification.

### 6.2 Behavioural browser verification plan

**No browser automation exists** — no `tests/Browser`, no Dusk/Playwright/Puppeteer dependency
**[MEASURED]**. G0.1 therefore **cannot** be behaviourally verified in-repo, and installing tooling is
**G2**, which is not authorised.

G0.1's containment is server-side, so response-body assertions (T-1…T-3) are **materially stronger
evidence than for any client-side fix** — the payload either is or is not in the body, and that is
directly asserted.

**Planned browser scenarios, to run when G2 lands** (recorded now so G0.1 is not later assumed
browser-verified):

1. Load each of the 4 routes unauthenticated → `view-source:` contains no vertex; DevTools network
   payload contains no vertex.
2. Load authenticated as owner → geometry renders as today.
3. Load with the Google credential dead → no geometry leak via the failure path; canonical state intact.
4. Confirm the presence indicator renders as intended text, not a broken map frame.
5. Re-save the record as owner afterwards → geometry unchanged (guards against read-path redaction
   contaminating a later write).

**Interim manual verification** (a person, not automation), recorded in the G0.1 report:
`curl` each of the 4 routes unauthenticated and grep the body for seeded coordinates.

### 6.3 Non-vacuity probe

Passing tests must be proven capable of failing.

**Procedure — temporary, reverted immediately, never committed:**

1. **Baseline:** run the suite; all pass.
2. **Reintroduce the exposure:** in one controller, revert the handoff to pass the unredacted array
   (a one-line change reproducing today's behaviour).
3. **Assert failure:** T-1, T-2, T-3, T-7 and T-10 **must fail** for that route. If any passes, the
   test is vacuous — a wrong seed, a wrong assertion target, or an assertion against rendered text
   rather than raw body.
4. **Second probe — hidden-but-present:** redact only the *drawing* while leaving `polygons` in the
   JS payload. T-1 **must still fail.** This is the probe that proves §2 rule 5 is enforced rather
   than merely stated.
5. **Third probe — tier collapse:** make the projection redact unconditionally. **T-4 must fail**,
   proving the tests detect over-redaction that would break owners, not just under-redaction.
6. **Fourth probe — write contamination:** make the projection mutate the input array. **T-5 and T-8
   must fail.**
7. **Revert every probe.** Confirm a clean tree, then re-run the suite.

The G0.1 implementation report must state the observed pass/fail for all four probes. **A probe that
does not produce the expected failure blocks the gate.**

---

## 7. Risks and unknowns

| # | Item | Impact | Disposition |
|---|---|---|---|
| **D4** | T2 authorised-participant rule is **ambiguous** — no single existing rule; criteria controllers have no participant concept | Conservative default withholds geometry from agents/participants who see it today | **owner decision.** Default: withhold. Flagged, not silent |
| **D5** | `location_notes` is unbounded free text, publicly rendered (component line 328; `tenant_criteria/view:981`). Not geometry, but may contain precise location detail | Potential residual disclosure after G0.1 | **owner decision.** Not silently included (scope creep) nor silently ignored (negligent). Recommend review; trivial to add to the projection if the owner wants it |
| **U1** | AI endpoints (`ask-ai/ask`, `agent-ai/*`, `ai-knowledge/{token}`) not audited to conclusion | Unknown whether any assembles location DNA into a response reachable without authentication | **Recommend a bounded follow-up audit before G0.1 is closed.** Not blocking the plan; would be blocking if it proved to expose geometry |
| **U2** | Optional component guard (file 7) could, if written wrongly, strip geometry from T1 or break the two subject-property views | Regression on a working surface | Guard must **assert only, never redact**. T-4 covers it. May be omitted entirely |
| **U3** | Radius-centre `address` removal may leave an empty label where a page previously showed text | Cosmetic | Presence-only wording (§2 rule 7) covers it. Not a data risk |
| **U4** | `tenant_criteria/view:976` prints `{{ $rs['address'] ?? $rs }}` — echoing an **array** when `address` is absent | Pre-existing latent render error, unrelated to F-P1 | **Do not fix in G0.1** — note only; fixing is unrelated scope |
| **R12** | `location_intelligence_snapshot` holds a durable full copy of geometry on `accepted_bid_summaries` | At-rest retention of sensitive data; not currently exposed | Out of G0.1 scope. Retention policy remains an open owner item |
| **R13** | Geometry disclosed to Bridge MLS API as bounding boxes | Third-party disclosure | Out of scope; must not alter matching |
| **G8** | Subject-property coordinates are Google-derived and publicly rendered | Licence exposure if ever rendered over PMTiles | v1.2 §10.2; G8 prerequisite, not G0.1 |

---

## 8. Rollback plan

- **Granularity:** every change is an independent revert. Files 2–7 are single hunks; file 1 is a new
  file (delete); files 8–10 are new tests (delete).
- **Order:** revert controllers (2–5) first — that alone restores prior behaviour end to end. Then the
  Blade hunk (6), then the optional guard (7), then delete the service (1).
- **No data migration, no config change, no schema change** ⇒ **nothing to un-migrate.** Canonical state
  is never written by G0.1, so rollback cannot lose data.
- **Feature-flag alternative:** a flag could gate the projection, but it would mean shipping code whose
  "off" state is the known exposure. **Recommendation: no flag.** Revert is the rollback.
- **Post-rollback verification:** re-run T-1…T-10 and confirm they **fail** (the exposure is back) —
  which also re-proves non-vacuity.
- **Honest note:** rollback restores a **known live privacy exposure**. It is a deliberate decision,
  not a safe default.

---

## 9. Stop conditions

Implementation stops immediately and reports, without completing, if any of these is encountered:

1. Containment requires changing canonical state semantics.
2. Containment requires renderer migration.
3. Containment requires a geocoder decision.
4. Containment requires provider activation.
5. Authorisation rules for a route cannot be determined — **already partially triggered: D4 (T2) is
   ambiguous.** The plan proceeds only because withholding is a safe default; if the owner requires T2
   to retain geometry, implementation stops until D4 is decided.
6. Exact geometry turns out to be required by a public feature that cannot be safely removed without an
   owner decision.
7. The required change would enter G1 scope — the domain core, envelope, capability config, revision
   token, mirror consolidation, or `HasSearchAreas`.
8. **Additional stop conditions specific to this gate:**
   - Any non-vacuity probe fails to produce its expected failure (§6.3).
   - Redaction cannot be placed after the enrichment calls without altering boundary, flood-zone or
     school-district output (§4.1).
   - U1 audit finds an AI endpoint exposing geometry — that is a **new** exposure needing its own
     decision, not an extension of G0.1.
   - Any change would touch `map-input.blade.php` or owner editing behaviour.

---

## 10. Authorisation status

**G0.1 is planned and not implemented.** Implementation requires explicit owner authorisation.

Recommended sequence at authorisation:

1. Decide **D4** (T2 participants) and **D5** (`location_notes`).
2. Approve the containment boundary (§4.2) and the file plan (§5).
3. Authorise implementation with the stop conditions in §9 binding.
4. Optionally authorise the bounded U1 audit first.

**On completion, the report must state:** the four non-vacuity probe outcomes; the manual `curl`
verification result; explicit confirmation that canonical state was not written; and explicit
confirmation that G0.1 remains **not** behaviourally browser-verified until G2.
