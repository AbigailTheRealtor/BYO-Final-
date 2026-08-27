<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Exactly one thing may apply production migrations: deploy/start-production.sh.
 *
 * WHAT THIS GUARDS
 * ----------------
 * The Replit `[postMerge]` hook ran `scripts/post-merge.sh`, which independently
 * executed `php artisan migrate --force --no-interaction`. That made the platform's
 * merge action a SECOND migration owner, firing outside the explicit deployment
 * flow, from whatever branch happened to be checked out in the workspace, with no
 * backup, no preflight and no lock.
 *
 * This application is on Laravel 8, which has no `migrate --isolated` — the built-in
 * migration lock arrived in Laravel 9. There is no mutex available, so concurrency
 * safety rests ENTIRELY on single ownership. A second owner is not a stylistic
 * problem; it is the only thing that could have produced two concurrent migrators.
 *
 * WHY THE EXISTING COVERAGE MISSED IT
 * -----------------------------------
 * `DeploymentMigrationReadinessTest::test_no_other_deploy_script_migrates()` globs
 * `deploy/*.sh`. `scripts/post-merge.sh` is not in `deploy/`, so it was never
 * examined. This test closes that gap by resolving the hook's target from `.replit`
 * itself rather than assuming a path.
 *
 * WHY THIS IS NOT A VACUOUS GREP
 * ------------------------------
 * Three properties, each asserted rather than assumed:
 *
 *   1. The file inspected is the one the platform actually runs — the path is PARSED
 *      out of `.replit`'s `[postMerge]` stanza, so renaming the script or repointing
 *      the hook cannot leave this test quietly checking a file nobody executes.
 *   2. Comments cannot launder a migration. Full-line comments are stripped before
 *      matching, so a line describing migrate reads as absent — and the stripper is
 *      itself proven, in both directions, by a self-check against synthetic content.
 *   3. The authorized owner is asserted POSITIVELY. If someone "fixed" this by
 *      deleting migrations everywhere, the start-production assertion fails.
 *
 * The script is never executed: it appends to .env, installs packages and builds
 * assets.
 */
class PostMergeMigrationOwnershipTest extends TestCase
{
    /** Commands that apply or destroy schema. Any of these is a migration invocation. */
    private const MIGRATION_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:reset',
        'migrate:refresh',
        'db:wipe',
        'schema:load',
    ];

    private function replit(): string
    {
        $path = base_path('.replit');

        $this->assertFileExists($path, '.replit must be readable');

        return (string) file_get_contents($path);
    }

    /**
     * The script path the Replit `[postMerge]` hook actually invokes, parsed from
     * `.replit` rather than assumed.
     */
    private function postMergeScriptRelativePath(): string
    {
        $inPostMerge = false;

        foreach (preg_split('/\R/', $this->replit()) ?: [] as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '[')) {
                $inPostMerge = $trimmed === '[postMerge]';

                continue;
            }

            if ($inPostMerge && preg_match('/^path\s*=\s*"([^"]+)"/', $trimmed, $m) === 1) {
                return $m[1];
            }
        }

        $this->fail('.replit must declare a [postMerge] path — this test cannot verify a hook it cannot find');
    }

    /**
     * Executable lines only: full-line shell comments and blank lines removed.
     *
     * @return list<string>
     */
    private function executableLines(string $script): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $script) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $lines[] = $trimmed;
        }

        return $lines;
    }

    /**
     * Executable artisan migration invocations found in a script.
     *
     * @return list<string>
     */
    private function migrationInvocations(string $script): array
    {
        $found = [];

        foreach ($this->executableLines($script) as $line) {
            foreach (self::MIGRATION_COMMANDS as $command) {
                // `artisan migrate` followed by end, whitespace or a flag — so
                // `migrate:fresh` is not mistaken for bare `migrate` and vice versa.
                if (preg_match('/\bartisan\s+' . preg_quote($command, '/') . '(?![\w:-])/', $line) === 1) {
                    $found[] = $line;

                    continue 2;
                }
            }
        }

        return $found;
    }

    // ── the detector must actually work, in both directions ─────────────────

    public function test_the_detector_finds_an_executable_migration_and_ignores_a_commented_one(): void
    {
        // Positive control: without this, an over-eager comment stripper or a broken
        // regex would make every assertion below pass by finding nothing, anywhere.
        $this->assertSame(
            ['php artisan migrate --force --no-interaction'],
            $this->migrationInvocations(
                "#!/bin/bash\n# php artisan migrate --force   <- a comment, must be ignored\nphp artisan migrate --force --no-interaction\n"
            ),
            'The detector must find a real migration and ignore a commented one'
        );

        // Negative control: a script that only mentions migrate in prose is clean.
        $this->assertSame(
            [],
            $this->migrationInvocations("#!/bin/bash\n# we deliberately do not run php artisan migrate here\ncomposer install\n"),
            'A comment mentioning migrate must not register as an executable migration'
        );

        // Destructive variants are distinguished, not conflated.
        $this->assertSame(
            ['php artisan migrate:fresh'],
            $this->migrationInvocations("php artisan migrate:fresh\n"),
            'Destructive variants must be detected in their own right'
        );
    }

    // ── the hook is real and points at a real script ────────────────────────

    public function test_the_replit_post_merge_hook_targets_a_real_script(): void
    {
        $relative = $this->postMergeScriptRelativePath();

        $this->assertSame(
            'scripts/post-merge.sh',
            $relative,
            'The [postMerge] hook target moved — this test must follow it, not a stale path'
        );

        $this->assertFileExists(
            base_path($relative),
            'The [postMerge] hook must point at a script that exists'
        );
    }

    // ── the regression ──────────────────────────────────────────────────────

    public function test_the_post_merge_hook_never_migrates_production(): void
    {
        $relative = $this->postMergeScriptRelativePath();
        $script   = (string) file_get_contents(base_path($relative));

        $invocations = $this->migrationInvocations($script);

        $this->assertSame(
            [],
            $invocations,
            "The Replit [postMerge] hook ({$relative}) must not apply migrations. Laravel 8 has no "
            . 'migrate --isolated, so a second migration owner has no lock to protect it. Found: '
            . implode(' | ', $invocations)
        );
    }

    // ── the authorized owner still owns it ──────────────────────────────────

    public function test_start_production_remains_the_sole_authorized_migration_owner(): void
    {
        $start = (string) file_get_contents(base_path('deploy/start-production.sh'));

        $this->assertNotEmpty(
            $this->migrationInvocations($start),
            'deploy/start-production.sh must remain the one place production migrations run'
        );
    }

    public function test_no_script_outside_the_authorized_owner_migrates(): void
    {
        $offenders = [];

        $candidates = array_merge(
            glob(base_path('scripts/*.sh')) ?: [],
            glob(base_path('deploy/*.sh')) ?: []
        );

        foreach ($candidates as $path) {
            if (realpath($path) === realpath(base_path('deploy/start-production.sh'))) {
                continue;
            }

            if ($this->migrationInvocations((string) file_get_contents($path)) !== []) {
                $offenders[] = str_replace(base_path() . '/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only deploy/start-production.sh may migrate. Offenders: ' . implode(', ', $offenders)
        );
    }
}
