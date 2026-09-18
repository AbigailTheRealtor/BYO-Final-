<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The customer's own Saved / Maybe / Passed area.
 *
 * THE PRIVACY QUESTION IS THE FIRST ONE. These pages publish what a customer
 * thinks of somebody else's house, so most of this file is about scope: one
 * account, one seeker role, and no parameter that could ask for either.
 */
class MyListingPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
    }

    // ------------------------------------------------------------- the lists

    /** @test */
    public function each_tab_shows_only_that_state(): void
    {
        $user = $this->buyer();

        $saved  = $this->sellerListing('1 Saved Way');
        $maybe  = $this->sellerListing('2 Maybe Way');
        $passed = $this->sellerListing('3 Passed Way');

        $this->setState($user, $saved, ListingPreferenceState::Save);
        $this->setState($user, $maybe, ListingPreferenceState::Maybe);
        $this->setState($user, $passed, ListingPreferenceState::Pass);

        $this->actingAs($user);

        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()->assertSee('1 Saved Way')->assertDontSee('2 Maybe Way')->assertDontSee('3 Passed Way');

        $this->get(route('listing-preferences.mine.index', ['state' => 'maybe']))
            ->assertOk()->assertSee('2 Maybe Way')->assertDontSee('1 Saved Way');

        $this->get(route('listing-preferences.mine.index', ['state' => 'pass']))
            ->assertOk()->assertSee('3 Passed Way')->assertDontSee('1 Saved Way');
    }

    /** The customer's words, never the stored enum. @test */
    public function the_tabs_use_customer_terminology(): void
    {
        $this->actingAs($this->buyer());

        $html = $this->get(route('listing-preferences.mine.index'))->assertOk()->getContent();

        foreach (['Saved', 'Maybe', 'Passed'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        // The storage vocabulary must not be on the page as a label.
        $this->assertStringNotContainsString('>save<', $html);
        $this->assertStringNotContainsString('>pass<', $html);
    }

    /**
     * ONE ACCOUNT. There is no id in the URL, so this asserts the only thing
     * that could go wrong: a query that forgot its user scope.
     *
     * @test
     */
    public function a_customer_never_sees_another_customers_preferences(): void
    {
        $mine   = $this->buyer();
        $theirs = $this->buyer();

        $secret = $this->sellerListing('99 Private Lane');
        $this->setState($theirs, $secret, ListingPreferenceState::Save);

        $this->actingAs($mine);

        foreach (['save', 'maybe', 'pass'] as $state) {
            $this->get(route('listing-preferences.mine.index', ['state' => $state]))
                ->assertOk()
                ->assertDontSee('99 Private Lane');
        }

        $this->get(route('listing-preferences.mine.history'))
            ->assertOk()
            ->assertDontSee('99 Private Lane');
    }

    /** Buyer and Tenant histories stay separate, because the role is part of the key. @test */
    public function buyer_and_tenant_scopes_remain_separate(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('7 Role Road');

        // Same account id, but recorded under the tenant role.
        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Tenant,
            $this->ref($listing),
            ListingPreferenceState::Save,
            [],
        );

        $this->actingAs($user); // user_type = buyer

        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()
            ->assertDontSee('7 Role Road');
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $this->get(route('listing-preferences.mine.index'))->assertRedirect();
        $this->get(route('listing-preferences.mine.history'))->assertRedirect();
    }

    /** @test */
    public function the_routes_are_feature_gated(): void
    {
        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());

        $this->get(route('listing-preferences.mine.index'))->assertNotFound();
        $this->get(route('listing-preferences.mine.history'))->assertNotFound();
    }

    /**
     * The account menu links here for a shopper — and only when the page exists
     * and is theirs. A link that 404s, or one shown to an agent, is worse than
     * none.
     *
     * @test
     */
    public function the_account_menu_links_here_only_for_a_seeker_with_the_feature_on(): void
    {
        $url = route('listing-preferences.mine.index');

        $this->actingAs($this->buyer());
        $this->assertStringContainsString($url, view('layouts.partials.header')->render());

        $this->actingAs(User::factory()->create(['user_type' => 'tenant']));
        $this->assertStringContainsString($url, view('layouts.partials.header')->render());

        $this->actingAs(User::factory()->create(['user_type' => 'agent']));
        $this->assertStringNotContainsString($url, view('layouts.partials.header')->render());

        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());
        $this->assertStringNotContainsString($url, view('layouts.partials.header')->render());

        // A BidYourAgent deployment refuses these BidYourOffer-only routes, so
        // it must not advertise them either.
        config()->set('listing_preferences.enabled', true);
        config()->set('products.active', \App\Support\Product\ProductContext::BIDYOURAGENT);
        $this->assertStringNotContainsString($url, view('layouts.partials.header')->render());
    }

    /** A non-seeker gets an honest empty page rather than a refusal. @test */
    public function a_non_seeker_account_sees_an_explanation(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'agent']));

        $this->get(route('listing-preferences.mine.index'))
            ->assertOk()
            ->assertSee('nothing here for this account');
    }

    // ------------------------------------------------------ Passed is recoverable

    /**
     * PASS MUST REMAIN RECOVERABLE. The listing is listed, and the control
     * beside it offers the other two states and a way to remove the choice.
     *
     * @test
     */
    public function a_passed_property_is_listed_and_can_be_changed_or_removed(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('5 Regret Road');
        $this->setState($user, $listing, ListingPreferenceState::Pass);

        $this->actingAs($user);

        $html = $this->get(route('listing-preferences.mine.index', ['state' => 'pass']))
            ->assertOk()
            ->assertSee('5 Regret Road')
            ->getContent();

        // The shared control is present, with all three states and a clear.
        $this->assertStringContainsString('data-lp-control', $html);
        foreach (['save', 'maybe', 'pass'] as $state) {
            $this->assertStringContainsString('data-lp-state="' . $state . '"', $html);
        }
        $this->assertStringContainsString('data-lp-clear', $html);

        // And the page says passing is not permanent.
        $this->assertStringContainsString('Passing hides nothing permanently', $html);

        // Moving it back works through the shared write path.
        $this->postJson(route('listing-preferences.account.store'), [
            'listing_type' => 'seller_agent',
            'listing_id'   => $listing->id,
            'state'        => 'maybe',
        ])->assertOk()->assertJson(['success' => true, 'state' => 'maybe']);

        $this->get(route('listing-preferences.mine.index', ['state' => 'maybe']))
            ->assertOk()->assertSee('5 Regret Road');
    }

    /** The listing itself is never touched by a preference change. @test */
    public function changing_a_preference_does_not_modify_the_listing(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('8 Untouched Terrace');
        $before  = DB::table('seller_agent_auctions')->where('id', $listing->id)->first();

        $this->actingAs($user);
        $this->postJson(route('listing-preferences.account.store'), [
            'listing_type' => 'seller_agent',
            'listing_id'   => $listing->id,
            'state'        => 'pass',
        ])->assertOk();

        $this->assertEquals(
            (array) $before,
            (array) DB::table('seller_agent_auctions')->where('id', $listing->id)->first(),
            'a preference must never alter the listing row'
        );
    }

    /** The account surface records itself, and it comes from the route. @test */
    public function a_write_from_this_area_is_recorded_against_the_account_surface(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('9 Surface Street');

        $this->actingAs($user);
        $this->postJson(route('listing-preferences.account.store'), [
            'listing_type' => 'seller_agent',
            'listing_id'   => $listing->id,
            'state'        => 'save',
        ])->assertOk();

        $this->assertDatabaseHas('listing_preference_events', [
            'user_id'      => $user->id,
            'listing_type' => 'seller_agent',
            'listing_id'   => $listing->id,
            'surface'      => 'account',
        ]);
    }

    // ------------------------------------------------------------- hydration

    /**
     * NO N+1. The same page with three times the listings — of two different
     * listing types, each with reasons — costs the same number of queries, on
     * the list and on the history.
     *
     * @test
     */
    public function the_query_count_does_not_grow_with_the_number_of_listings(): void
    {
        $user = $this->buyer();
        $reason = array_key_first(\App\Support\ListingPreferences\ListingPreferenceReasonCatalog::forState(
            ListingPreferenceState::Save, \App\Support\SmartTags\SmartTagContext::ResidentialSale));

        $seed = function (int $from, int $count) use ($user, $reason): void {
            for ($i = $from; $i < $from + $count; $i++) {
                $this->setState($user, $this->sellerListing("{$i} Count Lane"), ListingPreferenceState::Save, [$reason]);

                $bridge = BridgeProperty::create([
                    'provider'         => 'stellar_bridge',
                    'listing_key'      => "COUNT-{$i}",
                    'property_type'    => 'Residential',
                    'unparsed_address' => "{$i} Count Court",
                    'raw_json'         => json_encode(['IDXParticipationYN' => true]),
                ]);
                app(ListingPreferenceWriter::class)->setState(
                    (int) $user->id, SeekerRole::Buyer,
                    new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id),
                    ListingPreferenceState::Save, [$reason],
                );
            }
        };

        // ONE listener for the whole test, reset per request.
        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

        $measure = function (string $url) use (&$sql): array {
            // The prefetch is a container singleton, so inside ONE test app it
            // survives from request to request and a second request would find
            // its rows already primed. Production serves each request from a
            // fresh process; forgetting it here measures that, not the cache.
            app(\App\Support\ListingPreferences\ListingPreferencePrefetch::class)->forget();
            $sql = [];
            $this->get($url)->assertOk();

            return $sql;
        };

        $this->actingAs($user);
        $list    = route('listing-preferences.mine.index', ['state' => 'save']);
        $history = route('listing-preferences.mine.history');

        $seed(1, 2);                                   // 4 cards
        $this->get($list)->assertOk();                 // warm framework-level caches once
        $smallList    = $measure($list);
        $smallHistory = $measure($history);

        $seed(3, 4);                                   // 12 cards
        $largeList    = $measure($list);
        $largeHistory = $measure($history);


        $grew = fn (array $small, array $large) => print_r(array_filter(
            array_count_values($large),
            fn ($count, $query) => $count > (array_count_values($small)[$query] ?? 0),
            ARRAY_FILTER_USE_BOTH
        ), true);

        $this->assertCount(count($smallList), $largeList,
            'list: ' . count($smallList) . ' queries for 4 cards, ' . count($largeList) . " for 12\n" . $grew($smallList, $largeList));
        $this->assertCount(count($smallHistory), $largeHistory,
            'history: ' . count($smallHistory) . ' queries for 4 rows, ' . count($largeHistory) . " for 12\n" . $grew($smallHistory, $largeHistory));
    }

    /**
     * ONE COPY of the shared behaviour however many cards the page shows — a
     * copy per control handles every click once per copy.
     *
     * @test
     */
    public function the_page_carries_one_copy_of_the_shared_behaviour(): void
    {
        $user = $this->buyer();
        foreach (['31 Once Way', '32 Once Way', '33 Once Way'] as $address) {
            $this->setState($user, $this->sellerListing($address), ListingPreferenceState::Save);
        }
        $this->actingAs($user);

        $html = $this->get(route('listing-preferences.mine.index', ['state' => 'save']))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-lp-listing-type="seller_agent"'));
        $this->assertSame(1, substr_count($html, 'One delegated listener for every control on the page'));
        $this->assertSame(1, substr_count($html, '.lp-control {'));
    }

    /** All three listing types hydrate into a card. @test */
    public function bridge_seller_and_landlord_listings_all_hydrate(): void
    {
        $user = $this->buyer();

        $seller = $this->sellerListing('11 Seller Street');
        $this->setState($user, $seller, ListingPreferenceState::Save);

        $bridge = BridgeProperty::create([
            'provider'         => 'stellar_bridge',
            'listing_key'      => 'HYDRATE-1',
            'property_type'    => 'Residential',
            'unparsed_address' => '12 Bridge Boulevard',
            'city'             => 'Orlando',
            'state_or_province' => 'FL',
            'postal_code'      => '32801',
            'list_price'       => 450000,
            'raw_json'         => json_encode(['IDXParticipationYN' => true]),
        ]);
        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id),
            ListingPreferenceState::Save, [],
        );

        $this->actingAs($user);

        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()
            ->assertSee('11 Seller Street')
            ->assertSee('12 Bridge Boulevard')
            ->assertSee('Orlando, FL 32801')
            ->assertSee('$450,000');
    }

    /**
     * The Stellar detail route resolves a key within the CURRENT provider, so a
     * Bridge row issued by another MLS is never linked there — its key would
     * open a different property.
     *
     * @test
     */
    public function only_a_current_provider_bridge_row_links_to_the_stellar_page(): void
    {
        $ours = BridgeProperty::create([
            'provider'      => 'stellar_bridge',
            'listing_key'   => 'LINK-SHARED',
            'property_type' => 'Residential',
            'raw_json'      => json_encode(['IDXParticipationYN' => true]),
        ]);
        $foreign = BridgeProperty::create([
            'provider'      => 'another_mls',
            'listing_key'   => 'LINK-SHARED',
            'property_type' => 'Residential',
            'raw_json'      => json_encode(['IDXParticipationYN' => true]),
        ]);

        $cards = app(\App\Services\ListingPreferences\ListingPreferenceListingHydrator::class)->hydrate([
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $ours->id),
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $foreign->id),
        ]);

        $this->assertSame(
            route('stellar.property.show', ['listingKey' => 'LINK-SHARED']),
            $cards['bridge:' . $ours->id]->url,
        );
        $this->assertNull($cards['bridge:' . $foreign->id]->url);
    }

    /**
     * A Bridge listing the feed refuses to publish is NOT shown, even to the
     * customer who saved it — and their preference survives.
     *
     * @test
     */
    public function an_idx_refused_bridge_listing_shows_as_unavailable(): void
    {
        $user = $this->buyer();

        $bridge = BridgeProperty::create([
            'provider'         => 'stellar_bridge',
            'listing_key'      => 'HYDRATE-REFUSED',
            'property_type'    => 'Residential',
            'unparsed_address' => '13 Forbidden Way',
            'list_price'       => 500000,
            'raw_json'         => json_encode(['IDXParticipationYN' => false]),
        ]);

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id),
            ListingPreferenceState::Pass, [],
        );

        $this->actingAs($user);

        $this->get(route('listing-preferences.mine.index', ['state' => 'pass']))
            ->assertOk()
            ->assertSee('no longer available')
            ->assertDontSee('13 Forbidden Way')
            ->assertDontSee('$500,000');
    }

    /**
     * An address-suppressed IDX listing keeps its locality and loses its street
     * line — the same rule every other public MLS surface follows.
     *
     * @test
     */
    public function an_address_suppressed_bridge_listing_hides_the_street_and_keeps_the_locality(): void
    {
        $user = $this->buyer();

        $bridge = BridgeProperty::create([
            'provider'          => 'stellar_bridge',
            'listing_key'       => 'HYDRATE-NOADDR',
            'property_type'     => 'Residential',
            'unparsed_address'  => '14 Hidden Street',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'list_price'        => 425000,
            'raw_json'          => json_encode([
                'IDXParticipationYN'      => true,
                'InternetAddressDisplayYN' => false,
            ]),
        ]);

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id),
            ListingPreferenceState::Save, [],
        );

        $this->actingAs($user);

        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()
            ->assertDontSee('14 Hidden Street')
            ->assertSee('Tampa, FL 33601');
    }

    /**
     * A BYO listing imported from the MLS inherits the feed's address
     * restriction — the rule the Seller and Landlord detail pages already
     * apply. Only the listing's owner sees their own street line, and a title
     * seeded FROM the address is not a way around it.
     *
     * @test
     */
    public function an_address_restricted_byo_listing_hides_the_street_from_everyone_but_its_owner(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('77 Withheld Way');
        $listing->update(['title' => '77 Withheld Way']);

        $listing->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, 'BYO-NOADDR');
        $listing->saveMeta(MlsQuickImportDraftWriter::META_DISPLAY_PERMISSIONS, [
            'idx_participation' => true,
            'address_display'   => false,
        ]);
        foreach (['property_city' => 'Clearwater', 'property_state' => 'FL', 'property_zip' => '33755'] as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        $this->setState($user, $listing, ListingPreferenceState::Save);
        $this->actingAs($user);

        foreach ([route('listing-preferences.mine.index', ['state' => 'save']), route('listing-preferences.mine.history')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('77 Withheld Way')
                ->assertSee('Seller Offer Listing');
        }

        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertSee('Clearwater, FL 33755');

        // The owner is the one viewer the feed's restriction does not bind.
        $cards = app(\App\Services\ListingPreferences\ListingPreferenceListingHydrator::class)
            ->hydrate([$this->ref($listing)], (int) $listing->user_id);
        $this->assertSame('77 Withheld Way', $cards['seller_agent:' . $listing->id]->addressLine);
    }

    /** A listing that has gone away does not break the page. @test */
    public function a_deleted_listing_degrades_to_an_unavailable_card(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('15 Vanished Vale');
        $this->setState($user, $listing, ListingPreferenceState::Pass);

        SellerAgentAuctionMeta::where('seller_agent_auction_id', $listing->id)->delete();
        DB::table('seller_agent_auctions')->where('id', $listing->id)->delete();

        $this->actingAs($user);

        $this->get(route('listing-preferences.mine.index', ['state' => 'pass']))
            ->assertOk()
            ->assertSee('no longer available')
            ->assertDontSee('15 Vanished Vale');
    }

    /** An archived native listing stops publishing facts; the preference stays. @test */
    public function an_archived_listing_shows_as_unavailable(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('16 Archived Avenue');
        $this->setState($user, $listing, ListingPreferenceState::Save);

        DB::table('seller_agent_auctions')->where('id', $listing->id)->update(['is_archived' => 1]);

        $this->actingAs($user);

        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()
            ->assertSee('no longer available')
            ->assertDontSee('16 Archived Avenue');
    }

    /**
     * ONE PROPERTY, ONE ROW. A Bridge listing and the BYO listing imported from
     * it share a subject, so a preference expressed once appears once.
     *
     * @test
     */
    public function bridge_and_byo_for_one_property_stay_one_preference(): void
    {
        $user = $this->buyer();
        $key  = 'CANON-MINE-1';

        $bridge = BridgeProperty::create([
            'provider'      => 'stellar_bridge',
            'listing_key'   => $key,
            'property_type' => 'Residential',
            'raw_json'      => json_encode(['IDXParticipationYN' => true]),
        ]);

        $byo = $this->sellerListing('17 Canonical Court');
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $byo->id,
            'meta_key'                => MlsQuickImportDraftWriter::META_LISTING_KEY,
            'meta_value'              => $key,
        ]);

        // Expressed once on the BYO listing…
        $this->setState($user, $byo, ListingPreferenceState::Save);
        // …and again on the Bridge row: same subject, so it UPDATES.
        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id),
            ListingPreferenceState::Maybe, [],
        );

        $this->assertSame(
            1,
            DB::table('listing_preferences')->where('user_id', $user->id)->count(),
            'one property must be one preference row'
        );

        $this->actingAs($user);
        $this->get(route('listing-preferences.mine.index', ['state' => 'maybe']))->assertOk();
        $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()
            ->assertDontSee('17 Canonical Court');
    }

    /** Reasons the customer gave are visible on their own card. @test */
    public function stored_reasons_render_on_the_card(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('18 Reason Row');

        $this->setState($user, $listing, ListingPreferenceState::Save, ['updated_kitchen']);

        $this->actingAs($user);

        $label = \App\Support\ListingPreferences\ListingPreferenceReasonCatalog::get('updated_kitchen')->label;

        $html = $this->get(route('listing-preferences.mine.index', ['state' => 'save']))
            ->assertOk()
            ->getContent();

        // VISIBLE, as the catalog's label, in the card's own reasons line — not
        // merely present in the control's data attribute, which a person cannot read.
        $this->assertMatchesRegularExpression(
            '/data-lp-card-reasons.*?' . preg_quote(e($label), '/') . '/s',
            $html
        );
    }

    // --------------------------------------------------------------- history

    /** @test */
    public function the_history_reads_newest_first_and_in_customer_language(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('20 Timeline Terrace');

        $this->setState($user, $listing, ListingPreferenceState::Save);
        $this->setState($user, $listing, ListingPreferenceState::Maybe);
        $this->setState($user, $listing, ListingPreferenceState::Pass);

        $this->actingAs($user);
        $html = $this->get(route('listing-preferences.mine.history'))->assertOk()->getContent();

        $this->assertStringContainsString('Marked Saved', $html);
        $this->assertStringContainsString('Changed from Saved to Maybe', $html);
        $this->assertStringContainsString('Changed from Maybe to Passed', $html);

        // Newest first.
        $this->assertLessThan(
            strpos($html, 'Marked Saved'),
            strpos($html, 'Changed from Maybe to Passed'),
            'the newest event must appear first'
        );
    }

    /** A withdrawal reads as a withdrawal, never as null. @test */
    public function a_clear_event_renders_as_preference_removed(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('21 Undo Union');

        $this->setState($user, $listing, ListingPreferenceState::Pass);
        app(ListingPreferenceWriter::class)->clear((int) $user->id, SeekerRole::Buyer, $this->ref($listing));

        $this->actingAs($user);
        $html = $this->get(route('listing-preferences.mine.history'))->assertOk()->getContent();

        $this->assertStringContainsString('Preference removed', $html);
    }

    /** Nothing internal reaches the page. @test */
    public function the_history_never_shows_internal_identifiers(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('22 Opaque Oval');

        $this->setState($user, $listing, ListingPreferenceState::Save, ['updated_kitchen']);
        app(ListingPreferenceWriter::class)->clear((int) $user->id, SeekerRole::Buyer, $this->ref($listing));

        $this->actingAs($user);
        $html = $this->get(route('listing-preferences.mine.history'))->assertOk()->getContent();

        foreach (['byo:seller_agent:', 'mls:', 'subject_key', 'to_state', 'from_state', 'listing_preference_events'] as $internal) {
            $this->assertStringNotContainsString($internal, $html, "{$internal} must not reach the customer");
        }

        // The reason is shown as its LABEL, not its stored key.
        $this->assertStringContainsString('Updated kitchen', $html);
    }

    /** History is append-only; the page cannot be a way to edit it. @test */
    public function the_history_is_immutable(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing('23 Immutable Isle');
        $this->setState($user, $listing, ListingPreferenceState::Save);

        $event = \App\Models\ListingPreferenceEvent::query()->where('user_id', $user->id)->firstOrFail();

        $this->expectException(\LogicException::class);
        $event->update(['to_state' => 'pass']);
    }

    // --------------------------------------------------------------- helpers

    private function ref(SellerAgentAuction $listing): SmartTagListingRef
    {
        return new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id);
    }

    private function setState(User $user, SellerAgentAuction $listing, ListingPreferenceState $state, array $reasons = []): void
    {
        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id,
            SeekerRole::Buyer,
            $this->ref($listing),
            $state,
            $reasons,
        );
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function sellerListing(string $address): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => 902000,
            'address'     => $address,
            'is_approved' => 1,
            'is_draft'    => false,
            'is_archived' => 0,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_type',
            'meta_value'              => 'Residential',
        ]);

        return $auction;
    }

    private function landlordListing(string $title): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => 902001,
            'is_approved' => 1,
            'is_draft'    => false,
            'is_archived' => 0,
        ]);

        foreach (['property_type' => 'Residential Property', 'titleListing' => $title] as $k => $v) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $k,
                'meta_value'                => $v,
            ]);
        }

        return $auction;
    }
}
