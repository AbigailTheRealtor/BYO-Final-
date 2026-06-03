<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiUsageLoggerService;

class AskAiListingQuestionController extends Controller
{
    private AskAiRunnerV2Service $runner;
    private AskAiUsageLoggerService $logger;

    public function __construct(AskAiRunnerV2Service $runner, AskAiUsageLoggerService $logger)
    {
        $this->runner = $runner;
        $this->logger = $logger;
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
                ]);
            } catch (\Throwable $logEx) {
            }

            if ($status === 'failed') {
                return response()->json([
                    'success'           => false,
                    'status'            => 'failed',
                    'answer'            => null,
                    'refusal_message'   => null,
                    'disclosures'       => null,
                    'source_attribution'=> null,
                    'error'             => 'Ask AI could not generate a response right now. Please try again later.',
                ]);
            }

            $final = $result['final_response'] ?? [];

            return response()->json([
                'success'            => $result['success'] ?? false,
                'status'             => $status,
                'answer'             => $final['answer']             ?? null,
                'refusal_message'    => $final['refusal_message']    ?? null,
                'disclosures'        => $final['disclosures']        ?? null,
                'source_attribution' => $final['source_attribution'] ?? null,
                'error'              => null,
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
                ]);
            } catch (\Throwable $logEx) {
            }

            return response()->json([
                'success'            => false,
                'status'             => 'failed',
                'answer'             => null,
                'refusal_message'    => null,
                'disclosures'        => null,
                'source_attribution' => null,
                'error'              => 'Ask AI could not generate a response right now. Please try again later.',
            ]);
        }
    }
}
