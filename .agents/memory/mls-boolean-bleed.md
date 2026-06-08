---
name: MLS parser boolean bleed fix
description: Why has_hoa / has_cdd patterns must use tight alternation, not open char classes, when boundary stop won't fire on partial words.
---

## Rule
MLS parser patterns that capture a boolean Yes/No field **must** use a tight alternation capture group:

```php
'/Association\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i'
'/CDD\s+Y\/N[\s:]+([Yy]es|[Nn]o|[YyNn])\b/i'
```

**Never** use an open char-class like `([^\|\n]{1,10})` for boolean fields even with `boundary=true`.

## Why
The boundary stop trims the captured value by searching for a label-stop word at a word boundary (`\b`). If the capture limit (e.g. 10 chars) is shorter than the next label word ("Association" = 11 chars), the stop word can only appear as a **partial prefix** inside the captured string ("Associ"), which `\b` won't match. The boundary fires on the whole word boundary, not on a prefix. Result: captured value is "Yes Associ" instead of "Yes".

## How to apply
- Any field whose valid values are a small fixed set (Yes/No, Y/N, True/False, 0/1) → use tight alternation in the capture group, omit boundary=true.
- Only use open char-class captures (`[^\|\n]{1,N}`) with `boundary=true` for free-text fields (names, descriptions, IDs) where the next label word is long enough to fully appear within the capture limit.
