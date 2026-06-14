<?php

namespace App\Services\LocationDna;

/**
 * LocationIntelligenceSummaryService — Phase 4A Presentation Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a pure, stateless text-formatting layer. It converts normalized
 * Location DNA enrichment payloads into human-readable summary lines.
 *
 * This service MUST NEVER:
 *   - Make any external API calls of any kind.
 *   - Make any database reads or writes.
 *   - Import or use OpenAI, scoring, or marketing report classes.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute scores, recommendations, or generate marketing copy.
 * ==================================================================================
 *
 * Input payload shape (all sections optional):
 *   [
 *     'floodZones'    => [ ['zone' => string], ... ],
 *     'schoolDistricts' => [ ['name' => string], ... ],
 *     'pois'          => [ ['label' => string, 'name' => string], ... ],
 *     'commuteTimes'  => [ ['destination' => string, 'minutes' => int|float], ... ],
 *   ]
 *
 * Output shape:
 *   ['summary_lines' => string[]]
 */
class LocationIntelligenceSummaryService
{
    /**
     * Convert a normalized Location DNA enrichment payload into summary lines.
     *
     * Each section is skipped gracefully when absent, not an array, or malformed.
     * Duplicate school district names are deduplicated (case-sensitive).
     *
     * @param  array $locationData  Enrichment payload (in-memory only, no DB reads).
     * @return array                ['summary_lines' => string[]]
     */
    public function summarize(array $locationData): array
    {
        $lines = [];

        $lines = array_merge($lines, $this->formatFloodZones($locationData));
        $lines = array_merge($lines, $this->formatSchoolDistricts($locationData));
        $lines = array_merge($lines, $this->formatPois($locationData));
        $lines = array_merge($lines, $this->formatCommuteTimes($locationData));

        return ['summary_lines' => $lines];
    }

    // =========================================================================
    // Private section formatters — each returns string[]
    // =========================================================================

    private function formatFloodZones(array $locationData): array
    {
        $section = $locationData['floodZones'] ?? null;

        if (!is_array($section)) {
            return [];
        }

        $lines = [];
        foreach ($section as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $zone = $entry['zone'] ?? null;
            if (is_string($zone) && $zone !== '') {
                $lines[] = "Flood Zone: {$zone}";
            }
        }

        return $lines;
    }

    private function formatSchoolDistricts(array $locationData): array
    {
        $section = $locationData['schoolDistricts'] ?? null;

        if (!is_array($section)) {
            return [];
        }

        $seen  = [];
        $lines = [];

        foreach ($section as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '' && !isset($seen[$name])) {
                $seen[$name] = true;
                $lines[]     = "School District: {$name}";
            }
        }

        return $lines;
    }

    private function formatPois(array $locationData): array
    {
        $section = $locationData['pois'] ?? null;

        if (!is_array($section)) {
            return [];
        }

        $lines = [];
        foreach ($section as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $label = $entry['label'] ?? null;
            $name  = $entry['name']  ?? null;
            if (is_string($label) && $label !== '' && is_string($name) && $name !== '') {
                $lines[] = "Nearby {$label}: {$name}";
            }
        }

        return $lines;
    }

    private function formatCommuteTimes(array $locationData): array
    {
        $section = $locationData['commuteTimes'] ?? null;

        if (!is_array($section)) {
            return [];
        }

        $lines = [];
        foreach ($section as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $destination = $entry['destination'] ?? null;
            $minutes     = $entry['minutes']     ?? null;
            if (is_string($destination) && $destination !== '' && is_numeric($minutes)) {
                $lines[] = "{$destination}: {$minutes} minutes";
            }
        }

        return $lines;
    }
}
