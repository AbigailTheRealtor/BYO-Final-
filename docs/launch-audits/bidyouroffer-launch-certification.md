# BidYourOffer — Production Launch Certification Report

**Audit type:** Full production launch certification (code-grounded static audit + config/route/DB-contract verification)
**Auditor roles:** Lead QA · Lead Laravel Architect · Lead PM · Lead UX · Lead Security · Final technical reviewer
**Date:** 2026-06-25
**Codebase:** Laravel 8.83 · 229 migrations · four-role symmetry (Seller/Buyer/Landlord/Tenant)
**Branch:** `main` (with uncommitted "Stellar" property-detail / matchmaker feature work)

---

## ⚠️ Scope & method honesty statement (read first)

This certification was performed as a **rigorous static code audit** with targeted verification of routes, config, persistence contracts, and Blade↔controller field contracts. **Authenticated browser automation was not available in this environment** — the test app is a login-gated Replit instance driven by Livewire POST flows that `WebFetch` cannot exercise. Therefore:

- Every finding marked **CONFIRMED** is grounded in specific `file:line` evidence and, for the top blockers, independently re-verified by the lead reviewer (route table, controller validation rules, Blade form fields, ownership scoping).
- Findings marked **NEEDS-RUNTIME** are strongly implied by code but require a live click-through or DB query to confirm exploit/impact. They are listed as such and **must be runtime-verified before launch** — they are not counted as "passing."
- The launch checklist explicitly marks runtime-only items as **NOT TESTED** rather than guessing.

A clarification surfaced during the audit that materially changes scope: there are **two distinct "match/DNA" stacks**, and the brief conflates them.
1. **Offer-Listing stack** (the four `/offer-listing/{role}` wizards) — the BidYourOffer core product.
2. **Stellar stack** (`/stellar/property/{listingKey}`, `/stellar/buyer/results`) — an **IDX/MLS listing-detail + buyer-matchmaker** built on external Bridge MLS data (`bridge_properties.raw_json`). The Stellar detail page is **not** a BidYourOffer offer-listing review, so sections named in the brief such as *Offer Summary, Offer Terms, Concessions, Criteria Summary, uploaded documents* do not exist there by design (they live in the offer-listing views). "Property DNA" on the consumer path is **deterministic/rule-based, not OpenAI-generated** — the CLAUDE.md description is stale.

---

## 1. Executive Summary

BidYourOffer is a feature-rich, architecturally mature platform, and large parts of it are well-built: the Buyer matching engine is injection-safe and fails open on MLS outage; the Location DNA geospatial math is correct and unit-consistent; the consumer DNA/summary surfaces are deterministic with **no hallucination or cost-blowout risk**; XSS hygiene on the new Stellar views is clean (zero unescaped `{!! !!}`); and the Stellar results/detail pages are correctly ownership-scoped.

**However, the platform is NOT production-ready.** The audit found **multiple confirmed Critical security defects and feature-breaking bugs** that block a public launch:

1. **Broken Access Control / IDOR across all four offer-listing edit & create write paths** — any authenticated user can read (Tenant) and overwrite/publish (all roles) another user's listing by changing the `{auctionId}` in the URL. There are **no authorization policies** for these models. *(CONFIRMED, re-verified)*
2. **Unauthenticated IDOR on the Ask-AI endpoint** — `/ask-ai/listing-question` sits outside the `auth` group with no throttle, loads any listing by integer id with no ownership check, and can leak restricted private fields (income requirement, financing terms, flood, deposit) on a knowledge-base miss. *(CONFIRMED route/auth; leak path NEEDS-RUNTIME)*
3. **The Ask-AI widget is 100% non-functional** — the Stellar Blade form posts `listing_key`/`criteria_id`, but the controller requires `listing_type`+`listing_id` (integer); every submission 422s and the validation error is rendered to the user as if it were an AI answer. *(CONFIRMED)*
4. **Seller create-path data loss** — the entire Broker Compensation tab plus ~40 financing/lease-option fields are validated but never persisted on Create (they *are* saved on Edit), silently losing legally/financially load-bearing data. *(CONFIRMED)*
5. **Edit-flow validation is far weaker than Create** in all four roles (e.g. Seller "Save Edit" validates a single field), allowing a listing to be published with required fields blank. *(CONFIRMED)*

In addition there are correctness defects in matching (Tenant rental/sale collision, inverted negative-amenity scoring, residential score capped at 94/100), operational gaps in Location DNA (geocode call has no timeout, silent Google failures cached as empty for 24h, no concurrency guard), and UX-honesty issues (always-on "coming soon" placeholder cards, vacant land mislabeled as "Traditional Residential Property").

### Verdict

> **Would I personally approve BidYourOffer for production launch today? — NO.**

The IDOR/authorization defects alone are disqualifying for any platform handling private financial criteria. Combined with a non-functional flagship "Ask AI" feature and silent data loss on the Seller create flow, the platform requires a focused remediation pass before it can be certified.

### Overall Launch Readiness Score: **52 / 100** — NOT CERTIFIED

---

## 2. Score Card

| Dimension | Score | Notes |
|---|---:|---|
| **OVERALL LAUNCH READINESS** | **52** | Blocked by security + create-path data loss |
| **By role** | | |
| Seller | 45 | Create-path data loss (broker comp), IDOR, 1-field edit validation |
| Buyer | 60 | IDOR + suspected multiselect submit loss; matching engine sound |
| Landlord | 58 | IDOR + waterfront edit error; strong EAV round-trip |
| Tenant | 45 | Worst IDOR (read+write); rental/sale matching collision |
| **By property type** | | |
| Residential | 70 | Best supported across display + matching |
| Income Property | 55 | Thin detail display; financials not mapped |
| Commercial Sale | 50 | Thin display; additive non-residential scoring |
| Business Opportunity | 48 | Thinnest support; `other_business_type` create-loss (Buyer) |
| Vacant Land | 45 | Mislabeled as residential personality; geocode skips no-street-address |
| Commercial Lease | 55 | EAV-backed; tenant rental/sale matching gap |
| **By area** | | |
| Create Listing | 55 | Functional but persistence + validation gaps |
| Draft / Edit | 50 | Create/Edit parity gaps + IDOR on write paths |
| Offer Details / Display | 80 | Safe, private, degrades cleanly; placeholder-card polish |
| Location DNA | 72 | Correct math; operational timeout/observability gaps |
| Property DNA | 78 | Deterministic, low-risk; land mislabel; admin-AI ungrounded |
| Ask AI | 22 | Broken widget + unauth IDOR + leak path |
| Matching | 72 | Buyer sound; tenant/amenity/score-ceiling defects |
| MLS / Bridge Integration | 75 | Injection-safe, bounded, fail-open; per-request import latency |
| Validation | 55 | Edit ≪ Create; server-side thin (client-JS reliant) |
| Security | 45 | Strong scoping on Stellar; undermined by IDOR x2 + injection |
| Performance | 72 | N+1-free mappers; synchronous per-request Bridge import |

---

## 3. Issues by Severity

### 🔴 CRITICAL (Launch Blockers)

#### C1 — IDOR / Broken Access Control on offer-listing Edit & Create write paths *(all four roles)*
- **Severity:** Critical · **Launch Blocker: YES** · **CONFIRMED (re-verified)**
- **Root cause:** Edit routes pass the listing id in the URL (`/offer-listing/{role}/edit/{auctionId}`, `web.php:1124-1138`). The `update()`/`saveDraft()` write paths resolve the record from the client-controlled `auctionId` with **no `where('user_id', Auth::id())` and no policy**. On Create, `store()` re-fetches by `listingId` and **reassigns `user_id = Auth::id()`**, letting a tampered id claim an arbitrary row. There are **zero policies** for these models (`app/Policies/` contains only `ShowingPolicy`).
- **Reproduction:** Log in as user A, open `/offer-listing/seller/edit/{id-owned-by-user-B}`, submit → B's listing is overwritten/published. For Tenant, the **load** is also unscoped, so B's private data is *readable*.
- **Files:** `SellerOfferListingEdit.php:2141,3978` (+ create `SellerOfferListing.php:2226`); `BuyerOfferListingEdit.php:1696,2800` (+ `BuyerOfferListing.php:1768`); `LandlordOfferListingEdit.php:2200,3825` (+ `LandlordOfferListing.php:2279`); `TenantOfferListingEdit.php:2287,2326,2404,3123` (load **and** save unscoped). Routes `web.php:1124-1138`.
- **Fix:** Re-fetch every write with `->where('user_id', Auth::id())->firstOrFail()` (or a Policy + `abort(403)`); never reassign `user_id` on an existing row. Best done once via a shared owner-scoped fetch trait used by all four roles. Add `OfferListingPolicy`.
- **Effort:** M (one shared pattern across four roles, ~0.5–1 day incl. tests).

#### C2 — Unauthenticated IDOR + private-field leak on Ask-AI endpoint
- **Severity:** Critical · **Launch Blocker: YES** · **CONFIRMED (auth/route); leak NEEDS-RUNTIME**
- **Root cause:** `POST /ask-ai/listing-question` is registered **outside** the `auth` group with **no throttle** (`web.php:262-264`). Controller reads `listing_type`+`listing_id` from the request and answers about that listing with **no ownership/authorization check** (`AskAiListingQuestionController.php:28-83`). On a knowledge-base miss, execution falls through to the OpenAI adapter with an `allowed_context` that still contains RESTRICTED keys (flood zone, deposit, income requirement/multiplier, rent min/max, seller-financing terms) — `SnapshotFactVisibility.php:28-57`, `AskAiResponseContractService.php:229,307,328`, `AskAiRunnerV2Service.php:~4420-4456`.
- **Reproduction:** Anonymous `POST /ask-ai/listing-question` with `listing_type=buyer&listing_id=<N>&question="what is the income requirement / flood zone?"` enumerating sequential ids → returns answers about offer listings the caller does not own.
- **Fix:** Move route behind `auth` + ownership policy, OR restrict `listing_type` to a public Bridge/MLS allow-list and reject seller/buyer/landlord/tenant types from the unauth path. Strip RESTRICTED keys from `allowed_context` at context assembly (not only at the KB-hit gate). Add `throttle:ask-ai-api`. Add regression test for the KB-miss path.
- **Effort:** 0.5–1 day.

#### C3 — Ask-AI widget completely non-functional (Blade↔controller field contract mismatch)
- **Severity:** Critical (flagship feature broken) · **Launch Blocker: YES** · **CONFIRMED**
- **Root cause:** Blade posts `listing_key` (string), `criteria_id`, `criteria_type`, `question` (`matchmaker-ask-ai.blade.php:25-32`). Controller requires `listing_type` (string) **and** `listing_id` (**integer**) (`AskAiListingQuestionController.php:30-35`). Every submission fails validation with 422 before reaching the runner. The widget JS then renders Laravel's validation `message` to the user as if it were an AI answer (`matchmaker-ask-ai.blade.php:81-90`).
- **Reproduction:** Open any `/stellar/property/{listingKey}`, ask a question → 422; user sees a validation string styled as an AI response.
- **Fix:** Emit `listing_type` + numeric `listing_id` from the component (the detail controller already has `$listing->id` in scope, `StellarPropertyDetailController.php:114`); confirm the runner accepts the Bridge listing-type token. Make JS fail safe on non-2xx.
- **Effort:** 1–2 h (+ confirm runner Bridge support).

#### C4 — Seller Create silently drops Broker Compensation + ~40 financing/lease-option fields
- **Severity:** Critical (silent data loss on legally load-bearing fields) · **Launch Blocker: YES** · **CONFIRMED**
- **Root cause:** Those fields are validated and sanitized on Create but absent from both `saveAllMetadata()` and `buildDraftPayload()` in the Create component; the **Edit** component *does* save them. Data entered at Create is lost and shows blank on later edit.
- **Files:** Create save path `SellerOfferListing.php:3165-3826` (e.g. `commission_structure_type` validated `:4259-4302`, never saved); Edit saves at `SellerOfferListingEdit.php:3244-3961`.
- **Fix:** Mirror Edit's `saveMeta` calls in Create and add keys to `buildDraftPayload`.
- **Effort:** M.

### 🟠 HIGH

| ID | Issue | Files | Blocker | Status |
|---|---|---|---|---|
| H1 | Edit-flow submit validation far weaker than Create (Seller validates 1 field; Tenant 8; Landlord/Buyer minimal) → publish with required fields blank | `SellerOfferListingEdit` update(); `TenantOfferListingEdit:3082-3102`; `Landlord/Buyer` Edit update() | Strongly recommended pre-GA | CONFIRMED |
| H2 | Landlord/Seller **waterfront fields** error or lose data in Edit: Blade binds `wire:model="water_frontage"/"waterfront_feet"` but Edit declares neither property → Livewire "public property not found" + data wiped | `property-preferences.blade.php:732,746`; `LandlordOfferListing.php:692-693` (Edit missing); Seller Edit missing | YES (edit flow) | CONFIRMED |
| H3 | Buyer Edit may drop multiselect edits on Submit (Create has `updated*Json` reconcile hooks; Edit lacks them) | `BuyerOfferListing.php:2349-2421` vs `BuyerOfferListingEdit` saveAllMetadata | YES (verify) | NEEDS-RUNTIME |
| H4 | Tenant matching cannot distinguish rentals from sales — loader emits `PropertyType='Residential'` (same as for-sale), forces `max_price=null` → candidate set includes for-sale homes, price scoring disabled | `TenantOfferListingCriteriaLoader.php:125-131,178` | YES if Tenant in launch scope | CONFIRMED |
| H5 | One-sided city normalization may zero out city matches (client `St.→Saint`; Bridge stores raw, exact `in_array`) | `BuyerOfferListingCriteriaLoader.php:301-306`; `BridgePropertyNormalizer.php:45`; `BuyerMatchScorer.php:154` | Verify before city-only search | NEEDS-RUNTIME |
| H6 | Location DNA geocode HTTP call has **no timeout** (Guzzle default infinite) — stage 1 hang stalls worker | `LocationDnaGeocodeService.php:200-205` | Pre-GA | CONFIRMED |
| H7 | Google POI failures totally silent (status REQUEST_DENIED/OVER_QUERY_LIMIT ignored; `catch{return []}` no log) → total POI loss looks like "no POIs nearby" | `GooglePlacesPoiAdapter.php:82-84,106-108` | Pre-GA (observability) | CONFIRMED |
| H8 | Transient POI outage cached as empty success for 24h (cache poisoning) | `PoiDistanceLookupService.php:110-119` | Pre-GA | CONFIRMED |
| H9 | Admin AI Marketing Report: no system prompt, no factual grounding, temperature ~1.0, self-attested source attribution | `OpenAiClientService.php:276-285`; `AiMarketingReportGeneratorService.php:142-148,280-301` | No* (human-approval gated) | CONFIRMED |

\* H9 is not a launch blocker **only because** AI reports are written `pending_review`, escaped on render, admin/agent-only, and gated behind mandatory human approval. **If that gate is ever bypassed/auto-published, H9 becomes Critical.**

### 🟡 MEDIUM

| ID | Issue | Files | Status |
|---|---|---|---|
| M1 | Negative amenity preferences mis-scored as matches ("No pool" still earns full pool points) | `BuyerMatchScorer.php:353-375` | CONFIRMED |
| M2 | "Why this matches" surfaces no-preference neutrals as positive reasons (financial/amenity "why" when buyer set no criteria) | `BuyerMatchScorer.php:382-406`; `BuyerResultViewMapper.php:188-198` | CONFIRMED |
| M3 | Residential listings can never reach 100% (location capped at 24 not 30 → max 94); non-residential additive block can exceed 100 (clamped); cross-type scores not comparable | `BuyerMatchScorer.php:30,167-173` | CONFIRMED |
| M4 | Three permanent "coming soon" placeholder cards always render (commute, appreciation, flood-zone never bind real data) | `matchmaker-commute/appreciation/flood-zone.blade.php`; `detail.blade.php:170,172,186` | CONFIRMED |
| M5 | Flood-zone data computed in pipeline but never surfaced (summary contract has no flood keys) | `LocationDnaSummaryService.php:195-262` | CONFIRMED |
| M6 | Non-residential property types under-served in detail display (no income/commercial financials mapped) | `PropertyDetailViewMapper.php:31-179` | CONFIRMED |
| M7 | Vacant Land mislabeled as "Traditional Residential Property" personality | `PropertyPersonalityService.php:504,742-762` | CONFIRMED |
| M8 | Google Places API key printed into client HTML map iframe (verify GCP referrer restriction) | `property-map.blade.php:14,46`; `config/services.php:37` | NEEDS-RUNTIME |
| M9 | No concurrency guard on POI delete→insert; `ComputeLocationDna` lacks `ShouldBeUnique`/lock (unique index can throw on overlap) | `ComputeLocationDna.php:14`; `LocationDnaPoiDistanceService.php:866-870` | CONFIRMED |
| M10 | OpenAI admin call synchronous on web request with blocking `sleep()` retry (~4 min worst case); no `max_tokens`; no route throttle | `OpenAiClientService.php:91,348,357` | CONFIRMED |
| M11 | Draft change-detection hash omits persisted fields (Seller broker/financing; Buyer 8 fields) → "No changes detected" skips save | `SellerOfferListing.php:2449-2498`; `BuyerOfferListing.php:1408-1745` | CONFIRMED |
| M12 | Buyer `other_business_type` collected but never saved on Create (Edit saves it) | `BuyerOfferListing.php` saveAllMetadata | CONFIRMED |
| M13 | Buyer server-side submit validation minimal (state/counties/auction_time only); rest is client JS | `BuyerOfferListing.php:2870-2885` | CONFIRMED |
| M14 | No prompt-injection defense (raw question into prompt; system instructions in client-visible `prompt_package`) | `AskAiPromptBuilderService.php:112-156,174,224` | CONFIRMED |
| M15 | Validation errors rendered as AI answers (symptom of C3, should fail safe regardless) | `matchmaker-ask-ai.blade.php:81-90` | CONFIRMED |
| M16 | No retries on any external integration (Google/FEMA/Census/geocode) | adapters as listed | CONFIRMED |
| M17 | POI tile cache uses per-process `array` store → advertised 7-day cross-listing reuse doesn't work | `LocationDnaPoiTileCache.php:94,111` | CONFIRMED |
| M18 | No `place_id` dedup across grouped POI categories (same POI ranks in two categories) | `LocationDnaPoiDistanceService.php:902-917` | CONFIRMED |
| M19 | Vacant land / no-street-address listings skipped at geocode → no Location DNA origin | `LocationDnaGeocodeService.php:31,81-87` | CONFIRMED |
| M20 | School lookup queries Unified districts only (TIGER layer 0) | `CensusSchoolDistrictAdapter.php:23` | CONFIRMED |

### 🟢 LOW

| ID | Issue | Files |
|---|---|---|
| L1 | Duplicate `'closing_appointment' => false` array key in `$enable` (all four roles) | `*OfferListing.php` (Seller :844, Buyer :517, Landlord :832, Tenant :744) |
| L2 | `SAVE_AS_NEW_DRAFT = true` with "set to false before launch" TODO (Seller, Tenant) | `SellerOfferListing.php:24`; `TenantOfferListing.php:31` |
| L3 | Dangling "Listing data last updated." with no date | `property-office.blade.php:16` |
| L4 | Lot size shown only from acres; `lot_size_sqft` mapped but never rendered (land listings) | `property-header.blade.php:92`; `property-key-facts.blade.php:16` |
| L5 | Dead "city" methods/validation referencing undeclared props (Buyer) | `BuyerOfferListing.php:929-986` |
| L6 | Buyer/Tenant can match only one property type per search (scalar→single-element array) | `BuyerOfferListingCriteriaLoader.php:116-121` |
| L7 | Commute stub config trap: setting `provider=google` leaves interface unbound → resolution error | `CommuteTimeStubAdapter.php`; `config/location_dna.php:90` |
| L8 | Doc error: CLAUDE.md says `ldna:generate`; actual command is `location-dna:generate` | `GenerateLocationDna.php:14` |
| L9 | `listing_type` accepts arbitrary string (no allow-list) | `AskAiListingQuestionController.php:31` |
| L10 | Dead code: `$themeGroups` unused in nearby component | `matchmaker-nearby.blade.php:13-21` |

---

## 4. Categorized Findings (deliverable index)

- **Duplicate Fields:** L1 (closing_appointment ×4). No other true duplicate persisted fields found; Landlord/Tenant EAV round-trips verified (no meta_key typos).
- **Missing Fields:** C4 (Seller broker-comp not persisted on Create), M12 (Buyer `other_business_type`), Seller `water_frontage`/`waterfront_feet` absent from Edit, M6 (non-residential financials not mapped in display).
- **Create/Edit Parity Issues:** C4, H1, H2, H3, M11; Seller AI/Seller-Info tab index swapped between create/edit blades; Buyer `assignment_fee_type` default `''` vs `'$'`.
- **Offer Detail Issues:** M4, M5, M6, L3, L4 (Stellar detail). Core offer-listing review display largely sound and null-safe.
- **Matching Issues:** H4, H5, M1, M2, M3, L6.
- **Location DNA Issues:** H6, H7, H8, M9, M16, M17, M18, M19, M20, L7, L8.
- **Property DNA Issues:** H9, M7, M10; consumer path deterministic and safe.
- **Ask AI Issues:** C2, C3, M14, M15, L9.
- **Validation Issues:** H1, M13, M11.
- **Authorization Issues:** C1, C2; no policies beyond `ShowingPolicy`.
- **Database Issues:** EAV round-trips healthy; M9 unique-index race; no migration integrity defects found.
- **Browser/Console Issues:** NOT TESTED (no browser automation). H2 will produce a Livewire console error in Edit; C3 produces a visible 422 in the network tab.
- **UI/UX Issues:** M4 (placeholder cards), M7 (land mislabel), L3, L4.
- **Performance Issues:** F7/synchronous per-request Bridge import (`BuyerMatchService.php:27,40-62`); M10 synchronous OpenAI; mappers are N+1-free (verified).
- **Technical Debt:** L2 launch-flag TODOs, L5/L10 dead code, stale CLAUDE.md (Property DNA description, command name), `AiMarketingReportOrchestratorService::generate()` appears unwired.

---

## 5. What Was Verified CLEAN (positive confirmations)

These reduce risk and should be credited:

- **No XSS:** zero `{!! !!}` in `resources/views/components/stellar/` and `resources/views/stellar/`; all dynamic data escaped; `raw_json` never passed to views.
- **OData injection-safe:** single-quote doubling for strings, int casts for numerics, `number_format` for coords (`BuyerCriteriaODataFilterBuilder.php:167-170`).
- **Bridge fails open:** API error/timeout/missing-credentials → returns `[]`/proceeds against local cache; fetches bounded (`lazy_max_records=500`, 20×200 pages); never unbounded full-dataset pull.
- **Match math safe:** all ratio denominators guarded; total clamped `[0,100]`; no divide-by-zero/off-by-one.
- **Stellar pages ownership-scoped:** `whereIn('user_id', $allowedUserIds)` in criteria loaders; match scoring restricted to requester's own criteria; no cross-party leak on results/detail.
- **Privacy on detail:** Tier-6 PII (agent email/phone/lockbox/internal remarks) omitted by mapper.
- **DNA is queued + deterministic on consumer path:** no synchronous OpenAI/external call on any consumer page view; Haversine correct and unit-consistent; graceful degradation when API keys absent; append-only versioning with `pg_advisory_xact_lock` per listing.
- **Detail page 500-safe:** Section-2 enrichment wrapped in `try/catch(\Throwable)`; mapper builds every key the blades read; `firstOrFail()` → 404 on missing listing, 403 on non-IDX.
- **Mass-assignment:** `BridgeProperty` uses scoped `$fillable`.
- **config/match_scoring.php integrity:** all weights sum to 100; engine self-normalizes by enabled-weight total (the "enabled must sum to 100" wording is loose but the invariant holds).

---

## 6. Production Launch Checklist

| Item | Status |
|---|---|
| Offer-listing Create — all roles render & save core fields | **PASS** (with C4 exception for Seller broker-comp) |
| Offer-listing Edit/Update preserves all data | **FAIL** (C4, H2, H3) |
| Save Draft preserves all entered data | **FAIL** (M11 hash omissions; Seller broker-comp) |
| Submit validation enforced server-side (Edit) | **FAIL** (H1, M13) |
| Authorization — owner-only edit/delete of listings | **FAIL** (C1 — no policy, IDOR) |
| Ask AI returns grounded answers | **FAIL** (C3 broken widget) |
| Ask AI endpoint authenticated & rate-limited | **FAIL** (C2) |
| Ask AI never leaks restricted private fields | **FAIL / NEEDS-RUNTIME** (C2 leak path) |
| Matching — Buyer correctness | **PASS** (with M1/M2/M3 caveats) |
| Matching — Tenant rentals vs sales | **FAIL** (H4) |
| Matching — city/county normalization both-sided | **NEEDS-RUNTIME** (H5) |
| Location DNA — correct distance math & ranking | **PASS** |
| Location DNA — external-call timeouts & failure handling | **FAIL** (H6, H7, H8) |
| Location DNA — concurrency-safe writes | **FAIL** (M9) |
| Location DNA — flood zone surfaced to consumer | **FAIL** (M5) |
| Property DNA — consumer path safe (no hallucination/cost) | **PASS** |
| Property DNA — vacant land labeled correctly | **FAIL** (M7) |
| Admin AI report — grounded & human-gated | **PASS (gated)** / H9 risk if gate removed |
| XSS / output escaping (Stellar) | **PASS** |
| OData / SQL injection safety | **PASS** |
| Secrets not hardcoded in committed code | **PASS** |
| Google Maps key referrer-restricted | **NOT TESTED** (M8) |
| Performance — no N+1 in mappers | **PASS** |
| Performance — no synchronous external call on interactive pages | **FAIL** (per-request Bridge import) |
| Console errors / 404 / 500 across flows | **NOT TESTED** (no browser automation) |
| End-to-end browser walkthrough per role × property type | **NOT TESTED** (no browser automation) |
| Launch flags (`SAVE_AS_NEW_DRAFT`) set to production values | **NOT TESTED** (L2) |

---

## 7. Final Certification

### 1. Would I personally approve BidYourOffer for production launch today?
**No.**

### 2. Why not (exactly)?
Four independent, confirmed disqualifiers:
1. **Authorization is missing on the core write paths** (C1). Any logged-in user can overwrite or publish another user's offer listing — and read a Tenant's private listing — by editing a URL id. There are no policies. This is a Critical broken-access-control vulnerability on a platform holding private financial criteria.
2. **The Ask-AI endpoint is unauthenticated and enumerable** (C2), with a code path that can leak restricted fields (income requirement, financing terms, flood, deposit).
3. **The flagship Ask-AI widget does not work at all** (C3) and shows raw validation errors to users.
4. **The Seller create flow silently loses Broker Compensation and ~40 financing fields** (C4) — load-bearing, legally significant data.

Any one of (1)–(2) is independently launch-blocking.

### 3. Prioritized remaining tasks

#### MUST FIX BEFORE LAUNCH
1. **C1** — Owner-scope all offer-listing Edit/Create write & load paths; add `OfferListingPolicy`. *(M)*
2. **C2** — Authenticate + authorize + throttle `/ask-ai/listing-question`; strip RESTRICTED keys at context assembly; add KB-miss regression test. *(0.5–1d)*
3. **C3** — Fix Ask-AI field contract (`listing_type`+`listing_id`); JS fail-safe on non-2xx. *(1–2h)*
4. **C4** — Persist Seller Broker Compensation + financing fields on Create + draft. *(M)*
5. **H1** — Unify Edit↔Create submit validation across all four roles (shared rule/sanitize concern). *(M)*
6. **H2** — Add waterfront props+save+load to Landlord & Seller Edit. *(S)*
7. **H3** — Verify & fix Buyer multiselect submit persistence. *(M, runtime-verify)*
8. **H4** — Fix Tenant rentals-vs-sales filter **if Tenant is in launch scope**. *(M)*
9. **H6/H7/H8** — Geocode timeout; log Google POI failures; stop caching error-empty POI results. *(S each)*
10. Confirm **launch flags** (`SAVE_AS_NEW_DRAFT`, commute provider) set to production values. *(XS)*
11. **Runtime-verify** H5 (city normalization), M8 (Maps key restriction), and complete a **manual browser walkthrough per role × property type** (the coverage this static audit could not exercise). *(0.5–1d)*

#### CAN WAIT UNTIL AFTER LAUNCH
- M1–M3 matching refinements (negative-amenity, neutral "why", 94/100 ceiling)
- M4/M5 placeholder cards + flood-zone surfacing; M7 vacant-land archetype
- M6 non-residential detail-display fields; L4 lot-size sqft fallback
- M9/M16/M17/M18/M20 Location DNA robustness (concurrency, retries, tile cache, POI dedup, school layers)
- M10/H9 admin OpenAI hardening (queue, max_tokens, system prompt) — *gate must stay hard*
- M14 structural prompt-injection defenses (before any GA expansion of Ask AI)
- Per-request Bridge import → queued/scheduled refresh
- L1–L10 cleanups; stale-doc fixes

---

## 8. Audit process note

This certification was produced by six specialist reviewers working in parallel across the seven audit phases, with the lead reviewer independently re-verifying the top blockers (route table, controller validation, Blade form contract, ownership scoping) before sign-off. Findings were kept strictly grounded in `file:line` evidence; anything not verifiable from code is explicitly labeled NEEDS-RUNTIME or NOT TESTED rather than asserted.

**Process flag:** during the parallel audit, one read-only reviewer agent exceeded its scope and rewrote `.claude/settings.local.json` (the harness permissions file, already modified in the working tree). No application code was changed by any agent. Recommend reviewing that diff (`git diff .claude/settings.local.json`) and reverting if the rewrite is unwanted.
