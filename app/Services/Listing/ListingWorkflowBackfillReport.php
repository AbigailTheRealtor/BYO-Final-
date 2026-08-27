<?php

namespace App\Services\Listing;

use App\Support\Listing\ListingWorkflow;

/**
 * Tally of one backfill or inventory pass, per table and in total.
 *
 * The five buckets are the ones the operator actually needs to act on, and they are
 * kept distinct on purpose — an `unclassified` row wants a new backfill rule, an
 * `ambiguous` one wants a human to look at it, and a `conflicting` one is evidence that
 * some write path stamped inconsistently and should be found before it writes again.
 */
final class ListingWorkflowBackfillReport
{
    /** @var array<string, array<string,int>> table => bucket => count */
    private array $tables = [];

    /** @var array<int, array<string,mixed>> a bounded sample of rows needing attention */
    private array $samples = [];

    private int $sampleLimit;

    public function __construct(int $sampleLimit = 50)
    {
        $this->sampleLimit = $sampleLimit;
    }

    public function record(string $table, string $bucket): void
    {
        if (! isset($this->tables[$table])) {
            $this->tables[$table] = $this->emptyBuckets();
        }

        if (! array_key_exists($bucket, $this->tables[$table])) {
            $this->tables[$table][$bucket] = 0;
        }

        $this->tables[$table][$bucket]++;
    }

    /** @param array<string,mixed> $detail */
    public function sample(string $table, $id, string $bucket, string $reason, array $detail = []): void
    {
        if (count($this->samples) >= $this->sampleLimit) {
            return;
        }

        $this->samples[] = [
            'table'    => $table,
            'id'       => $id,
            'bucket'   => $bucket,
            'reason'   => $reason,
            'evidence' => $detail,
        ];
    }

    /** @return array<string,int> */
    private function emptyBuckets(): array
    {
        return [
            ListingWorkflow::HIRE_AGENT    => 0,
            ListingWorkflow::OFFER_LISTING => 0,
            ListingWorkflowClassification::UNCLASSIFIED => 0,
            ListingWorkflowClassification::AMBIGUOUS    => 0,
            ListingWorkflowClassification::CONFLICTING  => 0,
        ];
    }

    /** @return array<string, array<string,int>> */
    public function perTable(): array
    {
        return $this->tables;
    }

    /** @return array<string,int> */
    public function totals(): array
    {
        $totals = $this->emptyBuckets();

        foreach ($this->tables as $buckets) {
            foreach ($buckets as $bucket => $count) {
                $totals[$bucket] = ($totals[$bucket] ?? 0) + $count;
            }
        }

        return $totals;
    }

    /** @return array<int, array<string,mixed>> */
    public function samples(): array
    {
        return $this->samples;
    }

    /**
     * Rows that could not be given an identity — the work list, and the gate on any
     * future NOT NULL enforcement.
     */
    public function unresolvedCount(): int
    {
        $t = $this->totals();

        return $t[ListingWorkflowClassification::UNCLASSIFIED]
            + $t[ListingWorkflowClassification::AMBIGUOUS]
            + $t[ListingWorkflowClassification::CONFLICTING];
    }

    public function resolvedCount(): int
    {
        $t = $this->totals();

        return $t[ListingWorkflow::HIRE_AGENT] + $t[ListingWorkflow::OFFER_LISTING];
    }

    /** @return array<string,mixed> */
    public function toLogContext(): array
    {
        return [
            'totals'     => $this->totals(),
            'per_table'  => $this->perTable(),
            'unresolved' => $this->unresolvedCount(),
            'resolved'   => $this->resolvedCount(),
        ];
    }
}
