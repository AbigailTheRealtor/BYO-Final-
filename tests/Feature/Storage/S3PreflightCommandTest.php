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

    /**
     * R2-E0: when enabled + confirmed, the preflight now validates the PUBLIC
     * bucket config alongside the private one, BEFORE any network call. With
     * s3_private complete (fake) but s3_public.bucket empty, it fails closed as
     * MISSING_CONFIG without touching the network — proving s3_public is probed.
     * (Before R2-E0 only s3_private was validated, so this path would have tried
     * a network HeadBucket instead.)
     */
    public function test_preflight_validates_public_bucket_config_before_network(): void
    {
        if (! class_exists('League\\Flysystem\\AwsS3v3\\AwsS3Adapter')) {
            $this->markTestSkipped('S3 Flysystem adapter not installed; adapter check precedes config check.');
        }

        // Enable the env gate for this test only (env() reads $_ENV/$_SERVER/putenv).
        putenv('STORAGE_S3_PREFLIGHT_ENABLED=true');
        $_ENV['STORAGE_S3_PREFLIGHT_ENABLED'] = 'true';
        $_SERVER['STORAGE_S3_PREFLIGHT_ENABLED'] = 'true';

        // s3_private complete (fake, offline) so validation passes it and reaches
        // the public check; s3_public.bucket empty so it fails closed before any
        // network HeadBucket. No real credentials and no network are used.
        config([
            'filesystems.disks.s3_private.key' => 'test-key',
            'filesystems.disks.s3_private.secret' => 'test-secret',
            'filesystems.disks.s3_private.region' => 'auto',
            'filesystems.disks.s3_private.bucket' => 'test-private',
            'filesystems.disks.s3_public.key' => 'test-key',
            'filesystems.disks.s3_public.secret' => 'test-secret',
            'filesystems.disks.s3_public.region' => 'auto',
            'filesystems.disks.s3_public.bucket' => '',
        ]);

        try {
            $this->artisan('storage:s3-preflight', ['--confirm' => true, '--json' => true])
                ->expectsOutput(json_encode([
                    'status' => 'MISSING_CONFIG',
                    'message' => 'S3 configuration is incomplete.',
                ]))
                ->assertExitCode(1);
        } finally {
            putenv('STORAGE_S3_PREFLIGHT_ENABLED');
            unset($_ENV['STORAGE_S3_PREFLIGHT_ENABLED'], $_SERVER['STORAGE_S3_PREFLIGHT_ENABLED']);
        }
    }

    /**
     * Enabled-path smoke for environments WITHOUT the S3 adapter: enabled +
     * confirmed short-circuits to MISSING_ADAPTER before any config/network work.
     * Complements the MISSING_CONFIG test so the enabled path is covered whether or
     * not the adapter is installed.
     */
    public function test_preflight_reports_missing_adapter_when_absent(): void
    {
        if (class_exists('League\\Flysystem\\AwsS3v3\\AwsS3Adapter')) {
            $this->markTestSkipped('S3 adapter installed; MISSING_ADAPTER path not reachable here.');
        }

        putenv('STORAGE_S3_PREFLIGHT_ENABLED=true');
        $_ENV['STORAGE_S3_PREFLIGHT_ENABLED'] = 'true';
        $_SERVER['STORAGE_S3_PREFLIGHT_ENABLED'] = 'true';

        try {
            $this->artisan('storage:s3-preflight', ['--confirm' => true, '--json' => true])
                ->expectsOutput(json_encode([
                    'status' => 'MISSING_ADAPTER',
                    'message' => 'S3 Flysystem adapter is not installed.',
                ]))
                ->assertExitCode(1);
        } finally {
            putenv('STORAGE_S3_PREFLIGHT_ENABLED');
            unset($_ENV['STORAGE_S3_PREFLIGHT_ENABLED'], $_SERVER['STORAGE_S3_PREFLIGHT_ENABLED']);
        }
    }
}
