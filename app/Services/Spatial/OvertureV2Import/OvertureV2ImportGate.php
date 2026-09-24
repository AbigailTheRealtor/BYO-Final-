<?php

namespace App\Services\Spatial\OvertureV2Import;

use App\Services\Spatial\ChainRegistry\ChainMatcher;
use App\Services\Spatial\ChainRegistry\ChainMatchInput;
use App\Services\Spatial\ChainRegistry\ChainMatchResult;
use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureTaxonomyMapV2;

/**
 * The manifest gate: turns one `overture-extract-v2` output directory into a validated
 * {@see OvertureV2ImportPlan}, or refuses. Pure — it reads three local files and opens no
 * connection, so every refusal happens before the database is touched.
 *
 * ALL of these must agree, or nothing is imported:
 *   * the declared contract for the corpus version (config/overture_v2_corpus.php);
 *   * the manifest's pins, counts and checksums;
 *   * the files' own SHA-256 and row counts;
 *   * the CODE the import runs with — taxonomy map version, chain-registry version and rule hash,
 *     precedence and name-normalizer versions. A verdict is only as good as the registry that
 *     produced it, so an importer running a different registry than the extraction refuses rather
 *     than trusting (or silently re-deciding) the extraction's rescue verdicts;
 *   * every row, field by field (lane contract, versions, category re-derived from
 *     `taxonomy.primary`, geometry inside the manifest's box, status, rescue lane);
 *   * the manifest's accounting: supplementary roles, rescue verdicts, base per-category tallies;
 *   * the chain-registry census, re-run here over the rows to be imported: memberships, split,
 *     per-chain counts, ambiguous and co-branded totals must equal the manifest's exactly, and an
 *     admitted rescue must re-match its own recorded chain and format.
 */
final class OvertureV2ImportGate
{
    /** The `OvertureV2Record::toArray()` wire format, in order. */
    public const WIRE_KEYS = [
        'lane', 'supplementary_role', 'rescue_lanes', 'materialization_policy', 'rescue_verdict',
        'rescued_lane', 'rescued_chain', 'rescued_as_category', 'rescued_format', 'source', 'source_ref',
        'source_release', 'extract_recipe_version', 'taxonomy_map_version', 'source_category',
        'category_key', 'legacy_category', 'basic_category', 'name', 'brand_name', 'brand_wikidata',
        'confidence', 'operating_status', 'operating_status_known', 'lon', 'lat', 'geometry_type',
        'address', 'eligibility',
    ];

    public const ADDRESS_KEYS = ['freeform', 'locality', 'postcode', 'region', 'country'];

    public const FILES = ['manifest.json', 'base.ndjson', 'supplementary.ndjson'];

    private const TEXT_FIELDS = [
        'source_category', 'category_key', 'legacy_category', 'basic_category', 'name', 'brand_name',
        'brand_wikidata', 'operating_status',
    ];

    private readonly ChainMatcher $matcher;

    public function __construct(
        private readonly ChainRegistry $registry,
        private readonly OvertureTaxonomyMapV2 $taxonomy = new OvertureTaxonomyMapV2(),
    ) {
        $this->matcher = new ChainMatcher($registry);
    }

    public function validate(OvertureV2ImportContract $contract, string $extractDir): OvertureV2ImportPlan
    {
        $bytes = $this->readFiles(rtrim($extractDir, '/'));
        $manifestSha = hash('sha256', $bytes['manifest.json']);
        $baseSha = hash('sha256', $bytes['base.ndjson']);
        $suppSha = hash('sha256', $bytes['supplementary.ndjson']);

        $manifest = json_decode($bytes['manifest.json'], true);
        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new InvalidOvertureV2Import('manifest.json is not a JSON object');
        }

        $this->checkPins($contract, $manifest, $baseSha, $suppSha);

        $recipe = $manifest['recipe'];
        $box = $this->box($recipe);
        $lanes = $this->lanes($recipe);
        $statuses = $this->statuses($recipe);
        $confidenceMin = $this->num($recipe['confidence_min'] ?? null, 'manifest recipe.confidence_min');

        $seen = [];
        $places = [];
        $baseByCategory = [];
        $baseRows = 0;
        foreach ($this->lines($bytes['base.ndjson'], 'base.ndjson') as $n => $row) {
            $where = "base.ndjson line {$n}";
            $this->checkCommon($row, $contract, $box, $statuses, $confidenceMin, $where, $seen);
            $this->checkBase($row, $where);
            $baseByCategory[$row['category_key']] = ($baseByCategory[$row['category_key']] ?? 0) + 1;
            $places[] = $row;
            $baseRows++;
        }

        $supplementaryRows = 0;
        $roles = ['diagnostic' => 0, 'rescue_candidate' => 0];
        $verdicts = ['admitted' => 0, 'refused' => 0];
        foreach ($this->lines($bytes['supplementary.ndjson'], 'supplementary.ndjson') as $n => $row) {
            $where = "supplementary.ndjson line {$n}";
            $this->checkCommon($row, $contract, $box, $statuses, $confidenceMin, $where, $seen);
            $verdict = $this->checkSupplementary($row, $lanes, $where);
            $supplementaryRows++;
            $roles[$row['supplementary_role']]++;
            if ($verdict !== 'not_candidate') {
                $verdicts[$verdict]++;
            }
            if ($verdict === 'admitted') {
                $places[] = $row;
            }
        }

        $this->checkAccounting($contract, $manifest, $baseRows, $supplementaryRows, $roles, $verdicts, $baseByCategory);
        [$memberships, $membershipCount] = $this->census($manifest, $places);

        ksort($baseByCategory, SORT_STRING);

        return new OvertureV2ImportPlan(
            $contract,
            $manifest,
            $manifestSha,
            $baseSha,
            $suppSha,
            ChainRegistry::MATCH_PRECEDENCE_VERSION,
            $this->registry->normalizerVersion(),
            $places,
            $memberships,
            $baseRows,
            $supplementaryRows,
            $roles['diagnostic'],
            $verdicts['admitted'],
            $verdicts['refused'],
            $membershipCount,
            $baseByCategory,
        );
    }

    /** @return array<string, string> */
    private function readFiles(string $dir): array
    {
        // A LOCAL directory only: a stream wrapper (ftp://, http://, phar://…) would make "read three
        // files" a network or archive read.
        if ($dir === '' || str_contains($dir, '://') || ! is_dir($dir) || realpath($dir) === false) {
            throw new InvalidOvertureV2Import('the extraction directory does not exist or is not a local directory');
        }
        $dir = realpath($dir);
        $out = [];
        foreach (self::FILES as $name) {
            $path = $dir . '/' . $name;
            if (! is_file($path)) {
                throw new InvalidOvertureV2Import("the extraction directory has no {$name}");
            }
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new InvalidOvertureV2Import("{$name} could not be read");
            }
            $out[$name] = $bytes;
        }

        return $out;
    }

    private function checkPins(OvertureV2ImportContract $c, array $m, string $baseSha, string $suppSha): void
    {
        $get = static function (array $a, string $path) {
            foreach (explode('.', $path) as $k) {
                if (! is_array($a) || ! array_key_exists($k, $a)) {
                    throw new InvalidOvertureV2Import("manifest has no {$path}");
                }
                $a = $a[$k];
            }

            return $a;
        };
        $same = static function (string $what, mixed $actual, mixed $expected): void {
            if ($actual !== $expected) {
                throw new InvalidOvertureV2Import(sprintf('%s is %s, the contract requires %s', $what, json_encode($actual), json_encode($expected)));
            }
        };

        // Manifest ↔ contract.
        $same('manifest recipe.recipe_version', $get($m, 'recipe.recipe_version'), $c->extractRecipeVersion);
        $same('manifest recipe.release', $get($m, 'recipe.release'), $c->sourceRelease);
        $same('manifest recipe.taxonomy_map_version', $get($m, 'recipe.taxonomy_map_version'), $c->taxonomyMapVersion);
        $same('manifest chain_registry.registry_version', $get($m, 'chain_registry.registry_version'), $c->registryVersion);
        $same('manifest chain_registry.rule_hash', $get($m, 'chain_registry.rule_hash'), $c->registryRuleHash);
        $same('manifest counts.base_corpus_rows', $get($m, 'counts.base_corpus_rows'), $c->baseRows);
        $same('manifest counts.supplementary_rows', $get($m, 'counts.supplementary_rows'), $c->supplementaryRows);
        $same('manifest counts.matcher_analysis_rows', $get($m, 'counts.matcher_analysis_rows'), $c->matcherAnalysisRows);
        $same('manifest counts.fully_accounted', $get($m, 'counts.fully_accounted'), true);
        $same('manifest outputs.base.ndjson rows', $get($m, 'outputs')['base.ndjson']['rows'] ?? null, $c->baseRows);
        $same('manifest outputs.supplementary.ndjson rows', $get($m, 'outputs')['supplementary.ndjson']['rows'] ?? null, $c->supplementaryRows);
        $same('manifest outputs.base.ndjson sha256', $get($m, 'outputs')['base.ndjson']['sha256'] ?? null, $c->baseSha256);
        $same('manifest outputs.supplementary.ndjson sha256', $get($m, 'outputs')['supplementary.ndjson']['sha256'] ?? null, $c->supplementarySha256);

        // Files ↔ contract.
        $same('base.ndjson sha256', $baseSha, $c->baseSha256);
        $same('supplementary.ndjson sha256', $suppSha, $c->supplementarySha256);

        // Running code ↔ contract and manifest: the rules that will re-derive categories and memberships.
        $same('the running taxonomy map version', OvertureTaxonomyMapV2::VERSION, $c->taxonomyMapVersion);
        $same('the registry\'s taxonomy map version', $this->registry->taxonomyMapVersion(), $c->taxonomyMapVersion);
        $same('the running chain-registry version', $this->registry->registryVersion(), $c->registryVersion);
        $same('the running chain-registry rule hash', $this->registry->ruleHash(), $c->registryRuleHash);
        $same('manifest chain_registry.match_precedence_version', $get($m, 'chain_registry.match_precedence_version'), ChainRegistry::MATCH_PRECEDENCE_VERSION);
        $same('manifest chain_registry.normalizer_version', $get($m, 'chain_registry.normalizer_version'), $this->registry->normalizerVersion());
        $get($m, 'matcher_census');
        $get($m, 'tallies');
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} west, south, east, north */
    private function box(array $recipe): array
    {
        $b = $recipe['bbox'] ?? null;
        if (! is_array($b)) {
            throw new InvalidOvertureV2Import('manifest has no recipe.bbox');
        }
        $w = $this->num($b['west'] ?? null, 'manifest recipe.bbox.west');
        $s = $this->num($b['south'] ?? null, 'manifest recipe.bbox.south');
        $e = $this->num($b['east'] ?? null, 'manifest recipe.bbox.east');
        $n = $this->num($b['north'] ?? null, 'manifest recipe.bbox.north');
        if (! ($w < $e && $s < $n && $w >= -180 && $e <= 180 && $s >= -90 && $n <= 90)) {
            throw new InvalidOvertureV2Import('manifest recipe.bbox is not a valid WGS84 box');
        }

        return [$w, $s, $e, $n];
    }

    /** @return array<string, array{chain: string, source_category: string, as_category: string}> */
    private function lanes(array $recipe): array
    {
        $lanes = $recipe['rescue_lanes'] ?? null;
        if (! is_array($lanes)) {
            throw new InvalidOvertureV2Import('manifest has no recipe.rescue_lanes');
        }
        foreach ($lanes as $key => $lane) {
            if (! is_string($key) || ! is_array($lane) || ! is_string($lane['chain'] ?? null)
                || ! is_string($lane['source_category'] ?? null) || ! is_string($lane['as_category'] ?? null)
            ) {
                throw new InvalidOvertureV2Import("manifest recipe.rescue_lanes.{$key} is malformed");
            }
            if (! in_array($lane['as_category'], $this->taxonomy->canonicalKeys(), true)) {
                throw new InvalidOvertureV2Import("manifest rescue lane {$key} targets unknown category {$lane['as_category']}");
            }
            if (! in_array($lane['chain'], $this->registry->chainKeys(), true)) {
                throw new InvalidOvertureV2Import("manifest rescue lane {$key} names unknown chain {$lane['chain']}");
            }
        }

        return $lanes;
    }

    /** @return array{explicit: list<string>, null: bool} */
    private function statuses(array $recipe): array
    {
        $explicit = $recipe['eligible_statuses'] ?? null;
        $null = $recipe['null_status_eligible'] ?? null;
        if (! is_array($explicit) || ! array_is_list($explicit) || ! is_bool($null)) {
            throw new InvalidOvertureV2Import('manifest recipe status rule is malformed');
        }

        return ['explicit' => $explicit, 'null' => $null];
    }

    /** @return \Generator<int, array<string, mixed>> 1-based line number => decoded row */
    private function lines(string $bytes, string $file): \Generator
    {
        if ($bytes !== '' && ! str_ends_with($bytes, "\n")) {
            throw new InvalidOvertureV2Import("{$file} does not end with a newline; it may be truncated");
        }
        $n = 0;
        foreach (explode("\n", rtrim($bytes, "\n")) as $line) {
            $n++;
            if ($bytes === '') {
                return;
            }
            $row = json_decode($line, true);
            if (! is_array($row) || array_keys($row) !== self::WIRE_KEYS) {
                throw new InvalidOvertureV2Import("{$file} line {$n} is not an overture-extract-v2 record");
            }
            yield $n => $row;
        }
    }

    /** @param array<string, true> $seen */
    private function checkCommon(array $r, OvertureV2ImportContract $c, array $box, array $statuses, float $confidenceMin, string $where, array &$seen): void
    {
        if ($r['source'] !== 'overture') {
            throw new InvalidOvertureV2Import("{$where}: source must be overture");
        }
        if (! is_string($r['source_ref']) || trim($r['source_ref']) === '' || trim($r['source_ref']) !== $r['source_ref']) {
            throw new InvalidOvertureV2Import("{$where}: missing or untrimmed source_ref");
        }
        if (isset($seen[$r['source_ref']])) {
            throw new InvalidOvertureV2Import("{$where}: duplicate source_ref {$r['source_ref']}");
        }
        $seen[$r['source_ref']] = true;
        foreach (['source_release' => $c->sourceRelease, 'extract_recipe_version' => $c->extractRecipeVersion, 'taxonomy_map_version' => $c->taxonomyMapVersion] as $k => $want) {
            if ($r[$k] !== $want) {
                throw new InvalidOvertureV2Import("{$where}: {$k} is not {$want}");
            }
        }
        foreach (self::TEXT_FIELDS as $k) {
            if ($r[$k] !== null && ! is_string($r[$k])) {
                throw new InvalidOvertureV2Import("{$where}: {$k} is not text");
            }
        }

        [$w, $s, $e, $n] = $box;
        $lon = $r['lon'];
        $lat = $r['lat'];
        if ($r['geometry_type'] !== 'POINT'
            || ! (is_int($lon) || is_float($lon)) || ! (is_int($lat) || is_float($lat))
            || ! is_finite((float) $lon) || ! is_finite((float) $lat)
            || $lon < $w || $lon > $e || $lat < $s || $lat > $n
        ) {
            throw new InvalidOvertureV2Import("{$where}: malformed geometry or point outside the manifest box");
        }

        $conf = $r['confidence'];
        if (! (is_int($conf) || is_float($conf)) || ! is_finite((float) $conf) || $conf < $confidenceMin || $conf > 1) {
            throw new InvalidOvertureV2Import("{$where}: confidence outside [{$confidenceMin}, 1]");
        }

        $status = $r['operating_status'];
        if ($status === null) {
            if (! $statuses['null'] || $r['operating_status_known'] !== false) {
                throw new InvalidOvertureV2Import("{$where}: NULL status must be eligible and recorded as unknown");
            }
        } elseif (! in_array($status, $statuses['explicit'], true) || $r['operating_status_known'] !== true) {
            throw new InvalidOvertureV2Import("{$where}: status {$status} is not an eligible known status");
        }

        if (! is_array($r['address']) || array_keys($r['address']) !== self::ADDRESS_KEYS) {
            throw new InvalidOvertureV2Import("{$where}: malformed address");
        }
        foreach ($r['address'] as $k => $v) {
            if ($v !== null && ! is_string($v)) {
                throw new InvalidOvertureV2Import("{$where}: address.{$k} is not text");
            }
        }
    }

    private function checkBase(array $r, string $where): void
    {
        if ($r['lane'] !== 'base' || $r['supplementary_role'] !== null || $r['rescue_lanes'] !== []
            || $r['materialization_policy'] !== 'corpus' || $r['eligibility'] !== 'base_category_eligible'
            || $r['rescue_verdict'] !== null || $r['rescued_lane'] !== null || $r['rescued_chain'] !== null
            || $r['rescued_as_category'] !== null || $r['rescued_format'] !== null
        ) {
            throw new InvalidOvertureV2Import("{$where}: not a base-lane record");
        }
        if (! is_string($r['category_key']) || ! in_array($r['category_key'], $this->taxonomy->canonicalKeys(), true)) {
            throw new InvalidOvertureV2Import("{$where}: unknown canonical category");
        }
        // taxonomy.primary is authoritative: the category must re-derive from the raw token.
        if ($this->taxonomy->mapSource($r['source_category']) !== $r['category_key']) {
            throw new InvalidOvertureV2Import("{$where}: category_key does not follow from taxonomy.primary");
        }
    }

    /**
     * @param array<string, array{chain: string, source_category: string, as_category: string}> $lanes
     * @return string the verdict: not_candidate | admitted | refused
     */
    private function checkSupplementary(array $r, array $lanes, string $where): string
    {
        if ($r['lane'] !== 'supplementary' || $r['category_key'] !== null || $r['eligibility'] !== 'supplementary_selector') {
            throw new InvalidOvertureV2Import("{$where}: not a supplementary-lane record");
        }
        if ($this->taxonomy->isImported($r['source_category'])) {
            throw new InvalidOvertureV2Import("{$where}: an import token in the supplementary lane");
        }
        $noRescue = $r['rescued_lane'] === null && $r['rescued_chain'] === null
            && $r['rescued_as_category'] === null && $r['rescued_format'] === null;
        $role = $r['supplementary_role'];
        $verdict = $r['rescue_verdict'];
        $policy = $r['materialization_policy'];

        if ($role === 'diagnostic' && $verdict === 'not_candidate' && $policy === 'matcher_only' && $r['rescue_lanes'] === [] && $noRescue) {
            return 'not_candidate';
        }
        if ($role !== 'rescue_candidate' || ! is_array($r['rescue_lanes']) || $r['rescue_lanes'] === [] || ! array_is_list($r['rescue_lanes'])) {
            throw new InvalidOvertureV2Import("{$where}: unknown supplementary role or lane set");
        }
        foreach ($r['rescue_lanes'] as $lane) {
            if (! is_string($lane) || ! isset($lanes[$lane]) || $lanes[$lane]['source_category'] !== strtolower(trim((string) $r['source_category']))) {
                throw new InvalidOvertureV2Import("{$where}: rescue lane does not claim this row's token");
            }
        }
        if ($verdict === 'refused' && $policy === 'matcher_only' && $noRescue) {
            return 'refused';
        }
        if ($verdict === 'admitted' && $policy === 'rescued') {
            $lane = $r['rescued_lane'];
            if (! in_array($lane, $r['rescue_lanes'], true)
                || $r['rescued_chain'] !== $lanes[$lane]['chain']
                || $r['rescued_as_category'] !== $lanes[$lane]['as_category']
                || ! is_string($r['rescued_format']) || $r['rescued_format'] === ''
            ) {
                throw new InvalidOvertureV2Import("{$where}: admitted rescue disagrees with its lane");
            }

            return 'admitted';
        }

        // Includes `pending`: an unresolved verdict is never imported.
        throw new InvalidOvertureV2Import("{$where}: unresolved or inconsistent rescue verdict");
    }

    /**
     * @param array{diagnostic: int, rescue_candidate: int} $roles
     * @param array{admitted: int, refused: int}            $verdicts
     * @param array<string, int>                            $baseByCategory
     */
    private function checkAccounting(OvertureV2ImportContract $c, array $m, int $base, int $supp, array $roles, array $verdicts, array $baseByCategory): void
    {
        if ($base !== $c->baseRows || $supp !== $c->supplementaryRows) {
            throw new InvalidOvertureV2Import("the files hold {$base} base / {$supp} supplementary rows; the contract requires {$c->baseRows} / {$c->supplementaryRows}");
        }
        $expectRoles = array_filter($roles, static fn (int $n) => $n > 0);
        if (! self::sameExactly($m['tallies']['supplementary_by_role'] ?? null, $expectRoles)) {
            throw new InvalidOvertureV2Import('supplementary roles do not reconcile with the manifest tallies');
        }
        if (! self::sameExactly($m['matcher_census']['rescue_verdicts'] ?? null, $verdicts)) {
            throw new InvalidOvertureV2Import('rescue verdicts do not reconcile with the manifest census');
        }
        if (! self::sameExactly($m['tallies']['base_by_category'] ?? null, $baseByCategory)) {
            throw new InvalidOvertureV2Import('base per-category counts do not reconcile with the manifest tallies');
        }
    }

    /**
     * Re-runs the chain matcher over the rows being imported and requires the manifest's census
     * back, exactly. Rows not imported (diagnostic, refused) produced no membership in the census
     * (it aborts otherwise), so the imported rows alone must account for every membership.
     *
     * @param list<array<string, mixed>> $places
     * @return array{0: array<string, list<array<string, mixed>>>, 1: int}
     */
    private function census(array $manifest, array $places): array
    {
        $mc = $manifest['matcher_census'];
        if (! is_array($mc)) {
            throw new InvalidOvertureV2Import('manifest matcher_census is not an object');
        }
        $out = [];
        $total = 0;
        $fromBase = 0;
        $rescued = 0;
        $ambiguous = 0;
        $coBranded = 0;
        $byChain = [];
        foreach ($places as $r) {
            $match = $this->matcher->match(new ChainMatchInput(
                $r['name'], $r['brand_name'], $r['brand_wikidata'], $r['category_key'], $r['operating_status'], $r['source_category'],
            ));
            if ($match->outcome === ChainMatchResult::AMBIGUOUS) {
                $ambiguous++;
            }
            $rows = [];
            foreach ($match->memberships as $mem) {
                $isRescue = $r['lane'] === 'supplementary';
                if (! $isRescue && $mem->rescuedFromSourceCategory !== null) {
                    throw new InvalidOvertureV2Import("base row {$r['source_ref']} re-matched as a rescue");
                }
                if ($isRescue && ($mem->brandKey !== $r['rescued_chain'] || $mem->formatKey !== $r['rescued_format']
                    || $mem->rescuedFromSourceCategory === null || count($match->memberships) !== 1)
                ) {
                    throw new InvalidOvertureV2Import("admitted rescue {$r['source_ref']} does not re-match its recorded chain and format");
                }
                $rows[] = [
                    'brand_key' => $mem->brandKey,
                    'role' => $mem->role,
                    'format_key' => $mem->formatKey,
                    'match_method' => $mem->matchMethod,
                    'storefront_status' => $mem->storefrontStatus(),
                    'co_brand_with' => $mem->coBrandWith,
                    'rescued_from_source_category' => $mem->rescuedFromSourceCategory,
                ];
                $total++;
                $isRescue ? $rescued++ : $fromBase++;
                $byChain[$mem->brandKey] = ($byChain[$mem->brandKey] ?? 0) + 1;
                if ($mem->coBrandWith !== []) {
                    $coBranded++;
                }
            }
            if ($r['lane'] === 'supplementary' && $rows === []) {
                throw new InvalidOvertureV2Import("admitted rescue {$r['source_ref']} no longer matches any chain");
            }
            if ($rows !== []) {
                $out[$r['source_ref']] = $rows;
            }
        }
        ksort($byChain, SORT_STRING);

        $expect = [
            'memberships' => $total,
            'memberships_from_base' => $fromBase,
            'memberships_rescued_from_supplementary' => $rescued,
            'ambiguous_rows' => $ambiguous,
            'co_branded_memberships' => $coBranded,
            'memberships_by_chain' => $byChain,
        ];
        foreach ($expect as $k => $v) {
            // Strict: a manifest that OMITS a count, or states it as a string, does not reconcile.
            if (! array_key_exists($k, $mc) || ! self::sameExactly($mc[$k], $v)) {
                throw new InvalidOvertureV2Import("the chain census does not reconcile: {$k} is " . json_encode($v) . ', the manifest says ' . json_encode($mc[$k] ?? null));
            }
        }

        return [$out, $total];
    }

    /**
     * Identical values; maps compared independently of key order. Never PHP's loose `==`, under
     * which a missing count (null) equals 0 and "7" equals 7.
     */
    private static function sameExactly(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual) && is_array($expected)) {
            ksort($actual, SORT_STRING);
            ksort($expected, SORT_STRING);
        }

        return $actual === $expected;
    }

    private function num(mixed $v, string $what): float
    {
        if (! (is_int($v) || is_float($v)) || ! is_finite((float) $v)) {
            throw new InvalidOvertureV2Import("{$what} is not a number");
        }

        return (float) $v;
    }
}
