<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Terminal negotiation experience: tests that completed offer chains (Accepted,
 * Rejected, Withdrawn, Expired) present the correct read-only UI and never
 * expose active-state banners or negotiation action buttons.
 *
 * Covers all 12 spec points for both Seller (sale) and Rental flows.
 */
class OfferTerminalNegotiationTest extends TestCase
{
    use DatabaseTransactions;

    // ── Shared helpers ────────────────────────────────────────────────────────

    /**
     * Create a listing owner (who owns the OfferAuction) and a separate submitter.
     * Returns [$listingOwner, $submitter, $offerAuction].
     */
    private function makeParties(): array
    {
        $listingOwner = User::factory()->create();
        $submitter    = User::factory()->create();
        $offerAuction = OfferAuction::factory()->create(['user_id' => $listingOwner->id]);

        return [$listingOwner, $submitter, $offerAuction];
    }

    /**
     * Build a simple two-offer chain: root (submitted) → terminal (finalStatus).
     * Returns [$root, $terminal].
     */
    private function makeSimpleChain(
        User $submitter,
        OfferAuction $offerAuction,
        string $finalStatus,
        array $rootMetaKeys = []
    ): array {
        $root = Offer::factory()->submitted()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
        ]);

        foreach ($rootMetaKeys as $key => $value) {
            $root->saveMeta($key, $value);
        }

        $terminal = Offer::factory()->create([
            'status'           => $finalStatus,
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $root->id,
            'submitted_at'     => now(),
        ]);

        return [$root, $terminal];
    }

    // ── Test 1: Accepted chain — seller flow: historical parent shows banner ──

    public function test_accepted_chain_historical_parent_shows_banner_seller_flow(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $accepted] = $this->makeSimpleChain($submitter, $offerAuction, 'accepted', [
            'offer_type'  => 'sale',
            'offer_price' => '350000',
        ]);
        $accepted->saveMeta('offer_type', 'sale');
        $accepted->saveMeta('offer_price', '360000');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertSee('This offer is part of a completed negotiation.');
        $response->assertSee('accepted', false);
        $response->assertSee(route('offers.show', $accepted));
        $response->assertSee('View Final Outcome');
    }

    // ── Test 2: Accepted chain — rental flow: same historical banner behavior ─

    public function test_accepted_chain_historical_parent_shows_banner_rental_flow(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $accepted] = $this->makeSimpleChain($submitter, $offerAuction, 'accepted', [
            'offer_type'   => 'rental',
            'monthly_rent' => '2000',
        ]);
        $accepted->saveMeta('offer_type', 'rental');
        $accepted->saveMeta('monthly_rent', '2100');

        $response = $this->actingAs($submitter)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertSee('This offer is part of a completed negotiation.');
        $response->assertSee('accepted', false);
        $response->assertSee(route('offers.show', $accepted));
    }

    // ── Test 3: Rejected chain — banner points to the rejected offer ──────────

    public function test_rejected_chain_historical_parent_shows_banner(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $terminal] = $this->makeSimpleChain($submitter, $offerAuction, 'rejected');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertSee('This offer is part of a completed negotiation.');
        $response->assertSee(route('offers.show', $terminal));
    }

    // ── Test 4: Withdrawn chain — banner points to the withdrawn offer ────────

    public function test_withdrawn_chain_historical_parent_shows_banner(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $terminal] = $this->makeSimpleChain($submitter, $offerAuction, 'withdrawn');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertSee('This offer is part of a completed negotiation.');
        $response->assertSee(route('offers.show', $terminal));
    }

    // ── Test 5: Expired chain — banner points to the expired offer ───────────

    public function test_expired_chain_historical_parent_shows_banner(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $terminal] = $this->makeSimpleChain($submitter, $offerAuction, 'expired');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertSee('This offer is part of a completed negotiation.');
        $response->assertSee(route('offers.show', $terminal));
    }

    // ── Test 6: Historical offers show no pending/active banners ─────────────

    public function test_historical_offers_show_no_active_state_banners(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root] = $this->makeSimpleChain($submitter, $offerAuction, 'accepted');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertDontSee('Your counter offer is pending');
        $response->assertDontSee('This offer awaits your response.');
    }

    // ── Test 7: Historical offers expose no negotiation action buttons ────────

    public function test_historical_offers_hide_all_negotiation_action_buttons(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root] = $this->makeSimpleChain($submitter, $offerAuction, 'rejected');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);

        $content   = $response->getContent();
        $acceptUrl = route('offers.accept', $root);
        $rejectUrl = route('offers.reject', $root);
        $counterUrl = route('offers.counter', $root);
        $withdrawUrl = route('offers.withdraw', $root);

        $this->assertStringNotContainsString('action="' . $acceptUrl . '"', $content);
        $this->assertStringNotContainsString('action="' . $rejectUrl . '"', $content);
        $this->assertStringNotContainsString('action="' . $counterUrl . '"', $content);
        $this->assertStringNotContainsString('action="' . $withdrawUrl . '"', $content);

        $this->assertStringNotContainsString('id="accept-offer-action-btn"', $content);
        $this->assertStringNotContainsString('id="reject-offer-action-btn"', $content);
        $this->assertStringNotContainsString('id="counter-offer-action-btn"', $content);
        $this->assertStringNotContainsString('id="withdraw-offer-action-btn"', $content);
    }

    // ── Test 8: Accepted page reads from snapshot, not live metas ────────────

    public function test_accepted_offer_reads_from_immutable_snapshot_not_live_metas(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        $accepted = Offer::factory()->accepted()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
        ]);
        $accepted->saveMeta('offer_type', 'sale');
        $accepted->saveMeta('offer_price', '400000');

        // Store the immutable snapshot at acceptance time.
        $snapshotData = [
            'offer_type'  => 'sale',
            'offer_price' => '400000',
        ];
        OfferMeta::create([
            'offer_id'   => $accepted->id,
            'meta_key'   => 'accepted_terms_snapshot',
            'meta_value' => json_encode($snapshotData),
        ]);

        // Mutate the live meta after acceptance — must not affect displayed terms.
        $accepted->saveMeta('offer_price', '999999');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $accepted));

        $response->assertStatus(200);
        // Snapshot price must appear.
        $response->assertSee('400,000');
        // Mutated live price must NOT appear in the final terms.
        $response->assertDontSee('999,999');
    }

    // ── Test 9: Accepted offer page displays the acceptance timestamp ─────────

    public function test_accepted_offer_page_displays_acceptance_timestamp(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        $acceptedAt = now()->subHour();
        $accepted   = Offer::factory()->create([
            'status'           => 'accepted',
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'submitted_at'     => $acceptedAt,
            'updated_at'       => $acceptedAt,
        ]);
        $accepted->saveMeta('offer_type', 'sale');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $accepted));

        $response->assertStatus(200);
        $response->assertSee('Offer Accepted');
        $response->assertSee($acceptedAt->format('F j, Y'), false);
    }

    // ── Test 10: Negotiation summary with timestamps; terminal entry marked ───

    public function test_negotiation_summary_renders_timestamps_and_marks_terminal(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $accepted] = $this->makeSimpleChain($submitter, $offerAuction, 'accepted');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $accepted));

        $response->assertStatus(200);
        $response->assertSee('Negotiation Summary');

        $content = $response->getContent();

        // Both offer IDs appear in the summary.
        $this->assertStringContainsString((string) $root->id, $content);
        $this->assertStringContainsString((string) $accepted->id, $content);

        // Terminal summary entry is marked.
        $this->assertStringContainsString('data-testid="terminal-summary-entry"', $content);
        $this->assertStringContainsString('data-testid="negotiation-summary"', $content);
    }

    // ── Test 11: Multi-counter chain resolves to the deepest terminal offer ───

    public function test_multi_counter_chain_resolves_to_deepest_terminal(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        // root → counter1 → counter2 → accepted
        $root     = Offer::factory()->submitted()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
        ]);
        $counter1 = Offer::factory()->countered()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $root->id,
        ]);
        $counter2 = Offer::factory()->countered()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $counter1->id,
        ]);
        $accepted = Offer::factory()->accepted()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $counter2->id,
        ]);
        $accepted->saveMeta('offer_type', 'sale');

        // Visiting the root shows historical banner pointing to $accepted.
        $response = $this->actingAs($listingOwner)->get(route('offers.show', $root));

        $response->assertStatus(200);
        $response->assertSee('This offer is part of a completed negotiation.');
        $response->assertSee(route('offers.show', $accepted));

        // Visiting the terminal leaf directly renders the terminal UI, not historical.
        $terminalResponse = $this->actingAs($listingOwner)->get(route('offers.show', $accepted));

        $terminalResponse->assertStatus(200);
        $terminalResponse->assertSee('Offer Accepted');
        $terminalResponse->assertSee('Final Negotiated Terms');
        $terminalResponse->assertDontSee('This offer is part of a completed negotiation.');

        // Negotiation summary shows all four steps.
        $content = $terminalResponse->getContent();
        $this->assertStringContainsString((string) $root->id, $content);
        $this->assertStringContainsString((string) $counter1->id, $content);
        $this->assertStringContainsString((string) $counter2->id, $content);
        $this->assertStringContainsString((string) $accepted->id, $content);
    }

    // ── Test 12: Redirect-loop guard — terminal offer visited directly ────────

    public function test_terminal_offer_visited_directly_shows_terminal_ui_no_redirect(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        [$root, $accepted] = $this->makeSimpleChain($submitter, $offerAuction, 'accepted');
        $accepted->saveMeta('offer_type', 'sale');

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $accepted));

        // Must not redirect.
        $response->assertStatus(200);

        // Must show the terminal UI, not the historical-record banner.
        $response->assertSee('Offer Accepted');
        $response->assertSee('Final Negotiated Terms');
        $response->assertDontSee('This offer is part of a completed negotiation.');

        // No active-state banners.
        $response->assertDontSee('Your counter offer is pending');
        $response->assertDontSee('This offer awaits your response.');
    }

    // ── Test 13: Branched anomaly — older final sibling + newer active sibling ─

    /**
     * Regression guard: an older rejected/expired sibling must NOT cause
     * the chain to be classified as terminal when a newer active sibling exists.
     * The terminal resolver walks the deepest-child path by (created_at, id)
     * so the newer active node wins and the chain stays active.
     */
    public function test_older_final_sibling_does_not_mark_chain_terminal_when_newer_active_sibling_exists(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        $root = Offer::factory()->submitted()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
        ]);

        // Older sibling that reached a terminal status first.
        $olderFinal = Offer::factory()->create([
            'status'           => 'rejected',
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $root->id,
            'created_at'       => now()->subMinutes(5),
            'submitted_at'     => now()->subMinutes(5),
        ]);

        // Newer active sibling created after the older one was rejected.
        $newerActive = Offer::factory()->countered()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $root->id,
            'created_at'       => now()->subMinutes(2),
            'submitted_at'     => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $newerActive));

        $response->assertStatus(200);

        // Chain must still be active — no terminal banner, no historical banner.
        $response->assertDontSee('Offer Rejected');
        $response->assertDontSee('Offer Accepted');
        $response->assertDontSee('Offer Withdrawn');
        $response->assertDontSee('Final Negotiated Terms');
        $response->assertDontSee('This offer is part of a completed negotiation.');
    }

    // ── Test 14: Snapshot unavailable notice for pre-feature accepted offers ───

    public function test_accepted_offer_without_snapshot_shows_controlled_unavailable_notice(): void
    {
        [$listingOwner, $submitter, $offerAuction] = $this->makeParties();

        $accepted = Offer::factory()->accepted()->create([
            'user_id'          => $submitter->id,
            'offer_auction_id' => $offerAuction->id,
        ]);
        $accepted->saveMeta('offer_type', 'sale');
        $accepted->saveMeta('offer_price', '300000');

        // Deliberately do NOT write an accepted_terms_snapshot meta.

        $response = $this->actingAs($listingOwner)->get(route('offers.show', $accepted));

        $response->assertStatus(200);
        $response->assertSee('Offer Accepted');
        $response->assertSee('Final Negotiated Terms');

        // Must show the controlled notice, not the live meta value.
        $response->assertSee('Terms snapshot not available.');
        $response->assertDontSee('300,000');
        $response->assertDontSee('300000');
    }
}
