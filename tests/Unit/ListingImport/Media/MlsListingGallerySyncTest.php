<?php

namespace Tests\Unit\ListingImport\Media;

use App\Services\ListingImport\Media\MlsListingGallerySync;
use App\Services\ListingImport\Media\MlsMediaItem;
use App\Support\Listing\ListingPhotoEntry;
use Tests\TestCase;

/**
 * MlsListingGallerySync — the refresh contract.
 *
 * Every "what happens when the MLS changes?" question from the brief has an
 * assertion here, because the dangerous answers are all silent ones: a gallery
 * that duplicates on every refresh, a seller's own photographs deleted by a
 * background sync, or a live listing emptied by one bad API response.
 */
class MlsListingGallerySyncTest extends TestCase
{
    private function sync(): MlsListingGallerySync
    {
        return new MlsListingGallerySync();
    }

    private function item(string $key, int $sequence = 0, array $overrides = []): MlsMediaItem
    {
        return new MlsMediaItem(
            mediaKey:              $key,
            listingKey:            $overrides['listingKey'] ?? 'LK-1',
            url:                   $overrides['url'] ?? "https://cdn.example.com/{$key}.jpg",
            sequence:              $sequence,
            isPreferred:           $overrides['isPreferred'] ?? false,
            category:              'Photo',
            caption:               $overrides['caption'] ?? null,
            modificationTimestamp: $overrides['modifiedAt'] ?? null,
        );
    }

    /** @param list<ListingPhotoEntry> $entries */
    private function keys(array $entries): array
    {
        return array_map(fn (ListingPhotoEntry $e) => $e->key(), $entries);
    }

    // ─── First import ────────────────────────────────────────────────────────

    /** @test */
    public function a_first_import_builds_the_gallery_in_feed_order(): void
    {
        $result = $this->sync()->sync(null, [
            $this->item('a', 0),
            $this->item('b', 1),
            $this->item('c', 2),
        ]);

        $this->assertSame(['mls:a', 'mls:b', 'mls:c'], $this->keys($result->entries));
        $this->assertSame(3, $result->added);
        $this->assertSame(0, $result->removed);
        $this->assertSame(3, $result->totalMlsPhotos());
    }

    /** @test */
    public function the_first_photo_becomes_the_cover_when_the_feed_names_no_preference(): void
    {
        $result = $this->sync()->sync(null, [$this->item('a', 0), $this->item('b', 1)]);

        $this->assertSame('mls:a', $result->coverKey);
        $this->assertTrue($result->entries[0]->isCover);
        $this->assertFalse($result->entries[1]->isCover);
    }

    /** @test */
    public function an_explicit_preferred_photo_outranks_feed_position(): void
    {
        $result = $this->sync()->sync(null, [
            $this->item('a', 0),
            $this->item('b', 1, ['isPreferred' => true]),
            $this->item('c', 2),
        ]);

        $this->assertSame('mls:b', $result->coverKey);
        // Ordering is untouched by the cover choice — the preferred photo is not
        // dragged to the front, it is merely marked.
        $this->assertSame(['mls:a', 'mls:b', 'mls:c'], $this->keys($result->entries));
    }

    /** @test */
    public function exactly_one_entry_ever_carries_the_cover_flag(): void
    {
        $result = $this->sync()->sync(null, [
            $this->item('a', 0, ['isPreferred' => true]),
            $this->item('b', 1, ['isPreferred' => true]),
        ]);

        $covers = array_filter($result->entries, fn (ListingPhotoEntry $e) => $e->isCover);
        $this->assertCount(1, $covers);
    }

    // ─── Idempotence ─────────────────────────────────────────────────────────

    /** @test */
    public function re_importing_the_same_media_does_not_duplicate_the_gallery(): void
    {
        $items = [$this->item('a', 0), $this->item('b', 1)];

        $first  = $this->sync()->sync(null, $items);
        $second = $this->sync()->sync(ListingPhotoEntry::toStorageCollection($first->entries), $items);

        $this->assertSame(['mls:a', 'mls:b'], $this->keys($second->entries));
        $this->assertSame(0, $second->added);
        $this->assertSame(0, $second->updated);
        $this->assertSame(2, $second->unchanged);
        $this->assertFalse($second->changedAnything());
    }

    /** @test */
    public function a_third_import_is_still_stable(): void
    {
        $items = [$this->item('a', 0), $this->item('b', 1)];

        $stored = null;
        for ($i = 0; $i < 3; $i++) {
            $result = $this->sync()->sync($stored, $items);
            $stored = ListingPhotoEntry::toStorageCollection($result->entries);
        }

        $this->assertCount(2, $stored);
    }

    // ─── Feed changes ────────────────────────────────────────────────────────

    /** @test */
    public function a_newly_added_mls_photo_is_picked_up(): void
    {
        $first = $this->sync()->sync(null, [$this->item('a', 0)]);

        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('a', 0), $this->item('b', 1)],
        );

        $this->assertSame(['mls:a', 'mls:b'], $this->keys($second->entries));
        $this->assertSame(1, $second->added);
    }

    /** @test */
    public function a_photo_the_feed_withdrew_stops_being_published(): void
    {
        $first = $this->sync()->sync(null, [$this->item('a', 0), $this->item('b', 1)]);

        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('a', 0)],
        );

        $this->assertSame(['mls:a'], $this->keys($second->entries));
        $this->assertSame(1, $second->removed);
    }

    /** @test */
    public function a_replaced_image_is_updated_in_place_rather_than_duplicated(): void
    {
        $first = $this->sync()->sync(null, [
            $this->item('a', 0, ['url' => 'https://cdn.example.com/old.jpg', 'modifiedAt' => '2026-01-01T00:00:00Z']),
        ]);

        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('a', 0, ['url' => 'https://cdn.example.com/new.jpg', 'modifiedAt' => '2026-06-01T00:00:00Z'])],
        );

        $this->assertCount(1, $second->entries);
        $this->assertSame('https://cdn.example.com/new.jpg', $second->entries[0]->url);
        $this->assertSame(1, $second->updated);
        $this->assertSame(0, $second->added);
    }

    /**
     * @test
     *
     * A URL rotation with no change of stamp still has to be stored, or the
     * gallery keeps rendering a reference that no longer resolves.
     */
    public function a_rotated_url_is_detected_even_without_a_modification_stamp(): void
    {
        $first = $this->sync()->sync(null, [$this->item('a', 0, ['url' => 'https://cdn.example.com/v1.jpg'])]);

        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('a', 0, ['url' => 'https://cdn.example.com/v2.jpg'])],
        );

        $this->assertSame('https://cdn.example.com/v2.jpg', $second->entries[0]->url);
        $this->assertSame(1, $second->updated);
    }

    /** @test */
    public function a_reordered_feed_reorders_a_gallery_the_owner_has_not_arranged(): void
    {
        $first = $this->sync()->sync(null, [$this->item('a', 0), $this->item('b', 1)]);

        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('b', 0), $this->item('a', 1)],
        );

        $this->assertSame(['mls:b', 'mls:a'], $this->keys($second->entries));
    }

    /** @test */
    public function a_changed_primary_photo_moves_the_cover(): void
    {
        $first = $this->sync()->sync(null, [
            $this->item('a', 0, ['isPreferred' => true]),
            $this->item('b', 1),
        ]);

        $this->assertSame('mls:a', $first->coverKey);

        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('a', 0), $this->item('b', 1, ['isPreferred' => true])],
        );

        // The owner never chose a cover here, so the feed's new preference wins.
        $this->assertSame('mls:b', $second->coverKey);
    }

    // ─── The empty-set asymmetry ─────────────────────────────────────────────

    /**
     * @test
     *
     * The single most destructive plausible bug in this class: one failed or
     * empty response emptying a live listing's gallery.
     */
    public function an_empty_incoming_set_removes_nothing(): void
    {
        $first = $this->sync()->sync(null, [$this->item('a', 0), $this->item('b', 1)]);

        $second = $this->sync()->sync(ListingPhotoEntry::toStorageCollection($first->entries), []);

        $this->assertSame(['mls:a', 'mls:b'], $this->keys($second->entries));
        $this->assertSame(0, $second->removed);
    }

    /** @test */
    public function detaching_is_explicit_and_keeps_user_uploads(): void
    {
        $stored = ['user-one.jpg', ['source' => 'mls', 'media_key' => 'a', 'url' => 'https://cdn.example.com/a.jpg'], 'user-two.jpg'];

        $result = $this->sync()->detachAll($stored);

        $this->assertSame(['user-one.jpg', 'user-two.jpg'], $this->keys($result->entries));
        $this->assertSame(1, $result->removed);
        $this->assertSame(2, $result->userPhotosPreserved);
    }

    /** @test */
    public function detaching_re_derives_a_cover_when_the_old_one_was_mls(): void
    {
        $stored = [
            ['source' => 'mls', 'media_key' => 'a', 'url' => 'https://cdn.example.com/a.jpg', 'is_cover' => true],
            'user-one.jpg',
        ];

        $result = $this->sync()->detachAll($stored);

        $this->assertSame('user-one.jpg', $result->coverKey);
        $this->assertTrue($result->entries[0]->isCover);
    }

    // ─── User uploads ────────────────────────────────────────────────────────

    /**
     * @test
     *
     * The brief's hardest requirement: a refresh must never destroy the
     * photographs the user took themselves.
     */
    public function a_refresh_never_deletes_user_uploaded_photos(): void
    {
        $stored = ['my-own-photo.jpg', ['source' => 'mls', 'media_key' => 'a', 'url' => 'https://cdn.example.com/a.jpg']];

        // A refresh that withdraws every MLS photo it previously had.
        $result = $this->sync()->sync($stored, [$this->item('z', 0)]);

        $this->assertContains('my-own-photo.jpg', $this->keys($result->entries));
        $this->assertSame(1, $result->userPhotosPreserved);
        $this->assertSame(1, $result->removed);
    }

    /** @test */
    public function user_uploads_sit_after_mls_photos_on_an_unarranged_gallery(): void
    {
        $result = $this->sync()->sync(['mine.jpg'], [$this->item('a', 0), $this->item('b', 1)]);

        $this->assertSame(['mls:a', 'mls:b', 'mine.jpg'], $this->keys($result->entries));
    }

    /** @test */
    public function user_uploads_keep_their_relative_order(): void
    {
        $result = $this->sync()->sync(['one.jpg', 'two.jpg', 'three.jpg'], [$this->item('a', 0)]);

        $this->assertSame(['mls:a', 'one.jpg', 'two.jpg', 'three.jpg'], $this->keys($result->entries));
    }

    // ─── Owner-arranged galleries ────────────────────────────────────────────

    /** @test */
    public function an_owner_arranged_gallery_survives_a_refresh(): void
    {
        $stored = [
            'mine.jpg',
            ['source' => 'mls', 'media_key' => 'b', 'url' => 'https://cdn.example.com/b.jpg'],
            ['source' => 'mls', 'media_key' => 'a', 'url' => 'https://cdn.example.com/a.jpg'],
        ];

        $result = $this->sync()->sync(
            $stored,
            [$this->item('a', 0), $this->item('b', 1)],
            orderCustomized: true,
        );

        $this->assertSame(['mine.jpg', 'mls:b', 'mls:a'], $this->keys($result->entries));
    }

    /** @test */
    public function new_mls_photos_are_appended_to_an_owner_arranged_gallery(): void
    {
        $stored = [
            ['source' => 'mls', 'media_key' => 'b', 'url' => 'https://cdn.example.com/b.jpg'],
            'mine.jpg',
        ];

        $result = $this->sync()->sync(
            $stored,
            [$this->item('b', 0), $this->item('new', 1)],
            orderCustomized: true,
        );

        $this->assertSame(['mls:b', 'mine.jpg', 'mls:new'], $this->keys($result->entries));
    }

    /** @test */
    public function an_owner_chosen_cover_is_not_overruled_by_the_feed(): void
    {
        $stored = [
            ['source' => 'mls', 'media_key' => 'a', 'url' => 'https://cdn.example.com/a.jpg'],
            ['source' => 'mls', 'media_key' => 'b', 'url' => 'https://cdn.example.com/b.jpg', 'is_cover' => true, 'cover_chosen_by_owner' => true],
        ];

        $result = $this->sync()->sync($stored, [
            $this->item('a', 0, ['isPreferred' => true]),
            $this->item('b', 1),
        ]);

        $this->assertSame('mls:b', $result->coverKey);
    }

    /** @test */
    public function an_owner_chosen_cover_that_the_feed_withdrew_falls_back_safely(): void
    {
        $stored = [
            ['source' => 'mls', 'media_key' => 'gone', 'url' => 'https://cdn.example.com/gone.jpg', 'is_cover' => true, 'cover_chosen_by_owner' => true],
            ['source' => 'mls', 'media_key' => 'a', 'url' => 'https://cdn.example.com/a.jpg'],
        ];

        $result = $this->sync()->sync($stored, [$this->item('a', 0)]);

        $this->assertSame('mls:a', $result->coverKey);
        $this->assertTrue($result->entries[0]->isCover);
    }

    // ─── Cover selection ─────────────────────────────────────────────────────

    /** @test */
    public function an_owner_may_choose_any_member_as_the_cover(): void
    {
        $result = $this->sync()->sync(['mine.jpg'], [$this->item('a', 0), $this->item('b', 1)]);

        $chosen = $this->sync()->chooseCover($result->entries, 'mine.jpg');

        $this->assertNotNull($chosen);
        $covers = array_values(array_filter($chosen, fn (ListingPhotoEntry $e) => $e->isCover));
        $this->assertCount(1, $covers);
        $this->assertSame('mine.jpg', $covers[0]->key());
        $this->assertTrue($covers[0]->coverChosenByOwner);
    }

    /**
     * @test
     *
     * The regression this pair of flags exists to prevent: a cover this class
     * stamped itself must not be mistaken for a human decision on the next pass,
     * or the feed's first answer is frozen forever.
     */
    public function a_derived_cover_survives_a_refresh_without_becoming_an_owner_decision(): void
    {
        $first = $this->sync()->sync(null, [$this->item('a', 0), $this->item('b', 1)]);

        $this->assertSame('mls:a', $first->coverKey);
        $this->assertFalse($first->entries[0]->coverChosenByOwner);

        // The feed now names a different primary, and it takes effect.
        $second = $this->sync()->sync(
            ListingPhotoEntry::toStorageCollection($first->entries),
            [$this->item('a', 0), $this->item('b', 1, ['isPreferred' => true])],
        );

        $this->assertSame('mls:b', $second->coverKey);
    }

    /** @test */
    public function an_owner_cover_choice_survives_repeated_refreshes(): void
    {
        $items  = [$this->item('a', 0, ['isPreferred' => true]), $this->item('b', 1)];
        $result = $this->sync()->sync(null, $items);

        $chosen = $this->sync()->chooseCover($result->entries, 'mls:b');
        $stored = ListingPhotoEntry::toStorageCollection($chosen);

        for ($i = 0; $i < 3; $i++) {
            $refreshed = $this->sync()->sync($stored, $items);
            $this->assertSame('mls:b', $refreshed->coverKey, "lost the owner's cover on pass {$i}");
            $stored = ListingPhotoEntry::toStorageCollection($refreshed->entries);
        }
    }

    /**
     * @test
     *
     * A selector that is not a member of THIS gallery must not become its cover
     * — that is the path by which a crafted request would attach a reference to
     * media the listing does not hold.
     */
    public function a_selector_addressing_nothing_in_the_gallery_is_refused(): void
    {
        $result = $this->sync()->sync(null, [$this->item('a', 0)]);

        $this->assertNull($this->sync()->chooseCover($result->entries, 'mls:someone-elses-photo'));
        $this->assertNull($this->sync()->chooseCover($result->entries, 'not-my-file.jpg'));
        $this->assertNull($this->sync()->chooseCover($result->entries, ''));
    }
}
