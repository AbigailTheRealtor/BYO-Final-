# Bidding Period / Traditional Listing Type — Full Audit Report

**Date:** April 8, 2026  
**Scope:** Read-only audit across all 4 roles (Tenant, Buyer, Seller, Landlord)  
**Purpose:** Map every location where Bidding Period vs Traditional logic is stored, enforced, or displayed; produce a concrete soft-deadline-only conversion recommendation.

---

## Table of Contents

1. [Field Inventory (DB + Meta)](#1-field-inventory)
2. [Create / Edit Flows per Role](#2-createedit-flows-per-role)
3. [Action Enforcement per Role](#3-action-enforcement-per-role)
4. [Status Logic](#4-status-logic)
5. [Timer UI Locations](#5-timer-ui-locations)
6. [Background / Scheduled Logic](#6-background--scheduled-logic)
7. [Role-by-Role Differences Table](#7-role-by-role-differences-table)
8. [Flagged Inconsistencies](#8-flagged-inconsistencies)
9. [Soft-Deadline-Only Conversion Recommendation](#9-soft-deadline-only-conversion-recommendation)

---

## 1. Field Inventory

### 1.1 Primary Listing Type Fields

| Concept | Location | Field / Key | Roles Affected | Valid Values |
|---|---|---|---|---|
| Listing type (primary) | Meta table (`*_metas`) | `auction_type` | All 4 agent roles | `'Bidding Period'`, `'Traditional'`, `'Auction (Timer)'` (Tenant legacy) |
| Listing type (redundant DB column) | `seller_agent_auctions`, `buyer_agent_auctions`, `landlord_agent_auctions` table | `auction_type` | Seller, Buyer, Landlord | Same values — saved at creation time and on edit |
| Bidding Period timer length | Meta table | `auction_time` | All 4 roles (only populated when `auction_type = 'Bidding Period'`) | e.g. `'3 Days'`, `'7 Days'`, `'2 Weeks'`, `'5 Hours'` |
| Auction length (numeric shorthand) | Meta table | `auction_length` | All 4 roles | e.g. `'7 Days'` (raw form value) |
| Auction length (days integer, legacy) | Meta table | `auction_length_days` | All 4 roles | Integer string; `-1` if non-day unit |
| Soft deadline date | Meta table | `expiration_date` | All 4 roles | Date string (Carbon parseable) |
| Hard-ended flag (Tenant) | `tenant_agent_auctions` table | `auction_ended` | **Tenant only** | boolean (no migration adds this for Buyer/Seller) |
| Hard-ended flag (Landlord) | `landlord_agent_auctions` table | `auction_ended` | **Landlord only** | boolean — added by migration `2025_02_01_150253` |
| Hard-ended flag (Property) | `property_auctions` table | `auction_ended` | Property (out of scope) | boolean — added by migration `2025_02_06_062653` |

**Key observation:** `auction_ended` is a DB column on `tenant_agent_auctions` (always existed in schema) and `landlord_agent_auctions` (added by migration). It does **not** exist as a DB column on `buyer_agent_auctions` or `seller_agent_auctions`.

### 1.2 Derived / Computed Status

| Concept | Storage | Key | Values |
|---|---|---|---|
| Listing lifecycle status | Meta table | `listing_status` | `'Hired Agent'`, `'Pending'` (Active / Expired are computed, not stored) |
| Sold flag | DB column | `is_sold` | boolean — marks listing as completed / hired |
| Sold date | DB column | `sold_date` | datetime |

### 1.3 Anonymous Bidder Mapping (Bidding Period only)

| Table | Relevant Columns | Purpose |
|---|---|---|
| `bidding_period_agent_mappings` | `auction_id`, `auction_type` (e.g. `'tenant_agent'`), `agent_user_id`, `anonymous_number` | Assigns a random integer label ("Agent 347") to each agent on a Bidding Period listing so competitors cannot identify each other during the active timer |

---

## 2. Create / Edit Flows per Role

### 2.1 Tenant (`TenantAgentAuction`)

**Create — Livewire component:** `app/Http/Livewire/TenantAgentAuction.php`  
**Edit — Livewire component:** `app/Http/Livewire/TenantAgentAuctionEdit.php`  
**Controller (legacy store/update):** `app/Http/Controllers/TenantAgentAuctionController.php`

- `auction_type` is a required Livewire property (line 40, validated at line 747: `'auction_type' => 'required'`).
- `auction_time` is conditionally required: `if ($this->auction_type === 'Bidding Period') { $rules['auction_time'] = 'required'; }` — lines 3354, 3394, 3435, 3479 in `TenantAgentAuctionEdit.php`.
- `expiration_date` is always required (line 3020: `if (empty(trim($this->expiration_date ?? ''))) $_svErrors[] = 'Expiration Date';`).
- On save, `auction_time` is cleared to `''` for Traditional: `$auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');` (line 3618 of `TenantAgentAuction.php`).
- **`auction_type` is LOCKED after creation** in the edit component: lines 3071–3072 of `TenantAgentAuctionEdit.php` are explicitly commented out (`// LOCKED: auction_type cannot be changed after listing creation`). The `auction_time` field is also locked in edit (line 3081).
- Controller `store()` method also saves `auction_type`, `auction_length`, and `expiration_date` as meta (lines 54–55, 53 of `TenantAgentAuctionController.php`).

### 2.2 Buyer (`BuyerAgentAuction`)

**Create — Livewire component:** `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php`  
**Edit — Livewire component:** `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php`  
**Controller:** `app/Http/Controllers/BuyerAgentAuctionController.php`

- `auction_type`, `expiration_date`, `auction_time` are all Livewire properties (lines 33, 38–39).
- `auction_time` is conditionally required for Bidding Period: `if ($this->auction_type === 'Bidding Period') { $validationRules['auction_time'] = 'required|string'; }` (line 2120–2121).
- On save: `$auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');` (line 1675).
- Controller `storeAuction()` saves `auction_type`, `auction_length`, `expiration_date` as meta (lines 142, 47, 141). Also saves `auction_length_days` as meta (lines 48–54).
- **`auction_type` is NOT explicitly locked** in the Buyer edit component — no equivalent locked comment found. The edit controller `updateBuyerAgentAuction()` does save `auction_type` on update (line 315), meaning it can be changed after creation.
- `BuyerAgentAuctionEdit.php` (line 1807): also saves `auction_type` on update — **no lock**.

### 2.3 Seller (`SellerAgentAuction`)

**Create — Livewire component:** `app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php`  
**Edit — Livewire component:** `app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php`  
**Controller:** `app/Http/Controllers/SellerAgentAuctionController.php`

- `auction_type`, `expiration_date`, `auction_time` are Livewire properties (lines 32, 37–38 of `SellerAgentAuction.php`).
- `auction_time` conditionally required for Bidding Period: line 2424–2425.
- On save: `$auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');` (line 1948).
- **Seller saves `auction_type` to both DB column and meta.** In `SellerAgentAuctionController::sellerAgentHireAuctionSave()` (lines 67–68): `$auction->auction_type = $request->auction_type; $auction->auction_length = $auction_lenth_days;` — written to the DB table directly, not just meta.
- Edit controller `updateSellerAgentHireAuction()` (line 334): `$auction->auction_type = $request->auction_type;` — also written to DB column on update.
- `SellerAgentAuctionEdit.php` (line 1807): saves `auction_type` on update — **no lock**.
- `SellerAgentAuction.php` Livewire save also handles `auction_type` clearing `auction_time` if not Bidding Period (line 2328–2329).

### 2.4 Landlord (`LandlordAgentAuction`)

**Create — Livewire component:** `app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php`  
**Edit — Livewire component:** `app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php`  
**Controller:** `app/Http/Controllers/LandlordAgentAuctionController.php`

- `auction_type`, `expiration_date`, `auction_time` are Livewire properties (lines 30, 35–36).
- `auction_time` conditionally required for Bidding Period.
- On save: `$auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');` (line 1912).
- **Landlord also saves `auction_type` to both DB column and meta.** Controller `store()` (lines 41–42): `$auction->auction_type = $request->auction_type; $auction->auction_length = $auction_length_days;` — written to DB.
- Edit controller `update()` (lines 231–232): also writes `auction_type` to DB column on update.
- `LandLordAgentAuctionEdit.php` (line 1807): saves `auction_type` on update — **no lock**.

### 2.5 Summary: Create / Edit Differences

| Role | `auction_type` required at create? | `auction_time` required for BP? | `auction_type` locked after creation? | Saves to DB column? | Saves to meta? |
|---|---|---|---|---|---|
| Tenant | Yes (Livewire validation) | Yes | **YES (explicitly locked)** | No | Yes |
| Buyer | Yes | Yes | No | No | Yes |
| Seller | Not validated server-side (no explicit required rule found) | Yes | No | **Yes** | Yes |
| Landlord | Not validated server-side (no explicit required rule found) | Yes | No | **Yes** | Yes |

---

## 3. Action Enforcement per Role

All 4 role detail views use an identical PHP block pattern computed at the top of the bid-management section. The variables and logic are the same across Tenant, Buyer, Seller, and Landlord views.

### 3.1 Shared Blade PHP Block Logic (all 4 roles)

```php
$listingType = trim($auction->get->auction_type ?? '');
$isTraditionalListing = (strtolower($listingType) === 'traditional' || empty($listingType));
$isBiddingPeriodListing = (strtolower($listingType) === 'bidding period');

// auction_time drives the Bidding Period countdown
$auction_time = trim($auction->get->auction_time ?? '');
$useAuctionTime = !empty($auction_time) && strtolower($auction_time) !== 'null';

// For Bidding Period: expiration = created_at + auction_time duration
// For Traditional: expiration = expiration_date (informational only)

$isBiddingTimerActive = $isBiddingPeriodListing && $expiration && !$isExpired;
$canTakeAction = $isTraditionalListing || ($isBiddingPeriodListing && $isExpired);
```

**File locations:**
- Tenant: `resources/views/hire_tenant_agent/view.blade.php` lines 1351–1410
- Buyer: `resources/views/buyerAgentAuctionDetail.blade.php` lines 2130–2189
- Seller: `resources/views/hire_seller_agent/view.blade.php` lines 2449–2508
- Landlord: `resources/views/hire_landlord_agent/view.blade.php` lines 2122–2183

### 3.2 Action Restrictions by Listing Type

| Action | Traditional | Bidding Period (timer active) | Bidding Period (timer expired) |
|---|---|---|---|
| Place bid (agent) | Allowed immediately | Allowed during timer | No new bids (listing goes to Pending) |
| Edit own bid (agent) | Allowed | Allowed | Allowed (if listing still active) |
| View competing bids | **Not allowed** (private) | Allowed via CompetingBids (anonymized, submit-to-view) | Not applicable (timer ended) |
| Accept bid (listing owner) | **Allowed immediately** | **BLOCKED** (timer active) | Allowed |
| Reject bid (listing owner) | **Allowed immediately** | **BLOCKED** (timer active) | Allowed |
| Counter bid (listing owner or agent) | **Allowed immediately** | **BLOCKED** (timer active) | Allowed |

**Blade conditions for action button display (same across all roles):**
```blade
{{-- Traditional (not expired) OR Bidding Period (timer ended): show buttons --}}
$showActionButtons = ($isTraditionalListing && !$isExpired) || ($isBiddingPeriodListing && $isExpired);

@if ($showActionButtons)
    {{-- Accept / Reject / Counter buttons --}}
@elseif ($isBiddingPeriodListing && !$canTakeAction)
    {{-- Timer still active — show "Bidding Period Active" message --}}
@elseif ($isTraditionalListing && $isExpired)
    {{-- Traditional listing has expired --}}
@endif
```

Tenant view reference: lines 3412–3449.  
Seller view: equivalent pattern exists but `$showActionButtons` is not a named variable — the `$canTakeAction` value drives it directly.  
Landlord view: `$canTakeAction` drives action visibility (line 2183).

### 3.3 Competing Bids Access Gate (Tenant only)

**`CompetingBidsController::viewCompetingBids()` — `app/Http/Controllers/CompetingBidsController.php` lines 24–29:**
```php
if (!$auction->isBiddingPeriodType()) {
    return back()->with('error', 'Competing bids are only visible for Bidding Period listings.');
}
if (!$auction->isBiddingPeriodActive()) {
    return back()->with('error', 'The bidding period has ended. Competing bids are no longer visible.');
}
```

**`CompetingBidsService::canViewCompetingBids()` — `app/Services/CompetingBidsService.php` line 25:**
```php
if (!$auction->isBiddingPeriodActive()) {
    return false;
}
```

This gate is **only implemented for Tenant** — the `CompetingBidsController` only works with `TenantAgentAuction`.

### 3.4 Bid Submission Guard (Landlord only)

`LandlordAgentAuctionBidController::save_bid()` lines 37–39:
```php
if (in_array($_listingGuard->status, ['Hired Agent', 'Pending', 'Expired'])) {
    return redirect()->back()->with('error', 'This listing is not currently accepting new bids.');
}
```
This is the only backend guard against submitting a bid when a listing is not active. Buyer and Seller controllers do not have an equivalent backend guard.

---

## 4. Status Logic

### 4.1 Status Attribute Methods

All four agent-role models have a `getStatusAttribute()` method, but with **different implementations**:

**Tenant model (`TenantAgentAuction`) — lines 26–43:**
```php
public function getStatusAttribute()
{
    $isSold = in_array($this->is_sold, [true, 'true', 1, '1'], true);
    if ($isSold) return 'Hired Agent';
    $metaStatus = $this->info('listing_status');
    if ($metaStatus === 'Hired Agent') return 'Hired Agent';
    if ($metaStatus === 'Pending') return 'Pending';
    if ($this->auction_ended) return 'Expired';  // <-- uses DB column
    return 'Active';
}
```
Tenant uses `auction_ended` DB column to compute `'Expired'`. Does **not** use `expiration_date` for status.

**Buyer model (`BuyerAgentAuction`) — line 96:**
```php
public function getStatusAttribute()
{
    $isSold = in_array($this->is_sold, [true, 'true', 1, '1'], true);
    if ($isSold) return 'Hired Agent';
    $metaStatus = $this->info('listing_status');
    if ($metaStatus === 'Hired Agent') return 'Hired Agent';
    if ($metaStatus === 'Pending') return 'Pending';
    $expirationDate = $this->info('expiration_date');
    if ($expirationDate && \Carbon\Carbon::now()->gte(\Carbon\Carbon::parse($expirationDate))) {
        return 'Expired';  // <-- uses expiration_date meta
    }
    return 'Active';
}
```

**Seller model (`SellerAgentAuction`) — line 79:** Identical to Buyer — uses `expiration_date` meta.

**Landlord model (`LandlordAgentAuction`) — line 79:** Identical to Buyer/Seller — uses `expiration_date` meta.

### 4.2 Status Transitions

| Transition | Trigger | How |
|---|---|---|
| `Active → Pending` | Bidding Period timer expires | `Controller::autoTransitionBpToPending()` called on every listing view for all 4 roles; sets `listing_status` meta to `'Pending'` |
| `Active → Hired Agent` | Listing owner accepts a bid | Sets `is_sold = true`, `sold_date`, and `listing_status` meta to `'Hired Agent'` (all 4 roles' bid controllers) |
| `Active → Expired` (Tenant) | `auction_ended = true` | Tenant model returns `'Expired'` when DB column is `true`; set by `LandlordAgentAuctionBidController::checkHireNowTerms()` (line 654) — **note: this sets it on a Landlord auction, not Tenant** |
| `Active → Expired` (Landlord DB) | `auction_ended = true` | `LandlordAgentAuctionController::endAuction()` (line 520) sets `auction_ended = true` |
| `Active → Expired` (Buyer/Seller) | `expiration_date` past | Computed in `getStatusAttribute()` via `expiration_date` meta — no DB flag |

### 4.3 `auction_ended` Column Usage

| Location | What It Sets | Effect |
|---|---|---|
| `LandlordAgentAuctionController::endAuction()` line 520 | `auction_ended = true` on `landlord_agent_auctions` | Landlord listing becomes Expired |
| `LandlordAgentAuctionBidController::checkHireNowTerms()` line 654 | `auction_ended = 1` on the `LandlordAgentAuction` | Marks auction as ended via HireNow terms match |
| `LandlordAuctionController.php` line 569 | `auction_ended = true` | Property auction (out of scope) |
| `PropertyAuctionController.php` line 1432 | `auction_ended = true` | Property auction (out of scope) |

No code sets `auction_ended` on `buyer_agent_auctions` or `seller_agent_auctions` (the column does not exist on those tables).

---

## 5. Timer UI Locations

### 5.1 Detail Page — Full Countdown Timers (Days / Hours / Minutes / Seconds boxes)

All detail pages show a full box countdown **only for Bidding Period** listings. Traditional listings suppress the timer entirely.

| Role | File | Lines | Timer Source |
|---|---|---|---|
| Tenant | `resources/views/hire_tenant_agent/view.blade.php` | ~1440–1465 (primary), ~4129 (bottom) | `auction_time` meta → computed expiration = `created_at` + duration via Carbon diff |
| Buyer | `resources/views/buyerAgentAuctionDetail.blade.php` | ~2221–2240 (primary), ~4130 (bottom) | Same pattern |
| Seller | `resources/views/hire_seller_agent/view.blade.php` | ~2538–2560 (primary), ~5400 (bottom) | Same pattern |
| Landlord | `resources/views/hire_landlord_agent/view.blade.php` | ~2212–2240 (primary), ~4181 (bottom) | Same pattern |
| Property | `resources/views/property_detail.blade.php` | ~440–460 | Out of scope |
| Agent Service | `resources/views/agentServiceAuctionDetail.blade.php` | 247–259 | Out of scope |
| Seller Property | `resources/views/seller_property/view.blade.php` | ~3206–3230 | Out of scope |

**Timer data source (all 4 agent roles — identical pattern):**
```php
// Step 1: Get auction_time and check if not empty
$auctionTime = data_get($auction->get, 'auction_time');
// Step 2: Extract numeric days from auction_time (e.g., "10 Days" -> 10)
// Step 3: Fallback to expiration_date if auction_time is empty
$expirationDate = data_get($auction->get, 'expiration_date');
```

**Traditional listings:** The `@if ($isBiddingPeriodListing)` wrapper suppresses the timer block entirely. A comment is present in all views: `{{-- Traditional listings: No timer displayed --}}`.

### 5.2 Listing / Search Cards — Compact Badge Timers

Shown on listing index cards, gated by `auction_type === 'bidding period'`.

| Role | File | Lines | Timer Source |
|---|---|---|---|
| Tenant | `resources/views/author_inc/tenant_agent_auctions.blade.php` | ~34–47 | `$auction->get->auction_time` (raw string shown in badge); uses `timer.jquery` plugin |
| Buyer | `resources/views/author_inc/buyer_agent_auctions.blade.php` | ~34 | Same pattern |
| Seller | `resources/views/author_inc/seller_agent_auctions.blade.php` | ~34 | Same pattern |
| Landlord | `resources/views/author_inc/landlord_agent_auctions.blade.php` | ~34–45 | Uses `$auction->auction_length` (DB column) rather than `auction_time` meta |

**Note:** Landlord listing card reads `$auction->auction_length` (DB integer column) while the other 3 roles read `$auction->get->auction_time` (meta string). This is an inconsistency.

### 5.3 My-Bids Dashboard Pages — Countdown Badges

| Role | File | Lines | Logic |
|---|---|---|---|
| Tenant | `resources/views/my-bids/tenant-agent.blade.php` | ~31–62 | `$isBiddingPeriod = ($auctionType === 'Bidding Period')` — only shows countdown badge if Bidding Period |
| Buyer | `resources/views/my-bids/buyer-agent.blade.php` | ~20–50 | Same pattern |
| Seller | `resources/views/my-bids/seller-agent.blade.php` | ~21–50 | Same pattern |
| Landlord | `resources/views/my-bids/landlord-agent.blade.php` | ~20–50 | Same pattern |

All 4 My-Bids pages show a `countdown-timer` span with `data-end` attribute only for Bidding Period listings.

### 5.4 Listing Details Page — Displayed Fields (all roles)

In the listing metadata section of each detail view, the following fields are shown regardless of type:

- `expiration_date` — displayed as formatted date for all listing types (informational for Traditional, used as timer source for Bidding Period).
- `auction_type` — displayed as badge/label.
- `auction_time` (Bidding Period Length) — displayed only when `auction_type === 'bidding period'` and `auction_time` is not null.

---

## 6. Background / Scheduled Logic

### 6.1 `Controller::autoTransitionBpToPending()`

**File:** `app/Http/Controllers/Controller.php` lines 18–57

This is the only active expiration mechanism. It is a "just-in-time" check triggered on each listing view, not a background cron.

**Logic:**
1. Skip if listing is sold.
2. Check `auction_type` meta — exit if not Bidding Period.
3. Read `auction_time` meta — exit if empty or null.
4. Parse duration (supports days, hours, weeks, minutes).
5. Compute expiration: `created_at + duration`.
6. If `now() >= expiration` AND `listing_status` is not already `'Pending'` or `'Hired Agent'`: set `listing_status` meta to `'Pending'`.

**Called from:**

| Controller | Method | Line |
|---|---|---|
| `TenantAgentAuctionController` | `view()` | 280 |
| `BuyerAgentAuctionController` | `viewAuctionDetails()` | 400 |
| `SellerAgentAuctionController` | `view()` | 449 |
| `LandlordAgentAuctionController` | `view()` | 508 |

**Key characteristics:**
- No background cron or queue job runs this — it only fires when a user visits the listing detail page.
- It only sets a meta value — it does **not** set `auction_ended` or `is_sold`.
- If a listing's timer expires but no one visits the page, the status remains `'Active'` until next visit.

### 6.2 Scheduled Commands — All Commented Out

**File:** `app/Console/Kernel.php`

All expiration-related schedules are commented out:
```php
// $schedule->command('autoBid')->everyMinute();
// $schedule->command('expirationDate')->everyMinute();
// $schedule->command('autoBid')->everyThirtyMinutes();
// $schedule->command('seller:autocounter')->everyMinute();
// $schedule->command('buyer:autocounter')->everyMinute();
```

The command classes (`AutoBid`, `SellerAutocounter`, `BuyerAutocounter`) exist in `app/Console/Commands/` but are not registered in `$commands` (both registration lines are also commented out).

### 6.3 No Active Jobs or Observers for Expiration

No active Laravel Jobs, Observers, or Event listeners were found that enforce Bidding Period expiration in the background.

---

## 7. Role-by-Role Differences Table

| Feature | Tenant | Buyer | Seller | Landlord |
|---|---|---|---|---|
| `auction_ended` DB column | Yes (always existed) | **No** | **No** | Yes (added 2025-02-01) |
| Model uses `auction_ended` in `getStatusAttribute()` | **Yes** — returns `'Expired'` | No — uses `expiration_date` | No — uses `expiration_date` | No — uses `expiration_date` |
| `isBiddingPeriodType()` model method | **Yes** | **No** | **No** | **No** |
| `isBiddingPeriodActive()` model method | **Yes** | **No** | **No** | **No** |
| `auction_type` written to DB column at creation | No (meta only) | No (meta only) | **Yes** | **Yes** |
| `auction_type` locked after creation | **Yes** (edit component comment-locks) | **No** | **No** | **No** |
| Backend bid submission guard (status check) | No | No | No | **Yes** (lines 37–39) |
| Competing bids feature | **Yes** (Tenant only) | No | No | No |
| Anonymous bidder mapping | **Yes** (Tenant only) | No | No | No |
| `endAuction()` endpoint (manually end) | No | No | No | **Yes** (line 520) |
| HireNow terms auto-acceptance | No | No | No | **Yes** (`checkHireNowTerms()`, sets `auction_ended = 1`) |
| Detail view `$canTakeAction` / `$showActionButtons` logic | Yes | Yes | Yes | Yes |
| Timer UI on detail page (BP only) | Yes | Yes | Yes | Yes |
| Timer UI on listing cards | Yes | Yes | Yes | Yes (reads DB column, not meta) |
| `autoTransitionBpToPending()` called | Yes | Yes | Yes | Yes |

---

## 8. Flagged Inconsistencies

### 8.1 `auction_ended` Column — Tenant vs Landlord vs Buyer/Seller

- **Tenant** model's `getStatusAttribute()` uses `$this->auction_ended` (DB column) to return `'Expired'`.
- **Buyer** and **Seller** models do not have an `auction_ended` column — they fall back to `expiration_date` meta for 'Expired' status.
- **Landlord** has the `auction_ended` column (migration 2025-02-01) but its `getStatusAttribute()` also uses `expiration_date` meta — not `auction_ended`.
- **Result:** The `auction_ended` column on Landlord is set by `endAuction()` and `checkHireNowTerms()` but is **never read by the model's status method**. It is effectively dead data for the Landlord model's status computation.

### 8.2 `isBiddingPeriodType()` / `isBiddingPeriodActive()` Only Defined on Tenant Model

The CompetingBids feature (controller + service) calls `$auction->isBiddingPeriodType()` and `$auction->isBiddingPeriodActive()` — methods that **only exist on `TenantAgentAuction`**. Buyer, Seller, and Landlord models have no equivalent. The blade-level logic for these roles implements the same concept inline in PHP blocks rather than via model methods.

### 8.3 Landlord Listing Card Reads `auction_length` DB Column, Others Read `auction_time` Meta

In `author_inc/landlord_agent_auctions.blade.php`: `round(@$auction->auction_length) <= 0 ? 'No Time Limit' : ...`  
In `author_inc/tenant_agent_auctions.blade.php`: `(!@$auction->get->auction_time) ? 'No Time Limit' : ...`

Landlord uses the DB integer column `auction_length` (which stores a parsed day count), while other roles read the raw `auction_time` meta string. If `auction_length` is `0` or `-1` (non-day units), the Landlord card will display "No Time Limit" even if an `auction_time` meta exists.

### 8.4 `auction_type` Can Be Changed After Creation for Buyer, Seller, Landlord

Unlike Tenant (where it is explicitly locked in the edit component), the other three roles allow `auction_type` to be changed in edit forms. This means a listing could be switched from Bidding Period to Traditional (or vice versa) after bids have been placed — which would invalidate the competing-bid anonymization and action-restriction logic retroactively.

### 8.5 `autoTransitionBpToPending()` Is View-Triggered Only

If a Bidding Period listing expires and no one visits the detail page, the `listing_status` meta will never be updated to `'Pending'`. The listing will appear as `'Active'` in list/search views and dashboards. The bid submission guard in Landlord's controller (which checks `$auction->status`) will work correctly because `getStatusAttribute()` re-evaluates in real time, but the My-Bids countdown timers and search pages show the raw data — they do not rely on this transition.

### 8.6 Buyer Model `getStatusAttribute()` Uses `expiration_date` for All Types

The Buyer (and Seller/Landlord) models return `'Expired'` when `now() >= expiration_date`, regardless of whether the listing is Bidding Period or Traditional. For a Bidding Period listing, the relevant timer is `auction_time` (duration from `created_at`), not `expiration_date`. If `expiration_date` is set to a date that differs from `created_at + auction_time`, the model's computed status will disagree with the blade timer logic.

### 8.7 `auction_type === 'Auction (Timer)'` — Tenant Legacy Value

Tenant's `isBiddingPeriodType()` method accepts both `'Bidding Period'` and `'Auction (Timer)'` as equivalent Bidding Period types (line 47 of `TenantAgentAuction.php`). The `autoTransitionBpToPending()` function also accepts `'auction (timer)'` (line 28 of `Controller.php`). The other three roles' blade logic only checks for `'bidding period'` (lowercase via `strtolower()`). Any Buyer/Seller/Landlord listing with `auction_type = 'Auction (Timer)'` would display `$isTraditionalListing = true` in the blade and be treated as Traditional — a silent inconsistency.

---

## 9. Soft-Deadline-Only Conversion Recommendation

### Goal

Remove all hard enforcement tied to the bidding period timer. The `expiration_date` and `auction_time` fields remain stored and displayed as informational context only. Listing owners can accept, reject, or counter any bid at any time — the timer is advisory only.

### What "Soft Deadline Only" Means

- `expiration_date` is displayed on the listing (as it is today for Traditional listings).
- The countdown timer, if shown at all, is purely cosmetic — it does not gate any action.
- `auction_type` distinction is retained in storage and display, but its enforcement is removed.
- Competing-bids anonymization can be retained or removed independently.

---

### 9.1 Changes Required — Blade Views (4 files)

**Files:** `hire_tenant_agent/view.blade.php`, `buyerAgentAuctionDetail.blade.php`, `hire_seller_agent/view.blade.php`, `hire_landlord_agent/view.blade.php`

**What to change:**

1. **Remove the `$canTakeAction` / `$showActionButtons` guard.**  
   Currently: `$showActionButtons = ($isTraditionalListing && !$isExpired) || ($isBiddingPeriodListing && $isExpired);`  
   Change to: `$showActionButtons = !$isExpired && !$isSold;` (always show action buttons as long as listing is not expired or sold).

2. **Remove the "Bidding Period Active" locked message block.**  
   The `@elseif ($isBiddingPeriodListing && !$canTakeAction)` branch showing "Timer still active — accept/reject locked" can be removed entirely.

3. **Suppress or restyle the full countdown timer boxes to informational-only.**  
   Options:
   - Remove the timer block entirely.
   - Keep it but label it "Suggested Deadline" or "Advisory Deadline" with no locking consequence.

4. **Remove or restyle the "Bidding Period Ended" status banner.**  
   Currently shown when `$isBiddingPeriodListing && $isExpired` — can be removed or softened to "Advisory Period Ended — Bids Still Open."

5. **Simplify the "View Bid" button logic for listing owners.**  
   Currently, during an active BP timer, the listing owner's "View Bid" button is disabled. Remove this disable: `@if ($isBiddingPeriodListing && $isBiddingTimerActive && $isListingOwner && !$isBidOwner)` — the owner should be able to view bids at any time.

6. **My-Bids countdown badges** (`my-bids/tenant-agent.blade.php`, `buyer-agent.blade.php`, `seller-agent.blade.php`, `landlord-agent.blade.php`): Keep or remove the countdown badge display — it carries no enforcement consequence and is already informational. No functional change required if kept as-is.

### 9.2 Changes Required — Controller Logic

**File:** `app/Http/Controllers/CompetingBidsController.php` (lines 24–29)

Remove the `isBiddingPeriodActive()` gate:
```php
// REMOVE: if (!$auction->isBiddingPeriodActive()) { ... }
```
If competing bids are being retained, the gate should only check that the agent has submitted a bid — not that the timer is active.

**File:** `app/Http/Controllers/LandlordAgentAuctionBidController.php` (lines 37–39)

This guard checks `$_listingGuard->status` (which includes `'Expired'` and `'Pending'`). With a soft deadline, a listing in `'Pending'` state (meaning timer elapsed) should still accept bids. Change to only block if `is_sold = true` or `listing_status = 'Hired Agent'`:
```php
// CHANGE: only block if actually hired
if (in_array($_listingGuard->status, ['Hired Agent'])) { ... }
```

### 9.3 Changes Required — Controller Background Logic

**File:** `app/Http/Controllers/Controller.php` — `autoTransitionBpToPending()`

With a soft deadline, transitioning to `'Pending'` on timer expiry is no longer appropriate since it blocks action buttons. Options:
- **Remove the function entirely** from all 4 controller `show()` methods.
- **Or change it** to only write an informational meta (e.g., `listing_soft_deadline_passed = true`) that affects display only, not action gating.

If removed, also remove the 4 calls:
- `TenantAgentAuctionController::view()` line 280
- `BuyerAgentAuctionController::viewAuctionDetails()` line 400
- `SellerAgentAuctionController::view()` line 449
- `LandlordAgentAuctionController::view()` line 508

### 9.4 Changes Required — Model Methods (Tenant only)

**File:** `app/Models/TenantAgentAuction.php`

`isBiddingPeriodActive()` gates `CompetingBidsService`. With a soft deadline, it becomes a no-op (always returns `false` since timer is no longer enforced). Options:
- Make it always return `true` (if competing bids are retained).
- Remove it along with `isBiddingPeriodType()` if competing bids are also removed.

`getStatusAttribute()` currently returns `'Expired'` when `auction_ended = true`. With a soft deadline, `'Expired'` should no longer block actions — remove this branch or keep only for read-only display.

### 9.5 Changes Required — `auction_type` Lock (Tenant Edit)

**File:** `app/Http/Livewire/TenantAgentAuctionEdit.php` lines 3071–3081

The lock is currently protecting internal consistency. With soft deadlines, the distinction between Bidding Period and Traditional becomes less critical. However, unlocking `auction_type` in Tenant edit (to match Buyer/Seller/Landlord behavior) is a separate product decision — not strictly required for soft-deadline conversion.

### 9.6 What to Keep (No Change Needed)

| Item | Reason to Keep |
|---|---|
| `expiration_date` stored and displayed | Informational — the "soft deadline" itself |
| `auction_time` stored and displayed | Useful for agents to understand the listing owner's intended window |
| `auction_type` field stored | May still drive UI display differences (e.g., competing-bids section) |
| `listing_status` meta and `is_sold` flag | Core hired/sold tracking — unaffected |
| `auction_ended` DB column | Can be retained for admin-triggered manual ending (keep `endAuction()` endpoint for Landlord admin use) |
| Competing bids anonymization (optional) | If retained, the submit-to-view gate can stay; only the `isBiddingPeriodActive()` timer check inside it should be removed |
| Notifications system | Out of scope per task specification |
| Property auction listings | Out of scope per task specification |

### 9.7 Enforcement Points Checklist

Below is a complete list of every enforcement point to relax, in order of priority:

| # | Location | File | Change |
|---|---|---|---|
| 1 | Blade: `$showActionButtons` / `$canTakeAction` guard | All 4 role `view.blade.php` files | Remove BP timer condition; always allow actions when listing is active |
| 2 | Blade: "Bidding Period Active — Actions Locked" message | All 4 role `view.blade.php` files | Remove or replace with informational banner |
| 3 | Blade: Owner's "View Bid" disabled during BP timer | All 4 role `view.blade.php` files | Remove disable condition |
| 4 | `Controller::autoTransitionBpToPending()` | `app/Http/Controllers/Controller.php` | Remove function or change to non-blocking display-only meta |
| 5 | Calls to `autoTransitionBpToPending()` | All 4 role controllers (`show()` / `view()` methods) | Remove all 4 call sites |
| 6 | `CompetingBidsController` — `isBiddingPeriodActive()` gate | `CompetingBidsController.php` lines 28–30 | Remove timer check; retain submit-to-view check |
| 7 | `CompetingBidsService::canViewCompetingBids()` — `isBiddingPeriodActive()` gate | `CompetingBidsService.php` line 25 | Remove timer check |
| 8 | `LandlordAgentAuctionBidController::save_bid()` — status guard blocks `'Pending'` | `LandlordAgentAuctionBidController.php` lines 37–39 | Change to only block `'Hired Agent'` status |
| 9 | `TenantAgentAuction::isBiddingPeriodActive()` | `app/Models/TenantAgentAuction.php` | Convert to no-op or remove |
| 10 | Blade: Countdown timer boxes (action-locking context) | All 4 role `view.blade.php` files | Restyle as advisory / remove |
| 11 | Blade: "Bidding Period Ended" banner | All 4 role `view.blade.php` files | Remove or replace with advisory message |

---

*End of audit. No code was changed in producing this report.*
