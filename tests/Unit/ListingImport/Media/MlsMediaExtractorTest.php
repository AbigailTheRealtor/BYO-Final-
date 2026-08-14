<?php

namespace Tests\Unit\ListingImport\Media;

use App\Services\ListingImport\Media\MlsMediaExtractor;
use App\Services\ListingImport\Media\MlsMediaPolicy;
use Tests\TestCase;

/**
 * MlsMediaExtractor — raw Bridge record → permitted, ordered media.
 *
 * The Media object's live shape could not be verified when this was written (no
 * Bridge credentials, no fixture, the only in-repo sample truncated), so the
 * extractor is written defensively against the RESO Media resource. These tests
 * are what make "defensively" mean something specific: every field optional,
 * every unusable entry skipped rather than guessed at, and ordering
 * reconstructed rather than trusted.
 */
class MlsMediaExtractorTest extends TestCase
{
    private function extractor(): MlsMediaExtractor
    {
        return new MlsMediaExtractor(new MlsMediaPolicy());
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The extractor runs behind the policy, and the policy's own gates are
        // exercised separately. Here the feature is on so item-level rules are
        // what the assertions actually observe.
        config()->set('mls_media.enabled', true);
        config()->set('mls_media.license_acknowledged', true);
        config()->set('mls_media.hosting_mode', 'reference');
        config()->set('mls_media.max_images', 50);
    }

    private function media(array $overrides = []): array
    {
        return array_merge([
            'MediaKey'      => 'abc-m1',
            'MediaURL'      => 'https://cdn.example.com/abc-m1.jpg',
            'Order'         => 0,
            'MediaCategory' => 'Photo',
        ], $overrides);
    }

    private function record(array $media, array $overrides = []): array
    {
        return array_merge([
            'ListingKey' => 'STELLAR-MFR-1',
            'ListingId'  => 'A4567890',
            'Media'      => $media,
        ], $overrides);
    }

    // ─── Presence / absence ──────────────────────────────────────────────────

    /** @test */
    public function a_record_with_no_media_key_yields_nothing(): void
    {
        $this->assertSame([], $this->extractor()->fromRecord(['ListingKey' => 'X']));
    }

    /** @test */
    public function an_empty_media_array_yields_nothing(): void
    {
        $this->assertSame([], $this->extractor()->fromRecord($this->record([])));
    }

    /** @test */
    public function a_single_photo_is_extracted(): void
    {
        $items = $this->extractor()->fromRecord($this->record([$this->media()]));

        $this->assertCount(1, $items);
        $this->assertSame('abc-m1', $items[0]->mediaKey);
        $this->assertSame('https://cdn.example.com/abc-m1.jpg', $items[0]->url);
        $this->assertSame(0, $items[0]->sequence);
        $this->assertSame('STELLAR-MFR-1', $items[0]->listingKey);
    }

    /** @test */
    public function multiple_photos_are_all_extracted(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'm1', 'Order' => 0]),
            $this->media(['MediaKey' => 'm2', 'Order' => 1]),
            $this->media(['MediaKey' => 'm3', 'Order' => 2]),
        ]));

        $this->assertCount(3, $items);
        $this->assertSame(['m1', 'm2', 'm3'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    // ─── Ordering ────────────────────────────────────────────────────────────

    /** @test */
    public function feed_order_is_honoured_even_when_the_array_is_shuffled(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'third',  'Order' => 2]),
            $this->media(['MediaKey' => 'first',  'Order' => 0]),
            $this->media(['MediaKey' => 'second', 'Order' => 1]),
        ]));

        $this->assertSame(['first', 'second', 'third'], array_map(fn ($i) => $i->mediaKey, $items));
        $this->assertSame([0, 1, 2], array_map(fn ($i) => $i->sequence, $items));
    }

    /** @test */
    public function a_one_based_sparse_feed_order_is_renumbered_densely_from_zero(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'a', 'Order' => 1]),
            $this->media(['MediaKey' => 'b', 'Order' => 7]),
            $this->media(['MediaKey' => 'c', 'Order' => 40]),
        ]));

        $this->assertSame([0, 1, 2], array_map(fn ($i) => $i->sequence, $items));
        $this->assertSame(['a', 'b', 'c'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    /** @test */
    public function entries_with_no_order_fall_back_to_array_position(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'a', 'Order' => null]),
            $this->media(['MediaKey' => 'b', 'Order' => null]),
            $this->media(['MediaKey' => 'c', 'Order' => null]),
        ]));

        $this->assertSame(['a', 'b', 'c'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    /** @test */
    public function a_tied_order_resolves_to_feed_sequence_rather_than_arbitrarily(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'x', 'Order' => 5]),
            $this->media(['MediaKey' => 'y', 'Order' => 5]),
            $this->media(['MediaKey' => 'z', 'Order' => 5]),
        ]));

        $this->assertSame(['x', 'y', 'z'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    // ─── Malformed / missing data ────────────────────────────────────────────

    /** @test */
    public function an_entry_with_no_media_key_is_skipped_not_guessed_at(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'good']),
            ['MediaURL' => 'https://cdn.example.com/orphan.jpg'],
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('good', $items[0]->mediaKey);
    }

    /** @test */
    public function an_entry_with_no_url_is_skipped(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'good']),
            ['MediaKey' => 'urlless'],
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('good', $items[0]->mediaKey);
    }

    /** @test */
    public function non_array_media_entries_are_skipped(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            'https://cdn.example.com/just-a-string.jpg',
            null,
            42,
            $this->media(['MediaKey' => 'real']),
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('real', $items[0]->mediaKey);
    }

    /** @test */
    public function a_repeated_media_key_is_collapsed_to_its_first_occurrence(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'dup', 'Order' => 0, 'MediaURL' => 'https://cdn.example.com/first.jpg']),
            $this->media(['MediaKey' => 'dup', 'Order' => 1, 'MediaURL' => 'https://cdn.example.com/second.jpg']),
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('https://cdn.example.com/first.jpg', $items[0]->url);
    }

    // ─── Field aliases ───────────────────────────────────────────────────────

    /** @test */
    public function common_url_field_aliases_are_accepted(): void
    {
        foreach (['MediaURL', 'MediaUrl', 'Url', 'URL', 'MediaUri'] as $alias) {
            $items = $this->extractor()->fromRecord($this->record([
                ['MediaKey' => 'k', $alias => 'https://cdn.example.com/x.jpg'],
            ]));

            $this->assertCount(1, $items, "alias {$alias} was not accepted");
        }
    }

    /** @test */
    public function common_key_field_aliases_are_accepted(): void
    {
        foreach (['MediaKey', 'MediaObjectID', 'Key'] as $alias) {
            $items = $this->extractor()->fromRecord($this->record([
                [$alias => 'k', 'MediaURL' => 'https://cdn.example.com/x.jpg'],
            ]));

            $this->assertCount(1, $items, "alias {$alias} was not accepted");
        }
    }

    // ─── Preferred / cover ───────────────────────────────────────────────────

    /** @test */
    public function the_preferred_photo_flag_is_carried_through(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'a', 'Order' => 0]),
            $this->media(['MediaKey' => 'b', 'Order' => 1, 'PreferredPhotoYN' => true]),
        ]));

        $this->assertFalse($items[0]->isPreferred);
        $this->assertTrue($items[1]->isPreferred);
    }

    /** @test */
    public function yes_no_strings_are_read_as_booleans(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'a', 'PreferredPhotoYN' => 'Y']),
        ]));

        $this->assertTrue($items[0]->isPreferred);
    }

    // ─── Compliance ──────────────────────────────────────────────────────────

    /** @test */
    public function an_entry_the_feed_marks_as_not_publicly_displayable_is_excluded(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'public']),
            $this->media(['MediaKey' => 'private', 'PermittedForPublicDisplay' => false]),
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('public', $items[0]->mediaKey);
    }

    /**
     * @test
     *
     * InternalOnlyYN states the opposite of the other permission fields. Reading
     * it as-is would turn "internal only" into "cleared for public display",
     * which is the single worst way the extractor could be wrong.
     */
    public function internal_only_media_is_excluded_and_not_inverted_into_permission(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'shown']),
            $this->media(['MediaKey' => 'internal', 'InternalOnlyYN' => true]),
        ]));

        $this->assertSame(['shown'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    /** @test */
    public function a_non_https_url_is_excluded(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'insecure', 'MediaURL' => 'http://cdn.example.com/x.jpg']),
        ]));

        $this->assertSame([], $items);
    }

    /** @test */
    public function a_data_uri_can_never_reach_a_src_attribute(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'evil', 'MediaURL' => 'data:text/html;base64,PHNjcmlwdD4=']),
            $this->media(['MediaKey' => 'evil2', 'MediaURL' => 'javascript:alert(1)']),
        ]));

        $this->assertSame([], $items);
    }

    /** @test */
    public function a_document_category_is_not_treated_as_a_photograph(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'photo',  'MediaCategory' => 'Photo']),
            $this->media(['MediaKey' => 'doc',    'MediaCategory' => 'Document']),
            $this->media(['MediaKey' => 'branded','MediaCategory' => 'Branded Virtual Tour']),
        ]));

        $this->assertSame(['photo'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    /** @test */
    public function an_uncategorised_entry_is_allowed_by_default_but_refusable(): void
    {
        $record = $this->record([['MediaKey' => 'k', 'MediaURL' => 'https://cdn.example.com/x.jpg']]);

        $this->assertCount(1, $this->extractor()->fromRecord($record));

        config()->set('mls_media.allow_uncategorised', false);
        $this->assertCount(0, $this->extractor()->fromRecord($record));
    }

    /** @test */
    public function the_gallery_is_capped_and_the_cap_keeps_the_leading_images(): void
    {
        config()->set('mls_media.max_images', 3);

        $media = [];
        for ($i = 0; $i < 10; $i++) {
            $media[] = $this->media(['MediaKey' => "m{$i}", 'Order' => $i]);
        }

        $items = $this->extractor()->fromRecord($this->record($media));

        $this->assertCount(3, $items);
        $this->assertSame(['m0', 'm1', 'm2'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    // ─── Gallery entry shape ─────────────────────────────────────────────────

    /** @test */
    public function the_persisted_entry_carries_provenance_and_never_a_local_path(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'k1', 'ShortDescription' => 'Front elevation']),
        ]));

        $entry = $items[0]->toGalleryEntry();

        $this->assertSame('mls', $entry['source']);
        $this->assertSame('bridge', $entry['provider']);
        $this->assertSame('k1', $entry['media_key']);
        $this->assertSame('STELLAR-MFR-1', $entry['listing_key']);
        $this->assertSame('Front elevation', $entry['caption']);
        $this->assertSame('https://cdn.example.com/abc-m1.jpg', $entry['url']);

        // Reference-only hosting: nothing here may look like a file we host.
        $this->assertArrayNotHasKey('filename', $entry);
        $this->assertArrayNotHasKey('path', $entry);
    }

    /** @test */
    public function the_cover_flag_is_not_decided_at_item_level(): void
    {
        $items = $this->extractor()->fromRecord($this->record([
            $this->media(['MediaKey' => 'k', 'PreferredPhotoYN' => true]),
        ]));

        // One entry may carry is_cover, which is a property of the collection.
        // The item must not pre-empt that decision on its own.
        $this->assertArrayNotHasKey('is_cover', $items[0]->toGalleryEntry());
    }
}
