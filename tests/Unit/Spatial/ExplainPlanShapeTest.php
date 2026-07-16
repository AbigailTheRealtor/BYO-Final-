<?php

namespace Tests\Unit\Spatial;

use PHPUnit\Framework\TestCase;
use Tests\Support\Spatial\ExplainPlanShape;

/**
 * B1.2 — unit tests for the EXPLAIN plan-shape assertion engine (no DB).
 *
 * Proves the E-50 acceptance-gate logic BEFORE it runs against the live cluster:
 * a Stage-0b-shaped plan passes; each degradation mode fails the right check.
 */
class ExplainPlanShapeTest extends TestCase
{
    /** A partitioned-Append plan whose child is the composite-GiST KNN Index Scan. */
    private function passingPlan(string $category = 'airport'): array
    {
        return [[
            'Plan' => [
                'Node Type' => 'Limit',
                'Plans' => [[
                    'Node Type' => 'Append',
                    'Plans' => [[
                        'Node Type' => 'Index Scan',
                        'Index Name' => 'places_cat_geom',
                        'Index Cond' => "(category_key = '{$category}'::text)",
                        'Order By' => '(geom <-> \'...\'::geography)',
                        'Rows Removed by Filter' => 0,
                    ]],
                ]],
            ],
        ]];
    }

    /** @test */
    public function a_stage0b_shaped_plan_passes_all_checks(): void
    {
        $result = (new ExplainPlanShape())->evaluate($this->passingPlan('airport'), 'airport');

        $this->assertTrue($result['pass'], 'Expected a clean pass; failures: ' . implode(',', $result['failures']));
        $this->assertSame([], $result['failures']);
        $this->assertTrue($result['checks']['composite_index_used']);
        $this->assertTrue($result['checks']['index_cond_on_category']);
        $this->assertTrue($result['checks']['knn_order_by_in_scan']);
        $this->assertTrue($result['checks']['no_seq_scan']);
        $this->assertTrue($result['checks']['no_sort']);
        $this->assertTrue($result['checks']['zero_rows_removed_by_filter']);
        $this->assertTrue($result['checks']['index_cond_expected_category']);
    }

    /** @test */
    public function it_accepts_a_raw_json_string(): void
    {
        $json = json_encode($this->passingPlan('marina'));
        $result = (new ExplainPlanShape())->evaluate($json, 'marina');
        $this->assertTrue($result['pass']);
    }

    /** @test */
    public function a_seq_scan_fails_the_gate(): void
    {
        $plan = [['Plan' => ['Node Type' => 'Seq Scan', 'Rows Removed by Filter' => 0]]];
        $result = (new ExplainPlanShape())->evaluate($plan);

        $this->assertFalse($result['pass']);
        $this->assertContains('no_seq_scan', $result['failures']);
        $this->assertContains('composite_index_used', $result['failures']);
    }

    /** @test */
    public function a_top_level_sort_fails_the_gate(): void
    {
        $plan = $this->passingPlan();
        // Wrap the whole thing in a Sort — the order did NOT come from the index.
        $plan = [['Plan' => ['Node Type' => 'Sort', 'Plans' => [$plan[0]['Plan']]]]];

        $result = (new ExplainPlanShape())->evaluate($plan, 'airport');
        $this->assertFalse($result['pass']);
        $this->assertContains('no_sort', $result['failures']);
    }

    /** @test */
    public function a_filter_walk_fails_the_gate(): void
    {
        // The geography-only degradation: category applied as a post-filter.
        $plan = [['Plan' => [
            'Node Type' => 'Index Scan',
            'Index Name' => 'places_geom_only',
            'Order By' => '(geom <-> \'...\'::geography)',
            'Rows Removed by Filter' => 8826,
        ]]];

        $result = (new ExplainPlanShape())->evaluate($plan, 'boat_ramp');
        $this->assertFalse($result['pass']);
        $this->assertContains('zero_rows_removed_by_filter', $result['failures']);
        $this->assertContains('composite_index_used', $result['failures']);
    }

    /** @test */
    public function a_missing_knn_order_by_fails_the_gate(): void
    {
        $plan = $this->passingPlan();
        unset($plan[0]['Plan']['Plans'][0]['Plans'][0]['Order By']);

        $result = (new ExplainPlanShape())->evaluate($plan, 'airport');
        $this->assertFalse($result['pass']);
        $this->assertContains('knn_order_by_in_scan', $result['failures']);
    }

    /** @test */
    public function the_wrong_expected_category_fails_only_that_check(): void
    {
        $result = (new ExplainPlanShape())->evaluate($this->passingPlan('airport'), 'marina');
        $this->assertFalse($result['pass']);
        $this->assertContains('index_cond_expected_category', $result['failures']);
        // The structural checks still hold.
        $this->assertTrue($result['checks']['composite_index_used']);
        $this->assertTrue($result['checks']['no_seq_scan']);
    }

    /** @test */
    public function malformed_payload_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ExplainPlanShape())->evaluate(['no plan key here']);
    }
}
