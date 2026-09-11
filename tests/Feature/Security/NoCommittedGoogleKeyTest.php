<?php

namespace Tests\Feature\Security;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * No real-shaped Google API key is committed to runtime source.
 *
 * A literal key sat in a commented-out `<script>` tag in resources/views/add_listing.blade.php.
 * A Blade comment is never rendered, so it never reached a browser — but it was in the
 * repository, which is where a credential must never be. It has been removed; because it
 * remains in git history, it must also be rotated in Google Cloud.
 *
 * A Google API key is `AIza` followed by 35 characters. Test fixtures that are deliberately
 * one character short (see GooglePlacesTestEnvGuardTest) do not match, and tests/ is not
 * scanned. Offending PATHS are reported; the matched value never is.
 */
class NoCommittedGoogleKeyTest extends TestCase
{
    private const SCANNED = ['app', 'config', 'resources', 'routes', 'database', 'public'];

    private const MAX_BYTES = 5_000_000;

    /** @test */
    public function no_real_shaped_google_key_is_committed_to_runtime_source(): void
    {
        $offenders = [];

        foreach (self::SCANNED as $directory) {
            $root = base_path($directory);

            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getSize() > self::MAX_BYTES) {
                    continue;
                }

                $contents = (string) @file_get_contents($file->getPathname());

                if (preg_match('/AIza[0-9A-Za-z_\-]{35}/', $contents) === 1) {
                    $offenders[] = substr($file->getPathname(), strlen(base_path()) + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A real-shaped Google API key is committed in: ' . implode(', ', $offenders)
        );
    }
}
