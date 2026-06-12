---
name: MLS parser boundary-stop over-firing
description: The $labelStop word-boundary pattern in the MLS parser fires on sub-words inside valid field values, silently truncating captured text. Fix strategies and known edge cases.
---

## Rule

Before adding any word to `$labelStop` in `MlsListingImportService::parseFields()`, verify it does not appear as a sub-word inside common field values.

The boundary-stop closure requires `\s*:` AFTER the matched label word:
```
'/^(.*?)(?:\s*(?:' . $labelStop . ')\s*:)/is'
```
This is the primary guard — the outer `\s*:` means the stop only fires when the label word is actually followed by a colon (i.e., it's a real field label, not part of a value).

**Why the outer colon-guard is NOT always sufficient:**

1. **Multi-word labels with qualifiers**: `Lot Size Acres:` — `Lot\s+Size` matches "Lot Size" but the outer `\s*:` needs `:` right after "Size". Fix: use `Lot\s+Size(?:\s+\w+)?` to optionally consume the qualifier.
2. **Y/N infix labels**: `CDD Y/N: No`, `Fireplace Y/N: No` — `CDD\b` needs `:` right after "CDD" but "CDD Y/N:" has "Y/N:" between. Fix: `CDD(?:\s+Y\/N)?\b`, `Fireplace(?:\s+Y\/N)?\b`.
3. **Section headers without colons**: "Rooms", "Exterior Information" appear as bare section headers in MLS exports with NO colon. The boundary-stop can never fire for these. Fix: post-extraction cleanup with `preg_replace('/\s*(?:Rooms|Exterior Information|...)\b.*$/i', '', $v)`.
4. **Greedy capture absorbs the label fragment**: `[A-Za-z0-9\-\.]+` will capture "1410Tax" because letters are in the class. The boundary stop gets `$val = "1410Tax"` (no colon present) and can never trim it. Fix: use non-greedy capture with lookahead `([A-Za-z0-9\-\.]+?)(?=[A-Z][a-z]|\s|$)` so Title Case words stop the capture before the boundary check runs.
5. **Removed standalone `Tax\b`**: caused parcel IDs like "19-30-17-45612-000-1410Tax" to not be trimmable. `Tax\s+(?:ID|Year)` (already present) handles the real label forms.
6. **Boolean-only fields**: `additional_parcels`, `flood_insurance_required` — tight capture `([Yy]es|[Nn]o|[YyNn])\b` completely avoids bleed without needing boundary=true.

**How to apply:**
- Run `php artisan audit:mls-import` — reports 0 Parser FAILs when all bleed is resolved.
- Run `php artisan test tests/Feature/ListingImport/` after parser changes; 253 tests must pass.
- For new boolean fields: always use tight char-class capture, never `[^\|\n]{1,N}` + boundary=true.
- For multi-word label qualifiers (e.g. "Lot Size Acres:"): use `(?:\s+\w+)?` inside $labelStop.
- For section headers without colons: add post-extraction `preg_replace` cleanup on the affected field.
