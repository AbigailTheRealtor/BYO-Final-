<?php

namespace App\Console\Commands;

use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaVersionService;
use Illuminate\Console\Command;

/**
 * ldna:stamp-versions — one-time backfill of POI version stamps (Stage E0).
 *
 * Stamps existing property_location_pois rows that have NULL pois_fetch_version
 * or NULL pois_scoring_version with the CURRENT versions. Existing rows were
 * produced by the current Google-only pipeline at the current weights, so
 * stamping them current establishes a clean baseline where cached rows are
 * eligible for reuse (no NULL-grandfathering — see canonical-field-mapping-spec §7).
 *
 * Idempotent: re-running stamps only rows still missing a version. No API calls.
 *
 * Usage:
 *   php artisan ldna:stamp-versions
 *   php artisan ldna:stamp-versions --chunk=1000
 */
class LdnaStampVersions extends Command
{
    protected $signature = 'ldna:stamp-versions {--chunk=500 : Rows processed per batch}';

    protected $description = 'Backfill NULL POI fetch/scoring version stamps to the current versions (Stage E0).';

    public function handle(LocationDnaVersionService $versions): int
    {
        $fetchVersion   = $versions->fetchVersion();
        $scoringVersion = $versions->scoringVersion();
        $chunk          = max(1, (int) $this->option('chunk'));

        $stamped = 0;

        PropertyLocationPoi::query()
            ->where(function ($q) {
                $q->whereNull('pois_fetch_version')->orWhereNull('pois_scoring_version');
            })
            ->chunkById($chunk, function ($rows) use ($fetchVersion, $scoringVersion, &$stamped) {
                foreach ($rows as $row) {
                    if ($row->pois_fetch_version === null) {
                        $row->pois_fetch_version = $fetchVersion;
                    }
                    if ($row->pois_scoring_version === null) {
                        $row->pois_scoring_version = $scoringVersion;
                    }
                    $row->save();
                    $stamped++;
                }
            });

        $this->info("Stamped {$stamped} POI row(s) to current versions (fetch={$fetchVersion}, scoring={$scoringVersion}).");

        return self::SUCCESS;
    }
}
