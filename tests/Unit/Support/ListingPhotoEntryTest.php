<?php

namespace Tests\Unit\Support;

use App\Support\Listing\ListingPhotoEntry;
use Tests\TestCase;

/**
 * ListingPhotoEntry — the one place the photo-collection shape widens.
 *
 * The round-trip tests are the important ones. `property_photos` has always been
 * an array of bare filename strings, and three security mechanisms depend on
 * that shape. This class is what lets MLS media share the collection without
 * changing what a user upload looks like on disk — so a listing that never
 * touches MLS import must round-trip byte-identically, and that is asserted
 * rather than assumed.
 */
class ListingPhotoEntryTest extends TestCase
{
    // ─── User uploads: unchanged in every respect ────────────────────────────

    /** @test */
    public function a_bare_filename_round_trips_as_a_bare_string(): void
    {
        $entry = ListingPhotoEntry::fromStored('abc-123.jpg');

        $this->assertNotNull($entry);
        $this->assertTrue($entry->isUser());
        $this->assertSame('abc-123.jpg', $entry->filename);
        $this->assertSame('abc-123.jpg', $entry->toStorage());
        $this->assertIsString($entry->toStorage());
    }

    /**
     * @test
     *
     * The no-migration guarantee: an existing collection comes back out exactly
     * as it went in.
     */
    public function an_all_string_collection_round_trips_identically(): void
    {
        $stored = ['a.jpg', 'b.jpg', 'c.jpg'];

        $this->assertSame(
            $stored,
            ListingPhotoEntry::toStorageCollection(ListingPhotoEntry::collection($stored)),
        );
    }

    /** @test */
    public function a_user_entrys_key_is_its_filename_so_existing_callers_are_unaffected(): void
    {
        $entry = ListingPhotoEntry::fromStored('abc-123.jpg');

        $this->assertSame('abc-123.jpg', $entry->key());
        $this->assertTrue($entry->matchesSelector('abc-123.jpg'));
    }

    /** @test */
    public function a_user_entry_has_a_local_file(): void
    {
        $this->assertTrue(ListingPhotoEntry::fromStored('abc.jpg')->hasLocalFile());
    }

    // ─── MLS entries ─────────────────────────────────────────────────────────

    private function mlsEntry(array $overrides = []): ListingPhotoEntry
    {
        return ListingPhotoEntry::fromStored(array_merge([
            'source'      => 'mls',
            'provider'    => 'bridge',
            'media_key'   => 'k1',
            'url'         => 'https://cdn.example.com/k1.jpg',
            'listing_key' => 'LK-1',
            'sequence'    => 2,
        ], $overrides));
    }

    /** @test */
    public function an_mls_entry_is_namespaced_and_cannot_collide_with_a_filename(): void
    {
        $entry = $this->mlsEntry();

        $this->assertTrue($entry->isMls());
        $this->assertSame('mls:k1', $entry->key());
        $this->assertFalse($entry->matchesSelector('k1'), 'A bare key resolved to an MLS entry');
        $this->assertFalse($entry->matchesSelector('k1.jpg'));
        $this->assertTrue($entry->matchesSelector('mls:k1'));
    }

    /**
     * @test
     *
     * Reference-only hosting: there is no file of ours behind an MLS entry, so
     * nothing may ever reach for a filesystem path on its behalf.
     */
    public function an_mls_entry_never_claims_a_local_file(): void
    {
        $entry = $this->mlsEntry();

        $this->assertFalse($entry->hasLocalFile());
        $this->assertNull($entry->filename);
    }

    /** @test */
    public function an_mls_entry_without_a_media_key_or_url_is_unusable(): void
    {
        $this->assertNull(ListingPhotoEntry::fromStored(['source' => 'mls', 'url' => 'https://cdn/x.jpg']));
        $this->assertNull(ListingPhotoEntry::fromStored(['source' => 'mls', 'media_key' => 'k']));
    }

    /** @test */
    public function an_mls_entry_round_trips_with_its_provenance_intact(): void
    {
        $stored = $this->mlsEntry()->toStorage();

        $this->assertIsArray($stored);
        $this->assertSame('mls', $stored['source']);
        $this->assertSame('bridge', $stored['provider']);
        $this->assertSame('k1', $stored['media_key']);
        $this->assertSame('LK-1', $stored['listing_key']);
    }

    // ─── Mixed collections ───────────────────────────────────────────────────

    /** @test */
    public function a_mixed_collection_keeps_both_shapes(): void
    {
        $stored = [
            'user-one.jpg',
            ['source' => 'mls', 'media_key' => 'k1', 'url' => 'https://cdn.example.com/k1.jpg'],
            'user-two.jpg',
        ];

        $entries = ListingPhotoEntry::collection($stored);

        $this->assertCount(3, $entries);
        $this->assertSame(['user-one.jpg', 'mls:k1', 'user-two.jpg'], array_map(fn ($e) => $e->key(), $entries));

        $out = ListingPhotoEntry::toStorageCollection($entries);
        $this->assertSame('user-one.jpg', $out[0]);
        $this->assertIsArray($out[1]);
        $this->assertSame('user-two.jpg', $out[2]);
    }

    /** @test */
    public function a_json_encoded_collection_is_decoded(): void
    {
        $entries = ListingPhotoEntry::collection(json_encode(['a.jpg', 'b.jpg']));

        $this->assertSame(['a.jpg', 'b.jpg'], array_map(fn ($e) => $e->key(), $entries));
    }

    /** @test */
    public function unusable_entries_are_dropped_rather_than_placeholdered(): void
    {
        $entries = ListingPhotoEntry::collection([
            'good.jpg',
            '',
            null,
            42,
            ['no' => 'filename'],
            ['source' => 'mls'],
        ]);

        $this->assertCount(1, $entries);
        $this->assertSame('good.jpg', $entries[0]->key());
    }

    /** @test */
    public function nothing_at_all_yields_an_empty_collection(): void
    {
        $this->assertSame([], ListingPhotoEntry::collection(null));
        $this->assertSame([], ListingPhotoEntry::collection(''));
        $this->assertSame([], ListingPhotoEntry::collection('not json'));
        $this->assertSame([], ListingPhotoEntry::collection([]));
    }

    // ─── Cover flags ─────────────────────────────────────────────────────────

    /** @test */
    public function a_user_entry_promoted_to_cover_becomes_an_array_and_collapses_back(): void
    {
        $entry = ListingPhotoEntry::fromStored('a.jpg');

        $covered = $entry->withCover(true, byOwner: true);
        $this->assertIsArray($covered->toStorage());
        $this->assertTrue($covered->isCover);
        $this->assertTrue($covered->coverChosenByOwner);
        $this->assertSame('a.jpg', $covered->filename);
        $this->assertSame('a.jpg', $covered->key(), 'Promotion must not change the entry\'s identity');

        // Reversible: clearing the flag returns it to a bare string.
        $this->assertSame('a.jpg', $covered->withCover(false)->toStorage());
    }

    /** @test */
    public function cover_provenance_is_recorded_separately_from_the_flag(): void
    {
        $derived = $this->mlsEntry()->withCover(true);
        $chosen  = $this->mlsEntry()->withCover(true, byOwner: true);

        $this->assertTrue($derived->isCover);
        $this->assertFalse($derived->coverChosenByOwner);

        $this->assertTrue($chosen->isCover);
        $this->assertTrue($chosen->coverChosenByOwner);
    }

    /** @test */
    public function clearing_a_cover_removes_the_provenance_too(): void
    {
        $cleared = $this->mlsEntry()->withCover(true, byOwner: true)->withCover(false);

        $this->assertFalse($cleared->isCover);
        $this->assertFalse($cleared->coverChosenByOwner);
        $this->assertArrayNotHasKey('cover_chosen_by_owner', (array) $cleared->toStorage());
    }

    /** @test */
    public function ownership_cannot_be_claimed_without_the_cover_flag(): void
    {
        $entry = ListingPhotoEntry::fromStored([
            'source'                => 'mls',
            'media_key'             => 'k',
            'url'                   => 'https://cdn.example.com/k.jpg',
            'cover_chosen_by_owner' => true,
        ]);

        $this->assertFalse($entry->isCover);
        $this->assertFalse($entry->coverChosenByOwner, 'Owner provenance survived without a cover flag');
    }

    // ─── Selector hygiene ────────────────────────────────────────────────────

    /** @test */
    public function a_non_string_or_empty_selector_matches_nothing(): void
    {
        $user = ListingPhotoEntry::fromStored('a.jpg');
        $mls  = $this->mlsEntry();

        foreach ([null, '', 0, [], true] as $selector) {
            $this->assertFalse($user->matchesSelector($selector));
            $this->assertFalse($mls->matchesSelector($selector));
        }
    }

    /** @test */
    public function a_traversal_string_matches_nothing_it_should_not(): void
    {
        $this->assertFalse(ListingPhotoEntry::fromStored('a.jpg')->matchesSelector('../a.jpg'));
        $this->assertFalse(ListingPhotoEntry::fromStored('a.jpg')->matchesSelector('auction/images/a.jpg'));
    }
}
