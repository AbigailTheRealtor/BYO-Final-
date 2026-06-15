<?php

namespace Tests\Feature\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AgentAiChatLead;
use App\Models\AgentAiChatMessage;
use App\Models\AgentAiChatSession;
use App\Services\AgentAi\AgentAiLeadIntentDetector;
use App\Services\AgentAi\AgentAiLeadScoringService;
use App\Services\AgentAi\AgentAiLeadCaptureService;
use App\Services\AgentAi\AgentAiNotificationService;
use App\Services\AgentAi\AgentAiEscalationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AgentAiBuild5Test
 *
 * Integration tests for Build 5: Lead Capture, Intent Scoring, Escalation, Agent Inbox.
 *
 * Covers:
 *   (a) Scoring accumulation per canonical point table
 *   (b) Lead record creation at correct thresholds
 *   (c) Auto-attach of signed-in visitor identity at session start
 *   (d) Contact capture prompt only at high-intent (never at low-intent)
 *   (e) Escalation endpoint creates agent_question lead
 *   (f) Notification fires at each threshold (deduplicated per session)
 *   (g) Inbox page is agent-scoped (cross-agent isolation)
 *   (h) Intent detection is deterministic (no AI dependency)
 */
class AgentAiBuild5Test extends TestCase
{
    use DatabaseTransactions;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeAgentUser(): int
    {
        $uid = substr(bin2hex(random_bytes(8)), 0, 10);
        return DB::table('users')->insertGetId([
            'first_name' => 'Test',
            'last_name'  => 'Agent',
            'name'       => 'Test Agent',
            'short_id'   => 'ag' . $uid,
            'user_name'  => 'tagent_' . $uid,
            'email'      => 'agent_b5_' . $uid . '@test.invalid',
            'password'   => bcrypt('password'),
            'user_type'  => 'agent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVisitorUser(): int
    {
        $uid = substr(bin2hex(random_bytes(8)), 0, 10);
        return DB::table('users')->insertGetId([
            'first_name' => 'Jane',
            'last_name'  => 'Visitor',
            'name'       => 'Jane Visitor',
            'short_id'   => 'vi' . $uid,
            'user_name'  => 'visitor_' . $uid,
            'email'      => 'visitor_b5_' . $uid . '@test.invalid',
            'password'   => bcrypt('password'),
            'user_type'  => 'buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSession(int $agentId, ?int $visitorUserId = null): AgentAiChatSession
    {
        return AgentAiChatSession::create([
            'session_token'   => bin2hex(random_bytes(32)),
            'agent_id'        => $agentId,
            'scope'           => AgentAiContextScope::AgentProfile->value,
            'visitor_user_id' => $visitorUserId,
            'started_at'      => now(),
            'last_active_at'  => now(),
        ]);
    }

    private function addUserMessage(AgentAiChatSession $session, string $content, ?string $intent = null, ?int $score = null): AgentAiChatMessage
    {
        return AgentAiChatMessage::create([
            'session_id'          => $session->id,
            'role'                => AgentAiChatMessage::ROLE_USER,
            'content'             => $content,
            'context_scope'       => AgentAiContextScope::AgentProfile->value,
            'detected_intent'     => $intent,
            'lead_score_snapshot' => $score,
            'created_at'          => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (a) Lead Scoring — canonical point table
    // ──────────────────────────────────────────────────────────────────────────

    public function test_points_for_signal_match_config_table(): void
    {
        $scorer = app(AgentAiLeadScoringService::class);

        $this->assertSame(5,  $scorer->pointsForSignal('property_question'));
        $this->assertSame(10, $scorer->pointsForSignal('financial_question'));
        $this->assertSame(25, $scorer->pointsForSignal('showing_request'));
        $this->assertSame(35, $scorer->pointsForSignal('offer_question'));
        $this->assertSame(50, $scorer->pointsForSignal('submit_offer_intent'));
        $this->assertSame(40, $scorer->pointsForSignal('consultation_request'));
        $this->assertSame(15, $scorer->pointsForSignal('human_escalation_requested'));
        $this->assertSame(20, $scorer->pointsForSignal('phone_provided'));
        $this->assertSame(10, $scorer->pointsForSignal('email_provided'));
    }

    public function test_points_for_unknown_signal_returns_zero(): void
    {
        $scorer = app(AgentAiLeadScoringService::class);
        $this->assertSame(0, $scorer->pointsForSignal('nonexistent_signal'));
    }

    public function test_score_turn_returns_correct_points_for_showing_request(): void
    {
        $scorer = app(AgentAiLeadScoringService::class);
        $result = $scorer->scoreTurn('I want to schedule a showing this weekend');

        $this->assertSame(AgentAiLeadIntentDetector::SIGNAL_SHOWING_REQUEST, $result['signal']);
        $this->assertSame(25, $result['points']);
    }

    public function test_score_turn_awards_email_bonus_once(): void
    {
        $scorer = app(AgentAiLeadScoringService::class);

        // First time — email bonus awarded
        $result1 = $scorer->scoreTurn('Contact me at user@example.com', []);
        $this->assertGreaterThanOrEqual(10, $result1['points']);

        // Second time — email already in collected, no bonus
        $result2 = $scorer->scoreTurn('Again user@example.com', ['email']);
        $this->assertLessThan($result1['points'], $result2['points']);
    }

    public function test_accumulate_for_session_sums_all_user_messages(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $scorer  = app(AgentAiLeadScoringService::class);

        // property_question = 5 pts
        $this->addUserMessage($session, 'How many bedrooms are there?');
        // financial_question = 10 pts
        $this->addUserMessage($session, 'What is the mortgage rate?');

        $total = $scorer->accumulateForSession($session->id);

        $this->assertSame(15, $total);
    }

    public function test_accumulate_for_session_caps_at_100(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $scorer  = app(AgentAiLeadScoringService::class);

        // submit_offer_intent = 50 pts x3 = 150, capped at 100
        $this->addUserMessage($session, 'I want to make an offer on this property');
        $this->addUserMessage($session, 'I want to make an offer on this property');
        $this->addUserMessage($session, 'I want to make an offer on this property');

        $total = $scorer->accumulateForSession($session->id);

        $this->assertSame(100, $total);
    }

    public function test_thresholds_are_config_driven(): void
    {
        $scorer     = app(AgentAiLeadScoringService::class);
        $thresholds = $scorer->thresholds();

        $this->assertArrayHasKey('dashboard_card', $thresholds);
        $this->assertArrayHasKey('email', $thresholds);
        $this->assertArrayHasKey('nav_badge', $thresholds);

        $this->assertSame(50, (int) $thresholds['dashboard_card']);
        $this->assertSame(75, (int) $thresholds['email']);
        $this->assertSame(90, (int) $thresholds['nav_badge']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (b) Lead record creation at thresholds
    // ──────────────────────────────────────────────────────────────────────────

    public function test_lead_record_is_created_on_high_intent_turn(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $capture = app(AgentAiLeadCaptureService::class);

        $lead = $capture->recordOrUpdate($session->id, [
            'lead_type'    => AgentAiChatLead::LEAD_TYPE_BUYER,
            'intent_phrase' => 'I want to schedule a showing',
            'lead_score'   => 25,
            'question'     => 'I want to schedule a showing',
        ]);

        $this->assertNotNull($lead->id);
        $this->assertSame($session->id, $lead->session_id);
        $this->assertSame($agentId, $lead->agent_id);
        $this->assertSame(AgentAiChatLead::LEAD_TYPE_BUYER, $lead->lead_type);

        $dbLead = AgentAiChatLead::where('session_id', $session->id)->first();
        $this->assertNotNull($dbLead);
        $this->assertSame(25, $dbLead->lead_score);
    }

    public function test_record_or_update_upserts_not_duplicates(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $capture = app(AgentAiLeadCaptureService::class);

        $capture->recordOrUpdate($session->id, [
            'lead_type'  => AgentAiChatLead::LEAD_TYPE_BUYER,
            'lead_score' => 10,
        ]);
        $capture->recordOrUpdate($session->id, [
            'lead_score' => 40,
        ]);

        $count = AgentAiChatLead::where('session_id', $session->id)->count();
        $this->assertSame(1, $count);
    }

    public function test_questions_asked_are_appended(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $capture = app(AgentAiLeadCaptureService::class);

        $capture->recordOrUpdate($session->id, ['question' => 'First question']);
        $capture->recordOrUpdate($session->id, ['question' => 'Second question']);

        $lead = AgentAiChatLead::where('session_id', $session->id)->first();
        $this->assertCount(2, $lead->questions_asked);
        $this->assertContains('First question', $lead->questions_asked);
        $this->assertContains('Second question', $lead->questions_asked);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (c) Auto-attach of signed-in visitor identity
    // ──────────────────────────────────────────────────────────────────────────

    public function test_session_start_attaches_authenticated_visitor(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $agentId   = $this->makeAgentUser();
        $visitorId = $this->makeVisitorUser();

        $this->actingAs(\App\Models\User::find($visitorId));

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $agentId,
            'scope'    => AgentAiContextScope::AgentProfile->value,
        ]);

        $response->assertStatus(201);

        $token   = $response->json('session_token');
        $session = AgentAiChatSession::where('session_token', $token)->first();

        $this->assertNotNull($session);
        $this->assertSame($visitorId, (int) $session->visitor_user_id);
    }

    public function test_session_start_does_not_require_auth(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $agentId = $this->makeAgentUser();

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $agentId,
            'scope'    => AgentAiContextScope::AgentProfile->value,
        ]);

        $response->assertStatus(201);

        $token   = $response->json('session_token');
        $session = AgentAiChatSession::where('session_token', $token)->first();

        $this->assertNotNull($session);
        $this->assertNull($session->visitor_user_id);
    }

    public function test_lead_capture_hydrates_visitor_name_from_user_record(): void
    {
        $agentId   = $this->makeAgentUser();
        $visitorId = $this->makeVisitorUser();
        $session   = $this->createSession($agentId, $visitorId);
        $capture   = app(AgentAiLeadCaptureService::class);

        $lead = $capture->recordOrUpdate($session->id, [
            'lead_score' => 25,
        ]);

        $this->assertSame('Jane Visitor', $lead->visitor_name);
        $this->assertStringContainsString('@test.invalid', $lead->visitor_email);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (d) Contact capture prompt — high-intent only, not for signed-in users
    // ──────────────────────────────────────────────────────────────────────────

    public function test_should_prompt_for_contact_is_true_for_anonymous_high_intent(): void
    {
        $capture = app(AgentAiLeadCaptureService::class);

        $this->assertTrue(
            $capture->shouldPromptForContact(AgentAiLeadIntentDetector::SIGNAL_SHOWING_REQUEST, null)
        );
    }

    public function test_should_prompt_for_contact_is_false_for_authenticated_visitor(): void
    {
        $capture = app(AgentAiLeadCaptureService::class);

        $this->assertFalse(
            $capture->shouldPromptForContact(AgentAiLeadIntentDetector::SIGNAL_SHOWING_REQUEST, 42)
        );
    }

    public function test_should_prompt_for_contact_is_false_for_low_intent(): void
    {
        $capture = app(AgentAiLeadCaptureService::class);

        $this->assertFalse(
            $capture->shouldPromptForContact(AgentAiLeadIntentDetector::SIGNAL_PROPERTY_QUESTION, null)
        );

        $this->assertFalse(
            $capture->shouldPromptForContact(AgentAiLeadIntentDetector::SIGNAL_FINANCIAL_QUESTION, null)
        );

        $this->assertFalse(
            $capture->shouldPromptForContact(null, null)
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (e) Escalation creates agent_question lead
    // ──────────────────────────────────────────────────────────────────────────

    public function test_confirm_escalation_creates_agent_question_lead(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $service = app(AgentAiEscalationService::class);

        $result = $service->confirmEscalation(
            $session->id,
            $agentId,
            'What is the exact HOA fee structure?',
            ['name' => 'Bob Buyer', 'email' => 'bob@test.invalid']
        );

        $this->assertTrue($result['escalated']);
        $this->assertNotNull($result['lead_id']);

        $lead = AgentAiChatLead::find($result['lead_id']);
        $this->assertNotNull($lead);
        $this->assertSame(AgentAiChatLead::LEAD_TYPE_AGENT_QUESTION, $lead->lead_type);
        $this->assertSame('What is the exact HOA fee structure?', $lead->intent_phrase);
        $this->assertSame('human_escalation', $lead->requested_action);
        $this->assertSame('Bob Buyer', $lead->visitor_name);
        $this->assertSame('bob@test.invalid', $lead->visitor_email);
    }

    public function test_escalation_confirm_endpoint_returns_escalated_status(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $response = $this->postJson('/agent-ai/escalate', [
            'session_token' => $session->session_token,
            'question'      => 'Can you connect me with the agent directly?',
            'visitor_name'  => 'Alice Buyer',
            'visitor_email' => 'alice@test.invalid',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'escalated'])
            ->assertJsonStructure(['status', 'message', 'lead_id']);

        $lead = AgentAiChatLead::where('session_id', $session->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame(AgentAiChatLead::LEAD_TYPE_AGENT_QUESTION, $lead->lead_type);
    }

    public function test_escalation_endpoint_requires_question(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $response = $this->postJson('/agent-ai/escalate', [
            'session_token' => $session->session_token,
        ]);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (f) Notification thresholds — deduplication
    // ──────────────────────────────────────────────────────────────────────────

    public function test_notification_fires_dashboard_card_at_50(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $service = app(AgentAiNotificationService::class);

        $triggered = $service->checkAndNotify($session, 50);

        $this->assertContains('dashboard_card', $triggered);
        $session->refresh();
        $this->assertNotNull($session->notified_score_50_at);
    }

    public function test_notification_fires_email_at_75(): void
    {
        Mail::fake();

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $service = app(AgentAiNotificationService::class);

        $triggered = $service->checkAndNotify($session, 75);

        $this->assertContains('email', $triggered);
        $session->refresh();
        $this->assertNotNull($session->notified_score_75_at);
    }

    public function test_notification_fires_nav_badge_at_90(): void
    {
        Mail::fake();

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $service = app(AgentAiNotificationService::class);

        $triggered = $service->checkAndNotify($session, 90);

        $this->assertContains('nav_badge', $triggered);
        $session->refresh();
        $this->assertNotNull($session->notified_score_90_at);
    }

    public function test_notification_does_not_duplicate_for_same_threshold(): void
    {
        Mail::fake();

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $service = app(AgentAiNotificationService::class);

        // First fire
        $triggered1 = $service->checkAndNotify($session, 75);
        $this->assertContains('email', $triggered1);

        // Reload to get updated timestamps
        $session->refresh();

        // Second fire — must NOT re-emit
        $triggered2 = $service->checkAndNotify($session, 80);
        $this->assertNotContains('email', $triggered2);
    }

    public function test_notification_each_threshold_fires_at_most_once(): void
    {
        Mail::fake();

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $service = app(AgentAiNotificationService::class);

        $service->checkAndNotify($session, 90);
        $session->refresh();

        $triggered = $service->checkAndNotify($session, 100);
        $this->assertNotContains('dashboard_card', $triggered);
        $this->assertNotContains('email', $triggered);
        $this->assertNotContains('nav_badge', $triggered);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (g) Agent inbox — agent-scoped isolation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_inbox_page_returns_200_for_agent(): void
    {
        $agentId = $this->makeAgentUser();
        $agent   = \App\Models\User::find($agentId);

        $this->actingAs($agent)
            ->get('/agent/ai-inbox')
            ->assertStatus(200);
    }

    public function test_inbox_page_requires_auth(): void
    {
        $this->get('/agent/ai-inbox')
            ->assertRedirect('/login');
    }

    public function test_inbox_only_shows_own_sessions(): void
    {
        $agentId1 = $this->makeAgentUser();
        $agentId2 = $this->makeAgentUser();

        $session1 = $this->createSession($agentId1);
        $session2 = $this->createSession($agentId2);

        // Give agent2's session a lead so it has data
        AgentAiChatLead::create([
            'session_id' => $session2->id,
            'agent_id'   => $agentId2,
            'lead_score' => 60,
        ]);

        $agent1 = \App\Models\User::find($agentId1);

        $response = $this->actingAs($agent1)->get('/agent/ai-inbox');
        $response->assertStatus(200);

        // Agent 1 should not see agent 2's sessions — sessions variable is scoped
        // We verify at the controller level by checking the session IDs returned.
        // The simplest assertion is that agent2's lead isn't accessible via the
        // inbox show route when acting as agent1.
        $this->actingAs($agent1)
            ->get('/agent/ai-inbox/' . $session2->id)
            ->assertStatus(404);
    }

    public function test_mark_reviewed_scoped_to_owning_agent(): void
    {
        $agentId1 = $this->makeAgentUser();
        $agentId2 = $this->makeAgentUser();

        $session1 = $this->createSession($agentId1);

        // Agent2 cannot mark agent1's session reviewed
        $agent2 = \App\Models\User::find($agentId2);
        $this->actingAs($agent2)
            ->postJson('/agent/ai-inbox/' . $session1->id . '/mark-reviewed')
            ->assertStatus(404);
    }

    public function test_mark_reviewed_sets_timestamps(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $agent   = \App\Models\User::find($agentId);

        $response = $this->actingAs($agent)
            ->postJson('/agent/ai-inbox/' . $session->id . '/mark-reviewed');

        $response->assertStatus(200)->assertJson(['status' => 'reviewed']);

        $session->refresh();
        $this->assertNotNull($session->reviewed_at);
        $this->assertSame($agentId, (int) $session->reviewed_by_user_id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (h) Intent detection — fully deterministic
    // ──────────────────────────────────────────────────────────────────────────

    public function test_intent_detector_detects_showing_request(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertSame(
            AgentAiLeadIntentDetector::SIGNAL_SHOWING_REQUEST,
            $detector->detectSignal('Can I schedule a showing this weekend?')
        );
    }

    public function test_intent_detector_detects_submit_offer_intent(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertSame(
            AgentAiLeadIntentDetector::SIGNAL_SUBMIT_OFFER_INTENT,
            $detector->detectSignal('I want to make an offer on this property')
        );
    }

    public function test_intent_detector_detects_financial_question(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertSame(
            AgentAiLeadIntentDetector::SIGNAL_FINANCIAL_QUESTION,
            $detector->detectSignal('What are the current mortgage rates in this area?')
        );
    }

    public function test_intent_detector_detects_property_question(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertSame(
            AgentAiLeadIntentDetector::SIGNAL_PROPERTY_QUESTION,
            $detector->detectSignal('How many bedrooms does this home have?')
        );
    }

    public function test_intent_detector_returns_null_for_unclassified_text(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertNull($detector->detectSignal('Hello there'));
    }

    public function test_intent_detector_detects_lead_type_buyer(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertSame(
            AgentAiChatLead::LEAD_TYPE_BUYER,
            $detector->detectLeadType('I am looking to buy a home in this area')
        );
    }

    public function test_intent_detector_detects_lead_type_tenant(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertSame(
            AgentAiChatLead::LEAD_TYPE_TENANT,
            $detector->detectLeadType('I am looking to rent a 2-bedroom apartment')
        );
    }

    public function test_high_intent_signals_are_complete(): void
    {
        $expected = [
            AgentAiLeadIntentDetector::SIGNAL_SHOWING_REQUEST,
            AgentAiLeadIntentDetector::SIGNAL_SUBMIT_OFFER_INTENT,
            AgentAiLeadIntentDetector::SIGNAL_CONSULTATION_REQUEST,
            AgentAiLeadIntentDetector::SIGNAL_ESCALATION_REQUESTED,
        ];

        foreach ($expected as $signal) {
            $this->assertContains($signal, AgentAiLeadIntentDetector::HIGH_INTENT_SIGNALS);
        }

        // Low-intent signals must NOT be in HIGH_INTENT_SIGNALS
        $this->assertNotContains(
            AgentAiLeadIntentDetector::SIGNAL_PROPERTY_QUESTION,
            AgentAiLeadIntentDetector::HIGH_INTENT_SIGNALS
        );

        $this->assertNotContains(
            AgentAiLeadIntentDetector::SIGNAL_FINANCIAL_QUESTION,
            AgentAiLeadIntentDetector::HIGH_INTENT_SIGNALS
        );
    }

    public function test_email_detection_identifies_email_address(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertTrue($detector->containsEmail('You can reach me at jane@example.com'));
        $this->assertFalse($detector->containsEmail('No contact info here'));
    }

    public function test_phone_detection_identifies_phone_number(): void
    {
        $detector = app(AgentAiLeadIntentDetector::class);

        $this->assertTrue($detector->containsPhone('Call me at 555-867-5309'));
        $this->assertFalse($detector->containsPhone('No phone here'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (i) Lead model — valid lead types
    // ──────────────────────────────────────────────────────────────────────────

    public function test_lead_model_defines_all_valid_types(): void
    {
        $expected = ['buyer', 'seller', 'landlord', 'tenant', 'investor', 'referral', 'agent_question'];

        foreach ($expected as $type) {
            $this->assertContains($type, AgentAiChatLead::LEAD_TYPES);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // (j) Session-lead relationship
    // ──────────────────────────────────────────────────────────────────────────

    public function test_session_has_one_lead_relationship(): void
    {
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        AgentAiChatLead::create([
            'session_id' => $session->id,
            'agent_id'   => $agentId,
            'lead_score' => 30,
        ]);

        $session->load('lead');

        $this->assertNotNull($session->lead);
        $this->assertSame(30, (int) $session->lead->lead_score);
    }
}
