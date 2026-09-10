<?php

namespace Tests\Unit\Realtime;

use Tests\TestCase;

/**
 * Static assertions over resources/js/bootstrap.js and the tracked tree.
 *
 * There is no JS unit runner in this repository (Playwright is the only front-end tooling,
 * and it needs a live server), so the client-side contract is pinned at source. The three
 * properties below are the ones that were broken, and each of them is a property of the
 * FILE — a hardcoded credential, a build-time configuration read, an unvalidated channel
 * name — so source is the honest place to assert them.
 */
class RealtimeBootstrapSourceTest extends TestCase
{
    /**
     * The client key that used to ship as a hardcoded fallback. It is a third-party Pusher
     * app key that nobody here controls, and every page load — guests on the login screen
     * included — opened a WebSocket with it.
     */
    private const RETIRED_FALLBACK_KEY = '3a4373231eb68d1c839d';

    private function bootstrapSource(): string
    {
        $path = base_path('resources/js/bootstrap.js');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @test */
    public function the_hardcoded_pusher_key_is_gone_from_the_front_end_source(): void
    {
        $this->assertStringNotContainsString(
            self::RETIRED_FALLBACK_KEY,
            $this->bootstrapSource(),
            'A default credential must never come back. An absent key means realtime is OFF.'
        );
    }

    /**
     * @test
     *
     * Widened past bootstrap.js because the point is that no tracked file carries it —
     * not a Blade template, not a config file, not a second copy of the bundle entry.
     * public/ is excluded: it holds build output, which is regenerated, not authored.
     */
    public function no_tracked_source_file_carries_the_retired_client_key(): void
    {
        $tracked = $this->trackedFiles();

        $this->assertNotEmpty($tracked, 'git ls-files returned nothing. Broken scanner.');

        $offenders = [];

        foreach ($tracked as $relative) {
            if (str_starts_with($relative, 'public/') || str_starts_with($relative, 'tests/Unit/Realtime/')) {
                continue;
            }

            $absolute = base_path($relative);

            if (! is_file($absolute) || filesize($absolute) > 2 * 1024 * 1024) {
                continue;
            }

            if (str_contains((string) file_get_contents($absolute), self::RETIRED_FALLBACK_KEY)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, "Retired Pusher client key found in tracked source:\n  "
            .implode("\n  ", $offenders));
    }

    /**
     * @test
     *
     * MIX_ variables are frozen into the bundle when assets are built, while the server
     * reads its own copy per request. Two sources for one value, and on this host the
     * build environment and the runtime environment are not the same thing — which is how
     * a bundle ends up talking to an app key the server has already moved off, with a
     * connected-but-silent socket as the only symptom.
     */
    public function the_client_no_longer_reads_its_credentials_from_build_time_mix_variables(): void
    {
        $source = $this->bootstrapSource();

        // Comments explain the history, so only look at what the code would execute.
        $code = $this->stripComments($source);

        $this->assertStringNotContainsString('MIX_PUSHER_APP_KEY', $code);
        $this->assertStringNotContainsString('MIX_PUSHER_APP_CLUSTER', $code);
        $this->assertStringNotContainsString('process.env', $code);

        // ...and reads them from the page the server rendered instead.
        $this->assertStringContainsString("metaContent('realtime-key')", $code);
        $this->assertStringContainsString("metaContent('realtime-cluster')", $code);
        $this->assertStringContainsString("metaContent('realtime-broadcaster')", $code);
    }

    /**
     * @test
     *
     * Every `new Echo(...)` must be reachable only through the realtime configuration the
     * server rendered. If the constructor ever runs unconditionally again, an install with
     * no Pusher setup starts opening sockets on every page view.
     */
    public function echo_is_only_constructed_when_the_server_published_a_realtime_configuration(): void
    {
        $code = $this->stripComments($this->bootstrapSource());

        $this->assertSame(
            1,
            substr_count($code, 'new Echo('),
            'Exactly one Echo construction site, so there is exactly one thing to guard.'
        );

        $guardOffset = strpos($code, 'if (realtime) {');
        $constructOffset = strpos($code, 'new Echo(');

        $this->assertNotFalse($guardOffset, 'The `if (realtime)` gate around Echo construction is gone.');
        $this->assertNotFalse($constructOffset);
        $this->assertLessThan(
            $constructOffset,
            $guardOffset,
            'Echo is constructed before the realtime gate is checked.'
        );
    }

    /**
     * @test
     *
     * The guest defect. `content="{{ auth()->id() }}"` renders as an EMPTY string for a
     * logged-out visitor whenever a template emits the tag outside @auth, and the old
     * code only checked that the TAG existed — so it subscribed to the literal private
     * channel "user.", which is an unauthenticated POST to /broadcasting/auth that can
     * only ever be refused.
     *
     * The id is now required to be digits. That makes the defect structurally impossible
     * rather than dependent on every template remembering its @auth wrapper.
     */
    public function a_private_user_channel_is_only_subscribed_with_a_numeric_user_id(): void
    {
        $code = $this->stripComments($this->bootstrapSource());

        $this->assertSame(
            1,
            substr_count($code, '.private('),
            'Exactly one private-channel subscription site.'
        );

        $this->assertStringContainsString(
            '/^[0-9]+$/.test(userId)',
            $code,
            'The user id must be validated as digits, not merely checked for presence.'
        );

        $guardOffset = strpos($code, '/^[0-9]+$/.test(userId)');
        $subscribeOffset = strpos($code, '.private(');

        $this->assertLessThan(
            $subscribeOffset,
            $guardOffset,
            'The id is validated after the subscription, which is not a guard.'
        );

        // And the subscription is unreachable at all when Echo was never constructed.
        $echoGuardOffset = strpos($code, 'if (!window.Echo)');
        $this->assertNotFalse($echoGuardOffset, 'Missing the `if (!window.Echo) return;` guard.');
        $this->assertLessThan($subscribeOffset, $echoGuardOffset);
    }

    /** @return string[] repo-relative paths */
    private function trackedFiles(): array
    {
        $output = shell_exec('cd '.escapeshellarg(base_path()).' && git ls-files 2>/dev/null');

        return array_values(array_filter(array_map('trim', explode("\n", (string) $output))));
    }

    /** Crude but sufficient: this file has no regex or string literal containing `//` or `/*`. */
    private function stripComments(string $source): string
    {
        $source = preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', (string) $source);
    }
}
