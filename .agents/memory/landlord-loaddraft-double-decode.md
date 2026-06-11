---
name: Landlord loadDraft double-decode bug
description: LandlordAgentAuction::getGetAttribute() auto-decodes JSON meta values to arrays; loadDraft() then calls json_decode() on those arrays, throwing TypeError in PHP 8. Seller is not affected.
---

## Rule

When writing or modifying `loadDraft()` for Landlord, never call `json_decode()` on a value retrieved from `$auction->get->field` — the accessor already decoded it.

**Why:** `LandlordAgentAuction::getGetAttribute()` returns PHP arrays (not strings) for any valid-JSON meta value. PHP 8 `json_decode()` requires a string first argument; passing an array throws `\TypeError` unconditionally, crashing `loadDraft()` before any field is restored.

**How to apply:** Replace `json_decode($auction->get->field ?? '[]', true)` with `$auction->get->field ?? []` throughout `LandlordOfferListing::loadDraft()`. `ensureArray()` handles both string and array inputs safely.
