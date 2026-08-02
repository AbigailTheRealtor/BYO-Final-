<?php

namespace Tests\Feature\Viho;

use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * Controls for the dependency scanner itself.
 *
 * WHY THESE COME FIRST. PresentationDependencyContractTest asserts that the real tree contains no
 * forbidden edges, and it currently passes. A scanner that detected nothing at all would produce
 * exactly the same green result, and would keep producing it through every milestone of the
 * migration — which is the failure mode the previous production-scope guard actually suffered:
 * it passed because it looked in the wrong place, and was credited as evidence.
 *
 * So the contract test is only worth its green tick if this file proves the scanner can go red.
 * Every fixture below is source text held in memory. Nothing is written to disk and no
 * intentionally-invalid dependency is ever planted in a production file — a violation that exists
 * on disk long enough for another process to observe it is not a test, it is a defect with a
 * timer on it.
 *
 * @see \Tests\Support\PresentationDependencyScanner
 */
class PresentationDependencyScannerTest extends TestCase
{
    private Scanner $scanner;

    /** A representative file in each zone, used as the "from" side of an edge. */
    private const HIRE_AGENT_FILE   = 'resources/views/hire_seller_agent/view.blade.php';
    private const CREATE_OFFER_FILE = 'resources/views/offer-listing/seller/view.blade.php';
    private const VIHO_FILE         = 'resources/views/components/viho/hero.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    // ── Zone resolution ──────────────────────────────────────────────────────

    /**
     * @dataProvider zoneCases
     */
    public function test_resolves_the_owning_zone_of_a_path(string $path, string $expected): void
    {
        $this->assertSame($expected, $this->scanner->zoneForPath($path), $path);
    }

    public static function zoneCases(): array
    {
        return [
            'hire seller view'   => ['resources/views/hire_seller_agent/view.blade.php', Scanner::ZONE_HIRE_AGENT],
            'hire framework css' => ['resources/views/hire_agent/framework/styles.blade.php', Scanner::ZONE_HIRE_AGENT],
            'hire component'     => ['resources/views/components/hire-agent/hero.blade.php', Scanner::ZONE_HIRE_AGENT],
            'hire presenter'     => ['app/Support/HireAgent/HireAgentHeroData.php', Scanner::ZONE_HIRE_AGENT],
            'create offer view'  => ['resources/views/offer-listing/seller/view.blade.php', Scanner::ZONE_CREATE_OFFER],
            'create offer part'  => ['resources/views/offer-listing/partials/_competing-bids.blade.php', Scanner::ZONE_CREATE_OFFER],
            'viho component'     => ['resources/views/components/viho/hero.blade.php', Scanner::ZONE_VIHO],
            'viho partial'       => ['resources/views/viho/tokens.blade.php', Scanner::ZONE_VIHO],
            'viho support'       => ['app/Support/Viho/VihoHeroData.php', Scanner::ZONE_VIHO],
            'layout'             => ['resources/views/layouts/main.blade.php', Scanner::ZONE_NEUTRAL],
            'shared partial'     => ['resources/views/partials/listing-photos-tours-documents.blade.php', Scanner::ZONE_NEUTRAL],
            'orphaned listing'   => ['resources/views/components/listing/section.blade.php', Scanner::ZONE_NEUTRAL],
        ];
    }

    /**
     * The decisive discrimination, stated as its own test.
     *
     * `components/hire-agent-modal.blade.php` is SHARED and is already used by all four Create
     * Offer views. `components/hire-agent/hero.blade.php` is Hire Agent private. Every naive
     * substring check on "hire-agent" conflates them and reports four cross-product violations
     * that do not exist. The trailing slash in the zone prefix is what separates them.
     */
    public function test_shared_hire_agent_modal_is_not_hire_agent_private(): void
    {
        $this->assertSame(
            Scanner::ZONE_NEUTRAL,
            $this->scanner->zoneForPath('resources/views/components/hire-agent-modal.blade.php'),
            'x-hire-agent-modal is a shared component. Classifying it as Hire Agent private would '
            . 'make all four Create Offer views look like cross-product violations.'
        );

        $this->assertSame(
            Scanner::ZONE_HIRE_AGENT,
            $this->scanner->zoneForPath('resources/views/components/hire-agent/hero.blade.php')
        );
    }

    /** The same discrimination, exercised through the tag parser rather than the path resolver. */
    public function test_component_tag_parsing_separates_the_shared_modal_from_private_components(): void
    {
        $deps = $this->scanner->dependenciesIn('<x-hire-agent-modal modal-id="x" /><x-hire-agent.hero role="seller" />');

        $byTarget = [];
        foreach ($deps as $dep) {
            $byTarget[$dep['target']] = $dep['zone'];
        }

        $this->assertSame(Scanner::ZONE_NEUTRAL, $byTarget['x-hire-agent-modal'] ?? null);
        $this->assertSame(Scanner::ZONE_HIRE_AGENT, $byTarget['x-hire-agent.hero'] ?? null);
    }

    // ── Required control 1: a fake Hire Agent → Create Offer dependency ──────

    /**
     * @dataProvider hireAgentToCreateOfferForms
     */
    public function test_detects_a_hire_agent_to_create_offer_dependency(string $label, string $source): void
    {
        $violations = $this->scanner->violationsIn(self::HIRE_AGENT_FILE, $source);

        $this->assertNotEmpty(
            $violations,
            "The scanner must reject a Hire Agent → Create Offer dependency expressed as {$label}."
        );
        $this->assertStringContainsString(Scanner::ZONE_CREATE_OFFER, implode("\n", $violations));
    }

    public static function hireAgentToCreateOfferForms(): array
    {
        return [
            '@include'      => ['@include', "@include('offer-listing.partials._competing-bids', ['x' => 1])"],
            '@extends'      => ['@extends', "@extends('offer-listing.seller.view')"],
            '@includeIf'    => ['@includeIf', "@includeIf('offer-listing.partials._competing-bids')"],
            '@includeWhen'  => ['@includeWhen', "@includeWhen(\$cond, 'offer-listing.partials._competing-bids')"],
            '@component'    => ['@component', "@component('offer-listing.seller.view')@endcomponent"],
            'view()'        => ['view()', "@php echo view('offer-listing.seller.view'); @endphp"],
            'View::make()'  => ['View::make()', "@php echo View::make('offer-listing.seller.view'); @endphp"],
            'css class'     => ['a Create Offer css class', '<div class="sol-hero sol-hero-summary">x</div>'],
            'css selector'  => ['a Create Offer css selector', '<style>.sol-sticky-card { top: 72px; }</style>'],
        ];
    }

    // ── Required control 2: a fake Create Offer → Hire Agent dependency ──────

    /**
     * @dataProvider createOfferToHireAgentForms
     */
    public function test_detects_a_create_offer_to_hire_agent_dependency(string $label, string $source): void
    {
        $violations = $this->scanner->violationsIn(self::CREATE_OFFER_FILE, $source);

        $this->assertNotEmpty(
            $violations,
            "The scanner must reject a Create Offer → Hire Agent dependency expressed as {$label}."
        );
        $this->assertStringContainsString(Scanner::ZONE_HIRE_AGENT, implode("\n", $violations));
    }

    public static function createOfferToHireAgentForms(): array
    {
        return [
            '@include'   => ['@include', "@include('hire_agent.framework.styles')"],
            'component'  => ['a private component tag', '<x-hire-agent.detail-shell role="seller" />'],
            'import'     => ['a PHP import', '@php use App\Support\HireAgent\HireAgentHeroData; @endphp'],
            'fqn'        => ['a fully-qualified reference', '@php \App\Support\HireAgent\HireAgentHeroData::for("seller", $a); @endphp'],
            'css class'  => ['a Hire Agent css class', '<div class="hla-hero">x</div>'],
        ];
    }

    // ── Required controls 3 & 4: permitted product → VIHO dependencies ───────

    /**
     * @dataProvider productZones
     */
    public function test_permits_a_product_to_depend_on_viho(string $from): void
    {
        $source = <<<'BLADE'
        @include('viho.tokens')
        <x-viho.hero :title="$t" />
        <x-viho.section-card title="Overview">body</x-viho.section-card>
        <div class="viho-hero viho-section-card">x</div>
        @php use App\Support\Viho\VihoHeroData; @endphp
        BLADE;

        $this->assertSame(
            [],
            $this->scanner->violationsIn($from, $source),
            'Depending on the neutral shared library is the entire point of the new contract.'
        );
    }

    public static function productZones(): array
    {
        return [
            'hire agent'   => [self::HIRE_AGENT_FILE],
            'create offer' => [self::CREATE_OFFER_FILE],
        ];
    }

    // ── Required control 5: a prohibited VIHO → product dependency ───────────

    /**
     * @dataProvider vihoOutboundForms
     */
    public function test_detects_a_viho_to_product_dependency(string $label, string $source): void
    {
        $this->assertNotEmpty(
            $this->scanner->violationsIn(self::VIHO_FILE, $source),
            "The shared library must not reach back into a product via {$label}."
        );
    }

    public static function vihoOutboundForms(): array
    {
        return [
            'a Hire Agent include'    => ['a Hire Agent include', "@include('hire_agent.framework.styles')"],
            'a Hire Agent component'  => ['a Hire Agent component', '<x-hire-agent.hero role="seller" />'],
            'a Hire Agent presenter'  => ['a Hire Agent presenter', '@php use App\Support\HireAgent\HireAgentHeroData; @endphp'],
            'a Create Offer include'  => ['a Create Offer include', "@include('offer-listing.partials._competing-bids')"],
            'a Hire Agent css class'  => ['a Hire Agent css class', '<div class="hla-hero">x</div>'],
            'a Create Offer css class' => ['a Create Offer css class', '<div class="sol-hero">x</div>'],
        ];
    }

    // ── Negative controls: things that must NOT be flagged ───────────────────

    /**
     * Documentation prose is not a dependency.
     *
     * This is not hypothetical. `hire_agent/framework/styles.blade.php` explains at length why it
     * "cannot reach Create Offer, which owns its own separate .sol- namespace", and
     * `detail-shell.blade.php` documents the same boundary. A contract that scanned comments would
     * fail on the very text that describes it.
     */
    public function test_does_not_flag_dependencies_named_only_in_comments(): void
    {
        $source = <<<'BLADE'
        {{-- This component must never @include('offer-listing.partials._competing-bids'),
             must not use .sol-hero, and shares nothing with hire_agent.framework.styles. --}}
        <!-- Historically this sat beside <x-hire-agent.detail-shell>. -->
        @php
            /* Create Offer owns .sol-, .bol-, .lol- and .tcl-. */
            // Never: @include('offer-listing.seller.view')
        @endphp
        <div class="viho-hero">ok</div>
        BLADE;

        $this->assertSame([], $this->scanner->violationsIn(self::VIHO_FILE, $source));
        $this->assertSame([], $this->scanner->violationsIn(self::HIRE_AGENT_FILE, $source));
    }

    /**
     * Route names, model names and English prose are not presentation dependencies.
     *
     * `hire.agent.auction.edit` is a real route used by the Hire Agent sidebar; "offer listing"
     * appears in copy throughout both products. Neither is an edge.
     */
    public function test_does_not_flag_route_names_or_prose(): void
    {
        $source = <<<'BLADE'
        <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id]) }}">Edit</a>
        <a href="{{ route('offer.listing.seller.searchListing') }}">Back to Search</a>
        <p>Submit an offer listing, or hire an agent to represent you.</p>
        <span>{{ $auction->hire_agent_status }}</span>
        BLADE;

        $this->assertSame([], $this->scanner->violationsIn(self::CREATE_OFFER_FILE, $source));
        $this->assertSame([], $this->scanner->violationsIn(self::HIRE_AGENT_FILE, $source));
    }

    /** A zone may depend on itself and on neutral shared files without limit. */
    public function test_does_not_flag_same_zone_or_neutral_dependencies(): void
    {
        $hire = <<<'BLADE'
        @extends('layouts.main')
        @include('hire_agent.framework.styles')
        @include('partials.listing-photos-tours-documents')
        <x-hire-agent.detail-shell role="seller" :auction="$auction" />
        <x-hire-agent-modal modal-id="m" />
        <x-location-dna-map :listing="$l" />
        <div class="hla-hero hla-hero-title">x</div>
        @php use App\Support\HireAgent\HireAgentHeroData; @endphp
        BLADE;

        $this->assertSame([], $this->scanner->violationsIn(self::HIRE_AGENT_FILE, $hire));

        $offer = <<<'BLADE'
        @extends('layouts.main')
        @include('offer-listing.partials._competing-bids', ['role' => 'seller'])
        @include('showings._request-form', ['auctionId' => $auction->id])
        <x-hire-agent-modal modal-id="solHireAgentModal" />
        <div class="sol-hero sol-sticky-card">x</div>
        BLADE;

        $this->assertSame([], $this->scanner->violationsIn(self::CREATE_OFFER_FILE, $offer));
    }

    /** A neutral file is unconstrained — layouts and shared partials serve both products. */
    public function test_neutral_files_are_not_constrained(): void
    {
        $source = "@include('hire_agent.framework.styles')\n@include('offer-listing.partials._competing-bids')";

        $this->assertSame([], $this->scanner->violationsIn('resources/views/layouts/main.blade.php', $source));
    }

    /**
     * The vendored "Viho" admin theme does not become a VIHO-namespace reference.
     *
     * `public/assets/admin/` ships `.viho-demo-content` and `.viho-demo-section`. That theme is
     * loaded only by `layouts/admin.blade.php`, which neither product extends, but the shared
     * library is about to claim the `viho-` prefix in earnest and the two must stay distinguishable.
     */
    public function test_vendored_admin_theme_classes_are_not_viho_namespace(): void
    {
        $this->assertNull($this->scanner->zoneForClassToken('viho-demo-content'));
        $this->assertNull($this->scanner->zoneForClassToken('viho-demo-section'));
        $this->assertNull($this->scanner->zoneForClassToken('vihoAdminConfig'));

        $this->assertSame(Scanner::ZONE_VIHO, $this->scanner->zoneForClassToken('viho-hero'));
    }

    /** Unowned class tokens belong to nobody and constrain nothing. */
    public function test_unowned_class_tokens_have_no_zone(): void
    {
        foreach (['container', 'card', 'row', 'btn-primary', 'section-card', 'removeBold'] as $token) {
            $this->assertNull($this->scanner->zoneForClassToken($token), $token);
        }
    }

    // ── VIHO presentation-only controls ──────────────────────────────────────

    /**
     * @dataProvider forbiddenVihoSymbols
     */
    public function test_detects_non_presentation_logic_inside_viho(string $source): void
    {
        $this->assertNotEmpty(
            $this->scanner->nonPresentationSymbolsIn(self::VIHO_FILE, $source),
            'The shared library must stay presentation-only.'
        );
    }

    public static function forbiddenVihoSymbols(): array
    {
        return [
            'db'        => ['@php $rows = DB::table("bids")->get(); @endphp'],
            'query'     => ['@php $b = $auction->bids()->where("user_id", 1)->get(); @endphp'],
            'auth'      => ['@if (auth()->id() === $auction->user_id) owner @endif'],
            'gate'      => ['@can("view", $auction) x @endcan'],
            'access'    => ['@php use App\Services\HireAgent\HireAgentProposalAccess; @endphp'],
            'feed'      => ['@php app(PublicOfferFeedService::class); @endphp'],
            'timer'     => ['<span>{{ $meta->auction_time }} remaining</span>'],
            'countdown' => ['<div class="countdown" data-ends="{{ $x }}"></div>'],
        ];
    }

    /** Rendering a value the caller handed in is exactly what a presentation component is for. */
    public function test_permits_ordinary_presentation_markup_inside_viho(): void
    {
        $source = <<<'BLADE'
        @props(['title', 'facts' => [], 'figure' => null])
        <div class="viho-hero">
            <h1 class="viho-hero-title">{{ $title }}</h1>
            @foreach ($facts as $fact)
                <span class="viho-hero-fact">{{ $fact['label'] }}: {{ $fact['value'] }}</span>
            @endforeach
            @isset($figure)
                <p class="viho-hero-figure">{{ $figure['value'] }}</p>
            @endisset
        </div>
        BLADE;

        $this->assertSame([], $this->scanner->nonPresentationSymbolsIn(self::VIHO_FILE, $source));
        $this->assertSame([], $this->scanner->violationsIn(self::VIHO_FILE, $source));
    }

    /** The presentation-only rule applies to VIHO alone; products keep their own logic. */
    public function test_presentation_only_rule_does_not_apply_outside_viho(): void
    {
        $source = '@if (auth()->id() === $auction->user_id) <a href="#">Edit</a> @endif';

        $this->assertSame([], $this->scanner->nonPresentationSymbolsIn(self::HIRE_AGENT_FILE, $source));
        $this->assertSame([], $this->scanner->nonPresentationSymbolsIn(self::CREATE_OFFER_FILE, $source));
    }
}
