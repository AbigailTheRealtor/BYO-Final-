<?php

namespace Tests\Feature\Viho;

use Illuminate\Support\Facades\Blade;
use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * Milestone 4 — the neutral VIHO hero primitive.
 *
 * x-viho.hero is the first component released from the deferred composed-component list, and the
 * release was granted on the strength of a frozen contract rather than on the component looking
 * finished. This file is where that contract is held to.
 *
 * THE CONTRACT IS CLOSED. eyebrow, title, subtitle, identifier, status, figure, facts, actions —
 * and nothing else. Every value arrives pre-resolved and pre-formatted. The component resolves no
 * role, reads no authentication, generates no route, formats no money and parses no date. Those
 * bans are enforced by source scanning below rather than by convention, because a convention is
 * exactly what erodes when a later milestone is in a hurry.
 *
 * @see \Tests\Feature\Viho\VihoDesignTokenFoundationTest for the deferral release
 * @see \Tests\Feature\Viho\VihoPresentationPrimitivesTest for the shared-consumer boundary
 */
class VihoHeroPrimitiveTest extends TestCase
{
    private const PATH = 'resources/views/components/viho/hero.blade.php';

    private Scanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    private function render(string $template): string
    {
        return Blade::render($template);
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    public function test_it_renders_from_a_title_alone(): void
    {
        $html = $this->render('<x-viho.hero title="A Listing" />');

        $this->assertStringContainsString('viho-hero', $html);
        $this->assertStringContainsString('data-viho-hero', $html);
        $this->assertStringContainsString('A Listing', $html);
    }

    /**
     * Exactly one h1, and it is the title.
     *
     * The hero is the page's heading. A second h1 anywhere in this component would put two on
     * every page that renders it, which is the defect the Hire Agent pages carried before M4.
     */
    public function test_it_emits_exactly_one_heading(): void
    {
        $html = $this->render(<<<'BLADE'
        <x-viho.hero
            eyebrow="Section · Role"
            title="A Listing"
            subtitle="A subtitle"
            identifier="Listing ID: ABC-123"
            :status="['label' => 'Active', 'tone' => 'success', 'icon' => 'fa-solid fa-circle-check']"
            :figure="['label' => 'Monthly Rent', 'value' => '$2,450']"
            :facts="[['label' => 'Type', 'value' => 'Condo']]"
        />
        BLADE);

        $this->assertSame(1, substr_count($html, '<h1'), 'The hero must emit exactly one h1.');
        $this->assertStringContainsString('<h1 class="viho-hero-title">A Listing</h1>', $html);
    }

    /** The eyebrow is not a heading — it must not compete with the title for structure. */
    public function test_the_eyebrow_is_not_a_heading(): void
    {
        $html = $this->render('<x-viho.hero eyebrow="Hire Agent · Landlord" title="T" />');

        $this->assertStringContainsString('viho-hero-eyebrow', $html);
        $this->assertStringContainsString('Hire Agent · Landlord', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        foreach (['<h2', '<h3', '<h4', '<h5', '<h6'] as $tag) {
            $this->assertStringNotContainsString($tag, $html, "The eyebrow must not render as {$tag}.");
        }
    }

    public function test_optional_regions_are_omitted_rather_than_emptied(): void
    {
        $html = $this->render('<x-viho.hero title="T" />');

        foreach ([
            'viho-hero-eyebrow',
            'viho-hero-subtitle',
            'viho-hero-identifier',
            'viho-hero-figure',
            'viho-hero-facts',
            'viho-hero-actions',
            'viho-badge',
        ] as $absent) {
            $this->assertStringNotContainsString(
                $absent,
                $html,
                "With no value supplied, {$absent} must not render as an empty shell."
            );
        }
    }

    /**
     * The figure is echoed exactly, never reformatted.
     *
     * A component that reformatted its figure would impose a locale on every caller. The awkward
     * value here is deliberate: a bare number would pass even if the component ran number_format.
     */
    public function test_the_figure_is_echoed_verbatim(): void
    {
        $html = $this->render(
            '<x-viho.hero title="T" :figure="[\'label\' => \'Monthly Rent\', \'value\' => \'$12,500,000.50\']" />'
        );

        $this->assertStringContainsString('$12,500,000.50', $html);
        $this->assertStringContainsString('Monthly Rent', $html);
    }

    /** Status colour is the caller's decision, passed through as a badge variant. */
    public function test_status_tone_is_supplied_not_inferred(): void
    {
        $expired = $this->render(
            '<x-viho.hero title="T" :status="[\'label\' => \'Expired\', \'tone\' => \'neutral\', \'icon\' => null]" />'
        );
        $active = $this->render(
            '<x-viho.hero title="T" :status="[\'label\' => \'Active\', \'tone\' => \'success\', \'icon\' => null]" />'
        );

        $this->assertStringContainsString('viho-badge-neutral', $expired);
        $this->assertStringContainsString('Expired', $expired);
        $this->assertStringContainsString('viho-badge-success', $active);

        // The proof it is not inferred: the same label drawn in a different tone on request.
        $odd = $this->render(
            '<x-viho.hero title="T" :status="[\'label\' => \'Expired\', \'tone\' => \'success\', \'icon\' => null]" />'
        );
        $this->assertStringContainsString('viho-badge-success', $odd);
    }

    public function test_malformed_fact_rows_are_dropped_rather_than_rendered_blank(): void
    {
        $html = $this->render(<<<'BLADE'
        <x-viho.hero title="T" :facts="[
            ['label' => 'Good', 'value' => 'Kept'],
            ['label' => '', 'value' => 'No label'],
            ['label' => 'No value', 'value' => ''],
            'not-an-array',
        ]" />
        BLADE);

        $this->assertStringContainsString('Kept', $html);
        $this->assertStringNotContainsString('No label', $html);
        $this->assertStringNotContainsString('No value', $html);
        $this->assertSame(1, substr_count($html, 'viho-hero-fact-label'));
    }

    public function test_the_actions_slot_renders_caller_markup_untouched(): void
    {
        $html = $this->render(<<<'BLADE'
        <x-viho.hero title="T">
            <x-slot name="actions"><a href="/somewhere" class="btn">Edit Listing</a></x-slot>
        </x-viho.hero>
        BLADE);

        $this->assertStringContainsString('viho-hero-actions', $html);
        $this->assertStringContainsString('<a href="/somewhere" class="btn">Edit Listing</a>', $html);
    }

    /** Values are escaped: the hero renders text, never caller-supplied markup. */
    public function test_values_are_escaped(): void
    {
        $html = $this->render('<x-viho.hero title="<script>alert(1)</script>" />');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── Contract enforcement ─────────────────────────────────────────────────

    /**
     * The frozen prop list, asserted against the source.
     *
     * A rendering test cannot catch a ninth prop being added — it would simply go unused by the
     * existing assertions. Reading @props is what pins the contract closed.
     */
    public function test_the_prop_contract_is_frozen(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::PATH));

        preg_match('/@props\(\[(.*?)\]\)/s', $src, $m);
        $this->assertNotEmpty($m, 'The hero must declare an explicit @props list.');

        preg_match_all("/'([a-zA-Z]+)'\s*=>/", $m[1], $found);
        sort($found[1]);

        $this->assertSame(
            ['eyebrow', 'facts', 'figure', 'identifier', 'status', 'subtitle', 'title'],
            $found[1],
            'The hero prop contract is frozen. `actions` is a slot, not a prop; anything else here '
            . 'is a contract change requiring architectural review, not an incremental addition.'
        );
    }

    /**
     * No role, auth, route, config, model, query or formatting logic.
     *
     * This is the specific list the M4 deferral release was granted against. It is asserted here
     * as well as in the shared primitives sweep because this component is the reason the deferral
     * was lifted, and a failure should name it directly.
     */
    public function test_the_primitive_contains_no_business_logic(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::PATH));

        foreach ([
            '$role', 'auth(', 'Auth::', 'Gate::', '@can', 'policy(',
            'route(', 'url(', 'action(', 'app(', 'resolve(', 'config(',
            'DB::', '::where(', '->get()', 'Eloquent',
            'number_format', 'money_format', 'Carbon', 'strtotime', 'date(',
            '<script', 'document.', 'window.',
        ] as $banned) {
            $this->assertStringNotContainsString(
                $banned,
                $src,
                "x-viho.hero must not contain {$banned}. The deferral release in M4 was granted on "
                . 'the condition that this component resolves and formats nothing.'
            );
        }

        $this->assertSame([], $this->scanner->violationsIn(self::PATH, $this->scanner->read(self::PATH)));
        $this->assertSame([], $this->scanner->nonPresentationSymbolsIn(self::PATH, $this->scanner->read(self::PATH)));
    }

    /** It carries no media affordance — a listing without photographs must not show a frame. */
    public function test_it_renders_no_media_affordance(): void
    {
        $src  = Scanner::stripComments($this->scanner->read(self::PATH));
        $html = $this->render('<x-viho.hero title="T" />');

        foreach (['<img', 'background-image', 'carousel', 'placeholder-photo'] as $banned) {
            $this->assertStringNotContainsString($banned, $src);
        }

        $this->assertStringNotContainsString('<img', $html);
    }

    /** It carries no timer, countdown or competitive vocabulary. */
    public function test_it_carries_no_timer_or_competitive_vocabulary(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::PATH));

        foreach ([
            'countdown', 'Remaining', 'auction_time', 'Bidding Period', 'expires',
            'rank', 'highest', 'competing', 'proposal_count',
        ] as $banned) {
            $this->assertStringNotContainsString(
                $banned,
                $src,
                "x-viho.hero must not introduce {$banned}."
            );
        }
    }
}
