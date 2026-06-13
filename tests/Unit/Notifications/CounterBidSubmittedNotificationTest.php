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

        $this->bid     = Mockery::mock()->makePartial();
        $this->bid->id = 14;

        $this->auction          = Mockery::mock()->makePartial();
        $this->auction->id      = 24;
        $this->auction->title   = 'Counter Bid Auction';
        $this->auction->user_id = 77;

        $this->sender             = Mockery::mock()->makePartial();
        $this->sender->id         = 55;
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

    public function test_to_database_receiver_message_is_concise(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('You received a counter bid.', $data['message']);
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

    public function test_to_database_has_recipient_context(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('recipient_context', $data);
        $this->assertSame('owner', $data['recipient_context']);
    }

    public function test_to_database_sender_receives_submitter_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 55;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('submitter', $data['recipient_context']);
        $this->assertSame('Your counter bid was submitted.', $data['message']);
    }

    public function test_to_database_auction_owner_receives_owner_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 77;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('owner', $data['recipient_context']);
        $this->assertSame('You received a counter bid.', $data['message']);
    }

    public function test_to_database_receiver_receives_owner_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 99;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('owner', $data['recipient_context']);
        $this->assertSame('You received a counter bid.', $data['message']);
    }

    public function test_to_database_unrelated_user_defaults_to_owner(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 999;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('owner', $data['recipient_context']);
    }
}
