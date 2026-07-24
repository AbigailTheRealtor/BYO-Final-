<?php

namespace Tests\Feature\Storage;

use App\Http\Controllers\AcceptedBidSummaryController;
use App\Models\Offer;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

/**
 * R2-E0 (HI-05A) — the accepted-bid summary's uploaded-photo URLs must be produced
 * through the public-media storage seam (ListingMediaUrl / ListingStorageReader::
 * publicUrl), NOT a hand-built config('app.url')."/storage/..." string, so a public
 * read-flip (LISTING_PUBLIC_READ=object_first) reaches this summary surface too.
 *
 * Drives the private buildOfferPropertySectionHtml() via reflection with an
 * in-memory metas collection + a non-persisted Offer (the method touches no DB, no
 * $this state, and uses $offer only for its id). Offline: the object secondary is a
 * local-driver disk with a distinct URL, mirroring PublicMediaUrlResolverTest.
 */
class AcceptedBidSummaryMediaUrlTest extends TestCase
{
    private const REL = 'offer-property-photos/42/photo1.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        // Defensive: any accidental object touch stays offline.
        Storage::fake('s3_public');
        Storage::fake('s3_private');
    }

    private function buildHtml(): string
    {
        $offer = new Offer();
        $offer->id = 42;

        $metas = collect(['prop_photos' => json_encode(['photo1.jpg'])]);

        // No constructor deps needed — the method uses no $this state.
        $ref = new ReflectionClass(AcceptedBidSummaryController::class);
        $controller = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('buildOfferPropertySectionHtml');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $metas, $offer);
    }

    /** DEFAULT local mode: the uploaded-photo URL is byte-equivalent to the prior URL. */
    public function test_uploaded_photo_url_uses_resolver_local_mode(): void
    {
        $html = $this->buildHtml();

        // Resolver local output == the prior config('app.url')."/storage/".$rel.
        $this->assertStringContainsString(asset('storage/' . self::REL), $html);
        $this->assertStringContainsString(Storage::disk('public')->url(self::REL), $html);
    }

    /** object_first: the summary reflects the object secondary URL (routing proof). */
    public function test_uploaded_photo_url_follows_object_first(): void
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

        $html = $this->buildHtml();

        $this->assertStringContainsString('https://cdn.example.test/o/' . self::REL, $html);
        $this->assertStringNotContainsString(asset('storage/' . self::REL), $html);
    }
}
