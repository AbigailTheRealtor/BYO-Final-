---
name: Ask AI Phase 1 Lineage Remediation — Phantom Key Pattern
description: Three phantom routing keys found and fixed; establishes the cross-check rule for LISTING_KEY_KEYWORD_MAP vs extractFactualFields().
---

# Ask AI Phase 1 Lineage Remediation — Phantom Key Pattern

**Why:** Three keys were in LISTING_KEY_KEYWORD_MAP and/or the response contract but had no data source in the context builder, causing silent routing failures (Guard B would never fire because the key wasn't present in ctx['listing'] at all).

## The Rule
Every entry in `LISTING_KEY_KEYWORD_MAP` must map to a context key that is produced by `extractFactualFields()` for at least one role. If a key is in the map but no role's extractor produces it, it is a phantom and Guard B will never fire — OpenAI gets called without relevant context.

## Fixes Applied (June 2026)

| Key | Issue | Fix |
|-----|-------|-----|
| `listing.buy_now_price` | In LISTING_KEY_KEYWORD_MAP; form saves `saveMeta('buy_now_price', ...)` but extractor didn't read it | Added `'buy_now_price' => $infoGet('buy_now_price')` to seller extractor |
| `listing.hoa_fee_requirement` | In map + contract; no saveMeta() call in any current form; not a native column of seller_agent_auctions or buyer_agent_auctions | Removed from LISTING_KEY_KEYWORD_MAP, context label map, and response contract |
| `listing.condo_fee` / `condo_fee_schedule` | In response contract only (not in LISTING_KEY_KEYWORD_MAP); legacy property_auctions columns; no current form saves them | Removed from response contract |

## Confirmed OK
- Landlord `number_occupant` key: form saves `number_occupant`, extractor reads `infoGet('number_occupant')` → CORRECT
- Tenant `number_of_occupants` key: TenantAgentAuction saves `number_of_occupants` (household size), extractor reads same → CORRECT

## Approved field count after Phase 1
49 entries in LISTING_KEY_KEYWORD_MAP (was 50).

## How to Apply
Before adding a new entry to LISTING_KEY_KEYWORD_MAP, verify the context key it maps to is produced by `extractFactualFields()` in `AskAiContextBuilderService.php` for the intended role(s). If not, add the `infoGet()` or `nativeGet()` read to the extractor first.

**Test files to update when adding/removing LISTING_KEY_KEYWORD_MAP entries:**
- `tests/Unit/Services/AskAi/AskAiApprovedFieldCoverageHarnessTest.php` (data provider + count in docblock)
- `tests/Unit/Services/AskAi/AskAiContextBuilderServiceTest.php` (Case U role key-set tests; Case W regression tests)
