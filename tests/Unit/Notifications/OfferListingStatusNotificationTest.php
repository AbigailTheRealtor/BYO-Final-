<?php

namespace Tests\Unit\Notifications;

use App\Models\OfferAuction;
use App\Notifications\OfferListingStatusNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * OfferListingStatusNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class OfferListingStatusNotificationTest extends TestCase
{
    private MockInterface $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing          = Mockery::mock(OfferAuction::class)->makePartial();
        $this->listing->id      = 70;
        $this->listing->title   = 'Oak Street Listing';
        $this->listing->user_id = 5;
        $this->listing->shouldReceive('getAttribute')->with('title')->andReturn('Oak Street Listing');
        $this->listing->shouldReceive('getAttribute')->with('id')->andReturn(70);
        $this->listing->shouldReceive('getAttribute')->with('user_id')->andReturn(5);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_approved_message_is_set_and_not_generic(): void
    {
        $n    = new OfferListingStatusNotification($this->listing, 'approved');
        $data = $n->toDatabase(null);

        $this->assertArrayHasKey('message', $data);
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('You have a notification', $data['message']);
        $this->assertNotSame('New Notification', $data['message']);
    }

    public function test_rejected_message_is_set_and_not_generic(): void
    {
        $n    = new OfferListingStatusNotification($this->listing, 'rejected');
        $data = $n->toDatabase(null);

        $this->assertArrayHasKey('message', $data);
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('You have a notification', $data['message']);
    }

    public function test_approved_message_does_not_embed_title(): void
    {
        $n    = new OfferListingStatusNotification($this->listing, 'approved');
        $data = $n->toDatabase(null);

        $this->assertStringNotContainsString('Oak Street Listing', $data['message']);
    }

    public function test_context_line_contains_listing_title(): void
    {
        $n    = new OfferListingStatusNotification($this->listing, 'approved');
        $data = $n->toDatabase(null);

        $this->assertArrayHasKey('context_line', $data);
        $this->assertSame('Oak Street Listing', $data['context_line']);
    }

    public function test_type_is_offer_listing_status(): void
    {
        $n    = new OfferListingStatusNotification($this->listing, 'approved');
        $data = $n->toDatabase(null);

        $this->assertSame('offer_listing_status', $data['type']);
    }
}
