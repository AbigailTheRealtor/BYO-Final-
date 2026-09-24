<?php

namespace App\Services\Spatial\OvertureExtractV2;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureTaxonomyMapV2;

/**
 * The validated `overture-extract-v2` recipe: config/overture_extract_v2.php, read and checked
 * once. Pure — no database, no network; the container is used only when one is bound.
 *
 * VALIDATION IS FAIL-CLOSED. An unknown or missing key, a malformed release or version, an
 * inverted bounding box, a floor outside (0, 1], a status listed as both eligible and excluded, a
 * selector pattern that does not compile or matches the empty string, or a rescue lane the chain
 * registry does not declare in exactly the same terms — each is an {@see InvalidOvertureExtractV2}.
 *
 * RESCUE LANES MIRROR THE REGISTRY, BOTH WAYS. Every lane must name a chain whose
 * `source_category_rescues` declares that exact token with that exact target category, and every
 * registry rescue must have a lane. The extractor can therefore never label a row a rescue
 * candidate the matcher would not consider, nor silently omit one the registry expects. A lane's
 * token may never be one of the 16 import tokens: a rescue widens one chain, never the corpus.
 */
final class OvertureExtractV2Config
{
    public const CONFIG_KEY = 'overture_extract_v2';

    public const IDENTITY_STRONG = 'strong';
    public const MATERIALIZATION_ELIGIBLE_IF_MATCHED = 'eligible_if_matched';

    private const TOP_KEYS = ['recipe_version', 'release', 'bbox', 'confidence_min', 'operating_status', 'supplementary'];
    private const BBOX_KEYS = ['west', 'south', 'east', 'north'];
    private const STATUS_KEYS = ['eligible_explicit', 'eligible_unknown_is_null', 'excluded'];
    private const SUPPLEMENTARY_KEYS = ['diagnostic_selector', 'rescue_lanes'];
    private const SELECTOR_KEYS = ['name_brand_patterns', 'any_brand_wikidata', 'evidence'];
    private const LANE_KEYS = ['chain', 'source_category', 'as_category', 'identity', 'materialization', 'reason'];

    public readonly string $recipeVersion;
    public readonly string $release;
    public readonly float $west;
    public readonly float $south;
    public readonly float $east;
    public readonly float $north;
    public readonly float $confidenceMin;
    /** @var list<string> */
    public readonly array $eligibleStatuses;
    public readonly bool $nullStatusEligible;
    /** @var list<string> */
    public readonly array $excludedStatuses;
    /** @var list<string> compiled `/…/u` patterns, in config order */
    public readonly array $selectorPatterns;
    /** @var list<string> the patterns as configured, for the manifest pins */
    public readonly array $selectorPatternSources;
    public readonly bool $selectorAnyBrandWikidata;
    /** @var array<string, array{chain: string, source_category: string, as_category: string, reason: string}> lane key => lane, key-sorted */
    public readonly array $rescueLanes;

    private function __construct(array $c, ChainRegistry $registry)
    {
        self::exactKeys($c, self::TOP_KEYS, 'config');

        $this->recipeVersion = self::string($c['recipe_version'], 'recipe_version');
        if (preg_match('/^overture-extract-v[1-9]\d*$/', $this->recipeVersion) !== 1) {
            self::fail("recipe_version must look like overture-extract-vN, got {$this->recipeVersion}");
        }
        $this->release = self::string($c['release'], 'release');
        if (preg_match('/^\d{4}-\d{2}-\d{2}\.\d+$/', $this->release) !== 1) {
            self::fail("release must look like YYYY-MM-DD.N, got {$this->release}");
        }

        self::exactKeys($c['bbox'], self::BBOX_KEYS, 'bbox');
        $this->west = self::number($c['bbox']['west'], 'bbox.west');
        $this->south = self::number($c['bbox']['south'], 'bbox.south');
        $this->east = self::number($c['bbox']['east'], 'bbox.east');
        $this->north = self::number($c['bbox']['north'], 'bbox.north');
        if (! ($this->west < $this->east && $this->south < $this->north)
            || $this->west < -180 || $this->east > 180 || $this->south < -90 || $this->north > 90
        ) {
            self::fail('bbox must be a non-empty [west, south, east, north] box within WGS84 bounds');
        }

        $this->confidenceMin = self::number($c['confidence_min'], 'confidence_min');
        if ($this->confidenceMin <= 0 || $this->confidenceMin > 1) {
            self::fail('confidence_min must be in (0, 1]');
        }

        self::exactKeys($c['operating_status'], self::STATUS_KEYS, 'operating_status');
        $this->eligibleStatuses = self::tokens($c['operating_status']['eligible_explicit'], 'operating_status.eligible_explicit');
        $this->excludedStatuses = self::tokens($c['operating_status']['excluded'], 'operating_status.excluded');
        if ($this->eligibleStatuses === []) {
            self::fail('operating_status.eligible_explicit must name at least one status');
        }
        if (array_intersect($this->eligibleStatuses, $this->excludedStatuses) !== []) {
            self::fail('a status cannot be both eligible and excluded');
        }
        if (! is_bool($c['operating_status']['eligible_unknown_is_null'])) {
            self::fail('operating_status.eligible_unknown_is_null must be a boolean');
        }
        $this->nullStatusEligible = $c['operating_status']['eligible_unknown_is_null'];

        self::exactKeys($c['supplementary'], self::SUPPLEMENTARY_KEYS, 'supplementary');
        $sel = $c['supplementary']['diagnostic_selector'];
        self::exactKeys($sel, self::SELECTOR_KEYS, 'supplementary.diagnostic_selector');
        self::string($sel['evidence'], 'supplementary.diagnostic_selector.evidence');
        if (! is_bool($sel['any_brand_wikidata'])) {
            self::fail('supplementary.diagnostic_selector.any_brand_wikidata must be a boolean');
        }
        $this->selectorAnyBrandWikidata = $sel['any_brand_wikidata'];
        if (! is_array($sel['name_brand_patterns']) || ! array_is_list($sel['name_brand_patterns']) || $sel['name_brand_patterns'] === []) {
            self::fail('supplementary.diagnostic_selector.name_brand_patterns must be a non-empty list');
        }
        $patterns = [];
        $sources = [];
        foreach ($sel['name_brand_patterns'] as $i => $p) {
            $p = self::string($p, "supplementary.diagnostic_selector.name_brand_patterns.{$i}");
            if (str_contains($p, '/')) {
                self::fail("selector pattern {$p} may not contain '/'");
            }
            $compiled = '/' . $p . '/u';
            if (@preg_match($compiled, '') === false) {
                self::fail("selector pattern {$p} does not compile");
            }
            if (preg_match($compiled, '') === 1 || preg_match($compiled, ' ') === 1) {
                self::fail("selector pattern {$p} matches an empty name");
            }
            if (in_array($compiled, $patterns, true)) {
                self::fail("duplicate selector pattern {$p}");
            }
            $patterns[] = $compiled;
            $sources[] = $p;
        }
        $this->selectorPatterns = $patterns;
        $this->selectorPatternSources = $sources;

        $taxonomy = new OvertureTaxonomyMapV2();
        $lanes = $c['supplementary']['rescue_lanes'];
        if (! is_array($lanes) || ($lanes !== [] && array_is_list($lanes))) {
            self::fail('supplementary.rescue_lanes must be a map of lane key => lane');
        }
        $parsed = [];
        $claimed = [];
        foreach ($lanes as $key => $lane) {
            $p = "supplementary.rescue_lanes.{$key}";
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
                self::fail("{$p}: lane key must match ^[a-z][a-z0-9_]*$");
            }
            self::exactKeys($lane, self::LANE_KEYS, $p);
            $chainKey = self::string($lane['chain'], "{$p}.chain");
            $token = self::string($lane['source_category'], "{$p}.source_category");
            $as = self::string($lane['as_category'], "{$p}.as_category");
            self::string($lane['reason'], "{$p}.reason");
            if ($lane['identity'] !== self::IDENTITY_STRONG) {
                self::fail("{$p}.identity must be 'strong'");
            }
            if ($lane['materialization'] !== self::MATERIALIZATION_ELIGIBLE_IF_MATCHED) {
                self::fail("{$p}.materialization must be 'eligible_if_matched'");
            }
            if ($taxonomy->isImported($token) || in_array($token, $taxonomy->canonicalKeys(), true)) {
                self::fail("{$p}: {$token} is already imported; a rescue lane never re-admits a corpus token");
            }
            if (! in_array($chainKey, $registry->chainKeys(), true)) {
                self::fail("{$p}: unknown chain {$chainKey}");
            }
            $rescue = $registry->chain($chainKey)->rescueFor($token);
            if ($rescue === null) {
                self::fail("{$p}: chain {$chainKey} declares no source-category rescue for {$token}");
            }
            if ($rescue['as_category'] !== $as) {
                self::fail("{$p}: as_category {$as} disagrees with the registry ({$rescue['as_category']})");
            }
            if (isset($claimed["{$chainKey}\0{$token}"])) {
                self::fail("{$p}: {$chainKey} / {$token} is already claimed by another lane");
            }
            $claimed["{$chainKey}\0{$token}"] = true;
            $parsed[$key] = ['chain' => $chainKey, 'source_category' => $token, 'as_category' => $as, 'reason' => $lane['reason']];
        }
        foreach ($registry->rescueSources() as $token => $chains) {
            foreach ($chains as $chainKey) {
                if (! isset($claimed["{$chainKey}\0{$token}"])) {
                    self::fail("the registry declares a {$chainKey} rescue for {$token} but no rescue lane claims it");
                }
            }
        }
        ksort($parsed, SORT_STRING);
        $this->rescueLanes = $parsed;
    }

    /** The recipe as configured: container config when bound, else the file. */
    public static function load(?ChainRegistry $registry = null): self
    {
        $registry ??= ChainRegistry::load();
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config(self::CONFIG_KEY);
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return self::fromArray($fromContainer, $registry);
                    }
                }
            } catch (InvalidOvertureExtractV2 $e) {
                throw $e;
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }
        $path = __DIR__ . '/../../../../config/overture_extract_v2.php';
        $loaded = is_file($path) ? require $path : null;
        if (! is_array($loaded)) {
            throw new InvalidOvertureExtractV2('config/overture_extract_v2.php is missing or did not return an array');
        }

        return self::fromArray($loaded, $registry);
    }

    public static function fromArray(array $config, ChainRegistry $registry): self
    {
        return new self($config, $registry);
    }

    /** @return list<string> lane keys whose token is this raw source token (exact, lower-cased). */
    public function rescueLanesForToken(?string $sourceToken): array
    {
        if ($sourceToken === null) {
            return [];
        }
        $token = strtolower(trim($sourceToken));
        $out = [];
        foreach ($this->rescueLanes as $key => $lane) {
            if ($lane['source_category'] === $token) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** Recipe pins for the output manifest; registry pins are added by the caller that ran it. */
    public function pins(): array
    {
        return [
            'recipe_version' => $this->recipeVersion,
            'release' => $this->release,
            'bbox' => ['west' => $this->west, 'south' => $this->south, 'east' => $this->east, 'north' => $this->north],
            'bbox_semantics' => 'row bbox contained in box (SQL); point re-checked inside box (normalizer)',
            'confidence_min' => $this->confidenceMin,
            'confidence_rule' => '>=',
            'eligible_statuses' => $this->eligibleStatuses,
            'null_status_eligible' => $this->nullStatusEligible,
            'excluded_statuses' => $this->excludedStatuses,
            'taxonomy_source_field' => OvertureTaxonomyMapV2::SOURCE_FIELD,
            'taxonomy_map_version' => OvertureTaxonomyMapV2::VERSION,
            'base_source_tokens' => (new OvertureTaxonomyMapV2())->sourceTokens(),
            // The selector decides the supplementary lane, so it is pinned: editing a pattern
            // without bumping recipe_version still shows up as a manifest difference.
            'diagnostic_selector' => [
                'subject' => "lower(name + ' ' + brand), unanchored",
                'name_brand_patterns' => $this->selectorPatternSources,
                'any_brand_wikidata' => $this->selectorAnyBrandWikidata,
            ],
            'rescue_lanes' => array_map(static fn (array $l): array => ['chain' => $l['chain'], 'source_category' => $l['source_category'], 'as_category' => $l['as_category']], $this->rescueLanes),
        ];
    }

    private static function exactKeys(mixed $value, array $keys, string $path): void
    {
        if (! is_array($value) || array_is_list($value) && $value !== []) {
            self::fail("{$path} must be a map");
        }
        $have = array_keys($value);
        foreach (array_diff($have, $keys) as $extra) {
            self::fail("{$path}: unknown key '{$extra}'");
        }
        foreach (array_diff($keys, $have) as $missing) {
            self::fail("{$path}: missing key '{$missing}'");
        }
    }

    private static function string(mixed $v, string $path): string
    {
        if (! is_string($v) || trim($v) === '') {
            self::fail("{$path} must be a non-empty string");
        }

        return $v;
    }

    private static function number(mixed $v, string $path): float
    {
        if (! is_int($v) && ! is_float($v)) {
            self::fail("{$path} must be a number");
        }

        return (float) $v;
    }

    /** @return list<string> */
    private static function tokens(mixed $v, string $path): array
    {
        if (! is_array($v) || ($v !== [] && ! array_is_list($v))) {
            self::fail("{$path} must be a list");
        }
        $out = [];
        foreach ($v as $i => $t) {
            $t = self::string($t, "{$path}.{$i}");
            if (preg_match('/^[a-z][a-z_]*$/', $t) !== 1 || in_array($t, $out, true)) {
                self::fail("{$path}.{$i}: {$t} must be a unique lower-case status token");
            }
            $out[] = $t;
        }

        return $out;
    }

    private static function fail(string $message): never
    {
        throw new InvalidOvertureExtractV2($message);
    }
}
