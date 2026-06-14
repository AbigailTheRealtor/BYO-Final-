<?php

namespace Tests\Feature\LocationDna;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for the location-dna-intelligence-summary Blade component and its
 * integration into the Buyer Criteria and Tenant Criteria public view pages.
 *
 * Component rendering (a–c) uses view()->render() directly.
 * Page load tests (d–e) use $this->get() with minimal DB fixtures.
 */
class LocationDnaIntelligenceSummaryComponentTest extends TestCase
{
    use DatabaseTransactions;

    private function renderComponent(array $summaryLines): string
    {
        return view('components.location-dna-intelligence-summary', [
            'summaryLines' => $summaryLines,
        ])->render();
    }

    /**
     * (a) Empty $summaryLines renders no card markup at all.
     */
    public function test_empty_summary_lines_renders_nothing(): void
    {
        $html = $this->renderComponent([]);

        $this->assertStringNotContainsString('Location Intelligence', $html);
        $this->assertStringNotContainsString('<div class="card', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    /**
     * (b) A non-empty array renders the card header and each line as a list item.
     */
    public function test_non_empty_summary_lines_renders_card_with_items(): void
    {
        $lines = [
            'Close to downtown schools',
            'Low flood risk area',
            'Near public transit hubs',
        ];

        $html = $this->renderComponent($lines);

        $this->assertStringContainsString('Location Intelligence', $html);
        $this->assertStringContainsString('<ul', $html);
        foreach ($lines as $line) {
            $this->assertStringContainsString($line, $html);
        }
    }

    /**
     * (c) A line containing a <script> tag is HTML-escaped in the output.
     */
    public function test_script_tag_in_line_is_html_escaped(): void
    {
        $html = $this->renderComponent(['<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * (d) The Buyer Criteria public view loads successfully even when no
     *     $locationIntelligenceSummary variable is passed from the controller.
     */
    public function test_buyer_criteria_view_loads_without_location_intelligence_summary(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer Listing',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200);
    }

    /**
     * (e) The Tenant Criteria public view loads successfully even when no
     *     $locationIntelligenceSummary variable is passed from the controller.
     *
     * Skipped when tenant_criteria_auctions table is absent in this environment.
     */
    public function test_tenant_criteria_view_loads_without_location_intelligence_summary(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200);
    }
}
