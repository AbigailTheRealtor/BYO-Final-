# Property DNA Phase O — Property DNA Explanation Engine

## Purpose

Phases A–N built the DNA generation infrastructure, buyer/tenant preference profiling, compatibility engine, and a deterministic explanation layer for compatibility results (Phase N). The DNA profile outputs themselves — the archetype tags, marketing hooks, lifestyle tags, and deal-breaker flags persisted in `PropertyDnaProfile` and `BuyerTenantDnaProfile` — were opaque structured arrays with no neutral description layer.

Phase O introduces two deterministic explanation services that translate those persisted DNA profile arrays into structured explanation records. Both services are pure translation layers with the same governance posture as Phase N. All persisted values pass through verbatim. No normalization, formatting, interpretation, recommendation, or prediction is introduced.

---

## Service Architecture

Phase O mirrors the two-generator architecture of the DNA generation layer:

| DNA Generation Layer          | Phase O Explanation Layer              |
|-------------------------------|----------------------------------------|
| `PropertyDnaGenerator`        | `PropertyDnaExplanationService`        |
| `BuyerTenantDnaGenerator`     | `BuyerTenantDnaExplanationService`     |

Both explanation services live in `app/Services/Dna/` alongside the generators they describe.

---

## Allowed Behavior

- Reading `ai_buyer_archetype_tags` and `ai_marketing_hooks` from a `PropertyDnaProfile` model instance.
- Reading `lifestyle_tags` and `deal_breaker_flags` from a `BuyerTenantDnaProfile` model instance.
- Splitting archetype tag strings on the first `:` to extract a prefix for constant-map lookup.
- Splitting lifestyle tag strings on the first `:` to extract a prefix; using the full tag string when no `:` is present (bare tags such as `has-pets`).
- Looking up extracted prefixes, trait keys, and flag keys in private constant maps.
- Returning structured explanation records with verbatim input values.
- Returning a neutral fallback explanation string for any unrecognized prefix, trait key, or flag key.

---

## Prohibited Behavior

Both services MUST NEVER:

- Change, recalculate, modify, or influence any DNA profile, score, or metric.
- Rank, sort, order, or weight explanation output by score or any other signal.
- Recommend any listing, buyer, tenant, seller, landlord, or agent.
- Determine, infer, or output suitability, qualification, approval, or rejection.
- Predict any outcome, likelihood, or probability of any transaction event.
- Perform AI reasoning, language model inference, embedding lookup, or ML logic.
- Generate narrative persuasion copy, endorsements, or matchmaking language.
- Normalize, reformat, label-convert, or transform any persisted value string.
- Read or write any scoring model, compatibility record, or database row.
- Surface output in any user-facing view, API, PDF, email, or cache layer.
- Modify `PropertyDnaGenerator`, `BuyerTenantDnaGenerator`, either profile model, or any compatibility engine component.

---

## Verbatim Value Passthrough Contract

All persisted values are passed through **exactly as stored** in the database. No transformation of any kind is applied.

| Input (persisted)         | Output `tag` / `trait` / `value` / `flag` / `source_field` |
|---------------------------|--------------------------------------------------------------|
| `"type:single_family"`    | `"type:single_family"` — NOT `"Single Family"`, NOT `"single_family"`, NOT `"type: single_family"` |
| `"12"` (bedrooms value)   | `"12"` — NOT `12` (integer), NOT `"12 bedrooms"`, NOT any transformed form |
| `"seller_financing"`      | `"seller_financing"` — NOT `"Seller Financing"`, NOT `"seller financing"` |
| `"450000"` (budget value) | `"450000"` — NOT `450000` (integer), NOT `"$450,000"`, NOT any formatted form |

The explanation string describes what the **dimension slot** represents. It does not describe or interpret the persisted value itself.

---

## Output Structures

### `PropertyDnaExplanationService::generate(PropertyDnaProfile $profile): array`

```
[
    'archetype_tag_explanations' => [
        [
            'tag'         => string,   // full original tag string, verbatim
            'explanation' => string,   // neutral description of what the tag prefix represents
        ],
        ...
    ],
    'marketing_hook_explanations' => [
        [
            'trait'       => string,   // trait key, verbatim
            'value'       => string,   // trait value, verbatim
            'explanation' => string,   // neutral description of what the trait key represents
        ],
        ...
    ],
]
```

### `BuyerTenantDnaExplanationService::generate(BuyerTenantDnaProfile $profile): array`

```
[
    'lifestyle_tag_explanations' => [
        [
            'tag'         => string,   // full original tag string, verbatim
            'explanation' => string,   // neutral description of what the tag prefix represents
        ],
        ...
    ],
    'deal_breaker_explanations' => [
        [
            'flag'         => string,        // flag key, verbatim
            'source_field' => string,        // source field identifier, verbatim
            'value'        => string|null,   // recorded value verbatim, or null if absent
            'explanation'  => string,        // neutral description of what the flag key represents
        ],
        ...
    ],
]
```

---

## Archetype Tag Prefix Mappings (`PropertyDnaExplanationService`)

Source: `PropertyDnaGenerator::buildArchetypeTags()`. These are all prefixes emitted by the current generator.

| Prefix        | Explanation String |
|---------------|--------------------|
| `type`        | Identifies the structural or categorical property type recorded on the listing. |
| `style`       | Identifies the architectural or stylistic classification recorded on the listing. |
| `condition`   | Identifies the physical condition or state of the property as recorded on the listing. |
| `amenity`     | Identifies an on-site amenity or facility that the property listing indicates is present. |
| `parking`     | Identifies the type of parking facility the property listing indicates is available. |
| `feature`     | Identifies a physical feature or specified terms recorded on the property listing. |
| `policy`      | Identifies an occupancy or use policy term that the property listing indicates is specified. |
| `community`   | Identifies a community designation or restriction recorded on the property listing. |
| `use`         | Identifies a use classification (such as commercial) recorded on the property listing. |
| `governance`  | Identifies a governance or association structure recorded on the property listing. |
| `timing`      | Identifies a timing or availability term that the property listing indicates is specified. |
| `structure`   | Identifies a transaction or lease structure option recorded on the property listing. |
| `financing`   | Identifies a financing option or loan structure that the property listing indicates is available. |
| `marketing`   | Identifies a marketing asset or presentation feature recorded on the property listing. |

**Fallback (unrecognized prefix):** `"No explanation is mapped for this archetype tag prefix."`

---

## Marketing Hook Trait Key Mappings (`PropertyDnaExplanationService`)

Source: `PropertyDnaGenerator::buildMarketingHooks()`. These are all trait keys emitted by the current generator.

| Trait Key        | Explanation String |
|------------------|--------------------|
| `property_type`  | The property type dimension recorded on the listing. |
| `bedrooms`       | The bedroom count dimension recorded on the listing. |
| `bathrooms`      | The bathroom count dimension recorded on the listing. |
| `minimum_sqft`   | The minimum heated square footage dimension recorded on the listing. |
| `total_acreage`  | The total acreage dimension recorded on the listing. |
| `occupant_status`| The current occupancy status dimension recorded on the listing. |
| `lease_length`   | The lease length or flexibility dimension recorded on the listing. |
| `sale_provision` | The sale provision type dimension recorded on the listing. |
| `financing_types`| The financing types offered dimension recorded on the listing. |
| `view`           | The view preference dimension recorded on the listing. |

**Fallback (unrecognized trait key):** `"No explanation is mapped for this marketing hook trait key."`

---

## Lifestyle Tag Prefix Mappings (`BuyerTenantDnaExplanationService`)

Source: `BuyerTenantDnaGenerator::buildLifestyleTags()`. These are all prefixes (and bare tags) emitted by the current generator.

| Prefix / Bare Tag   | Explanation String |
|---------------------|--------------------|
| `prefers-type`      | Identifies a property type preference recorded on the demand listing. |
| `prefers-condition` | Identifies a property condition preference recorded on the demand listing. |
| `has-pets`          | Indicates that the demand listing records the presence of pets. |
| `seeks`             | Identifies a community or living arrangement the demand listing indicates is sought. |
| `requires`          | Identifies an amenity or facility the demand listing records as required. |
| `open-to`           | Identifies a transaction structure or financing option the demand listing records as acceptable. |
| `financial`         | Identifies a financial status or qualification term recorded on the demand listing. |
| `preference`        | Identifies a policy or community preference term recorded on the demand listing. |

**Note on bare tags:** `has-pets` contains a hyphen but no colon. When no `:` is present, the full tag string is used as the lookup key rather than a prefix.

**Fallback (unrecognized prefix or bare tag):** `"No explanation is mapped for this lifestyle tag prefix."`

---

## Deal-Breaker Flag Key Mappings (`BuyerTenantDnaExplanationService`)

Source: `BuyerTenantDnaGenerator::buildDealBreakerFlags()`. These are all flag keys emitted by the current generator.

| Flag Key                     | Explanation String |
|------------------------------|--------------------|
| `55_plus_required`           | The demand listing records that a 55-plus community designation is required. |
| `pool_required`              | The demand listing records that a pool is required. |
| `garage_required`            | The demand listing records that a garage is required. |
| `carport_required`           | The demand listing records that a carport is required. |
| `minimum_bedrooms_specified` | The demand listing records a minimum bedroom count requirement; the persisted value is the recorded minimum. |
| `minimum_bathrooms_specified`| The demand listing records a minimum bathroom count requirement; the persisted value is the recorded minimum. |
| `minimum_sqft_specified`     | The demand listing records a minimum square footage requirement; the persisted value is the recorded minimum. |
| `budget_ceiling_specified`   | The demand listing records a budget ceiling; the persisted value is the recorded budget figure. |

**Fallback (unrecognized flag key):** `"No explanation is mapped for this deal-breaker flag key."`

---

## Governance Restrictions

1. Both services are translation layers only. They read persisted profile data and map stored strings to neutral description strings. They must never alter, re-compute, or influence any DNA or compatibility output.
2. Persisted values are passed through verbatim. No string transformation, case conversion, underscore-to-space substitution, or label lookup is permitted on values.
3. Explanation strings must be neutral and factual — they describe what a dimension slot represents, not what the value implies about the property or party.
4. Explanation strings must not contain recommendation language, predictive language, suitability language, or protected-class language.
5. Output order must be deterministic: input order from the persisted array is preserved. No sorting by score, frequency, importance, or any other signal.
6. Unrecognized keys produce a neutral fallback string. No dimension present in the persisted data is silently dropped.
7. No changes may be made to `PropertyDnaGenerator`, `BuyerTenantDnaGenerator`, either profile model, any scoring formula, compatibility engine, jobs, migrations, schema, routes, or any UI.
8. Both services must pass grep verifications: no prohibited recommendation language, no AI dependencies.

---

## Grep Verification Commands

Run these after any modification to files in `app/Services/Dna/`:

```bash
# 1. PHP syntax check — PropertyDnaExplanationService
php -l app/Services/Dna/PropertyDnaExplanationService.php

# 2. PHP syntax check — BuyerTenantDnaExplanationService
php -l app/Services/Dna/BuyerTenantDnaExplanationService.php

# 3. No prohibited recommendation/prediction language in Dna services
grep -r "recommend\|ideal\|best match\|suitable\|qualified\|approved\|predict" app/Services/Dna/

# 4. No AI dependencies in Dna services
grep -r "OpenAI\|ChatGPT\|GPT\|LLM" app/Services/Dna/
```

Commands 3 and 4 must return zero matches. Commands 1 and 2 must report no syntax errors.

---

## Files

| File | Role |
|------|------|
| `app/Services/Dna/PropertyDnaExplanationService.php` | Translates `PropertyDnaProfile` tag and hook arrays into explanation records |
| `app/Services/Dna/BuyerTenantDnaExplanationService.php` | Translates `BuyerTenantDnaProfile` tag and flag arrays into explanation records |
| `app/Services/Dna/PropertyDnaGenerator.php` | Source of truth for archetype tag prefixes and marketing hook trait keys — do not modify |
| `app/Services/Dna/BuyerTenantDnaGenerator.php` | Source of truth for lifestyle tag prefixes and deal-breaker flag keys — do not modify |
| `app/Models/PropertyDnaProfile.php` | Eloquent model with `ai_buyer_archetype_tags` and `ai_marketing_hooks` cast to `array` |
| `app/Models/BuyerTenantDnaProfile.php` | Eloquent model with `lifestyle_tags` and `deal_breaker_flags` cast to `array` |
