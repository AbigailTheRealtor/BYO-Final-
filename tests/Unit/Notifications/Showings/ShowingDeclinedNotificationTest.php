<?php

namespace Tests\Unit\Notifications\Showings;

use App\Notifications\Showings\ShowingDeclinedNotification;
use App\Models\Showing;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * ShowingDeclinedNotificationTest
 *
 * Pure unit test — no database, no factories, mocked models only.
 */
class ShowingDeclinedNotificationTest extends TestCase
{
    private MockInterface $showing;
    private ShowingDeclinedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showing                    = Mockery::mock(Showing::class)->makePartial();
        $this->showing->id                = 32;
        $this->showing->offer_auction_id  = 52;
        $this->showing->requested_date    = null;
        $this->showing->owner_message     = null;
        $this->showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('owner_message')->andReturn(null);
        $this->showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(52);
        $this->showing->shouldReceive('getAttribute')
            ->with('offerAuction')
            ->andReturn(null);

        $this->notification = new ShowingDeclinedNotification($this->showing);
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

    public function test_to_database_message_is_declined(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('Your showing request was declined.', $data['message']);
    }

    public function test_to_database_has_context_line(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertArrayHasKey('context_line', $data);
        $this->assertNotEmpty($data['context_line']);
    }

    public function test_to_database_type_is_showing_declined(): void
    {
        $data = $this->notification->toDatabase(null);
        $this->assertSame('showing_declined', $data['type']);
    }
}
