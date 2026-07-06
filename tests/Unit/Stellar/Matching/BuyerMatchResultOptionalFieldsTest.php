<?php

namespace Tests\Unit\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Tests\TestCase;

/**
 * Phase 4 · git-C9 (Plan-C5) — additive BuyerMatchResult report slots.
 *
 * Verifies the three new nullable slots (whyNot / confidence / recommendations) are additive and
 * backward-compatible: the pre-git-C9 positional construction still works and leaves them null,
 * and toArray() is UNCHANGED (still exactly the seven pre-existing keys) so the live batch
 * buyer-results path is unaffected.
 */
class BuyerMatchResultOptionalFieldsTest extends TestCase
{
    private function listing(): BridgeProperty
    {
        return new BridgeProperty(['property_type' => 'Residential']);
    }

    /** @test */
    public function pre_git_c9_positional_construction_leaves_new_slots_null(): void
    {
        // Exactly the signature existing callers use — no trailing args.
        $result = new BuyerMatchResult('KEY-9', 88, ['location' => 24], $this->listing());

        $this->assertNull($result->whyNot);
        $this->assertNull($result->confidence);
        $this->assertNull($result->recommendations);
    }

    /** @test */
    public function full_positional_construction_with_existing_blocks_leaves_new_slots_null(): void
    {
        $result = new BuyerMatchResult(
            'KEY-9',
            88,
            ['location' => 24],
            $this->listing(),
            [['dimension' => 'location']], // whyThisMatches
            [['label' => 'Smaller lot']],  // tradeoffs
            [['type' => 'flag']],          // cautionFlags
            [['field' => 'year_built']]    // missingData
        );

        $this->assertNull($result->whyNot);
        $this->assertNull($result->confidence);
        $this->assertNull($result->recommendations);
    }

    /** @test */
    public function new_slots_are_set_when_supplied(): void
    {
        $result = new BuyerMatchResult(
            'KEY-9', 88, ['location' => 24], $this->listing(),
            [], [], [], [],
            [['dimension' => 'price', 'label' => 'Above budget']], // whyNot
            ['level' => 'high'],                                   // confidence
            [['label' => 'Widen price']]                           // recommendations
        );

        $this->assertSame([['dimension' => 'price', 'label' => 'Above budget']], $result->whyNot);
        $this->assertSame(['level' => 'high'], $result->confidence);
        $this->assertSame([['label' => 'Widen price']], $result->recommendations);
    }

    /** @test */
    public function to_array_is_unchanged_no_new_keys_leak(): void
    {
        // Batch-output guard: even with the new slots populated, toArray() must expose only the
        // seven pre-git-C9 keys, so the live buyer-results page output is byte-identical.
        $result = new BuyerMatchResult(
            'KEY-9', 88, ['location' => 24], $this->listing(),
            [], [], [], [],
            [['dimension' => 'price']],   // whyNot (populated — must NOT appear in toArray)
            ['level' => 'high'],          // confidence (populated — must NOT appear)
            [['label' => 'Widen price']]  // recommendations (populated — must NOT appear)
        );

        $this->assertSame([
            'listing_key',
            'total_score',
            'category_scores',
            'why_this_matches',
            'tradeoffs',
            'caution_flags',
            'missing_data',
        ], array_keys($result->toArray()));
    }
}
