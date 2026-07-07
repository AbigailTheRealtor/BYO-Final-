# Bid Your Offer — Documentation Index & Authority Map

**Purpose:** This is the **start-here map** for the `docs/` tree. It answers two questions: *where does a given topic live?* and *which document wins when two seem to overlap?* It is an **index only** — it points at documents, it never re-specifies their content.

> **How to read this file.** Higher tiers govern lower tiers. When any subsystem document appears to conflict with the Master Development Roadmap, the roadmap is authoritative unless the roadmap itself defers to that document. This index is maintained by hand; when you add or supersede a document, update the relevant row here.

---

## Authority order (highest → lowest)

1. **Architecture Reference** — frozen architectural invariants the whole platform must obey.
2. **Master Development Roadmap** — the single implementation blueprint: *what to build, in what order, and why.*
3. **Intelligence Catalog & Design References** — definitions of the intelligence systems (what each thing *means*), not build sequencing.
4. **Subsystem owner documents** — the authoritative doc for one subsystem (Location Intelligence, Matching, Ask AI, MLS import, …).
5. **Supporting technical references** — field audits, provider maps, comparisons that phases consume.
6. **Historical / superseded** — kept for provenance; never authoritative.

---

## Tier 1 — The single implementation blueprint

| Document | Path | Role |
|---|---|---|
| **Master Development Roadmap v2.4 (FROZEN)** | `docs/bid-your-offer-v1-master-roadmap.md` | The one document an implementer executes. Phases 0–16, ordered and gated. *(Filename still says "v1" — this refers to the product, not the doc version, which is 2.4. A future rename is planned.)* |

## Tier 0 — Architecture reference

| Document | Path | Role |
|---|---|---|
| **Master Architecture Reference v1.0 (Frozen)** | *not yet in repo* | Frozen architectural invariants. Currently a published artifact; to be persisted under `docs/architecture/` when supplied. |

## Tier 2 — Intelligence catalog & design references

| Document | Path | Role |
|---|---|---|
| **Beyond-MLS Property DNA — Intelligence Catalog & Design Reference** | `docs/beyond-mls-property-dna-roadmap.md` | Authoritative for *definitions* of the DNA/intelligence systems (§0–§10) and the §F design foundation. **Not** a build sequence — sequencing is owned by the Master Development Roadmap. |

## Tier 3 — Subsystem owner documents

Each subsystem below maps to a phase of the blueprint. The named doc is the **owner** for that subsystem; other docs in the cluster are subordinate.

| Subsystem | Blueprint phase | Owner document |
|---|---|---|
| MLS / Direct Import | Phases 0–2 | `docs/mls-direct-import-design-and-plan.md` |
| Beyond-MLS Wave 1 (implementation onto real code) | Phases 3–9 (foundation) | `docs/beyond-mls-wave1-implementation-architecture.md` |
| Location Intelligence | Phase 8 | `docs/canonical-field-mapping-spec.md` (contract) + `docs/location-provider-capability-map-proposal.md`, `docs/PHASE_8_PROVIDER_AGNOSTIC_LOCATION_INTELLIGENCE_RECOMMENDATION.md` |
| Ask AI | Phase 12 | `resources/docs/ASK_AI_ROADMAP_AND_GUARDRAILS.md` (plus the `docs/ask-ai/` and `docs/ask-ai-*` audit clusters) |
| Matching (Match Check / Matching V2) | Phase 13 | `docs/matching-v2-validation-runbook.md` + `docs/match-check-phase4-*` / `docs/matching-v2-*` scope docs |
| Transaction Engine · Marketplace · Operations | *out of scope of the blueprint* | *not yet documented — future separate roadmaps* |

## Tier 4 — Supporting technical references

| Topic | Path |
|---|---|
| Definitive field comparison (BYO ↔ Stellar) | `docs/bid-your-offer-stellar-master-field-comparison.md` |
| Current-state field inventory | `docs/bid-your-offer-field-audit.md` |
| Location provider capability map | `docs/location-provider-capability-map-proposal.md` |

## Tier 5 — Historical / superseded (not authoritative)

| Document | Superseded by |
|---|---|
| `docs/bid-your-offer-v2.1-architecture-review.md` | Consumed into the Master Development Roadmap v2.4 |
| `docs/bid-your-offer-stellar-gap-analysis.md` | `docs/bid-your-offer-stellar-master-field-comparison.md` |

---

## Conventions

- **Status banner.** Each authoritative document should carry a one-line banner at the top stating its tier and (for non-blueprint docs) that implementation sequencing is owned by the Master Development Roadmap.
- **Supersession.** When a document is replaced, add a "Superseded by → `<path>`" line at its top and move its row to Tier 5 here. Physical relocation into a `_superseded/` folder is optional and deferred.
- **Where new docs go.** A new document must declare its tier and owner in this index at creation time. Do not create speculative documents (for phases not yet in build); expand the owning document instead until real content exists.
- **HTML mirrors.** The `public/byo-*.html` files are rendered snapshots of their `.md` sources and can lag behind them. The `.md` file is always the source of truth.

---

*This index is documentation-only. Creating or updating it changes no code, migrations, config, or feature flags.*
