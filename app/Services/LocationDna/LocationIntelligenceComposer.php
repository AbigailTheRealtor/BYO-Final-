<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LocationIntelligenceComposer — Phase 4B Integration Layer (updated Phase 5A)
 *
 * Thin orchestrator that wires LocationDnaEnrichmentRunner to
 * LocationIntelligenceSummaryService with per-layer fault isolation.
 *
 * Phase 5A addition: LocationPreferenceAnalyzer generates preference-derived
 * intelligence lines from the raw preferences array and prepends them to the
 * enrichment summary lines before returning the final payload.
 *
 * No exception ever escapes to the caller. Each layer failure is logged
 * with Log::warning and a safe fallback is returned.
 *
 * Return shape:
 * [
 *   'enrichment' => [...],          // runner payload or empty fallback
 *   'summary'    => ['summary_lines' => [...]], // preference lines + enrichment lines, or empty
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
        private readonly LocationDnaEnrichmentRunner        $runner,
        private readonly LocationIntelligenceSummaryService $summaryService,
        private readonly LocationPreferenceAnalyzer         $preferenceAnalyzer,
    ) {}

    /**
     * Run enrichment then summarize, with independent fault isolation per layer.
     *
     * Preference-derived intelligence lines (from LocationPreferenceAnalyzer) are
     * prepended to the enrichment summary lines in the final payload.
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

        $preferenceLines = $this->analyzePreferences($preferences);

        $summary['summary_lines'] = array_merge(
            $preferenceLines,
            $summary['summary_lines'] ?? [],
        );

        return [
            'enrichment' => $enrichment,
            'summary'    => $summary,
        ];
    }

    /**
     * Run the preference analyzer with fault isolation.
     * Returns an empty array on failure so enrichment lines are unaffected.
     *
     * @param  array  $preferences
     * @return string[]
     */
    private function analyzePreferences(array $preferences): array
    {
        try {
            $result = $this->preferenceAnalyzer->analyze($preferences);

            return $result['summary_lines'] ?? [];
        } catch (Throwable $e) {
            Log::warning('LocationIntelligenceComposer: preference analyzer failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
