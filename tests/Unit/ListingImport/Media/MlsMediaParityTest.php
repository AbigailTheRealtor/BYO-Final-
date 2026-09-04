<?php

namespace Tests\Unit\ListingImport\Media;

use App\Services\ListingImport\Media\MlsMediaExtractor;
use App\Services\ListingImport\Media\MlsMediaPolicy;
use Tests\TestCase;

/**
 * MLS photo parity — every permitted photo, in the feed's order, and not one
 * the feed refused.
 *
 * Two defects from the 2026-09-04 payload audit are pinned here:
 *
 *   · The 50-photo ceiling truncated 186 of 1,202 cached listings, one of them
 *     from 100 photos down to 50. The ceiling existed to mirror the manual
 *     uploader's limit, which is about bytes WE store — and MLS media is
 *     referenced, never copied, so the comparison never applied.
 *
 *   · `Permission: ["Public"]` is on all 34,248 media objects in the corpus and
 *     nothing read it. Stellar sends none of the boolean columns the extractor
 *     checked, so every image resolved to "the feed did not object", and one
 *     arriving as `["Private"]` would have been published.
 */
class MlsMediaParityTest extends TestCase
{
    private function extractor(): MlsMediaExtractor
    {
        return new MlsMediaExtractor(new MlsMediaPolicy());
    }

    /** A media object shaped exactly like Stellar's. */
    private function object(int $order, array $overrides = []): array
    {
        return array_merge([
            'MediaKey'          => "KEY-m{$order}",
            'MediaCategory'     => 'Photo',
            'MediaURL'          => "https://cdn.example.com/photo-{$order}.jpeg",
            'MediaObjectID'     => "799934446_{$order}",
            'ResourceRecordKey' => 'KEY',
            'ResourceName'      => 'Property',
            'ClassName'         => 'Residential',
            'ShortDescription'  => null,
            'MimeType'          => 'image/jpeg',
            'Order'             => $order,
            'Permission'        => ['Public'],
            'LongDescription'   => null,
        ], $overrides);
    }

    private function record(int $count, array $overrides = []): array
    {
        $media = [];

        for ($i = 1; $i <= $count; $i++) {
            $media[] = $this->object($i, $overrides);
        }

        return ['ListingKey' => 'KEY', 'Media' => $media];
    }

    // ─── Counts ──────────────────────────────────────────────────────────────

    /** @test */
    public function a_record_with_no_media_yields_nothing(): void
    {
        $this->assertSame([], $this->extractor()->fromRecord(['ListingKey' => 'KEY']));
        $this->assertSame([], $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => []]));
    }

    /** @test */
    public function a_single_photo_survives(): void
    {
        $this->assertCount(1, $this->extractor()->fromRecord($this->record(1)));
    }

    /**
     * @test
     *
     * THE CAP FIX. The corpus's largest gallery is 100 photographs; 186 listings
     * carry more than 50. All of them must now arrive whole.
     */
    public function galleries_larger_than_fifty_are_no_longer_truncated(): void
    {
        $items = $this->extractor()->fromRecord($this->record(100));

        $this->assertCount(100, $items, 'Photos past the old 50-image ceiling are still being dropped');
    }

    /**
     * @test
     *
     * The ceiling still exists, as a backstop against a malformed response
     * claiming thousands of images. It is simply far above anything Stellar
     * publishes.
     */
    public function a_safety_ceiling_still_applies_far_above_real_gallery_sizes(): void
    {
        config(['mls_media.max_images' => 250]);

        $this->assertCount(250, $this->extractor()->fromRecord($this->record(400)));
    }

    // ─── Ordering and cover ──────────────────────────────────────────────────

    /** @test */
    public function the_feeds_order_is_preserved_and_densified(): void
    {
        // Stellar's Order is 1-based; the output must be a dense 0-based
        // sequence so "preserved ordering" means something the gallery can use.
        $record = ['ListingKey' => 'KEY', 'Media' => [
            $this->object(3),
            $this->object(1),
            $this->object(2),
        ]];

        $items = $this->extractor()->fromRecord($record);

        $this->assertSame([0, 1, 2], array_map(fn ($i) => $i->sequence, $items));
        $this->assertSame(
            ['KEY-m1', 'KEY-m2', 'KEY-m3'],
            array_map(fn ($i) => $i->mediaKey, $items)
        );
    }

    /**
     * @test
     *
     * Stellar sends no `PreferredPhotoYN` on any of the 34,248 objects in the
     * corpus, so the primary image is the one the feed ordered first. That is
     * the correct answer for this feed and the test records that it is a
     * consequence of the data rather than an accident.
     */
    public function the_first_ordered_photo_is_the_primary_when_the_feed_names_none(): void
    {
        $items = $this->extractor()->fromRecord($this->record(4));

        $this->assertSame('KEY-m1', $items[0]->mediaKey);
        $this->assertSame(0, $items[0]->sequence);

        foreach ($items as $item) {
            $this->assertFalse($item->isPreferred, 'This feed names no preferred photo');
        }
    }

    /** @test */
    public function an_explicit_preferred_photo_is_honoured_when_the_feed_sends_one(): void
    {
        $record = ['ListingKey' => 'KEY', 'Media' => [
            $this->object(1),
            $this->object(2, ['PreferredPhotoYN' => true]),
        ]];

        $items = $this->extractor()->fromRecord($record);

        $this->assertFalse($items[0]->isPreferred);
        $this->assertTrue($items[1]->isPreferred);
    }

    // ─── Captions ────────────────────────────────────────────────────────────

    /**
     * @test
     *
     * `ShortDescription` is present-but-null on every object in the corpus, and
     * `LongDescription` is populated on 4,445 of them. The caption chain has to
     * fall through the null rather than stopping at it.
     */
    public function a_long_description_becomes_the_caption_when_short_is_null(): void
    {
        $items = $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => [
            $this->object(1, ['ShortDescription' => null, 'LongDescription' => 'Rear elevation']),
        ]]);

        $this->assertSame('Rear elevation', $items[0]->caption);
    }

    // ─── Permission ──────────────────────────────────────────────────────────

    /**
     * @test
     *
     * THE PERMISSION FIX. An object the feed has not marked Public is refused.
     */
    public function a_non_public_permission_list_refuses_the_image(): void
    {
        foreach ([['Private'], ['Office'], ['IDX', 'Private']] as $permission) {
            $items = $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => [
                $this->object(1, ['Permission' => $permission]),
            ]]);

            $this->assertSame([], $items, json_encode($permission) . ' must not be published');
        }
    }

    /** @test */
    public function a_public_permission_list_admits_the_image(): void
    {
        $this->assertCount(1, $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => [
            $this->object(1, ['Permission' => ['Public']]),
        ]]));
    }

    /**
     * @test
     *
     * An explicit list must not fall through to a boolean column that is absent
     * and would resolve to "no objection" — that fall-through is exactly how a
     * refusal would become permission.
     */
    public function a_refusing_permission_list_beats_an_absent_boolean_column(): void
    {
        $items = $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => [
            $this->object(1, ['Permission' => ['Private']]),  // no PermittedForPublicDisplay at all
        ]]);

        $this->assertSame([], $items);
    }

    /** @test */
    public function a_feed_that_sends_no_permission_column_still_works(): void
    {
        $object = $this->object(1);
        unset($object['Permission']);

        $this->assertCount(1, $this->extractor()->fromRecord([
            'ListingKey' => 'KEY',
            'Media'      => [$object],
        ]));
    }

    // ─── Robustness ──────────────────────────────────────────────────────────

    /** @test */
    public function duplicate_media_keys_are_collapsed(): void
    {
        $items = $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => [
            $this->object(1),
            $this->object(1),
            $this->object(2),
        ]]);

        $this->assertCount(2, $items);
    }

    /**
     * @test
     *
     * A single unusable entry costs that entry and nothing else. A gallery that
     * quietly omits one photograph is a cosmetic defect; one that renders a
     * broken `src` looks like our bug.
     */
    public function a_broken_entry_is_skipped_without_losing_the_rest(): void
    {
        $items = $this->extractor()->fromRecord(['ListingKey' => 'KEY', 'Media' => [
            $this->object(1),
            $this->object(2, ['MediaURL' => 'http://insecure.example.com/x.jpg']),
            $this->object(3, ['MediaURL' => null]),
            // Every key alias blank: MediaObjectID is one of them, so nulling
            // MediaKey alone would still resolve — correctly, and that is the
            // point of the alias chain.
            $this->object(4, ['MediaKey' => null, 'MediaObjectID' => null]),
            ['not an object'],
            $this->object(5),
        ]]);

        $this->assertCount(2, $items);
        $this->assertSame(['KEY-m1', 'KEY-m5'], array_map(fn ($i) => $i->mediaKey, $items));
    }

    /** @test */
    public function extraction_is_idempotent_for_an_unchanged_record(): void
    {
        $record = $this->record(6);

        $a = $this->extractor()->fromRecord($record);
        $b = $this->extractor()->fromRecord($record);

        $this->assertSame(
            array_map(fn ($i) => $i->mediaKey . ':' . $i->sequence, $a),
            array_map(fn ($i) => $i->mediaKey . ':' . $i->sequence, $b),
        );
    }
}
