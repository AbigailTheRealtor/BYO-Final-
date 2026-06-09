---
name: Ask AI router normalizer shape and trace
description: Three-shape OpenAI response format, max_tokens 80, four new runner trace fields, and prohibited re-classification path introduced by the field router enrichment.
---

## The rule

`AskAiIntentNormalizerService` uses `max_tokens => 80` (raised from 60). Any test that pins this value must assert 80.

The prompt instructs OpenAI to return exactly one of three JSON shapes:
- `{"status":"matched","context_path":"<path>"}` — routed to a known field
- `{"status":"unsupported"}` — no matching field
- `{"status":"prohibited"}` — fair-housing or protected-class question

The legacy `{"normalized_key":"..."}` shape is still parsed as a fallback.

`getLastContextPath()` returns the raw path OpenAI produced before the hallucination guard validates it — useful for observability even when the guard rejects it.

## Runner trace (four new fields, always present including exception path)

| Field | Values |
|---|---|
| `deterministic_question_type` | classifier output before any routing |
| `router_called` | `'Y'` / `'N'` |
| `router_status` | `matched` / `unsupported` / `prohibited` / `failed` / `not_called` |
| `router_context_path` | string or null |

Mapping: normalizer `lastStatus` `'unknown'` → `router_status` `'unsupported'`.

## Prohibited re-classification

When `getLastStatus() === 'prohibited'`, the runner re-sets `$questionType = 'prohibited'` and rebuilds the classification before forwarding to the internal runner — same refusal path as classifier-blocked questions.

**Why:** OpenAI may detect a fair-housing violation in an ambiguously phrased `unsupported` question that the keyword classifier missed. Routing it through the internal runner's existing `prohibited` handler keeps refusal logic in one place.

**How to apply:** Any time the normalizer is modified or new `lastStatus` values are added, update the `match` block in `AskAiRunnerV2Service` and add a corresponding runner trace test.
