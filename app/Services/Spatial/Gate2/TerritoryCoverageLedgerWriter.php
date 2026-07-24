<?php

namespace App\Services\Spatial\Gate2;

use App\Services\Spatial\CorpusImportLedger;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-b (Gate 2 Florida pilot, Class-2).
 *
 * TerritoryCoverageLedgerWriter — the ONLY live database writer in this slice. A Gate 2 measurement
 * run records exactly one `corpus_imports` row (SSOT §7.2 / E-32, B1.2 migration 11) whose
 * `territory_coverage` jsonb captures which territories were measured and their per-territory
 * roll-up. Everything else in C3d-b Group A is read-only.
 *
 * WHAT IT WRITES — AND WHAT IT WILL NOT TOUCH
 * -------------------------------------------
 *   • Exactly ONE table: `corpus_imports`. It never writes places, boundaries, addresses, authority
 *     links, or any other corpus table — a measurement observes the corpus, it does not mutate it.
 *   • Reuses {@see CorpusImportLedger} for the row shape, INSERT SQL, and ordered bindings (jsonb
 *     columns JSON-encoded), so the provenance ledger has a single authoring contract (2C).
 *
 * DETERMINISTIC & IDEMPOTENT
 * --------------------------
 * The (corpus_version, dataset) pair is the run key. `write()` first checks whether a row for that key
 * already exists; if so it writes NOTHING and reports `skipped`. Re-running the same measurement
 * therefore never duplicates a ledger row. The check + insert run in one transaction so concurrent
 * runs cannot both insert.
 *
 * @see \App\Services\Spatial\CorpusImportLedger
 * @see \Tests\Unit\Spatial\Gate2\TerritoryCoverageLedgerWriterTest
 */
final class TerritoryCoverageLedgerWriter
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CorpusImportLedger $ledger = new CorpusImportLedger(),
    ) {
    }

    /**
     * Record one Gate 2 measurement run in `corpus_imports`, idempotently on (corpus_version, dataset).
     *
     * @param  string               $dataset            ledger dataset tag (e.g. 'gate2_coverage')
     * @param  string               $corpusVersion      names the measured corpus snapshot (run key)
     * @param  int                  $rowCount           owned-corpus features observed across measured cells
     * @param  array<string,mixed>  $territoryCoverage  the jsonb payload (measured territories + roll-up)
     * @param  array<string,mixed>  $notes              free-form provenance
     * @param  string|null          $startedAt          ISO-8601 (caller's clock — kept out of this class)
     * @param  string|null          $finishedAt         ISO-8601
     * @return array{written:bool,skipped:bool,corpus_version:string,dataset:string,reason:string}
     */
    public function write(
        string $dataset,
        string $corpusVersion,
        int $rowCount,
        array $territoryCoverage,
        array $notes = [],
        ?string $startedAt = null,
        ?string $finishedAt = null,
    ): array {
        if (trim($dataset) === '') {
            throw new InvalidArgumentException('TerritoryCoverageLedgerWriter: dataset must be non-empty.');
        }
        if (trim($corpusVersion) === '') {
            throw new InvalidArgumentException('TerritoryCoverageLedgerWriter: corpus_version must be non-empty.');
        }

        // build() enforces row_count >= 0, non-empty corpus_version, and a valid status.
        $row = $this->ledger->build(
            dataset: $dataset,
            corpusVersion: $corpusVersion,
            rowCount: $rowCount,
            territoryCoverage: $territoryCoverage,
            status: CorpusImportLedger::STATUS_ACTIVE,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            notes: $notes,
        );

        return $this->connection->transaction(function () use ($row, $dataset, $corpusVersion): array {
            if ($this->exists($corpusVersion, $dataset)) {
                return [
                    'written'        => false,
                    'skipped'        => true,
                    'corpus_version' => $corpusVersion,
                    'dataset'        => $dataset,
                    'reason'         => 'a corpus_imports row already exists for this (corpus_version, dataset)',
                ];
            }

            $this->connection->insert($this->ledger->insertSql(), $this->ledger->insertBindings($row));

            return [
                'written'        => true,
                'skipped'        => false,
                'corpus_version' => $corpusVersion,
                'dataset'        => $dataset,
                'reason'         => 'inserted',
            ];
        });
    }

    private function exists(string $corpusVersion, string $dataset): bool
    {
        $sql = sprintf(
            'SELECT count(*) AS c FROM %s WHERE corpus_version = ? AND dataset = ?',
            CorpusImportLedger::TABLE,
        );

        $found = $this->connection->selectOne($sql, [$corpusVersion, $dataset]);

        $count = is_object($found) ? ($found->c ?? 0) : (is_array($found) ? ($found['c'] ?? 0) : 0);

        return (int) $count > 0;
    }
}
