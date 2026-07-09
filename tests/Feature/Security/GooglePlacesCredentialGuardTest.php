<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Phase 0 / S1a — the credential guard.
 *
 * The 2026-07-05 incident (38,236 live Nearby Search requests, ~$1,223, six days)
 * was caused by the test suite reaching live Google. `phpunit.xml` already blanked
 * GOOGLE_PLACES_API_KEY, but the `<server>` entries lacked `force="true"`, so PHPUnit
 * would NOT overwrite a real key already present in the process environment — which is
 * exactly the condition on this machine today.
 *
 * These tests fail if that regression is ever reintroduced.
 *
 * @see docs/architecture/SPATIAL-INTELLIGENCE-PLATFORM.md — erratum E-20, INV-11
 * @see docs/investigations/Google-Places-Root-Cause-Analysis.md
 */
class GooglePlacesCredentialGuardTest extends TestCase
{
    /** @test */
    public function the_google_places_api_key_is_blank_under_the_testing_environment(): void
    {
        $this->assertSame('testing', app()->environment());

        $this->assertTrue(
            blank(config('services.google.places_key')),
            'GOOGLE_PLACES_API_KEY must be blank under APP_ENV=testing. If this fails, '
            . 'phpunit.xml has lost force="true" on the <server> entry and a real key from '
            . 'the process environment is leaking into the test suite.',
        );
    }

    /** @test */
    public function phpunit_forces_the_google_env_overrides_rather_than_deferring_to_the_process_environment(): void
    {
        $phpunit = file_get_contents(base_path('phpunit.xml'));

        foreach ([
            'GOOGLE_PLACES_API_KEY',
            'GOOGLE_PLACES_ENABLED',
            'GOOGLE_PLACES_DAILY_LIMIT',
            'GOOGLE_PLACES_HOURLY_LIMIT',
        ] as $var) {
            $this->assertMatchesRegularExpression(
                '/<server name="' . preg_quote($var, '/') . '"[^>]*force="true"/',
                $phpunit,
                "phpunit.xml must set force=\"true\" on {$var}; without it PHPUnit defers to "
                . 'an existing process environment variable and the guard is inert.',
            );
        }
    }

    /** @test */
    public function the_google_places_kill_switch_is_disabled_under_the_testing_environment(): void
    {
        $this->assertFalse(
            config('google_places.enabled'),
            'The Google Places kill switch must be off in tests.',
        );
    }

    /** @test */
    public function the_kill_switch_defaults_to_disabled_when_the_environment_is_silent(): void
    {
        // Fail-safe: an operator must explicitly opt in. Absence never means "on".
        $this->assertFalse(
            (bool) env('GOOGLE_PLACES_ENABLED_DOES_NOT_EXIST', false),
        );
        $this->assertFalse(config('google_places.enabled', false));
    }
}
