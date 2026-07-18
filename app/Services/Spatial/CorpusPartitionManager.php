<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * Pure DDL AUTHOR for the `places` LIST partition of a single corpus_version.
 * `places` is `PARTITION BY LIST (corpus_version)` (B1.2 migration 04) and its
 * partitions are created at IMPORT time — that is this batch's concern. This
 * class emits the partition SQL as strings ONLY; it opens no connection, needs
 * no SPATIAL_* secret, and NEVER executes. Deterministic and side-effect-free.
 *
 * Load flow authored here (owner decision — zero-downtime LIST attach):
 *   1) createStagingTableSql()  — standalone table LIKE places, off the parent
 *   2) (COPY the corpus in — CorpusCopyLoader)
 *   3) addCheckConstraintSql()  — CHECK (corpus_version = 'v') so ATTACH is O(1)
 *   4) attachPartitionSql()     — ALTER TABLE places ATTACH PARTITION … (fast)
 * The simpler direct form (createPartitionSql) is offered for a single-shot load
 * where isolation-before-visibility is not required.
 *
 * Partition identifier: `places_p_<sanitized-version>`, lowercased, every run of
 * non [a-z0-9] collapsed to a single `_`. PostgreSQL identifiers are capped at 63
 * bytes (NAMEDATALEN-1); an over-long name is REFUSED here rather than silently
 * truncated into a collision.
 */
final class CorpusPartitionManager
{
    /** The partitioned parent (B1.2 §7.2). Never parameterized — one corpus. */
    public const PARENT_TABLE = 'places';

    /** Fixed prefix — avoids a leading digit and namespaces staging tables. */
    private const PARTITION_PREFIX = 'places_p_';

    /** PostgreSQL NAMEDATALEN-1. */
    private const MAX_IDENTIFIER_BYTES = 63;

    /**
     * Deterministic partition identifier for a corpus_version. Throws when the
     * version is empty or the derived identifier would exceed the PG limit.
     */
    public function partitionName(string $corpusVersion): string
    {
        $version = trim($corpusVersion);
        if ($version === '') {
            throw new \InvalidArgumentException('corpus_version must be a non-empty string.');
        }

        $slug = strtolower($version);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        if ($slug === '') {
            throw new \InvalidArgumentException(
                "corpus_version [{$corpusVersion}] has no alphanumeric characters to form an identifier."
            );
        }

        $name = self::PARTITION_PREFIX . $slug;
        if (strlen($name) > self::MAX_IDENTIFIER_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                'Derived partition identifier [%s] is %d bytes; PostgreSQL caps identifiers at %d.',
                $name,
                strlen($name),
                self::MAX_IDENTIFIER_BYTES
            ));
        }

        return $name;
    }

    /**
     * Direct attached partition: created already bound to the parent, so rows
     * COPY'd into it are visible in `places` immediately. Use when the version is
     * not yet queried by any consumer.
     */
    public function createPartitionSql(string $corpusVersion): string
    {
        $part = $this->partitionName($corpusVersion);

        return sprintf(
            'CREATE TABLE IF NOT EXISTS %s PARTITION OF %s FOR VALUES IN (%s)',
            $part,
            self::PARENT_TABLE,
            $this->quoteLiteral($corpusVersion)
        );
    }

    /**
     * Detached staging table, structurally identical to `places` (indexes +
     * defaults + constraints), NOT yet a partition. Load into this, then attach.
     */
    public function createStagingTableSql(string $corpusVersion): string
    {
        $part = $this->partitionName($corpusVersion);

        return sprintf(
            'CREATE TABLE IF NOT EXISTS %s (LIKE %s INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES)',
            $part,
            self::PARENT_TABLE
        );
    }

    /**
     * The matching CHECK on a detached staging table. With this present, ATTACH
     * PARTITION skips the full validation scan (O(1) metadata flip).
     */
    public function addCheckConstraintSql(string $corpusVersion): string
    {
        $part = $this->partitionName($corpusVersion);

        return sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s_ck CHECK (corpus_version = %s)',
            $part,
            $part,
            $this->quoteLiteral($corpusVersion)
        );
    }

    public function attachPartitionSql(string $corpusVersion): string
    {
        $part = $this->partitionName($corpusVersion);

        return sprintf(
            'ALTER TABLE %s ATTACH PARTITION %s FOR VALUES IN (%s)',
            self::PARENT_TABLE,
            $part,
            $this->quoteLiteral($corpusVersion)
        );
    }

    public function detachPartitionSql(string $corpusVersion): string
    {
        return sprintf(
            'ALTER TABLE %s DETACH PARTITION %s',
            self::PARENT_TABLE,
            $this->partitionName($corpusVersion)
        );
    }

    public function dropPartitionSql(string $corpusVersion): string
    {
        return sprintf('DROP TABLE IF EXISTS %s', $this->partitionName($corpusVersion));
    }

    /** Single-quote SQL string literal (doubles embedded quotes). */
    private function quoteLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
