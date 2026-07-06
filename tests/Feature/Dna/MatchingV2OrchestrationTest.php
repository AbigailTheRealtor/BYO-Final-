<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\MatchingV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 C6 — end-to-end orchestration. The facade composes discovery +
 * narrowing/compliance + §F6 ranking and returns a type-preserving result. All
 * read-only.
 */
class MatchingV2OrchestrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);
        config(['matching.candidate_discovery.senior_unknown_policy' => 'open']);
    }

    private function score(string $type, int $id, string $side, int $value): void
    {
        DnaScore::create([
            'listing_type'      => $type,
            'listing_id'        => $id,
            'score_key'         => 'pet_friendliness',
            'side'              => $side,
            'value'             => $value,
            'data_completeness' => 100,
            'confidence'        => 90,
            'explanation'       => 'seed',
            'version'           => 'TEST_V1',
            'generator_version' => 'TEST_V1',
            'generated_by'      => 'system',
        ]);
    }

    private function seller(int $id, int $pet, array $extraMeta = []): void
    {
        $a = SellerAgentAuction::create(['id' => $id, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $a->saveMeta('workflow_type', 'offer_listing');
        foreach ($extraMeta as $k => $v) {
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

    private function subjectBuyer(int $id, ?int $pet, array $extraMeta = []): void
    {
        $a = BuyerAgentAuction::create(['id' => $id, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $a->saveMeta('workflow_type', 'offer_listing');
        foreach ($extraMeta as $k => $v) {
            $a->saveMeta($k, $v);
        }
        if ($pet !== null) {
            $this->score('buyer_agent', $id, 'demand', $pet);
        }
    }

    public function test_preserves_listing_type_across_mixed_pool_best_first(): void
    {
        $this->subjectBuyer(970001, 80);
        $this->seller(970002, 100);   // strong pet alignment
        $this->landlord(970003, 40);  // weak pet alignment

        $result = app(MatchingV2Service::class)->matchForSubject('buyer_agent', 970001);

        $this->assertSame(2, $result->determinedCount());
        $matches = $result->matches();

        // Each match keeps its own listing_type; the strong seller ranks first.
        $this->assertSame('seller_agent', $matches[0]['listing_type']);
        $this->assertSame(970002, $matches[0]['listing_id']);
        $this->assertSame('landlord_agent', $matches[1]['listing_type']);
        $this->assertSame(970003, $matches[1]['listing_id']);
    }

    public function test_compliance_gate_flows_through_the_facade(): void
    {
        // Non-eligible seeker must not receive a senior-restricted listing.
        $this->subjectBuyer(970010, 80, ['leasing_55_plus' => 'No']);
        $this->seller(970011, 100, ['leasing_55_plus' => 'Yes']); // senior-restricted → excluded
        $this->seller(970012, 90, ['leasing_55_plus' => 'No']);   // open → kept

        $ids = array_column(app(MatchingV2Service::class)->matchForSubject('buyer_agent', 970010)->matches(), 'listing_id');

        $this->assertContains(970012, $ids);
        $this->assertNotContains(970011, $ids);
    }

    public function test_subject_without_dna_yields_all_undetermined(): void
    {
        $this->subjectBuyer(970020, null); // no demand DNA
        $this->seller(970021, 100);

        $result = app(MatchingV2Service::class)->matchForSubject('buyer_agent', 970020);

        $this->assertSame(0, $result->determinedCount());
        $this->assertSame(1, $result->candidatesConsidered());
        $this->assertSame(1, $result->undeterminedCount());
        $this->assertTrue($result->isEmpty());
    }

    public function test_disabled_is_inert(): void
    {
        config(['matching.v2_enabled' => false]);
        $this->subjectBuyer(970030, 80);
        $this->seller(970031, 100);

        $result = app(MatchingV2Service::class)->matchForSubject('buyer_agent', 970030);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->candidatesConsidered());
    }

    public function test_orchestration_is_read_only(): void
    {
        $this->subjectBuyer(970040, 80);
        $this->seller(970041, 100);
        $this->landlord(970042, 60);

        $counts = fn () => [DnaScore::count(), SellerAgentAuction::count(), LandlordAgentAuction::count(), BuyerAgentAuction::count()];
        $before = $counts();

        app(MatchingV2Service::class)->matchForSubject('buyer_agent', 970040);

        $this->assertSame($before, $counts());
    }
}
