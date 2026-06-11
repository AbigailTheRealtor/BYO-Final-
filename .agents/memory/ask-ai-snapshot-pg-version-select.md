---
name: Ask AI snapshot PostgreSQL version-select pattern
description: PostgreSQL rejects lockForUpdate() combined with aggregate functions (max/count); also fails inside test savepoints. Use orderByDesc+value instead.
---

# Ask AI Snapshot — PostgreSQL Version-Select Pattern

## The Rule
Inside `DB::transaction()` or any PostgreSQL savepoint, never combine `lockForUpdate()` with aggregate functions (`max()`, `count()`, etc.). Use `->orderByDesc('version')->value('version') ?? 0` to get the latest version with no lock.

## Why
PostgreSQL returns `SQLSTATE[0A000]: FOR UPDATE is not allowed with aggregate functions` for any query that combines `SELECT MAX(...) FOR UPDATE`. This also surfaces in tests because Laravel's `RefreshDatabase` wraps each test in an outer transaction, and nested `DB::transaction()` calls become SAVEPOINTs — `FOR UPDATE` inside a savepoint can also trigger similar errors in some PostgreSQL versions.

## How to Apply
In `AskAiKnowledgeSnapshotBuilderService::build()` and `persistFailure()`:
```php
// WRONG (PostgreSQL error):
->lockForUpdate()->max('version') ?? 0

// CORRECT:
->orderByDesc('version')->value('version') ?? 0
```
The `DB::transaction()` boundary provides sufficient concurrency protection for the use case (single-process listing saves). The advisory lock was unnecessary.

## Test Resilience Note
`RefreshDatabase` does NOT reset DB state between individual test methods in the same class — it wraps the whole class in a single transaction. Tests that assert absolute version numbers (e.g. `assertEquals(1, $v1->version)`) are fragile. Use unique high-range listing IDs per test (e.g. 420001, 550001) and assert relative ordering (`v2 == v1 + 1`) instead.
