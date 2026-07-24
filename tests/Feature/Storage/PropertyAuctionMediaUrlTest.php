<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;

/**
 * R2-E0 (HI-05A) — the property-auction search surface must build public
 * auction-image URLs through the storage seam (ListingMediaUrl::get), not the
 * legacy hand-built "storage/auction/images/..." path string, so a public
 * read-flip (LISTING_PUBLIC_READ=object_first) reaches this surface.
 *
 * The URL is built inline inside the large public searchListing() action (which
 * would need heavy DB + request fixtures to drive end-to-end), so this guards the
 * wiring at the source level — the same approach PublicMediaViewSmokeTest uses to
 * assert "no legacy URL idiom remains". ListingMediaUrl's local/object behavior is
 * covered behaviorally by tests/Unit/Storage/PublicMediaUrlResolverTest.
 */
class PropertyAuctionMediaUrlTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(
            app_path('Http/Controllers/PropertyAuctionController.php')
        );
    }

    public function test_auction_images_routed_through_resolver(): void
    {
        $this->assertStringContainsString(
            "ListingMediaUrl::get('auction/images/'",
            $this->source(),
            'Auction image URLs must be produced via ListingMediaUrl::get().'
        );
    }

    public function test_legacy_hand_built_auction_url_is_gone(): void
    {
        $this->assertStringNotContainsString(
            "'storage/auction/images/'",
            $this->source(),
            'The legacy hand-built storage/auction/images/ path must not remain.'
        );
    }
}
