<?php

namespace App\Services\Spatial\ChainRegistry;

use App\Services\Spatial\OvertureTaxonomyMapV2;

/**
 * The ONLY reader of config/poi_chain_registry.php, and the validator that refuses it when any
 * load-time rule is broken. Design: docs/spatial/overture-chain-registry-design.md §1, §2, §4.2.
 *
 * Pure: no database, no network, no cache. It works without a booted container — the offline
 * PR 3 extraction tooling runs outside the application — using the container's config when one
 * is bound and the file otherwise (the `LandlordScreeningPolicy::conf()` pattern).
 *
 * NOTHING CONSUMES THIS YET. No Location DNA, Ask AI, Buyer/Tenant or brand-query code references
 * the chain registry; a structural test asserts it.
 *
 * VALIDATION IS FAIL-CLOSED. An unknown key, a missing required field, an alias that is not
 * already normalised, an alias shared by two chains, a prefix alias that would swallow another
 * chain's alias, a QID claimed by two roles, a category outside the 16 canonical v2 keys, a
 * regex that matches the empty string, a format that could turn a department into a storefront
 * by category alone, an asymmetric co-brand, an entry without evidence, or any
 * `validation_status` other than `provisional` — each is an {@see InvalidChainRegistry}.
 *
 * RULE HASH. {@see ruleHash()} is a SHA-256 over every identity-affecting rule (presentation —
 * display names, labels, visibility, notes, evidence text — is excluded). Every chain's
 * {@see chainRuleHash()} is derived from the WHOLE registry, deliberately: foreign-brand (R5)
 * and ambiguity (R8) decisions depend on other chains' rules, so no edit anywhere can be proven
 * not to move this chain's matches. A validation record is valid only while its hash is current.
 */
final class ChainRegistry
{
    public const CONFIG_KEY = 'poi_chain_registry';

    /** Versions the matcher's precedence (design §12). A precedence change is a rule change. */
    public const MATCH_PRECEDENCE_VERSION = 'chain-match-precedence-v1';

    /** The only status the config may declare. Promotion lives outside the registry (§14). */
    public const STATUS_PROVISIONAL = 'provisional';

    public const MATCH_EXACT = 'exact';
    public const MATCH_PREFIX = 'prefix';

    public const CO_BRAND_EVIDENCE_RULE = 'co_brand_evidence';

    private const TOP_KEYS = ['registry_version', 'normalizer_version', 'taxonomy_map_version', 'global', 'chains'];
    private const GLOBAL_KEYS = ['excluded_categories', 'exclusion_wikidata_ids', 'exclusion_name_patterns', 'closed_name_patterns', 'fuel_brands'];
    private const CHAIN_REQUIRED = ['display_name', 'aliases', 'brand_aliases', 'own_wikidata_ids', 'allowed_categories', 'formats', 'default_format', 'validation_status'];
    private const CHAIN_OPTIONAL = ['department_wikidata_ids', 'exclusion_wikidata_ids', 'exclusion_name_patterns', 'fuel_brands', 'excluded_categories', 'fuel_sites', 'co_brands', 'notes'];
    private const FORMAT_KEYS = ['categories', 'name_patterns', 'role', 'user_visible', 'label', 'evidence'];

    /**
     * Neutral strings no rule may match. A pattern that matches one of these matches almost
     * everything: as an exclusion it rejects every row, as a format pattern it upgrades every row.
     */
    private const NEUTRAL_PROBES = ['', 'a', 'z', '1', 'store', 'zzz 1', 'the store 12', 'abcdefghijklmnop', 'grocery'];

    private static ?self $fileInstance = null;

    private string $registryVersion;
    private string $normalizerVersion;
    private string $taxonomyMapVersion;

    /** @var list<string> */
    private array $globalExcludedCategories = [];
    /** @var array<string, string> QID => label */
    private array $exclusionWikidataIds = [];
    /** @var array<string, string> reason => regex */
    private array $exclusionNamePatterns = [];
    /** @var array<string, string> reason => regex */
    private array $closedNamePatterns = [];
    /** @var array<string, array{label: string, wikidata_ids: list<string>, names: list<string>}> */
    private array $fuelBrands = [];

    /** @var array<string, ChainDefinition> key-sorted */
    private array $chains = [];

    /** @var array<string, true> the 16 canonical v2 category keys */
    private array $canonicalCategories = [];

    private ?string $ruleHash = null;

    private function __construct()
    {
    }

    /**
     * The registry as configured: container config when bound, else the file.
     */
    public static function load(): self
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config(self::CONFIG_KEY);
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return self::fromArray($fromContainer);
                    }
                }
            } catch (InvalidChainRegistry $e) {
                throw $e;
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }

        if (self::$fileInstance === null) {
            $path = __DIR__ . '/../../../../config/poi_chain_registry.php';
            $loaded = is_file($path) ? require $path : null;
            if (! is_array($loaded)) {
                throw new InvalidChainRegistry('config/poi_chain_registry.php is missing or did not return an array');
            }
            self::$fileInstance = self::fromArray($loaded);
        }

        return self::$fileInstance;
    }

    /**
     * Build and validate a registry from a config array. Throws on the first broken rule.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $r = new self();
        $r->canonicalCategories = array_fill_keys((new OvertureTaxonomyMapV2())->canonicalKeys(), true);
        $r->parse($config);

        return $r;
    }

    // ── Accessors ────────────────────────────────────────────────────────────────────────────

    public function registryVersion(): string
    {
        return $this->registryVersion;
    }

    public function normalizerVersion(): string
    {
        return $this->normalizerVersion;
    }

    public function taxonomyMapVersion(): string
    {
        return $this->taxonomyMapVersion;
    }

    /** @return list<string> sorted */
    public function chainKeys(): array
    {
        return array_keys($this->chains);
    }

    public function has(string $brandKey): bool
    {
        return isset($this->chains[$brandKey]);
    }

    /** @throws UnknownChainKey */
    public function chain(string $brandKey): ChainDefinition
    {
        if (! isset($this->chains[$brandKey])) {
            throw new UnknownChainKey("unknown_chain: {$brandKey}");
        }

        return $this->chains[$brandKey];
    }

    /** @return array<string, ChainDefinition> key-sorted */
    public function chains(): array
    {
        return $this->chains;
    }

    /** @return list<string> */
    public function globalExcludedCategories(): array
    {
        return $this->globalExcludedCategories;
    }

    public function isGlobalExclusionWikidata(string $qid): bool
    {
        return isset($this->exclusionWikidataIds[$qid]);
    }

    /** @return list<string> */
    public function exclusionWikidataIds(): array
    {
        return array_keys($this->exclusionWikidataIds);
    }

    /** @return array<string, string> reason => regex */
    public function exclusionNamePatterns(): array
    {
        return $this->exclusionNamePatterns;
    }

    /** @return array<string, string> reason => regex */
    public function closedNamePatterns(): array
    {
        return $this->closedNamePatterns;
    }

    /** @return array<string, array{label: string, wikidata_ids: list<string>, names: list<string>}> */
    public function fuelBrands(): array
    {
        return $this->fuelBrands;
    }

    public function fuelBrandForWikidata(string $qid): ?string
    {
        foreach ($this->fuelBrands as $key => $fuel) {
            if (in_array($qid, $fuel['wikidata_ids'], true)) {
                return $key;
            }
        }

        return null;
    }

    public function fuelBrandForName(string $normalizedName): ?string
    {
        foreach ($this->fuelBrands as $key => $fuel) {
            if (in_array($normalizedName, $fuel['names'], true)) {
                return $key;
            }
        }

        return null;
    }

    public function isCanonicalCategory(string $categoryKey): bool
    {
        return isset($this->canonicalCategories[$categoryKey]);
    }

    // ── Rule hash ────────────────────────────────────────────────────────────────────────────

    /**
     * Every identity-affecting rule, canonicalised. Presentation and evidence text are excluded.
     *
     * @return array<string, mixed>
     */
    public function canonicalRules(): array
    {
        $chains = [];
        foreach ($this->chains as $key => $c) {
            $formats = [];
            foreach ($c->formats as $fk => $f) {
                $formats[$fk] = [
                    'categories' => self::sorted($f->categories),
                    'name_patterns' => self::sorted($f->namePatterns),
                    'role' => $f->role,
                ];
            }
            ksort($formats);

            $aliases = array_map(static fn (array $a): string => $a['match'] . ':' . $a['value'], $c->aliases);

            $coBrands = [];
            foreach ($c->coBrands as $partner => $compounds) {
                $coBrands[$partner] = self::sorted($compounds);
            }
            ksort($coBrands);

            $chains[$key] = [
                'aliases' => self::sorted($aliases),
                'brand_aliases' => self::sorted($c->brandAliases),
                'own_wikidata_ids' => self::sorted($c->ownWikidataIds),
                'department_wikidata_ids' => self::sorted($c->departmentWikidataIds),
                'exclusion_wikidata_ids' => self::sorted($c->exclusionWikidataIds),
                'exclusion_name_patterns' => self::sortedMap($c->exclusionNamePatterns),
                'fuel_brands' => self::sorted($c->fuelBrands),
                'allowed_categories' => self::sortedMap($c->allowedCategories),
                'excluded_categories' => self::sorted($c->excludedCategories),
                'formats' => $formats,
                'default_format' => $c->defaultFormat,
                'fuel_sites' => $c->fuelSites,
                'co_brands' => $coBrands,
            ];
        }

        $fuel = [];
        foreach ($this->fuelBrands as $key => $f) {
            $fuel[$key] = ['wikidata_ids' => self::sorted($f['wikidata_ids']), 'names' => self::sorted($f['names'])];
        }
        ksort($fuel);

        return [
            'registry_version' => $this->registryVersion,
            'normalizer_version' => $this->normalizerVersion,
            'taxonomy_map_version' => $this->taxonomyMapVersion,
            'match_precedence_version' => self::MATCH_PRECEDENCE_VERSION,
            'global' => [
                'excluded_categories' => self::sorted($this->globalExcludedCategories),
                'exclusion_wikidata_ids' => self::sorted(array_keys($this->exclusionWikidataIds)),
                'exclusion_name_patterns' => self::sortedMap($this->exclusionNamePatterns),
                'closed_name_patterns' => self::sortedMap($this->closedNamePatterns),
                'fuel_brands' => $fuel,
            ],
            'chains' => $chains,
        ];
    }

    public function ruleHash(): string
    {
        if ($this->ruleHash === null) {
            $json = json_encode($this->canonicalRules(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->ruleHash = hash('sha256', $json);
        }

        return $this->ruleHash;
    }

    /**
     * The hash a chain's validation record is bound to. Derived from the whole registry on
     * purpose — see the class docblock.
     *
     * @throws UnknownChainKey
     */
    public function chainRuleHash(string $brandKey): string
    {
        $this->chain($brandKey);

        return hash('sha256', 'chain:' . $brandKey . "\n" . $this->ruleHash());
    }

    // ── Parsing and validation ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $config */
    private function parse(array $config): void
    {
        self::exactKeys($config, self::TOP_KEYS, [], 'registry');

        $this->registryVersion = self::string($config['registry_version'], 'registry_version');
        if (preg_match('/^chain-registry-v[1-9]\d*$/', $this->registryVersion) !== 1) {
            self::fail("registry_version must look like chain-registry-vN, got {$this->registryVersion}");
        }

        $this->normalizerVersion = self::string($config['normalizer_version'], 'normalizer_version');
        if ($this->normalizerVersion !== ChainNameNormalizer::VERSION) {
            self::fail("normalizer_version {$this->normalizerVersion} does not match the code's " . ChainNameNormalizer::VERSION);
        }

        $this->taxonomyMapVersion = self::string($config['taxonomy_map_version'], 'taxonomy_map_version');
        if ($this->taxonomyMapVersion !== OvertureTaxonomyMapV2::VERSION) {
            self::fail("taxonomy_map_version {$this->taxonomyMapVersion} does not match the code's " . OvertureTaxonomyMapV2::VERSION);
        }

        $this->parseGlobal(self::map($config['global'], 'global'));

        $chains = self::map($config['chains'], 'chains');
        if ($chains === []) {
            self::fail('chains must not be empty');
        }

        $raw = [];
        foreach ($chains as $key => $entry) {
            $key = (string) $key;
            if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
                self::fail("chain key '{$key}' must match ^[a-z][a-z0-9_]*$");
            }
            $raw[$key] = self::map($entry, "chains.{$key}");
        }
        ksort($raw);

        foreach ($raw as $key => $entry) {
            $this->chains[$key] = $this->parseChain($key, $entry);
        }

        $this->validateCrossChain();
    }

    /** @param array<string, mixed> $g */
    private function parseGlobal(array $g): void
    {
        self::exactKeys($g, self::GLOBAL_KEYS, [], 'global');

        foreach (self::map($g['excluded_categories'], 'global.excluded_categories') as $cat => $evidence) {
            $this->assertCategory((string) $cat, 'global.excluded_categories');
            self::evidence($evidence, "global.excluded_categories.{$cat}");
            $this->globalExcludedCategories[] = (string) $cat;
        }
        sort($this->globalExcludedCategories);

        foreach (self::map($g['exclusion_wikidata_ids'], 'global.exclusion_wikidata_ids') as $qid => $entry) {
            $qid = self::qid((string) $qid, 'global.exclusion_wikidata_ids');
            $e = self::map($entry, "global.exclusion_wikidata_ids.{$qid}");
            self::exactKeys($e, ['label', 'evidence'], [], "global.exclusion_wikidata_ids.{$qid}");
            self::evidence($e['evidence'], "global.exclusion_wikidata_ids.{$qid}");
            $this->exclusionWikidataIds[$qid] = self::string($e['label'], "global.exclusion_wikidata_ids.{$qid}.label");
        }
        ksort($this->exclusionWikidataIds);

        $this->exclusionNamePatterns = self::patternMap($g['exclusion_name_patterns'], 'global.exclusion_name_patterns');
        $this->closedNamePatterns = self::patternMap($g['closed_name_patterns'], 'global.closed_name_patterns');

        foreach (self::map($g['fuel_brands'], 'global.fuel_brands') as $key => $entry) {
            $key = (string) $key;
            $path = "global.fuel_brands.{$key}";
            $e = self::map($entry, $path);
            self::exactKeys($e, ['label', 'wikidata_ids', 'names', 'evidence'], [], $path);
            self::evidence($e['evidence'], $path);

            $qids = [];
            foreach (self::list($e['wikidata_ids'], "{$path}.wikidata_ids") as $q) {
                $qids[] = self::qid(self::string($q, "{$path}.wikidata_ids"), "{$path}.wikidata_ids");
            }
            $names = [];
            foreach (self::list($e['names'], "{$path}.names") as $n) {
                $names[] = self::normalisedValue(self::string($n, "{$path}.names"), "{$path}.names");
            }
            if ($qids === [] && $names === []) {
                self::fail("{$path} identifies nothing");
            }

            $this->fuelBrands[$key] = [
                'label' => self::string($e['label'], "{$path}.label"),
                'wikidata_ids' => self::unique(self::sorted($qids), "{$path}.wikidata_ids"),
                'names' => self::unique(self::sorted($names), "{$path}.names"),
            ];
        }
        ksort($this->fuelBrands);
    }

    /** @param array<string, mixed> $c */
    private function parseChain(string $key, array $c): ChainDefinition
    {
        $p = "chains.{$key}";
        self::exactKeys($c, self::CHAIN_REQUIRED, self::CHAIN_OPTIONAL, $p);

        $displayName = self::string($c['display_name'], "{$p}.display_name");

        // Aliases (names.primary).
        $aliases = [];
        foreach (self::list($c['aliases'], "{$p}.aliases") as $i => $a) {
            $a = self::map($a, "{$p}.aliases.{$i}");
            self::exactKeys($a, ['value', 'match', 'evidence'], [], "{$p}.aliases.{$i}");
            self::evidence($a['evidence'], "{$p}.aliases.{$i}");
            $value = self::normalisedValue(self::string($a['value'], "{$p}.aliases.{$i}.value"), "{$p}.aliases.{$i}");
            $match = self::string($a['match'], "{$p}.aliases.{$i}.match");
            if ($match !== self::MATCH_EXACT && $match !== self::MATCH_PREFIX) {
                self::fail("{$p}.aliases.{$i}.match must be exact or prefix, got {$match}");
            }
            $aliases[$value] = ['value' => $value, 'match' => $match];
        }
        if ($aliases === []) {
            self::fail("{$p}.aliases must have at least one entry");
        }
        if (count($aliases) !== count(self::list($c['aliases'], "{$p}.aliases"))) {
            self::fail("{$p}.aliases has duplicate values");
        }
        ksort($aliases);
        $aliases = array_values($aliases);

        // Brand aliases (brand.names.primary). May be empty, must be present.
        $brandAliases = [];
        foreach (self::list($c['brand_aliases'], "{$p}.brand_aliases") as $i => $b) {
            $b = self::map($b, "{$p}.brand_aliases.{$i}");
            self::exactKeys($b, ['value', 'evidence'], [], "{$p}.brand_aliases.{$i}");
            self::evidence($b['evidence'], "{$p}.brand_aliases.{$i}");
            $brandAliases[] = self::normalisedValue(self::string($b['value'], "{$p}.brand_aliases.{$i}.value"), "{$p}.brand_aliases.{$i}");
        }
        $brandAliases = self::unique(self::sorted($brandAliases), "{$p}.brand_aliases");

        // Wikidata.
        $own = [];
        foreach (self::map($c['own_wikidata_ids'], "{$p}.own_wikidata_ids") as $qid => $evidence) {
            $own[] = self::qid((string) $qid, "{$p}.own_wikidata_ids");
            self::evidence($evidence, "{$p}.own_wikidata_ids.{$qid}");
        }
        $department = self::labelledQids($c['department_wikidata_ids'] ?? [], "{$p}.department_wikidata_ids");
        $exclusionQids = self::labelledQids($c['exclusion_wikidata_ids'] ?? [], "{$p}.exclusion_wikidata_ids");
        $own = self::sorted($own);

        foreach ([[$own, $department], [$own, $exclusionQids], [$department, $exclusionQids]] as [$x, $y]) {
            if (array_intersect($x, $y) !== []) {
                self::fail("{$p}: a Wikidata ID may hold only one role (own / department / exclusion)");
            }
        }
        foreach (array_merge($own, $department) as $qid) {
            if (isset($this->exclusionWikidataIds[$qid])) {
                self::fail("{$p}: {$qid} is a global exclusion ID and cannot be chain identity");
            }
        }

        $exclusionPatterns = self::patternMap($c['exclusion_name_patterns'] ?? [], "{$p}.exclusion_name_patterns");

        // Fuel brands expected at this chain's sites (diagnostic only).
        $fuelBrands = [];
        foreach (self::list($c['fuel_brands'] ?? [], "{$p}.fuel_brands") as $fk) {
            $fk = self::string($fk, "{$p}.fuel_brands");
            if (! isset($this->fuelBrands[$fk])) {
                self::fail("{$p}.fuel_brands names unknown fuel brand {$fk}");
            }
            $fuelBrands[] = $fk;
        }
        $fuelBrands = self::unique(self::sorted($fuelBrands), "{$p}.fuel_brands");

        // Categories.
        $allowed = [];
        foreach (self::map($c['allowed_categories'], "{$p}.allowed_categories") as $cat => $entry) {
            $cat = (string) $cat;
            $this->assertCategory($cat, "{$p}.allowed_categories");
            $e = self::map($entry, "{$p}.allowed_categories.{$cat}");
            self::exactKeys($e, ['role', 'evidence'], [], "{$p}.allowed_categories.{$cat}");
            self::evidence($e['evidence'], "{$p}.allowed_categories.{$cat}");
            $allowed[$cat] = self::role($e['role'], "{$p}.allowed_categories.{$cat}.role");
        }
        if ($allowed === []) {
            self::fail("{$p}.allowed_categories must have at least one entry");
        }
        ksort($allowed);

        $excluded = [];
        foreach (self::map($c['excluded_categories'] ?? [], "{$p}.excluded_categories") as $cat => $evidence) {
            $cat = (string) $cat;
            $this->assertCategory($cat, "{$p}.excluded_categories");
            self::evidence($evidence, "{$p}.excluded_categories.{$cat}");
            $excluded[] = $cat;
        }
        $excluded = self::sorted($excluded);

        foreach (array_keys($allowed) as $cat) {
            if (in_array($cat, $excluded, true) || in_array($cat, $this->globalExcludedCategories, true)) {
                self::fail("{$p}: category {$cat} is both allowed and excluded");
            }
        }

        // Formats.
        $formats = [];
        foreach (self::map($c['formats'], "{$p}.formats") as $fk => $entry) {
            $fk = (string) $fk;
            if (preg_match('/^[a-z][a-z0-9_]*$/', $fk) !== 1) {
                self::fail("{$p}.formats key '{$fk}' must match ^[a-z][a-z0-9_]*$");
            }
            $formats[$fk] = $this->parseFormat($fk, self::map($entry, "{$p}.formats.{$fk}"), $allowed, "{$p}.formats.{$fk}");
        }
        if ($formats === []) {
            self::fail("{$p}.formats must have at least one entry");
        }
        ksort($formats);

        foreach ($allowed as $cat => $role) {
            $defaults = array_values(array_filter($formats, static fn (ChainFormat $f): bool => $f->isDefault() && $f->appliesTo($cat)));
            if (count($defaults) !== 1) {
                self::fail("{$p}: category {$cat} needs exactly one format without name patterns, found " . count($defaults));
            }
        }

        $defaultFormat = self::string($c['default_format'], "{$p}.default_format");
        if (! isset($formats[$defaultFormat]) || ! $formats[$defaultFormat]->isDefault()) {
            self::fail("{$p}.default_format {$defaultFormat} must name a format without name patterns");
        }

        // Fuel sites: required exactly when the chain has a fuel role.
        $hasFuelRole = in_array(ChainRole::FUEL, $allowed, true);
        $fuelSites = null;
        if (array_key_exists('fuel_sites', $c) && $c['fuel_sites'] !== null) {
            $fs = self::map($c['fuel_sites'], "{$p}.fuel_sites");
            self::exactKeys($fs, ['store_with_fuel', 'fuel_only', 'evidence'], [], "{$p}.fuel_sites");
            self::evidence($fs['evidence'], "{$p}.fuel_sites");
            foreach (['store_with_fuel', 'fuel_only'] as $flag) {
                if (! is_bool($fs[$flag])) {
                    self::fail("{$p}.fuel_sites.{$flag} must be a boolean");
                }
            }
            $fuelSites = ['store_with_fuel' => $fs['store_with_fuel'], 'fuel_only' => $fs['fuel_only']];
        }
        if ($hasFuelRole !== ($fuelSites !== null)) {
            self::fail("{$p}: fuel_sites must be declared exactly when a category has the fuel role");
        }

        // Co-brands (symmetry is checked once every chain is parsed).
        $coBrands = [];
        foreach (self::map($c['co_brands'] ?? [], "{$p}.co_brands") as $partner => $entry) {
            $partner = (string) $partner;
            $cp = "{$p}.co_brands.{$partner}";
            if ($partner === $key) {
                self::fail("{$cp}: a chain cannot co-brand with itself");
            }
            $e = self::map($entry, $cp);
            self::exactKeys($e, ['evidence_rule', 'compound_names', 'evidence'], [], $cp);
            self::evidence($e['evidence'], $cp);
            if ($e['evidence_rule'] !== self::CO_BRAND_EVIDENCE_RULE) {
                self::fail("{$cp}.evidence_rule must be " . self::CO_BRAND_EVIDENCE_RULE);
            }
            $compounds = [];
            foreach (self::list($e['compound_names'], "{$cp}.compound_names") as $n) {
                $compounds[] = self::normalisedValue(self::string($n, "{$cp}.compound_names"), "{$cp}.compound_names");
            }
            if ($compounds === []) {
                self::fail("{$cp}.compound_names must have at least one entry");
            }
            $coBrands[$partner] = self::unique(self::sorted($compounds), "{$cp}.compound_names");
        }
        ksort($coBrands);

        if (($c['validation_status'] ?? null) !== self::STATUS_PROVISIONAL) {
            self::fail("{$p}.validation_status must be '" . self::STATUS_PROVISIONAL . "': promotion is a validation record, never config");
        }

        foreach (self::list($c['notes'] ?? [], "{$p}.notes") as $note) {
            self::string($note, "{$p}.notes");
        }

        return new ChainDefinition(
            $key,
            $displayName,
            $aliases,
            $brandAliases,
            $own,
            $department,
            $exclusionQids,
            $exclusionPatterns,
            $fuelBrands,
            $allowed,
            $excluded,
            $formats,
            $defaultFormat,
            $fuelSites,
            $coBrands,
            self::STATUS_PROVISIONAL,
        );
    }

    /**
     * @param array<string, mixed>  $f
     * @param array<string, string> $allowed
     */
    private function parseFormat(string $key, array $f, array $allowed, string $p): ChainFormat
    {
        self::exactKeys($f, ['categories', 'role', 'evidence'], array_diff(self::FORMAT_KEYS, ['categories', 'role', 'evidence']), $p);
        self::evidence($f['evidence'], $p);

        $categories = [];
        foreach (self::list($f['categories'], "{$p}.categories") as $cat) {
            $cat = self::string($cat, "{$p}.categories");
            if (! isset($allowed[$cat])) {
                self::fail("{$p}: category {$cat} is not in the chain's allowed_categories");
            }
            $categories[] = $cat;
        }
        if ($categories === []) {
            self::fail("{$p}.categories must have at least one entry");
        }
        $categories = self::unique(self::sorted($categories), "{$p}.categories");

        $patterns = [];
        foreach (self::list($f['name_patterns'] ?? [], "{$p}.name_patterns") as $pattern) {
            $patterns[] = self::pattern(self::string($pattern, "{$p}.name_patterns"), "{$p}.name_patterns");
        }
        $patterns = self::unique(self::sorted($patterns), "{$p}.name_patterns");

        $role = self::role($f['role'], "{$p}.role");

        // Fuel identity comes from a fuel CATEGORY, never from a name pattern: a pattern format
        // may not turn a non-fuel category's rows into fuel (it would also escape fuel_sites).
        if ($role === ChainRole::FUEL) {
            foreach ($categories as $cat) {
                if ($allowed[$cat] !== ChainRole::FUEL) {
                    self::fail("{$p}: the fuel role is allowed only on categories whose own role is fuel, not {$cat}");
                }
            }
        }

        // A default format may not change a category's role: the category alone must never
        // upgrade a department into a storefront. Only an explicit name pattern may.
        if ($patterns === []) {
            foreach ($categories as $cat) {
                if ($allowed[$cat] !== $role) {
                    self::fail("{$p}: a format without name patterns must keep category {$cat}'s role ({$allowed[$cat]}), got {$role}");
                }
            }
        }

        $userVisible = $f['user_visible'] ?? false;
        if (! is_bool($userVisible)) {
            self::fail("{$p}.user_visible must be a boolean");
        }
        $label = $f['label'] ?? null;
        if ($label !== null) {
            $label = self::string($label, "{$p}.label");
        }
        if ($userVisible !== ($label !== null)) {
            self::fail("{$p}: a label is required exactly when user_visible is true");
        }

        return new ChainFormat($key, $categories, $patterns, $role, $userVisible, $label);
    }

    private function validateCrossChain(): void
    {
        // Co-brand symmetry, with identical compound names on both sides.
        foreach ($this->chains as $key => $chain) {
            foreach ($chain->coBrands as $partner => $compounds) {
                if (! isset($this->chains[$partner])) {
                    self::fail("chains.{$key}.co_brands names unknown chain {$partner}");
                }
                if (($this->chains[$partner]->coBrands[$key] ?? null) !== $compounds) {
                    self::fail("co-brand {$key}/{$partner} must be declared symmetrically with identical compound_names");
                }
            }
        }

        // Every identity string belongs to exactly one chain.
        $owner = [];
        foreach ($this->chains as $key => $chain) {
            $strings = array_unique(array_merge(array_column($chain->aliases, 'value'), $chain->brandAliases));
            foreach ($strings as $s) {
                if (isset($owner[$s]) && $owner[$s] !== $key) {
                    self::fail("alias collision: '{$s}' belongs to both {$owner[$s]} and {$key}");
                }
                $owner[$s] = $key;
            }
        }

        // Compound names are unique to one pair and never equal a single chain's alias.
        $compoundOwner = [];
        foreach ($this->chains as $key => $chain) {
            foreach ($chain->coBrands as $partner => $compounds) {
                $pair = implode('/', self::sorted([$key, $partner]));
                foreach ($compounds as $compound) {
                    if (isset($owner[$compound])) {
                        self::fail("compound name '{$compound}' collides with an alias of {$owner[$compound]}");
                    }
                    if (isset($compoundOwner[$compound]) && $compoundOwner[$compound] !== $pair) {
                        self::fail("compound name '{$compound}' is declared by two co-brand pairs");
                    }
                    $compoundOwner[$compound] = $pair;
                }
            }
        }

        // A prefix alias must not swallow another chain's identity string. A compound belonging
        // to a pair that includes the prefix's own chain is the one intended overlap.
        foreach ($this->chains as $key => $chain) {
            foreach ($chain->aliases as $alias) {
                if ($alias['match'] !== self::MATCH_PREFIX) {
                    continue;
                }
                $prefix = $alias['value'];
                foreach ($owner as $s => $sOwner) {
                    if ($sOwner !== $key && self::wordPrefixed($s, $prefix)) {
                        self::fail("prefix alias '{$prefix}' of {$key} would swallow '{$s}' of {$sOwner}");
                    }
                }
                foreach ($compoundOwner as $compound => $pair) {
                    if (! in_array($key, explode('/', $pair), true) && self::wordPrefixed($compound, $prefix)) {
                        self::fail("prefix alias '{$prefix}' of {$key} would swallow compound '{$compound}' of {$pair}");
                    }
                }
            }
        }

        // Wikidata roles are exclusive across the whole registry.
        $qidRole = [];
        $claim = static function (string $qid, string $role) use (&$qidRole): void {
            if (isset($qidRole[$qid]) && $qidRole[$qid] !== $role) {
                self::fail("Wikidata ID {$qid} is claimed as both {$qidRole[$qid]} and {$role}");
            }
            $qidRole[$qid] = $role;
        };
        foreach (array_keys($this->exclusionWikidataIds) as $qid) {
            $claim($qid, 'global exclusion');
        }
        foreach ($this->fuelBrands as $fk => $fuel) {
            foreach ($fuel['wikidata_ids'] as $qid) {
                $claim($qid, "fuel brand {$fk}");
            }
        }
        foreach ($this->chains as $key => $chain) {
            foreach ($chain->ownWikidataIds as $qid) {
                $claim($qid, "own ID of {$key}");
            }
            foreach ($chain->departmentWikidataIds as $qid) {
                $claim($qid, "department ID of {$key}");
            }
        }
        foreach ($this->chains as $key => $chain) {
            foreach ($chain->exclusionWikidataIds as $qid) {
                if (isset($qidRole[$qid])) {
                    self::fail("chains.{$key}.exclusion_wikidata_ids: {$qid} is already the {$qidRole[$qid]}");
                }
            }
        }

        // A fuel brand name is never a chain identity string, and belongs to one fuel brand.
        $fuelNameOwner = [];
        foreach ($this->fuelBrands as $fk => $fuel) {
            foreach ($fuel['names'] as $name) {
                if (isset($owner[$name]) || isset($compoundOwner[$name])) {
                    self::fail("fuel brand {$fk} name '{$name}' collides with chain identity");
                }
                if (isset($fuelNameOwner[$name])) {
                    self::fail("fuel brand name '{$name}' is declared by both {$fuelNameOwner[$name]} and {$fk}");
                }
                $fuelNameOwner[$name] = $fk;
            }
        }

        // No exclusion or closed-name pattern may match a fuel-brand name. Patterns are also run
        // against the row's brand field, so one that matched "mobil" would let a fuel brand
        // destroy a membership — which fuel identity must never do.
        $patternSets = ['global.exclusion_name_patterns' => $this->exclusionNamePatterns, 'global.closed_name_patterns' => $this->closedNamePatterns];
        foreach ($this->chains as $key => $chain) {
            $patternSets["chains.{$key}.exclusion_name_patterns"] = $chain->exclusionNamePatterns;
        }
        foreach ($patternSets as $where => $patterns) {
            foreach ($patterns as $reason => $pattern) {
                foreach (array_keys($fuelNameOwner) as $name) {
                    if (preg_match($pattern, $name) === 1) {
                        self::fail("{$where}.{$reason} matches fuel brand name '{$name}'; a fuel brand may never remove a membership");
                    }
                }
            }
        }
    }

    // ── Small helpers ────────────────────────────────────────────────────────────────────────

    private function assertCategory(string $cat, string $path): void
    {
        if (! isset($this->canonicalCategories[$cat])) {
            self::fail("{$path}: '{$cat}' is not one of the canonical v2 category keys");
        }
    }

    private static function wordPrefixed(string $subject, string $prefix): bool
    {
        return $subject === $prefix || str_starts_with($subject, $prefix . ' ');
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>         $required
     * @param list<string>         $optional
     */
    private static function exactKeys(array $value, array $required, array $optional, string $path): void
    {
        foreach ($required as $k) {
            if (! array_key_exists($k, $value)) {
                self::fail("{$path}: missing required field '{$k}'");
            }
        }
        foreach (array_keys($value) as $k) {
            if (! in_array($k, $required, true) && ! in_array($k, $optional, true)) {
                self::fail("{$path}: unknown field '{$k}'");
            }
        }
    }

    /** @return array<string, mixed> */
    private static function map($value, string $path): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            self::fail("{$path} must be a keyed map");
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function list($value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            self::fail("{$path} must be a list");
        }

        return $value;
    }

    private static function string($value, string $path): string
    {
        if (! is_string($value) || trim($value) === '') {
            self::fail("{$path} must be a non-empty string");
        }

        return $value;
    }

    private static function evidence($value, string $path): void
    {
        if (! is_string($value) || preg_match('/^[MDP](: \S.*)?$/s', $value) !== 1) {
            self::fail("{$path}: evidence must be 'M', 'D' or 'P', optionally followed by ': <note>'");
        }
    }

    private static function role($value, string $path): string
    {
        if (! is_string($value) || ! in_array($value, ChainRole::all(), true)) {
            self::fail("{$path} must be one of " . implode('|', ChainRole::all()));
        }

        return $value;
    }

    private static function qid(string $value, string $path): string
    {
        if (preg_match('/^Q[1-9]\d*$/', $value) !== 1) {
            self::fail("{$path}: '{$value}' is not a Wikidata ID");
        }

        return $value;
    }

    private static function normalisedValue(string $value, string $path): string
    {
        if (ChainNameNormalizer::normalize($value) !== $value) {
            self::fail("{$path}: '{$value}' must be stored already normalised (" . ChainNameNormalizer::VERSION . ')');
        }
        if (ChainNameNormalizer::isQidShaped($value)) {
            self::fail("{$path}: '{$value}' is shaped like a Wikidata ID and can never be a name");
        }

        return $value;
    }

    /**
     * A regex the matcher may run: `/…/` with no modifiers (names are already lower-case),
     * compiles, and does not match the empty string — a pattern that matches everything would
     * turn an exclusion into "reject every row" and a format into "every row is this format".
     */
    private static function pattern(string $pattern, string $path): string
    {
        if (preg_match('#^/.+/$#s', $pattern) !== 1) {
            self::fail("{$path}: '{$pattern}' must be delimited by / with no modifiers");
        }
        if (@preg_match($pattern, '') === false) {
            self::fail("{$path}: '{$pattern}' does not compile");
        }
        foreach (self::NEUTRAL_PROBES as $probe) {
            if (preg_match($pattern, $probe) === 1) {
                self::fail($probe === ''
                    ? "{$path}: '{$pattern}' matches the empty string"
                    : "{$path}: '{$pattern}' matches the neutral string '{$probe}' and would match almost anything");
            }
        }

        return $pattern;
    }

    /** @return array<string, string> */
    private static function patternMap($value, string $path): array
    {
        $out = [];
        foreach (self::map($value, $path) as $reason => $entry) {
            $reason = (string) $reason;
            if (preg_match('/^[a-z][a-z0-9_]*$/', $reason) !== 1) {
                self::fail("{$path}: reason key '{$reason}' must match ^[a-z][a-z0-9_]*$");
            }
            $e = self::map($entry, "{$path}.{$reason}");
            self::exactKeys($e, ['pattern', 'evidence'], [], "{$path}.{$reason}");
            self::evidence($e['evidence'], "{$path}.{$reason}");
            $out[$reason] = self::pattern(self::string($e['pattern'], "{$path}.{$reason}.pattern"), "{$path}.{$reason}");
        }
        ksort($out);

        return $out;
    }

    /** @return list<string> */
    private static function labelledQids($value, string $path): array
    {
        $out = [];
        foreach (self::map($value, $path) as $qid => $entry) {
            $qid = self::qid((string) $qid, $path);
            $e = self::map($entry, "{$path}.{$qid}");
            self::exactKeys($e, ['label', 'evidence'], [], "{$path}.{$qid}");
            self::string($e['label'], "{$path}.{$qid}.label");
            self::evidence($e['evidence'], "{$path}.{$qid}");
            $out[] = $qid;
        }

        return self::sorted($out);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    private static function sortedMap(array $map): array
    {
        ksort($map, SORT_STRING);

        return $map;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function unique(array $values, string $path): array
    {
        if (count($values) !== count(array_unique($values))) {
            self::fail("{$path} has duplicate values");
        }

        return $values;
    }

    private static function fail(string $message): never
    {
        throw new InvalidChainRegistry($message);
    }
}
