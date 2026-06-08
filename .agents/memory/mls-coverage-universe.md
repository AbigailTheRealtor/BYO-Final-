---
name: MLS coverage report universe rule
description: Why MlsCoverageReporter must use fieldInventory() as universe, not parsedKeys ∪ fieldMapKeys.
---

## Rule
`MlsCoverageReporter::buildRows()` must iterate `fieldInventory()` — a complete, flat (form × field) array covering all 7 Stellar MLS forms — as its universe.

Never derive the universe from `parsedKeys ∪ fieldMapKeys`.

## Why
`parsedKeys ∪ fieldMapKeys` only contains fields the app already knows about. Commercial Sale, Commercial Lease, Income, Vacant Land, and Business Opportunity each have form-specific fields (NOI, cap rate, building size, bays, lease rate type, business type, etc.) that have no parser branch and no app property. If the universe is derived from parser output, those fields are invisible in the report — the exact gap the audit exists to expose.

## How to apply
- `fieldInventory()` returns one entry per `(form × field)` pair.
- Entries with `canonical_key = null` mean "MLS field present on form; no parser branch; no app target."
- `buildRows()` emits a row for every inventory entry. Null-key entries get `—` for canonical key and `N` for Safe To Import.
- The test asserts that known commercial-only labels (Building Size, NOI, Cap Rate, Lease Rate Type, Business Type, etc.) appear in the report output, and that `| N |` count exceeds 10.
