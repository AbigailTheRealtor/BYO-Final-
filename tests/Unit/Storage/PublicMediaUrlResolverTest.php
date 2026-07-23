<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\ListingMediaUrl;
use App\Support\Storage\ListingStorageReader;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-D.2 (HI-05A) — ListingStorageReader::publicUrl() / ListingMediaUrl behavior.
 *
 * URL generation is pure string work (no filesystem/network); the object
 * secondary is modeled with a local-driver disk carrying a distinct 'url', so
 * every case is deterministic and offline. Covers: local equivalence to the
 * prior asset('storage/…') output, object-first delegation, prefix scoping,
 * public/private isolation, and safe degradation.
 */
class PublicMediaUrlResolverTest extends TestCase
{
    private const KEY = 'auction/images/x.jpg';

    private const OUT = 'agent-offer-presets/1/card.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        // Defensive: any accidental object touch stays offline. The public disk
        // is left on its real config so url() reflects production output.
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    private function reader(): ListingStorageReader
    {
        return app(ListingStorageReader::class);
    }

    /** A local-driver stand-in for the public object secondary with a distinct URL. */
    private function useObjectSecondary(string $url = 'https://cdn.example.test/o'): void
    {
        config([
            'filesystems.disks.obj_public' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/obj_public'),
                'url' => $url,
            ],
            'listing_storage.public_secondary_disk' => 'obj_public',
        ]);
    }

    /** (1) DEFAULT local mode: output is byte-identical to the prior public URL. */
    public function test_local_mode_matches_prior_public_url(): void
    {
        $url = $this->reader()->publicUrl(self::KEY);

        // The three idioms this replaced all produced APP_URL.'/storage/'.$key.
        $this->assertSame(asset('storage/' . self::KEY), $url);
        $this->assertSame(Storage::disk('public')->url(self::KEY), $url);
    }

    /** (1b) The ListingMediaUrl façade returns exactly the reader's result. */
    public function test_facade_delegates_to_reader(): void
    {
        $this->assertSame($this->reader()->publicUrl(self::KEY), ListingMediaUrl::get(self::KEY));
        $this->assertSame(asset('storage/' . self::KEY), ListingMediaUrl::get(self::KEY));
    }

    /** (2) object_first: the public secondary disk's URL is returned. */
    public function test_object_first_returns_secondary_url(): void
    {
        $this->useObjectSecondary();
        config(['listing_storage.public_read' => 'object_first']);

        $url = $this->reader()->publicUrl(self::KEY);

        $this->assertSame('https://cdn.example.test/o/' . self::KEY, $url);
        $this->assertNotSame(asset('storage/' . self::KEY), $url);
    }

    /** (3) prefix-scoped object_first affects only matching keys; others stay local. */
    public function test_prefix_scope_applies_consistently(): void
    {
        $this->useObjectSecondary();
        config([
            'listing_storage.public_read' => 'object_first',
            'listing_storage.read_prefixes' => 'auction/images',
        ]);

        // In scope → secondary URL.
        $this->assertSame(
            'https://cdn.example.test/o/' . self::KEY,
            $this->reader()->publicUrl(self::KEY)
        );

        // (4) Out of scope → local public URL unchanged.
        $this->assertSame(
            asset('storage/' . self::OUT),
            $this->reader()->publicUrl(self::OUT)
        );
    }

    /**
     * (5) ISOLATION: a public_secondary_disk misconfigured to the private
     * secondary (or the resolved private disk) is REFUSED — the local public URL
     * is returned, never a private object URL.
     */
    public function test_private_disk_is_never_selected_for_public_url(): void
    {
        config(['listing_storage.public_read' => 'object_first']);

        foreach (['s3_private', 'private'] as $forbidden) {
            config(['listing_storage.public_secondary_disk' => $forbidden]);
            $this->assertSame(
                asset('storage/' . self::KEY),
                $this->reader()->publicUrl(self::KEY),
                "public url must fall back to local when secondary is [{$forbidden}]"
            );
        }
    }

    /** (6) A missing/invalid public secondary degrades safely to the local URL. */
    public function test_missing_secondary_degrades_to_local(): void
    {
        config([
            'listing_storage.public_read' => 'object_first',
            'listing_storage.public_secondary_disk' => 'no_such_disk_xyz',
        ]);

        $this->assertSame(asset('storage/' . self::KEY), $this->reader()->publicUrl(self::KEY));
    }

    /** (7) publicUrl never mutates private read behavior: privateResponse still works. */
    public function test_private_read_behavior_unchanged(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        Storage::disk('private')->put(self::KEY, '%PDF-1.4');

        $resp = $this->reader()->privateResponse(self::KEY, 'x.pdf');
        $this->assertNotNull($resp);
        $this->assertSame(200, $resp->getStatusCode());
    }
}
