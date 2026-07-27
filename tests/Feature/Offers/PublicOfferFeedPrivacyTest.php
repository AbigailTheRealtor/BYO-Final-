<?php

namespace Tests\Feature\Offers;

use App\Models\LandlordAgentAuction;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Offers\PublicOfferFeedService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Privacy and authorization for the anonymous competing-bid feed.
 *
 * The load-bearing assertion throughout is that a viewer who may not see bids
 * receives NO bid data — not hidden data, not empty markup around real values.
 * Guests get the login callout and nothing else.
 */
class PublicOfferFeedPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private PublicOfferFeedService $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feed = app(PublicOfferFeedService::class);
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');
    }

    private function sellerListing(User $owner): array
    {
        $listing = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Feed Seller Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '14 Days');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'            => $owner->id,
            'bidding_starts_at' => CarbonImmutable::now()->subDay(),
            'bidding_ends_at'   => CarbonImmutable::now()->addDays(6),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return [$listing->fresh('meta'), $offerAuction];
    }

    private function landlordListing(User $owner): array
    {
        $listing = LandlordAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Feed Landlord Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '14 Days');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'            => $owner->id,
            'bidding_starts_at' => CarbonImmutable::now()->subDay(),
            'bidding_ends_at'   => CarbonImmutable::now()->addDays(6),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);
        $offerAuction->saveMeta('linked_landlord_auction_id', $listing->id);

        return [$listing->fresh('meta'), $offerAuction->fresh('metas')];
    }

    private function submittedOffer(OfferAuction $auction, User $bidder, array $metas, string $role = 'buyer'): Offer
    {
        $offer = Offer::factory()->submitted()->create([
            'user_id'          => $bidder->id,
            'offer_auction_id' => $auction->id,
            'role'             => $role,
        ]);

        foreach ($metas as $k => $v) {
            $offer->saveMeta($k, $v);
        }

        return $offer->fresh('metas');
    }

    // -------------------------------------------------------- authorization

    public function test_guest_may_not_view_the_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $this->assertFalse($this->feed->canView(null, $listing, 'seller'));
    }

    public function test_eligible_buyer_may_view_a_seller_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $buyer = User::factory()->create(['user_type' => 'buyer']);

        $this->assertTrue($this->feed->canView($buyer, $listing, 'seller'));
    }

    public function test_tenant_may_not_view_a_seller_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $tenant = User::factory()->create(['user_type' => 'tenant']);

        $this->assertFalse($this->feed->canView($tenant, $listing, 'seller'));
    }

    public function test_eligible_tenant_may_view_a_landlord_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        [$listing] = $this->landlordListing($owner);

        $tenant = User::factory()->create(['user_type' => 'tenant']);

        $this->assertTrue($this->feed->canView($tenant, $listing, 'landlord'));
    }

    public function test_buyer_may_not_view_a_landlord_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        [$listing] = $this->landlordListing($owner);

        $buyer = User::factory()->create(['user_type' => 'buyer']);

        $this->assertFalse($this->feed->canView($buyer, $listing, 'landlord'));
    }

    public function test_listing_owner_may_view_their_own_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $this->assertTrue($this->feed->canView($owner, $listing, 'seller'));
    }

    public function test_authorized_representative_may_view_the_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $this->assertTrue($this->feed->canView(User::factory()->create(['user_type' => 'agent']), $listing, 'seller'));
        $this->assertTrue($this->feed->canView(User::factory()->create(['user_type' => 'buyer_agent']), $listing, 'seller'));
    }

    public function test_admin_may_view_the_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $this->assertTrue($this->feed->canView(User::factory()->create(['user_type' => 'admin']), $listing, 'seller'));
    }

    // ---------------------------------------------------- guest page render

    public function test_guest_sees_the_login_callout_on_a_seller_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing] = $this->sellerListing($owner);

        $response = $this->get(route('offer.listing.seller.view', $listing->id));

        $response->assertOk();
        $response->assertSee('Log In to View Bids');
    }

    public function test_guest_receives_no_bid_data_on_a_seller_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), [
            'offer_price'    => '987654',
            'financing_type' => 'Conventional',
            'notes'          => 'PRIVATE BIDDER NOTE',
        ]);

        $response = $this->get(route('offer.listing.seller.view', $listing->id));

        $response->assertOk();
        // Not merely hidden — the value is absent from the response body entirely.
        $response->assertDontSee('987654');
        $response->assertDontSee('PRIVATE BIDDER NOTE');
        $response->assertDontSee('Bidder #1');
    }

    public function test_guest_receives_no_bid_data_on_a_landlord_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        [$listing, $offerAuction] = $this->landlordListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'tenant']), [
            'monthly_rent'   => '4321',
            'monthly_income' => '250000',
        ], 'tenant');

        $response = $this->get(route('offer.listing.landlord.view', $listing->id));

        $response->assertOk();
        $response->assertDontSee('4321');
        $response->assertDontSee('250000');
        $response->assertDontSee('Bidder #1');
    }

    public function test_ineligible_authenticated_viewer_receives_no_bid_data(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), [
            'offer_price' => '987654',
        ]);

        $response = $this->actingAs(User::factory()->create(['user_type' => 'tenant']))
            ->get(route('offer.listing.seller.view', $listing->id));

        $response->assertOk();
        $response->assertDontSee('987654');
        $response->assertDontSee('Log In to View Bids'); // already signed in
    }

    public function test_eligible_buyer_sees_the_feed_on_the_page(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), [
            'offer_price' => '987654',
        ]);

        $response = $this->actingAs(User::factory()->create(['user_type' => 'buyer']))
            ->get(route('offer.listing.seller.view', $listing->id));

        $response->assertOk();
        $response->assertSee('Bidder #1');
        $response->assertSee('987654');
    }

    // ------------------------------------------------------- sanitized feed

    public function test_landlord_feed_exposes_only_the_allow_listed_terms(): void
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        [, $offerAuction] = $this->landlordListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'tenant']), [
            // Allowed
            'monthly_rent'               => '2500',
            'lease_term_months'          => '12',
            'security_deposit'           => '2500',
            'last_month_rent_offered'    => 'Yes',
            'move_in_date'               => '2026-09-01',
            'move_in_funds'              => '5000',
            'maintenance_responsibility' => 'Landlord',
            // Must never surface
            'occupants'          => '4',
            'pets'               => 'Two dogs',
            'smoking'            => 'No',
            'monthly_income'     => '250000',
            'employment'         => 'Acme Corp',
            'credit_score'       => '780',
            'criminal_history'   => 'None',
            'eviction_history'   => 'None',
            'bankruptcy'         => 'None',
            'screening_result'   => 'Pass',
            'references'         => 'Jane Doe',
            'notes'              => 'PRIVATE NOTE',
            'custom_terms'       => 'PRIVATE TERMS',
            'contact_email'      => 'tenant@example.com',
        ], 'tenant');

        $rows = $this->feed->build($offerAuction, 'landlord');

        $this->assertCount(1, $rows);
        $terms = $rows[0]['terms'];

        $this->assertSame(
            PublicOfferFeedService::LANDLORD_ALLOWED_TERMS,
            array_values(array_intersect(PublicOfferFeedService::LANDLORD_ALLOWED_TERMS, array_keys($terms))),
        );

        foreach ([
            'occupants', 'pets', 'smoking', 'monthly_income', 'employment', 'credit_score',
            'criminal_history', 'eviction_history', 'bankruptcy', 'screening_result',
            'references', 'notes', 'custom_terms', 'contact_email',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $terms, "'{$forbidden}' must never appear in the public feed.");
        }
    }

    public function test_seller_allow_list_matches_the_agreed_field_set(): void
    {
        $this->assertSame([
            'offer_price',
            'earnest_deposit',
            'earnest_deposit_unit',
            'financing_type',
            'financing_contingency',
            'financing_contingency_days',
            'down_payment_value',
            'down_payment_unit',
            'inspection_contingency',
            'inspection_contingency_days',
            'appraisal_contingency',
            'appraisal_contingency_days',
            'sale_of_buyer_property_contingency',
            'sale_of_buyer_property_contingency_days',
            'closing_date',
            'possession_date',
            'home_warranty_requested',
            'seller_contribution_requested',
        ], PublicOfferFeedService::SELLER_ALLOWED_TERMS);
    }

    public function test_seller_feed_discloses_requested_flags_but_never_their_details(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), [
            'sale_of_buyer_property_contingency'      => '1',
            'sale_of_buyer_property_contingency_days' => '45',
            'home_warranty_requested'                 => 'Yes',
            'home_warranty_details'                   => 'PRIVATE WARRANTY DETAIL',
            'seller_contribution_requested'           => 'Yes',
            'seller_contribution_details'             => 'PRIVATE CONTRIBUTION DETAIL',
            'possession_notes'                        => 'PRIVATE POSSESSION NOTE',
            'included_personal_property'              => 'PRIVATE INCLUDED ITEMS',
            'excluded_items'                          => 'PRIVATE EXCLUDED ITEMS',
        ]);

        $terms = $this->feed->build($offerAuction, 'seller')[0]['terms'];

        $this->assertSame('1', $terms['sale_of_buyer_property_contingency']);
        $this->assertSame('45', $terms['sale_of_buyer_property_contingency_days']);
        $this->assertSame('Yes', $terms['home_warranty_requested']);
        $this->assertSame('Yes', $terms['seller_contribution_requested']);

        foreach ([
            'home_warranty_details',
            'seller_contribution_details',
            'possession_notes',
            'included_personal_property',
            'excluded_items',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $terms, "'{$forbidden}' is free text and must never be disclosed.");
        }
    }

    public function test_seller_feed_excludes_expires_at_and_exotic_financing_free_text(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), [
            'offer_price'                => '500000',
            'expires_at'                 => '2026-09-01',
            'cryptocurrency_type'        => 'PRIVATE CRYPTO',
            'crypto_exchange_method'     => 'PRIVATE CRYPTO METHOD',
            'exchange_item'              => 'Artwork',
            'other_exchange_item'        => 'PRIVATE EXCHANGE ITEM',
            'seller_financing_term'      => 'PRIVATE SF TERM',
            'seller_late_fee_amount'     => 'PRIVATE SF LATE FEE',
        ]);

        $terms = $this->feed->build($offerAuction, 'seller')[0]['terms'];

        foreach ([
            'expires_at', 'cryptocurrency_type', 'crypto_exchange_method',
            'exchange_item', 'other_exchange_item', 'seller_financing_term',
            'seller_late_fee_amount',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $terms);
        }
    }

    public function test_seller_feed_excludes_free_text_and_bidder_property_info(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), [
            'offer_price'       => '500000',
            'financing_type'    => 'Cash',
            'notes'             => 'PRIVATE NOTE',
            'custom_terms'      => 'PRIVATE TERMS',
            'match_explanation' => 'PRIVATE EXPLANATION',
            'prop_street'       => '1 Private Way',
            'prop_city'         => 'Nowhere',
        ]);

        $terms = $this->feed->build($offerAuction, 'seller')[0]['terms'];

        $this->assertSame('500000', $terms['offer_price']);
        $this->assertSame('Cash', $terms['financing_type']);

        foreach (['notes', 'custom_terms', 'match_explanation', 'prop_street', 'prop_city'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $terms);
        }
    }

    public function test_feed_never_exposes_identity_or_raw_ids(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $bidder = User::factory()->create(['user_type' => 'buyer', 'name' => 'Sensitive Bidder Name']);
        $offer  = $this->submittedOffer($offerAuction, $bidder, ['offer_price' => '500000']);

        $row = $this->feed->build($offerAuction, 'seller')[0];

        $encoded = json_encode($row);

        $this->assertStringNotContainsString('Sensitive Bidder Name', $encoded);
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertArrayNotHasKey('offer_id', $row);
        $this->assertArrayNotHasKey('id', $row);
        $this->assertStringNotContainsString($bidder->email, $encoded);
    }

    public function test_draft_offers_never_appear_in_the_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $draft = Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'draft',
        ]);
        $draft->saveMeta('offer_price', '111111');

        $this->assertSame([], $this->feed->build($offerAuction, 'seller'));
    }

    public function test_internal_statuses_are_reported_as_sanitized_labels(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $this->submittedOffer($offerAuction, User::factory()->create(['user_type' => 'buyer']), []);

        $row = $this->feed->build($offerAuction, 'seller')[0];

        $this->assertSame('Active', $row['status']);
        $this->assertNotSame('submitted', $row['status']);
    }

    // ---------------------------------------------------- bidder numbering

    public function test_bidder_numbers_are_assigned_in_submission_order(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $first = Offer::factory()->submitted()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'submitted_at'     => CarbonImmutable::now()->subHours(5),
        ]);
        $second = Offer::factory()->submitted()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'submitted_at'     => CarbonImmutable::now()->subHours(2),
        ]);

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertSame([1, 2], array_column($rows, 'bidder_number'));
    }

    public function test_a_counter_offer_inherits_its_root_bidder_number(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $bidderOne = User::factory()->create(['user_type' => 'buyer']);
        $root = Offer::factory()->create([
            'user_id'          => $bidderOne->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'countered',
            'submitted_at'     => CarbonImmutable::now()->subHours(5),
        ]);

        // Owner's counter is a child of the root — same bidder, same number.
        $counter = Offer::factory()->create([
            'user_id'          => $owner->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $root->id,
            'role'             => 'seller',
            'status'           => 'submitted',
            'submitted_at'     => CarbonImmutable::now()->subHours(4),
        ]);

        // A separate bidder arriving later must be #2.
        Offer::factory()->submitted()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'submitted_at'     => CarbonImmutable::now()->subHours(3),
        ]);

        $rows    = $this->feed->build($offerAuction, 'seller');
        $numbers = array_column($rows, 'bidder_number');

        // Root + its counter both carry #1; the second bidder carries #2.
        $this->assertSame([1, 1, 2], $numbers);
    }

    public function test_bidder_numbers_are_stable_when_another_bid_is_withdrawn(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->sellerListing($owner);

        $withdrawn = Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'withdrawn',
            'submitted_at'     => CarbonImmutable::now()->subHours(9),
        ]);
        $survivor = Offer::factory()->submitted()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'submitted_at'     => CarbonImmutable::now()->subHours(4),
        ]);

        $rows = $this->feed->build($offerAuction, 'seller');

        // The withdrawn bid is not listed, but it keeps its slot so the surviving
        // bidder's number does not shift under them.
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['bidder_number']);
    }

    public function test_feed_is_empty_for_a_listing_with_no_offer_auction(): void
    {
        $this->assertSame([], $this->feed->build(null, 'seller'));
    }
}
