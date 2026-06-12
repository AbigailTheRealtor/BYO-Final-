<?php

namespace Tests\Unit\Notifications;

use App\Notifications\BidModifiedNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * BidModifiedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class BidModifiedNotificationTest extends TestCase
{
    private MockInterface $bid;
    private MockInterface $auction;
    private BidModifiedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bid    = Mockery::mock()->makePartial();
        $this->bid->id = 13;

        $this->auction           = Mockery::mock()->makePartial();
        $this->auction->id       = 23;
        $this->auction->title    = 'Modified Auction';
        $this->auction->listing_id = null;
        $this->auction->user_id  = 7;

        $this->notification = new BidModifiedNotification($this->bid, $this->auction);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_to_database_has_message_key(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_to_database_message_is_not_generic(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('You have a notification', $data['message']);
        $this->assertNotSame('New Notification', $data['message']);
    }

    public function test_to_database_message_is_concise(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('A bid on your listing was updated.', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_context_line_contains_auction_title(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('Modified Auction', $data['context_line']);
    }

    public function test_to_database_type_is_bid_modified(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('bid_modified', $data['type']);
    }
}
