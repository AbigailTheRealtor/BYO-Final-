<?php

namespace App\Http\Controllers;

use App\Models\ListingCompatibilityScore;
use App\Services\Dna\Compatibility\ByaCompatibilityAlignmentService;
use App\Services\Dna\Compatibility\ByaCompatibilityExplanationService;
use App\Services\Dna\Compatibility\ByaCompatibilityNarrativeService;
use App\Services\Dna\Compatibility\ByaCompatibilityReportService;

class ByaBetaController extends Controller
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

    /**
     * Show the beta compatibility insight view.
     *
     * Only permitted fields are passed to the view:
     *   - summary_sentence
     *   - per-dimension: dimension name, alignment_category, sentence
     *
     * Internal keys (explanation_key, template_id, trace data, reviewer notes,
     * review history, admin metadata) are never passed to the view.
     */
    public function show(int $id)
    {
        $record = ListingCompatibilityScore::select([
            'id',
            'compatibility_trait_results',
            'compatibility_framework_version',
            'compatibility_computed_at',
        ])->findOrFail($id);

        $compV1      = $record->compatibility_trait_results ?? [];
        $alignV1     = $this->alignmentService->categorize($compV1);
        $explainV1   = $this->explanationService->explain($alignV1);
        $narrativeV1 = $this->narrativeService->generate($explainV1, $alignV1);
        $reportV1    = $this->reportService->generate($alignV1, $explainV1, $narrativeV1);

        $summarySentence = $reportV1['summary']['summary_sentence'] ?? null;

        $dimensions = [];
        foreach ($reportV1['dimensions'] ?? [] as $dimensionName => $data) {
            $dimensions[] = [
                'dimension'          => $dimensionName,
                'alignment_category' => $data['alignment_category'] ?? null,
                'sentence'           => $data['sentence'] ?? null,
            ];
        }

        return response()
            ->view('bya_beta.compatibility_report', compact('summarySentence', 'dimensions'))
            ->header('Cache-Control', 'no-store');
    }
}
