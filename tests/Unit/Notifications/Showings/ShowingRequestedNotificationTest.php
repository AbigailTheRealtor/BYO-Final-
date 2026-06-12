<?php

namespace Tests\Unit\Notifications\Showings;

use App\Notifications\Showings\ShowingRequestedNotification;
use App\Models\Showing;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * ShowingRequestedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class ShowingRequestedNotificationTest extends TestCase
{
    private MockInterface $showing;
    private ShowingRequestedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $requester             = Mockery::mock()->makePartial();
        $requester->first_name = 'John';
        $requester->last_name  = 'Buyer';

        $this->showing                       = Mockery::mock(Showing::class)->makePartial();
        $this->showing->id                   = 30;
        $this->showing->offer_auction_id     = 50;
        $this->showing->requested_start_time = '10:00:00';
        $this->showing->requested_end_time   = '11:00:00';
        $this->showing->requested_date       = null;
        $this->showing->requester            = $requester;
        $this->showing->shouldReceive('getAttribute')->with('requester')->andReturn($requester);
        $this->showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('requested_start_time')->andReturn('10:00:00');
        $this->showing->shouldReceive('getAttribute')->with('requested_end_time')->andReturn('11:00:00');
        $this->showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(50);

        $this->showing->shouldReceive('getAttribute')
            ->with('offerAuction')
            ->andReturn(null);

        $this->notification = new ShowingRequestedNotification($this->showing);
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

    public function test_to_database_message_is_new_showing_request(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('New showing request received.', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_type_is_showing_requested(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('showing_requested', $data['type']);
    }
}
