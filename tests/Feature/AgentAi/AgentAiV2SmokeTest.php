<?php

namespace Tests\Feature\AgentAi;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AgentAiV2SmokeTest
 *
 * Feature-flag gate tests for the V2 routes.
 *
 * Assertions:
 *  - Flag OFF  → POST /agent-ai/ask           returns 404
 *  - Flag OFF  → POST /agent-ai/session/start returns 404
 *  - Flag ON   → POST /agent-ai/ask           returns a real response (not 404/405)
 *  - Flag ON   → POST /agent-ai/session/start returns a real response (not 404/405)
 *  - V1 route  → POST /ask-ai/ask             still resolves (unchanged)
 *
 * Build 1 note: Originally asserted {"status":"not_implemented"}. Updated in
 * Build 3 when the controller was fully implemented.
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
    // Flag ON — both V2 routes must be reachable (controller is implemented)
    // ──────────────────────────────────────────────────────────────────────

    public function test_ask_is_reachable_when_flag_is_on(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $response = $this->postJson('/agent-ai/ask', []);

        // A 422 (missing question) or 400 (missing session token) means the controller
        // ran — it is no longer returning not_implemented.
        $this->assertNotEquals(404, $response->getStatusCode(), 'V2 /agent-ai/ask must not return 404 when flag is on');
        $this->assertNotEquals(405, $response->getStatusCode(), 'V2 /agent-ai/ask must not return 405');
        $this->assertNotEquals(500, $response->getStatusCode(), 'V2 /agent-ai/ask must not return 500');
    }

    public function test_session_start_is_reachable_when_flag_is_on(): void
    {
        config(['ask_ai.agent_ai_v2_enabled' => true]);

        $response = $this->postJson('/agent-ai/session/start', []);

        // A 422 (invalid scope) means the controller ran validation — it is implemented.
        $this->assertNotEquals(404, $response->getStatusCode(), 'V2 /agent-ai/session/start must not return 404 when flag is on');
        $this->assertNotEquals(405, $response->getStatusCode(), 'V2 /agent-ai/session/start must not return 405');
        $this->assertNotEquals(500, $response->getStatusCode(), 'V2 /agent-ai/session/start must not return 500');
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
