<?php

namespace Tests\Feature\Viho;

use Illuminate\Support\Facades\Blade;
use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * Controls for the M2 neutrality guards.
 *
 * VihoPresentationPrimitivesTest asserts that no VIHO component references a product, uses a
 * product CSS prefix, or reaches into authorization or the database. All of those pass today. A
 * guard that could never fail would pass identically, and would go on passing through every
 * milestone that adds components — so each guard is exercised here against a fixture that SHOULD
 * trip it.
 *
 * Every fixture is a source string held in memory. Nothing is written under
 * `resources/views/components/viho/`, because a deliberately-invalid component sitting on disk is
 * not a test — it is a defect with a timer on it, and `test_the_component_directory_holds_only_
 * approved_primitives` would fail on it besides.
 *
 * The path passed to the scanner is a plausible VIHO path so that zone resolution puts the fixture
 * in the right zone; the file itself never exists.
 */
class VihoPrimitiveGuardControlsTest extends TestCase
{
    /** A VIHO-zone path that is never created on disk. */
    private const FIXTURE_PATH = 'resources/views/components/viho/__fixture.blade.php';

    private Scanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    protected function tearDown(): void
    {
        // Belt and braces: prove the fixture path was never materialised.
        $this->assertFileDoesNotExist(base_path(self::FIXTURE_PATH));

        parent::tearDown();
    }

    // ── Cross-product dependency controls ────────────────────────────────────

    /**
     * @dataProvider forbiddenDependencies
     */
    public function test_a_viho_component_referencing_a_product_is_rejected(string $label, string $source): void
    {
        $violations = $this->scanner->violationsIn(self::FIXTURE_PATH, $source);

        $this->assertNotEmpty(
            $violations,
            "A VIHO component that references {$label} must be rejected."
        );
    }

    public static function forbiddenDependencies(): array
    {
        return [
            'a Hire Agent include'     => ['a Hire Agent include', "@include('hire_agent.framework.styles')"],
            'a Hire Agent component'   => ['a Hire Agent component', '<x-hire-agent.detail-shell role="seller" />'],
            'a Hire Agent presenter'   => ['a Hire Agent presenter', '@php use App\Support\HireAgent\HireAgentHeroData; @endphp'],
            'a Create Offer partial'   => ['a Create Offer partial', "@include('offer-listing.partials._competing-bids')"],
            'a Create Offer view'      => ['a Create Offer view', "@extends('offer-listing.seller.view')"],
            'the hla- prefix'          => ['the hla- css prefix', '<div class="hla-hero">x</div>'],
            'the sol- prefix'          => ['the sol- css prefix', '<div class="sol-hero">x</div>'],
            'the bol- prefix'          => ['the bol- css prefix', '<div class="bol-hero">x</div>'],
            'the lol- prefix'          => ['the lol- css prefix', '<div class="lol-hero">x</div>'],
            'the tcl- prefix'          => ['the tcl- css prefix', '<div class="tcl-hero">x</div>'],
        ];
    }

    // ── Presentation-only controls ───────────────────────────────────────────

    /**
     * @dataProvider forbiddenLogic
     */
    public function test_a_viho_component_containing_business_logic_is_rejected(string $label, string $source): void
    {
        $this->assertNotEmpty(
            $this->scanner->nonPresentationSymbolsIn(self::FIXTURE_PATH, $source),
            "A VIHO component that performs {$label} must be rejected."
        );
    }

    public static function forbiddenLogic(): array
    {
        return [
            'an Auth call'        => ['an Auth call', '@if (Auth::check()) owner @endif'],
            'a user inspection'   => ['a user inspection', '@if (auth()->id() === $ownerId) owner @endif'],
            'a gate check'        => ['a gate check', '@can("update", $listing) edit @endcan'],
            'a DB query'          => ['a DB query', '@php $r = DB::table("bids")->get(); @endphp'],
            'an Eloquent filter'  => ['an Eloquent filter', '@php $b = $auction->bids()->where("user_id", 1); @endphp'],
            'proposal access'     => ['proposal access', '@php use App\Services\HireAgent\HireAgentProposalAccess; @endphp'],
            'a public offer feed' => ['a public offer feed', '@php app(PublicOfferFeedService::class); @endphp'],
            'timer logic'         => ['timer logic', '<span>{{ $meta->auction_time }}</span>'],
        ];
    }

    /**
     * Route generation inside a shared component is caught.
     *
     * Route names are product vocabulary — `hire.agent.auction.edit` and
     * `offer.listing.seller.searchListing` belong to one product each. The scanner flags `Route::`
     * directly; the `route()` helper is caught by the primitives' own source assertion, so both
     * halves are exercised here.
     */
    public function test_route_generation_inside_a_component_is_rejected(): void
    {
        $this->assertNotEmpty(
            $this->scanner->nonPresentationSymbolsIn(self::FIXTURE_PATH, '@php Route::current(); @endphp'),
            'Route facade use inside a shared component must be rejected.'
        );

        // The `route()` helper is not a scanner symbol, so the primitives test bans it by source.
        // This control proves that ban is meaningful by showing the string is detectable.
        $helper = '<a href="{{ route(\'offer.listing.seller.searchListing\') }}">Back</a>';
        $this->assertStringContainsString('route(', Scanner::stripComments($helper));
    }

    /** Control: an ordinary presentation component trips none of the guards. */
    public function test_a_well_formed_component_is_accepted(): void
    {
        $source = <<<'BLADE'
        @props(['title', 'variant' => 'neutral'])
        <div class="viho-card viho-card-{{ $variant }}">
            <h3 class="viho-section-header-title">{{ $title }}</h3>
            <div class="viho-card-body">{{ $slot }}</div>
        </div>
        BLADE;

        $this->assertSame([], $this->scanner->violationsIn(self::FIXTURE_PATH, $source));
        $this->assertSame([], $this->scanner->nonPresentationSymbolsIn(self::FIXTURE_PATH, $source));
    }

    // ── Accessibility control ────────────────────────────────────────────────

    /**
     * The icon-only guard is a real failure, not a rendered warning.
     *
     * Asserted here as well as in the primitives test because this is the one accessibility rule
     * enforced by throwing rather than by markup, and a future refactor could soften it to a
     * silent fallback without any other test noticing.
     */
    public function test_icon_only_without_a_label_throws_rather_than_rendering(): void
    {
        $threw = false;

        try {
            Blade::render('<x-viho.button icon-only icon="fa-solid fa-share" />');
        } catch (\Throwable $e) {
            $threw = true;
            $this->assertStringContainsString('icon-only', $e->getMessage());
        }

        $this->assertTrue($threw, 'An icon-only button with no accessible name must fail loudly.');

        // Control: the same call with a label renders normally.
        $this->assertStringContainsString(
            'aria-label="Share"',
            Blade::render('<x-viho.button icon-only icon="fa-solid fa-share" label="Share" />')
        );
    }
}
