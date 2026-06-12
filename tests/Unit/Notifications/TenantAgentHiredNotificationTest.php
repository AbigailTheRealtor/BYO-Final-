<?php

namespace Tests\Unit\Notifications;

use App\Notifications\TenantAgentHiredNotification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * TenantAgentHiredNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class TenantAgentHiredNotificationTest extends TestCase
{
    private MockInterface $bid;
    private MockInterface $auction;
    private TenantAgentHiredNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bid    = Mockery::mock()->makePartial();
        $this->bid->id = 43;

        $this->auction         = Mockery::mock()->makePartial();
        $this->auction->id     = 63;
        $this->auction->title  = 'Tenant Listing Title';
        $this->auction->user_id = 4;

        $this->notification = new TenantAgentHiredNotification($this->bid, $this->auction, null, 'tenant_agent');
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
        $this->assertSame('Tenant Listing Title', $data['context_line']);
    }
}
