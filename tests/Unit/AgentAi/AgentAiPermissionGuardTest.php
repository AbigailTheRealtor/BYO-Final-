<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AgentAi\AgentAiPermissionGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentAiPermissionGuardTest
 *
 * Verifies:
 *   (a) Ownership mismatch (agent B accessing agent A's listing) throws
 *       AgentAiPermissionException with reason='ownership_mismatch'.
 *   (b) Correct owner passes validation without throwing.
 *   (c) Non-existent listing throws with reason='listing_not_found'.
 *   (d) Agent profile scope: non-agent user throws with reason='not_an_agent'.
 *   (e) Agent profile scope: valid agent passes.
 *   (f) Missing listing_id for listing scopes throws with reason='missing_listing_id'.
 *   (g) Invalid (zero) agentId throws with reason='invalid_agent_id'.
 */
class AgentAiPermissionGuardTest extends TestCase
{
    use DatabaseTransactions;

    private AgentAiPermissionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new AgentAiPermissionGuard();
    }

    public function test_throws_ownership_mismatch_when_agent_does_not_own_listing(): void
    {
        $ownerAgent = User::factory()->create(['user_type' => 'agent']);
        $otherAgent = User::factory()->create(['user_type' => 'agent']);

        $listing = SellerAgentAuction::create([
            'user_id'     => $ownerAgent->id,
            'title'       => 'Ownership Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $thrown = false;
        $reason = null;

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::PublicListingSeller,
                $otherAgent->id,
                'seller',
                $listing->id
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
            $reason = $e->getReason();
        }

        $this->assertTrue($thrown, 'AgentAiPermissionException should be thrown for cross-agent listing access.');
        $this->assertEquals('ownership_mismatch', $reason,
            "Reason must be 'ownership_mismatch' when agentId does not match listing user_id.");
    }

    public function test_passes_for_correct_listing_owner(): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $listing = SellerAgentAuction::create([
            'user_id'     => $agent->id,
            'title'       => 'Ownership Valid',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $threw = false;
        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::PublicListingSeller,
                $agent->id,
                'seller',
                $listing->id
            );
        } catch (AgentAiPermissionException $e) {
            $threw = true;
        }

        $this->assertFalse($threw, 'No exception should be thrown when the agent owns the listing.');
    }

    public function test_throws_listing_not_found_for_nonexistent_listing(): void
    {
        $agent = User::factory()->create(['user_type' => 'agent']);

        $thrown = false;
        $reason = null;
        $status = null;

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::PublicListingSeller,
                $agent->id,
                'seller',
                999999
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
            $reason = $e->getReason();
            $status = $e->getHttpStatus();
        }

        $this->assertTrue($thrown);
        $this->assertEquals('listing_not_found', $reason);
        $this->assertEquals(404, $status);
    }

    public function test_throws_not_an_agent_for_client_user_in_agent_profile_scope(): void
    {
        $clientUser = User::factory()->create(['user_type' => 'buyer']);

        $thrown = false;
        $reason = null;

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::AgentProfile,
                $clientUser->id,
                null,
                null
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
            $reason = $e->getReason();
        }

        $this->assertTrue($thrown, 'Non-agent user should be blocked for agent_profile scope.');
        $this->assertEquals('not_an_agent', $reason);
    }

    public function test_passes_for_valid_agent_in_agent_profile_scope(): void
    {
        $agent = User::factory()->create(['user_type' => 'agent']);

        $threw = false;
        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::AgentProfile,
                $agent->id,
                null,
                null
            );
        } catch (AgentAiPermissionException $e) {
            $threw = true;
        }

        $this->assertFalse($threw, 'Valid agent should pass agent_profile scope validation.');
    }

    public function test_throws_agent_not_found_for_nonexistent_user_in_agent_profile_scope(): void
    {
        $thrown = false;
        $reason = null;
        $status = null;

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::AgentProfile,
                999999,
                null,
                null
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
            $reason = $e->getReason();
            $status = $e->getHttpStatus();
        }

        $this->assertTrue($thrown);
        $this->assertEquals('agent_not_found', $reason);
        $this->assertEquals(404, $status);
    }

    public function test_throws_missing_listing_id_for_listing_scope_without_id(): void
    {
        $agent = User::factory()->create(['user_type' => 'agent']);

        $thrown = false;
        $reason = null;

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::PublicListingSeller,
                $agent->id,
                'seller',
                null
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
            $reason = $e->getReason();
        }

        $this->assertTrue($thrown);
        $this->assertEquals('missing_listing_id', $reason);
    }

    public function test_throws_invalid_agent_id_for_zero_agent_id(): void
    {
        $thrown = false;
        $reason = null;

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::PublicListingSeller,
                0,
                'seller',
                1
            );
        } catch (AgentAiPermissionException $e) {
            $thrown = true;
            $reason = $e->getReason();
        }

        $this->assertTrue($thrown);
        $this->assertEquals('invalid_agent_id', $reason);
    }

    public function test_exception_http_status_defaults_to_403_for_ownership_mismatch(): void
    {
        $ownerAgent = User::factory()->create(['user_type' => 'agent']);
        $otherAgent = User::factory()->create(['user_type' => 'agent']);

        $listing = SellerAgentAuction::create([
            'user_id'     => $ownerAgent->id,
            'title'       => 'HTTP Status Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        try {
            $this->guard->validateAgentScope(
                AgentAiContextScope::PublicListingSeller,
                $otherAgent->id,
                'seller',
                $listing->id
            );
            $this->fail('Expected AgentAiPermissionException was not thrown.');
        } catch (AgentAiPermissionException $e) {
            $this->assertEquals(403, $e->getHttpStatus());
            $this->assertEquals('ownership_mismatch', $e->getReason());
        }
    }
}
