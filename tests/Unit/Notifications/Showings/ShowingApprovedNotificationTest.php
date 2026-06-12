<?php

namespace Tests\Unit\Notifications\Showings;

use App\Notifications\Showings\ShowingApprovedNotification;
use App\Models\Showing;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * ShowingApprovedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class ShowingApprovedNotificationTest extends TestCase
{
    private MockInterface $showing;
    private ShowingApprovedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showing                        = Mockery::mock(Showing::class)->makePartial();
        $this->showing->id                    = 31;
        $this->showing->offer_auction_id      = 51;
        $this->showing->approved_date         = null;
        $this->showing->requested_date        = null;
        $this->showing->approved_start_time   = null;
        $this->showing->approved_end_time     = null;
        $this->showing->requested_start_time  = null;
        $this->showing->requested_end_time    = null;
        $this->showing->owner_message         = null;
        $this->showing->shouldReceive('getAttribute')->with('approved_date')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('approved_start_time')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('approved_end_time')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('requested_start_time')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('requested_end_time')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('owner_message')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(51);
        $this->showing->shouldReceive('getAttribute')
            ->with('offerAuction')
            ->andReturn(null);

        $this->notification = new ShowingApprovedNotification($this->showing);
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

    public function test_to_database_message_is_approved(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('Your showing request was approved.', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_type_is_showing_approved(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('showing_approved', $data['type']);
    }
}
