---
name: Ask AI three-file coordination rule
description: Adding a new listing.* field to Ask AI requires changes in exactly three files; missing any one causes a silent routing failure.
---

# Ask AI three-file coordination rule

When a new `listing.*` field is added to Ask AI, all three of the following files **must** be updated together:

1. **`AskAiContextBuilderService.php`** — `extractFactualFields()` (or the role-specific block) must read the field from EAV and expose it as `ctx['listing'][key]`. If the EAV meta key name differs from the canonical field name, add an explicit alias.

2. **`AskAiRunnerV2Service.php`** — `LISTING_KEY_KEYWORD_MAP` must have a `'listing.{key}'` entry with ≥2 natural-language phrases. Also add a `deriveFieldLabel` entry or the Guard B response falls back to the generic "Information has not been provided" message.

3. **`AskAiQuestionClassifierService.php`** — the `listing_facts` keyword array must include at least one phrase that will match real user questions about the field; otherwise the classifier returns `'unsupported'` and bypasses the deterministic Guard B path entirely.

**Why:** The three files form a pipeline — classify → detect → read context. If any stage is missing, the question silently falls through to the normalizer/OpenAI path. No single file cross-checks the others.

**How to apply:** Before closing a PR that adds a new listing field to the Ask AI context, grep for the canonical key name in all three files. The root-cause audit found 9 failures that all reduced to one or more of these three stages being incomplete.
