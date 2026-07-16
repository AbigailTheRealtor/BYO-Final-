<?php

/**
 * B1.2 — mixed-geometry KNN validation runner  (RUN LATER, on the cluster).
 *
 * Boots the framework, runs the §7.5 candidate-retrieval EXPLAIN per category on
 * the pgsql_spatial connection, and feeds each plan to
 * Tests\Support\Spatial\ExplainPlanShape. Exit 0 iff every category's plan
 * matches the SIA-D41 baseline (the E-50 acceptance gate, plan §6 part A).
 *
 * This file is intentionally NOT executed during B1.2 authoring — it requires the
 * live verification cluster. It is dev/staging-only.
 *
 *   SPATIAL_DATABASE_URL=... php spikes/phase-2-batch-1b-postgis-schema/validate/run_validation.php
 */

$root = dirname(__DIR__, 3);

require $root . '/vendor/autoload.php';
require_once $root . '/tests/Support/Spatial/ExplainPlanShape.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $root . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Tests\Support\Spatial\ExplainPlanShape;

if (app()->environment('production')) {
    fwrite(STDERR, "[B1.2] Refusing to run KNN validation in production.\n");
    exit(2);
}

$conf = config('database.connections.pgsql_spatial');
if (empty($conf['url']) && empty($conf['host'])) {
    fwrite(STDERR, "[B1.2] pgsql_spatial is not configured (set SPATIAL_DATABASE_URL). Aborting.\n");
    exit(2);
}

$cv = 'fixture-tier2-v1';
$ref = "ST_SetSRID(ST_MakePoint(-95.0, 38.0), 4326)::geography";
$categories = ['airport', 'boat_ramp', 'marina', 'urgent_care', 'park'];

$engine = new ExplainPlanShape();
$conn = DB::connection('pgsql_spatial');
$allPass = true;

foreach ($categories as $cat) {
    $sql = "EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) "
         . "SELECT p.place_id, p.name FROM places p "
         . "WHERE p.category_key = ? AND p.corpus_version = ? "
         . "ORDER BY p.geom <-> {$ref} LIMIT 20";

    $rows = $conn->select($sql, [$cat, $cv]);
    $json = $rows[0]->{'QUERY PLAN'} ?? null;

    $result = $engine->evaluate($json, $cat);
    $allPass = $allPass && $result['pass'];

    printf("%-12s %s%s\n",
        $cat,
        $result['pass'] ? 'PASS' : 'FAIL',
        $result['pass'] ? '' : '  (failed: ' . implode(', ', $result['failures']) . ')'
    );
}

echo $allPass
    ? "\n[B1.2] PLAN-SHAPE GATE: PASS — composite-GiST KNN holds on mixed geography(Geometry).\n"
    : "\n[B1.2] PLAN-SHAPE GATE: FAIL — inspect plans; SSOT fallback is KNN against `centroid` (geography(Point)).\n";

exit($allPass ? 0 : 1);
