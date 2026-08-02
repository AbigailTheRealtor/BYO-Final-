<?php

namespace Tests\Feature\Viho;

use Tests\Support\PresentationDependencyScanner as Scanner;
use Tests\TestCase;

/**
 * Milestone 1 — the shared design-token foundation.
 *
 * M1 adds one file: a neutral stylesheet of CSS custom properties that both products may later
 * consume. It is deliberately inert. Nothing includes it, so nothing renders differently, and the
 * tests below are as much about that absence as about the file's contents.
 *
 * WHY "IS IT UNUSED?" IS THE LOAD-BEARING ASSERTION. The whole safety argument for M1 is that no
 * page can change because no page reaches the file. That claim decays the moment someone wires it
 * up early — which is exactly what M3 will legitimately do — so it is asserted here rather than
 * assumed, and the assertions name the milestone that is allowed to change them.
 *
 * WHY THE VALUES ARE ASSERTED AGAINST CREATE OFFER, NOT AGAINST A LIST. Checking that
 * `--viho-primary` equals `#2563EB` proves only that this file says what this file says. The
 * useful property is that it says the same thing the four Create Offer views already say, so the
 * declared tokens are compared against the live `:root` blocks they were extracted from. If a
 * future edit "improves" a value here, the comparison fails and names the view it diverged from.
 *
 * @see \Tests\Support\PresentationDependencyScanner
 * @see \Tests\Feature\Viho\PresentationDependencyContractTest for the M0 direction contract
 */
class VihoDesignTokenFoundationTest extends TestCase
{
    private const STYLESHEET = 'resources/views/viho/styles.blade.php';

    private const CREATE_OFFER_VIEWS = [
        'seller'   => 'resources/views/offer-listing/seller/view.blade.php',
        'buyer'    => 'resources/views/offer-listing/buyer/view.blade.php',
        'landlord' => 'resources/views/offer-listing/landlord/view.blade.php',
        'tenant'   => 'resources/views/offer-listing/tenant/view.blade.php',
    ];

    private const HIRE_AGENT_VIEWS = [
        'resources/views/hire_seller_agent/view.blade.php',
        'resources/views/hire_buyer_agent/view.blade.php',
        'resources/views/hire_landlord_agent/view.blade.php',
        'resources/views/hire_tenant_agent/view.blade.php',
    ];

    /** The thirteen tokens the four Create Offer views already declare, verbatim. */
    private const DECLARED_TOKENS = [
        '--viho-primary'       => '#2563EB',
        '--viho-primary-hover' => '#1D4ED8',
        '--viho-page-bg'       => '#F8FAFC',
        '--viho-card-bg'       => '#FFFFFF',
        '--viho-heading'       => '#0F172A',
        '--viho-text'          => '#334155',
        '--viho-label'         => '#64748B',
        '--viho-border'        => '#E2E8F0',
        '--viho-success'       => '#16A34A',
        '--viho-seller'        => '#2563EB',
        '--viho-buyer'         => '#7C3AED',
        '--viho-landlord'      => '#0F766E',
        '--viho-tenant'        => '#0891B2',
    ];

    private Scanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new Scanner(base_path());
    }

    private function stylesheet(): string
    {
        return $this->scanner->read(self::STYLESHEET);
    }

    /** The `--name: value;` pairs declared in a source string. @return array<string, string> */
    private function tokensIn(string $source): array
    {
        preg_match_all('/(--viho-[a-z0-9-]+)\s*:\s*([^;]+);/i', $source, $m, PREG_SET_ORDER);

        $out = [];
        foreach ($m as $hit) {
            $out[$hit[1]] = trim($hit[2]);
        }

        return $out;
    }

    // ── 1. Location ──────────────────────────────────────────────────────────

    /**
     * The file sits in the neutral namespace, and the scanner agrees it does.
     *
     * Asserting the path alone would not prove neutrality — `zoneForPath` is what the whole
     * dependency contract keys off, so it is the thing that has to say VIHO.
     */
    public function test_the_stylesheet_exists_in_the_neutral_namespace(): void
    {
        $this->assertTrue($this->scanner->exists(self::STYLESHEET), self::STYLESHEET . ' must exist.');

        $this->assertSame(
            Scanner::ZONE_VIHO,
            $this->scanner->zoneForPath(self::STYLESHEET),
            'The shared stylesheet must live in the neutral VIHO zone, not in a product namespace.'
        );
    }

    /** It is not hidden inside a product-owned directory under a neutral-sounding name. */
    public function test_the_stylesheet_is_not_in_a_product_namespace(): void
    {
        foreach ([
            'resources/views/offer-listing/',
            'resources/views/hire_agent/',
            'resources/views/components/hire-agent/',
            'resources/views/components/listing/',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                self::STYLESHEET,
                'The shared foundation must not be owned by a product.'
            );
        }
    }

    // ── 2. Token families ────────────────────────────────────────────────────

    /**
     * The declared thirteen are reproduced with the values Create Offer already uses.
     *
     * @dataProvider declaredTokens
     */
    public function test_declared_tokens_match_create_offer(string $token, string $expected): void
    {
        $tokens = $this->tokensIn($this->stylesheet());

        $this->assertArrayHasKey($token, $tokens, "{$token} must be defined in the shared foundation.");
        $this->assertSame($expected, $tokens[$token], "{$token} must keep the value Create Offer already renders.");
    }

    public static function declaredTokens(): array
    {
        $out = [];
        foreach (self::DECLARED_TOKENS as $token => $value) {
            $out[$token] = [$token, $value];
        }

        return $out;
    }

    /**
     * The extracted values are still what the four views declare — the anti-drift check.
     *
     * This is the assertion that makes M1 an extraction rather than a redesign. It reads the live
     * `:root` blocks and compares them to the foundation, so "someone improved a hex" fails here
     * with the offending role named.
     */
    public function test_the_four_create_offer_views_still_declare_the_same_values(): void
    {
        $foundation = $this->tokensIn($this->stylesheet());

        foreach (self::CREATE_OFFER_VIEWS as $role => $view) {
            preg_match('/:root\s*\{(.*?)\}/s', $this->scanner->read($view), $m);
            $this->assertNotEmpty($m, "Precondition: the {$role} view declares a :root token block.");

            $roleTokens = $this->tokensIn($m[1]);
            $this->assertNotEmpty($roleTokens, "Precondition: the {$role} block declares tokens.");

            foreach ($roleTokens as $token => $value) {
                $this->assertArrayHasKey($token, $foundation, "{$token} is declared by {$role} but missing from the foundation.");
                $this->assertSame(
                    strtoupper($value),
                    strtoupper($foundation[$token]),
                    "{$token} diverges between the {$role} view and the shared foundation. M1 extracts values; it does not change them."
                );
            }
        }
    }

    /**
     * Every required family is present.
     *
     * @dataProvider requiredFamilies
     */
    public function test_required_token_families_are_present(string $family, array $expectedTokens): void
    {
        $tokens = $this->tokensIn($this->stylesheet());

        foreach ($expectedTokens as $token) {
            $this->assertArrayHasKey($token, $tokens, "The {$family} family must define {$token}.");
            $this->assertNotSame('', $tokens[$token], "{$token} must have a value.");
        }
    }

    public static function requiredFamilies(): array
    {
        return [
            'colour'            => ['colour', ['--viho-primary', '--viho-primary-hover']],
            'surface'           => ['surface', ['--viho-page-bg', '--viho-card-bg', '--viho-surface-subtle', '--viho-surface-inverse']],
            'text'              => ['text', ['--viho-heading', '--viho-text', '--viho-text-strong', '--viho-text-soft']],
            'muted text'        => ['muted text', ['--viho-label', '--viho-text-muted']],
            'border'            => ['border', ['--viho-border', '--viho-border-strong', '--viho-border-subtle']],
            'accent'            => ['accent', ['--viho-seller', '--viho-buyer', '--viho-landlord', '--viho-tenant']],
            'semantic status'   => ['semantic status', [
                '--viho-success',
                '--viho-status-blue-bg', '--viho-status-blue-fg', '--viho-status-blue-border',
                '--viho-status-green-bg', '--viho-status-amber-fg', '--viho-status-teal-border',
                '--viho-status-purple-bg',
            ]],
            'radius'            => ['radius', ['--viho-radius-sm', '--viho-radius-md', '--viho-radius-lg', '--viho-radius-xl', '--viho-radius-pill', '--viho-radius-circle']],
            'shadow'            => ['shadow', ['--viho-shadow-card', '--viho-shadow-soft', '--viho-shadow-raised', '--viho-shadow-overlay', '--viho-shadow-lifted']],
            'spacing'           => ['spacing', ['--viho-space-3xs', '--viho-space-2xs', '--viho-space-xs', '--viho-space-sm', '--viho-space-md', '--viho-space-lg', '--viho-space-xl', '--viho-space-2xl']],
            'typography size'   => ['typography size', ['--viho-font-3xs', '--viho-font-2xs', '--viho-font-xs', '--viho-font-sm', '--viho-font-md', '--viho-font-lg', '--viho-font-xl', '--viho-font-2xl', '--viho-font-display']],
            'typography weight' => ['typography weight', ['--viho-weight-semibold', '--viho-weight-bold', '--viho-weight-extrabold']],
            'typography metric' => ['typography metric', ['--viho-tracking-tight', '--viho-tracking-tighter', '--viho-tracking-wide', '--viho-leading-none', '--viho-leading-tight', '--viho-leading-snug', '--viho-leading-normal', '--viho-leading-relaxed']],
            'motion'            => ['motion', ['--viho-transition']],
            'responsive'        => ['responsive', ['--viho-bp-md', '--viho-bp-lg']],
        ];
    }

    /**
     * Every promoted value is one that already occurs in all four Create Offer views.
     *
     * The rule M1 was given was "extract what is genuinely common". This checks the promoted
     * radius, shadow and breakpoint values against the real views rather than trusting the note in
     * the file header. Landlord writes several of these without a space after the colon, so the
     * comparison is made against whitespace-normalised source.
     */
    public function test_promoted_values_occur_in_all_four_views(): void
    {
        $normalised = [];
        foreach (self::CREATE_OFFER_VIEWS as $role => $view) {
            $normalised[$role] = preg_replace('/\s+/', '', $this->scanner->read($view));
        }

        $tokens = $this->tokensIn($this->stylesheet());

        foreach ([
            '--viho-radius-sm', '--viho-radius-md', '--viho-radius-lg',
            '--viho-radius-xl', '--viho-radius-pill', '--viho-radius-circle',
            '--viho-shadow-card', '--viho-shadow-soft', '--viho-shadow-raised',
            '--viho-shadow-overlay', '--viho-shadow-lifted',
            '--viho-bp-md', '--viho-bp-lg',
        ] as $token) {
            $needle = preg_replace('/\s+/', '', $tokens[$token]);

            foreach ($normalised as $role => $src) {
                $this->assertStringContainsString(
                    $needle,
                    $src,
                    "{$token} ({$tokens[$token]}) was promoted to the shared foundation but does not occur in the {$role} view. "
                    . 'Only values common to all four may be promoted.'
                );
            }
        }
    }

    /**
     * The two measured disagreements stay deferred rather than silently resolved.
     *
     * Landlord defines no `rose` badge tone, and renders its hero status pill teal where the other
     * three render it green. Promoting either would recolour a live page under cover of a token
     * extraction. This asserts the foundation declines to decide.
     */
    public function test_deferred_disagreements_are_not_silently_resolved(): void
    {
        $tokens = $this->tokensIn($this->stylesheet());

        foreach (['--viho-status-rose-bg', '--viho-status-rose-fg', '--viho-status-rose-border'] as $token) {
            $this->assertArrayNotHasKey(
                $token,
                $tokens,
                'Landlord defines no rose tone, so three-of-four is not common and rose stays deferred.'
            );
        }

        foreach (['--viho-status-pill-bg', '--viho-status-pill-fg', '--viho-status-pill-border'] as $token) {
            $this->assertArrayNotHasKey(
                $token,
                $tokens,
                "Landlord's hero status pill is teal where the others are green. That is a per-role "
                . 'choice and must not be flattened into a neutral token.'
            );
        }

        // Control: the disagreements are real, not assumed.
        $landlord = preg_replace('/\s+/', '', $this->scanner->read(self::CREATE_OFFER_VIEWS['landlord']));
        $seller   = preg_replace('/\s+/', '', $this->scanner->read(self::CREATE_OFFER_VIEWS['seller']));

        $this->assertStringNotContainsString('badge-rose', $landlord, 'Precondition: Landlord has no rose tone.');
        $this->assertStringContainsString('badge-rose', $seller, 'Precondition: Seller does have one.');
    }

    // ── 3. Presentation-only boundary ────────────────────────────────────────

    /** No product logic, delegated to the M0 scanner rather than reimplemented. */
    public function test_the_stylesheet_contains_no_forbidden_product_logic(): void
    {
        $this->assertSame(
            [],
            $this->scanner->nonPresentationSymbolsIn(self::STYLESHEET, $this->stylesheet()),
            'The shared foundation must stay presentation-only.'
        );
    }

    /** No JavaScript of any kind. */
    public function test_the_stylesheet_contains_no_javascript(): void
    {
        $src = Scanner::stripComments($this->stylesheet());

        foreach (['<script', 'function(', '=>', 'document.', 'window.', 'addEventListener', 'querySelector'] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "The foundation must contain no JavaScript ({$needle}).");
        }
    }

    /** No Blade control flow, model access or route helpers — it is a stylesheet. */
    public function test_the_stylesheet_contains_no_dynamic_blade_or_routing(): void
    {
        $src = Scanner::stripComments($this->stylesheet());

        foreach (['@if', '@foreach', '@php', '{{', 'route(', 'url(\'', 'asset(', '::class', '$auction', '$user'] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "The foundation must be static CSS ({$needle}).");
        }
    }

    // ── 4/5. Not yet consumed ────────────────────────────────────────────────

    /**
     * Hire Agent does not include it yet. Adoption is M3.
     *
     * The safety argument for M1 is that nothing reaches this file. Left unasserted, that argument
     * would quietly expire the first time someone wired it up early.
     */
    public function test_hire_agent_does_not_include_the_stylesheet_yet(): void
    {
        foreach (self::HIRE_AGENT_VIEWS as $view) {
            $this->assertStringNotContainsString(
                'viho.styles',
                $this->scanner->read($view),
                "{$view} must not consume the shared foundation until M3."
            );
        }

        foreach ($this->scanner->filesInZone(Scanner::ZONE_HIRE_AGENT) as $file) {
            $this->assertStringNotContainsString('viho.styles', Scanner::stripComments($this->scanner->read($file)), $file);
        }
    }

    /** Create Offer does not include it yet. Adoption is M8. */
    public function test_create_offer_does_not_include_the_stylesheet_yet(): void
    {
        foreach (self::CREATE_OFFER_VIEWS as $role => $view) {
            $this->assertStringNotContainsString(
                'viho.styles',
                $this->scanner->read($view),
                "The {$role} view must not consume the shared foundation until M8."
            );
        }
    }

    /** Nothing anywhere includes it — layouts and shared partials included. */
    public function test_nothing_in_the_application_includes_the_stylesheet_yet(): void
    {
        $referencing = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if (! $item->isFile() || ! str_ends_with($item->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = ltrim(str_replace(base_path() . '/', '', $item->getPathname()), '/');
            if ($rel === self::STYLESHEET) {
                continue;
            }

            if (str_contains(Scanner::stripComments(file_get_contents($item->getPathname())), 'viho.styles')) {
                $referencing[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $referencing,
            "M1 is additive and inert. These files already consume it:\n" . implode("\n", $referencing)
        );
    }

    // ── 6. No rendered output changed ────────────────────────────────────────

    /**
     * The rendered-output argument, stated as the fact it rests on.
     *
     * A stylesheet can only affect a page it reaches. The test above proves nothing reaches this
     * one. This adds the second, independent leg: the tokens it declares are inert anyway, because
     * the repository contains no `var(--viho-…)` reference at all. Both legs would have to fail
     * before a pixel could move.
     */
    public function test_the_tokens_are_not_consumed_anywhere(): void
    {
        $consumers = [];

        foreach (['resources/views', 'resources/css', 'public/css'] as $dir) {
            $full = base_path($dir);
            if (! is_dir($full)) {
                continue;
            }

            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($items as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                // Comments are stripped first. The foundation's own header explains that nothing
                // reads a token yet, and its responsive section shows the `@media (…: var(…))`
                // form precisely to warn that it does not work — both would otherwise register as
                // the consumption they are documenting the absence of.
                $body = Scanner::stripComments(file_get_contents($item->getPathname()));

                if (str_contains($body, 'var(--viho')) {
                    $consumers[] = ltrim(str_replace(base_path() . '/', '', $item->getPathname()), '/');
                }
            }
        }

        $this->assertSame(
            [],
            $consumers,
            "No page reads a --viho token yet, which is why re-declaring them cannot change rendering. "
            . "Once M3 starts consuming them this assertion is expected to change:\n" . implode("\n", $consumers)
        );
    }

    // ── 7/8. The M0 contract still holds ─────────────────────────────────────

    /** The new file introduces no forbidden edge, in either direction. */
    public function test_the_stylesheet_references_no_product_path_or_namespace(): void
    {
        $this->assertSame(
            [],
            $this->scanner->violationsIn(self::STYLESHEET, $this->stylesheet()),
            'The shared foundation must reference neither product.'
        );
    }

    /** Belt and braces: none of the four product CSS prefixes appears in the foundation. */
    public function test_the_stylesheet_claims_no_product_css_namespace(): void
    {
        $src = Scanner::stripComments($this->stylesheet());

        foreach (['.sol-', '.bol-', '.lol-', '.tcl-', '.hla-'] as $prefix) {
            $this->assertStringNotContainsString(
                $prefix,
                $src,
                "The shared foundation must not style {$prefix} — those namespaces belong to the products."
            );
        }
    }

    /** M1 adds no cross-product edge anywhere. */
    public function test_the_m0_dependency_contract_still_holds(): void
    {
        $violations = [];

        foreach ([Scanner::ZONE_HIRE_AGENT, Scanner::ZONE_CREATE_OFFER, Scanner::ZONE_VIHO] as $zone) {
            foreach ($this->scanner->filesInZone($zone) as $file) {
                $violations = array_merge($violations, $this->scanner->violationsIn($file, $this->scanner->read($file)));
            }
        }

        $this->assertSame([], $violations, "M1 must add no forbidden edge:\n" . implode("\n", $violations));
    }

    /** M1 builds no components. That is M2. */
    public function test_m1_creates_no_shared_components(): void
    {
        $this->assertFalse(
            $this->scanner->exists('resources/views/components/viho'),
            'Shared Blade components are M2, not M1.'
        );
    }
}
