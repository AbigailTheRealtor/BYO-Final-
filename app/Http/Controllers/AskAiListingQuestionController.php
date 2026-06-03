<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AskAi\AskAiRunnerV2Service;

class AskAiListingQuestionController extends Controller
{
    private AskAiRunnerV2Service $runner;

    public function __construct(AskAiRunnerV2Service $runner)
    {
        $this->runner = $runner;
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_type' => ['required', 'string'],
            'listing_id'   => ['required', 'integer'],
            'question'     => ['required', 'string', 'max:1000'],
            'options'      => ['nullable', 'array'],
        ]);

        try {
            $result = $this->runner->run(
                $validated['listing_type'],
                (int) $validated['listing_id'],
                $validated['question'],
                $validated['options'] ?? []
            );

            $final = $result['final_response'] ?? [];

            $status = $result['status'] ?? 'failed';

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
