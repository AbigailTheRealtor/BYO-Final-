---
name: Ask AI classifier KEYWORD_RULES section placement
description: Section order in KEYWORD_RULES constant; listing_facts occupies lines 141-1267, property_standout starts at 1285; keywords for area/neighborhood must be placed in property_standout block to get location_intelligence context
---

## Rule
Keywords added to `KEYWORD_RULES` must be placed inside the correct section block in the file. The sections appear in this strict evaluation order:

1. `prohibited` (line ~40)
2. `agent_profile` (added, evaluates before listing_facts)
3. `listing_facts` (lines 141–1267 — very large block)
4. `compatibility_signals`
5. `property_standout` (line 1285+)
6. `suited_audience`
7. `buyer_tenant_match`
8. `missing_data`
9. `marketing_angles`
10. `educational`

## Why
`str_contains` matching is evaluated top-to-bottom across all sections. If a keyword appears in `listing_facts`, it wins over any matching keyword in `property_standout` even if property_standout was intended. Neighborhood/area keywords ("what is the neighborhood like", "about the area", etc.) must be in `property_standout` so that `location_intelligence` context is included in the prompt package — if they land in `listing_facts`, only `listing` + `faq_answers` are provided.

## How to apply
When adding keywords to `property_standout`, verify their line numbers are above 1285. Use `grep -n "'property_standout' =>"` to confirm. Any keyword accidentally placed inside the listing_facts block (lines 141-1267) will route to listing_facts instead, silently ignoring the property_standout intent.
