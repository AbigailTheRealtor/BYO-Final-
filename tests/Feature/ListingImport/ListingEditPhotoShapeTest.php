<?php

namespace Tests\Feature\ListingImport;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Seller and Landlord EDIT pages against both stored photo shapes.
 *
 * THE CRASH THIS CLOSES
 * ---------------------
 * The edit galleries echoed the stored value straight into an attribute:
 *
 *     <div data-filename="{{ $photo }}">
 *     <img src="{{ ListingMediaUrl::get('auction/images/' . $photo) }}">
 *
 * which assumed every entry is a bare filename string. A quick-imported listing
 * stores MLS photographs as ListingPhotoEntry ARRAYS carrying an absolute
 * provider URL, so opening one for editing raised
 *
 *     htmlspecialchars(): Argument #1 ($string) must be of type string, array given
 *
 * and the page never rendered. ListingViewPhotoShapeTest already covers the two
 * shapes on the PUBLISHED pages; the edit pages had no equivalent, which is why
 * this reached a browser.
 *
 * Both roles are asserted on identical fixtures for every case, so a future fix
 * to one that forgets the other fails here.
 */
class ListingEditPhotoShapeTest extends TestCase
{
    use DatabaseTransactions;

    private const PROVIDER_URL = 'https://dvvjkgh94f2v6.cloudfront.net/735d922b/730842080/83dcefb7.jpeg';
    private const LEGACY_FILE  = 'phpunit-legacy-photo-1.jpg';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_media.enabled'              => true,
            'mls_media.license_acknowledged' => true,
            'mls_media.hosting_mode'         => 'reference',
            'mls_media.roles'                => ['seller', 'landlord'],
        ]);

        $this->owner = User::factory()->create(['user_type' => 'seller']);
    }

    /** An MLS provider-reference entry, exactly as the quick-import writer stores it. */
    private function mlsEntry(int $sequence = 0): array
    {
        return [
            'source'      => 'mls',
            'media_key'   => 'PHPUNIT-MEDIA-' . $sequence,
            'url'         => self::PROVIDER_URL,
            'provider'    => 'bridge',
            'listing_key' => 'PHPUNIT-LISTING-KEY',
            'sequence'    => $sequence,
        ];
    }

    private function sellerListing(array $photos): int
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'Edit Photo Shape Seller',
            'is_approved' => false,
            'is_draft'    => true,
            'address'     => '100 Edit Test Blvd',
        ]);

        foreach (['workflow_type' => 'offer_listing', 'property_photos' => json_encode($photos)] as $k => $v) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => $k,
                'meta_value'              => $v,
            ]);
        }

        return $auction->id;
    }

    private function landlordListing(array $photos): int
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'Edit Photo Shape Landlord',
            'is_approved' => false,
            'is_draft'    => true,
        ]);

        foreach (['workflow_type' => 'offer_listing', 'property_photos' => json_encode($photos)] as $k => $v) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $k,
                'meta_value'                => $v,
            ]);
        }

        return $auction->id;
    }

    /**
     * Render an edit page, failing on any PHP diagnostic.
     *
     * The original defect was a TypeError, but the neighbouring failure mode —
     * "Array to string conversion" — is only a warning, and a page emitting it
     * still returns 200. Catching it has to be explicit or a half-fixed page
     * passes a status assertion.
     *
     * Scoped to the shape-mismatch diagnostics rather than "any diagnostic":
     * these pages already emit unrelated PHP 8.2 deprecations (dynamic property
     * $cities) that predate this work, and failing on those would make the test
     * a tripwire for something it is not about.
     */
    private function renderEdit(string $route, int $id): string
    {
        $raised = [];
        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            if (preg_match('/array to string|htmlspecialchars/i', $message) === 1) {
                $raised[] = $message;
            }

            return true;
        });

        try {
            $response = $this->actingAs($this->owner)->get(route($route, $id));
            $response->assertOk();
            $html = $response->getContent();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'PHP diagnostics raised while rendering ' . $route);

        return $html;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function roleProvider(): array
    {
        return [
            'seller'   => ['sellerListing', 'offer.listing.seller.edit'],
            'landlord' => ['landlordListing', 'offer.listing.landlord.edit'],
        ];
    }

    // ── MLS provider-reference photos ────────────────────────────────────────

    /**
     * The exact browser crash: edit a quick-imported listing.
     *
     * @dataProvider roleProvider
     */
    public function test_edit_opens_with_mls_provider_photos(string $factory, string $route): void
    {
        $id = $this->{$factory}([$this->mlsEntry(0), $this->mlsEntry(1)]);

        $html = $this->renderEdit($route, $id);

        // The provider URL is used as the image source, untouched.
        $this->assertStringContainsString(self::PROVIDER_URL, $html);

        // And no stringified array leaked into the markup. `Array` is what PHP
        // produces when an array is coerced for output, so the attribute that
        // used to receive the raw entry is the precise place to look — the bare
        // word appears legitimately elsewhere on the page (inline JS).
        $this->assertStringNotContainsString('data-filename="Array"', $html);
        $this->assertStringNotContainsString('auction/images/https://', $html);
    }

    /**
     * Reference-only: the provider URL must not be rewritten into a local path.
     *
     * @dataProvider roleProvider
     */
    public function test_mls_photos_stay_provider_hosted_on_the_edit_page(string $factory, string $route): void
    {
        $id = $this->{$factory}([$this->mlsEntry(0)]);

        $html = $this->renderEdit($route, $id);

        $this->assertStringContainsString('src="' . self::PROVIDER_URL . '"', $html);
        $this->assertStringNotContainsString('storage/auction/images/PHPUNIT-MEDIA', $html);
    }

    // ── Legacy / manual uploads ──────────────────────────────────────────────

    /**
     * The shape that already worked must keep working, byte-for-byte in intent:
     * a bare filename still resolves through the local upload directory.
     *
     * @dataProvider roleProvider
     */
    public function test_edit_opens_with_legacy_manual_photos(string $factory, string $route): void
    {
        $id = $this->{$factory}([self::LEGACY_FILE]);

        $html = $this->renderEdit($route, $id);

        $this->assertStringContainsString(self::LEGACY_FILE, $html);
        // Resolved as a local upload, not as an absolute provider URL.
        $this->assertStringNotContainsString(self::PROVIDER_URL, $html);
    }

    /**
     * Mixed collections are the realistic case once an owner adds their own
     * photograph to an imported listing.
     *
     * @dataProvider roleProvider
     */
    public function test_edit_opens_with_a_mixed_collection(string $factory, string $route): void
    {
        $id = $this->{$factory}([$this->mlsEntry(0), self::LEGACY_FILE, $this->mlsEntry(1)]);

        $html = $this->renderEdit($route, $id);

        $this->assertStringContainsString(self::PROVIDER_URL, $html);
        $this->assertStringContainsString(self::LEGACY_FILE, $html);
    }

    /**
     * An empty gallery is not a crash either — guards the placeholder branch.
     *
     * @dataProvider roleProvider
     */
    public function test_edit_opens_with_no_photos(string $factory, string $route): void
    {
        $id = $this->{$factory}([]);

        $this->renderEdit($route, $id);
    }
}
