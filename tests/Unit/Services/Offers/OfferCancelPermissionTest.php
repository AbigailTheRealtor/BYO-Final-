<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferPermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * B2.1B — OfferPermissionService::canCancel().
 *
 * Only an accepted offer may be cancelled, and only by the listing owner, a
 * platform admin, or system. The offer submitter (buyer/tenant) may not cancel.
 */
class OfferCancelPermissionTest extends TestCase
{
    use DatabaseTransactions;

    private OfferPermissionService $permissions;
    private User $owner;
    private User $submitter;
    private Offer $accepted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = new OfferPermissionService();

        $this->owner     = User::factory()->create();
        $this->submitter = User::factory()->create();

        $auction = OfferAuction::factory()->create(['user_id' => $this->owner->id]);
        $this->accepted = Offer::factory()->accepted()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $this->submitter->id,
        ]);
    }

    public function test_listing_owner_may_cancel(): void
    {
        $this->assertTrue($this->permissions->canCancel($this->accepted, $this->owner->id, 'seller')['allowed']);
    }

    public function test_admin_may_cancel(): void
    {
        $stranger = User::factory()->create();
        $this->assertTrue($this->permissions->canCancel($this->accepted, $stranger->id, 'admin')['allowed']);
    }

    public function test_system_may_cancel(): void
    {
        $this->assertTrue($this->permissions->canCancel($this->accepted, null, 'system')['allowed']);
    }

    public function test_submitter_may_not_cancel(): void
    {
        $result = $this->permissions->canCancel($this->accepted, $this->submitter->id, 'buyer');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('only the listing owner or a platform admin', $result['reason']);
    }

    public function test_unrelated_user_may_not_cancel(): void
    {
        $stranger = User::factory()->create();
        $this->assertFalse($this->permissions->canCancel($this->accepted, $stranger->id, 'buyer')['allowed']);
    }

    public function test_non_accepted_offer_may_not_be_cancelled(): void
    {
        $auction   = OfferAuction::factory()->create(['user_id' => $this->owner->id]);
        $submitted = Offer::factory()->submitted()->create(['offer_auction_id' => $auction->id]);

        // Even the owner cannot cancel a non-accepted offer.
        $result = $this->permissions->canCancel($submitted, $this->owner->id, 'seller');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString("expected 'accepted'", $result['reason']);
    }
}
