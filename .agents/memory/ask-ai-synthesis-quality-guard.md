---
name: Ask AI synthesis quality guard — heuristic 4 and prompt hardening
description: How raw field echoes are detected and rewritten; why terminal punctuation is the key signal; which fallback paths bypass the guard and how they were patched.
---

## The rule
`isResponseDegraded()` heuristic 4 (added): any extracted answer that lacks terminal punctuation (`.`, `!`, or `?`) is treated as degraded and triggers the one-shot quality rewrite call.

```php
if (!preg_match('/[.!?]["\')»]?\s*$/', $trimmed)) {
    return true;
}
```

**Why:** Raw field echoes like `"Central Air, Mini-Split Unit(s)"`, `"Concrete Block, Stucco"`, `"2017"`, `"Pool, Spa, Tennis Court"` all lack terminal punctuation. Properly composed OpenAI prose answers always end with a period. This is a reliable, zero-false-positive signal.

**How to apply:** The heuristic lives in `AskAiFinalResponseBuilderService::isResponseDegraded()`. It fires automatically after every main-path OpenAI call (the quality rewrite at line ~3830 of AskAiRunnerV2Service).

## Patched bypass paths
Two adapter-failed direct-return paths previously returned raw values and bypassed the quality guard entirely:

1. **FAQ direct-return fallback** (~line 3466 of AskAiRunnerV2Service): When the main OpenAI call fails and the FAQ field has a stored value, the raw `faqText` was returned directly. Now: if `isResponseDegraded($faqText)` is true, a rewrite call is attempted; if the rewrite also fails, the raw text is returned as a last resort.

2. **listing.* direct-return fallback** (~line 3552): Same pattern for listing field values. Now attempts rewrite before returning raw `(string) $listingFieldValue`.

## Prompt hardening (co-deployed)
- `SYNTHESIS_DIRECTIVES` for `_default` and `listing_facts`: added explicit "never echo raw comma-separated values" instruction with example, and "always end with terminal punctuation" requirement.
- `SYSTEM_INSTRUCTIONS` last entry: strengthened to include comma-separated-list prohibition and a concrete example.
- `buildQualityRewritePackage()`: added list-to-prose conversion example and explicit punctuation requirement.
- `buildDescriptionFallbackPackage()`: added "compose as complete sentence ending with period" instruction.
