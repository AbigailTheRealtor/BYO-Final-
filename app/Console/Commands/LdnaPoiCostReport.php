<?php

namespace App\Console\Commands;

use App\Services\LocationDna\LocationDnaPoiCostReporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * ldna:poi-cost-report — Print a POI API cost savings summary from run stats.
 *
 * Usage:
 *   php artisan ldna:poi-cost-report
 *   php artisan ldna:poi-cost-report --since="2026-06-01"
 *   php artisan ldna:poi-cost-report --since="7 days ago"
 */
class LdnaPoiCostReport extends Command
{
    protected $signature = 'ldna:poi-cost-report
        {--since= : Only include runs on or after this date/time (any strtotime-compatible string)}';

    protected $description = 'Print a POI API cost savings summary (calls made, avoided, estimated savings)';

    public function handle(LocationDnaPoiCostReporter $reporter): int
    {
        $sinceOption = $this->option('since');
        $since       = null;

        if (! empty($sinceOption)) {
            $ts = strtotime($sinceOption);
            if ($ts === false) {
                $this->error("Invalid --since value: '{$sinceOption}'. Use a date string like '2026-06-01' or '7 days ago'.");
                return Command::FAILURE;
            }
            $since = date('Y-m-d H:i:s', $ts);
            $this->line("Reporting since: {$since}");
        }

        try {
            $report = $reporter->report($since);
        } catch (Throwable $e) {
            $this->error('Reporter failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('=== POI Cost Report ===');

        $this->table(
            ['Metric', 'Value'],
            [
                ['calls_made',               number_format($report['calls_made'])],
                ['calls_avoided_tile',        number_format($report['calls_avoided_tile'])],
                ['calls_avoided_grouping',    number_format($report['calls_avoided_grouping'])],
                ['cache_hit_rate_pct',        $report['cache_hit_rate_pct'] . '%'],
                ['estimated_saving_usd',      '$' . number_format($report['estimated_saving_usd'], 4)],
                ['listings_with_reused_data', count($report['listing_ids_with_reused_data'])],
            ]
        );

        if (! empty($report['listing_ids_with_reused_data'])) {
            $this->newLine();
            $this->line('Listings with at least one tile cache hit:');
            foreach ($report['listing_ids_with_reused_data'] as $entry) {
                $this->line("  {$entry['listing_type']} / {$entry['listing_id']}");
            }
        }

        if (isset($report['error'])) {
            $this->warn('Reporter encountered an error: ' . $report['error']);
        }

        return Command::SUCCESS;
    }
}
