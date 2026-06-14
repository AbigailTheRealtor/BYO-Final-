<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LocationIntelligenceComposer — Phase 4B Integration Layer
 *
 * Thin orchestrator that wires LocationDnaEnrichmentRunner to
 * LocationIntelligenceSummaryService with per-layer fault isolation.
 *
 * No exception ever escapes to the caller. Each layer failure is logged
 * with Log::warning and a safe fallback is returned.
 *
 * Return shape:
 * [
 *   'enrichment' => [...],          // runner payload or empty fallback
 *   'summary'    => ['summary_lines' => [...]], // summary or empty lines
 * ]
 */
class LocationIntelligenceComposer
{
    private const EMPTY_ENRICHMENT = [
        'floodZones'      => [],
        'schoolDistricts' => [],
        'pois'            => [],
        'commuteTimes'    => [],
    ];

    public function __construct(
        private readonly LocationDnaEnrichmentRunner      $runner,
        private readonly LocationIntelligenceSummaryService $summaryService,
    ) {}

    /**
     * Run enrichment then summarize, with independent fault isolation per layer.
     *
     * @param  array  $boundaryData  Payload from BoundaryLookupService::resolve()
     * @param  array  $preferences   Decoded location_dna_preferences array
     * @return array  ['enrichment' => [...], 'summary' => ['summary_lines' => [...]]]
     */
    public function compose(array $boundaryData, array $preferences): array
    {
        try {
            $enrichment = $this->runner->run($boundaryData, $preferences);
        } catch (Throwable $e) {
            Log::warning('LocationIntelligenceComposer: enrichment runner failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'enrichment' => self::EMPTY_ENRICHMENT,
                'summary'    => ['summary_lines' => []],
            ];
        }

        try {
            $summary = $this->summaryService->summarize($enrichment);
        } catch (Throwable $e) {
            Log::warning('LocationIntelligenceComposer: summary service failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'enrichment' => $enrichment,
                'summary'    => ['summary_lines' => []],
            ];
        }

        return [
            'enrichment' => $enrichment,
            'summary'    => $summary,
        ];
    }
}
