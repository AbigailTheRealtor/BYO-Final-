---
name: SQLite :memory: test pattern — DatabaseTransactions vs RefreshDatabase
description: RefreshDatabase with SQLite :memory: has no per-method rollback; use DatabaseTransactions; never use expectException for DB constraint violations.
---

# SQLite :memory: Test Pattern

## The Rule
Feature tests in this project must use `DatabaseTransactions`, not `RefreshDatabase`, when running on SQLite `:memory:`.

## Why
`RefreshDatabase` detects `:memory:` and calls `refreshInMemoryDatabase()` which only runs `artisan migrate` once — it does NOT call `beginDatabaseTransaction()`. This means data written in one test METHOD persists into the next method. Listing IDs that look unique per-method will collide on the second method that uses the same ID.

`DatabaseTransactions` wraps each method in a transaction that rolls back at the end, giving clean per-method isolation on SQLite.

## How to Apply
```php
// WRONG (no per-method rollback on :memory:):
use Illuminate\Foundation\Testing\RefreshDatabase;

// CORRECT:
use Illuminate\Foundation\Testing\DatabaseTransactions;
```

The base `TestCase` already handles schema building: it runs `artisan migrate` once when the first `DatabaseTransactions` test class runs (via `$sqliteSchemaBuilt` static flag). All subsequent test classes share the same schema.

## expectException and DB Constraint Violations
**Never use `$this->expectException(QueryException::class)` for DB constraint violation tests.** On PostgreSQL, a constraint violation INSIDE a test aborts the outer DatabaseTransactions transaction, making all subsequent DB queries fail. On SQLite this is less severe but still bad practice.

**Instead use try/catch:**
```php
$threw = false;
try {
    Model::create([...duplicate...]);
} catch (QueryException $e) {
    $threw = true;
    $this->assertStringContainsString('unique', $e->getMessage());
}
$this->assertTrue($threw, 'Expected unique constraint violation');
```

## Artisan Command Output Testing
`PendingCommand::expectsOutputToContain()` does not exist in Laravel 8.x. Use:
```php
$exitCode = Artisan::call('my:command');
$output   = Artisan::output();
$this->assertEquals(0, $exitCode);
$this->assertStringContainsString('Expected text', $output);
```
