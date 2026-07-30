# TIMED_OFFER_RUNTIME_INVESTIGATION

**Single source of truth for the Timed-Offer Listings investigation and repair.**

| Field | Value |
|---|---|
| Document status | **Active — master investigation artifact** |
| Created | 2026-07-27 |
| Last updated | 2026-07-27 |
| Audit worktree | `/home/runner/audit-timed-offer-flow` |
| Audit branch | `audit/timed-offer-flow` |
| Commit under audit (HEAD) | `b8713452d47bdcd6cb8502f32c9b86e3f0e3907b` |
| `origin/main` at audit time | `260d83f85` |
| Local `main` at audit time | `10037715a` |
| Audit mode | Read-only. No application code created, modified, staged, or committed. |
| Scope constraint | Committed repository state at HEAD and its git history only. Uncommitted changes in `/home/runner/workspace` were deliberately excluded and never inspected. |

**Maintenance protocol.** This document is updated throughout the repair process. Every stage that lands must update: the relevant matrix rows, the deviation register (§10), the test-gap table (§12), and the change log (§16). Do not delete superseded findings — strike them through and date the change, so the investigation record stays auditable.

---

# Executive Investigation Summary

## 1. What the approved architecture required

The approved Timed Listing Architecture artifact — itself a read-only design marked *pending approval*, explicitly stating that **no code or migrations had been created** — required eleven things:

- The bidding timer belongs to `OfferAuction`, not to legacy listing-expiration fields.
- `auction_time` is **only** a listing-creation wizard input.
- At publication: stamp `bidding_starts_at` from server time, and compute `bidding_ends_at` from `bidding_starts_at` + the selected duration.
- After publication, `bidding_ends_at` is the **sole** runtime source of truth.
- `auction_time` must never be repeatedly recalculated at runtime.
- The Listing Expiration Date must **never** be used, referenced, substituted, or used as a fallback for the Bidding Period timer.
- Two clocks must never be merged: listing `bidding_ends_at` (may new bids be submitted?) versus per-offer `expires_at` (response deadline for one specific offer).
- Seller timed listings require a public consumer bidding surface: countdown, exact deadline and timezone, total offer count, anonymous Bid #N, offer amount, financing type, active/revised/withdrawn/countered status, and identification of the logged-in consumer's own bid where authorized.
- Landlord timed listings require: public countdown, exact deadline and timezone, public application count only, sealed private applications for the owner or authorized agent, and no public rent-bidding feed.
- The owner side requires a dedicated "offers received on my listing" aggregation and management route. **The artifact itself identified the absence of this route as an existing gap.**
- Delivery required separate build batches for: domain state machine, database timer fields, server enforcement, seller UI, landlord UI, anonymous bid feed, notifications, and security/end-to-end testing.

## 2. What actually exists in HEAD

A **countdown badge and an offer-drafting workflow** — nothing else from the architecture.

- A Bidding Period badge renders on listing cards and on listing detail pages for all four roles.
- A working private offer workflow: create draft, submit, accept, reject, counter, withdraw, cancel, PDF, plus seven notification classes and thirteen offer services.
- **No canonical bidding-deadline storage of any kind.** `bidding_starts_at` and `bidding_ends_at` have **zero occurrences** anywhere in HEAD — no migration, model, service, view, route, or test.
- Instead of one deadline, **five mutually inconsistent runtime derivations** of "when bidding ends."
- No bid count, no anonymous Bid #N, no public bid feed, no owner offers-received route, and **no server-side enforcement of any bidding deadline whatsoever**.

## 3. What exists only on branches

The bulk of the timed-listing implementation was written and never landed. `phase-timed-offer-listings` (tip `2580415e6`), with siblings `phase-timed-listings-notifications` and `backup/timed-listings-2026-07-21`, contains roughly 38 files: listing state machine, `ListingTimerService`, `BidNumberAllocator`, `AnonymousBidFeedSerializer`, `BidFeedController`, `ListingOffersController`, `OwnerOfferComparisonPresenter`, `resources/views/listings/offers-received.blade.php`, `ApprovedBiddingDuration` rule, `TimedListingOfferGuard`, `CloseBiddingCommand`, `config/timed_listings.php`, and their tests.

**None of these commits is an ancestor of HEAD or of `origin/main`.** The branch is also stale: diffed against HEAD it shows 16,717 deletions, because it forked before the Phase-2 spatial and storage-seam work. It cannot be merged as-is without reverting shipped work.

## 4. What exists only in `origin/main`

`origin/main` (`260d83f85`) is **four commits ahead of local `main`** and is not contained in HEAD. It carries `8a3a5a918`, *"feat(bidding-period): anchor the bidding window to a canonical activation stamp"* (PR #50, merged 2026-07-27) — 27 files, ~2,895 insertions: `BiddingWindowService`, `BiddingWindow`, `PublicOfferFeedService`, `ListingOfferAuctionLinker`, `StampsBiddingActivation`, `_competing-bids.blade.php`, a `bidding_started_at` migration, and five substantial test files.

That commit's own message independently corroborates this investigation's findings. **However, it does not satisfy the approved architecture** — see deviation D-13: it stores `bidding_started_at` and *recomputes* the deadline at runtime rather than persisting `bidding_ends_at`, and it deliberately retains an `expiration_date` legacy fallback.

## 5. What is completely missing

Missing from **every** branch, not merely from HEAD:

- **`bidding_ends_at` — never created anywhere, on any branch, at any point in history.**

Missing from HEAD (exists only on unmerged branches): canonical activation stamp, deadline enforcement, bid count, anonymous Bid #N, public bid feed, owner offers-received dashboard, landlord sealed-application surface, publish-time duration validation, and the listing state machine.

## 6. Why the countdown is wrong

**The listing card and the listing detail page apply inverted priority to the same two fields.**

- **Card → `4d 11h` (correct).** `offer-listing/seller/search.blade.php:213-248` computes `created_at + auction_time` and nothing else. A 5-day listing created ~13h ago yields 4d 11h.
- **Detail → `38d 6h` (defective).** `offer-listing/seller/view.blade.php:1474-1510` checks `expiration_date` **first** and only falls back to `auction_time` if expiration is absent. The listing's `expiration_date` is ~39 days out.

The `6h` remainder is decisive proof of source. `Carbon::parse()` on a date-only string returns **midnight**; app timezone is `UTC`. At ~17:52 UTC, midnight is 6h08m away — producing `38d 6h` rather than a whole number of days. No other field yields that remainder.

The defect was introduced deliberately by **`c729357b1`** (2026-06-02, Task #1811), which is an ancestor of HEAD. That commit resolved a hero-vs-sidebar disagreement by standardising all four detail pages onto `expiration_date`, hard-wiring the prohibited dependency, and left the four search cards untouched — converting a page-local inconsistency into the card-vs-detail split now observed.

**This is the exact dependency the architecture prohibits, and it is not a fallback — it is the primary source.**

## 7. Why the consumer bidding UI is missing

**It was never built.** Not hidden by conditional rendering, not blocked by authorization, not a data problem.

What exists at HEAD is an *offer-drafting workflow*, not a *bidding surface*. A "Submit Offer" button posts to `offers.store`, creates a `status='draft'` offer, and redirects to a private offer detail page. There is no count, no feed, no anonymity layer, no own-bid identification, and no deadline gate. The render conditions that do exist all pass correctly — the surfaces are simply absent from the build.

The listing in the screenshots is therefore a **timed listing in name only**: the badge counts down against the wrong field while the server enforces no deadline at all.

## 8. Why the owner dashboard is missing

**Never implemented at HEAD; written on an unmerged branch.**

`MyOffersController::index` is a three-line query — `Offer::where('user_id', Auth::id())` — returning offers the user *made*. No route, controller, or view anywhere in HEAD aggregates offers *received* on a listing. The seller has per-offer decision endpoints (accept/reject/counter) but **no inbox**: those endpoints are reachable only if the seller already holds a specific offer ID.

`ListingOffersController`, `OwnerOfferComparisonPresenter`, `ListingManagementAccess`, and `resources/views/listings/offers-received.blade.php` exist on `phase-timed-offer-listings` (commit `5fef88f2c`) and were never merged. **The gap the architecture artifact explicitly predicted remains open.**

## 9. Was the approved architecture actually implemented?

**No. Zero of the eleven artifact requirements are implemented in the committed runtime at HEAD.**

Requirements 6 and 7 are not merely unimplemented — they are **actively violated** by code that shipped and is live.

The prior work resolves into three disconnected pools: a design artifact that never became schema; an unmerged, now-stale feature branch; and a partial corrective commit on `origin/main` that HEAD does not contain and that itself deviates from the approved design. Applying the required evidentiary standard — design documents, unused classes, unmounted Blade components, isolated tests, and unmerged branches do not count as implementation — **the architecture exists on paper and in branches, and not in the product.**

## 10. Current recommendation before any code changes

**Do not begin implementation until two decisions are made. Both are the owner's call, and everything downstream depends on them.**

- **Decision A — the fate of `8a3a5a918`.** It sits on `origin/main` but not in HEAD, and it does **not** satisfy the approved architecture (D-13: no persisted `bidding_ends_at`; runtime recomputation; retained `expiration_date` legacy fallback). Accept it as a pragmatic interim, or amend it to the artifact? Building Stage 1 on top of `bidding_started_at` without settling this entrenches the deviation.
- **Decision B — may live deadlines move?** Correcting the timer source will visibly change the displayed deadline on every active listing with a populated `expiration_date`. That needs a product and communications decision, not just a code change.

Two further recommendations, offered as engineering judgement rather than open questions:

- **Reconcile branches before writing anything.** Local `main` is four commits behind `origin/main`; rebasing HEAD changes the defect surface. Sequence this first.
- **Land the regression lock with the source fix, not after it.** The absence of a single countdown assertion is why `c729357b1` survived eight weeks and multiple audits. Verified: **zero** tests match `Bidding Period Time Remaining`, `sol-bp-timer`, `data-seconds`, or `timerRemaining`.

Recover the unmerged work by **cherry-picking onto current `main`** — never by merging `phase-timed-offer-listings`, which would revert 16,717 lines of shipped Phase-2 work.

> **RESOLVED 2026-07-27.** Decisions A and B were subsequently approved by the owner. See **Owner-Approved Architecture Decisions** immediately below, which now governs. The recommendation text above is retained as the state of the investigation at the close of Phase 0.

---

# Owner-Approved Architecture Decisions

**Status: APPROVED — 2026-07-27**

**This section is the permanent architectural contract for the project. It supersedes any conflicting implementation, past or future, including code already merged to `origin/main`.**

Any code that conflicts with this section is by definition a deviation, regardless of when it was written, which branch it sits on, or whether it has already shipped.

---

## Decision A — Canonical Bidding Timer

**Status: APPROVED**

The approved Timed Listing Architecture is the governing design.

The implementation SHALL:

- Persist both `bidding_starts_at` and `bidding_ends_at`.
- Compute `bidding_ends_at` exactly once, when the listing becomes Active.
- Use `bidding_ends_at` as the sole runtime source for every bidding countdown.
- Never recompute the bidding deadline from `auction_time` after activation.
- Never derive, substitute, or fall back to `expiration_date`.
- Keep Listing Expiration completely independent from the Bidding Period.

**Commit `8a3a5a918` is NOT accepted as the final architecture.**

Its useful work may be reused, but it **must be amended to conform to the approved architecture before merge.** Specifically, it currently persists `bidding_started_at` and recomputes the deadline at runtime rather than persisting `bidding_ends_at`, and it retains an `expiration_date` legacy fallback in `legacyDeadline()`. Both conflict with this decision. See deviation **D-13**.

## Decision B — Existing Listings

**Status: APPROVED**

Existing listings shall not receive fabricated bidding windows.

If a listing predates `bidding_starts_at` / `bidding_ends_at` and those values cannot be reconstructed accurately, they shall **remain unset** until explicitly initialized through an approved migration or product workflow.

**The system must never silently invent historical bidding deadlines.**

## Permanent Architectural Invariants

These are permanent engineering rules. They may not be violated without explicit architectural approval.

| # | Invariant |
|---|---|
| 1 | Listing Expiration Date is **NEVER** part of bidding logic. |
| 2 | Listing Expiration Date and Bidding Period are two completely independent business concepts. |
| 3 | There is exactly **ONE** canonical bidding deadline. |
| 4 | That deadline is **stored, not recomputed**. |
| 5 | Every countdown reads the exact same timestamp. |
| 6 | Every enforcement check reads the exact same timestamp. |
| 7 | Every API returns the exact same timestamp. |
| 8 | Every role (Seller, Buyer, Landlord, Tenant) follows the same timer architecture. |
| 9 | Runtime code may never introduce alternate deadline derivations. |
| 10 | `expiration_date` may never appear anywhere in the bidding timer code path. |
| 11 | Any future architectural deviation requires explicit owner approval. |
| 12 | Any implementation that violates these invariants shall be recorded in the Deviation Register (Part XI) **before merge**. |

### Conformance of the current codebase

As of `b8713452d`, the runtime violated invariants **1, 2, 3, 4, 5, 6, 9, and 10**. Invariant 8 held only in the sense that all four roles were broken identically. Invariants 7, 11, and 12 were untested — no bidding-deadline API surface existed, and no deviation register existed before this document.

**Updated 2026-07-27 (Stage 0, on `repair/timed-offer-architecture`, not yet committed).** Invariants **1–10 now hold across the entire offer-listing surface**, enforced by two repository-wide guard tests. Invariant 8 holds in substance: Seller and Landlord resolve a canonical window; Buyer and Tenant display no countdown at all rather than a synthetic one, which is conformance, not exemption. Invariants 11 and 12 are being followed by this document.

### Role support for timed bidding — established by evidence, 2026-07-27

**Buyer and Tenant Bidding Period listings are part of the SAME timed-offer product as Seller and Landlord. They are not a separate concept.** Established from routes, models, database relationships, `OfferAuction` linkage, UI flows and the creation partials — not inferred:

- All four role partials carry a byte-identical `Bidding Period` option, the same tooltip text, and the same required "Bidding Period Length" field. Seller (`:192`) and Landlord (`:247`) are ungated; Buyer (`:185`) and Tenant (`:184`) wrap the identical block in `@if (config('bya_beta.bidding_period_enabled'))`, which defaults `false`.
- `config/bya_beta.php:30-37` documents that flag as a **creation-UI rollout switch**, explicitly stating that listings already saved as `Bidding Period` "continue to function normally". The in-repo comment reads *"Bidding Period **restored** for Seller … Buyer/Tenant **remain** Traditional-only via the gate"* — sequencing language, not a product boundary.
- `TenantOfferListing:4042-4043` enforces `auction_time` as required when `auction_type === 'Bidding Period'`; both Buyer and Tenant persist the pair through the same `saveMeta()` idiom as Seller. No divergence exists in the data model.
- Buyer and Tenant listings **do** obtain `OfferAuction` rows — the table that carries `bidding_starts_at` / `bidding_ends_at`. Offers are genuinely submitted against them today.

**Their countdown removal in Stage 0 is TEMPORARY.** It is correct only because no valid publish-time `bidding_ends_at` currently exists for those roles.

**Why the existing bridge is unsuitable for timer activation.** `OfferController::resolveOfferAuctionId()` (`:1821-1848`) creates a lazy bridge `OfferAuction` keyed `listing_id = "buyer_criteria:{id}"` / `"tenant_criteria:{id}"` **at first offer submission, not at listing publication**. A window stamped there would begin when the first bidder arrived rather than when the listing went live — reproducing exactly the class of defect (anchoring to the wrong lifecycle event) that this work exists to eliminate. Additionally `ListingOfferAuctionLinker::ensureFor()` accepts only `'seller'` and `'landlord'`, and neither Buyer nor Tenant component calls `stampBiddingActivation()` (verified: zero references to `linked_offer_auction_id`, `ensureFor` or `OfferAuction` in those components).

**Binding constraint while the gap exists.** Buyer and Tenant surfaces **must never** fall back to `expiration_date`, **never** derive a deadline from `created_at`, and **never** perform runtime deadline arithmetic. Showing no countdown is the only conforming behaviour until canonical timestamps exist. This is enforced by the repository-wide guard in `CanonicalBiddingWindowTest`.

### Current state of record (2026-07-27)

| Item | Status |
|---|---|
| Seller / Landlord canonical timer | **Implemented in Stage 0** (committed `b34b0cda6`) |
| Buyer / Tenant canonical timer | **Implemented in Stage 1** (branch `repair/buyer-tenant-activation`, uncommitted, pending review) — countdowns RESTORED |
| Buyer / Tenant timed-bidding product support | **Confirmed** — same product, deferred rollout |
| Schema change expected for Stage 1 | **None** — `offer_auctions` already carries both columns for every role |

Scope boundary recorded deliberately: the **Hire-an-Agent** auction surface (`hire_*`, `*AgentAuction*`, `search-buyer-agent-auctions`) also carries `auction_time` countdowns. It is a *separate auction product* on separate tables with no `OfferAuction` linkage, and is outside the timed-offer listing domain these invariants govern. It is named here so its exclusion is a recorded decision rather than an oversight.

---

# Part I — Approved Timed Listing Architecture (summary)

Reproduced as the authoritative specification for this investigation. The artifact was a read-only design marked *pending approval*, and explicitly stated that **no code or migrations had been created**. The existence of the artifact or of a phase branch is therefore **not** evidence of implementation.

| # | Requirement |
|---|---|
| 1 | The timer belongs to `OfferAuction`, not the legacy listing expiration fields. |
| 2 | `auction_time` is only the listing-creation wizard input. |
| 3 | On publication: `bidding_starts_at` is set from server time; `bidding_ends_at` is calculated from `bidding_starts_at` plus the selected auction duration. |
| 4 | After publication, `bidding_ends_at` is the sole runtime source of truth for the bidding deadline. |
| 5 | `auction_time` must not be repeatedly recalculated at runtime. |
| 6 | The Listing Expiration Date must never be used, referenced, substituted, or used as fallback for the Bidding Period timer. |
| 7 | Two different clocks, never merged or confused: listing `bidding_ends_at` controls whether new bids may be submitted; an individual offer's `expires_at` may control a response deadline for that specific offer. |
| 8 | Seller timed listings require a public consumer bidding surface containing: countdown; exact deadline and timezone; total offer count; anonymous Bid #N; offer amount; financing type; active/revised/withdrawn/countered status; identification of the logged-in consumer's own bid where authorized. |
| 9 | Landlord timed listings require: public countdown; exact deadline and timezone; public application count only; sealed private applications for the owner or authorized agent; no public rent-bidding feed. |
| 10 | The owner side requires a dedicated "offers received on my listing" aggregation and management route. The artifact explicitly identified the absence of that route as an existing gap. |
| 11 | The implementation plan required separate build batches for: domain state machine; database timer fields; server enforcement; seller UI; landlord UI; anonymous bid feed; notifications; security and end-to-end testing. |

**Evidentiary standard applied throughout.** Design documents, unused classes, unmounted Blade components, isolated tests, and unmerged branches do **not** count as completed implementation. For every requirement marked implemented, this document proves presence in committed code *and* connection to the real page shown in the screenshots.

---

# Part II — Phase 0 Audit: repository context and verdict

## Repository context

```
pwd                     /home/runner/audit-timed-offer-flow
git status --short      (empty — clean worktree)
git branch --show-current   audit/timed-offer-flow
git rev-parse HEAD      b8713452d47bdcd6cb8502f32c9b86e3f0e3907b
```

Worktrees attached at audit time:

| Path | Commit | Branch |
|---|---|---|
| `/home/runner/workspace` | `b8713452d` | `phase-1-spatial/p0-address-validation` (dirty — **excluded from audit**) |
| `/home/runner/audit-timed-offer-flow` | `b8713452d` | `audit/timed-offer-flow` ← this audit |
| `/home/runner/worktrees/hi-05a-r2e1-full-population-readiness` | `91c3804fa` | phase-2 batch |
| `/home/runner/worktrees/phase-2-c1-florida-boundaries` | `41a0862ee` | phase-2 spatial |

The most recent 50 commits on HEAD are **entirely** Phase-2 spatial and object-storage work. No timed-offer commit appears in recent history on this line of development.

## Verdict

**The approved Timed Listing Architecture was never implemented in the committed runtime at HEAD.** Zero of eleven requirements implemented; requirements 6 and 7 actively violated by shipped code.

The observed symptoms are fully explained by code that *is* at HEAD. Both defects are architectural rather than cosmetic:

- the countdown mismatch is a **prohibited dependency on the listing expiration date**, promoted to primary source;
- the missing bidding area is **absent implementation**, not a rendering or permission fault.

---

# Part III — Countdown investigation

## Symptom

A Bidding Period listing configured for **5 days** displays:

| Surface | Displayed | Correct? |
|---|---|---|
| Listing card | ~`4d 11h` remaining | ✅ Yes |
| Listing Details | ~`38d 6h` remaining | ❌ No |

## Path A — Listing card (`4d 11h`, correct)

```
GET /search/seller-listings
  → routes/web.php:370
  → SellerOfferListingController::searchOfferListings()          :428
  → view('offer-listing.seller.search')                          :511
  → timer block                                                  :213-248
  → source: $auction->created_at + seller_agent_auction_metas.auction_time
  → badge .timer-{id}[data-seconds]                              :309-311
  → client tick                                                  :372-382
```

```php
// resources/views/offer-listing/seller/search.blade.php:213
// Bidding Period countdown — calculated exclusively from created_at + auction_time
$_start = \Carbon\Carbon::parse($auction->created_at);
$_end = match(true) {
    in_array($_unit, ['hour','hours'])     => $_start->addHours($_val),
    in_array($_unit, ['week','weeks'])     => $_start->addWeeks($_val),
    in_array($_unit, ['minute','minutes']) => $_start->addMinutes($_val),
    default                                => $_start->addDays($_val),
};
$remainingSeconds = (int)\Carbon\Carbon::now()->diffInSeconds($_end, false);
```

Single source, no fallback. 5 days minus ~13h elapsed = **4d 11h**. Matches the screenshot.

## Path B — Listing Details (`38d 6h`, defective)

```
GET /offer-listing/seller/view/{id}       (PUBLIC — no auth)
  → routes/web.php:1155
  → SellerOfferListingController::view()                         :86
  → view('offer-listing.seller.view')                            :135
  → timer block                                                  :1474-1510
  → source: seller_agent_auction_metas.expiration_date  ← DEFECT
  → badge .sol-bp-timer[data-seconds]                            :1539
  → client tick                                                  :2815+
```

```php
// resources/views/offer-listing/seller/view.blade.php:1474
// Bidding Period countdown — uses expiration_date when available; falls back to created_at + auction_time for legacy records
$_expDateStr = trim($str('expiration_date'));
if ($_expDateStr !== '') {
    $_timerEnd = \Carbon\Carbon::parse($_expDateStr);   // ← LISTING EXPIRATION, checked FIRST
} else {
    // ... $_start->addDays($_val)                       // ← bidding duration, demoted to fallback
}
```

## Proof of source: the `6h` remainder

`Carbon::parse()` on a date-only string (`"2026-09-04"`) returns **midnight**. App timezone is `UTC` (`config/app.php:86`). At ~17:52 UTC, midnight is **6h08m** away.

That is why the detail page reads `38d 6h` and not a whole number of days. Only a date-only `expiration_date` parsed at midnight produces that remainder. `created_at + auction_time` would preserve the creation time-of-day and could not.

## Five competing derivations of "bidding end"

| # | Location | Source (in priority order) | Status |
|---|---|---|---|
| 1 | `*/search.blade.php` (card) | `created_at + auction_time` | ✅ Correct, but disagrees with #2 |
| 2 | `*/view.blade.php` (detail timer) | **`expiration_date`**, then `created_at + auction_time` | ❌ **Critical violation** |
| 3 | `seller/view.blade.php:2492-2506` (sidebar "Bidding Ends") | **`expiration_date`**, then `$_timerEnd` | ❌ Violation |
| 4 | `seller/view.blade.php:1086` (hub "Bidding ends") | `bidding_end_date` ?: `offer_deadline` | ❌ **Dead code** |
| 5 | `SellerOfferListingController.php:477-501` (`ending_soon` SQL) | `created_at + INTERVAL auction_time`, then **`expiration_date`** | ❌ Violation in ORDER BY |

Derivation #4 is dead: `bidding_end_date` and `offer_deadline` are **read exactly once** — at that line — and **written nowhere** in the entire codebase. Verified total across `app`, `resources`, `database`, `routes`: **1 occurrence**, the read itself. The "Bidding ends" line in the Quick Actions hub can never render.

Derivation #5 places the prohibited fallback inside the search ranking, so `ending_soon` ordering disagrees with both visible timers.

## All four roles affected identically

| Role | Card (correct source) | Detail (defective source) |
|---|---|---|
| Seller | `search.blade.php:213` | `view.blade.php:1474` |
| Buyer | `search.blade.php:213` | `view.blade.php:498` |
| Landlord | `search.blade.php:201` | `view.blade.php:460` |
| Tenant | `search.blade.php:213` | `view.blade.php:518` |

Every detail page prefers `expiration_date`; every card prefers `auction_time`. The mismatch is **guaranteed** on every Bidding Period listing where the two values differ.

## Aggravating factor: the comment contradicts the code

Four lines below the defective block:

```php
// resources/views/offer-listing/seller/view.blade.php:1529
{{-- Bidding Period countdown timer (source: created_at + auction_time) --}}
```

The comment states the architecturally correct source while the code fifty lines above reads `expiration_date` first. Present in all four role views. Any review conducted by reading comments would clear this file — a plausible contributor to the defect surviving eight weeks.

## Provenance

**`c729357b1`** — 2026-06-02, *"Fix offer listing hero timer to use expiration_date as primary source"*, Task #1811 (Fix Offer Listing Timer Source Consistency). **Confirmed ancestor of HEAD.**

From its commit message:

> *Problem: All four offer listing view pages computed the hero countdown timer using `created_at + auction_time` exclusively, while the sticky sidebar on buyer/tenant/landlord already checked `expiration_date` first... Logic applied uniformly: 1. Check `$str('expiration_date')` — if present, parse with Carbon and use as `$_timerEnd`. 2. Only if expiration_date is absent, fall back to created_at + auction_time.*

The commit resolved a genuine internal inconsistency (hero vs sidebar) by standardising on the **architecturally prohibited** field, and did not touch the four search cards. A page-local bug became the cross-surface split now observed.

## Timezone handling

`config/app.php:86` sets `'timezone' => 'UTC'` (with `'America/New_York'` commented out at `:84`). Timers are server-computed once at render into `data-seconds`, then decremented client-side. **No surface displays the deadline's timezone**, which artifact requirements 8 and 9 both mandate.

---

# Part IV — Missing consumer bidding investigation

## Finding

**Never implemented.** Not conditional rendering, not authorization, not missing data.

What exists at HEAD is an **offer-drafting workflow**, not a **bidding surface**.

## Capability-by-capability

| Capability | At HEAD | Evidence |
|---|:--:|---|
| "Submit Offer" button | ✅ | `seller/view.blade.php:1110`, POSTs to `offers.store` |
| Creates offer draft | ✅ | `OfferController::store` → `status='draft'` → redirect `offers.show` |
| View own bid | ✅ | `GET /offers/{offer}` → `offers/show.blade.php` (59 KB) |
| Bid status | ✅ | `offers.status` column; rendered on offer detail |
| My offers list | ✅ | `GET /offers` → `MyOffersController::index` |
| Counter | ✅ | `POST /offers/{o}/counter` + `OfferCounterService` |
| Withdraw | ✅ | `POST /offers/{o}/withdraw` |
| Acceptance / rejection | ✅ | `POST /offers/{o}/accept` \| `/reject` |
| **Total offer count** | ❌ | No count query, no display |
| **Anonymous Bid #N** | ❌ | `bid_number` / "Bid #": **0 occurrences** in `resources/views/offer-listing/` |
| **Anonymous bidding activity / feed** | ❌ | No `partials/` directory exists under `offer-listing/` |
| **Offer amount / financing type (public)** | ❌ | No public feed to carry them |
| **Own-bid identification on listing** | ❌ | Not implemented |
| **Deadline enforcement on submit** | ❌ | No code path consults any bidding deadline before accepting an offer |

## Secondary defects in the path that does exist

**Guests receive 403 instead of a login redirect.**

```php
// app/Http/Middleware/EnsureOfferPlayoffAccess.php:21
if (!Auth::check() || Gate::denies('offer-playoff')) {
    abort(403, 'You do not have access to Offer Playoff.');
}
```

The public listing detail route is deliberately unauthenticated (`routes/web.php:1145-1157`: *"These must sit outside the auth group so unauthenticated visitors can open a listing card from the public search pages"*). The Submit Offer button is therefore rendered to logged-out visitors and hard-fails with 403 on click.

**Silent failure when the listing is unlinked.**

```blade
{{-- seller/view.blade.php:1112 --}}
<input type="hidden" name="offer_auction_id" value="{{ optional($offerAuction)->id }}">
```

`resolveOfferAuction()` (`SellerOfferListingController.php:293-302`) returns `null` when the `linked_offer_auction_id` meta is absent, emitting an empty value that fails validation at `OfferController.php:56` (`required|integer|exists:offer_auctions,id`). Seller listings self-link at publish (`SellerOfferListing.php:3906`, `SellerOfferListingEdit.php:4040`), so this affects older or backfill-dependent rows. The repair command `offer:backfill-linked-auction` is **manual** — `app/Console/Kernel.php` schedules only `offers:expire-pending`.

## The `offer-playoff` Gate is NOT the blocker

```php
// config/offer.php:29-31
if ($raw === null || trim($raw) === '' || trim($raw) === '*') {
    return '*';   // open to all authenticated users
}
```

`AuthServiceProvider.php:40` grants admins unconditionally and everyone else when the list is `'*'`. With `OFFER_PLAYOFF_ALLOWED_IDS` unset, the Gate is open. It does **not** explain the missing bidding area.

---

# Part V — Missing owner dashboard investigation

## Finding

**Never implemented at HEAD. Written on an unmerged branch.**

## Evidence

The entire owner-side offer aggregation at HEAD:

```php
// app/Http/Controllers/MyOffersController.php — complete file body
public function index()
{
    $offers = Offer::where('user_id', Auth::id())
        ->orderBy('created_at', 'desc')
        ->get();

    return view('offers.index', compact('offers'));
}
```

This returns offers the user **made**, not offers **received**. `resources/views/offers/index.blade.php` is titled "My Offers".

Searches for a received-offers route (`received-offers`, `offers/received`, `offersFor`, `receivedOffers`, listing-scoped offer routes) across `routes/web.php` and all controllers returned **no matches**.

| Owner capability | At HEAD |
|---|:--:|
| Accept / reject / counter a specific offer | ✅ via `offers.show` — reachable only with a known offer ID |
| **Aggregated "offers received on my listing"** | ❌ No route, no controller, no view |
| Bid comparison / ranking | ❌ Absent |
| Notification on new offer | ✅ `OfferSubmittedNotification` exists |

**The seller has decision endpoints but no inbox.**

## Where it was written

Commit `5fef88f2c` *"feat(timed-listings): add owner offers dashboard"* on `phase-timed-offer-listings` — 11 files, 521 insertions:

```
app/Http/Controllers/ListingOffersController.php            (+70)
app/Presenters/OwnerOfferComparisonPresenter.php            (+100)
app/Services/Listings/ListingManagementAccess.php           (+47)
resources/views/listings/offers-received.blade.php          (+73)
resources/views/offer-listing/seller/view.blade.php         (+8)   ← entry point
resources/views/offer-listing/landlord/view.blade.php       (+8)   ← entry point
routes/web.php                                              (+7)
tests/Feature/Listings/ListingManagementAccessTest.php      (+87)
tests/Feature/Listings/ListingOffersDashboardTest.php       (+113)
```

**Not an ancestor of HEAD or `origin/main`.** The gap the architecture artifact explicitly predicted remains open.

---

# Part VI — Branch provenance findings

## Divergence

**`origin/main` (`260d83f85`) is four commits ahead of local `main` (`10037715a`), and is not contained in HEAD.**

```
HEAD..origin/main:
  260d83f85  Merge pull request #50 from AbigailTheRealtor/phase-bidding-period-ui-clean
  8a3a5a918  feat(bidding-period): anchor the bidding window to a canonical activation stamp
  8d7699f26  Merge pull request #48 from ...r2e1-full-population-readiness-r2
  807c768b4  test: isolate public storage in create-edit parity suite

origin/main..HEAD:
  b8713452d  fix(offer-listing): stop the legacy DOM gate from swallowing the Submit click
  50ad98ee2  fix(offer-listing): guide Seller/Landlord publish failures to the failing tab
  5c24dde72  feat(location): add street-address shape validation and ZIP gazetteer lookup
```

## Ancestry matrix

| Commit | Date | In HEAD | In `origin/main` | Subject |
|---|---|:--:|:--:|---|
| `c729357b1` | 06-02 | ✅ | ✅ | **Defect origin** — timer → `expiration_date` primary |
| `b739f6e9d` | 07-21 | ❌ | ❌ | OfferAuction timer columns + publish-time stamping |
| `d0a20a16f` | 07-21 | ❌ | ❌ | listing state machine |
| `2307356dc` | 07-21 | ❌ | ❌ | offer integrity guards |
| `5fef88f2c` | 07-21 | ❌ | ❌ | **owner offers dashboard** |
| `b7766bc07` | 07-21 | ❌ | ❌ | owner decision actions |
| `8462054c6` | 07-21 | ❌ | ❌ | **anonymous bid feed and bid numbers** |
| `96cebc622` | 07-21 | ❌ | ❌ | **seller public bidding UI** |
| `e074a72c4` | 07-21 | ❌ | ❌ | landlord public application status UI |
| `5921e3c9d` | 07-23 | ❌ | ❌ | validate bidding durations at publish |
| `7aa3f78b1` | 07-23 | ❌ | ❌ | merge notifications → timed-offer-listings |
| `2580415e6` | 07-24 | ❌ | ❌ | self-bid and duplicate guards at submit |
| `8a3a5a918` | 07-27 | ❌ | ✅ | **bidding window canonical activation stamp** (PR #50) |

## Branches holding unmerged timed-listing work

| Branch | Tip | Date | Contents |
|---|---|---|---|
| `phase-timed-offer-listings` | `2580415e6` | 07-24 | **Most complete** — full timed-listing implementation |
| `phase-timed-listings-notifications` | `34831922e` | 07-23 | Notification acceptance coverage |
| `backup/timed-listings-2026-07-21` | `e074a72c4` | 07-21 | Snapshot |
| `phase-bidding-period-ui` | `1b189f21f` | 07-27 | Countdown anchor work |
| `phase-bidding-period-ui-clean` | `8a3a5a918` | 07-27 | Merged to `origin/main` via PR #50 |

## Critical merge hazard

`phase-timed-offer-listings` is **stale**. Diffed against HEAD it reports **298 files changed, 9,365 insertions, 16,717 deletions** — it forked before the Phase-2 spatial and object-storage-seam work and would **revert shipped functionality** on a naïve merge.

**It cannot be fast-forwarded or merged.** The timed-listing commits must be cherry-picked or re-authored onto current `main`.

---

# Part VII — Unmerged commit analysis

## `phase-timed-offer-listings` — files never merged (~38)

```
app/Console/Commands/CloseBiddingCommand.php
app/Console/Kernel.php
app/Http/Controllers/BidFeedController.php
app/Http/Controllers/ListingOffersController.php
app/Http/Controllers/OfferController.php
app/Models/ListingBidNumber.php
app/Models/ListingStateEvent.php
app/Models/OfferAuction.php
app/Presenters/AnonymousBidFeedSerializer.php
app/Presenters/OwnerOfferComparisonPresenter.php
app/Providers/RouteServiceProvider.php
app/Repositories/OfferRepository.php
app/Rules/ApprovedBiddingDuration.php
app/Services/Listings/ListingDecisionService.php
app/Services/Listings/ListingManagementAccess.php
app/Services/Listings/ListingStateEventService.php
app/Services/Listings/ListingStateReconciler.php
app/Services/Listings/ListingTimerService.php
app/Services/Listings/ListingTransitionService.php
app/Services/Listings/RoleListingResolver.php
app/Services/Offers/BidFeedAccess.php
app/Services/Offers/BidNumberAllocator.php
app/Services/Offers/OfferCounterService.php
app/Services/Offers/OfferDecisionService.php
app/Services/Offers/OfferPermissionService.php
app/Services/Offers/OfferSubmissionService.php
app/Services/Offers/TimedListingOfferGuard.php
config/timed_listings.php
resources/views/listings/offers-received.blade.php
routes/web.php
tests/Feature/Listings/BidFeedEndpointTest.php
tests/Feature/Listings/BidNumberAllocatorTest.php
tests/Feature/Listings/CloseBiddingCommandTest.php
tests/Feature/Offers/OfferDuplicateGuardTest.php
tests/Feature/Offers/OfferSelfBidGuardTest.php
tests/Unit/Listings/ListingTimerServiceTest.php
tests/Unit/Offers/TimedListingOfferGuardTest.php
tests/Unit/Rules/ApprovedBiddingDurationTest.php
```

Notable: **`bidding_ends_at` does not appear in this set either.** The branch introduces `ListingTimerService` and a `config/timed_listings.php`, but the artifact's persisted-deadline column was never authored on any branch.

## `8a3a5a918` — merged to `origin/main`, absent from HEAD (27 files, +2,895)

```
app/Services/Offers/BiddingWindowService.php                (+276)
app/Services/Offers/PublicOfferFeedService.php              (+291)
app/Services/Offers/ListingOfferAuctionLinker.php           (+132)
app/Services/Offers/BiddingWindow.php                       (+89)
app/Http/Livewire/Concerns/StampsBiddingActivation.php      (+129)
database/..._add_bidding_started_at_to_offer_auctions_table.php  (+38)
resources/views/offer-listing/partials/_competing-bids.blade.php (+141)
resources/views/offer-listing/seller/view.blade.php         (+75/-)
resources/views/offer-listing/landlord/view.blade.php       (+79/-)
tests/Feature/Offers/PublicOfferFeedPrivacyTest.php         (+555)
tests/Feature/Offers/BiddingTermsFreezeTest.php             (+337)
tests/Feature/Offers/BiddingWindowServiceTest.php           (+303)
tests/Feature/Offers/BiddingPeriodEnforcementTest.php       (+235)
  ... + 14 more
```

Its commit message independently confirms this investigation:

> *"The bidding deadline was computed inside the Seller and Landlord public view Blades from expiration_date (a LISTING expiry) or, failing that, created_at (when the DRAFT was first saved — often days before the listing went Active). The countdown, the server, and the bidders therefore disagreed about when bidding closed, and nothing enforced the deadline at all."*

**But it deviates from the approved architecture (D-13).** From `BiddingWindowService`:

```
 * moment is stamped once onto offer_auctions.bidding_started_at:
 *     deadline = bidding_started_at + auction_time
 ...
 * Listings that went Active before this column existed have bidding_started_at
 *     ... 1. expiration_date   2. listing created_at + auction_time
```

- Column is `bidding_started_at`, not the artifact's `bidding_starts_at`.
- **No `bidding_ends_at` is persisted** — the deadline is recomputed at runtime via `addDuration()`.
- **An `expiration_date` legacy fallback is deliberately retained** in `legacyDeadline()`, flagged by `BiddingWindow::$isLegacyFallback`.

## Where the unmerged UI actually lives

`96cebc622` *"seller public bidding UI"* (6 files, +331) created `resources/views/timed-listing/seller-public-status.blade.php` (+124) and modified `offer-listing/seller/view.blade.php` (+12) as its mount point — so the branch **did** wire its surface into the real listing page. That wiring, and the surface, are absent from HEAD.

---

# Part VIII — Database and domain inventory

## Tables

| Table | Migration | Columns / notes |
|---|---|---|
| `offer_auctions` | `2026_04_16_003141` | `id, user_id, listing_id, title, is_draft, is_approved, is_sold, timestamps` — **no timer columns** |
| `offer_auction_metas` | `2026_04_16_003149` | EAV |
| `offers` | `2026_06_02_000001` | `offer_auction_id, user_id, role, status, listing_snapshot, parent_offer_id, submitted_at, expires_at` — **no `bid_number`, no anonymity** |
| `offer_metas` | `2026_06_02_000002` | EAV |
| `offer_event_logs` | `2026_06_02_000003` | Audit trail |

**No migration anywhere in HEAD adds a bidding timer column.**

## Field semantics

| Field | Storage | Meaning | Type hazard |
|---|---|---|---|
| `auction_time` | `*_agent_auction_metas` (EAV) | Bidding **duration** label, e.g. `"5 Days"` | Free text; re-parsed by `explode(' ')` at every render and in raw SQL |
| `expiration_date` | `*_agent_auction_metas` (EAV) | **Listing** expiry | Date-only string; `Carbon::parse()` → midnight UTC |
| `offers.expires_at` | native | **Per-offer** response deadline | Correct and separate |
| `bidding_starts_at` / `bidding_ends_at` | — | **Do not exist** | **0 occurrences at HEAD** |

**Three clocks exist; two are already conflated.** The bidding deadline has *no storage at all* and is recomputed from whichever field each surface happens to prefer.

## Domain objects at HEAD

- **Models:** `OfferAuction`, `OfferAuctionMeta`, `Offer`, `OfferMeta`, `OfferEventLog`
- **Services (13):** `OfferSubmissionService`, `OfferPermissionService`, `OfferStateMachineService`, `OfferDecisionService`, `OfferCounterService`, `OfferExpirationService`, `OfferHistoryService`, `OfferNegotiationChainService`, `OfferAvailableActionsService`, `OfferEventLogService`, `OfferTimelineBuilder`, `OfferWorkflowFacade`, `ImportantPlacesService`
- **Notifications (7):** Submitted, Accepted, Rejected, Countered, Withdrawn, Cancelled, Expired
- **Commands:** `ExpireOffersCommand` (scheduled), `BackfillLinkedOfferAuction` (manual)

**None of the 13 services references a bidding deadline.**

## Role symmetry note

Per `CLAUDE.md`: `seller_agent_auctions` and `buyer_agent_auctions` use **native columns**; `landlord_agent_auctions` and `tenant_agent_auctions` use **EAV meta**. Any timer repair must respect this asymmetry — which is precisely why `8a3a5a918` chose `offer_auctions` as the carrier (the one native table both roles link to 1:1). That reasoning is sound and should be preserved.

---

# Part IX — Implementation matrix (audit scope)

| Capability | Exists in Code | Merged into HEAD | Connected to Runtime UI | Tested | Defect |
|---|:--:|:--:|:--:|:--:|---|
| Bidding countdown (card) | ✅ | ✅ | ✅ | ❌ | Correct source, disagrees with detail |
| Bidding countdown (detail) | ✅ | ✅ | ✅ | ❌ | **CRITICAL — reads `expiration_date` first** |
| Sidebar "Bidding Ends" | ✅ | ✅ | ✅ | ❌ | Third source; `expiration_date` first |
| Hub "Bidding ends" | ✅ | ✅ | ❌ | ❌ | Dead — source fields never written |
| `ending_soon` sort | ✅ | ✅ | ✅ | ❌ | Fourth source; explicit expiration fallback |
| Canonical bidding-end field | ❌ | ❌ | ❌ | ❌ | **Does not exist on any branch** |
| Submit offer (draft) | ✅ | ✅ | ✅ | ✅ | Guest 403; null-link failure |
| View own offer / status | ✅ | ✅ | ✅ | ✅ | — |
| Accept / reject / counter / withdraw | ✅ | ✅ | ✅ | ✅ | No deadline gate |
| Bid count | ❌ | ❌ | ❌ | ❌ | **Missing** |
| Anonymous Bid #N | ⚠️ branch only | ❌ | ❌ | ⚠️ branch | **Missing at HEAD** |
| Anonymous bid feed | ⚠️ branch only | ❌ | ❌ | ⚠️ branch | **Missing at HEAD** |
| Owner offers dashboard | ⚠️ branch only | ❌ | ❌ | ⚠️ branch | **Missing at HEAD** |
| Bidding deadline enforcement | ⚠️ `origin/main` only | ❌ | ❌ | ⚠️ | **Missing at HEAD** |
| Duration validation at publish | ⚠️ branch only | ❌ | ❌ | ⚠️ branch | **Missing at HEAD** |
| Listing state machine | ⚠️ branch only | ❌ | ❌ | ⚠️ branch | **Missing at HEAD** |

---

# Part X — Artifact comparison matrix

| # | Artifact requirement | Implemented | Commit/branch | Runtime route/component | Tested | Evidence or gap |
|---|---|:--:|---|---|:--:|---|
| 1 | Timer belongs to `OfferAuction`, not legacy expiration | ❌ **No** | — | — | ❌ | `offer_auctions` has 7 columns, none temporal beyond `timestamps`. Timer lives in Blade `@php`. |
| 2 | `auction_time` is wizard input only | ❌ **No** | — | 4 search + 4 view Blades, 5 controllers | ❌ | Re-parsed via `explode(' ')` at every render and in raw SQL |
| 3 | On publish: set `bidding_starts_at`; compute `bidding_ends_at` | ❌ **No** | — | — | ❌ | **0 occurrences** of either identifier at HEAD |
| 4 | `bidding_ends_at` sole runtime source of truth | ❌ **No** | — | — | ❌ | Field absent; **five** competing derivations instead |
| 5 | `auction_time` not recalculated at runtime | ❌ **No** | — | every card + detail render | ❌ | Recalculated on every page load |
| 6 | Expiration never used/substituted/fallback | ❌ **VIOLATED** | `c729357b1` (in HEAD) | 4 detail Blades, 4 sidebars, `ending_soon` SQL | ❌ | Used as **primary**, not merely fallback |
| 7 | Two clocks never merged | ❌ **VIOLATED** | — | detail timer | ❌ | Listing expiry drives the bidding countdown |
| 8 | Seller public bidding surface (8 elements) | ❌ **No** | `96cebc622` unmerged | — | ⚠️ branch-only | Countdown present but wrong-sourced; count / Bid #N / amount / financing / status / own-bid / timezone all absent |
| 9 | Landlord: countdown, deadline+TZ, count only, sealed | ❌ **No** | `e074a72c4` unmerged | — | ⚠️ branch-only | No application count, no sealed surface |
| 10 | Owner "offers received" route | ❌ **No** | `5fef88f2c` unmerged | — | ⚠️ branch-only | **Gap the artifact predicted is still open** |
| 11 | Separate build batches (8 tracks) | ⚠️ **Partial** | branch commits exist per batch | — | ⚠️ | Batches authored; **none landed** |

## Explicit determination

| Question | Answer |
|---|---|
| Only produced the design artifact? | **Substantially yes** for HEAD — no artifact requirement reached the runtime |
| Implemented only the timer badge? | **Yes** — the badge is the *only* timed-listing element at HEAD, and it is wrong-sourced |
| Implemented only backend guards? | **No** — guards exist only in `8a3a5a918` / `2580415e6`, neither in HEAD |
| Omitted the consumer bidding UI? | **Yes** |
| Omitted the owner offers dashboard? | **Yes** |
| Failed to create `bidding_ends_at`? | **Yes — never created, on any branch** |
| Continued using `expiration_date`? | **Yes — promoted to primary by `c729357b1`** |
| Implemented files never merged? | **Yes — ~38 files on `phase-timed-offer-listings`; 27 in `8a3a5a918`** |
| Merged code unreachable from the real route? | **Yes — `bidding_end_date` / `offer_deadline` read but never written** |

---

# Part XI — Architectural deviation register

| ID | Severity | Deviation | Violates | Location | Status |
|---|---|---|---|---|:--:|
| **D-1** | **Critical** | `expiration_date` is the **primary** bidding-deadline source on all four detail pages | Req. 6, 7 | `seller:1474`, `buyer:498`, `landlord:460`, `tenant:518` | **RESOLVED (Stage 0)** — all four roles |
| **D-2** | **Critical** | No `bidding_ends_at` column exists on any branch | Req. 1, 3, 4 | — | Open |
| **D-3** | High | `auction_time` recalculated at every render | Req. 2, 5 | All card + detail renders | **RESOLVED (Stage 0)** — read once at activation |
| **D-4** | High | Five competing deadline derivations | Req. 4 | Card, detail, sidebar, hub, SQL sort | **RESOLVED (Stage 0)** — one stored source |
| **D-5** | High | `ending_soon` SQL contains explicit `expiration_date` fallback | Req. 6 | `SellerOfferListingController:477-501` (+3 roles) | **RESOLVED (Stage 0)** — all four rewritten |
| **D-6** | High | No seller public bidding surface | Req. 8 | — | Open |
| **D-7** | High | No landlord application-count or sealed-application surface | Req. 9 | — | Open |
| **D-8** | High | No owner offers-received route | Req. 10 | — | Open |
| **D-9** | High | No server enforcement of the bidding deadline at HEAD | Req. 4, 11 | — | Open |
| **D-10** | Medium | `bidding_end_date` / `offer_deadline` read, never written — dead code implying a fifth unimplemented model | Req. 4 | `seller/view.blade.php:1086` | **RESOLVED (Stage 0)** — dead read removed |
| **D-11** | Medium | In-code comment misstates the timer source, defeating comment-level review | — | `seller/view.blade.php:1529` (+3 roles) | **RESOLVED (Stage 0)** |
| **D-12** | Medium | Guests receive 403 rather than a login redirect from a deliberately public page | Req. 8 | `EnsureOfferPlayoffAccess:21` | Open |
| **D-13** | **High** | **Rejected architecture in `origin/main`.** `8a3a5a918` stamps `bidding_started_at` (not `bidding_starts_at`), persists **no `bidding_ends_at`**, recomputes the deadline at runtime via `addDuration()`, and **retains an `expiration_date` legacy fallback** in `legacyDeadline()` | Req. 3, 4, 5, 6; Invariants 3, 4, 10 | `BiddingWindowService` (origin/main only) | **RESOLVED (Stage 0)** — amended on `repair/timed-offer-architecture`: `bidding_starts_at` + stored `bidding_ends_at`, `legacyDeadline()` deleted outright |
| **D-14** | Medium | No surface displays the deadline's timezone | Req. 8, 9 | All countdown surfaces | **PARTIAL (Stage 0)** — `displayTimezoneAbbreviation()` added and used in the Seller hub; remaining surfaces land with the Stage 6 bidding UI |

---

# Part XII — Reachability and conditional-rendering analysis

Against the ten-way classification, the evidence supports **1, 2, 3, 4, 9, and 10 simultaneously** — different sub-capabilities fail for different reasons.

| # | Classification | Applies | Evidence |
|---|---|:--:|---|
| 1 | Never implemented | ✅ | Bid count, feed, anonymity, owner dashboard, `bidding_ends_at` |
| 2 | Implemented, never merged | ✅ | `phase-timed-offer-listings` — ~38 files, 0 in HEAD |
| 3 | Exists only on another branch | ✅ | Same, plus `8a3a5a918` on `origin/main` only |
| 4 | Exists but disconnected | ✅ | `bidding_end_date` / `offer_deadline` read, never written |
| 5 | Hidden by conditional rendering | ❌ | `@if($hubIsBidding)` and `@if($hasBPTimer)` gates all pass correctly |
| 6 | Supports agents but not consumers | ❌ | The offer workflow is consumer-facing |
| 7 | Supports only seller review | ❌ | Even seller review lacks aggregation |
| 8 | Blocked by authorization | ⚠️ Partial | Gate defaults open; **guests 403 instead of login redirect** |
| 9 | Depends on missing listing data | ✅ | Null `linked_offer_auction_id` → empty hidden input → validation failure |
| 10 | Absent from the deployed commit | ✅ | Entire timed-listing set absent from HEAD |

**The listing in the screenshots satisfies every render condition that exists.** Nothing is being hidden — the surfaces are not in the build.

---

# Part XIII — Test-gap analysis

59 test files exist in `tests/Feature/Offers/`. Classification of the relevant ones:

| Test | Type | Covers |
|---|---|---|
| `OfferControllerTest` | Feature / route integration | store / submit / accept / reject / withdraw endpoints |
| `OfferDetailPageTest`, `OfferDetailPermissionTest` | Feature | `/offers/{id}` render + authorization |
| `MyOffersDashboardTest` | Feature | `/offers` (own offers only) |
| `OfferActionButtonWiringTest`, `OfferActionVisibilityTest` | Feature | Action buttons on offer detail |
| `OfferCounterFormTest`, `OfferTerminalNegotiationTest` | Feature | Counter chain |
| `Offer*NotificationDispatchTest` (×7) | Feature | Notification dispatch |
| `ExpireOffersCommandTest`, `OfferExpiresAtNativeWriteTest`, `OfferRequestTimeExpiryTest` | Feature / Unit | **`offers.expires_at` — the per-offer clock, not the bidding deadline** |
| `SellerOfferViewReadOnlyTest`, `BatchAUiRegressionTest`, `CreateEditParityRegressionTest` | Feature | Public seller view renders |
| `OfferTransactionAtomicityTest` | Feature | Transaction integrity |
| `OfferSelfBidDuplicateTest` | Feature | Self-bid / duplicate guards |

## Do any tests prove the required claims?

| Claim | Proven? | Evidence |
|---|:--:|---|
| A 5-day timed-offer listing uses the same bidding deadline everywhere | ❌ **No** | **Zero tests** match `Bidding Period Time Remaining`, `sol-bp-timer`, `data-seconds`, or `timerRemaining`. Verified count: **0**. |
| The real consumer page displays the bidding interface | ❌ **No** | No such interface exists to assert against |
| A consumer can submit a bid through the actual UI | ⚠️ **Partial** | Endpoint is tested; the Blade → guest-403 → null-link path is not |
| The seller can view submitted bids | ❌ **No** | No aggregation route exists to test |
| Counters and withdrawals work | ✅ **Yes** | `OfferCounterFormTest`, `OfferWithdrawnNotificationDispatchTest` |
| Expired bidding periods disable submissions | ❌ **No** | No deadline enforcement exists at HEAD |

## The decisive gap

Several tests render the public seller view and assert on its content — yet **not one asserts the countdown's value or source**. `c729357b1` inverted the timer source across four pages in June and passed CI unchallenged.

The suite tests the offer **workflow** thoroughly and the bidding **period** not at all: a textbook case of isolated-class coverage standing in for the real runtime flow. Per the artifact's evidentiary standard, the branch-only tests (`ListingTimerServiceTest`, `BidFeedEndpointTest`, `BiddingWindowServiceTest`, `PublicOfferFeedPrivacyTest`, …) **do not count** — they test unmerged code.

---

# Part XIV — Risk assessment

| Risk | Severity | Note |
|---|---|---|
| Bidding deadline is legally ambiguous | **Critical** | Two surfaces show different deadlines for the same listing. In a real-estate bidding context this is a disclosure and contract-formation exposure, not a cosmetic bug. |
| No deadline enforcement | **Critical** | Offers can be submitted indefinitely; the countdown is decorative. |
| Changing the timer source moves live windows | **High** | Any listing with a populated `expiration_date` will see its displayed deadline jump on deploy. Requires a comms/ops plan, not just a code change. |
| Stale branch merge would revert Phase-2 work | **High** | `phase-timed-offer-listings` diff = 16,717 deletions. **Cherry-pick only.** |
| `origin/main` fix deviates from the artifact | **High** | Shipping Stage 1 on top of `bidding_started_at` without resolving D-13 entrenches the deviation. |
| Zero countdown test coverage | **High** | The defect survived eight weeks and multiple audits. |
| Backfill fabricates start times | **Medium** | Any `bidding_starts_at` backfill invents a start for already-active listings; needs explicit product sign-off. `8a3a5a918` correctly refuses to backfill. |
| Four-role symmetry | **Medium** | Per `CLAUDE.md`, changes must apply across seller/buyer/landlord/tenant; the native-vs-EAV split makes landlord/tenant structurally different. |
| Local `main` behind `origin/main` | **Medium** | Four commits behind; rebasing changes the defect surface mid-repair. |

---

# Part XV — Recommended repair stages

Sequenced; each stage independently shippable. **No code has been written. Approval required before Stage 0.**

| Stage | Work | Notes |
|---|---|---|
| **0** | **Reconcile branches.** Rebase/merge strategy, and amend `8a3a5a918` to conform to Decision A before any reuse. | Local `main` is 4 commits behind `origin/main`. Do this before anything else. Decision A is settled: `8a3a5a918` is **not** accepted as-is. |
| **1** | **Schema.** Add `offer_auctions.bidding_starts_at` and **`bidding_ends_at`** per Decision A and req. 1/3. | **No backfill** — Decision B: listings that predate these columns keep them **unset** until an approved migration or product workflow initializes them. Never invent a historical deadline. |
| **2** | **Publish-time stamping.** Compute and store both fields exactly once when the listing becomes Active; never recalculate. | Decision A; req. 3, 5; Invariant 4. |
| **3** | **Single read path.** One accessor/service. Replace all five derivations; delete the `expiration_date` branches in 4 detail Blades, 4 sidebars, and the `ending_soon` SQL; remove the dead `bidding_end_date`/`offer_deadline` read; fix the misleading comments. | Req. 4, 6. Closes D-1, D-3, D-4, D-5, D-10, D-11. |
| **4** | **Regression lock.** Assert card and detail render byte-identical `data-seconds` for one 5-day listing; add a repo-wide guard that no bidding-countdown path references a listing-expiration field. | **Land with Stage 3, not after.** This is what would have caught `c729357b1`. |
| **5** | **Server enforcement.** Refuse draft creation and submission past `bidding_ends_at`; keep owner accept/counter/reject open so owners can work through bids that arrived in time. | Req. 4, 11. Closes D-9. |
| **6** | **Consumer bidding surface.** Count, Bid #N, amount, financing, status, own-bid identification, deadline + timezone. | Req. 8. Cherry-pick from `96cebc622` / `8462054c6`. Closes D-6, D-14. |
| **7** | **Owner offers dashboard.** | Req. 10. Cherry-pick from `5fef88f2c`. Closes D-8. |
| **8** | **Landlord surface.** Public count only, sealed applications, no rent-bidding feed. | Req. 9. Closes D-7. |
| **9** | **Guest UX + link integrity.** Login redirect instead of 403; make `linked_offer_auction_id` a publish-time invariant. | Closes D-12. |

**Recovery method for all branch-sourced stages: cherry-pick onto current `main`. Never merge `phase-timed-offer-listings`.**

> **Numbering note.** The table above is the original Phase 0 plan. Stage 0 is complete (pending commit). The next actionable stage is defined below and supersedes row "1" of that table, whose schema work Stage 0 already delivered.

## Stage 1 — Buyer/Tenant Canonical Activation Linkage

**Status: IMPLEMENTED, UNCOMMITTED — pending review.** Branch `repair/buyer-tenant-activation`, based on Stage 0 `b34b0cda6`.

### Delivered architecture

`ListingOfferAuctionLinker` now covers all four roles. Buyer and Tenant key their `OfferAuction` by the pre-existing deterministic `listing_id` convention — `buyer_criteria:{id}` / `tenant_criteria:{id}` — while Seller and Landlord keep their meta-based link. **`offer_auctions.listing_id` carries a UNIQUE index, so a second auction for the same listing is impossible by construction**, whichever path runs first. `ensureForCriteria()` uses `firstOrCreate` on that key, so an auction created earlier by a legacy first-offer submission is **adopted, not duplicated**, and every offer already attached keeps resolving. The linker also writes `linked_offer_auction_id`, so criteria listings answer `resolve()` identically to Seller/Landlord and the shared search-card window map needs no role branch. `listingFor()` gained criteria reverse-resolution, without which a Buyer/Tenant window could never be enforced server-side.

Activation moved to publication at four sites — `BuyerOfferListing::store()`, `BuyerOfferListingEdit::update()`, `TenantOfferListing::store()`, `TenantOfferListingEdit::update()` — via a new `stampBiddingActivationAuto()` that infers the role from the listing model. Role inference is required because the Tenant components build their auction from a dynamic `$auctionClass` chosen by `user_type`; inferring from the model is also safer than trusting a component property a form post could influence. `TenantOfferListingEdit` shares one `update()` between draft and publish saves, so its call is explicitly guarded by `! $this->_isDraftSave`.

**`OfferController::resolveOfferAuctionId()` no longer creates anything itself** — it delegates to the linker and never calls `markActivated()`. Submission can therefore attach an offer but can never start, restart or extend a window. A guard test asserts zero executable `markActivated(` call sites in that controller.

Buyer/Tenant countdowns, "Bidding Ends" sidebars (now with an explicit timezone abbreviation) and `ending_soon` ordering are **restored**, all reading the stored `bidding_ends_at`.

### Legacy behaviour — timed listings FAIL CLOSED

A listing published before Stage 1 has no auction. The linker may create or adopt the auction **relationship** so existing offers keep resolving, but both timestamps stay NULL and the window reads **UNINITIALIZED**.

**An uninitialized Bidding Period listing displays no countdown AND rejects new offers.** A bid accepted against no deadline cannot be adjudicated — there is no defensible answer to "was this bid in time?" — so the listing refuses rather than accepting into an undefined window. Enforcement sits at three layers: draft creation (`OfferController::store`, before any row is written), the submit backstop (`OfferSubmissionService`), and the UI gate (`OfferPermissionService::canSubmit`). All three share one message via `BiddingWindowService::UNINITIALIZED_MESSAGE`:

> "This listing's bidding period has not been initialized. The listing owner must republish or initialize the bidding window before offers can be submitted."

**Republishing, or an owner-approved initialization workflow, is required before new timed offers can be accepted.** **First-offer submission never initializes the bidding period** — no timestamp is written during submission, and no deadline is derived from `expiration_date`, `created_at`, `auction_time`, the first-offer time, or any other timestamp.

Uninitialized is deliberately NOT the same as closed: a closed window has a real deadline that has passed, and keeps its own separate message and rejection path. `isClosed()` stays false for an uninitialized window so nothing infers a deadline that was never set.

Traditional listings are unaffected — they are not Bidding Period, so the guard cannot fire for them. Offers attached before this change remain persisted, readable and correctly linked. No migration and no backfill.

### Tests

`BuyerTenantCanonicalActivationTest` — 23 tests — and `UninitializedBiddingWindowFailsClosedTest` — 20 tests — all passing, covering every required proof: one canonical auction per publication, both timestamps stamped, first offer creating nothing and starting nothing, republication not moving either timestamp, Traditional receiving no window, legacy adoption with offers preserved, surfaces reading storage, uninitialized listings showing nothing, reverse resolution, Seller/Landlord unchanged, and two static guards. Full `tests/Feature/Offers`: **55 failed / 778 passed** against the Stage 0 baseline of 55 failed / 735 passed — the 43 new tests, with a `diff`-identical failing suite set of 14 (all pre-existing environment failures). `CriteriaSearchSortTest` unchanged at 5 failed / 11 passed.

Closes the gap that forced the temporary countdown removal, so Buyer and Tenant reach the same canonical contract Seller and Landlord now have.

**Scope**

1. Extend `ListingOfferAuctionLinker` to support Buyer and Tenant criteria listings.
2. Create or resolve the `OfferAuction` **during listing publication**, not at first offer submission.
3. Stamp `bidding_starts_at` and `bidding_ends_at` in the **same publish transaction**.
4. Make activation **idempotent** — re-publishing or re-saving must never restart a live window.
5. **Preserve** the existing `Offer → OfferAuction` relationship; offers already submitted against bridge rows must keep resolving.
6. **Remove or reconcile the lazy first-offer bridge** so it cannot create a competing auction row for a listing that already has one.
7. **Restore Buyer and Tenant countdowns only after** the canonical timestamps exist — never before.

**Required tests**

8. End-to-end proof that the Buyer/Tenant countdown **starts at publication** and matches every other surface (card, detail, ordering, enforcement) to the same timestamp.
9. Proof that the **first submitted offer does not start, restart, or alter** the bidding window.

**Constraints carried from Stage 0.** No schema change is expected. Every Permanent Architectural Invariant continues to apply; the repository-wide guards must stay green throughout.

---

## Future Operational Task — Legacy Buyer/Tenant Listings

**Status: OPEN. Required before enabling Buyer/Tenant Bidding Period in production.**

Stage 1 makes the architecture correct, but the `bya_beta.bidding_period_enabled` flag is a separate product decision and this check gates it.

1. **Determine whether any existing Buyer or Tenant Bidding Period listing lacks `bidding_starts_at` or `bidding_ends_at`.** These listings display no countdown and, since the fail-closed amendment, **reject new offers** until re-initialized.

   ```sql
   -- Buyer / Tenant criteria listings whose canonical window is incomplete.
   SELECT oa.id, oa.listing_id, oa.bidding_starts_at, oa.bidding_ends_at
   FROM offer_auctions oa
   WHERE (oa.listing_id LIKE 'buyer_criteria:%' OR oa.listing_id LIKE 'tenant_criteria:%')
     AND (oa.bidding_starts_at IS NULL OR oa.bidding_ends_at IS NULL);

   -- Plus Bidding Period listings that have no OfferAuction at all.
   SELECT COUNT(*) FROM offer_auctions
   WHERE bidding_starts_at IS NOT NULL AND bidding_ends_at IS NULL;
   ```

2. **Do NOT backfill or fabricate bidding windows automatically.** Decision B stands: a start time invented at migration is not when the listing activated, and stamping one silently moves a live window.

3. **If affected listings exist**, either run an owner-approved initialization workflow, or notify the listing owners that **republishing is required before new timed offers can be accepted**. Owners currently receive no proactive notice — a bidder simply sees the rejection message — so notification is the minimum courteous path.

4. **Record the result of the production data check in this artifact**, including the counts found, the remedy chosen, and the date. An empty result is itself a result worth recording.

**Production data check result:** _not yet run._

---

# Part XVI — Commands executed (Phase 0)

All prefixed `cd /home/runner/audit-timed-offer-flow &&` or run via `git -C`. Read-only throughout. No command touched `/home/runner/workspace`.

```bash
pwd; git status --short; git branch --show-current; git rev-parse HEAD
git worktree list; git log --oneline --decorate -50
git log --all --oneline --regexp-ignore-case --grep=<16 terms>
git branch -a; git for-each-ref --sort=-committerdate
git merge-base --is-ancestor <commit> HEAD            # × 15
git merge-base --is-ancestor <commit> origin/main     # × 13
git branch -a --contains <commit>                     # × 5
git log HEAD..origin/main; git log origin/main..HEAD
git log -L 1473,1511:resources/views/offer-listing/seller/view.blade.php
git log --oneline -15 -- resources/views/offer-listing/seller/view.blade.php
git show --stat 8a3a5a918 96cebc622 5fef88f2c
git show 8a3a5a918:app/Services/Offers/BiddingWindowService.php
git diff --stat HEAD 2580415e6 -- app resources database routes tests
grep -rn   # auction_time, expiration_date, bidding_*, countdown, Remaining,
           # bid_number, anonymous, linked_offer_auction_id, offer-playoff, …
sed -n / Read   # seller+buyer+landlord+tenant search & view Blades,
                # SellerOfferListingController, OfferController, MyOffersController,
                # EnsureOfferPlayoffAccess, AuthServiceProvider, config/offer.php,
                # config/app.php, routes/web.php, offer migrations
ls / find       # views, services, migrations, tests
```

---

# Part XVII — Change log

| Date | Change | Author |
|---|---|---|
| 2026-07-27 | Document created. Phase 0 read-only audit complete at `b8713452d`. Verdict: architecture not implemented; 14 deviations registered (D-1…D-14); 2 owner decisions outstanding (A: fate of `8a3a5a918`; B: may live deadlines move). No application code modified. Committed `b787762fb`. | Phase 0 audit |
| 2026-07-27 | **Future Operational Task added** — legacy Buyer/Tenant listings must be checked for incomplete canonical windows before `bya_beta.bidding_period_enabled` is switched on in production. No automatic backfill; owner-approved initialization or owner notification only; the production data check result is to be recorded here. | Owner review |
| 2026-07-27 | **Stage 1 fail-closed amendment (uncommitted).** Uninitialized Bidding Period listings now REJECT new offers as well as displaying no countdown, enforced at three layers (draft creation before any row is written, submit backstop, UI gate) behind one shared `BiddingWindowService::UNINITIALIZED_MESSAGE`. Submission never stamps or derives a timestamp; uninitialized stays distinct from closed, which keeps its own message. Traditional listings unaffected; pre-existing offers preserved and readable. `UninitializedBiddingWindowFailsClosedTest` adds 20 tests. Full Offers suite 55 failed / 778 passed vs. baseline 55 / 735, failing suite set identical at 14. **Not committed; pending review.** | Stage 1 amendment |
| 2026-07-27 | **Stage 1 implemented (uncommitted)** on `repair/buyer-tenant-activation` from Stage 0 `b34b0cda6`. `ListingOfferAuctionLinker` extended to Buyer/Tenant via the unique `buyer_criteria:` / `tenant_criteria:` `listing_id` key, making duplicate auctions structurally impossible and adopting legacy bridge rows without detaching their offers; criteria reverse-resolution added so server-side enforcement can reach the listing. Activation moved to publication at four sites with role inference and an explicit draft-save guard. `OfferController::resolveOfferAuctionId()` delegates to the linker and never stamps. Buyer/Tenant countdowns, sidebars and `ending_soon` ordering RESTORED on the stored `bidding_ends_at`. Legacy listings stay uninitialized until next published — no migration, no backfill. 23 new tests pass; full Offers suite 55 failed / 758 passed vs. baseline 55 / 735, identical failing set. **Not committed; pending review.** | Stage 1 |
| 2026-07-27 | **Buyer/Tenant role support established by evidence and Stage 1 defined.** Buyer and Tenant Bidding Period listings confirmed as the SAME timed-offer product as Seller/Landlord (identical creation partials behind the `bya_beta.bidding_period_enabled` rollout flag; `auction_time` validation; existing `OfferAuction` relationship). Their Stage 0 countdown removal recorded as **TEMPORARY**. The lazy `buyer_criteria:` / `tenant_criteria:` bridge documented as unsuitable for timer activation because it is created at first offer submission rather than at publication. Binding constraint recorded: no `expiration_date`, no `created_at`, no runtime arithmetic while the gap exists. **Stage 1 — Buyer/Tenant Canonical Activation Linkage** added as a formal follow-up (not started). Current state of record added; no schema change expected for Stage 1. | Owner review |
| 2026-07-27 | **Stage 0 implemented** on `repair/timed-offer-architecture` (worktree `/home/runner/worktrees/repair-timed-offer-architecture`, base `origin/main` `260d83f85`). Canonical two-column window `offer_auctions.bidding_starts_at` + `bidding_ends_at`, both stored once in one transaction at activation; `legacyDeadline()` and every `expiration_date` / `created_at` fallback DELETED, not deprecated; `isLegacyFallback` removed. Repository sweep across all four roles: Seller/Landlord cards and `ending_soon` ordering read the stored `bidding_ends_at` via `linked_offer_auction_id`; Buyer/Tenant countdowns, "Bidding Ends" sidebars and deadline ordering REMOVED (no `OfferAuction` linkage, so no canonical deadline — none synthesized). Deviations **D-1, D-3, D-4, D-5, D-10, D-11, D-13 RESOLVED**; **D-14 PARTIAL**. New `CanonicalBiddingWindowTest` (23 tests) incl. two repo-wide guards; `BiddingWindowServiceTest` replaced (asserted the rejected architecture). Full `tests/Feature/Offers`: 55 failed / 735 passed vs. a clean-`origin/main` baseline of 55 failed / 734 passed — identical failing set, all pre-existing environment failures. **Not committed; pending review.** | Stage 0 |
| 2026-07-27 | **Owner-Approved Architecture Decisions section added and ratified — now the permanent architectural contract, superseding any conflicting implementation.** **Decision A APPROVED:** persist both `bidding_starts_at` and `bidding_ends_at`; compute the end exactly once at Active; sole runtime source; never recompute from `auction_time` after activation; never derive or fall back to `expiration_date`. **Commit `8a3a5a918` explicitly rejected as final architecture** — reuse permitted only after amendment. **Decision B APPROVED:** no fabricated bidding windows; pre-existing listings keep `bidding_starts_at`/`bidding_ends_at` unset until an approved migration or product workflow initializes them. **12 Permanent Architectural Invariants ratified.** Consequent updates: Executive Summary §10 marked RESOLVED (Phase 0 text retained); D-13 status changed from "Decision required" to "Open — amend required"; repair Stages 0–2 rewritten to carry the approved decisions. Conformance assessment added: the runtime at `b8713452d` breaches invariants 1, 2, 3, 4, 5, 6, 9, 10. **Lessons Learned appendix added.** No application code modified. | Owner decision ratification |

---

# Lessons Learned

Architectural lessons from this investigation. These are written to outlive the specific defect — each one is the generalizable form of something that actually went wrong here, with the evidence that produced it.

## 1. Design artifacts do not equal implementation

The Timed Listing Architecture artifact was thorough, correct, and approved — and produced **zero** implemented requirements in the runtime. The artifact even said so, explicitly marking itself *pending approval* with *no code or migrations created*. That disclaimer was accurate and was nonetheless read, by the project as a whole, as though the design had landed.

**The rule:** a design document is a statement of intent, never a record of delivery. Track implementation separately from design, and never let the existence of an artifact — or a branch named after it — stand in for evidence that code runs.

## 2. Unmerged branches do not count as delivered functionality

Roughly 38 files of genuine, working timed-listing implementation exist on `phase-timed-offer-listings`: state machine, bid-number allocator, anonymous feed serializer, owner dashboard, publish-time validation, plus their tests. None of it is an ancestor of HEAD. From the user's perspective it does not exist.

Worse, the branch decayed while it waited. By audit time it was 16,717 deletions divergent from HEAD, because the trunk moved on with Phase-2 spatial and storage work. **Unmerged work is not banked — it is depreciating.** The longer it sits, the more likely recovery means cherry-picking or rewriting rather than merging.

**The rule:** "done" means merged into the branch that ships. Work that is written but parked should be tracked as a liability with a decay cost, not as an asset.

## 3. Runtime code must match the approved architecture

`c729357b1` was a *conscientious* commit. It identified a real inconsistency (hero timer disagreeing with sidebar), applied a uniform fix across all four roles, documented its reasoning clearly, and passed review and CI. It was also the commit that hard-wired the prohibited dependency, by choosing the architecturally forbidden field as the standard.

Local consistency was achieved; architectural conformance was never checked, because nothing in the pipeline could check it.

**The rule:** an architectural constraint that exists only in a document is not enforced. Constraints must be expressed as something mechanical — a test, a lint rule, a grep guard in CI — or they will be violated by well-intentioned people doing careful work.

## 4. There must never be multiple competing business-rule derivations

The same question — *when does bidding end?* — had **five** different answers in the codebase: the card computed one, the detail timer another, the sidebar a third, the Quick Actions hub a fourth (from fields never written anywhere, so it silently rendered nothing), and the `ending_soon` SQL sort a fifth.

Every one of these was added by someone solving a local problem correctly. No one added a contradiction on purpose; the contradiction was emergent. And because each surface computed independently, no single file looked wrong when read in isolation.

Two secondary symptoms are worth naming because they are diagnostic of this pattern generally: **the comment at `view.blade.php:1529` stated the correct source while the code fifty lines above did the opposite**, and the hub derivation referenced fields with zero writers anywhere in the codebase. Duplicated logic drifts from its own documentation, and dead branches accumulate unnoticed.

**The rule:** a business rule gets exactly one implementation and every consumer calls it. When you find yourself writing the same rule a second time, that is the signal to extract it — not after the third or fourth.

## 5. Critical business deadlines must be persisted, not recomputed

`auction_time` is a free-text label (`"5 Days"`) re-parsed by `explode(' ')` on every page render and inside raw SQL. The deadline had no storage at all, so it was only ever as stable as whatever inputs each call site happened to reach for — and those inputs disagreed.

Recomputation also silently moves the finish line: anchoring to `created_at` means the clock started when the *draft* was first saved, often days before the listing went Active. Bidders, the countdown, and the server can all be looking at different moments with no error surfacing anywhere.

**The rule:** anything a user, a contract, or a legal obligation depends on gets computed once, at a defined event, and stored. Derived-on-read is acceptable for display convenience; it is not acceptable for a deadline that determines whether a bid is valid.

## 6. Every architectural rule needs regression tests against the real runtime UI

There are 59 test files in `tests/Feature/Offers/`. Several render the public seller view and assert on its content. **Zero** assert the countdown's value or source — verified: no test matches `Bidding Period Time Remaining`, `sol-bp-timer`, `data-seconds`, or `timerRemaining`.

That is why the defect survived eight weeks, multiple audits, and a full CI suite. The offer *workflow* was tested thoroughly at the service and endpoint level; the bidding *period* — the thing the user actually sees and relies on — was tested not at all. Isolated-class coverage created the appearance of a well-tested subsystem while the integration point stayed unguarded.

**The rule:** test the surface the user sees, not only the class that computes it. A rule like *"every countdown reads the same timestamp"* should be a test that renders both surfaces and compares them, plus a repo-wide guard that the forbidden field never appears in the timer path. Land that guard **with** the fix, not after — it is the only thing that stops the next well-meaning commit from reintroducing the defect.

## 7. Architecture changes require end-to-end verification before release

Backend and frontend drifted apart at every layer. The offer workflow shipped with 13 services, 7 notifications, and a full state machine — and no bidding surface to drive it. A "Submit Offer" button renders to logged-out visitors and returns 403 on click, because the page is deliberately public while every offer endpoint requires auth. A hidden `offer_auction_id` input posts empty when the listing link is missing, failing validation with no useful signal.

Each of these passes its own unit boundary. All of them fail the actual user journey. Nobody had walked the path end to end as a real consumer.

**The rule:** before release, exercise the complete journey in the real application as the real actor — logged out and logged in. Component-level green is not release evidence.

---

## The meta-lesson

Every individual decision in this history was defensible. The design was sound, the implementation branch was real work, `c729357b1` was a careful fix, the tests were genuine tests, and `8a3a5a918` correctly diagnosed the problem.

The failure was **architectural drift with no mechanism to detect it** — no enforced link between the approved design and the shipped runtime, and no test that would fail when they diverged. Drift of this kind is not caught by working harder or reviewing more carefully. It is caught by making conformance mechanical.

That is precisely what the **Permanent Architectural Invariants** exist to do. Their value is not in being written down — the previous architecture was written down too. It is in Invariant 12: **any implementation that violates them is recorded in the Deviation Register before merge**, which converts an aspiration into a gate.

---

*End of document. Update this file as each repair stage lands — matrices, deviation register, repair stages, test-gap table, and change log. Preserve superseded findings by striking through and dating them; do not delete. Republish the same file path to keep the artifact URL stable.*

---

# Regression Reopening — 2026-07-29

**Status: INVESTIGATION ONLY. No code, migration, database record, branch, or commit was changed.**

This section is **additive**. Every prior finding, decision, invariant, deviation and completed-stage record above stands unmodified. Nothing has been rewritten or deleted.

## R0. Investigation context

| Item | Value |
|---|---|
| Worktree | `/home/runner/worktrees/regression-timed-offer-runtime` |
| Branch | `regression/timed-offer-runtime` |
| HEAD | `41db5d149c87f62a486ef16fe7c7a5cfaaeff77f` |
| `origin/main` | `41db5d149c87f62a486ef16fe7c7a5cfaaeff77f` (identical) |
| `git status --short` | empty — clean |
| Primary workspace | untouched; no uncommitted file from it was read |

**Environment limitation, stated up front.** This worktree has **no `vendor/` and no `.env`**. `php artisan` cannot bootstrap. Therefore **no runtime reproduction, no database query, and no live-page render was performed**. Every finding below is derived from committed source, git history, and reachability analysis. Where a conclusion requires database state, it is labelled as such and the exact discriminating query is supplied rather than guessed at.

## R1. Locating the master artifact

The artifact is **not in `main`** and never has been. It lives only on `audit/timed-offer-flow`:

| Commit | Subject | Ancestor of HEAD? |
|---|---|---|
| `b787762fb` | docs: add timed-offer runtime investigation and architecture audit (+808) | **No** |
| `9c8ed8a99` | docs: ratify owner-approved architecture decisions and add lessons learned | **No** |
| `6b6a28d84` | docs: record Stage 0 completion, Buyer/Tenant role support, and Stage 1 | **No** |
| `195f43a16` | docs: record Stage 1, fail-closed enforcement, and the legacy data follow-up | **No** (branch tip) |

`find` over the worktree returned nothing because the file is absent from this branch. The 1,063-line artifact was read out of `audit/timed-offer-flow` and restored verbatim before this section was appended.

**Consequence worth recording:** the governing architectural contract for this subsystem is reachable only from a docs branch that has never been merged. Any engineer working from `main` cannot see the invariants they are bound by.

## R2. Prior repair commits — ancestry resolved

The Stage 0 hash in the prior record **was rebased**, exactly as the reopening brief anticipated. It was not lost.

| Recorded ref | Resolves? | Current status |
|---|---|---|
| `b34b0cda6` (Stage 0) | Yes, but **dangling** | On **no branch**. Not an ancestor of HEAD. |
| `1c356d8d1` | Yes | **Replacement for `b34b0cda6`** — byte-identical subject, same file set. **Ancestor of HEAD.** On `main`, `integrate/timed-offers`, `repair/timed-offer-architecture`, `repair/buyer-tenant-activation`. |
| `8a3a5a918` (prior partial main) | Yes | **Ancestor of HEAD.** On `main`. |
| `1b189f21f` | Yes | Same subject as `8a3a5a918`, pre-rebase twin. On `phase-bidding-period-ui` only. Not an ancestor. |
| `549baab97` (Stage 1) | Yes | **Ancestor of HEAD.** Tip of `repair/buyer-tenant-activation` and `integrate/timed-offers`. |

**Conclusion:** `b34b0cda6` → `1c356d8d1` is a rebase, not a loss. The short hash changed; the work did not.

## R3. Does the current branch contain Stage 0 and Stage 1?

**Yes — both, in full.**

- **Stage 0** (canonical window as sole deadline source) — present via `1c356d8d1`.
- **Stage 1** (Buyer/Tenant activation at publication, fail-closed) — present via `549baab97`.

What is **NOT** in HEAD, and never was:

| Missing from `main` | Lives on | Contains |
|---|---|---|
| `5fef88f2c` owner offers dashboard | `phase-timed-offer-listings`, `phase-timed-listings-notifications`, `backup/timed-listings-2026-07-21` | `ListingOffersController`, `OwnerOfferComparisonPresenter`, `listings/offers-received.blade.php`, `ListingManagementAccess`, 2 test files, 7 route lines |
| The artifact itself | `audit/timed-offer-flow` | this document |

`git grep` for `ListingOffersController`, `offers-received`, `OwnerOfferComparisonPresenter` across `app resources routes tests` returns **zero hits**. Artifact Requirement 10 (owner "offers received on my listing" route) remains **unimplemented in `main`** — unchanged from the prior finding, not a new regression.

## R4. Is the timer architecture still present? — YES

All six proof obligations from the reopening brief, answered against HEAD:

### 1. Are `bidding_starts_at` / `bidding_ends_at` still persisted? — **Yes**

- `app/Models/OfferAuction.php:20-21` (fillable), `:28-29` (datetime casts).
- `database/migrations/2026_07_27_000002_replace_bidding_started_at_with_canonical_window.php` — renames `bidding_started_at` → `bidding_starts_at`, adds `bidding_ends_at`, **no backfill** (Decision B honoured, documented at `:26`).
- Written in exactly one place: `BiddingWindowService::markActivated()` (`:99-100`), inside a transaction, guarded by `if ($offerAuction->bidding_starts_at !== null) return false;` — never overwrites, never restarts.

### 2. Does every runtime countdown read only `bidding_ends_at`? — **Yes, across all four roles**

| Surface | Source at HEAD |
|---|---|
| `offer-listing/{seller,buyer,landlord,tenant}/search.blade.php:~214-223` | `$biddingWindows[$auction->id]->remainingSeconds()` — controller-built from stored `bidding_ends_at` |
| `offer-listing/seller/view.blade.php:1481-1492` | `$biddingWindow` only |
| `offer-listing/landlord/view.blade.php:~461` | `$biddingWindow` only |
| `offer-listing/buyer/view.blade.php:~500-511` | `$biddingWindow` only |
| `offer-listing/tenant/view.blade.php:~520-531` | `$biddingWindow` only |
| Seller sidebar "Bidding Ends" `:2486-2501` | `$bpEndsAtDisplay` — canonical (former deviation D-?, now fixed) |
| Seller Quick-Actions hub `:1086-1094` | `$_hubWindow->endsAtForDisplay()` — the dead `bidding_end_date`/`offer_deadline` derivation (D-10) is **gone** |

The five competing derivations catalogued in Part III are down to **one**.

### 3. Has `expiration_date` re-entered any bidding timer, enforcement, sorting, API, or fallback path? — **No**

`git grep expiration_date` over `app resources routes tests` returns 100+ hits, and **every one was classified**. In the offer-listing bidding surface, `expiration_date` survives only as:

- a **displayed listing field** — `{!! $row('Expiration Date', ...) !!}` in all four `view.blade.php` (seller `:1507`, landlord `:1080`, buyer `:919`, tenant `:967`). Display of an independent business field, not timer input.
- **prohibition comments** — e.g. `SellerOfferListingController:491` *"No arithmetic, no auction_time, no created_at, no expiration_date."*
- an **export column map** (`app/Exports/ListingFieldMaps/*`).

Every remaining hit is in the **Hire-an-Agent** auction product (`*AgentAuctionController`, `PropertyAuction*`, `hire_*` views) or `BuyerCriteria`/`TenantCriteria` — the scope boundary already recorded above under *Current state of record (2026-07-27)*.

`BackfillLinkedOfferAuction` was checked specifically: it contains **no** reference to `bidding_ends_at`, `bidding_starts_at`, `expiration_date`, `auction_time`, or `markActivated`. It cannot fabricate a window. Decision B holds.

### 4. Are `_competing-bids` and `PublicOfferFeedService` mounted on the real Seller listing-detail route? — **Yes**

```
GET /offer-listing/seller/view/{id}          routes/web.php:1155
  → SellerOfferListingController::view()     :88
  → PublicOfferFeedService::canView()        :140
  → ->build($offerAuction, 'seller')         :141
  → view('offer-listing.seller.view')        :143  (compact includes canViewBidFeed, bidFeed, biddingWindow)
  → @include('offer-listing.partials._competing-bids')   view.blade.php:1551
```

Landlord is mounted identically (`LandlordOfferListingController:185`, `landlord/view.blade.php:1125`). **Buyer and Tenant detail views do not include the partial** — consistent with Requirements 8/9, which specify the feed for Seller and Landlord listings only.

### 5. Does the current route use the repaired view or a duplicate legacy view? — **Both exist; the repaired one is canonical**

Two distinct detail surfaces resolve for offer listings:

| Route | Controller | View | Timer | Feed |
|---|---|---|---|---|
| `/offer-listing/seller/view/{id}` (`web.php:1155`) | `SellerOfferListingController::view` on **`SellerAgentAuction`** | `offer-listing/seller/view.blade.php` | **Canonical** | **Mounted** |
| `/offer/listing/view/{id}` (`web.php:711`, `offerPlayoffAccess`) | `AgentController::offerListingView` (`:495`) on **`OfferAuction`** | `agent/offer-listing-view.blade.php` | **None** | **None** |

The legacy page renders `Expiration Date` and `Auction Time` as **static rows** (`agent/offer-listing-view.blade.php:88-90`) — it has **no countdown element at all**. It is owner-scoped (`where('user_id', $uid)` unless admin) and reads a *different table* than the repaired page. It is a genuine duplicate surface and a latent hazard, but it cannot produce a ticking "N days remaining".

### 6. Do submitted bids remain included after the bidding period closes? — **Yes, window closure alone does not hide them**

`PublicOfferFeedService::build()` never consults `BiddingWindow`. The partial only adds a `Bidding Closed` badge in the card header (`_competing-bids.blade.php:69-71`). Closing the window does **not** remove a single row.

**But offer-level expiry does — see Regression B.**

## R5. Effect of `41db5d149` (HEAD)

`git show 41db5d149` — 6 files, +471/−36. Extracts the four hand-rolled `ending_soon` ORDER BY subqueries into `BiddingWindowService::endsAtSubquery()` / `applyEndingSoonOrder()` and adds `CAST(oa.id AS TEXT)` so the bigint↔EAV-text comparison stops raising `SQLSTATE[42883]` on PostgreSQL. Adds a 386-line regression suite.

**Assessment against the reopening questions:**

| Question | Answer |
|---|---|
| Changed timer ordering? | Only the **search ORDER BY**. Sort keys unchanged: `ends_at IS NULL ASC`, `ends_at ASC`, `created_at DESC`. |
| Changed deadline sourcing? | **No.** Still `oa.bidding_ends_at`, verbatim. The suite asserts the subquery contains none of `expiration_date`, `auction_time`, `created_at`, `bidding_starts_at`. |
| Changed query joins? | Yes — `oa.id = m.meta_value` became `CAST(oa.id AS TEXT) = m.meta_value`. **Widens** the match on PostgreSQL from *always error* to *correct*; on SQLite semantics are unchanged. Cannot drop rows. |
| Changed offer visibility? | **No.** Touches no offer query, no `PublicOfferFeedService`, no view. |

**`41db5d149` is exonerated for both regressions.** So are `1c356d8d1` and `549baab97`, neither of which touches offer status or the feed.

## R6. Regression A — timer showing ~35 / ~91 days

### Not reproducible from HEAD source

Every countdown path in the offer-listing product reads the stored canonical deadline. There is no surviving `expiration_date` fallback, no `created_at + auction_time` arithmetic, and no half-window completion. `CanonicalBiddingWindowTest` enforces this repository-wide, including `test_legacy_listing_without_stamps_does_not_fall_back_to_expiration_date` (`:242-256`).

A legacy listing that predates the migration has `bidding_starts_at` set and `bidding_ends_at` NULL → `BiddingWindow::uninitialized()` → `hasDeadline()` false → **no countdown renders at all**, and `OfferController:91` fails the bid closed. The failure mode of stale data is *silence*, not a wrong number. **35 or 91 days cannot be produced by the repaired path under any data state.**

### Ranked explanations, with discriminating evidence

**H1 — the screenshots are of the Hire-an-Agent surface (most likely).** That product is the *recorded scope boundary* above, and it still derives deadlines the prohibited way:

- `hire_seller_agent/search.blade.php:263` — `Carbon::parse($auction->created_at)->addDays($lengthDays)`
- `hire_tenant_agent/search.blade.php:265`, `hire_landlord_agent/search.blade.php:271`, `search-buyer-agent-auctions.blade.php:226` — identical
- `hire_landlord_agent/view.blade.php:2347-2356` — `$expiration = Carbon::parse($auction->get->expiration_date)` as CASE-2/CASE-3 fallback, then `:2367 $diff_d = $now->diffInDays($expiration)` rendered into a `Days` tile

A 90-day listing agreement reads ~91 days there; a ~5-week one reads ~35. These pages sit on `seller_agent_auctions` / `landlord_agent_auctions` — **the same rows** the repaired offer-listing pages render — so the same listing genuinely shows a canonical countdown on one URL and an `expiration_date`-derived one on another.
*Discriminator:* the URL in the screenshot. `/hire/...`, `/seller/agent/auction/detail/...`, or `/search/*-agent-auctions` ⇒ H1 confirmed.

**H2 — the deployed environment is not running `41db5d149`.** The repair landed 2026-07-27/28; the report is 2026-07-29.
*Discriminator:* `git rev-parse HEAD` on the server.

**H3 — the legacy `/offer/listing/view/{id}` page.** Shows `Expiration Date` as a static row and could be *read* as the bidding deadline, but renders no countdown.
*Discriminator:* is the number ticking?

### First bad commit

**None in the offer-listing timer path.** The originally-blamed commit `c729357b1` (2026-06-02, *"Fix offer listing hero timer to use expiration_date as primary source"*) was **corrected** by `1c356d8d1`. For H1 the defect is pre-existing and out of the repaired scope — never introduced by this workstream, merely never in it.

## R7. Regression B — a previously visible bid disappeared

### Mechanism, confirmed in source

```
Offer row  →  offers.status
  → PublicOfferFeedService::build()            :171-176
      ->where('offer_auction_id', $oa->id)
      ->whereIn('status', self::PUBLIC_STATUSES)     ← ['submitted','countered','accepted']
  → row dropped before it ever reaches the view
```

Now the erasing clock:

1. **`expires_at` is MANDATORY on every submit.** `OfferController::submit()` rejects the offer unless `expires_at` meta is present — `:165-167` (sale: *"response requested by date"*) and `:181-183` (rental). Every submitted bid therefore carries a self-destruct timestamp.
2. **A sweeper runs every 60 seconds.** `Kernel.php:44-46` → `$schedule->command('offers:expire-pending')->everyMinute()->withoutOverlapping(5)`.
3. **It flips `submitted`/`countered` → `expired`.** `ExpireOffersCommand:19-22` selects exactly those statuses with `expires_at < now()`; `OfferStateMachineService::ALLOWED_TRANSITIONS[:34]` permits `submitted → expired`.
4. **`expired` is not in `PUBLIC_STATUSES`.** The row vanishes from Competing Bids.
5. **`EnforcesRequestTimeExpiry` (`:31-32`) is a request-time safety net**, so the disappearance happens even if the scheduler is down.
6. **`assignBidderNumbers()` (`:222-226`) still counts expired roots**, so surviving bidders keep their numbers. The table loses a row with no gap and no notice — which is exactly why this reads as "the bid was deleted".

### Answering the reopening checklist

| Hypothesis | Verdict |
|---|---|
| Present in storage but excluded by status | **THIS.** `status = 'expired'`, excluded by `PUBLIC_STATUSES`. |
| Connected to a different auction | Not indicated — `build()` keys on the resolved `offer_auction_id`. |
| Filtered after the bidding window closes | **No.** `build()` never reads the window (R4.6). |
| Omitted by a changed query or join | **No.** `41db5d149` touched no offer query. |
| Hidden by authorization | **No** — `canView()` is all-or-nothing; it would empty the whole table, not one row. |
| Not passed to the view | No — `bidFeed` is passed whenever `canViewBidFeed`. |
| Legacy detail page rendering | Possible but distinct: `/offer/listing/view/{id}` has **no** feed at all, so *every* bid would be missing. |
| Actually missing from the database | **Cannot be excluded without a DB query** (see below). |

### Why this is a genuine defect, not intended behaviour

Requirement 7 of the approved architecture: *"Two different clocks, never merged or confused: listing `bidding_ends_at` controls whether new bids may be submitted; an individual offer's `expires_at` may control a response deadline for that specific offer."*

The offer's `expires_at` is the bidder's **"please respond by"** date to the *seller*. Letting it erase the bid from the **competitive record** merges the two clocks in the one direction the architecture did not anticipate: not a wrong deadline, but a *correct deadline with the wrong consequence*. Because `expires_at` is mandatory and the sweeper runs every minute, **every bid on the platform is guaranteed to eventually delete itself from the public feed while its listing's bidding window is still open.** A seller comparing offers loses candidates mid-auction.

### First bad commit

- `PUBLIC_STATUSES` introduced in **`8a3a5a918`** (ancestor of HEAD) — the feed shipped without an expired-bid policy.
- `ExpireOffersCommand` introduced in **`e42c000c1`** (Task #1950), predating the feed.

Neither is wrong alone. The defect was **created by composition** when `8a3a5a918` added a status-filtered public feed on top of an existing every-minute expiry sweep, with no test covering the interaction. **`8a3a5a918` is the first bad commit** — it is the one that made the pre-existing sweep user-visible as data loss.

### Not yet proven — requires database access

Whether *this specific* bid is expired versus genuinely absent cannot be settled from source. Run in the live environment:

```sql
SELECT o.id, o.status, o.submitted_at, o.expires_at, o.parent_offer_id,
       o.offer_auction_id,
       (SELECT m.meta_value FROM seller_agent_auction_metas m
         WHERE m.seller_agent_auction_id = :listing_id
           AND m.meta_key = 'linked_offer_auction_id' LIMIT 1) AS linked_oa
  FROM offers o
 WHERE o.offer_auction_id = (SELECT m.meta_value FROM seller_agent_auction_metas m
                              WHERE m.seller_agent_auction_id = :listing_id
                                AND m.meta_key = 'linked_offer_auction_id' LIMIT 1)
 ORDER BY o.id;

SELECT * FROM offer_event_logs
 WHERE offer_id = :offer_id ORDER BY created_at DESC;
```

`offer_event_logs` is decisive: `OfferExpirationService::expire()` writes an `offer_expired` row with `metadata->source = 'scheduled_command'` on every sweep transition. Its presence confirms this diagnosis outright; its absence redirects to "different auction" or "genuinely absent".

## R8. Proposed minimal fix

**Regression B — one-line behavioural change plus a label.**

`app/Services/Offers/PublicOfferFeedService.php`

```php
// Statuses that appear in the public feed. An expired offer stays on the
// competitive record: expires_at is the bidder's response deadline for the
// SELLER, not an instruction to erase the bid mid-auction (Requirement 7 —
// the two clocks must never be merged).
private const PUBLIC_STATUSES = ['submitted', 'countered', 'accepted', 'expired'];

private const PUBLIC_STATUS_LABELS = [
    'submitted' => 'Active',
    'countered' => 'In Negotiation',
    'accepted'  => 'Accepted',
    'expired'   => 'Expired',        // sanitized; no internal state leaks
];
```

Add `'Expired' => 'bg-secondary'` to `$statusBadge` in `_competing-bids.blade.php:59-64`.

Scope notes: `assignBidderNumbers()` needs no change — it already includes expired roots, so numbering stays stable. `withdrawn` and `rejected` are deliberately **left excluded**: a withdrawn bid was retracted by its author and a rejected one was refused by the owner; neither is a standing competitive term. Only `expired` represents *"a real bid whose response window lapsed"*. This is an owner-facing product decision and should be ratified as an amendment to Requirement 7 before merge, per Invariant 11.

**Regression A — no fix until the surface is identified.** If H1 is confirmed, the correct response is a scope decision (extend the canonical architecture to Hire-an-Agent, or accept the divergence and stop the two products sharing a listing row), not a patch. Do not "fix" a page that is out of contract by guessing.

## R9. Proposed regression tests

**B1 — expired bids stay on the record** (`tests/Feature/Offers/PublicOfferFeedPrivacyTest.php`)
Submit two offers; set one's `expires_at` to the past; run `offers:expire-pending`; assert `build()` still returns **two** rows and the expired one reports the sanitized label `Expired`.

**B2 — bidder numbering survives an expiry**
Three bidders, middle one expires. Assert the remaining two keep numbers **1** and **3** — no renumbering, no gap-filling.

**B3 — the two clocks stay separate** (the invariant test)
A listing whose `bidding_ends_at` is 5 days out, with a bid whose `expires_at` was yesterday. Assert the bid is present in the feed **and** `BiddingWindow::isClosed()` is `false`. This is the test whose absence allowed the composition defect.

**B4 — window closure does not empty the feed**
Travel past `bidding_ends_at`. Assert every `submitted` bid is still returned and the header badge reads `Bidding Closed`. Locks R4.6 behaviour that currently holds only by accident of omission.

**A1 — extend the Invariant-10 guard's file scope** (`CanonicalBiddingWindowTest:474-476`)
The repository guard currently globs only `resources/views/offer-listing/*/{view,search}.blade.php` and `app/Http/Controllers/*OfferListingController.php`. It does not cover `app/Services/Offers/`, `AgentController::offerListingView`, or `agent/offer-listing-view.blade.php`. Widen it, with the Hire-an-Agent tree added to an **explicit, commented exclusion list** so the boundary is asserted rather than merely absent.

**A2 — assert the duplicate surface carries no bidding timer**
Static assertion that `agent/offer-listing-view.blade.php` contains no countdown element, so the legacy page cannot silently regrow one.

## R10. Deviation Register additions (proposed — not yet ratified)

| ID | Deviation | Severity | Status |
|---|---|---|---|
| D-14 | Offer-level `expires_at` erases a bid from the public competitive record while its listing's bidding window is still open. Merges the two clocks Requirement 7 separates. | **High** | Open — fix proposed R8 |
| D-15 | The governing architecture artifact exists only on unmerged `audit/timed-offer-flow`; engineers on `main` cannot see the invariants binding them. | Medium | Open |
| D-16 | Duplicate listing-detail surface `/offer/listing/view/{id}` (`AgentController::offerListingView`) reads a different table, carries no canonical window and no bid feed. | Medium | Open |
| D-17 | Invariant-10 repository guard's file scope excludes `app/Services/Offers/`, `AgentController` and the legacy agent view. | Medium | Open — fix proposed R9/A1 |
| D-18 | Hire-an-Agent surfaces derive deadlines from `created_at + auction_time` and `expiration_date` on the *same listing rows* the repaired pages render. Recorded scope boundary; candidate source of Regression A. | Medium | Open — needs scope decision |

## R11. Change log entry

| Date | Change |
|---|---|
| 2026-07-29 | Regression reopening. Confirmed Stage 0 (`b34b0cda6` → rebased `1c356d8d1`) and Stage 1 (`549baab97`) are both ancestors of `main` at `41db5d149`, and the canonical timer architecture is fully intact. `41db5d149` exonerated. Regression A not reproducible in the repaired path — three ranked hypotheses recorded with discriminators. Regression B root-caused to composition of `PUBLIC_STATUSES` (`8a3a5a918`) with the every-minute `offers:expire-pending` sweep (`e42c000c1`). Deviations D-14…D-18 proposed. **No code, migration, database record, branch or commit changed.** |

---

# Regression B Repair — 2026-07-29

**Status: IMPLEMENTED.** Narrowly scoped to the owner-facing offer feed. The bidding-window architecture was not touched.

Additive section. All prior content, including the *Regression Reopening* investigation above, is preserved unmodified.

## RB1. Ratified product behaviour

An offer must not disappear from the listing owner's offers feed merely because its own response-request deadline passed. Ratified as an amendment to Requirement 7 (Invariant 11 satisfied — this is an explicit owner decision, recorded before merge per Invariant 12):

| Clock | Governs | May it hide a bid? |
|---|---|---|
| `offer_auctions.bidding_ends_at` | the listing's bidding-period countdown; whether new bids may be submitted | No |
| `offers.expires_at` | whether that one offer is still awaiting a response | No |

- Expiring an offer **may** change its status and its available actions.
- Expiring an offer **must not** erase it from the owner's offer history or comparison feed.
- A listing's `expiration_date` **must never** contribute to the bidding-period countdown.
- The countdown remains sourced solely from the persisted bidding window.

## RB2. Root cause (confirmed)

Composition of two individually-defensible behaviours:

1. `OfferController::submit()` makes `expires_at` **mandatory** on every submit (`:165-167` sale, `:181-183` rental — *"response requested by date"*).
2. `Kernel::schedule()` runs `offers:expire-pending` **every minute**; it flips `submitted`/`countered` → `expired` once `expires_at` passes.
3. `PublicOfferFeedService::PUBLIC_STATUSES` admitted only `['submitted','countered','accepted']`.

Therefore **every** bid was guaranteed to eventually delete itself from the owner's feed while its listing's bidding window was still open. `assignBidderNumbers()` already retained expired roots, so surviving bidders kept their numbers and the row vanished with no gap and no notice — which is why it read as deletion.

First bad commit: **`8a3a5a918`**, which introduced the status-filtered feed on top of the pre-existing sweep (`e42c000c1`) with no test covering the interaction.

## RB3. Files changed

| File | Change |
|---|---|
| `app/Services/Offers/PublicOfferFeedService.php` | `PUBLIC_STATUSES` += `'expired'`; `PUBLIC_STATUS_LABELS` += `'expired' => 'Expired'`; rationale docblock |
| `resources/views/offer-listing/partials/_competing-bids.blade.php` | `$statusBadge` += `'Expired' => 'bg-secondary'` |
| `tests/Feature/Offers/ExpiredOfferFeedRetentionTest.php` | **new** — 13 tests, B1–B7 |
| `tests/Feature/Offers/CanonicalBiddingWindowTest.php` | **+3 tests** — Invariant 10 guard expansion |

Two production lines of behaviour changed. Deliberately **not** touched: `ExpireOffersCommand`, `OfferExpirationService`, `OfferStateMachineService`, `expires_at`, `bidding_ends_at`, `BiddingWindowService`, `markActivated()`, and every migration.

`withdrawn` and `rejected` remain excluded — a retracted or refused bid is not a standing competitive term. Only `expired` means *"a real bid whose response window lapsed"*.

Action rules required **no change**: `expired` is already in `OfferStateMachineService::FINAL_STATUSES`, so `OfferPermissionService` already denies submit/counter/accept/reject/withdraw/cancel/expire. B4 characterizes that rather than altering it.

## RB4. Tests added (16)

`ExpiredOfferFeedRetentionTest` — B1 expired offer stays in the feed · B2 labelled `Expired`, never a live status · **B3 open window + lapsed offer deadline ⇒ owner sees both (the key assertion)** · B4 no invalid actions exposed, and rendering never mutates status · B5 withdrawn/rejected/draft stay excluded, allow-list and anonymity still enforced · B6 numbering stable when the middle and when the first bidder expires · B7 countdown unaffected, ignores `expiration_date`, and an uninitialized window still renders history.

`CanonicalBiddingWindowTest` — the duplicate offer-detail surface (`AgentController::offerListingView` + `agent/offer-listing-view.blade.php`) derives no deadline (locked clean); a frozen inventory of the 10 legacy self-computing Hire-Agent surfaces so no **new** one joins unnoticed and a repaired one forces the exemption to be removed; all four offer-listing detail views still read the shared `$biddingWindow`.

## RB5. Verification results

Baseline captured from a pristine `git archive HEAD` export (read-only; no worktree mutation).

| Suite | Baseline | After | Delta |
|---|---|---|---|
| `tests/Feature/Offers` | 56 failed / 811 passed | **55 failed / 828 passed** | **0 newly failing**, +16 new tests, +1 baseline artifact resolved |
| `tests/Unit` | 224 failed / 5961 passed | **224 failed / 5961 passed** | identical failing set |
| `CanonicalBiddingWindowTest` | — | **26 passed** | +3 |
| `PublicOfferFeedPrivacyTest` | — | **25 passed** | unchanged |
| `ExpiredOfferFeedRetentionTest` | — | **13 passed** | new |

`comm` diff of failing-test names: **zero newly failing tests** in either suite.

**Pre-existing failures are unrelated and untouched** — 12 `QueryException`s from SQLite lacking `ILIKE`, and HTML/message assertions on offer *detail* pages. None reference the feed. The one baseline-only failure (`test_no_production_files_were_modified`) is task scaffolding that shells out to `git diff`; it "failed" solely because the export has no `.git`. Both changed files were already in its allowlist.

**Fail-without-fix proof.** With `PUBLIC_STATUSES` temporarily reverted, **8 of the 13** new tests fail (B1, B2, B3, both B6, two B5, one B7). The other 5 guard behaviour that is already correct. These are genuine regression locks, not tautologies.

`git diff --check`: clean.

## RB6. Timer independence — confirmed

The repair touches only which **statuses** the feed displays. No countdown code path was modified.

- B7 asserts `BiddingWindowService::for()` returns the unchanged persisted `bidding_ends_at` with an expired offer present, that the window is not closed by a lapsed offer deadline, and that a fixture `expiration_date` of `2027-01-31` never influences it.
- `test_bidding_window_service_performs_no_deadline_arithmetic_outside_activation` still passes: `addDuration()` has exactly one call site.
- `test_no_offer_listing_surface_derives_a_bidding_deadline` still passes across all four roles.

**`expiration_date` remains absent from every bidding timer path.**

## RB7. Runtime / database verification — NOT performed

This worktree has no `.env` and no database. `vendor/` was installed locally via `composer install` to run the test suite; **no secret was read or copied from the primary workspace, and no database was contacted.** All results above are from the SQLite in-memory harness (`phpunit.xml:40-41`).

The following read-only queries remain outstanding for the live environment. **They mutate nothing.**

```sql
-- 1-3. Does the affected offer exist, and what is its status / expires_at?
SELECT o.id, o.status, o.submitted_at, o.expires_at, o.parent_offer_id, o.offer_auction_id
  FROM offers o
 WHERE o.offer_auction_id = (SELECT m.meta_value FROM seller_agent_auction_metas m
                              WHERE m.seller_agent_auction_id = :listing_id
                                AND m.meta_key = 'linked_offer_auction_id' LIMIT 1)
 ORDER BY o.id;

-- 4. The listing's canonical bidding window (must be independent of the above).
SELECT oa.id, oa.bidding_starts_at, oa.bidding_ends_at
  FROM offer_auctions oa
 WHERE CAST(oa.id AS TEXT) = (SELECT m.meta_value FROM seller_agent_auction_metas m
                               WHERE m.seller_agent_auction_id = :listing_id
                                 AND m.meta_key = 'linked_offer_auction_id' LIMIT 1);

-- 5. Decisive: the scheduled command's own audit trail.
SELECT id, offer_id, event_type, from_status, to_status, metadata, created_at
  FROM offer_event_logs
 WHERE offer_id = :offer_id
 ORDER BY created_at DESC;
-- Expect event_type='offer_expired', from_status='submitted',
-- metadata->>'source'='scheduled_command'. Its presence confirms this diagnosis.
```

Step 6 — that the repaired feed returns the record — is verified in the harness by B1/B3 and should be re-confirmed on the real listing page after deploy.

## RB8. Unresolved legacy route risk

**Regression A is NOT fixed and was not touched.** It was not reproducible from the repaired path; the ranked hypotheses in R6 stand. Deliberately deferred, per the brief's instruction not to repair unrelated legacy surfaces:

| Risk | Status |
|---|---|
| **D-18** — 10 legacy Hire-Agent / legacy-auction views derive deadlines from `created_at + auction_time` and `expiration_date`, on the **same listing rows** the repaired pages render | Open. Now **frozen as an inventory** by `test_no_new_legacy_surface_starts_deriving_a_bidding_deadline` — contained, not repaired. Needs a scope decision. |
| **D-16** — duplicate detail surface `/offer/listing/view/{id}` reads a different table, no canonical window, no bid feed | Open. Now **locked clean** against growing a countdown. |
| **D-15** — the governing artifact lives only on unmerged `audit/timed-offer-flow` | Open. |
| **Regression A identification** | Blocked on the screenshot URL. |

The confirmed self-computing surfaces are listed verbatim in `CanonicalBiddingWindowTest::LEGACY_SELF_COMPUTING_SURFACES`.

## RB9. Change log entry

| Date | Change |
|---|---|
| 2026-07-29 | **Regression B repaired.** `expired` added to the owner feed's displayable statuses with an `Expired` label and badge; `withdrawn`/`rejected` still excluded. Action rules, `expires_at`, the expiry command and the entire bidding-window architecture unchanged. 16 tests added (13 retention/B1–B7, 3 Invariant-10 guard expansion). 0 newly failing tests across `tests/Feature/Offers` and `tests/Unit`. Regression A untouched and still open. |
