# Match Check — Phase 4 · C-Numbering Reconciliation

**Status:** Reconciliation / docs-only. **No code, config, or test is changed by this document.**
**Purpose:** The committed build plan and the as-built git history **both** use the labels
`C5` / `C6` / `C7`, but for **different work**. This doc freezes that ambiguity: it maps the two
sequences against each other, states what is already satisfied vs. still open, and proposes one
**unified forward numbering** so no future slice is ever called "C5/C6/C7" ambiguously again.

Baseline: HEAD is the authoritative C7 commit **`3847d52cd`** (`MatchCheckCriteriaLoader`).

> ⚠️ Not to be confused with the **Matching V2** `C<n>` series (`§MatchingV2 C6/C6.1/C7`), which
> is a wholly separate track and is **out of scope** here.

---

## 1. Two sequences, same labels

- **Plan-C<n>** = the slices defined in `docs/mls-direct-import-phase4-build-plan.md` (dated
  2026-07-05; locked to decisions F1–F9). A *design* enumeration.
- **git-C<n>** = the slices actually committed to git (`feat(match-check): … Phase 4 Wave N/CN`).
  The *as-built* record.

**C1–C4 are identical in both.** They diverge at C5, and the divergence is the entire reason for
this doc. Going forward we use only the **git-C<n>** numbering (git is the committed source of
truth); the plan's C5–C12 numbers are **retired as identifiers** and survive only as feature names.

---

## 2. Build plan — Plan-C1 … Plan-C12 (with current status)

| Plan-C# | Wave | Feature (F-ref) | Status vs. as-built |
|---------|------|-----------------|---------------------|
| C1 | 0 | Flag + `CheckMatchCheckEnabled` middleware | ✅ **Satisfied** (git-C1 `30b1bbca1`) |
| C2 | 1 | `ListingVisibilityGate` (F9) | ✅ **Satisfied** (git-C2 `957e0ddc9`) |
| C3 | 1 | `CriteriaIntentDetector` (F5) | ✅ **Satisfied** (git-C3 `da091f84d`) |
| C4 | 1 | `CriteriaListingResolver::resolvePreferred` (F5) | ✅ **Satisfied** (git-C4 `081a351c4`) |
| C5 | 2 | **`MatchReport` DTO** + extend `BuyerMatchResult` (F3/F8) | ❌ **Open** — git built something else at C5 |
| C6 | 2 | **`BuyerMatchResultBuilder`** report blocks: why-not / confidence / recommendations (F3) | ❌ **Open** — git built something else at C6 |
| C7 | 3 | **`mapOneDetailed()`** + non-residential reconciliation (F4) | ❌ **Open** — git built something else at C7 |
| C8 | 4 | `LocationDnaEnrichmentGuard` + opt-out lookup param (F6) | ❌ **Open** |
| C9 | 5 | `MlsPropertyMatchAnalysisService` orchestrator (F1/F2/F5/F6/F9) | ⚠️ **Partially satisfied** — see §4 |
| C10 | 6 | Route + controller + view (`/match-check`) | ❌ **Open** |
| C11 | 6 | Compliance regression test (F7/F9) | ❌ **Open** |
| C12 | 7 | "Better matches" low-score tail (F2) | ❌ **Open** |

---

## 3. As-built git — git-C1 … git-C7 (what each delivered, and its plan mapping)

| git-C# | Commit | Delivered | Maps to plan as… |
|--------|--------|-----------|------------------|
| C1 | `30b1bbca1` | flag + middleware | = Plan-C1 |
| C2 | `957e0ddc9` | `ListingVisibilityGate` + `VisibilityDecision` | = Plan-C2 |
| C3 | `da091f84d` | `CriteriaIntentDetector` | = Plan-C3 |
| C4 | `081a351c4` | `resolvePreferred()` | = Plan-C4 |
| C5 | `e755ccbc9` | `MatchCheckOrchestrator::prepare()` + `MatchCheckPreparation` (flag→visibility→intent→resolve) | **New**: a decomposed piece of **Plan-C9's** front half. *Not* Plan-C5. |
| C6 | `3384cc0a2` | `MatchCheckScorer` + `MatchCheckResult` + `evaluate()` (delegates to `BuyerMatchScorer`) | **New**: the `scorer->score()` seam of **Plan-C9**, plus a **lean** score VO. *Not* Plan-C5/C6 — `MatchCheckResult` carries only status/score/categoryScores, **not** the rich why-not/confidence/recommendations of a `MatchReport`. |
| C7 | `3847d52cd` | `MatchCheckCriteriaLoader` (descriptor → `BuyerCriteriaPayload`, unwired) | **New**: the "load criteria → `BuyerCriteriaPayload`" step of **Plan-C9**, extracted as a reusable service. *Not* Plan-C7. |

**Summary of the divergence:** git took the plan's single **C9 orchestrator** and started building
its *internals* early, as three granular, independently-inert slices (`prepare` / `score` / `load`) —
using a **lean `MatchCheckResult`** instead of the plan's rich, serializable `MatchReport`. It has
**not** yet built the plan's report-model chain (Plan-C5 DTO, Plan-C6 builder blocks, Plan-C7
detailed mapper), the enrichment guard (Plan-C8), the MLS#/address lookup front-end, the HTTP
surface (Plan-C10/C11), or the better-matches tail (Plan-C12).

---

## 4. Already satisfied vs. still open

**Satisfied (committed, inert, tested):**
- Plan-C1…C4 in full.
- The *scoring-composition skeleton* of Plan-C9: `prepare()` (visibility/intent/criteria selection),
  `evaluate()` (delegated numeric scoring via the existing `BuyerMatchScorer`), and the criteria
  **payload producer** (`MatchCheckCriteriaLoader`).

**Open (not yet built):**
1. **Seam gap (git-local):** `MatchCheckCriteriaLoader` has **no caller**. `evaluate()` still takes an
   external `?BuyerCriteriaPayload` and returns `CRITERIA_NOT_LOADED` when none is passed, so the
   as-built pipeline **cannot yet produce a `SCORED` result on its own**. This gap exists only because
   git decomposed the loader out of the orchestrator; the plan had no separate seam here.
2. **Rich report model** — Plan-C5 `MatchReport` DTO + optional `BuyerMatchResult` fields (F3/F8).
   git's `MatchCheckResult` is a thin score VO and does **not** cover why-not/confidence/recommendations.
3. **Report builder blocks** — Plan-C6 (`buildWhyNot/buildConfidence/buildRecommendations`, F3).
4. **Detailed view mapper + F4 reconciliation** — Plan-C7 (`mapOneDetailed()`).
5. **Enrichment throttle** — Plan-C8 (`LocationDnaEnrichmentGuard` + `dispatchDna` opt-out param, F6).
6. **Full orchestrator** — Plan-C9: MLS#/address **lookup** front-end (`BridgeListingLookupService`,
   `dispatchDna:false`) → compose the git primitives → build a `MatchReport` → enrichment via the guard.
   The git `MatchCheckOrchestrator` is a **subset** of this (no lookup, lean result, no report build).
7. **HTTP surface** — Plan-C10 route/controller/view; Plan-C11 compliance regression test (F7/F9).
8. **Optional tail** — Plan-C12 "better matches" (F2).

---

## 5. One architectural fork to settle (affects §6 ordering)

Does the committed **`MatchCheckOrchestrator` grow into** the Plan-C9 orchestrator, or does a **new
`MlsPropertyMatchAnalysisService`** become the orchestrator and merely *consume* the git primitives?

- **Stance (i) — grow it (recommended).** `MatchCheckOrchestrator` already embodies `prepare()`+
  `evaluate()`. We wire the loader into it, enrich its output to a `MatchReport`, and add a lookup
  front-end. One orchestrator; the git primitives are durable. Building a second parallel service
  would duplicate `prepare/score`.
- **Stance (ii) — replace it.** Introduce Plan-C9's `MlsPropertyMatchAnalysisService` as the real
  orchestrator; `MatchCheckOrchestrator.evaluate()` becomes a thin internal helper (or is absorbed).
  Under (ii), wiring the loader into `evaluate()` risks being throwaway.

**Recommendation: stance (i).** It preserves every committed git primitive and avoids a parallel
orchestrator. The forward sequence in §6 assumes (i). *(If you prefer (ii), only the first forward
slice changes — say so at review and I'll re-cut §6.)*

---

## 6. Proposed unified forward sequence (git-C8 → git-C16)

Continues the **git** numbering from HEAD (`3847d52cd` = git-C7). Each slice stays **additive, inert
where appropriate, and leaves `mls_match_check.enabled` OFF**; none touch Matching V2.

| New # | Slice | Delivers (plan-feature) | Touches live file? | Notes |
|-------|-------|-------------------------|--------------------|-------|
| **git-C8** | **Wire `MatchCheckCriteriaLoader` into `evaluate()`** — close the `CRITERIA_NOT_LOADED` seam | *(git-local; enables Plan-C9 end-to-end scoring)* | edits `MatchCheckOrchestrator` (committed) + 1 existing test's semantics | Smallest step; makes the backend produce `SCORED` end-to-end. Inert while flag OFF. |
| git-C9 | `MatchReport` DTO + optional `BuyerMatchResult` fields | = Plan-C5 (F3/F8) | additive on shared DTO | Pure new VO; serializable; nullable AI slot. |
| git-C10 | `BuyerMatchResultBuilder` report blocks | = Plan-C6 (F3) | additive on live builder | why-not / confidence / recommendations; regression-snapshot the batch path. |
| git-C11 | `mapOneDetailed()` + non-residential reconciliation | = Plan-C7 (F4) | additive on live mapper | `mapOne()` untouched; totals reconcile. |
| git-C12 | `LocationDnaEnrichmentGuard` + `dispatchDna` opt-out param | = Plan-C8 (F6) | default-preserving edit to lookup | Import paths byte-for-byte unchanged by default. |
| git-C13 | Grow orchestrator: MLS#/address lookup front-end + emit `MatchReport` + route enrichment through the guard | = Plan-C9 (F1/F2/F5/F6/F9) | grows `MatchCheckOrchestrator` (stance i) | Composes git-C8…C12; still no route. |
| git-C14 | Route + controller + view (`/match-check`) | = Plan-C10 | new user surface, **flag-gated** | First consumer-reachable surface; flag default OFF ⇒ 404. |
| git-C15 | Compliance regression test | = Plan-C11 (F7/F9) | test only | No `raw_json`/PII/PublicRemarks; non-IDX fully blocked. |
| git-C16 | "Better matches" low-score tail | = Plan-C12 (F2) | gated edit to orchestrator + view | Behind `better_matches_enabled` (default OFF) + threshold. |

Dependency spine (unchanged in spirit from the plan's DAG, re-expressed on the as-built base):

```
git-C1..C4 ─┐
git-C5..C7 ─┼─► git-C8 ─► git-C13 ─► git-C14 ─► git-C15
git-C9..C11 ┘            ▲              └─► git-C16
git-C12 ────────────────┘
```

---

## 7. Recommended next single implementation slice

**git-C8 — wire `MatchCheckCriteriaLoader` into `MatchCheckOrchestrator::evaluate()`** (close the
`CRITERIA_NOT_LOADED` seam).

Why this one, first:
- It removes the only **half-finished** state in the tree: a committed loader (git-C7) with **no
  caller**. Every later slice — the report model, the orchestrator, the route — assumes the backend
  can produce a real `SCORED` result end-to-end; today it cannot without an externally-injected payload.
- It is the **smallest** coherent increment and is a strict prerequisite under either stance in §5.
- It stays **inert**: while `mls_match_check.enabled` is OFF (default), `prepare()` returns `disabled`
  and `evaluate()` short-circuits before the loader is ever reached. No route/controller/UI added.
- Scope preview (for its own scope doc, next): inject `MatchCheckCriteriaLoader` as a nullable 5th
  constructor arg on `MatchCheckOrchestrator` (preserving the existing 3-/4-arg construction), and in
  `evaluate()` — only in the READY-with-preferred-criteria path and only when no payload was supplied —
  call the loader to obtain one. Update the one existing test whose contract changes
  (`evaluate_visible_with_criteria_but_no_payload_returns_criteria_not_loaded`) to inject a loader, and
  add tests for the new auto-load path. Additive, no new flag, no Matching V2.

**Alternative if you prefer plan-forward richness first:** make **git-C9 (`MatchReport` DTO)** the next
slice instead — it is purely additive (new file, zero edits) and unblocks the eventual UI, but it
leaves the `evaluate()` seam open. My recommendation remains git-C8.

---

## 8. Constraints reaffirmed for all forward slices

- **Additive / inert-first**, each independently revertable, suite green per commit.
- **No enabling** `mls_match_check.enabled` (default OFF); no `.env` change; GA is a separate owner step.
- **No new scoring logic** — F4 is display reconciliation only; scorer weights/caps unchanged.
- **Matching V2 is entirely out of scope** (`app/Services/Dna/Relevance/*`, `config/matching.php`,
  its `matching_v2_*` tables, and its own C6/C6.1/C7).
- Numbering: **git-C<n> only** from here. The bare labels "C5/C6/C7" are retired to avoid the
  plan-vs-git collision this document exists to resolve.

---

*This document changes no production code, tests, or config. It is a numbering/authority
reconciliation for review; it stages nothing and commits nothing on its own.*
