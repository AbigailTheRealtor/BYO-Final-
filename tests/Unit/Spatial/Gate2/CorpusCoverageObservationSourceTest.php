<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\Gate2\CorpusCoverageObservationSource;
use App\Services\Spatial\Gate2\CoverageQueryCatalog;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-b — CorpusCoverageObservationSource turns catalog COUNTs into assembler-shape
 * observations, emits ONLY measured cells, preserves a real zero as measured-zero (never null, never
 * dropped), never coerces an unqueried cell to zero, and performs no writes. Pure — a fake runner
 * stands in for pgsql_spatial.
 */
class CorpusCoverageObservationSourceTest extends TestCase
{
    private function catalog(): CoverageQueryCatalog
    {
        return new CoverageQueryCatalog(
            [
                'overture_places' => [
                    'categories' => ['grocery_store', 'restaurant', 'pharmacy'],
                    'measure'    => ['strategy' => 'places', 'table' => 'places', 'category_column' => 'category_key', 'geom_column' => 'geom'],
                ],
                'noaa_cusp' => ['categories' => ['shoreline'], 'measure' => null],
            ],
            ['FL' => '12', 'PR' => '72'],
            'county',
        );
    }

    /** @test */
    public function it_emits_one_measured_observation_per_catalog_query(): void
    {
        $counts = ['grocery_store' => 5, 'restaurant' => 0, 'pharmacy' => 2];
        $calls  = 0;

        $runner = function (string $sql, array $bindings) use ($counts, &$calls): int {
            $calls++;
            $this->assertStringContainsString('count(*)', $sql);
            return $counts[$bindings[0]] ?? -1;
        };

        $observations = (new CorpusCoverageObservationSource($this->catalog(), $runner))->observe(['FL']);

        $this->assertSame(3, $calls, 'one runner call per measurable FL query');
        $this->assertCount(3, $observations);

        foreach ($observations as $o) {
            $this->assertSame('overture_places', $o['dataset']);
            $this->assertSame('FL', $o['territory']);
            $this->assertTrue($o['measured']);
            $this->assertIsInt($o['present_count']);
        }
    }

    /** @test */
    public function a_real_zero_is_measured_zero_not_null_and_not_dropped(): void
    {
        $runner = static fn (string $sql, array $bindings): int => 0;

        $observations = (new CorpusCoverageObservationSource($this->catalog(), $runner))->observe(['FL']);

        $this->assertCount(3, $observations, 'a zero count is still a measured cell — never dropped');
        foreach ($observations as $o) {
            $this->assertTrue($o['measured']);
            $this->assertSame(0, $o['present_count'], 'measured zero, never null');
        }
    }

    /** @test */
    public function it_never_emits_an_unqueried_cell_so_pr_and_watch_datasets_stay_unmeasured(): void
    {
        $runner = static fn (string $sql, array $bindings): int => 1;

        // Only FL requested → no PR observations; noaa_cusp is unmeasured everywhere.
        $observations = (new CorpusCoverageObservationSource($this->catalog(), $runner))->observe(['FL']);

        foreach ($observations as $o) {
            $this->assertSame('FL', $o['territory'], 'no unqueried territory may appear');
            $this->assertNotSame('noaa_cusp', $o['dataset'], 'a declared-unmeasured dataset must never be emitted');
        }
    }

    /** @test */
    public function it_rejects_a_negative_count_from_the_runner(): void
    {
        $runner = static fn (string $sql, array $bindings): int => -1;

        $this->expectException(InvalidArgumentException::class);

        (new CorpusCoverageObservationSource($this->catalog(), $runner))->observe(['FL']);
    }
}
