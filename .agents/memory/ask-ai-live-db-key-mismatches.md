---
name: Ask AI live DB key mismatches
description: Six extractFactualFields() reads used wrong EAV meta keys and always returned null; discovered by querying real listing IDs against production meta tables.
---

# Ask AI Live DB Key Mismatches

**Why:** PHPUnit stubs let any key return a value, so wrong-key bugs are invisible in unit tests. Only a direct SQL query against real listing IDs reveals "form saves key X, code reads key Y" mismatches.

**How to apply:** When adding or auditing a new field extraction in `extractFactualFields()`, run `SELECT meta_key, meta_value FROM <role>_agent_auction_metas WHERE <role>_agent_auction_id = <real_id>` against a live listing to confirm the actual stored key before writing the code.

## The Six Confirmed Mismatches (June 2026)

| Context field | Wrong key read | Actual DB key | Listing IDs verified |
|---|---|---|---|
| `water_view` (all roles) | `'view'` or `'water_view'` | `view_preference` (JSON multiselect) | 87, 121, 97, 71 |
| `rent_amount` (landlord) | `'maximum_budget'` | `desired_rental_amount` → `starting_rent` → `lease_now_price` | 71 |
| `utilities` (landlord) | `'utilities'` | `property_utilities` (JSON), fallback `utilities` | 71 |
| `lease_length` (landlord) | `min_lease_period` without resolveOtherValue | `min_lease_period` with `min_lease_period_other` sibling + `desired_lease_length` fallback | 71 |
| `max_rent` (tenant) | `'maximum_budget'` | `budget`, fallback `maximum_budget` | 170 |
| `desired_lease_length` (tenant) | `'tenant_desired_lease_length'` | `desired_lease_length` (JSON) → `lease_for` (JSON) | 170 |

## Key Facts
- `view_preference` is the universal scenic/water-view key across **all four roles** — `view` and `water_view` never appear as stored meta keys.
- Landlord rent fields are role-specific (`desired_rental_amount`, not the buyer/seller `maximum_budget`).
- `infoGet()` returns `''` (empty string) not `null` for absent EAV metas, so `?? fallback` works correctly only when the primary value is `null` — use explicit truthiness checks (`?: fallback`) when cascading through empty-string fields.
- 12 Case W regression tests added to `AskAiContextBuilderServiceTest` covering all six mismatches and their fallback cascades.
