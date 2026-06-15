<?php

namespace App\Http\Controllers\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use App\Http\Controllers\Controller;
use App\Models\AgentAiChatMessage;
use App\Models\AgentAiChatSession;
use App\Services\AgentAi\AgentAiActionResolver;
use App\Services\AgentAi\AgentAiContextBuilder;
use App\Services\AgentAi\AgentAiEscalationService;
use App\Services\AgentAi\AgentAiFinalResponseBuilder;
use App\Services\AgentAi\AgentAiLeadCaptureService;
use App\Services\AgentAi\AgentAiLeadIntentDetector;
use App\Services\AgentAi\AgentAiLeadScoringService;
use App\Services\AgentAi\AgentAiNotificationService;
use App\Services\AgentAi\AgentAiOpenAiOrchestrator;
use App\Services\AgentAi\AgentAiPermissionGuard;
use App\Services\AgentAi\AgentAiPromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AgentAiChatController
 *
 * Handles the V2 Agent AI conversation endpoints:
 *
 *   POST /agent-ai/session/start  — create or resume a session
 *   POST /agent-ai/ask            — submit a question and receive an AI answer
 *   POST /agent-ai/escalate       — confirm visitor escalation to agent (Build 5)
 *
 * GOVERNANCE:
 *   - Never expose prompt contents, raw context blocks, API keys, or internal
 *     model-selection details in any response.
 *   - All reads go through AgentAiPermissionGuard before context is loaded.
 *   - Message persistence uses AgentAiChatMessage; lead data uses AgentAiChatLead.
 *   - Offer, counter-offer, and competing-bid tables are never queried
 *     (enforced by AgentAiPermissionGuard::BLOCKED_TABLES and asserted in tests).
 */
class AgentAiChatController extends Controller
{
    public function __construct(
        private readonly AgentAiPermissionGuard       $permissionGuard,
        private readonly AgentAiContextBuilder        $contextBuilder,
        private readonly AgentAiPromptBuilder         $promptBuilder,
        private readonly AgentAiOpenAiOrchestrator    $orchestrator,
        private readonly AgentAiFinalResponseBuilder  $responseBuilder,
        private readonly AgentAiActionResolver        $actionResolver,
        private readonly AgentAiLeadIntentDetector    $intentDetector,
        private readonly AgentAiLeadScoringService    $leadScorer,
        private readonly AgentAiLeadCaptureService    $leadCapture,
        private readonly AgentAiNotificationService   $notificationService,
        private readonly AgentAiEscalationService     $escalationService,
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

        // ── Per-scope rollout flag check ───────────────────────────────────────
        // If neither the global flag nor this scope's individual flag is enabled,
        // return 404 — V1 serves this page type.
        if (!$this->isScopeEnabled($scope)) {
            return response()->json([
                'status' => 'error',
                'error'  => 'This scope is not yet available.',
            ], 404);
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

        // ── Auto-attach signed-in visitor (Build 5) ───────────────────────────
        $visitorUserId = Auth::id();

        // ── Create new session ─────────────────────────────────────────────────
        $session = AgentAiChatSession::create([
            'session_token'   => $this->generateSecureToken(),
            'agent_id'        => $agentId,
            'scope'           => $scope->value,
            'listing_type'    => $listingType,
            'listing_id'      => $listingId,
            'visitor_user_id' => $visitorUserId,
            'visitor_ip'      => $request->ip(),
            'started_at'      => now(),
            'last_active_at'  => now(),
            'channel'         => $channel,
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
     *   action_key    (string, optional) — when 'view_agent_services', bypasses OpenAI
     *
     * Response: { status, answer, escalate, actions, capture_contact_prompt? }
     *
     * Pipeline (standard):
     *   1. permission guard (check session token)
     *   2. load context (AgentAiContextBuilder::buildForScope)
     *   3. build prompt (AgentAiPromptBuilder::build)
     *   4. call OpenAI (AgentAiOpenAiOrchestrator::call)
     *   5. normalize response (AgentAiFinalResponseBuilder::build)
     *   6. detect intent, score turn, update lead record (Build 5)
     *   7. persist user + assistant messages (with detected_intent + lead_score_snapshot)
     *   8. fire threshold notifications (Build 5)
     *   9. update session last_active_at
     *
     * Pipeline (action_key = 'view_agent_services'):
     *   1. permission guard (check session token)
     *   2. AgentAiActionResolver::resolveInlineServices() — OpenAI is bypassed
     *   3. persist user + assistant messages
     *   4. update session last_active_at
     */
    public function ask(Request $request): JsonResponse
    {
        $question  = trim((string) $request->input('question', ''));
        $actionKey = trim((string) $request->input('action_key', ''));

        // Known action keys that can replace a question (bypass or enrich the pipeline).
        $knownActionKeys = [
            AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES,
        ];

        if ($question === '' && !in_array($actionKey, $knownActionKeys, true)) {
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
        $session       = $guard['session'];
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

        // ── Per-scope rollout flag check ───────────────────────────────────────
        // Re-validates that the scope is still enabled; guards against sessions
        // that were started before a scope was rolled back.
        if (!$this->isScopeEnabled($resolvedScope)) {
            return response()->json([
                'status' => 'error',
                'error'  => 'This scope is not yet available.',
            ], 404);
        }

        $agentId   = (int) $session->agent_id;
        $listingId = $session->listing_id ? (int) $session->listing_id : null;

        // ── Inline handler: View Agent's Services ─────────────────────────────
        if ($actionKey === AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES) {
            return $this->handleViewAgentServices($session, $resolvedScope, $agentId, $listingId, $question);
        }

        // ── Load conversation history ─────────────────────────────────────────
        $historyMessages = $session->messages()->get()->all();

        // ── Build context ─────────────────────────────────────────────────────
        $context = [];
        try {
            $context = $this->contextBuilder->buildForScope(
                $resolvedScope,
                $agentId,
                $session->listing_type,
                $listingId
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
            $context = [
                'scope'                => $resolvedScope->value,
                'context_string'       => '',
                'total_token_estimate' => 0,
                'missing_sources'      => ['all'],
                'truncated_sources'    => [],
                'assembled_at'         => now()->toISOString(),
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
        // Wrap in try/catch so transport-layer exceptions (timeout, misconfigured
        // HTTP client, unexpected provider error) never bubble up as HTTP 500.
        // Any throw is normalized to success: false so AgentAiFinalResponseBuilder
        // applies the standard graceful fallback path.
        try {
            $adapterResult = $this->orchestrator->call($promptPackage);
        } catch (\Throwable $e) {
            Log::error('AgentAiChatController: orchestrator threw an uncaught exception', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
            ]);
            $adapterResult = [
                'success'     => false,
                'raw_content' => null,
                'usage'       => null,
                'model'       => null,
                'model_tier'  => null,
                'error'       => 'orchestrator_exception',
            ];
        }

        // ── Normalize response (includes action resolution) ───────────────────
        $finalResponse = $this->responseBuilder->build($promptPackage, $adapterResult, [
            'scope'      => $resolvedScope,
            'agent_id'   => $agentId,
            'listing_id' => $listingId,
        ]);

        // ── Build 5: Intent detection + lead scoring ───────────────────────────
        // $detectedSignal is used for scoring only (not stored on the message).
        // $detectedIntent stores the lead-type taxonomy (buyer/seller/etc.) on
        // agent_ai_chat_messages.detected_intent for inbox display.
        $detectedSignal = $this->intentDetector->detectSignal($question);
        $detectedIntent = $this->intentDetector->detectLeadType($question);

        // Determine which contact fields have already been collected from prior
        // persisted messages so scoreTurn() does not double-award bonuses.
        $priorUserMessages = array_filter($historyMessages, fn ($m) => $m->role === 'user');
        $collectedFields   = [];
        foreach ($priorUserMessages as $msg) {
            if ($this->intentDetector->containsEmail($msg->content)) {
                $collectedFields[] = 'email';
            }
            if ($this->intentDetector->containsPhone($msg->content)) {
                $collectedFields[] = 'phone';
            }
        }
        $collectedFields = array_unique($collectedFields);

        $currentScore    = $this->leadScorer->accumulateForSession($session->id);
        $turnResult      = $this->leadScorer->scoreTurn($question, $collectedFields);
        $newScore        = min($currentScore + $turnResult['points'], 100);

        // ── Build 5: Contact capture trigger for anonymous high-intent visitors
        $captureContactPrompt = null;
        $visitorUserId        = $session->visitor_user_id;

        if ($this->leadCapture->shouldPromptForContact($detectedSignal, $visitorUserId)) {
            $captureContactPrompt = 'It looks like you\'re interested in taking the next step! '
                . 'Please share your name and best contact info so the agent can follow up with you.';
        }

        // ── Build 5: Upsert lead record when high-intent or score >= 50 ───────
        $lead = null;
        if ($detectedSignal !== null || $newScore >= 50) {
            try {
                $detectedLeadType = $this->intentDetector->detectLeadType($question);
                $lead = $this->leadCapture->recordOrUpdate($session->id, array_filter([
                    'lead_type'    => $detectedLeadType,
                    'intent_phrase' => $question,
                    'lead_score'   => $newScore,
                    'listing_type' => $session->listing_type,
                    'listing_id'   => $listingId,
                    'question'     => $question,
                ], fn ($v) => $v !== null));

                // Finalize: generate a deterministic conversation summary and
                // follow-up suggestion once the lead crosses score >= 50.
                if ($newScore >= 50 && empty($lead->conversation_summary)) {
                    $questions = $lead->questions_asked ?? [];
                    $leadTypeLabel = $lead->lead_type
                        ? ucfirst(str_replace('_', ' ', $lead->lead_type))
                        : 'Unclassified';
                    $topQuestions = implode('; ', array_slice($questions, 0, 5));
                    $summary = "Lead expressed interest as a {$leadTypeLabel}."
                        . ($topQuestions ? " Asked about: {$topQuestions}." : '');
                    $followUp = 'Follow up about their interest in '
                        . ($lead->intent_phrase ?? 'your services') . '.';
                    $this->leadCapture->finalize($session->id, $summary, $followUp);
                    $lead->refresh();
                }
            } catch (\Throwable $e) {
                Log::warning('AgentAiChatController: lead record upsert failed', [
                    'session_id' => $session->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // ── Build 5: Escalation — if escalate: true, trigger escalation prompt
        $escalatePromptText = null;
        if (!empty($finalResponse['escalate'])) {
            $escalatePromptText = $this->escalationService->buildEscalationPrompt();
        }

        // ── Persist messages ──────────────────────────────────────────────────
        $tokensUsed   = $finalResponse['tokens_used'] ?? 0;
        $contextScope = $resolvedScope->value;

        AgentAiChatMessage::create([
            'session_id'          => $session->id,
            'role'                => AgentAiChatMessage::ROLE_USER,
            'content'             => $question,
            'context_scope'       => $contextScope,
            'detected_intent'     => $detectedIntent,
            'lead_score_snapshot' => $newScore,
            'tokens_used'         => null,
            'created_at'          => now(),
        ]);

        AgentAiChatMessage::create([
            'session_id'          => $session->id,
            'role'                => AgentAiChatMessage::ROLE_ASSISTANT,
            'content'             => $finalResponse['answer'] ?? '',
            'context_scope'       => $contextScope,
            'detected_intent'     => null,
            'lead_score_snapshot' => $newScore,
            'tokens_used'         => $tokensUsed > 0 ? $tokensUsed : null,
            'created_at'          => now(),
        ]);

        // ── Build 5: Fire threshold notifications ─────────────────────────────
        try {
            $this->notificationService->checkAndNotify($session, $newScore, $lead);
        } catch (\Throwable $e) {
            Log::warning('AgentAiChatController: notification check failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // ── Update session activity ───────────────────────────────────────────
        $session->update(['last_active_at' => now()]);

        // ── Return public response ────────────────────────────────────────────
        // GOVERNANCE: Never expose prompt contents, raw context, API keys,
        // model names, or internal orchestrator details in the response.
        $publicResponse = [
            'status'   => $finalResponse['status'],
            'answer'   => $finalResponse['answer'],
            'escalate' => $finalResponse['escalate'],
            'actions'  => $finalResponse['actions'] ?? [],
        ];

        if ($captureContactPrompt !== null) {
            $publicResponse['capture_contact_prompt'] = $captureContactPrompt;
        }

        if ($escalatePromptText !== null) {
            $publicResponse['escalation_prompt'] = $escalatePromptText;
        }

        return response()->json($publicResponse);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /agent-ai/escalate
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Confirm a visitor's request to escalate to the agent.
     *
     * Request body:
     *   session_token (string, required)
     *   question      (string, required) — the visitor's exact unanswered question
     *   visitor_name  (string, optional)
     *   visitor_email (string, optional)
     *   visitor_phone (string, optional)
     *
     * Creates/updates the lead record with lead_type = agent_question and
     * the exact question as intent_phrase.
     *
     * Response: { status, message, lead_id }
     */
    public function confirmEscalation(Request $request): JsonResponse
    {
        $question = trim((string) $request->input('question', ''));

        if ($question === '') {
            return response()->json([
                'status' => 'error',
                'error'  => 'question is required.',
            ], 422);
        }

        // ── Permission guard ─────────────────────────────────────────────────
        $guard = $this->permissionGuard->check($request, AgentAiContextScope::AgentProfile);

        if (!$guard['allowed']) {
            return response()->json([
                'status' => 'error',
                'error'  => 'Access denied.',
                'reason' => $guard['reason'],
            ], $guard['http_status']);
        }

        /** @var AgentAiChatSession $session */
        $session = $guard['session'];

        // ── Per-scope rollout flag check ───────────────────────────────────────
        $escalationScope = AgentAiContextScope::tryFrom((string) $session->scope);
        if ($escalationScope !== null && !$this->isScopeEnabled($escalationScope)) {
            return response()->json([
                'status' => 'error',
                'error'  => 'This scope is not yet available.',
            ], 404);
        }

        $agentId = (int) $session->agent_id;

        $visitorData = array_filter([
            'name'  => $request->input('visitor_name'),
            'email' => $request->input('visitor_email'),
            'phone' => $request->input('visitor_phone'),
        ]);

        $result = $this->escalationService->confirmEscalation(
            $session->id,
            $agentId,
            $question,
            $visitorData
        );

        if (!$result['escalated']) {
            return response()->json([
                'status' => 'error',
                'error'  => $result['error'],
            ], 500);
        }

        // Score escalation signal, persist updated lead_score, and fire notifications.
        try {
            $escalationPoints = $this->leadScorer->pointsForSignal(
                AgentAiLeadIntentDetector::SIGNAL_ESCALATION_REQUESTED
            );
            $currentScore = $this->leadScorer->accumulateForSession($session->id);
            $newScore     = min($currentScore + $escalationPoints, 100);

            $lead = \App\Models\AgentAiChatLead::where('session_id', $session->id)->first();

            // Persist the updated score so inbox queries reflect escalated urgency.
            if ($lead !== null) {
                $lead->lead_score = $newScore;
                $lead->save();
            }

            $this->notificationService->checkAndNotify($session, $newScore, $lead);
        } catch (\Throwable $e) {
            Log::warning('AgentAiChatController: escalation notification failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status'  => 'escalated',
            'message' => $result['suggested_cta'],
            'lead_id' => $result['lead_id'],
        ]);
    }

    // ── Private handlers ──────────────────────────────────────────────────────

    /**
     * Handle the "View Agent's Services" inline action.
     *
     * Bypasses context build, prompt build, and OpenAI entirely.
     * Persists user + assistant messages and updates session activity.
     */
    private function handleViewAgentServices(
        AgentAiChatSession $session,
        AgentAiContextScope $resolvedScope,
        int $agentId,
        ?int $listingId,
        string $userQuestion
    ): JsonResponse {
        $inlineResponse = $this->actionResolver->resolveInlineServices($agentId);

        $contextScope    = $resolvedScope->value;
        $userContent     = $userQuestion !== '' ? $userQuestion : "View Agent's Services";
        $assistantAnswer = $inlineResponse['answer'] ?? '';

        AgentAiChatMessage::create([
            'session_id'    => $session->id,
            'role'          => AgentAiChatMessage::ROLE_USER,
            'content'       => $userContent,
            'action_key'    => AgentAiActionResolver::ACTION_VIEW_AGENT_SERVICES,
            'context_scope' => $contextScope,
            'tokens_used'   => null,
            'created_at'    => now(),
        ]);

        AgentAiChatMessage::create([
            'session_id'    => $session->id,
            'role'          => AgentAiChatMessage::ROLE_ASSISTANT,
            'content'       => $assistantAnswer,
            'context_scope' => $contextScope,
            'tokens_used'   => null,
            'created_at'    => now(),
        ]);

        $session->update(['last_active_at' => now()]);

        return response()->json([
            'status'   => $inlineResponse['status'],
            'answer'   => $assistantAnswer,
            'escalate' => $inlineResponse['escalate'],
            'actions'  => $inlineResponse['actions'] ?? [],
        ]);
    }

    /**
     * Return the config key for a scope's per-scope rollout flag.
     */
    private function scopeConfigKey(AgentAiContextScope $scope): string
    {
        return match ($scope) {
            AgentAiContextScope::PublicListingSeller   => 'agent_ai_v2_seller_enabled',
            AgentAiContextScope::PublicListingLandlord => 'agent_ai_v2_landlord_enabled',
            AgentAiContextScope::BuyerCriteria         => 'agent_ai_v2_buyer_enabled',
            AgentAiContextScope::TenantCriteria        => 'agent_ai_v2_tenant_enabled',
            AgentAiContextScope::AgentProfile          => 'agent_ai_v2_agent_profile_enabled',
        };
    }

    /**
     * Return true if either the global V2 flag or the scope-specific flag is enabled.
     *
     * The global flag (agent_ai_v2_enabled) enables all scopes at once.
     * The per-scope flags allow individual scopes to be turned on/off independently.
     */
    private function isScopeEnabled(AgentAiContextScope $scope): bool
    {
        if (config('ask_ai.agent_ai_v2_enabled', false)) {
            return true;
        }

        return (bool) config('ask_ai.' . $this->scopeConfigKey($scope), false);
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
