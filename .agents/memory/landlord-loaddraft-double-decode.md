---
name: Landlord loadDraft double-decode bug
description: LandlordAgentAuction::getGetAttribute() auto-decodes JSON meta values to arrays; loadDraft() then calls json_decode() on those arrays, throwing TypeError in PHP 8. Seller is not affected. RESOLVED — all array fields now use ensureArray() or is_string() guards.
---

## Rule

When writing or modifying `loadDraft()` for Landlord, never call `json_decode()` on a value retrieved from `$auction->get->field` — the accessor already decoded it.

**Why:** `LandlordAgentAuction::getGetAttribute()` returns PHP arrays (not strings) for any valid-JSON meta value. PHP 8 `json_decode()` requires a string first argument; passing an array throws `\TypeError` unconditionally, crashing `loadDraft()` before any field is restored.

**How to apply:** Replace `json_decode($auction->get->field ?? '[]', true)` with `$auction->get->field ?? []` throughout `LandlordOfferListing::loadDraft()`. `ensureArray()` handles both string and array inputs safely.

**Current status (RESOLVED):** All `->get->` array fields now use `ensureArray()` directly (lines 2289, 2374, 2382–2384, 2691–2699). Remaining `json_decode()` calls in loadDraft use `$auction->info()` (EAV string, not auto-decoded) or have proper `is_string()` guards. 11 Landlord roundtrip tests confirm save→reload works end-to-end.

**Safe `is_string()` pattern (for fields that need explicit decode):**
```php
$this->field = is_string($auction->get->field)
    ? json_decode($auction->get->field, true) ?? []
    : (array)($auction->get->field ?? []);
```
