<?php

namespace Tests\Unit\Services\Offers;

use App\Services\Offers\OfferStateMachineService;
use Tests\TestCase;

class OfferStateMachineServiceTest extends TestCase
{
    private OfferStateMachineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OfferStateMachineService();
    }

    /** @test — Case 1: all approved statuses return true from isApprovedStatus */
    public function test_all_approved_statuses_return_true(): void
    {
        foreach (OfferStateMachineService::APPROVED_STATUSES as $status) {
            $this->assertTrue(
                $this->service->isApprovedStatus($status),
                "Expected '{$status}' to be an approved status."
            );
        }
    }

    /** @test — Case 2: unknown status returns false from isApprovedStatus */
    public function test_unknown_status_returns_false_from_is_approved(): void
    {
        $this->assertFalse($this->service->isApprovedStatus('pending'));
        $this->assertFalse($this->service->isApprovedStatus(''));
        $this->assertFalse($this->service->isApprovedStatus('DRAFT'));
    }

    /** @test — Case 3: active statuses are draft, submitted, countered */
    public function test_active_statuses_are_draft_submitted_countered(): void
    {
        $this->assertTrue($this->service->isActiveStatus('draft'));
        $this->assertTrue($this->service->isActiveStatus('submitted'));
        $this->assertTrue($this->service->isActiveStatus('countered'));

        $this->assertFalse($this->service->isActiveStatus('accepted'));
        $this->assertFalse($this->service->isActiveStatus('rejected'));
        $this->assertFalse($this->service->isActiveStatus('withdrawn'));
        $this->assertFalse($this->service->isActiveStatus('expired'));
        $this->assertFalse($this->service->isActiveStatus('cancelled'));
    }

    /** @test — Case 4: final statuses are accepted, rejected, withdrawn, expired, cancelled */
    public function test_final_statuses_are_correct(): void
    {
        $this->assertTrue($this->service->isFinalStatus('accepted'));
        $this->assertTrue($this->service->isFinalStatus('rejected'));
        $this->assertTrue($this->service->isFinalStatus('withdrawn'));
        $this->assertTrue($this->service->isFinalStatus('expired'));
        $this->assertTrue($this->service->isFinalStatus('cancelled'));

        $this->assertFalse($this->service->isFinalStatus('draft'));
        $this->assertFalse($this->service->isFinalStatus('submitted'));
        $this->assertFalse($this->service->isFinalStatus('countered'));
    }

    /** @test — Case 5: all allowed transitions return true from canTransition */
    public function test_all_allowed_transitions_return_true(): void
    {
        $allowedPairs = [
            ['draft',      'submitted'],
            ['submitted',  'countered'],
            ['submitted',  'accepted'],
            ['submitted',  'rejected'],
            ['submitted',  'withdrawn'],
            ['submitted',  'expired'],
            ['countered',  'accepted'],
            ['countered',  'rejected'],
            ['countered',  'countered'],
            ['countered',  'withdrawn'],
            ['countered',  'expired'],
            ['accepted',   'cancelled'],
        ];

        foreach ($allowedPairs as [$from, $to]) {
            $this->assertTrue(
                $this->service->canTransition($from, $to),
                "Expected transition '{$from}' → '{$to}' to be allowed."
            );
        }
    }

    /** @test — Case 6: six specific forbidden transitions return false */
    public function test_six_forbidden_transitions_return_false(): void
    {
        $forbiddenPairs = [
            ['accepted',  'countered'],
            ['accepted',  'rejected'],
            ['rejected',  'accepted'],
            ['withdrawn', 'accepted'],
            ['expired',   'accepted'],
            ['cancelled', 'accepted'],
        ];

        foreach ($forbiddenPairs as [$from, $to]) {
            $this->assertFalse(
                $this->service->canTransition($from, $to),
                "Expected transition '{$from}' → '{$to}' to be forbidden."
            );
        }
    }

    /** @test — Case 7: accepted → cancelled returns true */
    public function test_accepted_to_cancelled_is_allowed(): void
    {
        $this->assertTrue($this->service->canTransition('accepted', 'cancelled'));

        $result = $this->service->validateTransition('accepted', 'cancelled');
        $this->assertTrue($result['allowed']);
    }

    /** @test — Case 8: unknown from-status fails validateTransition */
    public function test_unknown_from_status_fails_validate_transition(): void
    {
        $result = $this->service->validateTransition('unknown_status', 'submitted');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('unknown_status', $result['reason']);
    }

    /** @test — Case 9: unknown to-status fails validateTransition */
    public function test_unknown_to_status_fails_validate_transition(): void
    {
        $result = $this->service->validateTransition('submitted', 'unknown_status');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('unknown_status', $result['reason']);
    }

    /** @test — Case 10: validateTransition returns the required four-key array shape */
    public function test_validate_transition_returns_required_four_key_shape(): void
    {
        $result = $this->service->validateTransition('draft', 'submitted');

        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('from_status', $result);
        $this->assertArrayHasKey('to_status', $result);
        $this->assertArrayHasKey('reason', $result);

        $this->assertEquals('draft', $result['from_status']);
        $this->assertEquals('submitted', $result['to_status']);

        $resultForbidden = $this->service->validateTransition('rejected', 'accepted');
        $this->assertArrayHasKey('allowed', $resultForbidden);
        $this->assertArrayHasKey('from_status', $resultForbidden);
        $this->assertArrayHasKey('to_status', $resultForbidden);
        $this->assertArrayHasKey('reason', $resultForbidden);
        $this->assertFalse($resultForbidden['allowed']);
        $this->assertNotEmpty($resultForbidden['reason']);
    }

    /** @test — Case 6b: forbidden non-final transition returns correct reason string */
    public function test_forbidden_non_final_transition_returns_correct_reason(): void
    {
        $result = $this->service->validateTransition('draft', 'accepted');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('forbidden transition', $result['reason']);
    }

    /** @test — Case 11: service file contains no database write calls */
    public function test_service_file_contains_no_database_write_calls(): void
    {
        $path = app_path('Services/Offers/OfferStateMachineService.php');
        $source = file_get_contents($path);

        $this->assertStringNotContainsString('->save(', $source, 'Service must not call ->save()');
        $this->assertStringNotContainsString('->update(', $source, 'Service must not call ->update()');
        $this->assertStringNotContainsString('->create(', $source, 'Service must not call ->create()');
        $this->assertStringNotContainsString('->delete(', $source, 'Service must not call ->delete()');
        $this->assertStringNotContainsString('::create(', $source, 'Service must not call ::create()');
        $this->assertStringNotContainsString('::update(', $source, 'Service must not call ::update()');
    }

    /** @test — Case 12: service file contains no OfferEventLog usage */
    public function test_service_file_contains_no_offer_event_log_usage(): void
    {
        $path = app_path('Services/Offers/OfferStateMachineService.php');
        $source = file_get_contents($path);

        $this->assertStringNotContainsString('OfferEventLog', $source, 'Service must not reference OfferEventLog');
    }
}
