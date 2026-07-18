<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * Pure builder for the `corpus_imports` provenance/version ledger (B1.2
 * migration 11, SSOT §7.2, E-32): every import writes exactly one row. This
 * class assembles that row and the parameterized INSERT/UPDATE SQL — it does NOT
 * execute anything. No connection, no SPATIAL_* secret, no clock read: timestamps
 * are passed in for INSERT (kept pure/testable) while the status-flip UPDATEs use
 * the server `now()` at live run time.
 *
 * `bytes` defaults to the accepted planning proxy (row_count × 450, via
 * CorpusSizingProjector) so the ledger records a size estimate even offline; an
 * explicit measured value overrides it.
 *
 * Lifecycle statuses (owner decision):
 *   staging → active            (activation flip)
 *   active  → superseded        (a newer version wins)
 *   *       → failed            (acceptance/load abort)
 */
final class CorpusImportLedger
{
    public const TABLE = 'corpus_imports';

    public const STATUS_STAGING    = 'staging';
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_FAILED     = 'failed';

    public const STATUSES = [
        self::STATUS_STAGING,
        self::STATUS_ACTIVE,
        self::STATUS_SUPERSEDED,
        self::STATUS_FAILED,
    ];

    /** INSERT columns, in order (id is bigserial → omitted). */
    public const COLUMNS = [
        'dataset',
        'corpus_version',
        'row_count',
        'bytes',
        'territory_coverage',
        'started_at',
        'finished_at',
        'status',
        'notes',
    ];

    public function __construct(
        private readonly CorpusSizingProjector $sizing = new CorpusSizingProjector(),
    ) {
    }

    /**
     * Assemble a ledger row (structured — jsonb fields stay as PHP arrays).
     *
     * @param array<string,mixed> $territoryCoverage region/bbox provenance
     * @param array<string,mixed> $notes             free-form provenance
     * @return array<string,mixed>
     */
    public function build(
        string $dataset,
        string $corpusVersion,
        int $rowCount,
        array $territoryCoverage,
        string $status = self::STATUS_STAGING,
        ?string $startedAt = null,
        ?string $finishedAt = null,
        array $notes = [],
        ?int $bytes = null,
    ): array {
        if (trim($corpusVersion) === '') {
            throw new \InvalidArgumentException('corpus_version must be a non-empty string.');
        }
        if ($rowCount < 0) {
            throw new \InvalidArgumentException("row_count must be >= 0; got {$rowCount}.");
        }
        $this->assertStatus($status);

        return [
            'dataset'            => $dataset,
            'corpus_version'     => $corpusVersion,
            'row_count'          => $rowCount,
            'bytes'              => $bytes ?? $this->sizing->project($rowCount)['total_bytes'],
            'territory_coverage' => $territoryCoverage,
            'started_at'         => $startedAt,
            'finished_at'        => $finishedAt,
            'status'             => $status,
            'notes'              => $notes,
        ];
    }

    /** Parameterized INSERT for the ledger row. Authored; never executed here. */
    public function insertSql(): string
    {
        $cols = implode(', ', self::COLUMNS);
        $placeholders = implode(', ', array_fill(0, count(self::COLUMNS), '?'));

        return sprintf('INSERT INTO %s (%s) VALUES (%s)', self::TABLE, $cols, $placeholders);
    }

    /**
     * Ordered bindings for insertSql(), jsonb columns JSON-encoded to match the
     * `?` order in COLUMNS.
     *
     * @param array<string,mixed> $row a build() result
     * @return list<mixed>
     */
    public function insertBindings(array $row): array
    {
        return array_map(function (string $col) use ($row) {
            $value = $row[$col] ?? null;
            if (in_array($col, ['territory_coverage', 'notes'], true)) {
                return json_encode($value ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return $value;
        }, self::COLUMNS);
    }

    /**
     * Flip a staged row to active (idempotent on status). Uses server now() for
     * finished_at at live run time.
     */
    public function activateSql(): string
    {
        return sprintf(
            "UPDATE %s SET status = '%s', finished_at = now() "
            . "WHERE corpus_version = ? AND status = '%s'",
            self::TABLE,
            self::STATUS_ACTIVE,
            self::STATUS_STAGING
        );
    }

    /** Retire the currently-active row for a superseded version. */
    public function supersedeSql(): string
    {
        return sprintf(
            "UPDATE %s SET status = '%s' WHERE corpus_version = ? AND status = '%s'",
            self::TABLE,
            self::STATUS_SUPERSEDED,
            self::STATUS_ACTIVE
        );
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Unknown ledger status [{$status}]. Allowed: " . implode(', ', self::STATUSES)
            );
        }
    }
}
