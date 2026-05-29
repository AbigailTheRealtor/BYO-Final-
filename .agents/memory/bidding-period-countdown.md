---
name: Bidding Period countdown source
description: Platform source of truth for countdown timers on Bidding Period offer listings.
---

The canonical source for Bidding Period countdown timers is `created_at + auction_time` exclusively.

**Why:** The task explicitly requires removing `expiration_date` as the primary source. `auction_time` is a duration string like "5 Days", "2 Weeks", "3 Hours", "30 Minutes" — always stored in meta. `created_at` is the listing's start timestamp. Together they define the end of the bidding window without relying on an optional `expiration_date` field.

**How to apply:**
- Check `auction_type === 'bidding period'`
- Parse `auction_time` by splitting on space: `[$val, $unit]`
- Compute end: `Carbon::parse(created_at)->add{Unit}($val)`
- Supported units: hours/hour → addHours; weeks/week → addWeeks; minutes/minute → addMinutes; default → addDays
- If `auction_time` is empty or `$val === 0` → no timer
- This logic is identical across all 6 bidding period views: buyer/seller/landlord/tenant search cards, landlord view, tenant view, seller view, buyer view

**Files using this pattern:**
- `resources/views/offer-listing/*/search.blade.php` (4 search files, PHP block)
- `resources/views/offer-listing/landlord/view.blade.php` (PHP block, JS timer via lol-bp-timer)
- `resources/views/offer-listing/tenant/view.blade.php` (PHP block, JS timer via tcl-bp-timer)
- `resources/views/offer-listing/seller/view.blade.php` (PHP block, JS timer via sol-bp-timer)
- `resources/views/offer-listing/buyer/view.blade.php` (PHP block, JS timer via bol-bp-timer)

**`buyerAgentAuctionDetail.blade.php`** and **`seller_property/view.blade.php`** are separate — the buyer agent detail already correctly uses `auction_time + created_at` for bidding period; seller_property uses `auction_length` column for "Auction Listing" type (unrelated).
