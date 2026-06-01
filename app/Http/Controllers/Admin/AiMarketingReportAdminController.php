<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Dna\MarketingReadinessException;
use App\Http\Controllers\Controller;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\AiMarketingReportOrchestratorService;
use App\Services\Dna\AiMarketingReportPersistenceService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiMarketingReportAdminController extends Controller
{
    public function generate(
        PropertyDnaProfile $profile,
        AiMarketingReportOrchestratorService $orchestrator,
        AiMarketingReportPersistenceService $persistence,
    ) {
        try {
            $orchestrationResult = $orchestrator->run($profile);
        } catch (MarketingReadinessException $e) {
            $snapshot      = $e->getReadinessSnapshot();
            $missingGroups = $snapshot['missing_groups'] ?? [];
            $groupList     = !empty($missingGroups) ? implode(', ', $missingGroups) : 'unknown';

            Log::warning('Admin: marketing report generation blocked — readiness gate failed', [
                'admin_user_id' => Auth::id(),
                'profile_id'    => $profile->id,
                'missing_groups' => $missingGroups,
            ]);

            return redirect()
                ->route('admin.property-dna.marketing-brief-preview', $profile->id)
                ->with('error', 'This profile is not marketing-ready. Missing required information groups: ' . $groupList . '. All three required groups (Property Attributes, Transaction Details, Quantitative Data) must be present before a report can be generated.');
        } catch (Exception $e) {
            Log::error('Admin: marketing report generation failed', [
                'admin_user_id' => Auth::id(),
                'profile_id'    => $profile->id,
                'error'         => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.property-dna.marketing-brief-preview', $profile->id)
                ->with('error', 'Report generation failed: ' . $e->getMessage());
        }

        try {
            $persistResult = $persistence->persist($profile, $orchestrationResult);
        } catch (Exception $e) {
            Log::error('Admin: marketing report persistence failed', [
                'admin_user_id'        => Auth::id(),
                'profile_id'           => $profile->id,
                'orchestration_status' => $orchestrationResult['orchestration_status'] ?? '(absent)',
                'error'                => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.property-dna.marketing-brief-preview', $profile->id)
                ->with('error', 'Report could not be saved: ' . $e->getMessage());
        }

        $reportId = $persistResult['marketing_report_id'];

        Log::info('Admin: marketing report generated and persisted', [
            'admin_user_id' => Auth::id(),
            'profile_id'    => $profile->id,
            'report_id'     => $reportId,
        ]);

        return redirect()
            ->route('admin.property-dna.marketing-reports.show', $reportId)
            ->with('success', 'Marketing report generated successfully.');
    }

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
