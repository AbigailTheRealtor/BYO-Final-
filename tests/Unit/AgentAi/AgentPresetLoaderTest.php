<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentAi\Loaders\AgentPresetLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentPresetLoaderTest
 *
 * Verifies:
 *   (a) Only returns preset data belonging to the requested agent.
 *   (b) Never includes fee amounts, percentages, or private contact data.
 *   (c) Token estimate does not exceed the agent_profile scope budget (1,000 tokens).
 */
class AgentPresetLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private AgentPresetLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new AgentPresetLoader();
    }

    public function test_returns_null_when_agent_id_is_zero(): void
    {
        $result = ($this->loader)(['agent_id' => 0, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);
        $this->assertNull($result);
    }

    public function test_returns_null_when_no_presets_exist(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);
        $this->assertNull($result);
    }

    public function test_returns_fragment_for_agent_with_presets(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => ['services' => ['Listing', 'Open House'], 'commission_structure' => 'Flat Fee'],
        ]);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $this->assertEquals(AgentPresetLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(AgentPresetLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('presets', $result['content']);
    }

    public function test_only_returns_presets_for_requested_agent(): void
    {
        $agentA = User::factory()->create(['user_type' => 'agent']);
        $agentB = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $agentA->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => ['services' => ['Listing A'], 'commission_structure' => 'Tiered A'],
        ]);
        AgentDefaultProfile::create([
            'user_id'      => $agentB->id,
            'role_type'    => 'buyer',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => ['services' => ['Buyer Rep B'], 'commission_structure' => 'Flat B'],
        ]);

        $resultA = ($this->loader)(['agent_id' => $agentA->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);
        $resultB = ($this->loader)(['agent_id' => $agentB->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($resultA);
        $this->assertNotNull($resultB);

        $presetsA = $resultA['content']['presets'] ?? [];
        $presetsB = $resultB['content']['presets'] ?? [];

        $this->assertCount(1, $presetsA);
        $this->assertCount(1, $presetsB);

        $servicesA = implode(',', array_column($presetsA, 'services'));
        $servicesB = implode(',', array_column($presetsB, 'services'));

        $this->assertStringContainsString('Listing A', $servicesA);
        $this->assertStringNotContainsString('Buyer Rep B', $servicesA);

        $this->assertStringContainsString('Buyer Rep B', $servicesB);
        $this->assertStringNotContainsString('Listing A', $servicesB);
    }

    public function test_never_includes_fee_amounts_or_percentages(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => [
                'services'                     => ['Listing'],
                'purchase_fee_percentage'      => '2.5',
                'purchase_fee_flat'            => '5000',
                'lease_fee_percentage'         => '1.0',
                'lease_fee_flat'               => '2000',
                'retainer_fee_amount'          => '750',
                'referral_fee_percent'         => '25',
                'early_termination_fee_amount' => '1500',
                'nominal'                      => '200',
                'email'                        => 'agent@example.com',
                'phone'                        => '555-9999',
            ],
        ]);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $presets = $result['content']['presets'] ?? [];
        $this->assertNotEmpty($presets);

        $flatPreset = $presets[0];

        $forbiddenKeys = [
            'purchase_fee_percentage', 'purchase_fee_flat',
            'lease_fee_percentage', 'lease_fee_flat',
            'retainer_fee_amount', 'referral_fee_percent',
            'early_termination_fee_amount', 'nominal',
            'email', 'phone',
        ];

        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $flatPreset,
                "Private/sensitive field '{$key}' must not appear in preset summary.");
        }
    }

    public function test_token_estimate_within_agent_profile_budget(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        for ($i = 0; $i < 5; $i++) {
            AgentDefaultProfile::create([
                'user_id'      => $user->id,
                'role_type'    => 'seller',
                'property_type'=> "type_{$i}",
                'profile_data' => [
                    'services'              => ['Listing', 'CMA', 'Staging Consultation'],
                    'commission_structure'  => 'Flat fee + percentage hybrid',
                    'purchase_fee_type'     => 'Combination',
                ],
            ]);
        }

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(1000, $result['token_estimate'],
            "AgentPresetLoader token_estimate must not exceed 1,000 tokens for agent_profile scope.");
    }

    public function test_does_not_include_offer_or_bid_data(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => [
                'services'         => ['Listing'],
                'bid_amount'       => '75000',
                'counteroffer_val' => '80000',
                'accepted_bid_id'  => 42,
            ],
        ]);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $presets = $result['content']['presets'] ?? [];

        $bidKeys = ['bid_amount', 'counteroffer_val', 'accepted_bid_id'];
        foreach ($presets as $preset) {
            foreach ($bidKeys as $key) {
                $this->assertArrayNotHasKey($key, $preset,
                    "Bid/offer field '{$key}' must never appear in preset context.");
            }
        }
    }
}
