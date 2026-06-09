# Ask AI — Natural Language Field Router Audit Results

**Task**: Ask AI Natural Language Field Router (Task #2405)
**Date**: 2026-06-09
**Scope**: Role-aware registry metadata enrichment so OpenAI can route unphrased questions to approved listing fields.

---

## Summary

The keyword classifier remains the deterministic fast path for all known question patterns.
OpenAI is invoked only when the classifier returns `unsupported` and the feature flag
`ask_ai.enable_openai_intent_normalization` is `true`.

This task enriches the normalizer's payload with role-filtered registry metadata so that,
when OpenAI is called on an `unsupported` question, it has full context about which canonical
field paths exist for the requested listing role. OpenAI responds with a structured JSON object
(new three-shape format) instead of a bare key string. The runner traces the router's decision
with four new observability fields.

---

## Components Modified

### 1. `AskAiFieldQuestionRegistryService` — `routerEntries(string $role): array`

New static method. Returns all registry entries (FAQ + listing field) that are eligible for
OpenAI routing for the given role:

- **Includes**: `pinned`, `opaque_key` entries from `registry()` + all entries from `listingFieldRegistry()`.
- **Excludes**: `match_criteria` and `umbrella_only` entries (not actionable for a field lookup).
- **Role filter**: entries are filtered to those matching the requested role (or that are role-agnostic).

### 2. `AskAiIntentNormalizerService` — payload and response format

**`normalize(string $question, array $knownFieldKeys, string $role = ''): ?string`**
- Accepts an optional `$role` parameter.
- When `$role` is non-empty, `buildPayload()` includes a `field_registry` array sourced from
  `AskAiFieldQuestionRegistryService::routerEntries($role)`.

**New three-shape response format (task `intent_normalization_v2`):**

| Shape | Meaning |
|---|---|
| `{"status":"matched","context_path":"<key>"}` | OpenAI routed to a known canonical field path |
| `{"status":"unsupported"}` | Question is not answerable from approved field paths |
| `{"status":"prohibited"}` | Question touches a fair-housing or other prohibited topic |

**Backward compatibility**: The legacy `{"normalized_key":"..."}` shape is still parsed as a
fallback, so existing tests and any in-flight deployments with the old prompt continue to work.

**`getLastContextPath(): ?string`**
- New accessor. Returns the raw `context_path` OpenAI produced (captured before the hallucination
  guard validates it). Useful for observability even when the guard rejects the path.
- Resets to `null` on every `normalize()` call entry.

**`lastStatus` vocabulary additions:**
- `'prohibited'` — new status; occurs when OpenAI returns `{"status":"prohibited"}`.

**`CALL_OPTIONS` max_tokens**: raised from `60` to `80` to accommodate the richer JSON response.

### 3. `AskAiRunnerV2Service` — router observability in trace

Four new fields added to the `$trace` array (and the exception catch trace):

| Field | Values | Meaning |
|---|---|---|
| `deterministic_question_type` | classifier result string | The pre-router question type (classifier output before any normalization). |
| `router_called` | `'Y'` / `'N'` | Whether the normalizer (OpenAI router) fired this request. |
| `router_status` | `matched` / `unsupported` / `prohibited` / `failed` / `not_called` | Outcome of the router call. Note: normalizer's `unknown` status maps to `unsupported` here. |
| `router_context_path` | string or `null` | The raw `context_path` returned by OpenAI (from `getLastContextPath()`). Populated even when the hallucination guard rejects it. |

**Role passthrough**: `normalize()` is now called with `$listingType` as the role argument.

**Prohibited re-classification**: when `getLastStatus()` returns `'prohibited'`, the runner
re-classifies the question as `prohibited` so the internal runner applies the same fair-housing
refusal it uses for classifier-blocked questions.

**Router status mapping** (normalizer → trace):

| Normalizer `lastStatus` | `router_status` in trace |
|---|---|
| `matched` | `matched` |
| `unknown` | `unsupported` |
| `prohibited` | `prohibited` |
| `failed` | `failed` |
| anything else | `not_called` |

---

## New Tests Added

### `AskAiIntentNormalizerServiceTest.php`

| Case | Count | Coverage |
|---|---|---|
| M — `getLastContextPath()` tracking | 6 | path set on match, null on unknown/exception, captured before guard, resets between instances, null before first call |
| N — New `{status, context_path}` format | 12 | matched/unsupported/prohibited shapes, hallucinated path rejected + still captured, empty path, unrecognized status |
| P — Role parameter and governance | 11 | role accepted for tenant/seller, empty role, guard still fires with role, file-level governance checks for `routerEntries`, `field_registry`, all three status shapes in prompt, `getLastContextPath` declaration |

### `AskAiRunnerV2ServiceTest.php`

| Case | Count | Coverage |
|---|---|---|
| P — Router trace fields | 18 | four keys always present; `deterministic_question_type` reflects classifier; `router_called` Y/N; `router_status` maps all five values; `router_context_path` null/populated; `prohibited` re-classification; exception path has router keys; file governance check for `$listingType` passthrough |

**Total new test methods**: ~47

---

## Backward Compatibility

- All legacy tests that mock `makeClientMock('faq_answers.hvac_system_age')` (returning
  `normalized_key`) continue to pass — the legacy parsing branch is preserved.
- All existing runner tests continue to pass — new trace keys are additive.
- Feature flag `ask_ai.enable_openai_intent_normalization` is unchanged; default is `false`.

---

## Feature Flag

```php
config('ask_ai.enable_openai_intent_normalization', false)
```

The router is entirely disabled when this flag is `false`. No OpenAI calls are made and
`router_called` will always be `'N'` in the trace.
