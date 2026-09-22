<?php

namespace Tests\Unit\Spatial\ChainRegistry;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\ChainRegistry\ChainRole;
use App\Services\Spatial\ChainRegistry\UnknownChainKey;
use App\Services\Spatial\OvertureTaxonomyMapV2;
use PHPUnit\Framework\TestCase;

/**
 * The shipped registry, config/poi_chain_registry.php, against the approved design and the
 * locked decisions of 2026-09-22. Pure; no container.
 */
class ChainRegistryConfigTest extends TestCase
{
    /**
     * registry_version => rule hash. Editing any identity-affecting rule changes the hash and
     * fails this test until the registry version is bumped and a new pin is added here — which
     * is also what invalidates every chain's validation record.
     */
    private const RULE_HASH_PINS = [
        'chain-registry-v1' => '09a1c24c0ace9a71a296ca7825bf7d571f647513cac2cba545d700e7439c61d9',
    ];

    private const APPROVED_CHAINS = [
        'aldi', 'burger_king', 'chick_fil_a', 'cvs', 'mcdonalds', 'publix', 'racetrac', 'seven_eleven',
        'shell', 'speedway', 'starbucks', 'taco_bell', 'target', 'trader_joes', 'walgreens', 'walmart',
        'wawa', 'wendys', 'whole_foods', 'winn_dixie',
    ];

    /** Measured own QIDs, v2 design §6, verbatim. */
    private const MEASURED_OWN_QIDS = [
        'publix' => ['Q672170'], 'starbucks' => ['Q37158'], 'walgreens' => ['Q1591889'],
        'cvs' => ['Q2078880'], 'walmart' => [], 'target' => ['Q1046951'], 'aldi' => ['Q41171672'],
        'winn_dixie' => ['Q1264366'], 'whole_foods' => [], 'trader_joes' => ['Q688825'],
        'seven_eleven' => ['Q259340'], 'wawa' => ['Q5936320'], 'racetrac' => ['Q735942'],
        'speedway' => [], 'shell' => ['Q110716465'], 'mcdonalds' => ['Q38076'],
        'taco_bell' => ['Q752941'], 'chick_fil_a' => ['Q491516'], 'wendys' => ['Q550258'],
        'burger_king' => ['Q177054'],
    ];

    private const MEASURED_EXCLUSION_QIDS = [
        'Q38928', 'Q4835981', 'Q5835668', 'Q59773555', 'Q7271456', 'Q857063', 'Q861042',
    ];

    private ChainRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = ChainRegistry::fromArray(require __DIR__ . '/../../../../config/poi_chain_registry.php');
    }

    public function test_versions_are_independent_contracts(): void
    {
        $this->assertSame('chain-registry-v1', $this->registry->registryVersion());
        $this->assertSame('chain-name-norm-v1', $this->registry->normalizerVersion());
        $this->assertSame(OvertureTaxonomyMapV2::VERSION, $this->registry->taxonomyMapVersion());
        $this->assertStringNotContainsString('2026', $this->registry->registryVersion(), 'not tied to an Overture release');
        $this->assertStringNotContainsString('overture', $this->registry->registryVersion(), 'not tied to a corpus version');
    }

    public function test_exactly_the_approved_twenty_chains(): void
    {
        $this->assertSame(self::APPROVED_CHAINS, $this->registry->chainKeys());
    }

    public function test_load_reads_the_same_file_without_a_container(): void
    {
        $this->assertSame($this->registry->ruleHash(), ChainRegistry::load()->ruleHash());
    }

    public function test_rule_hash_is_pinned_to_the_registry_version(): void
    {
        $version = $this->registry->registryVersion();
        $this->assertArrayHasKey($version, self::RULE_HASH_PINS, "add a rule-hash pin for {$version}");
        $this->assertSame(
            self::RULE_HASH_PINS[$version],
            $this->registry->ruleHash(),
            'An identity-affecting rule changed. Bump registry_version and pin the new hash; every chain returns to provisional.'
        );
    }

    public function test_own_qids_are_exactly_the_measured_ones(): void
    {
        foreach (self::MEASURED_OWN_QIDS as $key => $qids) {
            $this->assertSame($qids, $this->registry->chain($key)->ownWikidataIds, $key);
        }
    }

    public function test_global_exclusion_qids_are_exactly_the_measured_ones(): void
    {
        $this->assertSame(self::MEASURED_EXCLUSION_QIDS, $this->registry->exclusionWikidataIds());
    }

    public function test_fuel_brand_ids_are_a_separate_catalogue(): void
    {
        $fuel = $this->registry->fuelBrands();
        $this->assertSame(['arco', 'mobil'], array_keys($fuel));
        $this->assertSame(['Q109676002'], $fuel['mobil']['wikidata_ids']);
        $this->assertSame(['Q304769'], $fuel['arco']['wikidata_ids']);

        foreach ($this->registry->chains() as $key => $chain) {
            foreach (array_merge($fuel['mobil']['wikidata_ids'], $fuel['arco']['wikidata_ids']) as $fq) {
                $this->assertNotContains($fq, $chain->ownWikidataIds, $key);
                $this->assertNotContains($fq, $chain->departmentWikidataIds, $key);
            }
        }
        $this->assertSame(['mobil'], $this->registry->chain('seven_eleven')->fuelBrands);
        $this->assertSame([], $this->registry->chain('shell')->fuelBrands);
    }

    public function test_every_chain_is_provisional(): void
    {
        foreach ($this->registry->chains() as $key => $chain) {
            $this->assertSame(ChainRegistry::STATUS_PROVISIONAL, $chain->validationStatus, $key);
        }
    }

    public function test_unknown_chain_key_is_refused_not_answered(): void
    {
        $this->expectException(UnknownChainKey::class);
        $this->registry->chain('greenwise');
    }

    // ── Locked decisions (2026-09-22) ────────────────────────────────────────────────────────

    public function test_decision_1_shopping_center_is_never_identity(): void
    {
        $this->assertContains('shopping_center', $this->registry->globalExcludedCategories());
        foreach ($this->registry->chains() as $key => $chain) {
            $this->assertArrayNotHasKey('shopping_center', $chain->allowedCategories, $key);
        }
    }

    public function test_decision_3_mcdonalds_not_identified_from_coffee_or_cafe(): void
    {
        $mcd = $this->registry->chain('mcdonalds');
        $this->assertSame(['burger_restaurant', 'fast_food_restaurant'], array_keys($mcd->allowedCategories));
        $this->assertContains('coffee_shop', $mcd->excludedCategories);
        $this->assertContains('cafe', $mcd->excludedCategories);
    }

    public function test_decision_4_no_generic_restaurant_for_fast_food_or_starbucks(): void
    {
        foreach (['mcdonalds', 'taco_bell', 'chick_fil_a', 'wendys', 'burger_king', 'starbucks'] as $key) {
            $chain = $this->registry->chain($key);
            $this->assertArrayNotHasKey('restaurant', $chain->allowedCategories, $key);
            $this->assertContains('restaurant', $chain->excludedCategories, $key);
        }
        foreach ($this->registry->chains() as $key => $chain) {
            $this->assertArrayNotHasKey('restaurant', $chain->allowedCategories, $key);
        }
    }

    public function test_decision_6_only_seven_eleven_permits_fuel_only(): void
    {
        foreach ($this->registry->chains() as $key => $chain) {
            $expected = $key === 'seven_eleven';
            $this->assertSame($expected, ($chain->fuelSites['fuel_only'] ?? false) === true, $key);
        }
        foreach (['wawa', 'racetrac', 'speedway', 'seven_eleven'] as $key) {
            $this->assertTrue($this->registry->chain($key)->fuelSites['store_with_fuel'], $key);
        }
    }

    public function test_decision_7_walmart_has_no_fuel(): void
    {
        $walmart = $this->registry->chain('walmart');
        $this->assertArrayNotHasKey('gas_station', $walmart->allowedCategories);
        $this->assertNotContains(ChainRole::FUEL, $walmart->allowedCategories);
        $this->assertNull($walmart->fuelSites);
    }

    public function test_decision_9_no_unmeasured_sub_brand_aliases(): void
    {
        foreach ($this->registry->chains() as $key => $chain) {
            $strings = array_merge(array_column($chain->aliases, 'value'), $chain->brandAliases);
            foreach ($strings as $s) {
                $this->assertStringNotContainsString('greenwise', $s, $key);
                $this->assertStringNotContainsString('licensed', $s, $key);
                $this->assertStringNotContainsString('mccafe', $s, $key);
                $this->assertStringNotContainsString('supercenter', $s, $key);
                $this->assertStringNotContainsString('neighborhood market', $s, $key);
            }
        }
    }

    public function test_decision_10_store_with_fuel_is_not_a_visible_label(): void
    {
        foreach ($this->registry->chains() as $key => $chain) {
            foreach ($chain->formats as $format) {
                $this->assertStringNotContainsStringIgnoringCase('fuel', (string) $format->label, "{$key}.{$format->key}");
            }
        }
    }

    public function test_decision_11_cvs_shopping_not_rescued(): void
    {
        $this->assertArrayNotHasKey('shopping', $this->registry->chain('cvs')->allowedCategories);
        $this->assertSame(['drugstore', 'pharmacy'], array_keys($this->registry->chain('cvs')->allowedCategories));
    }

    public function test_ordinary_word_chains_are_exact_only(): void
    {
        foreach (['target', 'shell', 'speedway'] as $key) {
            foreach ($this->registry->chain($key)->aliases as $alias) {
                $this->assertSame(ChainRegistry::MATCH_EXACT, $alias['match'], $key);
            }
        }
    }

    public function test_seven_eleven_speedway_co_brand_is_symmetric_and_the_only_one(): void
    {
        $pairs = [];
        foreach ($this->registry->chains() as $key => $chain) {
            foreach (array_keys($chain->coBrands) as $partner) {
                $pairs[] = "{$key}/{$partner}";
            }
        }
        $this->assertSame(['seven_eleven/speedway', 'speedway/seven_eleven'], $pairs);
    }

    // ── Rule hash sensitivity ────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: callable(array): array}> */
    public static function identityAffectingEdits(): array
    {
        return [
            'alias added'            => [static function (array $c): array { $c['chains']['aldi']['aliases'][] = ['value' => 'aldi market', 'match' => 'prefix', 'evidence' => 'P']; return $c; }],
            'alias match mode'       => [static function (array $c): array { $c['chains']['aldi']['aliases'][0]['match'] = 'exact'; return $c; }],
            'brand alias'            => [static function (array $c): array { $c['chains']['aldi']['brand_aliases'][] = ['value' => 'aldi sud', 'evidence' => 'P']; return $c; }],
            'own QID'                => [static function (array $c): array { $c['chains']['walmart']['own_wikidata_ids']['Q483551'] = 'P'; return $c; }],
            'global exclusion QID'   => [static function (array $c): array { $c['global']['exclusion_wikidata_ids']['Q1'] = ['label' => 'x', 'evidence' => 'P']; return $c; }],
            'global pattern'         => [static function (array $c): array { $c['global']['exclusion_name_patterns']['kiosk'] = ['pattern' => '/\bkiosk\b/', 'evidence' => 'P']; return $c; }],
            'chain pattern'          => [static function (array $c): array { $c['chains']['cvs']['exclusion_name_patterns']['photo'] = ['pattern' => '/\bphoto\b/', 'evidence' => 'P']; return $c; }],
            'allowed category'       => [static function (array $c): array { $c['chains']['aldi']['allowed_categories']['convenience_store'] = ['role' => 'storefront', 'evidence' => 'P']; $c['chains']['aldi']['formats']['store']['categories'][] = 'convenience_store'; return $c; }],
            'category role'          => [static function (array $c): array { $c['chains']['publix']['allowed_categories']['pharmacy']['role'] = 'storefront'; $c['chains']['publix']['formats']['pharmacy_department']['role'] = 'storefront'; return $c; }],
            'excluded category'      => [static function (array $c): array { $c['chains']['aldi']['excluded_categories'] = ['restaurant' => 'P']; return $c; }],
            'format pattern'         => [static function (array $c): array { $c['chains']['walmart']['formats']['supercenter']['name_patterns'] = ['/^super center\b/']; return $c; }],
            'fuel expected brand'    => [static function (array $c): array { $c['chains']['wawa']['fuel_brands'] = ['mobil']; return $c; }],
            'fuel sites'             => [static function (array $c): array { $c['chains']['wawa']['fuel_sites']['fuel_only'] = true; return $c; }],
            'co-brand compound'      => [static function (array $c): array { foreach (['seven_eleven' => 'speedway', 'speedway' => 'seven_eleven'] as $a => $b) { $c['chains'][$a]['co_brands'][$b]['compound_names'][] = '7 eleven and speedway'; } return $c; }],
        ];
    }

    /** @dataProvider identityAffectingEdits */
    public function test_identity_affecting_edits_change_every_chain_hash(callable $edit): void
    {
        $config = require __DIR__ . '/../../../../config/poi_chain_registry.php';
        $edited = ChainRegistry::fromArray($edit($config));

        $this->assertNotSame($this->registry->ruleHash(), $edited->ruleHash());
        foreach ($this->registry->chainKeys() as $key) {
            $this->assertNotSame($this->registry->chainRuleHash($key), $edited->chainRuleHash($key), $key);
        }
    }

    public function test_presentation_edits_do_not_change_the_hash(): void
    {
        $config = require __DIR__ . '/../../../../config/poi_chain_registry.php';
        $config['chains']['walmart']['display_name'] = 'Walmart Inc.';
        $config['chains']['walmart']['notes'][] = 'another note';
        $config['chains']['walmart']['formats']['supercenter']['label'] = 'Super Center';
        $config['chains']['aldi']['aliases'][0]['evidence'] = 'M: re-measured';

        $this->assertSame($this->registry->ruleHash(), ChainRegistry::fromArray($config)->ruleHash());
    }

    public function test_reordered_config_has_the_same_hash(): void
    {
        $config = require __DIR__ . '/../../../../config/poi_chain_registry.php';
        $config['chains'] = array_reverse($config['chains'], true);
        $config['global']['exclusion_name_patterns'] = array_reverse($config['global']['exclusion_name_patterns'], true);
        $config['chains']['seven_eleven']['aliases'] = array_reverse($config['chains']['seven_eleven']['aliases']);
        $config['chains']['walmart']['formats'] = array_reverse($config['chains']['walmart']['formats'], true);

        $this->assertSame($this->registry->ruleHash(), ChainRegistry::fromArray($config)->ruleHash());
    }

    public function test_chain_hashes_differ_per_chain(): void
    {
        $hashes = array_map(fn (string $k): string => $this->registry->chainRuleHash($k), $this->registry->chainKeys());
        $this->assertCount(20, array_unique($hashes));
    }
}
