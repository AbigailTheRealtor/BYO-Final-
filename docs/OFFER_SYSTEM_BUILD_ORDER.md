# Offer System Build Order

This document defines the required sequence for building the Offer System. Phases must be executed in order. No phase may be skipped or partially completed before the next phase begins.

---

## Phase 0 — Governance Docs *(current)*

**Goal:** Establish documentation, guardrails, and protected-area boundaries before any code is written.

**Deliverables:**
- `docs/OFFER_SYSTEM_GOVERNANCE.md`
- `docs/OFFER_SYSTEM_BUILD_ORDER.md`
- `docs/OFFER_SYSTEM_DO_NOT_TOUCH.md`

**Exit criteria:** All three documents exist and are reviewed. No application code, migrations, or UI has been created.

---

## Phase 1A — Offer Architecture Foundation

**Goal:** Create the foundational data layer for the Offer System. No UI is built in this phase.

**Deliverables:**
- Database tables for offers (per listing type as needed).
- Offer state machine definition (e.g., `draft`, `submitted`, `countered`, `accepted`, `rejected`, `withdrawn`).
- Base Offer model(s) with relationships to existing listing models.
- Core service class(es) for offer lifecycle transitions.
- Feature flag or guard to prevent offer routes from being accessible until Phase 1B is complete.

**Ordering rules:**
- No UI (Livewire, Blade, or otherwise) may be built before this phase is complete.
- No counter-offer logic before Phase 2.
- No AI integration before Phase 7.

**Exit criteria:** Tables exist, model relationships resolve correctly, state machine transitions are unit-tested.

---

## Phase 1B — Offer Creation from Existing Listing Terms

**Goal:** Allow a buyer/tenant to submit an offer pre-populated from the seller's/landlord's published listing terms.

**Deliverables:**
- Offer submission interface that reuses existing listing field definitions, existing EAV structures, and existing term groupings wherever possible. The Offer System must not create a parallel listing-form architecture.
- Pre-fill logic sourcing seller/landlord preferred terms as starting defaults (read-only from existing listing records).
- Offer submission flow writing to the Phase 1A tables.
- Basic offer view page for the submitting party.

**Ordering rules:**
- Phase 1A foundation tables and state machine must be complete.
- No counter-offer UI before Phase 2.
- Does not modify existing listing creation forms (see `OFFER_SYSTEM_DO_NOT_TOUCH.md`).

**Exit criteria:** A buyer/tenant can submit an offer on a listing; the offer is stored and viewable by both parties.

---

## Phase 2 — Counter Engine

**Goal:** Allow either party to issue a structured counter-offer against a submitted offer.

**Deliverables:**
- Counter-offer creation from an existing offer (field-level diffing).
- Counter state recorded in the offer state machine.
- Thread/chain view showing the progression of offer → counter → counter.
- Notifications (in-app or email) when a counter is issued.

**Ordering rules:**
- Phase 1B must be complete; offers must be submittable before counters are meaningful.
- No offer comparison view before Phase 3.

**Exit criteria:** Both parties can counter; the full offer chain is stored and viewable.

---

## Phase 3 — Offer Comparison View

**Goal:** Give the listing owner (seller/landlord) a side-by-side view of all active offers on a listing.

**Deliverables:**
- Offer comparison table/grid showing key terms across multiple offers.
- Sortable and filterable columns.
- Visual highlight of best-value terms per field.
- Links to individual offer detail and counter-offer actions.

**Ordering rules:**
- Phases 1A, 1B, and 2 must be complete.
- No accepted offer summaries before Phase 4.

**Exit criteria:** A seller/landlord can view and compare all offers on their listing in a single screen.

---

## Phase 4 — Accepted Offer Summaries

**Goal:** When an offer is accepted, generate and store a structured summary of the agreed terms.

**Deliverables:**
- Acceptance action that transitions offer state to `accepted` and locks all other offers.
- Accepted Offer Summary record written to the database.
- Summary view page (for both parties) displaying agreed terms with the required legal disclaimer (see `OFFER_SYSTEM_GOVERNANCE.md`).
- Invalidation of any existing cached bid/offer PDFs for the listing.

**Ordering rules:**
- Phases 1A–3 must be complete.
- PDF export not available until Phase 5.
- e-sign routing not available until Phase 6.

**Exit criteria:** Accepting an offer stores a summary, locks competing offers, and displays a compliant summary view.

---

## Phase 5 — PDF Generation

**Goal:** Export the Accepted Offer Summary as a PDF for record-keeping.

**Deliverables:**
- PDF template for the Accepted Offer Summary using `barryvdh/laravel-dompdf`.
- Download endpoint accessible to both parties.
- Legal disclaimer text included on every PDF page/footer.
- Cache invalidation aligned with existing accepted bid summary cache patterns.

**Ordering rules:**
- Phase 4 (Accepted Offer Summaries) must be complete.
- e-sign integration not available until Phase 6.

**Exit criteria:** Both parties can download a well-formatted PDF of the accepted offer summary.

---

## Phase 6 — Third-Party E-Sign Integration

**Goal:** Route the Accepted Offer Summary through an approved electronic-signature workflow.

**Deliverables:**
- Integration with a compliant e-sign provider (provider TBD).
- Envelope creation from the Accepted Offer Summary data.
- Signature status tracking (pending, signed, declined).
- Completed/signed document stored and accessible from the platform.
- Updated legal disclaimer reflecting that a signed document from this workflow constitutes a binding agreement (subject to applicable law).

**Ordering rules:**
- Phase 5 (PDF generation) must be complete before e-sign integration begins.
- AI offer analysis must not begin before this phase is complete (AI should not operate on unsigned/informal summaries as if they are contracts).

**Exit criteria:** An accepted offer can be sent for e-signature; completed signed documents are stored on the platform.

---

## Phase 7 — AI Offer Analysis

**Goal:** Add AI-powered explanation and analysis tools to help users understand offer terms and differences.

**Ordering rules:**
- **No AI features may be built before structured offer data exists (Phase 1A) and accepted offer summaries are functional (Phase 4).**
- AI must operate on structured, machine-readable offer fields — not free-form text.
- AI must comply with all constraints in `OFFER_SYSTEM_GOVERNANCE.md` (no autonomous acceptance/rejection, no legal advice, no independent negotiation).

**Deliverables:**
- Plain-language explanation of individual offer fields on demand.
- Summary of differences between an offer and a counter-offer.
- Flag unusual or missing fields for human review.
- AI disclaimer displayed alongside every AI-generated output.

**Exit criteria:** AI features are available in the UI; all outputs include required disclaimers; no AI action can accept, reject, or modify an offer without explicit human confirmation.

---

## Global Ordering Rules

1. **No phase may be skipped.** Each phase builds directly on the data structures, state machine, and UI established by all prior phases.
2. **No UI before foundation.** No Livewire components, Blade offer forms, or offer-related routes may be introduced before Phase 1A foundation tables and models exist and are tested.
3. **No AI before structured data.** AI integration (Phase 7) must not begin before Phase 1A (structured offer tables) and Phase 4 (accepted offer summaries) are complete.
4. **No destructive migrations.** Every migration must be additive. No existing columns, tables, or EAV meta keys may be removed or renamed (see `OFFER_SYSTEM_DO_NOT_TOUCH.md`).
5. **Governance docs stay current.** If a phase decision changes a guardrail defined in `OFFER_SYSTEM_GOVERNANCE.md`, that document must be updated in the same pull request.

---

## Stop Rule

If implementing a phase requires changes that belong to a later phase — additional tables, state transitions, UI components, or AI features not scoped to the current phase — **stop and request approval before proceeding**.

Do not pull work forward from later phases to make an earlier phase easier to build. Each phase boundary is a deliberate checkpoint. Crossing it without approval undermines the sequencing guarantees this document exists to enforce.
