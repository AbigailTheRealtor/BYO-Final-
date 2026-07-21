<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;

/**
 * R2-A (HI-05A) — the S3 preflight command must be inert unless explicitly
 * enabled AND confirmed. These tests exercise only the disabled path, so no
 * network call or AWS client construction ever occurs. (STORAGE_S3_PREFLIGHT_ENABLED
 * is unset in the test environment, so the env gate is off by default.)
 */
class S3PreflightCommandTest extends TestCase
{
    /** (12) with neither --confirm nor the env gate, it is a no-op. */
    public function test_preflight_is_disabled_without_confirm(): void
    {
        $this->artisan('storage:s3-preflight')
            ->expectsOutput('status=DISABLED')
            ->assertExitCode(0);
    }

    /** (12) --confirm alone (env gate off in test env) is still disabled. */
    public function test_preflight_disabled_when_env_gate_off_even_with_confirm(): void
    {
        $this->artisan('storage:s3-preflight', ['--confirm' => true])
            ->expectsOutput('status=DISABLED')
            ->assertExitCode(0);
    }

    /** (12) the command is registered and the disabled path emits valid JSON. */
    public function test_preflight_json_disabled_output(): void
    {
        $this->artisan('storage:s3-preflight', ['--json' => true])
            ->expectsOutput(json_encode([
                'status' => 'DISABLED',
                'message' => 'Preflight is disabled. Pass --confirm and set STORAGE_S3_PREFLIGHT_ENABLED=true to run.',
            ]))
            ->assertExitCode(0);
    }
}
