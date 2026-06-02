<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferDecisionService;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferStateMachineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * OfferDecisionServiceTest
 *
 * Verifies OfferDecisionService using DatabaseTransactions — every test
 * runs inside a transaction that rolls back automatically.
 *
 * Test coverage (15 cases):
 *   Cases 1–6:  allowed transitions mutate Offer.status and write the correct event log.
 *   Cases 7–9:  forbidden transitions leave Offer.status unchanged and write a
 *               forbidden_transition_attempt log.
 *   Case 10:    failed transition does not modify Offer.status (fresh DB query).
 *   Case 11:    success path writes event log with matching event_type/from_status/to_status.
 *   Case 12:    failed path writes forbidden_transition_attempt log.
 *   Case 13:    return array always contains all six keys.
 *   Case 14:    $metadata and $ipAddress pass through to the event log row.
 *   Case 15:    $actorId can be null without error.
 */
class OfferDecisionServiceTest extends TestCase
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

    // ── Cases 1–6: allowed transitions ────────────────────────────────────

    /** Case 1: submitted → accepted */
    public function test_accept_from_submitted_is_allowed(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->accept($offer, $offer->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('submitted', $result['from_status']);
        $this->assertSame('accepted', $result['to_status']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_accepted',
        ]);
    }

    /** Case 2: countered → accepted */
    public function test_accept_from_countered_is_allowed(): void
    {
        $offer = Offer::factory()->countered()->create();

        $result = $this->service->accept($offer, $offer->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('countered', $result['from_status']);
        $this->assertSame('accepted', $result['to_status']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_accepted',
        ]);
    }

    /** Case 3: submitted → rejected */
    public function test_reject_from_submitted_is_allowed(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->reject($offer, $offer->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('submitted', $result['from_status']);
        $this->assertSame('rejected', $result['to_status']);
        $this->assertSame('rejected', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_rejected',
        ]);
    }

    /** Case 4: countered → rejected */
    public function test_reject_from_countered_is_allowed(): void
    {
        $offer = Offer::factory()->countered()->create();

        $result = $this->service->reject($offer, $offer->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('countered', $result['from_status']);
        $this->assertSame('rejected', $result['to_status']);
        $this->assertSame('rejected', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_rejected',
        ]);
    }

    /** Case 5: submitted → withdrawn */
    public function test_withdraw_from_submitted_is_allowed(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->withdraw($offer, $offer->user_id, 'buyer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('submitted', $result['from_status']);
        $this->assertSame('withdrawn', $result['to_status']);
        $this->assertSame('withdrawn', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_withdrawn',
        ]);
    }

    /** Case 6: countered → withdrawn */
    public function test_withdraw_from_countered_is_allowed(): void
    {
        $offer = Offer::factory()->countered()->create();

        $result = $this->service->withdraw($offer, $offer->user_id, 'buyer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('countered', $result['from_status']);
        $this->assertSame('withdrawn', $result['to_status']);
        $this->assertSame('withdrawn', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'offer_withdrawn',
        ]);
    }

    // ── Cases 7–9: forbidden transitions ──────────────────────────────────

    /** Case 7: draft → accepted is forbidden */
    public function test_accept_from_draft_is_forbidden(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $result = $this->service->accept($offer, $offer->user_id, 'agent');

        $this->assertFalse($result['allowed']);
        $this->assertSame('draft', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }

    /** Case 8: accepted → rejected is forbidden */
    public function test_reject_from_accepted_is_forbidden(): void
    {
        $offer = Offer::factory()->accepted()->create();

        $result = $this->service->reject($offer, $offer->user_id, 'agent');

        $this->assertFalse($result['allowed']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }

    /** Case 9: rejected → withdrawn is forbidden */
    public function test_withdraw_from_rejected_is_forbidden(): void
    {
        $offer = Offer::factory()->create(['status' => 'rejected']);

        $result = $this->service->withdraw($offer, $offer->user_id, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertSame('rejected', Offer::find($offer->id)->status);
        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }

    // ── Case 10: failed transition does not modify Offer.status ───────────

    public function test_failed_transition_does_not_modify_offer_status_via_fresh_query(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $this->service->accept($offer, null, 'system');

        $fresh = Offer::find($offer->id);
        $this->assertSame('draft', $fresh->status);
        $this->assertSame('draft', $offer->status);
    }

    // ── Case 11: success path writes event log with correct fields ─────────

    public function test_success_path_writes_event_log_with_correct_fields(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->accept($offer, $offer->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $log = $result['event_log'];
        $this->assertInstanceOf(OfferEventLog::class, $log);
        $this->assertSame('offer_accepted', $log->event_type);
        $this->assertSame('submitted', $log->from_status);
        $this->assertSame('accepted', $log->to_status);
        $this->assertSame($offer->id, $log->offer_id);
    }

    // ── Case 12: failed path writes forbidden_transition_attempt log ───────

    public function test_failed_path_writes_forbidden_transition_attempt_log(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $result = $this->service->accept($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $log = $result['event_log'];
        $this->assertInstanceOf(OfferEventLog::class, $log);
        $this->assertSame('forbidden_transition_attempt', $log->event_type);
        $this->assertSame('draft', $log->from_status);
        $this->assertSame('accepted', $log->to_status);
        $this->assertNotNull($log->id);
    }

    // ── Case 13: return array always contains all six keys ─────────────────

    public function test_return_array_contains_all_six_keys_on_success(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->accept($offer, null, 'system');

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);
    }

    public function test_return_array_contains_all_six_keys_on_failure(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $result = $this->service->accept($offer, null, 'system');

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);
    }

    // ── Case 14: $metadata and $ipAddress pass through to the event log ────

    public function test_metadata_and_ip_address_pass_through_to_event_log(): void
    {
        $offer     = Offer::factory()->submitted()->create();
        $metadata  = ['note' => 'strong offer', 'priority' => 1];
        $ipAddress = '10.0.0.1';

        $result = $this->service->accept($offer, $offer->user_id, 'agent', $metadata, $ipAddress);

        $log = $result['event_log'];
        $fresh = OfferEventLog::find($log->id);

        $this->assertSame($metadata, $fresh->metadata);
        $this->assertSame($ipAddress, $fresh->ip_address);
    }

    // ── Case 15: $actorId can be null without error ────────────────────────

    public function test_null_actor_id_does_not_cause_error(): void
    {
        $offer = Offer::factory()->submitted()->create();

        $result = $this->service->accept($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['event_log']->actor_id);
        $this->assertSame('accepted', Offer::find($offer->id)->status);
    }
}
