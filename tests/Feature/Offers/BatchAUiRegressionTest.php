<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch A — launch-audit remediation regression guards.
 *
 * Covers the five low-risk "false-green" items verified in Batch A:
 *   #3  Seller edit → redirect to the listing detail page (both draft-publish and
 *       already-published edit paths), never a re-rendered form with a flash banner.
 *   #12 Pet fields ("Number of Pets Allowed", "Maximum Weight Per Pet") render as
 *       text inputs, not number inputs, across Create Seller/Landlord + Hire
 *       Seller/Landlord.
 *   #13 Create Landlord "Desired Lease Term" Other placeholder no longer repeats an
 *       option already offered in the dropdown.
 *   #32 Create Buyer "Conditions or Requirements for Lease Purchase" placeholder
 *       capitalizes "Seller".
 *
 * NOTE (Owner Decision #4): these tests provide CODE verification only. Visual/
 * browser confirmation is still outstanding — every Batch A item remains
 * "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until a human browser QA pass runs.
 * The pet-field markup is conditionally rendered (@if pets === 'Yes'), so it is
 * asserted against the Blade source rather than an HTTP GET.
 */
class BatchAUiRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW_ROOT = 'resources/views/livewire';

    private function makeAgentUser(): User
    {
        return User::factory()->create(['user_type' => 'agent']);
    }

    /**
     * Build a Seller auction seeded with the required publish fields so that the
     * edit component's update() passes SellerPublishValidation.
     */
    private function makeSellerAuction(User $user, bool $isDraft = false): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Batch A Seller Listing',
            'is_draft'    => $isDraft,
            'is_approved' => !$isDraft,
            'is_sold'     => false,
        ]);

        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type', 'meta_value' => 'offer_listing'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_type', 'meta_value' => 'Residential'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'first_name',    'meta_value' => 'Test'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'last_name',     'meta_value' => 'Agent'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'phone_number',  'meta_value' => '5551234567'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'email',         'meta_value' => 'agent@example.com'],
        ]);

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'linked_offer_auction_id',
            'meta_value'              => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    private function viewSource(string $relativePath): string
    {
        $full = base_path(self::VIEW_ROOT . '/' . $relativePath);
        $this->assertFileExists($full, "Expected view partial missing: {$relativePath}");

        return (string) file_get_contents($full);
    }

    // ─── #3 · Seller edit redirect ────────────────────────────────────────────

    /**
     * #3: editing an ALREADY-PUBLISHED (non-draft) Seller listing must redirect to
     * the listing detail page — the bug was that the non-draft path flashed success
     * and returned nothing, stranding the user on the edit form.
     */
    public function test_seller_edit_of_published_listing_redirects_to_detail_view(): void
    {
        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, false); // published

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('offer.listing.seller.view', ['id' => $auction->id]));
    }

    /**
     * #3 regression guard: the draft → publish path (which already redirected) must
     * keep redirecting to the detail view, i.e. the fix did not disturb it.
     */
    public function test_seller_edit_of_draft_publishes_and_redirects_to_detail_view(): void
    {
        $user    = $this->makeAgentUser();
        $auction = $this->makeSellerAuction($user, true); // draft

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('offer.listing.seller.view', ['id' => $auction->id]));
    }

    // ─── #12 · Pet fields are text inputs, not number inputs ──────────────────

    /**
     * @dataProvider petPartialProvider
     */
    public function test_pet_fields_are_text_not_number(string $partial): void
    {
        $src = $this->viewSource($partial);

        foreach (['number_of_pets', 'weight_of_pets'] as $field) {
            $this->assertMatchesRegularExpression(
                '/type="text"\s+wire:model(?:\.defer)?="' . $field . '"/',
                $src,
                "[{$partial}] {$field} must be a text input."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/type="number"\s+wire:model(?:\.defer)?="' . $field . '"/',
                $src,
                "[{$partial}] {$field} must NOT be a number input."
            );
        }
    }

    public static function petPartialProvider(): array
    {
        return [
            'create-seller'   => ['offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php'],
            'create-landlord' => ['offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php'],
            'hire-seller'     => ['hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php'],
            'hire-landlord'   => ['hire-landlord-agent/landlord-agent-auction-tabs/commission-based/property-preferences.blade.php'],
        ];
    }

    // ─── #13 · Landlord lease-term Other placeholder ──────────────────────────

    /**
     * #13: the Create Landlord "Other" lease-term placeholder example must not repeat
     * an option already in the dropdown ("6 Months"). The old copy used "6-month".
     */
    public function test_create_landlord_other_lease_term_placeholder_has_no_duplicate_example(): void
    {
        $src = $this->viewSource('offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php');

        $this->assertStringNotContainsString('6-month', $src,
            'Create Landlord lease-term placeholder must not use "6-month" (duplicates the "6 Months" option).');
        $this->assertStringContainsString('placeholder="Enter desired lease term (e.g., 8 Months)"', $src,
            'Create Landlord lease-term placeholder should use a non-listed example (e.g., 8 Months).');
    }

    // ─── #32 · Buyer lease-purchase placeholder capitalization ────────────────

    /**
     * #32: the Create Buyer "Conditions or Requirements for Lease Purchase"
     * placeholder must capitalize "Seller".
     */
    public function test_create_buyer_lease_purchase_placeholder_capitalizes_seller(): void
    {
        $src = $this->viewSource('offer-listing/offer-buyer-tabs/commission-based/purchasing-terms.blade.php');

        $this->assertStringContainsString('Seller to cover closing costs', $src,
            'Create Buyer lease-purchase placeholder must capitalize "Seller".');
        $this->assertStringNotContainsString('seller to cover closing costs', $src,
            'Create Buyer lease-purchase placeholder must not contain the lowercase "seller" variant.');
    }
}
