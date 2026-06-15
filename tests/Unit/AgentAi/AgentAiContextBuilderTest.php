<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AgentAi\AgentAiContextBuilder;
use App\Services\AgentAi\AgentAiContextSourceRegistry;
use App\Services\AgentAi\AgentAiPermissionGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentAiContextBuilderTest
 *
 * Verifies:
 *   (a) Token-budget truncation drops lowest-retention fragments first;
 *       listing_core is NEVER dropped even when over budget.
 *   (b) highest-retention fragments survive a budget cut longer than lowest.
 *   (c) Permission guard is called before any loader runs.
 *   (d) Assembled context_string contains listing_core content.
 */
class AgentAiContextBuilderTest extends TestCase
{
    use DatabaseTransactions;

    private function makeBuilder(AgentAiContextSourceRegistry $registry, ?AgentAiPermissionGuard $guard = null): AgentAiContextBuilder
    {
        return new AgentAiContextBuilder($registry, $guard);
    }

    private function makeRegistry(): AgentAiContextSourceRegistry
    {
        return new AgentAiContextSourceRegistry();
    }

    private function fragment(string $sourceKey, int $tokenEstimate): array
    {
        return [
            'source_key'     => $sourceKey,
            'priority'       => 60,
            'content'        => array_fill(0, $tokenEstimate, 'x'),
            'token_estimate' => $tokenEstimate,
            'public_allowed' => true,
            'role_scope'     => [],
            'cache_ttl'      => null,
            'loaded_at'      => now()->toISOString(),
        ];
    }

    public function test_token_budget_drops_lowest_retention_first(): void
    {
        $registry = $this->makeRegistry();

        // knowledge_snapshot (retention=1) is the lowest-retention key per audit Section 10.3.
        // Total = 2200+600+400+200 = 3400 — over the 3000-token seller budget.
        // knowledge_snapshot must be dropped first (retention=1 < extended_knowledge=4 < agent_profile=8).
        $registry->register(
            'knowledge_snapshot',
            [AgentAiContextScope::PublicListingSeller],
            10,
            fn ($ctx) => $this->fragment('knowledge_snapshot', 2200)
        );

        $registry->register(
            'extended_knowledge',
            [AgentAiContextScope::PublicListingSeller],
            20,
            fn ($ctx) => $this->fragment('extended_knowledge', 600)
        );

        $registry->register(
            'agent_profile',
            [AgentAiContextScope::PublicListingSeller],
            30,
            fn ($ctx) => $this->fragment('agent_profile', 400)
        );

        $registry->register(
            'listing_core',
            [AgentAiContextScope::PublicListingSeller],
            40,
            fn ($ctx) => $this->fragment('listing_core', 200)
        );

        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Budget Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $guard = new class extends AgentAiPermissionGuard {
            public function validateAgentScope($scope, $agentId, $listingType, $listingId): void {}
        };

        $builder = $this->makeBuilder($registry, $guard);

        $result = $builder->buildForScope(
            AgentAiContextScope::PublicListingSeller,
            $user->id,
            'seller',
            $listing->id
        );

        $survivingKeys = array_column($result['fragments'], 'source_key');
        $truncatedKeys = $result['truncated_sources'];

        $this->assertContains('listing_core', $survivingKeys,
            'listing_core must never be dropped.');

        $this->assertLessThanOrEqual(3000, $result['total_token_estimate'],
            'Surviving fragments must fit within the 3000-token seller scope budget.');

        $this->assertContains('knowledge_snapshot', $truncatedKeys,
            'knowledge_snapshot (retention=1) is the lowest-retention key and must be dropped first.');

        $this->assertNotContains('listing_core', $truncatedKeys,
            'listing_core must never appear in truncated_sources.');

        $this->assertNotContains('agent_profile', $truncatedKeys,
            'agent_profile (retention=8) must not be dropped before knowledge_snapshot (retention=1).');
    }

    public function test_listing_core_never_dropped_even_when_massively_over_budget(): void
    {
        $registry = $this->makeRegistry();

        $registry->register(
            'listing_core',
            [AgentAiContextScope::PublicListingSeller],
            100,
            fn ($ctx) => $this->fragment('listing_core', 500)
        );

        $registry->register(
            'extended_knowledge',
            [AgentAiContextScope::PublicListingSeller],
            60,
            fn ($ctx) => $this->fragment('extended_knowledge', 1000)
        );

        $registry->register(
            'agent_presets',
            [AgentAiContextScope::PublicListingSeller],
            70,
            fn ($ctx) => $this->fragment('agent_presets', 1000)
        );

        $registry->register(
            'agent_profile',
            [AgentAiContextScope::PublicListingSeller],
            80,
            fn ($ctx) => $this->fragment('agent_profile', 2000)
        );

        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Never Drop Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $guard = new class extends AgentAiPermissionGuard {
            public function validateAgentScope($scope, $agentId, $listingType, $listingId): void {}
        };

        $builder = $this->makeBuilder($registry, $guard);

        $result = $builder->buildForScope(
            AgentAiContextScope::PublicListingSeller,
            $user->id,
            'seller',
            $listing->id
        );

        $survivingKeys = array_column($result['fragments'], 'source_key');
        $this->assertContains('listing_core', $survivingKeys,
            'listing_core must be present in fragments regardless of budget pressure.');

        $truncated = $result['truncated_sources'];
        $this->assertNotContains('listing_core', $truncated,
            'listing_core must never appear in truncated_sources.');
    }

    public function test_high_retention_fragments_survive_longer_than_low_retention(): void
    {
        $registry = $this->makeRegistry();

        // Total = 2200+800+200 = 3200, which exceeds the 3000-token seller budget.
        // listing_description (retention=1) must be dropped first, leaving
        // 800+200 = 1000, which is within budget.
        $registry->register(
            'listing_description',
            [AgentAiContextScope::PublicListingSeller],
            10,
            fn ($ctx) => $this->fragment('listing_description', 2200)
        );

        $registry->register(
            'agent_profile',
            [AgentAiContextScope::PublicListingSeller],
            80,
            fn ($ctx) => $this->fragment('agent_profile', 800)
        );

        $registry->register(
            'listing_core',
            [AgentAiContextScope::PublicListingSeller],
            100,
            fn ($ctx) => $this->fragment('listing_core', 200)
        );

        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Retention Order Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $guard = new class extends AgentAiPermissionGuard {
            public function validateAgentScope($scope, $agentId, $listingType, $listingId): void {}
        };

        $builder = $this->makeBuilder($registry, $guard);

        $result = $builder->buildForScope(
            AgentAiContextScope::PublicListingSeller,
            $user->id,
            'seller',
            $listing->id
        );

        $survivingKeys = array_column($result['fragments'], 'source_key');
        $truncated     = $result['truncated_sources'];

        $this->assertContains('listing_description', $truncated,
            'listing_description (retention=1, 2000 tokens) must be dropped first to bring total under the 3000-token budget.');

        $this->assertContains('agent_profile', $survivingKeys,
            'agent_profile (retention=8, 600 tokens) must survive when listing_description is dropped to meet the budget.');

        $this->assertContains('listing_core', $survivingKeys,
            'listing_core (never-drop) must always survive.');
    }

    public function test_permission_guard_is_called_before_loaders(): void
    {
        $registry = $this->makeRegistry();

        $loaderCalled = false;

        $registry->register(
            'extended_knowledge',
            [AgentAiContextScope::PublicListingSeller],
            60,
            function ($ctx) use (&$loaderCalled) {
                $loaderCalled = true;
                return null;
            }
        );

        $throwingGuard = new class extends AgentAiPermissionGuard {
            public function validateAgentScope($scope, $agentId, $listingType, $listingId): void
            {
                throw new AgentAiPermissionException('Blocked', 'ownership_mismatch');
            }
        };

        $builder = $this->makeBuilder($registry, $throwingGuard);

        $thrown = false;
        try {
            $builder->buildForScope(
                AgentAiContextScope::PublicListingSeller,
                999,
                'seller',
                1
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'AgentAiPermissionException should have been thrown.');
        $this->assertFalse($loaderCalled, 'No loader should have been called when the permission guard throws.');
    }

    public function test_assembled_context_string_contains_listing_core_content(): void
    {
        $registry = $this->makeRegistry();

        $registry->register(
            'listing_core',
            [AgentAiContextScope::PublicListingSeller],
            100,
            fn ($ctx) => [
                'source_key'     => 'listing_core',
                'priority'       => 100,
                'content'        => ['listing_type' => 'seller', 'bedrooms' => '4', 'address' => '100 Test St'],
                'token_estimate' => 20,
                'public_allowed' => true,
                'role_scope'     => ['seller'],
                'cache_ttl'      => 300,
                'loaded_at'      => now()->toISOString(),
            ]
        );

        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Context String Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $guard = new class extends AgentAiPermissionGuard {
            public function validateAgentScope($scope, $agentId, $listingType, $listingId): void {}
        };

        $builder = $this->makeBuilder($registry, $guard);

        $result = $builder->buildForScope(
            AgentAiContextScope::PublicListingSeller,
            $user->id,
            'seller',
            $listing->id
        );

        $contextString = $result['context_string'];

        $this->assertStringContainsString('listing_type', $contextString);
        $this->assertStringContainsString('seller', $contextString);
        $this->assertStringContainsString('bedrooms', $contextString);
        $this->assertStringContainsString('4', $contextString);
    }

    public function test_under_budget_returns_all_fragments(): void
    {
        $registry = $this->makeRegistry();

        $registry->register(
            'agent_profile',
            [AgentAiContextScope::AgentProfile],
            80,
            fn ($ctx) => $this->fragment('agent_profile', 50)
        );

        $registry->register(
            'agent_presets',
            [AgentAiContextScope::AgentProfile],
            70,
            fn ($ctx) => $this->fragment('agent_presets', 50)
        );

        $user = User::factory()->create(['user_type' => 'agent']);

        $guard = new class extends AgentAiPermissionGuard {
            public function validateAgentScope($scope, $agentId, $listingType, $listingId): void {}
        };

        $builder = $this->makeBuilder($registry, $guard);

        $result = $builder->buildForScope(
            AgentAiContextScope::AgentProfile,
            $user->id,
            null,
            null
        );

        $this->assertEmpty($result['truncated_sources'],
            'No fragment should be truncated when well within budget.');
        $this->assertCount(2, $result['fragments']);
    }
}
