<?php

namespace Tests\Unit\Stellar;

use App\Models\BridgeProperty;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Tests\TestCase;

/**
 * Phase 4 · git-C11 (Plan-C7, F3/F4) — BuyerResultViewMapper::mapOneDetailed().
 *
 * Verifies the detailed mapper: it is a superset of mapOne()'s compliance-safe scalars, keeps the
 * explanation detail mapOne strips (fields_used / deviation), surfaces the git-C10 blocks, renders
 * every contributing category including non_residential with a reconciled total (F4), leaks no
 * restricted keys, and leaves mapOne() itself unchanged.
 */
class BuyerResultViewMapperDetailedTest extends TestCase
{
    private function mapper(): BuyerResultViewMapper
    {
        return new BuyerResultViewMapper();
    }

    private function listing(array $attrs = [], ?array $rawJson = null): BridgeProperty
    {
        $p = new BridgeProperty();
        foreach ($attrs as $k => $v) {
            $p->{$k} = $v;
        }
        if ($rawJson !== null) {
            $p->raw_json = json_encode($rawJson);
        }
        return $p;
    }

    private function result(array $categoryScores, int $totalScore, ?BridgeProperty $listing = null): BuyerMatchResult
    {
        return new BuyerMatchResult(
            'KEY-1',
            $totalScore,
            $categoryScores,
            $listing ?? $this->listing(['list_price' => 400000, 'city' => 'Tampa', 'state_or_province' => 'FL'])
        );
    }

    /** Recursively collect every key and scalar string value in a nested array. */
    private function flatten(array $data): array
    {
        $out = [];
        array_walk_recursive($data, function ($value, $key) use (&$out) {
            $out[] = (string) $key;
            if (is_string($value)) {
                $out[] = $value;
            }
        });
        return $out;
    }

    /** @test */
    public function map_one_detailed_is_a_superset_of_map_one_safe_scalars(): void
    {
        $result   = $this->result(['location' => 20, 'price' => 15], 35);
        $mapper   = $this->mapper();
        $base     = $mapper->mapOne($result);
        $detailed = $mapper->mapOneDetailed($result);

        foreach (['listing_key', 'total_score', 'score_display', 'price_display', 'address',
                  'city_state_zip', 'beds', 'baths', 'sqft', 'property_type', 'hero_photo_url'] as $key) {
            $this->assertArrayHasKey($key, $detailed);
            $this->assertSame($base[$key], $detailed[$key], "scalar {$key} should match mapOne");
        }
    }

    /** @test */
    public function detailed_why_and_tradeoff_blocks_retain_fields_used_and_deviation(): void
    {
        $result = $this->result(['location' => 20, 'price' => 0], 20);
        $result->whyThisMatches = [
            ['dimension' => 'location', 'label' => 'In your area', 'fields_used' => ['city'], 'score_contribution' => 20],
        ];
        $result->tradeoffs = [
            ['dimension' => 'price', 'label' => 'Above ideal', 'fields_used' => ['list_price'], 'deviation' => '12%_above_ideal'],
        ];

        $mapper   = $this->mapper();
        $detailed = $mapper->mapOneDetailed($result);
        $card     = $mapper->mapOne($result);

        // Detailed KEEPS fields_used + deviation...
        $this->assertSame(['city'], $detailed['why_this_matches'][0]['fields_used']);
        $this->assertSame('location', $detailed['why_this_matches'][0]['dimension']);
        $this->assertSame('12%_above_ideal', $detailed['tradeoffs'][0]['deviation']);
        $this->assertSame(['list_price'], $detailed['tradeoffs'][0]['fields_used']);

        // ...while the card (mapOne) still strips them.
        $this->assertArrayNotHasKey('fields_used', $card['why_this_matches'][0]);
        $this->assertArrayNotHasKey('deviation', $card['tradeoffs'][0]);
    }

    /** @test */
    public function detailed_surfaces_the_git_c10_blocks(): void
    {
        $result = $this->result(['location' => 0, 'price' => 15], 15);
        $result->whyNot = [
            ['dimension' => 'location', 'label' => 'Outside your areas', 'fields_used' => ['city'], 'score_contribution' => 0],
        ];
        $result->confidence = ['level' => 'high', 'score' => 0.9, 'factors' => ['geo_precise' => true, 'completeness' => 1.0]];
        $result->recommendations = [
            ['type' => 'consider_adjacent_area', 'dimension' => 'location', 'label' => 'Consider nearby areas'],
        ];

        $detailed = $this->mapper()->mapOneDetailed($result);

        $this->assertSame('location', $detailed['why_not'][0]['dimension']);
        $this->assertSame(['city'], $detailed['why_not'][0]['fields_used']);
        $this->assertSame('high', $detailed['confidence']['level']);
        $this->assertSame(['geo_precise' => true, 'completeness' => 1.0], $detailed['confidence']['factors']);
        $this->assertSame('consider_adjacent_area', $detailed['recommendations'][0]['type']);
    }

    /** @test */
    public function null_git_c10_slots_are_handled_gracefully(): void
    {
        // A result that only went through build() (not buildDetailed()) leaves the slots null.
        $result   = $this->result(['location' => 20], 20);
        $detailed = $this->mapper()->mapOneDetailed($result);

        $this->assertSame([], $detailed['why_not']);
        $this->assertNull($detailed['confidence']);
        $this->assertSame([], $detailed['recommendations']);
    }

    /** @test */
    public function non_residential_category_appears_and_reconciles(): void
    {
        $listing = $this->listing(['list_price' => 850000, 'property_type' => 'Commercial Sale']);
        // location 20 + price 15 + non_residential 8 = 43 contributed; total intentionally 44 to
        // exercise the rounding adjustment.
        $result  = $this->result(
            ['location' => 20, 'price' => 15, 'size' => 0, 'property_type' => 0,
             'amenities' => 0, 'financial' => 0, 'lifestyle' => 0, 'non_residential' => 8],
            44,
            $listing
        );

        $detailed = $this->mapper()->mapOneDetailed($result);

        $keys = array_column($detailed['category_bars'], 'key');
        $this->assertContains('non_residential', $keys);
        $this->assertNotContains('size', $keys); // zero-scoring → excluded

        $nonRes = collect($detailed['category_bars'])->firstWhere('key', 'non_residential');
        $this->assertSame('Property Fit', $nonRes['label']);
        $this->assertSame(8, $nonRes['contributed']);
        $this->assertSame(10, $nonRes['available']);

        $totals = $detailed['category_totals'];
        $this->assertSame(43, $totals['contributed_sum']);
        $this->assertSame(44, $totals['total_score']);
        $this->assertSame(1, $totals['rounding_adjustment']);
        $this->assertSame(
            $totals['total_score'],
            $totals['contributed_sum'] + $totals['rounding_adjustment']
        );
    }

    /** @test */
    public function contributing_categories_only_are_shown(): void
    {
        $result = $this->result(
            ['location' => 20, 'price' => 0, 'size' => 8, 'non_residential' => 0],
            28
        );

        $detailed = $this->mapper()->mapOneDetailed($result);
        $keys     = array_column($detailed['category_bars'], 'key');

        $this->assertContains('location', $keys);
        $this->assertContains('size', $keys);
        $this->assertNotContains('price', $keys);           // zero
        $this->assertNotContains('non_residential', $keys); // zero
        $this->assertSame($detailed['category_totals']['total_score'],
            $detailed['category_totals']['contributed_sum'] + $detailed['category_totals']['rounding_adjustment']);
    }

    /** @test */
    public function map_one_output_shape_is_unchanged(): void
    {
        $result = $this->result(['location' => 20, 'price' => 15], 35);
        $card   = $this->mapper()->mapOne($result);

        // Original card bar shape (score/max), NOT the detailed shape (contributed/available).
        $this->assertSame(['key', 'label', 'score', 'max', 'pct'], array_keys($card['category_bars'][0]));

        // Detailed-only keys must NOT appear on the card.
        $this->assertArrayNotHasKey('why_not', $card);
        $this->assertArrayNotHasKey('confidence', $card);
        $this->assertArrayNotHasKey('recommendations', $card);
        $this->assertArrayNotHasKey('category_totals', $card);
    }

    /** @test */
    public function detailed_output_leaks_no_restricted_keys_or_pii(): void
    {
        $rawJson = [
            'ListAgentFullName'   => 'Jane Agent',
            'ListAgentEmail'      => 'jane@example.com',
            'ListOfficeName'      => 'Big Brokerage LLC',
            'PublicRemarks'       => 'Secret internal remarks',
            'LockBoxSerialNumber' => 'LB-123',
            'ShowingInstructions' => 'Call listing agent',
            'Media'               => [['Order' => 1, 'MediaURL' => 'https://cdn.example.com/1.jpg']],
        ];
        $listing = $this->listing(
            ['list_price' => 500000, 'property_type' => 'Commercial Sale', 'city' => 'Tampa'],
            $rawJson
        );
        $result = $this->result(['location' => 20, 'non_residential' => 8], 28, $listing);
        $result->whyThisMatches = [['dimension' => 'location', 'label' => 'X', 'fields_used' => ['city'], 'score_contribution' => 20]];

        $detailed  = $this->mapper()->mapOneDetailed($result);
        $flattened = $this->flatten($detailed);

        foreach (['raw_json', 'PublicRemarks', 'public_remarks', 'ListAgentFullName', 'Jane Agent',
                  'jane@example.com', 'ListOfficeName', 'Big Brokerage LLC', 'LockBoxSerialNumber',
                  'LB-123', 'ShowingInstructions', 'Secret internal remarks'] as $forbidden) {
            $this->assertNotContains($forbidden, $flattened, "restricted token leaked: {$forbidden}");
        }

        // The safe hero photo URL is still surfaced (proves Media was read without leaking PII).
        $this->assertSame('https://cdn.example.com/1.jpg', $detailed['hero_photo_url']);
    }
}
