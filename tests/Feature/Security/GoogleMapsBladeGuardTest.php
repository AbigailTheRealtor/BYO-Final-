<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Phase 0 item 1 — "no Blade/JS path references `google` unless it exists."
 *
 * A server-side `@if($mapsKey)` is not enough. A credential can be *present and rejected*
 * — revoked, over quota, or blocked by referrer policy — in which case the SDK tag is
 * rendered, Google refuses it, and `google` is never defined. Every entry function that
 * touches `google.maps` must therefore guard at runtime, or the page throws
 * `ReferenceError: google is not defined` and takes the rest of its handler down with it.
 *
 * These are static assertions over Blade source. They are deliberately paranoid about
 * their own validity: `the_scan_finds_the_functions_it_claims_to_check` fails closed if
 * the scanner stops finding anything, because a guard test that cannot fail is worse
 * than no guard test at all (erratum E-41).
 */
class GoogleMapsBladeGuardTest extends TestCase
{
    /** Entry points invoked directly by page handlers, not only as the SDK's own callback. */
    private const ENTRY_FUNCTIONS = ['initialize', 'initializeMap'];

    private const RUNTIME_GUARD = "typeof google === 'undefined'";

    /**
     * Views permitted to contain a literal Maps SDK URL. Everything else must go through
     * a component, so the key, the degraded panel, and gm_authFailure have one home.
     */
    private const SDK_URL_OWNERS = [
        'resources/views/components/google-maps-script.blade.php',
        'resources/views/components/google-maps-deferred-loader.blade.php',
        'resources/views/components/location-dna-map.blade.php',
    ];

    /** Dead, unreferenced backup. No route or include resolves it. */
    private const DEAD_FILES = [
        'resources/views/hire_tenant_agent/add.blade09012024.php',
    ];

    /** @return string[] */
    private function bladeFiles(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.php')) {
                $files[] = $entry->getPathname();
            }
        }

        $this->assertNotEmpty($files);

        return $files;
    }

    private function relative(string $absolute): string
    {
        return ltrim(str_replace(base_path(), '', $absolute), '/');
    }

    /** Strip JS line comments so a commented-out `google.maps.…` never trips the scan. */
    private static function stripJsLineComments(string $source): string
    {
        return preg_replace('#//[^\n]*#', '', $source);
    }

    /**
     * Find each `function <name>(…) { … }` body by brace matching.
     *
     * @return array<int, array{name: string, body: string}>
     */
    private function entryFunctions(string $source): array
    {
        $pattern = '/function\s+(' . implode('|', self::ENTRY_FUNCTIONS) . ')\s*\([^)]*\)\s*\{/';
        $found   = [];

        if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            return $found;
        }

        foreach ($matches[0] as $i => [$text, $offset]) {
            $open  = strpos($source, '{', $offset + strlen($text) - 1);
            $depth = 0;
            $close = null;

            for ($p = $open; $p < strlen($source); $p++) {
                if ($source[$p] === '{') {
                    $depth++;
                } elseif ($source[$p] === '}') {
                    if (--$depth === 0) {
                        $close = $p;
                        break;
                    }
                }
            }

            if ($close !== null) {
                $found[] = ['name' => $matches[1][$i][0], 'body' => substr($source, $open + 1, $close - $open - 1)];
            }
        }

        return $found;
    }

    /** @test */
    public function every_entry_function_that_touches_google_maps_guards_first(): void
    {
        $unguarded = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relative($file);
            if (in_array($relative, self::DEAD_FILES, true)) {
                continue;
            }

            $source = self::stripJsLineComments(file_get_contents($file));
            if (!str_contains($source, 'google.maps')) {
                continue;
            }

            foreach ($this->entryFunctions($source) as $fn) {
                if (!str_contains($fn['body'], 'google.maps')) {
                    continue;
                }

                // The guard must be the FIRST thing the function does — a guard placed
                // after the first google.maps reference protects nothing.
                $firstUse = strpos($fn['body'], 'google.maps');
                $guardAt  = strpos($fn['body'], self::RUNTIME_GUARD);

                if ($guardAt === false || $guardAt > $firstUse) {
                    $unguarded[] = "{$relative}::{$fn['name']}()";
                }
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            "These functions touch google.maps before checking it exists:\n  " . implode("\n  ", $unguarded),
        );
    }

    /** @test */
    public function the_scan_finds_the_functions_it_claims_to_check(): void
    {
        // Fail closed. If the brace matcher or the strip breaks, the assertion above would
        // pass over an empty set and report safety it never verified. E-41, exactly.
        $checked = 0;

        foreach ($this->bladeFiles() as $file) {
            if (in_array($this->relative($file), self::DEAD_FILES, true)) {
                continue;
            }

            $source = self::stripJsLineComments(file_get_contents($file));

            foreach ($this->entryFunctions($source) as $fn) {
                if (str_contains($fn['body'], 'google.maps')) {
                    $checked++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(
            40,
            $checked,
            "The scan found only {$checked} google-touching entry functions; 41 were guarded in Batch 5. "
            . 'The scanner is broken and is not proving anything.',
        );
    }

    /** @test */
    public function only_the_loader_components_hard_code_the_maps_sdk_url(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relative($file);

            if (in_array($relative, self::SDK_URL_OWNERS, true) || in_array($relative, self::DEAD_FILES, true)) {
                continue;
            }

            // Blade comments {{-- … --}} and JS line comments are not live code.
            $source = preg_replace('#\{\{--.*?--\}\}#s', '', file_get_contents($file));
            $source = self::stripJsLineComments($source);

            if (str_contains($source, 'maps.googleapis.com/maps/api/js')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These views hard-code the Maps SDK URL instead of using a loader component:\n  "
            . implode("\n  ", $offenders),
        );
    }

    /** @test */
    public function every_google_maps_view_compiles_to_valid_php(): void
    {
        // Blade parses its directives inside JS comments. A comment reading
        // "// the server-side @if($mapsKey) check" compiles to a real, unterminated
        // `if (...)` and the view 500s at render time — while `artisan view:cache`
        // reports success, because compiling is not executing. That bug shipped into
        // location-dna-map during this very batch and was caught here, not in a browser.
        $compiler = app('blade.compiler');
        $broken   = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relative($file);

            if (in_array($relative, self::DEAD_FILES, true) || !str_ends_with($file, '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file);
            if (!str_contains($source, 'google.maps') && !str_contains($source, 'google-maps')) {
                continue;
            }

            $temp = tempnam(sys_get_temp_dir(), 'blade-lint-');
            file_put_contents($temp, $compiler->compileString($source));
            exec('php -l ' . escapeshellarg($temp) . ' 2>&1', $output, $status);
            unlink($temp);

            if ($status !== 0) {
                $broken[] = $relative . ' — ' . ($output[0] ?? 'parse error');
            }

            $output = [];
        }

        $this->assertSame([], $broken, "These Google-Maps views compile to invalid PHP:\n  " . implode("\n  ", $broken));
    }

    /** @test */
    public function the_degraded_panel_has_exactly_one_definition(): void
    {
        // It used to be copy-pasted into five files. One home means one wording, one fix.
        $definers = [];

        foreach ($this->bladeFiles() as $file) {
            if (str_contains(file_get_contents($file), 'Google Maps is not configured')) {
                $definers[] = $this->relative($file);
            }
        }

        $this->assertSame(['resources/views/components/google-maps-unavailable.blade.php'], $definers);
    }
}
