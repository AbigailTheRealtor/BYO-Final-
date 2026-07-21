<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\PlaceAuthorityLinkMaterializer;
use Tests\TestCase;

/**
 * Batch 2D Part C1 — the authored Class-2 link recipe must not drift from the framework
 * (column order, the SSOT §8.2 thresholds) and must stay guarded, offline, and read-only except
 * the single link INSERT. Pure file inspection; no PostGIS. Mirrors 2C's OvertureImportSqlManifest.
 */
class LinkAuthoritySqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2d-part-c1-authority-linking';
    }

    private function sql(): string
    {
        $path = $this->spikeDir . '/sql/link_authority.sql';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @test */
    public function the_recipe_is_marked_authored_not_run_and_cluster_guarded(): void
    {
        $sql = $this->sql();
        $this->assertStringContainsString('AUTHORED, NOT RUN', $sql);
        $this->assertStringContainsString('SPATIAL_', $sql, 'must reference the (unset) spatial guard');
    }

    /** @test */
    public function the_recipe_encodes_the_ssot_thresholds(): void
    {
        $sql = $this->sql();
        $this->assertStringContainsString('ST_DWithin(', $sql);
        $this->assertStringContainsString('150', $sql, 'the 150 m radius (SSOT §8.2)');
        $this->assertStringContainsString('similarity(', $sql);
        $this->assertStringContainsString('0.6', $sql, 'the ≥0.6 similarity floor (SSOT §8.2)');
    }

    /** @test */
    public function the_insert_column_list_equals_the_materializer_columns(): void
    {
        $this->assertStringContainsString(
            implode(', ', PlaceAuthorityLinkMaterializer::COLUMNS),
            $this->sql(),
            'INSERT column list must equal PlaceAuthorityLinkMaterializer::COLUMNS',
        );
    }

    /** @test */
    public function exactly_one_insert_into_place_authority_links_and_nothing_else_writes(): void
    {
        $sql = strtoupper($this->sql());
        $this->assertSame(1, substr_count($sql, 'INSERT INTO PLACE_AUTHORITY_LINKS'), 'exactly one link INSERT');
        foreach (['UPDATE ', 'DELETE ', 'DROP ', 'ATTACH ', 'TRUNCATE '] as $writeVerb) {
            $this->assertStringNotContainsString($writeVerb, $sql, "recipe must not {$writeVerb}");
        }
    }

    /** @test */
    public function the_spike_ships_a_readme_and_results_template(): void
    {
        $this->assertFileExists($this->spikeDir . '/README.md');
        $this->assertFileExists($this->spikeDir . '/RESULTS_TEMPLATE.md');
    }
}
