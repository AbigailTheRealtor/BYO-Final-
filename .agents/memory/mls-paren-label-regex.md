---
name: MLS regex parenthesized label form
description: How to write MLS parser patterns that also match parenthesized label suffixes like "(Months)" or "(Sq Ft)"
---

## Rule
When a MLS label has an optional parenthesized qualifier (e.g. "Minimum Lease (Months):"), use an alternation inside the optional group:

```php
/Minimum\s+Lease(?:\s*\(Months?\)|\s+(?:Term|Months?))?[\s:]+(\d+)/i
```

NOT:
```php
/Minimum\s+Lease(?:\s+(?:Term|Months?))?[\s:\(]+(\d+)/i   // WRONG
```

The second form attempts to match the `(` as part of the separator `[\s:\(]+`, but it will also match embedded `(` in values unintentionally, and more importantly it doesn't consume the full `(Months)` token before the `:`, causing the capture to miss.

**Why:** The first failure mode ("Minimum Lease (Months): 6" not captured) revealed this when the parenthesized form was added to the round-trip test.

**How to apply:** Any MLS label pattern with a `(...)` optional qualifier (like "Office Area (Sq Ft):") should use `\s*\(QualifierWords?\)` as one of the alternation arms in the optional non-capturing group, separate from the `: ` separator.
