# G1c — Architecture Decision Package

**Gate:** G1c (per §12 of the [G1 Pre-Implementation Report](./LOCATION-DNA-ENGINE-V1.2-G1-PRE-IMPLEMENTATION-REPORT.md))
**Type:** design and decision package — **owner-approved**
**Status:** **ALL SEVEN DECISIONS APPROVED. THREE INERT DOMAIN LAYERS IMPLEMENTED — G1c CONTRACT CORE, G1d CAPABILITY RESOLVER, G1e PROVENANCE MODEL. WORKFLOW INTEGRATION AND PERSISTENCE: NOT STARTED. G1f–G1g: NOT STARTED. G1 IS NOT COMPLETE.**
**Governed by:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md) · lineage
[v1.0](./LOCATION-DNA-ENGINE-V1.md) → [v1.1](./LOCATION-DNA-ENGINE-V1.1.md) → v1.2, adopted per the
[Adoption Record](./LOCATION-DNA-ENGINE-V1.2-ADOPTION-RECORD.md)
**Prepared at:** `a0df14fb7d2b63c506886afbf59a822ad9a2ecd6`
**Approved at (base commit):** `18cd954bcdc43b4796f69689f28bc2df47c45f22` · **Approval date:** 2026-07-30
**Implemented — G1c inert contract core:** `ddbf3b2bb3db9581f2c8125faf9ac9515fe9f38e` (2026-07-30)
**Implemented — G1d inert capability resolver:** `933228c2c12a4dd2d0dbde1147446c082c46cc6f` (2026-07-30)
**Implemented — G1e inert provenance model:** `00b7a025d15f3331b3265f358968d6476578f604` (2026-07-30)
— see the [Implementation record](#implementation-record).

> **The seven decisions below are APPROVED.** See the [Owner approval record](#owner-approval-record) for the
> exact approved options and the two carried conditions.
>
> **Approval of a decision is not authorization to implement it.** Each increment still requires its own
> separate authorization, and the stop conditions in the migration plan remain binding.
>
> **Implementation status, precisely:**
>
> | Scope | Status |
> |---|---|
> | G1c **inert contract core** | **IMPLEMENTED** — `ddbf3b2bb` |
> | G1d **inert capability resolver** | **IMPLEMENTED** — `933228c2c` |
> | **Workflow integration** | **NOT STARTED** |
> | **Persistence** | **NOT STARTED** |
> | G1e **inert provenance model** | **IMPLEMENTED** — `00b7a025d` |
> | **Provenance integration / persistence** | **NOT STARTED** |
> | G1f **writer consolidation** | **NOT STARTED** |
> | G1g **adapter contract** | **NOT STARTED** |
>
> The approval increment changed no production code, test or configuration. Three implementation increments
> have since added **deliberately inert** domain layers — the contract core (`ddbf3b2bb`), the capability
> resolver (`933228c2c`) and the provenance model (`00b7a025d`) — each wired into no workflow, no persistence
> and no public surface, each with its own unit tests, inertness guards, and exact readiness-allowlist entries
> recording its production additions.
>
> **None of G1c, G1d or G1e is complete, and G1 as a whole is not complete.** Each has an implemented inert
> layer and an unstarted integration half. Where this document records a gate as IMPLEMENTED it means the
> inert domain layer only — never its workflow integration or its persistence.
>
> The original alternatives, trade-offs and consequences for every decision are **preserved unchanged** below
> — approval records which option was chosen, not a rewrite of the analysis that produced it.

---

## 0. How to read this package

Four distinct kinds of statement appear throughout, always labelled:

| Label | Meaning | Force |
|---|---|---|
| **OBSERVED** | Current runtime or static behaviour, with a test or line citation | Fact. Not a preference. |
| **REQUIREMENT** | Already binding under an approved governing document (v1.2 §3 locked decisions, §5–§18, or the L-principles) | Uses **must**. Not open for decision here. |
| **RECOMMENDATION** | This package's proposal | Uses *should* / *proposed*. Never **must**. |
| **OWNER DECISION** | The owner's call. **All seven are now `APPROVED`** (2026-07-30) — see each decision's approval block and the [Owner approval record](#owner-approval-record). | Binding as a contract term. Does **not** authorize implementation. |

**Evidence base.** Seven characterisation suites, **77 tests**, all passing:

| Suite | Tests |
|---|---|
| `tests/Feature/Spatial/G1aTraitPresenceSemanticsCharacterisationTest.php` | 12 |
| `tests/Feature/Spatial/G1aRecordInterpretationCharacterisationTest.php` | 10 |
| `tests/Feature/Spatial/G1aCrossDimensionPreservationCharacterisationTest.php` | 6 |
| `tests/Feature/Spatial/G1aBuyerOfferInlineCharacterisationTest.php` | 12 |
| `tests/Feature/Spatial/G1aWorkflowPersistenceMatrixCharacterisationTest.php` | 7 |
| `tests/Unit/Services/Bridge/G1bCriteriaHashCharacterisationTest.php` | 21 |
| `tests/Feature/Spatial/G1bAcceptedBidDocumentCharacterisationTest.php` | 9 |

**Tests are evidence of what the system does today, not of what it should do.** Several recommendations
below deliberately propose changing behaviour a test currently pins; each such case is called out with the
test that will need to change and why that is intended rather than a regression.

**Where an approved clarification and the original recommendation differ, the approved clarification
governs.** The recommendations are preserved as authored so the reasoning remains auditable; the
**Approved clarifications** block under each decision is the binding text.

**Locked constraints that bound every option below** (v1.2 §3, not re-argued): server ownership of canonical
semantics (3); capable-but-non-authoritative client (4); presence-versus-absence as *the* semantic mechanism
(5); **no `dimension_meta`** (6); mirror and trait consolidation in G1 (13); JSON as the canonical transport
contract (14); user-drawn geometry treated as sensitive (19).

---

## D-G1-1 — Canonical Location DNA contract

### Exact question

What is the authoritative top-level shape of the canonical document, and what does each of *absent*, *null*,
*empty string*, *empty array* and *present-but-cleared* mean? How are unknown future keys, malformed values
and `schema_version` treated, and do administrative labels and geometry share one document?

### Current observed behaviour

**OBSERVED — the substrate already distinguishes the states; the readers do not.**
`test_s4_present_but_empty_survives_storage_as_a_distinct_state` proves `array_key_exists()` answers
truthfully against real storage. `test_s4_and_absence_are_distinguishable_in_storage_but_not_by_the_trait`
proves cleared and never-authored produce one outcome through the trait. So the defect is in the readers, and
**G1c does not need a storage change**.

**OBSERVED — `schema_version` is inert.** Zero production consumers; the only tree match is
`config/overture_places.php:43`, an unrelated Overture dataset version (G1b F-G1B-2). A record stamped `99`
is read *and rewritten* — `test_s2_unknown_future_schema_version_is_read_and_written_without_refusal`.

**OBSERVED — no consumer validates shape.** G1b F-G1B-4: the validator category is empty. A path-less
polygon reaches renderers and the matching engine unchecked.

**OBSERVED — `null` on the raw property is real.** With no blob,
`location_dna_preferences_json` holds boolean `false`, not `''`, because `??` does not catch `false`
(`HasSearchAreas.php:65`) — `test_s3_absent_blob_yields_an_empty_record_with_mirrors_as_the_only_source`.

**OBSERVED — three vocabularies coexist.** Canonical (`cities`, `zip_codes`, `counties`, `state`); the Bridge
DTO (`preferred_cities`, `preferred_counties`, no `state`); and `PropertyLocationDna::summary_json`
(`preferred_cities`, `preferred_zips`, `preferred_neighborhoods`) — G1b §7.K.

### Applicable evidence

v1.2 §5.1 (dimension table), §5.2 (presence rule and the `array_key_exists()` mechanism), §5.3 (G-C1–G-C4,
byte-compatibility withdrawn), §5.4 (S1–S5), §5.5 (`schema_version`), §18 (withdrawals). G1b F-G1B-2,
F-G1B-4. G1a `G1aRecordInterpretationCharacterisationTest` (all ten tests).

### Options

**Option 1-A — Adopt v1.2 §5 as written, unchanged.** Nine dimensions plus `subject_property`; one document;
`array_key_exists()` presence; absent = never authored; present-but-empty = intentionally cleared;
`schema_version` controls interpretation mode only, with refuse-to-interpret above 2.

*Advantages:* zero design divergence from the approved architecture; §5.4's S1–S5 fixtures already exist as
tests; the substrate is proven adequate; no migration.
*Disadvantages:* leaves `null` and empty-string semantics under-specified — §5.1 gives a canonical empty per
dimension but does not say what an explicit `null` on a dimension *means*; leaves malformed-value handling to
G1c to invent.
*Compatibility:* G-C1–G-C4 preserved. No renames.
*Migration:* none. Lazy upgrade on first v1.2 write (§5.5).
*Tests:* the S1–S5 suite becomes a compatibility suite rather than a characterisation suite. Two tests will
need to change by design — `test_s2_schema_version_is_ignored_and_behaves_identically_to_s1` and
`test_s2_unknown_future_schema_version_is_read_and_written_without_refusal` — because they pin the *absence*
of the interpretation mode.
*Privacy/security:* neutral. Geometry stays sensitive (§3 decision 19).

**Option 1-B — Adopt §5 and additionally close the three gaps it leaves.** Option 1-A plus: an explicit
`null`-is-equivalent-to-absent rule; empty string treated as the canonical empty for `state` and
`location_notes` only, and rejected elsewhere; and a named malformed-value disposition (reject at the
hydrator boundary, surface an error, never silently coerce).

*Advantages:* removes the three under-specifications that would otherwise be settled by whoever writes the
code first; gives F-G1B-4 an owner; makes the hydrator the single place shape is judged.
*Disadvantages:* more surface to specify and test now; the malformed-value rule creates a new error path
consumers do not currently have.
*Compatibility:* G-C4 (reader tolerance) needs care — historical writers produced malformed entries, and
rejecting them at read time would break records that render today. Mitigation: reject on *write*, tolerate
with a surfaced warning on *read*.
*Migration:* none required; the read-tolerance carve-out avoids a data migration.
*Tests:* Option 1-A's consequences, plus new contract tests per input class and a negative test that a
malformed value never silently becomes a clear (L5).
*Privacy/security:* mildly positive — a rejected malformed geometry cannot be half-rendered.

**Option 1-C — Split administrative labels and geometry into separate documents.**

*Advantages:* the public/private boundary becomes structural rather than a projection call, which would make
G0.1's containment and F-G1B-1's latent exposure impossible by construction rather than by discipline.
*Disadvantages:* it is a **migration event** for every one of the 44 consumer IDs and contradicts §5.1's
single-document table and §5.3's "no renames" guarantee. §18's net-effect statement ("the nine existing
dimensions plus `subject_property`, and nothing else") reads against it.
*Compatibility:* breaks G-C1 and G-C3 as written.
*Migration:* bulk migration required — explicitly excluded by settled decision "no migration required for
v1" (§5.5).
*Tests:* nearly all 77 characterisation tests would need rewriting.
*Privacy/security:* strongest of the three, and the only option that removes the class of risk F-G1B-1 names.

### Recommendation

**RECOMMENDATION: Option 1-B.** It keeps the approved contract intact while giving the three
under-specified cases an explicit owner, and it puts shape judgement in the hydrator, which §5.4 already
designates as the single reader of `schema_version`. Option 1-C is the better privacy architecture and is
worth revisiting at G10+ when a storage projection is on the table anyway (§17 G10+), but adopting it now
would contradict two settled decisions and invalidate the characterisation base G1a/G1b just built.

Specifically proposed under 1-B: `null` on a dimension is treated as **absent**, not as cleared; empty string
is the canonical empty for `state` and `location_notes` and is **rejected** for every other dimension;
unknown future keys are **retained on read and never emitted by a v1.2 writer** (§18's read-tolerant rule for
`neighborhoods`, generalised); malformed values are rejected on write and surfaced-but-tolerated on read.

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 1-B** |
| Owner status | **APPROVED** |
| Approved option | **1-B** — adopt the v1.2 §5 canonical contract and close the identified gaps |
| Approval | ☐ 1-A ☑ **1-B** ☐ 1-C ☐ Other |

**Approved clarifications (owner, 2026-07-30).** These are now binding contract terms for G1c, not
recommendations:

- **Absent** means *not supplied*. In a patch context absent means **preserve** — it is not an instruction.
- **`null`** is **not a valid authored dimension value**.
- **Empty string** is normalized according to the dimension contract and **may not silently stand in for
  clear**.
- **Empty array** is the canonical **cleared** value for collection dimensions.
- **Present-but-cleared** is **authoritative**.
- **Malformed values** are **rejected or quarantined**, never silently merged.
- **Unknown future keys** are preserved only through **version-aware hydration** and are **not interpreted by
  an older writer**.
- **`schema_version`** has defined read/write semantics.
- **Administrative labels and geometry may remain in the same private canonical document**, subject to
  projection at exposure boundaries. Option 1-C's split is therefore **not** adopted.

**Implementation status.** **Inert contract core: IMPLEMENTED** (`ddbf3b2bb`) — the canonical document, hydrator, normalizer and serializer express this contract as a framework-free domain layer. **Workflow integration and persistence: NOT STARTED.** No schema change was introduced by the approval commit and none by the implementation commit.

---

## D-G1-2 — Operation vocabulary

### Exact question

What explicit operations exist, how is operation intent carried, and how does the design distinguish an
unmounted editor from a deliberate clear?

### Current observed behaviour

**OBSERVED — the ambiguity is proven, not theoretical.**
`test_unmounted_editor_and_deliberate_clear_are_indistinguishable_to_consumers` shows an empty payload and a
deliberate clear-everything converge on one observable state.
`test_unmounted_editor_empty_payload_destroys_all_saved_geometry` shows the server writes an empty string
straight over the authoritative blob — every polygon, radius search and note gone in one save.

**OBSERVED — the only defence is client-side.** The G0 guard is JavaScript
(`387a971d8`); the server performs no parseability check, no comparison against stored state, and no
distinction between the two cases.

**OBSERVED — a no-op save is not side-effect-free.**
`test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror`: loading a mirror-only legacy record and
saving with no user edit writes `cities` as `[]`.

**OBSERVED — one save records a cleared dimension three different ways.**
`test_one_save_records_cleared_dimensions_three_different_ways` and
`test_three_way_clear_split_is_uniform_across_every_invocable_save_path`: `cities` honours the clear
(`HasSearchAreas.php:130`, no guard); `counties` and `state` keep stale values (`:123`/`:126` via the guarded
hydration at `:100`/`:103`).

### Applicable evidence

v1.2 §6.1 (envelope), §6.2 (two operations; "absence is never an instruction"), §6.3 (closed error set
including `unavailable`), §9 (client/server boundary), L5 (failure must never be recorded as a clear).
G1a F-G1-4, F-G1-7.

### Options

**Option 2-A — v1.2 §6.2 as written: exactly `set` and `clear`, carried in a transport-neutral envelope.**
One envelope, one dimension; `value` required for `set` and forbidden for `clear`; `set` with a canonically
empty value rejected (`empty_set_rejected`); absence from payload is **not** an operation.

*Advantages:* solves the ambiguity by construction — an unmounted editor cannot express anything, so it
produces no envelope and therefore no change; smallest vocabulary that covers current behaviour; already
approved.
*Disadvantages:* requires every one of the eight editing surfaces to construct envelopes, which is the G1f
work; the "no envelope means no change" rule depends on the adapter never fabricating one.
*Compatibility:* the wire format changes for the Livewire bridge. Read paths unaffected.
*Migration:* none for data. All eight workflows must be adapted (G1f), one at a time.
*Tests:* `test_unmounted_editor_empty_payload_destroys_all_saved_geometry` and its sibling
**will need to change by design** — they pin the destructive behaviour this decision exists to remove. That
is the intended outcome, not a regression, and the replacement assertion is "an empty payload produces no
write".
*Privacy/security:* positive — fewer accidental writes of geometry.

**Option 2-B — 2-A plus two additional operations: `preserve` and `migrate-from-legacy`.**

*Advantages:* makes "I am not touching this dimension" explicit rather than implied by omission, which is
easier to audit in a log; gives S5 mirror recovery a named operation instead of an implicit load-time merge.
*Disadvantages:* `preserve` is a synonym for "send no envelope", and v1.2 §6.2 rejects `replace` on exactly
that ground — two names for one operation is the duplicate-source-of-truth failure L1 exists to prevent.
`migrate-from-legacy` risks becoming a write path that promotes inherited values to authored, which §5.4
rule 5 forbids.
*Compatibility:* wider vocabulary to keep stable.
*Migration/Tests:* as 2-A plus coverage for two more operations.
*Privacy/security:* neutral.

**Option 2-C — Dirty-field metadata instead of an operation vocabulary.** The client sends the full document
plus a list of fields it actually touched.

*Advantages:* smallest change to the existing bridge; no envelope construction in eight components.
*Disadvantages:* the dirty list is **client-supplied authority over server semantics**, which contradicts
settled decisions 3 and 4 and L6. A client that fails to hydrate would send an empty dirty list *or* a wrong
one, and the server could not tell. It reintroduces the exact ambiguity this decision must solve, one layer
up.
*Compatibility/Migration:* least disruptive.
*Tests:* cheapest.
*Privacy/security:* negative — trusts the client.

### Recommendation

**RECOMMENDATION: Option 2-A.** It is the only option that solves the proven ambiguity *structurally* rather
than by adding a signal that can itself be wrong. The decisive argument against 2-C is that it violates
settled decisions 3 and 4; the argument against 2-B is that §6.2 already rejected a synonym operation for a
stated reason that applies unchanged to `preserve`.

Intent is proposed to be carried **in a command object** (an immutable envelope value object) constructed
server-side from adapter input — not in the raw payload, and not in dirty-field metadata. **REQUIREMENT
(v1.2 §6.1):** the envelope must not carry `user_id` or a capability list; the principal comes from the
authenticated session and capabilities are resolved server-side.

**How the unmounted-editor case resolves under 2-A:** the adapter cannot construct an envelope from an
unparseable or absent payload, so it raises `unavailable` (§6.3) and **no write occurs**. A deliberate clear
is an explicit `clear` envelope per dimension. The two are then different at the type level, not
distinguishable only by inspecting values.

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 2-A** |
| Owner status | **APPROVED** |
| Approved option | **2-A** — server-built command object with exactly two mutation operations |
| Approval | ☑ **2-A** ☐ 2-B ☐ 2-C ☐ Other |

**Approved clarifications (owner, 2026-07-30).** Exactly two mutation operations exist — **`set`** and
**`clear`** — and:

- An **unmounted field performs no operation**.
- An **omitted field performs no operation**.
- **Absence is not clear.**
- **`preserve` is represented by the absence of a command**, not by a third mutation operation. Option 2-B is
  therefore **not** adopted.
- **Legacy migration is an internal provenance/hydration concern**, not a user mutation operation.
- **Only an explicit `clear` command may withdraw an existing dimension.**

**Implementation status.** **Inert contract core: IMPLEMENTED** (`ddbf3b2bb`) — `DimensionOperation`, `DimensionCommand` and the pure `DimensionCommandApplier` realise exactly `set` and `clear`, with preserve expressed as the absence of a command. **Adapter and transport integration: NOT STARTED** (G1g); no workflow constructs a command yet. — Concurrency and hash semantics

### Exact question

Which orderings are semantically meaningful; must clearing geometry invalidate cached Bridge results; may
omission and explicit empty hash identically; and what is the canonical hash contract?

### Current observed behaviour

All from `G1bCriteriaHashCharacterisationTest` (21 tests) against the real
`CriteriaHashService::hash()`. Sole production caller: `LazyBridgeImportService.php:24`.

| Question | OBSERVED today | Citation |
|---|---|---|
| Polygon **vertex** order meaningful? | **No** — erased. Lists are value-sorted unconditionally | `CriteriaHashService.php:83-87`; `test_reordered_polygon_path_coordinates_do_not_change_the_hash` |
| Polygon **collection** order meaningful? | No | `test_reordered_polygons_entries_do_not_change_the_hash` |
| Radius entry order meaningful? | No | `test_reordered_radius_searches_entries_do_not_change_the_hash` |
| Clearing geometry invalidates cache? | **No** — clearing produces the same hash as never authoring | `:76`, `:82`; `test_missing_geometry_key_and_empty_geometry_array_hash_identically` |
| Omission ≡ explicit empty? | **Yes** | same test |
| `schema_version` affects hash? | No — structurally excluded (35-key whitelist) | `:30-66`; `test_higher_schema_version_does_not_affect_the_hash` |
| Unknown keys affect hash? | No — never collected | `test_unknown_future_keys_do_not_affect_the_hash` |
| Admin-label changes affect hash? | **Only under DTO names.** Canonical `cities`/`counties`/`state` are inert; `preferred_cities` moves it | `test_administrative_label_changes_affect_the_hash_only_under_dto_names` |
| `location_notes` affects hash? | No — not a DTO key | `test_location_notes_changes_do_not_affect_the_hash` |
| Malformed geometry hashable? | Yes, without error | `test_malformed_nested_geometry_is_hashed_without_error` |
| Mutates input? | **No** | `test_hashing_does_not_mutate_the_supplied_payload` |

Nested associative canonicalisation **is** recursive (`ksort` at `:98`), so U-G1B-2's original risk — spurious
churn from omission — does not exist.

### Applicable evidence

v1.2 §5.3 (hash the canonicalised form, never raw bytes; order-independence "where order is meaningful"),
§5.6 (revision token: deterministic, computed-not-stored, per-document **and per-dimension**, independent of
`schema_version`), §6.4 (per-dimension optimistic concurrency; losing autosave discarded, never retried),
§4.3 F-C3. G1b F-G1B-6.

### The substantive problem

**OBSERVED:** vertex order is erased, yet two polygons with the same vertex set in different order are
**different shapes**. A user who reshapes a polygon by reordering its path — which the editor can produce —
gets no cache invalidation and no MLS refetch. Separately, a user who clears all geometry also gets none.

**RECOMMENDATION — do not preserve current behaviour on these two points.** §5.3's order-independence is
qualified ("where order is meaningful"), and the current implementation applies it unconditionally. That is a
divergence from the governing document, not an intentional design.

### Options

**Option 3-A — Preserve current hash behaviour; document the two insensitivities as accepted.**
*Advantages:* zero change; 21 tests stay as-is; no cache churn risk.
*Disadvantages:* a reshaped polygon and a cleared search area both fail to invalidate. Accepts a
correctness gap in change detection.
*Compatibility:* perfect. *Migration:* none. *Tests:* none change.
*Privacy/security:* neutral, but a stale cached Bridge result can outlive the criteria that produced it.

**Option 3-B — Order-sensitive where order is meaningful; order-insensitive elsewhere; presence-sensitive.**
Vertex order **within** a polygon `path` becomes meaningful; the **collection** order of `polygons` and of
`radius_searches` stays insensitive; a cleared dimension hashes differently from an absent one.
*Advantages:* aligns the hash with §5.3 as written and with §5.6's per-dimension token requirement; a
reshape and a clear both invalidate.
*Disadvantages:* changes hash values for existing records → a **one-time cache-invalidation wave** and a
burst of Bridge refetches on first access. Needs a rate/cost check against R13 before rollout.
*Compatibility:* the hash is a cache key, not persisted contract (§5.6: computed, not stored), so no schema
impact. **Three tests change by design** —
`test_reordered_polygon_path_coordinates_do_not_change_the_hash`,
`test_missing_geometry_key_and_empty_geometry_array_hash_identically`, and the `polygons`-collection test
would be re-scoped.
*Migration:* none for data; operational only.
*Tests:* new per-dimension token tests (§16.2), plus a cost/volume check on the refetch wave.
*Privacy/security:* positive — geometry changes stop being invisible to downstream caches.

**Option 3-C — Separate the two concerns: keep `CriteriaHashService` for the Bridge cache key, and introduce
the §5.6 revision token as a distinct, presence-sensitive, order-aware hash for concurrency.**
*Advantages:* no cache-invalidation wave; the Bridge key keeps its current semantics while concurrency gets
the correct semantics it needs; matches §5.6's "independent stamps for independent concerns" house rule
(§4.3 F-C3).
*Disadvantages:* two hashes to keep straight; the Bridge cache retains the reshape/clear blindness of 3-A.
*Compatibility:* perfect for the Bridge path. *Migration:* none.
*Tests:* all 21 existing tests stay; new revision-token tests added.
*Privacy/security:* neutral-to-positive.

### Recommendation

**RECOMMENDATION: Option 3-C, with 3-B's vertex-order fix scheduled as a follow-up.** §5.6 requires a
per-document *and* per-dimension revision token that is independent of `schema_version`, and the Bridge cache
key cannot serve that role — it is whitelist-scoped, omits `state` and `location_notes` entirely, and uses a
different vocabulary. Building the revision token as its own component is required work regardless of what is
decided about the Bridge key, and it is the cheaper of the two orderings.

Proposed canonical **revision-token** contract (distinct from the Bridge cache key):

| Property | Proposed |
|---|---|
| Algorithm | recursive `ksort`, then SHA-256 over the canonicalised JSON — the established house pattern (§5.6, §4.3 F-C3) |
| Vertex order within a `path` | **preserved** — meaningful |
| Collection order of `polygons` / `radius_searches` | normalised — not meaningful |
| Absent vs present-but-empty | **distinct tokens** |
| `schema_version` | excluded (§5.6 explicitly) |
| Unknown future keys | included if retained on read, so an unknown key cannot be silently dropped between reads |
| Administrative labels | included, under **canonical** names |
| `location_notes` | included |
| Malformed values | tokenised without error; rejection is the hydrator's job (D-G1-1), not the hash's |
| Scope | per-document **and** per-dimension |
| Mutation | none |

Leaving the Bridge key untouched under 3-C means **the reshape and clear insensitivities persist for MLS
refetching**. That is an accepted, named consequence of the recommendation, not an oversight, and it is the
main reason to prefer 3-B if refetch correctness matters more than avoiding a one-time invalidation wave.

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 3-C** (+ 3-B vertex fix as scheduled follow-up) |
| Owner status | **APPROVED** |
| Approved option | **3-C** — separate the Location DNA revision token from the existing Bridge cache key |
| Approval | ☐ 3-A ☐ 3-B ☑ **3-C** ☐ Other |

**Approved clarifications (owner, 2026-07-30).** The Location DNA **revision token** contract is:

- **Polygon vertex order is semantically meaningful.**
- **Polygon vertex reordering must change the Location DNA revision token.**
- **Polygon collection order is not** semantically meaningful, unless later evidence proves otherwise.
- **Radius-search collection order is not** semantically meaningful, unless later evidence proves otherwise.
- **Explicit clearing must change** the revision token.
- **Omission / no-operation must not change** the revision token.
- **`schema_version` must affect the revision token when it changes interpretation.** (Note: this refines
  §5.6's "independent of `schema_version`" — the token is insensitive to a lazy upgrade that changes no
  values, and sensitive to a version change that alters interpretation.)
- **Unknown keys must be handled through the version-aware canonical document.**
- **`location_notes` must affect the private document revision token.**
- **Administrative-label changes must affect the document revision token.**
- **Malformed geometry may not be treated as a valid canonical hash input** — consistent with D-G1-1's
  reject-or-quarantine rule.

**Carried condition — Bridge cache-key deferral.** **`CriteriaHashService` and the Bridge cache key are not
changed during G1c contract-core implementation.** Correction of the Bridge cache key's polygon
vertex-order behaviour is **deferred to a separately authorized compatibility increment**, to be taken only
**after** the domain revision-token contract is implemented and characterized. Consequently the reshape and
clear insensitivities documented under Option 3-A **persist for MLS refetching** until that increment is
authorized — an accepted, recorded consequence. The 21 tests in
`G1bCriteriaHashCharacterisationTest` therefore remain valid and unchanged for now.

**Implementation status.** **Inert revision token: IMPLEMENTED** (`ddbf3b2bb`) as `LocationDnaRevisionToken`, format `ldna-r1:<sha256>`, per-document and per-dimension. **Concurrency enforcement: NOT STARTED** — comparing tokens inside the applying transaction requires the persistence service, which is not created. **The Bridge cache-key deferral stands unchanged**: `CriteriaHashService` was not touched, so a polygon reshape and a deliberate geometry clear still do not invalidate the Bridge cache. **Provenance separation: PROVEN** (`00b7a025d`) — G1e's provenance model does **not** feed the token. Principle 9 holds: the token represents interpreted Location DNA values, not provenance metadata, and `LocationDnaRevisionToken` was not modified. A test constructs two wildly different provenance maps over the same document and asserts the token is identical. — Withdrawals and clear behaviour

### Exact question

What is the authoritative behaviour for each clear case, for an unmounted-editor absence, and when legacy
mirrors disagree with the canonical blob? And are §18's withdrawals confirmed?

### Current observed behaviour

**OBSERVED — the clear outcome per dimension, per workflow.**
`test_cleared_cities_outcome_across_all_eight_workflows` pins the baseline: **6 of 8 workflows resurrect a
cleared `cities` list; 2 honour it.** The six are the four trait hosts plus both Buyer Offer inline copies;
the two correct ones are `TenantOfferListing:3339` and `TenantOfferListingEdit:2494`.

**OBSERVED — the mirror-write split.** Within one save: `cities` honours a clear
(`HasSearchAreas.php:130`); `counties` (`:123` via `:103`) and `state` (`:126` via `:100`) retain stale
values. Five presence-guard sites: 48, 71, 77, **100**, 103 — five, not the four §4.2 names.

**OBSERVED — the site-48 resurrection is latent, not constant.**
`test_full_clear_cycle_survives_only_because_the_cities_mirror_is_also_cleared`: line 130 clears the mirror in
the same save, masking the load-side defect. It bites only when the mirror is stale.

**OBSERVED — legacy mirror precedence today.** The mirror is consulted only when the blob key is absent, in
the two correct implementations; in the other three it is consulted whenever `empty()` is true, which
includes cleared.

### Applicable evidence

v1.2 §5.2, §5.4 (S4 intentional clear, S5 inherited-not-authored, rule 5 non-promoting), §8.2 (import
precedence), §18 (withdrawals). G1a F-G1-3, F-G1-4, F-G1-7; G1b F-G1B-3.

### Authoritative behaviour proposed (all cases)

| Case | Proposed authoritative behaviour | Changes today's behaviour? |
|---|---|---|
| `cities` cleared | present-but-empty; no fallback; mirror written `[]` | Yes for 6 of 8 workflows |
| `counties` cleared | present-but-empty; mirror written `[]` | **Yes for all 8** — today the stale value persists |
| `state` cleared | present-but-empty (`""`); mirror written `""` | **Yes for all 8** |
| `polygons` cleared | present-but-empty | No (no mirror involved) |
| `radius_searches` cleared | present-but-empty | No |
| **All** Location DNA cleared | every dimension present-but-empty; all mirrors emptied; blob retained with `schema_version: 2` | Yes |
| Field absent because editor not mounted | **no write at all** for that dimension; adapter raises `unavailable` | **Yes** — today the blob is overwritten with `''` |
| Legacy mirror disagrees with blob | **blob wins whenever the key is present**, including present-but-empty; mirror consulted only when the key is absent, and the recovered value is *inherited, not authored* (§5.4 S5) | Yes for 3 of 5 implementations |

**REQUIREMENT (v1.2 §5.4 rule 5):** a mirror-recovered value must not be written back as if authored.
**OBSERVED:** PHP alone does not promote today
(`test_s5_php_alone_does_not_promote_an_inherited_value_into_the_blob`), but the browser bridge could, and
that path is unproven (U-G1B-1, blocked on G2).

### Explicit protection of line 130

**RECOMMENDATION — treat `HasSearchAreas.php:130`'s clear-mirroring as a behaviour to preserve, not merely
to leave alone.** It is the one mirror write that already honours a clear, and it is what makes the site-48
defect latent rather than constant. A consolidation that "fixed" the read guards while regressing line 130
would convert a latent defect into a constant one.
`test_full_clear_cycle_survives_only_because_the_cities_mirror_is_also_cleared` is the test that catches
that, and it is proposed as a **binding stop condition** for G1f (already recorded as stop condition 4 in the
G1 report §15).

### Options

**Option 4-A — Converge all eight workflows on the proposed table above.**
*Advantages:* one behaviour everywhere; removes the 6-of-8 split; gives `counties` and `state` the clear
semantics `cities` already has.
*Disadvantages:* changes observable behaviour in all eight workflows; the `counties`/`state` change is
user-visible (a cleared county list will now actually disappear from Ask AI, matching, filtering and public
display).
*Compatibility:* G-C1–G-C4 preserved; no renames. Consumers that read the discrete mirrors will begin seeing
empty values where they previously saw stale ones — **29 of 44 consumer IDs decide presence with `empty()`
(G1b F-G1B-3)**, so most will render "nothing" rather than break.
*Migration:* no data migration. Existing stale mirrors self-correct on next save. Divergence repair is
addressed in D-G1-6.
*Tests:* the parity baseline test changes from 6/2 to 8/0 **by design**. Five presence-guard tests and the
three-way-split tests will need updating — each is a characterisation test whose purpose was to pin the
pre-consolidation state.
*Privacy/security:* positive — a user clearing geometry now actually clears it everywhere, which matters
under §3 decision 19.

**Option 4-B — Converge only `cities`; leave `counties` and `state` as-is.**
*Advantages:* smallest behavioural change; the two Tenant Offer implementations already do this, so
convergence is toward proven code.
*Disadvantages:* leaves two dimensions permanently unable to express a clear, which is a standing §5.2
violation and would have to be re-litigated later.
*Compatibility/Migration:* least disruptive.
*Tests:* fewer changes.
*Privacy/security:* leaves a case where a user's clear is silently ignored.

### Recommendation

**RECOMMENDATION: Option 4-A.** Partial convergence leaves the contract self-contradictory in exactly the way
§5.2 exists to prevent, and the audit shows the read side will absorb the change quietly (F-G1B-3).

**§18 withdrawals — confirmation sought, not assumed.** `commute` withdrawn entirely with no placeholder;
`neighborhoods` withdrawn from the contract but **read-tolerant** for legacy data; `'overrides' => []`
removed. **OBSERVED:** `neighborhoods` is still read in the wild at `buyer_criteria/add-bid.blade.php:345`
and, under the `preferred_neighborhoods` name, at `offers/show.blade.php:392` and `:525` — all three against
`summary_json`, where each resolves to a default (G1b F-G1B-1).

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 4-A**, §18 withdrawals confirmed as written |
| Owner status | **APPROVED** |
| Approved option | **4-A** — converge all eight workflows on the canonical clear behaviour |
| Approval | ☑ **4-A** ☐ 4-B ☐ Other · §18 withdrawals: ☑ **confirmed** ☐ revisit |

**Approved clarifications (owner, 2026-07-30).** The existing **§18 withdrawal semantics are confirmed**
(`commute` withdrawn entirely; `neighborhoods` withdrawn from the contract but read-tolerant;
`'overrides' => []` removed). The authoritative clear behaviour is:

- **Clearing `cities`** removes cities and updates or clears the derived mirror.
- **Clearing `counties`** removes counties and updates or clears the derived mirror.
- **Clearing `state`** removes state and updates or clears the derived mirror.
- **Clearing `polygons`** removes polygons.
- **Clearing `radius_searches`** removes radius searches.
- **Clearing every dimension** produces a **valid canonical cleared document**.
- **An unmounted editor makes no change.**
- **Stale legacy mirrors may not resurrect an explicitly cleared canonical value.**
- **The currently correct `HasSearchAreas.php:130` mirror-clearing behaviour is protected.**
- **The two correct Tenant Offer behaviours are the convergence baseline.**
- **The current defective trait and Buyer Offer resurrection behaviour is not preserved.**

Option 4-B (converge `cities` only) is **not** adopted.

**Implementation status.** **Inert clear semantics: IMPLEMENTED** (`ddbf3b2bb`) — a cleared dimension is present-at-canonical-empty and authoritative throughout the core pipeline. **Per-dimension clear PROVENANCE: IMPLEMENTED inert** (`00b7a025d`) — `OwnerCleared` is an authoritative provenance kind, explicitly distinct from absence, and it is the only kind that blocks fallback resurrection; a transition rule denies any automatic restoration of an owner-cleared dimension from a mirror, an inherited value, a derived value or the retained snapshot. **Per-dimension clear AUTHORISATION: IMPLEMENTED inert** (`933228c2c`) — the G1d resolver expresses which dimensions may be set and which cleared, per context, using the G1c `Dimension` vocabulary; and repair is denied for a canonical record, which is how a mirror is prevented from resurrecting a present-but-cleared dimension. **Workflow convergence: NOT STARTED.** The parity baseline moves from 6/2 to 8/0 only when G1f is separately
authorized.

---

## D-G1-5 — Canonical writer and implementation ownership

### Exact question

Is the trait replaced, rewritten or retired; are the inline implementations removed; what becomes canonical;
and which layer owns normalisation, persistence and legacy-mirror compatibility?

### Current observed behaviour — the proven inventory

**Defective semantics (3):**

| Implementation | Proven by |
|---|---|
| `app/Http/Livewire/Concerns/HasSearchAreas.php` (132 lines) | `test_site48_cleared_cities_are_resurrected_from_the_legacy_mirror` |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | `test_create_flow_cleared_cities_are_resurrected_from_the_mirror` |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` | `test_edit_flow_cleared_cities_are_resurrected_from_the_mirror` |

**Correct divergent semantics (2):** `TenantOfferListing.php:3339` and `TenantOfferListingEdit.php:2494`,
both using `array_key_exists('cities', …)`, both proven by
`test_cleared_cities_outcome_across_all_eight_workflows`.

**OBSERVED — the trait serves 4 of the 8 workflows**; the Buyer Offer pair and Tenant Offer pair carry their
own copies. Component sizes range 2,271–5,323 lines.

### Options

**Option 5-A — Retire the trait; introduce a domain service; components delegate.** `HasSearchAreas` is
deleted after all four hosts are migrated; both Buyer Offer inline copies are removed; the two Tenant Offer
divergences are removed in favour of the service, which implements their (correct) semantics.

*Advantages:* one implementation, server-side, framework-free — satisfies §6's "the domain core must not
depend on Livewire, Blade, HTTP or JavaScript types"; consolidation 5 → 1 as settled decision 13 requires;
the correct behaviour becomes the default rather than a local divergence.
*Disadvantages:* touches all eight large components; the highest-risk increment in G1.
*Compatibility:* observable behaviour changes per D-G1-4. Host contract (`$state`, `$counties`, `$cities`
props) can be preserved during transition to limit blast radius.
*Migration:* no data migration; per-workflow code migration, one commit each.
*Tests:* every workflow's characterisation must pass unchanged **except** the clear-semantics assertions that
D-G1-4 intentionally changes. The `test_hire_trait_semantics_are_unchanged` tripwire in
`TenantOfferCitiesMirrorTest` **will fire** — by design; it exists to force exactly this re-verification.
*Privacy/security:* positive.

**Option 5-B — Rewrite the trait in place; keep it as the single implementation.**
*Advantages:* smallest diff; the four trait hosts need no change at all; familiar shape.
*Disadvantages:* the trait is a Livewire concern — keeping it canonical welds the domain core to the UI
framework, which §6 identifies as "the first structural blocker" and v1.2 explicitly corrects. Does not help
the four non-trait workflows.
*Compatibility/Migration:* least disruptive.
*Tests:* fewest changes.
*Privacy/security:* neutral.

**Option 5-C — Domain service now, trait retained as a thin deprecated adapter.**
*Advantages:* all of 5-A's architecture with a smaller first step — the trait becomes a two-line delegation,
so the four hosts converge immediately without being edited; inline copies removed next.
*Disadvantages:* leaves a deprecated shim that must actually be removed later, or it becomes permanent.
*Compatibility:* best of the three during transition.
*Migration:* two-stage: shim, then removal.
*Tests:* the tripwire still fires; per-workflow parity still required.
*Privacy/security:* positive.

### Layer ownership proposed (identical under 5-A and 5-C)

| Concern | Proposed owner |
|---|---|
| Canonical state representation | immutable value object (`LocationDnaDocument`) |
| Parsing / interpretation mode | hydrator — **REQUIREMENT (§5.5):** the only reader of `schema_version` |
| Normalisation | normaliser, invoked by the hydrator on read and the serializer on write; never by a consumer |
| Presence semantics | value object, via `array_key_exists` — never `empty()` |
| Persistence | persistence service; the only writer of the meta key |
| Legacy mirror compatibility | a dedicated adapter, isolated so its removal is a single-file change (D-G1-6) |
| Public redaction | existing `PublicGeometryProjection` — unchanged, and **REQUIREMENT:** G0.1's seam must be preserved, not routed around |
| Hash / revision token | separate component per D-G1-3 |

### Recommendation

**RECOMMENDATION: Option 5-C.** It reaches 5-A's end state but converges the four trait hosts in one small
step, which materially reduces the risk of the largest increment. The shim's removal is proposed as an
explicit G1f exit criterion so it cannot become permanent.

**Convergence without regressing the two correct behaviours:** the service implements the Tenant Offer
semantics as its default, so those two workflows converge by *adopting equivalent behaviour*, and their
existing characterisation tests should pass unchanged. That is the proposed acceptance test for the
consolidation: **the two correct workflows' tests must not change.**

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 5-C** |
| Owner status | **APPROVED** |
| Approved option | **5-C** — domain service now; trait retained temporarily as a thin, deprecated compatibility shim |
| Approval | ☐ 5-A ☐ 5-B ☑ **5-C** ☐ Other |

**Approved clarifications (owner, 2026-07-30).** Ownership is fixed as follows:

- **`LocationDnaPersistenceService` becomes the sole canonical writer.**
- **Inline Buyer Offer writers are removed** during controlled G1f convergence.
- **Tenant Offer implementations are routed through the canonical writer without losing their correct clear
  semantics.**
- **The trait may delegate only** — it may **not** independently normalize or merge.
- **Normalization belongs to the domain normalizer.**
- **Hydration belongs to the hydrator.**
- **Persistence orchestration belongs to the persistence service.**
- **Legacy compatibility belongs exclusively to `LegacyMirrorAdapter`.**
- **Public projection remains a separate exposure-boundary concern** (`PublicGeometryProjection`, unchanged).

**Implementation status.** **Provenance layer: IMPLEMENTED inert** (`00b7a025d`) — `App\Services\LocationDna\Provenance` classifies value origin and standing without touching persistence, and is a third sibling domain namespace alongside the contract core and the capability resolver. **Capability layer: IMPLEMENTED inert** (`933228c2c`) — `LocationDnaCapabilityResolver` now expresses the approved layer ownership as authorisation facts, including that persistence, legacy repair and snapshot access are each a distinct capability rather than a consequence of ownership. **Canonical writer: NOT STARTED.** Deliberately: `LocationDnaPersistenceService` and the trait shim were both excluded from the G1c increment, and `LegacyMirrorAdapter` was not created. The core's component boundaries express the approved layer ownership, but nothing writes, delegates or replaces an inline writer yet. The shim's removal remains a G1f exit criterion.

---

## D-G1-6 — Legacy mirrors and migration

### Exact question

Do the `cities` / `counties` / `state` mirrors remain writable; are they backfilled; is divergence repaired
lazily or by migration; what is the precedence, sunset criteria, rollback and required observability?

### Current observed behaviour

**OBSERVED — the mirrors have real consumers.** The trait's docblock names Ask AI, the match engine,
filtering and public display. G1b counts **five Stellar criteria loaders** plus matching consumers reading
discrete or blob values (C-24 … C-35).

**OBSERVED — divergence is reachable today.** `test_s3_php_only_save_preserves_corrupt_bytes_but_empties_the_mirror`:
a corrupt blob leaves the bytes intact while the `cities` mirror is rewritten to `[]` — blob and mirror
silently disagree, with no error surfaced.

**OBSERVED — a no-op save can destroy a legacy mirror**
(`test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror`).

### Options

**Option 6-A — Mirrors remain writable, derived from the blob on every write; read-only as a fallback.**
Precedence: blob wins whenever the key is present; mirror consulted only when the key is absent, yielding an
*inherited* value (§5.4 S5).
*Advantages:* every existing mirror consumer keeps working with no change; matches the trait's stated design
("the map blob is authoritative; the discrete keys are derived"); no migration.
*Disadvantages:* two stores stay in sync only by discipline; divergence remains possible on the corrupt-blob
path.
*Compatibility:* highest. *Migration:* none.
*Tests:* mirror-derivation tests per dimension; a divergence test for the corrupt-blob case.
*Privacy/security:* neutral.

**Option 6-B — Mirrors become read-only fallback immediately; stop writing them.**
*Advantages:* one writer, one truth; divergence becomes impossible going forward.
*Disadvantages:* every mirror consumer must read through the domain service instead — that is the G1b audit's
44-ID surface, and it converts D-G1-6 into a large consumer-migration project. Existing rows keep whatever
mirror values they have, frozen and increasingly stale.
*Compatibility:* breaks consumers that read discrete meta directly until each is migrated.
*Migration:* consumer migration required before the write stops, not after.
*Tests:* every mirror consumer needs a test proving it reads through the service.
*Privacy/security:* neutral.

**Option 6-C — 6-A now, with a defined sunset to 6-B.** Mirrors stay writable and derived; a lazy repair
fixes divergence on write; observability is added; removal is gated on measured evidence that no consumer
reads the discrete keys.
*Advantages:* no flag day; the sunset is evidence-gated rather than date-gated; rollback is trivial while the
mirrors still exist.
*Disadvantages:* the deprecation window must actually be closed; requires observability work that produces no
user-visible value.
*Compatibility:* highest. *Migration:* lazy, write-triggered — consistent with §5.5's lazy-upgrade rule and
the settled "no migration required for v1".
*Tests:* 6-A's tests plus a guard that no *new* discrete-meta reader is introduced.
*Privacy/security:* neutral.

### Recommendation

**RECOMMENDATION: Option 6-C.**

- **Backfill:** none proposed. Repair lazily on write, consistent with §5.5's lazy upgrade and the settled
  no-migration decision. A bulk backfill would rewrite records the user has not touched, which risks
  converting *inherited* values into *authored* ones — forbidden by §5.4 rule 5.
- **Precedence during transition:** blob wins when the key is present (including present-but-empty); mirror
  consulted only when absent; recovered values marked inherited.
- **Sunset criteria (all four, evidence-based):** (1) zero discrete-meta reads observed over a full business
  cycle; (2) all five Stellar loaders migrated to the domain service; (3) a guard test failing on any new
  discrete-meta reader; (4) F-G1B-1's `summary_json` vocabulary question resolved, since two of the three
  readers there use mirror-adjacent names.
- **Rollback boundary:** while mirrors are still written, rollback is reverting the writer — no data loss,
  because the mirrors are still populated. After the write stops, rollback requires a backfill; that is the
  point of no easy return, and it is why the sunset is evidence-gated.
- **Observability required before removal:** per-key read counters on discrete `cities` / `counties` /
  `state`, attributable to caller, retained long enough to cover seasonal workflows.

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 6-C** |
| Owner status | **APPROVED** |
| Approved option | **6-C** — derived-writable mirrors during transition, lazy repair, evidence-gated sunset |
| Approval | ☐ 6-A ☐ 6-B ☑ **6-C** ☐ Other |

**Approved clarifications (owner, 2026-07-30).**

- **The canonical document is authoritative when valid and present.**
- **Mirrors are derived outputs**, not independent authored truth.
- **Mirrors may be used as fallback only for a legacy-only record.**
- **Mirrors may not override present-but-cleared canonical values.**
- **Divergence is repaired lazily**, when a safely interpreted record is written.
- **No bulk backfill is authorized.**
- **Lazy repair must preserve provenance** and **may not convert inherited values into authored values**
  (§5.4 rule 5).
- **Observability is required before mirror removal.**
- **Mirror sunset requires separate approval**, after evidence shows no required readers remain.
- **Rollback consists of stopping canonical-writer adoption while retaining mirror compatibility** — **not**
  restoring resurrection behaviour.

**Implementation status.** **Mirror PROVENANCE: IMPLEMENTED inert** (`00b7a025d`) — `LegacyFallback` (read-through, non-authoritative) and `LegacyRepaired` (present in canonical storage, still not owner-authored) are distinct kinds, and migration repair has exactly one legal transition, `LegacyFallback -> LegacyRepaired`. D-G1-6's approved rule that lazy repair "must preserve provenance and may not convert inherited values into authored values" is therefore expressible and enforced at the model level — no repair runs. **Mirror CAPABILITY: IMPLEMENTED inert** (`933228c2c`) — the resolver distinguishes consulting a mirror from repairing one, grants consultation only for a legacy-only record, and denies repair outright for a canonical record. **Mirror BEHAVIOUR: NOT STARTED.** `LegacyMirrorAdapter` was not created; the hydrator deliberately has no mirror concept at all, so no fallback, repair or precedence behaviour exists yet. No bulk backfill was performed and none is authorized. — Snapshot retention and future readers

### Exact question

`AcceptedBidSummary.location_intelligence_snapshot` retains the full unprojected blob — exact geometry and
`location_notes` — with zero production readers. What is the authoritative disposition?

### Current observed behaviour

**OBSERVED — the column holds everything, unprojected.**
`test_snapshot_extraction_retains_full_unprojected_geometry` proves sentinel polygon coordinates, radius
centre address and `location_notes` all survive into the snapshot, with **no projection marker** —
`AcceptedBidSummaryService.php:730`, `BuyerAcceptedBidSummaryService.php:476`,
`BackfillLocationSnapshots.php:146`.

**OBSERVED — zero production readers.**
`test_retention_column_has_no_production_read_sites` asserts the column appears in exactly four files: the
three writers and the model's `$fillable`/`$casts`. No view references it.

**OBSERVED — the rendered document is safe today.** `test_public_rendered_html_contains_labels_and_no_geometry`
and `test_build_target_areas_returns_empty_when_only_the_canonical_blob_is_present`: the document renders
administrative labels only, sourced from legacy discrete meta, never from the blob.

**OBSERVED — the audience includes a non-owner.** `test_recipients_are_owner_and_counterparty_only`:
`canAccessSummary()` at `AcceptedBidSummaryController.php:335-338` admits `tenant_user_id` **and**
`agent_user_id`.

**This state is not safe by design — it is safe by the accident of no reader existing.** Adding one reader,
anywhere, creates a non-owner geometry exposure with no projection in the path. R12 already tracks the
retention concern; the rendering counterpart was unowned until now.

### Options

| # | Option | Intended future use | Authorization | Retention | Access controls | Projection | Test guard | Existing rows |
|---|---|---|---|---|---|---|---|---|
| **7-A** | Retain full data under an explicit private-data contract | dispute evidence; audit of what was agreed | owner + named counterparty only, per-read authorised | tied to transaction record lifetime | at-rest encryption or restricted column grant | none at write; **mandatory at every read** | reader-allowlist guard | unchanged |
| **7-B** | Store a projected administrative-label-only snapshot | same audit purpose, reduced fidelity | as document | as document | not needed | `PublicGeometryProjection` at write | shape guard asserting no geometry keys | migration to re-project or drop geometry |
| **7-C** | Split: labels in the summary row, geometry in a restricted store | full evidence with least privilege | separate, stricter grant for the geometry store | independent per store | encryption on the geometry store | at the boundary between them | guard per store | migration to split |
| **7-D** | Stop writing the unused snapshot | none — accept loss of evidence | n/a | n/a | n/a | n/a | guard that nothing writes it | orphaned data to purge or leave |
| **7-E** | Retain temporarily with a sunset and a reader guard | decide later with evidence | unchanged | explicit sunset date | unchanged | required if a reader is ever added | **reader guard already exists** | unchanged until sunset |

### Recommendation

**RECOMMENDATION: Option 7-E in the near term, resolving to 7-B or 7-C once the intended use is known.**

The reason is that **nobody currently knows what the snapshot is for.** It was introduced for §15 projection
consistency and R12 tracks its retention, but no requirement names a reader. Choosing 7-A commits to an
access-control programme for data with no consumer; 7-D destroys evidence that may be needed for exactly the
disputes an accepted-bid document exists to settle. 7-E costs almost nothing because the guard already exists
— `test_retention_column_has_no_production_read_sites` fails the moment a reader appears.

Proposed under 7-E: a sunset review at which the owner either names the use (→ 7-A/7-B/7-C) or stops the
write (→ 7-D); the existing reader guard promoted to a **binding stop condition** for G1d–G1g; and the
question of whether a future reader would require projection recorded explicitly as **undecided** rather
than left to the first implementer.

**Not recommended: treating the current state as acceptable indefinitely.** The user's framing is correct —
absence of a reader is not a safety property.

### Owner approval

| Field | Value |
|---|---|
| Recommended | **Option 7-E**, resolving to 7-B or 7-C |
| Owner status | **APPROVED** |
| Approved option | **7-E** — retain the snapshot temporarily under an explicit sunset and reader guard |
| Approval | ☐ 7-A ☐ 7-B ☐ 7-C ☐ 7-D ☑ **7-E** ☐ Other |

**Approved clarifications (owner, 2026-07-30).**

- **The existing unprojected snapshot is private sensitive data.**
- **No new production reader may be added without separate architecture approval.**
- **Any future reader must define authorization and projection requirements first.**
- **The existing structural guard against new readers remains required** —
  `test_retention_column_has_no_production_read_sites` in
  `tests/Feature/Spatial/G1bAcceptedBidDocumentCharacterisationTest.php`.
- **Existing snapshot rows are not deleted or rewritten during G1c.**
- **The final disposition is not chosen yet** — permanent full retention (7-A), projected-only (7-B) and
  separated sensitive retention (7-C) all remain open.
- **Reassess and select the final disposition before G1g is declared complete.**
- **If a production reader becomes necessary before then: stop and obtain an explicit D-G1-7 amendment
  before implementing it.**

**Implementation status.** **Snapshot PROVENANCE: IMPLEMENTED inert** (`00b7a025d`) — `SnapshotRetained` is representable as an ORIGIN and carries the authority `forbidden_as_restoration_source`; every automatic and migration transition out of it is denied, and it grants no snapshot-access capability. **Snapshot DENIAL: IMPLEMENTED inert** (`933228c2c`) — both the retained-snapshot and the future-snapshot-reader contexts resolve to a fully denied capability set, and a test asserts **exhaustively** across every surface × viewer × purpose combination that no context grants snapshot access. **No reader: unchanged.** The snapshot was not read, written, deleted or re-projected by either increment, and the existing structural reader guard still holds. **The final disposition among 7-A / 7-B / 7-C remains due before G1g is declared complete**, and no reader may be added without a separate approved amendment.

**Illustrative only. Not production code. Not authorised for implementation.**

### Components and responsibilities

| Proposed name | Responsibility | Must not |
|---|---|---|
| `LocationDnaDocument` | immutable value object; per-dimension presence via `array_key_exists`; exposes `isAuthored()` / `isCleared()` / `isAbsent()` per dimension | know about Livewire, Blade, HTTP, or persistence |
| `DimensionEnvelope` | immutable command: `target`, one `dimension`, `operation`, conditional `value`, `workflowContext`, conditional `expectedRevision`, conditional `provenance`, `idempotencyKey` | carry `user_id` or a capability list (§6.1) |
| `LocationDnaHydrator` | the **only** reader of `schema_version`; determines interpretation mode once; performs S5 mirror recovery marked inherited; surfaces corrupt blobs | upgrade on read; let consumers re-derive mode; promote inherited → authored |
| `LocationDnaNormalizer` | value shape/unit normalisation (miles, coordinate precision, blank filtering) | decide presence; drop keys |
| `LocationDnaSerializer` | omits never-authored keys distinguishably from canonical-empty; stamps `schema_version: 2`; lazy-upgrades on first write | emit omission as a result of failure (L5); guarantee byte-identity |
| `LocationDnaRevisionToken` | per-document and per-dimension token per D-G1-3 | depend on `schema_version` (§5.6) |
| `LocationDnaPersistenceService` | the only writer of the canonical meta key; applies a validated envelope batch atomically inside one transaction | write when the schema version is unknown |
| `LegacyMirrorAdapter` | derives discrete `cities`/`counties`/`state` on write; supplies inherited fallback on read; isolated for single-file removal | be consulted when the blob key is present |
| `PublicGeometryProjection` | **exists and is unchanged** — public redaction seam from G0.1 | be routed around |
| `LocationDnaCapabilityResolver` | *(G1d — **IMPLEMENTED inert**, `933228c2c`)* deny-by-default resolution over an explicit context. Shipped with `LocationDnaCapability`, `LocationDnaCapabilitySet`, `LocationDnaAccessContext`, `LocationDnaSurface`, `LocationDnaViewerRelationship`, `LocationDnaPurpose` and `LocationDnaCapabilityException` | be referenced by any workflow, controller, model, route or view |

### Illustrative interfaces

```php
// Value object — presence is structural, never empty()-derived.
interface LocationDnaDocumentInterface
{
    public function isAbsent(string $dimension): bool;   // key not present
    public function isCleared(string $dimension): bool;   // present, canonical empty
    public function isAuthored(string $dimension): bool;  // present, non-empty
    public function value(string $dimension): mixed;      // null when absent
    public function interpretationMode(): InterpretationMode; // S1|S2 — set at hydration
    public function revisionToken(?string $dimension = null): string;
}

// Operation vocabulary — D-G1-2 Option 2-A.
enum DimensionOperation { case Set; case Clear; }

final class DimensionEnvelope
{
    // value REQUIRED for Set and FORBIDDEN for Clear (§6.2).
    // Absence from a batch is NOT an instruction (§6.2).
}

interface LocationDnaPersistenceServiceInterface
{
    /** @param DimensionEnvelope[] $batch  Validated as a whole; applied atomically. */
    public function apply(array $batch): PatchResult;   // never partially applies
}
```

### Workflow walk-throughs (proposed behaviour, not current)

| Workflow | Proposed handling |
|---|---|
| **Create** | No prior document. First write stamps `schema_version: 2` and records the observed presence set, so subsequent absence is meaningful (§5.4 rule 4). `expectedRevision` not required for a first authoring write (§6.4). |
| **Edit** | Hydrate → mode fixed once → user edits one dimension → adapter builds one `Set` envelope carrying `expectedRevision` for that dimension → server compares tokens inside the applying transaction. Other dimensions are untouched because no envelope names them. |
| **Deliberate clear** | One explicit `Clear` envelope per cleared dimension. Serializer writes the canonical empty; `LegacyMirrorAdapter` writes the empty mirror. Distinguishable from absence at the type level. |
| **Unmounted editor / no-op** | Adapter cannot construct an envelope from an absent or unparseable payload → raises `unavailable` (§6.3) → **no write**. This is the structural resolution of the proven ambiguity. A genuine no-op save produces an empty batch, which applies nothing — including no mirror rewrite, fixing `test_no_op_save_on_a_legacy_record_destroys_the_cities_mirror`. |
| **Legacy-only record** | Mode S1. Blob absent → mirror supplies values marked **inherited**. Nothing is promoted to authored until the user next writes that dimension (§5.4 rule 5). |
| **Corrupt blob** | Hydrator surfaces an error rather than returning an empty record (§5.4 S3). Proposed: read-only degradation with the raw bytes preserved, and **no mirror rewrite** — which is the current divergence path (`test_s3_php_only_save_preserves_corrupt_bytes_but_empties_the_mirror`). |
| **Higher-version blob** | `schema_version > 2` → refuse to interpret, fail loudly, read-only, no write (§5.5). Changes today's behaviour, which reads and rewrites. |
| **Concurrent update** | Per-dimension optimistic concurrency. Different dimensions both succeed; the same dimension yields `revision_conflict` for the loser, which reloads and re-presents. A losing autosave is **discarded, never retried** (§6.4). |

---

## Proposed migration plan (post-approval, none authorised now)

| Increment | Authorized production surfaces | Required tests | Stop conditions | Rollback boundary | Unchanged |
|---|---|---|---|---|---|
| **G1c** contract core | new domain namespace only; **nothing wired** to any existing path | §16.2 domain-state; S1–S5 as compatibility; revision-token; serializer omission | core complete but **no existing code path calls it**; any need to touch a component → stop | delete the new namespace | all 8 workflows; all 44 consumer IDs; the mirrors |
| **G1d** capability resolver — **IMPLEMENTED inert (`933228c2c`)** | new resolver namespace, **inert in production**. Delivered WITHOUT `config/` profiles: the resolver encodes the approved rules in code, and no configuration file was added | 8 workflows × every dimension; deny-by-default incl. unknown context and typo | resolver inert; no live write gated yet | remove config + resolver | every write path |
| **G1e** provenance — **IMPLEMENTED inert (`00b7a025d`)** | a provenance MODEL, not a recorder: kinds, authority, actors, a per-dimension map and transition rules. No recorder was built, because recording requires persistence, which is not started | §16.5 incl. the negative test that polygons carry **no** provider metadata | enrichment call order unchanged (G1b §7.E) | remove recorder | enrichment behaviour |
| **G1f** writer consolidation | `HasSearchAreas` + the 4 non-trait components, **one workflow per commit** | per-workflow parity vs its G1a characterisation | the two correct Tenant Offer workflows' tests **must not change**; line 130's clear-mirroring not regressed; any uncharacterised workflow → stop | revert per-workflow commit | the public projection seam; the mirrors (until D-G1-6 sunset) |
| **G1g** adapter contract | Livewire adapter, form-POST adapter, `NullAdapter` | one shared contract suite; identical envelopes → identical results | JS bridge unproven until **G2** — the suite stops at the PHP boundary and says so | remove adapters | the domain core |

**Known tests that change by design, not by regression** — recorded here so a red run is not mistaken for
breakage:

| Test | Increment | Why |
|---|---|---|
| `test_hire_trait_semantics_are_unchanged` (`TenantOfferCitiesMirrorTest`) | G1f | Asserts the trait still contains `empty(` and no `array_key_exists`. It exists to force re-verification. |
| `test_cleared_cities_outcome_across_all_eight_workflows` | G1f | 6/2 baseline becomes 8/0 under D-G1-4 4-A. |
| the five `test_siteNN_*` guard tests | G1f | Pin the pre-consolidation resurrection. |
| `test_one_save_records_cleared_dimensions_three_different_ways` | G1f | The three-way split is what 4-A removes. |
| `test_unmounted_editor_empty_payload_destroys_all_saved_geometry` (+ sibling) | G1c/G1g | Pin the destructive behaviour 2-A removes. |
| `test_s2_schema_version_is_ignored_*`, `test_s2_unknown_future_schema_version_*` | G1c | Pin the absence of the interpretation mode. |
| 3 hash tests | only if D-G1-3 3-B is chosen | Vertex order and omission≡empty. |

---

## Decision summary

| Decision | Approved option | Owner status |
|---|---|---|
| **D-G1-1** Canonical contract | **1-B** — adopt v1.2 §5 and close the null / empty-string / malformed gaps | **APPROVED** |
| **D-G1-2** Operation vocabulary | **2-A** — exactly `set` + `clear`, carried in a server-built command object | **APPROVED** |
| **D-G1-3** Concurrency & hash | **3-C** — separate revision token from the Bridge cache key; Bridge vertex-order fix **deferred** to a separately authorized increment | **APPROVED** |
| **D-G1-4** Clear behaviour | **4-A** — converge all 8 workflows; §18 withdrawals **confirmed** | **APPROVED** |
| **D-G1-5** Canonical writer | **5-C** — domain service now, trait as a thin deprecated shim, removal an exit criterion | **APPROVED** |
| **D-G1-6** Legacy mirrors | **6-C** — derived-writable now, lazy repair, evidence-gated sunset | **APPROVED** |
| **D-G1-7** Snapshot retention | **7-E** — sunset + existing reader guard; final disposition **still open** | **APPROVED** |

**All seven are APPROVED. No implementation has begun, and approval does not authorize it.**

The three that most change observable behaviour, and therefore warrant the closest attention during
implementation, remain **D-G1-4** (a user's clear will start taking effect on `counties` and `state` in all
eight workflows), **D-G1-2** (an unmounted editor will stop writing at all), and — **only once the deferred
Bridge increment is authorized** — the cache-invalidation wave discussed under D-G1-3.

---

## Owner approval record

**Approval base commit:** `18cd954bcdc43b4796f69689f28bc2df47c45f22`
**Approval date:** 2026-07-30
**Approved by:** owner
**Recorded by:** this increment (documentation only)

### Exact approved options

| Decision | Approved option | Summary of what was approved |
|---|---|---|
| D-G1-1 | **1-B** | Adopt the v1.2 §5 canonical contract and close the identified gaps. Absent = not supplied / preserve in a patch context; `null` is not a valid authored value; empty string normalized per dimension contract and never a stand-in for clear; empty array is the canonical cleared value for collections; present-but-cleared is authoritative; malformed values rejected or quarantined; unknown future keys preserved only via version-aware hydration and never interpreted by an older writer; `schema_version` has defined read/write semantics; labels and geometry may share one private canonical document subject to projection at exposure boundaries. **No schema change in the approval commit.** |
| D-G1-2 | **2-A** | Server-built command object, exactly `set` and `clear`. Unmounted field = no operation; omitted field = no operation; absence is not clear; `preserve` is the absence of a command, not a third operation; legacy migration is an internal provenance/hydration concern; only an explicit `clear` may withdraw a dimension. |
| D-G1-3 | **3-C** | Separate the Location DNA revision token from the Bridge cache key. Polygon **vertex** order is meaningful and reordering **must** change the token; polygon and radius **collection** order are not meaningful absent later evidence; explicit clearing **must** change the token; omission/no-op **must not**; `schema_version` affects the token when it changes interpretation; unknown keys handled via the version-aware document; `location_notes` and administrative labels affect the document token; malformed geometry is not a valid canonical hash input. |
| D-G1-4 | **4-A** | Converge all eight workflows on the canonical clear behaviour; §18 withdrawal semantics **confirmed**. Clearing any of `cities` / `counties` / `state` updates or clears the derived mirror; clearing `polygons` / `radius_searches` removes them; clearing everything yields a valid canonical cleared document; an unmounted editor makes no change; stale mirrors may not resurrect a cleared canonical value; `HasSearchAreas.php:130` clear-mirroring is protected; the two correct Tenant Offer behaviours are the convergence baseline; defective trait and Buyer Offer resurrection is **not** preserved. |
| D-G1-5 | **5-C** | Domain service now; trait retained temporarily as a thin deprecated shim that may delegate only. `LocationDnaPersistenceService` is the sole canonical writer; inline Buyer Offer writers removed during controlled G1f convergence; Tenant Offer routed through the canonical writer without losing correct clear semantics; normalization → normalizer, hydration → hydrator, orchestration → persistence service, legacy compatibility → `LegacyMirrorAdapter` exclusively; public projection stays a separate exposure-boundary concern. |
| D-G1-6 | **6-C** | Derived-writable mirrors during transition, lazy repair, evidence-gated sunset. Canonical document authoritative when valid and present; mirrors are derived outputs; fallback only for legacy-only records; mirrors may not override present-but-cleared values; lazy repair preserves provenance and never converts inherited → authored; **no bulk backfill authorized**; observability required before removal; sunset requires separate approval; rollback = stop canonical-writer adoption while retaining mirror compatibility, **not** restoring resurrection. |
| D-G1-7 | **7-E** | Retain the snapshot temporarily under an explicit sunset and reader guard. |

### Carried condition 1 — D-G1-3 Bridge hash deferral

**`CriteriaHashService` and the Bridge cache key are not changed during G1c contract-core implementation.**
Correction of the Bridge cache key's polygon vertex-order behaviour is **deferred to a separately authorized
compatibility increment**, to be taken only after the domain revision-token contract is implemented and
characterized.

Accepted consequence, recorded rather than glossed: until that increment is authorized, a polygon reshape and
a deliberate geometry clear **continue not to invalidate the Bridge cache**, so an MLS refetch will not be
triggered by either. The 21 tests in `G1bCriteriaHashCharacterisationTest` remain valid and unchanged.

### Carried condition 2 — D-G1-7 reader guard and G1g reassessment

- The existing unprojected snapshot is **private sensitive data**.
- **No new production reader may be added without separate architecture approval**, and any future reader
  must define its authorization and projection requirements first.
- The structural guard **remains required**:
  `test_retention_column_has_no_production_read_sites` in
  `tests/Feature/Spatial/G1bAcceptedBidDocumentCharacterisationTest.php`.
- **Existing snapshot rows are not deleted or rewritten during G1c.**
- The final disposition among 7-A / 7-B / 7-C is **not chosen yet** and **must be reassessed and selected
  before G1g is declared complete**.
- If a production reader becomes necessary sooner: **stop and obtain an explicit D-G1-7 amendment** before
  implementing it.

### One recorded refinement of a governing document

D-G1-3's approved clarification that **`schema_version` must affect the revision token when it changes
interpretation** refines v1.2 §5.6, which states the token is "independent of `schema_version`". The two are
reconciled as: the token is **insensitive** to a lazy upgrade that changes no values, and **sensitive** to a
version change that alters interpretation. Recorded here so the divergence from §5.6's wording is explicit
rather than discovered during implementation.

### Implementation status

Recorded at approval, then updated when the inert core landed.

| Gate | Status |
|---|---|
| G1a characterisation | **COMPLETE** (`cf53249ac`, `958867234`) |
| G1b consumer audit | **COMPLETE** (`7fbe33679`, reconciled `a0df14fb7`) |
| G1b blocking unknowns | **RESOLVED** (`f2ea4355d`) |
| **G1c inert contract core** | **IMPLEMENTED** (`ddbf3b2bb`) |
| **G1d inert capability resolver** | **IMPLEMENTED** (`933228c2c`) |
| **Workflow integration** (G1c/G1d consumers) | **NOT STARTED** |
| **Persistence** (`LocationDnaPersistenceService`) | **NOT STARTED** |
| **G1e inert provenance model** | **IMPLEMENTED** (`00b7a025d`) |
| **Provenance integration / persistence** | **NOT STARTED** |
| **G1f** writer consolidation | **NOT STARTED** |
| **G1g** adapter contract | **NOT STARTED** |

Each increment requires its own separate authorization. The stop conditions in the migration plan above
remain binding, including: the two correct Tenant Offer workflows' tests must not change; line 130's
clear-mirroring must not regress; and no workflow lacking characterisation may be migrated.

The three that most change observable behaviour, and therefore warrant the closest reading, are **D-G1-4**
(a user's clear will start taking effect on `counties` and `state` in all eight workflows), **D-G1-2** (an
unmounted editor will stop writing at all), and **D-G1-3** if 3-B is chosen (a one-time Bridge
cache-invalidation wave).

---

## Implementation record

### G1c — inert contract core

**Implementation commit:** `ddbf3b2bb3db9581f2c8125faf9ac9515fe9f38e`
**Commit subject:** `feat(location-dna): add inert G1c contract core`
**Approval commit:** `b919f9e93d3efcbb6b1cb86ef7220e7a7d5a038b`
**Implementation date:** 2026-07-30
**Namespace:** `App\Services\LocationDna\Contract` → `app/Services/LocationDna/Contract/`

`app/Domain/` does not exist in this repository; the established convention is
`app/Services/<Area>/` with purpose-named sub-namespaces (`App\Services\Stellar\Matching\DTO`,
`App\Services\Dna\Relevance`), and `app/Services/LocationDna/` is the Location DNA home that already
contains `PublicGeometryProjection`. A `Contract` sub-namespace keeps the contract core with the rest of
Location DNA and matches the increment's own name.

### Scope, exactly

| | |
|---|---|
| Production files added | **17** |
| Test files added | **8** |
| Tests added, all passing | **128** |
| Existing files modified | **1** — `tests/Feature/Offers/OfferWorkflowReadinessTest.php` |
| Readiness-guard entries added | **17 exact literal paths**, plus one identifying comment |
| Wildcard / prefix / glob / matching-logic change | **none** — scan roots, matching algorithm, failure message and assertion behaviour all unchanged |
| Existing production files changed | **none** |
| Production references to the new namespace | **zero** |
| Revision-token format | `ldna-r1:<sha256>` |

**Implemented (all inert):** immutable `LocationDnaDocument` · `Dimension` and `DimensionKind` ·
`DimensionOperation` · `DimensionCommand` · `DimensionCommandApplier` · `LocationDnaHydrator` ·
`HydrationOutcome` and `HydrationResult` · `InterpretationMode` · `LocationDnaNormalizer` ·
`LocationDnaSerializer` · `LocationDnaRevisionToken` · explicit contract exceptions and violations
(`ContractViolation`, `LocationDnaContractException`, `MalformedDocumentException`,
`UnsupportedSchemaVersionException`) · focused unit tests · inertness guards · the exact
readiness-allowlist entries.

**Not implemented by this increment, deliberately:** `LocationDnaPersistenceService` · `LegacyMirrorAdapter` ·
`LocationDnaCapabilityResolver` *(since delivered by G1d — see below)* · workflow integration · trait
delegation · inline-writer replacement · mirror repair · provenance integration · database persistence ·
migrations · Bridge cache-key changes · public projection changes · snapshot readers · **G1f** · **G1g**
*(G1e's inert provenance model has since been delivered — see below)*.

The inertness guard asserts the absence of the first three by class name, so their accidental appearance
fails the suite.

### Semantics now realised in code

- **Three dimension presence states** — `absent`, `cleared`, `authored` — decided by `array_key_exists`,
  never `empty()`.
- **Exactly two mutation operations**, `set` and `clear`. **Preserve is represented by no command.**
  `set(null)`, `set('')`, `set([])`, a value-carrying `clear`, and every unsupported operation name are
  rejected at construction.
- **Hydration does not convert malformed input into an empty valid document.** Bad JSON, a decoded scalar,
  a JSON list, a bad `schema_version` and a malformed known dimension each yield a distinct outcome, with
  the raw input quarantined.
- **An unsupported higher `schema_version` remains read-only and is not rewritten.**
- **Polygon vertex order affects the revision token** — reordering vertices changes it.
- **Polygon and radius-search collection ordering does not** affect the token.
- **A canonical clear affects the token**; an absent dimension and a cleared dimension are distinct.
- **An interpretation-neutral lazy upgrade does not** affect the token.
- **Private serialization retains geometry and `location_notes` in full**, and **the serializer performs no
  public projection** — `PublicGeometryProjection` remains the separate, unchanged exposure boundary.
- **No component mutates its input**, asserted for the hydrator, the normalizer, the applier and the token.

### Deferred items — restated, still open

- **`CriteriaHashService` and the Bridge cache-key behaviour remain unchanged.** The service was not
  touched, imported or subclassed by the contract core.
- **The polygon vertex-order correction in the Bridge cache key remains separately deferred** to its own
  authorized compatibility increment, per D-G1-3's carried condition.
- **Deliberate geometry clearing still does not invalidate the Bridge cache**, and neither does a polygon
  reshape. Accepted and recorded, not fixed.
- **`LocationDnaPersistenceService` remains unimplemented**, so nothing yet writes canonical state, applies
  a batch atomically, or compares revision tokens inside a transaction.
- **D-G1-7's snapshot disposition remains due before G1g is declared complete**, and **no new snapshot
  reader is authorized**. The existing structural reader guard remains required.
- **G1f–G1g remain not started.** The G1c, G1d and G1e inert layers are implemented; none is wired.

### What did not change

All seven architecture decisions remain **APPROVED**; none was reopened, altered or returned to
`UNDECIDED` by this implementation. No production file outside the new namespace changed. No test other
than the readiness allowlist changed. No configuration, migration, route, view, model, controller,
Livewire component or trait changed.

### G1d — inert capability resolver

**Implementation commit:** `933228c2c12a4dd2d0dbde1147446c082c46cc6f`
**Commit subject:** `feat(location-dna): add inert G1d capability resolver`
**Implementation date:** 2026-07-30
**Namespace:** `App\Services\LocationDna\Capability` → `app/Services/LocationDna/Capability/`

Sibling to the G1c `Contract` namespace, under the same `app/Services/<Area>/` convention.

| | |
|---|---|
| Production files added | **8** |
| Test files added | **5** |
| Tests added, all passing | **70** |
| Existing files modified | **2** — both authorised (below) |
| External dependencies | exactly two: `Contract\Dimension` and `RuntimeException`, asserted by a dependency-allowlist test |
| Production references to the new namespace | **zero** |

**Implemented (all inert):** `LocationDnaCapability` (9-case closed vocabulary) · `LocationDnaSurface` ·
`LocationDnaViewerRelationship` · `LocationDnaPurpose` · `LocationDnaAccessContext` ·
`LocationDnaCapabilitySet` · `LocationDnaCapabilityResolver` · `LocationDnaCapabilityException`.

**Not implemented, deliberately:** workflow integration · any wiring to real users or application roles ·
`LocationDnaPersistenceService` · `LegacyMirrorAdapter` · provenance · snapshot readers · policies ·
middleware · gates · service providers · capability configuration files · permission tables ·
**G1f** · **G1g** *(G1e's inert provenance model has since been delivered — see below)*.

**Delivered without `config/` profiles.** The migration plan anticipated "new resolver + `config/`
profiles". The resolver encodes the approved rules in code and **no configuration file was added**, so
nothing in `config/` changed. Whether capability declaration should later move to configuration (§7's
"declared in configuration") is left open for the wiring increment.

#### Semantics realised in code

- **Default deny by construction.** Every resolution starts from a fully denied set; an unknown surface,
  viewer or purpose, or any incomplete context, resolves to nothing granted. Unrecognised names parse to
  `Unknown` rather than throwing, so they flow into the deny path.
- **No implication between capabilities.** Reading does not imply editing; administrative labels do not
  imply geometry; geometry does not imply `location_notes`; consulting a mirror does not imply repairing it.
- **Authentication alone grants nothing.** An authenticated non-owner resolves to a byte-identical grant
  list to an anonymous viewer — D4 expressed as code.
- **Public and authenticated non-owner surfaces** receive administrative labels plus a
  `RequirePublicProjection` obligation, and are denied canonical read, geometry, notes, snapshot and
  mutation.
- **Owner-private edit** receives canonical read, geometry, notes and edit, with eight explicitly
  enumerated settable/clearable dimensions and **no** projection obligation. **Owner-private preview** is
  the same reads with no `EditDocument` and zero mutable dimensions.
- **Counterparty accepted-bid** is administrative-labels-only, matching the behaviour G1b proved.
- **Internal matching, Ask AI and Bridge** receive a purpose-specific canonical read and no outward
  exposure capability whatsoever.
- **Dimension mutation requires both** `EditDocument` **and** an explicit per-dimension grant. There is no
  wildcard; `subject_property` is read-only because §17 G8 owns it; unknown dimensions are unnameable
  because grants use the G1c `Dimension` enum rather than strings.
- **Snapshot access is denied in every context**, proven exhaustively.
- **The resolver cannot see a document**, asserted by reflection — so the presence of geometry can never
  influence a grant.

#### The two authorised existing-file changes

| File | Change |
|---|---|
| `tests/Feature/Offers/OfferWorkflowReadinessTest.php` | **+9/−0** — the 8 exact production paths plus the comment `G1d inert Location DNA capability resolver`. No wildcard, no matching-logic change. |
| `tests/Unit/Services/LocationDna/Contract/G1cContractCoreInertnessGuardTest.php` | **+53/−9** — narrow, separately authorised refinement. Its first assertion moved from "nothing outside `Contract/` references the contract" to "nothing outside the **approved Location DNA domain namespaces** references it", via an explicit two-entry `DOMAIN_DIRS` list (`Contract/`, `Capability/`). |

**Why that refinement was a correction, not a weakening.** The original assertion encoded a stronger claim
than the architecture requires. G1d's approved design mandates reuse of the G1c `Dimension` vocabulary
rather than duplicating dimension names as unvalidated strings, so the capability layer necessarily depends
on the contract — and a sibling *domain* layer consuming the contract is not production wiring.
`DOMAIN_DIRS` is deliberately **not** a wildcard under `app/Services/LocationDna/`: provenance, persistence
and a legacy mirror adapter are not pre-exempted, and each must be added under its own authorisation. The
guard's other four assertions, including the eight-workflow check, were untouched, and everything else still
fails it — controllers, Livewire components, models, routes, Blade views, traits and existing services.

**One further correction, inside the new increment only.** A docblock in `LocationDnaCapability` originally
named the retained-snapshot database column literally, which tripped the G1b column-reference guard — a
guard that intentionally performs no comment stripping. The docblock was reworded to reference the snapshot
indirectly; **no G1b assertion was changed**, and the capability case, its backing value and every denial
behaviour are identical.

#### What did not change

All seven decisions remain **APPROVED**. No existing production file changed. No configuration, migration,
route, view, model, controller, Livewire component or trait changed. `PublicGeometryProjection` and
`CriteriaHashService` were neither referenced nor modified, so Bridge cache-key behaviour is unchanged and
the D-G1-3 deferral stands. No persistence service, mirror adapter or snapshot reader exists.

### G1e — inert provenance model

**Implementation commit:** `00b7a025d15f3331b3265f358968d6476578f604`
**Commit subject:** `feat(location-dna): add inert G1e provenance model`
**Implementation date:** 2026-07-30
**Namespace:** `App\Services\LocationDna\Provenance` → `app/Services/LocationDna/Provenance/`

Third sibling domain namespace, alongside `Contract` (G1c) and `Capability` (G1d).

| | |
|---|---|
| Production files added | **7** |
| Test suites added | **5** |
| Tests added, all passing | **88** |
| Existing files modified | **2** — both authorised (below) |
| Imports permitted and observed | exactly two: `App\Services\LocationDna\Contract\Dimension` and native PHP types/exceptions (`RuntimeException`) |
| Production references to the new namespace | **zero** |

**The seven production types:** `LocationDnaProvenanceKind` · `ProvenanceAuthority` · `ProvenanceActor` ·
`DimensionProvenance` · `LocationDnaProvenanceMap` · `ProvenanceTransition` ·
`LocationDnaProvenanceException`.

**No separate provenance resolver or classifier was added**, deliberately: authority is *derived* from the
provenance kind rather than stored beside it, transition legality is owned by `ProvenanceTransition`, and a
resolver would therefore carry no distinct responsibility — only an extra indirection to keep in sync.

#### Vocabularies

**Provenance kinds (9):** `owner_authored` · `owner_cleared` · `legacy_fallback` · `legacy_repaired` ·
`inherited` · `derived` · `imported` · `snapshot_retained` · `unknown`

**Authority classifications (4):** `authoritative` · `non_authoritative` ·
`conditionally_authoritative` · `forbidden_as_restoration_source`

**Actors (3):** `explicit_owner` · `automatic_system` · `migration_repair`

**There is no force actor and no bypass actor.** A test asserts the actor vocabulary is exactly those three
and that no case name contains `force`, `bypass`, `override` or `admin` — an escape hatch would become the
path every future caller took.

#### Per-kind semantics

| Kind | Authority | Semantics |
|---|---|---|
| **OwnerAuthored** | authoritative | positive explicit owner intent; protected from automatic fallback, inherited or derived overwrite |
| **OwnerCleared** | authoritative | explicitly distinct from absence; the **only** kind that blocks fallback resurrection |
| **LegacyFallback** | non-authoritative | read-through fallback; not canonical authorship, and not canonical storage |
| **LegacyRepaired** | non-authoritative | may exist in canonical storage; remains distinguishable from owner-authored data |
| **Inherited** | non-authoritative | distinct from derived; must be explicitly re-authored to become owner-authored |
| **Derived** | non-authoritative | computed; grants no exposure or edit capability |
| **Imported** | conditionally authoritative | may have standing over absence (§8.2); does not override owner-authored or owner-cleared state |
| **SnapshotRetained** | forbidden as restoration source | representable as an origin only; grants no snapshot-access capability |
| **Unknown** | forbidden as restoration source | default-safe; never promoted automatically |

#### Transition semantics

The rules are **inert, total and deterministic**, evaluated across all **9 kinds × 9 target kinds × 3
actors** — a test asserts every triple yields a stable boolean. **No transition writes to storage**; the
model decides, it never performs.

- An **explicit owner action** may author or clear, from any origin — and may establish *only* those two
  owner-stated kinds.
- **Migration repair** may convert eligible `LegacyFallback` to `LegacyRepaired`, and nothing else.
- **Automatic fallback cannot restore owner-cleared state.**
- **Derived cannot automatically overwrite owner-authored state.**
- **Inherited cannot automatically overwrite owner-authored state**, nor can an import.
- **Snapshot restoration is denied** for every non-owner actor.
- **Unknown provenance is never automatically promoted.**
- **No force or bypass transition exists.**

#### Dimension and three-state behaviour

- Provenance is tracked **per G1c `Dimension`**, reusing that enum — arbitrary string dimensions are
  rejected, so an unknown dimension is unnameable rather than merely validated.
- Each dimension carries **independent** provenance; changing one leaves the others untouched.
- **Absent means no provenance entry at all** — absence is not an origin. The map is deliberately sparse,
  which is how it stays compatible with the G1c three states without becoming the `dimension_meta`
  structure settled decision 6 forbids.
- **`OwnerCleared` is distinct from absent**: cleared has an entry, absent has none.
- **`OwnerAuthored` is distinct from `LegacyRepaired`**: both may sit in canonical storage; only the first
  is authoritative.
- **Geometry provenance is independent from administrative-label provenance.**
- **`location_notes` provenance can be represented but grants no exposure.**

#### Separation boundaries

G1e does **not**: grant any G1d capability · invoke `LocationDnaCapabilityResolver` · alter
`LocationDnaRevisionToken` · alter `CriteriaHashService` · alter `PublicGeometryProjection` · alter G1c
document serialization · wire any workflow · read or write persistence · repair mirrors · read retained
snapshots · create a persistence service, mirror adapter or workflow adapter · add a provenance table or
column · add an observer, listener, middleware, policy, gate, provider or config file.

Asserted, not merely claimed: a test proves no provenance type exposes a capability-style method
(`mayExpose`, `mayEdit`, `mayRead`, `grant`, `permit`, `authorize`…), that provenance code never invokes the
capability layer, and that two entirely different provenance maps over the same document produce an
identical revision token.

#### The two authorised existing-file changes

| File | Change |
|---|---|
| `tests/Unit/Services/LocationDna/Contract/G1cContractCoreInertnessGuardTest.php` | **+1/−0** — one exact `Provenance` directory added to `DOMAIN_DIRS`. No wildcard, no matching-logic change, no scan-root change, no assertion weakening, no pre-authorisation of any future namespace. |
| `tests/Feature/Offers/OfferWorkflowReadinessTest.php` | **+8/−0** — the seven exact production paths plus one `G1e inert Location DNA provenance model` comment. No wildcard, no matching-logic change. |

#### Validation

**G1e 88 passing · G1d 70 passing · G1c 128 passing · readiness guard 10 passing.** All required G1a, G1b,
G0.1 and SearchAreas regressions passed. `php -l` clean on all 14 changed/new PHP files; `git diff --check`
clean.

**Known pre-existing failures remain unchanged and are NOT G1e failures:**
`SearchAreasStateCountyRoundTripTest` — 1 of 4, SQLite `ILIKE` behaviour;
`LazyBridgeImportServiceTest` — 2 of 22, PostgreSQL advisory-lock behaviour. Both reproduce identically in
the G0.1 control worktree with no G1 file present.

#### What did not change

All seven decisions remain **APPROVED**; none returned to `UNDECIDED`. The provenance architecture is
implemented **only as an inert domain model** — workflow integration and persistence remain **NOT STARTED**,
as do **G1f** and **G1g**. The **D-G1-7 snapshot disposition remains unresolved**, and **no snapshot reader
is authorised**.
