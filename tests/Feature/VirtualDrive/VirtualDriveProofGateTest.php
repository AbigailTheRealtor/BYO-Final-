<?php

namespace Tests\Feature\VirtualDrive;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * The Virtual Drive provider proof is development-only, and that must hold
 * whatever the flag says.
 */
class VirtualDriveProofGateTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private const ROUTES = [
        '/dev/virtual-drive/apple',
        '/dev/virtual-drive/google',
        '/dev/virtual-drive/api/listings?set=test',
    ];

    private const FALSE_VALUES = [null, '', 'false', 'FALSE', '0', 'off', 'OFF', 'no', ' No ', 'banana', '2', 'enabled'];

    private const TRUE_VALUES = ['true', 'TRUE', '1', 'on', 'yes', 'YES', ' true '];

    /** @test */
    public function the_shipped_default_is_off_and_every_route_404s(): void
    {
        $this->assertFalse($this->virtualDriveConfig(null)['proof_enabled']);
        $this->assertFalse(config('virtual_drive.proof_enabled'));

        foreach (self::ROUTES as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    /** @test */
    public function the_flag_opens_the_proof_in_an_allowed_environment(): void
    {
        config(['virtual_drive.proof_enabled' => true]);

        $this->get('/dev/virtual-drive/apple')->assertOk();
        $this->get('/dev/virtual-drive/google')->assertOk();
        $this->getJson('/dev/virtual-drive/api/listings?set=test')->assertOk();
    }

    /** @test */
    public function production_is_refused_even_with_the_flag_on(): void
    {
        config(['virtual_drive.proof_enabled' => true]);
        $this->app['env'] = 'production';

        foreach (self::ROUTES as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    /** @test */
    public function an_environment_that_is_not_named_is_refused_even_with_the_flag_on(): void
    {
        config(['virtual_drive.proof_enabled' => true]);

        foreach (['staging', 'prod', 'preview'] as $environment) {
            $this->app['env'] = $environment;

            foreach (self::ROUTES as $uri) {
                $this->get($uri)->assertNotFound();
            }
        }
    }

    /** @test */
    public function the_flag_parses_strictly_and_fails_closed(): void
    {
        foreach (self::FALSE_VALUES as $value) {
            $this->assertFalse(
                $this->virtualDriveConfig($value)['proof_enabled'],
                'VIRTUAL_DRIVE_PROOF_ENABLED=' . var_export($value, true) . ' must leave the proof OFF'
            );
        }

        foreach (self::TRUE_VALUES as $value) {
            $this->assertTrue(
                $this->virtualDriveConfig($value)['proof_enabled'],
                'VIRTUAL_DRIVE_PROOF_ENABLED=' . var_export($value, true) . ' must switch the proof ON'
            );
        }
    }

    /** @test */
    public function each_page_loads_exactly_one_provider_and_never_the_other(): void
    {
        config(['virtual_drive.proof_enabled' => true]);

        $apple  = $this->get('/dev/virtual-drive/apple')->assertOk()->getContent();
        $google = $this->get('/dev/virtual-drive/google')->assertOk()->getContent();

        $this->assertStringContainsString('js/virtual-drive/apple-lookaround-provider.js', $apple);
        $this->assertStringNotContainsString('google-streetview-provider.js', $apple);
        $this->assertStringNotContainsString('maps.googleapis.com', $apple);

        $this->assertStringContainsString('js/virtual-drive/google-streetview-provider.js', $google);
        $this->assertStringNotContainsString('apple-lookaround-provider.js', $google);
        $this->assertStringNotContainsString('apple-mapkit', $google);

        // No map of any kind shares the screen with the imagery.
        foreach ([$apple, $google] as $page) {
            $this->assertStringNotContainsString('maplibre', strtolower($page));
            $this->assertStringNotContainsString('js/app.js', $page);
        }
    }

    /** @test */
    public function no_credential_is_emitted_when_none_is_configured_and_there_is_no_fallback(): void
    {
        config([
            'virtual_drive.proof_enabled'      => true,
            'virtual_drive.apple.mapkit_token' => null,
            'virtual_drive.google.browser_key' => null,
            // Both must be ignored: a server key, and another surface's browser key.
            'services.google.places_key'       => 'PLACES-SERVER-KEY-SENTINEL',
            'explore.google.browser_key'       => 'EXPLORE-BROWSER-KEY-SENTINEL',
        ]);

        foreach (['/dev/virtual-drive/apple', '/dev/virtual-drive/google'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('data-credential=""', false)
                ->assertDontSee('PLACES-SERVER-KEY-SENTINEL')
                ->assertDontSee('EXPLORE-BROWSER-KEY-SENTINEL');
        }
    }

    /** @test */
    public function a_configured_credential_reaches_only_its_own_providers_page(): void
    {
        config([
            'virtual_drive.proof_enabled'      => true,
            'virtual_drive.apple.mapkit_token' => 'APPLE-TOKEN-SENTINEL',
            'virtual_drive.google.browser_key' => 'GOOGLE-KEY-SENTINEL',
        ]);

        $this->get('/dev/virtual-drive/apple')
            ->assertSee('data-credential="APPLE-TOKEN-SENTINEL"', false)
            ->assertDontSee('GOOGLE-KEY-SENTINEL');

        $this->get('/dev/virtual-drive/google')
            ->assertSee('data-credential="GOOGLE-KEY-SENTINEL"', false)
            ->assertDontSee('APPLE-TOKEN-SENTINEL');
    }

    /** Evaluates config/virtual_drive.php with the variable set to $value (null = unset). */
    private function virtualDriveConfig(?string $value): array
    {
        $name      = 'VIRTUAL_DRIVE_PROOF_ENABLED';
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
            return require base_path('config/virtual_drive.php');
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
