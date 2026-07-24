<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\Gate2\CoverageQueryCatalog;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-b — CoverageQueryCatalog produces ONLY read-only, parameterised COUNTs for the
 * measurable (dataset × category × territory) tuples, binds Florida via state FIPS, leaves PR/AK/
 * rural_CONUS and declared-unmeasured datasets unqueried, and invents no metric. Pure — no DB.
 */
class CoverageQueryCatalogTest extends TestCase
{
    /** @return array<string,mixed> */
    private function datasets(): array
    {
        return [
            'overture_places' => [
                'categories' => ['grocery_store', 'restaurant', 'pharmacy'],
                'measure'    => [
                    'strategy'        => 'places',
                    'table'           => 'places',
                    'category_column' => 'category_key',
                    'geom_column'     => 'geom',
                ],
            ],
            // declared-unmeasured (an E-32 PR watch dataset) — must never produce a query.
            'noaa_cusp' => [
                'categories' => ['shoreline'],
                'measure'    => null,
            ],
        ];
    }

    private function catalog(): CoverageQueryCatalog
    {
        return new CoverageQueryCatalog(
            $this->datasets(),
            ['FL' => '12', 'PR' => '72', 'AK' => '02'], // rural_CONUS deliberately absent
            'county',
        );
    }

    /** @test */
    public function florida_only_produces_one_count_query_per_measurable_category(): void
    {
        $queries = $this->catalog()->queriesFor(['FL']);

        // 3 overture categories × 1 measurable dataset × FL. noaa_cusp contributes nothing.
        $this->assertCount(3, $queries);
        foreach ($queries as $q) {
            $this->assertSame('overture_places', $q['dataset']);
            $this->assertSame('FL', $q['territory']);
            $this->assertStringContainsString('count(*)', $q['sql']);
            $this->assertStringContainsString('FROM places p', $q['sql']);
            // FIPS and category are BOUND, not interpolated into the SQL text.
            $this->assertSame([$q['category'], 'county', '12'], $q['bindings']);
            $this->assertStringNotContainsString('12', $q['sql']);
        }

        $this->assertSame(
            ['grocery_store', 'restaurant', 'pharmacy'],
            array_map(static fn (array $q): string => $q['category'], $queries),
        );
    }

    /** @test */
    public function it_never_queries_a_declared_unmeasured_dataset(): void
    {
        foreach ($this->catalog()->queriesFor(['FL', 'PR', 'AK']) as $q) {
            $this->assertNotSame('noaa_cusp', $q['dataset'], 'PR watch dataset must stay unmeasured');
        }

        $this->assertSame(['overture_places'], $this->catalog()->measurableDatasetKeys());
        $this->assertSame(['overture_places', 'noaa_cusp'], $this->catalog()->datasetKeys());
    }

    /** @test */
    public function rural_conus_has_no_fips_so_it_is_never_queried(): void
    {
        $this->assertSame([], $this->catalog()->queriesFor(['rural_CONUS']));
    }

    /** @test */
    public function pr_and_ak_bind_their_own_fips_when_requested(): void
    {
        $pr = $this->catalog()->queriesFor(['PR']);
        $ak = $this->catalog()->queriesFor(['AK']);

        $this->assertNotEmpty($pr);
        $this->assertNotEmpty($ak);
        $this->assertSame('72', $pr[0]['bindings'][2]);
        $this->assertSame('02', $ak[0]['bindings'][2]);
    }

    /** @test */
    public function every_query_is_read_only_with_no_destructive_verb(): void
    {
        foreach ($this->catalog()->queriesFor(['FL', 'PR', 'AK']) as $q) {
            $upper = strtoupper($q['sql']);
            foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'TRUNCATE ', 'ALTER ', 'CREATE '] as $verb) {
                $this->assertStringNotContainsString($verb, $upper, "catalog SQL must not contain {$verb}");
            }
        }
    }

    /** @test */
    public function it_computes_no_coverage_metric(): void
    {
        foreach ($this->catalog()->queriesFor(['FL']) as $q) {
            $upper = strtoupper($q['sql']);
            foreach (['::NUMERIC', '::FLOAT', '::DECIMAL', 'AVG(', 'SUM(', ' / '] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $upper, "catalog must not compute '{$forbidden}'");
            }
        }
    }

    /** @test */
    public function it_rejects_an_unsafe_identifier_in_the_registry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $catalog = new CoverageQueryCatalog(
            [
                'evil' => [
                    'categories' => ['x'],
                    'measure'    => ['strategy' => 'places', 'table' => 'places; DROP TABLE places'],
                ],
            ],
            ['FL' => '12'],
            'county',
        );

        $catalog->queriesFor(['FL']);
    }
}
