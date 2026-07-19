<?php

namespace Tests\Unit\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Offers\OfferCancelledNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * B2.1B — OfferCancelledNotification (mocked Offer only; no DB).
 */
class OfferCancelledNotificationTest extends TestCase
{
    private MockInterface $offer;
    private OfferCancelledNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offer          = Mockery::mock(Offer::class)->makePartial();
        $this->offer->id      = 42;
        $this->offer->status  = 'cancelled';
        $this->offer->user_id = 9;

        $this->notification = new OfferCancelledNotification($this->offer, 'Financing fell through');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_via_returns_database_and_mail(): void
    {
        $this->assertSame(['database', 'mail'], $this->notification->via(null));
    }

    public function test_to_database_contains_required_keys(): void
    {
        $data = $this->notification->toDatabase(null);

        foreach (['offer_id', 'status', 'link', 'type', 'recipient_context', 'reason', 'message'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    public function test_to_database_type_is_offer_cancelled(): void
    {
        $this->assertSame('offer_cancelled', $this->notification->toDatabase(null)['type']);
    }

    public function test_to_database_carries_reason(): void
    {
        $this->assertSame('Financing fell through', $this->notification->toDatabase(null)['reason']);
    }

    public function test_owner_receives_owner_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 99;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('owner', $data['recipient_context']);
        $this->assertSame('An accepted offer on your listing was cancelled.', $data['message']);
    }

    public function test_submitter_receives_submitter_message(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 9;

        $data = $this->notification->toDatabase($notifiable);

        $this->assertSame('submitter', $data['recipient_context']);
        $this->assertSame('Your accepted offer was cancelled.', $data['message']);
    }

    public function test_to_mail_returns_mail_message_with_reason(): void
    {
        $notifiable     = new \stdClass();
        $notifiable->id = 9;

        $mail = $this->notification->toMail($notifiable);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringContainsString('42', $mail->subject);

        $joined = implode(' ', array_map(fn ($l) => (string) $l, $mail->introLines));
        $this->assertStringContainsString('Financing fell through', $joined);
    }
}
