<?php

namespace Tests\Feature\AgentAi;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentAiV2SmokeTest
 *
 * Build 1 smoke tests — verifies the feature flag gate and placeholder responses.
 * No OpenAI calls. No DB writes. No context loading.
 *
 * Assertions:
 *  - Flag OFF  → POST /agent-ai/ask        returns 404
 *  - Flag OFF  → POST /agent-ai/session/start returns 404
 *  - Flag ON   → POST /agent-ai/ask        returns 200 with {"status":"not_implemented"}
 *  - Flag ON   → POST /agent-ai/session/start returns 200 with {"status":"not_implemented"}
 *  - V1 route  → POST /ask-ai/ask          still resolves (unchanged)
 */
class AgentAiV2SmokeTest extends TestCase
{
    use DatabaseTransactions;

    // ──────────────────────────────────────────────────────────────────────
    // Flag OFF — both V2 routes must return 404
    // ──────────────────────────────────────────────────────────────────────

    public function test_ask_returns_404_when_flag_is_off(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => false]);

        $response = $this->postJson('/agent-ai/ask', []);

        $response->assertStatus(404);
    }

    public function test_session_start_returns_404_when_flag_is_off(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => false]);

        $response = $this->postJson('/agent-ai/session/start', []);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Flag ON — both V2 routes must return placeholder JSON
    // ──────────────────────────────────────────────────────────────────────

    public function test_ask_returns_placeholder_json_when_flag_is_on(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $response = $this->postJson('/agent-ai/ask', []);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'not_implemented']);
    }

    public function test_session_start_returns_placeholder_json_when_flag_is_on(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $response = $this->postJson('/agent-ai/session/start', []);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'not_implemented']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // V1 is untouched — the existing Ask AI route still resolves
    // ──────────────────────────────────────────────────────────────────────

    public function test_v1_ask_ai_route_still_resolves_regardless_of_v2_flag(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => false]);

        // We only verify the route exists and is reachable (not 404/405).
        // A 422 means validation ran — the V1 controller is alive.
        $response = $this->postJson('/ask-ai/ask', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'V1 /ask-ai/ask must not return 404');
        $this->assertNotEquals(405, $response->getStatusCode(), 'V1 /ask-ai/ask must not return 405 (Method Not Allowed)');
    }
}
