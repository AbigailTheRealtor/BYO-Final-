<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferCounterService;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferStateMachineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * OfferCounterServiceTest
 *
 * Verifies OfferCounterService using DatabaseTransactions — every test
 * runs inside a transaction that rolls back automatically.
 *
 * Test coverage (14 cases):
 *   Case 1:  submitted → countered is allowed; allowed=true; parent status updated in DB.
 *   Case 2:  countered → countered is allowed; parent remains countered; child created.
 *   Case 3:  accepted → countered is forbidden; allowed=false; parent unchanged; counter_offer=null.
 *   Case 4:  Parent status is persisted as countered in DB after success (fresh query).
 *   Case 5:  Child offer row exists in DB after success.
 *   Case 6:  Child parent_offer_id equals parent's id.
 *   Case 7:  Child listing_snapshot copies from parent when $overrides is empty.
 *   Case 8:  offer_countered event log is written on success.
 *   Case 9:  forbidden_transition_attempt log written on failure; parent unchanged; counter_offer=null.
 *   Case 10: $metadata and $ipAddress pass through to the event log row (fresh DB query).
 *   Case 11: Return array contains all seven keys on both success and failure paths.
 *   Case 12: $overrides can replace child listing_snapshot.
 *   Case 13: $overrides cannot override parent_offer_id — the service forces it.
 *   Case 14: $overrides cannot override status — the service forces it to countered.
 */
class OfferCounterServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OfferCounterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OfferCounterService(
            new OfferStateMachineService(),
            new OfferEventLogService(),
        );
    }

    // ── Case 1: submitted → countered is allowed ──────────────────────────

    public function test_counter_from_submitted_is_allowed(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, $parent->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('submitted', $result['from_status']);
        $this->assertSame('countered', $result['to_status']);
        $this->assertSame('countered', Offer::find($parent->id)->status);
    }

    // ── Case 2: countered → countered is allowed ──────────────────────────

    public function test_counter_from_countered_is_allowed(): void
    {
        $parent = Offer::factory()->countered()->create();

        $result = $this->service->counter($parent, $parent->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('countered', $result['from_status']);
        $this->assertSame('countered', $result['to_status']);
        $this->assertNotNull($result['counter_offer']);
    }

    // ── Case 3: accepted → countered is forbidden ─────────────────────────

    public function test_counter_from_accepted_is_forbidden(): void
    {
        $parent = Offer::factory()->accepted()->create();

        $result = $this->service->counter($parent, $parent->user_id, 'agent');

        $this->assertFalse($result['allowed']);
        $this->assertNull($result['counter_offer']);
        $this->assertSame('accepted', Offer::find($parent->id)->status);
    }

    // ── Case 4: parent status persisted as countered in DB (fresh query) ──

    public function test_parent_status_persisted_as_countered_after_success(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $this->service->counter($parent, null, 'system');

        $fresh = Offer::find($parent->id);
        $this->assertSame('countered', $fresh->status);
    }

    // ── Case 5: child offer row exists in DB after success ─────────────────

    public function test_child_offer_row_exists_in_db_after_success(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, null, 'system');

        $child = $result['counter_offer'];
        $this->assertNotNull($child->id);
        $this->assertDatabaseHas('offers', ['id' => $child->id]);
    }

    // ── Case 6: child parent_offer_id equals parent's id ──────────────────

    public function test_child_parent_offer_id_equals_parent_id(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, null, 'system');

        $child = $result['counter_offer'];
        $this->assertSame($parent->id, $child->parent_offer_id);
    }

    // ── Case 7: child listing_snapshot copies from parent when no overrides

    public function test_child_listing_snapshot_copies_from_parent_when_no_overrides(): void
    {
        $snapshot = ['price' => 500000, 'beds' => 3];
        $parent   = Offer::factory()->submitted()->create(['listing_snapshot' => $snapshot]);

        $result = $this->service->counter($parent, null, 'system');

        $child = $result['counter_offer'];
        $this->assertSame($snapshot, $child->listing_snapshot);
    }

    // ── Case 8: offer_countered event log written on success ──────────────

    public function test_offer_countered_event_log_written_on_success(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, $parent->user_id, 'agent');

        $this->assertTrue($result['allowed']);
        $log = $result['event_log'];
        $this->assertInstanceOf(OfferEventLog::class, $log);
        $this->assertSame('offer_countered', $log->event_type);
        $this->assertSame('submitted', $log->from_status);
        $this->assertSame('countered', $log->to_status);
        $this->assertSame($parent->id, $log->offer_id);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $parent->id,
            'event_type' => 'offer_countered',
        ]);
    }

    // ── Case 9: forbidden_transition_attempt log on failure ───────────────

    public function test_forbidden_transition_attempt_log_written_on_failure(): void
    {
        $parent = Offer::factory()->accepted()->create();

        $result = $this->service->counter($parent, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertNull($result['counter_offer']);

        $log = $result['event_log'];
        $this->assertInstanceOf(OfferEventLog::class, $log);
        $this->assertSame('forbidden_transition_attempt', $log->event_type);
        $this->assertSame($parent->id, $log->offer_id);

        $this->assertSame('accepted', Offer::find($parent->id)->status);

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $parent->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }

    // ── Case 10: $metadata and $ipAddress pass through to event log ───────

    public function test_metadata_and_ip_address_pass_through_to_event_log(): void
    {
        $parent    = Offer::factory()->submitted()->create();
        $metadata  = ['note' => 'counter-offer test', 'round' => 2];
        $ipAddress = '10.1.2.3';

        $result = $this->service->counter($parent, $parent->user_id, 'agent', [], $metadata, $ipAddress);

        $log   = $result['event_log'];
        $fresh = OfferEventLog::find($log->id);

        $this->assertSame($metadata, $fresh->metadata);
        $this->assertSame($ipAddress, $fresh->ip_address);
    }

    // ── Case 11: return array contains all seven keys ─────────────────────

    public function test_return_array_contains_all_seven_keys_on_success(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, null, 'system');

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('parent_offer', $result);
        $this->assertArrayHasKey('counter_offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);
    }

    public function test_return_array_contains_all_seven_keys_on_failure(): void
    {
        $parent = Offer::factory()->accepted()->create();

        $result = $this->service->counter($parent, null, 'system');

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('parent_offer', $result);
        $this->assertArrayHasKey('counter_offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);
    }

    // ── Case 12: $overrides can replace child listing_snapshot ────────────

    public function test_overrides_can_replace_child_listing_snapshot(): void
    {
        $parent          = Offer::factory()->submitted()->create(['listing_snapshot' => ['price' => 100000]]);
        $newSnapshot     = ['price' => 200000, 'beds' => 4];

        $result = $this->service->counter($parent, null, 'system', ['listing_snapshot' => $newSnapshot]);

        $child = $result['counter_offer'];
        $this->assertSame($newSnapshot, $child->listing_snapshot);
    }

    // ── Case 13: $overrides cannot override parent_offer_id ───────────────

    public function test_overrides_cannot_override_parent_offer_id(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, null, 'system', ['parent_offer_id' => 99999]);

        $child = $result['counter_offer'];
        $this->assertSame($parent->id, $child->parent_offer_id);
    }

    // ── Case 14: $overrides cannot override status ────────────────────────

    public function test_overrides_cannot_override_status(): void
    {
        $parent = Offer::factory()->submitted()->create();

        $result = $this->service->counter($parent, null, 'system', ['status' => 'accepted']);

        $child = $result['counter_offer'];
        $this->assertSame('countered', $child->status);
    }
}
