---
name: Bidding Period countdown source
description: Platform source of truth for countdown timers on Bidding Period offer listings.
---

The canonical source for Bidding Period countdown timers is `expiration_date` (stored in the listing's meta table).

**Why:** The Tenant form stores only `expiration_date` — `auction_time` (duration string like "5 Days") was never wired to save for Tenant/Landlord listings. Using the absolute date is also more precise than computing `created_at + N days`.

**How to apply:**
- Check `auction_type === 'bidding period'`
- Primary: use `expiration_date` → `Carbon::parse($expDate)->endOfDay()`
- Fallback (backward compat): if `expiration_date` is empty, use `created_at + auction_time days`
- If both are empty → no timer
- This logic is identical across all search cards (tenant, landlord, seller, buyer) and the tenant public view.

Seller/buyer listings from before this task have both fields set consistently; `expiration_date` takes priority.
