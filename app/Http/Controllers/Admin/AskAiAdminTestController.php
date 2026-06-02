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

        return view('admin.ask-ai-test', compact('result'));
    }
}
