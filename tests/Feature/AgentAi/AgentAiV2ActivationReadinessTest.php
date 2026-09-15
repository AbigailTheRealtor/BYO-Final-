<?php

namespace Tests\Feature\AgentAi;

use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Services\AgentAi\Loaders\ExtendedKnowledgeLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AgentAiV2ActivationReadinessTest — P0.1 defect 6.
 *
 * Every Agent AI V2 scope stays OFF, and the prerequisite for turning one on is enumerated
 * here rather than remembered. Follows the OvertureActivationReadinessTest pattern.
 *
 * ==========================================================================================
 * THE PREREQUISITE
 * ==========================================================================================
 * `ExtendedKnowledgeLoader::loadSnapshotFacts()` is the only consumer anywhere in the
 * application that reads the STORED visibility flags on snapshot rows
 * (`public_allowed = true AND visibility = 'public_allowed'`). Every other reader —
 * AskAiKnowledgeSearchService, the analytics controller — gates on `restricted`.
 *
 * Snapshot rows written before P0 carry the OLD DEFAULT-OPEN classification:
 * `public_allowed = true` for every key that was not on the restricted list, which includes
 * buyer and tenant criteria and the seller's own minimum cap rate and minimum net income.
 * P0 fixed the writer; it did not migrate or backfill a single existing row, deliberately.
 *
 * So today the stale flags are inert: the only thing that would read them sits behind
 * CheckAgentAiV2Enabled, which `abort(404)`s while the global flag and all five per-scope
 * flags are false. Enabling ANY ONE of them opens `/agent-ai/ask` — a route that carries no
 * auth middleware — onto those stale rows, and the pre-P0 wide fact set becomes publicly
 * readable again with no code change and no diff to review.
 *
 * **A SNAPSHOT REBUILD UNDER THE NEW VISIBILITY RULES IS A REQUIRED PREREQUISITE BEFORE ANY
 * AGENT AI V2 SCOPE IS ENABLED.** Rebuilding is not part of P0.1. The full sequence is
 * recorded in docs/ask-ai-agent-ai-v2-activation-prerequisites.md.
 *
 * A FAILURE HERE IS NOT NECESSARILY A BUG. If activation is genuinely intended, the
 * assertion that fails is what should be updated — deliberately, in the same change that
 * satisfies the obligation it names, and only after the rebuild has run.
 */
class AgentAiV2ActivationReadinessTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The five per-scope flags CheckAgentAiV2Enabled consults, plus the global switch.
     * Any one of them being true makes the V2 routes reachable.
     */
    private const V2_FLAG_KEYS = [
        'agent_ai_v2_enabled',
        'agent_ai_v2_seller_enabled',
        'agent_ai_v2_landlord_enabled',
        'agent_ai_v2_buyer_enabled',
        'agent_ai_v2_tenant_enabled',
        'agent_ai_v2_agent_profile_enabled',
    ];

    /** @test */
    public function every_agent_ai_v2_flag_defaults_off(): void
    {
        // Read the config FILE rather than the resolved value, so an env var set in this
        // environment cannot make the assertion pass.
        $config = require base_path('config/ask_ai.php');

        foreach (self::V2_FLAG_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $config,
                "config/ask_ai.php no longer declares '{$key}' — CheckAgentAiV2Enabled reads it, "
                . 'and a missing key would silently read as false or as something else entirely.'
            );

            $this->assertFalse(
                (bool) $config[$key],
                "'{$key}' is enabled. Agent AI V2 must not be reachable until existing knowledge "
                . 'snapshots have been rebuilt under the P0 visibility rules — stale rows still '
                . 'carry public_allowed = true for buyer/tenant criteria and seller minimums. '
                . 'See docs/ask-ai-agent-ai-v2-activation-prerequisites.md.'
            );
        }
    }

    /** @test */
    public function the_v2_routes_are_unreachable_with_the_shipped_defaults(): void
    {
        // The executable form of the same statement: with nothing overridden, the doors are
        // shut. This is what makes the stale flags inert today.
        foreach (self::V2_FLAG_KEYS as $key) {
            config(["ask_ai.{$key}" => false]);
        }

        $this->postJson('/agent-ai/ask', [])->assertStatus(404);
        $this->postJson('/agent-ai/session/start', [])->assertStatus(404);
        $this->postJson('/agent-ai/escalate', [])->assertStatus(404);
    }

    /** @test */
    public function enabling_a_single_scope_is_sufficient_to_open_the_routes(): void
    {
        // Not a recommendation — the reason the guard above matters. One flag is the whole
        // distance between "inert" and "publicly readable", and the route has no auth.
        foreach (self::V2_FLAG_KEYS as $key) {
            foreach (self::V2_FLAG_KEYS as $reset) {
                config(["ask_ai.{$reset}" => false]);
            }
            config(["ask_ai.{$key}" => true]);

            $this->assertNotSame(
                404,
                $this->postJson('/agent-ai/ask', [])->getStatusCode(),
                "Enabling only '{$key}' already makes /agent-ai/ask reachable, which is why "
                . 'every one of these flags is a snapshot-rebuild prerequisite, not just the '
                . 'global one.'
            );
        }
    }

    /** @test */
    public function the_v2_route_group_still_carries_no_auth_middleware(): void
    {
        // If this ever changes, the risk profile of the flags above changes with it and this
        // whole guard should be revisited. Pinned so the change cannot pass unnoticed.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'agent-ai/ask');

        $this->assertNotNull($route, 'The /agent-ai/ask route is no longer registered.');

        $middleware = $route->gatherMiddleware();

        $this->assertNotContains(
            'auth',
            $middleware,
            'POST /agent-ai/ask now has auth middleware. That is an improvement — update this '
            . 'assertion and re-assess the activation prerequisites, which assume the route is '
            . 'anonymous.'
        );
    }

    /** @test */
    public function a_stale_snapshot_row_is_still_readable_as_public_by_the_only_flag_reader(): void
    {
        // The concrete demonstration of what the rebuild is for. A row written the pre-P0 way
        // — public_allowed = true on a seller minimum — is served by ExtendedKnowledgeLoader,
        // because the loader reads what is STORED and P0 changed only what is written.
        //
        // This test asserts the CURRENT, UNSAFE state on purpose: it is the evidence that the
        // prerequisite is real rather than theoretical. When the rebuild has run and this
        // behaviour is no longer reachable, this assertion is the one to revisit.
        $snapshot = AskAiKnowledgeSnapshot::create([
            'listing_type'  => 'seller',
            'listing_id'    => 930101,
            'version'       => 1,
            'status'        => 'ready',
            'snapshot_uuid' => (string) Str::uuid(),
            'built_at'      => now(),
        ]);

        AskAiFact::create([
            'snapshot_id'    => $snapshot->id,
            'canonical_key'  => 'minimum_cap_rate',
            'value'          => '7.25',
            // Exactly what the pre-P0 default-open classifier wrote for this key.
            'visibility'     => 'public_allowed',
            'listing_type'   => 'seller',
            'listing_id'     => 930101,
            'label'          => 'Minimum Cap Rate',
            'value_type'     => 'numeric',
            'source_path'    => 'context.listing.minimum_cap_rate',
            'classification' => 'public_factual',
            'public_allowed' => true,
            'restricted'     => false,
            'sort_order'     => 0,
        ]);

        $fragment = (new ExtendedKnowledgeLoader())([
            'listing_type' => 'seller',
            'listing_id'   => 930101,
        ]);

        $this->assertNotNull($fragment);
        $this->assertArrayHasKey('snapshot_facts', $fragment['content']);
        $this->assertSame(
            '7.25',
            $fragment['content']['snapshot_facts']['minimum_cap_rate'] ?? null,
            'A stale pre-P0 row is still served as public by ExtendedKnowledgeLoader. This is '
            . 'the state the snapshot rebuild exists to clear, and the reason no Agent AI V2 '
            . 'scope may be enabled before it has run.'
        );
    }

    /** @test */
    public function a_rebuilt_snapshot_row_is_not_readable_as_public(): void
    {
        // The other half of the same demonstration: written under the P0 rules, the same key
        // is owner_only and the loader does not see it. That is what the rebuild achieves,
        // and asserting it here proves the rebuild is a sufficient remedy — not merely a
        // step someone has to trust.
        $snapshot = AskAiKnowledgeSnapshot::create([
            'listing_type'  => 'seller',
            'listing_id'    => 930102,
            'version'       => 1,
            'status'        => 'ready',
            'snapshot_uuid' => (string) Str::uuid(),
            'built_at'      => now(),
        ]);

        AskAiFact::create([
            'snapshot_id'    => $snapshot->id,
            'canonical_key'  => 'minimum_cap_rate',
            'value'          => '7.25',
            'visibility'     => 'owner_only',
            'listing_type'   => 'seller',
            'listing_id'     => 930102,
            'label'          => 'Minimum Cap Rate',
            'value_type'     => 'numeric',
            'source_path'    => 'context.listing.minimum_cap_rate',
            'classification' => 'owner_only',
            'public_allowed' => false,
            'restricted'     => false,
            'sort_order'     => 0,
        ]);

        $fragment = (new ExtendedKnowledgeLoader())([
            'listing_type' => 'seller',
            'listing_id'   => 930102,
        ]);

        // Either no fragment at all (every source empty) or a fragment without the key.
        $facts = $fragment['content']['snapshot_facts'] ?? [];

        $this->assertArrayNotHasKey(
            'minimum_cap_rate',
            $facts,
            'A row written under the P0 visibility rules must not be served as public.'
        );
    }
}
