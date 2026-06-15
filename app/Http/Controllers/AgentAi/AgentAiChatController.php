<?php

namespace App\Http\Controllers\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use App\Http\Controllers\Controller;
use App\Models\AgentAiChatMessage;
use App\Models\AgentAiChatSession;
use App\Services\AgentAi\AgentAiContextBuilder;
use App\Services\AgentAi\AgentAiFinalResponseBuilder;
use App\Services\AgentAi\AgentAiOpenAiOrchestrator;
use App\Services\AgentAi\AgentAiPermissionGuard;
use App\Services\AgentAi\AgentAiPromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AgentAiChatController
 *
 * Handles the V2 Agent AI conversation endpoints:
 *
 *   POST /agent-ai/session/start  — create or resume a session
 *   POST /agent-ai/ask            — submit a question and receive an AI answer
 *
 * GOVERNANCE:
 *   - Never expose prompt contents, raw context blocks, API keys, or internal
 *     model-selection details in any response.
 *   - All reads go through AgentAiPermissionGuard before context is loaded.
 *   - Message persistence uses AgentAiChatMessage; no other tables are written.
 *   - Offer, counter-offer, and competing-bid tables are never queried
 *     (enforced by AgentAiPermissionGuard::BLOCKED_TABLES and asserted in tests).
 */
class AgentAiChatController extends Controller
{
    public function __construct(
        private readonly AgentAiPermissionGuard  $permissionGuard,
        private readonly AgentAiContextBuilder   $contextBuilder,
        private readonly AgentAiPromptBuilder    $promptBuilder,
        private readonly AgentAiOpenAiOrchestrator    $orchestrator,
        private readonly AgentAiFinalResponseBuilder  $responseBuilder,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // POST /agent-ai/session/start
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a new session or resume an existing one by token.
     *
     * Request body:
     *   agent_id     (int, required)
     *   scope        (string, required) — one of AgentAiContextScope values
     *   listing_type (string, nullable)
     *   listing_id   (int, nullable)
     *   session_token (string, optional) — if provided and valid, resumes that session
     *   visitor_ip   (string, optional)
     *   channel      (string, optional) — must be in AgentAiChatSession::ALLOWED_CHANNELS
     *   channel_user_id (string, optional)
     *
     * Response: { session_token, scope, resumed }
     */
    public function startSession(Request $request): JsonResponse
    {
        $agentId     = (int) $request->input('agent_id', 0);
        $scopeValue  = (string) $request->input('scope', '');
        $listingType = $request->input('listing_type');
        $listingId   = $request->input('listing_id') !== null
            ? (int) $request->input('listing_id')
            : null;
        $resumeToken = $request->input('session_token');
        $channel     = $request->input('channel');
        $channelUserId = $request->input('channel_user_id');

        // ── Validate scope ────────────────────────────────────────────────────
        $scope = AgentAiContextScope::tryFrom($scopeValue);
        if ($scope === null) {
            return response()->json([
                'status' => 'error',
                'error'  => 'Invalid scope. Must be one of: '
                    . implode(', ', array_column(AgentAiContextScope::cases(), 'value')),
            ], 422);
        }

        // ── Validate channel if provided ──────────────────────────────────────
        if ($channel !== null && !in_array($channel, AgentAiChatSession::ALLOWED_CHANNELS, true)) {
            return response()->json([
                'status' => 'error',
                'error'  => 'Invalid channel. Allowed values: '
                    . implode(', ', AgentAiChatSession::ALLOWED_CHANNELS),
            ], 422);
        }

        // ── Validate agent ownership ──────────────────────────────────────────
        try {
            $this->permissionGuard->validateAgentScope($scope, $agentId, $listingType, $listingId);
        } catch (AgentAiPermissionException $e) {
            return response()->json([
                'status' => 'error',
                'error'  => 'Access denied.',
                'reason' => $e->getReason(),
            ], $e->getHttpStatus());
        }

        // ── Resume existing session ────────────────────────────────────────────
        if (!empty($resumeToken)) {
            $existing = AgentAiChatSession::where('session_token', $resumeToken)
                ->where('agent_id', $agentId)
                ->whereNull('ended_at')
                ->first();

            if ($existing !== null) {
                $existing->update(['last_active_at' => now()]);

                return response()->json([
                    'status'        => 'resumed',
                    'session_token' => $existing->session_token,
                    'scope'         => $existing->scope,
                    'resumed'       => true,
                ]);
            }
        }

        // ── Create new session ─────────────────────────────────────────────────
        $session = AgentAiChatSession::create([
            'session_token'  => $this->generateSecureToken(),
            'agent_id'       => $agentId,
            'scope'          => $scope->value,
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'visitor_ip'     => $request->ip(),
            'started_at'     => now(),
            'last_active_at' => now(),
            'channel'        => $channel,
            'channel_user_id' => $channelUserId,
        ]);

        return response()->json([
            'status'        => 'created',
            'session_token' => $session->session_token,
            'scope'         => $session->scope,
            'resumed'       => false,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /agent-ai/ask
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Submit a question to the V2 pipeline and receive an AI answer.
     *
     * Request body:
     *   session_token (string, required)
     *   question      (string, required)
     *
     * Response: { status, answer, escalate }
     *
     * Pipeline:
     *   1. permission guard (check session token)
     *   2. load context (AgentAiContextBuilder::buildForScope)
     *   3. build prompt (AgentAiPromptBuilder::build)
     *   4. call OpenAI (AgentAiOpenAiOrchestrator::call)
     *   5. normalize response (AgentAiFinalResponseBuilder::build)
     *   6. persist user + assistant messages
     *   7. update session last_active_at
     */
    public function ask(Request $request): JsonResponse
    {
        $question = trim((string) $request->input('question', ''));

        if ($question === '') {
            return response()->json([
                'status' => 'error',
                'error'  => 'question is required.',
            ], 422);
        }

        // ── Permission guard ─────────────────────────────────────────────────
        // check() resolves the session's stored scope and re-validates ownership.
        $guard = $this->permissionGuard->check($request, AgentAiContextScope::AgentProfile);

        if (!$guard['allowed']) {
            return response()->json([
                'status' => 'error',
                'error'  => 'Access denied.',
                'reason' => $guard['reason'],
            ], $guard['http_status']);
        }

        /** @var AgentAiChatSession $session */
        $session     = $guard['session'];
        $resolvedScope = AgentAiContextScope::tryFrom($session->scope);

        if ($resolvedScope === null) {
            Log::error('AgentAiChatController: session has invalid scope', [
                'session_id' => $session->id,
                'scope'      => $session->scope,
            ]);
            return response()->json([
                'status' => 'error',
                'error'  => 'Session scope is invalid.',
            ], 500);
        }

        // ── Load conversation history ─────────────────────────────────────────
        // Load the FULL session history (oldest first) and let AgentAiPromptBuilder
        // decide which turns are verbatim and which are condensed into a summary.
        // Never pre-cap here — silently dropping older turns would bypass
        // the prompt builder's summarization logic.
        $historyMessages = $session->messages()->get()->all();

        // ── Build context ─────────────────────────────────────────────────────
        $context = [];
        try {
            $context = $this->contextBuilder->buildForScope(
                $resolvedScope,
                (int) $session->agent_id,
                $session->listing_type,
                $session->listing_id ? (int) $session->listing_id : null
            );
        } catch (AgentAiPermissionException $e) {
            return response()->json([
                'status' => 'error',
                'error'  => 'Access denied.',
                'reason' => $e->getReason(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('AgentAiChatController: context build failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
            // Non-fatal — continue with empty context; prompt builder handles gracefully
            $context = [
                'scope'             => $resolvedScope->value,
                'context_string'    => '',
                'total_token_estimate' => 0,
                'missing_sources'   => ['all'],
                'truncated_sources' => [],
                'assembled_at'      => now()->toISOString(),
            ];
        }

        // ── Build prompt ──────────────────────────────────────────────────────
        $promptPackage = $this->promptBuilder->build(
            $context,
            $question,
            $resolvedScope,
            $historyMessages
        );

        // ── Call OpenAI ───────────────────────────────────────────────────────
        $adapterResult = $this->orchestrator->call($promptPackage);

        // ── Normalize response ────────────────────────────────────────────────
        $finalResponse = $this->responseBuilder->build($promptPackage, $adapterResult);

        // ── Persist messages ──────────────────────────────────────────────────
        $tokensUsed  = $finalResponse['tokens_used'] ?? 0;
        $contextScope = $resolvedScope->value;

        AgentAiChatMessage::create([
            'session_id'    => $session->id,
            'role'          => AgentAiChatMessage::ROLE_USER,
            'content'       => $question,
            'context_scope' => $contextScope,
            'tokens_used'   => null,
            'created_at'    => now(),
        ]);

        AgentAiChatMessage::create([
            'session_id'    => $session->id,
            'role'          => AgentAiChatMessage::ROLE_ASSISTANT,
            'content'       => $finalResponse['answer'] ?? '',
            'context_scope' => $contextScope,
            'tokens_used'   => $tokensUsed > 0 ? $tokensUsed : null,
            'created_at'    => now(),
        ]);

        // ── Update session activity ───────────────────────────────────────────
        $session->update(['last_active_at' => now()]);

        // ── Return public response ────────────────────────────────────────────
        // GOVERNANCE: Never expose prompt contents, raw context, API keys,
        // model names, or internal orchestrator details in the response.
        return response()->json([
            'status'   => $finalResponse['status'],
            'answer'   => $finalResponse['answer'],
            'escalate' => $finalResponse['escalate'],
        ]);
    }

    /**
     * Generate a cryptographically secure random session token.
     * Never sequential, never predictable.
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
