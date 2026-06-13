<?php

namespace Tests\Unit;

use App\Services\ScoreBreakdownService;
use Tests\TestCase;

/**
 * Unit tests for ScoreBreakdownService.
 *
 * Covers:
 *   - All four roles (Seller, Buyer, Landlord, Tenant)
 *   - score_type 'none' returns empty breakdown
 *   - strong result: both sides populated and match
 *   - weak result: both sides populated but do NOT match
 *   - missing result: one or both sides not populated
 *   - partial result: array (services) field with some matches
 *   - missing ≠ weak distinction (core correctness invariant)
 *   - summary counts correct
 *   - active_field_set reflects quick_match or full_match
 *   - role-appropriate labels returned
 *   - response shape always contains required keys
 */
class ScoreBreakdownServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Fixtures — mirrors CompatibilityScoreServiceTest
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

    private function buyerQuickMatchBid(): array
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
        ];
    }

    private function buyerFullMatchBid(): array
    {
        return array_merge($this->buyerQuickMatchBid(), [
            'purchase_fee_flat'            => '1000',
            'lease_fee_percentage'         => '5',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
        ]);
    }

    private function landlordQuickMatchBid(): array
    {
        return [
            'services'                   => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'       => 'Landlord Pays',
            'purchase_fee_type'          => 'Flat Fee',
            'purchase_fee_percentage'    => '8',
            'protection_period'          => '120',
            'agency_agreement_timeframe' => '12 months',
            'brokerage_relationship'     => 'Transaction Broker',
        ];
    }

    private function landlordFullMatchBid(): array
    {
        return array_merge($this->landlordQuickMatchBid(), [
            'purchase_fee_flat'                  => '750',
            'early_termination_fee_option'       => 'No',
            'renewal_fee_type'                   => 'Flat Fee',
            'broker_fee_timing'                  => 'Upon Execution of Lease',
            'tenant_broker_commission_structure' => 'No Compensation',
            'expansion_commission_percentage'    => '5',
            'interested_in_property_management'  => 'No',
            'interested_in_selling'              => 'No',
        ]);
    }

    private function tenantQuickMatchBid(): array
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
        ];
    }

    private function tenantFullMatchBid(): array
    {
        return array_merge($this->tenantQuickMatchBid(), [
            'purchase_fee_flat'            => '500',
            'lease_fee_percentage'         => '5',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
            'broker_fee_timing'            => 'Upon Execution of Lease',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response shape
    // ─────────────────────────────────────────────────────────────────────────

    public function test_response_always_contains_required_keys(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = ScoreBreakdownService::breakdown([], [], $role);

            $this->assertArrayHasKey('score_data', $result, "Missing score_data for {$role}");
            $this->assertArrayHasKey('field_breakdown', $result, "Missing field_breakdown for {$role}");
            $this->assertArrayHasKey('summary', $result, "Missing summary for {$role}");
            $this->assertArrayHasKey('active_field_set', $result, "Missing active_field_set for {$role}");
        }
    }

    public function test_summary_always_contains_required_keys(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result  = ScoreBreakdownService::breakdown([], [], $role);
            $summary = $result['summary'];

            foreach (['strong', 'weak', 'partial', 'missing', 'total'] as $key) {
                $this->assertArrayHasKey($key, $summary, "Missing summary.{$key} for {$role}");
                $this->assertIsInt($summary[$key], "summary.{$key} must be int for {$role}");
            }
        }
    }

    public function test_field_breakdown_rows_contain_required_keys(): void
    {
        $bid    = $this->sellerQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        foreach ($result['field_breakdown'] as $row) {
            $this->assertArrayHasKey('field',         $row);
            $this->assertArrayHasKey('label',         $row);
            $this->assertArrayHasKey('result',        $row);
            $this->assertArrayHasKey('listing_value', $row);
            $this->assertArrayHasKey('bid_value',     $row);
            $this->assertArrayHasKey('note',          $row);
        }
    }

    public function test_result_values_are_canonical(): void
    {
        $allowed = ['strong', 'weak', 'partial', 'missing'];
        $bid     = $this->sellerFullMatchBid();
        $result  = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        foreach ($result['field_breakdown'] as $row) {
            $this->assertContains($row['result'], $allowed,
                "Field '{$row['field']}' has invalid result '{$row['result']}'");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // score_type 'none' → empty breakdown
    // ─────────────────────────────────────────────────────────────────────────

    public function test_not_ready_bid_returns_empty_breakdown(): void
    {
        $result = ScoreBreakdownService::breakdown([], [], 'seller');

        $this->assertSame('none', $result['active_field_set']);
        $this->assertEmpty($result['field_breakdown']);
        $this->assertSame(0, $result['summary']['total']);
    }

    public function test_not_ready_returns_empty_breakdown_all_roles(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = ScoreBreakdownService::breakdown([], [], $role);

            $this->assertSame('none', $result['active_field_set'],
                "Expected active_field_set 'none' for not_ready {$role}");
            $this->assertEmpty($result['field_breakdown'],
                "Expected empty breakdown for not_ready {$role}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // active_field_set
    // ─────────────────────────────────────────────────────────────────────────

    public function test_quick_match_ready_bid_sets_active_field_set_to_quick_match(): void
    {
        $bid    = $this->sellerQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        $this->assertSame('quick_match', $result['active_field_set']);
    }

    public function test_full_match_ready_bid_sets_active_field_set_to_full_match(): void
    {
        $bid    = $this->sellerFullMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        $this->assertSame('full_match', $result['active_field_set']);
    }

    public function test_active_field_set_matches_score_type(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $bidMethod = $role . 'FullMatchBid';
            $bid       = $this->$bidMethod();
            $result    = ScoreBreakdownService::breakdown($bid, $bid, $role);

            $this->assertSame($result['score_data']['score_type'], $result['active_field_set'],
                "active_field_set must match score_type for {$role}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // strong: both sides populated and match
    // ─────────────────────────────────────────────────────────────────────────

    public function test_matching_scalar_field_produces_strong_result(): void
    {
        $bid        = $this->sellerQuickMatchBid();
        $result     = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $breakdown  = $result['field_breakdown'];

        $commField = $this->findField($breakdown, 'commission_structure');

        $this->assertNotNull($commField);
        $this->assertSame('strong', $commField['result'],
            'Identical commission_structure values must produce a strong result.');
    }

    public function test_all_scalar_fields_matching_produces_only_strong_and_missing(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listingNoServices        = $bid;
        $listingNoServices['services'] = [];

        $result = ScoreBreakdownService::breakdown($listingNoServices, $bid, 'seller');

        foreach ($result['field_breakdown'] as $row) {
            $this->assertContains($row['result'], ['strong', 'missing'],
                "Field '{$row['field']}' should be strong or missing when all scalars match.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // weak: both sides populated but values differ
    // ─────────────────────────────────────────────────────────────────────────

    public function test_differing_scalar_field_produces_weak_result(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $bid['commission_structure'] = 'Seller Pays';

        $listing['services'] = [];

        $result    = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $breakdown = $result['field_breakdown'];

        $commField = $this->findField($breakdown, 'commission_structure');

        $this->assertNotNull($commField);
        $this->assertSame('weak', $commField['result'],
            'Differing commission_structure must produce a weak result.');
    }

    public function test_weak_field_has_note(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $bid['brokerage_relationship'] = 'Transaction Broker';
        $listing['services']           = [];

        $result    = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $breakdown = $result['field_breakdown'];

        $field = $this->findField($breakdown, 'brokerage_relationship');

        $this->assertSame('weak', $field['result']);
        $this->assertNotEmpty($field['note'], 'Weak fields must include an explanatory note.');
    }

    public function test_weak_field_carries_both_values(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $bid['protection_period'] = '90';
        $listing['services']      = [];

        $result   = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field    = $this->findField($result['field_breakdown'], 'protection_period');

        $this->assertSame('weak', $field['result']);
        $this->assertSame('180', $field['listing_value']);
        $this->assertSame('90',  $field['bid_value']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // missing: one or both sides not populated
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unpopulated_listing_field_produces_missing(): void
    {
        $bid     = $this->sellerQuickMatchBid();
        $listing = $this->sellerQuickMatchBid();

        unset($listing['commission_structure']);
        $listing['services'] = [];

        $result    = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $breakdown = $result['field_breakdown'];

        $field = $this->findField($breakdown, 'commission_structure');

        $this->assertNotNull($field);
        $this->assertSame('missing', $field['result'],
            'Absent listing field must produce a missing result.');
    }

    public function test_null_listing_field_produces_missing(): void
    {
        $bid                         = $this->sellerQuickMatchBid();
        $listing                     = $this->sellerQuickMatchBid();
        $listing['protection_period'] = null;
        $listing['services']          = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'protection_period');

        $this->assertSame('missing', $field['result']);
    }

    public function test_global_placeholder_produces_missing(): void
    {
        $bid                             = $this->sellerQuickMatchBid();
        $listing                         = $this->sellerQuickMatchBid();
        $listing['purchase_fee_percentage'] = '0';
        $listing['services']             = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'purchase_fee_percentage');

        $this->assertSame('missing', $field['result'],
            'Global placeholder value must be treated as missing.');
    }

    public function test_whitespace_only_field_produces_missing(): void
    {
        $bid                     = $this->sellerQuickMatchBid();
        $listing                 = $this->sellerQuickMatchBid();
        $listing['protection_period'] = '   ';
        $listing['services']     = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'protection_period');

        $this->assertSame('missing', $field['result'],
            'Whitespace-only field must be treated as missing.');
    }

    public function test_missing_field_has_note(): void
    {
        $bid                         = $this->sellerQuickMatchBid();
        $listing                     = $this->sellerQuickMatchBid();
        unset($listing['commission_structure']);
        $listing['services']         = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'commission_structure');

        $this->assertNotEmpty($field['note'],
            'Missing fields must include a note clarifying this does not imply a poor fit.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE CORRECTNESS: missing ≠ weak
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * A field where the listing has no data must never be classified as 'weak'.
     * Missing means data was not provided — it does NOT mean the agent is a poor fit.
     */
    public function test_unpopulated_listing_field_is_never_classified_as_weak(): void
    {
        $bid                         = $this->sellerFullMatchBid();
        $listing                     = $this->sellerFullMatchBid();
        unset($listing['nominal']);
        $listing['services']         = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'nominal');

        $this->assertNotSame('weak', $field['result'],
            'A missing listing field must never be classified as weak.');
        $this->assertSame('missing', $field['result']);
    }

    /**
     * A field where the listing has empty-string data must never be classified as 'weak'.
     *
     * Note: by design, if the BID is missing a required field, the bid drops to a lower
     * readiness state and that field is excluded from the breakdown entirely — so it can
     * never appear as 'weak'. This test validates the same invariant (missing ≠ weak) from
     * the listing side: when listing data is an empty string, the field must be 'missing'.
     */
    public function test_empty_string_listing_field_is_never_classified_as_weak(): void
    {
        $listing            = $this->sellerFullMatchBid();
        $listing['nominal'] = '';
        $listing['services'] = [];
        $bid                = $this->sellerFullMatchBid();

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'nominal');

        $this->assertNotNull($field,
            'nominal must appear in the breakdown when the bid is full_match_ready.');
        $this->assertNotSame('weak', $field['result'],
            'An empty-string field must never be classified as weak.');
        $this->assertSame('missing', $field['result']);
    }

    /**
     * A field where both sides have data but values differ must be 'weak', never 'missing'.
     */
    public function test_populated_mismatching_field_is_never_classified_as_missing(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $bid['brokerage_relationship'] = 'Transaction Broker';
        $listing['services']           = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'brokerage_relationship');

        $this->assertNotSame('missing', $field['result'],
            'A field with conflicting populated values must never be classified as missing.');
        $this->assertSame('weak', $field['result']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // partial: services field with some matches
    // ─────────────────────────────────────────────────────────────────────────

    public function test_partially_matched_services_produces_partial_result(): void
    {
        $listing = [
            'services' => ['service-alpha', 'service-beta'],
        ];
        $bid = array_merge($this->sellerQuickMatchBid(), [
            'services' => ['service-alpha'],
        ]);

        $listingFull = array_merge($this->sellerQuickMatchBid(), [
            'services' => ['service-alpha', 'service-beta'],
        ]);

        $result    = ScoreBreakdownService::breakdown($listingFull, $bid, 'seller');
        $breakdown = $result['field_breakdown'];
        $field     = $this->findField($breakdown, 'services');

        $this->assertNotNull($field);
        $this->assertSame('partial', $field['result'],
            'Partially overlapping services must produce a partial result.');
    }

    public function test_all_services_matched_produces_strong_result(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'services');

        $this->assertNotNull($field);
        $this->assertSame('strong', $field['result'],
            'Fully overlapping services must produce a strong result.');
    }

    public function test_no_services_matched_produces_weak_result(): void
    {
        $listing = array_merge($this->sellerQuickMatchBid(), [
            'services' => ['service-alpha'],
        ]);
        $bid = array_merge($this->sellerQuickMatchBid(), [
            'services' => ['service-gamma'],
        ]);

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'services');

        $this->assertNotNull($field);
        $this->assertSame('weak', $field['result'],
            'No services overlapping must produce a weak result.');
    }

    public function test_empty_listing_services_produces_missing_not_weak(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $listing['services'] = [];

        $bid = $this->sellerQuickMatchBid();

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'services');

        $this->assertSame('missing', $field['result'],
            'Empty listing services must produce missing, not weak.');
    }

    public function test_partial_result_note_contains_match_count(): void
    {
        $listing = array_merge($this->sellerQuickMatchBid(), [
            'services' => ['service-alpha', 'service-beta'],
        ]);
        $bid = array_merge($this->sellerQuickMatchBid(), [
            'services' => ['service-alpha', 'service-gamma'],
        ]);

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $field  = $this->findField($result['field_breakdown'], 'services');

        $this->assertSame('partial', $field['result']);
        $this->assertStringContainsString('1 of 2', $field['note'],
            'Partial match note must state matched count vs total.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Summary counts
    // ─────────────────────────────────────────────────────────────────────────

    public function test_summary_total_equals_number_of_fields(): void
    {
        $bid    = $this->sellerQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        $quickMatchFields = count(config('match_readiness.seller.quick_match'));

        $this->assertSame($quickMatchFields, $result['summary']['total'],
            'summary.total must equal the number of fields in the active field set.');
    }

    public function test_summary_counts_sum_to_total(): void
    {
        $bid    = $this->sellerFullMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');
        $s      = $result['summary'];

        $this->assertSame(
            $s['total'],
            $s['strong'] + $s['weak'] + $s['partial'] + $s['missing'],
            'Category counts must sum to total.'
        );
    }

    public function test_all_matching_bid_produces_no_weak_or_partial_in_summary(): void
    {
        $bid    = $this->sellerQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        $this->assertSame(0, $result['summary']['weak'],
            'All-matching bid must have 0 weak fields.');
        $this->assertSame(0, $result['summary']['partial'],
            'All-matching bid must have 0 partial fields.');
    }

    public function test_summary_counts_correct_for_known_input(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $bid['commission_structure']  = 'Seller Pays';
        $bid['brokerage_relationship'] = 'Transaction Broker';
        $listing['services']          = [];

        $result = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $s      = $result['summary'];

        $this->assertSame(2, $s['weak'],    'Exactly 2 scalar mismatches should produce 2 weak fields.');
        $this->assertSame(1, $s['missing'], 'Empty listing services should produce 1 missing field.');
        $this->assertSame(0, $s['partial'], 'No partial fields expected.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // All four roles — field set length matches config
    // ─────────────────────────────────────────────────────────────────────────

    public function test_buyer_quick_match_breakdown_covers_all_quick_match_fields(): void
    {
        $bid    = $this->buyerQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'buyer');

        $expectedCount = count(config('match_readiness.buyer.quick_match'));
        $this->assertSame($expectedCount, $result['summary']['total']);
    }

    public function test_buyer_full_match_breakdown_covers_all_full_match_fields(): void
    {
        $bid    = $this->buyerFullMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'buyer');

        $expectedCount = count(config('match_readiness.buyer.full_match'));
        $this->assertSame($expectedCount, $result['summary']['total']);
    }

    public function test_landlord_full_match_breakdown_covers_all_full_match_fields(): void
    {
        $bid    = $this->landlordFullMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'landlord');

        $expectedCount = count(config('match_readiness.landlord.full_match'));
        $this->assertSame($expectedCount, $result['summary']['total']);
    }

    public function test_tenant_full_match_breakdown_covers_all_full_match_fields(): void
    {
        $bid    = $this->tenantFullMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'tenant');

        $expectedCount = count(config('match_readiness.tenant.full_match'));
        $this->assertSame($expectedCount, $result['summary']['total']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Role-specific labels
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seller_labels_include_commission_rate(): void
    {
        $labels = ScoreBreakdownService::fieldLabels('seller');

        $this->assertArrayHasKey('purchase_fee_percentage', $labels);
        $this->assertSame('Commission Rate', $labels['purchase_fee_percentage'],
            "Seller label for purchase_fee_percentage should be 'Commission Rate'.");
    }

    public function test_landlord_labels_include_leasing_commission_rate(): void
    {
        $labels = ScoreBreakdownService::fieldLabels('landlord');

        $this->assertSame('Leasing Commission Rate', $labels['purchase_fee_percentage'],
            "Landlord label for purchase_fee_percentage should be 'Leasing Commission Rate'.");
    }

    public function test_buyer_labels_include_buyer_fee_rate(): void
    {
        $labels = ScoreBreakdownService::fieldLabels('buyer');

        $this->assertSame('Buyer Fee Rate', $labels['purchase_fee_percentage'],
            "Buyer label for purchase_fee_percentage should be 'Buyer Fee Rate'.");
    }

    public function test_tenant_labels_include_tenant_fee_rate(): void
    {
        $labels = ScoreBreakdownService::fieldLabels('tenant');

        $this->assertSame('Tenant Fee Rate', $labels['purchase_fee_percentage'],
            "Tenant label for purchase_fee_percentage should be 'Tenant Fee Rate'.");
    }

    public function test_landlord_labels_include_lease_specific_terms(): void
    {
        $labels = ScoreBreakdownService::fieldLabels('landlord');

        $this->assertSame('Renewal Fee Type',                   $labels['renewal_fee_type']);
        $this->assertSame('Broker Fee Timing',                  $labels['broker_fee_timing']);
        $this->assertSame('Tenant Broker Commission',           $labels['tenant_broker_commission_structure']);
        $this->assertSame('Property Management Interest',       $labels['interested_in_property_management']);
        $this->assertSame('Interest in Selling',                $labels['interested_in_selling']);
    }

    public function test_seller_labels_include_seller_specific_terms(): void
    {
        $labels = ScoreBreakdownService::fieldLabels('seller');

        $this->assertSame('Nominal Fee',             $labels['nominal']);
        $this->assertSame('Commission Type',          $labels['commission_structure_type']);
        $this->assertSame('Seller Leasing Fee Type',  $labels['seller_leasing_fee_type']);
    }

    public function test_labels_returned_for_all_four_roles(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $labels = ScoreBreakdownService::fieldLabels($role);

            $this->assertIsArray($labels, "fieldLabels({$role}) must return an array.");
            $this->assertNotEmpty($labels, "fieldLabels({$role}) must not be empty.");
            $this->assertArrayHasKey('services', $labels,
                "Every role must have a label for 'services'.");
        }
    }

    public function test_breakdown_rows_use_role_appropriate_labels(): void
    {
        $bid    = $this->landlordQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'landlord');

        $field = $this->findField($result['field_breakdown'], 'purchase_fee_percentage');
        $this->assertNotNull($field, "purchase_fee_percentage must appear in landlord breakdown.");
        $this->assertSame('Leasing Commission Rate', $field['label'],
            'Breakdown rows must use role-appropriate labels.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // score_data forwarded correctly
    // ─────────────────────────────────────────────────────────────────────────

    public function test_score_data_matches_compatibility_score_service_output(): void
    {
        $bid    = $this->sellerQuickMatchBid();
        $result = ScoreBreakdownService::breakdown($bid, $bid, 'seller');

        $this->assertArrayHasKey('readiness_state', $result['score_data']);
        $this->assertArrayHasKey('score_type',      $result['score_data']);
        $this->assertArrayHasKey('score',           $result['score_data']);
        $this->assertSame('quick_match_ready', $result['score_data']['readiness_state']);
        $this->assertSame('quick_match',       $result['score_data']['score_type']);
    }

    public function test_score_data_score_is_null_when_not_ready(): void
    {
        $result = ScoreBreakdownService::breakdown([], [], 'seller');

        $this->assertNull($result['score_data']['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Weights config is inactive — scores unchanged
    // ─────────────────────────────────────────────────────────────────────────

    public function test_weights_config_enabled_flag_is_false(): void
    {
        $enabled = config('match_readiness.weights._enabled');

        $this->assertFalse($enabled,
            'Weighting must remain disabled (P5 framework only — activating is a future-phase task).');
    }

    public function test_weights_config_covers_all_roles(): void
    {
        $weights = config('match_readiness.weights');

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $this->assertArrayHasKey($role, $weights,
                "Weights config must define per-field weights for role: {$role}.");
        }
    }

    public function test_all_weights_default_to_1(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $roleWeights = config("match_readiness.weights.{$role}", []);
            foreach ($roleWeights as $field => $weight) {
                $this->assertSame(1.0, (float) $weight,
                    "Weight for {$role}.{$field} must default to 1.0.");
            }
        }
    }

    public function test_weights_config_covers_all_full_match_fields_for_each_role(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $fullMatchFields = config("match_readiness.{$role}.full_match", []);
            $roleWeights     = config("match_readiness.weights.{$role}", []);

            foreach ($fullMatchFields as $field) {
                $this->assertArrayHasKey($field, $roleWeights,
                    "Weights config for {$role} must include field '{$field}'.");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function findField(array $breakdown, string $field): ?array
    {
        foreach ($breakdown as $row) {
            if ($row['field'] === $field) {
                return $row;
            }
        }
        return null;
    }
}
