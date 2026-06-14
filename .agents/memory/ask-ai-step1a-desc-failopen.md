---
name: Ask AI Step 1a-desc fallback gate design
description: Confirmed final gate design for the unsupported→description fallback — single-stage (Stage 1 only), no keyword allowlist, OpenAI sentinel is the authority. Design decision explicitly locked.
---

## Confirmed final routing behavior

```
prohibited/restricted     → blocked           (no OpenAI call)
bare greeting/ack/spam    → unsupported       (no OpenAI call)
everything else           → description/context/OpenAI fallback
  OpenAI finds answer     → ready
  OpenAI finds no answer  → insufficient_context
```

## Gate (single-stage, no keyword allowlist)

```php
if (
    $questionType === 'unsupported'
    && $this->enableDescriptionFallback
    && !$this->isObviouslyNonListingQuestion($question)   // Stage 1 only
) { ... description/OpenAI fallback ... }
```

`isObviouslyNonListingQuestion()` catches only exact bare matches: greetings,
one-word acks, and spam ("hello", "hi", "ok", "yes", "thanks", etc.).

**Stage 2 keyword allowlist is permanently removed. Do not restore it.**

## Why Stage 2 was removed (explicitly confirmed)

A keyword allowlist recreates the failure class it was meant to fix.
Every valid listing question whose phrasing lacks a keyword in the list
returns 'unsupported'. No allowlist can be complete.

Priority: a valid listing question must NEVER return 'unsupported' because
the classifier or keyword map missed it.

## Acceptable side-effect (confirmed by user)

Weather/sports/jokes that are not bare greetings reach the description
fallback and return 'insufficient_context' (sentinel miss) rather than
'unsupported'. This is intentional — the token cost of an occasional
off-topic sentinel call is preferable to blocking valid listing questions.

## isListingRelatedQuestion() status

The method still exists as a classification utility, used only in unit tests.
It is NOT part of the gate and must not be re-added there.

## Placeholder sanitization — three paths (ALL required)

`isBareAnswerPlaceholder()` must appear in THREE places or bare "Other"/"TBD"/"N/A" leaks as `ready`:
1. **Step 1a-desc hit**: `$unsuppAnswer !== null && !$this->isBareAnswerPlaceholder($unsuppAnswer)`
2. **Guard B null-field description-hit**: `$descAnswer !== null && !$this->isBareAnswerPlaceholder($descAnswer)`
3. **`finalResponseBuilder->build()`**: main OpenAI path
