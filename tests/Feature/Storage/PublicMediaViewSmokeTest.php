<?php

namespace Tests\Feature\Storage;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R2-D.2 (HI-05A) — view-side smoke coverage for the public-media URL resolver.
 *
 *  - The resolver renders correctly inside a real Blade context (local + object).
 *  - Every migrated view still compiles (no Blade syntax broken by the rewrite).
 *  - No legacy public-media URL idiom remains in the eight approved views.
 *
 * All disks faked; URL generation is offline.
 */
class PublicMediaViewSmokeTest extends TestCase
{
    /** The eight approved R2-D.2 views. */
    private const VIEWS = [
        'resources/views/agent/offer-listing-view.blade.php',
        'resources/views/agent-presets/edit.blade.php',
        'resources/views/buyerAgentAuctionDetail.blade.php',
        'resources/views/hire-agent-direct/preview.blade.php',
        'resources/views/components/listing/client-info.blade.php',
        'resources/views/hire_tenant_agent/view.blade.php',
        'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php',
        'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/tenant-info.blade.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    /** The resolver renders to the local public URL inside a Blade template. */
    public function test_resolver_renders_local_url_in_blade(): void
    {
        $out = Blade::render(
            '<img src="{{ \App\Support\Storage\ListingMediaUrl::get($p) }}">',
            ['p' => 'auction/images/x.jpg']
        );

        $this->assertStringContainsString(asset('storage/auction/images/x.jpg'), $out);
    }

    /** object_first renders the public secondary URL inside a Blade template. */
    public function test_resolver_renders_object_url_in_blade(): void
    {
        config([
            'filesystems.disks.obj_public' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/obj_public'),
                'url' => 'https://cdn.example.test/o',
            ],
            'listing_storage.public_secondary_disk' => 'obj_public',
            'listing_storage.public_read' => 'object_first',
        ]);

        $out = Blade::render(
            '<img src="{{ \App\Support\Storage\ListingMediaUrl::get($p) }}">',
            ['p' => 'auction/images/x.jpg']
        );

        $this->assertStringContainsString('https://cdn.example.test/o/auction/images/x.jpg', $out);
    }

    /** Every migrated view still compiles (Blade syntax intact). */
    public function test_approved_views_compile(): void
    {
        foreach (self::VIEWS as $rel) {
            $path = base_path($rel);
            $this->assertFileExists($path);

            $compiled = Blade::compileString(file_get_contents($path));

            $this->assertNotEmpty($compiled);
            $this->assertStringContainsString('ListingMediaUrl::get', $compiled, "resolver missing in {$rel}");
        }
    }

    /**
     * No legacy public-media URL idiom (asset('storage/…'), Storage::url(),
     * Storage::disk('public')->url()) remains in the approved views. PHP comment
     * lines are excluded — they mention the idioms in prose, not as call sites.
     */
    public function test_no_legacy_media_url_idiom_remains(): void
    {
        $patterns = [
            "/asset\(\s*['\"]storage\//",
            "/Storage::url\(/",
            "/disk\(\s*['\"]public['\"]\s*\)->url\(/",
        ];

        foreach (self::VIEWS as $rel) {
            $lines = file(base_path($rel), FILE_IGNORE_NEW_LINES);
            foreach ($lines as $n => $line) {
                if (preg_match('/^\s*(\/\/|\*|#)/', $line)) {
                    continue; // skip comment lines
                }
                foreach ($patterns as $p) {
                    $this->assertSame(
                        0,
                        preg_match($p, $line),
                        "legacy media URL idiom remains in {$rel}:" . ($n + 1) . " → {$line}"
                    );
                }
            }
        }
    }
}
