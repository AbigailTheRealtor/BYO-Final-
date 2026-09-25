<?php

namespace Tests\Unit\Spatial;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Phase 4 — the Overture v2 operator load (spikes/phase-4-overture-v2-load/, and
 * .github/workflows/overture-v2-operator-load.yml) is the only path that may write the v2 corpus to
 * the live spatial cluster. It reuses the unchanged guarded tools, so its safety lives in its SHAPE,
 * and this test pins that shape: dispatch-only, one protected environment holding the one secret,
 * a pinned SHA on main, read-only preflight first, write stages behind recorded rehearsal evidence
 * and typed confirmations, exactly three migration paths, no activation, read-only SQL, and a
 * preflight script that refuses the wrong shell and the wrong target.
 *
 * Static inspection plus subprocesses against a STUB psql. No database, no network, no secret.
 */
class OvertureV2OperatorLoadGuardTest extends TestCase
{
    private const WORKFLOW = '.github/workflows/overture-v2-operator-load.yml';
    private const DIR = 'spikes/phase-4-overture-v2-load';
    private const ENVIRONMENT = 'overture-v2-spatial';
    private const FINGERPRINT = '835261fd2b754974525e2963cd97471776a2a2d45e8062374ae4a17ece965b5f';
    private const V2_PATHS = [
        '--path=database/migrations/spatial/2026_09_24_000001_spatial_overture_v2_create_corpora.php',
        '--path=database/migrations/spatial/2026_09_24_000002_spatial_overture_v2_create_places.php',
        '--path=database/migrations/spatial/2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php',
    ];
    private const SQL_FILES = ['preflight.sql', 'v1_snapshot.sql', 'verify_v2_load.sql', 'sample_fidelity.sql', 'migration_ledger.sql'];
    private const WRITE_JOBS = ['migrate', 'import'];

    private static function source(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    /** @return array<string, mixed> */
    private static function workflow(): array
    {
        return Yaml::parseFile(base_path(self::WORKFLOW));
    }

    /** @return list<array{job: string, step: array<string, mixed>}> */
    private static function steps(): array
    {
        $out = [];
        foreach (self::workflow()['jobs'] as $name => $job) {
            foreach ($job['steps'] ?? [] as $step) {
                $out[] = ['job' => (string) $name, 'step' => $step];
            }
        }

        return $out;
    }

    /** @return list<string> every `run:` script in the workflow */
    private static function runScripts(?string $job = null): array
    {
        $runs = [];
        foreach (self::steps() as $s) {
            if (($job === null || $s['job'] === $job) && isset($s['step']['run'])) {
                $runs[] = (string) $s['step']['run'];
            }
        }

        return $runs;
    }

    /** @return array<string, string> every env key => value, workflow, job and step level */
    private static function envEntries(): array
    {
        $wf = self::workflow();
        $all = [];
        $add = static function (array $env, string $where) use (&$all): void {
            foreach ($env as $k => $v) {
                $all["{$where}:{$k}"] = (string) $v;
            }
        };
        $add($wf['env'] ?? [], 'workflow');
        foreach ($wf['jobs'] as $name => $job) {
            $add($job['env'] ?? [], "job {$name}");
            foreach ($job['steps'] ?? [] as $i => $step) {
                $add($step['env'] ?? [], "job {$name} step {$i}");
            }
        }

        return $all;
    }

    // ── Triggers, permissions, environment, secret ─────────────────────────────────────────────

    /** @test */
    public function it_can_only_be_started_by_hand(): void
    {
        $on = self::workflow()['on'];
        $this->assertSame(['workflow_dispatch'], array_keys($on), 'workflow_dispatch must be the ONLY trigger');
        $this->assertSame('read', self::workflow()['permissions']['contents'] ?? null);
        $this->assertCount(1, self::workflow()['permissions'], 'no permission beyond contents: read');
    }

    /** @test */
    public function the_one_secret_is_only_read_inside_the_protected_environment(): void
    {
        $yaml = self::source(self::WORKFLOW);
        preg_match_all('/secrets\.([A-Za-z0-9_]+)/', $yaml, $m);
        $this->assertSame(['SPATIAL_DATABASE_URL'], array_values(array_unique($m[1])), 'SPATIAL_DATABASE_URL is the only secret');

        foreach (self::workflow()['jobs'] as $name => $job) {
            $usesSecret = str_contains(json_encode($job, JSON_UNESCAPED_SLASHES), 'secrets.');
            if ($usesSecret) {
                $this->assertSame(self::ENVIRONMENT, $job['environment'] ?? null, "job {$name} reads the secret outside the protected environment");
            }
        }

        $gate = self::workflow()['jobs']['gate'];
        $this->assertArrayNotHasKey('environment', $gate, 'the gate job must hold no environment');
        $this->assertStringNotContainsString('secrets.', json_encode($gate, JSON_UNESCAPED_SLASHES), 'the gate job must hold no secret');
    }

    /** @test */
    public function no_application_database_credential_or_production_signal_is_present(): void
    {
        $forbiddenKeys = ['DATABASE_URL', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'PGHOST', 'PGHOSTADDR',
            'PGDATABASE', 'PGUSER', 'PGPASSWORD', 'PGSERVICE', 'REPLIT_DEPLOYMENT'];
        foreach (self::envEntries() as $where => $value) {
            [, $key] = explode(':', $where, 2) + [1 => ''];
            $this->assertNotContains($key, $forbiddenKeys, "{$where} names an application database variable");
            $this->assertStringNotContainsStringIgnoringCase('helium', $value, "{$where} names the application database");
            if ($key === 'APP_ENV') {
                $this->assertNotSame('production', strtolower($value), "{$where} claims production");
            }
            if ($key === 'DB_CONNECTION') {
                $this->assertSame('sqlite', $value, 'the default connection on the runner must be sqlite, never a pgsql target');
            }
        }
        foreach (self::runScripts() as $run) {
            $this->assertStringNotContainsStringIgnoringCase('helium', $run);
            $this->assertStringNotContainsString('.env.example', $run, 'no .env is copied onto the operator runner');
        }
    }

    // ── Pinned SHA, gating and ordering ────────────────────────────────────────────────────────

    /** @test */
    public function every_job_runs_exactly_the_pinned_commit_from_main(): void
    {
        $inputs = self::workflow()['on']['workflow_dispatch']['inputs'];
        $this->assertTrue($inputs['commit_sha']['required']);

        foreach (self::steps() as $s) {
            if (str_starts_with((string) ($s['step']['uses'] ?? ''), 'actions/checkout')) {
                $this->assertSame('${{ inputs.commit_sha }}', $s['step']['with']['ref'] ?? null, "checkout in {$s['job']} is not pinned");
                $this->assertFalse($s['step']['with']['persist-credentials'] ?? true, "checkout in {$s['job']} keeps credentials");
            }
        }

        $gate = implode("\n", self::runScripts('gate'));
        $this->assertStringContainsString('refs/heads/main', $gate);
        $this->assertStringContainsString('^[0-9a-f]{40}$', $gate);
        $this->assertStringContainsString('merge-base --is-ancestor "$INPUT_SHA" origin/main', $gate);
        $this->assertStringContainsString('git rev-parse HEAD', $gate);
    }

    /** @test */
    public function third_party_actions_are_pinned_and_stack_traces_carry_no_arguments(): void
    {
        foreach (self::steps() as $s) {
            if (isset($s['step']['uses'])) {
                $this->assertMatchesRegularExpression('/^[\w.-]+\/[\w.-]+@[0-9a-f]{40}$/', (string) $s['step']['uses'],
                    "{$s['job']}: actions must be pinned to a full commit SHA");
            }
            if (str_starts_with((string) ($s['step']['uses'] ?? ''), 'shivammathur/setup-php')) {
                $this->assertSame('zend.exception_ignore_args=On', $s['step']['with']['ini-values'] ?? null);
            }
        }
    }

    /** @test */
    public function the_runner_identity_is_declared_truthfully_and_preflight_reruns_before_each_write(): void
    {
        $jobs = self::workflow()['jobs'];
        foreach (self::WRITE_JOBS as $job) {
            $this->assertSame('operator', $jobs[$job]['env']['APP_ENV'] ?? null, "{$job} must declare APP_ENV=operator");
            $names = array_map(static fn (array $st): string => (string) ($st['name'] ?? ($st['uses'] ?? '')), $jobs[$job]['steps']);
            $rerun = array_search('Re-run the read-only preflight immediately before the write (approval may have waited)', $names, true);
            $this->assertNotFalse($rerun, "{$job} must re-run the preflight inside itself");
            $write = $job === 'migrate' ? 'Apply exactly the three v2 migrations (one batch)' : 'Import (WRITE) — explicit --database, never a default';
            $this->assertLessThan(array_search($write, $names, true), $rerun, "{$job}: the preflight must run before the write");
        }
        $header = self::source(self::WORKFLOW);
        $this->assertStringContainsString('DEFAULTS an unset', $header, 'the workflow must say why APP_ENV is declared');
        $this->assertStringContainsString("grep -c '^LEDGER ready '", implode("\n", self::runScripts('import')), 'exactly one LEDGER line must be asserted');
    }

    /** @test */
    public function inputs_never_reach_a_shell_by_interpolation(): void
    {
        foreach (self::runScripts() as $run) {
            $this->assertStringNotContainsString('${{', $run, 'a run: script interpolates an expression; pass it through env: instead');
        }
    }

    /** @test */
    public function read_only_preflight_runs_first_and_every_other_stage_needs_it(): void
    {
        $jobs = self::workflow()['jobs'];
        $this->assertSame('gate', $jobs['preflight']['needs']);
        $this->assertStringContainsString('bin/preflight.sh', implode("\n", self::runScripts('preflight')));
        foreach (['migrate', 'import', 'verify'] as $job) {
            $this->assertEqualsCanonicalizing(['gate', 'preflight'], (array) $jobs[$job]['needs'], "{$job} must need gate and preflight");
            $this->assertSame("inputs.stage == '{$job}'", $jobs[$job]['if'], "{$job} must run only for its own stage");
        }
        $this->assertSame('preflight', self::workflow()['on']['workflow_dispatch']['inputs']['stage']['default']);
    }

    /** @test */
    public function write_stages_are_gated_on_confirmations_and_rehearsal_evidence(): void
    {
        $gateSteps = array_filter(self::steps(), static fn (array $s): bool => $s['job'] === 'gate' && isset($s['step']['if']));
        $this->assertCount(2, $gateSteps, 'exactly the confirmation and rehearsal steps are write-stage conditional');
        foreach ($gateSteps as $s) {
            $this->assertSame("inputs.stage == 'migrate' || inputs.stage == 'import'", $s['step']['if']);
        }

        $gate = implode("\n", self::runScripts('gate'));
        foreach ([
            'THIS DOES NOT ACTIVATE OVERTURE V2',
            '2026_09_24_000001,2026_09_24_000002,2026_09_24_000003',
            'EXPECTED_FINGERPRINT',
            '"PASSED"',
            '^3\.6\.[0-9]+$',
            '"52716"',
            '"11082"',
            '"ALREADY_READY"',
            'rehearsed_commit',
            'git diff --quiet "$rehearsed" "$INPUT_SHA"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gate, "the gate must check: {$needle}");
        }

        foreach (self::WRITE_JOBS as $job) {
            $this->assertSame(self::ENVIRONMENT, self::workflow()['jobs'][$job]['environment'], "{$job} must wait for a reviewer");
        }
    }

    // ── Migrations: exactly three paths ────────────────────────────────────────────────────────

    /** @test */
    public function migrate_names_exactly_the_three_v2_migrations_and_nothing_else(): void
    {
        $runs = implode("\n", self::runScripts());
        $yaml = self::source(self::WORKFLOW);

        preg_match_all('/--path=\S+/', $runs, $m);
        $this->assertSame(self::V2_PATHS, array_values(array_unique($m[0])), 'the only --path values are the three v2 migrations');
        $this->assertDoesNotMatchRegularExpression('#--path=database/migrations/spatial(?:/)?(?:\s|$)#', $runs, 'never the whole spatial directory');
        $this->assertStringNotContainsString('--step', $yaml);
        $this->assertStringNotContainsString('2026_08_11', $yaml, 'never an August address migration');
        $this->assertStringNotContainsString('2026_08_12', $yaml, 'never an August address migration');
        foreach (['migrate:rollback', 'migrate:fresh', 'migrate:reset', 'migrate:refresh', 'db:wipe', 'db:seed'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $runs, "the workflow must never run {$forbidden}");
        }

        foreach (self::runScripts() as $run) {
            if (preg_match('/php artisan migrate\b/', $run)) {
                $this->assertStringContainsString('--database=pgsql_spatial', $run);
                $this->assertSame(3, substr_count($run, '--path='), 'every migrate invocation names exactly three paths');
            }
            if (str_contains($run, 'corpus:import-overture-v2') && str_contains($run, '--write')) {
                $this->assertStringContainsString('--database=pgsql_spatial', $run, 'a write always names its database');
            }
        }
    }

    /** @test */
    public function nothing_in_the_workflow_can_activate_v2(): void
    {
        foreach (self::envEntries() as $where => $value) {
            $this->assertStringNotContainsString('OVERTURE_CORPUS_POI', $where, "{$where} sets an activation variable");
        }
        foreach (self::runScripts() as $run) {
            foreach (['OVERTURE_CORPUS_POI', 'location_providers', 'overture_corpus_poi', 'corpus_imports', 'config:cache', 'tinker'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $run, "a run: script touches {$forbidden}");
            }
        }
    }

    // ── Read-only SQL ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function every_verification_sql_file_is_read_only(): void
    {
        foreach (self::SQL_FILES as $file) {
            $sql = self::source(self::DIR . '/sql/' . $file);
            $this->assertSame('SET default_transaction_read_only = on;', strtok($sql, "\n"), "{$file} must begin with the read-only SET");
            $this->assertMatchesRegularExpression('/^\\\\set ON_ERROR_STOP on$/m', $sql, "{$file} must stop on error");

            $code = implode("\n", array_filter(
                explode("\n", $sql),
                static fn (string $l): bool => ! str_starts_with(ltrim($l), '--') && ! str_starts_with(ltrim($l), '\\'),
            ));
            foreach (['INSERT', 'UPDATE', 'DELETE', 'MERGE', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'GRANT', 'REVOKE',
                'COPY', 'VACUUM', 'REINDEX', 'CLUSTER', 'COMMENT', 'LOCK', 'CALL', 'DO', 'EXECUTE', 'PREPARE',
                'REFRESH', 'SECURITY', 'NOTIFY', 'LISTEN'] as $keyword) {
                $this->assertDoesNotMatchRegularExpression('/\b' . $keyword . '\b/i', $code, "{$file} contains {$keyword}");
            }
            foreach (['set_config', 'pg_terminate_backend', 'pg_cancel_backend', 'pg_advisory', 'dblink', 'lo_import',
                'pg_read_file', 'read_only = off', 'READ WRITE'] as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $code, "{$file} contains {$needle}");
            }
            foreach (['\\copy', '\\gexec', '\\!', '\\o ', '\\w '] as $meta) {
                $this->assertStringNotContainsString($meta, $sql, "{$file} uses the psql meta-command {$meta}");
            }
        }
    }

    // ── bin/preflight.sh, as a subprocess against a stub psql ──────────────────────────────────

    /**
     * Runs a procedure script with a STUB psql first on PATH. The stub records its argv, the
     * libpq variables in its environment, and a copy (with modes) of the service file it was
     * handed — so a test can see exactly what psql would have received, and where.
     *
     * @param list<string> $command
     * @param array<string, string> $env
     * @return array{code: int, out: string, psql_called: bool, psql_args: string, psql_argv: list<string>, svc: array<string, string>}
     */
    private function runWithStubPsql(array $command, array $env, int $psqlExit = 0, string $psqlStderr = ''): array
    {
        $stubDir = sys_get_temp_dir() . '/ov2-psql-stub-' . bin2hex(random_bytes(6));
        mkdir($stubDir);
        $marker = $stubDir . '/psql.called';
        file_put_contents($stubDir . '/stderr.txt', $psqlStderr);
        file_put_contents($stubDir . '/psql', "#!/usr/bin/env bash\n"
            . "printf '%s\\n' \"\$@\" > '{$marker}'\n"
            . "{ printf 'PGSERVICE=%s\\n' \"\${PGSERVICE:-}\"; printf 'PGSERVICEFILE=%s\\n' \"\${PGSERVICEFILE:-}\";\n"
            . "  if [ -f \"\${PGSERVICEFILE:-/nonexistent}\" ]; then printf 'mode=%s\\n' \"\$(stat -c %a \"\$PGSERVICEFILE\")\";\n"
            . "    printf 'dirmode=%s\\n' \"\$(stat -c %a \"\$(dirname \"\$PGSERVICEFILE\")\")\"; cp \"\$PGSERVICEFILE\" '{$stubDir}/svc.copy'; fi; } > '{$stubDir}/svc.env'\n"
            . "cat '{$stubDir}/stderr.txt' >&2\nexit {$psqlExit}\n");
        chmod($stubDir . '/psql', 0755);

        $proc = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(['PATH' => $stubDir . ':' . (string) getenv('PATH')], $env),
        );
        $this->assertIsResource($proc);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        $called = is_file($marker);
        $psqlArgs = $called ? (string) file_get_contents($marker) : '';
        $svc = [];
        if (is_file($stubDir . '/svc.env')) {
            foreach (explode("\n", trim((string) file_get_contents($stubDir . '/svc.env'))) as $line) {
                [$k, $v] = explode('=', $line, 2) + [1 => ''];
                $svc[$k] = $v;
            }
        }
        $svc['content'] = is_file($stubDir . '/svc.copy') ? (string) file_get_contents($stubDir . '/svc.copy') : '';
        array_map('unlink', glob($stubDir . '/*'));
        @rmdir($stubDir);

        return ['code' => $code, 'out' => $out, 'psql_called' => $called, 'psql_args' => $psqlArgs,
            'psql_argv' => $called ? explode("\n", rtrim($psqlArgs, "\n")) : [], 'svc' => $svc];
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     * @return array{code: int, out: string, psql_called: bool, psql_args: string, psql_argv: list<string>, svc: array<string, string>}
     */
    private function preflight(array $args, array $env, int $psqlExit = 0, string $psqlStderr = ''): array
    {
        return $this->runWithStubPsql(array_merge(['bash', base_path(self::DIR . '/bin/preflight.sh')], $args), $env, $psqlExit, $psqlStderr);
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     * @return array{code: int, out: string, psql_called: bool, psql_args: string, psql_argv: list<string>, svc: array<string, string>}
     */
    private function spatialPsql(array $args, array $env, int $psqlExit = 0): array
    {
        return $this->runWithStubPsql(array_merge(['bash', base_path(self::DIR . '/bin/spatial_psql.sh')], $args), $env, $psqlExit);
    }

    /** @return list<string> the ephemeral service directories currently under /tmp */
    private static function serviceDirs(): array
    {
        return glob('/tmp/ov2-pgsvc.*') ?: [];
    }

    private const GOOD_URL = 'postgresql://ov2op:S3cretPassw0rd@p.abc123.db.postgresbridge.com:5432/postgres?sslmode=require';

    /** @test */
    public function preflight_refuses_the_wrong_shell_and_the_wrong_target_before_psql(): void
    {
        $cases = [
            'no state' => [[], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 'v2-state'],
            'bad state' => [['--v2-state=loadedish'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 'v2-state'],
            'production' => [['--v2-state=absent'], ['APP_ENV' => 'production', 'SPATIAL_DATABASE_URL' => self::GOOD_URL], 'production'],
            'deployment' => [['--v2-state=absent'], ['REPLIT_DEPLOYMENT' => '1', 'SPATIAL_DATABASE_URL' => self::GOOD_URL], 'REPLIT_DEPLOYMENT'],
            'helium PGHOST' => [['--v2-state=absent'], ['PGHOST' => 'helium', 'SPATIAL_DATABASE_URL' => self::GOOD_URL], 'PGHOST'],
            'helium DATABASE_URL' => [['--v2-state=absent'], ['DATABASE_URL' => 'postgresql://u:p@helium/heliumdb', 'SPATIAL_DATABASE_URL' => self::GOOD_URL], 'DATABASE_URL'],
            'no url' => [['--v2-state=absent'], [], 'SPATIAL_DATABASE_URL is not set'],
            'no host' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql:///postgres'], 'no host'],
            'not crunchy' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql://u:p@example.com:5432/postgres'], 'postgresbridge'],
            'helium host' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql://u:p@helium:5432/postgres'], 'postgresbridge'],
            'heliumdb name' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql://u:p@p.abc.db.postgresbridge.com/heliumdb'], 'application database'],
            'multi-host' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql://u:p@a.db.postgresbridge.com,helium/postgres'], 'multi-host'],
            'any PGHOSTADDR' => [['--v2-state=absent'], ['PGHOSTADDR' => '10.0.0.5', 'SPATIAL_DATABASE_URL' => self::GOOD_URL], 'PGHOSTADDR'],
            'any PGSERVICE' => [['--v2-state=absent'], ['PGSERVICE' => 'x', 'SPATIAL_DATABASE_URL' => self::GOOD_URL], 'PGSERVICE'],
            'url host param' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL . '&host=helium'], 'connection parameter'],
            'url hostaddr param' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL . '&hostaddr=10.0.0.5'], 'connection parameter'],
            'uppercase host' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql://u:p@P.ABC.db.postgresbridge.com/postgres'], 'lowercase'],
            'weak tls' => [['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => 'postgresql://u:p@p.abc.db.postgresbridge.com/postgres?sslmode=disable'], 'sslmode'],
        ];
        foreach ($cases as $label => [$args, $env, $expect]) {
            $r = $this->preflight($args, $env);
            $this->assertSame(1, $r['code'], "{$label}: must refuse before connecting");
            $this->assertStringContainsString($expect, $r['out'], "{$label}: refusal must say why");
            $this->assertFalse($r['psql_called'], "{$label}: psql must never be reached");
            $this->assertStringNotContainsString('S3cretPassw0rd', $r['out'], "{$label}: the secret leaked");
        }
    }

    /** @test */
    public function preflight_hands_psql_the_read_only_script_and_maps_its_outcome(): void
    {
        $ok = $this->preflight(['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 0);
        $this->assertSame(0, $ok['code']);
        $this->assertStringContainsString('PREFLIGHT PASSED', $ok['out']);
        $this->assertStringContainsString('2026_09_24_000001, 2026_09_24_000002, 2026_09_24_000003', $ok['out']);
        $this->assertStringNotContainsString('S3cretPassw0rd', $ok['out'], 'the secret leaked');
        foreach (['ON_ERROR_STOP=1', 'v2_state=absent', 'target_host=p.abc123.db.postgresbridge.com', 'target_port=5432',
            'expected_fingerprint=' . self::FINGERPRINT, 'sql/preflight.sql', '-X'] as $arg) {
            $this->assertStringContainsString($arg, $ok['psql_args'], "psql was not handed {$arg}");
        }
        // The connection reaches psql through the service file, never through its argv.
        foreach ([self::GOOD_URL, 'S3cretPassw0rd', 'ov2op', 'postgresql://', 'password', 'service='] as $secretish) {
            $this->assertStringNotContainsString($secretish, $ok['psql_args'], "the preflight's psql argv carries {$secretish}");
        }
        $this->assertSame('ov2_spatial', $ok['svc']['PGSERVICE'] ?? null);
        $this->assertStringContainsString("password=S3cretPassw0rd\n", $ok['svc']['content']);
        $this->assertFileDoesNotExist((string) ($ok['svc']['PGSERVICEFILE'] ?? ''), 'the service file must be deleted when psql exits');

        $down = $this->preflight(['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 2);
        $this->assertSame(4, $down['code'], 'a connection failure is exit 4');
        $this->assertStringContainsString('CONNECTIVITY FAILED', $down['out']);
        $this->assertStringContainsString('Do not change the Crunchy allowlist', $down['out']);

        $failed = $this->preflight(['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 3);
        $this->assertSame(5, $failed['code'], 'a failed check is exit 5');
        $this->assertStringContainsString('PREFLIGHT FAILED', $failed['out']);
    }

    /** @test */
    public function preflight_never_echoes_the_secret_and_knows_every_spatial_migration(): void
    {
        $src = self::source(self::DIR . '/bin/preflight.sh');
        $this->assertDoesNotMatchRegularExpression('/(echo|printf)[^\n]*SPATIAL_DATABASE_URL/', $src);

        // A new spatial migration makes the runbook stale: the preflight refuses at run time, and
        // this fails first, in CI.
        $actual = array_map('basename', glob(base_path('database/migrations/spatial/*_*.php')));
        sort($actual);
        preg_match('/EXPECTED_MIGRATIONS="([^"]+)"/', $src, $m);
        $expected = preg_split('/\s+/', trim($m[1]));
        sort($expected);
        $this->assertSame($expected, $actual, 'bin/preflight.sh must list exactly the spatial migrations in the repository');
    }

    // ── The URL is never a process argument (bin/spatial_psql.sh) ─────────────────────────────

    /** @test */
    public function spatial_psql_hands_psql_no_connection_string_in_argv(): void
    {
        $before = self::serviceDirs();
        $args = ['-X', '-q', '-v', 'ON_ERROR_STOP=1', '-f', '/dev/null'];
        $r = $this->spatialPsql($args, ['SPATIAL_DATABASE_URL' => self::GOOD_URL]);

        $this->assertSame(0, $r['code'], $r['out']);
        $this->assertSame('', $r['out'], 'the helper prints nothing of its own on success');
        $this->assertSame($args, $r['psql_argv'], 'psql receives exactly the caller\'s options and nothing else');
        foreach ([self::GOOD_URL, 'S3cretPassw0rd', 'ov2op', 'p.abc123', 'postgres', 'host=', 'service=', 'sslmode'] as $secretish) {
            $this->assertStringNotContainsString($secretish, $r['psql_args'], "psql argv carries {$secretish}");
        }

        // What psql DOES receive: a service name in its environment, and a 0600 file in a 0700
        // directory under /tmp that holds the connection.
        $this->assertSame('ov2_spatial', $r['svc']['PGSERVICE']);
        $this->assertMatchesRegularExpression('#^/tmp/ov2-pgsvc\.[A-Za-z0-9]{8}/pg_service\.conf$#', $r['svc']['PGSERVICEFILE']);
        $this->assertSame('600', $r['svc']['mode']);
        $this->assertSame('700', $r['svc']['dirmode']);
        $this->assertSame("[ov2_spatial]\nhost=p.abc123.db.postgresbridge.com\nport=5432\ndbname=postgres\nuser=ov2op\n"
            . "password=S3cretPassw0rd\nsslmode=require\n", $r['svc']['content']);

        // Deleted on exit — and nothing else left behind.
        $this->assertFileDoesNotExist($r['svc']['PGSERVICEFILE']);
        $this->assertDirectoryDoesNotExist(dirname($r['svc']['PGSERVICEFILE']));
        $this->assertSame($before, self::serviceDirs());
    }

    /** @test */
    public function spatial_psql_passes_psql_failures_through_and_still_cleans_up(): void
    {
        $r = $this->spatialPsql(['-X', '-f', '/dev/null'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 3);
        $this->assertSame(3, $r['code'], 'psql\'s exit status is the helper\'s');
        $this->assertFileDoesNotExist($r['svc']['PGSERVICEFILE']);

        // Percent-encoding is decoded exactly once, and an explicit stronger sslmode is kept.
        $encoded = $this->spatialPsql(['-X', '-f', '/dev/null'],
            ['SPATIAL_DATABASE_URL' => 'postgresql://ov2%40op:p%3As%2Fw@p.abc123.db.postgresbridge.com:6543/db1?sslmode=verify-full&connect_timeout=15']);
        $this->assertSame(0, $encoded['code'], $encoded['out']);
        $this->assertSame("[ov2_spatial]\nhost=p.abc123.db.postgresbridge.com\nport=6543\ndbname=db1\nuser=ov2@op\n"
            . "password=p:s/w\nsslmode=verify-full\nconnect_timeout=15\n", $encoded['svc']['content']);
        $this->assertStringNotContainsString('p:s/w', $encoded['psql_args']);
    }

    /** @test */
    public function spatial_psql_refuses_ambient_routing_foreign_arguments_and_bad_targets_before_psql(): void
    {
        $url = ['SPATIAL_DATABASE_URL' => self::GOOD_URL];
        $ok = ['-X', '-f', '/dev/null'];
        $cases = [
            'PGHOST' => [$ok, $url + ['PGHOST' => 'x'], 'PGHOST'],
            'PGHOSTADDR' => [$ok, $url + ['PGHOSTADDR' => '10.0.0.5'], 'PGHOSTADDR'],
            'PGDATABASE' => [$ok, $url + ['PGDATABASE' => 'x'], 'PGDATABASE'],
            'PGUSER' => [$ok, $url + ['PGUSER' => 'x'], 'PGUSER'],
            'PGPORT' => [$ok, $url + ['PGPORT' => '1'], 'PGPORT'],
            'PGSERVICE' => [$ok, $url + ['PGSERVICE' => 'x'], 'PGSERVICE'],
            'PGSERVICEFILE' => [$ok, $url + ['PGSERVICEFILE' => '/tmp/x'], 'PGSERVICEFILE'],
            'PGOPTIONS' => [$ok, $url + ['PGOPTIONS' => '-c x=y'], 'PGOPTIONS'],
            'production' => [$ok, $url + ['APP_ENV' => 'production'], 'production'],
            'deployment' => [$ok, $url + ['REPLIT_DEPLOYMENT' => '1'], 'REPLIT_DEPLOYMENT'],
            'helium DATABASE_URL' => [$ok, $url + ['DATABASE_URL' => 'postgresql://u:p@helium/heliumdb'], 'DATABASE_URL'],
            'no url' => [$ok, [], 'SPATIAL_DATABASE_URL is not set'],
            'not crunchy' => [$ok, ['SPATIAL_DATABASE_URL' => 'postgresql://u:S3cretPassw0rd@example.com/postgres'], 'postgresbridge'],
            'url host param' => [$ok, ['SPATIAL_DATABASE_URL' => self::GOOD_URL . '&host=helium'], 'connection parameter'],
            'weak tls' => [$ok, ['SPATIAL_DATABASE_URL' => 'postgresql://u:S3cretPassw0rd@p.abc.db.postgresbridge.com/postgres?sslmode=prefer'], 'sslmode'],
            'unwritable password' => [$ok, ['SPATIAL_DATABASE_URL' => 'postgresql://u:S3cretPassw0rd%20@p.abc.db.postgresbridge.com/postgres'], 'service file'],
            '-h' => [['-h', 'helium', '-f', '/dev/null'], $url, 'not allowed'],
            '-d' => [['-d', 'heliumdb', '-f', '/dev/null'], $url, 'not allowed'],
            '-U' => [['-U', 'x', '-f', '/dev/null'], $url, 'not allowed'],
            '--dbname' => [['--dbname=x', '-f', '/dev/null'], $url, 'not allowed'],
            'positional db' => [['-X', 'heliumdb'], $url, 'not allowed'],
            'positional conninfo' => [['-X', 'service=other'], $url, 'not allowed'],
            'url as -c value' => [['-c', 'postgresql://u:p@helium/x'], $url, 'connection string'],
            'dangling -f' => [['-X', '-f'], $url, 'needs a value'],
            'no -X (psqlrc would run)' => [['-q', '-f', '/dev/null'], $url, '-X is required'],
        ];
        $before = self::serviceDirs();
        foreach ($cases as $label => [$args, $env, $expect]) {
            $r = $this->spatialPsql($args, $env);
            $this->assertSame(1, $r['code'], "{$label}: must refuse");
            $this->assertStringContainsString('[spatial-psql] REFUSING:', $r['out'], "{$label}: refusal must be the helper's own");
            $this->assertStringContainsString($expect, $r['out'], "{$label}: refusal must say why");
            $this->assertFalse($r['psql_called'], "{$label}: psql must never be reached");
            foreach (['S3cretPassw0rd', 'ov2op', 'p.abc123'] as $secretish) {
                $this->assertStringNotContainsString($secretish, $r['out'], "{$label}: a refusal printed {$secretish}");
            }
        }
        $this->assertSame($before, self::serviceDirs(), 'a refusal must leave no service directory behind');
    }

    /** @test */
    public function libpq_reads_the_connection_from_the_service_file_the_helper_writes(): void
    {
        $realPsql = trim((string) shell_exec('command -v psql 2>/dev/null'));
        if ($realPsql === '') {
            $this->markTestSkipped('no psql client on this machine');
        }
        $dir = sys_get_temp_dir() . '/ov2-libpq-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700);
        $file = "{$dir}/pg_service.conf";
        // Port 1 on a loopback hostaddr: libpq takes host, port, user and password from the file,
        // resolves nothing and reaches nothing — the connection is refused locally.
        $proc = proc_open(['python3', base_path(self::DIR . '/bin/spatial_target.py'), 'service', $file], [1 => ['pipe', 'w']], $pipes, null,
            ['PATH' => (string) getenv('PATH'), 'SPATIAL_DATABASE_URL' => 'postgresql://ov2op:S3cretPassw0rd@p.abc123.db.postgresbridge.com:1/postgres']);
        $this->assertSame("OK\n", stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        proc_close($proc);
        $this->assertSame('600', substr(sprintf('%o', fileperms($file)), -3));

        $proc = proc_open([$realPsql, '-X', '-c', 'SELECT 1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            ['PATH' => (string) getenv('PATH'), 'PGSERVICEFILE' => $file, 'PGSERVICE' => 'ov2_spatial',
                'PGHOSTADDR' => '127.0.0.1', 'PGCONNECT_TIMEOUT' => '2']);
        $err = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        unlink($file);
        rmdir($dir);

        // With a hostaddr set libpq names only the address; port 1 (never the 5432 default) proves
        // it read the connection from the service file, and no service-file error was raised.
        $this->assertSame(2, $code, 'psql could not connect (as intended): ' . $err);
        $this->assertStringContainsString('port 1 failed', $err, 'libpq took the port from the service file');
        $this->assertStringNotContainsStringIgnoringCase('service file', $err);
        $this->assertStringNotContainsStringIgnoringCase('definition of service', $err);
        $this->assertStringNotContainsString('S3cretPassw0rd', $err);
    }

    /** @test */
    public function no_procedure_file_hands_the_url_to_a_command_as_an_argument(): void
    {
        $shell = ['workflow' => implode("\n", self::runScripts())];
        foreach (glob(base_path(self::DIR . '/bin/*.sh')) as $file) {
            $shell[basename($file)] = (string) file_get_contents($file);
        }
        foreach ($shell as $where => $src) {
            // The only permitted expansion of the URL in shell is the emptiness test.
            preg_match_all('/\$\{?SPATIAL_DATABASE_URL[^}\s"]*\}?/', $src, $m);
            foreach ($m[0] as $use) {
                $this->assertSame('${SPATIAL_DATABASE_URL:-}', $use, "{$where} expands the URL as {$use}");
            }
            $this->assertDoesNotMatchRegularExpression('/\[ -n "\$\{SPATIAL_DATABASE_URL:-\}" \][^\n]*\$\{SPATIAL/', $src);
        }
        foreach (['bin/spatial_psql.sh', 'bin/preflight.sh', 'bin/mask_log_identity.sh'] as $file) {
            preg_match_all('/\$\{?SPATIAL_DATABASE_URL/', self::source(self::DIR . '/' . $file), $m);
            $this->assertLessThanOrEqual(1, count($m[0]), "{$file}: the URL is only ever tested for presence");
        }

        // Every psql in a workflow run: script is the helper, or a client install/version check.
        foreach (self::runScripts() as $run) {
            $stripped = preg_replace(['#bin/spatial_psql\.sh#', '/command -v psql/', '/psql --version/', '/postgresql-client/'], '', $run);
            $this->assertDoesNotMatchRegularExpression('/\bpsql\b/', $stripped, "a run: script invokes psql directly:\n{$run}");
        }
        // In bin/, only the helper starts psql.
        foreach (glob(base_path(self::DIR . '/bin/*.sh')) as $file) {
            $code = implode("\n", array_filter(explode("\n", (string) file_get_contents($file)),
                static fn (string $l): bool => ! str_starts_with(ltrim($l), '#')));
            $code = preg_replace(['/command -v psql/', '/"psql is required\."/', '#spatial_psql\.sh#', "/'\\^psql:[^']*'/"], '', $code);
            // A command position: psql preceded by start, whitespace or a shell operator.
            $invocation = '/(^|[\s;|&(])psql(\s|$)/m';
            if (basename($file) === 'spatial_psql.sh') {
                $this->assertSame(1, preg_match_all($invocation, $code), 'the helper starts psql exactly once');
                $this->assertStringContainsString('PGSERVICEFILE="${SVC_DIR}/pg_service.conf" PGSERVICE=ov2_spatial psql "$@"', $code);
            } else {
                $this->assertDoesNotMatchRegularExpression($invocation, $code, basename($file) . ' starts psql directly');
            }
        }

        // No procedure document shows the old form, either.
        $docs = self::source(self::DIR . '/RUNBOOK.md') . self::source(self::WORKFLOW);
        foreach (self::SQL_FILES as $f) {
            $docs .= self::source(self::DIR . '/sql/' . $f);
        }
        $this->assertDoesNotMatchRegularExpression('/psql\s+["\']?\$\{?SPATIAL_DATABASE_URL/', $docs);
    }

    /** @test */
    public function the_url_is_read_by_exactly_one_parser(): void
    {
        $this->assertStringContainsString('python3 "${HERE}/spatial_target.py" check', self::source(self::DIR . '/bin/preflight.sh'));
        $this->assertStringContainsString('python3 "${HERE}/spatial_target.py" service', self::source(self::DIR . '/bin/spatial_psql.sh'));
        foreach (['bin/preflight.sh', 'bin/spatial_psql.sh'] as $file) {
            $this->assertStringNotContainsString('urlsplit', self::source(self::DIR . '/' . $file), "{$file} parses the URL itself");
        }
        $py = self::source(self::DIR . '/bin/spatial_target.py');
        $this->assertStringContainsString('os.O_EXCL', $py);
        $this->assertStringContainsString('0o600', $py);
        $this->assertDoesNotMatchRegularExpression('/print\([^)]*(password|fields\[.user.\]|\bu\b)/', $py, 'the parser never prints the password, user or URL');
    }

    // ── Migration ledger: the batch contract ───────────────────────────────────────────────────

    /** @test */
    public function the_migrate_stage_proves_one_new_batch_around_the_write(): void
    {
        $steps = self::workflow()['jobs']['migrate']['steps'];
        $names = array_map(static fn (array $st): string => (string) ($st['name'] ?? ($st['uses'] ?? '')), $steps);
        $before = array_search('Migration ledger (before) — eleven core rows, record the previous maximum batch', $names, true);
        $write = array_search('Apply exactly the three v2 migrations (one batch)', $names, true);
        $after = array_search('Migration ledger (after) — the three v2 migrations as ONE batch = previous maximum + 1', $names, true);
        $this->assertNotFalse($before);
        $this->assertNotFalse($after);
        $this->assertLessThan($write, $before);
        $this->assertGreaterThan($write, $after);

        $b = (string) $steps[$before]['run'];
        $a = (string) $steps[$after]['run'];
        $this->assertStringContainsString('-v ledger_phase=before', $b);
        $this->assertStringContainsString('PREVIOUS_MAX_BATCH', $b);
        $this->assertStringContainsString('-v ledger_phase=after', $a);
        $this->assertStringContainsString('-v previous_max_batch="$prev"', $a);
        $this->assertStringContainsString('grep -qx "LEDGER OK"', $a);
        $this->assertStringContainsString('diff -u', $a, 'the core rows must be diffed before/after');

        $sql = self::source(self::DIR . '/sql/migration_ledger.sql');
        foreach ([
            "batch = :'previous_max_batch'::int + 1",
            "count(*) FILTER (WHERE batch > :'previous_max_batch'::int) = 3",
            "max(batch) = :'previous_max_batch'::int + 1",
            "'2026_08_11_000001_spatial_core_version_address_corpus'",
            "'2026_08_12_000001_spatial_core_index_address_lookup'",
            "'2026_09_24_000001_spatial_overture_v2_create_corpora'",
            "'2026_09_24_000002_spatial_overture_v2_create_places'",
            "'2026_09_24_000003_spatial_overture_v2_create_chain_memberships'",
            "'PREVIOUS_MAX_BATCH=' || max(batch)",
            "\\echo 'LEDGER OK'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $sql, "migration_ledger.sql must check: {$needle}");
        }
    }

    // ── Fingerprint, rehearsal gate, runbook ───────────────────────────────────────────────────

    /** @test */
    public function the_recorded_fingerprint_is_one_value_everywhere(): void
    {
        $this->assertStringContainsString('EXPECTED_FINGERPRINT="' . self::FINGERPRINT . '"', self::source(self::DIR . '/bin/preflight.sh'));
        $this->assertGreaterThanOrEqual(2, substr_count(self::source(self::DIR . '/RUNBOOK.md'), self::FINGERPRINT));
        preg_match_all('/\b[0-9a-f]{64}\b/', self::source(self::DIR . '/bin/preflight.sh') . self::source(self::DIR . '/RUNBOOK.md'), $m);
        $fingerprints = array_diff(array_unique($m[0]), [
            'b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f', // registry rule hash
            'bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168', // base.ndjson
            'edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4', // supplementary.ndjson
        ]);
        $this->assertSame([self::FINGERPRINT], array_values($fingerprints), 'no second fingerprint may appear');
    }

    /** @test */
    public function the_rehearsal_record_is_a_well_formed_gate(): void
    {
        $md = self::source(self::DIR . '/REHEARSAL.md');
        $this->assertSame(1, preg_match('/<!-- rehearsal-gate:start -->\n(.*?)\n<!-- rehearsal-gate:end -->/s', $md, $m));
        $fields = [];
        foreach (explode("\n", $m[1]) as $line) {
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $fields[$k] = $v;
        }
        foreach (['rehearsal_status', 'rehearsed_commit', 'performed_on', 'postgresql_version', 'postgis_version',
            'places_loaded', 'memberships_loaded', 'second_import', 'verify_v2_load', 'sample_fidelity',
            'v1_snapshot_unchanged', 'rollback_recovery_exercised'] as $key) {
            $this->assertArrayHasKey($key, $fields, "REHEARSAL.md is missing {$key}");
        }
        $this->assertContains($fields['rehearsal_status'], ['NOT_RUN', 'PASSED']);
        if ($fields['rehearsal_status'] === 'PASSED') {
            $this->assertMatchesRegularExpression('/^3\.6\.\d+$/', $fields['postgis_version']);
            $this->assertMatchesRegularExpression('/^16\./', $fields['postgresql_version']);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $fields['rehearsed_commit']);
            $this->assertSame('52716', $fields['places_loaded']);
            $this->assertSame('11082', $fields['memberships_loaded']);
            $this->assertSame('ALREADY_READY', $fields['second_import']);
            $this->assertSame('VERIFY_OK', $fields['verify_v2_load']);
            $this->assertSame('OK', $fields['sample_fidelity']);
            $this->assertSame('yes', $fields['v1_snapshot_unchanged']);
            $this->assertSame('yes', $fields['rollback_recovery_exercised']);
        }
    }

    /** @test */
    public function the_runbook_requires_a_full_size_rehearsal_and_never_activates(): void
    {
        $rb = self::source(self::DIR . '/RUNBOOK.md');
        foreach ([
            'Full-size rehearsal — REQUIRED before any write stage',
            'PostGIS 3.6.x',
            '**Never the live spatial cluster. Never a reduced dataset.**',
            '52,716 places',
            '11,082 memberships',
            'ALREADY READY',
            'pg_terminate_backend',
            'Do not change the Crunchy Bridge network allowlist',
            'operator-container fallback',
            'Import only — it never activates v2.',
            '`overture-v2-spatial`',
            'Prevent self-review',
            'Environment secret only',
            'declare `APP_ENV=operator`',
            'If the Required reviewers rule is not available',
            'is no longer readable, STOP',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rb, "RUNBOOK.md must state: {$needle}");
        }
    }

    /** @test */
    public function the_runbook_defers_write_permission_to_the_gate_and_names_the_fallback_approval_model(): void
    {
        $rb = self::source(self::DIR . '/RUNBOOK.md');

        // The header states no status of its own: it defers to REHEARSAL.md's gate block and the
        // rehearsal-validity gate, so it can never again contradict them.
        $header = substr($rb, 0, (int) strpos($rb, 'Loads the validated Overture v2 corpus'));
        $this->assertStringContainsString('decided by the machine-readable gate block in', $header);
        $this->assertStringContainsString('accepts the candidate SHA', $header);
        $this->assertStringNotContainsString('NOT_RUN', $header, 'the header must not hard-code a rehearsal status');
        $this->assertStringNotContainsString('are\ndisabled', $header);

        foreach ([
            '**Claude — operator / executor.**',
            '**Abigail — independent human reviewer / approver.**',
            'no production write executes merely because the',
            '**STOPS again** before the next write phase.',
            'no self-reviewing GitHub protection may be configured as a',
            'Approval of the migration is not approval of the import',
            'needs its own approval',
            // Source overlays: exactly the four vendor-rewritten files, verified against the commit.
            '`app/Models/Country.php`, `app/Models/State.php`, `app/Models/City.php` and',
            '`database/seeders/CountryStateCityTableSeeder.php`',
            'mounted **read-only**',
            '`--network none`',
            // Partial migration failure is a stop, never an automatic recovery.
            '**the three are not atomic as a group**',
            'Any failed step is a **STOP and report**',
            '`migration_ledger.sql` with `ledger_phase=after`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rb, "RUNBOOK.md must state: {$needle}");
        }
    }

    // ── bin/compare_sample.py ──────────────────────────────────────────────────────────────────

    /** @test */
    public function the_sample_comparator_passes_a_faithful_row_and_fails_a_changed_one(): void
    {
        $dir = sys_get_temp_dir() . '/ov2-compare-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $src = ['source_ref' => 'r1', 'lane' => 'base', 'materialization_policy' => 'corpus', 'source' => 'overture',
            'source_release' => '2026-08-19.0', 'extract_recipe_version' => 'overture-extract-v2',
            'taxonomy_map_version' => 'overture-taxonomy-v2.0', 'name' => 'Publix', 'category_key' => 'grocery_store',
            'source_category' => 'grocery_store', 'brand_name' => 'Publix', 'brand_wikidata' => 'Q7255207',
            'confidence' => 0.95, 'operating_status' => 'open', 'rescued_chain' => null, 'rescued_format' => null,
            'lon' => -82.5, 'lat' => 27.9,
            'address' => ['freeform' => '1 Main St', 'locality' => 'Tampa', 'postcode' => '33602', 'region' => 'FL', 'country' => 'US']];
        file_put_contents("{$dir}/base.ndjson", json_encode($src) . "\n");
        file_put_contents("{$dir}/supplementary.ndjson", '');

        $strata = ['category:grocery_store', 'category:coffee_shop', 'category:pharmacy', 'category:convenience_store',
            'category:fast_food_restaurant', 'category:gas_station', 'category:department_store', 'category:superstore',
            'rescued_cvs', 'membership:fuel', 'membership:store_in_target', 'membership:storefront_unconfirmed'];
        $membership = ['brand_key' => 'publix', 'role' => 'storefront', 'format_key' => 'supermarket',
            'storefront_status' => 'storefront', 'registry_version' => 'chain-registry-v2',
            'registry_rule_hash' => 'b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f'];

        $run = function (array $dbRow) use ($dir): array {
            $lines = [json_encode(['stratum' => 'category:grocery_store'] + $dbRow + ['memberships' => []])];
            file_put_contents("{$dir}/sample.jsonl", implode("\n", $lines) . "\n");
            exec(sprintf('python3 %s --sample %s --extract-dir %s 2>&1',
                escapeshellarg(base_path(self::DIR . '/bin/compare_sample.py')),
                escapeshellarg("{$dir}/sample.jsonl"), escapeshellarg($dir)), $out, $code);

            return [$code, implode("\n", $out)];
        };

        [$code, $out] = $run($src);
        $this->assertStringContainsString('PASS category:grocery_store r1', $out);
        $this->assertNotSame(0, $code, 'eleven strata are absent, so the run as a whole must fail');
        $this->assertStringContainsString('FAIL membership:fuel: stratum absent', $out);

        [, $out] = $run(['name' => 'Publix Super Market'] + $src);
        $this->assertStringContainsString('FAIL category:grocery_store r1', $out);
        $this->assertStringContainsString('name:', $out);

        // Every stratum present and faithful: the whole comparison passes.
        $lines = [];
        foreach ($strata as $s) {
            $lines[] = json_encode(['stratum' => $s] + $src + ['memberships' => [$membership]]);
        }
        file_put_contents("{$dir}/sample.jsonl", implode("\n", $lines) . "\n");
        exec(sprintf('python3 %s --sample %s --extract-dir %s 2>&1',
            escapeshellarg(base_path(self::DIR . '/bin/compare_sample.py')),
            escapeshellarg("{$dir}/sample.jsonl"), escapeshellarg($dir)), $out2, $code2);
        $text = implode("\n", $out2);
        // The membership/rescued strata still fail on their own requirement (a Publix storefront is
        // not a fuel, store_in_target, unconfirmed or rescued CVS row) — the checker is not fooled.
        $this->assertNotSame(0, $code2);
        $this->assertStringContainsString('no fuel membership on the sampled row', $text);
        $this->assertStringContainsString('sampled row is not a rescued CVS row', $text);
        $this->assertStringContainsString('PASS category:superstore r1', $text);

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    /** @test */
    public function the_duckdb_runner_and_comparator_reach_no_database(): void
    {
        foreach (['bin/run_duckdb_sql.py', 'bin/compare_sample.py'] as $file) {
            $src = strtolower(self::source(self::DIR . '/' . $file));
            foreach (['psycopg', 'pg8000', 'sqlalchemy', 'subprocess', 'spatial_database_url', 'environ'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $src, "{$file} must not contain {$forbidden}");
            }
        }
    }

    // ── Public logs: nothing names the target ──────────────────────────────────────────────────

    /** @test */
    public function preflight_never_prints_psql_connection_errors(): void
    {
        $leak = "psql: error: connection to server at \"p.abc123.db.postgresbridge.com\" (10.9.8.7), port 5432 failed: "
            . "FATAL:  password authentication failed for user \"ov2op\"\n";

        $down = $this->preflight(['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 2, $leak);
        $this->assertSame(4, $down['code']);
        $this->assertStringContainsString('details withheld', $down['out']);
        $this->assertStringContainsString('connection failure category: authentication', $down['out']);
        foreach (['p.abc123', 'postgresbridge.com"', '10.9.8.7', 'ov2op', 'S3cretPassw0rd'] as $secretish) {
            $this->assertStringNotContainsString($secretish, $down['out'], "a connection failure printed {$secretish}");
        }

        // A failed CHECK shows the committed script's own error line, and still nothing else.
        $scriptError = "psql:/repo/spikes/phase-4-overture-v2-load/sql/preflight.sql:61: ERROR:  division by zero\n" . $leak;
        $failed = $this->preflight(['--v2-state=absent'], ['SPATIAL_DATABASE_URL' => self::GOOD_URL], 3, $scriptError);
        $this->assertSame(5, $failed['code']);
        $this->assertStringContainsString('preflight.sql:61: ERROR:  division by zero', $failed['out']);
        foreach (['10.9.8.7', 'ov2op', 'connection to server'] as $secretish) {
            $this->assertStringNotContainsString($secretish, $failed['out'], "a failed check printed {$secretish}");
        }
    }

    /** @test */
    public function every_secret_job_masks_the_target_before_anything_else_reads_the_secret(): void
    {
        foreach (self::workflow()['jobs'] as $name => $job) {
            $firstSecretStep = null;
            foreach ($job['steps'] ?? [] as $step) {
                if (str_contains(json_encode($step, JSON_UNESCAPED_SLASHES), 'secrets.SPATIAL_DATABASE_URL')) {
                    $firstSecretStep = $step;
                    break;
                }
            }
            if ($firstSecretStep === null) {
                continue;
            }
            $this->assertSame("Mask the target's identity in this job's log", $firstSecretStep['name'] ?? null,
                "job {$name}: the first step that reads the secret must be the masking step");
            $this->assertStringContainsString('bin/mask_log_identity.sh', (string) $firstSecretStep['run']);
        }
    }

    /**
     * @param array<string, string> $env
     * @return array{code: int, out: string}
     */
    private function mask(array $env): array
    {
        $proc = proc_open(['bash', base_path(self::DIR . '/bin/mask_log_identity.sh')], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes, null, array_merge(['PATH' => (string) getenv('PATH')], $env));
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($proc), 'out' => $out];
    }

    /** @test */
    public function the_masking_step_masks_in_actions_and_is_silent_everywhere_else(): void
    {
        // Operator container / any ordinary terminal: prints NOTHING (printing the mask command
        // would display exactly what it hides).
        $quiet = $this->mask(['SPATIAL_DATABASE_URL' => self::GOOD_URL]);
        $this->assertSame(0, $quiet['code']);
        $this->assertSame('', $quiet['out']);

        // GitHub Actions: host, user and password are registered as masks, and appear nowhere else.
        // A stray GITHUB_ACTIONS without the runner's own variables is not a runner: still silent.
        $stray = $this->mask(['GITHUB_ACTIONS' => 'true', 'SPATIAL_DATABASE_URL' => self::GOOD_URL]);
        $this->assertSame('', $stray['out']);

        $gha = $this->mask(['GITHUB_ACTIONS' => 'true', 'GITHUB_RUN_ID' => '1', 'RUNNER_TEMP' => sys_get_temp_dir(),
            'SPATIAL_DATABASE_URL' => self::GOOD_URL]);
        $this->assertSame(0, $gha['code']);
        foreach (['p.abc123.db.postgresbridge.com', 'ov2op', 'S3cretPassw0rd'] as $value) {
            $this->assertStringContainsString("::add-mask::{$value}\n", $gha['out']);
        }
        foreach (explode("\n", trim($gha['out'])) as $line) {
            if (! str_starts_with($line, '::add-mask::')) {
                foreach (['p.abc123', 'ov2op', 'S3cretPassw0rd'] as $value) {
                    $this->assertStringNotContainsString($value, $line, 'a value appeared outside a mask command');
                }
            }
        }

        // Never set -x, and never print the VALUE (a message naming the variable is fine).
        $this->assertDoesNotMatchRegularExpression('/set -x|(echo|printf)[^\n]*\$\{?SPATIAL_DATABASE_URL/', self::source(self::DIR . '/bin/mask_log_identity.sh'));
    }
}
