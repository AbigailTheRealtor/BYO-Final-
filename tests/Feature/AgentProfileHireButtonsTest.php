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
}
