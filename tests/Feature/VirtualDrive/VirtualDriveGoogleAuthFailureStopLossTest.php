<?php

namespace Tests\Feature\VirtualDrive;

use App\Support\VirtualDrive\VirtualDriveGoogleAuthBlock;
use App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The auth-failure stop-loss — a rejected Google key must not be able to spend
 * the day's launches one reload at a time.
 *
 * On 2026-09-15 six reload-and-press attempts against a key Google kept
 * rejecting spent six launches in eleven minutes. Each grant was correct by the
 * ledger's rules; each was also certain to fail. These tests pin the rule that
 * replaces that: the first rejection blocks every later claim, from any session,
 * on any day, until the explicit reset command — and nothing is refunded.
 *
 * No Google anything is contacted: the block is a local file, the endpoints are
 * ours, and the browser half is covered by virtual-drive-auth-failure.spec.js.
 */
class VirtualDriveGoogleAuthFailureStopLossTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'GOOGLE-KEY-SENTINEL';

    /**
     * A value with the SHAPE of a Google API key, for proving the redaction.
     * Built at runtime so no Google-key-shaped literal exists in the source for
     * secret scanning to mistake for a real credential; it says what it is.
     */
    private static function keyShaped(): string
    {
        return implode('', ['AI', 'za', '_TEST_ONLY_NOT_A_REAL_KEY_', str_repeat('0', 9)]);
    }

    private const CLAIM = '/dev/virtual-drive/api/google-launch';

    private const REPORT = '/dev/virtual-drive/api/google-auth-failure';

    private string $blockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blockPath = sys_get_temp_dir() . '/vd-auth-block-' . bin2hex(random_bytes(6)) . '/block.json';

        config([
            'virtual_drive.proof_enabled'             => true,
            'virtual_drive.google.enabled'            => true,
            'virtual_drive.google.browser_key'        => self::KEY,
            'virtual_drive.google.daily_launch_limit' => 3,
            'virtual_drive.google.auth_block_path'    => $this->blockPath,
        ]);

        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->blockPath);
        @rmdir(dirname($this->blockPath));

        parent::tearDown();
    }

    private function refererReport(array $overrides = []): array
    {
        return $overrides + [
            'code'            => 'RefererNotAllowedMapError',
            'message'         => "Google Maps JavaScript API error: RefererNotAllowedMapError\n"
                . 'https://developers.google.com/maps/documentation/javascript/error-messages#referer-not-allowed-map-error '
                . 'Your site URL to be authorized: https://proof.example.test:8000/dev/virtual-drive/google',
            'authorized_url'  => 'https://proof.example.test:8000/dev/virtual-drive/google?listing=abc',
            'page_origin'     => 'https://proof.example.test:8000',
            'referrer_policy' => 'browser-default',
            'referrer_sent'   => 'https://proof.example.test:8000/',
            'source'          => 'gm_authFailure+console',
        ];
    }

    private function reportFailure(array $overrides = [])
    {
        return $this->withHeaders(['Origin' => 'https://proof.example.test:8000'])
            ->postJson(self::REPORT, $this->refererReport($overrides));
    }

    private function ledger(): VirtualDriveGoogleLaunchLedger
    {
        return $this->app->make(VirtualDriveGoogleLaunchLedger::class);
    }

    // ----------------------------------------------------------- the block itself

    /** @test */
    public function an_auth_failure_blocks_every_later_claim_and_hands_out_no_key(): void
    {
        $this->postJson(self::CLAIM)->assertOk()->assertJson(['granted' => true, 'used' => 1]);

        $this->reportFailure()->assertOk()->assertJson([
            'blocked'      => true,
            'auth_failure' => ['code' => 'RefererNotAllowedMapError'],
        ]);

        for ($i = 0; $i < 3; $i++) {
            $refused = $this->postJson(self::CLAIM)
                ->assertStatus(423)
                ->assertJson([
                    'granted'      => false,
                    'reason'       => VirtualDriveGoogleLaunchLedger::REASON_AUTH_FAILURE_BLOCKED,
                    'used'         => 1,
                    'auth_failure' => ['code' => 'RefererNotAllowedMapError', 'request_origin' => 'https://proof.example.test:8000'],
                ]);

            $this->assertArrayNotHasKey('credential', $refused->json());
            $this->assertStringNotContainsString(self::KEY, $refused->getContent());
            $this->assertStringContainsString('RefererNotAllowedMapError', $refused->json('message'));
            $this->assertStringContainsString(VirtualDriveGoogleAuthBlock::RESET_COMMAND, $refused->json('message'));
        }

        // Refusals spent nothing.
        $this->assertSame(1, $this->ledger()->peek()['used']);
    }

    /** @test */
    public function the_failed_launch_is_not_refunded_by_the_report_or_by_the_reset(): void
    {
        $this->postJson(self::CLAIM)->assertOk();
        $this->assertSame(1, $this->ledger()->peek()['used']);

        $this->reportFailure()->assertOk();
        $this->assertSame(1, $this->ledger()->peek()['used'], 'a report must not hand a launch back');

        $this->artisan('virtual-drive:google-auth-block', ['--reset' => true, '--yes' => true])->assertExitCode(0);
        $this->assertSame(1, $this->ledger()->peek()['used'], 'a reset must not hand a launch back');

        $this->postJson(self::CLAIM)->assertOk()->assertJson(['used' => 2]);
    }

    /** @test */
    public function the_block_refuses_even_when_launches_remain_and_before_anything_is_counted(): void
    {
        $this->reportFailure()->assertOk();

        $this->postJson(self::CLAIM)->assertStatus(423)->assertJson(['used' => 0, 'remaining' => 3]);
        $this->assertSame(0, $this->ledger()->peek()['used']);
    }

    // ----------------------------------------------------------- reloads, sessions, days, caches

    /**
     * A reload is a new page, a new session in a fresh browser, or a new day —
     * none of them may clear the block.
     *
     * @test
     */
    public function a_reload_a_new_session_a_new_day_and_a_cache_clear_do_not_bypass_the_block(): void
    {
        $this->reportFailure()->assertOk();

        // Reload: the page is rendered locked, with the recorded cause and no key.
        $page = $this->get('/dev/virtual-drive/google')->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString('data-google-auth-block="{', $html);
        $this->assertStringContainsString('RefererNotAllowedMapError', $html);
        $this->assertStringContainsString('data-google-auth-failure-endpoint="/dev/virtual-drive/api/google-auth-failure"', $html);
        $this->assertStringContainsString('launches are blocked in this proof environment', $html);
        $this->assertStringNotContainsString(self::KEY, $html);

        // A different browser: no cookies, no session.
        $this->flushSession();
        $this->postJson(self::CLAIM)->assertStatus(423);

        // Tomorrow.
        $this->travel(1)->days();
        $this->postJson(self::CLAIM)->assertStatus(423);

        // The routine debugging command.
        Cache::flush();
        Artisan::call('cache:clear');
        $this->postJson(self::CLAIM)->assertStatus(423);
    }

    /** @test */
    public function a_second_report_keeps_the_first_cause_and_still_blocks(): void
    {
        $this->reportFailure()->assertOk();
        $this->reportFailure(['code' => 'ApiTargetBlockedMapError'])->assertOk()
            ->assertJsonPath('auth_failure.code', 'RefererNotAllowedMapError')
            ->assertJsonPath('auth_failure.reports', 2);

        $this->postJson(self::CLAIM)->assertStatus(423);
    }

    /** @test */
    public function a_block_file_that_cannot_be_read_still_blocks(): void
    {
        @mkdir(dirname($this->blockPath), 0775, true);
        file_put_contents($this->blockPath, '{not json');

        $this->postJson(self::CLAIM)
            ->assertStatus(423)
            ->assertJson(['reason' => VirtualDriveGoogleLaunchLedger::REASON_AUTH_FAILURE_BLOCKED]);

        $this->assertStringContainsString('launches are blocked', $this->get('/dev/virtual-drive/google')->getContent());
    }

    /** @test */
    public function a_block_that_cannot_be_written_is_reported_as_not_recorded(): void
    {
        config(['virtual_drive.google.auth_block_path' => '/dev/null/virtual-drive/block.json']);

        $this->reportFailure()->assertStatus(503)->assertJson(['blocked' => false]);
    }

    // ----------------------------------------------------------- only the explicit reset clears it

    /** @test */
    public function only_the_explicit_reset_command_clears_the_block(): void
    {
        $this->reportFailure()->assertOk();

        // Status is read-only.
        $this->assertSame(0, Artisan::call('virtual-drive:google-auth-block'));
        $this->assertStringContainsString('BLOCKED', Artisan::output());
        $this->assertFileExists($this->blockPath);
        $this->postJson(self::CLAIM)->assertStatus(423);

        // --reset without confirming leaves it in place.
        $this->artisan('virtual-drive:google-auth-block', ['--reset' => true])
            ->expectsConfirmation('Clear the block and allow Google Street View launches again? Confirm the key restriction has been fixed first.', 'no')
            ->assertExitCode(1);
        $this->postJson(self::CLAIM)->assertStatus(423);

        // Confirmed.
        $this->artisan('virtual-drive:google-auth-block', ['--reset' => true])
            ->expectsConfirmation('Clear the block and allow Google Street View launches again? Confirm the key restriction has been fixed first.', 'yes')
            ->assertExitCode(0);
        $this->postJson(self::CLAIM)->assertOk()->assertJson(['granted' => true]);
        $this->assertFileDoesNotExist($this->blockPath);
    }

    /** @test */
    public function no_http_route_can_clear_the_block(): void
    {
        $proofRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'dev/virtual-drive'));

        $this->assertNotEmpty($proofRoutes);

        foreach ($proofRoutes as $route) {
            $this->assertDoesNotMatchRegularExpression('/reset|clear|unblock/i', $route->uri() . ' ' . $route->getName());
        }

        $this->reportFailure()->assertOk();

        // 404, not 405: app/Exceptions/Handler.php maps MethodNotAllowed to not-found.
        foreach (['DELETE', 'PUT', 'PATCH', 'GET'] as $method) {
            $this->json($method, self::REPORT)->assertNotFound();
            $this->json($method, self::CLAIM)->assertNotFound();
        }

        $this->postJson(self::CLAIM)->assertStatus(423);
    }

    /** @test */
    public function the_reset_command_refuses_outside_the_proof_environments(): void
    {
        $this->reportFailure()->assertOk();

        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;

            $this->assertSame(1, Artisan::call('virtual-drive:google-auth-block', ['--reset' => true, '--yes' => true]));
            $this->assertStringContainsString('Refused', Artisan::output());
        }

        $this->app['env'] = 'testing';
        $this->assertFileExists($this->blockPath);
        $this->postJson(self::CLAIM)->assertStatus(423);
    }

    // ----------------------------------------------------------- what is recorded, and never the key

    /** @test */
    public function exact_error_information_is_kept_and_the_key_is_never_stored_or_returned(): void
    {
        $keyShaped = self::keyShaped();

        // The sentinel must still look like a key to the redaction, or this test proves nothing.
        $this->assertMatchesRegularExpression('/^AIza[0-9A-Za-z_\-]{35}$/', $keyShaped);

        $response = $this->reportFailure([
            'message'        => 'Google Maps JavaScript API error: InvalidKeyMapError key ' . self::KEY
                . ' and ' . $keyShaped . ' from https://maps.googleapis.com/maps/api/js?key=' . $keyShaped . '&v=weekly',
            'code'           => 'InvalidKeyMapError',
            'authorized_url' => 'https://proof.example.test:8000/dev/virtual-drive/google?key=' . $keyShaped . '#x',
            'page_origin'    => 'https://proof.example.test:8000/dev/virtual-drive/google?key=' . self::KEY,
        ])->assertOk();

        $this->assertSame('InvalidKeyMapError', $response->json('auth_failure.code'));
        $this->assertSame('https://proof.example.test:8000/dev/virtual-drive/google', $response->json('auth_failure.authorized_url'));
        $this->assertSame('https://proof.example.test:8000', $response->json('auth_failure.page_origin'));
        $this->assertSame('https://proof.example.test:8000', $response->json('auth_failure.request_origin'));
        $this->assertStringContainsString('InvalidKeyMapError', $response->json('auth_failure.message'));

        $stored = file_get_contents($this->blockPath);

        foreach ([$response->getContent(), $stored, $this->postJson(self::CLAIM)->getContent(), $this->get('/dev/virtual-drive/google')->getContent()] as $surface) {
            $this->assertStringNotContainsString(self::KEY, $surface);
            $this->assertStringNotContainsString($keyShaped, $surface);
            $this->assertDoesNotMatchRegularExpression('/AIza[0-9A-Za-z_\-]{10,}/', $surface);
        }

        $this->assertSame(0, Artisan::call('virtual-drive:google-auth-block'));
        $output = Artisan::output();
        $this->assertStringContainsString('InvalidKeyMapError', $output);
        $this->assertStringNotContainsString(self::KEY, $output);
        $this->assertStringNotContainsString($keyShaped, $output);
        $this->assertDoesNotMatchRegularExpression('/AIza[0-9A-Za-z_\-]{10,}/', $output);
    }

    /** @test */
    public function a_code_that_is_not_a_maps_error_is_not_recorded_as_one(): void
    {
        $this->reportFailure(['code' => '<script>alert(1)</script>'])->assertOk()
            ->assertJsonPath('auth_failure.code', null);

        // It still blocks: the report says Google rejected the key, whatever it called it.
        $this->postJson(self::CLAIM)->assertStatus(423);
    }

    // ----------------------------------------------------------- the gates still stand

    /** @test */
    public function the_report_endpoint_does_not_exist_where_the_proof_is_closed(): void
    {
        config(['virtual_drive.proof_enabled' => false]);
        $this->reportFailure()->assertNotFound();

        // CSRF is live outside the testing environment and answers 419 before the
        // proof gate; disabled so this asserts the gate, as the kill-switch test does.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['virtual_drive.proof_enabled' => true]);
        $this->app['env'] = 'production';
        $this->reportFailure()->assertNotFound();

        $this->app['env'] = 'testing';
        $this->assertFileDoesNotExist($this->blockPath);
    }

    /** @test */
    public function the_report_is_a_throttled_post(): void
    {
        $route = Route::getRoutes()->getByName('dev.virtual-drive.api.google-auth-failure');

        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
        $this->assertContains('virtual-drive-proof', $route->gatherMiddleware());

        $this->get(self::REPORT)->assertNotFound();
    }

    /**
     * The proof server runs with a ceiling of 20 (owner decision, 2026-09-15),
     * pinned in the script so the Replit Secret can neither raise nor lower it
     * by accident. The stop-loss, not the ceiling, is what stops a rejected key.
     *
     * @test
     */
    public function the_proof_serve_script_pins_the_ceiling_to_twenty_launches(): void
    {
        $script = file_get_contents(base_path('scripts/dev/virtual-drive-proof-serve.sh'));

        $this->assertMatchesRegularExpression('/^GOOGLE_LAUNCH_LIMIT=20$/m', $script);
        $this->assertStringContainsString('VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT="${GOOGLE_LAUNCH_LIMIT}"', $script);
        $this->assertStringNotContainsString('${VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT', $script, 'the Secret must not be able to raise it');
        $this->assertStringNotContainsString('VIRTUAL_DRIVE_GOOGLE_ENABLED=true \\', $script, 'the script still must not switch Google on');
    }
}
