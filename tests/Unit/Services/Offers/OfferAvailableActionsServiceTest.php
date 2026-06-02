<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferPermissionService;
use Tests\TestCase;

class OfferAvailableActionsServiceTest extends TestCase
{
    private function makePermissionResult(bool $allowed, string $action, string $reason = ''): array
    {
        return ['allowed' => $allowed, 'action' => $action, 'reason' => $reason];
    }

    private function makeAllowedMock(): OfferPermissionService
    {
        $mock = $this->createMock(OfferPermissionService::class);
        $mock->method('canSubmit')->willReturn($this->makePermissionResult(true, 'submit'));
        $mock->method('canCounter')->willReturn($this->makePermissionResult(true, 'counter'));
        $mock->method('canAccept')->willReturn($this->makePermissionResult(true, 'accept'));
        $mock->method('canReject')->willReturn($this->makePermissionResult(true, 'reject'));
        $mock->method('canWithdraw')->willReturn($this->makePermissionResult(true, 'withdraw'));
        $mock->method('canExpire')->willReturn($this->makePermissionResult(true, 'expire'));
        $mock->method('canViewTimeline')->willReturn($this->makePermissionResult(true, 'view_timeline'));
        return $mock;
    }

    private function makeDeniedMock(): OfferPermissionService
    {
        $mock = $this->createMock(OfferPermissionService::class);
        $mock->method('canSubmit')->willReturn($this->makePermissionResult(false, 'submit', 'denied submit'));
        $mock->method('canCounter')->willReturn($this->makePermissionResult(false, 'counter', 'denied counter'));
        $mock->method('canAccept')->willReturn($this->makePermissionResult(false, 'accept', 'denied accept'));
        $mock->method('canReject')->willReturn($this->makePermissionResult(false, 'reject', 'denied reject'));
        $mock->method('canWithdraw')->willReturn($this->makePermissionResult(false, 'withdraw', 'denied withdraw'));
        $mock->method('canExpire')->willReturn($this->makePermissionResult(false, 'expire', 'denied expire'));
        $mock->method('canViewTimeline')->willReturn($this->makePermissionResult(false, 'view_timeline', 'denied view_timeline'));
        return $mock;
    }

    // ── Point 1 & 2: All seven methods called exactly once with same args ──

    public function test_all_seven_permission_methods_are_called_exactly_once_with_same_args(): void
    {
        $offer   = Offer::factory()->make(['status' => 'submitted']);
        $actorId = 42;
        $actorRole = 'buyer';

        $actionMap = [
            'canSubmit'       => 'submit',
            'canCounter'      => 'counter',
            'canAccept'       => 'accept',
            'canReject'       => 'reject',
            'canWithdraw'     => 'withdraw',
            'canExpire'       => 'expire',
            'canViewTimeline' => 'view_timeline',
        ];

        $mock = $this->createMock(OfferPermissionService::class);

        foreach ($actionMap as $method => $action) {
            $mock->expects($this->once())
                ->method($method)
                ->with(
                    $this->identicalTo($offer),
                    $this->identicalTo($actorId),
                    $this->identicalTo($actorRole)
                )
                ->willReturn($this->makePermissionResult(true, $action));
        }

        $service = new OfferAvailableActionsService($mock);
        $service->forOffer($offer, $actorId, $actorRole);
    }

    // ── Point 3: All seven can_* keys are present ─────────────────────────

    public function test_all_seven_can_keys_are_present(): void
    {
        $service = new OfferAvailableActionsService($this->makeAllowedMock());
        $result  = $service->forOffer(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer');

        $this->assertArrayHasKey('can_submit', $result);
        $this->assertArrayHasKey('can_counter', $result);
        $this->assertArrayHasKey('can_accept', $result);
        $this->assertArrayHasKey('can_reject', $result);
        $this->assertArrayHasKey('can_withdraw', $result);
        $this->assertArrayHasKey('can_expire', $result);
        $this->assertArrayHasKey('can_view_timeline', $result);
    }

    // ── Point 4: All can_* values are booleans ────────────────────────────

    public function test_all_can_values_are_booleans(): void
    {
        $service = new OfferAvailableActionsService($this->makeAllowedMock());
        $result  = $service->forOffer(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer');

        $this->assertIsBool($result['can_submit']);
        $this->assertIsBool($result['can_counter']);
        $this->assertIsBool($result['can_accept']);
        $this->assertIsBool($result['can_reject']);
        $this->assertIsBool($result['can_withdraw']);
        $this->assertIsBool($result['can_expire']);
        $this->assertIsBool($result['can_view_timeline']);
    }

    // ── Point 5: All seven reasons keys are present ───────────────────────

    public function test_all_seven_reasons_keys_are_present(): void
    {
        $service = new OfferAvailableActionsService($this->makeAllowedMock());
        $result  = $service->forOffer(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer');

        $this->assertArrayHasKey('reasons', $result);
        $this->assertArrayHasKey('submit', $result['reasons']);
        $this->assertArrayHasKey('counter', $result['reasons']);
        $this->assertArrayHasKey('accept', $result['reasons']);
        $this->assertArrayHasKey('reject', $result['reasons']);
        $this->assertArrayHasKey('withdraw', $result['reasons']);
        $this->assertArrayHasKey('expire', $result['reasons']);
        $this->assertArrayHasKey('view_timeline', $result['reasons']);
    }

    // ── Point 6: All reasons values are strings ───────────────────────────

    public function test_all_reasons_values_are_strings(): void
    {
        $service = new OfferAvailableActionsService($this->makeAllowedMock());
        $result  = $service->forOffer(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer');

        foreach ($result['reasons'] as $key => $value) {
            $this->assertIsString($value, "reasons['{$key}'] must be a string.");
        }
    }

    // ── Point 7: allowed = true maps to can_* = true ─────────────────────

    public function test_allowed_true_maps_to_can_true(): void
    {
        $service = new OfferAvailableActionsService($this->makeAllowedMock());
        $result  = $service->forOffer(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer');

        $this->assertTrue($result['can_submit']);
        $this->assertTrue($result['can_counter']);
        $this->assertTrue($result['can_accept']);
        $this->assertTrue($result['can_reject']);
        $this->assertTrue($result['can_withdraw']);
        $this->assertTrue($result['can_expire']);
        $this->assertTrue($result['can_view_timeline']);
    }

    // ── Point 8: allowed = false maps to can_* = false ───────────────────

    public function test_allowed_false_maps_to_can_false(): void
    {
        $service = new OfferAvailableActionsService($this->makeDeniedMock());
        $result  = $service->forOffer(Offer::factory()->make(['status' => 'draft']), 1, 'seller');

        $this->assertFalse($result['can_submit']);
        $this->assertFalse($result['can_counter']);
        $this->assertFalse($result['can_accept']);
        $this->assertFalse($result['can_reject']);
        $this->assertFalse($result['can_withdraw']);
        $this->assertFalse($result['can_expire']);
        $this->assertFalse($result['can_view_timeline']);
    }

    // ── Point 10: Static scan — no forbidden tokens in service file ───────

    public function test_service_file_contains_no_write_or_forbidden_references(): void
    {
        $path   = app_path('Services/Offers/OfferAvailableActionsService.php');
        $source = file_get_contents($path);

        $forbidden = [
            '->save(',
            '->update(',
            '->create(',
            'DB::',
            'OfferEventLog',
            'OfferSubmissionService',
            'OfferCounterService',
            'OfferDecisionService',
            'OfferExpirationService',
            'OfferWorkflowFacade',
        ];

        foreach ($forbidden as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source,
                "OfferAvailableActionsService must not reference '{$token}'."
            );
        }
    }
}
