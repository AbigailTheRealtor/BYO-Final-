# G1c — Architecture Decision Package

**Gate:** G1c (per §12 of the [G1 Pre-Implementation Report](./LOCATION-DNA-ENGINE-V1.2-G1-PRE-IMPLEMENTATION-REPORT.md))
**Type:** design and decision package for owner approval
**Status:** **DOCUMENTATION ONLY. NO IMPLEMENTATION. ALL DECISIONS UNDECIDED.**
**Governed by:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md) · lineage
[v1.0](./LOCATION-DNA-ENGINE-V1.md) → [v1.1](./LOCATION-DNA-ENGINE-V1.1.md) → v1.2, adopted per the
[Adoption Record](./LOCATION-DNA-ENGINE-V1.2-ADOPTION-RECORD.md)
**Prepared at:** `a0df14fb7d2b63c506886afbf59a822ad9a2ecd6`

> **Nothing in this document is decided.** Every recommendation is a proposal awaiting owner approval.
> No production code, test or configuration is changed by this increment, and G1c implementation has not
> begun.

---

## 0. How to read this package

Four distinct kinds of statement appear throughout, always labelled:

| Label | Meaning | Force |
|---|---|---|
| **OBSERVED** | Current runtime or static behaviour, with a test or line citation | Fact. Not a preference. |
| **REQUIREMENT** | Already binding under an approved governing document (v1.2 §3 locked decisions, §5–§18, or the L-principles) | Uses **must**. Not open for decision here. |
| **RECOMMENDATION** | This package's proposal | Uses *should* / *proposed*. Never **must**. |
| **OWNER DECISION** | Yours. Marked `UNDECIDED` until you say otherwise. | — |

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
| Owner status | **UNDECIDED** |
| Approval | ☐ 1-A ☐ **1-B** ☐ 1-C ☐ Other (specify) |

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
| Owner status | **UNDECIDED** |
| Approval | ☐ **2-A** ☐ 2-B ☐ 2-C ☐ Other (specify) |

---

## D-G1-3 — Concurrency and hash semantics

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
| Owner status | **UNDECIDED** |
| Approval | ☐ 3-A ☐ 3-B ☐ **3-C** ☐ 3-C now + 3-B later ☐ Other (specify) |

---

## D-G1-4 — Withdrawals and clear behaviour

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
| Owner status | **UNDECIDED** |
| Approval | ☐ **4-A** ☐ 4-B ☐ Other (specify) · §18 withdrawals: ☐ confirmed ☐ revisit |

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
| Owner status | **UNDECIDED** |
| Approval | ☐ 5-A ☐ 5-B ☐ **5-C** ☐ Other (specify) |

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
| Owner status | **UNDECIDED** |
| Approval | ☐ 6-A ☐ 6-B ☐ **6-C** ☐ Other (specify) |

---

## D-G1-7 — Snapshot retention and future readers

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
| Owner status | **UNDECIDED** |
| Approval | ☐ 7-A ☐ 7-B ☐ 7-C ☐ 7-D ☐ **7-E** ☐ Other (specify) |

---

## Proposed domain design

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
| `LocationDnaCapabilityResolver` | *(G1d, not G1c)* deny-by-default resolution over an open context map | — |

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
| **G1d** capability resolver | new resolver + `config/` profiles; **inert in production** | 8 workflows × every dimension; deny-by-default incl. unknown context and typo | resolver inert; no live write gated yet | remove config + resolver | every write path |
| **G1e** provenance | provenance recorder within the core | §16.5 incl. the negative test that polygons carry **no** provider metadata | enrichment call order unchanged (G1b §7.E) | remove recorder | enrichment behaviour |
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

| Decision | Recommended option | Owner status |
|---|---|---|
| **D-G1-1** Canonical contract | **1-B** — adopt v1.2 §5 and close the null / empty-string / malformed gaps | **UNDECIDED** |
| **D-G1-2** Operation vocabulary | **2-A** — exactly `set` + `clear`, carried in a server-built command object | **UNDECIDED** |
| **D-G1-3** Concurrency & hash | **3-C** — separate revision token from the Bridge cache key; 3-B vertex fix as follow-up | **UNDECIDED** |
| **D-G1-4** Clear behaviour | **4-A** — converge all 8 workflows; §18 withdrawals confirmed | **UNDECIDED** |
| **D-G1-5** Canonical writer | **5-C** — domain service now, trait as a thin deprecated shim, removal an exit criterion | **UNDECIDED** |
| **D-G1-6** Legacy mirrors | **6-C** — derived-writable now, lazy repair, evidence-gated sunset | **UNDECIDED** |
| **D-G1-7** Snapshot retention | **7-E** — sunset + existing reader guard, resolving to 7-B or 7-C | **UNDECIDED** |

**All seven remain UNDECIDED. No approval is assumed. No implementation has begun.**

The three that most change observable behaviour, and therefore warrant the closest reading, are **D-G1-4**
(a user's clear will start taking effect on `counties` and `state` in all eight workflows), **D-G1-2** (an
unmounted editor will stop writing at all), and **D-G1-3** if 3-B is chosen (a one-time Bridge
cache-invalidation wave).
