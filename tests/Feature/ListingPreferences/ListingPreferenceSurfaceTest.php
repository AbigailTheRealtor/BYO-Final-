<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The first customer surface: what the control renders, and for whom.
 *
 * The component is rendered directly rather than through the full listing page,
 * because the page is 3,500 lines with its own data requirements — the question
 * here is what the CONTROL does, and that is the piece every later surface will
 * reuse.
 */
class ListingPreferenceSurfaceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
    }

    /** @test */
    public function the_control_renders_three_states_for_a_signed_in_buyer(): void
    {
        $this->actingAs($this->buyer());

        $html = $this->render($this->sellerListing());

        $this->assertStringContainsString('data-lp-control', $html);
        foreach (ListingPreferenceState::cases() as $state) {
            $this->assertStringContainsString('data-lp-state="' . $state->value . '"', $html);
        }
    }

    /** @test */
    public function the_current_state_renders_as_active(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing();

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id),
            ListingPreferenceState::Maybe,
            ['layout'],
        );

        $this->actingAs($user);
        $html = $this->render($listing);

        $this->assertMatchesRegularExpression(
            '/data-lp-state="maybe"[^>]*aria-pressed="true"|aria-pressed="true"[^>]*data-lp-state="maybe"/',
            $html,
            'the current state must render visibly active'
        );

        // …and its reasons come back with it. The payload is emitted as raw
        // JSON inside a single-quoted attribute, so it is asserted as written.
        $this->assertStringContainsString('"state":"maybe"', $html);
        $this->assertStringContainsString('layout', $html);
    }

    /**
     * The chips in the payload come from the catalog, filtered by state and by
     * the listing's real context.
     *
     * @test
     */
    public function the_chip_payload_is_catalog_driven_and_context_filtered(): void
    {
        $this->actingAs($this->buyer());

        $residential = $this->render($this->sellerListing('Residential'));
        $land        = $this->render($this->sellerListing('Vacant Land'));

        // A kitchen chip belongs on a house and not on a lot.
        $this->assertStringContainsString('updated_kitchen', $residential);
        $this->assertStringNotContainsString('updated_kitchen', $land);

        // Non-tag reasons are context independent and appear on both.
        $this->assertStringContainsString('location_proximity', $residential);
        $this->assertStringContainsString('location_proximity', $land);

        // Each state's own prompt travels with its chips.
        $this->assertStringContainsString('What do you like about this property?', $residential);
        $this->assertStringContainsString('What are you unsure about?', $residential);
        // The Pass prompt's apostrophe is \u0027-escaped by @json — which is
        // precisely what keeps the single-quoted data attribute well formed.
        $this->assertStringContainsString('Why isn\\u0027t this one for you?', $residential);
    }

    /**
     * Fair Housing, at the surface. These must not reach the browser at all —
     * not merely be refused if clicked.
     *
     * @test
     */
    public function excluded_smart_tags_never_reach_the_rendered_payload(): void
    {
        $this->actingAs($this->buyer());

        $html = $this->render($this->sellerListing());

        $this->assertStringNotContainsString('accessible_features', $html);
        $this->assertStringNotContainsString('playground', $html);
    }

    /** @test */
    public function a_guest_sees_the_control_and_the_existing_login_flow(): void
    {
        $html = $this->render($this->sellerListing());

        $this->assertStringContainsString('data-lp-control', $html);
        $this->assertStringContainsString('data-lp-guest="1"', $html);
        $this->assertStringContainsString(route('login'), $html);

        // A guest render never carries someone else's state.
        $this->assertStringContainsString('"state":null', $html);
    }

    /** @test */
    public function the_control_renders_nothing_while_the_feature_is_off(): void
    {
        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());

        $this->assertSame('', trim($this->render($this->sellerListing())));
    }

    /** @test */
    public function the_control_renders_nothing_for_an_account_that_is_not_a_seeker(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'agent']));

        $this->assertSame('', trim($this->render($this->sellerListing())));
    }

    /** @test */
    public function the_control_renders_nothing_for_a_buyer_on_a_lease_listing(): void
    {
        $this->actingAs($this->buyer());

        $landlord = \App\Models\LandlordAgentAuction::create(['user_id' => 900301]);
        \App\Models\LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $landlord->id,
            'meta_key'                  => 'property_type',
            'meta_value'                => 'Residential Property',
        ]);

        $html = Blade::render(
            '<x-listing-preference.control listing-type="landlord_agent" :listing-id="$id" />',
            ['id' => $landlord->id]
        );

        $this->assertSame('', trim($html), 'a buyer is not shown a control their click would be refused on');
    }

    /**
     * The listing page must never break because the control could not be
     * prepared.
     *
     * @test
     */
    public function an_unresolvable_listing_degrades_to_nothing_rather_than_erroring(): void
    {
        $this->actingAs($this->buyer());

        $html = Blade::render(
            '<x-listing-preference.control listing-type="not_a_type" :listing-id="$id" />',
            ['id' => 0]
        );

        $this->assertSame('', trim($html));
    }

    /**
     * Both wired surfaces render the shared component — neither grew its own.
     *
     * @test
     */
    public function both_detail_pages_use_the_shared_component(): void
    {
        foreach (['seller' => 'seller_agent', 'landlord' => 'landlord_agent'] as $role => $type) {
            $view = file_get_contents(
                resource_path("views/offer-listing/{$role}/view.blade.php")
            );

            $this->assertStringContainsString(
                "<x-listing-preference.control listing-type=\"{$type}\"",
                (string) $view,
                "the {$role} detail page must render the shared control"
            );

            // The dead placeholder it replaced is gone.
            $this->assertStringNotContainsString('Save Listing', (string) $view);
        }
    }

    private function render(SellerAgentAuction $listing): string
    {
        return Blade::render(
            '<x-listing-preference.control listing-type="seller_agent" :listing-id="$id" />',
            ['id' => $listing->id]
        );
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function sellerListing(string $propertyType = 'Residential'): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => 900300,
            'address' => '13 Surface Way, St. Petersburg, FL 33701',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_type',
            'meta_value'              => $propertyType,
        ]);

        return $auction;
    }
}
