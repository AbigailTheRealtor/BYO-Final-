<?php

namespace Tests\Feature\Viho;

use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * The presentation dependency contract, enforced over the real tree.
 *
 * THE CONTRACT THIS REPLACES. Until now the boundary was stated by
 * HireAgentDetailFrameworkTest::test_create_offer_is_untouched_and_uncoupled, which asserted that
 * Hire Agent and Create Offer were disjoint in every respect — "no shared file, no shared class
 * name, no include across the boundary", with the CSS namespaces asserted disjoint too because
 * "a shared CSS prefix would be enough to couple them".
 *
 * That was the correct contract for a Hire-Agent-only refactor, where any contact between the two
 * products could only have been an accident. It is incompatible with the approved architecture,
 * which permits — indeed requires — both products to depend on one neutral shared library:
 *
 *     Hire Agent   ──────►  VIHO  ◄──────  Create Offer      permitted
 *     Hire Agent   ──✗──►  Create Offer                      forbidden
 *     Create Offer ──✗──►  Hire Agent                        forbidden
 *     VIHO         ──✗──►  either product                    forbidden
 *
 * The old assertion cannot express that shape: it forbids the two permitted edges along with the
 * three forbidden ones. The replacement is not a weakening. It is strictly stronger in the
 * direction that matters — the old test checked two Create Offer views for two substrings, while
 * this one classifies EVERY dependency of EVERY file in both products, in both directions, across
 * includes, extends, component tags, view() calls, PHP imports, asset references and CSS class
 * namespaces.
 *
 * WHAT IS NOT RELAXED. Nothing about proposal privacy. Create Offer keeps `_competing-bids` and
 * `PublicOfferFeedService`; Hire Agent still may not reach either, and that is now enforced as a
 * dependency edge rather than as a substring search over two files.
 *
 * @see \Tests\Support\PresentationDependencyScanner
 * @see \Tests\Feature\Viho\PresentationDependencyScannerTest for the scanner's own controls
 */
class PresentationDependencyContractTest extends TestCase
{
    private Scanner $scanner;

    /** The four Hire Agent detail views, which are the pages the migration will move. */
    private const HIRE_AGENT_VIEWS = [
        'resources/views/hire_seller_agent/view.blade.php',
        'resources/views/hire_buyer_agent/view.blade.php',
        'resources/views/hire_landlord_agent/view.blade.php',
        'resources/views/hire_tenant_agent/view.blade.php',
    ];

    private const CREATE_OFFER_VIEWS = [
        'resources/views/offer-listing/seller/view.blade.php',
        'resources/views/offer-listing/buyer/view.blade.php',
        'resources/views/offer-listing/landlord/view.blade.php',
        'resources/views/offer-listing/tenant/view.blade.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    // ── Product isolation (contract items 1–4) ───────────────────────────────

    /**
     * 1. No Hire Agent presentation file depends on anything Create Offer owns.
     *
     * Every file in the zone, not a sampled few: the zone covers the four detail views, the shared
     * shell and components, the framework stylesheet and the hero presenter.
     */
    public function test_hire_agent_does_not_depend_on_create_offer(): void
    {
        $this->assertZoneHasNoViolations(Scanner::ZONE_HIRE_AGENT);
    }

    /** 2. No Create Offer presentation file depends on anything Hire Agent owns. */
    public function test_create_offer_does_not_depend_on_hire_agent(): void
    {
        $this->assertZoneHasNoViolations(Scanner::ZONE_CREATE_OFFER);
    }

    /**
     * 3. Hire Agent uses none of Create Offer's CSS namespaces.
     *
     * Stated separately from the edge scan because a CSS prefix couples two pages without any
     * include ever appearing — the reason the original contract asserted namespace disjointness in
     * the first place. That concern survives the rewrite intact.
     */
    public function test_hire_agent_does_not_use_create_offer_css_namespaces(): void
    {
        $this->assertZoneUsesNoForeignCss(Scanner::ZONE_HIRE_AGENT, Scanner::ZONE_CREATE_OFFER);
    }

    /** 4. Create Offer uses none of Hire Agent's CSS namespaces. */
    public function test_create_offer_does_not_use_hire_agent_css_namespaces(): void
    {
        $this->assertZoneUsesNoForeignCss(Scanner::ZONE_CREATE_OFFER, Scanner::ZONE_HIRE_AGENT);
    }

    /**
     * The shared Hire Agent modal is not a cross-product edge, and the contract knows it.
     *
     * All four Create Offer views render `<x-hire-agent-modal>`. It lives at
     * `components/hire-agent-modal.blade.php` — beside the private `components/hire-agent/`
     * directory, not inside it — and is shared by design. This test pins that reading, because if
     * the zone prefixes ever lose their trailing slash the four tests above start failing for a
     * reason that has nothing to do with a real violation.
     */
    public function test_the_shared_hire_agent_modal_is_recognised_as_shared(): void
    {
        $this->assertTrue($this->scanner->exists('resources/views/components/hire-agent-modal.blade.php'));

        $this->assertSame(
            Scanner::ZONE_NEUTRAL,
            $this->scanner->zoneForPath('resources/views/components/hire-agent-modal.blade.php')
        );

        foreach (self::CREATE_OFFER_VIEWS as $view) {
            $this->assertStringContainsString(
                '<x-hire-agent-modal',
                $this->scanner->read($view),
                'Precondition: Create Offer renders the shared modal. If this ever stops being true '
                . 'the discrimination it guards is no longer exercised by the real tree.'
            );
        }
    }

    // ── Neutral shared dependency (contract items 5–9) ───────────────────────

    /**
     * 5 & 6. Both products are permitted to depend on the neutral VIHO namespace.
     *
     * Asserted as a property of the contract, not of today's tree: no VIHO file exists yet, so
     * nothing references it. What must be true now is that such a reference WOULD be legal — which
     * is precisely what the old contract made impossible and what M1 depends on.
     */
    public function test_both_products_may_depend_on_the_viho_namespace(): void
    {
        $reference = <<<'BLADE'
        @include('viho.tokens')
        <x-viho.hero :title="$t" />
        <div class="viho-hero">x</div>
        @php use App\Support\Viho\VihoHeroData; @endphp
        BLADE;

        foreach ([self::HIRE_AGENT_VIEWS[0], self::CREATE_OFFER_VIEWS[0]] as $consumer) {
            $this->assertSame(
                [],
                $this->scanner->violationsIn($consumer, $reference),
                "{$consumer} must be free to consume the neutral shared library."
            );
        }
    }

    /**
     * 7 & 8. The VIHO namespace may not reference either product.
     *
     * A shared library that reaches back into one of its consumers is not shared; it is that
     * consumer's code with extra steps, and the other consumer inherits it.
     */
    public function test_viho_may_not_reference_either_product(): void
    {
        foreach ([
            'a Hire Agent view'      => "@include('hire_agent.framework.styles')",
            'a Hire Agent component' => '<x-hire-agent.hero role="seller" />',
            'a Hire Agent presenter' => '@php use App\Support\HireAgent\HireAgentHeroData; @endphp',
            'a Hire Agent class'     => '<div class="hla-hero">x</div>',
            'a Create Offer view'    => "@include('offer-listing.partials._competing-bids')",
            'a Create Offer class'   => '<div class="sol-hero">x</div>',
        ] as $label => $source) {
            $this->assertNotEmpty(
                $this->scanner->violationsIn('resources/views/components/viho/hero.blade.php', $source),
                "The shared library must not reference {$label}."
            );
        }
    }

    /**
     * 9. VIHO stays presentation-only.
     *
     * Enforced over any VIHO file that exists. The rule matters most for what it keeps OUT: the
     * proposal-privacy decision of Milestone 2 and the timer retirement of Milestone 3 hold because
     * those decisions live in controllers and services. A shared component that started making them
     * would own them for both products at once, where neither product's tests are looking.
     */
    public function test_viho_contains_no_non_presentation_logic(): void
    {
        $files = $this->scanner->filesInZone(Scanner::ZONE_VIHO);

        if ($files === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $found = [];
        foreach ($files as $file) {
            $found = array_merge($found, $this->scanner->nonPresentationSymbolsIn($file, $this->scanner->read($file)));
        }

        $this->assertSame([], $found, "The shared library must stay presentation-only:\n" . implode("\n", $found));
    }

    // ── Current-state protection (contract items 10–14) ──────────────────────

    /**
     * 10. Hire Agent's presentation is as Milestone 5A left it, except for the migrated roles.
     *
     * AMENDED IN M3, FOUR TIMES. It first asserted that no Hire Agent view consumed VIHO at all;
     * the Landlord pilot narrowed it to three unmigrated roles, Seller to two, Buyer to one, and
     * Tenant to none. Every role view is now expected to consume VIHO.
     *
     * What this test still asserts, and why it is not vacuous: each of the four is checked
     * INDIVIDUALLY, so a role silently losing VIHO in a later refactor fails here by name; and
     * every one of them must still render the shared detail shell, which is the guarantee that
     * the migration changed presentation inside the main column and not the page skeleton. The
     * "must not consume" half now lives where the risk moved to — the shared Hire Agent files and
     * Create Offer — and is asserted in VihoPresentationPrimitivesTest and its Create Offer twin.
     */
    public function test_every_hire_agent_role_view_consumes_viho_and_keeps_the_shared_shell(): void
    {
        foreach (self::HIRE_AGENT_VIEWS as $view) {
            $src = $this->scanner->read($view);

            $this->assertStringContainsString(
                '<x-hire-agent.detail-shell',
                $src,
                "{$view} must still render the shared detail shell — the migration was presentation "
                . 'inside the main column, not a change to the page skeleton.'
            );

            $this->assertStringContainsString(
                '<x-viho.',
                $src,
                "{$view} is a migrated role and is expected to consume VIHO."
            );
        }

        foreach ([
            'resources/views/hire_agent/framework/styles.blade.php',
            'resources/views/components/hire-agent/detail-shell.blade.php',
            'resources/views/components/hire-agent/hero.blade.php',
            'resources/views/components/hire-agent/info-card.blade.php',
            'resources/views/components/hire-agent/field.blade.php',
            'resources/views/components/hire-agent/flash.blade.php',
            'app/Support/HireAgent/HireAgentHeroData.php',
        ] as $file) {
            $this->assertTrue($this->scanner->exists($file), "{$file} must still exist after M0.");
        }

        $this->assertStringContainsString(
            '.hla-hero',
            $this->scanner->read('resources/views/hire_agent/framework/styles.blade.php'),
            'Hire Agent keeps its own namespace until the visual migration replaces it.'
        );
    }

    /**
     * 11. Create Offer's presentation is untouched.
     *
     * Each role still declares its own tokens and its own prefix. The four-way duplication is the
     * thing M8 removes; recording it here means the later de-duplication has to be deliberate.
     */
    public function test_create_offer_presentation_is_unchanged(): void
    {
        foreach ([
            'resources/views/offer-listing/seller/view.blade.php'   => 'sol-',
            'resources/views/offer-listing/buyer/view.blade.php'    => 'bol-',
            'resources/views/offer-listing/landlord/view.blade.php' => 'lol-',
            'resources/views/offer-listing/tenant/view.blade.php'   => 'tcl-',
        ] as $view => $prefix) {
            $src = $this->scanner->read($view);

            $this->assertStringContainsString("{$prefix}view-page", $src, "{$view} keeps its own namespace.");
            $this->assertStringContainsString('--viho-primary:', $src, "{$view} keeps its own token block until M8.");
            $this->assertStringNotContainsString('x-viho.', $src, "{$view} must not consume VIHO yet — that is M8.");
        }

        $this->assertStringContainsString(
            'sol-hero',
            $this->scanner->read('resources/views/offer-listing/seller/view.blade.php'),
            "Create Offer's Seller view keeps its own separately-audited hero."
        );
    }

    /**
     * 12. The shared library does not exist yet — and if it ever does, it is neutral.
     *
     * Written as a conditional rather than a flat "must not exist" so M1 does not have to delete
     * it. When the VIHO paths appear, this test starts enforcing their neutrality instead of their
     * absence, and the assertion above (item 9) enforces the rest.
     */
    public function test_the_viho_library_is_absent_or_neutral(): void
    {
        $files = $this->scanner->filesInZone(Scanner::ZONE_VIHO);

        if ($files === []) {
            $this->assertFalse($this->scanner->exists('resources/views/viho'), 'M0 creates no VIHO production paths.');
            $this->assertFalse($this->scanner->exists('resources/views/components/viho'));
            $this->assertFalse($this->scanner->exists('app/Support/Viho'));

            return;
        }

        $violations = [];
        foreach ($files as $file) {
            $violations = array_merge($violations, $this->scanner->violationsIn($file, $this->scanner->read($file)));
        }

        $this->assertSame([], $violations, "VIHO must depend on neither product:\n" . implode("\n", $violations));
    }

    /**
     * 13. The privacy, timer and competing-bids protections are intact, restated as edges.
     *
     * The old contract proved Create Offer still owned `_competing-bids` and that the Hire Agent
     * framework files did not mention `PublicOfferFeedService`. Both facts are preserved; the Hire
     * Agent half is now checked across the whole zone rather than six named files.
     */
    public function test_proposal_privacy_boundary_is_intact(): void
    {
        $partial = 'resources/views/offer-listing/partials/_competing-bids.blade.php';

        $this->assertTrue($this->scanner->exists($partial));
        $this->assertStringContainsString('PublicOfferFeedService', $this->scanner->read($partial));

        foreach (['seller', 'landlord'] as $role) {
            $this->assertStringContainsString(
                "@include('offer-listing.partials._competing-bids'",
                $this->scanner->read("resources/views/offer-listing/{$role}/view.blade.php"),
                'Create Offer keeps its own competing-bids surface.'
            );
        }

        foreach ($this->scanner->filesInZone(Scanner::ZONE_HIRE_AGENT) as $file) {
            $src = Scanner::stripComments($this->scanner->read($file));

            $this->assertStringNotContainsString('PublicOfferFeedService', $src, $file);
            $this->assertStringNotContainsString('_competing-bids', $src, $file);
            $this->assertStringNotContainsString('CompetingBidsService', $src, $file);
        }
    }

    /**
     * 14. Neither product was made to depend on the other in order to satisfy the new tests.
     *
     * The cheapest way to make a dependency-direction contract pass is to add the dependency and
     * declare it intended. The count of cross-product edges is zero, and this states it as a
     * number so a future diff cannot quietly raise it.
     */
    public function test_there_are_zero_cross_product_edges(): void
    {
        $edges = [];

        foreach ([Scanner::ZONE_HIRE_AGENT, Scanner::ZONE_CREATE_OFFER] as $zone) {
            foreach ($this->scanner->filesInZone($zone) as $file) {
                $edges = array_merge($edges, $this->scanner->violationsIn($file, $this->scanner->read($file)));
            }
        }

        $this->assertCount(0, $edges, "Cross-product edges must remain at zero:\n" . implode("\n", $edges));
    }

    /**
     * The orphaned `components/listing/*` library is not adopted as the shared namespace.
     *
     * Six components — section, kv-row, accordion, pills, client-info, services-list — with zero
     * references anywhere in `resources/views/`. They are a previous attempt at exactly the library
     * M1 will build. M0 neither adopts, modifies nor deletes them; their retirement is a later
     * milestone. This records the state so adopting them becomes a decision rather than a drift.
     */
    public function test_the_orphaned_listing_components_remain_unadopted(): void
    {
        $orphans = [
            'section', 'kv-row', 'accordion', 'pills', 'client-info', 'services-list',
        ];

        foreach ($orphans as $name) {
            $path = "resources/views/components/listing/{$name}.blade.php";

            $this->assertTrue($this->scanner->exists($path), "{$path} is left in place by M0.");
            $this->assertSame(
                Scanner::ZONE_NEUTRAL,
                $this->scanner->zoneForPath($path),
                'The orphaned library is not the VIHO namespace.'
            );
        }

        foreach (array_merge(
            $this->scanner->filesInZone(Scanner::ZONE_HIRE_AGENT),
            $this->scanner->filesInZone(Scanner::ZONE_CREATE_OFFER)
        ) as $file) {
            $this->assertStringNotContainsString(
                '<x-listing.',
                Scanner::stripComments($this->scanner->read($file)),
                "{$file} must not adopt the orphaned components/listing library."
            );
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function assertZoneHasNoViolations(string $zone): void
    {
        $files = $this->scanner->filesInZone($zone);

        $this->assertNotEmpty($files, "Precondition: the {$zone} zone must contain files to scan.");

        $violations = [];
        foreach ($files as $file) {
            $violations = array_merge($violations, $this->scanner->violationsIn($file, $this->scanner->read($file)));
        }

        $this->assertSame(
            [],
            $violations,
            "Forbidden cross-product dependencies in {$zone}:\n" . implode("\n", $violations)
        );
    }

    private function assertZoneUsesNoForeignCss(string $zone, string $foreign): void
    {
        $offenders = [];

        foreach ($this->scanner->filesInZone($zone) as $file) {
            foreach ($this->scanner->cssZoneReferencesIn($this->scanner->read($file)) as $ref) {
                if ($ref['zone'] === $foreign) {
                    $offenders[] = "{$file} uses .{$ref['token']} ({$foreign})";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "{$zone} must not style itself with {$foreign}'s namespace:\n" . implode("\n", $offenders)
        );
    }
}
