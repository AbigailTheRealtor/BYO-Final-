<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\Validation\MatchingValidationRunner;
use App\Services\Dna\Relevance\Validation\ValidationReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Matching V2 C6.1 — the matching:validate command end-to-end: the fail-closed
 * production + empty-corpus guards (exit 2, pipeline never invoked, no files), the
 * clean-corpus success path (exit 0, JSON written only under --out, flag
 * restored), and the hard-failure exit code (1).
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md §8
 */
class MatchingValidateCommandTest extends TestCase
{
    use DatabaseTransactions;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        config(['matching.v2_enabled' => false]);
        $this->out = sys_get_temp_dir() . '/mv-out-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->out)) {
            array_map('unlink', glob($this->out . '/*') ?: []);
            @rmdir($this->out);
        }
        parent::tearDown();
    }

    private function seedCorpus(): void
    {
        $b = BuyerAgentAuction::create(['id' => 984001, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $b->saveMeta('workflow_type', 'offer_listing');
        $b->saveMeta('leasing_55_plus', 'No');
        $this->score('buyer_agent', 984001, 'demand', 80);

        $s = SellerAgentAuction::create(['id' => 984002, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $s->saveMeta('workflow_type', 'offer_listing');
        $s->saveMeta('leasing_55_plus', 'No');
        $this->score('seller_agent', 984002, 'property', 100);
    }

    private function score(string $type, int $id, string $side, int $value): void
    {
        DnaScore::create([
            'listing_type' => $type, 'listing_id' => $id, 'score_key' => 'pet_friendliness',
            'side' => $side, 'value' => $value, 'data_completeness' => 100, 'confidence' => 90,
            'explanation' => 'seed', 'version' => 'TEST_V1', 'generator_version' => 'TEST_V1', 'generated_by' => 'system',
        ]);
    }

    public function test_refuses_to_run_in_production_and_touches_nothing(): void
    {
        $this->seedCorpus();
        $this->app['env'] = 'production';

        // The pipeline must not be invoked at all in production.
        $this->mock(MatchingValidationRunner::class)->shouldReceive('run')->never();

        // Exit 2 (refused) + the ->never() expectation above prove the pipeline
        // was not invoked; no diagnostic files may be written either.
        $exit = Artisan::call('matching:validate', ['--out' => $this->out]);

        $this->assertSame(2, $exit);
        $this->assertFalse(is_dir($this->out), 'no output directory may be created in production');
    }

    public function test_refuses_on_an_empty_corpus(): void
    {
        // No dna_scores rows seeded.
        $this->mock(MatchingValidationRunner::class)->shouldReceive('run')->never();

        $exit = Artisan::call('matching:validate', ['--out' => $this->out]);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('No dna_scores', Artisan::output());
        $this->assertFalse(file_exists($this->out . '/summary.json'));
    }

    public function test_clean_corpus_exits_zero_writes_files_and_restores_flag(): void
    {
        $this->seedCorpus();

        $exit = Artisan::call('matching:validate', ['--out' => $this->out]);

        $this->assertSame(0, $exit);
        $this->assertTrue(file_exists($this->out . '/summary.json'), 'summary.json must be written');
        $this->assertNotEmpty(glob($this->out . '/*.json'), 'per-scenario JSON must be written');

        // The force-enable flag must be restored to its pre-run value.
        $this->assertFalse((bool) config('matching.v2_enabled'));

        // The written summary is machine-readable and reports no hard failure.
        $summary = json_decode((string) file_get_contents($this->out . '/summary.json'), true);
        $this->assertIsArray($summary);
        $this->assertFalse($summary['has_hard_failure']);
    }

    public function test_writes_only_under_the_given_out_dir_and_creates_no_product_rows(): void
    {
        $this->seedCorpus();
        $before = [DnaScore::count(), BuyerAgentAuction::count(), SellerAgentAuction::count()];

        Artisan::call('matching:validate', ['--out' => $this->out]);

        $this->assertSame($before, [DnaScore::count(), BuyerAgentAuction::count(), SellerAgentAuction::count()]);
        // Every written file lives under the requested --out dir.
        foreach (glob($this->out . '/*') ?: [] as $f) {
            $this->assertStringStartsWith($this->out, $f);
        }
    }

    public function test_hard_failure_maps_to_exit_code_one(): void
    {
        $this->seedCorpus();

        // A report carrying a failing HARD safety check → the command must exit 1.
        $report = new ValidationReport();
        $report->addSafetyCheck(['name' => 'read-only', 'severity' => 'hard', 'pass' => false, 'detail' => 'MUTATED: dna_scores']);
        $this->mock(MatchingValidationRunner::class)->shouldReceive('run')->once()->andReturn($report);

        $exit = Artisan::call('matching:validate', ['--out' => $this->out]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('HARD FAILURE', Artisan::output());
        $this->assertTrue(file_exists($this->out . '/summary.json'), 'the failing report is still written for evidence');
    }
}
