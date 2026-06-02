<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferEventLogService;
use App\Services\Offers\OfferStateMachineService;
use App\Services\Offers\OfferSubmissionService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * OfferSubmissionServiceTest
 *
 * Verifies OfferSubmissionService using mocked collaborators only.
 * No database, no factories, no RefreshDatabase, no DatabaseTransactions.
 *
 * Test coverage (10 cases):
 *   (1)  Draft offer can be submitted (returns allowed=true)
 *   (2)  Status changes to 'submitted' on success
 *   (3)  submitted_at is set when it is empty
 *   (4)  Existing submitted_at is not overwritten
 *   (5)  'offer_submitted' event log is written on success
 *   (6)  Non-draft invalid transition does not change status
 *   (7)  Invalid transition writes 'forbidden_transition_attempt' log
 *   (8)  Return array contains all required keys
 *   (9)  metadata and ip_address pass through to event log
 *   (10) actor_id can be null; save() called once on success, never on failure
 */
class OfferSubmissionServiceTest extends TestCase
{
    private MockInterface $stateMachine;
    private MockInterface $eventLogger;
    private OfferSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = Mockery::mock(OfferStateMachineService::class);
        $this->eventLogger  = Mockery::mock(OfferEventLogService::class);

        $this->service = new OfferSubmissionService(
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

    private function makeOffer(string $status = 'draft', mixed $submittedAt = null): MockInterface
    {
        $offer = Mockery::mock(Offer::class)->makePartial();
        $offer->status       = $status;
        $offer->submitted_at = $submittedAt;
        return $offer;
    }

    private function makeEventLog(): OfferEventLog
    {
        $log = new OfferEventLog();
        $log->id = 99;
        return $log;
    }

    private function allowedTransitionResult(string $from = 'draft'): array
    {
        return ['allowed' => true, 'from_status' => $from, 'to_status' => 'submitted', 'reason' => ''];
    }

    private function forbiddenTransitionResult(string $from, string $reason = 'forbidden'): array
    {
        return ['allowed' => false, 'from_status' => $from, 'to_status' => 'submitted', 'reason' => $reason];
    }

    // ── Case 1: draft offer can be submitted ─────────────────────────────────

    public function test_draft_offer_can_be_submitted(): void
    {
        $offer = $this->makeOffer('draft');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('draft', 'submitted')
            ->andReturn($this->allowedTransitionResult());

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->andReturn($log);

        $result = $this->service->submit($offer, actorId: 1);

        $this->assertTrue($result['allowed']);
    }

    // ── Case 2: status changes to 'submitted' on success ────────────────────

    public function test_status_changes_to_submitted_on_success(): void
    {
        $offer = $this->makeOffer('draft');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('draft', 'submitted')
            ->andReturn($this->allowedTransitionResult());

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $this->service->submit($offer, actorId: 1);

        $this->assertSame('submitted', $offer->status);
    }

    // ── Case 3: submitted_at is set when it is empty ─────────────────────────

    public function test_submitted_at_is_set_when_empty(): void
    {
        $offer = $this->makeOffer('draft', null);
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedTransitionResult());

        $offer->shouldReceive('save')->once();
        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $this->service->submit($offer, actorId: 1);

        $this->assertNotNull($offer->submitted_at);
    }

    // ── Case 4: existing submitted_at is not overwritten ─────────────────────

    public function test_existing_submitted_at_is_not_overwritten(): void
    {
        $original = '2024-01-15 10:00:00';
        $offer    = $this->makeOffer('draft', $original);
        $log      = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedTransitionResult());

        $offer->shouldReceive('save')->once();
        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $this->service->submit($offer, actorId: 1);

        // Eloquent casts the date string to Carbon on assignment; compare as
        // a formatted string to confirm the value was not overwritten.
        $this->assertSame(
            $original,
            is_string($offer->submitted_at)
                ? $offer->submitted_at
                : $offer->submitted_at->format('Y-m-d H:i:s'),
        );
    }

    // ── Case 5: 'offer_submitted' event log is written on success ────────────

    public function test_offer_submitted_event_log_is_written_on_success(): void
    {
        $offer = $this->makeOffer('draft');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedTransitionResult());

        $offer->shouldReceive('save')->once();

        $this->eventLogger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::on(fn ($o) => $o === $offer),
                Mockery::any(),
                Mockery::any(),
                'offer_submitted',
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
            )
            ->andReturn($log);

        $result = $this->service->submit($offer, actorId: 1);

        $this->assertSame($log, $result['event_log']);
    }

    // ── Case 6: non-draft invalid transition does not change status ───────────

    public function test_invalid_transition_does_not_change_status(): void
    {
        $offer = $this->makeOffer('accepted');

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('accepted', 'submitted')
            ->andReturn($this->forbiddenTransitionResult('accepted', 'Transition from accepted to submitted is forbidden.'));

        $log = $this->makeEventLog();
        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $offer->shouldReceive('save')->never();

        $this->service->submit($offer, actorId: 1);

        $this->assertSame('accepted', $offer->status);
    }

    // ── Case 7: invalid transition writes 'forbidden_transition_attempt' ──────

    public function test_invalid_transition_writes_forbidden_transition_attempt_log(): void
    {
        $offer = $this->makeOffer('rejected');

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('rejected', 'submitted')
            ->andReturn($this->forbiddenTransitionResult('rejected', 'rejected is a final state.'));

        $log = $this->makeEventLog();

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

        $offer->shouldReceive('save')->never();

        $result = $this->service->submit($offer, actorId: 1);

        $this->assertFalse($result['allowed']);
    }

    // ── Case 8: return array contains all required keys ───────────────────────

    public function test_return_array_contains_all_required_keys(): void
    {
        $offer = $this->makeOffer('draft');
        $log   = $this->makeEventLog();

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedTransitionResult());

        $offer->shouldReceive('save')->once();
        $this->eventLogger->shouldReceive('log')->once()->andReturn($log);

        $result = $this->service->submit($offer, actorId: 1);

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('offer', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('event_log', $result);

        $this->assertSame($offer, $result['offer']);
        $this->assertSame('draft', $result['from_status']);
        $this->assertSame('submitted', $result['to_status']);
    }

    // ── Case 9: metadata and ip_address pass through to event log ────────────

    public function test_metadata_and_ip_address_pass_through_to_event_log(): void
    {
        $offer     = $this->makeOffer('draft');
        $log       = $this->makeEventLog();
        $metadata  = ['source' => 'web', 'session_id' => 'abc123'];
        $ipAddress = '192.0.2.55';

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->andReturn($this->allowedTransitionResult());

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

        $result = $this->service->submit($offer, actorId: 5, metadata: $metadata, ipAddress: $ipAddress);

        $this->assertTrue($result['allowed'], 'Expected submit() to succeed so that the event log receives metadata/ip.');
    }

    // ── Case 10: actor_id can be null; save() called once/never ──────────────

    public function test_actor_id_can_be_null_and_save_called_correctly(): void
    {
        $log = $this->makeEventLog();

        // Success path: actor_id=null, save() called exactly once.
        $offerSuccess = $this->makeOffer('draft');

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('draft', 'submitted')
            ->andReturn($this->allowedTransitionResult());

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

        $resultSuccess = $this->service->submit($offerSuccess, actorId: null);
        $this->assertTrue($resultSuccess['allowed']);

        // Failure path: actor_id=null, save() never called.
        $offerFailure = $this->makeOffer('expired');

        $this->stateMachine->shouldReceive('validateTransition')
            ->once()
            ->with('expired', 'submitted')
            ->andReturn($this->forbiddenTransitionResult('expired', 'expired is a final state.'));

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

        $resultFailure = $this->service->submit($offerFailure, actorId: null);
        $this->assertFalse($resultFailure['allowed']);
    }
}
