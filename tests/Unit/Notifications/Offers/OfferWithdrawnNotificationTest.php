<?php

namespace Tests\Unit\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Offers\OfferWithdrawnNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * OfferWithdrawnNotificationTest
 *
 * Verifies OfferWithdrawnNotification using a mocked Offer model only.
 * No database, no factories, no RefreshDatabase, no DatabaseTransactions.
 *
 * Test coverage (8 cases):
 *   (1) via() returns exactly ['database', 'mail']
 *   (2) toDatabase() contains keys offer_id, status, link, type
 *   (3) toDatabase()['offer_id'] matches the mocked offer's id
 *   (4) toDatabase()['status'] matches the mocked offer's status
 *   (5) toDatabase()['link'] contains the expected offer id
 *   (6) toDatabase()['type'] equals 'offer_withdrawn'
 *   (7) toMail() returns an instance of MailMessage
 *   (8) toMail() subject contains the offer id
 */
class OfferWithdrawnNotificationTest extends TestCase
{
    private MockInterface $offer;
    private OfferWithdrawnNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offer = Mockery::mock(Offer::class)->makePartial();
        $this->offer->id     = 42;
        $this->offer->status = 'withdrawn';

        $this->notification = new OfferWithdrawnNotification($this->offer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Case 1: via() returns exactly ['database', 'mail'] ───────────────────

    public function test_via_returns_database_and_mail(): void
    {
        $this->assertSame(['database', 'mail'], $this->notification->via(null));
    }

    // ── Case 2: toDatabase() contains all required keys ───────────────────────

    public function test_to_database_contains_required_keys(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertArrayHasKey('offer_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('link', $data);
        $this->assertArrayHasKey('type', $data);
    }

    // ── Case 3: toDatabase()['offer_id'] matches the mocked offer's id ───────

    public function test_to_database_offer_id_matches_offer(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame(42, $data['offer_id']);
    }

    // ── Case 4: toDatabase()['status'] matches the mocked offer's status ─────

    public function test_to_database_status_matches_offer(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame('withdrawn', $data['status']);
    }

    // ── Case 5: toDatabase()['link'] contains the expected offer id ──────────

    public function test_to_database_link_contains_offer_id(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertStringContainsString('42', $data['link']);
    }

    // ── Case 6: toDatabase()['type'] equals 'offer_withdrawn' ────────────────

    public function test_to_database_type_is_offer_withdrawn(): void
    {
        $data = $this->notification->toDatabase(null);

        $this->assertSame('offer_withdrawn', $data['type']);
    }

    // ── Case 7: toMail() returns an instance of MailMessage ──────────────────

    public function test_to_mail_returns_mail_message_instance(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    // ── Case 8: toMail() subject contains the offer id ───────────────────────

    public function test_to_mail_subject_contains_offer_id(): void
    {
        $mail = $this->notification->toMail(null);

        $this->assertStringContainsString('42', $mail->subject);
    }
}
