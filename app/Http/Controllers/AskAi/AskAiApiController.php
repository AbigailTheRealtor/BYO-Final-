<?php

namespace App\Http\Controllers\AskAi;

use App\Http\Controllers\Controller;
use App\Services\AskAi\AskAiComplianceGuardrailService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AskAiApiController extends Controller
{
    private const CONTRACT_VERSION = 'ASK_AI_API_V1';

    private const STATUS_MAP = [
        'ready'                => 'answered',
        'insufficient_context' => 'insufficient_context',
        'blocked'              => 'blocked',
        'unsupported'          => 'unsupported',
        'failed'               => 'failed',
    ];

    private AskAiRunnerV2Service $runner;
    private AskAiViewerAuthorizationService $viewerAuth;

    public function __construct(AskAiRunnerV2Service $runner, AskAiViewerAuthorizationService $viewerAuth)
    {
        $this->runner     = $runner;
        $this->viewerAuth = $viewerAuth;
    }

    /**
     * Channel-agnostic Ask AI endpoint.
     *
     * Accepts the canonical input contract, calls the internal pipeline via
     * AskAiRunnerV2Service, maps the runner's status to the canonical API
     * status, and returns the canonical output contract.
     *
     * Used by both:
     *   POST /ask-ai/ask      (web middleware — session/CSRF, open to authenticated + guest)
     *   POST /api/ask-ai/ask  (auth:sanctum — external channel integrations)
     */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_type' => ['required', 'string'],
            'listing_id'   => ['required', 'integer'],
            'question'     => ['required', 'string', 'max:1000'],
            'options'      => ['nullable', 'array'],
            'channel'      => ['nullable', 'string', 'in:web,sms,messenger,whatsapp,mobile,crm'],
            'session_id'   => ['nullable', 'string', 'max:255'],
        ]);

        $listingType = $validated['listing_type'];
        $listingId   = (int) $validated['listing_id'];
        $question    = $validated['question'];
        $options     = $validated['options'] ?? [];
        $channel     = $validated['channel'] ?? 'web';

        // Part J / C-B — resolve the requester's authorization scope and thread it into
        // the pipeline so confidential applicant fields are redacted before reaching the
        // model. Guests and unverified requesters resolve to 'public' (default-deny).
        $requesterId = $request->user()?->id;
        $viewerScope = $this->viewerAuth->resolveScope($requesterId, $listingType, $listingId);
        $options['viewer_scope']      = $viewerScope;
        $options['requester_user_id'] = $requesterId;

        Log::info('ask_ai.api.request', [
            'channel'      => $channel,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'user_id'      => $requesterId,
            'viewer_scope' => $viewerScope,
            'ip'           => $request->ip(),
        ]);

        try {
            $result = $this->runner->run($listingType, $listingId, $question, $options);

            $runnerStatus   = $result['status'] ?? 'failed';
            $apiStatus      = self::STATUS_MAP[$runnerStatus] ?? 'failed';
            $success        = $result['success'] ?? false;
            $final          = $result['final_response'] ?? [];
            $classification = $result['classification'] ?? [];

            if ($runnerStatus === 'failed' || ($result['error'] ?? null) !== null) {
                Log::warning('ask_ai.api.pipeline_failure', [
                    'channel'      => $channel,
                    'listing_type' => $listingType,
                    'listing_id'   => $listingId,
                    'internal'     => $result['error'] ?? 'status=failed',
                ]);
            }

            $publicError = ($apiStatus === 'failed')
                ? 'Ask AI could not generate a response right now. Please try again later.'
                : null;

            // C-J — every response carries the standing educational disclaimer.
            $disclosures = $this->withDisclaimer($final['disclosures'] ?? []);

            return response()->json([
                'success'             => $success,
                'status'              => $apiStatus,
                'answer_text'         => $final['answer']                 ?? null,
                'question_type'       => $classification['question_type'] ?? null,
                'follow_up_questions' => $final['follow_up_questions']    ?? [],
                'disclosures'         => $disclosures,
                'disclaimer'          => AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER,
                'attribution'         => $final['source_attribution']     ?? null,
                'error'               => $publicError,
                'contract_version'    => self::CONTRACT_VERSION,
            ]);

        } catch (\Throwable $e) {
            Log::error('ask_ai.api.exception', [
                'channel'      => $channel,
                'listing_type' => $listingType,
                'listing_id'   => $listingId,
                'message'      => $e->getMessage(),
            ]);

            return response()->json([
                'success'             => false,
                'status'              => 'failed',
                'answer_text'         => null,
                'question_type'       => null,
                'follow_up_questions' => [],
                'disclosures'         => [AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER],
                'disclaimer'          => AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER,
                'attribution'         => null,
                'error'               => 'Ask AI could not generate a response right now. Please try again later.',
                'contract_version'    => self::CONTRACT_VERSION,
            ]);
        }
    }

    /**
     * Ensure the standing educational disclaimer (C-J) is present in a disclosures list.
     *
     * @param  mixed $disclosures  Disclosures from the runner (array or null).
     * @return string[]
     */
    private function withDisclaimer($disclosures): array
    {
        $disclosures = is_array($disclosures) ? array_values($disclosures) : [];

        foreach ($disclosures as $existing) {
            if (is_string($existing) && trim($existing) === AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER) {
                return $disclosures;
            }
        }

        $disclosures[] = AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER;
        return $disclosures;
    }
}
