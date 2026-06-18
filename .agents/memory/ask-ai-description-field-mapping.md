---
name: Ask AI description field mapping per role
description: Where each listing role stores its public freetext description — native column vs EAV meta vs absent.
---

# Ask AI description field mapping per role

## The rule

| Role     | Context builder source (primary → fallback)                                 |
|----------|-----------------------------------------------------------------------------|
| seller   | EAV `additional_details` (`seller_agent_auction_metas`) → native `description` column |
| buyer    | native `buyer_agent_auctions.additional_details` column only               |
| tenant   | EAV `additional_details` (`tenant_agent_auction_metas`) only               |
| landlord | EAV `additional_details` (`landlord_agent_auction_metas`) only             |

**Why:** Seller offer-listing forms (SellerOfferListing Livewire) save the property
description via `saveMeta('additional_details', ...)` into EAV, NOT the native
`description` column. The context builder was updated (audit fix I-1) to read
EAV `additional_details` first, then fall back to native `description` for
legacy agent-auction wizard rows. CANONICAL_SOURCE_MAP seller.description is
now `['additional_details', 'native:description']`.

`loadListingDescription()` in `AskAiRunnerV2Service` implements this mapping
for the description fallback (Step 1a-desc) and also reads EAV first.

**Note:** The Ask AI context builder (`AskAiContextBuilderService`) reads
`$infoGet('additional_details')` for landlord/tenant and `$infoGet('additional_details') ?: $nativeGet('description')` for seller. All four roles populate `ctx['listing']['description']`.

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
