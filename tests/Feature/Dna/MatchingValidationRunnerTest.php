<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\Validation\MatchingValidationRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 C6.1 — the read-only validation runner: proves the harness is SAFE
 * (read-only row counts, transient force-enable flag), that its compliance
 * evaluators have TEETH (a crafted senior tuple is flagged; an open one is not),
 * and that the no-DNA / determinism scenarios behave as scoped.
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md §8
 */
class MatchingValidationRunnerTest extends TestCase
{
    use DatabaseTransactions;

    // Non-eligible buyer, senior seller, open seller, open landlord — a corpus
    // spanning both directions and both property types.
    private const BUYER = 980001;   // leasing_55_plus = No  (non-eligible seeker)
    private const SELLER_SENIOR = 980002;   // leasing_55_plus = Yes (senior-restricted)
    private const SELLER_OPEN = 980003;   // leasing_55_plus = No
    private const LANDLORD = 980004;

    protected function setUp(): void
    {
        parent::setUp();
        // The runner owns the force-enable; start from the production default (OFF)
        // so we can prove it is restored to exactly that afterwards.
        config(['matching.v2_enabled' => false]);
    }

    private function seedCorpus(): void
    {
        $this->subjectBuyer(self::BUYER, 80, ['leasing_55_plus' => 'No']);
        $this->seller(self::SELLER_SENIOR, 100, ['leasing_55_plus' => 'Yes']);
        $this->seller(self::SELLER_OPEN, 90, ['leasing_55_plus' => 'No']);
        $this->landlord(self::LANDLORD, 60);
    }

    private function score(string $type, int $id, string $side, int $value, string $key = 'pet_friendliness'): void
    {
        DnaScore::create([
            'listing_type' => $type, 'listing_id' => $id, 'score_key' => $key,
            'side' => $side, 'value' => $value, 'data_completeness' => 100, 'confidence' => 90,
            'explanation' => 'seed', 'version' => 'TEST_V1', 'generator_version' => 'TEST_V1', 'generated_by' => 'system',
        ]);
    }

    private function subjectBuyer(int $id, int $pet, array $meta = []): void
    {
        $a = BuyerAgentAuction::create(['id' => $id, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $a->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $a->saveMeta($k, $v);
        }
        $this->score('buyer_agent', $id, 'demand', $pet);
    }

    private function seller(int $id, int $pet, array $meta = []): void
    {
        $a = SellerAgentAuction::create(['id' => $id, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $a->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $a->saveMeta($k, $v);
        }
        $this->score('seller_agent', $id, 'property', $pet);
    }

    private function landlord(int $id, int $pet): void
    {
        $a = LandlordAgentAuction::create(['id' => $id, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $a->saveMeta('workflow_type', 'offer_listing');
        $this->score('landlord_agent', $id, 'property', $pet);
    }

    /** @return array<string,mixed>|null */
    private function scenario(\App\Services\Dna\Relevance\Validation\ValidationReport $report, string $name): ?array
    {
        foreach ($report->scenarios() as $s) {
            if ($s['scenario'] === $name) {
                return $s;
            }
        }

        return null;
    }

    /** @return array{name:string,severity:string,pass:bool,detail:string}|null */
    private function safety(\App\Services\Dna\Relevance\Validation\ValidationReport $report, string $name): ?array
    {
        foreach ($report->safetyChecks() as $c) {
            if ($c['name'] === $name) {
                return $c;
            }
        }

        return null;
    }

    public function test_whole_run_is_read_only(): void
    {
        $this->seedCorpus();

        $counts = fn () => [
            DnaScore::count(),
            SellerAgentAuction::count(), LandlordAgentAuction::count(), BuyerAgentAuction::count(),
            \DB::table('seller_agent_auction_metas')->count(),
            \DB::table('buyer_agent_auction_metas')->count(),
            \DB::table('landlord_agent_auction_metas')->count(),
        ];
        $before = $counts();

        $report = app(MatchingValidationRunner::class)->run(['limit' => 5]);

        $this->assertSame($before, $counts(), 'the validation run must not create/delete any product-table row');
        $this->assertTrue($this->safety($report, 'read-only')['pass'], 'the read-only safety check must pass');
    }

    public function test_force_enable_flag_is_transient(): void
    {
        $this->seedCorpus();
        $this->assertFalse((bool) config('matching.v2_enabled'));

        $report = app(MatchingValidationRunner::class)->run(['limit' => 5]);

        $this->assertFalse((bool) config('matching.v2_enabled'), 'the in-process force-enable must be restored');
        $this->assertTrue($this->safety($report, 'flag-restored')['pass']);
    }

    public function test_compliance_evaluators_have_teeth_both_directions(): void
    {
        $this->seedCorpus();
        $runner = app(MatchingValidationRunner::class);

        // A senior-restricted listing IS flagged; an open listing is NOT.
        $this->assertNotEmpty(
            $runner->seniorOffenders([['listing_type' => 'seller_agent', 'listing_id' => self::SELLER_SENIOR]]),
            'a senior-restricted listing must be detected as an offender'
        );
        $this->assertSame(
            [],
            $runner->seniorOffenders([['listing_type' => 'seller_agent', 'listing_id' => self::SELLER_OPEN]]),
            'an open listing must not be flagged senior'
        );

        // A non-eligible seeker IS flagged as an offender for a senior listing.
        $this->assertNotEmpty(
            $runner->nonEligibleOffenders([['listing_type' => 'buyer_agent', 'listing_id' => self::BUYER]]),
            'a non-eligible seeker must be detected as an offender'
        );
    }

    public function test_compliance_scenario_passes_on_a_clean_pipeline(): void
    {
        $this->seedCorpus();

        $report = app(MatchingValidationRunner::class)->run(['limit' => 5]);
        $seeker = $this->scenario($report, 'compliance-seeker');

        $this->assertNotNull($seeker, 'the non-eligible buyer must be discovered as a compliance-seeker subject');
        $this->assertFalse($seeker['hard_failed'], 'a correctly-gated pipeline must not leak senior stock');

        // The senior-restricted seller must be absent from the seeker's matches.
        $ids = array_column($seeker['result']['matches'], 'listing_id');
        $this->assertNotContains(self::SELLER_SENIOR, $ids);
    }

    public function test_no_dna_scenario_degrades_without_crashing(): void
    {
        $this->seedCorpus();

        $report = app(MatchingValidationRunner::class)->run(['limit' => 5]);
        $noDna  = $this->scenario($report, 'no-dna');

        $this->assertNotNull($noDna);
        $this->assertSame(0, $noDna['determined'], 'a subject with no scores must have zero determined matches');
        $this->assertFalse($noDna['hard_failed']);
    }

    public function test_determinism_check_passes_on_identical_double_run(): void
    {
        $this->seedCorpus();

        $report = app(MatchingValidationRunner::class)->run(['limit' => 5, 'determinism_sample' => 3]);

        $this->assertTrue($this->safety($report, 'determinism')['pass'], 'identical inputs must yield identical results');
    }

    public function test_mixed_pool_preserves_listing_type_in_written_json(): void
    {
        $this->seedCorpus();

        $report = app(MatchingValidationRunner::class)->run(['limit' => 5]);
        $mixed  = $this->scenario($report, 'mixed-pool');

        $this->assertNotNull($mixed);
        foreach ($mixed['result']['matches'] as $m) {
            $this->assertContains($m['listing_type'], ['seller_agent', 'landlord_agent'],
                'every match of a demand subject must carry a correct property-side type');
        }
    }
}
