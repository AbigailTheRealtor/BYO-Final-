---
name: Ask AI registry dual-structure
description: AskAiFieldQuestionRegistryService has two separate registries — FAQ (faq_answers.*) and listing model (listing.*); sample_question_2 and field_type are injected by withSecondQuestions(), not stored inline.
---

## Rule
`AskAiFieldQuestionRegistryService` maintains **two distinct registries**:
- `registry()` — 168 FAQ entries (`faq_answers.*` paths), FAQ-only, all with `field_type='faq'`, `sample_question`, `sample_question_2`
- `listingFieldRegistry()` — 45 listing model entries (`listing.*` paths), `field_type='listing_model'`, `keyword_route_status='listing_native'`

`allCanonicalPaths()` only covers the FAQ registry. For listing model paths use `allListingFieldPaths()`.

`sample_question_2` and `field_type='faq'` are injected by `withSecondQuestions()` at `registry()` call time; they are NOT stored inline in the source entry arrays.

**Why:** Code reviewer required listing model fields separate from FAQ entries, and ≥2 sample questions per entry without rewriting 168 inline entries.

**How to apply:** When adding new FAQ entries to any of the base/addon registry methods, add the corresponding second question to `secondSampleQuestionsMap()`. When adding listing model fields, add to `listingFieldRegistry()` and verify they appear in both the context builder and response contract service.
