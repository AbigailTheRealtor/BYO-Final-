# BidYourAgent — Production Launch Certification Report

**Audit type:** Production launch certification (read-only)
**Date:** 2026-06-25
**Auditor role:** Lead QA / Laravel Architect / Product / UX / Security / Final Technical Reviewer
**Codebase:** Laravel 8.83.29 · PHP 8.2 · PostgreSQL (`heliumdb`) · 153 models · 229 migrations · 1,296-line route file · 261 test files
**Method:** Static source analysis across 6 parallel audit streams, every finding cited to `file:line`. No application code was modified.

---

## ⚠️ Methodology & Limitations (read first)

This certification is **evidence-based static analysis**. What that means for the scores below:

- ✅ **Verified:** route/middleware wiring, controller & Livewire logic, authorization checks, EAV meta read/write parity, notification dispatch sites, validation rules, config, secrets, DB schema — all read directly and cited.
- ❌ **NOT executed (marked `NOT TESTED`):**
  - Live browser walkthroughs of the wizards/marketplace (Playwright browser binaries are **not installed**; installing them is a protected dependency action that was not taken).
  - Per-property-type **conditional-field rendering** (Residential / Income / Commercial / Business Opportunity / Vacant Land / Rental / Lease) — the wizards share code paths, but each type's Blade partials were not matrix-rendered.
  - Runtime confirmation of 500s, queue/broadcast delivery, websocket auth, and live DB row state.

Where a finding is a **code-path certainty** (e.g. a route outside the `auth` group calling `findOrFail()->update()` with no ownership check), it is reported as confirmed. Where behavior depends on runtime, it is flagged. **No PASS is claimed for anything not actually verified.**

---

## 1. Executive Summary

BidYourAgent is a feature-rich, four-role (Seller / Buyer / Landlord / Tenant) agent-hiring marketplace. The core domain logic is substantial and, in several areas, genuinely well-built — the **Offer state machine**, the **Accepted Bid Summary** core, the **`AgentBidMapperService`**, and the **dashboard query layer** are all solid and correctly authorized.

However, the audit surfaced a **systemic authorization failure**: a large number of state-mutating and data-exposing endpoints sit **outside the authentication middleware group** and/or perform **no ownership checks**, producing multiple confirmed **IDOR (Insecure Direct Object Reference)** and **broken-access-control** vulnerabilities. Several of these allow an anonymous or arbitrary logged-in user to **edit other users' listings, end auctions, delete bids, write counter-bids, tamper with listing dates, and read other bidders' financial/contact PII.**

In addition, the consumer-facing marketplace has **data-integrity defects** that violate the product's core promise — most notably, consumers are shown **stale agent profile data instead of the values the agent actually submitted on the bid**, submitted offers **never expire**, and a dashboard notification button **crashes (HTTP 500)** for most counter-bid notifications.

**These are not polish items. They are launch blockers.**

### Verdict: ❌ **NOT APPROVED FOR PRODUCTION LAUNCH**

**Overall Launch Readiness Score: 41 / 100**

The functional skeleton is largely present and much of the happy path appears wired correctly, but the security posture and several data-integrity guarantees are unacceptable for a public launch handling financial and contact data. The blockers are well-understood and mostly **low-effort to fix** (the dominant root cause — routes outside the `auth` group + missing ownership checks — is a concentrated, fixable pattern).

---

## 2. Scores

### 2.1 Overall

| Dimension | Score | Notes |
|---|---:|---|
| **Overall Launch Readiness** | **41 / 100** | Gated by security; functional core healthier |
| Security | **24 / 100** | Multiple confirmed IDOR, unauth state mutation, PII leak |
| Consumer Listing (Phase 1) | 50 / 100 | Works, but IDOR on edit + create/edit parity drift |
| Agent Marketplace (Phase 2) | 45 / 100 | Inverted role guard, no own-listing guard, duplicate bids |
| Consumer Bid Review (Phase 3) | 48 / 100 | Wrong/unsurfaced data; role asymmetry |
| Bid Flow | 50 / 100 | Submit/edit work; display + guards incomplete |
| Counter / Negotiation Flow (Phase 4) | 33 / 100 | Public endpoints + IDOR delete; modern path is sound |
| Accepted Bid Summary (Phase 5) | 70 / 100 | Core sound; PDF cache + Seller content gaps |
| Notifications (Phase 6) | 40 / 100 | One 500 crash; many missing/duplicated/mis-targeted |
| Dashboards (Phase 7) | 65 / 100 | Well-optimized; broken "View" button |
| Validation | 50 / 100 | Divergent create/edit; no upload mime/size rules |
| Performance | 75 / 100 | Dashboards optimized; not load-tested |

### 2.2 Score by Role

| Role | Score | Dominant issues |
|---|---:|---|
| Seller | 45 / 100 | Edit IDOR; create/edit parity; `video_upload` never saved; accepted-summary content incomplete; no bid-edit notification |
| Buyer | 42 / 100 | Edit IDOR; Agent Highlights show stale profile; duplicate bids; `BuyerCounteredTermsController` writes a non-existent column; wrong "Bid Updated" notification |
| Landlord | 40 / 100 | Edit IDOR; **unauth `viewBid` PII leak**; `retained_deposits` wiped on edit; retainer/split-payment terms never displayed |
| Tenant | 45 / 100 | Edit IDOR; no dedicated bid-detail partial (split review); no listing-status guard on bid; withdraw has no notification |

### 2.3 Score by Property Type

> **⚠️ NOT FULLY TESTED.** Per-property-type conditional rendering was **not** matrix-verified (no live browser run). The wizards are single shared components that branch internally by type, so the *authorization/data-integrity* blockers above apply **equally to every property type**. Type-specific field-rendering correctness is **unverified** and must be confirmed before launch.

| Role | Property types | Status |
|---|---|---|
| Seller | Residential · Income Property · Commercial Sale · Business Opportunity · Vacant Land | Code paths shared; conditional rendering **NOT TESTED** |
| Buyer | Residential · Income Property · Commercial Sale · Business Opportunity · Vacant Land | Code paths shared; conditional rendering **NOT TESTED** |
| Landlord | Residential Rental · Commercial Lease | Code paths shared; conditional rendering **NOT TESTED** |
| Tenant | Residential Rental · Commercial Lease | Code paths shared; conditional rendering **NOT TESTED** |

---

## 3. Critical Issues (Launch Blockers)

All six are confirmed by code path. All are **Launch blocker: YES.**

### CRIT-1 — Horizontal IDOR: any logged-in user can edit/overwrite any listing (all 4 roles)
- **Severity:** Critical · **Root cause:** The unified edit component `TenantAgentAuctionEdit.php` (the live edit path for **all four roles**) does `findOrFail($auctionId)` in `loadAuctionData()` (~:2502) and saves via `find($this->auctionId)` (~:2392, 2431, 3313) with **zero** `Auth::id()`/`user_id` ownership comparison. Routes `routes/web.php:593` and `:662` are protected only by `['auth','verified']` (`routes/web.php:464`) — login, not ownership. (Create components correctly scope by `user_id`; edit does not.)
- **Files:** `app/Http/Livewire/TenantAgentAuctionEdit.php` (load/save paths); `routes/web.php:464,593,662`
- **Repro:** Log in as User A → open `/hire/agent/auction/edit/{B's id}/seller` (or buyer/landlord/tenant) → B's listing loads, edits persist to B's record.
- **Fix:** Scope fetch by `->where('user_id', Auth::id())->firstOrFail()` in load **and** save; or add a policy/mount authorization. · **Effort:** S

### CRIT-2 — Unauthenticated auction termination (4 endpoints)
- **Severity:** Critical · **Root cause:** `endAuction` POST routes sit **outside** the `auth` group and call `Model::findOrFail($id)->update(['auction_ended'=>true])` with no auth/ownership check.
- **Files:** `routes/web.php:374,386,458`; `LandlordAgentAuctionController.php:538-543`, `PropertyAuctionController.php:1512-1517`, `LandlordAuctionController.php:571-576`
- **Repro:** `POST /property/auction/end/{anyId}` (with CSRF) ends any user's auction.
- **Fix:** Move routes inside `auth` group + owner check. · **Effort:** S

### CRIT-3 — Unauthenticated bidder PII / financial-data leak
- **Severity:** Critical · **Root cause:** `LandlordAuctionController@viewBid` route (`routes/web.php:457`) is outside `auth`; method `findOrFail($bid_id)` and returns the full bid (incl. financial/contact meta) to the view with no ownership check.
- **Files:** `routes/web.php:457`; `LandlordAuctionController.php:563-569`
- **Repro:** Enumerate `/landlord/auction/bid/view/{bid_id}` unauthenticated → read any bidder's data.
- **Fix:** Require auth + restrict to listing owner/assigned agent. · **Effort:** S

### CRIT-4 — Public, unauthenticated counter-bid write (arbitrary `bid_id`)
- **Severity:** Critical · **Root cause:** `counterBiding` and `sellerCounterBid` POST routes are registered at top level **outside any auth group**. `SellerCounterBidController@store` uses **no `Auth` at all**; `CounterBidController@store` writes a counter-bid + ~150 meta rows onto any `bid_id` with no party verification.
- **Files:** `routes/web.php:389-390`; `CounterBidController.php:33-256`; `SellerCounterBidController.php:17-40`. Live UI references confirm reachability (`resources/views/seller_property/add-counter-bid.blade.php:159`, `view.blade.php:3962`).
- **Repro:** `POST /property/counter/bid/{anyBidId}` with no/any session writes a counter-bid onto any auction.
- **Fix:** Move into `auth` group + verify caller is the opposing party. · **Effort:** M

### CRIT-5 — `destroyCounter`: any authenticated user can hard-delete any seller bid by ID
- **Severity:** Critical · **Root cause:** "Reject counter" is implemented as a hard `delete()` keyed only on the path ID, no ownership check.
- **Files:** `SellerCounterBidController.php:59-64`; `routes/web.php:796`
- **Repro:** `POST /hire/agent/seller/destroy/counter/{id}` with any account permanently deletes that `SellerAgentAuctionBid`.
- **Fix:** Verify actor is listing owner; soft-status as `rejected` instead of deleting. · **Effort:** S

### CRIT-6 — Dashboard notification "View" button 500s for most counter-bid notifications
- **Severity:** Critical · **Root cause:** `NotificationController::resolveDestination()` generates `route()` URLs for route names that **do not exist** — `agent.buyer.agent.auction.bid.view-counter` (:78), `buyer.…view-counter` (:80), `landlord.…view-counter` (:68), `tenant.…view-counter` (:84). Only the seller name exists. No `Route::has()` guard → `RouteNotFoundException` → HTTP 500.
- **Files:** `NotificationController.php:63-89`; `resources/views/dashboard.blade.php:842`; payload `CounterBidSubmittedNotification.php:53-54`
- **Repro:** As buyer/agent on buyer/landlord/tenant auctions, receive a counter bid → click "View" on the dashboard card → 500.
- **Fix:** Correct the route names or guard `go()` with `Route::has()` → fallback to dashboard. · **Effort:** S

---

## 4. High Issues

**Launch-blocking Highs (must fix before launch):**

| ID | Title | Files | Blocker |
|---|---|---|---|
| HIGH-1 | `AgentAuth` middleware is **inverted** — only redirects `seller_agent`/`buyer_agent`; real agent type is `agent`, so **consumers can reach agent bid forms** | `app/Http/Middleware/AgentAuth.php:20-24`; route groups `routes/web.php:723,754,755,758,826` | YES |
| HIGH-2 | **No "cannot bid on your own listing" guard** (all 4 roles) — agent-created listings can be self-bid | `Buyer/Seller/Tenant/Landlord *AgentAuctionBid.php` (mount/submit) | YES |
| HIGH-3 | **Duplicate bids allowed** — only Landlord prevents; Buyer/Seller/Tenant `new …BidData()->save()` unconditionally; no unique `(user_id,auction_id)` index | `Buyer…Bid.php:1054`, `Seller…Bid.php:991`, `Tenant…Bid.php:1310` vs `Landlord…Bid.php:1459-1467` | YES |
| HIGH-4 | **Consumer sees wrong agent data** — "Agent Highlights" strip reads from live `AgentDefaultProfile`, not the submitted bid meta (Buyer/Seller/Landlord); hidden entirely if agent never saved a default profile | `partials/bid_detail_body/buyer.blade.php:299-362`, `seller.blade.php:249-258`, `landlord.blade.php:284-296` | YES |
| HIGH-5 | **Legacy `*CounteredTermsController` store/update have no authorization (IDOR)** — all 4 roles + agent; `update` is live via edit blades | `Seller/Buyer/Landlord/Tenant CounteredTermsController`, `AgentCounteredTermsController`, `CounteredTerms.php` | YES |
| HIGH-6 | **Submitted offers never auto-expire** — `ExpireOffersCommand` filters native `offers.expires_at`, but submit writes the deadline only to **meta**; native column stays NULL → never expires (counters do expire) | `ExpireOffersCommand.php:19` vs `OfferController.php:1257`; `OfferSubmissionService::submit()` | YES |
| HIGH-7 | **Listing-date tampering** — `renew_save` (all 4 roles) rewrites `listing_date`/`expiration_date` from `$request->id` with no auth/ownership (routes outside `auth`) | `routes/web.php:306-309`; `PropertyAuctionController.php:1702-1710`, `TenantCriteriaAuctionController.php:722-728`, `BuyerCriteriaAuctionBidController.php:441-447`, `LandlordAuctionController.php:1317-1324` | YES |

**Serious Highs (strongly recommended before launch; not all hard blockers):**

| ID | Title | Files | Blocker |
|---|---|---|---|
| HIGH-8 | Create and Edit are **different components** → systemic field/validation parity drift; intended `SellerAgentAuctionEdit`/`BuyerAgentAuctionEdit`/`LandLordAgentAuctionEdit` are **orphaned dead code** | `routes/web.php:514,592` (create) vs `:593,662` (edit) | No |
| HIGH-9 | **Required-field set differs** between create-submit and edit-submit (e.g. create requires `property_type/state/current_status`; edit requires `listing_date/expiration_date/meeting_Preference` instead) | create `SellerAgentAuction.php:2921-2929` vs edit `TenantAgentAuctionEdit.php:3261-3268` | No |
| HIGH-10 | Seller/Buyer **compatibility preferences not validated on edit** (edit only validates the `tenant_specific` branch) | `TenantAgentAuctionEdit.php:3271-3278` | No |
| HIGH-11 | `BuyerCounteredTermsController@update` writes **non-existent column** `tenant_auction_id` (copy-paste from Tenant) → SQL error 500; store() also targets non-existent columns | `BuyerCounteredTermsController.php:82`; schema `2025_09_30_152717_create_buyer_counter_terms_table.php` | No (dead path) |
| HIGH-12 | **Accepted Bid Summary PDF cache never invalidated** on terms change — `regenerate*` methods exist but have no caller; serve logic returns cached file unconditionally (mitigated: terms freeze at acceptance) | `AcceptedBidSummaryService.php:344-361`, `SellerAcceptedBidSummaryService.php:928-935`; `AcceptedBidSummaryController.php:179-191` | No |
| HIGH-13 | **"Counter Rejected" fires no notification** for any role (`CounterBidRejectedNotification` dispatched nowhere) | `Buyer/Seller/Tenant/Landlord` reject handlers | No |
| HIGH-14 | **"Listing Published/Approved" notification missing** for all 4 hire-agent roles (only `OfferAuction` notifies) | `SellerAgentAuctionController.php:712-719` + landlord/tenant/buyer approve methods | No |
| HIGH-15 | **"Bid Updated" notification inconsistent** — Seller sends nothing on edit; Buyer sends wrong type (`BidSubmittedNotification`) on edit | `SellerAgentAuctionBid.php:1142-1153`, `BuyerAgentAuctionBid.php:1256-1262` | No |
| HIGH-16 | **Offer Withdrawn notifies the withdrawer (self)**, not the listing owner | `OfferController.php:324` | No |
| HIGH-17 | **Duplicate notification on Offer Counter** — same party gets `OfferCounteredNotification` **and** `OfferSubmittedNotification` | `OfferController.php:720,730` | No |
| HIGH-18 | Agent-bid **Withdraw** exists only for Tenant and sends no notification | `routes/web.php:696`; `TenantAgentAuctionBidController.php:582` | No |

---

## 5. Medium Issues

| ID | Title | Files |
|---|---|---|
| MED-1 | Legacy guard-less bid write paths persist **mismatched meta keys** (Buyer/Landlord) → bids submitted via them render blank | `BuyerAgentAuctionBidController::saveBABid`, `LandlordAgentAuctionBidController::save_bid/saveCounterBid`; routes `:551,830,834` |
| MED-2 | Availability / Experience / **Service-Area fields written on every role's bid but never displayed** to the consumer | submit handlers buyer `:1239-1250`, seller `:1088-1099`, landlord `:1696-1707`, tenant `:1562-1573` |
| MED-3 | Landlord **`retained_deposits` wiped on edit-resave** (not re-hydrated) and never displayed | `LandlordAgentAuctionBid.php:186,1598` |
| MED-4 | Seller **`video_upload` is read by the review view but never written** → always empty | `partials/bid_detail_body/seller.blade.php`; `SellerAgentAuctionBid.php submit()` |
| MED-5 | Landlord **retainer-fee & split-payment terms written but never displayed** | `LandlordAgentAuctionBid.php:1541-1542,1577-1579` |
| MED-6 | **Tenant has no dedicated bid-detail partial** — review split across two views with partial parity | `hire_tenant_agent/view.blade.php`, `tenant_agent/bid_preview.blade.php` |
| MED-7 | `storage_space` create/edit **parity gap** — editable on edit but never loaded/saved (silent drop) | `TenantAgentAuctionEdit.php:202`; `leasing-terms.blade.php:168,246` |
| MED-8 | **Analytics observer never matches `listing_type`** (`*_offer` vs `seller/buyer/…`) → `agent_hired` funnel/attribution silently broken | `AcceptedBidSummaryAnalyticsObserver.php:26-31` vs services `:293/:396/:305` |
| MED-9 | **Seller accepted-summary omits** agency timeframe / protection period / brokerage relationship / early-termination / `additional_details_broker` | `SellerAcceptedBidSummaryService.php:747-792,949-963` |
| MED-10 | Counter-term Livewire components **don't verify actor is a party** (Buyer/Landlord/Tenant; only Seller does) | `Buyer/Landlord/Tenant AgentAuctionCounterTerm.php` |
| MED-11 | Offer authorization **trusts user-controlled role string** for `system`/`admin` bypass (exploitable only if `role`/`user_type` is user-settable — **unverified**) | `OfferPermissionService.php:88,122,156,241`; `OfferController.php:213` |
| MED-12 | "**Mark read" deletes** the notification (no read history); inconsistent with Dismiss; `go()` doesn't mark read | `NotificationController.php:21-42,143-195` |
| MED-13 | Header notification dropdown has **no deep-link** — clicking only deletes; "tap View" copy misleading | `header.blade.php:223-227,528-532` |
| MED-14 | Dashboard notification **filter omits real types** (`OfferExpired`, `CounterBidRejected`) and references **non-existent class** `BidCounteredNotification` | `DashboardController.php:48-60` |
| MED-15 | "**Counter Accepted" has no dedicated notification** (reuses generic `BidAcceptedNotification`; `CounterBidAcceptedNotification` is dead) | `DashboardController.php:50`; bid controllers |
| MED-16 | **"Listing Closed / Sold" has zero notification coverage** (all roles) | (no dispatch site found) |
| MED-17 | **Duplicate route name** `hire.agent.auction.bid.view-counter` (defined 3×) → `route()` resolves only Tenant | `routes/web.php:614,646,702` |
| MED-18 | **Duplicate / malformed route names** — `admin.` on 5 routes, `agent.` on 3, `buyer.edit-counter-terms` on 2 (group prefix left without suffix) | `route:list` (admin/agent/buyer groups) |
| MED-19 | **Near-absent policy layer** — only `ShowingPolicy` exists/registered; listings, bids, counters have no policies (root cause of the IDOR cluster) | `app/Policies/`, `AuthServiceProvider.php:21` |
| MED-20 | **`$guarded = []` on 11 models** (fully open mass assignment; no current `request->all()` exploit found, latent risk) | `BuyerAgentAuction.php:13`, `SellerAgentAuction.php:13`, `LandlordAgentAuction.php:14`, `PropertyAuctionBid.php:12`, + 7 counter-term models |
| MED-21 | **No foreign-key constraints** on core relational columns (`user_id`, `*_id`) → orphan rows / null-deref 500 risk | core auction/bid/counter migrations |
| MED-22 | Native-column vs EAV deviation from documented contract — wizard writes domain fields only to meta, leaving native columns empty (verify search/match-scoring consumers) | create persistence `SellerAgentAuction.php:3062-3085`, `BuyerAgentAuction.php:2372-2391` |
| MED-23 | Renew GET forms + public listing views (`viewPropertyListing`, landlord `view`) **leak listing/bid data** with no `display_bids` gate | `routes/web.php:303-305`; `PropertyAuctionController.php:1427-1440`, `LandlordAuctionController.php:551-561` |
| MED-24 | Locked fields (`auction_type`, `working_with_agent`, `auction_time`) **silently un-editable** with no UI message | `TenantAgentAuctionEdit.php:3326-3346` |

---

## 6. Low Issues

| ID | Title | Files |
|---|---|---|
| LOW-1 | Seller leasing-fee "Other" validation compares lowercase `'other'`; UI option casing may never trigger the required rule | `SellerAgentAuction.php:2948` |
| LOW-2 | Duplicate array key `'closing_appointment'=>false` (harmless overwrite) | `BuyerAgentAuction.php:491-492` |
| LOW-3 | **Photo/video uploads have no mime/size validation** in submit rules | seller/buyer create store rules |
| LOW-4 | `saveDraft()` runs **zero validation** (by design) → fully empty drafts persist | create + edit components |
| LOW-5 | First counter-bid passes the price-floor guard regardless of amount (max over empty set = NULL) | `CounterBidController.php:38-40`, `SellerCounterBidController.php:22-24` |
| LOW-6 | Buyer `accept_buyer_counter_term` lacks the double-accept guard other roles have | `BuyerAgentAuctionBidController.php:499-531` |
| LOW-7 | Tenant bid `submit()` lacks a `listing_status` guard (can bid on Pending/Hired tenant listing) — add inline check (do **not** refactor into the trait per CLAUDE.md) | `Tenant/…Bid.php:1278-1287` |
| LOW-8 | Marketing track-record fields (`awards_recognition`, `sold_listed_examples`, `marketing_success_examples`) written but never displayed (all roles) | submit handlers |
| LOW-9 | Landlord dead/unpersisted properties + referral key mismatch (`referral_percentage` read vs `referral_fee_percent` saved) | `Landlord/…Bid.php:201-207,792,1601` |
| LOW-10 | Badge undercounts when >20 unread (`take(20)` cap overwrites server count) | `NotificationController.php:15`; `header.blade.php:179` |
| LOW-11 | Redundant unread queries per page load (eager + on-load AJAX + 30s poll) | `header.blade.php:172,602,609` |
| LOW-12 | Dead no-op event listeners | `EventServiceProvider.php` |
| LOW-13 | Edit save uses `find()` not `firstOrFail()` → null-deref risk on stale ID | `TenantAgentAuctionEdit.php:2392,2431,3313` |
| LOW-14 | Vestigial create-only meta keys (`landlord_broker_flate_fee_type`) | `TenantAgentAuction.php:4758` |
| LOW-15 | AJAX helper endpoints unauthenticated (CSRF-protected; low sensitivity — confirm no private echo) | `routes/web.php:298-301` |

---

## 7. Categorized Findings

### Duplicate Fields
- LOW-2 duplicate array key `closing_appointment` (buyer create). No other true duplicate **form** fields found; the larger risk is the **dual create/edit components** (HIGH-8) which duplicate *entire field sets* across divergent code.

### Missing Fields / Unsurfaced Data
- HIGH-4 (Agent Highlights wrong source), MED-2/4/5 (Service Areas, Seller `video_upload`, Landlord retainer/split-payment all collected but not shown), MED-6 (Tenant has no unified bid view), MED-9 (Seller accepted-summary missing agency terms), LOW-8 (marketing track-record unshown).

### Create / Edit Parity
- HIGH-8 (different components), HIGH-9 (different required sets), HIGH-10 (compat prefs unvalidated on edit), MED-7 (`storage_space` silently dropped), MED-24 (locked fields).

### Marketplace
- HIGH-1 (inverted role guard), HIGH-2 (own-listing bid), HIGH-3 (duplicate bids), MED-1 (legacy mismatched-key write paths).

### Agent Bid
- HIGH-2/3/4, MED-2/3/4/5, LOW-8/9. **Positive:** `AgentBidMapperService` is a comprehensive superset across all 4 roles (`AgentBidMapperService.php:42-246`).

### Counter / Negotiation
- CRIT-4, CRIT-5, HIGH-5, HIGH-11, MED-10, LOW-5/6. **Positive:** the modern `OfferStateMachineService` (`:32-110`) blocks all illegal transitions and accept/reject/withdraw enforce party ownership.

### Accepted Bid Summary
- HIGH-12 (PDF cache), MED-8 (analytics), MED-9 (Seller content). **Positive:** no unreplaced `{{placeholder}}` leak path; download gated on both-party signature; correct authorization; Tenant summary present.

### Notifications
- CRIT-6, HIGH-13/14/15/16/17/18, MED-12/13/14/15/16/17, LOW-10/11/12.

### Dashboards
- CRIT-6 (View button). **Positive:** well-optimized (bulk listing cache `DashboardController.php:130-147`, eager loading, safe zero-state) — **no N+1 found**.

### Authorization
- CRIT-1/2/3/4/5, HIGH-1/5/7, MED-10/11/19/23. Root cause: **routes outside the `auth` group + absent policy layer**.

### Validation
- HIGH-9/10, MED-7, LOW-1/3/4. Hand-rolled, divergent between create and edit; no upload constraints.

### Database
- MED-20 (mass assignment), MED-21 (no FKs), MED-22 (native vs EAV). **Positive (verified):** `config/match_scoring.php` enabled weights resolve correctly (services 35 + terms 35 normalize per documented formula; dimension weights sum to 100).

### Browser / Runtime
- **NOT TESTED** — no live browser run (Playwright binaries absent). Console errors, hydration, live 404/500, websocket auth, queue delivery all **unverified**.

### UI / UX
- MED-13/24, LOW-4. Misleading notification copy; silent field locks; empty-draft persistence.

### Performance
- **Positive:** dashboards optimized. **Not load-tested.** Latent 500 risk from no-FK + unguarded `find()` chains (MED-21).

### Security (consolidated)
- **6 Critical + 4 launch-blocking High** all trace to two patterns: (1) state-mutating/data-exposing routes placed **outside** the `auth` middleware group, and (2) **no ownership/policy check** inside the handler. **Positive (verified):** `.env` gitignored & no committed secrets; `APP_DEBUG` defaults false; `Handler.php` doesn't leak stack traces; dev-login impersonation routes are env-gated to non-production.

### Technical Debt
- Orphaned components (`*AgentAuctionEdit`, `LandLordAgentAuctionEdit` — 124 KB dead), duplicate/legacy controllers and routes, duplicate route names, dead notification classes, no-op listeners, `$guarded=[]` models, absent policy layer.

---

## 8. Production Launch Checklist

Legend: ✅ PASS · ❌ FAIL · ⚠️ NOT TESTED

### Security & Authorization
- ❌ All state-mutating routes behind `auth` middleware (CRIT-2/3/4, HIGH-7)
- ❌ Ownership/authorization enforced on listing edit (CRIT-1)
- ❌ Ownership enforced on counter create/delete (CRIT-4/5, HIGH-5)
- ❌ Role middleware enforces agents-only on bid forms (HIGH-1)
- ❌ Consumer financial/contact PII protected from enumeration (CRIT-3)
- ❌ Policy layer covers Auction/Bid/Counter models (MED-19)
- ⚠️ `role`/`user_type` not user-settable (MED-11 — unverified)
- ✅ Secrets not committed; `.env` gitignored
- ✅ `APP_DEBUG=false` default; no stack-trace leakage *(confirm prod `.env` at deploy)*
- ✅ Dev-login impersonation routes env-gated to non-prod

### Consumer Listing (Phase 1)
- ⚠️ Create → Save Draft → Edit → Update → Submit, each property type (NOT browser-tested)
- ❌ Create/Edit field & validation parity (HIGH-8/9/10, MED-7)
- ❌ Edit authorization (CRIT-1)
- ⚠️ Per-property-type conditional rendering (NOT TESTED)
- ❌ Upload mime/size validation (LOW-3)

### Agent Marketplace & Bids (Phase 2-3)
- ❌ Agents-only access (HIGH-1)
- ❌ No self-bid / no duplicate bids (HIGH-2/3)
- ❌ Consumer review shows the submitted bid values (HIGH-4, MED-2/4/5)
- ✅ Profile/preset auto-populate mapper complete (verified)
- ⚠️ Upload storage security (mime/size/path) — NOT TESTED

### Negotiation (Phase 4)
- ❌ Counter endpoints authenticated & party-checked (CRIT-4/5, HIGH-5)
- ✅ Modern offer state machine blocks illegal transitions (verified)
- ❌ Submitted offers expire at deadline (HIGH-6)

### Final Agreement (Phase 5)
- ✅ Placeholder replacement / no `{{ }}` leak; both-party signature gate (verified)
- ❌ PDF invalidation on terms change (HIGH-12)
- ❌ Seller summary content complete (MED-9)
- ❌ Hire-funnel analytics fire (MED-8)

### Notifications (Phase 6)
- ❌ Dashboard "View" deep-links resolve (CRIT-6)
- ❌ Coverage for Published / Counter-Rejected / Listing-Closed (HIGH-13/14, MED-16)
- ❌ Correct recipient & type (HIGH-15/16, MED-15)
- ❌ No duplicate notifications (HIGH-17)
- ⚠️ Queue/broadcast delivery & websocket auth (NOT TESTED)

### Dashboards (Phase 7)
- ✅ Query performance / no N+1 / safe zero-state (verified)
- ❌ Action buttons resolve (CRIT-6)

### Data & Config
- ✅ `match_scoring` weights valid (verified)
- ❌ FK constraints / mass-assignment hardening (MED-20/21)
- ⚠️ Native-column consumers (search/match) unaffected by EAV-only writes (MED-22 — NOT TESTED)

### Runtime / Browser
- ⚠️ No console errors / no unexpected 404/500 (NOT TESTED)
- ⚠️ Livewire hydration across wizards (NOT TESTED)
- ⚠️ End-to-end happy path per role × property type (NOT TESTED)

---

## 9. Final Certification

**1. Would you personally approve BidYourAgent for production launch today?**
**No.**

**2. If NO, explain exactly why.**
The application contains **6 confirmed Critical access-control vulnerabilities** and **multiple launch-blocking High issues**, including: any logged-in user can edit/overwrite any user's listing (CRIT-1); anonymous users can end auctions (CRIT-2), read other bidders' financial/contact PII (CRIT-3), and write counter-bids onto arbitrary bids (CRIT-4); any authenticated user can permanently delete any seller bid (CRIT-5); a core dashboard action 500s for most counter-bid notifications (CRIT-6); consumer role enforcement is inverted (HIGH-1); marketplace integrity guards (own-listing, duplicate-bid) are missing (HIGH-2/3); consumers are shown **stale/incorrect agent data** instead of submitted bid values (HIGH-4); and **submitted offers never expire** (HIGH-6). Shipping these would expose user financial/PII data and allow trivial tampering with other users' records. Separately, large swaths of the system (per-property-type rendering, end-to-end browser flows, runtime errors) are **untested** and cannot be certified.

**3. Every remaining task before launch, in priority order** — see §10.

**4. Must-Fix vs Can-Wait** — see §10.

---

## 10. Remediation Plan

### 🔴 MUST FIX BEFORE LAUNCH (in priority order)

**Tier 1 — Security / access control (highest priority; mostly low-effort, concentrated root cause):**
1. CRIT-1 — Add ownership scoping to listing edit load + save (all 4 roles). *(S)*
2. CRIT-2 — Move 4 `endAuction` routes into `auth` + owner check. *(S)*
3. CRIT-3 — Auth-gate + ownership on `viewBid`. *(S)*
4. CRIT-4 — Auth-gate counter-bid endpoints + party check. *(M)*
5. CRIT-5 — Authorize `destroyCounter`; soft-reject instead of delete. *(S)*
6. HIGH-5 — Authorize all `*CounteredTermsController` store/update. *(M)*
7. HIGH-7 — Auth-gate + ownership on `renew_save` (all roles). *(S)*
8. HIGH-1 — Fix inverted `AgentAuth` to positively assert `user_type==='agent'` + re-check in `submit()`. *(S)*
9. MED-11 — Verify `role`/`user_type` cannot be user-set; prefer policy over string equality. *(S, verify-first)*
10. MED-19 — Introduce Auction/Bid/Counter policies (the durable fix underpinning 1–8). *(L)*

**Tier 2 — Marketplace & data integrity:**
11. HIGH-2 — Own-listing bid guard (all roles). *(S)*
12. HIGH-3 — Duplicate-bid prevention + unique index (3 roles). *(M)*
13. HIGH-4 — Source Agent Highlights from submitted bid meta, not default profile. *(M)*
14. HIGH-6 — Mirror offer deadline to native `expires_at` on submit. *(S)*
15. MED-1 — Remove/neutralize legacy mismatched-key bid write paths. *(M)*
16. HIGH-11 — Remove/repair `BuyerCounteredTermsController` non-existent-column writes. *(S)*

**Tier 3 — Core UX correctness:**
17. CRIT-6 — Fix/guard notification `view-counter` route resolution. *(S)*
18. HIGH-15/16/17 — Correct Bid-Updated type, Offer-Withdrawn recipient, de-dupe Offer-Counter. *(S)*
19. HIGH-9/10, MED-7 — Reconcile create/edit required-field & compatibility validation; persist `storage_space`. *(M)*
20. LOW-3 — Add upload mime/size validation. *(S)*

**Tier 4 — Pre-launch verification (cannot certify without this):**
21. Execute end-to-end browser tests: login → create (each property type) → submit → agent bid → review → counter → accept → accepted summary → notifications, per role. *(L)*
22. Confirm per-property-type conditional rendering for all 5 Seller/Buyer + 2 Landlord/Tenant types. *(M)*
23. Confirm deployed `.env`: `APP_ENV=production`, `APP_DEBUG=false`. *(S)*

### 🟡 CAN SAFELY WAIT UNTIL AFTER LAUNCH
- HIGH-8 — Consolidate create/edit into one component; delete orphaned `*Edit` dead code. *(L, refactor)*
- HIGH-12 — Wire PDF cache invalidation (mitigated by terms-freeze). *(S)*
- HIGH-13/14, MED-16 — Add Counter-Rejected / Listing-Published / Listing-Closed notifications. *(M)*
- HIGH-18, MED-15 — Agent-bid withdraw across roles; dedicated Counter-Accepted notification. *(M)*
- MED-2/5/6/9 — Surface unshown bid/summary sections; unified Tenant bid view; Seller agency terms *(escalate MED-9 to must-fix if those terms are legally required on the agreement doc)*. *(M)*
- MED-8 — Fix analytics `listing_type` mapping. *(S)*
- MED-3/4 — Landlord `retained_deposits` hydration; Seller `video_upload` persistence. *(S)*
- MED-12/13/14 — Notification read-history, header deep-links, dashboard filter list. *(S)*
- MED-17/18 — De-duplicate route names. *(S)*
- MED-20/21 — `$fillable` hardening; FK constraints. *(M-L)*
- MED-22/23/24, LOW-1/2/5/6/7/8/9/10/11/12/13/14/15 — cleanup, minor guards, dead code. *(S each)*

---

## 11. Coverage Statement

**Verified (cited):** routing & middleware nesting; create/edit/submit logic for all 4 roles; EAV meta read/write parity; agent bid submit/edit/withdraw + consumer review write/read parity; `AgentBidMapperService`; offer state machine, permissions, expiration; legacy counter controllers; accepted-summary services (all 4 roles), controller, observer; notification dispatch sites & recipients for all 11 events; dashboard queries; policies, routes (`route:list`), mass assignment, secrets, DB schema sampling, `match_scoring`, error handler, debug config.

**NOT verified (must test before launch):** live browser flows; per-property-type conditional rendering; runtime 404/500; Livewire hydration; queue/broadcast/websocket delivery; upload storage security; whether `role`/`user_type` is user-settable (gates MED-11 exploitability); downstream native-column consumers (gates MED-22 blast radius); live DB row state / existing duplicates.

*End of report.*
