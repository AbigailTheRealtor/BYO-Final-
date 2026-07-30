# G1 — Domain Core and Mirror Consolidation · Pre-Implementation Report

**Gate:** G1 (v1.2 §17) · **Type:** decision and characterisation gate — **not** an implementation task
**Status:** **PRE-IMPLEMENTATION REPORT ONLY. NO PRODUCTION CODE AUTHORISED.**
**Governed by:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md) · adopted per
[Adoption Record](./LOCATION-DNA-ENGINE-V1.2-ADOPTION-RECORD.md)
**Branch:** `architecture/location-dna-g1-domain-core`
**Base:** `73f32fe62` — `phase-2-spatial/ui-repair-maplibre-basemap` after the G0.1 fast-forward
**Audit baseline:** every measured claim below re-verified at `73f32fe62`
**Amended:** `cf53249ac` — findings §0 F-G1-3 … F-G1-6, §5.2, §6.2, §12 and §14 updated with what the
G1a characterisation suites **proved** rather than predicted. See §17 for the amendment record.

> This document decides nothing. It states what G1 requires, what the codebase actually contains, and
> which owner decisions block implementation. §17 G1 names four owner decisions and a characterisation
> prerequisite; the decisions remain open, and the prerequisite is **partially** discharged — see §6.2.

---

## 0. Findings that change G1's shape

Seven things the governing document does not currently say. **F-G1-1** and **F-G1-2** were established by
audit when this report was authored. **F-G1-3** through **F-G1-7** were proven behaviourally by the G1a
characterisation suites (`cf53249ac`) and are recorded here because they change the consolidation surface,
not merely the description of it.

Each carries the name of the test that proves it. Where a first prediction turned out to be wrong, the
correction is kept rather than quietly replaced — F-G1-6 is the example, and the corrected version
relocates the risk it describes.

### F-G1-1 · The "four byte-identical inline copies" claim is false, and the direction of consolidation is inverted

v1.2 §4.2 records five mirror-contract implementations — `HasSearchAreas` plus "4 byte-identical inline
copies" — and names the trait's four `empty()` sites as "the concrete defect that makes consolidation a
correctness task, not a tidiness task."

**The four inline copies are not byte-identical, and two of them are already correct.**
`TenantOfferListing.php:3339` and `TenantOfferListingEdit.php:2494` each carry an explicit,
approved divergence:

> `// NOTE: deliberately DIVERGES from HasSearchAreas::loadSearchAreas(), which uses empty() and`
> `// therefore cannot honour the invariant. Divergence is intentional and approved; the trait is`
> `// not changed here.`

Those two sites already use `array_key_exists('cities', …)` — exactly the §5.2 mechanism — and are
documented as load-time-only so a clear performed in the widget is not restored on the next save.

**Consequence for G1.** "Consolidate 5 → 1" cannot mean "delete the copies and keep the trait". The
trait is the *least* correct of the five for `cities`. Consolidating onto it would reintroduce the
mirror-resurrection bug in the two Tenant Offer workflows, which is a data-loss regression in live
workflows. The consolidation target must be the divergent behaviour, promoted into the single
implementation, with the trait's `empty()` sites converted as §16.3 requires — **five sites, not four;
see F-G1-3.**

This should be corrected in v1.2 §4.2 before implementation, so the plan of record does not describe
the copies as interchangeable.

**Now proven, not inferred.** When this finding was first written it rested on reading the divergence
comments. `G1aTraitPresenceSemanticsCharacterisationTest` (commit `cf53249ac`) exercises the trait
behaviourally through real EAV storage and confirms it: with a blob carrying `"cities": []` and a
populated legacy mirror, the trait returns the mirror's cities. The resurrection is real, not a
code-reading inference.

### F-G1-2 · The consumer count is now 43, not 42

§4.3 F-C1 measures 42 files reading or writing `location_dna_preferences`. Re-measured at `73f32fe62`:
**43**. G0.1 added `app/Services/LocationDna/PublicGeometryProjection.php`, which reads the contract.
The audit obligation in §5.3 and §16.3 is therefore 43 consumers, and the new one is a *read-only
projection* whose tolerance requirement is different in kind from the rest — it must tolerate omitted
keys **and** must never be fed back into canonical state (its own governance block forbids it).

### F-G1-3 · There are FIVE `empty()`-class sites in the trait, not four

§4.2 names four: lines 48, 71, 77, 103. A fifth produces the identical defect and is not named.

**Line 100**, in `hydrateDiscreteLocationFromBlob()`, guards the `state` write-back with
`trim((string) ($ldna['state'] ?? '')) !== ''` rather than `empty()`. Because the guard is spelled
differently it escaped §4.2's list, but its effect is exactly line 103's: a cleared `state` never
refreshes the discrete prop, so `saveSearchAreas()` writes the **previous** value into the `state` meta
key. Proven by `test_site100_clearing_state_writes_a_stale_discrete_mirror`.

**Consequence.** A consolidation that converts the four named sites and stops leaves the defect
half-fixed for `state`. **G1f must convert five sites.** v1.2 §4.2's count needs correcting.

### F-G1-4 · One save records a cleared dimension three different ways — so consolidation is not mechanical

This is the most consequential thing G1a proved, and it was not visible from reading §4.2.

Clearing `cities`, `counties` and `state` together in a single `saveSearchAreas()` call produces three
different outcomes in the persisted mirrors:

| Dimension | Mirror write | Honours the clear? | Why |
|---|---|---|---|
| `cities` | line 130, direct `$ldnaDecoded['cities'] ?? []` | **Yes** — mirrors as `[]` | no non-empty guard on the path |
| `counties` | line 123, from `$this->counties` via line 103 | **No** — keeps the previous value | guarded hydration skips empty blob values |
| `state` | line 126, from `$this->state` via line 100 | **No** — keeps the previous value | same, via the `trim(…) !== ''` spelling |

Proven by `test_one_save_records_cleared_dimensions_three_different_ways`.

**Consequence for the consolidation surface.** The three dimensions **do not share a code path today**,
so G1f cannot be a mechanical `empty()` → `array_key_exists()` substitution across five call sites. The
work is to route all three dimensions through one presence-honouring writer, and the mirror-write lines
(123, 126, 130) are part of the consolidation surface — not just the five read guards. §12's G1f row and
D-G1-5 are amended accordingly.

A related trap, proven by `test_full_clear_cycle_survives_only_because_the_cities_mirror_is_also_cleared`:
the site-48 resurrection is **latent, not constant**. Line 130 clears the `cities` mirror in the same
save, so a full clear cycle survives and the load-side defect stays masked. It bites only when the mirror
is stale. A consolidation that "fixed" site 48 while regressing line 130 would convert a latent defect
into a constant one, and that cycle test is what would catch it.

### F-G1-5 · `schema_version` is inert — S1 and S2 are indistinguishable, and an unknown version is not refused

§5.4's interpretation mode does not exist in any form on this path. Proven by two tests:

- A record stamped `schema_version: 2` loads **identically** to one with no stamp
  (`test_s2_schema_version_is_ignored_and_behaves_identically_to_s1`). The stamp round-trips intact, so
  the lazy upgrade §5.5 describes has a clean starting point — but nothing interprets it.
- A record stamped `99` is read **and rewritten** without refusal
  (`test_s2_unknown_future_schema_version_is_read_and_written_without_refusal`). §5.5 requires refuse-to-
  interpret, fail-loudly, read-only. This is precisely the "guessing risks recording a clear that was
  never intended" hazard L5 names.

Both are asserted rather than described, so they fail the moment G1c makes the hydrator honour the field
— which is when G1c needs to be told its new path is live.

### F-G1-6 · A corrupt blob diverges from its mirrors rather than destroying data

§5.4 S3 requires a corrupt blob to surface an error. It does not: `json_decode` returns null, `?? []`
absorbs it, and the load completes as though nothing was authored.

**The first draft of this report predicted that one save then destroys the corrupt bytes. That prediction
was wrong and the test failed.** `loadSearchAreas()` assigns the **raw** string to
`location_dna_preferences_json` (line 65), not the decoded array, and `saveSearchAreas()` persists that
raw string verbatim — so the corrupt bytes survive a load/save round trip intact and stay forensically
recoverable.

What degrades instead is the derived state: the decode yielded `[]`, so the `cities` mirror is rewritten
to `[]` in the same save while the blob still holds data. **Blob and mirror silently disagree.** That is
a divergence, not a deletion, and it relocates the S3 risk from data loss to consumer inconsistency —
every consumer of the discrete key sees an empty record while the authoritative bytes say otherwise.
Recorded because the corrected finding changes what G1c's error path must protect.

Two smaller shape facts fall out of the same fixtures. On a record with no blob,
`location_dna_preferences_json` holds boolean `false`, not `''` — `??` catches only null, and a consumer
type-hinting `string` breaks on it. And present-but-empty **is** faithfully distinguishable in storage
(`test_s4_present_but_empty_survives_storage_as_a_distinct_state`): the substrate is sound, and the
§5.2 violation lives entirely in the readers, not the column. G1c does not need a storage change.

### F-G1-7 · The server has no defence against an unmounted editor, and a no-op save can destroy a legacy mirror

Two proven behaviours that together form the strongest available argument for G1's server-authoritative
patch merging:

- **An unmounted editor destroys all saved geometry.** When the bridge delivers an empty string the
  server writes it straight over the authoritative blob — every polygon, radius search, city, county and
  note gone in one save, mirror emptied alongside
  (`test_unmounted_editor_empty_payload_destroys_all_saved_geometry`). There is no parseability check, no
  comparison against stored state, and no way to tell "the user cleared everything" from "the editor
  never loaded" — the two cases L5 says must never be conflated
  (`test_unmounted_editor_and_deliberate_clear_are_indistinguishable_to_consumers`). **The G0 guard is
  the only defence and it is entirely client-side JavaScript.**
- **A no-op save on a legacy record destroys the `cities` mirror.** Loading a mirror-only record and
  saving with no user edit rewrites the discrete keys from a blob that never contained them: the prefill
  held the recovered values, the persisted payload never did, and `cities` is written as `[]`
  (`test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror`). §16.6's "save-with-no-changes must
  change nothing" holds for the blob and **fails for the mirrors**.

---

## 1. §5 — Canonical state contract

### 1.1 Dimensions (§5.1)

Nine dimensions plus `subject_property`. Verified against the document:

| Dimension | Type | Canonical empty | Profile |
|---|---|---|---|
| `cities` | `string[]` | `[]` | Search Envelope |
| `zip_codes` | `string[]` | `[]` | Search Envelope |
| `counties` | `string[]` | `[]` | Search Envelope |
| `state` | `string` | `""` | Search Envelope |
| `polygons` | `object[]` (`label`, `path[{lat,lng}]`) | `[]` | Search Envelope |
| `radius_searches` | `object[]` (`lat`, `lng`, `radius_miles`, `address` XOR `label`) | `[]` | Search Envelope |
| `flexible_location` | `bool` | `false` | Search Envelope |
| `location_notes` | `string` | `""` | both |
| `subject_property` | `object` | `{}` | Subject Property |

Binding contract notes: `important_places_json` stays a separate meta key; **radius is miles**,
converted from metres by `1609.34` — a renderer reporting metres multiplies every saved radius by
~1,609, and this is a named adapter contract test (§16.6).

### 1.2 Presence versus absence (§5.2)

- **Absent** = never authored; legacy fallback may apply.
- **Present but empty** = explicitly cleared; no fallback may apply.
- **Mechanism is `array_key_exists()`. Never `empty()`.**
- The serializer must be able to **omit a key** distinguishably from emitting the canonical empty value.
  Today's serializer always writes all keys — that is the G1 change.
- The rule is **incomplete alone**: "absent means never authored" is only decidable if the reader knows
  whether the writer could omit keys. §5.4 resolves this.

### 1.3 Compatibility (§5.3) — byte-compatibility is withdrawn

| Guarantee | Statement |
|---|---|
| **G-C1** Key naming | No renames; every recognised key keeps its name |
| **G-C2** Value shape | Shapes and units unchanged where a key is present |
| **G-C3** Semantic round-trip | Decode → encode preserves presence set, values, and meaningful order |
| **G-C4** Reader tolerance | A v1.2 reader accepts every document any historical writer produced |
| **NOT guaranteed** | Byte-identical output, key ordering, presence of never-authored keys, whitespace |

Load-bearing consequences: omission **must never be produced by a failure** (L5) — a transport error,
unmounted editor, timeout or partial payload is an error to surface, never an absence to record; and
consumers that hash for cache keys must hash the **canonicalised** form, never raw bytes.

### 1.4 Record interpretation (§5.4) — five situations

| # | Situation | Detection | Missing key means |
|---|---|---|---|
| S1 | Legacy blob, all-keys writer | blob present, `schema_version` absent | Indeterminate → treat as absent; fallback MAY apply; a clear is **not expressible retroactively** |
| S2 | Canonical record | `schema_version` ≥ 2 | Absent = never authored; authoritative |
| S3 | No blob at all | meta key absent or unparseable | All dimensions absent; **corrupt blob is an error to surface**, never silently empty |
| S4 | Present but empty | `array_key_exists()` true, value canonically empty | Intentionally cleared; no fallback in any mode |
| S5 | Recovered from legacy mirror | value sourced from discrete meta at hydration | **Inherited, not authored**; never written back as authored |

Mode is read from `schema_version` **once, at hydration**; no consumer re-derives it. Upgrade is lazy
and write-triggered; reads never upgrade; there is no bulk migration.

### 1.5 `schema_version` (§5.5)

Controls **exactly one thing**: the interpretation mode. Absent ⇒ S1; `2` ⇒ S2. Every v1.2 write stamps
`2`. **Unknown future version (> 2) ⇒ refuse to interpret, fail loudly, read-only** — do not guess, do
not downgrade, do not write. The hydrator is the only reader of `schema_version`.

---

## 2. §6.2 — Operation vocabulary

**Exactly two operations.**

| Operation | Meaning | Value |
|---|---|---|
| `set` | Replace the entire dimension. Marks it present and authored. | Required; must **not** be the canonical empty value |
| `clear` | Record intentional clearing. Marks it present-but-empty (S4). | Forbidden |

Rejected for v1, with reasons on record: `replace` (synonym for `set` — duplicate source of truth,
L1); `append` / `remove` / `reorder` (require element identity the array dimensions lack —
`polygons` labels are non-unique, `radius_searches` entries have no identity); `merge` (undefined for
arrays of unlabelled geometry, and an undefined merge is how data is silently lost).

Three rules that constrain implementation:

1. **Array dimensions submit the whole dimension.** Editing one polygon submits `set` with the complete
   list — every write is a complete statement of intent, so a dropped element is impossible. Measured to
   round-trip at 1,200 vertices.
2. **Emptiness has one expression.** `set` with a canonically empty value is **rejected**
   (`empty_set_rejected`), never silently normalised. Scalars clear via `clear`, never `set("")`.
3. **"Absent from payload" is not an operation.** An adapter that cannot construct an envelope raises
   an error; it does not omit.

**Envelope (§6.1)** carries: `envelope_version`, `target`, `dimension` (exactly one), `operation`,
`value` (conditional), `workflow_context`, `expected_revision` (conditional), `provenance`
(conditional), `idempotency_key` (recommended). Deliberately absent: `user_id` (the IDOR boundary — taken
from the authenticated principal server-side), any capability list, any transport field. Multi-dimension
saves are a **batch** validated as a whole and applied atomically; a batch failing any envelope applies
none.

**Closed error-code set (§6.3):** `capability_denied`, `not_authorised`, `revision_conflict`,
`invalid_value`, `empty_set_rejected`, `provenance_required` / `provenance_forbidden`,
`unknown_schema_version`, `unavailable`. Each maps to a named test. `unavailable` exists so that
failure has somewhere to go other than data loss.

---

## 3. §6.4 — Concurrency mechanism

**Per-dimension optimistic concurrency using the revision token, with an append-only audit record.**
Dimension-scoped rather than document-scoped is the load-bearing choice: two people editing *different*
dimensions must not conflict; two editing the *same* dimension is a genuine conflict that must not be
silently resolved.

| Scenario | Required behaviour |
|---|---|
| Two browser tabs | First write wins and advances the token; second write of the **same** dimension fails `revision_conflict` and the adapter reloads and re-presents. Different dimensions both succeed. |
| User and agent concurrently | Same mechanism. Authorisation decides *whether*; concurrency decides *what happens when both do*. Audit retains who wrote which dimension when. |
| Autosave racing manual save | Losing autosave is **discarded, never retried with a refreshed token** — retrying would let a background process overwrite a deliberate foreground save. |
| Stale draft | Fails `revision_conflict` on diverged dimensions **only**; undiverged dimensions still apply; the user is shown what could not be applied. Silent discard prohibited. |
| Import racing an authored change | Resolved by **precedence, not timing**: import never overwrites an authored dimension; it writes only absent dimensions. |

`expected_revision` is **required** for all interactive authenticated edits; **not required** for the
first authoring write to an absent dimension, for import writes, and for administrative correction
(explicitly last-write-wins, audited). Enforcement compares tokens **inside the same transaction** that
applies the patch.

---

## 4. §18 — Withdrawal of `neighborhoods` and `commute`

| Item | v1.2 decision | Reason of record |
|---|---|---|
| **`commute`** | **Withdrawn from v1** entirely | Its justification was "cheap insurance against a later schema change", but §5.3 withdraws byte-compatibility and §5.5 defines lazy upgrade, so adding a dimension later is no longer a migration event. No routing provider evaluated (R10). A dimension with no UI, no provider and no consumer is exactly the speculative schema the standard rejects. |
| **`neighborhoods`** | **Withdrawn from the contract; retained as read-tolerant** | No writer writes it and no consumer reads it. Readers continue to **accept it where present in legacy data** (G-C4) so nothing breaks; nothing new writes it. Reinstating is a config + contract addition when a consumer exists. |

Also settled in §18 and relevant to G1's surface: the empty `'overrides' => []` capability mechanism is
**removed**; `dimension_meta` **stays removed**; `schema_version` is **retained with defined behaviour**.

**Net contract for v1: the nine existing dimensions plus `subject_property`, and nothing else.**

**Implementation consequence.** `neighborhoods` needs a *read-tolerance* test and an assertion that no
writer emits it. `commute` needs no code at all — and adding a placeholder for it would violate §18.

---

## 5. Mirror consolidation — inventory and affected workflows

### 5.1 The 5 → 1 inventory, as it actually exists at `73f32fe62`

| # | Implementation | File | Mechanism for `cities` | Correct per §5.2? |
|---|---|---|---|---|
| 1 | `HasSearchAreas` trait (132 lines) | `app/Http/Livewire/Concerns/HasSearchAreas.php` | `empty()` | **No** |
| 2 | Inline copy | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | inline, no trait | to be characterised |
| 3 | Inline copy | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` | inline, no trait | to be characterised |
| 4 | Divergent inline | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php:3339` | `array_key_exists()` | **Yes — already correct** |
| 5 | Divergent inline | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php:2494` | `array_key_exists()` | **Yes — already correct** |

The trait's surface is three methods: `loadSearchAreas($auction)`, `hydrateDiscreteLocationFromBlob()`,
`saveSearchAreas($auction)`.

### 5.2 The `HasSearchAreas` presence-guard sites — FIVE, confirmed at this commit

The document's measured line numbers (48, 71, 77, 103) are **exact**, but the list is incomplete: line
100 is a fifth site of the same defect class, spelled differently (F-G1-3). Each reads blob state and
must become presence semantics:

| Line | Method | Code | Required conversion | Characterised by |
|---|---|---|---|---|
| 48 | `loadSearchAreas` | `if (empty($ldna['cities'] ?? [])) {` | `! array_key_exists('cities', $ldna)` | `test_site48_*` |
| 71 | `loadSearchAreas` | `&& empty($this->existingLocationDna['state'] ?? '')` | `! array_key_exists('state', …)` | `test_site71_*` |
| 77 | `loadSearchAreas` | `&& empty($this->existingLocationDna['counties'] ?? [])` | `! array_key_exists('counties', …)` | `test_site77_*` |
| **100** | `hydrateDiscreteLocationFromBlob` | `trim((string) ($ldna['state'] ?? '')) !== ''` | presence check on `state` | `test_site100_*` |
| 103 | `hydrateDiscreteLocationFromBlob` | `&& !empty($ldna['counties'] ?? [])` | `array_key_exists('counties', $ldna)` | `test_site103_*` |

**The consolidation surface is wider than these five guards.** Per F-G1-4, the three mirror-write lines
must change with them, because they are where the divergent outcomes are actually produced:

| Line | Writes | Current behaviour on a clear |
|---|---|---|
| 123 | `counties` meta from `$this->counties` | stale value persists |
| 126 | `state` meta from `$this->state` | stale value persists |
| 130 | `cities` meta from the decoded blob | correctly `[]` — **must not regress** |

**Do not convert lines 58, 72 and 78.** They are `!empty()` guards on *legacy mirror input and local
component state*, not on blob presence — converting them would change unrelated behaviour. No G1a test
asserts anything about them, and G1f must leave them alone. §16.3 requires **a test per site** proving
cleared values are no longer resurrected; that is **five** tests, not four and not eight.

### 5.3 Workflows affected

Eight components across four Search-Envelope workflows (Buyer/Tenant × Offer/Hire). Sizes matter for
consolidation risk — these are among the largest files in the codebase:

| Workflow | Component | Lines | Mirror source |
|---|---|---|---|
| Hire Buyer Agent | `HireBuyerAgent/BuyerAgentAuction.php` | 2,527 | trait |
| Hire Buyer Agent | `HireBuyerAgent/BuyerAgentAuctionEdit.php` | 2,271 | trait |
| Hire Tenant Agent | `TenantAgentAuction.php` | 5,299 | trait |
| Hire Tenant Agent | `TenantAgentAuctionEdit.php` | 4,218 | trait |
| Buyer Offer Listing | `OfferListing/Buyer/BuyerOfferListing.php` | 3,081 | **inline** |
| Buyer Offer Listing | `OfferListing/Buyer/BuyerOfferListingEdit.php` | 2,937 | **inline** |
| Tenant Offer Listing | `OfferListing/Tenant/TenantOfferListing.php` | 5,323 | trait **+ divergent inline** |
| Tenant Offer Listing | `OfferListing/Tenant/TenantOfferListingEdit.php` | 4,098 | trait **+ divergent inline** |

Note `TenantAgentAuction` is frozen-by-convention from the `HasListingLifecycle` refactor (CLAUDE.md).
That exclusion is about the lifecycle trait, not about `HasSearchAreas`, but it signals a component that
has resisted refactoring and should be sequenced late.

---

## 6. Characterisation coverage — existing and missing

§17 G1 states the prerequisite plainly: characterisation coverage must exist for each workflow the
consolidation touches **before it is touched**, and §16.3 adds that *"a workflow with no
characterisation may not be migrated."*

### 6.1 Existing coverage, mapped to components

| Suite | Tests | Components exercised |
|---|---|---|
| `tests/Feature/Spatial/SearchAreasPersistenceCharacterisationTest.php` | 9 | `TenantAgentAuction` only |
| `tests/Feature/Spatial/TenantOfferCitiesMirrorTest.php` | 15 | `BuyerOfferListing`, `BuyerOfferListingEdit`, `TenantOfferListing`, `TenantOfferListingEdit`, `TenantAgentAuction` |
| `tests/Feature/Offers/HireSearchAreasParityTest.php` | 9 | `BuyerAgentAuction`, `BuyerAgentAuctionEdit`, `TenantAgentAuction`, `TenantAgentAuctionEdit` |
| `tests/Feature/Offers/SearchAreasStateCountyRoundTripTest.php` | 4 | `BuyerAgentAuction`, `BuyerOfferListingEdit` |
| `tests/Unit/Spatial/SearchAreasGeometryContractTest.php` | 16 | `BuyerAgentAuction`, `TenantAgentAuction` |
| `tests/Unit/Spatial/SearchAreasGeometryGuardTest.php` | — | structural guard |
| `tests/Unit/Spatial/SearchAreasWidgetContractTest.php` | — | widget contract |

**All eight components have some coverage.** That is better than §16.3's phrasing assumes, and it means
no workflow is categorically blocked. But coverage is uneven per dimension.

### 6.2 The gap list — status after G1a

G1a added three suites in commit `cf53249ac`, 28 tests, all green:

| Suite | Tests |
|---|---|
| `tests/Feature/Spatial/G1aTraitPresenceSemanticsCharacterisationTest.php` | 12 |
| `tests/Feature/Spatial/G1aRecordInterpretationCharacterisationTest.php` | 10 |
| `tests/Feature/Spatial/G1aCrossDimensionPreservationCharacterisationTest.php` | 6 |

**Closed by G1a:**

| Gap | How |
|---|---|
| No suite covers the `empty()` sites | All **five** sites now have behavioural coverage through real EAV storage, one test per site plus a control per site. Previously pinned only structurally, by a grep for `empty(`. |
| No S1–S5 fixture set | All five situations now have fixtures, including corrupt blob, absent blob, unknown future `schema_version`, and the inherited-vs-authored pair. |
| No cross-dimension preservation test with the editor unmounted | Both cases covered — mounted preserves every other dimension byte-identically; unmounted destroys everything, and the indistinguishability from a deliberate clear is asserted separately. |
| No save-with-no-changes test | Covered, and it **split**: byte-stable for the blob, destructive for the mirrors on a legacy record (F-G1-7). |

**Still open, and correctly out of G1a's scope:**

| Gap | Belongs to |
|---|---|
| No consumer-tolerance suite — 43 consumers, none asserted to tolerate omitted keys | **G1b** |
| No revision-token tests | **G1c** — the token does not exist yet, so there is nothing to characterise |
| No capability-resolver tests | **G1d** — same reason |

**Still open, and genuinely G1a residue — G1f is NOT unblocked for these workflows:**

| Residue | Why it matters |
|---|---|
| **The two Buyer Offer inline copies are not behaviourally characterised.** G1a exercised the trait through a thin host; `BuyerOfferListing.php` and `BuyerOfferListingEdit.php` carry their own inline implementations that no G1a test executes. | §16.3: "a workflow with no characterisation may not be migrated." These two are the copies whose correctness F-G1-1 lists as *"to be characterised"* — still unknown. |
| **Per-component persistence characterisation remains 1-of-8.** `SearchAreasPersistenceCharacterisationTest` still covers only `TenantAgentAuction`. G1a broadened *dimension* coverage, not *component* coverage. | The parity evidence L11 requires is per workflow. |
| **No factory exists for the Buyer/Hire models.** `HireSearchAreasParityTest` works around this with `forceFill()` and `Livewire::test()`; a G1a follow-up would need the same vehicle rather than the thin host. | Practical blocker on closing the two residues above. |

**Assessment.** The characterisation prerequisite is discharged for the **trait** and for the **storage
substrate**, which is what G1c and the §5.4 fixtures needed. It is **not** discharged for the two Buyer
Offer inline copies. G1f may proceed workflow-by-workflow only for workflows whose implementation is
characterised; the Buyer Offer pair needs a further G1a increment first.

### 6.3 Pre-existing test failure, unrelated to G1

`tests/Feature/Offers/SearchAreasStateCountyRoundTripTest.php` fails 1 of 4:
`SQLSTATE[HY000]: near "ILIKE": syntax error`, raised from
`BuyerOfferListingEdit::getPlaceSuggestions()` at line 980. `ILIKE` is PostgreSQL syntax and the suite
runs on SQLite. Reproduced identically in the G0.1 worktree with no G1a file present, so it predates all
G1 work. Fixing it means changing production code and is outside G1a; recorded here so a future reader
does not attribute it to consolidation.

---

## 7. The 43-consumer tolerance audit

Every file reading or writing `location_dna_preferences` at `73f32fe62`. Each must be asserted to
tolerate **omitted** keys (§5.3, §16.3). Grouped by §4.3's consumer classes:

**Editing UI (10)** — `Concerns/HasSearchAreas.php` · `HireBuyerAgent/BuyerAgentAuction.php` ·
`HireBuyerAgent/BuyerAgentAuctionEdit.php` · `OfferListing/Buyer/BuyerOfferListing.php` ·
`OfferListing/Buyer/BuyerOfferListingEdit.php` · `OfferListing/Tenant/TenantOfferListing.php` ·
`OfferListing/Tenant/TenantOfferListingEdit.php` · `TenantAgentAuction.php` ·
`TenantAgentAuctionEdit.php` · `partials/location-dna/map-input.blade.php`

**Livewire bridge / tab partials (5)** — `partials/location-dna/search-areas-bridge.blade.php` ·
`livewire/hire-buyer-agent/…/property-preferences.blade.php` ·
`livewire/offer-listing/offer-buyer-tabs/…/property-preferences.blade.php` ·
`livewire/offer-listing/offer-tenant-tabs/…/property-details.blade.php` ·
`livewire/tenant-agent-auction-tabs/…/property-details.blade.php`

**Read-only display (5)** — `components/location-dna-map.blade.php` ·
`LocationDna/LocationDnaChipPresenter.php` · `tenant_criteria/view.blade.php` ·
`tenant_criteria/add-bid.blade.php` · **`LocationDna/PublicGeometryProjection.php` (new in G0.1)**

**Public view controllers (4)** — `BuyerCriteriaAuctionController.php` ·
`BuyerOfferListingController.php` · `TenantCriteriaAuctionController.php` ·
`TenantOfferListingController.php`

**Matching (5)** — `LocationDna/LocationMatchEngine.php` ·
`LocationDna/LocationMatchAuctionExtractor.php` · `LocationDna/LocationPreferenceAnalyzer.php` ·
`LocationDna/LocationMatchIntegrationService.php` · `Jobs/ComputeCompatibilityScore.php`

**Criteria loading — legacy path (5)** — `Stellar/BuyerCriteriaLoader.php` ·
`Stellar/TenantCriteriaLoader.php` · `Stellar/BuyerOfferListingCriteriaLoader.php` ·
`Stellar/TenantOfferListingCriteriaLoader.php` · `Stellar/CriteriaListingResolver.php`

**Enrichment (5)** — `LocationDna/BoundaryLookupService.php` ·
`LocationDna/FloodZoneLookupService.php` · `LocationDna/SchoolDistrictLookupService.php` ·
`LocationDna/LocationDnaEnrichmentRunner.php` · `LocationDna/LocationIntelligenceComposer.php`

**Summaries / PDF (2)** — `AcceptedBidSummaryService.php` · `BuyerAcceptedBidSummaryService.php`

**Backfill / seed (2)** — `Console/Commands/BackfillLocationSnapshots.php` ·
`database/seeders/LocationDnaTestSeeder.php`

**Total: 43.** Two classes carry elevated risk: the **matching** consumers read canonical state outside
any request cycle (no hydrator in scope today), and **outbound export** via Bridge `PolygonBoundingBox`
means geometry leaves the system — §4.3 lists it as a consumer class even though the file does not name
the meta key directly, so it must be audited by behaviour, not by grep.

---

## 8. Provenance requirements (§10)

Provenance answers **exactly one question: which provider produced this value?** It is not a state
machine and never encodes authored/cleared/absent.

| Subject | Provenance | Note |
|---|---|---|
| `subject_property` | **per field group** — `provider` + `resolution` (`unresolved` / `zip_centroid` / `rooftop`) | one resolution act |
| Geocoded address | **per entry** | needed for the Google-separation test |
| Radius-search **centre** | **per entry, only when a provider resolved it from an address** | the radius itself is user intent and carries none |
| **Polygons** | **none — forbidden** | entirely user-authored; attaching provider metadata would create a place for semantics to leak |
| `cities` / `zip_codes` / `counties` / `state` | **none in the blob** | provenance belongs on the provider cache record, not the user's choice |
| POIs | per entry **in the POI store** | already `CanonicalPoiAssembler` / `CanonicalField` |
| Imported MLS facts | per entry + `source: imported` in the audit trail | §8.2 rule 2 |
| Normalised boundaries | per boundary record | registry `license` key |
| Provider-derived coordinates anywhere | **per entry, mandatory** | the enforcement hook for L9 |

**The negative tests matter as much as the positive ones**: polygons must carry no provider metadata,
and unlabelled historical coordinates must be treated as **Google-provenance until proven otherwise**.
The measured hazard is already persisted — `accepted_bid_summaries.google_place_id` beside
`property_lat` / `property_lng`, plus `google_place_id` listing meta and `prop_google_place_id` offer
meta. This is a **hard prerequisite of the subject-property renderer gate**, not cleanup.

---

## 9. Revision-token requirements (§5.6)

Deterministic hash of the **canonicalised** document, by the same pattern as `CriteriaHashService`:
recursive key sort, order-independent list normalisation, then SHA-256.

- **Deterministic** — identical meaning ⇒ identical token, regardless of byte layout or key order. This
  is what lets §5.3 withdraw the byte guarantee without losing change detection.
- **Computed, not stored** — no new column for v1.
- **Per-document and per-dimension** — document token for whole-record concurrency; per-dimension token
  so a conflict scopes to the dimension that actually diverged (§6.4).
- **Independent of `schema_version`** — a lazy upgrade that changes no values changes no dimension token.

Serves three purposes and carries no semantics of its own: optimistic concurrency (§6.4), projection
staleness detection (§15), audit history.

---

## 10. Capability-resolver requirements (§7)

- **Context is an open map, not a fixed compound key.** Recognised: `listing_family`,
  `participant_role`, `transaction_mode`, `property_category`, `residential_or_commercial`,
  `lifecycle_state`, `source`. **v1 config uses only `listing_family` and `participant_role`**; the rest
  are recognised-but-unused. *The generalisation is in the resolver signature, not the configuration* —
  adding `property_category` later must mean adding config rows, never editing every consumer.
- **Deny by default.** Unrecognised context, missing profile, or a typo resolves to **deny**.
- **Most-specific match wins**, with a documented, deterministic specificity ordering.
- **No override mechanism** — v1.1's `'overrides' => []` is removed.
- **Profiles stay explicit allow-maps** — listing both `true` and `false` is verbose but makes
  divergence diffable. Deny-by-default is the fallback, not a licence to leave dimensions unstated.
- **Enforcement:** config declares, resolver decides, **the server rejects** (`capability_denied`). Which
  Blade file rendered is not an authorisation input (L6). Authorisation is separate and additional, per
  `(principal, record, dimension)`; `loadDraft()`'s existing `Auth::id()` scoping must be preserved and
  extended per dimension.

---

## 11. Serializer and hydrator boundaries

The single most consequential structural rule in G1:

> **The domain core must not depend on Livewire, Blade, HTTP or JavaScript types.** It accepts an
> envelope and returns a result. Livewire is one adapter.

| Component | Owns | Must not |
|---|---|---|
| **Hydrator** | The **only** reader of `schema_version`; determines interpretation mode **once**; performs S5 mirror recovery marked *inherited, not authored*; surfaces a corrupt blob as an error | Upgrade on read; let any consumer re-derive mode; promote inherited values to authored |
| **Serializer** | Omits never-authored keys distinguishably from canonical-empty; stamps `schema_version: 2` on every write; lazy-upgrades on first v1.2 write by recording the observed presence set | Emit omission as a result of failure (L5); guarantee byte-identity; write when the version is unknown |
| **Domain core** | Envelope validation, capability check, authorisation, token comparison inside the applying transaction, batch atomicity | Reference any transport, framework or view type |
| **Adapters** | Wrap the envelope for Livewire / form POST / Null | Re-implement interpretation — all receive the same result shape |

Two enforcement obligations: the hydrator must be the **single entry point**, proven by a test asserting
**no consumer of the 43 reads the raw blob directly**; and one shared adapter contract suite must run
against every adapter including a `NullAdapter`, asserting identical results for identical envelopes.

---

## 12. Proposed G1 implementation sequence

G1 is too large for one unit — canonical core, plus a 5 → 1 consolidation across eight components
totalling ~29,000 lines, plus a 43-consumer audit. Proposed decomposition into sub-gates, each with its
own stop condition and **no page behaviour changed**:

| Sub-gate | Scope | Depends on | Stop condition |
|---|---|---|---|
| **G1a · Characterisation completion** — **PARTIALLY COMPLETE (`cf53249ac`)** | Close every gap in §6.2. Tests only, no production change. Delivered: five-site presence semantics, S1–S5 fixtures, mounted/unmounted cross-dimension preservation, save-with-no-changes. **Residue: the two Buyer Offer inline copies and 7-of-8 per-component persistence — see §6.2.** | — | All eight workflows characterised; suite green; zero production files touched. **Not yet met** — residue outstanding |
| **G1b · Consumer-tolerance audit** | Read-only audit of the 43 consumers for omitted-key tolerance (R11). Findings + failing tests for real defects. | G1a | Every consumer classified tolerant / defective, with evidence |
| **G1c · Domain core, additive only** | Canonical state object, serializer with omission, hydrator with interpretation modes, revision token, envelope + two operations, result/error shapes. Nothing wired to any workflow. | owner decisions D-G1-1…4 | Core exists with §16.2 coverage; **no existing code path calls it** |
| **G1d · Capability resolver + config** | Resolver with open context map, config profiles for the eight workflows, deny-by-default, server-side rejection. Not yet enforced on live writes. | G1c | 8 workflows × every dimension tested; resolver inert in production |
| **G1e · Provenance rules** | Provenance recording per §10.1, including the forbidden-on-polygons negative test and unlabelled-coordinates-are-Google default. | G1c | §16.5 provenance coverage green |
| **G1f · Mirror consolidation, one workflow at a time** | Consolidate 5 → 1 onto the **divergent (correct)** behaviour — see F-G1-1 — converting the **five** presence guards (48, 71, 77, 100, 103) *and* routing the three mirror writes (123, 126, 130) through one presence-honouring writer, per F-G1-3 and F-G1-4. One workflow per commit, parity-gated against its G1a characterisation. | G1a **incl. residue**, G1c | Per workflow: characterisation identical before and after; **no page behaviour changed**; line 130's correct clear-mirroring not regressed |
| **G1g · Adapter contract** | Livewire adapter + form POST adapter + `NullAdapter` behind one shared contract suite. | G1c, G1d | Identical envelopes ⇒ identical results across all adapters |

Sequencing rationale: **G1a strictly first** (the §17 prerequisite, and the only thing that makes G1f
provable); the audit before the core so the core's tolerance requirements are informed by real defects;
**G1f last among the code changes** because it is the only sub-gate that touches live server paths.

### 12.1 Files likely to be touched

**New (G1c–G1e):** a domain-core namespace — canonical state, serializer, hydrator, revision-token
service, envelope, operation vocabulary, result/error shapes, capability resolver, provenance recorder —
plus `config/` capability profiles. Exact paths to be proposed in the G1c plan, not guessed here.

**Modified (G1f–G1g), in dependency order:**

- `app/Http/Livewire/Concerns/HasSearchAreas.php` — four `empty()` sites; likely absorbed entirely
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` · `…/TenantOfferListingEdit.php` — divergent inline copies become the consolidated behaviour
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` · `…/BuyerOfferListingEdit.php` — inline copies removed
- `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php` · `…/BuyerAgentAuctionEdit.php` — trait consumers
- `app/Http/Livewire/TenantAgentAuction.php` · `TenantAgentAuctionEdit.php` — trait consumers; sequence last
- `resources/views/partials/location-dna/search-areas-bridge.blade.php` — 3 bridge implementations → 1
- Consumer fixes arising from G1b, scope unknown until that audit runs

**Explicitly not touched in G1:** any client JS, any renderer, `map-input.blade.php`'s editing
behaviour, any provider, any public API — and, per G0.1, the public-view projection seam, which G1 must
preserve rather than route around.

---

## 13. Conflict risk with current parallel worktrees

Live worktrees at the time of writing:

| Worktree | Branch / HEAD | State |
|---|---|---|
| `/home/runner/workspace` | `phase-2-spatial/ui-repair-maplibre-basemap` @ `73f32fe62` | primary; 2 unrelated modified + 3 untracked |
| `/home/runner/worktrees/fix-public-geometry-containment` | `fix/public-geometry-containment` @ `73f32fe62` | clean; G0.1 preserved |
| `/home/runner/worktrees/location-dna-g1-domain-core` | `architecture/location-dna-g1-domain-core` @ `73f32fe62` | this worktree |
| `/home/runner/worktrees/ux-hire-agent-create-offer-parity` | `ux/hire-agent-create-offer-parity` | **active — advanced twice during this session** |
| `/home/runner/worktrees/baseline-m1-8e9f572c9` | detached | transient baseline |
| `/home/runner/worktrees/g01-baseline` | detached @ `9377acdad` | registered but absent from disk (`prunable`) |

**The material risk is `ux/hire-agent-create-offer-parity`.**

- Diverged from the base at `10037715a` (2026-07-24); now **21 behind / 23 ahead**.
- It **does not contain G0.1**.
- It modifies **all four** Buyer/Tenant Offer Listing Livewire components — precisely four of the five
  mirror implementations G1f must consolidate.
- It also modifies `BuyerOfferListingController.php` and `TenantOfferListingController.php`, both of
  which G0.1 changed.

**Mitigating measurement:** across `BuyerOfferListing.php` and `TenantOfferListing.php`, that branch
changes **zero** mirror-related lines (no `location_dna`, `existingLocationDna`, `searchArea` or
`cities` additions or deletions). So the risk is **mechanical, not semantic** — competing edits to
different regions of the same very large files, not competing mirror logic.

**Recommended handling:**

1. Land `ux/hire-agent-create-offer-parity` **before** G1f, or explicitly accept a rebase of it onto
   post-G1f code. Running both concurrently through the same four files is the avoidable risk.
2. When that branch integrates, verify G0.1's four controller hunks survive — it has adjacent edits in
   two of them and does not currently contain the containment.
3. G1a and G1b are **safe to run concurrently** with it: tests-only and read-only respectively, with no
   file overlap.
4. `g01-baseline` is registered but absent from disk. It is stale and harmless; do **not** run
   `git worktree prune` to clear it, since that would also clear other registrations — remove it
   specifically if wanted.

---

## 14. Owner decision checklist — all blocking G1c and beyond

| # | Decision | §  | What is being asked | Blocks |
|---|---|---|---|---|
| **D-G1-1** | Approve the canonical state contract | §5 | Nine dimensions + `subject_property`; presence-vs-absence via `array_key_exists()`; withdrawal of the byte-compatibility guarantee in favour of G-C1–G-C4; the five interpretation situations S1–S5; `schema_version` semantics incl. **refuse-to-interpret above 2** | G1c |
| **D-G1-2** | Approve the operation vocabulary | §6.2 | Exactly `set` and `clear`; rejection of `replace`/`append`/`remove`/`reorder`/`merge`; whole-dimension submission for arrays; `set` with empty value **rejected** rather than normalised; batch-atomic multi-dimension saves | G1c |
| **D-G1-3** | Approve the concurrency mechanism | §6.4 | Per-dimension optimistic concurrency on the revision token; append-only audit; **losing autosave discarded, never retried**; partial application of a stale draft with the user shown what failed; import-never-overwrites-authored precedence | G1c |
| **D-G1-4** | Confirm the §18 withdrawals | §18 | `commute` withdrawn entirely (no placeholder); `neighborhoods` withdrawn from the contract but **read-tolerant** for legacy data; `'overrides' => []` removed | G1c |
| **D-G1-5** | *(new — arising from F-G1-1, now evidenced by F-G1-3 / F-G1-4)* Approve the consolidation direction **and surface** | §4.2 | Consolidate **onto the divergent `array_key_exists()` behaviour**, not onto the trait. Correct §4.2's "4 byte-identical inline copies" claim **and its count of four guard sites — there are five (line 100)**. Accept that the surface includes the three mirror-write lines (123, 126, 130), because the three dimensions do not share a code path today, so the change is not a mechanical substitution | G1f |
| **D-G1-6** | *(new — arising from §13)* Decide branch sequencing | — | Whether `ux/hire-agent-create-offer-parity` lands before G1f, or G1f proceeds and that branch rebases | G1f |

**Not owner decisions, but prerequisites:** G1a characterisation completion (§17 G1 prerequisite, §16.3
"a workflow with no characterisation may not be migrated"), and the G1b consumer audit, which §5.3
states is "a G1 deliverable, not an assumption."

---

## 15. Stop conditions for G1

Carried from §17 G1 and extended for the decomposition:

1. Report with diffs and tests, **no page behaviour changed**, before anything else.
2. Any sub-gate that would change observable page behaviour stops and reports instead.
3. A workflow without complete G1a characterisation is **not** migrated in G1f. Per §6.2 this currently
   excludes `BuyerOfferListing` and `BuyerOfferListingEdit`.
4. Consolidation that would regress the two already-correct Tenant Offer divergences stops (F-G1-1), and
   likewise anything that would regress line 130's correct clear-mirroring (F-G1-4).
5. Any requirement to touch client JS, a renderer, a provider or a public API means the work has left
   G1 scope.
6. Any need to alter G0.1's public-view projection seam stops and reports — G0.1 is committed and its
   regression guard is binding.
7. `unavailable` must never be convertible to a clear; if any design pressure pushes that way, stop (L5).

---

## 16. Authorisation status

**No G1 production code is implemented and none is authorised.** Decisions D-G1-1 through D-G1-6 remain
open; none has been resolved or assumed by G1a. What exists on this branch is one documentation commit
and one tests-only commit.

Remaining sequence:

1. Decide D-G1-1 … D-G1-4 (the four §17 owner decisions), plus D-G1-5 and D-G1-6 raised here.
2. Correct v1.2 §4.2 (the byte-identical claim **and** the count of four guard sites → five) and
   §4.3 F-C1 (42 → 43).
3. ~~Authorise **G1a only**~~ — done, partially, in `cf53249ac`. Either authorise the **G1a residue**
   (Buyer Offer inline copies; per-component persistence) or accept that G1f cannot migrate those two
   workflows.
4. **G1b is the natural next step**: read-only, needs none of the six decisions, and its findings inform
   D-G1-1's tolerance requirements.

### 16.1 A tripwire that WILL fire in G1f

`TenantOfferCitiesMirrorTest::test_hire_trait_semantics_are_unchanged()` asserts that
`HasSearchAreas.php` **contains** `empty(` and **does not contain** `array_key_exists`. It was written
deliberately, to force the Hire flows to be re-verified if anyone "aligned" the trait silently.

G1f converting the five guard sites **will** fail it. That is the test doing its job, not a regression.
Updating it is part of G1f's own scope, and the re-verification it demands is exactly the parity check
G1f owes the four Hire workflows. Recorded here so nobody treats a red run as a reason to stop.

---

## 17. Amendment record

| Amendment | Commit | Change |
|---|---|---|
| Original report | `eef66f570` | As authored, before any characterisation existed |
| **G1a findings** | *this commit* | §0 gains F-G1-3 … F-G1-7. F-G1-1 marked proven rather than inferred. §5.2 four sites → **five**, plus the mirror-write surface. §6.2 rewritten as closed / deferred / **residue**, with the pre-existing `ILIKE` failure recorded in new §6.3. §12 G1a marked partially complete and G1f's scope widened. D-G1-5 extended to the five-site count and the non-mechanical surface. §15 stop conditions 3 and 4 tightened. §16 rewritten; §16.1 tripwire added |

**Nothing in this amendment changes a decision, authorises a gate, or touches production code.** Every
addition is a statement of measured behaviour with a named test behind it.
