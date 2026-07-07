# Bid Your Offer — Master Development Roadmap

> **📍 Authority (Tier 1).** This is the **single authoritative implementation blueprint** for the Bid Your Offer Real Estate Intelligence Platform — the one document that defines *what to build, in what order, and why*. All intelligence-catalog, subsystem, and technical-reference documents are subordinate to it. See [`docs/README.md`](README.md) for the full documentation hierarchy. *(Banner added 2026-07-07 as documentation-hygiene only; no roadmap content changed.)*

**Version:** 2.4 — **FROZEN implementation blueprint.**
**Supersedes:** Version 2.3, Version 2.2, Version 2.1 (architecture review, `docs/bid-your-offer-v2.1-architecture-review.md`), and Version 2.0, which superseded Version 1.0.
**Type:** Planning / documentation only. **No code, Blade, Livewire, migrations, or config were changed. Nothing committed.**
**Date:** 2026-07-02 (V1.0) · Revised 2026-07-02 (V2.0) · Revised 2026-07-02 (V2.2 — integrates approved V2.1 review) · Revised 2026-07-02 (V2.3 — final architectural principles) · Revised 2026-07-02 (V2.4 — final pre-freeze integration; roadmap frozen)
**Owner context:** Real Estate Matchmaker Club / Bid Your Offer (BYO).
**Mission:** *Bid Your Offer is a Real Estate Intelligence Platform that transforms structured property, location, and user data into trusted, explainable intelligence to improve real estate decisions, matching, marketing, and transactions.*
**Purpose:** This is the single authoritative, **permanent architectural blueprint** for building out Bid Your Offer as **one unified Real Estate Intelligence Platform** — from the current audited state through launch and beyond. Every supported data source (BYO, Stellar MLS, future MLS providers, RentCast, ATTOM, public records, CSV imports, future APIs) flows through **one Canonical Data Model**, and every field, entity, and intelligence layer ultimately feeds **one Universal Real Estate Knowledge Graph** — so the platform can help buyers, tenants, sellers, landlords, agents, investors, and businesses make **better, explainable real estate decisions**. It tells a future Claude session **exactly what to build, in what order, and why**, so the project direction never has to be rediscovered.

> **How to use this document.** Work the phases in order. Each phase declares its Goal, Source documents, Tasks, Deliverable(s), Success criteria, Dependencies, a Verification checklist, and explicit Do-not-do warnings. Do not start a phase until its Dependencies are satisfied. **Phase clusters:** Phases 0–2 add data; Phase 3 canonicalizes it; **Phases 3.5–3.9 build the source-neutral foundation** (Field Mapping → Entity Dictionary → Master Intelligence Taxonomy → AI Automation → Knowledge Graph); Phases 4–9.5 turn canonical data into intelligence (Metadata → the full DNA suite → Target Audiences → Story Engine); Phases 10–14 apply that intelligence (Recommendations, Marketing, Ask AI, Matching, Analytics); **Phases 14.5–14.7 add governance and the automated import pipeline**; Phase 15 is the release gate; **Phase 16 is a post-launch Future Expansion (Decision Support Intelligence)**. The **Architecture & Scalability Requirements** (front matter), the **Scope Boundary** note, the **Permanent Development Principles (1–23)**, the **Permanent UI/UX Consistency Standards**, **Appendix M (Testing Strategy)**, and **Appendix N (Universal Intelligence Coverage Matrix)** govern every phase.

---

## Change Log — Version 2.3 → Version 2.4 (final pre-freeze integration)

Version 2.4 **preserves all Version 2.3 content unchanged** and integrates the final pre-freeze additions **where they belong**, without redesign, without removing phases, and **without duplicating concepts already present**. Several requested items were **already in the roadmap** and were only reinforced (not re-added):

- **Already present (reinforced, not duplicated):** Universal Listing ↔ Criteria Matching (Phase 13 subsection + Principle 20); Universal Intelligence Coverage Matrix (Appendix N); Platform Learning Loop (Principle 17); "compute don't ask" for metadata (Principle 2); canonical-only consumption (Principles 11–12); the purpose statement (Principle 19).
- **Net-new in V2.4:**
  1. **Location Intelligence Engine** elevated to a **first-class intelligence engine** whose *output* is Location DNA + Location Preference DNA — clarified in the platform framing and **Phase 8** (consume/generate lists added). *(Reframing of existing Phase 8; no new phase.)*
  2. **Appendix N strengthened** with an explicit **bidirectional structure**: **N.3 (A) field → systems** (no dead data) and **N.3 (B) system → required/optional/derived inputs + confidence + missing** (no starved engine).
  3. **Principle 21 — Compute Whenever Possible** (generalizes Principle 2 to canonical fields, metadata, public datasets, Location Intelligence, existing user selections, and AI inference).
  4. **Principle 22 — Location Story Principle** (location stories from objective/verifiable data; never infer protected characteristics).
  5. **Principle 23 — Canonical Platform Principle** (named consolidation of Principles 11/12/20: the Canonical Data Model is the single source of truth; all downstream consumes canonical, source-agnostic).
  6. **Principle 16 outcome list expanded** to the full set (adds better data quality · metadata · DNA · stories · analytics · explainability).

Nothing renumbered among existing phases or principles 1–20; new principles are 21–23.

---

## Change Log — Version 2.2 → Version 2.3 (final architectural principles)

Version 2.3 **preserves all Version 2.2 content unchanged** and adds five architectural additions (principles + one future-expansion phase + one appendix + one scope note). It does **not** restructure the roadmap or modify existing phases except as noted:

1. **Platform Learning Loop** → new **Permanent Development Principle 17** — every meaningful interaction preserves learning signals that can improve future intelligence, even before adaptive learning ships.
2. **Decision Support Intelligence** → new **Phase 16 (Future Expansion, post-launch)** — the long-term objective: help consumers and agents make better decisions via explainable AI.
3. **Workflow Architecture** → new **Permanent Development Principle 18** — every major workflow follows one documented lifecycle (create → validate → canonical → metadata → DNA → stories → audiences → marketing → Ask AI → matching → analytics → publish → learning loop).
4. **Testing Strategy** → new **Appendix M** — the permanent testing strategy; every phase defines its automated tests.
5. **Transaction Engine scope note** → new **Scope Boundary** section — Transaction Engine, Marketplace Platform, and Operations Platform are intentionally kept **out of scope for this roadmap** and are to be captured as **their own separate roadmap documents when that work begins (none exists yet)**, preserving the Intelligence/Transaction separation.
6. **Final purpose principle** → new **Permanent Development Principle 19** — the platform's purpose is to transform data into trusted, explainable intelligence for better decisions; every future feature must strengthen one of the ten stated objectives.
7. **Universal Listing ↔ Criteria Matching** → new subsection under **Phase 13** + new **Permanent Development Principle 20** ("One Matching Engine. One Intelligence Engine. Many Data Sources.") — source-agnostic, bidirectional matching of every listing source against every applicable criteria source, with a defined per-match output schema; verification folded into Phase 15.

Appendix L (deliverable index) updated for Phase 16; nothing renumbered among existing phases.

---

## Change Log — Version 2.1 → Version 2.2 (final pre-implementation revision)

Version 2.2 **preserves all Version 2.0 content unchanged** and integrates the **approved high-impact recommendations from the Version 2.1 architecture review** (`docs/bid-your-offer-v2.1-architecture-review.md`). No existing phase, task, deliverable, appendix, or principle was removed. The additions:

1. **Compliance / Responsible AI / Fair Housing / Privacy / Data Governance** → new **Phase 14.5**, plus checks folded into the Phase 15 launch gate.
2. **Story Engine** (12 story types, grounded only in structured data + canonical fields + metadata + taxonomy + DNA) → new **Phase 9.5**.
3. **Complete DNA suite** — Seller DNA & Landlord DNA → new **Phase 7.5**; Lifestyle / Luxury / Investment / Commercial / Community DNA promoted to first-class engines → new **Phase 8.5**.
4. **Symmetric Location Intelligence** — property-side Location DNA outputs (personality/signals/audiences/stories) and buyer/tenant **Location Preference DNA** on one shared canonical location vocabulary → integrated into **Phase 8** (+ cross-refs in Phases 6/7).
5. **Canonical Entity Dictionary** (Property, Listing, Unit, Parcel, Buyer, Tenant, Seller, Landlord, Agent, Neighborhood, Community, Media, Transaction, Market, Amenity, Business, School…) → new **Phase 3.6**.
6. **Master Intelligence Taxonomy** (governed, versioned, namespaced) → new **Phase 3.7**.
7. **AI Automation** (validation, cross-source contradiction detection, description analysis, metadata extraction, completeness scoring, missing-field/inferred metadata, future image understanding, QA) → new **Phase 3.8**.
8. **Universal Real Estate Knowledge Graph** (central intelligence layer connecting every entity + intelligence output) → new **Phase 3.9**, populated by all downstream phases and consumed by Ask AI / Matching / Recommendation / Analytics.
9. **Intelligent Multi-Source Import Pipeline** (import → canonical → metadata → DNA → stories → audiences → marketing → Ask AI → search → matching → analytics, zero manual tagging) → new **Phase 14.7**.
10. **Behavioral Learning** (views, favorites, saved searches, offers, accepted/rejected matches, search behavior, agent interactions re-weight matching without changing the canonical model) → integrated into **Phase 13**.
11. **Architecture & Scalability Requirements** (non-functional, technology-neutral) → new front-matter section; verified at Phase 15.
12. **Universal Ask AI** (answers any supported question across all layers + provenance + confidence; states when data is unavailable rather than inventing) → expanded **Phase 12**.
13. **Universal Intelligence Principle** → new **Permanent Development Principle 15**.

Appendices **K** (dependency graph) and **L** (deliverable index) were updated to include every new phase and deliverable. Nothing was renumbered among the existing integer phases; all new phases use decimal numbers so prior cross-references remain valid.

---

## Change Log — Version 1.0 → Version 2.0

Version 2.0 preserves every phase, task, implementation detail, deliverable, success criterion, dependency, verification checklist, appendix, and principle from Version 1.0. Nothing was removed or simplified. The following **architectural evolutions** were layered on top:

1. **Single Canonical Data Model (the central change).** Bid Your Offer and Stellar MLS are **no longer separate implementation paths.** Everything is now built around one **Canonical Data Model**: a source-neutral field vocabulary that all data sources map into. All implementation work — every field, every phase — now applies **simultaneously** to BYO user-entered fields, Stellar MLS imported fields, future MLS providers, RentCast, ATTOM, public records, CSV imports, and future APIs, through a shared **Canonical Field Mapping layer**.

2. **New Phase 3.5 — Canonical Field Mapping.** Inserted immediately after Phase 3. Defines the translation/normalization layer between every supported data source and the Canonical Data Dictionary, with the new deliverable `docs/canonical-field-mapping-spec.md`.

3. **All intelligence consumes canonical fields, never raw source fields.** The Metadata Engine (Phase 4), Property DNA (5), Buyer DNA (6), Tenant DNA (7), Location DNA (8), Target Audience Intelligence (9), Recommendation Engine (10), Marketing Intelligence (11), Ask AI (12), Matching (13), and Analytics/Explainability (14) now read the **canonical field layer** — not raw BYO fields and not raw Stellar fields. This makes every intelligence layer automatically source-agnostic: a listing ingested from RentCast or a CSV scores, matches, and markets identically to a BYO-entered one, provided its source mapping exists.

4. **Full-lifecycle "done" replaces BYO-first-then-integrate.** Version 1.0 sequenced BYO implementation first and Stellar integration later. Version 2.0 replaces that with a **per-field full-lifecycle definition of done**: a field is not "complete" until it has been verified end-to-end across BYO forms, validation, database, edit/autopopulate, public display, canonical mapping, Stellar mapping, future-MLS compatibility, metadata generation, Property/Buyer/Tenant/Location DNA (where applicable), search, Ask AI, matching, recommendations, marketing, analytics, tests, and documentation. See the expanded **Cross-cutting definition of done** below.

5. **New source-neutral principles.** The Permanent Development Principles were extended (Principles 11–14) to encode the Canonical Data Model, multi-source ingestion, per-field lifecycle completeness, and confidence/provenance tracking.

6. **Documentation, dependency graph, and deliverable index updated** to include Phase 3.5, the canonical layer, and the new source-neutral consumption model.

7. **Foundation-audit completeness pass (final V2.0 revision).** Added a **Foundation Audit Coverage Matrix** and a **Metadata / Lifestyle / Target Audience Completeness Check** near the top, proving all four audits are fully carried forward, and added **Appendices A–J** enumerating verbatim the required/optional field lists and the Beyond-MLS §10 build-sheets (Top 100 metadata tags, Top 50 lifestyle categories, Top 50 target audiences, Top 50 buyer/tenant motivations, Top 50 investment tags, Top 50 commercial tags, Top 50 neighborhood tags, Top 50 marketing tags, Top 25 AI scores) so no intelligence recommendation is lost. The original two appendices were renumbered to **K** (dependency graph) and **L** (deliverable index); nothing was removed.

**Migration note for readers of V1.0:** wherever V1.0 said "add field X to BYO and wire it into Ask AI/search/DNA," V2.0 means the same work **plus** defining X's canonical field, its BYO↔canonical mapping, its Stellar↔canonical mapping, and its forward-compatible mapping shape for future sources — and pointing every downstream consumer at the canonical field rather than the raw BYO meta key.

---

# Foundation Audit Coverage Matrix

This roadmap is built from four foundation audits. This matrix proves each one is fully carried forward, names the phases that consume it, and records any items that are pointed-to (rather than inlined) so nothing is lost. **Coverage status legend:** ✅ Fully incorporated (inlined or enumerated in an appendix) · 🔗 Incorporated by reference (the roadmap points to the audit rather than duplicating a very large enumeration).

| Source Audit | What It Contributes | Roadmap Phases Using It | Coverage Status | Missing Items / Notes |
|---|---|---|---|---|
| **`docs/bid-your-offer-field-audit.md`** | Every current BYO field across all four role flows × five property types; storage model (EAV meta per role; native `title`/`address`); save/edit/display/matching/Ask AI/DNA wiring per field; the cross-cutting facts (agent-bid matching reality, Ask AI registry subsystem, three DNA subsystems' wiring, Buyer DNA unwired for offer listings); BYO-internal duplicate/cleanup findings. | Phase 0 (baseline); Phase 3 (every current field becomes a canonical dictionary row); Phase 3.5 (BYO→canonical source mapping); Phases 4–14 (current fields feed metadata/DNA/search/Ask AI/matching via canonical layer); Phase 15 (save/edit/display verification). | 🔗 + ✅ | The ~1,000+ individual current BYO fields are **incorporated by reference** into Phase 3 (which enumerates every canonical field, existing + new) rather than re-listed here — the field audit remains the authoritative current-state inventory. Duplicate cleanups (`condition_prop`/`condition_prop_buyer`, empty `pet_policy`, tour-URL label inversion) are carried in Phases 2 & 3 as canonical-name reconciliations. |
| **`docs/bid-your-offer-stellar-master-field-comparison.md`** | Definitive required-vs-optional split; §1 required-missing fields (39 instances / ≈18 concepts); §2 optional-missing fields (55 concepts); §3 do-not-add administrative fields (22 groups); §4 where-BYO-is-better; §5 statistics (86.1% required / ~90.4% overall). | Phase 1 (required-missing → implement); Phase 2 (optional-missing → implement); Phase 3.5 (Stellar→canonical mapping + §3 exclusions); Phase 15 (§3 do-not-add audit, no-duplicate audit). | ✅ | Required-missing enumerated in **Appendix A** and Phase 1 table; optional-missing in **Appendix B** and Phase 2; **do-not-add list carried in Phases 1/2/3.5/15 Do-not-do warnings** and summarized in Appendix A/B preambles. "BYO-is-better" (§4) preserved as the Orientation "ahead of Stellar" list + do-not-regress warnings. |
| **`docs/bid-your-offer-stellar-gap-analysis.md`** | Top 100 field additions (§8, tiered Critical/High/Medium); Top 100 metadata/lifestyle tag slugs (§9); Wave 0–7 implementation order (§10); per-form Implemented/Partial/Missing/Do-not-add breakdowns; launch-blocker list; duplicate cleanups. | Phase 1 (Critical/launch-blocker tier); Phase 2 (High/Medium tiers + Wave 1–7 sequencing); Phase 4 (metadata tags); Phase 3 (cleanups). | ✅ | Top 100 field additions map onto Phases 1–2 (Appendices A/B); the §9 Top 100 tag slugs are enumerated in **Appendix C.2**; the Wave 0–7 order is preserved inside Phase 2's sequencing paragraph. |
| **`docs/beyond-mls-property-dna-roadmap.md`** | The intelligence layer: Lifestyle Intelligence, Target Audience Intelligence, Buyer/Tenant Intent, Neighborhood Intelligence, Property Personality, Commercial/Investment Intelligence, AI-derived metadata "Compute-Don't-Ask" scores, Future DNA categories, and the §10 build sheets (Top 100 fields, Top 100 tags, Top 25 scores, Top 50 lifestyle/audiences/motivations/investment/commercial/neighborhood/marketing) + the 8-wave order. | Phase 4 (metadata), 5 (Property DNA), 6 (Buyer DNA), 7 (Tenant DNA), 8 (Location DNA), 9 (Target Audience), 10 (Recommendations), 11 (Marketing), 12 (Ask AI), 13 (Matching), 14 (Analytics). | ✅ | Previously referenced thematically; **now enumerated verbatim** in Appendices C–J so future sessions never lose the specific tags/scores/audiences/motivations. See the Completeness Check below for the per-list mapping. |

**Cross-audit completeness of the user's checklist** (each item and where it lives):

- **All current BYO fields** → Field Audit → Phase 3 dictionary (🔗 by reference) + Phase 3.5 BYO mappings.
- **All required Stellar fields** → Master Comparison §1 + §5.2 → 242 already-supported (Field Audit/Phase 3) + 39 to implement (**Appendix A**, Phase 1).
- **All optional high-value Stellar fields** → Master Comparison §2 → **Appendix B**, Phase 2.
- **All missing required fields** → **Appendix A** (Phase 1).
- **All missing optional fields worth adding** → **Appendix B** (Phase 2), incl. the low-priority backlog.
- **All do-not-add MLS/admin fields** → Master Comparison §3 → Phases 1/2/3.5/15 Do-not-do warnings + Appendix A/B preambles.
- **All Beyond-MLS lifestyle categories** → **Appendix D** (§10.4, 50).
- **All target audiences** → **Appendix E** (§10.5, 50).
- **All buyer motivations** / **all tenant motivations** → **Appendix F** (§10.6, 50, flow-tagged B/T).
- **All property personality tags** → **Appendix C.1** (§10.2 Personality rows 21–34) + Phase 5 personality/vibe.
- **All commercial intelligence tags** → **Appendix G.2** (§10.8, 50).
- **All investment intelligence tags** → **Appendix G.1** (§10.7, 50).
- **All neighborhood intelligence tags** → **Appendix H** (§10.9, 50).
- **All marketing intelligence tags** → **Appendix I** (§10.10, 50).
- **All AI-derived metadata scores** → **Appendix J** (§10.3, 25) + Phase 5.
- **All Property/Buyer/Tenant/Location DNA concepts** → Phases 5/6/7/8 + Appendices C–J inputs.
- **All metadata tag recommendations** → **Appendix C** (C.1 = §10.2 Top 100; C.2 = gap-analysis §9 Top 100 slugs).
- **All Ask AI field/question implications** → Phase 12 + every canonical field's Ask AI registry entry (Field Audit registry subsystem).
- **All search/matching/marketing implications** → Phases 12/13/11, driven by the canonical fields + Appendix tags/scores.

---

# Metadata / Lifestyle / Target Audience Completeness Check

Confirms the roadmap fully carries forward the Beyond-MLS roadmap's §10 recommendation build-sheets. Every list below is now enumerated verbatim in an appendix (previously they were referenced only thematically), so no future Claude session loses them.

| Beyond-MLS §10 List | Items | Carried Forward In | Consuming Phase(s) | Status |
|---|---|---:|---|---|
| §10.1 Top 100 Beyond-MLS Fields | 100 | Phases 1–2 (implement) + **Appendix A/B** (missing subset) + gap-analysis §8 cross-ref | 1, 2, 3, 3.5 | ✅ |
| §10.2 Top 100 AI Metadata Tags | 100 | **Appendix C.1** | 4 (metadata), 9, 11, 13 | ✅ |
| §10.3 Top 25 AI Scores | 25 | **Appendix J** | 5 (Property DNA), 9, 13 | ✅ |
| §10.4 Top 50 Lifestyle Categories | 50 | **Appendix D** | 4, 6, 7, 9 | ✅ |
| §10.5 Top 50 Target Audiences | 50 | **Appendix E** | 9 (Target Audience), 10, 11 | ✅ |
| §10.6 Top 50 Buyer / Tenant Motivations | 50 | **Appendix F** | 6 (Buyer DNA), 7 (Tenant DNA), 13 | ✅ |
| §10.7 Top 50 Investment Tags | 50 | **Appendix G.1** | 4, 5, 10, 13 | ✅ |
| §10.8 Top 50 Commercial Tags | 50 | **Appendix G.2** | 4, 5, 9, 13 | ✅ |
| §10.9 Top 50 Neighborhood Tags | 50 | **Appendix H** | 8 (Location DNA), 9, 13 | ✅ |
| §10.10 Top 50 Marketing Tags | 50 | **Appendix I** | 11 (Marketing), 12 | ✅ |
| Gap-Analysis §9 Top 100 metadata/lifestyle tag slugs | 100 | **Appendix C.2** | 4, 9, 11, 13 | ✅ |

> **Carry-forward rule.** These appendices are the **catalog of intended intelligence outputs**, not new user-input fields. Per Principle 2 & 9 they are **derived** from canonical fields at save/DNA-generation time (never hand-tagged), and per Principle 12 they are computed on the canonical layer so they are identical across data sources. When a phase implements a tag/score/audience/motivation, it should trace it back to the canonical fields (Appendix A/B + Phase 3 dictionary) that produce it.

---

## Orientation — where the project stands today

Established by the four foundation audits (Phase 0):

- BYO already supports **~90.4% of all consumer-relevant Stellar MLS listing fields** and **~86.1% of the strictly required ones**. The remaining gap is **not** breadth of physical description — it is a small set of required fields plus a high-value lifestyle/DNA layer.
- **39 required consumer fields are Missing/Partial** (≈ **18 distinct field-concepts**, since most repeat across the seven forms). **55 optional consumer field-concepts are Missing** (exact, enumerated).
- **~22 Stellar field-groups must NOT be added** — they are MLS-administrative plumbing (legal survey IDs, agent/office, showing/lockbox, IDX/VOW syndication, signatures, owner/tenant PII).
- BYO is already **ahead of Stellar** on: Property/Buyer/Tenant/Location DNA, the Ask AI per-role field registry (~90 fields/role), compatibility scoring, consumer criteria capture (commute, search-area polygons, Important Places), structured financing sub-forms (incl. crypto/NFT), business-opportunity economics (SDE/EBITDA/FF&E), and rental pre-screening. **Do not regress any of these.**

**Architectural facts every phase must respect (from the Field Audit + CLAUDE.md):**

- **Role symmetry**: almost everything is quadruplicated (Seller / Buyer / Landlord / Tenant). A field added to a listing side usually needs its mirror on the criteria side.
- **Storage model**: Offer Listing flows write **everything via EAV meta** (`{role}_agent_auction_metas`) except `title`/`address` native columns. Seller/Buyer store via meta in the Offer Listing flow; Landlord/Tenant use EAV meta by design. Respect the seller/buyer native-column vs landlord/tenant meta asymmetry described in CLAUDE.md for the older Hire flow.
- **Matching today** is agent↔consumer **bid**-matching only (`services` 35% + `terms` 35% active; others disabled; weights sum to 100). **No property-criteria field feeds the match score yet.** Property criteria feed search/filter and the (kill-switched) BYA compatibility engine. Phase 13 changes this.
- **Ask AI** is a formal per-role registry (`AskAiFieldQuestionRegistryService`, `AskAiKnowledgeSnapshotBuilderService`, `AskAiContextBuilderService`, `config/ai_faq_{role}.php`). Every new field that should be answerable must be registered here.
- **DNA subsystems** are separate: **Location DNA** (`LocationDnaPipelineRunner`, `ComputeLocationDna` job), **Property DNA** (`PropertyDnaGenerator` via `PropertyAuctionDnaObserver`), **Buyer/Tenant DNA** (`BuyerTenantDnaGenerator`, reads `buyer_criteria_auctions`/tenant criteria — **currently unwired for the Buyer Offer Listing flow**).

---

## The Canonical Data Model (Version 2.0 core architecture)

Version 2.0 unifies everything under a **single Canonical Data Model**. Bid Your Offer and Stellar MLS are **not** separate implementation paths — they are two *sources* that both map into one source-neutral canonical vocabulary. Every intelligence layer reads the canonical layer, so a listing is scored, matched, searched, and marketed **identically regardless of where its data came from**.

**Supported and future data sources (all map into the canonical layer via the Canonical Field Mapping — Phase 3.5):**

1. **Bid Your Offer user-entered fields** — the four role flows (Seller/Buyer/Landlord/Tenant), EAV-meta-stored.
2. **Stellar MLS imported fields** — the seven consumer data-entry forms.
3. **Future MLS providers** — any additional RESO/MLS feed; the mapping layer is provider-pluggable.
4. **RentCast** — rental/AVM/comp data.
5. **ATTOM** — property, tax, deed, valuation, and hazard data.
6. **Public records** — assessor/recorder/permit data.
7. **CSV imports** — bulk/manual ingestion.
8. **Future APIs** — any source added later maps in without touching downstream consumers.

**Layering (top to bottom):**

```
Sources:   BYO forms │ Stellar │ future MLS │ RentCast │ ATTOM │ public records │ CSV │ future APIs
                │         │          │            │          │          │          │        │
                └─────────┴──────────┴────────────┴──── Canonical Field Mapping (Phase 3.5) ──┘
                                                   │
                                          Canonical Data Model
                                     (canonical fields — Phase 3 dictionary)
                                                   │
   ┌───────────┬───────────┬───────────┬──────────┴────┬───────────┬───────────┬──────────┐
Metadata   Property DNA  Buyer DNA  Tenant DNA   Location DNA   Target Aud.  Recommend.  Marketing
Engine(4)     (5)          (6)         (7)            (8)           (9)          (10)        (11)
   └───────────┴───────────┴───────────┴───── Ask AI (12) · Matching (13) · Analytics (14) ──────┘
```

**Consequences that govern every phase from here on:**

- **Downstream consumers never reference a raw source field.** Phases 4–14 read canonical fields only. If a consumer needs a value, it asks the canonical layer, which resolves it from whichever source(s) populated it (with confidence/provenance).
- **Adding a new source is a mapping-only change.** When a future source (e.g. a new MLS or API) is onboarded, only its Phase 3.5 mapping is authored; no metadata rule, DNA score, matcher, or marketing generator changes.
- **Every canonical field carries provenance + confidence.** Because the same canonical field can be filled by BYO entry, Stellar import, ATTOM, or CSV, each value records which source(s) supplied it and a confidence level (see Phase 3.5). Conflicts are resolved by a documented precedence policy, not silently.
- **Normalization happens at the mapping layer.** Source-specific option lists (e.g. Stellar's ownership enum vs a BYO dropdown vs an ATTOM code) are normalized to canonical allowed-values in Phase 3.5, so consumers see one vocabulary.

---

**Cross-cutting definition of done — the per-field full lifecycle (Version 2.0).** Version 2.0 replaces the "implement in BYO first, integrate sources later" sequencing with a **full-lifecycle completion gate**: a field is **not complete** until *every* stage below is verified end-to-end. This applies to every field added in Phases 1–2 (and is re-verified in Phase 15). A field that passes forms but has no canonical mapping, or has a canonical mapping but no Stellar mapping, or is searchable but not answerable by Ask AI, is **incomplete**.

For each field, verify — in this order, but none skippable:

1. **BYO forms** — input rendered in the correct wizard tab, mirroring the most similar existing field's UI (Permanent UI/UX Standards). Full Service scope only; never inside `initializeLimitedService()`.
2. **Validation** — partial rule on Save Draft, full rule on Submit, in the component and its `…Edit` twin.
3. **Database** — persists (EAV meta or native per the storage model), for all applicable roles/property types.
4. **Edit / autopopulate** — round-trips correctly on edit.
5. **Public display** — shows where flagged Public; hidden where excluded.
6. **Canonical mapping** — the field maps to its **canonical field** (Phase 3 dictionary), with normalized allowed-values (Phase 3.5).
7. **Stellar mapping** — the corresponding Stellar MLS field(s) map to the same canonical field, normalized identically.
8. **Future-MLS compatibility** — the mapping shape is provider-pluggable (RentCast/ATTOM/public-records/CSV/future-API slots defined or explicitly N/A), so a new source needs only a mapping entry.
9. **Metadata generation** — derived tags fire from the canonical field at save/DNA-generation time (Phase 4), never hand-entered.
10. **Property DNA** — feeds the relevant Property DNA scores (Phase 5) where applicable.
11. **Buyer DNA** — feeds Buyer DNA dimensions (Phase 6) where applicable.
12. **Tenant DNA** — feeds Tenant DNA dimensions (Phase 7) where applicable.
13. **Location DNA** — feeds/consumes Location DNA (Phase 8) where applicable.
14. **Search** — exposed as a search facet where flagged **S**, querying the canonical field.
15. **Ask AI** — registered in `AskAiFieldQuestionRegistryService` + `config/ai_faq_{role}.php`; answerable from the canonical field.
16. **Matching** — participates in Matching V2 (Phase 13) where applicable, via the canonical field, respecting `config/match_scoring.php` weight discipline.
17. **Recommendation Engine** — available to recommendations (Phase 10) where applicable.
18. **Marketing Intelligence** — available to marketing generation (Phase 11) where applicable.
19. **Analytics** — surfaced in analytics/explainability + completeness scoring (Phase 14) where applicable.
20. **Tests** — automated coverage (SQLite in-memory per CLAUDE.md) for save/edit/mapping/normalization at minimum.
21. **Documentation** — recorded in the Canonical Data Dictionary (Phase 3) and Canonical Field Mapping spec (Phase 3.5).

Stages 6–8 (canonical + Stellar + future-source mapping) are the Version 2.0 additions that make every downstream stage (metadata, DNA, search, Ask AI, matching, recommendations, marketing, analytics — Phases 4–14) source-agnostic. **Where a stage is genuinely not applicable to a field, mark it "N/A" with a one-line reason — do not silently skip it** (Principle: no silent caps).

---

## The Unified Real Estate Intelligence Platform (Version 2.2 framing)

The Canonical Data Model is the *input* discipline; the **Universal Real Estate Knowledge Graph** (Phase 3.9) is the *integration* substrate. Everything the platform builds — canonical fields (Phase 3), canonical entities (Phase 3.6), taxonomy terms (Phase 3.7), AI-cleaned/inferred data (Phase 3.8), metadata (Phase 4), the full DNA suite (Phases 5–8.5), target audiences (Phase 9), stories (Phase 9.5) — becomes nodes and edges in one graph, and every consumer (Recommendations, Marketing, Ask AI, Matching, Analytics) reads from it.

**First-class intelligence engines** (each source-agnostic, each reading/writing the canonical layer + graph): the **Canonical Data Model**, the **Metadata Engine**, the **DNA Engines**, the **Location Intelligence Engine** (whose outputs are Location DNA + Location Preference DNA — see Phase 8), the **Story Engine**, the **Knowledge Graph**, the **Universal Matching Engine**, **Marketing Intelligence**, **Ask AI**, and **Analytics**. Location Intelligence is a peer of the DNA engines, not a sub-feature of them. The layered picture:

```
SOURCES → Canonical Field Mapping (3.5) → Canonical Data Model (fields 3 · entities 3.6)
        → Master Intelligence Taxonomy (3.7) → AI Automation & Data Quality (3.8)
        → UNIVERSAL REAL ESTATE KNOWLEDGE GRAPH (3.9)  ◄── central intelligence layer
              ▲ populated by: Metadata (4) · Property/Buyer/Tenant/Seller/Landlord DNA (5–7.5)
                · Location DNA + Location Preference DNA (8) · Domain DNA suite (8.5)
                · Target Audiences (9) · Story Engine (9.5)
              ▼ consumed by: Recommendations (10) · Marketing (11) · Ask AI (12)
                · Matching (13) · Analytics/Explainability (14)
        → Governance: Compliance/Responsible-AI (14.5) · Automated Import Pipeline (14.7)
        → Launch gate (15)
```

---

## Architecture & Scalability Requirements (Version 2.2, non-functional)

These are **technology-neutral architectural requirements**, not technology choices. Specific engines/stores are implementation decisions made during the phases; the roadmap fixes the *requirements* so a 100,000+ agent, multi-MLS future does not force a redesign. Assume growth to 100k+ agents across multiple MLSs.

1. **Canonical read model.** Canonical field/entity values must be readable from a **query-optimized, indexed store** rather than scanned live from EAV meta rows. (The EAV `*_agent_auction_metas` model remains the write/source-of-record for BYO forms; a derived canonical read model serves search/matching/intelligence.)
2. **Optimized search layer.** Faceted search over canonical fields must be served by a search-optimized index, not ad-hoc SQL over meta.
3. **Semantic / vector retrieval support.** DNA vectors, story embeddings, and vibe embeddings must be storable and queryable in a vector-capable store to support similarity/semantic retrieval and Matching V2.
4. **Event-driven DNA/intelligence regeneration.** Metadata, DNA, stories, audiences, and graph edges recompute in response to canonical-value change events — versioned and partial, never a full-table rescan per edit.
5. **Multi-MLS support.** The ingestion + mapping layer must support multiple concurrent MLS/source feeds without per-source downstream code.
6. **Incremental synchronization.** Imports are incremental, idempotent, and queued; re-imports update rather than duplicate.
7. **Entity resolution & duplicate detection.** The same real-world property arriving from two sources (e.g. two overlapping MLSs, or MLS + public records) resolves to **one canonical entity** (Phase 3.6), with source values merged per the Phase 3.5 precedence policy.
8. **Scalable AI architecture.** AI generation (DNA, stories, inference, image understanding) runs as asynchronous, rate-limited, cacheable, versioned jobs — decoupled from request paths.
9. **Model & taxonomy versioning at scale.** Re-scoring/re-generating across 100k+ listings after a model or taxonomy change is a governed, resumable migration.

> **Deliverable:** `docs/architecture-scalability-requirements.md` (may be authored alongside Phase 3.9). **Verified at Phase 15.** Technology selection (which search engine, which vector store, which queue) stays an implementation decision.

---

## Scope Boundary — Intelligence vs. Transactions (Version 2.3)

**This roadmap intentionally focuses on the Real Estate Intelligence Platform.** The following are **out of scope for this roadmap** and are deliberately excluded to preserve a clean separation between **Intelligence** and **Transactions/Operations**. Each is intended to be **captured in its own separate roadmap document when that work begins — none has been written yet:**

- **Transaction Engine** — offer workflows, agent bidding, counteroffers, negotiations, bid timers, reserve logic, offer acceptance, and the transaction lifecycle.
- **Marketplace Platform** — the consumer/agent marketplace surfaces.
- **Operations Platform** — internal operations tooling.

**Why the separation is intentional:** the Intelligence Platform *produces* trusted, explainable signals (DNA, stories, matches, recommendations, audiences); the Transaction Engine *consumes* them to run deals. Keeping them as separate documents lets each evolve on its own cadence without cross-contaminating architecture. Where the two meet, the interface is the Canonical Data Model + Knowledge Graph (this roadmap) feeding the transaction workflows (a future separate document). Match *outcomes* and *offer activity* flow **back** into this platform only as **learning signals** (Principle 17), not as transaction logic.

> Note: BYO's existing offer/bidding workflow (referenced in the Field Audit as "ahead of Stellar") will be specified in the future Transaction Engine document; this roadmap does not respecify it.

---

# Phase 0 — Foundation Audits Complete

**Status:** ✅ **Complete.**

**Goal:** Establish an exhaustive, verified baseline of BYO's current data model vs. the Stellar MLS consumer forms, the required-vs-optional split, the gap analysis, and the intelligence-layer design — so all later phases build on facts, not guesses.

**Source documents (all delivered, documentation-only, nothing committed):**

1. **`docs/bid-your-offer-field-audit.md`** — Current BYO field audit. Every field in all four role flows (Seller/Buyer/Landlord/Tenant) × five property types, with storage location, save/edit/display wiring, matching/Ask AI/DNA usage, current tags, and known issues. Confirms the EAV-meta storage model, the agent-bid matching reality, the Ask AI registry, and the three DNA subsystems' wiring state.
2. **`docs/bid-your-offer-stellar-master-field-comparison.md`** — **Definitive source of truth.** All 77 pages of the seven Stellar forms read; every asterisked (required) field extracted and classified consumer/administrative and Supported/Partial/Missing. Contains the required-missing tables (§1), optional-missing tables (§2), the do-not-add list (§3), where-BYO-is-better (§4), and the final statistics (§5: 86.1% required / ~90.4% overall).
3. **`docs/bid-your-offer-stellar-gap-analysis.md`** — Working gap analysis (superseded by the Master Comparison for counts, but retains the **Top 100 field additions**, **Top 100 metadata/lifestyle tags**, and the **Wave 0–7 implementation order**). Includes per-form Implemented / Covered-under-different-name / Partial / Missing / Do-not-add breakdowns and BYO-internal duplicate cleanups.
4. **`docs/beyond-mls-property-dna-roadmap.md`** — Beyond-MLS intelligence design. The seven-question schema (What · Why · Value source · Property types · Flows · Becomes · Priority), Lifestyle Intelligence, Target Audience Intelligence, Buyer/Tenant Intent, Neighborhood Intelligence, Property Personality, Commercial/Investment Intelligence, AI-derived metadata ("Compute, Don't Ask"), Future DNA categories, and §10 Top-N build sheets with an 8-Wave sequencing.

**Deliverables:** ✅ Current BYO field audit · ✅ Stellar MLS comparison · ✅ Required-vs-optional field analysis · ✅ Gap analysis · ✅ Beyond-MLS Property DNA roadmap.

**Success criteria (met):** Every Stellar consumer field classified; exact missing-field enumeration exists; do-not-add list exists; intelligence-layer design exists.

**Dependencies:** None.

**Verification checklist:**
- [x] All four documents exist under `docs/`.
- [x] Master Comparison enumerates the 39 required-missing and 55 optional-missing fields.
- [x] Do-not-add list (§3) enumerated.
- [x] Gap analysis Top 100 + Wave sequencing present.
- [x] Property DNA roadmap §10 build sheets present.

**Do-not-do warnings:** Do not re-run these audits from scratch — extend/annotate them. Do not treat the gap-analysis field **counts** as authoritative where they differ from the Master Comparison; the Master Comparison supersedes them.

---

# Phase 1 — MLS Compatibility Required Fields

**Goal:** Implement every remaining **consumer-relevant required** Stellar MLS field that is Missing/Partial in BYO, so every property type becomes launch-complete against the MLS. **The Master Field Comparison (§1) is the source of truth for this phase.**

**Source documents:** `bid-your-offer-stellar-master-field-comparison.md` §1 + §5.2 (required tables & counts); `bid-your-offer-stellar-gap-analysis.md` "Critical fields missing before launch" + Wave 0; `bid-your-offer-field-audit.md` (current wiring).

**Scope — the required-missing field-concepts (≈18 distinct, spanning 39 form-instances).** For each, the spec dimensions the user requires (Affected flows · Affected property types · Input type · Required/optional · Suggested options · Where it displays · Ask AI? · Search? · DNA? · Notes):

| Field-concept | Affected flows | Affected property types | Input type | Req/Opt | Suggested options | Display | Ask AI | Search | DNA | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| **Green Energy Generation (Y/N + type)** | Seller, Buyer, Landlord, Tenant | Residential, Income, Commercial Sale, Business, Commercial Lease, Rental | select/checkbox | Required | Yes/No + Solar/Wind/Hydro | Public listing | Yes | Yes | Property DNA (`energy_efficient`, `low_utility_cost`) | Pairs with Solar Panel Ownership (Phase 2) |
| **Exact Lot Size Square Feet** | Seller, Buyer, Landlord, Tenant | All land-bearing (esp. Residential, Vacant Land) | number | Required | numeric sqft | Public | Yes | Yes (≥X sqft) | Property DNA (`large_lot`) | Replaces range bucket for search/valuation |
| **Exact Lot Size Acres** | Seller, Buyer | All land-bearing | number | Required | numeric acres | Public | Yes | Yes | Property DNA (`large_acreage`) | Complements `total_acreage` range |
| **Ownership Type** | Seller, Buyer, Landlord, Tenant | Residential, Income, Commercial Sale, Business, Vacant Land | select | Required | Fee Simple / Condominium / Co-op / Fractional / Leasehold | Public | Yes | Yes | tags `fee_simple`, `leasehold` | Distinct from `occupant_status` |
| **Land Lease (Y/N + fee)** | Seller, Buyer | Residential, Income | radio + number | Required | Yes/No + annual fee | Public | Yes | Yes | tag `land_lease` | Lending/cost constraint; pairs with Ownership |
| **Front Exposure** | Seller, Buyer | Residential | select | Required | N/S/E/W + NE/NW/SE/SW | Public | Yes | Yes | Property DNA (`exposure_south`, `natural_light`) | Cheap, strong lifestyle/energy signal |
| **Floors / Stories** | Seller, Buyer, Landlord, Tenant | Residential, Rental | number | Required | numeric | Public | Yes | Yes (single vs multi) | Accessibility DNA (`single_level`, `multi_story`) | Top accessibility/aging-in-place filter |
| **Fireplace (standalone field)** | Seller, Buyer | Residential, Income | radio + select | Required | Y/N + Gas/Wood/Electric + location | Public | Yes | Yes | lifestyle `fireplace`, `cozy` | Currently buried in `interior_features`; extract to searchable field |
| **Room Types / Additional Rooms** | Seller, Buyer, Landlord, Tenant | Residential, Rental | repeater/multiselect | Required (grid) | Kitchen/Living/Primary + Den/Office/Bonus/Media/Great/Florida/Loft/Inside Utility | Public | Yes | Yes | Property DNA (`home_office`, `media_room`, `flex_space`) | Structured room list; see Phase 2 detail |
| **Road Surface Type (where missing)** | Seller, Buyer | Residential (extend from Commercial/VL) | select | Required | Paved/Asphalt/Concrete/Gravel/Dirt/Private | Public | Partial | Yes | — | Extend-to-flow of existing field |
| **Furnishings (where missing)** | Seller, Buyer | Residential sale (extend from rental) | select | Required | Furnished / Unfurnished / Partially | Public | Yes | Yes | tag `furnished` | Extend rental-side `tenant_require` to sale |
| **Laundry Features (where missing)** | Seller, Buyer | Residential sale (extend from Landlord) | multiselect | Required | In-Unit/Hookups/Common/None | Public | Partial | Yes | — | Extend existing landlord field to sale |
| **Floor Covering (where missing)** | Seller, Buyer | Residential sale (extend from Landlord) | multiselect | Required | Tile/Wood/LVP/Carpet/Laminate/Stone | Public | Partial | Yes | — | Extend existing landlord field to sale |
| **Application Fee** | Landlord, Tenant | Residential Rental | number (currency) | Required | $ amount | Public | Yes | Yes | tag `application_fee` | Standard rental move-in cost |
| **In-Law Suite (Y/N)** | Seller, Buyer, Landlord, Tenant | Residential, Rental | radio + detail | Required (Rental) | Y/N + attached/detached/kitchen/private-entry + sqft | Public | Yes | Yes | Property DNA (`multigenerational`, `income_potential`, `has_adu`) | Detail fields = Phase 2 |
| **Long-Term Rental flag** | Landlord, Tenant | Residential Rental | radio | Required | Long-Term (Y/N) | Public | Yes | Yes | Rental DNA (`annual` vs `seasonal`) | **Adopt with the seasonal block (Phase 2), not as a bare toggle** |
| **Lease Price Unit (Commercial Lease)** | Landlord, Tenant | Commercial Lease | select + number | **Required (Critical)** | $ Total Monthly / Per Square Foot + Annual/Monthly frequency | Public | Yes | Yes | tags `rate_per_sqft`, `nnn_rate` | **Blocks credible commercial-lease comparison/search/matching** |
| **Structured Initial Pass-Through Expenses** | Landlord, Tenant | Commercial Lease | number + select | Required | $ + Flat-Monthly / Annual $-per-SqFt | Public | Yes | Yes | tags `low_cam`, `nnn_transparent` | Replaces free-text `cam_nnn_additional_rent_charges` |
| **Road Frontage (where missing)** | Landlord, Tenant | Commercial Lease (extend from Seller-Commercial) | select/number | Required | frontage type + feet | Public | Partial | Yes | — | Extend-to-flow |
| **Designated Builder (Y/N)** | Seller, Buyer | Vacant Land | radio | Required | Y/N (flag only — see do-not-add) | Public | Yes | No | — | Builder tie-in in deed-restricted/PUD land |
| **For Lease flag (Vacant Land)** | Seller, Buyer | Vacant Land | radio | Required | Y/N | Public | Yes | Yes | tag `land_for_lease` | Ag/billboard/cell-tower land-lease segment |
| **Business Ownership / Entity Structure** | Seller, Buyer | Business Opportunity | select | Required | Franchise / Sole Proprietor / LLC / Corporation / Partnership / Leasehold | Public | Yes | Yes | Business DNA (`franchise`, `independent`) | Primary business-buyer filter |

> **Version 2.0 — Canonical & full-lifecycle note.** Every field below is implemented against the **full-lifecycle definition of done** (21 stages in the Orientation): it is not complete until it round-trips through BYO forms → validation → DB → edit → public display → **canonical mapping → Stellar mapping → future-source compatibility** → metadata → DNA → search → Ask AI → matching → recommendations → marketing → analytics → tests → docs. Concretely, each field concept below is authored as a **canonical field** first; the BYO form field and the Stellar MLS field(s) both map into it (Phase 3.5). Downstream work targets the canonical field, not the raw meta key. Because Phase 1 precedes Phases 3/3.5 in numbering but the mapping cannot lag the field, author each field's provisional canonical name + BYO↔canonical + Stellar↔canonical mapping stub *as part of implementing it*, then reconcile those stubs into the formal dictionary (Phase 3) and mapping spec (Phase 3.5).

**Tasks:**
1. For each field-concept above, add option lists to the appropriate `config/` file (mirroring the closest existing option list's style). Define the **canonical allowed-values** and the source→canonical normalization for both the BYO option list and the Stellar option list.
2. Add the Livewire property + validation (partial rule on Save Draft, full rule on Submit) in each affected role component and its `…Edit` twin. **Full Service scope only — never inside `initializeLimitedService()`.**
3. Add the Blade input to the correct wizard tab, mirroring the most similar existing field's markup, label, placeholder, tooltip, helper text, and spacing exactly (Permanent UI/UX Standards).
4. Mirror listing↔criteria (Seller/Landlord ↔ Buyer/Tenant) where the concept applies.
5. Wire save (EAV meta), edit autopopulate, and public-listing display bindings.
6. Register each field in `AskAiFieldQuestionRegistryService` + the relevant `config/ai_faq_{role}.php`.
7. Add search facets for every field flagged **Search = Yes**.
8. Emit derived DNA/metadata tags at save/DNA-generation time (do not ask users to tag — see Principle 2).
9. Handle the four extend-to-flow partials (Road Surface, Furnishings, Laundry, Floor Covering on residential-sale; Road Frontage on commercial-lease) as extensions of the existing field, not new fields.
10. Treat **Lease Price Unit** as the #1 priority item (Critical) — it unblocks all commercial-lease comparison.
11. **Canonical mapping (V2.0):** for each field, author its canonical field name, BYO↔canonical mapping, and Stellar↔canonical mapping stub, plus placeholder slots for RentCast/ATTOM/public-records/CSV/future-API (or mark N/A). Downstream consumers (metadata/DNA/search/Ask AI/matching) reference the canonical field.
12. **Provenance + confidence (V2.0):** record which source populated each value and a confidence level; define the precedence when two sources conflict (e.g. BYO-entered vs Stellar-imported vs ATTOM).
13. **Tests (V2.0):** add save/edit/mapping/normalization test coverage per field (SQLite in-memory).

**Deliverable:** Every consumer-facing **required** Stellar MLS field is implemented **and mapped into the Canonical Data Model** for both BYO and Stellar sources (with future-source slots defined). Required-field compatibility moves from 86.1% toward 100% across all sources, not just BYO.

**Success criteria:**
- Master Comparison §1 tables have zero remaining "Missing" required rows on any flow.
- Each new field saves, edits/autopopulates, and displays publicly where flagged.
- Commercial-lease listings can now be normalized and compared by rate unit **regardless of source**.
- **Every field has a canonical name, a BYO↔canonical mapping, and a Stellar↔canonical mapping** (Phase 3.5 stubs authored).
- Full-lifecycle definition of done (21 stages) satisfied — or explicitly N/A'd — for every field.

**Dependencies:** Phase 0 complete. No dependency on Phase 2+.

**Verification checklist:**
- [ ] Every §1 required-missing row implemented on both its listing and (where applicable) criteria flow.
- [ ] Lease Price Unit + frequency present on Landlord + Tenant Commercial Lease.
- [ ] Exact Lot Size (sqft + acres) numeric on Residential + Vacant Land.
- [ ] Business Entity Structure present on Seller + Buyer Business.
- [ ] Extend-to-flow partials extended, not duplicated.
- [ ] All new fields registered in Ask AI and search where flagged.
- [ ] No field added inside `initializeLimitedService()`.
- [ ] **Every field has a canonical name + BYO↔canonical + Stellar↔canonical mapping stub (V2.0).**
- [ ] **Future-source slots (RentCast/ATTOM/public-records/CSV/future-API) defined or N/A'd per field (V2.0).**
- [ ] **Provenance + confidence + conflict-precedence defined per field (V2.0).**
- [ ] **Save/edit/mapping/normalization tests added (V2.0).**

**Do-not-do warnings:**
- **Do NOT** add any §3 administrative field (legal survey IDs, agent/office, showing/lockbox, IDX/VOW, signatures, owner/tenant PII).
- **Do NOT** add Designated Builder *name/details* — only the Y/N flag. Do NOT add Long-Term as a bare admin toggle — adopt it with the seasonal block.
- **Do NOT** modify frozen legacy `initializeLimitedService()` in any Create Offer Listing Blade.
- **Do NOT** create a second condition/ownership field where one exists — extend, don't duplicate (Principle 7).
- **Do NOT** let any downstream consumer read the raw BYO meta key or the raw Stellar field — it must read the canonical field (V2.0).
- **Do NOT** consider a field "done" because BYO forms pass — it is incomplete until canonical + Stellar + future-source mapping and the rest of the 21-stage lifecycle are verified (V2.0).

---

# Phase 2 — High-Value Optional MLS Fields

**Goal:** Implement the optional Stellar fields that materially improve AI, matching, search, marketing, and DNA — turning BYO's differentiation into searchable, matchable intelligence. **Master Comparison §2 (55 enumerated optional-missing concepts) is the source of truth.**

**Source documents:** `bid-your-offer-stellar-master-field-comparison.md` §2 + §5.3; `bid-your-offer-stellar-gap-analysis.md` §8 Top 100 (Tiers HIGH/MEDIUM) + Waves 1–7; `beyond-mls-property-dna-roadmap.md` (which fields "become" which intelligence).

**Scope — recommended optional fields (each with Priority · Reason · Search · Public · Ask AI · DNA · Metadata tags), grouped as the user listed:**

- **Schools (Elementary/Middle/High)** — High · #1 residential driver · Search Y · Public Y · AI Y · Location+Buyer DNA · `school_zone_rated`, `top_school_district`, `family_oriented`.
- **Architectural Style** — High · property personality/marketing · Search Y · Public Y · AI Y · Property DNA · `architectural_coastal/key_west/mediterranean/modern/traditional`.
- **Accessibility Features (24-value)** — High · underserved audience · Search Y · Public Y · AI Y · Property/Buyer/Tenant DNA · `wheelchair_accessible`, `single_level`, `aging_in_place`.
- **Community Features** — High · lifestyle/target-audience · Search Y · Public Y · AI Y · all DNA · `gated`, `golf_community`, `dog_friendly`, `walkable`, `active_adult`.
- **ADU / In-Law Suite details** — High · multigen/income/remote-work · Search Y · Public Y · AI Y · Property DNA · `multigenerational`, `has_adu`, `income_potential` (Y/N flag was Phase 1; details here).
- **Additional Rooms details** — High · home-office/lifestyle rooms · Search Y · Public Y · AI Y · Property DNA · `home_office`, `media_room`, `flex_space`, `florida_room`.
- **Disaster Mitigation** — High · FL insurability/resilience · Search Y · Public Y · AI Y · Property+Location DNA · `hurricane_hardened`, `impact_windows`, `above_flood_plain`, `insurance_friendly`.
- **Solar Panel Ownership** — High · materially changes deal · Search Y · Public Y · AI Y · Property DNA · `solar_owned`, `energy_efficient`, `low_utility_cost`.
- **Solar Lease / Finance Terms** — High · diligence/assumability · Public Y · AI Y · Property DNA · `assumable_solar`.
- **Patio / Porch Features** — Medium · outdoor-living · Search Y · Public Y · AI Y · Property DNA · `screened_lanai`, `outdoor_living`.
- **Condo floor / building / elevator fields** (Floor #, Total Floors, Elevator, Corner/End/Penthouse/High-Mid-Rise/Stilt/Walk-Up) — Medium · condo buyer filters · Search Y · Public Y · AI Y · `elevator`, `high_floor`, `penthouse`, `corner_unit`.
- **Window Features** — Medium · impact/insurance/energy · Search Y · Public Y · AI Y · `impact_windows`, `energy_efficient`.
- **Fencing** — Medium · extend to Seller/Buyer residential · Search Y · Public Y · `fenced_yard`.
- **Security Features** — Medium · extend to Seller/Buyer residential · Search Y · Public Y · `secure_home`.
- **Spa Features** — Medium · extend/complete pool block · Search Y · Public Y · `spa_hot_tub`.
- **Existing Lease Details** (monthly rent + lease end + notice) — Medium · investor buyers of tenant-occupied · Public Y · AI Y · `tenant_occupied`.
- **Individually Metered Utilities** (per-utility) — High · investor value driver · Search Y · Public Y · AI Y · Investor DNA · `separately_metered`, `low_owner_expense`.
- **Gross Scheduled Income** — Medium · actual vs pro-forma · Public Y · AI Y · Investor DNA · `value_add`.
- **Estimated Market Income** — Medium · upside · Public Y · AI Y · Investor DNA · `upside_potential`.
- **Financial Source** (Accountant/Broker/Owner/Tax Return) — Medium · credibility · Public Y · AI Y · `financials_verified`.
- **Total Monthly Rent / Expenses** (aggregate) — Medium · DSCR/cash-flow read · Search Y · Public Y · AI Y · `cash_flowing`.
- **Structured Tenant Pays** (income) — Medium · NOI driver · Public Y · AI Y · `tenant_pays_utilities`.
- **Commercial Loading / Dock Configuration** (bays grade/dock-high/dock-well, door H×W, truck doors, high bays, clear span, columns) — High · industrial matching · Search Y · Public Y · AI Y · Commercial DNA · `dock_high`, `drive_in`, `clear_span`, `industrial_ready`.
- **Number of Tenants** (Single/Multi/Vacant) — High · investment-grade matching · Search Y · Public Y · AI Y · `single_tenant_nnn`, `multi_tenant`.
- **Anchor / Co-Tenant** — High · drives value · Public Y · AI Y · `anchored`.
- **Vacancy Rate** — Medium · underwriting · Search Y · Public Y · AI Y · `stabilized`, `value_add`.
- **NOI Type** (Actual vs Projected) — Medium · cap-rate trust · Public Y · AI Y · `actual_noi`, `proforma`.
- **Total Parking Spaces** (+ ratio) — Medium · retail/office viability · Search Y · Public Y · AI Y · `ample_parking`, `parking_ratio`.
- **Signage** (Pole/On-Building/Directory/Street) — Medium · retail visibility · Search Y · Public Y · AI Y · `pole_sign`, `high_visibility`.
- **Adjoining Property** — Medium · use-compatibility context · Public Y · AI Y · Location DNA · `commercial_corridor`, `anchor_adjacent`.
- **Restrooms / Offices / Conference Rooms** (extend to sale side) — Medium · office/hospitality fit · Search Y · Public Y · AI Y · `turnkey_office`, `built_out`.
- **Seasonal Rental block** (Seasonal Rent, Off-Season Rent, Weeks/Months-Available calendar) — High · huge FL segment · Search Y · Public Y · AI Y · Rental DNA · `seasonal`, `snowbird`, `vacation_rental`, `annual` (adopt **with** the Phase 1 Long-Term flag).
- **Association Fees for Tenants** (approval/security/parking/other + frequency) — Medium · true move-in cost · Search Y · Public Y · AI Y · `hoa_approval_required`, `move_in_cost`; plus **Assoc Approval Required + process/timeframe** · `association_approval`.
- **Tenant Pays utilities** (responsibility list) — Medium · cost of occupancy · Search Y · Public Y · AI Y · `tenant_pays_utilities`.
- **Vacant Land topography / wetlands / conservation / cleared-wooded** (Lot Features) — High · buildability/valuation · Search Y · Public Y · AI Y · Land DNA · `level_lot`, `wetlands`, `cleared`, `wooded`.
- **Future Land Use** (+ Zoning Compatible Y/N) — Medium · development potential · Search Y · Public Y · AI Y · `development_potential`, `rezoning_candidate`.
- **AG Exemption** (+ Farm Type) — Medium · tax/use + farm audience · Search Y · Public Y · AI Y · `agricultural`, `ag_exemption`, `equestrian`.
- **Business Non-Compete and Seller Training** (structure the free text: Y/N + term/period) — Low/Medium · buyer screening terms · Public Y · AI Y · `training_provided`, `non_compete`.
- **Structured Buyer-side Vacant-Land criteria** (zoning intent, utilities-required, buildable, min-acreage, road access) — High · **biggest criteria-side gap; blocks land matching** · Search Y · AI Y · Buyer DNA · `land_use_intent`, `utilities_required`, `buildable_only`.

**Additional backlog (adopt opportunistically, low priority):** Green Building Certifications; Indoor Air Quality; Condo Fee tiers; Additional Applicant Fee; Primary Bed Size; Pet Restrictions Source; Freezer/Freestanding/Converted-Residence; Space Class on sale side; Management Type; Hours/Days of Operation; PUD + Additional Parcels/assemblage; Equestrian barn/paddock/stalls; GRM (derived, not stored).

**Recommended sequencing within Phase 2 (from Gap Analysis Waves 1–7):** Wave 1 residential DNA/lifestyle (Schools, Front Exposure already in P1, Architectural Style, Accessibility, In-Law/ADU details, Additional Rooms, Community Features) → Wave 2 FL resilience/energy (Disaster Mitigation, Solar, Windows, Patio/Porch, Spa) → Wave 3 commercial economics (Loading/Dock, Tenants/Anchor, Parking, Signage, Restrooms/Offices, Space Class) → Wave 4 income/investor (Individually Metered, Gross Scheduled/Market Income, Financial Source, Total Monthly, NOI Type, Vacancy) → Wave 5 rental depth (Seasonal block, Association Fees, Tenant Pays) → Wave 6 land depth (Lot Features, Future Land Use, AG, Buyer land criteria) → Wave 7 condo/ownership depth + extend Fencing/Security/Floor-Covering + backlog.

**Tasks:** Same **full-lifecycle build steps as Phase 1** — the 21-stage definition of done — applied per field above, in the recommended wave order. That includes, for each field: config + canonical allowed-values → Livewire+validation → Blade mirroring existing UI → listing↔criteria mirror → save/edit/display → **canonical mapping + Stellar mapping + future-source slots** → derived metadata tags → DNA feeds → Ask AI registry → search facets → matching → recommendations → marketing → analytics → tests → docs. Fold in the Gap-Analysis BYO-internal cleanups where touched: `condition_prop` vs `condition_prop_buyer`, always-empty `pet_policy` meta, `video_tour_url`/`virtual_tour_url` label inversion — and note that these cleanups are **canonical-name reconciliations** (Principle 7): collapse the duplicates to one canonical field in the mapping layer.

> **Version 2.0 — multi-source note.** Several Phase 2 fields are *natively multi-source*: Schools and neighborhood data can arrive from Stellar's named-school fields **or** be derived by Location DNA (Census/TIGER); rent/AVM comps can arrive from RentCast; tax/hazard/valuation data from ATTOM or public records. Author each such field's canonical mapping to accept **all** applicable sources with a documented precedence (e.g. Stellar-named school vs Location-DNA-derived school-zone), so the canonical value is consistent regardless of ingestion path.

**Deliverable:** BYO **exceeds** MLS data quality for consumer-facing search, AI, and listing intelligence — **and every optional field is mapped into the Canonical Data Model** for BYO, Stellar, and future sources.

**Success criteria:**
- All 55 §2 optional-missing concepts implemented (except any explicitly deferred backlog items, which are logged, not silently dropped — Principle: no silent caps).
- Buyer-side Vacant Land criteria now expressible structurally.
- Commercial listings carry structured loading/tenancy/parking/signage data.
- Every added field feeds at least one of Search / Matching / Ask AI / DNA / Marketing (Principle 1 & 5), **via its canonical field**.
- **Every added field has a canonical name and BYO + Stellar mappings, with future-source slots defined or N/A'd, and provenance/confidence tracked.**
- Full-lifecycle definition of done (21 stages) satisfied — or explicitly N/A'd — for every field.

**Dependencies:** Phase 0. Phase 1 recommended first (Phase 2 details like In-Law/ADU detail and Seasonal block build on Phase 1 flags), but Phase 2 waves are otherwise independent of each other.

**Verification checklist:**
- [ ] All §2 concepts implemented or explicitly logged as deferred backlog.
- [ ] Schools wired into both a named field and Location DNA.
- [ ] Seasonal block adopted together with the Long-Term flag.
- [ ] Buyer Vacant Land criteria structured (not free-text KB).
- [ ] Commercial loading/dock + tenancy fields present on both Sale and Lease.
- [ ] Derived tags generated at save/DNA time, not hand-entered.
- [ ] **Every field has canonical + BYO + Stellar mappings and future-source slots (V2.0).**
- [ ] **Multi-source fields (Schools, comps, tax/hazard) accept all applicable sources with documented precedence (V2.0).**
- [ ] **Duplicate cleanups reconciled to a single canonical field (V2.0).**

**Do-not-do warnings:**
- **Do NOT** add the "Do Not Add" §2 item (Room Dimensions grid) or any §3 administrative field.
- **Do NOT** adopt RentSpree — BYO's own pre-screening is richer (§4).
- **Do NOT** regress any §4 "BYO is better" capability while adding parity fields.
- **Do NOT** collect free text where a structured control fits (Principle 4).
- **Do NOT** point any consumer at a raw source field — read the canonical field (V2.0).
- **Do NOT** hardcode a single source's option list into a consumer — normalize to canonical allowed-values first (V2.0).

---

# Phase 3 — Canonical Data Dictionary

**Goal:** Produce one authoritative, **source-neutral** data dictionary defining every **canonical field** in the Canonical Data Model, so all later intelligence phases reference canonical names — and so Phase 3.5 has a fixed canonical vocabulary to map every source into. This is the single source of truth for the V1.0 data model, independent of whether a value arrives from BYO, Stellar, RentCast, ATTOM, public records, CSV, or a future API.

> **Version 2.0 note.** The dictionary now defines **canonical fields**, not "BYO fields." A canonical field is the abstract concept (e.g. `ownership_type`, `lot_size_sqft`, `green_energy_generation`); the concrete BYO meta key and the concrete Stellar field are *sources* that Phase 3.5 maps into it. This phase establishes the canonical names and allowed-values; Phase 3.5 establishes the per-source translations.

**Source documents:** `bid-your-offer-field-audit.md` (existing fields + wiring), Master Comparison + Gap Analysis (added fields), `beyond-mls-property-dna-roadmap.md` (which fields "become" which intelligence).

**Create or update:** `docs/master-data-dictionary.md`

**Tasks:** For **every** canonical field (existing + Phase 1 + Phase 2), record one row with:
- Canonical field name · Display label · Flow(s) · Property type(s) · Input type · **Canonical allowed values (normalized vocabulary)** · Required status (DB/UI/No) · Storage location (native column vs `{role}_agent_auction_metas` meta_key) · Source-type (Objective/Subjective/AI-Generated/Derived/Agent/Seller/Buyer/Landlord/Tenant-entered) · **Contributing data sources (BYO / Stellar / RentCast / ATTOM / public records / CSV / future — enumerate which can populate it)** · **Provenance + confidence policy** · Public display · Search filter · Ask AI · Matching · Property DNA · Buyer DNA · Tenant DNA · Location DNA · Metadata tags generated · Notes.

Additional tasks:
1. Reconcile every duplicate/aliased name (e.g. `condition_prop` vs `condition_prop_buyer`) to a single canonical name; note aliases (per-source) in Notes. Aliases become *source mappings* in Phase 3.5, not separate canonical fields.
2. Flag every field's "purpose" per Principle 5 (Search/Matching/Ask AI/DNA/Marketing/Analytics/Compliance) — any field with no purpose is a bug to remove, not document.
3. Cross-link each field to the metadata tags it generates (forward reference to Phase 4) and the DNA scores it feeds (Phases 5–8).
4. **Freeze the canonical field names** used downstream. Phase 3.5 and Phases 4–14 must reference exactly these names.

**Deliverable:** `docs/master-data-dictionary.md` — one canonical, source-neutral source of truth for every field in the data model. **Feeds directly into Phase 3.5.**

**Success criteria:**
- Every field from the audit + Phases 1–2 has exactly one canonical row.
- No two rows describe the same concept under different names (Principle 7); per-source aliases are noted, not duplicated.
- Every row declares at least one purpose (Principle 5).
- Storage location is exact (native vs meta_key) for each field.
- **Every row lists which data sources can populate it and the provenance/confidence policy (V2.0).**
- **Canonical field names are frozen and ready for Phase 3.5 mapping (V2.0).**

**Dependencies:** Phases 1 & 2 complete (dictionary must describe the finished field set).

**Verification checklist:**
- [ ] Row count ≥ (audited fields + Phase 1 additions + Phase 2 additions).
- [ ] No duplicate concepts.
- [ ] Every row: storage location, required status, source, and ≥1 purpose populated.
- [ ] DNA/tag columns forward-reference Phases 4–8 consistently.
- [ ] **Every row lists contributing data sources + provenance/confidence policy (V2.0).**
- [ ] **Canonical field names frozen for Phase 3.5 + downstream (V2.0).**

**Do-not-do warnings:**
- **Do NOT** document fields that don't exist in code — the dictionary describes the built model, not aspirations (aspirations live in Phases 4–14).
- **Do NOT** invent new field names here; canonicalize existing ones.
- **Do NOT** create separate canonical rows for the same concept in different sources — one canonical field, many source mappings (V2.0).

---

# Phase 3.5 — Canonical Field Mapping

**(New in Version 2.0.)**

**Goal:** Build the translation/normalization layer between **every supported data source** and the Canonical Data Dictionary (Phase 3). After this phase, any source — BYO forms, Stellar MLS, future MLS providers, RentCast, ATTOM, public records, CSV imports, future APIs — can populate canonical fields with normalized values, provenance, and confidence, and every downstream consumer (Phases 4–14) reads canonical fields without knowing or caring which source supplied them.

**Why this phase exists.** It is the architectural keystone of Version 2.0. Without it, each intelligence layer would need per-source branching (BYO logic vs Stellar logic vs ATTOM logic), which is exactly the "separate implementation paths" V2.0 abolishes. With it, onboarding a new source is a mapping-only change and touches no downstream code.

**Source documents:** `docs/master-data-dictionary.md` (Phase 3 — the canonical vocabulary + frozen names); `bid-your-offer-stellar-master-field-comparison.md` (Stellar field names, options, required/optional, do-not-add §3); `bid-your-offer-field-audit.md` (BYO meta keys, storage model); external source schemas (Stellar/RESO, RentCast, ATTOM, public-records, CSV templates) as they are onboarded.

**Create:** `docs/canonical-field-mapping-spec.md`

**Tasks:** For **every important field**, author one mapping row with:
- **Canonical Field** — the frozen canonical name from Phase 3.
- **BYO Field(s)** — the concrete BYO Livewire property / meta key(s), per role/flow.
- **Stellar MLS Field(s)** — the concrete Stellar form field(s) that map in.
- **Future Source Mapping** — slots for future MLS providers, RentCast, ATTOM, public records, CSV, and future APIs (populate where known; mark N/A or "TBD-on-onboarding" otherwise, but the slot must exist so a new source needs no schema change).
- **Allowed Values** — the raw per-source value sets (e.g. Stellar's ownership enum vs BYO's dropdown vs an ATTOM code list).
- **Normalized Values** — the single canonical value set every source is normalized into (with the per-source value→canonical-value crosswalk).
- **Metadata Generated** — which tags this canonical field produces (links to Phase 4).
- **Property DNA Usage** — which Property DNA scores consume it (Phase 5).
- **Buyer DNA Usage** — which Buyer DNA dimensions consume it (Phase 6).
- **Tenant DNA Usage** — which Tenant DNA dimensions consume it (Phase 7).
- **Location DNA Usage** — how Location DNA consumes/derives it (Phase 8).
- **Search Usage** — the search facet it powers (Phase 12/13 search).
- **Ask AI Usage** — the Ask AI registry entry it backs (Phase 12).
- **Matching Usage** — the Matching V2 dimension/gate it feeds (Phase 13).
- **Marketing Usage** — the marketing outputs it drives (Phase 11).
- **Confidence Level** — default confidence by source (e.g. BYO-entered vs Stellar-imported vs ATTOM-derived), and the precedence policy when multiple sources disagree.
- **Notes** — normalization caveats, unit conversions (e.g. acres↔sqft), missing-value behavior, and do-not-map warnings (never map Stellar §3 admin fields into consumer canonical fields).

Additional tasks:
1. **Define the normalization functions** (value crosswalks + unit conversions + casing/whitespace rules) per canonical field, shared across sources.
2. **Define the source-precedence / conflict-resolution policy** (e.g. explicit BYO-entered value overrides an imported one; ATTOM tax data overrides stale public-records; most-recent-wins vs most-authoritative-wins) — documented, never silent.
3. **Define provenance recording** — each canonical value stores which source(s) supplied it and when (`provenance`, `last_refreshed`), consistent with the Location DNA `confidence`/`provenance`/`last_refreshed`/`human_corroborated` model.
4. **Define the ingestion contract** for each source type: BYO save-time write, Stellar/MLS import job, RentCast/ATTOM API pull, public-records sync, CSV importer — each produces canonical values through the same mapping.
5. **Reconcile Phase 3 aliases** (the duplicate cleanups) as BYO source mappings pointing at one canonical field.
6. **Explicitly exclude** every Stellar §3 admin field and all PII from mapping into consumer canonical fields (they have no canonical home).
7. **Verify round-trip parity**: a listing entered in BYO and the "same" listing imported from Stellar must resolve to identical canonical values (option normalization proven by test fixtures).

**Deliverable:** `docs/canonical-field-mapping-spec.md` — the authoritative translation layer mapping all supported and future sources into the Canonical Data Model.

**Success criteria:**
- Every canonical field (Phase 3) that has a BYO and/or Stellar source has a complete mapping row.
- Every mapping row defines normalized canonical values + a per-source crosswalk.
- Future-source slots (future MLS / RentCast / ATTOM / public records / CSV / future API) exist for every row (populated or N/A).
- Source-precedence, provenance, and confidence policies are defined and consistent.
- A BYO-entered and a Stellar-imported version of the same field normalize to the same canonical value (proven by fixtures/tests).
- No Stellar §3 admin field or PII maps into any consumer canonical field.

**Dependencies:** Phase 3 (frozen canonical names). Phases 1 & 2 provide the BYO+Stellar field pairs; their mapping stubs are formalized here.

**Verification checklist:**
- [ ] Every canonical field has a mapping row (or a documented "no source yet").
- [ ] BYO Field(s) and Stellar MLS Field(s) columns populated for every applicable row.
- [ ] Future-source slots present for every row.
- [ ] Normalized-values crosswalk defined per row; unit conversions specified.
- [ ] Source-precedence + provenance + confidence policies documented.
- [ ] Round-trip parity fixtures pass (BYO vs Stellar → same canonical value).
- [ ] No §3 admin field / PII mapped into a consumer canonical field.
- [ ] Downstream-usage columns (Metadata/DNA/Search/Ask AI/Matching/Marketing) cross-reference Phases 4–14.

**Do-not-do warnings:**
- **Do NOT** let any consumer bypass this layer to read a raw source field.
- **Do NOT** map Stellar administrative/PII fields (§3) into consumer canonical fields.
- **Do NOT** resolve source conflicts silently — apply and log the documented precedence policy.
- **Do NOT** hardcode a source's option list downstream — normalize here, once.
- **Do NOT** create a new canonical field to accommodate a source quirk — extend the mapping/normalization instead (Principle 7 & 11).

---

# Phase 3.6 — Canonical Entity Dictionary

**(New in Version 2.2.)**

**Goal:** Define the source-neutral **object model** beneath the canonical *field* dictionary — the entities the platform reasons about and how they relate — so fields attach to entities, entity resolution works across sources, and the Knowledge Graph (Phase 3.9) has a schema.

**Source documents:** `docs/master-data-dictionary.md` (Phase 3, fields); `docs/canonical-field-mapping-spec.md` (Phase 3.5, sources); Field Audit (current tables/relationships).

**Create:** `docs/canonical-entity-dictionary.md`

**Tasks:** For each canonical entity, define: canonical entity name · description · identity/primary-key strategy · **entity-resolution keys** (how the same real-world entity is matched across BYO/Stellar/RentCast/ATTOM/public-records/CSV) · attributes (which canonical fields attach) · relationships to other entities · contributing sources · provenance/confidence · lifecycle/state. Entities to cover (at minimum): **Property, Listing, Unit, Parcel, Buyer, Tenant, Seller, Landlord, Agent, Neighborhood, Community, Media, Transaction, Market, Amenity, Business, School.**

1. Distinguish **Property** (the physical asset) from **Listing** (a source's offer/record of it) — critical for multi-MLS de-duplication (one Property, many Listings).
2. Define entity-resolution / duplicate-detection rules feeding the Architecture Requirement #7 and the import pipeline (Phase 14.7).
3. Map every Phase 3 canonical field to its owning entity.
4. Define the relationships that become Knowledge Graph edges (Phase 3.9), e.g. Listing→Property, Property→Neighborhood, Property→Community, Unit→Property, Buyer→SearchArea.

**Deliverable:** `docs/canonical-entity-dictionary.md` — the canonical object/relationship model.

**Success criteria:** Every canonical field attaches to exactly one owning entity; Property vs Listing separation explicit; entity-resolution keys defined for every multi-source entity; relationships enumerated for the graph schema.

**Dependencies:** Phase 3 (fields) & 3.5 (source mappings).

**Verification checklist:**
- [ ] All listed entities defined with identity + resolution keys.
- [ ] Property↔Listing separation modeled (one Property, many source Listings).
- [ ] Every canonical field assigned an owning entity.
- [ ] Relationships enumerated (feed Phase 3.9 graph schema).

**Do-not-do warnings:** Do NOT model source-specific records as separate canonical entities — resolve them to one entity with source provenance. Do NOT introduce PII-only entities that violate the §3 do-not-add / privacy rules.

---

# Phase 3.7 — Master Intelligence Taxonomy

**(New in Version 2.2.)**

**Goal:** Convert the scattered intelligence vocabularies (Appendices C–J and the DNA/audience/tag lists) into **one governed, versioned, namespaced taxonomy** that every engine (Metadata, DNA, Audiences, Stories, Marketing, Matching) binds to — eliminating divergent tag spellings and making the vocabulary a first-class, evolvable asset.

**Source documents:** Appendices C–J of this roadmap; `beyond-mls-property-dna-roadmap.md` §8/§10; `bid-your-offer-stellar-gap-analysis.md` §9; `docs/metadata-mapping-spec.md`.

**Create:** `docs/master-intelligence-taxonomy.md`

**Tasks:** Define one governed taxonomy with **namespaces**: `lifestyle:*`, `luxury:*`, `audience:*`, `personality:*` (property), `community_personality:*`, `neighborhood_personality:*`, `motivation_buyer:*`, `motivation_tenant:*`, `investment:*`, `commercial:*`, `marketing:*`, `architecture:*`, `amenity:*`, `feature:*`, `location_theme:*`, `story:*`. For each term: canonical slug · display label · definition · namespace · which DNA/engine consumes it · deriving canonical fields (link to Phase 4) · symmetric? (property↔demand) · status (active/deprecated) · version introduced.

1. Define **governance**: who owns the taxonomy, how terms are proposed/approved, and the review cadence.
2. Define **versioning**: semantic version of the taxonomy; every DNA/metadata output records the taxonomy version it was generated against (ties to Architecture Requirement #9).
3. Define **naming conventions**: lowercase snake/kebab per namespace, no synonyms — one canonical slug per concept (Principle 7).
4. Define **lifecycle**: how a term is added, deprecated, merged, or split without breaking historical data.
5. Seed the taxonomy from Appendices C–J (they become the initial v1 vocabulary) and add the missing named vocabularies: **Architecture styles, Amenities, Property Features, Community Personality, Neighborhood Personality, Location Themes, Story types.**

**Deliverable:** `docs/master-intelligence-taxonomy.md` — the single governed intelligence vocabulary.

**Success criteria:** One namespaced slug per concept; every Appendix C–J term present; governance/versioning/naming/lifecycle/ownership defined; all downstream engines reference this taxonomy, not ad-hoc strings.

**Dependencies:** Phases 3, 3.5, 3.6; consumes Appendices C–J.

**Verification checklist:**
- [ ] All namespaces defined and seeded from Appendices C–J.
- [ ] Governance, versioning, naming, lifecycle, ownership documented.
- [ ] Newly required vocabularies (architecture/amenities/features/community & neighborhood personality/location themes/story types) added.
- [ ] No synonym/duplicate slugs (Principle 7).

**Do-not-do warnings:** Do NOT let engines invent tags outside this taxonomy. Do NOT hard-delete deprecated terms — deprecate with versioning so historical scores remain interpretable.

---

# Phase 3.8 — AI Automation & Data Quality

**(New in Version 2.2.)**

**Goal:** Turn raw ingested data (especially imported/MLS/external) into clean, enriched, trustworthy canonical data automatically — so no manual tagging or cleanup is required after import, and downstream intelligence is never built on contradictory or unvalidated inputs.

**Source documents:** `docs/canonical-field-mapping-spec.md` (normalization + precedence); `docs/canonical-entity-dictionary.md` (entity resolution); `docs/master-intelligence-taxonomy.md`; Field Audit (Ask AI/NLP subsystems already present).

**Create:** `docs/ai-automation-spec.md`

**Tasks:** Specify each automation as a versioned, asynchronous, cacheable job producing canonical values/metadata with provenance + confidence:
1. **AI data validation** — range/type/plausibility checks on canonical values (e.g. sqft, price, year built).
2. **Cross-source contradiction detection** — when BYO/Stellar/ATTOM/RentCast/public-records disagree on the same canonical field, **detect and surface** the conflict (Phase 3.5 decides which wins; this flags that they differ) with a confidence penalty.
3. **AI description analysis (NLP)** — extract structured signals from free-text (MLS Public Remarks, BYO `additional_details`) into canonical fields/metadata.
4. **AI metadata extraction & inferred metadata** — derive taxonomy tags the source didn't explicitly provide (feeds Phase 4).
5. **AI completeness scoring** — per-entity completeness against the canonical dictionary (feeds Phases 10 & 14).
6. **AI missing-field recommendations** — suggest which high-value canonical fields to fill (consumer/agent facing).
7. **Future AI image understanding** — vision-derived tags (e.g. `chef-kitchen`, `designer-finishes`, condition) the taxonomy already presumes; defined now as a pluggable stage even if enabled later.
8. **AI quality assurance** — confidence scoring + human-review routing for low-confidence AI outputs; fact-vs-inference labeling (ties to Phase 14.5).

**Deliverable:** `docs/ai-automation-spec.md`.

**Success criteria:** Every automation produces canonical outputs with provenance + confidence + version; contradiction detection surfaces (not just resolves) conflicts; NLP + inferred metadata feed Phase 4; completeness feeds Phases 10/14; image-understanding defined as a future-ready stage; no manual tagging required post-import.

**Dependencies:** Phases 3, 3.5, 3.6, 3.7.

**Verification checklist:**
- [ ] Validation, contradiction detection, NLP, metadata extraction, completeness, missing-field recs, image-understanding stage, QA all specified.
- [ ] Outputs carry provenance + confidence + taxonomy/model version.
- [ ] Contradiction detection flags cross-source disagreements distinctly from precedence resolution.
- [ ] Fact-vs-inference labeling defined (feeds Phase 14.5).

**Do-not-do warnings:** Do NOT let AI-inferred values silently overwrite source-of-record values — store as inferences with confidence and respect precedence. Do NOT present inferences as facts (Phase 14.5). Do NOT run AI generation on request paths — async only (Architecture Requirement #8).

---

# Phase 3.9 — Universal Real Estate Knowledge Graph

**(New in Version 2.2.)**

**Goal:** Define the **central intelligence layer** — a graph connecting every canonical entity and every intelligence output — that all consumers (Ask AI, Matching, Recommendations, Analytics) read from. This is the integration substrate that makes the platform's intelligence compound rather than fragment.

**Source documents:** `docs/canonical-entity-dictionary.md` (nodes/edges); `docs/master-intelligence-taxonomy.md` (tag nodes); all DNA/story/audience specs (Phases 4–9.5); Architecture & Scalability Requirements.

**Create:** `docs/knowledge-graph-spec.md`

**Tasks:** Define the graph model that connects: **Property · Location · Community · Neighborhood · Buyer · Tenant · Seller · Landlord · Agent · Lifestyle · Luxury · Investment · Commercial · Metadata · Stories · Target Audiences · Marketing · Recommendations · Ask AI · Matching · Analytics.**
1. **Node types** = canonical entities (Phase 3.6) + intelligence nodes (taxonomy terms, DNA profiles, stories, audiences).
2. **Edge types** = relationships (Listing→Property, Property→Neighborhood/Community, Property→Audience with fit score, Buyer→SearchArea, DNA→Property, Story→Property, etc.), each carrying weight + provenance + confidence.
3. **Population contract** — every downstream phase (4–9.5) **writes** its outputs into the graph as nodes/edges; specify what each contributes.
4. **Consumption contract** — Ask AI (12), Matching (13), Recommendations (10), Analytics (14) **read** the graph; specify the query patterns (traversal, similarity, explanation paths).
5. **Provenance/confidence on every edge** so explainability (Phase 14) can walk the graph to justify any answer.
6. Align with Architecture Requirements #1/#3/#4 (canonical read model, vector retrieval, event-driven regeneration).

**Deliverable:** `docs/knowledge-graph-spec.md` — the central intelligence-layer specification.

**Success criteria:** Node + edge types defined for every listed element; population contract per producing phase; consumption contract per consuming phase; every edge carries provenance + confidence; explanation paths defined for Phase 14.

**Dependencies:** Phases 3.6, 3.7 (schema); progressively populated by Phases 4–9.5; consumed by 10–14.

**Verification checklist:**
- [ ] All 21 listed elements represented as node/edge types.
- [ ] Producing phases (4–9.5) have a write contract; consuming phases (10–14) have a read contract.
- [ ] Every edge carries provenance + confidence.
- [ ] Explanation-path queries defined for Analytics/Explainability (Phase 14).

**Do-not-do warnings:** Do NOT let the graph become a second source of truth that drifts from canonical — it is derived from canonical fields/entities. Do NOT put PII or §3 admin data into consumer-visible graph paths.

---

# Phase 4 — Metadata Mapping Engine

**Goal:** Define, in one spec, how **canonical fields automatically generate** metadata tags — so users never hand-tag anything derivable (Principle 2), and so tags are identical no matter which source populated the canonical field.

> **Version 2.0 — canonical consumption.** The Metadata Engine reads **canonical fields only** (Phase 3 dictionary, resolved through Phase 3.5). It never references a raw BYO meta key or a raw Stellar field. A tag rule keyed on `dock` fires the same whether `dock` was BYO-entered, Stellar-imported, or ATTOM-derived.

**Source documents:** Gap Analysis §9 (Top 100 metadata/lifestyle tags), Master Comparison §2 "Suggested Metadata Tags" column, `beyond-mls-property-dna-roadmap.md` §8 ("Compute, Don't Ask") + §10.2 (Top 100 AI Metadata Tags) + §5 (Property Personality tag model), `docs/master-data-dictionary.md` (canonical field names), **`docs/canonical-field-mapping-spec.md` (how each source resolves to the canonical field a rule keys on)**.

**Create:** `docs/metadata-mapping-spec.md`

**Tasks:** For every important **canonical field**, define a mapping row:
- Canonical Field · Canonical value(s) that trigger tags · Generated metadata tags · DNA category · Applies to Seller · Applies to Buyer · Applies to Landlord · Applies to Tenant · Search facet · Marketing use · Ask AI use · Match-scoring use.

Include worked examples (verbatim from the brief, aligned to canonical fields):
- Dock = Yes → `boating`, `fishing`, `kayaking`, `waterfront_lifestyle`, `luxury_waterfront`
- Boat Lift = Yes → `boating`, `yacht_friendly`, `luxury_waterfront`
- Home Office = Yes → `remote_work`, `executive_home`, `hybrid_work`
- Pool + Outdoor Kitchen → `entertaining`, `resort_lifestyle`, `outdoor_living`
- ADU + Separate Entrance → `multigenerational`, `income_potential`
- Elevator + Single-Level → `accessibility`, `aging_in_place`
- Solar Owned → `energy_efficient`, `low_utility_cost`, `eco_friendly`

Also specify (from the Property DNA roadmap): each tag carries `confidence` (0–1), `evidence` (which fields/photos triggered it), `polarity` (for conflicting tags), and `human_confirmed` when an agent/owner confirms. Tags **rank**, they don't hard-gate search. Negative/exclusion tags exist for commercial (`no_drive_thru`, `no_food_service`).

**Important rule (state explicitly in the spec):** *Do not ask users to manually tag listings if the metadata can be derived from structured fields.* Tags are generated at save/DNA-generation time.

**Deliverable:** `docs/metadata-mapping-spec.md` — master metadata generation blueprint.

**Success criteria:**
- Every canonical field that can produce a tag has a mapping row.
- Every tag in Gap-Analysis §9 / roadmap §10.2 has ≥1 field that derives it (no orphan tags).
- Compound rules (field A + field B → tag) are captured.
- Each tag row declares Search/Marketing/Ask AI/Matching use and role applicability.

**Dependencies:** Phase 3 (canonical field names) **and Phase 3.5 (source→canonical resolution, so rules key on canonical values regardless of source).**

**Verification checklist:**
- [ ] No tag lacks a deriving field.
- [ ] No field that should tag is missing a rule.
- [ ] Compound/negative/confidence semantics documented.
- [ ] "Compute, don't ask" rule stated.
- [ ] **Every tag rule keys on a canonical field, not a raw source field (V2.0).**
- [ ] **Tags fire identically across sources (BYO/Stellar/imported), proven by fixtures (V2.0).**

**Do-not-do warnings:**
- **Do NOT** introduce a manual tag UI for anything derivable (Principle 2 & 9).
- **Do NOT** let tags hard-exclude search results — they rank.

---

# Phase 5 — Property DNA Engine

**Goal:** Specify how Property DNA **scores** are computed automatically from **canonical fields + metadata tags**.

> **Version 2.0 — canonical consumption.** Property DNA scores read **canonical fields** (Phase 3) resolved through Phase 3.5, never raw BYO or Stellar fields. A property scores identically whether its data was BYO-entered, Stellar-imported, RentCast/ATTOM-enriched, or CSV-loaded — provided the source mapping exists. Each score's inputs are cited as canonical field names.

**Source documents:** `beyond-mls-property-dna-roadmap.md` §8 (score list, scoring architecture) + §10.3 (Top 25 AI Scores, with symmetry column) + §5 (Property Personality / vibe embedding); `docs/metadata-mapping-spec.md`; `docs/master-data-dictionary.md`; **`docs/canonical-field-mapping-spec.md` (canonical inputs + confidence per source)**.

**Create:** `docs/property-dna-spec.md`

**Tasks:** For each score, define **Canonical-field inputs** · Weighting logic · Metadata tags used · Output format · Where it displays · How Ask AI uses it · How matching uses it. Factor per-input **source confidence** (Phase 3.5) into the score's own confidence value. Cover the score set (from roadmap §8, aligned to the brief):
Luxury · Family · Investor · Outdoor Living · Waterfront · Boating · Remote Work · Accessibility · Walkability · Wellness · Privacy · Entertainment · Low Maintenance · Vacation Home · Retirement — plus the roadmap's additional scores where valuable (School-Fit, Climate-Resilience/Insurability, Energy-Efficiency/Green, Turnkey/Move-In, Renovation-Upside, Smart-Home, Golf, Equestrian, Multigenerational-Fit, Snowbird-Fit, STR/Airbnb-Potential, Noise/Tranquility, Land-Buildability, Commercial-Fit-Out).

Per the roadmap's **scoring architecture**, each score is stored as: a 0–100 value + an **explanation string** (human-readable "why") + a **confidence value** (0–100) + a **version tag** (model + feature-set version), and **refreshes automatically when inputs change**. Include the worked example: *"Boating 92: saltwater canal, 60 ft frontage, private dock with lift, 1.2 mi to nearest inlet."*

**Deliverable:** `docs/property-dna-spec.md` — Property DNA scoring specification.

**Success criteria:**
- Every listed score has inputs, weights, tags, output format, display location, Ask AI use, and matching use.
- Every score emits an explanation string + confidence + version.
- Scores recompute on input change.
- Inputs trace back to canonical fields (Phase 3) / tags (Phase 4).

**Dependencies:** Phases 3, 3.5 & 4.

**Verification checklist:**
- [ ] All 15 brief scores + selected roadmap scores specified.
- [ ] Explanation/confidence/version defined for each.
- [ ] No score depends on a non-existent field/tag.
- [ ] **Every score input is a canonical field, not a raw source field (V2.0).**
- [ ] **Score value + confidence identical across sources for equivalent data (V2.0).**

**Do-not-do warnings:** Do NOT hardcode weights in views — put weights in config where the pattern already exists (mirrors `config/match_scoring.php` discipline: enabled weights sum to a defined total). Do NOT surface raw scores users can't interpret without the explanation string (Principle 9).

---

# Phase 6 — Buyer DNA Engine

**Goal:** Define how Buyer criteria become a Buyer DNA profile — the demand-side mirror of Property DNA — built on **canonical criteria fields**.

> **Version 2.0 — canonical consumption.** Buyer DNA reads **canonical buyer-criteria fields** (Phase 3) resolved through Phase 3.5. Buyer criteria today are BYO-entered, but the canonical model keeps the door open for imported buyer/tenant demand data from future sources; the DNA logic is unchanged either way. Buyer preference axes must be symmetric with the canonical Property DNA axes (Phase 5) so the matcher (Phase 13) compares them on identical canonical dimensions.

> **Version 2.2 — Location Preference DNA.** Buyer DNA includes a **Location Preference DNA** sub-profile generated from the buyer's map searches, drawn Search Areas, Important Places, and commute anchors — expressed in the **same canonical location vocabulary** as property-side Location DNA (Phase 8), so location intent matches location reality on identical axes. See Phase 8 for the shared vocabulary and the full Location Preference DNA spec; this phase owns the buyer-side capture (lifestyle/community/luxury/investment/commute preferences + Search Area & Important Place intelligence).

**Source documents:** `beyond-mls-property-dna-roadmap.md` §3 (Buyer/Tenant Intent) + §9 (Intent DNA, Financial DNA, Audience DNA) + §10.6 (Top 50 Motivations); Field Audit (Buyer flow fields, `purchase_purpose`, commute, search-areas, Important Places, flood/HOA tolerance); `docs/master-data-dictionary.md`; **`docs/canonical-field-mapping-spec.md`**; **`docs/property-dna-spec.md` (for axis symmetry)**.

**Create:** `docs/buyer-dna-spec.md`

**Tasks:** Define how each captured criterion maps into Buyer DNA dimensions and metadata tags:
- Lifestyle preferences · Property preferences · Location preferences · Commute preferences · Financing preferences · Investment intent · Must-haves · Deal breakers · Target-audience alignment · Metadata tags.

Use the roadmap's **symmetry principle**: every Property DNA axis (Phase 5) has a matching Buyer preference-weight on the same 0–100 axis, so matching (Phase 13) is a dot-product/weighted-distance between property score vector and buyer preference vector. Note that **`BuyerTenantDnaGenerator` is currently unwired for the Buyer Offer Listing flow** — wiring it is an implementation prerequisite flagged for Phase 13, and this spec must define what it should read.

**Deliverable:** `docs/buyer-dna-spec.md` — Buyer DNA scoring and profile specification.

**Success criteria:**
- Every Buyer criterion maps to ≥1 DNA dimension or tag.
- Must-haves and deal-breakers are modeled as gates vs. weights.
- Buyer axes are symmetric with Property DNA axes.

**Dependencies:** Phases 3, 3.5, 4, 5.

**Verification checklist:**
- [ ] Symmetry with Property DNA axes verified.
- [ ] Deal-breakers modeled as feasibility gates.
- [ ] Financial/Timeline gates defined.
- [ ] Notes the current unwired state of `BuyerTenantDnaGenerator` for offer listings.
- [ ] **Every Buyer DNA input is a canonical criteria field, not a raw source field (V2.0).**
- [ ] **Buyer axes are symmetric with canonical Property DNA axes (V2.0).**

**Do-not-do warnings:** Do NOT create Buyer axes that have no Property DNA counterpart (they'd never match). Do NOT ask buyers to self-score — infer from their criteria (Principle 9).

---

# Phase 7 — Tenant DNA Engine

**Goal:** Define how Tenant criteria become a Tenant DNA profile — built on **canonical criteria fields**.

> **Version 2.0 — canonical consumption.** Tenant DNA reads **canonical tenant-criteria fields** (Phase 3) resolved through Phase 3.5, and its axes must be symmetric with the canonical rental-listing/Property DNA axes (Phases 5/7) so matching compares identical canonical dimensions regardless of source.

> **Version 2.2 — Location Preference DNA.** Tenant DNA includes a **Location Preference DNA** sub-profile from map searches, Search Areas, Important Places, and commute anchors, in the **same canonical location vocabulary** as property-side Location DNA (Phase 8). This phase owns the tenant-side capture (lifestyle/community/commute/budget-location preferences + Search Area & Important Place intelligence); Phase 8 owns the shared vocabulary.

**Source documents:** `beyond-mls-property-dna-roadmap.md` §3 + §9 (Pet DNA, Timeline DNA); Field Audit (Tenant flow: `rental_purpose`, pre-screening self-disclosure, pet block, accessibility, smoking, occupants); `docs/master-data-dictionary.md`; **`docs/canonical-field-mapping-spec.md`**.

**Create:** `docs/tenant-dna-spec.md`

**Tasks:** Map each tenant criterion into Tenant DNA dimensions + tags:
- Lease preferences · Pet preferences · Commute needs · Lifestyle preferences · Work-from-home needs · Neighborhood preferences · Budget priorities · Move-in urgency · Must-haves · Deal breakers.

Mirror the rental listing side (Landlord) so Tenant DNA axes match Landlord/property axes (seasonal vs annual, pets, furnishings, association approval, tenant-pays). Model move-in urgency via Timeline DNA as a feasibility gate.

**Deliverable:** `docs/tenant-dna-spec.md` — Tenant DNA scoring and profile specification.

**Success criteria:**
- Every tenant criterion maps to a dimension/tag.
- Pet, budget, and timeline handled as first-class (gates where appropriate).
- Symmetric with the rental listing axes.

**Dependencies:** Phases 3, 3.5, 4, 5 (and consistency with Phase 6).

**Verification checklist:**
- [ ] Symmetry with rental listing/property axes.
- [ ] Move-in urgency = timeline gate.
- [ ] Pet DNA modeled from the existing rich pet block.
- [ ] **Every Tenant DNA input is a canonical criteria field, not a raw source field (V2.0).**

**Do-not-do warnings:** Do NOT regress BYO's ahead-of-Stellar tenant pre-screening. Do NOT duplicate accessibility/community modeling already defined for Property DNA — reference it.

---

# Phase 7.5 — Seller DNA & Landlord DNA Engines

**(New in Version 2.2.)**

**Goal:** Complete the actor-DNA set by promoting **Seller DNA** and **Landlord DNA** to first-class engines — the supply-side mirror of Buyer/Tenant DNA — so the platform models motivation, flexibility, and fit on the *listing* side too (feeding matching, recommendations, and marketing tone).

**Source documents:** Field Audit (Seller flow: `current_status`, sale terms, financing sub-forms, broker-compensation; Landlord flow: pre-screening, rent terms, association rules); `beyond-mls-property-dna-roadmap.md` §3/§9 (Relationship/Agent-Fit DNA, Deal DNA); `docs/master-data-dictionary.md`; `docs/master-intelligence-taxonomy.md`.

**Create:** `docs/seller-landlord-dna-spec.md`

**Tasks:** Define each engine on canonical fields, symmetric where a concept mirrors the demand side:
- **Seller DNA** — selling motivation (`current_status`: relocating/investor/first-time/under-contract…), urgency/timeline, price flexibility, financing openness (assumable/seller-financing/lease-option/crypto), contingency tolerance, ideal-buyer profile, deal structure (Deal DNA). Consumed by Matching (ideal-buyer↔buyer), Recommendations (pricing/positioning), Marketing tone, Seller Story (Phase 9.5).
- **Landlord DNA** — leasing motivation, tenant-screening strictness, pet/furnishing/lease-length flexibility, seasonal vs annual intent, association-approval friction, ideal-tenant profile. Consumed by Matching (ideal-tenant↔tenant), Recommendations, Landlord Story.
- Both carry explanation string + confidence + taxonomy version (per Phase 5 scoring architecture) and write to the Knowledge Graph (Phase 3.9).

**Deliverable:** `docs/seller-landlord-dna-spec.md`.

**Success criteria:** Seller & Landlord DNA specified as first-class engines on canonical fields; ideal-buyer/ideal-tenant profiles defined and symmetric with Buyer/Tenant DNA; explanation/confidence/version present; graph write contract defined.

**Dependencies:** Phases 3–3.9, 5, 6, 7.

**Verification checklist:**
- [ ] Seller DNA + Landlord DNA each specified with inputs, dimensions, outputs.
- [ ] Ideal-buyer / ideal-tenant profiles symmetric with Buyer/Tenant DNA.
- [ ] Explanation/confidence/version + graph write contract.
- [ ] Inputs are canonical fields only (Principle 12).

**Do-not-do warnings:** Do NOT expose seller/landlord *motivation* signals in ways that harm the client's negotiating position or leak PII (respect Phase 14.5). Do NOT duplicate Buyer/Tenant axes — mirror them.

---

# Phase 8 — Location DNA Expansion

**Goal:** Build the **Location Intelligence Engine** — a **first-class intelligence engine** (peer to the DNA engines) that transforms geographic, environmental, community, infrastructure, public, and user-defined location data into explainable intelligence. **Location DNA and Location Preference DNA are its outputs, not the engine itself.** Expand the existing Location DNA pipeline into this stronger, source-agnostic engine that both **consumes and produces canonical data**.

> **Version 2.4 — Location Intelligence Engine (first-class).** Clarified throughout the roadmap: wherever "Location DNA" appears, it is the *output* of this engine.
>
> **It consumes:** property location · Buyer/Tenant Search Areas · drawn polygons · radius searches · ZIP Codes · cities · counties · states · Important Places · commute preferences · MLS data · public datasets · future APIs · user-defined preferences.
>
> **It generates (all canonical, with provenance + confidence, written to the Knowledge Graph):** Location DNA · Lifestyle DNA inputs · Community DNA inputs · luxury signals · investment signals · commercial signals · target-audience signals · marketing signals · Story inputs (Location/Neighborhood/Community stories) · matching signals · recommendation signals · Ask AI knowledge · analytics.
>
> **Symmetric outputs:** property listings automatically generate **Property Location DNA**; Buyer/Tenant criteria automatically generate **Location Preference DNA** using the **same canonical location taxonomy and intelligence model** — so both are **directly comparable inside the Universal Matching Engine** (Phase 13). (Compute automatically — Principle 21; never infer protected characteristics in location stories — Principle 22.)

> **Version 2.0 — canonical consumption + production.** Location DNA is dual-natured in the Canonical Data Model: it **consumes** canonical geo fields (address/lat/lng/city/state) and **produces** canonical derived fields + neighborhood tags (school-zone, flood-risk, walkability, proximities). Its outputs are therefore registered as a **data source** in Phase 3.5 — a canonical field like school-zone may be filled by a Stellar named-school field **or** by Location DNA derivation, with the precedence policy deciding. External hazard/valuation feeds (ATTOM, public records) also map into the same canonical geo/hazard fields, so consumers never branch on origin.

> **Version 2.2 — Symmetric Location Intelligence.** Location DNA must do more than list nearby places. It has two symmetric sides sharing **one canonical location vocabulary** (governed in Phase 3.7, `location_theme:*` + neighborhood/community personality namespaces):
>
> **(a) Property Location DNA (supply side)** generates, per property: **location metadata**, **lifestyle metadata**, **Community Personality**, **Neighborhood Personality**, **luxury signals**, **investment signals**, **marketing signals**, **location-level target audiences**, and an **explainable Location Story** (handed to Phase 9.5). All carry provenance + confidence and write to the Knowledge Graph (Phase 3.9).
>
> **(b) Location Preference DNA (demand side)** is generated from a Buyer/Tenant's **map searches, drawn Search Areas, Important Places, and commute anchors** (owned for capture by Phases 6/7) and expressed in the **same canonical vocabulary**, producing: Location Preferences, Lifestyle Preferences, Community Preferences, Luxury Preferences, Investment Preferences, Commute Preferences, **Search Area Intelligence**, **Important Place Intelligence**, and (optionally) a demand-side Location Story.
>
> Because both sides use one vocabulary, Matching (Phase 13) scores location intent against location reality on identical axes — the core payoff of the Canonical Data Model applied to place.

**Version 2.2 additional tasks (fold into Phase 8):**
1. Emit Community Personality and Neighborhood Personality tags (taxonomy namespaces from Phase 3.7) with confidence/provenance.
2. Derive location-level luxury/investment/marketing signals and location-level target audiences (feed Phases 9, 10, 11).
3. Produce the property **Location Story** input for the Story Engine (Phase 9.5).
4. Specify **Location Preference DNA** generation from map/search-area/important-place/commute inputs in the same canonical vocabulary; define the property↔preference matching axes for Phase 13.

**Source documents:** `beyond-mls-property-dna-roadmap.md` §4 (Neighborhood Intelligence) + §10.9 (Top 50 Neighborhood Tags); CLAUDE.md (`LocationDnaPipelineRunner`, `ComputeLocationDna`, `GooglePlacesPoiAdapter`, FEMA, Census TIGER, `LocationDnaPoiTileCache`, `config/location_dna.php`); Field Audit (Location DNA current triggers); **`docs/canonical-field-mapping-spec.md` (Location DNA as a source; ATTOM/public-records hazard mappings)**.

**Create:** `docs/location-dna-expansion-spec.md`

**Tasks:** Specify expansion across:
Schools · Flood zones · FEMA risk · Transit · Walkability · Parks · Beaches · Marinas · Golf · Healthcare · Grocery · Dining · Shopping · Airports · Employment centers · Neighborhood demographics · Market trends · Future growth indicators.

For each: data source/feed, cache/refresh strategy (tile cache), the neighborhood tags it emits, `confidence`/`provenance`/`last_refreshed`/`human_corroborated` metadata (per roadmap §4), and which DNA/search/Ask AI consumers use it. Respect FEMA bounding-box limits in `config/location_dna.php`.

**Deliverable:** `docs/location-dna-expansion-spec.md` — Location DNA expansion roadmap.

**Success criteria:**
- Every listed dimension has a source, cache/refresh plan, emitted tags, and consumers.
- Every neighborhood field carries confidence + provenance + freshness.
- Integrates with Property/Buyer/Tenant DNA and search facets.

**Dependencies:** Phases 3, 3.5, 4 (tags); complements Phases 5–7.

**Verification checklist:**
- [ ] All 18 dimensions specified with source + refresh.
- [ ] Provenance/confidence/freshness on each.
- [ ] Boat-ramp/marina/inlet proximity feeds Boating Score (Phase 5).
- [ ] **Location DNA outputs registered as a canonical data source in Phase 3.5, with precedence vs Stellar/ATTOM (V2.0).**
- [ ] **Consumers read the canonical geo/hazard field, not the Location DNA output directly (V2.0).**

**Do-not-do warnings:** Do NOT exceed FEMA bounding-box thresholds. Do NOT store PII from demographic feeds. Do NOT block the async pipeline model — keep it queued (`ComputeLocationDna`).

---

# Phase 8.5 — Domain DNA Suite (Lifestyle / Luxury / Investment / Commercial / Community DNA)

**(New in Version 2.2.)**

**Goal:** Promote the domain intelligences that Version 2.0 folded into Property-DNA scores/tags into **first-class DNA engines**, each a coherent, symmetric, explainable profile. This completes the DNA suite and gives Matching, Recommendations, Marketing, and Stories dedicated domain vectors.

**Source documents:** `beyond-mls-property-dna-roadmap.md` §5–§9 (Lifestyle, Investment, Commercial, Community, Personality/Vibe); `docs/property-dna-spec.md` (Phase 5 scores it draws on); `docs/master-intelligence-taxonomy.md`; Appendices D (lifestyle), G (investment/commercial), and the luxury tags/scores.

**Create:** `docs/domain-dna-suite-spec.md`

**Tasks:** For each engine define inputs (canonical fields + Phase 5 scores + taxonomy terms), dimensions, output (vector + explanation + confidence + version), symmetric demand-side counterpart, graph write contract, and consumers:
- **Lifestyle DNA** — the lifestyle categories (Appendix D) as a scored profile; symmetric with Buyer/Tenant lifestyle preferences.
- **Luxury DNA** — luxury tier/signals (finishes, prestige, privacy, view, price-vs-comps, luxury location signals from Phase 8); symmetric with luxury buyer appetite; feeds Luxury Story + luxury marketing.
- **Investment DNA** — the investment tags + return metrics (Appendix G.1, cap rate/DSCR/GRM/cash-flow/appreciation); symmetric with investor mandate; feeds Investment Story + investor recommendations.
- **Commercial DNA** — use-fit + commercial tags (Appendix G.2, dock/clear-span/class/signage/parking/build-out); symmetric with commercial tenant/buyer use-fit; feeds commercial marketing/matching.
- **Community DNA** — community personality/amenity/social profile (pairs with Neighborhood Personality from Phase 8); symmetric with community preferences.

**Deliverable:** `docs/domain-dna-suite-spec.md`.

**Success criteria:** All five domain DNA engines specified as first-class, on canonical inputs + taxonomy terms; each has a symmetric demand-side counterpart, explanation/confidence/version, and a graph write contract; no divergent tag spellings (bound to Phase 3.7).

**Dependencies:** Phases 3.7, 3.9, 5, 6, 7, 7.5, 8.

**Verification checklist:**
- [ ] Lifestyle, Luxury, Investment, Commercial, Community DNA each specified with inputs/dimensions/outputs.
- [ ] Each has a symmetric demand-side counterpart on identical axes.
- [ ] Explanation/confidence/version + graph write contract.
- [ ] All terms bound to the Master Intelligence Taxonomy (Phase 3.7).

**Do-not-do warnings:** Do NOT duplicate Property DNA scores — these engines *compose* them into domain vectors, they don't re-derive them. Do NOT invent tags outside the taxonomy.

---

# Phase 9 — Target Audience Intelligence

**Goal:** Automatically predict the best target audiences for each listing.

> **Version 2.0 — canonical consumption.** Audience fit is derived from **canonical DNA scores + canonical metadata tags + Location DNA** (all built on canonical fields), never from raw source fields. An audience prediction is therefore identical for equivalent listings regardless of ingestion source.

**Source documents:** `beyond-mls-property-dna-roadmap.md` §2 (audiences) + §10.5 (Top 50 Target Audiences); Phases 4–8 (canonical tags + DNA scores that signal audiences); **`docs/canonical-field-mapping-spec.md`**.

**Create:** `docs/target-audience-intelligence-spec.md`

**Tasks:** For each audience define Required signals · Supporting signals · Negative signals · Score-calculation idea · Marketing use · Matching use · Ask AI use. Cover the brief's audiences (Growing Family, Luxury Buyer, Snowbird, Retiree, Empty Nester, Remote Worker, Executive, Investor, Boat Owner, Golfer, Equestrian Buyer, First-Time Buyer, Medical Professional, Military, College Student, Business Owner, Restaurant Operator, Warehouse/Logistics User, Developer, Builder) plus the roadmap's additional segments (1031 Exchange, House-Hacker, Fix-and-Flipper, Foreign/EB-5, Corporate Transferee, NNN Investor, Owner-Operator, etc.).

Each audience is a scored fit derived from Property DNA scores + metadata tags + Location DNA — never hand-assigned.

**Deliverable:** `docs/target-audience-intelligence-spec.md`.

**Success criteria:**
- Every audience has required/supporting/negative signals and a score idea.
- Signals reference existing scores/tags (Phases 4–8), not new inputs.
- Marketing/Matching/Ask AI use defined per audience.

**Dependencies:** Phases 3.5, 4–8.

**Verification checklist:**
- [ ] All brief audiences + roadmap additions covered.
- [ ] Negative signals present (so bad-fit audiences are suppressed).
- [ ] No audience needs manual tagging.
- [ ] **All audience signals reference canonical DNA scores/tags, not raw source fields (V2.0).**

**Do-not-do warnings:** Do NOT compute audiences from raw fields directly where a DNA score already abstracts them — reuse. Do NOT expose scoring internals to users without plain-language framing (Principle 9).

---

# Phase 9.5 — Story Engine

**(New in Version 2.2.)**

**Goal:** Generate rich, human, **grounded** narratives for every actor and dimension — the consumer-facing differentiator and a first-class input to Marketing, Ask AI, and Matching. Stories are assembled from structured data, canonical fields, metadata, the Master Intelligence Taxonomy, and DNA — **never from unsupported assumptions.**

**Source documents:** `beyond-mls-property-dna-roadmap.md` §5 (personality/vibe, evidence/confidence); all DNA specs (Phases 5–8.5); `docs/master-intelligence-taxonomy.md` (`story:*` namespace); `docs/knowledge-graph-spec.md`; Phase 8 (Location Story input); Phase 14.5 (fact-vs-inference + narrative guardrails).

**Create:** `docs/story-engine-spec.md`

**Tasks:** Specify generation of the twelve story types, each with: inputs (canonical fields + metadata + taxonomy + DNA it draws on) · structure/length · tone rules (audience/intent-tuned, per Phase 11) · **grounding rule** (every claim traces to a canonical value or a labeled inference with confidence) · where it surfaces · graph write contract:
- **Property Story · Location Story · Lifestyle Story · Community Story · Neighborhood Story · Luxury Story · Investment Story · Buyer Story · Tenant Story · Seller Story · Landlord Story · Marketing Story.**
1. Every story records **provenance** and separates **fact vs. AI inference** (Phase 14.5); low-confidence claims are hedged or omitted.
2. Stories are **regenerated event-drivenly** when their source canonical values/DNA change (Architecture Requirement #4), versioned against the taxonomy/model.
3. Stories feed Marketing (Phase 11), Ask AI (Phase 12), and are available as matching/marketing context; they write to the Knowledge Graph as Story nodes linked to their subject entity.

**Deliverable:** `docs/story-engine-spec.md`.

**Success criteria:** All 12 story types specified with grounded inputs, tone rules, grounding/fact-vs-inference rule, surfaces, and graph write contract; no story can assert a fact not present in canonical data or labeled as an inference; stories regenerate on source change.

**Dependencies:** Phases 3.7, 3.9, 5–8.5, 9; governed by Phase 14.5.

**Verification checklist:**
- [ ] All 12 story types specified.
- [ ] Grounding rule enforced (fact vs inference, provenance, confidence).
- [ ] Stories consume DNA + taxonomy + canonical fields only.
- [ ] Event-driven regeneration + versioning defined; graph write contract present.

**Do-not-do warnings:** Do NOT let stories invent features, demographics, or protected-class framing (Phase 14.5). Do NOT generate a story from a single source without respecting provenance/confidence. Do NOT hardcode narrative templates that bypass the taxonomy.

---

# Phase 10 — Recommendation Engine

**Goal:** Define the recommendations the platform generates from **canonical fields + canonical tags + DNA + audiences**.

> **Version 2.0 — canonical consumption.** Recommendations read canonical data only. Critically, **listing-completeness and missing-data recommendations are computed against the canonical dictionary (Phase 3) and mapping (Phase 3.5)** — a field is "missing" only if no source has populated its canonical value, so an imported Stellar listing missing a canonical field gets the same "add this" recommendation a sparse BYO listing would.

> **Version 2.2 — consumes the full intelligence stack.** Recommendations now read the **Knowledge Graph** (Phase 3.9) and draw on the complete DNA suite (Property/Buyer/Tenant/Seller/Landlord/Location + Lifestyle/Luxury/Investment/Commercial/Community), target audiences (Phase 9), and **stories** (Phase 9.5). Best-buyer/best-tenant recs use Seller/Landlord ideal-profile DNA (Phase 7.5); completeness recs draw on AI completeness scoring (Phase 3.8). All recommendations are explainable via graph paths (Phase 14) and respect compliance guardrails (Phase 14.5).

**Source documents:** `beyond-mls-property-dna-roadmap.md` (§2/§5 vibe embedding, §7 investor-fit, §10.6 motivations); Phases 4–9 specs; **`docs/master-data-dictionary.md` + `docs/canonical-field-mapping-spec.md` (completeness/missing-data basis)**.

**Create:** `docs/recommendation-engine-spec.md`

**Tasks:** Specify each recommendation type: Best buyer matches · Best tenant matches · Best target audiences · Best marketing angles · Strongest selling points · Weaknesses/objections · Improvements that increase demand · Pricing/positioning recommendations · Listing completeness recommendations · Missing-data recommendations. For each: inputs, output format, where it surfaces (agent dashboard / listing / Ask AI), and how it uses the vibe embedding + score vectors.

**Deliverable:** `docs/recommendation-engine-spec.md`.

**Success criteria:**
- Each recommendation type has inputs, output, and surface.
- Listing-completeness and missing-data recs reference the Phase 3 dictionary.
- Best-match recs reference Phase 13 matching outputs.

**Dependencies:** Phases 3.5, 4–9 (13 for match-based recs — this phase specifies, Phase 13 supplies the engine).

**Verification checklist:**
- [ ] All 10 recommendation types specified.
- [ ] Completeness/missing-data recs tie to canonical dictionary.
- [ ] Objection/weakness recs derive from low DNA scores or conflicting tags.
- [ ] **Completeness/missing-data recs computed against canonical values across all sources, not just BYO entry (V2.0).**

**Do-not-do warnings:** Do NOT recommend adding fields that violate the do-not-add list. Do NOT surface a "missing data" nag for admin-only fields BYO deliberately excluded.

---

# Phase 11 — Marketing Intelligence Engine

**Goal:** Use **canonical fields + canonical metadata + DNA + audience intelligence** to generate marketing across channels.

> **Version 2.0 — canonical consumption + integrity.** Marketing generators read canonical fields/tags/scores only. The marketing-integrity guardrail is now source-aware: **a claim may only be made from a canonical value whose confidence/provenance supports it** (Phase 3.5). Unconfirmed AI-derived tags must be hedged; an imported value of low confidence must not be marketed as a confirmed fact.

> **Version 2.2 — story-driven, taxonomy-bound, compliance-gated.** Marketing generation now consumes the **Story Engine** (Phase 9.5) — Property/Location/Lifestyle/Community/Luxury/Investment stories become the narrative backbone of the channel outputs — plus the full DNA suite, target audiences, and the Master Intelligence Taxonomy (Phase 3.7) for consistent phrasing (Appendix I marketing tags). **All audience-targeted marketing is governed by Phase 14.5** (Fair Housing / anti-steering): targeting uses objective property facts + user-stated preferences only, never protected-class proxies, and every claim separates fact from inference.

**Source documents:** `beyond-mls-property-dna-roadmap.md` §10.10 (Top 50 Marketing Tags + best channel), §2 (audience-tuned copy), §5 (personality/vibe); Phases 4–9 specs; **`docs/canonical-field-mapping-spec.md` (confidence/provenance for claim-safety)**.

**Create:** `docs/marketing-intelligence-spec.md`

**Tasks:** Specify generation for each output: Facebook listing posts · Instagram captions · Google ad keywords · SEO titles · SEO meta descriptions · YouTube descriptions · Email campaigns · Open-house talking points · Luxury brochure copy · Investor summary · Rental marketing copy · Commercial marketing copy. For each: which DNA scores/tags/audiences drive it, tone rules (numbers-first for investors, lifestyle framing for relocation/retirement — per roadmap §3), and channel routing from the marketing-tag table.

**Deliverable:** `docs/marketing-intelligence-spec.md`.

**Success criteria:**
- Every output type has inputs, tone rules, and channel routing.
- Copy is driven by Audience DNA + vibe embedding, not templates alone.
- Investor/commercial/rental variants defined.

**Dependencies:** Phases 3.5, 4–9.

**Verification checklist:**
- [ ] All 12 output types specified.
- [ ] Tone modulated by audience/intent.
- [ ] Marketing tags routed to channels.
- [ ] **Copy generated from canonical fields/tags/scores, not raw source fields (V2.0).**
- [ ] **Claims gated by canonical confidence/provenance; low-confidence/unconfirmed values hedged (V2.0).**

**Do-not-do warnings:** Do NOT fabricate features not present in the data (marketing-integrity guardrail — every claim must trace to a confirmed field/tag). Do NOT emit copy that contradicts an unconfirmed AI tag as if confirmed.

---

# Phase 12 — Ask AI Expansion

**Goal:** Ensure Ask AI can answer using every canonical field and every intelligence layer.

> **Version 2.0 — canonical consumption.** Ask AI answers from **canonical fields** (resolved through Phase 3.5) plus the canonical intelligence layers. The Ask AI knowledge snapshot is built from canonical values, so Ask AI answers identically whether a listing was BYO-entered or imported — and it can state provenance/confidence ("per the imported MLS record…", "estimated from ATTOM…") because the canonical layer carries it.

> **Version 2.2 — Universal Ask AI over the Knowledge Graph.** Ask AI answers **any supported question** by traversing the **Universal Real Estate Knowledge Graph** (Phase 3.9): Property · Listing · Buyer · Tenant · Seller · Landlord · Location · Neighborhood · Community · Lifestyle · Luxury · Investment · Commercial · Metadata · DNA · Stories · Target Audiences · Marketing · Matching · Recommendations · Search · Analytics · Explainability · **source provenance · confidence.** Its coverage checklist expands to include Seller/Landlord DNA (7.5), the domain DNA suite (8.5), Location/Location-Preference DNA (8), and all 12 story types (9.5). **Grounding rule (hard):** if the information is unavailable or low-confidence, Ask AI **states that plainly and does not invent an answer** — and it distinguishes facts from AI inferences (Phase 14.5). Every answer can cite the graph path that produced it (Phase 14 explainability).

**Source documents:** Field Audit (Ask AI registry subsystem: `AskAiFieldQuestionRegistryService`, `AskAiKnowledgeSnapshotBuilderService`, `AskAiContextBuilderService`, `config/ai_faq_{role}.php`); `beyond-mls-property-dna-roadmap.md` §3 (tone) + §8/§5 (explanation strings); **`docs/canonical-field-mapping-spec.md`**; Phases 1–11.

**Create:** `docs/ask-ai-expansion-spec.md` + a field-coverage checklist.

**Tasks:** Verify/spec Ask AI coverage of: Required MLS fields (Phase 1) · Optional high-value fields (Phase 2) · Metadata tags (Phase 4) · Property DNA (Phase 5) · Buyer DNA (Phase 6) · Tenant DNA (Phase 7) · Location DNA (Phase 8) · Target Audience Intelligence (Phase 9) · Recommendation Engine outputs (Phase 10). Register every Phase 1–2 field in the registry. Expose each DNA/score's **explanation string** to Ask AI so "why is this a 94% match?" and "why is the Boating Score 92?" are answerable. Apply intent-driven tone rules.

**Deliverable:** `docs/ask-ai-expansion-spec.md` + field-coverage checklist (one row per field/layer: registered? answerable? explanation exposed?).

**Success criteria:**
- Every Phase 1–2 field appears in `AskAiFieldQuestionRegistryService`.
- Every DNA score's explanation string is retrievable by Ask AI.
- Tone modulates by buyer/tenant intent.

**Dependencies:** Phases 1–11 (3.5 for canonical resolution; 5–10 for intelligence answers).

**Verification checklist:**
- [ ] Coverage checklist has zero unregistered consumer fields.
- [ ] Explanation strings exposed for every score.
- [ ] Snapshot builder includes new fields on draft-save and submit.
- [ ] **Ask AI answers from canonical fields; snapshot built from canonical values (V2.0).**
- [ ] **Ask AI can cite source provenance/confidence for imported values (V2.0).**

**Do-not-do warnings:** Do NOT let Ask AI answer from admin-only/PII fields. Do NOT bypass the FAQ/registry as the answer source.

---

# Phase 13 — Matching Engine V2

**Goal:** Upgrade matching beyond today's agent-bid matching to true property↔buyer/tenant fit, computed entirely over **canonical DNA vectors**.

> **Version 2.0 — canonical consumption.** Matching V2 operates on the **canonical DNA vectors** (Property/Buyer/Tenant/Location, all built on canonical fields). Because the vectors are source-neutral, an imported Stellar/RentCast/ATTOM listing matches against a BYO buyer's criteria on exactly the same axes as a BYO-entered listing — no per-source matching logic. Deal-breaker/financial/timeline gates also read canonical fields.

**Source documents:** Field Audit (current matching reality: `*BidMatchScoreHelper`, `config/match_scoring.php` — services 35% + terms 35% active, weights sum to 100, property criteria not scored today, BYA compatibility kill-switched); `beyond-mls-property-dna-roadmap.md` §9 ("How the DNA systems compose" — unified 0–100 score, dot-product/weighted-distance, intent re-weighting, Financial/Timeline gates); Phases 5–9 specs; **`docs/canonical-field-mapping-spec.md`**; `config/bya_compatibility.php`.

**Create:** `docs/matching-engine-v2-spec.md`

> **Version 2.2 — full-suite, story-aware, graph-native, learning-ready.** Matching V2 consumes the **entire DNA suite** (Property/Buyer/Tenant/**Seller/Landlord**/Location + **Location Preference DNA** + **Lifestyle/Luxury/Investment/Commercial/Community**), **target audiences** (Phase 9), **stories/personality** (Phase 9.5), **motivations** (Appendix F), Search Areas, Important Places, commute, required criteria, and deal breakers — all as canonical vectors read from the **Knowledge Graph** (Phase 3.9). Seller/Landlord ideal-profiles match against Buyer/Tenant profiles (two-sided fit).
>
> **Behavioral Learning (future-ready, non-breaking).** Specify a learning layer that re-weights match ranking from observed signals — **views, favorites, saved searches, offers, accepted offers, rejected matches, search behavior, agent interactions** — **without changing the underlying canonical model or DNA definitions** (it adjusts weights/priors, not the vocabulary). Define the feedback events, the re-weighting mechanism, guardrails against bias amplification (coordinate with Phase 14.5), and versioning. This is the compounding, hardest-to-replicate moat identified in the V2.1 review.

**Tasks:** Specify a match score that uses: Property DNA · Buyer DNA · Tenant DNA · **Seller DNA · Landlord DNA** · Location DNA · **Location Preference DNA** · Lifestyle DNA · **Luxury DNA · Investment DNA · Commercial DNA · Community DNA** · **Stories/personality** · **Target Audiences** · **Motivations** · Metadata tags · Required preferences · Deal breakers · Search areas · Important Places · Commute constraints · Financial constraints. Define:
1. The **symmetric vector match** (property score vector · buyer preference-weight vector).
2. **Deal-breakers / Financial / Timeline as feasibility gates** (hard filters) vs. weighted dimensions (soft rank).
3. **Intent-driven re-weighting** of the blend.
4. How this composes with — and does not break — the existing agent-bid matching and `config/match_scoring.php` (any newly enabled weights must still sum to the configured total).
5. The prerequisite of **wiring `BuyerTenantDnaGenerator` into the Buyer/Tenant Offer Listing flows** (currently unwired).
6. The relationship to the kill-switched BYA compatibility engine (`BYA_COMPATIBILITY_KILL_SWITCH`, `BYA_COMPATIBILITY_GA_ENABLED`) — **do not enable GA without coordinating with the owner.**

**Deliverable:** `docs/matching-engine-v2-spec.md`.

**Success criteria:**
- Unified 0–100 match score defined from the DNA vectors + gates.
- Gates vs. weights clearly separated.
- Config weight discipline preserved (sum to configured total).
- Buyer/Tenant DNA wiring prerequisite documented.

**Dependencies:** Phases 3.5, 5–9 (and Phase 6/7 wiring).

**Verification checklist:**
- [ ] Deal-breakers/financial/timeline modeled as gates.
- [ ] Symmetric axes confirmed against Phases 5–7.
- [ ] `config/match_scoring.php` weights still sum correctly if changed.
- [ ] GA flag untouched without owner sign-off.
- [ ] **Match computed over canonical DNA vectors; no per-source matching logic (V2.0).**
- [ ] **Imported and BYO-entered listings match on identical canonical axes (V2.0).**

**Do-not-do warnings:**
- **Do NOT** enable `BYA_COMPATIBILITY_GA_ENABLED` or flip the kill switch without owner coordination.
- **Do NOT** break the existing agent-bid matching that launch depends on.
- **Do NOT** let a soft dimension override a hard deal-breaker gate.

## Universal Listing ↔ Criteria Matching

**(New in Version 2.3.)**

**Goal:** Every **listing source** and every **criteria source** becomes matchable the moment it is mapped into the Canonical Data Model — one matching engine, source-agnostic, bidirectional. Matching operates on canonical fields + DNA + metadata + graph outputs, never on raw source-specific fields (Principle 12 & the new principle below).

**Matchable sources (any listing source ↔ any criteria source):**
- **Listings:** Bid Your Offer listings · Stellar MLS listings · future MLS listings · RentCast rental listings · ATTOM / public-record (off-market) opportunities · CSV-imported listings · future listing feeds.
- **Criteria:** Buyer criteria · Tenant criteria · Investor criteria · Commercial criteria · Vacant-land criteria.

**Bidirectional matching (both directions supported):**
1. **Criteria → Listings** — e.g. Buyer criteria matches BYO Seller listings, Stellar MLS listings, future MLS listings, and off-market opportunities; Tenant criteria matches BYO Landlord listings, Stellar rentals, RentCast rentals, and future rental feeds; commercial/business/investment/vacant-land criteria match all applicable listing sources.
2. **Listings → Criteria** — e.g. a Seller listing identifies matching buyer criteria already in the platform; a Landlord listing identifies matching tenant criteria already in the platform.

**The Universal Matching Engine compares:** canonical listing fields · canonical criteria fields · Property DNA · Buyer DNA · Tenant DNA · Seller DNA · Landlord DNA · Location DNA · Location Preference DNA · Lifestyle DNA · Luxury DNA · Investment DNA · Commercial DNA · Community DNA · metadata tags · search areas · Important Places · commute constraints · must-haves · deal breakers · budget/price compatibility · timing/availability compatibility · financing/terms compatibility · source confidence · data completeness.

**Match outputs (per match):** overall match score · category scores · **why it matched** · **why it may not match** · deal breakers triggered · missing data affecting confidence · recommended next action · **listing source** · **criteria source** · source confidence · last-refreshed date. Every output is explainable via Knowledge-Graph paths (Phase 14) and governed by compliance/anti-steering (Phase 14.5).

**How it fits Phase 13:** this is the source-agnostic, bidirectional application of the Matching V2 vector engine above — the DNA vectors and gates already defined are computed identically regardless of which source populated the canonical listing or criteria. Add these as tasks/success criteria to `docs/matching-engine-v2-spec.md`:
- Bidirectional match APIs (criteria→listings and listings→criteria) over the canonical read model + vector store.
- Per-match output schema (the fields above), including listing/criteria source, confidence, and last-refreshed.
- Off-market matching (ATTOM/public-record opportunities) treated as a listing source like any other.

> **Permanent Principle — One Matching Engine. One Intelligence Engine. Many Data Sources.**
> No downstream system should need to know whether data came from Bid Your Offer, Stellar MLS, RentCast, ATTOM, public records, CSV, or a future provider. All **Matching, Ask AI, Marketing, Stories, Recommendations, Analytics, and Decision Support** must operate on **canonical fields, DNA, metadata, stories, and knowledge-graph outputs — never raw source-specific fields.** *(This restates and hardens Principles 11–12 specifically for matching; it is also recorded as Permanent Development Principle 20.)*

**Verification additions (fold into Phase 15):**
- [ ] Matching works for every listing source × every criteria source, both directions.
- [ ] Match output includes listing source, criteria source, source confidence, last-refreshed, why-matched / why-not, deal-breakers, missing-data-confidence, and recommended next action.
- [ ] Off-market (ATTOM/public-record) opportunities are matchable as listings.

---

# Phase 14 — Analytics and Explainability

**Goal:** Show users **why** something matched, and how complete/marketable a listing is.

> **Version 2.0 — canonical consumption.** Analytics reads canonical fields + canonical DNA explanation strings/confidence. The **Data-completeness score is computed against the canonical dictionary (Phase 3) across all sources** (a canonical field is "present" if any source filled it), and the **AI-confidence score aggregates the per-value confidence/provenance from Phase 3.5**. Explanations can therefore say not just "why 94%" but "which source supplied each contributing value."

> **Version 2.2 — graph-path explainability + fact/inference display.** Every match, recommendation, story, and score is explained by **walking the Knowledge Graph** (Phase 3.9) — the provenance/confidence on each node/edge produces a human-readable justification path. Analytics also surfaces the **fact-vs-inference** distinction (Phase 3.8/14.5) in every consumer-facing explanation, and reports **data-completeness** using AI completeness scoring (Phase 3.8) across all sources. Behavioral-learning effects (Phase 13) are themselves auditable here.

**Source documents:** `beyond-mls-property-dna-roadmap.md` (design principle 5 auditability, explanation strings, §9 composition); Phases 5–13 specs; **`docs/master-data-dictionary.md` + `docs/canonical-field-mapping-spec.md` (completeness + confidence basis)**.

**Create:** `docs/analytics-explainability-spec.md`

**Tasks:** Specify: Match-score explanation · Lifestyle overlap · Location overlap · Missing preferences · Deal breakers (why excluded) · Best-fit audience · Marketability score · Data-completeness score · AI-confidence score. Each reads the per-DNA explanation strings + confidence values (Phase 5) and presents them in plain language (Principle 9). Data-completeness references the Phase 3 dictionary; marketability references Phase 11; audience references Phase 9.

**Deliverable:** `docs/analytics-explainability-spec.md`.

**Success criteria:**
- Every match exposes a per-dimension "why matched / why not" breakdown.
- Completeness, marketability, and confidence scores defined and sourced.
- All explanations are human-readable, no raw internals.

**Dependencies:** Phases 3.5, 5–13.

**Verification checklist:**
- [ ] "Why 94%?" answerable with a per-DNA breakdown.
- [ ] Deal-breaker exclusions explained.
- [ ] Completeness/marketability/confidence defined.
- [ ] **Data-completeness computed against the canonical dictionary across all sources (V2.0).**
- [ ] **AI-confidence aggregates canonical per-value provenance/confidence (V2.0).**

**Do-not-do warnings:** Do NOT surface confidence/score internals without the explanation string. Do NOT show completeness gaps for deliberately-excluded admin fields.

---

# Phase 14.5 — Compliance / Responsible AI / Fair Housing / Privacy / Data Governance

**(New in Version 2.2 — governs Phases 4–14 and gates Phase 15.)**

**Goal:** Make the platform's intelligence legally and ethically safe by architecture — not by afterthought. A system that auto-generates target audiences, audience-targeted marketing, and recommendations operates squarely in Fair Housing territory; this phase defines the guardrails and bakes them into every generating engine and the launch gate.

**Source documents:** V2.1 architecture review (Compliance section); Phases 9 (audiences), 11 (marketing), 9.5 (stories), 3.8 (fact/inference); `docs/canonical-field-mapping-spec.md` (provenance/confidence); Master Comparison §3 (PII/admin do-not-add).

**Create:** `docs/compliance-responsible-ai-spec.md`

**Tasks:**
1. **Responsible AI architecture** — every AI output (DNA, stories, audiences, marketing, recs) carries provenance + confidence + taxonomy/model version and is auditable.
2. **Fair Housing (architectural perspective)** — recommendations, audiences, and targeting derive **only** from objective property characteristics, structured data, and **user-stated** preferences — **never** from assumptions about or proxies for protected classes (race, color, religion, sex, familial status, national origin, disability, and applicable state classes).
3. **Anti-steering architecture** — audience/marketing labels may describe a property's objective fit but must **not** control *which consumers are shown or steered toward/away from* a listing in a way that implicates protected classes; define the firewall between "descriptive fit" and "distribution/targeting."
4. **Explainable AI** — every recommendation/match/score is explainable via graph paths (Phase 14); no black-box consumer decisions.
5. **Consumer transparency** — clearly label AI-generated content and **distinguish facts from inferences** in all consumer-facing narratives (ties to Phases 3.8 & 9.5).
6. **Data provenance** — every canonical value and intelligence output records its source(s) and confidence (Phase 3.5/3.9).
7. **Privacy** — PII handling/retention/consent for external sources (ATTOM, public records) and BYO users; keep §3 PII out of consumer surfaces and the graph's public paths.
8. **MLS compliance** — RESO/IDX/VOW display and data-licensing rules for imported listings (what may be shown, derived, retained, and attributed).
9. **External data governance** — licensing terms, attribution, retention, and refresh obligations per source (RentCast/ATTOM/public records/future APIs).
10. **Narrative guardrails** — Story Engine and Marketing may not assert unverified facts or protected-class framing; low-confidence claims hedged or omitted.
11. **Launch compliance checklist** — the concrete pass/fail items folded into Phase 15.

**Deliverable:** `docs/compliance-responsible-ai-spec.md` + the launch compliance checklist (consumed by Phase 15).

**Success criteria:** Fair-Housing/anti-steering firewall defined and referenced by Phases 9 & 11; fact-vs-inference labeling required across stories/marketing/Ask AI; PII/privacy + MLS + external-source governance defined; every AI output auditable with provenance/confidence; launch checklist authored.

**Dependencies:** Phases 3.5, 3.8, 9, 9.5, 11 (defines guardrails they must honor); enforced at Phase 15. **Authored early enough to constrain Phases 9 & 11 before they ship.**

**Verification checklist:**
- [ ] Fair Housing + anti-steering firewall specified and cross-referenced by audience/marketing phases.
- [ ] Fact-vs-inference labeling mandated across all consumer narratives.
- [ ] Privacy/PII, MLS display/licensing, and external-source governance defined.
- [ ] All AI outputs carry provenance + confidence + version and are explainable.
- [ ] Launch compliance checklist produced for Phase 15.

**Do-not-do warnings:** Do NOT target or exclude audiences by protected-class proxies. Do NOT present AI inferences as verified facts. Do NOT expose PII/§3 admin data. Do NOT treat compliance as a post-launch task — it gates launch.

---

# Phase 14.7 — Intelligent Multi-Source Import & Ingestion Pipeline

**(New in Version 2.2.)**

**Goal:** Define, end-to-end, the automated pipeline that turns any imported record (Stellar/future MLS/RentCast/ATTOM/public records/CSV/future API) into a fully-intelligent listing **with zero manual tagging** — the orchestration that wires ingestion (Phase 3.5) through every engine.

**Source documents:** Phase 3.5 (mapping/ingestion contract), 3.6 (entity resolution), 3.8 (AI automation/QA), 3.9 (graph), all intelligence phases (4–9.5), 14.5 (compliance), Architecture & Scalability Requirements.

**Create:** `docs/import-pipeline-spec.md`

**Tasks:** Specify the pipeline stages, each idempotent, queued, incremental, versioned, observable:

```
Source record → Canonical Mapping (3.5) → Entity Resolution/De-dup (3.6)
  → AI Validation + Contradiction Detection + NLP + Inferred Metadata (3.8)
  → Metadata (4) → DNA suite (5–8.5) → Target Audiences (9) → Stories (9.5)
  → Marketing Intelligence (11) → Ask AI knowledge snapshot (12)
  → Search facets (12/13) → Matching signals (13) → Analytics (14)
  → Knowledge Graph population (3.9) → Compliance gate (14.5)
```

1. **Zero manual tagging** — every downstream artifact generates automatically; a human step is a defect.
2. **Entity resolution/de-dup** — one canonical Property even when listed by multiple sources (Architecture Requirement #7).
3. **Incremental & idempotent** — re-imports update, never duplicate (Requirement #6).
4. **Provenance/confidence** stamped throughout; compliance gate (14.5) runs before consumer exposure.
5. **Observability** — per-stage success/failure, backfill/replay, and versioned re-runs after model/taxonomy changes (Requirement #9).

**Deliverable:** `docs/import-pipeline-spec.md`.

**Success criteria:** Full chain specified with no manual step; entity resolution + idempotent incremental sync defined; every stage stamps provenance/confidence; compliance gate precedes exposure; observable and replayable.

**Dependencies:** Phases 3.5–3.9, 4–9.5, 11–14, 14.5.

**Verification checklist:**
- [ ] End-to-end chain specified; zero manual tagging.
- [ ] Entity resolution / de-dup across sources defined.
- [ ] Idempotent, incremental, replayable, observable.
- [ ] Compliance gate (14.5) runs before consumer exposure.

**Do-not-do warnings:** Do NOT require manual tagging or cleanup after import. Do NOT expose imported data before the compliance gate. Do NOT create duplicate Property entities for the same real-world asset.

---

# Phase 15 — Final Launch Audit

**Goal:** Before launch, verify the whole V1.0 data model and intelligence stack end-to-end. This is the release gate.

**Source documents:** All prior phase deliverables; Master Comparison §1–§3; `master-data-dictionary.md`; Field Audit (as the wiring baseline).

**Create:** `docs/launch-audits/v1-release-candidate-audit.md`

**Tasks / verification (each a pass/fail line item):**
1. All required consumer MLS fields implemented (Phase 1 complete; §1 has no Missing rows).
2. High-value optional fields implemented (Phase 2 complete; §2 deferrals logged, not silent).
3. Forms **save** correctly (EAV meta) across all four roles × property types.
4. Forms **edit/autopopulate** correctly.
5. Public listing displays correctly (fields flagged Public show; excluded fields hidden).
6. Search filters work for every field flagged **Search**.
7. Ask AI sees fields (registry coverage checklist from Phase 12 is 100%).
8. Metadata tags generate correctly (Phase 4 rules fire at save/DNA time).
9. DNA scores generate correctly (Phases 5–8; explanation/confidence/version present).
10. Matching uses the correct signals (Phase 13; gates vs weights; config sums).
11. **No MLS-admin-only fields were added to consumer forms** (audit against §3 do-not-add list).
12. **No duplicate fields exist** (audit against Phase 3 canonical names).
13. **No dead fields exist** (every field has ≥1 declared purpose per Principle 5).
14. **(V2.0) Canonical mapping complete** — every canonical field has a mapping row (Phase 3.5); every consumer reads canonical fields, not raw source fields.
15. **(V2.0) Stellar mapping complete** — every Stellar consumer field maps to a canonical field, normalized; no §3 admin field/PII mapped in.
16. **(V2.0) Future-source readiness** — future MLS / RentCast / ATTOM / public-records / CSV / future-API slots exist for every mapping row (populated or N/A); onboarding a new source requires no downstream change (spot-checked with one dry-run source mapping, e.g. a CSV or RentCast pull).
17. **(V2.0) Round-trip parity** — a BYO-entered listing and a Stellar-imported equivalent resolve to identical canonical values and produce identical tags/DNA/matches (fixtures pass).
18. **(V2.0) Provenance + confidence** — every canonical value records source provenance + confidence; conflict-precedence policy applied and logged, never silent.
19. **(V2.0) Full-lifecycle completeness** — every field added in Phases 1–2 passed all 21 lifecycle stages (or explicit N/A with reason), including tests and documentation.
20. **(V2.2) Canonical Entity Dictionary complete** — every canonical field attaches to an owning entity; Property↔Listing separation and entity-resolution keys defined (Phase 3.6).
21. **(V2.2) Master Intelligence Taxonomy governed** — one namespaced slug per concept; all engines bind to it; versioning/ownership defined (Phase 3.7).
22. **(V2.2) AI Automation live** — validation, cross-source contradiction detection, NLP, inferred metadata, completeness scoring operate on ingested data; outputs carry provenance/confidence/version (Phase 3.8).
23. **(V2.2) Knowledge Graph populated & consumed** — producing phases write; Ask AI/Matching/Recommendations/Analytics read; every edge carries provenance/confidence (Phase 3.9).
24. **(V2.2) DNA suite complete** — Property/Buyer/Tenant/Seller/Landlord/Location + Location Preference + Lifestyle/Luxury/Investment/Commercial/Community DNA all generate (Phases 5–8.5), each explainable with confidence/version.
25. **(V2.2) Story Engine live** — all 12 story types generate, grounded (fact vs inference, provenance), regenerating on source change (Phase 9.5).
26. **(V2.2) Compliance / Fair Housing / Responsible AI / Privacy / governance** — the Phase 14.5 launch compliance checklist passes: anti-steering firewall, objective-signal-only targeting, fact-vs-inference labeling, PII/MLS/external-source governance. **Launch-blocking.**
27. **(V2.2) Automated import pipeline** — import a raw multi-source record and confirm the full chain (canonical→metadata→DNA→stories→audiences→marketing→Ask AI→search→matching→analytics→graph) generates with **zero manual tagging** and entity de-dup works (Phase 14.7).
28. **(V2.2) Architecture & Scalability Requirements met** — canonical read model, optimized search, vector retrieval, event-driven regeneration, multi-MLS/incremental sync, entity resolution, and async AI architecture are in place (front-matter requirements).

**Deliverable:** `docs/launch-audits/v1-release-candidate-audit.md` — Version 1.0 Release Candidate audit (Canonical-Data-Model + Knowledge-Graph + Compliance complete).

**Success criteria:** All 28 line items pass. Any failure blocks launch and routes back to the owning phase. Items 26 (compliance) is an absolute launch blocker.

**Dependencies:** Phases 1–14.7 (including 3.5–3.9, 7.5, 8.5, 9.5, 14.5).

**Verification checklist:** (the 28 items above, each explicitly checked with evidence — file/line or test reference; items 3–10, 14–19, 22–27 exercised against real flows, not reasoned).

**Do-not-do warnings:**
- **Do NOT** launch with any §3 admin field on a consumer form.
- **Do NOT** launch with duplicate or dead fields.
- **Do NOT** sign off items 3–10 from reasoning alone — exercise the actual flows (save → edit → display → search → Ask AI → DNA).
- **Do NOT** flip the compatibility GA flag as part of launch without explicit owner coordination.
- **(V2.0) Do NOT** launch with any consumer reading a raw source field instead of the canonical field.
- **(V2.0) Do NOT** launch with a canonical field that lacks a mapping row, provenance/confidence, or (for BYO+Stellar fields) both source mappings.
- **(V2.0) Do NOT** sign off items 14–19 from reasoning alone — run the round-trip parity fixtures and at least one dry-run new-source mapping.

---

# Phase 16 — Future Expansion: Decision Support Intelligence

**(New in Version 2.3 — post-launch Future Expansion. Not part of the Version 1.0 launch gate; sequenced after Phase 15.)**

**Goal:** Evolve the platform from *answering questions* to *helping people make better real estate decisions* — explainable, confidence-scored decision support for every actor, built entirely on the intelligence layers this roadmap already produces.

> **Framing.** The long-term objective of the platform is not simply to answer questions — it is to help consumers and agents make better real estate decisions using **explainable AI**. Decision Support composes existing outputs (it does not introduce a new data source or a new canonical vocabulary); every recommendation is traceable via Knowledge-Graph paths (Phase 14) and governed by compliance (Phase 14.5).

**Source documents:** Canonical Data Model (Phase 3), Canonical Entity Dictionary (3.6), Master Intelligence Taxonomy (3.7), Knowledge Graph (3.9), Metadata (4), full DNA suite (5–8.5), Story Engine (9.5), Recommendations (10), Analytics/Explainability (14), Compliance (14.5).

**Create:** `docs/decision-support-intelligence-spec.md`

**Tasks:** Specify decision-support assistants per actor, each returning an **explainable, confidence-scored** recommendation with trade-offs and the graph path that justifies it:
- **Buyer** — Which property best fits my goals? What trade-offs exist? Why is one property a better match?
- **Seller** — Which offer is strongest? Which pricing strategy should I use? Which buyer profile is the strongest fit?
- **Landlord** — Which tenant best aligns with my stated criteria?
- **Agent** — Which listings should I pursue? Which clients should I prioritize? Which marketing strategy is most effective?
- **Investor** — Which property best aligns with my investment goals?
- **Luxury** — Which marketing strategy reaches qualified luxury buyers?
- **Commercial** — Which business uses best fit this property?

Every assistant must be built on: Canonical Data Model · Canonical Entity Dictionary · Master Intelligence Taxonomy · Knowledge Graph · Metadata · DNA · Stories · Explainability · Confidence · Recommendations · Analytics.

**Deliverable:** `docs/decision-support-intelligence-spec.md`.

**Success criteria:** Each actor has decision assistants that produce explainable, confidence-scored guidance with trade-offs, sourced only from existing canonical intelligence + graph paths, and governed by Phase 14.5. No new canonical vocabulary or data source introduced.

**Dependencies:** Phases 3–14.7 (this is a composition layer on top of the completed platform). Post-Phase-15 / post-launch.

**Verification checklist:**
- [ ] Decision assistants specified for Buyer, Seller, Landlord, Agent, Investor, Luxury, Commercial.
- [ ] Every recommendation is explainable (graph path) + confidence-scored + trade-off-aware.
- [ ] Built only on existing intelligence layers (no new canonical source/vocabulary).
- [ ] Governed by compliance/anti-steering (Phase 14.5).

**Do-not-do warnings:** Do NOT let decision support assert conclusions beyond what the data + confidence support (Principle 15/16/19). Do NOT introduce protected-class reasoning (Phase 14.5). Do NOT create a parallel data model — compose the canonical one.

---

# Permanent Development Principles

1. **Every new field must either capture a fact or generate intelligence. If it does neither, do not add it.**
2. **Prefer derived metadata over manual tagging.** If the system can infer a tag from structured fields, do not ask the user to select it manually.
3. **Support MLS compatibility without becoming an MLS clone.** Add consumer-relevant MLS fields, but do not expose MLS administrative workflows to users.
4. **Keep structured data structured.** Use dropdowns, checkboxes, radios, and multi-selects instead of free text whenever possible.
5. **Every field must declare its purpose:** Search, Matching, Ask AI, Property DNA, Buyer DNA, Tenant DNA, Location DNA, Marketing, Analytics, or Compliance.
6. **Seller/Landlord listing fields and Buyer/Tenant criteria fields should mirror each other** whenever the same concept applies.
7. **Do not create duplicate fields under different names.** Use canonical names from the master data dictionary.
8. **Every field added must be included in save, edit/autopopulate, public display where relevant, Ask AI registry where relevant, search where relevant, and metadata/DNA generation where relevant.**
9. **AI should create intelligence from the data.** Users should not have to understand metadata, scoring, or tags.
10. **Freeze the Version 1.0 data model after Phase 15** unless a field is legally required, MLS-required, or materially improves matching/search/AI.

11. **(V2.0) One Canonical Data Model.** Bid Your Offer and Stellar MLS are not separate paths — they are sources that map into one canonical vocabulary. There is exactly one canonical field per concept; sources map into it. Never create a parallel field to accommodate a source quirk (extend the mapping instead).

12. **(V2.0) Every intelligence layer consumes canonical fields, never raw source fields.** Metadata, Property/Buyer/Tenant/Location DNA, Target Audience, Recommendations, Marketing, Ask AI, Matching, and Analytics read the canonical layer. Adding a new source (future MLS, RentCast, ATTOM, public records, CSV, future API) is a mapping-only change that touches no downstream consumer.

13. **(V2.0) A field is complete only when its full lifecycle passes.** BYO forms, validation, database, edit/autopopulate, public display, canonical mapping, Stellar mapping, future-MLS compatibility, metadata, Property/Buyer/Tenant/Location DNA (where applicable), search, Ask AI, matching, recommendations, marketing, analytics, tests, and documentation — all verified end-to-end. "Works in the BYO form" is not "done." Where a stage is genuinely N/A, mark it N/A with a reason; never silently skip.

14. **(V2.0) Every canonical value carries provenance and confidence.** Record which source(s) supplied each value, when, and how confident it is. Resolve multi-source conflicts by the documented precedence policy — applied and logged, never silent. Downstream consumers (marketing claims, Ask AI answers, analytics confidence) must respect provenance/confidence.

15. **(V2.2) Universal Intelligence Principle.** Every piece of structured data should ultimately answer one or more of: **Who is this property for? · Why is it a good match? · How should it be marketed? · What story does it tell? · What intelligence can AI derive from it?** If a field, metadata tag, DNA score, taxonomy entry, or intelligence layer does not contribute to one of these objectives, reconsider whether it belongs in the platform.

16. **(V2.2, expanded V2.4) Universal Contribution Principle.** Every piece of structured data should ultimately contribute to one or more of: **better data quality · better metadata · better DNA · better matching · better recommendations · better search · better marketing · better stories · better Ask AI responses · better analytics · better explainability · better decision support · better user experience.** If a field, metadata tag, taxonomy entry, DNA signal, story, or intelligence layer does not materially improve one or more of these outcomes, reconsider whether it belongs in the platform. *(This is the outcome-facing companion to Principle 15: 15 asks what question the data answers; 16 asks what platform outcome it improves. New data should satisfy both. Appendix N is the permanent validation of this principle.)*

17. **(V2.3) Platform Learning Loop.** Every meaningful interaction with the platform should have the potential to improve the platform over time. The architecture must **preserve learning signals** from: listing creation · listing edits · MLS imports · buyer criteria · tenant criteria · search behavior · saved searches · favorites · listing views · Ask AI interactions · user corrections · offer activity · accepted offers · rejected offers · agent hiring decisions · marketing performance · match outcomes · metadata corrections · DNA corrections. These signals should improve future **metadata · DNA · matching · search · recommendations · stories · marketing intelligence · Ask AI · analytics**. Preserve (capture, timestamp, attribute, and store) these signals **even if adaptive learning is implemented only in a future release** — and re-weighting must adjust weights/priors, never the canonical model or taxonomy (see Phase 13 behavioral learning). Learning signals are governed by Privacy/Fair-Housing (Phase 14.5) — no protected-class learning.

18. **(V2.3) Workflow Architecture.** Every major workflow follows one documented lifecycle:

    ```
    Create → Validate → Canonical Mapping → Metadata → DNA → Stories
      → Target Audiences → Marketing Intelligence → Ask AI → Matching
      → Analytics → Publish → Learning Loop
    ```

    Apply this lifecycle consistently to: **listing creation · listing editing · MLS imports · buyer creation · tenant creation · agent hiring · offer creation.** (The Phase 14.7 import pipeline is this lifecycle applied to imports; each workflow substitutes its own Create/Validate front end and reuses the same canonical→intelligence→publish→learning tail.) A workflow that skips a stage must mark it N/A with a reason — never silently.

19. **(V2.3) Purpose Principle (final).** The purpose of Bid Your Offer is not simply to store real estate data. Its purpose is to **transform structured real estate data into trusted, explainable intelligence that helps buyers, tenants, sellers, landlords, agents, investors, and businesses make better real estate decisions.** Every future feature should strengthen one or more of: **better data quality · better intelligence · better matching · better recommendations · better search · better marketing · better Ask AI · better decision support · better transparency · better user experience.** If a proposed feature does not materially improve one or more of these objectives, reconsider whether it belongs in the platform.

20. **(V2.3) One Matching Engine. One Intelligence Engine. Many Data Sources.** No downstream system should need to know whether data came from Bid Your Offer, Stellar MLS, RentCast, ATTOM, public records, CSV, or a future provider. All **Matching, Ask AI, Marketing, Stories, Recommendations, Analytics, and Decision Support** must operate on **canonical fields, DNA, metadata, stories, and knowledge-graph outputs — never raw source-specific fields.** Matching is **universal and bidirectional**: every listing source is matchable against every applicable criteria source, and every criteria source against every applicable listing source (see Phase 13 → Universal Listing ↔ Criteria Matching).

21. **(V2.4) Compute Whenever Possible.** Whenever reliable information can be derived from **canonical fields, metadata, public datasets, Location Intelligence, existing user selections, or AI inference**, the platform should **compute it automatically** instead of asking users to manually enter it. *(Generalizes Principle 2 — "prefer derived metadata over manual tagging" — from tags to all derivable intelligence. Computed values still carry provenance + confidence and are labeled inference vs. fact per Phases 3.8/14.5.)*

22. **(V2.4) Location Story Principle.** Every location should be able to tell an **explainable story** generated only from **objective, verifiable data** — geographic features, waterfront, beaches, marinas, parks, trails, recreation, dining, shopping, walkability, transit, commute, schools (where applicable), public amenities, environmental characteristics, and market characteristics. **Location stories must never infer protected characteristics or demographic assumptions** (enforced by Phase 14.5). *(Realized by the Location Intelligence Engine (Phase 8) feeding the Story Engine (Phase 9.5).)*

23. **(V2.4) Canonical Platform Principle.** The **Canonical Data Model is the platform's single source of truth.** Every intelligence engine, AI capability, workflow, and future integration must consume **canonical data rather than source-specific fields.** No downstream system should need to know whether data originated from Bid Your Offer, Stellar MLS, future MLS providers, RentCast, ATTOM, public records, CSV imports, or future APIs — once normalized, **all data participates equally** in the Intelligence Platform. *(The named consolidation of Principles 11, 12, and 20; if any two appear to conflict, this principle governs.)*

---

# Permanent UI/UX Consistency Standards

Every new field added to Bid Your Offer must match the existing UI/UX conventions already established throughout the application.

Do not invent new placeholder styles, tooltip styles, labels, validation messages, spacing, capitalization, or layouts.

Before implementing any new field, inspect the most similar existing field and mirror its design exactly.

This includes, but is not limited to: Placeholder text · Labels · Helper text · Tooltip wording and format · Validation messages · Required-field indicators · Input widths · Field spacing · Field grouping · Section headings · Card layout · Icons · Badge styles · Dropdown formatting · Multi-select formatting · Checkbox formatting · Radio button formatting · Toggle formatting · Date picker formatting · Number input formatting · Currency formatting · Character counters · Error styling · Success styling.

Examples:
- If similar dropdowns use "Select..." then use the same pattern.
- If similar text fields use "Enter..." then use the same pattern.
- If similar fields have helper text beneath the input, follow that style.
- If similar fields use tooltips, match the same tooltip component and wording style.
- If similar fields display examples, follow the exact same example formatting.
- If similar sections use specific spacing or dividers, match them exactly.

Never introduce a different style simply because a new field is being added. Consistency with the existing Bid Your Offer design system takes precedence over creating a new convention. If there is any uncertainty, inspect comparable fields already implemented elsewhere in the application and replicate their behavior, wording, layout, and appearance.

---

## Appendix A — Required Stellar MLS Field Implementation List

The consumer-facing **required** (asterisked) Stellar fields that are Missing/Partial in BYO and must be implemented (Phase 1). Source: Master Comparison §1 + §5.2. **242 of 281 required consumer fields are already Supported** by BYO (see Field Audit / Phase 3); this appendix is the remaining **39 form-instances ≈ 18 distinct concepts** to build. Each maps to a canonical field (Phase 3) with BYO + Stellar source mappings (Phase 3.5). **Do-not-add:** none of the Master Comparison §3 administrative required fields (Office Exclusive, Listing/Service Type, Seller Representation, legal survey IDs, agent/office, showing/lockbox, IDX/VOW, signatures, owner/tenant PII) are implemented.

| # | Canonical field (concept) | Forms / property types requiring it | Priority | Notes |
|---|---|---|---|---|
| A1 | Green Energy Generation (Y/N + Solar/Wind) | Residential, Income, Commercial Sale, Business, Commercial Lease, Rental | High | + Solar Panel Ownership (Appendix B) |
| A2 | Exact Lot Size — Square Feet (numeric) | All seven forms | High | replaces range bucket |
| A3 | Exact Lot Size — Acres (numeric) | All land-bearing (Res, Income, CS, Biz, CL, VL) | High | complements `total_acreage` |
| A4 | Ownership Type (Fee Simple/Condo/Co-op/Fractional/Leasehold) | Residential, Income, Commercial Sale, Business, Vacant Land | Medium | ≠ `occupant_status` |
| A5 | Land Lease (Y/N + fee) | Residential, Income | Medium | pairs with A4 |
| A6 | Front Exposure (N/S/E/W + intercardinal) | Residential | High | sun-orientation DNA |
| A7 | Floors / Stories (numeric) | Residential, Residential Rental | Medium | accessibility filter |
| A8 | Fireplace standalone (Y/N + Gas/Wood/Electric + location) | Residential, Income | Medium | extract from `interior_features` |
| A9 | Room Types / Additional Rooms (structured grid) | Residential, Residential Rental | Medium | Kitchen/Living/Primary + Den/Bonus/Media/Great/Florida/Loft/Inside-Utility |
| A10 | Road Surface Type (extend-to-flow) | Residential-sale (exists on CS/VL) | Low | extend existing field |
| A11 | Furnishings (extend-to-flow) | Residential-sale (exists on Rental) | Low | Furnished/Unfurnished/Partial |
| A12 | Laundry Features (extend-to-flow) | Residential-sale (exists on Landlord) | Low | extend existing field |
| A13 | Floor Covering (extend-to-flow) | Residential-sale (exists on Landlord) | Low | extend existing field |
| A14 | Application Fee | Residential Rental | High | standard move-in cost |
| A15 | In-Law Suite (Y/N + detail) | Residential Rental (required), Residential | High | detail fields in Appendix B |
| A16 | Long-Term flag (+ seasonal block) | Residential Rental | Medium | adopt with seasonal block (Appendix B), not bare toggle |
| A17 | **Lease Price Unit** ($ Total Monthly vs Per SqFt) + frequency | Commercial Lease | **Critical** | unblocks commercial-lease comparison/search/matching |
| A18 | Structured Initial Pass-Through Expenses ($ + Flat-Monthly / Annual $/SqFt) | Commercial Lease | High | replaces free-text CAM |
| A19 | Road Frontage (extend-to-flow) | Commercial Lease (exists on Seller-Commercial) | Low | extend existing field |
| A20 | Designated Builder (Y/N flag only) | Vacant Land | Low | flag only — name/details are do-not-add |
| A21 | For Lease flag | Vacant Land | Low | ag/billboard/cell-tower land-lease |
| A22 | Business Ownership / Entity Structure (Franchise/Sole-Prop/LLC/Corp/Partnership/Leasehold) | Business Opportunity | High | primary business-buyer filter |

---

## Appendix B — High-Value Optional MLS Field List

The **55 optional (non-asterisked) Stellar consumer field-concepts** Missing/Partial in BYO and worth adding (Phase 2). Source: Master Comparison §2 + Gap Analysis §8. Grouped by domain; priority per audit. All map to canonical fields (Phase 3/3.5). "Do Not Add" §2 item (Room Dimensions grid) and all §3 admin fields are excluded.

**Residential lifestyle/DNA:** Schools (Elem/Middle/High) — High · Architectural Style — High · Accessibility Features (24-value) — High · Community Features (gated/golf/dog-park/…) — High · In-Law Suite / ADU details — High · Additional Rooms details — High · Disaster Mitigation (hurricane shutters/impact windows/above-flood-plain/safe-room) — High · Solar Panel Ownership — High · Solar Lease/Finance Terms — High · Patio & Porch Features — Medium · Window Features — Medium · Fireplace description — Medium · Fencing (extend) — Medium · Security Features (extend) — Medium · Spa Features (extend) — Medium.

**Condo/ownership depth:** Property Position (Corner/End/Penthouse/High-Mid-Rise/Stilt/Walk-Up) — Medium · Floor Number — Medium · Total # of Floors — Medium · Building Elevator — Medium · Condo Fee / Addtl-Maint tiers — Medium · Ownership Type + Land Lease (also required, Appendix A) — Medium.

**Income/investor:** Individually Metered Utilities — High · Gross Scheduled Income — Medium · Estimated Market (potential) Income — Medium · Financial Source — Medium · Total Monthly Rent / Expenses — Medium · Structured Terms-of-Lease / Tenant-Pays — Medium · GRM (derived) — Low.

**Commercial (Sale + Lease):** Loading / Dock configuration (bays grade/dock-high/dock-well, door H×W, truck doors, high bays, clear span, columns) — High · Number of Tenants (Single/Multi/Vacant) — High · Anchor / Co-Tenant — High · Pass-Through Expense Includes (structured checklist) — High · Commercial Transaction Terms (escalation/TI allowance/pre-leasing/build-to-suit/sublease) — High · Vacancy Rate — Medium · NOI Type (Actual vs Projected) — Medium · Total Parking Spaces / ratio — Medium · Signage — Medium · Adjoining Property / Adjacent Use — Medium · Restrooms/Offices/Conference Rooms (extend to sale) — Medium · Space Classification A/B/C/D on sale side — Medium · Freezer/Freestanding/Converted Residence — Low · Management Type — Low · Income Includes (Rent/Parking/Storage/Laundry) — Medium.

**Business Opportunity:** Number of Tenants / Anchor — Medium · NOI/Income Type (Actual vs Projected) — Medium · Non-Compete (+term) & Seller Training (+period) structured — Low/Medium · Hours/Days of Operation — Low · Freezer/Freestanding/Converted Residence — Low.

**Residential Rental:** Short-Term / Seasonal block (Seasonal Rent, Off-Season Rent, Weeks/Months-Available calendar) — High · Additional Applicant Fee — Medium · Association Fees for Tenants (approval/security/parking/other + frequency) — Medium · Assoc Approval Required + process/timeframe — Medium · Tenant Pays (utilities list) — Medium · Primary Bed Size — Low · Pet Restrictions Source (Association vs Landlord) — Low.

**Vacant Land:** Structured Buyer-side land criteria (zoning intent/utilities-required/buildable/min-acreage/road-access) — High · Lot Features (topography/wetlands/flood-plain/conservation/cleared-wooded/soil/brownfield) — High · Future Land Use + Zoning Compatible — Medium · Farm Type + AG Exemption — Medium · PUD + Additional Parcels/assemblage — Low/Medium · Horse/Barn amenities + paddocks/stalls — Low.

**Low-priority backlog (adopt opportunistically):** Green Building Certifications (LEED/ENERGY STAR/HERS/FGBC); Indoor Air Quality (MERV/low-VOC/whole-house-vacuum); Room-dimensions grid *(Do Not Add)*; Existing-Lease detail (monthly rent + lease end + notice).

---

## Appendix C — Metadata Tag Master List

Two complementary tag catalogs. **C.1** is the Beyond-MLS §10.2 curated 100 (with category/source/becomes/priority). **C.2** is the Gap-Analysis §9 100 tag *slugs* grouped by DNA layer. Both are **derived** at save/DNA-generation time from canonical fields (Principle 2 & 12), never hand-entered.

### C.1 — Top 100 AI Metadata Tags (Beyond-MLS §10.2)

| # | Tag | Category | Source | Becomes | Priority |
|---|---|---|---|---|---|
| 1 | lock-and-leave | Lifestyle | Derived | Snowbird/second-home match; filter | Critical |
| 2 | remote-work-ready | Lifestyle | Derived | Post-2020 audience targeting | Critical |
| 3 | multigenerational | Lifestyle | AI-Generated (vision) | Family match; ADU marketing | Critical |
| 4 | family-friendly | Lifestyle | Derived | Family segment; school pairing | Critical |
| 5 | snowbird-ready | Lifestyle | Derived | Seasonal-buyer targeting | High |
| 6 | pet-paradise | Lifestyle | Derived | Pet-owner match | High |
| 7 | entertainer-dream | Lifestyle | AI-Generated (vision) | Luxury/social marketing | High |
| 8 | outdoor-living | Lifestyle | AI-Generated (vision) | FL lifestyle marketing | High |
| 9 | active-adult | Lifestyle | Derived | 55+ community match | High |
| 10 | low-maintenance-living | Lifestyle | Derived | Lock-and-leave pairing | High |
| 11 | wellness-focused | Lifestyle | Derived | Health-conscious audience | High |
| 12 | vacation-vibe | Lifestyle | AI-Generated | Second-home marketing | High |
| 13 | eco-conscious-living | Lifestyle | Derived | Green-buyer targeting | Medium |
| 14 | work-from-anywhere | Lifestyle | Derived | Digital-nomad segment | Medium |
| 15 | hobby-farm-ready | Lifestyle | Derived | Rural/homestead audience | Medium |
| 16 | boater-lifestyle | Lifestyle | Derived | Waterfront targeting | Medium |
| 17 | car-enthusiast-garage | Lifestyle | AI-Generated (vision) | Hobbyist niche | Medium |
| 18 | urban-professional | Lifestyle | Derived | Young-professional segment | Medium |
| 19 | quiet-sanctuary | Lifestyle | Derived | Retreat marketing | Medium |
| 20 | social-club-community | Lifestyle | Derived | Amenity-driven match | Medium |
| 21 | coastal-modern | Personality | AI-Generated (vision) | Style search; marketing tone | High |
| 22 | key-west-charm | Personality | AI-Generated (vision) | Style search | High |
| 23 | mediterranean-elegance | Personality | AI-Generated (vision) | Marketing tone | High |
| 24 | mid-century-cool | Personality | AI-Generated (vision) | Style search | Medium |
| 25 | rustic-retreat | Personality | AI-Generated (vision) | Marketing tone | Medium |
| 26 | modern-minimalist | Personality | AI-Generated (vision) | Style search | Medium |
| 27 | timeless-traditional | Personality | AI-Generated (vision) | Marketing tone | Medium |
| 28 | industrial-loft | Personality | AI-Generated (vision) | Style search | Medium |
| 29 | luxury-estate-feel | Personality | AI-Generated | Luxury positioning | High |
| 30 | cozy-cottage | Personality | AI-Generated | Marketing tone | Medium |
| 31 | grand-and-formal | Personality | AI-Generated | Luxury tone | Medium |
| 32 | light-and-airy | Personality | Derived (exposure) | Marketing tone | Medium |
| 33 | architecturally-significant | Personality | AI-Generated | Marketing hook | Future |
| 34 | statement-property | Personality | AI-Generated | Luxury tone | Future |
| 35 | walk-to-beach | Neighborhood | Derived (Location DNA) | Vacation/coastal match | High |
| 36 | walk-to-dining | Neighborhood | Derived (Location DNA) | Walkability marketing | High |
| 37 | top-rated-schools | Neighborhood | Derived (Location DNA) | Family match | Critical |
| 38 | quiet-street | Neighborhood | Derived (noise model) | Privacy marketing | High |
| 39 | vibrant-downtown | Neighborhood | Derived (POI) | Urban targeting | High |
| 40 | golf-course-community | Neighborhood | Derived (community) | Golf-lifestyle match | High |
| 41 | gated-community | Neighborhood | Derived (features) | Security/luxury match | High |
| 42 | dog-park-nearby | Neighborhood | Derived (POI) | Pet-owner match | Medium |
| 43 | transit-connected | Neighborhood | Derived (Location DNA) | Commuter targeting | Medium |
| 44 | up-and-coming | Neighborhood | AI-Generated (trends) | Appreciation marketing | Medium |
| 45 | arts-district | Neighborhood | Derived (POI) | Cultural segment | Medium |
| 46 | nightlife-hub | Neighborhood | Derived (POI) | Young-professional target | Medium |
| 47 | family-neighborhood | Neighborhood | Derived (demographics) | Family match | Medium |
| 48 | nature-adjacent | Neighborhood | Derived (green space) | Outdoor audience | Medium |
| 49 | marina-district | Neighborhood | Derived (POI) | Boater targeting | Medium |
| 50 | medical-corridor | Neighborhood | Derived (POI) | Retiree/investor target | Medium |
| 51 | high-visibility-corner | Commercial | Derived (frontage+traffic) | Retail marketing | High |
| 52 | restaurant-ready | Commercial | AI-Generated (vision) | Use-fit match | High |
| 53 | drive-in-warehouse | Commercial | Derived (dock config) | Industrial match | High |
| 54 | dock-high-logistics | Commercial | Derived (dock config) | Industrial match | High |
| 55 | turnkey-office | Commercial | AI-Generated (vision) | Office-tenant match | High |
| 56 | medical-office-ready | Commercial | Derived (build-out) | Use-fit match | Medium |
| 57 | anchored-center | Commercial | Derived (co-tenancy) | Investor match | High |
| 58 | high-traffic-retail | Commercial | Derived (traffic counts) | Retail viability | High |
| 59 | flex-industrial | Commercial | Derived (space class) | Use-fit search | Medium |
| 60 | clear-span-warehouse | Commercial | Derived (features) | Industrial match | Medium |
| 61 | second-generation-space | Commercial | Derived (build-out) | Cost-saving marketing | Medium |
| 62 | pad-ready-site | Commercial | Derived (land + entitlement) | Development match | Medium |
| 63 | franchise-opportunity | Commercial | Derived (entity) | Business-buyer match | High |
| 64 | absentee-run-business | Commercial | Derived (mgmt profile) | Lifestyle-investor target | Medium |
| 65 | build-to-suit-available | Commercial | Derived (lease terms) | Tenant match | Medium |
| 66 | ample-parking | Commercial | Derived (ratio) | Retail/office fit | Medium |
| 67 | income-stable | Investment | Derived (WALT+tenancy) | Investor match | Critical |
| 68 | cash-flowing | Investment | Derived (NOI+debt) | Investor match | Critical |
| 69 | value-add | Investment | Derived (rent gap) | Value-add investor match | High |
| 70 | turnkey-rental | Investment | Derived (condition+lease) | Passive-investor target | High |
| 71 | high-cap-rate | Investment | Derived (NOI/price) | Yield-investor match | High |
| 72 | separately-metered | Investment | Derived (utilities) | Multifamily-investor signal | High |
| 73 | str-goldmine | Investment | Derived (STR revenue) | STR-investor targeting | High |
| 74 | single-tenant-nnn | Investment | Derived (tenancy) | NNN-investor match | High |
| 75 | 1031-candidate | Investment | Derived (economics) | Exchange-buyer target | Medium |
| 76 | appreciation-play | Investment | AI-Generated (trends) | Long-hold investor | Medium |
| 77 | below-market-rents | Investment | Derived (rent comps) | Value-add signal | Medium |
| 78 | development-upside | Investment | Derived (zoning+FLU) | Developer match | Medium |
| 79 | assumable-loan-deal | Investment | Derived (rate gap) | Financing-savvy buyer | Medium |
| 80 | recession-resilient-tenant | Investment | Derived (tenant type) | Defensive-investor target | Future |
| 81 | move-in-ready | Condition | AI-Generated (vision) | Turnkey match | Critical |
| 82 | recently-renovated | Condition | AI-Generated (vision) | Buyer-effort match | High |
| 83 | chef-kitchen | Condition | AI-Generated (vision) | Luxury/foodie marketing | High |
| 84 | designer-finishes | Condition | AI-Generated (vision) | Luxury marketing | High |
| 85 | fixer-upper | Condition | AI-Generated (vision) | Flip-investor match | High |
| 86 | new-roof-and-systems | Condition | Derived (age data) | TCO/insurability signal | High |
| 87 | hurricane-hardened | Condition | Derived (mitigation) | Insurability marketing | High |
| 88 | impact-windows | Condition | Derived (features) | Insurability signal | Medium |
| 89 | smart-home-equipped | Condition | AI-Generated (vision) | Tech-buyer targeting | Medium |
| 90 | solar-powered | Condition | Derived (green features) | Energy marketing | Medium |
| 91 | ev-ready | Condition | Derived (charging) | Tech/green targeting | Medium |
| 92 | needs-tlc | Condition | AI-Generated (vision) | Bargain-buyer match | Medium |
| 93 | waterfront | Location-perk | Derived (features) | Waterfront match | Critical |
| 94 | deep-water-access | Location-perk | Derived (waterway) | Boater match | High |
| 95 | sunset-views | Location-perk | Derived (exposure+view) | Luxury marketing | High |
| 96 | dark-sky | Location-perk | Derived (light-pollution) | Rural/retreat marketing | Medium |
| 97 | golf-frontage | Location-perk | Derived (parcel+course) | Golf-lifestyle marketing | Medium |
| 98 | corner-lot-privacy | Location-perk | Derived (parcel geometry) | Privacy marketing | Medium |
| 99 | oversized-lot | Location-perk | Derived (lot size) | Space-seeker match | Medium |
| 100 | end-unit-quiet | Location-perk | Derived (unit position) | Condo-buyer match | Medium |

### C.2 — Top 100 Metadata / Lifestyle Tag Slugs (Gap Analysis §9)

Derived slugs, grouped by DNA layer, exposed as search facets.

- **Location DNA (1–12):** `school_zone_rated` · `top_school_district` · `walkable` · `near_public_transit` · `golf_community` · `gated_community` · `waterfront` · `water_view` · `beach_proximity` · `downtown_proximity` · `short_commute` · `low_hoa`
- **Property DNA — style/character (13–24):** `architectural_coastal` · `architectural_key_west` · `architectural_mediterranean` · `architectural_modern` · `architectural_traditional` · `new_construction` · `move_in_ready` · `fixer_upper` · `luxury` · `historic` · `single_level` · `multi_story`
- **Property DNA — features (25–45):** `pool_home` · `heated_pool` · `spa_hot_tub` · `screened_lanai` · `outdoor_living` · `fireplace` · `gourmet_kitchen` · `open_floorplan` · `high_ceilings` · `smart_home` · `home_office` · `media_room` · `flex_space` · `florida_room` · `bonus_room` · `oversized_garage` · `rv_boat_parking` · `ev_charging` · `large_lot` · `corner_lot` · `cul_de_sac`
- **Lifestyle / exposure (46–55):** `exposure_south` · `natural_light` · `sunset_view` · `sunrise_view` · `private_backyard` · `fenced_yard` · `dog_friendly` · `equestrian` · `boater_lifestyle` · `golf_cart_community`
- **Target audience (56–68):** `family_oriented` · `active_adult_55plus` · `multigenerational` · `remote_worker_ready` · `investor_ready` · `snowbird_seasonal` · `first_time_buyer` · `downsizer` · `accessibility_ready` · `aging_in_place` · `wheelchair_accessible` · `pet_owner_friendly` · `eco_conscious`
- **Resilience / green / cost (69–80):** `hurricane_hardened` · `impact_windows` · `above_flood_plain` · `insurance_friendly` · `flood_zone_low_risk` · `solar_owned` · `energy_efficient` · `low_utility_cost` · `green_certified` · `no_cdd` · `low_property_tax` · `assumable_financing`
- **Financial / deal (81–88):** `cash_flowing` · `value_add` · `high_cap_rate` · `seller_financing_available` · `lease_option_available` · `turnkey_investment` · `tenant_occupied` · `below_market_rent`
- **Commercial (89–95):** `nnn_investment` · `single_tenant_net_lease` · `multi_tenant` · `anchored_center` · `industrial_dock_high` · `high_visibility_retail` · `build_to_suit`
- **Land (96–98):** `buildable_lot` · `agricultural_zoned` · `development_potential`
- **Rental (99–100):** `furnished_rental` · `annual_lease`

---

## Appendix D — Lifestyle Category Master List (Beyond-MLS §10.4, Top 50)

Living-experience buckets a property can carry and a buyer/tenant can want (symmetric). Property types: Res/Inc/CS/CL/Biz/Land.

| # | Lifestyle | Property Types | Symmetric Match Signal | Priority |
|---|---|---|---|---|
| 1 | Waterfront Living | Res/Inc/Land | Wants water frontage/view lifestyle | High |
| 2 | Boating & Marina | Res/CS/Land | Wants dock/boat-lift/deep-water access | High |
| 3 | Golf Community | Res | Wants on-course or golf-access living | High |
| 4 | Beach / Coastal | Res | Wants beach-proximity lifestyle | High |
| 5 | Equestrian / Horse Property | Res/Land | Wants stalls + riding acreage | Medium |
| 6 | Active Adult 55+ | Res/Inc | Wants age-restricted amenity community | High |
| 7 | Family Suburban | Res | Wants schools + yard + safe streets | Critical |
| 8 | Multigenerational Living | Res | Wants ADU/in-law separate quarters | High |
| 9 | Remote-Work Ready | Res | Wants home office + strong connectivity | High |
| 10 | Walkable Urban | Res/CS | Wants errands-on-foot walkability | High |
| 11 | Gated Privacy | Res/Land | Wants gated/secured seclusion | High |
| 12 | Resort-Style Amenity | Res/Inc | Wants pool/clubhouse/fitness amenity | Medium |
| 13 | Snowbird / Seasonal | Res | Wants seasonal winter-residence lease | High |
| 14 | Eco / Green Living | Res/Land | Wants solar/energy-efficient home | Medium |
| 15 | Off-Grid / Self-Sufficient | Land/Res | Wants well/septic/solar independence | Future |
| 16 | Homestead / Hobby Farm | Land/Res | Wants ag land + gardening capacity | Medium |
| 17 | Car Enthusiast / Garage | Res | Wants oversized/multi-bay garage | Medium |
| 18 | RV / Boat Storage | Res/Land | Wants on-site RV/boat parking | Medium |
| 19 | Wellness / Fitness | Res/Inc | Wants gym/spa/wellness amenity | Future |
| 20 | Entertaining / Hosting | Res | Wants open floor + outdoor kitchen | Medium |
| 21 | Pet-Centric | Res | Wants pet-friendly space + fenced yard | High |
| 22 | Gardening / Green Thumb | Res/Land | Wants large lot / greenhouse room | Future |
| 23 | Downtown Loft / High-Rise | Res | Wants condo high-floor city living | Medium |
| 24 | Nightlife & Dining | Res | Wants entertainment-district proximity | Medium |
| 25 | Arts & Cultural | Res | Wants gallery/theater-district access | Future |
| 26 | Outdoor Recreation / Trails | Res/Land | Wants trail/park adjacency | Medium |
| 27 | Lakefront | Res/Land | Wants lake frontage/view | High |
| 28 | Riverfront | Res/Land | Wants river frontage | Medium |
| 29 | Quiet Rural Retreat | Land/Res | Wants acreage + low-density privacy | Medium |
| 30 | Dark-Sky / Stargazing | Land | Wants low light-pollution setting | Future |
| 31 | Fly-In / Aviation | Res/Land | Wants airpark/hangar access | Future |
| 32 | Vineyard / Winery | Land/Biz | Wants agri-tourism/vineyard land | Future |
| 33 | Ranch / Cattle | Land | Wants grazing acreage + fencing | Medium |
| 34 | Minimalist / Tiny-Home | Res/Land | Wants small-footprint efficiency | Future |
| 35 | Luxury Estate | Res | Wants high-end finishes + privacy | High |
| 36 | Historic Character | Res | Wants architectural heritage | Medium |
| 37 | New-Construction Community | Res | Wants new-build with warranty | High |
| 38 | University-Adjacent | Res/Inc | Wants walk-to-campus rental/hold | Medium |
| 39 | Medical-District Proximity | Res/CS | Wants healthcare-worker convenience | Medium |
| 40 | Marina / Yacht | Res/CS | Wants deep-water yacht berthing | Future |
| 41 | Nature-Preserve Adjacency | Res/Land | Wants conservation-buffer views | Medium |
| 42 | Agritourism / Farm-Stay | Land/Biz | Wants agri-business hospitality land | Future |
| 43 | Maker / Workshop Space | Res/CS | Wants shop/flex workspace | Future |
| 44 | Poolside / Private Pool | Res | Wants private pool | High |
| 45 | Mountain / Hill View | Res/Land | Wants elevated view lot | Medium |
| 46 | Golf-Cart Community | Res | Wants cart-legal neighborhood | Medium |
| 47 | Country-Club Membership | Res | Wants club-access lifestyle | Medium |
| 48 | Waterway / Canal | Res/Land | Wants canal navigable access | Medium |
| 49 | Beachfront Vacation Rental | Res/Inc | Wants STR-income beach asset | High |
| 50 | Co-Living / Shared | Res/Inc | Wants flexible shared occupancy | Future |

---

## Appendix E — Target Audience Master List (Beyond-MLS §10.5, Top 50)

Who the person is (identity/segment), distinct from motivation (Appendix F). Consumed by Phase 9.

| # | Audience | Typical Property Types | Marketing Angle | Priority |
|---|---|---|---|---|
| 1 | First-Time Homebuyer | Res | Affordability + step-by-step guidance | High |
| 2 | Growing Family | Res | Schools, bedrooms, safe yard | Critical |
| 3 | Empty-Nester / Downsizer | Res | Low-maintenance right-sizing | High |
| 4 | Retiree / 55+ | Res | Active-adult amenity community | High |
| 5 | Snowbird | Res | Warm-weather seasonal escape | High |
| 6 | Remote Worker / Digital Nomad | Res | Home office + fiber connectivity | High |
| 7 | Buy-and-Hold Investor | Inc/Res | Reliable monthly cash flow | High |
| 8 | Fix-and-Flip Investor | Res | Value-add renovation upside | Medium |
| 9 | 1031-Exchange Investor | Inc/CS | Tax-deferred like-kind swap | Medium |
| 10 | Commercial NNN Investor | CS | Passive net-lease income | High |
| 11 | Owner-Operator | Biz/CS | SBA-financed business + real estate | High |
| 12 | Franchise Buyer | Biz | Proven franchise concept | Medium |
| 13 | Small-Business Tenant | CL | Affordable, right-sized lease | High |
| 14 | Industrial / Warehouse Tenant | CL | Dock loading + clear span | Medium |
| 15 | Retail Tenant | CL | Visibility + foot traffic | Medium |
| 16 | Restaurant Operator | CL/Biz | Build-out + hood + parking | Medium |
| 17 | Medical / Professional Office | CL | Class-A fit-out + parking | Medium |
| 18 | Startup / Coworking | CL | Flexible short-term terms | Future |
| 19 | Multigenerational Household | Res | ADU/in-law for extended family | High |
| 20 | Luxury Buyer | Res | Exclusivity + concierge service | High |
| 21 | Vacation-Home Buyer | Res | Lifestyle escape destination | Medium |
| 22 | Short-Term-Rental Operator | Res/Inc | STR yield + permitted zone | High |
| 23 | Land Developer | Land | Entitlement + density upside | Medium |
| 24 | Builder / Spec Developer | Land | Shovel-ready buildable lots | Medium |
| 25 | Farmer / Rancher | Land | Ag productivity + water rights | Medium |
| 26 | Equestrian Buyer | Res/Land | Stalls + paddocks + acreage | Medium |
| 27 | Boater / Yacht Owner | Res | Deep-water dock + lift | Medium |
| 28 | Golfer | Res | On-course / club access | Medium |
| 29 | Relocating Professional | Res | Turnkey, fast move-in | High |
| 30 | Corporate-Housing Provider | Inc/Res | Furnished mid-term units | Future |
| 31 | Student / Near-Campus Renter | Res | Walk-to-campus convenience | Medium |
| 32 | Young Professional | Res | Urban walkability + nightlife | Medium |
| 33 | Voucher / Subsidized Landlord | Inc | Guaranteed program rent | Future |
| 34 | Accessibility-Needs Buyer | Res | Single-level / ADA features | High |
| 35 | Eco-Conscious Buyer | Res | Solar + green certification | Medium |
| 36 | Pet Owner | Res | Pet-friendly policy + yard | High |
| 37 | Second-Home Investor | Res/Inc | Dual personal-use + income | Medium |
| 38 | Cash Buyer | Res/CS | Fast, contingency-free close | High |
| 39 | VA / Military Buyer | Res | VA-eligible, base proximity | Medium |
| 40 | Self-Storage / Flex Investor | CS | Low-management asset class | Future |
| 41 | Hospitality Investor | CS/Biz | Hotel/motel operating asset | Future |
| 42 | Absentee-Owner Investor | Inc/Biz | Professionally managed hands-off | Medium |
| 43 | Institutional / Assembly Buyer | CS/Land | Church/civic assembly use | Future |
| 44 | Aging-in-Place Homeowner | Res | Accessibility retrofit ready | Medium |
| 45 | Downtown Condo Buyer | Res | Lock-and-leave city base | Medium |
| 46 | Rural / Homestead Buyer | Land/Res | Self-sufficiency + acreage | Medium |
| 47 | Foreign / EB-5 Investor | Res/CS | Portfolio + visa-linked assets | Future |
| 48 | Estate / Trust Seller-Buyer | Res/Land | As-is legacy disposition | Medium |
| 49 | New-Construction Buyer | Res | Warranty + modern build | High |
| 50 | Shared-Equity / Co-Op Buyer | Res | Affordability ownership model | Future |

---

## Appendix F — Buyer/Tenant Motivation Master List (Beyond-MLS §10.6, Top 50)

The underlying "why" behind demand. Flow(s): B (Buyer) / T (Tenant). Ranking Effect = how the signal modulates match order (Phase 13). Consumed by Phases 6, 7, 13.

| # | Motivation | Flow(s) | Ranking Effect | Priority |
|---|---|---|---|---|
| 1 | Job relocation | B/T | Boost move-in-ready + commute-fit | High |
| 2 | Growing family needs space | B/T | Boost beds/schools/yard listings | Critical |
| 3 | Downsizing | B/T | Boost low-maintenance/single-level | High |
| 4 | Retirement | B/T | Boost 55+/amenity/warm-climate | High |
| 5 | Investment cash flow | B | Rank by cap-rate/NOI descending | High |
| 6 | Portfolio diversification | B | Boost stabilized/varied asset types | Medium |
| 7 | Tax deferral (1031) | B | Boost like-kind + timing-fit | Medium |
| 8 | School-zone priority | B/T | Hard-boost target-school listings | Critical |
| 9 | Shorter commute | B/T | Rank by commute-time proximity | High |
| 10 | Multigenerational needs | B/T | Boost ADU/in-law properties | High |
| 11 | Remote-work setup | B/T | Boost home-office/fiber | High |
| 12 | Lifestyle upgrade (luxury) | B | Boost high-end amenity tier | Medium |
| 13 | Affordability / budget-driven | B/T | Sort by value + $/sqft | High |
| 14 | Stop renting (first home) | B | Boost starter + low-cost entry | High |
| 15 | Seasonal escape (snowbird) | B/T | Boost seasonal-available units | High |
| 16 | Vacation / second home | B | Boost lifestyle destinations | Medium |
| 17 | STR income potential | B | Boost STR-permitted zones | High |
| 18 | Business expansion | T | Boost larger sqft / clear-span | Medium |
| 19 | New business launch | T/B | Boost turnkey / built-out space | Medium |
| 20 | Franchise acquisition | B | Boost franchise-resale listings | Medium |
| 21 | Semi-passive income | B | Boost NNN / absentee-run | Medium |
| 22 | Aging-in-place | B | Boost accessibility features | High |
| 23 | Health / air quality | B/T | Boost low-VOC / healthy-home | Future |
| 24 | Pet accommodation | B/T | Hard-filter pet-friendly | High |
| 25 | Privacy / seclusion | B | Boost gated / acreage | Medium |
| 26 | Safety / low-crime | B/T | Boost low-crime-index areas | High |
| 27 | Walkability preference | B/T | Boost high walk-score | Medium |
| 28 | Waterfront dream | B | Boost water-frontage | High |
| 29 | Boat / dock need | B | Hard-filter dock/lift | Medium |
| 30 | Golf access | B | Boost golf-community | Medium |
| 31 | Equestrian need | B | Hard-filter stalls/acreage | Medium |
| 32 | Land banking | B | Boost hold/appreciation land | Medium |
| 33 | Development intent | B | Boost buildable/zoning-fit | Medium |
| 34 | Estate / legacy planning | B | Boost large-lot/legacy assets | Future |
| 35 | Divorce / life change | B/T | Boost fast-avail + flexible | Medium |
| 36 | Move-up (equity roll) | B | Boost next-tier step-up | Medium |
| 37 | Fast possession needed | B/T | Boost immediate-availability | High |
| 38 | Flexible / short lease | T | Boost month-to-month/seasonal | Medium |
| 39 | Long-term stability | T | Boost long-lease-ok listings | Medium |
| 40 | Low move-in cost | T | Sort by low deposit/fees | High |
| 41 | Utilities included | T | Boost rent-includes-utilities | Medium |
| 42 | Furnished need | T | Filter furnished units | Medium |
| 43 | Fast association approval | T | Deprioritize slow-approval | Medium |
| 44 | Energy-cost savings | B/T | Boost solar/efficient | Medium |
| 45 | Insurance affordability | B | Boost hardened/low-flood | High |
| 46 | HOA-fee sensitivity | B | Sort by low/no HOA | Medium |
| 47 | Rezoning / upside bet | B | Boost rezoning-candidate | Future |
| 48 | Owner-user (biz + RE) | B | Boost RE-included business | Medium |
| 49 | Prestige / status address | B | Boost prestige neighborhoods | Future |
| 50 | Community belonging | B/T | Boost active-social community | Future |

---

## Appendix G — Commercial/Investment Intelligence Master List

**G.1** = Investment tags (Beyond-MLS §10.7, 50); **G.2** = Commercial use-fit tags (§10.8, 50). Consumed by Phases 4, 5, 9, 10, 13.

### G.1 — Top 50 Investment Tags (§10.7)

Derived = computed from structured fields; Entered = captured from seller/landlord input.

| # | Investment Tag | Derived/Entered | Signals to an Investor | Priority |
|---|---|---|---|---|
| 1 | `cash_flowing` | Derived | Positive in-place net income | High |
| 2 | `high_cap_rate` | Derived | Above-market yield | High |
| 3 | `value_add` | Derived | Below-market rents / upside | High |
| 4 | `stabilized_asset` | Derived | Full occupancy, in-place income | Medium |
| 5 | `turnkey_investment` | Derived | No rehab, tenant-ready | High |
| 6 | `below_market_rent` | Derived | Rent-growth headroom | Medium |
| 7 | `separately_metered` | Entered | Tenant-paid utilities | High |
| 8 | `low_owner_expense` | Derived | High expense pass-through | Medium |
| 9 | `single_tenant_nnn` | Entered | Passive net-lease income | High |
| 10 | `multi_tenant_diversified` | Entered | Spread tenant-default risk | Medium |
| 11 | `anchored_center` | Entered | Credit anchor stability | Medium |
| 12 | `credit_tenant` | Entered | Investment-grade lessee | Medium |
| 13 | `long_wault` | Derived | Long weighted lease term | Future |
| 14 | `proforma_upside` | Derived | Market-vs-actual income gap | Medium |
| 15 | `strong_dscr` | Derived | Healthy debt coverage | Medium |
| 16 | `favorable_grm` | Derived | Attractive rent multiple | Future |
| 17 | `assumable_financing` | Entered | Below-rate debt transfer | High |
| 18 | `seller_financing` | Entered | Flexible acquisition terms | High |
| 19 | `lease_option_available` | Entered | Control before purchase | Medium |
| 20 | `1031_ready` | Derived | Like-kind timing fit | Medium |
| 21 | `sba_eligible` | Derived | Owner-user financing path | Medium |
| 22 | `owner_user_opportunity` | Derived | Occupy plus income | Medium |
| 23 | `re_included_business` | Entered | Real estate bundled with biz | Medium |
| 24 | `redevelopment_play` | Derived | Teardown / reposition upside | Medium |
| 25 | `rezoning_candidate` | Derived | Entitlement upside | Medium |
| 26 | `development_potential` | Derived | Buildable density | Medium |
| 27 | `land_banking` | Derived | Appreciation hold | Future |
| 28 | `assemblage_parcel` | Entered | Adjacent-lot combination | Future |
| 29 | `subdividable` | Derived | Split-lot potential | Future |
| 30 | `tenant_occupied` | Entered | Income from day one | High |
| 31 | `vacant_reposition` | Derived | Lease-up upside | Medium |
| 32 | `str_income_potential` | Derived | Short-term rental yield | High |
| 33 | `student_housing_income` | Derived | University-driven demand | Future |
| 34 | `voucher_ready` | Entered | Guaranteed subsidized rent | Future |
| 35 | `professionally_managed` | Entered | Hands-off ownership | Medium |
| 36 | `absentee_run` | Entered | Owner-optional operation | Medium |
| 37 | `defensive_use` | Derived | Recession-resistant tenant | Future |
| 38 | `high_traffic_count` | Derived | Retail exposure metric | Medium |
| 39 | `growth_corridor` | Derived | Appreciating submarket | Medium |
| 40 | `opportunity_zone` | Derived | Tax-advantaged federal zone | Medium |
| 41 | `low_vacancy_submarket` | Derived | Strong local demand | Medium |
| 42 | `new_build_low_capex` | Derived | Minimal near-term capex | Medium |
| 43 | `deferred_maintenance` | Derived | Capex risk / discount signal | Medium |
| 44 | `income_verified` | Entered | Financials documented | Medium |
| 45 | `nnn_transparent` | Derived | Clear pass-through structure | Medium |
| 46 | `escalating_rent` | Entered | Built-in rent growth | Medium |
| 47 | `ffe_included` | Entered | Equipment in sale price | Medium |
| 48 | `inventory_included` | Entered | Stock in sale price | Future |
| 49 | `franchise_resale` | Entered | Proven-model acquisition | Medium |
| 50 | `portfolio_scalable` | Derived | Repeatable unit economics | Future |

### G.2 — Top 50 Commercial Tags (§10.8)

Physical/use-fit signals for commercial demand. Property types among CS/CL/Biz/Land.

| # | Commercial Tag | Property Types | Use-Fit Signaled | Priority |
|---|---|---|---|---|
| 1 | `dock_high` | CS/CL | Truck-height loading | High |
| 2 | `grade_level_door` | CS/CL | Drive-in ground access | High |
| 3 | `clear_span` | CS/CL | Unobstructed warehouse floor | High |
| 4 | `high_bay` | CS/CL | Tall-clearance storage | Medium |
| 5 | `heavy_power_3phase` | CS/CL | Industrial electrical service | High |
| 6 | `cold_storage` | CS/CL/Biz | Freezer / refrigerated space | Medium |
| 7 | `drive_thru` | CS/CL/Biz | QSR/retail throughput lane | Medium |
| 8 | `hood_grease_trap` | CL/Biz | Restaurant-ready kitchen | Medium |
| 9 | `vanilla_shell` | CL | Ready-to-fit-out interior | Medium |
| 10 | `gray_shell` | CL | Raw build-out interior | Future |
| 11 | `built_out_office` | CS/CL | Turnkey office layout | Medium |
| 12 | `class_a_space` | CS/CL | Premium office grade | High |
| 13 | `class_b_space` | CS/CL | Value office grade | Medium |
| 14 | `flex_space` | CS/CL | Office + warehouse mix | Medium |
| 15 | `pole_sign` | CS/CL | Highway pylon signage | Medium |
| 16 | `high_visibility` | CS/CL/Biz | Strong street presence | High |
| 17 | `ample_parking` | CS/CL | High parking ratio | High |
| 18 | `rail_served` | CS/Land | Freight rail spur | Future |
| 19 | `corner_location` | CS/CL/Biz | Two-street exposure | Medium |
| 20 | `endcap_unit` | CL | Premium end retail bay | Medium |
| 21 | `anchor_adjacent` | CS/CL | Traffic-driver next door | Medium |
| 22 | `freestanding` | CS/Biz | Standalone building | Medium |
| 23 | `mixed_use` | CS/Land | Live-work-retail blend | Medium |
| 24 | `nnn_lease_ready` | CS/CL | Net-lease structured | High |
| 25 | `gross_lease` | CL | All-inclusive rent | Medium |
| 26 | `percentage_rent` | CL/Biz | Sales-based retail lease | Future |
| 27 | `sublease_available` | CL | Flexible short-term space | Medium |
| 28 | `build_to_suit` | CL/Land | Custom pre-lease build | Medium |
| 29 | `ti_allowance` | CL | Landlord fit-out budget | High |
| 30 | `restaurant_ready` | CL/Biz | F&B-permitted infrastructure | Medium |
| 31 | `medical_ready` | CS/CL | Healthcare fit-out | Medium |
| 32 | `warehouse_ready` | CS/CL | Logistics-capable shell | High |
| 33 | `manufacturing_ready` | CS/CL | Industrial process use | Medium |
| 34 | `retail_storefront` | CS/CL/Biz | Customer-facing frontage | High |
| 35 | `office_suite` | CS/CL | Professional workspace | Medium |
| 36 | `hospitality_ready` | CS/Biz | Hotel/motel use | Future |
| 37 | `self_storage_use` | CS | Storage-facility asset | Future |
| 38 | `truck_court_depth` | CS/CL | Trailer maneuvering room | Future |
| 39 | `fenced_yard_storage` | CS/Land | Outdoor material storage | Medium |
| 40 | `tall_clear_height` | CS/CL | Extra ceiling clearance | Medium |
| 41 | `heavy_floor_load` | CS/CL | Industrial slab capacity | Future |
| 42 | `zoned_commercial` | CS/Land | Commercial entitlement | High |
| 43 | `zoned_industrial` | CS/Land | Industrial entitlement | Medium |
| 44 | `zoned_flexible` | CS/Land | Broad permitted-use zoning | Medium |
| 45 | `impact_fees_paid` | CS/Land | Reduced development cost | Future |
| 46 | `turnkey_business` | Biz | Operating going-concern | High |
| 47 | `liquor_license` | Biz/CL | Alcohol-permitted premises | Medium |
| 48 | `franchise_territory` | Biz | Protected market rights | Future |
| 49 | `owner_will_train` | Biz | Transition support offered | Medium |
| 50 | `conference_ready` | CS/CL | Built meeting-room space | Future |

---

## Appendix H — Neighborhood/Location Intelligence Master List (Beyond-MLS §10.9, Top 50)

AI-derived from the Location DNA pipeline (Google Places POIs, FEMA flood, Census/TIGER schools, commute engine, crime/traffic/topography feeds). All computed, never hand-entered. Consumed by Phases 8, 9, 13.

| # | Neighborhood Tag | AI-Derived Source | Tells a Buyer | Priority |
|---|---|---|---|---|
| 1 | `top_rated_schools` | School-rating + Census zone | Strong education zone | Critical |
| 2 | `short_commute_downtown` | Commute engine | Quick job-center access | High |
| 3 | `walkable_score_high` | POI density + walk index | Daily errands on foot | High |
| 4 | `bike_friendly` | Trail/lane POI | Cyclable street network | Medium |
| 5 | `transit_accessible` | Transit-stop POI | Public transport nearby | Medium |
| 6 | `low_crime_area` | Crime-data feed | Safer neighborhood | High |
| 7 | `near_beach` | Coastline geodata | Beach within easy reach | High |
| 8 | `waterfront_district` | Hydrology geodata | Water-adjacent locale | High |
| 9 | `low_flood_risk` | FEMA flood layer | Insurance-friendly ground | High |
| 10 | `outside_flood_zone` | FEMA zone X | No flood-insurance requirement | High |
| 11 | `restaurant_rich` | Places dining density | Dining options nearby | Medium |
| 12 | `nightlife_nearby` | Places venue density | Evening entertainment near | Medium |
| 13 | `grocery_convenient` | Places supermarket proximity | Daily needs close | High |
| 14 | `hospital_proximity` | Places medical POI | Healthcare access | Medium |
| 15 | `park_adjacent` | Parks geodata | Green space nearby | Medium |
| 16 | `golf_course_nearby` | Places golf POI | Golf access | Medium |
| 17 | `marina_nearby` | Places marina POI | Boating access | Medium |
| 18 | `shopping_district` | Retail POI cluster | Retail hub near | Medium |
| 19 | `employment_hub` | Commute/jobs data | Near major employers | High |
| 20 | `university_adjacent` | Places campus POI | College-town setting | Medium |
| 21 | `airport_proximity` | Airport geodata | Travel convenience | Medium |
| 22 | `quiet_residential` | POI sparsity + zoning | Low-noise streets | Medium |
| 23 | `family_friendly_area` | Schools + parks + demo | Family-oriented area | High |
| 24 | `active_adult_area` | 55+ community density | Retiree-friendly locale | Medium |
| 25 | `up_and_coming` | Price-trend + permits | Appreciating submarket | Medium |
| 26 | `established_prestige` | Value + tenure data | Prestige address | Medium |
| 27 | `historic_district` | Register/zoning overlay | Heritage character | Medium |
| 28 | `arts_cultural_zone` | Museum/gallery POI | Cultural scene near | Future |
| 29 | `entertainment_district` | Venue POI cluster | Events and venues near | Future |
| 30 | `low_traffic_street` | Road-class data | Calm residential street | Medium |
| 31 | `high_traffic_corridor` | Traffic-count data | Commercial exposure | Medium |
| 32 | `dark_sky_area` | Light-pollution data | Starry night skies | Future |
| 33 | `agricultural_area` | Land-use data | Rural/farm surroundings | Medium |
| 34 | `conservation_adjacent` | Protected-land geodata | Nature buffer | Medium |
| 35 | `gated_enclave` | Community-boundary data | Secured neighborhood | Medium |
| 36 | `master_planned` | Community data | Planned amenity community | Medium |
| 37 | `golf_cart_zone` | Local-ordinance data | Cart-legal streets | Medium |
| 38 | `snowbird_popular` | Seasonal-occupancy data | Winter-resident area | Medium |
| 39 | `str_permitted_zone` | Local STR ordinance | Vacation rental allowed | High |
| 40 | `no_hoa_area` | Parcel/HOA data | HOA-free freedom | Medium |
| 41 | `high_elevation` | Topography / DEM | Flood-resilient ground | Medium |
| 42 | `coastal_evac_zone` | Emergency-mgmt layer | Hurricane-evac exposure | Medium |
| 43 | `new_growth_zone` | Permit/construction data | Active new development | Medium |
| 44 | `mature_tree_canopy` | NDVI/tree imagery | Established shade cover | Future |
| 45 | `pet_amenity_area` | Dog-park/vet POI | Pet amenities near | Medium |
| 46 | `fitness_wellness_hub` | Gym/studio POI | Fitness options near | Future |
| 47 | `low_tax_area` | Millage data | Tax-favorable locale | Medium |
| 48 | `flood_history_clear` | Claims/history data | No recorded flooding | Medium |
| 49 | `emerging_food_scene` | New-dining permits | Trending dining area | Future |
| 50 | `commercial_corridor` | Zoning + POI mix | Business-district context | Medium |

---

## Appendix I — Marketing Intelligence Master List (Beyond-MLS §10.10, Top 50)

Punchy outbound-copy phrases with channel routing. Best Channel = listing headline / social / email / Ask AI. Consumed by Phases 11 & 12. Subject to the marketing-integrity guardrail (Phase 11): a phrase may only be used when its canonical value + confidence support it.

| # | Marketing Tag | Best Channel | Audience It Attracts | Priority |
|---|---|---|---|---|
| 1 | "Move-In Ready" | Listing headline | Relocating / first-time buyers | High |
| 2 | "Priced to Sell" | Listing headline | Bargain hunters | High |
| 3 | "Waterfront Paradise" | Social | Waterfront lovers / boaters | High |
| 4 | "Boater's Dream" | Social | Boat owners | Medium |
| 5 | "Golfer's Retreat" | Social | Golfers | Medium |
| 6 | "Chef's Kitchen" | Listing headline | Entertainers / luxury | Medium |
| 7 | "Resort-Style Living" | Social | Amenity seekers | Medium |
| 8 | "Turnkey Investment" | Email | Investors | High |
| 9 | "Cash Flow Machine" | Email | Income investors | High |
| 10 | "Hurricane-Ready & Insured" | Listing headline | FL safety-minded buyers | High |
| 11 | "Solar-Powered Savings" | Ask AI | Eco / cost-conscious | Medium |
| 12 | "Room to Grow" | Listing headline | Growing families | High |
| 13 | "Work-From-Home Ready" | Social | Remote workers | High |
| 14 | "In-Law Suite Included" | Listing headline | Multigen buyers | High |
| 15 | "Gated & Private" | Listing headline | Privacy seekers | Medium |
| 16 | "Steps to the Beach" | Social | Beach lifestyle | High |
| 17 | "Sunset Views" | Social | Lifestyle / luxury | Medium |
| 18 | "Lock-and-Leave" | Email | Snowbirds / second-home | Medium |
| 19 | "House-Hacker Income" | Ask AI | Owner-occupant investors | High |
| 20 | "Brand-New Construction" | Listing headline | New-build buyers | High |
| 21 | "Historic Charm" | Social | Character seekers | Medium |
| 22 | "Fully Furnished" | Email | Relocating / seasonal | Medium |
| 23 | "Pet Paradise" | Social | Pet owners | Medium |
| 24 | "Top-Rated Schools" | Listing headline | Families | Critical |
| 25 | "Minutes to Downtown" | Email | Commuters | High |
| 26 | "No HOA" | Listing headline | Freedom seekers | Medium |
| 27 | "Bring Your Boat & RV" | Social | Toy owners | Medium |
| 28 | "Equestrian Estate" | Social | Horse owners | Medium |
| 29 | "Development Opportunity" | Email | Developers | Medium |
| 30 | "Owner Financing Available" | Ask AI | Credit-flexible buyers | High |
| 31 | "Assumable Low-Rate Loan" | Email | Rate-sensitive buyers | High |
| 32 | "Turnkey Business For Sale" | Email | Entrepreneurs | High |
| 33 | "Prime Retail Location" | Listing headline | Retail tenants | Medium |
| 34 | "Warehouse & Distribution Ready" | Email | Industrial tenants | Medium |
| 35 | "Class-A Office Space" | Email | Professional tenants | Medium |
| 36 | "High-Visibility Corner" | Social | Retail / business buyers | Medium |
| 37 | "Seasonal Rental Income" | Email | STR investors | High |
| 38 | "Snowbird Special" | Social | Seasonal renters | Medium |
| 39 | "Accessible & Single-Level" | Ask AI | Mobility / aging buyers | High |
| 40 | "Energy-Efficient Home" | Ask AI | Eco / cost-conscious | Medium |
| 41 | "Private Pool Oasis" | Social | Pool lovers | High |
| 42 | "Screened Lanai Living" | Social | FL outdoor-lifestyle buyers | Medium |
| 43 | "Luxury Estate" | Social | High-end buyers | High |
| 44 | "First-Time Buyer Friendly" | Email | First-timers | High |
| 45 | "Downsizer's Delight" | Email | Empty-nesters | Medium |
| 46 | "Rare Acreage" | Social | Land / privacy seekers | Medium |
| 47 | "Build Your Dream Home" | Social | Land buyers | Medium |
| 48 | "1031-Exchange Ready" | Email | Exchange investors | Medium |
| 49 | "Ask AI: Is This Home Right for You?" | Ask AI | Undecided browsing buyers | High |
| 50 | "Just Listed — Bid Your Offer" | Social | All active buyers | Critical |

---

## Appendix J — AI Score Master List (Beyond-MLS §10.3, Top 25)

The 0–100 AI-derived scores ("Compute, Don't Ask"), with key canonical inputs and whether the score is symmetric (property score ↔ buyer/tenant demand weight). Each is stored with an explanation string + confidence + version (Phase 5). Consumed by Phases 5, 9, 13.

| # | Score (0–100) | Computed From (key inputs) | Symmetric? | Becomes | Priority |
|---|---|---|---|---|---|
| 1 | Luxury Score | Price-vs-comps, finishes (vision), lot/view prestige, micro-location, amenities | Yes | Luxury tier; targeting; marketing | Critical |
| 2 | Family-Fit Score | School ratings, beds/baths, yard, safety, community amenities, quiet-street | Yes | Family segment match | Critical |
| 3 | Walkability Score | POI density (grocery/dining/school), street connectivity, sidewalks | No | Neighborhood DNA; search facet | Critical |
| 4 | Investment Score | Cap rate, DSCR, rent-vs-market gap, appreciation trend, expense ratio | Yes | Investor match; ranking | Critical |
| 5 | Cash-Flow Score | In-place NOI, debt service at market rate, vacancy, metering, expenses | Yes | Investment DNA; investor filter | Critical |
| 6 | Commute-Friendliness Score | Isochrones to buyer's Important Places, transit access, road congestion | Yes | Personalized match | Critical |
| 7 | School-Fit Score | Assigned district ratings, proximity, zone stability, program match | Yes | Family match; Ask AI | High |
| 8 | Turnkey Score | Condition (vision), systems age, renovation signals, lease-in-place | Yes | Move-in match; investor filter | High |
| 9 | Waterfront-Lifestyle Score | Water access type, depth/boatability, dock/lift, view, frontage feet | Yes | Waterfront targeting | High |
| 10 | Climate-Resilience Score | Flood zone, storm-surge, wind mitigation, elevation, insurance/claims history | Yes | Insurability signal; disclosure | High |
| 11 | Remote-Work Score | Office/flex rooms, broadband speed, quiet/noise, dedicated space | Yes | Post-2020 targeting | High |
| 12 | Safety Score | Area crime index, community security (gated/guard), lighting, street type | No | Neighborhood DNA; family input | High |
| 13 | STR-Potential Score | STR regulatory status, nightly-rate comps, occupancy est., location draw | Yes | STR-investor targeting | High |
| 14 | Pet-Friendliness Score | Fenced yard, dog amenities/parks, pet policy, floor level, community rules | Yes | Pet-owner match | High |
| 15 | Accessibility Score | Single-level, step-free entry, door/hall width, roll-in bath, elevator | Yes | Underserved-audience match | High |
| 16 | Energy-Efficiency Score | Solar ownership, HERS/insulation, window type, HVAC age, orientation | Yes | Low-utility marketing; TCO | High |
| 17 | Privacy Score | Lot buffer/setback, tree cover, neighbor proximity, unit position, gating | Yes | Luxury/retreat marketing | High |
| 18 | Vacation-Home Score | Beach/attraction proximity, lock-and-leave, seasonal appeal, view, amenities | Yes | Second-home targeting | High |
| 19 | Retirement Score | Single-level, healthcare proximity, 55+ community, low-maint, walkability | Yes | Active-adult targeting | Medium |
| 20 | Lock-and-Leave Score | Maintenance burden, security, HOA-covered exterior, condo/turnkey status | Yes | Snowbird match | Medium |
| 21 | Wellness Score | Air quality, trails/green space, gym/spa amenities, natural light, quiet | Yes | Health-conscious targeting | Medium |
| 22 | Entertainment Score | Nightlife/dining/culture proximity, entertainer layout, outdoor space | Yes | Young-professional target | Medium |
| 23 | Foodie Score | Restaurant density/quality nearby, chef-kitchen, food-hub proximity | No | Culinary-lifestyle targeting | Medium |
| 24 | Appreciation Score | Submarket momentum, permits, demographic shift, infrastructure plans | Yes | Long-hold investor match | Medium |
| 25 | Aging-in-Place Score | Accessibility features, single-level, healthcare proximity, safety, low-maint | Yes | Longevity targeting; Ask AI | Medium |

> **Beyond the Top 25:** the Beyond-MLS §8 "Compute, Don't Ask" catalog also names further scores that Phase 5 should carry as it matures — Outdoor-Lifestyle, Entertaining/Hosting, Golf, Equestrian, Multigenerational-Fit, First-Time-Buyer-Fit, Snowbird-Fit, Value-for-Money, Renovation-Upside, Smart-Home, Nightlife, Kid-Safety, Noise/Tranquility, Land-Buildability, Business-Turnkey, Commercial-Fit-Out — plus the §7 investment metrics (Cap Rate, Cash-on-Cash, GRM, DSCR, Break-Even Occupancy, Land-to-Improvement Ratio, Cash-Flow Stability). These are enumerated in Phase 5's task list.

---

## Appendix K — Phase dependency graph

```
Phase 0 (done)
  ├─> Phase 1 (required fields — full lifecycle) ─┐
  ├─> Phase 2 (optional fields — full lifecycle) ─┤
  │        └─> Phase 3 (canonical data dictionary)
  │              └─> FOUNDATION CLUSTER (source-neutral):
  │                    Phase 3.5 (Canonical Field Mapping)        ★ V2.0
  │                    Phase 3.6 (Canonical Entity Dictionary)    ★ V2.2
  │                    Phase 3.7 (Master Intelligence Taxonomy)   ★ V2.2
  │                    Phase 3.8 (AI Automation & Data Quality)   ★ V2.2
  │                    Phase 3.9 (Universal Knowledge Graph)      ★ V2.2  ◄── central intelligence layer
  │                      └─> INTELLIGENCE CLUSTER (writes to graph):
  │                            Phase 4  (Metadata Engine)
  │                            Phase 5  (Property DNA)
  │                            Phase 6  (Buyer DNA + Location Preference DNA)
  │                            Phase 7  (Tenant DNA + Location Preference DNA)
  │                            Phase 7.5 (Seller DNA & Landlord DNA)        ★ V2.2
  │                            Phase 8  (Location DNA + symmetric Location Preference DNA)
  │                            Phase 8.5 (Lifestyle/Luxury/Investment/Commercial/Community DNA) ★ V2.2
  │                            Phase 9  (Target Audience Intelligence)
  │                            Phase 9.5 (Story Engine — 12 story types)    ★ V2.2
  │                      └─> APPLICATION CLUSTER (reads graph):
  │                            Phase 10 (Recommendations)
  │                            Phase 11 (Marketing Intelligence)
  │                            Phase 12 (Universal Ask AI)
  │                            Phase 13 (Matching V2 + Behavioral Learning) ★ V2.2
  │                            Phase 14 (Analytics/Explainability)
  │                      └─> GOVERNANCE & INGESTION:
  │                            Phase 14.5 (Compliance/Responsible-AI/Fair-Housing/Privacy/Governance) ★ V2.2
  │                            Phase 14.7 (Intelligent Multi-Source Import Pipeline)                    ★ V2.2
  └──────────────────────────────────────────────────────────> Phase 15 (Launch audit — canonical + graph + compliance complete)

Cross-cutting: Architecture & Scalability Requirements (front matter) govern all phases; verified at Phase 15.
```

★ **Two keystones.** **Phase 3.5** unifies all *sources* into one canonical vocabulary; **Phase 3.9** unifies all *intelligence* into one Knowledge Graph. Everything in the Foundation cluster produces canonical data + graph schema; the Intelligence cluster writes nodes/edges; the Application cluster reads them; Governance gates exposure. New data sources plug in at Phase 3.5 and new records flow the whole chain automatically via Phase 14.7 — without touching any consumer.

## Appendix L — Deliverable index

| Phase | Deliverable file |
|---|---|
| 0 | (existing) field-audit, stellar-master-field-comparison, stellar-gap-analysis, beyond-mls-property-dna-roadmap |
| 1 | Code: required Stellar fields implemented **+ canonical/Stellar mapping stubs** (no new doc) |
| 2 | Code: high-value optional fields implemented **+ canonical/Stellar mapping stubs** (no new doc) |
| 3 | `docs/master-data-dictionary.md` (source-neutral canonical dictionary) |
| **3.5** | **`docs/canonical-field-mapping-spec.md`** ★ V2.0 |
| **3.6** | **`docs/canonical-entity-dictionary.md`** ★ V2.2 |
| **3.7** | **`docs/master-intelligence-taxonomy.md`** ★ V2.2 |
| **3.8** | **`docs/ai-automation-spec.md`** ★ V2.2 |
| **3.9** | **`docs/knowledge-graph-spec.md`** ★ V2.2 |
| 4 | `docs/metadata-mapping-spec.md` |
| 5 | `docs/property-dna-spec.md` |
| 6 | `docs/buyer-dna-spec.md` |
| 7 | `docs/tenant-dna-spec.md` |
| **7.5** | **`docs/seller-landlord-dna-spec.md`** ★ V2.2 |
| 8 | `docs/location-dna-expansion-spec.md` (incl. symmetric Location Preference DNA) |
| **8.5** | **`docs/domain-dna-suite-spec.md`** (Lifestyle/Luxury/Investment/Commercial/Community DNA) ★ V2.2 |
| 9 | `docs/target-audience-intelligence-spec.md` |
| **9.5** | **`docs/story-engine-spec.md`** ★ V2.2 |
| 10 | `docs/recommendation-engine-spec.md` |
| 11 | `docs/marketing-intelligence-spec.md` |
| 12 | `docs/ask-ai-expansion-spec.md` + field-coverage checklist |
| 13 | `docs/matching-engine-v2-spec.md` (incl. behavioral-learning layer) |
| 14 | `docs/analytics-explainability-spec.md` |
| **14.5** | **`docs/compliance-responsible-ai-spec.md`** + launch compliance checklist ★ V2.2 |
| **14.7** | **`docs/import-pipeline-spec.md`** ★ V2.2 |
| Cross-cutting | **`docs/architecture-scalability-requirements.md`** ★ V2.2 |
| 15 | `docs/launch-audits/v1-release-candidate-audit.md` |
| **16 (future)** | **`docs/decision-support-intelligence-spec.md`** ★ V2.3 (post-launch) |
| **Appendix M** | **`docs/testing-strategy.md`** ★ V2.3 (permanent testing strategy) |
| **Appendix N** | **`docs/universal-intelligence-coverage-matrix.md`** ★ V2.3 (permanent field↔intelligence QA matrix) |

---

## Appendix M — Permanent Testing Strategy

**(New in Version 2.3.)** Every phase and every workflow must ship with automated tests. Tests run on **SQLite in-memory** (per CLAUDE.md, not PostgreSQL) via `php artisan test`. **Every future phase must define its corresponding automated tests** as part of its Deliverable (this is stage 20 of the per-field full-lifecycle definition of done, generalized to every engine).

**Create:** `docs/testing-strategy.md`

| Test type | What it covers | Applies to |
|---|---|---|
| **Unit testing** | Pure functions/services in isolation (mappers, normalizers, score calculators, tag derivers) | All phases; e.g. `AgentBidMapperService`, `*BidMatchScoreHelper` |
| **Integration testing** | Multiple components together (save → meta → canonical read → consumer) | Phases 1–3.9, 14.7 |
| **Regression testing** | Guard against re-breaking fixed behavior; change-scope allowlists | All phases; existing suite pattern |
| **MLS import testing** | Raw source record → full pipeline; fixtures per source (Stellar/RentCast/ATTOM/CSV) | Phase 14.7, 3.5 |
| **Canonical mapping testing** | Source→canonical normalization + round-trip parity (BYO vs Stellar → same canonical value) | Phase 3.5 |
| **Metadata testing** | Tag rules fire correctly from canonical values; no orphan tags | Phase 4 |
| **DNA testing** | Score inputs/weights/outputs; explanation/confidence/version present; identical across sources | Phases 5–8.5 |
| **Story testing** | Grounding rule (fact vs inference), no unsupported assertions, regeneration on change | Phase 9.5 |
| **Ask AI testing** | Coverage checklist; answers from canonical/graph; states-when-unavailable; provenance/confidence | Phase 12 |
| **Matching testing** | Gates vs weights; symmetric axes; config weights sum; behavioral-learning re-weighting bounds | Phase 13 |
| **Recommendation testing** | Correct recs per input; completeness/missing-data logic | Phase 10 |
| **Analytics testing** | Explainability paths; completeness/confidence scores | Phase 14 |
| **Performance testing** | Search/matching/vector retrieval at 100k+ listings; ingestion throughput; DNA recompute latency | Architecture & Scalability Requirements; verified at Phase 15 |
| **Compliance testing** | Anti-steering firewall; no protected-class proxies; fact-vs-inference labeling | Phase 14.5 |

**Success criteria:** Every phase's Deliverable includes its automated tests; the full-lifecycle stage 20 (Tests) is satisfied per field/engine; Phase 15 does not pass until the above suites are green.

---

## Appendix N — Universal Intelligence Coverage Matrix

**(New in Version 2.3.)** This appendix is the **permanent quality-assurance / validation document for the entire Intelligence Platform** and the **validation checklist for every future roadmap revision.** It enforces Principles 1, 5, 15, 16, and 19: **every canonical field must intentionally feed at least one intelligence system, and every intelligence system must have sufficient canonical inputs.** It runs in **both directions** — field→systems (no dead data) and system→fields (no starved engine).

### N.1 — How to use this matrix

1. **Populate one row per canonical field** from the Canonical Data Dictionary (Phase 3) after Phases 1–2 land. Mark each intelligence column ✓ (consumes), ○ (derived/indirect), or blank (not used). Columns:

   `Canonical Field | Metadata | Property DNA | Buyer DNA | Tenant DNA | Seller DNA | Landlord DNA | Location DNA | Lifestyle DNA | Luxury DNA | Investment DNA | Commercial DNA | Community DNA | Story Engine | Target Audience | Marketing Intelligence | Ask AI | Matching | Analytics | Decision Support`

2. **Dead-data rule (field→systems):** a field is **not** expected to feed every system, but **every field must feed at least one**. If a field's row is entirely blank, document why in a **Justification** column; if no valid reason exists, reconsider whether the field belongs in the platform (Principles 1/16/19). *(Genuinely valid blank-ish reasons: legal/compliance-only capture, identity/join keys, provenance/audit metadata — these still "belong" but should be tagged as such, not silently unused.)*

3. **Starved-engine rule (system→fields):** for **each intelligence column**, confirm it has enough canonical inputs to produce accurate, explainable output. Where an engine is under-fed, record the gap in **N.3** and recommend the additional canonical fields or derived metadata needed.

### N.2 — Illustrative rows (pattern to replicate for every field)

*(Representative examples using canonical fields already in the roadmap; the implemented appendix enumerates the full dictionary. ✓ = direct consumer, ○ = indirect/derived.)*

| Canonical Field | Meta | Prop DNA | Buyer DNA | Tenant DNA | Seller DNA | Landlord DNA | Loc DNA | Lifestyle | Luxury | Investment | Commercial | Community | Story | Target Aud. | Marketing | Ask AI | Matching | Analytics | Decision Support |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `waterfront` / `water_access` | ✓ | ✓ | ○ | ○ |  |  | ✓ | ✓ | ✓ | ○ |  | ○ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `lot_size_sqft` | ✓ | ✓ | ○ |  |  |  | ○ | ○ | ○ | ✓ | ✓ |  | ○ | ○ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `green_energy_generation` / `solar_panel_ownership` | ✓ | ✓ | ○ |  | ○ |  |  | ✓ | ○ | ○ |  |  | ✓ | ✓ | ✓ | ✓ | ✓ | ○ | ✓ |
| `application_fee` (rental) | ✓ |  |  | ✓ |  | ✓ |  |  |  | ○ |  |  | ○ | ○ | ○ | ✓ | ✓ | ✓ | ✓ |
| `lease_price_unit` (commercial) | ✓ | ○ |  | ○ |  | ○ |  |  |  | ✓ | ✓ |  | ○ | ○ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `ownership_entity_structure` (business) | ✓ | ○ | ○ |  | ○ |  |  |  |  | ✓ | ✓ |  | ○ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `front_exposure` | ✓ | ✓ | ○ |  |  |  | ○ | ✓ | ○ |  |  |  | ✓ | ○ | ✓ | ✓ | ✓ | ○ | ○ |
| `parcel_id` (identity/join key) |  |  |  |  |  |  | ○ |  |  |  |  |  |  |  |  | ○ |  | ○ |  |
| `commute` (work/school ZIP + max min + mode) |  |  | ✓ | ✓ |  |  | ✓ |  |  |  |  | ○ | ○ | ○ | ○ | ✓ | ✓ | ✓ | ✓ |

*(The `parcel_id` row shows a legitimately sparse field: it is an identity/entity-resolution key (Phase 3.6), not a consumer signal — tagged in Justification as "identity/join key," which is why its row is mostly blank yet valid.)*

### N.3 (Direction B) — System → Required Inputs (no starved engine)

The matrix is **bidirectional.** Direction **A** (N.1–N.2) is *field → systems* (no dead data). Direction **B** (this table) is *system → inputs*: for **each intelligence engine**, record its **required** canonical fields, **optional** canonical fields, **derived metadata** it uses, its **confidence requirement**, and any **missing inputs** with a recommendation. An engine that lacks sufficient reliable inputs must not ship without a logged gap + remediation. Seeded observations (completed against the full dictionary during Phase 3/3.5):

| Intelligence engine | Required canonical inputs | Optional canonical inputs | Derived metadata used | Confidence requirement | Missing inputs → recommendation |
|---|---|---|---|---|---|
| Metadata Engine | core physical + location fields | most fields | — (it produces metadata) | source confidence per field | — (strong base) |
| Property DNA | beds/baths/sqft, condition, features, waterfront, pool | style, exposure, rooms | vision/NLP tags (3.8) | med-high; explanation required | vision-derived condition once image understanding ships |
| Buyer / Tenant DNA | purpose, budget, must-haves, deal-breakers | lifestyle/commute prefs | inferred prefs | med; symmetric axes | wire `BuyerTenantDnaGenerator` for offer listings; capture explicit motivations (Appendix F) |
| Seller / Landlord DNA | selling/leasing motivation, urgency, flexibility | financing/terms openness | inferred ideal-buyer/tenant | med | capture motivation/urgency/flexibility as structured fields (Phase 7.5) |
| Location Intelligence (Location DNA + Location Preference DNA) | property location / Search Areas / Important Places / commute | polygons, radius, ZIP/city/county | POI/flood/school/commute derivations | med-high; provenance + freshness | structure map Search Areas + Important Places (not free text) — Phases 6/7/8 |
| Lifestyle / Luxury / Community DNA | features, amenities, community features, price-vs-comps | architectural style, finish quality | lifestyle/luxury/community tags | med | architectural-style + finish-quality + amenity depth (Phases 2, 3.7) |
| Investment / Commercial DNA | price, NOI/cap rate, rents, tenancy, dock config | metering, pass-through, signage | return metrics | med-high (financials) | individually-metered utilities, dock config, tenancy, pass-through structure (Phase 2) |
| Story Engine | any grounded canonical field + DNA + taxonomy | all | all metadata/tags | grounding + confidence per claim | none new — composes existing |
| Target Audience | DNA scores + metadata tags | motivations | audience signals | med | none new — derives from DNA/tags |
| Marketing Intelligence | stories, DNA, audiences, taxonomy | market data | marketing tags | claim-safety via confidence | none new — consumes stories/DNA/audiences |
| Ask AI | canonical fields + graph outputs | all | all | states-when-unavailable | register every new field (Phase 12 checklist) |
| Universal Matching | canonical listing + criteria fields, DNA suite, gates | stories, audiences | metadata tags | source confidence + completeness | behavioral signals (Phase 13 learning loop) improve over time |
| Analytics / Decision Support | intelligence outputs + provenance/confidence | behavioral signals | completeness/confidence scores | explanation paths required | completeness + confidence scoring (Phase 3.8) as inputs |

### N.4 — Compounding-improvement opportunities (chain of value)

The matrix also surfaces where one layer can lift the next; validate these chains each revision:

- **Existing fields → additional metadata** (Phase 3.8 inferred metadata + NLP on descriptions).
- **Metadata → better DNA** (richer tags sharpen DNA scores).
- **DNA → better Stories** (higher-confidence DNA yields richer, grounded narratives).
- **Stories → better Marketing** (narrative backbone for channel copy).
- **Marketing → better Recommendations** (which angle/audience performs).
- **Recommendations → better Matching** (surface strongest fits).
- **Matching → better Ask AI** ("why is this a match?" answered from match output).
- **Analytics → the Platform Learning Loop** (outcomes re-weight metadata/DNA/matching over time — Principle 17).

### N.5 — Governance

- **When to run:** after Phases 1–2 (initial population), at Phase 15 (launch gate — no unjustified dead field; no starved engine), and at **every future roadmap revision** (permanent validation checklist).
- **Deliverable:** `docs/universal-intelligence-coverage-matrix.md` (the fully-populated matrix; this appendix is its specification and template).
- **Pass criteria:** every canonical field feeds ≥1 intelligence system or carries a documented valid justification; every intelligence system has sufficient inputs or a logged gap + remediation; the N.4 chains are reviewed.

**Do-not-do warnings:** Do NOT keep a field with a blank row and no justification (dead data — Principles 1/16/19). Do NOT ship an intelligence system that is starved of inputs without logging the gap. Do NOT let the matrix drift from the Canonical Data Dictionary (Phase 3) — regenerate it when the dictionary changes.

---

*This roadmap (Version 2.4, FROZEN) is documentation only. No code, Blade, Livewire, migration, or config file was modified in producing it, and nothing was committed. It is the permanent, frozen architectural blueprint for Bid Your Offer as one unified Real Estate Intelligence Platform, executed phase-by-phase; each phase's Dependencies must be satisfied before that phase begins. Version 2.4 integrates the final pre-freeze additions (Location Intelligence Engine as first-class; bidirectional Coverage Matrix; Principles 21–23) on top of Version 2.3's architectural principles, Version 2.2's V2.1-review integration, and Version 2.0's Canonical Data Model: all data sources unify through one canonical vocabulary (Phase 3.5) and one entity model (Phase 3.6), all intelligence unifies in one Knowledge Graph (Phase 3.9), one Universal Matching Engine serves every source bidirectionally (Phase 13), every field completes its full lifecycle before being considered complete, and every intelligence layer consumes canonical data — never raw source fields. Transaction Engine, Marketplace, and Operations are out of scope here and are to be captured as separate roadmaps when that work begins (see Scope Boundary; none written yet). The Architecture & Scalability Requirements, the Permanent Development Principles (1–23), the UI/UX Standards, the Testing Strategy (Appendix M), and the Universal Intelligence Coverage Matrix (Appendix N) govern every phase.*

---

## Final Confirmation

**The Version 2.x roadmap is considered architecturally complete for Version 1.0.**

**The roadmap is now frozen as the implementation blueprint.**

**Future enhancements should be managed through versioned roadmap updates based on implementation experience and real-world user feedback.**

**Planning is complete.**

**Implementation should begin.**
