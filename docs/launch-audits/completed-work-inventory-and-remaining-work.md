# Launch Audits — Completed-Work Inventory & Remaining Work

**Date:** 2026-06-26
**Purpose:** Single source of truth for what has been *implemented* across the four launch-audit workstreams (BidYourAgent, BidYourOffer, Location DNA, Census Intelligence), what remains, and how the current **uncommitted** working tree maps to that work. Built by reconciling the seven audit/remediation documents against the actual code in the tree (every "Done" item below was verified by reading the modified file, not inferred from the reports).

**State at time of writing:** branch `main`, last commit `b9416e999`. All remediation is **uncommitted** (46 modified, 32 untracked, 0 staged; +1,809 / −613). Nothing below has been committed yet — see the companion **commit plan** (§6) before any `git add`.

**Legend:** ✅ Done (verified in tree) · 🟡 Partial · ⬜ Not started · 🔒 Gated (needs owner/legal decision) · ↩︎ Reclassified (no code needed)

---

## 1. BidYourAgent

Source: `bidyouragent-launch-certification.md` (41/100, ❌ not approved), `bidyouragent-phase1-remediation.md`, `bidyouragent-phase1-summary.md`. Readiness after Phase 1: **~55%**.

### Phase 1 — Authorization & Security ✅ (verified in tree)

| ID | Item | Status | Evidence in tree |
|---|---|---|---|
| CRIT-1 | Listing-edit horizontal IDOR (all 4 roles) | ✅ Fixed | `TenantAgentAuctionEdit.php` owner-scoped load/save |
| CRIT-2 | Unauthenticated auction termination | ✅ Fixed (2 live) / ↩︎ (1 dead) | `auth` + owner guard in `PropertyAuctionController`, `LandlordAgentAuctionController`; `routes/web.php` |
| CRIT-3 | Unauth `viewBid` PII leak | ↩︎ Reclassified — dead table (500, no data) | `auth` added for surface reduction |
| CRIT-4 | Public counter-bid write | ↩︎ Reclassified — missing columns (500 before write) | `auth` added |
| CRIT-5 | `destroyCounter` hard-delete IDOR | ✅ Fixed | Party guard in `SellerCounterBidController.php` |
| HIGH-1 | Inverted `AgentAuth` role gate | ✅ Fixed | `AgentAuth.php` allowlists `user_type==='agent'` |
| HIGH-7 | Unauth `renew_save` (date tampering) | ✅ Fixed (3 live) / ↩︎ (1 dead) | Owner guards in Property/Buyer/Tenant criteria controllers |
| — | Route-auth consistency sweep | ✅ Done | remaining sensitive routes wrapped in `auth` |
| HIGH-5 | Legacy `*CounteredTermsController` IDOR (6 controllers) | ✅ Hardened *(was the one open Phase-1 item)* | Party guards in `CounteredTerms.php` + Seller/Buyer/Landlord/Tenant CounteredTermsController; new `CounteredTermsAuthorizationTest.php` |

**Tests:** `tests/Feature/Security/Phase1AuthorizationTest.php` (reported 12 passed / 3 skipped), `tests/Feature/Security/CounteredTermsAuthorizationTest.php` (new).
**Caveat:** 3 ownership tests auto-skip — workspace harness resolves to live pgsql instead of isolated SQLite (pre-existing, affects the wider suite). CI-ready against SQLite.

### Remaining BidYourAgent work ⬜

- **Phase 2 — Data integrity:** CRIT-6 (notification "View" 500 — `view-counter` route names don't exist), HIGH-4 (consumer shown stale `AgentDefaultProfile` instead of submitted bid meta), HIGH-6 (submitted offers never expire — deadline written to meta, native `expires_at` stays NULL), HIGH-11 (`BuyerCounteredTermsController` writes non-existent column).
- **Phase 3 — Marketplace integrity:** HIGH-2 (no "can't bid on own listing" guard), HIGH-3 (duplicate bids — only Landlord prevents; no unique index), HIGH-8/9/10 (dual create/edit components → required-field & compatibility-validation parity drift), MED-7 (`storage_space` silently dropped).
- **Phase 4 — Tech debt:** remove dead `LandlordAuction`/`LandlordAuctionBid` subsystem + broken legacy counter `store()` methods (CRIT-3/4 reclassified targets), MED-19 formal policy layer, MED-20/21 `$fillable`/FK hardening, duplicate route names (MED-17/18), notification coverage gaps (HIGH-13/14, MED-16).
- **Phase 5 — Browser certification:** per-role × per-property-type walkthrough; runtime 404/500; Livewire hydration; queue/broadcast/websocket; upload mime/size (LOW-3).
- **Open decision:** formal `OfferListingPolicy`/`AuctionPolicy` vs. the inline-guard + trait approach actually used. Phase 1 deliberately used inline guards (smallest-safe); a consolidated policy layer remains recommended but optional.

---

## 2. BidYourOffer

Source: `bidyouroffer-launch-certification.md` (52/100, ❌ not certified), `bidyouroffer-remediation-plan.md` (6-phase plan). Note the cert's scope clarification: **Offer-Listing stack** ≠ **Stellar IDX/MLS stack** — the brief conflated them.

### Phase 1 — Critical security ✅ (verified in tree)

| ID | Item | Status | Evidence in tree |
|---|---|---|---|
| C1 | Offer-listing IDOR — edit & create write/load paths (all 4 roles) | ✅ Fixed | `ResolvesOwnedAuction` concern wired into all 4 `*OfferListingEdit` + all 4 `*OfferListing`; owner-scope refs confirmed in each; new `tests/Feature/Offers/OfferListingAuthorizationTest.php` |
| C2 | Unauth IDOR + restricted-field leak on `/ask-ai/listing-question` | 🟡 Mostly | Route now `['auth','throttle:ask-ai-api']`; controller enforces `ownsListing(Auth::id(),…)`. **Remaining:** RESTRICTED-key stripping at context assembly on KB-miss path still needs runtime probe |
| C3 | Ask-AI widget non-functional (Blade↔controller field mismatch) | ✅ Fixed (reworked) | Widget redesigned around the C2 owns-listing model: `matchmaker-ask-ai.blade.php:40-41` posts `listing_type={criteriaType}`/`listing_id={criteriaId}`; `detail.blade.php:192-196` passes `:criteria-id`/`:criteria-type`; renders only when a criteria context is present. **Remaining:** JS fail-safe on non-2xx (M15) and an end-to-end runtime smoke test |

**Implementation note:** C1 was closed with the **`ResolvesOwnedAuction` trait**, not the `OfferListingPolicy` the plan proposed. Only `ShowingPolicy` exists in `app/Policies/`. If a formal policy is still wanted, it is an open follow-up (not a blocker — the IDOR is closed). C2 + C3 were solved together: the Ask-AI engine now serves **only the requester's own** offer-listing/criteria (no public MLS), so the widget posts the consumer's own criteria context and the endpoint is auth + owner-scoped.

**Owner-only vs assigned-agent — evaluated, safe for launch.** The trait guard is owner-only (Seller/Landlord pass `null` for the assigned-agent seam). Verified this blocks **no live workflow**: agents edit only listings they own (the agent hub queries `where('user_id', Auth::id())`), the two-persona model means agents don't write consumer listings here, and the pre-existing Seller/Landlord `$isAssigned` branch only ever gated the read-only Location DNA panel. Full evaluation, the smallest-safe future change, and the long-term **Listing Participants / Shared Listing Management** direction are documented in **`listing-authorization-model.md`**. No change before launch.

### Remaining BidYourOffer work

- **Phase 2 — Data integrity:** ⬜ C4 (Seller create silently drops broker-comp + ~47 financing/lease fields; Edit saves them — run the create-vs-edit `saveMeta` diff for all 4 roles; `scratch/{ll,t}_{create,edit}.txt` hold partial diffs), M12 (Buyer `other_business_type` create-loss), M11 (draft-hash omits persisted fields). *(C3 widget contract already reworked — see Phase 1 table; only the JS fail-safe (M15) + runtime smoke test remain.)*
- **Phase 3 — Validation & parity:** ⬜ H1 (Edit validation far weaker than Create — Seller Edit validates 1 field), H2 (Landlord/Seller waterfront props undeclared in Edit → Livewire error + data loss), H3 (Buyer multiselect submit — runtime-verify).
- **Phase 4 — UX & consistency:** ⬜ M4 (always-on "coming soon" cards: commute/appreciation/flood-zone), M5 (flood-zone computed but never surfaced — liability-sensitive), M7 (vacant land mislabeled "Traditional Residential"), M1/M2/M3 (negative-amenity mis-scoring, neutral "why", 94/100 residential ceiling), M6/L4 (non-residential display, lot-size sqft fallback), M15 (JS fail-safe).
- **Phase 5 — Performance & debt:** ⬜ H6 (geocode no timeout), H7 (silent Google POI failures), H8 (error-empty cached 24h), M9 (POI delete→insert concurrency guard), M10/H9 (admin OpenAI: queue, max_tokens, system prompt — **gate must stay hard**), M16–M20, per-request Bridge import → background, L1/L2/L5/L8 cleanups + stale CLAUDE.md. *(Note: H8 and M17 tile-cache overlap with Location DNA Phase 1 work already done — see §3.)*
- **Phase 6 — Browser verification:** ⬜ per role × property type; IDOR attempts blocked; console/500/404; Maps-key referrer restriction (M8); city-normalization runtime check (H5/H4 Tenant rental-vs-sale).
- **Matching correctness (cross-phase):** ⬜ H4 (Tenant rentals vs sales collision), H5 (one-sided city normalization).

---

## 3. Location DNA

Source: `location-dna-audit.md` (5-phase plan), `location-dna-architecture-review.md` (pre–Phase 2 validation).

### Phase 1 — High-value bug fixes ✅ (all six verified in tree)

| Item | Status | Evidence in tree |
|---|---|---|
| `'bridge'` listing type produced no LDNA | ✅ Fixed | `'bridge'` branch added in `LocationDnaPipelineRunner.php:178` |
| Agent-AI `geocode_status` filter never matched (`'success'`→`'geocoded'`) | ✅ Fixed | `ExtendedKnowledgeLoader.php:239`; new `ExtendedKnowledgeLoaderTest.php` |
| Ask-AI lifestyle keys always null (key mismatch) | ✅ Fixed | `AskAiContextBuilderService.php:2112` reads `lifestyle_categories`/`location_narrative`; `AskAiContextBuilderServiceTest.php` |
| Tile cache on per-process `array` store | ✅ Fixed | `LocationDnaPoiTileCache.php` uses `Cache::store($storeName)` + flush; `LocationDnaPoiTileCacheTest.php` |
| Missing exclusion rules (marina/boat_ramp boat-dealer; animal hospital ⊄ hospital) | ✅ Fixed | rules in `LocationDnaPoiDistanceService.php:211,295`; `CategoryExclusionRulesRegressionTest.php` |
| Stop caching adapter errors (`PoiDistanceLookupService`) | ⬜ Not done | `PoiDistanceLookupService.php:104` still `Cache::put`s the error result inside the `catch` for full TTL — overlaps BYO H8 |

Also modified: `config/location_dna.php`, `LdnaBenchmarkTilePrecision.php`, `LocationDnaPipelineTriggerTest.php`.

### Remaining Location DNA work ⬜ / 🔒

- **Phase 2 — Pipeline correctness:** rules-version stamp (`fetch_version` vs `scoring_version`) + recompute-from-cache rerank path + `ldna:reconcile`/`--from-cache`; fix double-counted confidence in `LocationDnaRankingEngine`; absolute-vs-relative distance (product call). **Architecture review validates this design — proceed as designed.**
- **Phase 3 — Category completeness:** add urgent-care + airports as first-class categories; promote hidden outdoor/recreation score; **unify the two POI subsystems** (~4–6 eng-weeks, post-launch).
- **Phase 4 — Premium consumer UI:** reuse agent-panel narrative/lifestyle-bars/top-3-per-category/top-rated-dining on Stellar; real interactive map; fill/remove the 3 dead placeholder cards (overlaps BYO M4/M5). *(The new untracked Stellar `matchmaker-*` components are the in-flight start of this — see §5.)*
- **Phase 5 — Match-score integration:** feed LDNA quality into Buyer/Tenant match scores; stand up a tenant scorer; weights must sum to 100 (`config/match_scoring.php`); coordinate with kill-switch/GA owner.
- **Sequencing (from architecture review):** consumer-presentation first if launch is near; rules-versioning is the right next *engineering* investment; defer unification + match-score integration.

---

## 4. Census Intelligence 🔒

Source: `CENSUS_INTELLIGENCE_PHASE_5_3_GOVERNANCE_AND_ARCHITECTURE_PLAN.md` — **planning/governance only; no production code under this phase.**

- **Status:** ⬜ / 🔒 — nothing built, nothing to build until the §10.1 governance conflict is resolved. Census demographics directly contradict Location DNA Phase A "Prohibited Inputs" (FHA proxy risk); must be a **separate, independently-governed module**.
- **Blocking decisions (owner/legal):** approve a separate neutrally-governed module; confirm Match-Score integration **excluded** (recommended); decline demographic Target-Market-DNA or approve housing-stock-only re-scope; keep/drop "family households %".
- **When approved:** phased build 5.3.A–H (governance close-out → config/contracts → data layer → fetch/resolve → neutral-language guardrails → UI behind flag → Ask-AI behind flag → refresh ops). Kill switch ships ON (feature OFF). Match-Score/Target-Market integration is gated 5.3.X — recommended **do not build**.

---

## 5. Stellar consumer UI (in-flight feature work, pre-audit)

Not a remediation workstream per se — this is the IDX/MLS property-detail + buyer-matchmaker feature that was already mid-development when the audits ran (the certs reference it as the "uncommitted Stellar work"). It is the practical vehicle for Location DNA Phase 4 / BidYourOffer Phase 4 consumer-UI items.

- **Untracked (new):** 14 `matchmaker-*` + 12 `property-*` Blade components under `resources/views/components/stellar/`; `app/Services/Stellar/PropertyMatchContextService.php`.
- **Modified:** `StellarPropertyDetailController.php`, `BuyerResultViewMapper.php`, `PropertyDetailViewMapper.php`, `resources/views/components/stellar/buyer-result-card.blade.php`, `resources/views/stellar/property/detail.blade.php`.
- **Known open items against this surface:** C3 (Ask-AI widget contract), M4/M5 (placeholder cards, flood surfacing), M7 (land mislabel) — all listed under their owning workstreams above.

---

## 6. Working-tree → workstream map (for the commit plan)

Several files are touched by **multiple** workstreams — most importantly `routes/web.php` (BYA Phase 1 auth routes **and** BYO C1 edit routes **and** BYO C2 ask-ai route). These must be split with `git add -p` if per-workstream commits are desired. Full grouping lives in the companion commit plan.

| Workstream | Key files |
|---|---|
| A — BYA Phase 1 auth | `AgentAuth.php`, `TenantAgentAuctionEdit.php`, `PropertyAuctionController.php`, `LandlordAgentAuctionController.php`, `SellerCounterBidController.php`, `BuyerCriteriaAuctionBidController.php`, `TenantCriteriaAuctionController.php`, `routes/web.php`*, `Security/Phase1AuthorizationTest.php` |
| B — BYA HIGH-5 CounteredTerms | `CounteredTerms.php`, `{Seller,Buyer,Landlord,Tenant}CounteredTermsController.php`, `Security/CounteredTermsAuthorizationTest.php` |
| C — BYO C1 offer-listing IDOR | `Concerns/ResolvesOwnedAuction.php`, 4 `*OfferListingEdit.php`, 4 `*OfferListing.php`, `routes/web.php`*, `Offers/OfferListingAuthorizationTest.php` |
| D — BYO C2 Ask-AI auth | `AskAiListingQuestionController.php`, `routes/web.php`*, `AskAi*Test.php` (5 feature tests) |
| E — Location DNA Phase 1 | `LocationDnaPipelineRunner.php`, `LocationDnaPoiDistanceService.php`, `LocationDnaPoiTileCache.php`, `ExtendedKnowledgeLoader.php`, `AskAiContextBuilderService.php`, `config/location_dna.php`, `LdnaBenchmarkTilePrecision.php`, + 4 LDNA/AskAi unit tests |
| F — Stellar consumer UI | 26 new stellar components, `PropertyMatchContextService.php`, `StellarPropertyDetailController.php`, `BuyerResultViewMapper.php`, `PropertyDetailViewMapper.php`, `buyer-result-card.blade.php`, `stellar/property/detail.blade.php` |
| G — Documentation | `docs/launch-audits/*` (8 docs incl. this one), `docs/CENSUS_INTELLIGENCE_PHASE_5_3...md` |
| H — Do NOT commit / review | `scratch/` (working diffs — ignore), `.claude/settings.local.json` (**rewritten by an audit agent — review/revert**), `.replit` (env — review) |

\* `routes/web.php` is shared by A, C, D — split with `git add -p`.

---

## 7. Cross-cutting status

- **Nothing is committed.** All Phase-1 security work for both products + all LDNA Phase 1 + the Stellar feature sit uncommitted on `main`. Highest near-term risk = losing this work; commit before further remediation.
- **Test harness:** resolves to live pgsql in this workspace; isolated-SQLite ownership tests auto-skip. Pre-existing; recommend fixing so CI runs the full suite.
- **`.claude/settings.local.json`:** flagged in the BidYourOffer cert as rewritten by an out-of-scope audit agent. Review `git diff` and revert if unwanted before any commit.
- **Browser certification** outstanding for both products (no Playwright in this environment).
```
