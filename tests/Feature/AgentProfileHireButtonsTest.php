<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for the hire-button section of the agent public profile page.
 *
 * Prerequisites (all satisfied by the test-safe schema baseline):
 *   - migration 2026_04_29_000001: users_user_type_check includes 'agent'
 *   - migration 2024_08_19_183330: user_meta table exists  (User eager-load)
 *   - migration 2026_03_25_221827: agent_default_profiles table exists
 *   - migration 2022_12_19_121313: settings table exists   (page render)
 *   - migration 2025_11_19_134433: notifications table exists (auth layout)
 *   - UserObserver::creating() no longer tries to set phone_number
 *   - UserFactory::asAgent() state sets user_type = 'agent'
 */
class AgentProfileHireButtonsTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an agent user with a known hex short_id.
     *
     * We supply short_id explicitly so that URL assertions are deterministic.
     * UserObserver::creating() skips short_id generation when the attribute is
     * already set, and fills user_name from the factory-generated email.
     */
    private function makeAgent(string $shortId = 'deadbeef1234'): User
    {
        return User::factory()->asAgent()->create(['short_id' => $shortId]);
    }

    /**
     * Create an AgentDefaultProfile for the given agent.
     *
     * Pass a non-empty $services array to make the preset "valid"
     * (renders a hire button). Pass [] to simulate a preset with no services.
     */
    private function makeProfile(
        User   $agent,
        string $role,
        string $propertyType,
        array  $services = [],
    ): AgentDefaultProfile {
        return AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => $role,
            'property_type' => $propertyType,
            'profile_data'  => ['services' => $services],
        ]);
    }

    /** Canonical profile URL for the default test agent. */
    private function profileUrl(string $shortId = 'deadbeef1234'): string
    {
        return "/agent/{$shortId}/profile";
    }

    /** Expected hire URL for a given agent / role / property-type combination. */
    private function hireUrl(string $shortId, string $role, string $propertyType): string
    {
        return "/hire/{$shortId}/{$role}/{$propertyType}";
    }

    // =========================================================================
    // Test 1 — Single valid preset → direct <a> link, no picker
    // =========================================================================

    public function test_single_valid_preset_renders_direct_link(): void
    {
        $agent = $this->makeAgent();
        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // A direct <a> tag pointing to the correct hire URL must be present.
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);

        // The property-type picker (<details>) must NOT be rendered when there
        // is only one valid option for the role.
        //
        // We check the full class attribute string rather than the bare class
        // name because 'property-type-picker' also appears in the page's
        // @push('scripts') JS snippet (querySelectorAll selector).
        $response->assertDontSee('hire-picker-wrap property-type-picker', false);
    }

    // =========================================================================
    // Test 2 — Multiple valid presets for the same role → <details> picker
    // =========================================================================

    public function test_multiple_valid_presets_for_same_role_renders_picker(): void
    {
        $agent = $this->makeAgent();

        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);
        $this->makeProfile($agent, 'seller', 'income',      ['Manage income property listing']);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // The <details> picker element must be present.
        $response->assertSee('hire-picker-wrap property-type-picker', false);

        // Both property-type URLs must appear inside the picker.
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'income'), false);

        $html = $response->getContent();
        $this->assertStringContainsString('hire-picker-wrap', $html);
    }

    // =========================================================================
    // Test 3 — Owner preview: note shown, no functional hire links
    // =========================================================================

    public function test_owner_sees_preview_note_and_no_functional_hire_links(): void
    {
        $agent = $this->makeAgent();
        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);

        // Owner visits their own public profile while authenticated.
        $response = $this->actingAs($agent)->get($this->profileUrl());

        $response->assertStatus(200);

        // The owner-preview note must be visible.
        $response->assertSee('Clients will use this button to hire you', false);

        // No functional hire link must be rendered for the owner.
        $response->assertDontSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);

        // The picker element must not be rendered either.
        $response->assertDontSee('hire-picker-wrap property-type-picker', false);
    }

    // =========================================================================
    // Test 4 — Preset with no services is silently excluded
    // =========================================================================

    public function test_preset_with_no_services_is_excluded_from_hire_buttons(): void
    {
        $agent = $this->makeAgent();
        $this->makeProfile($agent, 'seller', 'residential', []); // empty services

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // No hire button or picker must appear because there are no valid presets.
        // We check the HTML attribute form 'class="hire-btn"' because the bare
        // string 'hire-btn' also appears in the view's inline <style> CSS block.
        $response->assertDontSee('class="hire-btn"', false);
        $response->assertDontSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);
    }

    // =========================================================================
    // Test 5 — Mixed presets: only the valid one produces a button
    // =========================================================================

    public function test_only_presets_with_services_produce_hire_buttons(): void
    {
        $agent = $this->makeAgent();

        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);
        $this->makeProfile($agent, 'buyer',  'residential', []); // invalid — no services

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // The valid seller URL is present.
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);

        // The empty buyer URL is absent.
        $response->assertDontSee($this->hireUrl('deadbeef1234', 'buyer', 'residential'), false);
    }

    // =========================================================================
    // Test 6 — Multi-role: each role with one preset → separate direct links
    // =========================================================================

    public function test_multiple_roles_with_single_preset_each_render_separate_direct_links(): void
    {
        $agent = $this->makeAgent();

        $this->makeProfile($agent, 'seller',   'residential', ['List on MLS']);
        $this->makeProfile($agent, 'buyer',    'residential', ['Find buyer properties']);
        $this->makeProfile($agent, 'landlord', 'residential', ['Market rental unit']);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // Each role must produce its own direct link.
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller',   'residential'), false);
        $response->assertSee($this->hireUrl('deadbeef1234', 'buyer',    'residential'), false);
        $response->assertSee($this->hireUrl('deadbeef1234', 'landlord', 'residential'), false);

        // No picker should be rendered — each role has exactly one valid preset.
        $response->assertDontSee('hire-picker-wrap property-type-picker', false);
    }

    // =========================================================================
    // Test 7 — Profile page is publicly accessible without login
    // =========================================================================

    public function test_profile_page_is_publicly_accessible_without_login(): void
    {
        $agent = $this->makeAgent();

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);
    }

    // =========================================================================
    // Test 8 — Non-existent short_id returns 404
    // =========================================================================

    public function test_unknown_agent_short_id_returns_404(): void
    {
        $response = $this->get('/agent/aabbccdd1122/profile');

        $response->assertStatus(404);
    }

    // =========================================================================
    // Test 9 — Marketing Plan appears when non-empty
    // =========================================================================

    public function test_marketing_plan_section_appears_when_non_empty(): void
    {
        $agent = $this->makeAgent('aa11bb22cc33');

        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'seller',
            'property_type' => 'residential',
            'profile_data'  => [
                'services'       => ['List property on MLS'],
                'marketing_plan' => 'I use social media and open houses to attract qualified buyers.',
            ],
        ]);

        $response = $this->get("/agent/aa11bb22cc33/profile");

        $response->assertStatus(200);
        $response->assertSee('Marketing Plan', false);
        $response->assertSee('I use social media and open houses to attract qualified buyers.', false);
    }

    public function test_marketing_plan_section_absent_when_empty(): void
    {
        $agent = $this->makeAgent('aa11bb22cc44');

        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'seller',
            'property_type' => 'residential',
            'profile_data'  => [
                'services'       => ['List property on MLS'],
                'marketing_plan' => '',
            ],
        ]);

        $response = $this->get("/agent/aa11bb22cc44/profile");

        $response->assertStatus(200);
        $response->assertDontSee('Marketing Plan', false);
    }

    // =========================================================================
    // Test 10 — Exactly one "Additional Services" heading when both sources populated
    // =========================================================================

    public function test_only_one_additional_services_heading_rendered(): void
    {
        $agent = $this->makeAgent('dd11ee22ff33');

        // 'other_services' provides custom services and the selected services
        // list contains an item that will be auto-bucketed into Additional Services.
        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'seller',
            'property_type' => 'residential',
            'profile_data'  => [
                'services'       => ['Some custom unlisted service for auto-bucket'],
                'other_services' => ['Another custom service from other_services field'],
            ],
        ]);

        $response = $this->get("/agent/dd11ee22ff33/profile");

        $response->assertStatus(200);

        // The "Additional Services" label must appear exactly once in the profile body.
        // We search for the rendered heading style which has the "profile-field-label" class.
        $html = $response->getContent();
        $count = substr_count($html, 'Additional Services');
        $this->assertSame(1, $count, 'Expected exactly one "Additional Services" heading but found: ' . $count);
    }

    // =========================================================================
    // Test 11 — Anonymous visitors never see compensation field values (Task #195)
    // =========================================================================

    public function test_anonymous_visitor_does_not_see_compensation_fields(): void
    {
        $agent = $this->makeAgent('beefcafe5678');

        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'buyer',
            'property_type' => 'residential',
            'profile_data'  => [
                'services'             => ['Find buyer properties'],
                'commission_structure' => 'percentage',
                'purchase_fee_percentage' => '3',
                'agency_agreement_timeframe' => '90 days',
                'protection_period'    => '30',
            ],
        ]);

        $response = $this->get("/agent/beefcafe5678/profile");

        $response->assertStatus(200);

        // Compensation section heading must not appear for anonymous visitors.
        $response->assertDontSee('Broker Compensation', false);

        // Specific compensation values must not be rendered in the HTML.
        $response->assertDontSee('Agency Agreement Terms', false);
        $response->assertDontSee('3%', false);
        $response->assertDontSee('90 days', false);
    }

    // =========================================================================
    // Test 10 — Authenticated visitors do see compensation fields (Task #195)
    // =========================================================================

    public function test_authenticated_visitor_sees_compensation_fields(): void
    {
        $agent  = $this->makeAgent('cafe1234abcd');
        $viewer = User::factory()->create();

        AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => 'buyer',
            'property_type' => 'residential',
            'profile_data'  => [
                'services'                => ['Find buyer properties'],
                'commission_structure'    => 'Percentage',
                'purchase_fee_percentage' => '3',
                'agency_agreement_timeframe' => '90 days',
            ],
        ]);

        $response = $this->actingAs($viewer)->get("/agent/cafe1234abcd/profile");

        $response->assertStatus(200);

        // Compensation section heading must appear for authenticated visitors.
        $response->assertSee('Broker Compensation', false);

        // Specific compensation values must be rendered.
        $response->assertSee('Percentage', false);
        $response->assertSee('3%', false);
        $response->assertSee('90 days', false);
    }
}
