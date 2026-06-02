<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Services\Offers\OfferPermissionService;
use App\Services\Offers\OfferStateMachineService;
use Tests\TestCase;

class OfferPermissionServiceTest extends TestCase
{
    private OfferPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OfferPermissionService();
    }

    // ── canSubmit ──────────────────────────────────────────────────────────

    public function test_can_submit_allowed_for_draft_buyer(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canSubmit($offer, 1, 'buyer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('submit', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_submit_allowed_for_draft_system(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canSubmit($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_submit_denied_for_final_status(): void
    {
        foreach (OfferStateMachineService::FINAL_STATUSES as $status) {
            $offer = Offer::factory()->make(['status' => $status]);
            $result = $this->service->canSubmit($offer, 1, 'buyer');

            $this->assertFalse($result['allowed'], "Expected denial for final status '{$status}'.");
            $this->assertNotEmpty($result['reason']);
            $this->assertStringContainsString($status, $result['reason']);
        }
    }

    public function test_can_submit_denied_for_wrong_role(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canSubmit($offer, 1, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('seller', $result['reason']);
    }

    public function test_can_submit_denied_for_wrong_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canSubmit($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('submitted', $result['reason']);
    }

    public function test_can_submit_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canSubmit($offer, 1, 'buyer');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canCounter ─────────────────────────────────────────────────────────

    public function test_can_counter_allowed_for_submitted_agent(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canCounter($offer, 1, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('counter', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_counter_allowed_for_countered_seller(): void
    {
        $offer = Offer::factory()->make(['status' => 'countered']);
        $result = $this->service->canCounter($offer, 1, 'seller');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_counter_denied_for_final_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'accepted']);
        $result = $this->service->canCounter($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('accepted', $result['reason']);
    }

    public function test_can_counter_denied_for_wrong_role(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canCounter($offer, 1, 'tenant');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('tenant', $result['reason']);
    }

    public function test_can_counter_denied_for_wrong_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canCounter($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('draft', $result['reason']);
    }

    public function test_can_counter_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canCounter($offer, 1, 'buyer');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canAccept ──────────────────────────────────────────────────────────

    public function test_can_accept_allowed_for_submitted_seller(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canAccept($offer, 1, 'seller');

        $this->assertTrue($result['allowed']);
        $this->assertSame('accept', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_accept_allowed_for_countered_agent(): void
    {
        $offer = Offer::factory()->make(['status' => 'countered']);
        $result = $this->service->canAccept($offer, 1, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_accept_denied_for_final_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'rejected']);
        $result = $this->service->canAccept($offer, 1, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('rejected', $result['reason']);
    }

    public function test_can_accept_denied_for_wrong_role(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canAccept($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('buyer', $result['reason']);
    }

    public function test_can_accept_denied_for_wrong_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canAccept($offer, 1, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('draft', $result['reason']);
    }

    public function test_can_accept_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canAccept($offer, 1, 'seller');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canReject ──────────────────────────────────────────────────────────

    public function test_can_reject_allowed_for_submitted_agent(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canReject($offer, 1, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('reject', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_reject_allowed_for_countered_system(): void
    {
        $offer = Offer::factory()->make(['status' => 'countered']);
        $result = $this->service->canReject($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_reject_denied_for_final_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'withdrawn']);
        $result = $this->service->canReject($offer, 1, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('withdrawn', $result['reason']);
    }

    public function test_can_reject_denied_for_wrong_role(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canReject($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('buyer', $result['reason']);
    }

    public function test_can_reject_denied_for_wrong_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canReject($offer, 1, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('draft', $result['reason']);
    }

    public function test_can_reject_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canReject($offer, 1, 'seller');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canWithdraw ────────────────────────────────────────────────────────

    public function test_can_withdraw_allowed_for_submitted_buyer(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canWithdraw($offer, 1, 'buyer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('withdraw', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_allowed_for_countered_system(): void
    {
        $offer = Offer::factory()->make(['status' => 'countered']);
        $result = $this->service->canWithdraw($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_denied_for_final_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'expired']);
        $result = $this->service->canWithdraw($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('expired', $result['reason']);
    }

    public function test_can_withdraw_denied_for_wrong_role(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canWithdraw($offer, 1, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('seller', $result['reason']);
    }

    public function test_can_withdraw_denied_for_wrong_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canWithdraw($offer, 1, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('draft', $result['reason']);
    }

    public function test_can_withdraw_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canWithdraw($offer, 1, 'buyer');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canExpire ──────────────────────────────────────────────────────────

    public function test_can_expire_allowed_for_submitted_system(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canExpire($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('expire', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_expire_allowed_for_countered_system(): void
    {
        $offer = Offer::factory()->make(['status' => 'countered']);
        $result = $this->service->canExpire($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_expire_denied_for_final_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'cancelled']);
        $result = $this->service->canExpire($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('cancelled', $result['reason']);
    }

    public function test_can_expire_denied_for_non_system_role(): void
    {
        foreach (['buyer', 'seller', 'agent'] as $role) {
            $offer = Offer::factory()->make(['status' => 'submitted']);
            $result = $this->service->canExpire($offer, 1, $role);

            $this->assertFalse($result['allowed'], "Expected denial for role '{$role}'.");
            $this->assertNotEmpty($result['reason']);
            $this->assertStringContainsString($role, $result['reason']);
        }
    }

    public function test_can_expire_denied_for_wrong_status(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canExpire($offer, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('draft', $result['reason']);
    }

    public function test_can_expire_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canExpire($offer, null, 'system');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canViewTimeline ────────────────────────────────────────────────────

    public function test_can_view_timeline_allowed_for_any_status_and_permitted_roles(): void
    {
        $statuses = OfferStateMachineService::APPROVED_STATUSES;
        $roles = ['buyer', 'seller', 'agent', 'system'];

        foreach ($statuses as $status) {
            foreach ($roles as $role) {
                $offer = Offer::factory()->make(['status' => $status]);
                $result = $this->service->canViewTimeline($offer, 1, $role);

                $this->assertTrue($result['allowed'], "Expected allowed for status '{$status}', role '{$role}'.");
                $this->assertSame('view_timeline', $result['action']);
                $this->assertSame('', $result['reason']);
            }
        }
    }

    public function test_can_view_timeline_denied_for_unknown_role(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canViewTimeline($offer, 1, 'stranger');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('stranger', $result['reason']);
    }

    public function test_can_view_timeline_return_shape(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft']);
        $result = $this->service->canViewTimeline($offer, 1, 'buyer');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── Cross-cutting: allowed always has empty reason ─────────────────────

    public function test_allowed_results_always_have_empty_reason(): void
    {
        $checks = [
            fn () => $this->service->canSubmit(Offer::factory()->make(['status' => 'draft']), 1, 'buyer'),
            fn () => $this->service->canCounter(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer'),
            fn () => $this->service->canAccept(Offer::factory()->make(['status' => 'submitted']), 1, 'seller'),
            fn () => $this->service->canReject(Offer::factory()->make(['status' => 'submitted']), 1, 'seller'),
            fn () => $this->service->canWithdraw(Offer::factory()->make(['status' => 'submitted']), 1, 'buyer'),
            fn () => $this->service->canExpire(Offer::factory()->make(['status' => 'submitted']), null, 'system'),
            fn () => $this->service->canViewTimeline(Offer::factory()->make(['status' => 'accepted']), 1, 'agent'),
        ];

        foreach ($checks as $check) {
            $result = $check();
            $this->assertTrue($result['allowed']);
            $this->assertSame('', $result['reason'], 'Allowed results must have an empty reason string.');
        }
    }

    // ── Cross-cutting: denied always has non-empty reason ──────────────────

    public function test_denied_results_always_have_non_empty_reason(): void
    {
        $checks = [
            fn () => $this->service->canSubmit(Offer::factory()->make(['status' => 'accepted']), 1, 'buyer'),
            fn () => $this->service->canCounter(Offer::factory()->make(['status' => 'draft']), 1, 'buyer'),
            fn () => $this->service->canAccept(Offer::factory()->make(['status' => 'draft']), 1, 'seller'),
            fn () => $this->service->canReject(Offer::factory()->make(['status' => 'draft']), 1, 'seller'),
            fn () => $this->service->canWithdraw(Offer::factory()->make(['status' => 'draft']), 1, 'buyer'),
            fn () => $this->service->canExpire(Offer::factory()->make(['status' => 'draft']), null, 'system'),
            fn () => $this->service->canViewTimeline(Offer::factory()->make(['status' => 'draft']), 1, 'intruder'),
        ];

        foreach ($checks as $check) {
            $result = $check();
            $this->assertFalse($result['allowed']);
            $this->assertNotEmpty($result['reason'], 'Denied results must have a non-empty reason string.');
        }
    }

    // ── Static no-write scan ───────────────────────────────────────────────

    public function test_service_file_contains_no_write_or_forbidden_references(): void
    {
        $path = app_path('Services/Offers/OfferPermissionService.php');
        $source = file_get_contents($path);

        $forbidden = [
            'DB::',
            '->save(',
            '->update(',
            '->create(',
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
                "OfferPermissionService must not reference '{$token}'."
            );
        }
    }
}
