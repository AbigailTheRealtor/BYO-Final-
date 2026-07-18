<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework).
 *
 * Authors the ordered ACTIVATION plan that flips a staged corpus_version live —
 * the partition ATTACH plus the ledger status flip — and, separately, the
 * destructive RETIREMENT plan for an old version. It composes
 * CorpusPartitionManager (DDL) and CorpusImportLedger (status SQL); it produces
 * an ordered list of steps and NEVER executes them. No connection, no cluster.
 *
 * Activation flow (owner decision — zero-downtime LIST attach):
 *   BEGIN
 *   → add CHECK (corpus_version = new)   -- makes the ATTACH an O(1) metadata flip
 *   → ATTACH PARTITION new               -- new rows become visible atomically
 *   → ledger: staging → active (new)
 *   → ledger: active → superseded (previous, if any)
 *   COMMIT
 *
 * The previous partition is deliberately LEFT ATTACHED on activation (retention /
 * instant rollback); physically removing it is a separate, explicit retirement.
 */
final class CorpusActivationService
{
    public function __construct(
        private readonly CorpusPartitionManager $partitions = new CorpusPartitionManager(),
        private readonly CorpusImportLedger $ledger = new CorpusImportLedger(),
    ) {
    }

    /**
     * Ordered activation steps. Ledger steps carry `bindings` for their `?`
     * placeholder (corpus_version). Wrapped in BEGIN/COMMIT — activation is
     * all-or-nothing.
     *
     * @return list<array{seq:int,label:string,sql:string,bindings:list<mixed>}>
     */
    public function plan(string $newVersion, ?string $previousVersion = null): array
    {
        if (trim($newVersion) === '') {
            throw new \InvalidArgumentException('newVersion must be a non-empty string.');
        }
        if ($previousVersion !== null && $previousVersion === $newVersion) {
            throw new \InvalidArgumentException('previousVersion must differ from newVersion.');
        }

        $steps = [];
        $steps[] = ['label' => 'begin transaction', 'sql' => 'BEGIN', 'bindings' => []];
        $steps[] = ['label' => 'pin staging CHECK for O(1) attach', 'sql' => $this->partitions->addCheckConstraintSql($newVersion), 'bindings' => []];
        $steps[] = ['label' => 'attach new partition', 'sql' => $this->partitions->attachPartitionSql($newVersion), 'bindings' => []];
        $steps[] = ['label' => 'ledger: staging → active', 'sql' => $this->ledger->activateSql(), 'bindings' => [$newVersion]];

        if ($previousVersion !== null) {
            $steps[] = ['label' => 'ledger: active → superseded (previous)', 'sql' => $this->ledger->supersedeSql(), 'bindings' => [$previousVersion]];
        }

        $steps[] = ['label' => 'commit transaction', 'sql' => 'COMMIT', 'bindings' => []];

        return $this->sequence($steps);
    }

    /**
     * Destructive retirement of an old version's partition: detach from the
     * parent, then drop the standalone table. Explicit and separate from
     * activation — an operator runs it only after the new version is proven.
     *
     * @return list<array{seq:int,label:string,sql:string,bindings:list<mixed>}>
     */
    public function retirementPlan(string $version): array
    {
        $steps = [
            ['label' => 'detach retired partition', 'sql' => $this->partitions->detachPartitionSql($version), 'bindings' => []],
            ['label' => 'DROP retired partition table', 'sql' => $this->partitions->dropPartitionSql($version), 'bindings' => []],
        ];

        return $this->sequence($steps);
    }

    /**
     * Render a plan as a human-readable dry-run script: `-- label` comments and
     * statements terminated with `;`. Bindings are shown as trailing comments —
     * the live run binds them as parameters; this preview never inlines them.
     *
     * @param list<array{seq:int,label:string,sql:string,bindings:list<mixed>}> $plan
     */
    public function renderScript(array $plan): string
    {
        $lines = [];
        foreach ($plan as $step) {
            $lines[] = "-- [{$step['seq']}] {$step['label']}";
            $bind = $step['bindings'] === [] ? '' : '  -- bind: ' . implode(', ', array_map('strval', $step['bindings']));
            $lines[] = $step['sql'] . ';' . $bind;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<array{label:string,sql:string,bindings:list<mixed>}> $steps
     * @return list<array{seq:int,label:string,sql:string,bindings:list<mixed>}>
     */
    private function sequence(array $steps): array
    {
        $seq = 1;

        return array_map(static function (array $s) use (&$seq): array {
            return ['seq' => $seq++, 'label' => $s['label'], 'sql' => $s['sql'], 'bindings' => $s['bindings']];
        }, $steps);
    }
}
