<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaNormalizationService;
use PHPUnit\Framework\TestCase;

/**
 * ByaNormalizationServiceTest
 *
 * Tests the BYA_NORM_V1 normalization engine against in-memory listing stubs.
 * No database connection is required — all test data is fabricated inline.
 *
 * Each test asserts:
 *   - Structural shape of the returned payload
 *   - Correct three-state slot assignments (answered / skipped / absent)
 *   - Role-specific routing crosswalks (naming inconsistency resolutions)
 *   - Absence of scoring fields, compatibility labels, and public/UI output
 */
class ByaNormalizationServiceTest extends TestCase
{
    private ByaNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaNormalizationService();
    }

    // -------------------------------------------------------------------------
    // Helper: build an in-memory listing stub
    // -------------------------------------------------------------------------

    /**
     * Build a minimal listing stub that the service can read.
     *
     * The stub exposes an info() method returning the raw JSON blob stored under
     * 'compatibility_preferences', mirroring the real EAV saveMeta/info() pattern.
     */
    private function makeListingStub(int $id, array $compatibilityPreferences): object
    {
        $json = json_encode($compatibilityPreferences, JSON_UNESCAPED_UNICODE);

        return new class ($id, $json) {
            public int $id;
            private string $json;

            public function __construct(int $id, string $json)
            {
                $this->id   = $id;
                $this->json = $json;
            }

            public function info(string $key): mixed
            {
                if ($key === 'compatibility_preferences') {
                    return $this->json;
                }
                // Dot-notation sub-key access not needed for this storage pattern
                return null;
            }
        };
    }

    /** Build a listing stub with no compatibility_preferences meta at all. */
    private function makeEmptyListingStub(int $id): object
    {
        return new class ($id) {
            public int $id;

            public function __construct(int $id)
            {
                $this->id = $id;
            }

            public function info(string $key): mixed
            {
                return null;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Test 1: Seller payload matches BYA_NORM_V1 shape with all 12 trait keys
    // -------------------------------------------------------------------------

    public function test_seller_payload_matches_bya_norm_v1_shape_with_all_12_trait_keys(): void
    {
        $listing = $this->makeListingStub(4821, [
            'seller_specific' => [
                'communication_style'           => 'Frequent & Proactive',
                'preferred_contact_method'      => ['Phone Call', 'Text/SMS'],
                'response_time_expectation'     => 'Within a Few Hours',
                'negotiation_style'             => 'Balanced — Fair & Reasonable',
                'willing_to_negotiate_on'       => ['Closing Costs', 'Possession Date'],
                'firm_on_price'                 => 'Somewhat — Open to Reasonable Offers',
                'primary_transaction_goal'      => 'Maximum Sale Price',
                'flexibility_on_timeline'       => 'Somewhat Flexible',
                'post_sale_plan'                => 'Purchasing Another Property',
                'representation_priorities'     => ['Market Expertise', 'Strong Negotiator'],
                'past_agent_experience'         => 'Positive Experience with Past Agent(s)',
                'involvement_level'             => 'Moderately Involved — Major steps only',
                'preferred_agent_working_style' => 'Proactive & Takes Initiative',
                'target_sale_timeline'          => '60–90 days',
                'showing_availability'          => ['Weekday Afternoons', 'Weekend Mornings'],
                'open_house_preference'         => 'Open to It',
                'additional_compatibility_notes' => 'Advance notice for showings appreciated.',
                'qualities_most_important'      => ['Honesty & Transparency'],
                'additional_decision_makers'    => 'Spouse',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'seller');

        // Top-level envelope — all six keys always present
        $this->assertSame('BYA_NORM_V1', $payload['normalization_version']);
        $this->assertSame('seller', $payload['role']);
        $this->assertSame(4821, $payload['listing_id']);
        $this->assertArrayHasKey('traits', $payload);
        $this->assertArrayHasKey('informational_context', $payload);
        $this->assertArrayHasKey('proxy_risk_flags', $payload);

        // traits object always has exactly 12 keys
        $this->assertCount(12, $payload['traits']);

        $expectedTraitKeys = [
            'communication_channel',
            'communication_frequency',
            'responsiveness_expectation',
            'negotiation_style',
            'guidance_level',
            'decision_making_style',
            'transaction_pace',
            'risk_tolerance',
            'collaboration_style',
            'representation_priorities',
            'representation_philosophy',
            'property_strategy_fit',
        ];

        foreach ($expectedTraitKeys as $key) {
            $this->assertArrayHasKey($key, $payload['traits'], "Trait key '{$key}' is missing from payload");
        }

        // Every slot has 'value' and 'missing' keys
        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertArrayHasKey('value', $slot, "Slot '{$traitKey}' is missing 'value' key");
            $this->assertArrayHasKey('missing', $slot, "Slot '{$traitKey}' is missing 'missing' key");
            $this->assertIsBool($slot['missing'], "Slot '{$traitKey}'.missing must be boolean");
        }

        // Seller-specific routing crosswalks
        // communication_channel ← preferred_contact_method (multi-select, correctly named)
        $this->assertFalse($payload['traits']['communication_channel']['missing']);
        $this->assertIsArray($payload['traits']['communication_channel']['value']);

        // communication_frequency ← communication_style (frequency data despite key name)
        $this->assertFalse($payload['traits']['communication_frequency']['missing']);
        $this->assertNotNull($payload['traits']['communication_frequency']['value']);

        // risk_tolerance — ABSENT for Seller
        $this->assertNull($payload['traits']['risk_tolerance']['value']);
        $this->assertTrue($payload['traits']['risk_tolerance']['missing']);

        // representation_philosophy — present for Seller, carries past_agent_experience sub-key
        $reprPhil = $payload['traits']['representation_philosophy'];
        $this->assertFalse($reprPhil['missing']);
        $this->assertArrayHasKey('past_agent_experience', $reprPhil);
        $this->assertSame('Positive Experience with Past Agent(s)', $reprPhil['past_agent_experience']);

        // proxy_risk_flags — empty for Seller
        $this->assertIsArray($payload['proxy_risk_flags']);
        $this->assertEmpty($payload['proxy_risk_flags']);

        // informational_context — correct keys present, no extra trait keys
        $ic = $payload['informational_context'];
        $this->assertArrayHasKey('post_sale_plan', $ic);
        $this->assertArrayHasKey('target_sale_timeline', $ic);
        $this->assertArrayHasKey('showing_availability', $ic);
        $this->assertArrayHasKey('firm_on_price', $ic);
        $this->assertArrayHasKey('qualities_most_important', $ic);
        $this->assertArrayHasKey('willing_to_negotiate_on', $ic);
        $this->assertCount(11, $ic);

        // No scoring fields anywhere in the payload
        $this->assertArrayNotHasKey('representation_compatibility_score', $payload);
        $this->assertArrayNotHasKey('match_score', $payload);
        $this->assertArrayNotHasKey('compatibility_label', $payload);
        $this->assertArrayNotHasKey('recommendation', $payload);
    }

    // -------------------------------------------------------------------------
    // Test 2: Buyer `communication_frequency` raw key maps to
    //         collaboration_style.showing_format_preference (not to the
    //         communication_frequency trait)
    // -------------------------------------------------------------------------

    public function test_buyer_communication_frequency_key_maps_to_collaboration_style_showing_format_preference(): void
    {
        $listing = $this->makeListingStub(7034, [
            'buyer_specific' => [
                'communication_style'           => 'Regular Updates (Every Few Days)',
                'preferred_contact_method'      => ['Phone Call', 'Text Message'],
                'communication_frequency'       => 'In-Person Only',   // misleadingly named key
                'negotiation_style'             => 'Firm but Fair',
                'preferred_agent_working_style' => 'Responsive Partner',
                'primary_transaction_goal'      => 'Primary Residence',
                'representation_priorities'     => ['Price Negotiation', 'Contract Protection'],
                'risk_tolerance'                => 'Moderate',
                'support_level'                 => 'High – Guided Throughout',
                'decision_making_style'         => 'Careful & Deliberate',
                'timeline_flexibility'          => 'Somewhat Flexible',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'buyer');

        // communication_frequency trait must come from communication_style (frequency data)
        $commFreq = $payload['traits']['communication_frequency'];
        $this->assertFalse($commFreq['missing']);
        $this->assertSame('Regular Updates (Every Few Days)', $commFreq['value']);

        // collaboration_style must carry the showing_format_preference sub-key
        $collabStyle = $payload['traits']['collaboration_style'];
        $this->assertFalse($collabStyle['missing']);
        $this->assertArrayHasKey('showing_format_preference', $collabStyle,
            'Buyer collaboration_style must carry showing_format_preference sub-key');
        $this->assertSame('In-Person Only', $collabStyle['showing_format_preference']);

        // The raw 'communication_frequency' value must NOT appear as the
        // communication_frequency trait value
        $this->assertNotSame('In-Person Only', $payload['traits']['communication_frequency']['value']);

        // responsiveness_expectation — ABSENT for Buyer
        $this->assertTrue($payload['traits']['responsiveness_expectation']['missing']);
        $this->assertNull($payload['traits']['responsiveness_expectation']['value']);

        // representation_philosophy — ABSENT for Buyer
        $this->assertTrue($payload['traits']['representation_philosophy']['missing']);
        $this->assertArrayNotHasKey('past_agent_experience', $payload['traits']['representation_philosophy']);
        $this->assertArrayNotHasKey('showing_format_preference', $payload['traits']['representation_philosophy']);

        // showing_format_preference must NOT appear on communication_frequency slot
        $this->assertArrayNotHasKey('showing_format_preference', $payload['traits']['communication_frequency']);

        // All 12 traits present
        $this->assertCount(12, $payload['traits']);

        // No proxy risk flags for Buyer
        $this->assertEmpty($payload['proxy_risk_flags']);
    }

    // -------------------------------------------------------------------------
    // Test 3: Landlord with populated tenant_type_preference emits proxy risk
    //         flag at both top level and within property_strategy_fit
    // -------------------------------------------------------------------------

    public function test_landlord_with_tenant_type_preference_emits_proxy_risk_flag_at_top_level_and_in_slot(): void
    {
        $listing = $this->makeListingStub(2290, [
            'landlord_specific' => [
                'communication_style'           => 'Phone Calls Preferred',
                'preferred_contact_method'      => 'Weekly Check-Ins',
                'response_time_expectation'     => 'Same Business Day',
                'negotiation_style'             => 'Collaborative Win-Win',
                'property_management_involvement' => 'Minimal Involvement',
                'preferred_agent_working_style' => 'Proactive & Assertive',
                'primary_leasing_goal'          => 'Long-Term Stable Tenant',
                'tenant_type_preference'        => 'Individual / Family',
                'representation_priorities'     => ['Tenant Screening & Vetting', 'Lease Negotiation'],
                'risk_tolerance'                => 'Moderate – Standard Criteria',
                'lease_duration_preference'     => '1 Year',
                'concessions_willingness'       => 'Open to Minor Concessions',
                'lease_terms_flexibility'       => 'Somewhat Flexible',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'landlord');

        // Top-level proxy_risk_flags must be populated
        $this->assertNotEmpty($payload['proxy_risk_flags']);
        $this->assertCount(1, $payload['proxy_risk_flags']);

        $topFlag = $payload['proxy_risk_flags'][0];
        $this->assertSame('tenant_type_preference', $topFlag['field']);
        $this->assertSame('property_strategy_fit', $topFlag['trait']);
        $this->assertIsString($topFlag['reason']);
        $this->assertNotEmpty($topFlag['reason']);

        // property_strategy_fit slot must carry embedded proxy_risk_flags sub-key
        $stratFit = $payload['traits']['property_strategy_fit'];
        $this->assertFalse($stratFit['missing']);
        $this->assertArrayHasKey('proxy_risk_flags', $stratFit,
            'property_strategy_fit slot must carry proxy_risk_flags sub-key for Landlord');
        $this->assertCount(1, $stratFit['proxy_risk_flags']);

        $inSlotFlag = $stratFit['proxy_risk_flags'][0];
        $this->assertSame('tenant_type_preference', $inSlotFlag['field']);
        $this->assertSame('property_strategy_fit', $inSlotFlag['trait']);
        $this->assertIsString($inSlotFlag['reason']);
        $this->assertNotEmpty($inSlotFlag['reason']);

        // Landlord naming crosswalks
        // communication_channel ← communication_style (channel data despite key name)
        $this->assertFalse($payload['traits']['communication_channel']['missing']);
        $this->assertSame('Phone Calls Preferred', $payload['traits']['communication_channel']['value']);

        // communication_frequency ← preferred_contact_method (frequency data despite key name)
        $this->assertFalse($payload['traits']['communication_frequency']['missing']);
        $this->assertSame('Weekly Check-Ins', $payload['traits']['communication_frequency']['value']);

        // decision_making_style — ABSENT for Landlord
        $this->assertTrue($payload['traits']['decision_making_style']['missing']);

        // transaction_pace — ABSENT for Landlord
        $this->assertTrue($payload['traits']['transaction_pace']['missing']);

        // representation_philosophy — ABSENT for Landlord
        $this->assertTrue($payload['traits']['representation_philosophy']['missing']);
        $this->assertArrayNotHasKey('proxy_risk_flags', $payload['traits']['representation_philosophy']);

        // proxy_risk_flags must NOT appear on other slots
        $this->assertArrayNotHasKey('proxy_risk_flags', $payload['traits']['communication_channel']);
        $this->assertArrayNotHasKey('proxy_risk_flags', $payload['traits']['communication_frequency']);
        $this->assertArrayNotHasKey('proxy_risk_flags', $payload['traits']['negotiation_style']);

        // All 12 traits present
        $this->assertCount(12, $payload['traits']);

        // informational_context has exactly 6 keys for Landlord
        $this->assertCount(6, $payload['informational_context']);
        $this->assertArrayHasKey('additional_representation_notes', $payload['informational_context']);
        $this->assertArrayHasKey('lease_duration_preference', $payload['informational_context']);
    }

    // -------------------------------------------------------------------------
    // Test 4: Tenant preferred_contact_method appears only in
    //         informational_context.preferred_contact_time_of_day and never
    //         in any trait slot
    // -------------------------------------------------------------------------

    public function test_tenant_preferred_contact_method_appears_only_in_informational_context(): void
    {
        $listing = $this->makeListingStub(9155, [
            'tenant_specific' => [
                'communication_style'              => 'Text / SMS',
                'contact_frequency'                => 'Every few days',
                'preferred_contact_method'         => 'Evening',  // time-of-day data, NOT a trait
                'preferred_agent_working_style'    => 'Highly proactive – send regular updates without prompting',
                'negotiation_style'                => 'Collaborative – find mutually beneficial terms',
                'primary_rental_goal'              => 'Find a long-term home',
                'representation_priorities'        => ['Neighborhood / location', 'Budget management'],
                'desired_level_of_agent_involvement' => 'Mostly Delegated – Agent leads, I approve key decisions',
                'most_important_agent_traits'      => ['Responsiveness', 'Local Expertise'],
                'concerns_or_barriers'             => 'I have a large dog.',
                'timeline_urgency'                 => 'Within 30 Days',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'tenant');

        // preferred_contact_method must appear in informational_context as preferred_contact_time_of_day
        $ic = $payload['informational_context'];
        $this->assertArrayHasKey('preferred_contact_time_of_day', $ic);
        $this->assertSame('Evening', $ic['preferred_contact_time_of_day']);

        // preferred_contact_method must NOT appear as a key in informational_context
        $this->assertArrayNotHasKey('preferred_contact_method', $ic);

        // preferred_contact_method must NOT feed any trait slot
        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertNotSame('Evening', $slot['value'],
                "Trait '{$traitKey}' must not contain the preferred_contact_method value");
            $this->assertArrayNotHasKey('preferred_contact_method', $slot,
                "Trait '{$traitKey}' must not have a preferred_contact_method sub-key");
        }

        // communication_channel ← communication_style (channel data)
        $this->assertFalse($payload['traits']['communication_channel']['missing']);
        $this->assertSame('Text / SMS', $payload['traits']['communication_channel']['value']);

        // communication_frequency ← contact_frequency (correctly named)
        $this->assertFalse($payload['traits']['communication_frequency']['missing']);
        $this->assertSame('Every few days', $payload['traits']['communication_frequency']['value']);

        // responsiveness_expectation — ABSENT for Tenant
        $this->assertTrue($payload['traits']['responsiveness_expectation']['missing']);

        // risk_tolerance — ABSENT for Tenant
        $this->assertTrue($payload['traits']['risk_tolerance']['missing']);

        // representation_philosophy — ABSENT for Tenant
        $this->assertTrue($payload['traits']['representation_philosophy']['missing']);

        // informational_context has exactly 11 keys for Tenant
        // (BYA_NORM_V1.1: budget_flexibility added — see Phase 5/6 QA Follow-up).
        $this->assertCount(11, $ic);
        $this->assertArrayHasKey('most_important_agent_traits', $ic);
        $this->assertArrayHasKey('concerns_or_barriers', $ic);
        $this->assertArrayHasKey('budget_flexibility', $ic);

        // proxy_risk_flags empty for Tenant
        $this->assertEmpty($payload['proxy_risk_flags']);
    }

    // -------------------------------------------------------------------------
    // Test 5: All 12 trait keys are always present regardless of role
    // -------------------------------------------------------------------------

    public function test_all_12_trait_keys_are_always_present_regardless_of_role(): void
    {
        $expectedKeys = [
            'communication_channel',
            'communication_frequency',
            'responsiveness_expectation',
            'negotiation_style',
            'guidance_level',
            'decision_making_style',
            'transaction_pace',
            'risk_tolerance',
            'collaboration_style',
            'representation_priorities',
            'representation_philosophy',
            'property_strategy_fit',
        ];

        $roles = ['seller', 'buyer', 'landlord', 'tenant'];

        foreach ($roles as $role) {
            $listing = $this->makeListingStub(100, [
                "{$role}_specific" => [
                    'negotiation_style' => 'Some Value',
                ],
            ]);

            $payload = $this->service->normalize($listing, $role);

            $this->assertCount(12, $payload['traits'],
                "Role '{$role}' must always emit exactly 12 trait keys");

            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $payload['traits'],
                    "Trait key '{$key}' is missing for role '{$role}'");
                $this->assertArrayHasKey('value', $payload['traits'][$key],
                    "Slot '{$key}' is missing 'value' for role '{$role}'");
                $this->assertArrayHasKey('missing', $payload['traits'][$key],
                    "Slot '{$key}' is missing 'missing' for role '{$role}'");
            }

            // Key order must match the canonical order
            $this->assertSame($expectedKeys, array_keys($payload['traits']),
                "Trait key order is incorrect for role '{$role}'");
        }
    }

    // -------------------------------------------------------------------------
    // Test 6: Skipped state and absent state remain distinct
    // -------------------------------------------------------------------------

    public function test_skipped_and_absent_states_are_distinct_and_correctly_assigned(): void
    {
        // Build a Seller listing where some fields are left blank (skipped)
        // and some are structurally absent (Seller has no risk_tolerance field).
        $listing = $this->makeListingStub(4821, [
            'seller_specific' => [
                // communication_style present but blank → skipped
                'communication_style'       => '',
                // decision_making_style key absent from raw → skipped
                // (key not in array, but field exists structurally for Seller)
                'preferred_contact_method'  => ['Phone Call'],
                'negotiation_style'         => 'Balanced — Fair & Reasonable',
                'involvement_level'         => 'Moderately Involved — Major steps only',
                'preferred_agent_working_style' => 'Consultative & Guides Me',
                'primary_transaction_goal'  => 'Quick Sale',
                'representation_priorities' => [],
                // flexibility_on_timeline not provided → skipped
            ],
        ]);

        $payload = $this->service->normalize($listing, 'seller');

        // communication_frequency ← communication_style (blank) → SKIPPED
        $commFreq = $payload['traits']['communication_frequency'];
        $this->assertNull($commFreq['value'],
            'Skipped field must have value: null');
        $this->assertFalse($commFreq['missing'],
            'Skipped field must have missing: false');

        // decision_making_style key absent from raw array, but structurally exists for Seller → SKIPPED
        $dms = $payload['traits']['decision_making_style'];
        $this->assertNull($dms['value'],
            'Skipped field (key absent from raw) must have value: null');
        $this->assertFalse($dms['missing'],
            'Skipped field (key absent from raw) must have missing: false — not absent');

        // transaction_pace ← flexibility_on_timeline not provided → SKIPPED
        $pace = $payload['traits']['transaction_pace'];
        $this->assertNull($pace['value']);
        $this->assertFalse($pace['missing']);

        // risk_tolerance — structurally ABSENT for Seller
        $risk = $payload['traits']['risk_tolerance'];
        $this->assertNull($risk['value'],
            'Absent field must have value: null');
        $this->assertTrue($risk['missing'],
            'Absent field must have missing: true');

        // responsiveness_expectation — key absent from raw, but structurally exists for Seller → SKIPPED
        $resp = $payload['traits']['responsiveness_expectation'];
        $this->assertNull($resp['value']);
        $this->assertFalse($resp['missing'],
            'responsiveness_expectation is structurally present for Seller — must be skipped not absent');

        // representation_priorities provided as empty array → skipped (null value, missing: false)
        $reprPrio = $payload['traits']['representation_priorities'];
        $this->assertNull($reprPrio['value']);
        $this->assertFalse($reprPrio['missing']);

        // Confirm the three-state distinction is preserved across both "kinds" of skipped:
        //   skipped (blank value) vs absent (missing: true) must remain different
        $this->assertNotSame(
            $payload['traits']['communication_frequency']['missing'],
            $payload['traits']['risk_tolerance']['missing'],
            'Skipped state (missing: false) and absent state (missing: true) must be different'
        );
    }

    // -------------------------------------------------------------------------
    // Test 7: No scoring fields, no compatibility labels, no public/UI output
    // -------------------------------------------------------------------------

    public function test_payload_contains_no_scoring_fields_no_labels_and_no_ui_output(): void
    {
        $listing = $this->makeListingStub(999, [
            'seller_specific' => [
                'communication_style'           => 'Frequent & Proactive',
                'preferred_contact_method'      => ['Email'],
                'negotiation_style'             => 'Balanced — Fair & Reasonable',
                'primary_transaction_goal'      => 'Maximum Sale Price',
                'representation_priorities'     => ['Market Expertise'],
                'preferred_agent_working_style' => 'Proactive & Takes Initiative',
                'involvement_level'             => 'Mostly Hands-Off — I trust my agent',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'seller');

        $forbiddenTopLevelKeys = [
            'representation_compatibility_score',
            'match_score',
            'score',
            'compatibility_score',
            'compatibility_label',
            'label',
            'recommendation',
            'recommended',
            'ranking',
            'rank',
            'explanation',
            'ai_explanation',
            'summary',
            'html',
            'blade',
        ];

        foreach ($forbiddenTopLevelKeys as $key) {
            $this->assertArrayNotHasKey($key, $payload,
                "Forbidden key '{$key}' must not appear in payload top level");
        }

        // No scoring fields in any trait slot
        $forbiddenSlotKeys = [
            'score', 'weight', 'compatibility_score', 'match_score',
            'label', 'recommendation', 'explanation', 'rank',
        ];

        foreach ($payload['traits'] as $traitKey => $slot) {
            foreach ($forbiddenSlotKeys as $key) {
                $this->assertArrayNotHasKey($key, $slot,
                    "Forbidden key '{$key}' must not appear in trait slot '{$traitKey}'");
            }
        }

        // informational_context must not contain trait values used for scoring
        $forbiddenContextKeys = [
            'representation_compatibility_score',
            'match_score',
            'score',
            'label',
            'recommendation',
        ];

        foreach ($forbiddenContextKeys as $key) {
            $this->assertArrayNotHasKey($key, $payload['informational_context'],
                "Forbidden key '{$key}' must not appear in informational_context");
        }

        // Confirm the normalization_version is exactly the expected constant
        $this->assertSame('BYA_NORM_V1', $payload['normalization_version']);

        // No extra top-level keys beyond the 6 defined in the schema
        $allowedTopLevelKeys = [
            'normalization_version',
            'role',
            'listing_id',
            'traits',
            'informational_context',
            'proxy_risk_flags',
        ];
        $actualTopLevelKeys = array_keys($payload);
        sort($allowedTopLevelKeys);
        sort($actualTopLevelKeys);
        $this->assertSame(
            $allowedTopLevelKeys,
            $actualTopLevelKeys,
            'Payload must contain exactly the 6 defined top-level keys'
        );
    }

    // -------------------------------------------------------------------------
    // Test 8: Null-safe guard — listing with no compatibility_preferences meta
    //         returns a valid payload with all traits in absent or skipped state
    // -------------------------------------------------------------------------

    public function test_null_safe_guard_no_meta_returns_valid_payload_with_all_traits_in_skipped_state(): void
    {
        // A listing stub whose info() always returns null — simulates a listing
        // that was created before compatibility_preferences were added, or a listing
        // where the consumer never reached the compatibility tab.
        $listing = $this->makeEmptyListingStub(1234);

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $payload = $this->service->normalize($listing, $role);

            // Must return a valid envelope without throwing
            $this->assertSame('BYA_NORM_V1', $payload['normalization_version'],
                "Role '{$role}': normalization_version must be BYA_NORM_V1");
            $this->assertSame($role, $payload['role'],
                "Role '{$role}': role key must be present");
            $this->assertSame(1234, $payload['listing_id']);
            $this->assertArrayHasKey('traits', $payload);
            $this->assertArrayHasKey('informational_context', $payload);
            $this->assertArrayHasKey('proxy_risk_flags', $payload);

            // All 12 trait keys must be present
            $this->assertCount(12, $payload['traits'],
                "Role '{$role}': must emit 12 trait slots even with no meta");

            // All slots must have value: null and a valid missing boolean
            // (either skipped or absent, depending on role — never throwing)
            foreach ($payload['traits'] as $traitKey => $slot) {
                $this->assertNull($slot['value'],
                    "Role '{$role}', trait '{$traitKey}': value must be null when no meta");
                $this->assertIsBool($slot['missing'],
                    "Role '{$role}', trait '{$traitKey}': missing must be boolean");
            }

            // proxy_risk_flags must always be an array
            $this->assertIsArray($payload['proxy_risk_flags']);

            // informational_context must always be an array
            $this->assertIsArray($payload['informational_context']);
        }
    }

    // -------------------------------------------------------------------------
    // Additional: unknown role returns structurally valid stub with role "unknown"
    // -------------------------------------------------------------------------

    public function test_unknown_role_returns_structurally_valid_stub(): void
    {
        $listing = $this->makeListingStub(999, []);

        $payload = $this->service->normalize($listing, 'invalid_role');

        $this->assertSame('BYA_NORM_V1', $payload['normalization_version']);
        $this->assertSame('unknown', $payload['role']);
        $this->assertCount(12, $payload['traits']);
        $this->assertIsArray($payload['proxy_risk_flags']);
        $this->assertIsArray($payload['informational_context']);

        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertNull($slot['value']);
            $this->assertTrue($slot['missing'],
                "Unknown role: all traits must be absent (missing: true), got false for '{$traitKey}'");
        }
    }

    // -------------------------------------------------------------------------
    // Additional: Landlord without tenant_type_preference has empty proxy flags
    // -------------------------------------------------------------------------

    public function test_landlord_without_tenant_type_preference_has_empty_proxy_risk_flags(): void
    {
        $listing = $this->makeListingStub(2291, [
            'landlord_specific' => [
                'communication_style'           => 'Email Only',
                'preferred_contact_method'      => 'Only Major Milestones',
                'negotiation_style'             => 'Firm on Terms',
                'primary_leasing_goal'          => 'Maximize Monthly Rent',
                'preferred_agent_working_style' => 'Data-Driven & Analytical',
                'property_management_involvement' => 'Hands-Off (Agent Manages All)',
                'representation_priorities'     => ['Marketing & Advertising'],
                // tenant_type_preference intentionally absent
            ],
        ]);

        $payload = $this->service->normalize($listing, 'landlord');

        $this->assertEmpty($payload['proxy_risk_flags'],
            'Landlord without tenant_type_preference must have empty top-level proxy_risk_flags');

        $this->assertArrayNotHasKey('proxy_risk_flags',
            $payload['traits']['property_strategy_fit'],
            'property_strategy_fit must not carry proxy_risk_flags when tenant_type_preference is absent');
    }

    // -------------------------------------------------------------------------
    // Phase 5/6 QA Follow-up: literal "Other" resolves to the user's companion
    // free-text so Ask AI / narrative never surface the bare placeholder "Other".
    // -------------------------------------------------------------------------

    /** Scalar "Other" goal resolves via the suffix companion (Seller/Buyer/Landlord form). */
    public function test_other_goal_resolves_to_suffix_companion_seller(): void
    {
        $listing = $this->makeListingStub(9001, [
            'seller_specific' => [
                'primary_transaction_goal'       => 'Other',
                'primary_transaction_goal_other' => 'Sell quickly to relocate',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'seller');

        $this->assertSame('Sell quickly to relocate',
            $payload['traits']['property_strategy_fit']['value']);
    }

    /** Scalar "Other" goal resolves via the prefix companion (Tenant naming form). */
    public function test_other_goal_resolves_to_prefix_companion_tenant(): void
    {
        $listing = $this->makeListingStub(9002, [
            'tenant_specific' => [
                'primary_rental_goal'       => 'Other',
                'other_primary_rental_goal' => 'Short-term corporate housing',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'tenant');

        $this->assertSame('Short-term corporate housing',
            $payload['traits']['property_strategy_fit']['value']);
    }

    /** A multi-select "Other" element is replaced by the companion free-text (Buyer). */
    public function test_other_in_priorities_array_resolves_buyer(): void
    {
        $listing = $this->makeListingStub(9003, [
            'buyer_specific' => [
                'representation_priorities'       => ['Market Expertise', 'Other'],
                'representation_priorities_other' => 'Bilingual negotiation',
            ],
        ]);

        $payload = $this->service->normalize($listing, 'buyer');
        $vals    = $payload['traits']['representation_priorities']['value'];

        $this->assertContains('Bilingual negotiation', $vals);
        $this->assertNotContains('Other', $vals);
    }

    /** With no companion text, the literal value is preserved (no data loss). */
    public function test_other_without_companion_is_preserved(): void
    {
        $listing = $this->makeListingStub(9004, [
            'seller_specific' => ['primary_transaction_goal' => 'Other'],
        ]);

        $payload = $this->service->normalize($listing, 'seller');

        $this->assertSame('Other', $payload['traits']['property_strategy_fit']['value']);
    }

    /**
     * BYA_NORM_V1.1: Tenant budget_flexibility now surfaces in informational_context so
     * Ask AI can read it (previously unrepresented). Buyer "Meeting / Showing Preference"
     * (communication_frequency) is already covered by collaboration_style.showing_format_preference.
     */
    public function test_tenant_budget_flexibility_surfaces_to_ask_ai(): void
    {
        $listing = $this->makeListingStub(9005, [
            'tenant_specific' => ['budget_flexibility' => 'Flexible for the right home'],
        ]);

        $payload = $this->service->normalize($listing, 'tenant');

        $this->assertArrayHasKey('budget_flexibility', $payload['informational_context']);
        $this->assertSame('Flexible for the right home',
            $payload['informational_context']['budget_flexibility']);
    }
}
