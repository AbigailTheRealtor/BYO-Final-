<?php

namespace Tests\Feature;

use App\Services\ScoreBreakdownService;
use Tests\TestCase;

/**
 * Feature tests for the Score Breakdown Panel UI (P5).
 *
 * Asserts that the `components.score-breakdown-panel` Blade component renders
 * the correct HTML for known bid/listing pairs across all four roles.
 *
 * Covers:
 *   - Panel not rendered when bid is not match-ready
 *   - "Score Breakdown" heading visible when bid is match-ready
 *   - "Strong Match" label for fields that match exactly
 *   - "Weak Match" label for fields that conflict (data provided but differs)
 *   - "Not Provided" label for fields with no data (missing ≠ weak)
 *   - "Partial Match" label for services fields with some overlap
 *   - Missing fields show note explaining they do not affect compatibility
 *   - Weak fields show note explaining data was provided but conflicts
 *   - Score percentage shown in panel header when available
 *   - Role-appropriate field labels (Seller, Buyer, Landlord, Tenant)
 *   - Active field-set badge (Full Match / Quick Match)
 *   - Listing vs bid values displayed for weak scalar fields
 */
class ScoreBreakdownPanelRenderTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Fixtures — aligned with ScoreBreakdownServiceTest
    // ─────────────────────────────────────────────────────────────────────────

    private function sellerQuickMatchBid(): array
    {
        return [
            'services'                   => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'       => 'Buyer Pays',
            'purchase_fee_type'          => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'    => '3',
            'protection_period'          => '180',
            'agency_agreement_timeframe' => '6 months',
            'brokerage_relationship'     => 'Single Agent',
        ];
    }

    private function sellerFullMatchBid(): array
    {
        return array_merge($this->sellerQuickMatchBid(), [
            'purchase_fee_flat'            => '500',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
            'nominal'                      => 'No',
            'commission_structure_type'    => 'Fixed',
            'seller_leasing_fee_type'      => 'N/A',
        ]);
    }

    private function landlordFullMatchBid(): array
    {
        return [
            'services'                            => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'                => 'Landlord Pays',
            'purchase_fee_type'                   => 'Flat Fee',
            'purchase_fee_percentage'             => '8',
            'protection_period'                   => '120',
            'agency_agreement_timeframe'          => '12 months',
            'brokerage_relationship'              => 'Transaction Broker',
            'purchase_fee_flat'                   => '750',
            'early_termination_fee_option'        => 'No',
            'renewal_fee_type'                    => 'Flat Fee',
            'broker_fee_timing'                   => 'Upon Execution of Lease',
            'tenant_broker_commission_structure'  => 'No Compensation',
            'expansion_commission_percentage'     => '5',
            'interested_in_property_management'   => 'No',
            'interested_in_selling'               => 'No',
        ];
    }

    private function buyerFullMatchBid(): array
    {
        return [
            'services'                   => ['draft and submit offers using state-approved purchase forms'],
            'commission_structure'       => 'Buyer Pays',
            'purchase_fee_type'          => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'    => '2.5',
            'lease_fee_type'             => 'Flat Fee',
            'protection_period'          => '90',
            'agency_agreement_timeframe' => '3 months',
            'brokerage_relationship'     => 'Single Agent',
            'purchase_fee_flat'          => '1000',
            'lease_fee_percentage'       => '5',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
        ];
    }

    private function tenantFullMatchBid(): array
    {
        return [
            'services'                   => ['schedule and attend property showings with the tenant'],
            'commission_structure'       => 'Tenant Pays',
            'purchase_fee_type'          => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'    => '2',
            'lease_fee_type'             => 'Flat Fee',
            'protection_period'          => '60',
            'agency_agreement_timeframe' => '6 months',
            'brokerage_relationship'     => 'Single Agent',
            'purchase_fee_flat'          => '500',
            'lease_fee_percentage'       => '5',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
            'broker_fee_timing'            => 'Upon Execution of Lease',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render the score-breakdown-panel component and return the HTML string.
     */
    private function renderPanel(array $breakdown): string
    {
        return view('components.score-breakdown-panel', ['breakdown' => $breakdown])->render();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Panel visibility
    // ─────────────────────────────────────────────────────────────────────────

    public function test_panel_not_rendered_when_bid_is_not_ready(): void
    {
        $breakdown = ScoreBreakdownService::breakdown([], [], 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringNotContainsString('Score Breakdown', $html,
            'Panel must not render when the bid is not match-ready.');
        $this->assertStringNotContainsString('score-breakdown-wrapper', $html);
    }

    public function test_panel_renders_when_bid_is_quick_match_ready(): void
    {
        $bid       = $this->sellerQuickMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Score Breakdown', $html,
            'Panel heading must appear for a quick_match_ready bid.');
    }

    public function test_panel_renders_when_bid_is_full_match_ready(): void
    {
        $bid       = $this->sellerFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Score Breakdown', $html,
            'Panel heading must appear for a full_match_ready bid.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Score displayed in panel header
    // ─────────────────────────────────────────────────────────────────────────

    public function test_score_percentage_appears_in_panel_header(): void
    {
        $bid       = $this->sellerQuickMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $score = $breakdown['score_data']['score'];
        $this->assertStringContainsString("{$score}%", $html,
            'Score percentage must appear in the panel header for a match-ready bid.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Active field set badge (Full Match / Quick Match)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_full_match_badge_appears_for_full_match_ready_bid(): void
    {
        $bid       = $this->sellerFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Full Match', $html,
            'Full Match badge must appear in the panel for a full_match_ready bid.');
    }

    public function test_quick_match_badge_appears_for_quick_match_ready_bid(): void
    {
        $bid       = $this->sellerQuickMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Quick Match', $html,
            'Quick Match badge must appear in the panel for a quick_match_ready bid.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Strong Match — both sides match
    // ─────────────────────────────────────────────────────────────────────────

    public function test_strong_match_label_appears_for_matching_fields(): void
    {
        $bid       = $this->sellerQuickMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Strong Match', $html,
            'Strong Match label must appear when fields match between listing and bid.');
    }

    public function test_strong_match_summary_badge_shown_when_fields_match(): void
    {
        $bid       = $this->sellerQuickMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $strongCount = $breakdown['summary']['strong'];
        $this->assertGreaterThan(0, $strongCount);
        $this->assertStringContainsString("{$strongCount} Strong Match", $html,
            'Summary badge must display strong-match count.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Weak Match — both sides populated but values differ
    // ─────────────────────────────────────────────────────────────────────────

    public function test_weak_match_label_appears_for_conflicting_fields(): void
    {
        $listing                       = $this->sellerQuickMatchBid();
        $bid                           = $this->sellerQuickMatchBid();
        $bid['commission_structure']   = 'Seller Pays';
        $listing['services']           = [];

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Weak Match', $html,
            'Weak Match label must appear for fields with conflicting data.');
    }

    public function test_weak_match_shows_listing_and_bid_values(): void
    {
        $listing                     = $this->sellerQuickMatchBid();
        $bid                         = $this->sellerQuickMatchBid();
        $bid['brokerage_relationship'] = 'Transaction Broker';
        $listing['services']           = [];

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Single Agent', $html,
            'The listing value for the weak field must appear in the rendered panel.');
        $this->assertStringContainsString('Transaction Broker', $html,
            'The bid value for the weak field must appear in the rendered panel.');
    }

    public function test_weak_match_note_text_is_present(): void
    {
        $listing                     = $this->sellerQuickMatchBid();
        $bid                         = $this->sellerQuickMatchBid();
        $bid['commission_structure'] = 'Seller Pays';
        $listing['services']         = [];

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('differs from the listing', $html,
            'Weak match rows must include a note explaining the conflict.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Not Provided — missing fields (missing ≠ weak)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_not_provided_label_appears_for_missing_fields(): void
    {
        $listing             = $this->sellerFullMatchBid();
        $listing['nominal']  = null;
        $listing['services'] = [];
        $bid                 = $this->sellerFullMatchBid();

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Not Provided', $html,
            'Not Provided label must appear for fields where data was not supplied.');
    }

    /**
     * Core correctness: "Not Provided" and "Weak Match" must never be conflated.
     * A missing field must show "Not Provided", not "Weak Match".
     */
    public function test_missing_field_shows_not_provided_never_weak_match(): void
    {
        $listing             = $this->sellerFullMatchBid();
        $listing['nominal']  = null;
        $listing['services'] = [];
        $bid                 = $this->sellerFullMatchBid();

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');

        $nominalRow = null;
        foreach ($breakdown['field_breakdown'] as $row) {
            if ($row['field'] === 'nominal') {
                $nominalRow = $row;
                break;
            }
        }

        $this->assertNotNull($nominalRow);
        $this->assertSame('missing', $nominalRow['result'],
            'A null listing field must be classified as missing, not weak.');

        $html = $this->renderPanel($breakdown);
        $this->assertStringContainsString('Not Provided', $html);
    }

    public function test_missing_field_note_explains_no_score_impact(): void
    {
        $listing             = $this->sellerFullMatchBid();
        $listing['nominal']  = null;
        $listing['services'] = [];
        $bid                 = $this->sellerFullMatchBid();

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('does not affect compatibility', $html,
            'Panel must clarify that missing fields do not affect the compatibility score.');
    }

    public function test_missing_fields_notice_banner_appears_when_missing_fields_exist(): void
    {
        $listing             = $this->sellerFullMatchBid();
        $listing['nominal']  = null;
        $listing['services'] = [];
        $bid                 = $this->sellerFullMatchBid();

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('excluded from scoring', $html,
            'The "missing fields" info banner must appear when at least one field is missing.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Partial Match — services with some overlap
    // ─────────────────────────────────────────────────────────────────────────

    public function test_partial_match_label_appears_for_partially_matched_services(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $listing['services'] = ['service-alpha', 'service-beta'];

        $bid = $this->sellerQuickMatchBid();
        $bid['services'] = ['service-alpha', 'service-gamma'];

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Partial Match', $html,
            'Partial Match label must appear when only some services overlap.');
    }

    public function test_partial_match_note_contains_match_count(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $listing['services'] = ['service-alpha', 'service-beta'];

        $bid = $this->sellerQuickMatchBid();
        $bid['services'] = ['service-alpha', 'service-gamma'];

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('1 of 2', $html,
            'Partial match note in the panel must state matched count vs total.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Role-appropriate labels
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seller_panel_shows_commission_rate_label(): void
    {
        $bid       = $this->sellerFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Commission Rate', $html,
            "Seller bid panel must use 'Commission Rate' for purchase_fee_percentage.");
    }

    public function test_landlord_panel_shows_leasing_commission_rate_label(): void
    {
        $bid       = $this->landlordFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'landlord');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Leasing Commission Rate', $html,
            "Landlord bid panel must use 'Leasing Commission Rate' for purchase_fee_percentage.");
    }

    public function test_landlord_panel_shows_renewal_fee_type_label(): void
    {
        $bid       = $this->landlordFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'landlord');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Renewal Fee Type', $html,
            "Landlord bid panel must show 'Renewal Fee Type'.");
    }

    public function test_buyer_panel_shows_buyer_fee_rate_label(): void
    {
        $bid       = $this->buyerFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'buyer');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Buyer Fee Rate', $html,
            "Buyer bid panel must use 'Buyer Fee Rate' for purchase_fee_percentage.");
    }

    public function test_tenant_panel_shows_tenant_fee_rate_label(): void
    {
        $bid       = $this->tenantFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'tenant');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Tenant Fee Rate', $html,
            "Tenant bid panel must use 'Tenant Fee Rate' for purchase_fee_percentage.");
    }

    public function test_landlord_panel_shows_broker_fee_timing_label(): void
    {
        $bid       = $this->landlordFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'landlord');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Broker Fee Timing', $html,
            "Landlord bid panel must show 'Broker Fee Timing'.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // All four roles — panel renders without error
    // ─────────────────────────────────────────────────────────────────────────

    public function test_panel_renders_without_error_for_all_four_roles(): void
    {
        $fixtures = [
            'seller'   => $this->sellerFullMatchBid(),
            'buyer'    => $this->buyerFullMatchBid(),
            'landlord' => $this->landlordFullMatchBid(),
            'tenant'   => $this->tenantFullMatchBid(),
        ];

        foreach ($fixtures as $role => $bid) {
            $breakdown = ScoreBreakdownService::breakdown($bid, $bid, $role);
            $html      = $this->renderPanel($breakdown);

            $this->assertStringContainsString('Score Breakdown', $html,
                "Panel must render without error for role: {$role}.");
            $this->assertStringContainsString('Strong Match', $html,
                "All-matching bid must produce Strong Match entries for role: {$role}.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Field count in panel
    // ─────────────────────────────────────────────────────────────────────────

    public function test_panel_shows_fields_evaluated_count(): void
    {
        $bid       = $this->sellerFullMatchBid();
        $breakdown = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $total     = $breakdown['summary']['total'];
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString("{$total} fields evaluated", $html,
            'Panel must display the total number of fields evaluated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Known bid/listing pair — seller quick match
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seller_quick_match_pair_renders_correctly(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $bid['brokerage_relationship'] = 'Transaction Broker';

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Score Breakdown',    $html);
        $this->assertStringContainsString('Quick Match',        $html);
        $this->assertStringContainsString('Weak Match',         $html);
        $this->assertStringContainsString('Strong Match',       $html);
        $this->assertStringContainsString('Brokerage Relationship', $html);
        $this->assertStringContainsString('Single Agent',       $html);
        $this->assertStringContainsString('Transaction Broker', $html);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Known bid/listing pair — landlord full match with missing field
    // ─────────────────────────────────────────────────────────────────────────

    public function test_landlord_full_match_pair_with_missing_field_renders_correctly(): void
    {
        $listing                               = $this->landlordFullMatchBid();
        $listing['expansion_commission_percentage'] = null;
        $bid                                   = $this->landlordFullMatchBid();

        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'landlord');
        $html      = $this->renderPanel($breakdown);

        $this->assertStringContainsString('Full Match',                $html);
        $this->assertStringContainsString('Leasing Commission Rate',   $html);
        $this->assertStringContainsString('Renewal Fee Type',          $html);
        $this->assertStringContainsString('Not Provided',              $html);
        $this->assertStringContainsString('Expansion Commission Rate', $html);
        $this->assertStringNotContainsString(
            'Weak Match',
            substr($html, strpos($html, 'Expansion Commission Rate'), 500),
            'The Expansion Commission Rate field (missing) must not be shown as Weak Match.'
        );
    }
}
