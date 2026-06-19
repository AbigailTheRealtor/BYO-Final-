---
name: AskAiRunnerV2 test makeMocks required stubs
description: finalBuilder mock must stub coerceToContractStatus + contractFormOf or runner returns success=false at the final return path.
---

## Rule

`makeMocks()` in `AskAiRunnerV2ServiceTest` must always stub these two methods on the `finalBuilder` mock:

```php
$finalBuilderMock->method('coerceToContractStatus')->willReturnArgument(0);
$finalBuilderMock->method('contractFormOf')->willReturn('direct_fact');
```

**Why:** The runner's final return path calls both unconditionally on every non-short-circuit path:
1. `$this->finalResponseBuilder->contractFormOf($finalResponse, ...)` — unstubbed PHPUnit mock returns `null`; harmless but incorrect trace.
2. `$finalResponse = $this->finalResponseBuilder->coerceToContractStatus($finalResponse)` — unstubbed mock returns `null`; then `$finalResponse['success'] ?? false` evaluates to `false`, causing every happy-path test that reaches the final return to report `success=false` even though the adapter and builder both returned correct results.

**How to apply:** Both stubs belong in `makeMocks()` so they apply globally to all 145 runner unit tests. Do not add them per-test — they are safe defaults (pass-through + constant) for every code path.

**Fixed tests:** S1 (agent_profile happy path), and keeps all other happy-path tests healthy when the runner gains new final-path logic.
