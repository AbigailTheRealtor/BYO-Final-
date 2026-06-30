# C3 / C4 — Self-Bidding & Duplicate-Offer Business Rules (PENDING OWNER APPROVAL)

**Status:** 🟡 Blocked — awaiting owner decision
**Audit items:** C3 (BYA-H2, self-bid prevention), C4 (BYA-H3, duplicate-offer prevention)
**Created during:** Phase C — Core Workflow Restoration
**Owner:** Abigail Sweeney

> No code is changing C3/C4 until the rules below are approved. A blanket
> "listing owner ≠ bidder" guard was prototyped and **reverted** because it
> broke 59 established tests and contradicts how BidYourOffer actually models
> offers (see §3).

---

## 1. Why this is a decision, not a bug fix

Two distinct systems can host a "bid/offer on a listing", and they model
ownership differently:

| System | Entry point | What the "auction owner" means |
|--------|-------------|-------------------------------|
| **BidYourOffer** (Offer negotiation engine) | `OfferController::store` → `App\Models\Offer` | The `OfferAuction` is the **offerer's own offer container**. The person creating the offer normally **is** the auction owner. |
| **BidYourAgent** (Hire-Agent bids) | `*AgentAuctionBid::submit()` / `*BidController` | The auction is a **consumer's hire-agent listing**; bidders are **agents** (a different party from the listing owner). |

A single "owner cannot bid on own listing" rule is correct for one model and
wrong for the other, so the rule must be stated per system.

---

## 2. What is ALREADY enforced today (no change needed)

### BidYourOffer — self-action on the response side
`App\Services\Offers\OfferPermissionService` already blocks a party from acting
on **their own** offer:

- `canCounter()` / `canAccept()` / `canReject()` return *not allowed* when
  `actorId === offer->user_id` ("Waiting for the other party to respond.").
- Party membership is restricted to the two legitimate parties (listing owner +
  root submitter) via `getLegitimatePartyIds()`.

Locked in by `tests/Feature/Offers/OfferSelfBidDuplicateTest.php`.

### BidYourAgent — duplicate bids
The Hire-Agent bid components are effectively **one active bid per agent**:

- **Landlord** `LandlordAgentAuctionBid::submit()` — explicitly updates an
  existing bid instead of inserting a second (`where('user_id', Auth::id())`).
- **Seller / Buyer / Tenant** components load/edit the agent's existing bid by
  `user_id`, so a repeat submission edits rather than duplicates.

---

## 3. What was prototyped and REVERTED (and why)

Added to `OfferController::store`:
1. **Self-bid:** reject when `OfferAuction.user_id === Auth::id()`.
2. **Duplicate:** reject when the user already has a non-final `Offer` on the
   same `offer_auction_id`.

**Result:** 59 existing tests failed. Root cause: in BidYourOffer the
`OfferAuction` is the offerer's **own** container, so creating the initial
`Offer` where auction-owner == offerer is the **normal primary flow** (every
`*OfferEntryTest`, `OfferTermsEntryTest`, etc. relies on it). The guards were
fully reverted; `OfferController.php` is back to its original state.

---

## 4. Proposed rules — please approve / amend

### Rule A — Self-bidding (BidYourOffer)
- **A1 (current behaviour, recommended):** No restriction at offer *creation*;
  a party simply cannot counter/accept/reject **their own** offer (already
  enforced). ✅ no code change.
- **A2 (only if desired):** Additionally forbid a user from creating an offer
  against a container owned by a *different* user they are not a party to.
  → Needs a definition of "who may respond to whom" first.

**Decision needed:** Is A1 sufficient, or do you want A2?

### Rule B — Self-bidding (BidYourAgent / Hire-Agent)
Should a **consumer (listing owner)** be blocked from submitting an **agent
bid** on their **own** hire-agent listing?
- **B1 (recommended):** Yes — block it in `*AgentAuctionBid::submit()` and the
  legacy `*BidController` (owner `user_id` may not also be the bidding agent).
  Low risk; agents and the listing owner are normally different accounts.
- **B2:** Leave as-is (no explicit block).

**Decision needed:** B1 or B2? (If B1, confirm it should apply to all four
roles.)

### Rule C — Duplicate offers (BidYourOffer)
Should a single party be limited to **one active** `Offer` per container?
- **C1:** Yes — block a second offer while one is in `draft/submitted/countered`
  (final-state offers don't block a fresh one). *Note:* must be scoped so it
  does **not** break the same-user create flow the test suite exercises — likely
  means scoping by *(responding party ≠ container owner)*, which depends on
  Rule A2.
- **C2 (recommended for now):** Defer — the negotiation chain (parent/child
  offers + active-leaf guard) already prevents parallel live counters.

**Decision needed:** C1 or C2?

### Rule D — Duplicate bids (BidYourAgent)
Already one-active-bid-per-agent (§2). 
- **D1 (recommended):** Keep as-is.
- **D2:** Make the dedupe explicit/uniform across all four roles (Seller/Buyer/
  Tenant mirror Landlord's update-in-place safeguard).

**Decision needed:** D1 or D2?

---

## 5. Recommendation summary

| Rule | Recommended | Code change if approved |
|------|-------------|-------------------------|
| A — BYO self-bid | **A1** (already enforced) | none |
| B — BYA self-bid | **B1** (block owner-as-agent) | small guard ×4 roles |
| C — BYO duplicate | **C2** (defer) | none |
| D — BYA duplicate | **D1** (keep) / D2 if uniformity wanted | none / small |

Once you pick A/B/C/D, I will implement only the approved guards (expected:
just Rule B if you accept the recommendation), with targeted tests, and update
the master audit document.
