<?php

namespace Tests\Feature\Spatial\OvertureV2Import;

use App\Services\Spatial\ChainRegistry\ChainRegistry;
use App\Services\Spatial\OvertureV2Import\InvalidOvertureV2Import;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportContract;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportGate;
use App\Services\Spatial\OvertureV2Import\OvertureV2ImportPlan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The manifest gate over a REAL extraction of `import_sample.ndjson`. Every refusal here happens in
 * a class that is handed no connection, so it happens before anything could be written; the
 * tampering helpers re-sign the files where a test must get past the checksum gate to the rule it
 * is actually about.
 */
class OvertureV2ImportGateTest extends TestCase
{
    use BuildsOvertureV2Extraction;

    protected function tearDown(): void
    {
        $this->tearDownExtractions();
        parent::tearDown();
    }

    private function validate(string $dir, ?OvertureV2ImportContract $contract = null): OvertureV2ImportPlan
    {
        return (new OvertureV2ImportGate(ChainRegistry::load()))->validate($contract ?? $this->contractFor($dir), $dir);
    }

    private function assertRefused(string $dir, string $messagePart, ?OvertureV2ImportContract $contract = null): void
    {
        try {
            $this->validate($dir, $contract);
            $this->fail("expected a refusal mentioning [{$messagePart}]");
        } catch (InvalidOvertureV2Import $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    public function test_a_valid_extraction_becomes_a_plan_of_materialization_candidates_only(): void
    {
        $plan = $this->validate($this->extractFixture());

        $this->assertSame([5, 5, 10], [$plan->baseRows, $plan->supplementaryRows, $plan->matcherAnalysisRows()]);
        $this->assertSame([2, 2, 1], [$plan->diagnosticRows, $plan->rescueAdmittedRows, $plan->rescueRefusedRows]);
        $this->assertSame(['convenience_store' => 1, 'gas_station' => 1, 'pharmacy' => 2, 'restaurant' => 1], $plan->baseByCategory);

        // Carried places: the 5 base rows and the 2 admitted rescues. Never a matcher_only row.
        $byRef = [];
        foreach ($plan->places as $p) {
            $byRef[$p['source_ref']] = $p;
            $this->assertContains($p['materialization_policy'], ['corpus', 'rescued']);
        }
        ksort($byRef);
        $this->assertSame(['im-01', 'im-02', 'im-03', 'im-04', 'im-05', 'im-06', 'im-07'], array_keys($byRef));
        $this->assertSame(['cvs_shopping', 'cvs', 'drugstore', 'store'], [$byRef['im-06']['rescued_lane'], $byRef['im-06']['rescued_chain'], $byRef['im-06']['rescued_as_category'], $byRef['im-06']['rescued_format']]);
        $this->assertSame('store_in_target', $byRef['im-07']['rescued_format']);
    }

    public function test_the_plan_carries_every_membership_shape_the_schema_must_store(): void
    {
        $m = $this->validate($this->extractFixture())->memberships;

        $this->assertSame(7, array_sum(array_map('count', $m)));
        $this->assertSame(['seven_eleven', 'speedway'], array_column($m['im-02'], 'brand_key'), 'a co-branded place holds two memberships');
        $this->assertSame([['speedway'], ['seven_eleven']], array_column($m['im-02'], 'co_brand_with'));
        $this->assertSame(['department', 'pharmacy_department', 'storefront_unconfirmed'], [$m['im-03'][0]['role'], $m['im-03'][0]['format_key'], $m['im-03'][0]['storefront_status']]);
        $this->assertSame(['fuel', 'fuel', 'fuel'], [$m['im-04'][0]['role'], $m['im-04'][0]['format_key'], $m['im-04'][0]['storefront_status']]);
        $this->assertSame('store_in_target', $m['im-05'][0]['format_key']);
        $this->assertSame('shopping', $m['im-06'][0]['rescued_from_source_category']);
        $this->assertArrayNotHasKey('im-08', $m, 'a refused rescue carries no membership');
        $this->assertArrayNotHasKey('im-09', $m, 'a diagnostic row carries no membership');
    }

    public function test_the_gate_opens_no_database_connection(): void
    {
        $before = array_keys(DB::getConnections());
        $this->validate($this->extractFixture());
        $this->assertSame($before, array_keys(DB::getConnections()));
    }

    /** @return array<string, array{0: string, 1: mixed, 2: string}> */
    public function manifestPins(): array
    {
        return [
            'wrong recipe' => ['recipe.recipe_version', 'overture-extract-v1', 'manifest recipe.recipe_version'],
            'wrong release' => ['recipe.release', '2026-07-22.0', 'manifest recipe.release'],
            'wrong taxonomy version' => ['recipe.taxonomy_map_version', 'overture-taxonomy-v1.0', 'manifest recipe.taxonomy_map_version'],
            'wrong registry version' => ['chain_registry.registry_version', 'chain-registry-v1', 'manifest chain_registry.registry_version'],
            'wrong registry rule hash' => ['chain_registry.rule_hash', str_repeat('0', 64), 'manifest chain_registry.rule_hash'],
            'wrong precedence version' => ['chain_registry.match_precedence_version', 'precedence-v0', 'match_precedence_version'],
            'wrong normalizer version' => ['chain_registry.normalizer_version', 'normalizer-v0', 'normalizer_version'],
            'count mismatch' => ['counts.base_corpus_rows', 6, 'manifest counts.base_corpus_rows'],
            'not fully accounted' => ['counts.fully_accounted', false, 'fully_accounted'],
        ];
    }

    /** @dataProvider manifestPins */
    public function test_a_manifest_that_disagrees_with_the_contract_is_refused(string $path, mixed $value, string $messagePart): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editManifest($dir, function (array &$m) use ($path, $value): void {
            $ref = &$m;
            foreach (explode('.', $path) as $k) {
                $ref = &$ref[$k];
            }
            $ref = $value;
        });

        $this->assertRefused($dir, $messagePart, $contract);
    }

    public function test_a_manifest_missing_a_pin_is_refused(): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editManifest($dir, function (array &$m): void {
            unset($m['chain_registry']['rule_hash']);
        });

        $this->assertRefused($dir, 'manifest has no chain_registry.rule_hash', $contract);
    }

    public function test_a_file_whose_checksum_changed_is_refused(): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        file_put_contents($dir . '/base.ndjson', str_replace('Joe\'s Diner', 'Joe\'s Dinner', (string) file_get_contents($dir . '/base.ndjson')));

        $this->assertRefused($dir, 'base.ndjson sha256', $contract);
    }

    public function test_a_resigned_manifest_still_cannot_move_the_contract_checksums(): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editRow($dir, 'base.ndjson', 'im-01', function (array &$r): void {
            $r['name'] = 'Somebody Else';
        });

        $this->assertRefused($dir, 'manifest outputs.base.ndjson sha256', $contract);
    }

    public function test_a_contract_whose_counts_disagree_with_the_files_is_refused(): void
    {
        $dir = $this->extractFixture();

        $this->assertRefused($dir, 'manifest counts.base_corpus_rows', $this->contractFor($dir, ['base_rows' => 4, 'matcher_analysis_rows' => 9]));
    }

    public function test_the_running_registry_rule_hash_must_match_even_when_manifest_and_contract_agree(): void
    {
        // An extraction produced by a DIFFERENT registry, with a contract written for it: the files
        // are consistent with each other, but this importer would re-derive a different verdict.
        $dir = $this->extractFixture();
        $foreign = str_repeat('a', 64);
        $this->editManifest($dir, function (array &$m) use ($foreign): void {
            $m['chain_registry']['rule_hash'] = $foreign;
        });

        $this->assertRefused($dir, 'the running chain-registry rule hash', $this->contractFor($dir, ['registry_rule_hash' => $foreign]));
    }

    public function test_the_running_taxonomy_version_must_match_even_when_manifest_and_contract_agree(): void
    {
        $dir = $this->extractFixture();
        $this->editManifest($dir, function (array &$m): void {
            $m['recipe']['taxonomy_map_version'] = 'overture-taxonomy-v9.9';
        });

        $this->assertRefused($dir, 'the running taxonomy map version', $this->contractFor($dir, ['taxonomy_map_version' => 'overture-taxonomy-v9.9']));
    }

    public function test_an_unresolved_rescue_verdict_refuses_the_whole_import(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'supplementary.ndjson', 'im-06', function (array &$r): void {
            $r['rescue_verdict'] = 'pending';
        });

        $this->assertRefused($dir, 'unresolved or inconsistent rescue verdict', $this->contractFor($dir));
    }

    public function test_a_diagnostic_row_relabelled_as_materializable_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'supplementary.ndjson', 'im-09', function (array &$r): void {
            $r['materialization_policy'] = 'rescued';
        });

        $this->assertRefused($dir, 'unknown supplementary role or lane set', $this->contractFor($dir));
    }

    public function test_a_refused_rescue_promoted_to_admitted_without_its_lane_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'supplementary.ndjson', 'im-08', function (array &$r): void {
            $r['rescue_verdict'] = 'admitted';
            $r['materialization_policy'] = 'rescued';
        });

        $this->assertRefused($dir, 'admitted rescue disagrees with its lane', $this->contractFor($dir));
    }

    public function test_an_admitted_rescue_whose_recorded_format_the_registry_does_not_reproduce_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'supplementary.ndjson', 'im-06', function (array &$r): void {
            $r['rescued_format'] = 'store_in_target';
        });

        $this->assertRefused($dir, 'does not re-match its recorded chain and format', $this->contractFor($dir));
    }

    public function test_a_base_row_whose_category_does_not_follow_from_its_token_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'base.ndjson', 'im-01', function (array &$r): void {
            $r['category_key'] = 'pharmacy';
        });

        $this->assertRefused($dir, 'category_key does not follow from taxonomy.primary', $this->contractFor($dir));
    }

    public function test_a_duplicate_source_ref_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'base.ndjson', 'im-02', function (array &$r): void {
            $r['source_ref'] = 'im-01';
        });

        $this->assertRefused($dir, 'duplicate source_ref im-01', $this->contractFor($dir));
    }

    public function test_a_point_outside_the_manifest_box_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'base.ndjson', 'im-01', function (array &$r): void {
            $r['lon'] = -95.0;
        });

        $this->assertRefused($dir, 'point outside the manifest box', $this->contractFor($dir));
    }

    public function test_a_census_that_does_not_reconcile_is_refused(): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editManifest($dir, function (array &$m): void {
            $m['matcher_census']['memberships'] = 8;
        });

        $this->assertRefused($dir, 'the chain census does not reconcile: memberships', $contract);
    }

    public function test_a_census_count_the_manifest_omits_or_states_as_text_does_not_reconcile(): void
    {
        // Under loose comparison a missing count (null) equalled a computed 0.
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editManifest($dir, function (array &$m): void {
            unset($m['matcher_census']['ambiguous_rows']);
        });
        $this->assertRefused($dir, 'the chain census does not reconcile: ambiguous_rows', $contract);

        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editManifest($dir, function (array &$m): void {
            $m['matcher_census']['memberships'] = '7';
        });
        $this->assertRefused($dir, 'the chain census does not reconcile: memberships', $contract);

        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        $this->editManifest($dir, function (array &$m): void {
            $m['matcher_census']['rescue_verdicts']['refused'] = '1';
        });
        $this->assertRefused($dir, 'rescue verdicts do not reconcile', $contract);
    }

    public function test_an_untrimmed_source_ref_is_refused(): void
    {
        $dir = $this->extractFixture();
        $this->editRow($dir, 'base.ndjson', 'im-01', function (array &$r): void {
            $r['source_ref'] = 'im-01 ';
        });

        $this->assertRefused($dir, 'missing or untrimmed source_ref', $this->contractFor($dir));
    }

    public function test_only_a_local_directory_is_read(): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);

        $this->assertRefused('file://' . $dir, 'is not a local directory', $contract);
        $this->assertRefused('ftp://example.invalid/extract', 'is not a local directory', $contract);
    }

    public function test_a_missing_file_or_directory_is_refused(): void
    {
        $dir = $this->extractFixture();
        $contract = $this->contractFor($dir);
        unlink($dir . '/supplementary.ndjson');

        $this->assertRefused($dir, 'has no supplementary.ndjson', $contract);
        $this->assertRefused($dir . '/nope', 'does not exist', $contract);
    }
}
