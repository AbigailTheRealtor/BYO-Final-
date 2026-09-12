<?php

namespace Tests\Feature\VirtualDrive;

use App\Support\VirtualDrive\VirtualDriveGoogleGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * VIRTUAL_DRIVE_GOOGLE_ENABLED — the development-only Google kill switch.
 *
 * The promise being tested is narrow and absolute: with the switch off, no
 * Google Maps JavaScript library and no StreetViewPanorama can initialize. So
 * these tests do not check a flag; they check the three things that make the
 * promise true without the browser's cooperation.
 *
 *   1. google-streetview-provider.js — the only file that can construct a
 *      panorama — is not on the page.
 *   2. No browser key is emitted. (And none is emitted when the switch is ON
 *      either: the key lives in a granted launch claim and nowhere else.)
 *   3. The launch-claim endpoint, the only source of that key, refuses.
 *
 * Apple Look Around must be unaffected throughout, because the point of having
 * a separate switch is to review Apple with Google incapable of starting.
 */
class VirtualDriveGoogleKillSwitchTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private const FALSE_VALUES = [null, '', 'false', 'FALSE', '0', 'off', 'OFF', 'no', ' No ', 'banana', '2', 'enabled'];

    private const TRUE_VALUES = ['true', 'TRUE', '1', 'on', 'yes', 'YES', ' true '];

    protected function setUp(): void
    {
        parent::setUp();

        // The proof itself open; only the Google switch is under test here.
        config(['virtual_drive.proof_enabled' => true]);
    }

    /** @test */
    public function the_shipped_default_is_off(): void
    {
        $this->assertFalse($this->config(null)['google']['enabled']);
        $this->assertFalse(config('virtual_drive.google.enabled'));
        $this->assertFalse(VirtualDriveGoogleGate::enabled());
    }

    /** @test */
    public function the_switch_parses_strictly_and_fails_closed(): void
    {
        foreach (self::FALSE_VALUES as $value) {
            $this->assertFalse(
                $this->config($value)['google']['enabled'],
                VirtualDriveGoogleGate::FLAG . '=' . var_export($value, true) . ' must leave Google OFF'
            );
        }

        foreach (self::TRUE_VALUES as $value) {
            $this->assertTrue(
                $this->config($value)['google']['enabled'],
                VirtualDriveGoogleGate::FLAG . '=' . var_export($value, true) . ' must switch Google ON'
            );
        }
    }

    /**
     * The whole point, stated as one assertion set: with the switch off there is
     * nothing on the page that could load Google or build a panorama.
     *
     * @test
     */
    public function with_google_off_the_page_carries_no_provider_script_and_no_key(): void
    {
        config([
            'virtual_drive.google.enabled'            => false,
            'virtual_drive.google.browser_key'        => 'GOOGLE-KEY-SENTINEL',
            'virtual_drive.google.daily_launch_limit' => 10,
        ]);

        $page = $this->get('/dev/virtual-drive/google')->assertOk()->getContent();

        foreach ([
            'google-streetview-provider.js',
            'maps.googleapis.com',
            'StreetViewPanorama',
            'GOOGLE-KEY-SENTINEL',
        ] as $absent) {
            $this->assertStringNotContainsString($absent, $page, "a switched-off page must not contain {$absent}");
        }

        // The shell is still there — the listings, cards and photos are not Google's.
        $this->assertStringContainsString('js/virtual-drive/virtual-drive-shell.js', $page);
        $this->assertStringContainsString('data-provider-enabled="0"', $page);
        $this->assertStringContainsString('data-credential=""', $page);

        // And it says so, in the gate's own words, where the launch button is.
        $this->assertStringContainsString(VirtualDriveGoogleGate::FLAG . '=false', $page);
        $this->assertStringContainsString('no maps javascript library is loaded', strtolower($page));
    }

    /** @test */
    public function with_google_on_the_script_returns_but_the_key_still_is_not_in_the_page(): void
    {
        config([
            'virtual_drive.google.enabled'            => true,
            'virtual_drive.google.browser_key'        => 'GOOGLE-KEY-SENTINEL',
            'virtual_drive.google.daily_launch_limit' => 10,
        ]);

        $page = $this->get('/dev/virtual-drive/google')->assertOk()->getContent();

        $this->assertStringContainsString('js/virtual-drive/google-streetview-provider.js', $page);
        $this->assertStringContainsString('data-provider-enabled="1"', $page);

        // THE KEY IS NEVER IN THE MARKUP. It is delivered by a granted claim.
        $this->assertStringNotContainsString('GOOGLE-KEY-SENTINEL', $page);
        $this->assertStringContainsString('data-credential=""', $page);
        $this->assertStringContainsString('data-credential-available="1"', $page);
        $this->assertStringContainsString('data-launch-claim-endpoint="' . route('dev.virtual-drive.api.google-launch') . '"', $page);
    }

    /** @test */
    public function the_page_reports_a_missing_key_without_publishing_whether_one_exists_elsewhere(): void
    {
        config([
            'virtual_drive.google.enabled'            => true,
            'virtual_drive.google.browser_key'        => null,
            'virtual_drive.google.daily_launch_limit' => 10,
        ]);

        $this->get('/dev/virtual-drive/google')
            ->assertOk()
            ->assertSee('data-credential-available="0"', false)
            ->assertSee('VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY is not', false);
    }

    /** @test */
    public function the_apple_page_is_untouched_by_the_google_switch(): void
    {
        config([
            'virtual_drive.google.enabled'      => false,
            'virtual_drive.apple.mapkit_token'  => 'APPLE-TOKEN-SENTINEL',
        ]);

        $page = $this->get('/dev/virtual-drive/apple')->assertOk()->getContent();

        $this->assertStringContainsString('js/virtual-drive/apple-lookaround-provider.js', $page);
        $this->assertStringContainsString('data-provider-enabled="1"', $page);
        $this->assertStringContainsString('data-credential-available="1"', $page);
        // Apple's token is a browser credential by design and still arrives inline.
        $this->assertStringContainsString('APPLE-TOKEN-SENTINEL', $page);
        // Apple claims nothing: the ceiling belongs to the billed provider.
        $this->assertStringContainsString('data-launch-claim-endpoint=""', $page);
        $this->assertStringNotContainsString('google-streetview-provider.js', $page);
    }

    /** @test */
    public function the_comparison_page_says_google_is_off_and_still_loads_neither_provider(): void
    {
        config([
            'virtual_drive.google.enabled'     => false,
            'virtual_drive.google.browser_key' => 'GOOGLE-KEY-SENTINEL',
            'virtual_drive.apple.mapkit_token' => 'APPLE-TOKEN-SENTINEL',
        ]);

        $page = $this->get('/dev/virtual-drive')->assertOk()->getContent();

        $this->assertStringContainsString(VirtualDriveGoogleGate::FLAG . '=false', $page);

        foreach ([
            'google-streetview-provider.js',
            'apple-lookaround-provider.js',
            'maps.googleapis.com',
            'GOOGLE-KEY-SENTINEL',
            'APPLE-TOKEN-SENTINEL',
        ] as $absent) {
            $this->assertStringNotContainsString($absent, $page);
        }
    }

    /** @test */
    public function the_claim_endpoint_refuses_while_the_switch_is_off_and_hands_out_no_key(): void
    {
        config([
            'virtual_drive.google.enabled'            => false,
            'virtual_drive.google.browser_key'        => 'GOOGLE-KEY-SENTINEL',
            'virtual_drive.google.daily_launch_limit' => 10,
        ]);

        $response = $this->postJson('/dev/virtual-drive/api/google-launch')
            ->assertStatus(403)
            ->assertJson(['granted' => false, 'reason' => 'google_disabled']);

        $this->assertStringNotContainsString('GOOGLE-KEY-SENTINEL', $response->getContent());
        $this->assertArrayNotHasKey('credential', $response->json());
        $this->assertStringContainsString(VirtualDriveGoogleGate::FLAG . '=false', $response->json('message'));
    }

    /**
     * The switch lives under the proof gate, never beside it: on a production
     * host a Google switch left on still reaches a 404.
     *
     * @test
     */
    public function the_google_switch_cannot_open_anything_the_proof_gate_has_closed(): void
    {
        config([
            'virtual_drive.google.enabled'            => true,
            'virtual_drive.google.browser_key'        => 'GOOGLE-KEY-SENTINEL',
            'virtual_drive.google.daily_launch_limit' => 10,
        ]);

        // CSRF is live outside the testing environment, and it answers 419 before
        // the route's own gate is reached. Disabled here so the assertion is about
        // the proof gate rather than about the token.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        foreach (['production', 'staging', 'prod'] as $environment) {
            $this->app['env'] = $environment;

            $this->get('/dev/virtual-drive/google')->assertNotFound();
            $this->postJson('/dev/virtual-drive/api/google-launch')->assertNotFound();
        }

        $this->app['env'] = 'testing';
        config(['virtual_drive.proof_enabled' => false]);

        $this->get('/dev/virtual-drive/google')->assertNotFound();
        $this->postJson('/dev/virtual-drive/api/google-launch')->assertNotFound();
    }

    /** @test */
    public function the_claim_is_a_post_and_cannot_be_spent_by_following_a_link(): void
    {
        config([
            'virtual_drive.google.enabled'            => true,
            'virtual_drive.google.browser_key'        => 'GOOGLE-KEY-SENTINEL',
            'virtual_drive.google.daily_launch_limit' => 10,
        ]);

        // 404 rather than 405: app/Exceptions/Handler.php maps MethodNotAllowed to
        // not-found deliberately. Either way no allowance is spent.
        $this->get('/dev/virtual-drive/api/google-launch')->assertNotFound();
        $this->call('HEAD', '/dev/virtual-drive/api/google-launch')->assertNotFound();

        $this->assertSame(0, $this->app->make(\App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger::class)->peek()['used']);
    }

    /**
     * Evaluate config/virtual_drive.php with one environment variable set to an
     * exact value, restoring the process environment afterwards.
     *
     * @return array<string,mixed>
     */
    private function config(?string $value, string $name = VirtualDriveGoogleGate::FLAG): array
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
