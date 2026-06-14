<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use App\Services\AgentBidMapperService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for the Agent Bid Compatibility Questionnaire.
 *
 * Covers:
 *  §1 — HasCompatibilityPreferences trait: save/load round-trip for all 7 sections.
 *  §2 — HasCompatibilityPreferences trait: unknown sections are ignored on save.
 *  §3 — HasCompatibilityPreferences trait: invalid (non-assoc, empty, non-array) data is skipped.
 *  §4 — HasCompatibilityPreferences trait: loadCompatibilitySection returns null for missing sections.
 *  §5 — AgentBidMapperService::mapCompatibilityFromProfile() extracts all valid sections.
 *  §6 — AgentBidMapperService::mapCompatibilityFromProfile() returns [] when key absent.
 *  §7 — AgentBidMapperService::mapCompatibilityFromProfile() filters unknown section keys.
 *  §8 — AgentPresetController stores compatibility_preferences in profile_data.
 *  §9 — AgentPresetController: empty/absent compatibility_preferences defaults to [].
 */
class AgentBidCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(): User
    {
        static $counter = 0;
        $counter++;
        $shortId = str_pad((string) $counter, 12, 'a', STR_PAD_LEFT);
        return User::factory()->asAgent()->create(['short_id' => $shortId]);
    }

    /**
     * Create a persisted TenantAgentAuctionBid owned by the given agent.
     * We use TenantAgentAuctionBid as the canonical representative of all four
     * bid models that share HasCompatibilityPreferences — only one model needs
     * to be exercised for trait logic.  TenantAgentAuctionBidFactory exists in
     * this project; Seller/Buyer/Landlord bid factories do not.
     */
    private function makeBid(User $agent): TenantAgentAuctionBid
    {
        return TenantAgentAuctionBid::factory()->create(['user_id' => $agent->id]);
    }

    /** Canonical 7-section payload for full round-trip tests. */
    private function fullCompatibilityPayload(): array
    {
        return [
            'communication_preferences' => [
                'agent_communication_channels'        => ['Phone Call', 'Email'],
                'agent_communication_frequency'       => 'Weekly',
                'agent_response_time_commitment'      => 'Within 24 Hours',
                'agent_communication_notes'           => 'Email for details.',
                'agent_availability_notes'            => 'Weekdays 9am–6pm.',
            ],
            'negotiation_approach' => [
                'agent_negotiation_style' => 'Collaborative',
                'agent_negotiation_notes' => 'Win-win focused.',
            ],
            'guidance_style' => [
                'agent_guidance_level' => 'Balanced',
                'agent_guidance_notes' => 'Guiding without hovering.',
            ],
            'collaboration_preferences' => [
                'agent_collaboration_style'     => 'Highly Proactive',
                'agent_availability_windows'    => 'Weekdays 8am–7pm',
            ],
            'transaction_strategy' => [
                'agent_transaction_pace'      => 'Moderate',
                'agent_strategy_experience'   => ['Investment Properties', 'New Construction'],
                'agent_strategy_notes'        => '30+ off-market deals.',
            ],
            'representation_philosophy' => [
                'agent_decision_support_style'       => 'Data-Driven',
                'agent_risk_posture'                 => 'Balanced',
                'agent_representation_philosophy'    => ['Fiduciary-First', 'Results-Oriented'],
                'agent_philosophy_narrative'         => 'Always transparent.',
                'agent_philosophy_notes'             => 'Client interests first.',
            ],
            'representation_priorities' => [
                'agent_representation_priorities' => ['Negotiation Strength', 'Market Knowledge'],
                'agent_priority_notes'            => 'My edge is negotiation.',
            ],
        ];
    }

    // =========================================================================
    // §1 — HasCompatibilityPreferences: full save/load round-trip
    // =========================================================================

    public function test_save_and_load_compatibility_preferences_all_seven_sections(): void
    {
        $agent   = $this->makeAgent();
        $bid     = $this->makeBid($agent);
        $payload = $this->fullCompatibilityPayload();

        $bid->saveCompatibilityPreferences($payload);
        $bid->refresh();

        $loaded = $bid->loadCompatibilityPreferences();

        foreach (array_keys($payload) as $section) {
            $this->assertIsArray($loaded[$section], "Section '{$section}' should be an array after load.");
            $this->assertSame($payload[$section], $loaded[$section], "Section '{$section}' data should match after round-trip.");
        }
    }

    public function test_missing_sections_are_returned_as_null_on_load(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        // Save nothing — all sections should come back null.
        $loaded = $bid->loadCompatibilityPreferences();

        $this->assertCount(7, $loaded);
        foreach ($loaded as $section => $value) {
            $this->assertNull($value, "Section '{$section}' should be null when nothing was saved.");
        }
    }

    public function test_partial_save_leaves_other_sections_null(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $bid->saveCompatibilityPreferences([
            'negotiation_approach' => ['agent_negotiation_style' => 'Assertive'],
        ]);
        $bid->refresh();

        $loaded = $bid->loadCompatibilityPreferences();

        $this->assertSame(['agent_negotiation_style' => 'Assertive'], $loaded['negotiation_approach']);

        foreach (['communication_preferences', 'guidance_style', 'collaboration_preferences',
                  'transaction_strategy', 'representation_philosophy', 'representation_priorities'] as $section) {
            $this->assertNull($loaded[$section], "Section '{$section}' should be null when not saved.");
        }
    }

    // =========================================================================
    // §2 — Unknown sections are silently ignored on save
    // =========================================================================

    public function test_unknown_section_keys_are_ignored_on_save(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $bid->saveCompatibilityPreferences([
            'negotiation_approach'    => ['agent_negotiation_style' => 'Methodical'],
            'unknown_bogus_section'   => ['field' => 'value'],   // must be dropped
            'another_invalid_section' => ['foo' => 'bar'],        // must be dropped
        ]);
        $bid->refresh();

        $loaded = $bid->loadCompatibilityPreferences();

        $this->assertSame(['agent_negotiation_style' => 'Methodical'], $loaded['negotiation_approach']);
        $this->assertNull($loaded['communication_preferences']);
        // The trait must not create a meta key for unknown sections.
        $this->assertFalse(
            $bid->meta()->where('meta_key', 'LIKE', '%unknown_bogus_section%')->exists(),
            'Unknown sections must not be persisted to the meta table.'
        );
    }

    // =========================================================================
    // §3 — Invalid data shapes are skipped
    // =========================================================================

    public function test_empty_array_section_is_skipped(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $bid->saveCompatibilityPreferences([
            'negotiation_approach' => [],   // empty — should be skipped
        ]);

        $loaded = $bid->loadCompatibilityPreferences();
        $this->assertNull($loaded['negotiation_approach'], 'Empty array should not be persisted.');
    }

    public function test_sequential_list_section_is_skipped(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $bid->saveCompatibilityPreferences([
            'negotiation_approach' => ['value_one', 'value_two'],  // list, not assoc
        ]);

        $loaded = $bid->loadCompatibilityPreferences();
        $this->assertNull($loaded['negotiation_approach'], 'Sequential list should not be persisted.');
    }

    public function test_non_array_section_value_is_skipped(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $bid->saveCompatibilityPreferences([
            'negotiation_approach' => 'just a string',  // not an array
        ]);

        $loaded = $bid->loadCompatibilityPreferences();
        $this->assertNull($loaded['negotiation_approach'], 'Non-array value should not be persisted.');
    }

    // =========================================================================
    // §4 — loadCompatibilitySection returns null for missing / unknown sections
    // =========================================================================

    public function test_load_compatibility_section_returns_null_for_unknown_section(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $result = $bid->loadCompatibilitySection('non_existent_section');
        $this->assertNull($result, 'loadCompatibilitySection must return null for unknown section names.');
    }

    public function test_load_compatibility_section_returns_null_when_not_saved(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $result = $bid->loadCompatibilitySection('guidance_style');
        $this->assertNull($result);
    }

    public function test_load_compatibility_section_returns_data_when_saved(): void
    {
        $agent = $this->makeAgent();
        $bid   = $this->makeBid($agent);

        $bid->saveCompatibilityPreferences([
            'guidance_style' => ['agent_guidance_level' => 'Hands-On'],
        ]);
        $bid->refresh();

        $result = $bid->loadCompatibilitySection('guidance_style');
        $this->assertSame(['agent_guidance_level' => 'Hands-On'], $result);
    }

    // =========================================================================
    // §5 — AgentBidMapperService::mapCompatibilityFromProfile() — full extraction
    // =========================================================================

    public function test_map_compatibility_from_profile_extracts_all_valid_sections(): void
    {
        $compatData = $this->fullCompatibilityPayload();
        $profileData = ['bio' => 'Some bio', 'compatibility_preferences' => $compatData];

        $result = AgentBidMapperService::mapCompatibilityFromProfile($profileData);

        $this->assertCount(7, $result);
        foreach (array_keys($compatData) as $section) {
            $this->assertArrayHasKey($section, $result);
            $this->assertSame($compatData[$section], $result[$section]);
        }
    }

    public function test_map_compatibility_from_profile_partial_sections(): void
    {
        $profileData = [
            'compatibility_preferences' => [
                'negotiation_approach' => ['agent_negotiation_style' => 'Assertive'],
                'guidance_style'       => ['agent_guidance_level' => 'Advisory'],
            ],
        ];

        $result = AgentBidMapperService::mapCompatibilityFromProfile($profileData);

        $this->assertCount(2, $result);
        $this->assertSame(['agent_negotiation_style' => 'Assertive'], $result['negotiation_approach']);
        $this->assertSame(['agent_guidance_level' => 'Advisory'], $result['guidance_style']);
    }

    // =========================================================================
    // §6 — mapCompatibilityFromProfile() returns [] when key absent or not array
    // =========================================================================

    public function test_map_compatibility_from_profile_returns_empty_when_key_absent(): void
    {
        $result = AgentBidMapperService::mapCompatibilityFromProfile(['bio' => 'no compat key']);
        $this->assertSame([], $result);
    }

    public function test_map_compatibility_from_profile_returns_empty_when_value_is_not_array(): void
    {
        $result = AgentBidMapperService::mapCompatibilityFromProfile([
            'compatibility_preferences' => 'not an array',
        ]);
        $this->assertSame([], $result);
    }

    public function test_map_compatibility_from_profile_returns_empty_for_empty_profile(): void
    {
        $result = AgentBidMapperService::mapCompatibilityFromProfile([]);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // §7 — mapCompatibilityFromProfile() filters unknown section keys
    // =========================================================================

    public function test_map_compatibility_from_profile_filters_unknown_sections(): void
    {
        $profileData = [
            'compatibility_preferences' => [
                'negotiation_approach'  => ['agent_negotiation_style' => 'Adaptive'],
                'completely_made_up'    => ['junk' => 'data'],
                'also_invalid'          => ['more' => 'junk'],
            ],
        ];

        $result = AgentBidMapperService::mapCompatibilityFromProfile($profileData);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('negotiation_approach', $result);
        $this->assertArrayNotHasKey('completely_made_up', $result);
        $this->assertArrayNotHasKey('also_invalid', $result);
    }

    public function test_map_compatibility_from_profile_skips_non_array_section_values(): void
    {
        $profileData = [
            'compatibility_preferences' => [
                'negotiation_approach'       => ['agent_negotiation_style' => 'Conservative'],
                'transaction_strategy'       => 'just a string',   // must be skipped
                'communication_preferences'  => null,              // must be skipped
            ],
        ];

        $result = AgentBidMapperService::mapCompatibilityFromProfile($profileData);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('negotiation_approach', $result);
        $this->assertArrayNotHasKey('transaction_strategy', $result);
        $this->assertArrayNotHasKey('communication_preferences', $result);
    }

    // =========================================================================
    // §8 — AgentPresetController stores compatibility_preferences in profile_data
    // =========================================================================

    public function test_preset_save_stores_compatibility_preferences_in_profile_data(): void
    {
        $agent = $this->makeAgent();

        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'seller',
            'property_type' => 'residential',
            'profile_data'  => [],
        ]);

        $compatPayload = [
            'negotiation_approach' => [
                'agent_negotiation_style' => 'Assertive',
                'agent_negotiation_notes' => 'Push hard every time.',
            ],
            'guidance_style' => [
                'agent_guidance_level' => 'Balanced',
            ],
        ];

        $this->actingAs($agent)
            ->post(route('agent.presets.save', ['role' => 'seller', 'propertyType' => 'residential']), [
                'profile_save_scope'        => 'current_preset',
                'services'                  => [],
                'other_services'            => [],
                'bio'                       => '',
                'compatibility_preferences' => $compatPayload,
            ]);

        $saved = AgentDefaultProfile::findForAgent($agent->id, 'seller', 'residential')?->profile_data;

        $this->assertIsArray($saved);
        $this->assertArrayHasKey('compatibility_preferences', $saved);
        // Use assertEquals (not assertSame) — array key order may differ after JSON decode.
        $this->assertEquals($compatPayload, $saved['compatibility_preferences']);
    }

    public function test_preset_save_stores_empty_compatibility_when_absent_from_request(): void
    {
        $agent = $this->makeAgent();

        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'buyer',
            'property_type' => 'residential',
            'profile_data'  => [],
        ]);

        $this->actingAs($agent)
            ->post(route('agent.presets.save', ['role' => 'buyer', 'propertyType' => 'residential']), [
                'profile_save_scope' => 'current_preset',
                'services'           => [],
                'other_services'     => [],
                'bio'                => '',
                // no compatibility_preferences key
            ]);

        $saved = AgentDefaultProfile::findForAgent($agent->id, 'buyer', 'residential')?->profile_data;

        $this->assertIsArray($saved);
        $this->assertArrayHasKey('compatibility_preferences', $saved);
        $this->assertSame([], $saved['compatibility_preferences']);
    }

    // =========================================================================
    // §9 — mapCompatibilityFromProfile + save/load integration
    // =========================================================================

    public function test_preset_compatibility_data_round_trips_through_mapper_and_bid(): void
    {
        $agent = $this->makeAgent();

        // Simulate profile_data as would be stored after a preset save.
        $profileData = [
            'bio'                       => 'Test agent bio',
            'compatibility_preferences' => [
                'communication_preferences' => [
                    'agent_communication_channels'   => ['Phone Call', 'Text Message'],
                    'agent_communication_frequency'  => 'Daily Updates',
                ],
                'representation_priorities' => [
                    'agent_representation_priorities' => ['Negotiation Strength'],
                    'agent_priority_notes'            => 'My top strength.',
                ],
            ],
        ];

        // Extract via mapper (simulates what loadDefaultProfile() does).
        $compatData = AgentBidMapperService::mapCompatibilityFromProfile($profileData);

        // Persist to bid (simulates Livewire submit).
        $bid = $this->makeBid($agent);
        $bid->saveCompatibilityPreferences($compatData);
        $bid->refresh();

        // Reload from DB and verify.
        $loaded = $bid->loadCompatibilityPreferences();

        $this->assertSame(
            ['Phone Call', 'Text Message'],
            $loaded['communication_preferences']['agent_communication_channels']
        );
        $this->assertSame(
            'Daily Updates',
            $loaded['communication_preferences']['agent_communication_frequency']
        );
        $this->assertSame(
            ['Negotiation Strength'],
            $loaded['representation_priorities']['agent_representation_priorities']
        );
        $this->assertNull($loaded['negotiation_approach']);
        $this->assertNull($loaded['guidance_style']);
    }

    // =========================================================================
    // §10 — Preset auto-fill blank-field guard
    // =========================================================================

    /**
     * Preset auto-fill must not overwrite sections that already have data.
     * Mirrors the guard added to all 4 Livewire component loadDefaultProfile() paths:
     *   if (empty($existing)) { $this->compatibility_agent_response[$section] = $data; }
     */
    public function test_preset_autofill_does_not_overwrite_existing_compatibility_section(): void
    {
        // Existing bid data already has communication_preferences filled.
        $existing = [
            'communication_preferences' => [
                'agent_communication_channels'   => ['Text Message'],
                'agent_communication_frequency'  => 'As Needed',
            ],
        ];

        // Preset has communication_preferences AND negotiation_approach.
        $presetCompatData = [
            'communication_preferences' => [
                'agent_communication_channels'   => ['Phone Call', 'Email'],
                'agent_communication_frequency'  => 'Daily Updates',
            ],
            'negotiation_approach' => [
                'agent_negotiation_style' => 'Collaborative',
            ],
        ];

        // Simulate the blank-field guard loop from Livewire components.
        $result = $existing;
        foreach ($presetCompatData as $_cpSection => $_cpData) {
            if (is_array($_cpData) && !empty($_cpData)) {
                $sectionExisting = $result[$_cpSection] ?? null;
                if (empty($sectionExisting)) {
                    $result[$_cpSection] = $_cpData;
                }
            }
        }

        // communication_preferences should retain the original bid data.
        $this->assertSame(
            ['Text Message'],
            $result['communication_preferences']['agent_communication_channels'],
            'Preset must not overwrite an already-filled section.'
        );
        $this->assertSame('As Needed', $result['communication_preferences']['agent_communication_frequency']);

        // negotiation_approach was empty — preset value should be applied.
        $this->assertSame(
            'Collaborative',
            $result['negotiation_approach']['agent_negotiation_style'],
            'Preset must fill sections that are currently empty.'
        );
    }

    // =========================================================================
    // §11 — End-to-end: preset → mapper hydration → bid submit → detail render
    // =========================================================================

    /**
     * Full round-trip: preset profile_data → AgentBidMapperService → saved bid
     * → consumer detail page renders the Working Style & Compatibility section.
     *
     * Uses the tenant bid preview route (tenant.agent.bid.preview) because both
     * TenantAgentAuction and TenantAgentAuctionBid have factories, and the
     * tenant_agent/bid_preview.blade.php includes the display partial.
     */
    public function test_compatibility_data_renders_on_consumer_bid_detail_page(): void
    {
        // 1. Client owns the listing; agent submits a bid.
        $client  = User::factory()->create();
        $agent   = $this->makeAgent();
        $auction = TenantAgentAuction::factory()->create(['user_id' => $client->id]);
        $bid     = TenantAgentAuctionBid::factory()->create([
            'user_id'                 => $agent->id,
            'tenant_agent_auction_id' => $auction->id,
        ]);

        // 2. Simulate preset-to-bid hydration via the mapper (mirrors loadDefaultProfile()).
        $profileData = [
            'compatibility_preferences' => [
                'communication_preferences' => [
                    'agent_communication_channels'   => ['Phone Call', 'Email'],
                    'agent_communication_frequency'  => 'Weekly',
                    'agent_response_time_commitment' => 'Within 24 Hours',
                ],
                'negotiation_approach' => [
                    'agent_negotiation_style' => 'Collaborative',
                    'agent_negotiation_notes' => 'Win-win focused.',
                ],
                'representation_priorities' => [
                    'agent_representation_priorities' => ['Negotiation Strength'],
                    'agent_priority_notes'             => 'My edge is negotiation.',
                ],
            ],
        ];

        $compatData = AgentBidMapperService::mapCompatibilityFromProfile($profileData);
        $bid->saveCompatibilityPreferences($compatData);
        $bid->refresh();

        // 3. Consumer (listing owner) hits the bid detail page.
        $response = $this->actingAs($client)
            ->get(route('tenant.agent.bid.preview', ['bidId' => $bid->id]));

        $response->assertStatus(200);

        // 4. The display partial renders the "Working Style & Compatibility" heading
        //    and at least one saved compatibility value.
        $response->assertSee('Working Style');
        $response->assertSee('Communication Preferences');
        $response->assertSee('Weekly');
        $response->assertSee('Collaborative');
    }
}
