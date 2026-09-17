<?php

namespace Tests\Feature\VirtualDrive;

use App\Support\VirtualDrive\VirtualDriveGoogleGate;
use App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT — the daily ceiling on intentional
 * Virtual Drive launches, across the whole proof environment.
 *
 * "Across the proof environment" is what these tests are really about. A
 * per-browser counter would pass a naive test and fail the requirement, so the
 * cases below spend the allowance from one session and then try to launch from
 * another: the second must be refused because of what the first did.
 *
 * No Google anything is contacted. The ledger is cache state and the endpoint is
 * ours; the only thing that ever reaches Google is the browser, which is exactly
 * why the grant — and not the page — is what carries the key.
 */
class VirtualDriveGoogleDailyLaunchLimitTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private const KEY = 'GOOGLE-KEY-SENTINEL';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'virtual_drive.proof_enabled'             => true,
            'virtual_drive.google.enabled'            => true,
            'virtual_drive.google.browser_key'        => self::KEY,
            'virtual_drive.google.daily_launch_limit' => 3,
        ]);

        Cache::flush();
    }

    private function ledger(): VirtualDriveGoogleLaunchLedger
    {
        return $this->app->make(VirtualDriveGoogleLaunchLedger::class);
    }

    // ----------------------------------------------------------- the configured number

    /** @test */
    public function the_limit_is_read_from_the_environment_variable(): void
    {
        $this->assertSame(10, $this->limitFromEnv('10'));
        $this->assertSame(1, $this->limitFromEnv('1'));
        $this->assertSame(250, $this->limitFromEnv(' 250 '));
    }

    /**
     * An absent or nonsense ceiling is ZERO — refuse — never "unlimited". An
     * operator who switched Google on without choosing a number has not
     * authorised an unbounded day.
     *
     * @test
     */
    public function an_absent_or_malformed_limit_refuses_rather_than_unleashing(): void
    {
        foreach ([null, '', 'ten', '-1', '-10', '0', '2.5', 'unlimited', 'true'] as $value) {
            $this->assertSame(
                0,
                $this->limitFromEnv($value),
                VirtualDriveGoogleGate::LIMIT_FLAG . '=' . var_export($value, true) . ' must mean NO launches'
            );
        }
    }

    /** @test */
    public function a_configured_ceiling_of_zero_refuses_every_launch_and_names_the_variable(): void
    {
        config(['virtual_drive.google.daily_launch_limit' => 0]);

        $response = $this->postJson('/dev/virtual-drive/api/google-launch')
            ->assertStatus(403)
            ->assertJson(['granted' => false, 'reason' => 'ceiling_not_configured']);

        $this->assertStringContainsString(VirtualDriveGoogleGate::LIMIT_FLAG, $response->json('message'));
        $this->assertStringNotContainsString(self::KEY, $response->getContent());
    }

    // ----------------------------------------------------------- the ceiling itself

    /** @test */
    public function launches_are_granted_up_to_the_limit_and_then_refused(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/dev/virtual-drive/api/google-launch')
                ->assertOk()
                ->assertJson([
                    'granted'   => true,
                    'limit'     => 3,
                    'used'      => $i,
                    'remaining' => 3 - $i,
                ])
                ->assertJsonPath('credential', self::KEY);
        }

        $refused = $this->postJson('/dev/virtual-drive/api/google-launch')
            ->assertStatus(429)
            ->assertJson(['granted' => false, 'reason' => 'daily_limit_reached', 'used' => 3, 'remaining' => 0]);

        // A refusal hands out no key, so the browser cannot load Maps JS anyway.
        $this->assertArrayNotHasKey('credential', $refused->json());
        $this->assertStringNotContainsString(self::KEY, $refused->getContent());
    }

    /** @test */
    public function the_refusal_message_is_clear_about_what_happened_and_what_still_works(): void
    {
        config(['virtual_drive.google.daily_launch_limit' => 1]);

        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();

        $message = (string) $this->postJson('/dev/virtual-drive/api/google-launch')->assertStatus(429)->json('message');

        $this->assertStringContainsString('Daily Google Street View limit reached', $message);
        $this->assertStringContainsString('1 launches for ' . $this->ledger()->day(), $message);
        $this->assertStringContainsString('nothing was requested from Google', $message);
        $this->assertStringContainsString('Apple Look Around', $message);
        $this->assertStringContainsString(VirtualDriveGoogleGate::LIMIT_FLAG, $message);
    }

    /**
     * The requirement in one test: the ceiling is environment-wide, not
     * per-browser. A second session with its own cookies inherits what the first
     * one spent.
     *
     * @test
     */
    public function the_allowance_is_shared_across_sessions_and_browsers(): void
    {
        config(['virtual_drive.google.daily_launch_limit' => 2]);

        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();
        $this->flushSession();
        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();

        // A third "browser" — fresh session, no shared client state.
        $this->flushSession();
        $this->postJson('/dev/virtual-drive/api/google-launch')
            ->assertStatus(429)
            ->assertJson(['reason' => 'daily_limit_reached']);
    }

    /** @test */
    public function the_ceiling_is_per_calendar_day_and_resets_at_midnight(): void
    {
        config(['virtual_drive.google.daily_launch_limit' => 1]);

        $this->travelTo(now()->startOfDay()->addHours(9));
        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();
        $this->postJson('/dev/virtual-drive/api/google-launch')->assertStatus(429);

        // Later the same day: still spent.
        $this->travelTo(now()->startOfDay()->addHours(23)->addMinutes(59));
        $this->postJson('/dev/virtual-drive/api/google-launch')->assertStatus(429);

        // The next calendar day: a fresh allowance, under a different key.
        $this->travelTo(now()->addDay()->startOfDay()->addMinute());
        $this->postJson('/dev/virtual-drive/api/google-launch')
            ->assertOk()
            ->assertJson(['used' => 1, 'remaining' => 0]);

        $this->travelBack();
    }

    /** @test */
    public function the_tally_is_kept_under_a_day_keyed_cache_entry(): void
    {
        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();

        $key = VirtualDriveGoogleLaunchLedger::KEY_PREFIX . now()->toDateString();

        $this->assertSame(1, (int) Cache::get($key));
    }

    // ----------------------------------------------------------- reading without spending

    /** @test */
    public function rendering_the_page_reports_the_allowance_and_never_spends_one(): void
    {
        config(['virtual_drive.google.daily_launch_limit' => 5]);

        $this->get('/dev/virtual-drive/google')
            ->assertOk()
            ->assertSee('0 of 5 launches used today', false)
            ->assertSee('data-daily-launch-limit="5"', false);

        $this->assertSame(0, $this->ledger()->peek()['used']);

        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();

        $this->get('/dev/virtual-drive/google')->assertOk()->assertSee('1 of 5 launches used today', false);
        $this->assertSame(1, $this->ledger()->peek()['used']);
    }

    /** @test */
    public function a_page_rendered_after_the_limit_is_reached_says_so_before_anything_is_pressed(): void
    {
        config(['virtual_drive.google.daily_launch_limit' => 1]);

        $this->postJson('/dev/virtual-drive/api/google-launch')->assertOk();

        $this->get('/dev/virtual-drive/google')
            ->assertOk()
            ->assertSee('Daily Google Street View limit reached')
            ->assertSee('Nothing will be requested');
    }

    // ----------------------------------------------------------- failing closed

    /**
     * A cache store that cannot hold the tally would make every launch look like
     * the first of the day, with no error anywhere — the ceiling reading as
     * enforced and enforcing nothing. So an unwritable ledger is a refusal.
     *
     * @test
     */
    public function a_ledger_that_cannot_be_written_refuses_instead_of_counting_nothing(): void
    {
        config(['cache.default' => 'null']);

        $response = $this->postJson('/dev/virtual-drive/api/google-launch')
            ->assertStatus(503)
            ->assertJson(['granted' => false, 'reason' => 'ledger_unavailable']);

        $this->assertStringNotContainsString(self::KEY, $response->getContent());
        $this->assertStringContainsString('refused rather than started uncounted', $response->json('message'));
    }

    /** @test */
    public function the_ledger_refuses_before_it_counts_when_the_switch_is_off_or_the_key_is_absent(): void
    {
        config(['virtual_drive.google.enabled' => false]);
        $this->assertSame('google_disabled', $this->ledger()->claim()['reason']);

        config(['virtual_drive.google.enabled' => true, 'virtual_drive.google.browser_key' => '']);
        $this->assertSame('credential_missing', $this->ledger()->claim()['reason']);

        // Neither refusal may consume a launch: the day is still untouched.
        config(['virtual_drive.google.browser_key' => self::KEY]);
        $this->assertSame(0, $this->ledger()->peek()['used']);
        $this->assertTrue($this->ledger()->claim()['granted']);
    }

    /** @test */
    public function the_endpoint_is_throttled_below_the_ceiling_it_guards(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'dev.virtual-drive.api.google-launch');

        $this->assertNotNull($route);
        $this->assertContains('throttle:20,1', $route->gatherMiddleware());
        $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])));
    }

    /**
     * Evaluate config/virtual_drive.php with the ceiling variable set exactly,
     * then restore the process environment.
     */
    private function limitFromEnv(?string $value): int
    {
        $name      = VirtualDriveGoogleGate::LIMIT_FLAG;
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
            return (require base_path('config/virtual_drive.php'))['google']['daily_launch_limit'];
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
