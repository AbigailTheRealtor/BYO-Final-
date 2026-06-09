---
name: Ask AI classifier listing_facts vs buyer_tenant_match boundary
description: Substring collision rules when adding keywords to listing_facts that overlap with buyer_tenant_match phrases.
---

## Rule

When adding keywords to `listing_facts` in `AskAiQuestionClassifierService::KEYWORD_RULES`, check every candidate phrase as a substring against all existing `buyer_tenant_match` keywords — and vice versa. `KEYWORD_RULES` is evaluated in array order; `listing_facts` appears before `buyer_tenant_match`, so a listing_facts keyword that is a substring of a buyer_tenant_match question will silently steal it.

## Known protected phrases in buyer_tenant_match

These must never appear as listing_facts keywords (or as substrings of listing_facts keywords):

- `'desired lease length'` — "What is the desired lease length for this buyer?"
- `'preferred lease length'` — counterpart phrase
- `'move-in date'` / `'move in date'` — bare forms kept in buyer_tenant_match for compatibility questions

Safe factual forms that can live in listing_facts without collision:
- `'what lease length is desired'` / `'lease length is desired'` — word-order reversal means 'desired lease length' is not a substring
- `'what is the move-in date'` / `'when is the move-in date'` — full question forms that don't match bare 'move-in date' checks

## Why

`'what is the desired lease length'` was added as a listing_facts keyword and immediately stole "What is the desired lease length for this buyer?" from buyer_tenant_match. The existing regression test `case_M_desired_lease_length_still_classifies_as_buyer_tenant_match` caught it. The over-broad phrase was removed; the two word-order-reversed forms were sufficient.

## How to apply

Before committing any new listing_facts keyword:
1. Grep buyer_tenant_match keywords for any string that contains your new phrase as a substring.
2. Grep your new phrase for any existing buyer_tenant_match keyword that is a substring of it.
3. If collision found, use a more specific / word-order-variant phrasing or add only at buyer_tenant_match level.
4. Run `AskAiQuestionClassifierServiceTest` — case M tests guard the known protected phrases.
