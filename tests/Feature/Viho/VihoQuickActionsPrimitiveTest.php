<?php

namespace Tests\Feature\Viho;

use Illuminate\Support\Facades\Blade;
use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * x-viho.quick-actions — the M5.3 release from deferral.
 *
 * Modelled on VihoSectionNavPrimitiveTest, and testing the same kind of claim: not that today's
 * markup looks right, but that the component stays a PRIMITIVE. For an action band there are four
 * ways to stop being one — learning what an action is, learning who is looking, learning how many
 * columns the host wants, and growing behaviour. Each has a test below.
 *
 * The authorization tests deserve their own note. This component is the layer that CANNOT leak,
 * because it cannot see the viewer; the leak it is built to prevent happens one layer up, where a
 * caller composes tiles. What is asserted here is that the component has no way to help — no
 * auth call, no user lookup, no filtering — so the decision cannot drift down into it and become
 * invisible.
 */
class VihoQuickActionsPrimitiveTest extends TestCase
{
    private const COMPONENT = 'resources/views/components/viho/quick-actions.blade.php';

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

    private function tiles(): string
    {
        return '<x-viho.action-tile label="Send Message" href="/x" />'
             . '<x-viho.action-tile label="Share Listing" />';
    }

    // ── rendering ────────────────────────────────────────────────────────────

    public function test_it_renders_one_region_wrapping_the_supplied_tiles(): void
    {
        $html = $this->render('<x-viho.quick-actions>' . $this->tiles() . '</x-viho.quick-actions>');

        $this->assertSame(1, substr_count($html, '<section'), 'Exactly one region.');
        $this->assertStringContainsString('viho-quick-actions-grid', $html);
        $this->assertSame(2, substr_count($html, 'class="viho-action-tile"'), 'Both tiles survive.');
        $this->assertStringContainsString('Send Message', $html);
        $this->assertStringContainsString('Share Listing', $html);
    }

    /** Order is the caller's; the band must not sort or regroup. */
    public function test_it_preserves_the_supplied_order(): void
    {
        $html = $this->render('<x-viho.quick-actions>' . $this->tiles() . '</x-viho.quick-actions>');

        $this->assertLessThan(
            strpos($html, 'Share Listing'),
            strpos($html, 'Send Message'),
            'Tiles render in the order the caller composed them.'
        );
    }

    public function test_the_label_and_icon_are_optional(): void
    {
        $with = $this->render(
            '<x-viho.quick-actions label="Quick Actions" icon="fa-solid fa-bolt">' . $this->tiles() . '</x-viho.quick-actions>'
        );
        $this->assertStringContainsString('Quick Actions', $with);
        $this->assertStringContainsString('fa-solid fa-bolt', $with);
        $this->assertStringContainsString('aria-hidden="true"', $with, 'The icon is decorative.');

        $without = $this->render('<x-viho.quick-actions>' . $this->tiles() . '</x-viho.quick-actions>');
        $this->assertStringNotContainsString('viho-quick-actions-label', $without);
    }

    public function test_the_aria_label_is_optional_and_passed_through(): void
    {
        $with = $this->render('<x-viho.quick-actions ariaLabel="Quick actions">' . $this->tiles() . '</x-viho.quick-actions>');
        $this->assertStringContainsString('aria-label="Quick actions"', $with);

        $without = $this->render('<x-viho.quick-actions>' . $this->tiles() . '</x-viho.quick-actions>');
        $this->assertStringNotContainsString('aria-label', $without);
    }

    /**
     * An empty band renders nothing at all.
     *
     * Not cosmetic. The realistic empty case is a viewer for whom every tile was conditioned out —
     * exactly the authorization-gated case — and a heading floating over an empty grid reads as a
     * section that failed to load. Whitespace and Blade comments must count as empty, because that
     * is precisely what a slot of conditioned-out tiles leaves behind.
     */
    public function test_an_empty_band_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render('<x-viho.quick-actions label="Quick Actions" />')));
        $this->assertSame('', trim($this->render('<x-viho.quick-actions label="Quick Actions"></x-viho.quick-actions>')));

        $whitespaceOnly = $this->render(
            '<x-viho.quick-actions label="Quick Actions">   {{-- all tiles conditioned out --}}   </x-viho.quick-actions>'
        );
        $this->assertSame('', trim($whitespaceOnly), 'A slot of whitespace and comments is empty.');
    }

    public function test_values_are_escaped(): void
    {
        $html = $this->render(
            '<x-viho.quick-actions :label="$l" :ariaLabel="$a">' . $this->tiles() . '</x-viho.quick-actions>',
            ['l' => '<script>alert(1)</script>', 'a' => '"><script>alert(2)</script>']
        );

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<script>alert(2)', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── it stays a primitive ─────────────────────────────────────────────────

    /** The contract is frozen. A new prop is a design decision, not a convenience. */
    public function test_the_prop_contract_is_frozen(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        preg_match('/@props\(\[(.*?)\]\)/s', $src, $m);
        $this->assertNotEmpty($m, 'The component must declare @props.');

        preg_match_all("/'([a-zA-Z]+)'\s*=>/", $m[1], $props);
        $declared = $props[1];
        sort($declared);

        $this->assertSame(
            ['ariaLabel', 'icon', 'label'],
            $declared,
            'x-viho.quick-actions takes label, icon and ariaLabel. Tiles arrive through the slot; '
            . 'an `items` prop would drag forms, modals and multi-CTA vocabulary into the neutral layer.'
        );
    }

    /**
     * No column count, anywhere.
     *
     * A `columns` prop lets a caller hard-code the number that suits their widest breakpoint, and
     * the caller cannot know how much room the band has — that depends on where the host page puts
     * it. The grid is auto-fit for that reason, and this pins it.
     */
    public function test_it_declares_no_column_count(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));
        $this->assertStringNotContainsString('columns', $src, 'No column-count prop or class.');

        $css = Scanner::stripComments($this->scanner->read('resources/views/viho/styles.blade.php'));
        preg_match('/\.viho-quick-actions-grid\s*\{(.*?)\}/s', $css, $grid);
        $this->assertNotEmpty($grid, 'The grid must declare its own rules.');

        $this->assertStringContainsString('auto-fit', $grid[1], 'Track count follows the available width.');
        $this->assertDoesNotMatchRegularExpression(
            '/repeat\(\s*\d+\s*,/',
            $grid[1],
            'A fixed repeat() count is the thing auto-fit exists to avoid.'
        );
    }

    /** No behaviour. Copy-to-clipboard and share sheets belong to the product. */
    public function test_the_primitive_contains_no_javascript(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        foreach (['<script', 'document.', 'window.', 'onclick', 'addEventListener', 'navigator.'] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "x-viho.quick-actions must not contain {$needle}.");
        }
    }

    /**
     * No authorization, and no way to acquire one.
     *
     * An action band is where an authorization mistake becomes visible: a tile advertises that a
     * workflow exists and what it is called, which is a disclosure even when the route behind it
     * is protected. The component must not be able to participate in that decision at all — if it
     * could filter tiles, the rule would eventually live in two places.
     */
    public function test_the_primitive_makes_no_authorization_decision(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        foreach ([
            'auth(', 'Auth::', 'user()', 'can(', 'cannot(', 'Gate::', 'policy(',
            '@auth', '@guest', '@can', 'is_owner', 'user_id',
        ] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $src,
                "x-viho.quick-actions must not contain {$needle} — it cannot see the viewer, and that "
                . 'is what makes it unable to leak to one.'
            );
        }
    }

    /** No product vocabulary: no routes, no listing concepts, no ids of its own. */
    public function test_the_primitive_contains_no_business_logic(): void
    {
        $src = Scanner::stripComments($this->scanner->read(self::COMPONENT));

        foreach ([
            'route(', 'url(', 'config(', 'DB::', 'Model', '->get->', 'auction', 'listing',
            'bid', 'proposal', 'compensation', 'hla-',
        ] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $src,
                "x-viho.quick-actions must not contain {$needle}."
            );
        }
    }

    /** It emits no id, so two bands on one page cannot collide. */
    public function test_it_emits_no_id_attribute(): void
    {
        $html = $this->render('<x-viho.quick-actions label="Quick Actions">' . $this->tiles() . '</x-viho.quick-actions>');

        $this->assertDoesNotMatchRegularExpression(
            '/<section[^>]*\sid=/',
            $html,
            'The band must not emit an id of its own.'
        );
    }
}
