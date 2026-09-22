<?php

namespace Tests\Unit\Spatial\ChainRegistry;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\ChainRegistry\InvalidChainRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Every load-time rule of design §2 / §4.2 refuses a broken registry. Each case takes the shipped
 * config, breaks exactly one thing, and expects {@see InvalidChainRegistry}. Pure; no container.
 */
class ChainRegistryValidationTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function config(): array
    {
        return require __DIR__ . '/../../../../config/poi_chain_registry.php';
    }

    public function test_the_shipped_config_is_valid(): void
    {
        $this->assertCount(20, ChainRegistry::fromArray(self::config())->chainKeys());
    }

    /** @return array<string, array{0: callable(array): array, 1: string}> */
    public static function brokenConfigs(): array
    {
        return [
            // Validation status — the registry cannot self-promote.
            'supported in config'          => [fn (array $c) => self::set($c, 'chains.aldi.validation_status', 'supported'), 'validation_status'],
            'status missing'               => [fn (array $c) => self::unset($c, 'chains.aldi.validation_status'), 'validation_status'],

            // Shape.
            'unknown top-level key'        => [fn (array $c) => self::set($c, 'extra', true), "unknown field 'extra'"],
            'unknown chain field'          => [fn (array $c) => self::set($c, 'chains.aldi.alias', []), "unknown field 'alias'"],
            'missing brand_aliases'        => [fn (array $c) => self::unset($c, 'chains.aldi.brand_aliases'), "'brand_aliases'"],
            'no aliases'                   => [fn (array $c) => self::set($c, 'chains.aldi.aliases', []), 'aliases'],
            'display-name key'             => [fn (array $c) => self::rename($c, 'aldi', 'Aldi'), 'chain key'],
            'wrong normalizer version'     => [fn (array $c) => self::set($c, 'normalizer_version', 'chain-name-norm-v2'), 'normalizer_version'],
            'wrong taxonomy version'       => [fn (array $c) => self::set($c, 'taxonomy_map_version', 'overture-taxonomy-v1'), 'taxonomy_map_version'],
            'bad registry version'         => [fn (array $c) => self::set($c, 'registry_version', '2026-08-19.0'), 'registry_version'],

            // Evidence.
            'alias without evidence'       => [fn (array $c) => self::unset($c, 'chains.aldi.aliases.0.evidence'), "'evidence'"],
            'bad evidence marker'          => [fn (array $c) => self::set($c, 'chains.aldi.own_wikidata_ids.Q41171672', 'measured'), 'evidence'],

            // Aliases.
            'alias not normalised'         => [fn (array $c) => self::set($c, 'chains.aldi.aliases.0.value', 'ALDI'), 'normalised'],
            'alias is a QID'               => [fn (array $c) => self::set($c, 'chains.aldi.aliases.0.value', 'q41171672'), 'Wikidata'],
            'unknown match mode'           => [fn (array $c) => self::set($c, 'chains.aldi.aliases.0.match', 'fuzzy'), 'exact or prefix'],
            'alias collision'              => [fn (array $c) => self::push($c, 'chains.aldi.aliases', ['value' => 'publix', 'match' => 'exact', 'evidence' => 'P']), 'collision'],
            'brand alias collision'        => [fn (array $c) => self::push($c, 'chains.aldi.brand_aliases', ['value' => 'wawa', 'evidence' => 'P']), 'collision'],
            'prefix shadows another chain' => [fn (array $c) => self::push($c, 'chains.aldi.aliases', ['value' => 'burger', 'match' => 'prefix', 'evidence' => 'P']), 'swallow'],
            'prefix shadows a compound'    => [fn (array $c) => self::push($c, 'chains.aldi.aliases', ['value' => 'speedway 7', 'match' => 'prefix', 'evidence' => 'P']), 'swallow compound'],

            // Wikidata.
            'malformed QID'                => [fn (array $c) => self::set($c, 'chains.walmart.own_wikidata_ids', ['walmart' => 'P']), 'Wikidata'],
            'QID owned by two chains'      => [fn (array $c) => self::set($c, 'chains.walmart.own_wikidata_ids', ['Q672170' => 'P']), 'claimed'],
            'own QID is an exclusion'      => [fn (array $c) => self::set($c, 'chains.walmart.own_wikidata_ids', ['Q861042' => 'P']), 'exclusion'],
            'fuel QID as chain identity'   => [fn (array $c) => self::set($c, 'chains.walmart.own_wikidata_ids', ['Q109676002' => 'P']), 'claimed'],
            'unknown fuel brand'           => [fn (array $c) => self::set($c, 'chains.wawa.fuel_brands', ['sunoco']), 'unknown fuel brand'],
            'fuel name is chain identity'  => [fn (array $c) => self::set($c, 'global.fuel_brands.mobil.names', ['shell']), 'collides'],

            // Categories.
            'non-canonical category'       => [fn (array $c) => self::set($c, 'chains.aldi.allowed_categories.bakery', ['role' => 'storefront', 'evidence' => 'P']), 'canonical'],
            'shopping_center allowed'      => [fn (array $c) => self::allowShoppingCenter($c), 'both allowed and excluded'],
            'allowed and excluded'         => [fn (array $c) => self::set($c, 'chains.aldi.excluded_categories', ['grocery_store' => 'P']), 'both allowed and excluded'],
            'unknown role'                 => [fn (array $c) => self::set($c, 'chains.aldi.allowed_categories.grocery_store.role', 'kiosk'), 'role'],

            // Formats.
            'format outside allowed'       => [fn (array $c) => self::set($c, 'chains.aldi.formats.store.categories', ['grocery_store', 'pharmacy']), 'not in the chain'],
            'category without default'     => [fn (array $c) => self::unset($c, 'chains.walmart.formats.grocery_department'), 'exactly one format without name patterns'],
            'two defaults for a category'  => [fn (array $c) => self::set($c, 'chains.aldi.formats.other', ['categories' => ['grocery_store'], 'role' => 'storefront', 'evidence' => 'P']), 'exactly one format'],
            'default upgrades department'  => [fn (array $c) => self::set($c, 'chains.walmart.formats.grocery_department.role', 'storefront'), "keep category grocery_store's role"],
            'default_format has pattern'   => [fn (array $c) => self::set($c, 'chains.walmart.default_format', 'supercenter'), 'default_format'],
            'unknown default_format'       => [fn (array $c) => self::set($c, 'chains.aldi.default_format', 'market'), 'default_format'],
            'pattern matches empty string' => [fn (array $c) => self::set($c, 'chains.walmart.formats.supercenter.name_patterns', ['/.*/']), 'empty string'],
            'pattern matches anything'     => [fn (array $c) => self::set($c, 'global.exclusion_name_patterns.atm.pattern', '/./'), 'neutral string'],
            'format pattern matches words' => [fn (array $c) => self::set($c, 'chains.walmart.formats.neighborhood_market.name_patterns', ['/\\b/']), 'neutral string'],
            'pattern matches any word'     => [fn (array $c) => self::set($c, 'chains.walmart.formats.neighborhood_market.name_patterns', ['/\\w{6}/']), 'neutral string'],
            'pattern with modifier'        => [fn (array $c) => self::set($c, 'global.exclusion_name_patterns.atm.pattern', '/\batm\b/i'), 'no modifiers'],
            'pattern does not compile'     => [fn (array $c) => self::set($c, 'global.exclusion_name_patterns.atm.pattern', '/(atm/'), 'compile'],
            'visible without label'        => [fn (array $c) => self::set($c, 'chains.aldi.formats.store.user_visible', true), 'label'],

            'fuel role from a name pattern' => [fn (array $c) => self::set($c, 'chains.walmart.formats.fuel', ['categories' => ['superstore'], 'name_patterns' => ['/^fuel\\b/'], 'role' => 'fuel', 'evidence' => 'P']), 'fuel role is allowed only'],

            // Fuel brands can never remove a membership.
            'chain pattern hits fuel name' => [fn (array $c) => self::set($c, 'chains.seven_eleven.exclusion_name_patterns', ['mobil' => ['pattern' => '/\\bmobil\\b/', 'evidence' => 'P']]), 'fuel brand name'],
            'global pattern hits fuel name' => [fn (array $c) => self::set($c, 'global.closed_name_patterns.arco', ['pattern' => '/^arco$/', 'evidence' => 'P']), 'fuel brand name'],
            'fuel name in two fuel brands' => [fn (array $c) => self::set($c, 'global.fuel_brands.arco.names', ['arco', 'mobil']), 'declared by both'],

            // Fuel sites.
            'fuel role without fuel_sites' => [fn (array $c) => self::unset($c, 'chains.wawa.fuel_sites'), 'fuel_sites'],
            'fuel_sites without fuel role' => [fn (array $c) => self::set($c, 'chains.aldi.fuel_sites', ['store_with_fuel' => true, 'fuel_only' => false, 'evidence' => 'P']), 'fuel_sites'],

            // Co-brands.
            'asymmetric co-brand'          => [fn (array $c) => self::unset($c, 'chains.speedway.co_brands'), 'symmetrically'],
            'mismatched compound names'    => [fn (array $c) => self::set($c, 'chains.speedway.co_brands.seven_eleven.compound_names', ['7 eleven speedway']), 'symmetrically'],
            'co-brand with unknown chain'  => [fn (array $c) => self::set($c, 'chains.aldi.co_brands', ['lidl' => ['evidence_rule' => 'co_brand_evidence', 'compound_names' => ['aldi lidl'], 'evidence' => 'P']]), 'unknown chain'],
            'unknown evidence rule'        => [fn (array $c) => self::set($c, 'chains.speedway.co_brands.seven_eleven.evidence_rule', 'proximity'), 'evidence_rule'],
        ];
    }

    /** @dataProvider brokenConfigs */
    public function test_a_broken_rule_is_refused(callable $break, string $messageFragment): void
    {
        $this->expectException(InvalidChainRegistry::class);
        $this->expectExceptionMessage($messageFragment);

        ChainRegistry::fromArray($break(self::config()));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private static function set(array $c, string $path, $value): array
    {
        $ref = &$c;
        foreach (explode('.', $path) as $segment) {
            $ref = &$ref[$segment];
        }
        $ref = $value;

        return $c;
    }

    private static function unset(array $c, string $path): array
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        $ref = &$c;
        foreach ($segments as $segment) {
            $ref = &$ref[$segment];
        }
        unset($ref[$last]);

        return $c;
    }

    private static function push(array $c, string $path, $value): array
    {
        $ref = &$c;
        foreach (explode('.', $path) as $segment) {
            $ref = &$ref[$segment];
        }
        $ref[] = $value;

        return $c;
    }

    private static function rename(array $c, string $from, string $to): array
    {
        $c['chains'][$to] = $c['chains'][$from];
        unset($c['chains'][$from]);

        return $c;
    }

    private static function allowShoppingCenter(array $c): array
    {
        $c['chains']['publix']['allowed_categories']['shopping_center'] = ['role' => 'storefront', 'evidence' => 'P'];
        $c['chains']['publix']['formats']['supermarket']['categories'][] = 'shopping_center';

        return $c;
    }
}
