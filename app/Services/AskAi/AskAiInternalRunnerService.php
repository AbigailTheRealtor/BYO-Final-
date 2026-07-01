<?php

namespace App\Services\AskAi;

/**
 * AskAiInternalRunnerService — Phase 1–3 Orchestration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Orchestration runner for Ask AI Phases 1–3.
 * Chains AskAiContextBuilderService, AskAiResponseContractService, and
 * AskAiPromptBuilderService in sequence and returns a single structured result.
 * This service holds no business logic of its own — it only delegates and aggregates.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Generate any AI answer text or call OpenAI.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 * ==================================================================================
 */
class AskAiInternalRunnerService
{
    private AskAiContextBuilderService $contextBuilder;
    private AskAiResponseContractService $contractService;
    private AskAiPromptBuilderService $promptBuilder;
    private AskAiViewerAuthorizationService $viewerAuth;

    public function __construct(
        AskAiContextBuilderService $contextBuilder,
        AskAiResponseContractService $contractService,
        AskAiPromptBuilderService $promptBuilder,
        ?AskAiViewerAuthorizationService $viewerAuth = null
    ) {
        $this->contextBuilder  = $contextBuilder;
        $this->contractService = $contractService;
        $this->promptBuilder   = $promptBuilder;
        $this->viewerAuth      = $viewerAuth ?? new AskAiViewerAuthorizationService();
    }

    /**
     * Run the Phase 1–3 Ask AI pipeline in sequence.
     *
     * Output contract — always returns exactly these six keys:
     *   success        bool        — true only when prompt_package['status'] === 'prompt_ready'
     *   status         string      — mirrors prompt_package['status'], or 'failed' on exception
     *   context        array|null  — output of AskAiContextBuilderService::buildForListing()
     *   contract       array|null  — output of AskAiResponseContractService::buildContract()
     *   prompt_package array|null  — output of AskAiPromptBuilderService::buildPromptPackage()
     *   error          string|null — null on all non-exception paths; error message on \Throwable
     *
     * @param  string  $listingType   Canonical or aliased listing type string.
     * @param  int     $listingId     Primary key of the listing record.
     * @param  string  $questionType  One of the defined Ask AI question types.
     * @param  string  $userQuestion  The user's question text.
     * @param  array   $options       Optional options. Recognized keys:
     *                                  'normalized_field_key' (string) — when present and the contract
     *                                  is contract_ready, narrows allowed_context to only that canonical
     *                                  path, ensuring the prompt package is grounded in exactly the right
     *                                  field. Only applied if the key exists in the contract's allowed_context.
     *                                  Compatibility pair keys (demand_listing_type, demand_listing_id,
     *                                  supply_listing_type, supply_listing_id) are forwarded to
     *                                  buildForListing() unchanged.
     * @return array
     */
    public function run(
        string $listingType,
        int $listingId,
        string $questionType,
        string $userQuestion,
        array $options = []
    ): array {
        try {
            $context = $this->contextBuilder->buildForListing($listingType, $listingId, $options);

            // Part J / C-B — redact confidential applicant fields per the requester's
            // authorization scope BEFORE the context reaches the contract/prompt/model
            // layers. Scope is resolved upstream (controller) and passed in $options;
            // when absent we fail closed to 'public' (most-restrictive).
            $viewerScope = $options['viewer_scope'] ?? AskAiViewerAuthorizationService::SCOPE_PUBLIC;
            $context     = $this->viewerAuth->redactContext($context, $listingType, $viewerScope);

            $contract = $this->contractService->buildContract($questionType, $context);

            // ----------------------------------------------------------------
            // Intent normalization narrowing (optional).
            // When AskAiRunnerV2Service resolves an ambiguous 'unsupported'
            // question to a specific canonical field key via OpenAI, it injects
            // 'normalized_field_key' into $options. If the key is permitted by
            // the contract's allowed_context, narrow allowed_context to only
            // that path so the prompt package is grounded in exactly the right
            // field — not the full listing_facts context.
            // Applied only on contract_ready; blocked/insufficient paths are
            // untouched.
            //
            // Permitted means either:
            //   • Exact match  — the leaf key is explicitly listed (e.g. 'listing.bedrooms').
            //   • Prefix match — an umbrella entry covers it (e.g. 'faq_answers' covers all
            //                    'faq_answers.*' leaf paths). The listing_facts contract uses
            //                    'faq_answers' as a bare umbrella; the normalizer resolves to
            //                    specific leaves like 'faq_answers.hvac_system_age'.
            // ----------------------------------------------------------------
            $normalizedFieldKey = $options['normalized_field_key'] ?? null;

            if (
                $normalizedFieldKey !== null
                && ($contract['status'] ?? '') === 'contract_ready'
                && $this->normalizedKeyIsPermitted($normalizedFieldKey, $contract['allowed_context'] ?? [])
            ) {
                $contract['allowed_context'] = [$normalizedFieldKey];
            }

            $promptPackage = $this->promptBuilder->buildPromptPackage($userQuestion, $context, $contract);

            $status  = $promptPackage['status'] ?? 'failed';
            $success = ($status === 'prompt_ready');

            return [
                'success'        => $success,
                'status'         => $status,
                'context'        => $context,
                'contract'       => $contract,
                'prompt_package' => $promptPackage,
                'error'          => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success'        => false,
                'status'         => 'failed',
                'context'        => null,
                'contract'       => null,
                'prompt_package' => null,
                'error'          => $e->getMessage(),
            ];
        }
    }

    /**
     * Return true when the normalized field key is permitted by the contract's allowed_context.
     *
     * Two match modes are supported so that both listing.* and faq_answers.* normalizations work:
     *
     *   Exact match   — the leaf key is explicitly listed in allowed_context.
     *                   Example: 'listing.bedrooms' is listed → match.
     *
     *   Prefix match  — an umbrella entry in allowed_context covers the key's namespace.
     *                   Example: 'faq_answers' covers 'faq_answers.hvac_system_age' because
     *                   the key starts with 'faq_answers.'. The listing_facts contract intentionally
     *                   uses the bare 'faq_answers' umbrella; the normalizer resolves to specific
     *                   leaf paths. Without prefix matching, FAQ narrowing would never fire.
     *
     * @param  string   $key            The normalized field key (e.g. 'faq_answers.hvac_system_age').
     * @param  string[] $allowedContext  The contract's allowed_context array.
     * @return bool
     */
    private function normalizedKeyIsPermitted(string $key, array $allowedContext): bool
    {
        // Exact match — leaf path explicitly listed.
        if (in_array($key, $allowedContext, true)) {
            return true;
        }

        // Prefix match — umbrella entry (e.g. 'faq_answers') covers all 'faq_answers.*' leaves.
        foreach ($allowedContext as $entry) {
            if (str_starts_with($key, $entry . '.')) {
                return true;
            }
        }

        return false;
    }
}
