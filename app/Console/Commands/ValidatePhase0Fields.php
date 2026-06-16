<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidatePhase0Fields extends Command
{
    protected $signature = 'bridge:validate-phase0';

    protected $description = 'Validate population rates for the 20 Phase 1 target fields in bridge_properties and write a Go/No-Go report';

    private const CRITICAL_FIELDS = [
        'latitude',
        'longitude',
        'county_or_parish',
        'property_sub_type',
        'mls_status',
    ];

    private const AUDIT_BASELINES = [
        'latitude'           => '25/25 (100%)',
        'longitude'          => '25/25 (100%)',
        'county_or_parish'   => '25/25 (100%)',
        'property_sub_type'  => '25/25 (100%)',
        'senior_community_yn'=> '25/25 (100%)',
        'mls_status'         => '25/25 (100%)',
        'year_built'         => '25/25 (100%)',
        'association_fee'    => '22/25 (88%)',
        'pets_allowed'       => '24/25 (96%)',
        'furnished'          => '9/25 (36%)',
        'garage_yn'          => '25/25 (100%)',
        'pool_private_yn'    => '25/25 (100%)',
        'waterfront_yn'      => '25/25 (100%)',
        'tax_annual_amount'  => '25/25 (100%)',
        'lot_size_sqft'      => '25/25 (100%)',
        'association_yn'     => '25/25 (100%)',
        'new_construction_yn'=> '25/25 (100%)',
        'view_yn'            => '25/25 (100%)',
        'water_view_yn'      => '25/25 (100%)',
        'cdd_yn'             => '25/25 (100%)',
    ];

    /**
     * The 20 Phase 1 target fields in order.
     * Each entry: [column_name, stellar_json_key, query_type]
     * query_type: 'scalar' or 'array'
     */
    private const FIELDS = [
        ['latitude',            'Latitude',            'scalar'],
        ['longitude',           'Longitude',           'scalar'],
        ['county_or_parish',    'CountyOrParish',      'scalar'],
        ['property_sub_type',   'PropertySubType',     'scalar'],
        ['senior_community_yn', 'SeniorCommunityYN',   'scalar'],
        ['mls_status',          'MlsStatus',           'scalar'],
        ['year_built',          'YearBuilt',           'scalar'],
        ['association_fee',     'AssociationFee',      'scalar'],
        ['pets_allowed',        'PetsAllowed',         'array'],
        ['furnished',           'Furnished',           'scalar'],
        ['garage_yn',           'GarageYN',            'scalar'],
        ['pool_private_yn',     'PoolPrivateYN',       'scalar'],
        ['waterfront_yn',       'WaterfrontYN',        'scalar'],
        ['tax_annual_amount',   'TaxAnnualAmount',     'scalar'],
        ['lot_size_sqft',       'LotSizeSquareFeet',   'scalar'],
        ['association_yn',      'AssociationYN',       'scalar'],
        ['new_construction_yn', 'NewConstructionYN',   'scalar'],
        ['view_yn',             'ViewYN',              'scalar'],
        ['water_view_yn',       'STELLAR_WaterViewYN', 'scalar'],
        ['cdd_yn',              'STELLAR_CDDYN',       'scalar'],
    ];

    public function handle(): int
    {
        $totalRows = DB::table('bridge_properties')->count();

        if ($totalRows === 0) {
            $this->error('bridge_properties is empty. Run bridge:import-properties --target=1000 first.');
            return self::FAILURE;
        }

        $this->info("Total rows in bridge_properties: {$totalRows}");
        $this->newLine();

        $results = [];
        $anyBlock           = false;
        $anyCriticalFailure = false;
        $criticalFailures   = [];

        foreach (self::FIELDS as [$col, $key, $type]) {
            if ($type === 'array') {
                $rows = DB::select(
                    "SELECT COUNT(*) AS cnt FROM bridge_properties
                     WHERE jsonb_typeof(raw_json::jsonb->'PetsAllowed') = 'array'
                       AND jsonb_array_length(raw_json::jsonb->'PetsAllowed') > 0"
                );
            } else {
                $rows = DB::select(
                    "SELECT COUNT(*) AS cnt FROM bridge_properties
                     WHERE raw_json::jsonb->>? IS NOT NULL
                       AND raw_json::jsonb->>? != ''
                       AND raw_json::jsonb->>? != 'null'",
                    [$key, $key, $key]
                );
            }

            $count      = (int) ($rows[0]->cnt ?? 0);
            $pct        = $totalRows > 0 ? round(($count / $totalRows) * 100, 1) : 0.0;
            $isCritical = in_array($col, self::CRITICAL_FIELDS, true);

            if ($isCritical) {
                $tier   = 'Critical Go';
                if ($pct >= 80.0) {
                    $status = '✓ Confirmed';
                } else {
                    $status             = '✗ Critical Failure — Phase 1 replan required';
                    $anyCriticalFailure = true;
                    $criticalFailures[] = $col;
                }
            } else {
                if ($pct >= 80.0) {
                    $tier   = 'Go';
                    $status = '✓ Confirmed';
                } elseif ($pct >= 50.0) {
                    $tier   = 'Caution';
                    $status = '⚠ Caution — see Priority Adjustments';
                } else {
                    $tier     = 'Block';
                    $status   = '✗ Block — demoted to Phase 2';
                    $anyBlock = true;
                }
            }

            $results[] = [
                'column'    => $col,
                'key'       => $key,
                'count'     => $count,
                'total'     => $totalRows,
                'pct'       => $pct,
                'baseline'  => self::AUDIT_BASELINES[$col] ?? '—',
                'tier'      => $tier,
                'status'    => $status,
                'critical'  => $isCritical,
            ];
        }

        $this->table(
            ['Column', 'Stellar Key', 'Populated', 'Total', '%', 'Baseline', 'Tier', 'Status'],
            array_map(fn($r) => [
                $r['column'],
                $r['key'],
                $r['count'],
                $r['total'],
                $r['pct'] . '%',
                $r['baseline'],
                $r['tier'],
                $r['status'],
            ], $results)
        );

        $overallVerdict = ($anyCriticalFailure || $anyBlock)
            ? ($anyCriticalFailure ? 'REPLAN REQUIRED' : 'GO WITH ADJUSTMENTS')
            : 'GO';

        $this->newLine();
        if ($anyCriticalFailure) {
            $this->error("VERDICT: REPLAN REQUIRED — Critical field(s) failed: " . implode(', ', $criticalFailures));
        } elseif ($anyBlock) {
            $this->warn("VERDICT: GO WITH ADJUSTMENTS — Some non-critical fields were blocked and demoted to Phase 2.");
        } else {
            $this->info("VERDICT: GO — All critical fields confirmed. Phase 1 migration may proceed.");
        }

        $report = $this->buildReport($results, $totalRows, $anyCriticalFailure, $criticalFailures, $anyBlock);

        $outputDir  = base_path('docs/audits');
        $outputPath = $outputDir . '/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $report);
        $this->info("Report written to: docs/audits/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md");

        return self::SUCCESS;
    }

    private function buildReport(
        array $results,
        int   $totalRows,
        bool  $anyCriticalFailure,
        array $criticalFailures,
        bool  $anyBlock
    ): string {
        $date = date('Y-m-d');
        $now  = date('Y-m-d H:i:s T');

        if ($anyCriticalFailure) {
            $verdictLine = '**REPLAN REQUIRED** — ' . implode(', ', $criticalFailures) . ' failed the critical Go threshold. Phase 1 migration is blocked until the replan is complete.';
            $verdictShort = 'REPLAN REQUIRED';
        } elseif ($anyBlock) {
            $verdictLine = '**GO WITH ADJUSTMENTS** — All critical fields confirmed. Non-critical field(s) blocked and moved to Phase 2. Phase 1 migration may proceed with the adjusted column list documented in Section 3.';
            $verdictShort = 'GO WITH ADJUSTMENTS';
        } else {
            $verdictLine = '**GO** — All critical fields confirmed. Phase 1 migration may proceed.';
            $verdictShort = 'GO';
        }

        $lines   = [];
        $lines[] = "# Stellar Phase 0 — Data Validation Report";
        $lines[] = "";
        $lines[] = "> Validation date: {$date}  ";
        $lines[] = "> Total records in bridge_properties at validation time: {$totalRows}  ";
        $lines[] = "> Feed scope: Stellar MLS residential-for-sale, StandardStatus=Active  ";
        $lines[] = "> Executed by: automated (php artisan bridge:validate-phase0)  ";
        $lines[] = "> Generated: {$now}  ";
        $lines[] = "> Verdict: {$verdictShort}";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        $lines[] = "## Pagination Audit Note";
        $lines[] = "";
        $lines[] = "Before extending the import command, `BridgeApiService::fetchProperties()` was audited in full. "
            . "Findings: (a) the method accepts an `int \$limit` parameter and sends it as OData `\$top`, but has **no `\$skip` parameter and makes only a single API call**; "
            . "(b) `ImportBridgeProperties` has no pagination loop — it calls `fetchProperties(\$limit)` exactly once and stops; "
            . "(c) no retry or pagination capability existed in any form. "
            . "A new `fetchPropertiesPaginated(int \$top, int \$skip)` method was therefore added to `BridgeApiService`, "
            . "and a `--target` pagination loop was added to `ImportBridgeProperties`. "
            . "The existing `fetchProperties()` method was left unchanged to preserve backward compatibility.";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        $lines[] = "## Section 1 — Population Rate Table";
        $lines[] = "";
        $lines[] = "| Column Name | Stellar JSON Key | Count Populated | Total Records | Percentage | Audit-Sample Baseline | Threshold Tier | Status |";
        $lines[] = "|---|---|---|---|---|---|---|---|";

        foreach ($results as $r) {
            $lines[] = "| `{$r['column']}` | `{$r['key']}` | {$r['count']} | {$r['total']} | {$r['pct']}% | {$r['baseline']} | {$r['tier']} | {$r['status']} |";
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        $lines[] = "## Section 2 — Confirmed Fields";
        $lines[] = "";

        $confirmed = array_filter($results, fn($r) => str_starts_with($r['status'], '✓'));
        if (empty($confirmed)) {
            $lines[] = "_No fields were confirmed at the Go threshold._";
        } else {
            foreach ($confirmed as $r) {
                $lines[] = "- `{$r['column']}` (`{$r['key']}`): {$r['pct']}% — confirmed for Phase 1 as planned.";
            }
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        $lines[] = "## Section 3 — Priority Adjustments";
        $lines[] = "";

        $adjusted = array_filter($results, fn($r) => !str_starts_with($r['status'], '✓'));

        if (empty($adjusted)) {
            $lines[] = "No adjustments required — all fields confirmed at Go threshold or above.";
        } else {
            foreach ($adjusted as $r) {
                $lines[] = "### {$r['column']}";
                $lines[] = "";
                $lines[] = "- **Measured rate**: {$r['pct']}%";
                $lines[] = "- **Audit-sample baseline**: {$r['baseline']}";
                $lines[] = "- **Tier**: {$r['tier']}";

                if (str_starts_with($r['status'], '✗ Critical')) {
                    $lines[] = "- **Action**: Phase 1 migration blocked pending feed configuration investigation. "
                        . "This critical field is required for the matching engine's foundational query patterns. "
                        . "Do not proceed with Phase 1 until this field's population rate is investigated and resolved.";
                } elseif ($r['tier'] === 'Caution') {
                    $lines[] = "- **Action**: Included with null-handling note added to Phase 1 migration plan.";
                    $lines[] = "- **Null-handling requirement**: The matching engine must assign a neutral score (not zero) "
                        . "when `{$r['column']}` is null for a listing, rather than treating null as 'feature absent.' "
                        . "Query filters on this column must use IS NULL safety rather than equality-only predicates.";
                } else {
                    $lines[] = "- **Action**: Removed from Phase 1 DDL; moved to Phase 2 with note.";
                    $lines[] = "- **Phase 2 rationale**: This field's population rate of {$r['pct']}% falls below the 50% Block threshold "
                        . "in the current residential-for-sale feed. Promotion to a native column would create a misleading "
                        . "schema dimension where the majority of rows are NULL. "
                        . $this->phase2Rationale($r['column']);
                }

                $lines[] = "";
            }
        }

        $lines[] = "---";
        $lines[] = "";

        $lines[] = "## Section 4 — Go/No-Go Verdict";
        $lines[] = "";
        $lines[] = $verdictLine;
        $lines[] = "";

        $confirmedCount = count(array_filter($results, fn($r) => str_starts_with($r['status'], '✓')));
        $cautionCount   = count(array_filter($results, fn($r) => str_starts_with($r['status'], '⚠')));
        $blockCount     = count(array_filter($results, fn($r) => str_starts_with($r['status'], '✗ Block')));
        $criticalFail   = count(array_filter($results, fn($r) => str_starts_with($r['status'], '✗ Critical')));

        $lines[] = "**Summary:**";
        $lines[] = "";
        $lines[] = "- Confirmed (Go): {$confirmedCount} fields";
        $lines[] = "- Caution (50–79%): {$cautionCount} fields";
        $lines[] = "- Blocked (<50%, demoted to Phase 2): {$blockCount} fields";
        $lines[] = "- Critical failures (Phase 1 blocked): {$criticalFail} fields";
        $lines[] = "";
        $lines[] = "**Next steps:**";
        $lines[] = "";

        if ($anyCriticalFailure) {
            $lines[] = "1. Investigate the Stellar feed configuration for the failing critical field(s): " . implode(', ', $criticalFailures) . ".";
            $lines[] = "2. Determine whether the low population rate is a feed configuration issue, a data-entry practice issue, or a structural gap in the Stellar MLS data.";
            $lines[] = "3. Re-run this validation after the feed configuration is corrected.";
            $lines[] = "4. Do not write or run any Phase 1 migration DDL until all critical fields pass the Go threshold.";
        } elseif ($anyBlock) {
            $lines[] = "1. Update `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` to reflect the adjusted Phase 1 column list (blocked fields moved to Phase 2).";
            $lines[] = "2. Proceed with Phase 1 migration using the adjusted column list.";
            $lines[] = "3. Add null-handling notes for any Caution-tier fields before the matching engine consumes them.";
        } else {
            $lines[] = "1. Proceed with Phase 1 migration as documented in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`.";
            $lines[] = "2. All 20 Phase 1 target fields are confirmed at the Go threshold. No scope adjustments are required.";
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";
        $lines[] = "## Proof Block";
        $lines[] = "";
        $lines[] = "| Item | Value |";
        $lines[] = "|---|---|";
        $lines[] = "| Command run | `php artisan bridge:validate-phase0` |";
        $lines[] = "| Validation date | {$now} |";
        $lines[] = "| Total bridge_properties rows | {$totalRows} |";
        $lines[] = "| Fields evaluated | 20 |";
        $lines[] = "| Confirmed (Go) | {$confirmedCount} |";
        $lines[] = "| Caution | {$cautionCount} |";
        $lines[] = "| Blocked (Phase 2) | {$blockCount} |";
        $lines[] = "| Critical failures | {$criticalFail} |";
        $lines[] = "| Schema changes made | **None** |";
        $lines[] = "| Migrations created | **None** |";
        $lines[] = "";

        return implode("\n", $lines) . "\n";
    }

    private function phase2Rationale(string $col): string
    {
        return match ($col) {
            'furnished' => "Phase 2 promotion is appropriate once the Stellar For Lease feed is enabled — "
                . "`Furnished` is expected to populate at a much higher rate in rental records than in residential-for-sale records.",
            'pets_allowed' => "Phase 2 promotion is appropriate once the Stellar For Lease feed is enabled — "
                . "`PetsAllowed` is a rental-primary field.",
            default => "Phase 2 promotion is appropriate when a feed configuration change or supplemental data source "
                . "can be identified that would increase this field's population rate to the Go threshold.",
        };
    }
}
