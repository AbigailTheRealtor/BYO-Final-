<?php

namespace Tests\Feature\Explore;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * The public viewport projection — §49 C through U.
 *
 * Every assertion here is about something the SERVER decided. Nothing is
 * checked by inspecting a template, because the whole design premise is that an
 * ineligible listing never becomes a response in the first place.
 */
class ExploreListingApiTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'explore.enabled'                 => true,
            'explore.google.browser_key'      => null,
            'mls_media.enabled'               => true,
            'mls_media.license_acknowledged'  => true,
        ]);
    }

    private function listings(array $query = []): array
    {
        $query = array_merge(['bbox' => $this->bboxAroundDefault()], $query);

        return $this->getJson('/api/explore/listings?' . http_build_query($query))->json();
    }

    /** @return list<string> */
    private function keys(array $query = []): array
    {
        return array_column($this->listings($query)['listings'] ?? [], 'id');
    }

    /* ── C / D — both markets project ───────────────────────────────────── */

    /** @test */
    public function an_eligible_sale_listing_is_projected(): void
    {
        $listing = $this->makeListing(['list_price' => 525000]);

        $payload = $this->listings();

        $this->assertSame(1, $payload['count']);

        $projected = $payload['listings'][0];

        $this->assertSame($listing->listing_key, $projected['id']);
        $this->assertSame('sale', $projected['transaction_type']);
        $this->assertSame('stellar_bridge', $projected['provider']);
        $this->assertSame('$525,000', $projected['display_price']);
        $this->assertSame('Active', $projected['effective_status']);
        $this->assertSame(3, $projected['beds']);
        $this->assertSame(2, $projected['baths']);
        $this->assertSame(1850, $projected['living_area']);
        $this->assertNotEmpty($projected['attribution']);
    }

    /** @test */
    public function an_eligible_rental_listing_is_projected(): void
    {
        $this->makeRental(['list_price' => 2750]);

        $projected = $this->listings()['listings'][0];

        $this->assertSame('rent', $projected['transaction_type']);
        $this->assertSame('$2,750/mo', $projected['display_price']);
    }

    /* ── E / F — the filters actually separate the two markets ──────────── */

    /** @test */
    public function the_sale_filter_excludes_rentals(): void
    {
        $sale = $this->makeListing();
        $this->makeRental();

        $this->assertSame([$sale->listing_key], $this->keys(['transaction_type' => 'sale']));
    }

    /** @test */
    public function the_rent_filter_excludes_sales(): void
    {
        $this->makeListing();
        $rental = $this->makeRental();

        $this->assertSame([$rental->listing_key], $this->keys(['transaction_type' => 'rent']));
    }

    /** @test */
    public function no_filter_returns_both_markets_distinguishably(): void
    {
        $this->makeListing();
        $this->makeRental();

        $types = array_column($this->listings()['listings'], 'transaction_type');

        sort($types);
        $this->assertSame(['rent', 'sale'], $types);
    }

    /**
     * An unrecognised filter is refused, not widened to "all". A typo that
     * silently returns everything looks exactly like the filter working.
     *
     * @test
     */
    public function an_unrecognised_filter_is_refused(): void
    {
        $this->makeListing();

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault() . '&transaction_type=seller')
            ->assertStatus(422);
    }

    /* ── G / H — geography ──────────────────────────────────────────────── */

    /** @test */
    public function a_property_outside_the_bounding_box_is_excluded(): void
    {
        $inside = $this->makeListing();
        $this->makeListing(['latitude' => 28.4500, 'longitude' => -81.4000]);

        $this->assertSame([$inside->listing_key], $this->keys());
    }

    /** @test */
    public function an_oversized_bounding_box_is_refused_with_an_explanation(): void
    {
        $this->makeListing();

        $this->getJson('/api/explore/listings?bbox=25.0,-83.0,31.0,-80.0')
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    /**
     * §49-H. A record with no coordinate, or with the null-island (0,0) that a
     * feed writes when the field was never populated, has no place on a map and
     * is never geocoded onto one.
     *
     * @test
     */
    public function properties_without_usable_coordinates_are_excluded(): void
    {
        $good = $this->makeListing();
        $this->makeListing(['latitude' => null, 'longitude' => null]);
        $this->makeListing(['latitude' => 0, 'longitude' => 0]);

        // A record with no coordinate can never satisfy any viewport.
        $this->assertSame([$good->listing_key], $this->keys());

        // And the null island is not a place: a viewport drawn around (0, 0) —
        // which a coordinate-blind filter WOULD match — returns nothing.
        $this->assertSame([], $this->keys(['bbox' => '-0.05,-0.05,0.05,0.05']));
    }

    /* ── I — effective MLS status ───────────────────────────────────────── */

    /**
     * StandardStatus governs. A record whose MlsStatus still says something
     * marketable is excluded when StandardStatus has moved on — the two
     * genuinely disagree on real records ('Closed' ↔ 'Sold').
     *
     * @test
     */
    public function only_the_public_status_allowlist_is_published(): void
    {
        $active = $this->makeListing();

        foreach (['Closed', 'Pending', 'Active Under Contract', 'Coming Soon', 'Expired'] as $status) {
            $this->makeListing(['standard_status' => $status, 'mls_status' => 'Active']);
        }

        $this->assertSame([$active->listing_key], $this->keys());
    }

    /** @test */
    public function a_sold_record_still_calling_itself_active_in_mls_status_is_excluded(): void
    {
        $this->makeListing([
            'standard_status' => 'Closed',
            'mls_status'      => 'Sold',
        ], );

        $this->assertSame([], $this->keys());
    }

    /* ── J / K — feed display permissions ───────────────────────────────── */

    /** @test */
    public function a_listing_withdrawn_from_idx_is_excluded(): void
    {
        $visible = $this->makeListing();
        $this->makeListing([], ['IDXParticipationYN' => false]);

        $this->assertSame([$visible->listing_key], $this->keys());
    }

    /**
     * The second, independent refusal. Before MlsDisplayPermissions existed
     * this flag was read nowhere, and a public surface reading only
     * IDXParticipationYN would publish these.
     *
     * @test
     */
    public function a_listing_the_feed_bars_from_the_internet_entirely_is_excluded(): void
    {
        $visible = $this->makeListing();
        $this->makeListing([], ['InternetEntireListingDisplayYN' => false]);

        $this->assertSame([$visible->listing_key], $this->keys());
    }

    /** @test */
    public function the_reso_y_and_n_spellings_are_honoured_as_refusals(): void
    {
        $this->makeListing([], ['IDXParticipationYN' => 'N']);

        $this->assertSame([], $this->keys());
    }

    /**
     * A withheld address is not a withheld listing. 71 of 1,203 live records are
     * in exactly this state: the MLS instruction is "publish this without its
     * address", so the marker, the price and the facts stay and the street line
     * and postcode go.
     *
     * @test
     */
    public function an_address_refusal_suppresses_the_address_and_keeps_the_marker(): void
    {
        $listing = $this->makeListing(
            ['unparsed_address' => '77 Withheld Way'],
            ['InternetAddressDisplayYN' => false]
        );

        $payload = $this->listings();
        $projected = $payload['listings'][0];

        $this->assertSame($listing->listing_key, $projected['id']);
        $this->assertNull($projected['address']);
        $this->assertNull($projected['postal_code']);
        $this->assertSame('St Petersburg', $projected['city']);
        $this->assertNotNull($projected['latitude']);

        $this->assertStringNotContainsString('77 Withheld Way', json_encode($payload));
    }

    /**
     * A record whose raw payload cannot be read at all denies everything. "We
     * could not determine the permissions" is not "there were no permissions".
     *
     * @test
     */
    public function an_unreadable_record_is_excluded(): void
    {
        $visible = $this->makeListing();

        $broken = $this->makeListing();
        $broken->forceFill(['raw_json' => 'not json at all'])->save();

        $this->assertSame([$visible->listing_key], $this->keys());
    }

    /* ── L / M — prices mean different things ───────────────────────────── */

    /** @test */
    public function a_sale_price_carries_no_period_suffix(): void
    {
        $this->makeListing(['list_price' => 939000]);

        $projected = $this->listings()['listings'][0];

        $this->assertSame('$939,000', $projected['display_price']);
        $this->assertNull($projected['price_qualifier']);
    }

    /**
     * "/mo" is NOT universal for rentals. The live cache carries Seasonal 78,
     * Annually 19, Weekly 16 and Daily 2 alongside Monthly 386 — presenting a
     * seasonal rent as a monthly figure misprices the property by a factor.
     *
     * @test
     */
    public function a_rental_price_carries_the_period_the_feed_actually_reports(): void
    {
        $cases = [
            'Monthly'  => '/mo',
            'Weekly'   => '/wk',
            'Annually' => '/yr',
            'Daily'    => '/day',
            'Seasonal' => ' seasonal',
        ];

        foreach ($cases as $frequency => $suffix) {
            $listing = $this->makeRental(['list_price' => 3495], ['LeaseAmountFrequency' => $frequency]);

            $projected = collect($this->listings()['listings'])
                ->firstWhere('id', $listing->listing_key);

            $this->assertSame('$3,495' . $suffix, $projected['display_price'], "frequency {$frequency}");
        }
    }

    /**
     * A rental with no stated frequency shows the amount without a period
     * rather than with an assumed monthly one.
     *
     * @test
     */
    public function a_rental_with_no_stated_frequency_gets_no_assumed_period(): void
    {
        $this->makeRental(['list_price' => 3495], ['LeaseAmountFrequency' => null]);

        $projected = $this->listings()['listings'][0];

        $this->assertSame('$3,495', $projected['display_price']);
        $this->assertNull($projected['price_qualifier']);
    }

    /* ── N / O — canonical destinations ─────────────────────────────────── */

    /** @test */
    public function a_sale_listing_links_to_the_canonical_seller_page(): void
    {
        $listing = $this->makeListing();
        $auction = $this->linkSellerListing($listing->listing_key);

        $projected = $this->listings()['listings'][0];

        $this->assertSame(route('offer.listing.seller.view', ['id' => $auction->id]), $projected['canonical_url']);
        $this->assertTrue($projected['showing_available']);
    }

    /** @test */
    public function a_rental_listing_links_to_the_canonical_landlord_page(): void
    {
        $listing = $this->makeRental();
        $auction = $this->linkLandlordListing($listing->listing_key);

        $projected = $this->listings()['listings'][0];

        $this->assertSame(route('offer.listing.landlord.view', ['id' => $auction->id]), $projected['canonical_url']);
    }

    /**
     * A draft, unapproved or archived listing is not something the public can
     * open, so Explore must not advertise a link that 404s.
     *
     * @test
     */
    public function a_listing_the_public_cannot_open_is_not_offered_as_a_destination(): void
    {
        foreach ([
            ['is_draft' => true,  'is_approved' => true,  'is_archived' => false],
            ['is_draft' => false, 'is_approved' => false, 'is_archived' => false],
            ['is_draft' => false, 'is_approved' => true,  'is_archived' => true],
        ] as $state) {
            $listing = $this->makeListing();
            $this->linkSellerListing($listing->listing_key, $state);

            $projected = collect($this->listings()['listings'])->firstWhere('id', $listing->listing_key);

            $this->assertNull($projected['canonical_url'], json_encode($state));
            $this->assertFalse($projected['showing_available'], 'no showing without a live listing');
        }
    }

    /**
     * §22 / §37. A showing can only be requested against a real BidYourOffer
     * listing through the existing authenticated flow. An MLS-only property has
     * no showing workflow and must not offer one.
     *
     * @test
     */
    public function an_mls_only_property_offers_no_showing_and_no_canonical_link(): void
    {
        $this->makeListing();

        $projected = $this->listings()['listings'][0];

        $this->assertNull($projected['canonical_url']);
        $this->assertFalse($projected['showing_available']);
    }

    /**
     * The MLS detail page sits inside the auth middleware group, so an
     * anonymous visitor gets no control rather than one that bounces them to a
     * login screen.
     *
     * @test
     */
    public function the_mls_detail_link_is_offered_only_to_a_signed_in_visitor(): void
    {
        $listing = $this->makeListing();

        $this->assertNull($this->listings()['listings'][0]['detail_url']);

        $this->actingAs(User::factory()->create());

        $this->assertSame(
            route('stellar.property.show', ['listingKey' => $listing->listing_key]),
            $this->listings()['listings'][0]['detail_url']
        );
    }

    /* ── P / Q — media ──────────────────────────────────────────────────── */

    /** @test */
    public function media_capability_reports_what_may_actually_be_shown(): void
    {
        $this->makeListing();

        $projected = $this->listings()['listings'][0];

        $this->assertTrue($projected['has_photos']);
        $this->assertSame(3, $projected['photo_count']);
        $this->assertSame('https://cdn.example.com/1.jpg', $projected['primary_thumbnail']);
        $this->assertTrue($projected['has_virtual_tour']);
    }

    /** @test */
    public function the_branded_tour_is_never_offered(): void
    {
        $this->makeListing([], [
            'VirtualTourURLUnbranded' => null,
            'VirtualTourURLBranded'   => 'https://branded.example.com/tour',
            'VirtualTourURLZillow'    => 'https://zillow.example.com/tour',
        ]);

        $payload = $this->listings();

        $this->assertFalse($payload['listings'][0]['has_virtual_tour']);
        $this->assertStringNotContainsString('branded.example.com', json_encode($payload));
        $this->assertStringNotContainsString('zillow.example.com', json_encode($payload));
    }

    /** @test */
    public function media_the_feed_marks_private_is_not_published(): void
    {
        $this->makeListing([], [
            'Media' => [
                ['MediaKey' => 'P1', 'MediaURL' => 'https://cdn.example.com/private.jpg', 'Order' => 1, 'MediaCategory' => 'Photo', 'PermittedForPublicDisplay' => false],
                ['MediaKey' => 'P2', 'MediaURL' => 'https://cdn.example.com/public.jpg',  'Order' => 2, 'MediaCategory' => 'Photo', 'PermittedForPublicDisplay' => true],
            ],
        ]);

        $payload = $this->listings();

        $this->assertSame('https://cdn.example.com/public.jpg', $payload['listings'][0]['primary_thumbnail']);
        $this->assertStringNotContainsString('private.jpg', json_encode($payload));
    }

    /**
     * §19 / §49-Q. Tour beats Video beats Photos, and Google Photorealistic 3D
     * — the exterior world — is never reported as an interior tour.
     *
     * @test
     */
    public function media_priority_is_tour_then_video_then_photos(): void
    {
        $capability = app(\App\Services\Explore\ExploreMediaCapability::class);

        $this->assertSame('tour',   $capability->primaryAction(true,  true,  true));
        $this->assertSame('tour',   $capability->primaryAction(true,  false, false));
        $this->assertSame('video',  $capability->primaryAction(false, true,  true));
        $this->assertSame('photos', $capability->primaryAction(false, false, true));
        $this->assertNull($capability->primaryAction(false, false, false));
    }

    /** @test */
    public function a_listing_with_no_publishable_media_reports_none(): void
    {
        $this->makeListing([], ['Media' => [], 'VirtualTourURLUnbranded' => null]);

        $projected = $this->listings()['listings'][0];

        $this->assertFalse($projected['has_photos']);
        $this->assertFalse($projected['has_virtual_tour']);
        $this->assertFalse($projected['has_video']);
        $this->assertNull($projected['primary_thumbnail']);
    }

    /**
     * Media is re-decided at render time, not frozen at import: the licence
     * flags are read on every request, so turning the media flag off takes
     * effect immediately.
     *
     * @test
     */
    public function photographs_disappear_when_the_media_licence_flag_is_withdrawn(): void
    {
        $this->makeListing();

        config(['mls_media.license_acknowledged' => false]);

        $projected = $this->listings()['listings'][0];

        $this->assertFalse($projected['has_photos']);
        $this->assertNull($projected['primary_thumbnail']);
        // The tour is a listing field, not gallery media, and is unaffected.
        $this->assertTrue($projected['has_virtual_tour']);
    }

    /* ── T — panel ──────────────────────────────────────────────────────── */

    /** @test */
    public function the_panel_endpoint_returns_the_selected_property(): void
    {
        $listing = $this->makeListing();

        $this->getJson('/api/explore/listings/' . $listing->listing_key)
            ->assertOk()
            ->assertJsonPath('listing.id', $listing->listing_key)
            ->assertJsonPath('listing.transaction_type', 'sale')
            ->assertJsonPath('access_tier', 'public_idx');
    }

    /**
     * An ineligible listing 404s identically to one that does not exist. A
     * distinguishable refusal confirms to a prober that a withheld listing is
     * real.
     *
     * @test
     */
    public function the_panel_endpoint_refuses_an_ineligible_listing_indistinguishably(): void
    {
        $withheld = $this->makeListing([], ['IDXParticipationYN' => false]);

        $withheldResponse = $this->getJson('/api/explore/listings/' . $withheld->listing_key);
        $missingResponse  = $this->getJson('/api/explore/listings/LKDOESNOTEXIST');

        $withheldResponse->assertStatus(404);
        $missingResponse->assertStatus(404);
        $this->assertSame($missingResponse->json(), $withheldResponse->json());
    }

    /* ── U — stale-response guard ───────────────────────────────────────── */

    /**
     * The client stamps each request and the server echoes it, so an older
     * response arriving after a newer one can be discarded rather than painted
     * over a viewport that has moved on.
     *
     * @test
     */
    public function the_request_sequence_is_echoed_so_stale_responses_can_be_discarded(): void
    {
        $this->makeListing();

        $this->assertSame(7, $this->listings(['seq' => 7])['seq']);
        $this->assertSame(8, $this->listings(['seq' => 8])['seq']);

        // Echoed on a refusal too, or the client could never retire a failed
        // request from its sequence.
        $this->getJson('/api/explore/listings?bbox=nonsense&seq=9')
            ->assertStatus(422)
            ->assertJsonPath('seq', 9);
    }

    /* ── limits ─────────────────────────────────────────────────────────── */

    /** @test */
    public function the_result_count_is_bounded_and_reported_as_truncated(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->makeListing();
        }

        $payload = $this->listings(['limit' => 4]);

        $this->assertSame(4, $payload['count']);
        $this->assertTrue($payload['truncated']);
    }

    /** @test */
    public function a_requested_limit_cannot_exceed_the_configured_ceiling(): void
    {
        $this->makeListing();

        $this->assertSame(
            (int) config('explore.viewport.result_ceiling'),
            $this->listings(['limit' => 100000])['limit']
        );
    }

    /**
     * Phase 1 does not invent a match score for a property that has none.
     *
     * @test
     */
    public function no_match_score_is_fabricated(): void
    {
        $this->makeListing();

        $this->assertNull($this->listings()['listings'][0]['match_score']);
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    private function linkSellerListing(string $listingKey, array $state = []): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create(array_merge([
            'user_id'     => User::factory()->create()->id,
            'is_draft'    => false,
            'is_approved' => true,
            'is_archived' => false,
        ], $state));

        $auction->saveMeta(Meta::META_LISTING_KEY, $listingKey);
        $auction->saveMeta('workflow_type', 'offer_listing');

        return $auction->fresh();
    }

    private function linkLandlordListing(string $listingKey, array $state = []): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create(array_merge([
            'user_id'     => User::factory()->create()->id,
            'is_draft'    => false,
            'is_approved' => true,
            'is_archived' => false,
        ], $state));

        $auction->saveMeta(Meta::META_LISTING_KEY, $listingKey);
        $auction->saveMeta('workflow_type', 'offer_listing');

        return $auction->fresh();
    }
}
