---
name: AskAi context builder test mock patterns
description: Correct class namespaces and mock patterns for AskAiContextBuilderService unit tests
---

## Correct class namespaces (confirmed by grep)
- `PropertyIntelligenceProfileService` → `App\Services\Dna\PropertyIntelligenceProfileService`
- `LocationDnaIntelligenceContextService` → `App\Services\LocationDna\LocationDnaIntelligenceContextService`
- `LocationDnaMarketingContextService` → `App\Services\LocationDna\LocationDnaMarketingContextService`

## buildPayloadReadOnly() return type
`PropertyIntelligenceProfileService::buildPayloadReadOnly()` has a non-nullable return type of `array`. PHPUnit throws `IncompatibleReturnValueException` if you use `->willReturn(null)`. Either:
- Omit the stub entirely (mock's default for `array` return is `[]`)
- Or `->willReturn(['success' => false, 'status' => 'not_found'])`

## MlsFieldMap role methods are private
`MlsFieldMap::landlord()`, `MlsFieldMap::seller()`, etc. are all `private static`. Tests must call the public gateway: `MlsFieldMap::forRole('landlord')`.

**Why:** Calling private methods from tests throws `Call to private method` errors that are easy to miss when writing new tests from scratch.

**How to apply:** In any new MLS field map test, always use `MlsFieldMap::forRole($role)` not `MlsFieldMap::$role()`.
