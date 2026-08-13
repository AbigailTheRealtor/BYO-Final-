<?php

namespace Tests\Unit\Support;

use App\Support\Listing\ListingGalleryView;
use App\Support\Listing\ListingPhotoView;
use App\Support\Storage\ListingMediaUrl;
use Tests\TestCase;

/**
 * The display half of the widened `property_photos` shape.
 *
 * Two properties are load-bearing here and each has its own group of tests:
 *
 *   · a gallery of bare filenames — every listing that exists today — resolves
 *     to exactly the URLs it always did, so this change is invisible to them;
 *   · an MLS-sourced entry is emitted ONLY when both media flags and a supported
 *     hosting mode agree, and even then never as a path under our own storage.
 *
 * The media defaults are deliberately re-established in setUp() as OFF, so a
 * test that wants MLS media has to say so explicitly. A suite that inherited
 * "enabled" from somewhere else would be testing a configuration this product
 * does not ship.
 */
class ListingGalleryViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The shipped defaults, restated. See config/mls_media.php.
        config([
            'mls_media.enabled'             => false,
            'mls_media.license_acknowledged' => false,
            'mls_media.hosting_mode'        => 'reference',
            'mls_media.roles'               => ['seller', 'landlord'],
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

    private function mlsEntry(array $overrides = []): array
    {
        return array_merge([
            'source'      => 'mls',
            'media_key'   => 'MK-1',
            'url'         => 'https://media.example-mls.test/photo-1.jpg',
            'provider'    => 'bridge',
            'listing_key' => 'LK-1',
            'sequence'    => 0,
        ], $overrides);
    }

    // =====================================================================
    // 1 — the legacy shape is untouched
    // =====================================================================

    public function test_a_filename_only_gallery_resolves_exactly_as_it_always_did(): void
    {
        $gallery = ListingGalleryView::forRole(['a.jpg', 'b.jpg'], 'seller');

        $this->assertCount(2, $gallery->photos());

        // Asserted against the SAME call the old inline view code made, rather than
        // against a hand-written expected string: this is the claim that existing
        // listings render identically, so it must be tied to the real URL builder.
        $this->assertSame(ListingMediaUrl::get('auction/images/a.jpg'), $gallery->photos()[0]->url);
        $this->assertSame(ListingMediaUrl::get('auction/images/b.jpg'), $gallery->photos()[1]->url);

        $this->assertFalse($gallery->photos()[0]->isMls);
        $this->assertTrue($gallery->photos()[0]->isUser());
        $this->assertSame('a.jpg', $gallery->photos()[0]->key);
    }

    public function test_a_json_encoded_collection_is_decoded(): void
    {
        $gallery = ListingGalleryView::forRole(json_encode(['a.jpg']), 'seller');

        $this->assertCount(1, $gallery->photos());
        $this->assertSame(ListingMediaUrl::get('auction/images/a.jpg'), $gallery->photos()[0]->url);
    }

    public function test_a_user_upload_promoted_to_cover_still_resolves_locally(): void
    {
        $gallery = ListingGalleryView::forRole([
            ['filename' => 'a.jpg'],
            ['filename' => 'b.jpg', 'is_cover' => true],
        ], 'seller');

        $this->assertSame(ListingMediaUrl::get('auction/images/b.jpg'), $gallery->photos()[1]->url);
        $this->assertTrue($gallery->photos()[1]->isCover);
        $this->assertSame(1, $gallery->coverIndex());
    }

    public function test_nothing_at_all_yields_an_empty_gallery(): void
    {
        foreach ([null, '', [], 'not json', 0] as $stored) {
            $this->assertTrue(ListingGalleryView::forRole($stored, 'seller')->isEmpty());
        }

        // coverIndex must still be answerable on an empty gallery rather than
        // throwing — views guard on emptiness, they do not guard on this.
        $this->assertSame(0, ListingGalleryView::forRole(null, 'seller')->coverIndex());
    }

    // =====================================================================
    // 2 — an MLS entry resolves when, and only when, policy permits
    // =====================================================================

    public function test_an_mls_photo_resolves_to_the_provider_url_when_policy_permits(): void
    {
        $this->allowMedia();

        $gallery = ListingGalleryView::forRole([$this->mlsEntry()], 'seller');

        $this->assertCount(1, $gallery->photos());
        $this->assertSame('https://media.example-mls.test/photo-1.jpg', $gallery->photos()[0]->url);
        $this->assertTrue($gallery->photos()[0]->isMls);
        $this->assertSame('mls:MK-1', $gallery->photos()[0]->key);
    }

    // =====================================================================
    // 3 — an MLS entry is never given a path under our own storage
    // =====================================================================

    public function test_an_mls_photo_is_never_converted_to_an_auction_images_url(): void
    {
        $this->allowMedia();

        $gallery = ListingGalleryView::forRole([$this->mlsEntry()], 'landlord');

        $url = $gallery->photos()[0]->url;

        $this->assertStringNotContainsString('auction/images', $url);
        $this->assertStringNotContainsString(ListingGalleryView::UPLOAD_DIRECTORY, $url);
        $this->assertStringStartsWith('https://media.example-mls.test/', $url);
    }

    public function test_the_display_object_exposes_no_filename_for_an_mls_photo(): void
    {
        $this->allowMedia();

        $photo = ListingGalleryView::forRole([$this->mlsEntry()], 'seller')->photos()[0];

        // Structural, not incidental: a view that could reach a filename could
        // concatenate it onto a directory. There must be nothing to reach.
        $this->assertFalse(property_exists($photo, 'filename'));
        $this->assertInstanceOf(ListingPhotoView::class, $photo);
    }

    // =====================================================================
    // 4 — unusable entries fail closed
    // =====================================================================

    /**
     * @dataProvider unusableEntries
     */
    public function test_an_unusable_entry_is_skipped_rather_than_rendered(mixed $entry): void
    {
        $this->allowMedia();

        $gallery = ListingGalleryView::forRole([$entry, 'good.jpg'], 'seller');

        // The good neighbour survives — one bad entry must not empty a gallery.
        $this->assertCount(1, $gallery->photos());
        $this->assertSame(ListingMediaUrl::get('auction/images/good.jpg'), $gallery->photos()[0]->url);
    }

    public static function unusableEntries(): array
    {
        return [
            'mls entry with no media key' => [['source' => 'mls', 'url' => 'https://m.test/a.jpg']],
            'mls entry with no url'       => [['source' => 'mls', 'media_key' => 'MK-9']],
            'mls entry over plain http'   => [['source' => 'mls', 'media_key' => 'MK-9', 'url' => 'http://m.test/a.jpg']],
            'mls entry as a data uri'     => [['source' => 'mls', 'media_key' => 'MK-9', 'url' => 'data:image/png;base64,AAAA']],
            'mls entry as javascript'     => [['source' => 'mls', 'media_key' => 'MK-9', 'url' => 'javascript:alert(1)']],
            'mls entry with a relative url' => [['source' => 'mls', 'media_key' => 'MK-9', 'url' => '/auction/images/x.jpg']],
            'user entry with no filename' => [['source' => 'user', 'is_cover' => true]],
            'an empty string'             => [''],
            'a whitespace string'         => ['   '],
            'a bare integer'              => [42],
            'a nested array'              => [[['filename' => 'x.jpg']]],
            'a null'                      => [null],
        ];
    }

    // =====================================================================
    // 5 — a mixed gallery keeps both shapes, in order
    // =====================================================================

    public function test_a_mixed_gallery_preserves_both_shapes_and_their_order(): void
    {
        $this->allowMedia();

        $gallery = ListingGalleryView::forRole([
            $this->mlsEntry(['media_key' => 'MK-1', 'url' => 'https://m.test/1.jpg', 'sequence' => 0]),
            $this->mlsEntry(['media_key' => 'MK-2', 'url' => 'https://m.test/2.jpg', 'sequence' => 1]),
            'user-a.jpg',
            'user-b.jpg',
        ], 'seller');

        $this->assertSame(
            [
                'https://m.test/1.jpg',
                'https://m.test/2.jpg',
                ListingMediaUrl::get('auction/images/user-a.jpg'),
                ListingMediaUrl::get('auction/images/user-b.jpg'),
            ],
            $gallery->urls(),
            'stored order is gallery order, and it must not be reshuffled by source'
        );

        $this->assertSame([true, true, false, false], array_map(
            static fn (ListingPhotoView $p) => $p->isMls,
            $gallery->photos(),
        ));
    }

    public function test_a_cover_anywhere_in_a_mixed_gallery_is_found(): void
    {
        $this->allowMedia();

        $gallery = ListingGalleryView::forRole([
            $this->mlsEntry(),
            ['filename' => 'chosen.jpg', 'is_cover' => true, 'cover_chosen_by_owner' => true],
        ], 'seller');

        $this->assertSame(1, $gallery->coverIndex());
        $this->assertTrue($gallery->photos()[1]->isCover);
    }

    // =====================================================================
    // 8 — the media feature being OFF prevents every MLS photograph
    // =====================================================================

    public function test_no_mls_photo_is_emitted_at_the_shipped_defaults(): void
    {
        // setUp() left both flags false — this is the shipped configuration.
        $gallery = ListingGalleryView::forRole([$this->mlsEntry(), 'user.jpg'], 'seller');

        $this->assertCount(1, $gallery->photos());
        $this->assertFalse($gallery->photos()[0]->isMls);
        $this->assertSame(ListingMediaUrl::get('auction/images/user.jpg'), $gallery->photos()[0]->url);
    }

    public function test_the_master_flag_alone_is_not_enough(): void
    {
        config(['mls_media.enabled' => true]); // licence still unacknowledged

        $this->assertTrue(ListingGalleryView::forRole([$this->mlsEntry()], 'seller')->isEmpty());
    }

    public function test_the_licence_acknowledgement_alone_is_not_enough(): void
    {
        config(['mls_media.license_acknowledged' => true]); // master flag still off

        $this->assertTrue(ListingGalleryView::forRole([$this->mlsEntry()], 'seller')->isEmpty());
    }

    public function test_an_unimplemented_hosting_mode_emits_nothing(): void
    {
        $this->allowMedia();
        config(['mls_media.hosting_mode' => 'cached']);

        $this->assertTrue(ListingGalleryView::forRole([$this->mlsEntry()], 'seller')->isEmpty());
    }

    public function test_a_role_outside_the_media_role_list_emits_nothing(): void
    {
        $this->allowMedia();

        foreach (['buyer', 'tenant', 'agent', 'admin'] as $role) {
            $this->assertTrue(
                ListingGalleryView::forRole([$this->mlsEntry()], $role)->isEmpty(),
                "role {$role} must not receive MLS media",
            );
        }
    }

    public function test_an_unknown_role_fails_closed(): void
    {
        $this->allowMedia();

        // A surface that cannot say which role it is has not established that MLS
        // media is permitted there. "I don't know" must read as "no".
        $this->assertTrue(ListingGalleryView::forRole([$this->mlsEntry()], null)->isEmpty());
        $this->assertTrue(ListingGalleryView::forRole([$this->mlsEntry()], 'not-a-role')->isEmpty());
    }

    public function test_user_uploads_are_unaffected_by_every_media_flag_combination(): void
    {
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$enabled, $ack]) {
            config(['mls_media.enabled' => $enabled, 'mls_media.license_acknowledged' => $ack]);

            $gallery = ListingGalleryView::forRole(['a.jpg'], 'seller');

            $this->assertCount(1, $gallery->photos());
            $this->assertSame(ListingMediaUrl::get('auction/images/a.jpg'), $gallery->photos()[0]->url);
        }
    }

    // =====================================================================
    // 9 — no array-to-string conversion, anywhere on this path
    // =====================================================================

    public function test_resolving_a_structured_gallery_raises_no_array_to_string_warning(): void
    {
        $this->allowMedia();

        $raised = [];
        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        try {
            $gallery = ListingGalleryView::forRole([
                $this->mlsEntry(),
                ['filename' => 'a.jpg', 'is_cover' => true],
                'b.jpg',
                ['garbage' => true],
            ], 'seller');

            $gallery->urls();
            $gallery->coverIndex();
        } finally {
            restore_error_handler();
        }

        $arrayToString = array_filter($raised, static fn ($m) => str_contains($m, 'Array to string conversion'));

        $this->assertSame([], array_values($arrayToString), 'no array-to-string conversion may occur');
        $this->assertSame([], $raised, 'resolving a gallery must raise no PHP diagnostics at all');
    }

    public function test_the_literal_string_Array_can_never_become_a_url(): void
    {
        $this->allowMedia();

        $gallery = ListingGalleryView::forRole([
            ['source' => 'mls'],          // unusable
            ['filename' => null],         // unusable
            ['nested' => ['a' => 'b']],   // unusable
            'real.jpg',
        ], 'seller');

        foreach ($gallery->urls() as $url) {
            $this->assertStringNotContainsString('Array', $url);
        }

        $this->assertCount(1, $gallery->photos());
    }

    // =====================================================================
    // Role resolution
    // =====================================================================

    public function test_a_listing_model_resolves_to_its_role(): void
    {
        $this->assertSame('seller', ListingGalleryView::roleForAuction(new \App\Models\SellerAgentAuction()));
        $this->assertSame('landlord', ListingGalleryView::roleForAuction(new \App\Models\LandlordAgentAuction()));
        $this->assertSame('buyer', ListingGalleryView::roleForAuction(new \App\Models\BuyerAgentAuction()));
        $this->assertSame('tenant', ListingGalleryView::roleForAuction(new \App\Models\TenantAgentAuction()));
    }

    public function test_an_unrecognised_subject_resolves_to_no_role(): void
    {
        $this->assertNull(ListingGalleryView::roleForAuction(null));
        $this->assertNull(ListingGalleryView::roleForAuction('seller'));
        $this->assertNull(ListingGalleryView::roleForAuction(new \stdClass()));
    }
}
