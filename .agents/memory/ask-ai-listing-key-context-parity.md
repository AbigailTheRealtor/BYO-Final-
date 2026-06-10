---
name: Ask AI LISTING_KEY_KEYWORD_MAP context key parity rule
description: Every LISTING_KEY_KEYWORD_MAP entry must have a matching ctx['listing'][key]; describes the tenant budget key name mismatch and runner result trace shape.
---

## Rule
Every key in `LISTING_KEY_KEYWORD_MAP` of the form `listing.X` must have a corresponding field
`ctx['listing']['X']` populated by `AskAiContextBuilderService::buildForListing()`.  
If the context builder uses a different field name, Guard B (or the contract service's
allowed_context extraction) will see null, and OpenAI will receive empty context —
causing it to hallucinate "not available" answers even when the DB has data.

## Tenant budget key
The tenant context builder stores the maximum rent under `ctx['listing']['max_rent']`
(cascade: `infoGet('budget') ?? infoGet('maximum_budget')`).
`listing.rental_budget` was a broken LISTING_KEY_KEYWORD_MAP entry because no
`ctx['listing']['rental_budget']` key exists.  It was removed; its keywords were merged
into `listing.max_rent`.

**Why:** Discovered by running live questions against tenant listing 170 — "What is the
tenant's rental budget?" returned OpenAI error "not available" even though DB had `budget = 5,000`.
Trace showed `allowed_context = {}` (empty).

**How to apply:** Before adding any new `listing.X` entry to LISTING_KEY_KEYWORD_MAP, grep
`AskAiContextBuilderService.php` for `'X'` to confirm the context builder actually outputs
that key for the relevant role(s).

## Runner result trace shape
The runner result array contains a `trace` sub-array. The canonical field key is at:
- `$result['trace']['listing_key_detected']` — for listing.* routes
- `$result['trace']['faq_key_detected']` — for faq_answers.* routes
- `$result['trace']['normalized_field_key']` — only set if the normalizer/router ran
- `$result['final_response']['answer']` — the actual OpenAI answer text (NOT `$result['answer']`)

**Why:** Scripts that read `$result['answer']` or `$result['trace']['normalized_field_key']`
directly will always get empty/null; this was confirmed in the June 2026 live trace session.
