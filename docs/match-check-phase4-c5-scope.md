# Match Check — Phase 4 · Wave 2 / C5 Scope & C1–C4 Reconstruction

**Status:** Scope / reconstruction doc — **no implementation changed by this doc commit.**
**Purpose:** C1–C4 were only ever captured in commit messages. This doc reconstructs
that sequence from `git log`, records the existing files/classes, and pins down what
**C5** is. **C5 is now committed** (`e755ccbc9`) — see §4–§5 — including its test.
No production code, test, or config is changed by this document (docs-only).

> ⚠️ **Naming collision:** there are **two independent `C<n>` series** in this repo.
> This doc concerns the **Match Check** series (`feat(match-check): … Phase 4 Wave N/CN`).
> It is **not** the **Matching V2** series (`feat(matching): … §MatchingV2 C6/C6.1`),
> which numbers its own C6/C6.1/C7. Do not conflate the two.

---

## 1. Current HEAD

```
e755ccbc9  feat(match-check): Phase 4 Wave 2/C5 — MatchCheckOrchestrator entry point (inert)
```

Full SHA: `e755ccbc91bc240fe47490a3afd1acbc42f2f6b1`.

This HEAD **is the C5 commit** (see §4–§5). The Match Check series now runs C1 → C5 in
git history. (Intervening non-series commits exist: Matching V2 `428e61378`/`9378f6f3e`
belong to a **separate** `C<n>` track — see the collision note above.)

---

## 2. Completed C1–C4 commits (reconstructed from git log)

| C# | Wave | Commit | Feature | What it added |
|----|------|--------|---------|---------------|
| C1 | 0 | `30b1bbca1` | master flag + gate | Default-OFF master flag `config/mls_match_check.php` (`enabled`, plus forward-declared inert tunables for C8/C12) and `CheckMatchCheckEnabled` 404 middleware (mirrors `CheckAgentAiV2Enabled`), registered as the `match-check` route-middleware alias. Unwired to any route; entirely inert. |
| C2 | 1 | `957e0ddc9` | F9 — visibility | `ListingVisibilityGate` + `VisibilityDecision`. Single consumer-facing IDX visibility policy; IDX semantics **mirror `BuyerMatchService::match()` exactly** (only explicit falsey `IDXParticipationYN` blocks; absent/malformed fails open). Backend-only, inert. |
| C3 | 1 | `da091f84d` | F5 — intent | `CriteriaIntentDetector`. Auto-detects Sale (→ Buyer criteria) vs Rent (→ Tenant criteria) from the RESO `PropertyType`. Bare `Residential`/`Commercial` are tenure-ambiguous → returns `null` (caller falls back, never scores with the wrong engine). API: `detectFromType(?string)` + `detectFromModel(BridgeProperty)`. Backend-only, inert. |
| C4 | 1 | `081a351c4` | F5 — criteria pick | `CriteriaListingResolver::resolvePreferred(User, ?intent)`. The single auto-select record for Match Check: agents get `null` (must choose explicitly); consumers get the newest accessible record, side-filtered by intent; `null` when none matches (caller shows create-criteria empty state). Additive; reuses `resolveAccessible()`. 6 unit tests. |

Each C1–C4 commit is isolated, additive, and inert (no route/UI/scoring wired).

---

## 3. Existing files/classes from C1–C4 (verified in tree)

**Committed (tracked):**

- `config/mls_match_check.php` — master flag + inert C8 (`dna_*`, F6) and C12 (`better_matches_*`, F2) tunables. *(C1)*
- `app/Http/Middleware/CheckMatchCheckEnabled.php` — 404 gate mirroring the flag. *(C1)*
- `app/Http/Kernel.php` — `'match-check'` middleware alias (line 84). *(C1)*
- `app/Services/Stellar/MatchCheck/ListingVisibilityGate.php` — `decide(BridgeProperty): VisibilityDecision`. *(C2)*
- `app/Services/Stellar/MatchCheck/VisibilityDecision.php` — `visible` + machine `reason`. *(C2)*
- `app/Services/Stellar/MatchCheck/CriteriaIntentDetector.php` — `detectFromType()` / `detectFromModel()`. *(C3)*
- `app/Services/Stellar/CriteriaListingResolver.php` — `resolvePreferred()` added (rest predates the series). *(C4)*

**Tests (tracked):**

- `tests/Unit/MatchCheck/CheckMatchCheckEnabledTest.php` *(C1)*
- `tests/Unit/Stellar/MatchCheck/ListingVisibilityGateTest.php` *(C2)*
- `tests/Unit/Stellar/MatchCheck/CriteriaIntentDetectorTest.php` *(C3)*
- `tests/Unit/Stellar/CriteriaListingResolverPreferredTest.php` *(C4)*

Wave map from the C1 config (source of truth for downstream numbering):
**Wave 4 / C8** = Location DNA enrichment throttle (F6); **Wave 7 / C12** = "better matches"
low-score fallback (F2). C5–C7 sit between C4 and C8.

---

## 4. What C5 should be — and its actual current state

**C5 (correct, plan-aligned definition):** a single read-only **orchestration entry point**
that composes the already-built Wave 0/1 pieces — master flag → `ListingVisibilityGate` (F9)
→ `CriteriaIntentDetector` (F5) → `resolvePreferred` (F5) — into one decision for a
`(listing, user)` pair, **without** scoring, rendering, external enrichment, or persistence,
and **without** wiring to any route/controller/UI. It stays inert while the master flag is OFF.

This is the natural and only "next safe" step after C4: C2–C4 built the independent inert
inputs; C5 assembles them. It introduces no new external dependency and no gated behavior.

> **C5 is committed** as `e755ccbc9` — *feat(match-check): Phase 4 Wave 2/C5 —
> MatchCheckOrchestrator entry point (inert)* (2026-07-06 03:58Z, 3 files / +354).
> Its docblocks self-label as *"Phase 4 · Wave 2 / C5"*:
>
> - `app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php` — `prepare(BridgeProperty, User): MatchCheckPreparation`. Order: flag OFF → `disabled()`; not IDX-visible → `blocked()` (no criteria read); else detect intent + resolve preferred → `ready()`. Constructor-injects the three C2/C3/C4 services. Read-only.
> - `app/Services/Stellar/MatchCheck/MatchCheckPreparation.php` — immutable result VO with three terminal states `DISABLED` / `BLOCKED` / `READY`; named constructors `disabled()` / `blocked(VisibilityDecision)` / `ready(VisibilityDecision, ?intent, ?preferredCriteria)`; predicates `isDisabled/isBlocked/isReady/hasPreferredCriteria`.
> - `tests/Unit/Stellar/MatchCheck/MatchCheckOrchestratorTest.php` — unit coverage for the composition (+176 lines).
>
> These match the plan-aligned definition above **exactly**. C5 is therefore **done at the
> service layer**: committed, **tested**, and **inert** (short-circuits to `disabled()` while
> the default-OFF master flag holds), wired to no route/controller/UI. It is **not** consumer-
> exposed. The C5 files were authored/committed by a separate shell; **this doc neither touched
> nor committed them.**

---

## 5. C5 files (committed at `e755ccbc9`)

| File | State | Note |
|------|-------|------|
| `app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php` | **committed** | The C5 composition entry point. |
| `app/Services/Stellar/MatchCheck/MatchCheckPreparation.php` | **committed** | Immutable result VO. |
| `tests/Unit/Stellar/MatchCheck/MatchCheckOrchestratorTest.php` | **committed** | Unit coverage for the composition (+176). |

Committed path-scoped as `feat(match-check): Phase 4 Wave 2/C5 — MatchCheckOrchestrator
entry point (inert)`. Nothing further is required at the service layer; the remaining
Match Check work (route/controller/UI, scoring, enrichment) is later-wave and out of scope
(see §7).

---

## 6. Tests for C5 (committed with `e755ccbc9`)

`tests/Unit/Stellar/MatchCheck/MatchCheckOrchestratorTest.php` exercises
`MatchCheckOrchestrator::prepare()` (injected services faked/mocked; assert the returned
`MatchCheckPreparation` state — no DB, no scoring). The behaviors it must/does cover:

1. **Flag OFF (default)** → `DISABLED`; visibility gate / resolver **never called** (inert guarantee).
2. **Flag ON + not IDX-visible** → `BLOCKED`; `reason` propagated from `VisibilityDecision`; criteria resolver **never called** (no DB read for a hidden listing).
3. **Flag ON + visible + definite sale intent** → `READY`, `intent='buyer'`, preferred criteria passed through.
4. **Flag ON + visible + definite rent intent** → `READY`, `intent='tenant'`.
5. **Flag ON + visible + ambiguous type** → `READY`, `intent=null`, resolver called with `null` intent.
6. **Flag ON + visible + resolver returns null** (no record / agent) → `READY`, `hasPreferredCriteria() === false`.
7. **Ordering** — visibility is evaluated **before** intent/criteria (assert call order / that a blocked listing triggers no resolver call).

(C2/C3/C4 already carry their own unit coverage; C5 tests exercise only the composition.)

---

## 7. Out of scope for C5

- Any **route, controller, middleware wiring, or Blade/UI** for `/match-check` (later wave).
- Any **scoring / match execution** (Matching V2 or `BuyerMatchService::match()` invocation).
- Any **Location DNA enrichment** or its throttle (F6 — **Wave 4 / C8**).
- The **"better matches" low-score fallback** (F2 — **Wave 7 / C12**).
- **Enabling** the master flag, or any `.env` change.
- Persistence, caching, or writes of any kind.
- The Matching V2 series (C6/C6.1/C7) — unrelated track.

---

## 8. Risk / rollback notes

- **Blast radius: none while inert.** C5 is additive and not referenced by any route, job, or
  service; with the master flag OFF (default) `prepare()` returns `disabled()` before touching
  anything. Rollback = `git revert e755ccbc9` (removes the two services + their test cleanly).
- **Single-writer note (resolved):** the C5 code was authored/committed by a **separate shell**
  as `e755ccbc9`; the shared index was confirmed clean and HEAD steady before this doc commit.
- **No behavior change to existing consumers:** C5 does not alter `BuyerMatchService::match()`;
  it only *reads through* the C2 gate whose semantics already mirror `match()`.
- **Naming-collision risk:** committing C5 near a Matching V2 `C<n>` commit can confuse history —
  keep the `feat(match-check): … Wave 2/C5` prefix to disambiguate.

---

## 9. C5 status and what is / isn't gated

- **Service layer: DONE.** C5 is committed (`e755ccbc9`), tested, and inert — plan-aligned,
  additive, gated by no external decision (unlike Matching V2 **C7**, which remains blocked
  pending validation-runbook evidence — a separate track).
- **Coordination gate: cleared.** The concurrent C5 authorship was resolved by letting the
  other shell land its commit; index confirmed clean and HEAD steady at `e755ccbc9` before
  this doc was committed.
- **Still NOT consumer-exposed / still owner-gated for exposure:** C5 wires nothing to a route,
  controller, or UI, and the master flag `mls_match_check.enabled` remains **default OFF**.
  Turning Match Check on for consumers is a later wave and an explicit **owner decision** — do
  not enable the flag as part of C5.

---

*This document changes no production code, tests, or config. It is committed path-scoped as the
only file in its commit.*
