# Phase C — Manual Browser QA Checklist

Run these on the Replit dev URL after `npm run watch` is building. Each item maps
to a Phase C change. Mark ✅/❌ and note anything unexpected.

> Automated coverage already passing: Ask AI endpoint authorization + per-viewer
> `isOwner` gating, `Expired` lifecycle signal, bid-detail rendering
> (`AskAiAndExpiryGatingTest`, `OfferSelfBidDuplicateTest`, `AgentBidCompatibilityTest`).
> The items below are the interactive checks those can't cover.

## C1 — Ask AI 403 (offer-listing detail pages)
Pages: `/offer-listing/{seller,buyer,landlord,tenant}/view/{id}` (an **approved** listing).

- [ ] **As the listing OWNER:** open "Ask AI About This Property", submit a question → get an AI answer (or a normal "try again" message), **no 403** in the Network tab on `POST /ask-ai/listing-question`.
- [ ] **As a DIFFERENT logged-in user (non-owner):** open the same modal, submit → see the blue **"Ask AI for this listing is available to the listing owner."** notice, and **no failed 403** request is made.
- [ ] **As a guest (logged out):** the modal either isn't actionable or shows the same notice — no console error, no raw 403/login-redirect surfaced as a broken answer.
- [ ] Repeat the owner vs non-owner check on all four roles (seller/buyer/landlord/tenant view).

## C1 — Ask AI (Stellar shared property detail)
Page: `/property/{listingKey}?criteria_id=...&criteria_type=buyer|tenant`

- [ ] Arriving from **your own** Matched Listings (your criteria_id): the Ask AI box shows the question form and answers.
- [ ] With a **criteria_id you do not own** (tamper the URL): the Ask AI box shows the "available from your saved buyer/tenant criteria" notice — **no 403**, no form.

## C2 — Expired bidding-period listings reject new bids
Use a Hire-Agent listing whose `expiration_date` is in the past (Bidding Period).

- [ ] **Seller / Buyer / Landlord / Tenant** hire-agent listing, expired: attempting to **submit a NEW bid** is rejected with "This listing has expired and is no longer accepting new bids." (or the legacy "not currently accepting new bids").
- [ ] **Editing an existing bid** on the same expired listing still works (not blocked).
- [ ] A **Traditional** (non-bidding) listing shows **no countdown timer** and bidding is unaffected.
- [ ] An **active** (future expiration) bidding listing still accepts new bids and shows the live countdown.

## C5 — Agent contact/credential shows current data
Pages: agent bid detail (`partials/bid_detail_body/*`) and the seller listing bid cards (`sellerAgentAuctionDetail`).

- [ ] As an agent, place a bid; then **change your profile** phone / brokerage / license # / NAR ID (and name).
- [ ] Re-open that bid's detail as the **listing owner**: the **contact/credential block reflects the UPDATED values** (live), not the old bid-time values.
- [ ] The **negotiated terms** (commission %, offered price) and any **Accepted Bid Summary** are **unchanged** (still the agreed historical values).
- [ ] A legacy bid whose agent has no profile value falls back to the original snapshot (no blank fields).

## Regression spot-checks (should be unchanged)
- [ ] Owner Ask AI still returns real answers (V1 path intact for owners).
- [ ] Submitting a bid on a normal active listing works for all four roles.
- [ ] Bid detail page renders fully for owner and for the bidding agent.

---
**Result:** _____ (safe / blocked) — notes:
