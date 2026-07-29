# Hire Agent Listing Framework — Implementation Plan

**Branch:** `ux/hire-agent-create-offer-parity`
**Baseline commit:** `41db5d149`
**Companion:** `docs/investigations/hire-agent-vs-create-offer-ux-parity-2026-07-29.md`
**Authorization state:** Milestone 1 only

---

## Document provenance

> **This document is a reconstruction.** The original was uncommitted and was lost in the
> 2026-07-29 container reset that destroyed the isolated worktree. See the provenance note
> in the companion investigation for detail.
>
> Tagging used below:
>
> - **[APPROVED]** — owner-ratified. Binding.
> - **[VERIFIED]** — re-confirmed against the code at `41db5d149` during reconstruction.
> - **[RECONSTRUCTED]** — rebuilt from context; **milestone 2–9 titles and scoping fall in
>   this category and require owner confirmation before any of them is opened.** The
>   *count* of nine is approved; the exact decomposition below is a good-faith rebuild.
>
> Milestone 1 is recorded at higher confidence than 2–9 because its scope was actively
> under implementation when the reset occurred, including corrections discovered in flight.

---

## Standing constraints

All nine milestones are bound by the approved requirements in the companion investigation,
§3. Restated here in short form so this document is usable standalone: **[APPROVED]**

1. No Bidding Period mode in Hire Agent.
2. No countdown timer in Hire Agent.
3. The timer is never connected to `expiration_date`.
4. No transparent bidding.
5. Competing agents cannot see another agent's proposal, amount, activity, rank, order,
   identity, count, or summary.
6. Only the listing owner and authorized administrators may review all proposals.
7. Privacy outranks visual consistency and status precision.
8. No urgency language or urgency visuals.
9. The reusable Listing Detail Framework remains the architectural objective.
10. Nine milestones.
11. Milestone 1 is the only authorized implementation milestone.

### Process constraints

- **Isolated worktree only.** All implementation happens in
  `/home/runner/worktrees/ux-hire-agent-create-offer-parity`. The primary workspace at
  `/home/runner/workspace` is never edited, staged, stashed, reset, cleaned, or committed
  against for this work. The duplicate branch and worktree
  `ux/hire-agent-listing-redesign` are not used.
- **Checkpoint commits are the loss-prevention mechanism.** The external worktree does not
  survive container resets. Completed work is committed to
  `ux/hire-agent-create-offer-parity` at defined checkpoints. This is the lesson of the
  2026-07-29 reset, in which zero committed work meant total loss.
- **No merge, no push** without separate explicit instruction.
- **Tests run against SQLite `:memory:` only.** No migrations, tests, seeders, or writes
  against `heliumdb` or any networked PostgreSQL instance.

---

## Milestone map

| # | Milestone | State | Confidence |
|---|---|---|---|
| 1 | Baseline, inventory, and structural normalization | **Authorized** | High |
| 2 | Competing-agent proposal privacy | Blocked — needs approval | [RECONSTRUCTED] |
| 3 | Timer and countdown removal | Blocked — needs approval | [RECONSTRUCTED] |
| 4 | Status vocabulary normalization | Blocked — needs approval | [RECONSTRUCTED] |
| 5 | Listing Detail Framework extraction | Blocked — needs approval | [RECONSTRUCTED] |
| 6 | Hire Agent adoption of the framework | Blocked — needs approval | [RECONSTRUCTED] |
| 7 | Create Offer adoption of the framework | Blocked — needs approval | [RECONSTRUCTED] |
| 8 | Four-role parity sweep | Blocked — needs approval | [RECONSTRUCTED] |
| 9 | Regression hardening and documentation | Blocked — needs approval | [RECONSTRUCTED] |

The ordering rationale that survives reconstruction: **privacy (2) precedes framework work
(5–7)**. Extracting shared components before the privacy rules are enforced would bake
disclosure into the shared layer and then propagate it to both surfaces. Requirement 7
(privacy outranks consistency) is only cheap to honour if privacy lands first.

---

## Milestone 1 — Baseline, inventory, and structural normalization

**Status: AUTHORIZED. The only milestone that may be implemented.**

### 1.1 Objective

Establish a trustworthy, reproducible test baseline for the branch, and correct the one
file-organization asymmetry in the Hire Agent detail views — with **zero user-facing
behavior change**.

### 1.2 Success criteria

- A full baseline PHPUnit run is recorded from a clean start, with pre-existing failures
  enumerated and attributed.
- Database isolation is proven before the suite runs: SQLite `:memory:`, no networked
  PostgreSQL connection opened.
- The Buyer Hire detail view sits at a role-consistent path.
- Every reference to the old Buyer Hire view path is updated.
- The production-file guard passes.
- The complete diff contains no behavior change — no markup, copy, logic, validation,
  routing, or data changes. Path strings only.

### 1.3 Scope — the Buyer Hire view relocation

`resources/views/buyerAgentAuctionDetail.blade.php` → `resources/views/hire_buyer_agent/view.blade.php`

Rationale and destination-already-exists finding: companion investigation §4.1.

The move is performed with `git mv` so history follows the file, and the file **contents are
not touched at all**.

### 1.4 Corrections discovered before the container reset **[VERIFIED]**

These were found during the first, lost implementation attempt and are recorded here so
they are not rediscovered a third time. All four have been re-verified at `41db5d149`.

**Correction A — the relocation is the view plus exactly three test references.**

Complete reference inventory for `buyerAgentAuctionDetail`:

| # | Location | Kind |
|---|---|---|
| — | `resources/views/buyerAgentAuctionDetail.blade.php` | the view itself |
| 1 | `app/Http/Controllers/BuyerAgentAuctionController.php:455` | `return view('buyerAgentAuctionDetail', ...)` |
| 2 | `tests/Feature/Offers/OfferWorkflowReadinessTest.php:340` | allow-list entry |
| 3 | `tests/Feature/Offers/OfferWorkflowReadinessTest.php:397` | allow-list entry |
| 4 | `tests/Feature/Storage/PublicMediaViewSmokeTest.php:24` | approved-views entry |

So: one view, one controller call site, and **three test references**. There are no route
references, no Blade `@include`/`@extends` references, and no JavaScript references.

**Correction B — `OfferWorkflowReadinessTest.php` holds *two* references, not one.**

Lines 340 and 397 are both allow-list entries in
`test_no_production_files_were_modified`, in two different commented blocks (the
"Create-Offer / Hire-Agent launch remediation" block and the later "Pre-existing
working-tree changes" block). A single find-and-replace that stops at the first hit leaves
the suite red. Both must be updated.

**Correction C — `PublicMediaViewSmokeTest.php` holds one reference, and it is contract-bearing.**

Line 24 is an entry in the `VIEWS` constant. That test asserts the file **exists**,
**compiles**, and **still contains `ListingMediaUrl::get`**. The path must be updated in
lockstep with the `git mv`, or `assertFileExists` fails.

**Correction D — `client-info.blade.php` is referenced only by `PublicMediaViewSmokeTest.php`.**

`resources/views/components/listing/client-info.blade.php` has exactly one reference in the
repository: `PublicMediaViewSmokeTest.php:26`. No view includes it. It is **not** dead code
— it is test-contract-bearing. **Do not move, rename, or delete it in Milestone 1**, and do
not "clean it up" on the grounds that grep shows no view using it. Recorded because it was
initially misread as removable.

**Consequence of the production-file guard.** Because the relocation touches `app/` and
`resources/`, the new path must be added to the `OfferWorkflowReadinessTest` allow-list, and
`BuyerAgentAuctionController.php` must be allow-listed, in the same commit. Companion
investigation §4.3.

### 1.5 Out of scope for Milestone 1

Restated from the companion investigation §5, because this is the boundary that matters
most: no timer removal, no privacy implementation, no status vocabulary change, no framework
UI, no Create Offer change, no deletion of the orphaned
`resources/views/sellerAgentAuctionDetail.blade.php`, no edits to the relocated file's
contents.

### 1.6 Required verification

1. `tests/Feature/Offers/OfferWorkflowReadinessTest.php` — green, including the guard.
2. `tests/Feature/Storage/PublicMediaViewSmokeTest.php` — green.
3. Full suite — no new failures relative to the recorded baseline.
4. `git diff` review — path strings only; `git mv` recorded as a rename with 100% similarity.

### 1.7 Checkpoint

Commit message: `test(hire-agent): establish listing framework baseline`

Created only after every success criterion passes. Not merged. Not pushed.

---

## Milestones 2–9 **[RECONSTRUCTED — require owner confirmation]**

Scoping sketches only. None may be opened without explicit approval, and the owner should
expect to correct these.

### Milestone 2 — Competing-agent proposal privacy

Enforce approved requirements 4, 5, and 6. Audit every Hire Agent surface for disclosure of
another agent's proposal, amount, activity, rank, order, identity, **count**, or **summary**.
Aggregates and counts are in scope, not just per-agent detail. Establish owner/admin-only
review as the single authorized path to the full proposal set, enforced server-side rather
than by view-level hiding.

### Milestone 3 — Timer and countdown removal

Enforce requirements 2, 3, and 8. Remove countdown markup from the four Hire detail views.
Sever any timer-to-`expiration_date` linkage rather than hiding a still-wired timer. Sweep
urgency copy and urgency visuals at the same time, since requirement 8 is independent of the
timer's presence.

### Milestone 4 — Status vocabulary normalization

A shared status vocabulary for both surfaces, subject to requirement 7: where a precise
status would disclose competitive information, the vaguer status wins.

### Milestone 5 — Listing Detail Framework extraction

Extract the shared regions into the reusable framework (requirement 9) without adopting it
yet — build the layer, keep both surfaces on their current markup, so extraction and
migration fail independently.

### Milestone 6 — Hire Agent adoption

Migrate the four Hire detail views onto the framework, privacy rules already enforced.

### Milestone 7 — Create Offer adoption

Migrate the Create Offer detail views onto the same framework.

### Milestone 8 — Four-role parity sweep

Per `CLAUDE.md`, almost everything is quadruplicated across Seller / Buyer / Landlord /
Tenant, and the schema is asymmetric — Seller/Buyer use native columns while
Landlord/Tenant use EAV meta. Confirm all four roles reached parity and that the EAV-backed
roles were not silently skipped.

### Milestone 9 — Regression hardening and documentation

Lock the approved requirements into tests — especially the nine prohibited disclosure
dimensions and the no-timer rule, which are exactly the kind of constraint that regresses
silently. Update `CLAUDE.md` and these documents.

---

## Frozen code — do not touch in any milestone

Per `CLAUDE.md`:

- **`initializeLimitedService()`** in all four Create Offer Listing Blade files. Frozen
  legacy code for the Limited Service flow. Never modify, test, or clean up anything inside
  it.
- **`TenantAgentAuction` Livewire component.** Intentionally excluded from the
  `HasListingLifecycle` trait. Do not refactor it onto the trait. Note that this component
  serves live Hire flows, so milestones 6 and 8 must work around it rather than through it.
