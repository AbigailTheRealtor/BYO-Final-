---
name: Ask AI four-file coordination rule
description: Adding a new listing.* field to Ask AI requires changes in exactly FOUR files; missing any one causes a silent routing failure or Guard B stripping the field before it can be checked.
---

# Ask AI Four-File Coordination Rule

When a new `listing.*` field is added to Ask AI, **all four** of the following files must be updated together:

1. **`AskAiContextBuilderService.php`** — `CANONICAL_SOURCE_MAP` (or role-specific block) must read the field from EAV and expose it as `ctx['listing'][key]`. If the EAV meta key name differs from the canonical context key, add an explicit alias. Example: tenant `commercial_lease_type_preference` EAV key → `commercial_lease_type` context key.

2. **`AskAiQuestionClassifierService.php`** — the `listing_facts` keyword array must include at least one phrase that will match real user questions about the field; otherwise the classifier returns `'unsupported'` and bypasses the deterministic Guard B path entirely.

3. **`AskAiRunnerV2Service.php`** — `LISTING_KEY_KEYWORD_MAP` must have a `'listing.{key}'` entry with ≥2 natural-language phrases, plus a `deriveFieldLabel` entry (or Guard B response falls back to generic "not provided" message).

4. **`AskAiResponseContractService.php`** — `'listing.{key}'` must appear in the `listing_facts` `allowed_context` array. `filterAllowedContext()` in the prompt builder strips any `listing.*` key not explicitly in this list **before** Guard B runs. Phase 2 fields added to files 1–3 were still returning `insufficient_context` until this fourth file was updated.

**Why:** The four files form a pipeline — classify → detect → filter → read context. If file 4 is missing, Guard B sees `$fieldAbsent = true` (the key was stripped from `$promptPackage['allowed_context']['listing']`), triggers the description fallback, and eventually returns `insufficient_context` even when real data exists in the DB. Listings with a description text show `adapter_success: true` (description fallback fires and hits OpenAI) while listings without description show `adapter_success: null` — both end in `insufficient_context`.

**How to apply:** Before closing any PR that adds a new listing field, grep for the canonical key name in all four files. The live integration audit (Phase 2) found this pattern affecting all 20 Phase 2 fields simultaneously.

## Role-aware key remaps in Guard B (Runner)

Some fields have different EAV key names across roles. These are handled via explicit remaps in the Guard B section of `AskAiRunnerV2Service.php` around line 3860:

| Detected key | Condition | Remapped to |
|---|---|---|
| `listing.pets_allowed` | landlord role | `listing.pet_policy` |
| `listing.heating_and_fuel` | landlord role | `listing.heating_fuel` |
| `listing.hoa_fee` | landlord role | `listing.association_fee_amount` |
| `listing.hoa_payment_schedule` | landlord role | `listing.association_fee_frequency` |
| `listing.available_date` + "earliest" | tenant role | `listing.move_in_date_earliest` |
| `listing.available_date` + "latest"/"last" | tenant role | `listing.move_in_date_latest` |

The tenant move-in date remap exists because `listing.available_date` has a broad `'move-in date'` phrase that matches before `listing.move_in_date_earliest`/`latest` entries (which appear later in the map). The remap restores correctness for tenant role without removing the landlord-useful phrase.
