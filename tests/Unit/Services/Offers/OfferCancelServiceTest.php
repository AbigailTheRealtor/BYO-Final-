<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferEventLog;
use App\Models\OfferMeta;
use App\Services\Offers\OfferDecisionService;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferStateMachineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * B2.1B — OfferDecisionService::cancel() (accepted → cancelled).
 *
 * Exercises the real service (same construction as OfferDecisionServiceTest),
 * asserting: status transition + event log with reason, snapshot preservation,
 * sibling non-interference, listing reactivation, and forbidden non-accepted cancel.
 */
class OfferCancelServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OfferDecisionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OfferDecisionService(
            new OfferStateMachineService(),
            new OfferEventLogService()
        );
    }

    private function cancelMetadata(array $log): array
    {
        return is_array($log['metadata'] ?? null) ? $log['metadata'] : (array) json_decode($log['metadata'] ?? '[]', true);
    }

    public function test_cancel_accepted_sets_status_cancelled_and_logs_reason(): void
    {
        $auction = OfferAuction::factory()->create();
        $offer   = Offer::factory()->accepted()->create(['offer_auction_id' => $auction->id]);

        $result = $this->service->cancel($offer, $auction->user_id, 'admin', ['reason' => 'Buyer financing fell through']);

        $this->assertTrue($result['allowed']);
        $this->assertSame('accepted', $result['from_status']);
        $this->assertSame('cancelled', $result['to_status']);
        $this->assertSame('cancelled', Offer::find($offer->id)->status);

        $log = OfferEventLog::where('offer_id', $offer->id)
            ->where('event_type', 'offer_cancelled')
            ->first();
        $this->assertNotNull($log, 'offer_cancelled event log must be written');

        $meta = is_array($log->metadata) ? $log->metadata : (array) json_decode($log->metadata, true);
        $this->assertSame('Buyer financing fell through', $meta['reason'] ?? null);
    }

    public function test_cancel_preserves_accepted_terms_snapshot(): void
    {
        $auction = OfferAuction::factory()->create();
        $offer   = Offer::factory()->accepted()->create(['offer_auction_id' => $auction->id]);

        // Simulate the write-once snapshot captured at acceptance.
        OfferMeta::create([
            'offer_id'   => $offer->id,
            'meta_key'   => 'accepted_terms_snapshot',
            'meta_value' => json_encode(['offer_price' => '500000', 'financing' => 'cash']),
        ]);

        $this->service->cancel($offer, $auction->user_id, 'admin', ['reason' => 'x']);

        $snapshot = OfferMeta::where('offer_id', $offer->id)
            ->where('meta_key', 'accepted_terms_snapshot')
            ->first();
        $this->assertNotNull($snapshot, 'snapshot must survive cancellation');
        $this->assertSame(
            json_encode(['offer_price' => '500000', 'financing' => 'cash']),
            $snapshot->meta_value,
            'snapshot content must be unchanged by cancellation'
        );
    }

    public function test_cancel_does_not_reopen_or_modify_rejected_competitors(): void
    {
        $auction    = OfferAuction::factory()->create();
        $accepted   = Offer::factory()->accepted()->create(['offer_auction_id' => $auction->id]);
        $competitor = Offer::factory()->create(['offer_auction_id' => $auction->id, 'status' => 'rejected']);

        $this->service->cancel($accepted, $auction->user_id, 'admin', ['reason' => 'x']);

        $this->assertSame('rejected', Offer::find($competitor->id)->status, 'competing rejected offer must remain rejected');

        // No new event log rows written against the competitor by the cancel.
        $this->assertSame(
            0,
            OfferEventLog::where('offer_id', $competitor->id)->count(),
            'cancellation must not touch or re-notify competing offers'
        );
    }

    public function test_cancel_reactivates_listing(): void
    {
        $auction = OfferAuction::factory()->create();
        $auction->is_sold = true;
        $auction->save();
        $auction->saveMeta('listing_status', 'Accepted');

        $offer = Offer::factory()->accepted()->create(['offer_auction_id' => $auction->id]);

        $this->service->cancel($offer, $auction->user_id, 'admin', ['reason' => 'x']);

        $fresh = OfferAuction::find($auction->id);
        $this->assertFalse((bool) $fresh->is_sold, 'listing must no longer be marked sold');
        $this->assertSame('Active', $fresh->info('listing_status'), 'listing_status must be reset to Active');
    }

    public function test_cancel_from_non_accepted_is_forbidden(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->cancel($offer, $offer->user_id, 'admin', ['reason' => 'x']);

        $this->assertFalse($result['allowed']);
        $this->assertSame('submitted', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }
}
