<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The PHP runtime the production entrypoints hand to Laravel must still have a
 * database driver.
 *
 * WHAT WENT WRONG
 * ---------------
 * All three entrypoints configured the upload-limit overlay like this:
 *
 *     export PHP_INI_SCAN_DIR="$PWD/deploy/php"
 *
 * `PHP_INI_SCAN_DIR` REPLACES the interpreter's own scan directory; it does not
 * add to it. On this platform that directory is where every extension is
 * declared, so the assignment above silently unloaded all of them. A production
 * PHP dropped from 54 extensions to 12 — no PDO, no pdo_pgsql, no tokenizer —
 * while `deploy/php/uploads.ini` was applied exactly as intended.
 *
 * The visible symptom was a refusal to serve. `deploy:migrations-pending` calls
 * `Migrator::repositoryExists()`, that call threw before it could reach a
 * database, and the fail-closed gate correctly reported `repository_unreadable`
 * and exit 2. Nothing was wrong with the database, the credentials, the
 * migrations table or the schema — the process asking the question had no way
 * to ask it.
 *
 * WHY THE OBVIOUS SHORTHAND IS NOT THE FIX
 * ----------------------------------------
 * PHP documents an empty path element in `PHP_INI_SCAN_DIR` as meaning "the
 * compiled-in default", which would make `":$PWD/deploy/php"` the one-character
 * repair. It does not work here, and the reason is worth recording: the
 * substituted value is `PHP_CONFIG_FILE_SCAN_DIR`, and on this build that
 * constant is defined but EMPTY. The real default arrives by another route and
 * is only ever visible by asking the interpreter. Every colon form — leading,
 * trailing and doubled — was measured on this platform and every one of them
 * loses PDO. So the default directory has to be discovered and named
 * explicitly, which is what `deploy/lib/php-runtime.sh` does.
 *
 * HOW THESE TESTS PROVE IT
 * ------------------------
 * Not by reading the scripts. Each entrypoint is really executed with a fake
 * `php` on PATH that delegates ONLY `--ini` / `-i` to the real interpreter and
 * intercepts everything else — so the discovery logic genuinely runs while no
 * Artisan command, and in particular no migration, can. The `PHP_INI_SCAN_DIR`
 * the script actually exported is read back out of that fake's log, and a real
 * PHP process is then started with it and asked what it can see.
 *
 * `test_the_previous_bare_override_is_what_broke_the_runtime` keeps the whole
 * file honest: it runs the same probe against the OLD value and requires the
 * damage to still be observable. If that ever stops failing, these assertions
 * have stopped being able to detect the bug and the file needs revisiting.
 */
class PhpIniScanDirTest extends TestCase
{
    /** Every script that boots Laravel as a production process. */
    private const PRODUCTION_ENTRYPOINTS = [
        'deploy/start-production.sh',
        'deploy/start-serving.sh',
        'deploy/scheduler.sh',
    ];

    /** The limits deploy/php/uploads.ini exists to apply. */
    private const REQUIRED_UPLOAD_LIMITS = [
        'upload_max_filesize' => '50M',
        'post_max_size'       => '150M',
        'max_file_uploads'    => '50',
        'memory_limit'        => '512M',
    ];

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                exec('rm -rf ' . escapeshellarg($dir));
            }
        }

        $this->tempDirs = [];

        parent::tearDown();
    }

    public static function entrypointProvider(): array
    {
        $cases = [];

        foreach (self::PRODUCTION_ENTRYPOINTS as $script) {
            $cases[$script] = [$script];
        }

        return $cases;
    }

    // ── harness ─────────────────────────────────────────────────────────────

    private function tempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '-' . getmypid() . '-' . count($this->tempDirs);

        exec('rm -rf ' . escapeshellarg($dir));
        mkdir($dir, 0700, true);

        $this->tempDirs[] = $dir;

        return $dir;
    }

    /**
     * A fake `php` that lets the scan-dir discovery work and stops everything else.
     *
     * The allowlist is deliberately an allowlist: only `--ini` and `-i` reach the
     * real interpreter. Any Artisan invocation — `migrate` included — is recorded
     * and answered with exit 0 without ever being run.
     */
    private function fakePhpBin(): string
    {
        $dir = $this->tempDir('ini-scan-bin');

        file_put_contents($dir . '/php', <<<'SH'
#!/usr/bin/env bash
case "${1-}" in
    --ini|-i)
        exec "$REAL_PHP" "$@"
        ;;
esac

{
    printf 'INVOKED\t%s\n' "$*"
    printf 'PHP_INI_SCAN_DIR\t%s\n' "${PHP_INI_SCAN_DIR-<unset>}"
    printf 'APP_ENV\t%s\n' "${APP_ENV-<unset>}"
    printf 'APP_DEBUG\t%s\n' "${APP_DEBUG-<unset>}"
} >> "$FAKE_LOG"

exit 0
SH);

        chmod($dir . '/php', 0700);

        return $dir;
    }

    /**
     * Really run a production entrypoint and report what its PHP child observed.
     *
     * A hostile parent environment is supplied on purpose: the scan-dir repair
     * must not have disturbed the APP_ENV / APP_DEBUG override that sits beside
     * it in the same block of every script.
     *
     * @return array{code: int, output: string, log: string, scanDirs: list<string>, envs: list<string>, debugs: list<string>, invocations: list<string>}
     */
    private function runEntrypoint(string $relativeScript): array
    {
        $bin   = $this->fakePhpBin();
        $state = $this->tempDir('ini-scan-state');
        $log   = $this->tempDir('ini-scan-log') . '/calls.log';

        touch($log);

        $cmd = sprintf(
            'env PATH=%s REAL_PHP=%s FAKE_LOG=%s DEPLOY_STATE_DIR=%s APP_ENV=local APP_DEBUG=true bash %s 2>&1',
            escapeshellarg($bin . ':' . (string) getenv('PATH')),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($log),
            escapeshellarg($state),
            escapeshellarg(base_path($relativeScript))
        );

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        $raw = is_file($log) ? (string) file_get_contents($log) : '';

        $field = static function (string $key) use ($raw): array {
            $found = [];

            foreach (preg_split('/\R/', $raw) ?: [] as $line) {
                if (str_starts_with($line, $key . "\t")) {
                    $found[] = substr($line, strlen($key) + 1);
                }
            }

            return $found;
        };

        return [
            'code'        => $code,
            'output'      => implode("\n", $output),
            'log'         => $raw,
            'scanDirs'    => $field('PHP_INI_SCAN_DIR'),
            'envs'        => $field('APP_ENV'),
            'debugs'      => $field('APP_DEBUG'),
            'invocations' => $field('INVOKED'),
        ];
    }

    /**
     * The one PHP_INI_SCAN_DIR a script configured, proven to be the only one.
     *
     * A script that exported two different values would be a bug this test must
     * not average away, so disagreement fails rather than picking the first.
     */
    private function configuredScanDir(string $relativeScript): string
    {
        $result = $this->runEntrypoint($relativeScript);

        $this->assertSame(
            0,
            $result['code'],
            "{$relativeScript} must run to completion under the fake php. Output:\n{$result['output']}"
        );

        $this->assertNotEmpty(
            $result['scanDirs'],
            "{$relativeScript} never invoked php, so this test would prove nothing. Output:\n{$result['output']}"
        );

        $distinct = array_values(array_unique($result['scanDirs']));

        $this->assertCount(
            1,
            $distinct,
            "{$relativeScript} handed different PHP_INI_SCAN_DIR values to different PHP processes: " . implode(' | ', $distinct)
        );

        $this->assertNotSame('<unset>', $distinct[0], "{$relativeScript} must export PHP_INI_SCAN_DIR before invoking php");

        return $distinct[0];
    }

    /**
     * Start a real PHP process with a given scan dir and report what it loaded.
     *
     * @return array{pdo: bool, drivers: list<string>, tokenizer: bool, extensions: list<string>, ini: array<string, string>}
     */
    private function probe(?string $scanDir): array
    {
        $code = <<<'PHP'
$ini = [];
foreach (["upload_max_filesize", "post_max_size", "max_file_uploads", "memory_limit"] as $k) {
    $ini[$k] = (string) ini_get($k);
}
$extensions = array_map("strtolower", get_loaded_extensions());
sort($extensions);
echo json_encode([
    "pdo"        => class_exists("PDO"),
    "drivers"    => class_exists("PDO") ? PDO::getAvailableDrivers() : [],
    "tokenizer"  => function_exists("token_get_all"),
    "extensions" => $extensions,
    "ini"        => $ini,
]);
PHP;

        $cmd = sprintf(
            '%s %s -r %s 2>/dev/null',
            $scanDir === null ? 'env -u PHP_INI_SCAN_DIR' : 'env ' . escapeshellarg('PHP_INI_SCAN_DIR=' . $scanDir),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($code)
        );

        $raw = (string) shell_exec($cmd);

        $decoded = json_decode(trim($raw), true);

        $this->assertIsArray(
            $decoded,
            'The probe process must return JSON. A PHP so broken it cannot even run `-r` is itself a failure. Raw output: ' . var_export($raw, true)
        );

        return $decoded;
    }

    /** What this interpreter loads when nothing overrides its scan dir. */
    private function baseline(): array
    {
        return $this->probe(null);
    }

    private function executableLines(string $relative): array
    {
        $path = base_path($relative);

        $this->assertFileExists($path, "{$relative} must exist");

        $lines = [];

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $lines[] = $trimmed;
        }

        return $lines;
    }

    // ══ A. the runtime each entrypoint actually produces ════════════════════

    /**
     * @dataProvider entrypointProvider
     */
    public function test_the_configured_runtime_keeps_pdo(string $script): void
    {
        $probe = $this->probe($this->configuredScanDir($script));

        $this->assertTrue(
            $probe['pdo'],
            "{$script} configures a PHP with no PDO class. Laravel cannot open a database connection at all, "
            . 'and `deploy:migrations-pending` reports error=repository_unreadable.'
        );
    }

    /**
     * The production database is PostgreSQL, so pdo_pgsql specifically is what
     * the readiness gate needs. Skipped rather than failed where the interpreter
     * running the suite has no pgsql to keep in the first place — that is a fact
     * about the test host, not about the entrypoints.
     *
     * @dataProvider entrypointProvider
     */
    public function test_the_configured_runtime_keeps_the_postgres_driver(string $script): void
    {
        $baseline = $this->baseline();

        if (! in_array('pgsql', $baseline['drivers'], true)) {
            $this->markTestSkipped('This interpreter has no pdo_pgsql to preserve; run on a host that does to prove this.');
        }

        $probe = $this->probe($this->configuredScanDir($script));

        $this->assertContains(
            'pgsql',
            $probe['drivers'],
            "{$script} configures a PHP without pdo_pgsql. The production database is PostgreSQL, "
            . 'so the migration repository is unreadable no matter how healthy the database is.'
        );
    }

    /**
     * @dataProvider entrypointProvider
     */
    public function test_the_configured_runtime_keeps_tokenizer(string $script): void
    {
        $probe = $this->probe($this->configuredScanDir($script));

        $this->assertTrue(
            $probe['tokenizer'],
            "{$script} configures a PHP without tokenizer. token_get_all() is missing, which is why the "
            . 'error renderer itself crashed while trying to display the original failure.'
        );
    }

    /**
     * The precise invariant: the overlay ADDS a directory, it never subtracts an
     * extension. Stated as set equality against the untouched interpreter so it
     * stays true on any host, and so a future overlay that shadows something is
     * caught as well.
     *
     * @dataProvider entrypointProvider
     */
    public function test_the_configured_runtime_loses_no_extension_at_all(string $script): void
    {
        $baseline = $this->baseline();
        $probe    = $this->probe($this->configuredScanDir($script));

        $this->assertNotEmpty($baseline['extensions'], 'The baseline interpreter must load extensions for this comparison to mean anything');

        $this->assertSame(
            [],
            array_values(array_diff($baseline['extensions'], $probe['extensions'])),
            "{$script} configures a PHP that is missing extensions the interpreter loads on its own. "
            . 'PHP_INI_SCAN_DIR replaces the default scan directory; it must be added to, never substituted for.'
        );
    }

    /**
     * The overlay still has to do its own job — this is the regression the bare
     * override was introduced for in the first place, and the repair must not
     * trade one for the other.
     *
     * @dataProvider entrypointProvider
     */
    public function test_the_configured_runtime_still_applies_the_upload_limits(string $script): void
    {
        $probe = $this->probe($this->configuredScanDir($script));

        foreach (self::REQUIRED_UPLOAD_LIMITS as $directive => $expected) {
            $this->assertSame(
                $expected,
                $probe['ini'][$directive] ?? '',
                "{$script} must still apply deploy/php/uploads.ini — {$directive} should be {$expected}."
            );
        }
    }

    // ══ B. non-vacuity ═════════════════════════════════════════════════════

    /**
     * The old value, run through the same probe.
     *
     * Without this the assertions above could all pass on a PHP that has
     * everything compiled in statically, and nobody would know they had stopped
     * testing anything.
     */
    public function test_the_previous_bare_override_is_what_broke_the_runtime(): void
    {
        $baseline = $this->baseline();

        if (! $baseline['pdo']) {
            $this->markTestSkipped('This interpreter has no PDO even by default; the failure mode cannot be demonstrated here.');
        }

        $broken = $this->probe(base_path('deploy/php'));

        $this->assertFalse(
            $broken['pdo'],
            'PHP_INI_SCAN_DIR="<root>/deploy/php" is expected to strip PDO on this platform — that was the whole bug. '
            . 'If it no longer does, the other assertions in this file can no longer detect the regression.'
        );

        // …and it did apply the uploads.ini, which is exactly why the breakage
        // looked like a working configuration change.
        $this->assertSame('50M', $broken['ini']['upload_max_filesize'] ?? '', 'The bare override did apply the overlay; that is why it looked correct.');
    }

    // ══ C. how the value is arrived at ═════════════════════════════════════

    /**
     * @dataProvider entrypointProvider
     */
    public function test_the_configured_value_contains_the_overlay_and_the_interpreter_default(string $script): void
    {
        $configured = $this->configuredScanDir($script);
        $parts      = explode(PATH_SEPARATOR, $configured);

        $this->assertContains(
            base_path('deploy/php'),
            $parts,
            "{$script} must keep <root>/deploy/php on the scan path, or the upload limits are gone."
        );

        $default = $this->interpreterDefaultScanDir();

        if ($default === '') {
            $this->markTestSkipped('This interpreter reports no default scan dir, so there is nothing to preserve.');
        }

        $this->assertContains(
            $default,
            $parts,
            "{$script} must name the interpreter's own scan directory explicitly. The documented empty-element "
            . 'shorthand (":dir") does not restore it on this build — PHP_CONFIG_FILE_SCAN_DIR is empty here.'
        );
    }

    /** No store path, no absolute toolchain path, may be frozen into the repository. */
    public function test_no_deployment_file_hardcodes_an_interpreter_store_path(): void
    {
        $offenders = [];

        $candidates = array_merge(self::PRODUCTION_ENTRYPOINTS, ['deploy/lib/php-runtime.sh', 'deploy/lib/deploy-state.sh']);

        foreach ($candidates as $relative) {
            if (! is_file(base_path($relative))) {
                continue;
            }

            $source = (string) file_get_contents(base_path($relative));

            if (preg_match('#/nix/store/#', $source) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A /nix/store path is rebuilt under a new hash whenever the Nix channel moves, so pinning one '
            . 'reintroduces exactly this outage on the next upgrade. Offenders: ' . implode(', ', $offenders)
        );

        // Non-vacuity: the matcher must be able to see one.
        $this->assertSame(1, preg_match('#/nix/store/#', 'export X=/nix/store/abc-php/lib'), 'The matcher must detect a real store path');
    }

    private function interpreterDefaultScanDir(): string
    {
        $raw = (string) shell_exec('env -u PHP_INI_SCAN_DIR ' . escapeshellarg(PHP_BINARY) . ' --ini 2>/dev/null');

        if (preg_match('/^Scan for additional \.ini files in:[ \t]*(.*)$/m', $raw, $m) !== 1) {
            return '';
        }

        $value = trim($m[1]);

        return ($value === '' || $value === '(none)') ? '' : $value;
    }

    // ══ D. the neighbouring hardening must be undisturbed ══════════════════

    /**
     * The scan-dir block sits directly beside the APP_ENV / APP_DEBUG override in
     * all three scripts, so a repair to one is exactly the kind of edit that
     * damages the other.
     *
     * @dataProvider entrypointProvider
     */
    public function test_the_production_environment_override_is_unchanged(string $script): void
    {
        $result = $this->runEntrypoint($script);

        $this->assertNotEmpty($result['envs'], "{$script} must invoke php for this to prove anything");

        foreach ($result['envs'] as $seen) {
            $this->assertSame('production', $seen, "{$script}: a php process observed APP_ENV={$seen}\nLog:\n{$result['log']}");
        }

        foreach ($result['debugs'] as $seen) {
            $this->assertSame('false', $seen, "{$script}: a php process observed APP_DEBUG={$seen}\nLog:\n{$result['log']}");
        }
    }

    /**
     * Discovering the default scan dir means running the interpreter, and that
     * must not happen while the parent's hostile APP_ENV / APP_DEBUG are still in
     * force. So the environment override has to come first in the file.
     *
     * @dataProvider entrypointProvider
     */
    public function test_the_environment_is_exported_before_the_scan_dir_is_resolved(string $script): void
    {
        $lines      = $this->executableLines($script);
        $exportedAt = null;
        $resolvedAt = null;

        foreach ($lines as $index => $line) {
            if ($exportedAt === null && preg_match('/^export APP_DEBUG=false$/', $line) === 1) {
                $exportedAt = $index;
            }

            if ($resolvedAt === null && preg_match('/(^|\s)(configure_php_ini_scan_dir|export PHP_INI_SCAN_DIR)/', $line) === 1) {
                $resolvedAt = $index;
            }
        }

        $this->assertNotNull($exportedAt, "{$script} must export APP_DEBUG=false");
        $this->assertNotNull($resolvedAt, "{$script} must configure PHP_INI_SCAN_DIR");

        $this->assertLessThan(
            $resolvedAt,
            $exportedAt,
            "{$script} resolves the PHP scan dir — which starts a PHP process — before it has overridden the "
            . 'parent environment. That process would observe the caller\'s APP_ENV and APP_DEBUG.'
        );
    }

    /** One definition, sourced by all three, rather than three copies to drift apart. */
    public function test_every_entrypoint_uses_the_shared_helper(): void
    {
        $this->assertFileExists(base_path('deploy/lib/php-runtime.sh'));

        foreach (self::PRODUCTION_ENTRYPOINTS as $script) {
            $source = implode("\n", $this->executableLines($script));

            $this->assertStringContainsString(
                'deploy/lib/php-runtime.sh',
                $source,
                "{$script} must source the shared helper. Three inline copies is how the bare override reached all three files."
            );

            $this->assertStringContainsString(
                'configure_php_ini_scan_dir',
                $source,
                "{$script} must call configure_php_ini_scan_dir."
            );

            $this->assertStringNotContainsString(
                'export PHP_INI_SCAN_DIR=',
                $source,
                "{$script} must not assign PHP_INI_SCAN_DIR directly — that is the form that replaced the interpreter's own scan dir."
            );
        }
    }
}
