<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferPermissionService;
use App\Services\Offers\OfferStateMachineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferPermissionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OfferPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OfferPermissionService();
    }

    /**
     * Create a persisted offer/auction pair where $actor is the listing owner (not the
     * submitter of the offer).  This satisfies getLegitimatePartyIds() while passing the
     * identity (submitter) guard, so canAccept/canReject/canCounter return allowed=true
     * for the actor.  Returns [$offer, $actor->id] so callers can pass the real actor ID.
     */
    private function partyOffer(string $status): array
    {
        $actor      = User::factory()->create(['user_type' => 'seller']);
        $submitter  = User::factory()->create(['user_type' => 'buyer']);
        $auction    = OfferAuction::factory()->create(['user_id' => $actor->id]);

        $offer = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $submitter->id,
            'status'           => $status,
        ]);

        return [$offer, $actor->id];
    }

    // ── canSubmit ──────────────────────────────────────────────────────────

    public function test_can_submit_allowed_for_draft_buyer(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 1]);
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

    public function test_can_submit_allowed_for_draft_tenant_owner(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 5]);
        $result = $this->service->canSubmit($offer, 5, 'tenant');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_submit_allowed_for_draft_seller_owner(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 5]);
        $result = $this->service->canSubmit($offer, 5, 'seller');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_submit_allowed_for_draft_landlord_owner(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 5]);
        $result = $this->service->canSubmit($offer, 5, 'landlord');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_submit_allowed_for_draft_agent_owner(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 5]);
        $result = $this->service->canSubmit($offer, 5, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_submit_denied_for_non_owner(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 10]);
        $result = $this->service->canSubmit($offer, 99, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('creator', $result['reason']);
    }

    public function test_can_submit_denied_for_unauthenticated(): void
    {
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 10]);
        $result = $this->service->canSubmit($offer, null, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('authenticated', $result['reason']);
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
        $offer = Offer::factory()->make(['status' => 'draft', 'user_id' => 1]);
        $result = $this->service->canSubmit($offer, 1, 'buyer');

        $this->assertIsBool($result['allowed']);
        $this->assertIsString($result['action']);
        $this->assertIsString($result['reason']);
    }

    // ── canCounter ─────────────────────────────────────────────────────────

    public function test_can_counter_allowed_for_submitted_agent(): void
    {
        [$offer, $actorId] = $this->partyOffer('submitted');
        $result = $this->service->canCounter($offer, $actorId, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('counter', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_counter_allowed_for_countered_seller(): void
    {
        [$offer, $actorId] = $this->partyOffer('countered');
        $result = $this->service->canCounter($offer, $actorId, 'seller');

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
        [$offer, $actorId] = $this->partyOffer('submitted');
        $result = $this->service->canAccept($offer, $actorId, 'seller');

        $this->assertTrue($result['allowed']);
        $this->assertSame('accept', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_accept_allowed_for_countered_agent(): void
    {
        [$offer, $actorId] = $this->partyOffer('countered');
        $result = $this->service->canAccept($offer, $actorId, 'agent');

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
        // 'buyer' is now a permitted role for canAccept (supports counter-reversal where
        // buyer must accept the seller's counter).  Use 'tenant' — genuinely not in the
        // allowed-role list — to exercise the role-denial branch.
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canAccept($offer, 1, 'tenant');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('tenant', $result['reason']);
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
        [$offer, $actorId] = $this->partyOffer('submitted');
        $result = $this->service->canReject($offer, $actorId, 'agent');

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
        // 'buyer' is now a permitted role for canReject (supports counter-reversal).
        // Use 'tenant' — genuinely not in the allowed-role list — to test the role-denial branch.
        $offer = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canReject($offer, 1, 'tenant');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('tenant', $result['reason']);
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
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 1]);
        $result = $this->service->canWithdraw($offer, 1, 'buyer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('withdraw', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_allowed_for_submitted_tenant_creator(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 5]);
        $result = $this->service->canWithdraw($offer, 5, 'tenant');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_allowed_for_submitted_landlord_creator(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 5]);
        $result = $this->service->canWithdraw($offer, 5, 'landlord');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_allowed_for_submitted_seller_creator(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 5]);
        $result = $this->service->canWithdraw($offer, 5, 'seller');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_allowed_for_submitted_agent_creator(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 5]);
        $result = $this->service->canWithdraw($offer, 5, 'agent');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_withdraw_allowed_for_countered_creator(): void
    {
        $offer = Offer::factory()->make(['status' => 'countered', 'user_id' => 7]);
        $result = $this->service->canWithdraw($offer, 7, 'tenant');

        $this->assertTrue($result['allowed']);
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

    public function test_can_withdraw_denied_for_non_owner(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 10]);
        $result = $this->service->canWithdraw($offer, 99, 'seller');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('creator', $result['reason']);
    }

    public function test_can_withdraw_denied_for_unauthenticated(): void
    {
        $offer = Offer::factory()->make(['status' => 'submitted', 'user_id' => 10]);
        $result = $this->service->canWithdraw($offer, null, 'tenant');

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason']);
        $this->assertStringContainsString('authenticated', $result['reason']);
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

    public function test_can_view_timeline_allowed_for_listing_owner(): void
    {
        [$offer, $actorId] = $this->partyOffer('submitted');
        $result = $this->service->canViewTimeline($offer, $actorId, 'seller');

        $this->assertTrue($result['allowed']);
        $this->assertSame('view_timeline', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_view_timeline_allowed_for_root_submitter(): void
    {
        $submitter = User::factory()->create(['user_type' => 'tenant']);
        $auction   = OfferAuction::factory()->create();
        $offer     = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $submitter->id,
            'status'           => 'submitted',
        ]);

        $result = $this->service->canViewTimeline($offer, $submitter->id, 'tenant');

        $this->assertTrue($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_view_timeline_allowed_for_system(): void
    {
        $offer  = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canViewTimeline($offer, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('view_timeline', $result['action']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_view_timeline_denied_for_non_party(): void
    {
        [$offer] = $this->partyOffer('submitted');
        $stranger = User::factory()->create();

        $result = $this->service->canViewTimeline($offer, $stranger->id, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertSame('', $result['reason']);
    }

    public function test_can_view_timeline_denied_for_unauthenticated(): void
    {
        $offer  = Offer::factory()->make(['status' => 'submitted']);
        $result = $this->service->canViewTimeline($offer, null, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertSame('', $result['reason']);
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
        // canCounter/canAccept/canReject require the actor to be a legitimate party (listing
        // owner or root submitter).  Use partyOffer() to create persisted records so the
        // party check passes for the returned actor ID.
        [$counterOffer, $counterId] = $this->partyOffer('submitted');
        [$acceptOffer,  $acceptId]  = $this->partyOffer('submitted');
        [$rejectOffer,  $rejectId]  = $this->partyOffer('submitted');

        [$viewTimelineOffer, $viewTimelineActorId] = $this->partyOffer('submitted');

        $checks = [
            fn () => $this->service->canSubmit(Offer::factory()->make(['status' => 'draft', 'user_id' => 1]), 1, 'buyer'),
            fn () => $this->service->canCounter($counterOffer, $counterId, 'buyer'),
            fn () => $this->service->canAccept($acceptOffer,  $acceptId,  'seller'),
            fn () => $this->service->canReject($rejectOffer,  $rejectId,  'seller'),
            fn () => $this->service->canWithdraw(Offer::factory()->make(['status' => 'submitted', 'user_id' => 1]), 1, 'buyer'),
            fn () => $this->service->canExpire(Offer::factory()->make(['status' => 'submitted']), null, 'system'),
            fn () => $this->service->canViewTimeline($viewTimelineOffer, $viewTimelineActorId, 'seller'),
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
        ];

        foreach ($checks as $check) {
            $result = $check();
            $this->assertFalse($result['allowed']);
            $this->assertNotEmpty($result['reason'], 'Denied results must have a non-empty reason string.');
        }
    }

    // ── Multi-round counter chain (A → B → C) ─────────────────────────────

    /**
     * Build a 3-node persisted chain:
     *   A = root offer (status 'countered'),  submitted by $buyer
     *   B = first counter (status 'countered'), created by $seller (listing owner)
     *   C = second counter (status 'countered'), created by $buyer
     *
     * Returns [$offerA, $offerB, $offerC, $buyerId, $sellerId].
     */
    private function threeNodeChain(): array
    {
        $buyer  = User::factory()->create(['user_type' => 'buyer']);
        $seller = User::factory()->create(['user_type' => 'seller']);
        $auction = OfferAuction::factory()->create(['user_id' => $seller->id]);

        $offerA = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $buyer->id,
            'status'           => 'countered',
            'parent_offer_id'  => null,
        ]);

        $offerB = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $seller->id,
            'status'           => 'countered',
            'parent_offer_id'  => $offerA->id,
        ]);

        $offerC = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $buyer->id,
            'status'           => 'countered',
            'parent_offer_id'  => $offerB->id,
        ]);

        return [$offerA, $offerB, $offerC, $buyer->id, $seller->id];
    }

    // The recipient of C (seller = listing owner) can accept.
    public function test_multi_round_recipient_of_leaf_can_accept(): void
    {
        [, , $offerC, , $sellerId] = $this->threeNodeChain();

        $result = $this->service->canAccept($offerC, $sellerId, 'seller');

        $this->assertTrue($result['allowed'], 'Recipient of the leaf counter must be able to accept.');
        $this->assertSame('', $result['reason']);
    }

    // The recipient of C (seller = listing owner) can reject.
    public function test_multi_round_recipient_of_leaf_can_reject(): void
    {
        [, , $offerC, , $sellerId] = $this->threeNodeChain();

        $result = $this->service->canReject($offerC, $sellerId, 'seller');

        $this->assertTrue($result['allowed'], 'Recipient of the leaf counter must be able to reject.');
        $this->assertSame('', $result['reason']);
    }

    // The recipient of C (seller = listing owner) can counter.
    public function test_multi_round_recipient_of_leaf_can_counter(): void
    {
        [, , $offerC, , $sellerId] = $this->threeNodeChain();

        $result = $this->service->canCounter($offerC, $sellerId, 'seller');

        $this->assertTrue($result['allowed'], 'Recipient of the leaf counter must be able to counter.');
        $this->assertSame('', $result['reason']);
    }

    // The sender of C (buyer) is blocked from acting on C (must wait).
    public function test_multi_round_sender_of_leaf_is_blocked(): void
    {
        [, , $offerC, $buyerId] = $this->threeNodeChain();

        $accept  = $this->service->canAccept($offerC,  $buyerId, 'buyer');
        $reject  = $this->service->canReject($offerC,  $buyerId, 'buyer');
        $counter = $this->service->canCounter($offerC, $buyerId, 'buyer');

        $this->assertFalse($accept['allowed'],  'Sender of leaf C must not be able to accept.');
        $this->assertFalse($reject['allowed'],  'Sender of leaf C must not be able to reject.');
        $this->assertFalse($counter['allowed'], 'Sender of leaf C must not be able to counter.');

        $this->assertStringContainsString('wait', $accept['reason']);
        $this->assertStringContainsString('wait', $reject['reason']);
        $this->assertStringContainsString('wait', $counter['reason']);
    }

    // Stale-parent guard fires on A: it has non-final child B.
    public function test_multi_round_stale_parent_a_is_blocked(): void
    {
        [$offerA, , , $buyerId, $sellerId] = $this->threeNodeChain();

        foreach ([$buyerId, $sellerId] as $actorId) {
            $role = $actorId === $buyerId ? 'buyer' : 'seller';
            $this->assertFalse(
                $this->service->canAccept($offerA, $actorId, $role)['allowed'],
                "Stale parent A must block accept for role '{$role}'."
            );
            $this->assertFalse(
                $this->service->canReject($offerA, $actorId, $role)['allowed'],
                "Stale parent A must block reject for role '{$role}'."
            );
            $this->assertFalse(
                $this->service->canCounter($offerA, $actorId, $role)['allowed'],
                "Stale parent A must block counter for role '{$role}'."
            );
        }
    }

    // Stale-parent guard fires on B: it has non-final child C.
    public function test_multi_round_stale_parent_b_is_blocked(): void
    {
        [, $offerB, , $buyerId, $sellerId] = $this->threeNodeChain();

        foreach ([$buyerId, $sellerId] as $actorId) {
            $role = $actorId === $buyerId ? 'buyer' : 'seller';
            $this->assertFalse(
                $this->service->canAccept($offerB, $actorId, $role)['allowed'],
                "Stale parent B must block accept for role '{$role}'."
            );
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
