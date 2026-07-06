<?php

namespace Tests\Unit\Dna;

use App\Services\Canonical\CanonicalListing;
use App\Services\Dna\Scores\LockAndLeaveScoreService;
use Tests\TestCase;

/**
 * 55+ leak remediation (Option B) — Lock-and-Leave DEMAND scoring must be fully
 * age-invariant: 55+ status may not affect the value, and must never appear in the
 * persisted inputs or explanation. All 55+ handling lives in the Slice 2B gate.
 *
 * @see docs/matching-v2-55plus-leak-remediation-scope.md
 */
class LockAndLeaveComplianceTest extends TestCase
{
    private function demand(array $fields): CanonicalListing
    {
        return new CanonicalListing('buyer_agent', 1, $fields);
    }

    private function score(array $fields): array
    {
        return (new LockAndLeaveScoreService())->scoreDemand($this->demand($fields));
    }

    public function test_age_does_not_affect_value_completeness_explanation_or_inputs(): void
    {
        $base = [
            'demand.purchase_purpose' => 'Second Home / Vacation',
            'demand.current_status'   => 'Snowbird',
        ];

        $with55  = $this->score($base + ['demand.age_targeted' => true]);
        $not55   = $this->score($base + ['demand.age_targeted' => false]);
        $absent  = $this->score($base);

        // Every observable output is identical regardless of 55+ status.
        foreach (['value', 'data_completeness', 'confidence', 'explanation', 'inputs'] as $field) {
            $this->assertSame($with55[$field], $not55[$field], "differs by 55+ true/false on: {$field}");
            $this->assertSame($with55[$field], $absent[$field], "differs by 55+ present/absent on: {$field}");
        }
    }

    public function test_explanation_and_inputs_carry_no_age_data(): void
    {
        $r = $this->score([
            'demand.purchase_purpose' => 'Retirement / Downsizing',
            'demand.current_status'   => 'Retiree',
            'demand.age_targeted'     => true,
        ]);

        $this->assertStringNotContainsString('55', $r['explanation']);
        $this->assertStringNotContainsStringIgnoringCase('age', $r['explanation']);
        $this->assertSame(['current_status', 'purchase_purpose'], array_keys($r['inputs']));
        $this->assertArrayNotHasKey('age_targeted', $r['inputs']);
    }

    public function test_reports_v2_version(): void
    {
        $r = $this->score(['demand.purchase_purpose' => 'Second Home']);
        $this->assertSame('LOCK_AND_LEAVE_V2', $r['version']);
    }

    public function test_completeness_rebalanced_over_two_signals(): void
    {
        $both = $this->score([
            'demand.purchase_purpose' => 'Second Home',
            'demand.current_status'   => 'Snowbird',
        ]);
        $purposeOnly = $this->score(['demand.purchase_purpose' => 'Second Home']);
        $statusOnly  = $this->score(['demand.current_status' => 'Snowbird']);
        $neither     = $this->score([]);

        $this->assertSame(100, $both['data_completeness']);
        $this->assertSame(55, $purposeOnly['data_completeness']);
        $this->assertSame(45, $statusOnly['data_completeness']);

        // No non-protected signal at all → null value, zero confidence.
        $this->assertNull($neither['value']);
        $this->assertSame(0, $neither['confidence']);
    }

    public function test_value_derives_only_from_purpose_and_status(): void
    {
        // Strong purpose (+40) + strong status (+30) over base 20 = 90; age irrelevant.
        $r = $this->score([
            'demand.purchase_purpose' => 'Second Home / Vacation',
            'demand.current_status'   => 'Snowbird',
            'demand.age_targeted'     => true,
        ]);
        $this->assertSame(90, $r['value']);
    }
}
