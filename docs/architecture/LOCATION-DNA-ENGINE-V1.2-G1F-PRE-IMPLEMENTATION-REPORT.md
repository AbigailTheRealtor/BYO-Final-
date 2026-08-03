# G1f — Writer Consolidation · Pre-Implementation Report

**Gate:** G1f (per §12 of the [G1 Pre-Implementation Report](./LOCATION-DNA-ENGINE-V1.2-G1-PRE-IMPLEMENTATION-REPORT.md))
**Type:** pre-implementation audit, decision package and implementation record — **G1f-1, G1f-2 and G1f-3 implemented**
**Status:** **D-G1F-1 … D-G1F-5 APPROVED. ALL FOUR CHARACTERISATION GAPS CLOSED. G1f-1 (`BuyerAgentAuction`) IMPLEMENTED — `5c38fc574`. G1f-2 (`TenantAgentAuction`) IMPLEMENTED — `d3123fb94`. G1f-3 (Buyer Offer create + edit) IMPLEMENTED — `a17d4cb14`. G1f-4 AND LATER: NOT STARTED. G1g: NOT STARTED.**
**Governed by:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md) · decisions per the
[G1c Decision Package](./LOCATION-DNA-ENGINE-V1.2-G1C-DECISION-PACKAGE.md) (D-G1-1 … D-G1-7, all **APPROVED** 2026-07-30)
**Audited at:** `994270a04d9ee83d50b9fe4ebe023ee63603244e`
**Characterisation delivered:** `b91d5e7a215ea781043d1fd52b4a70fe35b08c9f` — four suites, 35 tests, tests only
**Decisions approved:** 2026-08-02 · **Approval base commit:** `b91d5e7a2`
**G1f-1 implemented:** `5c38fc5745d2b6a7ee63e7c6f0b048e6acc61a69` — `feat(location-dna): migrate BuyerAgentAuction writer`
**G1f-2 implemented:** `d3123fb94d530411a9422c2eef57875b21ec041d` — `feat(location-dna): migrate TenantAgentAuction writer`
**G1f-3 implemented:** `a17d4cb14ffb8669bcae09ad119e00006859d355` — `feat(location-dna): migrate Buyer Offer writers`
**Branch:** `architecture/location-dna-g1-domain-core`
**Audit date:** 2026-08-02

> **Approval of a decision is not authorization to implement it.** Each stage requires its own separate
> authorization, and every stop condition in §23 remains binding. G1f-1 and G1f-2 were each authorized
> individually; G1f-3 has not been.
>
> This document originally decided nothing. It now records five approved owner decisions (§22.2), three
> reaffirmed constraints that are **not** new decisions (§22.4), the closure of all four
> characterisation gaps by executed tests (§20.5), and two executed workflow migrations (§27, §28).
>
> Every line number, count and behaviour below was recomputed from the tree at the audited commit. No
> figure is carried forward from a prior document without rechecking, and where a prior document is
> **wrong or incomplete** this report says so and gives the measured value — including, now, two places
> where **this report itself was wrong** and the characterisation corrected it (§5.2, §6.3).

---

## 1. Executive summary

G1f is described in the G1 report §12 as *"consolidate 5 → 1 across eight workflows, one workflow per
commit."* **That description understates the surface in four material ways, each measured at this commit:**

1. **There are nine canonical write sites, not five implementations.** Four of them live in the two legacy
   form-POST criteria controllers (`BuyerCriteriaAuctionController`, `TenantCriteriaAuctionController`),
   which write `location_dna_preferences` on both create and update. The eight-workflow model does not
   contain them. They are consumer IDs C-10/C-11 in G1b, recorded there as *readers* that also write —
   but no G1 document treats them as part of the consolidation surface. **They are.** (§4, §5)

2. **The correct unmounted-editor behaviour already exists in production — in the two files G1f's model
   excludes.** Both criteria controllers gate the write on a non-empty payload *and* a successful
   `json_decode`, so an absent or unparseable payload results in **no write**. That is precisely D-G1-2
   Option 2-A's approved rule, already shipped. The eight Livewire workflows have no such gate and
   overwrite the authoritative blob with an empty string. **The reference implementation of the behaviour
   G1f must introduce is already in the tree.** (§6, F-G1F-2)

3. **The mirror set is four keys, not three, and one of them is never derived from the blob.** All four
   Tenant-family components write a `zipCodes` mirror sourced from a component property, never from the
   blob's `zip_codes` dimension. It is a standing divergence channel that no G1 document names and no test
   covers. (§6, F-G1F-5)

4. **Consolidation cannot be "one workflow per commit" for the Hire family.** All four Hire components
   write `cities` / `counties` / `state` from their own component properties **and then** call
   `saveSearchAreas()`, which rewrites the same three keys from the blob. Correctness today rests entirely
   on statement ordering. In the two Hire Tenant components the trait call is additionally gated on
   `user_type ∈ {buyer, tenant}` — so for a seller or landlord record **the blob is never written at all**
   and the component's own mirror writes stand unopposed. (§6, F-G1F-3, F-G1F-4)

**On transactions and failure**, the picture is worse than "no transaction". Five of the nine canonical
writes sit inside a database transaction, three sit outside one, and one — `TenantCriteriaAuctionController`'s
update path — has its transaction **commented out** in the source. In `BuyerOfferListingEdit` the mirror
write and the blob write are separated by **315 intervening `saveMeta` calls across 400 lines with no
transaction at all**, so any failure in that window persists a mirror that disagrees with the blob. (§7)

**On sequencing**, the conclusion is firm and slightly counter-intuitive: **the first workflow to migrate
should be `BuyerAgentAuction` (Hire Buyer create)** — not the Tenant Offer pair whose semantics are already
correct, and not the trait. Reasons in §19.

**On prerequisites**, the report's central finding is that **provenance persistence is NOT required before
writer consolidation, provided G1f is constrained to a specific shape** — a writer that persists only
dimensions carried by an explicit command, performs no legacy repair, and leaves S5 recovery load-time and
in-memory. That constraint is not optional; it is what makes the absence of a provenance column safe. If
G1f instead hydrates-then-rewrites the whole document, it silently promotes inherited values to authored on
the first save of every legacy record, which §5.4 rule 5 and D-G1-6 both forbid, **irreversibly and with no
way to detect it afterwards**. (§10)

**On authorization**, §23 recommends a deliberately narrow G1f-1 that creates `LocationDnaPersistenceService`
and migrates exactly one workflow, and **withholds** authorization for the remaining seven pending the
characterisation work named in §20.

### Findings index

| # | Finding | Section |
|---|---|---|
| F-G1F-1 | Nine canonical write sites across seven files; four are outside the eight-workflow model | §5 |
| F-G1F-2 | The approved unmounted-editor rule is already implemented in the two criteria controllers | §6.4 |
| F-G1F-3 | All four Hire components double-write the mirrors; correctness rests on statement ordering | §6.2 |
| F-G1F-4 | Hire Tenant blob writes are gated on `user_type`; seller/landlord records never get one | §6.2 |
| F-G1F-5 | `zipCodes` is a fourth mirror key, never derived from the blob | §6.3 |
| F-G1F-6 | The `state` mirror has incompatible encodings — **corrected by characterisation to three keys**, one writer emitting two per save | §6.3 |
| F-G1F-7 | Two edit flows split blob and mirror writes by 400–628 lines | §7.2 |
| F-G1F-8 | One canonical write path has its transaction commented out | §7.1 |
| F-G1F-9 | A second tripwire test will fire in G1f and is absent from the authorized change list | §20.4 |
| F-G1F-10 | **First authored write canonicalizes serialization** — retitled and amended after implementation | §11.3, **§27.7** |
| F-G1F-11 | The five guard sites of `BuyerOfferListingEdit` were never enumerated; now measured | §6.1 |
| F-G1F-12 | Two documents cite divergence line numbers that point at comments, not code | §2.3 |
| F-G1F-13 | ~~An eighth test changes by design in G1f-3~~ — **WITHDRAWN: a false-positive prediction.** The test never reaches the save path | §29.4, **§30.7** |
| F-G1F-14 | **The Buyer Offer components call `hydrateDiscreteLocationFromBlob()` TWICE**, and one call is a pre-validation dependency G1f-3 must not remove | §29.1, §30.4 |
| F-G1F-15 | **A ninth tripwire existed and was on no list** — `SearchAreasWidgetContractTest::test_finding_2b3_all_implementations_write_the_cities_mirror` | §30.8 |
| F-G1F-16 | **The G1f-2 boundary guard's scan never reached nested components** — `glob('**/*.php')` does not recurse in PHP. A test defect, not a production one | §30.9 |

### Status after approval, characterisation and three migrations (2026-08-03)

| Item | Status |
|---|---|
| D-G1F-1 … D-G1F-5 | **ALL APPROVED** — §22.2 |
| Reaffirmed constraints (not decisions) | 3 recorded — §22.4 |
| GAP 1 / GAP 2 / GAP 3 / GAP 4 | **ALL CLOSED** — `b91d5e7a2`, 35 tests — §20.5 |
| **G1f-1 · `BuyerAgentAuction`** | **IMPLEMENTED** — `5c38fc574` — §27 |
| **G1f-2 · `TenantAgentAuction`** | **IMPLEMENTED** — `d3123fb94` — §28 |
| **G1f-3 · Buyer Offer create + edit** | **IMPLEMENTED** — `a17d4cb14` — §30 |
| **G1f-4 and later** | **NOT STARTED** |
| **G1g** | **NOT STARTED** |
| Migrated workflows | **4 of 8** — `BuyerAgentAuction`, `TenantAgentAuction`, `BuyerOfferListing`, `BuyerOfferListingEdit`; the other four write as before |
| Direct canonical writers (§21) | **7 → 5** — first reduction, by G1f-3 — §30.10 |
| Provenance persistence | **Still not required**, under §10.5's eight binding restrictions — §27.6, §28.6, §30.5 |
| Defect boundaries | 4 recorded; **boundary 1 CLOSED by G1f-2** (§28.3). Three remain, none authorized for repair — §22.5 |
| Blockers | **B4 RESOLVED** by G1f-3 (§30.8). B5 and B6 — see §22.1 |
| Production code written | **9 new classes + 4 existing files** — §27.1, §28.1, §30.1 |

---

## 2. Repository verification

### 2.1 State at audit

| Property | Value |
|---|---|
| Workspace | `/home/runner/workspace` |
| Branch | `architecture/location-dna-g1-domain-core` |
| HEAD | `994270a04d9ee83d50b9fe4ebe023ee63603244e` |
| Expected HEAD | `994270a04d9ee83d50b9fe4ebe023ee63603244e` — **match** |
| Merge base with `main` | `10037715adca1daa0b59f381ffb6c23d2e01fbf1` |
| Divergence from `main` | 18 ahead / 38 behind |
| Merge, rebase, cherry-pick or bisect in progress | **none** |
| Tracked files modified by this audit | **none** |
| Production code modified by this audit | **none** |
| Tests modified by this audit | **none** |

### 2.2 Pre-existing workspace artifacts — carried across the branch switch, untouched

Two tracked files carry uncommitted local edits and three files are untracked. All five predate this
increment, all five are unrelated to Location DNA, and **none is staged or committed by this increment**:

| Artifact | State | Disposition |
|---|---|---|
| `.claude/settings.local.json` | modified | left as found; byte-identical between `73f32fe62` and `994270a04`, so the edit carried across the switch |
| `.replit` | modified | left as found; same |
| `First` | untracked, 0 bytes | left as found; absent from the target tree |
| `We` | untracked, 0 bytes | left as found; absent from the target tree |
| `scripts/audit-2b3-cities-mirror.php` | untracked, 276 lines | left as found; absent from the target tree |

**Environment note, recorded because it changes how this branch must be worked.** The
`/home/runner/worktrees/` tree that prior G1 increments used was destroyed by an environment reset before
this session. Six stale worktree registrations were pruned and the branch is now checked out directly in
the primary workspace, which is the only location that is both writable under the current sandbox and
carries an installed `vendor/`. The worktree topology described in the G1 report §13 no longer exists.

### 2.3 Documentation synchronisation

The three governing G1 documents are internally consistent on decisions, gate status and commit lineage.
Two drift items were found, both cosmetic, **neither corrected here**:

- **F-G1F-12.** The G1 report §5.1 and the G1c package cite the Tenant Offer divergences at
  `TenantOfferListing.php:3339` and `TenantOfferListingEdit.php:2494`. At this commit those lines hold the
  `// NOTE: deliberately DIVERGES …` comment; the `array_key_exists()` call itself is at **3345** and
  **2500**. The citation points at the divergence marker rather than the mechanism. Harmless, but a reader
  jumping to the cited line sees a comment and may conclude the code moved.
- The G1c decision package has a Markdown structure defect: the `## D-G1-3` and `## D-G1-4` headings are
  missing, and their titles (`— Concurrency and hash semantics`, `— Withdrawals and clear behaviour`) are
  appended to the end of the preceding *Implementation status* paragraphs at lines 331, 474 and 812. The
  content is complete and correctly ordered; only the headings are lost. Recorded for a future
  documentation increment.

### 2.4 Reconciliation status

| Reconciliation | Status |
|---|---|
| G1a characterisation complete, folded into the G1 report | complete (`08ccdc52c`, `e8bf1b46a`) |
| G1b audit reconciled with resolved unknowns U-G1B-2 / U-G1B-4 | complete (`a0df14fb7`) |
| G1c inert core recorded in the decision package | complete (`33ec2cd02`) |
| G1d inert resolver recorded | complete (`45135af8f`) |
| G1e inert provenance recorded | complete (`994270a04`) |
| Owner decisions D-G1-1 … D-G1-7 | all **APPROVED**, none reopened |

**Not reconciled, and outstanding from the G1 report §16 step 2:** v1.2 §4.2's "4 byte-identical inline
copies" claim and its count of four guard sites, and §4.3 F-C1's consumer count. Those corrections were
required *before* implementation and have not been made. This report adds two further corrections to the
same section (§5, §6.3), so the governing-document edit is now larger than when it was first raised.

---

## 3. Confirmations requested

| Confirmation | Status |
|---|---|
| Worktree clean | **Qualified.** No merge/rebase state; no staged changes; no production or test file modified. Two tracked files carry pre-existing unrelated local edits and three unrelated untracked files are present, all listed in §2.2 and all excluded from this commit. The worktree is **not** byte-clean and this report does not claim it is. |
| No merge | **Confirmed** — no merge performed, none in progress |
| No push | **Confirmed** — no remote operation performed |
| No amend | **Confirmed** — history not rewritten; `994270a04` remains the parent |
| Documentation synchronised | **Confirmed** with the two cosmetic drift items in §2.3 recorded and left uncorrected |
| All reconciliations complete | **Confirmed** for G1a–G1e; the v1.2 §4.2/§4.3 corrections remain outstanding as noted in §2.4 |

---

## 4. Exact inventory of the eight workflows

Measured at this commit. "Guard sites" are the presence-decision points that G1f must convert; "mirror
writes" are the discrete-meta writes that produce the observable outcome.

| # | Workflow | Component | Lines | Mirror source | Blob write | Txn |
|---|---|---|---|---|---|---|
| 1 | Hire Buyer — create | `HireBuyerAgent/BuyerAgentAuction.php` | 2,527 | trait **+ own prop writes** | `HasSearchAreas:120` via `:1908` | none |
| 2 | Hire Buyer — edit | `HireBuyerAgent/BuyerAgentAuctionEdit.php` | 2,271 | trait **+ own prop writes** | `HasSearchAreas:120` via `:1769` | none |
| 3 | Hire Tenant — create | `TenantAgentAuction.php` | 5,299 | trait **+ own prop writes**, **gated** | `HasSearchAreas:120` via `:4291` | none |
| 4 | Hire Tenant — edit | `TenantAgentAuctionEdit.php` | 4,218 | trait **+ own prop writes**, **gated** | `HasSearchAreas:120` via `:3457` | **yes** `3390→4176` |
| 5 | Buyer Offer — create | `OfferListing/Buyer/BuyerOfferListing.php` | 3,081 | inline, defective | `:2466` | none |
| 6 | Buyer Offer — edit | `OfferListing/Buyer/BuyerOfferListingEdit.php` | 2,937 | inline, defective | `:2825` | none |
| 7 | Tenant Offer — create | `OfferListing/Tenant/TenantOfferListing.php` | 5,323 | inline, **correct** | `:4380` | none |
| 8 | Tenant Offer — edit | `OfferListing/Tenant/TenantOfferListingEdit.php` | 4,098 | inline, **correct** | `:3913` | **yes** `3236→3951` |

**Total: ~29,754 lines across eight components.**

### 4.1 Entry points, per workflow

| # | Load entry point | Save entry point | Save invocable in isolation? |
|---|---|---|---|
| 1 | `loadSearchAreas()` @ `:1413` | `saveAllMetadata()` | yes — G1a characterised behaviourally |
| 2 | `loadSearchAreas()` @ `:1319` | `saveAllMetadata()` | yes |
| 3 | `loadSearchAreas()` @ `:3062` | `saveAllMetadata()` | yes |
| 4 | `loadSearchAreas()` @ `:2621` | inside `update()` | **no** — structural assertion only (G1 report §6.2.1) |
| 5 | `loadDraft()` | `saveAllMetadata()` | yes |
| 6 | `loadAuctionData()` | `saveAllMetadata()` | yes |
| 7 | `loadDraft()` | `saveAllMetadata()` | yes |
| 8 | `loadAuctionData()` | inside `update()` | **no** — structural assertion only |

The two non-invocable save paths are a **deliberate, documented test boundary**, not residue (G1 report
§6.2.1). They are nevertheless the two workflows whose blob and mirror writes are furthest apart (§7.2),
which means the boundary and the risk coincide. §19 sequences them last for that reason.

---

## 5. Complete inventory of every Location DNA write site

### 5.1 Canonical writes — the `location_dna_preferences` meta key

**F-G1F-1 — nine sites in seven files.** Every one must be either migrated or explicitly exempted by G1f.
The eight-workflow model in the G1 report §12 covers **five** of them (W1–W5). **W6–W9 are not in any G1
document's consolidation surface.**

| ID | File:line | Serves | Value written | Pre-write guard | Inside a txn |
|---|---|---|---|---|---|
| **W1** | `Concerns/HasSearchAreas.php:120` | workflows 1–4 | `$this->location_dna_preferences_json` | **none** | host-dependent (1 of 4) |
| **W2** | `OfferListing/Buyer/BuyerOfferListing.php:2466` | workflow 5 | same | **none** | no |
| **W3** | `OfferListing/Buyer/BuyerOfferListingEdit.php:2825` | workflow 6 | same | **none** | no |
| **W4** | `OfferListing/Tenant/TenantOfferListing.php:4380` | workflow 7 | same | **none** | no |
| **W5** | `OfferListing/Tenant/TenantOfferListingEdit.php:3913` | workflow 8 | same | **none** | **yes** |
| **W6** | `Controllers/BuyerCriteriaAuctionController.php:234` | Buyer Criteria create (form POST) | `$request->input(...)` | **non-empty + `json_last_error()`** | **yes** |
| **W7** | `Controllers/BuyerCriteriaAuctionController.php:606` | Buyer Criteria update (form POST) | same | **non-empty + `json_last_error()`** | **yes** |
| **W8** | `Controllers/TenantCriteriaAuctionController.php:148` | Tenant Criteria create (form POST) | same | **non-empty + `json_last_error()`** | **yes** |
| **W9** | `Controllers/TenantCriteriaAuctionController.php:444` | Tenant Criteria update (form POST) | same | **non-empty + `json_last_error()`** | **no — commented out** |

### 5.2 Mirror writes — the derived discrete keys

| Component | `cities` | `counties` | `state` | `zipCodes` | Source of truth |
|---|---|---|---|---|---|
| `HasSearchAreas` | `:130` | `:123` | `:126` | — | blob for `cities`; hydrated props for `counties`/`state` |
| `BuyerAgentAuction` | `:1901` | `:1902` | `:1903` | — | **component props**, then overwritten by W1 |
| `BuyerAgentAuctionEdit` | `:1762` | `:1763` | `:1764` | — | same |
| `TenantAgentAuction` | `:4282` | `:4283` | `:4285` | `:4284` | same; `zipCodes` never overwritten |
| `TenantAgentAuctionEdit` | `:3449` | `:3450` | `:3451` | `:3466` | same |
| `BuyerOfferListing` | `:2470` | `:2464` | `:2465` | — | blob for `cities`; hydrated props otherwise |
| `BuyerOfferListingEdit` | `:2828` | `:2424` | `:2425` | — | same — **split, 400 lines apart** |
| `TenantOfferListing` | `:4386` | `:4377` | `:4379` | `:4378` | same |
| `TenantOfferListingEdit` | `:3919` | `:3291` | `:3292` | `:3302` | same — **split, 628 lines apart** |
| `BuyerCriteriaAuctionController` | `:61`, `:433`, `:733` | `:62`, `:434`, `:732` | `:734` **only**; `:63` and `:435` write **`states`** | — | **request input**, never the blob |
| `TenantCriteriaAuctionController` | `:54`, `:352` | `:55`, `:353` | `:56`, `:354` | — | **request input**, never the blob |

**Correction to this table, proven by characterisation (`b91d5e7a2`).** As first written, this row recorded
`:63` and `:435` as `state` writes. **They are `states` — plural.** `BuyerCriteriaAuctionController` writes
the singular `state` key at `:734` **only**, on the update path. Proven by
`G1fLegacyCriteriaControllerCharacterisationTest::test_buyer_criteria_update_emits_both_state_keys_in_different_encodings`,
which stores a record through the real controller and reads back **both** keys: `states` JSON-encoded and
`state` raw, from one save. The same test establishes that `updateAuction` writes `cities` and `counties`
twice — at `:433`/`:434` and again at `:733`/`:732` — a controller-level double-write directly analogous to
F-G1F-3's Livewire one. See §17.5 for the approved disposition of the plural key.

**Consequence.** The mirror is written from **three different sources** across the codebase — the blob, a
component property, and raw request input — and no writer reconciles them. G1f's mirror projection contract
(§17) must decide which of the three survives.

### 5.3 Non-writers, confirmed

`BackfillLocationSnapshots`, `AcceptedBidSummaryService`, `BuyerAcceptedBidSummaryService`,
`ComputeCompatibilityScore`, the five Stellar loaders, `LocationMatchAuctionExtractor`,
`PublicGeometryProjection`, `LocationDnaSummaryService` and every Blade view **read only**. The only
non-production writer is `database/seeders/LocationDnaTestSeeder.php`. No queued job, observer, listener or
console command writes the canonical key.

---

## 6. Cross-workflow semantic comparison

### 6.1 Presence-guard sites — fifteen, across three defective implementations

The G1 report §5.2 enumerates the trait's five. **The two Buyer Offer copies each carry five more, and the
Edit copy's have never been enumerated in any document (F-G1F-11).** Measured here:

| Guard role | `HasSearchAreas` | `BuyerOfferListing` | `BuyerOfferListingEdit` |
|---|---|---|---|
| load · `cities` presence | `:48` | `:1940` | **`:1864`** |
| load · `state` prefill | `:71` | `:1961` | **`:1888`** |
| load · `counties` prefill | `:77` | `:1964` | **`:1891`** |
| hydrate · `state` write-back | `:100` | `:2434` | **`:2394`** |
| hydrate · `counties` write-back | `:103` | `:2437` | **`:2397`** |

All fifteen use `empty()` or the `trim(…) !== ''` spelling. **Not one uses `array_key_exists()`.**

The two correct implementations use `array_key_exists('cities', …)` at `TenantOfferListing.php:3345` and
`TenantOfferListingEdit.php:2500` — and **only for `cities`**. Their `state` and `counties` prefill guards
(`:3364`/`:3367` and equivalents) still use `empty()`, and their `hydrateDiscreteLocationFromBlob()` copies
are byte-equivalent to the Buyer Offer ones. **The Tenant Offer pair is correct for exactly one dimension
out of nine.** The G1 report's framing of them as "the correct implementations" is true only in the narrow
sense D-G1-4 uses; G1f must not read it as "these two are done."

### 6.2 The Hire family — a structure no G1 document records

**F-G1F-3.** Every Hire component writes the mirror keys twice per save:

```
BuyerAgentAuction::saveAllMetadata()
  :1901  saveMeta('cities',   json_encode($this->cities))      ← component property
  :1902  saveMeta('counties', json_encode($this->counties))    ← component property
  :1903  saveMeta('state',    $this->state)                    ← component property
  :1908  $this->saveSearchAreas($auction)
           → HasSearchAreas:118  hydrateDiscreteLocationFromBlob()
           → HasSearchAreas:120  saveMeta('location_dna_preferences', …)
           → HasSearchAreas:123  saveMeta('counties', …)       ← OVERWRITES :1902
           → HasSearchAreas:126  saveMeta('state', …)          ← OVERWRITES :1903
           → HasSearchAreas:130  saveMeta('cities', …)         ← OVERWRITES :1901
```

The source comment states the intent plainly — *"re-mirrors the discrete cities/counties/state written just
above from the blob"* — so this is deliberate. **But the correctness of all four Hire workflows rests
entirely on statement ordering inside a 2,300–5,300-line method, with no test asserting it.** A G1f change
that moves the trait call, or that makes the persistence service run at a different point in the save,
silently reverts four workflows to component-property semantics. No existing test would fail.

**F-G1F-4 — the gate.** In both Hire Tenant components the trait call is conditional:

```php
// TenantAgentAuction.php:4290        TenantAgentAuctionEdit.php:3456
if (in_array($this->user_type, ['buyer', 'tenant'])) {
    $this->saveSearchAreas($auction);
    $this->saveImportantPlaces($auction);
}
```

`saveSearchAreas()` is the **only** call site in either file. Therefore, for a `TenantAgentAuction` record
whose `user_type` is `seller` or `landlord`:

- the canonical blob is **never written**, on create or on edit;
- the mirror keys are written from component properties and **never corrected from the blob**;
- any pre-existing blob on that record becomes permanently stale, while the mirrors keep moving.

This is a live blob/mirror divergence generator that no G1 document names and no test covers. Whether it is
correct — a seller-side Hire record arguably has no search envelope — is an **owner question**, not
something G1f should decide silently. It is raised as **D-G1F-3** in §22.

**Now covered, and DECIDED — D-G1F-3 APPROVED: 3-C (2026-08-02).** `G1fHireTenantUserTypeGateCharacterisationTest`
(`b91d5e7a2`, 7 tests) executes all six `user_type` input classes through the real save path and proves:
the canonical blob is written for `buyer` and `tenant` **only**; the discrete mirrors are written for **all
six**; and across two consecutive `seller` saves the mirrors advance twice while a pre-existing blob stands
completely still (`test_a_pre_existing_blob_goes_stale_while_mirrors_advance_across_two_saves`), with an
ungated `tenant` control on the identical fixture proving the gate is the cause.

**The approved disposition separates two things the audit had conflated:**

- **The gate is PRESERVED for G1f-2.** Seller/landlord Hire Tenant records may continue to have no canonical
  Search Areas blob, and consolidation must not begin writing one for them. This records current behaviour
  for the migration; it does **not** endorse permanent mirror/blob divergence as an ideal design, and the
  gate may be revisited only under a separately authorized product decision about whether those records
  should have a search envelope at all.
- **The partial-write failure is a SEPARATE DEFECT** — see §22.5 defect 1. It is not part of the gate
  decision and must not be bundled with any future removal of the gate.

### 6.2.1 The partial-write defect the gate concealed

**Discovered by GAP 3's characterisation, not by the audit.** `saveAllMetadata()` builds a role key as
`$this->user_type . '_specific'` and indexes `$compatibility_preferences` with it
(`TenantAgentAuction.php:4689`). That array declares exactly four role keys — `tenant_specific`,
`buyer_specific`, `seller_specific`, `landlord_specific` — so an **empty or unrecognised `user_type` raises
`Undefined array key`**.

Line 4689 executes **after** the mirror writes at `:4282`-`:4285` and after the gate at `:4290`, and
`TenantAgentAuction` opens **no transaction**. The mirrors are therefore committed and the remainder of the
save never happens. Proven by `test_an_unrecognised_user_type_leaves_a_partial_write`, which asserts both
halves: the mirror holds the new value and the canonical key was never written.

**Partial persistence is proven, not inferred.** This is a concrete instance of §7's finding that atomicity
is the exception, and it is exactly the failure mode `LocationDnaPersistenceService` must not inherit.
**G1f must not silently preserve it**; correcting it requires its own scoped authorization, before or within
the Tenant Hire migration.

### 6.3 Mirror-key set and encoding

**F-G1F-5 — `zipCodes` is a fourth mirror key.** Written by all four Tenant-family components
(`TenantAgentAuction:4284`, `TenantAgentAuctionEdit:3466`, `TenantOfferListing:4378`,
`TenantOfferListingEdit:3302`) from `$this->zipCodes`. The canonical contract's corresponding dimension is
`zip_codes` (`Dimension::ZipCodes`). **Nothing derives one from the other.** `hydrateDiscreteLocationFromBlob()`
handles `state` and `counties` only. So a user editing ZIP codes in the map widget updates the blob's
`zip_codes` while the `zipCodes` mirror keeps its old value indefinitely.

**F-G1F-5 is now proven.** `G1fZipCodesMirrorCharacterisationTest` (`b91d5e7a2`, 7 tests) establishes all of
it behaviourally: the mirror holds the component-property value with a divergent blob present; the blob's
`zip_codes` survives the save untouched; **clearing canonical `zip_codes` leaves the mirror stale**; a save
with an empty property **destroys** a legacy-only mirror; and the Buyer family writes no `zipCodes` key at
all. A structural negative asserts that **no** writer derives the mirror from the blob, so the finding
cannot silently stop being true.

**F-G1F-6 — CORRECTED AND WIDENED by characterisation (`b91d5e7a2`).** As first written, this finding
recorded *two* incompatible `state` encodings. **There are three keys, not two encodings of one:**

| Key | Encoding | Written by |
|---|---|---|
| `state` | raw string | the trait at `:126`, all four Offer components, all four Hire components, and `BuyerCriteriaAuctionController:734` |
| `state` | JSON-encoded (`"FL"`) | `TenantCriteriaAuctionController:56`, `:354` |
| **`states`** (plural) | JSON-encoded | `BuyerCriteriaAuctionController:63`, `:435` |

And `BuyerCriteriaAuctionController::updateAuction()` emits **both `states` and `state`, in different
encodings, within a single save** — proven by
`test_buyer_criteria_update_emits_both_state_keys_in_different_encodings`. The Tenant double-encoding is
proven separately by `test_tenant_criteria_double_encodes_the_state_mirror`, which asserts the stored value
is literally `"FL"` and explicitly **not** `FL`.

No consumer in the G1b matrix reads the plural key. Its approved disposition is **legacy dead write, out of
scope for G1f-1** — §17.5. The `state` normalisation decision is **D-G1F-4 APPROVED: 4S-i** — §17.3.

### 6.4 Clear semantics — the measured matrix

Rebuilt at this commit from the guard and mirror-write structure, consistent with what G1a proved:

| Dimension | Trait (wf 1–4) | Buyer Offer (5, 6) | Tenant Offer (7, 8) | Criteria controllers (W6–W9) |
|---|---|---|---|---|
| `cities` cleared | resurrected on load; mirror correctly `[]` | resurrected; mirror `[]` | **honoured**; mirror `[]` | n/a — no blob-derived mirror |
| `counties` cleared | **stale value persists** | stale persists | stale persists | n/a |
| `state` cleared | **stale value persists** | stale persists | stale persists | n/a |
| `zip_codes` cleared | no mirror | no mirror | **mirror never updated at all** | n/a |
| `polygons` / `radius_searches` cleared | honoured (blob only) | honoured | honoured | honoured |
| Unmounted editor | **blob destroyed** | destroyed | destroyed | **no write — correct** |
| Unparseable payload | **blob destroyed** | destroyed | destroyed | **no write — correct** |

**F-G1F-2 is the row that matters most.** The last column is D-G1-2 Option 2-A's approved behaviour,
already in production:

```php
// BuyerCriteriaAuctionController.php:230-236 — and three structurally identical siblings
$ldnaValue = $request->input('location_dna_preferences');
if ($ldnaValue !== null && $ldnaValue !== '') {
    json_decode($ldnaValue);
    if (json_last_error() === JSON_ERROR_NONE) {
        $auction->saveMeta('location_dna_preferences', $ldnaValue);
    }
}
```

Absent payload → no write. Empty payload → no write. Unparseable payload → no write. That is exactly the
rule the G1c package describes as *"changes today's behaviour"* and schedules for G1f/G1g. It does not need
inventing. **G1f should lift this shape into `LocationDnaPersistenceService` and note in the commit message
that the behaviour is being generalised from an existing production implementation rather than introduced.**

The guard is not complete — it validates parseability but not shape, so a syntactically valid non-object
(`"0"`, `[1,2]`) still writes. `LocationDnaHydrator` already rejects both (§8.1), which is precisely the
gap the domain core closes.

---

## 7. Transaction and failure analysis

### 7.1 Transaction coverage

| Write | Transaction | Boundary |
|---|---|---|
| W1 via workflows 1, 2, 3 | **none** | — |
| W1 via workflow 4 | **yes** | `TenantAgentAuctionEdit:3390 → 4176`, rollback `:4200` |
| W2, W3, W4 | **none** | — |
| W5 | **yes** | `TenantOfferListingEdit:3236 → 3951`, rollback `:3987` |
| W6 | **yes** | `BuyerCriteriaAuctionController:55 → 318`, rollback `:321` |
| W7 | **yes** | `:426 → 754`, rollback `:757` |
| W8 | **yes** | `TenantCriteriaAuctionController:44 → 256`, rollback `:260` |
| W9 | **F-G1F-8 — none** | `:342`, `:543`, `:546` are **commented out** |

Five of nine transactional, three never transactional, one deliberately de-transactionalised at some point
and left that way. **G1f inherits an environment where atomicity is the exception.**

### 7.2 The non-atomic window

**F-G1F-7.** Two workflows separate the mirror write from the blob write by a large span:

| Workflow | Mirror write | Blob write | Gap | Intervening `saveMeta` calls | Transaction |
|---|---|---|---|---|---|
| `BuyerOfferListingEdit` | `:2424`–`:2425` | `:2825` | **400 lines** | **315** | **none** |
| `TenantOfferListingEdit` | `:3291`–`:3302` | `:3913` | **628 lines** | (large) | yes |

`BuyerOfferListingEdit` is the serious case. 315 sequential `updateOrCreate` round-trips execute between
writing `counties`/`state` and writing the blob, outside any transaction. **Any failure in that window — a
validation exception, a database timeout, a PHP fatal — commits a mirror derived from the new state
alongside a blob still holding the old.** The record is then permanently divergent with no error surfaced,
and G1b F-G1B-3 establishes that 29 of 44 consumers cannot detect the disagreement.

### 7.3 Granularity

`saveMeta()` is `updateOrCreate(["meta_key" => $key], ["meta_value" => $val])` on every model
(`BuyerAgentAuction:84`, `BuyerCriteriaAuction:51`, and siblings). Each key is a separate SELECT plus
INSERT-or-UPDATE. A complete Location DNA save is therefore **four or five independent statements** (blob +
`cities` + `counties` + `state` + optionally `zipCodes`) with no shared lock and, in six of nine paths, no
transaction.

`BuyerAgentAuction::saveMeta()` additionally JSON-encodes arrays and objects automatically; the base
`BuyerCriteriaAuction::saveMeta()` does not. Callers therefore pass pre-encoded JSON inconsistently, which
is the mechanical origin of F-G1F-6.

### 7.4 What G1f must guarantee

1. **One transaction per patch application.** D-G1-3's approved rule — token comparison *inside* the
   applying transaction — is unimplementable in the three non-transactional Livewire paths as they stand.
   `LocationDnaPersistenceService` must open its own transaction rather than assume a caller's.
2. **Blob and mirrors in the same transaction**, closing F-G1F-7 for `BuyerOfferListingEdit` as a side
   effect of the migration.
3. **Failure is `unavailable`, never a clear.** L5. The service must have no code path that converts an
   exception into an empty write.
4. **Nested-transaction safety.** Five paths already hold a transaction. Laravel's `DB::transaction()`
   handles nesting via savepoints, but the interaction with `TenantOfferListingEdit`'s explicit
   `beginTransaction`/`rollBack` pair must be exercised by test, not assumed. Named in §20.

---

## 8. G1d capability integration map

`LocationDnaCapabilityResolver` is implemented, inert, and has **zero production references**. G1f is the
first increment that could give it one.

### 8.1 Available surface

| Type | Relevant members |
|---|---|
| `LocationDnaCapability` | `ReadCanonicalDocument`, `ExposeAdministrativeLabels`, `ExposeExactGeometry`, `ExposeLocationNotes`, `EditDocument`, `ConsultLegacyMirrors`, `RepairLegacyMirrors`, `ReadRetainedSnapshot`, `RequirePublicProjection` |
| `LocationDnaSurface` | incl. `OwnerPrivateEdit`, `PrivateCanonicalPersistence`, `LegacyMigrationRepair` |
| `LocationDnaPurpose` | incl. `Edit`, `Persistence`, `LegacyRepair`; `isMutating()` |
| `LocationDnaCapabilitySet` | `allows()`, `maySet(Dimension)`, `mayClear(Dimension)`, `isFullyDenied()` |
| `LocationDnaAccessContext` | `of(...)`, `unknown()`, `isIncomplete()`, `signature()` |

### 8.2 Integration points G1f requires

| Point | Call | Failure mode |
|---|---|---|
| Before applying any command batch | `resolve(context)` then `allows(EditDocument)` | `capability_denied` |
| Per command in the batch | `maySet($dimension)` / `mayClear($dimension)` | `capability_denied`, batch rejected whole |
| Before consulting a legacy mirror | `allows(ConsultLegacyMirrors)` | fall through to absent |
| Before any repair write | `allows(RepairLegacyMirrors)` | **must deny in G1f** — see §10 |

### 8.3 The context-construction problem

`LocationDnaAccessContext::of()` needs a surface, a viewer relationship and a purpose. The eight workflows
supply none of these today; each performs an ad-hoc `Auth::id()` scoping in its own load method. **G1f must
construct the context server-side from the authenticated principal and the component's own identity — never
from client input** (§6.1's IDOR boundary, L6).

Two frictions, both surmountable, both needing an explicit decision:

- **`user_type` is a component property**, not a capability input. The Hire Tenant gate (F-G1F-4) currently
  keys behaviour off it. Mapping it into `LocationDnaSurface` would encode a workflow branch into the
  authorization layer, which §7's *"which Blade file rendered is not an authorisation input"* argues
  against. **Recommendation: leave the `user_type` gate as workflow logic in G1f and do not model it as a
  capability.**
- **`ownerEditableDimensions()` enumerates eight settable dimensions and excludes `subject_property`**
  (read-only, §17 G8). The eight workflows never write `subject_property`, so this is compatible today.

### 8.4 Recommended G1f-1 posture

**Wire the resolver, but only as an assertion, not as a branch.** Resolve the capability set, assert it
permits the batch, and throw `capability_denied` if not. Do **not** let a denied capability silently skip a
write — that converts an authorization failure into a data outcome, which is L5's exact prohibition.

---

## 9. G1e provenance integration map

`LocationDnaProvenanceMap` is implemented, inert, zero production references, and — critically — **has no
persistence**. No table, no column, no migration.

### 9.1 Available surface

`LocationDnaProvenanceKind` (9 kinds), `ProvenanceAuthority` (4), `ProvenanceActor` (3),
`DimensionProvenance`, `LocationDnaProvenanceMap` (`with()`, `without()`, `withTransition()`,
`allowsTransition()`, `toInternalArray()`, `fromInternalArray()`), `ProvenanceTransition`
(`isAllowed()`, `refusalReason()`, `assertAllowed()`).

`toInternalArray()` / `fromInternalArray()` mean the map **is** serializable — the model is persistence-ready
even though no persistence exists.

### 9.2 The kinds G1f would need

| Situation in G1f | Kind | Available? |
|---|---|---|
| User sets a dimension | `OwnerAuthored` | yes |
| User clears a dimension | `OwnerCleared` | yes — the only kind that blocks fallback resurrection |
| Value recovered from a legacy mirror at load | `LegacyFallback` | yes — non-authoritative |
| Recovered value written to canonical storage | `LegacyRepaired` | yes — the only legal migration transition |
| Blob key present, value authored before v1.2 | `Unknown` | yes — forbidden as restoration source |

### 9.3 The integration constraint

`ProvenanceTransition` enforces that `LegacyFallback → LegacyRepaired` is the **only** transition a
`MigrationRepair` actor may make, and that automatic fallback can never restore an `OwnerCleared` dimension.
**These rules are only enforceable if the prior kind is known.** With no persistence, every load starts from
an empty map, so on the next save every dimension looks like a first authoring write by an `ExplicitOwner` —
which the transition rules permit from any origin. **The guard rails exist but have nothing to hold onto.**

This is the crux of §10.

---

## 10. Is provenance persistence required before writer consolidation?

### 10.1 The question, precisely

D-G1-6 approved: *"lazy repair must preserve provenance and may not convert inherited values into authored
values."* §5.4 rule 5: a mirror-recovered value is *inherited, not authored*, and must never be written back
as authored. Both rules require the writer to distinguish, at write time, between a value the user authored
and a value the system recovered. **Nothing in the tree records that distinction durably.**

### 10.2 Answer

**No — provenance persistence is NOT required before writer consolidation, but only under a constraint
that must be stated as a binding condition of the G1f authorization.**

**The constraint:** `LocationDnaPersistenceService` must persist **only dimensions carried by an explicit
`DimensionCommand`**, and must perform **no legacy repair**. Under D-G1-2's approved vocabulary, "preserve"
is the absence of a command; a dimension with no command is not written, so an inherited value can never be
promoted, so there is nothing whose provenance needs recording.

**Why this works.** The promotion hazard exists only on a write path. If the writer never writes an
inherited value, the S5 rule is satisfied structurally rather than by bookkeeping — the same way D-G1-2
resolves the unmounted-editor ambiguity structurally rather than with a flag. This mirrors what
`TenantOfferListing` already does correctly: its legacy merge is annotated **LOAD-TIME ONLY** and is never
re-applied on save.

### 10.3 Why the alternative shape is unsafe

If G1f instead implements the intuitive shape — hydrate the stored blob, apply commands, serialize the whole
document back — then on the first save of any legacy record:

1. `loadSearchAreas()` has merged the legacy `cities` mirror into the prefill;
2. the browser bridge returns that merged value in the payload, indistinguishable from user input;
3. the writer serializes it as a present, authored dimension and stamps `schema_version: 2`;
4. the record is now S2, where **absent means never authored and is authoritative**;
5. the inherited value is permanently indistinguishable from an authored one, with no provenance record and
   no way to recover the distinction.

That is a **silent, irreversible, per-record violation of §5.4 rule 5 and D-G1-6**, occurring on the first
save of every legacy record touched. It would not fail any existing test. It is the single most dangerous
thing G1f could do, and it is the *natural* implementation — which is why it is called out here rather than
left to the implementer.

### 10.4 What is required before which gate

| Requirement | Blocks |
|---|---|
| Provenance **model** (G1e) | already implemented |
| Writer restricted to commanded dimensions, no repair | **G1f-1 — binding condition** |
| Provenance **persistence** (a column or table) | **G1f-N, the increment that first performs lazy mirror repair** |
| Provenance persistence | **D-G1-6's mirror sunset** — unconditionally |

**Recommendation.** Authorize G1f-1 without provenance persistence, under the §10.2 constraint, and make
the constraint a stop condition with a test that proves it (§20.2). Raise a separate decision — **D-G1F-1**
in §22 — for the persistence design before any repair increment.

### 10.5 D-G1F-1 — APPROVED (2026-08-02)

**The owner approved §10.2's answer verbatim:** provenance persistence is **not** required before writer
consolidation, **but only under the binding condition** that `LocationDnaPersistenceService` persists solely
dimensions carried by explicit `DimensionCommand` objects and performs **no legacy repair**.

The approval attaches eight restrictions, all binding on G1f-1:

| # | Restriction |
|---|---|
| 1 | Explicit owner `set` / `clear` commands **only** |
| 2 | **No** legacy repair |
| 3 | **No** inherited-value promotion |
| 4 | **No** imported-value promotion |
| 5 | **No** derived-value promotion |
| 6 | **No** snapshot restoration |
| 7 | **No** full-document rewrite |
| 8 | Provenance remains **transient / inert** for the initial migration |

**The final provenance-storage design remains deferred** until the first separately authorized repair
increment. §10.4's table is unchanged by this approval: provenance persistence still blocks the first repair
increment, and still blocks D-G1-6's mirror sunset unconditionally.

**How the condition is proven rather than asserted.** §20.2 test **T4** — *a dimension with no command is
not written, and its legacy mirror is not rewritten* — is the mechanical proof that restrictions 1, 3, 4, 5
and 7 hold. It is a binding stop condition of the G1f-1 authorization (§23), and if it cannot be made green
the approval lapses: G1f-1 would then require D-G1F-1's storage design to be settled first.

---

## 11. Revision-token analysis

### 11.1 What exists

`LocationDnaRevisionToken` is implemented and tested (part of G1c's 128 passing tests): format
`ldna-r1:<sha256>`, `forDocument()` and `forDimension()`, vertex-order sensitive, collection-order
insensitive, absent ≠ cleared, lazy-upgrade neutral, refuses to tokenise a malformed document, mutates
nothing. It matches D-G1-3's approved contract in full.

### 11.2 What is missing for G1f

| Requirement | Status |
|---|---|
| Token computed from a document | **exists** |
| Token **compared inside the applying transaction** (§6.4) | **needs `LocationDnaPersistenceService`** |
| `expected_revision` carried on an envelope | `DimensionCommand` has **no** `expectedRevision` field |
| Token surfaced to the editing surface for round-trip | no transport; the bridge carries only the blob string |
| `revision_conflict` error path | no error-code plumbing exists |

**`DimensionCommand` carries `dimension`, `operation` and `value` only.** The G1c package's illustrative
`DimensionEnvelope` included `expectedRevision`, `workflowContext`, `provenance` and `idempotencyKey`; the
implemented `DimensionCommand` has none of them. **G1f cannot enforce optimistic concurrency without either
extending `DimensionCommand` or passing the expected revision alongside the batch.**

### 11.3 Recommendation — defer concurrency, do not defer the token

**F-G1F-10.** Concurrency enforcement should **not** be in G1f-1. §6.4 makes `expected_revision` *not
required* for a first authoring write, and every G1f-1 migration is by definition the first canonical write
to that record. Requiring it immediately would mean building token transport through the Livewire bridge —
which is G1g/G2 work, and G1b U-G1B-1 leaves the bridge's behaviour unproven.

**But the token must be computed and recorded from G1f-1 onward**, because the first canonical write is a
one-way door: `LocationDnaSerializer::toArray()` unconditionally stamps `schema_version: 2`, moving the
record from S1 (absence indeterminate, fallback may apply) to S2 (absence authoritative, no fallback). That
transition is per-record, irreversible in practice, and changes how every subsequent read interprets the
document. Having the pre- and post-write tokens in the audit trail is what makes it reviewable.

**Recommended G1f-1 posture:** compute `forDocument()` before and after each write and log both; do not
gate the write on a comparison. Add the comparison in the increment that introduces token transport.

**As implemented (`5c38fc574`), the token does more than the recommendation asked.** It is computed before
and after, and the write is **skipped entirely when the two are equal** — so the token decides whether a
change is semantic, not merely whether it is loggable. That is what makes the one-way door in **§27.7**
narrower than this section originally predicted: it fires only on a genuine semantic change, never on a
re-save of identical values and never on a commandless save. Concurrency enforcement remains deferred as
recommended; `revision_conflict` is reserved and unused.

---

## 12. CriteriaHash analysis

**No change. `CriteriaHashService` must not be touched by G1f.**

D-G1-3's Carried Condition 1 defers the Bridge cache key to a separately authorized compatibility increment,
*after* the domain revision token is implemented and characterized. That deferral was restated unchanged at
G1c, G1d and G1e implementation.

**Verified at this commit:** `CriteriaHashService` is not referenced by any of the three domain namespaces;
`G1cContractCoreInertnessGuardTest::test_the_contract_core_does_not_reference_the_framework_or_persistence`
lists `CriteriaHashService` among its forbidden tokens and passes; `G1bCriteriaHashCharacterisationTest`'s
21 tests pass unchanged.

**Interaction with G1f, assessed and found nil.** The hash reads a Bridge DTO (`BuyerCriteriaPayload`), not
the meta key. Its 35-key whitelist uses `preferred_cities`/`preferred_counties` and has no `state` or
`location_notes`, so canonical vocabulary is structurally unable to reach it. Omission and canonical-empty
already hash identically (U-G1B-2, resolved), so **G1f's omission capability cannot cause cache churn**.

**Accepted consequence, restated:** a polygon reshape and a deliberate geometry clear continue not to
invalidate the Bridge cache. G1f does not change this and must not be expected to.

---

## 13. Snapshot isolation analysis

**No change. G1f must not read, write, delete or re-project `AcceptedBidSummary.location_intelligence_snapshot`.**

D-G1-7 approved Option 7-E: retain under sunset and reader guard; final disposition among 7-A/7-B/7-C still
open and **due before G1g is declared complete**; no new production reader without a separate amendment.

**Verified:** `G1bAcceptedBidDocumentCharacterisationTest` (9 tests) passes, including
`test_retention_column_has_no_production_read_sites`. G1d resolves `ReadRetainedSnapshot` to denied in
**every** context, proven exhaustively. G1e's `SnapshotRetained` carries
`forbidden_as_restoration_source` and every transition out of it is denied.

**The G1f-specific risk.** The snapshot is written by `AcceptedBidSummaryService:730`,
`BuyerAcceptedBidSummaryService:476` and `BackfillLocationSnapshots:146`, each taking the **whole decoded
blob**. If G1f changes the blob's serialized shape — and it does, because the serializer omits never-authored
keys and stamps `schema_version` — **the snapshot's shape changes with it**, silently, for every accepted
bid created after migration. No test asserts snapshot shape stability across a canonical-writer change.

This is not a reason to block G1f. It is a reason to add the assertion, named in §20.3.

---

## 14. Bridge boundary analysis

**No change. G1f must not touch `app/Services/Bridge/`.**

The boundary is clean and was re-verified:

| Property | Finding |
|---|---|
| Does Bridge read the canonical meta key? | **No** — it reads `BuyerCriteriaPayload` / `TenantCriteriaPayload` DTOs |
| Does Bridge read canonical dimension names? | **No** — `preferred_cities`, `preferred_counties`; no `state`, no `location_notes` |
| Sole production caller of the hash | `LazyBridgeImportService.php:24` |
| Would G1f's omission change a Bridge hash? | **No** — omission ≡ canonical-empty after DTO defaulting |
| Does geometry leave the system via Bridge? | Yes — as `PolygonBoundingBox`, out of scope per R13 |

**One indirect coupling to watch.** The Stellar criteria loaders (C-31 … C-35) read the canonical blob and
feed the DTOs. If G1f changes what those loaders receive — specifically if a never-authored key becomes
*absent* rather than *present-and-empty* — the loaders' `!empty()` idiom yields the same result either way,
so the DTO is unaffected. **Verified, not assumed:** all five use `is_array($ldnaRaw) ? … : (json_decode(…) ?? [])`
followed by `!empty($ldna['key'])`, which cannot distinguish the two cases. The coupling is real but inert.

---

## 15. Public/private projection analysis

**No change. `PublicGeometryProjection` must be preserved, not routed around** — G0.1's seam is committed
and its regression guard is binding (G1 report §15 stop condition 6).

**Verified at this commit:** `PublicGeometryProjectionTest` 15 tests pass; `PublicGeometryContainmentTest`
13 pass; `PublicGeometryRegressionGuardTest` 7 pass. All four public controllers still apply the projection.
`LocationDnaSerializerTest::test_serialisation_applies_no_public_projection` passes, confirming the two
concerns are separate as D-G1-5 requires.

### 15.1 The ordering rule G1f must not disturb

G0.1 deliberately applies the projection **after** enrichment, so enrichment runs on unprojected geometry
(G1b §7.E, F-G1B-5). G1e's implementation record already states provenance must not reorder those calls.
**The same constraint binds G1f:** the persistence service sits on the *write* path and the projection on
the *read* path, and they must not be merged, chained or made to share a normalisation step.

### 15.2 The latent surface, unchanged

F-G1B-1's three `summary_json` readers remain pre-wired to render canonical dimension names from a store
that does not carry them. **G1f must not write canonical dimensions into `property_location_dna.summary_json`.**
The persistence service writes exactly one canonical key plus the derived mirrors; if any future
implementation is tempted to also update the summary store, that is a D4 violation and a stop condition.

---

## 16. Proposed `LocationDnaPersistenceService` contract

**Illustrative. Not production code. Not authorized for implementation.**

Namespace: `App\Services\LocationDna\Persistence` — a fourth sibling to `Contract`, `Capability` and
`Provenance`, matching the established `app/Services/<Area>/<Purpose>/` convention.

### 16.1 Responsibilities

| Owns | Must not |
|---|---|
| The **only** write of the canonical meta key | Read `schema_version` (the hydrator's job) |
| Batch validation as a whole; atomic application | Partially apply a batch |
| Opening its own transaction | Assume a caller holds one |
| Capability assertion before applying | Convert a denial into a skipped write |
| Delegating mirror derivation to the projection (§17) | Derive mirrors itself |
| Returning a typed result | Throw framework exceptions across the boundary |
| Raising `unavailable` on failure | Record a failure as a clear (L5) |

### 16.2 Proposed interface

```php
final class LocationDnaPersistenceService
{
    /**
     * Apply a validated batch atomically. Never partially applies.
     *
     * @param  DimensionCommand[]  $batch  Empty batch is legal and applies nothing.
     */
    public function apply(
        LocationDnaWritableRecord $record,   // narrow port over saveMeta()/info()
        array $batch,
        LocationDnaAccessContext $context,
    ): PatchResult;
}
```

### 16.3 The record port

The service must not depend on Eloquent (§6, and `G1cContractCoreInertnessGuardTest` forbids `Model`,
`Eloquent` and `saveMeta` inside the domain core). A narrow port keeps the domain framework-free while
`saveMeta()` stays where it is:

```php
interface LocationDnaWritableRecord
{
    public function readCanonical(): mixed;              // raw meta value; may be false
    public function writeCanonical(string $json): void;
    public function readMirror(string $key): mixed;
    public function writeMirror(string $key, string $value): void;
}
```

`readCanonical()` returning `mixed` is deliberate: `info()` returns **boolean `false`** for an unwritten
key, and `LocationDnaHydrator::hydrate()` already handles `false`, `null` and `''` as absent. That is a
verified compatibility, not a hoped-for one.

### 16.4 Application algorithm

```
apply(record, batch, context):
  1. capabilities ← resolver.resolve(context)
  2. assert capabilities.allows(EditDocument)              else capability_denied
  3. for each command: assert maySet/mayClear(dimension)   else capability_denied
  4. if batch is empty: return PatchResult::noOp()         ← no write, no mirror rewrite
  5. result ← hydrator.hydrate(record.readCanonical())
  6. if result.isMalformed():        return unavailable    ← never overwrite, never clear
     if result.isUnsupportedVersion(): return unavailable  ← read-only above v2 (§5.5)
  7. document ← result.document() ?? LocationDnaDocument::emptyDocument()
  8. tokenBefore ← revisionToken.forDocument(document)
  9. next ← applier.apply(document, batch)                 ← pure, commanded dimensions only
 10. BEGIN TRANSACTION
 11.   record.writeCanonical(serializer.toJson(next))
 12.   mirrorProjection.write(record, next, batch)         ← §17
 13. COMMIT
 14. return PatchResult::applied(tokenBefore, revisionToken.forDocument(next))
```

**Step 4 is the fix for `test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror`.** An empty batch
writes nothing at all, including no mirror rewrite.

**Step 6 is the fix for the unmounted-editor destruction**, and generalises F-G1F-2's existing controller
guard with the shape validation it lacks.

**Step 9 is the §10.2 constraint made structural:** the applier only touches commanded dimensions, so an
inherited value with no command is never written.

### 16.5 Result and error shapes

`PatchResult` carries an outcome plus, on failure, one code from §6.3's closed set. G1f-1 needs
`capability_denied`, `invalid_value`, `empty_set_rejected`, `unknown_schema_version` and `unavailable`.
`revision_conflict` is reserved and unused until token transport exists (§11.3).

### 16.6 What the service must NOT do in G1f-1

Read or write provenance · perform legacy mirror repair · read a legacy mirror as a write-path input ·
compare revision tokens as a gate · touch `CriteriaHashService`, `PublicGeometryProjection` or the retained
snapshot · write to `summary_json` · construct its own context from client input.

---

## 17. Proposed mirror projection contract

Separate component: `LegacyMirrorProjection`. **Named deliberately, not `LegacyMirrorAdapter`.** The
approved D-G1-5 name is `LegacyMirrorAdapter` and the G1c inertness guard asserts by class name that it does
**not** exist. Creating it is a visible authorization event. Whether the G1f-1 write-side component adopts
that name — thereby tripping the guard immediately — or a distinct name reserving `LegacyMirrorAdapter` for
the full read+repair adapter, is **D-G1F-2** in §22.

### 17.0 D-G1F-2 — APPROVED: 2-A (2026-08-02)

**The distinct write-side name `LegacyMirrorProjection` is approved. `LegacyMirrorAdapter` is reserved** for
the later read / fallback / repair responsibility and **remains uncreated and separately
authorization-gated** — the G1c inertness guard assertion naming it stays in place unchanged.

**`LegacyMirrorProjection` — approved responsibilities, exhaustive:**

| It does | It does **not** |
|---|---|
| Is **pure** and **write-side only** | Read existing mirrors |
| Accept the resulting canonical `LocationDnaDocument` | Perform fallback |
| Return derived legacy mirror values | Perform repair |
| — | Determine provenance |
| — | Write to storage itself |

**Amended interpretation of D-G1-5.** The approved D-G1-5 text says *"legacy compatibility belongs
exclusively to `LegacyMirrorAdapter`."* That remains true as a statement of **isolation** — legacy
compatibility stays in one place and stays removable — but it is now read as covering **two deliberately
separate responsibilities**: write-side projection (`LegacyMirrorProjection`, G1f-1) and future
read / fallback / repair (`LegacyMirrorAdapter`, unauthorized). This is an interpretation amendment, not a
reopening of D-G1-5.

**This approval introduces no runtime behaviour change.** It names a component that does not yet exist.

**Evidence that the split is right, not merely convenient.** GAP 2 proved the Livewire family *derives* its
mirrors from the blob — the trait-derived write wins and the persisted value is blob-sourced. GAP 1 proved
the criteria controllers do **not**: canonical and mirror travel independently from separate request fields
and are persisted unreconciled (`test_canonical_and_mirror_values_are_persisted_unreconciled`). A single
write-side projection must therefore serve two structurally different mirror sources, which is a different
responsibility from read-side fallback and repair.

### 17.1 Responsibilities

| Owns | Must not |
|---|---|
| Deriving `cities` / `counties` / `state` from the document | Be consulted when the blob key is present (read side) |
| Deciding which mirror keys a given record type carries | Read request input |
| Being removable in one file at sunset | Promote an inherited value |

### 17.2 Proposed derivation table

| Mirror key | Source dimension | On authored | On cleared | On absent |
|---|---|---|---|---|
| `cities` | `Dimension::Cities` | `json_encode(values)` | `json_encode([])` | **not written** |
| `counties` | `Dimension::Counties` | `json_encode(values)` | `json_encode([])` | **not written** |
| `state` | `Dimension::State` | raw string | `''` | **not written** |
| `zipCodes` | — | **NOT MANAGED in G1f-1** — D-G1F-4 (a) | not managed | not managed |

**"Not written on absent" is the key departure from today.** Every current writer writes all its mirror keys
unconditionally on every save, which is exactly what destroys a legacy mirror on a no-op save. Writing only
commanded dimensions preserves the legacy mirror until the user actually edits that dimension — which is
what makes step 4 of §16.4 correct.

### 17.3 The `state` encoding decision — D-G1F-4 APPROVED: 4S-i (2026-08-02)

F-G1F-6 leaves incompatible encodings. The projection must emit **one**. Recommendation: **raw string**,
matching eight of the nine writers and both Livewire families. Consequence: Tenant Criteria records written
through W8/W9 keep their `"FL"` form until next saved, so readers must tolerate both during transition.
Raised as part of **D-G1F-4**.

**APPROVED — 4S-i, normalise the managed `state` mirror to a raw string.** Recorded terms:

- `LegacyMirrorProjection` **emits `state` as a raw string**.
- `BuyerAgentAuction` already uses this representation, so **G1f-1 does not change its observable `state`
  encoding** — the first migration is encoding-neutral.
- **Readers must tolerate existing JSON-encoded `state` values during transition.**
- Tenant Criteria records may change to raw-string encoding **only when their own later consolidation stage
  is separately authorized**.
- **No bulk migration or backfill is authorized.**

### 17.4 `zipCodes` — D-G1F-4 APPROVED: (a) FOR G1f-1 (2026-08-02)

Three options, none free: (a) leave unmanaged, preserving today's divergence; (b) derive from
`Dimension::ZipCodes`, which **changes observable data** for four workflows; (c) stop writing it, which
breaks any reader. **Recommendation: (a) for G1f-1** — out of scope, explicitly recorded — with (b) decided
separately. Doing (b) inside a consolidation increment would mix a defect fix into a refactor.

**APPROVED — option (a), for G1f-1 only.** Recorded terms:

- `zipCodes` **remains unmanaged** in the first migration; it is outside the managed mirror projection.
- This **preserves current `BuyerAgentAuction` behaviour** — that component writes no `zipCodes` key, proven
  by `test_buyer_family_components_write_no_zipcodes_mirror`.
- **G1f-1 must not introduce a new ZIP mirror write.**
- **G1f-1 must not remove an existing ZIP mirror write.**
- The new writer **must not imply that `zipCodes` has been normalised or repaired**.

**Options (b) and (c) are NOT approved.**

**Future decision checkpoint, binding.** Before migrating **any** workflow that currently writes `zipCodes`
— `TenantOfferListing`, `TenantOfferListingEdit`, `TenantAgentAuction`, `TenantAgentAuctionEdit` — reassess
whether to approve option (b). **G1f cannot be declared complete without a final managed-mirror decision for
`zipCodes`.**

The evidence that makes (b) a behaviour change rather than a cleanup is now measured: clearing canonical
`zip_codes` leaves the mirror stale, and a save with an empty property destroys a legacy-only mirror
(§6.3). Both would change under (b).

### 17.5 The plural `states` key — LEGACY DEAD WRITE, OUT OF SCOPE FOR G1f-1

Recorded disposition (2026-08-02). `BuyerCriteriaAuctionController` writes a plural `states` key at `:63`
and `:435`, JSON-encoded, and no consumer in the G1b matrix reads it (§6.3).

**For G1f-1:**

- **do not create it** · **do not normalise it** · **do not delete stored historical values** ·
  **do not add a reader** · **do not treat it as part of the managed mirror projection**.

**For the later legacy criteria-controller migration (G1f-7):**

- perform a **final reader audit**;
- if no production reader exists, **retire the write**;
- **preserve existing stored values** unless a separate cleanup or migration is authorized;
- **do not keep emitting both `state` and `states`** merely for historical symmetry.

**The unreachable `storeAuction` path (§22.5 defect 2) is not evidence that the plural write is required**
and must not be read as such.

---

## 18. Recommended staged rollout

| Increment | Scope | Stop condition | Rollback |
|---|---|---|---|
| **G1f-1** — **IMPLEMENTED (`5c38fc574`)** | Create `LocationDnaPersistenceService` + `LegacyMirrorProjection`. Migrate **one** workflow (§19). No provenance, no repair, no token gating. | **Met.** The migrated workflow's characterisation holds except the assertions D-G1-4 intentionally changes; the other seven workflows' characterisation **unchanged**; line 130 clear-mirroring not regressed. See §27 | revert the single commit |
| **G1f-2** — **IMPLEMENTED (`d3123fb94`)** | Migrate the second Hire create flow (`TenantAgentAuction`). Reuse the G1f-1 boundary; add no production class | **Met.** GAP 3 was closed at `b91d5e7a2`; the `user_type` gate is **explicitly preserved** per D-G1F-3 3-C, and defect boundary 1 is closed as a separate ordering repair. The six unmigrated workflows' characterisation is **unchanged**. See §28 | revert the single commit |
| **G1f-3** — **IMPLEMENTED (`a17d4cb14`)** | Migrate both Buyer Offer inline copies, **together in one commit** | **Met.** The 12 G1a inline tests are reconciled (4 changed, 8 unchanged); `test_buyer_offer_components_are_unchanged` converted as the authorized B4 change; the four unmigrated workflows' characterisation **unchanged**. The **write-side** inline sites are deleted; the **load-side** `empty()` guards and both `hydrateDiscreteLocationFromBlob()` definitions deliberately remain (F-G1F-14). See §30 | revert the single commit |
| **G1f-4** | Migrate both Tenant Offer copies | **the two correct workflows' clear tests must not change** — the binding acceptance test | revert |
| **G1f-5** | Convert the trait to a delegating shim (D-G1-5's 5-C); the four Hire hosts converge without edit | `test_hire_trait_semantics_are_unchanged` updated as authorized change; Hire double-write removed | revert |
| **G1f-6** | Migrate the two `update()`-based edit flows | non-atomic window closed for `BuyerOfferListingEdit`; nested-transaction behaviour proven | revert |
| **G1f-7** | Migrate the four criteria-controller writes (W6–W9) | **blocked on the §20 gap** — no characterisation exists | revert |
| **G1f-8** | Remove the shim | shim removal is D-G1-5's exit criterion | revert |

**Sequencing rationale.** Create-flows before edit-flows because their save paths are invocable in isolation
and therefore parity-testable. The Tenant Offer pair *after* the Buyer pair, so the workflows whose tests
must **not** change are migrated once the service is proven on workflows whose tests are expected to change.
The trait shim after all four inline copies, so the shim is introduced against a known-good service. The
criteria controllers last, and only after characterisation.

---

## 19. Which workflow should migrate first, and why

**Recommendation: `BuyerAgentAuction` — Hire Buyer, create.**

| Criterion | `BuyerAgentAuction` | Alternatives |
|---|---|---|
| Save path invocable in isolation | **yes** — `saveAllMetadata()` | the two `update()` flows are not |
| Transaction present | no — so the service's own transaction is exercised cleanly, with no nesting | `TenantAgentAuctionEdit`, `TenantOfferListingEdit` nest |
| Size | **2,527 lines — smallest of the eight** | `TenantOfferListing` is 5,323 |
| Existing characterisation | load-side + save-side behavioural (G1a), plus `HireSearchAreasParityTest` (9) and `SearchAreasGeometryContractTest` (16) | Buyer Offer has 12; Tenant Offer edit has structural only |
| Semantics | defective via the trait — expected to change, so a changed test is the signal, not the alarm | Tenant Offer is correct; a changed test there means a **regression** |
| Exercises the Hire double-write (F-G1F-3) | **yes** — the highest-value unknown | Offer flows do not |
| `user_type` gate (F-G1F-4) | **absent** — not entangled | both Hire Tenant flows are |
| Frozen-by-convention | no | `TenantAgentAuction` is (CLAUDE.md) |

**The decisive reason.** `BuyerAgentAuction` is the only workflow that exercises the Hire double-write
structure without also carrying the `user_type` gate, the `zipCodes` mirror, a nested transaction, a
non-invocable save path, or semantics that must not change. It isolates exactly one unknown.

**Explicitly rejected as first:** the Tenant Offer pair. It looks attractive — its semantics are already the
convergence target — but its tests **must not change**, so it is the worst possible place to discover that
the service's contract is wrong. It should be migrated once the service is proven, as confirmation.

**Also rejected:** the trait. Converting it first changes four workflows at once, which contradicts
"one workflow per commit" and makes a parity failure impossible to attribute.

**CONFIRMED after characterisation (`b91d5e7a2`).** `BuyerAgentAuction` **remains the recommended first
migration**, and the recommendation is now stronger than when it was made: the one unknown it was chosen to
isolate — the Hire double-write — is measured, so the migration has a real parity baseline rather than a
structural assumption. Three of the approvals also land in its favour: it is unaffected by the `user_type`
gate (D-G1F-3, which touches only the Hire Tenant pair), it writes no `zipCodes` key so D-G1F-4 (a) is a
no-op for it, and it already emits `state` as a raw string so 4S-i changes nothing observable in the first
migration.

---

## 20. Complete required test plan

### 20.1 Characterisation that already exists and must stay green

| Suite | Tests | Role in G1f |
|---|---|---|
| `G1aTraitPresenceSemanticsCharacterisationTest` | 12 | five guard sites; 5 change by design in G1f-5 |
| `G1aRecordInterpretationCharacterisationTest` | 10 | S1–S5; 2 change by design |
| `G1aCrossDimensionPreservationCharacterisationTest` | 6 | mounted/unmounted; 1 changes by design |
| `G1aBuyerOfferInlineCharacterisationTest` | 12 | both Buyer copies; change in G1f-3 |
| `G1aWorkflowPersistenceMatrixCharacterisationTest` | 7 | 8-workflow matrix; 6/2 → 8/0 in G1f |
| `SearchAreasPersistenceCharacterisationTest` | 9 | storage properties — **must not change** |
| `TenantOfferCitiesMirrorTest` | 15 | contains **two** tripwires (§20.4) |
| `HireSearchAreasParityTest` | 9 | Hire parity — **must not change** |
| `SearchAreasGeometryContractTest` / `GuardTest` / `WidgetContractTest` | 16 / 11 / 15 | geometry contract — must not change |
| `PublicGeometryProjectionTest` / `ContainmentTest` / `RegressionGuardTest` | 15 / 13 / 7 | G0.1 seam — **binding, must not change** |
| `G1bCriteriaHashCharacterisationTest` | 21 | Bridge deferral — **must not change** |
| `G1bAcceptedBidDocumentCharacterisationTest` | 9 | snapshot reader guard — must not change |
| G1c / G1d / G1e domain suites | 128 / 70 / 88 | inertness guards change in G1f-1 by authorization |

### 20.2 New tests G1f-1 must add

| # | Assertion | Why it cannot be static |
|---|---|---|
| T1 | An empty batch performs **no** write — canonical and every mirror byte-identical after | proves §16.4 step 4; the fix for the no-op mirror destruction |
| T2 | A malformed stored blob yields `unavailable` and **no** write; raw bytes preserved | proves the corrupt-blob path never clears |
| T3 | `schema_version > 2` yields `unavailable` and **no** write | §5.5 refuse-to-interpret |
| T4 | A dimension with no command is **not** written, and its legacy mirror is **not** rewritten | **the §10.2 provenance-safety constraint** — the single most important new test |
| T5 | A `clear` command writes canonical-empty and the empty mirror | D-G1-4 |
| T6 | A failure mid-apply rolls back canonical **and** mirrors together | closes F-G1F-7 |
| T7 | Applying inside a caller's open transaction commits correctly and rolls back correctly | nested-transaction safety (§7.4.4) |
| T8 | A denied capability raises `capability_denied` and writes nothing | proves a denial is not a skipped write |
| T9 | Snapshot shape is stable across a canonical-writer change | closes the §13 risk |
| T10 | Pre- and post-write revision tokens are recorded and differ iff values changed | §11.3 |

### 20.3 Per-workflow parity gate (every G1f-N)

For the migrated workflow: full G1a characterisation re-run; byte-identity of every uninvolved dimension; a
1,200-vertex polygon and unicode round-trip; the seven unmigrated workflows' characterisation unchanged;
`test_full_clear_cycle_survives_only_because_the_cities_mirror_is_also_cleared` still passing (line-130
protection, binding stop condition 4).

### 20.4 Tests that change by design — the authorized list is incomplete

The G1c package lists six entries. **F-G1F-9: a seventh exists and is not on it.**

```php
// TenantOfferCitiesMirrorTest::test_buyer_offer_components_are_unchanged
$this->assertStringContainsString(
    "\$auction->saveMeta('cities', json_encode(\$ldnaDecoded['cities'] ?? []));", $source);
$this->assertStringNotContainsString('FINDING 2B-3', $source);
```

It asserts that **both Buyer Offer components still contain the exact inline mirror-write line**. G1f-3
removes that line by definition, so this test fails. It is a second deliberate tripwire, structurally
identical in purpose to `test_hire_trait_semantics_are_unchanged`, and **it must be added to the authorized
change list before G1f-3** or a red run will look like an unplanned regression.

### 20.5 The four characterisation gaps — ALL CLOSED (`b91d5e7a2`)

**As originally written, this section stopped and reported.** No test was written, and the four gaps below
were declared with the smallest test that would settle each. They were subsequently authorized and closed by
a tests-only increment.

| Gap | Suite | Tests | Status |
|---|---|---|---|
| **GAP 1** — criteria-controller write paths | `tests/Feature/Spatial/G1fLegacyCriteriaControllerCharacterisationTest.php` | 14 | **CLOSED** |
| **GAP 2** — Hire double-write ordering | `tests/Feature/Spatial/G1fHireDoubleWriteCharacterisationTest.php` | 7 | **CLOSED** |
| **GAP 3** — `user_type` gate | `tests/Feature/Spatial/G1fHireTenantUserTypeGateCharacterisationTest.php` | 7 | **CLOSED** |
| **GAP 4** — `zipCodes` mirror | `tests/Feature/Spatial/G1fZipCodesMirrorCharacterisationTest.php` | 7 | **CLOSED** |
| **Total** | | **35** | all passing |

**GAP 2 was the only gap blocking G1f-1, and it is closed — so G1f-1 is UNBLOCKED.** It remains
unimplemented and unauthorized; see §23.

**What each closure proved, beyond confirming the prediction:**

- **GAP 2.** Ordering is proven from the **query log**, not from source order: the component-property write
  executes first, the trait-derived write wins, the persisted value is blob-derived, and no third value
  appears between them. It additionally proved that `counties` and `state` reach the winning write through a
  **mutated component property** while `cities` **bypasses the property path entirely** — so a single
  consolidated writer **cannot uniformly read component state**. A control case with agreeing values is
  included; `TenantAgentAuctionEdit` is asserted structurally at the documented boundary.
- **GAP 3.** Confirmed the gate, and **discovered a defect the audit had not seen**: an empty or unknown
  `user_type` raises after the mirror writes, with no transaction, so **partial persistence is proven**
  (§6.2.1, §22.5 defect 1).
- **GAP 4.** Confirmed every F-G1F-5 prediction and added a structural negative proving **no** writer
  derives the mirror from the blob.
- **GAP 1.** Confirmed all four controller paths preserve on absent, empty and unparseable payloads — the
  approved D-G1-2 rule, already in production. It also **corrected this report twice** (`states` plural, and
  `updateAuction`'s double mirror write) and established that **`storeAuction` cannot reach its own
  canonical write** against the migrated schema (§22.5 defect 2).

The original gap declarations are preserved below unchanged, so the reasoning that produced them stays
auditable.

---

---

**GAP 1 — the four criteria-controller write paths (W6–W9) have no characterisation whatsoever.**

- **Uncovered behaviour.** Whether the parseability guard actually prevents a write on each of the four
  paths; whether the mirror writes at `:61`/`:62`, `:433`/`:434`, `:732`–`:734`, `:54`–`:56`, `:352`–`:354`
  interact with the blob write; whether `TenantCriteriaAuctionController`'s `json_encode($request->state)`
  produces the double-encoded value F-G1F-6 predicts; and what the commented-out transaction at `:342`
  means for partial writes on the update path.
- **Production path.** `BuyerCriteriaAuctionController::store()` / `::update()` and
  `TenantCriteriaAuctionController::store()` / `::update()`, reached by authenticated form POST.
- **Why static inspection is insufficient.** The guard's correctness depends on what `$request->input()`
  returns for an absent field versus an empty one versus a whitespace string — Laravel-version-specific
  behaviour that cannot be read off the source. The double-encoding claim is an inference from
  `json_encode()` on a string; it needs a stored value to confirm. The commented-out transaction's effect on
  partial writes is only observable by forcing a mid-request failure.
- **Recommended tests (NOT written, NOT authorized).**
  1. `test_absent_ldna_payload_writes_nothing_on_each_criteria_path` — 4 paths × absent / `''` / whitespace.
  2. `test_unparseable_ldna_payload_writes_nothing_and_preserves_stored_bytes` — 4 paths.
  3. `test_syntactically_valid_non_object_payload_is_currently_written` — pins the shape gap the guard misses.
  4. `test_tenant_criteria_state_mirror_is_double_encoded` — pins F-G1F-6 with a stored value.
  5. `test_tenant_criteria_update_partial_write_survives_a_mid_request_failure` — pins F-G1F-8.
- **Blocks:** G1f-7. Does **not** block G1f-1.

---

**GAP 2 — the Hire double-write ordering (F-G1F-3) is unasserted.**

- **Uncovered behaviour.** That `saveSearchAreas()`'s mirror writes overwrite the component-property writes
  that precede them, in all four Hire components. Correctness rests on statement order alone.
- **Production path.** `BuyerAgentAuction::saveAllMetadata():1901–1908` and the three siblings.
- **Why static inspection is insufficient.** Reading the source shows the order; it does not prove the
  second `updateOrCreate` wins, nor that no intervening code mutates `$this->cities` between the two writes.
  For a divergent fixture — component property `["Tampa"]`, blob `["Orlando"]` — only execution shows which
  value lands.
- **Recommended tests (NOT written, NOT authorized).**
  1. `test_hire_trait_mirror_write_overwrites_the_component_property_write` — 4 components, divergent fixture.
  2. `test_hire_component_property_write_is_not_observable_after_save` — the negative form.
- **Blocks:** **G1f-1.** This is the workflow recommended for first migration, and this is the exact
  structure the migration removes. Without it there is no baseline to prove parity against.

---

**GAP 3 — the `user_type` gate (F-G1F-4) is uncharacterised.**

- **Uncovered behaviour.** That a `TenantAgentAuction` / `TenantAgentAuctionEdit` record with `user_type` of
  `seller` or `landlord` receives **no** canonical blob write, and that its mirrors are written from
  component properties and never corrected.
- **Production path.** `TenantAgentAuction::saveAllMetadata():4290`, `TenantAgentAuctionEdit:3456`.
- **Why static inspection is insufficient.** The gate is visible, but its *consequence* — a permanently
  stale blob beside a moving mirror — is a multi-save emergent property. Only a save-load-save sequence
  shows the divergence widening. G1a's matrix ran these components as buyer/tenant only, so the branch is
  unexecuted in the entire suite.
- **Recommended tests (NOT written, NOT authorized).**
  1. `test_seller_user_type_hire_tenant_save_writes_no_canonical_blob`.
  2. `test_seller_user_type_hire_tenant_mirrors_diverge_from_a_stale_blob_across_two_saves`.
  3. `test_buyer_user_type_hire_tenant_save_does_write_the_blob` — the control.
- **Blocks:** G1f-2. Does **not** block G1f-1.

---

**GAP 4 — the `zipCodes` mirror (F-G1F-5) is uncharacterised.**

- **Uncovered behaviour.** That editing `zip_codes` in the widget updates the blob but never the `zipCodes`
  mirror, in all four Tenant-family components.
- **Production path.** `TenantOfferListing:4378`, `TenantOfferListingEdit:3302`, `TenantAgentAuction:4284`,
  `TenantAgentAuctionEdit:3466`.
- **Why static inspection is insufficient.** The absence of a derivation is visible; the *observable
  divergence* is not, because `hydrateDiscreteLocationFromBlob()` might plausibly be extended elsewhere, and
  the JS bridge's handling of `zip_codes` is unproven (U-G1B-1). Only a round trip shows the mirror standing
  still while the blob moves.
- **Recommended tests (NOT written, NOT authorized).**
  1. `test_zip_codes_blob_edit_does_not_update_the_zipcodes_mirror` — 4 components.
  2. `test_zipcodes_mirror_retains_its_value_across_a_full_geometry_clear`.
- **Blocks:** the `zipCodes` half of D-G1F-4. Does **not** block G1f-1.

---

**Summary — superseded, retained for lineage.** As authored: *"GAP 2 blocks G1f-1. Gaps 1, 3 and 4 block
later increments. None of these tests has been written and none is authorized."*

**Current status: all four gaps CLOSED by `b91d5e7a2` — 35 tests, all passing.** GAP 2's closure unblocks
G1f-1. Gaps 1, 3 and 4 no longer block their later increments on characterisation grounds, though each
surfaced a defect boundary that does gate its own stage — see §22.5.

---

## 21. Proposed direct-writer guard

A standing structural guard, added in G1f-1, that fails the moment a canonical write appears outside the
persistence service. Modelled on the existing inertness guards, which are proven to work.

```php
// tests/Unit/Services/LocationDna/Persistence/G1fCanonicalWriterGuardTest.php  (PROPOSED)
private const CANONICAL_WRITE = "saveMeta('location_dna_preferences'";

private const AUTHORIZED_WRITERS = [
    'app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php',
    // Each entry below is a NOT-YET-MIGRATED writer. Entries are REMOVED as G1f
    // progresses; the list only ever shrinks. It reaching one entry is G1f's
    // completion condition.
    'app/Http/Livewire/Concerns/HasSearchAreas.php',
    'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
    'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
    'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
    'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
    'app/Http/Controllers/BuyerCriteriaAuctionController.php',
    'app/Http/Controllers/TenantCriteriaAuctionController.php',
];
```

### 21.1 Required properties

1. **Shrink-only.** Adding an entry is a regression and must require explicit authorization; the list
   reaching one entry is the completion condition. Same discipline as `DOMAIN_DIRS`.
2. **Comment-stripped before matching**, matching the existing guards, so a docblock mention is not a
   failure.
3. **Scan roots** `app/`, `routes/`, `database/` — the same roots the G1c guard uses.
4. **A second assertion covering mirror writes**, since a writer that stops writing the blob but keeps
   writing `cities` is still a direct writer.
5. **Explicitly excludes tests and seeders**, which legitimately construct fixtures directly.

### 21.2 Relationship to the existing inertness guards

`G1cContractCoreInertnessGuardTest` currently asserts that **no** production file references the contract and
that the eight workflows do not. **G1f-1 necessarily breaks both.** The required amendment is narrow and
must follow the precedent already set twice:

| Assertion | G1f-1 amendment |
|---|---|
| `test_no_production_file_outside_the_approved_domain_namespaces_references_it` | add `app/Services/LocationDna/Persistence` to `DOMAIN_DIRS` — one exact entry, no wildcard |
| `test_the_eight_workflow_components_and_the_trait_do_not_reference_the_core` | narrow to the seven **not yet migrated**; shrink-only, mirroring §21.1 |
| `test_the_classes_deliberately_not_created_in_this_increment_do_not_exist` | remove `LocationDnaPersistenceService` only. **`LegacyMirrorAdapter` is RETAINED in the assertion** — D-G1F-2 approved 2-A, so that class is not created by G1f-1 and stays authorization-gated |

Both prior increments (G1d, G1e) amended this guard by adding exactly one `DOMAIN_DIRS` entry with no
matching-logic change, and both recorded the amendment in the decision package. G1f-1 should do the same.

---

## 22. Risks, blockers and unresolved decisions

### 22.1 Blockers

| # | Blocker | Blocks | Status |
|---|---|---|---|
| B1 | **GAP 2** — Hire double-write ordering uncharacterised | **G1f-1** | **RESOLVED** — `b91d5e7a2`, 7 tests |
| B2 | GAP 1 — criteria controllers uncharacterised | G1f-7 | **RESOLVED** — `b91d5e7a2`, 14 tests |
| B3 | GAP 3 — `user_type` gate uncharacterised | G1f-2 | **RESOLVED** — `b91d5e7a2`, 7 tests. G1f-2 subsequently **implemented** against that baseline (`d3123fb94`, §28) |
| B4 | `test_buyer_offer_components_are_unchanged` absent from the authorized change list | G1f-3 | **RESOLVED** — named in the G1f-3 authorization in advance and converted, not deleted, into a positive migrated-boundary assertion (§30.8) |
| B5 | v1.2 §4.2 / §4.3 corrections still outstanding | formally, all of G1f | **OPEN** — documentation increment |
| B6 | D-G1-6 branch sequencing (`ux/hire-agent-create-offer-parity`) never decided | G1f-3, G1f-4 | **DECIDED — option B, and executed at G1f-3.** Proceed on the G1 branch; the UX branch rebases afterward. It was not merged, rebased, cherry-picked or modified (§30.2). **The rebase obligation stands and is unmet** |

**Blockers remaining before production implementation:** **none for G1f-1.** B4 blocks G1f-3, B6 blocks
G1f-3/G1f-4, and B5 is a documentation obligation carried from the G1 report §16 step 2. The defect
boundaries in §22.5 gate their own stages independently.

### 22.2 Decisions raised by this audit — ALL FIVE APPROVED (2026-08-02)

The questions are preserved as authored; the approved option and its recorded terms follow each.

| # | Decision | Question | Approved |
|---|---|---|---|
| **D-G1F-1** | Provenance persistence design | Column on the meta record, separate table, or embedded in the canonical document? Required before any repair increment; **not** required for G1f-1 under §10.2 | **§10.2 answer, with eight binding restrictions** — see §10.5. Storage design **deferred** to the first authorized repair increment |
| **D-G1F-2** | `LegacyMirrorAdapter` naming | Does G1f-1's write-side projection take the approved name — tripping the guard immediately — or a distinct name reserving it for the full read+repair adapter? | **2-A** — distinct name `LegacyMirrorProjection`; `LegacyMirrorAdapter` reserved and still uncreated. See §17.0 |
| **D-G1F-3** | The `user_type` gate | Is a seller/landlord Hire Tenant record *correct* to have no canonical blob, or is this a defect? Determines whether G1f-2 preserves or removes it | **3-C** — gate **preserved** for G1f-2; the partial-write failure treated as a **separate defect**. See §6.2, §6.2.1 |
| **D-G1F-4** | Mirror set and encoding | Is `zipCodes` in or out of the managed mirror set? Is the `state` mirror normalised to raw string, accepting a data change for Tenant Criteria records? | **`zipCodes`: (a) for G1f-1** — unmanaged, with a binding future checkpoint (§17.4). **`state`: 4S-i** — raw string (§17.3). Plural `states`: legacy dead write (§17.5) |
| **D-G1F-5** | Criteria controllers in scope | Are W6–W9 part of G1f, or a separate gate? They are canonical writers; leaving them means "sole canonical writer" is false at G1f completion | **APPROVED — all four in scope.** See §22.2.1 |

#### 22.2.1 D-G1F-5 — recorded terms

**All four legacy criteria-controller canonical write paths (W6–W9) are inside the final G1f consolidation
scope.** Recorded:

- they **may be migrated in a later G1f stage** — G1f-7 in §18's sequence;
- **G1f cannot be declared complete while they remain independent canonical writers**, so the §21 guard's
  shrink-only list can reach one entry as designed;
- the migrated-schema `buyer_id` failure makes **`storeAuction` currently unreachable** (§22.5 defect 2);
- **that defect must be separately characterized and scoped before migrating the path**, and
  **must not be silently repaired inside G1f-1**;
- the controller paths' **existing absent / empty / unparseable preservation behaviour must remain
  protected** — it is the approved D-G1-2 rule already in production, now pinned by 14 tests.

**Why this was the most consequential of the five.** D-G1-5's approved text says
*"`LocationDnaPersistenceService` becomes the sole canonical writer."* With W6–W9 excluded, that statement
would be false at the end of G1f, and the §21 guard would have to permanently exempt two controllers —
turning a shrink-only list into one with a permanent floor above one entry. The approval keeps D-G1-5 true.

### 22.3 Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Hydrate-then-rewrite promotes inherited → authored, silently and irreversibly | **highest** | §10.2 constraint + test T4, as a binding stop condition |
| Moving the trait call reverts four Hire workflows to component-property semantics | high | GAP 2 characterisation before G1f-1 |
| First canonical write flips a record S1 → S2 irreversibly | high | accepted and intended (§5.5 lazy upgrade); make it visible via token logging (§11.3) |
| Snapshot shape changes silently for new accepted bids | medium | test T9 |
| `BuyerOfferListingEdit`'s 315-call non-atomic window | medium | closed by migration; test T6 |
| Nested transactions in the two edit flows | medium | test T7; sequence them last |
| Concurrent edits to `ux/hire-agent-create-offer-parity` | medium | D-G1-6 sequencing; that branch has zero mirror-line changes, so the risk is mechanical |
| `zipCodes` divergence widens during a long G1f | low | out of scope by decision, recorded not hidden |

### 22.4 Reaffirmed constraints — NOT new decisions

Three items were raised alongside the five decisions and are recorded here as **reaffirmations of already-approved
architecture**, deliberately **not** as new G1f decisions. Recording them as decisions would have created a
second source of truth for rules that D-G1-2, D-G1-3, D-G1-5 and D-G1-6 already settle — the duplicate-authority
failure L1 exists to prevent.

**Reaffirmed constraint 1 — explicit commands only.** `LocationDnaPersistenceService` may persist only
dimensions represented by an explicit `DimensionCommand`, of which there are exactly two — `set` and `clear`.
**Absence means no operation.** No full-document rewrite from hydrated legacy state. No implicit repair. **No
inherited, imported, derived or legacy value may become owner-authored merely because another dimension is
saved.** Source: D-G1-2's approved vocabulary and §5.4 rule 5; mechanised by §10.5 and proven by test T4.

**Reaffirmed constraint 2 — canonical first, mirrors second.** Canonical Location DNA state is written
**first**. Legacy mirrors are **pure derived outputs** from the resulting canonical document. Canonical and
mirror writes belong in **one transaction** where the storage model permits. **A mirror may never override a
present-but-cleared canonical value.** Source: D-G1-5's layer ownership and D-G1-6's approved precedence;
realised by §16.4 steps 10–13 and §17.

**Reaffirmed carried decision — revision token versus `CriteriaHashService`.** D-G1-3 option **3-C remains
controlling**: `LocationDnaRevisionToken` stays separate from `CriteriaHashService`; `CriteriaHashService`
remains **unchanged** in G1f; Bridge cache-key behaviour remains **unchanged** in G1f; and the polygon
vertex-order and clear-invalidation correction remains a **separate, later compatibility increment**. Source:
D-G1-3's Carried Condition 1, restated unchanged at G1c, G1d and G1e. See §12.

### 22.5 Defect boundaries — separately identified, none authorized for repair here

Four boundaries are recorded distinctly so that no one of them is silently absorbed into a consolidation
increment. **None is repaired by this document, and none is authorized for repair.**

| # | Defect boundary | Proven by | Gates |
|---|---|---|---|
| **1** | ~~**Empty or unknown Tenant Hire `user_type` causes partial persistence.** Mirrors are written at `:4282`-`:4285`, the save raises at `:4689`, and no transaction protects the operation~~ · **CLOSED by G1f-2 (`d3123fb94`)** | was `test_an_unrecognised_user_type_leaves_a_partial_write`; now `test_an_unrecognised_user_type_writes_no_location_dna_at_all` plus `G1f2TenantAgentAuctionMigrationTest::test_an_unsupported_user_type_issues_zero_location_dna_writes` | **Closed within** the Tenant Hire migration, under that increment's own scoped authorization, and **not bundled** with removal of the `user_type` gate — the gate is preserved verbatim. See §28.3 |
| **2** | **`storeAuction` cannot reach its canonical write under the migrated schema** — `buyer_criteria_auctions.buyer_id` is NOT NULL with no default and the controller never sets it | `test_w6_cannot_reach_its_canonical_write_against_the_migrated_schema` | G1f-7. Must be separately characterized and scoped before W6 is migrated. **Must not be silently repaired inside G1f-1**, and must not be assumed operational |
| **3** | **`updateAuction` emits overlapping `state` / `states` keys in incompatible encodings** within one save | `test_buyer_criteria_update_emits_both_state_keys_in_different_encodings` | G1f-7. Disposition of the plural key is §17.5 |
| **4** | **`zipCodes` remains outside the G1f-1 managed mirror set**, so its divergence persists by decision | `G1fZipCodesMirrorCharacterisationTest` (7 tests) | The §17.4 checkpoint, binding before any workflow that writes `zipCodes` is migrated |

**Status after G1f-2.** One of the four is closed. Boundaries **2**, **3** and **4** remain open and none is
authorized for repair: 2 and 3 gate G1f-7, and 4 — `zipCodes` outside the managed mirror set — is the §17.4
checkpoint, which G1f-2 explicitly declined to decide (§28.4) and which remains **required before G1f can be
declared complete**.

**Declared boundary on defect 2.** It is measured against the schema **as migrated**, on the SQLite test
connection — the same class of environment-dependent result as the recorded `ILIKE` and
`pg_try_advisory_lock` failures. This report cannot determine whether the live PostgreSQL database has
acquired defaults for those columns outside the migration history. What is proven is narrow and sufficient:
against the schema this repository declares, W6 is unreachable, so G1f must not be the increment that
discovers it.

---

## 23. Exact implementation authorization recommended for G1f-1

**Recommended authorization — narrow, and staged behind one characterisation increment.**

### Stage A — characterisation only — **COMPLETE (`b91d5e7a2`)**

- **Authorized:** add `tests/Feature/Spatial/G1fHireDoubleWriteCharacterisationTest.php` implementing
  exactly the two tests in §20.5 GAP 2.
- **Authorized production surfaces:** **none.** Tests only.
- **Stop condition:** both tests green; zero production files touched.
- **Rollback:** delete the file.

**Delivered, and wider than the minimum by authorization.** All four gaps were closed rather than GAP 2
alone: four suites, **35 tests**, all passing, zero production files touched, zero existing tests modified.
**Stop condition met.** See §20.5 and §25.

### Stage B — G1f-1 proper — **AUTHORIZED AND IMPLEMENTED (`5c38fc574`)**

Stage A was green, its precondition discharged, and Stage B was subsequently authorized and delivered. The
full implementation record — scope, contracts, proofs and boundaries — is **§27**.

Both terms flagged below as changed by the approvals were honoured: the projection is named
`LegacyMirrorProjection`, `LegacyMirrorAdapter` remains uncreated with its guard assertion retained, and the
projection manages `cities` / `counties` / `state` only, with `state` emitted as a raw string.

**Two terms below are changed by the approvals and supersede the Stage B text as originally written:**

- the mirror projection component is named **`LegacyMirrorProjection`** per D-G1F-2 2-A, and
  **`LegacyMirrorAdapter` remains uncreated** — so the G1c inertness-guard assertion naming it is **retained,
  not removed**, contrary to §21.2's third row as first written;
- the projection manages `cities`, `counties` and `state` only. **`zipCodes` is excluded** per D-G1F-4 (a),
  and `state` is emitted as a **raw string** per 4S-i.

**Authorized production surfaces — exhaustive:**

| Path | Change |
|---|---|
| `app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php` | new |
| `app/Services/LocationDna/Persistence/LocationDnaWritableRecord.php` | new — the port interface |
| `app/Services/LocationDna/Persistence/LegacyMirrorProjection.php` | new (name subject to D-G1F-2) |
| `app/Services/LocationDna/Persistence/PatchResult.php` | new |
| `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php` | **the only** existing production file modified — route its save through the service |

**Authorized test changes — exhaustive:**

| Path | Change |
|---|---|
| `tests/Unit/Services/LocationDna/Persistence/*` | new — T1–T10 (§20.2) plus the §21 guard |
| `tests/Feature/Offers/OfferWorkflowReadinessTest.php` | **+N exact literal paths only** — no wildcard, no matching-logic change |
| `tests/Unit/Services/LocationDna/Contract/G1cContractCoreInertnessGuardTest.php` | the three narrow amendments in §21.2 |
| `tests/Feature/Spatial/G1aWorkflowPersistenceMatrixCharacterisationTest.php` | the migrated workflow's clear assertion only, 6/2 → 7/1 |

**Explicitly NOT authorized:**

Any provenance recording or persistence · any legacy mirror repair · any read-side mirror fallback change ·
`LegacyMirrorAdapter` under that name (pending D-G1F-2) · revision-token gating · the other seven workflows ·
the four criteria-controller writes · `HasSearchAreas` (the shim is G1f-5) · `CriteriaHashService` ·
`PublicGeometryProjection` · the retained snapshot · `summary_json` · any Blade view · any JavaScript ·
any migration, config file, route, model, observer or service provider · any `zipCodes` derivation ·
any `state` encoding normalisation.

**Binding stop conditions:**

1. Test T4 green — a dimension with no command is neither written nor mirror-rewritten. **If T4 cannot be
   made green, stop: the §10.2 provenance-safety constraint has failed and G1f-1 is not viable without
   D-G1F-1.**
2. The seven unmigrated workflows' characterisation **unchanged**, byte-for-byte.
3. `test_full_clear_cycle_survives_only_because_the_cities_mirror_is_also_cleared` still green.
4. The three G0.1 public-geometry suites still green (35 tests).
5. `G1bCriteriaHashCharacterisationTest` (21) and `G1bAcceptedBidDocumentCharacterisationTest` (9) unchanged.
6. Any need to touch a file outside the authorized list → **stop and report**.
7. Any observable page behaviour change beyond D-G1-4's intended clear semantics → **stop and report**.

**Rollback boundary:** a single revert of the G1f-1 commit. No data migration occurs, no schema changes, and
records written through the new service remain readable by the seven unmigrated writers — because the
serializer's output is a superset-compatible canonical document, and the hydrator's tolerance is proven.
**One asymmetry to record:** a record written by the service carries `schema_version: 2` permanently, and a
revert does not remove it. Reverting restores the old writer, which ignores the field entirely (F-G1B-2), so
the record continues to work — but the S1→S2 transition is not rolled back by a code revert.

---

## 24. What this report did not do

**As originally authored:** no production code, test or configuration was created, modified or deleted, and
no characterisation test was written for any of the four gaps in §20.5.

**Across both increments — the characterisation commit `b91d5e7a2` and this documentation commit — the
following remains true:**

- **No production code was created, modified or deleted.** `LocationDnaPersistenceService` was not created.
  `LegacyMirrorProjection` was not created. **`LegacyMirrorAdapter` was not created** and remains
  authorization-gated.
- **No existing test was modified.** The characterisation increment added four new files and changed nothing
  else; this documentation increment changes one Markdown file and nothing else.
- No workflow was wired. No trait, controller, model, route, view, migration or configuration changed.
- No snapshot was read, written, deleted or re-projected. `CriteriaHashService`, `PublicGeometryProjection`
  and the retained snapshot were not touched, so the D-G1-3 Bridge deferral and the D-G1-7 reader guard both
  stand.
- **No defect surfaced by the characterisation was repaired** — all four are recorded in §22.5 and none is
  authorized for repair.
- D-G1-1 … D-G1-7 remain **APPROVED and unmodified**; none was reopened. D-G1F-1 … D-G1F-5 are now
  **APPROVED** (§22.2), and approval is **not** authorization to implement.

**Superseded by `5c38fc574`.** The statements above describe the state through the decisions commit. G1f-1
has since been authorized and implemented: nine production classes exist, `BuyerAgentAuction` is migrated,
and §27 records the scope exactly.

**What remains true after G1f-1:** `LegacyMirrorAdapter` was not created · no snapshot was read, written,
deleted or re-projected · `CriteriaHashService`, Bridge and `PublicGeometryProjection` were not touched ·
no provenance was persisted · no migration, configuration, route, view, controller or model changed · no
defect from §22.5 was repaired · **seven of the eight workflows remain unmigrated** · **G1f-2 and later are
not started** · **G1g is not started**.

**This documentation increment itself changed no production code and no test.**

---

## 25. Validation

Regression suite executed at `994270a04` before this document was written, on the branch, in the primary
workspace:

| Group | Suites | Tests | Result |
|---|---|---|---|
| G1c contract core | 8 | 128 | pass |
| G1d capability resolver | 5 | 70 | pass |
| G1e provenance model | 5 | 88 | pass |
| G1a characterisation | 5 | 47 | pass |
| G1b characterisation | 2 | 30 | pass |
| G0.1 public geometry | 3 | 35 | pass |
| SearchAreas / mirror contract | 7 | 83 | pass |
| Offer workflow readiness | 1 | 10 | pass |
| `SearchAreasStateCountyRoundTripTest` | 1 | 4 | **3 pass, 1 fail — pre-existing** |
| `LazyBridgeImportServiceTest` | 1 | 22 | **20 pass, 2 fail — pre-existing** |
| **Total** | **38** | **517** | **514 pass, 3 fail** |

**All three failures are the documented pre-existing environment failures**, reproduced identically and
unrelated to Location DNA:

- `SearchAreasStateCountyRoundTripTest` — 1 of 4:
  `SQLSTATE[HY000]: General error: 1 near "ILIKE": syntax error`, from a PostgreSQL-only operator running
  against the SQLite test connection. Recorded as G1 report §6.3 and G1b F-G1B-9.
- `LazyBridgeImportServiceTest` — 2 of 22: `pg_try_advisory_lock`, same environment class. Recorded as the
  second instance under F-G1B-9.

**That increment added one documentation file and changed no code, so no test outcome changed.**

### 25.1 Re-validation after the characterisation increment (`b91d5e7a2`)

The full set was re-executed on the branch after the four G1f suites landed, and again before this
documentation commit.

| Group | Suites | Tests | Result |
|---|---|---|---|
| **G1f characterisation — new** | **4** | **35** | **pass** |
| G1c contract core | 8 | 128 | pass |
| G1d capability resolver | 5 | 70 | pass |
| G1e provenance model | 5 | 88 | pass |
| G1a characterisation | 5 | 47 | pass |
| G1b characterisation | 2 | 30 | pass |
| `CriteriaHashServiceTest` | 1 | 12 | pass |
| G0.1 public geometry | 3 | 35 | pass |
| SearchAreas / mirror contract | 7 | 83 | pass |
| Offer workflow readiness | 1 | 10 | pass |
| `SearchAreasStateCountyRoundTripTest` | 1 | 4 | **3 pass, 1 fail — pre-existing** |
| `LazyBridgeImportServiceTest` | 1 | 22 | **20 pass, 2 fail — pre-existing** |
| **Total** | **43** | **564** | **561 pass, 3 fail** |

**The three failures are unchanged in count, identity and cause** — `near "ILIKE": syntax error` and
`pg_try_advisory_lock`, both PostgreSQL-only SQL against the SQLite test connection, both reproduced
identically in the G0.1 control worktree with no G1 file present.

**Per-suite counts for the new increment:** `G1fHireDoubleWriteCharacterisationTest` 7 ·
`G1fHireTenantUserTypeGateCharacterisationTest` 7 · `G1fZipCodesMirrorCharacterisationTest` 7 ·
`G1fLegacyCriteriaControllerCharacterisationTest` 14. `php -l` clean on all four; `git diff --check` clean.

**Every pre-existing suite retained its exact prior count**, including the two tripwires that G1f will later
change by design (`TenantOfferCitiesMirrorTest` 15) and the four inertness guards — so the characterisation
increment demonstrably wired nothing.

### 25.2 Re-validation after the G1f-1 implementation (`5c38fc574`)

| Group | Tests | Result |
|---|---|---|
| **New G1f-1 suites** | **57** | pass |
| G1f characterisation (4 suites) | 36 | pass |
| G1a characterisation | 47 | pass |
| G1b characterisation | 30 | pass |
| G1c contract core | 128 | pass |
| G1d capability resolver | 70 | pass |
| G1e provenance model | 88 | pass |
| `CriteriaHashServiceTest` | 12 | pass |
| Offer workflow readiness | 10 | pass |
| SearchAreas / mirror contract | 83 | pass |
| G0.1 public geometry | 35 | pass |

**G1f characterisation is 36 rather than 35** because `G1fHireDoubleWriteCharacterisationTest` gained the
positive assertion that `BuyerAgentAuction`'s duplicate write is gone, replacing the behavioural row that
component no longer needs (§27.8).

#### Known pre-existing failures — unchanged

- `SearchAreasStateCountyRoundTripTest` — 1 of 4, SQLite `ILIKE`.
- `LazyBridgeImportServiceTest` — 2 of 22, PostgreSQL `pg_try_advisory_lock`.

#### Newly measured, and also pre-existing — added to the standing baseline

**`ImportantPlacesRoundTripTest` — 4 of 5 fail.** Measured for the first time during G1f-1 because it was
not previously in the regression list.

- Same **SQLite `ILIKE`** defect, raised from `BuyerOfferListingEdit::getPlaceSuggestions()` at `:976`-`:977`
  via `:980` — the identical production line as the `SearchAreasStateCountyRoundTripTest` failure.
- **`BuyerOfferListingEdit` was not modified by G1f-1**, confirmed against the commit's file list.
- **The suite contains no G1f-1 production reference** and no Location DNA reference at all.
- **It is not caused by this migration.**

**Added to the standing regression baseline for later workflow migrations**, so the next increment measures
it from a known state rather than rediscovering it. It also raises the count of distinct suites affected by
the one `ILIKE` defect to **three**, which strengthens the case for treating that defect as environment
work rather than per-suite noise.

### 25.3 Re-validation after the G1f-2 implementation (`d3123fb94`)

| Group | Tests | Result |
|---|---|---|
| **New G1f-2 suites** | **33** | pass |
| G1f-1 suites | 57 | pass |
| G1f characterisation (4 suites) | 37 | pass |
| G1a characterisation | 47 | pass |
| G1b characterisation | 30 | pass |
| `tests/Unit/Services/LocationDna` (G1c 128 · G1d 70 · G1e 88 · persistence · guards) | 925 | pass |
| `CriteriaHashServiceTest` | 12 | pass |
| Offer workflow readiness | 10 | pass |
| `HireSearchAreasParityTest` | 9 | pass |
| SearchAreas / mirror contract | 63 | pass |
| G0.1 public geometry | 35 | pass |

**G1f characterisation is 37 rather than 36** because `G1fHireDoubleWriteCharacterisationTest` gained the
positive assertion that `TenantAgentAuction`'s duplicate write is gone, replacing the behavioural row that
component no longer needs — the same trade G1f-1 made for `BuyerAgentAuction` (§28.8).

**`HireSearchAreasParityTest` is listed separately and passes UNMODIFIED.** It is on §20.1's must-not-change
list and it exercises this component tree, so it was the sharpest available check. Its two write-back tests
drive `TenantAgentAuctionEdit` — which G1f-2 did not migrate — and its three `TenantAgentAuction` tests are
render-only, so the migration is correctly invisible to it.

**`G1f1BuyerAgentAuctionMigrationTest` — 13 tests, unmodified, passing.** The first migration is unaffected
by the second, which is the property the one-workflow-per-commit sequence exists to preserve.

#### Known pre-existing failures — unchanged

- `SearchAreasStateCountyRoundTripTest` — 1 of 4, SQLite `ILIKE`.
- `ImportantPlacesRoundTripTest` — 4 of 5, the same SQLite `ILIKE` defect.
- `LazyBridgeImportServiceTest` — 2 of 22, PostgreSQL `pg_try_advisory_lock` unavailable in SQLite.

#### Newly measured, and also pre-existing — added to the standing baseline

**`PropertyLocationPoiVersionColumnsTest` — 2 of 2 fail.** Measured for the first time during G1f-2 because
the whole `tests/Unit/Services/LocationDna` directory was run rather than named suites.

- Failure is `SQLSTATE[HY000]: General error: 1 no such table: property_location_pois`, raised from the
  suite's own `setUp()`, which queries the table without `RefreshDatabase` or `DatabaseMigrations`.
- **It fails in isolation**, with none of G1f-2's changed files loaded.
- It concerns the POI table; **G1f-2 introduced no migration and touched no model or schema**.
- **It is not caused by this migration.**

**Added to the standing regression baseline**, bringing the standing set to four suites. It is a database
setup dependency rather than an `ILIKE` instance, so it is recorded as its own class.

### 25.4 Re-validation after the G1f-3 implementation (`a17d4cb14`)

| Group | Tests | Result |
|---|---|---|
| **New G1f-3 suites** | **33** (19 + 14) | pass |
| G1f-2 suites | 33 (22 + 11) | pass |
| G1f-1 suites | 57 (13 + 21 + 10 + 13) | pass |
| G1f characterisation (4 suites) | 37 (9 / 7 / 7 / 14) | pass |
| G1a characterisation (5 suites) | 47 (12 / 10 / 6 / 12 / 7) | pass |
| G1b characterisation | 30 (21 / 9) | pass |
| `tests/Unit/Services/LocationDna` (G1c 128 · G1d 70 · G1e 88 · persistence · guards) | **939** | pass |
| `CriteriaHashServiceTest` | 12 | pass |
| Offer workflow readiness | 10 | pass |
| `HireSearchAreasParityTest` | 9 | pass |
| SearchAreas / mirror contract | 63 (9 / 16 / 15 / 8 / 15) | pass |
| G0.1 public geometry | 35 (15 / 13 / 7) | pass |

**The `tests/Unit/Services/LocationDna` total rose 925 → 939** because G1f-3 added the 14-test boundary
guard to that tree.

#### Known pre-existing failures — unchanged, and none is a G1f-3 regression

| Suite | Failures | Cause |
|---|---|---|
| `SearchAreasStateCountyRoundTripTest` | 1 of 4 | SQLite `ILIKE` |
| `ImportantPlacesRoundTripTest` | 4 of 5 | the same SQLite `ILIKE` defect |
| `LazyBridgeImportServiceTest` | 2 of 22 | PostgreSQL `pg_try_advisory_lock` unavailable under SQLite |
| `PropertyLocationPoiVersionColumnsTest` | 2 of 2 | `property_location_pois` absent when the suite runs without database setup |

**No suite moved into or out of this set at G1f-3**, and the counts are identical to §25.3's.

---

## 26. Amendment record

| Amendment | Commit | Change |
|---|---|---|
| Original report | `751392600` | Pre-implementation audit as authored. All five decisions open; four characterisation gaps declared with a STOP; no tests written |
| **Characterisation** | `b91d5e7a2` | Tests only — four suites, 35 tests. Closed all four gaps. Corrected this report twice (`states` plural; `updateAuction`'s double mirror write) and surfaced two defects the audit had not seen (partial persistence on unknown `user_type`; `storeAuction` unreachable) |
| **Decisions approved** | `8295bb42d` | **D-G1F-1 … D-G1F-5 APPROVED** (§22.2) with recorded terms in §10.5, §17.0, §17.3, §17.4, §17.5, §6.2 and §22.2.1. Three items recorded as **reaffirmed constraints, not new decisions** (§22.4). All four gaps marked **CLOSED** (§20.5); §22.1 blockers B1–B3 marked **RESOLVED**. New **§22.5** records four defect boundaries, none authorized for repair. §23 Stage A marked **COMPLETE** and Stage B marked **UNBLOCKED, NOT AUTHORIZED, NOT STARTED**. §25.1 records re-validation |

| **G1f-1 implementation** | `5c38fc574` | **First workflow migrated.** Nine new production classes in `App\Services\LocationDna\Persistence`, one existing production file changed (`BuyerAgentAuction`), four new test suites (57 tests), six existing tests narrowly amended. See §27 |
| **G1f-1 reconciliation** | `4c1dea947` | Documentation only. Records the implementation (§27), amends **F-G1F-10** into its post-implementation form (§27.7) and narrows it, updates the rollout status table, and adds `ImportantPlacesRoundTripTest` to the standing pre-existing-failure baseline (§25.2) |
| **G1f-2 implementation** | `d3123fb94` | **Second workflow migrated.** **No new production class**; one existing production file changed (`TenantAgentAuction`), reusing the G1f-1 boundary unmodified. Two new test suites (33 tests), four existing tests narrowly amended. Closes defect boundary 1. See §28 |
| **G1f-2 reconciliation** | `6d4289a46` | Documentation only. Records the implementation (§28), marks defect boundary 1 **CLOSED** (§22.5), updates §18 and the rollout status table, adds `PropertyLocationPoiVersionColumnsTest` to the standing pre-existing-failure baseline (§25.3), and marks §27 as historical rather than rewriting it |
| **G1f-3 readiness** | `6988469c7` | Documentation only. Adds **§29** — the Buyer Offer write inventory, create/edit comparison, B6 evidence and recommendation, byte-serialization survey, proposed scope, test plan and blocker re-evaluation. Raises F-G1F-13 and F-G1F-14. Authorizes nothing |
| **G1f-3 implementation** | `a17d4cb14` | **Third and fourth workflows migrated, together.** **No new production class**; two existing production files changed (both Buyer Offer copies). Two new test suites (33 tests), six existing tests narrowly amended. **Resolves B4**, executes **B6 option B**, and shortens the §21 direct-writer list for the first time (7 → 5). See §30 |
| **G1f-3 reconciliation** | *this commit* | Documentation only. Records the implementation (§30), marks **B4 RESOLVED** and **B6 DECIDED-AND-EXECUTED** (§22.1), **withdraws F-G1F-13** as a false-positive prediction, adds **F-G1F-15** (a ninth tripwire) and **F-G1F-16** (a guard-recursion defect), updates §18 and the rollout table, and adds §25.4 |

**The first three amendments authorise no gate, migrate no workflow and touch no production code.** The
fourth — `5c38fc574` — is the first that does, and it migrates exactly one workflow under §23's authorization;
`d3123fb94` migrates exactly one more under its own. Every approval is a contract term; every closure is
backed by a named executed test.

---

## 27. G1f-1 implementation record

**Implementation commit:** `5c38fc5745d2b6a7ee63e7c6f0b048e6acc61a69`
**Subject:** `feat(location-dna): migrate BuyerAgentAuction writer`
**Date:** 2026-08-02 · **Parent:** `8295bb42d`

| Scope | Status |
|---|---|
| **G1f-1 · `BuyerAgentAuction` migration** | **IMPLEMENTED** |
| G1f-2 and every later stage | **NOT STARTED** *(as at `5c38fc574`; G1f-2 has since been implemented — §28)* |
| G1g adapter contract | **NOT STARTED** |

> **Read this section as of `5c38fc574`.** It is the record of the FIRST migration and is deliberately not
> rewritten by later increments. Where it says `BuyerAgentAuction` is the only migrated workflow, or counts
> seven unmigrated ones, that was true at this commit; **§28 supersedes those counts**. Everything else here
> — the service contract, the projection contract, the no-op and promotion boundaries, F-G1F-10 — is still
> current, and G1f-2 changed none of it.

**`BuyerAgentAuction` was the only migrated workflow at this commit.** The other seven canonical writers were
unchanged and wrote exactly as they did before, which `G1f1MigrationBoundaryGuardTest` asserts as a standing
invariant — a shrink-only invariant that G1f-2 narrowed by one entry rather than weakened.

### 27.1 Exact production scope

**New namespace:** `App\Services\LocationDna\Persistence` → `app/Services/LocationDna/Persistence/`. A
fourth sibling to `Contract` (G1c), `Capability` (G1d) and `Provenance` (G1e), under the same
`app/Services/<Area>/<Purpose>/` convention.

**Nine new production types:**

| Type | Responsibility |
|---|---|
| `LocationDnaPersistenceService` | the canonical writer |
| `LegacyMirrorProjection` | pure write-side mirror derivation (D-G1F-2 2-A) |
| `LocationDnaCommandBuilder` | payload → explicit commands; where "absence means no operation" lives |
| `OwnerPrivateLocationDnaWriter` | the workflow seam; assembles context, capabilities, commands, record |
| `LocationDnaWritableRecord` | the three-method write port |
| `MetaKeyedRecord` | the only persistence-touching class; wraps `info()`/`saveMeta()` |
| `CanonicalMetaKey` | names the meta key once, taken from the contract so it cannot drift |
| `PatchResult` | explicit result — returned, never thrown, for every expected outcome |
| `PersistenceOutcome` | closed enum: `Changed` / `NoOp` / `Rejected` |

**Existing production file changed — exactly one:**
`app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php`.

**Not changed by this increment:** any migration, configuration file, route, view, controller, model,
observer, policy or service provider. No schema change. No backfill.

### 27.2 The service contract, as implemented

```
LocationDnaPersistenceService::apply(record, commands, capabilities, actor) -> PatchResult
```

The implemented sequence, in order:

| # | Step |
|---|---|
| 1 | An empty command batch returns **before reading stored state** |
| 2 | Capability checks occur **before any read or write** |
| 3 | Every command is validated through `maySet` / `mayClear` |
| 4 | The provenance transition is evaluated **transiently** |
| 5 | Existing canonical state is hydrated |
| 6 | Malformed and unsupported higher-version documents are **rejected** |
| 7 | Commands are applied through the G1c contract layer |
| 8 | `LocationDnaRevisionToken` determines whether the change is **semantic** |
| 9 | No semantic change returns **no-op** — nothing is written |
| 10 | Canonical state is written **first** |
| 11 | Managed mirrors are derived through `LegacyMirrorProjection` |
| 12 | Canonical and mirror writes occur in **one transaction** |
| 13 | The result reports `changed` / `no-op` / `rejected`, plus document and revision token |

**The service does not:** read HTTP requests · inspect global `Auth` · inspect Livewire dirty state ·
accept arbitrary string dimensions · repair legacy state · persist provenance · access snapshots · invoke
`CriteriaHashService` · invoke Bridge · perform public projection.

Several of those are asserted, not merely stated:
`G1f1MigrationBoundaryGuardTest::test_the_service_reads_no_request_auth_or_livewire_state` and
`::test_the_persistence_namespace_touches_no_deferred_seam`.

### 27.3 `LegacyMirrorProjection` contract, as implemented

**Pure · write-side only · canonical-document input · mirror-map output.** It cannot read existing mirrors,
repair, persist or determine provenance — it has no collaborators through which to do any of it.

| Managed mirror (G1f-1) | Encoding |
|---|---|
| `cities` | JSON-encoded |
| `counties` | JSON-encoded |
| `state` | **raw string** (D-G1F-4 4S-i) |

- **Clear behaviour:** present-but-cleared produces a **cleared** mirror — `[]` for collections, `''` for
  `state`. This is D-G1-4 4-A finally taking effect for `counties` and `state`.
- **Absence behaviour:** absent dimensions are **omitted from the output entirely**, so the caller writes no
  key. This is what prevents a save from overwriting a legacy mirror it knows nothing about.

**Explicitly recorded:** `zipCodes` **remains unmanaged** (D-G1F-4 (a)) · the plural `states` key is
**not emitted** (§17.5) · **`LegacyMirrorAdapter` remains uncreated**, and the G1c/G1d guard assertions
naming it are retained rather than removed.

### 27.4 `BuyerAgentAuction` integration

The component now:

- builds explicit commands using **`array_key_exists` presence semantics**, never `empty()`;
- **emits no command for absent input**;
- maps a present non-empty value to **`set`**;
- maps a present empty value to **`clear`**;
- treats `null`, `false`, the empty string and an unparseable payload as an **empty command batch**;
- resolves the **owner-private edit** capability context **server-side**, never from client input;
- uses **transient `ExplicitOwner`** provenance, which is validated and discarded;
- performs **one canonical write and one derived write per managed mirror**;
- **no longer performs the former duplicate mirror write**;
- preserves **raw-string `state`** behaviour, so its observable encoding is unchanged;
- **adds no `zipCodes` behaviour**;
- leaves **all unrelated metadata behaviour unchanged**.

The component references only the `Persistence` namespace — never `Contract`, `Capability` or `Provenance` —
so the inertness guards for those three still hold against all eight workflow components.

`HasSearchAreas` was **not** modified and is **not** globally rewired; the three remaining trait hosts still
call `saveSearchAreas()` unchanged.

### 27.5 Transaction and rollback proofs

**Canonical and managed mirror writes are atomic**, in one `DB::transaction`.

**A non-vacuity failure found and corrected during implementation, recorded rather than quietly fixed:**

- the original rollback test was **initially vacuous** — it asserted a `rolledBack` flag that the in-memory
  fake set on itself, so it proved nothing about the transaction;
- it was replaced with **database-state assertions against a real record**, reading back through the model
  after the induced failure;
- **removing the transaction then caused the rollback test to fail**, which is the proof the first version
  could never have given;
- the mutation probe was removed and the source **restored byte-exact**, verified by `git hash-object`.

**The four mutation probes, all applied and all removed:**

| # | Probe | Result |
|---|---|---|
| 1 | Remove the service invocation from `BuyerAgentAuction` | migration tests fail (7 of 13) |
| 2 | Restore the duplicate mirror write | query-count test and double-write guard fail |
| 3 | Remove the transaction | rollback test fails |
| 4 | Permit a full-document rewrite (early return, token check and seam guard all disabled) | legacy no-op and no-promotion tests fail |

**All probe residue was removed**, verified by a repository-wide sweep of `app/` and `tests/`, and both
mutated source files were confirmed byte-identical to their pre-probe state by content hash.

Probe 4 additionally established that the no-op protection is **layered**: the seam returns early, the
service returns early, and the revision-token comparison independently suppresses a meaningless write. All
three had to be disabled before a legacy-only record would acquire a blob.

### 27.6 No-op and promotion boundaries — the binding behaviour

A commandless save:

- **does not read or rewrite** the canonical document;
- **does not write** mirrors;
- **does not change** the revision token;
- **does not create** a canonical blob for a legacy-only record;
- **does not repair** legacy mirrors;
- **does not promote** inherited, imported, derived or legacy values.

**This remains the condition under which provenance persistence stays deferred.** D-G1F-1's approval is
conditional on exactly these properties, and they are now proven by executed tests rather than by design
intent — see `test_an_empty_batch_writes_nothing_and_reports_a_no_op`,
`test_a_commandless_save_creates_no_blob_for_a_legacy_only_record`,
`test_a_no_op_save_on_a_legacy_only_record_creates_no_blob_and_preserves_the_mirror` and
`test_a_legacy_recovered_value_is_not_promoted_to_authored`.

### 27.7 F-G1F-10 — First authored write canonicalizes serialization

**Amended, not added.** F-G1F-10 was first recorded in §11.3 as *"the first canonical write is a one-way
S1→S2 door per record"*. Implementation narrowed it: the door is real, but it opens **only on a semantic
change**, which the pre-implementation wording did not capture. This subsection is the governing form.

**Observed behaviour:**

- the first **semantic** Location DNA write through the migrated writer emits **`schema_version: 2`**;
- the serializer emits **deterministic canonical key ordering**;
- **byte-for-byte identity with a legacy serialized blob is not preserved**;
- **semantic equality IS preserved** according to the G1c canonical contract;
- the change occurs **only when at least one explicit `set` or `clear` command produces a semantic document
  change**;
- **a no-command save does not trigger this rewrite**;
- **a semantic no-op does not trigger this rewrite** — the revision-token comparison suppresses it;
- the behaviour is **intentional** under the approved `schema_version` (§5.5) and serializer contracts, and
  §5.3 withdrew the byte-compatibility guarantee in favour of G-C1–G-C4.

**Compatibility consequence:**

- once written through G1f, **that record should not be expected to return to its prior byte
  representation**;
- **rollback means reverting workflow adoption for future writes**, not reconstructing the previous
  serialized byte layout;
- **readers must rely on semantic shape, not byte identity**;
- **no bulk rewrite or backfill is authorized.**

**Stop condition for G1f-2.** Before migrating the next workflow, **verify that its consumers and tests do
not depend on byte-identical blob serialization.** This is not hypothetical: it is exactly what made two
assertions in `G1aWorkflowPersistenceMatrixCharacterisationTest` change for `BuyerAgentAuction`, and the
next workflow's dependants have not been surveyed.

**The approved `schema_version` decision is not reopened, and serializer behaviour is unchanged by this
documentation increment.**

### 27.8 Test and guard changes

**Four new test files, 57 tests, all passing:**

| Suite | Tests |
|---|---|
| `tests/Feature/Spatial/G1f1LocationDnaPersistenceServiceTest.php` | 21 |
| `tests/Feature/Spatial/G1f1BuyerAgentAuctionMigrationTest.php` | 13 |
| `tests/Unit/Services/LocationDna/Persistence/LegacyMirrorProjectionTest.php` | 13 |
| `tests/Unit/Services/LocationDna/Persistence/G1f1MigrationBoundaryGuardTest.php` | 10 |

**Six existing tests modified, each narrowly:**

| Test | Change |
|---|---|
| `OfferWorkflowReadinessTest` | **nine exact production paths** added to the allowlist. **No wildcard was added**; the scan roots, matching algorithm and assertion behaviour are unchanged |
| `G1cContractCoreInertnessGuardTest` | one explicit `Persistence` entry added to `DOMAIN_DIRS` |
| `G1dCapabilityInertnessGuardTest` | one explicit `PERSISTENCE_DIR` exemption, named as a constant |
| `G1eProvenanceInertnessGuardTest` | one explicit `PERSISTENCE_DIR` exemption, named as a constant |
| `G1fHireDoubleWriteCharacterisationTest` | `BuyerAgentAuction` removed from the double-write **behavioural set** and replaced with a **positive assertion that the duplicate write is gone**. The other two components are exercised exactly as before |
| `G1aWorkflowPersistenceMatrixCharacterisationTest` | a `migrated_save` flag marks the migrated workflow and skips it from the **two save-side** tests only |

**Each inertness guard received only the explicit `Persistence` sibling namespace G1f-1 required.** No
wildcard was introduced anywhere, and no future namespace or workflow was pre-authorized — a later domain
namespace must still be added under its own authorization.

**The persistence matrix still covers all eight load paths.** Only the migrated **save** path is marked
migrated and skipped from the pre-consolidation save-side characterisation; every load-side assertion still
runs against all eight workflows.

### 27.9 Rollout status — as at `5c38fc574`, superseded by §28.10

| Stage | Status |
|---|---|
| G1f audit and decisions | **COMPLETE** |
| G1f characterisation gaps | **CLOSED** |
| **G1f-1 · `BuyerAgentAuction`** | **IMPLEMENTED** — `5c38fc574` |
| G1f-2 | **NOT STARTED** *(implemented since — §28)* |
| G1f-3 | **NOT STARTED** |
| G1f-4 | **NOT STARTED** |
| Legacy criteria-controller migration | **NOT STARTED** |
| Direct-writer guard closeout | **NOT STARTED** |
| G1g | **NOT STARTED** |

**`BuyerAgentAuction` was the only migrated workflow at this commit.** The §21 direct-writer guard listed
**seven** remaining direct writers; the list is shrink-only and reaching one entry is G1f's completion
condition. **The current rollout table is §28.10.**

---

## 28. G1f-2 implementation record

**Implementation commit:** `d3123fb94d530411a9422c2eef57875b21ec041d`
**Subject:** `feat(location-dna): migrate TenantAgentAuction writer`
**Date:** 2026-08-03 · **Parent:** `9716eb885`

| Scope | Status |
|---|---|
| **G1f-2 · `TenantAgentAuction` migration** | **IMPLEMENTED** |
| G1f-3 and every later stage | **NOT STARTED** |
| G1g adapter contract | **NOT STARTED** |

**Two workflows are now migrated — `BuyerAgentAuction` and `TenantAgentAuction`.** The remaining six
canonical write paths are unchanged and write exactly as they did before.

**Parent note.** `9716eb885` is an unrelated workspace-preservation commit (`.replit` and a 2B-3 cities
audit script) that landed on this branch after the G1f-1 reconciliation. It changed no Location DNA
production file, test or document — verified by an empty `git diff` over `app/Services/LocationDna`,
`BuyerAgentAuction.php`, `tests/Feature/Spatial`, `tests/Unit/Services/LocationDna` and this report — so
the G1f-1 baseline G1f-2 builds on is `4c1dea947`'s tree unmodified.

### 28.1 Exact production scope

**One existing production file changed. No new production class.**

| File | Change |
|---|---|
| `app/Http/Livewire/TenantAgentAuction.php` | +122 / −11 — one import, two new protected methods, the Location Information block rewritten |

**The `App\Services\LocationDna\Persistence` namespace is byte-identical to G1f-1's.** No class was added,
removed or edited, proven by an empty `git diff 9716eb885 d3123fb94 -- app/Services/LocationDna/`. This was
the intended shape: G1f-1 built the boundary and G1f-2 is the first evidence that it generalises to a second
workflow **without needing to change**. A boundary that had to be widened for its second consumer would have
been a finding; it was not.

**What was added to the component:**

- `SUPPORTED_USER_TYPES` — a private constant naming the four roles the component is built for. Not a
  Location DNA concept; it is the vocabulary `loadDraft()`, every `match ($this->user_type)` model map and
  the four `*_specific` compatibility keys already depend on.
- `assertLocationDnaUserTypeIsSupported()` — the Location DNA write path's precondition (§28.3).
- `persistLocationDna($auction)` — the same one-line seam onto `OwnerPrivateLocationDnaWriter` that
  `BuyerAgentAuction` carries. The component names one class and holds no Location DNA policy: no
  `Dimension`, no `DimensionCommand`, no capability resolution, asserted by
  `G1f2MigrationBoundaryGuardTest::test_the_migrated_component_touches_no_deferred_seam`.

**Nothing else in production was touched.** No config, route, view, controller, model or migration.

### 28.2 `user_type` behaviour — D-G1F-3 option 3-C, as implemented

**The gate is preserved verbatim.** `in_array($this->user_type, ['buyer', 'tenant'])` is textually
unchanged and remains the only route to the canonical writer.

| `user_type` | Canonical document | Managed mirrors | Notes |
|---|---|---|---|
| `buyer` | **written** | derived from the resulting canonical document | one write per managed key |
| `tenant` | **written** | derived from the resulting canonical document | one write per managed key |
| `seller` | **never written** | property-sourced, unchanged | no promotion, no canonicalisation |
| `landlord` | **never written** | property-sourced, unchanged | no promotion, no canonicalisation |
| empty | **rejected before any write** | none | `InvalidArgumentException` |
| unknown | **rejected before any write** | none | `InvalidArgumentException` |

**`buyer` / `tenant`.** The canonical document is written **first**; `cities`, `counties` and `state` are
projected from the RESULTING document by `LegacyMirrorProjection` and written second; both halves are inside
one transaction. Exactly one write per managed key — the double-write is gone.

**`seller` / `landlord`.** The existing canonical-write gate is preserved, so no canonical blob is created,
no existing blob is promoted or canonicalised, and the property-sourced mirrors these roles have always
written remain supported. **These three mirror writes are not the double-write G1f removes**: with the gate
closed the trait never ran, so they are the only mirror writes this branch has ever performed. Removing them
would have silently stopped mirroring location for two of the four roles.

> **This preserves current behaviour. It is not a declaration that blob/mirror divergence for gated roles is
> architecturally ideal forever.** D-G1F-3 3-C's terms are unchanged: the gate may only be removed under a
> separate product/workflow authorization, and F-G1F-4's divergence finding stands.

**empty / unknown.** Rejected with `InvalidArgumentException`, before any Location DNA write. No canonical,
`cities`, `counties`, `state` or `zipCodes` write occurs and every stored value remains byte-identical. The
value is **not** coerced into `buyer`, `tenant`, `seller` or `landlord` — guessing a role would attach one
record's Location DNA to another role's semantics and make the gate act on a value the user never supplied.

### 28.3 Defect boundary 1 — CLOSED

**The defect, as characterised.** `saveAllMetadata()` wrote the discrete `cities` / `counties` / `zipCodes` /
`state` mirrors, then reached the `$this->user_type . '_specific'` role-key lookup, which is undefined for
any value outside the four declared roles and raised there. No transaction protected the earlier writes, so
those mirrors were committed and the remainder of the save never happened. **Partial persistence was
therefore possible on every empty or unrecognised `user_type`.**

**The correction.** `user_type` validation was moved **above** the Location Information write block. An
invalid or unsupported value now fails before any Location DNA persistence, while every Location DNA key
still holds its stored value. Nothing is written, so nothing can be left half-written.

**How it was proven.** Both statement forms and both record states were measured, because neither alone is
decisive:

| Case | Fixture | What a write would look like | Result |
|---|---|---|---|
| A | record with no Location DNA meta | INSERT — binds the meta **key** | zero writes for all five keys |
| B | record with all five keys populated | UPDATE — binds only the new **value** | zero writes; all five values byte-identical afterwards |

**This is an ordering repair, not a gate change.** The `buyer` / `tenant` gate is untouched; what moved is
where the role is validated. The two concerns were deliberately not merged, so that "the gate changed" and
"the ordering changed" remain distinguishable in the history.

### 28.4 `zipCodes` — unchanged, and still outside the managed set

**D-G1F-4 option (a) is carried forward unchanged.** `zipCodes` remains outside `LegacyMirrorProjection`:

- still **property-sourced**, from `$this->zipCodes`;
- still written for supported roles according to the existing workflow behaviour;
- **never** derived from `Dimension::ZipCodes`;
- still absent from `LegacyMirrorProjection::MANAGED_KEYS`, which remains `['cities', 'counties', 'state']`;
- a canonical ZIP clear still does **not** clear the mirror;
- **no normalization and no repair was introduced.**

**The strongest evidence is a null result: all seven tests in `G1fZipCodesMirrorCharacterisationTest` pass
UNMODIFIED.** That suite pins the pre-migration behaviour of this exact component, so its silence means the
behaviour was preserved rather than re-specified around the migration.

**One ordering improvement, no behaviour change.** The `zipCodes` write now occurs **after** the canonical
block rather than before it. A failure inside the canonical write therefore aborts before `zipCodes` can be
advanced, so the writer's all-or-nothing guarantee is not undermined by an unmanaged mirror write that
already landed. An invalid `user_type` produces no `zipCodes` write at all. The written value, its source and
its reach are identical.

**The final managed-mirror decision for `zipCodes` remains DEFERRED.** The §17.4 checkpoint is unchanged and
still **binding before G1f can be declared complete**. G1f-2 was the first increment to migrate a workflow
that writes the key, and it declined to decide it — recorded, not overlooked. It remains defect boundary 4.

### 28.5 Persistence and transaction behaviour

**`TenantAgentAuction` reuses the G1f-1 boundary. No persistence class changed.**

For `buyer` / `tenant`, through `OwnerPrivateLocationDnaWriter` → `LocationDnaCommandBuilder` →
`LocationDnaPersistenceService`:

- explicit per-dimension commands are built from the submitted payload;
- **absence remains no operation** — a key absent from the payload gets no command;
- a present non-empty value becomes **set**;
- a present empty value becomes **clear**;
- the canonical document is written **first**;
- `cities` / `counties` / `state` are projected from the **resulting** canonical document, never from a
  stored mirror;
- all of it occurs in **one transaction**.

**Rollback proof.** The record is passed as a proxy that forwards to the real model but raises on the `state`
mirror write — the LAST managed key the projection emits, so `cities` and `counties` have already been
written inside the transaction when it fires. Afterwards the canonical document, `cities` and `counties` are
all absent: the batch applies wholly or not at all, and no partial managed state remains.

**The `seller` / `landlord` gated paths do not invoke the canonical persistence service at all.** They never
construct a command, never open the writer's transaction and never reach the projection.

### 28.6 No-op and promotion guarantees

Every G1f-1 guarantee holds unchanged for the second workflow. An **absent payload**:

- preserves the canonical document **byte for byte**;
- preserves every managed mirror;
- **creates no canonical blob for a legacy-only record**;
- performs **no repair**;
- performs **no inherited, imported, derived or legacy promotion**;
- **persists no provenance** — the actor is still transient, validated and discarded.

A **semantic no-op** — a present payload stating what the document already means — issues **no write at
all**; the revision-token comparison suppresses it.

**The gated `seller` / `landlord` path also promotes nothing.** A record whose location lives only in the
discrete mirrors does not have those values read back and written out as an authored canonical document,
which is the §10.3 hazard D-G1F-1's constraint exists to prevent.

### 28.7 Schema-version behaviour

- The first **semantic** `buyer` / `tenant` write stamps **`schema_version: 2`**.
- Deterministic canonical key ordering is emitted.
- A **no-command save does not canonicalise** — a legacy document keeps its exact bytes.
- A **semantic no-op issues no write**, so it cannot canonicalise either.
- The **`seller` / `landlord` gated path does not canonicalise**: no write means no rewrite.

**Consumers and tests were surveyed before implementation**, per §27.7's stop condition for G1f-2. No
production consumer of `location_dna_preferences` depends on byte-identical serialisation — every reader
decodes the JSON and reads keys semantically, and `CriteriaHashService` hashes a canonical payload array
rather than the stored string. Three test assertions in `G1fHireTenantUserTypeGateCharacterisationTest`
compared the stored blob byte-for-byte; they were narrowly converted to semantic comparisons (§28.8).
`HireSearchAreasParityTest`'s byte-identity assertion drives `TenantAgentAuctionEdit`, which is unmigrated,
and was therefore not affected.

**F-G1F-10 is carried forward unchanged in principle:**

- **semantic equality governs**;
- **byte identity is not guaranteed** after a semantic write;
- **no bulk rewrite or backfill is authorized**;
- the stop condition renews itself: **before migrating the next workflow, verify that ITS consumers and tests
  do not depend on byte-identical serialisation.**

### 28.8 Test and guard changes

**Two new test files, 33 tests, all passing:**

| Suite | Tests |
|---|---|
| `tests/Feature/Spatial/G1f2TenantAgentAuctionMigrationTest.php` | 22 |
| `tests/Unit/Services/LocationDna/Persistence/G1f2MigrationBoundaryGuardTest.php` | 11 |

**Four existing tests modified, each narrowly:**

| Test | Change |
|---|---|
| `G1fHireTenantUserTypeGateCharacterisationTest` | The old **partial-write expectation is inverted to prove zero writes**, and renamed accordingly. Two byte-identity blob comparisons became semantic. The "mirrors written for every `user_type`" row now asserts absence for the two unsupported roles. The gate's entry point is asserted per component |
| `G1fHireDoubleWriteCharacterisationTest` | **`TenantAgentAuction` removed from the double-write behavioural set** (2 → 1) and replaced with a positive assertion that its duplicate write is gone — the same trade G1f-1 made. The remaining component is exercised exactly as before |
| `G1aWorkflowPersistenceMatrixCharacterisationTest` | **`TenantAgentAuction` marked migrated on the SAVE side only.** The two save-side counts move 5 → 4. **All eight load-side entries remain covered** |
| `G1f1MigrationBoundaryGuardTest` | **Unmigrated workflow count 7 → 6**; **trait-host count 3 → 2**; one persistence-namespace exemption added |

**No other existing test required modification.** In particular:

- **Offer workflow readiness required no update** — `TenantAgentAuction.php` was already allow-listed and
  G1f-2 added no production file.
- **The G1c, G1d and G1e inertness guards required no update** — G1f-2 introduced no namespace, so the
  explicit `Persistence` exemptions G1f-1 added remain exactly as they were. No wildcard exists in any of
  them and none was added.

### 28.9 Non-vacuity probes

Six probes were run against the implementation and removed. **Three found genuine vacuity in the new tests**,
which were strengthened and re-probed — recorded because the finding is the useful part.

| # | Probe | Result |
|---|---|---|
| 1 | Move `user_type` validation below the mirror writes | **Initially exposed a vacuous key-only write counter** — `updateOrCreate` binds the meta key only on INSERT, so an UPDATE was invisible to it. The test was rewritten to detect both INSERT and UPDATE forms; the probe then failed it |
| 2 | Remove the persistence-service call | **9 tests fail** |
| 3 | Restore the duplicate mirror writes | **Initially exposed a second vacuous key-based assertion**, for the same reason on a fresh-record fixture. The test was rewritten to count by VALUE on a pre-seeded record; the probe then failed **6 tests** |
| 4 | Remove the `DB::transaction` | Rollback tests fail in **both** the G1f-1 and G1f-2 suites |
| 5 | Permit legacy-mirror → canonical promotion | **1 G1f-2 and 2 G1f-1 tests fail.** The first form tried — hydrate-then-rewrite of an unchanged document — was suppressed by the revision token and failed nothing, so the probe was retargeted at the actual §10.3 hazard |
| 6 | Open the gate to `seller` / `landlord` | **Initially passed two source-level gate assertions** — the gate literal occurs several times in this 5,300-line component for unrelated guards, so a whole-file search proved nothing. Both were narrowed to the gate NEAREST the writer call; the probe then failed **6 tests** |

**After the probes:** all removed; **SHA-256 restoration verified** against pre-probe baselines for all three
touched files; no probe residue anywhere in `app/` or the suites; and the persistence classes confirmed
**byte-identical** to their committed form.

**What probes 1, 3 and 6 are really worth.** Each of the three vacuous assertions would have passed forever
while proving nothing, and none was detectable by reading the test. Two shared one root cause — assuming a
meta write always binds its key — which is now stated explicitly in the suite's helper docblock so the next
migration does not rediscover it.

### 28.10 Rollout status — as at `d3123fb94`, superseded by §30.10

| Stage | Status |
|---|---|
| G1f audit and decisions | **COMPLETE** |
| G1f characterisation gaps | **CLOSED** |
| **G1f-1 · `BuyerAgentAuction`** | **IMPLEMENTED** — `5c38fc574` |
| **G1f-2 · `TenantAgentAuction`** | **IMPLEMENTED** — `d3123fb94` |
| G1f-3 | **NOT STARTED** |
| G1f-4 | **NOT STARTED** |
| Legacy criteria-controller migration | **NOT STARTED** |
| Trait / shim closeout | **NOT STARTED** |
| Direct-writer guard closeout | **NOT STARTED** |
| G1g | **NOT STARTED** |

**Counts, each measured rather than carried forward:**

| Measure | Value |
|---|---|
| Workflows migrated | **2** — `BuyerAgentAuction`, `TenantAgentAuction` |
| Workflow implementations unmigrated | **6** |
| Remaining direct canonical writer sites (§21 guard) | **7 — unchanged** |
| `HasSearchAreas` hosts still calling the trait save | **2** — `BuyerAgentAuctionEdit`, `TenantAgentAuctionEdit` |
| `LegacyMirrorAdapter` | **absent** |

**Why the direct-writer count did not fall.** The §21 guard lists files containing a literal
`saveMeta('location_dna_preferences'`. `TenantAgentAuction` never had one — it reached the canonical key
**through the trait**, which is itself one of the seven entries. So migrating it removes a canonical writer
without shortening that list. The list falls when the inline copies migrate (G1f-3, G1f-4), and reaches one
entry only when the trait is converted and the criteria controllers are migrated. **A flat count here is the
expected result of this particular migration, not a stalled one** — the measure that did move is the
trait-host count, 3 → 2.

---

## 29. G1f-3 readiness — Buyer Offer

**Type:** planning and read-only audit. **Nothing here is implemented and nothing here is authorized.**
**Audited at:** `6d4289a46857b171e952c246a1d40a99e8067a30` · **Audit date:** 2026-08-03
**Target:** `BuyerOfferListing` (create) and `BuyerOfferListingEdit` (edit) — §18's G1f-3 stage.

> **Status when written: NOT STARTED, NOT AUTHORIZED.** No production file, test or persistence class was
> modified by this section. Every line number and count below was measured at the audited commit.
>
> **Read this section as of `6988469c7`.** G1f-3 has since been authorized and implemented — **§30 is the
> implementation record**. This section is preserved as the plan it was, not rewritten to match the outcome,
> so the two can be compared. Two forecasts did not survive contact:
>
> - **F-G1F-13 was wrong** (§29.4 predicted `test_empty_blob_state_does_not_wipe_discrete_state` would
>   invert; it did not, and the test was left untouched — §30.7);
> - **a ninth tripwire was missed** (F-G1F-15, §30.8), so the count of changed existing tests landed on the
>   forecast total of eight by coincidence rather than by accuracy.
>
> Everything else in §29 — the inventory, the create/edit comparison, F-G1F-14, the B6 evidence and the
> proposed scope — was borne out.

### 29.1 Buyer Offer write inventory

| Property | `BuyerOfferListing` (create) | `BuyerOfferListingEdit` (edit) |
|---|---|---|
| File | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` |
| Lines | 3,081 | 2,937 |
| Implementation | **inline**, not the trait | **inline**, not the trait |
| `HasSearchAreas` | **not used** | **not used** |
| Save method | `saveAllMetadata($auction)` @ `:2445` | `saveAllMetadata($auction)` @ `:2405` |
| Save invocable in isolation | **yes** | **yes** |
| Load method | `loadDraft()` — blob read @ `:1932` | `loadAuctionData()` — blob read @ `:1858` |
| Canonical blob write | `:2466` | `:2825` |
| `counties` mirror | `:2464`, from the mutated prop | `:2424`, from the mutated prop |
| `state` mirror | `:2465`, from the mutated prop, **raw string** | `:2425`, from the mutated prop, **raw string** |
| `cities` mirror | `:2470`, **derived from the blob** | `:2828`, **derived from the blob** |
| `zipCodes` | **never written** | **never written** |
| Plural `states` | **never written** | **never written** |
| Transaction | **none** — zero `DB::transaction` in the file | **none** — zero in the file |
| Blob ↔ mirror distance | **contiguous, `:2464`-`:2470`** | **`:2425` → `:2825` — 400 lines, 315 intervening `saveMeta` calls** |
| Save-path callers | `:1796`, `:1840`, `:3004` | `:1750`, `:1810`, `:2904` |
| Authorization boundary | `ResolvesOwnedAuction` | `ResolvesOwnedAuction`, **owner-only, no assigned-agent allowance** (`:1335`) |

**Mirror source asymmetry, unchanged from G1a.** `cities` is the ONLY dimension already derived from the
blob (`json_decode(...)['cities'] ?? []`). `counties` and `state` reach storage through the component props,
which `hydrateDiscreteLocationFromBlob()` overwrites from the blob a few statements earlier. So the three
dimensions do not share a code path here either — the same shape F-G1-4 records for the trait.

**F-G1F-14 — `hydrateDiscreteLocationFromBlob()` is called TWICE per flow, and only ONE call is a write
concern.**

| Call site | Purpose | G1f-3 disposition |
|---|---|---|
| `BuyerOfferListing:2463` / `BuyerOfferListingEdit:2423` — inside `saveAllMetadata()` | mutates the props immediately before the mirror writes | **REMOVED** with the mirror writes it feeds |
| `BuyerOfferListing:2962` — inside `store()` · `BuyerOfferListingEdit:2870` — inside `update()` | populates `$this->state` / `$this->counties` **before `$this->validate()`**, because the discrete Acceptable State/Counties inputs were removed in 9B-3 | **MUST REMAIN, UNCHANGED** |

**This is a new integration constraint, absent from both prior migrations.** Neither `BuyerAgentAuction` nor
`TenantAgentAuction` had a pre-validation hydrate call — in the Hire family the method has no direct call
site at all and is reached only through `saveSearchAreas()`. Removing the Buyer Offer pre-validation call
would break `counties => required|array|min:1` and `state => required|string` for every listing whose
location is supplied only through the map, which
`SearchAreasStateCountyRoundTripTest::test_submit_succeeds_with_state_and_counties_supplied_only_by_blob`
pins. **The two concerns share a method name and nothing else; G1f-3 must separate them by call site.**

**The inline method itself is duplicated.** `hydrateDiscreteLocationFromBlob()` is defined locally in each
file — `:2428` (create) and `:2388` (edit) — byte-identical to each other, and near-identical to
`HasSearchAreas::hydrateDiscreteLocationFromBlob()`. G1f-3 removes neither definition: the surviving
pre-validation call still needs it. **Deleting the method is trait/shim-stage work, not G1f-3 work.**

### 29.2 Create vs edit — the semantic differences

| Dimension of difference | Assessment |
|---|---|
| **Write ordering** | **The only material difference.** Create writes all four keys contiguously; edit writes `counties`/`state` at `:2424`-`:2425` and the canonical blob + `cities` at `:2825`-`:2828`. Edit's 400-line, 315-call, untransacted window is F-G1F-7, and it is the single largest atomicity defect in the eight-workflow set |
| **Mirror semantics** | **Identical.** Same sources, same encodings, same guards |
| **`hydrateDiscreteLocationFromBlob()`** | **Identical** definitions; identical two-call-site structure |
| **Load side** | Equivalent — same legacy-`cities` merge with the same `empty()` guard, same 9B-2 prefill. Edit reads `$this->state` from meta at `:1857` before the blob; create's prefill runs after its property load. Ordering only |
| **`user_type` gate** | **Neither has one.** Nothing analogous to D-G1F-3 applies |
| **`zipCodes`** | **Neither writes it.** D-G1F-4 and the §17.4 checkpoint are inert for this stage |
| **`state` encoding** | Both already raw string. **4S-i is a no-op here**, exactly as it was for `BuyerAgentAuction` |
| **Save invocability** | Both invocable. Unlike G1f-4's targets, neither is behind an `update()`-only boundary |
| **Publish-path callers** | Create's third call site is `store()`; edit's is `update()`. Both call `saveAllMetadata()` and then continue — the UX branch inserts after exactly those two calls (§29.3) |

**Consequence for sequencing: they should migrate TOGETHER, in one commit.** The reasons are specific, not
stylistic:

1. **The characterisation is written as pairs.** Nine of the twelve tests in
   `G1aBuyerOfferInlineCharacterisationTest` loop over `[BuyerOfferListing::class, BuyerOfferListingEdit::class]`
   in a single test body. Migrating one would force each of those tests to grow a per-class branch, and then
   force it back out again one commit later — churn in the exact suite whose stability is the parity signal.
2. **`test_buyer_offer_components_are_unchanged` (B4) names both files in one loop.** It fails the moment
   either is migrated, so the tripwire cannot be retired incrementally.
3. **The two files are the same implementation.** F-G1-1's equivalence finding, re-proven by
   `test_buyer_offer_copies_are_defective_like_the_trait_not_divergent_like_tenant`, is that these are one
   implementation copied twice. Splitting them buys no isolation — a defect in one is a defect in both.

**The counter-argument, recorded and rejected.** "One workflow per commit" is §18's rule, and edit carries a
much larger atomicity defect than create. But the risk that rule manages is *attribution* — being unable to
tell which workflow a parity failure came from. Here every behavioural test already reports the class name in
its failure message, so attribution survives. **The recommendation is one commit covering both, with
per-class assertions in the new suite so a failure still names the flow.**

### 29.3 B6 — branch sequencing evidence

#### Topology, measured

| Item | Value |
|---|---|
| `architecture/location-dna-g1-domain-core` HEAD | `6d4289a46857b171e952c246a1d40a99e8067a30` |
| `ux/hire-agent-create-offer-parity` HEAD | `f590b356531fcde364d9ba2def678243226f1a5b` |
| Merge base | `10037715adca1daa0b59f381ffb6c23d2e01fbf1` |
| G1 ahead of UX | **46 commits** |
| UX ahead of G1 | **38 commits** |
| UX remote tracking branch | **none — the branch is local only** |

**Environment note, correcting §2.2.** `/home/runner/worktrees/` **exists again**. §2.2 recorded that tree as
destroyed and its six registrations pruned; that is no longer true. Four worktrees are registered and all
four exist on disk, including `/home/runner/worktrees/ux-hire-agent-create-offer-parity`, which has the UX
branch checked out and **four uncommitted paths** (`resources/views/hire_buyer_agent/view.blade.php` and
three `tests/Feature/Viho/*` files). Its tip commit is 2026-08-03 02:48 — recent, and unrelated to Location
DNA. **None of the uncommitted paths is a Buyer Offer writer**, so the working state adds no overlap. No lock
file or in-progress operation was present in any worktree.

#### Exact Buyer Offer files the UX branch changes

Both target files are touched, **+7 lines each, in two hunks per file**:

| File | Hunk | Location | Content |
|---|---|---|---|
| `BuyerOfferListing.php` | A | class body, **`:22`** | `use …\StampsBiddingActivation;` |
| | B | inside `store()`, **after `$this->saveAllMetadata($auction);` at `:3005`** | 6 lines calling `stampBiddingActivationAuto($auction)` |
| `BuyerOfferListingEdit.php` | A | class body, **`:18`** | the same trait `use` |
| | B | inside `update()`, **after `$this->saveAllMetadata($auction);` at `:2905`** | the same 6-line call |

#### Overlap with the G1f-3 write regions

| G1f-3 region | Nearest UX hunk | Separation |
|---|---|---|
| `BuyerOfferListing` `:2459`-`:2470` (inside `saveAllMetadata`) | hunk B at `:3005` | **~535 lines, different method** |
| `BuyerOfferListingEdit` `:2418`-`:2425` and `:2825`-`:2828` | hunk B at `:2905` | **77 lines, different method** |
| top-of-file `use` block — `:19` (create) / `:15` (edit) | hunk A at `:22` / `:18` | **2–3 lines, but distinct insertion points** |

**No region overlaps.** The only near-adjacency is the import block: G1f-3 would add one `use` line at the
end of the file-level import list, while UX adds a trait `use` inside the class body two lines later. These
are different lines, and a three-way merge conflicts only on the *same* lines — so this auto-merges.

#### Did Location DNA logic itself change?

**No.** Measured across the 38 UX commits:

- `git diff --stat 10037715a..ux -- app/Services/LocationDna/` — **empty**;
- `... -- app/Http/Livewire/Concerns/HasSearchAreas.php` — **empty**;
- no canonical write, no mirror write, no `hydrateDiscreteLocationFromBlob` call, no `location_dna_*` key
  anywhere in the Buyer Offer hunks.

The UX branch's Offer-Listing work is bidding activation and publish gating (`StampsBiddingActivation`,
`GuidesPublishValidation`) plus Viho presentation. It is orthogonal to the Location DNA write path.

#### Mechanical or semantic?

**Mechanical.** Two independent reasons:

1. **The G1 branch has never touched either Buyer Offer file.** `git diff 10037715a..architecture/… --` over
   both files is **empty**. Every hunk in a future merge is therefore one-sided, and git takes it verbatim.
2. **The changes are disjoint in both position and meaning.** UX edits `store()`/`update()` call sites and
   the class `use` block; G1f-3 edits the `saveAllMetadata()` body. Neither reads or writes what the other
   touches.

**The one thing to re-check at integration time, not now:** UX hunk B runs `stampBiddingActivationAuto()`
immediately **after** `saveAllMetadata()`. Post-G1f-3, `saveAllMetadata()` opens and closes a transaction
internally (inside the writer). The stamping call sits outside it, exactly as it sits outside today's
untransacted writes. **Nesting is therefore unchanged** — but this should be re-confirmed against the merged
tree rather than assumed from two diffs read separately.

#### Recommendation: **option B — proceed with G1f-3 now; the UX branch rebases afterward**

| Option | Assessment |
|---|---|
| **A. Wait for the UX branch to land** | **Rejected.** UX is 38 commits of styling and bidding work with uncommitted WIP in its worktree and no remote tracking branch. There is no landing date, and G1f-3 is not blocked on anything it contains. This converts a mechanical merge into an indefinite hold |
| **B. Proceed now; UX rebases afterward** | **RECOMMENDED.** Both files are one-sided on the G1 side, the hunks are disjoint, and no Location DNA logic changed. UX's rebase is a verbatim replay of two small hunks per file |
| **C. Fresh integration branch** | **Rejected.** It adds a merge point and a third branch to keep current without removing the eventual rebase. Justified when both sides edit the same logic; neither does |
| **D. Split — migrate create now, edit after UX lands** | **Rejected.** It is the pair-splitting §29.2 argues against, and it would not help: edit is the file where UX's hunk B is *closest* (77 lines), so deferring edit keeps the only near-adjacency open longer |

**Additional evidence for B, from the two completed stages.** G1f-1 and G1f-2 each changed exactly one
component and left the branch mergeable; the same shape applies here. The original B6 wording — *"that branch
has zero mirror-line changes, so the risk is mechanical"* — is **confirmed and refined**: the branch is not
zero-change on these files, it is +7 lines each, but **zero of those lines is a mirror line or any Location
DNA line**.

**B6 is a recommendation, not a decision.** It remains an **owner decision** and is unresolved until recorded
as one.

### 29.4 Byte-serialization stop condition — result

**No production consumer requires byte-identical `location_dna_preferences` serialization. The stop
condition is NOT triggered.**

| Consumer reachable from a Buyer Offer record | Read | Byte-dependent? |
|---|---|---|
| `BuyerOfferListingController:112` | `json_decode(...)` | no |
| `Stellar\BuyerOfferListingCriteriaLoader:126` | decoded, keys read | no |
| `Stellar\BuyerCriteriaLoader:112` | `json_decode(...) ?? []` | no |
| `BuyerAcceptedBidSummaryService:468` | null/empty guard, then decoded | no |
| `ComputeCompatibilityScore:398` | decoded | no |
| `BackfillLocationSnapshots:134` | `data_get(...)`, then decoded | no |
| `CriteriaHashService` | hashes a canonical **payload array**, never the stored string | no |
| Load side of both components (`:1932`, `:1858`) | `json_decode(...) ?? []` | no |

**Therefore, for G1f-3:**

- the first semantic write **may** stamp `schema_version: 2` — permitted;
- deterministic canonical key ordering **is** acceptable — permitted;
- a **no-command save and a semantic no-op must preserve bytes** — required, and already guaranteed by the
  writer's empty-batch return and revision-token comparison;
- **no bulk rewrite or backfill is authorized**, per F-G1F-10.

**Tests that compare raw bytes, and their disposition:**

| Test | Comparison | Disposition |
|---|---|---|
| `G1aBuyerOfferInlineCharacterisationTest::test_geometry_round_trips_byte_identically_on_both_flows` | `assertSame($encoded, $stored)` on both flows | **Authorized change** — becomes a semantic/geometry-fidelity assertion. The property that must survive is that geometry, unicode and float precision round-trip losslessly, not that the bytes are unchanged |
| `G1aWorkflowPersistenceMatrixCharacterisationTest::test_geometry_persists_byte_identically_through_every_invocable_save_path` | same, across the workflow set | **Established mechanism** — add `'migrated_save' => true` to both Buyer Offer rows; counts move 4 → 2 |
| `SearchAreasPersistenceCharacterisationTest` (`:105`, `:165`) | byte + `strlen` | **Unaffected — must not change.** It drives a synthetic `SearchAreasPersistenceHost` that mixes in `HasSearchAreas` directly, not any Buyer Offer component. It becomes relevant only at the trait/shim stage |
| `HireSearchAreasParityTest:204` | `assertEquals($blob, ...)` | **Unaffected** — drives `TenantAgentAuctionEdit`, unmigrated |

**F-G1F-13 — an eighth authorized-change entry exists, on no list.**

```php
// SearchAreasStateCountyRoundTripTest::test_empty_blob_state_does_not_wipe_discrete_state
->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id])
->set('location_dna_preferences_json', json_encode(['cities' => [], 'state' => '']))
->call('saveDraftOnly');
$this->assertEquals('Georgia', $auction->fresh()->info('state'));
```

It **currently passes** (verified: 3 of 4 pass; the one failure is the unrelated `ILIKE` defect). Post-G1f-3
a present-empty `state` becomes an explicit **clear**, the projection emits `''`, and the discrete `state`
is legitimately wiped — the assertion inverts. It is a backward-compatibility guard written against the
defect D-G1-4 4-A repeals, in a suite that appears on **no** G1f list: not §20.1's must-not-change set, not
§20.4's authorized-change list, and not the G1c package's six entries. **Structurally identical in purpose
to F-G1F-9's discovery**, and it must be added to the authorized-change list **before** G1f-3 or a red run
will look like an unplanned regression.

### 29.5 Proposed implementation scope

**Migrate both writers in ONE commit** (§29.2).

**The existing persistence classes are sufficient. No new class is expected, and no Buyer Offer-specific
seam is needed.** G1f-2 already demonstrated the boundary generalising to a second workflow without
modification; the Buyer Offer components need strictly less than `TenantAgentAuction` did — no role gate, no
`zipCodes`, no unsupported-input rejection. `OwnerPrivateLocationDnaWriter` is the correct seam: the surface
is owner-private editing in both flows.

**Expected production changes — exactly two existing files, no new file:**

| File | Expected change |
|---|---|
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | one import; one `persistLocationDna()` method; `:2463`-`:2470` replaced by one call |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` | one import; one `persistLocationDna()` method; `:2423`-`:2425` **and** `:2825`-`:2828` replaced by one call at a single site |

**Behaviour the migration is expected to produce, all inherited from the existing boundary:**

- **Transaction boundary** — canonical write and the three managed mirror writes inside the writer's single
  `DB::transaction`. For the edit flow this **closes F-G1F-7's 400-line, 315-call untransacted window**,
  which is the largest single correctness gain available in G1f.
- **Command building** — `LocationDnaCommandBuilder` from the submitted payload only. Key absent → no
  command → preserve. Key present non-empty → `set`. Key present empty → `clear`. No full-document rewrite.
- **Capability context** — `OwnerPrivateEdit` / `Owner` / `Edit`, authenticated, resolved by
  `LocationDnaCapabilityResolver`. Unchanged from G1f-1.
- **Provenance actor** — `ProvenanceActor::ExplicitOwner`, **transient**: used to validate the transition,
  never stored. No provenance persistence.
- **Mirror projection** — `cities`, `counties`, `state` derived from the RESULTING canonical document.
  Present-but-cleared produces a cleared mirror; absent produces no write.
- **`zipCodes`** — **no decision required and none to be taken.** Neither component writes the key. D-G1F-4
  (a) and the §17.4 checkpoint remain untouched by this stage.
- **`state`** — raw string. 4S-i is already satisfied; **no data change is expected for any Buyer Offer
  record.**
- **No-op and promotion guarantees** — absent payload preserves canonical bytes and all mirrors; no blob is
  created for a legacy-only record; no repair; no inherited/imported/derived/legacy promotion; a semantic
  no-op issues no write.
- **Deliberately NOT changed** — both load paths, both pre-validation `hydrateDiscreteLocationFromBlob()`
  call sites (F-G1F-14), both inline method definitions, `HasSearchAreas`, and the six unmigrated workflows.

**Explicitly out of scope for G1f-3:** deleting the duplicated inline `hydrateDiscreteLocationFromBlob()`
definitions; converting the trait to a shim; the Tenant Offer pair; the criteria controllers; any provenance,
snapshot, Bridge, `CriteriaHashService` or public-projection change; any migration or schema change.

### 29.6 Test plan

**New suite — `tests/Feature/Spatial/G1f3BuyerOfferMigrationTest.php`**, asserted per class so a failure
names the flow:

| Group | Assertions |
|---|---|
| Write path | canonical written through the boundary on both flows; mirrors equal `LegacyMirrorProjection` output; exactly one canonical write and one write per managed key; canonical before mirrors |
| Clear | explicit clear propagates to `cities`, `counties` **and** `state` on both flows — the assertion that inverts the three-way split |
| Absence / no-op | absent payload preserves canonical bytes and every mirror; legacy-only record gets no blob; semantic no-op issues no statement |
| Schema | first semantic write stamps `schema_version: 2`; no-command save and semantic no-op preserve bytes exactly |
| Atomicity | **the edit flow specifically** — induced managed-mirror failure rolls back the canonical write and the earlier mirrors together, proving the 400-line window is closed |
| Preservation | `zipCodes` still never written by either component; pre-validation hydrate still populates `$this->state` / `$this->counties` before `validate()`; unrelated metadata still persists; load side unchanged |

**New structural guard — `tests/Unit/Services/LocationDna/Persistence/G1f3MigrationBoundaryGuardTest.php`:**
exactly four workflow components wired to the writer; the four remaining unmigrated ones untouched;
`HasSearchAreas` not globally wired and still serving two hosts; the Tenant Offer divergence construct
intact; `LegacyMirrorAdapter` still absent; no new persistence class; no migration; no provenance
persistence; `CriteriaHashService`, Bridge and `PublicGeometryProjection` untouched.

**Existing tests expected to require narrow updates — the full forecast:**

| Test | Expected change | Certainty |
|---|---|---|
| `G1aBuyerOfferInlineCharacterisationTest::test_create_flow_records_cleared_dimensions_three_different_ways` | clear now propagates to all three | **certain** |
| `…::test_edit_flow_records_cleared_dimensions_three_different_ways` | same | **certain** |
| `…::test_geometry_round_trips_byte_identically_on_both_flows` | byte → semantic | **certain** |
| `…::test_unmounted_editor_destroys_geometry_on_both_flows` | absence now **preserves**; the defect it pins is repealed | **certain** |
| `G1aWorkflowPersistenceMatrixCharacterisationTest` | `'migrated_save' => true` on both Buyer Offer rows; two save-side counts 4 → 2 | **certain** |
| `TenantOfferCitiesMirrorTest::test_buyer_offer_components_are_unchanged` | **B4's tripwire** — the asserted inline mirror line is removed | **certain** |
| `SearchAreasStateCountyRoundTripTest::test_empty_blob_state_does_not_wipe_discrete_state` | **F-G1F-13** — clear now wipes, by design | **certain** |
| `G1f1MigrationBoundaryGuardTest` / `G1f2MigrationBoundaryGuardTest` | unmigrated list 6 → 4; wired-component count 2 → 4; `AUTHORIZED_WRITERS` **7 → 5** — the first stage that shortens it | **certain** |
| `G1aBuyerOfferInlineCharacterisationTest::test_populated_blob_mirrors_correctly_on_both_flows` | none — populated values project identically | expected green |
| `…::test_buyer_offer_copies_are_defective_like_the_trait_not_divergent_like_tenant` | none — it asserts the **load-side** `empty()` branch, which G1f-3 does not touch | expected green |
| The suite's seven load-side tests | none — G1f-3 migrates the write path only | expected green |
| `SearchAreasPersistenceCharacterisationTest`, `HireSearchAreasParityTest`, G1b, G0.1 public geometry, readiness, G1c/G1d/G1e inertness guards | **none** | expected green |

**Eight existing tests are expected to change — more than either prior stage, and the count is itself the
finding.** G1f-1 amended six and G1f-2 four; G1f-3 repeals three characterised defects at once (resurrection
on clear, the three-way split, unmounted-editor destruction) across two components. **If more than these
eight require modification, that is a stop-and-report condition.**

**Mutation probes required** — at minimum, and each must be shown to fail a named test before removal:

1. Remove the persistence-service call from one flow → that flow's migration tests fail.
2. Restore the inline mirror writes → per-key write-count tests fail. **Count by VALUE on a pre-seeded
   record**, per §28.9's finding — a key-only counter is invisible to an `UPDATE`.
3. Remove the writer's transaction → the edit-flow rollback test fails.
4. Permit legacy-mirror → canonical promotion → the no-op/legacy-only tests fail.
5. Remove the **pre-validation** hydrate call → `test_submit_succeeds_with_state_and_counties_supplied_only_by_blob`
   fails. **New for this stage** — it guards F-G1F-14.
6. Migrate only one of the two components → the structural guard's count assertion fails.

All probes removed before commit, with SHA-256 restoration verified.

### 29.7 Blocker re-evaluation

| # | Blocker | Status | Must resolve BEFORE authorization, or MAY resolve INSIDE it |
|---|---|---|---|
| **B4** | `test_buyer_offer_components_are_unchanged` absent from the authorized-change list | **OPEN — and now unavoidable.** It names both target files in one loop and asserts the exact inline mirror line G1f-3 deletes | **MAY be resolved inside the G1f-3 authorization**, by naming it explicitly in that authorization's change list. It is a test-expectation update, not an investigation. **It must be named in advance** — an unnamed red tripwire is indistinguishable from a regression |
| **B5** | v1.2 §4.2 / §4.3 corrections outstanding | **OPEN**, carried since the G1 report §16 step 2. §4.2's "4 byte-identical inline copies" and its four-guard-site count, §4.3's F-C1 consumer count, plus this report's own §5 and §6.3 corrections | **MAY be resolved inside**, or deferred again — but it is now **three stages overdue** and the governing document is drifting further from measured reality with each migration. Recommended as its own documentation increment **before** G1f-4, not as a G1f-3 sub-task |
| **B6** | UX branch sequencing | **OPEN — owner decision.** Evidence complete (§29.3); recommendation is **option B** | **MUST be decided BEFORE implementation.** It is the only one of the three that changes what is done rather than what is written down. Everything needed to decide it is in §29.3 |

**Also carried, and unaffected by G1f-3:** defect boundaries 2 and 3 gate G1f-7; boundary 4 (`zipCodes`) is
inert for this stage but still required before G1f completion.

### 29.8 Exact authorization recommended next

**Recommended: a single, narrow G1f-3 authorization covering both Buyer Offer writers, conditional on B6.**

**Prerequisite, outside the implementation authorization:**

- **Record the B6 decision.** Recommended: option B.

**Then authorize, in one commit:**

1. Migrate `BuyerOfferListing` and `BuyerOfferListingEdit` to `OwnerPrivateLocationDnaWriter`, replacing the
   in-`saveAllMetadata()` hydrate call and all four mirror/canonical writes per file with one call each.
2. **Preserve** both pre-validation `hydrateDiscreteLocationFromBlob()` call sites and both method
   definitions.
3. Add `G1f3BuyerOfferMigrationTest` and `G1f3MigrationBoundaryGuardTest`.
4. Amend exactly the **eight** existing tests forecast in §29.6 — **including B4's tripwire and F-G1F-13**,
   both named in the authorization in advance.
5. Add no production class, no migration, no config, no provenance persistence; change no persistence class,
   no controller, model, route or view.

**Binding stop conditions for that increment:**

- stop if more than the eight forecast tests require modification;
- stop if any persistence class must materially change;
- stop if the pre-validation hydrate call cannot be preserved;
- stop if a Buyer Offer consumer is found to require byte-identical serialization;
- stop if closing the edit flow's untransacted window requires nesting inside a caller's transaction that
  does not exist today;
- stop if another session modifies the workspace or the UX worktree's Buyer Offer files.

**Not authorized by this section, and not to be started:** G1f-3 itself, G1f-4, the trait/shim conversion,
the criteria controllers, and G1g. **This section is planning only.**

---

## 30. G1f-3 implementation record

**Implementation commit:** `a17d4cb14ffb8669bcae09ad119e00006859d355`
**Subject:** `feat(location-dna): migrate Buyer Offer writers`
**Date:** 2026-08-03 · **Parent:** `6988469c7377d6f5c6d6676a8ad576bcd971cbd9`

| Scope | Status |
|---|---|
| **G1f-3 · `BuyerOfferListing` + `BuyerOfferListingEdit`** | **IMPLEMENTED — both, in one commit** |
| G1f-4 and every later stage | **NOT STARTED** |
| G1g adapter contract | **NOT STARTED** |

**Four workflows are now migrated.** The remaining four canonical write paths are unchanged.

### 30.1 Exact production scope

**Two existing production files changed. No new production class.**

| File | Change |
|---|---|
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | +55 / −11 — one import, one `persistLocationDna()` method, the inline write block replaced |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` | +56 / −13 — the same, plus the removal of the second half of its split write |

**The `App\Services\LocationDna\Persistence` namespace is byte-identical to G1f-1's**, verified by an empty
`git diff 6988469c7 a17d4cb14 -- app/Services/`. Three consecutive migrations have now reused the boundary
without changing it, which is the strongest available evidence that the G1f-1 design generalises.

**Nothing else in production was touched:** no config, route, controller, model, view, migration or
JavaScript.

**Both copies migrated in the same commit**, per the authorization. The reason is recorded in §29.2 and
proved out: nine of the twelve tests in `G1aBuyerOfferInlineCharacterisationTest` loop over both classes in
one body, and the B4 tripwire named both files in one loop — a half-migrated pair would have left both in an
ambiguous state. `G1f3MigrationBoundaryGuardTest::test_the_buyer_offer_pair_is_migrated_together`
mechanises the rule so a future increment cannot split them by accident.

### 30.2 B6 — option B, executed

**The decision was executed exactly as approved.** `ux/hire-agent-create-offer-parity` was **not merged, not
rebased, not cherry-picked and not modified**. G1f-3 proceeded on `architecture/location-dna-g1-domain-core`.

**Verified immediately before staging:**

- both Buyer Offer files on the UX branch were **byte-identical to the audited versions** — blobs
  `739f89307…` and `5b1ef4355…`, unchanged from §29.3's measurement;
- the UX overlap remained the same two mechanical hunks per file (a class-body trait `use`, and a
  `stampBiddingActivationAuto()` call after `saveAllMetadata()`), touching **no Location DNA logic**;
- no merge, rebase or cherry-pick was performed on either branch.

**The UX branch advanced twice during the increment, and neither advance invalidated the evidence:**

| Advance | When | Content | Buyer Offer touched? | Location DNA touched? |
|---|---|---|---|---|
| `f590b3565` → `f53b5b968` | between the readiness audit and the G1f-3 authorization | Viho styling of the buyer detail blade + three Viho tests — the uncommitted WIP §29.3 had already recorded, now landed | **no** | **no** |
| `f53b5b968` → `4bff72fbb` | between the G1f-3 commit and this reconciliation | Viho styling of the tenant detail blade + the same three Viho tests | **no** | **no** |

Both were confirmed by diffing the two Buyer Offer paths and the Location DNA paths across each advance and
finding them empty. **The B6 evidence remained valid throughout.**

> **The rebase obligation stands and is UNMET.** `ux/hire-agent-create-offer-parity` must rebase onto the
> completed G1f work. It is 40 commits ahead of the merge base and has never been merged into this branch
> (`git merge-base --is-ancestor` returns false). Option B's cost was deferred, not avoided, and the longer
> the branch runs the more of G1f it will replay over.

### 30.3 Create and edit — what each flow became

**Both flows now share one write path.** Canonical state is written first, the three managed mirrors are
projected from the resulting document, and both happen in one transaction with one write per managed key.
**No direct inline canonical or managed-mirror writer remains in either component.**

**Create flow.** The former sequence — `hydrateDiscreteLocationFromBlob()`, then `counties`, then `state`,
then the canonical blob, then `cities` decoded from that blob — was four statements drawing on three
different value sources with no transaction. All of it is now one `persistLocationDna($auction)` call.

**Edit flow.** The same four writes existed but were **split**: `counties` and `state` near the top of
`saveAllMetadata()`, the canonical blob and `cities` roughly 400 lines and **315 intervening `saveMeta`
calls** later. Both halves were collapsed into a single call at the earlier position. This is what closes
F-G1F-7, the largest atomicity defect in the eight-workflow set: any failure inside that window used to
commit mirrors that disagreed with the blob, permanently and undetectably.

### 30.4 F-G1F-14 — the two hydration calls, separated

**Each Buyer Offer flow called `hydrateDiscreteLocationFromBlob()` twice, and the two calls are not
redundant duplicates of one another. They serve different purposes and only one was removed.**

| Call site | Purpose | Disposition |
|---|---|---|
| inside `saveAllMetadata()` | mutated `$this->state` / `$this->counties` immediately before the mirror writes that read them | **REMOVED** — the mirrors are now projected from canonical state, so the mutation has no consumer |
| inside `store()` / `update()`, **before `$this->validate()`** | populates `$this->state` / `$this->counties` for the `counties => required\|array\|min:1` and `state => required\|string` rules, because the discrete Acceptable State/Counties inputs were removed in 9B-3 | **PRESERVED, UNCHANGED** |

**The method definitions in both files are also preserved** — the surviving call still needs them. Deleting
the duplicated definitions is trait/shim-stage work, not G1f-3 work.

**How the distinction is defended.** `G1f3BuyerOfferMigrationTest::test_only_the_pre_validation_hydrate_call_remains`
asserts the survivor **by position, not by count**: it checks that exactly one call remains *and* that it
appears after the writer call in the file, which is where `store()`/`update()` sit. A count-only assertion
would pass just as happily if the wrong call had survived — and the wrong survivor breaks submit for every
listing whose location comes only from the map, while every mirror test still passes.
`test_the_pre_validation_hydrate_still_populates_the_validated_props` adds the behavioural half. Mutation
probe 6 confirms both are load-bearing.

### 30.5 Command semantics, clear semantics and the standing guarantees

**Command rules, unchanged from the shared builder:**

- **absent input → no command → preserve**;
- present non-empty value → **set**;
- present empty value → **clear**;
- `null`, `false`, `''` or an unparseable payload → **empty batch**, and the writer returns before reading
  or writing anything;
- **no full-document rewrite**, **no mirror-to-canonical promotion**, **no inherited/imported/derived
  promotion**, **no legacy repair**, **no snapshot restoration**, **no provenance persistence**.

**Clears now propagate uniformly** to `cities`, `counties` **and** `state`, on both create and edit. This
repairs F-G1-4's three-way split on the last two implementations that carried it: `cities` used to honour a
clear while `counties` and `state` kept their stale values. It is D-G1-4 option 4-A arriving at the Buyer
Offer family.

**A stale mirror cannot resurrect a cleared canonical value.** Asserted as a sequence rather than a single
save: clear the dimension, then perform a later save that states nothing, and the clear must still stand in
both the mirror and the canonical document. The component property is deliberately left holding the old
value throughout, so the assertion fails if it can leak back in.

**Unmounted-editor destruction is repaired.** An empty payload used to be written straight over the
authoritative blob and to empty the `cities` mirror in the same save, with client-side JavaScript as the
only defence. It now states nothing, produces no command, and preserves the stored document byte for byte.

### 30.6 Transaction and rollback

**Both flows use the shared transaction boundary** inside `LocationDnaPersistenceService`. Unrelated
metadata writes remain outside it, deliberately — the transaction was not broadened beyond Location DNA.

**Rollback proof.** The record is passed as a proxy that forwards to the real model but raises on the
`state` mirror write — the LAST managed key the projection emits, so `cities` and `counties` have already
been written inside the transaction when it fires. Afterwards the canonical document, `cities` and
`counties` are all absent: the batch applies wholly or not at all, and **no partial Location DNA state
remains**. The test runs on **both** flows; for the edit flow it is the direct proof that **F-G1F-7 is
closed**.

### 30.7 Schema version, byte behaviour, and the F-G1F-13 withdrawal

**Approved behaviour, as implemented:**

- the first **semantic** write stamps **`schema_version: 2`** with deterministic key ordering;
- a **no-command save preserves bytes** exactly;
- a **semantic no-op preserves bytes and performs no write** — the revision token suppresses it;
- **no bulk rewrite or backfill** was performed or authorized;
- **no consumer requires byte-identical serialization** — §29.4's survey found all eight reachable Buyer
  Offer consumers decode the JSON, and `CriteriaHashService` hashes a payload array rather than the stored
  string.

**F-G1F-10 is carried forward unchanged in principle:** semantic equality governs, byte identity is not
guaranteed after a semantic write, and the stop condition renews itself for the next workflow.

**F-G1F-13 — WITHDRAWN. It was a false-positive prediction, and the test was left untouched.**

§29.4 predicted that `SearchAreasStateCountyRoundTripTest::test_empty_blob_state_does_not_wipe_discrete_state`
would invert at G1f-3, on the reasoning that a present-empty `state` becomes an explicit clear and the
projection would emit `''` over the stored `'Georgia'`. **It still passes, and the prediction was wrong for
a reason worth recording:**

- the test drives `BuyerOfferListingEdit::update()`, whose fixture seeds `workflow_type` and `state` but
  **no `counties`**;
- `update()` validates `counties => required|array|min:1` **before** reaching `saveAllMetadata()`;
- validation fails, `update()` returns, and **the save path never executes**.

So the assertion holds because nothing was written at all — identically before and after the migration.
**The test does not currently prove anything about post-migration state-clear semantics**, and it did not
prove anything about pre-migration semantics either. It is vacuous with respect to the write path.

**No change was made to it.** The authorization permitted a narrow update "required to reflect the approved
behavior"; none was required, and rewriting a test that passes for an unrelated reason would have changed
what it covers under cover of a migration. **No broad rewrite of the suite was made.** The vacuity is
recorded here for a later increment to address on its own terms — the clear semantics it was thought to
cover are proven instead by
`G1f3BuyerOfferMigrationTest::test_an_explicit_clear_propagates_to_every_managed_mirror`.

**The lesson for G1f-4:** a forecast that a test will change must confirm the test actually reaches the code
being changed. Source-level reasoning about the payload was not sufficient.

### 30.8 B4 resolved, and F-G1F-15 — the ninth tripwire

**B4 — RESOLVED.** `TenantOfferCitiesMirrorTest::test_buyer_offer_components_are_unchanged` asserted that
both Buyer Offer files still contained the exact inline mirror-write line, which G1f-3 removes by
definition. It was named in the authorization in advance, so its red run was an expected update rather than
a regression signal.

**It was converted, not deleted**, into `test_buyer_offer_components_are_migrated_and_the_boundary_held`,
which now proves:

- both Buyer Offer files use **exactly one writer seam** and contain **no inline canonical or `cities`
  write**;
- the `FINDING 2B-3` marker still does not appear, and the Tenant divergence construct was not copied
  across;
- **no Tenant Offer workflow was authorized** — both still write canonically and neither references the
  seam;
- **no Hire edit workflow was authorized** — neither references the seam.

The guard therefore protects strictly more after the conversion than before: it previously watched two
files, and now watches the migration boundary across six.

**F-G1F-15 — a NINTH tripwire existed, on no list, and was found only by running the suites.**

`SearchAreasWidgetContractTest::test_finding_2b3_all_implementations_write_the_cities_mirror` searched all
five implementations for the literal `saveMeta('cities'`. It is structurally identical to B4's tripwire and
appears in **none** of the tracking lists: not §20.1's must-not-change set, not §20.4's authorized-change
list, not the G1c package's six entries, and not §29.6's forecast.

**FINDING 2B-3 is not regressed** — both Buyer Offer components still write the `cities` mirror on every
save, now derived from canonical state by `LegacyMirrorProjection` instead of from a locally decoded blob.
Only its source-level expression changed for two of the five files.

**The update preserves the protection rather than narrowing it.** Each implementation is now asserted
against the mechanism it actually uses:

- the three unmigrated implementations (`HasSearchAreas`, both Tenant Offer copies) still assert the inline
  `saveMeta('cities'` write;
- the two migrated implementations assert `$this->persistLocationDna($auction);`;
- `cities` is asserted to remain in `LegacyMirrorProjection::MANAGED_KEYS`, which is what makes the seam
  equivalent to the inline write.

Removing the mirror write from **any** of the five still fails here.

**Two tripwires of this shape have now been found by execution rather than by audit** (F-G1F-9 at G1f-1,
F-G1F-15 here). **Recommended before G1f-4:** grep the whole test suite for source-level assertions naming
`TenantOfferListing`/`TenantOfferListingEdit` and their inline writes, so the next stage's authorized-change
list is complete in advance rather than discovered by a red run.

### 30.9 F-G1F-16 — a real defect in the G1f-2 boundary guard

**Found by mutation probe 2, and it is a TEST defect, not a production defect.**

`G1f2MigrationBoundaryGuardTest::test_exactly_two_workflow_components_are_wired_to_the_writer` scanned for
wired components with:

```php
glob($this->root().'/app/Http/Livewire/**/*.php')   // plus a one-level glob
```

**PHP's `glob()` does not recurse on `**`** — it behaves as a single `*`. The scan therefore covered
`app/Http/Livewire/*.php` and `app/Http/Livewire/*/*.php` only, and **never reached the two-level-deep
`OfferListing/Buyer/…` and `OfferListing/Tenant/…` components at all**. The guard would have reported
"exactly two wired" no matter what those four files contained.

It was invisible while it was true — G1f-2's migrated component sits one level deep, so the guard passed
honestly. It surfaced the moment G1f-3 wired two files the scan could not see and the count did not move.

**Corrected** by replacing both globs with a `RecursiveIteratorIterator`, the same mechanism
`G1f1MigrationBoundaryGuardTest::productionFiles()` already used. The guard now measures the actual files,
and the new `G1f3MigrationBoundaryGuardTest` uses a recursive helper throughout for the same reason. The
cause is documented in place so the next guard author does not repeat it.

**No production behaviour was affected at any point.** The defect only ever weakened a check.

### 30.10 Mirrors, and what was deliberately not introduced

**The managed mirror set is unchanged.** `LegacyMirrorProjection::MANAGED_KEYS` remains
`['cities', 'counties', 'state']`, and the projection itself was not modified.

| Mirror | Encoding | G1f-3 effect |
|---|---|---|
| `cities` | JSON-encoded array | source changes from locally decoded blob to canonical projection; value identical for populated input |
| `counties` | JSON-encoded array | source changes from mutated component prop to canonical projection; a clear now takes effect |
| `state` | **raw string** | source changes as above; a clear now takes effect |

**Encoding-neutral for Buyer Offer.** Both components already wrote `state` as a raw string, so D-G1F-4's
4S-i decision is a no-op here and **no Buyer Offer record's `state` encoding changed**.

**Not introduced, deliberately:**

- **`zipCodes`** — neither Buyer Offer component has ever written the key, and neither does now. D-G1F-4 (a)
  and the §17.4 checkpoint are inert for this stage and remain undecided (defect boundary 4).
- **the plural `states` key** — never emitted; still the legacy dead write of §17.5.
- **`LegacyMirrorAdapter`** — still uncreated, in every namespace.

### 30.11 Test and guard changes

**Two new test files, 33 tests, all passing:**

| Suite | Tests |
|---|---|
| `tests/Feature/Spatial/G1f3BuyerOfferMigrationTest.php` | 19 |
| `tests/Unit/Services/LocationDna/Persistence/G1f3MigrationBoundaryGuardTest.php` | 14 |

Every behavioural test in the migration suite runs **both** classes and names the failing flow, so migrating
the pair together costs no attribution.

**Six existing tests modified, each narrowly, with the exact intent recorded:**

| Test | Intent of the change |
|---|---|
| `G1aBuyerOfferInlineCharacterisationTest` | Four of twelve. The two three-way-split tests now assert a **uniform** clear; the byte-identity geometry test becomes **semantic** (dimension-by-dimension, plus vertex count, float precision and unicode — the properties byte identity only protected incidentally); the unmounted-editor test asserts **preservation** instead of destruction. **The seven load-side tests and the structural equivalence test are unchanged**, because G1f-3 migrated the write path only |
| `G1aWorkflowPersistenceMatrixCharacterisationTest` | `'migrated_save' => true` on both Buyer Offer rows; the two save-side counts move 4 → 2. **All eight load-side entries remain covered** |
| `TenantOfferCitiesMirrorTest` | **B4** — converted from "unchanged" to a positive migrated-boundary assertion (§30.8) |
| `SearchAreasWidgetContractTest` | **F-G1F-15** — asserted per mechanism so FINDING 2B-3's protection is preserved (§30.8) |
| `G1f1MigrationBoundaryGuardTest` | `AUTHORIZED_WRITERS` **7 → 5**; unmigrated workflows 6 → 4; two persistence-namespace exemptions added |
| `G1f2MigrationBoundaryGuardTest` | Migrated set 2 → 4; unmigrated 6 → 4; **plus the F-G1F-16 recursion fix** (§30.9) |

**Six existing tests changed against a forecast of eight.** The forecast named F-G1F-13 (not needed) and did
not name F-G1F-15 (needed) — the totals agree by coincidence rather than by accuracy, which §29's preamble
now records.

**No other existing test required modification.** Offer workflow readiness needed none (both files were
already allow-listed and no production file was added); the G1c/G1d/G1e inertness guards needed none (no new
namespace).

### 30.12 Non-vacuity probes

Eight probes were run and removed. **One found a further vacuity in a new test**, which was strengthened and
re-probed.

| # | Probe | Result |
|---|---|---|
| 1 | Remove the writer call from the create flow | **10 tests fail** |
| 2 | Restore one inline canonical write in the edit flow | G1f-3 guard **2**, B4 tripwire **1**, G1f-1 guard **1**, migration suite **5** |
| 3 | Restore the duplicate property-sourced mirror writes | **Initially caught only 3.** With a payload stating all three dimensions, the reinstated write is first overwritten by `hydrateDiscreteLocationFromBlob()` and then writes the SAME value the writer does — and Eloquent issues no `UPDATE` for an unchanged attribute, so the duplicate collapses and the per-key count stays 1. A second measurement was added using a **cities-only payload**, where the props survive unhydrated and a duplicate binds values the writer never produces; the probe then failed **4** |
| 4 | Remove the `DB::transaction` | rollback tests fail in **G1f-1, G1f-2 and G1f-3** |
| 5 | Permit legacy-mirror → canonical promotion | **1 G1f-3, 1 G1f-2, 2 G1f-1** |
| 6 | Remove the pre-validation hydrate call | migration suite **1**, G1f-3 guard **1**, and `SearchAreasStateCountyRoundTripTest` moves 1 → **2** failures — the validation-preparation coverage firing exactly as F-G1F-14 requires |
| 7 | Reintroduce state resurrection in the projection | **1 G1f-3, 2 G1a inline, 1 projection unit, 1 G1f-1** |
| 8 | Add `zipCodes` and plural `states` to the managed set | **4 suites fail** |

**After the probes:** all removed; **SHA-256 byte-exact restoration verified** on all five touched files
(both Buyer Offer components, `LocationDnaPersistenceService`, `OwnerPrivateLocationDnaWriter`,
`LegacyMirrorProjection`); **no probe residue** anywhere in `app/` or `tests/`; and the **persistence
namespace confirmed unchanged** against the committed tree.

**Probe 3's finding, carried forward.** This is the third consecutive stage at which a write-count assertion
proved vacuous, each for a different reason — G1f-2 found that `updateOrCreate` binds the key only on
`INSERT`, and G1f-3 found that Eloquent suppresses an `UPDATE` when the value is unchanged. **Any future
write-count assertion must state which of the two mechanisms makes it non-vacuous**, and prove it with a
probe.

### 30.13 Rollout status — current

| Stage | Status |
|---|---|
| G1f audit and decisions | **COMPLETE** |
| G1f characterisation gaps | **CLOSED** |
| **G1f-1 · `BuyerAgentAuction`** | **IMPLEMENTED** — `5c38fc574` |
| **G1f-2 · `TenantAgentAuction`** | **IMPLEMENTED** — `d3123fb94` |
| **G1f-3 · Buyer Offer create + edit** | **IMPLEMENTED** — `a17d4cb14` |
| G1f-4 · Tenant Offer create + edit | **NOT STARTED** |
| Hire edit migrations (`BuyerAgentAuctionEdit`, `TenantAgentAuctionEdit`) | **NOT STARTED** |
| Legacy criteria-controller migration | **NOT STARTED** |
| Trait / shim closeout | **NOT STARTED** |
| Direct-writer guard closeout | **NOT STARTED** |
| G1g | **NOT STARTED** |

**Counts, each measured at this commit rather than carried forward:**

| Measure | Value |
|---|---|
| **Workflows migrated** | **4** — `BuyerAgentAuction`, `TenantAgentAuction`, `BuyerOfferListing`, `BuyerOfferListingEdit` |
| **Workflow implementations unmigrated** | **4** — `BuyerAgentAuctionEdit`, `TenantAgentAuctionEdit`, `TenantOfferListing`, `TenantOfferListingEdit` |
| **Direct canonical writer sites (§21)** | **7 → 5** |
| `HasSearchAreas` hosts still calling the trait save | **2** — `BuyerAgentAuctionEdit`, `TenantAgentAuctionEdit` |
| `LegacyMirrorAdapter` | **absent** |

**The five remaining direct writers**, all still in scope:

1. `app/Http/Livewire/Concerns/HasSearchAreas.php` — retires at the trait/shim stage
2. `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` — G1f-4
3. `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php` — G1f-4
4. `app/Http/Controllers/BuyerCriteriaAuctionController.php` — G1f-7
5. `app/Http/Controllers/TenantCriteriaAuctionController.php` — G1f-7

**The two legacy criteria-controller writers remain IN scope and IN the count**, per D-G1F-5. They are not
dropped from the list to make it look shorter; the list shrinks by migration only, and
`G1f3MigrationBoundaryGuardTest::test_the_criteria_controllers_remain_in_scope_and_in_the_count` asserts
exactly that.

**Why this stage moved the count when the first two did not.** G1f-1 and G1f-2 both migrated components that
reached the canonical key **through the trait**, which is itself one entry — so removing them shortened
nothing. The Buyer Offer copies wrote the key **inline**, so migrating them removed two entries directly.
The list next falls at G1f-4, and reaches one entry only once the trait is converted and both criteria
controllers are migrated.
