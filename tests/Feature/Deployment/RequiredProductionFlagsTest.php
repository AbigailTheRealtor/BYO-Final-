<?php

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The fail-closed required-production-flags gate.
 *
 * WHAT THIS GUARDS
 * ----------------
 * The shipped config defaults make the modern platform the version-controlled
 * floor, which handles an ABSENT variable. This gate handles the variable that is
 * PRESENT AND WRONG — a stale secret, a typo, a value left behind by a finished
 * pilot. That failure is silent in a way the others are not: the application
 * boots, answers 200, passes its health check, and serves a superseded surface.
 *
 * The behaviours asserted here are each the kind that is true on the day it is
 * written and quietly false later: that the gate refuses, that it refuses BEFORE
 * a migration or a port bind, that an unreadable contract fails closed rather
 * than passing vacuously, and — most important — that the contract can never grow
 * a safety switch.
 *
 * The version-controlled DEFAULTS are proven separately, in
 * RequiredProductionDefaultsTest, which strips the environment rather than
 * setting config. Both halves are needed: this file proves the gate catches a
 * wrong value, that one proves an absent value is already right.
 */
class RequiredProductionFlagsTest extends TestCase
{
    /** The contracted, all-satisfied configuration. */
    private function satisfiedConfig(): array
    {
        return [
            'hire_agent_hero.redesign_enabled'        => true,
            'hire_agent_hero.redesign_roles'          => ['seller', 'buyer', 'landlord', 'tenant'],
            'hire_agent_detail.redesign_enabled'      => true,
            'hire_agent_detail.redesign_roles'        => ['seller', 'buyer', 'landlord', 'tenant'],
            'mls_direct_import.prefill_enabled'       => true,
            'mls_direct_import.quick_import_enabled'  => true,
            'required_production_flags.enforced'      => true,
        ];
    }

    private function runGate(array $options = []): array
    {
        $code   = Artisan::call('deploy:require-flags', $options);
        $output = Artisan::output();

        return [$code, $output];
    }

    private function script(string $name): string
    {
        $path = base_path('deploy/' . $name);

        $this->assertFileExists($path, "deploy/{$name} must exist");

        return (string) file_get_contents($path);
    }

    // ── A. the success path ─────────────────────────────────────────────────

    public function test_a_satisfied_contract_exits_zero(): void
    {
        config($this->satisfiedConfig());

        [$code, $output] = $this->runGate();

        $this->assertSame(0, $code, 'A satisfied contract must not block a deploy.');
        $this->assertStringContainsString('all required flags satisfied', $output);
        $this->assertStringNotContainsString('[FAIL]', $output);
    }

    // ── B. one wrong value refuses ──────────────────────────────────────────

    public function test_a_single_wrong_boolean_exits_nonzero(): void
    {
        config($this->satisfiedConfig());
        config(['hire_agent_detail.redesign_enabled' => false]);

        [$code, $output] = $this->runGate();

        $this->assertSame(1, $code, 'A wrong required flag must refuse the deploy.');
        $this->assertStringContainsString('hire_agent_detail.redesign_enabled', $output);
        $this->assertStringContainsString('Refusing to start', $output);
    }

    public function test_a_missing_role_exits_nonzero(): void
    {
        config($this->satisfiedConfig());
        // The landlord pilot value, left behind. This is the exact shape of the
        // regression: enabled, but only for the role the pilot ran on.
        config(['hire_agent_detail.redesign_roles' => ['landlord']]);

        [$code, $output] = $this->runGate();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('hire_agent_detail.redesign_roles', $output);
    }

    public function test_every_required_key_is_independently_load_bearing(): void
    {
        // Each contracted key must be able to fail the gate ON ITS OWN. A contract
        // where one entry is never actually consulted is a contract with a hole in
        // it that no single-key test would find.
        $breaks = [
            'hire_agent_hero.redesign_enabled'       => false,
            'hire_agent_hero.redesign_roles'         => ['landlord'],
            'hire_agent_detail.redesign_enabled'     => false,
            'hire_agent_detail.redesign_roles'       => ['landlord'],
            'mls_direct_import.prefill_enabled'      => false,
            'mls_direct_import.quick_import_enabled' => false,
        ];

        foreach ($breaks as $key => $wrongValue) {
            config($this->satisfiedConfig());
            config([$key => $wrongValue]);

            [$code, $output] = $this->runGate();

            $this->assertSame(1, $code, "Breaking {$key} alone must refuse the deploy.");
            $this->assertStringContainsString($key, $output);
        }
    }

    // ── subset semantics on role lists ──────────────────────────────────────

    public function test_an_extra_role_still_satisfies_the_contract(): void
    {
        // "At least these", never "exactly these". A fifth role adopting the
        // redesign must not fail a deployment for being extra.
        config($this->satisfiedConfig());
        config([
            'hire_agent_detail.redesign_roles' => ['seller', 'buyer', 'landlord', 'tenant', 'future_role'],
            'hire_agent_hero.redesign_roles'   => ['future_role', 'tenant', 'landlord', 'buyer', 'seller'],
        ]);

        [$code] = $this->runGate();

        $this->assertSame(0, $code, 'Extra roles, and a different order, must both still satisfy the contract.');
    }

    public function test_a_non_array_role_value_does_not_satisfy_an_array_expectation(): void
    {
        config($this->satisfiedConfig());
        config(['hire_agent_detail.redesign_roles' => 'seller,buyer,landlord,tenant']);

        [$code] = $this->runGate();

        $this->assertSame(1, $code, 'A raw string must not pass a list expectation by looking similar.');
    }

    // ── D. an unreadable contract fails closed ──────────────────────────────

    public function test_an_empty_contract_fails_closed(): void
    {
        config($this->satisfiedConfig());
        config(['required_production_flags.required' => []]);

        [$code, $output] = $this->runGate();

        $this->assertSame(1, $code, 'An empty contract must fail closed, not pass vacuously.');
        $this->assertStringContainsString('EMPTY or unreadable', $output);
    }

    public function test_a_missing_contract_file_fails_closed(): void
    {
        config($this->satisfiedConfig());
        config()->offsetUnset('required_production_flags');

        [$code, $output] = $this->runGate();

        $this->assertSame(1, $code, 'A contract that did not load must fail closed.');
        $this->assertStringContainsString('EMPTY or unreadable', $output);
    }

    // ── E. the escape hatch is loud, and it works ───────────────────────────

    public function test_the_escape_hatch_warns_loudly_and_allows_startup(): void
    {
        config($this->satisfiedConfig());
        config([
            'hire_agent_detail.redesign_enabled'   => false,
            'required_production_flags.enforced'   => false,
        ]);

        [$code, $output] = $this->runGate();

        $this->assertSame(0, $code, 'The escape hatch must allow startup to continue.');
        $this->assertStringContainsString('THE GATE IS OFF', $output);
        $this->assertStringContainsString('REQUIRED_PRODUCTION_FLAGS_ENFORCED=false', $output);
        // The violation is still named. An override must not also hide what it overrode.
        $this->assertStringContainsString('hire_agent_detail.redesign_enabled', $output);
    }

    public function test_the_escape_hatch_is_loud_about_an_unreadable_contract_too(): void
    {
        config($this->satisfiedConfig());
        config([
            'required_production_flags.required' => [],
            'required_production_flags.enforced' => false,
        ]);

        [$code, $output] = $this->runGate();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('THE GATE IS OFF', $output);
    }

    // ── F. --report can never gate ──────────────────────────────────────────

    public function test_report_exits_zero_even_when_the_contract_is_violated(): void
    {
        config($this->satisfiedConfig());
        config(['hire_agent_detail.redesign_enabled' => false]);

        [$code, $output] = $this->runGate(['--report' => true]);

        $this->assertSame(0, $code, '--report must never gate; start-serving.sh depends on it.');
        $this->assertStringContainsString('exiting 0 without gating', $output);
        // It still reports the violation — that is the entire point of the mode.
        $this->assertStringContainsString('hire_agent_detail.redesign_enabled', $output);
    }

    public function test_report_exits_zero_on_an_unreadable_contract(): void
    {
        config($this->satisfiedConfig());
        config()->offsetUnset('required_production_flags');

        [$code] = $this->runGate(['--report' => true]);

        $this->assertSame(0, $code, '--report must not gate even on the fail-closed path.');
    }

    public function test_report_exits_zero_on_the_success_path(): void
    {
        config($this->satisfiedConfig());

        [$code] = $this->runGate(['--report' => true]);

        $this->assertSame(0, $code);
    }

    // ── G. nothing secret reaches the output ────────────────────────────────

    public function test_the_output_carries_no_credential_values(): void
    {
        config($this->satisfiedConfig());
        // Real-shaped values on keys the gate must never touch.
        config([
            'bridge.dataset'         => 'secret-dataset-name',
            'bridge.token'           => 'SECRETBRIDGETOKENVALUE0123456789',
            'services.openai.key'    => 'sk-secret-openai-key-value',
            'app.key'                => 'base64:SECRETAPPKEYVALUE',
            'database.connections.pgsql.password' => 'secret-db-password',
        ]);

        foreach ([[], ['--report' => true]] as $options) {
            [, $output] = $this->runGate($options);

            foreach ([
                'secret-dataset-name',
                'SECRETBRIDGETOKENVALUE0123456789',
                'sk-secret-openai-key-value',
                'base64:SECRETAPPKEYVALUE',
                'secret-db-password',
            ] as $secret) {
                $this->assertStringNotContainsString(
                    $secret,
                    $output,
                    'The gate must print flag state only — never a credential.'
                );
            }
        }
    }

    // ── the contract may never grow a safety switch ─────────────────────────

    public function test_the_contract_names_no_safety_switch_or_credential(): void
    {
        // The single most important assertion in this file. If this gate could
        // require a safety switch to be ON, it would become a deploy-time
        // mechanism for enabling a consumer-facing or spend-incurring feature,
        // decided in a file nobody reads during a rollout conversation.
        $forbidden = [
            'bya_compatibility',      // kill switch + GA
            'location_dna',           // Location DNA gates
            'criteria_location_dna',  // geography cascade / preview
            'census_geocoder',        // outbound geocoder
            'address_point_corpus',   // corpus rung
            'mls_match_check',        // Buyer/Tenant scoring page
            'dna_scores',             // production score generation
            'matching',               // Matching V2 persistence
            'bridge',                 // Bridge credentials
            'services',               // API credentials
            'database',               // database credentials
            'app.key',                // application key
        ];

        $contract = (array) config('required_production_flags.required');

        $this->assertNotEmpty($contract, 'The shipped contract must not be empty.');

        foreach (array_keys($contract) as $key) {
            foreach ($forbidden as $prefix) {
                $this->assertStringStartsNotWith(
                    $prefix,
                    (string) $key,
                    "The required-production contract must never name {$prefix}: it is a safety switch or a credential."
                );
            }
        }
    }

    public function test_the_shipped_contract_is_exactly_the_six_product_surfaces(): void
    {
        $this->assertSame(
            [
                'hire_agent_hero.redesign_enabled',
                'hire_agent_hero.redesign_roles',
                'hire_agent_detail.redesign_enabled',
                'hire_agent_detail.redesign_roles',
                'mls_direct_import.prefill_enabled',
                'mls_direct_import.quick_import_enabled',
            ],
            array_keys((array) config('required_production_flags.required')),
            'Adding to this contract is a deliberate act; it must not happen by accident.'
        );
    }

    public function test_the_shipped_contract_is_satisfied_by_the_shipped_defaults(): void
    {
        // A contract the shipped configuration cannot satisfy would block every
        // deploy from a clean environment.
        [$code, $output] = $this->runGate();

        $this->assertSame(0, $code, 'The shipped defaults must satisfy the shipped contract: ' . $output);
    }

    // ── C. the production script gates, in the right place ──────────────────

    public function test_the_production_script_calls_the_gate(): void
    {
        $this->assertStringContainsString(
            'php artisan deploy:require-flags',
            $this->script('start-production.sh'),
            'The production start script must call the required-flag gate.'
        );
    }

    public function test_the_production_gate_call_is_not_suppressed(): void
    {
        $script = $this->script('start-production.sh');

        $this->assertMatchesRegularExpression(
            '/^php artisan deploy:require-flags\s*$/m',
            $script,
            'The production gate must not be suppressed with `|| true`, `|| :` or `--report`.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/deploy:require-flags[^\n]*(\|\||--report)/',
            $script,
            'The production gate must be able to fail the deploy.'
        );
    }

    public function test_the_production_gate_runs_before_migrations_and_before_serving(): void
    {
        $script = $this->script('start-production.sh');

        $gate    = strpos($script, "\nphp artisan deploy:require-flags");
        $migrate = strpos($script, "\nphp artisan migrate --force");
        $serve   = strpos($script, "\nexec php artisan serve");

        $this->assertNotFalse($gate, 'The gate call must be present.');
        $this->assertNotFalse($migrate, 'The migrate call must be present.');
        $this->assertNotFalse($serve, 'The serve call must be present.');

        $this->assertLessThan(
            $migrate,
            $gate,
            'The gate must run BEFORE migrations: a deploy stopped before any schema change is trivially re-runnable.'
        );

        $this->assertLessThan(
            $serve,
            $gate,
            'The gate must run BEFORE the server binds a port.'
        );
    }

    // ── the workspace script reports, and can never refuse ──────────────────

    public function test_the_workspace_script_calls_the_gate_in_report_mode_only(): void
    {
        $script = $this->script('start-serving.sh');

        $this->assertStringContainsString(
            'php artisan deploy:require-flags --report || true',
            $script,
            'The workspace call must be --report AND || true, so it can never stop a local server.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^php artisan deploy:require-flags(?!\s+--report)/m',
            $script,
            'The workspace script must never call the gate in gating mode.'
        );
    }

    public function test_the_workspace_script_still_binds_a_port_when_the_contract_is_violated(): void
    {
        // The behavioural half of the assertion above: --report returns 0 on a
        // violated contract, so `set -e` cannot stop start-serving.sh there.
        config($this->satisfiedConfig());
        config(['hire_agent_detail.redesign_enabled' => false]);

        [$code] = $this->runGate(['--report' => true]);

        $this->assertSame(0, $code);
    }

    // ── the command is the only reader, and it writes nothing ───────────────

    public function test_the_contract_has_exactly_one_reader(): void
    {
        $readers = [];

        $directories = [app_path(), base_path('resources'), base_path('routes'), base_path('config')];

        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $path = $file->getPathname();

                // The config file itself is the contract, not a reader of it.
                if ($path === config_path('required_production_flags.php')) {
                    continue;
                }

                if (str_contains((string) file_get_contents($path), 'required_production_flags')) {
                    $readers[] = str_replace(base_path() . '/', '', $path);
                }
            }
        }

        sort($readers);

        $this->assertSame(
            ['app/Console/Commands/DeployRequireProductionFlags.php'],
            $readers,
            'Only the gate command may read the contract; a second reader is a second opinion about what production requires.'
        );
    }

    public function test_the_command_performs_no_writes(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/DeployRequireProductionFlags.php'));

        foreach ([
            'file_put_contents', 'fopen', 'unlink', 'mkdir', 'putenv',
            'DB::', 'Schema::', 'Storage::', 'Artisan::call', 'exec(', 'shell_exec', 'system(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "The gate must stay read-only; {$forbidden} has no place in it."
            );
        }

        // It must not write config either — a gate that repairs a flag hides the
        // fact that the environment was wrong.
        $this->assertDoesNotMatchRegularExpression(
            '/config\(\s*\[/',
            $source,
            'The gate must never set configuration; it compares and reports.'
        );
    }
}
