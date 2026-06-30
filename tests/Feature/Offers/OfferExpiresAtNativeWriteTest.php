<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * BYA-H6 — submitting an offer with terms must write the *native* offers.expires_at
 * column (not only offer_metas). ExpireOffersCommand filters the native column, so
 * before this fix submitted offers never expired (native column stayed NULL).
 *
 * These tests drive the real submit endpoint (OfferController::submit ->
 * persistTermsMeta) and assert the native column is populated, then prove such an
 * offer is actually expired by the scheduled command.
 */
class OfferExpiresAtNativeWriteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $listingOwner;
    private Offer $draftOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user         = User::factory()->create(['user_type' => 'seller']);
        $this->listingOwner = User::factory()->create(['user_type' => 'seller']);

        $auction = OfferAuction::factory()->create(['user_id' => $this->listingOwner->id]);

        // role=seller => sale offer (resolveOfferType) and skips the buyer/tenant
        // property-section requirement, keeping the required-terms set minimal.
        $this->draftOffer = Offer::factory()->create([
            'user_id'          => $this->user->id,
            'offer_auction_id' => $auction->id,
            'role'             => 'seller',
            'status'           => 'draft',
            'expires_at'       => null,
        ]);
    }

    private function actingAsAllowedUser(): static
    {
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [$this->user->id]);

        return $this->actingAs($this->user);
    }

    private function allowSubmit(): void
    {
        $mock = $this->createMock(OfferAvailableActionsService::class);
        $mock->method('forOffer')->willReturn([
            'can_submit'        => true,
            'can_counter'       => true,
            'can_accept'        => true,
            'can_reject'        => true,
            'can_withdraw'      => true,
            'can_expire'        => false,
            'can_view_timeline' => true,
            'reasons'           => [
                'submit' => '', 'counter' => '', 'accept' => '', 'reject' => '',
                'withdraw' => '', 'expire' => 'Only system may expire.', 'view_timeline' => '',
            ],
        ]);
        $this->app->instance(OfferAvailableActionsService::class, $mock);
    }

    /** Minimal valid sale-terms payload for a submit-with-terms request. */
    private function saleTermsPayload(string $expiresAt): array
    {
        return [
            '_offer_terms_present' => 1,
            'offer_price'          => '480000',
            'financing_type'       => 'Cash',
            'closing_date'         => now()->addDays(30)->toDateString(),
            'expires_at'           => $expiresAt,
        ];
    }

    // ── 1. Submit writes the NATIVE expires_at column ────────────────────────

    public function test_submit_persists_native_expires_at_column(): void
    {
        Notification::fake();
        $this->allowSubmit();

        $expiresAt = now()->addDays(3)->toDateString();

        $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer), $this->saleTermsPayload($expiresAt))
            ->assertOk();

        $fresh = $this->draftOffer->fresh();

        // Native column (not just meta) must be populated.
        $this->assertNotNull($fresh->expires_at, 'Native offers.expires_at must be set on submit.');
        $this->assertSame($expiresAt, $fresh->expires_at->toDateString());
        // Meta write is unchanged / still present.
        $this->assertSame($expiresAt, $fresh->getMeta('expires_at'));
    }

    // ── 2. End-to-end: submitted offer with past response date is expired ────

    public function test_submitted_offer_with_past_expiry_is_expired_by_command(): void
    {
        Notification::fake();
        $this->allowSubmit();

        // Submit normally; the real workflow facade transitions the offer to
        // submitted and the controller writes the native expires_at deadline.
        $this->actingAsAllowedUser()
            ->postJson(route('offers.submit', $this->draftOffer), $this->saleTermsPayload(now()->addDays(3)->toDateString()))
            ->assertOk();

        $submitted = $this->draftOffer->fresh();
        $this->assertNotNull($submitted->expires_at, 'Native expires_at must be written on submit.');
        $this->assertContains($submitted->status, ['submitted', 'countered']);

        // Advance the clock past that actual native deadline (the command filters the
        // native offers.expires_at column). Before the BYA-H6 fix the column was NULL,
        // so no amount of elapsed time would have made the command expire the offer.
        $this->travelTo($submitted->expires_at->copy()->addHour());

        $this->artisan('offers:expire-pending')->assertExitCode(0);

        // The submitted offer, whose native expires_at is now in the past, is expired.
        $this->assertSame('expired', $this->draftOffer->fresh()->status);
    }
}
