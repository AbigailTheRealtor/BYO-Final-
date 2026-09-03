<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The environment that matters is the WORKER's, not the launcher's.
 *
 * WHAT THIS COVERS THAT THE NEIGHBOURING SUITES DO NOT
 * ----------------------------------------------------
 * Two suites already assert that the deployment entrypoints establish a correct
 * environment, and both were green while production served stack traces:
 *
 *   ProductionRuntimeEnvironmentTest  APP_ENV=production / APP_DEBUG=false are
 *                                     exported, and a fresh Laravel process
 *                                     started underneath them resolves them.
 *   PhpIniScanDirTest                 PHP_INI_SCAN_DIR is composed rather than
 *                                     replaced, so the platform's extensions
 *                                     survive alongside deploy/php.
 *
 * Both measure the LAUNCHER. `php artisan serve` does not answer HTTP: it spawns
 * `php -S <host>:<port> server.php`, and that child is the web server. What the
 * child inherits is decided by
 * Illuminate\Foundation\Console\ServeCommand::startProcess():
 *
 *     new Process($this->serverCommand(), null, collect($_ENV)->mapWithKeys(
 *         function ($value, $key) use ($hasEnvironment) {
 *             if ($this->option('no-reload') || ! $hasEnvironment) {
 *                 return [$key => $value];          // whole environment
 *             }
 *             return in_array($key, [
 *                 'APP_ENV', 'LARAVEL_SAIL', 'PHP_CLI_SERVER_WORKERS',
 *                 'PHP_IDE_CONFIG', 'SYSTEMROOT', 'XDEBUG_CONFIG',
 *                 'XDEBUG_MODE', 'XDEBUG_SESSION',
 *             ]) ? [$key => $value] : [$key => false];   // false = DELETE
 *         })->all());
 *
 * A `.env` file exists, so `$hasEnvironment` is true and the allowlist branch is
 * taken unless `--no-reload` is passed. `APP_ENV` is on the list. `APP_DEBUG` is
 * not, and neither is `PHP_INI_SCAN_DIR`. Both were deleted from the worker,
 * which fell back to `.env` — where APP_DEBUG=true — and rendered full exception
 * traces, SQL and absolute paths to anyone who could provoke a 500. The same
 * strip is why deploy/php/uploads.ini never reached the process handling
 * uploads, no matter how carefully the launcher composed the scan dir.
 *
 * So these tests deliberately assert the SAME properties as their neighbours,
 * one process further down. That is not duplication — it is the boundary the
 * neighbours cannot see across.
 *
 * HOW THEY WORK
 * -------------
 * They read the real `exec php artisan serve …` line out of the deployment
 * scripts, run THAT invocation on an ephemeral loopback port with the
 * environment the scripts establish, then read the resulting worker's own
 * `/proc/<pid>/environ` back. Deleting `--no-reload` from either script makes
 * them fail.
 *
 * The scan dir is obtained by calling `configure_php_ini_scan_dir` from
 * deploy/lib/php-runtime.sh — the shipped helper, not a copy of its logic — so
 * this suite tests the interaction with it and never re-implements it.
 *
 * A negative control (test_the_harness_detects_a_worker_that_lost_the_contract)
 * runs the same harness WITHOUT the flag and requires it to observe the failure,
 * so a harness that silently stopped measuring anything cannot pass vacuously.
 *
 * Nothing here migrates, seeds, writes to the database, binds port 5000, or
 * reaches the network. Servers bind 127.0.0.1 on an ephemeral port and are
 * stopped in the same test that started them.
 */
class ServeWorkerRuntimeEnvironmentTest extends TestCase
{
    private const REQUIRED_ENV   = 'production';
    private const REQUIRED_DEBUG = 'false';

    /** Every process this test starts, so tearDown can guarantee none survives. */
    private array $started = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir('/proc') || ! is_readable('/proc/self/environ')) {
            $this->markTestSkipped(
                'Reading another process\'s environment requires a readable /proc; '
                . 'the deployment target is Linux, so this is a local-tooling skip, not a pass.'
            );
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->started as $handle) {
            $this->stopServer($handle);
        }

        $this->started = [];

        parent::tearDown();
    }

    // ══ the contract ════════════════════════════════════════════════════════

    /**
     * @dataProvider servingEntrypoints
     */
    public function test_the_http_worker_receives_the_production_environment(string $script): void
    {
        $env = $this->workerEnvironment($this->startServerFromScript($script));

        $this->assertSame(
            self::REQUIRED_ENV,
            $env['APP_ENV'] ?? null,
            "{$script}: the process answering HTTP must run with APP_ENV=production"
        );

        $this->assertArrayHasKey(
            'APP_DEBUG',
            $env,
            "{$script}: APP_DEBUG was stripped from the worker — it will fall back to .env. "
            . 'This is exactly the failure --no-reload exists to prevent.'
        );

        $this->assertSame(
            self::REQUIRED_DEBUG,
            $env['APP_DEBUG'],
            "{$script}: the process answering HTTP must run with APP_DEBUG=false"
        );
    }

    /**
     * Shell variables are not the claim; what Laravel resolves inside the worker is.
     *
     * @dataProvider servingEntrypoints
     */
    public function test_the_http_worker_resolves_production_config(string $script): void
    {
        $resolved = $this->resolveConfigUnder($this->workerEnvironment($this->startServerFromScript($script)));

        $this->assertSame('production', $resolved['env'], "{$script}: worker resolved app.env={$resolved['env']}");
        $this->assertSame('false', $resolved['debug'], "{$script}: worker resolved app.debug={$resolved['debug']}");
    }

    /**
     * The scan dir composed by deploy/lib/php-runtime.sh must SURVIVE the handover.
     *
     * PhpIniScanDirTest proves the composition is right. This proves it is still
     * there one process later — the strip discarded it wholesale, and the
     * interpreter's own default was reinstated in its place, so a correct
     * composition upstream produced no effect at all downstream.
     *
     * @dataProvider servingEntrypoints
     */
    public function test_the_http_worker_keeps_the_composed_ini_scan_dir(string $script): void
    {
        $env = $this->workerEnvironment($this->startServerFromScript($script));

        $scanDir = $env['PHP_INI_SCAN_DIR'] ?? null;

        $this->assertNotNull($scanDir, "{$script}: PHP_INI_SCAN_DIR did not reach the worker at all");

        $components = explode(':', $scanDir);

        $this->assertSame(
            realpath(base_path('deploy/php')),
            realpath((string) end($components)),
            "{$script}: deploy/php must be the LAST scan-dir component in the worker, "
            . 'so its directives win over the platform defaults'
        );

        $this->assertGreaterThan(
            1,
            count($components),
            "{$script}: the worker's scan dir lost the platform's own directory — "
            . 'it received a replacement rather than the composed value'
        );

        $this->assertSame(
            $this->composedScanDir(),
            $scanDir,
            "{$script}: the worker's scan dir differs from what configure_php_ini_scan_dir composes"
        );
    }

    /**
     * The consequence, asserted rather than the string.
     *
     * A worker that lost the platform directory loses PDO with it, and the whole
     * application follows. The platform path is a Nix store hash that moves with
     * the channel, so the durable claim is "the worker can still reach a
     * database", not any particular directory name.
     *
     * @dataProvider servingEntrypoints
     */
    public function test_the_http_worker_keeps_the_platform_php_extensions(string $script): void
    {
        $loaded = $this->loadedExtensionsUnder($this->workerEnvironment($this->startServerFromScript($script)));

        foreach (['pdo', 'pdo_pgsql', 'session', 'mbstring', 'openssl', 'tokenizer'] as $required) {
            $this->assertContains(
                $required,
                $loaded,
                "{$script}: the worker lost the {$required} extension"
            );
        }
    }

    /**
     * deploy/php/uploads.ini exists for the request-handling process specifically.
     *
     * @dataProvider servingEntrypoints
     */
    public function test_the_http_worker_applies_the_deploy_php_upload_limits(string $script): void
    {
        $ini = $this->readIniUnder(
            $this->workerEnvironment($this->startServerFromScript($script)),
            ['upload_max_filesize', 'post_max_size', 'max_file_uploads']
        );

        $expected = $this->expectedUploadDirectives();

        foreach ($expected as $directive => $value) {
            $this->assertSame(
                $value,
                $ini[$directive],
                "{$script}: {$directive} from deploy/php/uploads.ini did not reach the worker"
            );
        }
    }

    // ══ the security property, over HTTP ════════════════════════════════════

    /**
     * A 500 from the patched server must reveal nothing about the application.
     *
     * The probe is a read-only route parameter of the wrong type: it produces a
     * QueryException from a SELECT, mutating nothing.
     *
     * @dataProvider servingEntrypoints
     */
    public function test_a_server_error_never_renders_a_debug_page(string $script): void
    {
        $handle = $this->startServerFromScript($script);

        [$status, $body] = $this->request($handle, '/seller/agent/auction/view/abc');

        $this->assertNotSame(
            0,
            $status,
            "{$script}: the error probe got no HTTP response at all. The worker never answered, so the "
            . 'debug-leak assertions below did not run — that is a harness failure, not an absent error page.'
        );

        if ($status !== 500) {
            $this->markTestSkipped(
                "The error probe returned HTTP {$status} rather than 500, so there is no error page to inspect."
            );
        }

        foreach ($this->debugLeakSignatures() as $label => $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $body,
                "{$script}: the production error page leaked {$label}"
            );
        }

        $this->assertLessThan(
            50_000,
            strlen($body),
            "{$script}: a non-debug error page is small; " . strlen($body) . ' bytes indicates a rendered trace'
        );
    }

    // ══ the negative control ════════════════════════════════════════════════

    /**
     * Proves the harness can still SEE the failure it is guarding against.
     *
     * Without this, a harness that quietly stopped reading the worker — a changed
     * /proc layout, a missed child process — would report every test above green
     * while measuring nothing. Runs on loopback and is stopped immediately.
     */
    public function test_the_harness_detects_a_worker_that_lost_the_contract(): void
    {
        $env = $this->workerEnvironment(
            $this->startServer($this->serveInvocation(base_path('deploy/start-serving.sh'), null, false))
        );

        $this->assertSame(
            self::REQUIRED_ENV,
            $env['APP_ENV'] ?? null,
            'Control: APP_ENV is on ServeCommand\'s allowlist and must survive even without --no-reload'
        );

        $this->assertArrayNotHasKey(
            'APP_DEBUG',
            $env,
            'Control: without --no-reload, ServeCommand must be observed stripping APP_DEBUG. '
            . 'If this fails, either Laravel changed or the harness is no longer reading the real worker — '
            . 'and the tests above have stopped proving anything.'
        );

        $this->assertNotSame(
            $this->composedScanDir(),
            $env['PHP_INI_SCAN_DIR'] ?? null,
            'Control: the composed scan dir must be observed NOT surviving without --no-reload'
        );

        $this->assertSame(
            'true',
            $this->resolveConfigUnder($env)['debug'],
            'Control: the stripped worker must fall back to .env and resolve app.debug=true'
        );
    }

    /** Reading the flag out of the scripts is what ties these tests to production. */
    public function servingEntrypoints(): array
    {
        return [
            'start-serving.sh'    => ['deploy/start-serving.sh'],
            'start-production.sh' => ['deploy/start-production.sh'],
        ];
    }

    // ══ harness ═════════════════════════════════════════════════════════════

    /**
     * The scan dir the shipped helper composes, obtained by CALLING the helper.
     *
     * deploy/lib/php-runtime.sh is the single definition of this logic (PR #120);
     * re-deriving it here would be a second definition that could drift from the
     * one production uses, which is the whole reason it was extracted.
     */
    private function composedScanDir(): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $script = <<<'SH'
set -euo pipefail
export APP_ENV=production
export APP_DEBUG=false
. "$PWD/deploy/lib/php-runtime.sh"
configure_php_ini_scan_dir "$PWD/deploy/php"
printf '%s' "$PHP_INI_SCAN_DIR"
SH;

        $value = $this->runProcess(['bash', '-c', $script], [
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
        ]);

        $this->assertNotSame('', trim($value), 'configure_php_ini_scan_dir produced an empty PHP_INI_SCAN_DIR');

        return $cached = trim($value);
    }

    /**
     * The directives deploy/php/uploads.ini actually declares.
     *
     * Parsed rather than hard-coded so raising a limit in the ini is not also a
     * test edit — the claim is "the file reaches the worker", not "the file says 50M".
     *
     * @return array<string, string>
     */
    private function expectedUploadDirectives(): array
    {
        $ini = parse_ini_file(base_path('deploy/php/uploads.ini'), false, INI_SCANNER_RAW);

        $this->assertIsArray($ini, 'deploy/php/uploads.ini is not parseable');

        $wanted = [];

        foreach (['upload_max_filesize', 'post_max_size', 'max_file_uploads'] as $directive) {
            $this->assertArrayHasKey($directive, $ini, "deploy/php/uploads.ini no longer declares {$directive}");

            $wanted[$directive] = trim((string) $ini[$directive]);
        }

        return $wanted;
    }

    /**
     * Extract the real `exec php artisan serve …` invocation from a deployment
     * script and retarget it at loopback and an ephemeral port.
     *
     * Reading it rather than hard-coding it is deliberate: if `--no-reload` is
     * removed from the script, this harness runs the weakened command and the
     * assertions fail. A hard-coded invocation would keep passing forever.
     *
     * @return array{0: array<int, string>, 1: int}
     */
    private function serveInvocation(string $scriptPath, ?int $port = null, bool $keepFlags = true): array
    {
        $line = null;

        foreach (preg_split('/\R/', (string) file_get_contents($scriptPath)) ?: [] as $candidate) {
            $trimmed = ltrim($candidate);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^exec\s+php\s+artisan\s+serve\b/', $trimmed)) {
                $line = $trimmed;
                break;
            }
        }

        $this->assertNotNull($line, "No executable `exec php artisan serve` line found in {$scriptPath}");

        $port ??= $this->freePort();

        $rewritten = [];

        foreach (preg_split('/\s+/', trim(substr($line, strlen('exec ')))) as $arg) {
            if (str_starts_with($arg, '--host=')) {
                $rewritten[] = '--host=127.0.0.1';
                continue;
            }

            if (str_starts_with($arg, '--port=')) {
                $rewritten[] = '--port=' . $port;
                continue;
            }

            if (! $keepFlags && $arg === '--no-reload') {
                continue;
            }

            $rewritten[] = $arg;
        }

        $this->assertSame('php', $rewritten[0], "Unexpected serve invocation in {$scriptPath}: {$line}");

        return [$rewritten, $port];
    }

    /** @return array{proc: resource, pid: int, port: int, worker: int} */
    private function startServerFromScript(string $relativeScript): array
    {
        return $this->startServer($this->serveInvocation(base_path($relativeScript)));
    }

    /**
     * @param  array{0: array<int, string>, 1: int}  $invocation
     * @return array{proc: resource, pid: int, port: int, worker: int}
     */
    private function startServer(array $invocation): array
    {
        [$argv, $port] = $invocation;

        // Exactly what the entrypoints establish before they exec: the production
        // flags, and the scan dir the shipped helper composes.
        $env = [
            'PATH'             => getenv('PATH') ?: '/usr/bin:/bin',
            'HOME'             => getenv('HOME') ?: sys_get_temp_dir(),
            'APP_ENV'          => self::REQUIRED_ENV,
            'APP_DEBUG'        => self::REQUIRED_DEBUG,
            'PHP_INI_SCAN_DIR' => $this->composedScanDir(),
        ];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $proc = proc_open($argv, $descriptors, $pipes, base_path(), $env);

        $this->assertIsResource($proc, 'Could not start the serve process');

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $handle = [
            'proc'   => $proc,
            'pipes'  => $pipes,
            'pid'    => proc_get_status($proc)['pid'],
            'port'   => $port,
            'worker' => 0,
        ];

        $this->started[] = &$handle;

        $handle['worker'] = $this->awaitWorker($port);

        $this->awaitAcceptingConnections($port);

        return $handle;
    }

    /**
     * The worker PID appearing in /proc does not mean the socket is accepting.
     *
     * Without this wait the first request can fail at the transport layer and be
     * read as "no error page to inspect", which turns the security assertion
     * below into a skip. A skip in that test is indistinguishable from a pass at
     * a glance, so the readiness wait is part of the assertion, not tidying.
     */
    private function awaitAcceptingConnections(int $port): void
    {
        $deadline = microtime(true) + 20.0;

        while (microtime(true) < $deadline) {
            $socket = @stream_socket_client(
                'tcp://127.0.0.1:' . $port,
                $errno,
                $errstr,
                0.5,
                STREAM_CLIENT_CONNECT
            );

            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }

        $this->fail("The worker on 127.0.0.1:{$port} never accepted a connection within 20s");
    }

    /** The `php -S` child is the web server; the launcher merely supervises it. */
    private function awaitWorker(int $port): int
    {
        $deadline = microtime(true) + 20.0;

        while (microtime(true) < $deadline) {
            foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $path) {
                $cmdline = @file_get_contents($path);

                if ($cmdline === false || $cmdline === '') {
                    continue;
                }

                $argv = explode("\0", $cmdline);

                if (in_array('-S', $argv, true) && in_array('127.0.0.1:' . $port, $argv, true)) {
                    return (int) basename(dirname($path));
                }
            }

            usleep(100_000);
        }

        $this->fail("No `php -S 127.0.0.1:{$port}` worker appeared within 20s");
    }

    /** @return array<string, string> */
    private function workerEnvironment(array $handle): array
    {
        $raw = @file_get_contents('/proc/' . $handle['worker'] . '/environ');

        $this->assertIsString($raw, 'Could not read the worker environment from /proc');

        $env = [];

        foreach (explode("\0", $raw) as $pair) {
            if ($pair === '' || ! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * Boot Laravel under a given environment and read the resolved config back.
     *
     * @param  array<string, string>  $env
     * @return array{env: string, debug: string}
     */
    private function resolveConfigUnder(array $env): array
    {
        $code = 'require ' . var_export(base_path('vendor/autoload.php'), true) . '; '
            . '$app = require getenv("BOOTSTRAP_APP"); '
            . '$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); '
            . 'echo config("app.env"), "\n", var_export((bool) config("app.debug"), true), "\n";';

        $env['BOOTSTRAP_APP'] = base_path('bootstrap/app.php');

        $output = $this->runProcess([PHP_BINARY, '-r', $code], $env);

        [$resolvedEnv, $resolvedDebug] = array_pad(explode("\n", trim($output)), 2, '');

        return ['env' => trim($resolvedEnv), 'debug' => trim($resolvedDebug)];
    }

    /**
     * @param  array<string, string>  $env
     * @return array<int, string>
     */
    private function loadedExtensionsUnder(array $env): array
    {
        $output = $this->runProcess([PHP_BINARY, '-r', 'echo implode("\n", get_loaded_extensions());'], $env);

        return array_map('strtolower', array_filter(array_map('trim', explode("\n", $output))));
    }

    /**
     * @param  array<string, string>  $env
     * @param  array<int, string>  $directives
     * @return array<string, string>
     */
    private function readIniUnder(array $env, array $directives): array
    {
        $parts = [];

        foreach ($directives as $directive) {
            $parts[] = "echo ini_get('{$directive}'), \"\\n\";";
        }

        $output = $this->runProcess([PHP_BINARY, '-r', implode(' ', $parts)], $env);

        $values = array_map('trim', array_pad(explode("\n", trim($output)), count($directives), ''));

        return array_combine($directives, $values);
    }

    /**
     * @param  array<int, string>  $argv
     * @param  array<string, string>  $env
     */
    private function runProcess(array $argv, array $env): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $proc = proc_open($argv, $descriptors, $pipes, base_path(), $env);

        $this->assertIsResource($proc, 'Could not start a process: ' . implode(' ', $argv));

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exit = proc_close($proc);

        $this->assertSame(0, $exit, 'Process exited ' . $exit . ': ' . $stderr);

        return $stdout;
    }

    /** @return array{0: int, 1: string} */
    private function request(array $handle, string $path): array
    {
        $context = stream_context_create(['http' => [
            'ignore_errors' => true,
            'timeout'       => 30,
        ]]);

        $status = 0;
        $body   = false;

        // Retried because a transport-level failure must never be mistaken for a
        // response worth inspecting; see awaitAcceptingConnections().
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $http_response_header = [];

            $body = @file_get_contents('http://127.0.0.1:' . $handle['port'] . $path, false, $context);

            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                    $status = (int) $m[1];
                }
            }

            if ($status !== 0) {
                break;
            }

            usleep(250_000);
        }

        return [$status, (string) $body];
    }

    /** @return array<string, string> */
    private function debugLeakSignatures(): array
    {
        return [
            'a stack frame'         => 'vendor/laravel/framework',
            'the application path'  => base_path('app'),
            'SQL query text'        => 'select * from',
            'a SQLSTATE code'       => 'SQLSTATE',
            'an exception class'    => 'Illuminate\Database\QueryException',
            'a stack trace marker'  => 'Stack trace',
            'the Whoops debug page' => 'Whoops',
        ];
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        $this->assertIsResource($socket, "Could not reserve an ephemeral port: {$errstr}");

        $name = stream_socket_get_name($socket, false);

        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    private function stopServer(array $handle): void
    {
        if (! empty($handle['worker'])) {
            @posix_kill($handle['worker'], SIGTERM);
        }

        if (is_resource($handle['proc'] ?? null)) {
            @proc_terminate($handle['proc'], SIGTERM);

            foreach ($handle['pipes'] ?? [] as $pipe) {
                if (is_resource($pipe)) {
                    @fclose($pipe);
                }
            }

            @proc_close($handle['proc']);
        }
    }
}
