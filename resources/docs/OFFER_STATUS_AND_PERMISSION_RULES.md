# Offer Status & Permission Rules

> **Status:** Authoritative specification  
> **Scope:** All four listing types — Seller→Buyer, Buyer Criteria→Seller, Landlord→Tenant, Tenant Criteria→Landlord  
> **Purpose:** Reference document for future code, tests, and policy decisions relating to offer lifecycle management.

---

## Table of Contents

1. [Offer Statuses](#1-offer-statuses)
2. [Status Transition Rules](#2-status-transition-rules)
3. [Permission Matrix](#3-permission-matrix)
4. [Notes & Edge Cases](#4-notes--edge-cases)

---

## 1. Offer Statuses

The platform recognises seven lifecycle statuses for an offer. Each offer exists in exactly one status at any given time.

### `draft`
The offer has been created and saved by the Offer Creator but has not yet been formally submitted to the other party. The creator may continue editing all fields. The offer is not visible to the Offer Receiver or any other party. A draft imposes no obligation on either side and may be abandoned at any time.

### `submitted`
The Offer Creator has formally submitted the offer. It is now visible to the Offer Receiver (and their agent, where applicable) and is awaiting a response. The Offer Creator may no longer freely edit the offer's material terms; they may only withdraw it. The clock for any platform-enforced response deadline begins when the offer enters this status.

### `countered`
The Offer Receiver (or their agent, acting on their behalf) has responded with modified terms rather than a flat acceptance or rejection. A counter-offer replaces the previously submitted terms as the active proposal. Roles now invert: the original Offer Creator becomes the Responding Party and must accept, reject, or counter in turn. Each round of countering creates a new version entry in the offer history; all prior versions are preserved for audit purposes.

### `accepted`
The Responding Party has accepted the current terms without modification. This is a **terminal status** — no further transitions are permitted. An accepted offer may trigger downstream platform actions such as generating an Accepted Bid Summary, initiating PDF packet creation, and notifying relevant parties. Neither party may alter the offer after acceptance.

### `rejected`
The Offer Receiver or Responding Party has explicitly declined the offer in its current form. This is a **terminal status** — no further transitions are permitted. A rejection does not prevent either party from creating a new, separate offer on the same listing; it only permanently closes this specific offer thread.

### `withdrawn`
The Offer Creator has voluntarily retracted the offer before it reached a terminal resolution. This is a **terminal status** — no further transitions are permitted. A withdrawn offer may be withdrawn at any point after submission and before the offer is accepted, rejected, or expired. The withdrawal reason (if provided) is logged in the offer history.

### `expired`
The platform's automated deadline enforcement has closed the offer because the required response was not received within the allowed window. This is a **terminal status** — no further transitions are permitted. Expiry is triggered by the system (automated job) or may be forced by an Admin. Neither the Offer Creator nor the Offer Receiver may reopen an expired offer; a new offer must be created.

---

## 2. Status Transition Rules

### Valid Transitions

| From Status | To Status | Triggered By |
|---|---|---|
| `draft` | `submitted` | Offer Creator (explicit submit action) |
| `draft` | `withdrawn` | Offer Creator (explicit withdrawal) |
| `submitted` | `countered` | Offer Receiver / Responding Party |
| `submitted` | `accepted` | Offer Receiver / Responding Party |
| `submitted` | `rejected` | Offer Receiver / Responding Party |
| `submitted` | `withdrawn` | Offer Creator |
| `submitted` | `expired` | System (deadline) or Admin (force-expire) |
| `countered` | `countered` | Either party (subsequent counter-round) |
| `countered` | `accepted` | Responding Party (current round) |
| `countered` | `rejected` | Responding Party (current round) |
| `countered` | `withdrawn` | Offer Creator (original creator only) |
| `countered` | `expired` | System (deadline) or Admin (force-expire) |

### Terminal States (Locked — No Further Transitions)

The following statuses are **final**. Once an offer enters any of these states it is permanently closed. Any attempt to transition out of a terminal state must be rejected by the platform.

| Terminal Status | Locked Since |
|---|---|
| `accepted` | Immediately upon entering the status |
| `rejected` | Immediately upon entering the status |
| `withdrawn` | Immediately upon entering the status |
| `expired` | Immediately upon entering the status |

### Transition Diagram

```
                              ┌──────────────────────────────────┐
                              │           (counter again)         │
                              │                                   │
  ┌────────┐  (submit)  ┌───────────┐  (counter)  ┌──────────┐  │
  │ draft  │──────────► │ submitted │────────────► │ countered│──┘
  └────────┘            └───────────┘              └──────────┘
      │                  │   │   │                  │   │   │
      │ (withdraw)        │   │   │                  │   │   │
      ▼                  │   │   │                  │   │   │
  [withdrawn] ◄──────────┘   │   │       (withdraw) │   │   │
  (terminal)   (withdraw)    │   │                  ▼   │   │
                             │   │              [withdrawn]  │
                             │   │              (terminal)   │
                             │   │ (accept)                  │ (accept)
                             │   ▼                           ▼
                             │ [accepted] ◄──────────── [accepted]
                             │ (terminal)                (terminal)
                             │
                             │ (reject)                    (reject)
                             ▼                           ▼
                           [rejected] ◄──────────── [rejected]
                           (terminal)               (terminal)

  Both `submitted` and `countered` also transition to:
  [expired] (terminal) — triggered by System (auto) or Admin (force-expire)
```

> **Simplified flow:** `draft → submitted → [countered ↔ countered …]* → accepted | rejected | withdrawn | expired`  
> States in `[brackets]` are terminal — no further transitions permitted.

---

## 3. Permission Matrix

> **Important:** Permissions are defined by **relationship to the offer and listing**, not solely by user type. The same user-type (e.g., an agent) may hold different relationship roles on different offers. Always resolve the relationship role before applying this matrix.
>
> - **Offer Creator** — the party who originated the offer. May be a Buyer, Tenant, Seller, Landlord, or Agent depending on listing type.
> - **Offer Receiver** — the party to whom the offer is initially addressed. Typically the listing owner or their agent.
> - **Listing Owner** — the user who owns the listing against which the offer is placed.
> - **Listing Owner's Agent** — an agent formally hired to represent the Listing Owner on this listing.
> - **Responding Party** — the party whose turn it is to respond in the current counter-offer round. Alternates after each counter.
> - **Responding Party's Agent** — an agent formally hired to represent the Responding Party.
> - **Admin** — platform administrator with elevated privileges.
> - **System/Platform** — automated processes acting on behalf of the platform (e.g., scheduled expiry jobs).

### Action Definitions

| Action | Description |
|---|---|
| **Create Offer** | Initiate a new offer thread (creates a `draft`) |
| **Submit Offer** | Transition offer from `draft` → `submitted` |
| **Counter Offer** | Submit a counter-proposal (`submitted` or `countered` → `countered`) |
| **Accept Offer** | Accept current terms (`submitted`/`countered` → `accepted`) |
| **Reject Offer** | Explicitly decline (`submitted`/`countered` → `rejected`) |
| **Withdraw Offer** | Retract own offer (`draft`/`submitted`/`countered` → `withdrawn`) |
| **Force-Expire Offer** | Close offer immediately due to deadline or moderation (`submitted`/`countered` → `expired`) |
| **View Offer** | Read the current terms of the offer |
| **View Offer History** | Access the full version/counter history and audit log |

### Permission Matrix

| Action | Offer Creator | Offer Receiver | Listing Owner | Listing Owner's Agent | Responding Party | Responding Party's Agent | Admin | System / Platform |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Create Offer** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅‡ | ❌ |
| **Submit Offer** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅‡ | ❌ |
| **Counter Offer** | ❌ | ✅ | ✅† | ✅† | ✅ | ✅ | ❌ | ❌ |
| **Accept Offer** | ❌ | ✅ | ✅† | ✅† | ✅ | ✅ | ❌ | ❌ |
| **Reject Offer** | ❌ | ✅ | ✅† | ✅† | ✅ | ✅ | ❌ | ❌ |
| **Withdraw Offer** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Force-Expire Offer** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **View Offer** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **View Offer History** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Legend:**  
✅ — Permitted  
❌ — Not permitted  
✅† — Permitted only when the Listing Owner or their Agent is the current Responding Party (i.e., it is their turn in the counter-offer cycle)  
✅‡ — Permitted for data-correction or moderation purposes only; action must be clearly attributed to the Admin in the offer history, never recorded as if performed by a standard party

---

## 4. Notes & Edge Cases

### Withdrawal
- **Only the Offer Creator may withdraw** an offer, at any point before the offer reaches a terminal state.
- Withdrawal is permitted from `draft`, `submitted`, and `countered` status.
- Even if the offer is in a `countered` state where the Offer Creator is the current Responding Party, the Creator retains the right to withdraw rather than respond.
- Withdrawal does not constitute rejection; the Offer Receiver is not considered to have rejected anything.

### Acceptance & Rejection
- **Only the current Offer Receiver or Responding Party** (and their authorised agent) may accept or reject an offer.
- Once a counter-offer is sent, roles alternate: the prior Offer Creator becomes the new Responding Party and is the only party who may accept or reject the counter.
- Accepting a `countered` offer binds both parties to the terms of the *most recent* counter, not the original submission.

### Countering
- Either the Offer Receiver *or* the Offer Creator (when it is their turn to respond) may submit a counter-offer.
- Each counter creates a new immutable version in the offer history; prior versions are retained for the full audit trail.
- There is no platform-enforced limit on the number of counter rounds, though a deadline expiry may terminate the thread.

### Expiry
- **The System auto-expires** offers that have not been resolved by the platform-enforced response deadline.
- **Admins may force-expire** an offer at any time, regardless of whether the deadline has passed, for moderation or dispute-resolution purposes.
- Neither the Offer Creator nor the Offer Receiver may manually trigger expiry; only the System and Admins hold this privilege.

### Admin Permissions
- Admins may **not** counter, accept, reject, or withdraw offers. These actions are exclusively reserved for the parties in relationship with the offer (Offer Creator, Offer Receiver, and Responding Party). The platform must enforce this even for administrators to preserve audit integrity.
- Admins may **force-expire** an offer at any time for moderation or dispute-resolution purposes (see Expiry section above).
- Admins may **create or submit** an offer on behalf of a user for data-correction purposes only. Such actions must be clearly attributed to the Admin in the offer history — they must never be recorded as if performed by the standard party.
- All admin actions must be logged in the offer history with the admin's identity and a stated reason.

### Applicability Across Listing Types
- These rules apply **uniformly** across all four listing types supported by the platform:
  - **Seller Offer Listing → Buyer** (seller posts listing, buyer submits offer)
  - **Buyer Criteria Listing → Seller** (buyer posts criteria, seller submits offer)
  - **Landlord Offer Listing → Tenant** (landlord posts listing, tenant submits offer)
  - **Tenant Criteria Listing → Landlord** (tenant posts criteria, landlord submits offer)
- The identity of who is the "Offer Creator" and who is the "Offer Receiver" is determined by the listing type and the direction of the initial offer, not by the user's general account role.

### Relationship-First Permission Principle
Permissions must be resolved by **relationship to the specific offer and listing** at the moment of the action, not by the user's general account type. For example:
- An agent user who has been hired by the Offer Creator is the **Responding Party's Agent** and may counter/accept/reject on that party's behalf.
- The same agent user, on a different listing where they represent the Offer Receiver, would be the **Listing Owner's Agent** and may only act within those permissions.
- A user who is generally classified as a "Buyer" on the platform is an **Offer Creator** only for offers they personally initiated.

### Draft Visibility
- Offers in `draft` status are **not visible** to the Offer Receiver, Listing Owner, or any other party outside the Offer Creator (and their agent, if applicable).
- A draft offer does not establish any rights or obligations for either party.

### No Reopening of Terminal Offers
- Once an offer reaches a terminal state (`accepted`, `rejected`, `withdrawn`, `expired`), it **cannot be reopened, reversed, or modified** by any party, including Admins.
- To continue negotiation after a rejection, withdrawal, or expiry, a new offer thread must be created.
