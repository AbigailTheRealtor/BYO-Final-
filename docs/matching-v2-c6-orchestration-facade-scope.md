# Matching V2 — C6: Orchestration Facade + Inspection Command — Scope Proposal

**Status:** Draft for review / approval — **no code yet**
**Date:** 2026-07-06
**Type:** Backend-only, read-only composition. No persistence, no UI/API, no Match Check integration.
**Depends on (merged):** C1 `DnaScoreRepository`, C2 `DnaMatchService`/§F6, C3 discovery Stage A, C4 narrowing/compliance Stage B, C5 55+ remediation.

---

## 0. What C6 delivers

One read-only **facade** that composes the already-built pieces into a single call —
**discover → narrow + compliance → rank/tier → ranked compliant result** — plus a
thin **artisan inspection command** so the owner can run and observe the engine on
real subjects for pre-GA validation. Nothing is persisted; nothing is exposed to
consumers.

```
MatchingV2Service::matchForSubject(type, id)
        │  (flag-gated; inert when off)
        ├─ 1. infer direction from subject type
        ├─ 2. CandidateDiscoveryService::discover(...)   → CandidateSet   (Stage A + B: capped, eligible, 55+-compliant)
        ├─ 3. DnaMatchService::match{Demand|Listing}(...) → RankedMatchSet (§F6 aggregate → classify → rank)
        └─ 4. assemble OrchestratedMatchResult           (type-preserving, best-first, with discovery metadata)
```

---

## 1. The `listing_type`-preservation problem (the design crux)

`RankedMatch.counterpartId` is an opaque `int|string` (`RankedMatch.php:16`); the matcher
sorts by it but never interprets it (`BatchRelevanceMatcher.php:105`). `DnaMatchService`
currently sets it to the bare `(int) listing_id` (`DnaMatchService.php:100`). But
discovery returns **mixed counterpart types** — for a demand subject the counterparts
are property listings of type `seller_agent` **and** `landlord_agent`, whose primary-key
ids **collide** (a `seller_agent` #5 and a `landlord_agent` #5 both exist). So a ranked
result keyed only by `listing_id` is ambiguous. C6 must preserve `listing_type` through
ranking.

### Recommended approach — **Design T: thread type through (additive)** — OD-1

Carry an optional counterpart `type` alongside `id`/`scores`, all the way into
`RankedMatch`, strictly additively and backward-compatibly:

- `RankedMatch` gains an optional third ctor param `?string $counterpartType = null`
  (existing `new RankedMatch($id, $result)` still valid), a `counterpartType()` accessor,
  and `toArray()` includes `counterpart_type` **only when non-null** (so existing §F6
  tests that pass no type see an unchanged array shape).
- `BatchRelevanceMatcher::counterpart()` reads an optional `$counterpart['type'] ?? null`
  and `rank()` passes it into `new RankedMatch($id, $result, $type)`; the sort gains a
  final tie-break by type so ordering is total even when ids collide across types.
  Counterparts without a `type` behave exactly as today.
- `DnaMatchService::counterparts()` adds `'type' => $type` to each counterpart. Existing
  C2 tests assert `counterpartId === <int>` (unchanged) and don't assert type, so they
  stay green; the ranked result now additionally carries the type.

**Why Design T over the alternative:** it yields a **self-describing** result (every match
knows its own type) that persistence/UI will need regardless, and keeps a **single global
ranking pass** (correct ordering, no duplicated sort). It is strictly additive — all
C1–C5 tests remain green.

**Alternative considered — Design G (facade group-by-type, zero value-object change):**
the facade groups candidates by `listing_type`, calls `DnaMatchService` once per type
(so each returned `RankedMatchSet` is known-type by construction), then merges + re-sorts
and re-aggregates tier counts. Pros: touches none of the §F6 value objects. Cons: pushes
ordering + tier-count aggregation into the facade (duplicated logic, more room for
ordering bugs), and calls the matcher once per type. **Recommend Design T**; Design G is
the fallback if the owner prefers to freeze the §F6 value objects entirely (OD-1).

---

## 2. Facade contract

**File:** `app/Services/Dna/Relevance/MatchingV2Service.php`

```php
class MatchingV2Service
{
    public function __construct(
        private readonly CandidateDiscoveryService $discovery,
        private readonly DnaMatchService $matcher,
    ) {}

    public function isEnabled(): bool; // config('matching.v2_enabled')

    /**
     * Compose discovery + narrowing + ranking for one subject. Direction is
     * inferred from the subject's listing_type. Returns an inert empty result
     * (no DB reads) when Matching V2 is disabled or the subject type is unsupported.
     */
    public function matchForSubject(
        string $listingType,
        int $listingId,
        ?int $cap = null,          // discovery/candidate-pool cap; defaults to config
    ): OrchestratedMatchResult;
}
```

- **Direction inference (OD-2):** `seller_agent`/`landlord_agent` (property subject) →
  `ListingToDemands`; `buyer_agent`/`tenant_agent` (demand subject) → `DemandToListings`.
  Unsupported/unknown type → inert empty result (safe; no throw).
- No AppServiceProvider binding needed — both constructor deps autowire.
- **No separate result-limit in C6 (OD-5):** the facade returns *all* determined matches
  (≤ discovery cap); callers/command take top-N via the existing `RankedMatchSet::top()`
  semantics exposed on the result. A dedicated result limit is deferred.

**File:** `app/Services/Dna/Relevance/OrchestratedMatchResult.php` — a new immutable VO:

```php
final class OrchestratedMatchResult
{
    // subject identity + inferred direction
    public function subjectType(): string;
    public function subjectId(): int;
    public function direction(): MatchDirection;

    /** Enriched, best-first matches: each {listing_type, listing_id, tier, value}. */
    public function matches(): array;          // array<int,array{listing_type,listing_id,tier,value}>
    public function top(int $n): array;

    public function determinedCount(): int;    // ranked matches
    public function undeterminedCount(): int;  // pairings the kernel could not tier
    public function tierCounts(): array;        // privacy-safe, zero-filled 4 tiers

    // discovery metadata
    public function candidatesConsidered(): int;      // count fed to the matcher
    public function candidatePoolTruncated(): bool;   // CandidateSet::wasTruncated()

    public static function empty(string $type, int $id, MatchDirection $dir): self;
    public function isEmpty(): bool;
    public function toArray(): array;          // stable machine shape for --json / future persistence
}
```

The facade maps each `RankedMatch` → `{listing_type: counterpartType, listing_id:
counterpartId, tier, value}` (Design T), preserving best-first order from the single
ranking pass.

---

## 3. Additive changes to `RankedMatch` / `RankedMatchSet`

- **`RankedMatch`** (additive): optional `?string $counterpartType = null` ctor param;
  `counterpartType()` accessor; `toArray()` adds `counterpart_type` only when non-null.
- **`RankedMatchSet`**: **no change.** It already exposes `matches`, `determinedCount()`,
  `undeterminedCount`, `tierCounts()`, `top()`, `toArray()` — all the facade needs. (Under
  Design G it would need a merge helper; under the recommended Design T it is untouched.)
- **`BatchRelevanceMatcher`**: additive — carry optional `type` through `counterpart()`
  and `rank()`; add type as the final sort tie-break.
- **`DnaMatchService`**: additive — include `'type'` in each counterpart.

All four changes are backward-compatible; the only behavioral delta is that ranked
matches now *also* carry their type.

---

## 4. Inspection command

**File:** `app/Console/Commands/MatchingV2Preview.php`
**Signature:** `matching:preview {listingType} {listingId} {--cap=} {--limit=20} {--json} {--respect-flag}`

- **Purpose:** run the composed pipeline for one subject and print the ranked, compliant
  result for pre-GA validation — the sanctioned way to observe the engine while it is
  globally disabled.
- **Flag behavior (OD-3):** by default the command **force-enables Matching V2 in-process**
  (`config(['matching.v2_enabled' => true])` for the duration of the run) and prints a
  banner stating the environment's real flag value and that the preview runs the engine
  regardless (read-only). `--respect-flag` opts into honoring the global flag (so it
  returns the inert empty result when off). Rationale: a CLI preview whose entire reason
  to exist is validation-before-enablement must be able to run while the prod flag is off;
  it performs only reads.
- **Default (human) output:** a banner (subject, inferred direction, flag state), a table
  of the top `--limit` matches — `rank | tier | listing_type | listing_id | value` — and a
  summary line: candidates considered, determined/undetermined counts, per-tier counts,
  and a `TRUNCATED` marker when the candidate pool was capped.
- **`--json`:** emits `OrchestratedMatchResult::toArray()` (machine/QA use); suppresses the
  human table.
- **`--cap=N`:** override the discovery cap. **`--limit=N`:** display cap only (default 20).
- **Exit codes:** `0` on success (including "0 matches"); `1` on unsupported/unknown
  subject type or subject not found (with a clear message).

---

## 5. Feature-flag behavior

- **Master gate unchanged:** `MATCHING_V2_ENABLED` (default off). `matchForSubject()`
  returns `OrchestratedMatchResult::empty(...)` with **zero DB reads** when off — the same
  inert contract as `DnaMatchService`/`CandidateDiscoveryService` (short-circuits *before*
  discovery).
- **Sub-config untouched:** discovery `cap`, `hard_filters_enabled`, `senior_unknown_policy`
  continue to govern the composed Stage A/B exactly as in C3/C4.
- **Inspection command** force-enables in-process by default (OD-3); production reachability
  of the facade remains gated by the real flag.
- No new env var. No new config keys.

---

## 6. Empty / no-score behavior

- **Flag off** → inert empty result, no reads.
- **Unsupported subject type** → inert empty result (facade) / exit 1 (command).
- **No candidates discovered** (nobody has counterpart-side DNA, or all narrowed out by
  eligibility/compliance) → short-circuit: the matcher is **not** called; empty result with
  `candidatesConsidered = 0`.
- **Subject has no DNA scores** → the matcher runs but every pairing is undetermined
  (empty subject scores can't be aggregated); result has `determinedCount = 0`,
  `undeterminedCount = candidatesConsidered`. This is a valid, informative outcome, not an
  error — surfaced distinctly from "no candidates."
- **Some candidates lack scores** → those pairings are undetermined (existing §F6 behavior),
  counted in `undeterminedCount`; the rest rank normally.

---

## 7. Cap / truncation behavior

- The **candidate-pool cap** is the discovery cap (C3/C4: over-fetch → narrow → trim to
  `cap`, default 200). The facade passes `cap` through to discovery.
- `OrchestratedMatchResult::candidatePoolTruncated()` surfaces `CandidateSet::wasTruncated()`
  — true when the eligible/compliant pool exceeded the cap and the ranked set is therefore
  **not** exhaustive. The command prints a `TRUNCATED` marker so a capped result is never
  read as the whole market.
- Determined matches ≤ candidates considered ≤ `cap`. No separate ranking cap in C6 (OD-5).

---

## 8. Read-only guarantees

- The facade calls only read-only collaborators (`CandidateDiscoveryService`,
  `DnaMatchService`) which read `dna_scores` + auction meta/lifecycle/geo via `SELECT`s.
- No writes, no `updateOrCreate`, no persistence of results, no generation triggers, no
  migration, no schema change.
- A feature test asserts row counts across `dna_scores` and the auction/meta/geo tables are
  unchanged after `matchForSubject()` and after `matching:preview`.
- The inspection command performs only reads even when it force-enables the flag in-process.

---

## 9. Rollback strategy

- **Flagged-off by default:** in production `MATCHING_V2_ENABLED=false`, so the facade is
  inert and the command is the only reachable surface (and it only reads). Zero production
  behavior change from merging C6.
- **Clean `git revert`:** all C6 code is additive — two new services, one new command, and
  backward-compatible optional fields on `RankedMatch`/matcher/`DnaMatchService`. No schema,
  no data, no migration, nothing persisted, so revert is a pure code removal with no
  cleanup. Because `counterpart_type` is never persisted in C6, reverting cannot orphan
  data (a consideration only for a later persistence slice).
- **Kill switch:** setting `MATCHING_V2_ENABLED=false` (the default) fully disables the
  facade without a revert.

---

## 10. Files to add / modify

### Add
| Path | Purpose |
|---|---|
| `app/Services/Dna/Relevance/MatchingV2Service.php` | The orchestration facade. |
| `app/Services/Dna/Relevance/OrchestratedMatchResult.php` | Type-preserving result VO + discovery metadata. |
| `app/Console/Commands/MatchingV2Preview.php` | `matching:preview` inspection command. |
| `tests/Unit/Dna/MatchingV2ServiceTest.php` | Facade unit tests (fakes; flag/direction/empty/compose). |
| `tests/Unit/Dna/RankedMatchTypeTest.php` | Additive `counterpartType` + type tie-break + toArray-omission. |
| `tests/Feature/Dna/MatchingV2OrchestrationTest.php` | End-to-end incl. mixed-type preservation + compliance flow-through + read-only. |
| `tests/Feature/Dna/MatchingV2PreviewCommandTest.php` | Command output, `--json`, force-enable banner, exit codes. |

### Modify (all additive / backward-compatible)
| Path | Change | Risk |
|---|---|---|
| `app/Services/Dna/Relevance/RankedMatch.php` | optional `?string $counterpartType`, accessor, null-omitting `toArray`. | Low |
| `app/Services/Dna/Relevance/BatchRelevanceMatcher.php` | carry optional `type` through; type tie-break in sort. | Low |
| `app/Services/Dna/Relevance/DnaMatchService.php` | include `'type'` in counterparts. | Low |
| (tests) `DnaMatchServiceTest`, `BatchRelevanceMatcherTest` | possibly add type assertions; verify no strict `toArray` shape breakage. | Low |

**Not modified:** `RankedMatchSet`, `DnaScoreRepository`, `CandidateDiscoveryService`, the
narrowers/resolver, config, generators, migrations. No `AppServiceProvider` binding needed.

---

## 11. Test plan

**Unit — `MatchingV2ServiceTest`** (fakes for discovery + matcher; no DB):
- Flag off → empty inert result; discovery and matcher **not** called.
- Direction inference: `buyer_agent`/`tenant_agent` → `matchDemandAgainstListings`;
  `seller_agent`/`landlord_agent` → `matchListingAgainstDemands` (assert which matcher
  method is invoked and with the discovered candidates).
- Empty candidate set → matcher not called; empty result, `candidatesConsidered = 0`.
- Truncation surfaced from the discovery `CandidateSet` into the result.
- Unsupported subject type → inert empty result.
- Result maps each ranked match to `{listing_type, listing_id, tier, value}` preserving order.

**Unit — `RankedMatchTypeTest`**:
- Matcher with `type` on counterparts → `RankedMatch.counterpartType()` set; `toArray()`
  includes `counterpart_type`.
- Without `type` → `counterpartType()` null; `toArray()` omits the key (back-compat).
- Colliding ids across two types produce a stable total order (type tie-break).

**Feature — `MatchingV2OrchestrationTest`** (real container, seeded):
- Seed `dna_scores` + eligible offer-listing auctions across **both** `seller_agent` and
  `landlord_agent` with colliding ids; run `matchForSubject('buyer_agent', …)`; assert each
  ranked match carries the correct `listing_type` (the core fix) and best-first order.
- Compliance flow-through: a senior-restricted candidate is absent for a non-eligible
  subject (Stage B still applies through the facade).
- Subject with no DNA → `determinedCount = 0`, `undeterminedCount = candidatesConsidered`.
- Read-only: `dna_scores`/auction/meta/geo row counts unchanged.

**Feature — `MatchingV2PreviewCommandTest`**:
- Runs for a seeded subject: human table contains expected rows; summary shows counts +
  tier counts; `--json` emits valid `toArray()`; force-enable banner present; `--respect-flag`
  returns empty when flag off; unsupported type → exit 1.

SQLite/Postgres per the existing `tests/*/Dna` conventions (`DatabaseTransactions`, high ids
to avoid PK collisions with seed data).

---

## 12. Out of scope

- Result **persistence / caching** (next slice — first write surface, its own governance review).
- **Consumer UI/API** and **Match Check** integration (later; needs sign-off + likely caching).
- Per-dimension **explanation surfacing** to consumers (safe post-C5, but a separate slice).
- Marketplace-scale batch recompute, pagination, or a dedicated ranking cap.
- Enabling `MATCHING_V2_ENABLED` or DNA generation in production.

---

## 13. Owner decisions

| ID | Decision | Options | Recommendation |
|---|---|---|---|
| **OD-1** | `listing_type` preservation. | **T)** thread additive `type` through `RankedMatch`/matcher/`DnaMatchService`. **G)** facade group-by-type, no value-object change. | **T** — self-describing result, single ranking pass, strictly additive. |
| **OD-2** | Direction. | infer from subject type · explicit param. | **Infer** — direction is fully determined by which side the subject sits on; less error-prone. |
| **OD-3** | Preview command vs the flag. | force-enable in-process by default (`--respect-flag` to honor) · always honor the flag. | **Force-enable by default** — the command exists to validate before enablement; it only reads. |
| **OD-4** | Facade name. | `MatchingV2Service` · `DnaMatchOrchestrator`. | **`MatchingV2Service`.** |
| **OD-5** | Result limit. | none in C6 (return all determined) · add a ranking top-N. | **None in C6**; command `--limit` is display-only; ranking top-N deferred. |
| **R-1** | Touching §F6 value objects even additively. | — | Mitigated: optional param + null-omitting `toArray`; all C1–C5 tests stay green (verified in the test plan). |

---

## 14. Summary of the ask

Approve **OD-1 (Design T)**, **OD-2 (infer direction)**, **OD-3 (force-enable preview)**,
**OD-4 (`MatchingV2Service`)**, **OD-5 (no result limit)**. On approval C6 ships as one
isolated backend-only commit: the facade + result VO + `matching:preview` command, the
additive `counterpartType` threading, and the §11 tests — read-only, flag-gated, no
persistence, no UI. **No code until approved.**
