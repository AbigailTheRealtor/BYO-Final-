---
name: MLS seller price maps to maximum_budget
description: MLS list price must map to maximum_budget (visible "Desired Sale Price"), not purchase_price (hidden Seller Financing sub-field) — both properties exist, so property_exists() passes silently on the wrong one.
---

# MLS Price Mapping Policy

## The Rule
MLS `price` canonical key must map to `maximum_budget` for Seller and Buyer roles —
**not** `purchase_price`, which belongs to the hidden Seller Financing sub-section.

**Why:** `purchase_price` exists as a Livewire property (so `property_exists()` passes),
but it only appears when the user selects "Seller Financing" as payment type. Mapping
price to it produces a silent no-op — the imported value goes nowhere visible. Discovered
via Playwright live UI testing where the Desired Sale Price field remained empty post-import.

## How to Apply (by role)
- Seller: `price → maximum_budget`
- Buyer: `price → maximum_budget` (buyer budget cap)
- Landlord: `price → desired_rental_amount`
- Tenant: omit price (semantically wrong direction — tenant is searching, not listing)

## Coverage Reporter Note
Multi-select fields (appliances, AC, heating) use the JSON Bridge Pattern (Blade `selected`
attributes, not `wire:model`). The reporter shows `Form Field Exists=N` for these — known
false negative; the fields work correctly. Do not add them to the field map as `wire:model`
properties.
