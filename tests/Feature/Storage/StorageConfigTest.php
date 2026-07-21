<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;

/**
 * R2-A (HI-05A) — config contract: the new object-storage disks exist and are
 * wired to the s3 driver, while the existing local disks are UNCHANGED (proving
 * R2-A introduces the seam without altering current behavior).
 */
class StorageConfigTest extends TestCase
{
    public function test_s3_public_and_private_disks_are_defined_with_s3_driver(): void
    {
        $pub = config('filesystems.disks.s3_public');
        $priv = config('filesystems.disks.s3_private');

        $this->assertIsArray($pub);
        $this->assertIsArray($priv);
        $this->assertSame('s3', $pub['driver']);
        $this->assertSame('s3', $priv['driver']);
        $this->assertSame('public', $pub['root']);
        $this->assertSame('private', $priv['root']);
        // s3_public MAY carry a url (public/CDN base); s3_private MUST NOT.
        $this->assertArrayHasKey('url', $pub);
        $this->assertArrayNotHasKey('url', $priv);
    }

    public function test_existing_local_disks_are_unchanged(): void
    {
        $this->assertSame('local', config('filesystems.disks.public.driver'));
        $this->assertSame('local', config('filesystems.disks.private.driver'));
        // Default disk is still local.
        $this->assertSame('local', config('filesystems.default'));
    }

    public function test_listing_storage_selectors_default_to_local_disks(): void
    {
        $this->assertSame('public', config('listing_storage.public_disk'));
        $this->assertSame('private', config('listing_storage.private_disk'));
    }
}
