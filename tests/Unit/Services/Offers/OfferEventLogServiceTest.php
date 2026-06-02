<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferEventLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * OfferEventLogServiceTest
 *
 * Verifies OfferEventLogService using the project's standard
 * DatabaseTransactions test pattern.  Each test runs inside a transaction
 * that rolls back automatically — no data survives to the live schema.
 *
 * No routes, controllers, views, or migrations are touched.
 *
 * Test coverage (9 cases):
 *   (1) log() returns an OfferEventLog model instance
 *   (2) The returned row is persisted (has a real primary key)
 *   (3) All provided field values are stored correctly
 *   (4) metadata is stored and retrieved as a PHP array (array cast)
 *   (5) ip_address is stored and retrievable
 *   (6) actor_id accepts null (system events)
 *   (7) ip_address accepts null
 *   (8) Multiple log() calls insert multiple distinct rows — never updates
 *   (9) log() never modifies Offer.status
 */
class OfferEventLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OfferEventLogService $service;
    private Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OfferEventLogService();

        // A real Offer row (with all FK dependencies) — rolled back after each test.
        $this->offer = Offer::factory()->create(['status' => 'draft']);
    }

    // ── Case 1: returns an OfferEventLog instance ─────────────────────────

    public function test_log_returns_offer_event_log_instance(): void
    {
        $result = $this->service->log(
            $this->offer,
            actorId: $this->offer->user_id,
            actorRole: 'buyer',
            eventType: 'submitted',
            fromStatus: 'draft',
            toStatus: 'submitted'
        );

        $this->assertInstanceOf(OfferEventLog::class, $result);
    }

    // ── Case 2: returned model is persisted (has a real PK) ──────────────

    public function test_log_persists_the_row_to_the_database(): void
    {
        $result = $this->service->log(
            $this->offer,
            actorId: $this->offer->user_id,
            actorRole: 'buyer',
            eventType: 'submitted',
            fromStatus: 'draft',
            toStatus: 'submitted'
        );

        $this->assertNotNull($result->id);
        $this->assertIsInt($result->id);

        $this->assertDatabaseHas('offer_event_logs', ['id' => $result->id]);
    }

    // ── Case 3: all field values are stored correctly ─────────────────────

    public function test_log_stores_all_provided_field_values(): void
    {
        $actorId    = $this->offer->user_id; // reuse a real user FK
        $actorRole  = 'agent';
        $eventType  = 'status_changed';
        $fromStatus = 'submitted';
        $toStatus   = 'accepted';
        $ipAddress  = '192.168.1.1';

        $result = $this->service->log(
            $this->offer,
            actorId: $actorId,
            actorRole: $actorRole,
            eventType: $eventType,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            metadata: [],
            ipAddress: $ipAddress
        );

        $this->assertSame($this->offer->id, $result->offer_id);
        $this->assertSame($actorId,    $result->actor_id);
        $this->assertSame($actorRole,  $result->actor_role);
        $this->assertSame($eventType,  $result->event_type);
        $this->assertSame($fromStatus, $result->from_status);
        $this->assertSame($toStatus,   $result->to_status);
        $this->assertSame($ipAddress,  $result->ip_address);
    }

    // ── Case 4: metadata is stored and retrieved as a PHP array ──────────

    public function test_log_stores_metadata_as_array(): void
    {
        $metadata = ['reason' => 'price too low', 'counter_value' => 450000];

        $result = $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'system',
            eventType: 'note_added',
            fromStatus: null,
            toStatus: null,
            metadata: $metadata
        );

        // Cast is declared on the model — must come back as an array.
        $fresh = OfferEventLog::find($result->id);
        $this->assertIsArray($fresh->metadata);
        $this->assertSame($metadata, $fresh->metadata);
    }

    // ── Case 5: ip_address is stored and retrievable ──────────────────────

    public function test_log_stores_ip_address(): void
    {
        $ip = '203.0.113.42';

        $result = $this->service->log(
            $this->offer,
            actorId: $this->offer->user_id,
            actorRole: 'buyer',
            eventType: 'viewed',
            fromStatus: null,
            toStatus: null,
            ipAddress: $ip
        );

        $this->assertSame($ip, $result->ip_address);
        $this->assertDatabaseHas('offer_event_logs', [
            'id'         => $result->id,
            'ip_address' => $ip,
        ]);
    }

    // ── Case 6: actor_id accepts null (system events) ─────────────────────

    public function test_log_accepts_null_actor_id(): void
    {
        $result = $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'system',
            eventType: 'auto_expired',
            fromStatus: 'submitted',
            toStatus: 'expired'
        );

        $this->assertNull($result->actor_id);
        $this->assertDatabaseHas('offer_event_logs', [
            'id'       => $result->id,
            'actor_id' => null,
        ]);
    }

    // ── Case 7: ip_address accepts null ───────────────────────────────────

    public function test_log_accepts_null_ip_address(): void
    {
        $result = $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'system',
            eventType: 'scheduled_task',
            fromStatus: null,
            toStatus: null,
            ipAddress: null
        );

        $this->assertNull($result->ip_address);
    }

    // ── Case 8: multiple calls insert multiple rows, never updates ─────────

    public function test_multiple_log_calls_insert_distinct_rows(): void
    {
        $before = OfferEventLog::where('offer_id', $this->offer->id)->count();

        $first  = $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'buyer',
            eventType: 'submitted',
            fromStatus: 'draft',
            toStatus: 'submitted'
        );

        $second = $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'agent',
            eventType: 'reviewed',
            fromStatus: 'submitted',
            toStatus: 'submitted'
        );

        $third = $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'system',
            eventType: 'auto_expired',
            fromStatus: 'submitted',
            toStatus: 'expired'
        );

        $after = OfferEventLog::where('offer_id', $this->offer->id)->count();

        // Three distinct PKs — not updates of the same row.
        $this->assertNotEquals($first->id, $second->id);
        $this->assertNotEquals($second->id, $third->id);
        $this->assertNotEquals($first->id, $third->id);

        // Row count grew by exactly 3.
        $this->assertSame($before + 3, $after);
    }

    // ── Case 9: log() never modifies Offer.status ─────────────────────────

    public function test_log_does_not_modify_offer_status(): void
    {
        $originalStatus = $this->offer->status; // 'draft'

        $this->service->log(
            $this->offer,
            actorId: null,
            actorRole: 'system',
            eventType: 'status_changed',
            fromStatus: 'draft',
            toStatus: 'submitted'
        );

        // In-memory model must be unchanged.
        $this->assertSame($originalStatus, $this->offer->status);

        // Database row must also be unchanged.
        $fresh = Offer::find($this->offer->id);
        $this->assertSame($originalStatus, $fresh->status);
    }
}
