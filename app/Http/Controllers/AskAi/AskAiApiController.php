<?php

namespace App\Http\Controllers\AskAi;

use App\Http\Controllers\Controller;
use App\Services\AskAi\AskAiRunnerV2Service;
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

    public function __construct(AskAiRunnerV2Service $runner)
    {
        $this->runner = $runner;
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

        Log::info('ask_ai.api.request', [
            'channel'      => $channel,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'user_id'      => $request->user()?->id,
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

            return response()->json([
                'success'             => $success,
                'status'              => $apiStatus,
                'answer_text'         => $final['answer']                 ?? null,
                'question_type'       => $classification['question_type'] ?? null,
                'follow_up_questions' => $final['follow_up_questions']    ?? [],
                'disclosures'         => $final['disclosures']            ?? null,
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
                'disclosures'         => null,
                'attribution'         => null,
                'error'               => 'Ask AI could not generate a response right now. Please try again later.',
                'contract_version'    => self::CONTRACT_VERSION,
            ]);
        }
    }
}
