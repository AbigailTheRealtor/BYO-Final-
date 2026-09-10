<?php

namespace Tests\Feature\Realtime;

use Tests\TestCase;

/**
 * What the browser is actually told, and by which layouts.
 *
 * The absence of the realtime-* meta tags IS the "off" signal that keeps
 * resources/js/bootstrap.js from constructing Echo. So the two things worth pinning are
 * that they are absent by default, and that every layout serving the bundle is in a
 * position to emit them.
 */
class RealtimeClientSurfaceTest extends TestCase
{
    private const PARTIAL = 'partials._realtime-config';

    private function enableRealtime(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.client.enabled' => true,
            'broadcasting.client.key' => 'surface-test-key',
            'broadcasting.client.cluster' => 'us2',
            // The server half is part of the gate too, so a partial configuration here
            // would leave the partial emitting nothing and the assertions below passing
            // for the wrong reason.
            'broadcasting.connections.pusher.app_id' => 'surface-test-app-id',
            'broadcasting.connections.pusher.secret' => 'surface-test-secret',
        ]);
    }

    /** @test */
    public function the_partial_emits_nothing_at_all_while_realtime_is_off(): void
    {
        $html = trim(view(self::PARTIAL)->render());

        $this->assertSame('', $html, 'An unconfigured install must put no realtime markup on the page.');
    }

    /** @test */
    public function the_partial_publishes_the_broadcaster_key_and_cluster_when_realtime_is_on(): void
    {
        $this->enableRealtime();

        $html = view(self::PARTIAL)->render();

        $this->assertStringContainsString('name="realtime-broadcaster" content="pusher"', $html);
        $this->assertStringContainsString('name="realtime-key" content="surface-test-key"', $html);
        $this->assertStringContainsString('name="realtime-cluster" content="us2"', $html);
    }

    /**
     * @test
     *
     * The app key is public by design — Pusher hands it to every browser. The SECRET is
     * not, and there is no path from this partial to it: the config block it reads does
     * not contain one. This asserts that stays true.
     */
    public function the_partial_never_publishes_the_pusher_secret(): void
    {
        $this->enableRealtime();
        config([
            'broadcasting.connections.pusher.secret' => 'super-secret-value',
            'broadcasting.connections.pusher.app_id' => 'secret-app-id',
        ]);

        $html = view(self::PARTIAL)->render();

        // Guard against passing for the wrong reason: the partial must actually be emitting.
        $this->assertStringContainsString('realtime-broadcaster', $html);

        $this->assertStringNotContainsString('super-secret-value', $html);
        $this->assertStringNotContainsString('secret-app-id', $html);
    }

    /**
     * @test
     *
     * Re-derived from the filesystem rather than from a hardcoded list, so a NEW layout
     * that loads the bundle without the partial is a red build rather than a layout where
     * realtime silently cannot work.
     */
    public function every_layout_that_loads_the_compiled_bundle_includes_the_realtime_partial(): void
    {
        $loaders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = (string) file_get_contents($file);

            // mix('js/app.js') in layouts/main, asset('js/app.js') in the Breeze-derived ones.
            if (! preg_match('/(?:mix|asset)\(\s*[\'"]js\/app\.js[\'"]\s*\)/', $source)) {
                continue;
            }

            $loaders[$this->relative($file)] = str_contains($source, "partials._realtime-config");
        }

        $this->assertNotEmpty(
            $loaders,
            'The scan found no layout loading js/app.js. That is a broken scanner, not a clean result.'
        );

        $missing = array_keys(array_filter($loaders, fn ($included) => ! $included));

        $this->assertSame([], $missing, sprintf(
            "These views load the compiled bundle but do not include %s, so bootstrap.js runs there ".
            "with no way to learn whether realtime is on:\n  %s",
            self::PARTIAL,
            implode("\n  ", $missing)
        ));
    }

    /**
     * @test
     *
     * A private-channel subscription needs a user id, and the id comes from this meta tag.
     * Every emission of it must be inside @auth, or a logged-out visitor gets
     * content="" and bootstrap.js is asked to subscribe to the channel "user.".
     *
     * bootstrap.js refuses that independently (see RealtimeBootstrapSourceTest) — this is
     * the other half of the belt and braces, and it is the half that keeps the tag itself
     * from ever being wrong.
     */
    public function the_user_id_meta_tag_is_only_ever_emitted_to_an_authenticated_visitor(): void
    {
        $emitters = [];

        foreach ($this->bladeFiles() as $file) {
            $source = (string) file_get_contents($file);

            if (! str_contains($source, 'name="user-id"')) {
                continue;
            }

            $emitters[] = $this->relative($file);

            $this->assertMatchesRegularExpression(
                '/@auth\b(?:(?!@endauth).)*?name="user-id"(?:(?!@endauth).)*?@endauth/s',
                $source,
                $this->relative($file).' emits meta[name=user-id] outside an @auth block. '
                    ."A guest would receive content=\"\" and be asked to subscribe to \"user.\"."
            );
        }

        $this->assertNotEmpty($emitters, 'The scan found no meta[name=user-id] emitter. Broken scanner.');
    }

    /** @return string[] absolute paths */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
