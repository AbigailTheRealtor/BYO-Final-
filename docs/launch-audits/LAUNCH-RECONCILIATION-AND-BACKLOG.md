# Launch Governance — Audit Reconciliation & Consolidated Remediation Backlog

**Phase:** 0 — Launch Governance (READ-ONLY) · **Priority 1 deliverable**
**Date:** 2026-06-27 · **Branch:** `launch-audit-remediation`
**Status:** Audit reconciliation complete. V1 checklist evaluation (Priority 2–4) pending the itemized checklist.
**Method:** Every Critical/High finding in both launch certifications re-verified against **current code** (not trusted from the remediation docs) via parallel static re-verification with `file:line` evidence.

> This is a **living document**. It is the official source of truth for remediation status through public launch. Update the status column + the change log after each implementation phase. **No application code was modified to produce this document.**

**Status legend:** ⬜ Not Started · 🟨 In Progress · 🟦 In QA · 🟥 Blocked · 🟩 Complete
**Reconciliation legend:** ✅ CLOSED (fix verified in current code) · ↩︎ RECLASSIFIED (non-exploitable / dead code) · 🔴 OPEN (verified still unfixed) · 🟧 PARTIAL (some sub-parts done)

---

## 1. Baseline verification

All 8 declared baseline commits exist and are ancestors of `HEAD` (`launch-audit-remediation`):

| Commit | Subject | In HEAD |
|---|---|---|
| `0d450cda9` | docs: launch-audit certifications, remediation plans, work inventory | ✅ |
| `ade5d018a` | feat(stellar): consumer property-detail + matchmaker UI | ✅ |
| `7dc78bdea` | fix(location-dna): Phase 1 bug fixes | ✅ |
| `d702cc789` | fix(security): owner-scope offer-listing edit/create (C1) | ✅ |
| `db8aea772` | fix(security): authenticate + owner-scope Ask-AI listing-question (C2) | ✅ |
| `55e85104c` | fix(security): BidYourAgent Phase 1 authz (CRIT-1/2/5, HIGH-1/7) | ✅ |
| `67eeda75c` | fix(security): party-guard legacy CounteredTerms controllers (HIGH-5) | ✅ |
| `1556afc6c` | fix(hooks): anchor PHP debug matcher | ✅ |

> The work-inventory doc (2026-06-26) stated "nothing is committed." That is now stale — the Phase-1 remediation **has been committed** (commits above). This reconciliation supersedes the inventory's status table.

---

## 2. Reconciliation — BidYourAgent (all Critical / High)

| ID | Title | Doc status | **Verified status** | Evidence (current code) |
|---|---|---|---|---|
| CRIT-1 | Listing-edit horizontal IDOR (4 roles) | Fixed | ✅ CLOSED | `TenantAgentAuctionEdit.php:2506` (load), `:3319` (update), `:2392,2431` (deleteMeta) all `where('user_id',Auth::id())` |
| CRIT-2 | Unauthenticated auction termination | Fixed (2 live)/↩︎(1 dead) | ✅ CLOSED | routes `web.php:381,393` `auth`; `PropertyAuctionController.php:1516`, `LandlordAgentAuctionController.php:542` owner guard |
| CRIT-3 | Unauth `viewBid` PII leak | Reclassified — dead | ↩︎ CONFIRMED dead | No `Schema::create('landlord_auctions'/'landlord_auction_bids')` migration; only `hasTable`-guarded ALTERs |
| CRIT-4 | Public counter-bid write | Reclassified — dead | ↩︎ CONFIRMED dead | `seller_counter_id` 0 occurrences; `property_auction_bids` has no `counter_id` → store 500s before write |
| CRIT-5 | `destroyCounter` hard-delete IDOR | Fixed | ✅ CLOSED | `SellerCounterBidController.php:68-71` party guard (owner OR bidding agent) |
| CRIT-6 | Notification "View" 500 (view-counter routes) | **Remaining (Phase 2)** | ✅ **CLOSED** (reconciled) | All 5 `*.view-counter` route names now resolve (`route:list`); `NotificationController.php:68-84` references resolve. **Docs were stale.** |
| HIGH-1 | Inverted `AgentAuth` role gate | Fixed | ✅ CLOSED | `AgentAuth.php` allowlists `user_type==='agent'`, else redirect |
| HIGH-5 | Legacy `*CounteredTermsController` IDOR (6 controllers) | "Hardened" (all) | ✅ **CLOSED** | Seller/Buyer/Landlord/Tenant + `CounteredTerms.php` guarded ✅; **`AgentCounteredTermsController.php` store@28 / update@50 now party-guarded** (fixed by `e18b55046`, N1) ✅ — all 6 controllers complete |
| HIGH-7 | Unauth `renew_save` date tampering | Fixed (3 live)/↩︎(1 dead) | ✅ CLOSED | routes `web.php:310-316` `auth`; owner guards `PropertyAuctionController.php:1708`, `BuyerCriteriaAuctionBidController.php:445`, `TenantCriteriaAuctionController.php:726` |
| HIGH-2 | No "can't bid on own listing" guard (4 roles) | Open | 🔴 OPEN | No `user_id`/`Auth::id()` compare in mount/submit: Buyer `:364/:1022`, Seller `:209/:980`, Tenant `:581/~1292`, Landlord `:667` |
| HIGH-3 | Duplicate bids allowed (Buyer/Seller/Tenant) | Open | 🔴 OPEN | Unconditional insert Buyer `:1054`, Seller `:991`, Tenant `:1310`; only Landlord `:1458` guards; **no unique index** on `*_agent_auction_bids` |
| HIGH-4 | Agent Highlights show stale `AgentDefaultProfile` | Open | 🔴 OPEN | `partials/bid_detail_body/buyer.blade.php:301`, `seller.blade.php:249`, `landlord.blade.php:286` read `AgentDefaultProfile`, not bid meta |
| HIGH-6 | Submitted offers never auto-expire | Open | 🔴 OPEN | `ExpireOffersCommand.php:19` filters native `expires_at`; submit writes meta only (`OfferController.php:1257`); native NULL after draft |
| HIGH-8/9/10 | Dual create/edit components → parity drift | Open | 🔴 OPEN | Dedicated `*AgentAuctionEdit` orphaned; every edit route resolves to `TenantAgentAuctionEdit` (`web.php:608,651,677`) |
| HIGH-11 | `BuyerCounteredTermsController@update` writes non-existent `tenant_auction_id` | Open | ↩︎ RECLASSIFIED — dead/unreachable | Buggy line still at `BuyerCounteredTermsController.php:96`, **but `@update` is unrouted** — `route:list` maps `buyer/update-counter-terms` → `CounteredTerms@update` (only this controller's `@add`/`@edit` view-renderers are routed). Live `CounteredTerms@update:76-87` writes only valid columns + HIGH-5 party guard. Not user-reachable → **not a launch blocker** (analogous to CRIT-3/4). Optional dead-code cleanup only |
| HIGH-12 | Accepted-summary PDF cache never invalidated | Open (mitigated) | 🔴 OPEN (not re-verified; mitigated by terms-freeze) | `AcceptedBidSummaryService.php:344-361` regenerate has no caller |
| HIGH-13 | "Counter Rejected" fires no notification | Open | 🔴 OPEN | `CounterBidRejectedNotification` class exists but 0 dispatch sites |
| HIGH-14 | "Listing Published/Approved" notification missing (4 roles) | Open | 🔴 OPEN | All 4 approve methods set flag + redirect, no `notify()` |
| HIGH-15 | "Bid Updated" notification inconsistent | Open | 🔴 OPEN (per cert; not re-verified this pass) | Seller none on edit; Buyer wrong type |
| HIGH-16 | Offer Withdrawn notifies self not owner | Open | 🔴 OPEN (per cert) | `OfferController.php:324` |
| HIGH-17 | Duplicate notification on Offer Counter | Open | 🔴 OPEN (per cert) | `OfferController.php:720,730` |
| HIGH-18 | Agent-bid Withdraw only Tenant, no notification | Open | 🔴 OPEN (per cert) | `TenantAgentAuctionBidController.php:582` |

**BYA tests present:** `Phase1AuthorizationTest.php` (15 methods), `CounteredTermsAuthorizationTest.php` (10 methods — **no Agent coverage**, corroborating the HIGH-5 gap). Caveat: 3 ownership tests auto-skip (workspace harness resolves to live pgsql, not isolated SQLite — pre-existing).

---

## 3. Reconciliation — BidYourOffer (all Critical / High)

| ID | Title | Doc status | **Verified status** | Evidence (current code) |
|---|---|---|---|---|
| C1 | Offer-listing IDOR (edit+create, 4 roles) | Fixed | ✅ CLOSED | `Concerns/ResolvesOwnedAuction.php:38-74` owner-scoped + 403; wired into all 4 edit (mount+hydrate) + all 4 create; hydrate guard covers all write paths |
| C2 | Unauth IDOR + restricted leak on `/ask-ai/listing-question` | Mostly | ✅ CLOSED (stripping sub-part) | route `web.php:265` `['auth','throttle:ask-ai-api']` ✅; controller `ownsListing()` 403 ✅; **restricted-field stripping now wired** — `AskAiViewerAuthorizationService::redactContext()` strips the `SnapshotFactVisibility` 'restricted' tier for all non-owner scopes across all 4 roles (fixed by `13a26e1a6`, BYO-C2s) ✅. *(WF-1 is now CLOSED — see §10; and note there was no `*_offer` alias 403 in practice: every live poster sends a mapped token — the real WF-1 defect was a missing import, not a token gap.)* |
| C3 | Ask-AI widget non-functional (field contract) | Fixed | ✅ CLOSED | `matchmaker-ask-ai.blade.php:40-41` posts `listing_type`/`listing_id`; `detail.blade.php:192-196` passes context; JS fail-safe (M15) at `:95-102` |
| C4 | Seller Create drops Broker Compensation | Open | 🔴 OPEN (narrowed) | `commission_structure_type` validated `required` (`SellerOfferListing.php:4292`) but **never saved** in create `saveAllMetadata()`; Edit saves it (`SellerOfferListingEdit.php:3677`). Other financing/lease fields ARE saved on create — residual gap is the broker-comp group |
| H1 | Edit submit validation ≪ Create (4 roles) | Open | 🔴 OPEN | Seller Edit `:3990` validates 1 field; Buyer `:2785` 2-3; Landlord `:3824` thin; Tenant `:3103` manual ~8 |
| H2 | Landlord/Seller waterfront fields error in Edit | Open | 🔴 OPEN | Partials bind `water_frontage`/`waterfront_feet` (`property-preferences.blade.php:732/746,1175/1189`); Edit components declare neither (0 grep hits) |
| H3 | Buyer Edit may drop multiselect on submit | Open (needs-runtime) | 🔴 OPEN / NEEDS-RUNTIME | Edit lacks Create's `updated*Json` reconcile hooks |
| H4 | Tenant matching can't distinguish rentals/sales | Open (scope-gated) | 🔴 OPEN | `TenantOfferListingCriteriaLoader.php:124-132` maps to `'Residential'` (sale); `:172-181` nulls max_price |
| H5 | One-sided city normalization | Open (needs-runtime) | 🔴 OPEN / NEEDS-RUNTIME | client `St.→Saint`; Bridge raw; exact `in_array` |
| H6 | LDNA geocode HTTP call has no timeout | Open | 🔴 OPEN | `LocationDnaGeocodeService.php:200-205` passes only `query`, no `timeout` |
| H7 | Google POI failures silent | Open | 🔴 OPEN | `GooglePlacesPoiAdapter.php:82` ignores `status`; `:106` `catch{return []}` no log |
| H8 | Transient POI outage cached empty 24h | Open | 🔴 OPEN | `PoiDistanceLookupService.php:103` `Cache::put` inside `catch`. **Same finding as LDNA Phase-1 item #6** (dedupe) |
| H9 | Admin AI report ungrounded | Not a blocker (gated) | 🔴 OPEN (post-launch, gate must stay hard) | `OpenAiClientService.php:276-285` no system prompt |

**BYO tests present:** `Offers/OfferListingAuthorizationTest.php` (6 methods), `AskAiListingQuestionTest.php` (18 methods incl. unauth/non-owner/leak-guard). 50+ AskAi tests overall.

---

## 4. Location DNA Phase 1 (committed `7dc78bdea`)

| Item | Verified | Evidence |
|---|---|---|
| `'bridge'` listing type → LDNA | ✅ CLOSED | `LocationDnaPipelineRunner.php:178` |
| Agent-AI `geocode_status` filter (`success`→`geocoded`) | ✅ CLOSED | `ExtendedKnowledgeLoader.php:239` |
| Ask-AI lifestyle keys null | ✅ CLOSED | `AskAiContextBuilderService.php:2112` |
| Tile cache per-process `array` store | ✅ CLOSED | `LocationDnaPoiTileCache.php` `Cache::store()` |
| Marina/hospital exclusions | ✅ CLOSED | `LocationDnaPoiDistanceService.php:211,295` |
| Stop caching adapter errors | 🔴 OPEN | `PoiDistanceLookupService.php:103` — **= BYO H8** (single backlog item) |

---

## 5. Removed from active backlog (reclassified / deduped — do NOT re-open)

- **CRIT-3, CRIT-4 (BYA)** — dead `LandlordAuction` subsystem + missing counter columns; non-exploitable (500 before any read/write). All auth-gated for surface reduction. *(Phase-4 dead-code removal only.)*
- **CRIT-2 / HIGH-7 landlord variants** — same dead subsystem.
- **BYO H8 ≡ LDNA Phase-1 item #6** — one finding, tracked once (below as `BYO-H8`).
- **BYO M4/M5 overlap LDNA Phase-4** placeholder-card / flood-surfacing — tracked once.

---

## 6. NEW findings from this governance pass (not correctly stated in prior docs)

| New ID | Finding | Severity | Why it matters |
|---|---|---|---|
| **N1** | `AgentCounteredTermsController` store/update unguarded — HIGH-5 remediation incomplete (6th controller missed) | High | Same IDOR class as the 5 that were fixed; an agent can write/update counter terms on any agent auction. **✅ FIXED by `e18b55046`** (party guard mirrored from Buyer; test `AgentCounteredTermsAuthorizationTest`) |
| **N2** | CRIT-6 reconciled to CLOSED (docs had it open) | — | Removes an item from the Phase-2 blocker list |
| **N3** | C4 scope narrowed to the broker-commission group only | — | Reduces effort; other ~financing fields already persist on create |
| **N4** | C2 restricted-key stripping not wired (route/owner/throttle done) | Medium-High | KB-miss path may still emit restricted keys; needs runtime probe before GA. **✅ FIXED by `13a26e1a6`** — runtime probe confirmed reachable; strip wired in `redactContext()` for all non-owner scopes; 18 unit tests green |

---

## 7. Consolidated Remaining Remediation Backlog (Critical + High)

Only **unresolved** items. Status reflects implementation only (🟩 requires impl+validation+tests; runtime-only → 🟦). Launch-blocker / Required-Before-V1 are **provisional** pending the itemized V1 checklist and the Tenant-scope decision.

| ID | Source | Title | Sev | Status | Launch Blocker | Req. before V1 | Effort | Regression risk | Dependencies |
|---|---|---|---|---|---|---|---|---|---|
| N1 | BYA Audit (HIGH-5) | Party-guard `AgentCounteredTermsController` store/update | High | 🟩 | ~~Yes~~ **DONE (`e18b55046`)** | — | S | Low (mirror existing guard) | none |
| BYA-H2 | BYA Audit | Self-bid guard (all 4 roles) | High | 🟩 | ~~Yes~~ **DONE — code-complete (docs stale)** | — | S | Low | guards in 4 `*AgentAuctionBid::submit()`; `ByaSelfBidGuardTest` 5/0 |
| BYA-H3 | BYA Audit | Duplicate-bid prevention (Buyer/Seller/Tenant) | High | 🟩 | ~~Yes~~ **DONE (code) — code-complete (docs stale)** | — | M | Med | update-in-place dedup; `ByaDuplicateBidGuardTest` 4/0. **DB unique index deferred** (owner D2 — pre-clean dups first) |
| BYA-H4 | BYA Audit | Agent Highlights → read submitted bid meta | High | 🟩 | ~~Yes~~ **DONE — code-complete (docs stale); browser QA pending** | — | M | Med | partials read `data_get($bid,'get.*')`; **Phase G browser cert** |
| BYA-H6 | BYA Audit | Mirror offer deadline to native `expires_at` on submit | High | 🟩 | ~~Yes~~ **DONE — code-complete (docs stale); functional QA pending** | — | S | Low | native dual-write `f569e9008`; `AskAiAndExpiryGatingTest`/`OfferExpiresAtNativeWriteTest` green; **Phase G expiry-run QA** |
| BYA-H11 | BYA Audit | Remove/repair `BuyerCounteredTermsController` non-existent column | High | ↩︎ | ~~Yes~~ **No — reclassified dead code** | No | S | Low | `@update` unrouted (dead); live path is `CounteredTerms@update` (clean) |
| BYA-H8/9/10 | BYA Audit | Create/Edit field+validation parity (consolidate or align) | High | ⬜ | No* | Yes (align), No (consolidate) | M (align) / L (consolidate) | Med | frozen `initializeLimitedService()` untouched |
| BYO-C4 | BYO Audit | Persist Seller broker-comp (`commission_structure_type` group) on Create | Crit | 🟩 | ~~Yes~~ **DONE (Phase B)** | — | S–M | Low | 6 fields added to Create save/load/draft; round-trip tests green |
| BYO-C2s | BYO Audit (N4) | Strip RESTRICTED keys at Ask-AI context assembly + runtime probe | High | 🟩 | ~~Yes~~ **DONE (`13a26e1a6`)** | — | S | Low | probe done; strip wired in `redactContext()` |
| BYO-H1 | BYO Audit | Edit submit validation parity with Create (4 roles) | High | 🟩 | ~~Yes~~ **DONE (Phase B)** | — | M | Med (draft path kept lenient) | shared {Seller,Landlord}PublishValidation concerns; create==edit verified identical |
| BYO-H2 | BYO Audit | Declare+save+load waterfront props in Seller/Landlord Edit | High | 🟩 | ~~Yes~~ **DONE (Phase B)** | — | S | Low | Seller via eb2eed4f3; Landlord Edit 4 layers added |
| BYO-H3 | BYO Audit | Buyer multiselect submit persistence | High | ⬜ | Yes (if confirmed) | Verify | M | Med | runtime verify |
| BYO-H4 | BYO Audit | Tenant rentals-vs-sales matching | High | 🟩 | ~~Scope-gated~~ **DONE — code-complete, committed locally `ce57eac2e` (not pushed); browser QA pending** | — | M | Med | `Residential Lease` + `max_price`, live-Bridge verified; `TenantCommercialLeaseMatchingTest` 14/0; **Phase G browser cert** |
| BYO-H5 | BYO Audit | Two-sided city normalization | High | ⬜ | Verify | Verify | S | Low | runtime `SELECT DISTINCT city` |
| BYO-H6 | BYO Audit | LDNA geocode timeout | High | ⬜ | Pre-GA | Recommend | S | Low | none |
| BYO-H7 | BYO Audit | Log Google POI failures (status + catch) | High | ⬜ | Pre-GA | Recommend | S | Low | none |
| BYO-H8 | BYO + LDNA | Stop caching POI adapter errors (cache-on-success only) | High | ⬜ | Pre-GA | Recommend | S | Low | depends on H7 |
| BYO-H9 | BYO Audit | Admin AI report grounding | High | ⬜ | No (gate hard) | No | M | Low | gate must stay manual |

\* HIGH-8/9/10: *aligning* required-field/validation parity is launch-relevant; *consolidating* the dual components into one is a post-launch refactor.

**Deferred (Medium/Low):** BYA MED-1…24 + LOW-1…15, BYO M1…M20 + L1…L10 carried forward at their severity. Most are post-launch; a few are launch-config/UX-honesty items (e.g. **BYO-M4** placeholder "coming soon" cards render unconditionally; **BYO-M7** vacant-land mislabeled).

> **BYO-L2 reclassified → Recommended UX cleanup (not Required — 2026-07-08 (2)).** The proposed `SAVE_AS_NEW_DRAFT` `true→false` flip is a **no-op**: the create-component overwrite branch is unreachable (`isResumingDraft` is never set — create routes pass no `listingId`, and resume routes to the separate `*OfferListingEdit` components). The real behavior is **intentional-but-unpruned draft versioning** in the 4 Edit components (`getDrafts()` lists every `is_draft` row; `parent_draft_id` is write-only; no pruning) — resume→save accumulates draft versions in the "Load Saved Draft" list. No data loss / security / broken workflow → **not launch-blocking.** Any real fix (show latest-per-chain, or prune superseded versions) is a **product decision** in the Edit components, Med regression risk. See Master Roadmap §3 + §8.1.

---

## 8. Launch-blocker shortlist (verified, Critical+High, security/data-integrity)

> **Superseded by §11 (updated) — all items below are now code-complete or reclassified (2026-07-08 (2)). Retained for history.**

1. ~~**BYO-C4** — Seller create broker-comp data loss *(Critical, data integrity)*~~ **✅ CLOSED (Phase B)**
2. ~~**N1** — `AgentCounteredTermsController` IDOR *(High, access control)*~~ **✅ CLOSED (`e18b55046`)**
3. ~~**BYA-H6** — offers never expire *(High, core negotiation workflow)*~~ **✅ CLOSED (code) — native `expires_at` write (`f569e9008`); Phase G expiry-run QA**
4. ~~**BYA-H2 / BYA-H3** — self-bid + duplicate-bid marketplace integrity *(High)*~~ **✅ CLOSED (code) — guards + dedup; DB unique index deferred**
5. ~~**BYA-H4** — consumers shown wrong agent data *(High, product promise)*~~ **✅ CLOSED (code) — partials read submitted bid meta; Phase G browser QA**
6. ~~**BYO-C2s** — Ask-AI restricted-field stripping *(High, privacy)*~~ **✅ CLOSED (`13a26e1a6`)**
7. ~~**BYO-H1 / BYO-H2** — edit-flow publish-with-blank-required + waterfront edit error *(High, data integrity)*~~ **✅ BOTH CLOSED (Phase B)**
8. ~~**BYA-H11** — Buyer counter-terms SQL-error path *(High)*~~ **↩︎ RECLASSIFIED — dead/unreachable code (`@update` unrouted), not a launch blocker**

All confirmed **CLOSED** Criticals (CRIT-1/2/5/6, C1, C3) and the reclassified dead-code items are **not** on this list.

---

## 9. End-to-end workflow certification (13-stage lifecycle)

Read-only static trace of every workflow across the lifecycle stages. ✅ wired · 🔴 broken/missing (workflow blocker) · 🟦 runtime-only (browser-cert needed). **Rule applied:** a stage that is technically present but breaks for a normal user is a **WORKFLOW BLOCKER even if the underlying audit item is "closed."**

### 9.1 Listing lifecycle — BidYourAgent (hire-an-agent), all 4 roles

| Stage | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Create | ✅ `web.php:529` | ✅ `web.php:607` | ✅ `web.php:646` | ✅ `web.php:676` |
| Save Draft | ✅ (no validation) | ✅ | ✅ (no validation) | ✅ (partial validation) |
| Edit | ✅ owner-scoped → shared `TenantAgentAuctionEdit` | ✅ same | ✅ same (3-way component split) | ✅ same |
| Publish/Approve | 🔴 no notify (HIGH-14) | 🔴 no notify | 🔴 no notify | 🔴 no notify |
| View | ✅ public (by design) | ✅ public | 🔴 leaks drafts (MED-23) | 🔴 leaks drafts (MED-23) |
| Notifications | 🟡 bid-only | 🟡 bid-only | 🟡 bid-only | 🟡 bid-only |
| Archive/Delete | 🔴 none (drafts only) | 🔴 none | 🔴 none | 🔴 none |
| Mobile/Desktop | 🟦 viewport+grid present; render RUNTIME | 🟦 | 🟦 | 🟦 |

### 9.2 Listing lifecycle — BidYourOffer (offer-listing), all 4 roles

| Stage | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Create | ✅ 🔴 drops `commission_structure_type` (C4) | ✅ 🔴 drops `other_business_type` (M12) | ✅ | ✅ |
| Save Draft | 🔴 `SAVE_AS_NEW_DRAFT=true` (L2) | 🔴 L2 | 🔴 L2 | 🔴 L2 |
| Edit | ✅ C1 owner-scoped; 🔴 H2 waterfront crash | ✅ C1 | ✅ C1; 🔴 H2 waterfront crash | ✅ C1 |
| Publish | 🔴 validates 1 field → blank publish (H1) | 🟦 partial (county/state) | 🔴 blank publish (H1) | ✅ required net |
| View | ✅ `web.php:1115` | ✅ `web.php:1107` | ✅ `web.php:1111` | ✅ `web.php:359` |
| Archive/Delete | 🔴 none | 🔴 none | 🔴 none | 🔴 none |
| Notifications | 🔴 none on create/publish | 🔴 none | 🔴 none | 🔴 none |
| Mobile/Desktop | 🟦 | 🟦 | 🟦 | 🟦 |

### 9.3 Negotiation lifecycle (agent bid → counter → accept/reject → expire → summary)

| Stage | Status | Evidence |
|---|---|---|
| Bid submit — self-bid guard | 🔴 none (all 4 roles) | HIGH-2 |
| Bid submit — duplicate guard | 🔴 none (Buyer/Seller/Tenant); ✅ Landlord | HIGH-3 |
| Bid edit — notify | 🔴 Seller none / Buyer wrong type; ✅ Landlord/Tenant | HIGH-15 |
| Bid withdraw | 🔴 Tenant-only, no notify | HIGH-18 |
| Counter — modern (Offer engine) | ✅ party-checked + state machine | `OfferPermissionService:63`, `OfferStateMachineService:32` |
| Counter — legacy AgentCounteredTerms | ✅ party-guarded (`e18b55046`, N1) | store@28/update@50 owner-or-bidder guard |
| Accept | ✅ party-checked; ✅ AcceptedBidSummary created | `OfferController:210`; summary services |
| Reject/decline | ✅ party-checked; 🔴 no `CounterBidRejectedNotification` | HIGH-13 |
| **Expiration** | 🔴 **offers NEVER expire** | HIGH-6 — `OfferController:1257` meta-only; `ExpireOffersCommand:17` filters native NULL |
| Accepted summary + PDF + both-party sign gate | ✅ wired; 🟦 PDF cache invalidation latent (HIGH-12) | `AcceptedBidSummaryController:174` |
| Notification recipients | 🔴 withdraw notifies self (HIGH-16); 🔴 duplicate offer-counter (HIGH-17) | `OfferController:324,720,730` |

### 9.4 Consumer discovery — Search/Match → View → Location DNA → Property DNA → Ask AI

| Stage | Buyer | Tenant |
|---|---|---|
| Search/Match | ✅ `web.php:180` ownership-scoped | ✅ **shared route** (audit "no tenant route" STALE); 🔴 H4 rentals-vs-sales |
| View detail | ✅ 500-safe, IDX-gated | ✅ shared |
| Location DNA | ✅ POI; 🔴 M4 placeholder cards, M5 flood not surfaced | same |
| Property DNA | ✅; 🔴 M7 vacant-land mislabel | same |
| Ask AI | 🔴 **403 for ALL consumers** (closed-but-broken) | 🔴 same |
| Notifications | ✅ none (correct for read-only) | none |
| Mobile/Desktop | 🟦 | 🟦 |

---

## 10. NEW workflow blockers (surfaced by §9; not in prior audits)

| ID | Workflow | Finding | Sev | Evidence | Launch Blocker |
|---|---|---|---|---|---|
| **WF-1** 🟩 | Ask AI (both products) | ✅ **CLOSED (code), committed locally `4bb7d8cf8` (not pushed).** **Root cause corrected:** the token map already covered every live poster (view pages post `seller`/`buyer`/`landlord`/`tenant`; widget posts `buyer`/`tenant`) — no `*_offer` token reaches the endpoint. The live break was a **missing `AskAiViewerAuthorizationService` import** → `Class not found` swallowed into a soft-fail on the owner path (introduced `645d325507`). Fix = add import + diagnostic logging; `AskAiListingQuestionTest` 61/0. **Phase G browser QA pending.** | High | `AskAiListingQuestionController` import fix; see Master Roadmap §8.3 | ~~Yes~~ **DONE (code)** |
| **WF-2** 🟩 | All 8 listing workflows | ✅ **CLOSED (Pass M-B, owner-approved 2026-06-28).** Was: no archive/delete/unpublish of a published listing. Now: owner-scoped reversible **archive/republish** (`is_archived`), and every public surface is archive-aware — 9 discovery gates + all 8 by-ID/detail/view guards + tenant author multi-tab; bids/history preserved | High | `is_archived` migration; `DashboardController::setListingArchived`; 8 detail-action owner-aware guards; `UserController::author` gated | ~~Yes~~ **DONE** |
| **WF-3** 🟩 | BYA Landlord+Tenant (found in **all 8**) | ✅ **CLOSED (Pass M-B).** Was: unscoped `deleteDraft()` meta deletion → cross-user data destruction. Now: ownership gate on all 8 `deleteDraft()` + BYA Buyer wrong-table bug fixed; DB-backed Livewire test passes | High (security) | ownership gate across 8 `deleteDraft()`; `Phase1AuthorizationTest::test_wf3_…` | ~~Yes~~ **DONE** |
| **WF-4** 🟩 | BYA Landlord+Tenant (closed in **all 8**) | ✅ **CLOSED (Pass M-B).** Was: public VIEW leaks draft/pending listings (no status/auth gate). Now: owner-only guard (`abort(404)` when `!is_approved`/`is_draft` and viewer ≠ owner) on all 8 detail actions, bundled with the WF-2 archive guard; CI-ready test added | Med-High (privacy) | owner-only draft/pending guard on 8 detail actions; `Phase1AuthorizationTest::test_wf4_…` | ~~Yes~~ **DONE** |
| **WF-5** | BYA Seller+Buyer | **Compatibility preferences uneditable after create** — shared editor only handles `tenant_specific` | Med-High | `TenantAgentAuctionEdit.php:2772-2795,3750-3752` | Recommended |
| **WF-6** | BYA legacy POST routes | Controller `update()` / `bidsVisibility()` lack ownership checks (authenticated IDOR, distinct from CRIT-1 entry points) | Med-High (security) | `LandlordAgentAuctionController.php:231`, `TenantAgentAuctionController.php:182,547` | Recommended→Yes (verify reachability) |
| **WF-7** | BYA edit (shared) | MED-24 silent locked fields on edit (auction_type/working_with_agent/auction_time reverted, no user message) | Low-Med (UX) | `TenantAgentAuctionEdit.php:3335-3351` | No |

---

## 11. Reconciliation updates from the workflow pass

- **Tenant launch-scope question RESOLVED.** Tenant discovery (search→match→detail) is wired via the shared `/stellar/buyer/results` + `/stellar/property/{listingKey}` routes (`StellarBuyerResultsController.php:142-143,228-230`). Therefore **`BYO-H4` (tenant rentals-vs-sales) is Required-Before-V1** if Tenant discovery ships in V1 — no longer scope-gated. Pending only your explicit confirmation that Tenant *is* in V1 scope.
- **`BYO-H3` (Buyer multiselect submit) → reclassified NOT-A-BLOCKER.** `business_type_selected` persists in both create (`BuyerOfferListing.php:2601`) and edit (`BuyerOfferListingEdit.php:2512`). The real Buyer create-path drop is **M12 (`other_business_type`)** — fold H3's concern into M12.
- **HIGH-8/9/10 expanded** with concrete workflow evidence: Landlord runs **3 different components** (create `LandLordAgentAuction` / resume `TenantAgentAuction` / edit `TenantAgentAuctionEdit`); `retained_deposits` exists only in the shared path, absent from Landlord create → silent create/edit field divergence.
- **MED-3 (`retained_deposits`) / MED-7 (`storage_space`) → verified CLOSED** as data-loss bugs (both round-trip on create+edit); MED-3's only residue is the Landlord-create omission folded into HIGH-8/9/10.
- **Positive confirmations:** C1 owner-scoping fully wired across all 8 BYO components; Track-B modern offer engine (counter/accept/reject party-checks + state machine) is sound; both-party PDF signature gate enforced.

### Updated launch-blocker shortlist (supersedes §8 — security + data-integrity + broken core workflows)

1. ~~**BYO-C4** — Seller create broker-comp data loss *(Critical)*~~ **✅ CLOSED (Phase B)**
2. ~~**N1** — `AgentCounteredTermsController` IDOR *(High)*~~ **✅ CLOSED (`e18b55046`)**
3. ~~**WF-3** — unscoped `deleteDraft` cross-user data destruction *(High, security — NEW)*~~ **✅ CLOSED (Pass M-B)**
4. ~~**BYA-H6** — offers never expire (breaks whole negotiation loop) *(High)*~~ **✅ CLOSED (code) — native `expires_at` dual-write (`f569e9008`); Phase G expiry-run QA**
5. ~~**WF-1** — Ask AI broken for all consumers *(High — NEW)*~~ **✅ CLOSED (code), committed locally `4bb7d8cf8` (not pushed) — real cause was a missing import (§8.3), not the token map; Phase G browser QA**
6. ~~**WF-2** — no delete/unpublish of own published listing *(High — NEW)*~~ **✅ CLOSED (Pass M-B, owner-approved) — owner archive/republish, archive-aware on all public surfaces**
7. ~~**BYA-H2 / BYA-H3** — self-bid + duplicate-bid integrity *(High)*~~ **✅ CLOSED (code) — guards + update-in-place dedup; DB unique index deferred (owner D2)**
8. ~~**BYA-H4** — consumers shown wrong agent data *(High)*~~ **✅ CLOSED (code) — partials read submitted bid meta; Phase G browser QA**
9. ~~**BYO-H1 / BYO-H2** — edit publishes blank required fields / waterfront edit crash *(High)*~~ **✅ BOTH CLOSED (Phase B)**
10. ~~**BYO-H4** — tenant rentals-vs-sales (Tenant scope confirmed live) *(High)*~~ **✅ CLOSED (code), committed locally `ce57eac2e` (not pushed) — `Residential Lease` + rent ceiling, live-Bridge verified; Phase G browser QA**
11. ~~**BYA-H11** — Buyer counter-terms SQL-error path *(High)*~~ **↩︎ RECLASSIFIED — dead/unreachable (`@update` unrouted), not a launch blocker**
12. ~~**BYO-C2s** — Ask-AI restricted-field stripping *(High)*~~ **✅ CLOSED (`13a26e1a6`)**
13. ~~**WF-4 / WF-6** — draft view leak + legacy-route IDOR *(Med-High, security — verify)*~~ **✅ BOTH CLOSED** (WF-6 Pass M-A, WF-4 Pass M-B)

> **All §11 launch blockers are now code-complete or reclassified (2026-07-08 (2)).** No open Required implementation work remains; **BYO-L2 → Recommended (§7)**. **Next launch phase = Phase G browser/mobile/runtime certification** (see Master Roadmap §7). **Code-complete ≠ launch-certified.**

---

## Change log

- **2026-06-27 (1)** — Initial reconciliation (Phase 0, Priority 1). Baseline (8 commits) verified in HEAD. All BYA + BYO Critical/High re-verified against current code. CRIT-6 reconciled CLOSED; HIGH-5 reopened as PARTIAL (N1); C4 narrowed (N3); C2 stripping flagged In QA (N4).
- **2026-06-27 (2)** — End-to-end workflow certification (§9–§11). 13-stage lifecycle traced across both products × 4 roles + negotiation + discovery. **7 new workflow blockers (WF-1…WF-7)**, incl. closed-but-broken Ask AI (WF-1), no listing delete (WF-2), unscoped deleteDraft data destruction (WF-3). Tenant scope resolved (discovery is live → H4 required). H3 reclassified non-blocker; MED-3/MED-7 verified closed. Awaiting itemized V1 checklist to finalize Required/Recommended/Post-Launch tiers and the 7 governance deliverables.
- **2026-06-28 (1)** — **WF-3 🟩 and WF-2 🟩 CLOSED (Pass M-B, owner-approved).** WF-3: ownership gate on all 8 `deleteDraft()` + BYA Buyer wrong-table fix (DB-backed test passes). WF-2: owner-scoped reversible archive/republish (`is_archived`) made archive-aware across **every public surface** — 9 discovery gates, all 8 by-ID/detail/view actions (owner-aware `abort(404)`), and the tenant author/profile multi-tab; owner retains visibility (My Listings + own detail); bids/summaries/history preserved. §10 + §11 shortlist updated. **Required remaining: 12** (was 15 — WF-6/WF-3/WF-2 closed). Readiness: **BYA ≈64%, BYO ≈61%.** See Master Roadmap change log 2026-06-28 (1) for the full file-level detail.
- **2026-06-28 (2)** — **WF-4 🟩 CLOSED — doc reconciled to code (recovery audit).** A recovery audit of an interrupted session found the WF-4 draft/pending view-leak guard was already implemented and tested in code but still tracked open here. The owner-only guard (`abort(404)` when `!is_approved`/`is_draft` and viewer ≠ owner) ships on **all 8** by-ID detail actions (bundled with the WF-2 archive guard), with CI-ready test `test_wf4_…`. Updated the §10 WF-4 row and the §11 shortlist. **Required remaining: 11** (WF-6/WF-3/WF-2/WF-4 closed); Phase A (security close-out) complete. Documentation only — no application code modified.
- **2026-06-29 (1)** — **Phase B 🟩 COMPLETE — BYO-C4 + BYO-H2 + BYO-H1 (Seller/Landlord UI parity).** **BYO-C4:** Seller Create now persists the 6 `commission_structure_type*` broker-comp fields (save/load/draft), mirroring Edit — closes the silent broker-comp drop. **BYO-H2:** Landlord Edit `water_frontage`/`waterfront_feet` added across all 4 layers (Seller Edit was already fixed by `eb2eed4f3`). **BYO-H1:** create/edit publish-validation parity via shared `OfferListing/Concerns/{Seller,Landlord}PublishValidation` (single source — create==edit rule sets verified byte-identical); Edit publish enforces required fields, drafts stay lenient. Frozen `initializeLimitedService()` untouched; no duplicate validators. **Tests:** `CreateEditParityRegressionTest` 41/41 green (fixtures updated to seed required fields); full Offers+ListingImport = 53 failed/415 passed vs 58/410 baseline (net −5 failures), **zero** failures in the offer-listing domain (remaining are pre-existing offer-negotiation/env). **Required remaining: 8** (7/15 closed); Phases A + B complete. See Master Roadmap change log 2026-06-29 (1) for file-level detail.
- **2026-07-08 (1)** — **N1 🟩 + BYO-C2s 🟩 CLOSED; BYA-H11 ↩︎ RECLASSIFIED (docs reconciled to code).** **N1:** `AgentCounteredTermsController` store/update now carry the owner-or-bidding-agent party guard (mirrors Buyer/HIGH-5), fixed by **`e18b55046`**; focused test `AgentCounteredTermsAuthorizationTest` added. This also completes **HIGH-5** (all 6 legacy CounteredTerms controllers now guarded). **BYO-C2s:** a runtime probe confirmed the `SnapshotFactVisibility` 'restricted' tier reached the assembled Ask-AI context for non-owner viewers (`redactContext()` did not strip it); fixed by **`13a26e1a6`** — `redactContext()` now strips restricted compliance fields (flood/HOA/CDD/deposit/rent/income-requirement/seller-financing) for every non-owner scope across all 4 roles, via whole-segment token matching; owner scope unchanged; 18 unit tests green, no regressions vs baseline (+12/−0). **BYA-H11:** reclassified **dead/unreachable** — the buggy `tenant_auction_id` write is in `BuyerCounteredTermsController@update`, which is **unrouted** (`route:list`: `buyer/update-counter-terms` → `CounteredTerms@update`, which writes only valid columns + party guard). Not user-reachable → **removed from the launch-blocker list** (optional dead-code cleanup only). **Verified launch blockers remaining: 6** — BYA-H6, WF-1, BYA-H2, BYA-H3, BYA-H4, BYO-H4. Documentation only — no application code modified in this pass (the N1/C2s code changes were committed separately in `e18b55046` / `13a26e1a6`).
- **2026-07-08 (2)** — **All 6 remaining launch blockers now code-complete; BYO-L2 reclassified → Recommended. Documentation only — no application code modified this pass.** **WF-1 🟩** implemented & committed **locally** (`4bb7d8cf8`, not pushed): root cause corrected — the live break was a **missing `AskAiViewerAuthorizationService` import** (soft-fail on the owner path, introduced `645d325507`), **not** the "widget posts `*_offer` → token-map 403" theory (every live poster already sends a mapped token); fix = add import + diagnostic logging; `AskAiListingQuestionTest` 61/0. **BYO-H4 🟩** implemented & committed **locally** (`ce57eac2e`, not pushed): residential tenants now map to `Residential Lease` (not the for-sale `Residential`) with the monthly budget as `max_price`, verified read-only against the live Bridge dataset; `TenantCommercialLeaseMatchingTest` 14/0. **BYA-H6 / BYA-H2 / BYA-H3 / BYA-H4 🟩** verified **already code-complete** in prior commits (docs were stale): native `expires_at` dual-write (`f569e9008`), self-bid guards (`ByaSelfBidGuardTest` 5/0), update-in-place dedup (`ByaDuplicateBidGuardTest` 4/0; **DB unique index still deferred** per owner D2), Agent-Highlights-from-bid-meta. **BYO-L2 ↩︎ RECLASSIFIED → Recommended (§7):** the `SAVE_AS_NEW_DRAFT` flip is a **no-op** (create-component branch unreachable; resume routes to the Edit components); the real behavior is intentional-but-unpruned draft versioning in the 4 `*OfferListingEdit` components — not launch-blocking. **Verified launch blockers remaining: 0 (code).** **Next launch phase = Phase G browser/mobile/runtime certification.** **Code-complete ≠ launch-certified;** WF-1/BYO-H4 commits are local on `launch-audit-remediation`, not pushed.
