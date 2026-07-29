# Location DNA Engine v1.2 — Governing Architecture (proposal)

**Status:** revision proposal · **not yet adopted** · no code written
**Supersedes:** `LOCATION-DNA-ENGINE-V1.1.md` (v1.1) and `LOCATION-DNA-ENGINE-V1.md` (v1.0), both retained unchanged as historical records
**Prepared:** 2026-07-29 · bounded correction of v1.1 following governance review
**Baseline commits (untouched):** `387a971d8` G0 guard · `248403874` city-mirror fix · `6fd0dae80` Phase 2B characterisation

> **Evidence convention.**
> **[MEASURED]** — verified in this repository at `387a971d8`.
> **[DERIVED]** — computed from a measured value; method shown.
> **[ESTIMATE]** — engineering judgement, unverified. Do not plan budgets on these.
> **[UNVERIFIED]** — an external claim that must be confirmed before it is relied on.
> **[NOT PRESENT]** — specified here, but no implementation exists today.

> **What v1.2 is.** v1.2 is a *bounded correction* of v1.1. It restores three sections that were
> lost between v1.0 and v1.1 (testing strategy, AI boundary, public contract surface), resolves six
> named contradictions, removes two structural blockers, and defines items v1.1 left ambiguous.
> It does not reopen the settled decisions listed in §3.
>
> **What v1.2 is not.** It is not an implementation plan and contains no implementation. Contract
> shapes shown below are *specifications of shape*, not proposed code. Nothing here authorises work;
> §17 defines what authorisation would look like, gate by gate.

---

## 1. Governing principles

These fifteen laws govern the engine. Every later section is an application of one of them. Where a
later section appears to conflict with a law, the law wins and the section is a defect.

| # | Law | Meaning in this system |
|---|---|---|
| **L1** | **One fact, one authoritative home.** | A fact is recorded in exactly one place. Mirrors, projections and caches are derivatives that can be rebuilt and never arbitrate. |
| **L2** | **Semantics live on the server.** | Absence, emptiness, merge precedence and validity are decided once, server-side, in the language that has test coverage. |
| **L3** | **Absence and emptiness are different facts.** | Absent = never authored (fallback may apply). Present-but-empty = intentionally cleared (no fallback). |
| **L4** | **Renderers never own canonical state.** | A renderer reflects state. A renderer that fails to load can destroy UI state and nothing else. |
| **L5** | **Unavailability is never user intent.** | "I could not load it" must never be recorded as "the user deleted it". |
| **L6** | **Capabilities are declared and server-enforced.** | Configuration declares; the server decides and rejects. Which Blade file rendered is not an authorisation input. |
| **L7** | **Canonical transport is distinct from optimised storage.** | The canonical contract is a transport/UI contract. Spatial indexes are derived read paths. |
| **L8** | **Providers are replaceable; canonical contracts are stable.** | Swapping a provider must not change the canonical shape or require consumers to change. |
| **L9** | **No Google fallback, and no cross-provider content misuse.** | A failed provider degrades visibly. Google-derived content is never displayed over a non-Google basemap, and never silently substituted. |
| **L10** | **Nationwide architecture does not require nationwide day-one data.** | No region-specific branching in code; coverage grows by configuration and data. |
| **L11** | **Wrap before replacement; prove parity before retirement.** | Legacy paths are adapted behind a seam and retired only after per-workflow parity is demonstrated. |
| **L12** | **Every invariant has an enforcement mechanism and a test.** | An invariant with no named enforcement point and no named test category is an aspiration, not an invariant. |
| **L13** | **AI interprets normalised output and never owns canonical state.** | AI is downstream of the contract. It never authors geometry and never decides semantics. |
| **L14** | **User-drawn geometry is sensitive.** | It discloses where a person wants to live, near which schools, at what budget. Exact vertices are never exposed publicly. |
| **L15** | **Crime and safety scoring is a documented refusal.** | In a housing context it correlates with protected characteristics and carries Fair Housing and steering exposure. Recorded as a refusal, not a deferral. |

---

## 2. Executive summary

The Location DNA Engine is **one canonical, server-owned state contract with role-configured UI
surfaces** — not one universal map component, and not eight map implementations. That conclusion is
unchanged from v1.1 and is not reopened.

v1.2 changes what surrounds that conclusion. Six points drive this revision:

1. **The contract has far more consumers than v1.1 described.** `location_dna_preferences` is read or
   written by **42 files** **[MEASURED]** — not the "8 include sites + 6 view pages" the earlier
   documents framed. Consumers include the matching engine, five Stellar criteria loaders, two
   accepted-bid-summary services, a browse-card chip presenter, a backfill command, and the Bridge
   OData filter builders. A contract with 42 consumers cannot be defined as a Livewire mechanism.
   §6 replaces the Livewire-specific patch protocol with a transport-neutral envelope.

2. **User-drawn geometry already leaves the system, in two directions v1.1 did not address.**
   `PolygonBoundingBox` converts drawn polygons into bounding boxes sent to the **external Bridge
   MLS API** **[MEASURED]**, and `location-dna-map.blade.php` serialises exact polygon vertices into
   page HTML via `@json($polygons)` **[MEASURED]**.

3. **Those viewer pages are unauthenticated.** All six read-only Location DNA surfaces sit
   deliberately outside the `auth` middleware group, with the comment *"These must sit outside the
   auth group so unauthenticated visitors can open a listing card from the public search pages"*
   **[MEASURED]**. Exact user-drawn vertices are therefore reachable without authentication **today**.
   v1.0 called the read-only viewer "structurally zero risk"; that is true of data loss and **false of
   privacy**. This is the highest-severity finding in this document (§13, **F-P1**).

4. **The architecture only described create forms.** Clone, import, archive, restore, expiration,
   legacy loading, normalisation and administrative correction were undefined — and each one has to
   answer the absent-versus-empty question. §8 defines all eighteen lifecycle stages. This was the
   second structural blocker.

5. **The repository already contains the primitives v1.1 said were missing.**
   `CriteriaHashService` produces a deterministic, order-independent SHA-256 over the location
   dimensions **[MEASURED]**, and `LocationDnaVersionService` establishes the house pattern that
   *independent concerns get independent version stamps* **[MEASURED]**. v1.2 adopts these for
   concurrency (§6.4) and projection staleness (§15) instead of inventing mechanisms.

6. **Google-derived coordinates are already persisted in the records a subject-property map would
   render.** `google_place_id` is stored on `accepted_bid_summaries` alongside `property_lat` /
   `property_lng`, and as listing and offer meta **[MEASURED]**. §11 treats this as a licence-ordering
   constraint on the subject-property gate, not a detail.

**Recommended v1 scope is unchanged in substance:** G0 (done) → G1 domain core including mirror
consolidation → behavioural verification → read-only renderer pilot. v1.2 adds one item ahead of all
of them: an owner decision on **F-P1**, which is a live exposure and does not depend on any
architecture work.

---

## 3. Settled decisions — locked

These twenty decisions are settled. v1.2 applies them and does not re-argue them. Any future revision
that reverses one must say so explicitly and carry its own justification.

1. State management before renderer migration.
2. Renderer-independent canonical state.
3. Server ownership of canonical semantics.
4. Capable but non-authoritative client.
5. Presence versus absence as the semantic mechanism.
6. No `dimension_meta`.
7. Two primary workflow capability profiles.
8. Configuration-driven capability declaration.
9. Server-side capability enforcement.
10. Reuse and extend the existing provider registry and contracts.
11. Wrap before replacing the shared partial.
12. Read-only viewer as the first renderer migration.
13. Mirror and trait consolidation in G1.
14. JSON as the canonical transport/UI contract.
15. Optimised spatial storage as a future derivative, not a prerequisite.
16. Nationwide architecture with potentially Florida-first data.
17. No Google fallback.
18. Fair Housing refusal for crime and safety scoring.
19. User-drawn geometry treated as sensitive data.
20. Evidence labels for measured, derived, estimated and unverified claims.

**One clarification, not a reversal.** Decision 6 forbids a parallel per-dimension state machine that
duplicates presence/absence. It does not forbid provenance (§10). The distinction is strict:
provenance records *which provider produced a value*, is attached only to provider-derived entries,
and **never encodes whether a dimension was authored, cleared or absent**. If a provenance design ever
needs to answer the absence question, it has become `dimension_meta` and must be rejected.

---

## 4. Current-state findings

All **[MEASURED]** at `387a971d8` unless labelled otherwise. This section is deliberately short;
v1.1's longer inventory is preserved in that document and summarised in the Adoption Record (§21).

### 4.1 Workflow inventory — unchanged

`@include('partials.location-dna.map-input')` appears in **8 Blade files**. Four workflows have Search
Areas (Buyer/Tenant × Offer/Hire → **Search Envelope** profile); four are subject-property-address only
(Seller/Landlord × Offer/Hire → **Subject Property** profile). Two legacy criteria pages
(`buyer_criteria`, `tenant_criteria`) use plain form POST.

### 4.2 Fragmentation to be eliminated in G1 — unchanged

| Concern | Count |
|---|---|
| Mirror-contract implementations | **5** (`HasSearchAreas` + 4 byte-identical inline copies) |
| Livewire bridge implementations | **3** (shared partial + 2 inline copies, 3 guard flags) |
| Transports | **2** (Livewire bridge ×4, form POST ×4) |

`app/Http/Livewire/Concerns/HasSearchAreas.php` is 132 lines and uses `empty()` at lines 48, 71, 77 and
103 — directly contradicting the presence-versus-absence rule (§5.2) in the same codebase. This is the
concrete defect that makes consolidation a correctness task, not a tidiness task.

### 4.3 New findings — the consumer surface

**F-C1 · The contract has 42 consumers.** Files reading or writing `location_dna_preferences`:

| Consumer class | Examples | Why it matters |
|---|---|---|
| Editing UI | 4 Offer/Hire components, `map-input`, 2 bridges | the only consumer v1.1 designed for |
| Read-only display | `location-dna-map.blade.php`, `LocationDnaChipPresenter`, 6 view pages | §13 privacy surface |
| Matching | `LocationMatchEngine`, `LocationMatchAuctionExtractor`, `LocationPreferenceAnalyzer`, `ComputeCompatibilityScore` | reads canonical state outside any request cycle |
| Criteria loading | 5 `Services/Stellar/*Loader` classes | legacy-record loading path (§8) |
| Enrichment | `BoundaryLookupService`, `FloodZoneLookupService`, `SchoolDistrictLookupService`, `LocationDnaEnrichmentRunner`, `LocationIntelligenceComposer` | provider-facing consumers |
| Outbound export | Bridge `PolygonBoundingBox` → external MLS API | geometry leaves the system |
| Summaries / PDF | `AcceptedBidSummaryService`, `BuyerAcceptedBidSummaryService` | geometry reaches durable documents |
| Backfill / migration | `BackfillLocationSnapshots` | writes a projection (§15) |

**F-C2 · A projection already exists.** `accepted_bid_summaries` carries `property_city`,
`property_county`, `property_state`, `property_zip`, `property_lat`, `property_lng` and
`google_place_id`, populated from listing meta by `BackfillLocationSnapshots`. §15 is therefore not
speculative future work; the consistency question is already live.

**F-C3 · Deterministic hashing is an established house pattern.** `CriteriaHashService::hash()`
canonicalises (recursive `ksort`, null-drop, numeric coercion, order-independent list sorting) and
returns SHA-256. `LocationDnaVersionService` computes two deliberately independent stamps
(`fetchVersion`, `scoringVersion`) with the documented rationale that *"a weight tweak must never
force a refetch, and a provider swap must never masquerade as a scoring change."*

**F-P1 · Public exposure of exact user-drawn geometry.** See §13. Severity: high, live, unauthenticated.

### 4.4 Verification capability

**No browser automation exists** — no `tests/Browser`, and no Dusk, Playwright or Puppeteer dependency
in `composer.json` or `package.json` **[MEASURED]**. Existing Location DNA and Spatial tests are
predominantly *structural*: `SearchAreasPartialTest` asserts on rendered HTML strings via
`assertStringNotContainsString`; `MaplibreProofAssetTest` asserts asset presence. These are valuable
and are **not** behavioural verification (§16.2).

### 4.5 Provider registry — unchanged, inert

`config/location_providers.php` header states: *"STAGE A (this file) is INERT. Nothing in the runtime
path reads it yet."* `google_places` is the sole active production provider; `osm_overpass` points at a
not-yet-implemented adapter while disabled. `config/location_dna_capabilities.php` does **not** exist
**[NOT PRESENT]** — it is proposed by §7 only.

---

## 5. Canonical data contract

### 5.1 Dimensions

The canonical document is a JSON object stored in the `location_dna_preferences` meta key. Its keys are
**dimensions** — the unit of authority, patching, capability and testing throughout this document.

| Dimension | Type | Canonical empty | Profile |
|---|---|---|---|
| `cities` | string[] | `[]` | Search Envelope |
| `zip_codes` | string[] | `[]` | Search Envelope |
| `counties` | string[] | `[]` | Search Envelope |
| `state` | string | `""` | Search Envelope |
| `polygons` | object[] (`label`, `path[{lat,lng}]`) | `[]` | Search Envelope |
| `radius_searches` | object[] (`lat`, `lng`, `radius_miles`, `address` XOR `label`) | `[]` | Search Envelope |
| `flexible_location` | bool | `false` | Search Envelope |
| `location_notes` | string | `""` | both |
| `subject_property` | object | `{}` | Subject Property |

Two contract-level notes carry forward from v1.1 and remain binding:

- `important_places_json` stays a **separate meta key**. Merging it would be a migration with no benefit.
- **Radius is miles**, converted from metres by `1609.34` **[MEASURED]**. A renderer reporting metres
  would multiply every saved radius by ~1,609. This is a named renderer-adapter contract test (§16.6).

`neighborhoods` and `commute` are **withdrawn from the v1 contract** — see §18.

### 5.2 Presence versus absence

> **A canonical key that is ABSENT means "never authored" — legacy fallback may apply.**
> **A canonical key PRESENT but empty means "explicitly cleared" — no fallback may apply.**

**Mechanism: `array_key_exists()`. Never `empty()`.** The serializer must be able to **omit a key**
distinguishably from **emitting the canonical empty value**. Today's serializer always writes all keys
**[MEASURED]**; that is a G1 change.

This rule is the reason `dimension_meta` was removed and stays removed: JSON natively represents
exactly these two states, and a parallel structure would relocate the ambiguity rather than remove it
(L1).

**The rule is incomplete on its own.** "Absent means never authored" is only decidable if the reader
knows whether the writer was capable of omitting keys. Today's writer was not. §5.4 resolves this.

### 5.3 Compatibility — resolving contradiction C1

v1.0 and v1.1 both classified the nine v1 keys as **"byte-compatible"**. That claim is withdrawn. It
was inconsistent with §5.2 in the same document: a serializer that can omit a never-authored key
cannot also guarantee byte-identical output to a serializer that always wrote every key.

**Compatibility in v1.2 is defined at the semantic and schema level. It is not a byte guarantee.**

| Guarantee | Statement | Enforcement |
|---|---|---|
| **G-C1 Key naming** | Every recognised key retains its current name. No renames. | contract test |
| **G-C2 Value shape** | Where a key is present, its value shape and units are unchanged (`radius_miles` in miles, `path` as `[{lat,lng}]`, `state` accepting name or abbreviation). | contract test per dimension |
| **G-C3 Semantic round-trip** | Decode → encode preserves meaning for every dimension: same presence set, same values, same order where order is meaningful. | round-trip test |
| **G-C4 Reader tolerance** | A v1.2 reader accepts every document any historical writer produced, without loss. | migration/compatibility test over legacy fixtures |
| **NOT guaranteed** | Byte-identical serialised output. Key ordering. Presence of never-authored keys. Whitespace. | — |

**Explicitly stated consequences:**

- Canonical serialisation **may omit** dimensions that were never authored. Two documents with
  identical meaning may differ byte-for-byte.
- **Present-but-empty means intentionally cleared.** It is a durable statement of user intent.
- **Omission must never be produced by a failure.** A transport error, an unmounted editor, a timeout
  or a partial payload must never be serialised as omission (L5). Omission is only ever produced by
  "this dimension was never authored". A patch that cannot be constructed is an error to surface, not
  an absence to record.
- **Legacy readers and writers require explicit compatibility handling.** Any consumer of the 42 in
  §4.3 that assumes all keys are present is a compatibility defect to be found and fixed. This audit is
  a G1 deliverable, not an assumption.
- Consumers that hash the document for cache keys or change detection must hash the **canonicalised**
  form (§4.3 F-C3), never raw bytes, or omission will present as a spurious change.

### 5.4 Record interpretation — resolving contradiction C2

The same absence must never mean two different things (L3). Five record situations must be
distinguished, and today's data cannot distinguish them by shape alone. v1.2 resolves this with an
explicit **interpretation mode** derived from `schema_version`, not by inspecting the data.

| Situation | Detection | Interpretation of a missing key |
|---|---|---|
| **S1 · Legacy blob, all-keys writer** | blob present, `schema_version` absent | **Indeterminate — treat as absent (never authored).** Legacy fallback MAY apply. A missing key here cannot be a clear, because the writer could not express one. |
| **S2 · Canonical record** | `schema_version` ≥ 2 | **Absent = never authored** (fallback may apply). Authoritative. |
| **S3 · No blob at all** | meta key absent or unparseable | Every dimension absent. Legacy mirrors are the only source. A corrupt blob is an **error to surface**, never silently an empty record. |
| **S4 · Present-but-empty** | `array_key_exists()` true, value canonically empty | **Intentionally cleared.** No fallback, in any mode. |
| **S5 · Recovered from legacy mirror** | value sourced from discrete `cities` / `counties` / `state` meta during hydration | Value is **inherited**, not authored. It does not become authored until the user next writes that dimension. |

**Interpretation rules:**

1. **Mode is read from `schema_version`, once, at hydration.** No consumer re-derives it.
2. **In S1, clearing is not expressible retroactively.** A legacy record's missing `polygons` means
   "unknown, assume never authored". The system must not later claim the user cleared it.
3. **The upgrade is lazy and write-triggered.** A record is rewritten as `schema_version: 2` only when
   the user next saves it through a v1.2 writer. Reading never upgrades. There is no bulk migration
   (consistent with settled decision: no migration required for v1).
4. **After upgrade, absence is authoritative.** The first v1.2 write records the full presence set it
   observed, so subsequent absence is meaningful.
5. **Mirror recovery is one-directional and non-promoting.** S5 values are used for display and
   matching, and are never written back into the blob as if authored, because that would convert
   "inherited" into "authored" and destroy the distinction.

**Test obligation.** Every one of S1–S5 is a required fixture in the migration/compatibility category
(§16.7). The invariant "no absence means two things" is enforced by the hydrator being the single
entry point and by a test asserting no consumer of the 42 reads the raw blob directly.

### 5.5 `schema_version` — resolving correction 16

`schema_version` is **retained**, with defined behaviour. It is not a decorative field.

**What it controls:** exactly one thing — the interpretation mode of §5.4. It does not gate features,
does not select providers, and does not version the transport envelope (§6 has its own version).
This follows the house rule that independent concerns get independent stamps (§4.3 F-C3).

| Behaviour | Rule |
|---|---|
| **Read** | Absent ⇒ mode S1. `2` ⇒ mode S2. Determined once, at hydration. |
| **Write** | Every v1.2 write stamps `schema_version: 2`. |
| **Lazy upgrade** | On first v1.2 write of a record: stamp the version and record the observed presence set. No other change. Reads never upgrade. |
| **Explicit migration** | None for v1. If ever needed, it is a separately gated batch operation that must be re-runnable and must not alter meaning. |
| **Unknown future version** (`> 2`) | **Refuse to interpret. Fail loudly and read-only.** Do not guess, do not downgrade, do not write. A newer writer may have used semantics this reader lacks, and guessing risks recording a clear that was never intended (L5). Surfaced as an error to the operator, not to the end user as an empty map. |

**Enforcement:** the hydrator is the only reader of `schema_version`. **Test:** contract tests for each
of read/write/lazy-upgrade/unknown-version, plus a test that an unknown version never produces a write.

### 5.6 Revision token

A **revision token** is a deterministic hash of the canonicalised document, computed by the same
pattern as `CriteriaHashService` **[MEASURED]**: recursive key sort, order-independent list
normalisation, then SHA-256.

Properties, all required and all testable:

- **Deterministic** — two documents with identical meaning produce the same token, regardless of byte
  layout or key order. This is why §5.3 withdraws the byte guarantee without losing change detection.
- **Computed, not stored** — no new column for v1. Any reader can derive it.
- **Per-document and per-dimension** — a document token for whole-record concurrency, and a
  per-dimension token so a conflict can be scoped to the dimension that actually diverged (§6.4).
- **Independent of `schema_version`** — a lazy upgrade that changes no values changes no dimension
  token.

The revision token serves three distinct purposes: optimistic concurrency (§6.4), projection staleness
detection (§15) and audit history. It carries no semantics of its own.

---

## 6. Transport-neutral public contract

This section restores the public contract surface that v1.0 §9 provided and v1.1 dropped, and corrects
its central error. v1.0 defined the patch protocol as a Livewire mechanism (`wire:model.defer`,
`DimensionPatch` bound to a Livewire host). With 42 consumers (§4.3 F-C1) and outbound export to an
external MLS API, **welding the domain core to one UI framework was the first structural blocker.**

**The domain core must not depend on Livewire, Blade, HTTP or JavaScript types.** It accepts an
envelope and returns a result. Livewire is one adapter.

### 6.1 The intent envelope

A single transport-neutral structure carries every mutation of canonical state, from every adapter.

| Field | Required | Purpose |
|---|---|---|
| `envelope_version` | yes | Version of the envelope contract itself. Independent of `schema_version` (§5.5). |
| `target` | yes | Record identity: listing family, role, record id. Resolved to a record server-side; never a raw table/row reference from the client. |
| `dimension` | yes | Exactly one dimension (§5.1). One envelope never spans dimensions. |
| `operation` | yes | One of the vocabulary in §6.2. |
| `value` | conditional | Required for `set`; forbidden for `clear`. |
| `workflow_context` | yes | The capability context (§7). Declares which workflow is speaking; does **not** grant anything. |
| `expected_revision` | conditional | Dimension-scoped revision token (§5.6). Required for authenticated interactive edits; see §6.4 for the exceptions. |
| `provenance` | conditional | Required when the value is provider-derived; forbidden for user-authored geometry (§10). |
| `idempotency_key` | recommended | Lets an adapter retry safely without double-applying. |

**Deliberate omissions:** no `user_id` (taken from the authenticated principal server-side, never from
the envelope — this is the IDOR boundary), no capability list (the server resolves capabilities; a
caller cannot assert them, L6), and no transport-specific fields.

**One envelope, one dimension.** Multi-dimension saves submit a **batch** of envelopes with defined
semantics: validated as a whole, applied atomically, and reported per envelope. A batch that fails
validation on any envelope applies none of them. This keeps a partial save from recording a partial
truth.

### 6.2 Operation vocabulary — resolving correction 13

**v1 supports exactly two operations.** This is the smallest vocabulary that expresses every behaviour
the current workflows need, and every one is testable.

| Operation | Meaning | Value |
|---|---|---|
| **`set`** | Replace the entire dimension with `value`. Marks the dimension present and authored. | Required. Must not be the canonical empty value. |
| **`clear`** | Record intentional clearing. Marks the dimension **present-but-empty** (§5.2 S4). | Forbidden. |

**Not supported in v1, with reasons:**

- **`replace`** — rejected as a synonym for `set`. Two names for one operation is exactly the
  duplicate-source-of-truth failure L1 exists to prevent.
- **`append` / `remove` / `reorder`** — rejected. They require element identity that the current array
  dimensions do not have (`polygons` entries have a non-unique `label`; `radius_searches` entries have
  no identity at all). Adding element identity is a contract change with no current consumer.
- **`merge`** — rejected. Merge semantics for arrays of unlabelled geometry are undefined, and an
  undefined merge is how data gets silently lost.

**Array dimensions submit the whole dimension.** Editing one polygon submits `set` with the complete
polygon list. This is deliberate: it makes every write a complete statement of intent for that
dimension, so a dropped element is impossible, and it makes the concurrency model comprehensible
(§6.4). The cost — a larger payload — is trivially small at realistic sizes and was measured to
round-trip at 1,200 vertices **[MEASURED]**.

**Emptiness has exactly one expression.** `set` with a canonically empty value is **rejected** as
invalid, not silently normalised. For scalar dimensions, clearing is `clear` (yielding `""` /
`false`), never `set("")`. One fact, one way to say it.

**What is deliberately *not* an operation:** "absent from payload". Absence is never an instruction
(§5.3). An adapter that cannot construct an envelope raises an error; it does not omit.

### 6.3 Result and error shapes

Every adapter receives the same result shape, so no adapter re-implements interpretation.

**Validation result** carries: `accepted` (bool); the resulting dimension revision token and document
revision token on success; the **effective presence state** of the dimension after application
(`authored` / `cleared`), so a caller never infers it; and `errors` (empty on success).

**Error shape** carries: a stable machine-readable `code`, the `dimension`, an operator-facing
`detail`, and a `retryable` flag. The v1 code set is closed and each code maps to a named test:

| Code | Cause | Adapter's correct response |
|---|---|---|
| `capability_denied` | dimension not enabled for this workflow context (§7) | surface as a bug, not to the user |
| `not_authorised` | principal may not write this record/dimension | deny |
| `revision_conflict` | `expected_revision` mismatch (§6.4) | reload and re-present; **never** auto-overwrite |
| `invalid_value` | shape, unit or range violation | surface field-level error |
| `empty_set_rejected` | `set` with canonical empty value | use `clear` |
| `provenance_required` / `provenance_forbidden` | §10 violation | fix caller |
| `unknown_schema_version` | record newer than reader (§5.5) | read-only; alert operator |
| `unavailable` | dependency failed | retry; **never** convert to a clear (L5) |

`unavailable` is listed last because it is the most important: it is the code that exists so that
failure has somewhere to go other than data loss.

### 6.4 Concurrency and conflict — resolving correction 14

**v1 mechanism: per-dimension optimistic concurrency using the revision token (§5.6), with an append-only
audit record.**

Dimension-scoped rather than document-scoped is the load-bearing choice: two people editing different
dimensions of the same listing is a normal, safe thing that must not conflict, while two people editing
the *same* dimension is a genuine conflict that must not be silently resolved.

| Scenario | Behaviour |
|---|---|
| **Two browser tabs** | Both hold a dimension token from load. First write succeeds and advances the token. Second write of the **same** dimension fails `revision_conflict`; the adapter reloads and re-presents. Writes to **different** dimensions both succeed. |
| **User and agent editing concurrently** | Identical mechanism. Authorisation (§7) decides *whether* each may write; concurrency decides *what happens when both do*. The audit record retains who wrote which dimension when. |
| **Autosave racing manual save** | Autosave carries the same `expected_revision` and the same `idempotency_key` per dimension. A losing autosave fails `revision_conflict` and is **discarded, never retried with a refreshed token** — retrying would let a background process overwrite a deliberate foreground save. Manual save always wins by being the later intentional act. |
| **Stale draft** | A draft resumed after the record moved on fails `revision_conflict` on the diverged dimensions only. Undiverged dimensions still apply. The user is shown what could not be applied. Silent discard is prohibited. |
| **Imported update racing an authored change** | Resolved by precedence, not by timing: **an import never overwrites an authored dimension** (§8.2). Import writes only absent dimensions. This makes the race outcome deterministic regardless of ordering. |

**Where `expected_revision` is required, and where it is not.** Required for all interactive
authenticated edits. Not required for: the first authoring write to an absent dimension (there is no
prior revision to conflict with), import writes (governed by the precedence rule above), and
administrative correction (which is explicitly a last-write-wins act, audited — §8.1).

**Enforcement:** the server compares tokens inside the same transaction that applies the patch.
**Test:** concurrency tests per scenario above, including the negative test that a losing autosave does
not retry.

### 6.5 Adapters

| Adapter | Status | Notes |
|---|---|---|
| **Livewire** | first adapter | Wraps the envelope in the existing Livewire transport. **One implementation, replacing three** (§4.2). Livewire v2.12 **[MEASURED]**, EOL risk tracked as R7. |
| Form POST | existing, must be adapted | The two legacy criteria pages. Adapting them behind the same envelope is what makes their eventual retirement provable (L11). |
| REST API | future | No consumer assumed today. The envelope is the API; no redesign required. |
| Mobile API | future | Same envelope. |
| Queued jobs | future | Enrichment and normalisation write via envelopes, which is how they inherit capability and provenance rules for free. |
| Administrative tools | future | Uses the audited administrative-correction path (§8.1). |
| AI-assisted workflows | future | Only via §12's authorised conversion step. |

**Test:** one shared adapter contract suite, run against every adapter, asserting identical results for
identical envelopes — including the `NullAdapter` used in headless tests.

---

## 7. Capability model — resolving correction 12

Settled decisions 8 and 9 stand: capability is **declared in configuration** and **enforced
server-side**. v1.2 corrects only the *shape* of the context, which v1.1 hard-coded to `family.role`.

### 7.1 Workflow context

Capability resolution takes a **workflow context**: an open map of named dimensions, not a fixed
compound key. Recognised dimensions may include:

`listing_family` · `participant_role` · `transaction_mode` · `property_category` ·
`residential_or_commercial` · `lifecycle_state` (§8) · `source` (authored vs imported)

**The v1 configuration uses only `listing_family` and `participant_role`** — the two dimensions the
eight current workflows actually vary by. The others are *recognised but unused*: the resolver accepts
them and consumers pass whatever context they have.

**The generalisation is in the resolver signature, not in the configuration.** Adding
`property_category` later means adding config rows and passing one more context key — it must not mean
editing every consumer. That is the whole requirement.

### 7.2 Resolution rules

- **Deny by default.** A dimension not affirmatively enabled for the resolved context is denied. An
  unrecognised context, a missing profile or a typo resolves to *deny*, never to permit.
- **Most-specific match wins**, with a deterministic and documented specificity ordering, so resolution
  is reproducible and diffable.
- **No empty override mechanism.** v1.1's `'overrides' => []` is removed (§18): an override table with
  no entries and no defined merge semantics is speculative machinery that would have to be designed
  correctly the first time someone used it.
- **Profiles remain explicit allow-maps.** Listing both `true` and `false` per dimension is verbose but
  makes divergence visible in a diff, which is the reason config was chosen over classes. Deny-by-default
  is the *fallback*, not a licence to leave dimensions unstated.

### 7.3 Enforcement

Configuration declares; the resolver decides; **the server rejects** any envelope whose dimension the
context does not enable (`capability_denied`). A page cannot grant itself a capability, and which Blade
file rendered is not an authorisation input (L6). Authorisation is separate and additional: every
envelope is authorised against `(principal, record, dimension)`. `loadDraft()` already scopes by
`Auth::id()` **[MEASURED]** and that must be preserved and extended per dimension.

**Test:** all eight workflows × every dimension, asserting the server *refuses* out-of-profile writes —
an authorisation test, not a UI test (§16.5).

---

## 8. Workflow lifecycle

This section is new. Its absence was the second structural blocker: the earlier documents described
create and edit forms, while §4.3 shows the contract is consumed across an entire record lifecycle,
each stage of which has to answer the absent-versus-empty question.

### 8.1 Stages and authority

**Authority values:** *authoritative* (the record of truth) · *provisional* (unsaved intent, may be
lost) · *read-only* · *inherited* (from mirrors or a parent, not authored) · *copied* · *normalised* ·
*publicly generalised* · *unavailable*.

| Stage | Present today | Location DNA is | Rules |
|---|---|---|---|
| **Create** | yes | provisional → authoritative on first save | Every dimension starts absent. Nothing is written until the user authors it; a create form must not stamp empty dimensions. |
| **Draft** | yes | authoritative, incomplete | Draft is persisted canonical state, not a scratchpad. Absence still means never authored. Drafts are scoped by principal **[MEASURED]**. |
| **Autosave** | partial | authoritative once acknowledged | Only an acknowledged write is durable. Unacknowledged intent is provisional (§9). Losing autosave is discarded, not retried (§6.4). |
| **Resume** | yes | authoritative | Rehydrate from canonical + mirrors. Presence set is restored exactly; an editor that cannot mount changes nothing (L4). |
| **Edit** | yes | authoritative | Per-dimension `set`/`clear` under optimistic concurrency. |
| **Intentional clear** | yes (guarded) | authoritative | `clear` → present-but-empty. Durable. No fallback may resurrect it. This is the stage G0 currently cannot serve when the editor cannot hydrate (§17, C3). |
| **Review** | yes | read-only | Review must render exactly what would publish, including *which* dimensions are cleared versus never authored. |
| **Publish** | yes | authoritative | Publication changes visibility, never content. Publishing must not normalise, backfill or geocode as a side effect. |
| **Public display** | yes | **publicly generalised** | §13. Exact user-drawn geometry never leaves the server for a public surface. |
| **Private display** | yes | read-only, full precision to authorised principals | §13 tiers. |
| **Clone / duplicate** | **[NOT PRESENT]** | copied | Rules defined here so a future clone cannot get it wrong: copy the **presence set exactly** — a cleared dimension stays cleared, an absent dimension stays absent. Never "copy values and drop empties", which silently converts *cleared* into *never authored*. Provenance is copied; the revision token is recomputed; `schema_version` is stamped at current. |
| **Archive** | **[NOT PRESENT]** for listings (`archived_at` exists only on DNA outputs/scores **[MEASURED]**) | read-only, frozen | Archiving freezes content and revision. Archived records are excluded from matching but remain readable. No normalisation on archive. |
| **Restore** | **[NOT PRESENT]** | authoritative again | Restore must reproduce the archived presence set, then re-enter the current interpretation mode (§5.4). It must not silently upgrade meaning. |
| **Expiration** | partial (`expiration_date` on tenant flows; `Offer.expires_at` **[MEASURED]**) | read-only | Expiry is a visibility and matching state. It must never clear or delete dimensions. An expired record's geometry is retained under the deletion/retention policy (§14). |
| **MLS import** | inbound Location DNA is **[NOT PRESENT]**; Bridge is today an **outbound** read path **[MEASURED]** | inherited / copied | Rules defined in §8.2 so an import cannot overwrite authored intent. |
| **Legacy-record loading** | yes (5 Stellar loaders + hydrator) | inherited | Mode S1/S5 (§5.4). Mirror-derived values are inherited, never promoted to authored. |
| **Migration / normalisation** | yes (`BackfillLocationSnapshots` **[MEASURED]**) | normalised, derived | Normalisation writes **projections**, not canonical state (§15). If it must write canonical state, it goes through envelopes and is audited, and it must be re-runnable without changing meaning. |
| **Administrative correction** | **[NOT PRESENT]** | authoritative, audited | The one explicit last-write-wins path. Requires an audit record with actor, before/after presence set and reason. Must not bypass capability or provenance rules; may bypass `expected_revision` — that is precisely what makes it administrative, and why it is audited. |

### 8.2 Absence semantics across the lifecycle

Four rules, each derived from L3 and L5, each with a test obligation:

1. **Cloning preserves the presence set exactly.** Cleared stays cleared; absent stays absent. The
   failure mode this prevents — dropping empties during a copy — silently rewrites user intent.
2. **Importing writes only absent dimensions.** An import may populate a dimension the user never
   authored; it may **never** overwrite an authored value and may **never** overwrite a *cleared*
   dimension. A clear is a statement that the user does not want that dimension, and an import must
   respect it. This makes the import/authoring race deterministic (§6.4).
3. **Editing distinguishes clear from unmounted.** Only an explicit `clear` clears. An editor that did
   not load emits nothing and changes nothing (L4/L5).
4. **Legacy loading never fabricates intent.** In mode S1, missing means *unknown, assume never
   authored*. The system must never report that a legacy user cleared something.

---

## 9. Client and server boundary — resolving contradiction C6

Settled decisions 3 and 4 stand: the client is **capable but not authoritative**. v1.2 corrects only
v1.1's phrase *"the client may hold a provisional view of canonical state"*, which was imprecise enough
to license exactly the confusion the architecture exists to prevent.

**The client never holds canonical state, provisional or otherwise.** It holds three things, and each
has a precise name:

| Client-side thing | Definition | Lost on refresh? |
|---|---|---|
| **Transient editor state** | Half-drawn polygon, active drawing mode, hover target, selection, map centre and zoom, open tab. | Yes, harmlessly. |
| **Provisional rendering model** | A local, renderer-facing model derived *from* server-acknowledged state plus optimistic effects, used to paint the map without a round-trip. It is a **derivative for display**, never an input to persistence. | Yes, harmlessly — it is rebuilt from canonical state. |
| **Unsaved user intent** | Envelopes constructed but not yet acknowledged by the server. | **Yes — and this is the one that matters.** |

**The persistence boundary, stated explicitly:** *canonical state is state the server has accepted and
persisted, or accepted and committed to persist within the same transaction.* Everything else is
unsaved intent.

**Consequences that must be true:**

- **Unsaved intent may be lost on refresh, navigation or crash** unless autosave or draft persistence
  has *acknowledged* it. Optimistic UI must never imply durability it does not have.
- **Acknowledgement is the only durability signal.** Not "the patch was sent", not "the UI updated".
- **A renderer failing to mount destroys transient state and the rendering model only.** It cannot
  affect canonical state. That is the invariant G0 approximates with a guard and the domain core makes
  structural.
- **The client never resolves absence, emptiness, merge precedence or capability.** Those are decided
  once, server-side (L2, L6).

---

## 10. Provenance — resolving correction 15

**Provenance answers exactly one question: which provider produced this value?** It is not a state
machine, and it never encodes authored/cleared/absent (§3 clarification).

### 10.1 Where provenance is recorded

**Granularity: per entry where a provider produced the entry; per document for the interpretation
stamp; never per dimension as a parallel presence structure.**

| Subject | Provenance | Rationale |
|---|---|---|
| `subject_property` | **per field group** — one `provider` plus `resolution` (`unresolved` / `zip_centroid` / `rooftop`) for the geocoded result | The coordinates and the address components come from one resolution act. |
| Geocoded address | **per entry** on the resolved object | Needed for the Google-separation test (§10.2). |
| Radius-search **centre** | **per entry**, only when a provider resolved the centre from an address | The radius itself is user intent and carries none. |
| **Polygons** | **none — forbidden** | Entirely user-authored. Attaching provider metadata to user-drawn geometry is the "unnecessary provider metadata" the correction warns against, and it would create a place for semantics to leak. |
| `cities` / `zip_codes` / `counties` / `state` | **none in the canonical blob** | These are user selections of published names. Where an autocomplete provider suggested them, provenance belongs on the *provider cache record*, not on the user's choice. |
| POIs | **per entry, in the POI store** — not in the canonical blob | Already the design of `CanonicalPoiAssembler` / `CanonicalField` **[MEASURED]**. |
| Imported MLS facts | **per entry**, plus `source: imported` in the record's audit trail | Required by §8.2 rule 2. |
| Normalised boundaries | **per boundary record** in the boundary store | Already carried by the registry's `license` key **[MEASURED]**. |
| Provider-derived coordinates anywhere | **per entry, mandatory** | This is the enforcement hook for L9. |

### 10.2 The Google-separation requirement

Provenance exists primarily so that this rule is *testable* rather than aspirational:

> **No Google-derived value may be rendered on, or served to, a non-Google basemap surface.**

**Measured hazard.** Google-derived coordinates are already persisted in exactly the records a
subject-property map would render: `accepted_bid_summaries.google_place_id` beside `property_lat` /
`property_lng`, plus `google_place_id` as listing meta and `prop_google_place_id` as offer meta,
populated by `GeocodeSelleryLandlordListings` and `BackfillLocationSnapshots` **[MEASURED]**.

**Consequences:**

- Every persisted coordinate needs a queryable provider, and unlabelled historical coordinates must be
  treated as **Google-provenance until proven otherwise** — the safe default.
- **This is a hard prerequisite of the subject-property renderer gate**, not a cleanup task. A
  MapLibre subject-property map rendering `property_lat`/`property_lng` of Google origin would be a
  licence violation on day one.
- **Enforcement:** a provenance check at the serialisation boundary of any non-Google renderer surface.
  **Test:** a contract test asserting no Google-provenance row is served to a MapLibre surface, and a
  test asserting unlabelled coordinates are treated as Google.

---

## 11. Providers and Google removal

Settled decision 10 stands: **extend the existing registry; do not rebuild it.** The registry is inert
**[MEASURED]** and `google_places` is the sole active provider.

**Stated plainly, because v1.0 got this wrong and v1.1 only partly corrected it:** switching POI
providers is **not a configuration flip**. It requires implementing an Overture-backed adapter, wiring
the registry into the runtime path, verifying the licence, and only then flipping a provider that is
annotated *"active production provider — do not disturb"*. That is a gate (G5), not a line.

**MapLibre supplies rendering only** — no search, no geocoding, no boundaries. Each is a separate
provider. Believing otherwise is the most common failure mode of a Google→MapLibre migration.

### 11.1 Per-capability Google-removal inventory

**Blocks pilot?** = blocks the read-only renderer pilot (G3). **Verification** names the test category
(§16) that must pass before that capability is considered migrated.

| Capability | Current provider | Target abstraction | Migration dependency | Licensing concern | Blocks pilot? | Verification |
|---|---|---|---|---|:--:|---|
| **Basemap** | Google Maps JS | `BasemapTileProviderInterface` **[NOT PRESENT]** → Protomaps PMTiles on R2 | archive + custom domain (R8) | OSM/Protomaps attribution — implemented in `config/spatial_basemap.php` **[MEASURED]** | **yes** | browser + visual |
| **Markers** | `google.maps.Marker` | renderer adapter | basemap | none once basemap moves | **yes** | renderer contract + browser |
| **Polygons** | `google.maps.Polygon/Polyline` | renderer adapter, GeoJSON source | basemap | none — user-authored content | **yes** | renderer contract + browser + geometry round-trip |
| **Radius circles** | `google.maps.Circle` | renderer adapter + own Haversine or `@turf/circle` | basemap | none | **yes** | renderer contract; **miles-vs-metres unit test (§5.1)** |
| **Address search** | Google Places autocomplete | `AddressAutocompleteProviderInterface` **[NOT PRESENT]** | **D1** | Google ToS; OSM-derived alternatives **[UNVERIFIED]** obligations | no | provider contract + integration |
| **Geocoding (forward)** | `LocationDnaGeocodeService` (Google) | `GeocodingProviderInterface` **[NOT PRESENT]** | **D1**; `addresses` table has 0 rows **[MEASURED]** | as above | no | provider contract |
| **Reverse geocoding** | Google | same interface | **D1** | as above | no | provider contract |
| **Autocomplete (server proxies)** | Google, multiple server-side proxy endpoints | same interface | **D1** | as above | no | provider contract + integration |
| **POI lookup** | `GooglePlacesPoiAdapter`, `enabled => true` "do not disturb" **[MEASURED]** | `PoiLookupAdapterInterface` (exists) → Overture; `osm_overpass` adapter **NOT YET IMPLEMENTED** **[MEASURED]** | **G5** — adapter + registry wiring + licence | **Overture licence [UNVERIFIED]** (ODbL share-alike vs CDLA-Permissive is materially different) | no | provider contract + provenance |
| **Travel-time / commute** | stub only; `openrouteservice` declared, disabled **[MEASURED]** | `CommuteTimeAdapterInterface` (exists) | provider evaluation not done (R10) | per-provider ToS | no | provider contract |
| **Data layers** (flood, school district, boundaries) | FEMA + Census adapters, already non-Google **[MEASURED]** | existing interfaces | city/ZIP boundaries not imported (R3) | public domain **[MEASURED]** | partial — layers renderable, city/ZIP absent | provider contract + integration |
| **Telemetry** | `google-maps-auth-telemetry`, `gm_authFailure` | renderer-agnostic health events | basemap | none | **yes** | integration; **must never carry geometry** (§14) |
| **Public viewers** | Google map on 6 **unauthenticated** routes **[MEASURED]** | renderer adapter + §13 generalisation | **F-P1 owner decision** | Google content over non-Google basemap | **yes — blocking** | privacy-generalisation test + browser |
| **Editable workflows** | Google throughout `map-input` | renderer adapter + envelope transport | **D1** — renderer and autocomplete swaps are inseparable for editable Search Areas | licence-ordering constraint | no | full stack, all categories |
| **Failure handling** | `gm_authFailure`; today a dead credential produced the data-loss hazard | `NullRenderer` + visible degradation | domain core | **no Google fallback** (L9) | **yes** | failure-behaviour tests (§16.9) |

**The licence-ordering constraint, restated precisely:** any workflow that switches to MapLibre must
*simultaneously* stop rendering Google-derived data on that map. Because Places autocomplete currently
supplies city lat/lng bias and radius-search centre coordinates, **the renderer swap and the
autocomplete swap cannot be separated for editable Search Areas.** The read-only viewer escapes this
only because it draws user geometry and our own boundaries — which is why it is the correct pilot, and
why §10.2's subject-property finding matters so much: the *subject-property* read-only map does **not**
escape it.

---

## 12. AI boundary

This section restores v1.0 §9.5, which v1.1 dropped entirely.

### 12.1 The three-layer boundary

| Layer | Owns | Never does |
|---|---|---|
| **Deterministic spatial computation** | Boundary containment, point-in-polygon, Haversine distance, areas and overlaps, isochrones, POI counts, flood zone, school district. Reproducible: same inputs → same outputs, always. | Interpret, rank subjectively, or write canonical state. |
| **Canonical domain state** | The contract (§5). Presence/absence, validation, merge, persistence, capability, provenance. | Compute spatial facts. Call AI. |
| **AI interpretation** | Explanation, summarisation, ranking and recommendation over normalised, authorised inputs. | Everything in §12.2. |

**A deterministic calculation must never be delegated to AI.** If a question has a correct answer
computable from geometry — *is this point inside this polygon?*, *how far is this?* — it belongs in a
spatial service. Asking a model instead makes a reproducible fact probabilistic. This is the boundary's
main purpose.

### 12.2 Rules

1. **AI consumes normalised, validated Location DNA output** — never the raw blob, never a partially
   hydrated record, never provider responses directly.
2. **AI may explain, summarise, rank or recommend** based on authorised inputs.
3. **AI does not author canonical geometry.** No polygon, radius, centre or coordinate originates from a
   model.
4. **AI does not decide absent-versus-empty semantics.** That is server-side and settled (L2, L3).
5. **AI does not perform deterministic spatial calculations** that belong in spatial services (§12.1).
6. **AI output is advisory** unless a separately authorised workflow converts *user-confirmed* intent
   into a canonical patch. That conversion must: present the proposal, require explicit user
   confirmation, emit a normal envelope (§6) through normal capability, authorisation, validation and
   concurrency checks, and record that the value originated from an AI-assisted flow in the audit
   trail. **There is no path from a model to persistence that bypasses the envelope.**
7. **AI respects capability, privacy, provenance, Fair Housing and authorisation rules.** Concretely:
   it receives only dimensions the context enables and the principal may read; user-drawn geometry is
   sensitive (L14) and is not sent to third-party models without explicit consent; Google-derived
   content is not laundered through a model to escape L9; and §14's Fair Housing refusal applies to AI
   outputs exactly as to any other feature — a model may not be asked to infer neighbourhood
   desirability, safety or demographic suitability.
8. **AI is never embedded inside the renderer, serializer, persistence layer or provider adapter.** It
   sits outside them, downstream of the contract. `LocationDnaChipPresenter`'s governance block —
   which already forbids importing OpenAI or scoring classes into a presentation layer **[MEASURED]** —
   is the house precedent and the right pattern.

**Enforcement:** AI consumers depend only on the normalised output type; no AI class is reachable from
domain, renderer, serializer or adapter namespaces. **Test:** a structural dependency test asserting
that, plus a test that the AI-assisted write path goes through the same validation as any adapter.

---

## 13. Privacy and public exposure — resolving contradiction C5

### 13.1 Finding F-P1 — present-state exposure

**All six read-only Location DNA surfaces are unauthenticated** **[MEASURED]**:

```
/offer-listing/tenant/view/{id}      /offer-listing/buyer/view/{id}
/offer-listing/seller/view/{id}      /offer-listing/landlord/view/{id}
/criteria/view/{id}                  /tenant/criteria/auction/view/{id}
```

They sit deliberately outside the `auth` group, with the comment *"These must sit outside the auth
group so unauthenticated visitors can open a listing card from the public search pages."*
`resources/views/components/location-dna-map.blade.php:335` serialises exact polygon vertices into the
page with `var polygons = @json($polygons);` **[MEASURED]**.

**Therefore exact user-drawn vertices are reachable today without authentication.** This contradicts
L14 and contradicts v1.1 §12's stated hard requirement that public viewers render a generalised
envelope. It is a **live exposure, not a design gap**.

It is also independent of all renderer work: it exists on the Google implementation and would be
faithfully reproduced by a MapLibre port. **It requires an owner decision ahead of, and separately
from, the architecture gates** (§17, G0.1). This document does not fix it and no code has been changed.

### 13.2 Viewer tiers

Four tiers, with distinct behaviour. The tier is resolved server-side from the principal and the
record; it is never inferred from which view rendered.

| Tier | Who | Geometry | Address |
|---|---|---|---|
| **T1 Owner** | the authoring user | full precision | full |
| **T2 Authorised participant** | hired agent, bid participant, party to the transaction | full precision | full, per existing agent-only-after-hire rule **[MEASURED]** — must not regress |
| **T3 Authenticated private viewer** | logged in, not a participant | **generalised** (§13.3) | city/ZIP granularity only |
| **T4 Public viewer** | unauthenticated | **presence-only** (§13.3) | city/ZIP granularity only |

### 13.3 The generalisation algorithm

**v1 default, deterministic and testable:**

- **T4 public — presence-only disclosure.** No coordinates of any kind are serialised to the client.
  The page states *that* a custom search area or radius search exists, without geometry. This is
  exactly what `LocationDnaChipPresenter` already does — it emits chips such as "Custom Search Area"
  and "Radius Search" from geometry without exposing vertices **[MEASURED]** — so the safest tier has a
  working precedent in the repository and needs no new algorithm.
  Administrative *names* the user selected from published lists (`cities`, `counties`, `state`,
  `zip_codes`) remain publishable at T4: they are not user-drawn geometry.
- **T3 authenticated private — snapped bounding envelope.** Where a map must be drawn: the union of the
  dimension's geometry reduced to a single axis-aligned bounding box, expanded outward to a fixed
  coordinate grid, with a minimum span floor, emitted as **one rectangle with no vertex-level
  structure**. Radius searches emit a grid-snapped centre and a radius rounded outward to whole miles.
  Vertex counts, shape and ordering are not disclosed.

**Parameter selection is a blocking owner decision.** The grid size and minimum span *are* the privacy
strength, and choosing them wrong makes the algorithm decorative. **No T3 geometry rendering may ship
before those parameters are set** (owner decision D3, §20). T4 presence-only requires no parameter and
is therefore the safe default at every gate.

**Invariants, each with a test obligation:**

| Invariant | Enforcement | Test |
|---|---|---|
| Exact user-drawn vertices are never serialised below T2 | server-side generalisation at the serialisation boundary, before any view | privacy-generalisation test asserting no T3/T4 payload contains any input vertex |
| Generalisation is not reversible into original vertices | grid snapping + single envelope + no vertex counts | test asserting output is identical for two different polygons with the same snapped envelope |
| Tier is server-resolved | tier derived from principal + record, never from the view | test asserting a public route cannot produce a T1/T2 payload |
| Generalisation is deterministic | pure function of geometry + parameters | property test: same input → same output |
| **Bounding boxes already sent to Bridge are not a public disclosure** | `PolygonBoundingBox` output goes to the MLS API, never to a page **[MEASURED]** | test asserting bbox output has no view-layer consumer |

The last row matters: the codebase already generalises geometry into bounding boxes for the Bridge
query path. That is an *outbound provider* disclosure governed by §14, not a public-display path, and
the two must not be conflated — but it does establish the envelope approach as familiar in this
codebase.

---

## 14. Security, licensing, compliance

**Licensing.**

- OSM/Protomaps attribution — implemented in `config/spatial_basemap.php` **[MEASURED]**.
- Census/TIGER and FEMA — public domain **[MEASURED]** via the registry's `license` key.
- **Overture licence: [UNVERIFIED].** ODbL share-alike versus CDLA-Permissive is materially different —
  share-alike would attach obligations to derived datasets. Must be confirmed before Overture POI data
  is relied on (R6).
- **OSM-derived geocoding: [UNVERIFIED] obligations.** Self-hosted Nominatim or OSM-derived coordinates
  may carry ODbL attribution and possibly share-alike. Must be resolved as part of **D1**.
- The registry already carries `cache_policy: attribution-required` **[MEASURED]** — the right
  enforcement hook.

**Google-derived data separation — hard requirement.** §10.2, including the measured
`google_place_id` + `property_lat`/`property_lng` hazard.

**Outbound disclosure.** User-drawn geometry is already transmitted to an external MLS API as bounding
boxes **[MEASURED]**. This is a legitimate functional need, and it is also a disclosure of sensitive
data to a third party. It must be documented in the data-flow record, minimised to what the query
requires, and must never be widened to full vertices without a decision.

**Privacy.** `location_dna_preferences` is sensitive (L14). Hard requirements, not recommendations:
never rendered at full precision below T2 (§13); never in client-side logs; never in third-party AI
prompts without explicit consent (§12 rule 7); precise street addresses remain agent-only-after-hire
**[MEASURED]** and must not regress.

**Authorisation.** Server-side capability enforcement (§7) plus per-`(principal, record, dimension)`
authorisation is the IDOR boundary. `loadDraft()` already scopes by `Auth::id()` **[MEASURED]**.

**Fair Housing — crime and safety scoring is a documented refusal (L15).** In a housing context it
correlates with protected characteristics and carries steering exposure. This is a refusal, not a
deferral, and it extends to AI (§12 rule 7) and to any provider that would supply it. If ever
revisited, counsel reviews first.

**Telemetry and retention.** Renderer health only — never geometry payloads. A retention policy for
drawn geometry on listing deletion, archival and expiry is required and does not exist today
**[NOT PRESENT]**.

---

## 15. Storage evolution and projection consistency

Settled decisions 14 and 15 stand: the canonical JSON is the transport/UI contract; optimised spatial
storage is a future derivative and **PostGIS is not a prerequisite for anything in v1**.

v1.2 adds the consistency model, and corrects one framing error: **a projection already exists.**
`accepted_bid_summaries` carries denormalised location columns populated from listing meta by
`BackfillLocationSnapshots` **[MEASURED]**. This is not hypothetical future work.

### 15.1 Consistency model

**v1 choice: projections are asynchronous and eventually consistent, with mandatory staleness
detection.** Synchronous or transactional projection is rejected for v1: it would couple every
canonical write to projection availability, and a projection outage would become a save outage — which
would violate L5 by turning unavailability into a failed user action.

Four invariants, non-negotiable regardless of which projections exist:

| # | Invariant | Enforcement | Test |
|---|---|---|---|
| **P1** | **Canonical state remains authoritative.** If canonical and projection disagree, canonical wins and the projection is rebuilt. A projection never arbitrates (L1). | projections are write-only derivatives; no canonical read path consults them | test asserting no canonical read resolves through a projection |
| **P2** | **Stale projections are detectable.** Every projection row records the **revision token** (§5.6) of the canonical document it was derived from. Stale = token mismatch. | token stamped at projection write | staleness test: mutate canonical, assert projection detected stale |
| **P3** | **Projections are rebuildable.** Every projection can be fully reconstructed from canonical state alone, deterministically, with no information that exists only in the projection. | rebuild command per projection | rebuild-idempotence test: rebuild twice → identical output |
| **P4** | **Matching never silently uses an unknown revision.** A matching run either verifies the projection token matches canonical, or records that it used a stale revision. Silent use of unverified data is prohibited. | token check at the matching read boundary | test asserting a stale projection is either refused or explicitly recorded |

**P4 is the one that protects users.** A match computed from a stale projection is a wrong answer
delivered confidently — the same class of failure as the data loss G0 fixed.

**Deferred:** the choice of spatial substrate (PostGIS geometry columns, spatial index strategy,
partitioning) is deferred to the storage-projection gate (§17, G9). The invariants above are **not**
deferred; they bind whatever is built, including the projection that exists today.

---

## 16. Testing strategy

This section restores and strengthens v1.0 §11, which v1.1 dropped. It is the enforcement half of L12.

> **Every claimed invariant must have an identified enforcement mechanism and test category.**
> An invariant with no named enforcement point and no named test category is an aspiration. Where this
> document states an invariant, it names both.

### 16.1 Categories — and what each can and cannot prove

Six categories. The distinction is not bookkeeping; conflating them is how false confidence is
manufactured.

| Category | Proves | Cannot prove | Present today |
|---|---|---|---|
| **Structural** | Source and rendered output contain, or do not contain, specific constructs. Cheap, fast, good regression fences. | **That anything works.** A partial can contain every correct string and still be broken at runtime. | yes — extensively **[MEASURED]** |
| **Behavioural (server)** | Domain logic produces correct results for given inputs, including negative cases. | Anything about the browser, JS or rendering. | yes |
| **Integration** | Components work together across the persistence and service boundary. | Client behaviour. | yes |
| **Browser** | What actually happens in a real browser: JS execution, renderer mount, user interaction, save, reload. | Correctness of server semantics in isolation. | **no — [MEASURED] no `tests/Browser`, no Dusk/Playwright/Puppeteer** |
| **Contract** | Every implementation of an interface behaves identically, including the Null implementation. | That the interface is the right shape. | partially (provider tests exist) |
| **Migration / compatibility** | Legacy and current records are read correctly, and upgrades preserve meaning. | Forward compatibility with unknown future versions. | partially |

**Stated explicitly, because both prior documents blurred it:** **structural source inspection is not
equivalent to browser verification.** `SearchAreasPartialTest` asserting `assertStringNotContainsString`
on rendered HTML, and `MaplibreProofAssetTest` asserting an asset exists **[MEASURED]**, are structural
tests. They are genuinely valuable and they prove nothing about runtime behaviour. The committed
assertion that `map-input.blade.php` contains zero MapLibre references is a structural fact about
source, not evidence that any map works.

**Consequence: G0 is verified structurally and is *not* verified behaviourally.** The guard's actual
runtime effect — geometry surviving a city edit with the editor unhydrated — cannot currently be
demonstrated in this repository. That is a stated limitation, not a hidden one, and it is why
behavioural verification is its own gate (§17, G2) rather than a line item.

### 16.2 Required coverage — domain state

| Area | Coverage | Category |
|---|---|---|
| **Domain-state unit tests** | Construct, read, patch, serialise, deserialise every dimension. Immutability. Order stability for order-significant dimensions. | behavioural |
| **Absent vs present-empty** | Every dimension × {absent, present-empty, populated}. The §5.2 rule. Explicit assertion that `array_key_exists()` and not `empty()` decides. | behavioural |
| **Intentional clear** | `clear` persists; survives reload; legacy fallback never resurrects it; `set` with empty value is **rejected** (`empty_set_rejected`). | behavioural + integration |
| **Merge and patch behaviour** | Patch application per dimension; **cross-dimension preservation** — changing `cities` alters nothing else, with the editor mounted **and unmounted**; batch atomicity (a failing envelope applies none). | behavioural + integration |
| **Serialisation** | §5.3 guarantees G-C1–G-C4: names, shapes, semantic round-trip, reader tolerance. Explicit negative test that byte-identity is **not** asserted anywhere. | contract |
| **Schema version** | Read / write / lazy upgrade / unknown-version refusal (§5.5), including that an unknown version never writes. | migration + behavioural |
| **Revision token** | Determinism, order-independence, per-dimension scoping, insensitivity to lazy upgrade. | behavioural |

### 16.3 Required coverage — compatibility and legacy

| Area | Coverage | Category |
|---|---|---|
| **Legacy compatibility** | Fixtures for all five situations S1–S5 (§5.4). Pre-blob records; mirror-only records; corrupt blob (must surface an error, never an empty record); `info()` returning `false` (finding 2B-1). | migration |
| **Mirror consolidation** | The five mirror implementations produce identical observable behaviour before and after consolidation, per workflow. `HasSearchAreas`'s four `empty()` sites (lines 48, 71, 77, 103 **[MEASURED]**) are converted to presence semantics **with a test per site** proving cleared values are no longer resurrected. | migration + behavioural |
| **Characterisation of legacy workflows** | Extend Phase 2B's committed characterisation (`SearchAreasPersistenceCharacterisationTest`, `TenantOfferCitiesMirrorTest`, `SearchAreasGeometryContractTest` **[MEASURED]**) to every workflow **before** it is adapted. Characterisation is the parity evidence L11 requires; a workflow with no characterisation may not be migrated. | migration |
| **Consumer audit** | Each of the 42 consumers (§4.3) is asserted to tolerate omitted keys. This is the concrete work behind §5.3's "legacy readers require explicit handling". | integration |

### 16.4 Required coverage — lifecycle

| Area | Coverage | Category |
|---|---|---|
| **Lifecycle behaviour** | Each stage in §8.1 that exists today: create → draft → resume → edit → clear → review → publish → display. Presence set preserved end-to-end. | integration |
| **Cloning** | Presence set copied **exactly**; the negative test that matters: a cleared dimension does not become absent. Applies when cloning is built **[NOT PRESENT]**. | behavioural |
| **Import precedence** | Import writes absent dimensions only; never overwrites authored; never overwrites cleared (§8.2 rule 2). | behavioural |
| **Archive / restore / expiry** | Content and presence unchanged by visibility transitions; no clearing on expiry. | integration |
| **Administrative correction** | Bypasses `expected_revision` but not capability, authorisation or provenance; always audited with before/after presence set. | integration |

### 16.5 Required coverage — capability, authorisation, provenance

| Area | Coverage | Category |
|---|---|---|
| **Capability enforcement** | All 8 workflows × every dimension. The **server refuses** out-of-profile writes (`capability_denied`) — an authorisation test, not a UI test. Deny-by-default: unknown context, missing profile and typo all deny. Resolver accepts unused context dimensions without behaviour change (§7.1). | behavioural + integration |
| **Authorisation** | Per-`(principal, record, dimension)`. Draft scoping by `Auth::id()` preserved. Cross-principal write attempts refused. | integration |
| **Provider provenance** | Provenance required where §10.1 requires it and **forbidden where forbidden** — the negative test that polygons carry no provider metadata is as important as the positive ones. Unlabelled coordinates treated as Google-provenance. | contract + behavioural |
| **Google-content separation** | No Google-provenance value served to a MapLibre surface (§10.2). Includes the measured `accepted_bid_summaries` path. | contract + integration |
| **Privacy generalisation** | The five invariants in §13.3, including that no T3/T4 payload contains any input vertex, and that a public route cannot produce a T1/T2 payload. | behavioural + integration |

### 16.6 Required coverage — adapters and providers

| Area | Coverage | Category |
|---|---|---|
| **Renderer adapter contract** | One shared suite run against every renderer including `NullRenderer`: mount, ready, reflect geometry, report edits, add layer, destroy. **`NullRenderer` proves editors and serialisation work with no map at all** — the structural expression of L4. **Unit contract: radius is miles** (§5.1) — a renderer reporting metres is caught here, not in production. | contract |
| **Transport adapter contract** | One shared suite run against every adapter (Livewire, form POST, Null): identical envelopes produce identical results, identical error codes, identical concurrency behaviour (§6.5). | contract |
| **Provider contract** | One suite per interface run against every adapter including stubs. **No-Google-fallback assertion: a failing provider degrades visibly and never substitutes Google** (L9). | contract |
| **Persistence round trips** | Write → read → write for every dimension and every lifecycle stage; presence set and values stable; token stable across a no-op save. **Save-with-no-changes must change nothing.** | integration |
| **Concurrency** | Every scenario in §6.4, including the negative test that a losing autosave is discarded rather than retried. | integration |
| **Projection consistency** | Invariants P1–P4 (§15.1) against the projection that exists today. | integration |

### 16.7 Required coverage — geography and non-Florida

| Area | Coverage | Category |
|---|---|---|
| **Non-Florida fixtures** | At least one non-Florida fixture in every geography-touching suite — boundary lookup, geocode, tile addressing, capability resolution, matching. Prevents Florida assumptions from becoming structural (L10). | behavioural + integration |
| **US-specific assumptions** | Each assumption in §19 has a test asserting it is confined to configuration or a named adapter, not the domain model. | structural + behavioural |

### 16.8 Required coverage — behavioural browser verification

**A prerequisite, not an optional extra.** No JS behaviour in this system is currently verifiable
**[MEASURED]**, and every renderer gate depends on JS behaviour.

Required scenarios, per workflow, each ending in **reload and re-verify**:

1. A legacy record opens populated.
2. **Save with no changes changes nothing** — the regression that guards the whole contract.
3. Add a dimension; reload.
4. Remove one element; reload.
5. Clear a dimension explicitly; reload; confirm it stays cleared and is not resurrected.
6. Edit a non-geometry dimension **with the renderer unavailable**; confirm geometry survives — the
   behavioural verification of G0 that does not exist today.
7. Draft, resume, publish.
8. Two-tab conflict: same dimension → conflict surfaced; different dimensions → both apply.
9. Public route: confirm no exact vertices in the delivered payload (§13.3).

Plus visual verification (basemap paints, layers paint, degraded state is visible), accessibility
(keyboard polygon editing, screen-reader labels, focus order, contrast) and mobile (touch drawing,
viewport, tile budget).

### 16.9 Required coverage — failure and unavailable editor

The category that exists because L5 exists. Failure must have a defined, tested behaviour everywhere.

| Failure | Required behaviour | Test |
|---|---|---|
| Renderer never mounts | Editors and serialisation still work; geometry untouched; **no write clears an unloaded dimension** | browser + behavioural |
| Renderer mounts then dies | Transient state and rendering model lost; canonical state unaffected | browser |
| Basemap tiles unavailable | Visible degradation; no Google fallback; state unaffected | browser + contract |
| Provider timeout / error | `unavailable` surfaced; **never converted to a clear**; retryable | behavioural + contract |
| Transport failure mid-save | No partial application; batch atomicity holds; user informed | integration |
| Corrupt blob | Error surfaced; **never interpreted as an empty record** | migration |
| Unknown `schema_version` | Read-only, loud failure, no write | migration |
| Projection unavailable | Canonical write still succeeds (§15.1); staleness recorded | integration |

---

## 17. Gate roadmap

Every v1-scoped activity has a named gate with scope, prerequisites, owner decisions, tests, rollback
and a stop condition. **Numbering differs from v1.1** because behavioural verification and
provider-registry activation are now separate named gates rather than line items inside other gates.

Gate order is a dependency statement, not a schedule. **No gate is authorised by this document.**

### G0 ✅ — Interim geometry-preservation guard (complete)

- **Scope:** `ldnaGeometryHydrated` gates geometry rebuild on editor hydration. Shipped `387a971d8`.
- **Verified:** structurally **[MEASURED]**. **Not verified behaviourally** (§16.1) — that requires G2.
- **Interim limitation:** while the editor cannot hydrate, geometry **cannot be intentionally cleared**.
- **Removed at:** **G4**, not G1 — see the C3 resolution below.

### G0.1 — Public geometry exposure remediation *(new; independent of all other gates)*

- **Scope:** decide and apply the T4 treatment for the six unauthenticated viewer routes (F-P1, §13).
- **Prerequisites:** none. This does not depend on the domain core, the renderer, or D1.
- **Owner decisions:** whether the public search-card flow must continue to work unauthenticated; and
  whether T4 presence-only (§13.3) is accepted as the v1 treatment.
- **Tests:** privacy-generalisation tests (§16.5); a test asserting no exact vertex reaches an
  unauthenticated route.
- **Rollback:** revert the serialisation change; the exposure returns, so rollback is a decision, not a
  default.
- **Stop condition:** stop and report once no unauthenticated route emits exact vertices.

**Why this is separate and first:** it is a live exposure that the renderer migration would otherwise
faithfully reproduce. Sequencing it behind an architecture programme would mean carrying it for the
duration of that programme.

### G1 — Domain core and mirror consolidation

- **Scope:** canonical state, serializer with omission capability, hydrator with interpretation modes
  (§5.4), envelope handling and the two operations (§6.2), capability resolver + config, revision token,
  provenance rules; **mirror/trait consolidation 5 → 1**, including converting `HasSearchAreas`'s four
  `empty()` sites; consumer-tolerance audit of the 42 consumers.
- **Not in scope:** any client JS change; any renderer; any provider change; any public API.
- **Prerequisites:** none technically. Characterisation coverage must exist for each workflow the
  consolidation touches (§16.3) *before* it is touched.
- **Owner decisions:** approve the contract (§5), the operation vocabulary (§6.2), the concurrency
  mechanism (§6.4), and the withdrawal of `neighborhoods` and `commute` (§18).
- **Tests:** §16.2, §16.3, §16.5, plus persistence round trips.
- **Rollback:** revert; config default preserves current behaviour.
- **Stop condition:** report with diffs and tests, **no page behaviour changed**, before anything else.

> **G1 is not "purely additive."** v1.1 described it that way while also giving it consolidation that
> touches four Offer components and a shared trait. Those are existing server-side code paths in live
> workflows. G1 is **additive core plus behaviour-preserving consolidation, parity-gated per
> workflow** — and its risk should be assessed on that basis.

### G2 — Behavioural verification capability

- **Scope:** propose, then install, browser automation. No product change.
- **Prerequisites:** owner approval of a new dev dependency.
- **Owner decisions:** approve the tool and its CI cost. Note **R8**: the `r2.dev` URL returns
  `403 / error code 1010` to non-browser user agents **[MEASURED]**, which breaks CI probes
  independently of CORS — a custom domain is required for CI tile checks.
- **Tests:** the harness itself, plus scenario 2 and scenario 6 from §16.8 as the first two tests —
  save-with-no-changes, and G0's behavioural verification.
- **Rollback:** remove the dependency.
- **Stop condition:** G0's runtime behaviour is demonstrated, or demonstrated *not* to hold.

**Placed before every renderer gate deliberately.** Migrating a renderer with no way to observe
renderer behaviour is how the current situation arose.

### G3 — Read-only renderer pilot (authenticated surfaces)

- **Scope:** `BasemapTileProviderInterface`, MapLibre renderer adapter, `NullRenderer`, read-only
  viewer behind a flag. Renders user geometry and our own boundaries only.
- **Prerequisites:** G1 (or standalone with a documented seam), G2, **G0.1 resolved**, R8 custom
  domain for production tiles.
- **Owner decisions:** D3 generalisation parameters **if** T3 geometry rendering is included; otherwise
  T4 presence-only and T1/T2 full precision, and D3 is not yet needed.
- **Tests:** renderer adapter contract incl. miles-vs-metres and `NullRenderer`; browser and visual;
  privacy generalisation; **no Google content on a MapLibre surface** (§10.2).
- **Rollback:** flag off.
- **Stop condition:** parity demonstrated against the Google viewer on authenticated surfaces, with
  the privacy tier enforced.

**Scope correction:** v1.0 called this pilot "structurally zero risk". True for data loss; **false for
privacy** (§13.1). The pilot targets exactly the six routes that are unauthenticated, which is why
G0.1 is a hard prerequisite.

**Explicitly excluded:** the subject-property read-only map, which is **not** unblocked, because the
coordinates it would render are Google-derived (§10.2). It belongs to G8.

### G4 — Patch transport (bridge 3 → 1) · removes the G0 limitation

- **Scope:** one transport adapter replacing three bridge implementations; envelope transport; explicit
  `clear` replacing "absent from payload"; the minimum client integration required to emit envelopes.
- **Prerequisites:** G1, G2.
- **Owner decisions:** accept that this **changes existing client behaviour** in four live workflows.
- **Tests:** transport adapter contract; concurrency (§16.6); browser scenarios 1–8; failure behaviour
  (§16.9).
- **Rollback:** flag off, per workflow.
- **Stop condition:** intentional clear works with the editor unhydrated, verified behaviourally; the
  G0 guard is retired in the same change that makes it unnecessary.

> **C3 resolution.** v1.1 claimed G1 "removes G0's interim limitation." It cannot: the guard lives in
> client code (`map-input.blade.php`) and G1 touches no client code. **The limitation remains until
> G4**, the gate that introduces explicit server-owned `clear` operations. G4 is explicitly **not**
> purely additive — it changes existing client behaviour, and is labelled accordingly.

### G5 — Provider-registry activation *(its own named gate)*

- **Scope:** implement a non-Google POI adapter; wire `LocationProviderRegistry` into the runtime path;
  flip POI from `google_places` to the new provider.
- **Prerequisites:** G1 for provenance rules; **R6 Overture licence verified**; adapter implemented
  (`osm_overpass` is annotated **NOT YET IMPLEMENTED** **[MEASURED]**).
- **Owner decisions:** authorise wiring the registry; authorise disturbing a provider annotated *"do
  not disturb"*; accept the licence conclusion.
- **Tests:** provider contract suite against the new adapter; provenance (§16.5); **no-Google-fallback**;
  before/after equivalence on POI results.
- **Rollback:** re-enable `google_places`; the registry returns to inert.
- **What remains inert after G5:** geocoding, autocomplete and routing providers — all still declared
  and disabled.
- **Provider before → after:** POI `google_places` → non-Google adapter. Everything else unchanged.
- **Observability:** per-provider call counts, error rates, cost attribution (the registry already
  carries `cost_per_1k` **[MEASURED]**), and a provenance breakdown of served records.
- **Stop condition:** POI served from a non-Google provider with provenance recorded, or rolled back.

> **C4 resolution.** v1.1 folded provider wiring into other work while simultaneously noting the
> registry is inert and the replacement adapter unimplemented. Those cannot both be true of a
> "configuration flip". Provider activation is now its own gate with its own licence verification and
> rollback.

### G6 — Geocoder replacement (**D1**)

- **Scope:** `GeocodingProviderInterface` and `AddressAutocompleteProviderInterface`; forward and
  reverse geocoding; autocomplete; replace `LocationDnaGeocodeService` and the server-side proxies.
- **Prerequisites:** **D1 decided**; OSM-derived licence obligations resolved (§14); `addresses` corpus
  populated (0 rows today **[MEASURED]**).
- **Owner decisions:** **D1** — the critical path.
- **Tests:** provider contract; accuracy comparison against current results; provenance labelling of
  every new coordinate; no-Google-fallback.
- **Rollback:** provider flag per capability.
- **Stop condition:** non-Google coordinates available with provenance, at acceptable accuracy.

### G7 — Editable renderer migration

- **Scope:** editable MapLibre per workflow. Pilot: **Buyer Hire Agent Listing** — it uses the shared
  trait *and* the shared bridge **[MEASURED]**, so the work generalises. Explicitly not Tenant Offer
  (inline bridge, inline mirror copy, recent city-mirror fix). Then Tenant Hire → Buyer Offer →
  Tenant Offer.
- **Prerequisites:** G3, G4, **G6/D1** — the renderer and autocomplete swaps are inseparable for
  editable Search Areas (§11.1).
- **Owner decisions:** per-workflow rollout approval.
- **Tests:** every category; full §16.8 browser set per workflow; characterisation parity before
  adaptation.
- **Rollback:** per-workflow flag.
- **Stop condition:** parity per workflow, reported individually.

### G8 — Subject-property profile

- **Scope:** the four Seller/Landlord workflows; subject-property marker and read-only map.
- **Prerequisites:** G6/D1, **plus resolution of the Google-provenance coordinate problem (§10.2)** —
  existing `property_lat`/`property_lng` are Google-derived and cannot be rendered over PMTiles.
- **Owner decisions:** whether historical Google-derived coordinates are re-geocoded or suppressed.
- **Tests:** Google-separation contract; provider contract; browser.
- **Rollback:** flag off.
- **Stop condition:** no Google-provenance coordinate rendered on a non-Google surface.

### G9 — Legacy retirement

- **Scope:** retire `map-input.blade.php`, `location-dna-map.blade.php`, remaining legacy transports.
- **Prerequisites:** G7 and G8 parity proven **per workflow** (L11).
- **Owner decisions:** accept retirement per workflow.
- **Tests:** characterisation equivalence; full browser set; no consumer left reading the legacy path.
- **Rollback:** retirement is the one irreversible step — hence parity first, and per workflow.
- **Stop condition:** never begin before parity evidence exists for that workflow.

### G10+ — Storage projection and contract consumers

- **Scope:** optimised spatial projection (§15); commute UI and provider; demographics; target-market;
  audience targeting; AI consumers (§12).
- **Prerequisites:** G1 for the contract; §15 invariants P1–P4 bind from day one, including for the
  projection that exists today.
- **Owner decisions:** substrate choice; routing provider evaluation (R10).
- **Stop condition:** per sub-gate, defined when proposed.

---

## 18. Speculative features reconsidered

Reviewed against the standard: keep only what current functionality, a confirmed near-term roadmap, or
compatibility requires.

| Item | v1.1 position | v1.2 decision | Reason |
|---|---|---|---|
| **`commute` contract in G1** | contract shape lands in G1 | **Withdrawn from v1** | The justification was "cheap insurance against a later schema change" — but §5.3 withdraws the byte-compatibility guarantee and §5.5 defines lazy upgrade, so adding a dimension later is no longer a migration event. The insurance is no longer needed, and no routing provider has been evaluated (R10). Adding a dimension with no UI, no provider and no consumer is exactly the speculative schema the standard rejects. |
| **`neighborhoods` as a canonical key** | stored, no UI, no consumer; owner question | **Withdrawn from the v1 contract; retained as read-tolerant** | A key no writer writes and no consumer reads is dead weight that every future reader must still handle. Readers continue to accept it where present in legacy data (G-C4), so nothing breaks; nothing new writes it. Reinstating it is a config + contract addition when a consumer exists. |
| **Empty overrides config** (`'overrides' => []`) | present, empty | **Removed** | An override mechanism with no entries and no defined merge semantics. Deny-by-default plus explicit profiles covers every current case (§7.2). |
| **Explicit capability listings vs deny-by-default** | explicit `true`/`false` per dimension | **Both — deliberately** | Deny-by-default is the *safety* property; explicit listings are the *reviewability* property, and were the reason config beat classes. Keeping both is not redundancy: the explicit map makes divergence diffable, and deny-by-default makes an omission safe rather than permissive. |
| **`dimension_meta`** | removed in v1.1 | **stays removed** (settled) | §3 clarification distinguishes it from provenance. |
| **`schema_version`** | present, undefined | **Retained with defined behaviour** (§5.5) | It now controls exactly one thing. Had no behaviour been definable, correction 16 required removal. |

**Net effect:** the v1 canonical contract is the **nine existing dimensions plus `subject_property`**
and nothing else. Every dimension has a writer, a reader and a test.

---

## 19. Scope — United States, v1

**The architecture is nationwide United States for v1. International expansion is not a v1
requirement**, and nothing in this document should be read as implying international support exists.

**Architecturally required now (L10):** no region-specific branching in domain, capability, provider or
renderer code; region-aware provider selection (`regions` key accepting `['*']` or region/state codes
**[MEASURED]**); region arguments on boundary, geocode and tile lookups; basemap archives addressed by
configuration **[MEASURED]**; at least one non-Florida fixture per geography-touching suite (§16.7).

**Data may begin Florida-only:** `places` 29,434 Florida rows; `boundaries` 67 Florida counties; the
PMTiles archive covers the Florida bbox at z0–15 **[MEASURED]**. Adding a state must mean building an
archive, importing boundaries and adding a config entry. If it ever requires touching the domain model,
the architecture has failed and that is a defect.

**Documented US-specific assumptions.** Each is confined to configuration or a named adapter, never the
domain model, and each has a test obligation (§16.7):

| Assumption | Where it lives | Neutral-naming note |
|---|---|---|
| **States** as the top-level administrative division | `state` dimension; `regions` config | `state` is the existing key name and is retained for compatibility (G-C1). A future international design would need a neutral `admin_area` concept; renaming now would break 42 consumers for no present benefit. |
| **ZIP codes** as the postal unit | `zip_codes` dimension | Same reasoning. Any *new* field should prefer `postal_code`. |
| **Miles** as the distance unit | `radius_miles`; conversion constant `1609.34` **[MEASURED]** | Retained. Unit is explicit in the key name, which is the important property. |
| **US-restricted geocoders** | Census Geocoder, NAD, OpenAddresses — all US-only | Confined behind `GeocodingProviderInterface`; a non-US geocoder is a new adapter, not a domain change. |
| **US-specific datasets** | FEMA NFHL, Census TIGER, Census school districts, Census ACS | Behind existing adapter interfaces. |
| **WGS-84 decimal degrees** | geometry throughout; Bridge OData `Latitude`/`Longitude` **[MEASURED]** | Not US-specific; stated so it is not mistaken for an assumption to revisit. |

**Naming rule going forward:** new field names avoid US-only terminology where a neutral name is
equally practical. Existing names are not renamed — compatibility (§5.3) outranks nomenclature.

**International support requires a later architecture decision.** It is not implied, not designed for,
and not blocked.

---

## 20. Risks and owner decisions

| # | Item | Impact | Owner call |
|---|---|---|---|
| **F-P1** | **Exact user-drawn vertices on six unauthenticated routes** **[MEASURED]** | Live privacy exposure; contradicts L14 | ✅ **immediate — G0.1** |
| **D1** | Non-Google geocoder + autocomplete | Blocks G6, G7, G8, all coordinate matching | ✅ **the critical path** |
| **D2** | Google-derived coordinates already persisted (`google_place_id`, `property_lat/lng`) **[MEASURED]** | Blocks G8; licence exposure if rendered over PMTiles | ✅ re-geocode or suppress |
| **D3** | T3 generalisation parameters (grid size, minimum span) | Blocks any T3 geometry rendering; not needed for T4 | ✅ before T3 ships |
| R1 | No browser automation **[MEASURED]** | No JS behaviour verifiable; **G0 unverified behaviourally** | ✅ approve tooling (G2) |
| R2 | Editable renderer + autocomplete inseparable (licence) | G7 waits on D1 | — |
| R3 | `boundaries` county-only **[MEASURED]** | No city/ZIP boundary display | ✅ authorise import |
| R4 | `addresses` 0 rows **[MEASURED]** | No subject-property coordinates | follows D1 |
| R5 | Registry inert; `google_places` "do not disturb" **[MEASURED]** | POI stays Google until G5 | ✅ authorise wiring |
| R6 | Overture licence **[UNVERIFIED]** | Could attach share-alike | ✅ verify before G5 |
| R7 | Livewire v2.12 **[MEASURED]**, EOL; transport uses v2 internals | Fragile; upgrade unplanned | ✅ is Livewire 3 planned? |
| R8 | No custom domain; `r2.dev` 403s non-browser agents **[MEASURED]** | Blocks production tiles **and CI probes** | ✅ |
| R9 | No PMTiles refresh pipeline; archive pinned `2026-07-26` **[MEASURED]** | Basemaps age silently | ✅ |
| R10 | No routing provider evaluated | Commute cost/latency unknown | — (deferred with `commute`) |
| R11 | 42 consumers, many assuming all keys present **[MEASURED]** | Omission could surprise a consumer | audit in G1 |
| R12 | Retention policy for drawn geometry absent **[NOT PRESENT]** | Sensitive data retained indefinitely | ✅ |
| R13 | Outbound geometry disclosure to Bridge MLS API **[MEASURED]** | Third-party disclosure of sensitive data | ✅ acknowledge and document |
| R14 | No clone/archive/restore/admin-correction paths exist **[NOT PRESENT]** | §8 rules unenforced until built | — |

---

## 21. Adoption record

Historical differences live here, not in the architecture. v1.0 and v1.1 are retained unchanged as
historical records; this section states only what a reader of v1.2 needs in order to trust it.

### 21.1 Lineage

| Version | Contribution | Status |
|---|---|---|
| v1.0 | First architecture. Correctly identified state-management-before-renderer, two capability profiles, wrap-don't-rewrite, read-only pilot, D1 as critical path. Introduced `dimension_meta` (a design error). Presented estimates as facts. | historical |
| v1.1 | Removed `dimension_meta`. Made client/server ownership explicit. Moved consolidation into G1. Added storage evolution and nationwide/Florida separation. Labelled evidence. Corrected the bundle figure and the "POI is one flip" claim. **Dropped the testing strategy, the AI boundary and the public contract surface.** | historical |
| **v1.2** | Restores the three dropped sections. Resolves C1–C6. Removes two structural blockers (transport coupling; lifecycle incompleteness). Defines concurrency, provenance scope, schema-version behaviour, projection consistency, lifecycle authority and the privacy algorithm. Withdraws speculative schema. Adds four measured findings, one of them a live exposure. | **proposed** |

### 21.2 Corrections carried in this document

Claims from earlier versions that v1.2 withdraws, with the reason:

- **"Byte-compatible"** (v1.0 §5.2, v1.1 §5.2) — withdrawn; incompatible with omission (§5.3).
- **"G1 removes G0's interim limitation"** (v1.1 §3.6, §11) — withdrawn; G1 touches no client code (§17 G4).
- **"G1 is additive"** (v1.1 §11) — withdrawn; consolidation changes live server code paths (§17 G1).
- **"POI flip is one config flip"** (v1.0 §7) — already corrected in v1.1; restated as G5 with a licence gate.
- **"Read-only viewer is structurally zero risk"** (v1.0 §10) — true for data loss, false for privacy (§13.1).
- **"~250 KB gzipped" bundle** (v1.0 §13) — corrected in v1.1 to **~404 KB gz** **[MEASURED]**; lazy-load on first map view, never in `app.js`.
- **Effort and complexity ratings** (v1.0 §18) — removed in v1.1 and not reinstated. No estimates are offered.
- **`neighborhoods` and `commute` in the v1 contract** (v1.0, v1.1) — withdrawn (§18).

### 21.3 Measured figures retained

Florida PMTiles archive 1,119,503,390 bytes (1.07 GiB), z0–15, PMTiles spec 3, vector; HTTP range reads
(206), `Accept-Ranges`, ETag verified. Delivered MapLibre payload ~404 KB gz. Nationwide tile estimate
**[DERIVED] ~35–70 GiB** (Florida ≈ 65,758 sq mi; CONUS ≈ 3,119,885 sq mi; ratio ≈ 47×, widened for
feature density and shared low-zoom tiles) — a derived estimate, not a measurement. A 1,200-vertex
polygon round-trips. Vertex cap ~1,000 **[ESTIMATE]**. Nationwide ZIP boundary count ~33,000
**[UNVERIFIED]**.

---

## 22. Adoption recommendation

**Recommendation: adopt v1.2 as the governing architecture, and authorise nothing beyond G0.1 and G1.**

**Why adopt.** The two conditions that should block adoption of a governing document are (a) a load-
bearing decision that is wrong, and (b) an invariant with no enforcement path. v1.1 had both: the
patch protocol was defined as a Livewire mechanism for a contract with 42 consumers, and its privacy,
compatibility and concurrency invariants had no tests, no mechanisms, and in one case no algorithm.
v1.2 closes those. Every invariant it states names an enforcement point and a test category (L12), and
where it defers, it names the gate and the owner.

**Two caveats that do not block adoption**, both labelled rather than hidden:

1. **Overture licence [UNVERIFIED] (R6)** — blocks G5, not adoption, and not G1.
2. **No routing provider evaluated (R10)** — now consequence-free, since `commute` is withdrawn from
   the v1 contract (§18).

**One item that does need action independent of adoption:** **F-P1**. It is a live exposure, it does
not depend on this architecture, and it should not wait for it.

**What I would ask for, in order:** (1) **F-P1** decision and G0.1. (2) **D1** — still the critical
path; until a non-Google geocoder exists, editable Search Areas cannot legally move to MapLibre,
polygon and radius matching cannot work, and no listing can carry a non-Google coordinate. (3)
**Browser automation (G2)** — without it, no renderer gate can be verified and G0 remains behaviourally
unproven. (4) **G1**, reported at its stop condition with no page behaviour changed.

**Where I still disagree with the framing this work started from:** it treats Location DNA as a mapping
problem. The genuinely dangerous defects found so far — a mirror going stale, geometry wiped by an
uninitialised renderer, absence indistinguishable from emptiness, and now exact private geometry served
to unauthenticated visitors — are **state-management and boundary defects, not rendering defects**. A
correct MapLibre renderer on the current state model would still lose data and would still leak
geometry. Fix the state model and the boundaries; the renderer is the easier half.
