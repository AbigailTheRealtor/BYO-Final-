<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Rules;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Phase 1b — the inertness guard for `App\Services\LocationDna\Criteria\Rules`.
 *
 * WHAT PHASE 1a ALREADY COVERS, AND WHY THAT FILE IS UNTOUCHED
 * -----------------------------------------------------------
 * `Phase1aCriteriaInertnessGuardTest` walks `app/Services/LocationDna/Criteria` RECURSIVELY, so
 * every file Phase 1b adds is already subject to its write ban, its no-persistence-layer ban, its
 * no-Livewire ban and its SQLite-portability ban — with no edit to that file. That was the reason
 * for putting the rules inside the guarded namespace (D1), and leaving the Phase 1a guard
 * byte-unchanged keeps it usable as standing evidence for the commit that introduced it.
 *
 * WHAT THIS FILE ADDS
 * -------------------
 * Only the boundaries Phase 1a could not have known about:
 *
 *   1. The rules layer has no write-shaped method name and no forbidden collaborator.
 *   2. It does NOT reference the canonical contract layer — pinning decision D2, which chose a
 *      separate GeographyTier enum over reusing the contract's Dimension enum.
 *   3. The four files Phase 1b was explicitly forbidden to touch are byte-identical.
 *   4. Phase 1b remains unreachable: no controller, view, route, Livewire component or migration.
 *   5. The flag still ships OFF.
 */
class Phase1bCriteriaRulesInertnessGuardTest extends TestCase
{
    private const RULES_DIR = 'app/Services/LocationDna/Criteria/Rules';

    /** The Phase 1a namespace, whose own guard already covers everything beneath it. */
    private const CRITERIA_DIR = 'app/Services/LocationDna/Criteria';

    /**
     * Files Phase 1b was explicitly forbidden to modify, pinned by content hash as of 0139c9352.
     *
     * A hash is used rather than a substring probe because the instruction was "do not modify",
     * not "keep these particular lines" — only a whole-file digest actually asserts that. If a
     * later phase is authorised to change one of these, updating the hash here is a deliberate,
     * reviewable one-line act, which is the intent.
     */
    private const FROZEN = [
        'app/Http/Controllers/BuyerCriteriaAuctionController.php'
            => 'b86beb7ef94a16ca52cb216ff81d57a64d450fe6',
        'app/Http/Controllers/TenantCriteriaAuctionController.php'
            => '0f8722378719f5591fbb3525e60d7421060b71ee',
        'app/Services/LocationDna/Persistence/LocationDnaPersistenceService.php'
            => 'cd7873b4eaf6125e0e3c8ede68e9b4070cef8d31',
        'app/Services/LocationDna/Persistence/LegacyMirrorProjection.php'
            => '087f5fdcf8d65c1419b4ffa6736f9643e65165f6',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 6);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->root().'/'.$relative);
    }

    /** Source with comments and docblocks stripped, so prose can never satisfy an assertion. */
    private function codeOnly(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= $token[1];
                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    /**
     * @param  list<string>  $dirs
     * @return list<string>
     */
    private function phpFilesUnder(array $dirs): array
    {
        $files = [];

        foreach ($dirs as $dir) {
            $base = $this->root().'/'.$dir;

            if (! is_dir($base)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = ltrim(str_replace($this->root(), '', $file->getPathname()), '/');
                }
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function rulesFiles(): array
    {
        return $this->phpFilesUnder([self::RULES_DIR]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE RULES LAYER CANNOT WRITE, AND DOES NOT LOOK LIKE IT MIGHT
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_rules_namespace_exists_and_is_covered_by_the_phase_1a_guard(): void
    {
        $this->assertNotEmpty($this->rulesFiles());

        $guard = $this->read('tests/Unit/Services/LocationDna/Criteria/Phase1aCriteriaInertnessGuardTest.php');

        $this->assertStringContainsString(
            "'".self::CRITERIA_DIR."'",
            $guard,
            'the Phase 1a guard must still scan the whole namespace — that recursion is what makes '
            .'the Phase 1b files inherit its write ban'
        );
        $this->assertStringContainsString(
            'RecursiveIteratorIterator',
            $guard,
            'the Phase 1a guard must still recurse; a non-recursive walk would silently stop '
            .'covering the Rules subdirectory'
        );
    }

    /**
     * No method in the rules layer is even NAMED like a write.
     *
     * Phase 1a bans the call shapes. This bans the vocabulary, which is the earlier warning sign:
     * a method called `persistSelection` is a design gone wrong before a single line of it writes
     * anything.
     */
    public function test_no_method_in_the_rules_layer_is_named_like_a_write(): void
    {
        foreach ($this->rulesFiles() as $relative) {
            preg_match_all(
                '/function\s+([A-Za-z_][A-Za-z0-9_]*)/',
                $this->codeOnly($this->read($relative)),
                $matches
            );

            foreach ($matches[1] as $method) {
                foreach (['save', 'persist', 'write', 'store', 'insert', 'delete', 'flush'] as $verb) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $verb,
                        $method,
                        "{$relative}::{$method}() is named like a write — Phase 1b is read-only."
                    );
                }
            }
        }
    }

    /** The rules layer reaches no framework surface: no controller, request, model or component. */
    public function test_the_rules_layer_reaches_no_framework_surface(): void
    {
        foreach ($this->rulesFiles() as $relative) {
            $code = $this->codeOnly($this->read($relative));

            foreach ([
                'Illuminate\\Http',
                'Illuminate\\Database',
                'Illuminate\\Support\\Facades',
                'App\\Http\\',
                'App\\Models\\',
                'Livewire',
                'DB::',
                'request(',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $code,
                    "{$relative} references `{$forbidden}` — the rules layer is pure domain logic "
                    .'over the read repository and nothing else.'
                );
            }
        }
    }

    /**
     * D2, pinned: the rules layer does not reference the canonical contract layer.
     *
     * That layer's own enum already names these four tiers, and reusing it is the obvious refactor.
     * It is also the exact thing the Phase 1a guard bans, because reaching the contract layer is
     * the first step by which a read layer becomes a write layer. This asserts the duplication is
     * still deliberate.
     */
    public function test_the_rules_layer_defines_its_own_tier_vocabulary(): void
    {
        foreach ($this->rulesFiles() as $relative) {
            $this->assertStringNotContainsString(
                'Dimension',
                $this->codeOnly($this->read($relative)),
                "{$relative} reaches for the contract layer's tier enum — D2 chose a separate one."
            );
        }

        $this->assertFileExists(
            $this->root().'/'.self::RULES_DIR.'/GeographyTier.php',
            'the separate tier enum D2 approved must exist'
        );
    }

    /** The rules classes depend only on the read repository (D3). */
    public function test_the_rules_classes_depend_only_on_the_read_repository(): void
    {
        foreach (['GeographySelectionResolver', 'GeographySelectionValidator'] as $class) {
            $constructor = new ReflectionMethod(
                "App\\Services\\LocationDna\\Criteria\\Rules\\{$class}",
                '__construct'
            );

            $types = array_map(
                static fn (ReflectionParameter $p): string => (string) $p->getType(),
                $constructor->getParameters()
            );

            $this->assertSame(
                ['App\\Services\\LocationDna\\Criteria\\CriteriaGeographyRepository'],
                $types,
                "{$class} must take the read repository and nothing else."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE FORBIDDEN FILES ARE BYTE-IDENTICAL
    // ═════════════════════════════════════════════════════════════════════════

    public function test_the_files_phase_1b_was_forbidden_to_touch_are_unchanged(): void
    {
        foreach (self::FROZEN as $relative => $expected) {
            $this->assertSame(
                $expected,
                sha1_file($this->root().'/'.$relative),
                "{$relative} was modified. Phase 1b was explicitly forbidden to touch it."
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · PHASE 1b IS STILL UNREACHABLE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Nothing outside the Criteria namespace references the rules layer.
     *
     * The whole Phase 1a namespace is skipped, not just the Rules subdirectory: GeographyOption's
     * docblock legitimately points at the selection DTO to explain why option identity and
     * selection identity differ, and a docblock wires nothing.
     */
    public function test_nothing_outside_the_namespace_references_the_rules_layer(): void
    {
        $offenders = [];

        foreach ($this->phpFilesUnder(['app', 'routes', 'database', 'config', 'resources/views']) as $relative) {
            if (str_starts_with($relative, self::CRITERIA_DIR)) {
                continue;
            }

            if (str_contains($this->codeOnly($this->read($relative)), 'Criteria\\Rules')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Phase 1b must be referenced by nothing. Wiring it is Phase 1c. Found: '
            .implode(', ', $offenders)
        );
    }

    public function test_phase_1b_added_no_migration(): void
    {
        foreach ($this->phpFilesUnder(['database/migrations']) as $relative) {
            $this->assertStringNotContainsString(
                'Criteria\\Rules',
                $this->read($relative),
                'Phase 1b required no schema change.'
            );
        }
    }

    public function test_the_feature_flag_still_ships_disabled(): void
    {
        $config = require $this->root().'/config/criteria_location_dna.php';

        $this->assertFalse($config['geography_preview_enabled']);
        $this->assertSame('eloquent', $config['geography_source']);
    }

    /** No map work: Phase 1b touches neither the widget nor its replacement. */
    public function test_phase_1b_introduced_no_map_work(): void
    {
        foreach ($this->rulesFiles() as $relative) {
            $source = strtolower($this->read($relative));

            $this->assertStringNotContainsString('maplibre', $source, "{$relative} references MapLibre — not this phase.");
            $this->assertStringNotContainsString('geojson', $source, "{$relative} references GeoJSON — geometry is a later phase.");
            $this->assertStringNotContainsString('polygon', $source, "{$relative} references polygons — geometry is a later phase.");
        }
    }
}
