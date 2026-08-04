<?php

namespace Tests\Support;

/**
 * Resolves which presentation ZONE a file belongs to, and which zones it depends on.
 *
 * WHY THIS EXISTS. The previous contract —
 * HireAgentDetailFrameworkTest::test_create_offer_is_untouched_and_uncoupled — asserted that Hire
 * Agent and Create Offer were *mutually disjoint in every respect*: no shared file, no shared class
 * name, no include across the boundary, and disjoint CSS namespaces. That was exactly right while
 * Hire Agent was being refactored alone, because the only way the two could have touched was by
 * accident.
 *
 * It is incompatible with the approved architecture, which is not "no sharing" but "no sharing
 * BETWEEN PRODUCTS":
 *
 *     Hire Agent   ──────►  VIHO  ◄──────  Create Offer
 *
 *     Hire Agent   ──✗──►  Create Offer
 *     Create Offer ──✗──►  Hire Agent
 *     VIHO         ──✗──►  either product
 *
 * The old contract cannot express that, because it forbids the two inbound edges along with the
 * two forbidden ones. Weakening it to "…unless the file is shared" would have deleted the very
 * property it exists to protect. This class replaces the blanket prohibition with a DIRECTED one:
 * every edge is classified, and only the cross-product and VIHO-outbound edges are violations.
 *
 * WHY A SCANNER AND NOT MORE file_get_contents() ASSERTIONS. The old test did substring checks,
 * and substring checks cannot express this contract safely. The decisive case is already in the
 * tree:
 *
 *     <x-hire-agent.detail-shell>   Hire Agent PRIVATE  → resources/views/components/hire-agent/
 *     <x-hire-agent-modal>          SHARED, and already used by all four Create Offer views
 *
 * `str_contains($createOfferView, 'hire-agent')` is true for both, so a naive check would report
 * four cross-product violations that do not exist and have never existed. The distinguishing fact
 * is structural, not textual: the private component resolves under the directory
 * `components/hire-agent/`, and the shared one resolves to the FILE `components/hire-agent-modal`,
 * which is not inside it. Zones are therefore resolved by path prefix — with the trailing slash
 * doing the real work — after each dependency is mapped to the file it actually names.
 *
 * The same trap applies to prose and to route names. `hire.agent.auction.edit` is a route, not an
 * include; "Create Offer" appears in Hire Agent's own documentation comments. Nothing here scans
 * free text: comments are stripped first, and only recognised dependency FORMS are extracted.
 *
 * WHAT IS DELIBERATELY NOT BUILT. This is not a Blade parser. It recognises the dependency forms
 * that actually occur in the scanned tree (`@extends`, `@include`, `<x-…>` tags) plus the small set
 * that the migration is likely to introduce (`@includeIf`/`@includeWhen`/`@includeUnless`/
 * `@includeFirst`, `@component`, `view()`, `View::make()`, PHP imports, asset()/mix()). It does not
 * evaluate dynamic view names — a view name assembled at runtime is invisible to it. That is an
 * accepted limitation, recorded here rather than hidden: the contract covers static presentation
 * dependencies, which is what a Blade view layer is made of.
 *
 * @see \Tests\Feature\Viho\PresentationDependencyScannerTest for the positive and negative controls
 * @see \Tests\Feature\Viho\PresentationDependencyContractTest for the contract over the real tree
 */
final class PresentationDependencyScanner
{
    public const ZONE_HIRE_AGENT   = 'hire_agent';
    public const ZONE_CREATE_OFFER = 'create_offer';
    public const ZONE_VIHO         = 'viho';

    /** Everything else: layouts, shared partials, unowned components, non-presentation code. */
    public const ZONE_NEUTRAL = 'neutral';

    /**
     * Zone membership by path prefix. Trailing slashes are load-bearing.
     *
     * `resources/views/components/hire-agent/` matches the private component directory and does
     * NOT match `resources/views/components/hire-agent-modal.blade.php`, which is shared and is
     * already consumed by all four Create Offer views. Removing either trailing slash would
     * silently reclassify a shared component as product-private and fail four true edges.
     *
     * @var array<string, array<int, string>>
     */
    public const ZONE_PATH_PREFIXES = [
        self::ZONE_HIRE_AGENT => [
            'resources/views/hire_seller_agent/',
            'resources/views/hire_buyer_agent/',
            'resources/views/hire_landlord_agent/',
            'resources/views/hire_tenant_agent/',
            'resources/views/hire_agent/',
            'resources/views/components/hire-agent/',
            'app/Support/HireAgent/',
        ],
        self::ZONE_CREATE_OFFER => [
            'resources/views/offer-listing/',
        ],
        self::ZONE_VIHO => [
            'resources/views/viho/',
            'resources/views/components/viho/',
            'app/Support/Viho/',
        ],
    ];

    /**
     * CSS class prefixes owned by each zone.
     *
     * Matched against whole class TOKENS, never as free substrings: `sol-` as a substring would be
     * a coin flip on any English text, while `.sol-hero` and `class="sol-hero"` are unambiguous.
     *
     * @var array<string, array<int, string>>
     */
    public const ZONE_CSS_PREFIXES = [
        self::ZONE_HIRE_AGENT   => ['hla-'],
        self::ZONE_CREATE_OFFER => ['sol-', 'bol-', 'lol-', 'tcl-'],
        self::ZONE_VIHO         => ['viho-'],
    ];

    /**
     * Class tokens that start with an owned prefix but belong to a third party.
     *
     * `public/assets/admin/` vendors the "Viho" Bootstrap admin template, which ships
     * `.viho-demo-content`, `.viho-demo-section` and `.vihoAdminConfig`. That theme is loaded only
     * by `layouts/admin.blade.php`; Hire Agent and Create Offer both extend `layouts/main.blade.php`
     * and never load it, and none of its classes appear anywhere under `resources/`. The collision
     * is therefore theoretical — but the shared library is about to claim the `viho-` prefix in
     * earnest, so the disambiguation is recorded here rather than left to be rediscovered.
     *
     * (`vihoAdminConfig` needs no entry: it has no hyphen, so it never matches the `viho-` prefix.)
     *
     * @var array<int, string>
     */
    public const VENDOR_CLASS_TOKENS = [
        'viho-demo-content',
        'viho-demo-section',
    ];

    /**
     * Symbols that must never appear inside the VIHO namespace.
     *
     * VIHO is presentation only. The point is not that these strings are dangerous, but that their
     * presence would mean a decision — who may see this, is this still open, what does the database
     * say — had migrated into a shared component where neither product owns it any more. The
     * proposal-privacy and timer-retirement work of Milestones 2 and 3 lives on exactly such
     * decisions staying in controllers and services.
     *
     * A component may freely RENDER a value it is handed. What it may not do is fetch it, or decide
     * who gets it.
     *
     * @var array<string, array<int, string>>
     */
    public const VIHO_FORBIDDEN_SYMBOLS = [
        // `->where(` covers both an Eloquent builder and a Collection filter. Both are forbidden
        // here for the same reason: inside a SHARED component, narrowing a set of proposals is a
        // visibility decision, and visibility decisions belong to HireAgentProposalAccess in the
        // controller — not to markup that two products share.
        'database access' => ['DB::', 'Eloquent', '::where(', '->where(', '->firstWhere(', '->firstOrFail(', '::query(', '->paginate('],
        'authorization'   => ['Auth::', 'auth()->', 'Gate::', '@can', '@cannot', '->authorize(', 'policy('],
        'proposal access' => ['HireAgentProposalAccess', 'PublicOfferFeedService', 'CompetingBids'],
        'routing'         => ['Route::', 'Controller'],
        'bidding / timer' => ['auction_time', 'countdown', 'Bidding Period', 'bidding ends'],
    ];

    public function __construct(private string $repoRoot)
    {
    }

    // ── zone resolution ──────────────────────────────────────────────────────

    /** The zone that owns a repo-relative path. */
    public function zoneForPath(string $relPath): string
    {
        $normalised = ltrim(str_replace('\\', '/', $relPath), './');

        foreach (self::ZONE_PATH_PREFIXES as $zone => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($normalised, $prefix)) {
                    return $zone;
                }
            }
        }

        return self::ZONE_NEUTRAL;
    }

    /** The zone that owns a CSS class token, or null when the token is unowned or vendored. */
    public function zoneForClassToken(string $token): ?string
    {
        if (in_array($token, self::VENDOR_CLASS_TOKENS, true)) {
            return null;
        }

        foreach (self::ZONE_CSS_PREFIXES as $zone => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($token, $prefix)) {
                    return $zone;
                }
            }
        }

        return null;
    }

    // ── dependency extraction ────────────────────────────────────────────────

    /**
     * Every static presentation dependency in a source string.
     *
     * @return array<int, array{kind: string, target: string, path: string, zone: string}>
     */
    public function dependenciesIn(string $source): array
    {
        $src  = self::stripComments($source);
        $deps = [];

        // @extends('a.b') · @include('a.b') · @includeIf('a.b') · @component('a.b')
        // View name is the first argument.
        if (preg_match_all(
            '/@(extends|include|includeIf|component)\s*\(\s*[\'"]([A-Za-z0-9_.\-\/]+)[\'"]/',
            $src,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $hit) {
                $deps[] = $this->viewDependency($hit[1], $hit[2]);
            }
        }

        // @includeWhen($cond, 'a.b') · @includeUnless($cond, 'a.b') — view name is argument TWO.
        if (preg_match_all(
            '/@(includeWhen|includeUnless)\s*\([^,]*,\s*[\'"]([A-Za-z0-9_.\-\/]+)[\'"]/',
            $src,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $hit) {
                $deps[] = $this->viewDependency($hit[1], $hit[2]);
            }
        }

        // @includeFirst(['a.b', 'c.d']) — every candidate is a real dependency.
        if (preg_match_all('/@includeFirst\s*\(\s*\[(.*?)\]/s', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                if (preg_match_all('/[\'"]([A-Za-z0-9_.\-\/]+)[\'"]/', $hit[1], $names)) {
                    foreach ($names[1] as $name) {
                        $deps[] = $this->viewDependency('includeFirst', $name);
                    }
                }
            }
        }

        // view('a.b') · View::make('a.b')
        if (preg_match_all(
            '/(?:View::make|(?<![\w>$-])view)\s*\(\s*[\'"]([A-Za-z0-9_.\-\/]+)[\'"]/',
            $src,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $hit) {
                $deps[] = $this->viewDependency('view', $hit[1]);
            }
        }

        // <x-foo.bar> component tags. `x-slot` is a slot marker, not a component.
        if (preg_match_all('/<x-([A-Za-z0-9][A-Za-z0-9._-]*)/', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $tag = rtrim($hit[1], '.-');
                if ($tag === 'slot' || $tag === '') {
                    continue;
                }
                $path = 'resources/views/components/' . str_replace('.', '/', $tag) . '.blade.php';
                $deps[] = [
                    'kind'   => 'component',
                    'target' => 'x-' . $tag,
                    'path'   => $path,
                    'zone'   => $this->zoneForPath($path),
                ];
            }
        }

        // PHP imports and fully-qualified references to presentation support classes.
        if (preg_match_all(
            '/(?:use\s+|\\\\)(App\\\\Support\\\\[A-Za-z0-9_\\\\]+)/',
            $src,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $hit) {
                $fqn  = trim($hit[1], '\\');
                $path = 'app/' . str_replace('\\', '/', substr($fqn, strlen('App\\'))) . '.php';
                $deps[] = [
                    'kind'   => 'import',
                    'target' => $fqn,
                    'path'   => $path,
                    'zone'   => $this->zoneForPath($path),
                ];
            }
        }

        // asset('…css') / mix('…js') — stylesheet and script includes.
        if (preg_match_all(
            '/(?:asset|mix)\s*\(\s*[\'"]([^\'"]+\.(?:css|js))[\'"]/',
            $src,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $hit) {
                $deps[] = [
                    'kind'   => 'asset',
                    'target' => $hit[1],
                    'path'   => $hit[1],
                    'zone'   => $this->zoneForPath($hit[1]),
                ];
            }
        }

        return $deps;
    }

    /** @return array{kind: string, target: string, path: string, zone: string} */
    private function viewDependency(string $kind, string $viewName): array
    {
        $path = 'resources/views/' . str_replace('.', '/', $viewName) . '.blade.php';

        return [
            'kind'   => $kind,
            'target' => $viewName,
            'path'   => $path,
            'zone'   => $this->zoneForPath($path),
        ];
    }

    /**
     * Zones referenced through CSS class tokens.
     *
     * Tokens are harvested from `class="…"` attributes and from selectors inside `<style>` blocks
     * only — never from arbitrary text.
     *
     * @return array<int, array{token: string, zone: string}>
     */
    public function cssZoneReferencesIn(string $source): array
    {
        $src    = self::stripComments($source);
        $tokens = [];

        if (preg_match_all('/class\s*=\s*(["\'])(.*?)\1/s', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                // Drop Blade expressions: their contents are PHP, not class tokens.
                $attr = preg_replace('/\{\{.*?\}\}/s', ' ', $hit[2]);
                foreach (preg_split('/\s+/', (string) $attr, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                    $tokens[] = $token;
                }
            }
        }

        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/s', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                if (preg_match_all('/\.([A-Za-z][A-Za-z0-9_-]*)/', $hit[1], $sel)) {
                    foreach ($sel[1] as $token) {
                        $tokens[] = $token;
                    }
                }
            }
        }

        $out = [];
        foreach (array_unique($tokens) as $token) {
            $zone = $this->zoneForClassToken($token);
            if ($zone !== null) {
                $out[] = ['token' => $token, 'zone' => $zone];
            }
        }

        return $out;
    }

    // ── the contract ─────────────────────────────────────────────────────────

    /**
     * Forbidden edges out of one file.
     *
     * Permitted: any zone → neutral, any product → VIHO, and any edge within a zone.
     * Forbidden: Hire Agent ↔ Create Offer in either direction, and VIHO → either product.
     *
     * @return array<int, string> human-readable violations, empty when the file is compliant
     */
    public function violationsIn(string $relPath, string $source): array
    {
        $from = $this->zoneForPath($relPath);

        if ($from === self::ZONE_NEUTRAL) {
            return [];
        }

        $forbidden = match ($from) {
            self::ZONE_HIRE_AGENT   => [self::ZONE_CREATE_OFFER],
            self::ZONE_CREATE_OFFER => [self::ZONE_HIRE_AGENT],
            self::ZONE_VIHO         => [self::ZONE_HIRE_AGENT, self::ZONE_CREATE_OFFER],
            default                 => [],
        };

        $out = [];

        foreach ($this->dependenciesIn($source) as $dep) {
            if (in_array($dep['zone'], $forbidden, true)) {
                $out[] = sprintf(
                    '%s [%s] --%s--> %s [%s]',
                    $relPath,
                    $from,
                    $dep['kind'],
                    $dep['target'],
                    $dep['zone']
                );
            }
        }

        foreach ($this->cssZoneReferencesIn($source) as $ref) {
            if (in_array($ref['zone'], $forbidden, true)) {
                $out[] = sprintf(
                    '%s [%s] --css-class--> .%s [%s]',
                    $relPath,
                    $from,
                    $ref['token'],
                    $ref['zone']
                );
            }
        }

        return $out;
    }

    /**
     * Non-presentation symbols found inside a VIHO file.
     *
     * @return array<int, string>
     */
    public function nonPresentationSymbolsIn(string $relPath, string $source): array
    {
        if ($this->zoneForPath($relPath) !== self::ZONE_VIHO) {
            return [];
        }

        $src = self::stripComments($source);
        $out = [];

        foreach (self::VIHO_FORBIDDEN_SYMBOLS as $category => $symbols) {
            foreach ($symbols as $symbol) {
                if (str_contains($src, $symbol)) {
                    $out[] = sprintf('%s contains %s symbol "%s"', $relPath, $category, $symbol);
                }
            }
        }

        return $out;
    }

    // ── file discovery ───────────────────────────────────────────────────────

    /**
     * Repo-relative paths of every existing file in a zone.
     *
     * @return array<int, string>
     */
    public function filesInZone(string $zone): array
    {
        $out = [];

        foreach (self::ZONE_PATH_PREFIXES[$zone] ?? [] as $prefix) {
            $dir = $this->repoRoot . '/' . rtrim($prefix, '/');
            if (! is_dir($dir)) {
                continue;
            }

            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($items as $item) {
                if (! $item->isFile()) {
                    continue;
                }
                $out[] = ltrim(str_replace($this->repoRoot . '/', '', $item->getPathname()), '/');
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /** Convenience: read a repo-relative file. */
    public function read(string $relPath): string
    {
        return (string) file_get_contents($this->repoRoot . '/' . $relPath);
    }

    public function exists(string $relPath): bool
    {
        return file_exists($this->repoRoot . '/' . $relPath);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Remove comment bodies so documentation prose can never be mistaken for a dependency.
     *
     * This matters concretely in both directions: Hire Agent's framework files DISCUSS Create Offer
     * at length in their header comments (that is where the decoupling rationale is written down),
     * and the four Create Offer views carry long explanatory blocks of their own. A contract that
     * read those as edges would fail on its own documentation.
     *
     * `//` is handled only at the start of a line, so `https://…` inside a URL survives intact.
     */
    public static function stripComments(string $source): string
    {
        $patterns = [
            '/\{\{--.*?--\}\}/s',   // Blade
            '/<!--.*?-->/s',        // HTML
            '/\/\*.*?\*\//s',       // C-style, covers PHPDoc
            '/^\s*\/\/.*$/m',       // whole-line PHP comments only
        ];

        return (string) preg_replace($patterns, ' ', $source);
    }
}
