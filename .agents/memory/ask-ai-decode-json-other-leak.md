---
name: Ask AI decodeJsonField Other-leak fix
description: decodeJsonField strips literal "Other" from JSON arrays; rental_budget has no keyword map entry so source-grep is its correct test layer.
---

## The Fix

`AskAiContextBuilderService::decodeJsonField()` now strips any element whose trimmed, lowercased value equals `"other"` from decoded JSON arrays before joining with `, `.

```php
$items = array_filter(
    array_map('strval', $decoded),
    static fn ($v) => $v !== '' && strtolower(trim($v)) !== 'other'
);
```

Arrays that reduce to empty (e.g. `["Other"]`) return `null` rather than the literal string `"Other"`.

## Why

Multi-select form fields use "Other" as a UI sentinel that signals the user typed a custom value in a companion `other_*` EAV key. For single-select fields, `resolveOtherValue()` handles the sentinel+companion pattern. For multi-select arrays there is no companion key — "Other" is simply a stray token. If it passes through to the AI prompt it appears as a literal answer element, degrading response quality.

Affected meta keys (as of June 2026): `pool_type`, `appliances`, `property_items`,
`pet_species_allowed`, `view_preference`, `financing_type`, `pet_species`, `desired_lease_length`.

## How to apply

- When adding a new JSON-multiselect meta key, no extra work needed — `decodeJsonField()` filters automatically.
- When writing tests for `decodeJsonField`, assert that `["SomeValue","Other"]` produces `"SomeValue"` and that `["Other"]` produces `null`.
- Regression tests live in `tests/Unit/Services/AskAi/AskAiLiveUiRegressionTest.php` (R9 family).
