<?php

namespace Tests\Unit\Realtime;

use App\Support\Realtime\RealtimeClientConfig;
use Tests\TestCase;

/**
 * The gate itself: what it takes to switch realtime on, and every way it stays off.
 *
 * @see \App\Support\Realtime\RealtimeClientConfig
 */
class RealtimeClientConfigTest extends TestCase
{
    /** Configure a fully working realtime setup, then let each test break one part. */
    private function configureWorkingRealtime(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.client.enabled' => true,
            'broadcasting.client.key' => 'test-app-key',
            'broadcasting.client.cluster' => 'mt1',
            // The server half. Present-or-absent only: nothing here is ever rendered.
            'broadcasting.connections.pusher.app_id' => 'test-app-id',
            'broadcasting.connections.pusher.secret' => 'test-secret',
        ]);
    }

    /** @test */
    public function it_is_off_under_the_shipped_defaults(): void
    {
        // Nothing touched: this is what a checkout with no realtime env answers.
        $this->assertFalse(RealtimeClientConfig::enabled());
    }

    /** @test */
    public function it_is_on_when_the_flag_the_driver_and_both_credentials_agree(): void
    {
        $this->configureWorkingRealtime();

        $this->assertTrue(RealtimeClientConfig::enabled());
        $this->assertSame('test-app-key', RealtimeClientConfig::key());
        $this->assertSame('mt1', RealtimeClientConfig::cluster());
    }

    /** @test */
    public function credentials_alone_do_not_switch_it_on(): void
    {
        // The exact scenario the explicit flag exists for: somebody puts a Pusher key
        // in the environment while trying something out. That must not start opening
        // sockets for every visitor.
        $this->configureWorkingRealtime();
        config(['broadcasting.client.enabled' => false]);

        $this->assertFalse(RealtimeClientConfig::enabled());
    }

    /** @test */
    public function the_flag_alone_does_not_switch_it_on_without_credentials(): void
    {
        $this->configureWorkingRealtime();
        config(['broadcasting.client.key' => null]);

        $this->assertFalse(
            RealtimeClientConfig::enabled(),
            'A missing key must mean OFF. It used to mean "use the hardcoded fallback".'
        );
    }

    /** @test */
    public function a_blank_credential_is_treated_as_absent(): void
    {
        // PUSHER_APP_KEY= in a .env reads as '' , not null. Whitespace likewise.
        $this->configureWorkingRealtime();
        config(['broadcasting.client.key' => '   ']);

        $this->assertFalse(RealtimeClientConfig::enabled());
        $this->assertNull(RealtimeClientConfig::key());
    }

    /** @test */
    public function a_missing_cluster_keeps_it_off(): void
    {
        $this->configureWorkingRealtime();
        config(['broadcasting.client.cluster' => '']);

        $this->assertFalse(RealtimeClientConfig::enabled());
        $this->assertNull(RealtimeClientConfig::cluster());
    }

    /**
     * @test
     *
     * A browser socket under BROADCAST_DRIVER=log can never receive anything, because
     * no event leaves the server. Connecting anyway is a socket that only advertises
     * that half a switch got set.
     */
    public function it_stays_off_while_the_server_broadcasts_somewhere_other_than_pusher(): void
    {
        $this->configureWorkingRealtime();

        foreach (['log', 'null', 'redis', 'ably'] as $driver) {
            config(['broadcasting.default' => $driver]);

            $this->assertFalse(
                RealtimeClientConfig::enabled(),
                "BROADCAST_DRIVER={$driver} must not permit a browser socket."
            );
        }
    }

    /** @test */
    public function the_shipped_config_defaults_to_off_with_no_environment_at_all(): void
    {
        // Evaluate the config file itself, in a child process with every relevant
        // variable removed — the container-rebuild scenario, reproduced rather than
        // described. An install that supplies nothing must not open sockets.
        $configPath = base_path('config/broadcasting.php');

        $script = <<<'PHP'
            $path = $argv[1];
            if (! function_exists('env')) {
                function env($key, $default = null) {
                    $value = getenv($key);
                    return $value === false ? $default : $value;
                }
            }
            $config = require $path;
            echo json_encode([
                'enabled' => $config['client']['enabled'],
                'key' => $config['client']['key'],
                'cluster' => $config['client']['cluster'],
                'default' => $config['default'],
            ]);
        PHP;

        $scriptFile = tempnam(sys_get_temp_dir(), 'rtcfg').'.php';
        file_put_contents($scriptFile, "<?php\n".$script);

        $command = 'env -u BROADCAST_CLIENT_ENABLED -u BROADCAST_DRIVER -u PUSHER_APP_KEY'
            .' -u PUSHER_APP_CLUSTER -u PUSHER_APP_ID -u PUSHER_APP_SECRET '
            .escapeshellarg(PHP_BINARY).' '.escapeshellarg($scriptFile).' '.escapeshellarg($configPath);

        $output = shell_exec($command.' 2>&1');
        @unlink($scriptFile);

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded, "Child process did not return JSON. Got: {$output}");
        $this->assertFalse($decoded['enabled'], 'broadcasting.client.enabled must default to false.');
        $this->assertNull($decoded['key']);
        $this->assertNull($decoded['cluster']);
        $this->assertSame('null', $decoded['default']);
    }

    /**
     * @test
     *
     * The availability case, not merely the pointless-socket case. Pusher\Pusher's
     * constructor is typed `string $secret`, and BroadcastServiceProvider's
     * require of routes/channels.php resolves the broadcaster — so a null secret
     * behind an "on" flag is a TypeError during provider boot, which is HTTP 500 on
     * every page rather than a broken notification.
     */
    public function an_incomplete_server_connection_keeps_it_off(): void
    {
        foreach (['broadcasting.connections.pusher.secret', 'broadcasting.connections.pusher.app_id'] as $missing) {
            $this->configureWorkingRealtime();
            config([$missing => null]);

            $this->assertFalse(
                RealtimeClientConfig::enabled(),
                "A blank {$missing} must read as OFF, before anything builds a Pusher client."
            );
        }
    }
}
