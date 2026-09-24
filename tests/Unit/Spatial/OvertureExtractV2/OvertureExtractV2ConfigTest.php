<?php

namespace Tests\Unit\Spatial\OvertureExtractV2;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureExtractV2\InvalidOvertureExtractV2;
use App\Services\Spatial\OvertureExtractV2\OvertureExtractV2Config;
use App\Services\Spatial\OvertureTaxonomyMapV2;
use PHPUnit\Framework\TestCase;

/**
 * config/overture_extract_v2.php: the shipped pins, and fail-closed validation of every rule —
 * above all that a rescue lane mirrors the chain registry exactly, in both directions.
 */
class OvertureExtractV2ConfigTest extends TestCase
{
    private ChainRegistry $registry;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = ChainRegistry::fromArray(require __DIR__ . '/../../../../config/poi_chain_registry.php');
        $this->config = require __DIR__ . '/../../../../config/overture_extract_v2.php';
    }

    private function load(array $config): OvertureExtractV2Config
    {
        return OvertureExtractV2Config::fromArray($config, $this->registry);
    }

    public function test_shipped_pins(): void
    {
        $c = $this->load($this->config);
        $this->assertSame('overture-extract-v2', $c->recipeVersion);
        $this->assertSame('2026-08-19.0', $c->release);
        $this->assertSame([-87.63, 24.40, -79.97, 31.00], [$c->west, $c->south, $c->east, $c->north]);
        $this->assertSame(0.90, $c->confidenceMin);
        $this->assertSame(['open'], $c->eligibleStatuses);
        $this->assertTrue($c->nullStatusEligible);
        $this->assertSame(['permanently_closed'], $c->excludedStatuses);
        $this->assertSame(['cvs_shopping'], array_keys($c->rescueLanes));
        $this->assertSame(['chain' => 'cvs', 'source_category' => 'shopping', 'as_category' => 'drugstore'], array_intersect_key($c->rescueLanes['cvs_shopping'], array_flip(['chain', 'source_category', 'as_category'])));
        $this->assertStringNotContainsString('chain-registry', $c->recipeVersion, 'recipe and registry versions are separate');
    }

    public function test_the_base_category_set_is_exactly_the_taxonomy_map(): void
    {
        $pins = $this->load($this->config)->pins();
        $this->assertSame((new OvertureTaxonomyMapV2())->sourceTokens(), $pins['base_source_tokens']);
        $this->assertCount(16, $pins['base_source_tokens']);
        $this->assertNotContains('shopping', $pins['base_source_tokens']);
        $this->assertSame('taxonomy.primary', $pins['taxonomy_source_field']);
        $this->assertSame(OvertureTaxonomyMapV2::VERSION, $pins['taxonomy_map_version']);
    }

    public function test_the_supplementary_selector_is_pinned_in_the_manifest(): void
    {
        $pins = $this->load($this->config)->pins();
        $sel = $this->config['supplementary']['diagnostic_selector'];
        $this->assertSame($sel['name_brand_patterns'], $pins['diagnostic_selector']['name_brand_patterns']);
        $this->assertSame($sel['any_brand_wikidata'], $pins['diagnostic_selector']['any_brand_wikidata']);

        $edited = $this->config;
        $edited['supplementary']['diagnostic_selector']['name_brand_patterns'][] = 'costco';
        $this->assertNotEquals($pins, $this->load($edited)->pins(), 'a selector edit changes the pins even with the same recipe_version');
    }

    public function test_load_reads_the_same_file_without_a_container(): void
    {
        $this->assertEquals($this->load($this->config)->pins(), OvertureExtractV2Config::load($this->registry)->pins());
    }

    /** @return array<string, array{0: callable(array): array, 1: string}> */
    public static function brokenConfigs(): array
    {
        return [
            'unknown top key'            => [fn (array $c) => $c + ['extra' => 1], "unknown key 'extra'"],
            'missing release'            => [function (array $c) { unset($c['release']); return $c; }, "missing key 'release'"],
            'bad recipe version'         => [fn (array $c) => array_replace($c, ['recipe_version' => 'chain-registry-v2']), 'overture-extract-vN'],
            'bad release'                => [fn (array $c) => array_replace($c, ['release' => 'latest']), 'YYYY-MM-DD.N'],
            'inverted bbox'              => [fn (array $c) => array_replace_recursive($c, ['bbox' => ['west' => -79.0, 'east' => -87.0]]), 'non-empty'],
            'string bbox'                => [fn (array $c) => array_replace_recursive($c, ['bbox' => ['north' => '31']]), 'number'],
            'floor zero'                 => [fn (array $c) => array_replace($c, ['confidence_min' => 0]), '(0, 1]'],
            'floor above one'            => [fn (array $c) => array_replace($c, ['confidence_min' => 1.2]), '(0, 1]'],
            'status both ways'           => [fn (array $c) => array_replace_recursive($c, ['operating_status' => ['excluded' => ['open']]]), 'both eligible and excluded'],
            'no eligible status'         => [function (array $c) { $c['operating_status']['eligible_explicit'] = []; return $c; }, 'at least one'],
            'null flag not bool'         => [fn (array $c) => array_replace_recursive($c, ['operating_status' => ['eligible_unknown_is_null' => 'yes']]), 'boolean'],
            'pattern does not compile'   => [fn (array $c) => self::patterns($c, ['cvs', '(unclosed']), 'does not compile'],
            'pattern matches anything'   => [fn (array $c) => self::patterns($c, ['.*']), 'matches an empty name'],
            'no patterns'                => [fn (array $c) => self::patterns($c, []), 'non-empty list'],
            'duplicate pattern'          => [fn (array $c) => self::patterns($c, ['cvs', 'cvs']), 'duplicate'],
            'lane for unknown chain'     => [fn (array $c) => self::lane($c, ['chain' => 'kmart']), 'unknown chain'],
            'lane registry lacks'        => [fn (array $c) => self::lane($c, ['source_category' => 'bakery']), 'declares no source-category rescue'],
            'lane wrong target'          => [fn (array $c) => self::lane($c, ['as_category' => 'pharmacy']), 'disagrees with the registry'],
            'lane on imported token'     => [fn (array $c) => self::lane($c, ['source_category' => 'pharmacy']), 'already imported'],
            'lane on canonical key'      => [fn (array $c) => self::lane($c, ['source_category' => 'shopping_center']), 'already imported'],
            'lane weak identity'         => [fn (array $c) => self::lane($c, ['identity' => 'brand']), "'strong'"],
            'lane always materialized'   => [fn (array $c) => self::lane($c, ['materialization' => 'always']), 'eligible_if_matched'],
            'lane unknown field'         => [fn (array $c) => self::lane($c, ['loose' => true]), "unknown key 'loose'"],
            'lane key malformed'         => [function (array $c) { $c['supplementary']['rescue_lanes'] = ['CVS' => $c['supplementary']['rescue_lanes']['cvs_shopping']]; return $c; }, 'lane key'],
            'lane claimed twice'         => [function (array $c) { $c['supplementary']['rescue_lanes']['cvs_shopping_2'] = $c['supplementary']['rescue_lanes']['cvs_shopping']; return $c; }, 'already claimed'],
            'registry rescue unclaimed'  => [function (array $c) { $c['supplementary']['rescue_lanes'] = []; return $c; }, 'no rescue lane claims it'],
            'lanes as a list'            => [function (array $c) { $c['supplementary']['rescue_lanes'] = [$c['supplementary']['rescue_lanes']['cvs_shopping']]; return $c; }, 'map of lane key'],
        ];
    }

    /** @dataProvider brokenConfigs */
    public function test_a_broken_recipe_is_refused(callable $break, string $fragment): void
    {
        try {
            $this->load($break($this->config));
            $this->fail('expected InvalidOvertureExtractV2');
        } catch (InvalidOvertureExtractV2 $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
    }

    public function test_a_registry_without_the_rescue_refuses_the_lane(): void
    {
        $registry = require __DIR__ . '/../../../../config/poi_chain_registry.php';
        unset($registry['chains']['cvs']['source_category_rescues']);
        $this->expectException(InvalidOvertureExtractV2::class);
        $this->expectExceptionMessage('declares no source-category rescue');
        OvertureExtractV2Config::fromArray($this->config, ChainRegistry::fromArray($registry));
    }

    private static function patterns(array $c, array $patterns): array
    {
        $c['supplementary']['diagnostic_selector']['name_brand_patterns'] = $patterns;

        return $c;
    }

    private static function lane(array $c, array $over): array
    {
        $c['supplementary']['rescue_lanes']['cvs_shopping'] = array_replace($c['supplementary']['rescue_lanes']['cvs_shopping'], $over);

        return $c;
    }
}
