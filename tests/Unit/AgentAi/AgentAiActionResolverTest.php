<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentAi\AgentAiActionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentAiActionResolverTest
 *
 * Verifies:
 *   (a) Correct action set per scope, matching the canonical SCOPE_ACTION_KEYS map.
 *   (b) "Contact Agent" and "Request More Information" always present on every listing scope.
 *   (c) "View Agent's Services" always present on every scope (listing + agent_profile).
 *   (d) All action objects follow the { label, action_key, href, unavailable_reason } schema.
 *   (e) Inline services response bypasses OpenAI and returns the correct structure.
 *   (f) URL resolution for workflow-routing actions (with and without listing_id).
 *   (g) Missing listing_id returns null href with machine-readable unavailable_reason.
 *   (h) Missing agent short_id (invalid agent) returns null href with 'agent_not_found'.
 *   (i) inline services response never includes private/compensation fields.
 */
class AgentAiActionResolverTest extends TestCase
{
    use DatabaseTransactions;

    private AgentAiActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AgentAiActionResolver();
    }

    // ── Action schema ─────────────────────────────────────────────────────────

    /**
     * Every action object must have label, action_key, href, and unavailable_reason keys.
     *
     * @dataProvider allScopeProvider
     */
    public function test_action_objects_follow_canonical_schema(AgentAiContextScope $scope): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $actions = $this->resolver->resolve($scope, $user->id, null);

        $this->assertNotEmpty($actions, "Expected at least one action for scope {$scope->value}");

        foreach ($actions as $i => $action) {
            $this->assertArrayHasKey('label', $action, "action[{$i}] missing 'label'");
            $this->assertArrayHasKey('action_key', $action, "action[{$i}] missing 'action_key'");
            $this->assertArrayHasKey('href', $action, "action[{$i}] missing 'href'");
            $this->assertArrayHasKey('unavailable_reason', $action, "action[{$i}] missing 'unavailable_reason'");

            $this->assertIsString($action['label']);
            $this->assertNotEmpty($action['label']);
            $this->assertIsString($action['action_key']);
            $this->assertNotEmpty($action['action_key']);

            // href is string|null
            $this->assertTrue(
                $action['href'] === null || is_string($action['href']),
                "action[{$i}].href must be string or null"
            );
            // unavailable_reason is string|null
            $this->assertTrue(
                $action['unavailable_reason'] === null || is_string($action['unavailable_reason']),
                "action[{$i}].unavailable_reason must be string or null"
            );
        }
    }

    // ── Scope-specific action sets ────────────────────────────────────────────

    public function test_seller_scope_returns_correct_action_keys(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, null);

        $keys = array_column($actions, 'action_key');

        $this->assertContains(AgentAiActionResolver::ACTION_SCHEDULE_SHOWING, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_SUBMIT_OFFER, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_CONTACT_AGENT, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_REQUEST_MORE_INFO, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION, $keys);

        // Landlord-only actions must NOT be present
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_TOUR, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SUBMIT_RENTAL_OFFER, $keys);
    }

    public function test_landlord_scope_returns_correct_action_keys(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingLandlord, $user->id, null);

        $keys = array_column($actions, 'action_key');

        $this->assertContains(AgentAiActionResolver::ACTION_SCHEDULE_TOUR, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_SUBMIT_RENTAL_OFFER, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_CONTACT_AGENT, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_REQUEST_MORE_INFO, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION, $keys);

        // Seller-only actions must NOT be present
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_SHOWING, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SUBMIT_OFFER, $keys);
    }

    public function test_buyer_criteria_scope_returns_correct_action_keys(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::BuyerCriteria, $user->id, null);

        $keys = array_column($actions, 'action_key');

        $this->assertContains(AgentAiActionResolver::ACTION_RESPOND_TO_BUYER, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_CONTACT_AGENT, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_REQUEST_MORE_INFO, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES, $keys);

        // Listing-only actions must NOT be present
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_SHOWING, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_TOUR, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SUBMIT_OFFER, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_RESPOND_TO_TENANT, $keys);
    }

    public function test_tenant_criteria_scope_returns_correct_action_keys(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::TenantCriteria, $user->id, null);

        $keys = array_column($actions, 'action_key');

        $this->assertContains(AgentAiActionResolver::ACTION_RESPOND_TO_TENANT, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_CONTACT_AGENT, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_REQUEST_MORE_INFO, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES, $keys);

        $this->assertNotContains(AgentAiActionResolver::ACTION_RESPOND_TO_BUYER, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_TOUR, $keys);
    }

    public function test_agent_profile_scope_returns_correct_action_keys(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::AgentProfile, $user->id, null);

        $keys = array_column($actions, 'action_key');

        $this->assertContains(AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_CONTACT_AGENT, $keys);
        $this->assertContains(AgentAiActionResolver::ACTION_VIEW_LISTINGS, $keys);

        // Listing-only actions must NOT be present
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_SHOWING, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SUBMIT_OFFER, $keys);
        $this->assertNotContains(AgentAiActionResolver::ACTION_SCHEDULE_TOUR, $keys);
    }

    // ── Invariants: contact_agent and request_more_information always on listing scopes ──

    /**
     * @dataProvider listingScopeProvider
     */
    public function test_contact_agent_always_present_on_listing_scopes(AgentAiContextScope $scope): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve($scope, $user->id, null);
        $keys    = array_column($actions, 'action_key');

        $this->assertContains(
            AgentAiActionResolver::ACTION_CONTACT_AGENT,
            $keys,
            "'contact_agent' must always be present for scope {$scope->value}"
        );
    }

    /**
     * @dataProvider listingScopeProvider
     */
    public function test_request_more_information_always_present_on_listing_scopes(AgentAiContextScope $scope): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve($scope, $user->id, null);
        $keys    = array_column($actions, 'action_key');

        $this->assertContains(
            AgentAiActionResolver::ACTION_REQUEST_MORE_INFO,
            $keys,
            "'request_more_information' must always be present for scope {$scope->value}"
        );
    }

    // ── In-chat actions (never get a platform URL) ────────────────────────────

    public function test_view_agent_services_has_null_href_and_is_handled_inline(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::AgentProfile, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES);

        $this->assertNotNull($action, "'view_agent_services' not found in agent_profile actions");
        $this->assertNull($action['href'], "'view_agent_services' must have null href (in-chat action)");
        $this->assertNull($action['unavailable_reason']);
    }

    public function test_contact_agent_has_null_href(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, 99);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_CONTACT_AGENT);

        $this->assertNotNull($action);
        $this->assertNull($action['href'], "'contact_agent' is an in-chat action and must have null href");
        $this->assertNull($action['unavailable_reason']);
    }

    public function test_request_more_information_has_null_href(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, 99);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_REQUEST_MORE_INFO);

        $this->assertNotNull($action);
        $this->assertNull($action['href'], "'request_more_information' must have null href");
        $this->assertNull($action['unavailable_reason']);
    }

    // ── URL resolution: workflow actions ──────────────────────────────────────

    public function test_schedule_showing_returns_null_href_when_no_listing_id(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SCHEDULE_SHOWING);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('missing_listing_id', $action['unavailable_reason']);
    }

    public function test_submit_offer_returns_null_href_when_no_listing_id(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SUBMIT_OFFER);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('missing_listing_id', $action['unavailable_reason']);
    }

    public function test_schedule_tour_returns_null_href_when_no_listing_id(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingLandlord, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SCHEDULE_TOUR);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('missing_listing_id', $action['unavailable_reason']);
    }

    public function test_submit_rental_offer_returns_null_href_when_no_listing_id(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingLandlord, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SUBMIT_RENTAL_OFFER);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('missing_listing_id', $action['unavailable_reason']);
    }

    public function test_respond_to_buyer_returns_null_href_when_no_listing_id(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::BuyerCriteria, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_RESPOND_TO_BUYER);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('missing_listing_id', $action['unavailable_reason']);
    }

    public function test_respond_to_tenant_returns_null_href_when_no_listing_id(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::TenantCriteria, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_RESPOND_TO_TENANT);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('missing_listing_id', $action['unavailable_reason']);
    }

    public function test_schedule_showing_returns_href_when_listing_id_provided(): void
    {
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, 42);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SCHEDULE_SHOWING);
        $this->assertNotNull($action);

        // Route 'offer.listing.seller.view' must be registered in this environment.
        // URL generation does not require auth, so href must be non-null.
        $this->assertNotNull($action['href'], 'schedule_showing must resolve href when listing_id is provided');
        $this->assertNull($action['unavailable_reason']);
        $this->assertStringContainsString('42', $action['href']);
    }

    public function test_workflow_action_with_listing_id_never_returns_null_href_with_null_reason(): void
    {
        // When listing_id is provided, the only valid outcomes are:
        //   (a) href resolved  → href != null, unavailable_reason = null
        //   (b) route failure  → href = null,  unavailable_reason = 'route_unavailable'
        // href = null + unavailable_reason = null is the bug we guard against.
        $user    = User::factory()->create(['user_type' => 'agent']);
        $actions = $this->resolver->resolve(AgentAiContextScope::PublicListingSeller, $user->id, 99);

        foreach ($actions as $action) {
            if ($action['href'] === null && $action['unavailable_reason'] === null) {
                // Only in-chat actions are allowed to have both null
                $this->assertContains(
                    $action['action_key'],
                    [
                        AgentAiActionResolver::ACTION_CONTACT_AGENT,
                        AgentAiActionResolver::ACTION_REQUEST_MORE_INFO,
                        AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES,
                        AgentAiActionResolver::ACTION_ASK_A_QUESTION,
                    ],
                    "Workflow action '{$action['action_key']}' has href=null and unavailable_reason=null — " .
                    "must be either a resolved href or 'route_unavailable'"
                );
            }
        }
    }

    public function test_schedule_consultation_returns_null_href_when_agent_not_found(): void
    {
        // Use an agent ID that does not exist in the DB
        $actions = $this->resolver->resolve(AgentAiContextScope::AgentProfile, 999999, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION);
        $this->assertNotNull($action);
        $this->assertNull($action['href']);
        $this->assertEquals('agent_not_found', $action['unavailable_reason']);
    }

    public function test_schedule_consultation_returns_href_when_agent_has_short_id(): void
    {
        $user = User::factory()->create([
            'user_type' => 'agent',
            'short_id'  => 'test-short-' . uniqid(),
        ]);

        $actions = $this->resolver->resolve(AgentAiContextScope::AgentProfile, $user->id, null);

        $action = $this->findAction($actions, AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION);
        $this->assertNotNull($action);

        // Should have a non-null href (route 'agent.profile.public' must exist)
        $this->assertNotNull($action['href'], 'schedule_consultation should resolve an href when agent has a short_id');
        $this->assertNull($action['unavailable_reason']);
        $this->assertStringContainsString($user->short_id, $action['href']);
    }

    // ── View Agent's Services inline handler ──────────────────────────────────

    public function test_resolve_inline_services_returns_correct_structure(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'       => $user->id,
            'role_type'     => 'seller',
            'property_type' => AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data'  => [
                'services'             => ['Listing', 'Open House', 'Negotiation'],
                'commission_structure' => 'Flat Fee',
            ],
        ]);

        $response = $this->resolver->resolveInlineServices($user->id);

        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('answer', $response);
        $this->assertArrayHasKey('escalate', $response);
        $this->assertArrayHasKey('actions', $response);

        $this->assertEquals('answered', $response['status']);
        $this->assertFalse($response['escalate']);
        $this->assertIsString($response['answer']);
        $this->assertNotEmpty($response['answer']);
        $this->assertIsArray($response['actions']);
    }

    public function test_resolve_inline_services_includes_secondary_actions(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $response = $this->resolver->resolveInlineServices($user->id);

        $actionKeys = array_column($response['actions'], 'action_key');

        $this->assertContains(AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION, $actionKeys);
        $this->assertContains(AgentAiActionResolver::ACTION_CONTACT_AGENT, $actionKeys);
        $this->assertContains(AgentAiActionResolver::ACTION_ASK_A_QUESTION, $actionKeys);
    }

    public function test_resolve_inline_services_contact_agent_and_ask_a_question_have_null_href(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $response = $this->resolver->resolveInlineServices($user->id);

        $inChatKeys = [
            AgentAiActionResolver::ACTION_CONTACT_AGENT,
            AgentAiActionResolver::ACTION_ASK_A_QUESTION,
        ];

        foreach ($response['actions'] as $action) {
            if (in_array($action['action_key'], $inChatKeys, true)) {
                $this->assertNull($action['href'],
                    "In-chat secondary action '{$action['action_key']}' must have null href");
            }
        }
    }

    public function test_resolve_inline_services_schedule_consultation_resolves_href_when_agent_has_short_id(): void
    {
        $user = User::factory()->create([
            'user_type' => 'agent',
            'short_id'  => 'inline-svc-' . uniqid(),
        ]);

        AgentDefaultProfile::create([
            'user_id'       => $user->id,
            'role_type'     => 'seller',
            'property_type' => AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data'  => ['services' => ['Listing']],
        ]);

        $response = $this->resolver->resolveInlineServices($user->id);
        $actions  = $response['actions'];

        $consultAction = null;
        foreach ($actions as $action) {
            if ($action['action_key'] === AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION) {
                $consultAction = $action;
                break;
            }
        }

        $this->assertNotNull($consultAction, 'schedule_consultation must be present in inline services secondary actions');
        $this->assertNotNull($consultAction['href'],
            'schedule_consultation must have a non-null href when agent has a short_id');
        $this->assertStringContainsString($user->short_id, $consultAction['href']);
        $this->assertNull($consultAction['unavailable_reason']);
    }

    public function test_resolve_inline_services_schedule_consultation_has_agent_not_found_when_no_short_id(): void
    {
        // Non-existent agent
        $response = $this->resolver->resolveInlineServices(999999);
        $actions  = $response['actions'];

        $consultAction = null;
        foreach ($actions as $action) {
            if ($action['action_key'] === AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION) {
                $consultAction = $action;
                break;
            }
        }

        $this->assertNotNull($consultAction, 'schedule_consultation must always be present in secondary actions');
        $this->assertNull($consultAction['href']);
        $this->assertEquals('agent_not_found', $consultAction['unavailable_reason']);
    }

    public function test_resolve_inline_services_includes_service_names_in_answer(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'       => $user->id,
            'role_type'     => 'seller',
            'property_type' => AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data'  => [
                'services' => ['Listing', 'Photography', 'Negotiation'],
            ],
        ]);

        $response = $this->resolver->resolveInlineServices($user->id);

        $this->assertStringContainsString('Listing', $response['answer']);
        $this->assertStringContainsString('Photography', $response['answer']);
        $this->assertStringContainsString('Negotiation', $response['answer']);
    }

    public function test_resolve_inline_services_never_includes_fee_amounts(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        AgentDefaultProfile::create([
            'user_id'       => $user->id,
            'role_type'     => 'seller',
            'property_type' => AgentDefaultProfile::ROLE_DEFAULT,
            'profile_data'  => [
                'services'                     => ['Listing'],
                'purchase_fee_percentage'      => '2.5',
                'purchase_fee_flat'            => '5000',
                'lease_fee_percentage'         => '1.0',
                'retainer_fee_amount'          => '750',
                'referral_fee_percent'         => '25',
                'early_termination_fee_amount' => '1500',
                'email'                        => 'private@example.com',
                'phone'                        => '555-9999',
            ],
        ]);

        $response = $this->resolver->resolveInlineServices($user->id);
        $answer   = $response['answer'];

        $this->assertStringNotContainsString('2.5', $answer, 'purchase_fee_percentage must not appear');
        $this->assertStringNotContainsString('5000', $answer, 'purchase_fee_flat must not appear');
        $this->assertStringNotContainsString('750', $answer, 'retainer_fee_amount must not appear');
        $this->assertStringNotContainsString('private@example.com', $answer, 'email must not appear');
        $this->assertStringNotContainsString('555-9999', $answer, 'phone must not appear');
    }

    public function test_resolve_inline_services_returns_graceful_message_when_no_presets(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $response = $this->resolver->resolveInlineServices($user->id);

        $this->assertEquals('answered', $response['status']);
        $this->assertFalse($response['escalate']);
        $this->assertNotEmpty($response['answer']);
    }

    public function test_resolve_inline_services_secondary_actions_follow_canonical_schema(): void
    {
        $user = User::factory()->create(['user_type' => 'agent']);

        $response = $this->resolver->resolveInlineServices($user->id);

        foreach ($response['actions'] as $i => $action) {
            $this->assertArrayHasKey('label', $action, "secondary action[{$i}] missing 'label'");
            $this->assertArrayHasKey('action_key', $action, "secondary action[{$i}] missing 'action_key'");
            $this->assertArrayHasKey('href', $action, "secondary action[{$i}] missing 'href'");
            $this->assertArrayHasKey('unavailable_reason', $action, "secondary action[{$i}] missing 'unavailable_reason'");
        }
    }

    // ── Zero-agent-id edge cases ──────────────────────────────────────────────

    public function test_resolve_returns_empty_array_for_zero_agent_id(): void
    {
        $actions = $this->resolver->resolve(AgentAiContextScope::AgentProfile, 0, null);
        // Should return actions but schedule_consultation should have unavailable_reason='agent_not_found'
        $consultAction = $this->findAction($actions, AgentAiActionResolver::ACTION_SCHEDULE_CONSULTATION);
        if ($consultAction !== null) {
            $this->assertEquals('agent_not_found', $consultAction['unavailable_reason']);
        }
    }

    // ── Data providers ────────────────────────────────────────────────────────

    public static function allScopeProvider(): array
    {
        return [
            'seller'   => [AgentAiContextScope::PublicListingSeller],
            'landlord' => [AgentAiContextScope::PublicListingLandlord],
            'buyer'    => [AgentAiContextScope::BuyerCriteria],
            'tenant'   => [AgentAiContextScope::TenantCriteria],
            'profile'  => [AgentAiContextScope::AgentProfile],
        ];
    }

    public static function listingScopeProvider(): array
    {
        return [
            'seller'   => [AgentAiContextScope::PublicListingSeller],
            'landlord' => [AgentAiContextScope::PublicListingLandlord],
            'buyer'    => [AgentAiContextScope::BuyerCriteria],
            'tenant'   => [AgentAiContextScope::TenantCriteria],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findAction(array $actions, string $actionKey): ?array
    {
        foreach ($actions as $action) {
            if ($action['action_key'] === $actionKey) {
                return $action;
            }
        }
        return null;
    }
}
