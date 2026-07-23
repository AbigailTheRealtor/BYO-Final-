<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingStorageReader;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-D.1 (HI-05A) — ListingStorageReader PRIVATE read behavior.
 *
 * All disks are faked (local adapters), so no network/object-storage call ever
 * occurs. Covers: the default local-only chain (private → public legacy),
 * object-first-with-fallback when opted in, prefix scoping, and the guarantee
 * that the default mode never consults the object secondary.
 */
class ListingStorageReaderTest extends TestCase
{
    private const DOC = 'auction/documents/a.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('s3_private');
        // Defaults: private_read=local, read_prefixes empty (config defaults).
    }

    private function reader(): ListingStorageReader
    {
        return app(ListingStorageReader::class);
    }

    /** (1) default: a file on the local private disk is served. */
    public function test_default_serves_local_private(): void
    {
        Storage::disk('private')->put(self::DOC, '%PDF-1.4');

        $resp = $this->reader()->privateResponse(self::DOC, 'a.pdf');

        $this->assertNotNull($resp);
        $this->assertSame(200, $resp->getStatusCode());
    }

    /** (2) default: a legacy file on the local public disk is still served (fallback). */
    public function test_default_falls_back_to_local_public_legacy(): void
    {
        Storage::disk('public')->put(self::DOC, '%PDF-1.4');

        $resp = $this->reader()->privateResponse(self::DOC, 'a.pdf');

        $this->assertNotNull($resp);
        $this->assertSame(200, $resp->getStatusCode());
    }

    /** (3) default: missing on every local disk → null (caller aborts 404). */
    public function test_default_returns_null_when_absent(): void
    {
        $this->assertNull($this->reader()->privateResponse(self::DOC, 'a.pdf'));
    }

    /**
     * (4) DEFAULT NEVER TOUCHES THE OBJECT SECONDARY: a file present ONLY on
     * s3_private is not served while private_read='local' — proving no
     * object-storage read happens by default.
     */
    public function test_default_ignores_object_secondary(): void
    {
        Storage::disk('s3_private')->put(self::DOC, '%PDF-1.4');

        $this->assertNull($this->reader()->privateResponse(self::DOC, 'a.pdf'));
    }

    /** (5) object_first: a file present only on the private secondary is served. */
    public function test_object_first_serves_from_secondary(): void
    {
        config(['listing_storage.private_read' => 'object_first']);
        Storage::disk('s3_private')->put(self::DOC, '%PDF-1.4');

        $resp = $this->reader()->privateResponse(self::DOC, 'a.pdf');

        $this->assertNotNull($resp);
        $this->assertSame(200, $resp->getStatusCode());
    }

    /** (6) object_first: an object miss falls back to the local private disk. */
    public function test_object_first_falls_back_to_local(): void
    {
        config(['listing_storage.private_read' => 'object_first']);
        // Nothing on s3_private; file only on local private.
        Storage::disk('private')->put(self::DOC, '%PDF-1.4');

        $resp = $this->reader()->privateResponse(self::DOC, 'a.pdf');

        $this->assertNotNull($resp);
        $this->assertSame(200, $resp->getStatusCode());
    }

    /**
     * (7b) object_first: an UNDEFINED/misconfigured secondary disk must degrade
     * gracefully to the local chain — resolving the disk cannot 500 the request.
     */
    public function test_object_first_degrades_gracefully_when_secondary_undefined(): void
    {
        config([
            'listing_storage.private_read' => 'object_first',
            'listing_storage.private_secondary_disk' => 'no_such_disk_xyz',
        ]);
        Storage::disk('private')->put(self::DOC, '%PDF-1.4');

        $resp = $this->reader()->privateResponse(self::DOC, 'a.pdf');

        $this->assertNotNull($resp); // fell back to local private; did not throw
        $this->assertSame(200, $resp->getStatusCode());
    }

    /**
     * (8) object_first + read_prefixes: a key OUTSIDE the scope is not read from
     * the object secondary (only the local chain is consulted).
     */
    public function test_object_first_respects_prefix_scope(): void
    {
        config([
            'listing_storage.private_read' => 'object_first',
            'listing_storage.read_prefixes' => 'auction/documents',
        ]);

        // In-scope key present only on the secondary → served.
        Storage::disk('s3_private')->put('auction/documents/in.pdf', '%PDF-1.4');
        $this->assertNotNull($this->reader()->privateResponse('auction/documents/in.pdf', 'in.pdf'));

        // Out-of-scope key present only on the secondary → NOT served (object skipped).
        Storage::disk('s3_private')->put('seller-disclosures/1/out.pdf', '%PDF-1.4');
        $this->assertNull($this->reader()->privateResponse('seller-disclosures/1/out.pdf', 'out.pdf'));
    }
}
