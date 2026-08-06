<?php

namespace Tests\Feature\LocationDna;

use Tests\TestCase;

/**
 * Phase 1c slice 1 — the Hire Buyer cascade wiring, and its blast radius.
 *
 * This suite is about SCOPE, not about the cascade's own behaviour (that is covered by the Rules,
 * Projection and state-resolution suites). Every assertion here answers one of two questions:
 *
 *   1. With the flag off, is every surface byte-identical to what it rendered before?
 *   2. With the flag on, can it reach anything other than Hire Buyer?
 *
 * The second question is sharper than it looks. The Hire Buyer property-preferences tab is
 * included by SIX views spanning all four roles — including Seller and Landlord — so "wire the
 * Buyer tab" unavoidably edits a file that Seller and Landlord render. What keeps them safe is
 * two independent guards, and both are asserted below.
 */
class HireBuyerGeographyCascadeWiringTest extends TestCase
{
    private const TAB = 'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php';

    private const WIDGET = 'resources/views/partials/location-dna/map-input.blade.php';

    private function read(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    /** The source of `geographyCascadeWorkflow()` alone, so unrelated user_type maps are excluded. */
    private function workflowMapBody(string $relative): string
    {
        $source = $this->read($relative);
        $start  = strpos($source, 'protected function geographyCascadeWorkflow(): ?string');

        $this->assertNotFalse($start, "{$relative} must declare geographyCascadeWorkflow()");

        $end = strpos($source, "\n    }", (int) $start);
        $this->assertNotFalse($end, "{$relative}: could not delimit geographyCascadeWorkflow()");

        return substr($source, (int) $start, ((int) $end) - ((int) $start));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · IT SHIPS OFF
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_master_gate_ships_disabled(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertFalse(
            $config['geography_cascade_enabled'],
            'The cascade must ship off. Manual visual verification is a documented prerequisite.'
        );
    }

    /**
     * The scope list names exactly what this slice wired, and nothing it did not.
     *
     * Listing an unwired workflow is the one configuration mistake that destroys data: the
     * cascade states all four geography keys whenever it is on, so a workflow enabled while its
     * tab still shows the legacy inputs submits four empty values over stored geography.
     */
    /** @test */
    public function the_scope_list_names_only_the_wired_workflow(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertSame(['hire_buyer'], $config['geography_cascade_workflows']);
    }

    /** The source default is untouched by this slice — census stays opt-in. */
    /** @test */
    public function the_geography_source_default_is_still_eloquent(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertSame('eloquent', $config['geography_source']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · SELLER AND LANDLORD CANNOT BE REACHED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Guard one: the shared tab defaults the flag to false for hosts that never declare it.
     *
     * SellerAgentAuction and LandLordAgentAuction include this exact partial and do not carry the
     * cascade trait. Without the null coalesce they would fatal on an undefined variable the
     * moment this slice landed; with it they render precisely what they rendered before.
     */
    /** @test */
    public function the_shared_tab_defaults_the_flag_off_for_hosts_that_do_not_declare_it(): void
    {
        $tab = $this->read(self::TAB);

        $this->assertStringContainsString('@if ($geoCascadeEnabled ?? false)', $tab);
        $this->assertStringContainsString("@include('partials.location-dna.geography-cascade')", $tab);
        $this->assertStringContainsString("'ldnaGeographyCascade'    => \$geoCascadeEnabled ?? false,", $tab);
    }

    /** The Seller and Landlord hire components must not carry the cascade trait. */
    /** @test */
    public function the_seller_and_landlord_components_do_not_carry_the_cascade(): void
    {
        foreach ([
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
        ] as $relative) {
            if (! file_exists(base_path($relative))) {
                continue;
            }

            $this->assertStringNotContainsString(
                'HasGeographyCascade',
                $this->read($relative),
                "{$relative} must not carry the cascade — Seller and Landlord have no geography surface."
            );
        }
    }

    /**
     * Guard two: the catch-all maps seller and landlord to NO workflow at all.
     *
     * This is the guard that does not depend on Blade. `TenantAgentAuction` serves all four roles
     * from one component, so a `user_type` of seller or landlord must resolve to null before the
     * scope list is ever consulted — which means no value of CRITERIA_LDNA_CASCADE_WORKFLOWS can
     * switch them on.
     */
    /** @test */
    public function the_catch_all_components_map_only_buyer_to_a_workflow(): void
    {
        foreach ([
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $body = $this->workflowMapBody($relative);

            $this->assertStringContainsString("'buyer' => 'hire_buyer',", $body);
            $this->assertStringContainsString('default => null,', $body);

            // Scoped to this method's body on purpose: both components map `user_type` in several
            // unrelated `match` expressions (draft model class, redirect routes), so a whole-file
            // search would read those as cascade wiring.
            $this->assertStringNotContainsString(
                "'seller' =>",
                $body,
                'Seller must fall through to null, never to a named workflow.'
            );
            $this->assertStringNotContainsString(
                "'landlord' =>",
                $body,
                'Landlord must fall through to null, never to a named workflow.'
            );
        }
    }

    /** `hire_tenant` is not wired in this slice and must not be claimed anywhere. */
    /** @test */
    public function the_tenant_workflow_is_not_yet_claimed(): void
    {
        foreach ([
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $this->assertStringNotContainsString(
                "'tenant' => 'hire_tenant'",
                $this->read($relative),
                'Hire Tenant is slice 2; its tab opt-in and scope entry must land together.'
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · ALL REACHABLE HIRE BUYER SURFACES ARE WIRED TOGETHER
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Every Hire Buyer surface carries the trait, boots it, hydrates it and merges it.
     *
     * Wiring the create side alone would produce a listing created with the cascade and edited
     * with the legacy editor — a broken round trip inside one user's own flow.
     */
    /** @test */
    public function every_hire_buyer_surface_is_wired_end_to_end(): void
    {
        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = $this->read($relative);

            $this->assertStringContainsString('HasGeographyCascade', $source, "{$relative} must carry the trait");
            $this->assertStringContainsString('bootGeographyCascade(', $source, "{$relative} must boot it");
            $this->assertStringContainsString('loadGeographyCascade(', $source, "{$relative} must hydrate it");
            $this->assertStringContainsString('applyGeographyCascadeToPayload(', $source, "{$relative} must merge it");
        }
    }

    /**
     * The merge is the LAST touch of the payload before the write.
     *
     * ORDERING IS THE WHOLE INTEGRATION. The Search Areas bridge re-serialises
     * `$location_dna_preferences_json` on every map interaction, so a merge performed anywhere
     * earlier than immediately before `saveSearchAreas()` would be overwritten by the next
     * interaction and the user's geography would silently vanish on save.
     */
    /** @test */
    public function the_merge_immediately_precedes_the_write_on_every_surface(): void
    {
        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = $this->read($relative);

            $merge = strpos($source, 'applyGeographyCascadeToPayload(');
            $write = strpos($source, '$this->saveSearchAreas(');

            $this->assertNotFalse($merge, "{$relative} must merge");
            $this->assertNotFalse($write, "{$relative} must write");
            $this->assertLessThan(
                $write,
                $merge,
                "{$relative}: the cascade merge must precede saveSearchAreas(), or the bridge overwrites it."
            );
        }
    }

    /** No surface reaches the G1 persistence layer — it is not on this branch and is out of scope. */
    /** @test */
    public function no_surface_introduces_the_g1_writer(): void
    {
        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = $this->read($relative);

            foreach ([
                'OwnerPrivateLocationDnaWriter',
                'LocationDnaPersistenceService',
                'LocationDna\\Contract',
                'LocationDna\\Capability',
                'LocationDna\\Provenance',
                'LocationDna\\Persistence',
            ] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$relative} must not reach {$forbidden}");
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · THE WIDGET SUPPRESSES ONLY ITS OWN TIER INPUTS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The shared widget defaults its opt-in off, so the surfaces that say nothing are unchanged.
     *
     * The same partial is included by many other pages; the default is what keeps this slice from
     * altering any of them.
     */
    /** @test */
    public function the_shared_widget_defaults_its_opt_in_off(): void
    {
        $this->assertStringContainsString(
            '$ldnaGeographyCascade  = $ldnaGeographyCascade ?? false;',
            $this->read(self::WIDGET)
        );
    }

    /**
     * Suppression is bounded to the tier inputs, and the map survives.
     *
     * The cascade replaces the four geography tier inputs — nothing else. Losing the draw tools
     * or Important Places along with them would be a silent feature regression, so the widget's
     * later sections must sit outside the conditional.
     */
    /** @test */
    public function suppression_covers_the_tier_inputs_but_not_the_map(): void
    {
        $widget = $this->read(self::WIDGET);

        $open  = strpos($widget, '@if (! $ldnaGeographyCascade)');
        $close = strpos($widget, '@endif', (int) $open);
        $map   = strpos($widget, 'Draw Custom Areas on Map');

        $this->assertNotFalse($open, 'the tier inputs must be conditional');
        $this->assertNotFalse($close);
        $this->assertNotFalse($map);
        $this->assertLessThan(
            $map,
            $close,
            'The map toolbar must fall OUTSIDE the suppressed block — the cascade replaces the '
            .'tier inputs only, never the drawing surface.'
        );
    }

    /**
     * The state input keeps its `if (stateEl)` guard.
     *
     * With the inputs suppressed the element is absent, and the serialiser reads it. Without this
     * guard the blob would throw on every map interaction for a cascade-enabled workflow.
     */
    /** @test */
    public function the_serialiser_tolerates_the_absent_state_input(): void
    {
        $this->assertStringContainsString('if (stateEl)', $this->read(self::WIDGET));
    }
}
