<?php

namespace Tests\Unit\Notifications;

use App\Notifications\CounterBidSubmittedNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * CounterBidSubmittedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class CounterBidSubmittedNotificationTest extends TestCase
{
    private MockInterface $bid;
    private MockInterface $auction;
    private MockInterface $sender;
    private CounterBidSubmittedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bid    = Mockery::mock()->makePartial();
        $this->bid->id = 14;

        $this->auction        = Mockery::mock()->makePartial();
        $this->auction->id    = 24;
        $this->auction->title = 'Counter Bid Auction';

        $this->sender             = Mockery::mock()->makePartial();
        $this->sender->first_name = 'Jane';
        $this->sender->last_name  = 'Doe';

        $this->notification = new CounterBidSubmittedNotification(
            $this->bid, $this->auction, $this->sender, 99, 'seller_agent'
        );
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
        $this->assertSame('You received a counter proposal.', $data['message']);
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
        $this->assertSame('Counter Bid Auction', $data['context_line']);
    }

    public function test_to_database_type_is_counter_bid_submitted(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('counter_bid_submitted', $data['type']);
    }
}
