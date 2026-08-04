# Hire Agent vs Create Offer — UX Parity Investigation

**Date:** 2026-07-29
**Branch:** `ux/hire-agent-create-offer-parity`
**Baseline commit:** `41db5d149`
**Status:** Approved requirements — implementation gated to Milestone 1 only

---

## Document provenance

> **This document is a reconstruction.**
>
> The original was an uncommitted working-tree file in the isolated worktree
> `/home/runner/worktrees/ux-hire-agent-create-offer-parity`, which was destroyed by a
> Replit container reset on 2026-07-29. Nothing had been committed to the branch, and
> nothing had been staged, so the original text is unrecoverable.
>
> It has been rebuilt from the approved design decisions and from direct re-verification
> against the code at `41db5d149`. Sections are tagged:
>
> - **[APPROVED]** — a requirement explicitly ratified by the product owner. Binding.
>   Reproduced faithfully in substance.
> - **[VERIFIED]** — a factual claim about the codebase re-confirmed at `41db5d149`
>   during reconstruction. Command output backed.
> - **[RECONSTRUCTED]** — supporting analysis rebuilt from context. Directionally correct
>   but not owner-ratified in this exact wording; correct freely.
>
> No approved requirement has been softened, broadened, or reinterpreted.

---

## 1. Purpose

The Hire Agent listing detail experience and the Create Offer listing detail experience
evolved independently. They now differ in layout, component structure, status vocabulary,
and file organization — while sharing a large amount of duplicated markup.

This investigation asked whether the two surfaces should converge on a single reusable
Listing Detail Framework, and if so, under what constraints.

**Answer: yes — but convergence is subordinate to privacy.** Hire Agent is not a
transparent auction and must never be made to look like one in the name of consistency.

---

## 2. The critical asymmetry: Hire Agent is not an auction

This is the central finding of the investigation and the source of most of the binding
requirements below.

Create Offer is a listing surface where competitive visibility is part of the product.
Hire Agent is a **sealed proposal** surface: agents submit proposals to a client, and
those proposals are confidential to the client.

Despite that, the Hire Agent views inherited auction-derived UI idioms from the legacy
codebase — countdown markup, bidding-period language, and expiration plumbing. **[VERIFIED]**
Countdown/timer idioms are present in the Hire detail views:

```
resources/views/buyerAgentAuctionDetail.blade.php
resources/views/hire_seller_agent/view.blade.php
resources/views/hire_landlord_agent/view.blade.php
resources/views/hire_tenant_agent/view.blade.php
```

and `expiration_date` appears in the same four views. These are the artifacts the later
milestones remove. They are **not** evidence that Hire Agent is intended to be an auction.

---

## 3. Approved requirements (binding)

These are the ratified constraints. Every milestone must satisfy all of them. **[APPROVED]**

### 3.1 No bidding period

1. **Hire Agent has no Bidding Period mode.** There is no bidding-period concept in the
   Hire Agent experience — not as a mode, not as a setting, not as a display state.

### 3.2 No timer

2. **Hire Agent has no countdown timer.** No countdown, no elapsed clock, no
   "time remaining" display on any Hire Agent surface.
3. **The timer is never connected to `expiration_date`.** Even where an `expiration_date`
   value exists in storage, it must not drive a timer, countdown, or any time-pressure
   display. The absence of a timer is not to be implemented by "hiding" a timer that is
   still wired to `expiration_date`.

### 3.3 No transparent bidding

4. **Hire Agent has no transparent bidding.**
5. **Competing agents cannot see another agent's proposal, amount, activity, rank, order,
   identity, count, or summary.** All nine of these are individually prohibited. In
   particular, *count* and *summary* are prohibited: "3 other agents have applied" and
   "proposals range from X to Y" are both violations, even though neither names an agent.
   Aggregates leak competitive information and are treated as disclosure.
6. **Only the listing owner and authorized administrators may review all proposals.**

### 3.4 Precedence rule

7. **Privacy outranks visual consistency and status precision.** Where a framework
   pattern, a shared component, or a more accurate status label would reveal prohibited
   information, the privacy constraint wins. Accept a less consistent and less precise
   surface rather than leak. This rule exists to pre-resolve the conflicts that framework
   adoption will surface — it is not advisory.

### 3.5 No urgency

8. **No urgency language or urgency visuals.** No "ending soon", "hurry", "last chance",
   "act now"; no urgency-coded colors, badges, pulsing, or animation. This holds
   independently of requirement 2 — removing the timer does not license urgency copy
   elsewhere.

### 3.6 Architecture and process

9. **The reusable Listing Detail Framework remains the architectural objective.** The goal
   is one framework serving both Hire Agent and Create Offer, with role and flow
   differences expressed through configuration and slots rather than duplicated markup.
10. **The implementation plan remains organized into the approved nine milestones.**
11. **Milestone 1 remains the only authorized implementation milestone.** No milestone
    beyond 1 begins without explicit owner approval.

---

## 4. Structural findings

### 4.1 The four live Hire Agent listing-detail views **[VERIFIED]**

| Role | Controller | View rendered |
|---|---|---|
| Seller | `app/Http/Controllers/SellerAgentAuctionController.php:490` | `resources/views/hire_seller_agent/view.blade.php` |
| Buyer | `app/Http/Controllers/BuyerAgentAuctionController.php:455` | `resources/views/buyerAgentAuctionDetail.blade.php` |
| Landlord | `app/Http/Controllers/LandlordAgentAuctionController.php:546` | `resources/views/hire_landlord_agent/view.blade.php` |
| Tenant | `app/Http/Controllers/TenantAgentAuctionController.php:307` | `resources/views/hire_tenant_agent/view.blade.php` |

**The Buyer view is the sole naming outlier.** Three of four roles live at
`resources/views/hire_<role>_agent/view.blade.php`. Buyer alone sits at the repository
view root as `buyerAgentAuctionDetail.blade.php` (363 KB).

`resources/views/hire_buyer_agent/` **already exists** and already holds
`bid_detail.blade.php` and `view_counter_terms.blade.php` — but no `view.blade.php`.
The destination directory for the relocation is therefore already established and already
role-consistent. This is what makes the relocation a low-risk, naming-only change and the
right content for Milestone 1.

### 4.2 An orphaned legacy Seller detail view **[VERIFIED]**

`resources/views/sellerAgentAuctionDetail.blade.php` (64 KB) is rendered by **no**
controller. Its only remaining reference in the repository is an allow-list entry at
`tests/Feature/Offers/OfferWorkflowReadinessTest.php:389`.

It is dead code. **It is explicitly out of scope for Milestone 1** — deleting it is a
behavior-adjacent cleanup that needs its own authorization, and it is recorded here only
so the inventory is complete.

### 4.3 The production-file guard constrains every milestone **[VERIFIED]**

`tests/Feature/Offers/OfferWorkflowReadinessTest::test_no_production_files_were_modified`
shells out to `git diff --name-only` and `git ls-files --others --exclude-standard` over
`app/ config/ routes/ database/ resources/` and fails on any changed or untracked path not
present in an in-test allow-list.

Consequences, which apply to all nine milestones:

- Any change under those five directories **must** be added to the allow-list in the same
  commit, or the suite goes red.
- `docs/` is **not** scanned, so documentation commits are unaffected.
- The guard compares against `HEAD`, so it behaves per-worktree and per-checkpoint. Work
  committed at a checkpoint stops being "modified" and drops out of the guard's view.

### 4.4 A view referenced only by a test **[VERIFIED]**

`resources/views/components/listing/client-info.blade.php` is referenced by
`tests/Feature/Storage/PublicMediaViewSmokeTest.php:26` and **by nothing else** in the
repository — no view includes it, no controller renders it.

`PublicMediaViewSmokeTest` asserts, for each of eight approved views, that the file exists,
that it compiles as Blade, and that it still contains a `ListingMediaUrl::get` call. So
`client-info.blade.php` cannot be moved, renamed, or deleted as "unused" without failing
that test. It looks like dead code by grep and is not, by test contract.

### 4.5 Shared duplication **[RECONSTRUCTED]**

The four Hire detail views plus the Create Offer detail views carry substantial duplicated
markup for the same conceptual regions (media, client info, terms display, compensation,
action rail). This duplication is the practical argument for the framework: today a
requirement like "remove the timer" is a four-to-eight-file edit with four-to-eight chances
to miss a surface.

---

## 5. Explicit non-goals for Milestone 1

Milestone 1 establishes a baseline and corrects file organization. It does **not**:

- remove timers or countdowns;
- implement proposal privacy;
- change status vocabulary;
- introduce or adopt framework UI;
- alter Create Offer;
- delete the orphaned `sellerAgentAuctionDetail.blade.php`;
- change any user-facing behavior whatsoever.

---

## 6. Related documents

- `docs/investigations/hire-agent-listing-framework-implementation-plan.md` — the nine-milestone plan.
