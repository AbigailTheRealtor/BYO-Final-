<?php

namespace Tests\Unit\Support\HireAgent;

use App\Support\HireAgent\CompatibilityPreferencePolicy;
use Tests\TestCase;

/**
 * The allowlist projection — the security boundary for compatibility_preferences.
 *
 * These are the adversarial tests. `$compatibility_preferences` is a public Livewire property, so
 * a client can set any nested path inside it; validate() does not strip unlisted keys and the
 * persist used to write the role sub-array verbatim. Everything below feeds the projection input
 * it would receive from a crafted request rather than from the form.
 */
class CompatibilityPreferencePolicyTest extends TestCase
{
    // ── Injected keys ────────────────────────────────────────────────────────

    /** @test */
    public function it_discards_arbitrary_injected_keys(): void
    {
        $out = CompatibilityPreferencePolicy::project([
            'communication_style' => 'Email Only',
            'totally_made_up_key' => 'injected',
            'is_admin'            => true,
            ''                    => 'empty key',
            '0'                   => 'numeric key',
        ], 'landlord', 'Residential Property');

        $this->assertSame(['communication_style' => 'Email Only'], $out);
    }

    /** @test */
    public function it_discards_the_retired_tenant_type_keys_for_every_property_type(): void
    {
        foreach (['Residential Property', 'Commercial Property', null, '', 'Income Property'] as $type) {
            $out = CompatibilityPreferencePolicy::project([
                'primary_leasing_goal'         => 'Maximize Monthly Rent',
                'tenant_type_preference'       => 'Individual / Family',
                'tenant_type_preference_other' => 'Long-term professional tenant',
            ], 'landlord', $type);

            $this->assertArrayNotHasKey('tenant_type_preference', $out,
                'Retired key survived projection for property type: ' . var_export($type, true));
            $this->assertArrayNotHasKey('tenant_type_preference_other', $out,
                'Retired companion survived projection for property type: ' . var_export($type, true));

            // Positive control: the projection is not simply returning nothing.
            $this->assertSame('Maximize Monthly Rent', $out['primary_leasing_goal']);
        }
    }

    /**
     * The stale-tab case. A landlord who opened the form before the deploy still posts the old
     * field on submit; nothing in the request marks it as stale.
     *
     * @test
     */
    public function a_stale_browser_tab_cannot_write_the_retired_field_back(): void
    {
        $out = CompatibilityPreferencePolicy::project([
            'communication_style'    => 'Email Only',
            'tenant_type_preference' => 'Students',
        ], 'landlord', 'Residential Property');

        $this->assertSame(['communication_style' => 'Email Only'], $out);
    }

    /** @test */
    public function it_discards_the_replaced_landlord_risk_tolerance_key(): void
    {
        $out = CompatibilityPreferencePolicy::project([
            'risk_tolerance'               => 'High – Willing to Work With Most Tenants',
            'applicant_screening_approach' => 'Written criteria, applied uniformly',
        ], 'landlord', 'Residential Property');

        $this->assertArrayNotHasKey('risk_tolerance', $out);
        $this->assertSame('Written criteria, applied uniformly', $out['applicant_screening_approach']);
    }

    /** @test */
    public function buyer_keeps_its_own_unrelated_risk_tolerance(): void
    {
        $out = CompatibilityPreferencePolicy::project(
            ['risk_tolerance' => 'Very Aggressive'],
            'buyer'
        );

        $this->assertSame(['risk_tolerance' => 'Very Aggressive'], $out,
            "Buyer risk_tolerance is about offer strategy and is unrelated to the landlord field "
            . 'that was retired; retiring one must not remove the other.');
    }

    // ── Commercial scoping ───────────────────────────────────────────────────

    /** @test */
    public function commercial_landlord_may_persist_the_business_use_keys(): void
    {
        $out = CompatibilityPreferencePolicy::project([
            'preferred_business_use'       => ['Office', 'Retail'],
            'preferred_business_use_other' => 'Veterinary clinic',
        ], 'landlord', 'Commercial Property');

        $this->assertSame(['Office', 'Retail'], $out['preferred_business_use']);
        $this->assertSame('Veterinary clinic', $out['preferred_business_use_other']);
    }

    /**
     * @test
     * @dataProvider nonCommercialPropertyTypes
     */
    public function a_non_commercial_listing_can_never_persist_the_business_use_keys($propertyType): void
    {
        $out = CompatibilityPreferencePolicy::project([
            'communication_style'          => 'Email Only',
            'preferred_business_use'       => ['Office'],
            'preferred_business_use_other' => 'Anything at all',
        ], 'landlord', $propertyType);

        $this->assertArrayNotHasKey('preferred_business_use', $out,
            'Commercial-only key persisted for property type: ' . var_export($propertyType, true));
        $this->assertArrayNotHasKey('preferred_business_use_other', $out,
            'Commercial-only companion persisted for property type: ' . var_export($propertyType, true));
        $this->assertSame('Email Only', $out['communication_style']);
    }

    /**
     * Anything that is not EXACTLY the commercial value is residential. property_type is EAV, so
     * absent, empty and legacy-spelling rows are all real states.
     */
    public static function nonCommercialPropertyTypes(): array
    {
        return [
            'residential'      => ['Residential Property'],
            'legacy spelling'  => ['Residential'],
            'income property'  => ['Income Property'],
            'null'             => [null],
            'empty string'     => [''],
            'lowercased'       => ['commercial property'],
            'near miss'        => ['Commercial'],
            'padded near miss' => ['Commercial Property!'],
            'unknown'          => ['Something Else'],
        ];
    }

    /** @test */
    public function whitespace_around_the_commercial_value_still_counts_as_commercial(): void
    {
        $out = CompatibilityPreferencePolicy::project(
            ['preferred_business_use' => ['Office']],
            'landlord',
            '  Commercial Property  '
        );

        $this->assertSame(['Office'], $out['preferred_business_use']);
    }

    // ── Roles ────────────────────────────────────────────────────────────────

    /** @test */
    public function an_unrecognised_role_persists_nothing(): void
    {
        $this->assertSame([], CompatibilityPreferencePolicy::project(
            ['communication_style' => 'Email Only'],
            'administrator'
        ));
        $this->assertSame([], CompatibilityPreferencePolicy::allowedKeys('administrator'));
    }

    /** @test */
    public function commercial_only_is_not_itself_addressable_as_a_role(): void
    {
        // The config key `landlord_commercial_only` sits beside the four real roles. Reading it as
        // a role would hand a caller the commercial keys with no property-type test at all.
        $this->assertSame([], CompatibilityPreferencePolicy::project(
            ['preferred_business_use' => ['Office']],
            'landlord_commercial_only',
            'Commercial Property'
        ));
    }

    /** @test */
    public function each_role_keeps_only_its_own_keys(): void
    {
        // A landlord payload carrying tenant and seller keys — cross-role contamination.
        $out = CompatibilityPreferencePolicy::project([
            'communication_style'  => 'Email Only',
            'concerns_or_barriers' => 'tenant-only key',
            'firm_on_price'        => 'seller-only key',
            'deal_breakers'        => 'buyer-only key',
        ], 'landlord', 'Residential Property');

        $this->assertSame(['communication_style' => 'Email Only'], $out);
    }

    // ── projectAll ───────────────────────────────────────────────────────────

    /** @test */
    public function project_all_drops_unknown_namespaces_and_projects_the_rest(): void
    {
        $out = CompatibilityPreferencePolicy::projectAll([
            'landlord_specific' => [
                'communication_style'    => 'Email Only',
                'tenant_type_preference' => 'Students',
            ],
            'tenant_specific'   => ['concerns_or_barriers' => 'kept'],
            'admin_specific'    => ['anything' => 'dropped'],
            'not_a_namespace'   => ['anything' => 'dropped'],
            'landlord_specific_extra' => ['anything' => 'dropped'],
        ], 'Residential Property');

        $this->assertSame([
            'landlord_specific' => ['communication_style' => 'Email Only'],
            'tenant_specific'   => ['concerns_or_barriers' => 'kept'],
        ], $out);
    }

    /** @test */
    public function project_all_ignores_non_array_members(): void
    {
        $out = CompatibilityPreferencePolicy::projectAll([
            'landlord_specific' => 'not an array',
            'tenant_specific'   => ['concerns_or_barriers' => 'kept'],
        ]);

        $this->assertSame(['tenant_specific' => ['concerns_or_barriers' => 'kept']], $out);
    }

    // ── Property type resolution ─────────────────────────────────────────────

    /**
     * The self-authorising write. One Edit request can both flip property_type to commercial and
     * supply the commercial-only key, because both are public Livewire properties.
     *
     * @test
     */
    public function on_edit_the_stored_property_type_wins_over_the_submitted_one(): void
    {
        $resolved = CompatibilityPreferencePolicy::propertyTypeForProjection(
            'Residential Property',   // stored
            'Commercial Property'     // submitted in the same request
        );

        $this->assertSame('Residential Property', $resolved);

        $this->assertArrayNotHasKey('preferred_business_use',
            CompatibilityPreferencePolicy::project(
                ['preferred_business_use' => ['Office']],
                'landlord',
                $resolved
            ),
            'A request that flips the listing commercial must not also authorise the '
            . 'commercial-only key it carries.');
    }

    /** @test */
    public function on_create_the_submitted_property_type_is_used_because_nothing_is_stored_yet(): void
    {
        $resolved = CompatibilityPreferencePolicy::propertyTypeForProjection(null, 'Commercial Property');

        $this->assertSame('Commercial Property', $resolved);
        $this->assertSame(['Office'], CompatibilityPreferencePolicy::project(
            ['preferred_business_use' => ['Office']],
            'landlord',
            $resolved
        )['preferred_business_use']);
    }

    /** @test */
    public function an_empty_stored_property_type_falls_through_to_the_submitted_one(): void
    {
        $this->assertSame('Commercial Property',
            CompatibilityPreferencePolicy::propertyTypeForProjection('   ', 'Commercial Property'));
    }

    // ── Shape ────────────────────────────────────────────────────────────────

    /** @test */
    public function the_projection_narrows_and_never_widens(): void
    {
        // A partial sub-array must come back partial. Padding it with empty allowed keys would
        // overwrite stored answers with blanks on any partial save.
        $out = CompatibilityPreferencePolicy::project(
            ['communication_style' => 'Email Only'],
            'landlord',
            'Residential Property'
        );

        $this->assertSame(['communication_style' => 'Email Only'], $out);
        $this->assertCount(1, $out);
    }

    /** @test */
    public function the_config_is_read_only_through_this_class(): void
    {
        // Mirrors the guard HireAgentDetailRedesignFlagTest applies to its own config: the file
        // has one reader, and a second one would be a second opinion about the same rule.
        $roots = [base_path('app'), base_path('resources/views'), base_path('routes')];
        $hits  = [];

        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }
                $path = $file->getPathname();
                if (str_ends_with($path, 'CompatibilityPreferencePolicy.php')) {
                    continue;
                }
                // The actual READ, not a mention: comments elsewhere legitimately name the file
                // to point a reader at it, and a docs pointer is not a second opinion about the rule.
                if (str_contains((string) file_get_contents($path), "config('hire_agent_compatibility_keys")) {
                    $hits[] = $path;
                }
            }
        }

        $this->assertSame([], $hits,
            'config/hire_agent_compatibility_keys.php must be read only by '
            . 'CompatibilityPreferencePolicy. Found other readers: ' . implode(', ', $hits));
    }
}
