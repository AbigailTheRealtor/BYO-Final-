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
| landlord | **No description field** — return null; fallback always skips  |

**Why:** Landlord and tenant have no native text columns for description.
Tenant stores an optional freetext note in EAV (`tenant_agent_auction_metas`),
not in the native table. Landlord has no equivalent field in either the native
table or any EAV meta key — the context builder omits it entirely.
`loadListingDescription()` in `AskAiRunnerV2Service` implements this mapping.

**How to apply:** Any feature that reads the "public listing description" for
fallback or summarization must use this mapping, not assume all four roles
have a native `description` column.

## Sentinel guard replaces keyword guard

`isRealEstateQuestion()` was initially added as a keyword guard to skip the
OpenAI call for off-topic questions. It was removed because:
- The normalizer already made 1 OpenAI call to reach `unsupported`.
- The adapter's `INFORMATION_NOT_IN_DESCRIPTION` sentinel handles irrelevant
  questions correctly → maps to `description_fallback_miss / insufficient_context`.
- Maintaining a second keyword registry is tech debt with no meaningful cost benefit.

## Dev DB note

In the development environment, `seller_agent_auctions.description` is empty
for all rows (including listing 121). The description fallback correctly skips
(no trace key added) when description is null/empty. Browser verification
requires a real listing with a seller-authored description.
