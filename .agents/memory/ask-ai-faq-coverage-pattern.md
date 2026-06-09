---
name: Ask AI FAQ coverage pattern
description: How FAQ_KEY_KEYWORD_MAP, deriveFieldLabel, and AskAiFieldQuestionRegistryService relate; key mismatch gotchas.
---

## Rule
`FAQ_KEY_KEYWORD_MAP` keys must use the exact raw config file key (e.g. `recent_renovations_list`,
not `recent_renovations`). `buildFaqAnswers()` stores answers keyed by the raw config key, so
the canonical path `faq_answers.<config_key>` must match exactly.

**Why:** The original deriveFieldLabel used `faq_answers.recent_renovations` but the seller
config key is `recent_renovations_list`. The normalized_field_key in internalRunner must
match the actual key stored in the faq_answers context block.

## How to apply
- When adding to FAQ_KEY_KEYWORD_MAP: grep the relevant config file to get the exact key spelling.
- Add the same key to deriveFieldLabel to avoid the generic "Listing field" fallback.
- The AskAiFieldQuestionRegistryService is the source of truth for all FAQ key ↔ canonical path mappings.
- AskAiCoverageHarnessTest asserts structural integrity; run it when modifying FAQ routing.

## Coverage state (post-audit)
- FAQ_KEY_KEYWORD_MAP: ~64 entries covering seller (31+) and landlord (23+) base keys
- Buyer/Tenant FAQ: accessible via faq_answers umbrella only; no pinned keyword routes (by design)
- Addon keys (commercial, business, land): still no keyword routes — separate pass needed
