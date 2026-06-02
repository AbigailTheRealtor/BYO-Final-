<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferExpirationService;
use App\Services\Offers\OfferStateMachineService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * OfferExpirationServiceTest
 *
 * Verifies OfferExpirationService using mocked collaborators only.
 * No database, no factories, no RefreshDatabase, no DatabaseTransactions.
 *
 * Test coverage (12 cases):
 *   (1)  submitted → expired succeeds (returns allowed=true)
 *   (2)  countered → expired succeeds (returns allowed=true)
 *   (3)  draft cannot expire (returns allowed=false)
 *   (4)  accepted cannot expire (returns allowed=false)
 *   (5)  rejected cannot expire (returns allowed=false)
 *   (6)  Failed path does not call save()
 *   (7)  Success path logs 'offer_expired'
 *   (8)  Failure path logs 'forbidden_transition_attempt'
 *   (9)  metadata and ipAddress pass through to the log call
 *   (10) null actor_id is accepted
 *   (11) Return shape is complete on success
 *   (12) Return shape is complete on failure
 */
class OfferExpirationServiceTest extends TestCase
{
    private MockInterface $stateMachine;
    private MockInterface $eventLogger;
    private OfferExpirationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = Mockery::mock(OfferStateMachineService::class);
        $this->eventLogger  = Mockery::mock(OfferEventLogService::class);

        $this->service = new OfferExpirationService(
            $this->stateMachine,
            $this->eventLogger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeOffer(string $status = 'submitted'): MockInterface
    {
        $offer = Mockery::mock(Offer::class)->makePartial();
        $offer->status = $status;
        return $offer;
    }

    private function makeEventLog(): OfferEventLog
    {
        $log     = new OfferEventLog();
        $log->id = 99;
        return $log;
    }

    private function allowedResult(string $from): array
    {
        return ['allowed' => true, 'from_status' => $from, 'to_status' => 'expired', 'reason' => ''];
    }

    private function forbiddenResult(string $from, string $reason = 'forbidden'): array
    {
        return ['allowed' => false, 'from_status' => $from, 'to_status' => 'expired', 'reason' => $reason];
    }

    // ── Case 1: submitted → expired succeeds ─────────────────────────────────

    public function test_submitted_offer_can_expire(): void
    {
        $offer = $this->makeOffer('submitted');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('submitted', 'expired')
            ->andReturn($this->allowedResult('submitted'));

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertTrue($result['allowed']);
        $this->assertSame('expired', $offer->status);
    }

    // ── Case 2: countered → expired succeeds ─────────────────────────────────

    public function test_countered_offer_can_expire(): void
    {
        $offer = $this->makeOffer('countered');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('countered', 'expired')
            ->andReturn($this->allowedResult('countered'));

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertTrue($result['allowed']);
        $this->assertSame('expired', $offer->status);
    }

    // ── Case 3: draft cannot expire ──────────────────────────────────────────

    public function test_draft_offer_cannot_expire(): void
    {
        $offer = $this->makeOffer('draft');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('draft', 'expired')
            ->andReturn($this->forbiddenResult('draft', "Transition from 'draft' to 'expired' is a forbidden transition."));

        $offer->shouldReceive('save')->never();

        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertFalse($result['allowed']);
        $this->assertSame('draft', $offer->status);
    }

    // ── Case 4: accepted cannot expire ───────────────────────────────────────

    public function test_accepted_offer_cannot_expire(): void
    {
        $offer = $this->makeOffer('accepted');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('accepted', 'expired')
            ->andReturn($this->forbiddenResult('accepted', "Transition from 'accepted' to 'expired' is not permitted: 'accepted' is a final state."));

        $offer->shouldReceive('save')->never();

        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertFalse($result['allowed']);
        $this->assertSame('accepted', $offer->status);
    }

    // ── Case 5: rejected cannot expire ───────────────────────────────────────

    public function test_rejected_offer_cannot_expire(): void
    {
        $offer = $this->makeOffer('rejected');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('rejected', 'expired')
            ->andReturn($this->forbiddenResult('rejected', "Transition from 'rejected' to 'expired' is not permitted: 'rejected' is a final state."));

        $offer->shouldReceive('save')->never();

        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertFalse($result['allowed']);
        $this->assertSame('rejected', $offer->status);
    }

    // ── Case 6: failed path does not call save() ─────────────────────────────

    public function test_failed_path_does_not_call_save(): void
    {
        $offer = $this->makeOffer('draft');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->forbiddenResult('draft', 'forbidden'));

        $offer->shouldReceive('save')->never();

        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->expire($offer, actorId: null);

        $this->assertFalse($result['allowed']);
        $this->assertSame('draft', $offer->status, 'Status must remain unchanged when transition is forbidden.');
    }

    // ── Case 7: success path logs 'offer_expired' ────────────────────────────

    public function test_success_path_logs_offer_expired(): void
    {
        $offer = $this->makeOffer('submitted');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedResult('submitted'));

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::on(fn ($o) => $o === $offer),
                Mockery::any(),
                Mockery::any(),
                'offer_expired',
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
            )
            ->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertSame($log, $result['event_log']);
    }

    // ── Case 8: failure path logs 'forbidden_transition_attempt' ─────────────

    public function test_failure_path_logs_forbidden_transition_attempt(): void
    {
        $offer = $this->makeOffer('rejected');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->forbiddenResult('rejected', 'rejected is a final state.'));

        $offer->shouldReceive('save')->never();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::on(fn ($o) => $o === $offer),
                Mockery::any(),
                Mockery::any(),
                'forbidden_transition_attempt',
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
            )
            ->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertFalse($result['allowed']);
        $this->assertSame($log, $result['event_log']);
    }

    // ── Case 9: metadata and ipAddress pass through to the log call ───────────

    public function test_metadata_and_ip_address_pass_through_to_log(): void
    {
        $offer     = $this->makeOffer('submitted');
        $log       = $this->makeEventLog();
        $metadata  = ['source' => 'scheduler', 'run_id' => 'abc'];
        $ipAddress = '10.0.0.42';

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedResult('submitted'));

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                $metadata,
                $ipAddress,
            )
            ->andReturn($log);

        $result = $this->service->expire($offer, actorId: 5, metadata: $metadata, ipAddress: $ipAddress);

        $this->assertTrue($result['allowed']);
    }

    // ── Case 10: null actor_id is accepted ───────────────────────────────────

    public function test_null_actor_id_is_accepted(): void
    {
        $log = $this->makeEventLog();

        // Success path: null actorId, save() called once.
        $offerSuccess = $this->makeOffer('submitted');

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('submitted', 'expired')
            ->andReturn($this->allowedResult('submitted'));

        $offerSuccess->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::any(),
                null,
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
            )
            ->andReturn($log);

        $resultSuccess = $this->service->expire($offerSuccess, actorId: null);
        $this->assertTrue($resultSuccess['allowed']);

        // Failure path: null actorId, save() never called.
        $offerFailure = $this->makeOffer('draft');

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('draft', 'expired')
            ->andReturn($this->forbiddenResult('draft', 'forbidden'));

        $offerFailure->shouldReceive('save')->never();

        $logFailure = $this->makeEventLog();
        $this->eventLogger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::any(),
                null,
                Mockery::any(),
                'forbidden_transition_attempt',
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
            )
            ->andReturn($logFailure);

        $resultFailure = $this->service->expire($offerFailure, actorId: null);
        $this->assertFalse($resultFailure['allowed']);
    }

    // ── Case 11: return shape is complete on success ──────────────────────────

    public function test_return_shape_is_complete_on_success(): void
    {
        $offer = $this->makeOffer('submitted');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedResult('submitted'));

        $offer->shouldReceive('save')->once();
        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->expire($offer, actorId: 1);

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);

        $this->assertTrue($result['allowed']);
        $this->assertSame($offer, $result['offer']);
        $this->assertSame('submitted', $result['from_status']);
        $this->assertSame('expired', $result['to_status']);
        $this->assertSame('', $result['reason']);
        $this->assertSame($log, $result['event_log']);
    }

    // ── Case 12: return shape is complete on failure ──────────────────────────

    public function test_return_shape_is_complete_on_failure(): void
    {
        $offer  = $this->makeOffer('draft');
        $log    = $this->makeEventLog();
        $reason = "Transition from 'draft' to 'expired' is a forbidden transition.";

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->forbiddenResult('draft', $reason));

        $offer->shouldReceive('save')->never();
        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->expire($offer, actorId: null);

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);

        $this->assertFalse($result['allowed']);
        $this->assertSame($offer, $result['offer']);
        $this->assertSame('draft', $result['from_status']);
        $this->assertSame('expired', $result['to_status']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame($log, $result['event_log']);
    }
}
