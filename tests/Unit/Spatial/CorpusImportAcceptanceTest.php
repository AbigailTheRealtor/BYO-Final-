<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusImportAcceptance;
use App\Services\Spatial\NormalizedPlaceRecord;
use Tests\TestCase;

/**
 * Batch 2C — the offline import acceptance gate. Pure evaluation over the staged
 * records; no DB, no cluster. Each invariant must fail loudly.
 */
class CorpusImportAcceptanceTest extends TestCase
{
    private CorpusImportAcceptance $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new CorpusImportAcceptance();
    }

    private function rec(array $over = []): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord(
            source: $over['source'] ?? 'overture',
            source_ref: $over['source_ref'] ?? 'ref-1',
            gers_id: $over['gers_id'] ?? 'ref-1',
            category_key: $over['category_key'] ?? 'gym',
            name: $over['name'] ?? 'Sunrise',
            brand: $over['brand'] ?? null,
            confidence: $over['confidence'] ?? 0.95,
            source_count: $over['source_count'] ?? 1,
            lon: $over['lon'] ?? -82.65,
            lat: $over['lat'] ?? 27.83,
        );
    }

    private function failed(array $verdict): array
    {
        return $verdict['failures'];
    }

    /** @test */
    public function a_clean_corpus_passes_every_invariant(): void
    {
        $verdict = $this->gate->evaluate([
            $this->rec(['source_ref' => 'a', 'category_key' => 'gym']),
            $this->rec(['source_ref' => 'b', 'category_key' => 'restaurant']),
        ]);

        $this->assertTrue($verdict['passed']);
        $this->assertSame([], $verdict['failures']);
        $this->assertSame(2, $verdict['row_count']);
    }

    /** @test */
    public function an_empty_corpus_fails_non_empty(): void
    {
        $verdict = $this->gate->evaluate([]);
        $this->assertFalse($verdict['passed']);
        $this->assertContains('non_empty', $this->failed($verdict));
    }

    /** @test */
    public function a_foreign_source_fails_source_uniform(): void
    {
        $verdict = $this->gate->evaluate([$this->rec(['source' => 'osm'])]);
        $this->assertContains('source_uniform', $this->failed($verdict));
    }

    /** @test */
    public function an_unregistered_category_fails(): void
    {
        $verdict = $this->gate->evaluate([$this->rec(['category_key' => 'airport'])]);
        $this->assertContains('category_registered', $this->failed($verdict));
    }

    /** @test */
    public function confidence_below_the_floor_fails(): void
    {
        $verdict = $this->gate->evaluate([$this->rec(['confidence' => 0.5])]);
        $this->assertContains('confidence_floor', $this->failed($verdict));
    }

    /** @test */
    public function out_of_range_coordinates_fail(): void
    {
        $verdict = $this->gate->evaluate([$this->rec(['lon' => 200.0])]);
        $this->assertContains('coordinates_valid', $this->failed($verdict));
    }

    /** @test */
    public function a_missing_source_ref_fails_identity(): void
    {
        $verdict = $this->gate->evaluate([$this->rec(['source_ref' => '  '])]);
        $this->assertContains('identity_present', $this->failed($verdict));
    }

    /** @test */
    public function the_row_count_reconciliation_is_only_checked_when_supplied(): void
    {
        $records = [$this->rec(['source_ref' => 'a']), $this->rec(['source_ref' => 'b'])];

        $mismatch = $this->gate->evaluate($records, 99);
        $this->assertContains('row_count_reconciles', $this->failed($mismatch));

        $match = $this->gate->evaluate($records, 2);
        $this->assertNotContains('row_count_reconciles', $this->failed($match));
        $this->assertTrue($match['passed']);
    }

    /** @test */
    public function every_check_names_the_offending_rows(): void
    {
        $verdict = $this->gate->evaluate([$this->rec(['source' => 'osm', 'source_ref' => 'bad-1'])]);
        $source = array_values(array_filter($verdict['checks'], fn ($c) => $c['name'] === 'source_uniform'))[0];
        $this->assertStringContainsString('bad-1', $source['detail']);
    }
}
