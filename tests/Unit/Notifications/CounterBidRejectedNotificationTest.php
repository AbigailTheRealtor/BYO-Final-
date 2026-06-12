<?php

namespace Tests\Unit\Notifications;

use App\Notifications\CounterBidRejectedNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * CounterBidRejectedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class CounterBidRejectedNotificationTest extends TestCase
{
    private MockInterface $counterBid;
    private MockInterface $auction;
    private CounterBidRejectedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->counterBid          = Mockery::mock()->makePartial();
        $this->counterBid->id      = 16;
        $this->counterBid->user_id = 9;

        $this->auction        = Mockery::mock()->makePartial();
        $this->auction->id    = 26;
        $this->auction->title = 'Counter Rejected Auction';

        $this->notification = new CounterBidRejectedNotification($this->counterBid, $this->auction);
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

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_context_line_contains_auction_title(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('Counter Rejected Auction', $data['context_line']);
    }

    public function test_to_database_type_is_counter_bid_rejected(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('counter_bid_rejected', $data['type']);
    }
}
