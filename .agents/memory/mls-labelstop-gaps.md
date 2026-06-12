---
name: MLS labelStop gap patterns
description: Common field-bleed root causes from missing or incomplete $labelStop entries in MlsListingImportService; confirmed via live tinker testing in Phase 2 audit.
---

## Rule

When adding or auditing MLS parser fields, check all of these gap patterns before assuming $labelStop is complete:

1. **Multi-word label variants with qualifiers** — `HOA\b(?=\s*:)` catches `HOA:` but NOT `HOA Dues:` or `HOA Fee:`. Add `HOA\s+(?:Dues?|Fee)\b(?=\s*:)` BEFORE the bare `HOA\b` form (more-specific first).

2. **Y/N suffix variants** — `Special\s+Assessment\b` catches "Special Assessment:" but NOT "Special Assessment Y/N:". The boundary check requires `\s*:` immediately after the word boundary; "Y/N" between the word and colon breaks the match. Fix: `Special\s+Assessment(?:\s+Y\/N)?\b`.

3. **Common label words absent entirely** — confirmed missing entries that caused bleed: `School\s+District\b`, `Neighborhood\b(?=\s*:)`, `Monthly\s+Fee\b`. These should be considered part of the standard stop list for any MLS export.

4. **`boundary=false` on long-capture parsers** — any parser with a `{1,200}` or similar long capture that does NOT pass `boundary=true` will bleed across the entire line in single-line MLS exports. `rent_includes` was the confirmed offender (fixed Phase 2). Audit all parsers with capture length > 60 chars for missing `boundary=true`.

## How to apply

Before closing any MLS bleed bug:
- Tinker-test the exact text string that failed (`import('', $rawText)`)
- Check the captured value for labelStop coverage gaps
- If a Y/N variant exists in Stellar MLS exports, add the `(?:\s+Y\/N)?` optional group
- Verify both the bleed-free case AND that existing correct parses still work (regression)

## Why

Live browser testing (Phase 2 audit, June 2026) caught 6 bleed bugs not covered by fixture-based tests. The fixture HTML files don't expose single-line, no-separator MLS export formats, which is the most common copy-paste input from agents.
