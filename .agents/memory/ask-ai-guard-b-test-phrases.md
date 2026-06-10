---
name: Ask AI Guard B test phrasing rule
description: Guard B pipeline tests must use exact phrases from LISTING_KEY_KEYWORD_MAP; wrong phrases leave normalizedFieldKey null and the adapter mock throws.
---

## Rule

When writing a test that asserts Guard B fires for a `listing.*` null field, the question string passed to `runner->run()` must contain a phrase that appears verbatim (case-insensitive substring match) in `AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP` for the target key.

## Why

`detectListingFieldKey()` iterates LISTING_KEY_KEYWORD_MAP and returns the first matching key. If the question doesn't match any phrase, `$normalizedFieldKey` remains null. Guard B only fires when `$normalizedFieldKey !== null && str_starts_with($normalizedFieldKey, 'listing.')`. Without that, the runner proceeds to call `$adapter->generate()`. If the test mock has `$adapter->expects($this->never())`, PHPUnit throws an unexpected-call exception inside the runner's try/catch, which returns `status = 'failed'` — not 'insufficient_context'. The test then fails with a confusing "status must be insufficient_context" assertion error.

## How to apply

Before writing a Guard B test:
1. Search `LISTING_KEY_KEYWORD_MAP` for the target key and copy one of its phrases exactly.
2. Use that phrase as (or as part of) the question string in `assertGuardBFiresWithLabel()`.
3. For fields with NO entry in LISTING_KEY_KEYWORD_MAP (e.g. `listing.rental_budget`), do not write a Guard B pipeline test. Use a source-grep test instead to confirm the EAV key is present in `AskAiContextBuilderService`.

## Known fields with no LISTING_KEY_KEYWORD_MAP entry (as of June 2026)

- `listing.rental_budget` — tenant max budget; reached only via FAQ/generic path; Guard B cannot be triggered via detectListingFieldKey.
