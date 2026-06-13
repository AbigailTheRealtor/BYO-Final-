<?php

namespace Tests\Unit;

use App\Services\CompatibilityScoreService;
use Tests\TestCase;

/**
 * Unit tests for CompatibilityScoreService.
 *
 * Covers:
 *   - All four roles (Seller, Buyer, Landlord, Tenant)
 *   - not_ready → score_type 'none', score null
 *   - quick_match_ready (not full_match_ready) → score_type 'quick_match'
 *   - full_match_ready → score_type 'full_match'
 *   - When both full and quick are satisfied → full_match only (never quick_match)
 *   - All fields match → 100
 *   - No fields match → 0
 *   - Partial match
 *   - Missing fields on either side are skipped (not treated as mismatches)
 *   - Zero comparable fields after skipping → score_type 'none', score null
 */
class CompatibilityScoreServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Minimum bid data satisfying Seller Quick Match required fields only.
     */
    private function sellerQuickMatchBid(): array
    {
        return [
            'services'                  => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'      => 'Buyer Pays',
            'purchase_fee_type'         => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'   => '3',
            'protection_period'         => '180',
            'agency_agreement_timeframe'=> '6 months',
            'brokerage_relationship'    => 'Single Agent',
        ];
    }

    /**
     * Additional fields needed to elevate a Seller bid to Full Match Ready.
     */
    private function sellerFullMatchExtra(): array
    {
        return [
            'purchase_fee_flat'           => '500',
            'early_termination_fee_option'=> 'No',
            'retainer_fee_option'         => 'No',
            'nominal'                     => 'No',
            'commission_structure_type'   => 'Fixed',
            'seller_leasing_fee_type'     => 'N/A',
        ];
    }

    /**
     * Full Seller bid (Quick + Full Match).
     */
    private function sellerFullMatchBid(): array
    {
        return array_merge($this->sellerQuickMatchBid(), $this->sellerFullMatchExtra());
    }

    /**
     * Minimum bid data satisfying Buyer Quick Match required fields only.
     */
    private function buyerQuickMatchBid(): array
    {
        return [
            'services'                  => ['draft and submit offers using state-approved purchase forms'],
            'commission_structure'      => 'Buyer Pays',
            'purchase_fee_type'         => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'   => '2.5',
            'lease_fee_type'            => 'Flat Fee',
            'protection_period'         => '90',
            'agency_agreement_timeframe'=> '3 months',
            'brokerage_relationship'    => 'Single Agent',
        ];
    }

    /**
     * Full Buyer bid (Quick + Full Match).
     */
    private function buyerFullMatchBid(): array
    {
        return array_merge($this->buyerQuickMatchBid(), [
            'purchase_fee_flat'           => '1000',
            'lease_fee_percentage'        => '5',
            'early_termination_fee_option'=> 'No',
            'retainer_fee_option'         => 'No',
        ]);
    }

    /**
     * Minimum bid data satisfying Landlord Quick Match required fields only.
     */
    private function landlordQuickMatchBid(): array
    {
        return [
            'services'                  => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'      => 'Landlord Pays',
            'purchase_fee_type'         => 'Flat Fee',
            'purchase_fee_percentage'   => '8',
            'protection_period'         => '120',
            'agency_agreement_timeframe'=> '12 months',
            'brokerage_relationship'    => 'Transaction Broker',
        ];
    }

    /**
     * Full Landlord bid (Quick + Full Match).
     */
    private function landlordFullMatchBid(): array
    {
        return array_merge($this->landlordQuickMatchBid(), [
            'purchase_fee_flat'                   => '750',
            'early_termination_fee_option'        => 'No',
            'renewal_fee_type'                    => 'Flat Fee',
            'broker_fee_timing'                   => 'Upon Execution of Lease',
            'tenant_broker_commission_structure'  => 'No Compensation',
            'expansion_commission_percentage'     => '5',
            'interested_in_property_management'   => 'No',
            'interested_in_selling'               => 'No',
        ]);
    }

    /**
     * Minimum bid data satisfying Tenant Quick Match required fields only.
     */
    private function tenantQuickMatchBid(): array
    {
        return [
            'services'                  => ['schedule and attend property showings with the tenant'],
            'commission_structure'      => 'Tenant Pays',
            'purchase_fee_type'         => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'   => '2',
            'lease_fee_type'            => 'Flat Fee',
            'protection_period'         => '60',
            'agency_agreement_timeframe'=> '6 months',
            'brokerage_relationship'    => 'Single Agent',
        ];
    }

    /**
     * Full Tenant bid (Quick + Full Match).
     */
    private function tenantFullMatchBid(): array
    {
        return array_merge($this->tenantQuickMatchBid(), [
            'purchase_fee_flat'           => '500',
            'lease_fee_percentage'        => '5',
            'early_termination_fee_option'=> 'No',
            'retainer_fee_option'         => 'No',
            'broker_fee_timing'           => 'Upon Execution of Lease',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Not Ready → score_type 'none', score null
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seller_not_ready_returns_none(): void
    {
        $result = CompatibilityScoreService::score([], [], 'seller');

        $this->assertSame('not_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    public function test_buyer_not_ready_returns_none(): void
    {
        $result = CompatibilityScoreService::score([], [], 'buyer');

        $this->assertSame('not_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    public function test_landlord_not_ready_returns_none(): void
    {
        $result = CompatibilityScoreService::score([], [], 'landlord');

        $this->assertSame('not_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    public function test_tenant_not_ready_returns_none(): void
    {
        $result = CompatibilityScoreService::score([], [], 'tenant');

        $this->assertSame('not_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    /**
     * Not Ready bid with global placeholder value ('0') is still not_ready.
     */
    public function test_not_ready_with_global_placeholder_bid(): void
    {
        $bid = $this->sellerQuickMatchBid();
        $bid['purchase_fee_percentage'] = '0';

        $result = CompatibilityScoreService::score([], $bid, 'seller');

        $this->assertSame('not_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Quick Match Ready (not Full Match) → score_type 'quick_match'
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seller_quick_match_ready_returns_quick_match_score_type(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'seller');

        $this->assertSame('quick_match_ready', $result['readiness_state']);
        $this->assertSame('quick_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    public function test_buyer_quick_match_ready_returns_quick_match_score_type(): void
    {
        $bid = $this->buyerQuickMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'buyer');

        $this->assertSame('quick_match_ready', $result['readiness_state']);
        $this->assertSame('quick_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    public function test_landlord_quick_match_ready_returns_quick_match_score_type(): void
    {
        $bid = $this->landlordQuickMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'landlord');

        $this->assertSame('quick_match_ready', $result['readiness_state']);
        $this->assertSame('quick_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    public function test_tenant_quick_match_ready_returns_quick_match_score_type(): void
    {
        $bid = $this->tenantQuickMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'tenant');

        $this->assertSame('quick_match_ready', $result['readiness_state']);
        $this->assertSame('quick_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Full Match Ready → score_type 'full_match'
    // ─────────────────────────────────────────────────────────────────────────

    public function test_seller_full_match_ready_returns_full_match_score_type(): void
    {
        $bid = $this->sellerFullMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'seller');

        $this->assertSame('full_match_ready', $result['readiness_state']);
        $this->assertSame('full_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    public function test_buyer_full_match_ready_returns_full_match_score_type(): void
    {
        $bid = $this->buyerFullMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'buyer');

        $this->assertSame('full_match_ready', $result['readiness_state']);
        $this->assertSame('full_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    public function test_landlord_full_match_ready_returns_full_match_score_type(): void
    {
        $bid = $this->landlordFullMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'landlord');

        $this->assertSame('full_match_ready', $result['readiness_state']);
        $this->assertSame('full_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    public function test_tenant_full_match_ready_returns_full_match_score_type(): void
    {
        $bid = $this->tenantFullMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'tenant');

        $this->assertSame('full_match_ready', $result['readiness_state']);
        $this->assertSame('full_match', $result['score_type']);
        $this->assertNotNull($result['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Full Match supersedes Quick Match — never returns both simultaneously
    // ─────────────────────────────────────────────────────────────────────────

    public function test_full_match_ready_never_returns_quick_match_score_type(): void
    {
        $bid = $this->sellerFullMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'seller');

        $this->assertSame('full_match_ready', $result['readiness_state']);
        $this->assertNotSame('quick_match', $result['score_type'],
            'When bid is Full Match Ready, score_type must be full_match, not quick_match.');
        $this->assertSame('full_match', $result['score_type']);
    }

    public function test_full_match_ready_uses_full_match_fields_not_quick_match(): void
    {
        $listing = $this->sellerFullMatchBid();

        $bid = $this->sellerFullMatchBid();
        $bid['nominal'] = 'Yes';

        $quickResult = CompatibilityScoreService::score($listing, $this->sellerQuickMatchBid(), 'seller');
        $fullResult  = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $quickResult['score_type']);
        $this->assertSame('full_match', $fullResult['score_type']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Score correctness — all match, no match, partial
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * When listing has no services (services skipped) and all scalar fields match,
     * the score is 100.
     */
    public function test_all_scalar_fields_match_returns_100(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listingNoServices = $bid;
        $listingNoServices['services'] = [];

        $result = CompatibilityScoreService::score($listingNoServices, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score']);
    }

    /**
     * All full_match scalar fields match (services skipped on listing side).
     */
    public function test_all_scalar_fields_match_full_match_returns_100(): void
    {
        $bid = $this->sellerFullMatchBid();

        $listingNoServices = $bid;
        $listingNoServices['services'] = [];

        $result = CompatibilityScoreService::score($listingNoServices, $bid, 'seller');

        $this->assertSame('full_match', $result['score_type']);
        $this->assertSame(100, $result['score']);
    }

    /**
     * No fields match (different services, all scalar fields differ) → 0%.
     */
    public function test_no_fields_match_returns_0(): void
    {
        $listing = [
            'commission_structure'      => 'Buyer Pays',
            'purchase_fee_type'         => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'   => '3',
            'protection_period'         => '180',
            'agency_agreement_timeframe'=> '6 months',
            'brokerage_relationship'    => 'Single Agent',
            'services'                  => ['list the property on the local multiple listing service (mls)'],
        ];

        $bid = [
            'commission_structure'      => 'Seller Pays',
            'purchase_fee_type'         => 'Flat Fee',
            'purchase_fee_percentage'   => '5',
            'protection_period'         => '90',
            'agency_agreement_timeframe'=> '3 months',
            'brokerage_relationship'    => 'Transaction Broker',
            'services'                  => ['create a branded flyer featuring the property\'s key highlights'],
        ];

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(0, $result['score']);
    }

    /**
     * Partial match: listing has no services (skipped), one scalar differs.
     * Score = (total_scalars - 1) / total_scalars * 100.
     */
    public function test_partial_match_returns_correct_percentage(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listing = $bid;
        $listing['services'] = [];
        $listing['commission_structure'] = 'DIFFERENT_VALUE';

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);

        $qmFields         = config('match_readiness.seller.quick_match');
        $comparableFields = array_filter($qmFields, fn($f) => $f !== 'services');
        $totalComparable  = count($comparableFields);
        $expectedScore    = (int) round(($totalComparable - 1) / $totalComparable * 100);

        $this->assertSame($expectedScore, $result['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Missing fields are skipped (not treated as mismatches)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * When the listing is missing a scalar field, it is skipped (not counted as mismatch).
     * The bid is quick_match_ready; remaining comparable fields all match → 100%.
     */
    public function test_missing_field_in_listing_is_skipped(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listingMissingField = $bid;
        $listingMissingField['services'] = [];
        unset($listingMissingField['commission_structure']);

        $result = CompatibilityScoreService::score($listingMissingField, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score'],
            'Missing listing field should be skipped, not counted as a mismatch.');
    }

    /**
     * When a full_match-only field is missing from the listing, it is skipped.
     * Bid is full_match_ready; remaining fields all match → 100%.
     */
    public function test_missing_full_match_field_in_listing_is_skipped(): void
    {
        $bid = $this->sellerFullMatchBid();

        $listingMissingNominal = $bid;
        $listingMissingNominal['services'] = [];
        unset($listingMissingNominal['nominal']);

        $result = CompatibilityScoreService::score($listingMissingNominal, $bid, 'seller');

        $this->assertSame('full_match', $result['score_type']);
        $this->assertSame(100, $result['score'],
            'Missing full_match-only listing field should be skipped, not counted as a mismatch.');
    }

    /**
     * When a full_match-only field is missing from the bid, that field is excluded from the
     * comparable set. The bid is still quick_match_ready (quick_match scoring used).
     * Quick match fields all match between listing and bid → 100%.
     */
    public function test_missing_field_in_bid_is_skipped(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listing = $bid;
        $listing['services'] = [];

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score'],
            'Non-services scalars all match — score should be 100%.');
    }

    public function test_null_field_in_listing_is_skipped(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listingWithNull = $bid;
        $listingWithNull['services'] = [];
        $listingWithNull['protection_period'] = null;

        $result = CompatibilityScoreService::score($listingWithNull, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score'],
            'Null field in listing should be skipped, not counted as a mismatch.');
    }

    /**
     * A global placeholder ('0') on the LISTING side causes that field to be skipped.
     * The bid still has a real value, so the bid remains quick_match_ready.
     * Remaining scalars all match → 100%.
     */
    public function test_global_placeholder_field_is_skipped(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listingWithPlaceholder = $bid;
        $listingWithPlaceholder['services'] = [];
        $listingWithPlaceholder['purchase_fee_percentage'] = '0';

        $result = CompatibilityScoreService::score($listingWithPlaceholder, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score'],
            'Global placeholder (0) on the listing side must be skipped, not counted as a mismatch.');
    }

    public function test_whitespace_only_field_is_skipped(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $listing = $bid;
        $listing['services'] = [];
        $listing['protection_period'] = '   ';

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score'],
            'Whitespace-only field in listing should be skipped.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Zero comparable fields → score_type 'none'
    // ─────────────────────────────────────────────────────────────────────────

    public function test_zero_comparable_fields_returns_none(): void
    {
        $minBid = $this->sellerQuickMatchBid();

        $emptyListing = [
            'services' => [],
        ];

        $result = CompatibilityScoreService::score($emptyListing, $minBid, 'seller');

        $this->assertSame('quick_match_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    public function test_zero_comparable_fields_full_match_returns_none(): void
    {
        $fullBid = $this->sellerFullMatchBid();

        $emptyListing = [
            'services' => [],
        ];

        $result = CompatibilityScoreService::score($emptyListing, $fullBid, 'seller');

        $this->assertSame('full_match_ready', $result['readiness_state']);
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    /**
     * Zero comparable fields must not return 0% or 100% — only no score.
     */
    public function test_zero_comparable_fields_does_not_return_0_or_100(): void
    {
        $minBid = $this->sellerQuickMatchBid();

        $emptyListing = [
            'services' => [],
        ];

        $result = CompatibilityScoreService::score($emptyListing, $minBid, 'seller');

        $this->assertNotSame(0, $result['score'], 'Zero comparable fields must not return 0%.');
        $this->assertNotSame(100, $result['score'], 'Zero comparable fields must not return 100%.');
        $this->assertNull($result['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Services scoring
    // ─────────────────────────────────────────────────────────────────────────

    public function test_services_full_match_contributes_to_score(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $bid     = $this->sellerQuickMatchBid();

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertSame(100, $result['score']);
    }

    public function test_services_missing_from_listing_skips_services_field(): void
    {
        $listing = $this->sellerQuickMatchBid();
        $listing['services'] = [];

        $bid = $this->sellerQuickMatchBid();

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertNotNull($result['score'],
            'Non-services fields should still be scored even when services are empty on listing side.');
    }

    /**
     * When bid's services array is empty, the bid fails quick_match readiness
     * (services is a required quick_match field). This confirms that an empty-services
     * bid produces not_ready — the "skip" rule only applies during scoring, not readiness.
     */
    public function test_empty_services_in_bid_results_in_not_ready(): void
    {
        $listing = $this->sellerQuickMatchBid();

        $bid = $this->sellerQuickMatchBid();
        $bid['services'] = [];

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('not_ready', $result['readiness_state'],
            'Services is required for Quick Match readiness; empty array must yield not_ready.');
        $this->assertSame('none', $result['score_type']);
        $this->assertNull($result['score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Response shape — all required keys always present
    // ─────────────────────────────────────────────────────────────────────────

    public function test_response_always_contains_required_keys(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = CompatibilityScoreService::score([], [], $role);
            $this->assertArrayHasKey('readiness_state', $result, "Missing readiness_state for role {$role}");
            $this->assertArrayHasKey('score_type', $result, "Missing score_type for role {$role}");
            $this->assertArrayHasKey('score', $result, "Missing score key for role {$role}");
        }
    }

    public function test_score_is_integer_when_not_null(): void
    {
        $bid = $this->sellerQuickMatchBid();

        $result = CompatibilityScoreService::score($bid, $bid, 'seller');

        $this->assertIsInt($result['score']);
    }

    public function test_score_range_is_0_to_100(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $bidMethod     = $role . 'QuickMatchBid';
            $bid           = $this->$bidMethod();
            $result        = CompatibilityScoreService::score($bid, $bid, $role);

            if ($result['score'] !== null) {
                $this->assertGreaterThanOrEqual(0, $result['score'], "Score below 0 for role {$role}");
                $this->assertLessThanOrEqual(100, $result['score'], "Score above 100 for role {$role}");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // readiness_state values
    // ─────────────────────────────────────────────────────────────────────────

    public function test_readiness_state_values_are_canonical(): void
    {
        $allowedStates = ['not_ready', 'quick_match_ready', 'full_match_ready'];
        $allowedTypes  = ['none', 'quick_match', 'full_match'];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = CompatibilityScoreService::score([], [], $role);
            $this->assertContains($result['readiness_state'], $allowedStates, "Invalid readiness_state for {$role}");
            $this->assertContains($result['score_type'], $allowedTypes, "Invalid score_type for {$role}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Role isolation — quick/full field subsets are role-specific
    // ─────────────────────────────────────────────────────────────────────────

    public function test_buyer_quick_match_uses_lease_fee_type_field(): void
    {
        $listing = $this->buyerQuickMatchBid();
        $bid     = $this->buyerQuickMatchBid();
        $bid['lease_fee_type'] = 'Different Fee Type';

        $result = CompatibilityScoreService::score($listing, $bid, 'buyer');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertLessThan(100, $result['score'],
            'Buyer Quick Match must include lease_fee_type in scoring.');
    }

    public function test_tenant_quick_match_uses_lease_fee_type_field(): void
    {
        $listing = $this->tenantQuickMatchBid();
        $bid     = $this->tenantQuickMatchBid();
        $bid['lease_fee_type'] = 'Different Fee Type';

        $result = CompatibilityScoreService::score($listing, $bid, 'tenant');

        $this->assertSame('quick_match', $result['score_type']);
        $this->assertLessThan(100, $result['score'],
            'Tenant Quick Match must include lease_fee_type in scoring.');
    }

    public function test_landlord_full_match_uses_renewal_fee_type_field(): void
    {
        $listing = $this->landlordFullMatchBid();
        $bid     = $this->landlordFullMatchBid();
        $bid['renewal_fee_type'] = 'Different Renewal';

        $result = CompatibilityScoreService::score($listing, $bid, 'landlord');

        $this->assertSame('full_match', $result['score_type']);
        $this->assertLessThan(100, $result['score'],
            'Landlord Full Match must include renewal_fee_type in scoring.');
    }

    public function test_seller_full_match_uses_nominal_field(): void
    {
        $listing = $this->sellerFullMatchBid();
        $bid     = $this->sellerFullMatchBid();
        $bid['nominal'] = 'Yes';

        $result = CompatibilityScoreService::score($listing, $bid, 'seller');

        $this->assertSame('full_match', $result['score_type']);
        $this->assertLessThan(100, $result['score'],
            'Seller Full Match must include nominal in scoring.');
    }
}
