<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NormalizedExtractIo;
use App\Services\Spatial\NormalizedPlaceRecord;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (B2/B3) — the NDJSON extract writer is deterministic (canonical
 * order, independent of input order) and idempotent, and round-trips records.
 */
class NormalizedExtractIoTest extends TestCase
{
    private NormalizedExtractIo $io;

    protected function setUp(): void
    {
        parent::setUp();
        $this->io = new NormalizedExtractIo();
    }

    private function rec(string $ref, string $cat, float $lon = -82.6, float $lat = 27.7): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord(
            source: 'overture',
            source_ref: $ref,
            gers_id: $ref,
            category_key: $cat,
            name: 'N-' . $ref,
            brand: null,
            confidence: 0.95,
            source_count: 1,
            lon: $lon,
            lat: $lat,
        );
    }

    /** @test */
    public function output_is_independent_of_input_order(): void
    {
        $forward = [
            $this->rec('a', 'restaurant'),
            $this->rec('b', 'grocery_store'),
            $this->rec('c', 'gym'),
        ];
        $shuffled = [$forward[2], $forward[0], $forward[1]];

        $this->assertSame($this->io->toNdjson($forward), $this->io->toNdjson($shuffled));
    }

    /** @test */
    public function canonical_order_is_category_key_then_source_ref(): void
    {
        $ndjson = $this->io->toNdjson([
            $this->rec('z', 'restaurant'),
            $this->rec('a', 'restaurant'),
            $this->rec('m', 'grocery_store'),
        ]);

        $refs = array_map(
            static fn (string $l) => json_decode($l, true)['source_ref'],
            array_values(array_filter(explode("\n", $ndjson)))
        );
        // grocery_store < restaurant; within restaurant, a < z.
        $this->assertSame(['m', 'a', 'z'], $refs);
    }

    /** @test */
    public function it_is_idempotent_across_reparse(): void
    {
        $records = [$this->rec('a', 'gym'), $this->rec('b', 'gym'), $this->rec('c', 'pharmacy')];
        $once = $this->io->toNdjson($records);
        $twice = $this->io->toNdjson($this->io->fromNdjson($once));

        $this->assertSame($once, $twice);
    }

    /** @test */
    public function it_round_trips_records_through_ndjson(): void
    {
        $records = [$this->rec('a', 'gym'), $this->rec('b', 'pharmacy')];
        $parsed = $this->io->fromNdjson($this->io->toNdjson($records));

        $this->assertCount(2, $parsed);
        // parsed is in canonical order: gym(a) then pharmacy(b).
        $this->assertSame('a', $parsed[0]->source_ref);
        $this->assertSame('b', $parsed[1]->source_ref);
    }

    /** @test */
    public function empty_input_produces_an_empty_string(): void
    {
        $this->assertSame('', $this->io->toNdjson([]));
        $this->assertSame([], $this->io->fromNdjson(''));
    }

    /** @test */
    public function it_writes_and_reads_a_file_returning_the_count(): void
    {
        $path = sys_get_temp_dir() . '/b2a_extract_' . getmypid() . '.ndjson';
        @unlink($path);

        $written = $this->io->writeFile($path, [$this->rec('b', 'gym'), $this->rec('a', 'gym')]);
        $this->assertSame(2, $written);
        $this->assertFileExists($path);

        $read = $this->io->readFile($path);
        $this->assertSame(['a', 'b'], array_map(static fn ($r) => $r->source_ref, $read));

        @unlink($path);
    }

    /** @test */
    public function float_coordinates_do_not_degrade_to_integers(): void
    {
        // JSON_PRESERVE_ZERO_FRACTION keeps whole-valued floats as floats so the
        // wire shape is stable regardless of coordinate value.
        $ndjson = $this->io->toNdjson([$this->rec('a', 'gym', -82.0, 27.0)]);
        $decoded = json_decode(trim($ndjson), true);
        $this->assertIsFloat($decoded['lon']);
        $this->assertIsFloat($decoded['lat']);
    }
}
