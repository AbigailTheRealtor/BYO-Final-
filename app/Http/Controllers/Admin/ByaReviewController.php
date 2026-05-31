<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ByaReviewLog;
use App\Models\ListingCompatibilityScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ByaReviewController extends Controller
{
    public function index()
    {
        abort(403);
    }

    public function store(Request $request, $id)
    {
        $record = ListingCompatibilityScore::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(ByaReviewLog::STATUSES))],
            'notes'  => ['nullable', 'string', 'max:5000'],
            'fair_housing_checklist' => ['nullable', 'array'],
            'fair_housing_checklist.*' => ['nullable', 'boolean'],
        ]);

        $checklist = [];
        foreach (array_keys(ByaReviewLog::CHECKLIST_ITEMS) as $key) {
            $checklist[$key] = isset($validated['fair_housing_checklist'][$key])
                ? (bool) $validated['fair_housing_checklist'][$key]
                : null;
        }

        $log = ByaReviewLog::create([
            'listing_compatibility_score_id' => $record->id,
            'reviewer_user_id'               => Auth::id(),
            'status'                         => $validated['status'],
            'notes'                           => $validated['notes'] ?? null,
            'fair_housing_checklist'          => $checklist,
        ]);

        Log::info('BYA Review: new log entry created', [
            'admin_user_id'                  => Auth::id(),
            'listing_compatibility_score_id' => $record->id,
            'review_log_id'                  => $log->id,
            'status'                         => $log->status,
            'timestamp'                      => now()->toIso8601String(),
        ]);

        return redirect()
            ->route('admin.bya.preview.show', $record->id)
            ->with('review_success', 'Review entry saved successfully.');
    }
}
