<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusCopyLoader;
use App\Services\Spatial\PlaceRowMaterializer;
use Tests\TestCase;

/**
 * Batch 2C — the COPY wire-format author. Serializes rows to PostgreSQL COPY text
 * (tab/`\N`/backslash rules) and authors the COPY statements. No DB, no cluster.
 */
class CorpusCopyLoaderTest extends TestCase
{
    private CorpusCopyLoader $loader;
    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new CorpusCopyLoader();
        $this->out = sys_get_temp_dir() . '/b2c_copy_' . getmypid() . '.txt';
        @unlink($this->out);
    }

    protected function tearDown(): void
    {
        @unlink($this->out);
        parent::tearDown();
    }

    /** @test */
    public function copy_statement_uses_the_materializer_column_order(): void
    {
        $sql = $this->loader->copyStatement('places_p_test');
        $this->assertStringContainsString('COPY places_p_test (', $sql);
        $this->assertStringContainsString(implode(', ', PlaceRowMaterializer::COLUMNS), $sql);
        $this->assertStringEndsWith('FROM STDIN', $sql);
    }

    /** @test */
    public function psql_copy_statement_streams_a_local_payload(): void
    {
        $sql = $this->loader->psqlCopyStatement('places_p_test', '/tmp/payload.txt');
        $this->assertStringContainsString("\\copy places_p_test (", $sql);
        $this->assertStringContainsString("FROM '/tmp/payload.txt' WITH (FORMAT text)", $sql);
    }

    /** @test */
    public function it_encodes_the_copy_text_escape_set(): void
    {
        $this->assertSame("a\\tb", $this->loader->encodeRow(["a\tb"]), 'tab → \\t');
        $this->assertSame('\\N', $this->loader->encodeRow([null]), 'null → \\N');
        $this->assertSame('c\\\\d', $this->loader->encodeRow(["c\\d"]), 'backslash doubled');
        $this->assertSame('line1\\nline2', $this->loader->encodeRow(["line1\nline2"]), 'newline → \\n');
        $this->assertSame('t', $this->loader->encodeRow([true]));
        $this->assertSame('3', $this->loader->encodeRow([3]));
        $this->assertSame('0.94', $this->loader->encodeRow([0.94]));
    }

    /** @test */
    public function empty_string_is_distinct_from_null(): void
    {
        // Empty stays empty (a zero-length field); only true null becomes \N.
        $this->assertSame("\t\\N", $this->loader->encodeRow(['', null]));
    }

    /** @test */
    public function to_copy_text_is_lf_terminated_and_empty_for_no_rows(): void
    {
        $this->assertSame("a\tb\nc\td\n", $this->loader->toCopyText([['a', 'b'], ['c', 'd']]));
        $this->assertSame('', $this->loader->toCopyText([]));
    }

    /** @test */
    public function write_payload_writes_the_file_and_returns_the_count(): void
    {
        $written = $this->loader->writePayload($this->out, [['a', 1], ['b', 2], ['c', 3]]);

        $this->assertSame(3, $written);
        $this->assertFileExists($this->out);
        $this->assertSame("a\t1\nb\t2\nc\t3\n", file_get_contents($this->out));
    }
}
