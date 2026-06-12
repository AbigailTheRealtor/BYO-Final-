<?php

namespace Tests\Unit\Notifications;

use App\Notifications\SellerAgentHiredNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * SellerAgentHiredNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class SellerAgentHiredNotificationTest extends TestCase
{
    private MockInterface $bid;
    private MockInterface $auction;
    private SellerAgentHiredNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bid    = Mockery::mock()->makePartial();
        $this->bid->id = 40;

        $this->auction         = Mockery::mock()->makePartial();
        $this->auction->id     = 60;
        $this->auction->title  = 'Seller Listing Title';
        $this->auction->user_id = 1;

        $this->notification = new SellerAgentHiredNotification($this->bid, $this->auction, null, 'seller_agent');
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

    public function test_to_database_type_is_agent_hired(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('agent_hired', $data['type']);
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
        $this->assertSame('Seller Listing Title', $data['context_line']);
    }
}
