<?php

namespace Tests\Feature\Viho;

use Illuminate\Support\Facades\Blade;
use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * x-viho.section-nav — the M5.2 release from deferral.
 *
 * Modelled on VihoHeroPrimitiveTest. The point of these tests is not that the markup looks right
 * today; it is that the component stays a PRIMITIVE — that it never learns what a section is, who
 * is looking, or how to scroll. Those are the three ways a nav stops being reusable.
 */
class VihoSectionNavPrimitiveTest extends TestCase
{
    private const COMPONENT = 'resources/views/components/viho/section-nav.blade.php';

    private Scanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    private function render(string $blade, array $data = []): string
    {
        return (string) Blade::render($blade, $data);
    }

    private function items(): array
    {
        return [
            ['id' => 'hla-section-property', 'label' => 'Property Details'],
            ['id' => 'hla-section-leasing',  'label' => 'Leasing Terms'],
        ];
    }

    // ── rendering ────────────────────────────────────────────────────────────

    public function test_it_renders_one_nav_with_one_link_per_item(): void
    {
        $html = $this->render('<x-viho.section-nav :items="$items" />', ['items' => $this->items()]);

        $this->assertSame(1, substr_count($html, '<nav'), 'Exactly one nav landmark.');
        $this->assertSame(2, substr_count($html, 'class="viho-section-nav-link"'));
        $this->assertStringContainsString('href="#hla-section-property"', $html);
        $this->assertStringContainsString('href="#hla-section-leasing"', $html);
        $this->assertStringContainsString('Property Details', $html);
    }

    /** Order is the caller's; the component must not sort. */
    public function test_it_preserves_the_supplied_order(): void
    {
        $html = $this->render('<x-viho.section-nav :items="$items" />', ['items' => $this->items()]);

        $this->assertLessThan(
            strpos($html, 'hla-section-leasing'),
            strpos($html, 'hla-section-property'),
            'The nav renders items in the order given, not alphabetically or by any other rule.'
        );
    }

    /** Nothing to navigate means no landmark at all, not an empty bar. */
    public function test_an_empty_list_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render('<x-viho.section-nav :items="[]" />')));
        $this->assertSame('', trim($this->render('<x-viho.section-nav />')));
    }

    /**
     * Malformed rows are dropped rather than rendered.
     *
     * A row without an id would emit `href="#"`; a row without a label would emit a link with no
     * accessible name. Both are worse than a shorter list.
     */
    public function test_malformed_rows_are_dropped(): void
    {
        $html = $this->render('<x-viho.section-nav :items="$items" />', ['items' => [
            ['id' => 'good', 'label' => 'Good'],
            ['id' => '', 'label' => 'No id'],
            ['id' => 'no-label', 'label' => ''],
            ['id' => '  ', 'label' => 'Whitespace id'],
            ['label' => 'Missing id key'],
            ['id' => 'missing-label-key'],
            'not an array',
            ['id' => ['array'], 'label' => 'Non-scalar id'],
        ]]);

        $this->assertSame(1, substr_count($html, 'class="viho-section-nav-link"'), 'Only the well-formed row survives.');
        $this->assertStringContainsString('href="#good"', $html);
        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('No id', $html);
        $this->assertStringNotContainsString('Whitespace id', $html);
        $this->assertStringNotContainsString('Missing id key', $html);
    }

    public function test_current_is_supplied_not_inferred(): void
    {
        $plain = $this->render('<x-viho.section-nav :items="$items" />', ['items' => $this->items()]);
        $this->assertStringNotContainsString('aria-current', $plain, 'Nothing is current unless the caller says so.');

        $marked = $this->render('<x-viho.section-nav :items="$items" current="hla-section-leasing" />', ['items' => $this->items()]);
        $this->assertSame(1, substr_count($marked, 'aria-current="true"'), 'Exactly one link is current.');

        $unknown = $this->render('<x-viho.section-nav :items="$items" current="does-not-exist" />', ['items' => $this->items()]);
        $this->assertStringNotContainsString('aria-current', $unknown, 'An unknown current marks nothing.');
    }

    public function test_the_aria_label_is_optional_and_passed_through(): void
    {
        $with = $this->render('<x-viho.section-nav :items="$items" ariaLabel="Sections" />', ['items' => $this->items()]);
        $this->assertStringContainsString('aria-label="Sections"', $with);

        $without = $this->render('<x-viho.section-nav :items="$items" />', ['items' => $this->items()]);
        $this->assertStringNotContainsString('aria-label', $without);
    }

    public function test_values_are_escaped(): void
    {
        $html = $this->render('<x-viho.section-nav :items="$items" />', ['items' => [
            ['id' => 'x', 'label' => '<script>alert(1)</script>'],
        ]]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── it stays a primitive ─────────────────────────────────────────────────

    /**
     * The frozen prop contract. Adding a prop is a contract change, and a nav is exactly the
     * component someone would be tempted to teach about sections, viewers or scrolling.
     */
    public function test_the_prop_contract_is_frozen(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        preg_match('/@props\(\[(.*?)\]\)/s', $src, $m);
        $this->assertNotEmpty($m, 'The component must declare @props.');

        preg_match_all("/'([a-zA-Z]+)'\s*=>/", $m[1], $found);
        $props = $found[1];
        sort($props);

        $this->assertSame(
            ['ariaLabel', 'current', 'items'],
            $props,
            'The section-nav prop contract is frozen. Widening it needs a milestone decision.'
        );
    }

    /**
     * NO JAVASCRIPT. This is the reason a navigation bar can be a neutral primitive at all.
     *
     * Sticky offset, smooth scrolling and current-section tracking are behaviour and belong to the
     * product. A component that shipped its own scroll listener would be deciding what "current"
     * means for every page that ever adopted it.
     */
    public function test_the_primitive_contains_no_javascript(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        foreach (['<script', 'document.', 'window.', 'addEventListener', 'onclick', 'onscroll', 'scrollIntoView'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $src,
                "x-viho.section-nav must contain no behaviour ({$needle}). Scroll handling belongs "
                . 'to the consuming product, attached via the data-viho-section-nav hook.'
            );
        }
    }

    /** It emits no id of its own, so two navs on one page cannot collide. */
    public function test_it_emits_no_id_attribute(): void
    {
        $html = $this->render('<x-viho.section-nav :items="$items" />', ['items' => $this->items()]);
        $this->assertDoesNotMatchRegularExpression('/\sid="/', $html);

        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));
        $this->assertDoesNotMatchRegularExpression('/\sid="[a-zA-Z][^"{}]*"/', $src);
    }

    /**
     * No product id scheme leaks into the neutral layer.
     *
     * `hla-section-*` is Hire Agent's convention. If it appeared here, the next product to adopt
     * the nav would inherit a naming scheme that means nothing to it.
     */
    public function test_it_hard_codes_no_product_section_ids(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        foreach (['hla-', 'sol-', 'lol-', 'bol-', 'tcl-', 'section-overview', 'section-photos'] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "The primitive must not know about {$needle}.");
        }
    }

    /** No authorization, no data access, no routing — the standard primitive guards. */
    public function test_the_primitive_contains_no_business_logic(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        foreach ([
            'auth(', 'Auth::', 'user()', 'Gate::', 'can(', 'route(', 'url(',
            'DB::', '::where', 'config(', 'HireAgent', 'Offer',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "x-viho.section-nav must not contain {$needle}.");
        }
    }

    /**
     * The sticky offset is the consumer's, not the component's.
     *
     * The correct offset is the height of whatever fixed chrome the host page has, which this
     * layer cannot know. A hard-coded pixel value here would be wrong on every page but one — and
     * Offer Listing's own 82px is exactly the number not to copy.
     */
    public function test_the_sticky_offset_is_left_to_the_consumer(): void
    {
        $css = Scanner::stripComments($this->scanner->read('resources/views/viho/styles.blade.php'));

        preg_match('/\.viho-section-nav\s*\{(.*?)\}/s', $css, $m);
        $this->assertNotEmpty($m, 'The nav must declare its own rules.');

        $this->assertStringContainsString('var(--viho-section-nav-offset', $m[1], 'The offset must be a consumer-supplied variable.');
        $this->assertDoesNotMatchRegularExpression('/top:\s*\d+px/', $m[1], 'The offset must not be a hard-coded pixel value.');
        $this->assertStringNotContainsString('82px', $css, "Offer Listing's header offset is not a shared constant.");
    }

    /**
     * The list items generate no marker, on a hostile host.
     *
     * Regression, and one that only manual verification found. `list-style: none` on the list is
     * the reflex reset and it is NOT sufficient: list-style-type only selects the default marker,
     * so a host page that sets ::marker `content` directly still paints one. The Hire Agent detail
     * page does exactly that — `.hla-detail-page ul:not(.services) li::marker` gives every list
     * item a FontAwesome chevron — and the bar rendered as
     * "» Property Details » Leasing Terms » …" the first time it was looked at in a browser.
     *
     * That selector outranks a single class, so loading this stylesheet later does not fix it. The
     * defence is `display: block`: a box that is not a list-item has no marker for a host rule to
     * style. Asserted structurally rather than visually because nothing else in the suite renders
     * this page in a browser.
     */
    public function test_the_list_items_generate_no_marker(): void
    {
        $css = Scanner::stripComments($this->scanner->read('resources/views/viho/styles.blade.php'));

        preg_match('/\.viho-section-nav-list\s*\{(.*?)\}/s', $css, $list);
        $this->assertNotEmpty($list, 'The list must declare its own rules.');
        $this->assertStringContainsString('list-style: none', $list[1], 'The conventional reset stays.');

        // The reset that actually holds: the item must not be a list-item at all.
        $this->assertMatchesRegularExpression(
            '/\.viho-section-nav-item\s*\{[^}]*display:\s*block/s',
            $css,
            'A section-nav item must be display:block so no ::marker is generated for a host page to style.'
        );

        // And the belt-and-braces marker reset must still outrank the host rule that broke it,
        // which is two classes and two elements — a bare `.viho-section-nav-item::marker` loses.
        $this->assertMatchesRegularExpression(
            '/\.viho-section-nav\s+\.viho-section-nav-list\s+\.viho-section-nav-item::marker\s*\{[^}]*content:\s*none/s',
            $css,
            'The ::marker reset must be specific enough to beat `.hla-detail-page ul:not(.services) li::marker`.'
        );
    }
}
