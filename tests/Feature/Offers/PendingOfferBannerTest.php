<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\OfferCounterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The pending banner on the shared offer-detail page must name what the offer
 * actually is.
 *
 * The defect: the "Your counter offer is pending" copy was hardcoded for every
 * pending offer whose sender was viewing it, so an ORIGINAL submission read as a
 * counteroffer. Production carried 41 originals with status 'countered' — every
 * one of them mislabelled, because countering B sets B's status to 'countered'
 * while B keeps a null parent and stays an original.
 *
 * The rule under test: parent_offer_id is the sole discriminator.
 */
class PendingOfferBannerTest extends TestCase
{
    use DatabaseTransactions;

    private const ORIGINAL_COPY = 'Your offer is pending';
    private const COUNTER_COPY  = 'Your counter offer is pending';

    /** Build an original (parent-less) offer owned by $user. */
    private function originalOffer(User $user, string $role, string $status = 'submitted'): Offer
    {
        return Offer::factory()->create([
            'user_id'         => $user->id,
            'role'            => $role,
            'status'          => $status,
            'parent_offer_id' => null,
            'submitted_at'    => now(),
        ]);
    }

    /** Build a genuine counteroffer through the canonical service. */
    private function counterOffer(User $user, string $role): Offer
    {
        $parent = $this->originalOffer($user, $role);

        $result = $this->app->make(OfferCounterService::class)->counter(
            parent: $parent,
            actorId: $user->id,
            actorRole: $role,
        );

        return $result['counter_offer'];
    }

    // ── 1 & 2: original submitted offers read as originals ───────────────────

    /**
     * Regression fixture for production offer 33750 — seller role, status
     * submitted, parent_offer_id NULL, viewed by its sender.
     */
    public function test_original_seller_submitted_offer_renders_original_copy(): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'seller');

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee(self::ORIGINAL_COPY);
        $response->assertDontSee(self::COUNTER_COPY);
    }

    /**
     * Regression fixture for production offer 33751 — landlord/rental role,
     * status submitted, parent_offer_id NULL, viewed by its sender.
     */
    public function test_original_landlord_submitted_offer_renders_original_copy(): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'landlord');

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertSee(self::ORIGINAL_COPY);
        $response->assertDontSee(self::COUNTER_COPY);
    }

    // ── 3 & 4: genuine counteroffers read as counters ────────────────────────

    public function test_genuine_seller_counteroffer_renders_counter_copy(): void
    {
        $user  = User::factory()->create();
        $child = $this->counterOffer($user, 'seller');

        $this->assertNotNull($child->parent_offer_id);

        $response = $this->actingAs($user)->get(route('offers.show', $child));

        $response->assertStatus(200);
        $response->assertSee(self::COUNTER_COPY);
    }

    public function test_genuine_landlord_counteroffer_renders_counter_copy(): void
    {
        $user  = User::factory()->create();
        $child = $this->counterOffer($user, 'landlord');

        $this->assertNotNull($child->parent_offer_id);

        $response = $this->actingAs($user)->get(route('offers.show', $child));

        $response->assertStatus(200);
        $response->assertSee(self::COUNTER_COPY);
    }

    // ── 5: an offer_submitted event never implies a counter ──────────────────

    public function test_offer_submitted_event_alone_does_not_imply_counteroffer(): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'seller');

        \App\Models\OfferEventLog::create([
            'offer_id'   => $offer->id,
            'actor_id'   => $user->id,
            'event_type' => 'offer_submitted',
            'to_status'  => 'submitted',
        ]);

        $this->assertFalse($offer->fresh()->isCounterOffer());

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertSee(self::ORIGINAL_COPY);
        $response->assertDontSee(self::COUNTER_COPY);
    }

    // ── 6: being the sender never implies a counter ──────────────────────────

    public function test_sender_ownership_alone_does_not_imply_counteroffer(): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'seller');

        $this->assertSame((int) $user->id, (int) $offer->user_id);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertSee(self::ORIGINAL_COPY);
        $response->assertDontSee(self::COUNTER_COPY);
    }

    /**
     * The trap this defect fell into: status 'countered' on an ORIGINAL means the
     * other party countered it, not that this row is a counter.
     */
    public function test_countered_status_on_an_original_is_not_a_counteroffer(): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'seller', 'countered');

        $this->assertFalse($offer->isCounterOffer());
    }

    // ── 7 & 8: the model rule itself ─────────────────────────────────────────

    public function test_offer_without_parent_is_not_a_counteroffer(): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'seller');

        $this->assertNull($offer->parent_offer_id);
        $this->assertFalse($offer->isCounterOffer());
    }

    public function test_offer_with_parent_is_a_counteroffer(): void
    {
        $user  = User::factory()->create();
        $child = $this->counterOffer($user, 'seller');

        $this->assertNotNull($child->parent_offer_id);
        $this->assertTrue($child->isCounterOffer());
    }

    // ── 9-12: terminal states show neither pending banner ────────────────────

    /**
     * @dataProvider terminalStatuses
     */
    public function test_terminal_offers_show_neither_pending_banner(string $status): void
    {
        $user  = User::factory()->create();
        $offer = $this->originalOffer($user, 'seller', $status);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertStatus(200);
        $response->assertDontSee(self::ORIGINAL_COPY);
        $response->assertDontSee(self::COUNTER_COPY);
    }

    public static function terminalStatuses(): array
    {
        return [
            'accepted'  => ['accepted'],
            'rejected'  => ['rejected'],
            'withdrawn' => ['withdrawn'],
            'expired'   => ['expired'],
        ];
    }

    // ── 13: the shared view serves every role identically ────────────────────

    /**
     * @dataProvider allRoles
     */
    public function test_shared_view_uses_the_same_rule_for_every_role(string $role): void
    {
        $user = User::factory()->create();

        $original = $this->originalOffer($user, $role);
        $this->actingAs($user)->get(route('offers.show', $original))
            ->assertSee(self::ORIGINAL_COPY)
            ->assertDontSee(self::COUNTER_COPY);

        $counter = $this->counterOffer($user, $role);
        $this->actingAs($user)->get(route('offers.show', $counter))
            ->assertSee(self::COUNTER_COPY);
    }

    public static function allRoles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }
}
