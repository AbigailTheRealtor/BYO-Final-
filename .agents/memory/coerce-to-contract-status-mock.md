---
name: coerceToContractStatus mock pattern
description: How to correctly mock coerceToContractStatus() in runner unit tests to avoid PHP 8.2 ErrorException
---

# coerceToContractStatus PHPUnit Mock Pattern

## The Rule
Every runner unit test that creates a `AskAiFinalResponseBuilderService` mock MUST configure `coerceToContractStatus` explicitly — never leave it as a bare (unconfigured) mock.

```php
$finalBuilder = $this->createMock(AskAiFinalResponseBuilderService::class);
$finalBuilder->method('build')->willReturn([...]);
// REQUIRED — prevents PHP 8.2 ErrorException in runner's final return
$finalBuilder->method('coerceToContractStatus')->willReturnArgument(0);
```

**Why:** `coerceToContractStatus` has return type `array`. PHPUnit's default mock behavior for array-return methods is to return `[]` (the array zero-value). The runner accesses `$finalResponse['success']` and `$finalResponse['status']` after calling `coerceToContractStatus()`. When the mock returns `[]`, PHP 8.2 generates an "undefined array offset" warning, which Laravel's test error handler converts to an `ErrorException`. The runner's catch block then fires, returning `question_type=null` and `status='failed'` — silently masking the real routing result.

**How to apply:** Use `willReturnArgument(0)` for runner tests (pass-through; runner behavior tests don't test coercion). Use a full `willReturn([...])` array if the test specifically validates coerced fields. The runner itself also has defensive `?? false` / `?? 'failed'` on its final return as a belt-and-suspenders guard, but this alone is insufficient — it changes the status to 'failed' instead of preserving the mock's intended status.

## Affected test files (all patched)
- `tests/Unit/AskAi/AskAiPipelineTraceRoutingFixTest.php` — `makeRunner()` factory
- `tests/Unit/AskAi/AskAiRoutingRootCauseFixTest.php` — `makeStubRunner()` + 4 per-test mocks
- `tests/Unit/AskAi/AskAiRunnerV2DescriptionFallbackTraceTest.php` — both `makeRunner()` factories
- `tests/Unit/AskAi/AskAiRunnerV2DescriptionFallbackUnsupportedTest.php` — `makeStubRunner()`

## Related runner fix
`AskAiRunnerV2Service::run()` final return now uses:
```php
'success' => $finalResponse['success'] ?? false,
'status'  => $finalResponse['status'] ?? 'failed',
```
This prevents the throw but does not preserve the intended status — both the runner fix AND the mock configuration are needed together.
