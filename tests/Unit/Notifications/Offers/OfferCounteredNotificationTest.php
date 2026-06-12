<?php

namespace Tests\Unit\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Offers\OfferCounteredNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Mockery;
use Tests\TestCase;

class OfferCounteredNotificationTest extends TestCase
{
    private Offer $parentOffer;
    private Offer $counterOffer;
    private OfferCounteredNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parentOffer = Mockery::mock(Offer::class)->makePartial();
        $this->parentOffer->shouldReceive('getAttribute')->with('id')->andReturn(10);

        $this->counterOffer = Mockery::mock(Offer::class)->makePartial();
        $this->counterOffer->shouldReceive('getAttribute')->with('id')->andReturn(42);
        $this->counterOffer->shouldReceive('getAttribute')->with('status')->andReturn('countered');
        $this->counterOffer->shouldReceive('getRouteKey')->andReturn(42);
        $this->counterOffer->shouldReceive('getRouteKeyName')->andReturn('id');

        $this->notification = new OfferCounteredNotification($this->parentOffer, $this->counterOffer);
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

        $this->assertArrayHasKey('parent_offer_id', $data);
        $this->assertArrayHasKey('counter_offer_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('link', $data);
        $this->assertArrayHasKey('type', $data);
    }

    // Case 3: toDatabase()['parent_offer_id'] matches the mocked parent offer's id
    public function test_to_database_parent_offer_id_matches_parent(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame(10, $data['parent_offer_id']);
    }

    // Case 4: toDatabase()['counter_offer_id'] matches the mocked counter offer's id
    public function test_to_database_counter_offer_id_matches_counter(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame(42, $data['counter_offer_id']);
    }

    // Case 5: toDatabase()['status'] matches the mocked counter offer's status
    public function test_to_database_status_matches_counter_offer_status(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame('countered', $data['status']);
    }

    // Case 6: toDatabase()['link'] contains the counter offer's id
    public function test_to_database_link_contains_counter_offer_id(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertStringContainsString('42', $data['link']);
    }

    // Case 7: toDatabase()['type'] equals 'offer_countered'
    public function test_to_database_type_equals_offer_countered(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame('offer_countered', $data['type']);
    }

    // Case 10: toDatabase()['message'] is present and non-generic
    public function test_to_database_message_is_non_generic(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertArrayHasKey('message', $data);
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('You have a notification', $data['message']);
        $this->assertNotSame('New Notification', $data['message']);
    }

    // Case 11: Offer notifications intentionally omit context_line
    public function test_to_database_has_no_context_line(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertArrayNotHasKey('context_line', $data);
    }

    // Case 8: toMail() returns an instance of MailMessage
    public function test_to_mail_returns_mail_message_instance(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    // Case 9: toMail() subject contains the counter offer id
    public function test_to_mail_subject_contains_counter_offer_id(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertStringContainsString('42', $mail->subject);
    }
}
