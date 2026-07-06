<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\Validation\ValidationRosterBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 C6.1 — read-only roster discovery. Proves per-category discovery
 * (compliance subjects first, low-DNA, no-DNA synthetic id) and the pinned
 * --roster file path. PURE read-only: builder issues only SELECTs.
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md §3
 */
class ValidationRosterBuilderTest extends TestCase
{
    use DatabaseTransactions;

    private const BUYER = 982001;   // leasing_55_plus = No (non-eligible)
    private const SELLER_SENIOR = 982002;   // leasing_55_plus = Yes (senior)
    private const SELLER_OPEN = 982003;

    private function score(string $type, int $id, string $side, int $value, string $key = 'pet_friendliness'): void
    {
        DnaScore::create([
            'listing_type' => $type, 'listing_id' => $id, 'score_key' => $key,
            'side' => $side, 'value' => $value, 'data_completeness' => 100, 'confidence' => 90,
            'explanation' => 'seed', 'version' => 'TEST_V1', 'generator_version' => 'TEST_V1', 'generated_by' => 'system',
        ]);
    }

    private function seedCorpus(): void
    {
        $b = BuyerAgentAuction::create(['id' => self::BUYER, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $b->saveMeta('workflow_type', 'offer_listing');
        $b->saveMeta('leasing_55_plus', 'No');
        // three distinct score keys → not low-DNA (the richest demand subject)
        $this->score('buyer_agent', self::BUYER, 'demand', 80, 'pet_friendliness');
        $this->score('buyer_agent', self::BUYER, 'demand', 70, 'price_alignment');
        $this->score('buyer_agent', self::BUYER, 'demand', 60, 'location_fit');

        $senior = SellerAgentAuction::create(['id' => self::SELLER_SENIOR, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $senior->saveMeta('workflow_type', 'offer_listing');
        $senior->saveMeta('leasing_55_plus', 'Yes');
        $this->score('seller_agent', self::SELLER_SENIOR, 'property', 100);

        $open = SellerAgentAuction::create(['id' => self::SELLER_OPEN, 'user_id' => 1, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $open->saveMeta('workflow_type', 'offer_listing');
        $open->saveMeta('leasing_55_plus', 'No');
        $this->score('seller_agent', self::SELLER_OPEN, 'property', 90);
    }

    /** @return array<int,array<string,mixed>> */
    private function entriesFor(array $roster, string $scenario): array
    {
        return array_values(array_filter($roster, fn ($e) => $e['scenario'] === $scenario));
    }

    public function test_discovers_compliance_subjects_first(): void
    {
        $this->seedCorpus();

        $roster = app(ValidationRosterBuilder::class)->build(5);

        $this->assertNotEmpty($roster);
        // Every leading entry until the first non-compliance one must be compliance-*.
        $sawNonCompliance = false;
        foreach ($roster as $e) {
            if (str_starts_with($e['scenario'], 'compliance-')) {
                $this->assertFalse($sawNonCompliance, 'compliance entries must all precede non-compliance ones');
            } else {
                $sawNonCompliance = true;
            }
        }

        // Both compliance directions were discovered from the seeded corpus.
        $this->assertNotEmpty($this->entriesFor($roster, 'compliance-seeker'));
        $this->assertNotEmpty($this->entriesFor($roster, 'compliance-listing'));
    }

    public function test_compliance_seeker_is_the_non_eligible_buyer(): void
    {
        $this->seedCorpus();

        $roster = app(ValidationRosterBuilder::class)->build(5);
        $seeker = $this->entriesFor($roster, 'compliance-seeker');

        $this->assertSame('buyer_agent', $seeker[0]['listing_type']);
        $this->assertSame(self::BUYER, $seeker[0]['listing_id']);

        $listing = $this->entriesFor($roster, 'compliance-listing');
        $this->assertSame(self::SELLER_SENIOR, $listing[0]['listing_id']);
    }

    public function test_no_dna_entry_uses_an_unused_id(): void
    {
        $this->seedCorpus();

        $roster = app(ValidationRosterBuilder::class)->build(5);
        $noDna  = $this->entriesFor($roster, 'no-dna');

        $this->assertNotEmpty($noDna);
        $this->assertSame('buyer_agent', $noDna[0]['listing_type']);
        // No dna_scores row may exist for the synthetic subject.
        $this->assertSame(0, DnaScore::where('listing_type', 'buyer_agent')->where('listing_id', $noDna[0]['listing_id'])->count());
    }

    public function test_low_dna_subjects_have_at_most_two_score_keys(): void
    {
        $this->seedCorpus();
        // A sparse subject (1 score key) that must surface as low-DNA.
        $sparse = BuyerAgentAuction::create(['id' => 982010, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false]);
        $sparse->saveMeta('workflow_type', 'offer_listing');
        $this->score('buyer_agent', 982010, 'demand', 55);

        $roster = app(ValidationRosterBuilder::class)->build(5);
        $low    = $this->entriesFor($roster, 'low-dna');

        $this->assertNotEmpty($low);
        foreach ($low as $e) {
            $keys = DnaScore::where('listing_type', $e['listing_type'])->where('listing_id', $e['listing_id'])->distinct('score_key')->count('score_key');
            $this->assertLessThanOrEqual(2, $keys);
        }
        // The 3-key buyer must NOT appear in low-DNA.
        $this->assertNotContains(self::BUYER, array_column($low, 'listing_id'));
    }

    public function test_from_file_loads_a_pinned_roster_compliance_first(): void
    {
        $path = sys_get_temp_dir() . '/mv-roster-' . uniqid() . '.json';
        file_put_contents($path, json_encode([
            ['scenario' => 'buyer-to-listings', 'listing_type' => 'buyer_agent', 'listing_id' => 1],
            ['scenario' => 'compliance-seeker', 'listing_type' => 'buyer_agent', 'listing_id' => 2],
        ]));

        try {
            $roster = app(ValidationRosterBuilder::class)->fromFile($path);

            $this->assertSame('compliance-seeker', $roster[0]['scenario'], 'pinned rosters are still compliance-first');
            $this->assertSame('buyer-to-listings', $roster[1]['scenario']);
            $this->assertSame('pinned', $roster[0]['note']);
        } finally {
            @unlink($path);
        }
    }

    public function test_from_file_throws_on_missing_file(): void
    {
        $this->expectException(\RuntimeException::class);
        app(ValidationRosterBuilder::class)->fromFile('/no/such/roster.json');
    }
}
