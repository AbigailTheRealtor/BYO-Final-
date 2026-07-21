<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingStorageDisks;
use InvalidArgumentException;
use League\Flysystem\Adapter\Local;
use Tests\TestCase;

/**
 * R2-A (HI-05A) — contract tests for the listing-storage disk seam.
 *
 * Proves the seam resolves correctly and, crucially, that it is INERT by
 * default (local disks), that it fails closed on a bad selector, and that no
 * credential/bucket detail leaks through exceptions. No network is performed.
 */
class ListingStorageDisksTest extends TestCase
{
    private function resolver(): ListingStorageDisks
    {
        return new ListingStorageDisks();
    }

    /** (1) local public disk remains the default. */
    public function test_public_disk_name_defaults_to_local_public(): void
    {
        $this->assertSame('public', $this->resolver()->publicDiskName());
    }

    /** (2) local private disk remains the default. */
    public function test_private_disk_name_defaults_to_local_private(): void
    {
        $this->assertSame('private', $this->resolver()->privateDiskName());
    }

    /** (3) selecting s3_public resolves that configured disk. */
    public function test_public_selector_resolves_s3_public(): void
    {
        config(['listing_storage.public_disk' => 's3_public']);
        $this->assertSame('s3_public', $this->resolver()->publicDiskName());
    }

    /** (4) selecting s3_private resolves that configured disk. */
    public function test_private_selector_resolves_s3_private(): void
    {
        config(['listing_storage.private_disk' => 's3_private']);
        $this->assertSame('s3_private', $this->resolver()->privateDiskName());
    }

    /** (5) an undefined selector fails closed (throws), never silent-fallback. */
    public function test_undefined_disk_selector_fails_closed(): void
    {
        config(['listing_storage.private_disk' => 'no_such_disk']);
        $this->expectException(InvalidArgumentException::class);
        $this->resolver()->privateDiskName();
    }

    /** (5b) an empty selector also fails closed. */
    public function test_empty_selector_fails_closed(): void
    {
        config(['listing_storage.public_disk' => '']);
        $this->expectException(InvalidArgumentException::class);
        $this->resolver()->publicDiskName();
    }

    /** (6) private disk has NO 'url' key in configuration. */
    public function test_s3_private_has_no_url_key(): void
    {
        $cfg = config('filesystems.disks.s3_private');
        $this->assertIsArray($cfg);
        $this->assertArrayNotHasKey('url', $cfg);
        $this->assertSame('private', $cfg['visibility']);
    }

    /** (7) public and private prefixes are distinct. */
    public function test_public_and_private_prefixes_are_distinct(): void
    {
        $public = config('filesystems.disks.s3_public.root');
        $private = config('filesystems.disks.s3_private.root');
        $this->assertSame('public', $public);
        $this->assertSame('private', $private);
        $this->assertNotSame($public, $private);
    }

    /** (8) no credential/bucket value leaks through the fail-closed exception. */
    public function test_exception_does_not_leak_credentials_or_bucket(): void
    {
        config([
            'filesystems.disks.s3_private.bucket' => 'SENTINEL-BUCKET-VALUE',
            'filesystems.disks.s3_private.secret' => 'SENTINEL-SECRET-VALUE',
            'listing_storage.private_disk' => 'bogus_disk_sentinel',
        ]);

        try {
            $this->resolver()->privateDiskName();
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $this->assertStringNotContainsString('SENTINEL-BUCKET-VALUE', $msg);
            $this->assertStringNotContainsString('SENTINEL-SECRET-VALUE', $msg);
            $this->assertStringNotContainsString('bogus_disk_sentinel', $msg);
            $this->assertStringContainsString('private_disk', $msg); // selector only
        }
    }

    /** (11) by default the seam resolves to LOCAL adapters — no S3 client built. */
    public function test_default_disks_resolve_to_local_adapters_no_network(): void
    {
        $this->assertInstanceOf(Local::class, $this->resolver()->publicDisk()->getAdapter());
        $this->assertInstanceOf(Local::class, $this->resolver()->privateDisk()->getAdapter());
    }
}
