# Property DNA Phase P — Deterministic Marketing Context Builder

**Document Date:** 2026-05-29
**Phase:** P — Deterministic Marketing Context Builder
**Preceding Phases:** O — Property DNA Explanation Engine (Phase O services are called by Phase P)
**Type:** New composing services — grouping layer only; no schema, no migrations, no UI, no scoring changes, no new explanation strings

---

## 1. Purpose

Phases N and O introduced translation layers that convert persisted DNA profile arrays into flat lists of neutral explanation records — one record per archetype tag slot, one per marketing hook trait, one per lifestyle tag slot, one per deal-breaker flag. Those flat lists are accurate and complete but carry no organizational structure. A consumer reading Phase O output sees all records in persisted input order with no grouping by marketing dimension category.

Phase P introduces two deterministic composing services that call the Phase O explanation services and reorganize the resulting explanation records into named marketing context category buckets:

- `PropertyMarketingContextService` — groups `PropertyDnaProfile` explanation records into `attribute_context`, `transaction_context`, and `quantitative_context`.
- `BuyerTenantMarketingContextService` — groups `BuyerTenantDnaProfile` explanation records into `preference_context` and `requirements_context`.

No new intelligence, scoring, ranking, recommendation, targeting, or AI behavior is introduced. Phase P adds organizational structure only.

---

## 2. Service Architecture

Phase P composes with Phase O explanation services:

```
PropertyDnaProfile
       │
       ▼
PropertyDnaExplanationService::generate()        ← Phase O (unchanged)
       │  returns:
       │    archetype_tag_explanations  [ {tag, explanation}, ... ]
       │    marketing_hook_explanations [ {trait, value, explanation}, ... ]
       ▼
PropertyMarketingContextService::build()         ← Phase P (new)
       │  groups archetype tag records by prefix → named attribute/transaction buckets
       │  passes marketing hook records to quantitative_context unchanged
       ▼
Structured marketing context array
```

```
BuyerTenantDnaProfile
       │
       ▼
BuyerTenantDnaExplanationService::generate()     ← Phase O (unchanged)
       │  returns:
       │    lifestyle_tag_explanations  [ {tag, explanation}, ... ]
       │    deal_breaker_explanations   [ {flag, source_field, value, explanation}, ... ]
       ▼
BuyerTenantMarketingContextService::build()      ← Phase P (new)
       │  groups lifestyle tag records by prefix/bare tag → named preference buckets
       │  passes deal-breaker flag records to requirements_context unchanged
       ▼
Structured marketing context array
```

Both Phase P services are **composing services** — they depend on Phase O services and produce a higher-level organization of the same underlying data. They add grouping structure; they add nothing else.

### Phase O vs Phase P Distinction

| Layer | What it does | Output shape |
|-------|-------------|--------------|
| Phase O | Maps each tag/hook/flag slot to a neutral explanation string | Flat list of explanation records, one per slot |
| Phase P | Groups Phase O explanation records by tag prefix into named category buckets | Nested structure of named bucket arrays |

Phase P introduces no new explanation strings. Every `explanation` value in Phase P output originates from Phase O constant maps, returned verbatim.

---

## 3. Allowed Behavior

- Calling `PropertyDnaExplanationService::generate()` and `BuyerTenantDnaExplanationService::generate()` with the provided profile model instance.
- Extracting tag prefixes by splitting on the first `:` (identical logic to Phase O).
- Looking up extracted prefixes in `TAG_GROUP_MAP` or `LIFESTYLE_GROUP_MAP` to determine the target bucket.
- Appending Phase O explanation records to named bucket arrays.
- Routing records whose prefix is not found in a map to the `unrecognized` bucket.
- Passing marketing hook explanation records and deal-breaker flag explanation records through to their respective output keys unchanged.
- Computing integer counts for the `summary` block.
- Returning empty arrays for buckets with no matching records.

---

## 4. Prohibited Behavior

Both services MUST NEVER:

- Generate marketing copy, ad copy, listing descriptions, or any narrative text.
- Perform audience targeting, buyer persona inference, or demographic grouping.
- Apply protected-class inference of any kind.
- Rank, sort, order, or weight any tag, hook, flag, or group by any signal.
- Endorse, steer toward, or evaluate any listing, buyer, tenant, seller, or agent.
- Determine fitness, screening, acceptance, or rejection for any party or property.
- Forecast any outcome, likelihood, or probability of any transaction event.
- Perform AI reasoning, language model inference, embedding lookup, or ML logic.
- Call any external service, API, or AI endpoint of any kind.
- Change, recalculate, modify, or influence any DNA profile, score, or metric.
- Introduce new explanation strings — all explanation text must originate from Phase O.
- Normalize, reformat, label-convert, or transform any tag string, trait key, flag key, or value string.
- Read or write any DNA profile, compatibility record, or marketing output table.
- Surface output in any user-facing view, API, PDF, email, or cache layer.
- Modify `PropertyDnaExplanationService`, `BuyerTenantDnaExplanationService`, either generator, either profile model, the compatibility engine, or any job, migration, or route.

---

## 5. Verbatim Passthrough Contract

All tag strings, trait keys, marketing hook values, flag keys, source field strings, and deal-breaker values are passed through **exactly as stored** in Phase O explanation records. Phase O itself already passes them verbatim from the database. Phase P preserves this chain end-to-end.

| Persisted value | Phase O `tag` / `value` / `flag` | Phase P bucket record `tag` / `value` / `flag` |
|-----------------|----------------------------------|------------------------------------------------|
| `"amenity:pool"` | `"amenity:pool"` | `"amenity:pool"` — in `attribute_context.amenities` |
| `"financing:seller-financed"` | `"financing:seller-financed"` | `"financing:seller-financed"` — in `transaction_context.financing` |
| `"3"` (bedrooms hook value) | `"3"` | `"3"` — in `quantitative_context` |
| `"has-pets"` (bare tag) | `"has-pets"` | `"has-pets"` — in `preference_context.occupant_signals` |
| `"500000"` (budget flag value) | `"500000"` | `"500000"` — in `requirements_context` |

The explanation strings in each record come from Phase O constant maps, returned verbatim without modification.

---

## 6. Output Structures

### `PropertyMarketingContextService::build(PropertyDnaProfile $profile): array`

```
[
    'attribute_context' => [
        'property_type'      => [ ['tag' => string, 'explanation' => string], ... ],
        'property_style'     => [ ['tag' => string, 'explanation' => string], ... ],
        'property_condition' => [ ['tag' => string, 'explanation' => string], ... ],
        'amenities'          => [ ['tag' => string, 'explanation' => string], ... ],
        'parking'            => [ ['tag' => string, 'explanation' => string], ... ],
        'features'           => [ ['tag' => string, 'explanation' => string], ... ],
        'policies'           => [ ['tag' => string, 'explanation' => string], ... ],
        'community'          => [ ['tag' => string, 'explanation' => string], ... ],
        'use_classification' => [ ['tag' => string, 'explanation' => string], ... ],
        'governance'         => [ ['tag' => string, 'explanation' => string], ... ],
        'unrecognized'       => [ ['tag' => string, 'explanation' => string], ... ],
    ],
    'transaction_context' => [
        'timing'                => [ ['tag' => string, 'explanation' => string], ... ],
        'transaction_structure' => [ ['tag' => string, 'explanation' => string], ... ],
        'financing'             => [ ['tag' => string, 'explanation' => string], ... ],
        'presentation'          => [ ['tag' => string, 'explanation' => string], ... ],
        'unrecognized'          => [ ['tag' => string, 'explanation' => string], ... ],
    ],
    'quantitative_context' => [
        ['trait' => string, 'value' => string, 'explanation' => string],
        ...
    ],
    'summary' => [
        'total_archetype_tags'         => int,
        'total_marketing_hooks'        => int,
        'non_empty_attribute_groups'   => int,
        'non_empty_transaction_groups' => int,
    ],
]
```

**Key properties:**
- All named bucket arrays are always present, even when empty.
- `unrecognized` buckets are always present in both `attribute_context` and `transaction_context`; they are empty unless an unknown prefix is encountered.
- `quantitative_context` is a flat array of all marketing hook explanation records in Phase O input order; no grouping is applied.
- Summary counts are integers only; no percentages, ratios, or derived signals.

### `BuyerTenantMarketingContextService::build(BuyerTenantDnaProfile $profile): array`

```
[
    'preference_context' => [
        'property_type_preference'      => [ ['tag' => string, 'explanation' => string], ... ],
        'property_condition_preference' => [ ['tag' => string, 'explanation' => string], ... ],
        'occupant_signals'              => [ ['tag' => string, 'explanation' => string], ... ],
        'community_signals'             => [ ['tag' => string, 'explanation' => string], ... ],
        'required_amenities'            => [ ['tag' => string, 'explanation' => string], ... ],
        'transaction_openness'          => [ ['tag' => string, 'explanation' => string], ... ],
        'financial_signals'             => [ ['tag' => string, 'explanation' => string], ... ],
        'policy_preferences'            => [ ['tag' => string, 'explanation' => string], ... ],
        'unrecognized'                  => [ ['tag' => string, 'explanation' => string], ... ],
    ],
    'requirements_context' => [
        [
            'flag'         => string,
            'source_field' => string,
            'value'        => string|null,
            'explanation'  => string,
        ],
        ...
    ],
    'summary' => [
        'total_lifestyle_tags'        => int,
        'total_deal_breaker_flags'    => int,
        'non_empty_preference_groups' => int,
        'has_hard_requirements'       => bool,
    ],
]
```

**Key properties:**
- All named bucket arrays are always present, even when empty.
- `unrecognized` bucket is always present; empty unless an unknown prefix is encountered.
- `requirements_context` is a flat array of all deal-breaker flag explanation records in Phase O input order; no sub-grouping is applied.
- `has_hard_requirements` is `true` when `requirements_context` contains at least one record, `false` otherwise.

---

## 7. TAG_GROUP_MAP — `PropertyMarketingContextService`

Source: `PropertyDnaGenerator::buildArchetypeTags()` — all 14 currently emitted prefixes are mapped.

### attribute_context groups (10 prefixes)

| Tag Prefix | Bucket Name | What it groups |
|------------|-------------|----------------|
| `type` | `property_type` | Property type archetype tags |
| `style` | `property_style` | Architectural style archetype tags |
| `condition` | `property_condition` | Property condition archetype tags |
| `amenity` | `amenities` | On-site amenity archetype tags |
| `parking` | `parking` | Parking facility archetype tags |
| `feature` | `features` | Physical feature and specified-terms archetype tags |
| `policy` | `policies` | Occupancy and use policy archetype tags |
| `community` | `community` | Community designation archetype tags |
| `use` | `use_classification` | Use classification archetype tags |
| `governance` | `governance` | Governance and association archetype tags |

### transaction_context groups (4 prefixes)

| Tag Prefix | Bucket Name | What it groups |
|------------|-------------|----------------|
| `timing` | `timing` | Timing and availability archetype tags |
| `structure` | `transaction_structure` | Lease and transaction structure archetype tags |
| `financing` | `financing` | Financing option archetype tags |
| `marketing` | `presentation` | Marketing asset and presentation archetype tags |

**Total: 14 prefixes mapped. Fallback: unrecognized prefix → `attribute_context.unrecognized`.**

---

## 8. LIFESTYLE_GROUP_MAP — `BuyerTenantMarketingContextService`

Source: `BuyerTenantDnaGenerator::buildLifestyleTags()` — all 8 currently emitted prefixes and bare tags are mapped.

| Tag Prefix / Bare Tag | Bucket Name | What it groups | Notes |
|-----------------------|-------------|----------------|-------|
| `prefers-type` | `property_type_preference` | Property type preference tags | |
| `prefers-condition` | `property_condition_preference` | Property condition preference tags | |
| `has-pets` | `occupant_signals` | Occupant characteristic bare tags | Bare tag — full string used as lookup key |
| `seeks` | `community_signals` | Community preference tags | |
| `requires` | `required_amenities` | Required amenity tags | |
| `open-to` | `transaction_openness` | Transaction structure interest tags | |
| `financial` | `financial_signals` | Financial status and qualification tags | |
| `preference` | `policy_preferences` | Policy and community awareness tags | |

**Total: 8 prefixes/bare tags mapped. Fallback: unrecognized prefix → `preference_context.unrecognized`.**

**Note on bare tag lookup:** When a lifestyle tag contains no `:` separator (e.g., `has-pets`), the full tag string is used as the map lookup key rather than extracting a prefix. This mirrors the Phase O logic exactly.

---

## 9. Fallback Behavior

No tag, hook, flag, or record present in Phase O output is ever silently dropped by Phase P.

| Scenario | Behavior |
|----------|----------|
| Archetype tag prefix not in TAG_GROUP_MAP | Record placed in `attribute_context.unrecognized` |
| Lifestyle tag prefix not in LIFESTYLE_GROUP_MAP | Record placed in `preference_context.unrecognized` |
| Profile with no archetype tags | All attribute_context and transaction_context buckets empty; quantitative_context empty |
| Profile with no marketing hooks | quantitative_context is empty array |
| Profile with no lifestyle tags | All preference_context buckets empty |
| Profile with no deal-breaker flags | requirements_context is empty array; has_hard_requirements is false |

---

## 10. Governance Restrictions

1. Both services are composing translation layers only. They call Phase O services and regroup the output by prefix category. They must never alter, re-compute, or influence any DNA profile, compatibility score, or coverage metric.

2. Both services must never generate text. Every string in the output originates from Phase O constant maps or from verbatim persisted profile data. No string concatenation, interpolation, or runtime text assembly produces explanation strings.

3. Both services must never call any AI system, language model, embedding service, or external API of any kind.

4. Both services must never introduce audience targeting, demographic grouping, protected-class inference, or evaluative judgments about any listing, buyer, tenant, seller, landlord, or agent.

5. The `summary` block must contain integer counts and one boolean only. No percentages, ratios, derived scores, weighted signals, or normalized metrics are permitted in the summary.

6. All named bucket arrays must always be present in output, even when empty. No bucket key may be omitted based on whether data is present.

7. Output order within each bucket must reflect Phase O input order. No reordering, sorting, or weighting is applied within any bucket.

8. No output from either service may appear in any public listing page, agent-facing view, client-facing view, API response, PDF, email, websocket broadcast, or cache layer without a separately approved visibility phase.

9. Both services must pass all grep verifications: no prohibited language in any operational code or docblock, no AI dependencies.

---

## 11. Grep Verification Commands

Run these against the Phase P service files after any modification:

```bash
# 1. PHP syntax check — PropertyMarketingContextService
php -l app/Services/Dna/PropertyMarketingContextService.php

# 2. PHP syntax check — BuyerTenantMarketingContextService
php -l app/Services/Dna/BuyerTenantMarketingContextService.php

# 3. No prohibited language in Phase P files
grep -n "recommend\|ideal\|best match\|suitable\|qualified\|approved\|predict" \
  app/Services/Dna/PropertyMarketingContextService.php \
  app/Services/Dna/BuyerTenantMarketingContextService.php

# 4. No AI dependencies in Phase P files
grep -n "OpenAI\|ChatGPT\|GPT\|LLM" \
  app/Services/Dna/PropertyMarketingContextService.php \
  app/Services/Dna/BuyerTenantMarketingContextService.php
```

Commands 3 and 4 must return zero matches. Commands 1 and 2 must report no syntax errors.

---

## 12. Summary Field Definitions

### `PropertyMarketingContextService` — `summary` block

| Field | Type | Definition |
|-------|------|------------|
| `total_archetype_tags` | `int` | Count of all records in `archetype_tag_explanations` returned by Phase O. Equals the number of tag strings present in `ai_buyer_archetype_tags` on the profile at compute time. |
| `total_marketing_hooks` | `int` | Count of all records in `marketing_hook_explanations` returned by Phase O. Equals the number of hook records present in `ai_marketing_hooks` on the profile at compute time. |
| `non_empty_attribute_groups` | `int` | Count of bucket arrays within `attribute_context` (including `unrecognized`) that contain at least one record. Range: 0 – 11 (10 named buckets + `unrecognized`). |
| `non_empty_transaction_groups` | `int` | Count of bucket arrays within `transaction_context` (including `unrecognized`) that contain at least one record. Range: 0 – 5 (4 named buckets + `unrecognized`). |

**Rules:**
- All four fields are always present in the `summary` key, even when the profile has no tags.
- All values are non-negative integers. No floats, percentages, or derived metrics are permitted.
- `non_empty_attribute_groups` and `non_empty_transaction_groups` count buckets that are non-empty **after** routing; they reflect actual tag coverage across named dimensions.

### `BuyerTenantMarketingContextService` — `summary` block

| Field | Type | Definition |
|-------|------|------------|
| `total_lifestyle_tags` | `int` | Count of all records in `lifestyle_tag_explanations` returned by Phase O. Equals the number of tag strings present in `lifestyle_tags` on the profile at compute time. |
| `total_deal_breaker_flags` | `int` | Count of all records in `deal_breaker_explanations` returned by Phase O. Equals the number of flag records present in `deal_breaker_flags` on the profile at compute time. |
| `non_empty_preference_groups` | `int` | Count of bucket arrays within `preference_context` (including `unrecognized`) that contain at least one record. Range: 0 – 9 (8 named buckets + `unrecognized`). |
| `has_hard_requirements` | `bool` | `true` when `requirements_context` contains at least one deal-breaker flag record; `false` otherwise. Derived directly from `total_deal_breaker_flags > 0`. |

**Rules:**
- All four fields are always present in the `summary` key, even when the profile has no tags or flags.
- `total_lifestyle_tags`, `total_deal_breaker_flags`, and `non_empty_preference_groups` are non-negative integers.
- `has_hard_requirements` is a boolean only — never a count, score, or severity signal.
- No percentages, ratios, weighted signals, or derived scores are permitted in either summary block.

---

## 13. Files

| File | Role |
|------|------|
| `app/Services/Dna/PropertyMarketingContextService.php` | Groups `PropertyDnaProfile` explanation records into attribute, transaction, and quantitative context |
| `app/Services/Dna/BuyerTenantMarketingContextService.php` | Groups `BuyerTenantDnaProfile` explanation records into preference context and requirements context |
| `app/Services/Dna/PropertyDnaExplanationService.php` | Phase O dependency — called by `PropertyMarketingContextService`; do not modify |
| `app/Services/Dna/BuyerTenantDnaExplanationService.php` | Phase O dependency — called by `BuyerTenantMarketingContextService`; do not modify |
| `app/Models/PropertyDnaProfile.php` | Input model for `PropertyMarketingContextService` |
| `app/Models/BuyerTenantDnaProfile.php` | Input model for `BuyerTenantMarketingContextService` |
| `app/Services/Dna/PropertyDnaGenerator.php` | Source of truth for archetype tag prefixes — do not modify |
| `app/Services/Dna/BuyerTenantDnaGenerator.php` | Source of truth for lifestyle tag prefixes and bare tags — do not modify |
