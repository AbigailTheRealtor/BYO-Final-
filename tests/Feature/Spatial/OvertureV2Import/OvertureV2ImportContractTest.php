<?php

namespace Tests\Feature\Spatial\OvertureV2Import;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureTaxonomyMapV2;
use App\Services\Spatial\OvertureV2Import\InvalidOvertureV2Import;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportContract;
use Tests\TestCase;

/**
 * config/overture_v2_corpus.php: the one shipped contract is well-formed and pinned to the code
 * that will run the import, every field is validated, and the file has exactly one reader.
 */
class OvertureV2ImportContractTest extends TestCase
{
    private const SHIPPED = 'overture-2026-08-19.0-fl-r2';

    /** @return array<string, mixed> */
    private function shippedConfig(): array
    {
        return require base_path('config/overture_v2_corpus.php');
    }

    private function assertConfigRefused(array $config, string $messagePart, string $version = self::SHIPPED): void
    {
        try {
            OvertureV2ImportContract::fromConfig($config, $version);
            $this->fail("expected a refusal mentioning [{$messagePart}]");
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    public function test_the_shipped_contract_pins_the_reproduced_florida_extraction(): void
    {
        $c = OvertureV2ImportContract::forVersion(self::SHIPPED);

        $this->assertSame(['2026-08-19.0', 'overture-extract-v2', 'overture-taxonomy-v2.0', 'chain-registry-v2'], [$c->sourceRelease, $c->extractRecipeVersion, $c->taxonomyMapVersion, $c->registryVersion]);
        $this->assertSame([52566, 2962, 55528], [$c->baseRows, $c->supplementaryRows, $c->matcherAnalysisRows]);
        $this->assertSame([self::SHIPPED], OvertureV2ImportContract::declaredVersions());
    }

    public function test_the_shipped_contract_agrees_with_the_code_that_will_run_the_import(): void
    {
        $c = OvertureV2ImportContract::forVersion(self::SHIPPED);
        $registry = ChainRegistry::load();

        $this->assertSame(OvertureTaxonomyMapV2::VERSION, $c->taxonomyMapVersion);
        $this->assertSame($registry->registryVersion(), $c->registryVersion);
        $this->assertSame($registry->ruleHash(), $c->registryRuleHash, 'a registry edit must arrive with a new, reviewed contract');
    }

    public function test_the_file_is_read_without_a_container(): void
    {
        $this->assertSame(self::SHIPPED, OvertureV2ImportContract::fromConfig($this->shippedConfig(), self::SHIPPED)->corpusVersion);
    }

    public function test_an_undeclared_corpus_version_is_refused(): void
    {
        $this->assertConfigRefused($this->shippedConfig(), 'no import contract is declared', 'overture-2026-08-19.0-fl-r3');
    }

    public function test_an_unknown_or_missing_key_refuses_the_whole_file(): void
    {
        $extra = $this->shippedConfig();
        $extra['import_contracts'][self::SHIPPED]['skip_census'] = true;
        $this->assertConfigRefused($extra, 'unknown: [skip_census]');

        $missing = $this->shippedConfig();
        unset($missing['import_contracts'][self::SHIPPED]['registry_rule_hash']);
        $this->assertConfigRefused($missing, 'missing: [registry_rule_hash]');

        $top = $this->shippedConfig();
        $top['activate'] = true;
        $this->assertConfigRefused($top, 'unknown: [activate]');
    }

    /** @return array<string, array{0: string, 1: mixed, 2: string}> */
    public function badValues(): array
    {
        return [
            'short hash' => ['registry_rule_hash', 'abc', 'lower-case sha256'],
            'upper-case hash' => ['base_sha256', str_repeat('A', 64), 'lower-case sha256'],
            'string count' => ['base_rows', '52566', 'non-negative integer'],
            'negative count' => ['supplementary_rows', -1, 'non-negative integer'],
            'empty recipe' => ['extract_recipe_version', ' ', 'non-empty string'],
            'arithmetic' => ['matcher_analysis_rows', 55529, 'matcher_analysis_rows must equal'],
            'release not in version' => ['source_release', '2026-07-22.0', 'must name its source release'],
        ];
    }

    /** @dataProvider badValues */
    public function test_a_malformed_value_refuses(string $key, mixed $value, string $messagePart): void
    {
        $config = $this->shippedConfig();
        $config['import_contracts'][self::SHIPPED][$key] = $value;

        $this->assertConfigRefused($config, $messagePart);
    }

    public function test_an_empty_corpus_and_a_malformed_version_are_refused(): void
    {
        $empty = $this->shippedConfig();
        $empty['import_contracts'][self::SHIPPED]['base_rows'] = 0;
        $empty['import_contracts'][self::SHIPPED]['matcher_analysis_rows'] = 2962;
        $this->assertConfigRefused($empty, 'an empty corpus is not a corpus');

        $config = $this->shippedConfig();
        $config['import_contracts']['v2-latest'] = $config['import_contracts'][self::SHIPPED];
        $this->assertConfigRefused($config, 'corpus version must look like');
    }

    public function test_the_config_is_data_with_no_environment_reads(): void
    {
        $code = '';
        foreach (token_get_all((string) file_get_contents(base_path('config/overture_v2_corpus.php'))) as $t) {
            if (! is_array($t) || ! in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= is_array($t) ? $t[1] : $t;
            }
        }
        $this->assertStringNotContainsString('env(', $code);
    }

    public function test_the_contract_class_is_the_only_reader_of_the_config(): void
    {
        $readers = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            // A READ names the key, or the file's path, as a string literal; prose that merely
            // mentions the file (a docblock, an option's help text) is not one.
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
                && preg_match('/overture_v2_corpus(\.php)?[\'"]/', (string) file_get_contents($file->getPathname())) === 1) {
                $readers[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        $this->assertSame(['app/Services/Spatial/OvertureV2Import/OvertureV2ImportContract.php'], $readers);
    }
}
