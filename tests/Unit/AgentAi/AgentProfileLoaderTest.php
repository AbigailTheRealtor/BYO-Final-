<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentAi\Loaders\AgentProfileLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentProfileLoaderTest
 *
 * Verifies:
 *   (a) Only returns data belonging to the requested agent.
 *   (b) Never includes private fields (email, phone, fee amounts).
 *   (c) Token estimate does not exceed the agent_profile scope budget (1,000 tokens).
 */
class AgentProfileLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private AgentProfileLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new AgentProfileLoader();
    }

    public function test_returns_null_when_agent_id_is_zero(): void
    {
        $result = ($this->loader)(['agent_id' => 0, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);
        $this->assertNull($result);
    }

    public function test_returns_null_when_agent_does_not_exist(): void
    {
        $result = ($this->loader)(['agent_id' => 999999, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);
        $this->assertNull($result);
    }

    public function test_returns_fragment_for_valid_agent(): void
    {
        $user = User::factory()->create(['user_type' => 'agent', 'first_name' => 'Jane', 'last_name' => 'Smith']);

        $result = ($this->loader)([
            'agent_id'     => $user->id,
            'scope'        => AgentAiContextScope::AgentProfile,
            'listing_type' => null,
            'listing_id'   => null,
        ]);

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertEquals(AgentProfileLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(AgentProfileLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('token_estimate', $result);
        $this->assertArrayHasKey('loaded_at', $result);
    }

    public function test_only_returns_data_for_requested_agent(): void
    {
        $agentA = User::factory()->create(['user_type' => 'agent', 'first_name' => 'Alice']);
        $agentB = User::factory()->create(['user_type' => 'agent', 'first_name' => 'Bob']);

        AgentDefaultProfile::create([
            'user_id'      => $agentA->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => ['bio' => 'Alice bio', 'brokerage' => 'Alice Realty'],
        ]);
        AgentDefaultProfile::create([
            'user_id'      => $agentB->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => ['bio' => 'Bob bio', 'brokerage' => 'Bob Realty'],
        ]);

        $resultA = ($this->loader)(['agent_id' => $agentA->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);
        $resultB = ($this->loader)(['agent_id' => $agentB->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($resultA);
        $this->assertNotNull($resultB);

        $contentA = $resultA['content'];
        $contentB = $resultB['content'];

        if (isset($contentA['bio'])) {
            $this->assertStringContainsString('Alice', $contentA['bio']);
            $this->assertStringNotContainsString('Bob', $contentA['bio']);
        }

        if (isset($contentB['bio'])) {
            $this->assertStringContainsString('Bob', $contentB['bio']);
            $this->assertStringNotContainsString('Alice', $contentB['bio']);
        }
    }

    public function test_never_includes_private_fields(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => [
                'bio'                      => 'My bio',
                'email'                    => 'agent@example.com',
                'phone'                    => '555-1234',
                'business_card_upload_path'=> '/uploads/card.jpg',
                'purchase_fee_percentage'  => '2.5',
                'purchase_fee_flat'        => '3500',
                'lease_fee_percentage'     => '1.5',
                'lease_fee_flat'           => '1000',
                'retainer_fee_amount'      => '500',
                'referral_fee_percent'     => '25',
                'early_termination_fee_amount' => '1000',
                'nominal'                  => '100',
            ],
        ]);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $content = $result['content'];

        $privateKeys = [
            'email', 'phone', 'business_card_upload_path',
            'purchase_fee_percentage', 'purchase_fee_flat',
            'lease_fee_percentage', 'lease_fee_flat',
            'retainer_fee_amount', 'referral_fee_percent',
            'early_termination_fee_amount', 'nominal',
        ];

        foreach ($privateKeys as $key) {
            $this->assertArrayNotHasKey($key, $content, "Private field '{$key}' must not appear in fragment content.");
        }
    }

    public function test_token_estimate_within_agent_profile_budget(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => [
                'bio'              => str_repeat('A very detailed bio. ', 20),
                'brokerage'        => 'Best Realty Inc',
                'services'         => ['Listing', 'Buyer Representation', 'Consultation'],
                'cities_served'    => ['Tampa', 'Orlando', 'Miami'],
                'availability_status' => 'Available',
            ],
        ]);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(1000, $result['token_estimate'],
            "AgentProfileLoader token_estimate must not exceed the agent_profile scope budget of 1,000 tokens.");
    }

    public function test_fragment_has_correct_source_key_and_priority(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $this->assertEquals('agent_profile', $result['source_key']);
        $this->assertEquals(80, $result['priority']);
        $this->assertTrue($result['public_allowed']);
    }

    public function test_does_not_include_offer_or_bid_data(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'      => $user->id,
            'role_type'    => 'seller',
            'property_type'=> AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data' => [
                'bio'            => 'My bio',
                'bid_amount'     => '50000',
                'offer_price'    => '450000',
                'counteroffer'   => '460000',
                'accepted_bid'   => 'agent123',
            ],
        ]);

        $result = ($this->loader)(['agent_id' => $user->id, 'scope' => AgentAiContextScope::AgentProfile, 'listing_type' => null, 'listing_id' => null]);

        $this->assertNotNull($result);
        $content = $result['content'];

        $bidKeys = ['bid_amount', 'offer_price', 'counteroffer', 'accepted_bid'];
        foreach ($bidKeys as $key) {
            $this->assertArrayNotHasKey($key, $content, "Bid/offer field '{$key}' must never appear in agent profile context.");
        }
    }
}
