<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;

/**
 * R2-D.4 (HI-05A) — physical separation of the public object-storage bucket.
 *
 * The 's3_public' disk must target a dedicated AWS_PUBLIC_BUCKET so the private
 * bucket can keep Block Public Access on while the public bucket is independently
 * readable. When AWS_PUBLIC_BUCKET is unset, resolution must fall back to
 * AWS_BUCKET, preserving the prior single-bucket behavior byte-for-byte.
 *
 * These tests re-evaluate the real config/filesystems.php under fully controlled,
 * throw-away env values (never the real Replit secrets) so the AWS_PUBLIC_BUCKET
 * fallback expression is exercised directly. No network calls are made.
 */
class PublicBucketSeparationTest extends TestCase
{
    /** Isolated, non-secret placeholder values used only inside this test. */
    private const FAKE_PRIVATE_BUCKET = 'test-private-bucket';
    private const FAKE_PUBLIC_BUCKET = 'test-public-bucket';
    private const FAKE_PUBLIC_URL = 'https://example.test/public-media';

    public function test_s3_public_bucket_falls_back_to_aws_bucket_when_public_unset(): void
    {
        $disks = $this->resolveFilesystemsWithEnv([
            'AWS_BUCKET' => self::FAKE_PRIVATE_BUCKET,
            'AWS_PUBLIC_BUCKET' => null, // explicitly unset
        ]);

        $this->assertSame(self::FAKE_PRIVATE_BUCKET, $disks['disks']['s3_public']['bucket']);
    }

    public function test_s3_public_bucket_uses_public_bucket_when_set(): void
    {
        $disks = $this->resolveFilesystemsWithEnv([
            'AWS_BUCKET' => self::FAKE_PRIVATE_BUCKET,
            'AWS_PUBLIC_BUCKET' => self::FAKE_PUBLIC_BUCKET,
        ]);

        $this->assertSame(self::FAKE_PUBLIC_BUCKET, $disks['disks']['s3_public']['bucket']);
    }

    public function test_s3_private_bucket_always_uses_aws_bucket(): void
    {
        // Even with a distinct public bucket set, the private disk stays on AWS_BUCKET.
        $disks = $this->resolveFilesystemsWithEnv([
            'AWS_BUCKET' => self::FAKE_PRIVATE_BUCKET,
            'AWS_PUBLIC_BUCKET' => self::FAKE_PUBLIC_BUCKET,
        ]);

        $this->assertSame(self::FAKE_PRIVATE_BUCKET, $disks['disks']['s3_private']['bucket']);

        // And when public falls back, both disks resolve to AWS_BUCKET.
        $fallback = $this->resolveFilesystemsWithEnv([
            'AWS_BUCKET' => self::FAKE_PRIVATE_BUCKET,
            'AWS_PUBLIC_BUCKET' => null,
        ]);
        $this->assertSame(self::FAKE_PRIVATE_BUCKET, $fallback['disks']['s3_private']['bucket']);
    }

    public function test_public_and_private_roots_are_unchanged(): void
    {
        $disks = $this->resolveFilesystemsWithEnv([
            'AWS_BUCKET' => self::FAKE_PRIVATE_BUCKET,
            'AWS_PUBLIC_BUCKET' => self::FAKE_PUBLIC_BUCKET,
        ]);

        $this->assertSame('public', $disks['disks']['s3_public']['root']);
        $this->assertSame('private', $disks['disks']['s3_private']['root']);
        // Private disk must never carry a URL.
        $this->assertArrayNotHasKey('url', $disks['disks']['s3_private']);
    }

    public function test_public_url_still_reads_from_aws_url(): void
    {
        $disks = $this->resolveFilesystemsWithEnv([
            'AWS_BUCKET' => self::FAKE_PRIVATE_BUCKET,
            'AWS_PUBLIC_BUCKET' => self::FAKE_PUBLIC_BUCKET,
            'AWS_URL' => self::FAKE_PUBLIC_URL,
        ]);

        $this->assertSame(self::FAKE_PUBLIC_URL, $disks['disks']['s3_public']['url']);
    }

    public function test_listing_storage_defaults_remain_inert(): void
    {
        // No selector/read/dual-write env set → defaults must be the inert baseline.
        $ls = $this->resolveConfigWithEnv('listing_storage.php', [
            'STORAGE_DUAL_WRITE' => null,
            'LISTING_PRIVATE_READ' => null,
            'LISTING_PUBLIC_READ' => null,
            'LISTING_READ_PREFIXES' => null,
        ]);

        $this->assertFalse($ls['dual_write']);
        $this->assertSame('local', $ls['private_read']);
        $this->assertSame('local', $ls['public_read']);
        $this->assertSame('', $ls['read_prefixes']);
    }

    // --- helpers ---------------------------------------------------------

    private function resolveFilesystemsWithEnv(array $env): array
    {
        return $this->resolveConfigWithEnv('filesystems.php', $env);
    }

    /**
     * Re-evaluate a raw config file with the given env vars applied, then fully
     * restore the previous environment. Values are set across $_ENV, $_SERVER and
     * putenv so Laravel's env() resolves them regardless of adapter order. A null
     * value means "ensure this key is unset" so fallback branches are exercised.
     */
    private function resolveConfigWithEnv(string $file, array $env): array
    {
        $saved = [];
        foreach ($env as $key => $value) {
            $saved[$key] = [
                'env_set' => array_key_exists($key, $_ENV),
                'env_val' => $_ENV[$key] ?? null,
                'srv_set' => array_key_exists($key, $_SERVER),
                'srv_val' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];

            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        try {
            return require config_path($file);
        } finally {
            foreach ($saved as $key => $s) {
                if ($s['env_set']) {
                    $_ENV[$key] = $s['env_val'];
                } else {
                    unset($_ENV[$key]);
                }
                if ($s['srv_set']) {
                    $_SERVER[$key] = $s['srv_val'];
                } else {
                    unset($_SERVER[$key]);
                }
                if ($s['getenv'] === false) {
                    putenv($key);
                } else {
                    putenv("{$key}={$s['getenv']}");
                }
            }
        }
    }
}
