---
name: Laravel flash + assertSessionHas in tests
description: assertSessionHas() fails on flash data because the session is aged before the assertion runs; use _flash.old check or business-outcome assertions.
---

# Problem
`$response->assertSessionHas('error', 'some message')` returns null even when the redirect **does** carry the flash. By the time `assertSessionHas` runs, `StartSession` middleware has called `ageFlashData()`, which moves the key from `_flash.new` to `_flash.old` and removes the top-level value.

**Why:** Laravel's `StartSession` middleware ages flash inside the same request cycle that produced the response. The test framework checks `$this->app['session.store']` post-aging.

**How to apply:**
- For flash *existence* checks, inspect `_flash.old`:
  ```php
  $flashOld = $response->baseResponse->getSession()->get('_flash.old', []);
  $this->assertContains('success', $flashOld);
  ```
- Better: assert the **business outcome** (model status, DB row) instead of the flash message. Flash messages are an implementation detail; status/meta changes are the contract.
