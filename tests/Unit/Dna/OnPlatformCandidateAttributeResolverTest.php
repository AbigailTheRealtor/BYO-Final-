<?php

namespace Tests\Unit\Dna;

use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\OnPlatformCandidateAttributeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B. The on-platform resolver builds provider-neutral
 * profiles from the *_agent auction tables + property_location_dna, batched with
 * no N+1.
 */
class OnPlatformCandidateAttributeResolverTest extends TestCase
{
    use DatabaseTransactions;

    private function seedSeller(int $id, array $attrs = [], array $meta = []): void
    {
        SellerAgentAuction::create(array_merge([
            'id'          => $id,
            'user_id'     => 1,
            'is_approved' => true,
            'is_sold'     => false,
        ], $attrs));

        $auction = SellerAgentAuction::find($id);
        foreach ($meta as $k => $v) {
            $auction->saveMeta($k, $v);
        }
    }

    private function tuples(array $ids): array
    {
        return array_map(fn ($id) => ['listing_type' => 'seller_agent', 'listing_id' => $id], $ids);
    }

    public function test_builds_eligible_profile_with_senior_type_and_geo(): void
    {
        $this->seedSeller(9001, [], [
            'workflow_type'   => 'offer_listing',
            'leasing_55_plus' => 'Yes',
            'property_type'   => 'residential',
        ]);
        PropertyLocationDna::create([
            'listing_type' => 'seller_agent',
            'listing_id'   => 9001,
            'geocoded_lat' => 27.95,
            'geocoded_lng' => -82.46,
            'source_city'  => 'Tampa',
            'source_zip'   => '33602',
        ]);

        $p = app(OnPlatformCandidateAttributeResolver::class)
            ->resolveMany('property', $this->tuples([9001]))['seller_agent:9001'];

        $this->assertTrue($p->isEligibleListing);
        $this->assertTrue($p->age55);
        $this->assertSame('residential', $p->propertyType);
        $this->assertSame(27.95, $p->lat);
        $this->assertSame(-82.46, $p->lng);
        $this->assertSame('Tampa', $p->city);
    }

    public function test_ineligible_when_not_offer_listing_or_sold_or_unapproved(): void
    {
        $this->seedSeller(910001, [], []); // no workflow_type → not a marketplace listing
        $this->seedSeller(910002, ['is_sold' => true], ['workflow_type' => 'offer_listing']);
        $this->seedSeller(910003, ['is_approved' => false], ['workflow_type' => 'offer_listing']);
        $this->seedSeller(910004, [], ['workflow_type' => 'offer_listing']); // eligible

        $profiles = app(OnPlatformCandidateAttributeResolver::class)
            ->resolveMany('property', $this->tuples([910001, 910002, 910003, 910004]));

        $this->assertFalse($profiles['seller_agent:910001']->isEligibleListing);
        $this->assertFalse($profiles['seller_agent:910002']->isEligibleListing);
        $this->assertFalse($profiles['seller_agent:910003']->isEligibleListing);
        $this->assertTrue($profiles['seller_agent:910004']->isEligibleListing);
    }

    public function test_unknown_senior_and_missing_geo_are_null(): void
    {
        $this->seedSeller(9001, [], ['workflow_type' => 'offer_listing']); // no leasing_55_plus, no geo

        $p = app(OnPlatformCandidateAttributeResolver::class)
            ->resolveMany('property', $this->tuples([9001]))['seller_agent:9001'];

        $this->assertNull($p->age55);
        $this->assertNull($p->lat);
        $this->assertNull($p->city);
    }

    public function test_batched_no_n_plus_1(): void
    {
        $ids = range(920001, 920006);
        foreach ($ids as $id) {
            $this->seedSeller($id, [], ['workflow_type' => 'offer_listing']);
        }

        $countQueries = function (array $ids): int {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            app(OnPlatformCandidateAttributeResolver::class)->resolveMany('property', $this->tuples($ids));
            return $n;
        };

        // Query count is independent of candidate count (one type → 3 queries: meta, lifecycle, geo).
        $this->assertSame($countQueries([$ids[0], $ids[1]]), $countQueries($ids));
    }
}
