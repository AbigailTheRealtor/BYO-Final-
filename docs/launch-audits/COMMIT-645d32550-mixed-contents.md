# Commit `645d32550` — mixed contents (documented, intentionally NOT rewritten)

**Commit:** `645d32550` — `feat(ask-ai): rebuild Knowledge Base with two-axis gating and compliance guardrails`
**Branch:** `launch-audit-remediation`
**Parent:** `7cc1ee85b` (`fix(bya): block listing owner from bidding on own hire-agent listing`)

This commit contains **two distinct workstreams** that were merged accidentally when a
`git commit --amend` read the shared git index while a concurrent session had BYA-H3
files staged. After review, the decision (2026-07-01) was to **accept the mixed commit
as-is and NOT rewrite history**, because the 4 BYA-H3 files below are the **only committed
copy** of that work — a rewrite would risk losing or duplicating it. Nothing is broken or
half-applied; the only issue is attribution.

## 1. Ask AI KB rebuild (22 files — the intended commit)

Controllers: `AiKnowledgeController.php`, `AskAi/AskAiApiController.php`,
`AskAiListingQuestionController.php`, `TenantCriteriaAuctionController.php`.
Services: `AskAiComplianceGuardrailService.php`, `AskAiViewerAuthorizationService.php`,
`AskAiFaqConfigService.php`, `AskAiFaqEnrichmentService.php`,
`AskAiFinalResponseBuilderService.php`, `AskAiIntentNormalizerService.php`,
`AskAiInternalRunnerService.php`, `AskAiPromptBuilderService.php`.
Config: `ai_faq_seller.php`, `ai_faq_buyer.php`, `ai_faq_landlord.php`, `tenant_ai_faq.php`.
Views: `shared/ai-questions-input.blade.php`, `shared/partials/ai-question-field.blade.php`.
Tests: `AskAiKnowledgeBaseRenderTest.php`, `AskAiComplianceGuardrailServiceTest.php`,
`AskAiFaqEnrichmentServiceTest.php`, `AskAiViewerAuthorizationServiceTest.php`.

Phases A (guardrails), B (two-axis gating), C (content) per
`docs/ask-ai-kb-replacement-spec.md`.

## 2. BYA-H3 duplicate-bid guard follow-up (4 files — accidentally included)

- `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`
- `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`
- `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`
- `tests/Feature/ByaDuplicateBidGuardTest.php` (new)

BYA-H3 (Rule D2): one active bid per agent per hire-agent listing. A repeat (non-edit)
submit updates the existing bid in place instead of inserting a duplicate row (mirrors
Landlord, which already had the safeguard). This is a **separate, complete BYA-H3
increment** distinct from the self-bid guard already committed in `7cc1ee85b`. It should
have been its own commit; it belongs to the BYA-H3 workstream, not the Ask AI KB rebuild.

## Do NOT
Reset, rebase, amend, force-push, or drop stashes touching `645d32550` without
coordinating with the session that owns BYA-H3 — those 4 files are the sole committed
copy of the duplicate-bid guard.
