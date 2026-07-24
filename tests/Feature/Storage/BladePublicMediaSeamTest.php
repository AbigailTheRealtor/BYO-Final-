<?php

namespace Tests\Feature\Storage;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * R2-E0b (HI-05A) — the Blade layer must build public-media URLs through the
 * storage seam (ListingMediaUrl::get), never asset('storage/…').
 *
 * WHY A REPO-WIDE SCAN, NOT AN ALLOW-LIST OF FILES:
 * R2-D.2 converted eight views and R2-E0 converted two controllers, but the
 * view set was tenant-and-agent-heavy — the seller/buyer/landlord twins of the
 * same templates were missed, and 68 raw call sites survived undetected because
 * the guard only ever looked at the files it already knew about. A guard scoped
 * to a known list cannot catch the file nobody added to the list. This test
 * therefore walks EVERY *.blade.php under resources/views and fails on any
 * occurrence outside a single, explicitly justified exception.
 *
 * Behavioral coverage of the resolver itself (local vs object-first, prefix
 * scope, public/private isolation) lives in
 * tests/Unit/Storage/PublicMediaUrlResolverTest.
 */
class BladePublicMediaSeamTest extends TestCase
{
    /**
     * The ONLY permitted asset('storage/…') site in the Blade layer.
     *
     * partials/listing-photos-tours-documents.blade.php links the listing
     * document. Since HI-05 that file is written to the PRIVATE disk, so the
     * correct fix is route('listing.document.show', …) via
     * ListingDocumentController — an authorization change owned by the document
     * track, not a URL-seam change. Deferred out of R2-E0b deliberately so this
     * PR stays byte-equivalent and revertible.
     *
     * Keyed by repo-relative path => the substring that identifies the line.
     */
    private const DEFERRED = [
        'resources/views/partials/listing-photos-tours-documents.blade.php'
            => "asset('storage/auction/documents/' . \$viewListingDocument)",
    ];

    /** The 25 views converted by R2-E0b, by group. */
    private const CONVERTED = [
        // 1 — hire-agent listing views
        'resources/views/hire_seller_agent/view.blade.php',
        'resources/views/hire_landlord_agent/view.blade.php',
        // 2 — agent bid presentation tabs
        'resources/views/livewire/buyer-agent-auction-bid-tabs/commission-based/agent-presentation.blade.php',
        'resources/views/livewire/seller-agent-auction-bid-tabs/commission-based/agent-presentation.blade.php',
        'resources/views/livewire/landlord-agent-auction-bid-tabs/commission-based/agent-presentation.blade.php',
        'resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/agent-presentation.blade.php',
        // 3 — bid detail partials & preview
        'resources/views/partials/bid_detail_body/landlord.blade.php',
        'resources/views/partials/bid_detail_body/seller.blade.php',
        'resources/views/partials/bid_detail_body/buyer.blade.php',
        'resources/views/tenant_agent/bid_preview.blade.php',
        // 4 — offer listing public views
        'resources/views/offer-listing/seller/view.blade.php',
        'resources/views/offer-listing/landlord/view.blade.php',
        'resources/views/offer-listing/buyer/view.blade.php',
        'resources/views/offer-listing/tenant/view.blade.php',
        // 5 — listing/client info tabs
        'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-info.blade.php',
        'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/buyer-info.blade.php',
        'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/landlord-info.blade.php',
        'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php',
        'resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/seller-info.blade.php',
        'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/buyer-info.blade.php',
        'resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/landlord-info.blade.php',
        'resources/views/livewire/tenant-agent-auction-tabs/commission-based/tenant-info.blade.php',
        // 6 — shared listing partial (photos only; document link deferred)
        'resources/views/partials/listing-photos-tours-documents.blade.php',
        // 7 — offer property photos
        'resources/views/offers/_property_being_offered_form.blade.php',
        'resources/views/offers/_property_being_offered_display.blade.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    /** @return array<int, string> repo-relative paths of every Blade view */
    private function allBladeViews(): array
    {
        $root = base_path('resources/views');
        $found = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = ltrim(str_replace(base_path(), '', $file->getPathname()), '/');
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Every legacy public-media URL idiom. Mirrors the three patterns
     * PublicMediaViewSmokeTest applies to the eight R2-D.2 views — applied here
     * to the whole view tree instead of a known list.
     */
    private const LEGACY_IDIOMS = [
        "/asset\(\s*['\"]storage\//",
        "/Storage::url\(/",
        "/disk\(\s*['\"]public['\"]\s*\)->url\(/",
    ];

    /**
     * THE GUARD. No legacy public-media URL idiom anywhere under
     * resources/views, except the single deferred document link.
     */
    public function test_no_raw_public_storage_url_remains_in_any_blade_view(): void
    {
        $offenders = [];

        foreach ($this->allBladeViews() as $rel) {
            $lines = file(base_path($rel), FILE_IGNORE_NEW_LINES);

            foreach ($lines as $n => $line) {
                // PHP comment lines mention the idioms in prose, not as call sites.
                if (preg_match('/^\s*(\/\/|\*|#)/', $line)) {
                    continue;
                }

                $matched = false;
                foreach (self::LEGACY_IDIOMS as $pattern) {
                    if (preg_match($pattern, $line)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    continue;
                }

                $allowed = self::DEFERRED[$rel] ?? null;
                if ($allowed !== null && str_contains($line, $allowed)) {
                    continue;
                }

                $offenders[] = $rel . ':' . ($n + 1) . ' → ' . trim($line);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Public media URLs must be built with ListingMediaUrl::get(), not asset('storage/…'), "
            . "so a public read-flip (LISTING_PUBLIC_READ=object_first) reaches every surface.\n"
            . implode("\n", $offenders)
        );
    }

    /** The deferred exception must remain exactly one line — no quiet growth. */
    public function test_deferred_exception_is_a_single_known_site(): void
    {
        $this->assertCount(1, self::DEFERRED, 'The deferred allow-list must not grow.');

        foreach (self::DEFERRED as $rel => $needle) {
            $src = (string) file_get_contents(base_path($rel));

            $this->assertSame(
                1,
                substr_count($src, $needle),
                "The deferred document link in {$rel} must appear exactly once."
            );
            $this->assertSame(
                1,
                preg_match_all("/asset\(\s*['\"]storage\//", $src),
                "{$rel} must contain no raw storage URL other than the deferred document link."
            );
        }
    }

    /** Every converted view still compiles and actually uses the resolver. */
    public function test_converted_views_compile_and_use_the_resolver(): void
    {
        foreach (self::CONVERTED as $rel) {
            $path = base_path($rel);
            $this->assertFileExists($path);

            $source = (string) file_get_contents($path);
            $this->assertStringContainsString(
                'ListingMediaUrl::get',
                $source,
                "resolver missing in {$rel}"
            );

            $this->assertNotEmpty(
                Blade::compileString($source),
                "Blade syntax broken in {$rel}"
            );
        }
    }

    /**
     * INERTNESS PROOF. At the default public_read='local' the resolver's output
     * is byte-identical to the asset('storage/…') string each converted site
     * previously emitted — for both path shapes.
     */
    public function test_local_mode_is_byte_equivalent_to_the_previous_asset_url(): void
    {
        $keys = [
            // shape A — bare filename under a literal dir
            'auction/images/2f1c9e40-0000-4000-8000-000000000001.jpg',
            'auction/videos/2f1c9e40-0000-4000-8000-000000000002.mp4',
            'offer-property-photos/91/2f1c9e40-0000-4000-8000-000000000003.png',
            // shape B — full relative key held in a meta-backed variable
            'auction/documents/2f1c9e40-0000-4000-8000-000000000004.png',
            'auction/promo-materials/2f1c9e40-0000-4000-8000-000000000005.pdf',
        ];

        foreach ($keys as $key) {
            $this->assertSame(
                asset('storage/' . $key),
                \App\Support\Storage\ListingMediaUrl::get($key),
                "Conversion must be byte-equivalent at public_read='local' for {$key}"
            );
        }
    }

    /** Both shapes route to the public secondary once object-first is selected. */
    public function test_object_first_reaches_both_path_shapes(): void
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

        // shape A, rendered the way the converted views render it
        $a = Blade::render(
            '<img src="{{ \App\Support\Storage\ListingMediaUrl::get(\'auction/images/\' . $fn) }}">',
            ['fn' => 'x.jpg']
        );
        $this->assertStringContainsString('https://cdn.example.test/o/auction/images/x.jpg', $a);

        // shape B
        $b = Blade::render(
            '<a href="{{ \App\Support\Storage\ListingMediaUrl::get($key) }}"></a>',
            ['key' => 'auction/documents/card.png']
        );
        $this->assertStringContainsString('https://cdn.example.test/o/auction/documents/card.png', $b);
    }

    /**
     * The seam must never surface a PRIVATE object through a public URL, even if
     * the public secondary selector is misconfigured to the private disk. Guards
     * the isolation property the converted business-card/promo sites now depend on.
     */
    public function test_public_url_never_resolves_to_the_private_secondary(): void
    {
        config([
            'filesystems.disks.obj_private' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/obj_private'),
                'url' => 'https://private.example.test/p',
            ],
            'listing_storage.private_secondary_disk' => 'obj_private',
            'listing_storage.public_secondary_disk' => 'obj_private',
            'listing_storage.public_read' => 'object_first',
        ]);

        $url = \App\Support\Storage\ListingMediaUrl::get('auction/images/x.jpg');

        $this->assertStringNotContainsString('private.example.test', $url);
        $this->assertSame(asset('storage/auction/images/x.jpg'), $url);
    }
}
