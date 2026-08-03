<?php

/**
 * FINDING 2B-3 — production audit: Tenant Offer `cities` mirror vs Search Areas blob.
 *
 * =============================================================================
 *  READ-ONLY. This script issues SELECT statements ONLY.
 * =============================================================================
 *
 *  It does NOT: write, update, delete, create temporary tables, run migrations,
 *  save or mutate any Eloquent model, or open a transaction. It reads two
 *  tables and prints aggregates. It is safe to run repeatedly and safe to run
 *  against production during business hours.
 *
 *  WHY IT EXISTS
 *  -------------
 *  Tenant Offer listings (TenantOfferListing / TenantOfferListingEdit) never
 *  write the discrete `cities` meta mirror, while the Hire flow and both Buyer
 *  Offer components do. Before adding that write, we must know how many live
 *  records carry meaningful `cities` data with an EMPTY blob — because for
 *  those, a naive mirror write would persist `[]` and destroy real data.
 *
 *  This script measures exactly that population.
 *
 *  USAGE
 *  -----
 *      php scripts/audit-2b3-cities-mirror.php
 *      php scripts/audit-2b3-cities-mirror.php --connection=pgsql
 *      php scripts/audit-2b3-cities-mirror.php --sample=5
 *      php scripts/audit-2b3-cities-mirror.php --reveal   (unmask city names)
 *
 *  Run it from the application root. It boots the Laravel container to reuse
 *  the configured connection, so it needs no DSN of its own.
 *
 *  PRIVACY
 *  -------
 *  City names are MASKED by default (first three characters, then dots) and
 *  record ids are shown as a short non-reversible digest. No address, name,
 *  email, phone, price or user identifier is read or printed — the script
 *  selects exactly three columns: the record id and two meta values.
 *
 *  Pass --reveal only if you have decided plain city names are acceptable in
 *  the output destination. Even then, nothing else is unmasked.
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// ── Options ──────────────────────────────────────────────────────────────────

$opts = getopt('', ['connection::', 'sample::', 'reveal', 'help']);

if (isset($opts['help'])) {
    echo "Read-only audit for FINDING 2B-3.\n"
       . "  --connection=NAME  connection to read (default: config database.default)\n"
       . "  --sample=N         rows per divergence category (default 8, max 25)\n"
       . "  --reveal           print city names unmasked\n";
    exit(0);
}

$connectionName = $opts['connection'] ?? config('database.default');
$sampleSize     = max(0, min(25, (int) ($opts['sample'] ?? 8)));
$reveal         = isset($opts['reveal']);

$db = DB::connection($connectionName);

// ── Identify the tables from the models, not by hardcoding ───────────────────

$parentTable = (new App\Models\TenantAgentAuction())->getTable();
$metaTable   = (new App\Models\TenantAgentAuctionMeta())->getTable();
$foreignKey  = 'tenant_agent_auction_id';

echo str_repeat('=', 78) . "\n";
echo "FINDING 2B-3 AUDIT — Tenant Offer cities mirror vs Search Areas blob\n";
echo str_repeat('=', 78) . "\n";
echo 'connection : ' . $connectionName . "\n";
echo 'database   : ' . $db->getDatabaseName() . ' (driver ' . $db->getDriverName() . ")\n";
echo 'parent     : ' . $parentTable . "\n";
echo 'meta       : ' . $metaTable . "\n";
echo 'mode       : READ-ONLY (SELECT only)' . "\n";
echo 'city names : ' . ($reveal ? 'REVEALED' : 'masked') . "\n\n";

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Normalise a stored JSON array into a clean list of non-empty strings. */
$normalise = static function ($raw): array {
    $decoded = json_decode((string) $raw, true);

    if (! is_array($decoded)) {
        return [];
    }

    $out = [];
    foreach ($decoded as $item) {
        if (is_string($item) && trim($item) !== '') {
            $out[] = trim($item);
        }
    }

    return $out;
};

/** Short non-reversible digest so a sample row can be located without exposing the id. */
$digest = static function ($id): string {
    return 'rec_' . substr(hash('sha256', 'finding-2b3:' . $id), 0, 10);
};

/** Mask a city name to its first three characters. */
$mask = static function (string $city) use ($reveal): string {
    if ($reveal) {
        return $city;
    }

    return mb_strlen($city) <= 3 ? str_repeat('.', mb_strlen($city)) : mb_substr($city, 0, 3) . '...';
};

$maskList = static function (array $cities) use ($mask): array {
    return array_map($mask, $cities);
};

// ── The one query: id + the two meta values, nothing else ────────────────────

$total = $db->table($parentTable)->count();

$rows = $db->table($parentTable . ' as a')
    ->leftJoin($metaTable . ' as m_cities', static function ($join) use ($foreignKey) {
        $join->on('m_cities.' . $foreignKey, '=', 'a.id')
             ->where('m_cities.meta_key', '=', 'cities');
    })
    ->leftJoin($metaTable . ' as m_blob', static function ($join) use ($foreignKey) {
        $join->on('m_blob.' . $foreignKey, '=', 'a.id')
             ->where('m_blob.meta_key', '=', 'location_dna_preferences');
    })
    ->select('a.id', 'm_cities.meta_value as mirror_raw', 'm_blob.meta_value as blob_raw')
    ->orderBy('a.id')
    ->get();

// ── Classify ─────────────────────────────────────────────────────────────────

$counts = [
    'total_records'                  => $total,
    'mirror_present'                 => 0,
    'mirror_nonempty'                => 0,
    'blob_present'                   => 0,
    'blob_decodes_to_array'          => 0,
    'blob_has_cities_key'            => 0,
    'blob_cities_nonempty'           => 0,
    'AT_RISK_mirror_full_blob_empty' => 0,
    'DIVERGED_both_full_different'   => 0,
    'BLOB_ONLY_mirror_empty'         => 0,
    'in_sync'                        => 0,
    'both_empty'                     => 0,
];

$samples = [
    'AT_RISK_mirror_full_blob_empty' => [],
    'DIVERGED_both_full_different'   => [],
    'BLOB_ONLY_mirror_empty'         => [],
];

foreach ($rows as $row) {
    $mirror = $normalise($row->mirror_raw);

    if ($row->mirror_raw !== null) {
        $counts['mirror_present']++;
    }
    if ($mirror !== []) {
        $counts['mirror_nonempty']++;
    }

    $blobArray  = json_decode((string) $row->blob_raw, true);
    $blobIsArr  = is_array($blobArray);
    $hasKey     = $blobIsArr && array_key_exists('cities', $blobArray);
    $blobCities = $hasKey ? $normalise(json_encode($blobArray['cities'])) : [];

    if ($row->blob_raw !== null) {
        $counts['blob_present']++;
    }
    if ($blobIsArr) {
        $counts['blob_decodes_to_array']++;
    }
    if ($hasKey) {
        $counts['blob_has_cities_key']++;
    }
    if ($blobCities !== []) {
        $counts['blob_cities_nonempty']++;
    }

    $mirrorFull = $mirror !== [];
    $blobFull   = $blobCities !== [];

    if ($mirrorFull && ! $blobFull) {
        $counts['AT_RISK_mirror_full_blob_empty']++;
        if (count($samples['AT_RISK_mirror_full_blob_empty']) < $sampleSize) {
            $samples['AT_RISK_mirror_full_blob_empty'][] = [
                'record'              => $digest($row->id),
                'mirror_city_count'   => count($mirror),
                'mirror_cities'       => $maskList($mirror),
                'blob_present'        => $row->blob_raw !== null,
                'blob_decodes'        => $blobIsArr,
                'blob_has_cities_key' => $hasKey,
            ];
        }
    } elseif ($mirrorFull && $blobFull) {
        $a = $mirror;
        $b = $blobCities;
        sort($a);
        sort($b);

        if ($a === $b) {
            $counts['in_sync']++;
        } else {
            $counts['DIVERGED_both_full_different']++;
            if (count($samples['DIVERGED_both_full_different']) < $sampleSize) {
                $samples['DIVERGED_both_full_different'][] = [
                    'record'            => $digest($row->id),
                    'mirror_city_count' => count($mirror),
                    'blob_city_count'   => count($blobCities),
                    'mirror_cities'     => $maskList($mirror),
                    'blob_cities'       => $maskList($blobCities),
                    'only_in_mirror'    => $maskList(array_values(array_diff($mirror, $blobCities))),
                    'only_in_blob'      => $maskList(array_values(array_diff($blobCities, $mirror))),
                ];
            }
        }
    } elseif (! $mirrorFull && $blobFull) {
        $counts['BLOB_ONLY_mirror_empty']++;
        if (count($samples['BLOB_ONLY_mirror_empty']) < $sampleSize) {
            $samples['BLOB_ONLY_mirror_empty'][] = [
                'record'          => $digest($row->id),
                'blob_city_count' => count($blobCities),
                'blob_cities'     => $maskList($blobCities),
                'mirror_present'  => $row->mirror_raw !== null,
            ];
        }
    } else {
        $counts['both_empty']++;
    }
}

// ── Report ───────────────────────────────────────────────────────────────────

echo "POPULATION\n" . str_repeat('-', 78) . "\n";
foreach ($counts as $label => $value) {
    printf("  %-34s %8d\n", $label, $value);
}

$atRisk = $counts['AT_RISK_mirror_full_blob_empty'];
$pct    = $total > 0 ? round(($atRisk / $total) * 100, 1) : 0.0;

echo "\nVERDICT\n" . str_repeat('-', 78) . "\n";
if ($atRisk === 0) {
    echo "  No records carry mirror data with an empty blob.\n";
    echo "  A direct mirror write would NOT destroy legacy data.\n";
} else {
    echo "  {$atRisk} of {$total} records ({$pct}%) hold city data in the mirror with an\n";
    echo "  EMPTY blob. Writing the mirror from the blob would CLEAR these.\n";
    echo "  The legacy-cities merge (Option A) is REQUIRED before the mirror write.\n";
}

foreach ($samples as $category => $rowsOut) {
    echo "\nSAMPLE — {$category} (max {$sampleSize})\n" . str_repeat('-', 78) . "\n";
    echo $rowsOut === []
        ? "  (none)\n"
        : json_encode($rowsOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n" . str_repeat('=', 78) . "\n";
echo "No rows were modified. This script performed SELECT queries only.\n";
echo str_repeat('=', 78) . "\n";
