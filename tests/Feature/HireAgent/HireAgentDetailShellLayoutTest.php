<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Support\HireAgent\HireAgentDetailRedesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M7.1 — the shared shell's page layout, and the role allowlist that scopes it.
 *
 * WHY THIS SUITE EXISTS SEPARATELY FROM HireAgentShellStructureTest. That suite asserts the
 * shell's STRUCTURE — one grid row, columns as siblings, sidebar after main, nesting depth. It is
 * deliberately blind to Bootstrap width classes so that a legitimate layout change does not have
 * to edit a structural guard. This suite asserts the layout VALUES that M7.1 introduces, and the
 * flag that gates them. Both must pass; neither substitutes for the other.
 *
 * THE ALLOWLIST IS THE POINT. Before M7.1 the redesign flag was safe as a bare boolean because
 * the redesigned markup lived in one role view. This milestone moves layout into a component all
 * four roles render, so the same switch would have migrated three roles nobody reviewed. The
 * tests below pin both halves: landlord changes, and seller/buyer/tenant provably do not.
 *
 * FLAG OFF MUST CHANGE NOTHING, and that is asserted here at the markup level. It was also
 * verified end-to-end by byte-diffing the full rendered page for all four roles against the
 * pre-change tree — identical to the byte, modulo CSRF. That comparison cannot live in a test
 * (it needs both trees), so this records that it was done and pins the observable half.
 */
class HireAgentDetailShellLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    /** Every role EXCEPT the default pilot — the ones that must not move. */
    public static function nonPilotRoles(): array
    {
        return ['seller' => ['seller'], 'buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    // ── Fixtures (mirrors HireAgentShellStructureTest's wiring) ──────────────

    /** @return array{0: class-string, 1: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail'],
            'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction'],
            'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view'],
            'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view'],
        };
    }

    private function makeListing(string $role, int $ownerId): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' layout listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Shell Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Shell Street');
        }

        // Seller's controller redirects anything that looks like an Offer Listing; the workflow
        // stamp is what a real Hire Agent listing carries.
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ([
            'listing_title'   => ucfirst($role) . ' listing title',
            'budget'          => '654321',
            'property_type'   => 'Residential Property',
            'auction_type'    => 'Traditional',
            'expiration_date' => now()->addDays(30)->toDateTimeString(),
        ] as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function render(string $role): string
    {
        [, $route] = $this->wiringFor($role);
        $owner = User::factory()->create(['user_type' => 'seller']);

        return $this->actingAs($owner)
            ->get(route($route, $this->makeListing($role, $owner->id)->id))
            ->assertOk()
            ->getContent();
    }

    private function enableRedesign(array $roles = ['landlord']): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => $roles,
        ]);
    }

    // ── The flag reader ──────────────────────────────────────────────────────

    /**
     * The master switch still gates everything. A role on the allowlist gets nothing while the
     * master is off — the two must agree, which is the contract config/hire_agent_hero.php set.
     */
    public function test_the_master_switch_still_gates_every_role(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord', 'seller', 'buyer', 'tenant'],
        ]);

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $this->assertFalse(HireAgentDetailRedesign::enabledFor($role), "{$role} must be off.");
        }
    }

    /** With the master on, only allowlisted roles qualify. */
    public function test_only_allowlisted_roles_are_enabled(): void
    {
        $this->enableRedesign(['landlord']);

        $this->assertTrue(HireAgentDetailRedesign::enabledFor('landlord'));
        $this->assertFalse(HireAgentDetailRedesign::enabledFor('seller'));
        $this->assertFalse(HireAgentDetailRedesign::enabledFor('buyer'));
        $this->assertFalse(HireAgentDetailRedesign::enabledFor('tenant'));
    }

    /**
     * A role that is not in the list — including a near-miss — fails closed rather than
     * resolving to something that looks close enough.
     */
    public function test_an_unknown_or_misspelled_role_fails_closed(): void
    {
        $this->enableRedesign(['landlord']);

        foreach (['Landlord', 'landlords', 'land', '', 'admin'] as $role) {
            $this->assertFalse(
                HireAgentDetailRedesign::enabledFor($role),
                "'{$role}' must not match the allowlist."
            );
        }
    }

    /** enabled() keeps its old meaning; M7.1 added a method rather than changing one. */
    public function test_the_master_reader_is_unchanged(): void
    {
        config(['hire_agent_detail.redesign_enabled' => true, 'hire_agent_detail.redesign_roles' => []]);

        $this->assertTrue(HireAgentDetailRedesign::enabled(), 'enabled() answers the master switch only.');
        $this->assertFalse(HireAgentDetailRedesign::enabledFor('landlord'), 'enabledFor() also needs the list.');
    }

    /** The shipped default is the landlord pilot, and widening it must be deliberate. */
    public function test_the_shipped_default_allowlist_is_landlord_only(): void
    {
        $this->assertSame(['landlord'], config('hire_agent_detail.redesign_roles'));
    }

    // ── Flag OFF: the legacy grid, for every role ────────────────────────────

    /** @dataProvider roles */
    public function test_flag_off_renders_the_legacy_grid(string $role): void
    {
        $html = $this->render($role);

        $this->assertStringContainsString('col-sm-12 col-md-8 col-lg-8 leftCol', $html);
        $this->assertStringContainsString('col-sm-12 col-md-4 col-lg-4 rightCol', $html);
        $this->assertStringContainsString('class="container listingDescription"', $html);
        $this->assertStringContainsString('<div class="row">', $html);

        $this->assertStringNotContainsString('listingDescription py-4', $html);
        $this->assertStringNotContainsString('row g-4 align-items-start', $html);
        $this->assertStringNotContainsString('hla-sidebar-sticky', $html);
    }

    // ── Flag ON: the pilot moves, the others do not ──────────────────────────

    public function test_flag_on_applies_the_offer_listing_geometry_to_the_pilot(): void
    {
        $this->enableRedesign();
        $html = $this->render('landlord');

        $this->assertStringContainsString('class="container listingDescription py-4"', $html);
        $this->assertStringContainsString('class="row g-4 align-items-start"', $html);
        $this->assertStringContainsString('col-sm-12 col-md-8 col-lg-9 leftCol', $html);

        /*
         | M7.5 — the width classes are the shell's; the sticky no longer is.
         |
         | This asserted `... rightCol hla-sidebar-sticky` until M7.5, because M7.1 put the sticky
         | hook on the column. M7.1 also recorded why that could not work — a column carrying a
         | populated proposal console is as tall as the main column, and an element that is never
         | shorter than its container never sticks — and named the fix. M7.5 moved the class onto
         | a card INSIDE the sidebar that holds the status/CTA stack only.
         |
         | Both halves are asserted. Dropping the class from the expected string alone would still
         | pass if the class came back, because assertStringContainsString matches a prefix of the
         | live class list.
         */
        $this->assertStringContainsString('col-sm-12 col-md-4 col-lg-3 rightCol"', $html);
        $this->assertStringNotContainsString('rightCol hla-sidebar-sticky', $html);
    }

    /**
     * The reason the allowlist exists. The shell renders for all four roles, so without it this
     * assertion would fail on the switch that turns landlord on.
     *
     * @dataProvider nonPilotRoles
     */
    public function test_flag_on_leaves_every_non_pilot_role_on_the_legacy_grid(string $role): void
    {
        $this->enableRedesign(['landlord']);
        $html = $this->render($role);

        $this->assertStringContainsString('col-sm-12 col-md-8 col-lg-8 leftCol', $html, "{$role} main column moved.");
        $this->assertStringContainsString('col-sm-12 col-md-4 col-lg-4 rightCol', $html, "{$role} sidebar moved.");
        $this->assertStringNotContainsString('listingDescription py-4', $html);
        $this->assertStringNotContainsString('row g-4 align-items-start', $html);
        $this->assertStringNotContainsString('hla-sidebar-sticky', $html);
    }

    /** Widening the list is the only thing that migrates a role — no code change required. */
    public function test_widening_the_allowlist_migrates_that_role(): void
    {
        $this->enableRedesign(['landlord', 'seller']);

        $this->assertStringContainsString('col-lg-9 leftCol', $this->render('seller'));
        $this->assertStringContainsString('col-lg-8 leftCol', $this->render('tenant'), 'Tenant stays behind.');
    }

    // ── The landlord BODY obeys the allowlist too, not just the shell ────────

    /**
     * THE GAP THESE TWO TESTS CLOSE, AND WHY IT SURVIVED THREE MILESTONES.
     *
     * The landlord view gated its own markup on the MASTER switch while the shell — and therefore
     * the framework stylesheet's entire redesign block — gated on enabledFor($role). Both call
     * sites were individually defensible: the markup lives in the landlord file, so the role is a
     * property of the file; the shell serves four roles, so it must ask about one. What neither
     * accounted for is that the markup DEPENDS on the stylesheet. `.hla-field-grid` takes its
     * `display: flex` from that block, and a Bootstrap column without a flex parent degrades into
     * a block at 50% width — one field per line with the other half blank.
     *
     * So with the master on and landlord off the allowlist, the page emitted redesign markup with
     * none of the CSS that makes it a layout. It failed OPEN into a broken page rather than closed
     * into the legacy one, which is the wrong direction for a rollout switch.
     *
     * WHY NOTHING CAUGHT IT. test_the_master_reader_is_unchanged above constructs exactly this
     * configuration and asserts only that the two READERS disagree. That is a true statement about
     * the flag class and says nothing about the page. These two tests assert the page.
     *
     * THEY ASSERT MARKUP, NOT GEOMETRY, deliberately. `.hla-field-grid` and the section cards are
     * emitted by the body; the shell's own column classes are already covered above. Pinning the
     * body's markup is what makes this a guard on the gate rather than a second layout test.
     */
    public function test_the_landlord_body_renders_the_redesign_when_the_role_is_allowlisted(): void
    {
        $this->enableRedesign(['landlord']);
        $html = $this->render('landlord');

        $this->assertStringContainsString('hla-field-grid', $html, 'The body must emit the field grid.');
        $this->assertStringContainsString('col-lg-6 col-12 hla-field', $html, 'Half-span cells must render.');
        $this->assertStringContainsString('viho-card', $html, 'Sections must render as cards.');
    }

    /**
     * The master switch alone must NOT be enough to render this page's body.
     *
     * The allowlist is empty rather than holding another role, so the only thing distinguishing
     * this from the test above is landlord's membership — which is precisely the variable under
     * test. A non-empty list would leave open whether some other role's presence mattered.
     */
    public function test_the_landlord_body_stays_legacy_when_the_role_is_not_allowlisted(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => [],
        ]);

        // The precondition IS the scenario: master on, role off. Without it this test would still
        // pass with the flag entirely disabled and would be guarding nothing.
        $this->assertTrue(HireAgentDetailRedesign::enabled(), 'Precondition: the master switch is on.');
        $this->assertFalse(HireAgentDetailRedesign::enabledFor('landlord'), 'Precondition: landlord is not allowlisted.');

        $html = $this->render('landlord');

        $this->assertStringNotContainsString('hla-field-grid', $html, 'No field grid without the stylesheet that lays it out.');
        $this->assertStringNotContainsString('col-lg-6 col-12 hla-field', $html, 'No half-span cells.');
        $this->assertStringNotContainsString('hla-section-', $html, 'No section card anchors.');

        // And it is the LEGACY page, not merely an absence — the shell's own classes prove which
        // branch rendered rather than leaving "nothing matched" as the only evidence.
        $this->assertStringContainsString('col-sm-12 col-md-8 col-lg-8 leftCol', $html);
        $this->assertStringContainsString('class="container listingDescription"', $html);
    }

    // ── Selectors other suites and stylesheets depend on ─────────────────────

    /**
     * `leftCol`, `rightCol` and `listingDescription` survive BOTH branches.
     *
     * HireAgentShellStructureTest selects on all three by name; three role views carry their own
     * CSS hanging off .leftCol; and .listingDescription is shared with buyer_criteria,
     * seller_property and tenant_criteria. Adding width classes is safe, replacing these is not.
     *
     * @dataProvider roles
     */
    public function test_the_load_bearing_class_names_survive_both_branches(string $role): void
    {
        foreach ([false, true] as $on) {
            if ($on) {
                $this->enableRedesign(['seller', 'buyer', 'landlord', 'tenant']);
            }

            $html = $this->render($role);

            foreach (['leftCol', 'rightCol', 'listingDescription'] as $class) {
                $this->assertStringContainsString($class, $html, "{$role}: {$class} must survive.");
            }
        }
    }

    // ── The sticky rule ships with the class, not without it ─────────────────

    public function test_the_sticky_rule_is_emitted_only_for_an_enabled_role(): void
    {
        $off = $this->render('landlord');
        $this->assertStringNotContainsString('hla-sidebar-sticky', $off, 'No class and no rule when off.');

        $this->enableRedesign();
        $on = $this->render('landlord');
        $this->assertStringContainsString('.hla-detail-page .hla-sidebar-sticky', $on, 'Rule ships with the class.');
        $this->assertStringContainsString('position: sticky', $on);
        $this->assertStringContainsString('@media (min-width: 992px)', $on, 'Suppressed where the columns stack.');
    }

    /** Scoped, so a layout rule for four Hire Agent pages cannot become one for nine. */
    public function test_the_sticky_rule_is_scoped_to_the_hire_agent_page(): void
    {
        $this->enableRedesign();
        $html = $this->render('landlord');

        $this->assertStringContainsString('.hla-detail-page .hla-sidebar-sticky', $html);
        $this->assertStringNotContainsString(' .rightCol {', $html, 'Must not target rightCol globally.');
    }

    // ── Rollout scope is decided in config, never in markup ──────────────────

    /**
     * The shell must ask the service, not test a role name. An inline check would be a second
     * opinion about rollout scope living in markup, which is what the config key exists to stop.
     */
    public function test_the_shell_contains_no_inline_role_check(): void
    {
        $src = (string) file_get_contents(
            base_path('resources/views/components/hire-agent/detail-shell.blade.php')
        );

        $this->assertStringContainsString('HireAgentDetailRedesign::enabledFor($role)', $src);
        $this->assertDoesNotMatchRegularExpression(
            "/\\\$role\s*===\s*['\"]/",
            $src,
            'Rollout scope belongs in config/hire_agent_detail.php, not in Blade.'
        );
        $this->assertStringNotContainsString('in_array($role', $src);
    }

    /** Offer Listing is a visual reference only; M7.1 must not have touched it. */
    public function test_offer_listing_views_render_no_hire_agent_shell_classes(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $src = (string) file_get_contents(base_path("resources/views/offer-listing/{$role}/view.blade.php"));

            $this->assertStringNotContainsString('hla-sidebar-sticky', $src);
            $this->assertStringNotContainsString('detail-shell', $src);
        }
    }
}
