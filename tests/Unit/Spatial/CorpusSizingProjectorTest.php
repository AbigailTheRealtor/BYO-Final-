<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusSizingProjector;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (B2/B4) — the storage projection applies the accepted planning
 * proxies (450 B/row total, 94 B/row gist) with no cluster.
 */
class CorpusSizingProjectorTest extends TestCase
{
    /** @test */
    public function default_proxies_match_the_owner_decision(): void
    {
        $p = new CorpusSizingProjector();
        $this->assertSame(450, $p->bytesPerRowTotal());
        $this->assertSame(94, $p->gistBytesPerRow());
    }

    /** @test */
    public function it_projects_a_row_count_with_the_proxies(): void
    {
        $p = new CorpusSizingProjector();
        $out = $p->project(1_000_000);

        $this->assertSame(1_000_000, $out['rows']);
        $this->assertSame(450_000_000, $out['total_bytes']);
        $this->assertSame(94_000_000, $out['gist_bytes']);
    }

    /** @test */
    public function zero_rows_projects_to_zero_bytes(): void
    {
        $out = (new CorpusSizingProjector())->project(0);
        $this->assertSame(0, $out['total_bytes']);
        $this->assertSame(0, $out['gist_bytes']);
        $this->assertSame('0 B', $out['total_human']);
    }

    /** @test */
    public function negative_rows_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CorpusSizingProjector())->project(-1);
    }

    /** @test */
    public function project_many_appends_an_exact_total(): void
    {
        $p = new CorpusSizingProjector();
        $out = $p->projectMany(['pinellas' => 12_000, 'florida' => 900_000]);

        $this->assertArrayHasKey('TOTAL', $out);
        $this->assertSame(912_000, $out['TOTAL']['rows']);
        $this->assertSame(912_000 * 450, $out['TOTAL']['total_bytes']);
        $this->assertSame(912_000 * 94, $out['TOTAL']['gist_bytes']);
    }

    /** @test */
    public function from_config_reads_the_sizing_block(): void
    {
        $p = CorpusSizingProjector::fromConfig(['bytes_per_row_total' => 450, 'gist_bytes_per_row' => 94]);
        $this->assertSame(450 * 10, $p->project(10)['total_bytes']);
        $this->assertSame(94 * 10, $p->project(10)['gist_bytes']);
    }

    /** @test */
    public function human_bytes_are_formatted_in_binary_units(): void
    {
        $out = (new CorpusSizingProjector())->project(1_000_000);
        // 450,000,000 B ≈ 429.15 MiB
        $this->assertStringContainsString('MiB', $out['total_human']);
    }
}
