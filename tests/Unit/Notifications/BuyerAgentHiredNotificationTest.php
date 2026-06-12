<?php

namespace Tests\Unit\Notifications;

use App\Notifications\BuyerAgentHiredNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * BuyerAgentHiredNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class BuyerAgentHiredNotificationTest extends TestCase
{
    private MockInterface $bid;
    private MockInterface $auction;
    private BuyerAgentHiredNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bid    = Mockery::mock()->makePartial();
        $this->bid->id = 41;

        $this->auction         = Mockery::mock()->makePartial();
        $this->auction->id     = 61;
        $this->auction->title  = 'Buyer Listing Title';
        $this->auction->user_id = 2;

        $this->notification = new BuyerAgentHiredNotification($this->bid, $this->auction, null, 'buyer_agent');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_to_database_type_is_agent_hired(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('agent_hired', $data['type']);
    }

    public function test_to_database_has_message_key(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('message', $data);
        $this->assertNotEmpty($data['message']);
    }

    public function test_to_database_message_is_not_generic(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertNotSame('You have a notification', $data['message']);
        $this->assertNotSame('New Notification', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertSame('Buyer Listing Title', $data['context_line']);
    }
}
