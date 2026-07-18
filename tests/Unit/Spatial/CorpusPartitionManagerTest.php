<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusPartitionManager;
use Tests\TestCase;

/**
 * Batch 2C — the LIST-partition DDL author. Pure string authoring: no DB, no
 * cluster, no SPATIAL_* secret. Verifies naming, sanitization, guards, and the
 * exact SQL shapes the live import runs.
 */
class CorpusPartitionManagerTest extends TestCase
{
    private CorpusPartitionManager $mgr;
    private string $version = 'overture-2026-06-17.0-pinellas';
    private string $partition = 'places_p_overture_2026_06_17_0_pinellas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mgr = new CorpusPartitionManager();
    }

    /** @test */
    public function it_derives_a_sanitized_partition_identifier(): void
    {
        $this->assertSame($this->partition, $this->mgr->partitionName($this->version));
    }

    /** @test */
    public function it_rejects_an_empty_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mgr->partitionName('   ');
    }

    /** @test */
    public function it_rejects_a_version_that_would_exceed_the_pg_identifier_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mgr->partitionName(str_repeat('a', 60)); // 9-char prefix + 60 > 63
    }

    /** @test */
    public function it_authors_a_direct_partition_of_places(): void
    {
        $sql = $this->mgr->createPartitionSql($this->version);
        $this->assertStringContainsString("PARTITION OF places FOR VALUES IN ('{$this->version}')", $sql);
        $this->assertStringContainsString($this->partition, $sql);
    }

    /** @test */
    public function it_authors_a_detached_staging_table_like_places(): void
    {
        $sql = $this->mgr->createStagingTableSql($this->version);
        $this->assertStringContainsString("CREATE TABLE IF NOT EXISTS {$this->partition}", $sql);
        $this->assertStringContainsString('LIKE places INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES', $sql);
        // A staging table is NOT yet a partition.
        $this->assertStringNotContainsString('PARTITION OF', $sql);
    }

    /** @test */
    public function it_authors_the_check_and_attach_for_an_o1_flip(): void
    {
        $check = $this->mgr->addCheckConstraintSql($this->version);
        $this->assertStringContainsString("CHECK (corpus_version = '{$this->version}')", $check);
        $this->assertStringContainsString("{$this->partition}_ck", $check);

        $attach = $this->mgr->attachPartitionSql($this->version);
        $this->assertSame(
            "ALTER TABLE places ATTACH PARTITION {$this->partition} FOR VALUES IN ('{$this->version}')",
            $attach
        );
    }

    /** @test */
    public function it_authors_detach_and_drop(): void
    {
        $this->assertSame(
            "ALTER TABLE places DETACH PARTITION {$this->partition}",
            $this->mgr->detachPartitionSql($this->version)
        );
        $this->assertSame(
            "DROP TABLE IF EXISTS {$this->partition}",
            $this->mgr->dropPartitionSql($this->version)
        );
    }

    /** @test */
    public function it_escapes_single_quotes_in_the_version_literal(): void
    {
        $sql = $this->mgr->createPartitionSql("a'b");
        $this->assertStringContainsString("FOR VALUES IN ('a''b')", $sql);
        // …while the identifier is sanitized to safe characters.
        $this->assertStringContainsString('places_p_a_b', $sql);
    }
}
