<?php

namespace Tests\Feature\Security;

use App\Models\AgentServiceAuction;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * The public author page lists the PROFILE USER's listings — never another user's.
 *
 * THE DEFECT. `/author/{id}` is public (`web` middleware only). UserController::author()
 * resolves the profile user from {id}, but the Seller, Buyer and Landlord profile branches,
 * and the agent profile's Buyer's Criteria / Tenant's Criteria / Agent Service tabs, filtered
 * their tables by status alone, with no `user_id`. Every other user's approved, live listing
 * therefore appeared on every profile of that account type, attributed to the person whose
 * page it was. The tenant profile's tabs and the agent's Property Listing (Sale) tab always
 * scoped to the profile user, and are pinned here as controls.
 *
 * Every test sets two users' otherwise-identical, publicly eligible listings side by side and
 * judges the listing ids the page received — as a guest, and as an unrelated signed-in
 * visitor — so status filtering cannot be what keeps the other user's listing out.
 */
class AuthorProfileOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private User $visitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visitor = User::factory()->create(['user_type' => 'buyer']);
    }

    // =====================================================================
    // Fixtures — each publicly eligible unless overridden
    // =====================================================================

    private function sellerListing(User $owner, array $attributes = []): SellerAgentAuction
    {
        // is_approved true / is_sold false are stored '1' / '0', as the Seller wizards publish.
        return SellerAgentAuction::forceCreate($attributes + [
            'user_id'     => $owner->id,
            'address'     => 'Seller listing ' . uniqid(),
            'is_approved' => true,
            'is_sold'     => false,
            'is_draft'    => false,
            'is_archived' => false,
        ]);
    }

    private function buyerListing(User $owner, array $attributes = []): BuyerAgentAuction
    {
        // 'true' / 'false', as the Buyer wizards publish.
        return BuyerAgentAuction::forceCreate($attributes + [
            'user_id'     => $owner->id,
            'title'       => 'Buyer listing ' . uniqid(),
            'is_approved' => 'true',
            'is_sold'     => 'false',
            'is_draft'    => false,
            'is_archived' => false,
        ]);
    }

    private function landlordListing(User $owner, array $attributes = []): LandlordAgentAuction
    {
        return LandlordAgentAuction::forceCreate($attributes + [
            'user_id'     => $owner->id,
            'title'       => 'Landlord listing ' . uniqid(),
            'is_approved' => true,
            'is_sold'     => false,
            'is_draft'    => false,
            'is_archived' => false,
        ]);
    }

    private function tenantListing(User $owner, array $attributes = []): TenantAgentAuction
    {
        return TenantAgentAuction::factory()->create($attributes + [
            'user_id'     => $owner->id,
            'is_archived' => false,
        ]);
    }

    // =====================================================================
    // Reading the page
    // =====================================================================

    private function sorted(iterable $ids): array
    {
        return collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /** The listing ids the author page handed its view for this profile and tab. */
    private function shown(User $profile, int $type = 0, string $key = 'pAuctions'): array
    {
        $data = $this->get(route('author', ['id' => $profile->id, 'type' => $type]))
            ->assertOk()->original->getData();

        return $this->sorted(collect($data[$key]->items())->pluck('id'));
    }

    /** Exactly these listings, for a guest and for an unrelated signed-in visitor. */
    private function assertProfileShowsOnly(User $profile, array $expected, string $label, int $type = 0, string $key = 'pAuctions'): void
    {
        $expected = $this->sorted(collect($expected)->pluck('id'));

        $this->app['auth']->forgetGuards();
        $this->assertSame($expected, $this->shown($profile, $type, $key), "{$label}: guest");

        $this->actingAs($this->visitor);
        $this->assertSame($expected, $this->shown($profile, $type, $key), "{$label}: unrelated signed-in visitor");
    }

    // =====================================================================
    // Seller, Buyer and Landlord profiles
    // =====================================================================

    /** @test */
    public function a_seller_profile_lists_only_that_sellers_listings(): void
    {
        $a = User::factory()->create(['user_type' => 'seller']);
        $b = User::factory()->create(['user_type' => 'seller']);
        $tenant = User::factory()->create(['user_type' => 'tenant']);

        $own        = $this->sellerListing($a);
        $otherOwner = $this->sellerListing($b);
        $otherType  = $this->sellerListing($tenant); // the multi-role wizard: a tenant's Seller listing

        // A's own rows that were never public, and must stay hidden.
        $this->sellerListing($a, ['is_draft' => true]);
        $this->sellerListing($a, ['is_approved' => false]);
        $this->sellerListing($a, ['is_sold' => true]);
        $this->sellerListing($a, ['is_archived' => true]);
        $this->sellerListing($a)->saveMeta('workflow_type', 'offer_listing');

        $this->assertProfileShowsOnly($a, [$own], "A's profile");
        $this->assertProfileShowsOnly($b, [$otherOwner], "B's profile");

        $this->actingAs($a);
        $this->assertSame([$own->id], $this->shown($a), "A viewing A's own profile");
        $this->assertNotContains($otherType->id, $this->shown($a), "a tenant's Seller listing is not the seller's");
    }

    /** @test */
    public function a_buyer_profile_lists_only_that_buyers_listings(): void
    {
        $a = User::factory()->create(['user_type' => 'buyer']);
        $b = User::factory()->create(['user_type' => 'buyer']);

        $own        = $this->buyerListing($a);
        $otherOwner = $this->buyerListing($b);

        $this->buyerListing($a, ['is_draft' => true]);
        $this->buyerListing($a, ['is_approved' => 'false']);
        $this->buyerListing($a, ['is_sold' => 'true']);
        $this->buyerListing($a, ['is_archived' => true]);

        $this->assertProfileShowsOnly($a, [$own], "A's profile");
        $this->assertProfileShowsOnly($b, [$otherOwner], "B's profile");

        $this->actingAs($a);
        $this->assertSame([$own->id], $this->shown($a), "A viewing A's own profile");
    }

    /** @test */
    public function a_landlord_profile_lists_only_that_landlords_listings(): void
    {
        $a = User::factory()->create(['user_type' => 'landlord']);
        $b = User::factory()->create(['user_type' => 'landlord']);

        $own        = $this->landlordListing($a);
        $otherOwner = $this->landlordListing($b);

        // No draft row here: this branch has never filtered is_draft. That is a separate,
        // recorded defect, not an ownership one, and this test does not pin it either way.
        $this->landlordListing($a, ['is_approved' => false]);
        $this->landlordListing($a, ['is_sold' => true]);
        $this->landlordListing($a, ['is_archived' => true]);

        $this->assertProfileShowsOnly($a, [$own], "A's profile");
        $this->assertProfileShowsOnly($b, [$otherOwner], "B's profile");

        $this->actingAs($a);
        $this->assertSame([$own->id], $this->shown($a), "A viewing A's own profile");
    }

    // =====================================================================
    // Tenant profile — every tab was already owner-scoped (control)
    // =====================================================================

    /** @test */
    public function every_tab_of_a_tenant_profile_lists_only_that_tenants_listings(): void
    {
        $a = User::factory()->create(['user_type' => 'tenant']);
        $b = User::factory()->create(['user_type' => 'tenant']);

        $tabs = [
            0 => fn (User $u) => $this->tenantListing($u),
            1 => fn (User $u) => $this->sellerListing($u),
            2 => fn (User $u) => $this->buyerListing($u),
            3 => fn (User $u) => $this->landlordListing($u),
        ];

        foreach ($tabs as $type => $make) {
            $own        = $make($a);
            $otherOwner = $make($b);

            $this->assertProfileShowsOnly($a, [$own], "tenant tab {$type}, A's profile", $type);
            $this->assertProfileShowsOnly($b, [$otherOwner], "tenant tab {$type}, B's profile", $type);
        }
    }

    // =====================================================================
    // Agent profile
    // =====================================================================

    /** @test */
    public function every_listing_tab_of_an_agent_profile_lists_only_that_agents_listings(): void
    {
        $a = User::factory()->asAgent()->create();
        $b = User::factory()->asAgent()->create();

        // type => [model, view key, eligible attributes]. user_id is the agent who created the
        // listing (each create route sits behind agentAuth); buyer_id is the agent's client.
        // Tab 1 (LandlordAuction) is absent: its table exists in no migration and not in the
        // production schema, so that tab cannot render a listing at all.
        $tabs = [
            0 => [PropertyAuction::class, 'auctions', [
                'is_approved' => true, 'sold' => false, 'title' => 'Property listing', 'address' => '1 Test St',
                'city_id' => 1, 'state_id' => 1, 'auction_type' => 'Traditional',
            ]],
            2 => [BuyerCriteriaAuction::class, 'pAuctions', [
                'is_approved' => true, 'is_sold' => false, 'title' => 'Buyer criteria',
                'buyer_id' => $this->visitor->id, 'max_price' => 500000,
            ]],
            3 => [TenantCriteriaAuction::class, 'pAuctions', ['is_approved' => true, 'is_sold' => false, 'is_draft' => false]],
        ];

        foreach ($tabs as $type => [$model, $key, $eligible]) {
            $own        = $model::forceCreate(['user_id' => $a->id] + $eligible);
            $otherOwner = $model::forceCreate(['user_id' => $b->id] + $eligible);

            $this->assertProfileShowsOnly($a, [$own], "agent tab {$type}, A's profile", $type, $key);
            $this->assertProfileShowsOnly($b, [$otherOwner], "agent tab {$type}, B's profile", $type, $key);
        }
    }

    /** @test */
    public function another_agents_service_listing_never_reaches_an_agent_profile(): void
    {
        $a = User::factory()->asAgent()->create();
        $b = User::factory()->asAgent()->create();

        // Tab 4 renders each listing's meta from agent_service_auction_metas, which no
        // migration creates, so no listing on that tab can render — its own included. What
        // is provable is the filter: B's listing must not reach A's page. Before the fix it
        // did, and rendering it failed the whole page.
        AgentServiceAuction::forceCreate(['user_id' => $b->id, 'is_approved' => true, 'is_sold' => false]);

        $this->assertProfileShowsOnly($a, [], "agent tab 4, A's profile", 4);
    }
}
