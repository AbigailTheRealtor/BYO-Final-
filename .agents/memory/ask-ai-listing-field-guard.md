---
name: Ask AI listing field guard pattern
description: Guard B for listing.* null fields + OpenAI json_object constraint fix; how listing field data flows end-to-end.
---

## OpenAI json_object constraint

`response_format: json_object` requires the literal word "json" in at least one message. The fix: SYSTEM_INSTRUCTIONS[11] ends with "Respond using a JSON object." — keeps count at 12, keeps "decisions" present, satisfies OpenAI.

**Why:** Without this, every listing_facts call fails with HTTP 0: "'messages' must contain the word 'json' in some form." No API key or network issue — pure constraint violation.

**How to apply:** If SYSTEM_INSTRUCTIONS ever gets refactored, ensure at least one entry contains the word "json" (case-insensitive match by OpenAI).

## Guard B — listing.* null field guard

`AskAiRunnerV2Service` has two missing-data guards before the adapter call:
- **Guard A** (`faq_answers.*`): fires when `allowed_context` is empty (FAQ answer absent from DB)
- **Guard B** (`listing.*`): fires when `detectListingFieldKey()` resolves a path AND that field's value is `null` in `allowed_context['listing']`

Guard B is triggered by `LISTING_KEY_KEYWORD_MAP` (bedrooms, annual_property_taxes keywords). When it fires, returns `insufficient_context` with a field-specific label from `deriveFieldLabel()`.

**Why:** `filterAllowedContext` includes null values for listing.* paths (unlike faq_answers.* absent keys). Guard A alone was insufficient.

## infoGet empty string caveat

`infoGet` returns `''` (empty string) for EAV meta rows that exist but have empty `meta_value`. Guard B checks `=== null`, so it does NOT fire for `''`. Empty-string values pass through to OpenAI as empty context.

**How to apply:** If a listing has an EAV meta row with empty string, OpenAI sees the field as `''` and says "not provided." This is acceptable behavior but could be improved by normalizing `''` to `null` in `infoGet`.

## Seller bedrooms data source

`bedroom_id` native column and the `bedrooms` lookup table are both empty in the dev DB. Actual bedroom data lives in EAV meta `meta_key='bedrooms'` as a text string (e.g. `'3'`). The context builder reads `$infoGet('bedrooms') ?? $nativeGet('bedroom_id')` — infoGet wins when meta exists.

The UI reads `$str('bedrooms')` — same EAV meta. No mismatch.

## Dev DB test listings with real data

- **Listing 20** (seller): `bedrooms='3'`, `annual_property_taxes='5300'` — use for happy-path pipeline verification.
- No listings in dev DB have `faq_answers` with `roof_age_and_condition` filled in.
