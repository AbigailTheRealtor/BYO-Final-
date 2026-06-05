---
name: Ask AI test constructor drift
description: Pattern where Ask AI integration test files each have their own makeContextBuilder() factory that must be updated when AskAiContextBuilderService or AskAiPromptBuilderService gain new constructor dependencies.
---

# Ask AI Test Constructor Drift

## The Rule
When `AskAiContextBuilderService` or `AskAiPromptBuilderService` gain new constructor dependencies, **four integration test files** each have their own copy of the mock factory that must be updated. Failing to update all four causes runtime failures (not compile errors) — the tests silently compile but crash when instantiating the mock.

## Affected files (as of last update)
- `tests/Unit/Services/AskAi/AskAiAvatarIntegrationTest.php` — `makeContextBuilder()`
- `tests/Unit/Services/AskAi/AskAiCompatibilityIntegrationTest.php` — `makeContextBuilderService()`
- `tests/Unit/Services/AskAi/AskAiPropertyDnaIntegrationTest.php` — `makeContextService()`
- `tests/Unit/Services/AskAi/AskAiIntelligenceIntegrationSmokeTest.php` — `makeContextBuilder()` and `makePromptBuilder()`

**Why:** No shared base class or trait exists for these factories. Each test file owns its own copies.

## How to apply
Any time `AskAiContextBuilderService.__construct()` or `AskAiPromptBuilderService.__construct()` changes signature:
1. Update `AskAiContextBuilderServiceTest.makeService()` (canonical reference)
2. Update all four files above to match
3. A follow-up task (#2100) proposes consolidating into a shared `AskAiIntegrationTestCase` base class

## Constructor signatures (current)
- `AskAiContextBuilderService(PropertyIntelligenceProfileService, LocationDnaIntelligenceContextService, LocationDnaMarketingContextService)`
- `AskAiPromptBuilderService(AskAiKnowledgeSourceRegistry)`
