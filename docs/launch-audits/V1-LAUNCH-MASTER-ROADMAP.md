# V1 Launch — Master Roadmap (Single Source of Truth)

**Phase:** 0 — Launch Governance (READ-ONLY). **No implementation has begun.**
**Date:** 2026-06-27 · **Branch:** `launch-audit-remediation`
**Authoritative backlog:** `LAUNCH-RECONCILIATION-AND-BACKLOG.md` (Engineering §2–§8 + Workflow Certification §9–§11). This roadmap **does not add product scope** — it sequences the close-out of already-verified findings. New items appear only where a genuine security / data-integrity / launch-blocking-workflow issue was discovered during verification (all such items are tagged `NEW` and carry `file:line` evidence).

**Tiering test (launch-confidence lens):** an item is **Required Before V1** only if it *realistically prevents a successful public launch* (security hole, data loss, or a broken core business workflow) **and** the platform *cannot safely launch without it*. Everything else is **Recommended** (ship-quality, not blocking) or **Post-Launch**. Each Required item is scoped to its **smallest complete fix** (Q4) with scope-creep explicitly fenced off (Q5).

**Status legend:** ⬜ Not Started · 🟨 In Progress · 🟦 In QA · 🟥 Blocked · 🟩 Complete

---

## 1. Executive Summary

Both products have a **healthy, well-built core** and a **closed security perimeter for the highest-severity IDOR cluster** (CRIT-1/2/5/6, C1, C3, HIGH-1/7, plus 5 of 6 CounteredTerms controllers — all verified in committed code). The match engine, the modern Offer state machine, EAV round-trips, accepted-summary signature gating, and Location-DNA math are verified sound.

They are **not yet production-ready** because the workflow certification proved that several **core business workflows break end-to-end even where individual audit items are "closed"**:

- **Negotiation loop is broken** — submitted offers **never expire** (BYA-H6), so accept/counter operate on stale offers and the expiry safety-valve never fires.
- **Ask AI is broken for every consumer** — returns **403 on each user's own listing** (WF-1, closed-but-broken C2/C3 token mismatch).
- ~~**Listing management is incomplete** — no user can **delete or unpublish their own published listing** (WF-2), in either product, any role.~~ **RESOLVED (WF-2 🟩, Pass M-B):** owners can now reversibly archive/republish any of their published listings; archived listings leave every public surface (search + detail + author profile) while bids/history are preserved.
- **Silent data loss / integrity holes on create & edit** — Seller broker-comp dropped (BYO-C4); edit can **publish with required fields blank** (BYO-H1); waterfront edit **crashes Livewire** (BYO-H2).
- **Residual security gaps** — unscoped draft-meta deletion enabling **cross-user data destruction** (WF-3, NEW — now 🟩 closed), draft-listing view leak (WF-4 — now 🟩 closed), legacy-route IDOR (WF-6, NEW — now 🟩 closed). *(N1 — the unguarded `AgentCounteredTermsController` — was investigated and confirmed **dead/unreachable**: no inbound link, no backing table; reclassified Post-Launch dead-code per §8.8, re-confirmed 2026-06-28. Not a live gap.)*
- **Marketplace + matching correctness** — no self-bid / duplicate-bid guards (BYA-H2/H3), consumers shown **stale agent data** (BYA-H4), tenant searches matched against **for-sale** inventory (BYO-H4).

**The good news:** the Required list is **finite, verified, and mostly low-effort** — it is concentrated remediation, not redesign. The dominant scope-creep traps (consolidating the dual create/edit components, a formal policy layer, the Listing-Participants model, full LDNA robustness) are correctly **deferred Post-Launch** because the smaller targeted fixes fully close the launch-blocking behavior.

**Readiness:** BidYourAgent **≈ 58%**, BidYourOffer **≈ 55%** (see §6). Estimated **~3 weeks** to a confidently launchable state (Required + final certification); **~4–5 weeks** including the Recommended quality tier.

---

## 2. Required Before V1 (launch blockers)

> Every item below answers **Q1 = Yes** (prevents successful launch) and **Q2 = No** (cannot safely launch without it). `Br?` = browser/runtime verification required.

### 2A. Security & Access Control

| ID | Status | Finding | Sev | Effort | Regression | Evidence | Files/components | QA | Br? |
|---|---|---|---|---|---|---|---|---|---|
| ~~N1~~ | ↩︎ | **RECLASSIFIED → Post-Launch (dead-code removal)** — `agent_counter_terms` table does not exist, no UI entry point, no consumer; store/update 500 before any write. See §4 + §8.8. | — | — | — | — | — | — | — |
| WF-6 `NEW` | 🟩 | Legacy `bidsVisibility()` lacks ownership checks (was reachable with **only `web` middleware — no auth**). **CLOSED (Pass M-A).** | Med-High (sec) | S | Low | `web.php:830-834` (only `web`); methods on `property_auctions`(0)/`tenant_criteria_auctions`(0)/`buyer_criteria_auctions`(0)/`landlord_agent_auctions`(**67 rows, live IDOR**) | owner guard added to 4 live methods + `auth` on 5 routes; `831` (`landlord_auctions`) dead → auth-only | route-auth test passes; DB-backed ownership test CI-ready (auto-skips in this harness) | No |
| WF-3 `NEW` | 🟩 | Unscoped `deleteDraft()` meta deletion → cross-user data destruction. **CLOSED (Pass M-B).** Found in **all 8** `deleteDraft()` (not 2) + BYA Buyer wrong-table bug (`tenant_…`→`buyer_…`). | High (sec) | S | Low | ownership gate added to all 8 `deleteDraft()` (BYA+BYO, 4 roles) | `SellerAgentAuction`, `BuyerAgentAuction`, `LandLordAgentAuction`, `TenantAgentAuction` + 4 `*OfferListing` | **DB-backed Livewire test PASSES** (`test_wf3_non_owner_cannot_delete_another_users_draft_meta` — non-owner blocked, owner allowed) | No |
| WF-4 | 🟩 | Public `view()` leaks draft/pending listings (= MED-23). **CLOSED (Pass M-B, owner-aware extension 2026-06-28).** Implemented across **all 8** detail actions (not just Landlord/Tenant) — bundled with the WF-2 archive guard: non-owner gets `abort(404)` when `!is_approved` or `is_draft`; owner may preview own. | Med-High (priv) | S | Low | owner-only draft/pending guard on all 8 by-ID detail actions (4 hire + 4 offer) | `test_wf4_draft_or_pending_listing_detail_is_hidden_from_non_owners` (DB-backed; CI-ready, auto-skips this harness) | No |
| BYO-C2s | ⬜ | Ask-AI restricted-key stripping at context assembly (KB-miss path) | High (priv) | S-M | Low | `SnapshotFactVisibility.php` unused by runner; `AskAiRunnerV2Service`/`AskAiResponseContractService` | strip RESTRICTED keys at context assembly | regression: KB-miss restricted question returns no restricted field | **Yes** (runtime probe) |

> **WF-6 note:** scoped to `bidsVisibility` per §8.1. The controller `update()` methods cited in the original evidence were **not** confirmed reachable; the live, reachable IDOR was `bidsVisibility` (closed). The original `view()`-leak portion is tracked separately as **WF-4**.

**Q4/Q5 notes:** all five are *additive guards / filters at the exact site* — no redesign. Do **not** expand into a formal policy layer (MED-19) here; that is Post-Launch. WF-6: if a route is confirmed dead, neutralize rather than guard.

### 2B. Data Integrity

| ID | Finding | Sev | Effort | Regression | Evidence | Files/components | QA | Br? |
|---|---|---|---|---|---|---|---|---|
| BYO-C4 🟩 | Seller Create silently drops broker-comp `commission_structure_type` group. **CLOSED (Phase B, 2026-06-29).** The 6 `commission_structure_type*` fields were validated on submit but never saved/loaded/drafted on Create (only Edit saved them). Added all 6 to Create `saveAllMetadata` + `buildDraftPayload` + load, mirroring Edit. Tenant already correct; Buyer/Landlord have no such group. | Critical | S-M | Low | `SellerOfferListing.php` saveAllMetadata/buildDraftPayload/load (6 fields each) | `CreateEditParityRegressionTest` + `SellerMlsFieldRoundTripTest`/`SellerIncomeFieldRoundTripTest` green | No |
| BYA-H6 | Submitted offers never auto-expire (native `expires_at` never written at submit) | High | S | Low | `OfferController.php:1257` meta-only; `ExpireOffersCommand.php:17` filters native NULL | `OfferController::persistTermsMeta`/submit | unit: submit sets native `expires_at`; command expires it | No |
| ~~BYA-H11~~ | **RECLASSIFIED → Post-Launch (dead path)** per §8.2 — Buyer `update-counter-terms` has no route; the SQL-500 path is unreachable. *(Was the 17→16 reduction.)* | — | — | — | — | — | — | — |
| BYO-H1 🟩 | Edit submit validation ≪ Create → publish with required fields blank. **CLOSED (Phase B, 2026-06-29).** Extracted each role's full Create publish-rules into shared concerns (`SellerPublishValidation`, `LandlordPublishValidation`) used by **both** create and edit — single source, no duplication. Edit `update()` (publish path) now enforces identical rules; drafts (`saveDraft`/`saveDraftOnly`) stay lenient. Buyer Edit already matched Create (no change). Frozen `initializeLimitedService()` untouched. | High | M | Med | new `OfferListing/Concerns/{Seller,Landlord}PublishValidation.php`; Seller/Landlord create+edit | unit: blank-required rejected on publish, valid passes, **create==edit rule sets identical** (verified); `CreateEditParityRegressionTest` green | **Yes** |
| BYO-H2 🟩 | Seller/Landlord waterfront props undeclared in Edit → Livewire crash + data loss. **CLOSED (Phase B, 2026-06-29).** Seller Edit fixed earlier (commit `eb2eed4f3`); this closes the remaining **Landlord Edit** gap — added `water_frontage`/`waterfront_feet` to declarations + buildDraftPayload + loadAuctionData + saveAllMetadata, mirroring Landlord Create. Buyer/Tenant N/A (no waterfront). | High | S | Low | `LandlordOfferListingEdit.php` (4 layers) | round-trip: edit waterfront without crash, persists | **Yes** |

**Q5 fence:** BYO-H1 must **not** trigger the dual-component consolidation (HIGH-8) — extract-and-reuse the rule set only. Frozen `initializeLimitedService()` untouched.

### 2C. Core Workflow Restoration

| ID | Finding | Sev | Effort | Regression | Evidence | Files/components | QA | Br? |
|---|---|---|---|---|---|---|---|---|
| WF-1 `NEW` | Ask AI returns 403 for **every** consumer (token map mismatch; closed-but-broken C2/C3) | High | S | Low | `AskAiListingQuestionController.php:22-35,257-272` vs widget tokens `matchmaker-ask-ai.blade.php:40,90` | add `buyer_offer→buyer_agent_auctions`, `tenant_offer→tenant_agent_auctions` aliases to `OWNER_TABLES`; gate/hide unsupported legacy tokens | browser: consumer asks about own match → grounded answer (not 403) | **Yes** |
| WF-2 `NEW` 🟩 | **CLOSED (Pass M-B, owner-approved 2026-06-28).** Reversible **archive/republish** (owner decision: `is_archived` flag). Every public surface is archive-aware: 9 discovery gates + **all 8 by-ID/detail/view actions** (4 hire-agent + 4 offer-listing) carry an owner-aware `abort(404)` guard, and the tenant author/profile multi-tab (5 tabs) is gated. Owner still sees archived everywhere they should (My Listings, own detail page). Bids/summaries/history preserved; republish restores all surfaces. | High | M | Med | `is_archived` migration (4 tables); `DashboardController::setListingArchived` (owner-scoped `firstOrFail`); route `my.listings.archive` (auth); 9 discovery gates `where('is_archived',0)`; 8 detail-action owner-aware archive guards; tenant author multi-tab gated; my.listings Unpublish/Republish + Archived badge | DashboardController, routes/web.php, myListings.blade.php, 8 search controllers, 8 detail/view actions, UserController | route-auth test PASSES; 2 DB-backed WF-2 tests CI-ready (archive-route owner-scope + archived-detail-hidden); security suite 15 pass / 0 fail. **Browser cert** of archive→hidden + republish→visible recommended as final Phase G smoke (no code gap remains) | **Yes** |
| BYA-H6 | *(see 2B — expiration is the negotiation-loop blocker)* | — | — | — | — | — | — | — |
| BYA-H2 | No "cannot bid on own listing" guard (all 4 roles) | High | S | Low | mount/submit no owner≠actor check, e.g. `SellerAgentAuctionBid.php:980` | self-bid guard in 4 bid components' submit | unit per role: own-listing bid rejected | No |
| BYA-H3 | Duplicate bids allowed (Buyer/Seller/Tenant); no unique index | High | M | Med | `BuyerAgentAuctionBid.php:1054`, Seller `:991`, Tenant `:1310`; Landlord `:1458` guards | existing-bid check (mirror Landlord) + unique `(user_id,auction_id)` migration | unit: 2nd bid updates not inserts; **pre-check existing duplicates before unique index** | No |
| BYA-H4 | Consumers shown stale `AgentDefaultProfile` instead of submitted bid meta | High | M | Med | `partials/bid_detail_body/buyer.blade.php:301`, seller `:249`, landlord `:286` | source Agent Highlights from submitted bid meta (3 partials) | browser: highlights reflect the submitted bid values | **Yes** |

### 2D. Matching & Discovery Correctness

| ID | Finding | Sev | Effort | Regression | Evidence | Files/components | QA | Br? |
|---|---|---|---|---|---|---|---|---|
| BYO-H4 | Tenant matching uses for-sale PropertyType + nulls rent ceiling (Tenant discovery IS live) | High | M | Med | `TenantOfferListingCriteriaLoader.php:131,178` | rental PropertyType + rent ceiling; verify Commercial-Lease path unaffected | browser: tenant results are rentals with sane prices | **Yes** |

### 2E. Launch Configuration

| ID | Finding | Sev | Effort | Regression | Evidence | Files/components | QA | Br? |
|---|---|---|---|---|---|---|---|---|
| BYO-L2 | `SAVE_AS_NEW_DRAFT = true` ("set false before launch" TODO) in all 4 create components | Med (config) | XS | Med | `SellerOfferListing.php:27`, Buyer `:26`, Landlord `:26`, Tenant `:34` | flip to `false` **after** C1 IDOR tests green (they are) | regression: resume-draft overwrites rather than orphan-creates; owner guard holds | **Yes** |

---

## 3. Recommended Before V1 (ship-quality; not launch-blocking)

> Q1 = No (won't stop a successful launch) but meaningfully improves trust/UX. Defer only if timeline forces it; each leaves a stated user impact.

| ID | Finding | Sev | Effort | Deferred-impact (Q3) |
|---|---|---|---|---|
| BYA-H13 | "Counter Rejected" notification never dispatched | Med-High | S | Rejected party not told; negotiation stalls (UI still shows status) |
| BYA-H14 | No "listing published/approved" notification (4 roles, both products) | Med | S | User unsure listing went live; cosmetic |
| BYA-H15 | Bid-Updated notification: Seller none / Buyer wrong type | Med | S | Owner mis/under-notified on bid edits |
| BYA-H16 | Offer-Withdrawn notifies the withdrawer (self) not owner | Med | S | Counterparty unaware of withdrawal |
| BYA-H17 | Duplicate notification on Offer Counter | Med | XS | Minor noise (two notifications) |
| BYA-H18 | Agent-bid Withdraw only Tenant, no notification | Med | S | Other roles' agents cannot withdraw |
| WF-5 | Seller/Buyer compatibility prefs uneditable after create | Med-High | M | Users can't revise compatibility answers (data preserved, just not editable) |
| WF-7 / MED-24 | Silent locked fields on edit (no user message) | Low-Med | S | Confusing silent reverts on 3 fields |
| M12 | Buyer Create drops `other_business_type` (folds old H3 concern) | Med | S | Business-opportunity sub-type lost on create |
| M11 | Draft change-detection hash omits persisted fields | Med | S | "No changes detected" can skip a real save |
| BYO-H5 | One-sided city normalization may zero city matches | Med | S | Abbreviated-city searches under-match |
| BYO-H6 | LDNA geocode no timeout | Med | S | A hung geocode stalls a queue worker |
| BYO-H7 | Silent Google POI failures (no status/log) | Med | S | POI outages invisible to ops |
| BYO-H8 | POI errors cached empty for 24h (= LDNA item) | Med | S | A blip blanks POIs for a day |
| M4 | Permanent "coming soon" placeholder cards (commute/appreciation/flood) | Med | S | Looks unfinished on every property page |
| M5 | Flood zone computed but never surfaced | Med | S | Liability-relevant data hidden |
| M7 | Vacant land mislabeled "Traditional Residential" | Med | S | Wrong personality label on land |

---

## 4. Post-Launch (deferred — scope-creep fences)

These are real but **do not gate launch**; several are the explicit scope-creep traps fenced off by Q5.

- **HIGH-8 — consolidate dual create/edit components** (large refactor; targeted parity fixes in BYO-H1/H2 + WF-5 cover the launch-blocking behavior).
- **MED-19 — formal Auction/Bid/Counter policy layer** (inline guards already close the IDORs; policy is the durable follow-up).
- **MED-20/21 — `$fillable` hardening + FK constraints** (latent; no live exploit found).
- **MED-17/18 — duplicate/malformed route names.**
- **Dead-code removal** — `LandlordAuction`/`LandlordAuctionBid` subsystem (CRIT-3/4 reclassified targets), orphaned `*AgentAuctionEdit` components, broken legacy counter `store()` methods, **and `AgentCounteredTermsController` + `AgentCounterTerm` model + `agent_counter_terms` routes/views (`web.php:797-800`) — reclassified N1, no table/UI/consumer (§8.8); also `BYA-H11` (dead Buyer `update-counter-terms`) and the `landlord.auction.bids.visibility` dead-table route.**
- **HIGH-12 — PDF cache invalidation** (mitigated by terms-freeze at acceptance).
- **H9 / M10 — admin AI report grounding** (gated behind mandatory human approval — *gate must stay hard*).
- **M1/M2/M3 — match-scoring refinements** (negative-amenity, neutral "why", 94/100 residential ceiling).
- **M9/M16/M18/M20 — LDNA robustness** (concurrency guard, retries, POI dedup, school layers).
- **M8 — Maps key referrer restriction** (verify at deploy).
- **Census Intelligence** — gated; do not build (FHA-proxy governance unresolved).
- **Listing Participants / Shared Listing Management** — explicit long-term direction; not a launch task.
- **Test harness** — make CI resolve to isolated SQLite so the 3 auto-skipped ownership tests run.

---

## 5. Suggested implementation phases & sequencing

Dependency-ordered. Each phase ends with its tests green before the next begins.

| Phase | Theme | Items | Effort | Gate |
|---|---|---|---|---|
| **A** | Security & access-control close-out | ~~N1~~ (reclassified §8.8), **WF-6 🟩 done**, **WF-3 🟩 done**, **WF-4 🟩 done** | ~2–3 d | ✅ Phase A complete — all authz unit tests green; no unguarded mutation/read path remains |
| **B** | Data-integrity (create/edit/persist) | **BYO-C4 🟩, BYO-H2 🟩, BYO-H1 🟩 done** *(BYA-H11 → Post-Launch dead path)* | ~3–4 d | ✅ Phase B complete — Create↔Edit round-trip parity; no blank publish; no Livewire crash |
| **C** | Core workflow restoration | BYA-H6 (expire), WF-1 (Ask AI), **WF-2 🟩 done**, BYA-H2, BYA-H3, BYA-H4 | ~5–7 d | Negotiation loop closes; Ask AI answers; users manage own listings; marketplace integrity |
| **D** | Matching & discovery correctness | BYO-H4, BYO-C2s | ~2–3 d | Tenant results are rentals; Ask AI emits no restricted field on KB-miss |
| **E** | Launch configuration | BYO-L2 flip (+ re-run C1 IDOR tests) | ~0.5 d | Drafts resume correctly under `false`; ownership holds |
| **F** | Recommended quality tier | §3 items (notifications, WF-5/7, M-series, LDNA ops) | ~5–7 d | Optional; ship subset as timeline allows |
| **G** | **Final end-to-end certification** | Browser/mobile per role × property type; runtime probes (WF-1, BYO-C2s, H1/H2/H4, BYA-H4); Maps-key; queue/websocket delivery | ~3–4 d | The one final certification before launch |

**Critical path to launch:** A → B → C → D → E → **G**. Phase F (Recommended) runs in parallel or slots before G as time permits.

---

## 6. Estimated timeline & per-platform readiness

**Timeline (single focused engineer):**
- **Required only (A–E) + Certification (G):** **~3 weeks** to a confidently launchable state.
- **Including Recommended (F):** **~4–5 weeks** for a higher-polish launch.

These are remediation estimates (mostly S/M, concentrated patterns), not redesign.

### Launch-readiness %

| Platform | Readiness | What remains before production-ready |
|---|---|---|
| **BidYourAgent** | **≈ 64%** | Security perimeter is **closed** (CRIT-1/2/5/6, HIGH-1/7, 5/6 HIGH-5); **WF-6 🟩, WF-3 🟩, WF-2 🟩** (listing archive/unpublish — owners now manage their own listings). Remaining: the **negotiation loop is broken** (offers never expire, BYA-H6), **marketplace integrity** guards missing (self-bid/duplicate, H2/H3), consumers see **wrong agent data** (H4). Closing Phases A–C + G brings it to launch-ready. |
| **BidYourOffer** | **≈ 70%** | Foundations are **strong** (IDOR closed via `ResolvesOwnedAuction`, match engine injection-safe & fail-open, DNA deterministic/safe); **WF-3 🟩, WF-2 🟩** (listing archive/unpublish across all 4 offer roles); **Phase B 🟩** — **BYO-C4** (Seller broker-comp persistence), **BYO-H2** (Landlord waterfront edit), **BYO-H1** (create/edit publish validation parity) all closed. Remaining: **flagship Ask AI is broken for every consumer** (WF-1), **tenant matching hits for-sale inventory** (H4), **restricted-field stripping** unverified (C2s), and the `SAVE_AS_NEW_DRAFT` flag still on (L2). Closing Phases D–E + G brings it to launch-ready. |

Readiness rises to **≈ 90%+ each** on completion of Required (A–E); the final **10%** is earned in Phase G certification (browser/mobile/runtime), which is the only thing static analysis cannot certify.

---

## 7. Final certification gate (Phase G — the one pre-launch certification)

Run after every Required blocker is 🟩. Per **role × property type**, in a browser, mobile + desktop:
- Login → Create → Save Draft → Edit → Publish → View → Search/Match → Ask AI → Notifications → Counter/Accept/Decline → Expiration → Archive/Delete
- IDOR attempts blocked; no console errors / 500 / 404; Livewire hydration clean
- Ask AI returns grounded answers and **no restricted fields** on KB-miss (BYO-C2s probe)
- Location DNA + Property DNA render correctly per property type
- Maps key referrer-restricted (M8); offers expire on schedule (BYA-H6)
- Deployed `.env`: `APP_ENV=production`, `APP_DEBUG=false`

A workflow is **certified** only when all 13 stages pass end-to-end for that role × type. **No platform launches until its certification matrix is fully green.**

---

## 8. Final Validation Pass (pre-implementation)

Every Required item re-verified against the current tree (unchanged branch). Result: **16 Required** (was 17), with corrections below.

### 8.1 Re-verification result (all citations confirmed current)

| Item | Re-verified | Note |
|---|---|---|
| ~~N1~~ | ✅ store@28/update@50 are unguarded **but unreachable** — routes `web.php:799-802` (`agent.*-counter-terms`, behind `agentAuth`) have **zero inbound links**; the only refs to `agent.add/update-counter-terms` are the `<form action>` inside `agent_counter_terms/{add,edit}.blade.php`, which are rendered *only* by `AgentCounteredTermsController` itself (closed loop, no door in). `AgentCounterTerm`→`agent_counter_terms` table **has no migration** → store/update 500 before any write. Live counter-terms UI flows route to the four **role** controllers (Buyer/Landlord/Tenant/Seller), each with its own model/table/`*_counter_terms.add` view. | **→ Post-Launch dead code (§8.8); re-confirmed 2026-06-28** |
| WF-3 | ✅ `LandLordAgentAuction.php:2926-2928`, `TenantAgentAuction.php:5140-5142` meta-delete unscoped; parent delete owner-scoped | Required confirmed |
| WF-6 | ✅ `bidsVisibility` routed+authenticated `web.php:830-834`, no owner check | Required confirmed; **scoped to bidsVisibility IDOR** (low blast radius) |
| WF-4 | ✅ was: `view()` no status/auth gate (agent-verified) | Required (D3) → **🟩 CLOSED (Pass M-B)** — owner-only draft/pending guard on all 8 detail actions; CI-ready test added |
| BYO-C2s | ✅ `SnapshotFactVisibility` NOT referenced by runner/context (0 hits) → stripping unwired | Required; In QA |
| BYO-C4 | ✅ was: create `saveMeta('commission_structure_type')` = 0 hits; edit = 1 | Required → **🟩 CLOSED (Phase B)** — 6 fields now saved/loaded/drafted on Create, mirroring Edit |
| BYA-H6 | ✅ `OfferController.php:1257` meta-only; comment `:1253` claims dual-write but no native set at submit; cmd filters native `:19` | Required confirmed |
| BYO-H1 | ✅ was: Edit thin validation | Required → **🟩 CLOSED (Phase B)** — shared {Seller,Landlord}PublishValidation concerns; create==edit rules verified identical; drafts stay lenient |
| BYO-H2 | ✅ was: waterfront props create=8 / **edit=0** | Required → **🟩 CLOSED (Phase B)** — Seller Edit via `eb2eed4f3`; Landlord Edit added (4 layers) |
| WF-1 | ✅ **evidence corrected** (see 8.3) | Required confirmed (runtime probe) |
| WF-2 | ✅ was: no listing delete/unpublish/archive route (0 hits) | Required (D2) → **🟩 CLOSED (Pass M-B)** — owner-scoped archive/republish + archive-aware on all public surfaces |
| BYA-H2 | ✅ no self-bid guard (agent-verified) | Required confirmed |
| BYA-H3 | ✅ unconditional insert 3 roles; no unique index | Required confirmed |
| BYA-H4 | ✅ `AgentDefaultProfile` at buyer `:301`, seller `:249`, landlord `:286` | Required confirmed |
| BYO-H4 | ✅ `TenantOfferListingCriteriaLoader.php:131,178` (agent-verified) | Required (scope decision — D1) |
| BYO-L2 | ✅ all 4 `SAVE_AS_NEW_DRAFT = true` | Required confirmed |

### 8.2 Tier correction — **BYA-H11 → Post-Launch (dead path)**

`BuyerCounteredTermsController@update` (the non-existent-`tenant_auction_id` writer, `:96`) has **no route** — the Buyer counter-terms group registers only `add` (`web.php:635`) and a GET `edit` (`:637`); there is **no `update-counter-terms`** route (contrast Seller `:547`, Landlord `:672`, Tenant `:727`). The 500 path is unreachable. This confirms the original BYA cert's "No (dead path)" classification. **Removed from Required; moved to Post-Launch dead-code repair/removal.** Net Required: 17 → **16**.

### 8.3 Evidence correction — WF-1 (no status change)

The roadmap said "`OWNER_TABLES` doesn't map them." More precisely: `OWNER_TABLES` (`AskAiListingQuestionController.php:22-34`) **does** contain `buyer`/`tenant` (→ agent-auction tables) but **not** the modern `buyer_offer`/`tenant_offer` tokens; and `criteriaType` defaults to `'buyer'` (`StellarPropertyDetailController.php:53`). The failure is a **token+id-space mismatch**, not simply absent tokens: the modern preferred path posts `*_offer` (unmapped → 403); the legacy `buyer`/`tenant` path maps to the agent-auction table while its id is a *criteria*-auction id (wrong record → 403). **Smallest fix unchanged:** add `buyer_offer→buyer_agent_auctions` / `tenant_offer→tenant_agent_auctions` aliases; gate unsupported legacy tokens. Runtime probe confirms.

### 8.4 Merge opportunities (one implementation closes multiple findings)

| Merge | Items | Rationale |
|---|---|---|
| **M-A — Legacy controller authz** 🟩 **DONE** | ~~N1~~ (reclassified dead) + **WF-6 (closed)** | WF-6 `bidsVisibility` owner-guarded on 4 live controllers + `auth` on 5 routes. N1 dropped to Post-Launch dead-code cleanup (§8.8). |
| **M-B — Listing lifecycle management** | WF-3 + WF-2 | One owner-scoped delete/unpublish helper; WF-3 scopes draft-delete, WF-2 adds published delete |
| **M-C — OfferListing create persistence parity** | BYO-C4 (+ M12 *Rec*) | Single create-vs-edit `saveMeta` diff across all 4 roles |
| **M-D — OfferListing Edit parity** | BYO-H1 + BYO-H2 | Same 4 edit components: add validation + waterfront props together |
| **M-E — Bid submission integrity** | BYA-H2 + BYA-H3 | Same 4 bid components: self-bid + duplicate guard + unique index |
| **M-F — Ask AI restoration** | WF-1 + BYO-C2s | **Hard dependency:** C2s (restricted stripping) is only testable once WF-1 lets the request reach the runner |

### 8.5 Dependency graph

```
WF-1 ──▶ BYO-C2s            (runner reached only after 403 fixed; do together = M-F)
BYO-C4 ─┐
BYO-H1 ─┴▶ BYO-L2           (flip SAVE_AS_NEW_DRAFT last, after persistence+validation correct; C1 tests already green)
live-DB duplicate check ──▶ BYA-H3 (pre-clean existing dups before unique index)
ResolvesOwnedAuction + inline owner guards (exist) ──▶ WF-2
AgentBidMapperService meta coverage (verify) ──▶ BYA-H4
```
All other Required items are independent (parallelizable within their phase).

### 8.6 Conflicts — RESOLVED by owner (2026-06-27)

- **D1 — Tenant V1 scope → IN.** Tenant matching + workflows are in V1. **BYO-H4 stays Required.**
- **D2 — WF-2 tier → Required.** Users must manage (delete/unpublish/archive) their own listings before launch; no admin-only workaround.
- **D3 — WF-4 tier → Required.** Unpublished drafts are private user data; not viewable by unauthorized users.

**Required-16 list frozen by owner.**

### 8.8 Implementation-time discovery (Pass M-A) — **N1 reclassified to dead code (owner-approved)**

During M-A, an authoritative live-DB check reclassified **N1**, and the owner approved on 2026-06-27 (with the deeper reachability verification below):
- **`Schema::hasTable('agent_counter_terms')` = `false`** on the live `heliumdb` (control: `seller_counter_terms`/`buyer_counter_terms` = `true`).
- **No migration** creates `agent_counter_terms`.
- **`AgentCounterTerm` has zero consumers** outside its controller.
- **No UI entry point** — nothing links to the `agent.counter-terms`/`agent.edit-counter-terms` form-display routes (control: the Buyer equivalent has 4 links in `buyerAgentAuctionDetail.blade.php`). Routes behind `agentAuth` (agents only).
- `store()`/`update()` **500 before any read/write** — no IDOR possible.

**Disposition (approved):** N1 = dead member of the CounteredTerms family (same as CRIT-3/4). **Reclassified Required → Post-Launch (dead-code removal).** Do **not** build the table or revive the feature; do **not** guard dead code for V1. **Required: 16 → 15.**

**Re-verification 2026-06-28 (read-only, source-only — confirms the above):** static source check independent of any live DB. (1) **No inbound entry point** — repo-wide grep finds zero links/redirects to `agent.counter-terms`, `agent.add-counter-terms`, `agent.edit-counter-terms`, or `agent.update-counter-terms`; the only matches are the two `<form action>` attributes inside `agent_counter_terms/{add,edit}.blade.php`, which `AgentCounteredTermsController::add/edit` are the sole renderers of → self-referential closed loop. (2) **No backing table** — no migration creates `agent_counter_terms` (the `2024_08_19_…create_counter_terms_table` migration creates the unrelated `counter_terms`); `store()`/`update()` would SQL-500 before persisting. (3) **Live UI is isolated** — every counter-terms link in the blades targets the role-prefixed routes (`buyer./landlord./tenant./seller.edit-counter-terms`) mapping to the four role controllers, each with its own model + existing table + `*_counter_terms.add` view (these are the controllers party-guarded in HIGH-5). **Conclusion: unchanged — N1 stays Post-Launch dead code; nothing to guard for V1.**

### 8.7 Final tally

**Required: 15** (N1 reclassified) · **Recommended: 17** (+M12 rides M-C) · **Post-Launch:** §4 + BYA-H11 + N1. Merge-clusters M-A…M-F.
**Progress:** **WF-6 🟩 (M-A)**, **WF-3 🟩 (M-B)**, **WF-2 🟩 (M-B, owner-approved 2026-06-28)**, **WF-4 🟩 (M-B)**, **BYO-C4 🟩 / BYO-H2 🟩 / BYO-H1 🟩 (Phase B, 2026-06-29)** → **7 / 15 Required Complete.** Remaining Required: **8.** Phases A (security close-out) **and** B (data-integrity create/edit/persist) are now complete.

---

## Change log

- **2026-06-27 (1)** — Master Roadmap created from the authoritative backlog (Reconciliation §2–§8 + Workflow Certification §9–§11). Tiered via launch-confidence lens: 17 Required, 17 Recommended, Post-Launch deferred. Phases A–G; ~3 wk (Required+cert) / ~4–5 wk (incl. Recommended). Readiness: BYA ≈58%, BYO ≈55%.
- **2026-06-27 (2)** — Final validation pass (§8). All Required citations re-verified current. **BYA-H11 → Post-Launch (dead path — no route).** WF-1 evidence corrected (token+id-space mismatch). WF-6 scoped to `bidsVisibility`. 6 merge-clusters + dependency graph identified. **Required: 16.** 3 conflicts (D1 Tenant scope, D2 WF-2 tier, D3 WF-4 tier) pending owner decision. Awaiting approval before Phase A.
- **2026-06-27 (3)** — Owner approved roadmap. **D1 IN / D2 Required / D3 Required.** **N1 reclassified → Post-Launch dead code** (owner-approved after reachability verification, §8.8) → **Required: 15.** **Pass M-A implemented & CLOSED (WF-6):** owner guard added to `bidsVisibility` in `PropertyAuctionController`, `TenantCriteriaAuctionController`, `BuyerCriteriaAuctionController`, `LandlordAgentAuctionController`; `auth` middleware added to all 5 `bids-visibility` routes (were `web`-only); WF-6 regression tests added to `Phase1AuthorizationTest` (route-auth ✓ passes, DB-backed ownership CI-ready). No regressions (13 passed / 4 skipped). **Stopped for owner review before Pass M-B.**
- **2026-06-27 (4)** — **Pass M-B implemented (WF-3 🟩 + WF-2 🟦).** **WF-3 CLOSED:** found the unscoped-meta-delete in **all 8** `deleteDraft()` (not the 2 flagged) + a BYA Buyer wrong-meta-table copy-paste bug; added an ownership gate to all 8 and corrected the table — **validated by a passing DB-backed Livewire test**. **WF-2 In QA:** owner chose reversible archive (`is_archived` flag); implemented migration (4 tables) + owner-scoped `DashboardController::setListingArchived` + `my.listings.archive` route (auth) + my.listings Unpublish/Republish UI + Archived badge + 9 `where('is_archived',0)` discovery gates (8 search controllers + author profile). Route-auth test passes; owner-scope test CI-ready; **browser cert of the UI + end-to-end archive→hidden flow deferred to Phase G**. Residuals: by-id detail page folds into WF-4; Stellar own-criteria + tenant author multi-tab not gated (owner's-own / minor — noted). M-B: 21 files, +167/−9. Security suite 15 passed / 5 skipped, no regression. **Stopped for owner review before Pass M-C.**
- **2026-06-28 (2)** — **N1 discrepancy resolved (read-only verification).** Source-only re-verification confirmed `AgentCounteredTermsController` is **dead/unreachable** (zero inbound links to its `agent.*-counter-terms` routes; no migration for the `agent_counter_terms` table → store/update 500; all live counter-terms UI flows route to the four role controllers). Corrected the one stale conflicting entry (§8.1 N1 row said "Required confirmed / routes LIVE") and the overview residual-gaps bullet to match the standing §8.8 disposition. **N1 remains Post-Launch dead code; Required count unchanged at 12 remaining.** No application code modified.
- **2026-06-28 (1)** — **WF-2 extended & CLOSED 🟩 (owner-approved).** Closed the two M-B residuals: added an **owner-aware archive guard** (`abort(404)` when `is_archived` and viewer ≠ owner) to **all 8 by-ID/detail/view actions** (4 hire-agent: `TenantAgentAuctionController::view`, `LandlordAgentAuctionController::view`, `SellerAgentAuctionController::viewDetail`, `BuyerAgentAuctionController::viewAuctionDetails`; 4 offer-listing: `*OfferListingController::view`), and gated the **tenant author/profile multi-tab** (5 tabs in `UserController::author`, inside the existing `!$isOwner` block — owner still sees archived). Added CI-ready test `test_wf2_archived_listing_detail_is_hidden_from_non_owners`. Stellar matching confirmed owner/agent-client-scoped (no cross-user exposure); admin dashboards intentionally show archived (moderation). 11 files, +176/−4. Security suite **15 passed / 16 skipped / 0 failed**, no regression. **WF-2 is now archive-aware on every public surface; browser cert recommended as final Phase G smoke (no code gap remains).** Owner approved upgrade 🟦→🟩. **Stopped — awaiting approval before Pass M-C.**
- **2026-06-28 (3)** — **WF-4 🟩 CLOSED — doc reconciled to code (recovery audit).** Recovery audit of the interrupted session found the WF-4 draft/pending view-leak guard was already implemented in code (and tested) but the roadmap still tracked it ⬜. The owner-only guard (`abort(404)` when `!is_approved` or `is_draft` and viewer ≠ owner) ships on **all 8** by-ID detail actions — bundled with the WF-2 archive guard, not just the 2 originally-cited Landlord/Tenant `view()`s — with CI-ready test `test_wf4_draft_or_pending_listing_detail_is_hidden_from_non_owners`. Updated §2A WF-4 row, §8.1 verification row, Phase A gate (now ✅ complete), overview residual bullet, and BYA readiness note. **Required: 11 remaining (4/15 closed). Phase A complete.** No application code modified — documentation only. Security suite **15 passed / 7 skipped / 0 failed**.
- **2026-06-29 (1)** — **Phase B 🟩 COMPLETE — Seller/Landlord UI parity (BYO-C4 + BYO-H2 + BYO-H1).** Implemented as one parity pass. **BYO-C4:** Seller Create validated but never persisted the 6 `commission_structure_type*` broker-comp fields — added them to `saveAllMetadata` + `buildDraftPayload` + load, mirroring Edit (Tenant already correct; Buyer/Landlord have no such group). **BYO-H2:** Landlord Edit was missing `water_frontage`/`waterfront_feet` in all 4 layers (declare/draft/load/save) — added, mirroring Landlord Create (Seller Edit already fixed by `eb2eed4f3`). **BYO-H1:** extracted each role's full Create publish-rules into shared concerns `OfferListing/Concerns/{Seller,Landlord}PublishValidation` used by **both** create and edit (single source — no duplication); Edit `update()` now enforces identical required-field rules, drafts stay lenient (`saveDraft`/`saveDraftOnly`). Buyer Edit already matched Create. Frozen `initializeLimitedService()` untouched. **Verification:** behavioral test confirms blank-required rejected + valid passes + **create==edit rule sets byte-identical** for both roles; `CreateEditParityRegressionTest` 41/41 green (fixtures updated to seed required fields — published listings now realistic; the prior thin fixtures had passed the "no-dispatch" cases only vacuously). Full `tests/Feature/Offers/ + ListingImport/` = **53 failed / 415 passed vs 58/410 baseline (HEAD)** — net **−5 failures, +5 passes**; **zero** remaining failures in the offer-listing domain (the 53 are pre-existing offer-negotiation/env failures — Google Maps key, QueueFake/Mockery, DB — in untouched code). Change-scope guard (`OfferWorkflowReadinessTest`) allowlist updated for the Phase-B files. **Required: 8 remaining (7/15 closed). Phases A + B complete.** Next: Phase C (BYA-H6 expiry, WF-1 Ask AI, BYA-H2/H3/H4).
