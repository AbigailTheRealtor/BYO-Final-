<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use Illuminate\Support\Facades\Log;

/**
 * AgentAiContextBuilder
 *
 * Assembles the full context payload for a given scope by invoking registered
 * loaders from AgentAiContextSourceRegistry, sorting fragments by priority,
 * applying the token budget, and assembling the final compressed context string.
 *
 * GOVERNANCE: Read-only. No DB writes. No external HTTP calls.
 * Must never include private fields (user_id, compensation, accepted-bid data).
 */
class AgentAiContextBuilder
{
    /**
     * Token budget per scope (input tokens, not counting system instructions).
     * Source: docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md Section 10.3.
     */
    private const TOKEN_BUDGETS = [
        'public_listing_seller'   => 3000,
        'public_listing_landlord' => 3000,
        'buyer_criteria'          => 1500,
        'tenant_criteria'         => 1500,
        'agent_profile'           => 1000,
    ];

    /**
     * Retention priority for each source_key — lower number = drop first.
     * Source: audit Section 10.3 truncation order.
     *
     * Canonical order (lowest = dropped first under budget pressure):
     *   1 knowledge_snapshot   — AI-generated supplement, most expendable
     *   2 location_intelligence — enrichment layer, not agent-authored
     *   3 property_intelligence / avatars — computed profiles
     *   4 extended_knowledge   — FAQ supplement
     *   5 listing_description  — agent-authored, kept until budget is tight
     *   6 faq_answers          — curated Q&A, high value
     *   7 agent_presets        — agent preference data
     *   8 agent_profile        — identity anchor, second-to-last to drop
     *  ∞ listing_core          — NEVER dropped
     *
     * Never-drop keys ('listing_core') use PHP_INT_MAX.
     * Higher numbers survive the budget cut longer.
     */
    private const SOURCE_KEY_RETENTION = [
        'knowledge_snapshot'    => 1,
        'location_intelligence' => 2,
        'property_intelligence' => 3,
        'buyer_avatar'          => 3,
        'tenant_avatar'         => 3,
        'extended_knowledge'    => 4,
        'listing_description'   => 5,
        'faq_answers'           => 6,
        'agent_presets'         => 7,
        'agent_profile'         => 8,
        'listing_core'          => PHP_INT_MAX,
    ];

    public function __construct(
        private readonly AgentAiContextSourceRegistry $registry,
        private readonly ?AgentAiPermissionGuard $permissionGuard = null,
    ) {}

    /**
     * Build the assembled context payload for the given scope.
     *
     * @param  AgentAiContextScope $scope
     * @param  int                 $entityId   Listing or agent profile ID
     * @param  array               $options    Optional hints passed through to loaders
     * @return array{
     *   scope: string,
     *   entity_id: int,
     *   fragments: array,
     *   total_token_estimate: int,
     *   missing_sources: array,
     *   assembled_at: string,
     * }
     */
    public function build(AgentAiContextScope $scope, int $entityId, array $options = []): array
    {
        throw new \RuntimeException('Not implemented — use buildForScope() for V2 pipeline.');
    }

    /**
     * Build and assemble context for the V2 agent AI pipeline.
     *
     * Steps:
     *   1. Validate agent ownership via AgentAiPermissionGuard.
     *   2. Query registry for all loaders registered for this scope.
     *   3. Call each loader with the scope context array.
     *   4. Collect non-null fragments; record null returns as missing sources.
     *   5. Sort fragments by source_key retention priority (ascending = drop first).
     *   6. Apply token-budget truncation: drop lowest-retention fragments first
     *      until the total token estimate is within budget.
     *   7. Assemble and return the structured result.
     *
     * @param  AgentAiContextScope $scope
     * @param  int                 $agentId      ID of the agent making the request.
     * @param  string|null         $listingType  Canonical listing type ('seller', 'landlord', etc.)
     * @param  int|null            $listingId    Primary key of the listing record.
     * @return array{
     *   scope: string,
     *   agent_id: int,
     *   listing_type: string|null,
     *   listing_id: int|null,
     *   fragments: array,
     *   total_token_estimate: int,
     *   missing_sources: string[],
     *   truncated_sources: string[],
     *   assembled_at: string,
     *   context_string: string,
     * }
     * @throws AgentAiPermissionException  When agentId does not own the listing.
     */
    public function buildForScope(
        AgentAiContextScope $scope,
        int $agentId,
        ?string $listingType,
        ?int $listingId
    ): array {
        if ($this->permissionGuard !== null) {
            $this->permissionGuard->validateAgentScope($scope, $agentId, $listingType, $listingId);
        }

        $scopeContext = [
            'scope'        => $scope,
            'agent_id'     => $agentId,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
        ];

        $loaderEntries = $this->registry->loadersForScope($scope);

        $fragments      = [];
        $missingSources = [];

        foreach ($loaderEntries as $entry) {
            try {
                $fragment = ($entry['loader'])($scopeContext);
                if ($fragment === null) {
                    $missingSources[] = $entry['key'];
                } else {
                    $fragments[] = $fragment;
                }
            } catch (\Throwable $e) {
                $missingSources[] = $entry['key'];
                Log::warning('AgentAiContextBuilder: loader threw exception', [
                    'source_key'   => $entry['key'],
                    'scope'        => $scope->value,
                    'agent_id'     => $agentId,
                    'listing_id'   => $listingId,
                    'listing_type' => $listingType,
                    'error'        => $e->getMessage(),
                    'class'        => get_class($e),
                ]);
            }
        }

        $budget           = self::TOKEN_BUDGETS[$scope->value] ?? 2000;
        $truncatedSources = [];
        $fragments        = $this->applyTokenBudget($fragments, $budget, $truncatedSources);

        $totalTokens   = array_sum(array_column($fragments, 'token_estimate'));
        $contextString = $this->assembleContextString($fragments);

        return [
            'scope'             => $scope->value,
            'agent_id'          => $agentId,
            'listing_type'      => $listingType,
            'listing_id'        => $listingId,
            'fragments'         => $fragments,
            'total_token_estimate' => $totalTokens,
            'missing_sources'   => $missingSources,
            'truncated_sources' => $truncatedSources,
            'assembled_at'      => now()->toISOString(),
            'context_string'    => $contextString,
        ];
    }

    /**
     * Apply the token budget by dropping fragments with the lowest retention
     * priority first until the total token estimate is within budget.
     *
     * Algorithm:
     *   1. Separate never-drop fragments (source_key === 'listing_core') from candidates.
     *   2. Sort candidates ascending by SOURCE_KEY_RETENTION — lowest retention value
     *      at the front (these are dropped first).
     *   3. While over budget, shift() from the front (lowest-retention) and record as
     *      truncated. Highest-retention fragments remain at the back and survive.
     *   4. Return never-drops merged with surviving candidates.
     *
     * Fragments with source_key 'listing_core' are NEVER dropped regardless of budget.
     *
     * @param  array    $fragments         All collected fragments (unsorted).
     * @param  int      $budget            Maximum allowed token estimate.
     * @param  string[] &$truncatedSources Populated with dropped source_keys (out-param).
     * @return array                       Surviving fragments (never-drops + survivors).
     */
    private function applyTokenBudget(array $fragments, int $budget, array &$truncatedSources): array
    {
        $total = array_sum(array_column($fragments, 'token_estimate'));

        if ($total <= $budget) {
            return $fragments;
        }

        $neverDrop  = [];
        $candidates = [];

        foreach ($fragments as $fragment) {
            if ($fragment['source_key'] === 'listing_core') {
                $neverDrop[] = $fragment;
            } else {
                $candidates[] = $fragment;
            }
        }

        usort($candidates, function (array $a, array $b): int {
            $rA = self::SOURCE_KEY_RETENTION[$a['source_key']] ?? 5;
            $rB = self::SOURCE_KEY_RETENTION[$b['source_key']] ?? 5;
            return $rA <=> $rB;
        });

        while ($total > $budget && !empty($candidates)) {
            $dropped = array_shift($candidates);
            $total  -= ($dropped['token_estimate'] ?? 0);
            $truncatedSources[] = $dropped['source_key'];
        }

        return array_merge($neverDrop, $candidates);
    }

    /**
     * Assemble all surviving fragments into a compact key:value context string
     * for injection into the OpenAI prompt.
     *
     * Per audit Section 11.1 rule 1: serialize as compact key-value text rather
     * than JSON for ~30% token reduction.
     * Per audit Section 11.1 rule 2: null/empty fields are never included.
     * Per audit Section 11.1 rule 3: alias keys are deduplicated at assembly time.
     *
     * @param  array $fragments
     * @return string
     */
    private function assembleContextString(array $fragments): string
    {
        usort($fragments, fn (array $a, array $b): int => (
            (self::SOURCE_KEY_RETENTION[$b['source_key']] ?? 5)
            <=> (self::SOURCE_KEY_RETENTION[$a['source_key']] ?? 5)
        ));

        $seen    = [];
        $lines   = [];
        $section = null;

        foreach ($fragments as $fragment) {
            $sourceKey = $fragment['source_key'] ?? 'unknown';
            $content   = $fragment['content']    ?? [];

            if (empty($content)) {
                continue;
            }

            if ($sourceKey !== $section) {
                $section = $sourceKey;
                $label   = str_replace('_', ' ', strtoupper($sourceKey));
                $lines[] = "[{$label}]";
            }

            foreach ($content as $key => $value) {
                if ($value === null || $value === '' || $value === [] || $value === false) {
                    continue;
                }

                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                if (is_array($value)) {
                    $value = implode(', ', array_filter(array_map('strval', $value)));
                }

                $lines[] = "{$key}: {$value}";
            }
        }

        return implode("\n", $lines);
    }
}
