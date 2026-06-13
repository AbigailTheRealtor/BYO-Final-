<?php

namespace Tests\Unit;

use App\Services\CompatibilityScoreService;
use App\Services\MatchReadinessService;
use App\Services\RecommendationService;
use App\Services\ScoreBreakdownService;
use Tests\TestCase;

/**
 * P6 — RecommendationService unit tests.
 *
 * Covers:
 *   - All four roles (Seller, Buyer, Landlord, Tenant)
 *   - consumerFitRecommendation: returns 'not_scored' when bid is not_ready
 *   - consumerFitRecommendation: returns strong_fit, good_fit, partial_fit labels (no superlatives)
 *   - consumerFitRecommendation: every non-not_scored result includes a non-empty reasons array
 *   - consumerFitRecommendation: reasons are traceable to field-level breakdown data
 *   - consumerFitRecommendation: label guardrail — no superlative terms ever appear
 *   - agentCoachingRecommendation: derives missing_fields from MatchReadinessService
 *   - agentCoachingRecommendation: impact note reflects which tier would be unlocked
 *   - agentCoachingRecommendation: full_match_ready returns no missing fields
 *   - presetCompletionAnalysis: identifies missing quick and full match fields in preset data
 *   - presetCompletionAnalysis: correct impact text per readiness tier
 *   - Default sort order: recommendations do not alter score data shape
 *   - No recommendation generated for 'not_ready' without traceable source
 */
class RecommendationServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Bid data helpers (mirrors CompatibilityScoreServiceTest fixtures)
    // ─────────────────────────────────────────────────────────────────────────

    private function sellerQuickBid(): array
    {
        return [
            'services'                   => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'        => 'Buyer Pays',
            'purchase_fee_type'           => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'     => '3',
            'protection_period'           => '180',
            'agency_agreement_timeframe'  => '6 months',
            'brokerage_relationship'      => 'Single Agent',
        ];
    }

    private function sellerFullBid(): array
    {
        return array_merge($this->sellerQuickBid(), [
            'purchase_fee_flat'            => '500',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
            'nominal'                      => 'No',
            'commission_structure_type'    => 'Fixed',
            'seller_leasing_fee_type'      => 'N/A',
        ]);
    }

    private function buyerQuickBid(): array
    {
        return [
            'services'                   => ['draft and submit offers using state-approved purchase forms'],
            'commission_structure'        => 'Buyer Pays',
            'purchase_fee_type'           => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'     => '2.5',
            'lease_fee_type'              => 'Flat Fee',
            'protection_period'           => '90',
            'agency_agreement_timeframe'  => '3 months',
            'brokerage_relationship'      => 'Single Agent',
        ];
    }

    private function buyerFullBid(): array
    {
        return array_merge($this->buyerQuickBid(), [
            'purchase_fee_flat'            => '1000',
            'lease_fee_percentage'         => '5',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
        ]);
    }

    private function landlordQuickBid(): array
    {
        return [
            'services'                   => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'        => 'Landlord Pays',
            'purchase_fee_type'           => 'Flat Fee',
            'purchase_fee_percentage'     => '8',
            'protection_period'           => '120',
            'agency_agreement_timeframe'  => '12 months',
            'brokerage_relationship'      => 'Transaction Broker',
        ];
    }

    private function landlordFullBid(): array
    {
        return array_merge($this->landlordQuickBid(), [
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

    private function tenantQuickBid(): array
    {
        return [
            'services'                   => ['schedule and attend property showings with the tenant'],
            'commission_structure'        => 'Tenant Pays',
            'purchase_fee_type'           => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage'     => '2',
            'lease_fee_type'              => 'Percentage of the Monthly Rent',
            'protection_period'           => '60',
            'agency_agreement_timeframe'  => '6 months',
            'brokerage_relationship'      => 'Single Agent',
        ];
    }

    private function tenantFullBid(): array
    {
        return array_merge($this->tenantQuickBid(), [
            'purchase_fee_flat'            => '500',
            'lease_fee_percentage'         => '8',
            'early_termination_fee_option' => 'No',
            'retainer_fee_option'          => 'No',
            'broker_fee_timing'            => 'Upon Execution of Lease',
        ]);
    }

    /**
     * Produce a realistic breakdown for a fully-matching listing/bid pair.
     * Both sides are identical so every field is 'strong'.
     */
    private function breakdownAllStrong(array $bidData, string $role): array
    {
        return ScoreBreakdownService::breakdown($bidData, $bidData, $role);
    }

    /**
     * Produce a breakdown where all scalar fields mismatch (listing values differ).
     */
    private function breakdownAllWeak(array $bidData, string $role): array
    {
        $listing = array_map(fn($v) => is_array($v) ? $v : '__mismatch__', $bidData);
        return ScoreBreakdownService::breakdown($listing, $bidData, $role);
    }

    /**
     * Empty breakdown (score_type = 'none') for a not_ready bid.
     */
    private function breakdownNotReady(string $role): array
    {
        return ScoreBreakdownService::breakdown([], [], $role);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // consumerFitRecommendation — not_ready / not_scored
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function consumer_fit_returns_not_scored_for_empty_breakdown(): void
    {
        $breakdown = $this->breakdownNotReady('seller');
        $result    = RecommendationService::consumerFitRecommendation($breakdown, 'seller');

        $this->assertSame('not_scored', $result['recommendation_type']);
        $this->assertNull($result['score']);
        $this->assertNull($result['label']);
        $this->assertEmpty($result['reasons']);
        $this->assertSame('score_type_none', $result['source']);
    }

    /** @test */
    public function consumer_fit_not_scored_has_no_traceable_source_when_not_ready(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $breakdown = $this->breakdownNotReady($role);
            $result    = RecommendationService::consumerFitRecommendation($breakdown, $role);

            $this->assertSame('not_scored', $result['recommendation_type'],
                "Role {$role}: not_ready bids must return not_scored");
            $this->assertEmpty($result['reasons'],
                "Role {$role}: not_scored must have no reasons (no traceable source)");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // consumerFitRecommendation — strong_fit / good_fit / partial_fit
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function consumer_fit_returns_strong_fit_when_score_is_80_or_above(): void
    {
        $bid       = $this->sellerFullBid();
        $breakdown = $this->breakdownAllStrong($bid, 'seller');

        $result = RecommendationService::consumerFitRecommendation($breakdown, 'seller');

        $this->assertSame('strong_fit', $result['recommendation_type']);
        $this->assertSame(100, $result['score']);
        $this->assertNotNull($result['label']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertSame('score_breakdown', $result['source']);
    }

    /** @test */
    public function consumer_fit_returns_partial_fit_for_mostly_weak_breakdown(): void
    {
        // Use a listing that has most scalar fields mismatching the bid
        $bid     = $this->sellerQuickBid();
        $listing = [
            'services'                   => ['list the property on the local multiple listing service (mls)'],
            'commission_structure'        => '__mismatch__',
            'purchase_fee_type'           => '__mismatch__',
            'purchase_fee_percentage'     => '__mismatch__',
            'protection_period'           => '__mismatch__',
            'agency_agreement_timeframe'  => '__mismatch__',
            'brokerage_relationship'      => '__mismatch__',
        ];
        $breakdown = ScoreBreakdownService::breakdown($listing, $bid, 'seller');
        $result    = RecommendationService::consumerFitRecommendation($breakdown, 'seller');

        // Score will be very low — services match (1 field) but 6 scalars mismatch
        $this->assertContains($result['recommendation_type'], ['partial_fit', 'good_fit']);
        $this->assertNotNull($result['label']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertSame('score_breakdown', $result['source']);
    }

    /** @test */
    public function consumer_fit_all_four_roles_produce_valid_shaped_result_for_full_match(): void
    {
        $testCases = [
            'seller'   => $this->sellerFullBid(),
            'buyer'    => $this->buyerFullBid(),
            'landlord' => $this->landlordFullBid(),
            'tenant'   => $this->tenantFullBid(),
        ];

        foreach ($testCases as $role => $bid) {
            $breakdown = $this->breakdownAllStrong($bid, $role);
            $result    = RecommendationService::consumerFitRecommendation($breakdown, $role);

            $this->assertArrayHasKey('recommendation_type', $result, "Role {$role}: missing recommendation_type");
            $this->assertArrayHasKey('score', $result,               "Role {$role}: missing score");
            $this->assertArrayHasKey('label', $result,               "Role {$role}: missing label");
            $this->assertArrayHasKey('reasons', $result,             "Role {$role}: missing reasons");
            $this->assertArrayHasKey('source', $result,              "Role {$role}: missing source");
            $this->assertNotNull($result['label'],                   "Role {$role}: label must not be null for scored bid");
            $this->assertNotEmpty($result['reasons'],                "Role {$role}: reasons must not be empty for scored bid");
            $this->assertSame('score_breakdown', $result['source'],  "Role {$role}: source must be score_breakdown");
        }
    }

    /** @test */
    public function consumer_fit_reasons_are_traceable_to_breakdown_fields(): void
    {
        $bid       = $this->sellerFullBid();
        $breakdown = $this->breakdownAllStrong($bid, 'seller');
        $result    = RecommendationService::consumerFitRecommendation($breakdown, 'seller');

        // Every reason must end with "matches requested criteria" (strong result trace)
        // or be one of the documented generic phrases
        $allowedPhrases = [
            'matches requested criteria',
            'differs from requested terms',
            'Service package partially aligns with requested services',
            'Some fields were not provided — excluded from scoring',
        ];

        foreach ($result['reasons'] as $reason) {
            $matched = false;
            foreach ($allowedPhrases as $phrase) {
                if (str_contains($reason, $phrase)) {
                    $matched = true;
                    break;
                }
            }
            $this->assertTrue($matched, "Reason is not traceable to a known breakdown result: '{$reason}'");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Label guardrail — no superlatives
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function consumer_fit_labels_never_contain_superlatives_for_any_role(): void
    {
        $testCases = [
            'seller'   => $this->sellerFullBid(),
            'buyer'    => $this->buyerFullBid(),
            'landlord' => $this->landlordFullBid(),
            'tenant'   => $this->tenantFullBid(),
        ];

        $forbidden = ['best agent', 'top agent', 'best', 'top', '#1', 'number one', 'number 1', 'leading', 'premier'];

        foreach ($testCases as $role => $bid) {
            $breakdown = $this->breakdownAllStrong($bid, $role);
            $result    = RecommendationService::consumerFitRecommendation($breakdown, $role);

            $label = strtolower($result['label'] ?? '');
            foreach ($forbidden as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $label,
                    "Role {$role}: label '{$result['label']}' contains forbidden term '{$term}'"
                );
            }

            // Also assert via the service's own guardrail method
            if ($result['label'] !== null) {
                $this->assertTrue(
                    RecommendationService::assertNoSuperlatives($result['label']),
                    "Role {$role}: assertNoSuperlatives() failed for label '{$result['label']}'"
                );
            }
        }
    }

    /** @test */
    public function assert_no_superlatives_detects_forbidden_terms(): void
    {
        $this->assertFalse(RecommendationService::assertNoSuperlatives('Best Agent in your area'));
        $this->assertFalse(RecommendationService::assertNoSuperlatives('Top Agent for your needs'));
        $this->assertFalse(RecommendationService::assertNoSuperlatives('The #1 choice'));
        $this->assertFalse(RecommendationService::assertNoSuperlatives('Premier service'));
        $this->assertFalse(RecommendationService::assertNoSuperlatives('Leading agent in your market'));

        $this->assertTrue(RecommendationService::assertNoSuperlatives('Recommended based on your criteria'));
        $this->assertTrue(RecommendationService::assertNoSuperlatives('Strong fit for several of your requirements'));
        $this->assertTrue(RecommendationService::assertNoSuperlatives('Partial fit — some criteria align'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // agentCoachingRecommendation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function agent_coaching_returns_full_match_ready_when_bid_is_complete(): void
    {
        $testCases = [
            'seller'   => $this->sellerFullBid(),
            'buyer'    => $this->buyerFullBid(),
            'landlord' => $this->landlordFullBid(),
            'tenant'   => $this->tenantFullBid(),
        ];

        foreach ($testCases as $role => $bid) {
            $result = RecommendationService::agentCoachingRecommendation($bid, $role);

            $this->assertSame('profile_completion',  $result['recommendation_type'],
                "Role {$role}: recommendation_type must be profile_completion");
            $this->assertSame('full_match_ready', $result['state'],
                "Role {$role}: state must be full_match_ready");
            $this->assertEmpty($result['missing_fields'],
                "Role {$role}: full_match_ready bid must have no missing_fields");
            $this->assertEmpty($result['missing_labels'],
                "Role {$role}: full_match_ready bid must have no missing_labels");
            $this->assertSame('match_readiness', $result['source'],
                "Role {$role}: source must be match_readiness");
        }
    }

    /** @test */
    public function agent_coaching_reports_missing_full_fields_for_quick_match_ready_bid(): void
    {
        $testCases = [
            'seller'   => $this->sellerQuickBid(),
            'buyer'    => $this->buyerQuickBid(),
            'landlord' => $this->landlordQuickBid(),
            'tenant'   => $this->tenantQuickBid(),
        ];

        foreach ($testCases as $role => $bid) {
            $result = RecommendationService::agentCoachingRecommendation($bid, $role);

            $this->assertSame('profile_completion', $result['recommendation_type'],
                "Role {$role}: recommendation_type must be profile_completion");
            $this->assertSame('quick_match_ready', $result['state'],
                "Role {$role}: state must be quick_match_ready");
            $this->assertNotEmpty($result['missing_fields'],
                "Role {$role}: quick_match_ready bid must list missing full match fields");
            $this->assertNotEmpty($result['missing_labels'],
                "Role {$role}: missing_labels must be populated");
            $this->assertStringContainsString('Full Match', $result['impact'],
                "Role {$role}: impact must reference Full Match");
            $this->assertSame('match_readiness', $result['source'],
                "Role {$role}: source must be match_readiness");
        }
    }

    /** @test */
    public function agent_coaching_reports_missing_quick_fields_for_not_ready_bid(): void
    {
        $testCases = ['seller', 'buyer', 'landlord', 'tenant'];

        foreach ($testCases as $role) {
            $result = RecommendationService::agentCoachingRecommendation([], $role);

            $this->assertSame('profile_completion', $result['recommendation_type'],
                "Role {$role}: recommendation_type must be profile_completion");
            $this->assertSame('not_ready', $result['state'],
                "Role {$role}: state must be not_ready");
            $this->assertNotEmpty($result['missing_fields'],
                "Role {$role}: not_ready bid must list missing quick match fields");
            $this->assertNotEmpty($result['missing_labels'],
                "Role {$role}: missing_labels must be populated");
            $this->assertStringContainsString('Quick Match', $result['impact'],
                "Role {$role}: impact must reference Quick Match");
            $this->assertSame('match_readiness', $result['source'],
                "Role {$role}: source must be match_readiness");
        }
    }

    /** @test */
    public function agent_coaching_missing_fields_matches_match_readiness_service_output(): void
    {
        $bid  = $this->sellerQuickBid();
        $role = 'seller';

        $readiness  = MatchReadinessService::evaluate($bid, $role);
        $coaching   = RecommendationService::agentCoachingRecommendation($bid, $role);

        // quick_match_ready: missing_fields in coaching must equal missing_full from readiness service
        $this->assertSame($readiness['missing_full'], $coaching['missing_fields'],
            'Coaching missing_fields must exactly mirror MatchReadinessService missing_full for quick_match_ready bid');
    }

    /** @test */
    public function agent_coaching_missing_fields_includes_human_readable_labels(): void
    {
        $result = RecommendationService::agentCoachingRecommendation($this->sellerQuickBid(), 'seller');

        $this->assertNotEmpty($result['missing_labels']);
        foreach ($result['missing_labels'] as $label) {
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
            // Labels must not be raw field keys (underscores should be absent or converted)
            $this->assertStringNotContainsString('_', $label,
                "Label '{$label}' appears to be a raw field key — it should be human-readable");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // presetCompletionAnalysis
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function preset_completion_returns_all_ready_when_full_preset_provided(): void
    {
        $testCases = [
            'seller'   => $this->sellerFullBid(),
            'buyer'    => $this->buyerFullBid(),
            'landlord' => $this->landlordFullBid(),
            'tenant'   => $this->tenantFullBid(),
        ];

        foreach ($testCases as $role => $preset) {
            $result = RecommendationService::presetCompletionAnalysis($preset, $role);

            $this->assertSame('profile_completion', $result['recommendation_type'],
                "Role {$role}: recommendation_type must be profile_completion");
            $this->assertEmpty($result['missing_quick_fields'],
                "Role {$role}: full preset must have no missing quick fields");
            $this->assertEmpty($result['missing_full_fields'],
                "Role {$role}: full preset must have no missing full fields");
            $this->assertStringContainsString('Full Match Ready', $result['impact'],
                "Role {$role}: full preset impact must mention Full Match Ready");
            $this->assertSame('preset_data', $result['source'],
                "Role {$role}: source must be preset_data");
        }
    }

    /** @test */
    public function preset_completion_identifies_missing_quick_fields_for_empty_preset(): void
    {
        $testCases = ['seller', 'buyer', 'landlord', 'tenant'];

        foreach ($testCases as $role) {
            $result = RecommendationService::presetCompletionAnalysis([], $role);

            $this->assertNotEmpty($result['missing_quick_fields'],
                "Role {$role}: empty preset must report missing quick fields");
            $this->assertNotEmpty($result['missing_quick_labels'],
                "Role {$role}: empty preset must have human-readable labels for missing quick fields");
            $this->assertStringContainsString('Quick Match', $result['impact'],
                "Role {$role}: empty preset impact must reference Quick Match");
            $this->assertSame('preset_data', $result['source'],
                "Role {$role}: source must be preset_data");
        }
    }

    /** @test */
    public function preset_completion_identifies_missing_full_fields_for_quick_only_preset(): void
    {
        $testCases = [
            'seller'   => $this->sellerQuickBid(),
            'buyer'    => $this->buyerQuickBid(),
            'landlord' => $this->landlordQuickBid(),
            'tenant'   => $this->tenantQuickBid(),
        ];

        foreach ($testCases as $role => $preset) {
            $result = RecommendationService::presetCompletionAnalysis($preset, $role);

            $this->assertEmpty($result['missing_quick_fields'],
                "Role {$role}: quick-complete preset must have no missing quick fields");
            $this->assertNotEmpty($result['missing_full_fields'],
                "Role {$role}: quick-complete preset must have missing full fields");
            $this->assertNotEmpty($result['missing_full_labels'],
                "Role {$role}: missing full labels must be populated");
            $this->assertStringContainsString('Full Match', $result['impact'],
                "Role {$role}: impact must reference Full Match");
        }
    }

    /** @test */
    public function preset_completion_missing_labels_are_human_readable(): void
    {
        $result = RecommendationService::presetCompletionAnalysis([], 'buyer');

        $this->assertNotEmpty($result['missing_quick_labels']);
        foreach ($result['missing_quick_labels'] as $label) {
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
            $this->assertStringNotContainsString('_', $label,
                "Label '{$label}' appears to be a raw field key — should be human-readable");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Default sort order guardrail
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function consumer_fit_does_not_alter_score_data_shape_from_breakdown(): void
    {
        $bid       = $this->sellerFullBid();
        $breakdown = $this->breakdownAllStrong($bid, 'seller');

        // Capture score data before calling recommendation service
        $scoreDataBefore = $breakdown['score_data'];

        // Call recommendation service
        RecommendationService::consumerFitRecommendation($breakdown, 'seller');

        // Score data in the original breakdown must be unchanged (no mutation)
        $this->assertSame($scoreDataBefore, $breakdown['score_data'],
            'consumerFitRecommendation must not mutate the input breakdown array');
    }

    /** @test */
    public function agent_coaching_does_not_mutate_bid_data(): void
    {
        $bid      = $this->sellerQuickBid();
        $original = $bid;

        RecommendationService::agentCoachingRecommendation($bid, 'seller');

        $this->assertSame($original, $bid,
            'agentCoachingRecommendation must not mutate the input bid data array');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Result shape completeness
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function all_coaching_results_include_required_keys(): void
    {
        $required = ['recommendation_type', 'state', 'missing_fields', 'missing_labels', 'impact', 'source'];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = RecommendationService::agentCoachingRecommendation([], $role);
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $result, "Role {$role}: coaching result missing key '{$key}'");
            }
        }
    }

    /** @test */
    public function all_preset_analysis_results_include_required_keys(): void
    {
        $required = [
            'recommendation_type',
            'missing_quick_fields',
            'missing_quick_labels',
            'missing_full_fields',
            'missing_full_labels',
            'impact',
            'source',
        ];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $result = RecommendationService::presetCompletionAnalysis([], $role);
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $result, "Role {$role}: preset analysis result missing key '{$key}'");
            }
        }
    }

    /** @test */
    public function all_consumer_fit_results_include_required_keys(): void
    {
        $required = ['recommendation_type', 'score', 'label', 'reasons', 'source'];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $breakdown = $this->breakdownNotReady($role);
            $result    = RecommendationService::consumerFitRecommendation($breakdown, $role);
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $result, "Role {$role}: consumer fit result missing key '{$key}'");
            }
        }
    }
}
