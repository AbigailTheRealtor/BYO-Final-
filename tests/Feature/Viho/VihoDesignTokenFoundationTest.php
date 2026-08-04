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

    /**
     * The ONE product file permitted to read a VIHO token. Added in M5.1.
     *
     * ── WHY THE BAN WAS LIFTED FOR EXACTLY ONE FILE ──────────────────────────────────────────
     *
     * The original rule said neither product may read a token "yet", and it was carrying the
     * zero-render-change guarantee for M1: a foundation nothing consumes cannot alter a pixel.
     * That guarantee has done its job. M5.1 aligns the Hire Agent framework stylesheet to the
     * shared scale, which is the adoption the token layer existed for.
     *
     * It is a NAMED FILE, not a prefix and not a directory. `resources/views/hire_agent/` would
     * have been easier to write and would have let every future file in that directory read
     * tokens without anyone deciding it should. The whole point of the M1 contract is that
     * adoption is a decision per file, so a wildcard here would dissolve the rule it is amending.
     *
     * What this does NOT permit:
     *   · Create Offer. Still barred entirely until M8 — asserted separately below.
     *   · Any other Hire Agent file. The role views, the shared components and the shell are all
     *     still held to the original ban.
     *   · Token DECLARATION. This file may read `var(--viho-*)`; it may not declare one. The
     *     neutral stylesheet remains the single source of the values.
     *
     * Adding a second entry here is an architectural change requiring its own milestone decision,
     * exactly as VihoPresentationPrimitivesTest::APPROVED_SHARED_CONSUMER is for components.
     */
    private const APPROVED_TOKEN_CONSUMER = 'resources/views/hire_agent/framework/styles.blade.php';

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
     * The measured disagreements stay deferred rather than silently resolved.
     *
     * AMENDED IN M2, AND THE AMENDMENT IS THE POINT OF THIS COMMENT. M1 asserted that no
     * `--viho-status-rose-*` token existed, because Landlord defines no rose badge and three of
     * four is not common.
     *
     * M2's badge contract requires a `danger` variant, and there is no danger tone common to all
     * four views to build it from. The foundation therefore now defines `--viho-status-danger-*`
     * carrying rose's values. Renaming rose to danger would be a way to slip past the old
     * assertion, so it is stated plainly instead: what M1 was protecting was Landlord's PAGE, not
     * the string "rose". A token in the shared library renders nothing — Landlord's view still
     * defines no rose class, still emits no danger badge, and is byte-for-byte unaffected.
     *
     * What stays genuinely open is the M8 question: when Create Offer migrates, does Landlord gain
     * a danger badge or keep going without one? Nothing here answers that.
     *
     * The hero status pill deferral is untouched and still enforced below.
     */
    public function test_deferred_disagreements_are_not_silently_resolved(): void
    {
        $tokens = $this->tokensIn($this->stylesheet());

        // The danger tone exists, and carries rose's values rather than a newly invented colour.
        $this->assertSame('#FFF1F2', $tokens['--viho-status-danger-bg'] ?? null);
        $this->assertSame('#BE123C', $tokens['--viho-status-danger-fg'] ?? null);
        $this->assertSame('#FECDD3', $tokens['--viho-status-danger-border'] ?? null);

        // Landlord's page is still untouched by it — the thing the M1 deferral actually protected.
        $landlordSrc = $this->scanner->read(self::CREATE_OFFER_VIEWS['landlord']);
        $this->assertStringNotContainsString('badge-rose', preg_replace('/\s+/', '', $landlordSrc));
        $this->assertStringNotContainsString('viho-badge', $landlordSrc, 'Landlord consumes no VIHO badge yet.');

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
     * All four Hire Agent role views load the stylesheet exactly once each. Nothing else does.
     *
     * AMENDED IN M3, FOUR TIMES — Landlord, Seller, Buyer, Tenant, each after the previous
     * appearance was approved. With the fourth role migrated the "don't enrol the others" half of
     * this test has no roles left to protect, so the assertion's centre of gravity moves: what it
     * now defends is that the four includes stay FOUR SEPARATE INCLUDES.
     *
     * "Exactly once, per file" is doing all the work. The obvious tidy-up now available is to
     * delete the four includes and put one in the shared detail shell. That would still render
     * correctly and would still leave every role loading the stylesheet — so a test that only
     * asked "does each role load it?" would pass. It is rejected because the shell is also the
     * seam Create Offer is expected to adopt at M8, and a stylesheet reaching pages through a
     * shared partial is exactly how M8 would arrive early and unreviewed. Each role therefore
     * keeps its own include, and the shared Hire Agent files must keep none.
     */
    public function test_all_four_role_views_include_the_stylesheet_exactly_once(): void
    {
        $roleViews = [
            'resources/views/hire_landlord_agent/view.blade.php',
            'resources/views/hire_seller_agent/view.blade.php',
            'resources/views/hire_buyer_agent/view.blade.php',
            'resources/views/hire_tenant_agent/view.blade.php',
        ];

        foreach ($roleViews as $view) {
            $this->assertSame(
                1,
                substr_count(Scanner::stripComments($this->scanner->read($view)), "@include('viho.styles')"),
                "{$view} must include the shared stylesheet exactly once — not zero times, and not "
                . 'twice via a shared partial.'
            );
        }

        // Everything else in the Hire Agent zone — the shared detail shell above all — must carry
        // none. Consolidating the four includes into the shell is the change this forbids.
        foreach ($this->scanner->filesInZone(Scanner::ZONE_HIRE_AGENT) as $file) {
            if (in_array($file, $roleViews, true)) {
                continue;
            }

            $this->assertStringNotContainsString(
                'viho.styles',
                Scanner::stripComments($this->scanner->read($file)),
                "{$file} must not pull the shared foundation in for every role. The four role "
                . 'views each carry their own include; the shared shell carries none.'
            );
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

    /**
     * The four role views are the ONLY files in the application that include it.
     *
     * AMENDED IN M3, FOUR TIMES — from "nothing includes it", to Landlord, to Landlord+Seller, to
     * those plus Buyer, and now all four. Enumerating every Blade file rather than checking the
     * known views is the point, and it is the assertion that still has teeth now that no Hire
     * Agent role is left to protect: a layout or a shared partial pulling this in would migrate
     * every page in the application — Create Offer included, whose adoption is M8 — and neither
     * is somewhere anyone would think to look.
     *
     * The result is sorted before comparison. RecursiveDirectoryIterator returns entries in
     * filesystem order, which is not guaranteed and not alphabetical; with a single expected
     * entry that never showed, and with four it would have turned a real contract into a test
     * that passes or fails depending on inode order.
     */
    public function test_only_the_four_role_views_include_the_stylesheet_application_wide(): void
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

        sort($referencing);

        $this->assertSame(
            [
                'resources/views/hire_buyer_agent/view.blade.php',
                'resources/views/hire_landlord_agent/view.blade.php',
                'resources/views/hire_seller_agent/view.blade.php',
                'resources/views/hire_tenant_agent/view.blade.php',
            ],
            $referencing,
            'The four Hire Agent role views must be the only consumers of the shared stylesheet. '
            . "Anything else here — a layout or Create Offer especially — would migrate pages that "
            . "have not been reviewed:\n"
            . implode("\n", $referencing)
        );
    }

    // ── 6. No rendered output changed ────────────────────────────────────────

    /**
     * Only VIHO-owned files may read a VIHO token.
     *
     * AMENDED IN M2. M1 asserted that NOTHING anywhere read a `var(--viho-…)`, and said in its own
     * failure message that this was expected to change "once M3 starts consuming them". That
     * assumption was wrong about the milestone, not about the rule: first consumption belongs to
     * M2, where the primitives are authored, because a primitive that hardcoded a literal the
     * token layer already holds would reintroduce inside VIHO exactly the duplication M8 exists to
     * remove.
     *
     * So the assertion is narrowed rather than dropped. It is deliberately NOT relaxed to a
     * repository-wide allowance: the interesting property was never "does anyone read a token", it
     * is "does a PRODUCT read one" — and that must stay false until M3 and M8 respectively. A
     * blanket allowance would have retired the guard at the exact moment it started mattering.
     */
    public function test_only_viho_owned_files_consume_the_tokens(): void
    {
        $allowedPrefixes = ['resources/views/viho/', 'resources/views/components/viho/'];

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

                // Comments are stripped first. The foundation's own header explains where tokens
                // may be read, and its responsive section shows the `@media (…: var(…))` form
                // precisely to warn that it does not work — both would otherwise register as the
                // consumption they are describing.
                $body = Scanner::stripComments(file_get_contents($item->getPathname()));

                if (! str_contains($body, 'var(--viho')) {
                    continue;
                }

                $rel = ltrim(str_replace(base_path() . '/', '', $item->getPathname()), '/');

                // M5.1 — one named file, matched exactly rather than by prefix, so it cannot
                // widen into a directory. See APPROVED_TOKEN_CONSUMER.
                if ($rel === self::APPROVED_TOKEN_CONSUMER) {
                    continue;
                }

                foreach ($allowedPrefixes as $prefix) {
                    if (str_starts_with($rel, $prefix)) {
                        continue 2;
                    }
                }

                $consumers[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $consumers,
            "Only resources/views/viho/** and resources/views/components/viho/** may read a --viho "
            . "token. Product adoption is M3 (Hire Agent) and M8 (Create Offer):\n" . implode("\n", $consumers)
        );
    }

    /**
     * Neither product reads a token, stated on its own so a failure names the product directly.
     *
     * AMENDED IN M5.1, BY EXACTLY ONE FILE. This carried M1's zero-render-change guarantee — a
     * foundation nothing consumes cannot alter a pixel — and that guarantee has served its
     * purpose. The Hire Agent framework stylesheet now reads the shared scale; see
     * APPROVED_TOKEN_CONSUMER for why the exception is a named file rather than a directory.
     *
     * CREATE OFFER IS UNCHANGED and still barred in full, which is the half of this assertion
     * that still carries a guarantee. The loop deliberately keeps both zones rather than dropping
     * Hire Agent, so a second Hire Agent file reading a token fails here by name.
     */
    public function test_neither_product_consumes_the_tokens(): void
    {
        foreach ([Scanner::ZONE_HIRE_AGENT, Scanner::ZONE_CREATE_OFFER] as $zone) {
            foreach ($this->scanner->filesInZone($zone) as $file) {
                if ($file === self::APPROVED_TOKEN_CONSUMER) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'var(--viho',
                    Scanner::stripComments($this->scanner->read($file)),
                    "{$file} must not read a VIHO token. M5.1 lifted this for exactly one file — "
                    . self::APPROVED_TOKEN_CONSUMER . ' — and a second consumer is an architectural '
                    . 'change requiring its own milestone decision, not a test edit.'
                );
            }
        }
    }

    /**
     * Create Offer specifically reads no token, asserted on its own.
     *
     * The amendment above put a conditional inside a loop covering both products. That is exactly
     * the shape of change that later gets "simplified" into skipping more than it should, so the
     * Create Offer half is restated here where no exception exists to widen.
     */
    public function test_create_offer_still_reads_no_token(): void
    {
        foreach ($this->scanner->filesInZone(Scanner::ZONE_CREATE_OFFER) as $file) {
            $this->assertStringNotContainsString(
                'var(--viho',
                Scanner::stripComments($this->scanner->read($file)),
                "{$file} is Create Offer, which may not read a VIHO token before M8. The M5.1 "
                . 'exception is a Hire Agent file and does not extend here.'
            );
        }
    }

    /**
     * The approved consumer is real, is the only one, and is a file rather than a pattern.
     *
     * Three ways the M5.1 amendment could rot: the constant naming a file that no longer exists,
     * the file existing but never actually reading a token (leaving a granted exception nobody
     * uses, which invites someone to reuse it for something else), and the constant quietly
     * becoming a directory prefix.
     */
    public function test_the_token_exception_is_a_single_real_file_that_uses_it(): void
    {
        $this->assertTrue(
            $this->scanner->exists(self::APPROVED_TOKEN_CONSUMER),
            'The approved token consumer must exist.'
        );

        $this->assertStringContainsString(
            'var(--viho',
            Scanner::stripComments($this->scanner->read(self::APPROVED_TOKEN_CONSUMER)),
            'The exception is granted for adoption. A file that reads no token should not hold one.'
        );

        $this->assertStringEndsWith(
            '.blade.php',
            self::APPROVED_TOKEN_CONSUMER,
            'The exception must name one file. A directory or prefix would let future files inherit '
            . 'an exception nobody decided to grant them.'
        );

        // The exception must not have quietly become a second way into the neutral zone: reading
        // tokens is permitted, declaring them is not.
        $this->assertStringNotContainsString(
            '--viho-primary:',
            $this->scanner->read(self::APPROVED_TOKEN_CONSUMER),
            'Tokens are declared once, in the neutral stylesheet. The consumer may only read them.'
        );
    }

    /** The VIHO primitives really do read the tokens — the positive half of the same contract. */
    public function test_the_viho_layer_actually_consumes_the_tokens(): void
    {
        $this->assertStringContainsString(
            'var(--viho-',
            $this->stylesheet(),
            'M2 primitives are styled from the token layer; if nothing reads a token, M1 was pointless.'
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

    /**
     * The component directory exists and holds only the approved primitives.
     *
     * AMENDED IN M2. M1 asserted this directory did NOT exist, as a milestone marker. It now
     * exists, so the marker becomes a scope contract instead: exactly the approved set, nothing
     * more. That inversion is what stops the library growing sideways — the deferred composed
     * components each need a data and interaction contract that has not been mapped yet, and the
     * cheapest way to skip that work is to quietly add them here.
     *
     * AMENDED IN M4, BY EXACTLY ONE ENTRY: `hero`. See
     * test_deferred_composed_components_do_not_exist for why that single deferral was lifted. The
     * rule itself is unchanged — still an exact list, still no directory pattern or wildcard — so
     * a tenth file appearing here fails as loudly as a ninth did before.
     *
     * @see \Tests\Feature\Viho\VihoPresentationPrimitivesTest for the components' own behaviour
     */
    public function test_the_component_directory_holds_only_approved_primitives(): void
    {
        $this->assertTrue(
            $this->scanner->exists('resources/views/components/viho'),
            'M2 creates the shared component directory.'
        );

        $found = array_map(
            fn ($f) => basename($f, '.blade.php'),
            $this->scanner->filesInZone(Scanner::ZONE_VIHO)
        );
        sort($found);

        $this->assertSame(
            ['action-tile', 'badge', 'button', 'card', 'empty-state', 'hero', 'kv', 'section-header', 'stat', 'styles'],
            $found,
            'Only the eight approved M2 primitives, the M4 hero, and the M1 stylesheet may exist in '
            . 'the neutral namespace.'
        );
    }

    /**
     * The deferred composed components do not exist yet.
     *
     * Named individually so that adding one fails with its own name rather than as an off-by-one
     * in a count.
     *
     * ── `hero` WAS RELEASED FROM THIS LIST IN M4 ─────────────────────────────────────────────
     *
     * It is the only entry ever removed, and the release was deliberate rather than a convenience.
     * What this list was protecting is stated in the sibling docblock above: a composed component
     * needs a data and interaction contract that has been mapped, and the cheap way to skip that
     * work is to quietly add the file. M4 did the work instead:
     *
     *   · The prop contract is FROZEN and scalar — eyebrow, title, subtitle, identifier, status,
     *     figure, facts, actions. Every value arrives pre-resolved and pre-formatted; the
     *     component resolves, computes and formats nothing.
     *   · The guard tests prohibit role inference, authentication, routing, config reads, model or
     *     query access, and currency/date/number formatting inside the primitive — enforced by
     *     source scanning, not by convention.
     *   · The component is introduced behind the Hire Agent redesign feature flag
     *     (HireAgentHeroData::redesignEnabledFor()), so it reaches no page until a role is
     *     explicitly allowlisted.
     *
     * ── WHAT THIS DOES NOT DO ────────────────────────────────────────────────────────────────
     *
     * It does not accelerate or authorize any remaining deferred component. `hero-gallery`,
     * `hero-fact`, `media-placeholder`, `detail-shell`, `sidebar`, `page` and the rest are
     * untouched and still forbidden — including the three whose names resemble the released one.
     * Each would need its own contract, its own guards and its own milestone decision. Removing a
     * second name from this list is an architectural change, not a test fix.
     */
    public function test_deferred_composed_components_do_not_exist(): void
    {
        foreach ([
            'page', 'hero-gallery', 'interaction-hub', 'quick-actions', 'mobile-bar',
            'section-nav', 'modal', 'doc-item', 'contact-cta-row', 'detail-shell', 'sidebar',
            'divider', 'hero-fact', 'media-placeholder',
        ] as $deferred) {
            $this->assertFalse(
                $this->scanner->exists("resources/views/components/viho/{$deferred}.blade.php"),
                "x-viho.{$deferred} is deferred to a later milestone and must not exist yet. M4 "
                . 'released `hero` from this list and nothing else; a second release requires its '
                . 'own frozen contract, its own guards and an explicit milestone decision.'
            );
        }
    }

    /**
     * The released component is actually present, approved, and out of the deferred set.
     *
     * The three halves are asserted together because each covers a different way the M4 amendment
     * could rot: the file being deleted while the allowlist entry remains, the allowlist entry
     * being dropped while the file remains, and `hero` being re-added to the deferred list while
     * the file still sits in the approved directory. Any one of those is a contradiction between
     * two lists that would otherwise each look internally consistent.
     */
    public function test_the_hero_is_released_from_deferral_and_registered(): void
    {
        $this->assertTrue(
            $this->scanner->exists('resources/views/components/viho/hero.blade.php'),
            'x-viho.hero was released from deferral in M4 and must exist.'
        );

        $found = array_map(
            fn ($f) => basename($f, '.blade.php'),
            $this->scanner->filesInZone(Scanner::ZONE_VIHO)
        );

        $this->assertContains('hero', $found, 'x-viho.hero must be registered in the approved set.');

        $source = $this->scanner->read('tests/Feature/Viho/VihoDesignTokenFoundationTest.php');
        $deferredBlock = substr(
            $source,
            (int) strpos($source, 'public function test_deferred_composed_components_do_not_exist'),
            600
        );

        $this->assertStringNotContainsString(
            "'hero',",
            $deferredBlock,
            'x-viho.hero must not be listed as deferred while it exists in the approved directory.'
        );
    }

    /**
     * A still-deferred name would be caught if it were introduced.
     *
     * The deferral guard keys on file existence, so fifteen assertions that a file is absent would
     * pass identically against a predicate that always returned false. Proving the same predicate
     * returns true for a component that IS present is what makes those assertions mean something —
     * and it is the control that the M4 release did not quietly soften the mechanism while
     * removing one entry from the list it drives.
     */
    public function test_a_still_deferred_component_would_be_caught_if_introduced(): void
    {
        $this->assertTrue(
            $this->scanner->exists('resources/views/components/viho/hero.blade.php'),
            'Control: exists() must detect a component that is genuinely present.'
        );

        foreach (['hero-gallery', 'hero-fact', 'media-placeholder', 'detail-shell', 'sidebar', 'page'] as $stillDeferred) {
            $this->assertFalse(
                $this->scanner->exists("resources/views/components/viho/{$stillDeferred}.blade.php"),
                "x-viho.{$stillDeferred} remains deferred. The M4 release covered `hero` alone, and "
                . 'a name resembling it is not covered by it.'
            );
        }
    }

    /** M2 adds nothing under app/Support/Viho — PHP presentation support stays deferred. */
    public function test_m2_adds_no_php_presentation_support(): void
    {
        $this->assertFalse(
            $this->scanner->exists('app/Support/Viho'),
            'PHP presentation support is deferred until a later milestone proves it necessary.'
        );
    }
}
