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
     * EMPTY, AND IT SHOULD STAY EMPTY.
     *
     * This list held exactly one entry: the listing-document link in
     * partials/listing-photos-tours-documents.blade.php. R2-E0b deferred it on
     * the grounds that replacing it is an authorization change rather than a
     * URL-seam change, and owned by the document track.
     *
     * M6 made that change. The link is now
     * route('listing.document.show', …) — delivered by ListingDocumentController,
     * which re-checks ListingDocumentAccessService::canViewDownload() on every
     * request and streams from the private disk — and the partial no longer
     * builds any storage URL for the document at all.
     *
     * The constant is kept rather than deleted, and
     * test_deferred_exception_is_a_single_known_site is now an emptiness
     * assertion, because the value of this list was never the entry: it was that
     * a NEW exception has to be written down here to pass. Deleting the
     * mechanism would remove that pressure along with the entry.
     *
     * Keyed by repo-relative path => the substring that identifies the line.
     */
    private const DEFERRED = [];

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

    /**
     * There is no deferred exception left, and adding one must be deliberate.
     *
     * M6 closed the single entry. This assertion is deliberately kept and inverted rather than
     * removed: an empty allow-list that is *asserted* empty is a standing guard, whereas a
     * deleted constant is an invitation to reintroduce a public media URL quietly.
     */
    public function test_no_deferred_exception_remains(): void
    {
        $this->assertSame(
            [],
            self::DEFERRED,
            'The deferred allow-list is closed. A new exception requires an explicit entry here '
            . 'and the review that goes with it.'
        );
    }

    /**
     * The listing document is reached through the authorized route, not a storage URL.
     *
     * Source-level, because it is the seam this suite owns. The behavioural half — who is offered
     * the control, and what the route does to an unauthorized request — lives in
     * ListingDocumentDeliveryTest.
     */
    public function test_the_listing_document_uses_the_authorized_route(): void
    {
        $rel = 'resources/views/partials/listing-photos-tours-documents.blade.php';
        $src = (string) file_get_contents(base_path($rel));

        $this->assertStringContainsString("route('listing.document.show'", $src);
        $this->assertStringNotContainsString('storage/auction/documents', $src);
        $this->assertSame(
            0,
            preg_match_all("/asset\(\s*['\"]storage\//", $src),
            "{$rel} must build no raw storage URL at all."
        );
    }

    /**
     * The two ways a view may satisfy this seam.
     *
     * `ListingMediaUrl::get` is the direct form and remains the default.
     *
     * `ListingGalleryView` is delegation, and it is accepted for a specific
     * reason rather than as a loosening. When `property_photos` widened to hold
     * MLS-sourced entries alongside upload filenames, a view could no longer
     * decide a photo's URL from its own inline idiom — an MLS photograph is
     * referenced at the provider and must NEVER be given a path under our
     * storage, while an upload must still resolve through exactly the seam this
     * suite guards. Leaving that choice in each view meant five copies of one
     * rule, which is how the published pages came to silently drop imported
     * photographs. ListingGalleryView makes the choice once and calls
     * ListingMediaUrl::get itself for every local file.
     *
     * So a view naming it is not bypassing the resolver; it is reaching the
     * resolver through the only component that also knows when NOT to. The
     * prohibition itself is unaffected and is enforced repo-wide, on every Blade
     * file including these, by
     * {@see test_no_raw_public_storage_url_remains_in_any_blade_view()}.
     */
    private const APPROVED_SEAMS = [
        'ListingMediaUrl::get',
        'ListingGalleryView',
    ];

    /** Every converted view still compiles and actually uses the resolver. */
    public function test_converted_views_compile_and_use_the_resolver(): void
    {
        foreach (self::CONVERTED as $rel) {
            $path = base_path($rel);
            $this->assertFileExists($path);

            $source = (string) file_get_contents($path);

            $usesApprovedSeam = false;
            foreach (self::APPROVED_SEAMS as $seam) {
                if (str_contains($source, $seam)) {
                    $usesApprovedSeam = true;
                    break;
                }
            }

            $this->assertTrue(
                $usesApprovedSeam,
                "resolver missing in {$rel}: expected ListingMediaUrl::get, or delegation to "
                . 'ListingGalleryView which calls it.'
            );

            $this->assertNotEmpty(
                Blade::compileString($source),
                "Blade syntax broken in {$rel}"
            );
        }
    }

    /**
     * Delegation is only acceptable while the thing delegated to still uses the seam.
     *
     * Without this, widening the assertion above would be a hole: a view could
     * name ListingGalleryView, ListingGalleryView could later stop calling the
     * resolver, and both halves would pass while the seam was gone.
     */
    public function test_the_delegated_resolver_still_uses_the_seam(): void
    {
        $rel = 'app/Support/Listing/ListingGalleryView.php';
        $src = (string) file_get_contents(base_path($rel));

        $this->assertStringContainsString(
            'ListingMediaUrl::get',
            $src,
            "{$rel} is an approved seam for Blade views and must build local URLs through the resolver."
        );

        $this->assertSame(
            0,
            preg_match_all("/asset\(\s*['\"]storage\//", $src),
            "{$rel} must build no raw storage URL."
        );
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
