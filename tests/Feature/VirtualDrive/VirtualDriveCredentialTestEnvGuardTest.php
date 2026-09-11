<?php

namespace Tests\Feature\VirtualDrive;

use Tests\TestCase;

/**
 * No test run may hold a credential that can start a street-level session.
 *
 * A Street View browser key can start a billable Dynamic Street View load and a
 * MapKit JS token spends Apple quota. The browser specs use fakes and need
 * neither, so both are blanked — across getenv(), $_SERVER and $_ENV — by
 * phpunit.xml and tests/bootstrap.php, the same double lock the Google Places
 * key has had since the 2026-07-05 incident.
 */
class VirtualDriveCredentialTestEnvGuardTest extends TestCase
{
    private const CREDENTIALS = ['VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY', 'VIRTUAL_DRIVE_MAPKIT_JS_TOKEN'];

    /** @test */
    public function neither_credential_is_visible_anywhere_in_a_test_run(): void
    {
        foreach (self::CREDENTIALS as $name) {
            $getenv = getenv($name);

            $this->assertTrue($getenv === false || $getenv === '', "{$name} must be blank in getenv()");
            $this->assertTrue(blank($_SERVER[$name] ?? null), "{$name} must be blank in \$_SERVER");
            $this->assertTrue(blank($_ENV[$name] ?? null), "{$name} must be blank in \$_ENV");
        }

        $this->assertTrue(blank(config('virtual_drive.google.browser_key')));
        $this->assertTrue(blank(config('virtual_drive.apple.mapkit_token')));
        $this->assertFalse(config('virtual_drive.proof_enabled'));
    }

    /** @test */
    public function both_the_phpunit_config_and_the_bootstrap_blank_them(): void
    {
        $phpunit   = (string) file_get_contents(base_path('phpunit.xml'));
        $bootstrap = (string) file_get_contents(base_path('tests/bootstrap.php'));

        foreach (self::CREDENTIALS as $name) {
            $this->assertStringContainsString('<server name="' . $name . '" value="" force="true"/>', $phpunit);
            $this->assertMatchesRegularExpression("/'" . $name . "'\s*=>\s*''/", $bootstrap);
        }

        $this->assertStringContainsString('<server name="VIRTUAL_DRIVE_PROOF_ENABLED" value="false" force="true"/>', $phpunit);
    }
}
