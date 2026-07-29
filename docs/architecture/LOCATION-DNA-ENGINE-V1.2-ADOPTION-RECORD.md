# Location DNA Engine v1.2 — Adoption Record

**Document type:** architecture governance record
**Status:** **ADOPTED**
**Adopted:** 2026-07-29
**Adopted by:** repository owner
**Governing architecture:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md)
**Baseline at adoption:** `387a971d8` · branch `phase-2-spatial/ui-repair-maplibre-basemap`

---

## 1. Governing architecture

`docs/architecture/LOCATION-DNA-ENGINE-V1.2.md` is **the governing Location DNA Engine
architecture**. All future Location DNA design, specification, planning and implementation work is
governed by it.

Where any other document, plan, ticket, comment or prior decision conflicts with v1.2, **v1.2
prevails**.

## 2. Superseded but retained

| Document | Status | Disposition |
|---|---|---|
| `LOCATION-DNA-ENGINE-V1.md` (v1.0) | **superseded** | **Retained as a historical record. Must not be deleted or modified.** |
| `LOCATION-DNA-ENGINE-V1.1.md` (v1.1) | **superseded** | **Retained as a historical record. Must not be deleted or modified.** |
| `LOCATION-DNA-ENGINE-V1.2.md` (v1.2) | **governing** | Amendable only per §7. |

v1.0 and v1.1 remain readable for provenance and audit. They are **not** to be cited as authority for
any future decision. Their withdrawn claims are listed in v1.2 §21.2.

## 3. Why v1.2 was required

v1.1 was not adoptable as a governing document for four reasons:

1. **Three sections had been lost between v1.0 and v1.1** — the testing strategy, the AI boundary and
   the public contract surface. A governing architecture with no testing strategy cannot enforce its own
   invariants.

2. **The contract was welded to one UI framework.** v1.0 defined the patch protocol as a Livewire
   mechanism. `location_dna_preferences` is read or written by **42 files**, including the matching
   engine, five criteria loaders, two accepted-bid-summary services and the Bridge OData filter
   builders which send geometry to an external MLS API. A contract with that many consumers, one of
   them outbound to a third party, cannot be defined as a Livewire binding.

3. **The architecture described only create and edit forms.** Clone, import, archive, restore,
   expiration, legacy loading, normalisation and administrative correction were undefined — and every
   one of them has to answer the absent-versus-empty question that the whole state model rests on.

4. **Six named internal contradictions (C1–C6)** remained unresolved, including a byte-compatibility
   claim that was incompatible with the omission semantics stated in the same document, and a
   privacy invariant with no algorithm, no enforcement point and no test.

**In addition, drafting v1.2 surfaced a live privacy exposure (F-P1) that no prior version
identified.** See §10.

## 4. Settled architectural decisions

The following are settled. They are applied, not re-argued. Reversing any one requires an approved
amendment under §7.

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

**Additionally settled by v1.2:**

21. **Compatibility is semantic and schema-level, not byte-level** (v1.2 §5.3).
22. **Transport-neutral intent envelope; Livewire is one adapter** (v1.2 §6).
23. **Two patch operations only — `set` and `clear`** (v1.2 §6.2).
24. **Per-dimension optimistic concurrency via a deterministic revision token** (v1.2 §6.4).
25. **`schema_version` controls interpretation mode and nothing else; unknown versions refuse to
    write** (v1.2 §5.5).
26. **Provenance records which provider produced a value; forbidden on user-authored polygons**
    (v1.2 §10).
27. **Projections are asynchronous and eventually consistent under invariants P1–P4** (v1.2 §15).
28. **The fifteen governing principles L1–L15** (v1.2 §1).

The governing principles are the highest authority in the document. Where a section of v1.2 conflicts
with a principle, the principle wins and the section is a defect to be corrected by amendment.

## 5. Explicitly deferred decisions

Deferred items, each with its owner or gate. Deferral is a decision: these must not be quietly pulled
forward, and must not be treated as unresolved oversights.

| Deferred item | Owner / gate |
|---|---|
| Non-Google geocoder and autocomplete (**D1** — the critical path) | owner; gate G6 |
| Historical Google-derived coordinates: re-geocode or suppress (**D2**) | owner; gate G8 |
| T3 generalisation parameters — grid size, minimum span (**D3**) | owner; before any T3 geometry rendering |
| `commute` contract, UI and routing provider | **withdrawn from v1**; gate G10 (R10) |
| `neighborhoods` as a written dimension | **withdrawn from v1**; reinstated only when a consumer exists |
| Optimised spatial substrate (PostGIS, indexing, partitioning) | gate G10; invariants P1–P4 bind now |
| Overture licence verification (R6) | owner; blocks gate G5 |
| Browser automation tooling (R1) | owner; gate G2 |
| City and ZIP boundary import (R3) | owner |
| Livewire 3 upgrade (R7) | owner |
| Custom domain for PMTiles (R8) | owner |
| PMTiles refresh pipeline (R9) | owner |
| Retention policy for drawn geometry (R12) | owner |
| Clone, archive, restore, administrative-correction paths (R14) | rules specified in v1.2 §8; unenforced until built |
| Demographics, target-market planning, audience targeting, AI consumers, neighbourhood similarity, transit | gate G10 |
| International scope | requires a later architecture decision; not implied, not designed for |
| Crime and safety scoring | **documented refusal (L15)** — not a deferral. Counsel reviews first if ever revisited. |

## 6. Named implementation gates

Each gate carries scope, prerequisites, owner decisions, tests, rollback and a stop condition in
v1.2 §17. Gate order is a dependency statement, not a schedule.

| Gate | Name | Status |
|---|---|---|
| **G0** | Interim geometry-preservation guard | ✅ **complete** (`387a971d8`) — verified structurally, **not** behaviourally |
| **G0.1** | **Public geometry containment (F-P1)** | **the only currently authorised gate** — see §8 |
| **G1** | Domain core and mirror consolidation | **not authorised** — see §9 |
| **G2** | Behavioural verification capability | not authorised |
| **G3** | Read-only renderer pilot (authenticated surfaces) | not authorised |
| **G4** | Patch transport (bridge 3 → 1) · removes the G0 limitation | not authorised |
| **G5** | Provider-registry activation | not authorised |
| **G6** | Geocoder replacement (**D1**) | not authorised |
| **G7** | Editable renderer migration | not authorised |
| **G8** | Subject-property profile | not authorised |
| **G9** | Legacy retirement | not authorised |
| **G10+** | Storage projection and contract consumers | not authorised |

**Correction carried from v1.1, recorded here because it changes gate expectations:** G0's interim
limitation — geometry cannot be intentionally cleared while the editor cannot hydrate — is **not**
removed by G1. The guard is client-side; G1 touches no client code. The limitation is removed at
**G4**. G1 is also **not** "purely additive": mirror consolidation changes live server-side code paths
in four Offer components and a shared trait, and its risk must be assessed on that basis.

## 7. Amendment rule

> **Future Location DNA plans, specifications, designs, tickets and implementations must not
> contradict `LOCATION-DNA-ENGINE-V1.2.md` without an approved architecture amendment.**

Binding consequences:

1. **No silent divergence.** Any work that would contradict v1.2 stops and requests an amendment
   before proceeding. Discovering a conflict mid-implementation is a stop condition.
2. **Amendments are explicit and owner-approved.** An amendment states which section and which
   settled decision it changes, why, and what evidence supports it.
3. **Amendments are recorded.** Approved amendments are appended to this Adoption Record, and v1.2 is
   revised as v1.3 (or later). v1.2 is then retained unchanged as a historical record, exactly as v1.0
   and v1.1 are now.
4. **v1.2 is not edited in place** except to correct a factual error that changes no decision; such
   corrections are noted here.
5. **The governing principles L1–L15 are the highest authority.** An amendment that weakens a
   principle must say so in those terms.
6. **Evidence discipline carries forward.** Amendments use the v1.2 evidence labels. An unlabelled
   claim is not adoptable.

## 8. Current implementation authorisation

> **Implementation authorisation is limited to G0.1 — Public Geometry Containment.**

Nothing else is authorised. Specifically **not** authorised at this time:

- G1 domain core or mirror consolidation
- any renderer migration or MapLibre work
- provider-registry activation or any provider change
- geocoder replacement
- any change to canonical state semantics, persistence or matching behaviour
- any merge or push

G0.1 is a **safety correction only**. Its bounded scope, containment boundary, file plan, test matrix
and stop conditions are specified in
[`LOCATION-DNA-ENGINE-V1.2-G0.1-PLAN.md`](./LOCATION-DNA-ENGINE-V1.2-G0.1-PLAN.md). G0.1 requires its
own explicit implementation authorisation before any code is written; adoption of v1.2 is not that
authorisation.

## 9. G1 is not yet authorised

G1 — domain core and mirror consolidation — is **not authorised**. It must not begin.

Recorded reasons:

1. **F-P1 takes precedence.** It is a live exposure and does not depend on the domain core.
2. **G1's prerequisite is unmet.** v1.2 §17 requires characterisation coverage to exist for each
   workflow the consolidation touches *before* it is touched.
3. **G1 has an unapproved dependency on judgement calls the owner has not yet made** — the contract
   (v1.2 §5), the operation vocabulary (§6.2), the concurrency mechanism (§6.4), and the withdrawal of
   `neighborhoods` and `commute` (§18).
4. **G1 is not risk-free.** It changes live server-side code paths in four Offer components and a
   shared trait.

When G1 is authorised, its stop condition stands: report with diffs and tests, **no page behaviour
changed**, before anything else begins.

## 10. F-P1 is a current privacy exposure

> **F-P1 is a present-state privacy exposure in production behaviour. It is not future architecture
> work, and it is not a design question.**

Recorded facts, all **[MEASURED]** at `387a971d8`:

- Four unauthenticated routes serialise **exact user-drawn polygon vertices** and **exact radius-search
  centre coordinates** into page HTML/JavaScript, via
  `resources/views/components/location-dna-map.blade.php` lines **335** (`var polygons = @json($polygons);`)
  and **336** (`var radii = @json($radii);`).
- Those routes carry middleware `["web"]` only. Two of them —
  `criteria/view/{id}` and `tenant/criteria/auction/view/{id}` — perform **no authorisation or approval
  check of any kind** in their controllers.
- The geometry is recoverable from **page source alone**, without interacting with the map and
  without a working Google credential.

This contradicts governing principle **L14** (user-drawn geometry is sensitive) and contradicts v1.1
§12's own stated hard requirement that public viewers render a generalised envelope.

**It is independent of all renderer work.** It exists on the current Google implementation and would be
faithfully reproduced by a MapLibre port. It must not be sequenced behind the architecture programme.

**Correction to a prior claim, recorded because it shaped earlier sequencing:** v1.0 §10 described the
read-only viewer as *"structurally zero risk"*. That is true of **data loss** and **false of privacy**.
The read-only viewer is the highest-privacy-risk surface in the system.

---

## Amendment log

| Date | Amendment | Sections affected | Approved by |
|---|---|---|---|
| — | *(none)* | — | — |
