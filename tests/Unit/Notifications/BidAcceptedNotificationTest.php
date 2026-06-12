<?php

namespace Tests\Unit\Notifications;

use App\Notifications\BidAcceptedNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * BidAcceptedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class BidAcceptedNotificationTest extends TestCase
{
    private MockInterface $bid;
    private MockInterface $auction;
    private BidAcceptedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bid          = Mockery::mock()->makePartial();
        $this->bid->id      = 11;
        $this->bid->user_id = 5;

        $this->auction        = Mockery::mock()->makePartial();
        $this->auction->id    = 21;
        $this->auction->title = 'Bid Accepted Auction';

        $this->notification = new BidAcceptedNotification($this->bid, $this->auction, null, 'seller_agent');
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
        $this->assertSame('Your bid was accepted.', $data['message']);
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
        $this->assertSame('Bid Accepted Auction', $data['context_line']);
    }

    public function test_to_database_type_is_bid_accepted(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('bid_accepted', $data['type']);
    }
}
