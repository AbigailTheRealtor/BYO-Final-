<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\BoundaryImportAcceptance;
use App\Services\Spatial\BoundaryRecord;
use Tests\TestCase;

/**
 * Batch 2D Part C3a — the offline boundary acceptance gate. Each invariant proven pass and fail.
 * Pure; no DB.
 */
class BoundaryImportAcceptanceTest extends TestCase
{
    private function geometry(): array
    {
        return ['type' => 'MultiPolygon', 'coordinates' => [[[[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], [-82.0, 27.0]]]]];
    }

    private function record(string $ref = 'PADUS-0001', ?float $acres = 10.0, string $kind = 'protected_area', ?array $geometry = null): BoundaryRecord
    {
        return new BoundaryRecord($kind, $ref, $geometry ?? $this->geometry(), ['acres' => $acres, 'name' => 'X', 'source' => 'padus']);
    }

    private function gate(): BoundaryImportAcceptance
    {
        return new BoundaryImportAcceptance();
    }

    private static function failed(array $v): array
    {
        return $v['failures'];
    }

    /** @test */
    public function a_well_formed_batch_passes_every_invariant(): void
    {
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', 10.0), $this->record('PADUS-0002', null)]);
        $this->assertTrue($v['passed']);
        $this->assertSame([], $v['failures']);
    }

    /** @test */
    public function an_empty_batch_fails_non_empty(): void
    {
        $this->assertContains('non_empty', self::failed($this->gate()->evaluate([])));
    }

    /** @test */
    public function a_foreign_kind_fails_kind_valid(): void
    {
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', 10.0, 'zcta')]);
        $this->assertContains('kind_valid', self::failed($v));
    }

    /** @test */
    public function invalid_geometry_fails_geometry_multipolygon(): void
    {
        $open = ['type' => 'MultiPolygon', 'coordinates' => [[[[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0]]]]]; // unclosed
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', 10.0, 'protected_area', $open)]);
        $this->assertContains('geometry_multipolygon', self::failed($v));
    }

    /** @test */
    public function a_duplicate_external_ref_hard_fails_ref_unique_D6(): void
    {
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', 10.0), $this->record('PADUS-0001', 20.0)]);
        $this->assertContains('ref_unique', self::failed($v));
    }

    /** @test */
    public function a_blank_ref_fails_ref_present(): void
    {
        $v = $this->gate()->evaluate([new BoundaryRecord('protected_area', '', $this->geometry(), ['acres' => 1.0])]);
        $this->assertContains('ref_present', self::failed($v));
    }

    /** @test */
    public function a_negative_acres_fails_acres_non_negative(): void
    {
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', -5.0)]);
        $this->assertContains('acres_non_negative', self::failed($v));
    }

    /** @test */
    public function a_null_acres_is_allowed(): void
    {
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', null)]);
        $this->assertTrue($v['passed']);
    }

    /** @test */
    public function a_claimed_count_mismatch_fails_row_count_reconciles(): void
    {
        $v = $this->gate()->evaluate([$this->record('PADUS-0001', 10.0)], 3);
        $this->assertContains('row_count_reconciles', self::failed($v));
    }
}
