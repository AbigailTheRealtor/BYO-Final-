<?php

namespace App\Console\Commands;

use App\Services\SmartTags\SmartTagLifecycle;
use Illuminate\Console\Command;

/**
 * Report how much of the Bridge inventory carries governed Smart Tags. READ ONLY.
 *
 * The readiness question for seeker Smart Tag matching: a listing with no resolved
 * tag reads as UNKNOWN to the scorer, so switching matching on over a mostly
 * untagged inventory scores every pick as "no information".
 *
 * STORED is what the scorer would read today. `--simulate` adds what the governed
 * Bridge derivation WOULD resolve, computed in memory by the same deriver and
 * resolver `smart-tags:derive` uses — the prediction a backfill is judged against.
 *
 * NOTHING IS WRITTEN, by construction rather than by a flag: the audit reaches no
 * writer, projector or state saver, and on PostgreSQL it runs inside a transaction
 * declared READ ONLY, so the server itself refuses a write. That is why this
 * command, unlike the backfill, needs no production confirmation — and why it can
 * be run while every Smart Tag gate is still closed.
 */
class SmartTagCoverageReport extends Command
{
    protected $signature = 'smart-tags:coverage
                            {--provider= : Only Bridge rows from this provider (bridge_properties.provider)}
                            {--status=* : Only these StandardStatus values (repeatable)}
                            {--simulate : Also predict coverage from the governed derivation, in memory}
                            {--batch-size=500 : Rows fetched per chunk}
                            {--json : Print the full report as JSON}';

    protected $description = 'Read-only Smart Tag coverage report for Bridge listings (stored, and optionally simulated)';

    public function handle(): int
    {
        $provider = $this->option('provider');
        $provider = is_string($provider) && trim($provider) !== '' ? trim($provider) : null;

        $statuses = array_values(array_filter(
            array_map(static fn ($s) => trim((string) $s), (array) $this->option('status')),
            static fn (string $s) => $s !== '',
        ));

        $report = SmartTagLifecycle::bridgeCoverage(
            $provider,
            $statuses,
            (bool) $this->option('simulate'),
            max(1, (int) $this->option('batch-size')),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Smart Tag coverage — Bridge (READ ONLY, nothing is written)');
        $this->line(sprintf('  provider        : %s', $provider ?? 'all'));
        $this->line(sprintf('  statuses        : %s', $statuses === [] ? 'all' : implode(', ', $statuses)));
        $this->line(sprintf('  listings        : %d', $report['scope']['listings']));
        $this->line(sprintf('  tagger version  : %s', $report['scope']['tagger_version']));

        $this->section('STORED (what the scorer reads today)', $report['stored']);

        if ($report['simulated'] !== null) {
            $this->section('SIMULATED (governed derivation, in memory)', $report['simulated']);
        }

        $this->checkability($report['checkability']);

        $performance = $report['performance'];
        $this->line('');
        $this->line(sprintf(
            '  performance: %d batches of %d, %d queries, %.3fs, %s listings/s, memory +%d KiB (peak %d KiB)',
            $performance['batches'],
            $performance['batch_size'],
            $performance['queries'],
            $performance['seconds'],
            $performance['listings_per_second'] ?? 'n/a',
            intdiv($performance['memory_growth_bytes'], 1024),
            intdiv($performance['peak_memory_bytes'], 1024),
        ));

        return self::SUCCESS;
    }

    /**
     * Seeker-tag checkability by context — from the governed Bridge rules, never from how
     * often a tag occurs. "can say no" counts the structured tags some rule is DECLARED able
     * to prove absent (`bridge.negative_evidence`); the rest can be present or unknown only. Per listing × tag counts appear only with --simulate. No MLS value
     * is printed: tag keys and counts only.
     *
     * @param array<string, mixed> $checkability
     */
    private function checkability(array $checkability): void
    {
        $this->line('');
        $this->info('SEEKER TAG CHECKABILITY (governed structured Bridge rules)');

        $simulated = (bool) $checkability['simulated'];
        $headers = ['context', 'seeker tags', 'structured rule', 'can say no', 'no structured rule'];
        if ($simulated) {
            array_push($headers, 'rule + present', 'rule + zero present', 'checks: present', 'known non-match',
                'field unavailable', 'field cannot say no', 'only "Other"', 'masked', 'unknown (conflict)');
        }

        $rows = [];
        foreach ($checkability['by_context'] as $context => $row) {
            $line = [$context, $row['seeker_tags_applicable'], $row['seeker_tags_structured'], $row['seeker_tags_negative_checkable'], $row['seeker_tags_not_structured']];
            if ($simulated) {
                $checks = $row['listing_tag_checks'];
                array_push($line, $row['structured_with_present'], $row['structured_zero_present'],
                    $checks['present'], $checks['known_non_match'], $checks['source_field_unavailable'],
                    $checks['field_cannot_say_no'], $checks['uninformative_only'], $checks['masked_by_generic_value'], $checks['unknown_other']);
            }
            $rows[] = $line;
        }

        $this->table($headers, $rows);
    }

    /** @param array<string, mixed> $tally */
    private function section(string $title, array $tally): void
    {
        $this->line('');
        $this->info($title);
        $this->line(sprintf('  covered (>=1 present tag) : %d / %d (%.1f%%)', $tally['covered'], $tally['listings'], $tally['coverage_pct']));
        $this->line(sprintf('  seeker-relevant covered   : %d (%.1f%%)', $tally['seeker_covered'], $tally['seeker_coverage_pct']));
        $this->line(sprintf('  present tags / listing    : mean %.2f, median %.1f', $tally['present_tags_mean'], $tally['present_tags_median']));
        $this->line(sprintf('  stale but covered         : %d', $tally['stale_but_covered']));

        if ($tally['uncovered_reasons'] !== []) {
            $this->line('  uncovered, by reason:');
            foreach ($tally['uncovered_reasons'] as $reason => $count) {
                $this->line(sprintf('    %-28s %d', $reason, $count));
            }
        }

        foreach (['property_type', 'context', 'status', 'provider'] as $dimension) {
            $rows = [];
            foreach ($tally['by'][$dimension] as $value => $bucket) {
                $rows[] = [$value, $bucket['listings'], $bucket['covered'], $bucket['coverage_pct'] . '%', $bucket['seeker_covered'], $bucket['seeker_coverage_pct'] . '%'];
            }
            $this->table([$dimension, 'listings', 'covered', 'coverage', 'seeker covered', 'seeker coverage'], $rows);
        }

        $rows = [];
        foreach ($tally['seeker_tag_coverage'] as $key => $row) {
            $rows[] = [$key, $row['listings'], $row['eligible_listings'], $row['pct_of_eligible'] . '%'];
        }
        $this->table(['seeker-derivable tag', 'listings', 'eligible (context)', '% of eligible'], $rows);
    }
}
