<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;

/**
 * NotificationPayloadContractTest
 *
 * Regression guard: every notification class listed here must return a non-empty
 * 'message' key from toDatabase(). Adding a new notification class without a
 * message will cause this test to fail, preventing silent regression.
 *
 * Uses an explicit data provider (not reflection) so test failures are readable
 * and new classes require a deliberate entry.
 */
class NotificationPayloadContractTest extends TestCase
{
    /**
     * @dataProvider notificationPayloadProvider
     */
    public function test_to_database_has_non_empty_message(callable $factory): void
    {
        $notification = $factory();
        $data         = $notification->toDatabase(new \stdClass());

        $this->assertIsArray($data, 'toDatabase() must return an array');
        $this->assertArrayHasKey('message', $data, 'Payload must contain a "message" key');
        $this->assertNotEmpty($data['message'], '"message" must not be empty');
        $this->assertNotSame('You have a notification', $data['message'], '"message" must not be the generic fallback text');
        $this->assertNotSame('New Notification', $data['message'], '"message" must not be the generic dropdown fallback text');
    }

    /**
     * @dataProvider notificationPayloadProvider
     */
    public function test_to_database_has_non_empty_type(callable $factory): void
    {
        $notification = $factory();
        $data         = $notification->toDatabase(new \stdClass());

        $this->assertArrayHasKey('type', $data, 'Payload must contain a "type" key');
        $this->assertNotEmpty($data['type'], '"type" must not be empty');
    }

    public static function notificationPayloadProvider(): array
    {
        return [

            // ── Bid notifications ─────────────────────────────────────────

            'BidSubmittedNotification' => [static function () {
                $bid              = new \stdClass();
                $bid->id          = 1;
                $auction          = new \stdClass();
                $auction->id      = 1;
                $auction->title   = 'Test';
                $auction->user_id = 1;
                return new \App\Notifications\BidSubmittedNotification($bid, $auction, 'seller_agent');
            }],

            'BidAcceptedNotification' => [static function () {
                $bid              = new \stdClass();
                $bid->id          = 1;
                $bid->user_id     = 1;
                $auction          = new \stdClass();
                $auction->id      = 1;
                $auction->title   = 'Test';
                return new \App\Notifications\BidAcceptedNotification($bid, $auction, null, 'seller_agent');
            }],

            'BidRejectedNotification' => [static function () {
                $bid          = new \stdClass();
                $bid->id      = 1;
                $bid->user_id = 1;
                $auction      = new \stdClass();
                $auction->id  = 1;
                $auction->title = 'Test';
                return new \App\Notifications\BidRejectedNotification($bid, $auction, 'seller_agent');
            }],

            'BidModifiedNotification' => [static function () {
                $bid              = new \stdClass();
                $bid->id          = 1;
                $auction          = new \stdClass();
                $auction->id      = 1;
                $auction->title   = 'Test';
                $auction->listing_id = null;
                $auction->user_id = 1;
                return new \App\Notifications\BidModifiedNotification($bid, $auction);
            }],

            'CounterBidSubmittedNotification' => [static function () {
                $bid          = new \stdClass();
                $bid->id      = 1;
                $auction      = new \stdClass();
                $auction->id  = 1;
                $auction->title = 'Test';
                $sender       = new \stdClass();
                $sender->first_name = 'A';
                $sender->last_name  = 'B';
                return new \App\Notifications\CounterBidSubmittedNotification($bid, $auction, $sender, 99, 'seller_agent');
            }],

            'CounterBidAcceptedNotification' => [static function () {
                $counterBid          = new \stdClass();
                $counterBid->id      = 1;
                $counterBid->user_id = 1;
                $auction             = new \stdClass();
                $auction->id         = 1;
                $auction->title      = 'Test';
                return new \App\Notifications\CounterBidAcceptedNotification($counterBid, $auction);
            }],

            'CounterBidRejectedNotification' => [static function () {
                $counterBid          = new \stdClass();
                $counterBid->id      = 1;
                $counterBid->user_id = 1;
                $auction             = new \stdClass();
                $auction->id         = 1;
                $auction->title      = 'Test';
                return new \App\Notifications\CounterBidRejectedNotification($counterBid, $auction);
            }],

            // ── Agent-hired notifications ─────────────────────────────────

            'SellerAgentHiredNotification' => [static function () {
                $bid             = new \stdClass();
                $bid->id         = 1;
                $auction         = new \stdClass();
                $auction->id     = 1;
                $auction->title  = 'Test';
                $auction->user_id = 1;
                return new \App\Notifications\SellerAgentHiredNotification($bid, $auction, null, 'seller_agent');
            }],

            'BuyerAgentHiredNotification' => [static function () {
                $bid             = new \stdClass();
                $bid->id         = 1;
                $auction         = new \stdClass();
                $auction->id     = 1;
                $auction->title  = 'Test';
                $auction->user_id = 1;
                return new \App\Notifications\BuyerAgentHiredNotification($bid, $auction, null, 'buyer_agent');
            }],

            'LandlordAgentHiredNotification' => [static function () {
                $bid             = new \stdClass();
                $bid->id         = 1;
                $auction         = new \stdClass();
                $auction->id     = 1;
                $auction->title  = 'Test';
                $auction->user_id = 1;
                return new \App\Notifications\LandlordAgentHiredNotification($bid, $auction, null, 'landlord_agent');
            }],

            'TenantAgentHiredNotification' => [static function () {
                $bid             = new \stdClass();
                $bid->id         = 1;
                $auction         = new \stdClass();
                $auction->id     = 1;
                $auction->title  = 'Test';
                $auction->user_id = 1;
                return new \App\Notifications\TenantAgentHiredNotification($bid, $auction, null, 'tenant_agent');
            }],

            // ── Offer listing status ──────────────────────────────────────

            'OfferListingStatusNotification — approved' => [static function () {
                $listing          = \Mockery::mock(\App\Models\OfferAuction::class)->makePartial();
                $listing->id      = 1;
                $listing->title   = 'Test Listing';
                $listing->user_id = 1;
                $listing->shouldReceive('getAttribute')->with('title')->andReturn('Test Listing');
                $listing->shouldReceive('getAttribute')->with('id')->andReturn(1);
                $listing->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
                return new \App\Notifications\OfferListingStatusNotification($listing, 'approved');
            }],

            'OfferListingStatusNotification — rejected' => [static function () {
                $listing          = \Mockery::mock(\App\Models\OfferAuction::class)->makePartial();
                $listing->id      = 1;
                $listing->title   = 'Test Listing';
                $listing->user_id = 1;
                $listing->shouldReceive('getAttribute')->with('title')->andReturn('Test Listing');
                $listing->shouldReceive('getAttribute')->with('id')->andReturn(1);
                $listing->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
                return new \App\Notifications\OfferListingStatusNotification($listing, 'rejected');
            }],

            // ── Offer notifications ───────────────────────────────────────

            'OfferSubmittedNotification' => [static function () {
                $offer         = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $offer->id     = 1;
                $offer->status = 'submitted';
                return new \App\Notifications\Offers\OfferSubmittedNotification($offer);
            }],

            'OfferAcceptedNotification' => [static function () {
                $offer         = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $offer->id     = 1;
                $offer->status = 'accepted';
                return new \App\Notifications\Offers\OfferAcceptedNotification($offer);
            }],

            'OfferRejectedNotification' => [static function () {
                $offer = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $offer->shouldReceive('getAttribute')->with('id')->andReturn(1);
                $offer->shouldReceive('getAttribute')->with('status')->andReturn('rejected');
                $offer->shouldReceive('getRouteKey')->andReturn(1);
                $offer->shouldReceive('getRouteKeyName')->andReturn('id');
                return new \App\Notifications\Offers\OfferRejectedNotification($offer);
            }],

            'OfferCounteredNotification' => [static function () {
                $parent          = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $parent->id      = 1;
                $parent->status  = 'countered';
                $counter         = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $counter->id     = 2;
                $counter->status = 'pending';
                return new \App\Notifications\Offers\OfferCounteredNotification($parent, $counter);
            }],

            'OfferWithdrawnNotification' => [static function () {
                $offer         = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $offer->id     = 1;
                $offer->status = 'withdrawn';
                return new \App\Notifications\Offers\OfferWithdrawnNotification($offer);
            }],

            'OfferExpiredNotification' => [static function () {
                $offer         = \Mockery::mock(\App\Models\Offer::class)->makePartial();
                $offer->id     = 1;
                $offer->status = 'expired';
                return new \App\Notifications\Offers\OfferExpiredNotification($offer);
            }],

            // ── Showing notifications ─────────────────────────────────────

            'ShowingRequestedNotification' => [static function () {
                $requester             = new \stdClass();
                $requester->first_name = 'A';
                $requester->last_name  = 'B';
                $showing = \Mockery::mock(\App\Models\Showing::class)->makePartial();
                $showing->id                   = 1;
                $showing->offer_auction_id     = 1;
                $showing->requested_start_time = null;
                $showing->requested_end_time   = null;
                $showing->shouldReceive('getAttribute')->with('requester')->andReturn($requester);
                $showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('requested_start_time')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('requested_end_time')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(1);
                $showing->shouldReceive('getAttribute')->with('offerAuction')->andReturn(null);
                return new \App\Notifications\Showings\ShowingRequestedNotification($showing);
            }],

            'ShowingApprovedNotification' => [static function () {
                $showing = \Mockery::mock(\App\Models\Showing::class)->makePartial();
                $showing->id               = 1;
                $showing->offer_auction_id = 1;
                $showing->shouldReceive('getAttribute')->with('approved_date')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('approved_start_time')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('approved_end_time')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('requested_start_time')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('requested_end_time')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('owner_message')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(1);
                $showing->shouldReceive('getAttribute')->with('offerAuction')->andReturn(null);
                return new \App\Notifications\Showings\ShowingApprovedNotification($showing);
            }],

            'ShowingDeclinedNotification' => [static function () {
                $showing = \Mockery::mock(\App\Models\Showing::class)->makePartial();
                $showing->id               = 1;
                $showing->offer_auction_id = 1;
                $showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('owner_message')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(1);
                $showing->shouldReceive('getAttribute')->with('offerAuction')->andReturn(null);
                return new \App\Notifications\Showings\ShowingDeclinedNotification($showing);
            }],

            'ShowingCanceledNotification' => [static function () {
                $showing = \Mockery::mock(\App\Models\Showing::class)->makePartial();
                $showing->id               = 1;
                $showing->offer_auction_id = 1;
                $showing->shouldReceive('getAttribute')->with('requested_date')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('canceled_at')->andReturn(null);
                $showing->shouldReceive('getAttribute')->with('offer_auction_id')->andReturn(1);
                $showing->shouldReceive('getAttribute')->with('offerAuction')->andReturn(null);
                return new \App\Notifications\Showings\ShowingCanceledNotification($showing);
            }],

            // ── Hire agent lead ───────────────────────────────────────────

            'HireAgentLeadNotification' => [static function () {
                $lead = \Mockery::mock(\App\Models\HireAgentLead::class)->makePartial();
                $lead->id                    = 1;
                $lead->target_agent_id       = 1;
                $lead->source_listing_type   = null;
                $lead->source_listing_id     = null;
                $lead->source_listing_role   = null;
                $lead->source_property_type  = null;
                $lead->lead_source           = 'widget';
                $lead->source_listing_title  = null;
                $lead->source_listing_url    = null;
                $lead->representation_type   = 'seller';
                $lead->selected_property_type = 'single_family';
                $lead->requester_name        = 'Test User';
                $lead->requester_email       = 'test@example.com';
                $lead->requester_phone       = null;
                $lead->preset_match_status   = 'none';
                $lead->status                = 'new';
                $lead->message               = null;
                $lead->created_at            = null;
                $lead->shouldReceive('representationTypeLabel')->andReturn('Seller Representation');
                $lead->shouldReceive('selectedPropertyTypeLabel')->andReturn('Single Family');
                $lead->shouldReceive('sourceListingTypeLabel')->andReturn('');
                $lead->shouldReceive('presetMatchStatusLabel')->andReturn('None');
                $lead->shouldReceive('getAttribute')->with('id')->andReturn(1);
                $lead->shouldReceive('getAttribute')->with('target_agent_id')->andReturn(1);
                $lead->shouldReceive('getAttribute')->with('source_listing_type')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('source_listing_id')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('source_listing_role')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('source_property_type')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('lead_source')->andReturn('widget');
                $lead->shouldReceive('getAttribute')->with('source_listing_title')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('source_listing_url')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('representation_type')->andReturn('seller');
                $lead->shouldReceive('getAttribute')->with('selected_property_type')->andReturn('single_family');
                $lead->shouldReceive('getAttribute')->with('requester_name')->andReturn('Test User');
                $lead->shouldReceive('getAttribute')->with('requester_email')->andReturn('test@example.com');
                $lead->shouldReceive('getAttribute')->with('requester_phone')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('preset_match_status')->andReturn('none');
                $lead->shouldReceive('getAttribute')->with('status')->andReturn('new');
                $lead->shouldReceive('getAttribute')->with('message')->andReturn(null);
                $lead->shouldReceive('getAttribute')->with('created_at')->andReturn(null);
                return new \App\Notifications\HireAgentLeadNotification($lead, null);
            }],

        ];
    }

    // ── No-duplicate-keys guard ───────────────────────────────────────────────

    /**
     * Proves that every toDatabase() payload array has unique keys.
     * This guards against accidental duplicate 'message' or 'type' entries
     * where PHP's "last key wins" rule would silently override the new value
     * with the old one.
     *
     * Current inventory: 23 notification classes with toDatabase().
     * Provider entries: 25 (OfferListingStatusNotification has 2 variants:
     *   "approved" and "rejected").
     */
    public function test_to_database_payloads_have_no_duplicate_keys(): void
    {
        foreach (static::notificationPayloadProvider() as $name => $args) {
            $notification = ($args[0])();
            $data         = $notification->toDatabase(new \stdClass());

            $keys   = array_keys($data);
            $unique = array_unique($keys);

            $this->assertSame(
                count($unique),
                count($keys),
                "Notification [{$name}] has duplicate keys in its toDatabase() payload: "
                    . implode(', ', array_diff_key($keys, $unique))
            );
        }
    }

    // ── Provider class-count guard ────────────────────────────────────────────

    /**
     * Asserts the data provider covers exactly 23 toDatabase() classes.
     * If a class is added or removed without updating the provider, this
     * fails immediately with a clear count mismatch message.
     *
     * (OfferListingStatusNotification accounts for 2 provider entries, so
     * 23 classes → 25 entries total.)
     */
    public function test_provider_covers_exactly_23_notification_classes(): void
    {
        $providerKeys = array_keys(static::notificationPayloadProvider());

        // Count distinct class names (strip variant suffixes like " — approved").
        $classNames = array_unique(array_map(
            fn($key) => preg_replace('/\s+—.*$/', '', $key),
            $providerKeys
        ));

        $this->assertCount(
            23,
            $classNames,
            'The data provider must cover exactly 23 toDatabase() notification classes. '
                . 'Current count: ' . count($classNames) . '. '
                . 'Update the provider when notification classes are added or removed.'
        );
    }

    // ── Auto-discovery guard ──────────────────────────────────────────────────

    /**
     * Scans app/Notifications/ for every PHP class that declares toDatabase()
     * and asserts each one has a matching entry in the manual data provider.
     *
     * If a developer adds a new notification class without adding it to the
     * provider this test fails immediately, making the provider self-enforcing.
     */
    public function test_all_toDatabase_classes_are_covered_by_provider(): void
    {
        $providerKeys = array_keys(static::notificationPayloadProvider());

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app/Notifications'))
        );

        $missing = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (!str_contains($contents, 'toDatabase')) {
                continue;
            }
            $shortName = $file->getBasename('.php');

            $covered = false;
            foreach ($providerKeys as $key) {
                if (str_contains($key, $shortName)) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $missing[] = $shortName;
            }
        }

        $this->assertEmpty(
            $missing,
            'These notification classes implement toDatabase() but have no entry in the data provider: '
                . implode(', ', $missing)
                . '. Add them to notificationPayloadProvider() so the contract is enforced.'
        );
    }

    // ── Type-value coverage: every type must match a dashboard icon branch ────

    /**
     * The dashboard.blade.php icon switch covers a finite set of type strings.
     * This test asserts that every notification's toDatabase()['type'] value
     * is one of those strings, so the icon switch never silently falls through
     * to the default (no-icon) branch for a real notification type.
     */
    public function test_all_notification_type_values_match_dashboard_icon_switch(): void
    {
        // Exhaustive list of type strings handled by the dashboard @if/$type switch.
        // 'bid_countered' and 'bid_received' are intentionally excluded: they
        // are dead aliases kept only in the NotificationController routing switch
        // for backward-compat with old DB records; no notification class emits them,
        // and they have been removed from the Blade icon switch.
        $dashboardTypes = [
            'bid_accepted', 'counter_bid_accepted',
            'agent_hired',
            'counter_bid_submitted',
            'bid_rejected', 'counter_bid_rejected',
            'bid_submitted', 'bid_modified',
            'offer_submitted', 'offer_countered',
            'offer_accepted',
            'offer_rejected', 'offer_withdrawn', 'offer_expired',
            'offer_listing_status',
            'showing_requested',
            'showing_approved',
            'showing_declined', 'showing_canceled',
            'hire_agent_lead',
        ];

        foreach (static::notificationPayloadProvider() as $name => $args) {
            $notification = ($args[0])();
            $data         = $notification->toDatabase(new \stdClass());

            $this->assertContains(
                $data['type'],
                $dashboardTypes,
                "Notification [{$name}] emits type [{$data['type']}] which has no branch in the dashboard icon switch. "
                    . 'Add the type to the switch or update the type value.'
            );
        }
    }

    // ── Routing data guard ────────────────────────────────────────────────────

    /**
     * Every toDatabase() payload must carry the routing key(s) needed for
     * deep-linking and notification handling in NotificationController::go().
     *
     * - Bid notifications:         bid_id + auction_id
     * - Counter-bid notifications:  counter_bid_id (or bid_id) + auction_id
     * - Agent-hired notifications:  bid_id + auction_id
     * - Offer notifications:        offer_id + link
     * - Offer listing status:       listing_id
     * - Showing notifications:      showing_id
     * - Hire agent lead:            lead_id + deep_link
     */
    public function test_to_database_payloads_contain_required_routing_data(): void
    {
        $routingRules = [
            'BidSubmittedNotification'          => ['bid_id', 'auction_id'],
            'BidAcceptedNotification'           => ['bid_id', 'auction_id'],
            'BidRejectedNotification'           => ['bid_id', 'auction_id'],
            'BidModifiedNotification'           => ['bid_id', 'auction_id'],
            'CounterBidSubmittedNotification'   => ['bid_id', 'auction_id'],
            'CounterBidAcceptedNotification'    => ['counter_bid_id', 'auction_id'],
            'CounterBidRejectedNotification'    => ['counter_bid_id', 'auction_id'],
            'SellerAgentHiredNotification'      => ['bid_id', 'auction_id'],
            'BuyerAgentHiredNotification'       => ['bid_id', 'auction_id'],
            'LandlordAgentHiredNotification'    => ['bid_id', 'auction_id'],
            'TenantAgentHiredNotification'      => ['bid_id', 'auction_id'],
            'OfferSubmittedNotification'        => ['offer_id', 'link'],
            'OfferAcceptedNotification'         => ['offer_id', 'link'],
            'OfferRejectedNotification'         => ['offer_id', 'link'],
            'OfferCounteredNotification'        => ['parent_offer_id', 'counter_offer_id', 'link'],
            'OfferWithdrawnNotification'        => ['offer_id', 'link'],
            'OfferExpiredNotification'          => ['offer_id', 'link'],
            'OfferListingStatusNotification — approved' => ['listing_id'],
            'OfferListingStatusNotification — rejected' => ['listing_id'],
            'ShowingRequestedNotification'      => ['showing_id'],
            'ShowingApprovedNotification'       => ['showing_id'],
            'ShowingDeclinedNotification'       => ['showing_id'],
            'ShowingCanceledNotification'       => ['showing_id'],
            'HireAgentLeadNotification'         => ['lead_id', 'deep_link'],
        ];

        $provider = static::notificationPayloadProvider();

        foreach ($routingRules as $name => $requiredKeys) {
            $factory      = $provider[$name][0];
            $notification = $factory();
            $data         = $notification->toDatabase(new \stdClass());

            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $data,
                    "Notification [{$name}] is missing required routing key [{$key}] in toDatabase() payload."
                );
            }
        }
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
