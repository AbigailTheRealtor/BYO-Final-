<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingCompatibilityScore;
use App\Services\Dna\Compatibility\ByaCompatibilityAlignmentService;
use App\Services\Dna\Compatibility\ByaCompatibilityExplanationService;
use App\Services\Dna\Compatibility\ByaCompatibilityNarrativeService;
use App\Services\Dna\Compatibility\ByaCompatibilityReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ByaPreviewController extends Controller
{
    private ByaCompatibilityAlignmentService $alignmentService;
    private ByaCompatibilityExplanationService $explanationService;
    private ByaCompatibilityNarrativeService $narrativeService;
    private ByaCompatibilityReportService $reportService;

    public function __construct(
        ByaCompatibilityAlignmentService $alignmentService,
        ByaCompatibilityExplanationService $explanationService,
        ByaCompatibilityNarrativeService $narrativeService,
        ByaCompatibilityReportService $reportService
    ) {
        $this->alignmentService   = $alignmentService;
        $this->explanationService = $explanationService;
        $this->narrativeService   = $narrativeService;
        $this->reportService      = $reportService;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'demand_listing_type',
            'supply_listing_type',
            'compatibility_computed_at_from',
            'compatibility_computed_at_to',
        ]);

        $activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        Log::info('BYA Preview: index accessed', [
            'admin_user_id' => Auth::id(),
            'page'          => 'bya-compatibility-preview.index',
            'filters'       => $activeFilters,
            'timestamp'     => now()->toIso8601String(),
        ]);

        $query = ListingCompatibilityScore::select([
            'id',
            'demand_listing_type',
            'demand_listing_id',
            'supply_listing_type',
            'supply_listing_id',
            'compatibility_framework_version',
            'compatibility_computed_at',
            'moderation_status',
        ])->where(function ($q) {
            $q->whereNotNull('compatibility_trait_results')
              ->orWhereNotNull('compatibility_framework_version');
        });

        if (!empty($filters['demand_listing_type'])) {
            $query->where('demand_listing_type', $filters['demand_listing_type']);
        }
        if (!empty($filters['supply_listing_type'])) {
            $query->where('supply_listing_type', $filters['supply_listing_type']);
        }
        if (!empty($filters['compatibility_computed_at_from'])) {
            $query->where('compatibility_computed_at', '>=', $filters['compatibility_computed_at_from']);
        }
        if (!empty($filters['compatibility_computed_at_to'])) {
            $query->where('compatibility_computed_at', '<=', $filters['compatibility_computed_at_to']);
        }

        $rows = $query->orderByDesc('compatibility_computed_at')->paginate(25)->withQueryString();

        return response()
            ->view('admin.bya.preview.index', compact('rows', 'filters'))
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, $id)
    {
        $record = ListingCompatibilityScore::select([
            'id',
            'demand_listing_type',
            'demand_listing_id',
            'supply_listing_type',
            'supply_listing_id',
            'compatibility_framework_version',
            'compatibility_trait_results',
            'compatibility_computed_at',
            'compatibility_archived_at',
            'moderation_status',
            'ai_explanation_version',
            'version',
            'computed_at',
        ])->findOrFail($id);

        Log::info('BYA Preview: record accessed', [
            'admin_user_id'        => Auth::id(),
            'page'                 => 'bya-compatibility-preview.show',
            'record_id'            => $record->id,
            'demand_listing_type'  => $record->demand_listing_type,
            'demand_listing_id'    => $record->demand_listing_id,
            'supply_listing_type'  => $record->supply_listing_type,
            'supply_listing_id'    => $record->supply_listing_id,
            'timestamp'            => now()->toIso8601String(),
        ]);

        $compV1      = $record->compatibility_trait_results ?? [];
        $alignV1     = $this->alignmentService->categorize($compV1);
        $explainV1   = $this->explanationService->explain($alignV1);
        $narrativeV1 = $this->narrativeService->generate($explainV1, $alignV1);
        $reportV1    = $this->reportService->generate($alignV1, $explainV1, $narrativeV1);

        return response()
            ->view('admin.bya.preview.show', compact('record', 'reportV1'))
            ->header('Cache-Control', 'no-store');
    }
}
