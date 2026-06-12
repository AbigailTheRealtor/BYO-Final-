---
name: Ask AI Phase 4 — answer key dual-form lookup
description: ask_ai_answers canonical_key may be stored as bare key or full-path; always try both; facts always use bare key.
---

## Rule

When looking up a stored answer for a `faq_answers.*` canonical key (e.g. `faq_answers.roof_age_and_condition`):
- The `ask_ai_answers` table may have `canonical_key = 'roof_age_and_condition'` (bare, no prefix)
- OR `canonical_key = 'faq_answers.roof_age_and_condition'` (full path)
- **Always query for both forms** using `WHERE canonical_key IN (bareKey, fullPathKey)`.

When looking up a listing fact for a `listing.*` canonical key:
- `ask_ai_facts.canonical_key` is ALWAYS the bare key (e.g. `bedrooms`, not `listing.bedrooms`).
- Strip the `listing.` prefix before querying.

`ask_ai_questions.canonical_key` uses the full path for both types:
- FAQ questions: `faq_answers.roof_age_and_condition`
- Listing-model questions: `listing.bedrooms`

**Why:** The context builder stores FAQ answers with the raw question key from the `listing_ai_faq` JSON (no prefix), while the registry and question table use the full prefixed path. Both storage patterns exist in production data.

**How to apply:** In `AskAiKnowledgeSearchService::lookupFaqAnswer()`, always query:
```php
->where(fn($q) => $q->where('canonical_key', $bareKey)->orWhere('canonical_key', $fullPathKey))
```
