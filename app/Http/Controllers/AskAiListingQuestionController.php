<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiRateLimitService;
use App\Services\AskAi\AskAiUsageLoggerService;

class AskAiListingQuestionController extends Controller
{
    private AskAiRunnerV2Service $runner;
    private AskAiUsageLoggerService $logger;
    private AskAiRateLimitService $rateLimiter;

    public function __construct(
        AskAiRunnerV2Service $runner,
        AskAiUsageLoggerService $logger,
        AskAiRateLimitService $rateLimiter
    ) {
        $this->runner      = $runner;
        $this->logger      = $logger;
        $this->rateLimiter = $rateLimiter;
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_type' => ['required', 'string'],
            'listing_id'   => ['required', 'integer'],
            'question'     => ['required', 'string', 'max:1000'],
            'options'      => ['nullable', 'array'],
        ]);

        $startTime    = microtime(true);
        $listingType  = $validated['listing_type'];
        $listingId    = (int) $validated['listing_id'];
        $question     = $validated['question'];
        $questionHash = hash('sha256', $question);

        $rateLimitResult = $this->rateLimiter->check($request, $listingType, $listingId);
        if ($rateLimitResult !== null) {
            $retryAfter = $rateLimitResult['retry_after'];

            try {
                $this->logger->logListingQuestion([
                    'listing_type'      => $listingType,
                    'listing_id'        => $listingId,
                    'user_id'           => auth()->id(),
                    'ip_address'        => $request->ip(),
                    'question_hash'     => $questionHash,
                    'question_type'     => null,
                    'status'            => 'rate_limited',
                    'success'           => false,
                    'model'             => null,
                    'response_time_ms'  => null,
                    'error_code'        => $rateLimitResult['limit_type'],
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'api_request_id'    => null,
                ]);
            } catch (\Throwable $logEx) {
            }

            return response()->json([
                'error' => [
                    'message'     => 'You have exceeded the Ask AI rate limit. Please try again later.',
                    'retry_after' => $retryAfter,
                    'limit_type'  => $rateLimitResult['limit_type'],
                ],
            ], 429)->header('Retry-After', $retryAfter);
        }

        try {
            $result = $this->runner->run(
                $listingType,
                $listingId,
                $question,
                $validated['options'] ?? []
            );

            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            $status         = $result['status'] ?? 'failed';
            $success        = $result['success'] ?? false;
            $questionType   = $result['classification']['question_type'] ?? null;
            $model          = $result['adapter_result']['model'] ?? null;

            $errorCode = null;
            if ($status === 'blocked') {
                $errorCode = 'blocked';
            } elseif ($status === 'failed') {
                $errorCode = 'failed';
            }

            $adapterResult    = $result['adapter_result'] ?? [];
            $promptTokens     = (int) ($adapterResult['prompt_tokens']     ?? 0);
            $completionTokens = (int) ($adapterResult['completion_tokens'] ?? 0);
            $totalTokens      = (int) ($adapterResult['total_tokens']      ?? 0);
            $apiRequestId     = $adapterResult['api_request_id']           ?? null;

            // Phase 4: read outcome_category from runner result; derive for legacy paths.
            $outcomeCategory = $result['outcome_category'] ?? null;
            if ($outcomeCategory === null) {
                $outcomeCategory = match ($status) {
                    'blocked'              => 'blocked_restricted',
                    'failed'               => 'error',
                    'insufficient_context' => 'openai_fallback',
                    default                => null,
                };
            }

            try {
                $this->logger->logListingQuestion([
                    'listing_type'     => $listingType,
                    'listing_id'       => $listingId,
                    'user_id'          => auth()->id(),
                    'ip_address'       => $request->ip(),
                    'question_hash'    => $questionHash,
                    'question_type'    => $questionType,
                    'status'           => $status,
                    'success'          => $success,
                    'model'            => $model,
                    'response_time_ms' => $responseTimeMs,
                    'error_code'       => $errorCode,
                    'prompt_tokens'     => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens'      => $totalTokens,
                    'api_request_id'    => $apiRequestId,
                    'outcome_category'  => $outcomeCategory,
                ]);
            } catch (\Throwable $logEx) {
            }

            if ($status === 'failed') {
                $pipelineError = $result['error'] ?? null;
                if ($pipelineError) {
                    Log::warning('AskAi pipeline failure', [
                        'listing_type'  => $listingType,
                        'listing_id'    => $listingId,
                        'question_hash' => $questionHash,
                        'question_type' => $questionType,
                        'error'         => $pipelineError,
                    ]);
                }
                return response()->json([
                    'success'            => false,
                    'status'             => 'failed',
                    'answer'             => null,
                    'refusal_message'    => null,
                    'disclosures'        => null,
                    'source_attribution' => null,
                    'error'              => 'Ask AI could not generate a response right now. Please try again later.',
                    'follow_up_questions'=> [],
                ]);
            }

            $final             = $result['final_response'] ?? [];
            $followUpQuestions = $final['follow_up_questions'] ?? [];

            return response()->json([
                'success'             => $result['success'] ?? false,
                'status'              => $status,
                'answer'              => $final['answer']             ?? null,
                'refusal_message'     => $final['refusal_message']    ?? null,
                'disclosures'         => $final['disclosures']        ?? null,
                'source_attribution'  => $final['source_attribution'] ?? null,
                'source'              => $final['source']             ?? null,
                'error'               => null,
                'follow_up_questions' => $followUpQuestions,
            ]);

        } catch (\Throwable $e) {
            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            try {
                $this->logger->logListingQuestion([
                    'listing_type'     => $listingType,
                    'listing_id'       => $listingId,
                    'user_id'          => auth()->id(),
                    'ip_address'       => $request->ip(),
                    'question_hash'    => $questionHash,
                    'question_type'    => null,
                    'status'           => 'failed',
                    'success'          => false,
                    'model'            => null,
                    'response_time_ms' => $responseTimeMs,
                    'error_code'       => 'failed',
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'api_request_id'    => null,
                    'outcome_category'  => 'error',
                ]);
            } catch (\Throwable $logEx) {
            }

            return response()->json([
                'success'             => false,
                'status'              => 'failed',
                'answer'              => null,
                'refusal_message'     => null,
                'disclosures'         => null,
                'source_attribution'  => null,
                'source'              => null,
                'error'               => 'Ask AI could not generate a response right now. Please try again later.',
                'follow_up_questions' => [],
            ]);
        }
    }
}
