# Listing Mode Full Audit — Bidding Period & Traditional, All Roles

**Audit Date:** 2026-04-08  
**Scope:** Code-traced audit of how Bidding Period and Traditional listing modes work across all four roles (Tenant, Seller, Buyer, Landlord). Tenant is the established gold standard. No code changes were made.

---

## Table of Contents

1. [Storage & Mode Detection](#1-storage--mode-detection)
2. [Timer Lifecycle & Auto-Transition](#2-timer-lifecycle--auto-transition)
3. [Checklist Tables — All 8 Role × Mode Combinations](#3-checklist-tables--all-8-role--mode-combinations)
   - 3.1 Tenant — Bidding Period
   - 3.2 Tenant — Traditional
   - 3.3 Seller — Bidding Period
   - 3.4 Seller — Traditional
   - 3.5 Buyer — Bidding Period
   - 3.6 Buyer — Traditional
   - 3.7 Landlord — Bidding Period
   - 3.8 Landlord — Traditional
4. [Status Field Consistency Matrix](#4-status-field-consistency-matrix)
5. [Parity Matrix — Seller / Buyer / Landlord vs Tenant](#5-parity-matrix--seller--buyer--landlord-vs-tenant)
6. [Recommended Surgical Fixes](#6-recommended-surgical-fixes)

---

## 1. Storage & Mode Detection

### Fields used per role

| Role | Primary mode flag | Timer value | Secondary storage | Main-table `auction_type` column? |
|------|-------------------|-------------|-------------------|------------------------------------|
| Tenant | `auction_type` meta (`'Bidding Period'` / `'Traditional'`) | `auction_time` meta (duration string) | `auction_length` meta (legacy, ignored) | **No** — meta-only. Source: `TenantAgentAuctionController` line 54 |
| Seller | `auction_type` meta | `auction_time` meta (duration string) | `auction_length` meta (legacy) | **Yes** — dual-write to main table + meta. Source: `SellerAgentAuctionController` lines 67, 144 |
| Buyer | `auction_type` meta | `auction_time` meta (duration string) | `auction_length` meta (legacy) | **No** — meta-only. Source: `BuyerAgentAuctionController` lines 142, 315 |
| Landlord | `auction_type` meta | `auction_time` meta (duration string) | `auction_length` meta (legacy) | **Yes** — dual-write to main table + meta. Source: `LandlordAgentAuctionController` lines 41, 54 |

**Exact string values (case-insensitive match in all views):**

```php
$isBiddingPeriodListing = strtolower($listingType) === 'bidding period';
$isTraditionalListing   = strtolower($listingType) === 'traditional' || empty($listingType);
```

All four view templates use this identical two-line pattern. Files:
- `resources/views/hire_tenant_agent/view.blade.php` lines 1354–1355
- `resources/views/hire_seller_agent/view.blade.php` lines 2479–2480
- `resources/views/buyerAgentAuctionDetail.blade.php` lines 2132–2133
- `resources/views/hire_landlord_agent/view.blade.php` lines 2124–2125

**Inconsistency (Tenant vs others — `isBiddingPeriodType()`):** `TenantAgentAuction` model has an `isBiddingPeriodType()` helper method used by the Livewire component. Seller, Buyer, and Landlord models do not implement this method; their Livewire components read the raw meta string directly. This is a cosmetic inconsistency with no functional impact on mode detection in the view templates (which all use the same string comparison).

---

## 2. Timer Lifecycle & Auto-Transition

### How the timer is computed (all 4 roles)

`auction_time` stores a human-readable **duration string** such as `"14 Days"`, `"2 Weeks"`, or `"5 Hours"` — not a timestamp. All four view templates parse this string, split it into a numeric value and a unit, then compute the expiration as `listing created_at + duration`:

```php
// view template pattern (all 4 roles, identical logic)
// $start_time = $auction->get->created_at ?? $auction->created_at ?? now();
// $auction_time = trim(auction_time meta); // e.g. "14 Days", "2 Weeks", "5 Hours"

$duration_parts = explode(' ', trim($auction_time)); // ['14', 'Days']
$duration_value = (int) $duration_parts[0];          // 14
$duration_unit  = strtolower($duration_parts[1]);    // 'days'

// Switch on unit to add to start_time:
$expiration = Carbon::parse($start_time)->addDays($duration_value);   // days (default)
// or ->addHours(), ->addWeeks(), ->addMinutes() depending on unit

$isExpired            = Carbon::now()->gte($expiration);
$isBiddingTimerActive = $isBiddingPeriodListing && $expiration && !$isExpired;
$canTakeAction        = $isTraditionalListing || ($isBiddingPeriodListing && $isExpired);
```

Sources:
- `hire_tenant_agent/view.blade.php` lines 1357–1409
- `hire_seller_agent/view.blade.php` lines 2483–2535
- `buyerAgentAuctionDetail.blade.php` lines 2130–2188
- `hire_landlord_agent/view.blade.php` lines 2122–2183

The timer variable is **computed per page-load in the view** — it is not stored as a timestamp in the database.

### `autoTransitionBpToPending()` — Base `Controller.php`

`app/Http/Controllers/Controller.php` (`autoTransitionBpToPending`, lines 18–49) uses the identical duration-based calculation: reads `auction_time` meta, parses unit/value, adds to `$auction->created_at`, and compares to `Carbon::now()`. If expired, it writes `listing_status = 'Pending'` to the listing meta table. The view and the base controller stay in sync because both compute expiration from the same source fields via the same arithmetic.

**Effect on bid submission:** Once expired and `listing_status = 'Pending'` is written, the bid submission guard in `TenantAgentAuctionBidController`, `BuyerAgentAuctionBidController`, and `LandlordAgentAuctionBidController` blocks new bids (those controllers check `in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])`). `SellerAgentAuctionController::saveSABid` does **not** have this guard (see FIX-01).

### Expiration date increment on bid submission

When a new bid is submitted during an active Bidding Period, the timer is extended by +1 day (`expiration_date + 1`):

| Role | Increments `expiration_date`? | Source |
|------|-------------------------------|--------|
| Tenant | No | `TenantAgentAuctionBidController` — no increment |
| Seller | **Yes** — +1 day per new bid | `SellerAgentAuctionController::saveSABid` lines 644–654 |
| Buyer | **Yes** — +1 day per new bid | `BuyerAgentAuctionBidController` lines 133–140 |
| Landlord | No | `LandlordAgentAuctionBidController` — no increment |

This discrepancy appears unintentional: only Seller and Buyer extend the timer.

---

## 3. Checklist Tables — All 8 Role × Mode Combinations

### Checklist Key

| # | Checklist Item |
|---|----------------|
| 1 | Can listing owner view bids (before timer ends / anytime)? |
| 2 | Full bid details or summary only? |
| 3 | Can owner see private compensation/services before timer ends? |
| 4 | Can owner Accept before timer ends? |
| 5 | Can owner Reject before timer ends? |
| 6 | Can owner Counter before timer ends? |
| 7 | Are these buttons hidden, disabled, or blocked server-side? |
| 8 | Can agents submit new bids while timer is active? |
| 9 | Can agents edit existing bids while timer is active? |
| 10 | Can agents submit counter-bids while timer is active? |
| 11 | What exactly happens when the timer expires? |
| 12 | Is expiration enforcement UI-only, server-only, or both? |
| 13 | Traditional: can owner act immediately after bid submission? |
| 14 | Are statuses consistent across roles? |
| 15 | Dashboard and detail pages show correct mode-specific messaging? |

---

### 3.1 Tenant — Bidding Period

**Files:** `resources/views/hire_tenant_agent/view.blade.php`, `app/Http/Controllers/TenantAgentAuctionBidController.php`, `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids before timer ends? | **Yes — bid cards are rendered** but full content is locked. The card list is rendered for the owner but "View Full Bid" is disabled with a lock icon while `$isBiddingTimerActive`. Source: `view.blade.php` lines 1692–1699, 2144–2152. |
| 2 | Full details or summary only before timer? | **Summary only for owner during active timer.** "View Full Bid" renders as a greyed-out `<span>` with a lock icon (`fa-lock`) and tooltip "Bids can be viewed when the bidding period ends." Full `#privateDataModal` becomes accessible only after timer expires. Source: lines 2144–2157. |
| 3 | Owner sees private compensation before timer? | **No.** The `#privateDataModal` (which contains compensation, identity, and full service terms) is locked behind the "View Full Bid" link. While the timer is active, the link is replaced with a disabled span. Source: lines 2144–2157. |
| 4 | Owner Accept before timer ends? | **No — UI hides Accept button.** `$showActionButtons = ($isTraditionalListing && !$isExpired) \|\| ($isBiddingPeriodListing && $isExpired)` evaluates to `false` during active BP. Source: view line 3403. |
| 5 | Owner Reject before timer ends? | **No — UI hides Reject button** for the same reason as Accept. Source: line 3403. |
| 6 | Owner Counter before timer ends? | **No — Counter Bid link absent.** The counter-bid action route is inside the same `$showActionButtons` block. Source: line 3405. |
| 7 | Hidden, disabled, or server-blocked? | **UI-hidden.** The buttons are absent from the rendered HTML (`@if ($showActionButtons)` evaluates false). The accept/reject/counter server-side methods (`accept_bid`, `reject_bid`, `counter_bid` in `TenantAgentAuctionBidController`) contain **no timer check** — they only verify ownership and bid terminal state. A direct POST while the timer is active would succeed server-side. |
| 8 | Agents submit new bids while timer active? | **Yes — but gated by listing status.** The Livewire component `TenantAgentAuctionBid.php` checks `end_date` at save time (line 1056–1058) and blocks submission if the auction has ended. The HTTP controller checks `listing_status in ['Hired Agent', 'Pending', 'Expired']`; during an active BP this status is neither, so submission is allowed. New bids trigger no timer extension (unlike Seller/Buyer). |
| 9 | Agents edit existing bids while timer active? | **Edits permitted during active BP.** `TenantAgentAuctionBid.php` line 1082–1084: edit mode checks `end_date`; if auction has ended, flashes "Cannot edit a bid after the auction has ended." During active BP (not ended), edits are permitted. Edit button visibility in the view uses `$canEditWithdraw = $isBidOwner && !$isExpired && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected'` — timer-active means `!$isExpired` is true, so edit button is shown. |
| 10 | Agents submit counter-bids while timer active? | **Open — no timer guard.** Counter-bid UI (agent's counter to owner's counter-offer) is shown when a counter-term exists. The counter-bid submission controller does not check the timer, so server-side a counter-bid could be POSTed during an active BP. |
| 11 | What happens when timer expires? | On next page load: (a) `autoTransitionBpToPending()` writes `listing_status = 'Pending'` to meta; (b) `$isExpired` becomes true in the view; (c) `$canTakeAction` becomes true — Accept/Reject/Counter buttons appear; (d) "View Full Bid" link becomes active (full modal); (e) the bid submission guard in the controller now blocks new bids (status = 'Pending'). No DB write happens at the exact millisecond of expiry — it is triggered lazily on the next request. |
| 12 | Enforcement: UI-only, server-only, or both? | **Both** for bid submission (Livewire `end_date` check + status guard on controller). **UI-only** for Accept/Reject/Counter owner actions. **UI-only** for "View Full Bid" lock. |
| 13 | Traditional: can owner act immediately? | N/A — see 3.2. |
| 14 | Status consistency? | `accepted` column on `tenant_agent_auction_bids` stores strings `'accepted'` / `'rejected'` / `null`. `TenantCounterTerm` uses a string `accepted` column consistent with parent bid table. |
| 15 | Mode-specific messaging? | **Detail page: Yes.** Timer countdown shown for BP (view line 1413). "Actions unlock when the bidding period ends." shown in bid action area (line 3433). Lock icon on "View Full Bid." **Dashboard/list: No.** `hire_tenant_agent/list.blade.php` contains no `auction_type` or mode-specific display (confirmed by grep). |

---

### 3.2 Tenant — Traditional

**Files:** same as 3.1

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids? | **Yes — immediately and always.** No timer guard. `$canSeeBidSummary = $isListingOwner \|\| !$isAgentViewer \|\| $isBiddingPeriodListing` — for Traditional with `$isListingOwner = true`, summary is visible. Source: view line 1554. |
| 2 | Full details or summary only? | **Full details immediately.** "View Full Bid" renders as a live `<a>` link to `#privateDataModal` for listing owner or bid owner. No lock condition applies (lock only triggers on `$isBiddingPeriodListing && $isBiddingTimerActive`). Source: lines 2144–2157. |
| 3 | Owner sees private compensation? | **Yes — always.** Traditional mode has no timer; compensation in the full modal is accessible as soon as any bid exists. |
| 4 | Owner Accept immediately? | **Yes.** `$canTakeAction = $isTraditionalListing \|\| ...` is always true for Traditional. `$showActionButtons = ($isTraditionalListing && !$isExpired) \|\| ...` — true while listing is active. Accept button rendered immediately. Source: line 3403. |
| 5 | Owner Reject immediately? | **Yes** — same condition as Accept. Reject button shown immediately. |
| 6 | Owner Counter immediately? | **Yes.** Counter Bid link shown in the `$showActionButtons` block immediately. |
| 7 | Hidden, disabled, or server-blocked? | Action buttons are rendered when `$showActionButtons` is true. Server-side accept/reject/counter: no timer check, only ownership + terminal-state check. Correct — Traditional should never have a timer gate. |
| 8 | Agents submit new bids? | N/A — Traditional has no timer. Agents can submit bids freely until the listing is expired (`$isExpired`) or accepted. Controller status guard still blocks if status is 'Hired Agent'/'Pending'/'Expired'. |
| 9 | Agents edit bids? | N/A — Traditional has no timer. Edit button shown while `!$isExpired && bidAccepted !== 'accepted'/'rejected'`. Livewire `end_date` check also applies (if listing is expired). |
| 10 | Agents submit counter-bids? | N/A — Traditional has no timer. Counter-bid submission is open whenever the bid is in a negotiable state. |
| 11 | What happens when listing expires? | For Traditional, `$expiration` is the listing lifecycle expiration date (not an auction timer). When `$isExpired` is true: bid submission blocked by Livewire; `$showActionButtons` uses `$isTraditionalListing && !$isExpired` so owner action buttons disappear; a "Listing has expired" notice is shown (view line 3412). |
| 12 | Enforcement: UI-only, server-only, or both? | **Both** for bid submission (Livewire `end_date` check). **UI-only** for hiding owner action buttons after listing expiry. |
| 13 | Traditional: can owner act immediately? | **Yes** — Accept, Reject, and Counter are available the moment a bid arrives, with no waiting period. |
| 14 | Status consistency? | Same as BP: `'accepted'`/`'rejected'`/`null` strings on the `accepted` column. Consistent. |
| 15 | Mode-specific messaging? | **Detail page: Yes.** No countdown timer shown for Traditional. "Bid information is private for traditional listings" shown to non-owner agents (view line 1568). **Dashboard/list: No.** No mode badge or label in `list.blade.php`. |

---

### 3.3 Seller — Bidding Period

**Files:** `resources/views/hire_seller_agent/view.blade.php`, `app/Http/Controllers/SellerAgentAuctionController.php`, `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids before timer ends? | **Yes — bid cards rendered but full content locked.** `$canViewBid = $isListingOwner \|\| $isBidOwner \|\| ($isBiddingPeriodListing && $isAgent && $userHasBid)` filters which bids are shown. Source: `view.blade.php` line 2811. |
| 2 | Full details or summary only before timer? | **Summary only for owner during active timer.** "View Full Bid" is a disabled span with lock icon when `$isBiddingPeriodListing && $isBiddingTimerActive && $isListingOwner`. Identical logic to Tenant. Source: lines 3039–3047. |
| 3 | Owner sees private compensation before timer? | **No.** Same lock as Tenant — full modal (`#privateDataModal`) is inaccessible to owner during active timer. |
| 4 | Owner Accept before timer ends? | **No — UI hides Accept button.** `$isBiddingPeriodListing && $isBiddingTimerActive` causes a "Actions unlock when the bidding period ends." yellow banner instead of buttons. Source: view lines 4451–4454. |
| 5 | Owner Reject before timer ends? | **No — UI hides Reject button.** Same `$isBiddingTimerActive` condition. |
| 6 | Owner Counter before timer ends? | **No — Counter Bid link absent** while timer is active. Source: view lines 4451–4454 (same block). |
| 7 | Hidden, disabled, or server-blocked? | **UI-hidden** (yellow banner replaces buttons). The server-side methods `acceptSABid` and `rejectSABid` in `SellerAgentAuctionController` contain **no timer check** — only ownership verification and `$bid->accepted` terminal state check. A direct POST bypassing the UI would succeed. No counter function timer check either. |
| 8 | Agents submit new bids while timer active? | **Yes — but with a critical gap.** `saveSABid` in `SellerAgentAuctionController` (line 476) has **no listing status guard** (`in_array` check). Unlike Tenant/Buyer/Landlord, even after `listing_status` is set to `'Pending'` by `autoTransitionBpToPending()`, a POST to `saveSABid` will succeed. This is a server-side security gap. Source: `SellerAgentAuctionController` lines 476–515. |
| 9 | Agents edit bids while timer active? | **Partially enforced — UI-only.** Edit button visibility uses `$canEditWithdraw = $isBidOwner && !$isExpired && bidAccepted !== 'accepted'/'rejected'`. `SellerAgentAuctionBid.php` Livewire has no `isBiddingPeriodListing` property or `end_date` check equivalent to Tenant's Livewire guard. Edit enforcement is primarily UI-layer. |
| 10 | Agents submit counter-bids while timer active? | **Open — no timer guard.** Seller counter-bid flow (seller issues counter, agent accepts/rejects) — agent counter-acceptance has no timer guard in the view or controller. |
| 11 | What happens when timer expires? | Same lazy-load mechanism: `autoTransitionBpToPending()` sets `listing_status = 'Pending'`. View variables `$isExpired`/`$isBiddingTimerActive` flip. Accept/Reject/Counter buttons appear. Full "View Full Bid" link becomes active. However, `saveSABid` status-guard gap means bid submission is still not blocked server-side even after status changes to 'Pending'. |
| 12 | Enforcement: UI-only, server-only, or both? | **UI-only** for owner Accept/Reject/Counter timer gate. **UI-only** for bid edit gate. **Neither** for new bid submission (`saveSABid` has no status guard at all). |
| 13 | Traditional: owner act immediately? | N/A — see 3.4. |
| 14 | Status consistency? | `accepted` column on `seller_agent_auction_bids` uses strings `'accepted'`/`'rejected'`/`null`. Consistent with Tenant/Buyer/Landlord. However, `SellerCounterTerm` uses **integer `status`** (0 = rejected/inactive, 1 = active/accepted) rather than a string `accepted` column — different from `TenantCounterTerm`. Source: `SellerAgentAuctionBid.php` line 52; `SellerCounterTerm` query `where('status', 1)`. |
| 15 | Mode-specific messaging? | **Detail page: Yes.** Timer countdown shown for BP. "Actions unlock when the bidding period ends." banner displayed. **Label bug:** view line 412 displays `"Auction Length:"` instead of `"Bidding Period Length:"`. **Dashboard/list: No.** `hire_seller_agent/list.blade.php` (242 lines) contains no `auction_type` or mode-specific display. |

---

### 3.4 Seller — Traditional

**Files:** same as 3.3

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids? | **Yes — immediately.** No timer gate. Same `$canViewBid` logic as BP. |
| 2 | Full details or summary only? | **Full details immediately.** "View Full Bid" link is a live `<a>` for owner/bid-owner. No lock condition for Traditional. |
| 3 | Owner sees private compensation? | **Yes — immediately** via `#privateDataModal`. |
| 4 | Owner Accept immediately? | **Yes.** `$canTakeAction = $isTraditionalListing \|\| ...` is true. BP timer condition false; Accept button rendered immediately. Source: view line 2536. |
| 5 | Owner Reject immediately? | **Yes** — same condition as Accept. |
| 6 | Owner Counter immediately? | **Yes** — Counter Bid link shown immediately. Source: view lines 4459–4483. |
| 7 | Hidden, disabled, or server-blocked? | Buttons are shown for Traditional when bid is undecided and listing is not sold/expired. Server-side: no timer check (correct for Traditional). |
| 8 | Agents submit new bids? | **Yes — and no status guard** in `saveSABid`. Unlike Tenant/Buyer/Landlord, even after the listing transitions to 'Hired Agent' or 'Pending', `saveSABid` does not block submission. This gap exists in both Traditional and BP mode for Seller. |
| 9 | Agents edit bids? | Edit button shown via `$canEditWithdraw` while `!$isExpired && !terminal`. Edits allowed while listing is active. |
| 10 | Agents submit counter-bids? | Open — agent can accept/reject seller counter-offers when in negotiable state. |
| 11 | What happens when listing expires? | `$isExpired` true → `$showActionButtons = $isTraditionalListing && !$isExpired` becomes false → owner action buttons disappear. "Listing has expired" notice shown. `$canEditWithdraw` becomes false (bid edit hidden). |
| 12 | Enforcement: UI-only, server-only, or both? | **UI-only** across the board. `saveSABid` is entirely unguarded by status. Accept/Reject/Counter server methods have no expiry check. |
| 13 | Traditional: can owner act immediately? | **Yes** — Accept, Reject, Counter available instantly. Matches Tenant gold standard. |
| 14 | Status consistency? | `accepted` column strings consistent with Tenant/Buyer/Landlord. `SellerCounterTerm.status` int inconsistency persists regardless of mode. |
| 15 | Mode-specific messaging? | **Detail page: Partial.** "Bid information is private for traditional listings" message shown at view line 2684 when `$isTraditionalListing && $otherBidsExist`. **Dashboard/list: No.** No mode label in list view. |

---

### 3.5 Buyer — Bidding Period

**Files:** `resources/views/buyerAgentAuctionDetail.blade.php`, `app/Http/Controllers/BuyerAgentAuctionBidController.php`, `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids before timer ends? | **Yes — bid cards visible.** `$canViewBid = $isListingOwner \|\| $isBidOwner` (view line 2349), but the `continue` gate is `if (!$canViewBid && !$isBiddingPeriodListing) { continue; }` (line 2350). During an active BP (`$isBiddingPeriodListing = true`), the `continue` is never reached — **all agents, including competitors, can see bid cards regardless of `$canViewBid`**. |
| 2 | Full details or summary only before timer? | **Summary only for owner.** "View Full Bid" disabled with lock icon when `$isBiddingTimerActive && $isListingOwner && !$isBidOwner`. Source: lines 2602–2610. Same as Tenant. |
| 3 | Owner sees private compensation before timer? | **No.** `#privateDataModal` inaccessible while timer active. Same as Tenant. |
| 4 | Owner Accept before timer ends? | **No — UI hides Accept.** `$showBuyerActionButtons = ($isTraditionalListing && !$isExpired) \|\| ($isBiddingPeriodListing && $isExpired)` — false during active BP. Source: view line 4027. |
| 5 | Owner Reject before timer ends? | **No — UI hides Reject.** Same condition as Accept. |
| 6 | Owner Counter before timer ends? | **No — Counter link absent.** Inside the same `$showBuyerActionButtons` block. |
| 7 | Hidden, disabled, or server-blocked? | **UI-hidden.** "Actions unlock when the bidding period ends." message shown (view line 4061). Server-side `accept_bid`, `reject_bid`, `counter_bid` in `BuyerAgentAuctionBidController` contain **no timer check** — only ownership + terminal-state check. Direct POST would succeed. |
| 8 | Agents submit new bids while timer active? | **Yes — gated by status guard.** `BuyerAgentAuctionBidController` line 41: `in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])` blocks submission once status is set. During active BP, status is not 'Pending' yet, so new bids are accepted. Each new bid **increments `expiration_date` +1 day** (controller lines 133–140), extending the timer on each submission. |
| 9 | Agents edit bids while timer active? | **UI-only enforcement.** `$canEditWithdraw = $isBidOwner && !$isExpiredBid && bidAccepted !== 'accepted'/'rejected'`. Edit button shown while timer active. No Livewire `end_date` check equivalent to Tenant's was found in `BuyerAgentAuctionBid.php`. |
| 10 | Agents submit counter-bids while timer active? | **Open — no timer guard.** Buyer counter-bid routes (agent counter to owner's counter) have no timer guard. Counter-bid submission UI appears when owner has issued a counter. |
| 11 | What happens when timer expires? | `autoTransitionBpToPending()` writes 'Pending'. View variables flip. Owner action buttons appear. "View Full Bid" link becomes live. Bid submission blocked by status guard. The +1 day extension stops occurring once the timer has expired. |
| 12 | Enforcement: UI-only, server-only, or both? | **Both** for new bid submission (status guard in controller). **UI-only** for owner Accept/Reject/Counter. **UI-only** for bid edits. |
| 13 | Traditional: owner act immediately? | N/A — see 3.6. |
| 14 | Status consistency? | `accepted` column on `buyer_agent_auction_bids` uses strings `'accepted'`/`'rejected'`/`null`. Consistent with Tenant/Landlord/Seller bid tables. No counter-term integer status anomaly found for Buyer. |
| 15 | Mode-specific messaging? | **Detail page: Yes.** Timer countdown shown for BP. "Actions unlock when the bidding period ends." at line 4061. **Key divergence:** Buyer view does **not** implement `#limitedBidModal` for competing agents in BP. Competing agents see bid cards (BP `continue` gate does not filter them, line 2350) but see only `"Private - visible only to listing creator"` (grey lock span, lines 2617–2620) — no services or compensation info at all. Tenant and Seller show a "View Full Services & Broker Compensation Terms" limited modal to competing agents during active BP. **Dashboard/list: No.** `buyerAgentAuctionList.blade.php` contains no `auction_type` or mode label. |

---

### 3.6 Buyer — Traditional

**Files:** same as 3.5

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids? | **Yes — immediately.** `$canViewBid = $isListingOwner \|\| $isBidOwner`; no timer gate. |
| 2 | Full details or summary only? | **Full details immediately.** "View Full Bid" is a live link for owner/bid-owner. Source: view lines 2611–2615. |
| 3 | Owner sees private compensation? | **Yes — immediately.** `#privateDataModal` accessible without restriction. |
| 4 | Owner Accept immediately? | **Yes.** `$showBuyerActionButtons = ($isTraditionalListing && !$isExpired) \|\| ...` is true. Accept button shown. |
| 5 | Owner Reject immediately? | **Yes** — same condition. Reject button shown. |
| 6 | Owner Counter immediately? | **Yes** — Counter link in same block. Source: view line 4037. |
| 7 | Hidden, disabled, or server-blocked? | Buttons shown for undecided bids. Server-side: no timer check (correct for Traditional). |
| 8 | Agents submit new bids? | Allowed while status guard passes. Submission also triggers +1 day `expiration_date` increment — this applies even in Traditional mode where no timer is expected. Incrementing `expiration_date` on a Traditional listing serves no purpose and unexpectedly extends the listing lifecycle date. |
| 9 | Agents edit bids? | Edit button shown via `$canEditWithdraw` while `!$isExpiredBid && !terminal`. Allowed. |
| 10 | Agents submit counter-bids? | Open — agent can respond to owner counter-offers when in negotiable state. |
| 11 | What happens when listing expires? | `$isExpired` true → `$showBuyerActionButtons` false → owner action buttons hidden. Bid submission blocked by Livewire/controller `end_date` check. |
| 12 | Enforcement: UI-only, server-only, or both? | **Both** for bid submission (status guard). **UI-only** for owner action hiding after expiry. |
| 13 | Traditional: can owner act immediately? | **Yes** — Accept, Reject, Counter available as soon as a bid exists. Matches Tenant gold standard. |
| 14 | Status consistency? | Consistent — string `'accepted'`/`'rejected'`/`null`. |
| 15 | Mode-specific messaging? | **Detail page: Partial.** No timer countdown for Traditional. No "actions locked" message. **Dashboard/list: No.** No mode label in `buyerAgentAuctionList.blade.php`. |

---

### 3.7 Landlord — Bidding Period

**Files:** `resources/views/hire_landlord_agent/view.blade.php`, `app/Http/Controllers/LandlordAgentAuctionBidController.php`, `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids before timer ends? | **Yes — bid cards visible.** `$canViewBid = $isListingOwner \|\| $isBidOwner \|\| ($isBiddingPeriodListing && $isAgent && $userHasBid)`. Source: view line 2378. Identical pattern to Tenant and Seller (competing agents who have bid can see cards). |
| 2 | Full details or summary only before timer? | **Summary only for owner.** "View Full Bid" disabled with lock icon when `$isBiddingPeriodListing && $isBiddingTimerActive && $isListingOwner && !$isBidOwner`. Source: view lines 2615–2622. Same as Tenant. |
| 3 | Owner sees private compensation before timer? | **No.** `#privateDataModal` inaccessible while timer active. Same as Tenant. |
| 4 | Owner Accept before timer ends? | **No — UI hides Accept.** `$showActionButtons = ($isTraditionalListing && !$isExpired) \|\| ($isBiddingPeriodListing && $isExpired)` — false during active BP. Source: view line 4150. |
| 5 | Owner Reject before timer ends? | **No — UI hides Reject.** Same condition. |
| 6 | Owner Counter before timer ends? | **No — Counter link absent during BP.** Source: view line 4150 (same block). |
| 7 | Hidden, disabled, or server-blocked? | **UI-hidden.** "Actions unlock when the bidding period ends." shown at view line 4175. Server-side `accept_bid`, `reject_bid`, `counter_bid` in `LandlordAgentAuctionBidController` contain **no timer check** — only ownership + terminal-state check. Direct POST would succeed. |
| 8 | Agents submit new bids while timer active? | **Yes — gated by status guard.** `LandlordAgentAuctionBidController` line 37: `in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])` blocks submission once status is set. During active BP, status is not 'Pending', so new bids are accepted. No timer extension on new bid (unlike Seller/Buyer). |
| 9 | Agents edit bids while timer active? | **UI-only enforcement.** `$canEditWithdraw = $isBidOwner && !$isExpired && bidAccepted !== 'accepted'/'rejected'`. Edit button shown while timer active. `LandlordAgentAuctionBid.php` Livewire lines 772, 778: checks `accepted`/`rejected` state only — **no end_date / BP timer check** for edit mode. Unlike Tenant, there is no server-side `end_date` check blocking edits. |
| 10 | Agents submit counter-bids while timer active? | **Open — no timer guard.** `LandlordCounterTerm` submission routes have no timer guard. Counter-bid UI appears to both listing owner and bid owner (`$showCounterBids = $isListingOwner \|\| $isBidOwner`). |
| 11 | What happens when timer expires? | `autoTransitionBpToPending()` writes 'Pending'. View variables flip. Owner action buttons appear. "View Full Bid" link becomes live. Bid submission blocked by status guard. |
| 12 | Enforcement: UI-only, server-only, or both? | **Both** for new bid submission (status guard in controller). **UI-only** for owner Accept/Reject/Counter. **UI-only** for bid edits (no Livewire `end_date` guard, unlike Tenant). |
| 13 | Traditional: owner act immediately? | N/A — see 3.8. |
| 14 | Status consistency? | `accepted` column on `landlord_agent_auction_bids` uses strings `'accepted'`/`'rejected'`/`null`. Consistent with Tenant/Buyer/Seller bid tables. However, `LandlordCounterTerm` uses **integer `status`** field (not a string `accepted` column): `status = 1` means accepted/active. Source: `LandlordAgentAuctionBid.php` line 51. Mirrors the `SellerCounterTerm` inconsistency. |
| 15 | Mode-specific messaging? | **Detail page: Yes.** Timer countdown for BP. "Actions unlock when the bidding period ends." banner shown. **No `#limitedBidModal`:** Landlord view does **not** render a limited modal for competing agents even though `$canViewBid` includes competing-agent bidders. Competing agents see bid cards but have no way to view limited service/compensation terms. Tenant and Seller render a "View Full Services & Broker Compensation Terms" link/modal. **Dashboard/list: No.** `hire_landlord_agent/list.blade.php` contains no `auction_type` or mode label. |

---

### 3.8 Landlord — Traditional

**Files:** same as 3.7

| # | Item | Finding |
|---|------|---------|
| 1 | Owner can view bids? | **Yes — immediately.** No timer gate. Same `$canViewBid` logic. |
| 2 | Full details or summary only? | **Full details immediately.** "View Full Bid" is live for owner/bid-owner. No lock condition. |
| 3 | Owner sees private compensation? | **Yes — immediately.** `#privateDataModal` accessible. |
| 4 | Owner Accept immediately? | **Yes.** `$showActionButtons = ($isTraditionalListing && !$isExpired)` is true. Accept button shown. Source: view line 4150. |
| 5 | Owner Reject immediately? | **Yes** — same condition. |
| 6 | Owner Counter immediately? | **Yes** — Counter link in same block. |
| 7 | Hidden, disabled, or server-blocked? | Buttons shown for undecided bids while listing is active. Server-side: no timer check (correct for Traditional). |
| 8 | Agents submit new bids? | Allowed while status guard passes. No timer extension on submission (consistent with Tenant, unlike Buyer/Seller). |
| 9 | Agents edit bids? | Edit button via `$canEditWithdraw` while `!$isExpired && !terminal`. Allowed. |
| 10 | Agents submit counter-bids? | Open — agent can respond to owner counter-offers in negotiable state. |
| 11 | What happens when listing expires? | `$isExpired` true → `$showActionButtons` false → owner action buttons hidden. Bid submission blocked by status guard. |
| 12 | Enforcement: UI-only, server-only, or both? | **Both** for bid submission (status guard). **UI-only** for owner action hiding after expiry. |
| 13 | Traditional: can owner act immediately? | **Yes** — Accept, Reject, Counter available instantly. Matches Tenant gold standard. |
| 14 | Status consistency? | Consistent for bid table (`'accepted'`/`'rejected'`/`null`). `LandlordCounterTerm.status` int inconsistency persists regardless of mode. |
| 15 | Mode-specific messaging? | **Detail page: Partial.** No timer countdown for Traditional. `$canSeeBidSummary` logic may suppress agent bid access appropriately. **Dashboard/list: No.** No mode label in `hire_landlord_agent/list.blade.php`. |

---

## 4. Status Field Consistency Matrix

| Role | Bid `accepted` field | Counter-term status field |
|------|----------------------|--------------------------|
| Tenant | String: `null` / `'accepted'` / `'rejected'` on `tenant_agent_auction_bids.accepted` | String `accepted` column (consistent with bid table) |
| Seller | String: `null` / `'accepted'` / `'rejected'` on `seller_agent_auction_bids.accepted` | **Integer `status`** (0 = rejected/inactive, 1 = active) on `seller_counter_terms.status` |
| Buyer | String: `null` / `'accepted'` / `'rejected'` on `buyer_agent_auction_bids.accepted` | String approach — no integer inconsistency found |
| Landlord | String: `null` / `'accepted'` / `'rejected'` on `landlord_agent_auction_bids.accepted` | **Integer `status`** (0 = rejected/inactive, 1 = active) on `landlord_counter_terms.status` |

The bid's own `accepted` column is consistent across all four roles. The divergence is in the counter-term tables: Seller and Landlord use an integer `status`, while Tenant (and Buyer) use a string `accepted` column. This creates inconsistent query patterns (`where('status', 1)` vs `where('accepted', 'accepted')`).

---

## 5. Parity Matrix — Seller / Buyer / Landlord vs Tenant

**Legend:** ✅ Match | ⚠️ Drift | ❌ Missing / Bug

| Behavior | Tenant (gold standard) | Seller | Buyer | Landlord |
|----------|------------------------|--------|-------|----------|
| Mode detection (`$isBiddingPeriodListing`) | ✅ | ✅ | ✅ | ✅ |
| Timer variable (`$isBiddingTimerActive`) | ✅ | ✅ | ✅ | ✅ |
| `canTakeAction` formula | ✅ | ✅ | ✅ | ✅ |
| `autoTransitionBpToPending()` on page load | ✅ | ✅ | ✅ | ✅ |
| Owner "View Full Bid" locked during active BP | ✅ | ✅ | ✅ | ✅ |
| Owner action buttons hidden during active BP | ✅ | ✅ | ✅ | ✅ |
| Traditional: owner can act immediately | ✅ | ✅ | ✅ | ✅ |
| **New bid submission status guard (server-side)** | ✅ controller line 42 | ❌ **Missing** — `saveSABid` has no `in_array` status guard | ✅ controller line 41 | ✅ controller line 37 |
| **Bid edit Livewire `end_date` guard (server-side)** | ✅ `TenantAgentAuctionBid.php` line 1082–1084 | ⚠️ **Absent** — Livewire guard not found | ⚠️ **Absent** — Livewire guard not found | ⚠️ **Absent** — Livewire guard not found |
| **Timer extension (+1 day) on new bid submission** | ✅ No extension (correct) | ⚠️ **Extends +1 day** per bid (`saveSABid` lines 644–654) | ⚠️ **Extends +1 day** per bid (controller lines 133–140) | ✅ No extension (correct) |
| **Competing agents see limited bid modal in BP** | ✅ `#limitedBidModal` (services + compensation) | ✅ `#limitedBidModal` present (view line 3054–3058) | ❌ **Missing** — competing agents can see bid cards (BP `continue` gate at line 2350 does not filter them), but "View Full Bid" section shows only `"Private - visible only to listing creator"` (lines 2617–2620); no limited services/compensation info is revealed | ❌ **Missing** — competing agents see bid cards but no `#limitedBidModal` is rendered |
| Accept/Reject/Counter server-side timer guard | ⚠️ UI-only (no server timer check) | ⚠️ UI-only | ⚠️ UI-only | ⚠️ UI-only |
| Counter-term status field type | ✅ String `accepted` column | ⚠️ Integer `status` column | ✅ String (consistent) | ⚠️ Integer `status` column |
| Mode label in dashboard/list view | ❌ Not shown | ❌ Not shown | ❌ Not shown | ❌ Not shown |
| BP section label in listing header | ✅ Correct label | ⚠️ Shows `"Auction Length:"` (view line 412) instead of `"Bidding Period Length:"` | ✅ Shows `"Bidding Period Length:"` (view line 401) | ✅ Shows `"Bidding Period Length:"` (view line 389) |

---

## 6. Recommended Surgical Fixes

The following fixes are recommended for a follow-up task. **No code changes were applied in this audit.**

---

### FIX-01 — Add status guard to `saveSABid` *(Critical)*

**File:** `app/Http/Controllers/SellerAgentAuctionController.php`  
**Method:** `saveSABid` (line 476)  
**Problem:** No `in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])` check. Agents can POST new bids to a Seller listing that has already transitioned to 'Pending', 'Hired Agent', or 'Expired'.  
**Fix:** Add the same guard used by `TenantAgentAuctionBidController` (line 42), `BuyerAgentAuctionBidController` (line 41), and `LandlordAgentAuctionBidController` (line 37) at the top of `saveSABid`.

---

### FIX-02 — Resolve spurious `expiration_date` +1 day increment from Buyer bid submission

**File:** `app/Http/Controllers/BuyerAgentAuctionBidController.php`  
**Lines:** 133–140  
**Problem:** Every new bid submission extends `expiration_date` by +1 day regardless of listing mode. For Traditional listings this is meaningless (and potentially harmful). For BP listings, the timer-extension behaviour is inconsistent with Tenant and Landlord and undocumented.  
**Fix options:** (a) Remove the increment entirely to match Tenant/Landlord. (b) Gate it on `$isBiddingPeriodListing` if the business rule is intentional, and apply consistently to Seller/Landlord as well.

---

### FIX-03 — Resolve spurious `expiration_date` +1 day increment from Seller bid submission

**File:** `app/Http/Controllers/SellerAgentAuctionController.php`  
**Lines:** 644–654  
**Problem:** Same as FIX-02 — Seller extends `expiration_date` per bid. Inconsistent with Tenant and Landlord.  
**Fix:** Align with the decision made in FIX-02.

---

### FIX-04 — Add `#limitedBidModal` for competing agents on Buyer Bidding Period listings

**File:** `resources/views/buyerAgentAuctionDetail.blade.php`  
**Problem:** Competing agents do see bid cards during active BP (the `continue` guard at line 2350 — `if (!$canViewBid && !$isBiddingPeriodListing) { continue; }` — does not skip them in BP mode). However, the "View Full Bid" section for non-owner/non-bid-owner users falls to the `@else` branch showing only `"Private - visible only to listing creator"` (lines 2617–2620). Tenant and Seller instead show a `#limitedBidModal` with limited services and broker compensation terms to competing agents. Buyer reveals no information at all to competitors.  
**Fix:** In the "View Full Bid" section (around line 2617), add an `@elseif ($isBiddingPeriodListing && $isAgentViewer && !$isBidOwner)` branch that renders a `#limitedBidModal` equivalent to Tenant's view lines 2159–2164, showing limited services and compensation terms without revealing identity.

---

### FIX-05 — Add `#limitedBidModal` for competing agents on Landlord Bidding Period listings

**File:** `resources/views/hire_landlord_agent/view.blade.php`  
**Problem:** Landlord view includes competing agents in `$canViewBid` (line 2378) so bid cards are shown. However, no `#limitedBidModal` is rendered. Competing agents see a bid card with no way to view even limited services/compensation terms.  
**Fix:** Add the limited-modal template block equivalent to `hire_tenant_agent/view.blade.php` lines 2159–2164, with a corresponding `#limitedBidModal` definition rendered per bid.

---

### FIX-06 — Add Livewire server-side edit guard for Seller bid edits during BP

**File:** `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`  
**Problem:** Unlike `TenantAgentAuctionBid.php` (lines 1082–1084) which blocks bid edits server-side after the auction has ended, the Seller Livewire component has no equivalent `end_date` check. Bid edit enforcement for Seller is UI-only.  
**Fix:** Add `end_date` / auction-mode check in the Seller Livewire edit path, mirroring Tenant's implementation.

---

### FIX-07 — Add Livewire server-side edit guard for Buyer bid edits during BP

**File:** `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`  
**Problem:** Same as FIX-06 — no `end_date` or timer check in Buyer's Livewire edit mode. Edit enforcement is UI-only.  
**Fix:** Same pattern as FIX-06.

---

### FIX-08 — Add Livewire server-side edit guard for Landlord bid edits during BP

**File:** `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`  
**Problem:** `LandlordAgentAuctionBid.php` line 772 checks terminal accepted/rejected state but not BP expiry. Edit enforcement is UI-only.  
**Fix:** Same pattern as FIX-06.

---

### FIX-09 — Add server-side timer guard to Accept/Reject/Counter methods for all roles *(Shared)*

**Files:**
- `app/Http/Controllers/TenantAgentAuctionBidController.php` (`accept_bid`, `reject_bid`, `counter_bid`)
- `app/Http/Controllers/BuyerAgentAuctionBidController.php` (`accept_bid`, `reject_bid`, `counter_bid`)
- `app/Http/Controllers/LandlordAgentAuctionBidController.php` (`accept_bid`, `reject_bid`, `counter_bid`)
- `app/Http/Controllers/SellerAgentAuctionController.php` (`acceptSABid`, `rejectSABid`, counter method)

**Problem:** All four roles' owner action methods contain no check that the bidding period has expired before allowing the action. A crafted direct POST can accept or reject a bid while the timer is still active.  
**Fix:** Each method should load the listing's `auction_type` and `auction_time` meta, compute `$isExpired`, and return a redirect-with-error if `$isBiddingPeriodListing && !$isExpired`.

---

### FIX-10 — Fix `"Auction Length:"` label on Seller listing header

**File:** `resources/views/hire_seller_agent/view.blade.php`  
**Line:** 412  
**Problem:** The label reads `"Auction Length:"` but should read `"Bidding Period Length:"` to match the mode label used elsewhere in the UI.  
**Fix:** Change the string on line 412 from `"Auction Length:"` to `"Bidding Period Length:"`.

---

### FIX-11 — Normalise counter-term `status` field for Seller and Landlord

**Files:** `SellerCounterTerm` model/migration, `LandlordCounterTerm` model/migration  
**Problem:** Seller and Landlord counter-terms use an integer `status` column (0/1) while Tenant (and Buyer) use a string `accepted` column. This creates inconsistent query patterns and makes cross-role reasoning harder.  
**Fix options:** (a) Migrate Seller/Landlord counter-term tables to add a string `accepted` column consistent with Tenant (requires data migration). (b) Add model accessor methods normalising the value to `'accepted'`/`'rejected'`/`null` for all consumers without a schema change.

---

### FIX-12 — Add mode-specific badge/label to all four dashboard list views

**Files:** `hire_tenant_agent/list.blade.php`, `hire_seller_agent/list.blade.php`, `buyerAgentAuctionList.blade.php`, `hire_landlord_agent/list.blade.php`  
**Problem:** None of the four list pages display the listing's mode. Users browsing their listings cannot distinguish Bidding Period from Traditional in the list view.  
**Fix:** Add a small badge or label per listing card reading the `auction_type` meta value and displaying `"Bidding Period"` or `"Traditional"` consistently across all four list views.

---

*End of audit report. All findings are based on direct code traces of the current codebase. No code changes were applied.*
