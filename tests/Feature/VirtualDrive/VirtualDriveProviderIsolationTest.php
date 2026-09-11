<?php

namespace Tests\Feature\VirtualDrive;

use Tests\TestCase;

/**
 * Source-level guards on the two provider scripts.
 *
 * The proof is only worth anything if it stays inside each provider's
 * documented API — a demo that "works" by reading an undocumented camera
 * object proves nothing about what can be shipped. These tests pin the
 * boundary at the source, since no PHP test can run the browser code.
 */
class VirtualDriveProviderIsolationTest extends TestCase
{
    /** Documented MapKit JS members the Apple provider may touch — and only these. */
    private const APPLE_MAPKIT_MEMBERS = ['init', 'addEventListener', 'Coordinate', 'Geocoder', 'LookAround'];

    /** Documented LookAround instance members (MapKit JS 5.79+). */
    private const APPLE_LOOKAROUND_MEMBERS = ['addEventListener', 'readyState', 'scene', 'destroy'];

    private const GOOGLE_LIBRARIES = ['streetView', 'marker', 'geometry', 'core'];

    private const PROOF_FILES = [
        'config/virtual_drive.php',
        'resources/views/dev/virtual-drive/show.blade.php',
        'public/js/virtual-drive/virtual-drive-shell.js',
        'public/js/virtual-drive/apple-lookaround-provider.js',
        'public/js/virtual-drive/google-streetview-provider.js',
        'public/css/virtual-drive/virtual-drive.css',
    ];

    private function source(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /** @test */
    public function the_apple_provider_touches_only_documented_mapkit_members(): void
    {
        $source = $this->source('public/js/virtual-drive/apple-lookaround-provider.js');

        preg_match_all('/\bmapkit\.([A-Za-z_$][A-Za-z0-9_$]*)/', $source, $mapkit);
        preg_match_all('/\blookAround\.([A-Za-z_$][A-Za-z0-9_$]*)/', $source, $lookAround);

        $this->assertNotEmpty($mapkit[1]);
        $this->assertSame([], array_values(array_diff(array_unique($mapkit[1]), self::APPLE_MAPKIT_MEMBERS)));
        $this->assertSame([], array_values(array_diff(array_unique($lookAround[1]), self::APPLE_LOOKAROUND_MEMBERS)));

        // Bracket access would sidestep the member check above.
        $this->assertDoesNotMatchRegularExpression('/\b(mapkit|lookAround)\s*\[/', $source);
    }

    /** @test */
    public function the_google_provider_uses_only_street_view_libraries_and_one_panorama(): void
    {
        $source = $this->source('public/js/virtual-drive/google-streetview-provider.js');

        preg_match_all("/importLibrary\\('([A-Za-z]+)'\\)/", $source, $libraries);

        $this->assertSame(self::GOOGLE_LIBRARIES, $libraries[1]);
        $this->assertStringNotContainsString('libraries=', $source);
        $this->assertDoesNotMatchRegularExpression('/importLibrary\(\s*[\'"]places[\'"]|google\.maps\.places|\.places\./i', $source);

        // One billable panorama object per page, and no Dynamic Maps load.
        $this->assertSame(1, substr_count($source, 'new lib.sv.StreetViewPanorama('));
        $this->assertStringNotContainsString('google.maps.Map(', $source);
        $this->assertStringNotContainsString('new lib.core.Map', $source);
    }

    /** @test */
    public function the_providers_do_not_reach_into_each_other(): void
    {
        $apple  = strtolower($this->source('public/js/virtual-drive/apple-lookaround-provider.js'));
        $google = strtolower($this->source('public/js/virtual-drive/google-streetview-provider.js'));
        $shell  = strtolower($this->source('public/js/virtual-drive/virtual-drive-shell.js'));

        $this->assertStringNotContainsString('google', $apple);
        $this->assertStringNotContainsString('mapkit', $google);

        // The shell reads capabilities; it never loads or names a vendor library.
        foreach (['mapkit', 'googleapis', 'google.maps'] as $vendor) {
            $this->assertStringNotContainsString($vendor, $shell);
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
    public function no_proof_file_writes_listing_data_as_html(): void
    {
        foreach (self::PROOF_FILES as $path) {
            if (str_ends_with($path, '.js')) {
                $this->assertStringNotContainsString('innerHTML', $this->source($path), $path);
            }
        }
    }
}
