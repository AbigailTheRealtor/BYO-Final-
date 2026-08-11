<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\Concerns\HasGeographyCascade;
use App\Http\Livewire\TenantAgentAuction;
use App\Http\Livewire\TenantAgentAuctionEdit;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Commit 1 of the cascade rollout — the guard that must exist BEFORE any workflow is wired.
 *
 * WHY THIS SUITE EXISTS SEPARATELY FROM HireBuyerGeographyCascadeWiringTest
 * -------------------------------------------------------------------------
 * That suite already asserts Seller/Landlord exclusion, and it does so by reading source text:
 * it greps `geographyCascadeWorkflow()` for `default => null,` and for the absence of
 * `'seller' =>`. That is a real guard and it stays. But it pins the SHAPE OF THE SOURCE, not the
 * BEHAVIOUR, and the rollout is about to edit exactly the `match` expression it reads.
 *
 * A textual guard passes in at least three ways that would ship the bug:
 *
 *   1. The arm is written in a form the string search does not recognise — `'seller','landlord' =>`,
 *      a different quote style, a trailing comment between the key and the arrow, or the map
 *      rewritten as an `if`/array lookup that contains no `default => null,` at all.
 *   2. The arms stay correct but `geographyCascadeIsEnabledFor()` or `bootGeographyCascade()`
 *      changes so that a null workflow no longer disables the cascade. The source of the match
 *      still reads exactly as the assertion expects.
 *   3. A fifth role is added to the match whose value is a live workflow key, leaving the seller
 *      and landlord arms untouched and the search satisfied.
 *
 * So this suite asserts the OUTCOME instead: with the configuration set as permissively as the
 * system allows, a seller and a landlord still resolve to no workflow and a disabled cascade.
 * Every assertion here runs the real code path rather than reading it.
 *
 * THE PERMISSIVE CONFIGURATION IS THE POINT
 * -----------------------------------------
 * Each test runs with the master gate ON, all four valid workflow keys in scope, the census
 * source selected and the neighbourhood tier ON — a configuration strictly more permissive than
 * any environment will ever run, and one that no shipped default produces. Asserting exclusion
 * under the default configuration would prove almost nothing, because the master gate alone is
 * enough to make everything false. The claim under test is the STRUCTURAL one from
 * TenantAgentAuction::geographyCascadeWorkflow(): that no value of CRITERIA_LDNA_CASCADE_WORKFLOWS
 * can reach Seller or Landlord.
 *
 * NO PRODUCTION CODE IS TOUCHED BY THIS COMMIT, and none of these assertions requires any. They
 * characterise today's behaviour so that slices A–C have something to break.
 *
 * @see \Tests\Feature\LocationDna\HireBuyerGeographyCascadeWiringTest the textual counterpart
 */
class SellerLandlordCascadeExclusionTest extends TestCase
{
    /**
     * The components that serve Seller and Landlord, and therefore the ones that could leak.
     *
     * Both are the SAME catch-all class family: one Livewire component drives all four roles off
     * `$user_type`. That is precisely why exclusion has to be proven rather than assumed — Seller
     * and Landlord run through the identical code path that Buyer uses to switch the cascade on.
     *
     * @var list<class-string>
     */
    private const CATCH_ALL_COMPONENTS = [
        TenantAgentAuction::class,
        TenantAgentAuctionEdit::class,
    ];

    /** The roles that must never reach a workflow, whatever the configuration says. */
    private const EXCLUDED_ROLES = ['seller', 'landlord'];

    /**
     * Every workflow key the configuration documents as valid, mapped to the tab that must render
     * the cascade before the key may be listed.
     *
     * @var array<string, string>
     */
    private const WORKFLOW_TABS = [
        'hire_buyer'   => 'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php',
        'hire_tenant'  => 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php',
        'create_buyer' => 'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php',
        'create_tenant' => 'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php',
    ];

    /**
     * Turn every gate on at once.
     *
     * Deliberately more permissive than production will ever be. If exclusion survives this, it
     * survives any real environment.
     */
    private function withEveryGateOpen(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => array_keys(self::WORKFLOW_TABS),
            'criteria_location_dna.geography_source'            => 'census',
            'criteria_location_dna.neighborhood_tier_enabled'   => true,
        ]);
    }

    /**
     * A component instance with no framework lifecycle run against it.
     *
     * `newInstanceWithoutConstructor()` keeps this a test of the cascade rather than of Livewire's
     * mount: these components hydrate auctions, resolve drafts and touch several services during a
     * real mount, none of which this suite is asking about. The cascade methods read only
     * `$user_type` and config, so an un-mounted instance answers the question exactly.
     */
    private function componentFor(string $class, string $userType): object
    {
        $component = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $component->user_type = $userType;

        return $component;
    }

    /** Invoke the protected workflow map without altering its visibility in production code. */
    private function workflowFor(string $class, string $userType): ?string
    {
        $component = $this->componentFor($class, $userType);

        $method = new ReflectionMethod($component, 'geographyCascadeWorkflow');
        $method->setAccessible(true);

        return $method->invoke($component);
    }

    /** Boot the cascade exactly as `mount()` does, and hand back the booted component. */
    private function bootedComponentFor(string $class, string $userType): object
    {
        $component = $this->componentFor($class, $userType);

        $workflow = new ReflectionMethod($component, 'geographyCascadeWorkflow');
        $workflow->setAccessible(true);

        $boot = new ReflectionMethod($component, 'bootGeographyCascade');
        $boot->setAccessible(true);
        $boot->invoke($component, $workflow->invoke($component));

        return $component;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE CONTROL — the permissive configuration really is permissive
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Buyer DOES switch on under this configuration.
     *
     * WITHOUT THIS TEST THE WHOLE SUITE COULD PASS VACUOUSLY. Every assertion below is a negative
     * — "seller gets nothing" — and a negative assertion is satisfied just as well by a
     * configuration that switches nothing on for anybody, by a typo'd config key, or by a helper
     * that silently fails to set anything. This is the positive control that proves the gates are
     * genuinely open when the exclusions are measured.
     *
     * @test
     */
    public function the_permissive_configuration_does_switch_the_cascade_on_for_buyer(): void
    {
        $this->withEveryGateOpen();

        foreach (self::CATCH_ALL_COMPONENTS as $class) {
            $this->assertSame(
                'hire_buyer',
                $this->workflowFor($class, 'buyer'),
                "{$class}: buyer must map to hire_buyer, or this suite is not measuring anything."
            );

            $component = $this->bootedComponentFor($class, 'buyer');

            $this->assertTrue(
                $component->geoCascadeEnabled,
                "{$class}: the control role must be ENABLED under the permissive config."
            );
            $this->assertTrue(
                $component->geoNeighborhoodTierEnabled,
                "{$class}: the tier must also be on, proving every gate in this suite is open."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE EXCLUSION, MEASURED RATHER THAN READ
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Seller and Landlord resolve to NO workflow, with every key in scope.
     *
     * This is the assertion the rollout is most likely to break: slices A–C all edit this exact
     * `match` expression.
     *
     * @test
     */
    public function seller_and_landlord_resolve_to_no_workflow_under_every_key(): void
    {
        $this->withEveryGateOpen();

        foreach (self::CATCH_ALL_COMPONENTS as $class) {
            foreach (self::EXCLUDED_ROLES as $role) {
                $this->assertNull(
                    $this->workflowFor($class, $role),
                    "{$class}: '{$role}' must resolve to null even with all four workflow keys in "
                    .'scope. Exclusion is structural — no configuration value may reach it.'
                );
            }
        }
    }

    /**
     * And the booted cascade is OFF for them, which is the claim that actually matters.
     *
     * A null workflow is only meaningful because of what boot does with it. Asserting the booted
     * flags closes the gap the textual guard leaves: it keeps holding even if the map is rewritten
     * in a form no string search would recognise.
     *
     * @test
     */
    public function seller_and_landlord_boot_with_the_cascade_disabled(): void
    {
        $this->withEveryGateOpen();

        foreach (self::CATCH_ALL_COMPONENTS as $class) {
            foreach (self::EXCLUDED_ROLES as $role) {
                $component = $this->bootedComponentFor($class, $role);

                $this->assertFalse(
                    $component->geoCascadeEnabled,
                    "{$class}: the cascade must be disabled for '{$role}'."
                );
                $this->assertSame(
                    '',
                    $component->geoWorkflow,
                    "{$class}: '{$role}' must carry no workflow key."
                );
            }
        }
    }

    /**
     * The neighbourhood tier cannot reach them either.
     *
     * The tier rides ON the cascade rather than beside it, so this follows from the test above —
     * but it follows only as long as that remains true. Pinned separately so that decoupling the
     * tier from the cascade fails here rather than silently exposing Seller and Landlord to a
     * surface no one has ever designed for them.
     *
     * NOTHING HERE MODIFIES TIER BEHAVIOUR. It observes it.
     *
     * @test
     */
    public function the_neighborhood_tier_cannot_reach_seller_or_landlord(): void
    {
        $this->withEveryGateOpen();

        foreach (self::CATCH_ALL_COMPONENTS as $class) {
            foreach (self::EXCLUDED_ROLES as $role) {
                $this->assertFalse(
                    $this->bootedComponentFor($class, $role)->geoNeighborhoodTierEnabled,
                    "{$class}: the neighbourhood tier must stay off for '{$role}' even with its own flag on."
                );
            }
        }
    }

    /**
     * An unrecognised role is excluded too.
     *
     * The map must be an allowlist. A `default` arm that falls through to a live workflow would
     * make every future role cascade-enabled on the day it is added — the failure mode where
     * nothing looks wrong until data is already being written.
     *
     * @test
     */
    public function an_unknown_role_is_excluded_by_default(): void
    {
        $this->withEveryGateOpen();

        foreach (self::CATCH_ALL_COMPONENTS as $class) {
            $this->assertNull(
                $this->workflowFor($class, 'some_future_role'),
                "{$class}: the workflow map must be an allowlist, not a denylist."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE DEDICATED SELLER AND LANDLORD COMPONENTS CARRY NO CASCADE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Asserted against the resolved trait list rather than the source text.
     *
     * `class_uses_recursive` sees a trait pulled in through a parent class or through another
     * trait; a source search for the token sees neither.
     *
     * @test
     */
    public function the_dedicated_seller_and_landlord_components_do_not_use_the_cascade_trait(): void
    {
        foreach ([
            \App\Http\Livewire\HireSellerAgent\SellerAgentAuction::class,
            \App\Http\Livewire\HireSellerAgent\SellerAgentAuctionEdit::class,
            \App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction::class,
            \App\Http\Livewire\HireLandLordAgent\LandLordAgentAuctionEdit::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "{$class} must exist for this guard to mean anything.");

            $this->assertNotContains(
                HasGeographyCascade::class,
                class_uses_recursive($class),
                "{$class} must not carry the cascade — Seller and Landlord have no geography surface."
            );
        }
    }

    /**
     * No Seller or Landlord view renders the geography surface at all.
     *
     * This is the structural fact the exclusion rests on, and it is the one a future contributor is
     * most likely to undo by accident — adding the shared widget to a Seller tab because it looked
     * like the Buyer tab. The widget include is what would give Seller a geography surface in the
     * first place; without it there is nothing for a cascade to replace.
     *
     * THE LIVEWIRE TAB DIRECTORIES ARE THE ONES THAT MATTER MOST, AND THEY WERE MISSING
     * ---------------------------------------------------------------------------------
     * The first version of this list named only the controller-rendered view folders
     * (`hire_seller_agent`, `landlord_auction`, …) and the Offer Listing components. It omitted
     * `livewire/hire-seller-agent` and `livewire/hire-landlord-agent` — which is precisely where the
     * Seller and Landlord `property-preferences` TABS live, and therefore the likeliest place for
     * someone to paste the Buyer tab's widget include. The guard was blind to the exact files it
     * most needed to watch. Thirty-six files across those two directories are now covered.
     *
     * A MISSING ROOT IS A FAILURE, NOT A SKIP
     * ---------------------------------------
     * The list used to `continue` past a directory that did not exist, so a renamed or moved folder
     * would quietly stop being checked and the suite would still pass — the same silence that let
     * the omission above go unnoticed. Each root is now asserted to exist and to contain at least
     * one file, so losing coverage fails loudly instead of shrinking invisibly.
     *
     * @test
     */
    public function no_seller_or_landlord_view_renders_the_geography_surface(): void
    {
        $roots = [
            // Controller-rendered views.
            'resources/views/hire_seller_agent',
            'resources/views/hire_landlord_agent',
            'resources/views/seller_property',
            'resources/views/landlord_auction',
            // Livewire component views.
            'resources/views/livewire/offer-listing/seller',
            'resources/views/livewire/offer-listing/landlord',
            // Livewire TAB partials — where a Buyer-tab copy/paste would actually land.
            'resources/views/livewire/hire-seller-agent',
            'resources/views/livewire/hire-landlord-agent',
        ];

        $checked = 0;

        foreach ($roots as $root) {
            $path = base_path($root);

            $this->assertDirectoryExists(
                $path,
                "{$root} is missing. If it moved, update this list — a root that silently disappears "
                .'takes its coverage with it and the guard keeps passing.'
            );

            $before = $checked;

            /** @var iterable<\SplFileInfo> $files */
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                $checked++;

                foreach ([
                    'partials.location-dna.map-input',
                    'partials.location-dna.geography-cascade',
                    'ldnaGeographyCascade',
                    'geoCascadeEnabled',
                ] as $marker) {
                    $this->assertStringNotContainsString(
                        $marker,
                        $source,
                        $file->getPathname().' renders a geography surface. Seller and Landlord carry '
                        .'none — adding one is new feature work, not a cascade rollout.'
                    );
                }
            }

            $this->assertGreaterThan(
                $before,
                $checked,
                "{$root} contains no PHP files. An empty root asserts nothing, so it is either the "
                .'wrong path or a folder whose contents moved.'
            );
        }

        $this->assertGreaterThan(0, $checked, 'The Seller/Landlord view roots must not all be missing.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · CONFIG AGREEMENT — the guard against the empty-geography wipe
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Every workflow the shipped config lists must already have a tab that renders the cascade.
     *
     * THIS IS THE DATA-LOSS GUARD, and it is the reason this commit lands before slice A. The
     * cascade states all four geography keys whenever it is enabled, so a workflow listed here
     * while its tab still shows the legacy inputs submits four EMPTY values over stored geography
     * and silently clears the user's selection. The config file states the rule in prose; this
     * turns it into a failing build.
     *
     * @test
     */
    public function every_configured_workflow_already_renders_the_cascade(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        foreach ($config['geography_cascade_workflows'] as $workflow) {
            $this->assertArrayHasKey(
                $workflow,
                self::WORKFLOW_TABS,
                "'{$workflow}' is not a documented workflow key. Valid keys are: "
                .implode(', ', array_keys(self::WORKFLOW_TABS)).'.'
            );

            $tab = base_path(self::WORKFLOW_TABS[$workflow]);

            $this->assertFileExists($tab, "'{$workflow}' is in scope but its tab is missing.");

            $this->assertStringContainsString(
                'ldnaGeographyCascade',
                (string) file_get_contents($tab),
                "'{$workflow}' is listed in geography_cascade_workflows but its tab does not opt in. "
                .'The tab opt-in and the scope entry MUST land in the same commit, or this workflow '
                .'writes four empty geography keys over stored data.'
            );
        }
    }

    /**
     * Seller and Landlord have no tab mapping, so no config value can name them.
     *
     * @test
     */
    public function no_workflow_key_belongs_to_seller_or_landlord(): void
    {
        foreach (array_keys(self::WORKFLOW_TABS) as $workflow) {
            foreach (self::EXCLUDED_ROLES as $role) {
                $this->assertStringNotContainsString(
                    $role,
                    $workflow,
                    "'{$workflow}' names an excluded role. Seller and Landlord carry no geography surface."
                );
            }
        }
    }

    /**
     * This commit enables nothing.
     *
     * The rollout plan turns workflows on one slice at a time; Commit 1 is tests only. If this
     * fails, something in this commit reached production configuration.
     *
     * @test
     */
    public function this_commit_leaves_every_gate_shipped_off(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertFalse($config['geography_cascade_enabled'], 'The master gate must still ship off.');
        $this->assertFalse($config['neighborhood_tier_enabled'], 'The neighbourhood tier must still ship off.');
        $this->assertSame(['hire_buyer'], $config['geography_cascade_workflows'], 'No workflow may be added by this commit.');
        $this->assertSame('eloquent', $config['geography_source'], 'The source default must stay eloquent.');
    }
}
