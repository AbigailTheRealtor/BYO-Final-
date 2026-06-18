---
name: EAV meta table column names
description: Actual column names for seller and landlord auction meta tables — needed for raw DB::table() queries in tests.
---

# EAV Meta Table Column Names

## seller_agent_auction_metas
Columns: `id`, `seller_agent_auction_id`, `meta_key`, `meta_value`, `created_at`, `updated_at`

## landlord_agent_auction_metas
Columns: `id`, `landlord_agent_auction_id`, `meta_key`, `meta_value`, `created_at`, `updated_at`

**Why this matters:** The columns are NOT named `key`/`value` as you might assume — they are `meta_key`/`meta_value`.  Tests that do raw `DB::table('..._metas')->where('key', ...)->value('value')` will get a PostgreSQL "column does not exist" error.

**How to apply:**
```php
// CORRECT
DB::table('seller_agent_auction_metas')
    ->where('seller_agent_auction_id', $id)
    ->where('meta_key', 'maximum_budget')
    ->value('meta_value');

// WRONG — PostgreSQL error: column "value" does not exist
DB::table('seller_agent_auction_metas')
    ->where('key', 'maximum_budget')
    ->value('value');
```
