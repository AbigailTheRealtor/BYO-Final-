<?php

namespace Tests\Feature\Showing;

use App\Enums\ShowingStatus;
use App\Events\ShowingStatusChanged;
use App\Models\OfferAuction;
use App\Models\Showing;
use App\Models\User;
use App\Notifications\Showings\ShowingApprovedNotification;
use App\Notifications\Showings\ShowingCanceledNotification;
use App\Notifications\Showings\ShowingDeclinedNotification;
use App\Notifications\Showings\ShowingRequestedNotification;
use App\Services\Showing\ShowingStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShowingNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeShowing(array $attrs = []): Showing
    {
        $owner   = User::factory()->create();
        $auction = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);

        return Showing::create(array_merge([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => User::factory()->create()->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ], $attrs));
    }

    private function service(): ShowingStatusService
    {
        return app(ShowingStatusService::class);
    }

    // -------------------------------------------------------------------------
    // ShowingStatusChanged event is dispatched on every transition
    // -------------------------------------------------------------------------

    public function test_showing_status_changed_event_is_dispatched_on_approve()
    {
        Event::fake([ShowingStatusChanged::class]);

        $showing = $this->makeShowing();
        $actor   = User::factory()->create();

        $this->service()->approve($showing, [
            'approved_date'       => now()->addDays(3)->format('Y-m-d'),
            'approved_start_time' => '10:00:00',
            'approved_end_time'   => '11:00:00',
        ], $actor);

        Event::assertDispatched(ShowingStatusChanged::class, function ($event) use ($showing) {
            return $event->showing->id === $showing->id
                && $event->previousStatus === ShowingStatus::REQUESTED;
        });
    }

    public function test_showing_status_changed_event_is_dispatched_on_decline()
    {
        Event::fake([ShowingStatusChanged::class]);

        $showing = $this->makeShowing();
        $actor   = User::factory()->create();

        $this->service()->decline($showing, [], $actor);

        Event::assertDispatched(ShowingStatusChanged::class, function ($event) use ($showing) {
            return $event->showing->id === $showing->id
                && $event->previousStatus === ShowingStatus::REQUESTED;
        });
    }

    public function test_showing_status_changed_event_is_dispatched_on_cancel()
    {
        Event::fake([ShowingStatusChanged::class]);

        $showing = $this->makeShowing();
        $actor   = User::factory()->create();

        $this->service()->cancel($showing, $actor);

        Event::assertDispatched(ShowingStatusChanged::class, function ($event) use ($showing) {
            return $event->showing->id === $showing->id
                && $event->previousStatus === ShowingStatus::REQUESTED;
        });
    }

    public function test_showing_status_changed_event_is_dispatched_on_complete()
    {
        Event::fake([ShowingStatusChanged::class]);

        $showing = $this->makeShowing(['status' => ShowingStatus::APPROVED]);
        $actor   = User::factory()->create();

        $this->service()->complete($showing, $actor);

        Event::assertDispatched(ShowingStatusChanged::class, function ($event) use ($showing) {
            return $event->showing->id === $showing->id
                && $event->previousStatus === ShowingStatus::APPROVED;
        });
    }

    // -------------------------------------------------------------------------
    // ShowingRequestedNotification — sent to owner, not to requester/actor
    // -------------------------------------------------------------------------

    public function test_requested_notification_sent_to_listing_owner()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        // Actor is the requester (the person who submitted the showing request)
        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $requester));

        Notification::assertSentTo($owner, ShowingRequestedNotification::class);
    }

    public function test_requested_notification_not_sent_to_actor_who_is_owner()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        // Actor IS the owner themselves — owner should not be notified
        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertNotSentTo($owner, ShowingRequestedNotification::class);
    }

    public function test_requested_notification_database_payload_contains_expected_keys()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $requester));

        Notification::assertSentTo(
            $owner,
            ShowingRequestedNotification::class,
            function ($notification, $channels) use ($owner, $showing) {
                $data = $notification->toDatabase($owner);

                return isset($data['showing_id'])
                    && isset($data['listing_address'])
                    && isset($data['requester_name'])
                    && isset($data['requested_date'])
                    && $data['showing_id'] === $showing->id
                    && $data['type'] === 'showing_requested';
            }
        );
    }

    // -------------------------------------------------------------------------
    // ShowingApprovedNotification — sent to requester, not actor
    // -------------------------------------------------------------------------

    public function test_approved_notification_sent_to_requester()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::APPROVED,
        ]);

        // Actor is the owner (who approved)
        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertSentTo($requester, ShowingApprovedNotification::class);
    }

    public function test_approved_notification_not_sent_to_actor_who_approved()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::APPROVED,
        ]);

        // Actor is the owner — should not be notified
        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertNotSentTo($owner, ShowingApprovedNotification::class);
    }

    public function test_approved_notification_database_payload_contains_expected_keys()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::APPROVED,
            'approved_date'        => now()->addDays(3)->format('Y-m-d'),
            'approved_start_time'  => '10:00:00',
            'approved_end_time'    => '11:00:00',
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertSentTo(
            $requester,
            ShowingApprovedNotification::class,
            function ($notification, $channels) use ($requester, $showing) {
                $data = $notification->toDatabase($requester);

                return isset($data['showing_id'])
                    && isset($data['listing_address'])
                    && isset($data['approved_date'])
                    && $data['showing_id'] === $showing->id
                    && $data['type'] === 'showing_approved';
            }
        );
    }

    // -------------------------------------------------------------------------
    // ShowingDeclinedNotification — sent to requester, not actor
    // -------------------------------------------------------------------------

    public function test_declined_notification_sent_to_requester()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::DECLINED,
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertSentTo($requester, ShowingDeclinedNotification::class);
    }

    public function test_declined_notification_not_sent_to_actor()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::DECLINED,
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertNotSentTo($owner, ShowingDeclinedNotification::class);
    }

    public function test_declined_notification_database_payload_contains_expected_keys()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::DECLINED,
            'owner_message'        => 'Property is under contract.',
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $owner));

        Notification::assertSentTo(
            $requester,
            ShowingDeclinedNotification::class,
            function ($notification, $channels) use ($requester, $showing) {
                $data = $notification->toDatabase($requester);

                return isset($data['showing_id'])
                    && isset($data['listing_address'])
                    && $data['owner_message'] === 'Property is under contract.'
                    && $data['type'] === 'showing_declined';
            }
        );
    }

    // -------------------------------------------------------------------------
    // ShowingCanceledNotification — sent to non-canceling party
    // -------------------------------------------------------------------------

    public function test_canceled_notification_sent_to_owner_when_requester_cancels()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::CANCELED,
            'canceled_at'          => now(),
        ]);

        // Actor is the requester who canceled
        event(new ShowingStatusChanged($showing, ShowingStatus::APPROVED, $requester));

        Notification::assertSentTo($owner, ShowingCanceledNotification::class);
        Notification::assertNotSentTo($requester, ShowingCanceledNotification::class);
    }

    public function test_canceled_notification_sent_to_requester_when_owner_cancels()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::CANCELED,
            'canceled_at'          => now(),
        ]);

        // Actor is the owner who canceled
        event(new ShowingStatusChanged($showing, ShowingStatus::APPROVED, $owner));

        Notification::assertSentTo($requester, ShowingCanceledNotification::class);
        Notification::assertNotSentTo($owner, ShowingCanceledNotification::class);
    }

    public function test_canceled_notification_database_payload_contains_expected_keys()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::CANCELED,
            'canceled_at'          => now(),
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::APPROVED, $requester));

        Notification::assertSentTo(
            $owner,
            ShowingCanceledNotification::class,
            function ($notification, $channels) use ($owner, $showing) {
                $data = $notification->toDatabase($owner);

                return isset($data['showing_id'])
                    && isset($data['listing_address'])
                    && isset($data['canceled_at'])
                    && $data['type'] === 'showing_canceled';
            }
        );
    }

    // -------------------------------------------------------------------------
    // database channel is included for each notification type
    // -------------------------------------------------------------------------

    public function test_all_notification_types_use_database_and_mail_channels()
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::make([
            'id'                   => 99999,
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);
        $showing->setRelation('offerAuction', $auction);
        $showing->setRelation('requester', $requester);

        foreach ([
            new ShowingRequestedNotification($showing),
            new ShowingApprovedNotification($showing),
            new ShowingDeclinedNotification($showing),
            new ShowingCanceledNotification($showing),
        ] as $notification) {
            $channels = $notification->via($owner);
            $this->assertContains('database', $channels);
            $this->assertContains('mail', $channels);
            $this->assertNotContains('broadcast', $channels);
        }
    }

    // -------------------------------------------------------------------------
    // Assigned agent (via user_agents table) receives requested/canceled notifications
    // -------------------------------------------------------------------------

    public function test_requested_notification_sent_to_assigned_agent_via_user_agents()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $agent     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);

        // Create the agent assignment via user_agents table (canonical assignment source)
        \Illuminate\Support\Facades\DB::table('user_agents')->insert([
            'user_id'    => $owner->id,
            'agent_id'   => $agent->id,
            'type'       => 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $requester));

        Notification::assertSentTo($agent, ShowingRequestedNotification::class);
        Notification::assertSentTo($owner, ShowingRequestedNotification::class);
    }

    public function test_requested_notification_not_sent_to_agent_who_is_actor()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $agent = User::factory()->create();
        $auction = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);

        \Illuminate\Support\Facades\DB::table('user_agents')->insert([
            'user_id'    => $owner->id,
            'agent_id'   => $agent->id,
            'type'       => 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => User::factory()->create()->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        // Actor is the agent — agent should not receive a notification
        event(new ShowingStatusChanged($showing, ShowingStatus::REQUESTED, $agent));

        Notification::assertNotSentTo($agent, ShowingRequestedNotification::class);
    }

    public function test_canceled_notification_sent_to_assigned_agent_when_requester_cancels()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $agent     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);

        \Illuminate\Support\Facades\DB::table('user_agents')->insert([
            'user_id'    => $owner->id,
            'agent_id'   => $agent->id,
            'type'       => 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::CANCELED,
            'canceled_at'          => now(),
        ]);

        // Actor is the requester who canceled → owner and agents should be notified
        event(new ShowingStatusChanged($showing, ShowingStatus::APPROVED, $requester));

        Notification::assertSentTo($owner, ShowingCanceledNotification::class);
        Notification::assertSentTo($agent, ShowingCanceledNotification::class);
        Notification::assertNotSentTo($requester, ShowingCanceledNotification::class);
    }

    // -------------------------------------------------------------------------
    // ShowingController::store() dispatches event, triggering owner notification
    // -------------------------------------------------------------------------

    public function test_store_request_dispatches_event_and_notifies_owner()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);

        $this->actingAs($requester)->post(route('showings.store'), [
            'offer_auction_id'     => $auction->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00',
            'requested_end_time'   => '11:00',
        ]);

        Notification::assertSentTo($owner, ShowingRequestedNotification::class);
    }

    public function test_store_request_does_not_notify_the_requester()
    {
        Notification::fake();

        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);

        $this->actingAs($requester)->post(route('showings.store'), [
            'offer_auction_id'     => $auction->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00',
            'requested_end_time'   => '11:00',
        ]);

        Notification::assertNotSentTo($requester, ShowingRequestedNotification::class);
    }

    // -------------------------------------------------------------------------
    // End-to-end: service dispatch stores notification in the notifications table
    // -------------------------------------------------------------------------

    public function test_approve_service_stores_database_notification_for_requester()
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $this->service()->approve($showing, [
            'approved_date'       => now()->addDays(3)->format('Y-m-d'),
            'approved_start_time' => '10:00:00',
            'approved_end_time'   => '11:00:00',
        ], $owner);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'users',
            'notifiable_id'   => $requester->id,
        ]);
    }

    public function test_decline_service_stores_database_notification_for_requester()
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = OfferAuction::factory()->sellerListing()->create(['user_id' => $owner->id]);
        $showing   = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $this->service()->decline($showing, ['owner_message' => 'No longer available.'], $owner);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'users',
            'notifiable_id'   => $requester->id,
        ]);
    }
}
