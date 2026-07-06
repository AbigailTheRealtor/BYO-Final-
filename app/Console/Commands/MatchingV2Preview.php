<?php

namespace App\Console\Commands;

use App\Services\Dna\Relevance\MatchingV2Service;
use Illuminate\Console\Command;

/**
 * matching:preview — Matching V2 C6 inspection command.
 *
 * Runs the composed pipeline (discover → narrow + compliance → rank) for one
 * subject and prints the ranked, compliant result. READ-ONLY: it never writes.
 *
 * Its purpose is pre-GA validation, so by default it FORCE-ENABLES Matching V2 in
 * this process for the duration of the run (config override only — the real flag
 * and any persisted state are untouched), then restores the flag. Pass
 * --respect-flag to honour MATCHING_V2_ENABLED instead (returns the inert empty
 * result when off).
 *
 * @see docs/matching-v2-c6-orchestration-facade-scope.md
 */
class MatchingV2Preview extends Command
{
    private const SUPPORTED = ['seller_agent', 'landlord_agent', 'buyer_agent', 'tenant_agent'];

    protected $signature = 'matching:preview
        {listingType : seller_agent|landlord_agent|buyer_agent|tenant_agent}
        {listingId : the *_agent_auctions id}
        {--cap= : override the discovery candidate cap}
        {--limit=20 : max rows to display (display only)}
        {--json : emit the machine-readable result instead of a table}
        {--respect-flag : honour MATCHING_V2_ENABLED instead of force-enabling}';

    protected $description = 'Preview the read-only Matching V2 pipeline for one subject (pre-GA validation).';

    public function handle(): int
    {
        $type = (string) $this->argument('listingType');
        $id   = (int) $this->argument('listingId');
        $cap  = $this->option('cap') !== null ? (int) $this->option('cap') : null;

        if (! in_array($type, self::SUPPORTED, true)) {
            $this->error("Unsupported subject listing_type: {$type} (expected " . implode('|', self::SUPPORTED) . ').');
            return self::FAILURE;
        }

        $realFlag = (bool) config('matching.v2_enabled', false);
        $forced   = ! $this->option('respect-flag');
        if ($forced) {
            config(['matching.v2_enabled' => true]); // in-process, read-only preview
        }

        try {
            $result = app(MatchingV2Service::class)->matchForSubject($type, $id, $cap);
        } finally {
            config(['matching.v2_enabled' => $realFlag]); // always restore
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info("Matching V2 preview — subject {$type}#{$id}");
        $this->line(sprintf(
            'MATCHING_V2_ENABLED here: %s (%s); this preview is READ-ONLY.',
            $realFlag ? 'ON' : 'OFF',
            $forced ? 'force-enabled in-process' : 'honouring flag',
        ));
        $this->line('Direction: ' . ($result->direction()?->name ?? 'n/a'));

        $rows = [];
        $rank = 1;
        foreach ($result->top((int) $this->option('limit')) as $m) {
            $rows[] = [$rank++, $m['tier'], $m['listing_type'] ?? 'n/a', $m['listing_id'], $m['value']];
        }

        if ($rows !== []) {
            $this->table(['#', 'tier', 'listing_type', 'listing_id', 'value'], $rows);
        } else {
            $this->line('No determined matches.');
        }

        $this->line(sprintf(
            'candidates=%d determined=%d undetermined=%d tiers=%s%s',
            $result->candidatesConsidered(),
            $result->determinedCount(),
            $result->undeterminedCount(),
            (string) json_encode($result->tierCounts()),
            $result->candidatePoolTruncated() ? '  [TRUNCATED]' : '',
        ));

        return self::SUCCESS;
    }
}
