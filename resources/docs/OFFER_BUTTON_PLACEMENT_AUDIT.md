# Offer System — Button Placement Audit

## Purpose

This document audits the current state of the four listing view pages and maps where each
of the ten Offer System buttons could be placed, based on what is already in the code.
It records current guards, existing routes, and observed gaps. It does **not** make
implementation decisions — all prescription is deferred to Open Questions or left to the
implementing team.

**How to read this document:**

- The **Current state** table in each section records what is literally in the Blade file
  today: exact guard expressions, line numbers, and route names. These are facts from the
  code, not inferences.
- The **Button map** table maps the ten Offer System buttons for that listing type. Every
  row describes current observed behavior where an analog exists, or documents what is absent.
  Where a cell calls out a gap it uses the label **Current State:** followed by
  **Potential Future Requirement:** to separate fact from possibility.

No Blade, PHP, or route files are created or changed by this document.

---

## Terminology & Role Reference

| Term | Meaning |
|---|---|
| **Owner** | Auth user whose `user_id` matches `$auction->user_id` (listing creator) |
| **Counterparty** | Auth user who is not the listing owner and is eligible to bid on this listing type |
| **`$auth_id`** | `auth()->id()` — resolved in every view file's `@php` preamble |
| **`!$auction->sold`** | Seller Property and Buyer Criteria use the `sold` column |
| **`!$auction->is_sold`** | Landlord and Tenant Criteria use the `is_sold` column |
| **`display_bids`** | `0` = bids hidden from non-owners; `1` = bids visible to all |

**Role vocabulary used in existing bid guards:**

| Listing Type | Owner role(s) | Counterparty roles (current Bid Now guard) |
|---|---|---|
| Seller Property | any user who created it | `admin`, `agent` |
| Buyer Criteria | any user who created it | `seller`, `agent` |
| Landlord | any user who created it | `seller`, `agent` |
| Tenant Criteria | any user who created it | `landlord`, `agent` |

---

## Listing Type: Seller Property

**View file:** `resources/views/seller_property/view.blade.php`
**rightCol div starts:** line 3198
**Potential insertion zone for new buttons:** lines 3252–3294 (between countdown block and highest-bidder card)

### Current state (what already exists in rightCol)

All guard expressions below are copied verbatim from the Blade file.

| Button label | Lines | Exact guard expression | Route |
|---|---|---|---|
| Send Message | 3253–3254 | `$auction->user_id != $auth_id` | `route('auction-chat', ['seller-property', $auction->id])` |
| AI Chat (float) | 3255 | Same as Send Message | — |
| Bid Now | 3275–3284 | `$auth_id` set + `in_array(user_type, ['admin','agent'])` + button disabled when `$auction->user_id == $auth_id \|\| ($auction->sold == 1 && $auction->sold_date != null)` | `route('seller_property_add_bid', $auction->id)` |
| Login for Bid | 3287–3293 | Not authenticated (no `$auth_id`) | `route('login')` |
| Show Bids | 3300–3303 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `$auction->display_bids == 0` | `route('property.bids.visibility', ['id'=>, 'vis'=>'show'])` |
| Hide Bids | 3305–3308 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `$auction->display_bids != 0` | `route('property.bids.visibility', ['id'=>, 'vis'=>'hide'])` |
| Accept (bid) | 3466–3472 | `$auction->user_id == $auth_id && !$auction->sold` | POST `route('acceptPABid')` |
| Reject (bid) | 3477–3484 | `$auction->user_id == $auth_id && !$auction->sold` | POST `route('rejectPABid')` |
| Counter Bid | 3496–3510 | `@auth` + `auth()->id() == $bid->user->id \|\| user_type in ['agent','admin']` + `!$auction->sold` | GET `route('add-counterBiding', ['bid_id'=>, 'auction_id'=>])` |
| Accept (counter sub-loop) | 3586–3596 | `user_type in ['agent','admin']` (inside owner's view) | POST `route('acceptPABid')` |
| Reject (counter sub-loop) | 3598–3605 | `user_type in ['agent','admin']` (inside owner's view) | POST `route('destroyCounter', $countBid->id)` |

### Button map

| Button | Who Can See It | When It Appears | When It Is Hidden | Existing Route Analog / Placeholder | Notes |
|---|---|---|---|---|---|
| **Make Offer** | Authenticated counterparty: `user_type in ['admin','agent']`, not the listing owner | Auth + eligible role | Owner viewing; not authenticated; ineligible role; button disabled when `$auction->sold == 1 && $auction->sold_date != null` (current Bid Now guard, line 3277) | Analog: `seller_property_add_bid`. Placeholder: `seller-property.offer.make` | Current State: "Bid Now" button exists at lines 3275–3284 with this guard. Potential insertion location: adjacent to existing Bid Now button (~line 3275). See Open Question #1 regarding Make Offer vs Submit Property Match. |
| **Submit Property Match** | See Open Question #1 | See Open Question #1 | See Open Question #1 | See Open Question #1 | Current State: No "Submit Property Match" button or route exists on this page. Whether this button belongs here is an architecture decision — see Open Question #1. |
| **View Offers** | Listing owner only | At least one offer submitted | No offers; non-owner | Placeholder: `seller-property.offers.view` | Current State: No "View Offers" button exists. The bid accordion at ~line 3319 displays bids inline when `display_bids == 1`. Potential insertion location: inside or above `.card.higestBider` (~line 3296). |
| **View My Offer** | Authenticated counterparty who has an existing bid on this listing | `$my_bid` (resolved at line 3250) is not null | No bid submitted yet; owner; guest | Placeholder: `seller-property.offer.my` | Current State: No "View My Offer" button exists. `$my_bid` is already computed at line 3250 via `$auction->bids->where('user_id', $auth_id)->first()`. |
| **Counteroffer** | Bid's own author OR `user_type in ['agent','admin']`; listing owner is not in the current guard unless they are also agent/admin | Inside expanded bid accordion; listing not sold | Listing sold; bid rejected; accordion collapsed; guest | Analog: `add-counterBiding`. Placeholder: `seller-property.offer.counter` | Current State: "Counter Bid" button exists at ~line 3502 with this exact guard. See Open Question #5 regarding whether listing owner should also have a counter path. |
| **Accept Offer** | Listing owner only: `$auction->user_id == $auth_id && !$auction->sold` | Inside accordion; listing not sold | Listing sold; bid rejected; non-owner | Analog: `acceptPABid`. Placeholder: `seller-property.offer.accept` | Current State: "Accept" button exists at ~line 3471 with this exact guard. |
| **Reject Offer** | Listing owner only: `$auction->user_id == $auth_id && !$auction->sold` | Inside accordion; listing not sold | Listing sold; bid already rejected; non-owner | Analog: `rejectPABid`. Placeholder: `seller-property.offer.reject` | Current State: "Reject" button exists at ~line 3481 with this guard and a `showToast()` confirmation. |
| **Withdraw Offer** | Authenticated user who owns the bid (`auth()->id() == $bid->user_id`), not listing owner | Inside accordion; listing not sold; bid not yet accepted or rejected | Listing sold; bid accepted or rejected; owner; guest | Placeholder: `seller-property.offer.withdraw` | Current State: No withdraw button or route exists. Potential Future Requirement: A withdraw action may be required if Offer System design supports bid retraction. See Open Question #6. |
| **View History** | Listing owner or bid author or `user_type in ['agent','admin']` | Inside accordion; at least one entry in the counteroffer chain | No counteroffers exist; guest | Placeholder: `seller-property.offer.history` | Current State: Counter-bid data is fetched at line 3513 via `PropertyAuctionBid::where('counter_id', $bid->id)` and rendered inline in the accordion body. No dedicated history view or route exists. |
| **View Accepted Summary** | Listing owner and accepted bid's author; admin and agent | After `$auction->sold == 1` (and `sold_date` is set) | Listing still active | `accepted-bid-summary.view` or `accepted-bid-summary.by-bid` (both confirmed in `routes/web.php`) | Current State: Both summary routes exist. No button linking to them appears in the rightCol today. Potential insertion location: rightCol above the social-share card (~line 3633). |

---

## Listing Type: Buyer Criteria

**View file:** `resources/views/buyer_criteria/view.blade.php`
**rightCol div starts:** line 1424
**Potential insertion zone for new buttons:** lines 1472–1497 (between countdown block and highest-bidder card)

### Current state (what already exists in rightCol)

| Button label | Lines | Exact guard expression | Route |
|---|---|---|---|
| Send Message | 1473–1475 | `$auction->user_id != $auth_id` | `route('auction-chat', ['buyer-criteria', $auction->id])` |
| Bid Now | 1479–1488 | `$auth_id` set + `in_array(user_type, ['seller','agent'])` + button disabled when `$auction->user_id == $auth_id` only — sold state is **not** in the disabled attribute; a "Sold" badge is shown but the button remains enabled | `route('criteria.auction.bid', $auction->id)` |
| Login for Bid | 1491–1496 | Not authenticated | `route('login')` |
| Show Bids | 1503–1506 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `display_bids == 0` | `route('criteria.auction.bids.visibility', ['id'=>, 'vis'=>'show'])` |
| Hide Bids | 1508–1511 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `display_bids != 0` | `route('criteria.auction.bids.visibility', ['id'=>, 'vis'=>'hide'])` |
| Accept (bid) | 1807–1817 | `$auction->user_id == $auth_id` + `!$auction->is_sold` | POST `route('acceptBCABid')` |
| Counter Bid | 1824–1828 | `auth()->id() == $bid->user->id \|\| user_type in ['agent','admin']` + `!$auction->sold` | GET `route('buyer-criteria.add.counter-bid', $bid->id)` |
| Accept (counter sub-loop) | ~1901–1912 | **Entirely commented out** — not active | `route('agent.landlord.auction.bid.accept', ...)` appears in the commented block — note this references the wrong listing type |

### Button map

| Button | Who Can See It | When It Appears | When It Is Hidden | Existing Route Analog / Placeholder | Notes |
|---|---|---|---|---|---|
| **Make Offer** | See Open Question #1 | See Open Question #1 | See Open Question #1 | See Open Question #1 | Current State: No "Make Offer" button or route exists. Whether this button applies to Buyer Criteria — or whether "Submit Property Match" covers this role — is an architecture decision. See Open Question #1. |
| **Submit Property Match** | Authenticated counterparty: `user_type in ['seller','agent']`, not listing owner | Auth + eligible role | Owner; guest; ineligible role; button disabled for owner only — sold state does **not** disable the current Bid Now button (line 1481) | Analog: `criteria.auction.bid`. Placeholder: `buyer-criteria.match.submit` | Current State: "Bid Now" button exists at lines 1479–1488 with this guard. `$my_bid` is resolved at line 1470. Potential insertion location: adjacent to existing Bid Now button (~line 1479). See Open Question #1. |
| **View Offers** | Listing owner (buyer) only | At least one submitted bid exists | No bids; non-owner | Placeholder: `buyer-criteria.offers.view` | Current State: No "View Offers" button exists. Bids are displayed inline in the accordion. Agent alias map (`$buyerAgentNumberMap`) computed at line 1523 provides anonymisation already in place. Potential insertion location: inside or above `.card.higestBider` (~line 1499). |
| **View My Offer** | Authenticated counterparty where `$my_bid` (line 1470) is not null | User has a bid record on this listing | No bid submitted; owner; guest | Placeholder: `buyer-criteria.offer.my` | Current State: No "View My Offer" button exists. `$my_bid` already resolved at line 1470. |
| **Counteroffer** | Bid's own author OR `user_type in ['agent','admin']`; listing owner is not in the current guard | Inside accordion; `!$auction->sold` | Listing sold; accordion collapsed; guest | Analog: `buyer-criteria.add.counter-bid`. Placeholder: `buyer-criteria.offer.counter` | Current State: "Counter Bid" button exists at ~line 1826 with this guard. The counter-bid display sub-loop (~lines 1845–1913) is entirely commented out. The commented block also references `route('agent.landlord.auction.bid.accept', ...)`, which is the wrong route for this listing type. |
| **Accept Offer** | Listing owner: `$auction->user_id == $auth_id && !$auction->is_sold` | Inside accordion; owner + listing not sold | Listing sold (`is_sold`); non-owner | Analog: `acceptBCABid`. Placeholder: `buyer-criteria.offer.accept` | Current State: "Accept" button exists at ~line 1814. Note: Buyer Criteria uses `is_sold` in this guard but `sold` in the Counter Bid guard (line 1823) — column naming is inconsistent within the same view. See Open Question #8. |
| **Reject Offer** | Would be listing owner only (by pattern with other listing types) | Would appear inside accordion; listing not sold | Listing sold; non-owner | Placeholder: `buyer-criteria.offer.reject` | Current State: No Reject button or route exists for Buyer Criteria. Potential Future Requirement: A reject action may be required if Offer System design calls for rejection support on this listing type. |
| **Withdraw Offer** | Bid author (`auth()->id() == $bid->user_id`), not listing owner | Inside accordion; listing not sold; bid not accepted or rejected | Listing sold; bid settled; owner; guest | Placeholder: `buyer-criteria.offer.withdraw` | Current State: No withdraw button or route exists. Potential Future Requirement: A withdraw action may be required if Offer System design supports bid retraction. See Open Question #6. |
| **View History** | Listing owner or bid author or `user_type in ['agent','admin']` | Inside accordion; at least one counteroffer exists | No counteroffers; guest | Placeholder: `buyer-criteria.offer.history` | Current State: The counter-bid display block (~lines 1845–1913) is entirely commented out. No history view or route exists. |
| **View Accepted Summary** | Listing owner and accepted-bid author; admin / agent | After `$auction->is_sold` is truthy | Listing still active | `accepted-bid-summary.view` or `accepted-bid-summary.by-bid` | Current State: Both summary routes exist in `routes/web.php`. No button linking to them appears in the rightCol today. Potential insertion location: rightCol below social-share card (~line 2074). |

---

## Listing Type: Landlord

**View file:** `resources/views/landlord_auction/view.blade.php`
**rightCol div starts:** line 1447
**Potential insertion zone for new buttons:** lines 1456–1480 (between highest-bid PHP preamble and highest-bidder card)

### Current state (what already exists in rightCol)

| Button label | Lines | Exact guard expression | Route |
|---|---|---|---|
| Send Message | 1457–1459 | `$auction->user_id != $auth_id` | `route('auction-chat', ['landlord-property', $auction->id])` |
| Bid Now | 1463–1471 | `$auth_id` set + `in_array(user_type, ['seller','agent'])` + disabled when `$auction->user_id == $auth_id` only — sold state is **not** in the disabled attribute; a "Sold" badge is shown but the button remains enabled | `route('agent.landlord.auction.bid', $auction->id)` |
| Login for Bid | 1474–1478 | Not authenticated | `route('login')` |
| Show Bids | 1486–1489 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `display_bids == 0` | `route('landlord.auction.bids.visibility', ['id'=>, 'vis'=>'show'])` |
| Hide Bids | 1491–1494 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `display_bids != 0` | `route('landlord.auction.bids.visibility', ['id'=>, 'vis'=>'hide'])` |
| Accept (bid) | 1594–1601 | `$auction->user_id == $auth_id && !$auction->is_sold` | POST `route('agent.landlord.auction.bid.accept', $bid->id)` |
| Reject (bid) | 1602–1605 | `$auction->user_id == $auth_id && !$auction->is_sold` | POST `route('agent.landlord.auction.bid.reject', $bid->id)` |
| Counter Bid | 1618–1622 | `@auth` + `auth()->id() == $bid->user->id \|\| user_type in ['agent','admin']` + `!$auction->sold` | GET `route('landlord.add.counter-bid', $bid->id)` |
| Accept (counter sub-loop) | 1702–1708 | `user_type in ['agent','admin']` + `$auction->user_id == $auth_id && !$auction->is_sold` | POST `route('agent.landlord.auction.bid.accept', $bid->id)` — note the URL param uses `$bid->id`, not `$countBid->id` |
| Reject (counter sub-loop) | 1710–1713 | `$auction->user_id == $auth_id && !$auction->is_sold` | POST `route('agent.landlord.auction.bid.reject', $countBid->id)` |

**Additional links (not buttons) in accordion body:**
- `View` eye link (line 1535–1537): `route('landlord.auction.bid.view', $bid->id)` — shown to all who can see accordion body
- Counter-bid `View` eye link (line 1644–1647): `route('landlord.auction.bid.view', $countBid->id)`

**`$my_bid` observation (line 1454):** `$my_bid` is hard-coded to `''` with no query. The equivalent variable on Seller Property (line 3250), Buyer Criteria (line 1470), and Tenant Criteria (line 620) all resolve via `$auction->bids->where('user_id', $auth_id)->first()`. This differs from the other three listing types.

### Button map

| Button | Who Can See It | When It Appears | When It Is Hidden | Existing Route Analog / Placeholder | Notes |
|---|---|---|---|---|---|
| **Make Offer** | Authenticated counterparty: `user_type in ['seller','agent']`, not listing owner | Auth + eligible role | Owner; guest; ineligible role; button disabled for owner only — sold state does **not** disable (line 1465) | Analog: `agent.landlord.auction.bid`. Placeholder: `landlord.offer.make` | Current State: "Bid Now" button exists at lines 1463–1471 with this guard. Potential insertion location: adjacent to existing Bid Now button (~line 1463). See Open Question #1. |
| **Submit Property Match** | See Open Question #1 | See Open Question #1 | See Open Question #1 | See Open Question #1 | Current State: No "Submit Property Match" button or route exists. See Open Question #1. |
| **View Offers** | Listing owner (landlord) only | At least one offer submitted | No offers; non-owner | Placeholder: `landlord.offers.view` | Current State: No "View Offers" button exists. Bids displayed inline in accordion. Potential insertion location: inside or above `.card.higestBider` (~line 1482). |
| **View My Offer** | Authenticated counterparty with an existing bid on this listing | User has a bid record | No bid yet; owner; guest | Placeholder: `landlord.offer.my` | Current State: No "View My Offer" button exists. `$my_bid` is hard-coded `''` at line 1454, unlike the other three listing types where it is resolved via a collection query. |
| **Counteroffer** | Bid's own author OR `user_type in ['agent','admin']`; listing owner is not in the current guard unless they are also agent/admin | Inside accordion; `!$auction->sold` | Listing sold; accordion collapsed; guest | Analog: `landlord.add.counter-bid`. Placeholder: `landlord.offer.counter` | Current State: "Counter Bid" exists at ~line 1620. Route name uses `landlord.add.counter-bid` (no `agent.` prefix, unlike the Accept/Reject routes). See Open Question #5 and #7. |
| **Accept Offer** | Listing owner: `$auction->user_id == $auth_id && !$auction->is_sold` | Inside accordion; owner + listing not sold | Listing sold (`is_sold`); non-owner | Analog: `agent.landlord.auction.bid.accept`. Placeholder: `landlord.offer.accept` | Current State: "Accept" exists at ~line 1594. Route carries `agent.` prefix even when invoked by a non-agent landlord owner. See Open Question #7. |
| **Reject Offer** | Listing owner: `$auction->user_id == $auth_id && !$auction->is_sold` | Inside accordion; owner + listing not sold | Listing sold; non-owner | Analog: `agent.landlord.auction.bid.reject`. Placeholder: `landlord.offer.reject` | Current State: "Reject" exists at ~line 1602. Same `agent.` prefix observation as Accept. |
| **Withdraw Offer** | Bid author, not listing owner | Inside accordion; listing not sold; bid not accepted or rejected | Listing sold; bid settled; owner; guest | Placeholder: `landlord.offer.withdraw` | Current State: No withdraw button or route exists. Potential Future Requirement: A withdraw action may be required if Offer System design supports bid retraction. See Open Question #6. |
| **View History** | Listing owner or bid author or `user_type in ['agent','admin']` | Inside accordion; at least one entry in `$allBids` | No counteroffers; guest | Placeholder: `landlord.offer.history` | Current State: Counter-bid chain is rendered inline at ~lines 1629–1716. `landlord.auction.bid.view` eye links already exist per entry. No dedicated history view or route exists. |
| **View Accepted Summary** | Listing owner and accepted-bid author; admin / agent | After `$auction->is_sold` is truthy | Listing still active | `accepted-bid-summary.view` or `accepted-bid-summary.by-bid` | Current State: Both summary routes exist. No button linking to them appears in the rightCol today. Potential insertion location: above social-share card (~line 1744). |

---

## Listing Type: Tenant Criteria

**View file:** `resources/views/tenant_criteria/view.blade.php`
**rightCol div starts:** line 580
**Potential insertion zone for new buttons:** lines 622–647 (between countdown block and highest-bidder card)

### Current state (what already exists in rightCol)

| Button label | Lines | Exact guard expression | Route |
|---|---|---|---|
| Send Message | 623–624 | `$auction->user_id != $auth_id` | `route('auction-chat', ['tenant-criteria', $auction->id])` |
| Bid Now | 628–638 | `$auth_id` set + `in_array(user_type, ['landlord','agent'])` + disabled when `$auction->user_id == $auth_id` only — sold state is **not** in the disabled attribute; a "Sold" badge is shown but the button remains enabled | `route('tenant.criteria.auction.bid', $auction->id)` |
| Login for Bid | 641–645 | Not authenticated | `route('login')` |
| Show Bids | 653–656 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `display_bids == 0` | `route('tenant.criteria.bids.visibility', ['id'=>, 'vis'=>'show'])` |
| Hide Bids | 658–661 | `$auction->user_id == auth()->id()` + `bids->count() > 0` + `display_bids != 0` | `route('tenant.criteria.bids.visibility', ['id'=>, 'vis'=>'hide'])` |
| Accept (bid) | 778–785 | `$auction->user_id == $auth_id && !$auction->is_sold` | POST `route('agent.tenant.criteria.auction.bid.accept')` — no URL param; `auction_id` and `bid_id` passed as POST hidden fields |
| Counter Bid (bid) | 786–790 | `$auction->user_id == $auth_id && !$auction->is_sold` — **listing owner only**; differs from Seller Property, Buyer Criteria, and Landlord where bid author or agent/admin can also counter | GET `route('tenant.criteria.add.counter-bid', $bid->id)` |
| Accept (counter sub-loop) | 884–893 | `user_type in ['agent','admin']` + `$auction->user_id == $auth_id && !$auction->is_sold` | POST `route('agent.tenant.criteria.auction.bid.accept')` — `bid_id` set to `$countBid->id` via hidden field |

**Counter Bid guard difference:** The Counter Bid button is accessible to the listing owner only on Tenant Criteria. On Seller Property, Buyer Criteria, and Landlord the same button is accessible to the bid's own author or any `agent`/`admin`. This is the only listing type with the owner-only restriction. See Open Question #2.

### Button map

| Button | Who Can See It | When It Appears | When It Is Hidden | Existing Route Analog / Placeholder | Notes |
|---|---|---|---|---|---|
| **Make Offer** | Authenticated counterparty: `user_type in ['landlord','agent']`, not listing owner | Auth + eligible role | Owner; guest; ineligible role; button disabled for owner only — sold state does **not** disable (line 630) | Analog: `tenant.criteria.auction.bid`. Placeholder: `tenant-criteria.offer.make` | Current State: "Bid Now" button exists at lines 628–638 with this guard. `$my_bid` is resolved at line 620. Potential insertion location: adjacent to existing Bid Now button (~line 628). See Open Question #1. |
| **Submit Property Match** | See Open Question #1 | See Open Question #1 | See Open Question #1 | See Open Question #1 | Current State: No "Submit Property Match" button or route exists. See Open Question #1. |
| **View Offers** | Listing owner (tenant) only | At least one offer submitted | No offers; non-owner | Placeholder: `tenant-criteria.offers.view` | Current State: No "View Offers" button exists. Bids displayed inline in accordion. Potential insertion location: inside or above `.card.higestBider` (~line 649). |
| **View My Offer** | Authenticated counterparty where `$my_bid` (line 620) is not null | User has a bid record | No bid submitted; owner; guest | Placeholder: `tenant-criteria.offer.my` | Current State: No "View My Offer" button exists. `$my_bid` is correctly resolved at line 620 via `$auction->bids->where('user_id', $auth_id)->first()`. |
| **Counteroffer** | Current guard: listing owner only — `$auction->user_id == $auth_id && !$auction->is_sold` (lines 775–790). This differs from the other three listing types. | Inside accordion; listing not sold | Listing sold; non-owner | Analog: `tenant.criteria.add.counter-bid`. Placeholder: `tenant-criteria.offer.counter` | Current State: "Counter Bid" exists at ~line 788. The listing-owner-only guard is unique to this listing type. See Open Question #2. |
| **Accept Offer** | Listing owner: `$auction->user_id == $auth_id && !$auction->is_sold` | Inside accordion; owner + listing not sold | Listing sold; non-owner | Analog: `agent.tenant.criteria.auction.bid.accept`. Placeholder: `tenant-criteria.offer.accept` | Current State: "Accept" exists at ~line 783. Route carries `agent.` prefix and takes no URL param. |
| **Reject Offer** | Would be listing owner only (by pattern with other listing types) | Would appear inside accordion; listing not sold | Listing sold; non-owner | Placeholder: `tenant-criteria.offer.reject` | Current State: No Reject button or route exists for Tenant Criteria. Potential Future Requirement: A reject action may be required if Offer System design calls for rejection support on this listing type. |
| **Withdraw Offer** | Bid author, not listing owner | Inside accordion; listing not sold; bid not accepted or rejected | Listing sold; bid settled; owner; guest | Placeholder: `tenant-criteria.offer.withdraw` | Current State: No withdraw button or route exists. Note: the current owner-only Counter Bid guard means bid authors cannot see that accordion section at all today; any Withdraw button would require its own guard block. Potential Future Requirement: see Open Question #6. |
| **View History** | Listing owner or bid author or `user_type in ['agent','admin']` | Inside accordion; at least one counter in `$allBids` (fetched at line 799) | No counteroffers; guest | Placeholder: `tenant-criteria.offer.history` | Current State: Counter-bid chain is rendered inline at lines 806–900. No dedicated history view or route exists. |
| **View Accepted Summary** | Listing owner and accepted-bid author; admin / agent | After `$auction->is_sold` is truthy | Listing still active | `accepted-bid-summary.view` or `accepted-bid-summary.by-bid` | Current State: Both summary routes exist. No button linking to them appears in the rightCol today. Potential insertion location: above social-share card (~line 924). |

---

## Cross-cutting notes

### Route naming convention (confirmed from `routes/web.php`)

| Category | Confirmed named routes |
|---|---|
| Primary bid submission | `seller_property_add_bid`, `criteria.auction.bid`, `agent.landlord.auction.bid`, `tenant.criteria.auction.bid` |
| Accept | `acceptPABid`, `acceptBCABid`, `agent.landlord.auction.bid.accept`, `agent.tenant.criteria.auction.bid.accept` |
| Reject | `rejectPABid`, `agent.landlord.auction.bid.reject` |
| Counter | `add-counterBiding`, `buyer-criteria.add.counter-bid`, `landlord.add.counter-bid`, `tenant.criteria.add.counter-bid` |
| Visibility toggle | `property.bids.visibility`, `criteria.auction.bids.visibility`, `landlord.auction.bids.visibility`, `tenant.criteria.bids.visibility` |
| Accepted summary | `accepted-bid-summary.view`, `accepted-bid-summary.by-bid` |
| End auction | `property.auction.end` |

Placeholder route names in the button map tables follow the same `{role}.offer.{action}` pattern for consistency. These are suggestions, not decisions.

### Observed gaps (current state only)

| # | Listing type | Observation |
|---|---|---|
| 1 | Buyer Criteria | No Reject Offer button or route exists. The other three listing types all have a reject path. |
| 2 | Tenant Criteria | No Reject Offer button or route exists. The other three listing types all have a reject path. |
| 3 | Landlord | `$my_bid` is hard-coded `''` at line 1454. The other three listing types resolve this variable via a collection query. |
| 4 | All four types | No Withdraw Offer button or route exists on any listing type. |
| 5 | Buyer Criteria | The counter-bid display sub-loop (~lines 1845–1913) is entirely commented out. It also references `route('agent.landlord.auction.bid.accept', ...)`, which is the wrong route for this listing type. |
| 6 | Tenant Criteria | The Counter Bid guard (lines 786–790) is listing-owner-only. All other listing types allow the bid's own author or agent/admin to counter. |
| 7 | Seller Property | The Accept button in the counter sub-loop (~line 3586) passes `$bid->id` as the URL param but `$countBid->price` as `counterPrice`. Verify the controller handles this correctly. |
| 8 | Buyer Criteria | Within the same view, the Accept guard uses `$auction->is_sold` (line 1806) and the Counter Bid guard uses `$auction->sold` (line 1823). The column is inconsistent within this one file. |

### Sold / is_sold state per listing type

| Listing type | Column used in Accept/Reject guard | Bid Now button disabled when sold? |
|---|---|---|
| Seller Property | `$auction->sold` | Yes — disabled when `sold == 1 && sold_date != null` (line 3277) |
| Buyer Criteria | `$auction->is_sold` (Accept guard); `$auction->sold` (Counter guard) — inconsistent within the file | No — button only disabled for owner; "Sold" badge shown |
| Landlord | `$auction->is_sold` | No — button only disabled for owner; "Sold" badge shown |
| Tenant Criteria | `$auction->is_sold` | No — button only disabled for owner; "Sold" badge shown |

### `display_bids` and bid visibility

The bid accordion is visible to non-owners only when `display_bids == 1`. However, the accordion loop condition on Buyer Criteria (line 1542) and Landlord (line 1506) and Tenant Criteria (line 674) reads `display_bids == 1 || $auction->user_id == auth()->id()`, so the owner always sees bids regardless of the toggle. Any Offer System button placed inside the accordion body must account for the same dual condition.

---

## Open questions

These are architecture and product decisions that this audit cannot answer.

1. **Make Offer vs Submit Property Match — which button goes on which page?** The current Bid Now button on Buyer Criteria is used by `seller` and `agent` types. Depending on whether the Offer System defines Buyer Criteria as a place where sellers submit property offers (with financing, closing terms, contingencies), "Make Offer" and "Submit Property Match" may be the same action or different ones. This affects all four listing types. The audit has noted both buttons for each page with the expectation that product will specify the mapping.

2. **Counteroffer access on Tenant Criteria:** The current Counter Bid guard is listing-owner-only. All other listing types allow the bid author and agent/admin to counter as well. Should Tenant Criteria be aligned with the other three, or is the owner-only restriction intentional?

3. **Reject Offer on Buyer Criteria and Tenant Criteria:** No reject path exists on these two listing types. Is this intentional (criteria listings can only be accepted or countered), or a gap to be filled?

4. **Withdraw Offer business rules:** No withdraw path exists on any listing type today. If the Offer System includes withdrawal, what are the rules: can a counterparty withdraw at any time before acceptance? After a counteroffer has been sent? Is there a time window?

5. **Counteroffer access for listing owner on Seller / Buyer / Landlord:** The current Counter Bid guard excludes the listing owner unless they are also agent/admin. Should the Offer System provide a dedicated counter path for non-agent owners?

6. **View Accepted Summary access rule:** Should both parties see the accepted summary immediately after acceptance, or only after mutual acknowledgement?

7. **`agent.` prefix on Landlord and Tenant Criteria routes:** `agent.landlord.auction.bid.accept`, `agent.landlord.auction.bid.reject`, and `agent.tenant.criteria.auction.bid.accept` are invoked by any listing owner, not only agents. Should the Offer System reuse these routes or introduce owner-facing alternatives?

8. **`sold` vs `is_sold` inconsistency in Buyer Criteria:** The Accept guard (line 1806) uses `$auction->is_sold` and the Counter Bid guard (line 1823) uses `$auction->sold` within the same file. Which column is authoritative for Buyer Criteria listings?
