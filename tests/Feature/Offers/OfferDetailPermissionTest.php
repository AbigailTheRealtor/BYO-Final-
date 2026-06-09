<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Notifications\Offers\OfferSubmittedNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers:
 * (a) submitter cannot accept/reject/counter their own offer (blade + server-side)
 * (b) recipient can accept/reject/counter
 * (c) after a counter the roles reverse
 * (d) counter form loads with term values from the latest offer
 * (e) Private Notes block is absent from the rendered view
 * (f) submitting an offer creates a database notification for the recipient (listing owner)
 */
class OfferDetailPermissionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Grant all authenticated users access to the offer-playoff gate for these tests.
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');
    }

    private function makeSeller(): User
    {
        return User::factory()->create(['user_type' => 'seller']);
    }

    private function makeBuyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function makeAgent(): User
    {
        return User::factory()->create(['user_type' => 'agent']);
    }

    private function makeAuction(User $seller): OfferAuction
    {
        return OfferAuction::factory()->create(['user_id' => $seller->id]);
    }

    private function makeSubmittedOffer(OfferAuction $auction, User $buyer): Offer
    {
        return Offer::factory()->submitted()->create([
            'user_id'          => $buyer->id,
            'offer_auction_id' => $auction->id,
            'role'             => 'buyer',
        ]);
    }

    private function makeDraftOffer(OfferAuction $auction, User $buyer): Offer
    {
        return Offer::factory()->create([
            'user_id'          => $buyer->id,
            'offer_auction_id' => $auction->id,
            'role'             => 'buyer',
            'status'           => 'draft',
        ]);
    }

    private function allActionsAllowed(): array
    {
        return [
            'can_submit'        => false,
            'can_counter'       => true,
            'can_accept'        => true,
            'can_reject'        => true,
            'can_withdraw'      => false,
            'can_expire'        => false,
            'can_view_timeline' => true,
            'reasons'           => [
                'submit' => '', 'counter' => '', 'accept' => '',
                'reject' => '', 'withdraw' => '', 'expire' => '', 'view_timeline' => '',
            ],
        ];
    }

    // ── (a) Submitter cannot accept/reject/counter their own offer (blade) ──────

    public function test_buyer_submitter_cannot_see_accept_button_on_their_own_offer(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($buyer)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringNotContainsString(
            'action="' . route('offers.accept', $offer) . '"',
            $response->getContent(),
            'Submitter must not see an enabled Accept form.'
        );
    }

    public function test_buyer_submitter_cannot_see_reject_button_on_their_own_offer(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($buyer)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringNotContainsString(
            'action="' . route('offers.reject', $offer) . '"',
            $response->getContent(),
            'Submitter must not see an enabled Reject form.'
        );
    }

    public function test_buyer_submitter_cannot_see_counter_form_on_their_own_offer(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($buyer)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringNotContainsString(
            'action="' . route('offers.counter', $offer) . '"',
            $response->getContent(),
            'Submitter must not see a Counter form action.'
        );
    }

    // ── (a) Server-side: submitter POSTing accept/reject/counter is denied ──────

    public function test_submitter_posting_accept_is_denied_server_side(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($buyer)
            ->postJson(route('offers.accept', $offer));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Cannot accept: you submitted this offer and must wait for the other party to respond.',
        ]);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'Offer status must stay submitted after a blocked accept.');
    }

    public function test_submitter_posting_reject_is_denied_server_side(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($buyer)
            ->postJson(route('offers.reject', $offer));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Cannot reject: you submitted this offer and must wait for the other party to respond.',
        ]);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'Offer status must stay submitted after a blocked reject.');
    }

    public function test_submitter_posting_counter_is_denied_server_side(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($buyer)
            ->postJson(route('offers.counter', $offer));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Cannot counter: you submitted this offer and must wait for the other party to respond.',
        ]);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'Offer status must stay submitted after a blocked counter attempt.');
    }

    public function test_stranger_posting_accept_is_denied_server_side(): void
    {
        $buyer    = $this->makeBuyer();
        $seller   = $this->makeSeller();
        $stranger = User::factory()->create(['user_type' => 'seller']);
        $offer    = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($stranger)
            ->postJson(route('offers.accept', $offer));

        $response->assertStatus(422);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'A stranger must not be able to accept an offer.');
    }

    public function test_stranger_posting_counter_is_denied_server_side(): void
    {
        $buyer    = $this->makeBuyer();
        $seller   = $this->makeSeller();
        $stranger = User::factory()->create(['user_type' => 'seller']);
        $offer    = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($stranger)
            ->postJson(route('offers.counter', $offer), ['offer_price' => '400000']);

        $response->assertStatus(422);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'A stranger who is not a party to the negotiation must not be able to counter.');
    }

    public function test_non_party_agent_posting_accept_is_denied_server_side(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $agent  = $this->makeAgent();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($agent)
            ->postJson(route('offers.accept', $offer));

        $response->assertStatus(422);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'An agent with no party relationship to this offer must not be able to accept it.');
    }

    public function test_non_party_agent_posting_reject_is_denied_server_side(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $agent  = $this->makeAgent();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($agent)
            ->postJson(route('offers.reject', $offer));

        $response->assertStatus(422);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'An agent with no party relationship to this offer must not be able to reject it.');
    }

    public function test_non_party_agent_posting_counter_is_denied_server_side(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $agent  = $this->makeAgent();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($agent)
            ->postJson(route('offers.counter', $offer), ['offer_price' => '400000']);

        $response->assertStatus(422);
        $offer->refresh();
        $this->assertSame('submitted', $offer->status,
            'An agent with no party relationship to this offer must not be able to counter it.');
    }

    // ── (b) Recipient sees Accept / Reject / Counter ──────────────────────────

    public function test_recipient_seller_sees_accept_and_reject_buttons(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($seller)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString(
            'action="' . route('offers.accept', $offer) . '"',
            $content, 'Recipient must see an enabled Accept form.'
        );
        $this->assertStringContainsString(
            'action="' . route('offers.reject', $offer) . '"',
            $content, 'Recipient must see an enabled Reject form.'
        );
    }

    public function test_recipient_seller_sees_counter_form(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $response = $this->actingAs($seller)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringContainsString(
            'action="' . route('offers.counter', $offer) . '"',
            $response->getContent(), 'Recipient must see an enabled Counter form.'
        );
    }

    // ── (c) After a counter, roles reverse ───────────────────────────────────

    public function test_after_seller_counters_buyer_gains_action_buttons(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $parent = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $counterChild = Offer::factory()->countered()->create([
            'user_id'          => $seller->id,
            'offer_auction_id' => $parent->offer_auction_id,
            'parent_offer_id'  => $parent->id,
            'role'             => 'buyer',
        ]);

        $response = $this->actingAs($buyer)->get(route('offers.show', $counterChild));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString(
            'action="' . route('offers.accept', $counterChild) . '"',
            $content, 'Buyer (new recipient) must see Accept on the counter offer.'
        );
        $this->assertStringContainsString(
            'action="' . route('offers.counter', $counterChild) . '"',
            $content, 'Buyer (new recipient) must see Counter form on the counter offer.'
        );
    }

    public function test_after_seller_counters_seller_loses_action_buttons(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $parent = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $counterChild = Offer::factory()->countered()->create([
            'user_id'          => $seller->id,
            'offer_auction_id' => $parent->offer_auction_id,
            'parent_offer_id'  => $parent->id,
            'role'             => 'buyer',
        ]);

        $response = $this->actingAs($seller)->get(route('offers.show', $counterChild));
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringNotContainsString(
            'action="' . route('offers.accept', $counterChild) . '"',
            $content, 'Seller (now submitter) must not see Accept on their own counter offer.'
        );
        $this->assertStringNotContainsString(
            'action="' . route('offers.counter', $counterChild) . '"',
            $content, 'Seller (now submitter) must not see Counter form on their own counter offer.'
        );
    }

    public function test_after_seller_counters_seller_posting_accept_is_denied(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $parent = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $counterChild = Offer::factory()->countered()->create([
            'user_id'          => $seller->id,
            'offer_auction_id' => $parent->offer_auction_id,
            'parent_offer_id'  => $parent->id,
            'role'             => 'buyer',
        ]);

        $response = $this->actingAs($seller)
            ->postJson(route('offers.accept', $counterChild));

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'submitted this offer',
            $response->json('message') ?? '',
            'Seller posting accept on their own counter must be denied with the identity check message.'
        );
    }

    public function test_after_seller_counters_buyer_posting_accept_is_allowed(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $parent = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);

        $counterChild = Offer::factory()->countered()->create([
            'user_id'          => $seller->id,
            'offer_auction_id' => $parent->offer_auction_id,
            'parent_offer_id'  => $parent->id,
            'role'             => 'buyer',
        ]);

        $this->mock(OfferWorkflowFacade::class, function ($mock) {
            $mock->shouldReceive('accept')->andReturn(['allowed' => true, 'reason' => '']);
        });

        $response = $this->actingAs($buyer)
            ->postJson(route('offers.accept', $counterChild));

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Offer accepted.']);
    }

    // ── (d) Counter form pre-filled from current offer's metas ───────────────

    public function test_counter_form_prefills_offer_price_from_current_offer_metas(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);
        $offer->saveMeta('offer_price', '475000');
        $offer->saveMeta('offer_type', 'sale');

        $this->mock(OfferAvailableActionsService::class, function ($mock) {
            $mock->shouldReceive('forOffer')->andReturn($this->allActionsAllowed());
        });

        $response = $this->actingAs($seller)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringContainsString('475000.00', $response->getContent(),
            'Counter form must show offer price prefilled from metas.');
    }

    public function test_counter_form_prefills_expires_at_from_current_offer_metas(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);
        $offer->saveMeta('expires_at', '2027-03-15');

        $this->mock(OfferAvailableActionsService::class, function ($mock) {
            $mock->shouldReceive('forOffer')->andReturn($this->allActionsAllowed());
        });

        $response = $this->actingAs($seller)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringContainsString('2027-03-15', $response->getContent(),
            'Counter form must show expires_at prefilled from metas.');
    }

    public function test_counter_form_prefills_custom_terms_from_current_offer_metas(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeSubmittedOffer($this->makeAuction($seller), $buyer);
        $offer->saveMeta('custom_terms', 'Seller pays closing costs');

        $this->mock(OfferAvailableActionsService::class, function ($mock) {
            $mock->shouldReceive('forOffer')->andReturn($this->allActionsAllowed());
        });

        $response = $this->actingAs($seller)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringContainsString('Seller pays closing costs', $response->getContent(),
            'Counter form must show custom_terms prefilled from metas.');
    }

    // ── (e) Private Notes is absent from the rendered view ────────────────────

    public function test_private_notes_section_is_absent_for_offer_owner(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeDraftOffer($this->makeAuction($seller), $buyer);
        $offer->saveMeta('notes', 'This is a private internal note.');

        $response = $this->actingAs($buyer)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringNotContainsString('Private Notes', $content,
            'Private Notes header must not appear on the offer detail page.');
        $this->assertStringNotContainsString('This is a private internal note.', $content,
            'Private Notes content must not be rendered on the offer detail page.');
    }

    public function test_private_notes_section_is_absent_for_non_owner(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        $offer  = $this->makeDraftOffer($this->makeAuction($seller), $buyer);
        $offer->saveMeta('notes', 'Secret note');

        $response = $this->actingAs($seller)->get(route('offers.show', $offer));
        $response->assertStatus(200);

        $this->assertStringNotContainsString('Private Notes', $response->getContent(),
            'Private Notes must not appear for non-owner either.');
    }

    public function test_blade_source_does_not_contain_private_notes_block(): void
    {
        $viewContent = file_get_contents(base_path('resources/views/offers/show.blade.php'));

        $this->assertStringNotContainsString('Private Notes', $viewContent,
            'The "Private Notes" string must be removed from the Blade template.');
    }

    // ── (f) Submitting dispatches OfferSubmittedNotification to the listing owner ──

    public function test_submitting_offer_dispatches_notification_to_listing_owner(): void
    {
        Notification::fake();

        $buyer     = $this->makeBuyer();
        $seller    = $this->makeSeller();
        $auction   = $this->makeAuction($seller);
        $draftOffer = $this->makeDraftOffer($auction, $buyer);

        $this->mock(OfferAvailableActionsService::class, function ($mock) {
            $mock->shouldReceive('forOffer')->andReturn([
                'can_submit'        => true,
                'can_counter'       => false,
                'can_accept'        => false,
                'can_reject'        => false,
                'can_withdraw'      => false,
                'can_expire'        => false,
                'can_view_timeline' => true,
                'reasons'           => [
                    'submit' => '', 'counter' => '', 'accept' => '',
                    'reject' => '', 'withdraw' => '', 'expire' => '', 'view_timeline' => '',
                ],
            ]);
        });

        $this->mock(OfferWorkflowFacade::class, function ($mock) {
            $mock->shouldReceive('submit')->andReturn(['allowed' => true, 'reason' => '']);
        });

        $this->actingAs($buyer)->post(route('offers.submit', $draftOffer));

        Notification::assertSentTo(
            $seller,
            OfferSubmittedNotification::class,
            fn ($n) => $n->offer->id === $draftOffer->id
        );
    }

    public function test_submitting_offer_does_not_notify_the_submitter(): void
    {
        Notification::fake();

        $buyer     = $this->makeBuyer();
        $seller    = $this->makeSeller();
        $auction   = $this->makeAuction($seller);
        $draftOffer = $this->makeDraftOffer($auction, $buyer);

        $this->mock(OfferAvailableActionsService::class, function ($mock) {
            $mock->shouldReceive('forOffer')->andReturn([
                'can_submit'        => true,
                'can_counter'       => false,
                'can_accept'        => false,
                'can_reject'        => false,
                'can_withdraw'      => false,
                'can_expire'        => false,
                'can_view_timeline' => true,
                'reasons'           => [
                    'submit' => '', 'counter' => '', 'accept' => '',
                    'reject' => '', 'withdraw' => '', 'expire' => '', 'view_timeline' => '',
                ],
            ]);
        });

        $this->mock(OfferWorkflowFacade::class, function ($mock) {
            $mock->shouldReceive('submit')->andReturn(['allowed' => true, 'reason' => '']);
        });

        $this->actingAs($buyer)->post(route('offers.submit', $draftOffer));

        Notification::assertNotSentTo(
            $buyer,
            OfferSubmittedNotification::class,
            'The submitter must not receive OfferSubmittedNotification on their own submission.'
        );
    }

    public function test_submitting_offer_creates_database_notification_for_recipient(): void
    {
        $buyer     = $this->makeBuyer();
        $seller    = $this->makeSeller();
        $auction   = $this->makeAuction($seller);
        $draftOffer = $this->makeDraftOffer($auction, $buyer);

        $this->mock(OfferAvailableActionsService::class, function ($mock) {
            $mock->shouldReceive('forOffer')->andReturn([
                'can_submit'        => true,
                'can_counter'       => false,
                'can_accept'        => false,
                'can_reject'        => false,
                'can_withdraw'      => false,
                'can_expire'        => false,
                'can_view_timeline' => true,
                'reasons'           => [
                    'submit' => '', 'counter' => '', 'accept' => '',
                    'reject' => '', 'withdraw' => '', 'expire' => '', 'view_timeline' => '',
                ],
            ]);
        });

        $this->mock(OfferWorkflowFacade::class, function ($mock) {
            $mock->shouldReceive('submit')->andReturn(['allowed' => true, 'reason' => '']);
        });

        $this->actingAs($buyer)->post(route('offers.submit', $draftOffer));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'users',
            'notifiable_id'   => $seller->id,
            'type'            => OfferSubmittedNotification::class,
        ]);
    }

    // ── Dashboard notification filter includes OfferSubmittedNotification ──────

    public function test_dashboard_notification_filter_includes_offer_submitted_type(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/DashboardController.php'));

        $this->assertStringContainsString(
            'App\Notifications\Offers\OfferSubmittedNotification',
            $source,
            'DashboardController must include OfferSubmittedNotification in its type filter.'
        );
    }

    // ── (f2) Counter creates OfferSubmittedNotification for counter recipient ──

    public function test_countering_dispatches_submitted_notification_to_counter_recipient(): void
    {
        Notification::fake();

        $buyer   = $this->makeBuyer();
        $seller  = $this->makeSeller();
        $auction = $this->makeAuction($seller);
        $offer   = $this->makeSubmittedOffer($auction, $buyer);

        $this->actingAs($seller)->post(route('offers.counter', $offer), [
            'offer_price' => '410000',
        ]);

        Notification::assertSentTo(
            $buyer,
            OfferSubmittedNotification::class
        );
    }

    public function test_countering_creates_database_submitted_notification_for_counter_recipient(): void
    {
        $buyer   = $this->makeBuyer();
        $seller  = $this->makeSeller();
        $auction = $this->makeAuction($seller);
        $offer   = $this->makeSubmittedOffer($auction, $buyer);

        $this->actingAs($seller)->post(route('offers.counter', $offer), [
            'offer_price' => '410000',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'users',
            'notifiable_id'   => $buyer->id,
            'type'            => OfferSubmittedNotification::class,
        ]);
    }

    // ── (g) Dashboard notification click-through routes to /offers/{id} ────────

    public function test_offer_submitted_notification_click_through_redirects_to_offer_show(): void
    {
        $buyer   = $this->makeBuyer();
        $seller  = $this->makeSeller();
        $auction = $this->makeAuction($seller);
        $offer   = $this->makeSubmittedOffer($auction, $seller);

        // Create a real DB notification for the seller with the offer link payload.
        $seller->notify(new OfferSubmittedNotification($offer));

        $notification = $seller->notifications()->latest()->first();
        $this->assertNotNull($notification, 'A database notification must exist for the seller.');

        $response = $this->actingAs($seller)
            ->get(route('notifications.go', $notification->id));

        $expectedUrl = route('offers.show', $offer);
        $response->assertRedirect($expectedUrl);
    }

    // ── (h) Stale-parent offers cannot be acted on after a counter ────────────

    private function makeStaleParentWithCounter(User $seller, User $buyer): array
    {
        $auction = $this->makeAuction($seller);
        $parent  = $this->makeSubmittedOffer($auction, $buyer);

        $child = Offer::factory()->countered()->create([
            'user_id'          => $seller->id,
            'offer_auction_id' => $auction->id,
            'parent_offer_id'  => $parent->id,
            'role'             => 'buyer',
        ]);

        return [$parent, $child];
    }

    public function test_stale_parent_accept_is_denied_after_counter(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        [$parent] = $this->makeStaleParentWithCounter($seller, $buyer);

        $response = $this->actingAs($seller)
            ->postJson(route('offers.accept', $parent));

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'counter offer is already pending',
            $response->json('message') ?? '',
            'Stale parent accept must be denied with the active-leaf guard message.'
        );
    }

    public function test_stale_parent_reject_is_denied_after_counter(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        [$parent] = $this->makeStaleParentWithCounter($seller, $buyer);

        $response = $this->actingAs($seller)
            ->postJson(route('offers.reject', $parent));

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'counter offer is already pending',
            $response->json('message') ?? '',
            'Stale parent reject must be denied with the active-leaf guard message.'
        );
    }

    public function test_stale_parent_counter_is_denied_after_counter(): void
    {
        $buyer  = $this->makeBuyer();
        $seller = $this->makeSeller();
        [$parent] = $this->makeStaleParentWithCounter($seller, $buyer);

        $response = $this->actingAs($seller)
            ->postJson(route('offers.counter', $parent), ['offer_price' => '500000']);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'counter offer is already pending',
            $response->json('message') ?? '',
            'Stale parent counter must be denied with the active-leaf guard message.'
        );
    }
}
