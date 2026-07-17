<?php

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B4)
 * Q2 measurement runner (COUNT-ONLY) for the Overture first slice.
 *
 * Standalone spike script — NOT an artisan command, NOT bootstrapped into
 * Laravel, touches NO PostGIS and NO SPATIAL_* secrets.
 *
 * In Batch 2A DuckDB is NOT installed and NO Overture data is downloaded, so
 * this script DETECTS the missing DuckDB and reports every Q2 measurement as
 * PENDING. It never installs DuckDB and never fetches data. When a later
 * Class-2 workstation has DuckDB + network, re-running it executes each
 * count-only SQL and fills the projection using the accepted planning proxies.
 *
 *   Usage:  php spikes/phase-2-batch-2a-overture-first-slice/q2/run_measurements.php
 */

// Accepted planning proxies (owner decision). SSOT: config/overture_places.php
// ('sizing'). Duplicated here as literals so the spike runs without booting
// Laravel; keep in lockstep with the config.
const BYTES_PER_ROW_TOTAL = 450;
const GIST_BYTES_PER_ROW  = 94;

const RELEASE = '2026-06-17.0';

$sqlDir = dirname(__DIR__) . '/sql/q2';
$measurements = [
    'pinellas'     => 'count_pinellas.sql',
    'florida'      => 'count_florida.sql',
    'conus'        => 'count_conus.sql',
    'per_category' => 'count_per_category.sql',
    'confidence'   => 'confidence_histogram.sql',
];

fwrite(STDOUT, "Batch 2A · Q2 measurement harness (Overture first slice)\n");
fwrite(STDOUT, "Release pin: " . RELEASE . "\n");
fwrite(STDOUT, "Proxies: total ~" . BYTES_PER_ROW_TOTAL . " B/row · gist ~" . GIST_BYTES_PER_ROW . " B/row\n");
fwrite(STDOUT, str_repeat('-', 64) . "\n");

$duckdb = trim((string) @shell_exec('command -v duckdb 2>/dev/null'));

if ($duckdb === '') {
    fwrite(STDOUT, "DuckDB: NOT INSTALLED.\n");
    fwrite(STDOUT, "All live Q2 measurements are PENDING (by design — Batch 2A does not\n");
    fwrite(STDOUT, "install DuckDB or download Overture data). SQL authored and ready:\n\n");
    foreach ($measurements as $key => $file) {
        $path = $sqlDir . '/' . $file;
        $status = is_file($path) ? 'ready' : 'MISSING';
        fwrite(STDOUT, sprintf("  [PENDING] %-13s → sql/q2/%-26s (%s)\n", $key, $file, $status));
    }
    fwrite(STDOUT, "\nProjection (applied once the CONUS row count is measured):\n");
    fwrite(STDOUT, "  total_bytes = rows × " . BYTES_PER_ROW_TOTAL . "\n");
    fwrite(STDOUT, "  gist_bytes  = rows × " . GIST_BYTES_PER_ROW . "\n");
    fwrite(STDOUT, "\nRecord results in q2/RESULTS_TEMPLATE.md (copy to RESULTS.md).\n");
    exit(0);
}

// ── Live path (later Class-2 phase only; unreachable in Batch 2A). ────────────
fwrite(STDOUT, "DuckDB: {$duckdb}\n\n");
foreach (['pinellas' => 'count_pinellas.sql', 'florida' => 'count_florida.sql', 'conus' => 'count_conus.sql'] as $region => $file) {
    $path = $sqlDir . '/' . $file;
    $out = trim((string) shell_exec(escapeshellarg($duckdb) . ' -noheader -list -c ' . escapeshellarg(".read {$path}") . ' 2>&1'));
    $rows = is_numeric($out) ? (int) $out : null;
    if ($rows === null) {
        fwrite(STDOUT, sprintf("  [%s] FAILED: %s\n", $region, $out));
        continue;
    }
    fwrite(STDOUT, sprintf(
        "  [%s] rows=%d  total≈%s  gist≈%s\n",
        $region,
        $rows,
        human($rows * BYTES_PER_ROW_TOTAL),
        human($rows * GIST_BYTES_PER_ROW)
    ));
}

function human(int $bytes): string
{
    $u = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $v = (float) $bytes;
    $i = 0;
    while ($v >= 1024 && $i < count($u) - 1) {
        $v /= 1024;
        $i++;
    }

    return $i === 0 ? "{$bytes} B" : sprintf('%.2f %s', $v, $u[$i]);
}
