<?php

namespace App\Services\Listing;

use App\Support\Listing\ListingWorkflow;
use Illuminate\Support\Facades\Schema;

/**
 * Classify existing rows that CAN be classified, and leave the rest alone.
 *
 * IT DOES NOT GUESS, AND IT DOES NOT FAIL.
 * A row carrying signals of both products, or of neither, keeps a NULL column and is
 * counted as unresolved. That is not a shortcoming — it is the honest answer, and a
 * migration is the wrong place to make a judgement call about a real user's listing.
 * The counts and a bounded sample land in the report; the durable work list is
 * `php artisan listings:workflow-inventory`.
 *
 * IDEMPOTENT. Only rows whose native column is still NULL are considered, so re-running
 * is a no-op and a partially-completed run resumes cleanly.
 *
 * The classification itself is {@see ListingWorkflowResolver}'s and nothing else's, so
 * this backfill and the runtime guard can never disagree about what a row is. That
 * shared rule is why the two known defect shapes come out right without a special case:
 * an MLS Quick Import draft carries `mls_quick_import` and no `service_type`, which the
 * resolver reads as OFFER_LISTING for seller and landlord alike.
 */
class ListingWorkflowBackfiller
{
    public function __construct(
        private readonly ListingWorkflowResolver $resolver,
    ) {}

    /**
     * Write the column for every row the resolver can classify.
     *
     * @param  bool  $dryRun  when true, classify and count but write nothing
     */
    public function backfill(bool $dryRun = false): ListingWorkflowBackfillReport
    {
        $report = new ListingWorkflowBackfillReport();

        foreach (ListingWorkflow::roleModels() as $modelClass) {
            $this->backfillModel($modelClass, $report, $dryRun);
        }

        return $report;
    }

    /** Read-only pass — classify everything, write nothing. */
    public function inventory(): ListingWorkflowBackfillReport
    {
        $report = new ListingWorkflowBackfillReport();

        foreach (ListingWorkflow::roleModels() as $modelClass) {
            $this->inventoryModel($modelClass, $report);
        }

        return $report;
    }

    /** @param class-string $modelClass */
    private function backfillModel(string $modelClass, ListingWorkflowBackfillReport $report, bool $dryRun): void
    {
        $model = new $modelClass();
        $table = $model->getTable();

        if (! Schema::hasTable($table) || ! ListingWorkflow::columnAvailable($modelClass)) {
            return;
        }

        $modelClass::query()
            ->whereNull(ListingWorkflow::COLUMN)
            ->with('meta')
            ->chunkById(200, function ($rows) use ($report, $table, $modelClass, $dryRun) {
                foreach ($rows as $row) {
                    $classification = $this->resolver->classify($row);

                    $report->record($table, $classification->bucket());

                    if (! $classification->isResolved()) {
                        $report->sample(
                            $table,
                            $row->getKey(),
                            $classification->bucket(),
                            $classification->reason,
                            $classification->evidence
                        );

                        continue;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    // Column only. The EAV stamp is NOT written here: back-writing meta
                    // for rows that never had it would destroy the very provenance the
                    // resolver used to classify them, and would make this pass
                    // non-reversible in practice even though down() drops the column.
                    $modelClass::query()
                        ->whereKey($row->getKey())
                        ->update([ListingWorkflow::COLUMN => $classification->workflow]);
                }
            });
    }

    /** @param class-string $modelClass */
    private function inventoryModel(string $modelClass, ListingWorkflowBackfillReport $report): void
    {
        $model = new $modelClass();
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        $modelClass::query()
            ->with('meta')
            ->chunkById(200, function ($rows) use ($report, $table) {
                foreach ($rows as $row) {
                    $classification = $this->resolver->classify($row);

                    $report->record($table, $classification->bucket());

                    if (! $classification->isResolved()) {
                        $report->sample(
                            $table,
                            $row->getKey(),
                            $classification->bucket(),
                            $classification->reason,
                            $classification->evidence
                        );
                    }
                }
            });
    }
}
