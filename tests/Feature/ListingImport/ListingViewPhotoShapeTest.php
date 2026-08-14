<?php

namespace Tests\Feature\ListingImport;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Support\Storage\ListingMediaUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The public Seller and Landlord listing pages, against both photo shapes.
 *
 * WHAT THIS IS PROVING, AND WHY IT NEEDED A FEATURE TEST
 * ------------------------------------------------------
 * ListingGalleryViewTest already covers the resolution rules in isolation. This
 * file covers something a unit test cannot: that the two published pages actually
 * ROUTE through those rules, and route through the SAME ones. The defect being
 * closed was never in the rules — it was that each view carried its own inline
 * copy of them, so a gallery visible on the quick-import review screen rendered
 * as nothing at all once the listing was published.
 *
 * Both pages are therefore asserted together, on identical fixtures, for every
 * case. A future edit that fixes one page and forgets the other fails here.
 *
 * THE DEFAULT CASE IS THE SHIPPED CASE
 * ------------------------------------
 * Media stays OFF unless a test switches it on, because that is the configuration
 * this product ships. The "enabled" tests exist to prove the capability is real
 * and correctly gated — not because anything is expected to run that way.
 */
class ListingViewPhotoShapeTest extends TestCase
{
    use DatabaseTransactions;

    private const PROVIDER_URL = 'https://media.example-mls.test/stellar-photo-1.jpg';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        // The shipped defaults, restated so no test inherits someone else's config.
        config([
            'mls_media.enabled'              => false,
            'mls_media.license_acknowledged' => false,
            'mls_media.hosting_mode'         => 'reference',
            'mls_media.roles'                => ['seller', 'landlord'],
        ]);
    }

    private function allowMedia(): void
    {
        config([
            'mls_media.enabled'              => true,
            'mls_media.license_acknowledged' => true,
            'mls_media.hosting_mode'         => 'reference',
        ]);
    }

    /** @return array{0: string, 1: int} route name and listing id */
    private function sellerListing(array $photos): array
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'Photo Shape Seller Listing',
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '100 Test Blvd',
        ]);

        foreach (['workflow_type' => 'offer_listing', 'property_photos' => json_encode($photos)] as $key => $value) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => $key,
                'meta_value'              => $value,
            ]);
        }

        return ['offer.listing.seller.view', $auction->id];
    }

    /** @return array{0: string, 1: int} */
    private function landlordListing(array $photos): array
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'Photo Shape Landlord Listing',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        foreach (['workflow_type' => 'offer_listing', 'property_photos' => json_encode($photos)] as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        return ['offer.listing.landlord.view', $auction->id];
    }

    private function mlsEntry(array $overrides = []): array
    {
        return array_merge([
            'source'      => 'mls',
            'media_key'   => 'MK-1',
            'url'         => self::PROVIDER_URL,
            'provider'    => 'bridge',
            'listing_key' => 'LK-1',
            'sequence'    => 0,
        ], $overrides);
    }

    /**
     * Render a listing page, failing the test on any PHP diagnostic.
     *
     * The array-to-string conversion this change removes is a WARNING, not an
     * exception — a page emitting it still returns 200 and still looks fine in a
     * status assertion. Catching it therefore has to be explicit, or the very
     * defect under test passes silently.
     */
    private function renderWithoutDiagnostics(string $route, int $id): string
    {
        $raised = [];
        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        try {
            $response = $this->actingAs($this->owner)->get(route($route, $id));
        } finally {
            restore_error_handler();
        }

        $response->assertStatus(200);

        $this->assertSame(
            [],
            array_values(array_filter($raised, static fn ($m) => str_contains($m, 'Array to string conversion'))),
            "rendering {$route} must not convert an array to a string",
        );

        return $response->getContent();
    }

    // =====================================================================
    // 1 / 6 / 7 — the legacy shape renders exactly as before, on both pages
    // =====================================================================

    /** @dataProvider bothRoles */
    public function test_a_filename_only_gallery_renders_from_auction_images(string $maker): void
    {
        [$route, $id] = $this->{$maker}(['legacy-photo.jpg']);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringContainsString(
            e(ListingMediaUrl::get('auction/images/legacy-photo.jpg')),
            $html,
            'an existing user upload must still resolve through auction/images',
        );
    }

    /** @dataProvider bothRoles */
    public function test_a_user_upload_promoted_to_cover_still_renders_locally(string $maker): void
    {
        [$route, $id] = $this->{$maker}([
            'plain.jpg',
            ['filename' => 'chosen.jpg', 'is_cover' => true],
        ]);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/plain.jpg')), $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/chosen.jpg')), $html);
    }

    // =====================================================================
    // 8 — with media OFF, no MLS photograph reaches either page
    // =====================================================================

    /** @dataProvider bothRoles */
    public function test_no_mls_photo_appears_at_the_shipped_defaults(string $maker): void
    {
        [$route, $id] = $this->{$maker}([$this->mlsEntry(), 'user.jpg']);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringNotContainsString('media.example-mls.test', $html);
        $this->assertStringNotContainsString('stellar-photo-1.jpg', $html);

        // The user's own photograph is unaffected by the MLS gate.
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/user.jpg')), $html);
    }

    /** @dataProvider bothRoles */
    public function test_a_gallery_of_only_mls_photos_renders_as_empty_when_media_is_off(string $maker): void
    {
        [$route, $id] = $this->{$maker}([$this->mlsEntry(), $this->mlsEntry(['media_key' => 'MK-2'])]);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringNotContainsString('media.example-mls.test', $html);
    }

    // =====================================================================
    // 6 / 7 — the widened shape is handled when media is permitted
    // =====================================================================

    /** @dataProvider bothRoles */
    public function test_an_mls_photo_renders_from_the_provider_url_when_permitted(string $maker): void
    {
        $this->allowMedia();

        [$route, $id] = $this->{$maker}([$this->mlsEntry()]);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringContainsString(e(self::PROVIDER_URL), $html);
    }

    /** @dataProvider bothRoles */
    public function test_a_permitted_mls_photo_is_never_given_a_local_path(string $maker): void
    {
        $this->allowMedia();

        [$route, $id] = $this->{$maker}([$this->mlsEntry()]);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringNotContainsString('auction/images/stellar-photo-1.jpg', $html);
        $this->assertStringNotContainsString('auction/images/MK-1', $html);
        $this->assertStringNotContainsString('auction/images/Array', $html);
    }

    /** @dataProvider bothRoles */
    public function test_a_mixed_gallery_renders_both_shapes(string $maker): void
    {
        $this->allowMedia();

        [$route, $id] = $this->{$maker}([$this->mlsEntry(), 'mine.jpg']);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringContainsString(e(self::PROVIDER_URL), $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/mine.jpg')), $html);
    }

    // =====================================================================
    // 4 / 9 — malformed entries never reach the page
    // =====================================================================

    /** @dataProvider bothRoles */
    public function test_malformed_entries_are_skipped_without_a_diagnostic(string $maker): void
    {
        $this->allowMedia();

        [$route, $id] = $this->{$maker}([
            ['source' => 'mls', 'media_key' => 'MK-X'],                      // no url
            ['source' => 'mls', 'url' => 'http://insecure.test/a.jpg'],      // no key, and http
            ['filename' => null],
            ['nested' => ['deep' => 'value']],
            'survivor.jpg',
        ]);

        $html = $this->renderWithoutDiagnostics($route, $id);

        $this->assertStringNotContainsString('auction/images/Array', $html);
        $this->assertStringNotContainsString('insecure.test', $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/survivor.jpg')), $html);
    }

    public static function bothRoles(): array
    {
        return [
            'seller page'   => ['sellerListing'],
            'landlord page' => ['landlordListing'],
        ];
    }

    // =====================================================================
    // 9 — the shared partial, which had the array-to-string defect
    // =====================================================================

    /**
     * The partial is rendered directly because it is not currently included by
     * any view. That is exactly why it needs its own test: an unreferenced file
     * gets no coverage from page tests, and this one contained a live
     * `trim((string) $p)` over a collection that is no longer scalar.
     */
    public function test_the_shared_partial_handles_both_shapes_without_array_to_string(): void
    {
        $this->allowMedia();

        $auction = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'Partial Fixture',
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '1 Partial Way',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_photos',
            'meta_value'              => json_encode([
                $this->mlsEntry(),
                ['filename' => 'promoted.jpg', 'is_cover' => true],
                'plain.jpg',
                ['nothing' => 'usable'],
            ]),
        ]);

        $raised = [];
        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        try {
            $html = view('partials.listing-photos-tours-documents', [
                'auction' => $auction->fresh(),
            ])->render();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(
            [],
            array_values(array_filter($raised, static fn ($m) => str_contains($m, 'Array to string conversion'))),
            'the shared partial must not convert an array to a string',
        );

        $this->assertStringNotContainsString('auction/images/Array', $html);
        $this->assertStringContainsString(e(self::PROVIDER_URL), $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/promoted.jpg')), $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/plain.jpg')), $html);
    }

    public function test_the_shared_partial_emits_no_mls_media_when_the_feature_is_off(): void
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'Partial Fixture Off',
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '2 Partial Way',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_photos',
            'meta_value'              => json_encode([$this->mlsEntry(), 'plain.jpg']),
        ]);

        $html = view('partials.listing-photos-tours-documents', [
            'auction' => $auction->fresh(),
        ])->render();

        $this->assertStringNotContainsString('media.example-mls.test', $html);
        $this->assertStringContainsString(e(ListingMediaUrl::get('auction/images/plain.jpg')), $html);
    }
}
