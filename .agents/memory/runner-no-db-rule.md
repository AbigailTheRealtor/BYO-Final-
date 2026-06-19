---
name: AskAiRunnerV2 no-DB architectural rule
description: AskAiRunnerV2Service must not contain direct DB::table() calls; DB reads belong in AskAiListingDescriptionRepository.
---

## Rule

`AskAiRunnerV2Service` must not import or use `Illuminate\Support\Facades\DB` or call `DB::table(` directly. This is enforced by unit test `test_case_I_service_file_contains_no_db_facade_calls` which scans the file source for prohibited terms.

**Why:** The runner is a pure orchestrator. Direct DB calls make it impossible to mock database behaviour in unit tests, and violate the single-responsibility principle.

## Current structure

All listing description DB reads live in `App\Services\AskAi\AskAiListingDescriptionRepository::load(string $listingType, int $listingId): ?string`.

The runner receives it via constructor injection (optional parameter, null-defaulted, auto-instantiated):

```php
public function __construct(
    ...
    ?AskAiListingDescriptionRepository $descriptionRepository = null
) {
    ...
    $this->descriptionRepository = $descriptionRepository ?? new AskAiListingDescriptionRepository();
}
```

The runner delegates via:
```php
protected function loadListingDescription(string $listingType, int $listingId): ?string
{
    return $this->descriptionRepository->load($listingType, $listingId);
}
```

**How to apply:** Any future DB read needed by the runner must go into `AskAiListingDescriptionRepository` (or a new dedicated repo class), never directly in the runner file. The test_case_I scan will catch violations immediately.
