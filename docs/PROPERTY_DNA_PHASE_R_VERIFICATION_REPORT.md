# Property DNA Phase R — Verification Report

**Document Date:** 2026-05-29
**Phase:** R — Deterministic Property Marketing Brief Builder
**Service:** `PropertyMarketingBriefService`
**File:** `app/Services/Dna/PropertyMarketingBriefService.php`

---

## 1. Structural

| Check | Result | Notes |
|---|---|---|
| File exists at `app/Services/Dna/PropertyMarketingBriefService.php` | PASS | Confirmed by file system |
| Namespace is `App\Services\Dna` | PASS | Line 3 of service file |
| Method signature is `public function build(PropertyDnaProfile $profile): array` | PASS | `public function build(PropertyDnaProfile $profile): array` |
| PHP syntax check (`php -l`) passes with no errors | PASS | `No syntax errors detected` |
| All nine top-level keys always returned by `build()` | PASS | All nine keys assigned unconditionally before return |
| No key is ever null — all nine present even for empty profile | PASS | Pass-through keys receive empty context arrays; derived keys receive empty arrays; summary always has 6 integer fields |
| Class autoloads correctly via Composer | PASS | `class_exists('App\Services\Dna\PropertyMarketingBriefService')` returns `true` |

### Nine required output keys (always present)

| # | Key | Source |
|---|---|---|
| 1 | `property_attribute_context` | Pass-through of Phase P `attribute_context` |
| 2 | `transaction_context` | Pass-through of Phase P `transaction_context` |
| 3 | `quantitative_context` | Pass-through of Phase P `quantitative_context` |
| 4 | `marketing_asset_checklist` | Derived from `presentation` bucket via `MARKETING_ASSET_MAP` |
| 5 | `missing_information_checklist` | Derived from empty named buckets via `MISSING_INFO_MAP` |
| 6 | `seller_landlord_questions` | Derived from sparse named buckets via `SELLER_LANDLORD_QUESTION_MAP` |
| 7 | `listing_preparation_notes` | Derived from timing/structure/financing records via `LISTING_PREPARATION_NOTE_MAP` |
| 8 | `neutral_feature_summary` | Verbatim attribute and quantitative entries from Phase P context |
| 9 | `summary` | Six deterministic integer counts |

---

## 2. Dependency

| Check | Result | Notes |
|---|---|---|
| Only one injected dependency: `PropertyMarketingContextService` | PASS | Constructor: `private readonly PropertyMarketingContextService $contextService` |
| No other services, facades, or helpers are referenced | PASS | Only `App\Models\PropertyDnaProfile` (input model type hint) |
| No AI system or language model calls | PASS | `grep -n "OpenAI\|ChatGPT\|GPT\|LLM"` returns zero matches |
| No external API calls | PASS | No `Http::`, Guzzle, `curl`, or HTTP client usage |
| No database access | PASS | No `DB::`, `Schema::`, `save()`, `create()`, `update()`, or Eloquent writes |
| No queue, session, cache, or event dispatch | PASS | None present |

Dependency chain: `PropertyMarketingBriefService` → `PropertyMarketingContextService` → `PropertyDnaExplanationService` → `PropertyDnaProfile`. Matches Phase R specification exactly.

---

## 3. Governance

| Check | Result | Notes |
|---|---|---|
| No database writes anywhere in the file | PASS | No `save()`, `create()`, `update()`, `insert()`, or `DB::` calls |
| No route registration | PASS | No `Route::` usage |
| No controller, Livewire component, Blade view, or JavaScript introduced | PASS | Backend-only service file |
| No migration, seeder, or schema change | PASS | No schema-touching code |
| No existing DNA service file was modified | PASS | Only new files added |
| No existing model was modified | PASS | `PropertyDnaProfile` is consumed as a read-only input |
| Service includes full governance doc-block | PASS | Lines 7–50 of service file; lists all MUST NEVER constraints |
| Output is not surfaced in any public, agent-facing, or client-facing layer | PASS | No route exposes this service; no Blade template calls it |

---

## 4. Determinism

### BUCKET_MINIMUM threshold decision

`BUCKET_MINIMUM = 1` (private const). This means `count($records) < 1`, which fires only when a bucket has **zero records** — i.e., is completely empty. The constant is named to allow future governance decisions to raise the threshold (for example, to 2 to catch sparse but non-empty buckets), but the current value of 1 deliberately confines questions to fully missing dimensions only. Raising it above 1 without a separate governance decision would emit questions for buckets that already contain at least one record, conflating "sparse" with "missing." The constant is accompanied by an explanatory comment block inside the service.

### Output string provenance

Every non-passthrough output string in this service originates from a `private const` defined within the class. There are no inline string literals used as output values anywhere in the operational code.

| Constant | Purpose |
|---|---|
| `MARKETING_ASSET_MAP` | Checklist entry per tag in `presentation` bucket |
| `MARKETING_ASSET_FALLBACK` | Fallback when a presentation tag is not in `MARKETING_ASSET_MAP` |
| `MISSING_INFO_MAP` | Missing-data checklist entry per named empty bucket |
| `MISSING_INFO_ATTRIBUTE_FALLBACK` | Fallback when an attribute bucket key misses `MISSING_INFO_MAP` |
| `MISSING_INFO_TRANSACTION_FALLBACK` | Fallback when a transaction bucket key misses `MISSING_INFO_MAP` |
| `SELLER_LANDLORD_QUESTION_MAP` | Pre-written question per named empty/sparse bucket |
| `QUESTION_ATTRIBUTE_FALLBACK` | Fallback when an attribute bucket key misses `SELLER_LANDLORD_QUESTION_MAP` |
| `QUESTION_TRANSACTION_FALLBACK` | Fallback when a transaction bucket key misses `SELLER_LANDLORD_QUESTION_MAP` |
| `LISTING_PREPARATION_NOTE_MAP` | Pre-written note per tag in timing/structure/financing buckets |
| `LISTING_PREPARATION_NOTE_FALLBACK` | Fallback when a tag misses `LISTING_PREPARATION_NOTE_MAP` |

**Clarification — map lookup key construction:** The service does concatenate strings to form map lookup keys internally (e.g., `'attribute_context:' . $bucket`). This concatenation is used only to construct the key used to look up a value in a `private const` map — it does not generate any output string directly. The retrieved output value always comes from the constant map (or a named fallback constant). This is distinct from generating output text dynamically; all non-passthrough output strings are static constants.

### Other determinism checks

| Check | Result | Notes |
|---|---|---|
| Same profile input always produces identical output | PASS | No random, time-dependent, or state-dependent code paths exist |
| Empty profile returns all nine sections with correct empty-profile counts | PASS | Documented in table below |
| No dead helper methods remain | PASS | `anyBucketHasRecords()` was removed; it is no longer present in the file |

### Empty-profile summary output (deterministic contract)

For a `PropertyDnaProfile` with no archetype tags and no marketing hooks:

| Field | Expected value |
|---|---|
| `summary.total_brief_sections_populated` | `1` (only `summary` itself is populated) |
| `summary.total_attribute_records` | `0` |
| `summary.total_transaction_records` | `0` |
| `summary.total_quantitative_records` | `0` |
| `summary.empty_attribute_bucket_count` | `10` (all 10 named attribute buckets) |
| `summary.empty_transaction_bucket_count` | `4` (all 4 named transaction buckets) |

---

## 5. Fair Housing

| Check | Result | Notes |
|---|---|---|
| No protected-class language in any static string | PASS | All constant map values reviewed; no race, color, national origin, religion, sex, familial status, or disability language |
| No demographic assumptions in any note, question, or checklist entry | PASS | All strings are property-attribute or data-completeness language only |
| No ideal-buyer or ideal-tenant targeting language | PASS | No "ideal", "best fit", "perfect for", or audience-targeting language in any constant or operational code |
| No neighborhood, school, or community demographic assumptions | PASS | No geographic or demographic inference in any string |
| No audience inference or suitability characterization | PASS | No output characterizes who should live in or be attracted to the property |
| `neutral_feature_summary` description values are verbatim Phase O explanation strings | PASS | Values are `$record['explanation']` verbatim — originate from Phase O `TAG_PREFIX_EXPLANATIONS` and `HOOK_TRAIT_EXPLANATIONS` static maps, which are independently verified neutral |
| Governance doc-block explicitly lists Fair Housing prohibitions | PASS | Lines 23–26 of service file |

---

## 6. Tag Map Provenance — Exact Match Evidence

Each tag key in `MARKETING_ASSET_MAP` and `LISTING_PREPARATION_NOTE_MAP` is traced
below to the exact line in `PropertyDnaGenerator::buildArchetypeTags()` that emits it.
All tag strings are verbatim copies of the string literals in the generator.

**Source file:** `app/Services/Dna/PropertyDnaGenerator.php`, method `buildArchetypeTags()`

### MARKETING_ASSET_MAP

| Map key | Generator line | Generator literal |
|---|---|---|
| `'marketing:video-tour'` | `$tags[] = 'marketing:video-tour';` (inside `if (($dimensions['has_video_tour'] ?? null) === 'yes')`) | Exact match ✓ |

`marketing:video-tour` is the only tag emitted with prefix `marketing` by the current generator. The `MARKETING_ASSET_FALLBACK` constant handles any future `marketing:*` tags that may be added to the generator before the map is updated.

### LISTING_PREPARATION_NOTE_MAP

| Map key | Generator line | Generator literal |
|---|---|---|
| `'timing:move-in-specified'` | `$tags[] = 'timing:move-in-specified';` (inside `if (($dimensions['move_in_timing'] ?? null) === 'specified')`) | Exact match ✓ |
| `'structure:lease-option'` | `$tags[] = 'structure:lease-option';` (inside `if (($dimensions['has_lease_option'] ?? null) === 'yes')`) | Exact match ✓ |
| `'structure:lease-purchase'` | `$tags[] = 'structure:lease-purchase';` (inside `if (($dimensions['has_lease_purchase'] ?? null) === 'yes')`) | Exact match ✓ |
| `'financing:seller-financed'` | `$tags[] = 'financing:seller-financed';` (inside `if (($dimensions['has_seller_financing'] ?? null) === 'yes')`) | Exact match ✓ |
| `'financing:assumable'` | `$tags[] = 'financing:assumable';` (inside `if (($dimensions['has_assumable_loan'] ?? null) === 'yes')`) | Exact match ✓ |

The `LISTING_PREPARATION_NOTE_FALLBACK` constant handles any future timing, structure, or financing tags emitted by the generator that are not yet present in the map.

All other archetype tag prefixes (`type`, `style`, `condition`, `amenity`, `parking`, `feature`, `policy`, `community`, `use`, `governance`) route to `attribute_context` buckets only and are not used as keys in either `MARKETING_ASSET_MAP` or `LISTING_PREPARATION_NOTE_MAP`.

---

## Grep Verification Summary

```bash
# PHP syntax
php -l app/Services/Dna/PropertyMarketingBriefService.php
# → No syntax errors detected

# No AI dependencies
grep -n "OpenAI\|ChatGPT\|GPT\|LLM" app/Services/Dna/PropertyMarketingBriefService.php
# → (no matches)

# No inline literal fallbacks remain in operational code
grep -n "?? '" app/Services/Dna/PropertyMarketingBriefService.php
# → (no matches — all ?? fallbacks now reference self:: constants)

# Prohibited language (all matches are inside governance/docblock prohibition lists)
grep -n "recommend\|ideal\|best match\|suitable\|qualified\|approved\|predict" app/Services/Dna/PropertyMarketingBriefService.php
# → Lines in governance docblock prohibition lists only; zero in operational logic
```

---

## Scope Boundary Confirmation

- Phase S (Admin Preview UI) — not implemented
- Phase T (Agent-Reviewed Brief UI) — not implemented
- Any route, controller, Livewire component, Blade view, or JavaScript — none added
- Any migration, seeder, or database change — none made
- Any AI, LLM, embedding, prompt template, or external API call — none present
- Any modification to existing DNA services, models, or explanation maps — none made

**Verdict: Phase R is complete and passes all verification categories.**
