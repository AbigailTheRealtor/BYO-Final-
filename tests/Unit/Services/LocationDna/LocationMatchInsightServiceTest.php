<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationMatchInsightService;
use Tests\TestCase;

/**
 * LocationMatchInsightServiceTest — Phase 6B
 *
 * Pure in-memory unit tests — no DB trait, no HTTP calls, no external services.
 *
 * Coverage:
 *   (1)  Output shape — always returns ['insights' => array]
 *   (2)  City only — emits city insight, no footer
 *   (3)  ZIP only — emits ZIP insight, no footer
 *   (4)  Neighborhood only — emits neighborhood insight, no footer
 *   (5)  Polygon only — emits polygon insight, no footer
 *   (6)  Radius only — emits radius insight, no footer
 *   (7)  No signals — emits fallback, nothing else
 *   (8)  Two signals — emits per-signal lines + multi-signal footer, no strong header
 *   (9)  Three signals — emits strong header first + per-signal lines + footer
 *   (10) Five signals — emits strong header first, all five lines, footer
 *   (11) Deterministic ordering — fixed emission sequence regardless of input variation
 *   (12) Empty matchResults array — gracefully returns no-signals fallback
 *   (13) overlap_signals missing key — gracefully returns no-signals fallback
 *   (14) Governance — source file references no DB, Eloquent, OpenAI, or HTTP classes
 *   (15) Strong header appears before any per-signal line
 *   (16) Multi-signal footer appears after all per-signal lines
 *   (17) No-signals fallback absent when at least one signal present
 *   (18) Multi-signal footer absent when only one signal present
 *   (19) Strong header absent when only two signals present
 *   (20) matched_neighborhoods non-empty triggers neighborhood insight (array check)
 */
class LocationMatchInsightServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): LocationMatchInsightService
    {
        return new LocationMatchInsightService();
    }

    /**
     * Build a fully-empty (no signals) match result.
     */
    private function emptyResult(): array
    {
        return [
            'matched_cities'        => [],
            'city_match'            => false,
            'matched_zips'          => [],
            'zip_match'             => false,
            'matched_neighborhoods' => [],
            'polygon_match'         => false,
            'matched_polygon_count' => 0,
            'radius_match'          => false,
            'matched_radius_count'  => 0,
            'overlap_signals'       => [],
        ];
    }

    /**
     * Build a result with the given signals fired.
     *
     * @param  string[] $signals  Subset of: city, zip, neighborhood, polygon, radius
     */
    private function resultWith(array $signals): array
    {
        $r = $this->emptyResult();
        $r['overlap_signals'] = $signals;

        if (in_array('city', $signals, true)) {
            $r['city_match']      = true;
            $r['matched_cities']  = ['Tampa'];
        }

        if (in_array('zip', $signals, true)) {
            $r['zip_match']      = true;
            $r['matched_zips']   = ['33601'];
        }

        if (in_array('neighborhood', $signals, true)) {
            $r['matched_neighborhoods'] = ['Palma Ceia'];
        }

        if (in_array('polygon', $signals, true)) {
            $r['polygon_match']         = true;
            $r['matched_polygon_count'] = 1;
        }

        if (in_array('radius', $signals, true)) {
            $r['radius_match']         = true;
            $r['matched_radius_count'] = 1;
        }

        return $r;
    }

    // =========================================================================
    // (1) Output shape
    // =========================================================================

    /** @test */
    public function output_always_has_insights_key_with_array_value(): void
    {
        $result = $this->service()->buildInsights($this->emptyResult());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('insights', $result);
        $this->assertIsArray($result['insights']);
    }

    // =========================================================================
    // (2–6) Single-signal cases
    // =========================================================================

    /** @test */
    public function city_only_emits_city_insight_and_no_footer(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['city']))['insights'];

        $this->assertContains('Property aligns with a preferred city.', $insights);
        $this->assertNotContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    /** @test */
    public function zip_only_emits_zip_insight_and_no_footer(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['zip']))['insights'];

        $this->assertContains('Property aligns with a preferred ZIP code.', $insights);
        $this->assertNotContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    /** @test */
    public function neighborhood_only_emits_neighborhood_insight_and_no_footer(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['neighborhood']))['insights'];

        $this->assertContains('Property aligns with a preferred neighborhood.', $insights);
        $this->assertNotContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    /** @test */
    public function polygon_only_emits_polygon_insight_and_no_footer(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['polygon']))['insights'];

        $this->assertContains('Property falls within a preferred search area.', $insights);
        $this->assertNotContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    /** @test */
    public function radius_only_emits_radius_insight_and_no_footer(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['radius']))['insights'];

        $this->assertContains('Property falls within a preferred search radius.', $insights);
        $this->assertNotContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    // =========================================================================
    // (7) No signals
    // =========================================================================

    /** @test */
    public function no_signals_emits_only_fallback(): void
    {
        $insights = $this->service()->buildInsights($this->emptyResult())['insights'];

        $this->assertSame(['No direct location preference overlap detected.'], $insights);
    }

    // =========================================================================
    // (8) Two signals
    // =========================================================================

    /** @test */
    public function two_signals_emit_per_signal_lines_and_footer_but_no_strong_header(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['city', 'zip']))['insights'];

        $this->assertContains('Property aligns with a preferred city.', $insights);
        $this->assertContains('Property aligns with a preferred ZIP code.', $insights);
        $this->assertContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('Strong location match.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    // =========================================================================
    // (9) Three signals → strong header
    // =========================================================================

    /** @test */
    public function three_signals_emit_strong_header_per_signal_lines_and_footer(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['city', 'zip', 'neighborhood']))['insights'];

        $this->assertContains('Strong location match.', $insights);
        $this->assertContains('Property aligns with a preferred city.', $insights);
        $this->assertContains('Property aligns with a preferred ZIP code.', $insights);
        $this->assertContains('Property aligns with a preferred neighborhood.', $insights);
        $this->assertContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
    }

    // =========================================================================
    // (10) All five signals
    // =========================================================================

    /** @test */
    public function all_five_signals_emit_strong_header_all_lines_and_footer(): void
    {
        $insights = $this->service()->buildInsights(
            $this->resultWith(['city', 'zip', 'neighborhood', 'polygon', 'radius'])
        )['insights'];

        $this->assertContains('Strong location match.', $insights);
        $this->assertContains('Property aligns with a preferred city.', $insights);
        $this->assertContains('Property aligns with a preferred ZIP code.', $insights);
        $this->assertContains('Property aligns with a preferred neighborhood.', $insights);
        $this->assertContains('Property falls within a preferred search area.', $insights);
        $this->assertContains('Property falls within a preferred search radius.', $insights);
        $this->assertContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('No direct location preference overlap detected.', $insights);
        $this->assertCount(7, $insights);
    }

    // =========================================================================
    // (11) Deterministic ordering
    // =========================================================================

    /** @test */
    public function emission_order_is_deterministic_for_all_five_signals(): void
    {
        $expected = [
            'Strong location match.',
            'Property aligns with a preferred city.',
            'Property aligns with a preferred ZIP code.',
            'Property aligns with a preferred neighborhood.',
            'Property falls within a preferred search area.',
            'Property falls within a preferred search radius.',
            'Multiple location preference signals align.',
        ];

        $insights = $this->service()->buildInsights(
            $this->resultWith(['city', 'zip', 'neighborhood', 'polygon', 'radius'])
        )['insights'];

        $this->assertSame($expected, $insights);
    }

    /** @test */
    public function emission_order_is_deterministic_for_two_signals(): void
    {
        $expected = [
            'Property aligns with a preferred city.',
            'Property aligns with a preferred ZIP code.',
            'Multiple location preference signals align.',
        ];

        $insights = $this->service()->buildInsights($this->resultWith(['city', 'zip']))['insights'];

        $this->assertSame($expected, $insights);
    }

    // =========================================================================
    // (12) Empty input
    // =========================================================================

    /** @test */
    public function empty_match_results_array_returns_no_signals_fallback(): void
    {
        $result = $this->service()->buildInsights([]);

        $this->assertSame(['insights' => ['No direct location preference overlap detected.']], $result);
    }

    // =========================================================================
    // (13) Missing overlap_signals key
    // =========================================================================

    /** @test */
    public function missing_overlap_signals_key_returns_no_signals_fallback(): void
    {
        $r = $this->emptyResult();
        unset($r['overlap_signals']);

        $result = $this->service()->buildInsights($r);

        $this->assertContains('No direct location preference overlap detected.', $result['insights']);
    }

    // =========================================================================
    // (14) Governance
    // =========================================================================

    /** @test */
    public function governance_source_file_contains_no_forbidden_class_references(): void
    {
        $source = file_get_contents(
            base_path('app/Services/LocationDna/LocationMatchInsightService.php')
        );

        // Check for actual use-statements and method-call patterns, not bare words
        // that may appear legitimately in governance docblock comments.
        $forbidden = [
            // Import patterns
            'use Illuminate\\Database'             => 'Illuminate\\Database import',
            'use Illuminate\\Support\\Facades\\DB' => 'DB facade import',
            'use Eloquent'                         => 'Eloquent import',
            'use Livewire'                         => 'Livewire import',
            'use GuzzleHttp'                       => 'GuzzleHttp import',
            'use OpenAI\\'                         => 'OpenAI import',
            // Method-call / facade patterns
            'DB::'                                 => 'DB facade call',
            'Http::'                               => 'Http facade call',
            'Schema::'                             => 'Schema facade call',
        ];

        foreach ($forbidden as $pattern => $description) {
            $this->assertStringNotContainsString(
                $pattern,
                $source,
                "Source file must not contain forbidden pattern ({$description}): {$pattern}"
            );
        }
    }

    // =========================================================================
    // (15) Strong header position
    // =========================================================================

    /** @test */
    public function strong_header_is_the_first_insight_when_present(): void
    {
        $insights = $this->service()->buildInsights(
            $this->resultWith(['city', 'zip', 'neighborhood'])
        )['insights'];

        $this->assertSame('Strong location match.', $insights[0]);
    }

    // =========================================================================
    // (16) Multi-signal footer position
    // =========================================================================

    /** @test */
    public function multi_signal_footer_is_the_last_insight_when_present(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['city', 'zip']))['insights'];

        $this->assertSame('Multiple location preference signals align.', end($insights));
    }

    // =========================================================================
    // (17) No-signals fallback absent when signals present
    // =========================================================================

    /** @test */
    public function no_signals_fallback_is_absent_when_at_least_one_signal_present(): void
    {
        foreach (['city', 'zip', 'neighborhood', 'polygon', 'radius'] as $signal) {
            $insights = $this->service()->buildInsights($this->resultWith([$signal]))['insights'];

            $this->assertNotContains(
                'No direct location preference overlap detected.',
                $insights,
                "Fallback must be absent when signal '{$signal}' is present."
            );
        }
    }

    // =========================================================================
    // (18) Multi-signal footer absent for single signal
    // =========================================================================

    /** @test */
    public function multi_signal_footer_is_absent_for_single_signal(): void
    {
        foreach (['city', 'zip', 'neighborhood', 'polygon', 'radius'] as $signal) {
            $insights = $this->service()->buildInsights($this->resultWith([$signal]))['insights'];

            $this->assertNotContains(
                'Multiple location preference signals align.',
                $insights,
                "Footer must be absent for single signal '{$signal}'."
            );
        }
    }

    // =========================================================================
    // (19) Strong header absent for two signals
    // =========================================================================

    /** @test */
    public function strong_header_is_absent_for_two_signals(): void
    {
        $insights = $this->service()->buildInsights($this->resultWith(['polygon', 'radius']))['insights'];

        $this->assertNotContains('Strong location match.', $insights);
    }

    // =========================================================================
    // (20) matched_neighborhoods array triggers neighborhood insight
    // =========================================================================

    /** @test */
    public function non_empty_matched_neighborhoods_triggers_neighborhood_insight(): void
    {
        $r = $this->emptyResult();
        $r['matched_neighborhoods'] = ['Westchase', 'Carrollwood'];
        $r['overlap_signals']       = ['neighborhood'];

        $insights = $this->service()->buildInsights($r)['insights'];

        $this->assertContains('Property aligns with a preferred neighborhood.', $insights);
    }
}
