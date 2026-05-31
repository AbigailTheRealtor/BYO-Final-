<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiMarketingReportAdminController extends Controller
{
    public function show(string $report)
    {
        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        Log::info('Admin: marketing_reports record accessed', [
            'admin_user_id' => Auth::id(),
            'report_id'     => $record->id,
        ]);

        $versions = DB::table('marketing_report_versions')
            ->where('marketing_report_id', $record->id)
            ->orderBy('section_key')
            ->orderByDesc('version_number')
            ->get();

        $audits = DB::table('marketing_report_audits')
            ->where('report_id', $record->id)
            ->orderByDesc('event_at')
            ->get();

        return response()
            ->view('admin.dna.marketing-report-show', compact('record', 'versions', 'audits'))
            ->header('Cache-Control', 'no-store');
    }
}
