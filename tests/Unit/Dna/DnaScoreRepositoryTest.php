<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\DnaScoreRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 1. The read-only loader returns exactly the
 * property-side or demand-side dna_scores for a listing, and nothing else.
 */
class DnaScoreRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private function seedScore(string $type, int $id, string $side, string $key, int $value): void
    {
        DnaScore::create([
            'listing_type'      => $type,
            'listing_id'        => $id,
            'score_key'         => $key,
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

    public function test_property_scores_returns_only_property_side_for_that_listing(): void
    {
        $this->seedScore('seller_agent', 4001, 'property', 'pet_friendliness', 80);
        $this->seedScore('seller_agent', 4001, 'property', 'waterfront_lifestyle', 60);
        $this->seedScore('seller_agent', 4001, 'demand', 'pet_friendliness', 70);   // wrong side
        $this->seedScore('seller_agent', 9999, 'property', 'pet_friendliness', 50);  // wrong listing

        $rows = app(DnaScoreRepository::class)->propertyScores('seller_agent', 4001);

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertInstanceOf(DnaScore::class, $row);
            $this->assertSame('property', $row->side);
            $this->assertSame(4001, (int) $row->listing_id);
        }
    }

    public function test_demand_scores_returns_only_demand_side_for_that_listing(): void
    {
        $this->seedScore('buyer_agent', 5001, 'demand', 'pet_friendliness', 75);
        $this->seedScore('buyer_agent', 5001, 'property', 'pet_friendliness', 40); // wrong side

        $rows = app(DnaScoreRepository::class)->demandScores('buyer_agent', 5001);

        $this->assertCount(1, $rows);
        $this->assertSame('demand', $rows[0]->side);
        $this->assertSame('pet_friendliness', $rows[0]->score_key);
    }

    public function test_returns_empty_array_when_no_scores(): void
    {
        $this->assertSame([], app(DnaScoreRepository::class)->propertyScores('seller_agent', 123456));
        $this->assertSame([], app(DnaScoreRepository::class)->demandScores('tenant_agent', 123456));
    }
}
