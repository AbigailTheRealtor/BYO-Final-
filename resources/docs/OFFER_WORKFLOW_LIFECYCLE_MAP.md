# Offer Workflow Lifecycle Map

**Status:** Pre-implementation reference document  
**Last updated:** 2026-06-02  
**Do not implement from this document alone** — see Section 9 for build order.

---

## Section 1 – Purpose

This document maps the intended lifecycle of the Offer workflow from listing creation through final resolution and audit trail. It is written **before any implementation begins** and serves as a shared reference for the team to surface open questions, align on actors and permissions, and establish a recommended build order.

This document is **not a binding specification**. It describes the intended shape of the workflow at the time of writing; details will evolve during implementation. The authoritative, machine-enforceable lifecycle rules — valid status transitions, guard conditions, and invariants — will be governed by a separate document: **`OFFER_STATUS_STATE_MACHINE.md`** (to be created as part of the OfferRepository / status engine work).

---

## Section 2 – Current Foundation

The following models and services are **already in place** in the codebase and form the foundation the Offer workflow will be built on top of.

### Models

| Model | File | Notes |
|---|---|---|
| `Offer` | `app/Models/Offer.php` | Core offer record. Holds `status`, `submitted_at`, `expires_at`, `listing_snapshot`, and links to `OfferAuction`, parent/child offers, `OfferMeta`, and `OfferEventLog`. |
| `OfferAuction` | `app/Models/OfferAuction.php` | The listing that opens a room for offers. Tracks `is_draft`, `is_approved`, `is_sold`. Derives `status` dynamically via `getStatusAttribute()` from meta keys `listing_status` and `listing_expiration`. |
| `OfferAuctionMeta` | `app/Models/OfferAuctionMeta.php` | EAV store for `OfferAuction` extended fields (key/value pairs, no timestamps). |
| `OfferMeta` | `app/Models/OfferMeta.php` | EAV store for `Offer` extended fields. |
| `OfferEventLog` | `app/Models/OfferEventLog.php` | Append-only audit log per offer. Captures `actor_id`, `actor_role`, `event_type`, `from_status`, `to_status`, `metadata`, and `ip_address`. No `updated_at`. |
| `AcceptedBidSummary` | `app/Models/AcceptedBidSummary.php` | Immutable accepted-offer record. Stores rendered HTML, PDF path, and dual-party signature data (tenant + agent). Currently wired to the Tenant Agent auction flow only. |

### Services

| Service | File | Notes |
|---|---|---|
| `AcceptedBidSummaryService` | `app/Services/AcceptedBidSummaryService.php` | Generates, regenerates, and serves the accepted bid HTML/PDF summary. Handles signature placeholder replacement and legacy HTML migration. Currently Tenant-auction-scoped. |

### Planned (does not yet exist)

| Component | Notes |
|---|---|
| `OfferRepository` | No repository layer exists yet. All future status transitions and event logging should route through this class to enforce invariants consistently. |

---

## Section 3 – Lifecycle Overview

The following state chain describes the intended end-to-end flow of an offer from the moment a listing is created to final resolution and audit record.

```
[Listing / OfferAuction Created]
         │
         ▼
  [Offer Draft Saved]
  (status: draft)
         │
         ▼
  [Offer Submitted]
  (status: submitted)
         │
         ▼
  [Seller / Landlord Review]
         │
    ┌────┴────────────────────┐
    ▼                         ▼
[Counter Issued]           [Decision]
(status: countered)      ┌─────┴──────┐
    │                    ▼            ▼
    ▼              [Accepted]    [Rejected]
[Buyer / Tenant   (status:       (status:
  Response]        accepted)      rejected)
    │
  ┌─┴────────────────────┐
  ▼                      ▼
[Accept Counter]   [Reject Counter /
(status: accepted)  Withdraw Offer]
                   (status: withdrawn
                    or closed)
         │
         ▼
  [Final Accepted]
  (status: accepted)
         │
         ▼
  [AcceptedBidSummary Generated]
  (dual-party acknowledgement / PDF)
         │
         ▼
  [OfferEventLog — Full Audit Trail]
```

### Status Values (provisional)

| Status | Description |
|---|---|
| `draft` | Offer started but not submitted. Editable by the submitting party. |
| `submitted` | Offer formally submitted and visible to the receiving party. No further edits. |
| `countered` | Receiving party has issued a counter-offer. Awaiting submitter response. |
| `accepted` | One party has formally accepted the terms. Triggers `AcceptedBidSummary` generation. |
| `rejected` | Receiving party declined the offer without a counter. |
| `withdrawn` | Submitting party withdrew before a decision was made. |
| `expired` | `expires_at` passed without acceptance or counter. System-set. |
| `closed` | Offer room closed because another offer on the same listing was accepted (if single-winner rule applies). |

---

## Section 4 – Actor Roles

Each actor has a distinct permission boundary within the Offer workflow. The table below defines the high-level actions each role is expected to be permitted to perform. Exact gate logic will be implemented in `OfferRepository` and Laravel Gate policies.

| Actor | Can Submit Offer | Can Counter | Can Accept | Can Reject | Can Withdraw | Can View Audit Log | Notes |
|---|---|---|---|---|---|---|---|
| `buyer` | Yes | No | No | No | Yes (own offer) | Own offers only | Submits offers on Seller listings. |
| `tenant` | Yes | No | No | No | Yes (own offer) | Own offers only | Submits offers on Landlord listings. |
| `seller` | No | Yes | Yes | Yes | No | Own listings | Receives and acts on buyer offers. |
| `landlord` | No | Yes | Yes | Yes | No | Own listings | Receives and acts on tenant offers. |
| `buyer_agent` | Yes (on behalf) | No | No | No | Yes (own offer) | Delegated listings | Acts on behalf of buyer client. |
| `tenant_agent` | Yes (on behalf) | No | No | No | Yes (own offer) | Delegated listings | Acts on behalf of tenant client. |
| `seller_agent` | No | Yes (on behalf) | Yes (on behalf) | Yes (on behalf) | No | Delegated listings | Acts on behalf of seller client. |
| `landlord_agent` | No | Yes (on behalf) | Yes (on behalf) | Yes (on behalf) | No | Delegated listings | Acts on behalf of landlord client. |
| `admin` | No | No | No | No | No | All | Read-only oversight and intervention. |
| `system` | No | No | No | No | No | All | Automated expiration, notifications. No user-facing UI. |

---

## Section 5 – Workflow Questions to Resolve

The following open questions must be answered before implementation of status transitions begins. They are recorded here to prevent conflicting assumptions from entering the codebase.

### Submission

- **Who can submit an offer?** Can a buyer/tenant submit directly without an agent, or is an agent required?
- **Can an agent submit on behalf of a client who has not yet registered?** What happens to the offer if the client never completes registration?
- **Is there a submission deadline?** Does the `OfferAuction.expiration_date` / `listing_expiration` meta key gate whether new offers can be submitted?

### Counter-Offers

- **How many rounds of countering are allowed?** Is there a cap, or can the parties counter indefinitely?
- **Does a counter expire?** Should counters carry their own `expires_at`, or inherit from the parent offer?
- **Who counter-counters?** If the buyer counters back, does that flip the flow back to the seller?

### Acceptance

- **Does accepting one offer automatically close the offer room?** If a seller accepts Offer A, are all other submitted/countered offers on the same listing automatically moved to `closed`?
- **Can multiple offers be accepted simultaneously?** (e.g., backup offers) If so, how is the primary acceptance tracked?
- **Can an accepted offer be cancelled after acceptance?** If yes, who can do it, and what status does the offer revert to?

### Withdrawal and Expiration

- **Can a submitted (non-countered) offer be withdrawn by the submitter?**
- **Can an offer be withdrawn after a counter has been issued?** Does withdrawal at that point cancel the counter too?
- **Can an expired offer be revived?** Should admins be able to manually reopen an expired offer, or is expiration permanent?

### Agent Permissions

- **Does a buyer_agent acting on behalf of a buyer need explicit delegation on record?** Is there a `hired_agent` link that gates this, or is it role-based only?
- **Can a seller_agent accept an offer without the seller's explicit in-platform confirmation?** Or is dual-confirmation required?

### Audit and Visibility

- **Who can view the full `OfferEventLog`?** Is it restricted to the two transacting parties plus admin, or is it visible to both agents as well?
- **How is the audit log surfaced in the UI?** Timeline view, accordion, or downloadable report?
- **Are rejected and withdrawn offers still visible to the receiving party after closure?**

---

## Section 6 – Required Future Backend Services

The following services are planned but do not yet exist. They should be created in the order recommended in Section 9.

| Service | Responsibility |
|---|---|
| `OfferDraftService` | Create and update offer drafts. Enforce field-level validation without triggering submission rules. Save via `OfferMeta` EAV. |
| `OfferSubmissionService` | Transition an offer from `draft` to `submitted`. Validate completeness, snapshot listing state into `Offer.listing_snapshot`, set `submitted_at`, write the first `OfferEventLog` entry. |
| `OfferCounterService` | Create a counter-offer record linked to the parent offer via `parent_offer_id`. Transition parent to `countered`, new record to `submitted`. Write event log entries for both. |
| `OfferDecisionService` | Handle accept, reject, and withdraw transitions. On accept: write `AcceptedBidSummary`, close other active offers on the same listing (if single-winner rule applies). All transitions write to `OfferEventLog`. |
| `OfferEventLogService` | Centralised append-only writer for `OfferEventLog`. Ensures actor, role, IP, and from/to status are always captured. Never updates or deletes records. |
| `OfferExpirationService` | Scheduled job (or command) that sweeps offers where `expires_at < now()` and `status` is still `submitted` or `countered`, transitioning them to `expired` and writing log entries. |
| `OfferAcceptedSummaryService` | Extension (or generalisation) of the existing `AcceptedBidSummaryService` to support Offer-model-backed acceptances across all four roles (buyer/seller, tenant/landlord), not just the Tenant Agent auction flow. |

---

## Section 7 – Required Future UI Areas

The following screens and panels need to be designed and built. They are listed in approximate order of user-facing dependency.

| Area | Description | Relevant Actors |
|---|---|---|
| **Offer Submission Form** | Multi-step form (or Livewire wizard) allowing a buyer or tenant to draft and submit an offer on an active listing. Must capture all required offer terms and validate before submission. | buyer, tenant, buyer_agent, tenant_agent |
| **Offer Review Dashboard** | Listing-owner view showing all active, countered, accepted, and closed offers for a given listing. Allows the seller/landlord to select an offer and take action (counter, accept, reject). | seller, landlord, seller_agent, landlord_agent |
| **Counter-Offer Comparison View** | Side-by-side display of the original offer terms versus the counter-offer terms, so both parties can review differences before responding. | All transacting parties |
| **Accepted Offer Summary Page** | Display of the generated `AcceptedBidSummary` HTML with dual-party signature capture UI. Must be immutable after both parties sign. | seller/landlord + buyer/tenant (or their agents) |
| **Audit / Event History Panel** | Chronological timeline of all `OfferEventLog` entries for a given offer, showing actor, role, action, and timestamp. Accessible to transacting parties and admin. | seller, landlord, buyer, tenant, agents, admin |

---

## Section 8 – Compliance and Safety Notes

The Offer workflow handles legally significant transaction data. The following constraints must be enforced at every layer of the implementation.

- **No legal advice.** The platform facilitates the recording and communication of offer terms. It does not interpret, validate, or endorse those terms as legally binding. All summary language must include a disclaimer directing parties to consult legal counsel.

- **No auto-decisioning.** The system must never accept, reject, or counter an offer on behalf of a user without an explicit, in-platform user action. Automated transitions (expiration) are permitted only for passive state changes, not acceptance.

- **No hidden offer manipulation.** Offer terms captured at submission time must be stored immutably in `Offer.listing_snapshot` and `OfferMeta`. No service may silently modify stored offer terms after submission.

- **All status changes must be auditable.** Every transition of `Offer.status` — regardless of whether it is user-initiated or system-initiated — must produce a corresponding `OfferEventLog` record written by `OfferEventLogService`. There must be no status-changing code path that bypasses the event log.

- **User actions must be explicit.** Acceptance, rejection, and withdrawal must require a deliberate user interaction (button click + confirmation step). No status change may occur as a side effect of navigation or page load.

- **Accepted terms must be preserved immutably.** Once an offer reaches `accepted` status and an `AcceptedBidSummary` is generated, the summary HTML and the underlying offer snapshot must not be modified. Regeneration of the PDF for display purposes is permitted, but the underlying data must not change.

- **Signature records are append-only.** Once a party signs the `AcceptedBidSummary`, their `signature_name`, `signed_at`, and `ip_address` fields must never be overwritten or nulled out, even by admins.

---

## Section 9 – Recommended Build Order

The following sequence minimises rework by ensuring each layer is in place before the components that depend on it are built.

| Step | Component | Why first |
|---|---|---|
| 1 | **`OfferRepository`** | Centralises all status-transition logic and event logging. Every subsequent service calls through it. Building services before the repository leads to duplicated guard logic. |
| 2 | **Status State Machine (`OFFER_STATUS_STATE_MACHINE.md` + enforcement)** | Defines the valid transitions matrix. Must exist before any service can safely change `Offer.status`, or transitions will be inconsistent from the start. |
| 3 | **`OfferEventLogService`** | Append-only logging must be in place before any transitions are implemented so that no transition ever ships without audit coverage. |
| 4 | **`OfferSubmissionService`** | First user-initiated transition. Requires the repository, state machine, and event log to be in place. |
| 5 | **`OfferCounterService`** | Builds on submission. Requires `parent_offer_id` linkage and dual event-log writes (parent + child). |
| 6 | **`OfferDecisionService`** | Accept/reject/withdraw. Depends on all prior services. Acceptance triggers `AcceptedBidSummary` generation, so the summary service must be ready or stubbed. |
| 7 | **`OfferAcceptedSummaryService` / `OfferExpirationService`** | Post-decision artifacts. Summary generation and scheduled expiration can be built in parallel once the decision service is stable. |
| 8 | **UI (forms, dashboards, panels)** | Built last, against stable service APIs. Livewire wizard for submission, review dashboard for listing owners, summary + signature pages for accepted offers, audit log panel. |
