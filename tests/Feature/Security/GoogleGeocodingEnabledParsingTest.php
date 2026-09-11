<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * GOOGLE_GEOCODING_ENABLED parses FAIL-CLOSED, whatever an operator types — and nothing else
 * switches Geocoding on.
 *
 * Same contract as GOOGLE_PLACES_ENABLED ({@see GooglePlacesEnabledParsingTest}): a `(bool)`
 * cast reads `off`, `no` and any typo as TRUE, which is the wrong answer for the switch an
 * operator reaches for mid-incident. Each value is evaluated straight from
 * config/google_geocoding.php with the variable set the way the environment sets it.
 *
 * Nothing here reaches a provider. It evaluates a config file.
 */
class GoogleGeocodingEnabledParsingTest extends TestCase
{
    /** @test */
    public function google_geocoding_is_off_unless_explicitly_switched_on(): void
    {
        foreach ([null, '', 'false', 'FALSE', '0', 'off', 'OFF', 'no', ' No ', 'banana', '2', 'enabled', 'nope'] as $value) {
            $this->assertFalse(
                $this->geocodingConfig(['GOOGLE_GEOCODING_ENABLED' => $value])['enabled'],
                'GOOGLE_GEOCODING_ENABLED=' . var_export($value, true) . ' must leave Google Geocoding OFF'
            );
        }

        foreach (['true', 'TRUE', '1', 'on', 'yes', 'YES', ' true '] as $value) {
            $this->assertTrue(
                $this->geocodingConfig(['GOOGLE_GEOCODING_ENABLED' => $value])['enabled'],
                'GOOGLE_GEOCODING_ENABLED=' . var_export($value, true) . ' must switch Google Geocoding ON'
            );
        }
    }

    /** A key, or the Places switch, is not permission to geocode. @test */
    public function neither_a_key_nor_the_places_switch_turns_geocoding_on(): void
    {
        $config = $this->geocodingConfig([
            'GOOGLE_GEOCODING_ENABLED' => null,
            'GOOGLE_PLACES_API_KEY'    => 'fake-test-key',
            'GOOGLE_PLACES_ENABLED'    => 'true',
        ]);

        $this->assertFalse($config['enabled']);
    }

    /** The approved ceilings: 25 an hour, 100 a day. @test */
    public function the_shipped_geocoding_ceilings_are_25_an_hour_and_100_a_day(): void
    {
        $config = $this->geocodingConfig([
            'GOOGLE_GEOCODING_HOURLY_LIMIT' => null,
            'GOOGLE_GEOCODING_DAILY_LIMIT'  => null,
        ]);

        $this->assertSame(25, $config['hourly_limit']);
        $this->assertSame(100, $config['daily_limit']);
    }

    /** A malformed ceiling is a ceiling of zero — it blocks, never unleashes. @test */
    public function a_malformed_ceiling_is_zero(): void
    {
        $config = $this->geocodingConfig([
            'GOOGLE_GEOCODING_HOURLY_LIMIT' => 'lots',
            'GOOGLE_GEOCODING_DAILY_LIMIT'  => 'unlimited',
        ]);

        $this->assertSame(0, $config['hourly_limit']);
        $this->assertSame(0, $config['daily_limit']);
    }

    /**
     * config/google_geocoding.php as it resolves with the given variables set — or absent, for
     * a null — in every place `env()` reads from, restored afterwards.
     *
     * @param array<string, string|null> $variables
     * @return array<string, mixed>
     */
    private function geocodingConfig(array $variables): array
    {
        $saved = [];

        foreach ($variables as $name => $value) {
            $saved[$name] = [
                'env'    => array_key_exists($name, $_ENV) ? $_ENV[$name] : null,
                'hadEnv' => array_key_exists($name, $_ENV),
                'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
                'hadSrv' => array_key_exists($name, $_SERVER),
                'putenv' => getenv($name),
            ];

            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            if ($value !== null) {
                $_ENV[$name]    = $value;
                $_SERVER[$name] = $value;
                putenv("{$name}={$value}");
            }
        }

        try {
            return require base_path('config/google_geocoding.php');
        } finally {
            foreach ($saved as $name => $was) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);

                if ($was['hadEnv']) {
                    $_ENV[$name] = $was['env'];
                }

                if ($was['hadSrv']) {
                    $_SERVER[$name] = $was['server'];
                }

                if ($was['putenv'] !== false) {
                    putenv("{$name}={$was['putenv']}");
                }
            }
        }
    }
}
