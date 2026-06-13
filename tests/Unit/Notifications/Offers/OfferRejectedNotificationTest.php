<?php

namespace Tests\Unit\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Offers\OfferRejectedNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Mockery;
use Tests\TestCase;

class OfferRejectedNotificationTest extends TestCase
{
    private Offer $offer;
    private OfferRejectedNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offer = Mockery::mock(Offer::class)->makePartial();
        $this->offer->shouldReceive('getAttribute')->with('id')->andReturn(42);
        $this->offer->shouldReceive('getAttribute')->with('status')->andReturn('rejected');
        $this->offer->shouldReceive('getAttribute')->with('user_id')->andReturn(8);
        $this->offer->shouldReceive('getRouteKey')->andReturn(42);
        $this->offer->shouldReceive('getRouteKeyName')->andReturn('id');

        $this->notification = new OfferRejectedNotification($this->offer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Case 1: via() returns exactly ['database', 'mail']
    public function test_via_returns_database_and_mail(): void
    {
        $this->assertSame(['database', 'mail'], $this->notification->via(null));
    }

    // Case 2: toDatabase() contains all required keys
    public function test_to_database_contains_required_keys(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertArrayHasKey('offer_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('link', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('recipient_context', $data);
    }

    // Case 3: toDatabase()['offer_id'] matches the mocked offer's id
    public function test_to_database_offer_id_matches_offer(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame(42, $data['offer_id']);
    }

    // Case 4: toDatabase()['status'] matches the mocked offer's status
    public function test_to_database_status_matches_offer_status(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame('rejected', $data['status']);
    }

    // Case 5: toDatabase()['link'] contains the offer's id
    public function test_to_database_link_contains_offer_id(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertStringContainsString('42', $data['link']);
    }

    // Case 6: toDatabase()['type'] equals 'offer_rejected'
    public function test_to_database_type_equals_offer_rejected(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame('offer_rejected', $data['type']);
    }

    // Case 9: toDatabase()['message'] is present and non-generic
    public function test_to_database_message_is_non_generic(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertArrayHasKey('message', $data);
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('You have a notification', $data['message']);
        $this->assertNotSame('New Notification', $data['message']);
    }

    // Case 10: Offer notifications intentionally omit context_line
    public function test_to_database_has_no_context_line(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertArrayNotHasKey('context_line', $data);
    }

    // Case 7: toMail() returns an instance of MailMessage
    public function test_to_mail_returns_mail_message_instance(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    // Case 8: submitter-side toMail() subject contains the offer id
    public function test_to_mail_submitter_subject_contains_offer_id(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 8;

        $mail = $this->notification->toMail($notifiable);

        $this->assertStringContainsString('42', $mail->subject);
    }

    // Case 11: listing owner receives the owner-side message
    public function test_to_database_owner_receives_owner_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 99;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('owner', $data['recipient_context']);
        $this->assertSame('An offer was declined.', $data['message']);
    }

    // Case 12: offer submitter receives the submitter-side message
    public function test_to_database_submitter_receives_submitter_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 8;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('submitter', $data['recipient_context']);
        $this->assertSame('Your offer was declined.', $data['message']);
    }
}
