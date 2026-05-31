---
name: PostgreSQL Gate resolver pattern
description: Why raw DB::table() must be used instead of Eloquent models inside Gate callbacks, and the tenant_criteria_auctions guard.
---

# PostgreSQL Gate resolver — raw DB over Eloquent

## The rule
Inside `Gate::define()` callbacks (and any code that runs mid-request before a log write), use `DB::table(...)` raw queries instead of Eloquent models to look up ownership or other DB facts.

**Why:** Eloquent models with `$with` (eager loading) or `$appends` (accessors) may trigger additional queries internally. If any of those queries fail (e.g. table missing, column missing, constraint), PostgreSQL marks the **entire active transaction as aborted**. Even though PHP's try/catch catches the exception, the PG connection remains in an aborted state, and every subsequent query on that connection — including the middleware's audit log INSERT — fails with `SQLSTATE[25P02]: current transaction is aborted`.

**How to apply:** In `resolveConsumerOwnerUserId()` (AuthServiceProvider.php) and similar helpers that run inside Gate inspections:
```php
$row = DB::table('buyer_criteria_auctions')
    ->select('user_id')
    ->where('id', $id)
    ->first();
return ($row && $row->user_id) ? (int) $row->user_id : null;
```

## tenant_criteria_auctions guard
The `tenant_criteria_auctions` table does not exist in the current dev/test database even though the `TenantCriteriaAuction` Eloquent model does. Querying a non-existent table aborts the PG transaction (same problem above).

Guard pattern:
```php
if (!Schema::hasTable('tenant_criteria_auctions')) {
    return null;
}
```

Two tests in `ConsumerCompatibilityBetaTest` (§12 and §12b) call `markTestSkipped()` when the table is absent. When the table is eventually created, remove the guard and re-enable those tests.
