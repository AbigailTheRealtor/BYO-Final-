<?php

namespace Tests\Unit\Notifications\Showings;

use App\Notifications\Showings\ShowingCanceledNotification;
use App\Models\Showing;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * ShowingCanceledNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class ShowingCanceledNotificationTest extends TestCase
{
    private MockInterface $showing;
    private ShowingCanceledNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showing                    = Mockery::mock(Showing::class)->makePartial();
        $this->showing->id                = 33;
        $this->showing->offer_auction_id  = 53;
        $this->showing->requested_date    = null;
        $this->showing->canceled_at       = null;
        $this->showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('canceled_at')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(53);
        $this->showing->shouldReceive('getAttribute')
            ->with('offerAuction')
            ->andReturn(null);

        $this->notification = new ShowingCanceledNotification($this->showing);
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

    public function test_to_database_message_is_canceled(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('A showing was canceled.', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_type_is_showing_canceled(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('showing_canceled', $data['type']);
    }
}
