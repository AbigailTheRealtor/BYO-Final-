<?php

namespace Tests\Feature\VirtualDrive;

use Tests\TestCase;

/**
 * Source-level guards on the proof's browser code.
 *
 * The proof is only worth anything if it stays inside each provider's
 * documented API and cannot start a billable session by accident. The browser
 * specs (tests/browser/virtual-drive-launch-guard.spec.js) prove the behaviour
 * against fakes; these pin the structure that behaviour rests on, in the suite
 * every pull request runs.
 */
class VirtualDriveProviderIsolationTest extends TestCase
{
    /** Documented MapKit JS members the Apple provider may touch — and only these. */
    private const APPLE_MAPKIT_MEMBERS = ['init', 'addEventListener', 'Coordinate', 'Geocoder', 'LookAround'];

    /** Documented LookAround instance members (MapKit JS 5.79+). */
    private const APPLE_LOOKAROUND_MEMBERS = ['addEventListener', 'readyState', 'scene', 'destroy'];

    private const GOOGLE_LIBRARIES = ['streetView', 'marker', 'geometry', 'core'];

    private const SHELL  = 'public/js/virtual-drive/virtual-drive-shell.js';
    private const APPLE  = 'public/js/virtual-drive/apple-lookaround-provider.js';
    private const GOOGLE = 'public/js/virtual-drive/google-streetview-provider.js';

    private const PROOF_FILES = [
        'config/virtual_drive.php',
        'resources/views/dev/virtual-drive/show.blade.php',
        'resources/views/dev/virtual-drive/compare.blade.php',
        self::SHELL,
        self::APPLE,
        self::GOOGLE,
        'public/js/virtual-drive/virtual-drive-observations.js',
        'public/js/virtual-drive/virtual-drive-compare.js',
        'public/css/virtual-drive/virtual-drive.css',
    ];

    private function source(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /**
     * The executable text only. These files explain themselves at length —
     * which is how a comment naming `getStreetView` or `launch()` would
     * otherwise fail a check that is about what the code DOES.
     */
    private function code(string $path): string
    {
        $source = preg_replace('#/\*.*?\*/#s', '', $this->source($path));

        return (string) preg_replace('#^\s*//.*$#m', '', (string) $source);
    }

    /** The body of `function $name(` up to the next top-level function in the IIFE. */
    private function functionBody(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');

        $this->assertNotFalse($start, "function {$name}() not found");

        $next = strpos($source, "\n    function ", $start + 10);

        return substr($source, $start, $next === false ? null : $next - $start);
    }

    /** @test */
    public function the_apple_provider_touches_only_documented_mapkit_members(): void
    {
        $source = $this->source(self::APPLE);

        preg_match_all('/\bmapkit\.([A-Za-z_$][A-Za-z0-9_$]*)/', $source, $mapkit);
        preg_match_all('/\blookAround\.([A-Za-z_$][A-Za-z0-9_$]*)/', $source, $lookAround);

        $this->assertNotEmpty($mapkit[1]);
        $this->assertSame([], array_values(array_diff(array_unique($mapkit[1]), self::APPLE_MAPKIT_MEMBERS)));
        $this->assertSame([], array_values(array_diff(array_unique($lookAround[1]), self::APPLE_LOOKAROUND_MEMBERS)));

        // Bracket access would sidestep the member check above.
        $this->assertDoesNotMatchRegularExpression('/\b(mapkit|lookAround)\s*\[/', $source);
    }

    /** @test */
    public function the_google_provider_uses_only_street_view_libraries(): void
    {
        $source = $this->source(self::GOOGLE);

        preg_match_all("/importLibrary\\('([A-Za-z]+)'\\)/", $source, $libraries);

        $this->assertSame(self::GOOGLE_LIBRARIES, $libraries[1]);
        $this->assertStringNotContainsString('libraries=', $source);
        $this->assertDoesNotMatchRegularExpression('/importLibrary\(\s*[\'"]places[\'"]|google\.maps\.places|\.places\./i', $source);

        // No Dynamic Maps load.
        $this->assertStringNotContainsString('google.maps.Map(', $source);
        $this->assertStringNotContainsString('lib.core.Map', $source);
    }

    /**
     * One billable panorama per page: a declared ceiling, one construction site,
     * and nothing that could rebuild or retry on its own.
     *
     * @test
     */
    public function the_google_provider_can_construct_at_most_one_panorama(): void
    {
        $source = $this->code(self::GOOGLE);

        $this->assertStringContainsString('var MAX_PANORAMAS_PER_PAGE = 1;', $source);
        $this->assertSame(1, substr_count($source, 'new lib.sv.StreetViewPanorama('));
        $this->assertStringContainsString('new lib.sv.StreetViewPanorama(', $this->functionBody($source, 'constructPanorama'));
        $this->assertStringContainsString('diag.panoramaConstructions >= MAX_PANORAMAS_PER_PAGE', $this->functionBody($source, 'constructPanorama'));

        foreach (['setInterval', 'setTimeout', 'location.reload', 'getStreetView'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, "google provider must not use {$forbidden}");
        }
    }

    /**
     * launch() is the only way a provider library is ever requested, and nothing
     * calls launch() except the launch button.
     *
     * @test
     */
    public function the_shell_loads_a_provider_only_from_the_launch_button(): void
    {
        $shell = $this->code(self::SHELL);

        $this->assertSame(1, substr_count($shell, '.load(cfg, hooks)'));
        $this->assertStringContainsString('.load(cfg, hooks)', $this->functionBody($shell, 'launch'));

        // Defined once, wired to the button once, exposed once for the specs — and never called.
        $this->assertSame(1, preg_match_all('/function launch\(\)/', $shell));
        $this->assertSame(1, substr_count($shell, "addEventListener('click', launch)"));
        $this->assertSame(0, preg_match_all('/(?<!function )\blaunch\(\)/', $shell));

        // The URL may carry the selected home; nothing reads a launch from it.
        $this->assertStringNotContainsString("searchParams.get('launch')", $shell);
        $this->assertStringNotContainsString('autoLaunch', $shell);
    }

    /** @test */
    public function the_providers_do_not_reach_into_each_other(): void
    {
        $apple  = strtolower($this->source(self::APPLE));
        $google = strtolower($this->source(self::GOOGLE));
        $shell  = strtolower($this->source(self::SHELL));

        $this->assertStringNotContainsString('google', $apple);
        $this->assertStringNotContainsString('mapkit', $google);

        // The shell reads capabilities; it never loads or names a vendor library.
        foreach (['mapkit', 'googleapis', 'google.maps'] as $vendor) {
            $this->assertStringNotContainsString($vendor, $shell);
        }
    }

    /** @test */
    public function the_observation_sheet_and_comparison_page_send_nothing_to_a_provider(): void
    {
        $observations = $this->source('public/js/virtual-drive/virtual-drive-observations.js');
        $compare      = $this->source('public/js/virtual-drive/virtual-drive-compare.js');

        foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', 'WebSocket'] as $network) {
            $this->assertStringNotContainsString($network, $observations);
        }

        foreach (['mapkit', 'googleapis', 'google.maps', 'sendBeacon'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($compare));
        }
    }

    /** @test */
    public function no_credential_is_committed_in_any_proof_file(): void
    {
        foreach (self::PROOF_FILES as $path) {
            $source = $this->source($path);

            $this->assertDoesNotMatchRegularExpression('/AIza[0-9A-Za-z_\-]{30,}/', $source, "{$path} contains a Google key");
            $this->assertDoesNotMatchRegularExpression('/eyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\./', $source, "{$path} contains a JWT");
        }
    }

    /** @test */
    public function no_proof_script_writes_listing_data_as_html(): void
    {
        foreach (self::PROOF_FILES as $path) {
            if (str_ends_with($path, '.js')) {
                $this->assertStringNotContainsString('innerHTML', $this->source($path), $path);
            }
        }
    }
}
