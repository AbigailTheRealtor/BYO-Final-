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
        self::SIGNS,
        'public/css/virtual-drive/virtual-drive.css',
    ];

    private const SIGNS = 'public/js/virtual-drive/virtual-drive-signs.js';

    /**
     * The sign rules — what a sign says, how big it is, which listings share a
     * building — are pure. They decide what a shopper sees; they must not be able
     * to fetch anything, and both providers and the shell depend on them.
     *
     * @test
     */
    public function the_sign_rules_are_pure(): void
    {
        $source = $this->code(self::SIGNS);

        foreach (['fetch(', 'XMLHttpRequest', 'importLibrary', 'createElement', 'innerHTML', 'setTimeout', 'setInterval'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, "the sign rules must not use {$forbidden}");
        }

        foreach (['google', 'mapkit', 'streetview'] as $vendor) {
            $this->assertStringNotContainsString($vendor, strtolower($source), "the sign rules must not name {$vendor}");
        }

        // A sign that cannot be read is not a sign: the floor, the ceiling and the
        // "hide it instead" distance are part of the contract, not a magic number.
        $this->assertStringContainsString('maxDistance:', $source);
        $this->assertStringContainsString('farWidth:', $source);
        $this->assertStringContainsString('nearWidth:', $source);
    }

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

    /** The text between two markers, or null when the opening marker is absent. */
    private function between(string $haystack, string $open, string $close): ?string
    {
        $start = strpos($haystack, $open);

        if ($start === false) {
            return null;
        }

        $end = strpos($haystack, $close, $start);

        return $end === false ? null : substr($haystack, $start, $end - $start + strlen($close));
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

    /**
     * MapKit JS 6 declares `location?: CoordinateData | Place | LookAroundScene`,
     * so the stored MLS coordinate is handed to Look Around directly. A service
     * call happens only in the opt-in Place mode, and readiness comes from the
     * documented readyState getter rather than an event v6 no longer names.
     *
     * @test
     */
    public function the_apple_provider_opens_look_around_from_the_stored_mls_coordinate(): void
    {
        $code = $this->code(self::APPLE);

        $this->assertSame('https://cdn.apple-mapkit.com/mk/6/mapkit.core.js', config('virtual_drive.apple.library_url'));
        $this->assertStringContainsString('{ latitude: listing.latitude, longitude: listing.longitude }', $this->functionBody($code, 'locationFor'));
        $this->assertStringContainsString("var mode = 'coordinate';", $code);

        // A Coordinate instance and the Geocoder exist only for opt-in Place mode.
        $placeFor = $this->functionBody($code, 'placeFor');

        $this->assertSame(substr_count($code, 'new mapkit.Coordinate('), substr_count($placeFor, 'new mapkit.Coordinate('));
        $this->assertSame(substr_count($code, 'new mapkit.Geocoder('), substr_count($placeFor, 'new mapkit.Geocoder('));
        $this->assertStringNotContainsString('PlaceLookup', $code);

        $this->assertDoesNotMatchRegularExpression("/addEventListener\\(\\s*'(load|readystatechange)'/", $code);
        $this->assertStringContainsString('lookAround.readyState', $code);
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

    /**
     * The kill switch and the daily ceiling are structural, not conventional.
     *
     * The ordering is the property: the allowance must be claimed BEFORE
     * provider.load(), or a refused launch has already fetched the billed
     * library. Asserted on the text of launch() because that ordering is the
     * whole protection and a later edit could reverse it while every behavioural
     * spec still passed against a faked endpoint.
     *
     * @test
     */
    public function the_shell_claims_the_daily_allowance_before_it_loads_a_provider(): void
    {
        $shell  = $this->code(self::SHELL);
        $launch = $this->functionBody($shell, 'launch');

        $claim = strpos($launch, 'claimDailyAllowance()');
        $load  = strpos($launch, '.load(cfg, hooks)');

        $this->assertNotFalse($claim, 'launch() must claim the daily allowance');
        $this->assertNotFalse($load);
        $this->assertLessThan($load, $claim, 'the allowance must be claimed BEFORE the provider library is requested');

        // A switched-off provider can never reach the load at all.
        $this->assertStringContainsString('!cfg.providerEnabled', $launch);

        // One attempt per press: nothing schedules a second claim.
        $claimBody = $this->functionBody($shell, 'claimDailyAllowance');

        foreach (['setTimeout', 'setInterval', 'location.reload'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $claimBody, "the claim must not {$forbidden}");
        }

        // Declared once, called once, and only from launch().
        $this->assertSame(1, preg_match_all('/function claimDailyAllowance\(\)/', $shell));
        $this->assertSame(1, preg_match_all('/(?<!function )\bclaimDailyAllowance\(\)/', $shell));

        // A refused claim is terminal.
        $this->assertStringContainsString('err.refused', $launch);
        $this->assertStringContainsString('lock(err.message', $launch);
    }

    /**
     * The switched-off page must not merely hide the Google provider — the file
     * that can construct a panorama must be absent. That is a `@if` in the
     * template, and this pins it at source alongside the rendered-page assertions
     * in VirtualDriveGoogleKillSwitchTest.
     *
     * @test
     */
    public function the_provider_script_tag_is_conditional_in_the_template(): void
    {
        $blade = $this->source('resources/views/dev/virtual-drive/show.blade.php');

        $conditional = $this->between($blade, '@if ($providerScript)', '@endif');

        $this->assertNotNull($conditional, 'the provider <script> must be inside @if ($providerScript)');
        $this->assertStringContainsString('$providerScript', $conditional);
        $this->assertStringContainsString('<script src=', $conditional);

        // And $providerScript is referenced NOWHERE outside that block, so there is
        // no second, unconditional way for the provider file to reach the page.
        $this->assertSame(
            substr_count($blade, '$providerScript'),
            substr_count($conditional, '$providerScript'),
            '$providerScript must be referenced only inside @if ($providerScript)'
        );

        // The Google browser key has no path into the markup at all.
        $this->assertStringNotContainsString('google.browser_key', $blade);
        $this->assertStringNotContainsString('google.browser_key', $this->source('resources/views/dev/virtual-drive/compare.blade.php'));
    }

    /**
     * The gate and the ledger decide whether Google may run. Neither may hold a
     * provider client, name a provider host, or reach the network to find out.
     *
     * @test
     */
    public function the_google_gate_and_ledger_contact_nobody(): void
    {
        foreach ([
            'app/Support/VirtualDrive/VirtualDriveGoogleGate.php',
            'app/Support/VirtualDrive/VirtualDriveGoogleLaunchLedger.php',
            'app/Http/Controllers/Dev/VirtualDriveGoogleLaunchController.php',
            'app/Support/VirtualDrive/VirtualDriveGoogleAuthBlock.php',
            'app/Http/Controllers/Dev/VirtualDriveGoogleAuthFailureController.php',
            'app/Console/Commands/VirtualDriveGoogleAuthBlockCommand.php',
        ] as $path) {
            $source = $this->source($path);

            foreach ([
                'Http::',
                'GuzzleHttp',
                'file_get_contents',
                'curl_',
                'googleapis.com',
                'maps.googleapis',
                'BridgeApiService',
            ] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$path} must not use {$forbidden}");
            }

            $this->assertDoesNotMatchRegularExpression('/AIza[0-9A-Za-z_\-]{30,}/', $source, "{$path} contains a Google key");
        }
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
