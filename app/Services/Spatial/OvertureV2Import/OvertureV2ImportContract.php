<?php

namespace App\Services\Spatial\OvertureV2Import;

/**
 * One declared v2 import contract (config/overture_v2_corpus.php) — the only reader of that file.
 * Every field must be present, typed and well-formed; an unknown key, a missing key or a bad value
 * refuses the whole file, so a typo can never loosen what the importer checks.
 */
final class OvertureV2ImportContract
{
    public const CONFIG_KEY = 'overture_v2_corpus';

    private const TOP_KEYS = ['import_contracts'];

    private const KEYS = [
        'source_release', 'extract_recipe_version', 'taxonomy_map_version', 'registry_version',
        'registry_rule_hash', 'base_rows', 'supplementary_rows', 'matcher_analysis_rows',
        'base_sha256', 'supplementary_sha256',
    ];

    private function __construct(
        public readonly string $corpusVersion,
        public readonly string $sourceRelease,
        public readonly string $extractRecipeVersion,
        public readonly string $taxonomyMapVersion,
        public readonly string $registryVersion,
        public readonly string $registryRuleHash,
        public readonly int $baseRows,
        public readonly int $supplementaryRows,
        public readonly int $matcherAnalysisRows,
        public readonly string $baseSha256,
        public readonly string $supplementarySha256,
    ) {
    }

    /** The contract for one corpus version, from the container config when bound, else the file. */
    public static function forVersion(string $corpusVersion): self
    {
        return self::fromConfig(self::loadConfig(), $corpusVersion);
    }

    /** @param array<string, mixed> $config the whole config/overture_v2_corpus.php array */
    public static function fromConfig(array $config, string $corpusVersion): self
    {
        $contracts = self::validated($config);
        if (! isset($contracts[$corpusVersion])) {
            throw new InvalidOvertureV2Import("no import contract is declared for corpus version [{$corpusVersion}]");
        }
        $c = $contracts[$corpusVersion];

        return new self(
            $corpusVersion,
            $c['source_release'],
            $c['extract_recipe_version'],
            $c['taxonomy_map_version'],
            $c['registry_version'],
            $c['registry_rule_hash'],
            $c['base_rows'],
            $c['supplementary_rows'],
            $c['matcher_analysis_rows'],
            $c['base_sha256'],
            $c['supplementary_sha256'],
        );
    }

    /** @return list<string> every declared corpus version */
    public static function declaredVersions(): array
    {
        return array_keys(self::validated(self::loadConfig()));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    private static function validated(array $config): array
    {
        self::exactKeys($config, self::TOP_KEYS, 'config');
        $contracts = $config['import_contracts'];
        if (! is_array($contracts) || $contracts === [] || array_is_list($contracts)) {
            throw new InvalidOvertureV2Import('import_contracts must be a non-empty map of corpus version => contract');
        }
        foreach ($contracts as $version => $c) {
            $p = "import_contracts.{$version}";
            if (! is_string($version) || preg_match('/^overture-\d{4}-\d{2}-\d{2}\.\d+-[a-z]+-r[1-9]\d*$/', $version) !== 1) {
                throw new InvalidOvertureV2Import("{$p}: corpus version must look like overture-YYYY-MM-DD.N-<region>-rN");
            }
            self::exactKeys($c, self::KEYS, $p);
            foreach (['source_release', 'extract_recipe_version', 'taxonomy_map_version', 'registry_version'] as $k) {
                if (! is_string($c[$k]) || trim($c[$k]) === '') {
                    throw new InvalidOvertureV2Import("{$p}.{$k} must be a non-empty string");
                }
            }
            if (! str_contains($version, $c['source_release'])) {
                throw new InvalidOvertureV2Import("{$p}: the corpus version must name its source release {$c['source_release']}");
            }
            foreach (['registry_rule_hash', 'base_sha256', 'supplementary_sha256'] as $k) {
                if (! is_string($c[$k]) || preg_match('/^[0-9a-f]{64}$/', $c[$k]) !== 1) {
                    throw new InvalidOvertureV2Import("{$p}.{$k} must be a lower-case sha256 hex digest");
                }
            }
            foreach (['base_rows', 'supplementary_rows', 'matcher_analysis_rows'] as $k) {
                if (! is_int($c[$k]) || $c[$k] < 0) {
                    throw new InvalidOvertureV2Import("{$p}.{$k} must be a non-negative integer");
                }
            }
            if ($c['base_rows'] === 0) {
                throw new InvalidOvertureV2Import("{$p}.base_rows must be positive: an empty corpus is not a corpus");
            }
            if ($c['matcher_analysis_rows'] !== $c['base_rows'] + $c['supplementary_rows']) {
                throw new InvalidOvertureV2Import("{$p}: matcher_analysis_rows must equal base_rows + supplementary_rows");
            }
        }

        return $contracts;
    }

    /** @return array<string, mixed> */
    private static function loadConfig(): array
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config(self::CONFIG_KEY);
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return $fromContainer;
                    }
                }
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }
        $path = __DIR__ . '/../../../../config/overture_v2_corpus.php';
        $loaded = is_file($path) ? require $path : null;
        if (! is_array($loaded)) {
            throw new InvalidOvertureV2Import('config/overture_v2_corpus.php is missing or did not return an array');
        }

        return $loaded;
    }

    /** @param list<string> $keys */
    private static function exactKeys(mixed $value, array $keys, string $path): void
    {
        if (! is_array($value)) {
            throw new InvalidOvertureV2Import("{$path} must be a map");
        }
        $have = array_keys($value);
        $missing = array_diff($keys, $have);
        $extra = array_diff($have, $keys);
        if ($missing !== [] || $extra !== []) {
            throw new InvalidOvertureV2Import(sprintf('%s keys differ (missing: [%s], unknown: [%s])', $path, implode(', ', $missing), implode(', ', $extra)));
        }
    }
}
