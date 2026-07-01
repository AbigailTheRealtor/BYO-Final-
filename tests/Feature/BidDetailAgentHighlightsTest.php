<?php

namespace Tests\Feature;

use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerAgentAuctionBidMeta;
use Tests\TestCase;

/**
 * BYA-H4 — Consumer-facing bid detail must render the "Agent Highlights" strip
 * from the values SUBMITTED WITH THE BID (bid meta), not from the agent's
 * current AgentDefaultProfile, which drifts after the bid is placed and would
 * otherwise show consumers stale figures.
 *
 * These tests pin the source of truth the three bid_detail_body partials use:
 *   - resources/views/partials/bid_detail_body/buyer.blade.php
 *   - resources/views/partials/bid_detail_body/seller.blade.php
 *   - resources/views/partials/bid_detail_body/landlord.blade.php
 */
class BidDetailAgentHighlightsTest extends TestCase
{
    /** The five fields the Agent Highlights strip renders. */
    private const HIGHLIGHT_KEYS = [
        'years_experience',
        'transactions_last_12_months',
        'avg_response_time',
        'primary_areas_served',
        'review_1',
    ];

    private function partialPaths(): array
    {
        $base = base_path('resources/views/partials/bid_detail_body/');
        return [
            'buyer'    => $base . 'buyer.blade.php',
            'seller'   => $base . 'seller.blade.php',
            'landlord' => $base . 'landlord.blade.php',
        ];
    }

    /**
     * The mechanism the partials read — $bid->get->{field} — resolves to the
     * per-bid submitted meta snapshot, independent of any AgentDefaultProfile.
     */
    public function test_bid_get_accessor_returns_submitted_highlight_snapshot(): void
    {
        $bid = new BuyerAgentAuctionBid();
        $bid->setRelation('meta', collect([
            new BuyerAgentAuctionBidMeta(['meta_key' => 'years_experience',            'meta_value' => '12']),
            new BuyerAgentAuctionBidMeta(['meta_key' => 'transactions_last_12_months', 'meta_value' => '30']),
            new BuyerAgentAuctionBidMeta(['meta_key' => 'avg_response_time',           'meta_value' => 'Within 1 hour']),
            new BuyerAgentAuctionBidMeta(['meta_key' => 'primary_areas_served',        'meta_value' => 'Austin, TX']),
            new BuyerAgentAuctionBidMeta(['meta_key' => 'review_1',                    'meta_value' => 'Great agent at bid time.']),
        ]));

        $this->assertSame('12', data_get($bid, 'get.years_experience'));
        $this->assertSame('30', data_get($bid, 'get.transactions_last_12_months'));
        $this->assertSame('Within 1 hour', data_get($bid, 'get.avg_response_time'));
        $this->assertSame('Austin, TX', data_get($bid, 'get.primary_areas_served'));
        $this->assertSame('Great agent at bid time.', data_get($bid, 'get.review_1'));
    }

    /**
     * When a bid carries no highlight meta, the strip source yields empty — the
     * partials must show nothing rather than falling back to a live profile.
     */
    public function test_bid_without_highlight_meta_yields_no_highlight_values(): void
    {
        $bid = new BuyerAgentAuctionBid();
        $bid->setRelation('meta', collect([
            new BuyerAgentAuctionBidMeta(['meta_key' => 'commission_structure', 'meta_value' => 'Flat Fee']),
        ]));

        foreach (self::HIGHLIGHT_KEYS as $key) {
            $this->assertNull(data_get($bid, 'get.' . $key), "Expected no submitted value for {$key}");
        }
    }

    /**
     * Guard the partials themselves: each must source the highlights from bid
     * meta and must NOT re-introduce a live AgentDefaultProfile query.
     */
    public function test_partials_source_highlights_from_bid_meta_not_default_profile(): void
    {
        foreach ($this->partialPaths() as $role => $path) {
            $this->assertFileExists($path);
            $src = file_get_contents($path);

            // Must read each highlight field from the submitted bid meta.
            foreach (self::HIGHLIGHT_KEYS as $key) {
                $this->assertStringContainsString(
                    "data_get(\$bid, 'get.{$key}')",
                    $src,
                    "[{$role}] Agent Highlights must read '{$key}' from submitted bid meta"
                );
            }

            // Must NOT re-introduce the stale live-profile lookup for highlights.
            $this->assertStringNotContainsString(
                'AgentDefaultProfile::where',
                $src,
                "[{$role}] Agent Highlights must not query the live AgentDefaultProfile"
            );
        }
    }
}
