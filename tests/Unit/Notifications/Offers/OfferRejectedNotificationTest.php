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

    // Case 7: toMail() returns an instance of MailMessage
    public function test_to_mail_returns_mail_message_instance(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    // Case 8: toMail() subject contains the offer id
    public function test_to_mail_subject_contains_offer_id(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertStringContainsString('42', $mail->subject);
    }
}
