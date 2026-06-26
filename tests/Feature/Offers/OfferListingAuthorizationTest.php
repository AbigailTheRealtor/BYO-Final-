<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\Concerns\ResolvesOwnedAuction;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * C1 — Broken Access Control / IDOR regression.
 *
 * The Offer Listing edit/create components are addressed by a client-controlled
 * {auctionId}/{listingId}. The shared ResolvesOwnedAuction guard (used in both
 * mount() and hydrate() of all four roles' components) must authorize ONLY the
 * owning consumer account.
 *
 * Two-persona model: ownership is auction.user_id === Auth::id(); a single
 * consumer account may own listings of every type. These tests use two distinct
 * consumer accounts (owner vs attacker) to prove the guard keys on the listing's
 * user_id, not on the role/listing-type, and that agents are not owners.
 */
class OfferListingAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $attacker;

    /** Anonymous holder exposing the protected trait methods for assertion. */
    private object $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner    = User::factory()->create(['user_type' => 'seller']);
        $this->attacker = User::factory()->create(['user_type' => 'buyer']);

        $this->guard = new class {
            use ResolvesOwnedAuction;

            public function can(string $modelClass, $id, ?string $type = null): bool
            {
                return $this->userCanManageAuction($modelClass, $id, $type);
            }

            public function assert(string $modelClass, $id, ?string $type = null): void
            {
                $this->assertCanManageAuction($modelClass, $id, $type);
            }
        };
    }

    private function makeAuction(string $modelClass): object
    {
        // forceCreate bypasses per-model $fillable/$guarded differences.
        return $modelClass::forceCreate([
            'user_id'  => $this->owner->id,
            'title'    => 'Owner listing',
            'is_draft' => true,
        ]);
    }

    public function modelProvider(): array
    {
        return [
            'seller'   => [SellerAgentAuction::class],
            'buyer'    => [BuyerAgentAuction::class],
            'landlord' => [LandlordAgentAuction::class],
            'tenant'   => [TenantAgentAuction::class],
        ];
    }

    /** @dataProvider modelProvider */
    public function test_owner_is_authorized(string $modelClass): void
    {
        $auction = $this->makeAuction($modelClass);
        Auth::login($this->owner);

        $this->assertTrue($this->guard->can($modelClass, $auction->id));
    }

    /** @dataProvider modelProvider */
    public function test_non_owner_is_denied(string $modelClass): void
    {
        $auction = $this->makeAuction($modelClass);
        Auth::login($this->attacker);

        $this->assertFalse($this->guard->can($modelClass, $auction->id));
    }

    /** @dataProvider modelProvider */
    public function test_assert_throws_403_for_non_owner(string $modelClass): void
    {
        $auction = $this->makeAuction($modelClass);
        Auth::login($this->attacker);

        try {
            $this->guard->assert($modelClass, $auction->id);
            $this->fail("{$modelClass}: non-owner should have been denied with 403");
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /** @dataProvider modelProvider */
    public function test_guest_is_denied(string $modelClass): void
    {
        $auction = $this->makeAuction($modelClass);
        Auth::logout();

        $this->assertFalse($this->guard->can($modelClass, $auction->id));
    }

    public function test_null_id_is_allowed_for_create_flow(): void
    {
        Auth::login($this->owner);

        // A brand-new create flow has no id yet — must not be blocked.
        $this->assertTrue($this->guard->can(SellerAgentAuction::class, null));
    }

    public function test_owner_can_mount_their_own_seller_edit_component(): void
    {
        $auction = $this->makeAuction(SellerAgentAuction::class);

        $component = Livewire::actingAs($this->owner)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id]);

        $this->assertEquals($auction->id, $component->get('auctionId'));
    }
}
