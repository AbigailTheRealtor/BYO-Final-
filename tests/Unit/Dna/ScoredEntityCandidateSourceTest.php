<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\CandidateQuery;
use App\Services\Dna\Relevance\ScoredEntityCandidateSource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 2 (Candidate Discovery), Stage A.
 *
 * ScoredEntityCandidateSource resolves the provider-agnostic candidate universe
 * from the unified dna_scores layer: distinct (listing_type, listing_id) on the
 * requested side, self excluded, deterministically ordered, hard-capped, with
 * truthful truncation reporting. Pure read-only.
 */
class ScoredEntityCandidateSourceTest extends TestCase
{
    use DatabaseTransactions;

    private function seedScore(string $type, int $id, string $side, string $key = 'pet_friendliness', int $value = 50): void
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

    private function query(
        string $side,
        array $allowed = [],
        ?string $excludeType = null,
        ?int $excludeId = null,
        int $cap = 200,
    ): CandidateQuery {
        return new CandidateQuery($side, $allowed, $excludeType, $excludeId, $cap);
    }

    public function test_returns_only_subjects_on_the_requested_side(): void
    {
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('landlord_agent', 102, 'property');
        $this->seedScore('buyer_agent', 201, 'demand');   // wrong side for a property query

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property'));

        $this->assertEqualsCanonicalizing([
            ['listing_type' => 'landlord_agent', 'listing_id' => 102],
            ['listing_type' => 'seller_agent', 'listing_id' => 101],
        ], $set->toArray());
    }

    public function test_collapses_multiple_score_rows_to_one_distinct_subject(): void
    {
        // Same subject, several score_keys → still one candidate tuple.
        $this->seedScore('seller_agent', 101, 'property', 'pet_friendliness');
        $this->seedScore('seller_agent', 101, 'property', 'waterfront_lifestyle');
        $this->seedScore('seller_agent', 101, 'property', 'lock_and_leave');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property'));

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 101],
        ], $set->toArray());
    }

    public function test_excludes_the_subject_itself(): void
    {
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('seller_agent', 102, 'property');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', excludeType: 'seller_agent', excludeId: 101));

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 102],
        ], $set->toArray());
    }

    public function test_exclusion_matches_full_tuple_not_id_alone(): void
    {
        // Same id, different type must NOT be excluded (ids collide across tables).
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('landlord_agent', 101, 'property');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', excludeType: 'seller_agent', excludeId: 101));

        $this->assertSame([
            ['listing_type' => 'landlord_agent', 'listing_id' => 101],
        ], $set->toArray());
    }

    public function test_empty_allowlist_returns_all_types_provider_agnostic(): void
    {
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('landlord_agent', 102, 'property');
        $this->seedScore('some_future_provider', 103, 'property');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', allowed: []));

        $this->assertCount(3, $set->toArray());
    }

    public function test_non_empty_allowlist_scopes_to_those_types(): void
    {
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('landlord_agent', 102, 'property');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', allowed: ['seller_agent']));

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 101],
        ], $set->toArray());
    }

    public function test_cap_truncates_and_reports_truncation(): void
    {
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('seller_agent', 102, 'property');
        $this->seedScore('seller_agent', 103, 'property');

        $capped = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', cap: 2));
        $this->assertCount(2, $capped->toArray());
        $this->assertTrue($capped->wasTruncated());

        $exact = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', cap: 3));
        $this->assertCount(3, $exact->toArray());
        $this->assertFalse($exact->wasTruncated());

        $roomy = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property', cap: 10));
        $this->assertCount(3, $roomy->toArray());
        $this->assertFalse($roomy->wasTruncated());
    }

    public function test_ordering_is_deterministic(): void
    {
        // Seeded out of order; expect (listing_type ASC, listing_id ASC).
        $this->seedScore('seller_agent', 103, 'property');
        $this->seedScore('landlord_agent', 200, 'property');
        $this->seedScore('seller_agent', 101, 'property');
        $this->seedScore('landlord_agent', 199, 'property');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property'));

        $this->assertSame([
            ['listing_type' => 'landlord_agent', 'listing_id' => 199],
            ['listing_type' => 'landlord_agent', 'listing_id' => 200],
            ['listing_type' => 'seller_agent', 'listing_id' => 101],
            ['listing_type' => 'seller_agent', 'listing_id' => 103],
        ], $set->toArray());
    }

    public function test_empty_when_no_subjects_on_that_side(): void
    {
        $this->seedScore('buyer_agent', 201, 'demand');

        $set = app(ScoredEntityCandidateSource::class)
            ->resolve($this->query('property'));

        $this->assertTrue($set->isEmpty());
        $this->assertSame([], $set->toArray());
        $this->assertFalse($set->wasTruncated());
    }
}
