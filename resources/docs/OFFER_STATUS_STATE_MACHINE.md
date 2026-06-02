# Offer Status State Machine

> This document is the source of truth unless superseded by a later approved version.

## Purpose

This document formally specifies the lifecycle of an Offer within the Bid Your Offer platform. It defines every approved status, the allowed and forbidden transitions between statuses, audit-log requirements for each transition, final-state rules, and the anticipated service classes responsible for executing transitions. All future workflow code, controllers, and tests relating to Offer status management must conform to this specification.

---

## 1. Approved Statuses

| Status | Meaning | Who Can Trigger | Active / Inactive / Final | Appears in Active Offer Lists |
|---|---|---|---|---|
| `draft` | Offer has been started but not yet formally submitted. All fields may still be edited. | Bidder (agent or client) | Active | No |
| `submitted` | Offer has been submitted by the bidder and is awaiting review or response from the listing party. | Bidder | Active | Yes |
| `countered` | The listing party (or agent) has responded with modified terms. The offer may be re-countered or resolved. | Listing party / agent | Active | Yes |
| `accepted` | The listing party has formally accepted the offer. The transaction moves toward closing. | Listing party / agent | Final | Yes (read-only) |
| `rejected` | The listing party has explicitly rejected the offer. No further bidder action is possible. | Listing party / agent | Final | No |
| `withdrawn` | The bidder has voluntarily retracted the offer before acceptance. | Bidder | Final | No |
| `expired` | The offer was not acted upon before its expiration deadline. Set automatically by the platform. | System (automated) | Final | No |
| `cancelled` | An admin or authorised manual process has cancelled a previously accepted offer. This is an exceptional flow and must be rare and audited. | Admin (manual only) | Final | No |

---

## 2. Allowed Transitions

| From Status | To Status | Trigger | Actor | Audit Required |
|---|---|---|---|---|
| `draft` | `submitted` | Bidder submits the offer form | Bidder | Yes |
| `submitted` | `countered` | Listing party issues a counter-offer | Listing party / agent | Yes |
| `submitted` | `accepted` | Listing party accepts the offer as submitted | Listing party / agent | Yes |
| `submitted` | `rejected` | Listing party rejects the offer | Listing party / agent | Yes |
| `submitted` | `withdrawn` | Bidder retracts the offer before a response | Bidder | Yes |
| `submitted` | `expired` | Offer deadline passes without resolution | System | Yes |
| `countered` | `accepted` | Bidder or listing party accepts the countered terms | Bidder or listing party / agent | Yes |
| `countered` | `rejected` | Listing party or bidder rejects the counter-offer | Listing party / agent or bidder | Yes |
| `countered` | `countered` | A subsequent counter-offer is issued (re-counter) | Listing party / agent or bidder | Yes |
| `countered` | `withdrawn` | Bidder withdraws during an active counter cycle | Bidder | Yes |
| `countered` | `expired` | Counter-offer deadline passes without resolution | System | Yes |
| `accepted` | `cancelled` | Admin or authorised manual correction reverses an accepted offer | Admin (manual only) | Yes — mandatory with reason |

---

## 3. Forbidden Transitions

The following transitions are explicitly prohibited by the platform and must never be permitted by any service, controller, or console command:

| Forbidden Transition | Reason |
|---|---|
| `accepted` → `countered` | Acceptance is final; no further negotiation is permitted without cancellation first. |
| `accepted` → `rejected` | An accepted offer cannot be rejected; use `cancelled` via the admin flow. |
| `rejected` → `accepted` | Rejection is final. A new offer must be submitted instead. |
| `withdrawn` → `accepted` | A withdrawn offer cannot be revived. The bidder must submit a new offer. |
| `expired` → `accepted` | An expired offer cannot be revived. The bidder must submit a new offer. |
| `cancelled` → `accepted` | A cancelled offer cannot re-enter the accepted state. |

Any code path that would produce a forbidden transition must throw an exception and abort the operation without persisting any state change.

---

## 4. Audit Log Requirements

Every status transition — allowed or attempted-but-forbidden — must produce an `OfferEventLog` record. Forbidden-transition attempts must be logged with `event_type = 'forbidden_transition_attempt'` before the exception is raised.

### Required Fields on `OfferEventLog`

| Field | Type | Description |
|---|---|---|
| `offer_id` | integer (FK) | The offer this event belongs to. |
| `actor_id` | integer (FK, nullable) | The user who triggered the transition. Null for system-generated events (e.g. expiration). |
| `actor_role` | string | The role of the actor: `bidder`, `listing_party`, `agent`, `system`, or `admin`. |
| `event_type` | string | Semantic event name, e.g. `offer_submitted`, `offer_accepted`, `offer_expired`, `forbidden_transition_attempt`. |
| `from_status` | string | The status before the transition. |
| `to_status` | string | The status after the transition (or the attempted target for forbidden transitions). |
| `metadata` | JSON (nullable) | Arbitrary context: counter-offer terms diff, rejection reason, cancellation reason, admin note, etc. |
| `ip_address` | string (nullable) | The IP address of the actor's request. Null for system-triggered events. |

### Additional Rules

- Every `accepted → cancelled` transition **must** include a `reason` field in `metadata`. The service must reject the operation if no reason is provided.
- `OfferEventLog` records are append-only and must never be updated or deleted.
- Log writes must occur within the same database transaction as the status update so that a rollback never produces an orphaned log entry.

---

## 5. Final State Rules

The statuses `accepted`, `rejected`, `withdrawn`, `expired`, and `cancelled` are **final states**. Once an offer reaches a final state:

1. No further automated or user-initiated transitions are permitted, with the single exception of `accepted → cancelled` which is available only through an approved admin/manual correction flow.
2. The correction flow must require explicit admin authentication, a mandatory written reason, and produces a mandatory `OfferEventLog` entry.
3. Offer fields must become read-only at the application layer as soon as a final state is reached.
4. Final-state offers must be excluded from active offer list queries unless explicitly included via an admin or history view.

---

## 6. Future Workflow Services

The following service classes are anticipated for implementing the transitions described above. Each service is responsible for a single category of transition, enforcing validation, persisting the status change, writing the audit log, and dispatching any downstream events (notifications, PDF regeneration, etc.).

| Service Class | Responsibility |
|---|---|
| `OfferSubmissionService` | Transitions an offer from `draft` to `submitted`. Validates completeness of offer fields before allowing submission. |
| `OfferCounterService` | Handles `submitted → countered` and `countered → countered` transitions. Captures and persists counter-offer terms as a versioned snapshot. |
| `OfferAcceptanceService` | Handles `submitted → accepted` and `countered → accepted`. Triggers downstream flows such as accepted bid summary creation and PDF generation. |
| `OfferRejectionService` | Handles `submitted → rejected` and `countered → rejected`. Sends rejection notifications to the bidder. |
| `OfferWithdrawalService` | Handles `submitted → withdrawn` and `countered → withdrawn`. Validates that the requesting actor is the original bidder. |
| `OfferExpirationService` | System-only service. Scheduled job that queries for offers past their expiration deadline and transitions them to `expired`. Runs outside the HTTP request cycle. |

Each service must:
- Accept the `Offer` model and any required input as constructor/method arguments.
- Validate the requested transition against the allowed-transitions table (Section 2) before writing anything.
- Wrap all database operations — status update + audit log write — in a single database transaction.
- Raise a typed exception (e.g. `ForbiddenOfferTransitionException`) for invalid transitions and log the attempt.
- Return the updated `Offer` model on success.
