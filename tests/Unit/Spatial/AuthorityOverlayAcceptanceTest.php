<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\AuthorityOverlayAcceptance;
use App\Services\Spatial\AuthorityRecord;
use Tests\TestCase;

/**
 * Batch 2D Part C2 — the offline authority-overlay acceptance gate. Each invariant proven
 * pass and fail. Pure; no DB.
 */
class AuthorityOverlayAcceptanceTest extends TestCase
{
    private function record(string $ref, ?float $metric = 4.0, string $source = 'cms', string $name = 'Name', float $lon = -82.0, float $lat = 27.0): AuthorityRecord
    {
        return new AuthorityRecord($source, $ref, $name, $lon, $lat, $metric);
    }

    private function gate(?array $domain = [1.0, 5.0], string $source = 'cms'): AuthorityOverlayAcceptance
    {
        return new AuthorityOverlayAcceptance($source, $domain);
    }

    private static function failed(array $verdict): array
    {
        return $verdict['failures'];
    }

    /** @test */
    public function a_well_formed_batch_passes_every_invariant(): void
    {
        $v = $this->gate()->evaluate([$this->record('100001', 4.0), $this->record('100002', null)]);
        $this->assertTrue($v['passed']);
        $this->assertSame([], $v['failures']);
        $this->assertSame(2, $v['row_count']);
    }

    /** @test */
    public function an_empty_batch_fails_non_empty(): void
    {
        $this->assertContains('non_empty', self::failed($this->gate()->evaluate([])));
    }

    /** @test */
    public function a_foreign_source_tag_fails_source_uniform(): void
    {
        $v = $this->gate()->evaluate([$this->record('X', 3.0, 'usgs')]);
        $this->assertContains('source_uniform', self::failed($v));
    }

    /** @test */
    public function a_duplicate_ref_fails_ref_unique(): void
    {
        $v = $this->gate()->evaluate([$this->record('100001', 4.0), $this->record('100001', 3.0)]);
        $this->assertContains('ref_unique', self::failed($v));
    }

    /** @test */
    public function a_blank_name_fails_name_present(): void
    {
        $v = $this->gate()->evaluate([$this->record('100001', 4.0, 'cms', '   ')]);
        $this->assertContains('name_present', self::failed($v));
    }

    /** @test */
    public function out_of_range_coordinates_fail_coordinates_valid(): void
    {
        $v = $this->gate()->evaluate([$this->record('100001', 4.0, 'cms', 'Name', -999.0, 27.0)]);
        $this->assertContains('coordinates_valid', self::failed($v));
    }

    /** @test */
    public function a_metric_outside_the_domain_fails_metric_in_domain(): void
    {
        $v = $this->gate()->evaluate([$this->record('100001', 9.0)]);
        $this->assertContains('metric_in_domain', self::failed($v));
    }

    /** @test */
    public function a_null_metric_is_allowed_even_with_a_domain(): void
    {
        $v = $this->gate()->evaluate([$this->record('100002', null)]);
        $this->assertNotContains('metric_in_domain', self::failed($v));
        $this->assertTrue($v['passed']);
    }

    /** @test */
    public function a_membership_source_with_no_domain_never_flags_metric(): void
    {
        $v = $this->gate(null, 'usgs')->evaluate([$this->record('BR-1', null, 'usgs', 'Ramp', -80.1, 25.7)]);
        $this->assertTrue($v['passed']);
    }

    /** @test */
    public function a_claimed_count_mismatch_fails_row_count_reconciles(): void
    {
        $v = $this->gate()->evaluate([$this->record('100001', 4.0)], 5);
        $this->assertContains('row_count_reconciles', self::failed($v));
    }
}
