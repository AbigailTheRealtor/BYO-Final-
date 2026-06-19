---
name: ldna validation flash — keyup on ldna-* inputs
description: Why typing in the Location DNA cities/ZIP input triggers the validation banner, and the fix pattern.
---

## The Rule
In `initializeFullService()`, change the `querySelectorAll` selector from:
```javascript
document.querySelectorAll('input, select, textarea')
```
to:
```javascript
document.querySelectorAll(
  'input:not([id^="ldna-"]), select:not([id^="ldna-"]), textarea:not([id^="ldna-"])'
)
```

## Why
`initializeFullService()` is called on every Livewire `message.processed` event (not just once). Each call attaches NEW `keyup` and `change` listeners to ALL inputs — including `ldna-cities-input`, `ldna-zips-input`, etc. Because it's called repeatedly, these inputs accumulate multiple listeners. Each keystroke fires multiple `checkFormValidity()` calls → the counties-count check fails (0 counties selected) → the validation banner shows → this is the "flashing" the user sees while typing location preference fields.

## Files Fixed (as of this session)
- `offer-buyer-listing.blade.php` — 2 occurrences (replace_all: true)
- `offer-buyer-listing-edit.blade.php` — 2 occurrences (replace_all: true)
- `offer-tenant-listing.blade.php` — 1 occurrence

## Tenant-Edit Is Different
`offer-tenant-listing-edit.blade.php` uses `updateSaveButton` (not `checkFormValidity`) on `input/change` — the keyup listener pattern is not present there; no fix needed.

## How to Apply
Whenever any buyer or tenant form blade adds `keyup`/`change` listeners to a broad input selector, always exclude `[id^="ldna-"]` elements. The ldna-* inputs are UI-only map helpers, not form fields that should trigger submit-readiness validation.
