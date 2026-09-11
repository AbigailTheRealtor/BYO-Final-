<?php

namespace Tests\Feature\Explore;

use App\Services\Explore\ExploreGoogleConfig;
use App\Services\Explore\Guards\ExploreProviderBudget;
use Tests\TestCase;

/**
 * The provider-safety switches parse FAIL-SAFE, whatever an operator types.
 *
 * WHY THIS NEEDS ITS OWN TEST
 * ---------------------------
 * `(bool) env(...)` reads `off` and `no` as TRUE — they are non-empty strings.
 * For an enable flag that turns a provider ON when somebody meant OFF. These
 * are the switches an operator reaches for mid-incident, so each is evaluated
 * here against the values people actually type, straight from
 * config/explore.php, with the variable set exactly as the environment would
 * set it.
 *
 * Nothing here reaches a provider. It evaluates a config file.
 */
class ExploreProviderFlagParsingTest extends TestCase
{
    /** Values that must read as OFF for an enable flag and as NOT TRIPPED for the kill switch. */
    private const FALSE_VALUES = [null, '', 'false', 'FALSE', '0', 'off', 'OFF', 'no', ' No '];

    /** Values that must read as ON / TRIPPED. */
    private const TRUE_VALUES = ['true', 'TRUE', '1', 'on', 'yes', 'YES', ' true '];

    /** Values that mean nothing — an enable flag must read them OFF, the kill switch TRIPPED. */
    private const MALFORMED = ['banana', '2', 'enabled', 'disable', 'nope'];

    /** @test */
    public function google_3d_is_off_unless_explicitly_switched_on(): void
    {
        foreach ([...self::FALSE_VALUES, ...self::MALFORMED] as $value) {
            $this->assertFalse(
                $this->exploreConfig('EXPLORE_GOOGLE_3D_ENABLED', $value)['google']['enabled'],
                'EXPLORE_GOOGLE_3D_ENABLED=' . var_export($value, true) . ' must leave Google OFF'
            );
        }

        foreach (self::TRUE_VALUES as $value) {
            $this->assertTrue(
                $this->exploreConfig('EXPLORE_GOOGLE_3D_ENABLED', $value)['google']['enabled'],
                'EXPLORE_GOOGLE_3D_ENABLED=' . var_export($value, true) . ' must switch Google ON'
            );
        }
    }

    /** @test */
    public function discovery_is_off_unless_explicitly_switched_on(): void
    {
        foreach ([...self::FALSE_VALUES, ...self::MALFORMED] as $value) {
            $this->assertFalse(
                $this->exploreConfig('EXPLORE_DISCOVERY_ENABLED', $value)['discovery']['enabled'],
                'EXPLORE_DISCOVERY_ENABLED=' . var_export($value, true) . ' must leave Bridge discovery OFF'
            );
        }

        foreach (self::TRUE_VALUES as $value) {
            $this->assertTrue(
                $this->exploreConfig('EXPLORE_DISCOVERY_ENABLED', $value)['discovery']['enabled'],
                'EXPLORE_DISCOVERY_ENABLED=' . var_export($value, true) . ' must switch discovery ON'
            );
        }
    }

    /** @test */
    public function the_provider_kill_switch_fails_toward_blocking(): void
    {
        foreach (self::FALSE_VALUES as $value) {
            $this->assertFalse(
                $this->exploreConfig('EXPLORE_PROVIDER_KILL_SWITCH', $value)['provider_budget']['kill_switch'],
                'EXPLORE_PROVIDER_KILL_SWITCH=' . var_export($value, true) . ' is an explicit or absent false'
            );
        }

        foreach ([...self::TRUE_VALUES, ...self::MALFORMED] as $value) {
            $this->assertTrue(
                $this->exploreConfig('EXPLORE_PROVIDER_KILL_SWITCH', $value)['provider_budget']['kill_switch'],
                'EXPLORE_PROVIDER_KILL_SWITCH=' . var_export($value, true) . ' must TRIP the kill switch'
            );
        }
    }

    /** @test */
    public function the_shipped_global_ceilings_are_the_controlled_launch_values(): void
    {
        $budget = $this->exploreConfig('EXPLORE_PROVIDER_GLOBAL_HOURLY', null)['provider_budget'];

        $this->assertSame(300, $budget['global_hourly']);
        $this->assertSame(2000, $budget['global_daily']);
        $this->assertSame(60, $budget['actor_hourly']);
        $this->assertSame(300, $budget['actor_daily']);
    }

    /**
     * The runtime readers re-assert the same posture for a value that arrived
     * some other way than config/explore.php — so neither switch can be
     * disarmed by a config value that merely looks right.
     *
     * @test
     */
    public function the_runtime_readers_fail_safe_too(): void
    {
        foreach (['on', 'yes', 'true', 1, null] as $value) {
            config(['explore.google.enabled' => $value]);
            $this->assertFalse((new ExploreGoogleConfig())->enabled(), 'only a real boolean true enables Google');
        }

        config(['explore.google.enabled' => true]);
        $this->assertTrue((new ExploreGoogleConfig())->enabled());

        foreach (['off', 'no', 0, '0', null, 'banana'] as $value) {
            config(['explore.provider_budget.kill_switch' => $value]);
            $this->assertTrue((new ExploreProviderBudget())->killed(), 'anything but an explicit false trips the kill switch');
        }

        config(['explore.provider_budget.kill_switch' => false]);
        $this->assertFalse((new ExploreProviderBudget())->killed());
    }

    /**
     * config/explore.php as it resolves with one variable set to $value, or
     * absent when $value is null — set in every place `env()` reads from, and
     * restored afterwards.
     *
     * @return array<string,mixed>
     */
    private function exploreConfig(string $name, ?string $value): array
    {
        $hadEnv    = array_key_exists($name, $_ENV);
        $hadServer = array_key_exists($name, $_SERVER);
        $oldEnv    = $_ENV[$name] ?? null;
        $oldServer = $_SERVER[$name] ?? null;
        $oldPutenv = getenv($name);

        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);

        if ($value !== null) {
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }

        try {
            return require base_path('config/explore.php');
        } finally {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            if ($hadEnv) {
                $_ENV[$name] = $oldEnv;
            }

            if ($hadServer) {
                $_SERVER[$name] = $oldServer;
            }

            if ($oldPutenv !== false) {
                putenv("{$name}={$oldPutenv}");
            }
        }
    }
}
