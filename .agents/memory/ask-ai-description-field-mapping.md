---
name: Ask AI description field mapping per role
description: Where each listing role stores its public freetext description — native column vs EAV meta vs absent.
---

# Ask AI description field mapping per role

## The rule

| Role     | Source                                                         |
|----------|----------------------------------------------------------------|
| seller   | `seller_agent_auctions.description` (native text column)      |
| buyer    | `buyer_agent_auctions.additional_details` (native text column) |
| tenant   | `tenant_agent_auction_metas` where `meta_key='additional_details'` (EAV) |
| landlord | `landlord_agent_auction_metas` where `meta_key='additional_details'` (EAV) |

**Why:** Landlord and tenant have no native text columns for description.
Both store optional freetext notes in EAV. `loadListingDescription()` in
`AskAiRunnerV2Service` implements this mapping for the description fallback (Step 1a-desc).

**Note:** The Ask AI *context builder* (`AskAiContextBuilderService`) still returns
null for landlord description — it was not updated as part of this task and remains
a separate concern. `loadListingDescription()` is only used by the unsupported-question
description fallback path.

**How to apply:** Any feature that reads the "public listing description" for
fallback or summarization must use this mapping, not assume all four roles
have a native `description` column.

## Description fallback env var

`ASK_AI_ENABLE_DESCRIPTION_FALLBACK=true` must be set in the Replit platform
development environment (same scope as `ASK_AI_ENABLE_OPENAI_INTENT_NORMALIZATION`).
The gate in `AskAiRunnerV2Service` at ~line 2728 checks `$this->enableDescriptionFallback`
which is wired via `AppServiceProvider` from `config('ask_ai.enable_description_fallback')`.

## Sentinel guard replaces keyword guard

`isRealEstateQuestion()` was initially added as a keyword guard to skip the
OpenAI call for off-topic questions. It was removed because:
- The normalizer already made 1 OpenAI call to reach `unsupported`.
- The adapter's `INFORMATION_NOT_IN_DESCRIPTION` sentinel handles irrelevant
  questions correctly → maps to `description_fallback_miss / insufficient_context`.
- Maintaining a second keyword registry is tech debt with no meaningful cost benefit.

## Dev DB note

In the development environment, `seller_agent_auctions.description` and
`buyer_agent_auctions.additional_details` were empty for all rows initially.
Smoke-test content was added directly to listings 121 (seller) and 5 (buyer).
Landlord listing 12 has a real `additional_details` EAV entry confirmed working.

## Known storage inconsistency — seller description

Some seller listings (confirmed: listing 121) store their public description in
`seller_agent_auction_metas` under `additional_details` (EAV) rather than the
native `description` column. The Ask AI runner fallback (`loadListingDescription`)
handles this transparently, so Ask AI works correctly. However the native column
remains empty for those listings. A future cleanup task should normalize seller
description storage so all rows use the native column.
