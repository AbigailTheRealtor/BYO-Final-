<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;

/**
 * AgentAiContextSourceRegistry
 *
 * Pluggable registry of context loaders for the Agent AI V2 pipeline.
 *
 * Each loader is a callable registered against one or more scopes with a
 * priority value. When the pipeline assembles context for a given scope,
 * it calls `loadersForScope()` to retrieve all loaders sorted by priority
 * (ascending — lower numbers run first), then invokes each callable.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * LOADER RETURN CONTRACT  (Build 2 implementers must satisfy this shape)
 * ──────────────────────────────────────────────────────────────────────────
 * Every callable registered via `register()` MUST return an associative
 * array matching the following shape:
 *
 *   [
 *     'source_key'     => string,   // Unique, human-readable identifier for
 *                                   // this context fragment (e.g. 'listing_core',
 *                                   // 'faq_answers', 'agent_profile').
 *                                   // Used for logging, caching keys, and
 *                                   // missing-source diagnostics.
 *
 *     'priority'       => int,      // The priority this loader was registered
 *                                   // with. Echo back the registered value so
 *                                   // the pipeline can log it alongside the
 *                                   // assembled context.
 *
 *     'content'        => array,    // Structured context data for this source.
 *                                   // Must contain only public-safe fields.
 *                                   // Private fields (user_id, compensation,
 *                                   // accepted-bid data) are NEVER included.
 *                                   // Keys should be snake_case strings.
 *
 *     'token_estimate' => int,      // Approximate token count for 'content'
 *                                   // after JSON serialization. Used by the
 *                                   // token-budget enforcer in
 *                                   // AgentAiContextBuilder to decide whether
 *                                   // to include or truncate this fragment.
 *                                   // Estimate using: strlen(json_encode($content)) / 4
 *   ]
 *
 * A loader MAY return null to signal that the source is not available for
 * the given listing/scope (e.g. no DNA profile exists yet). The pipeline
 * treats null returns as a missing-but-non-fatal source and logs a warning.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * GOVERNANCE:
 *   Loaders MUST be read-only. No DB writes, no external HTTP calls inside
 *   a loader. Loaders are called during a read-path; any write or side-effect
 *   will be a governance violation.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Build 1: Registry skeleton only — no loaders registered here.
 * Build 2: Concrete loaders (listing core, FAQ, agent profile, etc.) will be
 *          registered via AgentAiServiceProvider after this registry is bound.
 */
class AgentAiContextSourceRegistry
{
    /**
     * @var array<int, array{key: string, scopes: AgentAiContextScope[], priority: int, loader: callable}>
     */
    private array $entries = [];

    /**
     * Register a context loader for one or more scopes.
     *
     * @param  string                $key      Unique source identifier (e.g. 'listing_core').
     * @param  AgentAiContextScope[] $scopes   Scopes this loader applies to.
     * @param  int                   $priority Lower numbers run first (e.g. 10 = high priority, 100 = low).
     * @param  callable              $loader   Callable that accepts ($scopeContext: array) and returns the
     *                                         loader return contract array (see class docblock) or null.
     */
    public function register(string $key, array $scopes, int $priority, callable $loader): void
    {
        $this->entries[] = [
            'key'      => $key,
            'scopes'   => $scopes,
            'priority' => $priority,
            'loader'   => $loader,
        ];
    }

    /**
     * Return all loaders registered for the given scope, sorted by priority ascending.
     *
     * @param  AgentAiContextScope $scope
     * @return array<int, array{key: string, priority: int, loader: callable}>
     */
    public function loadersForScope(AgentAiContextScope $scope): array
    {
        $matching = array_filter($this->entries, function (array $entry) use ($scope): bool {
            foreach ($entry['scopes'] as $registeredScope) {
                if ($registeredScope === $scope) {
                    return true;
                }
            }
            return false;
        });

        usort($matching, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_values(array_map(fn (array $entry): array => [
            'key'      => $entry['key'],
            'priority' => $entry['priority'],
            'loader'   => $entry['loader'],
        ], $matching));
    }
}
