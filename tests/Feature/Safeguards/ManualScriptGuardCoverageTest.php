<?php

namespace Tests\Feature\Safeguards;

use App\Support\Safeguards\ProductionDatabaseGuard;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every manually invoked PHP entry point goes through the shared guard, or declares why it cannot
 * touch a database, and the declaration is checked too.
 *
 * NO HAND-WRITTEN LIST
 * --------------------
 * The files are discovered from scripts/ and spikes/ on every run. An expected-files list would be
 * the one place a new script could be forgotten, and a guard is worth nothing if a new script can
 * skip it just by being new. A newly added script is therefore in one of three states:
 *
 *   • guarded       calls ManualScriptBootstrap::boot(__FILE__) before anything else reaches Laravel;
 *   • not-applicable carries `@manual-script-guard not-applicable: <reason>` AND its code contains
 *                   nothing that can load Laravel or open a connection;
 *   • a failure     anything else, including a script that loads nothing and declares nothing.
 *
 * Reach detection reads PHP tokens with comments removed, so a docblock that mentions "artisan"
 * neither trips it nor satisfies it.
 *
 * The rules are proven against synthetic files too, so this test cannot pass just because the
 * scanner has quietly stopped detecting anything.
 */
class ManualScriptGuardCoverageTest extends TestCase
{
    /** Directories holding manually invoked developer / QA / spike PHP entry points. */
    private const ENTRY_POINT_DIRECTORIES = ['scripts', 'spikes'];

    private const BOOT_CALL = 'ManualScriptBootstrap::boot(__FILE__';

    private const EXEMPTION = '/@manual-script-guard\s+not-applicable:\s*(\S.{19,})/';

    /** Code fragments that load Laravel, reach a database, or run Artisan. */
    private const REACHES = [
        'vendor/autoload.php', 'bootstrap/app.php', '->bootstrap(', 'Kernel', 'Illuminate\\', 'App\\',
        'DB::', 'PDO', 'pg_connect', 'pg_pconnect', 'mysqli', 'artisan', 'psql',
        'app(', 'config(', 'env(', 'getenv(',
    ];

    /** Before the guard runs, only the autoloader may be loaded. */
    private const FORBIDDEN_BEFORE_BOOT = [
        'bootstrap/app.php', '->bootstrap(', 'Kernel', 'DB::', 'PDO', 'pg_connect', 'pg_pconnect', 'mysqli',
        'app(', 'config(', '::create(', '->save(', 'artisan',
    ];

    // Commands that describe themselves as non-production tooling must refuse production.
    // Case-sensitive on purpose: Gate1Validate's "synthetic benchmark" is an offline harness with no
    // database, and a leading "Benchmark" is the command that runs one against listings.
    private const NON_PRODUCTION_COMMAND_DESCRIPTION = '/\b(CI only|DEV-ONLY|dev only|staging\/dev|pre-GA|Benchmark)\b/';

    // ── the real tree ────────────────────────────────────────────────────────

    /** @test */
    public function every_manual_php_entry_point_is_guarded_or_verifiably_not_applicable(): void
    {
        $report = self::scan(base_path(), self::ENTRY_POINT_DIRECTORIES);

        $this->assertNotEmpty($report, 'No entry points discovered. The scanner, not the tree, is broken.');

        $guarded = array_keys(array_filter($report, static fn (array $r): bool => $r['status'] === 'guarded'));
        $this->assertNotEmpty($guarded, 'No guarded entry point was discovered, so this test would pass vacuously.');
        $this->assertContains('scripts/run_normalizer_verify.php', $guarded, 'A script known to boot Laravel was not recognised as guarded.');

        $violations = array_filter($report, static fn (array $r): bool => $r['status'] === 'violation');

        $this->assertSame([], array_map(static fn (array $r): string => $r['reason'], $violations), sprintf(
            "Manual entry points that can reach a database without %s:\n%s\n\n"
                . "Boot Laravel with `\$app = \\App\\Support\\Safeguards\\ManualScriptBootstrap::boot(__FILE__);`, "
                . "or, if the script genuinely cannot touch Laravel or a database, add\n"
                . "`@manual-script-guard not-applicable: <why>` to its header.",
            'ManualScriptBootstrap',
            implode("\n", array_map(static fn (string $f, array $r): string => "  {$f}: {$r['reason']}", array_keys($violations), $violations)),
        ));
    }

    /** @test */
    public function every_command_accepting_the_override_actually_goes_through_the_guard(): void
    {
        $checked = 0;

        foreach (File::files(app_path('Console/Commands')) as $file) {
            $source = $file->getContents();
            $name = $file->getRelativePathname();
            $declaresOption = str_contains($source, '{--' . ProductionDatabaseGuard::OVERRIDE_OPTION);
            $usesTrait = (bool) preg_match('/^\s*use\s+RefusesProductionDatabase\s*;/m', $source);
            $callsGuard = str_contains($source, '$this->refusesProductionDatabase(');

            if ($declaresOption) {
                $checked++;
                $this->assertTrue($usesTrait && str_contains($source, '$this->refusesProductionDatabase(true)'),
                    "{$name} declares --" . ProductionDatabaseGuard::OVERRIDE_OPTION . ' but does not pass it to the guard. The flag would be accepted and do nothing.');
            }

            if ($usesTrait) {
                $checked++;
                $this->assertTrue($callsGuard, "{$name} uses RefusesProductionDatabase but never calls it.");
            }

            if ($callsGuard && str_contains($source, 'refusesProductionDatabase(true)')) {
                $this->assertTrue($declaresOption, "{$name} permits the override but never declares the option, so it can never be supplied.");
            }
        }

        $this->assertGreaterThan(0, $checked, 'No guarded command was found. This test would pass vacuously.');
    }

    /** @test */
    public function commands_that_describe_themselves_as_non_production_tooling_refuse_production(): void
    {
        $matched = [];

        foreach (File::files(app_path('Console/Commands')) as $file) {
            $source = $file->getContents();

            if (! preg_match('/protected\s+\$description\s*=\s*([\'"])(.*?)\1\s*;/s', $source, $m)
                || ! preg_match(self::NON_PRODUCTION_COMMAND_DESCRIPTION, $m[2])) {
                continue;
            }

            $matched[] = $file->getRelativePathname();

            $this->assertStringContainsString('$this->refusesProductionDatabase(', $source,
                "{$file->getRelativePathname()} describes itself as non-production tooling (\"{$m[2]}\") but does not refuse the production database.");
        }

        $this->assertNotEmpty($matched, 'No command matched. The description rule has stopped selecting anything.');
    }

    /** @test */
    public function fixture_seeders_refuse_production(): void
    {
        // A seeder that makes users or calls itself a test seeder creates QA fixtures.
        $matched = [];

        foreach (File::files(database_path('seeders')) as $file) {
            // Code only: DatabaseSeeder carries a commented-out `User::factory(10)->create()`.
            $source = self::codeWithoutComments($file->getContents());
            $isFixture = str_ends_with($file->getFilenameWithoutExtension(), 'TestSeeder')
                || preg_match('/User::(factory|create|firstOrCreate|updateOrCreate)\s*\(/', $source);

            if (! $isFixture) {
                continue;
            }

            $matched[] = $file->getFilename();

            $this->assertMatchesRegularExpression(
                '/ProductionDatabaseGuard::assessApplication\(\)|ProductionDatabaseRefused::unlessSafe\(/',
                $source,
                "{$file->getFilename()} creates fixture data but does not check the resolved database.",
            );
        }

        $this->assertNotEmpty($matched, 'No fixture seeder matched. The rule has stopped selecting anything.');
    }

    // ── the scanner, proven against synthetic trees ──────────────────────────

    /** @test */
    public function a_newly_added_unguarded_script_fails_the_scan(): void
    {
        $report = $this->scanSynthetic([
            'scripts/new_qa_fixture.php' => "<?php\nrequire __DIR__ . '/../vendor/autoload.php';\n\$app = require __DIR__ . '/../bootstrap/app.php';\n\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\nApp\\Models\\User::factory()->create();\n",
        ]);

        $this->assertSame('violation', $report['scripts/new_qa_fixture.php']['status']);
        $this->assertStringContainsString('unguarded', $report['scripts/new_qa_fixture.php']['reason']);
    }

    /** @test */
    public function a_new_script_in_a_nested_directory_is_discovered_too(): void
    {
        $report = $this->scanSynthetic(['spikes/phase-9/bin/probe.php' => "<?php\n\$pdo = new PDO('pgsql:host=helium;dbname=heliumdb');\n"]);

        $this->assertSame('violation', $report['spikes/phase-9/bin/probe.php']['status']);
    }

    /** @test */
    public function a_script_that_declares_nothing_fails_even_if_it_looks_harmless(): void
    {
        $report = $this->scanSynthetic(['scripts/harmless.php' => "<?php\necho 'hello';\n"]);

        $this->assertSame('violation', $report['scripts/harmless.php']['status']);
        $this->assertStringContainsString('declares nothing', $report['scripts/harmless.php']['reason']);
    }

    /** @test */
    public function a_not_applicable_declaration_is_verified_against_the_code(): void
    {
        $report = $this->scanSynthetic([
            'scripts/honest.php' => "<?php\n// @manual-script-guard not-applicable: prints static text and never loads Laravel.\necho 'hi';\n",
            'scripts/liar.php' => "<?php\n// @manual-script-guard not-applicable: prints static text and never loads Laravel.\nrequire 'vendor/autoload.php';\nDB::table('users')->delete();\n",
            'scripts/terse.php' => "<?php\n// @manual-script-guard not-applicable: ok\necho 'hi';\n",
            // A comment that mentions artisan must not be mistaken for code.
            'scripts/commented.php' => "<?php\n/** @manual-script-guard not-applicable: formats text; NOT an artisan command, never boots Laravel. */\necho 'x';\n",
        ]);

        $this->assertSame('not-applicable', $report['scripts/honest.php']['status']);
        $this->assertSame('not-applicable', $report['scripts/commented.php']['status']);
        $this->assertSame('violation', $report['scripts/liar.php']['status']);
        $this->assertStringContainsString('DB::', $report['scripts/liar.php']['reason']);
        $this->assertSame('violation', $report['scripts/terse.php']['status'], 'A reasonless declaration was accepted.');
    }

    /** @test */
    public function the_guard_must_come_before_laravel_is_bootstrapped(): void
    {
        $report = $this->scanSynthetic([
            'scripts/good.php' => "<?php\nrequire __DIR__ . '/../vendor/autoload.php';\n\$app = \\App\\Support\\Safeguards\\ManualScriptBootstrap::boot(__FILE__);\nDB::table('x')->count();\n",
            'scripts/late.php' => "<?php\nrequire __DIR__ . '/../vendor/autoload.php';\n\$app = require __DIR__ . '/../bootstrap/app.php';\n\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n\\App\\Support\\Safeguards\\ManualScriptBootstrap::boot(__FILE__);\n",
            'scripts/commented_out.php' => "<?php\n// \\App\\Support\\Safeguards\\ManualScriptBootstrap::boot(__FILE__);\n\$app = require __DIR__ . '/../bootstrap/app.php';\n",
        ]);

        $this->assertSame('guarded', $report['scripts/good.php']['status']);
        $this->assertSame('violation', $report['scripts/late.php']['status']);
        $this->assertStringContainsString('before the guard', $report['scripts/late.php']['reason']);
        $this->assertSame('violation', $report['scripts/commented_out.php']['status'], 'A commented-out guard call was accepted.');
    }

    // ── scanner ──────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $directories
     * @return array<string, array{status: string, reason: string}>
     */
    private static function scan(string $root, array $directories): array
    {
        $report = [];

        foreach ($directories as $directory) {
            if (! is_dir("{$root}/{$directory}")) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$root}/{$directory}", \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($root) + 1);
                $report[$relative] = self::classify((string) file_get_contents($file->getPathname()));
            }
        }

        ksort($report);

        return $report;
    }

    /** @return array{status: string, reason: string} */
    private static function classify(string $source): array
    {
        $code = self::codeWithoutComments($source);
        $bootAt = strpos($code, self::BOOT_CALL);
        $declared = preg_match(self::EXEMPTION, $source) === 1;
        $reaches = array_values(array_filter(self::REACHES, static fn (string $needle): bool => str_contains($code, $needle)));

        if ($bootAt !== false && $declared) {
            return ['status' => 'violation', 'reason' => 'is both guarded and declared not-applicable; pick one'];
        }

        if ($bootAt !== false) {
            $before = substr($code, 0, $bootAt);
            $early = array_values(array_filter(self::FORBIDDEN_BEFORE_BOOT, static fn (string $needle): bool => str_contains($before, $needle)));

            return $early === []
                ? ['status' => 'guarded', 'reason' => '']
                : ['status' => 'violation', 'reason' => 'reaches Laravel before the guard runs (' . implode(', ', $early) . ')'];
        }

        if ($declared) {
            return $reaches === []
                ? ['status' => 'not-applicable', 'reason' => '']
                : ['status' => 'violation', 'reason' => 'declares not-applicable but its code contains ' . implode(', ', $reaches)];
        }

        return $reaches === []
            ? ['status' => 'violation', 'reason' => 'declares nothing; guard it or state why it cannot reach a database']
            : ['status' => 'violation', 'reason' => 'unguarded: its code contains ' . implode(', ', $reaches)];
    }

    private static function codeWithoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /** @param array<string, string> $files */
    private function scanSynthetic(array $files): array
    {
        $root = sys_get_temp_dir() . '/manual-script-guard-scan-' . getmypid() . '-' . bin2hex(random_bytes(4));

        try {
            foreach ($files as $relative => $contents) {
                File::ensureDirectoryExists(dirname("{$root}/{$relative}"));
                file_put_contents("{$root}/{$relative}", $contents);
            }

            return self::scan($root, self::ENTRY_POINT_DIRECTORIES);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
