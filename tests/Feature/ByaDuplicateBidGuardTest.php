<?php

namespace Tests\Feature;

use App\Http\Livewire\Buyer\BuyerAgentAuctionBid;
use App\Http\Livewire\Landlord\LandlordAgentAuctionBid;
use App\Http\Livewire\Seller\SellerAgentAuctionBid;
use App\Http\Livewire\Tenant\TenantAgentAuctionBid;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid as BuyerAgentAuctionBidData;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid as LandlordAgentAuctionBidData;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid as SellerAgentAuctionBidData;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid as TenantAgentAuctionBidData;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BYA-H3 (Rule D2): one agent has one active bid per hire-agent listing. A repeat
 * (non-edit) submission by the same agent must UPDATE the existing bid in place
 * rather than insert a duplicate row. Landlord already had this safeguard; these
 * tests prove Buyer/Seller/Tenant now mirror it, and that Landlord stays intact.
 */
class ByaDuplicateBidGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function agent(): User
    {
        return User::factory()->create(['user_type' => 'buyer_agent']);
    }

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** Required bid fields common to every role's rules(). */
    private function validFields(User $agent, array $extra = []): array
    {
        return array_merge([
            'bio'                 => 'Experienced agent.',
            'why_hire_you'        => 'Because results.',
            'what_sets_you_apart' => 'Local expertise.',
            'marketing_plan'      => 'Multi-channel.',
            'year_licensed'       => 2010,
            'first_name'          => 'Jane',
            'last_name'           => 'Smith',
            'phone'               => '555-123-4567',
            'email'               => $agent->email,
            'brokerage'           => 'Acme Realty',
            'license_no'          => 'LIC-123',
        ], $extra);
    }

    private function submitOnce(string $component, int $auctionId, User $agent, array $fields): void
    {
        Livewire::actingAs($agent)
            ->test($component, ['auctionId' => $auctionId])
            ->set($fields)
            ->call('submit');
    }

    public function test_buyer_duplicate_bid_updates_in_place(): void
    {
        $agent   = $this->agent();
        $auction = BuyerAgentAuction::create([
            'user_id' => $this->owner()->id, 'title' => 'Buyer listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        $this->submitOnce(BuyerAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, ['bio' => 'FIRST']));
        $first = BuyerAgentAuctionBidData::where('buyer_agent_auction_id', $auction->id)->where('user_id', $agent->id)->firstOrFail();

        $this->submitOnce(BuyerAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, ['bio' => 'SECOND']));

        $this->assertSame(1, BuyerAgentAuctionBidData::where('buyer_agent_auction_id', $auction->id)->where('user_id', $agent->id)->count(),
            'A second submit by the same agent must not create a duplicate bid row.');
        $this->assertSame($first->id, BuyerAgentAuctionBidData::where('buyer_agent_auction_id', $auction->id)->where('user_id', $agent->id)->first()->id,
            'The same bid row must be reused (updated in place).');
    }

    public function test_seller_duplicate_bid_updates_in_place(): void
    {
        $agent   = $this->agent();
        $auction = SellerAgentAuction::create([
            'user_id' => $this->owner()->id, 'title' => 'Seller listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        $this->submitOnce(SellerAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, ['bio' => 'FIRST']));
        $first = SellerAgentAuctionBidData::where('seller_agent_auction_id', $auction->id)->where('user_id', $agent->id)->firstOrFail();

        $this->submitOnce(SellerAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, ['bio' => 'SECOND']));

        $this->assertSame(1, SellerAgentAuctionBidData::where('seller_agent_auction_id', $auction->id)->where('user_id', $agent->id)->count(),
            'A second submit by the same agent must not create a duplicate bid row.');
        $this->assertSame($first->id, SellerAgentAuctionBidData::where('seller_agent_auction_id', $auction->id)->where('user_id', $agent->id)->first()->id);
    }

    public function test_tenant_duplicate_bid_updates_in_place(): void
    {
        $agent   = $this->agent();
        $auction = TenantAgentAuction::factory()->active()->create(['user_id' => $this->owner()->id]);

        $extra = ['commission_structure' => 'flat_fee', 'lease_fee_type' => 'one_month'];
        $this->submitOnce(TenantAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, array_merge($extra, ['bio' => 'FIRST'])));
        $first = TenantAgentAuctionBidData::where('tenant_agent_auction_id', $auction->id)->where('user_id', $agent->id)->firstOrFail();

        $this->submitOnce(TenantAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, array_merge($extra, ['bio' => 'SECOND'])));

        $this->assertSame(1, TenantAgentAuctionBidData::where('tenant_agent_auction_id', $auction->id)->where('user_id', $agent->id)->count(),
            'A second submit by the same agent must not create a duplicate bid row.');
        $this->assertSame($first->id, TenantAgentAuctionBidData::where('tenant_agent_auction_id', $auction->id)->where('user_id', $agent->id)->first()->id);
    }

    public function test_landlord_duplicate_bid_safeguard_remains_intact(): void
    {
        $agent   = $this->agent();
        $auction = LandlordAgentAuction::create([
            'user_id' => $this->owner()->id, 'title' => 'Landlord listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        $this->submitOnce(LandlordAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, ['bio' => 'FIRST']));
        $first = LandlordAgentAuctionBidData::where('landlord_agent_auction_id', $auction->id)->where('user_id', $agent->id)->firstOrFail();

        $this->submitOnce(LandlordAgentAuctionBid::class, $auction->id, $agent, $this->validFields($agent, ['bio' => 'SECOND']));

        $this->assertSame(1, LandlordAgentAuctionBidData::where('landlord_agent_auction_id', $auction->id)->where('user_id', $agent->id)->count(),
            'Landlord must continue to keep one active bid per agent.');
        $this->assertSame($first->id, LandlordAgentAuctionBidData::where('landlord_agent_auction_id', $auction->id)->where('user_id', $agent->id)->first()->id);
    }
}
