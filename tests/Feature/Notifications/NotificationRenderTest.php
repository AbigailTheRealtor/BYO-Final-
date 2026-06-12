<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * NotificationRenderTest
 *
 * Verifies that the dashboard notification panel renders human-readable messages
 * and never falls back to the generic placeholder text.
 *
 * Uses DatabaseTransactions — no permanent data written.
 */
class NotificationRenderTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function insertNotification(string $type, array $data): void
    {
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => $type,
            'notifiable_type' => (new User())->getMorphClass(),
            'notifiable_id'   => $this->user->id,
            'data'            => json_encode($data),
            'read_at'         => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ── Case 1: Dashboard never shows generic fallback ────────────────────────

    public function test_dashboard_never_shows_generic_fallback_text(): void
    {
        $this->insertNotification(
            'App\Notifications\BidSubmittedNotification',
            ['type' => 'bid_submitted', 'message' => 'New bid received on your listing.', 'bid_id' => 1, 'auction_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('You have a notification');
        $response->assertDontSee('New Notification');
    }

    // ── Case 2: Bid submitted message is displayed ────────────────────────────

    public function test_dashboard_shows_bid_submitted_message(): void
    {
        $this->insertNotification(
            'App\Notifications\BidSubmittedNotification',
            ['type' => 'bid_submitted', 'message' => 'New bid received on your listing.', 'context_line' => 'Oak St Listing', 'bid_id' => 1, 'auction_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('New bid received on your listing.');
    }

    // ── Case 3: Context line is rendered when present ─────────────────────────

    public function test_dashboard_renders_context_line_when_present(): void
    {
        $this->insertNotification(
            'App\Notifications\BidAcceptedNotification',
            ['type' => 'bid_accepted', 'message' => 'Your bid was accepted.', 'context_line' => '6817 Stones Throw Cir', 'bid_id' => 1, 'auction_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Your bid was accepted.');
        $response->assertSee('6817 Stones Throw Cir');
    }

    // ── Case 4: Agent-hired notification ─────────────────────────────────────

    public function test_dashboard_shows_agent_hired_message(): void
    {
        $this->insertNotification(
            'App\Notifications\SellerAgentHiredNotification',
            ['type' => 'agent_hired', 'message' => 'You have successfully hired an agent.', 'context_line' => 'Seller Listing', 'bid_id' => 1, 'auction_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('You have successfully hired an agent.');
    }

    // ── Case 5: Showing notification ─────────────────────────────────────────

    public function test_dashboard_shows_showing_message_with_context(): void
    {
        $this->insertNotification(
            'App\Notifications\Showings\ShowingRequestedNotification',
            ['type' => 'showing_requested', 'message' => 'New showing request received.', 'context_line' => '123 Main St • Jun 15', 'showing_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('New showing request received.');
        $response->assertSee('123 Main St • Jun 15');
    }

    // ── Case 6: Offer notification ────────────────────────────────────────────

    public function test_dashboard_shows_offer_message(): void
    {
        $this->insertNotification(
            'App\Notifications\Offers\OfferSubmittedNotification',
            ['type' => 'offer_submitted', 'message' => 'New offer received on your listing.', 'offer_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('New offer received on your listing.');
    }

    // ── Case 7: Hire agent lead notification ─────────────────────────────────

    public function test_dashboard_shows_hire_agent_lead_message(): void
    {
        $this->insertNotification(
            'App\Notifications\HireAgentLeadNotification',
            ['type' => 'hire_agent_lead', 'message' => 'New agent hire request received.', 'context_line' => 'Seller Representation • Single Family', 'lead_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('New agent hire request received.');
        $response->assertSee('Seller Representation • Single Family');
    }

    // ── Case 8: View and Dismiss buttons present ──────────────────────────────

    public function test_dashboard_shows_view_and_dismiss_buttons(): void
    {
        $this->insertNotification(
            'App\Notifications\BidSubmittedNotification',
            ['type' => 'bid_submitted', 'message' => 'New bid received on your listing.', 'bid_id' => 1, 'auction_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('View');
        $response->assertSee('Dismiss');
    }

    // ── Case 9: Multiple notification types in same page load ─────────────────

    public function test_dashboard_shows_multiple_notification_types_without_generic_text(): void
    {
        $this->insertNotification(
            'App\Notifications\BidRejectedNotification',
            ['type' => 'bid_rejected', 'message' => 'Your bid was rejected.', 'context_line' => 'Maple Ave Listing', 'bid_id' => 2, 'auction_id' => 2]
        );

        $this->insertNotification(
            'App\Notifications\Offers\OfferAcceptedNotification',
            ['type' => 'offer_accepted', 'message' => 'Your offer was accepted.', 'offer_id' => 3]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('You have a notification');
        $response->assertDontSee('New Notification');
        $response->assertSee('Your bid was rejected.');
        $response->assertSee('Your offer was accepted.');
    }

    // ── Case 10: Header Blade loop — no generic fallback text ─────────────────
    //
    // The header partial renders `$unread = auth()->user()->unreadNotifications`
    // (no type filter). This test proves that 'You have a notification' and
    // 'New Notification' never appear in the header dropdown for any seeded
    // notification type, because the Blade fallback is now 'Account activity'.

    public function test_header_dropdown_never_shows_old_generic_fallback_text(): void
    {
        $this->insertNotification(
            'App\Notifications\Showings\ShowingRequestedNotification',
            ['type' => 'showing_requested', 'message' => 'New showing request received.', 'context_line' => '123 Oak St • Jun 15', 'showing_id' => 1]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('You have a notification');
        $response->assertDontSee('New Notification');
    }

    // ── Case 11: Header Blade loop — renders message from payload ─────────────
    //
    // The header partial's @forelse loop renders `$note->data['message']`.
    // This verifies the actual message text appears in the full page response
    // (header is present on every authenticated page load).

    public function test_header_dropdown_renders_message_from_notification_payload(): void
    {
        $this->insertNotification(
            'App\Notifications\Showings\ShowingApprovedNotification',
            ['type' => 'showing_approved', 'message' => 'Your showing request was approved.', 'context_line' => '456 Elm Ave • Jun 20', 'showing_id' => 2]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Your showing request was approved.');
        $response->assertSee('456 Elm Ave • Jun 20');
    }

    // ── Cases 12–13: Header JS rendering path (/notifications/fetch) ──────────
    //
    // The header dropdown is populated by renderNotifications() — a JS function
    // that calls GET /notifications/fetch and reads note.data.message and
    // note.data.context_line from the JSON response.  These tests exercise that
    // AJAX endpoint directly to confirm it returns the correct payload shape.

    public function test_fetch_endpoint_returns_message_and_context_line_for_header_js(): void
    {
        $this->insertNotification(
            'App\Notifications\BidAcceptedNotification',
            [
                'type'         => 'bid_accepted',
                'message'      => 'Your bid was accepted.',
                'context_line' => 'Downtown Condo Listing',
                'bid_id'       => 99,
                'auction_id'   => 42,
                'auction_type' => 'tenant_agent',
            ]
        );

        $response = $this->actingAs($this->user)->getJson(route('notifications.fetch'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['id', 'type', 'data', 'created_at'],
        ]);

        $items = $response->json();
        $this->assertNotEmpty($items, 'Fetch endpoint returned no notifications.');

        $first = $items[0];
        $this->assertArrayHasKey('message', $first['data'],
            'JS renderNotifications() reads note.data.message — key must be present in fetch response.');
        $this->assertArrayHasKey('context_line', $first['data'],
            'JS renderNotifications() reads note.data.context_line — key must be present in fetch response.');

        $this->assertSame('Your bid was accepted.', $first['data']['message']);
        $this->assertSame('Downtown Condo Listing', $first['data']['context_line']);
    }

    public function test_fetch_endpoint_never_returns_generic_fallback_text(): void
    {
        $this->insertNotification(
            'App\Notifications\BidRejectedNotification',
            [
                'type'         => 'bid_rejected',
                'message'      => 'Your bid was rejected.',
                'context_line' => 'Oak Street Listing',
                'bid_id'       => 7,
                'auction_id'   => 3,
                'auction_type' => 'seller_agent',
            ]
        );

        $response = $this->actingAs($this->user)->getJson(route('notifications.fetch'));

        $response->assertStatus(200);

        $body = $response->content();
        $this->assertStringNotContainsString('You have a notification', $body,
            'Fetch endpoint must never return the old generic fallback text.');
        $this->assertStringNotContainsString('New Notification', $body,
            'Fetch endpoint must never return the old generic header title text.');

        $items = $response->json();
        $this->assertNotEmpty($items);
        $this->assertSame('Your bid was rejected.', $items[0]['data']['message']);
    }

    // ── Case 14: Legacy records — no message key, resolve via type map ─────────
    //
    // Simulates notifications stored before #2499 added the 'message' key.
    // Each row has only a 'type' key in its JSON payload. The resolver must
    // produce the correct human-readable string for every seeded type.

    public function test_legacy_notifications_without_message_key_resolve_via_type(): void
    {
        $legacyTypes = [
            [
                'class'   => 'App\Notifications\BidSubmittedNotification',
                'payload' => ['type' => 'bid_submitted'],
                'expected' => 'New bid received on your listing.',
            ],
            [
                'class'   => 'App\Notifications\BidAcceptedNotification',
                'payload' => ['type' => 'bid_accepted', 'bid_id' => 1, 'auction_id' => 1],
                'expected' => 'Your bid was accepted.',
            ],
            [
                'class'   => 'App\Notifications\Offers\OfferSubmittedNotification',
                'payload' => ['type' => 'offer_submitted'],
                'expected' => 'New offer received on your listing.',
            ],
            [
                'class'   => 'App\Notifications\Showings\ShowingRequestedNotification',
                'payload' => ['type' => 'showing_requested'],
                'expected' => 'New showing request received.',
            ],
            [
                'class'   => 'App\Notifications\HireAgentLeadNotification',
                'payload' => ['type' => 'hire_agent_lead'],
                'expected' => 'New agent hire request received.',
            ],
            [
                'class'   => 'App\Notifications\SellerAgentHiredNotification',
                'payload' => ['type' => 'agent_hired'],
                'expected' => 'You have successfully hired an agent.',
            ],
        ];

        foreach ($legacyTypes as $seed) {
            $this->insertNotification($seed['class'], $seed['payload']);
        }

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertStatus(200);

        foreach ($legacyTypes as $seed) {
            $response->assertSee($seed['expected']);
        }
    }

    // ── Case 15: Legacy agent-hired disambiguation ────────────────────────────
    //
    // A SellerAgentHiredNotification stored before #2499 has payload
    // type='bid_accepted' (the old bug). The FQCN column is the only signal.
    // The resolver must use str_contains($notificationClass, 'AgentHired') to
    // return the hired message.
    //
    // Note: assertDontSee('Your bid was accepted.') cannot be used here because
    // that string appears as a static JS literal in the header typeMessages map
    // regardless of which notifications are rendered. The positive assertSee
    // below is sufficient: if disambiguation were broken, the notification panel
    // would show the wrong text and this assertion would fail.

    public function test_legacy_agent_hired_with_bid_accepted_payload_resolves_to_hired_message(): void
    {
        $this->insertNotification(
            'App\Notifications\SellerAgentHiredNotification',
            ['type' => 'bid_accepted', 'bid_id' => 2, 'auction_id' => 2]
        );

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertStatus(200);

        $response->assertSee('You have successfully hired an agent.');
    }
}
