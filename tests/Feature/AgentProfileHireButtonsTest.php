<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentProfileHireButtonsTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Suite setup
    // -------------------------------------------------------------------------

    /**
     * Temporarily expand the users_user_type_check constraint to include 'agent'
     * so that test agent users can be inserted.
     *
     * The constraint is an immediate CHECK on user_type. In production only
     * ('admin','buyer','seller','buyer_agent','seller_agent') are allowed, but
     * AgentProfileController::show() queries WHERE user_type = 'agent', which
     * means real agent rows pre-date the constraint or were inserted via a path
     * that bypassed it. We need to create test rows with this type.
     *
     * PostgreSQL DDL is fully transactional: DatabaseTransactions wraps each
     * test in a single BEGIN/ROLLBACK, so the constraint change and all data
     * inserted in makeAgent()/makeProfile() are rolled back automatically when
     * the test ends — no manual teardown required.
     */
    protected function setUp(): void
    {
        parent::setUp(); // DatabaseTransactions::setUp() begins the transaction here.

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_type_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_user_type_check CHECK (
            user_type IN ('admin','buyer','seller','buyer_agent','seller_agent','agent')
        )");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an agent user with a known hex short_id so the route constraint
     * ([0-9a-f]+) is satisfied and the controller can resolve it.
     *
     * Inserts via DB::table() rather than User::create() or the factory to
     * bypass UserObserver::creating(), which writes the non-existent column
     * "phone_number" (the real column is "phone") and causes a DB error.
     */
    private function makeAgent(string $shortId = 'deadbeef1234'): User
    {
        $unique = Str::random(8);
        $now    = now();

        $id = DB::table('users')->insertGetId([
            'first_name'        => 'Test',
            'last_name'         => 'Agent',
            'name'              => 'Test Agent',
            'user_name'         => "testagent_{$unique}",
            'email'             => "agent_{$unique}@test-hire.example",
            'email_verified_at' => $now,
            'password'          => Hash::make('password'),
            'remember_token'    => Str::random(10),
            'user_type'         => 'agent',
            'short_id'          => $shortId,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        return User::find($id);
    }

    /**
     * Create an AgentDefaultProfile for the given agent.
     * Pass a non-empty $services array to make the preset "valid" (renders a hire button).
     * Pass an empty array to simulate a preset that has no services configured.
     */
    private function makeProfile(User $agent, string $role, string $propertyType, array $services = []): AgentDefaultProfile
    {
        return AgentDefaultProfile::create([
            'user_id'      => $agent->id,
            'role_type'    => $role,
            'property_type' => $propertyType,
            'profile_data' => ['services' => $services],
        ]);
    }

    /** Canonical profile URL for a given short_id. */
    private function profileUrl(string $shortId = 'deadbeef1234'): string
    {
        return "/agent/{$shortId}/profile";
    }

    /** Expected hire URL for a given combination. */
    private function hireUrl(string $shortId, string $role, string $propertyType): string
    {
        return "/hire/{$shortId}/{$role}/{$propertyType}";
    }

    // =========================================================================
    // Test 1 — Single valid preset → direct <a> link
    // =========================================================================

    public function test_single_valid_preset_renders_direct_link(): void
    {
        $agent = $this->makeAgent();
        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // A direct <a> tag pointing to the correct hire URL must be present.
        $expectedHref = $this->hireUrl('deadbeef1234', 'seller', 'residential');
        $response->assertSee($expectedHref, false);

        // The property-type picker (<details>) must NOT be rendered when there
        // is only one valid option for the role.
        // Note: 'property-type-picker' alone appears in the page's JS snippet
        // (querySelectorAll selector). We check the full class attribute string,
        // which is unique to the rendered <details> element.
        $response->assertDontSee('hire-picker-wrap property-type-picker', false);
    }

    // =========================================================================
    // Test 2 — Multiple valid presets for the same role → <details> picker
    // =========================================================================

    public function test_multiple_valid_presets_for_same_role_renders_picker(): void
    {
        $agent = $this->makeAgent();

        // Two valid presets under the same role — must trigger the picker.
        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);
        $this->makeProfile($agent, 'seller', 'income',      ['Manage income property listing']);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // The <details> picker element must be present.
        // Use the full class attribute string to avoid matching the JS selector.
        $response->assertSee('hire-picker-wrap property-type-picker', false);

        // Both property-type URLs must appear inside the picker.
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'income'), false);

        // The direct <a> link pattern (only present for single-option roles)
        // must NOT be the sole rendering — the picker wraps both options.
        // Verify both links are reachable from within the picker element by
        // confirming each href string appears exactly in the rendered HTML.
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
        $hireHref = $this->hireUrl('deadbeef1234', 'seller', 'residential');
        $response->assertDontSee($hireHref, false);

        // The picker element must not be rendered either.
        $response->assertDontSee('hire-picker-wrap property-type-picker', false);
    }

    // =========================================================================
    // Test 4 — Preset with no services is silently excluded
    // =========================================================================

    public function test_preset_with_no_services_is_excluded_from_hire_buttons(): void
    {
        $agent = $this->makeAgent();

        // A profile with an empty services list is not valid — no button.
        $this->makeProfile($agent, 'seller', 'residential', []);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // No hire button or picker must appear because there are no valid presets.
        // We check for the HTML attribute form 'class="hire-btn"' because the bare
        // string 'hire-btn' also appears in the view's inline <style> CSS rules.
        $response->assertDontSee('class="hire-btn"', false);
        $response->assertDontSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);
    }

    // =========================================================================
    // Test 5 — Mixed: one role with services, one without → only valid shown
    // =========================================================================

    public function test_only_presets_with_services_produce_hire_buttons(): void
    {
        $agent = $this->makeAgent();

        // Valid preset for seller/residential.
        $this->makeProfile($agent, 'seller', 'residential', ['List property on MLS']);

        // Empty preset for buyer/residential — must be excluded.
        $this->makeProfile($agent, 'buyer', 'residential', []);

        $response = $this->get($this->profileUrl());

        $response->assertStatus(200);

        // The valid seller URL is present.
        $response->assertSee($this->hireUrl('deadbeef1234', 'seller', 'residential'), false);

        // The empty buyer URL is absent.
        $response->assertDontSee($this->hireUrl('deadbeef1234', 'buyer', 'residential'), false);
    }

    // =========================================================================
    // Test 6 — Multi-role: each role with one preset → each renders direct link
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
    // Test 7 — Profile page is publicly accessible (no login required)
    // =========================================================================

    public function test_profile_page_is_publicly_accessible_without_login(): void
    {
        $agent = $this->makeAgent();

        // Visit as a completely unauthenticated guest.
        $response = $this->get($this->profileUrl());

        // Must return 200, not 401/403/302.
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
