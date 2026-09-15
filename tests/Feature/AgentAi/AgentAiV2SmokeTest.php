<?php

namespace Tests\Feature\AgentAi;

use App\Models\User;
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
 *  - V1 route  → POST /ask-ai/listing-question still reaches its controller (authenticated)
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

        // Targets /ask-ai/listing-question, the V1 route every Ask AI surface in the
        // product actually posts to. This assertion previously used /ask-ai/ask, the
        // unauthenticated endpoint that P0 removed for having no caller at all — which
        // made it the wrong route to guard the V1 pipeline with.
        //
        // P0.1 — the request is now AUTHENTICATED. /ask-ai/listing-question carries 'auth'
        // middleware, so a guest post is rejected with 401 before the controller is even
        // resolved: "not 404/405" would then hold for a route whose controller was broken or
        // deleted outright, which is not what this test exists to prove. Authenticating gets
        // past the middleware so the assertion lands where the original one did — on the
        // controller's own validation.
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/ask-ai/listing-question', []);

        // 422 with these three field errors can only come from the controller's validate()
        // call — it proves the V1 controller ran, not merely that a route is registered.
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['listing_type', 'listing_id', 'question']);
    }
}
