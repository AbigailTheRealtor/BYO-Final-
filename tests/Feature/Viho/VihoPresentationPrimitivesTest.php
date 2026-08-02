<?php

namespace Tests\Feature\Viho;

use Illuminate\Support\Facades\Blade;
use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * Milestone 2 — the neutral VIHO presentation primitives.
 *
 * Eight components, none of them consumed by any page. As with M1, "is it still unused?" is a
 * load-bearing assertion rather than an afterthought: the entire safety argument for M2 is that
 * nothing renders these yet, and that claim expires the moment M3 legitimately wires them up.
 *
 * WHY THESE RENDER FOR REAL. Every behavioural assertion below compiles the component through
 * Blade and inspects the output. Asserting on the Blade SOURCE would prove only that the file
 * contains the characters someone typed — it cannot tell whether a disabled anchor actually loses
 * its href, whether `@disabled` emits the attribute, or whether an icon-only button without a
 * label really fails. Those are the properties worth having.
 *
 * @see \Tests\Support\PresentationDependencyScanner
 * @see \Tests\Feature\Viho\VihoDesignTokenFoundationTest for the token layer and scope contract
 */
class VihoPresentationPrimitivesTest extends TestCase
{
    /** The eight approved M2 primitives. */
    private const PRIMITIVES = [
        'card', 'section-header', 'kv', 'badge', 'button', 'action-tile', 'stat', 'empty-state',
    ];

    /** Root class each primitive must emit, so a page can always find it. */
    private const ROOT_CLASSES = [
        'card'           => 'viho-card',
        'section-header' => 'viho-section-header',
        'kv'             => 'viho-kv',
        'badge'          => 'viho-badge',
        'button'         => 'viho-btn',
        'action-tile'    => 'viho-action-tile',
        'stat'           => 'viho-stat',
        'empty-state'    => 'viho-empty-state',
    ];

    /** Minimal props that let each primitive render on its own. */
    private const MINIMAL = [
        'card'           => '<x-viho.card>body</x-viho.card>',
        'section-header' => '<x-viho.section-header title="T" />',
        'kv'             => '<x-viho.kv label="L" value="V" />',
        'badge'          => '<x-viho.badge>B</x-viho.badge>',
        'button'         => '<x-viho.button>Go</x-viho.button>',
        'action-tile'    => '<x-viho.action-tile label="A" />',
        'stat'           => '<x-viho.stat label="L" value="V" />',
        'empty-state'    => '<x-viho.empty-state title="Nothing here" />',
    ];

    private Scanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    // ── 1/2/3. Resolution, root class, default render ────────────────────────

    /**
     * @dataProvider primitives
     */
    public function test_every_primitive_resolves_and_renders(string $name): void
    {
        $this->assertTrue(
            $this->scanner->exists("resources/views/components/viho/{$name}.blade.php"),
            "x-viho.{$name} must exist in the neutral namespace."
        );

        $html = $this->render(self::MINIMAL[$name]);

        $this->assertNotSame('', trim($html), "x-viho.{$name} must render something by default.");
        $this->assertStringContainsString(
            self::ROOT_CLASSES[$name],
            $html,
            "x-viho.{$name} must emit its neutral root class."
        );
    }

    public static function primitives(): array
    {
        $out = [];
        foreach (self::PRIMITIVES as $name) {
            $out[$name] = [$name];
        }

        return $out;
    }

    /** Every primitive lives in the VIHO zone as far as the dependency scanner is concerned. */
    public function test_every_primitive_is_in_the_neutral_zone(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $this->assertSame(
                Scanner::ZONE_VIHO,
                $this->scanner->zoneForPath("resources/views/components/viho/{$name}.blade.php"),
                "x-viho.{$name} must be owned by the neutral zone."
            );
        }
    }

    // ── 4/5. Props, slots, optional regions ──────────────────────────────────

    public function test_card_renders_title_subtitle_actions_and_footer(): void
    {
        $html = $this->render(<<<'BLADE'
        <x-viho.card title="Overview" subtitle="Sub" icon="fa-solid fa-list">
            <x-slot name="actions"><span id="act">A</span></x-slot>
            <x-slot name="footer"><span id="foot">F</span></x-slot>
            <p id="body">Body</p>
        </x-viho.card>
        BLADE);

        foreach (['Overview', 'Sub', 'id="act"', 'id="foot"', 'id="body"', 'viho-card-head', 'viho-card-foot'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        $this->assertStringContainsString('aria-hidden="true"', $html, 'The card icon is decorative.');
    }

    /** A body-only card emits no header or footer chrome at all. */
    public function test_card_omits_optional_regions_when_not_supplied(): void
    {
        $html = $this->render('<x-viho.card>only body</x-viho.card>');

        $this->assertStringNotContainsString('viho-card-head', $html);
        $this->assertStringNotContainsString('viho-card-foot', $html);
        $this->assertStringContainsString('viho-card-body', $html);
    }

    public function test_card_and_section_header_respect_the_caller_heading_level(): void
    {
        $this->assertStringContainsString('<h2', $this->render('<x-viho.card title="T" title-tag="h2">b</x-viho.card>'));
        $this->assertStringContainsString('<h5', $this->render('<x-viho.section-header title="T" tag="h5" />'));

        // Create Offer's card headers carry no heading semantics today; a primitive that forced
        // one would change the document outline of every page that adopted it.
        $div = $this->render('<x-viho.section-header title="T" tag="div" />');
        $this->assertStringContainsString('<div class="viho-section-header-title"', $div);
        $this->assertStringNotContainsString('<h3', $div);
    }

    /** An unknown tag falls back rather than emitting `<script>` or arbitrary markup. */
    public function test_heading_tag_is_restricted_to_a_known_set(): void
    {
        $html = $this->render('<x-viho.section-header title="T" tag="script" />');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('<h3', $html);
    }

    public function test_kv_renders_both_layouts_and_emphasis_variants(): void
    {
        $this->assertStringContainsString('viho-kv-split', $this->render('<x-viho.kv label="L" value="V" />'));
        $this->assertStringContainsString('viho-kv-inline', $this->render('<x-viho.kv label="L" value="V" layout="inline" />'));
        $this->assertStringContainsString('viho-kv-emphasized', $this->render('<x-viho.kv label="L" value="V" emphasis="emphasized" />'));
        $this->assertStringContainsString('viho-kv-muted', $this->render('<x-viho.kv label="L" value="V" emphasis="muted" />'));
    }

    /**
     * With no value and no explicit empty text, the row disappears entirely.
     *
     * Both products already drop absent rows rather than printing a dash or a blank label, so a
     * primitive that rendered an empty shell would add rows to pages that do not have them.
     */
    public function test_kv_renders_nothing_when_the_value_is_absent(): void
    {
        $this->assertSame('', trim($this->render('<x-viho.kv label="L" />')));
        $this->assertSame('', trim($this->render('<x-viho.kv label="L" :value="null" />')));

        $explicit = $this->render('<x-viho.kv label="L" empty="Not provided" />');
        $this->assertStringContainsString('Not provided', $explicit);
        $this->assertStringContainsString('viho-kv-empty', $explicit);
    }

    /** The value is displayed, never computed or reformatted. */
    public function test_kv_does_not_transform_the_value(): void
    {
        $html = $this->render('<x-viho.kv label="Price" value="1234.5" />');

        $this->assertStringContainsString('1234.5', $html);
        $this->assertStringNotContainsString('1,234', $html);
        $this->assertStringNotContainsString('$', $html);
    }

    public function test_stat_renders_label_value_and_support(): void
    {
        $html = $this->render('<x-viho.stat label="Views" value="42" support="last 7 days" accent />');

        $this->assertStringContainsString('Views', $html);
        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('last 7 days', $html);
        $this->assertStringContainsString('viho-stat-accent', $html);
    }

    public function test_empty_state_renders_title_description_and_action(): void
    {
        $html = $this->render(<<<'BLADE'
        <x-viho.empty-state icon="fa-solid fa-inbox" title="No records" description="Nothing yet.">
            <x-slot name="action"><span id="cta">Add</span></x-slot>
        </x-viho.empty-state>
        BLADE);

        foreach (['No records', 'Nothing yet.', 'id="cta"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_action_tile_renders_anchor_or_inert_container(): void
    {
        $anchor = $this->render('<x-viho.action-tile href="/x" icon="fa-solid fa-share" label="Share" description="Send it" />');
        $this->assertStringContainsString('<a ', $anchor);
        $this->assertStringContainsString('href="/x"', $anchor);
        $this->assertStringContainsString('Send it', $anchor);

        // No href and no action slot: an inert container, not a fake control.
        $inert = $this->render('<x-viho.action-tile label="Share" />');
        $this->assertStringNotContainsString('<a ', $inert);
        $this->assertStringContainsString('<div ', $inert);
    }

    // ── 6. Variants ──────────────────────────────────────────────────────────

    /**
     * @dataProvider badgeVariants
     */
    public function test_badge_variants_render_expected_classes(string $variant): void
    {
        $html = $this->render('<x-viho.badge variant="' . $variant . '">X</x-viho.badge>');

        $this->assertStringContainsString("viho-badge-{$variant}", $html);
    }

    public static function badgeVariants(): array
    {
        return array_map(fn ($v) => [$v], array_combine(
            ['neutral', 'primary', 'success', 'warning', 'danger', 'info', 'accent'],
            ['neutral', 'primary', 'success', 'warning', 'danger', 'info', 'accent']
        ));
    }

    /**
     * @dataProvider buttonVariants
     */
    public function test_button_variants_render_expected_classes(string $variant): void
    {
        $html = $this->render('<x-viho.button variant="' . $variant . '">X</x-viho.button>');

        $this->assertStringContainsString("viho-btn-{$variant}", $html);
    }

    public static function buttonVariants(): array
    {
        return array_map(fn ($v) => [$v], array_combine(
            ['primary', 'secondary', 'outline', 'subtle', 'success', 'danger'],
            ['primary', 'secondary', 'outline', 'subtle', 'success', 'danger']
        ));
    }

    /** An unrecognised variant falls back rather than emitting an unstyled class. */
    public function test_unknown_variants_fall_back_safely(): void
    {
        $this->assertStringContainsString('viho-badge-neutral', $this->render('<x-viho.badge variant="wat">X</x-viho.badge>'));
        $this->assertStringContainsString('viho-btn-primary', $this->render('<x-viho.button variant="wat">X</x-viho.button>'));
    }

    /**
     * The two M1 disagreements the badge carries stay parameterised.
     *
     * Truncation is opt-in because Seller/Buyer clamp and Landlord/Tenant do not — a two-two split
     * with no majority. The accent variant paints from caller-supplied custom properties and
     * hard-codes no role colour, which is what keeps Landlord's teal status pill a per-role choice
     * rather than something this component decided.
     */
    public function test_badge_preserves_the_deferred_disagreements(): void
    {
        $this->assertStringNotContainsString('viho-badge-truncate', $this->render('<x-viho.badge>X</x-viho.badge>'));
        $this->assertStringContainsString('viho-badge-truncate', $this->render('<x-viho.badge truncate>X</x-viho.badge>'));

        $src = $this->scanner->read('resources/views/components/viho/badge.blade.php');
        foreach (['#0f766e', '#0F766E', '#dcfce7', '#166534'] as $roleColour) {
            $this->assertStringNotContainsString($roleColour, $src, 'The badge must hard-code no role colour.');
        }
    }

    // ── 16/17/18 + accessibility ─────────────────────────────────────────────

    /**
     * An icon-only button with no accessible name is rejected outright.
     *
     * Blade wraps an exception thrown inside a component in its own ViewException, so the assertion
     * is on the message and on the original type reachable through getPrevious() rather than on the
     * outer class. Expecting InvalidArgumentException directly passes only by accident of the
     * handler configuration.
     */
    public function test_icon_only_button_without_a_label_is_rejected(): void
    {
        try {
            $this->render('<x-viho.button icon-only icon="fa-solid fa-share" />');
            $this->fail('An icon-only button with no accessible name must not render.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('icon-only buttons must supply', $e->getMessage());

            $root = $e;
            while ($root->getPrevious() !== null) {
                $root = $root->getPrevious();
            }
            $this->assertInstanceOf(\InvalidArgumentException::class, $root);
        }
    }

    public function test_icon_only_button_with_a_label_is_accepted(): void
    {
        $html = $this->render('<x-viho.button icon-only icon="fa-solid fa-share" label="Share listing" />');

        $this->assertStringContainsString('aria-label="Share listing"', $html);
        $this->assertStringContainsString('viho-btn-icon', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html, 'The icon itself is decorative.');
    }

    /** An explicit aria-label is honoured instead of the label prop. */
    public function test_icon_only_button_accepts_an_explicit_aria_label(): void
    {
        $html = $this->render('<x-viho.button icon-only icon="fa-solid fa-share" aria-label="Share" />');

        $this->assertStringContainsString('aria-label="Share"', $html);
    }

    /** Anchors keep their href and render as anchors. */
    public function test_anchor_semantics_are_preserved(): void
    {
        $html = $this->render('<x-viho.button href="/listings/1">View</x-viho.button>');

        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('href="/listings/1"', $html);
        $this->assertStringNotContainsString('<button', $html);
    }

    /**
     * A disabled anchor loses its href, which is what actually removes it from the tab order.
     *
     * `pointer-events:none` stops the mouse and nothing else: the link would still be focusable
     * and would still activate on Enter. This is the assertion that would catch a future
     * "simplification" back to a CSS-only disable.
     */
    public function test_disabled_anchor_is_genuinely_non_interactive(): void
    {
        $html = $this->render('<x-viho.button href="/x" disabled>View</x-viho.button>');

        $this->assertStringNotContainsString('href=', $html, 'A disabled anchor must not keep its href.');
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('viho-btn-disabled', $html);
    }

    public function test_disabled_button_emits_the_disabled_attribute(): void
    {
        $html = $this->render('<x-viho.button disabled>Go</x-viho.button>');

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    /** A loading control is announced as busy and is not activatable. */
    public function test_loading_button_is_marked_busy_and_blocked(): void
    {
        $html = $this->render('<x-viho.button loading>Go</x-viho.button>');

        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringContainsString('viho-btn-loading', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    /**
     * A bare `<button>` inside a form defaults to type=submit. Defaulting to "button" here stops a
     * share or print control silently submitting the page it was dropped into.
     */
    public function test_button_type_defaults_to_button_and_is_overridable(): void
    {
        $this->assertStringContainsString('type="button"', $this->render('<x-viho.button>Go</x-viho.button>'));
        $this->assertStringContainsString('type="submit"', $this->render('<x-viho.button type="submit">Go</x-viho.button>'));

        // An unrecognised type falls back rather than emitting an invalid attribute.
        $this->assertStringContainsString('type="button"', $this->render('<x-viho.button type="wat">Go</x-viho.button>'));
    }

    /** No primitive renders a clickable generic div. */
    public function test_no_primitive_emits_a_click_handler_on_a_generic_element(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $src = Scanner::stripComments($this->scanner->read("resources/views/components/viho/{$name}.blade.php"));

            foreach (['onclick', 'onmousedown', 'onkeydown', 'role="button"', 'tabindex='] as $needle) {
                $this->assertStringNotContainsString($needle, $src, "x-viho.{$name} must not fake interactivity.");
            }
        }
    }

    /** No component hard-codes an id, which would duplicate the moment it rendered twice. */
    public function test_no_primitive_hard_codes_an_id(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $src = Scanner::stripComments($this->scanner->read("resources/views/components/viho/{$name}.blade.php"));

            $this->assertDoesNotMatchRegularExpression(
                '/\sid="[a-zA-Z][^"{}]*"/',
                $src,
                "x-viho.{$name} must not hard-code an id — two instances on one page would collide."
            );
        }
    }

    /** Every icon a primitive renders itself is decorative and hidden from assistive technology. */
    public function test_component_rendered_icons_are_decorative(): void
    {
        foreach ([
            '<x-viho.card title="T" icon="fa-solid fa-x">b</x-viho.card>',
            '<x-viho.section-header title="T" icon="fa-solid fa-x" />',
            '<x-viho.kv label="L" value="V" icon="fa-solid fa-x" />',
            '<x-viho.badge icon="fa-solid fa-x">B</x-viho.badge>',
            '<x-viho.stat label="L" value="V" icon="fa-solid fa-x" />',
            '<x-viho.empty-state title="T" icon="fa-solid fa-x" />',
            '<x-viho.action-tile label="A" icon="fa-solid fa-x" />',
        ] as $template) {
            $html = $this->render($template);

            $this->assertMatchesRegularExpression(
                '/<i[^>]*aria-hidden="true"/',
                $html,
                "Decorative icon not hidden in: {$template}"
            );
        }
    }

    /**
     * A visible focus treatment exists and is not suppressed.
     *
     * Comments are stripped before the negative half: the stylesheet's own note that the focus
     * ring is "deliberately not `outline:none`" is documentation of the rule, not a breach of it.
     */
    public function test_focus_is_visible_and_not_suppressed(): void
    {
        $css = Scanner::stripComments($this->scanner->read('resources/views/viho/styles.blade.php'));

        $this->assertStringContainsString('.viho-btn:focus-visible', $css);
        $this->assertStringContainsString('.viho-action-tile:focus-visible', $css);
        $this->assertStringNotContainsString('outline: none', $css);
        $this->assertStringNotContainsString('outline:none', $css);
    }

    // ── 7. Token consumption ─────────────────────────────────────────────────

    /**
     * The primitives are styled from tokens, not from duplicated literals.
     *
     * Sampled rather than exhaustive: a handful of raw values remain deliberate — the badge's
     * 0.73rem and 0.55rem, the tile's 0.85rem padding — because no token in the M1 scale carries
     * them and inventing one to avoid a literal would have been a redesign, which M1 was explicit
     * about not doing.
     */
    public function test_primitive_styles_consume_the_shared_tokens(): void
    {
        $css = $this->scanner->read('resources/views/viho/styles.blade.php');

        foreach ([
            '.viho-card'           => 'var(--viho-card-bg)',
            '.viho-card-head'      => 'var(--viho-surface-gradient)',
            '.viho-kv-label'       => 'var(--viho-label)',
            '.viho-badge-primary'  => 'var(--viho-status-blue-bg)',
            '.viho-btn-primary'    => 'var(--viho-primary)',
            '.viho-action-tile'    => 'var(--viho-border-strong)',
            '.viho-stat-label'     => 'var(--viho-font-2xs)',
            '.viho-empty-state'    => 'var(--viho-space-2xl)',
        ] as $selector => $token) {
            $this->assertStringContainsString($selector, $css, "{$selector} must be defined.");
            $this->assertStringContainsString($token, $css, "{$selector} should be styled from {$token}.");
        }
    }

    // ── Stylesheet contract ──────────────────────────────────────────────────

    /** No component pulls the stylesheet in; the consuming page will include it once. */
    public function test_no_component_includes_the_stylesheet(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $src = $this->scanner->read("resources/views/components/viho/{$name}.blade.php");

            $this->assertStringNotContainsString('viho.styles', $src);
            $this->assertStringNotContainsString('<style', $src);
            $this->assertStringNotContainsString("@push('styles')", $src);
        }
    }

    /**
     * Rendering several primitives together emits no style block at all.
     *
     * A component that pushed its own CSS would emit it once per instance; a page with twenty kv
     * rows would carry twenty copies. Rendering the whole set at once is what makes that visible.
     */
    public function test_rendering_many_primitives_together_emits_no_duplicate_styles(): void
    {
        $html = $this->render(
            implode("\n", array_values(self::MINIMAL))
            . str_repeat('<x-viho.kv label="L" value="V" />', 5)
            . str_repeat('<x-viho.badge>B</x-viho.badge>', 5)
        );

        $this->assertSame(0, substr_count($html, '<style'), 'Primitives must emit no style block.');
        $this->assertStringNotContainsString(':root', $html);
        $this->assertGreaterThanOrEqual(6, substr_count($html, 'viho-kv'), 'Control: the kv rows really rendered.');
    }

    // ── 10-15. Neutrality ────────────────────────────────────────────────────

    /** No component references a product path, component or namespace. */
    public function test_no_component_references_a_product(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $rel = "resources/views/components/viho/{$name}.blade.php";

            $this->assertSame(
                [],
                $this->scanner->violationsIn($rel, $this->scanner->read($rel)),
                "x-viho.{$name} must reference neither product."
            );
        }
    }

    /** No product CSS prefix appears in any component. */
    public function test_no_component_uses_a_product_css_prefix(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $src = Scanner::stripComments($this->scanner->read("resources/views/components/viho/{$name}.blade.php"));

            foreach (['sol-', 'bol-', 'lol-', 'tcl-', 'hla-'] as $prefix) {
                $this->assertStringNotContainsString($prefix, $src, "x-viho.{$name} must not use the {$prefix} namespace.");
            }
        }
    }

    /** No forbidden business symbol, delegated to the M0 scanner rather than relisted. */
    public function test_no_component_contains_forbidden_business_logic(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $rel = "resources/views/components/viho/{$name}.blade.php";

            $this->assertSame(
                [],
                $this->scanner->nonPresentationSymbolsIn($rel, $this->scanner->read($rel)),
                "x-viho.{$name} must stay presentation-only."
            );
        }
    }

    /** No authorization, model access, service resolution, routing or JavaScript. */
    public function test_no_component_reaches_outside_presentation(): void
    {
        foreach (self::PRIMITIVES as $name) {
            $src = Scanner::stripComments($this->scanner->read("resources/views/components/viho/{$name}.blade.php"));

            foreach ([
                'auth(', 'Auth::', 'Gate::', '@can', '@cannot', 'policy(',
                'DB::', '::where(', '->get()', 'Model', 'Eloquent',
                'route(', 'url(', 'action(', 'app(', 'resolve(',
                '<script', 'fetch(', 'axios', 'document.', 'window.',
            ] as $needle) {
                $this->assertStringNotContainsString($needle, $src, "x-viho.{$name} must not contain {$needle}.");
            }
        }
    }

    // ── 8/9 + 19/20/21. Not yet consumed ─────────────────────────────────────

    /** No Hire Agent file renders a VIHO component. */
    public function test_hire_agent_does_not_consume_the_components_yet(): void
    {
        foreach ($this->scanner->filesInZone(Scanner::ZONE_HIRE_AGENT) as $file) {
            $this->assertStringNotContainsString('<x-viho.', $this->scanner->read($file), "{$file} — adoption is M3.");
        }
    }

    /** No Create Offer file renders a VIHO component. */
    public function test_create_offer_does_not_consume_the_components_yet(): void
    {
        foreach ($this->scanner->filesInZone(Scanner::ZONE_CREATE_OFFER) as $file) {
            $this->assertStringNotContainsString('<x-viho.', $this->scanner->read($file), "{$file} — adoption is M8.");
        }
    }

    /** Nothing anywhere — layouts and shared partials included — renders one. */
    public function test_nothing_in_the_application_consumes_the_components_yet(): void
    {
        $consumers = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if (! $item->isFile() || ! str_ends_with($item->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = ltrim(str_replace(base_path() . '/', '', $item->getPathname()), '/');
            if (str_starts_with($rel, 'resources/views/components/viho/')) {
                continue;
            }

            if (str_contains(Scanner::stripComments(file_get_contents($item->getPathname())), '<x-viho.')) {
                $consumers[] = $rel;
            }
        }

        $this->assertSame([], $consumers, "M2 is additive and inert:\n" . implode("\n", $consumers));
    }
}
