<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusActivationService;
use Tests\TestCase;

/**
 * Batch 2C — the activation/retirement plan author. Composes partition DDL +
 * ledger flips into an ordered, transactional plan. Pure; never executes.
 */
class CorpusActivationServiceTest extends TestCase
{
    private CorpusActivationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new CorpusActivationService();
    }

    /** @test */
    public function activation_without_a_previous_version_is_a_five_step_transaction(): void
    {
        $plan = $this->svc->plan('overture-2026-06-17.0-pinellas');

        $labels = array_column($plan, 'label');
        $this->assertSame([
            'begin transaction',
            'pin staging CHECK for O(1) attach',
            'attach new partition',
            'ledger: staging → active',
            'commit transaction',
        ], $labels);

        // sequence numbers are 1..N in order
        $this->assertSame(range(1, count($plan)), array_column($plan, 'seq'));

        $this->assertSame('BEGIN', $plan[0]['sql']);
        $this->assertSame('COMMIT', $plan[4]['sql']);
        // ledger activate carries the new version as its binding
        $this->assertSame(['overture-2026-06-17.0-pinellas'], $plan[3]['bindings']);
    }

    /** @test */
    public function a_previous_version_adds_a_supersede_step_before_commit(): void
    {
        $plan = $this->svc->plan('v-new', 'v-old');

        $labels = array_column($plan, 'label');
        $this->assertContains('ledger: active → superseded (previous)', $labels);
        $this->assertSame('commit transaction', end($plan)['label']);

        $supersede = array_values(array_filter($plan, fn ($s) => str_contains($s['label'], 'superseded')))[0];
        $this->assertSame(['v-old'], $supersede['bindings']);
    }

    /** @test */
    public function it_rejects_a_previous_version_equal_to_the_new_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->plan('v-1', 'v-1');
    }

    /** @test */
    public function retirement_detaches_then_drops(): void
    {
        $plan = $this->svc->retirementPlan('overture-2026-05-21.0-pinellas');

        $this->assertCount(2, $plan);
        $this->assertStringContainsString('DETACH PARTITION', $plan[0]['sql']);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $plan[1]['sql']);
    }

    /** @test */
    public function render_script_emits_labelled_terminated_statements(): void
    {
        $script = $this->svc->renderScript($this->svc->plan('v-new', 'v-old'));

        $this->assertStringContainsString('-- [1] begin transaction', $script);
        $this->assertStringContainsString('BEGIN;', $script);
        $this->assertStringContainsString('ATTACH PARTITION', $script);
        $this->assertStringContainsString("status = 'active'", $script);
        $this->assertStringContainsString('-- bind: v-new', $script);
        $this->assertStringContainsString('COMMIT;', $script);
    }
}
