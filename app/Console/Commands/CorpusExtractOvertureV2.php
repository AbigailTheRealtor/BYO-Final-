<?php

namespace App\Console\Commands;

use App\Services\Spatial\ChainRegistry\ChainMatcher;
use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureExtractV2\InvalidOvertureExtractV2;
use App\Services\Spatial\OvertureExtractV2\OvertureExtractV2Config;
use App\Services\Spatial\OvertureExtractV2\OvertureV2ExtractIo;
use App\Services\Spatial\OvertureExtractV2\OvertureV2MatcherCensus;
use App\Services\Spatial\OvertureExtractV2\OvertureV2Normalizer;
use App\Services\Spatial\OvertureExtractV2\OvertureV2Record;
use Illuminate\Console\Command;

/**
 * OFFLINE `overture-extract-v2`: normalizes the raw bounding-box NDJSON produced by
 * scripts/overture-v2/extract_bbox_raw.sql into the two lanes and a complete accounting manifest.
 *
 *   * Refuses to run in production, with no override.
 *   * Touches no database and no network. The only I/O is reading --input and writing three files
 *     into --output-dir: base.ndjson (the corpus), supplementary.ndjson (matcher analysis only,
 *     every rescue candidate resolved by the chain matcher) and manifest.json (pins, accounting,
 *     checksums, and the offline matcher census). The census is not optional: without it the
 *     supplementary lane would carry unresolved rescue candidates.
 *   * A line that is not a JSON object aborts the run: a partial corpus is never written. Each file
 *     is written to a temporary name and renamed into place only after every write succeeded, the
 *     manifest last — so a manifest on disk means both lanes beside it are complete.
 *
 * Recipe and accounting: docs/spatial/overture-v2-extraction-recipe.md.
 */
class CorpusExtractOvertureV2 extends Command
{
    protected $signature = 'corpus:extract-overture-v2
        {--input= : Raw bounding-box NDJSON from scripts/overture-v2/extract_bbox_raw.sql}
        {--output-dir= : Directory for base.ndjson, supplementary.ndjson and manifest.json}
        {--release-row-count= : Rows in the whole release (scripts/overture-v2/count_release_rows.sql), for the outside-bbox tally}';

    protected $description = 'OFFLINE: normalize a raw Overture v2 bounding-box extract into the base and supplementary lanes with full accounting (no DB, no network, refuses production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('corpus:extract-overture-v2 is an OFFLINE authoring tool and REFUSES to run in production.');

            return self::FAILURE;
        }

        $input = (string) $this->option('input');
        $outDir = rtrim((string) $this->option('output-dir'), '/');
        if ($input === '' || ! is_file($input)) {
            $this->error('--input must name an existing raw NDJSON file.');

            return self::FAILURE;
        }
        if ($outDir === '' || ! is_dir($outDir)) {
            $this->error('--output-dir must name an existing directory.');

            return self::FAILURE;
        }
        $releaseRows = $this->option('release-row-count');
        if ($releaseRows !== null && (! ctype_digit((string) $releaseRows))) {
            $this->error('--release-row-count must be a non-negative integer.');

            return self::FAILURE;
        }

        // Exact duplicate-id detection keeps every source id in memory (~1.25M for Florida).
        ini_set('memory_limit', '2G');

        try {
            $registry = ChainRegistry::load();
            $config = OvertureExtractV2Config::load($registry);
            $result = (new OvertureV2Normalizer($config))->run($this->rows($input));
        } catch (InvalidOvertureExtractV2 $e) {
            $this->error('ABORTED, nothing written: ' . $e->getMessage());

            return self::FAILURE;
        }

        try {
            $census = (new OvertureV2MatcherCensus(new ChainMatcher($registry), $config))->run($result);
        } catch (InvalidOvertureExtractV2 $e) {
            $this->error('ABORTED, nothing written: ' . $e->getMessage());

            return self::FAILURE;
        }
        foreach ($census['supplementary'] as $rec) {
            if ($rec->rescueVerdict === OvertureV2Record::RESCUE_PENDING) {
                $this->error("ABORTED, nothing written: rescue candidate {$rec->sourceRef} was left unresolved");

                return self::FAILURE;
            }
        }

        $base = OvertureV2ExtractIo::toNdjson($result->base());
        $supplementary = OvertureV2ExtractIo::toNdjson($census['supplementary']);

        $manifest = $result->accounting();
        $manifest['source'] = [
            'release_rows' => $releaseRows === null ? null : (int) $releaseRows,
            'outside_bbox_sql' => $releaseRows === null ? null : (int) $releaseRows - $result->inputRows(),
            'input_file_sha256' => hash_file('sha256', $input),
        ];
        $manifest['outputs'] = [
            'base.ndjson' => ['rows' => $result->baseCount(), 'sha256' => OvertureV2ExtractIo::checksum($base)],
            'supplementary.ndjson' => ['rows' => count($census['supplementary']), 'sha256' => OvertureV2ExtractIo::checksum($supplementary)],
        ];
        $manifest['chain_registry'] = [
            'registry_version' => $registry->registryVersion(),
            'rule_hash' => $registry->ruleHash(),
            'match_precedence_version' => ChainRegistry::MATCH_PRECEDENCE_VERSION,
            'normalizer_version' => $registry->normalizerVersion(),
        ];
        $manifest['matcher_census'] = $census['summary'];

        $failure = $this->writeAll($outDir, [
            'base.ndjson' => $base,
            'supplementary.ndjson' => $supplementary,
            'manifest.json' => OvertureV2ExtractIo::manifestJson($manifest),
        ]);
        if ($failure !== null) {
            $this->error('ABORTED: ' . $failure);

            return self::FAILURE;
        }

        $this->info("overture-extract-v2 ({$config->recipeVersion}, release {$config->release})");
        $this->line(sprintf('  bbox input rows        : %d', $result->inputRows()));
        $this->line(sprintf('  BASE corpus rows       : %d', $result->baseCount()));
        $this->line(sprintf('  supplementary rows     : %d', $result->supplementaryCount()));
        $this->line(sprintf('  matcher-analysis rows  : %d  (base + supplementary; not a corpus count)', $result->matcherAnalysisCount()));
        $this->line(sprintf('  rejected rows          : %d', $result->rejectedCount()));
        foreach ($result->rejections() as $code => $n) {
            $this->line(sprintf('    %-44s %d', $code, $n));
        }
        $this->line('  fully accounted        : ' . ($result->isFullyAccounted() ? 'yes' : 'NO'));
        $mc = $manifest['matcher_census'];
        $this->line(sprintf('  matcher memberships    : %d (ambiguous rows %d, co-branded %d)', $mc['memberships'], $mc['ambiguous_rows'], $mc['co_branded_memberships']));
        $this->line(sprintf('  rescue verdicts        : %d admitted, %d refused', $mc['rescue_verdicts'][OvertureV2Record::RESCUE_ADMITTED], $mc['rescue_verdicts'][OvertureV2Record::RESCUE_REFUSED]));

        return $result->isFullyAccounted() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Writes every file under a temporary name, then renames them into place in the given order.
     * Nothing is renamed unless every write succeeded; on any failure the temporaries are removed.
     *
     * @param array<string, string> $files name => bytes, manifest last
     */
    private function writeAll(string $outDir, array $files): ?string
    {
        $tmp = [];
        $cleanup = static function () use (&$tmp): void {
            foreach ($tmp as $t) {
                if (is_file($t)) {
                    @unlink($t);
                }
            }
        };
        foreach ($files as $name => $bytes) {
            $t = $outDir . '/.' . $name . '.tmp-' . getmypid();
            $tmp[$name] = $t;
            if (@file_put_contents($t, $bytes) !== strlen($bytes)) {
                $cleanup();

                return "could not write {$name} in {$outDir}; nothing written";
            }
        }
        // A manifest left by an earlier run must not survive beside lanes this run replaced: it goes
        // first, so a manifest on disk is only ever the one renamed in last.
        $manifest = $outDir . '/' . array_key_last($files);
        if (is_file($manifest) && ! @unlink($manifest)) {
            $cleanup();

            return "could not remove the previous manifest in {$outDir}; nothing written";
        }
        foreach ($files as $name => $bytes) {
            if (! @rename($tmp[$name], $outDir . '/' . $name)) {
                $cleanup();

                return "could not move {$name} into place in {$outDir}; the output directory is incomplete and must not be used";
            }
        }

        return null;
    }

    /** @return \Generator<int, mixed> */
    private function rows(string $path): \Generator
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new InvalidOvertureExtractV2("cannot open {$path}");
        }
        try {
            $n = 0;
            while (($line = fgets($fh)) !== false) {
                $n++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (! is_array($row)) {
                    throw new InvalidOvertureExtractV2("line {$n} is not a JSON object");
                }
                yield $row;
            }
        } finally {
            fclose($fh);
        }
    }
}
