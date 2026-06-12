---
name: Ask AI duplicate LISTING_KEY_KEYWORD_MAP key risk
description: PHP silently uses last-key-wins for duplicate const array keys; role-segmented listing.* map blocks drop the first entry's phrases at runtime.
---

# Ask AI duplicate LISTING_KEY_KEYWORD_MAP key risk

## The rule
`LISTING_KEY_KEYWORD_MAP` and `FAQ_KEY_KEYWORD_MAP` in `AskAiRunnerV2Service.php` are PHP `const` arrays. PHP does **not** error on duplicate keys — it silently uses the last definition and the first definition's phrases are unreachable at runtime.

The map is split into role-context sections (Residential, Vacant Land, Commercial, etc.), making it easy to accidentally define `'listing.zoning'` in both the VL section and the General section. A full audit found 7 such cases (zoning, waterfront, water_access, lot_dimensions, flood_insurance_required, has_special_assessments, ceiling_height) — all merged. `deriveFieldLabel` had 2 duplicate entries (has_special_assessments, ceiling_height) — also removed.

## Why it is dangerous
`detectListingFieldKey()` iterates the map at runtime. A phrase that only existed in the first (overwritten) entry will never match. The symptom is: phrase is visible in the source file but `detectListingFieldKey($question)` returns `null`. Confusingly, the key *appears* in the map when you read the file.

## How to apply
- Before adding a new entry, grep the full const for the key name to ensure it is not already present.
- `AskAiMapKeyUniquenessTest` (4 cases) is the regression guard: it uses bracket-depth source scanning to check LISTING_KEY_KEYWORD_MAP, FAQ_KEY_KEYWORD_MAP, and deriveFieldLabel for source-level duplicate keys. Case D compares runtime key count vs source unique count to catch any PHP collapse.
- When merging two partial entries, combine all phrase arrays into one canonical entry and delete the duplicate. Keep the canonical entry in the most general section (not role-specific) so it is not accidentally duplicated again.
