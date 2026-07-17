<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferMeta;
use App\Services\Offers\OfferCounterService;
use App\Services\Offers\OfferDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BLK-06 — request-time expiry enforcement.
 *
 * Every one of these tests calls the transition services directly and NEVER runs
 * the scheduler, proving the application self-protects against acting on an
 * expired offer even when `offers:expire-pending` is delayed or unavailable.
 */
class OfferRequestTimeExpiryTest extends TestCase
{
    use DatabaseTransactions;

    private function decision(): OfferDecisionService
    {
        return app(OfferDecisionService::class);
    }

    private function counter(): OfferCounterService
    {
        return app(OfferCounterService::class);
    }

    // ── Expired submitted offer cannot be accepted ───────────────────────────

    public function test_expired_submitted_offer_cannot_be_accepted(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $result = $this->decision()->accept($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsStringIgnoringCase('expired', $result['reason']);
        // The offer is transitioned to 'expired' at request time, not left actionable.
        $this->assertSame('expired', Offer::find($offer->id)->status);
    }

    // ── Expired countered offer cannot be accepted ───────────────────────────

    public function test_expired_countered_offer_cannot_be_accepted(): void
    {
        $offer = Offer::factory()->countered()->create(['expires_at' => now()->subMinutes(5)]);

        $result = $this->decision()->accept($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertSame('expired', Offer::find($offer->id)->status);
    }

    // ── Expired offer cannot be rejected ─────────────────────────────────────

    public function test_expired_offer_cannot_be_rejected(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $result = $this->decision()->reject($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertSame('expired', Offer::find($offer->id)->status);
    }

    // ── Expired offer cannot be withdrawn ────────────────────────────────────

    public function test_expired_offer_cannot_be_withdrawn(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $result = $this->decision()->withdraw($offer, $offer->user_id, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertSame('expired', Offer::find($offer->id)->status);
    }

    // ── Expired offer cannot be countered (and no child is created) ──────────

    public function test_expired_offer_cannot_be_countered(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $result = $this->counter()->counter($offer, $offer->user_id, 'agent');

        $this->assertFalse($result['allowed']);
        $this->assertNull($result['counter_offer']);
        $this->assertStringContainsStringIgnoringCase('expired', $result['reason']);
        $this->assertSame('expired', Offer::find($offer->id)->status);
        $this->assertSame(0, Offer::where('parent_offer_id', $offer->id)->count());
    }

    // ── Unexpired offer remains fully actionable ─────────────────────────────

    public function test_unexpired_offer_remains_actionable(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->addDay()]);

        $result = $this->decision()->accept($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
    }

    // ── Offer with no deadline (null) is never treated as expired ─────────────

    public function test_offer_with_null_expiry_is_actionable(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => null]);

        $result = $this->decision()->accept($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
    }

    // ── Request-time expiry writes an offer_expired audit row (no scheduler) ──

    public function test_request_time_expiry_logs_offer_expired_event(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $this->decision()->accept($offer, null, 'system');

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_expired',
            'to_status'  => 'expired',
        ]);
    }

    // ── No accepted-terms snapshot is written for a blocked (expired) accept ──

    public function test_expired_accept_does_not_capture_terms_snapshot(): void
    {
        $offer = Offer::factory()->submitted()->create(['expires_at' => now()->subHour()]);

        $this->decision()->accept($offer, null, 'system');

        $this->assertFalse(
            OfferMeta::where('offer_id', $offer->id)
                ->where('meta_key', 'accepted_terms_snapshot')
                ->exists()
        );
    }
}
