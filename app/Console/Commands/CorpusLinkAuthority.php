<?php

namespace App\Console\Commands;

use App\Services\Spatial\AuthorityLinkAcceptance;
use App\Services\Spatial\AuthorityLinkMatcher;
use App\Services\Spatial\AuthorityRecord;
use App\Services\Spatial\NormalizedExtractIo;
use App\Services\Spatial\PlaceAuthorityLinkMaterializer;
use Illuminate\Console\Command;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C1 (cross-source authority linking).
 *
 * OFFLINE authority↔corpus linking DRY-RUN / plan author. Reads a synthetic authority NDJSON and a
 * normalized places extract, applies the SSOT §8.2 rule via AuthorityLinkMatcher, gates the result
 * with AuthorityLinkAcceptance, and writes the review artifacts an operator would use:
 *   • links.ndjson         — the resolved place_authority_links rows (spatial_name auto-links)
 *   • ambiguous_report.json — the ambiguous tail (≥2 candidates), for HUMAN review — never auto-linked
 *   • summary.json          — counts + the thresholds used
 *
 * HARD constraints (owner decision — enforced here):
 *   • REFUSES to run in production. There is NO execute path against a cluster.
 *   • Opens NO pgsql_spatial connection and reads NO SPATIAL_* secret — it reads two local NDJSON
 *     files and writes plan artifacts to local disk. Live linking (ST_DWithin + pg_trgm against the
 *     loaded `places` table) is the Class-2 recipe in
 *     spikes/phase-2-batch-2d-part-c1-authority-linking/sql/link_authority.sql.
 */
class CorpusLinkAuthority extends Command
{
    protected $signature = 'corpus:link-authority
        {--authority= : Authority NDJSON (defaults to the committed synthetic fixture)}
        {--places= : Normalized places extract NDJSON (defaults to the committed synthetic fixture)}
        {--radius-m= : Override the match radius in metres (default from config/spatial_authority.php)}
        {--similarity-min= : Override the name-similarity floor (default from config)}
        {--out-dir= : Directory for the dry-run artifacts}';

    protected $description = 'OFFLINE: author the cross-source authority↔corpus link plan (place_authority_links) — no PostGIS, refuses production, never executes';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('[Batch 2D Part C1] corpus:link-authority is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Live authority linking (ST_DWithin + pg_trgm against places) is deferred to the Class-2 phase.');

            return self::FAILURE;
        }

        $authorityPath = (string) ($this->option('authority')
            ?: base_path('tests/fixtures/spatial/authority/authority_sample.ndjson'));
        $placesPath = (string) ($this->option('places')
            ?: base_path('tests/fixtures/spatial/authority/places_sample.ndjson'));

        foreach (['authority' => $authorityPath, 'places' => $placesPath] as $label => $path) {
            if (!is_file($path)) {
                $this->error("{$label} NDJSON not found: {$path}");

                return self::FAILURE;
            }
        }

        $radius = $this->option('radius-m') !== null
            ? (float) $this->option('radius-m')
            : (float) config('spatial_authority.match_radius_m', 150);
        $similarityMin = $this->option('similarity-min') !== null
            ? (float) $this->option('similarity-min')
            : (float) config('spatial_authority.name_similarity_min', 0.60);

        $outDir = (string) ($this->option('out-dir') ?: storage_path('app/spatial/authority/link'));

        $authority = AuthorityRecord::readFile($authorityPath);
        $places = (new NormalizedExtractIo())->readFile($placesPath);

        $matcher = new AuthorityLinkMatcher(null, $radius, $similarityMin);
        $result = $matcher->match($authority, $places);

        $acceptance = new AuthorityLinkAcceptance($matcher);
        $verdict = $acceptance->evaluate($result, $places);

        $this->info('[Batch 2D Part C1] Authority↔corpus linking — DRY RUN (no PostGIS, nothing executed)');
        $this->line("  authority       : {$authorityPath} (" . count($authority) . ' records)');
        $this->line("  places          : {$placesPath} (" . count($places) . ' records)');
        $this->line(sprintf('  radius / sim    : %.0f m / >= %.2f', $radius, $similarityMin));
        $this->newLine();
        $this->line('  results:');
        $this->line('    linked    : ' . count($result['linked']));
        $this->line('    unlinked  : ' . count($result['unlinked']));
        $this->line('    ambiguous : ' . count($result['ambiguous']) . ' (human review — never auto-linked)');
        $this->newLine();
        $this->line('  acceptance:');
        foreach ($verdict['checks'] as $check) {
            $this->line('    ' . ($check['passed'] ? '✓' : '✗') . " {$check['name']}: {$check['detail']}");
        }

        if (!$verdict['passed']) {
            $this->newLine();
            $this->error('[Batch 2D Part C1] Acceptance FAILED: ' . implode(', ', $verdict['failures']));
            $this->line('No link artifacts written.');

            return self::FAILURE;
        }

        // Author the artifacts (local disk only).
        $materializer = new PlaceAuthorityLinkMaterializer();
        $linkRows = $materializer->materializeLinked($result['linked']);

        $ambiguousReport = array_map(static fn (array $a): array => [
            'authority_source' => $a['authority']->authority_source,
            'authority_ref'    => $a['authority']->authority_ref,
            'candidates'       => array_map(static fn ($p): array => [
                'place_source'     => $p->source,
                'place_source_ref' => $p->source_ref,
                'name'             => $p->name,
            ], $a['candidates']),
        ], $result['ambiguous']);

        $summary = [
            'authority_records' => count($authority),
            'place_records'     => count($places),
            'linked'            => count($result['linked']),
            'unlinked'          => count($result['unlinked']),
            'ambiguous'         => count($result['ambiguous']),
            'radius_m'          => $radius,
            'name_similarity_min' => $similarityMin,
        ];

        $this->writeArtifact($outDir, 'links.ndjson', $this->ndjson($linkRows));
        $this->writeArtifact($outDir, 'ambiguous_report.json', $this->json($ambiguousReport));
        $this->writeArtifact($outDir, 'summary.json', $this->json($summary));

        $this->newLine();
        $this->line('  artifacts written (DRY RUN — nothing executed against a cluster):');
        foreach (['links.ndjson', 'ambiguous_report.json', 'summary.json'] as $f) {
            $this->line('    - ' . rtrim($outDir, '/') . '/' . $f);
        }
        $this->newLine();
        $this->info('[Batch 2D Part C1] link plan authored. Live linking is a Class-2 concern.');

        return self::SUCCESS;
    }

    /** @param list<array<string,mixed>> $rows */
    private function ndjson(array $rows): string
    {
        if ($rows === []) {
            return '';
        }
        $lines = array_map(
            static fn (array $r): string => json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            $rows
        );

        return implode("\n", $lines) . "\n";
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }

    private function writeArtifact(string $dir, string $file, string $contents): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(rtrim($dir, '/') . '/' . $file, $contents);
    }
}
