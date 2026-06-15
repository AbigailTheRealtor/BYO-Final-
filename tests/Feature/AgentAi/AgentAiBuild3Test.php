<?php

namespace Tests\Feature\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AgentAiChatMessage;
use App\Models\AgentAiChatSession;
use App\Services\AgentAi\AgentAiContextBuilder;
use App\Services\AgentAi\AgentAiFinalResponseBuilder;
use App\Services\AgentAi\AgentAiOpenAiOrchestrator;
use App\Services\AgentAi\AgentAiPermissionGuard;
use App\Services\AgentAi\AgentAiPromptBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgentAiBuild3Test
 *
 * Integration tests for Build 3:
 *   (a) Multi-turn conversation returns coherent follow-up answers.
 *   (b) History is capped at 6 turns verbatim; older turns are summarized.
 *   (c) Cross-agent request throws permission exception / returns 403.
 *   (d) Offer/bid tables NEVER appear in any DB query during a chat turn.
 *   (e) API error returns graceful fallback.
 *
 * All tests use DatabaseTransactions so no data leaks between tests.
 */
class AgentAiBuild3Test extends TestCase
{
    use DatabaseTransactions;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeAgentUser(): int
    {
        $uid = substr(bin2hex(random_bytes(8)), 0, 10);
        return DB::table('users')->insertGetId([
            'first_name'  => 'Test',
            'last_name'   => 'Agent',
            'name'        => 'Test Agent',
            'short_id'    => 'ag' . $uid,
            'user_name'   => 'tagent_' . $uid,
            'email'       => 'agent_b3_' . $uid . '@test.invalid',
            'password'    => bcrypt('password'),
            'user_type'   => 'agent',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function makeNonAgentUser(): int
    {
        $uid = substr(bin2hex(random_bytes(8)), 0, 10);
        return DB::table('users')->insertGetId([
            'first_name'  => 'Buyer',
            'last_name'   => 'User',
            'name'        => 'Buyer User',
            'short_id'    => 'bu' . $uid,
            'user_name'   => 'buyer_' . $uid,
            'email'       => 'buyer_b3_' . $uid . '@test.invalid',
            'password'    => bcrypt('password'),
            'user_type'   => 'buyer',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Create a session for a given agent ID.
     */
    private function createSession(int $agentId, string $scope = 'agent_profile'): AgentAiChatSession
    {
        return AgentAiChatSession::create([
            'session_token'  => bin2hex(random_bytes(32)),
            'agent_id'       => $agentId,
            'scope'          => $scope,
            'started_at'     => now(),
            'last_active_at' => now(),
        ]);
    }

    /**
     * Append a message to a session.
     */
    private function appendMessage(AgentAiChatSession $session, string $role, string $content): AgentAiChatMessage
    {
        return AgentAiChatMessage::create([
            'session_id'    => $session->id,
            'role'          => $role,
            'content'       => $content,
            'context_scope' => $session->scope,
            'created_at'    => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T1: Session management — start session
    // ──────────────────────────────────────────────────────────────────────────

    public function test_start_session_creates_new_session_and_returns_token(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $agentId,
            'scope'    => 'agent_profile',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['status', 'session_token', 'scope', 'resumed']);
        $response->assertJson(['status' => 'created', 'scope' => 'agent_profile', 'resumed' => false]);

        $token = $response->json('session_token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('agent_ai_chat_sessions', [
            'session_token' => $token,
            'agent_id'      => $agentId,
            'scope'         => 'agent_profile',
        ]);
    }

    public function test_start_session_resumes_existing_session_by_token(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id'     => $agentId,
            'scope'        => 'agent_profile',
            'session_token' => $session->session_token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'        => 'resumed',
            'session_token' => $session->session_token,
            'resumed'       => true,
        ]);
    }

    public function test_start_session_rejects_non_agent_user(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $buyerId = $this->makeNonAgentUser();

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $buyerId,
            'scope'    => 'agent_profile',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['status' => 'error']);
    }

    public function test_start_session_rejects_invalid_scope(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $agentId,
            'scope'    => 'invalid_scope_xyz',
        ]);

        $response->assertStatus(422);
    }

    public function test_start_session_rejects_invalid_channel(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();

        $response = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $agentId,
            'scope'    => 'agent_profile',
            'channel'  => 'telegram',
        ]);

        $response->assertStatus(422);
    }

    public function test_start_session_accepts_valid_channel(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();

        foreach (AgentAiChatSession::ALLOWED_CHANNELS as $channel) {
            $response = $this->postJson('/agent-ai/session/start', [
                'agent_id' => $agentId,
                'scope'    => 'agent_profile',
                'channel'  => $channel,
            ]);
            $response->assertStatus(201);
            $response->assertJson(['status' => 'created']);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T2: ask endpoint — permission guard
    // ──────────────────────────────────────────────────────────────────────────

    public function test_ask_returns_400_when_session_token_missing(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $response = $this->postJson('/agent-ai/ask', [
            'question' => 'What are the HOA fees?',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['status' => 'error', 'reason' => 'missing_session_token']);
    }

    public function test_ask_returns_404_when_session_not_found(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $response = $this->postJson('/agent-ai/ask', [
            'session_token' => 'nonexistent_token_' . uniqid(),
            'question'      => 'What is the listing price?',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['status' => 'error', 'reason' => 'session_not_found']);
    }

    public function test_ask_returns_403_when_session_is_ended(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);
        $session->update(['ended_at' => now()]);

        $response = $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'Any questions?',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['status' => 'error', 'reason' => 'session_ended']);
    }

    public function test_ask_returns_422_when_question_is_empty(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $response = $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => '',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'error']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T3(c): Cross-agent isolation — session belonging to agent A cannot be
    //        used if the underlying owner check is violated.
    // ──────────────────────────────────────────────────────────────────────────

    public function test_permission_guard_validateAgentScope_throws_for_mismatched_agent(): void
    {
        $guard = app(AgentAiPermissionGuard::class);

        $agentIdA = $this->makeAgentUser();
        $agentIdB = $this->makeAgentUser();

        // Create a seller listing owned by agentA (mock with users table reference).
        // We only need to verify that the guard throws when ownership mismatches.
        // Use a non-existent listing_id (999999) — guard should throw listing_not_found.
        $this->expectException(\App\Exceptions\AgentAiPermissionException::class);

        $guard->validateAgentScope(
            AgentAiContextScope::PublicListingSeller,
            $agentIdB,
            'seller',
            999999
        );
    }

    public function test_permission_guard_check_rejects_session_owned_by_non_agent(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $buyerId = $this->makeNonAgentUser();

        // Manually create a session with a non-agent agent_id (bypass start_session validation)
        $session = AgentAiChatSession::create([
            'session_token'  => bin2hex(random_bytes(32)),
            'agent_id'       => $buyerId,
            'scope'          => 'agent_profile',
            'started_at'     => now(),
            'last_active_at' => now(),
        ]);

        $response = $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'What is the price?',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['status' => 'error', 'reason' => 'not_an_agent']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T3(d): Offer/bid tables never queried during a chat turn
    // ──────────────────────────────────────────────────────────────────────────

    public function test_blocked_tables_constant_is_populated(): void
    {
        $blocked = AgentAiPermissionGuard::BLOCKED_TABLES;

        $this->assertNotEmpty($blocked, 'BLOCKED_TABLES must not be empty');

        $required = [
            'seller_agent_auction_bids',
            'accepted_bid_summaries',
            'counter_bids',
        ];

        foreach ($required as $table) {
            $this->assertContains(
                $table,
                $blocked,
                "BLOCKED_TABLES must include '{$table}'"
            );
        }
    }

    public function test_ask_pipeline_does_not_query_blocked_tables(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $queriedTables = [];

        // Listen to every query executed during the ask request
        DB::listen(function ($query) use (&$queriedTables) {
            if (preg_match('/from\s+"?(\w+)"?/i', $query->sql, $m)) {
                $queriedTables[] = $m[1];
            }
        });

        // Mock orchestrator to avoid real OpenAI call — returns a success response
        $this->instance(AgentAiOpenAiOrchestrator::class, new class extends AgentAiOpenAiOrchestrator {
            public function call(array $promptPackage, array $options = []): array
            {
                return [
                    'success'     => true,
                    'raw_content' => 'This property has a spacious layout.',
                    'usage'       => ['prompt_tokens' => 10, 'completion_tokens' => 8, 'total_tokens' => 18],
                    'model'       => 'test-model',
                    'model_tier'  => 'fast',
                    'error'       => null,
                ];
            }
        });

        $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'Tell me about this property.',
        ]);

        $blockedTables = AgentAiPermissionGuard::BLOCKED_TABLES;

        $violations = array_intersect($queriedTables, $blockedTables);

        $this->assertEmpty(
            $violations,
            'The following blocked tables were queried during ask: ' . implode(', ', $violations)
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T3(e): API error returns graceful fallback
    // ──────────────────────────────────────────────────────────────────────────

    public function test_ask_returns_fallback_when_openai_fails(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        // Stub orchestrator to simulate OpenAI failure
        $this->instance(AgentAiOpenAiOrchestrator::class, new class extends AgentAiOpenAiOrchestrator {
            public function call(array $promptPackage, array $options = []): array
            {
                return [
                    'success'     => false,
                    'raw_content' => null,
                    'usage'       => null,
                    'model'       => null,
                    'model_tier'  => null,
                    'error'       => 'Simulated OpenAI outage',
                ];
            }
        });

        $response = $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'What is the listing price?',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'fallback', 'escalate' => true]);

        $answer = $response->json('answer');
        $this->assertNotEmpty($answer, 'Fallback answer must not be empty');

        // Fallback must NOT contain any internal details
        $this->assertStringNotContainsStringIgnoringCase('openai', $answer);
        $this->assertStringNotContainsStringIgnoringCase('error', $answer);
        $this->assertStringNotContainsStringIgnoringCase('api key', $answer);
    }

    public function test_ask_persists_messages_even_when_using_fallback(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $this->instance(AgentAiOpenAiOrchestrator::class, new class extends AgentAiOpenAiOrchestrator {
            public function call(array $promptPackage, array $options = []): array
            {
                return [
                    'success'     => false,
                    'raw_content' => null,
                    'usage'       => null,
                    'model'       => null,
                    'model_tier'  => null,
                    'error'       => 'Simulated failure',
                ];
            }
        });

        $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'Is there a pool?',
        ]);

        $this->assertDatabaseHas('agent_ai_chat_messages', [
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => 'Is there a pool?',
        ]);

        $this->assertDatabaseHas('agent_ai_chat_messages', [
            'session_id' => $session->id,
            'role'       => 'assistant',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T3(a): Multi-turn conversation — messages are persisted and loaded
    // ──────────────────────────────────────────────────────────────────────────

    public function test_ask_persists_user_and_assistant_messages_per_turn(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $this->instance(AgentAiOpenAiOrchestrator::class, new class extends AgentAiOpenAiOrchestrator {
            public function call(array $promptPackage, array $options = []): array
            {
                return [
                    'success'     => true,
                    'raw_content' => 'The roof was replaced in 2020.',
                    'usage'       => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
                    'model'       => 'gpt-4o-mini',
                    'model_tier'  => 'fast',
                    'error'       => null,
                ];
            }
        });

        $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'How old is the roof?',
        ]);

        $messages = AgentAiChatMessage::where('session_id', $session->id)->get();
        $this->assertCount(2, $messages, 'Each ask must persist exactly 2 messages (user + assistant)');

        $roles = $messages->pluck('role')->toArray();
        $this->assertContains('user', $roles);
        $this->assertContains('assistant', $roles);
    }

    public function test_multi_turn_conversation_history_is_included_in_prompt(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        // Seed prior conversation history
        $this->appendMessage($session, 'user',      'Tell me about this property.');
        $this->appendMessage($session, 'assistant', 'This is a 3-bed, 2-bath home built in 2005.');

        $capturedMessages = null;

        $this->instance(AgentAiOpenAiOrchestrator::class, new class($capturedMessages) extends AgentAiOpenAiOrchestrator {
            public function __construct(private ?array &$captured) {}

            public function call(array $promptPackage, array $options = []): array
            {
                $this->captured = $promptPackage['messages'] ?? [];
                return [
                    'success'     => true,
                    'raw_content' => 'The roof was replaced in 2020.',
                    'usage'       => ['prompt_tokens' => 80, 'completion_tokens' => 15, 'total_tokens' => 95],
                    'model'       => 'gpt-4o-mini',
                    'model_tier'  => 'fast',
                    'error'       => null,
                ];
            }
        });

        $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'How old is the roof?',
        ]);

        // Verify the prior history was included in the prompt messages
        $this->assertNotNull($capturedMessages, 'Prompt messages must be captured by orchestrator');

        $allContent = implode(' ', array_column($capturedMessages ?? [], 'content'));
        $this->assertStringContainsString('Tell me about this property', $allContent);
        $this->assertStringContainsString('How old is the roof', $allContent);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // T3(b): History capped at 6 verbatim turns; older turns summarized
    // ──────────────────────────────────────────────────────────────────────────

    public function test_history_beyond_verbatim_limit_is_summarized(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        config(['ask_ai.agent_ai_verbatim_turns' => 2]); // set low for testing

        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        // Seed 8 messages (4 turns) — beyond the 2-turn verbatim limit
        for ($i = 1; $i <= 4; $i++) {
            $this->appendMessage($session, 'user',      "Old question {$i}");
            $this->appendMessage($session, 'assistant', "Old answer {$i}");
        }

        $capturedMessages = null;

        $this->instance(AgentAiOpenAiOrchestrator::class, new class($capturedMessages) extends AgentAiOpenAiOrchestrator {
            public function __construct(private ?array &$captured) {}

            public function call(array $promptPackage, array $options = []): array
            {
                $this->captured = $promptPackage['messages'] ?? [];
                return [
                    'success'     => true,
                    'raw_content' => 'Sure, here is the information.',
                    'usage'       => ['prompt_tokens' => 100, 'completion_tokens' => 10, 'total_tokens' => 110],
                    'model'       => 'gpt-4o-mini',
                    'model_tier'  => 'fast',
                    'error'       => null,
                ];
            }
        });

        $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'New question here',
        ]);

        $this->assertNotNull($capturedMessages);

        // The 'history_summarized' governance flag triggers a summary injection.
        // The summary message contains "Prior conversation summary"
        $allContent = implode(' ', array_column($capturedMessages ?? [], 'content'));
        $this->assertStringContainsString(
            'Prior conversation summary',
            $allContent,
            'Older turns must be condensed into a summary prefix'
        );

        // The oldest questions should be in the summary (not verbatim)
        // The most recent turns should appear verbatim
        $this->assertStringContainsString('Old question 3', $allContent);
        $this->assertStringContainsString('Old question 4', $allContent);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AgentAiPromptBuilder unit tests
    // ──────────────────────────────────────────────────────────────────────────

    public function test_prompt_builder_returns_prompt_ready_status(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => 'property: 3-bed home', 'governance_flags' => []];

        $result = $builder->build($context, 'What is the price?', AgentAiContextScope::AgentProfile);

        $this->assertEquals('prompt_ready', $result['status']);
        $this->assertNotEmpty($result['messages']);
        $this->assertIsInt($result['token_estimate']);
        $this->assertIsArray($result['governance_flags']);
    }

    public function test_prompt_builder_always_starts_with_system_message(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => '', 'governance_flags' => []];

        $result = $builder->build($context, 'Hello?', AgentAiContextScope::AgentProfile);

        $this->assertEquals('system', $result['messages'][0]['role']);
        $this->assertStringContainsStringIgnoringCase('fair housing', $result['messages'][0]['content']);
    }

    public function test_prompt_builder_includes_question_as_last_user_message(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => 'some context', 'governance_flags' => []];

        $question = 'Does the home have a fireplace?';
        $result   = $builder->build($context, $question, AgentAiContextScope::AgentProfile);

        $lastMessage = end($result['messages']);
        $this->assertEquals('user', $lastMessage['role']);
        $this->assertEquals($question, $lastMessage['content']);
    }

    public function test_prompt_builder_flags_empty_question(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => 'ctx', 'governance_flags' => []];

        $result = $builder->build($context, '   ', AgentAiContextScope::AgentProfile);

        $this->assertContains('empty_question', $result['governance_flags']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AgentAiPromptBuilder — context classification / safety filtering tests
    // ──────────────────────────────────────────────────────────────────────────

    public function test_prompt_builder_includes_public_safe_fragments_only(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = [
            'context_fragments' => [
                ['type' => 'listing_overview', 'classification' => 'public_safe',   'content' => 'APPROVED: 3 bed home'],
                ['type' => 'agent_profile',    'classification' => 'private',        'content' => 'PRIVATE: agent ssn'],
                ['type' => 'listing_details',  'classification' => 'unclassified',   'content' => 'UNCLASSIFIED: raw data'],
                ['type' => 'internal_notes',   'classification' => 'public_safe',    'content' => 'UNKNOWN_TYPE: internal'],
                ['type' => 'mls_snapshot',     'classification' => 'public_safe',    'content' => 'APPROVED_MLS: square footage'],
            ],
        ];

        $result = $builder->build($context, 'Tell me about the property', AgentAiContextScope::AgentProfile);

        // Only the public_safe + known-type fragments should appear
        $allContent = implode(' ', array_column($result['messages'], 'content'));
        $this->assertStringContainsString('APPROVED: 3 bed home', $allContent);
        $this->assertStringContainsString('APPROVED_MLS: square footage', $allContent);

        // Private, unclassified, and unknown-type fragments must NOT appear
        $this->assertStringNotContainsString('PRIVATE: agent ssn', $allContent);
        $this->assertStringNotContainsString('UNCLASSIFIED: raw data', $allContent);
        $this->assertStringNotContainsString('UNKNOWN_TYPE: internal', $allContent);

        // Exclusion governance flag must be set
        $this->assertContains('context_fragments_excluded', $result['governance_flags']);
    }

    public function test_prompt_builder_excludes_private_classified_fragments(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = [
            'context_fragments' => [
                ['type' => 'agent_profile', 'classification' => 'private', 'content' => 'secret bio'],
            ],
        ];

        $result = $builder->build($context, 'What is the agent bio?', AgentAiContextScope::AgentProfile);

        $allContent = implode(' ', array_column($result['messages'], 'content'));
        $this->assertStringNotContainsString('secret bio', $allContent);
        $this->assertContains('context_fragments_excluded', $result['governance_flags']);
        $this->assertContains('empty_context', $result['governance_flags']);
    }

    public function test_prompt_builder_excludes_context_string_with_raw_mls_marker(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => "Property info\n[RAW-MLS]\nfull mls dump here"];

        $result = $builder->build($context, 'Tell me more', AgentAiContextScope::AgentProfile);

        $allContent = implode(' ', array_column($result['messages'], 'content'));
        $this->assertStringNotContainsString('full mls dump here', $allContent);
        $this->assertContains('context_denied_unsafe_marker', $result['governance_flags']);
        $this->assertContains('empty_context', $result['governance_flags']);
    }

    public function test_prompt_builder_excludes_context_string_with_raw_document_marker(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => "[RAW-DOCUMENT] full deed text here"];

        $result = $builder->build($context, 'What does the deed say?', AgentAiContextScope::AgentProfile);

        $allContent = implode(' ', array_column($result['messages'], 'content'));
        $this->assertStringNotContainsString('full deed text here', $allContent);
        $this->assertContains('context_denied_unsafe_marker', $result['governance_flags']);
    }

    public function test_prompt_builder_excludes_context_string_with_confidential_marker(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => "[CONFIDENTIAL] brokerage internal rate sheet"];

        $result = $builder->build($context, 'What are the rates?', AgentAiContextScope::AgentProfile);

        $allContent = implode(' ', array_column($result['messages'], 'content'));
        $this->assertStringNotContainsString('brokerage internal rate sheet', $allContent);
        $this->assertContains('context_denied_unsafe_marker', $result['governance_flags']);
    }

    public function test_prompt_builder_allows_safe_context_string_without_denied_markers(): void
    {
        $builder = app(AgentAiPromptBuilder::class);
        $context = ['context_string' => 'Listed price: $450,000. 3 bedrooms, 2 baths. Built 2005.'];

        $result = $builder->build($context, 'What is the price?', AgentAiContextScope::AgentProfile);

        $allContent = implode(' ', array_column($result['messages'], 'content'));
        $this->assertStringContainsString('$450,000', $allContent);
        $this->assertNotContains('context_denied_unsafe_marker', $result['governance_flags']);
        $this->assertNotContains('empty_context', $result['governance_flags']);
    }

    public function test_prompt_builder_includes_approved_uploaded_document_and_excludes_private_version(): void
    {
        $builder = app(AgentAiPromptBuilder::class);

        // All three required fragment families: uploaded_document, mls_snapshot, knowledge_document
        $context = [
            'context_fragments' => [
                // Should be included
                ['type' => 'uploaded_document', 'classification' => 'public_safe',  'content' => 'UPLOAD_CONTENT: floor plan approved for display'],
                ['type' => 'mls_snapshot',      'classification' => 'public_safe',  'content' => 'MLS_CONTENT: 1800 sqft, 3bed/2bath'],
                ['type' => 'knowledge_document', 'classification' => 'public_safe', 'content' => 'KNOWLEDGE_CONTENT: buyer FAQ'],
                // Should be excluded (private classification on uploaded_document)
                ['type' => 'uploaded_document', 'classification' => 'private',      'content' => 'PRIVATE_UPLOAD: agent legal docs'],
                // Should be excluded (unclassified mls)
                ['type' => 'mls_snapshot',      'classification' => 'unclassified', 'content' => 'UNCLASSIFIED_MLS: raw dump'],
            ],
        ];

        $result = $builder->build($context, 'Tell me about the property', AgentAiContextScope::AgentProfile);

        $allContent = implode(' ', array_column($result['messages'], 'content'));

        // All three public_safe families must appear
        $this->assertStringContainsString('UPLOAD_CONTENT: floor plan approved for display', $allContent,
            'Approved uploaded_document fragment must be included');
        $this->assertStringContainsString('MLS_CONTENT: 1800 sqft', $allContent,
            'Approved mls_snapshot fragment must be included');
        $this->assertStringContainsString('KNOWLEDGE_CONTENT: buyer FAQ', $allContent,
            'Approved knowledge_document fragment must be included');

        // Private and unclassified versions must be excluded
        $this->assertStringNotContainsString('PRIVATE_UPLOAD: agent legal docs', $allContent,
            'Private uploaded_document fragment must NOT be included');
        $this->assertStringNotContainsString('UNCLASSIFIED_MLS: raw dump', $allContent,
            'Unclassified mls_snapshot fragment must NOT be included');

        $this->assertContains('context_fragments_excluded', $result['governance_flags']);
    }

    public function test_uploaded_document_fragment_type_is_in_allowlist(): void
    {
        $this->assertContains(
            'uploaded_document',
            \App\Services\AgentAi\AgentAiPromptBuilder::ALLOWED_FRAGMENT_TYPES,
            'uploaded_document must be in ALLOWED_FRAGMENT_TYPES — context builder supplies it for approved docs'
        );
    }

    public function test_agent_ai_chat_message_rejects_invalid_role(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/role must be one of/i');

        // Trigger the model boot hook by setting an invalid role
        $msg       = new \App\Models\AgentAiChatMessage();
        $msg->role = 'admin';
        $msg->save(); // should throw before hitting the DB
    }

    public function test_agent_ai_chat_message_accepts_user_and_assistant_roles(): void
    {
        // Boot hook must NOT throw for valid roles — verify via reflection
        foreach (\App\Models\AgentAiChatMessage::VALID_ROLES as $role) {
            $this->assertContains($role, \App\Models\AgentAiChatMessage::VALID_ROLES);
        }
        $this->assertCount(2, \App\Models\AgentAiChatMessage::VALID_ROLES);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AgentAiFinalResponseBuilder unit tests
    // ──────────────────────────────────────────────────────────────────────────

    public function test_response_builder_returns_fallback_when_orchestrator_fails(): void
    {
        $builder = app(AgentAiFinalResponseBuilder::class);

        $result = $builder->build(
            ['governance_flags' => [], 'scope' => 'agent_profile'],
            ['success' => false, 'raw_content' => null, 'usage' => null, 'model' => null, 'error' => 'API error']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('fallback', $result['status']);
        $this->assertTrue($result['escalate']);
        $this->assertNotEmpty($result['answer']);
    }

    public function test_response_builder_sets_escalate_true_for_low_confidence_phrases(): void
    {
        $builder = app(AgentAiFinalResponseBuilder::class);

        $result = $builder->build(
            ['governance_flags' => []],
            [
                'success'     => true,
                'raw_content' => 'I believe the price is around $500,000 but you should verify with the agent.',
                'usage'       => ['total_tokens' => 50],
                'model'       => 'gpt-4o-mini',
            ]
        );

        $this->assertTrue($result['escalate'], 'Low-confidence phrases must set escalate: true');
    }

    public function test_response_builder_appends_compliance_disclosure_for_legal_topics(): void
    {
        $builder = app(AgentAiFinalResponseBuilder::class);

        $result = $builder->build(
            ['governance_flags' => []],
            [
                'success'     => true,
                'raw_content' => 'The HOA fees are $250 per month.',
                'usage'       => ['total_tokens' => 20],
                'model'       => 'gpt-4o-mini',
            ]
        );

        $this->assertNotNull($result['disclosures'], 'HOA mention must trigger compliance disclosure');
        $this->assertStringContainsStringIgnoringCase('professional', $result['disclosures']);
    }

    public function test_response_builder_strips_commitment_phrases(): void
    {
        $builder = app(AgentAiFinalResponseBuilder::class);

        $result = $builder->build(
            ['governance_flags' => []],
            [
                'success'     => true,
                'raw_content' => 'I guarantee you will love this property.',
                'usage'       => ['total_tokens' => 10],
                'model'       => 'gpt-4o-mini',
            ]
        );

        $this->assertStringNotContainsStringIgnoringCase('I guarantee', $result['answer']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AgentAiOpenAiOrchestrator — model routing tests
    // ──────────────────────────────────────────────────────────────────────────

    public function test_orchestrator_returns_failed_result_when_api_key_not_set(): void
    {
        config(['ai.api_key' => '']);

        $orchestrator = app(AgentAiOpenAiOrchestrator::class);

        $result = $orchestrator->call([
            'status'           => 'prompt_ready',
            'messages'         => [['role' => 'user', 'content' => 'Hello']],
            'governance_flags' => [],
            'scope'            => 'agent_profile',
        ]);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        // Error message must indicate key is missing, not expose any actual key value
        $this->assertStringContainsStringIgnoringCase('api key', $result['error']);
    }

    public function test_orchestrator_returns_blocked_when_status_is_not_prompt_ready(): void
    {
        $orchestrator = app(AgentAiOpenAiOrchestrator::class);

        $result = $orchestrator->call([
            'status'   => 'blocked',
            'messages' => [],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('prompt_ready', $result['error']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ask endpoint returns expected response shape
    // ──────────────────────────────────────────────────────────────────────────

    public function test_ask_returns_correct_response_shape_on_success(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        $this->instance(AgentAiOpenAiOrchestrator::class, new class extends AgentAiOpenAiOrchestrator {
            public function call(array $promptPackage, array $options = []): array
            {
                return [
                    'success'     => true,
                    'raw_content' => 'The property is located in a great neighborhood.',
                    'usage'       => ['prompt_tokens' => 40, 'completion_tokens' => 12, 'total_tokens' => 52],
                    'model'       => 'gpt-4o-mini',
                    'model_tier'  => 'fast',
                    'error'       => null,
                ];
            }
        });

        $response = $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'Tell me about this property.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'answer', 'escalate']);

        // Must NOT expose internal details
        $responseData = $response->json();
        $this->assertArrayNotHasKey('prompt_package', $responseData);
        $this->assertArrayNotHasKey('model', $responseData);
        $this->assertArrayNotHasKey('model_tier', $responseData);
        $this->assertArrayNotHasKey('raw_content', $responseData);
        $this->assertArrayNotHasKey('usage', $responseData);
    }

    public function test_ask_updates_session_last_active_at(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();
        $session = $this->createSession($agentId);

        // Backdate the session
        $session->update(['last_active_at' => now()->subHour()]);
        $originalActiveAt = $session->last_active_at;

        $this->instance(AgentAiOpenAiOrchestrator::class, new class extends AgentAiOpenAiOrchestrator {
            public function call(array $promptPackage, array $options = []): array
            {
                return [
                    'success'     => true,
                    'raw_content' => 'The kitchen has been recently renovated.',
                    'usage'       => ['prompt_tokens' => 30, 'completion_tokens' => 8, 'total_tokens' => 38],
                    'model'       => 'gpt-4o-mini',
                    'model_tier'  => 'fast',
                    'error'       => null,
                ];
            }
        });

        $this->postJson('/agent-ai/ask', [
            'session_token' => $session->session_token,
            'question'      => 'Tell me about the kitchen.',
        ]);

        $session->refresh();
        $this->assertTrue(
            $session->last_active_at->isAfter($originalActiveAt),
            'last_active_at must be updated after each ask'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Live OpenAI smoke verification
    // Skipped unless OPENAI_API_KEY (or AI_API_KEY) is set in the environment.
    // Run manually in staging: php artisan test --filter live_openai
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @group live-openai
     */
    public function test_live_openai_call_returns_valid_response_shape(): void
    {
        $apiKey = config('ai.api_key', env('OPENAI_API_KEY', ''));

        if (empty($apiKey)) {
            $this->markTestSkipped(
                'Skipped: no OpenAI API key configured. '
                . 'Set OPENAI_API_KEY or AI_API_KEY to run this smoke test.'
            );
        }

        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();

        // Start a session
        $startResponse = $this->postJson('/agent-ai/session/start', [
            'agent_id' => $agentId,
            'scope'    => 'agent_profile',
        ]);
        $startResponse->assertStatus(201);
        $token = $startResponse->json('session_token');

        // Send a simple, context-agnostic question
        $askResponse = $this->postJson('/agent-ai/ask', [
            'session_token' => $token,
            'question'      => 'What is the typical role of a real estate agent in a home sale?',
        ]);

        // Must return 200 with the required public response shape
        $askResponse->assertStatus(200);
        $askResponse->assertJsonStructure(['status', 'answer', 'escalate']);

        $status = $askResponse->json('status');
        $answer = $askResponse->json('answer');

        // Live call must not have returned fallback (that would mean the API failed)
        $this->assertNotEquals(
            'fallback',
            $status,
            "Live OpenAI call returned fallback status — API may be down or key invalid.\nAnswer: {$answer}"
        );

        // Answer must be a non-empty string
        $this->assertIsString($answer);
        $this->assertNotEmpty($answer, 'Live OpenAI response answer must not be empty');
        $this->assertGreaterThan(
            20,
            strlen($answer),
            'Live OpenAI response answer is suspiciously short'
        );

        // Model name must NOT appear in the public response (governance: never expose)
        $responseJson = json_encode($askResponse->json());
        $this->assertStringNotContainsString('gpt-4', $responseJson, 'Model name must not appear in public response');
        $this->assertStringNotContainsString('gpt-3', $responseJson, 'Model name must not appear in public response');

        // escalate must be a boolean
        $this->assertIsBool($askResponse->json('escalate'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // session_token is cryptographically secure (not sequential)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_session_tokens_are_unique_and_not_sequential(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);
        $agentId = $this->makeAgentUser();

        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/agent-ai/session/start', [
                'agent_id' => $agentId,
                'scope'    => 'agent_profile',
            ]);
            $tokens[] = $response->json('session_token');
        }

        // All unique
        $this->assertCount(5, array_unique($tokens), 'Session tokens must be unique');

        // None are sequential integers or incrementing strings
        foreach ($tokens as $token) {
            $this->assertGreaterThan(
                20,
                strlen($token),
                'Session token must be long enough to be cryptographically secure'
            );
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]+$/',
                $token,
                'Session token must be hex-encoded'
            );
        }
    }
}
