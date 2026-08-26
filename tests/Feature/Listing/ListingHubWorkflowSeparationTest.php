<?php

namespace Tests\Feature\Listing;

use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * Characterisation lock on the hubs, which were ALREADY correct.
 *
 * The Hire Listings Hub and the Offer Listings Hub each carry a two-layer filter
 * (`whereDoesntHave workflow_type = offer_listing` plus a list of Offer-Listing-exclusive
 * meta keys, and the positive mirror of that). That protection predates this change and
 * is deliberately left alone — the audit found the draft pickers unguarded, not the hubs.
 *
 * These tests exist so that the separation the hubs already provide is pinned before the
 * discriminator work lands underneath them. They assert current behaviour; they are not a
 * new requirement, and nothing here changed to make them pass.
 *
 * Drafts never reach either hub — both filter `is_draft = false` — so the fixtures are
 * published listings.
 */
class ListingHubWorkflowSeparationTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    public function test_hire_seller_hub_excludes_offer_listings(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hire  = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false,
            ['listing_title' => 'HIRE LISTING'], ['is_approved' => true, 'is_sold' => 'false']);
        $offer = $this->makeListing('seller', ListingWorkflow::OFFER_LISTING, $user->id, false,
            ['listing_title' => 'OFFER LISTING'], ['is_approved' => true, 'is_sold' => 'false']);

        $response = $this->get(route('hireSellerAgentHireAuctions'));

        $response->assertOk();

        $ids = collect($response->viewData('auctions'))->pluck('id')->all();

        $this->assertContains($hire->id, $ids, 'the Hire hub must show Hire listings');
        $this->assertNotContains($offer->id, $ids, 'the Hire hub must not show Offer Listings');
    }

    public function test_hire_seller_hub_excludes_drafts_of_its_own_product(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $draft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true,
            [], ['is_approved' => true, 'is_sold' => 'false']);

        $response = $this->get(route('hireSellerAgentHireAuctions'));

        $ids = collect($response->viewData('auctions'))->pluck('id')->all();

        $this->assertNotContains($draft->id, $ids,
            'drafts belong to the picker, not the hub — this is why the picker was the sole unguarded surface');
    }

    /**
     * A stamped Hire listing survives the hub's Offer-meta heuristic.
     *
     * The Hire hub also excludes anything carrying a key from
     * SellerOfferListingController::OFFER_LISTING_META_KEYS. `property_photos` is on that
     * list and is written by plenty of Hire listings too, so this pins that a positively
     * stamped Hire row is not filtered out by the heuristic's collateral.
     */
    public function test_hire_hub_heuristic_does_not_hide_a_stamped_hire_listing_without_offer_keys(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hire = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false,
            ['listing_title' => 'PLAIN HIRE'], ['is_approved' => true, 'is_sold' => 'false']);

        $response = $this->get(route('hireSellerAgentHireAuctions'));

        $ids = collect($response->viewData('auctions'))->pluck('id')->all();

        $this->assertContains($hire->id, $ids);
    }

    /** Another user's listings never appear. */
    public function test_hire_hub_is_owner_scoped(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();

        $theirs = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $owner->id, false,
            [], ['is_approved' => true, 'is_sold' => 'false']);

        $this->actingAs($stranger);

        $response = $this->get(route('hireSellerAgentHireAuctions'));

        $ids = collect($response->viewData('auctions'))->pluck('id')->all();

        $this->assertNotContains($theirs->id, $ids);
    }
}
