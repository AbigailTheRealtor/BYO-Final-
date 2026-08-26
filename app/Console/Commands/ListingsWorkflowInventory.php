<?php

namespace App\Console\Commands;

use App\Services\Listing\ListingWorkflowBackfiller;
use App\Services\Listing\ListingWorkflowClassification;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Console\Command;

/**
 * Report how every listing row classifies, and optionally backfill the ones that can be.
 *
 * READ-ONLY BY DEFAULT. Running it with no options writes nothing — it is safe to point
 * at a shared or production database to answer "how many rows still have no product
 * identity, and why?". `--backfill` is the only mode that writes, and `--dry-run` makes
 * that mode report what it would do without doing it.
 *
 * This is the gate on any future NOT NULL enforcement: that migration should not be
 * written until this command reports zero unclassified, zero ambiguous and zero
 * conflicting rows.
 */
class ListingsWorkflowInventory extends Command
{
    protected $signature = 'listings:workflow-inventory
                            {--backfill : Write workflow_type for rows that can be classified}
                            {--dry-run : With --backfill, classify and report but write nothing}
                            {--samples=20 : How many unresolved rows to list}';

    protected $description = 'Inventory (and optionally backfill) the listing workflow_type discriminator';

    public function handle(ListingWorkflowBackfiller $backfiller): int
    {
        $backfill = (bool) $this->option('backfill');
        $dryRun   = (bool) $this->option('dry-run');

        ListingWorkflow::forgetSchemaMemo();

        if ($backfill) {
            $this->info($dryRun
                ? 'Classifying unstamped rows (DRY RUN — nothing will be written)…'
                : 'Backfilling workflow_type for rows that can be classified…');

            $report = $backfiller->backfill($dryRun);
        } else {
            $this->info('Inventorying every listing row (read-only)…');

            $report = $backfiller->inventory();
        }

        $buckets = [
            ListingWorkflow::HIRE_AGENT,
            ListingWorkflow::OFFER_LISTING,
            ListingWorkflowClassification::UNCLASSIFIED,
            ListingWorkflowClassification::AMBIGUOUS,
            ListingWorkflowClassification::CONFLICTING,
        ];

        $rows = [];

        foreach ($report->perTable() as $table => $counts) {
            $row = [$table];

            foreach ($buckets as $bucket) {
                $row[] = $counts[$bucket] ?? 0;
            }

            $rows[] = $row;
        }

        $totals = $report->totals();
        $totalRow = ['TOTAL'];

        foreach ($buckets as $bucket) {
            $totalRow[] = $totals[$bucket] ?? 0;
        }

        $rows[] = $totalRow;

        $this->table(array_merge(['table'], $buckets), $rows);

        $samples = array_slice($report->samples(), 0, (int) $this->option('samples'));

        if ($samples !== []) {
            $this->newLine();
            $this->warn('Rows needing attention (sample):');

            $this->table(
                ['table', 'id', 'bucket', 'reason'],
                array_map(static fn ($s) => [$s['table'], $s['id'], $s['bucket'], $s['reason']], $samples)
            );
        }

        $unresolved = $report->unresolvedCount();

        $this->newLine();

        if ($unresolved === 0) {
            $this->info('All rows carry a determinate workflow identity.');

            return self::SUCCESS;
        }

        $this->warn("{$unresolved} row(s) have no determinate workflow identity.");
        $this->line('These fail closed everywhere: they appear in no draft picker and can be resumed by neither product.');
        $this->line('NOT NULL enforcement must not be attempted until this reads zero.');

        // Still a success exit: an unresolved remainder is a finding to act on, not a
        // command failure. A non-zero exit here would break any CI job that runs this
        // as a report.
        return self::SUCCESS;
    }
}
