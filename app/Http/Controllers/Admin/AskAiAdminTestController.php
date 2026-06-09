<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Http\Request;

class AskAiAdminTestController extends Controller
{
    public function index()
    {
        return view('admin.ask-ai-test');
    }

    public function run(Request $request, AskAiRunnerV2Service $runner)
    {
        $validated = $request->validate([
            'listing_type' => ['required', 'string'],
            'listing_id'   => ['required', 'integer'],
            'question'     => ['required', 'string'],
            'options'      => ['nullable', 'json'],
        ]);

        $options = [];
        if (!empty($validated['options'])) {
            $decoded = json_decode($validated['options'], true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        $result = $runner->run(
            $validated['listing_type'],
            (int) $validated['listing_id'],
            $validated['question'],
            $options
        );

        $traceColumns = $this->extractTraceColumns($result);

        return view('admin.ask-ai-test', compact('result', 'traceColumns'));
    }

    /**
     * Extract 7-column per-stage trace summary from the runner result.
     *
     * Columns:
     *   classifier_result    — question_type assigned by classifier (or 'n/a')
     *   normalized_field_key — specific listing.field or faq_answers.field resolved (or null)
     *   context_status       — status from context assembly phase
     *   contract_status      — status from response contract phase
     *   prompt_package_status— status from prompt builder phase
     *   adapter_status       — status from OpenAI adapter phase
     *   final_status         — overall pipeline status (ready/insufficient_context/failed/etc.)
     *   error                — error message if any
     */
    private function extractTraceColumns(array $result): array
    {
        $trace          = $result['trace']          ?? [];
        $classification = $result['classification'] ?? [];
        $context        = $result['context']        ?? [];
        $contract       = $result['contract']       ?? [];
        $promptPackage  = $result['prompt_package'] ?? [];
        $adapterResult  = $result['adapter_result'] ?? [];

        // Error priority: top-level > adapter stage > prompt_package stage > contract stage.
        // In adapter-failed fallback paths the top-level error is intentionally null;
        // pulling from the adapter/prompt/contract stage surfaces the actual failure detail.
        $error = $result['error']
            ?? (isset($adapterResult['error']) && $adapterResult['error'] !== null ? $adapterResult['error'] : null)
            ?? (isset($promptPackage['error']) && $promptPackage['error'] !== null ? $promptPackage['error'] : null)
            ?? (isset($contract['error'])       && $contract['error']       !== null ? $contract['error']       : null)
            ?? null;

        return [
            'classifier_result'     => $trace['classifier_result']
                ?? $classification['question_type']
                ?? 'n/a',
            'normalized_field_key'  => $classification['normalized_field_key']
                ?? $trace['normalized_field_key']
                ?? null,
            'context_status'        => $context['status']
                ?? $trace['context_status']
                ?? 'n/a',
            'contract_status'       => $contract['status']
                ?? $trace['contract_status']
                ?? 'n/a',
            'prompt_package_status' => $promptPackage['status']
                ?? $trace['prompt_package_status']
                ?? 'n/a',
            'adapter_status'        => $adapterResult['status']
                ?? $trace['adapter_status']
                ?? 'n/a',
            'final_status'          => $result['status']
                ?? $trace['final_status']
                ?? 'n/a',
            'error'                 => $error,
        ];
    }
}
