<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaAgentResponseNormalizationService;
use PHPUnit\Framework\TestCase;

/**
 * ByaAgentResponseNormalizationServiceTest
 *
 * Tests the BYA_AGENT_NORM_V1 agent-side normalization engine against in-memory
 * agent bid stubs. No database connection is required — all test data is fabricated inline.
 *
 * Each test asserts:
 *   - Structural shape of the returned payload (version, role, bid_id, traits, etc.)
 *   - Correct 12-trait presence with value/missing slot shape
 *   - Section-to-trait mapping correctness (all 7 sections)
 *   - Missing section produces skipped state (missing: false), not absent (missing: true)
 *   - Unknown role produces absent state (missing: true) for all traits
 *   - informational_context separation from trait values
 *   - Proxy-risk fields surfaced in proxy_risk_flags only
 *   - Absence of scoring, comparison, AI, and label fields
 */
class ByaAgentResponseNormalizationServiceTest extends TestCase
{
    private ByaAgentResponseNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaAgentResponseNormalizationService();
    }

    // -------------------------------------------------------------------------
    // Helpers: build in-memory agent bid stubs
    // -------------------------------------------------------------------------

    /**
     * Build an agent bid stub that returns sections via loadCompatibilityPreferences().
     *
     * Mirrors how HasCompatibilityPreferences::loadCompatibilityPreferences() returns
     * an associative array of section => data (or null for missing sections).
     *
     * @param  int    $id
     * @param  array  $sections  Associative array: section_name => data_array|null
     */
    private function makeAgentBidStub(int $id, array $sections): object
    {
        return new class ($id, $sections) {
            public int $id;
            private array $sections;

            public function __construct(int $id, array $sections)
            {
                $this->id       = $id;
                $this->sections = $sections;
            }

            public function loadCompatibilityPreferences(): array
            {
                return $this->sections;
            }

            public function loadCompatibilitySection(string $section): ?array
            {
                return $this->sections[$section] ?? null;
            }
        };
    }

    /**
     * Build an agent bid stub with no compatibility data at all.
     */
    private function makeEmptyAgentBidStub(int $id): object
    {
        return new class ($id) {
            public int $id;

            public function __construct(int $id)
            {
                $this->id = $id;
            }

            public function loadCompatibilityPreferences(): array
            {
                return array_fill_keys([
                    'communication_preferences',
                    'negotiation_approach',
                    'guidance_style',
                    'collaboration_preferences',
                    'transaction_strategy',
                    'representation_philosophy',
                    'representation_priorities',
                ], null);
            }
        };
    }

    /**
     * Build an agent bid stub without a loadCompatibilityPreferences method at all.
     */
    private function makeNakedBidStub(int $id): object
    {
        return new class ($id) {
            public int $id;

            public function __construct(int $id)
            {
                $this->id = $id;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Helper: the full section set for a "complete" agent response
    // -------------------------------------------------------------------------

    private function fullSections(): array
    {
        return [
            'communication_preferences' => [
                'agent_communication_channels'    => ['Email', 'Phone Call', 'Text/SMS'],
                'agent_communication_frequency'   => 'Weekly',
                'agent_response_time_commitment'  => 'Within a Few Hours',
                'agent_communication_notes'       => 'I send a summary email every Friday.',
                'agent_availability_notes'        => 'Available Mon–Sat 8am–7pm.',
            ],
            'negotiation_approach' => [
                'agent_negotiation_style'  => 'Balanced',
                'agent_negotiation_notes'  => 'I always start collaborative and adjust as needed.',
            ],
            'guidance_style' => [
                'agent_guidance_level' => 'Mostly managed',
                'agent_guidance_notes' => 'I handle the paperwork; clients approve key steps.',
            ],
            'collaboration_preferences' => [
                'agent_collaboration_style'  => 'Proactive',
                'agent_availability_windows' => 'Weekdays and weekend mornings.',
            ],
            'transaction_strategy' => [
                'agent_transaction_pace'    => 'I work well with firm timelines',
                'agent_strategy_experience' => ['Primary Residence', 'Investment Property'],
                'agent_strategy_notes'      => 'Experienced with both first-time buyers and investors.',
            ],
            'representation_philosophy' => [
                'agent_representation_philosophy' => 'Client-first always',
                'agent_decision_support_style'    => 'I present a clear recommendation',
                'agent_risk_posture'              => 'Moderate',
                'agent_philosophy_narrative'      => 'I believe in radical transparency with every client.',
                'agent_philosophy_notes'          => 'Honesty is non-negotiable.',
            ],
            'representation_priorities' => [
                'agent_representation_priorities' => ['Negotiation', 'Communication', 'Market Expertise'],
                'agent_priority_notes'            => 'I focus most on strong offer strategy.',
            ],
        ];
    }

    // =========================================================================
    // TEST 1: Payload envelope — all required top-level keys always present
    // =========================================================================

    public function test_payload_envelope_always_contains_all_required_top_level_keys(): void
    {
        $bid     = $this->makeAgentBidStub(101, $this->fullSections());
        $payload = $this->service->normalize($bid, 'seller');

        $this->assertSame('BYA_AGENT_NORM_V1', $payload['normalization_version']);
        $this->assertSame('seller', $payload['role']);
        $this->assertSame(101, $payload['bid_id']);
        $this->assertArrayHasKey('traits', $payload);
        $this->assertArrayHasKey('informational_context', $payload);
        $this->assertArrayHasKey('proxy_risk_flags', $payload);

        $this->assertIsArray($payload['traits']);
        $this->assertIsArray($payload['informational_context']);
        $this->assertIsArray($payload['proxy_risk_flags']);
    }

    // =========================================================================
    // TEST 2: All 12 canonical trait keys always present
    // =========================================================================

    public function test_traits_always_contains_all_12_canonical_keys(): void
    {
        $bid     = $this->makeAgentBidStub(102, $this->fullSections());
        $payload = $this->service->normalize($bid, 'buyer');

        $this->assertCount(12, $payload['traits']);

        $expected = [
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

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $payload['traits'], "Trait key '{$key}' is missing from payload");
        }
    }

    // =========================================================================
    // TEST 3: Every slot has 'value' and 'missing' boolean keys
    // =========================================================================

    public function test_every_trait_slot_has_value_and_missing_boolean_keys(): void
    {
        $bid     = $this->makeAgentBidStub(103, $this->fullSections());
        $payload = $this->service->normalize($bid, 'landlord');

        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertArrayHasKey('value', $slot,
                "Slot '{$traitKey}' is missing 'value' key");
            $this->assertArrayHasKey('missing', $slot,
                "Slot '{$traitKey}' is missing 'missing' key");
            $this->assertIsBool($slot['missing'],
                "Slot '{$traitKey}'.missing must be boolean");
        }
    }

    // =========================================================================
    // TEST 4: Section mapping — communication_preferences → 3 traits
    // =========================================================================

    public function test_communication_preferences_section_feeds_three_traits_correctly(): void
    {
        $bid = $this->makeAgentBidStub(104, [
            'communication_preferences' => [
                'agent_communication_channels'   => ['Email', 'Phone Call'],
                'agent_communication_frequency'  => 'Weekly',
                'agent_response_time_commitment' => 'Within a Few Hours',
            ],
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'seller');

        // communication_channel — multi-select from agent_communication_channels
        $cc = $payload['traits']['communication_channel'];
        $this->assertFalse($cc['missing']);
        $this->assertIsArray($cc['value']);
        $this->assertContains('Email', $cc['value']);
        $this->assertContains('Phone Call', $cc['value']);

        // communication_frequency — single-select from agent_communication_frequency
        $cf = $payload['traits']['communication_frequency'];
        $this->assertFalse($cf['missing']);
        $this->assertSame('Weekly', $cf['value']);

        // responsiveness_expectation — single-select from agent_response_time_commitment
        $re = $payload['traits']['responsiveness_expectation'];
        $this->assertFalse($re['missing']);
        $this->assertSame('Within a Few Hours', $re['value']);
    }

    // =========================================================================
    // TEST 5: Section mapping — negotiation_approach → negotiation_style
    // =========================================================================

    public function test_negotiation_approach_section_feeds_negotiation_style(): void
    {
        $bid = $this->makeAgentBidStub(105, [
            'communication_preferences'  => null,
            'negotiation_approach' => [
                'agent_negotiation_style' => 'Collaborative',
                'agent_negotiation_notes' => 'I prefer win-win outcomes.',
            ],
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'buyer');

        $ns = $payload['traits']['negotiation_style'];
        $this->assertFalse($ns['missing']);
        $this->assertSame('Collaborative', $ns['value']);
    }

    // =========================================================================
    // TEST 6: Section mapping — guidance_style → guidance_level
    // =========================================================================

    public function test_guidance_style_section_feeds_guidance_level(): void
    {
        $bid = $this->makeAgentBidStub(106, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style' => [
                'agent_guidance_level' => 'Mostly managed',
                'agent_guidance_notes' => 'Clients approve key steps.',
            ],
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'landlord');

        $gl = $payload['traits']['guidance_level'];
        $this->assertFalse($gl['missing']);
        $this->assertSame('Mostly managed', $gl['value']);
    }

    // =========================================================================
    // TEST 7: Section mapping — collaboration_preferences → collaboration_style
    // =========================================================================

    public function test_collaboration_preferences_section_feeds_collaboration_style(): void
    {
        $bid = $this->makeAgentBidStub(107, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences' => [
                'agent_collaboration_style'  => 'Proactive',
                'agent_availability_windows' => 'Weekdays 9am–6pm.',
            ],
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'tenant');

        $cs = $payload['traits']['collaboration_style'];
        $this->assertFalse($cs['missing']);
        $this->assertSame('Proactive', $cs['value']);
    }

    // =========================================================================
    // TEST 8: Section mapping — transaction_strategy → transaction_pace + property_strategy_fit
    // =========================================================================

    public function test_transaction_strategy_section_feeds_two_traits(): void
    {
        $bid = $this->makeAgentBidStub(108, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy' => [
                'agent_transaction_pace'    => 'Urgent timelines are my specialty',
                'agent_strategy_experience' => ['Quick Sale', 'Fix & Flip'],
                'agent_strategy_notes'      => 'I specialize in fast-close transactions.',
            ],
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'seller');

        // transaction_pace
        $tp = $payload['traits']['transaction_pace'];
        $this->assertFalse($tp['missing']);
        $this->assertSame('Urgent timelines are my specialty', $tp['value']);

        // property_strategy_fit — multi-select
        $psf = $payload['traits']['property_strategy_fit'];
        $this->assertFalse($psf['missing']);
        $this->assertIsArray($psf['value']);
        $this->assertContains('Quick Sale', $psf['value']);
        $this->assertContains('Fix & Flip', $psf['value']);
    }

    // =========================================================================
    // TEST 9: Section mapping — representation_philosophy → 3 traits
    // =========================================================================

    public function test_representation_philosophy_section_feeds_three_traits(): void
    {
        $bid = $this->makeAgentBidStub(109, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy' => [
                'agent_representation_philosophy' => 'Client-first always',
                'agent_decision_support_style'    => 'I present a clear recommendation',
                'agent_risk_posture'              => 'Moderate',
                'agent_philosophy_narrative'      => 'Transparency above all.',
            ],
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'buyer');

        // representation_philosophy
        $rp = $payload['traits']['representation_philosophy'];
        $this->assertFalse($rp['missing']);
        $this->assertSame('Client-first always', $rp['value']);

        // decision_making_style
        $dms = $payload['traits']['decision_making_style'];
        $this->assertFalse($dms['missing']);
        $this->assertSame('I present a clear recommendation', $dms['value']);

        // risk_tolerance (from agent_risk_posture, the general risk comfort field)
        $rt = $payload['traits']['risk_tolerance'];
        $this->assertFalse($rt['missing']);
        $this->assertSame('Moderate', $rt['value']);
    }

    // =========================================================================
    // TEST 10: Section mapping — representation_priorities → representation_priorities trait
    // =========================================================================

    public function test_representation_priorities_section_feeds_representation_priorities_trait(): void
    {
        $bid = $this->makeAgentBidStub(110, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities' => [
                'agent_representation_priorities' => ['Negotiation', 'Communication'],
                'agent_priority_notes'            => 'My top priority is strong offer strategy.',
            ],
        ]);

        $payload = $this->service->normalize($bid, 'landlord');

        $reprPri = $payload['traits']['representation_priorities'];
        $this->assertFalse($reprPri['missing']);
        $this->assertIsArray($reprPri['value']);
        $this->assertContains('Negotiation', $reprPri['value']);
        $this->assertContains('Communication', $reprPri['value']);
    }

    // =========================================================================
    // TEST 11: Missing section → skipped state (missing: false, value: null)
    //          NOT absent (missing: true)
    // =========================================================================

    public function test_missing_section_produces_skipped_state_not_absent(): void
    {
        $bid = $this->makeAgentBidStub(111, [
            'communication_preferences' => [
                'agent_communication_channels'  => ['Email'],
                'agent_communication_frequency' => 'Weekly',
            ],
            // All other sections are null (missing)
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'seller');

        // Traits from missing sections must be SKIPPED (missing: false), not absent
        $skippedTraits = [
            'negotiation_style',
            'guidance_level',
            'collaboration_style',
            'transaction_pace',
            'property_strategy_fit',
            'decision_making_style',
            'risk_tolerance',
            'representation_philosophy',
            'representation_priorities',
        ];

        foreach ($skippedTraits as $traitKey) {
            $slot = $payload['traits'][$traitKey];
            $this->assertFalse(
                $slot['missing'],
                "Trait '{$traitKey}' should be skipped (missing: false) when section is null, not absent (missing: true)"
            );
            $this->assertNull(
                $slot['value'],
                "Trait '{$traitKey}' should have null value when section is missing"
            );
        }

        // Traits from the populated section are answered
        $this->assertFalse($payload['traits']['communication_channel']['missing']);
        $this->assertNotNull($payload['traits']['communication_channel']['value']);
        $this->assertFalse($payload['traits']['communication_frequency']['missing']);
    }

    // =========================================================================
    // TEST 12: Missing key within a present section → skipped (missing: false)
    // =========================================================================

    public function test_missing_key_within_present_section_produces_skipped_state(): void
    {
        $bid = $this->makeAgentBidStub(112, [
            'communication_preferences' => [
                'agent_communication_channels' => ['Email'],
                // agent_communication_frequency and agent_response_time_commitment absent
            ],
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'buyer');

        // communication_channel is answered
        $this->assertFalse($payload['traits']['communication_channel']['missing']);
        $this->assertNotNull($payload['traits']['communication_channel']['value']);

        // communication_frequency key is absent → skipped (missing: false, value: null)
        $cf = $payload['traits']['communication_frequency'];
        $this->assertFalse($cf['missing'],
            'Missing key within present section must produce skipped (missing: false), not absent');
        $this->assertNull($cf['value']);

        // responsiveness_expectation key is absent → skipped
        $re = $payload['traits']['responsiveness_expectation'];
        $this->assertFalse($re['missing'],
            'Missing key within present section must produce skipped (missing: false), not absent');
        $this->assertNull($re['value']);
    }

    // =========================================================================
    // TEST 13: Unknown role → stub payload with all traits absent (missing: true)
    // =========================================================================

    public function test_unknown_role_produces_stub_payload_with_all_traits_absent(): void
    {
        $bid     = $this->makeAgentBidStub(113, $this->fullSections());
        $payload = $this->service->normalize($bid, 'unknown_role');

        $this->assertSame('BYA_AGENT_NORM_V1', $payload['normalization_version']);
        $this->assertSame('unknown', $payload['role']);
        $this->assertCount(12, $payload['traits']);

        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertTrue(
                $slot['missing'],
                "Unknown role: trait '{$traitKey}' must be absent (missing: true)"
            );
            $this->assertNull(
                $slot['value'],
                "Unknown role: trait '{$traitKey}' value must be null"
            );
        }

        $this->assertIsArray($payload['informational_context']);
        $this->assertIsArray($payload['proxy_risk_flags']);
    }

    // =========================================================================
    // TEST 14: Role variants (seller, buyer, landlord, tenant) all produce valid payloads
    // =========================================================================

    public function test_all_four_supported_roles_produce_valid_payloads(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $bid     = $this->makeAgentBidStub(200 + array_search($role, ['seller', 'buyer', 'landlord', 'tenant']), $this->fullSections());
            $payload = $this->service->normalize($bid, $role);

            $this->assertSame('BYA_AGENT_NORM_V1', $payload['normalization_version'],
                "Role '{$role}': normalization_version wrong");
            $this->assertSame($role, $payload['role'],
                "Role '{$role}': role field wrong");
            $this->assertCount(12, $payload['traits'],
                "Role '{$role}': must have exactly 12 traits");

            foreach ($payload['traits'] as $traitKey => $slot) {
                $this->assertFalse($slot['missing'],
                    "Role '{$role}', trait '{$traitKey}': should not be absent when all sections are populated");
            }
        }
    }

    // =========================================================================
    // TEST 15: Role is normalised to lowercase
    // =========================================================================

    public function test_role_string_is_normalised_to_lowercase(): void
    {
        $bid = $this->makeAgentBidStub(115, $this->fullSections());

        $payload = $this->service->normalize($bid, 'SELLER');
        $this->assertSame('seller', $payload['role']);

        $payload2 = $this->service->normalize($bid, '  Buyer  ');
        $this->assertSame('buyer', $payload2['role']);
    }

    // =========================================================================
    // TEST 16: informational_context is separate from trait values
    //          Free-text fields must not appear as trait slot values
    // =========================================================================

    public function test_informational_context_is_separate_from_trait_values(): void
    {
        $bid = $this->makeAgentBidStub(116, [
            'communication_preferences' => [
                'agent_communication_channels'   => ['Email'],
                'agent_communication_frequency'  => 'Weekly',
                'agent_response_time_commitment' => 'Within a Few Hours',
                'agent_communication_notes'      => 'I send weekly update emails.',
                'agent_availability_notes'       => 'Available Mon–Sat.',
            ],
            'negotiation_approach' => [
                'agent_negotiation_style' => 'Balanced',
                'agent_negotiation_notes' => 'I start collaborative.',
            ],
            'guidance_style' => [
                'agent_guidance_level' => 'Mostly managed',
                'agent_guidance_notes' => 'Clients approve key steps.',
            ],
            'collaboration_preferences' => [
                'agent_collaboration_style'  => 'Proactive',
                'agent_availability_windows' => 'Weekdays only.',
            ],
            'transaction_strategy' => [
                'agent_transaction_pace'    => 'Flexible',
                'agent_strategy_experience' => ['Primary Residence'],
                'agent_strategy_notes'      => 'I adapt to client timelines.',
            ],
            'representation_philosophy' => [
                'agent_representation_philosophy' => 'Client-first',
                'agent_decision_support_style'    => 'Data-driven',
                'agent_risk_posture'              => 'Moderate',
                'agent_philosophy_narrative'      => 'Transparency is non-negotiable.',
                'agent_philosophy_notes'          => 'Always honest.',
            ],
            'representation_priorities' => [
                'agent_representation_priorities' => ['Negotiation'],
                'agent_priority_notes'            => 'Offer strategy is my focus.',
            ],
        ]);

        $payload = $this->service->normalize($bid, 'seller');

        $ic = $payload['informational_context'];
        $traits = $payload['traits'];

        // Free-text notes must be in informational_context, not trait values
        $this->assertArrayHasKey('agent_communication_notes', $ic);
        $this->assertSame('I send weekly update emails.', $ic['agent_communication_notes']);

        $this->assertArrayHasKey('agent_availability_notes', $ic);
        $this->assertArrayHasKey('agent_negotiation_notes', $ic);
        $this->assertArrayHasKey('agent_guidance_notes', $ic);
        $this->assertArrayHasKey('agent_availability_windows', $ic);
        $this->assertArrayHasKey('agent_strategy_notes', $ic);
        $this->assertArrayHasKey('agent_philosophy_narrative', $ic);
        $this->assertArrayHasKey('agent_philosophy_notes', $ic);
        $this->assertArrayHasKey('agent_priority_notes', $ic);

        // None of the narrative/notes text must appear as a primary trait slot value
        $traitValues = array_map(fn ($slot) => $slot['value'], $traits);
        $this->assertNotContains('I send weekly update emails.', $traitValues,
            'agent_communication_notes must not appear as a trait value');
        $this->assertNotContains('Transparency is non-negotiable.', $traitValues,
            'agent_philosophy_narrative must not appear as a trait value');
        $this->assertNotContains('I adapt to client timelines.', $traitValues,
            'agent_strategy_notes must not appear as a trait value');
    }

    // =========================================================================
    // TEST 17: Proxy-risk fields — agent_tenant_screening_strictness flagged
    // =========================================================================

    public function test_agent_tenant_screening_strictness_is_flagged_in_proxy_risk_flags(): void
    {
        $bid = $this->makeAgentBidStub(117, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy' => [
                'agent_risk_posture'                 => 'Moderate',
                'agent_representation_philosophy'    => 'Client-first',
                'agent_decision_support_style'       => 'Data-driven',
                'agent_tenant_screening_strictness'  => 'Strict — I only place highly qualified tenants',
            ],
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'landlord');

        // Must appear in proxy_risk_flags
        $this->assertNotEmpty($payload['proxy_risk_flags']);
        $flagFields = array_column($payload['proxy_risk_flags'], 'field');
        $this->assertContains('agent_tenant_screening_strictness', $flagFields);

        // Flag must have field, section, and reason keys
        $flag = array_values(array_filter(
            $payload['proxy_risk_flags'],
            fn ($f) => $f['field'] === 'agent_tenant_screening_strictness'
        ))[0];
        $this->assertSame('representation_philosophy', $flag['section']);
        $this->assertIsString($flag['reason']);
        $this->assertNotEmpty($flag['reason']);

        // Must NOT appear as risk_tolerance trait value
        $rt = $payload['traits']['risk_tolerance'];
        $this->assertNotSame('Strict — I only place highly qualified tenants', $rt['value'],
            'agent_tenant_screening_strictness must not be used as a trait value');

        // Must be in informational_context raw capture
        $this->assertArrayHasKey('agent_tenant_screening_strictness_raw', $payload['informational_context']);
        $this->assertSame(
            'Strict — I only place highly qualified tenants',
            $payload['informational_context']['agent_tenant_screening_strictness_raw']
        );
    }

    // =========================================================================
    // TEST 18: Proxy-risk fields — agent_tenant_profile_specialization flagged
    // =========================================================================

    public function test_agent_tenant_profile_specialization_is_flagged_in_proxy_risk_flags(): void
    {
        $bid = $this->makeAgentBidStub(118, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy' => [
                'agent_representation_philosophy'      => 'Client-first',
                'agent_decision_support_style'         => 'Data-driven',
                'agent_risk_posture'                   => 'Moderate',
                'agent_tenant_profile_specialization'  => 'Corporate / Relocation tenants',
            ],
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'landlord');

        $flagFields = array_column($payload['proxy_risk_flags'], 'field');
        $this->assertContains('agent_tenant_profile_specialization', $flagFields);

        $flag = array_values(array_filter(
            $payload['proxy_risk_flags'],
            fn ($f) => $f['field'] === 'agent_tenant_profile_specialization'
        ))[0];
        $this->assertSame('representation_philosophy', $flag['section']);
        $this->assertIsString($flag['reason']);
        $this->assertNotEmpty($flag['reason']);

        // Must be in informational_context
        $this->assertArrayHasKey('agent_tenant_profile_specialization_raw', $payload['informational_context']);

        // Must NOT appear as any trait slot value
        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertNotSame(
                'Corporate / Relocation tenants',
                $slot['value'],
                "agent_tenant_profile_specialization must not appear as trait value for '{$traitKey}'"
            );
        }
    }

    // =========================================================================
    // TEST 19: Proxy-risk fields — agent_property_strategy_specialization flagged
    // =========================================================================

    public function test_agent_property_strategy_specialization_is_flagged_in_proxy_risk_flags(): void
    {
        $bid = $this->makeAgentBidStub(119, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy' => [
                'agent_transaction_pace'                 => 'Flexible',
                'agent_strategy_experience'              => ['Primary Residence'],
                'agent_property_strategy_specialization' => 'Investment properties in opportunity zones',
            ],
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'buyer');

        $flagFields = array_column($payload['proxy_risk_flags'], 'field');
        $this->assertContains('agent_property_strategy_specialization', $flagFields);

        $flag = array_values(array_filter(
            $payload['proxy_risk_flags'],
            fn ($f) => $f['field'] === 'agent_property_strategy_specialization'
        ))[0];
        $this->assertSame('transaction_strategy', $flag['section']);
        $this->assertIsString($flag['reason']);
        $this->assertNotEmpty($flag['reason']);

        // Must be in informational_context
        $this->assertArrayHasKey('agent_property_strategy_specialization_raw', $payload['informational_context']);

        // The property_strategy_fit trait value must NOT be the specialization field value
        $psf = $payload['traits']['property_strategy_fit'];
        $this->assertNotSame(
            'Investment properties in opportunity zones',
            $psf['value'],
            'agent_property_strategy_specialization must not appear as property_strategy_fit trait value'
        );
        // It should come from agent_strategy_experience instead
        $this->assertNotNull($psf['value']);
        $this->assertSame(['Primary Residence'], $psf['value']);
    }

    // =========================================================================
    // TEST 20: All three proxy-risk fields simultaneously
    // =========================================================================

    public function test_all_three_proxy_risk_fields_simultaneously_produces_three_flags(): void
    {
        $bid = $this->makeAgentBidStub(120, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy' => [
                'agent_transaction_pace'                 => 'Flexible',
                'agent_strategy_experience'              => ['Lease Renewal'],
                'agent_property_strategy_specialization' => 'Affordable housing specialists',
            ],
            'representation_philosophy' => [
                'agent_representation_philosophy'      => 'Client-first',
                'agent_decision_support_style'         => 'Data-driven',
                'agent_risk_posture'                   => 'Flexible',
                'agent_tenant_screening_strictness'    => 'Moderate — standard qualification criteria',
                'agent_tenant_profile_specialization'  => 'Section 8 / housing voucher tenants',
            ],
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'landlord');

        $this->assertCount(3, $payload['proxy_risk_flags']);

        $flagFields = array_column($payload['proxy_risk_flags'], 'field');
        $this->assertContains('agent_tenant_screening_strictness', $flagFields);
        $this->assertContains('agent_tenant_profile_specialization', $flagFields);
        $this->assertContains('agent_property_strategy_specialization', $flagFields);

        foreach ($payload['proxy_risk_flags'] as $flag) {
            $this->assertArrayHasKey('field', $flag);
            $this->assertArrayHasKey('section', $flag);
            $this->assertArrayHasKey('reason', $flag);
        }
    }

    // =========================================================================
    // TEST 21: No proxy-risk flags when proxy-risk fields are absent
    // =========================================================================

    public function test_no_proxy_risk_flags_when_proxy_risk_fields_are_absent(): void
    {
        $bid     = $this->makeAgentBidStub(121, $this->fullSections());
        $payload = $this->service->normalize($bid, 'seller');

        $this->assertIsArray($payload['proxy_risk_flags']);
        $this->assertEmpty($payload['proxy_risk_flags'],
            'proxy_risk_flags must be empty when no proxy-risk fields are populated');
    }

    // =========================================================================
    // TEST 22: bid_id is correctly extracted
    // =========================================================================

    public function test_bid_id_is_correctly_extracted_from_agent_bid(): void
    {
        $bid     = $this->makeAgentBidStub(9999, $this->fullSections());
        $payload = $this->service->normalize($bid, 'seller');

        $this->assertSame(9999, $payload['bid_id']);
    }

    // =========================================================================
    // TEST 23: No scoring, comparison, AI, or label fields in payload
    // =========================================================================

    public function test_no_scoring_comparison_ai_or_label_fields_in_payload(): void
    {
        $bid     = $this->makeAgentBidStub(123, $this->fullSections());
        $payload = $this->service->normalize($bid, 'seller');

        $prohibited = [
            'representation_compatibility_score',
            'match_score',
            'compatibility_label',
            'recommendation',
            'ai_explanation',
            'comparison_result',
            'ranking',
            'weighted_score',
            'scoring_framework_version',
        ];

        foreach ($prohibited as $field) {
            $this->assertArrayNotHasKey($field, $payload,
                "Prohibited field '{$field}' must not appear in the payload");
        }

        // None of the trait slots should contain scoring fields
        foreach ($payload['traits'] as $traitKey => $slot) {
            foreach ($prohibited as $field) {
                $this->assertArrayNotHasKey($field, $slot,
                    "Prohibited field '{$field}' must not appear in trait slot '{$traitKey}'");
            }
        }
    }

    // =========================================================================
    // TEST 24: Service never throws on malformed or null input
    // =========================================================================

    public function test_service_never_throws_on_empty_sections(): void
    {
        $bid     = $this->makeEmptyAgentBidStub(124);
        $payload = $this->service->normalize($bid, 'seller');

        $this->assertSame('BYA_AGENT_NORM_V1', $payload['normalization_version']);
        $this->assertCount(12, $payload['traits']);

        // All traits skipped (missing: false, value: null) because sections are null
        foreach ($payload['traits'] as $traitKey => $slot) {
            $this->assertFalse($slot['missing'],
                "Trait '{$traitKey}' should be skipped, not absent, when sections are null");
            $this->assertNull($slot['value']);
        }
    }

    public function test_service_never_throws_when_bid_has_no_compatibility_method(): void
    {
        $bid     = $this->makeNakedBidStub(125);
        $payload = $this->service->normalize($bid, 'buyer');

        $this->assertSame('BYA_AGENT_NORM_V1', $payload['normalization_version']);
        $this->assertCount(12, $payload['traits']);
        $this->assertIsArray($payload['informational_context']);
        $this->assertIsArray($payload['proxy_risk_flags']);
    }

    // =========================================================================
    // TEST 25: Multi-select trait slots filter out empty and null values
    // =========================================================================

    public function test_multi_select_slots_filter_empty_and_null_values(): void
    {
        $bid = $this->makeAgentBidStub(125, [
            'communication_preferences' => [
                'agent_communication_channels' => ['Email', '', null, 'Phone Call'],
            ],
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy' => [
                'agent_strategy_experience' => ['', null, ''],
            ],
            'representation_philosophy'  => null,
            'representation_priorities'  => null,
        ]);

        $payload = $this->service->normalize($bid, 'buyer');

        // Empty/null values filtered out; remaining values kept
        $cc = $payload['traits']['communication_channel'];
        $this->assertIsArray($cc['value']);
        $this->assertNotContains('', $cc['value']);
        $this->assertNotContains(null, $cc['value']);
        $this->assertContains('Email', $cc['value']);
        $this->assertContains('Phone Call', $cc['value']);

        // All-empty array → value is null (skipped)
        $psf = $payload['traits']['property_strategy_fit'];
        $this->assertNull($psf['value']);
        $this->assertFalse($psf['missing']);
    }

    // =========================================================================
    // TEST 26: Role-scoped priority notes appear in informational_context
    // =========================================================================

    public function test_role_scoped_priority_notes_appear_in_informational_context(): void
    {
        $bid = $this->makeAgentBidStub(126, [
            'communication_preferences'  => null,
            'negotiation_approach'       => null,
            'guidance_style'             => null,
            'collaboration_preferences'  => null,
            'transaction_strategy'       => null,
            'representation_philosophy'  => null,
            'representation_priorities' => [
                'agent_representation_priorities'  => ['Negotiation'],
                'agent_priority_notes'             => 'General priority note.',
                'agent_seller_priority_notes'      => 'For sellers I focus on pricing strategy.',
                'agent_buyer_priority_notes'       => 'For buyers I focus on offer competitiveness.',
            ],
        ]);

        $payload = $this->service->normalize($bid, 'seller');

        $ic = $payload['informational_context'];
        $this->assertArrayHasKey('agent_priority_notes', $ic);
        $this->assertArrayHasKey('agent_seller_priority_notes', $ic);
        $this->assertArrayHasKey('agent_buyer_priority_notes', $ic);
        $this->assertSame('For sellers I focus on pricing strategy.', $ic['agent_seller_priority_notes']);

        // Trait value is still from agent_representation_priorities, not the notes
        $this->assertSame(['Negotiation'], $payload['traits']['representation_priorities']['value']);
    }

    // =========================================================================
    // TEST 27: payload uses bid_id, not listing_id
    // =========================================================================

    public function test_payload_uses_bid_id_not_listing_id(): void
    {
        $bid     = $this->makeAgentBidStub(777, $this->fullSections());
        $payload = $this->service->normalize($bid, 'seller');

        $this->assertArrayHasKey('bid_id', $payload);
        $this->assertArrayNotHasKey('listing_id', $payload,
            'Agent normalization payload must use bid_id, not listing_id');
        $this->assertSame(777, $payload['bid_id']);
    }

    // =========================================================================
    // TEST 28: normalization_version is BYA_AGENT_NORM_V1 (not BYA_NORM_V1)
    // =========================================================================

    public function test_normalization_version_is_bya_agent_norm_v1(): void
    {
        $bid     = $this->makeAgentBidStub(128, $this->fullSections());
        $payload = $this->service->normalize($bid, 'seller');

        $this->assertSame('BYA_AGENT_NORM_V1', $payload['normalization_version']);
        $this->assertNotSame('BYA_NORM_V1', $payload['normalization_version'],
            'Agent normalization must use BYA_AGENT_NORM_V1, not the consumer BYA_NORM_V1 constant');
    }
}
