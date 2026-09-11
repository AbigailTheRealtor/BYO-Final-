<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * GOOGLE_PLACES_ENABLED parses FAIL-CLOSED, whatever an operator types.
 *
 * It used to be `(bool) env(...)`, under which `off`, `no` and any typo are non-empty strings
 * and therefore TRUE — the billable Places API switched ON by somebody trying to switch it
 * off. Each value is evaluated straight from config/google_places.php with the variable set
 * the way the environment sets it.
 *
 * Nothing here reaches a provider. It evaluates a config file.
 */
class GooglePlacesEnabledParsingTest extends TestCase
{
    /** @test */
    public function google_places_is_off_unless_explicitly_switched_on(): void
    {
        foreach ([null, '', 'false', 'FALSE', '0', 'off', 'OFF', 'no', ' No ', 'banana', '2', 'enabled', 'nope'] as $value) {
            $this->assertFalse(
                $this->placesConfig(['GOOGLE_PLACES_ENABLED' => $value])['enabled'],
                'GOOGLE_PLACES_ENABLED=' . var_export($value, true) . ' must leave Google Places OFF'
            );
        }

        foreach (['true', 'TRUE', '1', 'on', 'yes', 'YES', ' true '] as $value) {
            $this->assertTrue(
                $this->placesConfig(['GOOGLE_PLACES_ENABLED' => $value])['enabled'],
                'GOOGLE_PLACES_ENABLED=' . var_export($value, true) . ' must switch Google Places ON'
            );
        }
    }

    /** The existing Nearby Search ceilings are preserved, not reinvented. @test */
    public function the_shipped_nearby_ceilings_are_unchanged(): void
    {
        $config = $this->placesConfig([
            'GOOGLE_PLACES_HOURLY_LIMIT' => null,
            'GOOGLE_PLACES_DAILY_LIMIT'  => null,
        ]);

        $this->assertSame(25, $config['hourly_limit']);
        $this->assertSame(100, $config['daily_limit']);
    }

    /**
     * config/google_places.php as it resolves with the given variables set — or absent, for a
     * null — in every place `env()` reads from, restored afterwards.
     *
     * @param array<string, string|null> $variables
     * @return array<string, mixed>
     */
    private function placesConfig(array $variables): array
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
            return require base_path('config/google_places.php');
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
