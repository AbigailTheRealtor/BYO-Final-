<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dna\AiMarketingReportPublicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AiMarketingReportPublicationController extends Controller
{
    public function publish(string $report)
    {
        $record = DB::table('marketing_reports')->where('id', $report)->first();

        if (! $record) {
            abort(404);
        }

        $service = new AiMarketingReportPublicationService();
        $result  = $service->publish($report, (int) Auth::id());

        if ($result['ok']) {
            return redirect()
                ->route('admin.property-dna.marketing-reports.show', $report)
                ->with('success', 'Marketing report has been published successfully.');
        }

        return redirect()
            ->route('admin.property-dna.marketing-reports.show', $report)
            ->with('error', $result['error']);
    }
}
