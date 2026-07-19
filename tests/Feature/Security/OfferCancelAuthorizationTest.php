<?php

namespace Tests\Feature\Security;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferEventLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * B2.1B — adversarial authorization for accepted-offer cancellation.
 *
 * Uses the REAL permission/facade stack (no mocking): only the listing owner and
 * platform admins may cancel an accepted offer; the submitter and unrelated users
 * may not. A reason is required and is trimmed + capped at 1000 chars.
 */
class OfferCancelAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $submitter;
    private User $admin;
    private User $stranger;
    private OfferAuction $auction;
    private Offer $accepted;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->owner     = User::factory()->create(['user_type' => 'seller']);
        $this->submitter = User::factory()->create(['user_type' => 'buyer']);
        $this->admin     = User::factory()->create(['user_type' => 'admin']);
        $this->stranger  = User::factory()->create(['user_type' => 'buyer']);

        $this->auction  = OfferAuction::factory()->create(['user_id' => $this->owner->id]);
        $this->accepted = Offer::factory()->accepted()->create([
            'user_id'          => $this->submitter->id,
            'offer_auction_id' => $this->auction->id,
        ]);

        // Grant offer-playoff access to the non-admin actors so the middleware Gate
        // is passed and the canCancel permission layer is what actually decides.
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [
            $this->owner->id, $this->submitter->id, $this->stranger->id,
        ]);
    }

    public function test_listing_owner_can_cancel(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('offers.cancel', $this->accepted), ['reason' => 'Deal collapsed'])
            ->assertOk();

        $this->assertSame('cancelled', Offer::find($this->accepted->id)->status);
    }

    public function test_admin_can_cancel(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('offers.cancel', $this->accepted), ['reason' => 'Admin override'])
            ->assertOk();

        $this->assertSame('cancelled', Offer::find($this->accepted->id)->status);
    }

    public function test_submitter_cannot_cancel(): void
    {
        $this->actingAs($this->submitter)
            ->postJson(route('offers.cancel', $this->accepted), ['reason' => 'let me out'])
            ->assertStatus(422);

        $this->assertSame('accepted', Offer::find($this->accepted->id)->status);
    }

    public function test_unrelated_user_cannot_cancel(): void
    {
        $this->actingAs($this->stranger)
            ->postJson(route('offers.cancel', $this->accepted), ['reason' => 'meddling'])
            ->assertStatus(422);

        $this->assertSame('accepted', Offer::find($this->accepted->id)->status);
    }

    public function test_cannot_cancel_non_accepted_offer(): void
    {
        $submitted = Offer::factory()->submitted()->create([
            'user_id'          => $this->submitter->id,
            'offer_auction_id' => $this->auction->id,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('offers.cancel', $submitted), ['reason' => 'too early'])
            ->assertStatus(422);

        $this->assertSame('submitted', Offer::find($submitted->id)->status);
    }

    public function test_reason_is_required(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('offers.cancel', $this->accepted), [])
            ->assertStatus(422);

        $this->assertSame('accepted', Offer::find($this->accepted->id)->status);
    }

    public function test_whitespace_only_reason_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('offers.cancel', $this->accepted), ['reason' => "   \n\t  "])
            ->assertStatus(422);

        $this->assertSame('accepted', Offer::find($this->accepted->id)->status);
    }

    public function test_reason_is_trimmed_and_capped_at_1000_chars(): void
    {
        $rawReason = '   ' . str_repeat('A', 1500) . '   ';

        $this->actingAs($this->owner)
            ->postJson(route('offers.cancel', $this->accepted), ['reason' => $rawReason])
            ->assertOk();

        $log = OfferEventLog::where('offer_id', $this->accepted->id)
            ->where('event_type', 'offer_cancelled')
            ->first();
        $this->assertNotNull($log);

        $meta   = is_array($log->metadata) ? $log->metadata : (array) json_decode($log->metadata, true);
        $stored = $meta['reason'] ?? '';

        $this->assertSame(1000, mb_strlen($stored), 'reason must be capped at 1000 chars');
        $this->assertSame(str_repeat('A', 1000), $stored, 'reason must be trimmed before capping');
    }
}
